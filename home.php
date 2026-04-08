<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
requireLogin();
requirePermissao('funcionarios_ver');

$sucesso = '';
$erro    = '';
$func    = ['nome' => '', 'cargo_id' => '', 'email' => '', 'telefone' => '', 'situacao' => 'Ativo'];

$pdo    = getConnection();
$cargos = $pdo->query("SELECT id, nome FROM cargos ORDER BY nome")->fetchAll();

$editando = false;
if (isset($_GET['editar'])) {
    requirePermissao('funcionarios_editar');
    $id   = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM funcionarios WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $reg = $stmt->fetch();
    if ($reg) { $func = $reg; $editando = true; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar'])) {
    $id       = (int)($_POST['id'] ?? 0);
    $nome     = trim($_POST['nome'] ?? '');
    $cargo_id = (int)($_POST['cargo_id'] ?? 0);
    $email    = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $situacao = $_POST['situacao'] === 'Inativo' ? 'Inativo' : 'Ativo';

    if ($nome === '') {
        $erro = 'O campo Nome é obrigatório.';
    } else {
        // Verifica permissão de acordo com a operação
        if ($id > 0) requirePermissao('funcionarios_editar');
        else         requirePermissao('funcionarios_criar');

        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE funcionarios SET nome=:n, cargo_id=:c, email=:e, telefone=:t, situacao=:s, atualizado_em=NOW() WHERE id=:id");
                $stmt->execute([':n'=>$nome,':c'=>$cargo_id,':e'=>$email,':t'=>$telefone,':s'=>$situacao,':id'=>$id]);
                registrarLog($pdo, 'editar', 'funcionarios', $id, "Funcionário '$nome' atualizado.");
                $sucesso = 'Funcionário atualizado com sucesso!';
            } else {
                $stmt = $pdo->prepare("INSERT INTO funcionarios (nome, cargo_id, email, telefone, situacao) VALUES (:n,:c,:e,:t,:s)");
                $stmt->execute([':n'=>$nome,':c'=>$cargo_id,':e'=>$email,':t'=>$telefone,':s'=>$situacao]);
                $novo_id = (int)$pdo->lastInsertId('funcionarios_id_seq');
                registrarLog($pdo, 'criar', 'funcionarios', $novo_id, "Funcionário '$nome' cadastrado.");

                $user_acesso  = trim($_POST['user_acesso'] ?? '');
                $senha_acesso = trim($_POST['senha_acesso'] ?? '');

                if ($user_acesso !== '' && $senha_acesso !== '') {
                    if (temPermissao('usuarios_gerenciar')) {
                        $hash = password_hash($senha_acesso, PASSWORD_DEFAULT);
                        $stmtUser = $pdo->prepare("INSERT INTO usuarios (username, senha, mudar_senha) VALUES (:u, :p, TRUE)");
                        $stmtUser->execute([':u' => $user_acesso, ':p' => $hash]);
                        registrarLog($pdo, 'criar', 'usuarios', null, "Acesso criado para usuário '$user_acesso'.");
                        $sucesso = 'Funcionário e acesso cadastrados com sucesso!';
                    } else {
                        $sucesso = 'Funcionário cadastrado. Sem permissão para criar acesso ao sistema.';
                    }
                } else {
                    $sucesso = 'Funcionário cadastrado com sucesso!';
                }
            }
            $func = ['nome'=>'','cargo_id'=>'','email'=>'','telefone'=>'','situacao'=>'Ativo'];
            $editando = false;
        } catch (Exception $e) {
            $erro = 'Erro ao salvar: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Funcionários</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<?php include '../includes/navbar.php'; ?>
<div class="main-content">
    <h2 class="page-title"><?= $editando ? 'Editar Funcionário' : 'Cadastro de Funcionários' ?></h2>

    <?php if ($sucesso): ?>
        <div class="alert alert-success"><?= htmlspecialchars($sucesso) ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="alert alert-error"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                <path d="M12 12c2.7 0 5-2.3 5-5s-2.3-5-5-5-5 2.3-5 5 2.3 5 5 5zm0 2c-3.3 0-10 1.7-10 5v2h20v-2c0-3.3-6.7-5-10-5z"/>
            </svg>
            <?= $editando ? 'Editar Funcionário' : 'Novo Funcionário' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="home.php<?= $editando ? '?editar='.$func['id'] : '' ?>">
                <input type="hidden" name="id" value="<?= $editando ? (int)$func['id'] : 0 ?>">
                <p class="id-label">ID: <?= $editando ? (int)$func['id'] : 'Automático' ?></p>

                <div class="form-row">
                    <div class="form-group">
                        <label for="nome">Nome *</label>
                        <input type="text" id="nome" name="nome" placeholder="Nome completo"
                               value="<?= htmlspecialchars($func['nome']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="cargo_id">Cargo</label>
                        <select id="cargo_id" name="cargo_id">
                            <option value="">Selecione...</option>
                            <?php foreach ($cargos as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= (string)($func['cargo_id'] ?? '') === (string)$c['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($c['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="email@empresa.com"
                               value="<?= htmlspecialchars($func['email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label for="telefone">Telefone</label>
                        <input type="text" id="telefone" name="telefone" placeholder="(61) 99999-9999"
                               value="<?= htmlspecialchars($func['telefone'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="situacao-group">
                        <label class="title">Situação</label>
                        <div class="radio-options">
                            <label>
                                <input type="radio" name="situacao" value="Ativo"
                                    <?= ($func['situacao'] ?? 'Ativo') === 'Ativo' ? 'checked' : '' ?>> Ativo
                            </label>
                            <label>
                                <input type="radio" name="situacao" value="Inativo"
                                    <?= ($func['situacao'] ?? '') === 'Inativo' ? 'checked' : '' ?>> Inativo
                            </label>
                        </div>
                    </div>
                </div>

                <?php if (!$editando && temPermissao('usuarios_gerenciar')): ?>
                <div class="acesso-section">
                    <p class="acesso-title">Criar Acesso ao Sistema <span>(Opcional)</span></p>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Usuário de Login</label>
                            <input type="text" name="user_acesso" placeholder="Ex: joao.silva">
                        </div>
                        <div class="form-group">
                            <label>Senha Provisória</label>
                            <input type="text" name="senha_acesso" placeholder="Será alterada no primeiro acesso">
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="btn-actions">
                    <button type="submit" name="salvar" class="btn btn-primary">
                        <?= $editando ? 'Atualizar' : 'Salvar' ?>
                    </button>
                    <a href="home.php" class="btn btn-outline">Limpar</a>
                    <a href="listagem.php" class="btn btn-secondary">← Listagem</a>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
