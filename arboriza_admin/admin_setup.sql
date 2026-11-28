-- =====================================================================
-- ARBORIZA SP — banco completo + acréscimos do painel administrativo
-- =====================================================================
-- Este arquivo sozinho cria TUDO, na ordem certa:
--   1. banco, tabelas, índices, views e procedures
--   2. dados iniciais (cidades e espécies) e usuários do MySQL
--   3. acréscimos do painel: tabela "usuarios" (login) e coluna "imagem"
--
-- ATENÇÃO: começa com DROP DATABASE, então apaga e recria o banco.
-- Pode rodar quantas vezes quiser; os dados anteriores serão perdidos.
--
-- Rodar (XAMPP na porta 3307, sem senha):
--   C:\xampp\mysql\bin\mysql.exe -u root -h 127.0.0.1 -P 3307 --default-character-set=utf8mb4 < admin_setup.sql
-- =====================================================================

DROP DATABASE IF EXISTS arborizacao_sp;
CREATE DATABASE arborizacao_sp
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE arborizacao_sp;

-- 1. Cidades/Regiões de SP
CREATE TABLE regioes (
    id_cidade INT AUTO_INCREMENT PRIMARY KEY,
    nome_cidade VARCHAR(100) NOT NULL UNIQUE,
    regiao_metropolitana VARCHAR(80) NOT NULL
);

-- 2. Espécies de árvores adequadas para arborização urbana em SP
CREATE TABLE especies_arvores (
    id_especie INT AUTO_INCREMENT PRIMARY KEY,
    nome_popular VARCHAR(80) NOT NULL UNIQUE,
    nome_cientifico VARCHAR(120) NOT NULL,
    porte ENUM('pequeno', 'medio', 'grande') NOT NULL,
    nativa_sp BOOLEAN NOT NULL DEFAULT TRUE
);

-- 3. Clientes (Solicitantes)
CREATE TABLE clientes (
    cpf CHAR(14) PRIMARY KEY, -- Formato '000.000.000-00'
    nome VARCHAR(120) NOT NULL,
    idade INT NOT NULL CHECK (idade >= 18),
    id_cidade INT NOT NULL,
    FOREIGN KEY (id_cidade) REFERENCES regioes(id_cidade) ON DELETE RESTRICT
);

-- 4. Tabela Única de Pedidos de Arborização
CREATE TABLE pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    cpf_cliente CHAR(14) NOT NULL,
    id_cidade INT NOT NULL,
    id_especie INT NULL, -- Opcional em casos de vistoria/poda sem espécie definida
    tipo_servico ENUM('plantio', 'poda', 'remocao', 'vistoria') NOT NULL DEFAULT 'plantio',
    endereco_plantio VARCHAR(200) NOT NULL,
    status ENUM('requisitado', 'em_andamento', 'finalizado', 'cancelado') NOT NULL DEFAULT 'requisitado',
    data_pedido DATE NOT NULL,
    hora_pedido TIME NOT NULL,
    data_inicio DATETIME NULL,
    data_finalizacao DATETIME NULL,
    FOREIGN KEY (cpf_cliente) REFERENCES clientes(cpf) ON DELETE RESTRICT,
    FOREIGN KEY (id_cidade) REFERENCES regioes(id_cidade) ON DELETE RESTRICT,
    FOREIGN KEY (id_especie) REFERENCES especies_arvores(id_especie) ON DELETE RESTRICT
);

-- Índice para buscas por data (como testado no EXPLAIN)
CREATE INDEX idx_pedidos_data ON pedidos(data_pedido);

-- Índice composto para filtrar pedidos por status e data (muito comum em painéis)
CREATE INDEX idx_pedidos_status_data ON pedidos(status, data_pedido);

-- Índices nas chaves estrangeiras mais consultadas
CREATE INDEX idx_pedidos_cliente ON pedidos(cpf_cliente);
CREATE INDEX idx_pedidos_cidade ON pedidos(id_cidade);
CREATE INDEX idx_clientes_cidade ON clientes(id_cidade);

