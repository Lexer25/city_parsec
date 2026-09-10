

 
 <?php
// ===== ШАПКА В САМОМ НАЧАЛЕ =====
echo View::factory('parsec/_nav');
// ===== КОНЕЦ ШАПКИ =====


echo View::factory('parsec/_mock_alert');
 
 if(isset($guid_pep)){
	 
 } else {
	 
	 $guid_pep='441dc23b-1111-44d2-a999-3991FF028067';
 }
 
 if(isset($card)){
	 
 } else {
	 
	 $card='002ABF38';
 }
 
 if(isset($guid_org)){
	 
 } else {
	 
	 $guid_org='441dc23b-2222-44d2-a999-131ff0280673';
 }
 
 
 if(isset($guid_access)){
	 
 } else {
	 
	 $guid_access='2ca7501c-731b-462f-bba4-87f76628f28a';
 }
 
 
 if(isset($guid)){
	 
 } else {
	 
	 $guid='2ca7501c-731b-462f-bba4-87f76628f28a';
 }
 
 
 

 
 ?>
 
 <!-- БЛОК СОДЕРЖИМОЕ ОТВЕТА -->

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo __('Результат выполнения команды');?></h3>
    </div>
    <div class="panel-body">
        <p><?php echo __('Получить справку по командам SOAP PARSEC');?></p>
  	<a href="http://127.0.0.1:10101/IntegrationService/IntegrationService.asmx?">http://127.0.0.1:10101/IntegrationService/IntegrationService.asmx?wsdl</a>
	
<?php
       
        
        if (isset($result)) {
       // echo Debug::vars('14', $result);//exit;
          ?>  
            <div style="position: relative;">
                <button onclick="copyToClipboard()" style="position: absolute; top: 5px; right: 5px; z-index: 10; padding: 5px 10px; background: #337ab7; color: white; border: none; border-radius: 3px; cursor: pointer;">
                    Копировать
                </button>
                <pre id="config-content" style="background-color: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: monospace; font-size: 12px; max-height: 500px; margin-top: 10px;"><?php echo Debug::vars($result); ?></pre>
            </div>
            
            <script>
            function copyToClipboard() {
                var pre = document.getElementById('config-content');
                var range = document.createRange();
                range.selectNode(pre);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                window.getSelection().removeAllRanges();
                alert('Ответ скопирован в буфер обмена');
            }
            </script>
            
        <?php } else { ?>
            <div class="alert alert-danger">Ответа нет или он пустой</div>
        <?php } ?>
    </div>
</div>
 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">GetObjectName Определение типа сущности по GUID (21.03.2026)</h3>
  </div>
	<form role="form" action="GetObjectName" method="POST">
		<div class="form-group">
			<label for="actionName">Номер GUID для определения</label>
			<input type="text" name="guid" class="form-control"  placeholder="Код категории доступа в формате GUID" value="<?php echo $guid;?>"/>
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>


<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Добавить идентификатор персоне</h3>
  </div>
	<form role="form" action="AddIdentifier" method="POST">
		<div class="form-group">
			<label for="actionName">Персона</label>
			<input type="text" name="guid_pep" class="form-control"  placeholder="Код категории доступа в формате GUID" value="<?php echo $guid_pep;?>"/>
			<label for="actionName">Идентификатор</label>
			<input type="text" name="card" class="form-control"  placeholder="Код категории доступа в формате GUID" value="<?php echo $card;?>"/>
			
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>


 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Удалить идентификатор</h3>
  </div>
	<form role="form" action="DeleteIdentifier" method="POST">
		<div class="form-group">
			
			<label for="actionName">Идентификатор</label>
			<input type="text" name="card" class="form-control"  placeholder="Код категории доступа в формате GUID" value="<?php echo $card;?>"/>
			
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>


 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Удалить персону</h3>
  </div>
	<form role="form" action="DeletePerson" method="POST">
		<div class="form-group">
			
			<label for="actionName">GUID persone</label>
			<input type="text" name="guid_pep" class="form-control"  placeholder="Код категории доступа в формате GUID" value="<?php echo $guid_pep;?>"/>
			
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>


 
<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">GetInheritedAccessGroups получить унаследованные категории доступа (21.03.2026)</h3>
  </div>
	<form role="form" action="GetInheritedAccessGroups" method="POST">
		<div class="form-group">
			<label for="actionName">Код категории доступа в формате GUID</label>
			<input type="text" name="guid_access" class="form-control"  placeholder="Код категории доступа в формате GUID" value="<?php echo $guid_access;?>"/>
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>




 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Свойства идентификатора GetIdentifierExtraData (21.03.2026)</h3>
  </div>
	<form role="form" action="GetIdentifierExtraData" method="POST">
		<div class="form-group">
			<label for="actionName">Код идентификатора карты в 16-ричном формате</label>
			<input type="text" name="card" class="form-control"  placeholder="Код идентификатора карты в 16-ричном
