<?php
namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Services\CsrfService;

class ModalidadeController
{
    public function index(): void
    {
        (new AuthController())->requireAuth();
        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();

        $modalidades = $pdo->query("
            SELECT m.id, m.nome, m.ativo,
                   (SELECT COUNT(*) FROM alunos a WHERE a.modalidade_id = m.id AND a.ativo = 1) AS total_alunos,
                   (SELECT COUNT(*) FROM faixas f WHERE f.modalidade_id = m.id) AS total_faixas
            FROM modalidades m WHERE m.academia_id = {$id} ORDER BY m.nome
        ")->fetchAll();

        $historico = $pdo->query("
            SELECT h.registrado_em, al.nome AS aluno,
                   fn.nome AS faixa_nova, gn.grau AS grau_novo,
                   fa.nome AS faixa_ant,  ga.grau AS grau_ant,
                   u.nome  AS usuario
            FROM historico_graduacoes h
            JOIN alunos    al ON al.id = h.aluno_id
            JOIN faixas    fn ON fn.id = h.faixa_nova_id
            JOIN graduacoes gn ON gn.id = h.graduacao_nova_id
            LEFT JOIN faixas    fa ON fa.id = h.faixa_anterior_id
            LEFT JOIN graduacoes ga ON ga.id = h.graduacao_anterior_id
            JOIN usuarios  u  ON u.id  = h.usuario_id
            WHERE h.academia_id = {$id}
            ORDER BY h.registrado_em DESC LIMIT 20
        ")->fetchAll();

        $csrfToken = CsrfService::generate();
        require ROOT . '/templates/modalidades/index.php';
    }

    public function salvar(): void
    {
        (new AuthController())->requireAuth('admin');
        CsrfService::validate($_POST['_csrf'] ?? '');

        $nome = trim(filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_SPECIAL_CHARS));
        $id   = (int)$_SESSION['academia_id'];

        if (strlen($nome) < 2) {
            $_SESSION['flash_erros'] = ['Nome da modalidade inválido.'];
            header('Location: /modalidades');
            exit;
        }

        Database::getConnection()
            ->prepare("INSERT INTO modalidades (academia_id, nome) VALUES (?, ?)")
            ->execute([$id, $nome]);

        $_SESSION['flash_ok'] = "Modalidade '{$nome}' criada.";
        header('Location: /modalidades');
        exit;
    }
}
