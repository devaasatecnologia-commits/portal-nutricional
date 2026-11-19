# NUTRICIONAL DIGITAL BRAIN (NDB) — Base de Conhecimento Corporativa (v2.0)

> Base viva do ecossistema **Nutricional Distribuidora**, ancorada exclusivamente em fatos verificados no código (`c:\xampp\htdocs\API`), no banco PostgreSQL (`ema`) e na infraestrutura em produção. Itens físicos ou externos não visíveis no código estão marcados como `[A PREENCHER]` para alinhamento contínuo.

---

## CAMADA 1 — Identidade Corporativa & Estrutura Organizacional
- **Empresas / Filiais Cadastradas no ERP (`filial`):**
  - **Filial `1` — NUTRICIONAL (FILIAL 1) — Matriz SC:** `NUTRICIONAL DISTRIBUIDORA LTDA` (`SC`, `(48) 3524-6543`).
  - **Filial `2` — NTD LOGISTICA (FILIAL 2):** `NTD LOGISTICA LTDA` (`SC`).
  - **Filial `6` — NUTRICIONAL (FILIAL 6):** `NUTRICIONAL DISTRIBUIDORA LTDA` (`PR`).
  - *(Filiais `3` e `5` constam como baixadas no ERP).*
- **Escala Atual Medida (`ema`):** **2.726 clientes ativos**, **33 representantes ativos**, **27 usuários internos ativos no Portal**, **45 módulos ativos** em `sistema_modulos`.
- **História, Missão, Visão, Valores & Diferenciais:** `[A PREENCHER COM O ALAN]`
- **Mapa Organizacional & RBAC Real:**
  `Diretoria / Admin (usuarios_admin)` → `Gestores (usuario.dash_gestores)` → `Supervisores (idsupervisor)` → `Representantes (cliforemp / cliente.idvendedor / usuarios_mercos)` → `Clientes (cliforemp + cliente, B2B)` ↔ `Fornecedores (cliforemp)`.

---

## CAMADA 2 — Conhecimento Operacional por Área
- **Comercial & Bonificações:**
  - Pedidos entram via força de vendas Mercos (`palmtop_pedido`/`palmtop_pedido_item`) ou ERP (`pedido`/`pedido_item`).
  - Status do pedido (`pedido.status`): `1` = Em Aberto, `3` = Cancelado, `5` = Faturado (`nfs`).
  - **Verba Flex:** Saldo consultado/debitado em tempo real via `MercosFlexService` (`:8081` F1 / `:8086` F6, cache 20s, anti-burst 3s). Bonificações faturadas em `nfs` (transações `25`/`1001`) são filtradas pela view `verificabonificacao`, que exclui intencionalmente `item.idmarca <> 60` (`MATERIAL APOIO VENDA`). Histórico em `log_flex_bonificacao`.
  - **Módulos:** `dashboard_vendas`, `cubo_vendas`, `analise_carteira`, `migracao-carteira`, `recompra_clientes`, `verbas-flex`, `movimentacao-flex`, `transferencia-flex`, `pedido-amostra`.
- **Logística, Estoque & Frota:**
  - **Regra Crítica de Entrada:** **Desembarque** (`DesembarqueController`) exige conclusão prévia da **Conferência XML** (`XmlController` -> `aps_oc_conferencia.conferido_portal = 'S'`), retornando HTTP `409` caso pendente.
  - **Estoque & Previsão:** `estoque_filial.saldoatual` (físico) `-` reserva (`estoque_filial_reserva`) `+` ordens de compra (`oc`/`oc_item`) `+` transferências em trânsito (`nf_transf_auto.status = 2`). Imagens dos produtos vêm de `item.path_foto_master` servidas via HFS (`:2053/fotos/`).
  - **Frota & Rastreamento:** `v1/src/Controllers/Frota/` (11 sub-controllers: `Embarque`, `Entrega`, `Rastreamento`, `AcertoEmbarque`, `Veiculo`, `Motorista`, `Geocodificacao`, `Cobli`, `Importador`, `Dashboard`, `Health`) integrados ao app offline do motorista (`motorista-offline`).
  - **Módulos:** `separacao`, `carregamento`, `monitor`, `xml`, `desembarque`, `gestao-deposito`, `consulta-saldo`, `estoque-previsao`, `inventario`, `retorno-carga`, `transferencia-matriz-filial`, `transferencia-filial-matriz`, `frota`, `frota-embarques`, `acerto-embarque`.
