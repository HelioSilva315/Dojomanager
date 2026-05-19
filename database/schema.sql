-- ============================================================
-- DojoManager — Schema Multi-Tenant v2.0
-- MySQL 8.0+ / MariaDB 10.5+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ----------------------
-- PLANOS
-- ----------------------
CREATE TABLE IF NOT EXISTS planos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(60)   NOT NULL,
    slug            VARCHAR(30)   NOT NULL UNIQUE,
    max_alunos      INT           NOT NULL DEFAULT 100,
    max_usuarios    INT           NOT NULL DEFAULT 3,
    preco_mensal    DECIMAL(8,2)  NOT NULL DEFAULT 0.00,
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- ACADEMIAS (tenants)
-- ----------------------
CREATE TABLE IF NOT EXISTS academias (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120)  NOT NULL,
    slug            VARCHAR(60)   NOT NULL UNIQUE COMMENT 'Ex: academia-tigre → tigre.sistema.com.br',
    cnpj            VARCHAR(18)   NULL UNIQUE,
    email           VARCHAR(180)  NOT NULL,
    telefone        VARCHAR(20)   NULL,
    logo_path       VARCHAR(255)  NULL,
    plano_id        INT UNSIGNED  NOT NULL DEFAULT 1,
    plano_expira_em DATE          NULL,
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE RESTRICT,
    INDEX idx_slug (slug),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- USUÁRIOS DO SISTEMA
-- ----------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academia_id     INT UNSIGNED  NULL COMMENT 'NULL = super-admin global',
    nome            VARCHAR(120)  NOT NULL,
    email           VARCHAR(180)  NOT NULL UNIQUE,
    senha_hash      VARCHAR(255)  NOT NULL,
    perfil          ENUM('superadmin','admin','instrutor','secretaria') NOT NULL DEFAULT 'secretaria',
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE CASCADE,
    INDEX idx_academia (academia_id),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- MODALIDADES (por academia)
