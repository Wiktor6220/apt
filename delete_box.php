<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$box_id = (int)$_GET['id'];

try {
    $conn = getDbConnection();

    // Sprawdź czy użytkownik ma dostęp do apteczki
    $stmt = $conn->prepare("SELECT id FROM medicine_boxes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $box_id, $_SESSION['user_id']);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        header("Location: index.php");
        exit();
    }

    // Rozpocznij transakcję
    $conn->begin_transaction();

    try {
        // Usuń wszystkie powiązane rekordy
        $stmt = $conn->prepare("DELETE FROM stock_movements WHERE medicine_id IN (SELECT id FROM medicines WHERE box_id = ?)");
        $stmt->bind_param("i", $box_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM intakes WHERE medicine_id IN (SELECT id FROM medicines WHERE box_id = ?)");
        $stmt->bind_param("i", $box_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM disposals WHERE medicine_id IN (SELECT id FROM medicines WHERE box_id = ?)");
        $stmt->bind_param("i", $box_id);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM medicines WHERE box_id = ?");
        $stmt->bind_param("i", $box_id);
        $stmt->execute();

        // Usuń apteczkę
        $stmt = $conn->prepare("DELETE FROM medicine_boxes WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $box_id, $_SESSION['user_id']);
        $stmt->execute();

        $conn->commit();
        header("Location: index.php?success=delete");
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: index.php?error=delete");
    }
} catch (Exception $e) {
    header("Location: index.php?error=delete");
}

$stmt->close();
$conn->close();
exit();
?> 