-- Script de criação do banco de dados
-- Execute este script no PostgreSQL antes de iniciar o sistema

CREATE DATABASE cadastro_funcionarios;

\c cadastro_funcionarios;

-- Tabela de usuários (para login)
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT NOW()
);

-- Inserir usuário admin padrão (senha: admin123)
INSERT INTO usuarios (username, senha)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi')
ON CONFLICT (username) DO NOTHING;

-- Tabela de cargos
CREATE TABLE IF NOT EXISTS cargos (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(100) NOT NULL UNIQUE
);

INSERT INTO cargos (nome) VALUES
    ('Administrador'),
    ('Gerente'),
    ('Assistente'),
    ('Analista'),
    ('Coordenador'),
    ('Diretor'),
    ('Supervisor')
ON CONFLICT (nome) DO NOTHING;

-- Tabela de funcionários
CREATE TABLE IF NOT EXISTS funcionarios (
    id SERIAL PRIMARY KEY,
    nome VARCHAR(200) NOT NULL,
    cargo_id INTEGER REFERENCES cargos(id),
    email VARCHAR(200),
    telefone VARCHAR(30),
    situacao VARCHAR(10) DEFAULT 'Ativo' CHECK (situacao IN ('Ativo', 'Inativo')),
    criado_em TIMESTAMP DEFAULT NOW(),
    atualizado_em TIMESTAMP DEFAULT NOW()
);

-- Dados de exemplo
INSERT INTO funcionarios (nome, cargo_id, email, telefone, situacao) VALUES
    ('João Silva', 1, 'jo@mi@ensx.com', '(61) 98888-1111', 'Ativo'),
    ('Ana Mendes', 2, 'repca@ensx.com', '(61) 98888-2222', 'Ativo'),
    ('Pedro Souza', 3, 'souza@ensx.com', '(61) 98888-3333', 'Ativo'),
    ('Carla Oliveira', 1, 'robog@ensx.com', '(61) 98888-4444', 'Ativo'),
    ('Lucas Martins', 3, 'lucas@ensx.com', '(61) 98888-5555', 'Inativo')
ON CONFLICT DO NOTHING;
