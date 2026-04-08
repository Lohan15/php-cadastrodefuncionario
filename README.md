# Cadastro de Funcionários

Sistema web de cadastro e gerenciamento de funcionários desenvolvido em **PHP** com banco de dados **PostgreSQL**.

## 📋 Funcionalidades

- **Login** com autenticação segura (password_hash/verify)
- **Cadastro de funcionários**: nome, cargo, e-mail, telefone, situação (ativo/inativo)
- **Listagem** com busca, paginação e ações (editar, alternar situação, excluir)
- **Recuperação de senha** (tela)
- **Logout**

## 🛠 Tecnologias

- PHP 8.x (puro, sem frameworks)
- PostgreSQL
- HTML5 + CSS3 (sem frameworks CSS)
- JavaScript puro

## ⚙️ Como configurar

### 1. Pré-requisitos

- PHP 8.0+
- PostgreSQL 13+
- Extensão `pdo_pgsql` habilitada no PHP

### 2. Banco de dados

```bash
# Acesse o PostgreSQL e execute o script:
psql -U postgres -f database.sql
```

Ou manualmente:
```sql
CREATE DATABASE cadastro_funcionarios;
\c cadastro_funcionarios
-- execute o conteúdo de database.sql
```

### 3. Configuração da conexão

Edite o arquivo `includes/db.php` com suas credenciais:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'cadastro_funcionarios');
define('DB_USER', 'postgres');
define('DB_PASS', 'sua_senha');
```

### 4. Servidor web

**Com PHP built-in server (desenvolvimento):**
```bash
cd cadastro-funcionarios
php -S localhost:8080
```

**Com Apache:** Configure o DocumentRoot para a pasta do projeto.

### 5. Acesso

- URL: `http://localhost:8080`
- Usuário: `admin`
- Senha: `password` *(ou altere via hash no banco)*

Para gerar um novo hash de senha:
```php
echo password_hash('nova_senha', PASSWORD_DEFAULT);
```
Atualize na tabela `usuarios`.

## 📁 Estrutura de pastas

```
cadastro-funcionarios/
├── index.php              # Tela de login
├── database.sql           # Script de criação do banco
├── css/
│   └── style.css          # Estilos principais
├── includes/
│   ├── auth.php           # Sessão e autenticação
│   ├── db.php             # Conexão PDO com PostgreSQL
│   └── navbar.php         # Barra de navegação
└── pages/
    ├── home.php           # Cadastro/edição de funcionário
    ├── listagem.php       # Listagem com busca e paginação
    ├── logout.php         # Encerramento de sessão
    └── esqueci_senha.php  # Recuperação de senha
```

## 🔒 Segurança

- Senhas armazenadas com `password_hash()` (bcrypt)
- Proteção contra SQL Injection via PDO Prepared Statements
- Proteção contra XSS via `htmlspecialchars()`
- Rotas protegidas por verificação de sessão

## 📸 Telas

| Tela | Descrição |
|------|-----------|
| Login | Autenticação com usuário e senha |
| Cadastro | Formulário de cadastro/edição de funcionário |
| Listagem | Tabela com busca, paginação e ações |
