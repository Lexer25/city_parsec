<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модуль сверки дерева организаций СКУД Артонит и СКУД Parsec.
 *
 * Алгоритм:
 *   1. Из Артонит берём организации (id_org > 3, guid не пуст) вместе с
 *      guid родителя.
 *   2. Для каждой через SOAP Parsec вызываем GetOrgUnit(guid).
 *   3. Если организации нет в Parsec > задача 5 (добавить организацию).
 *   4. Если организация есть — сравниваем PARENT_ID (Parsec) с guid родителя
 *      из Артонит. При расхождении > задача 55 (изменить организацию).
 *   5. По окончании выводим статистику.
 *
 * Запуск:
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion --task=parsecSyncOrg
 *
 * Опции:
 *   --add         1|0   Ставить задачи интегратору (по умолчанию 0)
 *   --limit       N     Ограничить количество проверяемых организаций (0 — без ограничений)
 *   --id_org      N     Проверить только одну организацию
 *   --verbose     1|0   Подробный вывод
 *
 * Формат лога — табличный, разделитель колонок "\t":
 *   счётчик | статус | организация | Artonit parent | Parsec parent | примечание
 *
 * @version 1.0.0
 * @date    2026-09-13
 */
class Task_parsecSyncOrg extends Minion_Task
{
    protected $_options = array(
        'add'     => 0,
        'limit'   => 0,
        'id_org'  => 0,
        'verbose' => 1,
    );

    /** @var Model_Cch */
    protected $_cch_model = null;

    /** @var string */
    protected $_session_id = null;

    /** @var array Кэш названий организаций Parsec: guid => name (CP1251) */
    protected $_org_name_cache = array();

