<?php
require_once '../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
    try {
        $stmt->execute([$email, $password]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        header('Location: dashboard.php');
    } catch (PDOException $e) {
        echo "Error: Email already exists.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-5">
    <h2>Register</h2>
    <form method="post" class="w-50">
        <div class="mb-3">
            <input type="email" name="email" class="form-control" required placeholder="Email">
        </div>
        <div class="mb-3">
            <input type="password" name="password" class="form-control" required placeholder="Password">
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
    </form>
</body>
</html>
