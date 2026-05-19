<?php
// =============================================================
// src/Controllers/AlunoController.php
// Cadastro completo de alunos, responsáveis, condição clínica,
// graduações e histórico
// =============================================================

namespace DojoManager\Controllers;

use DojoManager\Database;
use DojoManager\Services\CsrfService;
use DojoManager\Services\CepService;

class AlunoController
{
    private AuthController $auth;

    public function __construct()
    {
        $this->auth = new AuthController();
    }

    // ----------------------------------------------------------
    // GET /alunos
    // ----------------------------------------------------------
    public function index(): void
    {
        $this->auth->requireAuth();

        $busca      = trim($_GET['busca']       ?? '');
        $modalidade = (int)($_GET['modalidade'] ?? 0);
        $pagina     = max(1, (int)($_GET['p']   ?? 1));
        $porPagina  = 20;
        $offset     = ($pagina - 1) * $porPagina;

        $pdo    = Database::getConnection();
        $where  = ['1=1'];
        $params = [];

        if ($busca) {
            $where[]  = '(a.nome LIKE ? OR a.cpf LIKE ?)';
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
        }
        if ($modalidade) {
            $where[]  = 'a.modalidade_id = ?';
            $params[] = $modalidade;
        }

        $whereStr = implode(' AND ', $where);

        $total = $pdo->prepare("SELECT COUNT(*) FROM alunos a WHERE {$whereStr}");
        $total->execute($params);
        $totalRegistros = (int)$total->fetchColumn();

        $sql = "SELECT a.id, a.nome, a.cpf, a.data_nascimento, a.ativo,
                       m.nome AS modalidade, f.nome AS faixa, f.cor_hex, g.grau
                FROM alunos a
                LEFT JOIN modalidades m ON m.id = a.modalidade_id
                LEFT JOIN faixas      f ON f.id = a.faixa_id
                LEFT JOIN graduacoes  g ON g.id = a.graduacao_id
                WHERE {$whereStr}
                ORDER BY a.nome
                LIMIT {$porPagina} OFFSET {$offset}";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $alunos = $stmt->fetchAll();

        $modalidades = $pdo->query("SELECT id, nome FROM modalidades WHERE ativo = 1 ORDER BY nome")->fetchAll();

        require ROOT . '/templates/alunos/index.php';
    }

    // ----------------------------------------------------------
    // GET /alunos/novo
    // ----------------------------------------------------------
    public function showCadastro(): void
    {
        $this->auth->requireAuth();
        $csrfToken   = CsrfService::generate();
        $modalidades = Database::getConnection()
            ->query("SELECT id, nome FROM modalidades WHERE ativo = 1 ORDER BY nome")
            ->fetchAll();

        require ROOT . '/templates/alunos/cadastro.php';
    }

