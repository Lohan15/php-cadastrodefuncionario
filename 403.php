<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acesso Negado</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .forbidden-wrap { display: flex; align-items: center; justify-content: center; min-height: calc(100vh - 56px); }
        .forbidden-box { text-align: center; padding: 60px 40px; background: #fff; border-radius: 8px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); max-width: 420px; }
        .forbidden-box .code { font-size: 80px; font-weight: 900; color: #dc3545; line-height: 1; margin-bottom: 10px; }
        .forbidden-box h2 { color: #3b5998; font-size: 20px; margin-bottom: 12px; }
        .forbidden-box p { color: #666; font-size: 14px; margin-bottom: 30px; }
    </style>
</head>
<body>
<?php if (isset($_SESSION['usuario_id'])): ?>
<?php include __DIR__ . '/navbar.php'; ?>
<?php endif; ?>
<div class="forbidden-wrap">
    <div class="forbidden-box">
        <div class="code">403</div>
        <h2>Acesso Negado</h2>
        <p>Você não tem permissão para acessar este recurso.<br>Fale com o administrador do sistema.</p>
        <a href="home.php" class="btn btn-primary" style="text-decoration:none; padding: 10px 30px; display:inline-block;">← Voltar ao Início</a>
    </div>
</div>
</body>
</html>
