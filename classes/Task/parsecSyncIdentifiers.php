<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Модуль сверки идентификаторов (карт) персон СКУД Артонит и СКУД Parsec.
 *
 * Алгоритм:
 *   1. Из Артонит берём персон (id_pep > 1, guid не пуст) и их активные
 *      RFID-карты (CARD.id_cardtype = 1, ACTIVE > 0).
 *   2. Для каждой персоны через SOAP Parsec:
 *        GetPerson(pep_guid)             — убедиться, что персона есть;
 *        GetPersonIdentifiers(pep_guid)  — получить список её карт в Parsec.
 *   3. Нормализуем оба списка к [CARD_CODE => true] (upper, без пробелов).
 *   4. Сравниваем множества:
 *        - карта есть в Артонит, но нет в Parsec > задача 9  (добавить)
 *        - карта есть в Parsec, но нет в Артонит > задача 10 (удалить)
 *        - совпадает > ничего не делаем.
 *   5. По окончании выводим статистику.
 *
 * Запуск:
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion --task=parsec_sync_identifiers
 *
 * Опции:
 *   --add         1|0   Ставить задачи интегратору (по умолчанию 0)
 *   --limit       N     Ограничить количество проверяемых персон (0 — без ограничений)
 *   --id_pep      N     Проверить только одну персону
 *   --verbose     1|0   Подробный вывод
 *
 * Формат лога — табличный, разделитель колонок "\t":
 *   счётчик | статус | ФИО | Артонит (карты) | Parsec (карты) | примечание
 *
 * @version 1.0.0
 * @date    2026-09-13
 */
class Task_parsecSyncIdentifiers extends Minion_Task
{
    /**
     * Опции задачи.
     */
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

