<?php
// =============================================================
// src/Middleware/TenantMiddleware.php
// Identifica a academia pelo subdomínio ou slug da URL
// Ex: tigre.seudominio.com.br → academia "tigre"
// =============================================================

namespace DojoManager\Middleware;

use DojoManager\Database;

class TenantMiddleware
{
    private static ?array $academia = null;

    /**
     * Resolve a academia atual a partir do subdomínio ou query string
     * Armazena na sessão para não bater no banco a cada request
     */
    public static function resolve(): void
    {
        // Super admin não tem tenant
        if (isset($_SESSION['user_perfil']) && $_SESSION['user_perfil'] === 'superadmin') {
            return;
        }

        // Já está na sessão?
        if (!empty($_SESSION['academia_id'])) {
            self::$academia = [
                'id'       => $_SESSION['academia_id'],
                'nome'     => $_SESSION['academia_nome'],
                'slug'     => $_SESSION['academia_slug'],
                'logo'     => $_SESSION['academia_logo'] ?? null,
                'plano_id' => $_SESSION['academia_plano_id'],
            ];
            return;
        }

        $slug = self::resolverSlug();

        if (!$slug) {
            // Domínio principal sem slug = página de cadastro/landing
            return;
        }

        $pdo  = Database::getConnection();
        $stmt = $pdo->prepare("
            SELECT a.id, a.nome, a.slug, a.logo_path, a.ativo, a.plano_id,
                   p.max_alunos, p.max_usuarios, p.nome AS plano_nome,
                   a.plano_expira_em
            FROM academias a
            JOIN planos p ON p.id = a.plano_id
            WHERE a.slug = ? LIMIT 1
        ");
        $stmt->execute([$slug]);
        $academia = $stmt->fetch();

        if (!$academia) {
            self::abort(404, "Academia '{$slug}' não encontrada.");
        }

        if (!$academia['ativo']) {
            self::abort(403, "Esta academia está suspensa. Entre em contato com o suporte.");
        }

        // Verifica expiração do plano
        if ($academia['plano_expira_em'] && $academia['plano_expira_em'] < date('Y-m-d')) {
            self::abort(402, "Plano expirado. Acesse o painel para renovar.");
        }

        self::$academia = $academia;

        // Persiste na sessão
        $_SESSION['academia_id']       = $academia['id'];
        $_SESSION['academia_nome']     = $academia['nome'];
        $_SESSION['academia_slug']     = $academia['slug'];
        $_SESSION['academia_logo']     = $academia['logo_path'];
        $_SESSION['academia_plano_id'] = $academia['plano_id'];
    }

    public static function get(): ?array
    {
        return self::$academia;
    }

    public static function id(): ?int
    {
        return self::$academia['id'] ?? null;
    }

    public static function nome(): string
    {
        return self::$academia['nome'] ?? APP_NAME;
    }

    public static function logo(): ?string
    {
        return self::$academia['logo_path'] ?? null;
    }

    public static function planoId(): int
    {
        return (int)(self::$academia['plano_id'] ?? 1);
    }

    public static function maxAlunos(): int
    {
        return (int)(self::$academia['max_alunos'] ?? 50);
    }

    public static function maxUsuarios(): int
    {
        return (int)(self::$academia['max_usuarios'] ?? 2);
    }

    /**
     * Garante que a requisição atual pertence a uma academia válida
     */
    public static function requireTenant(): void
    {
        if (!self::id()) {
            header('Location: /');
            exit;
        }
    }

    // ----------------------------------------------------------
    // Resolve o slug pelo subdomínio ou parâmetro de URL
    // ----------------------------------------------------------
    private static function resolverSlug(): ?string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // Modo subdomínio: tigre.seudominio.com.br
        $dominioBase = $_ENV['APP_DOMAIN'] ?? '';
        if ($dominioBase && str_ends_with($host, '.' . $dominioBase)) {
            $slug = str_replace('.' . $dominioBase, '', $host);
            if ($slug && $slug !== 'www' && $slug !== 'superadmin') {
                return $slug;
            }
        }

        // Modo path: seudominio.com.br/a/tigre/...
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (preg_match('#^/a/([a-z0-9\-]+)#', $uri, $m)) {
            return $m[1];
        }

        // Slug na sessão (após login)
        return $_SESSION['academia_slug'] ?? null;
    }

    private static function abort(int $code, string $msg): void
    {
        http_response_code($code);
        echo "<h2>{$msg}</h2><p><a href='/'>Voltar</a></p>";
        exit;
    }
}
