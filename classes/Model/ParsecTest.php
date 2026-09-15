<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модель тестовых операций Parsec.
 *
 * Все методы принимают ассоциативный массив параметров и возвращают
 * ассоциативный массив-ответ единого формата:
 *
 *   ['ok' => true,  ... полезные поля ...]
 *   ['ok' => false, 'error' => 'текст', 'status' => 400|404|409|500]
 *
 * Никакого вывода, никакого JSON — чистая логика.
 * Кодировки: на входе UTF-8, в SQL — CP1251 (через _str).
 */
class Model_ParsecTest extends Model
{
    /** @var Database */
    protected $_db;

    public function __construct()
    {
        $this->_db = Database::instance('fb');
    }

    // =================================================================
    // 1. Организация
    // =================================================================

    public function add_org(array $p)
    {
        $name        = trim((string) Arr::get($p, 'name', ''));
        $guid        = trim((string) Arr::get($p, 'guid', ''));
        $parent_guid = trim((string) Arr::get($p, 'parent_guid', ''));

        if ($name === '' || $guid === '') {
            return $this->_err('106 Параметры name и guid обязательны', 400);
        }

        $parent_id = 1;
        if ($parent_guid !== '') {
            $parent_id = $this->find_org_id($parent_guid);
            if ($parent_id === null) {
                return $this->_err('113 Родитель не найден: ' . $parent_guid, 404);
            }
        }

        $before = $this->max_cardindev_id();

        $sql = 'INSERT INTO ORGANIZATION (ID_DB, NAME, GUID, ID_PARENT) VALUES ('
             . '1, ' . $this->_str($name) . ', ' . trim($this->_str($guid)) . ', ' . (int) $parent_id
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_err('126 INSERT ORGANIZATION: ' . $e->getMessage(), 500);
        }

        $id_org = $this->find_org_id($guid);

