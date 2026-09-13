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
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion --task=parsec_sync_peope
 *
 * Опции:
 *   --add         1|0   Ставить задачи (по умолчанию 1)
 *   --limit       N     Ограничить количество проверяемых персон (0 — без ограничений)
 *   --id_pep      N     Проверить только одну персону
 *   --verbose     1|0   Подробный вывод
 *
 * @version 1.0.0
 * @date    2026-09-12
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

        // --- 5. Основной цикл ---
        $i = 0;
        foreach ($people as $row) {
            $i++;
			//echo Debug::vars('126', $row, $add, $verbose, $i, count($people)); exit;
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
        $sql = 'SELECT first 100 p.id_pep,
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
				and p.id_pep>1';

        if ($id_pep > 0) {
            $sql .= ' AND p.id_pep = ' . (int) $id_pep;
        }

        $sql .= ' ORDER BY p.id_pep';

        if ($limit > 0) {
            // Firebird: FIRST n
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
      // echo Debug::vars('235', $row); //exit;
		
		$id_pep    = Arr::get($row, 'ID_PEP');
        $pep_guid  = strtoupper(trim((string) Arr::get($row, 'PEP_GUID')));
        $org_guid  = strtoupper(trim((string) Arr::get($row, 'ORG_GUID')));
        $fio       = trim(
            Arr::get($row, 'SURNAME') . ' ' .
            Arr::get($row, 'NAME') . ' ' .
            Arr::get($row, 'PATRONYMIC')
        );

        // Кодировка из Firebird WIN1251 -> UTF-8
        $fio = $this->_to_utf8($fio);
        $org_name = $this->_to_utf8((string) Arr::get($row, 'ORG_NAME'));

        $prefix = sprintf('[%d/%d] id_pep=%s', $i, $total, $id_pep);

        // --- Проверки входных данных ---
        if ($pep_guid === '') {
            $this->_stats['skipped']++;
            if ($verbose) {
                Minion_CLI::write($prefix . ' — ПРОПУЩЕН: пустой GUID персоны');
            }
            return;
        }

        // --- Запрос в Parsec ---
				 // echo Debug::vars('263', $this->_session_id, $pep_guid); //exit;
        $response = $this->_cch_model->GetPerson($this->_session_id, $pep_guid);
		//	echo Debug::vars('265', $response); //exit;
        // --- Ошибка SOAP ---
        if (isset($response->error) && $response->error) {
            $this->_stats['errors']++;
            $message = isset($response->message) ? $response->message : 'неизвестная ошибка SOAP';
            $this->_errors[] = array('id_pep' => $id_pep, 'guid' => $pep_guid, 'message' => $message);

            if ($verbose) {
                Minion_CLI::write($prefix . ' — 273 ОШИБКА SOAP: ' . $message);
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
                Minion_CLI::write($prefix . ' — 291 НЕТ В PARSEC (' . $fio . ')');
            }

            if ($add) {
                if ($this->_add_task_person($id_pep, 3)) {
                    $this->_stats['tasks_added']++;
                    if ($verbose) {
                        Minion_CLI::write('            -> создана задача 3 (добавить пользователя)');
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
                Minion_CLI::write($prefix . ' — ПРОПУЩЕН: у персоны нет GUID организации в Артонит');
            }
            return;
        }

        if ($parsec_org_guid === '') {
            // В Parsec не удалось получить GUID организации
            $this->_stats['errors']++;
            $this->_errors[] = array(
                'id_pep'  => $id_pep,
                'guid'    => $pep_guid,
                'message' => 'В ответе GetPerson нет ORG_UNIT_ID',
            );
            if ($verbose) {
                Minion_CLI::write($prefix . ' — 357 ОШИБКА: в ответе Parsec нет ORG_UNIT_ID');
            }
            return;
        }

        if ($parsec_org_guid !== $org_guid) {
            // --- Организации не совпадают ---
            $this->_stats['org_mismatch']++;

            if ($verbose) {
                Minion_CLI::write($prefix . ' — РАСХОЖДЕНИЕ ОРГАНИЗАЦИИ (' . $fio . ')');
                Minion_CLI::write('            Artonit org_guid: ' . $org_guid . ' (' . $org_name . ')');
                Minion_CLI::write('            Parsec  org_guid: ' . $parsec_org_guid);
            }

            if ($add) {
                if ($this->_add_task_person($id_pep, 35)) {
                    $this->_stats['tasks_added']++;
                    if ($verbose) {
                        Minion_CLI::write('            -> создана задача 35 (изменить данные пользователя)');
                    }
                }
            }
        } else {
            // --- Всё совпадает ---
            $this->_stats['org_match']++;

            if ($verbose) {
                Minion_CLI::write($prefix . ' — OK (' . $fio . ', org=' . $org_name . ')');
            }
        }
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
                    $err['message']
                ));
            }
            Minion_CLI::write('');
        }

        Minion_CLI::write('Готово.');
    }

    /**
     * Преобразование строки из WIN1251 в UTF-8 (для вывода в консоль).
     *
     * @param string $str
     * @return string
     */
    protected function _to_utf8($str)
    {
        if ($str === null || $str === '') {
            return '';
        }

        // Пробуем определить: если строка валидный UTF-8 — оставляем как есть
        if (function_exists('mb_check_encoding') && mb_check_encoding($str, 'UTF-8')) {
            return $str;
        }

        $converted = @iconv('windows-1251', 'UTF-8//IGNORE', $str);
        return ($converted === false) ? $str : $converted;
    }
}
