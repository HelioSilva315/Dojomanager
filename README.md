# Dojomanager
# DojoManager — Sistema SaaS para Academia de Artes Marciais

## Funcionalidades implementadas

- ✅ Login seguro com logo personalizável
- ✅ Criação de usuários com validação de senha forte e verificação de duplicidade
- ✅ Cadastro completo de alunos com todos os campos especificados
- ✅ Máscaras de CPF, RG e Data de Nascimento (JS)
- ✅ Integração com ViaCEP (API pública, sem chave de API)
- ✅ Detecção automática de menor de idade → abre formulário de responsável
- ✅ Cadastro de responsável (para menores)
- ✅ Cadastro de condição clínica com campos condicionais (Sim/Não)
- ✅ Cadastro de modalidades com faixas e graduações
- ✅ Atualização de faixa/graduação com histórico completo
- ✅ Dashboard com estatísticas (total, maiores, menores, por modalidade)
- ✅ Layout responsivo (mobile + desktop)
- ✅ Segurança: CSRF, bcrypt, prepared statements, headers HTTP, sessão segura

## Estrutura de arquivos

```
dojomanager/
├── public/              ← Document root do servidor web
│   ├── index.php        ← Front controller (único ponto de entrada)
│   ├── .htaccess        ← Regras Apache (rotas + segurança)
│   └── uploads/         ← Logos e fotos (criar com permissão 755)
├── src/
│   ├── Database.php     ← Conexão PDO singleton
│   ├── Controllers/
│   │   ├── AuthController.php
│   │   ├── AlunoController.php
│   │   ├── DashboardController.php
│   │   └── ModalidadeController.php
│   └── Services/
│       ├── CepService.php
│       └── CsrfService.php
├── config/
│   └── config.php       ← Constantes (sem credenciais!)
├── database/
│   └── schema.sql       ← Script de criação do banco
├── templates/           ← Views PHP
├── assets/
│   ├── css/app.css
│   └── js/app.js
├── .env.example         ← Modelo de variáveis de ambiente
├── .env                 ← Credenciais reais (NÃO commitar no git!)
├── .gitignore
└── composer.json
```

## Instalação

### 1. Requisitos
- PHP 8.1+
- MySQL 8.0+ ou MariaDB 10.5+
- Apache 2.4+ com mod_rewrite
- Composer

### 2. Clonar / fazer upload para o servidor
```bash
# Na raiz do domínio (ex: /var/www/html ou public_html)
git clone ... dojomanager
cd dojomanager
```

### 3. Instalar dependências
```bash
composer install --no-dev --optimize-autoloader
```

### 4. Configurar variáveis de ambiente
```bash
cp .env.example .env
nano .env   # preencha DB_HOST, DB_NAME, DB_USER, DB_PASSWORD
```

### 5. Criar o banco de dados
```sql
CREATE DATABASE dojomanager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```
Depois execute o schema:
```bash
mysql -u usuario -p dojomanager < database/schema.sql
```

### 6. Definir senha do admin
```php
// Execute uma vez para gerar o hash
echo password_hash('SuaSenhaForte123!', PASSWORD_BCRYPT, ['cost' => 12]);
```
Depois atualize na tabela:
```sql
UPDATE usuarios SET senha_hash = 'HASH_GERADO' WHERE email = 'admin@academia.com.br';
```

### 7. Configurar document root
O Apache deve apontar para a pasta `public/`, não para a raiz do projeto.

**Apache VirtualHost:**
```apache
<VirtualHost *:443>
    ServerName meudominio.com.br
    DocumentRoot /var/www/dojomanager/public

    <Directory /var/www/dojomanager/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### 8. Permissões
```bash
chmod 755 public/uploads
chown www-data:www-data public/uploads
chmod 600 .env
```

## Segurança implementada

| Camada | Medida |
|--------|--------|
| Autenticação | bcrypt cost=12, upgrade automático de hash |
| CSRF | Token rotativo por requisição, expira em 1h |
| SQL | 100% prepared statements (PDO) |
| Sessão | HTTPOnly, Secure, SameSite=Lax, strict mode |
| Credenciais | Variáveis de ambiente (.env), nunca no código |
| HTTP | X-Frame-Options, X-Content-Type-Options, CSP |
| Inputs | sanitização + validação server-side em todos os campos |
| Logs | Registro de logins (ok/falha) com IP e timestamp |

## .gitignore recomendado

```
.env
vendor/
public/uploads/*.jpg
public/uploads/*.png
*.log
```