формате" value="<?php echo $card;?>"/>
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>

 
 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Поиск people</h3>
  </div>
	<form role="form" action="search" method="POST">
		<div class="form-group">
			<label for="actionName">Укажите guid people</label>
			<input type="text" name="guid_pep" class="form-control"  placeholder="Укажите номер билета" value="<?php echo $guid_pep;?>"/>
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
</div>

 
 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">OpenPersonEditingSession</h3>
  </div>
	<form role="form" action="OpenPersonEditingSession" method="POST">
		<div class="form-group">
			<label for="actionName">Укажите guid people</label>
			<input type="text" name="guid_pep" class="form-control"  placeholder="Укажите номер билета" value="<?php echo $guid_pep;?>"/>
		</div>
		<button type="submit" class="btn btn-default">Отправить</button>
	</form>
	Описание:  Открывает сессию редактирования  субъекта доступа. Возвращает 
ключ вновь созданной сессии.
</div>

 <div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">GetPersonIdentifiers</h3>
  </div>
  <form role="form" action="GetPersonIdentifiers" method="POST">
	  <div class="form-group">
		<label for="actionName">Укажите guid people</label>
		<input type="text" name="guid_pep" class="form-control"  placeholder="Укажите номер билета" value="<?php echo $guid_pep;?>"/>
	  </div>
	<button type="submit" class="btn btn-default">Отправить</button>
</form>
 
</div>


<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Поиск people по коду идентификатора</h3>
  </div>
  <form role="form" action="searchCard" method="POST">
	  <div class="form-group">
		<label for="actionName">Укажите номер идентификатора</label>
		<input type="text" name="card" class="form-control"  placeholder="Укажите номер идентификатор" value="<?php echo $card;?>"/>
	  </div>
	<button type="submit" class="btn btn-default">Отправить</button>
</form>
 
</div>


<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Поиск организации по GUID</h3>
  </div>
  <form role="form" action="searchOrg" method="POST">
	  <div class="form-group">
		<label for="actionName">Укажите GUID организации</label>
		<input type="text" name="guid_org" class="form-control" placeholder="Укажите GUID организации" value="<?php echo $guid_org;?>"/>
	  </div>
	<button type="submit" class="btn btn-default">Отправить</button>
</form>
 
</div>



<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">compareOrg Сверка организаций</h3>
  </div>
  <form role="form" action="compareOrg" method="POST">
	 <label>
        <input type="checkbox" name="addOrg" value="1">Добавлять в задачи
      </label>
<br>
	<button type="submit" class="btn btn-default">Отправить</button>
</form>
 Результат: список организаций, которые есть в БД СКУД Артонит, но не в БД ПАРСЕК.
</div>

<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">comparePeople Сверка персон</h3>
  </div>
  <form role="form" action="comparePeople" method="POST">
	 <label>
        <input type="checkbox" name="addPeople" value="1">Добавлять в задачи
      </label>
<br>
	<button type="submit" class="btn btn-default">Отправить</button>
</form>
 Результат: список персон, которые есть в БД СКУД Артонит, но не в БД ПАРСЕК.
</div>


<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">Проверка очереди задача для интегратора</h3>
  </div>
  <form role="form" action="chectTaskOrder" method="POST">
	<button type="submit" class="btn btn-default">Отправить</button>
</form>

</div>


<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">GetAccessGroups Получить список категорий доступа</h3>
  </div>
  <form role="form" action="getAccess" method="POST">
	<button type="submit" class="btn btn-default">Отправить</button>
</form>

</div>


<div class="panel panel-primary col-md-10 col-md-offset-1">
  <div class="panel-heading row">
    <h3 class="panel-title ">GetOrgUnitsHiearhy Получить иерархию подразделений</h3>
  </div>
  <form role="form" action="GetOrgUnitsHierarhy" method="POST">
	<button type="submit" class="btn btn-default">Отправить</button>
</form>

</div>


