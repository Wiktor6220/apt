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
    $stmt = $conn->prepare("SELECT * FROM medicine_boxes WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $box_id, $_SESSION['user_id']);
    $stmt->execute();
    $box = $stmt->get_result()->fetch_assoc();

    if (!$box) {
        header("Location: index.php");
        exit();
    }

    $error = '';
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $name = trim($_POST['name']);
        
        if (empty($name)) {
            $error = "Nazwa apteczki jest wymagana.";
        } elseif (strlen($name) < 3) {
            $error = "Nazwa apteczki musi mieć co najmniej 3 znaki.";
        } else {
            $stmt = $conn->prepare("UPDATE medicine_boxes SET name = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $name, $box_id, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $success = "Apteczka została zaktualizowana pomyślnie.";
                $box['name'] = $name;
            } else {
                $error = "Wystąpił błąd podczas aktualizacji apteczki.";
            }
        }
    }

    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    $error = "Wystąpił błąd. Spróbuj ponownie później.";
    header("Location: index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytuj apteczkę - System Zarządzania Apteczkami</title>
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
                        <h3 class="text-center">Edytuj apteczkę</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <?php echo $success; ?>
                                <div class="mt-3">
                                    <a href="index.php" class="btn btn-primary">Powrót do listy apteczek</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Nazwa apteczki</label>
                                    <input type="text" class="form-control" id="name" name="name" 
                                           value="<?php echo htmlspecialchars($box['name']); ?>" required>
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