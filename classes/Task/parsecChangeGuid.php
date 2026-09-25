<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Задача смены GUID персоны в СКУД Артонит.
 *
 * Алгоритм:
 *   1. Находим персону по id_pep в таблице PEOPLE.
 *   2. Читаем её текущий GUID.
 *   3. Проверяем, что GUID начинается с шаблона FROM_PREFIX.
 *   4. Заменяем префикс на TO_PREFIX, хвост GUID оставляем как есть.
 *   5. Проверяем, что нового GUID ещё нет ни в PEOPLE, ни в ORGANIZATION.
 *   6. UPDATE PEOPLE SET GUID = <новый> WHERE ID_PEP = <id_pep>.
 *   7. Печатаем отчёт.
 *
 * Запуск:
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion ^
 *       --task=parsecChangeGuid --id_pep=1378
 *
 * Опции:
 *   --id_pep   N        ID персоны в Артонит (обязательно)
 *   --dry_run  1|0      Только показать, что будет сделано (по умолчанию 0)
 *   --verbose  1|0      Подробный вывод (по умолчанию 1)
 *
 * @version 1.0.0
 * @date    2026-09-24
 */
class Task_parsecChangeGuid extends Minion_Task
{
    /**
     * Префикс GUID, который ищем.
     * В нижнем регистре, как в базе.
     */
    const FROM_PREFIX = '441dc23b-1111-44d2-a999-';

    /**
     * Префикс GUID, на который заменяем.
     */
    const TO_PREFIX   = '441dc23b-1112-44d2-a999-';

    protected $_options = array(
        'id_pep'   => 0,
        'dry_run'  => 0,
        'verbose'  => 1,
    );

    /** @var Database */
    protected $_db;

    // ---------------------------------------------------------------------
    // Точка входа
    // ---------------------------------------------------------------------

    protected function _execute(array $params)
    {
        $id_pep  = (int) Arr::get($params, 'id_pep', 0);
        $dry_run = (int) Arr::get($params, 'dry_run', 0) === 1;
        $verbose = (int) Arr::get($params, 'verbose', 1) === 1;

        Minion_CLI::write('=== Смена GUID персоны (parsecChangeGuid) ===');
        Minion_CLI::write('FROM-префикс: ' . self::FROM_PREFIX);
        Minion_CLI::write('TO-префикс:   ' . self::TO_PREFIX);
        Minion_CLI::write('Режим:        ' . ($dry_run ? 'DRY-RUN (без записи в БД)' : 'обновление GUID'));
        Minion_CLI::write('');

        if ($id_pep <= 0) {
            Minion_CLI::write('ОШИБКА: не указан id_pep. Используйте --id_pep=N');
            return;
        }

        $this->_db = Database::instance('fb');

        // --- 1. Найти персону ---
        $person = $this->_find_person($id_pep);
        if ($person === null) {
            Minion_CLI::write('ОШИБКА: персона с id_pep=' . $id_pep . ' не найдена.');
            return;
        }

        $old_guid = strtoupper(trim((string) Arr::get($person, 'GUID')));
        $fio      = trim(
            Arr::get($person, 'SURNAME') . ' ' .
            Arr::get($person, 'NAME') . ' ' .
            Arr::get($person, 'PATRONYMIC')
        );

        Minion_CLI::write('Найдена персона:');
        Minion_CLI::write('  id_pep:      ' . $id_pep);
        Minion_CLI::write('  ФИО:         ' . $fio);
        Minion_CLI::write('  Текущий GUID: ' . $old_guid);
        Minion_CLI::write('');

        // --- 2. Проверить, что GUID начинается с нужного префикса ---
        if ($old_guid === '') {
            Minion_CLI::write('ОШИБКА: у персоны пустой GUID — менять нечего.');
            return;
        }

        if (stripos($old_guid, self::FROM_PREFIX) !== 0) {
            Minion_CLI::write('ОШИБКА: GUID не начинается с "' . strtoupper(self::FROM_PREFIX) . '".');
            Minion_CLI::write('  Фактический GUID: ' . $old_guid);
            return;
        }

        // --- 3. Сформировать новый GUID ---
        $tail     = substr($old_guid, strlen(self::FROM_PREFIX)); // 12 символов
        $new_guid = strtoupper(self::TO_PREFIX) . $tail;

        Minion_CLI::write('Преобразование:');
        Minion_CLI::write('  Было: ' . $old_guid);
        Minion_CLI::write('  Стало: ' . $new_guid);
        Minion_CLI::write('');

        // --- 4. Проверить, что нового GUID ещё нет ---
        if ($this->_guid_exists($new_guid)) {
            Minion_CLI::write('ОШИБКА: новый GUID уже занят в БД Артонит.');
            Minion_CLI::write('  Занят в: ' . $this->_guid_owner($new_guid));
            return;
        }

        // --- 5. Обновить ---
        if ($dry_run) {
            Minion_CLI::write('DRY-RUN: UPDATE не выполняется.');
            Minion_CLI::write('');
            Minion_CLI::write('Готово.');
            return;
        }

        $sql = 'UPDATE PEOPLE SET GUID = ' . $this->_str($new_guid)
             . ' WHERE ID_PEP = ' . (int) $id_pep;

        try {
            DB::query(Database::UPDATE, $sql)->execute($this->_db);
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL при UPDATE: ' . $e->getMessage());
            return;
        }

        Minion_CLI::write('OK: GUID персоны обновлён.');

        // --- 6. Проверить, что записалось ---
        $check = $this->_find_person($id_pep);
        $check_guid = strtoupper(trim((string) Arr::get($check, 'GUID')));
        Minion_CLI::write('Проверка после UPDATE: GUID=' . $check_guid);

        if ($check_guid !== $new_guid) {
            Minion_CLI::write('ВНИМАНИЕ: GUID в БД не совпадает с ожидаемым!');
        } else {
            Minion_CLI::write('OK: значения совпадают.');
        }

        Minion_CLI::write('');
        Minion_CLI::write('Готово.');
    }

