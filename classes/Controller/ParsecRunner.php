<?php defined('SYSPATH') or die('No direct script access.');

class Controller_ParsecRunner extends Controller_Template
{
    public $template = 'template';

    /** @var array */
    protected $_tasks = array();

    /** @var string */
    protected $_log_dir;

    public function before()
    {
        parent::before();

        // структура БД
        $cch = new Model_Cch();
        $errors = $cch->checkDatabaseStructure();
        if (!empty($errors)) {
            Session::instance()->set('db_error', implode(' ', $errors));
            $this->redirect('parsec/error');
            return;
        }

        // список задач из конфига
        $this->_tasks = (array) Kohana::$config->load('runner');

        // папка логов
        $this->_log_dir = MODPATH . 'parsec' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($this->_log_dir)) {
            @mkdir($this->_log_dir, 0777, true);
        }
    }

    // =========================================================
    // Форма: таблица задач
    // =========================================================

    public function action_index()
    {
        // группируем по полю 'group'
        $groups = array();
        foreach ($this->_tasks as $idx => $t) {
            $g = isset($t['group']) ? $t['group'] : 'Прочее';
            if (!isset($groups[$g])) {
                $groups[$g] = array();
            }
            $t['_idx'] = $idx;   // чтобы знать, какую строку запускать
            $groups[$g][] = $t;
        }

        $content = View::factory('parsec/runner', array(
            'groups'  => $groups,
            'running' => $this->_get_running_tasks(),
            'recent'  => $this->_get_recent_logs(20),
        ));
        $this->template->content = $content;
    }

    // =========================================================
    // Запуск конкретной строки
    // =========================================================

    public function action_run()
    {
        $idx = (int) $this->request->post('idx');

        if (!isset($this->_tasks[$idx])) {
            Session::instance()->set('runner_error', 'Задача не найдена: idx=' . $idx);
            $this->redirect('parsecrunner');
            return;
        }

        $task = $this->_tasks[$idx];

        $res = $this->_start_minion(
            $task['task'],
            isset($task['params']) ? (array) $task['params'] : array()
        );

        if ($res['ok']) {
            Session::instance()->set('runner_info',
                'Запущено: ' . $task['title'] . '. PID=' . $res['pid']);
            $this->redirect('parsecrunner/log/' . $res['token']);
        } else {
            Session::instance()->set('runner_error', $res['error']);
            $this->redirect('parsecrunner');
        }
    }

    // =========================================================
    // Просмотр лога
    // =========================================================

    public function action_log()
    {
        $token = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $this->request->param('id'));
        $log_file = $this->_log_dir . DIRECTORY_SEPARATOR . 'task_' . $token . '.log';

        if (!file_exists($log_file)) {
            throw HTTP_Exception::factory(404, 'Лог не найден');
        }

        if ($this->request->query('ajax')) {
            $this->auto_render = false;

            $text = $this->_tail($log_file, 500);

            // На всякий случай: если лог вдруг в CP1251 — конвертируем.
            if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
                $c = @iconv('windows-1251', 'UTF-8//IGNORE', $text);
                if ($c !== false) {
                    $text = $c;
                }
            }

            $this->response->headers('Content-Type', 'text/plain; charset=utf-8');
            $this->response->body($text);
            return;
        }

        $content = View::factory('parsec/runner_log', array(
            'token'    => $token,
            'log_file' => basename($log_file),
            'size'     => filesize($log_file),
            'mtime'    => filemtime($log_file),
        ));
        $this->template->content = $content;
    }

    // =========================================================
    // Запуск Minion в фоне
    // =========================================================

    protected function _start_minion($task, array $params = array())
    {
        $php    = 'c:\\xampp\\php\\php.exe';
        $minion = 'c:\\xampp\\htdocs\\city\\modules\\minion\\minion';

        if (!is_file($php)) {
            return array('ok' => false, 'error' => 'Не найден PHP: ' . $php);
        }
        if (!is_file($minion)) {
            return array('ok' => false, 'error' => 'Не найден minion: ' . $minion);
        }

        // Собираем аргументы
        $args = array($php, $minion, '--task=' . $task);
        foreach ($params as $k => $v) {
            $v = trim((string) $v);
            if ($v === '') {
                continue;
            }
            $args[] = '--' . $k . '=' . $v;
        }

        // Лог
        $token    = $task . '_' . date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 6);
        $log_file = $this->_log_dir . DIRECTORY_SEPARATOR . 'task_' . $token . '.log';

        // Экранируем всё
        $cmd = '';
        foreach ($args as $a) {
            $cmd .= ($cmd === '' ? '' : ' ') . escapeshellarg($a);
        }

        // Пишем .meta — пригодится для отладки
        $meta = array(
            'task'    => $task,
            'params'  => $params,
            'cmd'     => $cmd,
            'started' => date('Y-m-d H:i:s'),
        );
        file_put_contents(
            $this->_log_dir . DIRECTORY_SEPARATOR . 'task_' . $token . '.meta',
            json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        // Запуск
        $descriptors = array(
            0 => array('pipe', 'r'),
            1 => array('file', $log_file, 'a'),
            2 => array('file', $log_file, 'a'),
        );

        $pipes = array();
        $proc = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($proc)) {
            return array('ok' => false, 'error' => 'proc_open failed');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $status = proc_get_status($proc);
        $pid = (int) $status['pid'];

        // proc_close НЕ вызываем — процесс продолжится в фоне.
        // Мета-файл после старта тоже не трогаем, чтобы не мешать.

        return array(
            'ok'       => true,
            'pid'      => $pid,
            'log_file' => $log_file,
            'token'    => $token,
        );
    }

    // =========================================================
    // Служебное
    // =========================================================

    protected function _get_running_tasks()
    {
        $result = array();
        foreach (glob($this->_log_dir . DIRECTORY_SEPARATOR . 'task_*.meta') as $meta_file) {
            $age = time() - filemtime($meta_file);
            if ($age > 3600) {
                continue;
            }
            $meta = json_decode((string) file_get_contents($meta_file), true);
            if (is_array($meta)) {
                $meta['token'] = preg_replace(
                    array('/^.*task_/', '/\.meta$/'),
                    array('', ''),
                    $meta_file
                );
                $meta['mtime'] = filemtime($meta_file);
                $result[] = $meta;
            }
        }
        return $result;
    }

    protected function _get_recent_logs($limit = 20)
    {
        $files = glob($this->_log_dir . DIRECTORY_SEPARATOR . 'task_*.log');
        if (!$files) {
            return array();
        }
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $files = array_slice($files, 0, $limit);

        $out = array();
        foreach ($files as $f) {
            $token = preg_replace(array('/^.*task_/', '/\.log$/'), array('', ''), $f);
            $out[] = array(
                'token' => $token,
                'size'  => filesize($f),
                'mtime' => filemtime($f),
            );
        }
        return $out;
    }

    protected function _tail($file, $lines = 500)
    {
        $fp = fopen($file, 'r');
        if (!$fp) {
            return '';
        }
        $buffer = '';
        $chunk  = 4096;
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        while ($pos > 0 && substr_count($buffer, "\n") <= $lines) {
            $seek = min($chunk, $pos);
            $pos -= $seek;
            fseek($fp, $pos);
            $buffer = fread($fp, $seek) . $buffer;
        }
        fclose($fp);
        $arr = explode("\n", $buffer);
        $arr = array_slice($arr, -$lines);
        return implode("\n", $arr);
    }
}