<?php
// Получаем статус подключения из переменных, переданных в представление
$connection_status = isset($OpenSession) ? $OpenSession : 'Неизвестно';

// Явно определяем состояние подключения по переменным из контроллера
$is_connected = isset($connection_state) ? $connection_state : false;
$has_session = isset($session_id) && !empty($session_id);
$has_error = isset($connection_error) && !empty($connection_error);
$has_auth_error = isset($auth_error) && !empty($auth_error);

// Определяем общий статус панели
if ($has_error) {
    $status_class = 'panel-danger';
    $status_icon = 'glyphicon-remove-circle';
    $status_text = 'ОШИБКА ПОДКЛЮЧЕНИЯ';
} elseif ($has_auth_error) {
    $status_class = 'panel-danger';
    $status_icon = 'glyphicon-remove-circle';
    $status_text = 'ОШИБКА АВТОРИЗАЦИИ';
} elseif ($has_session) {
    $status_class = 'panel-success';
    $status_icon = 'glyphicon-ok-circle';
    $status_text = 'ПОДКЛЮЧЕНО';
} else {
    $status_class = 'panel-warning';
    $status_icon = 'glyphicon-warning-sign';
    $status_text = 'СЕССИЯ НЕ ОТКРЫТА';
}

// Определяем значения для каждой строки
if ($has_error) {
    // Нет подключения - сессия и авторизация пустые
    $connection_status_text = 'НЕТ';
    $session_status_text = '—';
    $auth_status_text = '—';
} elseif ($has_auth_error) {
    // Есть подключение, но ошибка авторизации
    $connection_status_text = 'ЕСТЬ';
    $session_status_text = '—';
    $auth_status_text = 'НЕТ (ошибка)';
} elseif ($has_session) {
    // Все хорошо
    $connection_status_text = 'ЕСТЬ';
    $session_status_text = 'ЕСТЬ (ID: ' . $session_id . ')';
    $auth_status_text = 'УСПЕШНО';
} else {
    // Подключение есть, но сессия не открыта
    $connection_status_text = 'ЕСТЬ';
    $session_status_text = 'НЕТ';
    $auth_status_text = '—';
}

$mock_mode = isset($soapConfig['mock_mode']) && $soapConfig['mock_mode'] === true;
?>

<!-- ===== ШАПКА В САМОМ НАЧАЛЕ ===== -->
<?php echo View::factory('parsec/_nav'); ?>
<!-- ===== КОНЕЦ ШАПКИ ===== -->

<?php if ($mock_mode): ?>
<div class="alert alert-warning" style="margin-bottom: 20px; border-left: 5px solid #f0ad4e;">
    <strong><span class="glyphicon glyphicon-flash"></span> РЕЖИМ MOCK:</strong> 
    Реальный SOAP-сервер не используется. Все данные генерируются для отладки.
    <br><small>Отключите в <code>modules/parsec/config/soap.php</code> параметр <code>mock_mode</code></small>
</div>
<?php endif; ?>


