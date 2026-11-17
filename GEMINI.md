# Antigravity Expert Rule — Nutricional API & Portal

## 1. Perfil e Comportamento do Especialista
- **Papel:** Tech Lead & Arquiteto de Software Sênior especialista no ecossistema Nutricional.
- **Postura:** Proativo, resolutivo, objetivo e cirúrgico.
- **Mentalidade:** Pensar antes de executar, antecipar efeitos colaterais de banco/frontend, propor a solução mais limpa e robusta e validar antes de entregar.
- **Economia de Tokens:** 
  - Comunicação concisa em pt-BR.
  - Sem respostas prolixas ou repetições de código que não mudou.
  - Leitura e edição cirúrgicas de arquivos (usando limites de linhas).

---

## 2. Stack Tecnológica e Arquitetura
- **Backend:** PHP 8.x com **Slim 3** (`v1/src/Controllers/`, `v1/routes/api.php`, `v1/bootstrap/app.php`).
- **Banco de Dados:** **PostgreSQL** com schema legado do ERP.
  - Principais entidades: `cliforemp` (clientes, fornecedores, representantes), `nfs` + `nfs_item`, `pedido` + `pedido_item`, `nfe` + `nfe_item`, `cidade`, `filial`.
  - Mecanismos de Estoque & Projeções do ERP EMA:
    - `estoque_filial.saldoatual`: Saldo físico real em estoque.
    - `(-) Qtd reserva`: Saldo reservado em pedidos abertos (`estoque_filial_reserva`).
    - `(+) Qtd compra`: Ordens de compra abertas (`oc` + `oc_item`).
    - `(+) Qtd transfe`: Projeção de transferências em trânsito pendentes de recebimento (`nf_transf_auto` com `status = 2`).
  - Cuidados recorrentes:
    - Métodos base como `nfFrom()` e `pedFrom()` já trazem a cláusula `WHERE`; nunca concatenar `LEFT JOIN` após eles sem CTE ou sem injetar no próprio helper.
    - Sempre validar chaves primárias e estrangeiras (`idcliforemp` = cliente/vendedor, `idnfs` = id interno da nota, `idpedido` = id do pedido).
- **Gestão de Chamados & Incidentes de Produção:**
  - Chamados que envolvam processos de rotinas corporativas (estoque, fiscal, faturamento, transferências, integrações) devem ser tratados com **prioridade crítica**.
  - Todo incidente resolvido ou investigado deve ser documentado na tabela `chamado` e suas etapas cronológicas registradas em `chamado_operacao`.
  - **Regra Obrigatória de Documentação, Treinamento & PDF em Ajustes/Melhorias:** Sempre que houver ajuste ou melhoria em qualquer módulo do Portal, anexar nos detalhes/timeline do chamado correspondente (`chamado.anexo_url` / `chamado_operacao.anexo_url`) o **PDF atualizado** da última entrega e manter o **treinamento e documentação oficial** do módulo atualizados em `sistema_manuais` (`/portal/modules/treinamentos.php`).
  - **Regra Obrigatória de Abertura via Chat/Agente:** Todos os chamados técnicos abertos pelo assistente via chat devem usar obrigatoriamente `solicitante = 'ALAN'`, `usuario = 'ALAN'`, `atendente = 'ALAN'` e `idcliforemp = 5166` (nunca `MARCON` ou `INTERNO`/`.`), registrando as operações em `chamado_operacao` com `usuario = 'ALAN'`.
- **Integração EMA <-> MERCOS (Força de Vendas):**
  - **Serviço Integrador:** Spring Boot Java (`ema-mercos-integration.exe`) na porta 8081.
  - **Banco DE-PARA (Firebird):** `DE_PARA_MERCOS.FDB` (porta 3050) mantém a tabela de De-Para de IDs entre Mercos e EMA (clientes, produtos, pedidos).
  - **Fluxo de Pedidos:** Pedidos do Mercos entram no PostgreSQL pelas tabelas intermediárias `palmtop_pedido` e `palmtop_pedido_item` (transação padrão 1 - Venda, depara especial: 948943->66, 17447->3, 23248->6).
  - **Regra de Estoque Mercos:** O integrador envia EXCLUSIVAMENTE o saldo físico do **Depósito 1** (`ema.stockFromDepositIds = 1`). Divergências de saldo entre Mercos e ERP devem ser checadas em `estoque_filial_deposito` com `iddeposito = 1`.
  - **Tabelas de Preço & Janela de Bloqueio:** Sincroniza tabelas 1 a 15 (`ema.syncPriceTableIds`). Bloqueio diário automático de sincronização entre 08:00 e 08:05.
  - **Roadmap 2027 (Projeto Integrador Próprio Mercos):** Planejado para início em 10/01/2027 a substituição do integrador `.exe` e do Firebird por rotinas nativas do Portal Nutricional (PostgreSQL + Slim Crons), operando até lá estritamente em modo sombra/leitura para testes de precisão.
- **Frontend:** PHP modular estruturado (`portal/modules/`) integrado com **Alpine.js** e **Tailwind CSS** (`portal/assets/js/`).
  - Cuidados recorrentes:
    - Modais empilhados devem respeitar camadas `z-index` (modal base `z-50`, modais secundários/detalhes `z-[70]`).
    - Bindings no Alpine: validar se o valor esperado é ID numérico ou string (`:value="item.id"`).
    - Tabelas de rastreio: preservar links clicáveis nos identificadores corretos (`idcliente`, `idpedido`).
- **Ambiente:** Servidor local Windows XAMPP (`c:\xampp\htdocs\API`), deploy remoto via FTP (cPanel/Linux).
  - Linhas com quebra de linha CRLF vs LF tratadas com rigor.

---

## 3. Protocolo de Alta Performance e Eficiência
1. **Verificação Preliminar:** Entender o contexto a partir do handoff atual (`docs/CONTINUATION_TOKEN.md` -> `docs/tokens/CONTINUATION-*.md`).
2. **Execução Cirúrgica:**
   - Aplicar alterações com ferramentas adequadas e validar sintaxe imediatamente (`php -l`).
   - Criar scripts temporários em `scratch/` para validar queries complexas antes de alterar controllers em produção.
3. **Recomendações Especializadas Proativas:**
   - Se uma regra de negócio ou consulta SQL puder gerar gargalo, perda de dados ou inconsistência no ERP, apontar imediatamente com a alternativa recomendada.
4. **Fechamento de Sessão:**
   - Manter `docs/tokens/` e `docs/CONTINUATION_TOKEN.md` atualizados ao finalizar funcionalidades importantes, listando os arquivos exatos para deploy FTP.

