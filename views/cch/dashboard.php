<?php //echo Debug::vars('1', $version); exit;?>
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
        <td>OpenSession</td>
        <td><?php echo htmlspecialchars($OpenSession); ?></td>
      </tr>
      <tr>
        <td><?php echo $i++;?></td>
        <td>GetDomains</td>
        <td><?php 
          if (is_string($GetDomains)) {
              echo htmlspecialchars($GetDomains);
          } else {
              echo Debug::vars($GetDomains);
          }
        ?></td>
      </tr>
      <tr>
        <td><?php echo $i++;?></td>
        <td>GetRootOrgUnit</td>
        <td><?php 
          if (is_string($GetRootOrgUnit)) {
              echo htmlspecialchars($GetRootOrgUnit);
          } else {
              echo Debug::vars($GetRootOrgUnit);
          }
        ?></td>
      </tr>
      <tr>
        <td><?php echo $i++;?></td>
        <td>GetOrgUnitsHierarhy</td>
        <td><?php echo is_string($GetOrgUnitsHierarhy) ? htmlspecialchars($GetOrgUnitsHierarhy) : Debug::vars($GetOrgUnitsHierarhy); ?></td>
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
                      <th><?php echo __('ID')?></th>
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
                              echo Form::submit(NULL, 'addAccessName');
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