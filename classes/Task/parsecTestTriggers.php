<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Прогонщик тестовых сценариев Parsec.
 *
 * Вызывает методы Model_ParsecTest напрямую — без HMVC, без JSON,
 * без подмены $_GET/$_POST.
 *
 * Запуск:
 *   minion --task=parsecTestTriggers
 *   minion --task=parsecTestTriggers --cleanup=0
 *   minion --task=parsecTestTriggers --scenario=/path/to/scenario.json
 *   minion --task=parsecTestTriggers --list
 */
class Task_parsecTestTriggers extends Minion_Task
{
    protected $_options = array(
        'scenario' => 'basic',
        'cleanup'  => 1,
        'verbose'  => 1,
        'list'     => 0,
    );

    /** @var Model_ParsecTest */
    protected $_model;

    protected $_suffix   = '';
    protected $_step_num = 0;
    protected $_ctx      = array();

    protected $_stats = array(
        'steps'  => 0,
        'passed' => 0,
        'failed' => 0,
        'errors' => 0,
    );

    // =================================================================
    // Точка входа
    // =================================================================

    protected function _execute(array $params)
    {
        if ((int) Arr::get($params, 'list', 0) === 1) {
            $this->_list_scenarios();
            return;
        }

        $scenario_name = (string) Arr::get($params, 'scenario', 'basic');
        $do_cleanup    = (int)    Arr::get($params, 'cleanup', 1) === 1;
        $verbose       = (int)    Arr::get($params, 'verbose', 1) === 1;

        $this->_model  = new Model_ParsecTest();
        $this->_suffix = date('YmdHis') . '_' . mt_rand(1000, 9999);

        $scenario = $this->_load_scenario($scenario_name);
        if ($scenario === null) {
            return;
        }

        $this->_print_header($scenario, $do_cleanup);

        foreach ($scenario['steps'] as $step) {
            $this->_step_num++;
            $this->_run_step($step, $verbose);
        }

        $this->_print_summary();

        if ($do_cleanup) {
            Minion_CLI::write('');
            Minion_CLI::write('=== ОЧИСТКА (Model_ParsecTest::cleanup) ===');
            $res = $this->_model->cleanup(array('prefix' => 'TEST-'));
            if (!empty($res['ok'])) {
                foreach ((array) Arr::get($res, 'deleted', array()) as $k => $v) {
                    Minion_CLI::write('  ' . $k . ': ' . (is_scalar($v) ? $v : json_encode($v)));
                }
            } else {
                Minion_CLI::write('  ошибка: ' . $this->_utf8_to_cp1251(Arr::get($res, 'error', '?')));
            }
        } else {
            Minion_CLI::write('');
            Minion_CLI::write('Очистка пропущена (--cleanup=0). Суффикс: ' . $this->_suffix);
        }
    }

    // =================================================================
    // Один шаг
    // =================================================================

