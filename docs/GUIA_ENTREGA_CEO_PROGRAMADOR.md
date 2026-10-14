# Manual de apresentação e continuidade do Portal Nutricional

**Material preparado por Alan para Robson**

**Destinatário:** CEO e programador responsável pela continuidade

**Data:** 6 de outubro de 2026

**Classificação:** uso interno

## Objetivo deste manual

Este material orienta Robson a apresentar o Portal Nutricional ao programador
que dará continuidade ao projeto. Robson apresenta o negócio, a operação, os
usuários e as prioridades. O programador investiga o código e confirma os
detalhes técnicos com os responsáveis. Não se espera que Robson explique
programação.

O manual reúne o contexto já informado por Alan e destaca o que deverá ser
combinado na reunião. Não substitui o repositório, os guias técnicos nem a
validação das regras com os responsáveis do ERP e das operações.

## Apresentação do negócio

O Portal Nutricional complementa o ERP da empresa. O ERP não reúne todas as
informações nem oferece todos os processos necessários à operação; o portal
preenche parte dessas lacunas por meio de informações, relatórios e processos
operacionais adicionais. Por isso, o portal impacta diretamente a operação e
deve ser entendido em conjunto com o ERP.

Ao apresentar o sistema, Robson deve explicar:

- quais atividades da empresa dependem do portal;
- quem usa cada área e para que resultado;
- quais informações ou processos são complementares ao ERP;
- o que é mais urgente preservar, corrigir ou desenvolver.

Alan informou que todos os módulos do portal são utilizados. O programador
deverá confirmar com Robson e com as equipes o uso e a criticidade de cada
módulo; a existência de código, isoladamente, não comprova como uma área é
utilizada atualmente.

## Demonstração prioritária: Logística e Frota

Robson deve priorizar uma demonstração de **Logística** e **Frota**, explicando
o objetivo operacional de cada etapa, os usuários envolvidos e o resultado que
a equipe espera.

### Logística

Demonstrar, em ambiente autorizado, um processo representativo de Logística do
início ao resultado final. Explicar:

1. Qual necessidade operacional inicia o processo.
2. Como a equipe consulta ou complementa informações do ERP.
3. Quais etapas são realizadas no portal e por quem.
4. Como a equipe confirma que o processo foi concluído corretamente.
5. Quais situações exigem intervenção ou aprovação de um responsável.

Robson e a equipe de Logística devem escolher antes ou durante a reunião qual
rotina específica será apresentada; este manual não presume um procedimento
único para todas as operações logísticas.

### Frota

Apresentar o fluxo de Frota conforme a operação real, por exemplo embarques,
organização de rotas, atividade do motorista, entregas, sincronização e acerto
administrativo. Escolher somente etapas aplicáveis ao processo atual e
explicar:

1. Como a operação começa e quais informações vêm do ERP.
2. Quem prepara ou acompanha o embarque e a rota.
3. O que o motorista executa no aplicativo, inclusive quando trabalha offline.
4. Como os dados retornam e como a equipe acompanha problemas.
5. Como ocorre o acerto e quais ações precisam de aprovação.

Usar preferencialmente ambiente de homologação, conta de demonstração e dados
fictícios. Não reprocessar pedidos, gerar lançamentos reais, alterar cadastros
de produção ou acionar integrações externas durante a apresentação.

## Prioridades e decisões da reunião

As prioridades dos primeiros 30–90 dias **ainda não estão definidas**. Alan
informou que o projeto seguirá recebendo desenvolvimento e atualizações; as
prioridades serão decididas por Robson e pelos responsáveis na reunião, após
considerarem as necessidades da operação e o estado atual do projeto.

Na reunião, combinar:

- quais problemas ou melhorias vêm primeiro;
- qual será a primeira entrega pequena e verificável;
- quem valida o resultado e qual é o critério de aceite;
- como novas solicitações serão priorizadas;
- quando as prioridades serão revistas.

Não assumir que toda melhoria desejada pode ser iniciada ao mesmo tempo.
Registrar as decisões e pendências no sistema interno aprovado pela empresa,
sem incluir senhas ou dados pessoais.

## Limites, aprovações e acessos

O programador **não terá acesso direto ao Bitwarden**. O cofre permanece sob
responsabilidade de seu custodiante. O compartilhamento da conta, da senha
mestra ou dos códigos MFA não faz parte da transição.

O acesso do programador ao repositório e aos demais ambientes/serviços será
combinado na reunião. Não presumir “acesso geral”: definir individualmente o
ambiente, as permissões, a justificativa e quem aprova. Acesso ou ação em
produção deve ser decidido de forma explícita; não decorre automaticamente do
acesso ao código.

Robson e os responsáveis devem decidir quem autoriza:

- leitura ou escrita no banco de desenvolvimento, homologação e produção;
- alterações no ERP, pedidos ou outros sistemas externos;
- publicação e rollback em produção;
- uso de contas de hospedagem, Google Maps, Cobli, e-mail e crons;
- acesso a dados pessoais ou relatórios com dados reais.

