<?php defined('SYSPATH') or die('No direct script access.');

/**
 * REST-подобный тест триггеров интеграции Parsec.
 *
 * URL-префикс: /parsectest
 *
 *   GET  /parsectest                 — HTML-форма для ручного теста
 *   GET  /parsectest/status          — JSON: последние N записей CARDINDEV
 *   POST /parsectest/add_org         — name, guid, parent_guid
 *   POST /parsectest/add_person      — surname, name, patronymic, guid, org_guid, tabnum
 *   POST /parsectest/add_card        — pep_guid, card
 *   POST /parsectest/add_access      — pep_guid, accessname_id
 *   POST /parsectest/cleanup         — удалить всё с guid LIKE 'TEST-%'
 *
 * Параметры читаются через _param(): сначала POST, потом query.
 * Это нужно для вызовов через HMVC из Task_parsecTestTriggers —
 * там данные передаются через query string.
 */
class Controller_ParsecTest extends Controller
{
    /** @var bool */
    public $auto_render = false;

    /** @var Database */
    protected $_db;

    public function before()
    {
        parent::before();
        $this->_db = Database::instance('fb');
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    // =================================================================
    // Чтение параметров
    // =================================================================

    /**
     * Универсальное чтение параметра: сначала POST, потом query.
     * Возвращает $default, если параметра нет ни там, ни там.
     */
    protected function _param($key, $default = '')
    {
        $v = $this->request->post($key);
        if ($v === null) {
            $v = $this->request->query($key);
        }
        return $v !== null ? $v : $default;
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
    // 1. Добавить организацию
    // =================================================================

    public function action_add_org()
    {
		$name        = trim((string) $this->_param('name'));
		$guid        = trim((string) $this->_param('guid'));
		$parent_guid = trim((string) $this->_param('parent_guid'));

        if ($name === '' || $guid === '') {
            return $this->_fail('106 Параметры name и guid обязательны', 400);
        }

        $parent_id = 1;
        if ($parent_guid !== '') {
            $parent_id = $this->_findOrgIdByGuid($parent_guid);
            if ($parent_id === null) {
                return $this->_fail('113 Родитель не найден: ' . $parent_guid, 404);
            }
        }

        $before = $this->_maxCardindevId();

        $sql = 'INSERT INTO ORGANIZATION (ID_DB, NAME, GUID, ID_PARENT) VALUES ('
             . '1, ' . $this->_str($name) . ', ' . trim($this->_str($guid)) . ', ' . (int) $parent_id
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_fail('126 INSERT ORGANIZATION: ' . $e->getMessage(), 500);
        }

        $id_org = $this->_findOrgIdByGuid($guid);

        return $this->_ok(array(
            'operation'      => 'add_org',
            'id_org'         => $id_org,
            'guid'           => $guid,
            'name'           => $name,
            'parent_guid'    => $parent_guid,
            'parent_id'      => $parent_id,
            'cardindev_new'  => $this->_newCardindev($before),
        ));
    }

    // =================================================================
    // 2. Добавить персону (по guid организации)
    // =================================================================

    public function action_add_person()
    {
        $surname    = trim((string) $this->_param('surname'));
        $name       = trim((string) $this->_param('name'));
        $patronymic = trim((string) $this->_param('patronymic'));
        $guid       = trim((string) $this->_param('guid'));
        $org_guid   = trim((string) $this->_param('org_guid'));
        $tabnum     = trim((string) $this->_param('tabnum'));

        if ($surname === '' || $guid === '' || $org_guid === '') {
            return $this->_fail('156 Параметры surname, guid, org_guid обязательны', 400);
        }

        $id_org = $this->_findOrgIdByGuid($org_guid);
        if ($id_org === null) {
            return $this->_fail('161 Организация не найдена: ' . $org_guid, 404);
        }

        $_id_pep = $this->_findPersonIdByGuid($guid);
        if ($_id_pep !== null) {
            return $this->_fail('166 GUID занят id_pep: ' . $_id_pep, 409);
        }

        $before = $this->_maxCardindevId();

        $sql = 'INSERT INTO PEOPLE (ID_DB, SURNAME, NAME, PATRONYMIC, GUID, ID_ORG, TABNUM) VALUES ('
             . '1, '
             . $this->_str($surname)          . ', '
             . $this->_str($name)             . ', '
             . $this->_str($patronymic)       . ', '
             . trim($this->_str($guid))       . ', '
             . (int) $id_org                  . ', '
             . $this->_str($tabnum)
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_fail('179 INSERT PEOPLE: ' . $e->getMessage(), 500);
        }

        $id_pep = $this->_findPersonIdByGuid($guid);

        return $this->_ok(array(
            'operation'      => 'add_person',
            'id_pep'         => $id_pep,
            'guid'           => $guid,
            'surname'        => $surname,
            'name'           => $name,
            'patronymic'     => $patronymic,
            'org_guid'       => $org_guid,
            'id_org'         => $id_org,
            'cardindev_new'  => $this->_newCardindev($before),
        ));
    }

