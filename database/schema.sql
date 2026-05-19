-- ============================================================
-- DojoManager - Schema do Banco de Dados
-- Compatível com MySQL 8.0+ / MariaDB 10.5+
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO';

-- ----------------------
-- USUÁRIOS DO SISTEMA
-- ----------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(120)  NOT NULL,
    email         VARCHAR(180)  NOT NULL UNIQUE,
    senha_hash    VARCHAR(255)  NOT NULL,
    perfil        ENUM('admin','instrutor','secretaria') NOT NULL DEFAULT 'secretaria',
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    logo_path     VARCHAR(255)  NULL COMMENT 'Logo personalizada da academia',
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- MODALIDADES
-- ----------------------
CREATE TABLE IF NOT EXISTS modalidades (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome          VARCHAR(80)   NOT NULL,
    descricao     TEXT          NULL,
    ativo         TINYINT(1)    NOT NULL DEFAULT 1,
    criado_em     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- FAIXAS POR MODALIDADE
-- ----------------------
CREATE TABLE IF NOT EXISTS faixas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    modalidade_id INT UNSIGNED  NOT NULL,
    nome          VARCHAR(60)   NOT NULL,
    cor_hex       VARCHAR(7)    NOT NULL DEFAULT '#cccccc',
    ordem         TINYINT       NOT NULL DEFAULT 0,
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- GRADUAÇÕES POR FAIXA
-- ----------------------
CREATE TABLE IF NOT EXISTS graduacoes (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    faixa_id      INT UNSIGNED  NOT NULL,
    grau          VARCHAR(30)   NOT NULL COMMENT 'Ex: 1º Grau, 2º Grau',
    ordem         TINYINT       NOT NULL DEFAULT 0,
    FOREIGN KEY (faixa_id) REFERENCES faixas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- ALUNOS
-- ----------------------
CREATE TABLE IF NOT EXISTS alunos (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                  VARCHAR(120) NOT NULL,
    data_nascimento       DATE         NOT NULL,
    data_cadastro         DATE         NOT NULL DEFAULT (CURRENT_DATE),
    cpf                   VARCHAR(14)  NOT NULL UNIQUE COMMENT 'Formato: 000.000.000-00',
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
    FOREIGN KEY (modalidade_id) REFERENCES modalidades(id) ON DELETE SET NULL,
    FOREIGN KEY (faixa_id)      REFERENCES faixas(id)      ON DELETE SET NULL,
    FOREIGN KEY (graduacao_id)  REFERENCES graduacoes(id)  ON DELETE SET NULL,
    INDEX idx_cpf (cpf),
    INDEX idx_modalidade (modalidade_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- RESPONSÁVEIS (menores de idade)
-- ----------------------
CREATE TABLE IF NOT EXISTS responsaveis (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id           INT UNSIGNED NOT NULL UNIQUE,
    nome               VARCHAR(120) NOT NULL,
    cpf                VARCHAR(14)  NOT NULL,
    rg                 VARCHAR(20)  NULL,
    rg_orgao_expedidor VARCHAR(20)  NULL,
    cep                VARCHAR(9)   NOT NULL,
    endereco           VARCHAR(200) NOT NULL,
    bairro             VARCHAR(80)  NOT NULL,
    cidade             VARCHAR(80)  NOT NULL,
    uf                 CHAR(2)      NOT NULL,
    telefone           VARCHAR(20)  NOT NULL,
    criado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- CONDIÇÃO CLÍNICA
-- ----------------------
CREATE TABLE IF NOT EXISTS condicoes_clinicas (
    id                     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id               INT UNSIGNED NOT NULL UNIQUE,
    tem_condicao_medica    TINYINT(1)   NOT NULL DEFAULT 0,
    condicao_medica_desc   TEXT         NULL,
    toma_medicamento       TINYINT(1)   NOT NULL DEFAULT 0,
    medicamento_desc       TEXT         NULL,
    possui_alergia         TINYINT(1)   NOT NULL DEFAULT 0,
    alergia_desc           TEXT         NULL,
    possui_lesao_cirurgica TINYINT(1)   NOT NULL DEFAULT 0,
    lesao_cirurgica_desc   TEXT         NULL,
    criado_em              DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id) REFERENCES alunos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- HISTÓRICO DE GRADUAÇÕES
-- ----------------------
CREATE TABLE IF NOT EXISTS historico_graduacoes (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    aluno_id            INT UNSIGNED NOT NULL,
    faixa_anterior_id   INT UNSIGNED NULL,
    graduacao_anterior_id INT UNSIGNED NULL,
    faixa_nova_id       INT UNSIGNED NOT NULL,
    graduacao_nova_id   INT UNSIGNED NOT NULL,
    usuario_id          INT UNSIGNED NOT NULL COMMENT 'Quem registrou',
    observacao          TEXT         NULL,
    registrado_em       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (aluno_id)              REFERENCES alunos(id)     ON DELETE CASCADE,
    FOREIGN KEY (faixa_nova_id)         REFERENCES faixas(id)     ON DELETE RESTRICT,
    FOREIGN KEY (graduacao_nova_id)     REFERENCES graduacoes(id) ON DELETE RESTRICT,
    FOREIGN KEY (usuario_id)            REFERENCES usuarios(id)   ON DELETE RESTRICT,
    INDEX idx_aluno (aluno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------
-- LOG DE ACESSO (segurança)
-- ----------------------
CREATE TABLE IF NOT EXISTS logs_acesso (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT UNSIGNED NULL,
    email      VARCHAR(180) NULL,
    ip         VARCHAR(45)  NOT NULL,
    acao       VARCHAR(60)  NOT NULL,
    sucesso    TINYINT(1)   NOT NULL DEFAULT 1,
    criado_em  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_usuario (usuario_id),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ----------------------
-- DADOS INICIAIS
-- ----------------------
INSERT IGNORE INTO usuarios (nome, email, senha_hash, perfil) VALUES
('Administrador', 'admin@academia.com.br', '$2y$12$PLACEHOLDER_HASH', 'admin');

INSERT IGNORE INTO modalidades (nome) VALUES
('Jiu-Jitsu'), ('Muay Thai'), ('Karatê'), ('Judô'), ('Boxe'), ('Taekwondo');

-- Faixas Jiu-Jitsu
INSERT IGNORE INTO faixas (modalidade_id, nome, cor_hex, ordem)
SELECT id, 'Branca', '#f5f5f5', 1 FROM modalidades WHERE nome = 'Jiu-Jitsu'
UNION ALL SELECT id, 'Azul',   '#3b82f6', 2 FROM modalidades WHERE nome = 'Jiu-Jitsu'
UNION ALL SELECT id, 'Roxa',   '#7c3aed', 3 FROM modalidades WHERE nome = 'Jiu-Jitsu'
UNION ALL SELECT id, 'Marrom', '#92400e', 4 FROM modalidades WHERE nome = 'Jiu-Jitsu'
UNION ALL SELECT id, 'Preta',  '#1f2937', 5 FROM modalidades WHERE nome = 'Jiu-Jitsu';
