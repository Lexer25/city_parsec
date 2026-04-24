<!-- ФОРМА ДЛЯ ОТОБРАЖЕНИЯ СОДЕРЖИМОГО STATE.TXT -->
<div class="panel panel-info" style="margin-bottom: 20px;">
    <div class="panel-heading">
        <h3 class="panel-title">
            <span class="glyphicon glyphicon-info-sign"></span>
            <?php echo __('Состояние системы (state.txt)'); ?>
        </h3>
    </div>
    <div class="panel-body">
        <?php if (!empty($service_state['error'])): ?>
            <div class="alert alert-warning alert-dismissible" role="alert">
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <span class="glyphicon glyphicon-exclamation-sign"></span>
                <?php echo htmlspecialchars($service_state['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php else: ?>
            <div class="well well-sm" style="max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; background-color: #f5f5f5; margin-bottom: 0;">
                <pre style="margin: 0; padding: 10px; border: none; background: transparent;"><?php echo htmlspecialchars($service_state['content'], ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
            <small class="text-muted" style="display: block; margin-top: 10px;">
                <span class="glyphicon glyphicon-stats"></span> 
                Размер: <?php echo number_format(strlen($service_state['content'])); ?> байт
                &nbsp;|&nbsp;
                <span class="glyphicon glyphicon-calendar"></span> 
                Изменен: <?php echo $service_state['file_exists'] ? date('d.m.Y H:i:s', $service_state['file_mtime']) : 'недоступно'; ?>
            </small>
        <?php endif; ?>
    </div>
</div>

<fieldset>
    <legend><?php echo __('parsec_about'); ?></legend>
    <?php echo __('parsec_legend'); ?>
</fieldset>

<?php
$e_mess = Validation::Factory(Session::instance()->as_array())
    ->rule('e_mess', 'is_array')
    ->rule('e_mess', 'not_empty');

if ($e_mess->check()) {
    $param = 'Yes message<br>';
    foreach (Arr::get($e_mess, 'e_mess') as $key => $value) {
        $param .= $value . '<br>';
    }
    ?>
    <div id="my-alert" class="alert alert-danger alert-dismissible" role="alert">
        <?php echo $param; ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <?php
}
Session::instance()->delete('e_mess');
?>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">
            <?php echo __('Список задач интегратора парсек :count', array(':count' => count($task_list))); ?>
        </h3>
    </div>
    <div class="panel-body">

        <?php
        // Массив операций
        $operatiion_name = array(
            '1' => 'add_card',
            '2' => 'del_card',
            '3' => 'add_people',
            '4' => 'del_people',
            '5' => 'add_org',
            '6' => 'del_org',
            '7' => 'add_access',
            '8' => 'del_access',
        );
		
		$operatiion_name = array(
            '1' => 'Добавить идентификатор',
            '2' => 'Удалить идентификатора',
            '3' => 'Добавить контакта',
            '4' => 'Удалить контакт',
            '5' => 'Добавить организацию',
            '6' => 'Удалить организацию',
            '7' => 'Добавить категорию доступа',
            '8' => 'Удалить категорию доступа',
        );
		
		
        
        // Подготовка данных
        $raw_data = array();
        $unique_dests = array();
        
        if (isset($task_list) && is_array($task_list)) {
            
            // Сбор уникальных значений dest
            foreach ($task_list as $item) {
                $dest = iconv('windows-1251', 'UTF-8', Arr::get($item, 'DEST', ''));
                if (!empty($dest)) {
                    $unique_dests[$dest] = true;
                }
            }
            
            $unique_dests = array_keys($unique_dests);
            sort($unique_dests); // Сортировка для удобства
            
            foreach ($task_list as $item) {
                $raw_data[] = array(
                    'id'          => Arr::get($item, 'ID', ''),
                    'id_card'     => iconv('windows-1251', 'UTF-8', Arr::get($item, 'ID_CARD', '')),
                    'id_pep'      => iconv('windows-1251', 'UTF-8', Arr::get($item, 'ID_PEP', '')),
                    'operation'   => Arr::get($item, 'OPERATION', ''),
                    'operation_name' => Arr::get($operatiion_name, Arr::get($item, 'OPERATION', ''), 'unknown'),
                    'org_name'    => iconv('windows-1251', 'UTF-8', Arr::get($item, 'ORG_NAME', '')),
                    'attempts'    => Arr::get($item, 'ATTEMPTS', ''),
                    'dest'        => iconv('windows-1251', 'UTF-8', Arr::get($item, 'DEST', '')),
                    'timestamp'   => Arr::get($item, 'TIME_STAMP', ''),
                    'hex'         => (Arr::get($item, 'OPERATION', '') == 2) ? ' (0x' . dechex(Arr::get($item, 'ID_CARD', 0)) . ')' : ''
                );
            }
        }
        ?>

        <?php if (!empty($raw_data)): ?>

        <!-- Таблица с фильтрами -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-condensed tablesorter" id="parsec-table">
                <!-- Заголовки с двумя строками -->
                <thead>
                    <!-- Первая строка - названия колонок -->
                    <tr class="active">
                        <th class="text-center">ID</th>
                        <th class="text-center">Что</th>
                        <th class="text-center">Кому</th>
                        <th class="text-center">Операция</th>
                        <th class="text-center">Организация</th>
                        <th class="text-center">Попытки</th>
                        <th class="text-center">Для кого</th>
                        <th class="text-center">Дата</th>
                        <th class="text-center">Действия</th>
                    </tr>
                    <!-- Вторая строка - номера колонок -->
                    <tr class="info" style="font-size: 10px">
                        <th  class="text-center">1</th>
                        <th class="text-center">2</th>
                        <th class="text-center">3</th>
                        <th class="text-center">4</th>
                        <th class="text-center">5</th>
                        <th class="text-center">6</th>
                        <th class="text-center">7</th>
                        <th class="text-center">8</th>
                        <th class="text-center">9</th>
                    </tr>
                </thead>

                <!-- Строка фильтров – в отдельном tbody, чтобы tablesorter её игнорировал -->
                <tbody class="filters-row">
                    <tr>
                        <th><input type="text" class="form-control input-sm column-filter" data-column="0" placeholder="ID"></th>
                        <th><input type="text" class="form-control input-sm column-filter" data-column="1" placeholder="GUID"></th>
                        <th><input type="text" class="form-control input-sm column-filter" data-column="2" placeholder="ID_PEP"></th>
                        <th>
                            <select class="form-control input-sm column-filter" data-column="3" data-type="select">
                                <option value="">Все операции</option>
                                <?php foreach ($operatiion_name as $op_id => $op_name): ?>
                                <option value="<?php echo $op_id; ?>"><?php echo $op_name; ?> (<?php echo $op_id; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input type="text" class="form-control input-sm column-filter" data-column="4" placeholder="Организация"></th>
                        <th><input type="text" class="form-control input-sm column-filter" data-column="5" placeholder="Попытки"></th>
                        <th>
                            <select class="form-control input-sm column-filter" data-column="6" data-type="select">
                                <option value="">Все получатели</option>
                                <?php foreach ($unique_dests as $dest_value): ?>
                                    <option value="<?php echo htmlspecialchars($dest_value, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($dest_value, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </th>
                        <th><input type="text" class="form-control input-sm column-filter" data-column="7" placeholder="ГГГГ-ММ-ДД"></th>
                        <th></th>
                    </tr>
                </tbody>

                <!-- Основные данные -->
                <tbody>
                    <?php foreach ($raw_data as $row): ?>
                    <tr>
                        <td><?php echo Form::hidden('id_cardindev[' . $row['id'] . ']', $row['id']); ?><?php echo $row['id']; ?></td>
                        <td><?php echo $row['id_card']; ?></td>
                        <td><?php echo $row['id_pep']; ?></td>
                        <td><?php echo $row['operation_name'] . ' (' . $row['operation'] . ')'; ?></td>
                        <td><?php echo htmlspecialchars($row['org_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $row['attempts']; ?></td>
                        <td><?php echo htmlspecialchars($row['dest'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo $row['timestamp']; ?></td>
                        <td>
                            <a href="parsec/repeat/<?php echo $row['id']; ?>" class="btn btn-xs btn-success">Repeat</a>
                            <a href="parsec/delete/<?php echo $row['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('<?php echo __('Вы уверены?'); ?>') ? true : false;">delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php else: ?>
            <div class="alert alert-info"><?php echo __('Список задач пуст.'); ?></div>
        <?php endif; ?>

        <div class="form-group" style="margin-top: 15px;">
            <?php
            echo Form::button('todo', 'RESTART ALL TASK', array(
                'value' => 'set_attempt',
                'class' => 'btn btn-warning',
                'type' => 'submit',
                'onclick' => 'return confirm(\'' . __('restart_all_task_parsec') . '\') ? true : false;'
            ));
            echo Form::button('todo', 'DELETE ALL TASKS', array(
                'value' => 'delAllTasks',
                'class' => 'btn btn-danger',
                'type' => 'submit',
                'style' => 'margin-left: 10px;',
                'onclick' => 'return confirm(\'' . __('delete_all_task_parsec') . '\') ? true : false;'
            ));
            ?>
        </div>
    </div>
</div>

<?php echo Form::close(); ?>

<!-- Дополнительные стили для гарантии работоспособности фильтров -->
<style>
    /* Убедимся, что поля фильтров кликабельны и не перекрываются */
    .filters-row input,
    .filters-row select {
        pointer-events: auto !important;
        background-color: #ffffff !important;
        z-index: 10;
    }
    /* Небольшой отступ для строки фильтров */
    .filters-row th {
        vertical-align: middle;
        padding: 8px;
    }
</style>

<script>
$(document).ready(function() {
    // Инициализация tablesorter – сортируются только заголовки из первой строки <thead>
    $('#parsec-table').tablesorter({
        // Сортировка только по ячейкам th внутри первого <thead>
        selectorHeaders: 'thead tr:first-child th',
        // Отключаем автоматическую сортировку при клике на поля ввода
        cancelSelection: false,
        widgets: ['zebra']
    });

    // Функция фильтрации строк по значениям из полей
    function filterTable() {
        var filters = [];
        $('.column-filter').each(function() {
            var value = $(this).val().toLowerCase().trim();
            var column = $(this).data('column');
            filters[column] = value;
        });

        // Проходим по строкам данных (последний <tbody>)
        $('#parsec-table > tbody:last-child tr').each(function() {
            var show = true;
            $(this).find('td').each(function(index) {
                var filterValue = filters[index];
                if (filterValue && filterValue !== '') {
                    var cellText = $(this).text().toLowerCase();
                    
                    // Для колонки "Операция" (индекс 3) ищем код в скобках
                    if (index == 3) {
                        var opMatch = cellText.match(/\((\d+)\)/);
                        var opCode = opMatch ? opMatch[1] : '';
                        if (filterValue !== opCode) {
                            show = false;
                            return false;
                        }
                    } 
                    // Для колонки "Для кого" (индекс 6) прямое сравнение
                    else if (index == 6) {
                        if (cellText !== filterValue) {
                            show = false;
                            return false;
                        }
                    }
                    else {
                        if (cellText.indexOf(filterValue) === -1) {
                            show = false;
                            return false;
                        }
                    }
                }
            });
            $(this).toggle(show);
        });
    }

    // Вешаем обработчики на все поля фильтров
    $('.column-filter').on('keyup change', function() {
        filterTable();
    });
});
</script>