    /** @var array */
    protected $_stats = array(
        'total'         => 0,
        'in_parsec'     => 0,
        'not_in_parsec' => 0,
        'parent_ok'     => 0,
        'parent_diff'   => 0,
        'name_diff'     => 0,
        'skipped'       => 0,
        'errors'        => 0,
        'tasks_add'     => 0,
        'tasks_update'  => 0,
        'time'          => 0,
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
        $id_org  = (int) Arr::get($params, 'id_org', 0);
        $verbose = (int) Arr::get($params, 'verbose', 1) === 1;

        Minion_CLI::write('=== Сверка организаций СКУД Артонит и Parsec ===');
        Minion_CLI::write('Режим добавления задач: ' . ($add ? 'ДА' : 'НЕТ'));
        if ($limit > 0) {
            Minion_CLI::write('Ограничение: ' . $limit . ' организаций');
        }
        if ($id_org > 0) {
            Minion_CLI::write('Проверка только id_org=' . $id_org);
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

        // --- 2. Проверка структуры БД ---
        $db_errors = $this->_cch_model->checkDatabaseStructure();
        if (!empty($db_errors)) {
            foreach ($db_errors as $err) {
                Minion_CLI::write('ОШИБКА СТРУКТУРЫ БД: ' . $err);
            }
            return;
        }

        // --- 3. Открытие сессии ---
        if (!$this->_open_session()) {
            return;
        }

        // --- 4. Список организаций из Артонит ---
        $orgs = $this->_get_organizations($id_org, $limit);
        if (empty($orgs)) {
            Minion_CLI::write('Нет организаций для проверки.');
            return;
        }

        $this->_stats['total'] = count($orgs);
        Minion_CLI::write('Получено организаций для проверки: ' . count($orgs));
        Minion_CLI::write('');

        // --- 4.1. Шапка таблицы ---
        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                'Счётчик',
                'Статус',
                'Организация',
                'Artonit parent',
                'Parsec parent',
                'Примечание'
            ));
            Minion_CLI::write(str_repeat('=', 160));
        }

        // --- 5. Основной цикл ---
        $i = 0;
        foreach ($orgs as $row) {
            $i++;
            $this->_check_organization($row, $add, $verbose, $i, count($orgs));
        }

        // --- 6. Итоги ---
        $this->_stats['time'] = round(microtime(true) - $start_time, 2);
        $this->_print_summary();
    }

    // ---------------------------------------------------------------------
    // Вспомогательные методы
    // ---------------------------------------------------------------------

    /**
     * Формирует строку лога в табличном формате.
     */
    protected function _log_row($prefix, $status, $org = '', $artonit = '', $parsec = '', $note = '')
    {
        return $prefix  . "\t"
             . $status  . "\t"
             . $org     . "\t"
             . $artonit . "\t"
             . $parsec  . "\t"
             . $note;
    }

    /**
     * UTF-8 > CP1251 (для ответов Parsec).
     */
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

    /**
     * Открывает сессию Parsec.
     */
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
     * Возвращает список организаций Артонит.
     * ВАЖНО: имя колонки родителя (id_parent) зависит от схемы БД.
     * Если в вашей БД оно называется иначе (id_org_parent, parent_id),
     * поправьте SQL в одном месте — ниже.
     */
    protected function _get_organizations($id_org = 0, $limit = 0)
    {
        $sql = 'SELECT o.id_org,
                       o.guid              AS org_guid,
                       o.name              AS org_name,
                       p.guid              AS parent_guid,
                       p.name              AS parent_name
                FROM organization o
                LEFT JOIN organization p ON p.id_org = o.id_parent
                WHERE o.guid IS NOT NULL
                  AND o.id_org > 3';

        if ($id_org > 0) {
            $sql .= ' AND o.id_org = ' . (int) $id_org;
        }

        $sql .= ' ORDER BY o.id_org';

        if ($limit > 0) {
            $sql = preg_replace('/^SELECT /', 'SELECT FIRST ' . (int) $limit . ' ', $sql);
        }

        try {
            return DB::query(Database::SELECT, $sql)
                ->execute(Database::instance('fb'))
                ->as_array();
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Проверяет одну организацию.
     */
    protected function _check_organization(array $row, $add, $verbose, $i, $total)
    {
        $id_org      = (int) Arr::get($row, 'ID_ORG');
        $org_guid    = strtoupper(trim((string) Arr::get($row, 'ORG_GUID')));
        $org_name    = (string) Arr::get($row, 'ORG_NAME');
        $parent_guid = strtoupper(trim((string) Arr::get($row, 'PARENT_GUID')));
        $parent_name = (string) Arr::get($row, 'PARENT_NAME');

        $prefix = sprintf('[%d/%d] id_org=%s guid=%s', $i, $total, $id_org, $org_guid);

        // --- Проверки входных данных ---
        if ($org_guid === '') {
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ПРОПУЩЕН', $org_name, '', '', 'пустой GUID организации'
                ));
            }
            return;
        }

        // --- Запрос в Parsec ---
        $response = $this->_cch_model->GetOrgUnit($this->_session_id, $org_guid);

        // --- Ошибка SOAP ---
        if (isset($response->error) && $response->error) {
            $this->_stats['errors']++;
            $message = isset($response->message) ? $response->message : 'неизвестная ошибка SOAP';
            $this->_errors[] = array(
                'id_org'  => $id_org,
                'guid'    => $org_guid,
                'message' => $message,
            );
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ОШИБКА SOAP', $org_name, '', '',
                    $this->_utf8_to_cp1251($message)
                ));
            }
            return;
        }

        // --- Разбор ответа ---
        $parsec = isset($response->GetOrgUnitResult) ? $response->GetOrgUnitResult : null;

        if (is_array($parsec) && empty($parsec)) {
            $parsec = null;
        }

        // --- Организации нет в Parsec ---
        if ($parsec === null) {
            $this->_stats['not_in_parsec']++;

            $artonit_parent_display = $parent_guid !== ''
                ? $parent_guid . ($parent_name !== '' ? ' (' . $parent_name . ')' : '')
                : '(корень)';

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'НЕТ В PARSEC', $org_name,
                    'parent: ' . $artonit_parent_display,
                    '',
                    ''
                ));
            }

            if ($add) {
                if ($this->_add_task_org($org_guid, 5)) {
                    $this->_stats['tasks_add']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix, '  -> задача 5', '', '', '',
                            'создана задача 5 (добавить организацию)'
                        ));
                    }
                }
            }
            return;
        }

        // --- Организация есть в Parsec ---
        $this->_stats['in_parsec']++;

        // Извлекаем PARENT_ID и NAME из ответа Parsec
        $parsec_parent_guid = $this->_extract_org_parent($parsec);
        $parsec_name        = $this->_extract_org_name($parsec);

        // Нормализация
        $parsec_parent_guid = strtoupper(trim((string) $parsec_parent_guid));

        // Сравнение родителей
        $parent_match = ($parsec_parent_guid === $parent_guid);

        // Сравнение имён (по байтам, без учёта регистра)
        $name_match = true;
        if ($parsec_name !== '' && $org_name !== '') {
            $name_match = (strcasecmp($this->_utf8_to_cp1251($parsec_name), $org_name) === 0);
        }

        $artonit_parent_display = $parent_guid !== ''
            ? $parent_guid . ($parent_name !== '' ? ' (' . $parent_name . ')' : '')
            : '(корень)';
        $parsec_parent_display = $parsec_parent_guid !== ''
            ? $parsec_parent_guid
            : '(корень)';

        // --- Полное совпадение ---
        if ($parent_match && $name_match) {
            $this->_stats['parent_ok']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'OK', $org_name,
                    'parent: ' . $artonit_parent_display,
                    'parent: ' . $parsec_parent_display,
                    ''
                ));
            }
            return;
        }

        // --- Есть расхождения ---
        $notes = array();

        if (!$parent_match) {
            $this->_stats['parent_diff']++;
            $notes[] = 'родитель не совпадает';
        }
        if (!$name_match) {
            $this->_stats['name_diff']++;
            $notes[] = 'имя не совпадает (Parsec: ' . $this->_utf8_to_cp1251($parsec_name) . ')';
        }

        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                $prefix, 'РАСХОЖДЕНИЕ', $org_name,
                'parent: ' . $artonit_parent_display,
                'parent: ' . $parsec_parent_display,
                implode('; ', $notes)
            ));
        }

        if ($add) {
            if ($this->_add_task_org($org_guid, 55)) {
                $this->_stats['tasks_update']++;
                if ($verbose) {
                    Minion_CLI::write($this->_log_row(
                        $prefix, '  -> задача 55', '', '', '',
                        'создана задача 55 (изменить организацию)'
                    ));
                }
            }
        }
    }

    /**
     * Рекурсивно ищет PARENT_ID в ответе GetOrgUnit.
     *
     * @param mixed $data
     * @param int   $depth
     * @return string
     */
    protected function _extract_org_parent($data, $depth = 0)
    {
        if ($depth > 5) {
            return '';
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            return '';
        }

        $keys = array(
            'PARENT_ID', 'ParentID', 'ParentId', 'parent_id',
            'PARENT_GUID', 'ParentGUID',
            'PARENT', 'Parent',
            'ORG_UNIT_PARENT_ID', 'ORGUNIT_PARENT_ID',
        );

        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        // Если PARENT — объект с ID внутри
        foreach (array('Parent', 'PARENT', 'ParentOrgUnit') as $parent_key) {
            if (isset($data[$parent_key]) && (is_object($data[$parent_key]) || is_array($data[$parent_key]))) {
                $sub = (array) $data[$parent_key];
                foreach (array('ID', 'Id', 'id', 'GUID', 'Guid', 'guid') as $id_key) {
                    if (isset($sub[$id_key]) && is_scalar($sub[$id_key]) && $sub[$id_key] !== '') {
                        return (string) $sub[$id_key];
                    }
                }
                $found = $this->_extract_org_parent($data[$parent_key], $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        // Рекурсивный обход
        foreach ($data as $value) {
            if (is_object($value) || is_array($value)) {
                $found = $this->_extract_org_parent($value, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * Рекурсивно ищет NAME в ответе GetOrgUnit.
     *
     * @param mixed $data
     * @param int   $depth
     * @return string
     */
    protected function _extract_org_name($data, $depth = 0)
    {
        if ($depth > 5) {
            return '';
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!is_array($data)) {
            return '';
        }

        foreach (array('NAME', 'Name', 'name') as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                return (string) $data[$key];
            }
        }

        foreach ($data as $value) {
            if (is_object($value) || is_array($value)) {
                $found = $this->_extract_org_name($value, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * Ставит задачу интегратору на добавление (5) или изменение (55) организации.
     *
     * @param string $org_guid
     * @param int    $operation 5 или 55
     * @return bool
     */
    protected function _add_task_org($org_guid, $operation)
    {
        $operation = (int) $operation;

        if (!in_array($operation, array(5, 55), true)) {
            return false;
        }

        $org_guid = trim((string) $org_guid);
        if ($org_guid === '') {
            return false;
        }

        $org_guid_sql = "'" . str_replace("'", "''", $org_guid) . "'";

        try {
            // Защита от дублей
            $check_sql = 'SELECT COUNT(*) AS CNT FROM cardindev cd
                          WHERE cd.operation = ' . $operation . '
                            AND cd.id_card   = ' . $org_guid_sql;

            $count = DB::query(Database::SELECT, $check_sql)
                ->execute(Database::instance('fb'))
                ->get('CNT');

            if ((int) $count > 0) {
                return false;
            }

            $sql = 'INSERT INTO CARDINDEV
                        (ID_DB, ID_CARD, DEVIDX, ID_DEV, OPERATION, ATTEMPTS, ID_PEP)
                    VALUES
                        (1, ' . $org_guid_sql . ', NULL, NULL, ' . $operation . ', 0, 1)';

            DB::query(Database::INSERT, $sql)
                ->execute(Database::instance('fb'));

            return true;
        } catch (Exception $e) {
            Kohana::$log->add(
                Log::ERROR,
                'parsecSyncOrg: не удалось создать задачу ' . $operation
                . ' для org_guid=' . $org_guid . ': ' . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Печатает итоговую статистику.
     */
    protected function _print_summary()
    {
        Minion_CLI::write('');
        Minion_CLI::write('=== ИТОГИ ===');
        Minion_CLI::write('Всего проверено:                 ' . $this->_stats['total']);
        Minion_CLI::write('Есть в Parsec:                   ' . $this->_stats['in_parsec']);
        Minion_CLI::write('  родитель и имя совпадают:      ' . $this->_stats['parent_ok']);
        Minion_CLI::write('  родитель расходится:           ' . $this->_stats['parent_diff']);
        Minion_CLI::write('  имя расходится:                ' . $this->_stats['name_diff']);
        Minion_CLI::write('Нет в Parsec:                    ' . $this->_stats['not_in_parsec']);
        Minion_CLI::write('Пропущено:                       ' . $this->_stats['skipped']);
        Minion_CLI::write('Ошибок SOAP/ответа:              ' . $this->_stats['errors']);
        Minion_CLI::write('Создано задач на добавление (5): ' . $this->_stats['tasks_add']);
        Minion_CLI::write('Создано задач на изменение (55): ' . $this->_stats['tasks_update']);
        Minion_CLI::write('Время выполнения:                ' . $this->_stats['time'] . ' сек');
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
                    'id_org=%s guid=%s : %s',
                    $err['id_org'],
                    $err['guid'],
                    $this->_utf8_to_cp1251($err['message'])
                ));
            }
            Minion_CLI::write('');
        }

        Minion_CLI::write('Готово.');
    }
}