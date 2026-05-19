<?php
// =============================================================
// config/config.php — Configurações gerais (sem credenciais!)
// Credenciais ficam em .env (nunca commitar no git)
// =============================================================

define('APP_NAME',    'DojoManager');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG',   (bool) ($_ENV['APP_DEBUG'] ?? false));

// Sessão
define('SESSION_LIFETIME', 7200); // 2 horas em segundos
define('SESSION_NAME',     'DOJOSESSID');

// Upload de logo
define('UPLOAD_MAX_SIZE',  2 * 1024 * 1024); // 2MB
define('UPLOAD_ALLOWED',   ['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
define('UPLOAD_PATH',      __DIR__ . '/../public/uploads/');

// API ViaCEP (pública, sem chave)
define('VIACEP_URL', 'https://viacep.com.br/ws/%s/json/');

// CSRF token lifetime (segundos)
define('CSRF_LIFETIME', 3600);
