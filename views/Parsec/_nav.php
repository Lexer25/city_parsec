<?php
// Определяем текущий URI и экшен для подсветки активного пункта
$current_uri = Request::current()->uri();
$current_action = Request::current()->action();
$current_uri = trim((string) $current_uri, '/');
?>
<div class="panel panel-default" style="margin-bottom: 20px;">
    <div class="panel-body">
        <ul class="nav nav-pills">
            <li role="presentation" class="<?php echo ($current_uri == 'parsec') ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('parsec'); ?>">
                    <span class="glyphicon glyphicon-tasks"></span> Контроль задач
                </a>
            </li>
            <li role="presentation" class="<?php echo ($current_uri == 'parsec2' || strpos($current_uri, 'parsec2/') === 0) ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('parsec2'); ?>">
                    <span class="glyphicon glyphicon-list-alt"></span> Контроль новый (очередь)
                </a>
            </li>
            <li role="presentation" class="<?php echo ($current_uri == 'cch' && $current_action == 'index') ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('cch'); ?>">
                    <span class="glyphicon glyphicon-cog"></span> Настройки
                </a>
            </li>
            <li role="presentation" class="<?php echo ($current_uri == 'cch/search') ? 'active' : ''; ?>">
                <a href="<?php echo URL::site('cch/search'); ?>">
                    <span class="glyphicon glyphicon-search"></span> Отладка
                </a>
            </li>
			<li role="presentation" class="<?php echo ($current_uri == 'parsecrunner') ? 'active' : ''; ?>">
				<a href="<?php echo URL::site('parsecrunner'); ?>">
					<span class="glyphicon glyphicon-play-circle"></span> Задачи
				</a>
			</li>
			
        </ul>
    </div>
</div>