    /** @var array */
    protected $_stats = array(
        'total'         => 0,
        'checked'       => 0,
        'not_in_parsec' => 0,
        'in_parsec'     => 0,
        'identical'     => 0,
        'mismatch'      => 0,
        'only_artonit'  => 0,
        'only_parsec'   => 0,
        'skipped'       => 0,
        'errors'        => 0,
        'tasks_add'     => 0,
        'tasks_del'     => 0,
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
        $id_pep  = (int) Arr::get($params, 'id_pep', 0);
        $verbose = (int) Arr::get($params, 'verbose', 1) === 1;

        Minion_CLI::write('=== Сверка идентификаторов персон СКУД Артонит и Parsec ===');
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

        // --- 4. Получение списка персон из Артонит с их картами ---
        $people = $this->_get_people_with_cards($id_pep, $limit);
        if (empty($people)) {
            Minion_CLI::write('Нет персон для проверки.');
            return;
        }

        $this->_stats['total'] = count($people);
        Minion_CLI::write('Получено персон для проверки: ' . count($people));
        Minion_CLI::write('');

        // --- 4.1. Шапка таблицы ---
        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                'Счётчик',
                'Статус',
                'ФИО',
                'Артонит (карты)',
                'Parsec (карты)',
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
     * Возвращает список персон Артонит с их активными RFID-картами.
     *
     * Формат: массив, где ключ — id_pep, значение — массив:
     *   [
     *       'id_pep'   => int,
     *       'pep_guid' => string,
     *       'fio'      => string,
     *       'cards'    => [ CARD_CODE => id_card, ... ]   // ключ — нормализованный код
     *   ]
     *
     * @param int $id_pep
     * @param int $limit
     * @return array
     */
    protected function _get_people_with_cards($id_pep = 0, $limit = 0)
    {
        $sql = 'SELECT p.id_pep,
                       p.guid              AS pep_guid,
                       p.surname,
                       p.name,
                       p.patronymic,
                       c.id_card,
                       c.id_cardtype,
                       c."ACTIVE"          AS card_active
                FROM people p
                LEFT JOIN card c
                       ON c.id_pep = p.id_pep
                      AND c.id_cardtype = 1
                      AND c."ACTIVE" > 0
                WHERE p.guid IS NOT NULL
                  AND p.id_pep > 1';

      

        // Ограничение на количество персон (не строк!)
        // Если указан limit, берём только первые N персон.
        if ($limit > 0) {
            $sql = 'SELECT first '.$limit.' p.id_pep,
                       p.guid              AS pep_guid,
                       p.surname,
                       p.name,
                       p.patronymic,
                       c.id_card,
                       c.id_cardtype,
                       c."ACTIVE"          AS card_active
                FROM people p
                LEFT JOIN card c
                       ON c.id_pep = p.id_pep
                      AND c.id_cardtype = 1
                      AND c."ACTIVE" > 0
                WHERE p.guid IS NOT NULL
                  AND p.id_pep > 1';
        }


	  if ($id_pep > 0) {
            $sql .= ' AND p.id_pep = ' . (int) $id_pep;
        }

        $sql .= ' ORDER BY p.id_pep, c.id_card';
		
//echo Debug::vars('291', $sql);exit;		
        try {
            $rows = DB::query(Database::SELECT, $sql)
                ->execute(Database::instance('fb'))
                ->as_array();
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL: ' . $e->getMessage());
            return array();
        }

        // Группируем карты по id_pep
        $result = array();
        foreach ($rows as $row) {
			
            $pid = (int) Arr::get($row, 'ID_PEP');

            if (!isset($result[$pid])) {
                $fio = trim(
                    Arr::get($row, 'SURNAME') . ' ' .
                    Arr::get($row, 'NAME') . ' ' .
                    Arr::get($row, 'PATRONYMIC')
                );

                $result[$pid] = array(
                    'id_pep'   => $pid,
                    'pep_guid' => strtoupper(trim((string) Arr::get($row, 'PEP_GUID'))),
                    'fio'      => $fio,
                    'cards'    => array(),
                );
            }

			$id_card = Arr::get($row, 'ID_CARD');
			if ($id_card !== null && $id_card !== '') {
				$code = $this->_decimal_to_hex_card($id_card);
				$result[$pid]['cards'][$code] = $id_card;  // ключ hex, значение — исходное dec
			}
        }
//echo Debug::vars('327', $result);exit;
        return array_values($result);
    }

    /**
     * Нормализация кода карты: upper-case, без пробелов, ведущие нули сохранены.
     *
     * @param string $card
     * @return string
     */
    protected function _normalize_card($card)
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $card));
    }

    /**
     * Проверяет одну персону.
     */
    protected function _check_person(array $row, $add, $verbose, $i, $total)
    {
        $id_pep   = $row['id_pep'];
        $pep_guid = $row['pep_guid'];
        $fio      = $row['fio'];
        $cards    = $row['cards'];   // [CODE => id_card]

        $prefix = sprintf('[%d/%d] id_pep=%s guid=%s', $i, $total, $id_pep, $pep_guid);

        // --- Проверка входных данных ---
        if ($pep_guid === '') {
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ПРОПУЩЕН', $fio, '', '', 'пустой GUID персоны'
                ));
            }
            return;
        }

        if (empty($cards)) {
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ПРОПУЩЕН', $fio, '(нет активных карт)', '', ''
                ));
            }
            return;
        }

        // --- Запрос в Parsec: GetPerson ---
        $response_person = $this->_cch_model->GetPerson($this->_session_id, $pep_guid);

        if (isset($response_person->error) && $response_person->error) {
            $this->_stats['errors']++;
            $message = isset($response_person->message) ? $response_person->message : 'неизвестная ошибка SOAP';
            $this->_errors[] = array(
                'id_pep'  => $id_pep,
                'guid'    => $pep_guid,
                'message' => 'GetPerson: ' . $message,
            );
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ОШИБКА SOAP', $fio, '', '',
                    $this->_utf8_to_cp1251($message)
                ));
            }
            return;
        }

        $person = isset($response_person->GetPersonResult)
            ? $response_person->GetPersonResult
            : null;

        if (is_array($person) && empty($person)) {
            $person = null;
        }

        if ($person === null) {
            // Персоны нет в Parsec — все её карты считаются отсутствующими
            $this->_stats['not_in_parsec']++;

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'НЕТ В PARSEC', $fio,
                    implode(', ', array_keys($cards)),
                    '',
                    ''
                ));
            }

            if ($add) {
                foreach ($cards as $code => $id_card) {
                    if ($this->_add_task_identifier($id_pep, $id_card, 9)) {
                        $this->_stats['tasks_add']++;
                    }
                }
            }
            return;
        }

        // --- Запрос в Parsec: GetPersonIdentifiers ---
        $response_ids = $this->_cch_model->GetPersonIdentifiers($this->_session_id, $pep_guid);
