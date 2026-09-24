<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Поиск GUID по всем сущностям Parsec.
 *
 * Проверяет указанный GUID во всех доступных сущностях:
 *   - персона           (GetPerson)
 *   - организация       (GetOrgUnit)
 *   - территория        (GetTerritory)
 *   - расписание        (GetSchedule)
 *   - категория доступа (GetAccessGroups, поиск в списке)
 *   - роль группового прохода (GetPassageRoles, поиск в списке)
 *   - унаследованные группы (GetInheritedAccessGroups)
 *   - праздники         (GetHolidays, поиск в списке)
 *   - расписания доступа (GetAccessSchedules, поиск в списке)
 *   - расписания рабочего времени (GetWorktimeSchedules, поиск в списке)
 *   - домены            (GetDomains, поиск в списке)
 *
 * Запуск:
 *   c:\xampp\php\php.exe c:\xampp\htdocs\city\modules\minion\minion ^
 *       --task=parsecSearchGuid --guid=441dc23b-1111-44d2-a999-1379ff028067
 *
 * Опции:
 *   --guid    GUID для поиска (обязательно)
 *   --verbose 1|0   Подробный вывод (по умолчанию 1)
 *
 * @version 1.0.0
 * @date    2026-09-24
 */
class Task_parsecSearchGuid extends Minion_Task
{
    protected $_options = array(
        'guid'    => '',
        'verbose' => 1,
    );

    /** @var Model_Cch */
    protected $_cch_model = null;

    /** @var string */
    protected $_session_id = null;

    /** @var string */
    protected $_guid = '';

    /** @var array */
    protected $_report = array();

    // ---------------------------------------------------------------------
    // Точка входа
    // ---------------------------------------------------------------------

    protected function _execute(array $params)
    {
        $this->_guid    = strtoupper(trim((string) Arr::get($params, 'guid', '')));
        $verbose        = (int) Arr::get($params, 'verbose', 1) === 1;

        Minion_CLI::write('=== Поиск GUID по всем сущностям Parsec ===');

        if ($this->_guid === '') {
            Minion_CLI::write('ОШИБКА: не указан GUID. Используйте --guid=...');
            return;
        }

        Minion_CLI::write('GUID: ' . $this->_guid);
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

        // --- 2. Сессия ---
        if (!$this->_open_session()) {
            return;
        }

        // --- 3. Все проверки ---
        $this->_check_person();
        $this->_check_org_unit();
        $this->_check_territory();
        $this->_check_schedule();
        $this->_check_access_groups();
        $this->_check_passage_roles();
        $this->_check_inherited_groups();
        $this->_check_holidays();
        $this->_check_access_schedules();
        $this->_check_worktime_schedules();
        $this->_check_domains();

        // --- 4. Итоги ---
        $this->_print_summary();
    }

    // ---------------------------------------------------------------------
    // Открытие сессии
    // ---------------------------------------------------------------------

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

    // ---------------------------------------------------------------------
    // Универсальные проверки
    // ---------------------------------------------------------------------

    /**
     * Проверка сущности, у которой есть метод «получить по GUID».
     *
     * @param string   $name       Название проверки
     * @param mixed    $response   Ответ SOAP
     * @param string   $result_key Ключ в ответе (GetPersonResult, GetOrgUnitResult, ...)
     * @param callable $formatter  Формат найденного объекта
     */
    protected function _check_by_guid($name, $response, $result_key, $formatter)
    {
        $found   = false;
        $details = '';
        $error   = '';

        if (isset($response->error) && $response->error) {
            $error = isset($response->message) ? $response->message : 'SOAP error';
        } else {
            $value = isset($response->$result_key) ? $response->$result_key : null;

            if ($value === null) {
                $details = 'объект не найден';
            } elseif (is_array($value) && empty($value)) {
                $details = 'объект не найден (пустой массив)';
            } else {
                $found   = true;
                $details = $formatter($value);
            }
        }

        $this->_add_report($name, $found, $details, $error);
    }

    /**
     * Проверка сущности, у которой есть только список (Get…s).
     * Ищет GUID среди элементов списка.
     *
     * @param string   $name       Название проверки
     * @param mixed    $response   Ответ SOAP
     * @param string   $result_key Ключ в ответе (GetHolidaysResult, ...)
     * @param callable $formatter  Формат найденного элемента
     */
    protected function _check_in_list($name, $response, $result_key, $formatter)
    {
        $found   = false;
        $details = '';
        $error   = '';

        if (isset($response->error) && $response->error) {
            $error = isset($response->message) ? $response->message : 'SOAP error';
        } else {
            $list = isset($response->$result_key) ? $response->$result_key : null;

            if ($list === null) {
                $details = 'список пуст';
            } else {
                $flat = $this->_flatten_list($list);

                foreach ($flat as $item) {
                    if (is_object($item) && isset($item->ID)
                        && strtoupper(trim((string) $item->ID)) === $this->_guid) {
                        $found   = true;
                        $details = $formatter($item);
                        break;
                    }
                }

                if (!$found) {
                    $details = 'GUID не найден среди ' . count($flat) . ' элементов';
                }
            }
        }

        $this->_add_report($name, $found, $details, $error);
    }

