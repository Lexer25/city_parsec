<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Cch extends Controller_Template {
	
	private $_ConnectionState = false;//состояние связи с SOAP сервером false-связи нет,  true-связь есть 
	
	public function before()
	{
	
		parent::before();
		$cch = new Model_Cch();
		$status = $cch->getConnectionStatus();

		if ($status->error) {
			// Сервер недоступен
			echo $status->message; // "Не удалось подключиться к серверу Parsec за 5 секунд"
			echo Debug::vars('17 no connection');//exit;
			
			
		} else {
			echo Debug::vars('21');//exit;
			$this->_ConnectionState = true;
			echo $status->message; // "Соединение установлено, время ответа: 120 мс"
		}
		
		

	}	
		
		
	public $template = 'template';
	
	
	
	public function action_err()
	{
		echo Debug::vars('34', 'err', $this->request->param('id')); exit;
		$content=View::factory('error_page');
	$this->template->content = $content;
	}
	
	/**27.11.2025 Получить список категорий доступа
	*/
	public function action_getAccess()
	{
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->GetAccessGroups($OpenSession->OpenSessionResult->Value->SessionID);// 
		//$GetPerson=$pars->GetAccessGroups('dc64435e-6826-4bde-84b2-952fb60e5e86');// 28.11.2025 в программе сюда подставляется категория доступа, хотя ожидают GUID сессии. не в этом ли ошибка?
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
            
			;
		$this->template->content = $content;
		
	}
	
	
	/**27.11.2025 GetPersonIdentifiers
	*/
	public function action_GetPersonIdentifiers()
	{
		//echo Debug::vars('48', $_POST); exit;
		$guid_pep = $this->request->post('guid_pep');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->GetPersonIdentifiers($OpenSession->OpenSessionResult->Value->SessionID, $guid_pep);// 
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
            
			;
		$this->template->content = $content;
		
	}
	
	
	
	
	/**09.04.2026 AddIdentifier добавить идентификатор пиплу
	441dc23b-1111-44d2-a999-12061FF02806
	people_1610 в Артсек
	
	*/
	public function action_AddIdentifier()
	{
		//echo Debug::vars('48', $_POST); exit;
		$guid_pep = $this->request->post('guid_pep');
		$card = $this->request->post('card');
		
		//$guid_pep = '441dc23b-1111-44d2-a999-12061FF02806';
		//$id_card = '00ABCDEF';
		
		
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->AddPersonIdentifier($OpenSession->OpenSessionResult->Value->SessionID, $card, $guid_pep);// 
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
            
			;
		$this->template->content = $content;
		
	}
	
	/**09.04.2026 DelIdentifier  удалить идентификатор
	441dc23b-1111-44d2-a999-12061FF02806
	people_1610 в Артсек
	
	*/
	public function action_DeleteIdentifier ()
	{
		//echo Debug::vars('48', $_POST); exit;
		$card = $this->request->post('card');
		
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->DeleteIdentifier($OpenSession->OpenSessionResult->Value->SessionID, $card);// 
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
            
			;
		$this->template->content = $content;
		
	}
	
	
	/**14.04.2026 DeletePerson  удалить persone
	
	
	*/
	public function action_DeletePerson ()
	{
		//echo Debug::vars('119', $_POST); exit;
		$card = $this->request->post('guid_pep');
		
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->DeletePerson($OpenSession->OpenSessionResult->Value->SessionID, $card);// 
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
            
			;
		$this->template->content = $content;
		
	}
	
	
	
	
	public function action_index()
	{
	 if ($this->request->method() === 'POST') {
            //echo Debug::vars('68', $_POST); exit;
			$this->_addAccessName($_POST);
			$this->_mainView();
        } else {
			
			// echo Debug::vars('123', $_POST); exit;
            $this->_mainView();
        }
		
	}
	
	
	public function _mainView()
	{
		$pars=Model::Factory('cch');
		
		$version=$pars->getParsecSoapVersion();// версия SOAP-сервера
		// echo Debug::vars('133', $version); //exit;
		$OpenSession=$pars->OpenSession();// открыта сессия
	
	echo Debug::vars('133', $this->_ConnectionState); //exit;
		 echo Debug::vars('133--', $OpenSession->error); //exit;
		 
		$version=null;
		$OpenSession=null;
		$GetDomains=null;
		$GetRootOrgUnit=null;
		$GetOrgUnitsHierarhy=null;
		$GetAccessGroups=null;
		$getAccessArtonit=null;
		
			if($this->_ConnectionState){
				$GetDomains=$pars->GetDomains();// домен текущего авторизованного пользователия
				$GetRootOrgUnit=$pars->GetRootOrgUnit($OpenSession->OpenSessionResult->Value->SessionID);// организация текущего пользователя
				//$GetOrgUnitsHierarhy=$pars->GetOrgUnitsHierarhy($OpenSession->OpenSessionResult->Value->SessionID);//  список всех организаций 
				$GetOrgUnitsHierarhy=null;
				
				$GetAccessGroups=$pars->GetAccessGroups($OpenSession->OpenSessionResult->Value->SessionID);//  список всех категорий доступа 
				$getAccessArtonit=$this->_getAccessArtonit();
				//echo Debug::vars('29', $getAccessArtonit); exit;
			}
		$content = View::factory('cch/dashboard', array(
			'version'=>$version,
			'OpenSession'=>$OpenSession,
			'GetDomains'=>$GetDomains,
			'GetRootOrgUnit'=>$GetRootOrgUnit,
			'GetOrgUnitsHierarhy'=>$GetOrgUnitsHierarhy,
			'GetAccessGroups'=>$GetAccessGroups,
			'getAccessArtonit'=>$getAccessArtonit,
		
			));
        $this->template->content = $content;
        //echo View::factory('profiler/stats');
		
	}
	


	public function action_search()
	{
		//echo Debug::vars('64'); exit;
		 if ($this->request->method() === 'POST') {
			//echo Debug::vars('66', $_POST); exit; 
            $this->_search_from_post();
        } else {
            $this->_show_search_form();
        }
	}
	

	public function action_OpenPersonEditingSession()
	{
		$guid_pep = $this->request->post('guid_pep');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->OpenPersonEditingSession($OpenSession->OpenSessionResult->Value->SessionID, $guid_pep);// 
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
             ->set('guid_pep', $guid_pep)
			;
		$this->template->content = $content;
	}
	

	/**
     * Показать форму поиска по guid_pep
     */
    protected function _show_search_form()
    {
        $this->template->content = View::factory('cch/search')
            // ->set('available_classes', $available_classes)
            // ->set('errors', array());
			;
    }
	

/**
     * Показать форму результата поиска по guid_pep
     */
    protected function _search_from_post()
    {
		$guid_pep = $this->request->post('guid_pep');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetPerson=$pars->GetPerson($OpenSession->OpenSessionResult->Value->SessionID, $guid_pep);// 
		$content = View::factory('cch/search')
             ->set('result', $GetPerson)
             ->set('guid_pep', $guid_pep)
			;
		$this->template->content = $content;
    }
	


	public function action_searchCard()
	{
		//echo Debug::vars('64'); exit;
		 if ($this->request->method() === 'POST') {
			//echo Debug::vars('66', $_POST); exit; 
            $this->_search_card_from_post();
        } else {
            $this->_show_search_card_form();
        }
	}
	

	/** 21.03.2026
     * Выдает сведения о дополнительных свойствах идентификатора
     */
    protected function action_GetIdentifierExtraData()
    {
		$card = $this->request->post('card');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetIdentifierExtraData=$pars->GetIdentifierExtraData($OpenSession->OpenSessionResult->Value->SessionID, $card);// 
		$content = View::factory('cch/search')
             ->set('result', $GetIdentifierExtraData)
             ->set('card', $card)
			;
		$this->template->content = $content;
    }
	

	/** 21.11.2025
     * получить название сущности по ее строке guid
     */
    protected function action_GetObjectName()
    {
		$guid = $this->request->post('guid');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetObjectName=$pars->GetObjectName($OpenSession->OpenSessionResult->Value->SessionID, $guid);// 
		//echo Debug::vars('203',$GetInheritedAccessGroups );exit;
		$content = View::factory('cch/search')
             ->set('result', $GetObjectName)
             ->set('guid', $guid)
			;
		$this->template->content = $content;
    }
	

	/** 21.11.2025
     * получать дочерние категории доступа по родительскому guid
     */
    protected function action_GetInheritedAccessGroups()
    {
		$guid_access = $this->request->post('guid_access');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetInheritedAccessGroups=$pars->GetInheritedAccessGroups($OpenSession->OpenSessionResult->Value->SessionID, $guid_access);// 
		//echo Debug::vars('203',$GetInheritedAccessGroups );exit;
		$content = View::factory('cch/search')
             ->set('result', $GetInheritedAccessGroups)
             ->set('guid_access', $guid_access)
			;
		$this->template->content = $content;
    }
	

	
	/** 21.11.2025
     * результат поиска по guid_pep
     */
    protected function _search_card_from_post()
    {
		$card = $this->request->post('card');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$FindPersonByIdentifier=$pars->FindPersonByIdentifier($OpenSession->OpenSessionResult->Value->SessionID, $card);// 
		$content = View::factory('cch/search')
             ->set('result', $FindPersonByIdentifier)
             ->set('card', $card)
			;
		$this->template->content = $content;
    }
	

	/** поиск организации
	*/
	public function action_searchOrg()
	{
		//echo Debug::vars('64'); exit;
		 if ($this->request->method() === 'POST') {
			//echo Debug::vars('66', $_POST); exit; 
            $this->_search_org_from_post();
        } else {
            $this->_show_search_card_form();
        }
	}
	

	/** 21.11.2025
     * результат поиска организации по guid_pep
     */
    protected function _search_org_from_post()
    {
		$guid_org = $this->request->post('guid_org');
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$GetOrgUnit=$pars->GetOrgUnit($OpenSession->OpenSessionResult->Value->SessionID, $guid_org);// 
		$content = View::factory('cch/search')
             ->set('result', $GetOrgUnit)
             ->set('guid_org', $guid_org)
			;
		$this->template->content = $content;
    }
	


	/** 26.11.2025
     * сравнение организаций в БД СКУД и в БД Парсек.
	 * на экран будет выведен список организаций, которые есть в БД СКУД, но не в БД Парсек
     */
    public function action_compareOrg()
    {
		
		$addOrg=false;
		if(isset($_POST['addOrg'])) $addOrg=true;
		//echo Debug::vars('171', $_POST, $addOrg); exit;
		$original_time_limit = ini_get('max_execution_time');
		set_time_limit(300); // 5 минут
		
		$orgList=$this->_getOrgList();//получить список всех организаций
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$resultList=array();
		//echo Debug::vars('174', $orgList); exit;
		foreach($orgList as $key=>$value)
		{
			//echo Debug::vars('174', $value); //exit;
			//echo Debug::vars('177', empty((array)$pars->GetOrgUnit($OpenSession->OpenSessionResult->Value->SessionID, '441dc23b-2222-44d2-a999-1ff0280673fa'))); exit;
			//если объект пустой, то фиксируею его в результатах
			if(empty((array)$pars->GetOrgUnit($OpenSession->OpenSessionResult->Value->SessionID, Arr::get($value, 'GUID'))))
			{
				$resultList[]=Arr::get($value, 'GUID');
				if($addOrg) $this->_addOrgListTask(Arr::get($value, 'GUID')); 
				//echo Debug::vars('188', $value); exit;
				

			}				
			//echo Debug::vars('179', $resultList); exit;
		}
		
		set_time_limit($original_time_limit);
		
		$content = View::factory('cch/search')
             ->set('result', $resultList)
            
			;
		$this->template->content = $content;
    }
	
	
	/** 27.11.2025
     * Проверка очереди задач интегратора (таблица cardInDev).
	 * если есть команда 5 (добавить организацию), а организация уже имеется в БД ПАРсек, то эта запись будет удалена.
     */
    public function action_chectTaskOrder()
    {
		$original_time_limit = ini_get('max_execution_time');
		set_time_limit(300); // 5 минут
		
		$orgList=$this->_getOrgListTask();//получить список организация для записи в Парсеке
		//echo Debug::vars('211', $orgList); //exit;
		$pars=Model::Factory('cch');
		$OpenSession=$pars->OpenSession();
		$resultList=array();
		foreach($orgList as $key=>$value)
		{

			//echo Debug::vars('177', empty((array)$pars->GetOrgUnit($OpenSession->OpenSessionResult->Value->SessionID, '441dc23b-2222-44d2-a999-1ff0280673fa'))); exit;
			//если объект пустой, то фиксируею его в результатах
			if(!empty((array)$pars->GetOrgUnit($OpenSession->OpenSessionResult->Value->SessionID, Arr::get($value, 'GUID'))))
			{
				$resultList[]=Arr::get($value, 'GUID');
				$this->_delOrgListTask(Arr::get($value, 'GUID'));

			}				
			//echo Debug::vars('179', $resultList); exit;
		}
		
		set_time_limit($original_time_limit);
		
		$content = View::factory('cch/search')
             ->set('result', $resultList)
            
			;
		$this->template->content = $content;
    }
	
	
	
	
	
	
	/** 26.11.2025
     * получить список организаций в БД СКУД
     */
    protected function _getOrgList()
    {
		$sql='select o.guid from organization o';
		return DB::query(Database::SELECT, $sql)
			->execute(Database::instance('fb'))
			->as_array();
    }
	
	/** 26.11.2025
     * получить список организаций из БД СКУД в Парсек
     */
    protected function _getOrgListTask()
    {
		$sql='select cd.id_card as guid from cardindev cd
		where cd.operation=5';
		return DB::query(Database::SELECT, $sql)
			->execute(Database::instance('fb'))
			->as_array();
    }
	
	/** 26.11.2025
     * получить список организаций из БД СКУД в Парсек
     */
    protected function _delOrgListTask($guid)
    {
		$sql=__('delete from cardindev cd
			where cd.operation=5
			and cd.id_card=\':guid\'', array(
			':guid'=>$guid,
			));
		//echo Debug::vars('275', $sql); exit;	
		return DB::query(Database::DELETE, $sql)
			->execute(Database::instance('fb'))
			;
    }
	
	/** 26.11.2025
     * добавить задачу по добавлению организации из БД СКУД Артонит в БД СКУД Парсек
     */
    protected function _addOrgListTask($guid)
    {
		$sql=__('INSERT INTO CARDINDEV (ID_DB,ID_CARD,DEVIDX,ID_DEV,OPERATION,ATTEMPTS, ID_PEP) VALUES (1,\':guid\',NULL,NULL,5,0, 1);', array(
			':guid'=>$guid,
			));
		//echo Debug::vars('275', $sql); exit;	
		try
        {
           DB::query(Database::INSERT, $sql)
			->execute(Database::instance('fb'))
			;
        }
        catch (Exception $e)
        {
            // Если соединение 'scheduler' не настроено, используем 'default'
            //return Database::instance();
        }
		
		return ;
    }
	
	/** 29.11.2025
     * получить список категорий досутпа из БД СКУД Артонит
     */
    protected function _getAccessArtonit()
    {
		$result=array();
		$sql=__('select an.id_accessname, an.name, an.time_stamp, an.guid from accessname an', array());
		
		try
        {
           $query=DB::query(Database::SELECT, $sql)
			->execute(Database::instance('fb'))
			;
			//преобразую массив т.о., чтобы ключом был guid	
		
			foreach ($query as $key=>$value)
			{
				$result[Arr::get($value, 'GUID')]['id']=Arr::get($value, 'ID');
				$result[Arr::get($value, 'GUID')]['name']=Arr::get($value, 'NAME');
				$result[Arr::get($value, 'GUID')]['time_stamp']=Arr::get($value, 'TIME_STAMP');
			}
		
			return $result;	
        }
        catch (Exception $e)
        {
            echo Debug::vars('386', $e); //exit;	
        }
		
		return ;
    }
	
	
	/** 29.11.2025
     * добавить категория доступа в БД СКУД Артонит
	* 	array(2) (
    *		"guid" => string(36) "febcf333-6340-4737-81df-0409ad389625"
    *		"name" => string(21) "(Особая) Artsec"
	*	)
    */
    protected function _addAccessName($data)
    {
		$result=array();
		if($this->uniqueGuid(Arr::get($data, 'guid')))
		{
			echo '555';
		} else {
			
			echo '666';
		}
		$sql=__('INSERT INTO ACCESSNAME (ID_DB,NAME,GUID) VALUES (1, \':name\', \':guid\')', array(
			':name'=>Arr::get($data,'name'),
			':guid'=>Arr::get($data,'guid'),
			));
		//echo Debug::vars('418', $data, $sql); exit;
		try
        {
		Log::instance()->add(Log::NOTICE, '425 '. iconv('UTF-8', 'windows-1251',$sql));          
		  $query=DB::query(Database::INSERT, iconv('UTF-8', 'windows-1251',$sql))
			->execute(Database::instance('fb'))
			;
			echo Debug::vars('418', $sql, $query); exit;
		
			
        }
        catch (Exception $e)
        {
           // echo Debug::vars('386', $e); //exit;	
        }
		
		return ;
    }
	
	public static function uniqueGuid ($guid) // проверка наличия guid в базе данных. Вдруг такого id_org нет...
	{
				// Check if the id_org already exists in the database
	$sql='select an.guid from accessname an
			where an.guid=\''.$guid.'\'';
	return  DB::query(Database::SELECT, $sql)
			->execute(Database::instance('fb'))
			->get('GUID');
	}
	
} // End cch
