<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер раздела «Контроль новый» — просмотр очереди интегратора
 * PARSEC_QUEUE.
 *
 * Пункт меню: Parsec → Контроль новый (url: parsec2).
 * Заменяет чтение CARDINDEV (Model_Parsec) на чтение очереди PARSEC_QUEUE,
 * в которой хранится история: одна строка = одно событие и его состояние.
 *
 * Действия:
 *   index   — список с фильтрами, сводкой по статусам и постраничным выводом;
 *   log     — карточка события и все попытки из PARSEC_QUEUE_LOG;
 *   repeat  — вернуть событие в очередь (статусы 3 и 4);
 *   cancel  — отменить ожидающее событие (статусы 0 и 3).
 */
class Controller_Parsec2 extends Controller_Template {

	public $template = 'template';

	public function before()
	{
		parent::before();

		I18n::load('parsec');

		$_SESSION['menu_active'] = 'parsec2';

		if (method_exists($this, 'set_full_width'))
		{
			$this->set_full_width(TRUE);
		}

		if (method_exists($this, 'set_title'))
		{
			$this->set_title('Контроль очереди PARSEC_QUEUE');
		}
	}

	/**
	 * Список событий очереди.
	 */
	public function action_index()
	{
		$query = $this->request->query();
		if ( ! is_array($query))
		{
			$query = array();
		}

		$filters = Model_Parsec2::prepare_filters($query);
		$model = Model::factory('parsec2');

		$error = NULL;
		$rows = array();
		$total = 0;
		$stats = array(
			'by_status' => array(),
			'total'     => 0,
			'other'     => 0,
		);

		try
		{
			$stats = $model->get_status_stats();
			$total = $model->count_queue($filters);

			// Страница могла «уехать» после смены фильтра — подрезаем её.
			$pages = max(1, (int) ceil($total / $filters['per_page']));
			if ($filters['page'] > $pages)
			{
				$filters['page'] = $pages;
			}

			$rows = $model->get_queue_list($filters);
		}
		catch (Exception $e)
		{
			$error = 'Не удалось прочитать таблицу PARSEC_QUEUE: '.$e->getMessage();
			Kohana::$log->add(Log::ERROR, $error);
		}

		$pages = max(1, (int) ceil($total / max(1, (int) $filters['per_page'])));

		$content = View::factory('parsec/parsec2', array(
			'filters' => $filters,
			'rows'    => $rows,
			'total'   => $total,
			'pages'   => $pages,
			'stats'   => $stats,
			'error'   => $error,
		));

		$this->template->content = $content;
	}

	/**
	 * Карточка события + история попыток.
	 */
	public function action_log()
	{
		$id = (int) $this->request->param('id');
		$model = Model::factory('parsec2');

		$event = NULL;
		$attempts = array();
		$error = NULL;

		try
		{
			$event = $model->get_event($id);

			if ($event)
			{
				$attempts = $model->get_event_attempts($id);
			}
		}
		catch (Exception $e)
		{
			$error = 'Не удалось прочитать событие #'.$id.': '.$e->getMessage();
			Kohana::$log->add(Log::ERROR, $error);
		}

		if ( ! $event && $error === NULL)
		{
			throw HTTP_Exception::factory(404, 'Событие #:id не найдено в PARSEC_QUEUE', array(':id' => $id));
		}

		$back = $this->_back_query();

		$content = View::factory('parsec/parsec2_detail', array(
			'event'    => $event,
			'attempts' => $attempts,
			'error'    => $error,
			'back'     => $back,
		));

		$this->template->content = $content;
	}

	/**
	 * Повторить событие (статусы 3 и 4 → 0).
	 */
	public function action_repeat()
	{
		$this->_change_state('repeat_event');
	}

	/**
	 * Отменить событие (статусы 0 и 3 → 6).
	 */
	public function action_cancel()
	{
		$this->_change_state('cancel_event');
	}

	/**
	 * Общая часть repeat/cancel: только POST, затем flash + возврат к списку.
	 *
	 * @param   string  $method  имя метода модели
	 * @return  void
	 */
	protected function _change_state($method)
	{
		$id = (int) $this->request->post('id');

		if ($this->request->method() !== 'POST' OR $id <= 0)
		{
			$this->redirect('parsec2');
			return;
		}

		$model = Model::factory('parsec2');

		try
		{
			$result = $model->$method($id);
		}
		catch (Exception $e)
		{
			Kohana::$log->add(Log::ERROR, 'PARSEC_QUEUE: '.$method.' #'.$id.': '.$e->getMessage());
			$result = array(
				'ok'      => FALSE,
				'message' => 'Ошибка базы данных: '.$e->getMessage(),
			);
		}

		Session::instance()->set('flash_message', array(
			'type' => $result['ok'] ? 'success' : 'error',
			'text' => $result['message'],
		));

		$this->redirect('parsec2'.$this->_back_query());
	}

	/**
	 * Сохранённая строка фильтров для возврата после действия.
	 *
	 * @return  string  '?a=b' или ''
	 */
	protected function _back_query()
	{
		$back = (string) $this->request->post('back');

		if ($back === '')
		{
			$back = (string) $this->request->query('back');
		}

		// Разрешаем только безопасный query-string: буквы, цифры и разделители.
		if ($back === '' OR ! preg_match('/^[A-Za-z0-9_.%&=+\-]+$/', $back))
		{
			return '';
		}

		return '?'.$back;
	}

}