        return array(
            'ok'            => true,
            'operation'     => 'add_org',
            'id_org'        => $id_org,
            'guid'          => $guid,
            'name'          => $name,
            'parent_guid'   => $parent_guid,
            'parent_id'     => $parent_id,
            'cardindev_new' => $this->new_cardindev($before),
        );
    }

    // =================================================================
    // 2. Персона
    // =================================================================

    public function add_person(array $p)
    {
        $surname    = trim((string) Arr::get($p, 'surname', ''));
        $name       = trim((string) Arr::get($p, 'name', ''));
        $patronymic = trim((string) Arr::get($p, 'patronymic', ''));
        $guid       = trim((string) Arr::get($p, 'guid', ''));
        $org_guid   = trim((string) Arr::get($p, 'org_guid', ''));
        $tabnum     = trim((string) Arr::get($p, 'tabnum', ''));

        if ($surname === '' || $guid === '' || $org_guid === '') {
            return $this->_err('156 Параметры surname, guid, org_guid обязательны', 400);
        }

        $id_org = $this->find_org_id($org_guid);
        if ($id_org === null) {
            return $this->_err('161 Организация не найдена: ' . $org_guid, 404);
        }

        $existing = $this->find_pep_id($guid);
        if ($existing !== null) {
            return $this->_err('166 GUID занят id_pep: ' . $existing, 409);
        }

        $before = $this->max_cardindev_id();

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
            return $this->_err('179 INSERT PEOPLE: ' . $e->getMessage(), 500);
        }

        return array(
            'ok'            => true,
            'operation'     => 'add_person',
            'id_pep'        => $this->find_pep_id($guid),
            'guid'          => $guid,
            'surname'       => $surname,
            'name'          => $name,
            'patronymic'    => $patronymic,
            'org_guid'      => $org_guid,
            'id_org'        => $id_org,
            'cardindev_new' => $this->new_cardindev($before),
        );
    }

    // =================================================================
    // 3. Карта
    // =================================================================

    public function add_card(array $p)
    {
        $pep_guid = trim((string) Arr::get($p, 'pep_guid', ''));
        $card     = trim((string) Arr::get($p, 'card', ''));

        if ($pep_guid === '' || $card === '') {
            return $this->_err('207 Параметры pep_guid и card обязательны', 400);
        }

        if (!ctype_digit($card)) {
            return $this->_err('215 card должен быть числом (десятичный номер карты)', 400);
        }

        $card_dec = (int) $card;
        $max      = 4294967296;   // 2^32

        if ($card_dec < 1 || $card_dec > $max) {
            return $this->_err('221 card вне диапазона 1..2^32', 400);
        }

        $id_pep = $this->find_pep_id($pep_guid);
        if ($id_pep === null) {
            return $this->_err('227 Персона не найдена: ' . $pep_guid, 404);
        }

        $_id_pep = $this->find_card_owner($card_dec);
        if ($_id_pep !== null) {
            return $this->_err(
                '233 Карта ' . $card_dec
                . ' (hex ' . strtoupper(str_pad(dechex($card_dec), 8, '0', STR_PAD_LEFT)) . ')'
                . ' уже выдана персоне id_pep = ' . $_id_pep,
                409
            );
        }

        $before = $this->max_cardindev_id();

        // ACTIVE — зарезервированное слово Firebird, обязательно в кавычках
        $sql = 'INSERT INTO CARD (ID_DB, ID_PEP, ID_CARD, TIMESTART, ID_CARDTYPE, "ACTIVE") VALUES ('
             . '1, ' . (int) $id_pep . ', ' . $card_dec . ', \'now\', 1, 1'
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_err('247 INSERT CARD: ' . $e->getMessage(), 500);
        }

        return array(
            'ok'            => true,
            'operation'     => 'add_card',
            'id_pep'        => $id_pep,
            'pep_guid'      => $pep_guid,
            'card'          => $card_dec,
            'card_hex'      => strtoupper(str_pad(dechex($card_dec), 8, '0', STR_PAD_LEFT)),
            'cardindev_new' => $this->new_cardindev($before),
        );
    }

    // =================================================================
    // 4. Категория доступа
    // =================================================================

    public function add_access(array $p)
    {
        $pep_guid      = trim((string) Arr::get($p, 'pep_guid', ''));
        $accessname_id = (int) Arr::get($p, 'accessname_id', 0);

        if ($pep_guid === '' || $accessname_id <= 0) {
            return $this->_err('271 Параметры pep_guid и accessname_id обязательны', 400);
        }

        $id_pep = $this->find_pep_id($pep_guid);
        if ($id_pep === null) {
            return $this->_err('276 Персона не найдена: ' . $pep_guid, 404);
        }

        $before = $this->max_cardindev_id();

        $sql = 'INSERT INTO SS_ACCESSUSER (ID_DB, ID_PEP, ID_ACCESSNAME) VALUES ('
             . '1, ' . (int) $id_pep . ', ' . (int) $accessname_id
             . ')';

        try {
            DB::query(Database::INSERT, $sql)->execute($this->_db);
        } catch (Exception $e) {
            return $this->_err('288 INSERT SS_ACCESSUSER: ' . $e->getMessage(), 500);
        }

        return array(
            'ok'            => true,
            'operation'     => 'add_access',
            'id_pep'        => $id_pep,
            'pep_guid'      => $pep_guid,
            'accessname_id' => $accessname_id,
            'cardindev_new' => $this->new_cardindev($before),
        );
    }

    // =================================================================
    // Статус
    // =================================================================

    public function status(array $p = array())
    {
        $limit = (int) Arr::get($p, 'limit', 20);
        if ($limit <= 0 || $limit > 500) {
            $limit = 20;
        }

        $max = $this->max_cardindev_id();

        $sql = 'SELECT FIRST ' . $limit
             . ' ID_CARDINDEV, OPERATION, ID_CARD, ID_PEP, ATTEMPTS, TIME_STAMP'
             . ' FROM CARDINDEV ORDER BY ID_CARDINDEV DESC';

        try {
            $rows = DB::query(Database::SELECT, $sql)
                ->execute($this->_db)
                ->as_array();
        } catch (Exception $e) {
            return $this->_err('322 SELECT CARDINDEV: ' . $e->getMessage(), 500);
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

        return array(
            'ok'        => true,
            'operation' => 'status',
            'max_id'    => $max,
            'rows'      => $out,
        );
    }

    // =================================================================
    // Cleanup
    // =================================================================

    public function cleanup(array $p = array())
    {
        $prefix = trim((string) Arr::get($p, 'prefix', 'TEST-'));
        if ($prefix === '') {
            $prefix = 'TEST-';
        }

        $escaped = str_replace("'", "''", $prefix);
        $like    = "'" . $escaped . "%'";
        $base    = $this->max_cardindev_id();

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
            return $this->_err('371 SELECT PEOPLE: ' . $e->getMessage(), 500);
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

        return array(
            'ok'        => true,
            'operation' => 'cleanup',
            'prefix'    => $prefix,
            'deleted'   => $deleted,
        );
    }

    // =================================================================
    // Публичные хелперы
    // =================================================================

    public function find_org_id($guid)
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

    public function find_pep_id($guid)
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

    public function find_card_owner($card_dec)
    {
        $sql = 'SELECT ID_PEP FROM CARD WHERE ID_CARD = ' . (int) $card_dec;

        try {
            $r = DB::query(Database::SELECT, $sql)->execute($this->_db)->get('ID_PEP');
            return ($r !== null && $r !== false) ? (int) $r : null;
        } catch (Exception $e) {
            return null;
        }
    }

    public function max_cardindev_id()
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

    public function new_cardindev($after_id)
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

    // =================================================================
    // Внутренние
    // =================================================================

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

    protected function _err($message, $status = 500)
    {
        return array('ok' => false, 'error' => $message, 'status' => $status);
    }
}