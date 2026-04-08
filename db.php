<?php
function getConnection() {
    // Dados fornecidos pelo painel do Render
    $host = 'dpg-d7aq9cjuibrs73av916g-a'; 
    $port = '5432';
    $dbname = 'cadastro_funcionarios_php';
    $user = 'cadastro_funcionarios_php_user';
    $password = '0BbtsnI6H2p3RomRbZJA5GZU8dKaGbjO';

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        die("ERRO DE CONEXÃO: " . $e->getMessage());
    }
}
?>
