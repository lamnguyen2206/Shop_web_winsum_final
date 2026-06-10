<?php

require_once __DIR__ . '/../config/database.php';



function returnEnsureSchema(mysqli $conn): void

{

    static $done = false;

    if ($done) {

        return;

    }

    $done = true;



    $conn->query(

        'CREATE TABLE IF NOT EXISTS order_return_requests (

            id BIGINT AUTO_INCREMENT PRIMARY KEY,

            order_id BIGINT NOT NULL,

            customer_id BIGINT NOT NULL,

            reason VARCHAR(80) NOT NULL,

            description TEXT NOT NULL,

            evidence_url VARCHAR(500) DEFAULT NULL,

            bank_account_number VARCHAR(40) DEFAULT NULL,

            bank_account_name VARCHAR(120) DEFAULT NULL,

            bank_name VARCHAR(120) DEFAULT NULL,

            return_shipping_cost DECIMAL(12,2) DEFAULT NULL,

            status ENUM(\'pending\',\'accepted\',\'goods_received\',\'completed\',\'rejected\') NOT NULL DEFAULT \'pending\',

            admin_note TEXT DEFAULT NULL,

            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            reviewed_at TIMESTAMP NULL DEFAULT NULL,

            reviewed_by VARCHAR(50) DEFAULT NULL,

            goods_received_at TIMESTAMP NULL DEFAULT NULL,

            refunded_at TIMESTAMP NULL DEFAULT NULL,

            INDEX idx_return_order (order_id),

            INDEX idx_return_status (status, created_at),

            CONSTRAINT fk_return_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE

        )'

    );



    $columns = [

        'bank_account_number' => 'VARCHAR(40) DEFAULT NULL',

        'bank_account_name' => 'VARCHAR(120) DEFAULT NULL',

        'bank_name' => 'VARCHAR(120) DEFAULT NULL',

        'return_shipping_cost' => 'DECIMAL(12,2) DEFAULT NULL',

        'goods_received_at' => 'TIMESTAMP NULL DEFAULT NULL',

        'refunded_at' => 'TIMESTAMP NULL DEFAULT NULL',

    ];

    foreach ($columns as $col => $def) {

        $check = $conn->query("SHOW COLUMNS FROM order_return_requests LIKE '" . $conn->real_escape_string($col) . "'");

        if ($check && $check->num_rows === 0) {

            $conn->query("ALTER TABLE order_return_requests ADD COLUMN {$col} {$def}");

        }

    }



    $conn->query("ALTER TABLE order_return_requests

                  MODIFY status ENUM('pending','accepted','goods_received','completed','rejected') NOT NULL DEFAULT 'pending'");

    $conn->query("UPDATE order_return_requests SET status = 'completed' WHERE status = 'approved'");

}



function returnGetWindowDays(): int

{

    return 7;

}



function returnGetWarehouseAddress(): string

{

    return 'Kho Winsum Home — 88 Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh. Hotline: 1900 6868 (8h–20h).';

}



function returnReasonOptions(): array

{

    return [

        'wrong_item' => 'Sai mẫu / sai sản phẩm',

        'damaged' => 'Hỏng / vỡ khi nhận hàng',

        'not_as_described' => 'Không đúng mô tả',

        'other' => 'Lý do khác',

    ];

}



function returnReasonLabel(string $reason): string

{

    return returnReasonOptions()[$reason] ?? $reason;

}



function returnNormalizeStatus(string $status): string

{

    return $status === 'approved' ? 'completed' : $status;

}



function returnStatusLabel(string $status): string

{

    return match (returnNormalizeStatus($status)) {

        'pending' => 'Chờ Admin duyệt',

        'accepted' => 'Chờ khách trả hàng',

        'goods_received' => 'Đã nhận hàng hoàn — chờ hoàn tiền',

        'completed' => 'Hoàn hàng thành công',

        'rejected' => 'Đã từ chối',

        default => $status,

    };

}



function returnActiveStatuses(): array

{

    return ['pending', 'accepted', 'goods_received'];

}



function orderGetDeliveredAt(mysqli $conn, int $orderId): ?string

{

    if ($orderId <= 0) {

        return null;

    }



    $stmt = $conn->prepare('SELECT os.delivered_at, o.status, o.updated_at

                            FROM orders o

                            LEFT JOIN order_shipments os ON os.order_id = o.id

                            WHERE o.id = ?

                            ORDER BY os.id DESC

                            LIMIT 1');

    if (!$stmt) {

        return null;

    }

    $stmt->bind_param('i', $orderId);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$row) {

        return null;

    }



    if (!empty($row['delivered_at'])) {

        return (string) $row['delivered_at'];

    }



    if (($row['status'] ?? '') === 'delivered' && !empty($row['updated_at'])) {

        return (string) $row['updated_at'];

    }



    return null;

}



function returnGetDeadlineForOrder(mysqli $conn, int $orderId): ?string

{

    $deliveredAt = orderGetDeliveredAt($conn, $orderId);

    if ($deliveredAt === null) {

        return null;

    }

    $deadline = strtotime($deliveredAt . ' +' . returnGetWindowDays() . ' days');

    if ($deadline === false) {

        return null;

    }



    return date('Y-m-d H:i:s', $deadline);

}



function returnGetByOrderId(mysqli $conn, int $orderId): ?array

{

    if ($orderId <= 0) {

        return null;

    }



    $stmt = $conn->prepare('SELECT id, order_id, customer_id, reason, description, evidence_url,

                                   bank_account_number, bank_account_name, bank_name, return_shipping_cost,

                                   status, admin_note, created_at, reviewed_at, reviewed_by,

                                   goods_received_at, refunded_at

                            FROM order_return_requests

                            WHERE order_id = ?

                            ORDER BY id DESC

                            LIMIT 1');

    if (!$stmt) {

        return null;

    }

    $stmt->bind_param('i', $orderId);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();



    return $row ?: null;

}



function returnOrderIsDelivered(array $order): bool

{

    $status = (string) ($order['status'] ?? '');

    $fulfillment = (string) ($order['fulfillment_status'] ?? '');



    return $status === 'delivered' || $fulfillment === 'delivered';

}



function returnOrderInReturnFlow(array $order): bool

{

    return in_array((string) ($order['status'] ?? ''), ['return_pending', 'return_accepted', 'return_received'], true);

}



/**

 * @return array{ok:bool,message:string,deadline?:string}

 */

function returnCanCustomerRequest(mysqli $conn, array $order, int $customerId): array

{

    if ($customerId <= 0) {

        return ['ok' => false, 'message' => 'Bạn cần đăng nhập để yêu cầu hoàn hàng.'];

    }



    $orderCustomerId = (int) ($order['customer_id'] ?? 0);

    if ($orderCustomerId !== $customerId) {

        return ['ok' => false, 'message' => 'Bạn không có quyền yêu cầu hoàn đơn này.'];

    }



    if (in_array((string) ($order['status'] ?? ''), ['returned', 'cancelled'], true)) {

        return ['ok' => false, 'message' => 'Đơn hàng không còn đủ điều kiện hoàn hàng.'];

    }



    if (returnOrderInReturnFlow($order)) {

        return ['ok' => false, 'message' => 'Đơn hàng đang trong quy trình hoàn hàng.'];

    }



    if (!returnOrderIsDelivered($order) && (string) ($order['status'] ?? '') !== 'delivered') {

        return ['ok' => false, 'message' => 'Chỉ đơn đã giao thành công mới được yêu cầu hoàn hàng.'];

    }



    $existing = returnGetByOrderId($conn, (int) $order['id']);

    if ($existing) {

        $existingStatus = returnNormalizeStatus((string) $existing['status']);

        if (in_array($existingStatus, ['pending', 'accepted', 'goods_received', 'completed'], true)) {

            return ['ok' => false, 'message' => 'Đơn hàng đã có yêu cầu hoàn hàng.'];

        }

    }



    $deadline = returnGetDeadlineForOrder($conn, (int) $order['id']);

    if ($deadline === null) {

        return ['ok' => false, 'message' => 'Không xác định được thời điểm giao hàng.'];

    }



    if (time() > strtotime($deadline)) {

        return ['ok' => false, 'message' => 'Đã hết hạn yêu cầu hoàn hàng (' . returnGetWindowDays() . ' ngày sau khi giao).'];

    }



    return ['ok' => true, 'message' => '', 'deadline' => $deadline];

}



function returnSaveEvidenceUpload(): ?string

{

    if (empty($_FILES['evidence']['tmp_name']) || !is_uploaded_file($_FILES['evidence']['tmp_name'])) {

        return null;

    }



    $file = $_FILES['evidence'];

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

        return null;

    }



    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {

        return null;

    }



    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';

    if ($finfo) {

        finfo_close($finfo);

    }



    if (!isset($allowed[$mime])) {

        return null;

    }



    $uploadDir = dirname(__DIR__) . '/assets/images/return-uploads';

    if (!is_dir($uploadDir)) {

        mkdir($uploadDir, 0755, true);

    }



    $filename = 'return_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];

    $dest = $uploadDir . '/' . $filename;



    if (!move_uploaded_file($file['tmp_name'], $dest)) {

        return null;

    }



    return 'assets/images/return-uploads/' . $filename;

}



/**

 * @param array{bank_account_number?:string,bank_account_name?:string,bank_name?:string} $bankInfo

 * @return array{ok:bool,message:string}

 */

function returnCreateRequest(

    mysqli $conn,

    int $orderId,

    int $customerId,

    string $reason,

    string $description,

    array $bankInfo = []

): array {

    returnEnsureSchema($conn);

    require_once __DIR__ . '/order-repository.php';



    $stmt = $conn->prepare('SELECT id, order_code, customer_id, status, fulfillment_status

                            FROM orders WHERE id = ? AND customer_id = ? LIMIT 1');

    if (!$stmt) {

        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];

    }

    $stmt->bind_param('ii', $orderId, $customerId);

    $stmt->execute();

    $orderRow = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$orderRow) {

        return ['ok' => false, 'message' => 'Không tìm thấy đơn hàng.'];

    }



    $check = returnCanCustomerRequest($conn, $orderRow, $customerId);

    if (!$check['ok']) {

        return ['ok' => false, 'message' => $check['message']];

    }



    $reason = trim($reason);

    $description = trim($description);

    $bankNumber = trim((string) ($bankInfo['bank_account_number'] ?? ''));

    $bankName = trim((string) ($bankInfo['bank_account_name'] ?? ''));

    $bankInstitution = trim((string) ($bankInfo['bank_name'] ?? ''));



    if ($reason === '' || !isset(returnReasonOptions()[$reason])) {

        return ['ok' => false, 'message' => 'Vui lòng chọn lý do hoàn hàng.'];

    }

    if ($description === '') {

        return ['ok' => false, 'message' => 'Vui lòng mô tả chi tiết lý do hoàn hàng.'];

    }

    if ($bankNumber === '' || $bankName === '' || $bankInstitution === '') {

        return ['ok' => false, 'message' => 'Vui lòng nhập đầy đủ thông tin tài khoản nhận hoàn tiền.'];

    }



    $evidenceUrl = returnSaveEvidenceUpload();

    if ($evidenceUrl === null) {

        return ['ok' => false, 'message' => 'Vui lòng tải lên ảnh bằng chứng (JPEG/PNG/WebP, tối đa 2MB).'];

    }



    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare('INSERT INTO order_return_requests

            (order_id, customer_id, reason, description, evidence_url,

             bank_account_number, bank_account_name, bank_name, status)

            VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'pending\')');

        if (!$stmt) {

            throw new RuntimeException('Không thể gửi yêu cầu hoàn hàng.');

        }

        $stmt->bind_param(

            'iissssss',

            $orderId,

            $customerId,

            $reason,

            $description,

            $evidenceUrl,

            $bankNumber,

            $bankName,

            $bankInstitution

        );

        if (!$stmt->execute()) {

            $stmt->close();

            throw new RuntimeException('Không thể lưu yêu cầu hoàn hàng.');

        }

        $stmt->close();



        if (!orderApplyStatusUpdate($conn, $orderId, 'return_pending', 'customer_return', $orderRow)) {

            throw new RuntimeException('Không cập nhật được trạng thái đơn hàng.');

        }



        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        return ['ok' => false, 'message' => 'Không thể gửi yêu cầu hoàn hàng. Vui lòng thử lại.'];

    }



    return ['ok' => true, 'message' => 'Đã gửi yêu cầu hoàn hàng. Đơn đang chờ Admin duyệt — chưa trừ doanh thu hay hoàn tiền.'];

}



