<?php echo View::factory('parsec/_nav'); ?>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">
            <span class="glyphicon glyphicon-list-alt"></span>
            Лог: <?php echo htmlspecialchars($log_file, ENT_QUOTES, 'UTF-8'); ?>
        </h3>
    </div>
    <div class="panel-body">

        <p>
            <label>
                <input type="checkbox" id="auto-refresh" checked>
                Обновлять автоматически (3 сек)
            </label>
            &nbsp;|&nbsp;
            <span id="log-status" class="text-muted">—</span>
        </p>

        <pre id="log-content"
             style="background:#f5f5f5;padding:15px;border-radius:5px;
                    max-height:600px;overflow:auto;
                    font-family:Consolas,monospace;font-size:12px;
                    white-space:pre-wrap;"></pre>

        <a href="<?php echo URL::site('parsecrunner'); ?>" class="btn btn-default">
            ← К списку задач
        </a>
    </div>
</div>

<script>
(function () {
    var token = <?php echo json_encode($token); ?>;
    var url   = <?php echo json_encode(URL::site('parsecrunner/log/' . $token) . '?ajax=1'); ?>;
    var pre   = document.getElementById('log-content');
    var stat  = document.getElementById('log-status');
    var chk   = document.getElementById('auto-refresh');
    var timer = null;

    function load() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', url + '&_=' + Date.now(), true);
        xhr.onload = function () {
            if (xhr.status === 200) {
                pre.textContent = xhr.responseText;
                pre.scrollTop = pre.scrollHeight;
                stat.textContent = 'обновлено ' + new Date().toLocaleTimeString();
            } else {
                stat.textContent = 'ошибка ' + xhr.status;
            }
        };
        xhr.onerror = function () { stat.textContent = 'сетевая ошибка'; };
        xhr.send();
    }

    function schedule() {
        if (timer) clearInterval(timer);
        if (chk.checked) timer = setInterval(load, 3000);
    }

    chk.addEventListener('change', schedule);
    load();
    schedule();
})();
</script>