<!-- БЛОК СОСТОЯНИЯ ПОДКЛЮЧЕНИЯ -->
<div class="panel <?php echo $status_class; ?>" style="margin-bottom: 20px;">
    <div class="panel-heading">
        <h3 class="panel-title">
            <span class="glyphicon <?php echo $status_icon; ?>"></span>
            Состояние подключения к Parsec
        </h3>
    </div>
    <div class="panel-body">
        <table class="table table-bordered table-condensed" style="margin-bottom: 0; width: auto;">
            <tr>
                <td width="200"><strong>Подключение:</strong></td>
                <td>
                    <span class="label <?php echo ($connection_status_text == 'ЕСТЬ') ? 'label-success' : 'label-danger'; ?>" style="font-size: 14px; padding: 5px 15px;">
                        <?php echo $connection_status_text; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Сессия:</strong></td>
                <td>
                    <?php if ($session_status_text == '—'): ?>
                        <span class="text-muted">—</span>
                    <?php elseif (strpos($session_status_text, 'ЕСТЬ') !== false): ?>
                        <span class="text-success"><?php echo htmlspecialchars($session_status_text); ?></span>
                    <?php else: ?>
                        <span class="text-danger"><?php echo htmlspecialchars($session_status_text); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Авторизация:</strong></td>
                <td>
                    <?php if ($auth_status_text == '—'): ?>
                        <span class="text-muted">—</span>
                    <?php elseif ($auth_status_text == 'УСПЕШНО'): ?>
                        <span class="text-success"><?php echo $auth_status_text; ?></span>
                    <?php else: ?>
                        <span class="text-danger"><?php echo $auth_status_text; ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php if ($has_error && $connection_error): ?>
            <tr>
                <td><strong>Детали ошибки:</strong></td>
                <td><span class="text-danger"><?php echo htmlspecialchars($connection_error); ?></span></td>
            </tr>
            <?php endif; ?>
            <?php if ($has_auth_error && $auth_error): ?>
            <tr>
                <td><strong>Детали ошибки:</strong></td>
                <td><span class="text-danger"><?php echo htmlspecialchars($auth_error); ?></span></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<!-- БЛОК ПАРАМЕТРОВ ПОДКЛЮЧЕНИЯ -->

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo __('connectionString');?></h3>
    </div>
    <div class="panel-body">
        <p><?php echo __('connectionStringDescription');?></p>
        
        <?php if (isset($soapConfig) && is_array($soapConfig)): ?>
            <table class='table table-bordered table-condensed' style="font-family: monospace; font-size: 12px;">
                <tr>
                    <th width="200">Параметр</th>
                    <th>Значение</th>
                </tr>
                <tr>
                    <td>wsdl</td>
                    <td><?php echo htmlspecialchars($soapConfig['wsdl']); ?></td>
                </tr>
                <tr>
                    <td>domain</td>
                    <td><?php echo htmlspecialchars($soapConfig['domain'] ?: '(пусто)'); ?></td>
                </tr>
                <tr>
                    <td>username</td>
                    <td><?php echo htmlspecialchars($soapConfig['username']); ?></td>
                </tr>
                <tr>
                    <td>password</td>
                    <td><?php echo str_repeat('*', strlen($soapConfig['password'])); ?></td>
                </tr>
                <tr>
                    <td>connection_timeout</td>
                    <td><?php echo $soapConfig['connection_timeout']; ?> сек</td>
                </tr>
            </table>
        <?php elseif (is_string($soapConfig)): ?>
            <div class="alert alert-warning">
                <?php echo htmlspecialchars($soapConfig); ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Конфигурация не загружена.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- БЛОК СОДЕРЖИМОЕ ФАЙЛА КОНФИГУРАЦИИ -->

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo __('connectionString');?></h3>
    </div>
    <div class="panel-body">
        <p><?php echo __('connectionStringDescription');?></p>
        
        <?php
        $config_file = DOCROOT . 'modules/parsec/config/soap.php';
        
        if (file_exists($config_file)) {
            $content = file_get_contents($config_file);
            $lines = explode("\n", $content);
            $numbered_lines = '';
            foreach ($lines as $num => $line) {
                $line_num = str_pad($num + 1, 4, ' ', STR_PAD_LEFT);
                $numbered_lines .= $line_num . ' | ' . rtrim($line) . "\n";
            }
            ?>
            
            <div style="position: relative;">
                <button onclick="copyToClipboard()" style="position: absolute; top: 5px; right: 5px; z-index: 10; padding: 5px 10px; background: #337ab7; color: white; border: none; border-radius: 3px; cursor: pointer;">
                    Копировать
                </button>
                <pre id="config-content" style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace; font-size: 12px; max-height: 500px; margin-top: 10px;"><?php echo htmlspecialchars($numbered_lines); ?></pre>
            </div>
            
            <script>
            function copyToClipboard() {
                var pre = document.getElementById('config-content');
                var range = document.createRange();
                range.selectNode(pre);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                window.getSelection().removeAllRanges();
                alert('Конфигурация скопирована в буфер обмена');
            }
            </script>
            
        <?php } else { ?>
            <div class="alert alert-danger">Файл конфигурации не найден: <?php echo $config_file; ?></div>
        <?php } ?>
    </div>
</div>

<!-- БЛОК КАТЕГОРИЙ ДОСТУПА-->

<div class="panel panel-primary">

  <div class="panel-heading">
    <h3 class="panel-title"><?php echo __('info')?></h3>
  </div>
  <div class="panel-body">
    <table class='table table-striped'>
      <tr>
        <th><?php echo __('№ п/п')?></th>
        <th><?php echo __('param')?></th>
        <th><?php echo __('Значение')?></th>
      </tr>
      <?php $i=1; ?>
      <tr>
        <td><?php echo $i++;?></td>
        <td>version</td>
        <td><?php echo is_string($version) ? htmlspecialchars($version) : Debug::vars($version); ?></td>
      </tr>
    
      <tr>
        <td><?php echo $i++;?></td>
        <td>GetAccessGroups<br>Список категорий доступа</td>
        <td>
          <?php 
          if (is_string($GetAccessGroups)) {
              echo htmlspecialchars($GetAccessGroups);
          } elseif (is_object($GetAccessGroups) && property_exists($GetAccessGroups, 'GetAccessGroupsResult')) {
              foreach ($GetAccessGroups->GetAccessGroupsResult as $var) {
                  $ii=0;
                  ?>
                  <table class='table table-striped'>
                    <tr>
                      <th><?php echo __('№ п/п')?></th>
                      <th><?php echo __('GUID')?></th>
                      <th><?php echo __('NAME_in_PARSEC')?></th>
                      <th><?php echo __('IDENTIFTYPE')?></th>
                      <th><?php echo __('NAME_in_ARTONIT')?></th>
                      <th><?php echo __('ToDo')?></th>
                    </tr>
                    <?php foreach ($var as $list) { ?>
                      <tr>
                        <td><?php echo ++$ii;?></td>
                        <td><?php echo $list->ID;?></td>
                        <td><?php echo htmlspecialchars($list->NAME);?></td>
                        <td><?php echo $list->IDENTIFTYPE;?></td>
                        <td><?php 
                          $artonitName = Arr::get(Arr::get($getAccessArtonit, $list->ID), 'name');
                          echo $artonitName ? iconv('windows-1251', 'UTF-8', $artonitName) : '—';
                        ?></td>
                        <td>
                          <?php 
                          if ($getAccessArtonit && array_key_exists($list->ID, $getAccessArtonit)) {
                              echo 'Уже есть';
                          } else {
                              echo Form::open();
                              echo Form::hidden('guid', $list->ID);
                              echo Form::hidden('name', $list->NAME);
                              echo Form::submit(NULL, __('addAccessName'));
                              echo Form::close();
                          }
                          ?>
                        </td>
                      </tr>
                    <?php } ?>
                  </table>
              <?php }
          } else {
              echo 'Нет данных или ошибка';
          }
          ?>
        </td>
      </tr>
    </table>
  </div>
</div>