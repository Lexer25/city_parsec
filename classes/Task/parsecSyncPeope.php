<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модуль сверки персон СКУД Артонит с СКУД Parsec.
 *
 * Алгоритм:
 *   1. Берём список персон из Артонит (id_pep, guid, id_org, guid организации).
 *   2. Для каждой персоны через SOAP Parsec получаем GetPerson по GUID.
 *   3. Если персоны нет в Parsec — ставим задачу 3 (добавление пользователя).
 *   4. Если персона есть — сравниваем GUID организации в Parsec с GUID организации
 *      из Артонит. При расхождении ставим задачу 35 (изменение данных пользователя).
 *   5. По окончании выводим статистику.
 *
 * Запуск:
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion --task=parsecSyncPeope
 *
 * Опции:
 *   --add         1|0   Ставить задачи (по умолчанию 0)
 *   --limit       N     Ограничить количество проверяемых персон (0 — без ограничений)
 *   --id_pep      N     Проверить только одну персону
 *   --verbose     1|0   Подробный вывод
 *
 * Формат лога — табличный, разделитель колонок "\t":
 *   счётчик | статус | ФИО | Artonit | Parsec | примечание
 *
 * @version 1.2.0
 * @date    2026-09-13
 */
class Task_parsecSyncPeope extends Minion_Task
{
    /**
     * Опции задачи.
     * Ключи без ведущих дефисов — так принято в Minion.
     */
    protected $_options = array(
        'add'     => 0,     // ставить задачи интегратору
        'limit'   => 0,     // 0 — без ограничения
        'id_pep'  => 0,     // 0 — все персоны
        'verbose' => 1,     // подробный вывод
    );

    /** @var Model_Cch */
    protected $_cch_model = null;

    /** @var string */
    protected $_session_id = null;

    /** @var array Кэш названий организаций Parsec: guid => name (уже в CP1251) */
    protected $_org_name_cache = array();

    /** @var array */
    protected $_stats = array(
        'total'        => 0,
        'in_parsec'    => 0,
        'not_in_parsec'=> 0,
        'org_mismatch' => 0,
        'org_match'    => 0,
        'skipped'      => 0,
        'errors'       => 0,
        'tasks_added'  => 0,
        'time'         => 0,
    );

    /** @var array */
    protected $_errors = array();

    // ---------------------------------------------------------------------
    // Точка входа
    // ---------------------------------------------------------------------

