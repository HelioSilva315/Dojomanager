<?php
namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Services\CsrfService;

// =============================================================
// ResponsavelController
// =============================================================
class ResponsavelController
{
    public function salvar(int $alunoId): void
    {
        (new AuthController())->requireAuth();
        CsrfService::validate($_POST['_csrf'] ?? '');

        $academiaId = (int)$_SESSION['academia_id'];
        $pdo        = Database::getConnection();

        // Garante que o aluno pertence a esta academia
        $stmt = $pdo->prepare("SELECT id FROM alunos WHERE id = ? AND academia_id = ?");
        $stmt->execute([$alunoId, $academiaId]);
        if (!$stmt->fetch()) { http_response_code(403); exit; }

        $str = fn($k) => trim(filter_var($_POST[$k] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));

        $nome  = $str('nome');
        $cpf   = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
        $tel   = $str('telefone');
        $cep   = preg_replace('/\D/', '', $_POST['cep'] ?? '');
        $end   = $str('endereco');
        $bairro= $str('bairro');
        $cidade= $str('cidade');
        $uf    = strtoupper(substr($str('uf'), 0, 2));

        $erros = [];
        if (strlen($nome) < 3)  $erros[] = 'Nome do responsável inválido.';
        if (strlen($cpf) !== 11) $erros[] = 'CPF do responsável inválido.';
        if (empty($tel))         $erros[] = 'Telefone do responsável obrigatório.';

        if (!empty($erros)) {
            $_SESSION['flash_erros'] = $erros;
            header("Location: /alunos/{$alunoId}");
            exit;
        }

        // Upsert
        $pdo->prepare("
            INSERT INTO responsaveis (aluno_id, nome, cpf, rg, rg_orgao_expedidor, cep, endereco, bairro, cidade, uf, telefone)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE nome=VALUES(nome), cpf=VALUES(cpf), telefone=VALUES(telefone),
                cep=VALUES(cep), endereco=VALUES(endereco), bairro=VALUES(bairro), cidade=VALUES(cidade), uf=VALUES(uf)
        ")->execute([
            $alunoId, $nome, $cpf,
            $str('rg') ?: null, $str('rg_orgao_expedidor') ?: null,
            $cep, $end, $bairro, $cidade, $uf, $tel
        ]);

        $_SESSION['flash_ok'] = 'Responsável salvo com sucesso.';
        header("Location: /alunos/{$alunoId}/clinico");
        exit;
    }
}

// =============================================================
// ClinicoController
// =============================================================
class ClinicoController
{
    public function salvar(int $alunoId): void
    {
        (new AuthController())->requireAuth();
        CsrfService::validate($_POST['_csrf'] ?? '');

        $academiaId = (int)$_SESSION['academia_id'];
        $pdo        = Database::getConnection();

        $stmt = $pdo->prepare("SELECT id FROM alunos WHERE id = ? AND academia_id = ?");
        $stmt->execute([$alunoId, $academiaId]);
        if (!$stmt->fetch()) { http_response_code(403); exit; }

        $bool = fn($k) => isset($_POST[$k]) && $_POST[$k] === 'sim' ? 1 : 0;
        $str  = fn($k) => trim(filter_var($_POST[$k] ?? '', FILTER_SANITIZE_SPECIAL_CHARS)) ?: null;

        $pdo->prepare("
            INSERT INTO condicoes_clinicas
                (aluno_id, tem_condicao_medica, condicao_medica_desc,
                 toma_medicamento, medicamento_desc,
                 possui_alergia, alergia_desc,
                 possui_lesao_cirurgica, lesao_cirurgica_desc)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                tem_condicao_medica=VALUES(tem_condicao_medica), condicao_medica_desc=VALUES(condicao_medica_desc),
                toma_medicamento=VALUES(toma_medicamento),       medicamento_desc=VALUES(medicamento_desc),
                possui_alergia=VALUES(possui_alergia),           alergia_desc=VALUES(alergia_desc),
                possui_lesao_cirurgica=VALUES(possui_lesao_cirurgica), lesao_cirurgica_desc=VALUES(lesao_cirurgica_desc)
        ")->execute([
            $alunoId,
            $bool('tem_condicao_medica'), $str('condicao_medica_desc'),
            $bool('toma_medicamento'),    $str('medicamento_desc'),
            $bool('possui_alergia'),      $str('alergia_desc'),
            $bool('possui_lesao_cirurgica'), $str('lesao_cirurgica_desc'),
        ]);

        $_SESSION['flash_ok'] = 'Condição clínica salva com sucesso.';
        header("Location: /alunos/{$alunoId}");
        exit;
    }
}
