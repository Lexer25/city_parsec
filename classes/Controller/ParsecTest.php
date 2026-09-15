<?php defined('SYSPATH') or die('No direct script access.');

/**
 * REST-обёртка над Model_ParsecTest.
 *
 * Разбирает URL и параметры, вызывает метод модели, отдаёт JSON.
 * Вся логика — в Model_ParsecTest.
 */
class Controller_ParsecTest extends Controller
{
    public $auto_render = false;

    /** @var Model_ParsecTest */
    protected $_model;

    public function before()
    {
        parent::before();
        $this->_model = new Model_ParsecTest();
    }

    // ---------- HTML-форма (без изменений) ----------
    public function action_index() { /* см. предыдущий файл — оставить как было */ }

    // ---------- Примитивы ----------

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

    // ---------- Helpers ----------

    protected function _param($key, $default = '')
    {
        $v = $this->request->post($key);
        if ($v === null) {
            $v = $this->request->query($key);
        }
        return $v !== null ? $v : $default;
    }

    protected function _response(array $data)
    {
        $data = $this->_to_utf8($data);

        $status = 200;
        if (empty($data['ok']) && isset($data['status'])) {
            $status = (int) $data['status'];
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"ok":false,"error":"json_encode failed"}';
            $status = 500;
        }

        $this->response->status($status);
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
        $this->response->body($json);
    }

    protected function _to_utf8($v)
    {
        if (is_array($v)) {
            $out = array();
            foreach ($v as $k => $x) $out[$k] = $this->_to_utf8($x);
            return $out;
        }
        if (is_object($v)) return $this->_to_utf8((array) $v);
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