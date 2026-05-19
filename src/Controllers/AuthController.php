<?php
// =============================================================
// src/Controllers/AuthController.php
// Login, logout, criação de usuário, validação de senha
// =============================================================

namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Services\CsrfService;

class AuthController
{
    // ----------------------------------------------------------
    // GET /login
    // ----------------------------------------------------------
    public function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            header('Location: /dashboard');
            exit;
        }
        $csrfToken = CsrfService::generate();
        $logoPath  = $this->getLogoPath();
        require ROOT . '/templates/auth/login.php';
    }

    // ----------------------------------------------------------
    // POST /login
    // ----------------------------------------------------------
    public function processLogin(): void
    {
        CsrfService::validate($_POST['_csrf'] ?? '');

        $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $senha = $_POST['senha'] ?? '';

        if (empty($email) || empty($senha)) {
            $this->redirectLogin('Preencha e-mail e senha.');
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, nome, senha_hash, perfil, ativo FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Timing-safe: verifica mesmo se usuário não existir
        $hash = $user['senha_hash'] ?? '$2y$12$invalidhashtopreventtiming00000000000000000';
        $ok   = $user && password_verify($senha, $hash) && (bool) $user['ativo'];

        $this->logAcesso($user['id'] ?? null, $email, $ok ? 'login_ok' : 'login_falha', $ok);

        if (!$ok) {
            $this->redirectLogin('E-mail ou senha incorretos.');
        }

        // Re-hash se necessário (upgrade automático de custo)
        if (password_needs_rehash($user['senha_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $newHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([$newHash, $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['user_nome']  = $user['nome'];
        $_SESSION['user_perfil']= $user['perfil'];
        $_SESSION['login_em']   = time();

        header('Location: /dashboard');
        exit;
    }

    // ----------------------------------------------------------
    // GET /logout
    // ----------------------------------------------------------
    public function logout(): void
    {
        $this->logAcesso($_SESSION['user_id'] ?? null, null, 'logout', true);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /login');
        exit;
    }

    // ----------------------------------------------------------
    // GET /usuarios/novo
    // ----------------------------------------------------------
    public function showCriarUsuario(): void
    {
        $this->requireAuth('admin');
        $csrfToken = CsrfService::generate();
        require ROOT . '/templates/usuarios/novo.php';
    }

    // ----------------------------------------------------------
    // POST /usuarios
    // ----------------------------------------------------------
    public function criarUsuario(): void
    {
        $this->requireAuth('admin');
        CsrfService::validate($_POST['_csrf'] ?? '');

        $nome   = trim(filter_input(INPUT_POST, 'nome',   FILTER_SANITIZE_SPECIAL_CHARS));
        $email  = trim(filter_input(INPUT_POST, 'email',  FILTER_SANITIZE_EMAIL));
        $senha  = $_POST['senha']   ?? '';
        $conf   = $_POST['conf']    ?? '';
        $perfil = $_POST['perfil']  ?? 'secretaria';

        $erros = [];

        if (strlen($nome) < 3)      $erros[] = 'Nome deve ter ao menos 3 caracteres.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
        if (!$this->validarSenha($senha, $erros)) { /* adicionado em validarSenha */ }
        if ($senha !== $conf)       $erros[] = 'As senhas não conferem.';
        if (!in_array($perfil, ['admin', 'instrutor', 'secretaria'])) $erros[] = 'Perfil inválido.';

        $pdo = Database::getConnection();

        // Verifica duplicidade
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
        $existe->execute([$email]);
        if ($existe->fetch()) {
            $erros[] = 'Este e-mail já está cadastrado.';
        }

        if (!empty($erros)) {
            $_SESSION['flash_erros'] = $erros;
            header('Location: /usuarios/novo');
            exit;
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nome, $email, $hash, $perfil]);

        $_SESSION['flash_ok'] = 'Usuário criado com sucesso.';
        header('Location: /usuarios');
        exit;
    }

    // ----------------------------------------------------------
    // Helpers internos
    // ----------------------------------------------------------
    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['login_em'])
            && (time() - $_SESSION['login_em']) < SESSION_LIFETIME;
    }

    public function requireAuth(string $perfil = null): void
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login');
            exit;
        }
        if ($perfil && $_SESSION['user_perfil'] !== $perfil) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }
    }

    private function validarSenha(string $senha, array &$erros): bool
    {
        $ok = true;
        if (strlen($senha) < 8)                      { $erros[] = 'Senha com mínimo 8 caracteres.'; $ok = false; }
        if (!preg_match('/[A-Z]/', $senha))           { $erros[] = 'Senha precisa de ao menos uma letra maiúscula.'; $ok = false; }
        if (!preg_match('/[0-9]/', $senha))           { $erros[] = 'Senha precisa de ao menos um número.'; $ok = false; }
        if (!preg_match('/[^A-Za-z0-9]/', $senha))   { $erros[] = 'Senha precisa de ao menos um caractere especial.'; $ok = false; }
        return $ok;
    }

    private function redirectLogin(string $msg): void
    {
        $_SESSION['flash_erro'] = $msg;
        header('Location: /login');
        exit;
    }

    private function logAcesso(?int $userId, ?string $email, string $acao, bool $sucesso): void
    {
        try {
            $ip   = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $pdo  = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO logs_acesso (usuario_id, email, ip, acao, sucesso) VALUES (?,?,?,?,?)");
            $stmt->execute([$userId, $email, $ip, $acao, (int)$sucesso]);
        } catch (\Exception $e) { /* Não travar o fluxo por falha de log */ }
    }

    private function getLogoPath(): ?string
    {
        try {
            $pdo  = Database::getConnection();
            $stmt = $pdo->query("SELECT logo_path FROM usuarios WHERE perfil = 'admin' AND logo_path IS NOT NULL LIMIT 1");
            $row  = $stmt->fetch();
            return $row['logo_path'] ?? null;
        } catch (\Exception $e) { return null; }
    }
}
