<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Модель очереди интегратора PARSEC_QUEUE (раздел «Контроль новый»).
 *
 * В отличие от Model_Parsec (таблица CARDINDEV), здесь читается история
 * очереди: одна строка = одно бизнес-событие и его текущее состояние.
 * Строки не удаляются, поэтому модель умеет:
 *   - постранично отдавать список с фильтрами;
 *   - считать сводку по статусам;
 *   - показывать попытки обработки из PARSEC_QUEUE_LOG;
 *   - возвращать в очередь событие со статусом 3/4 (повтор);
 *   - отменять ожидающее событие (статус 6).
 *
 * Особенности БД: Firebird 1.5 (dialect 3, WIN1251) через ODBC-драйвер.
 * PDO-драйвер Kohana не умеет bind-параметры, поэтому все значения
 * подставляются в SQL как экранированные литералы (см. _esc()).
 */
class Model_Parsec2 extends Model {

	/** @var string имя подключения из application/config/database.php */
	protected static $_db = 'fb';

	/**
	 * Статусы PARSEC_QUEUE (см. modules/parsec/sql + db/README.md службы).
	 * @var array
	 */
	public static $statuses = array(
		0 => 'Новая',
		1 => 'В работе',
		2 => 'Успешно',
		3 => 'Ошибка, будет повтор',
		4 => 'Ошибка, попытки исчерпаны',
		5 => 'Пропущено',
		6 => 'Отменено',
	);

	/** @var array CSS-классы Bootstrap для статусов */
	public static $status_classes = array(
		0 => 'label-default',
		1 => 'label-info',
		2 => 'label-success',
		3 => 'label-warning',
		4 => 'label-danger',
		5 => 'label-default',
		6 => 'label-default',
	);

	/** @var array подсказки «кто ставит статус» */
	public static $status_hints = array(
		0 => 'триггер или процедура PARSEC_ENQUEUE',
		1 => 'служба, TryClaimQueueRow',
		2 => 'служба',
		3 => 'служба',
		4 => 'служба',
		5 => 'служба, SkipErrCode',
		6 => 'триггеры',
	);

	/** @var array операции интегратора */
	public static $operations = array(
		1 => 'Добавить идентификатор',
		11 => 'Изменить идентификатор',
		2 => 'Удалить идентификатор',
		3 => 'Добавить контакт',
		4 => 'Удалить контакт',
		5 => 'Добавить организацию',
		6 => 'Удалить организацию',
		7 => 'Добавить категорию доступа',
		8 => 'Удалить категорию доступа',
		9 => 'Добавить идентификатор Parsec',
		10 => 'Удалить идентификатор Parsec',
		35 => 'Изменить данные контакта',
		55 => 'Изменить данные организации',
	);

	/** @var array источники событий (поле SOURCE_TABLE) */
	public static $sources = array(
		'PEOPLE' => 'PEOPLE',
		'ORGANIZATION' => 'ORGANIZATION',
		'CARD' => 'CARD',
		'SS_ACCESSUSER' => 'SS_ACCESSUSER',
	);

	/** @var array допустимые поля сортировки: ключ => колонка SQL */
	public static $sortable = array(
		'id' => 'q.id',
		'status' => 'q.status',
		'operation' => 'q.operation',
		'attempts' => 'q.attempts',
		'created' => 'q.created_at',
		'next' => 'q.next_attempt',
		'finished' => 'q.finished_at',
		'duration' => 'q.duration_ms',
	);

	/** @var array допустимые значения «строк на страницу» */
	public static $per_page_variants = array(25, 50, 100, 200);

	/** @var string|null последняя ошибка БД */
	protected $_last_error = NULL;

	/**
	 * Текст последней ошибки (NULL, если ошибок не было).
	 * @return string|null
	 */
	public function get_last_error()
	{
		return $this->_last_error;
	}

	// ---------------------------------------------------------------------
	// Фильтры
	// ---------------------------------------------------------------------

