Fechando o Bloco 4.2 — Token + Commit + Deploy
Vou entregar em ordem:

Token de continuidade (token-2026-09-21-parte-9.md)

Comandos de commit (git)

Sugestão de subida FTP (com cuidado)

1️⃣ Token de continuidade
Local sugerido: docs/tokens/token-2026-09-21-parte-9.md

Crie o arquivo com este conteúdo:

markdown
# 📋 TOKEN DE CONTINUIDADE — Sessão 2026-09-21 (parte 9)

=================================================================

Este documento é contexto técnico para a próxima sessão.
Não contém senhas, tokens JWT, chaves de API ou credenciais.

=================================================================


## 🎯 REGRAS DE TRABALHO (OBRIGATÓRIAS)

1. **Toda alteração em método/função: entregar COMPLETO**, nunca fragmento.
2. **Sempre validar com `php -l`** após editar PHP.
3. **Sempre testar via `curl.exe`** (console) ou diretamente no terminal.
4. **Metodologia do Padrão Ouro:** Implementação → Validação → Documentação.
5. **Backup antes de substituir** qualquer método.
6. **Registrar lições aprendidas** neste token ao final da sessão.
7. **NUNCA usar `&&` ou `&` no PowerShell 5.1** — usar `;`
8. **SEMPRE usar `curl.exe`** (com .exe) — o `curl` sem é alias do PowerShell
9. **SEMPRE salvar JSON de teste sem BOM** — usar `[System.IO.File]::WriteAllText` com `UTF8Encoding($false)`


---

## PROJETO

Repositório: portal-nutricional
Branch: main
API estável: /v1
Ponte de compatibilidade: /v2 → /v1 temporariamente
Banco: PostgreSQL externo (remoto), via .env em C:\xampp\htdocs\API\.env
Ambiente local: php -S localhost:8080 router.php a partir de C:\xampp\htdocs\API
Última referência: sessão 2026-09-21 (parte 9) — Bloco 4.2 COMPLETO


## FOCO DA MIGRAÇÃO

Reestruturar a lógica de "Faltante" e "Devolução" no acerto de embarque:
- Devolução não gera mais pedido ERP — passa a gerar comprovante para faturamento
- Faltante se bifurca em dois tipos: com estoque (transação ERP 19) e sem estoque (transação ERP 20)
- Arquitetura em 3 camadas: FATO → TRATAMENTO → DOCUMENTO


---

## ✅ CONCLUÍDO NA SESSÃO 2026-09-21 (parte 9)

### BLOCO 4 — FRONTEND + BACKEND (CICLO FECHADO) ✅
Referência: commit 5a05b76 (sessão anterior) + 785d0d6 + 9044a72

### BLOCO 4.1 — BLOQUEIO DE DUPLICAÇÃO ✅

**Backend (`AcertoEmbarqueController::criarPedidoProblema`):**
- Antes de criar pedido de FALTANTE, verifica se já existe pedido de
  acerto ativo (pendente/processando/criado_erp) para a mesma entrega
- Se existir, retorna HTTP 409 com `code: 'PEDIDO_JA_EXISTE'` + dados
  completos do pedido existente (id, status, tipo_tratamento, valor, erp_id)
- NÃO se aplica a devolução

**Frontend (`acerto-embarque.js`):**
- Nova função `verificarPedidoFaltanteExistente(entregaId)` — retorna
  objeto do pedido ou null
- Nova função `mostrarAvisoPedidoExistente(pedido)` — Swal com dados +
  botão "Ver pedido" que rola até o card
- `abrirPedidoProblema()` — bloqueia antes de abrir modal
- `salvarPedidoProblema()` — revalida (rede de segurança)
- `criarPedidoParaItensProblema()` — bloqueia no início + trata 409 do backend
- `renderizarDetalhesAcerto()` v5 — substitui botão laranja "Gerar Pedido"
  por badge azul "✅ Pedido #X" clicável (UX proativa)
- Nova função `scrollParaPedido(pedidoId)` — rola até o card

### BLOCO 4.2 — AJUSTES FINOS ✅

**Correção da impressão (`acerto-embarque.css`):**
- **Bug**: `@media print` imprimia `#modalAcerto` (tela principal) por baixo
  do comprovante, poluindo o papel
- **Causa raiz**: regra `.modal.show { display: block !important }` no final
  do `@media print` sobrescrevia `#modalAcerto { display: none }`
