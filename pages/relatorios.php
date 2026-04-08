<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();
requirePermissao('relatorios_ver');

$pdo = getConnection();

// ─── Dados para os relatórios ─────────────────────────────────────────────────

// 1. Total por cargo
$por_cargo = $pdo->query("
    SELECT c.nome AS cargo, COUNT(f.id) AS total
    FROM cargos c
    LEFT JOIN funcionarios f ON f.cargo_id = c.id
    GROUP BY c.nome ORDER BY total DESC
")->fetchAll();

// 2. Ativos vs Inativos
$situacao = $pdo->query("
    SELECT situacao, COUNT(*) AS total FROM funcionarios GROUP BY situacao
")->fetchAll();
$ativos   = 0; $inativos = 0;
foreach ($situacao as $s) {
    if ($s['situacao'] === 'Ativo') $ativos = $s['total'];
    else $inativos = $s['total'];
}
$total_geral = $ativos + $inativos;

// 3. Log de atividades (paginado)
$pagina_log  = max(1, (int)($_GET['pagina_log'] ?? 1));
$por_pag_log = 15;
$offset_log  = ($pagina_log - 1) * $por_pag_log;

$total_log   = (int)$pdo->query("SELECT COUNT(*) FROM logs")->fetchColumn();
$total_pags_log = (int)ceil($total_log / $por_pag_log);

$stmt_log = $pdo->prepare("
    SELECT l.id, l.usuario_nome, l.acao, l.entidade, l.descricao, l.ip, l.criado_em
    FROM logs l
    ORDER BY l.criado_em DESC
    LIMIT :lim OFFSET :off
");
$stmt_log->bindValue(':lim', $por_pag_log, PDO::PARAM_INT);
$stmt_log->bindValue(':off', $offset_log, PDO::PARAM_INT);
$stmt_log->execute();
$logs = $stmt_log->fetchAll();

// ─── Exportação ───────────────────────────────────────────────────────────────
if (isset($_GET['exportar'])) {
    $formato = $_GET['exportar'];

    $dados = $pdo->query("
        SELECT f.id, f.nome, c.nome AS cargo, f.email, f.telefone, f.situacao,
               to_char(f.criado_em, 'DD/MM/YYYY HH24:MI') AS criado_em
        FROM funcionarios f
        LEFT JOIN cargos c ON c.id = f.cargo_id
        ORDER BY f.nome
    ")->fetchAll();

    if ($formato === 'csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="funcionarios_'.date('Ymd').'.csv"');
        echo "\xEF\xBB\xBF"; // BOM UTF-8
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Nome','Cargo','E-mail','Telefone','Situação','Cadastrado em'], ';');
        foreach ($dados as $row) {
            fputcsv($out, array_values($row), ';');
        }
        fclose($out);
        exit;
    }

    if ($formato === 'html_print') {
        // Relatório HTML formatado para impressão / salvar como PDF pelo navegador
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">';
        echo '<title>Relatório de Funcionários</title>';
        echo '<style>
            body{font-family:Arial,sans-serif;font-size:12px;margin:20px;}
            h2{color:#3b5998;} table{width:100%;border-collapse:collapse;margin-top:20px;}
            th{background:#3b5998;color:white;padding:8px;text-align:left;}
            td{padding:7px 8px;border-bottom:1px solid #ddd;}
            tr:nth-child(even){background:#f5f5f5;}
            .badge{padding:2px 8px;border-radius:10px;font-size:11px;color:white;}
            .ativo{background:#28a745;} .inativo{background:#6c757d;}
            .footer{margin-top:20px;color:#666;font-size:11px;}
        </style></head><body>';
        echo '<h2>Relatório de Funcionários</h2>';
        echo '<p>Gerado em: '.date('d/m/Y H:i').' | Total: '.count($dados).' funcionário(s)</p>';
        echo '<table><thead><tr><th>ID</th><th>Nome</th><th>Cargo</th><th>E-mail</th><th>Telefone</th><th>Situação</th><th>Cadastrado em</th></tr></thead><tbody>';
        foreach ($dados as $row) {
            $badge = $row['situacao'] === 'Ativo' ? 'ativo' : 'inativo';
            echo '<tr>';
            echo '<td>'.htmlspecialchars($row['id']).'</td>';
            echo '<td>'.htmlspecialchars($row['nome']).'</td>';
            echo '<td>'.htmlspecialchars($row['cargo'] ?? '—').'</td>';
            echo '<td>'.htmlspecialchars($row['email'] ?? '').'</td>';
            echo '<td>'.htmlspecialchars($row['telefone'] ?? '').'</td>';
            echo '<td><span class="badge '.$badge.'">'.htmlspecialchars($row['situacao']).'</span></td>';
            echo '<td>'.htmlspecialchars($row['criado_em']).'</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p class="footer">Sistema de Cadastro de Funcionários</p>';
        echo '<script>window.print();</script>';
        echo '</body></html>';
        exit;
    }
}

// Cores para o gráfico de barras
$cores = ['#4267b2','#3b5998','#8b9dc3','#6c757d','#28a745','#dc3545','#e67e22'];

$acoes_label = [
    'criar'      => ['label'=>'Criou',     'color'=>'#28a745'],
    'editar'     => ['label'=>'Editou',    'color'=>'#4267b2'],
    'excluir'    => ['label'=>'Excluiu',   'color'=>'#dc3545'],
    'login'      => ['label'=>'Login',     'color'=>'#8b9dc3'],
    'permissoes' => ['label'=>'Permissões','color'=>'#e67e22'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main-content" style="max-width:1100px;">
    <h2 class="page-title">Relatórios</h2>

    <!-- ── Linha de cards de resumo ─────────────────────────────────────── -->
    <div class="relatorio-resumo">
        <div class="resumo-card">
            <div class="resumo-icon" style="background:#e8f0fe;">
                <svg viewBox="0 0 24 24" fill="#4267b2"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div>
                <p class="resumo-num"><?= $total_geral ?></p>
                <p class="resumo-label">Total de Funcionários</p>
            </div>
        </div>
        <div class="resumo-card">
            <div class="resumo-icon" style="background:#e6f9ee;">
                <svg viewBox="0 0 24 24" fill="#28a745"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
            </div>
            <div>
                <p class="resumo-num"><?= $ativos ?></p>
                <p class="resumo-label">Ativos</p>
            </div>
        </div>
        <div class="resumo-card">
            <div class="resumo-icon" style="background:#f8f0f0;">
                <svg viewBox="0 0 24 24" fill="#dc3545"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/></svg>
            </div>
            <div>
                <p class="resumo-num"><?= $inativos ?></p>
                <p class="resumo-label">Inativos</p>
            </div>
        </div>
        <div class="resumo-card">
            <div class="resumo-icon" style="background:#fff8e1;">
                <svg viewBox="0 0 24 24" fill="#e67e22"><path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM5 13.18v4L12 21l7-3.82v-4L12 17l-7-3.82z"/></svg>
            </div>
            <div>
                <p class="resumo-num"><?= count($por_cargo) ?></p>
                <p class="resumo-label">Cargos Cadastrados</p>
            </div>
        </div>
    </div>

    <!-- ── Gráficos ──────────────────────────────────────────────────────── -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">

        <!-- Funcionários por cargo -->
        <div class="card">
            <div class="card-header">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5 9.2h3V19H5V9.2zM10.6 5h2.8v14h-2.8V5zM16.2 13h2.8v6h-2.8v-6z"/></svg>
                Funcionários por Cargo
            </div>
            <div class="card-body">
                <?php if (empty($por_cargo)): ?>
                    <p style="color:#aaa; text-align:center; padding:20px 0;">Nenhum dado disponível.</p>
                <?php else:
                    $max = max(array_column($por_cargo, 'total')) ?: 1;
                    foreach ($por_cargo as $idx => $row):
                        $pct = round(($row['total'] / $max) * 100);
                        $cor = $cores[$idx % count($cores)];
                ?>
                <div class="bar-row">
                    <span class="bar-label"><?= htmlspecialchars($row['cargo']) ?></span>
                    <div class="bar-track">
                        <div class="bar-fill" style="width:<?= $pct ?>%; background:<?= $cor ?>;"></div>
                    </div>
                    <span class="bar-value"><?= $row['total'] ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Ativos vs Inativos -->
        <div class="card">
            <div class="card-header">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93h8l-1 7.93z"/></svg>
                Situação dos Funcionários
            </div>
            <div class="card-body" style="display:flex; align-items:center; justify-content:center; gap:40px; padding:30px 20px;">
                <?php if ($total_geral > 0):
                    $pct_ativo   = round(($ativos   / $total_geral) * 100);
                    $pct_inativo = round(($inativos  / $total_geral) * 100);
                    $ativo_dash   = round(($ativos   / $total_geral) * 283); // circunferência SVG r=45
                    $inativo_dash = 283 - $ativo_dash;
                ?>
                <div style="position:relative; width:140px; height:140px;">
                    <svg viewBox="0 0 100 100" width="140" height="140" style="transform:rotate(-90deg);">
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#e9ecef" stroke-width="10"/>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#28a745" stroke-width="10"
                            stroke-dasharray="<?= $ativo_dash ?> <?= $inativo_dash ?>"
                            stroke-linecap="round"/>
                        <?php if ($inativos > 0): ?>
                        <circle cx="50" cy="50" r="45" fill="none" stroke="#dc3545" stroke-width="10"
                            stroke-dasharray="<?= $inativo_dash ?> <?= $ativo_dash ?>"
                            stroke-dashoffset="-<?= $ativo_dash ?>"
                            stroke-linecap="round"/>
                        <?php endif; ?>
                    </svg>
                    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;">
                        <strong style="font-size:22px;color:#3b5998;"><?= $total_geral ?></strong><br>
                        <span style="font-size:11px;color:#666;">total</span>
                    </div>
                </div>
                <div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background:#28a745;"></span>
                        <span>Ativos</span>
                        <strong><?= $ativos ?> (<?= $pct_ativo ?>%)</strong>
                    </div>
                    <div class="legend-item" style="margin-top:12px;">
                        <span class="legend-dot" style="background:#dc3545;"></span>
                        <span>Inativos</span>
                        <strong><?= $inativos ?> (<?= $pct_inativo ?>%)</strong>
                    </div>
                </div>
                <?php else: ?>
                    <p style="color:#aaa;">Nenhum funcionário cadastrado.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Exportação ────────────────────────────────────────────────────── -->
    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M19 9h-4V3H9v6H5l7 7 7-7zM5 18v2h14v-2H5z"/></svg>
            Exportar Dados
        </div>
        <div class="card-body" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap;">
            <p style="color:#666; font-size:13px; flex:1;">Exporte a lista completa de funcionários nos formatos abaixo:</p>
            <a href="relatorios.php?exportar=csv" class="btn btn-primary" style="text-decoration:none;">
                📄 Exportar CSV (Excel)
            </a>
            <a href="relatorios.php?exportar=html_print" target="_blank" class="btn btn-outline" style="text-decoration:none;">
                🖨 Gerar PDF (Impressão)
            </a>
        </div>
    </div>

    <!-- ── Log de atividades ──────────────────────────────────────────────── -->
    <div class="card">
        <div class="card-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M13 3a9 9 0 0 0-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42A8.954 8.954 0 0 0 13 21a9 9 0 0 0 0-18zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
            Histórico de Atividades
            <span style="margin-left:auto; font-size:12px; font-weight:normal; color:#666;"><?= $total_log ?> registro(s)</span>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($logs)): ?>
                <p style="color:#aaa; text-align:center; padding:30px;">Nenhuma atividade registrada ainda.</p>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:160px;">Data/Hora</th>
                        <th style="width:130px;">Usuário</th>
                        <th style="width:100px;">Ação</th>
                        <th>Descrição</th>
                        <th style="width:110px;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log):
                        $info = $acoes_label[$log['acao']] ?? ['label'=>ucfirst($log['acao']),'color'=>'#6c757d'];
                    ?>
                    <tr>
                        <td style="font-size:12px; color:#666;">
                            <?= date('d/m/Y H:i', strtotime($log['criado_em'])) ?>
                        </td>
                        <td><strong><?= htmlspecialchars($log['usuario_nome'] ?? '—') ?></strong></td>
                        <td>
                            <span class="log-badge" style="background:<?= $info['color'] ?>;">
                                <?= $info['label'] ?>
                            </span>
                        </td>
                        <td style="font-size:13px;"><?= htmlspecialchars($log['descricao'] ?? '') ?></td>
                        <td style="font-size:11px; color:#aaa;"><?= htmlspecialchars($log['ip'] ?? '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Paginação do log -->
            <?php if ($total_pags_log > 1): ?>
            <div class="pagination" style="padding:15px 20px;">
                <?php if ($pagina_log > 1): ?>
                    <a href="?pagina_log=<?= $pagina_log-1 ?>" class="page-btn">‹ Anterior</a>
                <?php endif; ?>
                <?php for ($p = max(1,$pagina_log-2); $p <= min($total_pags_log,$pagina_log+2); $p++): ?>
                    <a href="?pagina_log=<?= $p ?>" class="page-btn <?= $p===$pagina_log?'page-active':'' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($pagina_log < $total_pags_log): ?>
                    <a href="?pagina_log=<?= $pagina_log+1 ?>" class="page-btn">Próxima ›</a>
                <?php endif; ?>
                <span class="page-info">Página <?= $pagina_log ?> de <?= $total_pags_log ?></span>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
