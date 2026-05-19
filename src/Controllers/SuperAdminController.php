<?php
// =============================================================
// src/Controllers/SuperAdminController.php
// Painel global — gerencia academias, planos e super-admins
// Acessível apenas por perfil 'superadmin'
// =============================================================

namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Services\CsrfService;

class SuperAdminController
{
    public function __construct()
    {
        (new AuthController())->requireAuth('superadmin');
    }

    // ----------------------------------------------------------
    // GET /superadmin — Dashboard global
    // ----------------------------------------------------------
    public function dashboard(): void
    {
        $pdo = Database::getConnection();

        $stats = [
            'total_academias'  => (int) $pdo->query("SELECT COUNT(*) FROM academias")->fetchColumn(),
            'academias_ativas' => (int) $pdo->query("SELECT COUNT(*) FROM academias WHERE ativo = 1")->fetchColumn(),
            'total_alunos'     => (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo = 1")->fetchColumn(),
            'total_usuarios'   => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE perfil != 'superadmin'")->fetchColumn(),
            'mrr'              => (float) $pdo->query("
                SELECT COALESCE(SUM(p.preco_mensal), 0)
                FROM academias a JOIN planos p ON p.id = a.plano_id
                WHERE a.ativo = 1
            ")->fetchColumn(),
        ];

        $academias = $pdo->query("
            SELECT a.id, a.nome, a.slug, a.email, a.ativo, a.criado_em,
                   p.nome AS plano_nome, p.preco_mensal,
                   a.plano_expira_em,
                   (SELECT COUNT(*) FROM alunos al WHERE al.academia_id = a.id AND al.ativo = 1) AS total_alunos,
                   (SELECT COUNT(*) FROM usuarios u WHERE u.academia_id = a.id AND u.ativo = 1) AS total_usuarios
            FROM academias a
            JOIN planos p ON p.id = a.plano_id
            ORDER BY a.criado_em DESC
        ")->fetchAll();

        $planos = $pdo->query("SELECT * FROM planos ORDER BY preco_mensal")->fetchAll();

        require ROOT . '/templates/superadmin/dashboard.php';
    }

    // ----------------------------------------------------------
    // GET /superadmin/academias/nova
    // ----------------------------------------------------------
    public function showNovaAcademia(): void
    {
        $planos    = Database::getConnection()->query("SELECT * FROM planos WHERE ativo=1 ORDER BY preco_mensal")->fetchAll();
        $csrfToken = CsrfService::generate();
        require ROOT . '/templates/superadmin/nova_academia.php';
    }

    // ----------------------------------------------------------
    // POST /superadmin/academias
    // Cria academia + usuário admin + modalidades padrão
    // ----------------------------------------------------------
    public function criarAcademia(): void
    {
        CsrfService::validate($_POST['_csrf'] ?? '');

        $nome     = trim(filter_input(INPUT_POST, 'nome',  FILTER_SANITIZE_SPECIAL_CHARS));
        $email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));
        $slug     = $this->gerarSlug($nome);
        $planoId  = (int)($_POST['plano_id'] ?? 1);
        $adminNome= trim(filter_input(INPUT_POST, 'admin_nome',  FILTER_SANITIZE_SPECIAL_CHARS));
        $adminEmail= trim(filter_input(INPUT_POST, 'admin_email', FILTER_SANITIZE_EMAIL));
        $adminSenha= $_POST['admin_senha'] ?? '';

        $erros = [];
        if (strlen($nome) < 3)                         $erros[] = 'Nome da academia inválido.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail da academia inválido.';
        if (strlen($adminNome) < 3)                    $erros[] = 'Nome do administrador inválido.';
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) $erros[] = 'E-mail do administrador inválido.';
        if (strlen($adminSenha) < 8)                   $erros[] = 'Senha do administrador deve ter ao menos 8 caracteres.';

        $pdo = Database::getConnection();

        // Slug único
        $slugBase = $slug;
        $i = 1;
        while ($pdo->prepare("SELECT id FROM academias WHERE slug = ?")->execute([$slug]) &&
               $pdo->prepare("SELECT id FROM academias WHERE slug = ?")->execute([$slug]) &&
               $pdo->query("SELECT COUNT(*) FROM academias WHERE slug = '{$slug}'")->fetchColumn() > 0) {
            $slug = $slugBase . '-' . $i++;
        }

        // E-mail de admin único
        $existe = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $existe->execute([$adminEmail]);
        if ($existe->fetch()) $erros[] = 'E-mail do administrador já cadastrado.';

        if (!empty($erros)) {
            $_SESSION['flash_erros'] = $erros;
            header('Location: /superadmin/academias/nova');
            exit;
        }

        try {
            $pdo->beginTransaction();

            // Trial de 30 dias
            $expira = date('Y-m-d', strtotime('+30 days'));

            // Cria academia
            $stmt = $pdo->prepare("
                INSERT INTO academias (nome, slug, email, plano_id, plano_expira_em)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nome, $slug, $email, $planoId, $expira]);
            $academiaId = (int)$pdo->lastInsertId();

            // Cria admin da academia
            $hash = password_hash($adminSenha, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("
                INSERT INTO usuarios (academia_id, nome, email, senha_hash, perfil)
                VALUES (?, ?, ?, ?, 'admin')
            ")->execute([$academiaId, $adminNome, $adminEmail, $hash]);

            // Cria assinatura trial
            $pdo->prepare("
                INSERT INTO assinaturas (academia_id, plano_id, status, inicio_em, expira_em)
                VALUES (?, ?, 'trial', CURDATE(), ?)
            ")->execute([$academiaId, $planoId, $expira]);

            // Modalidades padrão
            $modalidades = ['Jiu-Jitsu', 'Muay Thai', 'Karatê', 'Judô', 'Boxe'];
            $stmtMod = $pdo->prepare("INSERT INTO modalidades (academia_id, nome) VALUES (?, ?)");
            foreach ($modalidades as $mod) {
                $stmtMod->execute([$academiaId, $mod]);
            }

            // Faixas padrão do Jiu-Jitsu
            $jiuId = (int)$pdo->lastInsertId() - count($modalidades) + 1;
            // Busca ID do Jiu-Jitsu recém-criado
            $jiuStmt = $pdo->prepare("SELECT id FROM modalidades WHERE academia_id = ? AND nome = 'Jiu-Jitsu'");
            $jiuStmt->execute([$academiaId]);
            $jiuId = (int)$jiuStmt->fetchColumn();

            if ($jiuId) {
                $faixas = [
                    ['Branca', '#f5f5f5', 1], ['Azul', '#3b82f6', 2],
                    ['Roxa', '#7c3aed', 3],   ['Marrom', '#92400e', 4],
                    ['Preta', '#1f2937', 5],
                ];
                $stmtFaixa = $pdo->prepare("INSERT INTO faixas (modalidade_id, nome, cor_hex, ordem) VALUES (?,?,?,?)");
                foreach ($faixas as [$fnome, $fcor, $ford]) {
                    $stmtFaixa->execute([$jiuId, $fnome, $fcor, $ford]);
                    $faixaId = (int)$pdo->lastInsertId();
                    // 4 graus por faixa
                    $stmtGrau = $pdo->prepare("INSERT INTO graduacoes (faixa_id, grau, ordem) VALUES (?,?,?)");
                    for ($g = 1; $g <= 4; $g++) {
                        $stmtGrau->execute([$faixaId, "{$g}º Grau", $g]);
                    }
                }
            }

            $pdo->commit();

            $_SESSION['flash_ok'] = "Academia '{$nome}' criada! Slug: {$slug} | Admin: {$adminEmail}";
            header('Location: /superadmin');
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_erros'] = ['Erro ao criar academia: ' . ($e->getMessage())];
            header('Location: /superadmin/academias/nova');
            exit;
        }
    }

    // ----------------------------------------------------------
    // POST /superadmin/academias/{id}/toggle
    // Ativa ou suspende academia
    // ----------------------------------------------------------
    public function toggleAcademia(int $id): void
    {
        CsrfService::validate($_POST['_csrf'] ?? '');
        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE academias SET ativo = NOT ativo WHERE id = ?")->execute([$id]);
        $_SESSION['flash_ok'] = 'Status da academia atualizado.';
        header('Location: /superadmin');
        exit;
    }

    // ----------------------------------------------------------
    // POST /superadmin/academias/{id}/plano
    // Altera plano e data de expiração
    // ----------------------------------------------------------
    public function alterarPlano(int $id): void
    {
        CsrfService::validate($_POST['_csrf'] ?? '');
        $planoId = (int)($_POST['plano_id'] ?? 1);
        $expira  = $_POST['expira_em'] ?? date('Y-m-d', strtotime('+30 days'));

        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE academias SET plano_id = ?, plano_expira_em = ? WHERE id = ?")
            ->execute([$planoId, $expira, $id]);

        // Atualiza assinatura
        $pdo->prepare("
            UPDATE assinaturas SET plano_id = ?, status = 'ativa', expira_em = ?
            WHERE academia_id = ? ORDER BY id DESC LIMIT 1
        ")->execute([$planoId, $expira, $id]);

        $_SESSION['flash_ok'] = 'Plano atualizado com sucesso.';
        header('Location: /superadmin');
        exit;
    }

    // ----------------------------------------------------------
    // GET /superadmin/academias/{id} — detalhe
    // ----------------------------------------------------------
    public function detalheAcademia(int $id): void
    {
        $pdo = Database::getConnection();
        $academia = $pdo->prepare("
            SELECT a.*, p.nome AS plano_nome, p.max_alunos, p.max_usuarios
            FROM academias a JOIN planos p ON p.id = a.plano_id
            WHERE a.id = ?
        ");
        $academia->execute([$id]);
        $academia = $academia->fetch();

        if (!$academia) { http_response_code(404); echo 'Academia não encontrada'; exit; }

        $usuarios = $pdo->prepare("SELECT id, nome, email, perfil, ativo, criado_em FROM usuarios WHERE academia_id = ? ORDER BY nome")->execute([$id]);
        $usuarios = $pdo->prepare("SELECT id, nome, email, perfil, ativo, criado_em FROM usuarios WHERE academia_id = ? ORDER BY nome");
        $usuarios->execute([$id]);
        $usuarios = $usuarios->fetchAll();

        $totalAlunos = (int)$pdo->prepare("SELECT COUNT(*) FROM alunos WHERE academia_id = ? AND ativo = 1")->execute([$id]);
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE academia_id = ? AND ativo = 1");
        $stmtCount->execute([$id]);
        $totalAlunos = (int)$stmtCount->fetchColumn();

        $planos    = $pdo->query("SELECT * FROM planos WHERE ativo=1 ORDER BY preco_mensal")->fetchAll();
        $csrfToken = CsrfService::generate();

        require ROOT . '/templates/superadmin/detalhe_academia.php';
    }

    // ----------------------------------------------------------
    // Helper
    // ----------------------------------------------------------
    private function gerarSlug(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
            'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n',
        ]);
        $texto = preg_replace('/[^a-z0-9\s\-]/', '', $texto);
        $texto = preg_replace('/[\s\-]+/', '-', trim($texto));
        return substr($texto, 0, 50);
    }
}