- **Correção**: removida a regra genérica + reforço com `html body`
  prefixado + `position: absolute; left: -99999px` nos modais de fundo
- **Bônus**: adicionado `@page { margin: 12mm }`, `print-color-adjust: exact`
  e `page-break-inside: avoid` em linhas

**Ajuste na regra de "Conferido Total":**
- Antes: bloqueava se a entrega com divergência não tinha pedido em
  `frota_acerto_pedido`
- Problema: devolução NÃO gera pedido de acerto (só comprovante), então
  entregas só-devolução ficavam bloqueadas indevidamente
- **Correção**: agora considera "tratada" se tiver **OU** pedido de faltante
  **OU** comprovante de devolução emitido (`tratamento_numero_comprovante`)
- Mensagem do erro atualizada: "Divergências sem tratamento finalizado"

**Botão "Reimprimir Comprovante" para acerto finalizado:**
- Novo botão no footer do `#modalAcerto` (id `btn-visualizar-comprovante`)
- Substitui "Conferido Total" quando o acerto está `status = 'finalizado'`
- Nova função `reimprimirComprovanteConferencia()` — reconstrói o comprovante
  a partir dos dados atuais ou da API (se cards não estiverem no DOM)
- Nova função `reconstruirCardsConferidos(dados)` — cria objetos "virtuais"
  com `.dataset` compatível para reuso em `gerarComprovanteConferenciaTotal`
- `atualizarBotoesAcerto()` — força texto/onclick do botão quando finalizado

**Configuração de impressão (`@page`):**
- Margem de 12mm em todas as bordas
- `size: auto` (respeita o tamanho do papel selecionado)


### 🗂️ SCHEMA REAL — `frota_problema_tratamento`

- `id` (PK)
- `problema_id` (FK → frota_entrega_problema)
- `tipo_tratamento` (varchar) ⬅ **NÃO é `tipo`**
- `id_transacao_erp`, `id_filial_erp`, `transacao_descricao_snapshot`
- `acerto_pedido_id`, `numero_comprovante`, `valor_afetado`
- `status` (varchar, default 'pendente')
- `decidido_por`, `decidido_em`
- `comprovante_emitido_em`, `comprovante_emitido_por`
- `observacoes`, `created_at`, `updated_at`

### Estados do `status` (frota_problema_tratamento)

- `pendente` → ainda não decidido (faltante)
- `aguardando_fat` → pronto pra faturar (permite gerar comprovante de devolução)
- `comprovante_emitido` → comprovante já gerado
- `criado_erp` → pedido foi gerado no ERP (faltante)
- (potencialmente `cancelado`)

### Estados do `status` (frota_acerto_pedido)

- `pendente` → aguardando "Gerar ERP"
- `processando` → em envio pro ERP
- `criado_erp` → pedido criado no ERP (com `pedido_erp_criado_id`)
- `cancelado` / `erro` → fora do fluxo ativo


### 🧪 LIÇÕES APRENDIDAS (atualizado na sessão 2026-09-21 parte 9)

**16. `@media print` — regra genérica `.modal.show` sobrescreve `#id`**
O erro clássico foi colocar no final do `@media print` uma regra genérica
`.modal.show { display: block !important }`. Em CSS, quando duas regras têm
mesma especificidade, a **última vence**. Portanto `#modalAcerto { display: none }`
antes ficava sobrescrito. Solução: **remover** a regra genérica ou reforçar
com `html body #modalAcerto` (aumenta especificidade).

**17. `visibility: hidden` NÃO remove do fluxo — use `display: none`**
`visibility: hidden` esconde o conteúdo mas o elemento continua ocupando
espaço e sendo impresso como área branca. Sempre que quiser "sumir" do
papel, use `display: none` ou `position: absolute; left: -99999px`.

**18. Devolução NÃO cria `frota_acerto_pedido` — regras que só olham essa
tabela bloqueiam incorretamente**
Devolução cria registro em `frota_problema_tratamento` (com `tipo_tratamento =
'devolucao_comprovante'`), mas **não** cria `frota_acerto_pedido`. Portanto
qualquer verificação tipo "essa entrega já foi tratada?" precisa considerar
**as duas fontes**:
- Pedido em `frota_acerto_pedido` (faltante)
- OU `numero_comprovante` preenchido em `frota_problema_tratamento` (devolução)

