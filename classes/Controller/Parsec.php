<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Parsec extends Controller_Template {
	
	
	public $template = 'template';
	public function before()
	{
			
			parent::before();
			$session = Session::instance();
			//echo Debug::vars('9', $_POST, $_GET);
			I18n::load('parsec');
			$this->template->full_width = true;
			
	}

	public function action_index_()
	{
		$_SESSION['menu_active']='parsec';
		//echo Debug::vars('20', $_SESSION);
		

		$task_list=Model::Factory('parsec')->get_task_list();
		$content = View::factory('parsec/parsec', array(
			'task_list'=>$task_list,
			
		
		));
        $this->template->content = $content;
		
	}
	
	/** 23.11.2025 обнуляется attempt для указанного id_cardindev
	*/
	public function action_repeat()//
	{
		$id_cardindev = $this->request->param('id');
		Model::factory('parsec')->set_id_cardindev(array($id_cardindev=>$id_cardindev));
		$this->redirect('parsec');
		
	}
	
	
	/**23.11.2025 удалеяет указанный id_cardindev
	*/
	public function action_delete()//
	{
		$id_cardindev = $this->request->param('id');
		Model::factory('parsec')->delete_id_cardindev(array($id_cardindev=>$id_cardindev));
		$this->redirect('parsec');
		
	}
	
	
	
	
	public function action_edit_parsec()//
	{
		$_SESSION['menu_active']='kp_park_menu';
		//echo Debug::vars('43', $_GET, $_POST, $this->request->param('id')); exit;
		$id_parsec = $this->request->param('id');
		$parsec_getinfo=Model::Factory('parsec')->get_info_parsec($id_parsec); //получить лист точек прохода, уже входящих в периметр
		$parsec_device_list=Model::Factory('parsec')->get_list_dev($id_parsec); //получить лист точек прохода, уже входящих в периметр
		$door_list=Model::Factory('parsec')->get_door_list_not_parsec($id_parsec); //получить лист точек прохода, не входящих в периметр
		$people_list_inside=Model::Factory('parsec')->get_people_list_inside($id_parsec); //получить лист точек прохода, не входящих в периметр
		//echo Debug::vars('45', $people_list_inside); exit;
		
		$content = View::factory('parsec/edit_parsec', array(
			'parsec_device_list'=>$parsec_device_list,
			'door_list'=>$door_list,
			'id_parsec'=>$id_parsec,
			'parsec_getinfo'=>$parsec_getinfo,
			'people_list_inside' => $people_list_inside
		));
        $this->template->content = $content;
		
	}
	
	

	public function action_parsec_control()
	{
		//echo Debug::vars('30', $_GET, $_POST); exit;
		
		$todo = $this->request->post('todo');
		switch ($todo){
			case 'set_attempt'://сбрость счетчики в ноль.
				$id_cardindev = $this->request->post('id_cardindev');
				
				Model::factory('parsec')->set_id_cardindev($id_cardindev);
				$this->redirect('parsec');
			break;
			
			case 'delAllTasks'://удалить все задачи
			
				//$del_parsec = $this->request->post('id_parsec');
				Model::factory('parsec')->dellAllTasks();
				$this->redirect('parsec');
			break;
			
			case 'edit_parsec'://
				$post=Validation::factory($this->request->post());
				$post->rule('id_parsec', 'not_empty')
						->rule('id_parsec', 'digit')
						;
				
				if($post->check())
				{
					$this->redirect('parsec/edit_parsec/'.Arr::get($post, 'id_parsec'));
				} else 
				{
					Session::instance()->set('e_mess', $post->errors('parsec'));
					$this->redirect('parsec');
				}
		
			break;
			
			
			
			default:
				//echo Debug::vars('56', $_GET, $_POST); exit;
			break;
		}
		$content='';
        $this->template->content = $content;
		
	}
	//==================== 31.03.2026
	public function action_index()
	{
		$_SESSION['menu_active'] = 'parsec';
		
		$task_list = Model::Factory('parsec')->get_task_list();
		
		// Получение состояния из файла state.txt
		$service_state = $this->get_service_state();
		
		$content = View::factory('parsec/parsec', array(
			'task_list'     => $task_list,
			'service_state' => $service_state, // Добавляем состояние в view
		));
		$this->template->content = $content;
	}

	/**
	 * Получить текущее состояние сервиса из файла state.txt
	 * @return array Массив с ключами: content (содержимое), error (ошибка), file_path, file_exists, file_mtime
	 */
	private function get_service_state()
	{
		$state_file_path = 'D:\\Buro\\state.txt';
		$result = array(
			'content'    => '',
			'error'      => '',
			'file_path'  => $state_file_path,
			'file_exists'=> false,
			'file_mtime' => null
		);
		
		if (file_exists($state_file_path)) {
			$result['file_exists'] = true;
			$result['file_mtime'] = filemtime($state_file_path);
			
			$content = @file_get_contents($state_file_path);
			if ($content === false) {
				$result['error'] = __('Не удалось прочитать файл state.txt');
			} else {
				// Обрезаем лишние пробелы и переносы строк
				$result['content'] = trim($content);
			}
		} else {
			$result['error'] = __('Файл state.txt не найден по пути: :path', array(':path' => $state_file_path));
		}
		
		return $result;
	}
	
	
} 