    // ----------------------------------------------------------
    // POST /alunos
    // ----------------------------------------------------------
    public function salvar(): void
    {
        $this->auth->requireAuth();
        CsrfService::validate($_POST['_csrf'] ?? '');

        $dados = $this->sanitizarAluno($_POST);
        $erros = $this->validarAluno($dados);

        if (!empty($erros)) {
            $_SESSION['flash_erros'] = $erros;
            $_SESSION['form_dados']  = $dados;
            header('Location: /alunos/novo');
            exit;
        }

        $pdo = Database::getConnection();

        // Verifica CPF duplicado
        $stmt = $pdo->prepare("SELECT id FROM alunos WHERE cpf = ? LIMIT 1");
        $stmt->execute([$dados['cpf']]);
        if ($stmt->fetch()) {
            $_SESSION['flash_erros'] = ['CPF já cadastrado no sistema.'];
            $_SESSION['form_dados']  = $dados;
            header('Location: /alunos/novo');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO alunos
                    (nome, data_nascimento, cpf, rg, rg_orgao_expedidor,
                     cep, endereco, bairro, cidade, uf,
                     telefone, peso_kg, contato_emergencia, telefone_emergencia,
                     modalidade_id, faixa_id, graduacao_id)
                VALUES
                    (:nome, :dt_nasc, :cpf, :rg, :orgao,
                     :cep, :end, :bairro, :cidade, :uf,
                     :tel, :peso, :c_emerg, :t_emerg,
                     :modal_id, :faixa_id, :grad_id)
            ");
            $stmt->execute([
                ':nome'      => $dados['nome'],
                ':dt_nasc'   => $this->converterData($dados['data_nascimento']),
                ':cpf'       => $dados['cpf'],
                ':rg'        => $dados['rg']  ?: null,
                ':orgao'     => $dados['rg_orgao_expedidor'] ?: null,
                ':cep'       => preg_replace('/\D/', '', $dados['cep']),
                ':end'       => $dados['endereco'],
                ':bairro'    => $dados['bairro'],
                ':cidade'    => $dados['cidade'],
                ':uf'        => strtoupper($dados['uf']),
                ':tel'       => $dados['telefone']  ?: null,
                ':peso'      => $dados['peso_kg']   ?: null,
                ':c_emerg'   => $dados['contato_emergencia']  ?: null,
                ':t_emerg'   => $dados['telefone_emergencia'] ?: null,
                ':modal_id'  => $dados['modalidade_id'] ?: null,
                ':faixa_id'  => $dados['faixa_id']      ?: null,
                ':grad_id'   => $dados['graduacao_id']  ?: null,
            ]);

            $alunoId = (int)$pdo->lastInsertId();

            // Grava faixa inicial no histórico
            if ($dados['faixa_id'] && $dados['graduacao_id']) {
                $pdo->prepare("
                    INSERT INTO historico_graduacoes
                        (aluno_id, faixa_nova_id, graduacao_nova_id, usuario_id, observacao)
                    VALUES (?, ?, ?, ?, 'Matrícula inicial')
                ")->execute([$alunoId, $dados['faixa_id'], $dados['graduacao_id'], $_SESSION['user_id']]);
            }

            $pdo->commit();

            // Verificação de menor de idade
            $dtNasc = new \DateTime($this->converterData($dados['data_nascimento']));
            $idade  = (int)(new \DateTime())->diff($dtNasc)->y;

            $_SESSION['flash_ok']       = 'Aluno cadastrado com sucesso!';
            $_SESSION['aluno_id_novo']  = $alunoId;
            $_SESSION['aluno_menor']    = ($idade < 18);

            $redirect = ($idade < 18) ? "/alunos/{$alunoId}/responsavel" : "/alunos/{$alunoId}/clinico";
            header("Location: {$redirect}");
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_erros'] = ['Erro ao salvar aluno. Tente novamente.'];
            header('Location: /alunos/novo');
            exit;
        }
    }

    // ----------------------------------------------------------
    // POST /alunos/{id}/graduacao
    // ----------------------------------------------------------
    public function atualizarGraduacao(int $alunoId): void
    {
        $this->auth->requireAuth();
        CsrfService::validate($_POST['_csrf'] ?? '');

        $faixaId    = (int)($_POST['faixa_id']    ?? 0);
        $graduacaoId= (int)($_POST['graduacao_id'] ?? 0);
        $obs        = trim($_POST['observacao'] ?? '');

        if (!$faixaId || !$graduacaoId) {
            $_SESSION['flash_erros'] = ['Selecione faixa e graduação.'];
            header("Location: /alunos/{$alunoId}");
            exit;
        }

        $pdo = Database::getConnection();

        // Busca graduação atual
        $atual = $pdo->prepare("SELECT faixa_id, graduacao_id FROM alunos WHERE id = ?");
        $atual->execute([$alunoId]);
        $row = $atual->fetch();

        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE alunos SET faixa_id = ?, graduacao_id = ? WHERE id = ?")
                ->execute([$faixaId, $graduacaoId, $alunoId]);

            $pdo->prepare("
                INSERT INTO historico_graduacoes
                    (aluno_id, faixa_anterior_id, graduacao_anterior_id, faixa_nova_id, graduacao_nova_id, usuario_id, observacao)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ")->execute([$alunoId, $row['faixa_id'], $row['graduacao_id'], $faixaId, $graduacaoId, $_SESSION['user_id'], $obs ?: null]);

            $pdo->commit();
            $_SESSION['flash_ok'] = 'Graduação atualizada com sucesso!';
        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_erros'] = ['Erro ao atualizar graduação.'];
        }

        header("Location: /alunos/{$alunoId}");
        exit;
    }

    // ----------------------------------------------------------
    // GET /api/cep/{cep} — endpoint JSON para busca de CEP
    // ----------------------------------------------------------
    public function apiCep(string $cep): void
    {
        header('Content-Type: application/json');
        $cepLimpo = preg_replace('/\D/', '', $cep);

        if (strlen($cepLimpo) !== 8) {
            http_response_code(400);
            echo json_encode(['erro' => 'CEP inválido']);
            exit;
        }

        try {
            $dados = CepService::buscar($cepLimpo);
            echo json_encode($dados);
        } catch (\Exception $e) {
            http_response_code(502);
            echo json_encode(['erro' => 'Serviço de CEP indisponível']);
        }
        exit;
    }

    // ----------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------
    private function sanitizarAluno(array $p): array
    {
        $str = fn($k) => trim(filter_var($p[$k] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        return [
            'nome'                    => $str('nome'),
            'data_nascimento'         => $str('data_nascimento'),
            'cpf'                     => preg_replace('/\D/', '', $p['cpf'] ?? ''),
            'rg'                      => $str('rg'),
            'rg_orgao_expedidor'      => $str('rg_orgao_expedidor'),
            'cep'                     => preg_replace('/\D/', '', $p['cep'] ?? ''),
            'endereco'                => $str('endereco'),
            'bairro'                  => $str('bairro'),
            'cidade'                  => $str('cidade'),
            'uf'                      => strtoupper(substr($str('uf'), 0, 2)),
            'telefone'                => $str('telefone'),
            'peso_kg'                 => is_numeric($p['peso_kg'] ?? '') ? (float)$p['peso_kg'] : null,
            'contato_emergencia'      => $str('contato_emergencia'),
            'telefone_emergencia'     => $str('telefone_emergencia'),
            'modalidade_id'           => (int)($p['modalidade_id'] ?? 0),
            'faixa_id'                => (int)($p['faixa_id']      ?? 0),
            'graduacao_id'            => (int)($p['graduacao_id']  ?? 0),
        ];
    }

    private function validarAluno(array $d): array
    {
        $e = [];
        if (strlen($d['nome']) < 3)           $e[] = 'Nome inválido.';
        if (!$this->validarCPF($d['cpf']))    $e[] = 'CPF inválido.';
        if (!$this->validarData($d['data_nascimento'])) $e[] = 'Data de nascimento inválida.';
        if (strlen($d['cep']) !== 8)          $e[] = 'CEP inválido.';
        if (empty($d['endereco']))            $e[] = 'Endereço obrigatório.';
        if (empty($d['cidade']))              $e[] = 'Cidade obrigatória.';
        if (strlen($d['uf']) !== 2)           $e[] = 'UF inválida.';
        return $e;
    }

    private function validarCPF(string $cpf): bool
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) $d += $cpf[$c] * (($t + 1) - $c);
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) return false;
        }
        return true;
    }

    private function validarData(string $data): bool
    {
        // Aceita DD/MM/AAAA ou AAAA-MM-DD
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
            return checkdate((int)$m[2], (int)$m[1], (int)$m[3]);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $dt = \DateTime::createFromFormat('Y-m-d', $data);
            return $dt && $dt->format('Y-m-d') === $data;
        }
        return false;
    }

    private function converterData(string $data): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return $data;
    }
}
