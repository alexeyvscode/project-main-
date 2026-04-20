<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    
    // Авторизация (через заголовок или GET-параметр)
    $userId = null;
    $isAdmin = false;
    
    $headers = getallheaders();
    $token = null;
    
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
    } elseif (isset($_GET['token'])) {
        $token = $_GET['token'];
    }
    
    if ($token) {
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
    
    // Параметры фильтрации
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
    $status = isset($_GET['status']) ? $_GET['status'] : '';
    
    $whereParts = [];
    
    if (!$isAdmin) {
        $whereParts[] = "f.author_id = " . intval($userId);
    }
    
    if ($categoryId > 0) {
        $whereParts[] = "f.category_id = " . intval($categoryId);
    }
    
    if (!empty($status) && in_array($status, ['new', 'in_progress', 'completed'])) {
        $whereParts[] = "f.status = '" . addslashes($status) . "'";
    }
    
    $whereSQL = empty($whereParts) ? "" : "WHERE " . implode(" AND ", $whereParts);
    
    // Получаем ВСЕ отзывы (без пагинации)
    $querySql = "
        SELECT f.id, f.user_name, f.message, f.status, f.created_at,
               c.name as category_name,
               u.name as author_name, u.email as author_email
        FROM feedbacks f 
        JOIN categories c ON f.category_id = c.id 
        LEFT JOIN users u ON f.author_id = u.id
        $whereSQL
        ORDER BY f.created_at DESC
    ";
    
    $items = $pdo->query($querySql)->fetchAll(PDO::FETCH_ASSOC);
    
    // Генерируем имя файла
    $filename = 'feedbacks_export_' . date('Y-m-d_His') . '.csv';
    
    // Заголовки для скачивания CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Создаём output stream
    $output = fopen('php://output', 'w');
    
    // Добавляем BOM для корректного отображения UTF-8 в Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Заголовки CSV
    fputcsv($output, [
        'ID',
        'Дата',
        'Категория',
        'Статус',
        'Автор (имя)',
        'Автор (email)',
        'Сообщение'
    ]);
    
    // Данные
    foreach ($items as $item) {
        $authorName = $item['user_name'] ?? $item['author_name'] ?? 'Аноним';
        $authorEmail = $item['author_email'] ?? '-';
        $statusText = $item['status'] === 'new' ? 'Новый' : ($item['status'] === 'in_progress' ? 'В работе' : 'Завершён');
        
        fputcsv($output, [
            $item['id'],
            date('d.m.Y H:i', strtotime($item['created_at'])),
            $item['category_name'],
            $statusText,
            $authorName,
            $authorEmail,
            $item['message']
        ]);
    }
    
    fclose($output);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>