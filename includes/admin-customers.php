<?php
require_once __DIR__ . '/customer-admin-repository.php';
require_once __DIR__ . '/order-repository.php';

$adminMessage = '';
$filters = customerAdminParseFilters($_GET);
$perPage = 20;
$total = customerAdminCount($conn, $filters);
$totalPages = max(1, (int) ceil($total / $perPage));
$page = min($filters['page'], $totalPages);
$offset = ($page - 1) * $perPage;
$customers = customerAdminList($conn, $filters, $perPage, $offset);

$detailId = (int) ($_GET['id'] ?? 0);
$detail = $detailId > 0 ? customerAdminGetById($conn, $detailId) : null;
$detailOrders = $detail ? customerAdminGetRecentOrders($conn, $detailId) : [];

$actingId = adminManagementActingCustomerId();

if (isset($_GET['msg'])) {
    $adminMessage = (string) $_GET['msg'];
}
$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;

$detailUrlBase = customerAdminBuildListUrl($filters, $page);
$listUrlForJs = customerAdminBuildListUrl($filters, $page);
$statusOptions = ['active', 'inactive', 'blocked'];
$rowIndexStart = $offset;
?>

<section class="container admin-page admin-customers-page">
    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Quản lý khách hàng</span></p>

    <div class="admin-page-head">
        <h1>Quản lý khách hàng</h1>
    </div>

    <?php include __DIR__ . '/admin-nav.php'; ?>

    <?php if ($adminMessage !== ''): ?>
        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">
            <?php echo htmlspecialchars($adminMessage); ?>
        </p>
    <?php endif; ?>

    <div class="admin-customers-toolbar">
        <form method="get" action="index.php" class="admin-customers-search">
            <input type="hidden" name="view" value="admin-customers">
            <?php if ($filters['status'] !== ''): ?>
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filters['status']); ?>">
            <?php endif; ?>
            <?php if ($filters['role'] !== 'customer'): ?>
                <input type="hidden" name="role" value="<?php echo htmlspecialchars($filters['role']); ?>">
            <?php endif; ?>
            <?php if ($detailId > 0): ?>
                <input type="hidden" name="id" value="<?php echo $detailId; ?>">
            <?php endif; ?>
            <input type="search" name="q" value="<?php echo htmlspecialchars($filters['q']); ?>" placeholder="Tìm tên, SĐT, email, mã KH...">
            <button type="submit">Tìm</button>
            <?php if ($filters['q'] !== ''): ?>
                <a class="btn-secondary" href="<?php echo htmlspecialchars(customerAdminBuildListUrl(['q' => '', 'status' => $filters['status'], 'role' => $filters['role'], 'page' => 1], 1) . ($detailId ? '&id=' . $detailId : '')); ?>">Xóa</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="admin-filter-tabs">
        <a class="<?php echo $filters['role'] === 'customer' ? 'active' : ''; ?>" href="index.php?view=admin-customers&amp;role=customer<?php echo $filters['status'] !== '' ? '&amp;status=' . urlencode($filters['status']) : ''; ?><?php echo $filters['q'] !== '' ? '&amp;q=' . urlencode($filters['q']) : ''; ?>">Khách hàng</a>
        <a class="<?php echo $filters['role'] === 'admin' ? 'active' : ''; ?>" href="index.php?view=admin-customers&amp;role=admin<?php echo $filters['q'] !== '' ? '&amp;q=' . urlencode($filters['q']) : ''; ?>">Quản trị</a>
        <a class="<?php echo $filters['role'] === 'all' ? 'active' : ''; ?>" href="index.php?view=admin-customers&amp;role=all<?php echo $filters['status'] !== '' ? '&amp;status=' . urlencode($filters['status']) : ''; ?><?php echo $filters['q'] !== '' ? '&amp;q=' . urlencode($filters['q']) : ''; ?>">Tất cả</a>
        <span class="admin-filter-tabs-sep" aria-hidden="true"></span>
        <a class="<?php echo $filters['status'] === '' ? 'active' : ''; ?>" href="index.php?view=admin-customers&amp;role=<?php echo urlencode($filters['role']); ?><?php echo $filters['q'] !== '' ? '&amp;q=' . urlencode($filters['q']) : ''; ?>">Mọi trạng thái</a>
        <a class="<?php echo $filters['status'] === 'active' ? 'active' : ''; ?>" href="index.php?view=admin-customers&amp;role=<?php echo urlencode($filters['role']); ?>&amp;status=active<?php echo $filters['q'] !== '' ? '&amp;q=' . urlencode($filters['q']) : ''; ?>">Hoạt động</a>
        <a class="<?php echo $filters['status'] === 'blocked' ? 'active' : ''; ?>" href="index.php?view=admin-customers&amp;role=<?php echo urlencode($filters['role']); ?>&amp;status=blocked<?php echo $filters['q'] !== '' ? '&amp;q=' . urlencode($filters['q']) : ''; ?>">Đã khóa</a>
    </div>

    <div class="admin-customers-layout">
        <div class="admin-panel admin-panel-wide admin-customers-list">
            <p class="admin-hint"><?php echo (int) $total; ?> tài khoản · Trang <?php echo $page; ?>/<?php echo $totalPages; ?></p>

            <?php if ($customers === []): ?>
                <p class="empty-state">Không có khách hàng phù hợp bộ lọc.</p>
            <?php else: ?>
                <div class="admin-table-wrap admin-customers-table-wrap">
                    <table class="admin-table admin-customers-table">
                        <thead>
                            <tr>
                                <th class="col-stt">STT</th>
                                <th>Mã KH</th>
                                <th>Họ tên</th>
                                <th>Số điện thoại</th>
                                <th>Email</th>
                                <th>Vai trò</th>
                                <th>Trạng thái</th>
                                <th class="col-actions">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customers as $i => $row): ?>
                                <?php
                                $rowId = (int) $row['id'];
                                $canManage = customerAdminCanManageRow($row, $actingId);
                                $isBlocked = ($row['status'] ?? '') === 'blocked';
                                $stt = $rowIndexStart + $i + 1;
                                $statusClass = match ($row['status'] ?? '') {
                                    'active' => 'badge-status--active',
                                    'blocked' => 'badge-status--blocked',
                                    default => 'badge-status--inactive',
                                };
                                ?>
                                <tr class="<?php echo $detailId === $rowId ? 'is-selected' : ''; ?>" data-customer-id="<?php echo $rowId; ?>">
                                    <td class="col-stt"><?php echo $stt; ?></td>
                                    <td><code><?php echo htmlspecialchars($row['customer_code']); ?></code></td>
                                    <td><span data-customer-name><?php echo htmlspecialchars($row['full_name']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['phone']); ?></td>
                                    <td class="col-email"><?php echo !empty($row['email']) ? htmlspecialchars($row['email']) : '—'; ?></td>
                                    <td>
                                        <span class="badge-role badge-role-<?php echo htmlspecialchars($row['role']); ?>">
                                            <?php echo htmlspecialchars(customerAdminRoleLabel((string) $row['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-status <?php echo $statusClass; ?>">
                                            <?php echo htmlspecialchars(customerAdminStatusLabel((string) $row['status'])); ?>
                                        </span>
                                    </td>
                                    <td class="col-actions">
                                        <div class="admin-row-actions" role="group" aria-label="Hành động">
                                            <button type="button" class="btn-action btn-action--view" title="Xem chi tiết" aria-label="Xem chi tiết" onclick="AdminCustomers.onView(<?php echo $rowId; ?>)">
                                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 5c-5 0-9 4.5-9 7s4 7 9 7 9-4.5 9-7-4-7-9-7zm0 11a4 4 0 1 1 0-8 4 4 0 0 1 0 8z"/></svg>
                                            </button>
                                            <button type="button" class="btn-action btn-action--edit" title="Chỉnh sửa" aria-label="Chỉnh sửa" onclick="AdminCustomers.onEdit(<?php echo $rowId; ?>)">
                                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.83H5v-.92l9.06-9.06.92.92L5.92 20.08zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                            </button>
                                            <?php if ($canManage): ?>
                                            <button type="button" class="btn-action btn-action--lock" title="<?php echo $isBlocked ? 'Mở khóa' : 'Khóa tài khoản'; ?>" aria-label="<?php echo $isBlocked ? 'Mở khóa' : 'Khóa tài khoản'; ?>" onclick="AdminCustomers.onToggleBlock(<?php echo $rowId; ?>)">
                                                <?php if ($isBlocked): ?>
                                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 17a2 2 0 0 0 2-2v-2h-2v2a2 2 0 0 1-4 0V9a2 2 0 0 1 4 0h2V7a4 4 0 0 0-8 0v6a2 2 0 0 0 2 2zm6-7h-2v2h2V10zm0-4H6v2h12V6z"/></svg>
                                                <?php else: ?>
                                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M18 8h-1V6a5 5 0 0 0-10 0v2H6a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V10a2 2 0 0 0-2-2zm-7 0V6a3 3 0 0 1 6 0v2h-6z"/></svg>
                                                <?php endif; ?>
                                            </button>
                                            <?php else: ?>
                                            <span class="admin-muted admin-row-actions-muted">—</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="admin-pagination" aria-label="Phân trang khách hàng">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo htmlspecialchars(customerAdminBuildListUrl($filters, $page - 1) . ($detailId ? '&id=' . $detailId : '')); ?>">← Trước</a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <?php if ($p === $page || $p === 1 || $p === $totalPages || abs($p - $page) <= 1): ?>
                                <a class="<?php echo $p === $page ? 'active' : ''; ?>" href="<?php echo htmlspecialchars(customerAdminBuildListUrl($filters, $p) . ($detailId ? '&id=' . $detailId : '')); ?>"><?php echo $p; ?></a>
                            <?php elseif ($p === 2 || $p === $totalPages - 1): ?>
                                <span class="admin-pagination-ellipsis">…</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo htmlspecialchars(customerAdminBuildListUrl($filters, $page + 1) . ($detailId ? '&id=' . $detailId : '')); ?>">Sau →</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($detail): ?>
            <section class="admin-panel admin-customer-detail" aria-labelledby="admin-customer-detail-title">
                <div class="admin-customer-detail-head">
                    <h2 id="admin-customer-detail-title">Chi tiết khách hàng</h2>
                    <a class="btn-secondary" href="<?php echo htmlspecialchars(customerAdminBuildListUrl($filters, $page)); ?>">Đóng chi tiết</a>
                </div>

                <div class="admin-customer-detail-stack">
                    <div class="admin-customer-info">
                        <h3 class="admin-section-title">Thông tin tài khoản</h3>
                        <dl class="admin-detail-list admin-detail-list--wide">
                            <dt>Mã</dt><dd><code><?php echo htmlspecialchars($detail['customer_code']); ?></code></dd>
                            <dt>Họ tên</dt><dd data-customer-detail-name><?php echo htmlspecialchars($detail['full_name']); ?></dd>
                            <dt>SĐT</dt><dd><?php echo htmlspecialchars($detail['phone']); ?></dd>
                            <dt>Email</dt><dd><?php echo $detail['email'] !== '' && $detail['email'] !== null ? htmlspecialchars($detail['email']) : '—'; ?></dd>
                            <dt>Vai trò</dt><dd><?php echo htmlspecialchars(customerAdminRoleLabel((string) $detail['role'])); ?></dd>
                            <dt>Trạng thái</dt><dd>
                                <?php
                                $detailStatusClass = match ($detail['status'] ?? '') {
                                    'active' => 'badge-status--active',
                                    'blocked' => 'badge-status--blocked',
                                    default => 'badge-status--inactive',
                                };
                                ?>
                                <span class="badge-status <?php echo $detailStatusClass; ?>">
                                    <?php echo htmlspecialchars(customerAdminStatusLabel((string) $detail['status'])); ?>
                                </span>
                            </dd>
                            <dt>Đơn hàng</dt><dd><?php echo (int) $detail['order_count']; ?> đơn</dd>
                            <dt>Tổng chi tiêu</dt><dd><?php echo number_format((float) $detail['total_spent'], 0, ',', '.'); ?>đ</dd>
                            <dt>Đăng ký</dt><dd><?php echo htmlspecialchars((string) $detail['created_at']); ?></dd>
                        </dl>

                        <h3 class="admin-section-title">Đơn hàng gần đây</h3>
                        <?php if ($detailOrders === []): ?>
                            <p class="admin-muted">Chưa có đơn hàng liên kết tài khoản.</p>
                        <?php else: ?>
                            <ul class="admin-customer-orders">
                                <?php foreach ($detailOrders as $order): ?>
                                    <li>
                                        <strong>#<?php echo htmlspecialchars($order['order_code']); ?></strong>
                                        · <?php echo number_format((float) $order['grand_total'], 0, ',', '.'); ?>đ
                                        · <?php echo htmlspecialchars(orderStatusLabel((string) $order['status'])); ?>
                                        <br><small><?php echo htmlspecialchars((string) $order['ordered_at']); ?></small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <p><a href="<?php echo e(app_url('admin-orders', ['customer_id' => (int) $detail['id']])); ?>">Xem tất cả đơn hàng của khách →</a></p>
                        <?php endif; ?>
                    </div>

                    <?php if (($detail['role'] ?? '') !== 'admin'): ?>
                    <div class="admin-customer-crud" id="customer-edit">
                        <h3 class="admin-section-title">Quản lý tài khoản</h3>
                        <p class="admin-hint">Cập nhật trạng thái khách hàng (hoạt động / ngưng / khóa).</p>
                        <form method="post" action="<?php echo e(app_url('admin-customers')); ?>" class="admin-form admin-customer-status-form">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="view" value="admin-customers">
                            <input type="hidden" name="action" value="update_customer_status">
                            <input type="hidden" name="customer_id" value="<?php echo (int) $detail['id']; ?>">
                            <label for="customer-status">Trạng thái
                                <select id="customer-status" name="status">
                                    <?php foreach ($statusOptions as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $detail['status'] === $opt ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(customerAdminStatusLabel($opt)); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <button type="submit">Lưu thay đổi</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div class="admin-customer-crud admin-customer-crud--muted">
                        <h3 class="admin-section-title">Quản lý tài khoản</h3>
                        <p class="admin-muted">Tài khoản quản trị không thể đổi trạng thái tại đây.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <form id="admin-customer-action-form" method="post" action="<?php echo e(app_url('admin-customers')); ?>" class="visually-hidden">
        <input type="hidden" name="view" value="admin-customers">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="">
        <input type="hidden" name="customer_id" value="">
    </form>

    <script>
        window.adminCustomersConfig = { listUrl: <?php echo json_encode($listUrlForJs, JSON_UNESCAPED_UNICODE); ?> };
    </script>
</section>
