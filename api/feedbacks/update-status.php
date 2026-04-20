<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'PATCH') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=feedback_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $headers = getallheaders();
    $token = str_replace('Bearer ', '', $headers['Authorization'] ?? '');
    $payload = json_decode(base64_decode($token), true);
    
    if (!$payload || $payload['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Admin only']);
        exit();
    }
    
    $feedbackId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if (!$feedbackId) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        exit();
    }
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['status']) || !in_array($data['status'], ['new', 'in_progress', 'completed'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        exit();
    }
    
    $stmt = $pdo->prepare("UPDATE feedbacks SET status = ? WHERE id = ?");
    $stmt->execute([$data['status'], $feedbackId]);
    
    $stmt = $pdo->prepare("
        SELECT f.*, c.name as category_name, c.color as category_color 
        FROM feedbacks f 
        JOIN categories c ON f.category_id = c.id 
        WHERE f.id = ?
    ");
    $stmt->execute([$feedbackId]);
    $feedback = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($feedback);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>