<?php
require_once 'config.php';
requireLogin();

$conn = getDbConnection();

// Pobierz apteczki użytkownika (własne i udostępnione)
$stmt = $conn->prepare("
    SELECT mb.*, 
           u.username as owner_name,
           CASE 
               WHEN mb.user_id = ? THEN 'owner'
               ELSE bu.access_level
           END as access_type
    FROM medicine_boxes mb
    LEFT JOIN users u ON mb.user_id = u.id
    LEFT JOIN box_users bu ON mb.id = bu.box_id AND bu.user_id = ?
    WHERE mb.user_id = ? OR bu.user_id = ?
    ORDER BY mb.name ASC
");
$stmt->bind_param("iiii", $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']);
$stmt->execute();
$boxes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pobierz leki z terminem ważności < 7 dni
$stmt = $conn->prepare("
    SELECT m.*, dd.name as drug_name, mb.name as box_name 
    FROM medicines m 
    JOIN drug_dictionary dd ON m.drug_id = dd.id 
    JOIN medicine_boxes mb ON m.box_id = mb.id 
    WHERE mb.user_id = ? AND m.expiration_date < DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)
    ORDER BY m.expiration_date ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$expired_medicines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Pobierz przeterminowane leki
$stmt = $conn->prepare("
    SELECT m.*, dd.name as drug_name, mb.name as box_name 
    FROM medicines m 
    JOIN drug_dictionary dd ON m.drug_id = dd.id 
    JOIN medicine_boxes mb ON m.box_id = mb.id 
    WHERE mb.user_id = ? AND m.expiration_date < CURRENT_DATE
    ORDER BY m.expiration_date ASC
");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$outdated_medicines = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Zarządzania Apteczkami</title>
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
                        <a class="nav-link" href="logout.php">Wyloguj się</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($outdated_medicines)): ?>
            <div class="alert alert-danger">
                <h4 class="alert-heading">Uwaga!</h4>
                <p>Masz przeterminowane leki:</p>
                <ul>
                    <?php foreach ($outdated_medicines as $medicine): ?>
                        <li>
                            <?php echo htmlspecialchars($medicine['drug_name']); ?> 
                            w apteczce "<?php echo htmlspecialchars($medicine['box_name']); ?>" 
                            - ważny do: <?php echo date('d.m.Y', strtotime($medicine['expiration_date'])); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($expired_medicines)): ?>
            <div class="alert alert-warning">
                <h4 class="alert-heading">Uwaga!</h4>
                <p>Masz leki z kończącym się terminem ważności:</p>
                <ul>
                    <?php foreach ($expired_medicines as $medicine): ?>
                        <li>
                            <?php echo htmlspecialchars($medicine['drug_name']); ?> 
                            w apteczce "<?php echo htmlspecialchars($medicine['box_name']); ?>" 
                            - ważny do: <?php echo date('d.m.Y', strtotime($medicine['expiration_date'])); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row mb-4">
            <div class="col">
                <h2>Twoje apteczki</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBoxModal">
                    Dodaj nową apteczkę
                </button>
            </div>
        </div>

        <?php if (empty($boxes)): ?>
            <div class="alert alert-info">
                Nie masz jeszcze żadnych apteczek. Kliknij "Dodaj nową apteczkę", aby utworzyć pierwszą.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($boxes as $box): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($box['name']); ?></h5>
                                <p class="card-text">
                                    Właściciel: <?php echo htmlspecialchars($box['owner_name']); ?><br>
                                    Typ dostępu: 
                                    <?php if ($box['access_type'] === 'owner'): ?>
                                        <span class="badge bg-primary">Właściciel</span>
                                    <?php elseif ($box['access_type'] === 'edit'): ?>
                                        <span class="badge bg-warning">Edycja</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Wgląd</span>
                                    <?php endif; ?>
                                </p>
                                <a href="view_box.php?id=<?php echo $box['id']; ?>" class="btn btn-primary">Zobacz zawartość</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal dodawania apteczki -->
    <div class="modal fade" id="addBoxModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dodaj nową apteczkę</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="add_box.php" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Nazwa apteczki</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                        <button type="submit" class="btn btn-primary">Dodaj apteczkę</button>
                    </div>
                </form>
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