<?php
require_once '../includes/db.php';

$mensagem = '';
$erro = '';
$passo = 1; // 1: Pede usuário | 2: Pede nova senha | 3: Sucesso
$usuario_confirmado = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // PASSO 1: Verificar se o usuário existe
    if (isset($_POST['verificar_usuario'])) {
        $usuario = trim($_POST['usuario'] ?? '');
        
        if ($usuario === '') {
            $erro = 'Por favor, informe seu usuário.';
        } else {
            try {
                $pdo = getConnection();
                $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE username = :u LIMIT 1");
                $stmt->execute([':u' => $usuario]);
                $user = $stmt->fetch();

                if ($user) {
                    $passo = 2;
                    $usuario_confirmado = $user['username'];
                } else {
                    $erro = 'Usuário não encontrado no sistema.';
                }
            } catch (Exception $e) {
                $erro = 'Erro ao conectar com o banco de dados.';
            }
        }
    } 
    
    // PASSO 2: Atualizar a senha no banco
    elseif (isset($_POST['redefinir_senha'])) {
        $usuario_confirmado = $_POST['usuario_confirmado'];
        $nova_senha = $_POST['nova_senha'] ?? '';

        if (strlen($nova_senha) < 4) {
            $erro = 'A nova senha deve ter pelo menos 4 caracteres.';
            $passo = 2; // Volta pro passo 2 se der erro
        } else {
            try {
                $pdo = getConnection();
                // Criptografa a nova senha antes de salvar
                $hash_senha = password_hash($nova_senha, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("UPDATE usuarios SET senha = :s WHERE username = :u");
                $stmt->execute([':s' => $hash_senha, ':u' => $usuario_confirmado]);

                $mensagem = 'Senha alterada com sucesso! Você já pode fazer login.';
                $passo = 3; // Vai para a tela de sucesso
            } catch (Exception $e) {
                $erro = 'Erro ao atualizar a senha.';
                $passo = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="login-body">

<div class="login-card">
    <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#3b5998" width="60" height="60">
            <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
        </svg>
        <h1>Recuperar<br>Senha</h1>
    </div>

    <?php if ($erro): ?>
        <div class="alert-erro"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <?php if ($mensagem): ?>
        <div class="alert-erro" style="background: #d4edda; color: #155724; border-left-color: #28a745;">
            <?= htmlspecialchars($mensagem) ?>
        </div>
    <?php endif; ?>

    <?php if ($passo === 1): ?>
        <p style="color: #666; margin-bottom: 25px; font-size: 14px; text-align: left;">
            Informe seu nome de usuário para buscar sua conta no sistema.
        </p>

        <form method="POST" action="esqueci_senha.php" autocomplete="off">
            <div class="input-group">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#777">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <input type="text" name="usuario" placeholder="Seu usuário (ex: admin)" required autofocus>
            </div>
            <button type="submit" name="verificar_usuario" class="btn-entrar">Buscar Conta</button>
        </form>

    <?php elseif ($passo === 2): ?>
        <p style="color: #666; margin-bottom: 25px; font-size: 14px; text-align: left;">
            Conta encontrada! Crie uma nova senha para o usuário <strong><?= htmlspecialchars($usuario_confirmado) ?></strong>.
        </p>

        <form method="POST" action="esqueci_senha.php" autocomplete="off">
            <input type="hidden" name="usuario_confirmado" value="<?= htmlspecialchars($usuario_confirmado) ?>">
            
            <div class="input-group">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="#777">
                        <path d="M18 8h-1V6c0-2.8-2.2-5-5-5S7 3.2 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.7 1.4-3.1 3.1-3.1 1.7 0 3.1 1.4 3.1 3.1v2z"/>
                    </svg>
                </div>
                <input type="password" name="nova_senha" placeholder="Sua nova senha" required autofocus>
            </div>
            <button type="submit" name="redefinir_senha" class="btn-entrar">Salvar Nova Senha</button>
        </form>

    <?php elseif ($passo === 3): ?>
        <a href="../index.php" class="btn-entrar" style="display: block; text-decoration: none;">Ir para o Login</a>
    <?php endif; ?>

    <hr class="login-divider">
    <?php if ($passo !== 3): ?>
        <a href="../index.php" class="link-senha">← Voltar para o Login</a>
    <?php endif; ?>
</div>

</body>
</html>