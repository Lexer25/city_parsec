<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модуль сверки категорий доступа СКУД Артонит и СКУД Parsec.
 *
 * В Артонит категории доступа (ACCESSNAME) выданы персоне (SS_ACCESSUSER).
 * В Parsec эти же категории должны быть выданы идентификаторам персоны
 * (у идентификатора — ACCGROUP_ID, а также вложенные группы, которые
 * возвращает GetInheritedAccessGroups).
 *
 * Когда идентификатору назначено несколько категорий, интегратор создаёт
 * "контейнерную" группу доступа и вкладывает в неё все категории. Поэтому
 * эффективный набор групп идентификатора = {ACCGROUP_ID} ? GetInheritedAccessGroups(ACCGROUP_ID).
 *
 * Сверка делается на уровне персоны (как в триггерах PARSEC_07/08_SS_ACCESSUSER):
 *   - категория есть у человека в Артонит, но её нет ни на одной его карте в Parsec > задача 7 (добавить)
 *   - категория есть на картах в Parsec, но нет у человека в Артонит > задача 8 (удалить)
 *
 * Запуск:
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion --task=parsecSyncAccessGroups
 *
 * Опции:
 *   --add     1|0   Ставить задачи (по умолчанию 0)
 *   --limit   N     Ограничить количество персон (0 — без ограничений)
 *   --id_pep  N     Проверить только одну персону
 *   --verbose 1|0   Подробный вывод
 *
 * @version 1.0.0
 * @date    2026-09-13
 */
class Task_parsecSyncAccessGroups extends Minion_Task
{
    protected $_options = array(
        'add'     => 0,
        'limit'   => 0,
        'id_pep'  => 0,
        'verbose' => 1,
    );

    /** @var Model_Cch */
    protected $_cch_model = null;

    /** @var string */
    protected $_session_id = null;

    /**
     * @var array Карта ACCESSNAME Артонит по GUID: [GUID => ['id'=>int, 'name'=>string]]
     * Заполняется один раз при старте задачи.
     */
    protected $_accessname_by_guid = null;

    /**
     * @var array Кэш эффективных групп по ACCGROUP_ID: [accgroup_guid => [guid1, guid2, ...]]
     */
    protected $_accgroup_cache = array();

    /** @var array */
    protected $_stats = array(
        'total'          => 0,
        'checked'        => 0,
        'ok'             => 0,
        'mismatch'       => 0,
        'no_parsec_pep'  => 0,
        'no_parsec_card' => 0,
        'no_artonit_cat' => 0,
        'skipped'        => 0,
        'errors'         => 0,
        'tasks_add'      => 0,
        'tasks_del'      => 0,
        'time'           => 0,
    );

    /** @var array */
    protected $_errors = array();

    // ---------------------------------------------------------------------
    // Точка входа
    // ---------------------------------------------------------------------

