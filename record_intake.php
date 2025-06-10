<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_id = (int)$_POST['medicine_id'];
    $quantity = (int)$_POST['quantity'];
    $intake_date = $_POST['intake_date'];
    $notes = trim($_POST['notes']);

    if ($quantity <= 0) {
        header("Location: view_box.php?id=" . $_POST['box_id'] . "&error=Niewłaściwa ilość leku");
        exit();
    }

    try {
        $conn = getDbConnection();
        
        // 1. Sprawdź czy użytkownik ma dostęp do leku i pobierz aktualne dane
        $stmt = $conn->prepare("
            SELECT m.*, mb.user_id as box_owner_id,
                   (m.package_count * m.units_per_package + m.units_remaining) as total_units
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

        // 2. Sprawdź czy jest wystarczająca ilość leku
        if ($medicine['total_units'] < $quantity) {
            header("Location: view_box.php?id=" . $_POST['box_id'] . "&error=Niewystarczająca ilość leku w apteczce");
            exit();
        }

        $conn->begin_transaction();

        // 3. Oblicz nową ilość jednostek
        $remaining_units = $medicine['total_units'] - $quantity;
        
        // 4. Przelicz na pełne opakowania i pozostałe jednostki
        $new_package_count = floor($remaining_units / $medicine['units_per_package']);
        $new_remaining_units = $remaining_units % $medicine['units_per_package'];

        // 5. Aktualizuj ilość opakowań i pozostałych jednostek w bazie
        $stmt = $conn->prepare("UPDATE medicines SET package_count = ?, units_remaining = ? WHERE id = ?");
        $stmt->bind_param("iii", $new_package_count, $new_remaining_units, $medicine_id);
        if (!$stmt->execute()) {
            throw new Exception("Błąd podczas aktualizacji ilości leku: " . $stmt->error);
        }

        // 6. Dodaj wpis o przyjęciu leku
        $stmt = $conn->prepare("
            INSERT INTO intakes (
                medicine_id, 
                user_id, 
                dosage_time, 
                amount,
                notes
            ) VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iisss", $medicine_id, $_SESSION['user_id'], $intake_date, $quantity, $notes);
        if (!$stmt->execute()) {
            throw new Exception("Błąd podczas dodawania wpisu o przyjęciu leku: " . $stmt->error);
        }

        // 7. Dodaj wpis do historii ruchów magazynowych
        $stmt = $conn->prepare("
            INSERT INTO stock_movements (
                medicine_id, 
                user_id, 
                type, 
                quantity, 
                movement_date
            ) VALUES (?, ?, 'intake', ?, NOW())
        ");
        $stmt->bind_param("iii", $medicine_id, $_SESSION['user_id'], $quantity);
        if (!$stmt->execute()) {
            throw new Exception("Błąd podczas dodawania wpisu do historii ruchów: " . $stmt->error);
        }

        $conn->commit();
        
        // 8. Przekieruj z powrotem do apteczki
        header("Location: view_box.php?id=" . $_POST['box_id'] . "&success=Zarejestrowano przyjęcie leku");
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        header("Location: view_box.php?id=" . $_POST['box_id'] . "&error=Wystąpił błąd podczas rejestrowania przyjęcia leku: " . $e->getMessage());
    }
    exit();
}

$stmt->close();
$conn->close();
exit();
?> 