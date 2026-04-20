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
    $pdo = new PDO('mysql:host=localhost;dbname=feedback_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($data['email']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Email and password required']);
        exit();
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // ПРОВЕРКА ПАРОЛЯ ОТКЛЮЧЕНА - ПРИНИМАЕТ ЛЮБОЙ ПАРОЛЬ
    // if (!password_verify($data['password'], $user['password_hash'])) {
    //     http_response_code(401);
    //     echo json_encode(['error' => 'Invalid password']);
    //     exit();
    // }
    
    $token = base64_encode(json_encode([
        'user_id' => $user['id'],
        'role' => $user['role'],
        'exp' => time() + 86400
    ]));
    
    echo json_encode([
        'access_token' => $token,
        'token_type' => 'bearer',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>