    protected function _run_step($step, $verbose)
    {
        $this->_stats['steps']++;

        $step_id    = isset($step['id'])          ? $step['id']          : ('step' . $this->_step_num);
        $desc       = isset($step['description']) ? $step['description'] : '';
        $method     = isset($step['action'])      ? $step['action']      : '';
        $params     = isset($step['params'])      ? $step['params']      : array();
        $expect_ops = isset($step['expect_ops'])  ? $step['expect_ops']  : array();

        Minion_CLI::write(sprintf('[%d] %s — %s', $this->_step_num, $step_id, $desc));
        Minion_CLI::write('    Model_ParsecTest::' . $method . '()');

        // Подстановка {suffix}, {cardnum}, {ctx.step.field}
        $params = $this->_substitute($params);

        if ($verbose) {
            Minion_CLI::write('    PARAMS: ' . json_encode($params, JSON_UNESCAPED_UNICODE));
        }

        if (!method_exists($this->_model, $method)) {
            $this->_stats['errors']++;
            Minion_CLI::write('    ? ОШИБКА: Метод ' . $method . ' не найден в модели');
            Minion_CLI::write('');
            return;
        }

        $result = $this->_model->$method($params);
        $this->_ctx[$step_id] = $result;

        if (empty($result['ok'])) {
            $this->_stats['errors']++;
            Minion_CLI::write('    ? ОШИБКА: '
                . $this->_utf8_to_cp1251(Arr::get($result, 'error', '?')));
            Minion_CLI::write('');
            return;
        }

        // Печать ключевых полей
        foreach (array('id_org', 'id_pep', 'card', 'card_hex', 'accessname_id') as $k) {
            if (isset($result[$k]) && $result[$k] !== '') {
                Minion_CLI::write('    ' . $k . ' = ' . $result[$k]);
            }
        }

        // Разбор новых записей CARDINDEV
        $ops = array();
        $new = (array) Arr::get($result, 'cardindev_new', array());
        Minion_CLI::write('    Новых записей CARDINDEV: ' . count($new));
        foreach ($new as $ci) {
            Minion_CLI::write(sprintf(
                '      #%s op=%s id_card=%s id_pep=%s',
                Arr::get($ci, 'id_cardindev'),
                Arr::get($ci, 'operation'),
                Arr::get($ci, 'id_card'),
                Arr::get($ci, 'id_pep')
            ));
            $ops[] = (int) Arr::get($ci, 'operation');
        }

        // Проверка ожиданий
        if (!empty($expect_ops)) {
            $e = $expect_ops;
            $a = $ops;
            sort($e);
            sort($a);

            if ($e === $a) {
                $this->_stats['passed']++;
                Minion_CLI::write('    ? OK (ops: ' . implode(',', $a) . ')');
            } else {
                $this->_stats['failed']++;
                Minion_CLI::write('    ? ПРОВАЛ. Ожидалось: [' . implode(',', $e)
                    . '], получено: [' . implode(',', $a) . ']');
            }
        } else {
            $this->_stats['passed']++;
            Minion_CLI::write('    ?  (без проверки)');
        }

        Minion_CLI::write('');
    }

    // =================================================================
    // Подстановка
    // =================================================================

    protected function _substitute($data)
    {
        if (is_array($data)) {
            $out = array();
            foreach ($data as $k => $v) {
                $out[$k] = $this->_substitute($v);
            }
            return $out;
        }
        if (is_string($data)) {
            return $this->_substitute_str($data);
        }
        return $data;
    }

    protected function _substitute_str($str)
    {
        $str = str_replace('{suffix}',  $this->_suffix, $str);
        $str = str_replace('{ts}',      time(), $str);
        $str = str_replace('{uniq}',    uniqid(), $str);
        $str = str_replace('{cardnum}', (string) $this->_random_card(), $str);

        if (preg_match_all('/\{ctx\.([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)\}/', $str, $m)) {
            foreach ($m[0] as $i => $ph) {
                $sid   = $m[1][$i];
                $field = $m[2][$i];
                $val   = array_key_exists($sid, $this->_ctx)
                      && array_key_exists($field, (array) $this->_ctx[$sid])
                         ? $this->_ctx[$sid][$field]
                         : '';
                $str = str_replace($ph, $val, $str);
            }
        }
        return $str;
    }

    /**
     * Случайный номер карты в диапазоне 1 .. 0xFFFFFF (1..16777215).
     */
    protected function _random_card()
    {
        return mt_rand(1, 16777215);
    }

    // =================================================================
    // Кодировки
    // =================================================================