**19. Botão dinâmico: sempre reescrever `onclick` E `innerHTML` via JS**
Quando o HTML do PHP pode estar em cache no navegador do usuário, é preciso
que o JS force o texto e o `onclick` corretos. Ex:
```js
btn.innerHTML = '<i class="fa-solid fa-print"></i> Reimprimir Comprovante';
btn.setAttribute('onclick', 'reimprimirComprovanteConferencia()');
20. window.print() e modais Bootstrap — cuidado com dupla abertura
Se #modalAcerto (grande) e #modalComprovanteConferencia (pequeno) estão
ambos abertos no DOM, o window.print() imprime os dois. É preciso
esconder o modal de fundo antes do print. Duas abordagens:

CSS (@media print com html body #modalAcerto { display: none })

JS (fechar/inline style display: none no #modalAcerto antes de print)
A abordagem CSS é preferível porque não altera o estado do DOM.

⏳ PENDENTE — PRÓXIMOS BLOCOS
Bloco 5 — CSS acerto-embarque.css
Revisar estilos de todos os modais

Padronizar espaçamentos com Tailwind-like

Unificar tema claro/escuro

Bloco 6 — DashboardController.php (KPIs)
KPIs de faltante (com/sem estoque)

KPIs de devolução

Valor total por tipo de tratamento

Tempo médio até faturamento (comprovante)

Bloco 7 — gestao-cargas.js
Integração com o acerto (buscar pedidos gerados)

Filtros de status

Bloco 8 — Testes integrados
Fluxo completo: motorista → acerto → ERP/comprovante → faturamento

Testes de concorrência (dois gestores no mesmo acerto)

Bloco 9 — Limpeza + Token final
Remover backups .bak_*

Consolidar tokens

Bloco 10 — Melhorias visuais
Animações sutis

Loading skeletons

Feedback háptico

🎬 PROMPT SUGERIDO PARA RETOMAR
"Continuando a sessão 2026-09-21 (Migração Lógica Faltante/Devolução).

Status:

Bloco 1 ✅ (Banco)

Bloco 2 ✅ (AcertoEmbarqueController backend)

Bloco 3 ✅ (ERPPedidoService)

Bloco 4 ✅ (Frontend + Backend — Etapas 1, 2, 3, 4 + Reimpressão + Opção A + Opção C)

Bloco 4.1 ✅ (Bloqueio de duplicação — backend + frontend + UX)

Bloco 4.2 ✅ (Ajustes finos: impressão, regra conferência, reimpressão, logo)

Commitado em 5a05b76 + 785d0d6 + 9044a72 + próximo commit

Deploy em produção realizado

Próximo passo: Bloco 5 — CSS acerto-embarque.css

Regras de trabalho (seguir sempre):

Toda alteração em método/função: entregar COMPLETO

Sempre validar com php -l

Sempre testar via curl.exe ou terminal

Backup antes de substituir

Registrar lições no token

Me forneça o Bloco 5."

📁 ARQUIVOS ATIVOS PARA A PRÓXIMA SESSÃO
text
v1/src/Controllers/Frota/AcertoEmbarqueController.php ⭐ (backend fechado)
v1/src/Controllers/Frota/EntregaController.php ⭐ (auto-finalize aplicado)
v1/src/Services/Frota/ERPPedidoService.php (backend fechado)
v1/routes/api.php (rotas registradas)
portal/modules/frota/acerto-embarque.php ⭐ (footer com botão reimprimir)
portal/modules/frota/assets/acerto-embarque.css ⭐ (@media print v4)
portal/modules/frota/assets/acerto-embarque.js ⭐ (Bloco 4.1 + 4.2 completo)
🎉 OBSERVAÇÃO FINAL
Sessão muito produtiva:

Bloco 4.1 fechado (bloqueio de duplicação + UX proativa)

Bloco 4.2 fechado (impressão corrigida + regra de conferência ajustada +
botão de reimpressão + logo no comprovante)

Bug CSS crítico encontrado e corrigido (regra .modal.show genérica)

Regra de negócio ajustada (devolução tratada não bloqueia conferência)

Próxima sessão:

Bloco 5 — CSS acerto-embarque.css

Tempo estimado: 1-2 horas

Risco: baixo (backend 100% estável, foco é frontend)