    // =================================================================
    // 3. Выдать карту персоне
    // =================================================================

    public function action_add_card()
    {
        $pep_guid = trim((string) $this->_param('pep_guid'));
        $card     = trim((string) $this->_param('card'));

        if ($pep_guid === '' || $card === '') {
            return $this->_fail('207 Параметры pep_guid и card обязательны', 400);
        }

        if (!ctype_digit($card)) {
            return $this->_fail('215 card должен быть числом (десятичный номер карты)', 400);
        }

        $card_dec = (int) $card;
        $max      = 4294967296;   // 2^32

        if ($card_dec < 1 || $card_dec > $max) {
            return $this->_fail('221 card вне диапазона 1..2^32', 400);
        }

        $id_pep = $this->_findPersonIdByGuid($pep_guid);
        if ($id_pep === null) {
            return $this->_fail('227 Персона не найдена: ' . $pep_guid, 404);
        }

 $_id_pep = $this->_findCardByCard($card_dec);
if ($_id_pep !== null) {
    return $this->_fail(
        '233 Карта ' . $card_dec
        . ' (hex ' . strtoupper(str_pad(dechex($card_dec), 8, '0', STR_PAD_LEFT)) . ')'
        . ' уже выдана персоне id_pep = ' . $_id_pep,
        409
    );
}

        $before = $this->_maxCardindevId();

        // ACTIVE — зарезервированное слово Firebird, обязательно в кавычках
        $sql = 'INSERT INTO CARD (ID_DB, ID_PEP, ID_CARD, TIMESTART, ID_CARDTYPE, "ACTIVE") VALUES ('
             . '1, ' . (int) $id_pep . ', ' . $card_dec . ', \'now\', 1, 1'
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_fail('247 INSERT CARD: ' . $e->getMessage(), 500);
        }

