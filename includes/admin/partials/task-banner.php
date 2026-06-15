<?php

declare(strict_types=1);

/**
 * @var array{
 *   tasks: list<array{key:string,label:string,count:int,url:string,priority:string,is_unread:bool}>,
 *   unread_total: int,
 *   banner_visible: bool,
 *   fingerprint: string
 * } $adminTasksPayload
 */
if (empty($adminTasksPayload['banner_visible'])) {
    return;
}

$unreadTasks = array_values(array_filter(
    $adminTasksPayload['tasks'],
    static fn(array $t): bool => !empty($t['is_unread'])
));

if ($unreadTasks === []) {
    return;
}

require_once __DIR__ . '/../../csrf.php';
?>

<aside class="admin-task-banner" id="admin-task-banner" role="status" aria-live="polite">
    <div class="admin-task-banner-head">
        <strong>Việc cần xử lý (<?php echo (int) $adminTasksPayload['unread_total']; ?>)</strong>
        <button
            type="button"
            class="admin-task-banner-dismiss"
            data-admin-task-dismiss
            data-fingerprint="<?php echo e($adminTasksPayload['fingerprint']); ?>"
            aria-label="Ẩn thông báo đến khi có việc mới"
        >Ẩn</button>
    </div>
    <ul class="admin-task-banner-list">
        <?php foreach ($unreadTasks as $task): ?>
            <li class="admin-task-banner-item <?php echo e(adminTasksPriorityClass((string) $task['priority'])); ?>">
                <span class="admin-task-banner-text">
                    <strong><?php echo (int) $task['count']; ?></strong>
                    <?php echo e($task['label']); ?>
                </span>
                <span class="admin-task-banner-actions">
                    <a href="<?php echo e($task['url']); ?>">Xử lý</a>
                    <button
                        type="button"
                        class="admin-task-mark-read"
                        data-admin-task-mark
                        data-task-key="<?php echo e($task['key']); ?>"
                        data-task-count="<?php echo (int) $task['count']; ?>"
                    >Đã xem</button>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
</aside>
