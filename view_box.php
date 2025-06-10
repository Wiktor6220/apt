<?php
require_once 'config.php';
requireLogin();

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$box_id = (int)$_GET['id'];
$conn = getDbConnection();

// Sprawdź czy użytkownik ma dostęp do apteczki
$stmt = $conn->prepare("
    SELECT mb.*, bu.access_level
    FROM medicine_boxes mb 
    LEFT JOIN box_users bu ON mb.id = bu.box_id AND bu.user_id = ?
    WHERE mb.id = ? AND (mb.user_id = ? OR bu.user_id = ?)
");
$stmt->bind_param("iiii", $_SESSION['user_id'], $box_id, $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$box = $stmt->get_result()->fetch_assoc();

if (!$box) {
    header("Location: index.php");
    exit();
}

// Określ poziom dostępu użytkownika
$is_owner = ($box['user_id'] === $_SESSION['user_id']);
$can_edit = $is_owner || ($box['access_level'] === 'edit');
$can_dispose = $is_owner;

// Pobierz leki z apteczki
$stmt = $conn->prepare("
    SELECT m.*, 
           dd.name as drug_name,
           dd.description as drug_description
    FROM medicines m
    JOIN drug_dictionary dd ON m.drug_id = dd.id
    WHERE m.box_id = ?
    ORDER BY m.expiration_date ASC
");
$stmt->bind_param("i", $box_id);
$stmt->execute();
$medicines = $stmt->get_result();

// Pobierz historię zażycia leków
$stmt = $conn->prepare("
    SELECT i.*, dd.name as medicine_name, u.username, i.amount as quantity
    FROM intakes i
    JOIN medicines m ON i.medicine_id = m.id
    JOIN drug_dictionary dd ON m.drug_id = dd.id
    JOIN users u ON i.user_id = u.id
    WHERE m.box_id = ?
    ORDER BY i.dosage_time DESC
");
$stmt->bind_param("i", $box_id);
$stmt->execute();
$intakes = $stmt->get_result();

// Pobierz listę dostępnych leków do dodania
$stmt = $conn->prepare("SELECT * FROM drug_dictionary ORDER BY name");
$stmt->execute();
$available_drugs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pobierz listę użytkowników z dostępem do apteczki
$stmt = $conn->prepare("
    SELECT u.id, u.username, u.email, 
           CASE WHEN mb.user_id = u.id THEN 'owner' ELSE 'shared' END as access_type
    FROM users u
    LEFT JOIN medicine_boxes mb ON mb.user_id = u.id AND mb.id = ?
    LEFT JOIN box_users bu ON bu.user_id = u.id AND bu.box_id = ?
    WHERE mb.id IS NOT NULL OR bu.box_id IS NOT NULL
");
$stmt->bind_param("ii", $box_id, $box_id);
$stmt->execute();
$box_users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($box['name']); ?> - System Zarządzania Apteczkami</title>
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
                        <a class="nav-link" href="index.php">Powrót do listy apteczek</a>
                    </li>
                    <li class="nav-item">
                        <span class="nav-link">Witaj, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Wyloguj się</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2><?php echo htmlspecialchars($box['name']); ?></h2>
                <div class="btn-group">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMedicineModal">
                        Dodaj nowy lek
                    </button>
                    <?php if ($box['user_id'] === $_SESSION['user_id']): ?>
                    <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#shareBoxModal">
                        Udostępnij apteczkę
                    </button>
                    <a href="edit_box.php?id=<?php echo $box_id; ?>" class="btn btn-warning">Edytuj apteczkę</a>
                    <a href="delete_box.php?id=<?php echo $box_id; ?>" class="btn btn-danger" onclick="return confirm('Czy na pewno chcesz usunąć tę apteczkę?')">Usuń apteczkę</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (empty($medicines)): ?>
            <div class="alert alert-info">
                Ta apteczka jest pusta. Kliknij "Dodaj nowy lek", aby dodać pierwszy lek.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nazwa leku</th>
                            <th>Opis leku</th>
                            <th>Ilość</th>
                            <th>Cena</th>
                            <th>Data ważności</th>
                            <th>Status</th>
                            <th>Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($medicines as $medicine): ?>
                            <?php
                            $status = getExpirationStatus($medicine['expiration_date']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($medicine['drug_name']); ?></td>
                                <td><?php echo htmlspecialchars($medicine['drug_description']); ?></td>
                                <td>
                                    <?php echo $medicine['package_count']; ?> opakowań<br>
                                    <?php echo $medicine['units_per_package']; ?> sztuk w opakowaniu<br>
                                    <?php if ($medicine['units_remaining'] > 0): ?>
                                        <?php echo $medicine['units_remaining']; ?> sztuk w otwartym opakowaniu<br>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format($medicine['price'], 2); ?> zł</td>
                                <td><?php echo date('d.m.Y', strtotime($medicine['expiration_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $status['class']; ?>">
                                        <?php echo $status['message']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($can_edit): ?>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#intakeModal<?php echo $medicine['id']; ?>">
                                            Zażyj
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($can_dispose): ?>
                                        <button type="button" class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#disposeModal<?php echo $medicine['id']; ?>">
                                            Utylizuj
                                        </button>
                                        <?php endif; ?>
                                        <?php if ($is_owner): ?>
                                        <a href="edit_medicine.php?id=<?php echo $medicine['id']; ?>" class="btn btn-secondary btn-sm">Edytuj</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Historia zażycia leków -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0">Historia zażycia leków</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Lek</th>
                                <th>Użytkownik</th>
                                <th>Data i godzina</th>
                                <th>Ilość</th>
                                <th>Uwagi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($intake = $intakes->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($intake['medicine_name']); ?></td>
                                <td><?php echo htmlspecialchars($intake['username']); ?></td>
                                <td><?php echo date('d.m.Y H:i', strtotime($intake['dosage_time'])); ?></td>
                                <td><?php echo $intake['quantity']; ?> szt.</td>
                                <td><?php echo htmlspecialchars($intake['notes']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Modal udostępniania apteczki -->
        <div class="modal fade" id="shareBoxModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Udostępnij apteczkę</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="share_box.php" method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="box_id" value="<?php echo $box_id; ?>">
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Adres e-mail użytkownika</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="access_level" class="form-label">Poziom dostępu</label>
                                <select class="form-select" id="access_level" name="access_level" required>
                                    <option value="view">Tylko wgląd</option>
                                    <option value="edit">Możliwość edycji i zażywania leków</option>
                                </select>
                                <div class="form-text">
                                    <ul>
                                        <li><strong>Tylko wgląd:</strong> Użytkownik może tylko przeglądać zawartość apteczki</li>
                                        <li><strong>Możliwość edycji:</strong> Użytkownik może zażywać leki, ale nie może ich utylizować</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                            <button type="submit" class="btn btn-primary">Udostępnij</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Lista użytkowników z dostępem -->
        <div class="mt-4">
            <h3>Użytkownicy z dostępem</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nazwa użytkownika</th>
                            <th>Email</th>
                            <th>Typ dostępu</th>
                            <?php if ($box['user_id'] === $_SESSION['user_id']): ?>
                            <th>Akcje</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($box_users as $user): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php if ($user['access_type'] === 'owner'): ?>
                                    <span class="badge bg-primary">Właściciel</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Udostępniony</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($box['user_id'] === $_SESSION['user_id'] && $user['access_type'] === 'shared'): ?>
                            <td>
                                <a href="remove_box_access.php?box_id=<?php echo $box_id; ?>&user_id=<?php echo $user['id']; ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Czy na pewno chcesz usunąć dostęp dla tego użytkownika?')">
                                    Usuń dostęp
                                </a>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal dodawania leku -->
    <div class="modal fade" id="addMedicineModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dodaj nowy lek</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_medicine.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="box_id" value="<?php echo $box_id; ?>">
                        
                        <div class="mb-3">
                            <label for="drug_id" class="form-label">Nazwa leku</label>
                            <select class="form-select" id="drug_id" name="drug_id" required>
                                <option value="">Wybierz lek...</option>
                                <?php foreach ($available_drugs as $drug): ?>
                                    <option value="<?php echo $drug['id']; ?>">
                                        <?php echo htmlspecialchars($drug['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="package_count" class="form-label">Liczba opakowań</label>
                            <input type="number" class="form-control" id="package_count" name="package_count" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="units_per_package" class="form-label">Liczba jednostek w opakowaniu</label>
                            <input type="number" class="form-control" id="units_per_package" name="units_per_package" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="price" class="form-label">Cena</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="expiration_date" class="form-label">Data ważności</label>
                            <input type="date" class="form-control" id="expiration_date" name="expiration_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary">Dodaj lek</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal zażywania leku -->
    <?php foreach ($medicines as $medicine): ?>
    <div class="modal fade" id="intakeModal<?php echo $medicine['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Zażyj lek</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="record_intake.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                        <input type="hidden" name="box_id" value="<?php echo $box_id; ?>">
                        <p>Lek: <?php echo htmlspecialchars($medicine['drug_name']); ?></p>
                        <p>Dostępna ilość: <?php echo ($medicine['package_count'] * $medicine['units_per_package'] + $medicine['units_remaining']); ?> szt.</p>
                        
                        <div class="mb-3">
                            <label for="quantity" class="form-label">Ilość (szt.)</label>
                            <input type="number" class="form-control" id="quantity" name="quantity" min="1" max="<?php echo ($medicine['package_count'] * $medicine['units_per_package'] + $medicine['units_remaining']); ?>" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="intake_date" class="form-label">Data zażycia</label>
                            <input type="datetime-local" class="form-control" id="intake_date" name="intake_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notatki</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary">Zapisz zażycie</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal utylizacji leku -->
    <div class="modal fade" id="disposeModal<?php echo $medicine['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Utylizuj lek</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dispose_medicine.php" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="medicine_id" value="<?php echo $medicine['id']; ?>">
                        <input type="hidden" name="box_id" value="<?php echo $box_id; ?>">
                        <p>Lek: <?php echo htmlspecialchars($medicine['drug_name']); ?></p>
                        <p>Całkowita ilość do utylizacji: <?php echo ($medicine['package_count'] * $medicine['units_per_package'] + $medicine['units_remaining']); ?> szt.</p>
                        
                        <div class="mb-3">
                            <label for="disposal_date" class="form-label">Data utylizacji</label>
                            <input type="date" class="form-control" id="disposal_date" name="disposal_date" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reason" class="form-label">Powód utylizacji</label>
                            <select class="form-select" id="reason" name="reason" required>
                                <option value="expired">Przeterminowany</option>
                                <option value="damaged">Uszkodzony</option>
                                <option value="manual">Inny powód</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-danger">Utylizuj cały lek</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <footer class="footer mt-5 py-3 bg-light">
        <div class="container text-center">
            <span class="text-muted">Systemy Informatyczne w Medycynie. Wiktor Raczek</span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Funkcja do sprawdzania statusu ważności leku
function getExpirationStatus($expiration_date) {
    $today = new DateTime();
    $exp_date = new DateTime($expiration_date);
    $diff = $today->diff($exp_date);
    
    if ($exp_date < $today) {
        return [
            'status' => 'expired',
            'message' => 'Lek przeterminowany',
            'class' => 'danger'
        ];
    } elseif ($diff->days <= 7) {
        return [
            'status' => 'expiring',
            'message' => 'Lek kończy ważność za ' . $diff->days . ' dni',
            'class' => 'warning'
        ];
    } elseif ($diff->days <= 30) {
        return [
            'status' => 'expiring_soon',
            'message' => 'Lek kończy ważność za ' . $diff->days . ' dni',
            'class' => 'info'
        ];
    } else {
        return [
            'status' => 'valid',
            'message' => 'Lek ważny',
            'class' => 'success'
        ];
    }
} 