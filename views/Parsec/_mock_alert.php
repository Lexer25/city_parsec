<?php if (!empty($mock_mode)): ?>
<div class="alert alert-warning" style="margin-bottom: 20px; border-left: 5px solid #f0ad4e;">
    <strong><span class="glyphicon glyphicon-flash"></span> РЕЖИМ MOCK:</strong> 
    Реальный SOAP-сервер не используется. Все данные генерируются для отладки.
    <br><small>Отключите в <code>modules/parsec/config/soap.php</code> параметр <code>mock_mode</code></small>
</div>
<?php endif; ?>