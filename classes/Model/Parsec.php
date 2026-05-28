<?php defined('SYSPATH') OR die('No direct access allowed.');

class Model_Parsec extends Model {
	
	
	
	/*
	26.04.203 Получить список задач для интегратора
	
	*/
	public function get_task_list()
	{
	
		$sql='SELECT cd.id_cardindev as id,
				--выборка второго поля:
				--1 или 2 - это номер карты,
				--3 - ничего (добавление контакта)
				--4 (удаление контакта)
				-- 5 добавить организаци
				-- 6 удалить организацию
				-- 7 - это номер категории доступа
				-- 8 удалить категории доступа
				
				-- колонка 2 что id_card
						case 
				  when cd.operation in (1,2, 9, 10)  THEN cd.id_card
				  
				  when cd.operation in (3)  THEN COALESCE( (SELECT p.surname||\' \'||p.name||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\', \'Not found id_pep=\'||cd.id_pep)
					when cd.operation =4 then \'id_pep=\'||cd.id_pep
					
					
				  when cd.operation in( 7, 8)  THEN (SELECT an.name from accessname an where an.id_accessname=cd.id_card)

						end as id_card,
	
				   -- колонка 3 кому id_pep
							 case
				 -- when cd.operation in (1,2,9, 10, 3, 7, 8)  THEN (SELECT p.surname||\' \'||p.name||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\'
				 
				when cd.operation in (1, 2, 9, 10,   7, 8)  THEN COALESCE( (SELECT p.surname||\' \'||p.name||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\', \'Not found id_pep=\'||cd.id_pep)
				--when cd.operation in ( 3, 4)  THEN COALESCE( (SELECT p.surname||\' \'||p.name||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)||\' (\'||cd.id_pep||\')\', \'Not found id_pep=\'||cd.id_pep)
				WHEN cd.operation in( 3) THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
 
						end as id_pep,


 				--cd.id_pep, 
					cd.operation, 
					cd.attempts, 
					cd.time_stamp,
				-- организация кому добавляем или удаляем
					 CASE
				  WHEN cd.operation in (1,2,9, 10) THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				
				  WHEN cd.operation = 3 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  
				  WHEN cd.operation = 5 THEN (SELECT o.name   FROM organization o WHERE o.guid = cd.id_card)
				 -- WHEN cd.operation = 3 THEN (SELECT p.name||\' \'||p.surname||\' \'||p.patronymic   FROM people p WHERE p.id_pep = cd.id_pep)
				  WHEN cd.operation = 7 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  WHEN cd.operation = 8 THEN (SELECT o.name from people p join organization o on p.id_org=o.id_org  WHERE p.id_pep = cd.id_pep)
				  END as org_name,
				   case
				  when cd.operation in(1, 2, 9, 10) then (

                    select s.name from device d
                    join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
                    join server s on d2.id_server=s.id_server

                    where  d.id_dev=cd.id_dev
                    )
				  when cd.operation in (3, 4, 5, 6) then (
					select s.name from server s
					join servertypelist stl on stl.id_server =s.id_server
					join servertype sst on sst.id=stl.id_type
					where sst.sname=\'parsec\'
					)

				  when cd.operation in (7, 8) then (
					select first 1 s.name from access  a
					join device d on d.id_dev=a.id_dev
					 join device d2 on d2.id_ctrl=d.id_ctrl and d2.id_reader is null
					 join server s on s.id_server=d2.id_server
					 where a.id_accessname=cd.id_card
					 )
				  
				 	  
				  
				  end as dest
				  
					
				FROM cardindev cd 
where cd.operation not in (1,2)
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
