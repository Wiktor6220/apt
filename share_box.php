<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$box_id = (int)$_POST['box_id'];
$email = trim($_POST['email']);
$access_level = $_POST['access_level'] ?? 'view'; // 'view' lub 'edit'

try {
    $conn = getDbConnection();
    
    // Sprawdź czy użytkownik jest właścicielem apteczki
    $stmt = $conn->prepare("SELECT user_id FROM medicine_boxes WHERE id = ?");
    $stmt->bind_param("i", $box_id);
    $stmt->execute();
    $box = $stmt->get_result()->fetch_assoc();
    
    if (!$box || $box['user_id'] !== $_SESSION['user_id']) {
        header("Location: index.php");
        exit();
    }
    
    // Sprawdź czy użytkownik o podanym emailu istnieje
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        header("Location: view_box.php?id=" . $box_id . "&error=Użytkownik o podanym adresie email nie istnieje w systemie");
        exit();
    }
    
    // Sprawdź czy użytkownik nie jest już właścicielem apteczki
    if ($user['id'] === $_SESSION['user_id']) {
        header("Location: view_box.php?id=" . $box_id . "&error=Nie możesz udostępnić apteczki samemu sobie");
        exit();
    }
    
    // Sprawdź czy użytkownik nie ma już dostępu do apteczki
    $stmt = $conn->prepare("
        SELECT bu.*, u.username 
        FROM box_users bu
        JOIN users u ON bu.user_id = u.id
        WHERE bu.box_id = ? AND bu.user_id = ?
    ");
    $stmt->bind_param("ii", $box_id, $user['id']);
    $stmt->execute();
    $existing_access = $stmt->get_result()->fetch_assoc();
    
    if ($existing_access) {
        header("Location: view_box.php?id=" . $box_id . "&error=Użytkownik " . htmlspecialchars($existing_access['username']) . " ma już dostęp do tej apteczki");
        exit();
    }
    
    // Dodaj dostęp do apteczki
    $stmt = $conn->prepare("
        INSERT INTO box_users (box_id, user_id, access_level) 
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $box_id, $user['id'], $access_level);
    
    if ($stmt->execute()) {
        $access_level_text = $access_level === 'view' ? 'wgląd' : 'edycję';
        header("Location: view_box.php?id=" . $box_id . "&success=Apteczka została udostępniona użytkownikowi " . htmlspecialchars($user['username']) . " z uprawnieniami do " . $access_level_text);
    } else {
        throw new Exception("Błąd podczas udostępniania apteczki");
    }
    
} catch (Exception $e) {
    header("Location: view_box.php?id=" . $box_id . "&error=Wystąpił błąd podczas udostępniania apteczki: " . $e->getMessage());
}

$stmt->close();
$conn->close();
exit();
?> 