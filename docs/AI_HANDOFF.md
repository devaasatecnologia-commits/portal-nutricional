# Handoff seguro para assistentes de IA

**Revisado:** 2026-10-06

**Objetivo:** fornecer contexto reutilizavel sem exportar credenciais, dados
operacionais ou o repositorio inteiro.

## Antes de anexar qualquer arquivo

Nao existe uma lista universalmente segura para **qualquer** IA. Use apenas
ferramentas/provedores aprovados pela empresa para codigo interno, confira
politica de retencao, treinamento, controles de acesso e local de processamento.
Se nao houver aprovacao, use apenas uma descricao generica sem codigo interno.

Este arquivo e um indice, nao autorizacao para upload. O proprietario do projeto
e o responsavel por dados devem aprovar cada envio. A remocao de um arquivo
depois do upload nao garante que o provedor apague copias, logs ou backups.

## Contexto base, somente quando o destino estiver aprovado

Comece anexando apenas o minimo:

1. `README.md`
2. `docs/HANDBOOK.md`
3. `docs/ARCHITECTURE.md`
4. `docs/tokens/CONTINUATION-2026-10-14.md` - token atual; conferir data/estado
   antes de cada uso.
5. `docs/tokens/TOKEN-MANUTENCAO.md` - para diagnosticar erro em um modulo.
6. `composer.json` - dependencias e autoload, se relevante a tarefa.

Para contexto de negocio e onboarding, adicionar seletivamente:

- `docs/PORTAL_GUIDE.md`
- `docs/GUIA_ENTREGA_CEO_PROGRAMADOR.md`
- `docs/SECRETS_ACCESS.md` - politicas sem valores de credenciais.

Esses arquivos descrevem sistema interno. Mesmo sem senhas, compartilhe-os
somente com um provedor autorizado pela empresa.

## Acrescente arquivos por tarefa (nao todos os modulos)

- API/controller: `v1/routes/api.php` e somente o controller/service/middleware
  implicado pela mudanca.
- Tela do portal: a pagina PHP afetada e seus scripts CSS/JS dependentes.
- PWA/Frota offline: pagina, JS/CSS e `portal/modules/frota/service-worker.js`
  relacionados ao comportamento; remover URLs privadas e dados de teste.
- Banco/migracao: SQL especifico e revisado, sem dumps, resultados de consulta,
  dados pessoais ou credenciais. Solicitar aprovacao do dono do banco antes do
  envio e da execucao.
- Deploy: `docs/DEPLOY_FTP_CPANEL.md` e configuracoes de exemplo revisadas;
  nunca configuracoes de servidor reais.

Identifique no pedido os arquivos exatos e o objetivo. Nao anexe o projeto
inteiro por conveniencia.

## Nao enviar

- `.env`, `.env.*` real, arquivos de configuracao preenchidos, `phpinfo.php`,
  cookies, sessoes, tokens JWT/API, chaves privadas, senhas, URLs privadas ou
  codigos MFA/recuperacao.
- Dumps, backups, exportacoes, respostas de banco, logs, dados de clientes ou
  funcionarios, fotos, evidencias de entrega ou arquivos de `uploads/`.
- `vendor/`, `vendor.bak_*`, caches, temporarios, `node_modules/`, artefatos de
  build ou arquivos locais que nao sejam necessarios a tarefa.
- Tokens historicos em `docs/tokens/token-*.md`; podem estar desatualizados ou
  conter informacoes que nao foram auditadas. Use somente o token atual
  `docs/tokens/CONTINUATION-2026-10-14.md`, depois de conferir que continua
  atual e que o envio foi aprovado.
- Em especial, nao envie `token-2026-09-22-parte-13.md` nem
  `token-2026-09-30-parte-05.md` antes da revisao humana: uma verificacao por
  padroes sinalizou possiveis campos sensiveis, sem confirmar se sao segredos
  reais.
- `.env.example` por padrao. Apesar de nao conter valores secretos, revela
  nomes de integracoes e variaveis; anexe apenas quando a tarefa exigir e o
  envio estiver autorizado.
- Qualquer arquivo em `docs/` sem revisar seu conteudo e historico.

Antes de cada envio, revise `git status --short` e os arquivos anexados. Nao
inclua alteracoes locais de terceiros nem assuma que arquivos ignorados pelo Git
estao limpos.

## Prompt-base

> Analise somente os arquivos anexados, confirme o escopo antes de sugerir
> mudancas e indique quais fatos vieram do codigo e quais precisam de
> confirmacao. Nao invente schema, regras operacionais, estados de producao ou
> credenciais. Trate o banco como externo; nao execute operacoes. Proponha
> alteracoes minimas, identifique riscos de compatibilidade e sugira verificacoes
> que possam ser executadas em desenvolvimento/homologacao. Nao solicite, revele
> ou reconstrua segredos/dados pessoais. Nao assuma que snapshots historicos sao
> atuais. A tarefa e: [descrever uma tarefa especifica].

## Manutencao

Atualize o token atual somente com fatos confirmados no checkout. Nao copie
historico de conversa, credenciais, dados pessoais, saidas de producao nem uma
lista completa de arquivos sem justificativa. O indice de tokens historicos esta
em `docs/tokens/README.md`; isso nao torna os arquivos historicos apropriados
para envio.
