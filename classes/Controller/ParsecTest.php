<?php defined('SYSPATH') or die('No direct script access.');

/**
 * REST-обёртка над Model_ParsecTest.
 *
 * Разбирает URL и параметры, вызывает метод модели, отдаёт JSON.
 * Вся логика — в Model_ParsecTest.
 *
 * URL-префикс: /parsectest
 */
class Controller_ParsecTest extends Controller
{
    /** @var bool */
    public $auto_render = false;

    /** @var Model_ParsecTest */
    protected $_model;

    public function before()
    {
        parent::before();
        $this->_model = new Model_ParsecTest();
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    // =================================================================
    // HTML-форма
    // =================================================================

    public function action_index()
    {
        $base = URL::site('parsectest');

        $html = '<!doctype html><html><head><meta charset="utf-8">'
              . '<title>Parsec Test API</title>'
              . '<style>body{font:14px/1.5 monospace;max-width:900px;margin:20px auto}'
              . 'fieldset{margin:10px 0;padding:10px}legend{font-weight:bold}'
              . 'label{display:inline-block;width:130px}input{width:400px}'
              . 'button{margin-top:5px}pre{background:#f4f4f4;padding:10px;overflow:auto;max-height:300px}'
              . '</style></head><body>'
              . '<h1>Parsec Test API</h1>';

        $html .= '<fieldset><legend>1. Добавить организацию</legend>'
              . '<form method="post" action="' . $base . '/add_org">'
              . '<div><label>name</label><input name="name" value="TEST-ORG-1"></div>'
              . '<div><label>guid</label><input name="guid" value="TEST-ORG-1"></div>'
              . '<div><label>parent_guid</label><input name="parent_guid" value=""></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>2. Добавить персону</legend>'
              . '<form method="post" action="' . $base . '/add_person">'
              . '<div><label>surname</label><input name="surname" value="Тестов"></div>'
              . '<div><label>name</label><input name="name" value="Иван"></div>'
              . '<div><label>patronymic</label><input name="patronymic" value="Тестович"></div>'
              . '<div><label>guid</label><input name="guid" value="TEST-PEP-1"></div>'
              . '<div><label>org_guid</label><input name="org_guid" value="TEST-ORG-1"></div>'
              . '<div><label>tabnum</label><input name="tabnum" value="T-001"></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>3. Выдать карту</legend>'
              . '<form method="post" action="' . $base . '/add_card">'
              . '<div><label>pep_guid</label><input name="pep_guid" value="TEST-PEP-1"></div>'
              . '<div><label>card</label><input name="card" value="7419840"></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>4. Выдать категорию доступа</legend>'
              . '<form method="post" action="' . $base . '/add_access">'
              . '<div><label>pep_guid</label><input name="pep_guid" value="TEST-PEP-1"></div>'
              . '<div><label>accessname_id</label><input name="accessname_id" value="64"></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Cleanup</legend>'
              . '<form method="post" action="' . $base . '/cleanup">'
              . '<div><label>prefix</label><input name="prefix" value="TEST-"></div>'
              . '<button>Удалить тестовые данные</button></form></fieldset>';

        $html .= '<fieldset><legend>Статус CARDINDEV</legend>'
              . '<form method="get" action="' . $base . '/status">'
              . '<div><label>limit</label><input name="limit" value="20"></div>'
              . '<button>Показать</button></form></fieldset>';

        $html .= '<p><a href="' . $base . '/status">JSON /status</a></p>';

        $html .= '</body></html>';

        $this->response->headers('Content-Type', 'text/html; charset=utf-8');
        $this->response->body($html);
    }

    // =================================================================
    // Примитивы
    // =================================================================

    public function action_add_org()
    {
        $this->_response($this->_model->add_org(array(
            'name'        => trim((string) $this->_param('name')),
            'guid'        => trim((string) $this->_param('guid')),
            'parent_guid' => trim((string) $this->_param('parent_guid')),
        )));
    }

    public function action_add_person()
    {
        $this->_response($this->_model->add_person(array(
            'surname'    => trim((string) $this->_param('surname')),
            'name'       => trim((string) $this->_param('name')),
            'patronymic' => trim((string) $this->_param('patronymic')),
            'guid'       => trim((string) $this->_param('guid')),
            'org_guid'   => trim((string) $this->_param('org_guid')),
            'tabnum'     => trim((string) $this->_param('tabnum')),
        )));
    }

    public function action_add_card()
    {
        $this->_response($this->_model->add_card(array(
            'pep_guid' => trim((string) $this->_param('pep_guid')),
            'card'     => trim((string) $this->_param('card')),
        )));
    }

    public function action_add_access()
    {
        $this->_response($this->_model->add_access(array(
            'pep_guid'      => trim((string) $this->_param('pep_guid')),
            'accessname_id' => (int) $this->_param('accessname_id'),
        )));
    }

    public function action_cleanup()
    {
        $this->_response($this->_model->cleanup(array(
            'prefix' => trim((string) $this->_param('prefix')),
        )));
    }

    public function action_status()
    {
        $this->_response($this->_model->status(array(
            'limit' => (int) $this->request->query('limit'),
        )));
    }

    // =================================================================
    // Helpers
    // =================================================================

    /**
     * Универсальное чтение параметра: сначала POST, потом query.
     */
    protected function _param($key, $default = '')
    {
        $v = $this->request->post($key);
        if ($v === null) {
            $v = $this->request->query($key);
        }
        return $v !== null ? $v : $default;
    }

    /**
     * Отдаёт массив как JSON. Нормализует строки в UTF-8,
     * выставляет HTTP-статус из поля 'status', если ok=false.
     */
    protected function _response(array $data)
    {
        $data = $this->_to_utf8($data);

        $status = 200;
        if (empty($data['ok']) && isset($data['status'])) {
            $status = (int) $data['status'];
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(array(
                'ok'         => false,
                'error'      => 'json_encode failed: ' . json_last_error_msg(),
                'json_errno' => json_last_error(),
            ));
            $status = 500;
        }

        $this->response->status($status);
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
        $this->response->body($json);
    }

    /**
     * Рекурсивно приводит строки к UTF-8 для json_encode.
     */
    protected function _to_utf8($v)
    {
        if (is_array($v)) {
            $out = array();
            foreach ($v as $k => $x) {
                $out[$k] = $this->_to_utf8($x);
            }
            return $out;
        }
        if (is_object($v)) {
            return $this->_to_utf8((array) $v);
        }
        if (is_string($v) && $v !== '') {
            if (function_exists('mb_check_encoding') && mb_check_encoding($v, 'UTF-8')) {
                return $v;
            }
            $conv = @iconv('windows-1251', 'UTF-8//IGNORE', $v);
            return ($conv === false) ? $v : $conv;
        }
        return $v;
    }
}