<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();
requirePermissao('funcionarios_ver');

$pdo = getConnection();

// Ações rápidas
if (isset($_GET['excluir'])) {
    requirePermissao('funcionarios_excluir');
    $id = (int)$_GET['excluir'];
    $nome_func = $pdo->prepare("SELECT nome FROM funcionarios WHERE id=:id");
    $nome_func->execute([':id' => $id]);
    $nf = $nome_func->fetchColumn();
    $pdo->prepare("DELETE FROM funcionarios WHERE id = :id")->execute([':id' => $id]);
    registrarLog($pdo, 'excluir', 'funcionarios', $id, "Funcionário '$nf' excluído.");
    header('Location: listagem.php?msg=excluido'); exit;
}
if (isset($_GET['toggle'])) {
    requirePermissao('funcionarios_editar');
    $id    = (int)$_GET['toggle'];
    $stmt  = $pdo->prepare("SELECT situacao, nome FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $row   = $stmt->fetch();
    $novo  = $row['situacao'] === 'Ativo' ? 'Inativo' : 'Ativo';
    $pdo->prepare("UPDATE funcionarios SET situacao=:s, atualizado_em=NOW() WHERE id=:id")->execute([':s'=>$novo,':id'=>$id]);
    registrarLog($pdo, 'editar', 'funcionarios', $id, "Situação de '{$row['nome']}' alterada para '$novo'.");
    header('Location: listagem.php?msg=atualizado'); exit;
}

$busca    = trim($_GET['busca'] ?? '');
$pagina   = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = 10;
$offset   = ($pagina - 1) * $porPagina;

$where = ''; $params = [];
if ($busca !== '') {
    $where  = "WHERE f.nome ILIKE :b OR f.email ILIKE :b OR c.nome ILIKE :b";
    $params = [':b' => "%$busca%"];
}

$total = $pdo->prepare("SELECT COUNT(*) FROM funcionarios f LEFT JOIN cargos c ON c.id = f.cargo_id $where");
$total->execute($params);
$totalRegistros = (int)$total->fetchColumn();
$totalPags      = (int)ceil($totalRegistros / $porPagina);

$stmt = $pdo->prepare("SELECT f.id, f.nome, c.nome AS cargo, f.email, f.telefone, f.situacao
    FROM funcionarios f LEFT JOIN cargos c ON c.id = f.cargo_id
    $where ORDER BY f.id ASC LIMIT :lim OFFSET :off");
foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
$stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':off', $offset, PDO::PARAM_INT);
$stmt->execute();
$funcionarios = $stmt->fetchAll();

$msg = $_GET['msg'] ?? '';

function buildUrl(array $extra): string {
    $params = array_merge($_GET, $extra);
    unset($params['msg']);
    return 'listagem.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Listagem de Funcionários</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main-content">
    <h2 class="page-title">Listagem de Funcionários</h2>

    <?php if ($msg === 'excluido'): ?>
        <div class="alert alert-success">Funcionário excluído com sucesso.</div>
    <?php elseif ($msg === 'atualizado'): ?>
        <div class="alert alert-success">Situação atualizada com sucesso.</div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <!-- Barra de ações -->
            <div class="list-toolbar">
                <form method="GET" action="listagem.php" class="search-form">
                    <div class="search-input-wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 14z"/></svg>
                        <input type="text" name="busca" placeholder="Buscar por nome, cargo ou e-mail..." value="<?= htmlspecialchars($busca) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Pesquisar</button>
                    <?php if ($busca): ?>
                        <a href="listagem.php" class="btn btn-outline">Limpar</a>
                    <?php endif; ?>
                </form>

                <?php if (temPermissao('funcionarios_criar')): ?>
                    <a href="home.php" class="btn btn-primary">+ Novo Funcionário</a>
                <?php endif; ?>
            </div>

            <!-- Contador -->
            <p class="list-count">
                <?php if ($busca): ?>
                    <?= $totalRegistros ?> resultado(s) para "<strong><?= htmlspecialchars($busca) ?></strong>"
                <?php else: ?>
                    Total: <strong><?= $totalRegistros ?></strong> funcionário(s)
                <?php endif; ?>
            </p>

            <!-- Tabela -->
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Cargo</th>
                            <th>E-mail</th>
                            <th>Situação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($funcionarios)): ?>
                            <tr><td colspan="6" class="empty-row">Nenhum funcionário encontrado.</td></tr>
                        <?php else: foreach ($funcionarios as $i => $f): ?>
                            <tr>
                                <td class="col-id"><?= $offset + $i + 1 ?></td>
                                <td class="col-nome">
                                    <?php if (temPermissao('funcionarios_editar')): ?>
                                        <a href="home.php?editar=<?= $f['id'] ?>"><?= htmlspecialchars($f['nome']) ?></a>
                                    <?php else: ?>
                                        <?= htmlspecialchars($f['nome']) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($f['cargo'] ?? '—') ?></td>
                                <td class="col-email"><em><?= htmlspecialchars($f['email'] ?? '') ?></em></td>
                                <td>
                                    <span class="badge <?= $f['situacao'] === 'Ativo' ? 'badge-ativo' : 'badge-inativo' ?>">
                                        <?= $f['situacao'] ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <?php if (temPermissao('funcionarios_editar')): ?>
                                            <a href="home.php?editar=<?= $f['id'] ?>" class="action-btn action-edit" title="Editar">
                                                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm17.71-10.21a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                                            </a>
                                            <a href="<?= buildUrl(['toggle'=>$f['id']]) ?>"
                                               onclick="return confirm('Alterar situação de <?= htmlspecialchars($f['nome']) ?>?')"
                                               class="action-btn action-toggle" title="Alterar situação">
                                                <svg viewBox="0 0 24 24"><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10 10-4.5 10-10S17.5 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (temPermissao('funcionarios_excluir')): ?>
                                            <a href="<?= buildUrl(['excluir'=>$f['id']]) ?>"
                                               onclick="return confirm('Excluir <?= htmlspecialchars($f['nome']) ?>? Esta ação não pode ser desfeita.')"
                                               class="action-btn action-delete" title="Excluir">
                                                <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
                    <a href="<?= buildUrl(['pagina'=>1]) ?>" class="page-btn" title="Primeira">«</a>
                    <a href="<?= buildUrl(['pagina'=>$pagina-1]) ?>" class="page-btn">‹ Anterior</a>
                <?php endif; ?>

                <?php
                $inicio = max(1, $pagina - 2);
                $fim    = min($totalPags, $pagina + 2);
                for ($p = $inicio; $p <= $fim; $p++):
                ?>
                    <a href="<?= buildUrl(['pagina'=>$p]) ?>"
                       class="page-btn <?= $p === $pagina ? 'page-active' : '' ?>">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($pagina < $totalPags): ?>
                    <a href="<?= buildUrl(['pagina'=>$pagina+1]) ?>" class="page-btn">Próxima ›</a>
                    <a href="<?= buildUrl(['pagina'=>$totalPags]) ?>" class="page-btn" title="Última">»</a>
                <?php endif; ?>

                <span class="page-info">Página <?= $pagina ?> de <?= $totalPags ?></span>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
</body>
</html>
