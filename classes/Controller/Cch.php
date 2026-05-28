<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Cch extends Controller_Template {
    
    private $_ConnectionState = false; // состояние связи с SOAP сервером
    protected $_cch_model = null;      // модель CCH
    protected $_session_id = null;     // ID сессии Parsec
    protected $_session_error = null;   // ошибка открытия сессии
    
    public function before()
    {
        parent::before();
        
        $this->_cch_model = new Model_Cch();
        $status = $this->_cch_model->getConnectionStatus();

        if ($status->error) {
            // Сервер недоступен
            echo $status->message;
           
            $this->_ConnectionState = false;
            return; // не пытаемся открыть сессию
        } else {

            $this->_ConnectionState = true;
            echo $status->message;
            
            // Открываем сессию
            $openSessionResult = $this->_cch_model->OpenSession();
            if (isset($openSessionResult->error) && $openSessionResult->error) {
                $this->_session_error = $openSessionResult->message;
                echo 'Ошибка открытия сессии: ' . $openSessionResult->message;
                // Можно также залогировать
            } else {
                $this->_session_id = $openSessionResult->OpenSessionResult->Value->SessionID;
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
    
    // Инициализируем переменные для представления
    $version = null;
    $openSessionError = null;
    $GetDomains = null;
    $GetRootOrgUnit = null;
    $GetAccessGroups = null;
    $getAccessArtonit = null;
    
    // Если соединение есть и сессия открыта – получаем данные через SOAP
    if ($this->_ConnectionState && $this->_session_id) {
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
    } else {
        // Нет соединения или сессии – готовим сообщения об ошибке
        if (!$this->_ConnectionState) {
            $errorMsg = 'Нет соединения с SOAP-сервером Parsec.';
        } else {
            $errorMsg = $this->_session_error ?: 'Не удалось открыть сессию Parsec.';
        }
        $version = $errorMsg;
        $GetDomains = $errorMsg;
        $GetRootOrgUnit = $errorMsg;
        $GetAccessGroups = $errorMsg;
        // $getAccessArtonit остаётся null
    }
    
    $content = View::factory('cch/dashboard', array(
        'version' => $version,
        'OpenSession' => $this->_session_id ? 'Сессия активна (ID: ' . $this->_session_id . ')' : ($this->_session_error ?: 'Сессия не открыта'),
        'GetDomains' => $GetDomains,
        'GetRootOrgUnit' => $GetRootOrgUnit,
        'GetOrgUnitsHierarhy' => null,
        'GetAccessGroups' => $GetAccessGroups,
        'getAccessArtonit' => $getAccessArtonit,
    ));
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
        $this->template->content = $content;
    }
    
    protected function _show_search_form()
    {
        $this->template->content = View::factory('cch/search');
    }
    
    protected function _search_from_post()
    {
        if (!$this->_checkSession()) return;
        
        $guid_pep = $this->request->post('guid_pep');
        $GetPerson = $this->_cch_model->GetPerson($this->_session_id, $guid_pep);
        $content = View::factory('cch/search')
            ->set('result', $GetPerson)
            ->set('guid_pep', $guid_pep);
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
        $this->template->content = $content;
    }
    
    /**
     * Сравнение организаций в БД СКУД и в БД Парсек.
     */
    public function action_compareOrg()
    {
        if (!$this->_checkSession()) return;
        
        $addOrg = isset($_POST['addOrg']) ? true : false; // лучше заменить на $this->request->post('addOrg')
        $original_time_limit = ini_get('max_execution_time');
        set_time_limit(300);
        
        $orgList = $this->_getOrgList();
        $resultList = array();
        foreach ($orgList as $value) {
            $guid = Arr::get($value, 'GUID');
            $orgUnit = $this->_cch_model->GetOrgUnit($this->_session_id, $guid);
            if (empty((array)$orgUnit)) {
                $resultList[] = $guid;
                if ($addOrg) {
                    $this->_addOrgListTask($guid);
                }
            }
        }
        set_time_limit($original_time_limit);
        
        $content = View::factory('cch/search')->set('result', $resultList);
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
            if (!empty((array)$orgUnit)) {
                $resultList[] = $guid;
                $this->_delOrgListTask($guid);
            }
        }
        set_time_limit($original_time_limit);
        
        $content = View::factory('cch/search')->set('result', $resultList);
        $this->template->content = $content;
    }
    
    // --- вспомогательные методы (без изменений, кроме возможных правок SQL-инъекций) ---
    
    protected function _getOrgList()
    {
        $sql = 'select o.guid from organization o';
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
        // Внимание: здесь используется __() с подстановкой – это небезопасно.
        // Лучше переписать через параметры.
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
        // Этот метод почти не используется и содержит ошибки.
        // Оставлен как есть, но лучше переписать или удалить.
        if ($this->uniqueGuid(Arr::get($data, 'guid'))) {
            echo '555';
        } else {
            echo '666';
        }
        $sql = 'INSERT INTO ACCESSNAME (ID_DB, NAME, GUID) VALUES (1, :name, :guid)';
        try {
            Log::instance()->add(Log::NOTICE, '425 ' . $sql);
            $query = DB::query(Database::INSERT, $sql)
                ->param(':name', Arr::get($data, 'name'))
                ->param(':guid', Arr::get($data, 'guid'))
                ->execute(Database::instance('fb'));
            echo Debug::vars('418', $sql, $query);
            exit;
        } catch (Exception $e) {
            // ignore
        }
        return;
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
        if (!$this->_ConnectionState) {
            $this->template->content = 'Нет соединения с SOAP-сервером Parsec.';
            return false;
        }
        if (empty($this->_session_id)) {
            $error = $this->_session_error ?: 'Не удалось открыть сессию Parsec.';
            $this->template->content = $error;
            return false;
        }
        return true;
    }
    
    protected function _show_search_card_form()
    {
        // Этот метод не определён в оригинале – добавим заглушку
        $this->template->content = View::factory('cch/search');
    }
    
} // End cch