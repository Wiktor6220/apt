<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = (int)$_POST['medicine_id'];
    $disposal_date = $_POST['disposal_date'];
    $reason = trim($_POST['reason']);

    try {
        $conn = getDbConnection();
        
        // Sprawdź czy użytkownik ma dostęp do leku
        $stmt = $conn->prepare("
            SELECT m.*, mb.user_id as box_owner_id,
                   (m.package_count * m.units_per_package) as total_units
            FROM medicines m 
            JOIN medicine_boxes mb ON m.box_id = mb.id 
            LEFT JOIN box_users bu ON mb.id = bu.box_id
            WHERE m.id = ? AND (mb.user_id = ? OR bu.user_id = ?)
        ");
        $stmt->bind_param("iii", $medicine_id, $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $medicine = $stmt->get_result()->fetch_assoc();

        if (!$medicine) {
            header("Location: index.php");
            exit();
        }

        $conn->begin_transaction();

        // Dodaj wpis o utylizacji leku
        $stmt = $conn->prepare("INSERT INTO disposals (medicine_id, user_id, quantity, dispose_date, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $medicine_id, $_SESSION['user_id'], $medicine['total_units'], $disposal_date, $reason);
        $stmt->execute();

        // Dodaj wpis do historii ruchów magazynowych
        $stmt = $conn->prepare("INSERT INTO stock_movements (medicine_id, user_id, type, quantity, movement_date) VALUES (?, ?, 'dispose', ?, NOW())");
        $stmt->bind_param("iii", $medicine_id, $_SESSION['user_id'], $medicine['total_units']);
        $stmt->execute();

        // Usuń lek
        $stmt = $conn->prepare("DELETE FROM medicines WHERE id = ?");
        $stmt->bind_param("i", $medicine_id);
        $stmt->execute();

        $conn->commit();
        header("Location: view_box.php?id=" . $_POST['box_id'] . "&success=Zarejestrowano utylizację leku");
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        header("Location: view_box.php?id=" . $_POST['box_id'] . "&error=Wystąpił błąd podczas rejestrowania utylizacji leku");
    }
    exit();
}

$stmt->close();
$conn->close();
exit();
?> 