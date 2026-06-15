<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/product-repository.php';

function productAdminSlugify(string $text): string
{
    $text = trim(mb_strtolower($text, 'UTF-8'));
    $text = preg_replace('/[đĐ]/u', 'd', $text);
    $text = preg_replace('/[^a-z0-9\s-]/u', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-') ?: 'san-pham';
}

function productAdminList(mysqli $conn, int $limit = 200, string $search = ''): array
{
    $search = trim($search);
    $baseSql = "SELECT p.id, p.name, p.slug, p.sku, p.base_price, p.stock_status, p.is_active, p.is_featured,
                       c.name AS category_name,
                       COALESCE(inv.quantity_on_hand, 0) AS stock_quantity,
                       COALESCE(sales.units_sold, 0) AS units_sold
                FROM products p
                JOIN categories c ON c.id = p.category_id
                LEFT JOIN inventory_items inv ON inv.product_id = p.id
                LEFT JOIN (
                    SELECT oi.product_id, SUM(oi.quantity) AS units_sold
                    FROM order_items oi
                    INNER JOIN orders o ON o.id = oi.order_id
                    WHERE oi.product_id IS NOT NULL
                      AND o.status NOT IN ('cancelled', 'returned')
                      AND o.fulfillment_status NOT IN ('cancelled', 'returned')
                    GROUP BY oi.product_id
                ) sales ON sales.product_id = p.id";

    if ($search !== '') {
        $like = '%' . $search . '%';
        $sql = $baseSql . "
                WHERE p.name LIKE ?
                   OR p.sku LIKE ?
                   OR p.slug LIKE ?
                   OR c.name LIKE ?
                ORDER BY units_sold DESC, p.id DESC
                LIMIT ?";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return [];
        }
        $limitParam = (int) $limit;
        $stmt->bind_param('ssssi', $like, $like, $like, $like, $limitParam);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = $baseSql . "
                ORDER BY units_sold DESC, p.id DESC
                LIMIT " . (int) $limit;
        $result = $conn->query($sql);
    }

    if (!$result) {
        return [];
    }

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $row['price_label'] = productFormatPrice((float) $row['base_price']);
        $items[] = $row;
    }

    if (isset($stmt)) {
        $stmt->close();
    }

    return $items;
}

function productAdminGetById(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare("SELECT p.id, p.category_id, p.name, p.slug, p.sku, p.short_description, p.description,
                                   p.base_price, p.stock_status, p.material, p.color, p.warranty_months,
                                   p.is_featured, p.is_active,
                                   pi.image_url AS primary_image
                            FROM products p
                            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
                            WHERE p.id = ?
                            LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) {
        require_once __DIR__ . '/inventory-repository.php';
        $row['stock_quantity'] = inventoryGetProductQty($conn, (int) $row['id']);
    }
    return $row ?: null;
}

