<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();
requirePermissao('usuarios_ver');

$pdo = getConnection();
$modulos = $pdo->query("SELECT chave, nome, descricao FROM modulos ORDER BY id")->fetchAll();

$sucesso = '';
$erro    = '';
$editando_user = null;
$perms_user    = [];

// ─── Ações ────────────────────────────────────────────────────────────────────

// Ativar/Desativar usuário
if (isset($_GET['toggle']) && temPermissao('usuarios_gerenciar')) {
    $id   = (int)$_GET['toggle'];
    $stmt = $pdo->prepare("SELECT ativo, username FROM usuarios WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $u    = $stmt->fetch();
    $novo = $u['ativo'] ? false : true;
    $pdo->prepare("UPDATE usuarios SET ativo=:a WHERE id=:id")->execute([':a'=>$novo,':id'=>$id]);
    $acao = $novo ? 'ativado' : 'desativado';
    registrarLog($pdo, 'editar', 'usuarios', $id, "Usuário '{$u['username']}' $acao.");
    header('Location: usuarios.php?msg='.$acao); exit;
}

// Deletar usuário
if (isset($_GET['excluir']) && temPermissao('usuarios_gerenciar')) {
    $id = (int)$_GET['excluir'];
    if ($id === (int)$_SESSION['usuario_id']) {
        header('Location: usuarios.php?msg=autoexcluir'); exit;
    }
    $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $uname = $stmt->fetchColumn();
    $pdo->prepare("DELETE FROM usuarios WHERE id=:id")->execute([':id'=>$id]);
    registrarLog($pdo, 'excluir', 'usuarios', $id, "Usuário '$uname' excluído.");
    header('Location: usuarios.php?msg=excluido'); exit;
}

// Carregar usuário para edição de permissões
if (isset($_GET['permissoes']) && temPermissao('usuarios_gerenciar')) {
    $id = (int)$_GET['permissoes'];
    $stmt = $pdo->prepare("SELECT id, username, ativo FROM usuarios WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $editando_user = $stmt->fetch();
    $stmtP = $pdo->prepare("SELECT modulo_chave FROM permissoes WHERE usuario_id=:id");
    $stmtP->execute([':id'=>$id]);
    $perms_user = $stmtP->fetchAll(PDO::FETCH_COLUMN);
}

// Salvar permissões
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_permissoes']) && temPermissao('usuarios_gerenciar')) {
    $id = (int)$_POST['usuario_id'];
    $novas = $_POST['perm'] ?? [];

    $pdo->prepare("DELETE FROM permissoes WHERE usuario_id=:id")->execute([':id'=>$id]);
    if (!empty($novas)) {
        $ins = $pdo->prepare("INSERT INTO permissoes (usuario_id, modulo_chave) VALUES (:id, :chave)");
        foreach ($novas as $chave) {
            $ins->execute([':id'=>$id, ':chave'=>$chave]);
        }
    }

    $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $uname = $stmt->fetchColumn();
    registrarLog($pdo, 'permissoes', 'usuarios', $id, "Permissões do usuário '$uname' atualizadas.");

    // Atualiza sessão se for o próprio usuário
    if ($id === (int)$_SESSION['usuario_id']) {
        carregarPermissoes($pdo, $id);
    }

    $sucesso = 'Permissões salvas com sucesso!';
    $editando_user = null;
}

// Criar novo usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['criar_usuario']) && temPermissao('usuarios_gerenciar')) {
    $username = trim($_POST['novo_username'] ?? '');
    $senha    = trim($_POST['nova_senha'] ?? '');
    if ($username === '' || $senha === '') {
        $erro = 'Preencha usuário e senha.';
    } else {
        try {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO usuarios (username, senha, mudar_senha) VALUES (:u, :p, TRUE)");
            $stmt->execute([':u'=>$username,':p'=>$hash]);
            $novo_id = (int)$pdo->lastInsertId('usuarios_id_seq');
            registrarLog($pdo, 'criar', 'usuarios', $novo_id, "Usuário '$username' criado.");
            $sucesso = "Usuário '$username' criado! Ele precisará trocar a senha no primeiro acesso.";
        } catch (Exception $e) {
            $erro = 'Erro: usuário já existe ou dados inválidos.';
        }
    }
}

// ─── Listagem ─────────────────────────────────────────────────────────────────
$busca    = trim($_GET['busca'] ?? '');
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 10;
$offset   = ($pagina - 1) * $porPagina;

$where  = ''; $params = [];
if ($busca !== '') {
    $where  = "WHERE username ILIKE :b";
    $params = [':b' => "%$busca%"];
}