	/**
	 * Приводит входные данные (обычно $_GET) к предсказуемому набору фильтров.
	 * Возвращённый массив используется и для SQL, и для построения ссылок,
	 * поэтому «мусор» из запроса сюда не попадает.
	 *
	 * @param   array  $input
	 * @return  array
	 */
	public static function prepare_filters(array $input)
	{
		$filters = array(
			'status'       => '',
			'operation'    => '',
			'source_table' => '',
			'q'            => '',
			'date_from'    => '',
			'date_to'      => '',
			'sort'         => 'id',
			'dir'          => 'desc',
			'per_page'     => 50,
			'page'         => 1,
		);

		if (isset($input['status']))
		{
			$value = (string) $input['status'];
			if (in_array($value, array('0', '1', '2', '3', '4', '5', '6', 'active', 'problem'), TRUE))
			{
				$filters['status'] = $value;
			}
		}

		if (isset($input['operation']))
		{
			$value = trim((string) $input['operation']);
			if (preg_match('/^\d{1,9}$/', $value))
			{
				$filters['operation'] = $value;
			}
		}

		if (isset($input['source_table']))
		{
			$value = strtoupper(trim((string) $input['source_table']));
			if (isset(self::$sources[$value]))
			{
				$filters['source_table'] = $value;
			}
		}

		if (isset($input['q']))
		{
			$value = trim((string) $input['q']);
			if (function_exists('mb_substr'))
			{
				$value = mb_substr($value, 0, 100, 'UTF-8');
			}
			else
			{
				$value = substr($value, 0, 100);
			}
			$filters['q'] = $value;
		}

		foreach (array('date_from', 'date_to') as $key)
		{
			if (! isset($input[$key]))
			{
				continue;
			}

			$value = trim((string) $input[$key]);
			if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)
				&& checkdate((int) $m[2], (int) $m[3], (int) $m[1]))
			{
				$filters[$key] = $value;
			}
		}

		if (isset($input['sort']) && isset(self::$sortable[$input['sort']]))
		{
			$filters['sort'] = (string) $input['sort'];
		}

		if (isset($input['dir']) && strtolower((string) $input['dir']) === 'asc')
		{
			$filters['dir'] = 'asc';
		}

		if (isset($input['per_page']) && in_array((int) $input['per_page'], self::$per_page_variants, TRUE))
		{
			$filters['per_page'] = (int) $input['per_page'];
		}

		if (isset($input['page']) && (int) $input['page'] > 0)
		{
			$filters['page'] = (int) $input['page'];
		}

		return $filters;
	}

	/**
	 * Собирает WHERE из нормализованных фильтров.
	 * @param   array   $filters
	 * @return  string  пустая строка или «where ...»
	 */
	protected function _build_where(array $filters)
	{
		$where = array();

		switch ($filters['status'])
		{
			case 'active':
				$where[] = 'q.status in (0, 1)';
				break;
			case 'problem':
				$where[] = 'q.status in (3, 4)';
				break;
			case '':
				break;
			default:
				$where[] = 'q.status = '.(int) $filters['status'];
				break;
		}

		if ($filters['operation'] !== '')
		{
			$where[] = 'q.operation = '.(int) $filters['operation'];
		}

		if ($filters['source_table'] !== '')
		{
			$where[] = "q.source_table = '".$this->_esc($filters['source_table'])."'";
		}

		if ($filters['date_from'] !== '')
		{
			$where[] = "q.created_at >= '".$filters['date_from']." 00:00:00'";
		}

		if ($filters['date_to'] !== '')
		{
			$where[] = "q.created_at <= '".$filters['date_to']." 23:59:59'";
		}

		$search = trim($filters['q']);
		if ($search !== '')
		{
			$like = '%'.$this->_esc($search).'%';

			$parts = array(
				"q.id_card like '".$like."'",
				"coalesce(q.error_text, '') like '".$like."'",
				"coalesce(q.worker_id, '') like '".$like."'",
				"coalesce(q.dedup_key, '') like '".$like."'",
			);

			if (preg_match('/^\d{1,18}$/', $search))
			{
				$parts[] = 'q.id = '.(int) $search;
				$parts[] = 'q.id_pep = '.(int) $search;
			}

			$where[] = '('.implode(' or ', $parts).')';
		}

		return empty($where) ? '' : 'where '.implode(' and ', $where);
	}

	/**
	 * ORDER BY с белым списком полей.
	 * @param   array   $filters
	 * @return  string
	 */
	protected function _build_order(array $filters)
	{
		$column = self::$sortable[$filters['sort']];
		$dir = ($filters['dir'] === 'asc') ? 'asc' : 'desc';

		$order = $column.' '.$dir;
		if ($column !== 'q.id')
		{
			// стабильный порядок внутри одинаковых значений
			$order .= ', q.id '.$dir;
		}

		return 'order by '.$order;
	}

	// ---------------------------------------------------------------------
	// Чтение
	// ---------------------------------------------------------------------

	/**
	 * Сводка по статусам.
	 *
	 * @return  array  array('by_status' => array(код => кол-во), 'total' => N, 'other' => N)
	 */
	public function get_status_stats()
	{
		$by_status = array();
		foreach (array_keys(self::$statuses) as $code)
		{
			$by_status[$code] = 0;
		}

		$sql = 'select status, count(*) as cnt from parsec_queue group by status';

		try
		{
			$rows = DB::query(Database::SELECT, $sql)
				->execute(Database::instance(self::$_db))
				->as_array();
		}
		catch (Exception $e)
		{
			$this->_last_error = $e->getMessage();
			throw $e;
		}

		$total = 0;
		$other = 0;

		foreach ($rows as $row)
		{
			$code = (int) $row['STATUS'];
			$count = (int) $row['CNT'];
			$total += $count;

			if (array_key_exists($code, $by_status))
			{
				$by_status[$code] = $count;
			}
			else
			{
				$other += $count;
			}
		}

		return array(
			'by_status' => $by_status,
			'total'     => $total,
			'other'     => $other,
		);
	}

	/**
	 * Количество событий под фильтром.
	 * @param   array  $filters
	 * @return  integer
	 */
	public function count_queue(array $filters)
	{
		$sql = 'select count(*) as cnt from parsec_queue q '.$this->_build_where($filters);

		try
		{
			$rows = DB::query(Database::SELECT, $sql)
				->execute(Database::instance(self::$_db))
				->as_array();
		}
		catch (Exception $e)
		{
			$this->_last_error = $e->getMessage();
			throw $e;
		}

		if (empty($rows))
		{
			return 0;
		}

		$values = array_values($rows[0]);

		return (int) $values[0];
	}

	/**
	 * Страница списка событий.
	 *
	 * @param   array  $filters  нормализованные фильтры
	 * @return  array
	 */
	public function get_queue_list(array $filters)
	{
		$limit  = (int) $filters['per_page'];
		$offset = ((int) $filters['page'] - 1) * $limit;
		if ($offset < 0)
		{
			$offset = 0;
		}

		$sql = 'select first '.$limit.' skip '.$offset.'
					q.id, q.operation, q.id_pep, q.id_card, q.status, q.attempts,
					q.max_attempts, q.error_code, q.error_text, q.duration_ms,
					q.worker_id, q.source_table, q.dedup_key,
					cast(q.next_attempt as varchar(30)) as next_attempt,
					cast(q.lease_until as varchar(30)) as lease_until,
					cast(q.created_at as varchar(30)) as created_at,
					cast(q.updated_at as varchar(30)) as updated_at,
					cast(q.started_at as varchar(30)) as started_at,
					cast(q.finished_at as varchar(30)) as finished_at
				from parsec_queue q
				'.$this->_build_where($filters).'
				'.$this->_build_order($filters);

		try
		{
			$rows = DB::query(Database::SELECT, $sql)
				->execute(Database::instance(self::$_db))
				->as_array();
		}
		catch (Exception $e)
		{
			$this->_last_error = $e->getMessage();
			throw $e;
		}

		$rows = $this->_normalize_rows($rows);
		$this->_enrich_rows($rows);

		return $rows;
	}

	/**
	 * Одно событие по ID (с расшифровкой ФИО/организации).
	 *
	 * @param   integer  $id
	 * @return  array|null
	 */
	public function get_event($id)
	{
		$id = (int) $id;
		if ($id <= 0)
		{
			return NULL;
		}

		$sql = 'select q.id, q.operation, q.id_pep, q.id_card, q.status, q.attempts,
					q.max_attempts, q.error_code, q.error_text, q.duration_ms,
					q.worker_id, q.source_table, q.dedup_key,
					cast(q.next_attempt as varchar(30)) as next_attempt,
					cast(q.lease_until as varchar(30)) as lease_until,
					cast(q.created_at as varchar(30)) as created_at,
					cast(q.updated_at as varchar(30)) as updated_at,
					cast(q.started_at as varchar(30)) as started_at,
					cast(q.finished_at as varchar(30)) as finished_at
				from parsec_queue q
				where q.id = '.$id;

		$rows = DB::query(Database::SELECT, $sql)
			->execute(Database::instance(self::$_db))
			->as_array();

		if (empty($rows))
		{
			return NULL;
		}

		$rows = $this->_normalize_rows($rows);
		$this->_enrich_rows($rows);

		return $rows[0];
	}

	/**
	 * Попытки обработки события из PARSEC_QUEUE_LOG.
	 *
	 * @param   integer  $id  ID события
	 * @return  array
	 */
	public function get_event_attempts($id)
	{
		$id = (int) $id;
		if ($id <= 0)
		{
			return array();
		}

		$sql = 'select l.id, l.attempt_no, l.status, l.error_code, l.error_text,
					l.duration_ms, l.worker_id, l.details,
					cast(l.started_at as varchar(30)) as started_at,
					cast(l.finished_at as varchar(30)) as finished_at
				from parsec_queue_log l
				where l.queue_id = '.$id.'
				order by l.id';

		$rows = DB::query(Database::SELECT, $sql)
			->execute(Database::instance(self::$_db))
			->as_array();

		return $this->_normalize_rows($rows);
	}

	// ---------------------------------------------------------------------
	// Изменение состояния
	// ---------------------------------------------------------------------

	/**
	 * Вернуть событие в очередь: статус 0, попытки сброшены.
	 * Разрешено только для статусов 3 (ошибка, будет повтор) и 4 (dead letter).
	 *
	 * @param   integer  $id
	 * @return  array    array('ok' => bool, 'message' => string)
	 */
	public function repeat_event($id)
	{
		$id = (int) $id;
		$event = $this->get_event($id);

		if ( ! $event)
		{
			return array('ok' => FALSE, 'message' => 'Событие #'.$id.' не найдено.');
		}

		$status = (int) $event['status'];
		if ( ! in_array($status, array(3, 4), TRUE))
		{
			return array(
				'ok'      => FALSE,
				'message' => 'Повтор доступен только для статусов 3 и 4. У события #'.$id.' статус '
					.$this->_status_label($status).'.',
			);
		}

		$sql = 'update parsec_queue set
					status = 0,
					attempts = 0,
					next_attempt = current_timestamp,
					lease_until = null,
					started_at = null,
					finished_at = null,
					duration_ms = null,
					worker_id = null,
					error_code = null,
					error_text = null,
					updated_at = current_timestamp
				where id = '.$id.' and status in (3, 4)';

		try
		{
			DB::query(Database::UPDATE, $sql)
				->execute(Database::instance(self::$_db));
		}
		catch (Exception $e)
		{
			$this->_last_error = $e->getMessage();
			return array('ok' => FALSE, 'message' => 'Не удалось изменить событие #'.$id.': '.$e->getMessage());
		}

		$after = $this->get_event($id);

		if ( ! $after || (int) $after['status'] !== 0)
		{
			return array(
				'ok'      => FALSE,
				'message' => 'Событие #'.$id.' не удалось вернуть в очередь: состояние изменилось параллельно. Обновите страницу.',
			);
		}

		return array(
			'ok'      => TRUE,
			'message' => 'Событие #'.$id.' возвращено в очередь (статус 0, счётчик попыток сброшен).',
		);
	}

	/**
	 * Отменить ожидающее событие: статус 6.
	 * Разрешено только для статусов 0 (новая) и 3 (ошибка, будет повтор),
	 * чтобы не вмешиваться в обработку, которая уже идёт.
	 *
	 * @param   integer  $id
	 * @return  array    array('ok' => bool, 'message' => string)
	 */
	public function cancel_event($id)
	{
		$id = (int) $id;
		$event = $this->get_event($id);

		if ( ! $event)
		{
			return array('ok' => FALSE, 'message' => 'Событие #'.$id.' не найдено.');
		}

		$status = (int) $event['status'];
		if ( ! in_array($status, array(0, 3), TRUE))
		{
			return array(
				'ok'      => FALSE,
				'message' => 'Отмена доступна только для статусов 0 и 3. У события #'.$id.' статус '
					.$this->_status_label($status).'.',
			);
		}

		$sql = 'update parsec_queue set
					status = 6,
					lease_until = null,
					next_attempt = null,
					updated_at = current_timestamp
				where id = '.$id.' and status in (0, 3)';

		try
		{
			DB::query(Database::UPDATE, $sql)
				->execute(Database::instance(self::$_db));
		}
		catch (Exception $e)
		{
			$this->_last_error = $e->getMessage();
			return array('ok' => FALSE, 'message' => 'Не удалось изменить событие #'.$id.': '.$e->getMessage());
		}

		$after = $this->get_event($id);

		if ( ! $after || (int) $after['status'] !== 6)
		{
			return array(
				'ok'      => FALSE,
				'message' => 'Событие #'.$id.' не удалось отменить: состояние изменилось параллельно. Обновите страницу.',
			);
		}

		return array(
			'ok'      => TRUE,
			'message' => 'Событие #'.$id.' отменено (статус 6).',
		);
	}

	// ---------------------------------------------------------------------
	// Служебное
	// ---------------------------------------------------------------------

	/**
	 * Человекочитаемый статус.
	 * @param   integer  $code
	 * @return  string
	 */
	protected function _status_label($code)
	{
		$code = (int) $code;

		if (isset(self::$statuses[$code]))
		{
			return $code.' ('.self::$statuses[$code].')';
		}

		return $code.' (неизвестный)';
	}

	/**
	 * Приводит ключи строк к нижнему регистру и переводит cp1251 → UTF-8.
	 *
	 * @param   array  $rows
	 * @return  array
	 */
	protected function _normalize_rows(array $rows)
	{
		$result = array();

		foreach ($rows as $row)
		{
			$normalized = array();

			foreach ($row as $key => $value)
			{
				$normalized[strtolower($key)] = is_string($value) ? $this->_utf($value) : $value;
			}

			$result[] = $normalized;
		}

		return $result;
	}

	/**
	 * Дополняет строки расшифровкой: ФИО контакта, название организации,
	 * тексты статуса и операции.
	 *
	 * @param   array  $rows  по ссылке
	 * @return  void
	 */
	protected function _enrich_rows(array & $rows)
	{
		if (empty($rows))
		{
			return;
		}

		$peps = array();
		$guids = array();

		foreach ($rows as $row)
		{
			if (isset($row['id_pep']) && $row['id_pep'] !== NULL && $row['id_pep'] !== '')
			{
				$peps[(int) $row['id_pep']] = (int) $row['id_pep'];
			}

			$source = isset($row['source_table']) ? strtoupper($row['source_table']) : '';
			if ($source === 'ORGANIZATION' && ! empty($row['id_card']))
			{
				$guids[trim($row['id_card'])] = $row['id_card'];
			}
		}

		$people = $this->_load_people($peps);
		$orgs   = $this->_load_organizations($guids);

		foreach ($rows as & $row)
		{
			$person_name = '';
			if (isset($row['id_pep']) && isset($people[(int) $row['id_pep']]))
			{
				$person_name = $people[(int) $row['id_pep']];
			}

			$org_name = '';
			if (isset($row['id_card']))
			{
				$guid = trim($row['id_card']);
				if (isset($orgs[$guid]))
				{
					$org_name = $orgs[$guid];
				}
			}

			$status = (int) $row['status'];
			$operation = (int) $row['operation'];

			$row['status'] = $status;
			$row['operation'] = $operation;
			$row['id'] = (int) $row['id'];
			$row['attempts'] = (int) $row['attempts'];
			$row['max_attempts'] = (int) $row['max_attempts'];
			$row['id_pep'] = ($row['id_pep'] === NULL || $row['id_pep'] === '') ? NULL : (int) $row['id_pep'];
			$row['error_code'] = ($row['error_code'] === NULL || $row['error_code'] === '') ? NULL : (int) $row['error_code'];
			$row['duration_ms'] = ($row['duration_ms'] === NULL || $row['duration_ms'] === '') ? NULL : (int) $row['duration_ms'];
			$row['status_name'] = isset(self::$statuses[$status])
				? self::$statuses[$status]
				: 'Неизвестный ('.$status.')';
			$row['status_class'] = isset(self::$status_classes[$status])
				? self::$status_classes[$status]
				: 'label-default';
			$row['operation_name'] = isset(self::$operations[$operation])
				? self::$operations[$operation]
				: 'Неизвестная операция';
			$row['person_name'] = $person_name;
			$row['org_name'] = $org_name;
			$row['object_label'] = $this->_object_label($row, $org_name);
		}
		unset($row);
	}

	/**
	 * Что именно изменяет событие (для колонки «Что»).
	 *
	 * @param   array   $row
	 * @param   string  $org_name
	 * @return  string
	 */
	protected function _object_label(array $row, $org_name)
	{
		$operation = (int) $row['operation'];
		$id_card = isset($row['id_card']) ? (string) $row['id_card'] : '';

		switch ($operation)
		{
			case 5:
			case 6:
			case 55:
				return ($org_name !== '') ? $org_name : ('GUID '.$id_card);

			case 7:
			case 8:
				return 'категория доступа #'.$id_card;

			case 9:
			case 10:
				if ($id_card !== '' && ctype_digit($id_card))
				{
					return 'идентификатор 0x'.str_pad(dechex((int) $id_card), 8, '0', STR_PAD_LEFT);
				}
				return 'идентификатор '.$id_card;

			case 3:
			case 4:
			case 35:
				return 'контакт';

			default:
				return $id_card;
		}
	}

	/**
	 * ФИО контактов одним запросом.
	 *
	 * @param   array  $ids
	 * @return  array  id_pep => ФИО
	 */
	protected function _load_people(array $ids)
	{
		if (empty($ids))
		{
			return array();
		}

		$sql = 'select p.id_pep, p.surname, p.name, p.patronymic
				from people p
				where p.id_pep in ('.implode(',', $ids).')';

		$result = array();

		try
		{
			$rows = DB::query(Database::SELECT, $sql)
				->execute(Database::instance(self::$_db))
				->as_array();
		}
		catch (Exception $e)
		{
			// Расшифровка не критична: показываем хотя бы id_pep.
			Kohana::$log->add(Log::WARNING, 'PARSEC_QUEUE: не удалось прочитать ФИО: '.$e->getMessage());
			return $result;
		}

		foreach ($rows as $row)
		{
			$fio = trim($this->_utf($row['SURNAME']).' '.$this->_utf($row['NAME']).' '.$this->_utf($row['PATRONYMIC']));
			$result[(int) $row['ID_PEP']] = ($fio !== '') ? $fio : ('id_pep='.$row['ID_PEP']);
		}

		return $result;
	}

	/**
	 * Названия организаций одним запросом.
	 *
	 * @param   array  $guids
	 * @return  array  guid => название
	 */
	protected function _load_organizations(array $guids)
	{
		if (empty($guids))
		{
			return array();
		}

		$escaped = array();
		foreach ($guids as $guid)
		{
			$escaped[] = "'".$this->_esc($guid)."'";
		}

		$sql = 'select o.guid, o.name
				from organization o
				where o.guid in ('.implode(',', $escaped).')';

		$result = array();

		try
		{
			$rows = DB::query(Database::SELECT, $sql)
				->execute(Database::instance(self::$_db))
				->as_array();
		}
		catch (Exception $e)
		{
			Kohana::$log->add(Log::WARNING, 'PARSEC_QUEUE: не удалось прочитать организации: '.$e->getMessage());
			return $result;
		}

		foreach ($rows as $row)
		{
			$result[trim($row['GUID'])] = $this->_utf($row['NAME']);
		}

		return $result;
	}

	/**
	 * cp1251 → UTF-8 для показа в браузере.
	 *
	 * @param   mixed  $value
	 * @return  mixed
	 */
	protected function _utf($value)
	{
		if ( ! is_string($value) || $value === '')
		{
			return $value;
		}

		if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8'))
		{
			return $value;
		}

		$converted = @iconv('CP1251', 'UTF-8//IGNORE', $value);

		return ($converted === FALSE) ? $value : $converted;
	}

	/**
	 * UTF-8 → cp1251 перед подстановкой в SQL.
	 *
	 * @param   mixed  $value
	 * @return  string
	 */
	protected function _to_cp1251($value)
	{
		$value = (string) $value;

		if ($value === '')
		{
			return '';
		}

		if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8'))
		{
			$converted = @iconv('UTF-8', 'CP1251//IGNORE', $value);
			if ($converted !== FALSE)
			{
				return $converted;
			}
		}

		return $value;
	}

	/**
	 * Экранирование строкового литерала для Firebird.
	 *
	 * @param   mixed   $value
	 * @return  string
	 */
	protected function _esc($value)
	{
		return str_replace("'", "''", $this->_to_cp1251($value));
	}

}
