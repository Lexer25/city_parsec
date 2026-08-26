<?php defined('SYSPATH') or die('No direct script access.');
defined('PARSEC_VERSION') OR define('PARSEC_VERSION', '2.0.5');


Kohana::$config->load('menu')
    ->set('parsec', array(
        'title' => 'Parsec',
        'url' => '/parsec',
        'icon' => 'fa-cog',
        'order' => 300,
		'disabled' => false, 
        'children' => array(
            'tasks' => array(
                'title' => 'Контроль',
                'url' => 'parsec'
            ),
            'setting' => array(
                'title' => 'Настройки',
                'url' => 'cch'
            ),
			'config' => array(
                'title' => 'Конфигурация',
                'url' => 'cch/search'
            )
			
        )
    ));