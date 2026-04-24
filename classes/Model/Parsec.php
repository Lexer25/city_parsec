<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Parsec extends Model {
	
	
	
	/*
	26.04.203 Получить список задач для интегратора
	
	*/
	public function get_task_list()
	{
	
			$sql='SELECT 
				cd.id_cardindev as id, 
				cd.id_card, 
				cd.id_pep, 
				cd.operation, 
				cd.attempts, 
				cd.time_stamp,
				 CASE
				  WHEN cd.operation = 1 THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)
			  WHEN cd.operation = 5 THEN (SELECT o.name   FROM organization o WHERE o.guid = cd.id_card)
			  WHEN cd.operation = 3 THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)
			  WHEN cd.operation = 7 THEN (
					SELECT first 1 p.name||\' \'||p.surname||\' \'||p.patronymic||\' (\'||c.id_card||\') "\'||an.name||\'"\'  FROM people p
					left join card c on c.id_pep=p.id_pep
					left join accessname an on an.id_accessname=cd.id_card
					WHERE p.id_pep = cd.id_pep)
			  END as org_name,
			   case
			  when cd.operation = 1 then (
				select s.name from device d
				join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
				join server s on s.id_server=d2.id_server
				where d.id_dev=cd.id_dev
				)
			  when cd.operation = 2 then (select s.name from device d
				join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
				join server s on s.id_server=d2.id_server
				where d.id_dev=cd.id_dev)
			  when cd.operation = 3 then (select s.name from server s
				join servertypelist stl on stl.id_server =s.id_server
				join servertype sst on sst.id=stl.id_type
				where sst.sname=\'parsec\' )
			  when cd.operation = 4 then (4)
			  when cd.operation = 5 then (select s.name from server s
				join servertypelist stl on stl.id_server =s.id_server
				join servertype sst on sst.id=stl.id_type
				where sst.sname=\'parsec\')
			  when cd.operation = 6 then (6)
			  when cd.operation = 7 then (select first 1 s.name from access  a
				join device d on d.id_dev=a.id_dev
				 join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
				 join server s on s.id_server=d2.id_server
					where a.id_accessname=cd.id_card)
			  
			  when cd.operation = 8 then (select first 1 s.name from access  a
				join device d on d.id_dev=a.id_dev
				 join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
				 join server s on s.id_server=d2.id_server
					where a.id_accessname=cd.id_card)
			  
			  
			  end as dest
			  
				
			FROM cardindev cd 

			ORDER BY cd.id_cardindev';

		$sql='SELECT cd.id_cardindev as id,
				--выборка второго поля:
				--1 или 2 - это номер карты,
				--3 - ничего (добавление контакта)
				--4 (удаление контакта)
				-- добавить организаци
				-- 6 удалить организацию
				-- 7 - это номер категории доступа
				-- 8 удалить организацию
						case 
				  when cd.operation = 1  THEN cd.id_card
				  when cd.operation = 2  THEN cd.id_card
				  when cd.operation = 7  THEN (SELECT an.name from accessname an where an.id_accessname=cd.id_card)
				  when cd.operation = 8  THEN (SELECT an.name from accessname an where an.id_accessname=cd.id_card)

						end as id_card,
					--cd.id_card, 

				   -- колонка id_pep
							 case
				  when cd.operation = 1  THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\'
				  when cd.operation = 2  THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\'
				  when cd.operation = 3  THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\'
				  when cd.operation = 7  THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\'
				  when cd.operation = 8  THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\'

						end as id_pep,

					--cd.id_pep, 
					cd.operation, 
					cd.attempts, 
					cd.time_stamp,
				-- организация кому добавляем или удаляем
					 CASE
				  WHEN cd.operation = 1 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  WHEN cd.operation = 2 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  WHEN cd.operation = 3 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  
				  WHEN cd.operation = 5 THEN (SELECT o.name   FROM organization o WHERE o.guid = cd.id_card)
				  WHEN cd.operation = 3 THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)
				  WHEN cd.operation = 7 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  WHEN cd.operation = 8 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  END as org_name,
				   case
				  when cd.operation = 1 then (

					select s.name from server s
					join servertypelist stl on stl.id_server =s.id_server
					join servertype sst on sst.id=stl.id_type
					where sst.sname=\'parsec\'
					)
				  when cd.operation = 2 then (
					select s.name from server s
					join servertypelist stl on stl.id_server =s.id_server
					join servertype sst on sst.id=stl.id_type
					where sst.sname=\'parsec\'
					)
				  when cd.operation = 3 then (
					select s.name from server s
					join servertypelist stl on stl.id_server =s.id_server
					join servertype sst on sst.id=stl.id_type
					where sst.sname=\'parsec\'
					)
				  when cd.operation = 4 then (4)
				  when cd.operation = 5 then (
					select s.name from server s
					join servertypelist stl on stl.id_server =s.id_server
					join servertype sst on sst.id=stl.id_type
					where sst.sname=\'parsec\'
					)
				  when cd.operation = 6 then (6)
				  when cd.operation = 7 then (
					select first 1 s.name from access  a
					join device d on d.id_dev=a.id_dev
					 join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
					 join server s on s.id_server=d2.id_server
					 where a.id_accessname=cd.id_card
					 )
				  
				  when cd.operation = 8 then (
					select first 1 s.name from access  a
					join device d on d.id_dev=a.id_dev
					 join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
					 join server s on s.id_server=d2.id_server
					 where a.id_accessname=cd.id_card
					 )
				  
				  
				  end as dest
				  
					
				FROM cardindev cd 

				ORDER BY cd.id_cardindev';
						$query = DB::query(Database::SELECT, $sql)
						->execute(Database::instance('fb'))
						->as_array();
		//echo Debug::vars('10',$sql, $query ); exit;
		return $query;
	}
	
	
	/*
	26.04.203 установить attempt =0 для указанных id_cardindev
	
	*/
	public function set_id_cardindev($list)
	{
		//echo Debug::vars('10',$list, implode(",", array_keys($list)) ); exit;

				$sql='update cardindev cd
						set cd.attempts=0
						where cd.id_cardindev in ('.implode(",", array_keys($list)).')';
						
				$sql_='update cardindev cd
						set cd.attempts=0';
//echo Debug::vars('78',$sql); exit;
						$query = DB::query(Database::UPDATE, $sql)
						->execute(Database::instance('fb'))
						;
		//echo Debug::vars('10',$sql, $query ); exit;
		return $query;
	}
	
	
	/*
	26.04.203 установить attempt =0 для указанных id_cardindev
	
	*/
	public function delete_id_cardindev($list)
	{
		//echo Debug::vars('10',$list, implode(",", array_keys($list)) ); exit;

				$sql='delete from cardindev cd
						
						where cd.id_cardindev in ('.implode(",", array_keys($list)).')';
						
				//echo Debug::vars('93',$sql ); exit;	
						$query = DB::query(Database::UPDATE, $sql)
						->execute(Database::instance('fb'))
						;
		//echo Debug::vars('10',$sql, $query ); exit;
		return $query;
	}
	
	
	/*
	26.11.2025 удалить все задачи
	
	*/
	public function dellAllTasks()
	{
		$sql='delete from cardindev';
	//echo Debug::vars('113',$sql); exit;					
				//echo Debug::vars('116',$sql ); exit;	
						$query = DB::query(Database::UPDATE, $sql)
						->execute(Database::instance('fb'))
						;
		
		return $query;
	}
	
	
	
	
	
}