-- ----------------------
CREATE TABLE IF NOT EXISTS modalidades (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academia_id     INT UNSIGNED  NOT NULL,
    nome            VARCHAR(80)   NOT NULL,
    descricao       TEXT          NULL,
    ativo           TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE CASCADE,
    INDEX idx_academia (academia_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- FAIXAS (por modalidade)
-- ----------------------
CREATE TABLE IF NOT EXISTS faixas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modalidade_id   INT UNSIGNED  NOT NULL,
    nome            VARCHAR(60)   NOT NULL,
    cor_hex         VARCHAR(7)    NOT NULL DEFAULT '#cccccc',
    ordem           TINYINT       NOT NULL DEFAULT 0,
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- GRADUAÇÕES (por faixa)
-- ----------------------
CREATE TABLE IF NOT EXISTS graduacoes (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faixa_id        INT UNSIGNED  NOT NULL,
    grau            VARCHAR(30)   NOT NULL,
    ordem           TINYINT       NOT NULL DEFAULT 0,
    FOREIGN KEY (faixa_id) REFERENCES faixas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- ALUNOS (por academia)
-- ----------------------
CREATE TABLE IF NOT EXISTS alunos (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academia_id           INT UNSIGNED NOT NULL,
    nome                  VARCHAR(120) NOT NULL,
    data_nascimento       DATE         NOT NULL,
    data_cadastro         DATE         NOT NULL DEFAULT (CURRENT_DATE),
    cpf                   VARCHAR(14)  NOT NULL,
    rg                    VARCHAR(20)  NULL,
    rg_orgao_expedidor    VARCHAR(20)  NULL,
    cep                   VARCHAR(9)   NOT NULL,
    endereco              VARCHAR(200) NOT NULL,
    bairro                VARCHAR(80)  NOT NULL,
    cidade                VARCHAR(80)  NOT NULL,
    uf                    CHAR(2)      NOT NULL,
    telefone              VARCHAR(20)  NULL,
    peso_kg               DECIMAL(5,2) NULL,
    contato_emergencia    VARCHAR(120) NULL,
    telefone_emergencia   VARCHAR(20)  NULL,
    modalidade_id         INT UNSIGNED NULL,
    faixa_id              INT UNSIGNED NULL,
    graduacao_id          INT UNSIGNED NULL,
    ativo                 TINYINT(1)   NOT NULL DEFAULT 1,
    foto_path             VARCHAR(255) NULL,
    criado_em             DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (academia_id)   REFERENCES academias(id)  ON DELETE CASCADE,
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL,
    FOREIGN KEY (faixa_id)      REFERENCES faixas(id)      ON DELETE SET NULL,
    FOREIGN KEY (graduacao_id)  REFERENCES graduacoes(id)  ON DELETE SET NULL,
    UNIQUE KEY uq_cpf_academia (cpf, academia_id),
    INDEX idx_academia (academia_id),
    INDEX idx_modalidade (modalidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- RESPONSÁVEIS
-- ----------------------
CREATE TABLE IF NOT EXISTS responsaveis (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id            INT UNSIGNED NOT NULL UNIQUE,
    nome                VARCHAR(120) NOT NULL,
    cpf                 VARCHAR(14)  NOT NULL,
    rg                  VARCHAR(20)  NULL,
    rg_orgao_expedidor  VARCHAR(20)  NULL,
    cep                 VARCHAR(9)   NOT NULL,
    endereco            VARCHAR(200) NOT NULL,
    bairro              VARCHAR(80)  NOT NULL,
    cidade              VARCHAR(80)  NOT NULL,
    uf                  CHAR(2)      NOT NULL,
    telefone            VARCHAR(20)  NOT NULL,
    criado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- CONDIÇÃO CLÍNICA
-- ----------------------
CREATE TABLE IF NOT EXISTS condicoes_clinicas (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id                INT UNSIGNED NOT NULL UNIQUE,
    tem_condicao_medica     TINYINT(1)   NOT NULL DEFAULT 0,
    condicao_medica_desc    TEXT         NULL,
    toma_medicamento        TINYINT(1)   NOT NULL DEFAULT 0,
    medicamento_desc        TEXT         NULL,
    possui_alergia          TINYINT(1)   NOT NULL DEFAULT 0,
    alergia_desc            TEXT         NULL,
    possui_lesao_cirurgica  TINYINT(1)   NOT NULL DEFAULT 0,
    lesao_cirurgica_desc    TEXT         NULL,
    criado_em               DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- HISTÓRICO DE GRADUAÇÕES
-- ----------------------
CREATE TABLE IF NOT EXISTS historico_graduacoes (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academia_id             INT UNSIGNED NOT NULL,
    aluno_id                INT UNSIGNED NOT NULL,
    faixa_anterior_id       INT UNSIGNED NULL,
    graduacao_anterior_id   INT UNSIGNED NULL,
    faixa_nova_id           INT UNSIGNED NOT NULL,
    graduacao_nova_id       INT UNSIGNED NOT NULL,
    usuario_id              INT UNSIGNED NOT NULL,
    observacao              TEXT         NULL,
    registrado_em           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academia_id)      REFERENCES academias(id)  ON DELETE CASCADE,
    FOREIGN KEY (aluno_id)         REFERENCES alunos(id)     ON DELETE CASCADE,
    FOREIGN KEY (faixa_nova_id)    REFERENCES faixas(id)     ON DELETE RESTRICT,
    FOREIGN KEY (graduacao_nova_id)REFERENCES graduacoes(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id)       REFERENCES usuarios(id)   ON DELETE RESTRICT,
    INDEX idx_academia (academia_id),
    INDEX idx_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- ASSINATURAS / PAGAMENTOS
-- ----------------------
CREATE TABLE IF NOT EXISTS assinaturas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academia_id     INT UNSIGNED  NOT NULL,
    plano_id        INT UNSIGNED  NOT NULL,
    status          ENUM('ativa','suspensa','cancelada','trial') NOT NULL DEFAULT 'trial',
    inicio_em       DATE          NOT NULL,
    expira_em       DATE          NOT NULL,
    valor_pago      DECIMAL(8,2)  NULL,
    observacao      TEXT          NULL,
    criado_em       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (academia_id) REFERENCES academias(id) ON DELETE CASCADE,
    FOREIGN KEY (plano_id)    REFERENCES planos(id)    ON DELETE RESTRICT,
    INDEX idx_academia (academia_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- LOG DE ACESSO
-- ----------------------
CREATE TABLE IF NOT EXISTS logs_acesso (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academia_id INT UNSIGNED NULL,
    usuario_id  INT UNSIGNED NULL,
    email       VARCHAR(180) NULL,
    ip          VARCHAR(45)  NOT NULL,
    acao        VARCHAR(60)  NOT NULL,
    sucesso     TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_academia (academia_id),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- DADOS INICIAIS
-- ============================================================

-- Planos
INSERT IGNORE INTO planos (nome, slug, max_alunos, max_usuarios, preco_mensal) VALUES
('Trial',      'trial',      50,   2,   0.00),
('Básico',     'basico',     150,  3,   49.90),
('Pro',        'pro',        500,  10,  99.90),
('Enterprise', 'enterprise', 9999, 999, 199.90);

-- Super Admin global (academia_id = NULL)
-- IMPORTANTE: troque a senha após a instalação!
INSERT IGNORE INTO usuarios (academia_id, nome, email, senha_hash, perfil) VALUES
(NULL, 'Super Admin', 'superadmin@dojomanager.com.br', '$2y$12$TROQUE_ESTA_SENHA_HASH_APOS_INSTALAR', 'superadmin');
