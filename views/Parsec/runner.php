<?php echo View::factory('parsec/_nav'); ?>

<?php
$err = Session::instance()->get_once('runner_error');
$inf = Session::instance()->get_once('runner_info');
if ($err) echo '<div class="alert alert-danger">' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
if ($inf) echo '<div class="alert alert-success">' . htmlspecialchars($inf, ENT_QUOTES, 'UTF-8') . '</div>';
?>

<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">
            <span class="glyphicon glyphicon-play-circle"></span>
            Запуск задач
        </h3>
    </div>
    <div class="panel-body">

        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th style="width:45%">Задача</th>
                    <th style="width:25%">Minion-таск</th>
                    <th style="width:20%">Параметры</th>
                    <th style="width:10%"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($groups as $group_name => $rows): ?>
                <tr class="info">
                    <td colspan="4"><strong><?php echo htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8'); ?></strong></td>
                </tr>
                <?php foreach ($rows as $t): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($t['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><code><?php echo htmlspecialchars($t['task'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                        <td>
                            <?php
                            $parts = array();
                            foreach ((array) Arr::get($t, 'params', array()) as $k => $v) {
                                $parts[] = '--' . $k . '=' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
                            }
                            echo $parts ? '<code>' . implode('<br>', $parts) . '</code>' : '<span class="text-muted">—</span>';
                            ?>
                        </td>
                        <td>
                            <form method="post" action="<?php echo URL::site('parsecrunner/run'); ?>" style="display:inline">
                                <input type="hidden" name="idx" value="<?php echo (int) $t['_idx']; ?>">
                                <button type="submit" class="btn btn-success btn-sm">
                                    <span class="glyphicon glyphicon-play"></span> Запустить
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </tbody>
        </table>

    </div>
</div>

<?php if (!empty($running)): ?>
<div class="panel panel-warning">
    <div class="panel-heading"><h3 class="panel-title">Сейчас выполняется</h3></div>
    <div class="panel-body">
        <table class="table table-condensed">
            <thead><tr><th>Задача</th><th>PID</th><th>Старт</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($running as $r): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($r['task'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?php echo (int) $r['pid']; ?></td>
                    <td><?php echo htmlspecialchars($r['started'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <a class="btn btn-xs btn-primary" target="_blank"
                           href="<?php echo URL::site('parsecrunner/log/' . $r['token']); ?>">
                            Открыть лог
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($recent)): ?>
<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">Последние логи</h3></div>
    <div class="panel-body">
        <table class="table table-condensed">
            <thead><tr><th>Файл</th><th>Размер</th><th>Изменён</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><code><?php echo htmlspecialchars($r['token'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                    <td><?php echo number_format($r['size']); ?> б</td>
                    <td><?php echo date('Y-m-d H:i:s', $r['mtime']); ?></td>
                    <td>
                        <a class="btn btn-xs btn-primary" target="_blank"
                           href="<?php echo URL::site('parsecrunner/log/' . $r['token']); ?>">
                            Открыть
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>