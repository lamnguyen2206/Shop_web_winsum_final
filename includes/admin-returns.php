<?php

require_once __DIR__ . '/return-repository.php';

require_once __DIR__ . '/order-repository.php';



$adminMessage = trim((string) ($_GET['msg'] ?? ''));

$adminMessageOk = isset($_GET['msg_ok']) ? $_GET['msg_ok'] === '1' : null;

$statusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : 'pending';

if ($statusFilter === 'all') {

    $statusFilter = '';

}



$requests = returnAdminGetAll($conn, $statusFilter !== '' ? $statusFilter : null);

?>



<section class="container admin-page">

    <p class="breadcrumb"><a href="<?php echo e(app_url('home')); ?>">Trang chủ</a> / <span>Hoàn hàng / Khiếu nại</span></p>



    <div class="admin-page-head">

        <h1>Quản lý khiếu nại / Hoàn trả</h1>

        <p class="admin-muted">Quy trình 5 bước: Duyệt yêu cầu → Chờ khách trả hàng → Nhận hàng &amp; cộng kho → Hoàn tiền → Khách xác nhận đã nhận tiền.</p>

    </div>



    <?php include __DIR__ . '/admin-nav.php'; ?>



    <?php if ($adminMessage !== ''): ?>

        <p class="admin-notice<?php echo $adminMessageOk === true ? ' admin-notice--ok' : ($adminMessageOk === false ? ' admin-notice--err' : ''); ?>">

            <?php echo htmlspecialchars($adminMessage); ?>

        </p>

    <?php endif; ?>



    <div class="admin-filter-tabs">

        <a class="<?php echo $statusFilter === 'pending' ? 'active' : ''; ?>" href="index.php?view=admin-returns&amp;status=pending">Chờ duyệt</a>

        <a class="<?php echo $statusFilter === 'accepted' ? 'active' : ''; ?>" href="index.php?view=admin-returns&amp;status=accepted">Chờ khách trả hàng</a>

        <a class="<?php echo $statusFilter === 'goods_received' ? 'active' : ''; ?>" href="index.php?view=admin-returns&amp;status=goods_received">Chờ hoàn tiền</a>

        <a class="<?php echo $statusFilter === 'completed' ? 'active' : ''; ?>" href="index.php?view=admin-returns&amp;status=completed">Hoàn tất</a>

        <a class="<?php echo $statusFilter === 'rejected' ? 'active' : ''; ?>" href="index.php?view=admin-returns&amp;status=rejected">Từ chối</a>

        <a class="<?php echo $statusFilter === '' ? 'active' : ''; ?>" href="index.php?view=admin-returns&amp;status=all">Tất cả</a>

    </div>



    <div class="admin-panel admin-panel-wide">

        <?php if (empty($requests)): ?>

            <p class="empty-state">Không có yêu cầu hoàn hàng trong mục này.</p>

        <?php else: ?>

            <div class="review-admin-list">

                <?php foreach ($requests as $request): ?>

                    <?php $reqStatus = returnNormalizeStatus((string) ($request['status'] ?? '')); ?>

                    <article class="review-admin-card">

                        <div class="review-admin-meta">

                            <strong>Đơn #<?php echo htmlspecialchars((string) $request['order_code']); ?></strong>

                            <span class="badge-status badge-<?php echo htmlspecialchars($reqStatus); ?>">

                                <?php echo htmlspecialchars(returnStatusLabel($reqStatus)); ?>

                            </span>

                        </div>

                        <p class="review-product">

                            Khách: <strong><?php echo htmlspecialchars((string) $request['customer_name']); ?></strong>

                            · <?php echo number_format((float) $request['grand_total'], 0, ',', '.'); ?>đ

                            · Đơn: <?php echo htmlspecialchars(orderStatusLabel((string) ($request['order_status'] ?? ''))); ?>

                        </p>

                        <p><strong>Lý do:</strong> <?php echo htmlspecialchars(returnReasonLabel((string) $request['reason'])); ?></p>

                        <p><?php echo nl2br(htmlspecialchars((string) $request['description'])); ?></p>

                        <?php if (!empty($request['evidence_url'])): ?>

                            <p><a href="<?php echo htmlspecialchars((string) $request['evidence_url']); ?>" target="_blank" rel="noopener">Xem ảnh bằng chứng</a></p>

                        <?php endif; ?>

                        <p><strong>TK nhận hoàn:</strong>

                            <?php echo htmlspecialchars((string) ($request['bank_account_name'] ?? '')); ?>

                            — <?php echo htmlspecialchars((string) ($request['bank_name'] ?? '')); ?>

                            — <?php echo htmlspecialchars((string) ($request['bank_account_number'] ?? '')); ?>

                        </p>

                        <?php if (!empty($request['admin_note'])): ?>

                            <p class="admin-muted"><strong>Ghi chú:</strong> <?php echo nl2br(htmlspecialchars((string) $request['admin_note'])); ?></p>

                        <?php endif; ?>

                        <?php if (!empty($request['return_shipping_cost'])): ?>

                            <p class="admin-muted"><strong>Phí ship hoàn:</strong> <?php echo number_format((float) $request['return_shipping_cost'], 0, ',', '.'); ?>đ</p>

                        <?php endif; ?>

                        <?php if ($reqStatus === 'completed'): ?>

                            <?php if (!empty($request['customer_refund_confirmed_at'])): ?>

                                <p class="admin-muted"><strong>Khách xác nhận nhận tiền:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $request['customer_refund_confirmed_at']))); ?></p>

                            <?php elseif (!empty($request['refunded_at'])): ?>

                                <p class="admin-muted"><strong>Chờ khách xác nhận</strong> (đã hoàn tiền lúc <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string) $request['refunded_at']))); ?>)</p>

                            <?php endif; ?>

                        <?php endif; ?>

                        <small>Gửi lúc: <?php echo htmlspecialchars((string) $request['created_at']); ?></small>

                        <div class="admin-actions-cell">

                            <a class="btn-secondary" href="<?php echo e(app_url('admin-order-detail', ['code' => (string) $request['order_code']])); ?>">Xem đơn</a>



                            <?php if ($reqStatus === 'pending'): ?>

                                <form method="post" class="admin-inline-form admin-return-form">

                                    <?php echo csrfField(); ?>

                                    <input type="hidden" name="action" value="return_accept">

                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">

                                    <input type="text" name="admin_note" placeholder="Ghi chú cho khách (tùy chọn)" aria-label="Ghi chú chấp nhận">

                                    <button type="submit">Chấp nhận yêu cầu</button>

                                </form>

                                <form method="post" class="admin-inline-form admin-return-form">

                                    <?php echo csrfField(); ?>

                                    <input type="hidden" name="action" value="return_reject">

                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">

                                    <input type="text" name="admin_note" placeholder="Lý do từ chối (bắt buộc)" required aria-label="Lý do từ chối">

                                    <button type="submit" class="btn-secondary">Từ chối</button>

                                </form>

                            <?php elseif ($reqStatus === 'accepted'): ?>

                                <form method="post" class="admin-inline-form admin-return-form">

                                    <?php echo csrfField(); ?>

                                    <input type="hidden" name="action" value="return_confirm_goods">

                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">

                                    <input type="number" name="return_shipping_cost" min="0" step="1000" placeholder="Phí ship hoàn (đ)" aria-label="Phí vận chuyển hoàn">

                                    <button type="submit">Xác nhận đã nhận hàng hoàn</button>

                                </form>

                            <?php elseif ($reqStatus === 'goods_received'): ?>

                                <form method="post" class="admin-inline-form admin-return-form" onsubmit="return confirm('Bạn đã chuyển khoản hoàn tiền cho khách? Đơn sẽ chuyển sang trạng thái hoàn hàng và chờ khách xác nhận đã nhận tiền.');">

                                    <?php echo csrfField(); ?>

                                    <input type="hidden" name="action" value="return_complete_refund">

                                    <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">

                                    <input type="text" name="admin_note" placeholder="Mã GD chuyển khoản (tùy chọn)" aria-label="Ghi chú hoàn tiền">

                                    <button type="submit">Xác nhận đã hoàn tiền</button>

                                </form>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>

</section>