function productAdminSave(mysqli $conn, array $data): array
{
    $id = (int) ($data['id'] ?? 0);
    $name = trim((string) ($data['name'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    $sku = trim((string) ($data['sku'] ?? ''));
    $categoryId = (int) ($data['category_id'] ?? 0);
    $shortDescription = trim((string) ($data['short_description'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $basePrice = (float) ($data['base_price'] ?? 0);
    $stockStatus = (string) ($data['stock_status'] ?? 'in_stock');
    $material = trim((string) ($data['material'] ?? ''));
    $color = trim((string) ($data['color'] ?? ''));
    $warrantyMonths = ($data['warranty_months'] ?? '') !== '' ? (int) $data['warranty_months'] : null;
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $imageUrl = trim((string) ($data['image_url'] ?? ''));

    if ($name === '' || $sku === '' || $categoryId <= 0) {
        return ['ok' => false, 'message' => 'Vui lòng nhập tên, SKU và chọn danh mục.'];
    }
    if (!in_array($stockStatus, ['in_stock', 'out_of_stock', 'preorder'], true)) {
        $stockStatus = 'in_stock';
    }
    if ($slug === '') {
        $slug = productAdminSlugify($name);
    }

    $materialParam = $material !== '' ? $material : null;
    $colorParam = $color !== '' ? $color : null;

    $conn->begin_transaction();
    try {
        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE products SET
                category_id = ?, name = ?, slug = ?, sku = ?, short_description = ?, description = ?,
                base_price = ?, stock_status = ?, material = ?, color = ?, warranty_months = ?,
                is_featured = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?");
            if (!$stmt) {
                throw new RuntimeException('Không cập nhật được sản phẩm.');
            }
            $warrantyValue = $warrantyMonths ?? 0;
            $stmt->bind_param(
                'isssssdsssiiii',
                $categoryId,
                $name,
                $slug,
                $sku,
                $shortDescription,
                $description,
                $basePrice,
                $stockStatus,
                $materialParam,
                $colorParam,
                $warrantyValue,
                $isFeatured,
                $isActive,
                $id
            );
            $stmt->execute();
            $stmt->close();
            $productId = $id;
            $message = 'Đã cập nhật sản phẩm.';
        } else {
            $stmt = $conn->prepare("INSERT INTO products
                (category_id, name, slug, short_description, description, sku,
                 base_price, stock_status, material, color, warranty_months,
                 is_featured, is_active, published_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            if (!$stmt) {
                throw new RuntimeException('Không tạo được sản phẩm.');
            }
            $warrantyValue = $warrantyMonths ?? 0;
            $stmt->bind_param(
                'isssssdsssiii',
                $categoryId,
                $name,
                $slug,
                $shortDescription,
                $description,
                $sku,
                $basePrice,
                $stockStatus,
                $materialParam,
                $colorParam,
                $warrantyValue,
                $isFeatured,
                $isActive
            );
            $stmt->execute();
            $productId = (int) $stmt->insert_id;
            $stmt->close();
            $message = 'Đã thêm sản phẩm mới.';
        }

        if ($imageUrl !== '') {
            $stmtDel = $conn->prepare("DELETE FROM product_images WHERE product_id = ?");
            if ($stmtDel) {
                $stmtDel->bind_param('i', $productId);
                $stmtDel->execute();
                $stmtDel->close();
            }
            $sortOrder = 1;
            $isPrimary = 1;
            $altText = $name;
            $stmtImg = $conn->prepare("INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary)
                                       VALUES (?, ?, ?, ?, ?)");
            if ($stmtImg) {
                $stmtImg->bind_param('issii', $productId, $imageUrl, $altText, $sortOrder, $isPrimary);
                $stmtImg->execute();
                $stmtImg->close();
            }
        }

        require_once __DIR__ . '/inventory-repository.php';
        $stockQty = isset($data['stock_quantity']) && $data['stock_quantity'] !== ''
            ? (int) $data['stock_quantity']
            : inventoryGetProductQty($conn, $productId);
        $stockUpdateOk = inventorySetProductQty($conn, $productId, $stockQty);
        if (!$stockUpdateOk) {
            throw new RuntimeException('Không cập nhật được tồn kho sản phẩm.');
        }
        if ($stockQty > 0 && $stockStatus === 'preorder') {
            $stmtStock = $conn->prepare("UPDATE products SET stock_status = 'in_stock', updated_at = NOW() WHERE id = ?");
            if (!$stmtStock) {
                throw new RuntimeException('Không cập nhật được trạng thái tồn kho.');
            }
            $stmtStock->bind_param('i', $productId);
            $stmtStock->execute();
            $stmtStock->close();
        }

        $conn->commit();

        return ['ok' => true, 'message' => $message, 'product_id' => $productId];
    } catch (Throwable $e) {
        $conn->rollback();
        return ['ok' => false, 'message' => 'Lỗi lưu sản phẩm: có thể slug hoặc SKU đã tồn tại.'];
    }
}

function productAdminDelete(mysqli $conn, int $id): bool
{
    $stmt = $conn->prepare("UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = ?");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}