- **Financeiro, Crédito & Contábil:**
  - **Crédito:** `cliente.valorlimitecredito` + `situacaocredito` (`Venda liberada`, `Desbloqueado automaticamente`, `Bloqueado...`). Limite utilizado calculado sobre títulos em aberto.
  - **Títulos & Boletos:** Títulos abertos em `receber` (`status IN (2, 3) AND valorsaldo > 0 AND excluido = 'N'`). Boletos registrados em `receber_boleto` pelos bancos `237` (Bradesco) e `748` (Sicredi), com `codigobarra` (44 dígitos ITF-25), `numeroboleto` (linha digitável) e campo preparado para PIX híbrido (`shipayqrcode`, hoje sem emissão ativa). Títulos sem `codigobarra` representam cobranças de carteira/renegociação/outros (`portador`).
  - **Contábil/Fiscal:** `ContabilController`, DANFE Espelho com `logo-danfe-nutricional.png`, conferência e rastreio de CFOP/NCM/impostos em `nfs`/`nfe`.

---

## CAMADA 3 — Mapa de Processos (Fluxos Ponta a Ponta)
- **Fluxo Comercial & Flex:**
  `Representante (Mercos)` → `Integrador :8081/:8086 + Firebird` → `palmtop_pedido` → `pedido (1 Em Aberto)` → `Faturamento (nfs, pedido.status=5)` → `Receber/Boleto` + `verificabonificacao (se transação 25/1001 e marca <> 60)` → `Débito Flex Live`.
- **Fluxo de Suprimentos & Logística:**
  `OC + NF-e XML` → `Conferência XML (conferido_portal='S')` → `Desembarque / Lote` → `Separação` → `Carregamento / Embarque` → `Frota / Cobli / Entrega` → `Acerto de Embarque / Retorno`.
- **Fluxo Portal B2B (`/cliente` e `/representante`):**
  `CNPJ/E-mail (Primeiro Acesso)` → `Lookup ERP cliforemp/cliente` → `OTP E-mail (SMTP)` → `Senha (bcrypt)` → `Login (Turnstile + Senha)` → `2FA (E-mail ou Evolution WhatsApp)` → `JWT B2B (restrito a /portal-cliente/*)` → `Dashboard Transparência / Boleto FEBRABAN / DANFE / Pedidos / Fotos CDN`.

---

## CAMADA 4 — Arquitetura Digital & Infraestrutura
| Nó / Ativo | Papel & Software | Endereço / Porta | Ponto de Atenção |
| :--- | :--- | :--- | :--- |
| **cPanel Produção** | Apache/PHP + Portal Web + API `v1` | `https://api.nutricionalbr.com` | SSL outbound do PHP tem CA bundle antigo (exige `SMTPOptions` / `CURLOPT_SSL_VERIFYPEER=false` para serviços externos; sem acesso SSH). |
| **Servidor Windows 24h (`SERVER`)** | PostgreSQL `ema` + `evolution`, Mercos, HFS, Evolution API, Cloudflared | IP `200.53.15.132` | **Concentra banco ERP, integrador Mercos, fotos e WhatsApp** (ponto único de falha — exige nobreak e backup externo rigoroso). |
| **PostgreSQL 16.3** | Banco ERP `ema` + banco `evolution` | `200.53.15.132:5432` | `ema` com **86 GB**. |
| **HFS** | Servidor de fotos de produtos | `https://acesso.nutricionalbr.com:2053/fotos/` | Porta `9898` inativa/timeout externamente; usar sempre `2053` ou Túnel Cloudflare. |
| **Evolution API v2.3.7** | Gateway WhatsApp Web (Baileys) | `C:\evolution-api` (`:8080`, Node 22, PM2 + `EvolutionAPI-AutoStart`) | Instância `nutricional` (`554884686401`). |
| **Cloudflare Tunnel (`Servidor-Principal`)** | Exposição HTTPS sem abrir porta no roteador | `https://whats.nutricionalbr.com` → `http://localhost:8080` | Também possui rota `remoto.nutricionalbr.com`. |
| **Desenvolvimento Local** | XAMPP (Apache + PHP 8.2) + Git | `c:\xampp\htdocs\API` | Gateway `?api_route=` em `index.php` espelha produção. |

