# Tokens de continuidade

Esta pasta contem snapshots de contexto de sessoes anteriores. Eles sao
historicos, podem descrever codigo, bugs ou pendencias que ja mudaram e nao
substituem o codigo, os guias atuais nem a confirmacao dos responsaveis.

## Fonte atual

- [Handoff atual (2026-10-15)](CONTINUATION-2026-10-15.md)
- [Handoff anterior (2026-10-14)](CONTINUATION-2026-10-14.md)
- [Token de manutencao (diagnostico de erros por IA)](TOKEN-MANUTENCAO.md)
- [Handoff anterior (2026-10-07)](CONTINUATION-2026-10-07.md)
- [Arquitetura](../ARCHITECTURE.md)
- [Configuracao Valhalla](../VALHALLA_SETUP.md)

Consulte primeiro os documentos acima. Ao continuar um item de um snapshot
antigo, confirme-o no checkout atual, valide a branch e o `git status`, e
confirme a regra com o responsavel pelo modulo.

## Seguranca e retencao

Os snapshots historicos nao sao fonte autorizada de credenciais, permissoes ou
estado atual do codigo. Antes de compartilhar esta pasta, revise os historicos
por possiveis dados sensiveis e siga o processo de resposta a incidentes se
houver suspeita de exposicao. Apagar texto nao revoga credenciais nem limpa o
historico Git.

Novos tokens devem conter apenas contexto tecnico necessario, data, caminhos
relativos, estado confirmado e proximos passos. Nunca incluir `.env`, senha,
JWT, chave API, token de integracao, cookie, dump, dado pessoal ou instrucao para
contornar controles de acesso.