function returnAdminGetAll(mysqli $conn, ?string $statusFilter = null, int $limit = 100): array

{

    returnEnsureSchema($conn);



    $sql = 'SELECT r.id, r.order_id, r.customer_id, r.reason, r.description, r.evidence_url,

                   r.bank_account_number, r.bank_account_name, r.bank_name, r.return_shipping_cost,

                   r.status, r.admin_note, r.created_at, r.reviewed_at, r.reviewed_by,

                   r.goods_received_at, r.refunded_at,

                   o.order_code, o.customer_name, o.grand_total, o.status AS order_status, o.payment_status

            FROM order_return_requests r

            INNER JOIN orders o ON o.id = r.order_id';

    if ($statusFilter !== null && $statusFilter !== '') {

        $sql .= ' WHERE r.status = ?';

    }

    $sql .= ' ORDER BY r.id DESC LIMIT ?';



    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        return [];

    }

    if ($statusFilter !== null && $statusFilter !== '') {

        $stmt->bind_param('si', $statusFilter, $limit);

    } else {

        $stmt->bind_param('i', $limit);

    }

    $stmt->execute();

    $result = $stmt->get_result();

    $rows = [];

    while ($row = $result->fetch_assoc()) {

        $rows[] = $row;

    }

    $stmt->close();



    return $rows;

}



