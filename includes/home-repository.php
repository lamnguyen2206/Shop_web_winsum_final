<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/blog-repository.php';

function homeFormatCurrency(float $amount): string
{
    return number_format($amount, 0, ',', '.') . 'đ';
}

/** Danh mục trang chủ (6 loại, thứ tự cố định gồm kệ trang trí). */
function homeGetDisplayCategories(mysqli $conn): array
{
    require_once __DIR__ . '/product-repository.php';

    return productGetNavMenuCategories($conn);
}

function homeCategoryImageUrl(string $slug): string
{
    static $map = [
        'den-tha-tran' => 'assets/images/đèn thả ph.webp',
        'den-tuong' => 'assets/images/index-c2.webp',
        'den-ban' => 'assets/images/index-c3.webp',
        'den-san' => 'assets/images/đèn sàn sofa.webp',
        'den-chum' => 'assets/images/đèn thả chùm bauhaus.webp',
        'ke-trang-tri' => 'assets/images/kệ tivi 4 chân.webp',
    ];

    $path = $map[$slug] ?? 'assets/images/index-c3.webp';
    $fullPath = dirname(__DIR__) . '/' . $path;
    if (!is_file($fullPath)) {
        return 'assets/images/index-c3.webp';
    }

    return $path;
}

function homeGetFeaturedProducts(mysqli $conn, int $limit = 3): array
{
    $sql = "SELECT p.id, p.slug, p.name, p.base_price, c.name AS category_name,
                   COALESCE(pi.image_url, p.slug) AS image_or_slug
            FROM products p
            JOIN categories c ON c.id = p.category_id
            LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
            WHERE p.is_active = 1
              AND (p.published_at IS NULL OR p.published_at <= NOW())
            ORDER BY p.is_featured DESC, p.published_at DESC, p.id DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $image = $row['image_or_slug'];
        if ($image === null || strpos($image, '/') === false) {
            $image = 'assets/images/blog_1.png';
        }

        $products[] = [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'name' => $row['name'],
            'category' => $row['category_name'],
            'price' => homeFormatCurrency((float) $row['base_price']),
            'image' => $image
        ];
    }
    $stmt->close();
    return $products;
}

function homeGetNewsPosts(mysqli $conn, int $limit = 2): array
{
    $posts = blogGetFeaturedPosts($conn, $limit);
    if (empty($posts)) {
        $posts = array_slice(blogGetAllPosts($conn), 0, $limit);
    }
    return $posts;
}

/**
 * Sản phẩm chủ lực: ưu tiên theo số lượng đã mua, không đủ thì lấy is_featured.
 */
function homeGetBestsellerProducts(mysqli $conn, ?int $limit = null): array
{
    require_once __DIR__ . '/product-repository.php';
    $limit = $limit ?? productFeaturedHomeLimit();
    $fromSales = productGetBestSellers($conn, $limit);
    if ($fromSales !== []) {
        return $fromSales;
    }
    return homeGetFeaturedProducts($conn, $limit);
}