        return $this->_ok(array(
            'operation'      => 'add_card',
            'id_pep'         => $id_pep,
            'pep_guid'       => $pep_guid,
            'card'           => $card_dec,
            'card_hex'       => strtoupper(str_pad(dechex($card_dec), 8, '0', STR_PAD_LEFT)),
            'cardindev_new'  => $this->_newCardindev($before),
        ));
    }

    // =================================================================
    // 4. Выдать категорию доступа персоне
    // =================================================================

    public function action_add_access()
    {
        $pep_guid      = trim((string) $this->_param('pep_guid'));
        $accessname_id = (int) $this->_param('accessname_id');

        if ($pep_guid === '' || $accessname_id <= 0) {
            return $this->_fail('271 Параметры pep_guid и accessname_id обязательны', 400);
        }

        $id_pep = $this->_findPersonIdByGuid($pep_guid);
        if ($id_pep === null) {
            return $this->_fail('276 Персона не найдена: ' . $pep_guid, 404);
        }

        $before = $this->_maxCardindevId();

        $sql = 'INSERT INTO SS_ACCESSUSER (ID_DB, ID_PEP, ID_ACCESSNAME) VALUES ('
             . '1, ' . (int) $id_pep . ', ' . (int) $accessname_id
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_fail('288 INSERT SS_ACCESSUSER: ' . $e->getMessage(), 500);
        }

        return $this->_ok(array(
            'operation'       => 'add_access',
            'id_pep'          => $id_pep,
            'pep_guid'        => $pep_guid,
            'accessname_id'   => $accessname_id,
            'cardindev_new'   => $this->_newCardindev($before),
        ));
    }

    // =================================================================
    // Статус
    // =================================================================

    public function action_status()
    {
        $limit = (int) $this->request->query('limit');
        if ($limit <= 0 || $limit > 500) {
            $limit = 20;
        }

        $max = $this->_maxCardindevId();

        $sql = 'SELECT FIRST ' . $limit
             . ' ID_CARDINDEV, OPERATION, ID_CARD, ID_PEP, ATTEMPTS, TIME_STAMP'
             . ' FROM CARDINDEV ORDER BY ID_CARDINDEV DESC';

        try {
            $rows = DB::query(Database::SELECT, $sql)
                ->execute($this->_db)
                ->as_array();
        } catch (Exception $e) {
            return $this->_fail('322 SELECT CARDINDEV: ' . $e->getMessage(), 500);
        }

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id_cardindev' => (int) Arr::get($r, 'ID_CARDINDEV'),
                'operation'    => (int) Arr::get($r, 'OPERATION'),
                'id_card'      => Arr::get($r, 'ID_CARD'),
                'id_pep'       => Arr::get($r, 'ID_PEP'),
                'attempts'     => (int) Arr::get($r, 'ATTEMPTS'),
                'time_stamp'   => Arr::get($r, 'TIME_STAMP'),
            );
        }

        return $this->_ok(array(
            'operation'    => 'status',
            'max_id'       => $max,
            'rows'         => $out,
        ));
    }

    // =================================================================
    // Cleanup
    // =================================================================

    public function action_cleanup()
    {
        $prefix = trim((string) $this->_param('prefix'));
        if ($prefix === '') {
            $prefix = 'TEST-';
        }

        $escaped = str_replace("'", "''", $prefix);
        $like    = "'" . $escaped . "%'";
        $base    = $this->_maxCardindevId();

        $deleted = array();

        // 1. Найти тестовых персон
        $pep_ids = array();
        try {
            $rows = DB::query(Database::SELECT,
                'SELECT ID_PEP FROM PEOPLE WHERE GUID LIKE ' . $like
            )->execute($this->_db)->as_array();
            foreach ($rows as $r) {
                $pep_ids[] = (int) Arr::get($r, 'ID_PEP');
            }
        } catch (Exception $e) {
            return $this->_fail('371 SELECT PEOPLE: ' . $e->getMessage(), 500);
        }

        // 2. Удалить карты и категории доступа тестовых персон
        if (!empty($pep_ids)) {
            try {
                $sql = 'DELETE FROM CARD WHERE ID_PEP IN (' . implode(',', $pep_ids) . ')';
                DB::query(Database::DELETE, $sql)->execute($this->_db);
                $deleted['CARD'] = count($pep_ids);
            } catch (Exception $e) {
                $deleted['CARD_error'] = $e->getMessage();
            }

            try {
                $sql = 'DELETE FROM SS_ACCESSUSER WHERE ID_PEP IN (' . implode(',', $pep_ids) . ')';
                DB::query(Database::DELETE, $sql)->execute($this->_db);
                $deleted['SS_ACCESSUSER'] = count($pep_ids);
            } catch (Exception $e) {
                $deleted['SS_ACCESSUSER_error'] = $e->getMessage();
            }
        }

        // 3. Удалить персон
        try {
            $sql = 'DELETE FROM PEOPLE WHERE GUID LIKE ' . $like;
            DB::query(Database::DELETE, $sql)->execute($this->_db);
            $deleted['PEOPLE'] = 'ok';
        } catch (Exception $e) {
            $deleted['PEOPLE_error'] = $e->getMessage();
        }

        // 4. Удалить организации
        try {
            $sql = 'DELETE FROM ORGANIZATION WHERE GUID LIKE ' . $like;
            DB::query(Database::DELETE, $sql)->execute($this->_db);
            $deleted['ORGANIZATION'] = 'ok';
        } catch (Exception $e) {
            $deleted['ORGANIZATION_error'] = $e->getMessage();
        }

        // 5. Снести всё, что наросло в CARDINDEV за время чистки
        try {
            DB::query(Database::DELETE,
                'DELETE FROM CARDINDEV WHERE ID_CARDINDEV > ' . (int) $base
            )->execute($this->_db);
            $deleted['CARDINDEV_cleaned'] = true;
        } catch (Exception $e) {
            $deleted['CARDINDEV_error'] = $e->getMessage();
        }

        return $this->_ok(array(
            'operation' => 'cleanup',
            'prefix'    => $prefix,
            'deleted'   => $deleted,
        ));
    }

    // =================================================================
    // Helpers
    // =================================================================

    protected function _ok(array $data)
    {
        $data['ok'] = true;
        $data = $this->_to_utf8($data);              // < нормализация в UTF-8

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = json_encode(array(
                'ok'         => false,
                'error'      => 'json_encode failed: ' . json_last_error_msg(),
                'json_errno' => json_last_error(),
            ));
        }

        $this->response->status(200);
        $this->response->body($json);
        return $this->response;
    }

    protected function _fail($message, $status = 500)
    {
        $payload = $this->_to_utf8(array(            // < нормализация в UTF-8
            'ok'    => false,
            'error' => $message,
        ));

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '{"ok":false,"error":"json_encode failed"}';
        }

        $this->response->status($status);
        $this->response->body($json);
        return $this->response;
    }

    /**
     * Рекурсивно приводит строки к UTF-8 для json_encode.
     * Firebird у нас WIN1251, поэтому строки из ответа могут быть в CP1251.
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

    protected function _str($v)
    {
        if ($v === null) {
            return 'NULL';
        }
        $v = (string) $v;
        if ($v !== '' && function_exists('mb_check_encoding') && mb_check_encoding($v, 'UTF-8')) {
            $conv = @iconv('UTF-8', 'windows-1251//TRANSLIT//IGNORE', $v);
            if ($conv !== false) {
                $v = $conv;
            }
        }
        return "'" . str_replace("'", "''", $v) . "'";
    }

    protected function _findOrgIdByGuid($guid)
    {
        $sql = 'SELECT ID_ORG AS ID FROM ORGANIZATION '
             . 'WHERE GUID = ' . trim($this->_str($guid)) . ' '
             . 'ORDER BY ID_ORG DESC';
        try {
            $r = DB::query(Database::SELECT, $sql)->execute($this->_db)->as_array();
            return isset($r[0]) ? (int) Arr::get($r[0], 'ID') : null;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function _findPersonIdByGuid($guid)
    {
        $sql = 'SELECT ID_PEP AS ID FROM PEOPLE '
             . 'WHERE GUID = ' . trim($this->_str($guid)) . ' '
             . 'ORDER BY ID_PEP DESC';
        try {
            $r = DB::query(Database::SELECT, $sql)->execute($this->_db)->as_array();
            return isset($r[0]) ? (int) Arr::get($r[0], 'ID') : null;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function _findCardByCard($card)
    {
        $sql = 'SELECT ID_PEP FROM CARD WHERE ID_CARD = ' . (int) $card;

        try {
            $r = DB::query(Database::SELECT, $sql)->execute($this->_db)->get('ID_PEP');
            return ($r !== null && $r !== false) ? (int) $r : null;
        } catch (Exception $e) {
            return null;
        }
    }

    protected function _maxCardindevId()
    {
        try {
            $r = DB::query(Database::SELECT,
                'SELECT MAX(ID_CARDINDEV) AS MX FROM CARDINDEV'
            )->execute($this->_db)->as_array();
            return (int) Arr::get($r[0], 'MX', 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    protected function _newCardindev($after_id)
    {
        $sql = 'SELECT ID_CARDINDEV, OPERATION, ID_CARD, ID_PEP, ATTEMPTS'
             . ' FROM CARDINDEV WHERE ID_CARDINDEV > ' . (int) $after_id
             . ' ORDER BY ID_CARDINDEV';

        try {
            $rows = DB::query(Database::SELECT, $sql)->execute($this->_db)->as_array();
        } catch (Exception $e) {
            return array();
        }

        $out = array();
        foreach ($rows as $r) {
            $out[] = array(
                'id_cardindev' => (int) Arr::get($r, 'ID_CARDINDEV'),
                'operation'    => (int) Arr::get($r, 'OPERATION'),
                'id_card'      => Arr::get($r, 'ID_CARD'),
                'id_pep'       => Arr::get($r, 'ID_PEP'),
                'attempts'     => (int) Arr::get($r, 'ATTEMPTS'),
            );
        }
        return $out;
    }
}
