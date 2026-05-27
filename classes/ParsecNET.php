<?php defined('SYSPATH') OR die('No direct access allowed.');
/**
 *29.11.2025 Класс для работы с модулем интеграции Парсек
 *
 */
abstract class ParsecNET {
	
	public $domain='';
	public $userName ='parsec';
	public $password = 'parsec';
	public $url = 'http://192.168.10.5:10101/IntegrationService/IntegrationService.asmx?wsdl';
	
	public function before()
	{
	
		parent::before();
		
		

	}	
	
	public function __construct($config = array())
	{
		$client = new SoapClient($this->url);
		$this->_OpenSession();
	}
	
	
	public function _getSOAP_skud_wsdl()//формирую подключение к soap
	{
			return new SoapClient('http://192.168.10.5:10101/IntegrationService/IntegrationService.asmx?wsdl');		  

		
	
	}
	
	
	public function _OpenSession()//19.11.2025 открыть сессию
	{
		$client=$this->getSOAP_skud_wsdl();
		
		return $client->OpenSession(array($this->domain, $this->userName, $this->password));
		
		return;
	}
	
	//public function getSOAPVersion()
	public function getParsecSoapVersion()//19.11.2025
	{
		$client=$this->getSOAP_skud_wsdl();
		$a= $client->GetVersion()->GetVersionResult;
		return $a;
	}
	
	
} 