-- Visão de clientes com suas cidades e contagens calculadas em tempo real
CREATE VIEW vw_clientes_regioes AS
SELECT 
    c.cpf,
    c.nome,
    c.idade,
    r.nome_cidade,
    SUM(CASE WHEN p.status = 'requisitado'  THEN 1 ELSE 0 END) AS qtd_requisicoes_ativas,
    SUM(CASE WHEN p.status = 'em_andamento' THEN 1 ELSE 0 END) AS qtd_requisicoes_andamento,
    SUM(CASE WHEN p.status = 'finalizado'   THEN 1 ELSE 0 END) AS qtd_requisicoes_feitas
FROM clientes c
INNER JOIN regioes r ON c.id_cidade = r.id_cidade
LEFT JOIN pedidos p  ON p.cpf_cliente = c.cpf
GROUP BY c.cpf, c.nome, c.idade, r.nome_cidade;

-- Visão de pedidos requisitados (abertos) com espécie e endereço
CREATE VIEW vw_pedidos_requisitados AS
SELECT 
    p.id_pedido,
    p.data_pedido,
    p.hora_pedido,
    p.tipo_servico,
    p.endereco_plantio,
    e.nome_popular AS especie,
    c.cpf AS cliente_cpf,
    c.nome AS cliente_nome,
    r.nome_cidade
FROM pedidos p
INNER JOIN clientes c ON p.cpf_cliente = c.cpf
INNER JOIN regioes r  ON p.id_cidade = r.id_cidade
LEFT JOIN especies_arvores e ON p.id_especie = e.id_especie
WHERE p.status = 'requisitado';

-- Visão consolidada por cidade de SP
CREATE VIEW vw_resumo_regioes AS
SELECT 
    r.id_cidade,
    r.nome_cidade,
    r.regiao_metropolitana,
    COUNT(p.id_pedido) AS total_pedidos,
    SUM(CASE WHEN p.status = 'finalizado' THEN 1 ELSE 0 END) AS total_finalizados
FROM regioes r
LEFT JOIN pedidos p ON r.id_cidade = p.id_cidade
GROUP BY r.id_cidade, r.nome_cidade, r.regiao_metropolitana;

DELIMITER $$

