<?php
session_start();
require_once 'db.php';
require_once 'lang.php';

function registerUser($username, $email, $password, $full_name, $department) {
    $conn = getConnection();
    
    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (username, email, password, full_name, department) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    try {
        $stmt->execute([$username, $email, $hashed_password, $full_name, $department]);
        return true;
    } catch(PDOException $e) {
        if($e->getCode() == 23000) { // Duplicate entry error
            return "Username or email already exists";
        }
        return "Registration failed: " . $e->getMessage();
    }
}

function loginUser($username, $password) {
    $conn = getConnection();
    
    $sql = "SELECT id, username, password, role, full_name FROM users WHERE username = ? OR email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        return true;
    }
    
    return false;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function logout() {
    session_destroy();
    header("Location: login.php");
    exit();
}

function getUserRole() {
    return isset($_SESSION['role']) ? $_SESSION['role'] : null;
}

function requireLogin() {
    if(!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function requireRole($role) {
    if(!isLoggedIn() || getUserRole() !== $role) {
        header("Location: index.php");
        exit();
    }
}
?>