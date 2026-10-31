<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Раздел «Контроль новый»: очередь интегратора PARSEC_QUEUE.
 *
 * Ожидаемые переменные:
 *   array  $filters  нормализованные фильтры (Model_Parsec2::prepare_filters)
 *   array  $rows     события текущей страницы
 *   int    $total    всего событий под фильтром
 *   int    $pages    всего страниц
 *   array  $stats    сводка по статусам
 *   string $error    текст ошибки БД (или NULL)
 */
$statuses        = Model_Parsec2::$statuses;
$status_classes  = Model_Parsec2::$status_classes;
$status_hints    = Model_Parsec2::$status_hints;
$operations      = Model_Parsec2::$operations;
$sources         = Model_Parsec2::$sources;
$per_page_variants = Model_Parsec2::$per_page_variants;

$base_url = URL::site('parsec2');

/** Экранирование текста для HTML. */
$e = function ($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

/** Ссылка на список с изменёнными параметрами. */
$make_url = function ($changes = array()) use ($filters, $base_url) {
	$params = array_merge($filters, is_array($changes) ? $changes : array());
	foreach ($params as $key => $value) {
		if ($value === '' || $value === NULL) {
			unset($params[$key]);
		}
	}
	$query = http_build_query($params, '', '&');
	return ($query === '') ? $base_url : $base_url.'?'.$query;
};

/** Ссылка сортировки по колонке. */
$sort_url = function ($key) use ($filters, $make_url) {
	$dir = ($filters['sort'] === $key && $filters['dir'] === 'asc') ? 'desc' : 'asc';
	return $make_url(array('sort' => $key, 'dir' => $dir, 'page' => 1));
};

/** Индикатор направления сортировки. */
$sort_mark = function ($key) use ($filters) {
	if ($filters['sort'] !== $key) {
		return '<span class="glyphicon glyphicon-sort text-muted" style="font-size:10px;"></span>';
	}
	return ($filters['dir'] === 'asc') ? '&uarr;' : '&darr;';
};

/** Timestamp Firebird 'YYYY-MM-DD HH:MM:SS' → 'DD.MM.YYYY HH:MM:SS'. */
$dt = function ($value) {
	$value = (string) $value;
	if (strlen($value) >= 19) {
		return substr($value, 8, 2).'.'.substr($value, 5, 2).'.'.substr($value, 0, 4)
			.' '.substr($value, 11, 8);
	}
	return $value;
};

/** Обрезка длинного текста (UTF-8). */
$cut = function ($value, $length = 80) {
	$value = (string) $value;
	if (function_exists('mb_strlen') && mb_strlen($value, 'UTF-8') > $length) {
		return mb_substr($value, 0, $length, 'UTF-8').'…';
	}
	if ( ! function_exists('mb_strlen') && strlen($value) > $length) {
		return substr($value, 0, $length).'...';
	}
	return $value;
};

/** Длительность: мс → «12,59 с» + исходные мс. */
$duration = function ($ms) {
	$ms = (int) $ms;
	if ($ms <= 0) {
		return '—';
	}
	if ($ms < 1000) {
		return $ms.' мс';
	}
	return number_format($ms / 1000, 2, ',', ' ').' с';
};

$back_params = array();
foreach ($filters as $filter_key => $filter_value) {
	if ($filter_value !== '' && $filter_value !== NULL) {
		$back_params[$filter_key] = $filter_value;
	}
}
$back_query = http_build_query($back_params, '', '&');
$status_total = 0;
foreach ($statuses as $code => $title) {
	if (isset($stats['by_status'][$code])) {
		$status_total += (int) $stats['by_status'][$code];
	}
}

$first_row = ($total > 0) ? (($filters['page'] - 1) * $filters['per_page'] + 1) : 0;
$last_row  = min($total, $filters['page'] * $filters['per_page']);
?>

<?php echo View::factory('parsec/_nav'); ?>

<?php if ( ! empty($error)): ?>
	<div class="alert alert-danger">
		<span class="glyphicon glyphicon-exclamation-sign"></span>
		<?php echo $e($error); ?>
	</div>
<?php endif; ?>

<!-- ======================= Сводка по статусам ======================= -->
<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title">
			<span class="glyphicon glyphicon-tasks"></span>
			Очередь интегратора PARSEC_QUEUE — событий: <strong><?php echo (int) $stats['total']; ?></strong>
		</h3>
	</div>
	<div class="panel-body">
		<div style="margin-bottom: 5px;">
			<?php foreach ($statuses as $code => $title): ?>
				<?php
				$count = isset($stats['by_status'][$code]) ? (int) $stats['by_status'][$code] : 0;
				$is_active = ($filters['status'] === (string) $code);
				$link = $make_url(array('status' => (string) $code, 'page' => 1));
				?>
				<a href="<?php echo $e($link); ?>"
				   class="label <?php echo $status_classes[$code]; ?>"
				   style="display:inline-block; margin:0 6px 6px 0; padding:5px 9px; font-size:12px; text-decoration:none;<?php echo $is_active ? ' outline:2px solid #333;' : ''; ?>"
				   title="<?php echo $e('Ставит: '.$status_hints[$code]); ?>">
					<?php echo $code; ?> · <?php echo $e($title); ?>
					<span class="badge" style="background-color:rgba(0,0,0,.35);"><?php echo $count; ?></span>
				</a>
			<?php endforeach; ?>

			<?php if ( ! empty($stats['other'])): ?>
				<span class="label label-default" style="display:inline-block; margin:0 6px 6px 0; padding:5px 9px; font-size:12px;">
					прочие <span class="badge"><?php echo (int) $stats['other']; ?></span>
				</span>
			<?php endif; ?>

			<?php if ($filters['status'] !== ''): ?>
				<a href="<?php echo $e($make_url(array('status' => '', 'page' => 1))); ?>"
				   class="btn btn-xs btn-default" style="margin-bottom:6px;">
					<span class="glyphicon glyphicon-remove"></span> сбросить статус
				</a>
			<?php endif; ?>
		</div>

		<p class="text-muted" style="margin:0; font-size:12px;">
			Очередь читает служба <code>ParsecIntegrationClient</code>. Строки не удаляются: статус 2/4/5/6 —
			это завершённые события, они остаются как история.
			<?php if ($status_total !== (int) $stats['total']): ?>
				<br>Известные статусы: <?php echo (int) $status_total; ?> из <?php echo (int) $stats['total']; ?>.
			<?php endif; ?>
		</p>
	</div>
</div>

<!-- ======================= Фильтры ======================= -->
<div class="panel panel-default">
	<div class="panel-heading">
		<h3 class="panel-title">
			<span class="glyphicon glyphicon-filter"></span> Фильтры
			<span class="text-muted" style="font-size:12px;">(применяются к истории целиком, выборка — на сервере)</span>
		</h3>
	</div>
	<div class="panel-body">
		<form method="get" action="<?php echo $e($base_url); ?>" class="form-inline" role="form">
			<input type="hidden" name="sort" value="<?php echo $e($filters['sort']); ?>">
			<input type="hidden" name="dir" value="<?php echo $e($filters['dir']); ?>">

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-status">Статус</label>
				<select class="form-control input-sm" id="f-status" name="status">
					<option value="">Все статусы</option>
					<?php foreach ($statuses as $code => $title): ?>
						<option value="<?php echo $code; ?>" <?php echo ($filters['status'] === (string) $code) ? 'selected' : ''; ?>>
							<?php echo $code; ?> — <?php echo $e($title); ?>
						</option>
					<?php endforeach; ?>
					<option value="active" <?php echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>0, 1 — активные</option>
					<option value="problem" <?php echo ($filters['status'] === 'problem') ? 'selected' : ''; ?>>3, 4 — проблемные</option>
				</select>
			</div>

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-operation">Операция</label>
				<select class="form-control input-sm" id="f-operation" name="operation">
					<option value="">Все операции</option>
					<?php foreach ($operations as $code => $title): ?>
						<option value="<?php echo $code; ?>" <?php echo ($filters['operation'] === (string) $code) ? 'selected' : ''; ?>>
							<?php echo $code; ?> — <?php echo $e($title); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-source">Источник</label>
				<select class="form-control input-sm" id="f-source" name="source_table">
					<option value="">Все источники</option>
					<?php foreach ($sources as $code => $title): ?>
						<option value="<?php echo $e($code); ?>" <?php echo ($filters['source_table'] === $code) ? 'selected' : ''; ?>>
							<?php echo $e($title); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-q">Поиск</label>
				<input type="text" class="form-control input-sm" id="f-q" name="q"
					   value="<?php echo $e($filters['q']); ?>" size="24"
					   placeholder="ID, id_pep, id_card, ошибка, исполнитель">
			</div>

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-from">С даты</label>
				<input type="date" class="form-control input-sm" id="f-from" name="date_from"
					   value="<?php echo $e($filters['date_from']); ?>" title="Создано с">
			</div>

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-to">По дату</label>
				<input type="date" class="form-control input-sm" id="f-to" name="date_to"
					   value="<?php echo $e($filters['date_to']); ?>" title="Создано по">
			</div>

			<div class="form-group" style="margin-bottom:8px;">
				<label class="sr-only" for="f-per">Строк</label>
				<select class="form-control input-sm" id="f-per" name="per_page">
					<?php foreach ($per_page_variants as $variant): ?>
						<option value="<?php echo $variant; ?>" <?php echo ((int) $filters['per_page'] === $variant) ? 'selected' : ''; ?>>
							<?php echo $variant; ?> строк
						</option>
					<?php endforeach; ?>
				</select>
			</div>

			<button type="submit" class="btn btn-sm btn-primary" style="margin-bottom:8px;">
				<span class="glyphicon glyphicon-search"></span> Применить
			</button>
			<a href="<?php echo $e($base_url); ?>" class="btn btn-sm btn-default" style="margin-bottom:8px;">
				<span class="glyphicon glyphicon-repeat"></span> Сбросить
			</a>
		</form>
	</div>
</div>

<!-- ======================= Таблица событий ======================= -->
<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title">
			<span class="glyphicon glyphicon-list-alt"></span>
			События
			<?php if ($total > 0): ?>
				<span class="text-muted" style="font-size:12px;">
					<?php echo $first_row; ?>–<?php echo $last_row; ?> из <?php echo (int) $total; ?>
					(страница <?php echo (int) $filters['page']; ?> из <?php echo (int) $pages; ?>)
				</span>
			<?php endif; ?>
		</h3>
	</div>
	<div class="panel-body">

		<?php if (empty($rows)): ?>
			<div class="alert alert-info" style="margin-bottom:0;">
				<span class="glyphicon glyphicon-info-sign"></span>
				<?php echo ($total > 0)
					? 'На этой странице нет событий. Вернитесь на первую страницу.'
					: 'Под выбранные условия событий нет.'; ?>
			</div>
		<?php else: ?>

			<div class="table-responsive">
				<table class="table table-striped table-hover table-condensed" id="parsec2-table">
					<thead>
						<tr class="active">
							<th class="text-center" style="width:70px;">
								<a href="<?php echo $e($sort_url('id')); ?>">ID <?php echo $sort_mark('id'); ?></a>
							</th>
							<th class="text-center" style="width:100px;">
								<a href="<?php echo $e($sort_url('status')); ?>">Статус <?php echo $sort_mark('status'); ?></a>
							</th>
							<th class="text-center" style="width:150px;">
								<a href="<?php echo $e($sort_url('operation')); ?>">Операция <?php echo $sort_mark('operation'); ?></a>
							</th>
							<th class="text-center">Кому</th>
							<th class="text-center">Что</th>
							<th class="text-center" style="width:80px;">
								<a href="<?php echo $e($sort_url('attempts')); ?>">Попытки <?php echo $sort_mark('attempts'); ?></a>
							</th>
							<th class="text-center" style="width:150px;">
								<a href="<?php echo $e($sort_url('created')); ?>">Время <?php echo $sort_mark('created'); ?></a>
							</th>
							<th class="text-center" style="width:90px;">
								<a href="<?php echo $e($sort_url('duration')); ?>">Длит. <?php echo $sort_mark('duration'); ?></a>
							</th>
							<th class="text-center">Исполнитель</th>
							<th class="text-center">Ошибка</th>
							<th class="text-center" style="width:170px;">Действия</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($rows as $row): ?>
							<?php
							$status = (int) $row['status'];
							$operation = (int) $row['operation'];
							$event_id = (int) $row['id'];
							$error_text = isset($row['error_text']) ? (string) $row['error_text'] : '';
							$error_code = isset($row['error_code']) ? $row['error_code'] : NULL;
							?>
							<tr>
								<td class="text-center">
									<a href="<?php echo $e(URL::site('parsec2/log/'.$event_id).($back_query !== '' ? '?back='.urlencode($back_query) : '')); ?>"
									   title="Карточка события">
										<strong><?php echo $event_id; ?></strong>
									</a>
								</td>
								<td class="text-center">
									<span class="label <?php echo $row['status_class']; ?>"
										  title="<?php echo $e(isset($status_hints[$status]) ? $status_hints[$status] : ''); ?>">
										<?php echo $e($row['status_name']); ?>
									</span>
								</td>
								<td>
									<?php echo $e($row['operation_name']); ?>
									<div class="text-muted" style="font-size:11px;">код <?php echo $operation; ?></div>
								</td>
								<td style="font-size:12px;">
									<?php if (isset($row['id_pep']) && $row['id_pep'] !== '' && $row['id_pep'] !== NULL): ?>
										<?php echo $e(($row['person_name'] !== '') ? $row['person_name'] : 'id_pep='.$row['id_pep']); ?>
										<div class="text-muted" style="font-size:11px;">id_pep=<?php echo (int) $row['id_pep']; ?></div>
									<?php endif; ?>
								</td>
								<td style="font-size:12px;">
									<?php echo $e($row['object_label']); ?>
									<?php if (isset($row['id_card']) && $row['id_card'] !== '' && $row['id_card'] !== NULL): ?>
										<div class="text-muted" style="font-size:11px;"><?php echo $e($row['id_card']); ?></div>
									<?php endif; ?>
								</td>
								<td class="text-center">
									<?php echo (int) $row['attempts']; ?>
									<span class="text-muted">/ <?php echo (int) $row['max_attempts']; ?></span>
								</td>
								<td style="white-space:nowrap; font-size:12px;">
									<span class="glyphicon glyphicon-plus" title="создано"></span>
									<?php echo $e($dt($row['created_at'])); ?>
									<?php if ( ! empty($row['started_at'])): ?>
										<br><span class="glyphicon glyphicon-play" title="начато"></span>
										<?php echo $e($dt($row['started_at'])); ?>
									<?php endif; ?>
									<?php if ( ! empty($row['finished_at'])): ?>
										<br><span class="glyphicon glyphicon-ok" title="завершено"></span>
										<?php echo $e($dt($row['finished_at'])); ?>
									<?php endif; ?>
									<?php if (($status === 0 || $status === 3) && ! empty($row['next_attempt'])): ?>
										<br><span class="glyphicon glyphicon-time" title="следующая попытка"></span>
										<?php echo $e($dt($row['next_attempt'])); ?>
									<?php endif; ?>
								</td>
								<td class="text-center" style="font-size:12px;"
									<?php echo ($row['duration_ms'] !== NULL) ? ' title="'.(int) $row['duration_ms'].' мс"' : ''; ?>>
									<?php echo $e($duration($row['duration_ms'])); ?>
								</td>
								<td style="font-size:11px; word-break:break-all;">
									<?php echo $e($cut($row['worker_id'], 40)); ?>
								</td>
								<td style="font-size:11px;">
									<?php if ($error_code !== NULL && $error_code !== ''): ?>
										<span class="label label-danger"><?php echo $e($error_code); ?></span><br>
									<?php endif; ?>
									<?php if ($error_text !== ''): ?>
										<span title="<?php echo $e($error_text); ?>"><?php echo $e($cut($error_text, 60)); ?></span>
									<?php endif; ?>
								</td>
								<td class="text-center" style="white-space:nowrap;">
									<?php if ($status === 3 || $status === 4): ?>
										<form method="post" action="<?php echo $e(URL::site('parsec2/repeat')); ?>"
											  style="display:inline;"
											  onsubmit="return confirm('Вернуть событие #<?php echo $event_id; ?> в очередь? Счётчик попыток будет сброшен.');">
											<input type="hidden" name="id" value="<?php echo $event_id; ?>">
											<input type="hidden" name="back" value="<?php echo $e($back_query); ?>">
											<button type="submit" class="btn btn-xs btn-success" title="Статус 0, попытки 0">
												<span class="glyphicon glyphicon-refresh"></span> Повторить
											</button>
										</form>
									<?php endif; ?>

									<?php if ($status === 0 || $status === 3): ?>
										<form method="post" action="<?php echo $e(URL::site('parsec2/cancel')); ?>"
											  style="display:inline;"
											  onsubmit="return confirm('Отменить событие #<?php echo $event_id; ?>? Оно получит статус 6 и больше не будет обработано.');">
											<input type="hidden" name="id" value="<?php echo $event_id; ?>">
											<input type="hidden" name="back" value="<?php echo $e($back_query); ?>">
											<button type="submit" class="btn btn-xs btn-danger" title="Статус 6">
												<span class="glyphicon glyphicon-ban-circle"></span> Отменить
											</button>
										</form>
									<?php endif; ?>

									<a class="btn btn-xs btn-default"
									   href="<?php echo $e(URL::site('parsec2/log/'.$event_id).($back_query !== '' ? '?back='.urlencode($back_query) : '')); ?>"
									   title="Все попытки обработки">
										<span class="glyphicon glyphicon-search"></span> История
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ($pages > 1): ?>
				<?php
				$start_page = max(1, $filters['page'] - 2);
				$end_page = min($pages, $start_page + 4);
				$start_page = max(1, $end_page - 4);
				?>
				<nav class="text-center">
					<ul class="pagination pagination-sm" style="margin:0;">
						<li class="<?php echo ($filters['page'] <= 1) ? 'disabled' : ''; ?>">
							<a href="<?php echo $e($make_url(array('page' => max(1, $filters['page'] - 1)))); ?>"
							   aria-label="Предыдущая">&laquo;</a>
						</li>

						<?php for ($page = $start_page; $page <= $end_page; $page++): ?>
							<li class="<?php echo ($page === (int) $filters['page']) ? 'active' : ''; ?>">
								<a href="<?php echo $e($make_url(array('page' => $page))); ?>"><?php echo $page; ?></a>
							</li>
						<?php endfor; ?>

						<li class="<?php echo ($filters['page'] >= $pages) ? 'disabled' : ''; ?>">
							<a href="<?php echo $e($make_url(array('page' => min($pages, $filters['page'] + 1)))); ?>"
							   aria-label="Следующая">&raquo;</a>
						</li>
					</ul>
				</nav>
			<?php endif; ?>

		<?php endif; ?>
	</div>
</div>

<!-- ======================= Легенда ======================= -->
<div class="panel panel-default">
	<div class="panel-heading">
		<h3 class="panel-title">
			<span class="glyphicon glyphicon-question-sign"></span> Как читать таблицу
		</h3>
	</div>
	<div class="panel-body" style="font-size:12px;">
		<p>
			<code>PARSEC_QUEUE</code> — одно событие и его текущее состояние.
			<code>PARSEC_QUEUE_LOG</code> — каждая попытка обработки (кнопка «История»).
			Служба разбирает очередь строго по <code>ID</code>; первая ошибка останавливает проход,
			поэтому событие со статусом 3 задерживает все следующие за ним.
		</p>
		<p>
			«Повторить» доступно для статусов 3 и 4: счётчик попыток обнуляется, событие возвращается
			в очередь (статус 0). «Отменить» доступно для статусов 0 и 3: событие получает статус 6
			и больше не обрабатывается — так снимается «застрявшая» строка, если ошибку нельзя устранить.
		</p>
		<table class="table table-condensed" style="margin-bottom:0; max-width:760px;">
			<thead>
				<tr class="active">
					<th style="width:60px;">Код</th>
					<th>Значение</th>
					<th>Кто ставит</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($statuses as $code => $title): ?>
					<tr>
						<td><span class="label <?php echo $status_classes[$code]; ?>"><?php echo $code; ?></span></td>
						<td><?php echo $e($title); ?></td>
						<td class="text-muted"><?php echo $e($status_hints[$code]); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>
