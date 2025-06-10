<?php
// Konfiguracja bazy danych
define('DB_HOST', 'xxx');
define('DB_NAME', 'xxx');
define('DB_USER', 'xxx');
define('DB_PASS', 'xxx');

// Inicjalizacja sesji
session_start();

// Funkcja do połączenia z bazą danych
function getDbConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8");
        return $conn;
    } catch (Exception $e) {
        die("Błąd połączenia z bazą danych: " . $e->getMessage());
    }
}

// Funkcja do sprawdzania czy użytkownik jest zalogowany
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Funkcja do przekierowania niezalogowanych użytkowników
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Funkcja do bezpiecznego wyświetlania danych
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Funkcja do obsługi błędów
function handleError($message, $redirect = null) {
    if ($redirect) {
        header("Location: " . $redirect . "?error=" . urlencode($message));
    } else {
        die($message);
    }
    exit();
}

// Funkcja do obsługi sukcesu
function handleSuccess($message, $redirect) {
    header("Location: " . $redirect . "?success=" . urlencode($message));
    exit();
}
?> 