    protected function _execute(array $params)
    {
        $start_time = microtime(true);

        $add     = (int) Arr::get($params, 'add', 0) === 1;
        $limit   = (int) Arr::get($params, 'limit', 0);
        $id_pep  = (int) Arr::get($params, 'id_pep', 0);
        $verbose = (int) Arr::get($params, 'verbose', 1) === 1;

        Minion_CLI::write('=== Сверка категорий доступа СКУД Артонит и Parsec ===');
        Minion_CLI::write('Режим добавления задач: ' . ($add ? 'ДА' : 'НЕТ'));
        if ($limit > 0) {
            Minion_CLI::write('Ограничение: ' . $limit . ' персон');
        }
        if ($id_pep > 0) {
            Minion_CLI::write('Проверка только id_pep=' . $id_pep);
        }
        Minion_CLI::write('');

        // --- 1. Модель CCH ---
        try {
            $this->_cch_model = new Model_Cch();
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА: не удалось создать Model_Cch: ' . $e->getMessage());
            return;
        }

        if ($this->_cch_model->isMockMode()) {
            Minion_CLI::write('*** ВНИМАНИЕ: включён MOCK-режим. Реальный Parsec не опрашивается. ***');
            Minion_CLI::write('');
        }

        // --- 2. Структура БД ---
        $db_errors = $this->_cch_model->checkDatabaseStructure();
        if (!empty($db_errors)) {
            foreach ($db_errors as $err) {
                Minion_CLI::write('ОШИБКА СТРУКТУРЫ БД: ' . $err);
            }
            return;
        }

        // --- 3. Сессия ---
        if (!$this->_open_session()) {
            return;
        }

        // --- 4. Карта ACCESSNAME Артонит ---
        $this->_load_accessname_map();
        if (empty($this->_accessname_by_guid)) {
            Minion_CLI::write('В таблице ACCESSNAME нет ни одной записи с GUID — сверять нечего.');
            return;
        }
        Minion_CLI::write('Категорий доступа в Артонит: ' . count($this->_accessname_by_guid));
        Minion_CLI::write('');

        // --- 5. Список персон ---
        $people = $this->_get_people_list($id_pep, $limit);
        if (empty($people)) {
            Minion_CLI::write('Нет персон для проверки.');
            return;
        }

        $this->_stats['total'] = count($people);
        Minion_CLI::write('Получено персон для проверки: ' . count($people));
        Minion_CLI::write('');

        // --- 5.1. Шапка ---
        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                'Счётчик',
                'Статус',
                'ФИО',
                'Artonit (категории)',
                'Parsec (группы)',
                'Примечание'
            ));
            Minion_CLI::write(str_repeat('=', 160));
        }

        // --- 6. Основной цикл ---
        $i = 0;
        foreach ($people as $row) {
            $i++;
            $this->_check_person($row, $add, $verbose, $i, count($people));
        }

        // --- 7. Итоги ---
        $this->_stats['time'] = round(microtime(true) - $start_time, 2);
        $this->_print_summary();
    }

    // ---------------------------------------------------------------------
    // Инфраструктура
    // ---------------------------------------------------------------------

    protected function _log_row($prefix, $status, $fio = '', $artonit = '', $parsec = '', $note = '')
    {
        return $prefix  . "\t"
             . $status  . "\t"
             . $fio     . "\t"
             . $artonit . "\t"
             . $parsec  . "\t"
             . $note;
    }

    protected function _utf8_to_cp1251($str)
    {
        $str = (string) $str;
        if ($str === '') {
            return '';
        }
        if (function_exists('mb_check_encoding') && !mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }
        $converted = @iconv('UTF-8', 'CP1251//TRANSLIT//IGNORE', $str);
        return ($converted === false) ? $str : $converted;
    }

    protected function _open_session()
    {
        $status = $this->_cch_model->getConnectionStatus();
        if ($status->error) {
            Minion_CLI::write('ОШИБКА подключения к Parsec: ' . $status->message);
            return false;
        }

        $openSessionResult = $this->_cch_model->OpenSession();

        if (isset($openSessionResult->error) && $openSessionResult->error) {
            Minion_CLI::write('ОШИБКА OpenSession: ' . $openSessionResult->message);
            return false;
        }

        if (isset($openSessionResult->OpenSessionResult->Result)
            && $openSessionResult->OpenSessionResult->Result != 0) {
            $err = isset($openSessionResult->OpenSessionResult->ErrorMessage)
                ? $openSessionResult->OpenSessionResult->ErrorMessage
                : 'неизвестная ошибка';
            Minion_CLI::write('ОШИБКА авторизации Parsec: ' . $err);
            return false;
        }

        if (isset($openSessionResult->OpenSessionResult->Value->SessionID)) {
            $this->_session_id = $openSessionResult->OpenSessionResult->Value->SessionID;
            Minion_CLI::write('Сессия Parsec открыта: ' . $this->_session_id);
            Minion_CLI::write('');
            return true;
        }

        Minion_CLI::write('ОШИБКА: не получен SessionID от Parsec.');
        return false;
    }

    /**
     * Загружает карту ACCESSNAME Артонит по GUID.
     */
    protected function _load_accessname_map()
    {
        $this->_accessname_by_guid = array();

        $sql = 'SELECT an.id_accessname, an.guid, an.name
                FROM accessname an
                WHERE an.guid IS NOT NULL';

        try {
            $rows = DB::query(Database::SELECT, $sql)
                ->execute(Database::instance('fb'))
                ->as_array();

            foreach ($rows as $row) {
                $guid = strtoupper(trim((string) Arr::get($row, 'GUID')));
                if ($guid === '') {
                    continue;
                }
                $this->_accessname_by_guid[$guid] = array(
                    'id'   => (int) Arr::get($row, 'ID_ACCESSNAME'),
                    'name' => (string) Arr::get($row, 'NAME'),
                );
            }
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL (accessname): ' . $e->getMessage());
        }
    }

    /**
     * Список персон Артонит с guid.
     */
    protected function _get_people_list($id_pep = 0, $limit = 0)
    {
        $sql = 'SELECT p.id_pep, p.guid AS pep_guid,
                       p.surname, p.name, p.patronymic
                FROM people p
                WHERE p.guid IS NOT NULL
                  AND p.id_pep > 1';

        if ($id_pep > 0) {
            $sql .= ' AND p.id_pep = ' . (int) $id_pep;
        }

        $sql .= ' ORDER BY p.id_pep';

        if ($limit > 0) {
            $sql = preg_replace('/^SELECT /', 'SELECT FIRST ' . (int) $limit . ' ', $sql);
        }

        try {
            return DB::query(Database::SELECT, $sql)
                ->execute(Database::instance('fb'))
                ->as_array();
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL (people): ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Категории персоны в Артонит: [GUID => ['id'=>int, 'name'=>string]].
     */
    protected function _get_person_artonit_categories($id_pep)
    {
        $result = array();

        $sql = 'SELECT ssu.id_accessname, an.guid, an.name
                FROM ss_accessuser ssu
                JOIN accessname an ON an.id_accessname = ssu.id_accessname
                WHERE ssu.id_pep = ' . (int) $id_pep;

        try {
            $rows = DB::query(Database::SELECT, $sql)
                ->execute(Database::instance('fb'))
                ->as_array();

            foreach ($rows as $row) {
                $guid = strtoupper(trim((string) Arr::get($row, 'GUID')));
                if ($guid === '') {
                    continue;
                }
                $result[$guid] = array(
                    'id'   => (int) Arr::get($row, 'ID_ACCESSNAME'),
                    'name' => (string) Arr::get($row, 'NAME'),
                );
            }
        } catch (Exception $e) {
            $this->_errors[] = array(
                'id_pep'  => $id_pep,
                'message' => 'SQL(ss_accessuser): ' . $e->getMessage(),
            );
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // Основная проверка
    // ---------------------------------------------------------------------

    protected function _check_person(array $row, $add, $verbose, $i, $total)
    {
        $id_pep   = (int) Arr::get($row, 'ID_PEP');
        $pep_guid = strtoupper(trim((string) Arr::get($row, 'PEP_GUID')));
        $fio      = trim(
            Arr::get($row, 'SURNAME') . ' ' .
            Arr::get($row, 'NAME') . ' ' .
            Arr::get($row, 'PATRONYMIC')
        );

        $prefix = sprintf('[%d/%d] id_pep=%s guid=%s', $i, $total, $id_pep, $pep_guid);

        if ($pep_guid === '') {
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ПРОПУЩЕН', $fio, '', '', 'пустой GUID персоны'
                ));
            }
            return;
        }

        // --- 1. Категории из Артонит ---
        $artonit_cats = $this->_get_person_artonit_categories($id_pep);

        // Имена для красивого вывода
        $artonit_cats_display = array();
        foreach ($artonit_cats as $g => $info) {
            $artonit_cats_display[] = $g . ($info['name'] !== '' ? ' (' . $info['name'] . ')' : '');
        }
        $artonit_cats_str = implode(', ', $artonit_cats_display);

        // --- 2. Карты в Parsec ---
        $ids_response = $this->_cch_model->GetPersonIdentifiers($this->_session_id, $pep_guid);

        if (isset($ids_response->error) && $ids_response->error) {
            $this->_stats['errors']++;
            $msg = isset($ids_response->message) ? $ids_response->message : 'SOAP error';
            $this->_errors[] = array('id_pep' => $id_pep, 'message' => 'GetPersonIdentifiers: ' . $msg);
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ОШИБКА SOAP', $fio, $artonit_cats_str, '',
                    $this->_utf8_to_cp1251($msg)
                ));
            }
            return;
        }

        $identifiers = $this->_extract_parsec_identifiers($ids_response);

        if (empty($identifiers)) {
            $this->_stats['no_parsec_card']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'НЕТ КАРТ', $fio, $artonit_cats_str, '',
                    'у персоны нет идентификаторов в Parsec'
                ));
            }
            return;
        }

        // --- 3. Эффективные группы по всем картам ---
        $effective = array(); // [guid => true]
        foreach ($identifiers as $idn) {
            $accgroup = $idn['accgroup_id'];
            if ($accgroup === '') {
                continue;
            }
            $effective[$accgroup] = true;

            foreach ($this->_get_inherited_groups($accgroup) as $g) {
                $effective[$g] = true;
            }
        }

        // Фильтруем: оставляем только те группы, что известны в Артонит (ACCESSNAME)
        $effective_artonit = array();
        foreach (array_keys($effective) as $g) {
            if (isset($this->_accessname_by_guid[$g])) {
                $effective_artonit[$g] = true;
            }
        }

        $parsec_groups_display = array();
        foreach (array_keys($effective_artonit) as $g) {
            $nm = $this->_accessname_by_guid[$g]['name'];
            $parsec_groups_display[] = $g . ($nm !== '' ? ' (' . $nm . ')' : '');
        }
        $parsec_groups_str = implode(', ', $parsec_groups_display);

        // --- 4. Сравнение ---
        $only_artonit = array_diff_key($artonit_cats, $effective_artonit);
        $only_parsec  = array_diff_key($effective_artonit, $artonit_cats);

        if (empty($only_artonit) && empty($only_parsec)) {
            $this->_stats['ok']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'OK', $fio, $artonit_cats_str, $parsec_groups_str, ''
                ));
            }
            return;
        }

        $this->_stats['mismatch']++;

        $notes = array();
        if (!empty($only_artonit)) {
            $notes[] = '+ в Артонит: ' . implode(', ', array_keys($only_artonit));
        }
        if (!empty($only_parsec)) {
            $notes[] = '- в Parsec: ' . implode(', ', array_keys($only_parsec));
        }

        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                $prefix, 'РАСХОЖДЕНИЕ', $fio,
                $artonit_cats_str,
                $parsec_groups_str,
                implode('; ', $notes)
            ));
        }

        // --- 5. Задачи ---
        if ($add) {
            // Категории, которых нет в Parsec > задача 7
            foreach ($only_artonit as $g => $info) {
                if ($this->_add_task_access($id_pep, $info['id'], 7)) {
                    $this->_stats['tasks_add']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix, '  -> задача 7', '', '', '',
                            'добавить категорию ' . $g
                            . ($info['name'] !== '' ? ' (' . $info['name'] . ')' : '')
                        ));
                    }
                }
            }

            // Категории, которых нет в Артонит > задача 8
            foreach ($only_parsec as $g => $info) {
                $access_id = isset($this->_accessname_by_guid[$g])
                    ? $this->_accessname_by_guid[$g]['id']
                    : null;
                if ($access_id === null) {
                    continue;
                }

                if ($this->_add_task_access($id_pep, $access_id, 8)) {
                    $this->_stats['tasks_del']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix, '  -> задача 8', '', '', '',
                            'удалить категорию ' . $g
                            . ($info['name'] !== '' ? ' (' . $info['name'] . ')' : '')
                        ));
                    }
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Работа с Parsec
    // ---------------------------------------------------------------------

    /**
     * Извлекает список идентификаторов из ответа GetPersonIdentifiers.
     * Возвращает массив: [ ['code'=>..., 'accgroup_id'=>..., 'is_primary'=>...], ... ]
     */
    protected function _extract_parsec_identifiers($response)
    {
        $result = array();

        if (!isset($response->GetPersonIdentifiersResult)) {
            return $result;
        }

        $root = $response->GetPersonIdentifiersResult;

        if (is_object($root)) {
            $root = (array) $root;
        }
        if (!is_array($root)) {
            return $result;
        }

        // Разворачиваем обёртки
        $wrapper_keys = array(
            'Identifier', 'Identifiers',
            'IDENTIFIER', 'IDENTIFIERS',
            'IdentifierList', 'IDENTIFIER_LIST',
            'Items', 'ITEMS',
            'List', 'LIST',
            'Cards', 'CARDS',
        );

        $items = null;
        foreach ($wrapper_keys as $key) {
            if (isset($root[$key])) {
                $candidate = $root[$key];
                if (is_object($candidate)) {
                    $candidate = (array) $candidate;
                }
                if (is_array($candidate)) {
                    $items = $candidate;
                    break;
                }
            }
        }

        if ($items === null) {
            $items = $root;
        }

        if (!is_array($items)) {
            $items = array($items);
        } else {
            $first = reset($items);
            if (!is_object($first) && !is_array($first)) {
                $items = array($items);
            }
        }

        foreach ($items as $item) {
            if (!is_object($item) && !is_array($item)) {
                continue;
            }
            if (is_object($item)) {
                $item = (array) $item;
            }

            // Пропускаем служебные поля/не идентификаторы
            $code = '';
            foreach (array('CODE', 'Code', 'code') as $k) {
                if (isset($item[$k]) && is_scalar($item[$k])) {
                    $code = strtoupper(trim((string) $item[$k]));
                    break;
                }
            }
            if ($code === '') {
                continue;
            }

            $accgroup = '';
            foreach (array('ACCGROUP_ID', 'AccGroupID', 'AccGroupId', 'accgroup_id') as $k) {
                if (isset($item[$k]) && is_scalar($item[$k])) {
                    $accgroup = strtoupper(trim((string) $item[$k]));
                    break;
                }
            }

            $is_primary = null;
            foreach (array('IS_PRIMARY', 'IsPrimary', 'is_primary') as $k) {
                if (isset($item[$k])) {
                    $is_primary = (bool) $item[$k];
                    break;
                }
            }

            $result[] = array(
                'code'       => $code,
                'accgroup_id'=> $accgroup,
                'is_primary' => $is_primary,
            );
        }

        return $result;
    }

    /**
     * Возвращает массив GUID вложенных групп для ACCGROUP_ID.
     * Кэшируется.
     */
    protected function _get_inherited_groups($accgroup_guid)
    {
        $accgroup_guid = strtoupper(trim((string) $accgroup_guid));
        if ($accgroup_guid === '') {
            return array();
        }

        if (array_key_exists($accgroup_guid, $this->_accgroup_cache)) {
            return $this->_accgroup_cache[$accgroup_guid];
        }

        $resp = $this->_cch_model->GetInheritedAccessGroups($this->_session_id, $accgroup_guid);

        $guids = array();

        if (!isset($resp->error) || !$resp->error) {
            // Ответ: либо массив GUID, либо объект-обёртка
            $payload = null;
            if (isset($resp->GetInheritedAccessGroupsResult)) {
                $payload = $resp->GetInheritedAccessGroupsResult;
            } else {
                $payload = $resp;
            }

            if (is_object($payload)) {
                $payload = (array) $payload;
            }

            if (is_array($payload)) {
                foreach ($payload as $g) {
                    if (is_scalar($g)) {
                        $g = strtoupper(trim((string) $g));
                        if ($g !== '') {
                            $guids[] = $g;
                        }
                    } elseif (is_object($g) || is_array($g)) {
                        $g = (array) $g;
                        foreach (array('ID', 'Id', 'id', 'GUID', 'Guid', 'guid') as $k) {
                            if (isset($g[$k]) && is_scalar($g[$k])) {
                                $v = strtoupper(trim((string) $g[$k]));
                                if ($v !== '') {
                                    $guids[] = $v;
                                }
                                break;
                            }
                        }
                    }
                }
            }
        }

        $this->_accgroup_cache[$accgroup_guid] = $guids;
        return $guids;
    }

    // ---------------------------------------------------------------------
    // Постановка задач
    // ---------------------------------------------------------------------

    /**
     * Ставит задачу 7/8 на категорию доступа у персоны.
     * Операция 7: добавить категорию, 8: удалить.
     * Формат — как в триггерах PARSEC_07/08_SS_ACCESSUSER_*:
     *   id_card = id_accessname, id_pep = id_pep
     *
     * @param int $id_pep
     * @param int $id_accessname
     * @param int $operation 7 или 8
     * @return bool
     */
    protected function _add_task_access($id_pep, $id_accessname, $operation)
    {
        $id_pep        = (int) $id_pep;
        $id_accessname = (int) $id_accessname;
        $operation     = (int) $operation;

        if (!in_array($operation, array(7, 8), true)) {
            return false;
        }
        if ($id_pep <= 0 || $id_accessname <= 0) {
            return false;
        }

        try {
            // Защита от дублей
            $check_sql = 'SELECT COUNT(*) AS CNT FROM cardindev cd
                          WHERE cd.operation = ' . $operation . '
                            AND cd.id_pep   = ' . $id_pep . '
                            AND cd.id_card  = ' . $id_accessname;

            $count = DB::query(Database::SELECT, $check_sql)
                ->execute(Database::instance('fb'))
                ->get('CNT');

            if ((int) $count > 0) {
                return false;
            }

            $sql = 'INSERT INTO CARDINDEV
                        (ID_DB, ID_CARD, DEVIDX, ID_DEV, OPERATION, ATTEMPTS, ID_PEP)
                    VALUES
                        (1, ' . $id_accessname . ', NULL, NULL, ' . $operation . ', 0, ' . $id_pep . ')';

            DB::query(Database::INSERT, $sql)
                ->execute(Database::instance('fb'));

            return true;
        } catch (Exception $e) {
            Kohana::$log->add(
                Log::ERROR,
                'parsecSyncAccessGroups: не удалось создать задачу ' . $operation
                . ' id_pep=' . $id_pep . ' id_accessname=' . $id_accessname
                . ': ' . $e->getMessage()
            );
            return false;
        }
    }

    // ---------------------------------------------------------------------
    // Итоги
    // ---------------------------------------------------------------------

    protected function _print_summary()
    {
        Minion_CLI::write('');
        Minion_CLI::write('=== ИТОГИ ===');
        Minion_CLI::write('Всего проверено:                   ' . $this->_stats['total']);
        Minion_CLI::write('Категории совпадают (OK):          ' . $this->_stats['ok']);
        Minion_CLI::write('Есть расхождения:                  ' . $this->_stats['mismatch']);
        Minion_CLI::write('Нет карт в Parsec:                 ' . $this->_stats['no_parsec_card']);
        Minion_CLI::write('Персон нет в Parsec:               ' . $this->_stats['no_parsec_pep']);
        Minion_CLI::write('Категорий в Артонит нет:           ' . $this->_stats['no_artonit_cat']);
        Minion_CLI::write('Пропущено:                         ' . $this->_stats['skipped']);
        Minion_CLI::write('Ошибок SOAP/ответа:                ' . $this->_stats['errors']);
        Minion_CLI::write('Создано задач на добавление (7):   ' . $this->_stats['tasks_add']);
        Minion_CLI::write('Создано задач на удаление (8):     ' . $this->_stats['tasks_del']);
        Minion_CLI::write('Время выполнения:                  ' . $this->_stats['time'] . ' сек');
        Minion_CLI::write('');

        if (!empty($this->_errors)) {
            Minion_CLI::write('=== ОШИБКИ (первые 20) ===');
            $n = 0;
            foreach ($this->_errors as $err) {
                $n++;
                if ($n > 20) {
                    Minion_CLI::write('... и ещё ' . (count($this->_errors) - 20) . ' ошибок, см. лог.');
                    break;
                }
                Minion_CLI::write(sprintf(
                    'id_pep=%s : %s',
                    isset($err['id_pep']) ? $err['id_pep'] : '?',
                    $this->_utf8_to_cp1251($err['message'])
                ));
            }
            Minion_CLI::write('');
        }

        Minion_CLI::write('Готово.');
    }
}