-- 1. Abrir novo pedido de arborização
CREATE PROCEDURE novo_pedido_requisitado(
    IN p_cpf CHAR(14),
    IN p_id_cidade INT,
    IN p_id_especie INT,
    IN p_tipo_servico VARCHAR(20),
    IN p_endereco VARCHAR(200)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL; -- Propaga o erro em vez de esconder
    END;

    START TRANSACTION;

    INSERT INTO pedidos (
        cpf_cliente, id_cidade, id_especie, tipo_servico,
        endereco_plantio, status, data_pedido, hora_pedido
    )
    VALUES (
        p_cpf, p_id_cidade, p_id_especie, p_tipo_servico,
        p_endereco, 'requisitado', CURDATE(), CURTIME()
    );

    COMMIT;
END$$

-- 2. Avançar pedido de 'requisitado' para 'em_andamento'
CREATE PROCEDURE atualizar_para_andamento(
    IN p_id_pedido INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    UPDATE pedidos
    SET status = 'em_andamento',
        data_inicio = NOW()
    WHERE id_pedido = p_id_pedido
      AND status = 'requisitado';

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pedido não encontrado ou não está no status requisitado.';
    END IF;

    COMMIT;
END$$

-- 3. Avançar pedido de 'em_andamento' para 'finalizado'
CREATE PROCEDURE atualizar_para_finalizado(
    IN p_id_pedido INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    UPDATE pedidos
    SET status = 'finalizado',
        data_finalizacao = NOW()
    WHERE id_pedido = p_id_pedido
      AND status = 'em_andamento';

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pedido não encontrado ou não está em andamento.';
    END IF;

    COMMIT;
END$$

-- 4. Cancelar pedido (apenas se ainda não foi finalizado)
CREATE PROCEDURE cancelar_pedido(
    IN p_id_pedido INT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    START TRANSACTION;

    UPDATE pedidos
    SET status = 'cancelado'
    WHERE id_pedido = p_id_pedido
      AND status IN ('requisitado', 'em_andamento');

    IF ROW_COUNT() = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Pedido não encontrado ou já finalizado/cancelado.';
    END IF;

    COMMIT;
END$$

DELIMITER ;

-- Cidades de SP
INSERT INTO regioes (nome_cidade, regiao_metropolitana) VALUES
('São Paulo', 'RMSP'),
('Santo André', 'RMSP - ABC'),
('Campinas', 'RMC'),
('Ribeirão Preto', 'RMRP'),
('Santos', 'Baixada Santista');

-- Espécies comuns na arborização de SP
INSERT INTO especies_arvores (nome_popular, nome_cientifico, porte, nativa_sp) VALUES
('Ipê-Amarelo', 'Handroanthus chrysotrichus', 'medio', TRUE),
('Manacá-da-Serra', 'Pleroma mutabile', 'pequeno', TRUE),
('Quaresmeira', 'Pleroma granulosum', 'medio', TRUE),
('Sibipiruna', 'Cenostigma pluviosum', 'grande', TRUE),
('Resedá', 'Lagerstroemia indica', 'pequeno', FALSE);

-- Usuários do MySQL com princípio do menor privilégio (conforme Bloco 3 dos testes)
CREATE USER IF NOT EXISTS 'u_consulta'@'localhost'  IDENTIFIED BY 'Consulta@2026';
CREATE USER IF NOT EXISTS 'u_atendente'@'localhost' IDENTIFIED BY 'Atend@2026';

-- u_consulta: apenas leitura nas views
GRANT SELECT ON arborizacao_sp.vw_clientes_regioes     TO 'u_consulta'@'localhost';
GRANT SELECT ON arborizacao_sp.vw_pedidos_requisitados TO 'u_consulta'@'localhost';
GRANT SELECT ON arborizacao_sp.vw_resumo_regioes       TO 'u_consulta'@'localhost';

-- u_atendente: leitura nas views + abertura de pedidos via procedure
GRANT SELECT  ON arborizacao_sp.vw_clientes_regioes               TO 'u_atendente'@'localhost';
GRANT SELECT  ON arborizacao_sp.vw_pedidos_requisitados           TO 'u_atendente'@'localhost';
GRANT EXECUTE ON PROCEDURE arborizacao_sp.novo_pedido_requisitado TO 'u_atendente'@'localhost';

FLUSH PRIVILEGES;

-- =====================================================================
-- ACRÉSCIMOS DO PAINEL ADMINISTRATIVO
-- (precisam vir DEPOIS das tabelas criadas acima)
-- =====================================================================

-- Usuários que podem entrar no painel (login do site)
CREATE TABLE IF NOT EXISTS usuarios (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50)  NOT NULL UNIQUE,
    senha   VARCHAR(255) NOT NULL  -- hash bcrypt (password_hash), nunca a senha pura
);

-- Usuário de teste: admin / 123
-- Para trocar a senha: rode "php geraSenha.php MinhaSenha", copie o hash e use UPDATE.
INSERT IGNORE INTO usuarios (usuario, senha) VALUES
('admin', '$2y$12$pAb4JoVhxYcsvyotVyVmc.t6kM/eqKxjUW6MTFMUDvsoJJJstLKda');

-- Foto da espécie (guarda só o caminho do arquivo, ex.: img/ab12cd34.jpg)
ALTER TABLE especies_arvores
    ADD COLUMN imagem VARCHAR(255) NULL AFTER nativa_sp;
