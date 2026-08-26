<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Cch extends Controller_Template {
    
    private $_ConnectionState = false;     // состояние связи с SOAP сервером
    protected $_cch_model = null;          // модель CCH
    protected $_session_id = null;         // ID сессии Parsec
    protected $_session_error = null;      // ошибка открытия сессии
    protected $_connection_error = null;   // ошибка подключения к SOAP
    protected $_auth_error = null;         // ошибка авторизации
    
    public function before()
    {
        parent::before();
		
		// === ПРОВЕРКА СТРУКТУРЫ БД ===
        $this->_cch_model = new Model_Cch();
        $db_errors = $this->_cch_model->checkDatabaseStructure();
        if (!empty($db_errors)) {
            $this->_db_structure_error = implode(' ', $db_errors);
            $this->template->content = View::factory('error_page')
                ->set('message', $this->_db_structure_error);
            return false; // останавливаем выполнение action
        }
		
		
        
        $this->_cch_model = new Model_Cch();
        $status = $this->_cch_model->getConnectionStatus();

        if ($status->error) {
            // Сервер недоступен
            $this->_ConnectionState = false;
            $this->_connection_error = $status->message;
        } else {
            $this->_ConnectionState = true;
            
            // Открываем сессию
            $openSessionResult = $this->_cch_model->OpenSession();

            if (isset($openSessionResult->error) && $openSessionResult->error) {
    $this->_session_error = $openSessionResult->message;
    $this->_auth_error = 'Ошибка авторизации в Parsec: ' . $openSessionResult->message;
} else {
    // Проверяем, есть ли ошибка в ответе SOAP
    if (isset($openSessionResult->OpenSessionResult->Result) && $openSessionResult->OpenSessionResult->Result != 0) {
        // Есть ошибка авторизации
        $error_message = isset($openSessionResult->OpenSessionResult->ErrorMessage) 
            ? $openSessionResult->OpenSessionResult->ErrorMessage 
            : 'Неизвестная ошибка авторизации';
        $this->_session_error = $error_message;
        $this->_auth_error = 'Ошибка авторизации в Parsec: ' . $error_message;
    } elseif (isset($openSessionResult->OpenSessionResult->Value->SessionID)) {
        // Успешная авторизация
        $this->_session_id = $openSessionResult->OpenSessionResult->Value->SessionID;
    } else {
        // Неизвестный формат ответа
        $this->_session_error = 'Неизвестный формат ответа от сервера Parsec';
        $this->_auth_error = 'Ошибка: ' . $this->_session_error;
    }
}
        }
    }
    
    public $template = 'template';
    
    public function action_err()
    {
        $content = View::factory('error_page');
        $this->template->content = $content;
    }
    
    /**
     * Получить список категорий доступа
     */
    public function action_getAccess()
    {
        if (!$this->_checkSession()) return;
        
        $GetPerson = $this->_cch_model->GetAccessGroups($this->_session_id);
        $content = View::factory('cch/search')->set('result', $GetPerson);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * Получить список организаций
     */
    public function action_GetOrgUnitsHierarhy()
    {
        if (!$this->_checkSession()) return;
        
        $GetPerson = $this->_cch_model->GetOrgUnitsHierarhy($this->_session_id);
        $content = View::factory('cch/search')->set('result', $GetPerson);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * GetPersonIdentifiers
     */
    public function action_GetPersonIdentifiers()
    {
        if (!$this->_checkSession()) return;
        
        $guid_pep = $this->request->post('guid_pep');
        $GetPerson = $this->_cch_model->GetPersonIdentifiers($this->_session_id, $guid_pep);
        $content = View::factory('cch/search')->set('result', $GetPerson);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * AddIdentifier – добавить идентификатор персоне
     */
    public function action_AddIdentifier()
    {
        if (!$this->_checkSession()) return;
        
        $guid_pep = $this->request->post('guid_pep');
        $card = $this->request->post('card');
        
        $GetPerson = $this->_cch_model->AddPersonIdentifier($this->_session_id, $card, $guid_pep);
        $content = View::factory('cch/search')->set('result', $GetPerson);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * DeleteIdentifier – удалить идентификатор
     */
    public function action_DeleteIdentifier()
    {
        if (!$this->_checkSession()) return;
        
        $card = $this->request->post('card');
        $GetPerson = $this->_cch_model->DeleteIdentifier($this->_session_id, $card);
        $content = View::factory('cch/search')->set('result', $GetPerson);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * DeletePerson – удалить персону
     */
    public function action_DeletePerson()
    {
        if (!$this->_checkSession()) return;
        
        $guid_pep = $this->request->post('guid_pep');
        $GetPerson = $this->_cch_model->DeletePerson($this->_session_id, $guid_pep);
        $content = View::factory('cch/search')->set('result', $GetPerson);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    public function action_index()
    {
        if ($this->request->method() === 'POST') {
            $this->_addAccessName($_POST);
            $this->_mainView();
        } else {
            $this->_mainView();
        }
    }
    
public function _mainView()
{
    $pars = $this->_cch_model;
    
    // Получаем конфигурацию SOAP
    $soapConfig = $this->_getSoapConfigContent();
    
    // Инициализируем переменные для представления
    $version = null;
    $GetDomains = null;
    $GetRootOrgUnit = null;
    $GetAccessGroups = null;
    $getAccessArtonit = null;

    // Если соединение есть и сессия открыта – получаем данные через SOAP
    if ($this->_ConnectionState) {
        // Версия SOAP-сервера (не требует сессии)
        $version = $pars->getParsecSoapVersion();
        if (isset($version->error) && $version->error) {
            $version = 'Ошибка: ' . $version->message;
        }
 	
        $GetDomains = $pars->GetDomains();
        if (isset($GetDomains->error) && $GetDomains->error) {
            $GetDomains = 'Ошибка: ' . $GetDomains->message;
        }
        
        $GetRootOrgUnit = $pars->GetRootOrgUnit($this->_session_id);
        if (isset($GetRootOrgUnit->error) && $GetRootOrgUnit->error) {
            $GetRootOrgUnit = 'Ошибка: ' . $GetRootOrgUnit->message;
        }
        
        $GetAccessGroups = $pars->GetAccessGroups($this->_session_id);
        if (isset($GetAccessGroups->error) && $GetAccessGroups->error) {
            $GetAccessGroups = 'Ошибка: ' . $GetAccessGroups->message;
        } else {
            // Получаем локальные категории доступа из БД Артонит (если есть)
            $getAccessArtonit = $this->_getAccessArtonit();
        }
    }
    
    // Формируем текст статуса сессии для отображения
    $session_status = '';
	
    if ($this->_connection_error) {
        $session_status = 'Ошибка: ' . $this->_connection_error;
    } elseif ($this->_auth_error) {
        $session_status = 'Ошибка: ' . $this->_auth_error;
    } elseif ($this->_session_id) {
        $session_status = 'Сессия активна (ID: ' . $this->_session_id . ')';
    } elseif ($this->_session_error) {
        $session_status = 'Ошибка: ' . $this->_session_error;
    } else {
        $session_status = 'Сессия не открыта';
    }
    
    $content = View::factory('cch/dashboard', array(
        'version' => $version,
        'soapConfig' => $soapConfig,
        'OpenSession' => $session_status,
        'GetDomains' => $GetDomains,
        'GetRootOrgUnit' => $GetRootOrgUnit,
        'GetOrgUnitsHierarhy' => null,
        'GetAccessGroups' => $GetAccessGroups,
        'getAccessArtonit' => $getAccessArtonit,
        // Явно передаем статусы для отображения
        'connection_state' => $this->_ConnectionState,
        'connection_error' => $this->_connection_error,
        'auth_error' => $this->_auth_error,
        'session_id' => $this->_session_id,
        'session_error' => $this->_session_error,
    ));
    
    // Добавляем alert с ошибкой, если есть
    $content = $this->_addErrorAlert($content);
    
    $this->template->content = $content;
}
    
    public function action_search()
    {
        if ($this->request->method() === 'POST') {
            $this->_search_from_post();
        } else {
            $this->_show_search_form();
        }
    }
    
    public function action_OpenPersonEditingSession()
    {
        if (!$this->_checkSession()) return;
        
        $guid_pep = $this->request->post('guid_pep');
        $GetPerson = $this->_cch_model->OpenPersonEditingSession($this->_session_id, $guid_pep);
        $content = View::factory('cch/search')
            ->set('result', $GetPerson)
            ->set('guid_pep', $guid_pep);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    protected function _show_search_form()
    {
        $content = View::factory('cch/search');
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    protected function _search_from_post()
    {
        if (!$this->_checkSession()) return;
        
        $guid_pep = $this->request->post('guid_pep');
        $GetPerson = $this->_cch_model->GetPerson($this->_session_id, $guid_pep);
        $content = View::factory('cch/search')
            ->set('result', $GetPerson)
            ->set('guid_pep', $guid_pep);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    public function action_searchCard()
    {
        if ($this->request->method() === 'POST') {
            $this->_search_card_from_post();
        } else {
            $this->_show_search_card_form();
        }
    }
    
    protected function action_GetIdentifierExtraData()
    {
        if (!$this->_checkSession()) return;
        
        $card = $this->request->post('card');
        $GetIdentifierExtraData = $this->_cch_model->GetIdentifierExtraData($this->_session_id, $card);
        $content = View::factory('cch/search')
            ->set('result', $GetIdentifierExtraData)
            ->set('card', $card);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    protected function action_GetObjectName()
    {
        if (!$this->_checkSession()) return;
        
        $guid = $this->request->post('guid');
        $GetObjectName = $this->_cch_model->GetObjectName($this->_session_id, $guid);
        $content = View::factory('cch/search')
            ->set('result', $GetObjectName)
            ->set('guid', $guid);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    protected function action_GetInheritedAccessGroups()
    {
        if (!$this->_checkSession()) return;
        
        $guid_access = $this->request->post('guid_access');
        $GetInheritedAccessGroups = $this->_cch_model->GetInheritedAccessGroups($this->_session_id, $guid_access);
        $content = View::factory('cch/search')
            ->set('result', $GetInheritedAccessGroups)
            ->set('guid_access', $guid_access);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    protected function _search_card_from_post()
    {
        if (!$this->_checkSession()) return;
        
        $card = $this->request->post('card');
        $FindPersonByIdentifier = $this->_cch_model->FindPersonByIdentifier($this->_session_id, $card);
        $content = View::factory('cch/search')
            ->set('result', $FindPersonByIdentifier)
            ->set('card', $card);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    public function action_searchOrg()
    {
        if ($this->request->method() === 'POST') {
            $this->_search_org_from_post();
        } else {
            $this->_show_search_card_form();
        }
    }
    
    protected function _search_org_from_post()
    {
        if (!$this->_checkSession()) return;
        
        $guid_org = $this->request->post('guid_org');
        $GetOrgUnit = $this->_cch_model->GetOrgUnit($this->_session_id, $guid_org);
        $content = View::factory('cch/search')
            ->set('result', $GetOrgUnit)
            ->set('guid_org', $guid_org);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * Сравнение организаций в БД СКУД и в БД Парсек.
     */
    public function action_compareOrg()
    {
        if (!$this->_checkSession()) return;
        
        $addOrg = isset($_POST['addOrg']) ? true : false;
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(600);
       
        $orgList = $this->_getOrgList();
        $resultList = array();
        $timestart = microtime(true);
        $resultList['orgcount'] = count($orgList);
        
        foreach (array_slice($orgList, 0, 10) as $value) {
            $guid = Arr::get($value, 'GUID');
            $orgUnit = $this->_cch_model->GetOrgUnit($this->_session_id, $guid);
            $orgUnitArray = (array) $orgUnit;
            if (empty($orgUnitArray)) {
                $resultList['org_not_in_parsec'][] = $guid;
                if ($addOrg) {
                    $this->_addOrgListTask($guid);
                }
            }
        }
        
        $resultList['timeexcute'] = (microtime(true) - $timestart);
        set_time_limit($original_time_limit);
        
        $content = View::factory('cch/search')->set('result', $resultList);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * Сравнение пиплов в БД СКУД и в БД Парсек.
     */
    public function action_comparePeople()
    {
        if (!$this->_checkSession()) return;
        
        $addPep = isset($_POST['addPeople']) ? true : false;
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(600);
       
        $orgList = $this->_getPeopleList();
        $resultList = array();
        $timestart = microtime(true);
        $resultList['pepcount'] = count($orgList);
        
        foreach (array_slice($orgList, 4000, 1000) as $value) {
            $guid = Arr::get($value, 'GUID');
            $orgUnit = $this->_cch_model->GetPerson($this->_session_id, $guid);
            $orgUnitArray = (array) $orgUnit;
            if (empty($orgUnitArray)) {
                $resultList['person_not_in_parsec'][] = $guid;
                if ($addPep) {
                 ///   $this->_addPepistTask($guid);
                }
            }
        }
        
        $resultList['timeexcute'] = (microtime(true) - $timestart);
        set_time_limit($original_time_limit);
        
        $content = View::factory('cch/search')->set('result', $resultList);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    /**
     * Проверка очереди задач интегратора.
     */
    public function action_chectTaskOrder()
    {
        if (!$this->_checkSession()) return;
        
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(300);
        
        $orgList = $this->_getOrgListTask();
        $resultList = array();
        foreach ($orgList as $value) {
            $guid = Arr::get($value, 'GUID');
            $orgUnit = $this->_cch_model->GetOrgUnit($this->_session_id, $guid);
            $orgUnitArray = (array) $orgUnit;
            if (empty($orgUnitArray)) {
                $resultList[] = $guid;
                $this->_delOrgListTask($guid);
            }
        }
        set_time_limit($original_time_limit);
        
        $content = View::factory('cch/search')->set('result', $resultList);
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
    
    
    // --- вспомогательные методы ---
    
    protected function _getOrgList()
    {
        $sql = 'select o.guid from organization o';
        return DB::query(Database::SELECT, $sql)
            ->execute(Database::instance('fb'))
            ->as_array();
    }
    
    protected function _getPeopleList()
    {
        $sql = 'select p.guid from people p
			where p.guid is not null
			';
        return DB::query(Database::SELECT, $sql)
            ->execute(Database::instance('fb'))
            ->as_array();
    }
    
    protected function _getOrgListTask()
    {
        $sql = 'select cd.id_card as guid from cardindev cd where cd.operation=5';
        return DB::query(Database::SELECT, $sql)
            ->execute(Database::instance('fb'))
            ->as_array();
    }
    
    protected function _delOrgListTask($guid)
    {
        $sql = 'delete from cardindev cd
                where cd.operation=5
                and cd.id_card = :guid';
        return DB::query(Database::DELETE, $sql)
            ->param(':guid', $guid)
            ->execute(Database::instance('fb'));
    }
    
    protected function _addOrgListTask($guid)
    {
        $sql = 'INSERT INTO CARDINDEV (ID_DB, ID_CARD, DEVIDX, ID_DEV, OPERATION, ATTEMPTS, ID_PEP)
                VALUES (1, :guid, NULL, NULL, 5, 0, 1)';
        try {
            DB::query(Database::INSERT, $sql)
                ->param(':guid', $guid)
                ->execute(Database::instance('fb'));
        } catch (Exception $e) {
            // логирование ошибки
        }
    }
    
    protected function _getAccessArtonit()
    {
        $result = array();
        $sql = 'select an.id_accessname, an.name, an.time_stamp, an.guid from accessname an';
        try {
            $query = DB::query(Database::SELECT, $sql)
                ->execute(Database::instance('fb'));
            foreach ($query as $value) {
                $result[Arr::get($value, 'GUID')]['id'] = Arr::get($value, 'ID');
                $result[Arr::get($value, 'GUID')]['name'] = Arr::get($value, 'NAME');
                $result[Arr::get($value, 'GUID')]['time_stamp'] = Arr::get($value, 'TIME_STAMP');
            }
            return $result;
        } catch (Exception $e) {
            echo Debug::vars('386', $e);
        }
        return array();
    }
    
   protected function _addAccessName($data)
{
    // Проверяем существование GUID
    $guid = Arr::get($data, 'guid');
    $name = Arr::get($data, 'name');
    
    if (empty($guid) || empty($name)) {
        Log::instance()->add(Log::WARNING, 'Отсутствуют обязательные параметры: guid или name');
        return false;
    }
    
    try {
        // Проверяем, существует ли запись с таким GUID
        $checkSql = 'SELECT COUNT(*)  FROM ACCESSNAME WHERE GUID = \''.$guid.'\'';
        $checkQuery = DB::query(Database::SELECT, $checkSql)
            ->execute(Database::instance('fb'))
            ->as_array();
        
        $exists = (int)$checkQuery[0]['COUNT'] > 0;
        
        if ($exists) {
            Log::instance()->add(Log::NOTICE, 'Запись с GUID ' . $guid . ' уже существует');
            return false;
        }
        
        // Вставляем новую запись
        $sql = 'INSERT INTO ACCESSNAME (ID_DB, NAME, GUID) VALUES (1, \''.$name.'\', \''.$guid.'\')';
        
        Log::instance()->add(Log::NOTICE, 'Выполняется INSERT: ' . $sql);
        
        // Для PDO используем параметры через метод parameters()
        $query = DB::query(Database::INSERT, iconv('UTF-8', 'windows-1251', $sql))

            ->execute(Database::instance('fb'));
        
        Log::instance()->add(Log::NOTICE, 'Запись успешно добавлена. ID: ' . $query);
        
        return $query; // Возвращаем ID новой записи
        
    } catch (Exception $e) {
        Log::instance()->add(Log::ERROR, 'Ошибка при вставке записи: ' . $e->getMessage());
        return false;
    }
}
    
    public static function uniqueGuid($guid)
    {
        $sql = 'select an.guid from accessname an where an.guid = :guid';
        return DB::query(Database::SELECT, $sql)
            ->param(':guid', $guid)
            ->execute(Database::instance('fb'))
            ->get('GUID');
    }
    
    /**
     * Вспомогательный метод для проверки наличия активной сессии.
     * @return bool
     */
    protected function _checkSession()
    {
        if ($this->_connection_error) {
            $this->template->content = $this->_addErrorAlert('Нет соединения с SOAP-сервером Parsec.');
            return false;
        }
        if ($this->_auth_error) {
            $this->template->content = $this->_addErrorAlert($this->_auth_error);
            return false;
        }
        if (empty($this->_session_id)) {
            $error = $this->_session_error ?: 'Не удалось открыть сессию Parsec.';
            $this->template->content = $this->_addErrorAlert($error);
            return false;
        }
        return true;
    }
    
    /**
     * Выводит JavaScript alert с сообщением об ошибке
     * @param string|View $view представление, в которое добавляется скрипт
     * @return string модифицированный HTML
     */
    protected function _addErrorAlert($view)
    {
        $errorMsg = null;
        
        if ($this->_connection_error) {
            $errorMsg = $this->_connection_error;
        } elseif ($this->_auth_error) {
            $errorMsg = $this->_auth_error;
        } elseif ($this->_session_error && !$this->_session_id) {
            $errorMsg = 'Ошибка: ' . $this->_session_error;
        }
        
        if ($errorMsg) {
            $alert = '<script type="text/javascript">
                $(document).ready(function() {
                    alert("' . addslashes($errorMsg) . '");
                });
            </script>';
            
            // Если $view - объект View, рендерим его
            if ($view instanceof View) {
                $view = (string) $view;
            }
            return $view . $alert;
        }
        
        return $view;
    }
    
    protected function _show_search_card_form()
    {
        $content = View::factory('cch/search');
        $content = $this->_addErrorAlert($content);
        $this->template->content = $content;
    }
    
	
			/**
		 * Получить содержимое конфигурационного файла soap.php
		 * @return array|string
		 */
		protected function _getSoapConfigContent()
		{
			$config_file = DOCROOT . 'modules/parsec/config/soap.php';
			
			if (!file_exists($config_file)) {
				return 'Файл конфигурации не найден: ' . $config_file;
			}
			
			try {
				// Читаем содержимое файла
				$content = file_get_contents($config_file);
				
				// Для отладки – показываем сырое содержимое
				// return '<pre>' . htmlspecialchars($content) . '</pre>';
				
				// Или загружаем конфигурацию через Kohana
				$config = (array) Kohana::$config->load('soap.parsec');
				
				return $config;
				
			} catch (Exception $e) {
				return 'Ошибка чтения файла: ' . $e->getMessage();
			}
		}


} // End cch