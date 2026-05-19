<?php
namespace DojoManager\Controllers;

use DojoManager\Database;

class DashboardController
{
    public function index(): void
    {
        (new AuthController())->requireAuth();

        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();

        $q = fn(string $sql) => (int)$pdo->query($sql)->fetchColumn();

        $stats = [
            'total_alunos'      => $q("SELECT COUNT(*) FROM alunos WHERE academia_id={$id} AND ativo=1"),
            'maiores'           => $q("SELECT COUNT(*) FROM alunos WHERE academia_id={$id} AND ativo=1 AND TIMESTAMPDIFF(YEAR,data_nascimento,CURDATE())>=18"),
            'menores'           => $q("SELECT COUNT(*) FROM alunos WHERE academia_id={$id} AND ativo=1 AND TIMESTAMPDIFF(YEAR,data_nascimento,CURDATE())<18"),
            'total_modalidades' => $q("SELECT COUNT(*) FROM modalidades WHERE academia_id={$id} AND ativo=1"),
            'max_alunos'        => (int)($_SESSION['academia_max_alunos'] ?? 999),
        ];

        $porModalidade = $pdo->query("
            SELECT m.nome, COUNT(a.id) AS total
            FROM modalidades m
            LEFT JOIN alunos a ON a.modalidade_id = m.id AND a.ativo = 1
            WHERE m.academia_id = {$id} AND m.ativo = 1
            GROUP BY m.id, m.nome ORDER BY total DESC
        ")->fetchAll();

        $ultimosCadastros = $pdo->query("
            SELECT a.id, a.nome, a.data_cadastro,
                   m.nome AS modalidade, f.nome AS faixa, f.cor_hex,
                   TIMESTAMPDIFF(YEAR, a.data_nascimento, CURDATE()) AS idade
            FROM alunos a
            LEFT JOIN modalidades m ON m.id = a.modalidade_id
            LEFT JOIN faixas      f ON f.id = a.faixa_id
            WHERE a.academia_id = {$id}
            ORDER BY a.criado_em DESC LIMIT 8
        ")->fetchAll();

        require ROOT . '/templates/dashboard/index.php';
    }
}
