<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['temp_id'])) {
    header('Location: ../index.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma   = $_POST['confirma_senha'] ?? '';

    if (strlen($nova_senha) < 4) {
        $erro = 'A senha precisa ter pelo menos 4 caracteres.';
    } elseif ($nova_senha !== $confirma) {
        $erro = 'As senhas não coincidem. Tente novamente.';
    } else {
        try {
            $pdo  = getConnection();
            $hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE usuarios SET senha = :s, mudar_senha = FALSE WHERE id = :id");
            $stmt->execute([':s' => $hash, ':id' => $_SESSION['temp_id']]);

            $_SESSION['usuario_id']   = $_SESSION['temp_id'];
            $_SESSION['usuario_nome'] = ucfirst($_SESSION['temp_nome']);

            // Carrega permissões do usuário na sessão
            carregarPermissoes($pdo, $_SESSION['usuario_id']);

            registrarLog($pdo, 'login', 'usuarios', $_SESSION['usuario_id'], "Primeiro acesso: senha definida.");

            unset($_SESSION['temp_id'], $_SESSION['temp_nome']);
            header('Location: home.php');
            exit;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar a nova senha.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Primeiro Acesso</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="login-body">
<div class="login-card">
    <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#3b5998" width="55" height="55">
            <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
        </svg>
        <h1>Primeiro<br>Acesso</h1>
    </div>

    <p style="color:#666; margin-bottom:25px; font-size:14px; text-align:center;">
        Bem-vindo! Crie uma senha particular para continuar.
    </p>

    <?php if ($erro): ?>
        <div class="alert-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" action="primeiro_acesso.php" autocomplete="off">
        <div class="input-group">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#777">
                    <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
                </svg>
            </div>
            <input type="password" name="nova_senha" placeholder="Nova senha (mín. 4 caracteres)" required autofocus>
        </div>
        <div class="input-group">
            <div class="icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#777">
                    <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
                </svg>
            </div>
            <input type="password" name="confirma_senha" placeholder="Confirme a nova senha" required>
        </div>
        <button type="submit" class="btn-entrar">Salvar e Entrar</button>
    </form>
</div>
</body>
</html>
