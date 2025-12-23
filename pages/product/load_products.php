<?php
/**
 * AJAX endpoint for loading products
 * File: pages/product/load_products.php
 */

header('Content-Type: application/json');
require_once '../../helper/pdo_connection.php';

try {
    // Get filter parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 18; // Changed to 18 products per page
    $offset = ($page - 1) * $per_page;

    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';
    $min_price = isset($_GET['min_price']) ? max(0, floatval($_GET['min_price'])) : 0;
    $max_price = isset($_GET['max_price']) ? max(0, floatval($_GET['max_price'])) : 0;
    $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

    // Ensure min_price <= max_price if both are set
    if ($min_price > 0 && $max_price > 0 && $min_price > $max_price) {
        // Swap values if min > max
        $temp = $min_price;
        $min_price = $max_price;
        $max_price = $temp;
    }

    // Build query conditions
    $where_conditions = [];
    $params = [];

    // Only show available products
    $where_conditions[] = "p.status = 'Available'";

    if (!empty($search)) {
        $where_conditions[] = "(p.name LIKE :search_name OR p.description LIKE :search_desc)";
        $params[':search_name'] = '%' . $search . '%';
        $params[':search_desc'] = '%' . $search . '%';
    }

    if (!empty($category)) {
        $where_conditions[] = "p.category = :category";
        $params[':category'] = $category;
    }

    if ($min_price > 0) {
        $where_conditions[] = "p.price >= :min_price";
        $params[':min_price'] = $min_price;
    }

    if ($max_price > 0) {
        $where_conditions[] = "p.price <= :max_price";
        $params[':max_price'] = $max_price;
    }

    $where_sql = 'WHERE ' . implode(' AND ', $where_conditions);

    // Determine sorting
    $order_by = 'p.product_id DESC';
    switch ($sort) {
        case 'price_low':
            $order_by = 'p.price ASC';
            break;
        case 'price_high':
            $order_by = 'p.price DESC';
            break;
        case 'name_asc':
            $order_by = 'p.name ASC';
            break;
        case 'name_desc':
            $order_by = 'p.name DESC';
            break;
        case 'newest':
        default:
            $order_by = 'p.product_id DESC';
            break;
    }

    // Get total count
    $count_query = "SELECT COUNT(DISTINCT p.product_id) as total FROM Product p LEFT JOIN Review r ON p.product_id = r.product_id $where_sql";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_products / $per_page);

    // Get products with dynamic ratings
    $query = "SELECT p.*, 
              (SELECT sub_image_path FROM Product_Sub_Image WHERE product_id = p.product_id LIMIT 1) as first_sub_image,
              COALESCE(AVG(r.rating), 0) as average_rating,
              COUNT(r.rating) as review_count
              FROM Product p 
              LEFT JOIN Review r ON p.product_id = r.product_id
              $where_sql
              GROUP BY p.product_id
              ORDER BY $order_by
              LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Normalize image paths (remove ../ prefix if present)
    foreach ($products as &$product) {
        if (!empty($product['main_image_path']) && strpos($product['main_image_path'], '../') === 0) {
            $product['main_image_path'] = substr($product['main_image_path'], 3);
        }
        if (!empty($product['first_sub_image']) && strpos($product['first_sub_image'], '../') === 0) {
            $product['first_sub_image'] = substr($product['first_sub_image'], 3);
        }
    }
    unset($product);

    // Calculate pagination range
    $start_page = max(1, $page - 2);
    $end_page = min($total_pages, $page + 2);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => $total_products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
            'start_page' => $start_page,
            'end_page' => $end_page
        ]
    ]);

} catch (Exception $e) {
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while loading products',
        'error' => $e->getMessage()
    ]);
}
?>