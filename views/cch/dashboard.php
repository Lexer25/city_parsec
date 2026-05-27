<?php //echo Debug::vars('1', $version); exit;?>
<div class="panel panel-primary">
  <div class="panel-heading">
    <h3 class="panel-title"><?echo __('info')?></h3>
  </div>
  

  <div class="panel-body">
   
	
	<table class='table table-striped'>
		<tr>
			<th><? echo __('№ п/п')?></th>
			<th><? echo __('param')?></th>
			<th><? echo __('Значение')?></th>
			
			
		</tr>
	<?
	$i=1;

	?>
		<tr>
			<td><?php echo $i++;?></td>
			<td><?php echo 'version';?></td>
			<td><?php echo $version; ?></td>
			</td>
		</tr>	
		
		<tr>
			<td><?php echo $i++;?></td>
			<td><?php echo 'OpenSession';?></td>
			<td><?php echo Debug::vars($OpenSession); ?></td>
			</td>
		</tr>	
		
		<tr>
			<td><?php echo $i++;?></td>
			<td><?php echo 'GetDomains';?></td>
			<td><?php echo Debug::vars($GetDomains); ?></td>
			</td>
		</tr>	
		
		<tr>
			<td><?php echo $i++;?></td>
			<td><?php echo 'GetRootOrgUnit';?></td>
			<td><?php echo Debug::vars($GetRootOrgUnit); ?></td>
			</td>
		</tr>	
		
		<tr>
			<td><?php echo $i++;?></td>
			<td><?php echo 'GetOrgUnitsHierarhy';?></td>
			<td><?php echo Debug::vars($GetOrgUnitsHierarhy); ?></td>
			</td>
		</tr>	
		
		<tr>
			<td><?php echo $i++;?></td>
			<td><?php echo 'GetAccessGroups<br>Список категорий доступа';?></td>
			<td><?php 
				//echo Debug::vars($GetAccessGroups); 
				foreach($GetAccessGroups->GetAccessGroupsResult  as $var){
					
					//echo Debug::vars('65', $var);
					$ii=0;
					?>
					<table class='table table-striped'>
						<tr>
							<th><? echo __('№ п/п')?></th>
							<th><? echo __('ID')?></th>
							<th><? echo __('NAME_in_PARSEC')?></th>
							<th><? echo __('IDENTIFTYPE')?></th>
							<th><? echo __('NAME_in_ARTONIT')?></th>
							<th><? echo __('ToDo')?></th>
							
							
						</tr>
					<?php
					foreach ($var as $list){
						?>
						<tr>
							<td><?php echo ++$ii;?></td>
							<td><?php echo $list->ID;?></td>
							<td><?php echo $list->NAME;?></td>
							<td><?php echo $list->IDENTIFTYPE;?></td>
							<td><?php echo iconv('windows-1251','UTF-8', Arr::get(Arr::get($getAccessArtonit, $list->ID), 'name'));?></td>
							<td><?php 
								if(array_key_exists($list->ID, $getAccessArtonit))
								{
									echo '111';
								} else {
									
									echo '222';
									echo Form::open();
										echo Form::hidden('guid', $list->ID);
										echo Form::hidden('name', $list->NAME);
										echo Form::submit(NULL, 'addAccessName');
									echo Form::close();
								}
								
								?></td>
							
							</td>
						</tr>	
					<?php }
				}
				
				?></td>
			</td>
		</tr>	
		
		

	</table>
	
  </div>
 </div>