    /**
     * Приводит ответ SOAP к плоскому массиву объектов.
     *
     * @param mixed $list
     * @return array
     */
    protected function _flatten_list($list)
    {
        if (is_object($list)) {
            $list = (array) $list;
        }

        if (!is_array($list)) {
            return array($list);
        }

        $flat = array();
        foreach ($list as $item) {
            if (is_array($item)) {
                foreach ($item as $sub) {
                    $flat[] = $sub;
                }
            } else {
                $flat[] = $item;
            }
        }

        return $flat;
    }

    /**
     * @param string $name
     * @param bool   $found
     * @param string $details
     * @param string $error
     */
    protected function _add_report($name, $found, $details, $error)
    {
        $this->_report[] = array(
            'name'    => $name,
            'found'   => $found,
            'details' => $details,
            'error'   => $error,
        );

        $status = $error !== '' ? 'ОШИБКА' : ($found ? 'НАЙДЕНО' : 'нет');
        Minion_CLI::write(sprintf(
            "  %-45s [%s] %s",
            $name,
            $status,
            $error !== '' ? $error : $details
        ));
    }

    // ---------------------------------------------------------------------
    // Конкретные проверки
    // ---------------------------------------------------------------------

    protected function _check_person()
    {
        $r = $this->_cch_model->GetPerson($this->_session_id, $this->_guid);
        $this->_check_by_guid(
            'Персона (GetPerson)',
            $r,
            'GetPersonResult',
            function ($v) {
                return 'ID=' . (isset($v->ID) ? $v->ID : '?')
                     . ', ФИО=' . trim(
                         (isset($v->LAST_NAME)   ? $v->LAST_NAME   : '') . ' ' .
                         (isset($v->FIRST_NAME)  ? $v->FIRST_NAME  : '') . ' ' .
                         (isset($v->MIDDLE_NAME) ? $v->MIDDLE_NAME : '')
                     );
            }
        );
    }

    protected function _check_org_unit()
    {
        $r = $this->_cch_model->GetOrgUnit($this->_session_id, $this->_guid);
        $this->_check_by_guid(
            'Организация (GetOrgUnit)',
            $r,
            'GetOrgUnitResult',
            function ($v) {
                return 'ID=' . (isset($v->ID) ? $v->ID : '?')
                     . ', NAME=' . (isset($v->NAME) ? $v->NAME : '?');
            }
        );
    }

    protected function _check_territory()
    {
        if (!method_exists($this->_cch_model, 'GetTerritory')) {
            $this->_add_report('Территория (GetTerritory)', false, '', 'метод не реализован в Model_Cch');
            return;
        }
        $r = $this->_cch_model->GetTerritory($this->_session_id, $this->_guid);
        $this->_check_by_guid(
            'Территория (GetTerritory)',
            $r,
            'GetTerritoryResult',
            function ($v) {
                return 'ID=' . (isset($v->ID) ? $v->ID : '?')
                     . ', NAME=' . (isset($v->NAME) ? $v->NAME : '?')
                     . ', TYPE=' . (isset($v->TYPE) ? $v->TYPE : '?');
            }
        );
    }

    protected function _check_schedule()
    {
        if (!method_exists($this->_cch_model, 'GetSchedule')) {
            $this->_add_report('Расписание (GetSchedule)', false, '', 'метод не реализован в Model_Cch');
            return;
        }
        $r = $this->_cch_model->GetSchedule($this->_session_id, $this->_guid);
        $this->_check_by_guid(
            'Расписание (GetSchedule)',
            $r,
            'GetScheduleResult',
            function ($v) {
                return 'ID=' . (isset($v->ID) ? $v->ID : '?')
                     . ', NAME=' . (isset($v->NAME) ? $v->NAME : '?');
            }
        );
    }

    protected function _check_access_groups()
    {
        $r = $this->_cch_model->GetAccessGroups($this->_session_id);
        $this->_check_in_list(
            'Категория доступа (GetAccessGroups)',
            $r,
            'GetAccessGroupsResult',
            function ($item) {
                return 'ID=' . (isset($item->ID) ? $item->ID : '?')
                     . ', NAME=' . (isset($item->NAME) ? $item->NAME : '?');
            }
        );
    }

    protected function _check_passage_roles()
    {
        if (!method_exists($this->_cch_model, 'GetPassageRoles')) {
            $this->_add_report('Роль группового прохода (GetPassageRoles)', false, '', 'метод не реализован в Model_Cch');
            return;
        }
        $r = $this->_cch_model->GetPassageRoles($this->_session_id);
        $this->_check_in_list(
            'Роль группового прохода (GetPassageRoles)',
            $r,
            'GetPassageRolesResult',
            function ($item) {
                return 'ID=' . (isset($item->ID) ? $item->ID : '?')
                     . ', NAME=' . (isset($item->NAME) ? $item->NAME : '?');
            }
        );
    }

