<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=feedback_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $userId = null;
    $isAdmin = false;
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $payload = json_decode(base64_decode($token), true);
        if ($payload) {
            $userId = $payload['user_id'] ?? null;
            $isAdmin = ($payload['role'] ?? '') === 'admin';
        }
    }
    
    if (!$userId) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit();
    }
    
    $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
    $size = isset($_GET['size']) ? intval($_GET['size']) : 10;
    $offset = ($page - 1) * $size;
    
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    $sort = (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'ASC' : 'DESC';
    
    // НОВЫЙ ПАРАМЕТР: если true — показываем только свои отзывы (для профиля)
    $myOnly = isset($_GET['my_only']) && $_GET['my_only'] === 'true';
    
    $whereParts = [];
    
    // Если my_only=true — показываем ТОЛЬКО свои, независимо от роли
    if ($myOnly) {
        $whereParts[] = "f.author_id = " . intval($userId);
    } else {
        // Иначе: админ видит всё, оператор только свои
        if (!$isAdmin) {
            $whereParts[] = "f.author_id = " . intval($userId);
        }
    }
    
    if ($categoryId > 0) {
        $whereParts[] = "f.category_id = " . intval($categoryId);
    }
    
    if (!empty($status) && in_array($status, ['new', 'in_progress', 'completed'])) {
        $whereParts[] = "f.status = '" . addslashes($status) . "'";
    }
    
    $whereSQL = empty($whereParts) ? "" : "WHERE " . implode(" AND ", $whereParts);
    
    $countSql = "SELECT COUNT(*) as total FROM feedbacks f " . $whereSQL;
    $total = $pdo->query($countSql)->fetch(PDO::FETCH_ASSOC)['total'];
    
    $querySql = "
        SELECT f.*, c.name as category_name, c.color as category_color,
               u.name as author_name, u.email as author_email
        FROM feedbacks f 
        JOIN categories c ON f.category_id = c.id 
        LEFT JOIN users u ON f.author_id = u.id
        $whereSQL
        ORDER BY f.created_at $sort
        LIMIT $offset, $size
    ";
    
    $items = $pdo->query($querySql)->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'items' => $items,
        'total' => intval($total),
        'page' => $page,
        'size' => $size,
        'pages' => ceil($total / $size)
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>