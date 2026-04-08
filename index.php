<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: pages/home.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['usuario'] ?? '');
    $senha    = $_POST['senha'] ?? '';

    if ($username === '' || $senha === '') {
        $erro = 'Preencha usuário e senha.';
    } else {
        try {
            $pdo  = getConnection();
            $stmt = $pdo->prepare("SELECT id, username, senha, mudar_senha, ativo FROM usuarios WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if (!$user || !$user['ativo']) {
                $erro = 'Usuário ou senha inválidos.';
            } elseif ($user && password_verify($senha, $user['senha'])) {

                // PRIMEIRO ACESSO — troca de senha obrigatória
                if ($user['mudar_senha'] === true) {
                    $_SESSION['temp_id']   = $user['id'];
                    $_SESSION['temp_nome'] = $user['username'];
                    header('Location: pages/primeiro_acesso.php');
                    exit;
                }

                // LOGIN NORMAL
                $_SESSION['usuario_id']   = $user['id'];
                $_SESSION['usuario_nome'] = ucfirst($user['username']);

                // Carrega permissões na sessão
                carregarPermissoes($pdo, $user['id']);

                // Registra log de acesso
                registrarLog($pdo, 'login', 'usuarios', $user['id'], "Usuário '{$user['username']}' fez login.");

                header('Location: pages/home.php');
                exit;
            } else {
                $erro = 'Usuário ou senha inválidos.';
            }
        } catch (Exception $e) {
            $erro = 'Erro ao conectar com o banco de dados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Funcionários - Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">

<div class="login-card">
    <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#3b5998" width="60" height="60">
            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
        </svg>
        <h1>Cadastro de<br>Funcionários</h1>
    </div>

    <?php if ($erro): ?>
        <div class="alert-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php" autocomplete="off">
        <div class="input-group">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#777">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
            </div>
            <input type="text" name="usuario" placeholder="Usuário" required>
        </div>

        <div class="input-group">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#777">
                    <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
                </svg>
            </div>
            <input type="password" name="senha" placeholder="Senha" required>
        </div>

        <button type="submit" class="btn-entrar">Entrar</button>
    </form>

    <hr class="login-divider">
    <a href="pages/esqueci_senha.php" class="link-senha">Esqueci minha senha</a>
</div>

</body>
</html>
