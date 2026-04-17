-- ============================================
-- BANCO DE DADOS: Controle de Insumos T.I.
-- MySQL 8+ | Recriar do zero
-- ============================================

CREATE DATABASE IF NOT EXISTS insumos_ti
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE insumos_ti;

CREATE TABLE IF NOT EXISTS perfis (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome         VARCHAR(120) NOT NULL,
  email        VARCHAR(120) NOT NULL UNIQUE,
  senha_hash   VARCHAR(255) NOT NULL,
  perfil_id    INT UNSIGNED NOT NULL DEFAULT 3,
  ativo        TINYINT(1)   NOT NULL DEFAULT 1,
  criado_em    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ultimo_login TIMESTAMP NULL,
  FOREIGN KEY (perfil_id) REFERENCES perfis(id) ON DELETE RESTRICT,
  INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissoes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT UNSIGNED NOT NULL UNIQUE,
  p_entrada    TINYINT(1) NOT NULL DEFAULT 1,
  p_saida      TINYINT(1) NOT NULL DEFAULT 1,
  p_estoque    TINYINT(1) NOT NULL DEFAULT 1,
  p_inventario TINYINT(1) NOT NULL DEFAULT 1,
  p_historico  TINYINT(1) NOT NULL DEFAULT 0,
  p_relatorio  TINYINT(1) NOT NULL DEFAULT 0,
  p_inv_editar TINYINT(1) NOT NULL DEFAULT 0,
  p_usuarios   TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sessoes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT UNSIGNED NOT NULL,
  token      VARCHAR(64)  NOT NULL UNIQUE,
  criado_em  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  expira_em  TIMESTAMP    NOT NULL,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  INDEX idx_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS insumos (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome           VARCHAR(100) NOT NULL UNIQUE,
  estoque_atual  INT NOT NULL DEFAULT 0,
  estoque_minimo INT NOT NULL DEFAULT 5,
  unidade        VARCHAR(30) NOT NULL DEFAULT 'un',
  ativo          TINYINT(1) NOT NULL DEFAULT 1,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS responsaveis (
  id    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome  VARCHAR(120) NOT NULL UNIQUE,
  ativo TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS setores (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome        VARCHAR(120) NOT NULL UNIQUE,
  tipo_insumo VARCHAR(50)  NOT NULL,
  ativo       TINYINT(1)   NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS movimentacoes (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo           ENUM('ENTRADA','SAIDA') NOT NULL,
  insumo_id      INT UNSIGNED NOT NULL,
  responsavel_id INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NULL,
  setor_id       INT UNSIGNED NULL,
  quantidade     INT UNSIGNED NOT NULL,
  descricao      TEXT NULL,
  referencia     VARCHAR(60) NULL,
  criado_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (insumo_id)      REFERENCES insumos(id)      ON DELETE RESTRICT,
  FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE RESTRICT,
  FOREIGN KEY (setor_id)       REFERENCES setores(id)      ON DELETE SET NULL,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE SET NULL,
  INDEX idx_tipo   (tipo),
  INDEX idx_insumo (insumo_id),
  INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventarios (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  responsavel_id INT UNSIGNED NOT NULL,
  usuario_id     INT UNSIGNED NULL,
  status         ENUM('ABERTO','FINALIZADO','CANCELADO') NOT NULL DEFAULT 'ABERTO',
  observacao     TEXT NULL,
  aberto_em      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  finalizado_em  TIMESTAMP NULL,
  FOREIGN KEY (responsavel_id) REFERENCES responsaveis(id) ON DELETE RESTRICT,
  FOREIGN KEY (usuario_id)     REFERENCES usuarios(id)     ON DELETE SET NULL,
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inventario_itens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inventario_id INT UNSIGNED NOT NULL,
  insumo_id     INT UNSIGNED NOT NULL,
  qtd_sistema   INT NOT NULL DEFAULT 0,
  qtd_contada   INT NULL,
  diferenca     INT NULL,
  FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE,
  FOREIGN KEY (insumo_id)     REFERENCES insumos(id)     ON DELETE RESTRICT,
  UNIQUE KEY uq_inv_insumo (inventario_id, insumo_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Perfis
INSERT INTO perfis (nome) VALUES ('superadmin'),('admin'),('funcionario')
ON DUPLICATE KEY UPDATE nome = nome;

-- Insumos
INSERT INTO insumos (nome, estoque_minimo, unidade) VALUES
  ('Cupom',                       10,'rolo'),('Ribbon',5,'un'),
  ('Pulseira 25x280',             10,'cx'),('Etiqueta 100x50',10,'cx'),
  ('Etiqueta 3cs 33x22 azul',     10,'cx'),('Etiqueta 3cs 33x22 branca',10,'cx'),
  ('Etiqueta 3cs 33x22 vermelha', 10,'cx'),('Etiqueta 3cs 33x22 amarela',10,'cx')
ON DUPLICATE KEY UPDATE nome = nome;

-- Responsáveis
INSERT INTO responsaveis (nome) VALUES
  ('AMANDA ROCHA DA COSTA'),('ANTONIO CEZAR GONCALVES ARAUJO JUNIOR'),
  ('DANIEL MOREIRA DA SILVA DOS ANJOS'),('MARCOS DANIEL TAVARES DOS SANTOS'),
  ('JOSE MARIA GONÇALVES ALFONSO NETO'),('TOMÉ DE JESUS SOSINHO GONÇALVES FILHO')
ON DUPLICATE KEY UPDATE nome = nome;

-- Setores
INSERT INTO setores (nome, tipo_insumo) VALUES
  ('CUPOM FARMÁCIA SATELITE PA','Cupom'),('CUPOM TOTEM VISITANTES','Cupom'),
  ('CUPOM TOTEM SADT','Cupom'),('CUPOM TOTEM RECEPÇÃO 1','Cupom'),
  ('CUPOM TOTEM RECEPÇÃO 4','Cupom'),('CUPOM FARMÁCIA SATÉLITE BLOCO','Cupom'),
  ('CUPOM FARMÁCIA SATÉLITE UTI','Cupom'),('CUPOM FARMACIA CENTRAL','Cupom'),
  ('PULSEIRA URGENCIA','Pulseira'),('PULSEIRA RECEPÇÃO 5 / NIR','Pulseira'),
  ('PULSEIRA 6 ANDAR','Pulseira'),('PULSEIRA 8 ANDAR','Pulseira'),
  ('ETIQUETADORA FARMACIA SATELITE PA','Etiqueta'),('ETIQUETADORA URGENCIA','Etiqueta'),
  ('ETIQUETADORA VISITANTES','Etiqueta'),('ETIQUETADORA MAIS SABOR','Etiqueta'),
  ('ETIQUETADORA CME','Etiqueta'),('ETIQUETADORA CAF','Etiqueta'),
  ('ETIQUETADORA FARMACIA SATÉLITE UTI','Etiqueta'),('ETIQUETADORA FARMACIA CENTRAL','Etiqueta')
ON DUPLICATE KEY UPDATE nome = nome;