    protected function _check_inherited_groups()
    {
        $r = $this->_cch_model->GetInheritedAccessGroups($this->_session_id, $this->_guid);
        $this->_check_by_guid(
            'Унаследованные группы (GetInheritedAccessGroups)',
            $r,
            'GetInheritedAccessGroupsResult',
            function ($v) {
                if (is_array($v)) {
                    return 'получено элементов: ' . count($v);
                }
                if (is_object($v)) {
                    return 'объект: ' . json_encode($v);
                }
                return 'значение: ' . var_export($v, true);
            }
        );
    }

    protected function _check_holidays()
    {
        if (!method_exists($this->_cch_model, 'GetHolidays')) {
            $this->_add_report('Праздники (GetHolidays)', false, '', 'метод не реализован в Model_Cch');
            return;
        }
        $r = $this->_cch_model->GetHolidays($this->_session_id);
        $this->_check_in_list(
            'Праздники (GetHolidays)',
            $r,
            'GetHolidaysResult',
            function ($item) {
                return 'ID=' . (isset($item->ID) ? $item->ID : '?')
                     . ', NAME=' . (isset($item->NAME) ? $item->NAME : '?')
                     . ', MONTH=' . (isset($item->MONTH) ? $item->MONTH : '?')
                     . ', DAY='   . (isset($item->DAY)   ? $item->DAY   : '?');
            }
        );
    }

    protected function _check_access_schedules()
    {
        if (!method_exists($this->_cch_model, 'GetAccessSchedules')) {
            $this->_add_report('Расписания доступа (GetAccessSchedules)', false, '', 'метод не реализован в Model_Cch');
            return;
        }
        $r = $this->_cch_model->GetAccessSchedules($this->_session_id);
        $this->_check_in_list(
            'Расписания доступа (GetAccessSchedules)',
            $r,
            'GetAccessSchedulesResult',
            function ($item) {
                return 'ID=' . (isset($item->ID) ? $item->ID : '?')
                     . ', NAME=' . (isset($item->NAME) ? $item->NAME : '?');
            }
        );
    }

    protected function _check_worktime_schedules()
    {
        if (!method_exists($this->_cch_model, 'GetWorktimeSchedules')) {
            $this->_add_report('Расписания рабочего времени (GetWorktimeSchedules)', false, '', 'метод не реализован в Model_Cch');
            return;
        }
        $r = $this->_cch_model->GetWorktimeSchedules($this->_session_id);
        $this->_check_in_list(
            'Расписания рабочего времени (GetWorktimeSchedules)',
            $r,
            'GetWorktimeSchedulesResult',
            function ($item) {
                return 'ID=' . (isset($item->ID) ? $item->ID : '?')
                     . ', NAME=' . (isset($item->NAME) ? $item->NAME : '?');
            }
        );
    }

    protected function _check_domains()
    {
        $r = $this->_cch_model->GetDomains();
        $this->_check_in_list(
            'Домены (GetDomains)',
            $r,
            'GetDomainsResult',
            function ($item) {
                return 'ID=' . (isset($item->ID) ? $item->ID : '?')
                     . ', NAME=' . (isset($item->NAME) ? $item->NAME : '?');
            }
        );
    }

    // ---------------------------------------------------------------------
    // Итоги
    // ---------------------------------------------------------------------

    protected function _print_summary()
    {
        Minion_CLI::write('');
        Minion_CLI::write('=== ИТОГИ ===');

        $found    = 0;
        $errors   = 0;
        $notfound = 0;

        foreach ($this->_report as $r) {
            if ($r['error'] !== '') {
                $errors++;
            } elseif ($r['found']) {
                $found++;
            } else {
                $notfound++;
            }
        }

        Minion_CLI::write('Всего проверок:          ' . count($this->_report));
        Minion_CLI::write('GUID найден:             ' . $found);
        Minion_CLI::write('GUID не найден:          ' . $notfound);
        Minion_CLI::write('Ошибок SOAP/ответа:      ' . $errors);
        Minion_CLI::write('');

        if ($found > 0) {
            Minion_CLI::write('=== GUID НАЙДЕН В ===');
            foreach ($this->_report as $r) {
                if ($r['error'] === '' && $r['found']) {
                    Minion_CLI::write('  - ' . $r['name'] . ' — ' . $r['details']);
                }
            }
            Minion_CLI::write('');
        } else {
            Minion_CLI::write('=== GUID НЕ НАЙДЕН НИ В ОДНОЙ СУЩНОСТИ ===');
            Minion_CLI::write('');
        }

        if ($errors > 0) {
            Minion_CLI::write('=== ОШИБКИ ===');
            foreach ($this->_report as $r) {
                if ($r['error'] !== '') {
                    Minion_CLI::write('  - ' . $r['name'] . ': ' . $r['error']);
                }
            }
            Minion_CLI::write('');
        }

        Minion_CLI::write('Готово.');
    }
}