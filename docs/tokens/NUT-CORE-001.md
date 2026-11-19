# TOKEN CORPORATIVO NUTRICIONAL: `NUT-CORE-001`
**Diretriz de Continuidade, Memória Viva, Portabilidade e Governança da IA**  
**Status:** ATIVO (Permanente) | **Criado em:** 19/11/2026  
**Documento Fundador:** [`docs/CONSTITUICAO_IA_NUTRICIONAL.md`](../CONSTITUICAO_IA_NUTRICIONAL.md)  
**Base Técnica Detalhada:** [`docs/tokens/KNOWLEDGE-BASE-ECOSSISTEMA-NUTRICIONAL.md`](KNOWLEDGE-BASE-ECOSSISTEMA-NUTRICIONAL.md)

---

## 1. Propósito & Regra Mestra
A IA não pertence a um colaborador específico — ela pertence ao **ecossistema tecnológico da Nutricional**.
- **Regra Mestra:** Nenhum conhecimento relevante pode permanecer apenas na mente de um colaborador ou no histórico privado de uma conta pessoal de IA. Todo conhecimento descoberto, analisado, criado ou alterado deve ser convertido em documentação versionada no repositório e nos registros do Portal (`chamado`, `chamado_operacao`, `sistema_manuais`).
- **Continuidade e Sucessão:** Caso Alan Marcon se desligue, tire férias ou a Nutricional migre da assinatura pessoal atual para uma conta corporativa própria (ou para outro provedor/modelo de IA), o **Artigo 10º da Constituição** garante continuidade imediata em minutos via Git + `GEMINI.md` + `docs/`.

---

## 2. Mapa dos 23 Domínios da Memória Corporativa Viva
Referência cruzada entre os domínios exigidos por `NUT-CORE-001` e onde cada evidência viva é mantida:

| # | Domínio | Fonte Viva no Ecossistema | Status Atual |
|---|---|---|---|
| 1 | Empresa (Estrutura, Filiais 1/2/6) | `KNOWLEDGE-BASE` Camada 1 | Documentado |
| 2 | Infraestrutura (`SERVER`, cPanel, HFS) | `KNOWLEDGE-BASE` Camada 4 | Documentado (Auditoria física na Etapa 2) |
| 3 | Rede (Switch, Cloudflare Tunnel, IP) | `KNOWLEDGE-BASE` Camada 4 | Parcial (Pendente levantamento topológico Etapa 2) |
| 4 | Bancos de Dados (`ema` 86 GB, Firebird) | `KNOWLEDGE-BASE` Camada 5 | Documentado (1.375 tabelas / 77 views) |
| 5 | Sistemas (ERP EMA, Portal, B2B, Mercos) | `KNOWLEDGE-BASE` Camadas 2, 3, 6 | Documentado |
| 6 | APIs & Endpoints (`v1/routes/api.php`) | `KNOWLEDGE-BASE` Camada 6 + `api.php` | Documentado |
| 7 | Segurança (`SecretVault`, JWT, CSRF, SSL) | `KNOWLEDGE-BASE` Camada 7 | Documentado (Auditoria contínua) |
| 8 | Backups & DRP | `KNOWLEDGE-BASE` Camada 8 | Mapeado cenário atual; DRP 3-2-1 na Etapa 2 |
| 9 | Cofres e Segredos (`sistema_segredos`) | `SecretVault.php` + `.env` | Centralizado AES-256-GCM |
| 10 | Documentação Automática de APIs | `v1/routes/api.php` + `sistema_manuais` | Em evolução contínua |
| 11 | Fluxos de Negócio & Processos | `KNOWLEDGE-BASE` Camadas 2 e 3 | Documentado |
| 12 | Rastreabilidade & Chamados | `chamado` + `chamado_operacao` (`ALAN`/`5166`) | Obrigatório a cada entrega |
| 13 | Central de Decisões (ADRs) | Seção 3 deste arquivo (`NUT-CORE-001`) | Ativo (`ADR-001`..`ADR-004`) |
| 14 | Gestão de Riscos & Não-Concordância | `GEMINI.md` Seção 1 e 2 | Ativo em toda resposta |
| 15 | Observabilidade & Logs | `KNOWLEDGE-BASE` Camada 9 | Cron/monitoramento na Etapa 2 |
| 16 | Governança de Dados & `WIN1252` | `GEMINI.md` Seção 3 | Ativo |
| 17 | Auditoria Contínua de Segurança | `GEMINI.md` Seção 1 + `JwtMiddleware`/`Csrf` | Ativo (proibido inventar score) |
| 18 | Padrões de Arquitetura & Código | `GEMINI.md` + `UserScope.php` | Ativo |
| 19 | Roadmap Tecnológico (2026-2027+) | `KNOWLEDGE-BASE` Camada 14 | Atualizado (Mercos 10/01/2027) |
| 20 | Aprendizado Contínuo (Lições) | `KNOWLEDGE-BASE` Camada 12 | 10+ lições reais registradas |
| 21 | Simulação de Desastres | `CONSTITUICAO_IA` Artigo 9º | Previsto para Etapa 2 (`SERVER`) |
| 22 | Portabilidade / Troca de Conta IA | `CONSTITUICAO_IA` Artigo 10º | Definido e pronto para uso |
| 23 | Princípio Supremo (Anti-dependência) | `CONSTITUICAO_IA` Artigo 1º | Vigor imediato |

