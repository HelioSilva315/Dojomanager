<?php
// =============================================================
// public/index.php — Front Controller / Roteador
// Ponto de entrada único da aplicação
// =============================================================

declare(strict_types=1);

define('ROOT', dirname(__DIR__));

// Carrega .env antes de tudo (vlucas/phpdotenv via Composer)
require ROOT . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(ROOT);
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'])->notEmpty();

require ROOT . '/config/config.php';

// Sessão segura
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure',   isset($_SERVER['HTTPS']) ? '1' : '0');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_samesite', 'Lax');
session_name(SESSION_NAME);
session_start();

// Renova ID de sessão a cada 30 min para prevenir fixation
if (isset($_SESSION['login_em']) && (time() - $_SESSION['login_em']) > 1800) {
    session_regenerate_id(true);
    $_SESSION['login_em'] = time();
}

// Headers de segurança
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-" . base64_encode(random_bytes(16)) . "'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");

// Roteamento simples
$method = $_SERVER['REQUEST_METHOD'];
$uri    = strtok($_SERVER['REQUEST_URI'], '?');
$uri    = rtrim($uri, '/') ?: '/';

use DojoManager\Controllers\{AuthController, AlunoController, DashboardController, ModalidadeController};

$auth  = new AuthController();
$aluno = new AlunoController();

$routes = [
    'GET'  => [
        '/'                          => fn() => header('Location: /dashboard'),
        '/login'                     => fn() => $auth->showLogin(),
        '/logout'                    => fn() => $auth->logout(),
        '/dashboard'                 => fn() => (new DashboardController())->index(),
        '/alunos'                    => fn() => $aluno->index(),
        '/alunos/novo'               => fn() => $aluno->showCadastro(),
        '/modalidades'               => fn() => (new ModalidadeController())->index(),
        '/usuarios'                  => fn() => $auth->showUsuarios(),
        '/usuarios/novo'             => fn() => $auth->showCriarUsuario(),
    ],
    'POST' => [
        '/login'                     => fn() => $auth->processLogin(),
        '/alunos'                    => fn() => $aluno->salvar(),
        '/usuarios'                  => fn() => $auth->criarUsuario(),
    ],
];

// Rotas dinâmicas (com parâmetros)
if ($method === 'GET' && preg_match('#^/api/cep/(\d{5}-?\d{3})$#', $uri, $m)) {
    $aluno->apiCep($m[1]); exit;
}
if ($method === 'POST' && preg_match('#^/alunos/(\d+)/graduacao$#', $uri, $m)) {
    $aluno->atualizarGraduacao((int)$m[1]); exit;
}
if ($method === 'POST' && preg_match('#^/alunos/(\d+)/responsavel$#', $uri, $m)) {
    (new \DojoManager\Controllers\ResponsavelController())->salvar((int)$m[1]); exit;
}
if ($method === 'POST' && preg_match('#^/alunos/(\d+)/clinico$#', $uri, $m)) {
    (new \DojoManager\Controllers\ClinicoController())->salvar((int)$m[1]); exit;
}

// Despacha rota estática
if (isset($routes[$method][$uri])) {
    ($routes[$method][$uri])();
} else {
    http_response_code(404);
    require ROOT . '/templates/errors/404.php';
}
