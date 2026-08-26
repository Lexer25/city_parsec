<div class="alert alert-danger" style="margin: 20px; padding: 20px; border-radius: 5px;">
    <h4>
        <span class="glyphicon glyphicon-exclamation-sign"></span> 
        Ошибка структуры базы данных
    </h4>
    <p style="font-size: 16px; margin-top: 15px;">
        <?php echo isset($message) ? htmlspecialchars($message) : 'Неизвестная ошибка'; ?>
    </p>
    <p style="margin-top: 15px;">
        <strong>Модуль Parsec не может работать без этих полей.</strong>
    </p>
    <p>
        Добавьте поля <code>GUID</code> в таблицы <code>PEOPLE</code> и <code>ORGANIZATION</code>.
    </p>
</div>