<?php
// login.php
session_start();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Exemplo simples de autenticação (substitua por lógica real)
    if ($username === 'admin' && $password === 'admin') {
        $_SESSION['user'] = $username;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Usuário ou senha inválidos.';
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - GROM</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f7fa;
            color: #2c3e50;
            font-family: "Segoe UI", Tahoma, sans-serif;
            padding: 24px;
        }
        .login-card {
            width: min(400px, 100%);
            background: #fff;
            border: 1px solid #d8dee7;
            border-radius: 20px;
            padding: 32px 28px 24px 28px;
            box-shadow: 0 16px 36px rgba(44, 62, 80, .10);
        }
        h2 {
            margin: 0 0 18px;
            font-size: 26px;
            text-align: center;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        label {
            font-weight: 500;
        }
        input[type="text"], input[type="password"] {
            padding: 10px;
            border: 1px solid #d8dee7;
            border-radius: 8px;
            font-size: 16px;
        }
        button {
            padding: 10px;
            background: #2c3e50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #1a232e;
        }
        .error {
            color: #e74c3c;
            text-align: center;
            margin-bottom: 8px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>Login no GROM</h2>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <label for="username">Usuário</label>
            <input type="text" id="username" name="username" required autofocus>
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
