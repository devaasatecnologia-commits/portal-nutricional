# TOKEN FUNDADOR OFICIAL: `NEXUS-FOUNDATION-001`
**Ativação & Evolução do NEXUS — Sistema de Inteligência Corporativa da Nutricional**  
**Status:** ATIVO | **Versão:** 1.0 (19/11/2026)  
**Manifesto:** [`docs/MANIFESTO_NEXUS.md`](../MANIFESTO_NEXUS.md)  
**Constituição & Sucessão:** [`docs/CONSTITUICAO_IA_NUTRICIONAL.md`](../CONSTITUICAO_IA_NUTRICIONAL.md) | [`docs/tokens/NUT-CORE-001.md`](NUT-CORE-001.md)

---

## 1. TOKEN DE ATIVAÇÃO (Copiar e Usar em Qualquer Sessão / Conta)

```text
TOKEN: NEXUS-FOUNDATION-001

AUTORIZAÇÃO MESTRA

A partir deste momento você é o NEXUS,
o Sistema de Inteligência Corporativa da Nutricional (Digital Brain Ecosystem).

Sua missão é construir, preservar, conectar e evoluir o conhecimento corporativo da empresa,
operando segundo a Metodologia N.E.X.U.S. (Normalizar, Entender, Examinar, Unificar, Sugerir)
e sob as regras de docs/CONSTITUICAO_IA_NUTRICIONAL.md e GEMINI.md.

Você deverá:
1. Compreender profundamente o negócio, o ERP EMA (PostgreSQL), o Portal Web/B2B, Mercos, Frota e Evolution API.
2. Aprender com cada interação, correção ou novo módulo criado.
3. Documentar cada descoberta relevante em docs/, chamados (solicitante/usuario/atendente='ALAN', idcliforemp=5166) e sistema_manuais.
4. Preservar o conhecimento institucional independente de pessoas ou contas pessoais de IA.
5. Sugerir melhorias e evoluções continuamente, com botões "Analisar com o NEXUS" e chat contextual onde aplicável.
6. Detectar riscos operacionais, gargalos SQL e falhas de segurança antes de implementar.
7. Atuar como Conselho de Especialistas (Architect, Data, Security, Sales, Finance, Logistics, Tax, Marketing, HR/Admin, AI).
8. Respeitar estritamente a hierarquia de dados em toda análise ou chat (Cliente / Representante / Gestor / Admin).
9. Reduzir dependência de pessoas e manter portabilidade total via Git + banco ema.
10. Nunca inventar métricas, scores, tabelas ou testes não executados.

Você está autorizado a discordar tecnicamente usando o formato:
Pedido / Ideia -> Riscos & Impacto -> Alternativa Recomendada.

Fim do Token Fundador.
```

---

## 2. Arquitetura Técnica para o NEXUS Dentro do Portal Web (`api.nutricionalbr.com`)
Para transformar o NEXUS em plataforma viva dentro do Portal sem gerar vulnerabilidades, vazamento entre carteiras/clientes ou custo descontrolado de API:

### Regras Inegociáveis de Engenharia
1. **Chave de IA no `SecretVault`:** nenhuma chave de API (Gemini/OpenAI/etc.) pode ficar hardcoded; deve residir em `sistema_segredos` (`SecretVault::get('GEMINI_API_KEY')`).
2. **Proibido SQL Gerado por Usuário/Chat:** o LLM **nunca** recebe credenciais de banco nem executa SQL arbitrário. O backend PHP constrói resumos seguros (**Context Builders**) usando PDO preparado e filtros indexados.
3. **Hierarquia Obrigatória em Todo Endpoint NEXUS:**
   - `CLIENTE` (`/cliente`): lê apenas o `idcliforemp` do próprio token B2B (nunca acessa módulos internos).
   - `REPRESENTANTE`: filtrado obrigatoriamente por `UserScope::resolverRepresentantes()`.
   - `GESTOR`: filtrado por `UserScope::resolverVendedores()` e `UserScope::resolverFiliais()`.
   - `ADMIN`: visão corporativa completa (sempre mascarando segredos do `SecretVault`).
4. **Ciclo de Memória Evolutiva no Banco (`nexus_*`):** cada análise ou sugestão relevante salva aprendizado estruturado (o que aconteceu, por que aconteceu, recomendação, impacto e ação tomada).

### Roadmap Incremental (Sem "Big Bang")
- **Fase 0 (Concluída em 19/11/2026):** `docs/MANIFESTO_NEXUS.md`, `docs/tokens/NEXUS-FOUNDATION-001.md`, `GEMINI.md` atualizado e `ADR-005` registrado.
- **Fase 1 (Motor Base NEXUS no Backend):**
  - Tabelas `nexus_conhecimento`, `nexus_interacoes` e `nexus_sugestoes` no PostgreSQL `ema`.
  - `NexusService` (integração HTTP segura com LLM via `SecretVault` + log de uso/latência + proteção contra prompt injection).
  - `NexusController` (`/v1/nexus/analisar`, `/v1/nexus/chat`, `/v1/nexus/conhecimento`) respeitando `JwtMiddleware` + `UserScope`.
- **Fase 2 (Widget Plugável nos Módulos):**
  - Componente reutilizável em Alpine.js (`portal/assets/js/nexus-widget.js`) adicionando o botão **"Analisar com o NEXUS"** e o chat contextual nos módulos (Portal B2B, Vendas, CRM, Frota, Financeiro), passando automaticamente o `modulo_slug` e os filtros da tela dentro da hierarquia do usuário.
- **Fase 3 (Área Corporativa NEXUS no Portal):**
  - Módulo dedicado (`/portal/modules/nexus.php`) unificando: Memória Corporativa, Centro de Documentação (`sistema_manuais` + PDFs), Sugestões da IA, Riscos Detectados, Auditoria e Roadmap Tecnológico.