    // ---------------------------------------------------------------------
    // Вспомогательные методы
    // ---------------------------------------------------------------------

    /**
     * Найти персону по id_pep.
     *
     * @param int $id_pep
     * @return array|null
     */
    protected function _find_person($id_pep)
    {
        $sql = 'SELECT p.id_pep,
                       p.guid,
                       p.surname,
                       p.name,
                       p.patronymic
                FROM people p
                WHERE p.id_pep = ' . (int) $id_pep;

        try {
            $rows = DB::query(Database::SELECT, $sql)
                ->execute($this->_db)
                ->as_array();

            return isset($rows[0]) ? $rows[0] : null;
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL при поиске персоны: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Проверить, есть ли GUID в PEOPLE или ORGANIZATION.
     *
     * @param string $guid
     * @return bool
     */
    protected function _guid_exists($guid)
    {
        $guid_sql = $this->_str($guid);

        try {
            $sql = 'SELECT COUNT(*) AS CNT FROM people p WHERE p.guid = ' . $guid_sql;
            $cnt = (int) DB::query(Database::SELECT, $sql)->execute($this->_db)->get('CNT');
            if ($cnt > 0) {
                return true;
            }

            $sql = 'SELECT COUNT(*) AS CNT FROM organization o WHERE o.guid = ' . $guid_sql;
            $cnt = (int) DB::query(Database::SELECT, $sql)->execute($this->_db)->get('CNT');
            if ($cnt > 0) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            Minion_CLI::write('ОШИБКА SQL при проверке GUID: ' . $e->getMessage());
            // На всякий случай считаем, что GUID существует — чтобы не обновлять
            return true;
        }
    }

    /**
     * Определить, где именно занят GUID.
     *
     * @param string $guid
     * @return string
     */
    protected function _guid_owner($guid)
    {
        $guid_sql = $this->_str($guid);
        $owners   = array();

        try {
            $sql = 'SELECT id_pep FROM people p WHERE p.guid = ' . $guid_sql;
            $id_pep = DB::query(Database::SELECT, $sql)->execute($this->_db)->get('ID_PEP');
            if ($id_pep !== null && $id_pep !== false) {
                $owners[] = 'PEOPLE.id_pep=' . $id_pep;
            }

            $sql = 'SELECT id_org FROM organization o WHERE o.guid = ' . $guid_sql;
            $id_org = DB::query(Database::SELECT, $sql)->execute($this->_db)->get('ID_ORG');
            if ($id_org !== null && $id_org !== false) {
                $owners[] = 'ORGANIZATION.id_org=' . $id_org;
            }
        } catch (Exception $e) {
            // игнорируем
        }

        return empty($owners) ? 'неизвестно' : implode(', ', $owners);
    }

    /**
     * Экранировать строку для SQL.
     *
     * @param string $v
     * @return string
     */
    protected function _str($v)
    {
        if ($v === null) {
            return 'NULL';
        }
        $v = (string) $v;
        return "'" . str_replace("'", "''", $v) . "'";
    }
}