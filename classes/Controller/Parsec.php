<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Parsec extends Controller_Template {
	
	
	public $template = 'template';
	
	 protected $_db_structure_error = null;
	 
	 
	public function before()
    {
        parent::before();
        
        // === ПРОВЕРКА СТРУКТУРЫ БД ===
        $cch_model = new Model_Cch();
        $db_errors = $cch_model->checkDatabaseStructure();
        if (!empty($db_errors)) {
            // Если мы уже на странице ошибки - просто показываем её
            if ($this->request->action() === 'error') {
                return;
            }
            // Иначе - сохраняем ошибку и редиректим
            Session::instance()->set('db_error', implode(' ', $db_errors));
            $this->redirect('parsec/error');
            return;
        }
        // ==============================
        
        $session = Session::instance();
        I18n::load('parsec');
        if (method_exists($this, 'set_full_width')) {
            $this->set_full_width(false);
        }
    }
    
    /**
     * Экшен для отображения ошибки структуры БД
     */
    public function action_error()
    {
        $error = Session::instance()->get('db_error', 'Ошибка структуры базы данных');
        $content = View::factory('parsec_error_page')->set('message', $error);
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
			
			case 'delAllTasks'://удалдить все задачи
			
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

public function action_index()
{
    $_SESSION['menu_active'] = 'parsec';
    
    $task_list = Model::Factory('parsec')->get_task_list();
    
    // Получение состояния из файла state.txt
    $service_state = $this->get_service_state();
    
    // === ДОБАВИТЬ: получаем признак mock-режима ===
    $soap_config = (array) Kohana::$config->load('soap.parsec');
    $mock_mode = isset($soap_config['mock_mode']) && $soap_config['mock_mode'] === true;
    // =============================================
    
    $content = View::factory('parsec/parsec', array(
        'task_list'     => $task_list,
        'service_state' => $service_state,
        'mock_mode'     => $mock_mode,   // ← передаём во view
    ));
    $this->template->content = $content;
}

	/**
	 * Получить текущее состояние сервиса из файла state.txt
	 * @return array Массив с ключами: content (содержимое), error (ошибка), file_path, file_exists, file_mtime
	 */
	private function get_service_state()
	{
		$state_file_path = 'C:\IntegrationClient\state.json';
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