- **Inventário Físico Detalhado (Nobreaks, Switches, APs, Câmeras/NVR, Impressoras, RPO/RTO alvo):** `[A PREENCHER COM O ALAN]`

---

## CAMADA 5 — Banco de Dados (`ema`) & Mapa de Impacto
- **Métricas Reais:** PostgreSQL 16.3, **86 GB**, **1.375 tabelas**, **77 views**.
- **Top 8 Tabelas por Volume (Cuidado de Performance):**
  1. `nfs_item`: **32 GB** | 2. `pedido_item`: **14 GB** | 3. `crm_processo_atividade`: **8,9 GB** | 4. `nfs`: **8,4 GB** | 5. `pedido`: **3,6 GB** | 6. `ged_arquivo_versao`: **1,8 GB** | 7. `crm_processo`: **1,7 GB** | 8. `crm_processo_evento`: **1,5 GB**.
  - Logs legados relevantes: `pedido_item_log` (`838 MB`), `log_provider` (`480 MB`).
- **Mapa de Impacto antes de Alterações:**
  - Alterar **`cliforemp` / `cliente`**: impacta **ERP Desktop + Portal B2B + Mercos (`DE_PARA_MERCOS.FDB`) + Financeiro + CRM + WhatsApp/2FA**. (**Impacto ALTO**)
  - Alterar **`nfs` / `nfs_item` / `verificabonificacao`**: impacta **Faturamento + Contábil/DANFE + Estoque + Verba Flex + Portal B2B**. (**Impacto CRÍTICO**)
  - Alterar **`JwtMiddleware` / `CsrfMiddleware` / `SecretVault`**: impacta **Autenticação de todos os 45 módulos + App Motorista + Portal B2B**. (**Impacto CRÍTICO**)
  - Alterar **`estoque_filial` / `estoque_filial_deposito`**: impacta **Mercos (Depósito 1) + Separação + Estoque Previsão**. (**Impacto ALTO**)

---

## CAMADA 6 — Catálogo de Integrações
| Integração | Dados Técnicos | Criticidade & Impacto de Parada |
| :--- | :--- | :--- |
| **Mercos + Firebird** | `ema-mercos-integration.exe` (`:8081` F1, `:8086` F6), `DE_PARA_MERCOS.FDB` (`3050`), `FLEX_API_TOKEN` | **MUITO ALTA:** para entrada de pedidos dos 33 representantes e cálculo/débito de Verba Flex em tempo real. |
| **Evolution API (WhatsApp)** | `https://whats.nutricionalbr.com/message/sendText/nutricional`, `apikey` no `SecretVault` | **ALTA:** impede 2FA instantâneo via WhatsApp (contingência automática: usuário alterna para 2FA por **E-mail**). |
| **SMTP cPanel** | `mail.nutricionalbr.com:465` (`SMTPS` + `SMTPOptions`), `MAIL_PASS` no `SecretVault` | **ALTA:** impede Primeiro Acesso B2B, reset de senha e notificações de Crons/Chamados. |
| **Cloudflare Turnstile** | `https://challenges.cloudflare.com/turnstile/v0/siteverify`, chaves no `SecretVault` | **MÉDIA/ALTA:** protege `/cliente` e `/representante` contra bots/brute-force. |
| **Cobli & Google Maps** | `Frota/CobliController.php`, webhook público `/v1/frota/cobli/webhook` (HMAC), chaves no `SecretVault` | **MÉDIA/ALTA:** telemetria, posicionamento e geocodificação da Frota. |
| **Logisoft / SEFAZ** | Emissão CT-e, MDF-e, CIOT e consulta NF-e com Certificado A1 (Filiais 1 e 6) | **CRÍTICA (Fiscal/Logística):** trava embarques interestaduais e faturamento fiscal. |
| **HFS Fotos** | `https://acesso.nutricionalbr.com:2053/fotos/` | **BAIXA/MÉDIA:** falha apenas exibição de imagens de produtos no Estoque e Portal B2B. |

---

