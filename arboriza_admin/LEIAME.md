# Arboriza SP — Painel administrativo

## Como instalar
1. No MySQL, rode o script do banco (`codigo-paulo.txt`).
2. Rode `admin_setup.sql` **uma única vez** (cria a tabela `usuarios` e a coluna `imagem` das espécies).
3. (Opcional) Rode `dados_exemplo.sql` para ver o painel com movimento.
4. Copie esta pasta para o servidor (ex.: `htdocs/arboriza`) e confira a senha em `conexao.php`.
5. Abra `teste_conexao.php` para validar a conexão e depois `login.php`.

Login de teste: **admin / 123**

## Arquivos
| Arquivo | Função |
|---|---|
| login.php / logout.php / valida_sessao.php | Autenticação e proteção das páginas |
| index.php | Início: fluxo de pedidos, fila e menu |
| cadastro_pedido.php | Abrir/editar pedido (usa a procedure `novo_pedido_requisitado`) |
| listagem_pedidos.php | Painel com filtros + Iniciar / Finalizar / Cancelar (procedures) |
| cadastro_cliente.php | Clientes (CPF com máscara e validação) |
| cadastro_cidade.php | Cidades/regiões |
| cadastro_especie.php | Espécies com foto |
| relatorios.php | Relatórios lidos das 3 views |
| funcoes.php | Funções comuns (imagem, CPF, CSRF, mensagens) |
| cabecalho.php / rodape.php | Topo e rodapé comuns |
| styles.css | Estilos (troque as cores no `:root`) |
| geraSenha.php | `php geraSenha.php MinhaSenha` gera o hash para a tabela `usuarios` |

Para testar com CPFs falsos (111.111.111-11), mude `VALIDAR_CPF_REAL` para `false` em `funcoes.php`.
