<?php
// src/Controllers/DashboardController.php

namespace DojoManager\Controllers;

use DojoManager\Database;

class DashboardController
{
    public function index(): void
    {
        (new AuthController())->requireAuth();

        $pdo = Database::getConnection();

        $stats = [
            'total_alunos'    => (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo = 1")->fetchColumn(),
            'maiores'         => (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo=1 AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) >= 18")->fetchColumn(),
            'menores'         => (int) $pdo->query("SELECT COUNT(*) FROM alunos WHERE ativo=1 AND TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) < 18")->fetchColumn(),
            'total_modalidades' => (int) $pdo->query("SELECT COUNT(*) FROM modalidades WHERE ativo = 1")->fetchColumn(),
        ];

        $porModalidade = $pdo->query("
            SELECT m.nome, COUNT(a.id) AS total
            FROM modalidades m
            LEFT JOIN alunos a ON a.modalidade_id = m.id AND a.ativo = 1
            WHERE m.ativo = 1
            GROUP BY m.id, m.nome
            ORDER BY total DESC
        ")->fetchAll();

        $ultimosCadastros = $pdo->query("
            SELECT a.id, a.nome, a.data_cadastro,
                   m.nome AS modalidade,
                   f.nome AS faixa, f.cor_hex,
                   TIMESTAMPDIFF(YEAR, a.data_nascimento, CURDATE()) AS idade
            FROM alunos a
            LEFT JOIN modalidades m ON m.id = a.modalidade_id
            LEFT JOIN faixas      f ON f.id = a.faixa_id
            ORDER BY a.criado_em DESC
            LIMIT 8
        ")->fetchAll();

        require ROOT . '/templates/dashboard/index.php';
    }
}
