<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Список задач для веб-запуска.
 *
 * Каждая запись — одна строка в таблице формы.
 * Поля:
 *   title   — как подписать задачу в таблице
 *   task    — имя Minion-таска (без префикса --task=)
 *   params  — ассоциативный массив опций Minion (без префикса --)
 *   group   — необязательная метка для группировки/фильтрации
 */
return array(

    // ---- Организации ----
    array(
        'title'  => 'Сверка организаций (без задач)',
        'task'   => 'parsecSyncOrg',
        'params' => array('add' => 0),
        'group'  => 'Организации',
    ),
    array(
        'title'  => 'Сверка организаций (ставить задачи)',
        'task'   => 'parsecSyncOrg',
        'params' => array('add' => 1),
        'group'  => 'Организации',
    ),

    // ---- Персоны ----
    array(
        'title'  => 'Сверка персон — первые 100 (без задач)',
        'task'   => 'parsecSyncPeope',
        'params' => array('limit' => 100, 'add' => 0),
        'group'  => 'Персоны',
    ),
    array(
        'title'  => 'Сверка персон — первые 100 (ставить задачи)',
        'task'   => 'parsecSyncPeope',
        'params' => array('limit' => 100, 'add' => 1),
        'group'  => 'Персоны',
    ),
    array(
        'title'  => 'Сверка персон — все (ставить задачи)',
        'task'   => 'parsecSyncPeope',
        'params' => array('add' => 1),
        'group'  => 'Персоны',
    ),
    array(
        'title'  => 'Персона 1291 — сверка',
        'task'   => 'parsecSyncPeope',
        'params' => array('id_pep' => 1291, 'add' => 0),
        'group'  => 'Персоны',
    ),
    array(
        'title'  => 'Персона 1291 — сверка + задачи',
        'task'   => 'parsecSyncPeope',
        'params' => array('id_pep' => 1291, 'add' => 1),
        'group'  => 'Персоны',
    ),
    array(
        'title'  => 'Персона 1291 — сменить GUID',
        'task'   => 'parsecChangeGuid',
        'params' => array('id_pep' => 1291, 'dry_run' => 0),
        'group'  => 'Персоны',
    ),

    // ---- Идентификаторы ----
    array(
        'title'  => 'Сверка идентификаторов (без задач)',
        'task'   => 'parsecSyncIdentifiers',
        'params' => array('add' => 0),
        'group'  => 'Идентификаторы',
    ),
    array(
        'title'  => 'Сверка идентификаторов (ставить задачи)',
        'task'   => 'parsecSyncIdentifiers',
        'params' => array('add' => 1),
        'group'  => 'Идентификаторы',
    ),

    // ---- Категории доступа ----
    array(
        'title'  => 'Сверка категорий доступа (без задач)',
        'task'   => 'parsecSyncAccessGroups',
        'params' => array('add' => 0),
        'group'  => 'Категории',
    ),
    array(
        'title'  => 'Сверка категорий доступа (ставить задачи)',
        'task'   => 'parsecSyncAccessGroups',
        'params' => array('add' => 1),
        'group'  => 'Категории',
    ),

    // ---- Триггеры ----
    array(
        'title'  => 'Тест триггеров — все (без очистки)',
        'task'   => 'parsecTestTriggers',
        'params' => array('scenario' => 'all_triggers', 'cleanup' => 0),
        'group'  => 'Триггеры',
    ),
    array(
        'title'  => 'Тест триггеров — basic (с очисткой)',
        'task'   => 'parsecTestTriggers',
        'params' => array('scenario' => 'basic', 'cleanup' => 1),
        'group'  => 'Триггеры',
    ),

    // ---- Поиск ----
    array(
        'title'  => 'Поиск GUID: 441dc23b-1111-44d2-a999-1379ff028067',
        'task'   => 'parsecSearchGuid',
        'params' => array('guid' => '441dc23b-1111-44d2-a999-1379ff028067'),
        'group'  => 'Поиск',
    ),
);