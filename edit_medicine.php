<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$medicine_id = (int)$_GET['id'];

try {
    $conn = getDbConnection();

    // Sprawdź czy użytkownik ma dostęp do leku i pobierz szczegóły
    $stmt = $conn->prepare("
        SELECT m.*, mb.user_id as box_owner_id, dd.name as drug_name, 
               (m.package_count * m.units_per_package + m.units_remaining) as total_units
        FROM medicines m 
        JOIN medicine_boxes mb ON m.box_id = mb.id 
        JOIN drug_dictionary dd ON m.drug_id = dd.id
        WHERE m.id = ?
    ");
    $stmt->bind_param("i", $medicine_id);
    $stmt->execute();
    $medicine = $stmt->get_result()->fetch_assoc();

    if (!$medicine || $medicine['box_owner_id'] !== $_SESSION['user_id']) {
        header("Location: index.php");
        exit();
    }

    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_total_quantity = (int)$_POST['quantity']; // Teraz to jest nowa całkowita ilość
        $price = (float)$_POST['price'];
        $expiration_date = $_POST['expiration_date'];
        
        // Oblicz starą całkowitą ilość przed aktualizacją
        $old_total_quantity = $medicine['total_units'];

        if ($new_total_quantity < 0) {
            $error = "Całkowita ilość nie może być ujemna.";
        } elseif ($price < 0) {
            $error = "Cena nie może być ujemna.";
        } elseif (strtotime($expiration_date) < strtotime('today')) {
            $error = "Data ważności nie może być z przeszłości.";
        } else {
            $conn->begin_transaction();
            
            try {
                // Przelicz nową całkowitą ilość na opakowania i pozostałe jednostki
                $new_package_count = floor($new_total_quantity / $medicine['units_per_package']);
                $new_remaining_units = $new_total_quantity % $medicine['units_per_package'];

                // Aktualizuj pola package_count i units_remaining
                $stmt = $conn->prepare("UPDATE medicines SET package_count = ?, units_remaining = ?, price = ?, expiration_date = ? WHERE id = ?");
                $stmt->bind_param("iissi", $new_package_count, $new_remaining_units, $price, $expiration_date, $medicine_id);
                
                if ($stmt->execute()) {
                    // Dodaj wpis do historii ruchów magazynowych jeśli zmieniła się całkowita ilość
                    $quantity_diff = $new_total_quantity - $old_total_quantity;
                    if ($quantity_diff !== 0) { // Zapisz tylko jeśli nastąpiła zmiana ilości
                         // Użyj typu 'edit' lub 'adjustment' i zapisz różnicę (+/-)
                        $stmt = $conn->prepare("INSERT INTO stock_movements (medicine_id, user_id, type, quantity, movement_date) VALUES (?, ?, 'adjustment', ?, NOW())");
                        $stmt->bind_param("iii", $medicine_id, $_SESSION['user_id'], $quantity_diff);
                        $stmt->execute();
                    }
                    
                    $conn->commit();
                    $success = "Lek został zaktualizowany pomyślnie.";
                    // Zaktualizuj lokalne dane $medicine dla wyświetlenia sukcesu
                    $medicine['package_count'] = $new_package_count;
                    $medicine['units_remaining'] = $new_remaining_units;
                    $medicine['price'] = $price;
                    $medicine['expiration_date'] = $expiration_date;
                    $medicine['total_units'] = $new_total_quantity; // Zaktualizuj total_units dla spójności

                } else {
                    throw new Exception("Błąd podczas aktualizacji leku: " . $stmt->error);
                }
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Wystąpił błąd podczas aktualizacji leku: " . $e->getMessage();
            }
        }
    }

    // Odśwież dane leku po ewentualnej edycji przed wyświetleniem formularza
     if (empty($success)) { // Tylko jeśli nie było sukcesu (bo wtedy już zaktualizowaliśmy $medicine lokalnie)
        $stmt = $conn->prepare("
            SELECT m.*, mb.user_id as box_owner_id, dd.name as drug_name, 
                   (m.package_count * m.units_per_package + m.units_remaining) as total_units
            FROM medicines m 
            JOIN medicine_boxes mb ON m.box_id = mb.id 
            JOIN drug_dictionary dd ON m.drug_id = dd.id
            WHERE m.id = ?
        ");
        $stmt->bind_param("i", $medicine_id);
        $stmt->execute();
        $medicine = $stmt->get_result()->fetch_assoc();
     }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $error = "Wystąpił błąd. Spróbuj ponownie później: " . $e->getMessage();
     // W przypadku błędu przekieruj lub wyświetl błąd na tej stronie
     // header("Location: index.php");
     // exit();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytuj lek - System Zarządzania Apteczkami</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://bootswatch.com/5/lumen/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">System Zarządzania Apteczkami</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                         <span class="nav-link">Witaj, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                     </li>
                    <li class="nav-item">
                        <a class="nav-link" href="view_box.php?id=<?php echo $medicine['box_id']; ?>">Powrót do apteczki</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Wyloguj się</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Edytuj lek</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="view_box.php?id=<?php echo $medicine['box_id']; ?>" class="btn btn-primary">Powrót do apteczki</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Nazwa leku</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($medicine['drug_name']); ?>" disabled>
                                </div>
                                
                                <div class="mb-3">
                                     <label class="form-label">Ilość w opakowaniu</label>
                                     <input type="text" class="form-control" value="<?php echo $medicine['units_per_package']; ?>" disabled>
                                     <small class="form-text text-muted">Ilość jednostek w opakowaniu nie może być zmieniona po dodaniu leku.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Całkowita ilość (szt.)</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" 
                                           value="<?php echo ($medicine['package_count'] * $medicine['units_per_package'] + $medicine['units_remaining']); ?>" min="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="price" class="form-label">Cena</label>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           value="<?php echo $medicine['price']; ?>" step="0.01" min="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="expiration_date" class="form-label">Data ważności</label>
                                    <input type="date" class="form-control" id="expiration_date" name="expiration_date" 
                                           value="<?php echo $medicine['expiration_date']; ?>" required>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer mt-5 py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">Systemy Informatyczne w Medycynie. Wiktor Raczek</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 