//echo Debug::vars('429', $response_ids);//exit;
        if (isset($response_ids->error) && $response_ids->error) {
            $this->_stats['errors']++;
            $message = isset($response_ids->message) ? $response_ids->message : 'неизвестная ошибка SOAP';
            $this->_errors[] = array(
                'id_pep'  => $id_pep,
                'guid'    => $pep_guid,
                'message' => 'GetPersonIdentifiers: ' . $message,
            );
            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'ОШИБКА SOAP', $fio, '', '',
                    $this->_utf8_to_cp1251($message)
                ));
            }
            return;
        }

        $this->_stats['in_parsec']++;

        // --- Разбор списка идентификаторов Parsec ---
        $parsec_cards = $this->_extract_parsec_cards($response_ids);

        // --- Сравнение множеств ---
//		echo Debug::vars('452',$row , $cards,        $parsec_cards);//exit;
        $only_artonit = array_diff_key($cards,        $parsec_cards);
        $only_parsec  = array_diff_key($parsec_cards, $cards);
        $same         = array_intersect_key($cards,   $parsec_cards);

        $artonit_list = implode(', ', array_keys($cards));
        $parsec_list  = implode(', ', array_keys($parsec_cards));

        // --- Полное совпадение ---
        if (empty($only_artonit) && empty($only_parsec)) {
            $this->_stats['identical']++;

            if ($verbose) {
                Minion_CLI::write($this->_log_row(
                    $prefix, 'OK', $fio, $artonit_list, $parsec_list, ''
                ));
            }
            return;
        }

        // --- Есть расхождения ---
        $this->_stats['mismatch']++;

        $notes = array();
        if (!empty($only_artonit)) {
            $this->_stats['only_artonit'] += count($only_artonit);
            $notes[] = '+ в Артонит: ' . implode(', ', array_keys($only_artonit));
        }
        if (!empty($only_parsec)) {
            $this->_stats['only_parsec'] += count($only_parsec);
            $notes[] = '- в Parsec: '  . implode(', ', array_keys($only_parsec));
        }

        if ($verbose) {
            Minion_CLI::write($this->_log_row(
                $prefix, 'РАСХОЖДЕНИЕ', $fio,
                $artonit_list,
                $parsec_list,
                implode('; ', $notes)
            ));
        }

        if ($add) {
            // Карты, которых нет в Parsec > задача 9 (добавить)
            foreach ($only_artonit as $code => $id_card) {
                if ($this->_add_task_identifier($id_pep, $id_card, 9)) {
                    $this->_stats['tasks_add']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix, '  -> задача 9', '', '', '',
                            'добавить карту ' . $code
                        ));
                    }
                }
            }

            // Карты, которых нет в Артонит > задача 10 (удалить)
            foreach ($only_parsec as $code => $data) {
                if ($this->_add_task_identifier($id_pep, $data['raw'], 10)) {
                    $this->_stats['tasks_del']++;
                    if ($verbose) {
                        Minion_CLI::write($this->_log_row(
                            $prefix, '  -> задача 10', '', '', '',
                            'удалить карту ' . $code
                        ));
                    }
                }
            }
        }
    }


/**
 * Извлекает список карт из ответа GetPersonIdentifiers.
 *
 * Поддерживает структуры:
 *   - GetPersonIdentifiersResult => [ (object){CODE=>...}, ... ]
 *   - GetPersonIdentifiersResult => (object){ Identifier => [ (object){CODE=>...}, ... ] }
 *   - другие обёртки: Identifiers, Items, List, Cards и т.п.
 *
 * @param object $response
 * @return array [ CARD_CODE => ['raw'=>..., 'type'=>..., 'primary'=>...], ... ]
 */
