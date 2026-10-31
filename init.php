<?php defined('SYSPATH') or die('No direct script access.');
defined('PARSEC_VERSION') OR define('PARSEC_VERSION', '2.0.13');


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
            'tasks2' => array(
                'title' => 'Контроль новый',
                'url' => 'parsec2'
            ),
            'setting' => array(
                'title' => 'Настройки',
                'url' => 'cch'
            ),
			'config' => array(
                'title' => 'Отладка',
                'url' => 'cch/search'
            ),
			'parsecrunner' => array(
                'title' => 'Задачи',
                'url' => 'parsecrunner'
            ),
			'ParsecTest' => array(
                'title' => 'ParsecTest',
                'url' => 'ParsecTest'
            )
			
        )
    ));
	
	
Route::set('parsec_test', 'parsectest(/<action>)')
    ->defaults(array(
        'controller' => 'ParsecTest',
        'action'     => 'index',
    ));
	
	
Route::set('parsec2', 'parsec2(/<action>(/<id>))')
    ->defaults(array(
        'controller' => 'Parsec2',
        'action'     => 'index',
    ));
	
	
Route::set('parsec_runner', 'parsecrunner(/<action>(/<id>))')
    ->defaults(array(
        'controller' => 'ParsecRunner',
        'action'     => 'index',
    ));