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
    
    // Формируем WHERE
    if ($isAdmin) {
        $whereSql = "1=1";
    } else {
        $whereSql = "author_id = $userId";
    }
    
    // Общее количество
    $total = $pdo->query("SELECT COUNT(*) as total FROM feedbacks WHERE $whereSql")->fetch(PDO::FETCH_ASSOC)['total'];
    
    // По категориям
    $byCategory = [];
    if ($isAdmin) {
        $catSql = "SELECT c.name, COUNT(f.id) as count FROM categories c LEFT JOIN feedbacks f ON c.id = f.category_id GROUP BY c.id, c.name";
    } else {
        $catSql = "SELECT c.name, COUNT(f.id) as count FROM categories c LEFT JOIN feedbacks f ON c.id = f.category_id AND f.author_id = $userId GROUP BY c.id, c.name";
    }
    foreach ($pdo->query($catSql) as $row) {
        $byCategory[$row['name']] = intval($row['count']);
    }
    
    // По статусам
    $byStatus = ['new' => 0, 'in_progress' => 0, 'completed' => 0];
    $statusSql = "SELECT status, COUNT(*) as count FROM feedbacks WHERE $whereSql GROUP BY status";
    foreach ($pdo->query($statusSql) as $row) {
        $byStatus[$row['status']] = intval($row['count']);
    }
    
    // Сегодня
    $today = $pdo->query("SELECT COUNT(*) as count FROM feedbacks WHERE $whereSql AND DATE(created_at) = CURDATE()")->fetch(PDO::FETCH_ASSOC)['count'];
    
    // За неделю
    $week = $pdo->query("SELECT COUNT(*) as count FROM feedbacks WHERE $whereSql AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC)['count'];
    
    // По дням
    $dailyStats = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $count = $pdo->query("SELECT COUNT(*) as count FROM feedbacks WHERE $whereSql AND DATE(created_at) = '$date'")->fetch(PDO::FETCH_ASSOC)['count'];
        $dailyStats[] = ['date' => $date, 'count' => intval($count)];
    }
    
    echo json_encode([
        'total_feedbacks' => intval($total),
        'by_category' => $byCategory,
        'by_status' => $byStatus,
        'new_today' => intval($today),
        'new_this_week' => intval($week),
        'daily_stats' => $dailyStats
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>