<?php
session_start();

function isLoggedIn(): bool {
    return isset($_SESSION['usuario_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit;
    }
}

/**
 * Verifica se o usuário logado tem uma permissão específica.
 */
function temPermissao(string $chave): bool {
    if (!isset($_SESSION['permissoes'])) return false;
    return in_array($chave, $_SESSION['permissoes'], true);
}

/**
 * Bloqueia acesso se o usuário não tiver a permissão.
 */
function requirePermissao(string $chave): void {
    requireLogin();
    if (!temPermissao($chave)) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

/**
 * Carrega as permissões do usuário na sessão (chamado após login).
 */
function carregarPermissoes(PDO $pdo, int $usuario_id): void {
    $stmt = $pdo->prepare("SELECT modulo_chave FROM permissoes WHERE usuario_id = :id");
    $stmt->execute([':id' => $usuario_id]);
    $_SESSION['permissoes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Registra uma ação no log do sistema.
 */
function registrarLog(PDO $pdo, string $acao, string $entidade, ?int $entidade_id, string $descricao): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs (usuario_id, usuario_nome, acao, entidade, entidade_id, descricao, ip)
            VALUES (:uid, :unome, :acao, :entidade, :eid, :desc, :ip)
        ");
        $stmt->execute([
            ':uid'      => $_SESSION['usuario_id'] ?? null,
            ':unome'    => $_SESSION['usuario_nome'] ?? 'Sistema',
            ':acao'     => $acao,
            ':entidade' => $entidade,
            ':eid'      => $entidade_id,
            ':desc'     => $descricao,
            ':ip'       => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        ]);
    } catch (Exception $e) {
        // Log silencioso — não interrompe o fluxo
    }
}