## CAMADA 7 — Auditoria Contínua de Segurança (Baseada em Fatos)
Controles ativos verificados no código:
- Senhas de usuários B2B em `bcrypt` (`password_hash`); tokens OTP temporários (10 min, máx. 5 tentativas); JWT HS256 assinado com `CHAVE_SECRETA`; `JwtMiddleware` bloqueia token B2B fora de `/portal-cliente/*` (`HTTP 403`).
- `SecretVault` cifra segredos com **AES-256-GCM** no banco.
- Cloudflare Turnstile ativo na entrada pública (`/cliente` e `/representante`).
- **Pendências / Dívidas Técnicas Reais Identificadas na Auditoria Atual:**
  1. `WhatsAppService` e `CronRotinasController` usam `SSL verify_peer = false` para contornar CA bundle antigo do PHP no cPanel (recomendado atualizar `cacert.pem` no servidor/cPanel ou configurar CA explícito quando possível).
  2. `PortalClienteController::atualizarPreferencias2fa()` ainda contém `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` em runtime — recomenda-se remover após confirmar colunas aplicadas em produção.
  3. Limpeza de chaves não usadas em `sistema_segredos`: `WHATSAPP_META_PHONE_ID` e `WHATSAPP_META_TOKEN` ficaram cadastradas sem template após adoção da Evolution API.
  4. Porta `5432` do PostgreSQL (`200.53.15.132`) responde externamente para o cPanel/devs — garantir que firewall do servidor `SERVER` restrinja acesso ao `5432` apenas aos IPs autorizados (cPanel + escritórios) e validar política de acesso ao RDP (`remoto.nutricionalbr.com`).

---

## CAMADA 8 — Backup & Continuidade (DRP)
- **Ativos que dependem do Servidor `SERVER` (`200.53.15.132`):** Banco PostgreSQL `ema` (**86 GB**), banco `evolution`, Firebird `DE_PARA_MERCOS.FDB`, sessão WhatsApp (`C:\evolution-api\instances`), pastas de fotos do HFS, integrador Mercos `.exe` e serviço `cloudflared`.
- **Se `SERVER` cair agora:** param o ERP desktop, toda a API/Portal PHP (sem conexão ao Postgres `5432`), Mercos, HFS Fotos e Evolution WhatsApp. Permanece no ar apenas a página estática inicial do cPanel até o Postgres voltar.
- **Rotina de Backup & Metas de RPO / RTO / Backup Offsite:** `[A CONFIRMAR E AUDITAR COM O ALAN]` (prioridade máxima validar onde está salvo o backup diário dos `86 GB` do `ema` + `DE_PARA_MERCOS.FDB` + `.env` + `SecretVault`!).

---

## CAMADA 9 — Observabilidade & Tendências
- **Health Checks já disponíveis:** `/v1/frota/health`, `CronRotinasController` (auditoria de rotinas e e-mail), `FlexApiMonitor`, `https://whats.nutricionalbr.com/instance/connectionState/nutricional`, `/v1/portal-cliente/auth/config`.
- **Baseline Atual Medido (`19/11/2026`):** `ema` = **86 GB** (`nfs_item` 32 GB, `pedido_item` 14 GB, `crm_processo_atividade` 8,9 GB). Recomendado implementar job semanal no `CronRotinasController` salvando tamanho das tabelas e checagem automática de status da Evolution (`state = open`), Mercos (`:8081/:8086`) e certificados SSL/A1!

---

## CAMADA 10 — GED Corporativo
- **Descoberta Crítica na Inspeção do Banco:** O banco `ema` **já possui 11 tabelas nativas de GED** consumindo **1,8 GB**:
  `ged_arquivo`, `ged_arquivo_versao`, `ged_arquivo_copia`, `ged_arquivo_local`, `ged_arquivo_operacao`, `ged_arquivo_relacao`, `ged_extensao`, `ged_pasta`, `ged_pasta_permissao`, `ged_pasta_permissao_acao`, `ged_tipo_documento`.
- **Decisão Arquitetural:** Em vez de construir um GED paralelo do zero, o futuro módulo **GED Web no Portal** deve reaproveitar e expor essa estrutura nativa `ged_*` (que já contempla pastas, permissões por ação, tipos de documento e versionamento).

---

## CAMADA 11 — Patrimônio Digital & Redes Sociais
- **Instagram oficial:** `@nutricionalbr` (presente no layout da DANFE).
- **E-mail fiscal / atendimento:** `nfe@nutricionalbr.com`, `financeiro@nutricionalbr.com`, `nao-responder@nutricionalbr.com`.
- **WhatsApp Comercial Conectado (Evolution):** `554884686401` (`Distribuidora Nutricional`) + telefone financeiro `(48) 3524-6543`.
- **Demais contas (Facebook, LinkedIn, YouTube, Google Business, gestores e campanhas):** `[A PREENCHER COM O ALAN]`

