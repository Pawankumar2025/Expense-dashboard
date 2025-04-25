<?php
require_once '../config/db.php'; 
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

        $_SESSION['user'] = $email;
        $_SESSION['user_id'] = $user['id'];
        header("Location: dashboard.php");
        exit();
    
   
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Login</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    <form method="post" class="w-50">
        <div class="mb-3">
            <input type="email" name="email" class="form-control" required placeholder="Email">
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" required placeholder="Password">
        </div>
        <button type="submit" class="btn btn-success">Login</button>
    </form>
</body>
</html>
