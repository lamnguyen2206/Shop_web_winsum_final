<?php

declare(strict_types=1);

/**
 * @var array{
 *   tasks: list<array{key:string,label:string,count:int,url:string,priority:string,is_unread:bool}>,
 *   unread_total: int
 * } $adminTasksPayload
 */
$tasks = $adminTasksPayload['tasks'] ?? [];
?>

<div class="admin-panel admin-dashboard-panel--full admin-task-panel" id="admin-task-panel">
    <div class="admin-dashboard-panel-head">
        <h2>Việc cần xử lý</h2>
        <?php if ((int) ($adminTasksPayload['unread_total'] ?? 0) > 0): ?>
            <span class="admin-task-panel-badge"><?php echo (int) $adminTasksPayload['unread_total']; ?> mới</span>
        <?php endif; ?>
    </div>

    <?php if ($tasks === []): ?>
        <p class="admin-hint admin-task-panel-empty">Không có việc chờ xử lý. Tuyệt vời!</p>
    <?php else: ?>
        <ul class="admin-task-panel-list">
            <?php foreach ($tasks as $task): ?>
                <li class="admin-task-panel-item <?php echo e(adminTasksPriorityClass((string) $task['priority'])); ?><?php echo !empty($task['is_unread']) ? ' is-unread' : ''; ?>">
                    <div class="admin-task-panel-main">
                        <span class="admin-task-panel-count"><?php echo (int) $task['count']; ?></span>
                        <span class="admin-task-panel-label"><?php echo e($task['label']); ?></span>
                        <?php if (!empty($task['is_unread'])): ?>
                            <span class="admin-task-panel-new">Mới</span>
                        <?php endif; ?>
                    </div>
                    <a class="admin-link-action" href="<?php echo e($task['url']); ?>">Xử lý ngay</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
