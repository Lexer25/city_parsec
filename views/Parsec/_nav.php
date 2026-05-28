<?php
// Определяем текущий URI и экшен для подсветки активного пункта
$current_uri = Request::current()->uri();
$current_action = Request::current()->action();
?>
<div class="panel panel-default" style="margin-bottom: 20px;">
    <div class="panel-body">
        <ul class="nav nav-pills">
            <li role="presentation" class="<?php echo ($current_uri == 'parsec') ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('parsec'); ?>">
                    <span class="glyphicon glyphicon-tasks"></span> Контроль задач
                </a>
            </li>
            <li role="presentation" class="<?php echo ($current_uri == 'cch' && $current_action == 'index') ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('cch'); ?>">
                    <span class="glyphicon glyphicon-cog"></span> Настройки
                </a>
            </li>
            <li role="presentation" class="<?php echo ($current_uri == 'cch/search') ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('cch/search'); ?>">
                    <span class="glyphicon glyphicon-search"></span> Поиск / Конфигурация
                </a>
            </li>
        </ul>
    </div>
</div>