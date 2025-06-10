<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$name = trim($_POST['name']);

if (empty($name)) {
    header("Location: index.php?error=Nazwa apteczki jest wymagana");
    exit();
} elseif (strlen($name) < 3) {
    header("Location: index.php?error=Nazwa apteczki musi mieć co najmniej 3 znaki");
    exit();
}

try {
    $conn = getDbConnection();
    
    $stmt = $conn->prepare("INSERT INTO medicine_boxes (name, user_id) VALUES (?, ?)");
    $stmt->bind_param("si", $name, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        header("Location: index.php?success=Apteczka została utworzona pomyślnie");
    } else {
        header("Location: index.php?error=Wystąpił błąd podczas tworzenia apteczki");
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    header("Location: index.php?error=Wystąpił błąd podczas tworzenia apteczki");
}

exit();
?> 