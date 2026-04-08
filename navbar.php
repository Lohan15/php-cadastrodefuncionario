<nav class="navbar">
    <a href="home.php" class="navbar-brand">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="white" width="24" height="24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
        </svg>
        Cadastro de Funcionários
    </a>
    <div class="navbar-nav">
        <?php if (temPermissao('funcionarios_ver')): ?>
            <a href="home.php" class="<?= basename($_SERVER['PHP_SELF']) === 'home.php' ? 'nav-active' : '' ?>">Início</a>
            <a href="listagem.php" class="<?= basename($_SERVER['PHP_SELF']) === 'listagem.php' ? 'nav-active' : '' ?>">Funcionários</a>
        <?php endif; ?>

        <?php if (temPermissao('relatorios_ver')): ?>
            <a href="relatorios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'relatorios.php' ? 'nav-active' : '' ?>">Relatórios</a>
        <?php endif; ?>

        <?php if (temPermissao('usuarios_ver')): ?>
            <a href="usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'usuarios.php' ? 'nav-active' : '' ?>">Usuários</a>
        <?php endif; ?>

        <div class="navbar-user">
            <span>Olá, <strong><?= htmlspecialchars($_SESSION['usuario_nome'] ?? 'Admin') ?></strong></span>
            <a href="logout.php" class="btn-logout">Sair</a>
        </div>
    </div>
</nav>
