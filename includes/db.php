<?php
function getConnection() {
    $host = 'localhost';
    $port = '5432'; // Porta padrão do PostgreSQL
    $dbname = 'cadastro_funcionarios'; // Nome do banco que criamos
    $user = 'postgres'; // O usuário padrão do Postgres costuma ser 'postgres'
    $password = '123456'; // COLOQUE A SENHA DO SEU POSTGRESQL AQUI!

    try {
        // String de conexão específica para PostgreSQL
        $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Isso vai jogar o erro real na tela para nós!
        die("ERRO FATAL DO BANCO: " . $e->getMessage());
    }
}
?>