<?php
// =============================================================
// src/Controllers/AuthController.php — Multi-Tenant v2
// Login com isolamento por academia
// =============================================================

namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Middleware\TenantMiddleware;
use DojoManager\Services\CsrfService;

class AuthController
{
    public function showLogin(): void
    {
        if ($this->isAuthenticated()) {
            $this->redirectAposLogin();
        }
        $csrfToken  = CsrfService::generate();
        $academia   = TenantMiddleware::get();
        $logoPath   = $academia['logo_path'] ?? null;
        $nomeTenant = TenantMiddleware::nome();
        require ROOT . '/templates/auth/login.php';
    }

    public function processLogin(): void
    {
        CsrfService::validate($_POST['_csrf'] ?? '');

        $email      = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $senha      = $_POST['senha'] ?? '';
        $academiaId = TenantMiddleware::id();

        if (empty($email) || empty($senha)) {
            $this->redirectLogin('Preencha e-mail e senha.');
        }

        $pdo = Database::getConnection();

        if ($academiaId) {
            $stmt = $pdo->prepare("SELECT u.id, u.nome, u.senha_hash, u.perfil, u.ativo, u.academia_id FROM usuarios u WHERE u.email = ? AND u.academia_id = ? LIMIT 1");
            $stmt->execute([$email, $academiaId]);
        } else {
            $stmt = $pdo->prepare("SELECT u.id, u.nome, u.senha_hash, u.perfil, u.ativo, u.academia_id FROM usuarios u WHERE u.email = ? AND u.perfil = 'superadmin' LIMIT 1");
            $stmt->execute([$email]);
        }

        $user = $stmt->fetch();
        $hash = $user['senha_hash'] ?? '$2y$12$invalido000000000000000000000000000000000000000000000';
        $ok   = $user && password_verify($senha, $hash) && (bool)$user['ativo'];

        $this->logAcesso($academiaId, $user['id'] ?? null, $email, $ok ? 'login_ok' : 'login_falha', $ok);

        if (!$ok) {
            $this->redirectLogin('E-mail ou senha incorretos.');
        }

        if (password_needs_rehash($user['senha_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $novoHash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?")->execute([$novoHash, $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_nome']   = $user['nome'];
        $_SESSION['user_perfil'] = $user['perfil'];
        $_SESSION['login_em']    = time();

        if ($user['perfil'] !== 'superadmin' && $user['academia_id']) {
            $this->carregarContextoAcademia((int)$user['academia_id']);
        }

        $this->redirectAposLogin();
    }

    public function logout(): void
    {
        $this->logAcesso($_SESSION['academia_id'] ?? null, $_SESSION['user_id'] ?? null, null, 'logout', true);
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        header('Location: /login');
        exit;
    }

    public function showCriarUsuario(): void
    {
        $this->requireAuth('admin');
        $this->verificarLimiteUsuarios();
        $csrfToken = CsrfService::generate();
        require ROOT . '/templates/usuarios/novo.php';
    }

    public function criarUsuario(): void
    {
        $this->requireAuth('admin');
        CsrfService::validate($_POST['_csrf'] ?? '');
        $this->verificarLimiteUsuarios();

        $nome   = trim(filter_input(INPUT_POST, 'nome',  FILTER_SANITIZE_SPECIAL_CHARS));
        $email  = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $senha  = $_POST['senha']  ?? '';
        $conf   = $_POST['conf']   ?? '';
        $perfil = $_POST['perfil'] ?? 'secretaria';

        $erros = [];
        if (strlen($nome) < 3)                          $erros[] = 'Nome deve ter ao menos 3 caracteres.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail inválido.';
        $this->validarSenha($senha, $erros);
        if ($senha !== $conf)                           $erros[] = 'As senhas não conferem.';
        if (!in_array($perfil, ['admin','instrutor','secretaria'])) $erros[] = 'Perfil inválido.';

        $pdo = Database::getConnection();
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $existe->execute([$email]);
        if ($existe->fetch()) $erros[] = 'Este e-mail já está cadastrado.';

        if (!empty($erros)) {
            $_SESSION['flash_erros'] = $erros;
            header('Location: /usuarios/novo');
            exit;
        }

        $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare("INSERT INTO usuarios (academia_id, nome, email, senha_hash, perfil) VALUES (?,?,?,?,?)")
            ->execute([$_SESSION['academia_id'], $nome, $email, $hash, $perfil]);

        $_SESSION['flash_ok'] = 'Usuário criado com sucesso.';
        header('Location: /usuarios');
        exit;
    }

    public function listarUsuarios(): void
    {
        $this->requireAuth('admin');
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("SELECT id, nome, email, perfil, ativo, criado_em FROM usuarios WHERE academia_id = ? ORDER BY nome");
        $stmt->execute([$_SESSION['academia_id']]);
        $usuarios  = $stmt->fetchAll();
        $csrfToken = CsrfService::generate();
        require ROOT . '/templates/usuarios/index.php';
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id'], $_SESSION['login_em'])
            && (time() - $_SESSION['login_em']) < SESSION_LIFETIME;
    }

    public function requireAuth(?string $perfil = null): void
    {
        if (!$this->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $hierarquia = ['secretaria' => 1, 'instrutor' => 2, 'admin' => 3, 'superadmin' => 4];

        if ($perfil !== null) {
            $nivelRequerido = $hierarquia[$perfil] ?? 99;
            $nivelUsuario   = $hierarquia[$_SESSION['user_perfil']] ?? 0;
            if ($nivelUsuario < $nivelRequerido) {
                http_response_code(403);
                require ROOT . '/templates/errors/403.php';
                exit;
            }
        }
    }

    private function carregarContextoAcademia(int $academiaId): void
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("SELECT a.id, a.nome, a.slug, a.logo_path, a.plano_id, p.max_alunos, p.max_usuarios FROM academias a JOIN planos p ON p.id = a.plano_id WHERE a.id = ?");
        $stmt->execute([$academiaId]);
        $ac = $stmt->fetch();
        if ($ac) {
            $_SESSION['academia_id']         = $ac['id'];
            $_SESSION['academia_nome']       = $ac['nome'];
            $_SESSION['academia_slug']       = $ac['slug'];
            $_SESSION['academia_logo']       = $ac['logo_path'];
            $_SESSION['academia_plano_id']   = $ac['plano_id'];
            $_SESSION['academia_max_alunos'] = $ac['max_alunos'];
            $_SESSION['academia_max_users']  = $ac['max_usuarios'];
        }
    }

    private function verificarLimiteUsuarios(): void
    {
        $max  = (int)($_SESSION['academia_max_users'] ?? 3);
        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE academia_id = ? AND ativo = 1");
        $stmt->execute([$_SESSION['academia_id']]);
        if ((int)$stmt->fetchColumn() >= $max) {
            $_SESSION['flash_erros'] = ["Limite de {$max} usuários atingido. Faça upgrade do plano."];
            header('Location: /usuarios');
            exit;
        }
    }

    private function validarSenha(string $senha, array &$erros): void
    {
        if (strlen($senha) < 8)                    $erros[] = 'Senha com mínimo 8 caracteres.';
        if (!preg_match('/[A-Z]/', $senha))         $erros[] = 'Ao menos uma letra maiúscula.';
        if (!preg_match('/[0-9]/', $senha))         $erros[] = 'Ao menos um número.';
        if (!preg_match('/[^A-Za-z0-9]/', $senha)) $erros[] = 'Ao menos um caractere especial.';
    }

    private function redirectLogin(string $msg): void
    {
        $_SESSION['flash_erro'] = $msg;
        header('Location: /login');
        exit;
    }

    private function redirectAposLogin(): void
    {
        $destino = match ($_SESSION['user_perfil'] ?? '') {
            'superadmin' => '/superadmin',
            default      => '/dashboard',
        };
        header("Location: {$destino}");
        exit;
    }

    private function logAcesso(?int $academiaId, ?int $userId, ?string $email, string $acao, bool $sucesso): void
    {
        try {
            $pdo = Database::getConnection();
            $pdo->prepare("INSERT INTO logs_acesso (academia_id, usuario_id, email, ip, acao, sucesso) VALUES (?,?,?,?,?,?)")
                ->execute([$academiaId, $userId, $email, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $acao, (int)$sucesso]);
        } catch (\Exception $e) {}
    }
}