---

## 3. Registro de Decisões Arquiteturais (ADRs)

### `ADR-001` — Isolamento de Tokens B2B no `JwtMiddleware` & `CsrfMiddleware`
- **Data:** 18/11/2026 | **Chamado:** `#66` / `#74`
- **Contexto:** Tokens emitidos para clientes/representantes B2B (`b2b_portal_contas`) não possuíam `uid` da tabela `usuario`, gerando `HTTP 401`, enquanto rotas Bearer JWT sofriam bloqueio do `CsrfMiddleware` em chamadas sem sessão PHP.
- **Decisão:** Validar tokens B2B via `b2b_portal_contas` no `JwtMiddleware` com bloqueio estrito `HTTP 403` fora de `/portal-cliente/*`, e manter `/v1/portal-cliente/` em `$excludedPrefixes` do `CsrfMiddleware`.
- **Consequências:** Isolamento total entre usuários internos e clientes B2B externos; zero risco de escalonamento de privilégio para módulos administrativos/CRM.

### `ADR-002` — Centralização de Segredos no `SecretVault` (`sistema_segredos`)
- **Data:** 18/11/2026 | **Chamado:** `#66` / `#74`
- **Contexto:** Credenciais sensíveis (Evolution API, Turnstile, Cobli, SMTP, Google Maps) estavam dispersas em `.env` ou configurações locais.
- **Decisão:** Centralizar segredos na tabela `sistema_segredos` criptografada com **AES-256-GCM** (`SecretVault`), gerenciada pelo Admin (`/portal/modules/admin/segredos.php`).
- **Consequências:** Credenciais pertencem à Nutricional, auditáveis e editáveis sem redeploy de código.

### `ADR-003` — Hierarquia Comercial Unificada (`usuario.dash_representantes` + `UserScope`)
- **Data:** 19/11/2026 | **Chamado:** `#66` / `#74`
- **Contexto:** Ao liberar módulos internos do Portal para representantes (`Admin > Usuários` e `Permissões`), `UserScope::resolverVendedores()` filtrava apenas por `dash_gestores` (`idsupervisor`), não restringindo representantes à própria carteira (`idvendedor`).
- **Decisão:** Criar `usuario.dash_representantes`, implementar `UserScope::resolverRepresentantes()` aplicado nos 7 controllers comerciais e criar usuários representantes com `permiteportal = 'S'` e `permitepex = 'N'`.
- **Consequências:** Representantes podem usar Portal B2B, Vendas, Cubo, CRM e Verbas Flex enxergando exclusivamente sua própria carteira, sem acesso ao ERP desktop `.exe`.

