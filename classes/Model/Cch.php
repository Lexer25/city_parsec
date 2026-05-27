<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Cch extends Model {
    
    /**
     * @var SoapClient|null SOAP клиент
     */
    protected $_soap_client = null;
    
    /**
     * @var array Конфигурация SOAP
     */
    protected $_soap_config = null;
    
    /**
     * @var int Таймаут подключения (секунды)
     */
    protected $_connection_timeout = 5;
    
    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->_load_soap_config();
    }
    
    /**
     * Загрузка конфигурации SOAP
     */
    protected function _load_soap_config()
    {
        $this->_soap_config = Kohana::$config->load('soap.parsec');
    
        if (isset($this->_soap_config['connection_timeout'])) {
            $this->_connection_timeout = (int)$this->_soap_config['connection_timeout'];
        }
    }
    
    /**
     * Быстрая проверка доступности SOAP сервера через cURL (точный таймаут)
     * 
     * @return bool true - сервер доступен, false - недоступен
     */
    public function checkConnection()
    {
        $wsdl = (Kohana::$environment === Kohana::DEVELOPMENT) 
            ? $this->_soap_config['wsdl_dev'] 
            : $this->_soap_config['wsdl'];
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $wsdl,
            CURLOPT_NOBODY => true,           // HEAD запрос (не скачиваем тело)
            CURLOPT_CONNECTTIMEOUT => $this->_connection_timeout,  // Таймаут подключения
            CURLOPT_TIMEOUT => $this->_connection_timeout,         // Общий таймаут
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FAILONERROR => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3
        ));
        
        $start_time = microtime(true);
        $result = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        $connect_time = curl_getinfo($ch, CURLINFO_CONNECT_TIME);
        $total_time = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
        curl_close($ch);
        
        // Логируем результат проверки
        Kohana::$log->add(Log::DEBUG, "SOAP connection check: HTTP={$http_code}, connect_time={$connect_time}s, total_time={$total_time}s");
        
        // Считаем соединение успешным при любом HTTP ответе (200, 301, 302, 401, 500 и т.д.)
        // Главное, что сервер ответил в течение таймаута
        if ($curl_error === '' && $http_code > 0) {
            return true;
        }
        
        Kohana::$log->add(Log::ERROR, "SOAP сервер недоступен: {$curl_error}");
        return false;
    }
    
    /**
     * Инициализация SOAP клиента с быстрой проверкой
     * 
     * @return bool
     */
    protected function _init_soap_client()
    {
        // Не инициализируем повторно, если уже есть
        if ($this->_soap_client !== null) {
            return true;
        }
        
        $wsdl = (Kohana::$environment === Kohana::DEVELOPMENT) 
            ? $this->_soap_config['wsdl_dev'] 
            : $this->_soap_config['wsdl'];
        
        // Быстрая проверка соединения перед инициализацией SOAP
        if (!$this->checkConnection()) {
            Kohana::$log->add(Log::ERROR, 'SOAP клиент не инициализирован: сервер недоступен');
            return false;
        }
        
        $options = $this->_soap_config['soap_options'];
        $options['connection_timeout'] = $this->_connection_timeout;
        
        // Настройка stream context для таймаутов
        $stream_context = stream_context_create(array(
            'http' => array(
                'timeout' => $this->_connection_timeout
            )
        ));
        $options['stream_context'] = $stream_context;
        
        try {
            $this->_soap_client = new SoapClient($wsdl, $options);
            Kohana::$log->add(Log::INFO, 'SOAP клиент инициализирован');
            return true;
        } catch (Exception $e) {
            Kohana::$log->add(Log::ERROR, 'SOAP init error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Безопасный вызов SOAP метода с быстрой проверкой
     */
    protected function _call_soap($method, $params = array(), $retry = true)
    {
        // Быстрая проверка соединения перед вызовом
        if (!$this->checkConnection()) {
            return (object) array(
                'error' => true,
                'message' => 'Сервер Parsec недоступен. Проверьте сетевое соединение. (таймаут ' . $this->_connection_timeout . ' сек)'
            );
        }
        
        if (!$this->_init_soap_client()) {
            return (object) array(
                'error' => true,
                'message' => 'Не удалось подключиться к SOAP серверу Parsec'
            );
        }
        
        try {
            $result = $this->_soap_client->$method($params);
            return $result;
        } catch (SoapFault $e) {
            Kohana::$log->add(Log::ERROR, "SOAP {$method} fault: " . $e->getMessage());
            
            if ($retry) {
                $this->_soap_client = null;
                return $this->_call_soap($method, $params, false);
            }
            
            return (object) array(
                'error' => true,
                'message' => 'Ошибка SOAP: ' . $e->getMessage()
            );
        } catch (Exception $e) {
            Kohana::$log->add(Log::ERROR, "SOAP {$method} error: " . $e->getMessage());
            
            if ($retry) {
                $this->_soap_client = null;
                return $this->_call_soap($method, $params, false);
            }
            
            return (object) array(
                'error' => true,
                'message' => 'Ошибка подключения: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Получить статус соединения с SOAP сервером
     * 
     * @return object
     */
    public function getConnectionStatus()
    {
        $wsdl = (Kohana::$environment === Kohana::DEVELOPMENT) 
            ? $this->_soap_config['wsdl_dev'] 
            : $this->_soap_config['wsdl'];
        
        $start_time = microtime(true);
        $is_available = $this->checkConnection();
        $response_time = round((microtime(true) - $start_time) * 1000);
        
        if ($is_available) {
            return (object) array(
                'error' => false,
                'connected' => true,
                'response_time_ms' => $response_time,
                'message' => 'Соединение с сервером Parsec установлено (таймаут ' . $this->_connection_timeout . ' сек)',
                'wsdl' => $wsdl,
                'timeout' => $this->_connection_timeout
            );
        } else {
            return (object) array(
                'error' => true,
                'connected' => false,
                'response_time_ms' => $response_time,
                'message' => 'Не удалось подключиться к серверу Parsec за ' . $this->_connection_timeout . ' секунд',
                'wsdl' => $wsdl,
                'timeout' => $this->_connection_timeout
            );
        }
    }
    
    /**
     * Установить таймаут подключения
     * 
     * @param int $seconds
     * @return $this
     */
    public function setTimeout($seconds)
    {
        $this->_connection_timeout = (int)$seconds;
        return $this;
    }
    
    /**
     * Получить версию SOAP сервера
     */
    public function getParsecSoapVersion()
    {
        $result = $this->_call_soap('GetVersion');
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result->GetVersionResult;
    }
    
    /**
     * Открыть сессию
     */
    public function OpenSession()
    {
        $params = array(
            'domain' => $this->_soap_config['domain'],
            'userName' => $this->_soap_config['username'],
            'password' => $this->_soap_config['password']
        );
        
        $result = $this->_call_soap('OpenSession', $params);
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Получить список доменов
     */
    public function GetDomains()
    {
        $result = $this->_call_soap('GetDomains');
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result->GetDomainsResult;
    }
    
    /**
     * Получить корневое подразделение
     */
    public function GetRootOrgUnit($session_id = null)
    {
        if ($session_id === null) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии'
            );
        }
        
        return $this->_call_soap('GetRootOrgUnit', array('sessionID' => $session_id));
    }
    
    /**
     * Получить иерархию подразделений
     */
    public function GetOrgUnitsHierarhy($session_id = null)
    {
        if ($session_id === null) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии'
            );
        }
        
        return $this->_call_soap('GetOrgUnitsHierarhy', array('sessionID' => $session_id));
    }
    
    /**
     * Получить список категорий доступа
     */
    public function GetAccessGroups($session_id = null)
    {
        if ($session_id === null) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии'
            );
        }
        
        $result = $this->_call_soap('GetAccessGroups', array('sessionID' => $session_id));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Получить информацию о персоне по GUID
     */
    public function GetPerson($session_id, $person_id)
    {
        if (empty($session_id) || empty($person_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID персоны'
            );
        }
        
        $result = $this->_call_soap('GetPerson', array(
            'sessionID' => $session_id,
            'personID' => $person_id
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Открыть сессию редактирования персоны
     */
    public function OpenPersonEditingSession($session_id, $person_id)
    {
        if (empty($session_id) || empty($person_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID персоны'
            );
        }
        
        $result = $this->_call_soap('OpenPersonEditingSession', array(
            'sessionID' => $session_id,
            'personID' => $person_id
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Получить идентификаторы персоны
     */
    public function GetPersonIdentifiers($session_id, $person_id)
    {
        if (empty($session_id) || empty($person_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID персоны'
            );
        }
        
        return $this->_call_soap('GetPersonIdentifiers', array(
            'sessionID' => $session_id,
            'personID' => $person_id
        ));
    }
    
    /**
     * Найти персону по идентификатору карты
     */
    public function FindPersonByIdentifier($session_id, $card_code)
    {
        if (empty($session_id) || empty($card_code)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или код карты'
            );
        }
        
        $result = $this->_call_soap('FindPersonByIdentifier', array(
            'sessionID' => $session_id,
            'cardCode' => strtoupper(trim($card_code))
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Получить информацию о подразделении (организации)
     */
    public function GetOrgUnit($session_id, $org_unit_id)
    {
        if (empty($session_id) || empty($org_unit_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID организации'
            );
        }
        
        $result = $this->_call_soap('GetOrgUnit', array(
            'sessionID' => $session_id,
            'orgUnitID' => $org_unit_id
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Добавить идентификатор персоне
     */
    public function AddPersonIdentifier($session_id, $card_code, $person_id)
    {
        if (empty($session_id) || empty($card_code) || empty($person_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не заполнены обязательные параметры'
            );
        }
        
        $edit_session = $this->OpenPersonEditingSession($session_id, $person_id);
        
        if (isset($edit_session->error) && $edit_session->error) {
            return $edit_session;
        }
        
        $session_guid = $edit_session->OpenPersonEditingSessionResult->Value;
        
        $identifier = array(
            'CODE' => strtoupper(trim($card_code)),
            'PERSON_ID' => $person_id,
            'IS_PRIMARY' => true
        );
        
        $result = $this->_call_soap('AddPersonIdentifier', array(
            'personEditSessionID' => $session_guid,
            'identifier' => $identifier
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Удалить идентификатор
     */
    public function DeleteIdentifier($session_id, $card_code)
    {
        if (empty($session_id) || empty($card_code)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или код карты'
            );
        }
        
        $result = $this->_call_soap('DeleteIdentifier', array(
            'sessionID' => $session_id,
            'Code' => strtoupper(trim($card_code))
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Удалить персону
     */
    public function DeletePerson($session_id, $person_id)
    {
        if (empty($session_id) || empty($person_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID персоны'
            );
        }
        
        $result = $this->_call_soap('DeletePerson', array(
            'sessionID' => $session_id,
            'personID' => $person_id
        ));
        
        if (isset($result->error) && $result->error) {
            return $result;
        }
        
        return $result;
    }
    
    /**
     * Получить дочерние категории доступа по родительскому GUID
     */
    public function GetInheritedAccessGroups($session_id, $access_group_id)
    {
        if (empty($session_id) || empty($access_group_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID категории доступа'
            );
        }
        
        return $this->_call_soap('GetInheritedAccessGroups', array(
            'sessionID' => $session_id,
            'accessGroupID' => $access_group_id
        ));
    }
    
    /**
     * Получить дополнительные данные идентификатора
     */
    public function GetIdentifierExtraData($session_id, $card_code)
    {
        if (empty($session_id) || empty($card_code)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или код карты'
            );
        }
        
        return $this->_call_soap('GetIdentifierExtraData', array(
            'sessionID' => $session_id,
            'cardCode' => strtoupper(trim($card_code))
        ));
    }
    
    /**
     * Получить тип объекта по GUID
     */
    public function GetObjectName($session_id, $object_id)
    {
        if (empty($session_id) || empty($object_id)) {
            return (object) array(
                'error' => true,
                'message' => 'Не указан ID сессии или ID объекта'
            );
        }
        
        return $this->_call_soap('GetObjectName', array(
            'sessionID' => $session_id,
            'objectID' => $object_id
        ));
    }
}
