<?php
require_once 'config.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$box_id = (int)$_POST['box_id'];
$drug_id = (int)$_POST['drug_id'];
$package_count = (int)$_POST['package_count'];
$units_per_package = (int)$_POST['units_per_package'];
$price = (float)$_POST['price'];
$expiration_date = $_POST['expiration_date'];

// Walidacja danych
if ($package_count <= 0) {
    header("Location: view_box.php?id=" . $box_id . "&error=Nieprawidłowa ilość opakowań");
    exit();
}

if ($units_per_package <= 0) {
    header("Location: view_box.php?id=" . $box_id . "&error=Nieprawidłowa ilość jednostek w opakowaniu");
    exit();
}

if ($price <= 0) {
    header("Location: view_box.php?id=" . $box_id . "&error=Nieprawidłowa cena");
    exit();
}

try {
    $conn = getDbConnection();
    
    // Sprawdź czy użytkownik ma dostęp do apteczki
    $stmt = $conn->prepare("
        SELECT mb.id 
        FROM medicine_boxes mb 
        LEFT JOIN box_users bu ON mb.id = bu.box_id 
        WHERE mb.id = ? AND (mb.user_id = ? OR bu.user_id = ?)
    ");
    $stmt->bind_param("iii", $box_id, $_SESSION['user_id'], $_SESSION['user_id']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        header("Location: index.php");
        exit();
    }

    // Sprawdź czy lek istnieje w słowniku
    $stmt = $conn->prepare("SELECT id FROM drug_dictionary WHERE id = ?");
    $stmt->bind_param("i", $drug_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        header("Location: view_box.php?id=" . $box_id . "&error=Wybrany lek nie istnieje w słowniku");
        exit();
    }

    // Rozpocznij transakcję
    $conn->begin_transaction();

    try {
        // Dodaj lek do apteczki
        $stmt = $conn->prepare("
            INSERT INTO medicines (
                box_id, 
                drug_id, 
                package_count, 
                units_per_package, 
                units_remaining, 
                price, 
                expiration_date, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $units_remaining = 0;
        $stmt->bind_param("iiiiids", $box_id, $drug_id, $package_count, $units_per_package, $units_remaining, $price, $expiration_date);

        if ($stmt->execute()) {
            $medicine_id = $conn->insert_id;
            
            // Dodaj wpis do historii ruchów magazynowych
            $total_units = $package_count * $units_per_package;
            $stmt = $conn->prepare("
                INSERT INTO stock_movements (
                    medicine_id, 
                    user_id, 
                    type, 
                    quantity, 
                    movement_date
                ) VALUES (?, ?, 'add', ?, NOW())
            ");
            $stmt->bind_param("iii", $medicine_id, $_SESSION['user_id'], $total_units);
            $stmt->execute();
            
            $conn->commit();
            header("Location: view_box.php?id=" . $box_id . "&success=Dodano nowy lek do apteczki");
        } else {
            throw new Exception("Błąd podczas dodawania leku");
        }
    } catch (Exception $e) {
        $conn->rollback();
        header("Location: view_box.php?id=" . $box_id . "&error=Wystąpił błąd podczas dodawania leku");
    }
} catch (Exception $e) {
    header("Location: view_box.php?id=" . $box_id . "&error=Wystąpił błąd podczas dodawania leku");
}

$stmt->close();
$conn->close();
exit();
?> 