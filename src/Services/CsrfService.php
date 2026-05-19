<?php
// src/Services/CsrfService.php

namespace DojoManager\Services;

class CsrfService
{
    public static function generate(): string
    {
        if (empty($_SESSION['csrf_token']) || self::isExpired()) {
            $_SESSION['csrf_token']    = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_ts'] = time();
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate(string $token): void
    {
        if (
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $token) ||
            self::isExpired()
        ) {
            http_response_code(419);
            die('Token de segurança inválido ou expirado. <a href="javascript:history.back()">Voltar</a>');
        }
        // Rotaciona após uso
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_ts']);
    }

    private static function isExpired(): bool
    {
        return isset($_SESSION['csrf_token_ts']) &&
               (time() - $_SESSION['csrf_token_ts']) > CSRF_LIFETIME;
    }
}
