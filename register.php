<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($username) || empty($email) || empty($password)) {
        $error = "Wszystkie pola są wymagane.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Nieprawidłowy format adresu email.";
    } elseif ($password !== $confirm_password) {
        $error = "Hasła nie są identyczne.";
    } elseif (strlen($password) < 6) {
        $error = "Hasło musi mieć co najmniej 6 znaków.";
    } else {
        try {
            $conn = getDbConnection();
            
            // Sprawdź czy użytkownik już istnieje
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "Użytkownik o podanej nazwie lub emailu już istnieje.";
            } else {
                // Hashowanie hasła i dodanie użytkownika
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                $stmt->bind_param("sss", $username, $email, $hashed_password);
                
                if ($stmt->execute()) {
                    $success = "Rejestracja zakończona sukcesem. Możesz się teraz zalogować.";
                } else {
                    $error = "Wystąpił błąd podczas rejestracji.";
                }
            }
            
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            $error = "Wystąpił błąd podczas rejestracji. Spróbuj ponownie później.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rejestracja - System Zarządzania Apteczkami</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://bootswatch.com/5/lumen/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Rejestracja</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="username" class="form-label">Nazwa użytkownika</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Hasło</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Potwierdź hasło</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">Zarejestruj się</button>
                            </div>
                        </form>
                        <div class="text-center mt-3">
                            <p>Masz już konto? <a href="login.php">Zaloguj się</a></p>
                        </div>
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
</body>
</html> 