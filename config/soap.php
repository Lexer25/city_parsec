<?php defined('SYSPATH') OR die('No direct script access.');

return array(
    'parsec' => array(
        'wsdl' => 'http://127.0.0.1:10101/IntegrationService/IntegrationService.asmx?wsdl',
        'domain' => '',
        'username' => 'parsec',
        'password' => 'parsec',
        'connection_timeout' => 10,
        'soap_options' => array(
            'trace' => 1,
            'exceptions' => 1,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
            'encoding' => 'UTF-8'
        )
    )
);