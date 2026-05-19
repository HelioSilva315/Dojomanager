<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));

require ROOT . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'])->notEmpty();

require ROOT . '/config/config.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isset($_SERVER['HTTPS']) ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name(SESSION_NAME);
session_start();

if (isset($_SESSION['login_em']) && (time() - $_SESSION['login_em']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['login_em'] = time();
}

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

use DojoManager\Middleware\TenantMiddleware;
use DojoManager\Controllers\{AuthController, AlunoController, DashboardController, SuperAdminController};

TenantMiddleware::resolve();

$method = $_SERVER['REQUEST_METHOD'];
$uri    = strtok($_SERVER['REQUEST_URI'], '?');
$uri    = rtrim($uri, '/') ?: '/';

$auth = new AuthController();

// Autenticação
if ($method === 'GET'  && $uri === '/login')  { $auth->showLogin();    exit; }
if ($method === 'POST' && $uri === '/login')  { $auth->processLogin(); exit; }
if ($method === 'GET'  && $uri === '/logout') { $auth->logout();       exit; }

// Super Admin
if (str_starts_with($uri, '/superadmin')) {
    $sa = new SuperAdminController();
    if ($method === 'GET'  && $uri === '/superadmin')                 { $sa->dashboard();       exit; }
    if ($method === 'GET'  && $uri === '/superadmin/academias/nova')  { $sa->showNovaAcademia(); exit; }
    if ($method === 'POST' && $uri === '/superadmin/academias')       { $sa->criarAcademia();   exit; }
    if (preg_match('#^/superadmin/academias/(\d+)$#', $uri, $m)) {
        if ($method === 'GET') { $sa->detalheAcademia((int)$m[1]); exit; }
    }
    if (preg_match('#^/superadmin/academias/(\d+)/toggle$#', $uri, $m)) {
        if ($method === 'POST') { $sa->toggleAcademia((int)$m[1]); exit; }
    }
    if (preg_match('#^/superadmin/academias/(\d+)/plano$#', $uri, $m)) {
        if ($method === 'POST') { $sa->alterarPlano((int)$m[1]); exit; }
    }
    http_response_code(404); require ROOT . '/templates/errors/404.php'; exit;
}

// Área da academia — exige tenant resolvido
TenantMiddleware::requireTenant();

$aluno = new AlunoController();

// Rotas dinâmicas
if ($method === 'GET'  && preg_match('#^/api/cep/([\d\-]+)$#', $uri, $m))      { $aluno->apiCep($m[1]); exit; }
if ($method === 'POST' && preg_match('#^/alunos/(\d+)/graduacao$#',   $uri, $m)) { $aluno->atualizarGraduacao((int)$m[1]); exit; }
if ($method === 'POST' && preg_match('#^/alunos/(\d+)/responsavel$#', $uri, $m)) { (new \DojoManager\Controllers\ResponsavelController())->salvar((int)$m[1]); exit; }
if ($method === 'POST' && preg_match('#^/alunos/(\d+)/clinico$#',     $uri, $m)) { (new \DojoManager\Controllers\ClinicoController())->salvar((int)$m[1]); exit; }
if ($method === 'GET'  && preg_match('#^/alunos/(\d+)$#',             $uri, $m)) { $aluno->detalhe((int)$m[1]); exit; }

// Rotas estáticas
$rotas = [
    'GET' => [
        '/'              => fn() => header('Location: /dashboard'),
        '/dashboard'     => fn() => (new DashboardController())->index(),
        '/alunos'        => fn() => $aluno->index(),
        '/alunos/novo'   => fn() => $aluno->showCadastro(),
        '/usuarios'      => fn() => $auth->listarUsuarios(),
        '/usuarios/novo' => fn() => $auth->showCriarUsuario(),
        '/modalidades'   => fn() => (new \DojoManager\Controllers\ModalidadeController())->index(),
        '/configuracoes' => fn() => (new \DojoManager\Controllers\ConfigController())->index(),
    ],
    'POST' => [
        '/alunos'             => fn() => $aluno->salvar(),
        '/usuarios'           => fn() => $auth->criarUsuario(),
        '/modalidades'        => fn() => (new \DojoManager\Controllers\ModalidadeController())->salvar(),
        '/configuracoes/logo' => fn() => (new \DojoManager\Controllers\ConfigController())->uploadLogo(),
    ],
];

if (isset($rotas[$method][$uri])) {
    ($rotas[$method][$uri])();
} else {
    http_response_code(404);
    require ROOT . '/templates/errors/404.php';
}
