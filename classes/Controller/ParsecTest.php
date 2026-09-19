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
              . '<style>'
              . 'body{font:14px/1.5 monospace;max-width:1100px;margin:20px auto;padding:0 20px;color:#24292f}'
              . 'h1{border-bottom:2px solid #ccc;padding-bottom:8px}'
              . 'h2{margin-top:36px;color:#0969da;border-bottom:1px solid #d0d7de;padding-bottom:6px}'
              . 'h3{margin-top:24px;color:#57606a;font-size:15px}'
              . 'fieldset{margin:10px 0;padding:12px 14px;border:1px solid #d0d7de;border-radius:6px;background:#fafbfc}'
              . 'legend{font-weight:bold;padding:0 6px;color:#24292f}'
              . 'label{display:inline-block;width:150px;vertical-align:top}'
              . 'input{width:420px;padding:3px 6px;border:1px solid #d0d7de;border-radius:4px}'
              . 'button{margin-top:8px;padding:4px 14px;cursor:pointer;border:1px solid #0969da;background:#0969da;color:#fff;border-radius:4px}'
              . 'button:hover{background:#0757b8}'
              . 'pre{background:#f4f4f4;padding:10px;overflow:auto;max-height:400px;border-radius:4px}'
              . '.hint{color:#57606a;font-size:12px;margin:4px 0 8px 0}'
              . '.op{display:inline-block;background:#ddf4ff;color:#0969da;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:bold;margin-left:6px}'
              . '</style></head><body>'
              . '<h1>Parsec Test API</h1>'
              . '<p>HTML-формы для ручной проверки. Все эндпоинты — на базовом URL: <code>' . $base . '</code></p>';

        // ============================================================
        // ОРГАНИЗАЦИИ
        // ============================================================

        $html .= '<h2>Organization</h2>';
        $html .= '<p class="hint">Сущность ORGANIZATION. Создание, изменение, удаление.</p>';

        $html .= '<fieldset><legend>Создать организацию <span class="op">op 5</span></legend>'
              . '<form method="post" action="' . $base . '/add_org">'
              . '<div><label>name</label><input name="name" value="TEST-ORG-1"></div>'
              . '<div><label>guid</label><input name="guid" value="TEST-ORG-1"></div>'
              . '<div><label>parent_guid</label><input name="parent_guid" value=""></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Изменить организацию <span class="op">op 55</span></legend>'
              . '<div class="hint">Пустые поля не передаются — соответствующее поле в БД не меняется.</div>'
              . '<form method="post" action="' . $base . '/update_org">'
              . '<div><label>guid</label><input name="guid" value="TEST-ORG-1"></div>'
              . '<div><label>name</label><input name="name" value="TEST-ORG-RENAMED"></div>'
              . '<div><label>parent_guid</label><input name="parent_guid" value=""></div>'
              . '<div><label>divcode</label><input name="divcode" value=""></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Удалить организацию <span class="op">op 6</span></legend>'
              . '<div class="hint">Перед удалением убедитесь, что в организации нет персон.</div>'
              . '<form method="post" action="' . $base . '/del_org">'
              . '<div><label>guid</label><input name="guid" value="TEST-ORG-1"></div>'
              . '<button>Отправить</button></form></fieldset>';

        // ============================================================
        // ПЕРСОНЫ
        // ============================================================

        $html .= '<h2>Person</h2>';
        $html .= '<p class="hint">Сущность PEOPLE. Создание, изменение, удаление.</p>';

        $html .= '<fieldset><legend>Создать персону <span class="op">op 3</span></legend>'
              . '<form method="post" action="' . $base . '/add_person">'
              . '<div><label>surname</label><input name="surname" value="Тестов"></div>'
              . '<div><label>name</label><input name="name" value="Иван"></div>'
              . '<div><label>patronymic</label><input name="patronymic" value="Тестович"></div>'
              . '<div><label>guid</label><input name="guid" value="TEST-PEP-1"></div>'
              . '<div><label>org_guid</label><input name="org_guid" value="TEST-ORG-1"></div>'
              . '<div><label>tabnum</label><input name="tabnum" value="T-001"></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Изменить персону <span class="op">op 35</span></legend>'
              . '<div class="hint">Пустые поля не передаются — соответствующее поле в БД не меняется.</div>'
              . '<form method="post" action="' . $base . '/update_person">'
              . '<div><label>guid</label><input name="guid" value="TEST-PEP-1"></div>'
              . '<div><label>surname</label><input name="surname" value="Изменённый"></div>'
              . '<div><label>name</label><input name="name" value=""></div>'
              . '<div><label>patronymic</label><input name="patronymic" value=""></div>'
              . '<div><label>tabnum</label><input name="tabnum" value=""></div>'
              . '<div><label>org_guid</label><input name="org_guid" value=""></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Удалить персону <span class="op">op 4</span></legend>'
              . '<form method="post" action="' . $base . '/del_person">'
              . '<div><label>guid</label><input name="guid" value="TEST-PEP-1"></div>'
              . '<button>Отправить</button></form></fieldset>';

        // ============================================================
        // КАРТЫ
        // ============================================================

        $html .= '<h2>Card</h2>';
        $html .= '<p class="hint">Сущность CARD. Выдача и удаление карты у персоны.</p>';

        $html .= '<fieldset><legend>Выдать карту <span class="op">op 9</span></legend>'
              . '<form method="post" action="' . $base . '/add_card">'
              . '<div><label>pep_guid</label><input name="pep_guid" value="TEST-PEP-1"></div>'
              . '<div><label>card</label><input name="card" value="7419840"></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Удалить карту <span class="op">op 10</span></legend>'
              . '<form method="post" action="' . $base . '/del_card">'
              . '<div><label>pep_guid</label><input name="pep_guid" value="TEST-PEP-1"></div>'
              . '<div><label>card</label><input name="card" value="7419840"></div>'
              . '<button>Отправить</button></form></fieldset>';

        // ============================================================
        // КАТЕГОРИИ ДОСТУПА
        // ============================================================

        $html .= '<h2>Access</h2>';
        $html .= '<p class="hint">Сущность SS_ACCESSUSER. Выдача и удаление категории доступа у персоны.</p>';

        $html .= '<fieldset><legend>Выдать категорию доступа <span class="op">op 7</span></legend>'
              . '<div class="hint">Требует наличия активной RFID-карты у персоны.</div>'
              . '<form method="post" action="' . $base . '/add_access">'
              . '<div><label>pep_guid</label><input name="pep_guid" value="TEST-PEP-1"></div>'
              . '<div><label>accessname_id</label><input name="accessname_id" value="64"></div>'
              . '<button>Отправить</button></form></fieldset>';

        $html .= '<fieldset><legend>Удалить категорию доступа <span class="op">op 8</span></legend>'
              . '<div class="hint">Требует наличия карты у персоны.</div>'
              . '<form method="post" action="' . $base . '/del_access">'
              . '<div><label>pep_guid</label><input name="pep_guid" value="TEST-PEP-1"></div>'
              . '<div><label>accessname_id</label><input name="accessname_id" value="64"></div>'
              . '<button>Отправить</button></form></fieldset>';

        // ============================================================
        // СЛУЖЕБНОЕ
        // ============================================================

        $html .= '<h2>Service</h2>';
        $html .= '<p class="hint">Служебные операции: очистка тестовых данных и просмотр очереди CARDINDEV.</p>';

        $html .= '<fieldset><legend>Cleanup — удалить тестовые данные</legend>'
              . '<div class="hint">Удаляет всё, у чего GUID начинается с указанного префикса. Также чистит CARDINDEV.</div>'
              . '<form method="post" action="' . $base . '/cleanup">'
              . '<div><label>prefix</label><input name="prefix" value="TEST-"></div>'
              . '<button>Удалить тестовые данные</button></form></fieldset>';

        $html .= '<fieldset><legend>Status — очередь CARDINDEV</legend>'
              . '<form method="get" action="' . $base . '/status">'
              . '<div><label>limit</label><input name="limit" value="20"></div>'
              . '<button>Показать</button></form></fieldset>';

        $html .= '<p style="margin-top:30px"><a href="' . $base . '/status">JSON /status</a></p>';

        $html .= '</body></html>';

        $this->response->headers('Content-Type', 'text/html; charset=utf-8');
        $this->response->body($html);
    }

    // =================================================================
    // Organization
    // =================================================================

    public function action_add_org()
    {
        $this->_response($this->_model->add_org(array(
            'name'        => trim((string) $this->_param('name')),
            'guid'        => trim((string) $this->_param('guid')),
            'parent_guid' => trim((string) $this->_param('parent_guid')),
        )));
    }

    public function action_update_org()
    {
        $p = array(
            'guid' => trim((string) $this->_param('guid')),
        );

        foreach (array('name', 'parent_guid', 'divcode') as $k) {
            $v = $this->_param($k, null);
            if ($v !== null && trim((string) $v) !== '') {
                $p[$k] = trim((string) $v);
            }
        }

        $this->_response($this->_model->update_org($p));
    }

    public function action_del_org()
    {
        $this->_response($this->_model->del_org(array(
            'guid' => trim((string) $this->_param('guid')),
        )));
    }

    // =================================================================
    // Person
    // =================================================================

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

    public function action_update_person()
    {
        $p = array(
            'guid' => trim((string) $this->_param('guid')),
        );

        foreach (array('surname', 'name', 'patronymic', 'tabnum', 'org_guid') as $k) {
            $v = $this->_param($k, null);
            if ($v !== null && trim((string) $v) !== '') {
                $p[$k] = trim((string) $v);
            }
        }

        $this->_response($this->_model->update_person($p));
    }

    public function action_del_person()
    {
        $this->_response($this->_model->del_person(array(
            'guid' => trim((string) $this->_param('guid')),
        )));
    }

    // =================================================================
    // Card
    // =================================================================

    public function action_add_card()
    {
        $this->_response($this->_model->add_card(array(
            'pep_guid' => trim((string) $this->_param('pep_guid')),
            'card'     => trim((string) $this->_param('card')),
        )));
    }

    public function action_del_card()
    {
        $this->_response($this->_model->del_card(array(
            'pep_guid' => trim((string) $this->_param('pep_guid')),
            'card'     => trim((string) $this->_param('card')),
        )));
    }

    // =================================================================
    // Access
    // =================================================================

    public function action_add_access()
    {
        $this->_response($this->_model->add_access(array(
            'pep_guid'      => trim((string) $this->_param('pep_guid')),
            'accessname_id' => (int) $this->_param('accessname_id'),
        )));
    }

    public function action_del_access()
    {
        $this->_response($this->_model->del_access(array(
            'pep_guid'      => trim((string) $this->_param('pep_guid')),
            'accessname_id' => (int) $this->_param('accessname_id'),
        )));
    }

    // =================================================================
    // Service
    // =================================================================

    public function action_cleanup()
    {
        $this->_response($this->_model->cleanup(array(
            'prefix' => trim((string) $this->_param('prefix')),
        )));
    }

    public function action_status()
    {
        $this->_response($this->_model->status(array(
            'limit' => (int) $this->_param('limit'),
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