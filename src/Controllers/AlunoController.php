<?php
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

    public function index(): void
    {
        $this->auth->requireAuth();
        $id        = (int)$_SESSION['academia_id'];
        $busca     = trim($_GET['busca']       ?? '');
        $modalId   = (int)($_GET['modalidade'] ?? 0);
        $pagina    = max(1, (int)($_GET['p']   ?? 1));
        $porPagina = 20;
        $offset    = ($pagina - 1) * $porPagina;

        $pdo    = Database::getConnection();
        $where  = ["a.academia_id = {$id}"];
        $params = [];

        if ($busca)   { $where[] = '(a.nome LIKE ? OR a.cpf LIKE ?)'; $params[] = "%{$busca}%"; $params[] = "%{$busca}%"; }
        if ($modalId) { $where[] = 'a.modalidade_id = ?';             $params[] = $modalId; }

        $w     = implode(' AND ', $where);
        $total = $pdo->prepare("SELECT COUNT(*) FROM alunos a WHERE {$w}");
        $total->execute($params);
        $totalRegistros = (int)$total->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT a.id, a.nome, a.cpf, a.data_nascimento, a.ativo,
                   m.nome AS modalidade, f.nome AS faixa, f.cor_hex, g.grau
            FROM alunos a
            LEFT JOIN modalidades m ON m.id = a.modalidade_id
            LEFT JOIN faixas      f ON f.id = a.faixa_id
            LEFT JOIN graduacoes  g ON g.id = a.graduacao_id
            WHERE {$w} ORDER BY a.nome LIMIT {$porPagina} OFFSET {$offset}
        ");
        $stmt->execute($params);
        $alunos = $stmt->fetchAll();

        $modalidades = $pdo->prepare("SELECT id, nome FROM modalidades WHERE academia_id = ? AND ativo = 1 ORDER BY nome");
        $modalidades->execute([$id]);
        $modalidades = $modalidades->fetchAll();

        require ROOT . '/templates/alunos/index.php';
    }

    public function showCadastro(): void
    {
        $this->auth->requireAuth();
        $this->verificarLimiteAlunos();
        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();

        $csrfToken   = CsrfService::generate();
        $modalidades = $pdo->prepare("SELECT id, nome FROM modalidades WHERE academia_id = ? AND ativo = 1 ORDER BY nome");
        $modalidades->execute([$id]);
        $modalidades = $modalidades->fetchAll();

        require ROOT . '/templates/alunos/cadastro.php';
    }

    public function salvar(): void
    {
        $this->auth->requireAuth();
        CsrfService::validate($_POST['_csrf'] ?? '');
        $this->verificarLimiteAlunos();

        $dados = $this->sanitizarAluno($_POST);
        $erros = $this->validarAluno($dados);

        if (!empty($erros)) {
            $_SESSION['flash_erros'] = $erros;
            $_SESSION['form_dados']  = $dados;
            header('Location: /alunos/novo');
            exit;
        }

        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();

        // CPF único por academia
        $stmt = $pdo->prepare("SELECT id FROM alunos WHERE cpf = ? AND academia_id = ? LIMIT 1");
        $stmt->execute([$dados['cpf'], $id]);
        if ($stmt->fetch()) {
            $_SESSION['flash_erros'] = ['CPF já cadastrado nesta academia.'];
            $_SESSION['form_dados']  = $dados;
            header('Location: /alunos/novo');
            exit;
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                INSERT INTO alunos
                    (academia_id, nome, data_nascimento, cpf, rg, rg_orgao_expedidor,
                     cep, endereco, bairro, cidade, uf,
                     telefone, peso_kg, contato_emergencia, telefone_emergencia,
                     modalidade_id, faixa_id, graduacao_id)
                VALUES
                    (:academia_id, :nome, :dt_nasc, :cpf, :rg, :orgao,
                     :cep, :end, :bairro, :cidade, :uf,
                     :tel, :peso, :c_emerg, :t_emerg,
                     :modal_id, :faixa_id, :grad_id)
            ");
            $stmt->execute([
                ':academia_id' => $id,
                ':nome'        => $dados['nome'],
                ':dt_nasc'     => $this->converterData($dados['data_nascimento']),
                ':cpf'         => $dados['cpf'],
                ':rg'          => $dados['rg']   ?: null,
                ':orgao'       => $dados['rg_orgao_expedidor'] ?: null,
                ':cep'         => preg_replace('/\D/', '', $dados['cep']),
                ':end'         => $dados['endereco'],
                ':bairro'      => $dados['bairro'],
                ':cidade'      => $dados['cidade'],
                ':uf'          => strtoupper($dados['uf']),
                ':tel'         => $dados['telefone']  ?: null,
                ':peso'        => $dados['peso_kg']   ?: null,
                ':c_emerg'     => $dados['contato_emergencia']  ?: null,
                ':t_emerg'     => $dados['telefone_emergencia'] ?: null,
                ':modal_id'    => $dados['modalidade_id'] ?: null,
                ':faixa_id'    => $dados['faixa_id']      ?: null,
                ':grad_id'     => $dados['graduacao_id']  ?: null,
            ]);

            $alunoId = (int)$pdo->lastInsertId();

            if ($dados['faixa_id'] && $dados['graduacao_id']) {
                $pdo->prepare("
                    INSERT INTO historico_graduacoes
                        (academia_id, aluno_id, faixa_nova_id, graduacao_nova_id, usuario_id, observacao)
                    VALUES (?, ?, ?, ?, ?, 'Matrícula inicial')
                ")->execute([$id, $alunoId, $dados['faixa_id'], $dados['graduacao_id'], $_SESSION['user_id']]);
            }

            $pdo->commit();

            $dtNasc = new \DateTime($this->converterData($dados['data_nascimento']));
            $idade  = (int)(new \DateTime())->diff($dtNasc)->y;

            $_SESSION['flash_ok']      = 'Aluno cadastrado com sucesso!';
            $_SESSION['aluno_id_novo'] = $alunoId;
            $_SESSION['aluno_menor']   = ($idade < 18);

            header("Location: " . ($idade < 18 ? "/alunos/{$alunoId}/responsavel" : "/alunos/{$alunoId}/clinico"));
            exit;

        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_erros'] = ['Erro ao salvar aluno. Tente novamente.'];
            header('Location: /alunos/novo');
            exit;
        }
    }

    public function detalhe(int $alunoId): void
    {
        $this->auth->requireAuth();
        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("
            SELECT a.*, m.nome AS modalidade, f.nome AS faixa, f.cor_hex, g.grau
            FROM alunos a
            LEFT JOIN modalidades m ON m.id = a.modalidade_id
            LEFT JOIN faixas      f ON f.id = a.faixa_id
            LEFT JOIN graduacoes  g ON g.id = a.graduacao_id
            WHERE a.id = ? AND a.academia_id = ?
        ");
        $stmt->execute([$alunoId, $id]);
        $aluno = $stmt->fetch();

        if (!$aluno) { http_response_code(404); require ROOT . '/templates/errors/404.php'; exit; }

        $historico = $pdo->prepare("
            SELECT h.registrado_em, h.observacao,
                   fa.nome AS faixa_ant, ga.grau AS grau_ant,
                   fn.nome AS faixa_nova, gn.grau AS grau_novo,
                   u.nome AS usuario
            FROM historico_graduacoes h
            LEFT JOIN faixas    fa ON fa.id = h.faixa_anterior_id
            LEFT JOIN graduacoes ga ON ga.id = h.graduacao_anterior_id
            LEFT JOIN faixas    fn ON fn.id = h.faixa_nova_id
            LEFT JOIN graduacoes gn ON gn.id = h.graduacao_nova_id
            LEFT JOIN usuarios  u  ON u.id  = h.usuario_id
            WHERE h.aluno_id = ? AND h.academia_id = ?
            ORDER BY h.registrado_em DESC
        ");
        $historico->execute([$alunoId, $id]);
        $historico = $historico->fetchAll();

        $responsavel = $pdo->prepare("SELECT * FROM responsaveis WHERE aluno_id = ?");
        $responsavel->execute([$alunoId]);
        $responsavel = $responsavel->fetch();

        $clinico = $pdo->prepare("SELECT * FROM condicoes_clinicas WHERE aluno_id = ?");
        $clinico->execute([$alunoId]);
        $clinico = $clinico->fetch();

        $modalidades = $pdo->prepare("SELECT id, nome FROM modalidades WHERE academia_id = ? AND ativo=1 ORDER BY nome");
        $modalidades->execute([$id]);
        $modalidades = $modalidades->fetchAll();

        $csrfToken = CsrfService::generate();

        require ROOT . '/templates/alunos/detalhe.php';
    }

    public function atualizarGraduacao(int $alunoId): void
    {
        $this->auth->requireAuth();
        CsrfService::validate($_POST['_csrf'] ?? '');

        $faixaId    = (int)($_POST['faixa_id']    ?? 0);
        $graduacaoId= (int)($_POST['graduacao_id'] ?? 0);
        $obs        = trim($_POST['observacao'] ?? '');
        $id         = (int)$_SESSION['academia_id'];

        if (!$faixaId || !$graduacaoId) {
            $_SESSION['flash_erros'] = ['Selecione faixa e graduação.'];
            header("Location: /alunos/{$alunoId}");
            exit;
        }

        $pdo  = Database::getConnection();
        $atual = $pdo->prepare("SELECT faixa_id, graduacao_id FROM alunos WHERE id = ? AND academia_id = ?");
        $atual->execute([$alunoId, $id]);
        $row  = $atual->fetch();

        if (!$row) { http_response_code(404); exit; }

        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE alunos SET faixa_id = ?, graduacao_id = ? WHERE id = ? AND academia_id = ?")
                ->execute([$faixaId, $graduacaoId, $alunoId, $id]);
            $pdo->prepare("
                INSERT INTO historico_graduacoes
                    (academia_id, aluno_id, faixa_anterior_id, graduacao_anterior_id, faixa_nova_id, graduacao_nova_id, usuario_id, observacao)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$id, $alunoId, $row['faixa_id'], $row['graduacao_id'], $faixaId, $graduacaoId, $_SESSION['user_id'], $obs ?: null]);
            $pdo->commit();
            $_SESSION['flash_ok'] = 'Graduação atualizada!';
        } catch (\Exception $e) {
            $pdo->rollBack();
            $_SESSION['flash_erros'] = ['Erro ao atualizar graduação.'];
        }

        header("Location: /alunos/{$alunoId}");
        exit;
    }

    public function apiCep(string $cep): void
    {
        header('Content-Type: application/json');
        $cepLimpo = preg_replace('/\D/', '', $cep);
        if (strlen($cepLimpo) !== 8) { http_response_code(400); echo json_encode(['erro' => 'CEP inválido']); exit; }
        try {
            echo json_encode(CepService::buscar($cepLimpo));
        } catch (\Exception $e) {
            http_response_code(502);
            echo json_encode(['erro' => 'Serviço de CEP indisponível']);
        }
        exit;
    }

    private function verificarLimiteAlunos(): void
    {
        $max = (int)($_SESSION['academia_max_alunos'] ?? 999);
        $id  = (int)$_SESSION['academia_id'];
        $pdo = Database::getConnection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM alunos WHERE academia_id = ? AND ativo = 1");
        $stmt->execute([$id]);
        if ((int)$stmt->fetchColumn() >= $max) {
            $_SESSION['flash_erros'] = ["Limite de {$max} alunos do plano atingido. Faça upgrade para continuar."];
            header('Location: /alunos');
            exit;
        }
    }

    private function sanitizarAluno(array $p): array
    {
        $str = fn($k) => trim(filter_var($p[$k] ?? '', FILTER_SANITIZE_SPECIAL_CHARS));
        return [
            'nome'                => $str('nome'),
            'data_nascimento'     => $str('data_nascimento'),
            'cpf'                 => preg_replace('/\D/', '', $p['cpf'] ?? ''),
            'rg'                  => $str('rg'),
            'rg_orgao_expedidor'  => $str('rg_orgao_expedidor'),
            'cep'                 => preg_replace('/\D/', '', $p['cep'] ?? ''),
            'endereco'            => $str('endereco'),
            'bairro'              => $str('bairro'),
            'cidade'              => $str('cidade'),
            'uf'                  => strtoupper(substr($str('uf'), 0, 2)),
            'telefone'            => $str('telefone'),
            'peso_kg'             => is_numeric($p['peso_kg'] ?? '') ? (float)$p['peso_kg'] : null,
            'contato_emergencia'  => $str('contato_emergencia'),
            'telefone_emergencia' => $str('telefone_emergencia'),
            'modalidade_id'       => (int)($p['modalidade_id'] ?? 0),
            'faixa_id'            => (int)($p['faixa_id']      ?? 0),
            'graduacao_id'        => (int)($p['graduacao_id']  ?? 0),
        ];
    }

    private function validarAluno(array $d): array
    {
        $e = [];
        if (strlen($d['nome']) < 3)                    $e[] = 'Nome inválido.';
        if (!$this->validarCPF($d['cpf']))             $e[] = 'CPF inválido.';
        if (!$this->validarData($d['data_nascimento'])) $e[] = 'Data de nascimento inválida.';
        if (strlen($d['cep']) !== 8)                   $e[] = 'CEP inválido.';
        if (empty($d['endereco']))                     $e[] = 'Endereço obrigatório.';
        if (empty($d['cidade']))                       $e[] = 'Cidade obrigatória.';
        if (strlen($d['uf']) !== 2)                    $e[] = 'UF inválida.';
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
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) return checkdate((int)$m[2], (int)$m[1], (int)$m[3]);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) { $dt = \DateTime::createFromFormat('Y-m-d', $data); return $dt && $dt->format('Y-m-d') === $data; }
        return false;
    }

    private function converterData(string $data): string
    {
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $data, $m)) return "{$m[3]}-{$m[2]}-{$m[1]}";
        return $data;
    }
}
