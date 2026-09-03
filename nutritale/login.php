<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/icons.php';

if (current_user()) {
    redirect('index.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = db()->prepare('SELECT id, name, password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = 'Incorrect email or password.';
        } else {
            $_SESSION['user_id'] = (int)$user['id'];
            redirect('index.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Log in · <?= APP_NAME ?></title>
<link rel="icon" type="image/svg+xml" href="assets/img/favicon.svg">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-shell">
    <div class="auth-card">
        <div class="center-text mb-16"><?= nutritale_logo_svg(56) ?></div>
        <h1 class="center-text">Welcome back</h1>
        <div class="card">
            <?php foreach ($errors as $error): ?>
                <div class="alert alert-error"><?= h($error) ?></div>
            <?php endforeach; ?>
            <form method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                <label class="field">
                    <span>Email</span>
                    <input type="email" name="email" value="<?= h($email) ?>" required>
                </label>
                <label class="field">
                    <span>Password</span>
                    <input type="password" name="password" required>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Log in</button>
            </form>
            <a class="btn btn-text btn-block" href="forgot_password.php">Forgot password?</a>
            <a class="btn btn-text btn-block" href="register.php">Create an account</a>
        </div>
    </div>
</div>
</body>
</html>
