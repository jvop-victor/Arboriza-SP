-- =====================================================================
-- ARBORIZA SP — dados de exemplo (OPCIONAL)
-- =====================================================================
-- Rode depois do codigo-paulo.txt e do admin_setup.sql, só se quiser
-- ver o painel com movimento. Os CPFs abaixo são válidos (passam na
-- validação do site) e foram gerados só para teste.
-- =====================================================================

USE arborizacao_sp;

INSERT INTO clientes (cpf, nome, idade, id_cidade) VALUES
('529.982.247-25', 'Marina Albuquerque', 34, 1),
('111.444.777-35', 'Carlos Eduardo Tavares', 52, 2),
('390.533.447-05', 'Beatriz Nogueira', 41, 3),
('168.995.350-09', 'Rogério Fontes', 29, 4),
('746.971.314-01', 'Helena Prado', 63, 5);

-- Pedidos nascem como "requisitado" pela procedure
CALL novo_pedido_requisitado('529.982.247-25', 1, 1, 'plantio',  'Rua Augusta, 1200 - Consolação');
CALL novo_pedido_requisitado('529.982.247-25', 1, NULL, 'vistoria', 'Av. Paulista, 900 - Bela Vista');
CALL novo_pedido_requisitado('111.444.777-35', 2, 4, 'plantio',  'Rua Oliveira Lima, 45 - Centro');
CALL novo_pedido_requisitado('390.533.447-05', 3, NULL, 'poda',  'Rua Barão de Jaguara, 310 - Centro');
CALL novo_pedido_requisitado('168.995.350-09', 4, NULL, 'remocao', 'Av. Independência, 2100 - Jardim Sumaré');
CALL novo_pedido_requisitado('746.971.314-01', 5, 3, 'plantio',  'Av. Ana Costa, 500 - Gonzaga');

-- Movimenta alguns pedidos para ilustrar o fluxo
CALL atualizar_para_andamento(1);
CALL atualizar_para_andamento(3);
CALL atualizar_para_finalizado(3);
CALL atualizar_para_andamento(4);
CALL cancelar_pedido(5);
