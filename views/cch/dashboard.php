<?php //echo Debug::vars('1', $version); exit;?>
<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo __('connectionString')?></h3>
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
		<div class="panel panel-primary">
			<div class="panel-heading">
				<h3 class="panel-title"><?php echo __('connectionString')?></h3>
			</div>
		</div>
    </div>
</div>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo __('connectionString')?></h3>
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

</div>
<div class="panel panel-primary">
<?php echo View::factory('parsec/_nav'); ?>
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