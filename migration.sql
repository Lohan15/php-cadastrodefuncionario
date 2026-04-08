-- ============================================================
-- MIGRAÇÃO: Controle de Permissões, Log e Gerenciamento de Usuários
-- Execute após o database.sql inicial
-- ============================================================

\c cadastro_funcionarios;

-- 1. Adicionar coluna mudar_senha na tabela usuarios (se não existir)
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS mudar_senha BOOLEAN DEFAULT TRUE;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS ativo BOOLEAN DEFAULT TRUE;
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS atualizado_em TIMESTAMP DEFAULT NOW();

-- Atualiza admin para não precisar mudar senha
UPDATE usuarios SET mudar_senha = FALSE WHERE username = 'admin';

-- 2. Tabela de módulos (recursos do sistema)
CREATE TABLE IF NOT EXISTS modulos (
    id SERIAL PRIMARY KEY,
    chave VARCHAR(50) NOT NULL UNIQUE,
    nome VARCHAR(100) NOT NULL,
    descricao VARCHAR(255)
);

INSERT INTO modulos (chave, nome, descricao) VALUES
    ('funcionarios_ver',    'Ver Funcionários',        'Acessar a listagem de funcionários'),
    ('funcionarios_criar',  'Cadastrar Funcionários',  'Criar novos funcionários'),
    ('funcionarios_editar', 'Editar Funcionários',     'Alterar dados de funcionários'),
    ('funcionarios_excluir','Excluir Funcionários',    'Remover funcionários do sistema'),
    ('relatorios_ver',      'Ver Relatórios',          'Acessar a tela de relatórios e exportar'),
    ('usuarios_ver',        'Ver Usuários',            'Acessar a tela de gerenciamento de usuários'),
    ('usuarios_gerenciar',  'Gerenciar Usuários',      'Criar, editar e desativar usuários do sistema')
ON CONFLICT (chave) DO NOTHING;

-- 3. Tabela de permissões (usuário x módulo)
CREATE TABLE IF NOT EXISTS permissoes (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    modulo_chave VARCHAR(50) NOT NULL REFERENCES modulos(chave) ON DELETE CASCADE,
    UNIQUE(usuario_id, modulo_chave)
);

-- Dar todas as permissões ao admin
INSERT INTO permissoes (usuario_id, modulo_chave)
SELECT u.id, m.chave
FROM usuarios u, modulos m
WHERE u.username = 'admin'
ON CONFLICT DO NOTHING;

-- 4. Tabela de log de atividades
CREATE TABLE IF NOT EXISTS logs (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
    usuario_nome VARCHAR(100),
    acao VARCHAR(50) NOT NULL,
    entidade VARCHAR(50),
    entidade_id INTEGER,
    descricao TEXT,
    ip VARCHAR(45),
    criado_em TIMESTAMP DEFAULT NOW()
);

-- 5. Índices para performance
CREATE INDEX IF NOT EXISTS idx_logs_usuario ON logs(usuario_id);
CREATE INDEX IF NOT EXISTS idx_logs_criado ON logs(criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_permissoes_usuario ON permissoes(usuario_id);