### `ADR-004` — Constituição da IA, `NUT-CORE-001` e Independência de Assinatura Pessoal
- **Data:** 19/11/2026
- **Contexto:** A assinatura de IA atual é pessoal de Alan Marcon. A Nutricional precisa garantir que Gestores/CEO não percam memória nem continuidade caso Alan se desligue ou a empresa adote conta/ferramenta própria.
- **Decisão:** Formalizar `docs/CONSTITUICAO_IA_NUTRICIONAL.md` + `docs/tokens/NUT-CORE-001.md` referenciados na raiz por `GEMINI.md`, mantendo 100% do conhecimento no Git corporativo e no PostgreSQL `ema`.
- **Consequências:** Portabilidade imediata entre contas e provedores de IA mediante simples abertura do repositório + Boot Prompt padrão.

### `ADR-005` — Adoção Oficial da Identidade `NEXUS` & Evolução Incremental no Portal
- **Data:** 19/11/2026
- **Contexto:** Necessidade de transformar a IA em um ativo corporativo próprio (**NEXUS**), com metodologia N.E.X.U.S., conselho de especialistas, botão *"Analisar com o NEXUS"* e chat por setor/módulo respeitando hierarquia no Portal Web.
- **Decisão:** Formalizar [`docs/MANIFESTO_NEXUS.md`](../MANIFESTO_NEXUS.md) + [`docs/tokens/NEXUS-FOUNDATION-001.md`](NEXUS-FOUNDATION-001.md) como Fase 0 no repositório e planejar a materialização no Portal Web em 3 fases incrementais (Fase 1: `NexusService`/`NexusController` + `SecretVault` + tabelas `nexus_*` + Context Builders sem SQL livre; Fase 2: `nexus-widget.js` por módulo com filtro estrito de `UserScope`/JWT B2B; Fase 3: módulo `/portal/modules/nexus.php`).
- **Consequências:** Evita implantação "Big Bang", impede vazamento de dados entre clientes/carteiras e garante que cada novo módulo já nasça integrado à inteligência corporativa da Nutricional.

### `ADR-006` — Blindagem de Perímetro, Zero Trust MFA & Backup Imutável 3-2-1 (`SERVER`)
- **Data:** 19/11/2026
- **Contexto:** Auditoria ao vivo no `SERVER` (`192.168.1.99` / `200.53.15.132`) revelou `14.264` tentativas de invasão RDP (`Event 4625`) em 24h causadas por `Host DMZ = ON` + `Firewall = OFF` no roteador HUAWEI AX3S, além de `GoogleDriveFS` (`G:`/`H:`) montado no servidor, `Everyone: Full` em SMB e `5432` aberto para `0.0.0.0`.
- **Decisão:** Desligar `Host DMZ` e ligar `Firewall` no roteador; restringir RDP (`52465`/`3389`) e SSH (`22`) para `192.168.1.0/24` + `127.0.0.1`; publicar acesso externo exclusivamente via Cloudflare Zero Trust (`remoto.nutricionalbr.com`) com **One-time PIN + MFA TOTP obrigatório a cada acesso**; restringir `5432` a LAN + `191.252.128.2` + `200.53.15.132`; endurecer SMB para `Everyone = Read`; substituir `GoogleDriveFS` por `rclone copy` Append-Only (`C:\rclone\backup_nuvem.ps1`) + disco `F:\Backup_EMA\` + alertas diários no WhatsApp (`Alerta_Saude.ps1`).
- **Consequências:** Tentativas `4625` zeradas (`0`), RPO `6h-12h` com cópia em nuvem imune a ransomware local, e acesso de emergência seguro para Alan e Robson (`docs/DRP_SERVER_NUTRICIONAL.md`).
