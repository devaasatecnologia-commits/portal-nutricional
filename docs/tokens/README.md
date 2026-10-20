# Tokens de continuidade

Esta pasta contém snapshots de contexto de sessões anteriores (handoffs). Eles são
registros de continuidade para desenvolvedores e agentes de IA, descrevendo decisões,
estado de validação e pendências. Eles não substituem o código real nem a confirmação
dos responsáveis pelo banco ou deploy.

## Fonte atual

- **[Handoff atual (2026-10-19)](CONTINUATION-2026-10-19.md)**: Correção de fallbacks JWT (`'chave_super_secreta'`), Reset Administrativo de 2FA auditado, interfaces visuais de reset e saneamento de arquivos sensíveis.

## Handoffs recentes (Outubro/2026)

- **[Handoff 2026-10-16](CONTINUATION-2026-10-16.md)**: 2FA TOTP por usuário com auto-atendimento no login, banner de aviso no portal e tela enxuta `2fa.php`.
- **[Handoff 2026-10-15](CONTINUATION-2026-10-15.md)**: Cofre de segredos (AES-256-GCM), 2FA do admin, motor de crons em CLI com locks (`pg_try_advisory_lock`).
- **[Handoff 2026-10-14](CONTINUATION-2026-10-14.md)**: Otimizador de rotas da Frota sem Valhalla (Haversine + 2-opt), hotfix de autoload em deploy cPanel e criação do token de manutenção.
- **[Token de Manutenção](TOKEN-MANUTENCAO.md)**: Guia rápido para diagnóstico de erros e manutenção por IA em qualquer módulo.
- **[Handoff 2026-10-07](CONTINUATION-2026-10-07.md)**: Revisão profunda do módulo Frota, PWA offline, idempotência e separação de estoque.
- **[Handoff 2026-10-06](CONTINUATION-2026-10-06.md)**: Marco inicial da padronização de continuidade e regras de sigilo.

## Guias técnicos relacionados

- [Arquitetura e Operação](../ARCHITECTURE.md)
- [Handbook de Continuidade](../HANDBOOK.md)
- [Guia Seguro para Assistentes de IA](../AI_HANDOFF.md)
- [Deploy por FTP / cPanel](../DEPLOY_FTP_CPANEL.md)
- [Configuração Valhalla (opcional)](../VALHALLA_SETUP.md)

## Tokens históricos (Setembro/2026)

Snapshots mais antigos de sessões de desenvolvimento do módulo Frota e migração:
- `token-2026-09-30-parte-06.md`
- `token-2026-09-30-parte-05.md`
- `token-2026-09-30-parte-04.md`
- `token-2026-09-25-parte-03.md`
- `token-2026-09-24-parte-17.md`
- `token-2026-09-24-parte-15.md`
- `token-2026-09-23-parte-14.md`
- `token-2026-09-22-parte-13.md`
- `token-2026-09-22-parte-12.md`
- `token-2026-09-22-parte-11.md`
- `token-2026-09-22-parte-10.md`
- `token-2026-09-21-parte-9.md`
- `token-2026-09-18-parte-8.md`

## Segurança e Retenção

Os snapshots históricos não são fonte autorizada de credenciais, permissões ou
estado atual do código. Antes de compartilhar esta pasta, revise os históricos
por possíveis dados sensíveis e siga o processo de resposta a incidentes se
houver suspeita de exposição. Apagar texto não revoga credenciais nem limpa o
histórico Git.

Novos tokens devem conter apenas contexto técnico estritamente necessário, data, caminhos
relativos, estado confirmado e próximos passos. Nunca incluir `.env`, senhas,
JWT, chaves API, tokens de integração, cookies, dumps, dados pessoais ou instruções para
contornar controles de acesso.