    /**
     * UTF-8 -> CP1251 (для печати в консоль Windows).
     */
    protected function _utf8_to_cp1251($s)
    {
        $s = (string) $s;
        if ($s === '') {
            return $s;
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        $out = @iconv('UTF-8', 'windows-1251//TRANSLIT//IGNORE', $s);
        return ($out === false) ? $s : $out;
    }

    // =================================================================
    // Сценарии
    // =================================================================

    protected function _list_scenarios()
    {
        Minion_CLI::write('Встроенный сценарий: basic');
        Minion_CLI::write('');
        Minion_CLI::write('Внешние сценарии кладутся в:');
        Minion_CLI::write('  ' . __DIR__ . DIRECTORY_SEPARATOR . 'scenarios/*.json');
    }

    protected function _load_scenario($name)
    {
        if ($name === 'basic' || $name === '') {
            return $this->_default_scenario();
        }

        $dir  = __DIR__ . DIRECTORY_SEPARATOR . 'scenarios';
        $path = null;

        if (file_exists($name)) {
            $path = $name;
        } else {
            $candidate = $dir . DIRECTORY_SEPARATOR . $name . '.json';
            if (file_exists($candidate)) {
                $path = $candidate;
            }
        }

        if ($path === null) {
            Minion_CLI::write('Сценарий не найден: ' . $name);
            return null;
        }

        $json = @file_get_contents($path);
        if ($json === false) {
            Minion_CLI::write('Не удалось прочитать: ' . $path);
            return null;
        }

        $scenario = json_decode($json, true);
        if (!is_array($scenario) || empty($scenario['steps'])) {
            Minion_CLI::write('Некорректный JSON: ' . $path);
            return null;
        }

        Minion_CLI::write('Загружен сценарий: ' . $path);
        return $scenario;
    }

    /**
     * Встроенный сценарий.
     */
    protected function _default_scenario()
    {
        return array(
            'name'        => 'Базовый тест триггеров',
            'description' => 'Организация > персона > категория доступа > карта',
            'steps'       => array(
                array(
                    'id'          => 'org',
                    'description' => 'Добавить организацию',
                    'action'      => 'add_org',
                    'params'      => array(
                        'name'        => 'TEST-ORG-{suffix}',
                        'guid'        => 'TEST-ORG-{suffix}',
                        'parent_guid' => '',
                    ),
                    'expect_ops'  => array(5),
                ),
                array(
                    'id'          => 'people',
                    'description' => 'Добавить персону',
                    'action'      => 'add_person',
                    'params'      => array(
                        'surname'    => 'Тестов',
                        'name'       => 'Иван',
                        'patronymic' => 'Тестович',
                        'guid'       => 'TEST-PEP-{suffix}',
                        'org_guid'   => '{ctx.org.guid}',
                        'tabnum'     => 'T-{suffix}',
                    ),
                    'expect_ops'  => array(3),
                ),
                array(
                    'id'          => 'access',
                    'description' => 'Выдать категорию доступа',
                    'action'      => 'add_access',
                    'params'      => array(
                        'pep_guid'      => '{ctx.people.guid}',
                        'accessname_id' => 64,
                    ),
                    'expect_ops'  => array(7),
                ),
                array(
                    'id'          => 'card',
                    'description' => 'Выдать карту',
                    'action'      => 'add_card',
                    'params'      => array(
                        'pep_guid' => '{ctx.people.guid}',
                        'card'     => '{cardnum}',
                    ),
                    'expect_ops'  => array(9),
                ),
            ),
        );
    }

    // =================================================================
    // Печать
    // =================================================================

    protected function _print_header($scenario, $do_cleanup)
    {
        Minion_CLI::write('====================================================');
        Minion_CLI::write(' Тест триггеров Parsec (direct model)');
        Minion_CLI::write('====================================================');
        Minion_CLI::write('Сценарий:   ' . Arr::get($scenario, 'name', '(без имени)'));
        Minion_CLI::write('Описание:   ' . Arr::get($scenario, 'description', '—'));
        Minion_CLI::write('Шагов:      ' . count($scenario['steps']));
        Minion_CLI::write('Суффикс:    ' . $this->_suffix);
        Minion_CLI::write('Cleanup:    ' . ($do_cleanup ? 'ДА' : 'НЕТ'));
        Minion_CLI::write('');
    }

    protected function _print_summary()
    {
        Minion_CLI::write('');
        Minion_CLI::write('====================================================');
        Minion_CLI::write(' ИТОГИ');
        Minion_CLI::write('====================================================');
        Minion_CLI::write('Всего шагов:  ' . $this->_stats['steps']);
        Minion_CLI::write('Пройдено:     ' . $this->_stats['passed']);
        Minion_CLI::write('Провалено:    ' . $this->_stats['failed']);
        Minion_CLI::write('Ошибок:       ' . $this->_stats['errors']);
        Minion_CLI::write('');
        Minion_CLI::write(
            ($this->_stats['failed'] === 0 && $this->_stats['errors'] === 0)
                ? 'РЕЗУЛЬТАТ: PASS'
                : 'РЕЗУЛЬТАТ: FAIL'
        );
    }
}