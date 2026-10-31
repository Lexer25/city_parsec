<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Карточка события PARSEC_QUEUE: все поля строки и попытки из
 * PARSEC_QUEUE_LOG.
 *
 * Ожидаемые переменные:
 *   array|null $event     строка PARSEC_QUEUE (Model_Parsec2::get_event)
 *   array      $attempts  строки PARSEC_QUEUE_LOG
 *   string     $error     текст ошибки БД (или NULL)
 *   string     $back      '?...' — возврат к отфильтрованному списку
 */
$statuses       = Model_Parsec2::$statuses;
$status_classes = Model_Parsec2::$status_classes;
$operations     = Model_Parsec2::$operations;
$status_hints   = Model_Parsec2::$status_hints;

$e = function ($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

$dt = function ($value) {
	$value = (string) $value;
	if (strlen($value) >= 19) {
		return substr($value, 8, 2).'.'.substr($value, 5, 2).'.'.substr($value, 0, 4)
			.' '.substr($value, 11, 8);
	}
	return $value;
};

$field = function ($label, $value, $raw = FALSE) use ($e) {
	echo '<dt>'.$e($label).'</dt><dd>';
	if ($value === NULL || $value === '') {
		echo '<span class="text-muted">—</span>';
	} elseif ($raw) {
		echo $value;
	} else {
		echo $e($value);
	}
	echo '</dd>';
};

$list_url = URL::site('parsec2').$back;
$back_value = ltrim((string) $back, '?');
?>

<?php echo View::factory('parsec/_nav'); ?>

<?php if ( ! empty($error)): ?>
	<div class="alert alert-danger">
		<span class="glyphicon glyphicon-exclamation-sign"></span> <?php echo $e($error); ?>
	</div>
<?php endif; ?>

<?php if ( ! empty($event)): ?>
	<?php
	$event_id = (int) $event['id'];
	$status = (int) $event['status'];
	$operation = (int) $event['operation'];
	$operation_name = isset($operations[$operation]) ? $operations[$operation] : 'Неизвестная операция';
	$error_text = isset($event['error_text']) ? (string) $event['error_text'] : '';
	?>

	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title">
				<span class="glyphicon glyphicon-file"></span>
				Событие #<?php echo $event_id; ?> —
				<?php echo $e($operation_name); ?> (код <?php echo $operation; ?>)
				<span class="label <?php echo $event['status_class']; ?>" style="margin-left:10px;"
					  title="<?php echo $e(isset($status_hints[$status]) ? $status_hints[$status] : ''); ?>">
					<?php echo $e($event['status_name']); ?>
				</span>
			</h3>
		</div>
		<div class="panel-body">
			<div class="row">
				<div class="col-md-6">
					<dl class="dl-horizontal" style="font-size:12px;">
						<?php $field('ID', $event['id']); ?>
						<?php $field('Операция', $operation.' — '.$operation_name); ?>
						<?php $field('Кому (id_pep)', $event['id_pep']); ?>
						<?php $field('ФИО', $event['person_name']); ?>
						<?php $field('Что (id_card)', $event['id_card']); ?>
						<?php $field('Организация', $event['org_name']); ?>
						<?php $field('Источник', $event['source_table']); ?>
						<?php $field('DEDUP_KEY', $event['dedup_key']); ?>
					</dl>
				</div>
				<div class="col-md-6">
					<dl class="dl-horizontal" style="font-size:12px;">
						<?php $field('Попытки', (int) $event['attempts'].' / '.(int) $event['max_attempts']); ?>
						<?php $field('Создано', $dt($event['created_at'])); ?>
						<?php $field('Обновлено', $dt($event['updated_at'])); ?>
						<?php $field('Следующая попытка', $dt($event['next_attempt'])); ?>
						<?php $field('Аренда (lease_until)', $dt($event['lease_until'])); ?>
						<?php $field('Начато', $dt($event['started_at'])); ?>
						<?php $field('Завершено', $dt($event['finished_at'])); ?>
						<?php $field('Длительность', $event['duration_ms'] !== NULL ? ((int) $event['duration_ms']).' мс' : ''); ?>
					</dl>
				</div>
			</div>

			<dl class="dl-horizontal" style="font-size:12px; margin-bottom:10px;">
				<?php $field('Исполнитель', $event['worker_id']); ?>
				<?php $field('Код ошибки', $event['error_code']); ?>
				<dt>Текст ошибки</dt>
				<dd>
					<?php if ($error_text === ''): ?>
						<span class="text-muted">—</span>
					<?php else: ?>
						<pre style="white-space:pre-wrap; word-break:break-word; margin:0; font-size:11px;"><?php echo $e($error_text); ?></pre>
					<?php endif; ?>
				</dd>
			</dl>

			<?php if ($status === 3 || $status === 0): ?>
				<div class="alert alert-warning" style="font-size:12px; margin-bottom:10px;">
					<?php if ($status === 3): ?>
						<span class="glyphicon glyphicon-time"></span>
						Событие со статусом 3 задерживает всю очередь: служба повторяет именно его, пока не
						исчерпает <code>MAX_ATTEMPTS</code> (<?php echo (int) $event['max_attempts']; ?>).
						Повтор сбрасывает счётчик; отмена снимает блокировку.
					<?php else: ?>
						<span class="glyphicon glyphicon-time"></span>
						Событие ждёт обработки службой.
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div style="margin-bottom:15px;">
				<?php if ($status === 3 || $status === 4): ?>
					<form method="post" action="<?php echo $e(URL::site('parsec2/repeat')); ?>" style="display:inline;"
						  onsubmit="return confirm('Вернуть событие #<?php echo $event_id; ?> в очередь? Счётчик попыток будет сброшен.');">
						<input type="hidden" name="id" value="<?php echo $event_id; ?>">
						<input type="hidden" name="back" value="<?php echo $e($back_value); ?>">
						<button type="submit" class="btn btn-sm btn-success">
							<span class="glyphicon glyphicon-refresh"></span> Повторить
						</button>
					</form>
				<?php endif; ?>

				<?php if ($status === 0 || $status === 3): ?>
					<form method="post" action="<?php echo $e(URL::site('parsec2/cancel')); ?>" style="display:inline;"
						  onsubmit="return confirm('Отменить событие #<?php echo $event_id; ?>? Оно получит статус 6 и больше не будет обработано.');">
						<input type="hidden" name="id" value="<?php echo $event_id; ?>">
						<input type="hidden" name="back" value="<?php echo $e($back_value); ?>">
						<button type="submit" class="btn btn-sm btn-danger">
							<span class="glyphicon glyphicon-ban-circle"></span> Отменить
						</button>
					</form>
				<?php endif; ?>

				<a href="<?php echo $e($list_url); ?>" class="btn btn-sm btn-default">
					<span class="glyphicon glyphicon-arrow-left"></span> К списку
				</a>
			</div>

			<h4 style="margin-top:0;">
				<span class="glyphicon glyphicon-list"></span>
				Попытки обработки (PARSEC_QUEUE_LOG): <?php echo count($attempts); ?>
			</h4>

			<?php if (empty($attempts)): ?>
				<div class="alert alert-info" style="margin-bottom:0;">
					Записей нет: событие ещё не обрабатывалось.
				</div>
			<?php else: ?>
				<div class="table-responsive">
					<table class="table table-striped table-condensed" style="font-size:11px;">
						<thead>
							<tr class="active">
								<th>ID</th>
								<th>Попытка</th>
								<th>Статус</th>
								<th>Начато</th>
								<th>Завершено</th>
								<th>Длит.</th>
								<th>Код</th>
								<th>Ошибка</th>
								<th>Исполнитель</th>
								<th>Детали</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($attempts as $attempt): ?>
								<?php
								$attempt_status = isset($attempt['status']) ? (int) $attempt['status'] : NULL;
								$attempt_details = isset($attempt['details']) ? (string) $attempt['details'] : '';
								$decoded = json_decode($attempt_details, TRUE);
								if (is_array($decoded)) {
									$attempt_details = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
								}
								?>
								<tr>
									<td><?php echo (int) $attempt['id']; ?></td>
									<td><?php echo (int) $attempt['attempt_no']; ?></td>
									<td>
										<?php if ($attempt_status !== NULL && isset($statuses[$attempt_status])): ?>
											<span class="label <?php echo $status_classes[$attempt_status]; ?>">
												<?php echo $e($statuses[$attempt_status]); ?>
											</span>
										<?php else: ?>
											<span class="text-muted">—</span>
										<?php endif; ?>
									</td>
									<td><?php echo $e($dt($attempt['started_at'])); ?></td>
									<td><?php echo $e($dt($attempt['finished_at'])); ?></td>
									<td><?php echo ($attempt['duration_ms'] !== NULL) ? (int) $attempt['duration_ms'].' мс' : '—'; ?></td>
									<td><?php echo ($attempt['error_code'] !== NULL && $attempt['error_code'] !== '') ? $e($attempt['error_code']) : ''; ?></td>
									<td style="word-break:break-word;">
										<?php echo $e(isset($attempt['error_text']) ? $attempt['error_text'] : ''); ?>
									</td>
									<td style="word-break:break-all;"><?php echo $e($attempt['worker_id']); ?></td>
									<td>
										<?php if ($attempt_details === ''): ?>
											<span class="text-muted">—</span>
										<?php else: ?>
											<a href="#" onclick="$(this).next('pre').toggle(); return false;"
											   style="font-size:11px;">показать</a>
											<pre style="display:none; white-space:pre-wrap; word-break:break-word; margin:5px 0 0; font-size:10px; max-width:520px;"><?php echo $e($attempt_details); ?></pre>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<p>
	<a href="<?php echo $e($list_url); ?>" class="btn btn-default btn-sm">
		<span class="glyphicon glyphicon-arrow-left"></span> Вернуться к очереди
	</a>
</p>
