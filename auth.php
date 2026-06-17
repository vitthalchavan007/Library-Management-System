<?php
// auth.php - Authentication endpoints

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($method === 'POST') {
    $input = getJsonInput();
    
    if ($action === 'login') {
        $email = $input['email'] ?? '';
        $password = $input['password'] ?? '';
        $role = $input['role'] ?? '';
        
        if ($role === 'admin') {
            // Admin login (hardcoded for demo)
            if ($email === 'admin' && $password === 'admin123') {
                $token = generateToken(0, 'admin');
                sendResponse([
                    'success' => true,
                    'role' => 'admin',
                    'token' => $token
                ]);
            }
            sendResponse(['error' => 'Invalid admin credentials'], 401);
        }
        
        if ($role === 'student') {
            if (empty($email) || empty($password)) {
                sendResponse(['error' => 'Email and password required'], 400);
            }
            
            $pdo = getDB();
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch();
            
            if ($student && password_verify($password, $student['password'])) {
                $token = generateToken($student['id'], 'student');
                sendResponse([
                    'success' => true,
                    'role' => 'student',
                    'token' => $token,
                    'student' => [
                        'id' => $student['id'],
                        'name' => $student['name'],
                        'email' => $student['email'],
                        'roll' => $student['roll']
                    ]
                ]);
            }
            sendResponse(['error' => 'Invalid credentials'], 401);
        }
        
        sendResponse(['error' => 'Invalid role'], 400);
    }
}

sendResponse(['error' => 'Invalid request'], 400);
?>