protected function _extract_parsec_cards($response)
{
    $result = array();

    if (!isset($response->GetPersonIdentifiersResult)) {
        return $result;
    }

    $root = $response->GetPersonIdentifiersResult;

    // Приводим корень к массиву
    if (is_object($root)) {
        $root = (array) $root;
    }

    if (!is_array($root)) {
        return $result;
    }

    // Типичные имена полей-обёрток, внутри которых лежит массив идентификаторов
    $wrapper_keys = array(
        'Identifier',    'Identifiers',
        'IDENTIFIER',    'IDENTIFIERS',
        'IdentifierList','IDENTIFIER_LIST',
        'Items',         'ITEMS',
        'List',          'LIST',
        'Cards',         'CARDS',
        'Card',          'CARD',
        'PersonIdentifiers', 'PERSON_IDENTIFIERS',
    );

    // 1) Ищем обёртку первого уровня
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

    // 2) Обёртки нет — значит корень и есть список карт
    if ($items === null) {
        $items = $root;
    }

    // 3) Нормализуем $items в плоский массив объектов-карт
    if (!is_array($items)) {
        $items = array($items);
    } else {
        // Если это одиночный объект-карта (а не список) — оборачиваем
        $first = reset($items);
        if (!is_object($first) && !is_array($first)) {
            $items = array($items);
        }
    }

    // 4) Извлекаем код из каждого объекта-карты
    foreach ($items as $item) {
        if (!is_object($item) && !is_array($item)) {
            continue;
        }

        $code = $this->_extract_card_code($item);
        if ($code === '') {
            continue;
        }

        $result[$code] = array(
            'raw'     => $code,
            'type'    => $this->_extract_prop($item, array(
                'IDENTIFTYPE', 'IDENTIFIER_TYPE', 'IdentifierType',
                'TYPE', 'Type'
            )),
            'primary' => $this->_extract_prop($item, array(
                'IS_PRIMARY', 'IsPrimary', 'PRIMARY'
            )),
        );
    }

    return $result;
}

    /**
     * Извлекает код карты из объекта/массива (ищет CODE, CardCode, ID и т.д.).
     *
     * @param mixed $item
     * @return string
     */
    protected function _extract_card_code($item)
    {
        $value = $this->_extract_prop($item, array(
            'CODE', 'Code', 'code',
            'CARD_CODE', 'CardCode', 'cardCode',
            'IDENTIFIER', 'Identifier',
            'ID', 'Id', 'id',
        ));

        if ($value === null || $value === '') {
            return '';
        }

        return $this->_normalize_card((string) $value);
    }

    /**
     * Возвращает значение первого найденного свойства из списка ключей.
     *
     * @param mixed $item
     * @param array $keys
     * @return mixed
     */
    protected function _extract_prop($item, array $keys)
    {
        if (is_object($item)) {
            $item = (array) $item;
        }

        if (!is_array($item)) {
            return null;
        }

        foreach ($keys as $key) {
            if (isset($item[$key]) && is_scalar($item[$key])) {
                return $item[$key];
            }
        }

        return null;
    }

    /**
     * Ставит задачу интегратору на добавление (9) или удаление (10) идентификатора.
     *
     * @param int   $id_pep
     * @param mixed $id_card  Номер карты (id_card) или её raw-код
     * @param int   $operation 9 или 10
     * @return bool
     */
    protected function _add_task_identifier($id_pep, $id_card, $operation)
    {
        $id_pep    = (int) $id_pep;
        $operation = (int) $operation;

        if (!in_array($operation, array(9, 10), true)) {
            return false;
        }

        // Firebird: id_card — числовое? В CARD поле id_card, скорее всего, integer.
        // Приводим к int, если это число; иначе оставляем как есть.
        if (is_numeric($id_card)) {
            $id_card_sql = (int) $id_card;
        } else {
            $id_card_sql = "'" . str_replace("'", "''", (string) $id_card) . "'";
        }

        try {
            // Проверяем, нет ли уже такой задачи в очереди
            $check_sql = 'SELECT COUNT(*) AS CNT FROM cardindev cd
                          WHERE cd.operation = ' . $operation . '
                            AND cd.id_pep   = ' . $id_pep . '
                            AND cd.id_card  = ' . $id_card_sql;

            $count = DB::query(Database::SELECT, $check_sql)
                ->execute(Database::instance('fb'))
                ->get('CNT');

            if ((int) $count > 0) {
                return false;
            }

            $sql = 'INSERT INTO CARDINDEV
                        (ID_DB, ID_CARD, DEVIDX, ID_DEV, OPERATION, ATTEMPTS, ID_PEP)
                    VALUES
                        (1, ' . $id_card_sql . ', NULL, NULL, ' . $operation . ', 0, ' . $id_pep . ')';

            DB::query(Database::INSERT, $sql)
                ->execute(Database::instance('fb'));

            return true;
        } catch (Exception $e) {
            Kohana::$log->add(
                Log::ERROR,
                'parsec_sync_identifiers: не удалось создать задачу ' . $operation
                . ' для id_pep=' . $id_pep . ', id_card=' . $id_card
                . ': ' . $e->getMessage()
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
        Minion_CLI::write('Всего проверено:              ' . $this->_stats['total']);
        Minion_CLI::write('Есть в Parsec:                ' . $this->_stats['in_parsec']);
        Minion_CLI::write('  идентификаторы совпадают:   ' . $this->_stats['identical']);
        Minion_CLI::write('  есть расхождения:           ' . $this->_stats['mismatch']);
        Minion_CLI::write('    карт только в Артонит:    ' . $this->_stats['only_artonit']);
        Minion_CLI::write('    карт только в Parsec:     ' . $this->_stats['only_parsec']);
        Minion_CLI::write('Нет в Parsec:                 ' . $this->_stats['not_in_parsec']);
        Minion_CLI::write('Пропущено:                    ' . $this->_stats['skipped']);
        Minion_CLI::write('Ошибок SOAP/ответа:           ' . $this->_stats['errors']);
        Minion_CLI::write('Создано задач на добавление:  ' . $this->_stats['tasks_add']);
        Minion_CLI::write('Создано задач на удаление:    ' . $this->_stats['tasks_del']);
        Minion_CLI::write('Время выполнения:             ' . $this->_stats['time'] . ' сек');
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
	
	
	/**
 * Преобразует десятичный номер карты (Артонит) в hex-строку формата Parsec.
 *
 * Формат Parsec: строка ровно из 8 hex-символов, upper-case, с ведущими нулями.
 * Пример: 7419840 (dec) > "00713490".
 *
 * @param mixed $id_card Десятичный номер карты из CARD.id_card
 * @return string 8-символьная hex-строка или '' при ошибке
 */
protected function _decimal_to_hex_card($id_card)
{
    $dec = trim((string) $id_card);

    if ($dec === '') {
        return '';
    }

    // Если пришло не число — это уже не dec-формат, значит ошибка данных.
    // Возвращаем '' — вызывающий код должен обработать.
    if (!ctype_digit($dec)) {
        return '';
    }

    // dec > hex. Для значений, не влезающих в PHP_INT_MAX, используем GMP.
    if (function_exists('gmp_init')) {
        $hex = gmp_strval(gmp_init($dec, 10), 16);
    } else {
        $int = (int) $dec;
        if ((string) $int !== ltrim($dec, '0') && ltrim($dec, '0') !== '') {
            // Переполнение int — не должны сюда попадать для реальных карт
            return '';
        }
        $hex = dechex($int);
    }

    $hex = strtoupper($hex);

    // Parsec ожидает ровно 8 символов
    if (strlen($hex) > 8) {
        // Карта не влезает в формат Parsec — логируем и возвращаем ''
        Kohana::$log->add(
            Log::ERROR,
            'parsec_sync_identifiers: id_card=' . $dec
            . ' даёт hex=' . $hex . ' длиной ' . strlen($hex)
            . ' (ожидается ? 8 символов)'
        );
        return '';
    }

    return str_pad($hex, 8, '0', STR_PAD_LEFT);
}
}