function returnCountPending(mysqli $conn): int

{

    returnEnsureSchema($conn);

    $result = $conn->query("SELECT COUNT(*) AS c FROM order_return_requests WHERE status = 'pending'");

    if (!$result) {

        return 0;

    }

    $row = $result->fetch_assoc();

    return (int) ($row['c'] ?? 0);

}



function returnGetRequestById(mysqli $conn, int $requestId): ?array

{

    $stmt = $conn->prepare('SELECT r.*, o.order_code, o.payment_status

                            FROM order_return_requests r

                            INNER JOIN orders o ON o.id = r.order_id

                            WHERE r.id = ?

                            LIMIT 1');

    if (!$stmt) {

        return null;

    }

    $stmt->bind_param('i', $requestId);

    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    $stmt->close();



    return $row ?: null;

}



/**

 * Giai đoạn 2 — Admin chấp nhận yêu cầu, hướng dẫn khách trả hàng.

 *

 * @return array{ok:bool,message:string}

 */

function returnAdminAccept(mysqli $conn, int $requestId, string $adminNote = ''): array

{

    returnEnsureSchema($conn);

    require_once __DIR__ . '/order-repository.php';



    $request = returnGetRequestById($conn, $requestId);

    if (!$request || ($request['status'] ?? '') !== 'pending') {

        return ['ok' => false, 'message' => 'Yêu cầu hoàn hàng không hợp lệ hoặc đã được xử lý.'];

    }



    $orderId = (int) $request['order_id'];

    $warehouseNote = returnGetWarehouseAddress();

    $adminNote = trim($adminNote);

    $noteToStore = $adminNote !== ''

        ? $adminNote . "\n\nĐịa chỉ trả hàng: " . $warehouseNote

        : 'Địa chỉ trả hàng: ' . $warehouseNote;



    $conn->begin_transaction();

    try {

        $stmtReq = $conn->prepare("UPDATE order_return_requests

                                   SET status = 'accepted', admin_note = ?, reviewed_at = NOW(), reviewed_by = 'admin'

                                   WHERE id = ? AND status = 'pending'");

        if (!$stmtReq) {

            throw new RuntimeException('Không cập nhật được yêu cầu.');

        }

        $stmtReq->bind_param('si', $noteToStore, $requestId);

        $stmtReq->execute();

        if ($stmtReq->affected_rows === 0) {

            $stmtReq->close();

            throw new RuntimeException('Yêu cầu đã được xử lý.');

        }

        $stmtReq->close();



        $stmtCurrent = $conn->prepare('SELECT status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');

        if (!$stmtCurrent) {

            throw new RuntimeException('Không đọc được đơn hàng.');

        }

        $stmtCurrent->bind_param('i', $orderId);

        $stmtCurrent->execute();

        $currentOrder = $stmtCurrent->get_result()->fetch_assoc();

        $stmtCurrent->close();



        if (!$currentOrder || !orderApplyStatusUpdate($conn, $orderId, 'return_accepted', 'return_accept', $currentOrder)) {

            throw new RuntimeException('Không cập nhật được trạng thái đơn.');

        }



        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        return ['ok' => false, 'message' => 'Không thể chấp nhận yêu cầu hoàn hàng.'];

    }



    return ['ok' => true, 'message' => 'Đã chấp nhận yêu cầu. Khách được hướng dẫn gửi hàng về kho — chưa hoàn tiền.'];

}



/**

 * Giai đoạn 2 — Admin từ chối, đơn quay về Đã giao hàng.

 *

 * @return array{ok:bool,message:string}

 */

function returnAdminReject(mysqli $conn, int $requestId, string $adminNote = ''): array

{

    returnEnsureSchema($conn);

    require_once __DIR__ . '/order-repository.php';



    $adminNote = trim($adminNote);

    if ($adminNote === '') {

        return ['ok' => false, 'message' => 'Vui lòng nhập lý do từ chối.'];

    }



    $request = returnGetRequestById($conn, $requestId);

    if (!$request || ($request['status'] ?? '') !== 'pending') {

        return ['ok' => false, 'message' => 'Yêu cầu hoàn hàng không hợp lệ hoặc đã được xử lý.'];

    }



    $orderId = (int) $request['order_id'];



    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare("UPDATE order_return_requests

                                SET status = 'rejected', admin_note = ?, reviewed_at = NOW(), reviewed_by = 'admin'

                                WHERE id = ? AND status = 'pending'");

        if (!$stmt) {

            throw new RuntimeException('Không thể từ chối yêu cầu.');

        }

        $stmt->bind_param('si', $adminNote, $requestId);

        $stmt->execute();

        if ($stmt->affected_rows === 0) {

            $stmt->close();

            throw new RuntimeException('Yêu cầu đã được xử lý.');

        }

        $stmt->close();



        $stmtCurrent = $conn->prepare('SELECT status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');

        if (!$stmtCurrent) {

            throw new RuntimeException('Không đọc được đơn hàng.');

        }

        $stmtCurrent->bind_param('i', $orderId);

        $stmtCurrent->execute();

        $currentOrder = $stmtCurrent->get_result()->fetch_assoc();

        $stmtCurrent->close();



        if (!$currentOrder || !orderApplyStatusUpdate($conn, $orderId, 'delivered', 'return_reject', $currentOrder)) {

            throw new RuntimeException('Không khôi phục được trạng thái đơn.');

        }



        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        return ['ok' => false, 'message' => 'Không thể từ chối yêu cầu hoàn hàng.'];

    }



    return ['ok' => true, 'message' => 'Đã từ chối yêu cầu. Đơn hàng quay lại trạng thái Đã giao hàng.'];

}



/**

 * Giai đoạn 3 — Kho đã nhận hàng hoàn, cộng lại tồn kho.

 *

 * @return array{ok:bool,message:string}

 */

function returnAdminConfirmGoodsReceived(mysqli $conn, int $requestId, float $returnShippingCost = 0): array

{

    returnEnsureSchema($conn);

    require_once __DIR__ . '/order-repository.php';



    $request = returnGetRequestById($conn, $requestId);

    if (!$request || ($request['status'] ?? '') !== 'accepted') {

        return ['ok' => false, 'message' => 'Yêu cầu không ở trạng thái chờ nhận hàng hoàn.'];

    }



    $orderId = (int) $request['order_id'];

    $shippingCost = max(0, $returnShippingCost);



    $conn->begin_transaction();

    try {

        $stmtReq = $conn->prepare("UPDATE order_return_requests

                                   SET status = 'goods_received', goods_received_at = NOW(),

                                       return_shipping_cost = ?

                                   WHERE id = ? AND status = 'accepted'");

        if (!$stmtReq) {

            throw new RuntimeException('Không cập nhật được yêu cầu.');

        }

        $stmtReq->bind_param('di', $shippingCost, $requestId);

        $stmtReq->execute();

        if ($stmtReq->affected_rows === 0) {

            $stmtReq->close();

            throw new RuntimeException('Yêu cầu đã được xử lý.');

        }

        $stmtReq->close();



        $stmtCurrent = $conn->prepare('SELECT status, fulfillment_status FROM orders WHERE id = ? LIMIT 1');

        if (!$stmtCurrent) {

            throw new RuntimeException('Không đọc được đơn hàng.');

        }

        $stmtCurrent->bind_param('i', $orderId);

        $stmtCurrent->execute();

        $currentOrder = $stmtCurrent->get_result()->fetch_assoc();

        $stmtCurrent->close();



        if (!$currentOrder || !orderApplyStatusUpdate($conn, $orderId, 'return_received', 'return_goods_received', $currentOrder)) {

            throw new RuntimeException('Không cập nhật được trạng thái đơn.');

        }



        orderMaybeRestockInventory($conn, $orderId);



        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        return ['ok' => false, 'message' => 'Không thể xác nhận nhận hàng hoàn.'];

    }



    return ['ok' => true, 'message' => 'Đã xác nhận nhận hàng hoàn và cộng lại kho. Chờ Admin hoàn tiền cho khách.'];

}



/**

 * Giai đoạn 4 — Hoàn tiền xong, đóng đơn, trừ doanh thu thuần.

 *

 * @return array{ok:bool,message:string}

 */

function returnAdminCompleteRefund(mysqli $conn, int $requestId, string $adminNote = ''): array

{

    returnEnsureSchema($conn);

    require_once __DIR__ . '/order-repository.php';



    $request = returnGetRequestById($conn, $requestId);

    if (!$request || ($request['status'] ?? '') !== 'goods_received') {

        return ['ok' => false, 'message' => 'Yêu cầu không ở trạng thái chờ hoàn tiền.'];

    }



    $orderId = (int) $request['order_id'];

    $adminNote = trim($adminNote);



    $conn->begin_transaction();

    try {

        $noteAppend = $adminNote !== '' ? $adminNote : null;

        $stmtReq = $conn->prepare("UPDATE order_return_requests

                                   SET status = 'completed', refunded_at = NOW(),

                                       admin_note = CASE

                                           WHEN ? IS NULL THEN admin_note

                                           ELSE CONCAT(COALESCE(admin_note, ''), '\n\nHoàn tiền: ', ?)

                                       END

                                   WHERE id = ? AND status = 'goods_received'");

        if (!$stmtReq) {

            throw new RuntimeException('Không cập nhật được yêu cầu.');

        }

        $stmtReq->bind_param('ssi', $noteAppend, $noteAppend, $requestId);

        $stmtReq->execute();

        if ($stmtReq->affected_rows === 0) {

            $stmtReq->close();

            throw new RuntimeException('Yêu cầu đã được xử lý.');

        }

        $stmtReq->close();



        $stmtPay = $conn->prepare('SELECT payment_status, status, fulfillment_status FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');

        if (!$stmtPay) {

            throw new RuntimeException('Không đọc được đơn hàng.');

        }

        $stmtPay->bind_param('i', $orderId);

        $stmtPay->execute();

        $orderRow = $stmtPay->get_result()->fetch_assoc();

        $stmtPay->close();

        if (!$orderRow) {

            throw new RuntimeException('Không tìm thấy đơn hàng.');

        }



        if (!orderApplyStatusUpdate($conn, $orderId, 'returned', 'return_complete', $orderRow)) {

            throw new RuntimeException('Không cập nhật được trạng thái đơn.');

        }



        if (($orderRow['payment_status'] ?? '') === 'paid') {

            orderSetPaymentStatusInTransaction($conn, $orderId, 'refunded', 'return_complete');

        }



        require_once __DIR__ . '/coupon-repository.php';

        couponReleaseRedemption($conn, $orderId);



        $conn->commit();

    } catch (Throwable $e) {

        $conn->rollback();

        return ['ok' => false, 'message' => 'Không thể hoàn tất hoàn tiền.'];

    }



    return ['ok' => true, 'message' => 'Đã hoàn tiền và đóng đơn. Doanh thu thuần đã loại trừ đơn này.'];

}



/** @deprecated Dùng returnAdminAccept + returnAdminConfirmGoodsReceived + returnAdminCompleteRefund */

function returnAdminApprove(mysqli $conn, int $requestId, string $adminNote = ''): array

{

    return returnAdminAccept($conn, $requestId, $adminNote);

}