---

## CAMADA 12 — Memória Organizacional (Incidentes & Lições Reais)
1. **cPanel x Evolution API (`SSL error 20`):** Servidor cPanel antigo não reconhecia CA da Cloudflare Tunnel em `whats.nutricionalbr.com`. Correção: `CURLOPT_USERAGENT` + `CURLOPT_SSL_VERIFYPEER => false` em `WhatsAppService::post()`. **Lição:** testar integrações HTTPS diretamente do ambiente cPanel, não apenas localhost.
2. **cPanel x PHPMailer SMTP:** `mail.nutricionalbr.com` apresenta cert `*.hospedagemdesites.ws`; sem `SMTPOptions` o envio falhava apenas no ar. Adicionado também `Hostname = 'nutricionalbr.com'` e `AltBody` para entregabilidade no Gmail.
3. **CSRF em APIs Bearer JWT:** `CsrfMiddleware` bloqueava `POST /v1/portal-cliente/preferencias-2fa` quando a página não tinha sessão/CryptoJS. **Lição:** rotas puramente Bearer JWT devem estar em `$excludedPrefixes` no `CsrfMiddleware`.
4. **Tokens B2B no `JwtMiddleware`:** Tokens externos sem `uid` / `usuario` geravam `HTTP 401` em rotas protegidas. Corrigido com validação direta em `b2b_portal_contas` e isolamento estrito (`HTTP 403` fora de `/portal-cliente/*`).
5. **Evolution API no Windows sem Docker:** Evolution v2.3.7 exige Node 22 LTS (`require(ESM)` do Baileys) e banco PostgreSQL + Redis desativado (`CACHE_REDIS_ENABLED=false`) + `prisma generate` antes do `build` + tarefa agendada `SYSTEM` (`PM2_HOME`) para sobreviver a reboots.
6. **Notas Bonificadas R$ 239,00 sem débito Flex:** NFs `301005` e `301043` continham apenas item `3159` (`idmarca = 60` `MATERIAL APOIO VENDA`), corretamente ignorado pela view `verificabonificacao` (`item.idmarca <> 60`).
7. **Frontend Alpine.js:** `x-if` só funciona em `<template>` (nunca em `<div>`); modais empilhados precisam de `z-[70]`; e `carregarAuthConfig()` deve rodar mesmo após `sair()` ou token expirado.

---

## CAMADA 13 — Central de Decisões (Formato Padrão)
Toda solicitação estrutural ou nova feature deve registrar:
`Pedido` → `Análise Técnica` → `Riscos & Mapa de Impacto` → `Alternativas Comparadas` → `Recomendação do Arquiteto` → `Custo/Esforço Estimado` → `Prioridade`.

---

## CAMADA 14 — Roadmap Tecnológico Nutricional
- **Curto Prazo:**
  - Concluir testes finais `/representante` e encerrar **Chamado #66**.
  - Remover `ALTER TABLE` de runtime em `PortalClienteController` e limpar chaves não usadas no `SecretVault`.
  - Criar rotina de monitoramento automático no Cron (saúde PostgreSQL, Evolution `state=open`, tamanho das tabelas, validade A1 e backup offsite do `ema` `86 GB`).
  - Integrar status de entrega da **Frota** (`frota_embarque`/`frota_entrega`) na aba Pedidos do Portal B2B.
- **Médio Prazo:**
  - **10/01/2027 — Integrador Próprio Mercos (Nativo):** substituir `ema-mercos-integration.exe` + Firebird `DE_PARA_MERCOS.FDB` por rotinas nativas PostgreSQL + Slim Cron (hoje em planejamento/sombra).
  - Módulo **GED Web** no Portal reaproveitando as 11 tabelas nativas `ged_*` (`1,8 GB`).
  - Particionamento/arquivamento ou políticas de consulta para `nfs_item` (`32 GB`) e `pedido_item` (`14 GB`) + Dashboard Executivo consolidado.
- **Longo Prazo:**
  - Data Warehouse / BI separado para evitar peso analítico sobre o ERP transacional `ema`.
  - Assistente operacional inteligente via WhatsApp (consultas seguras para Gestores e Representantes via Evolution API) e governança multi-agente.