Começar com o menor acesso que permita realizar a tarefa. Quando possível,
solicitar contas individuais e credenciais separadas para desenvolvimento e
homologação. O custodiante configura segredos diretamente no ambiente sempre
que isso evitar expô-los. Nunca enviar ao programador o `.env` de produção ou
uma lista de senhas.

## Responsáveis

Alan e Robson são os contatos iniciais para a transição. A divisão de
responsabilidades — dúvidas de negócio, operação, prioridade, aprovação técnica
e autorização de produção — será confirmada por eles na reunião.

Também devem ser identificados os responsáveis por Logística, Frota, ERP/banco,
hospedagem e cada integração que estiver efetivamente em uso. Se um responsável
não for conhecido, registrar a pendência e indicar quem irá identificá-lo.

## Uso de inteligência artificial

Alan informou que a empresa utiliza DeepSeek e GPT com contas vinculadas ao
e-mail da Nutricional. Isso, por si só, **não confirma que o envio de código ou
documentos internos a esses serviços esteja aprovado**, nem quais configurações
de privacidade, retenção ou treinamento se aplicam à conta.

Antes de enviar qualquer conteúdo do projeto, o programador deverá:

1. Pedir aprovação de Robson para a ferramenta e para o tipo de informação.
2. Confirmar quais recursos e configurações corporativas estão ativos e revisar
   as políticas de tratamento de dados do serviço.
3. Enviar somente os arquivos necessários à tarefa, após revisá-los.
4. Nunca enviar senhas, `.env`, chaves, tokens, códigos MFA, dumps, dados reais
   de clientes/funcionários, logs ou conteúdo privado de produção.

O guia [AI_HANDOFF.md](AI_HANDOFF.md) contém uma lista seletiva de contexto e
uma lista de exclusões. Como ponto de partida, e somente após aprovação:

- `README.md`
- `docs/HANDBOOK.md`
- `docs/ARCHITECTURE.md`
- `docs/tokens/CONTINUATION-2026-10-06.md`, depois de confirmar que ainda está
  atualizado
- `composer.json`, se a tarefa envolver dependências
- `docs/PORTAL_GUIDE.md`, se a tarefa envolver processos do portal
- apenas os arquivos do módulo diretamente relacionado à tarefa

Não enviar o repositório inteiro por conveniência. **Não enviar tokens
históricos** `docs/tokens/token-*.md`: estão sujeitos a desatualização e ainda
precisam de revisão; duas verificações automáticas sinalizaram possíveis
campos sensíveis, sem confirmar que sejam credenciais reais. Não enviar
`.env.example` por padrão, pois ele revela nomes de variáveis e integrações.

## Documentos e arquivos técnicos para o programador

Depois de confirmar a branch/release de referência e revisar o estado do Git,
encaminhar o acesso autorizado ao repositório e indicar esta documentação:

1. `README.md` — visão geral e instruções de entrada.
2. `docs/HANDBOOK.md` — arquitetura e continuidade.
3. `docs/ARCHITECTURE.md` — gateways, API, autenticação e fluxos.
4. `docs/PORTAL_GUIDE.md` — módulos e regras operacionais documentadas.
5. `docs/DEPLOY_FTP_CPANEL.md` — publicação e cuidados de operação.
6. `docs/SECRETS_ACCESS.md` — processo de credenciais sem acesso direto ao cofre.
7. `docs/tokens/CONTINUATION-2026-10-06.md` — resumo atual, a reconfirmar antes
   de uso.
8. `docs/AI_HANDOFF.md` — política de seleção de arquivos para IA, quando
   aprovada.
9. Código-fonte do módulo da tarefa e dependências diretamente relacionadas.

Os SQL em `docs/` são scripts específicos, não um inventário completo do banco
nem uma autorização para executá-los. O schema PostgreSQL é externo; alterações
dependem da revisão, do backup, da homologação e da aprovação do responsável.

## Roteiro sugerido da reunião (60–90 minutos)

1. **Contexto (10 min):** Robson apresenta a empresa, o papel complementar do
   portal e os processos que não podem falhar.
2. **Demonstração (25–35 min):** mostrar Logística e Frota em ambiente e dados
   autorizados; explicar objetivo e resultado, sem executar operações reais.
3. **Prioridades (10–15 min):** escolher a primeira entrega, critério de aceite
   e forma de revisão.
4. **Acessos e aprovações (10–15 min):** definir repositório, ambientes e donos
   de cada permissão; confirmar que o Bitwarden fica restrito ao custodiante.
5. **Plano de continuidade (10–15 min):** identificar responsáveis, dúvidas
   abertas, riscos, próximo acompanhamento e ações da primeira semana.

## Resultado esperado

Ao final, o programador deve entender por que o portal complementa o ERP, ter
visto os processos de Logística e Frota, saber quem responde às dúvidas e ter
um primeiro objetivo acordado. As prioridades, os limites de acesso e as
permissões para outros ambientes ficam registrados como decisões de Robson e
dos responsáveis — não presumidos por este manual.

Este documento não contém nem deve receber senhas, chaves, tokens, dados reais
ou conteúdo do Bitwarden. O manual técnico completo permanece no
[HANDBOOK.md](HANDBOOK.md); os segredos ficam com o custodiante e cada ambiente
deve receber somente a configuração que lhe pertence.
