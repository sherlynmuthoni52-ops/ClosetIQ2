<?php
/**
 * Authentication Helper
 * ClosetIQ - Smart Wardrobe Inventory and Outfit Planner
 * 
 * Handles session management, login, registration, and logout.
 */

session_start();
require_once '../config/database.php';

/**
 * Redirects to index.php if user is not logged in.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
}

/**
 * Authenticates a user with username and password.
 * Returns user data on success, false on failure.
 */
function login($username, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare('SELECT id, username, email, password_hash FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        return $user;
    }
    
    return false;
}

/**
 * Registers a new user.
 * Returns user ID on success, false on failure.
 */
function register($username, $email, $password) {
    global $pdo;
    
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (:username, :email, :password_hash)');
    return $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password_hash' => $password_hash
    ]);
}

/**
 * Logs out the current user by destroying the session.
 */
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

/**
 * Gets the currently logged-in user's data.
 */
function get_logged_in_user() {
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare('SELECT id, username, email FROM users WHERE id = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}