$total = $pdo->prepare("SELECT COUNT(*) FROM usuarios $where");
$total->execute($params);
$totalRegistros = (int)$total->fetchColumn();
$totalPags      = (int)ceil($totalRegistros / $porPagina);

$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.ativo, u.mudar_senha, u.criado_em,
           COUNT(p.modulo_chave) AS total_perms
    FROM usuarios u
    LEFT JOIN permissoes p ON p.usuario_id = u.id
    $where
    GROUP BY u.id
    ORDER BY u.id ASC
    LIMIT :lim OFFSET :off
");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$usuarios = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';

function buildUrlU(array $extra): string {
    $params = array_merge($_GET, $extra);
    unset($params['msg'], $params['permissoes']);
    return 'usuarios.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciamento de Usuários</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main-content">
    <h2 class="page-title">Gerenciamento de Usuários</h2>

    <?php if ($sucesso): ?><div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div><?php endif; ?>
    <?php if ($erro):    ?><div class="alert alert-error"><?= htmlspecialchars($erro) ?></div><?php endif; ?>
    <?php if ($msg === 'excluido'):    ?><div class="alert alert-success">Usuário excluído.</div><?php endif; ?>
    <?php if ($msg === 'ativado'):     ?><div class="alert alert-success">Usuário ativado.</div><?php endif; ?>
    <?php if ($msg === 'desativado'):  ?><div class="alert alert-success">Usuário desativado.</div><?php endif; ?>
    <?php if ($msg === 'autoexcluir'): ?><div class="alert alert-error">Você não pode excluir sua própria conta.</div><?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr <?= $editando_user ? '380px' : '' ?>; gap: 20px; align-items: start;">

        <!-- Coluna principal -->
        <div>
            <!-- Card: Criar novo usuário -->
            <?php if (temPermissao('usuarios_gerenciar')): ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm-9-2V7H4v3H1v2h3v3h2v-3h3v-2H6zm9 4c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    Criar Novo Usuário
                </div>
                <div class="card-body">
                    <form method="POST" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                        <div class="form-group" style="flex:1; min-width: 160px;">
                            <label>Usuário</label>
                            <input type="text" name="novo_username" placeholder="Ex: maria.silva" required>
                        </div>
                        <div class="form-group" style="flex:1; min-width: 160px;">
                            <label>Senha Provisória</label>
                            <input type="text" name="nova_senha" placeholder="Será trocada no 1º acesso" required>
                        </div>
                        <div>
                            <button type="submit" name="criar_usuario" class="btn btn-primary" style="height:38px;">Criar Usuário</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Card: Listagem -->
            <div class="card">
                <div class="card-body">
                    <div class="list-toolbar">
                        <form method="GET" action="usuarios.php" class="search-form">
                            <div class="search-input-wrap">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 14z"/></svg>
                                <input type="text" name="busca" placeholder="Buscar usuário..." value="<?= htmlspecialchars($busca) ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">Buscar</button>
                            <?php if ($busca): ?><a href="usuarios.php" class="btn btn-outline">Limpar</a><?php endif; ?>
                        </form>
                    </div>

                    <p class="list-count">Total: <strong><?= $totalRegistros ?></strong> usuário(s)</p>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Usuário</th>
                                    <th>Status</th>
                                    <th>Permissões</th>
                                    <th>1º Acesso</th>
                                    <th>Criado em</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($usuarios)): ?>
                                    <tr><td colspan="7" class="empty-row">Nenhum usuário encontrado.</td></tr>
                                <?php else: foreach ($usuarios as $i => $u): ?>
                                    <tr <?= $editando_user && $editando_user['id'] === $u['id'] ? 'style="background:#f0f4ff;"' : '' ?>>
                                        <td class="col-id"><?= $offset + $i + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($u['username']) ?></strong>
                                            <?php if ($u['id'] === (int)$_SESSION['usuario_id']): ?>
                                                <span style="font-size:11px; color:#4267b2; margin-left:5px;">(você)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $u['ativo'] ? 'badge-ativo' : 'badge-inativo' ?>">
                                                <?= $u['ativo'] ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="perm-count"><?= $u['total_perms'] ?> módulo(s)</span>
                                        </td>
                                        <td>
                                            <?php if ($u['mudar_senha']): ?>
                                                <span style="color:#e67e22; font-size:12px;">⏳ Pendente</span>
                                            <?php else: ?>
                                                <span style="color:#28a745; font-size:12px;">✓ Concluído</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px; color:#666;">
                                            <?= date('d/m/Y', strtotime($u['criado_em'])) ?>
                                        </td>
                                        <td>
                                            <?php if (temPermissao('usuarios_gerenciar')): ?>
                                            <div class="action-btns">
                                                <a href="usuarios.php?permissoes=<?= $u['id'] ?><?= $busca ? '&busca='.urlencode($busca) : '' ?>"
                                                   class="action-btn action-edit" title="Editar permissões">
                                                    <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm0 4l5 2.18V11c0 3.5-2.33 6.79-5 7.93-2.67-1.14-5-4.43-5-7.93V7.18L12 5z"/></svg>
                                                </a>
                                                <?php if ($u['id'] !== (int)$_SESSION['usuario_id']): ?>
                                                    <a href="<?= buildUrlU(['toggle'=>$u['id']]) ?>"
                                                       onclick="return confirm('<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?> este usuário?')"
                                                       class="action-btn action-toggle" title="<?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>">
                                                        <svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                                    </a>
                                                    <a href="<?= buildUrlU(['excluir'=>$u['id']]) ?>"
                                                       onclick="return confirm('Excluir o usuário \'<?= htmlspecialchars($u['username']) ?>\'? Isso é permanente.')"
                                                       class="action-btn action-delete" title="Excluir">
                                                        <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginação -->
                    <?php if ($totalPags > 1): ?>
                    <div class="pagination">
                        <?php if ($pagina > 1): ?>
                            <a href="<?= buildUrlU(['pagina'=>1]) ?>" class="page-btn">«</a>
                            <a href="<?= buildUrlU(['pagina'=>$pagina-1]) ?>" class="page-btn">‹ Anterior</a>
                        <?php endif; ?>
                        <?php for ($p = max(1,$pagina-2); $p <= min($totalPags,$pagina+2); $p++): ?>
                            <a href="<?= buildUrlU(['pagina'=>$p]) ?>"
                               class="page-btn <?= $p === $pagina ? 'page-active' : '' ?>"><?= $p ?></a>
                        <?php endfor; ?>
                        <?php if ($pagina < $totalPags): ?>
                            <a href="<?= buildUrlU(['pagina'=>$pagina+1]) ?>" class="page-btn">Próxima ›</a>
                            <a href="<?= buildUrlU(['pagina'=>$totalPags]) ?>" class="page-btn">»</a>
                        <?php endif; ?>
                        <span class="page-info">Página <?= $pagina ?> de <?= $totalPags ?></span>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>

        <!-- Painel lateral: edição de permissões -->
        <?php if ($editando_user && temPermissao('usuarios_gerenciar')): ?>
        <div>
            <div class="card perm-panel">
                <div class="card-header">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
                    Permissões — <?= htmlspecialchars($editando_user['username']) ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="usuarios.php<?= $busca ? '?busca='.urlencode($busca) : '' ?>">
                        <input type="hidden" name="usuario_id" value="<?= $editando_user['id'] ?>">

                        <div class="perm-list">
                            <?php
                            $grupos = [
                                'Funcionários' => ['funcionarios_ver','funcionarios_criar','funcionarios_editar','funcionarios_excluir'],
                                'Relatórios'   => ['relatorios_ver'],
                                'Usuários'     => ['usuarios_ver','usuarios_gerenciar'],
                            ];
                            $modulos_map = array_column($modulos, null, 'chave');
                            foreach ($grupos as $grupo => $chaves):
                            ?>
                            <div class="perm-group">
                                <p class="perm-group-title"><?= $grupo ?></p>
                                <?php foreach ($chaves as $chave):
                                    $mod = $modulos_map[$chave] ?? null;
                                    if (!$mod) continue;
                                ?>
                                <label class="perm-item">
                                    <input type="checkbox" name="perm[]" value="<?= $chave ?>"
                                           <?= in_array($chave, $perms_user) ? 'checked' : '' ?>>
                                    <div>
                                        <strong><?= htmlspecialchars($mod['nome']) ?></strong>
                                        <small><?= htmlspecialchars($mod['descricao']) ?></small>
                                    </div>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="perm-footer">
                            <button type="submit" name="salvar_permissoes" class="btn btn-primary" style="width:100%;">
                                Salvar Permissões
                            </button>
                            <a href="usuarios.php<?= $busca ? '?busca='.urlencode($busca) : '' ?>"
                               class="btn btn-outline" style="width:100%; text-align:center; margin-top:8px;">
                                Cancelar
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>
</body>
</html>