    protected function _execute(array $params)
    {
        $start_time = microtime(true);

        $add     = (int) Arr::get($params, 'add', 1) === 1;
        $limit   = (int) Arr::get($params, 'limit', 0);
        $id_pep  = (int) Arr::get($params, 'id_pep', 0);
        $verbose = (int) Arr::get($params, 'verbose', 1) === 1;

        Minion_CLI::write('=== Сверка персон СКУД Артонит и Parsec ===');
        Minion_CLI::write('Режим добавления задач: ' . ($add ? 'ДА' : 'НЕТ'));
        if ($limit > 0) {
            Minion_CLI::write('Ограничение: ' . $limit . ' персон');
        }
        if ($id_pep > 0) {
            Minion_CLI::write('Проверка только id_pep=' . $id_pep);
        }
        Minion_CLI::write('');

        // --- 1. Инициализация модели CCH ---
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

        // --- 3. Открытие сессии Parsec ---
        if (!$this->_open_session()) {
            return;
        }

        // --- 4. Получение списка персон из Артонит ---
        $people = $this->_get_people_list($id_pep, $limit);
        if (empty($people)) {
            Minion_CLI::write('Нет персон для проверки.');
            return;
        }

        $this->_stats['total'] = count($people);
        Minion_CLI::write('Получено персон для проверки: ' . count($people));
        Minion_CLI::write('');

        // --- 4.1. Шапка таблицы (только в verbose-режиме) ---
        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                'Счётчик',
                'Статус',
                'ФИО',
                'Artonit',
                'Parsec',
                'Примечание'
            ));
            Minion_CLI::write(str_repeat('=', 160));
        }

        // --- 5. Основной цикл ---
        $i = 0;
        foreach ($people as $row) {
            $i++;
            $this->_check_person($row, $add, $verbose, $i, count($people));
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
     * Колонки: счётчик | статус | ФИО | Artonit | Parsec | примечание.
     *
     * @param string $prefix  [i/total] id_pep=N
     * @param string $status  РАСХОЖДЕНИЕ | OK | НЕТ В PARSEC | ПРОПУЩЕН | ОШИБКА
     * @param string $fio
     * @param string $artonit
     * @param string $parsec
     * @param string $note
     * @return string
     */
    protected function _log_row($prefix, $status, $fio = '', $artonit = '', $parsec = '', $note = '')
    {
        return $prefix  . "\t"
             . $status  . "\t"
             . $fio     . "\t"
             . $artonit . "\t"
             . $parsec  . "\t"
             . $note;
    }

    /**
     * Приводит строку из UTF-8 к CP1251 (для вывода в лог).
     * Если строка не является валидным UTF-8 — возвращает её как есть.
     *
     * @param string $str
     * @return string
     */
    protected function _utf8_to_cp1251($str)
    {
        $str = (string) $str;

        if ($str === '') {
            return '';
        }

        // Если строка НЕ валидный UTF-8 — считаем, что она уже в CP1251
        if (function_exists('mb_check_encoding') && !mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }

        $converted = @iconv('UTF-8', 'CP1251//TRANSLIT//IGNORE', $str);
        return ($converted === false) ? $str : $converted;
    }

    /**
     * Открывает сессию Parsec.
     *
     * @return bool
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

        // Проверяем SOAP-ответ
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
     * Возвращает список персон из Артонит.
     *
     * @param int $id_pep 0 — все
     * @param int $limit  0 — без ограничения
     * @return array
     */
    protected function _get_people_list($id_pep = 0, $limit = 0)
    {
        $sql = 'SELECT p.id_pep,
                       p.guid              AS pep_guid,
                       p.surname,
                       p.name,
                       p.patronymic,
                       p.id_org,
                       o.guid              AS org_guid,
                       o.name              AS org_name
                FROM people p
                LEFT JOIN organization o ON o.id_org = p.id_org
                WHERE p.guid IS NOT NULL
                  AND p.id_pep > 1';

        if ($id_pep > 0) {
            $sql .= ' AND p.id_pep = ' . (int) $id_pep;
        }

        $sql .= ' ORDER BY p.id_pep';

        if ($limit > 0) {
            // Firebird: FIRST n — ставим сразу после SELECT
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
     * Проверяет одну персону.
     *
     * @param array $row       строка из БД
     * @param bool  $add       ставить ли задачи
     * @param bool  $verbose   подробный вывод
     * @param int   $i         текущий номер
     * @param int   $total     всего
     */
    protected function _check_person(array $row, $add, $verbose, $i, $total)
    {
        $id_pep    = Arr::get($row, 'ID_PEP');
        $pep_guid  = strtoupper(trim((string) Arr::get($row, 'PEP_GUID')));
        $org_guid  = strtoupper(trim((string) Arr::get($row, 'ORG_GUID')));
        $fio       = trim(
            Arr::get($row, 'SURNAME') . ' ' .
            Arr::get($row, 'NAME') . ' ' .
            Arr::get($row, 'PATRONYMIC')
        );

        // Firebird отдаёт WIN1251, файл .php в CP1251, лог в CP1251.
        // Никаких перекодировок не требуется.
        $org_name = (string) Arr::get($row, 'ORG_NAME');

        $prefix = sprintf('[%d/%d] id_pep=%s', $i, $total, $id_pep);

        // --- Проверки входных данных ---
        if ($pep_guid === '') {
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'ПРОПУЩЕН',
                    $fio,
                    '',
                    '',
                    'пустой GUID персоны'
                ));
            }
            return;
        }

        // --- Запрос в Parsec ---
        $response = $this->_cch_model->GetPerson($this->_session_id, $pep_guid);

        // --- Ошибка SOAP ---
        if (isset($response->error) && $response->error) {
            $this->_stats['errors']++;
            $message = isset($response->message) ? $response->message : 'неизвестная ошибка SOAP';
            $this->_errors[] = array('id_pep' => $id_pep, 'guid' => $pep_guid, 'message' => $message);

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'ОШИБКА SOAP',
                    $fio,
                    '',
                    '',
                    $this->_utf8_to_cp1251($message)
                ));
            }
            return;
        }

        // --- Разбор ответа ---
        $person = isset($response->GetPersonResult) ? $response->GetPersonResult : null;

        // В mock-режиме может прийти объект или массив
        if (is_array($person) && empty($person)) {
            $person = null;
        }

        // --- Персоны нет в Parsec ---
        if ($person === null) {
            $this->_stats['not_in_parsec']++;

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'НЕТ В PARSEC',
                    $fio,
                    '',
                    '',
                    ''
                ));
            }

            if ($add) {
                if ($this->_add_task_person($id_pep, 3)) {
                    $this->_stats['tasks_added']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix,
                            '  -> задача',
                            '',
                            '',
                            '',
                            'создана задача 3 (добавить пользователя)'
                        ));
                    }
                }
            }
            return;
        }

        // --- Персона есть в Parsec ---
        $this->_stats['in_parsec']++;

        // GUID организации персоны в Parsec
        $parsec_org_guid = '';
        if (isset($person->ORG_ID)) {
            $parsec_org_guid = strtoupper(trim((string) $person->ORG_ID));
        }

        // --- Сравнение организаций ---
        if ($org_guid === '') {
            // У персоны в Артонит нет организации — не можем сравнить
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'ПРОПУЩЕН',
                    $fio,
                    '',
                    '',
                    'нет GUID организации в Артонит'
                ));
            }
            return;
        }

        if ($parsec_org_guid === '') {
            // В Parsec не удалось получить GUID организации
            $this->_stats['errors']++;
            $this->_errors[] = array(
                'id_pep'  => $id_pep,
                'guid'    => $pep_guid,
                'message' => 'В ответе GetPerson нет ORG_ID',
            );
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'ОШИБКА',
                    $fio,
                    'Artonit: ' . $org_guid . ' (' . $org_name . ')',
                    '',
                    'в ответе GetPerson нет ORG_ID'
                ));
            }
            return;
        }

        if ($parsec_org_guid !== $org_guid) {
            // --- Организации не совпадают ---
            $this->_stats['org_mismatch']++;

            // Получаем название организации в Parsec (с кэшем).
            // _get_parsec_org_name уже возвращает строку в CP1251.
            $parsec_org_name = $this->_get_parsec_org_name($parsec_org_guid);
            if ($parsec_org_name === '') {
                $parsec_org_name = '?';
            }

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'РАСХОЖДЕНИЕ',
                    $fio,
                    'Artonit: ' . $org_guid . ' (' . $org_name . ')',
                    'Parsec: '  . $parsec_org_guid . ' (' . $parsec_org_name . ')',
                    ''
                ));
            }

            if ($add) {
                if ($this->_add_task_person($id_pep, 35)) {
                    $this->_stats['tasks_added']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix,
                            '  -> задача',
                            '',
                            '',
                            '',
                            'создана задача 35 (изменить данные пользователя)'
                        ));
                    }
                }
            }
        } else {
            // --- Всё совпадает ---
            $this->_stats['org_match']++;

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix,
                    'OK',
                    $fio,
                    'Artonit: ' . $org_guid . ' (' . $org_name . ')',
                    'Parsec: '  . $parsec_org_guid . ' (' . $org_name . ')',
                    ''
                ));
            }
        }
    }

    /**
     * Получает название организации в Parsec по её GUID.
     * Результат кэшируется в памяти на время работы задачи.
     * Название возвращается уже в CP1251 (Parsec отдаёт UTF-8).
     *
     * @param string $org_guid GUID организации в Parsec
     * @return string Название или '' если не удалось получить
     */
    protected function _get_parsec_org_name($org_guid)
    {
        $org_guid = trim((string) $org_guid);

        if ($org_guid === '' || empty($this->_session_id)) {
            return '';
        }

        // Кэш
        if (array_key_exists($org_guid, $this->_org_name_cache)) {
            return $this->_org_name_cache[$org_guid];
        }

        $response = $this->_cch_model->GetOrgUnit($this->_session_id, $org_guid);

        $name = '';

        // Проверяем ошибку
        if (!isset($response->error) || !$response->error) {
            if (isset($response->GetOrgUnitResult) && $response->GetOrgUnitResult !== null) {
                $org = $response->GetOrgUnitResult;

                // Пробуем стандартные варианты
                if (is_object($org)) {
                    if (isset($org->NAME)) {
                        $name = (string) $org->NAME;
                    } elseif (isset($org->Name)) {
                        $name = (string) $org->Name;
                    } elseif (isset($org->OrgUnit->NAME)) {
                        $name = (string) $org->OrgUnit->NAME;
                    } elseif (isset($org->OrgUnit->Name)) {
                        $name = (string) $org->OrgUnit->Name;
                    }
                } elseif (is_array($org)) {
                    if (isset($org['NAME'])) {
                        $name = (string) $org['NAME'];
                    } elseif (isset($org['Name'])) {
                        $name = (string) $org['Name'];
                    }
                }

                // Фолбэк — рекурсивный поиск поля NAME
                if ($name === '') {
                    $name = $this->_find_name_recursive($org);
                }
            }
        }

        $name = trim($name);

        // Parsec отдаёт UTF-8, лог в CP1251 — приводим к CP1251
        if ($name !== '') {
            $name = $this->_utf8_to_cp1251($name);
        }

        $this->_org_name_cache[$org_guid] = $name;

        return $name;
    }

    /**
     * Рекурсивно ищет в объекте/массиве поле NAME.
     *
     * @param mixed $data
     * @param int   $depth
     * @return string
     */
    protected function _find_name_recursive($data, $depth = 0)
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

        // Приоритетные ключи
        foreach (array('NAME', 'Name', 'name') as $key) {
            if (isset($data[$key]) && is_scalar($data[$key]) && $data[$key] !== '') {
                return (string) $data[$key];
            }
        }

        // Вложенные объекты
        foreach ($data as $value) {
            if (is_object($value) || is_array($value)) {
                $found = $this->_find_name_recursive($value, $depth + 1);
                if ($found !== '') {
                    return $found;
                }
            }
        }

        return '';
    }

    /**
     * Ставит задачу интегратору на добавление (3) или изменение (35) персоны.
     *
     * @param int $id_pep
     * @param int $operation 3 или 35
     * @return bool
     */
    protected function _add_task_person($id_pep, $operation)
    {
        $id_pep    = (int) $id_pep;
        $operation = (int) $operation;

        if (!in_array($operation, array(3, 35), true)) {
            return false;
        }

        try {
            // Проверяем, нет ли уже такой задачи в очереди
            $check_sql = 'SELECT COUNT(*) AS CNT FROM cardindev cd
                          WHERE cd.operation = ' . $operation . '
                            AND cd.id_pep = ' . $id_pep;

            $count = DB::query(Database::SELECT, $check_sql)
                ->execute(Database::instance('fb'))
                ->get('CNT');

            if ((int) $count > 0) {
                // Задача уже есть — не дублируем
                return false;
            }

            $sql = 'INSERT INTO CARDINDEV
                        (ID_DB, ID_CARD, DEVIDX, ID_DEV, OPERATION, ATTEMPTS, ID_PEP)
                    VALUES
                        (1, NULL, NULL, NULL, ' . $operation . ', 0, ' . $id_pep . ')';

            DB::query(Database::INSERT, $sql)
                ->execute(Database::instance('fb'));

            return true;
        } catch (Exception $e) {
            Kohana::$log->add(
                Log::ERROR,
                'parsec_sync_peope: не удалось создать задачу ' . $operation
                . ' для id_pep=' . $id_pep . ': ' . $e->getMessage()
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
        Minion_CLI::write('Всего проверено:            ' . $this->_stats['total']);
        Minion_CLI::write('Есть в Parsec:              ' . $this->_stats['in_parsec']);
        Minion_CLI::write('  из них org совпадает:    ' . $this->_stats['org_match']);
        Minion_CLI::write('  из них org расходится:   ' . $this->_stats['org_mismatch']);
        Minion_CLI::write('Нет в Parsec:               ' . $this->_stats['not_in_parsec']);
        Minion_CLI::write('Пропущено:                  ' . $this->_stats['skipped']);
        Minion_CLI::write('Ошибок SOAP/ответа:         ' . $this->_stats['errors']);
        Minion_CLI::write('Создано задач интегратору:  ' . $this->_stats['tasks_added']);
        Minion_CLI::write('Время выполнения:           ' . $this->_stats['time'] . ' сек');
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
                    'id_pep=%s guid=%s : %s',
                    $err['id_pep'],
                    $err['guid'],
                    $this->_utf8_to_cp1251($err['message'])
                ));
            }
            Minion_CLI::write('');
        }

        Minion_CLI::write('Готово.');
    }
}