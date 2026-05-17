<? //http://itchief.ru/lessons/bootstrap-3/30-bootstrap-3-tables;
// страница отображения данных по парковочной системе
//echo Debug::vars('3', $task_list);
echo Form::open('parsec/parsec_control');
?>
<fieldset>
    <legend><?php echo __('parsec_about'); ?></legend>
    <?php echo __('parsec_legend'); ?>
    
</fieldset>
<?php

		$e_mess=Validation::Factory(Session::instance()->as_array())
				->rule('e_mess','is_array')
				->rule('e_mess','not_empty')
				;
		
		if($e_mess->check())
		{
	
			$param='Yes message<br>';
			
			foreach(Arr::get($e_mess, 'e_mess') as $key=>$value)
			{
				$param.=$value.'<br>';
			}
			?>
			<div id="my-alert" class="alert alert-danger alert-dismissible" role="alert">
				<?php 
					echo $param;
				?>
				
				<button type="button" class="close" data-dismiss="alert" aria-label="Close">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<?php
			
		} else 
		{
			
			
		}
		Session::instance()->delete('e_mess');
?>


			
			
<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?echo __('Список задач интегратора парсек :count', array(':count'=>count($task_list)))?></h3>
	</div>
	<div class="panel-body">
		<?php
		
		$operatiion_name=array(
		'1'=>'add_card',
		'2'=>'del_card',
		'3'=>'add_people',
		'4'=>'del_card',
		'5'=>'add_org',
		'6'=>'del_org',
		'7'=>'add_access',
		'8'=>'del_access',
		);
		if(isset($task_list))
		{
				?>
			<table class="table table-striped table-hover table-condensed">


					<tr>
						<th><?php echo __('id_cardindev');?></th>
						<th><?php echo __('GUID');?></th>
						<th><?php echo __('id_pep');?></th>
						<th><?php echo __('operation');?></th>
						<th><?php echo __('С кем связана операция');?></th>
						<th><?php echo __('attempt');?></th>
						<th><?php echo __('для кого');?></th>
						<th><?php echo __('timestamp');?></th>
						<th><?php echo __('todo');?></th>
					
						
						
						
					</tr>
					<?php 
					$i=0;
					$checked='no';
					foreach($task_list as $key=>$value)
					{
						echo '<tr>';
					//echo Debug::vars('74', $value);
							echo '<td>'.Form::hidden('id_cardindev['.Arr::get($value,'ID').']').Arr::get($value,'ID').'</td>';
							echo '<td>'.Arr::get($value,'ID_CARD');
								if(Arr::get($value,'OPERATION') == 2) echo ' ('.dechex(Arr::get($value,'ID_CARD')).')';
							echo '</td>';
							echo '<td>'.Arr::get($value,'ID_PEP').'</td>';
							echo '<td>'.Arr::get($operatiion_name, Arr::get($value,'OPERATION')).' ('.Arr::get($value,'OPERATION').')</td>';
							echo '<td>'.iconv('windows-1251','UTF-8', Arr::get($value,'ORG_NAME')).'</td>';
							echo '<td>'.Arr::get($value,'ATTEMPTS').'</td>';
							echo '<td>'.iconv('windows-1251','UTF-8', Arr::get($value,'DEST')).'</td>';
							echo '<td>'.Arr::get($value,'TIME_STAMP').'</td>';
							echo '<td>'
								.HTML::anchor('parsec/repeat/'.Arr::get($value,'ID'), 'Repeat').' '
								.HTML::anchor('parsec/delete/'.Arr::get($value,'ID'), 'delete')
								
								.'</td>';
						echo '</tr>';	
						$i++;
					}
					
					?>
				</table>		
						
				
					<?php
					
		}			
			echo Form::button('todo', 'RESTART ALL TASK', array('value'=>'set_attempt','class'=>'btn btn-warning', 'type' => 'submit', 'onclick'=>'return confirm(\''.__('restart_all_task_parsec').'\') ? true : false;'));	
			echo Form::button('todo', 'DELETE ALL TASKS', array('value'=>'delAllTasks','class'=>'btn btn-danger', 'type' => 'submit', 'onclick'=>'return confirm(\''.__('delete_all_task_parsec').'\') ? true : false;'));
			
		?>
</div>
</div>





  <?echo Form::close();?>
<script>
    $(function(){
        window.setTimeout(function(){
            $('#my-alert').alert('close');
        },5000);
    });
</script>
  