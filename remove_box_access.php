<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['box_id']) || !isset($_GET['user_id'])) {
    header("Location: index.php");
    exit();
}

$box_id = (int)$_GET['box_id'];
$user_id = (int)$_GET['user_id'];

try {
    $conn = getDbConnection();
    
    // Sprawdź czy użytkownik jest właścicielem apteczki
    $stmt = $conn->prepare("SELECT id FROM medicine_boxes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $box_id, $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    
    // Usuń dostęp
    $stmt = $conn->prepare("DELETE FROM box_users WHERE box_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $box_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: view_box.php?id=" . $box_id . "&success=Usunięto dostęp użytkownika do apteczki");
    } else {
        header("Location: view_box.php?id=" . $box_id . "&error=Wystąpił błąd podczas usuwania dostępu");
    }
} catch (Exception $e) {
    header("Location: view_box.php?id=" . $box_id . "&error=Wystąpił błąd podczas usuwania dostępu");
}

$stmt->close();
$conn->close();
exit();
?> 