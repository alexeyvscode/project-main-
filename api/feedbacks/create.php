<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Подключение к БД
    $pdo = new PDO('mysql:host=localhost;dbname=feedback_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Получаем данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        $data = $_POST;
    }
    
    // Проверяем авторизацию
    $authorId = null;
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        $token = str_replace('Bearer ', '', $headers['Authorization']);
        $payload = json_decode(base64_decode($token), true);
        if ($payload && isset($payload['user_id'])) {
            $authorId = $payload['user_id'];
        }
    }
    
    // Валидация
    $categoryId = isset($data['category_id']) ? intval($data['category_id']) : 0;
    $message = isset($data['message']) ? trim($data['message']) : '';
    $userName = isset($data['user_name']) ? trim($data['user_name']) : '';
    
    if (!$categoryId) {
        http_response_code(400);
        echo json_encode(['error' => 'Category is required']);
        exit();
    }
    
    if (strlen($message) < 5) {
        http_response_code(400);
        echo json_encode(['error' => 'Message must be at least 5 characters']);
        exit();
    }
    
    if (!$authorId && !$userName) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required for anonymous feedback']);
        exit();
    }
    
    // Сохраняем отзыв
    $stmt = $pdo->prepare("
        INSERT INTO feedbacks (author_id, user_name, category_id, message, status, created_at) 
        VALUES (?, ?, ?, ?, 'new', NOW())
    ");
    $stmt->execute([$authorId, $userName ?: null, $categoryId, $message]);
    
    $feedbackId = $pdo->lastInsertId();
    
    // Получаем созданный отзыв
    $stmt = $pdo->prepare("
        SELECT f.*, c.name as category_name, c.color as category_color 
        FROM feedbacks f 
        JOIN categories c ON f.category_id = c.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
    
    http_response_code(201);
    echo json_encode($feedback);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>