<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Заглушка SOAP-клиента для режима mock (PHP 5.6 compatible)
 * Имитирует работу SOAP-сервера Parsec
 */
class MockSoapClient
{
    protected $_wsdl;
    protected $_options;
    protected $_last_method;
    
    public function __construct($wsdl, $options = array())
    {
        $this->_wsdl = $wsdl;
        $this->_options = $options;
        Kohana::$log->add(Log::INFO, 'MockSoapClient инициализирован (mock режим)');
    }
    
    public function __call($method, $arguments)
    {
        $this->_last_method = $method;
        Kohana::$log->add(Log::DEBUG, "MockSoapClient: {$method} вызван с параметрами: " . print_r($arguments, true));
        
        $result = $this->_generateResponse($method, $arguments);
        Kohana::$log->add(Log::DEBUG, "MockSoapClient: ответ для {$method}: " . print_r($result, true));
        
        return $result;
    }
    
    protected function _generateResponse($method, $arguments)
    {
        $session_id = isset($arguments[0]['sessionID']) ? $arguments[0]['sessionID'] : null;
        
        switch ($method) {
            case 'GetVersion':
                return (object) array(
                    'GetVersionResult' => 'Mock Parsec v1.0 (режим отладки)'
                );
                
            case 'OpenSession':
                return (object) array(
                    'OpenSessionResult' => (object) array(
                        'Result' => 0,
                        'ErrorMessage' => '',
                        'Value' => (object) array(
                            'SessionID' => 'mock-session-' . uniqid()
                        )
                    )
                );
                
            case 'GetDomains':
                return (object) array(
                    'GetDomainsResult' => array(
                        (object) array('ID' => 'mock-domain-1', 'NAME' => 'Тестовый домен')
                    )
                );
                
            case 'GetRootOrgUnit':
                return (object) array(
                    'GetRootOrgUnitResult' => (object) array(
                        'ID' => '00000000-0000-0000-0000-000000000001',
                        'NAME' => 'Корневая организация (mock)',
                        'PARENT_ID' => null
                    )
                );
                
            case 'GetOrgUnitsHierarhy':
                return (object) array(
                    'GetOrgUnitsHierarhyResult' => (object) array(
                        'ID' => '00000000-0000-0000-0000-000000000001',
                        'NAME' => 'Иерархия (mock)',
                        'CHILDREN' => array()
                    )
                );
                
            case 'GetAccessGroups':
                return (object) array(
                    'GetAccessGroupsResult' => array(
                        array(
                            (object) array(
                                'ID' => '2ca7501c-731b-462f-bba4-87f76628f28a',
                                'NAME' => 'Администраторы (mock)',
                                'IDENTIFTYPE' => 1
                            ),
                            (object) array(
                                'ID' => '3ca7501c-731b-462f-bba4-87f76628f28b',
                                'NAME' => 'Сотрудники (mock)',
                                'IDENTIFTYPE' => 1
                            ),
                            (object) array(
                                'ID' => '4ca7501c-731b-462f-bba4-87f76628f28c',
                                'NAME' => 'Гости (mock)',
                                'IDENTIFTYPE' => 2
                            ),
                            (object) array(
                                'ID' => '5ca7501c-731b-462f-bba4-87f76628f28d',
                                'NAME' => 'Руководство (mock)',
                                'IDENTIFTYPE' => 1
                            )
                        )
                    )
                );
                
            case 'GetPerson':
                $person_id = isset($arguments[0]['personID']) ? $arguments[0]['personID'] : 'unknown';
                return (object) array(
                    'GetPersonResult' => (object) array(
                        'ID' => $person_id,
                        'NAME' => 'Тестовый',
                        'SURNAME' => 'Пользователь',
                        'PATRONYMIC' => 'Mockovich',
                        'BIRTHDAY' => '1990-01-01',
                        'ORG_UNIT_ID' => '00000000-0000-0000-0000-000000000001'
                    )
                );
                
            case 'GetPersonIdentifiers':
                return (object) array(
                    'GetPersonIdentifiersResult' => array(
                        (object) array(
                            'CODE' => '00AABBCC',
                            'IDENTIFIER_TYPE' => 1,
                            'IS_PRIMARY' => true
                        ),
                        (object) array(
                            'CODE' => '00DDEEFF',
                            'IDENTIFIER_TYPE' => 2,
                            'IS_PRIMARY' => false
                        )
                    )
                );
                
            case 'FindPersonByIdentifier':
                $card_code = isset($arguments[0]['cardCode']) ? $arguments[0]['cardCode'] : 'unknown';
                return (object) array(
                    'FindPersonByIdentifierResult' => (object) array(
                        'ID' => 'mock-person-' . md5($card_code),
                        'NAME' => 'Найден по карте ' . $card_code,
                        'SURNAME' => 'Mock',
                        'ORG_UNIT_ID' => '00000000-0000-0000-0000-000000000001'
                    )
                );
                
            case 'GetOrgUnit':
                $org_id = isset($arguments[0]['orgUnitID']) ? $arguments[0]['orgUnitID'] : 'unknown';
                return (object) array(
                    'GetOrgUnitResult' => (object) array(
                        'ID' => $org_id,
                        'NAME' => 'Тестовая организация (mock)',
                        'PARENT_ID' => null
                    )
                );
                
            case 'OpenPersonEditingSession':
                return (object) array(
                    'OpenPersonEditingSessionResult' => (object) array(
                        'Result' => 0,
                        'ErrorMessage' => '',
                        'Value' => 'mock-edit-session-' . uniqid()
                    )
                );
                
            case 'AddPersonIdentifier':
                return (object) array(
                    'AddPersonIdentifierResult' => (object) array(
                        'Result' => 0,
                        'ErrorMessage' => '',
                        'Value' => null
                    )
                );
                
            case 'DeleteIdentifier':
                return (object) array(
                    'DeleteIdentifierResult' => (object) array(
                        'Result' => 0,
                        'ErrorMessage' => '',
                        'Value' => null
                    )
                );
                
            case 'DeletePerson':
                return (object) array(
                    'DeletePersonResult' => (object) array(
                        'Result' => 0,
                        'ErrorMessage' => '',
                        'Value' => null
                    )
                );
                
            case 'GetInheritedAccessGroups':
                return (object) array(
                    'GetInheritedAccessGroupsResult' => array(
                        (object) array(
                            'ID' => 'child-access-1',
                            'NAME' => 'Дочерняя категория (mock)',
                            'IDENTIFTYPE' => 1
                        )
                    )
                );
                
            case 'GetIdentifierExtraData':
                return (object) array(
                    'GetIdentifierExtraDataResult' => (object) array(
                        'CARD_TYPE' => 'MIFARE',
                        'START_DATE' => '2024-01-01',
                        'END_DATE' => '2025-12-31'
                    )
                );
                
            case 'GetObjectName':
                $object_id = isset($arguments[0]['objectID']) ? $arguments[0]['objectID'] : 'unknown';
                return (object) array(
                    'GetObjectNameResult' => 'Тип объекта: ' . substr($object_id, 0, 8) . ' (mock)'
                );
                
            default:
                Kohana::$log->add(Log::WARNING, "MockSoapClient: неизвестный метод '{$method}'");
                return (object) array(
                    $method . 'Result' => null
                );
        }
    }
}
