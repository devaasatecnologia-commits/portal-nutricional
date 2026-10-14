# Acesso seguro a credenciais e servicos

Este guia define como um novo desenvolvedor solicita acesso. **Nao e um cofre e
nao contem credenciais.** Nao inserir valores reais neste arquivo, no handbook,
em tokens, issues, commits, mensagens ou testes.

## Bitwarden da organizacao: custodiante unico

A organizacao Bitwarden ja foi criada e, conforme a decisao atual da empresa,
somente uma pessoa autorizada (o custodiante) tera acesso a esse cofre. Nao
convide o programador para a organizacao, nao compartilhe a conta do custodiante
e nunca entregue a senha mestra ou os codigos MFA.

O custodiante deve:

1. Ativar MFA na propria conta e guardar codigos de recuperacao fora do
   computador de uso diario, em local seguro e controlado.
2. Guardar um item separado por servico e ambiente, identificando dono,
   finalidade, permissao e como revogar/rotacionar.
3. Revisar acessos e itens periodicamente e remover credenciais que nao sejam
   mais necessarias.
4. Definir um procedimento de continuidade para indisponibilidade do
   custodiante com a administracao da empresa. A decisao atual de acesso unico
   cria um ponto unico de falha; nao resolva isso compartilhando a conta.

O programador recebe acesso aos **servicos**, nao necessariamente ao cofre. O
CEO aprova o escopo e o dono de cada servico cria contas nominais ou chaves
exclusivas de desenvolvimento/homologacao. Producao permanece restrita. A
organizacao Bitwarden pode armazenar o registro administrativo, mas o
custodiante nao deve simplesmente repassar a senha compartilhada de producao.

## Inventario a manter no cofre (nao neste repositorio)

Manter uma entrada separada por servico e ambiente. Os nomes abaixo sao
categorias para inventario, nao indicam que a integracao esteja ativa em
producao.

| Servico | Dados que o dono deve registrar no Bitwarden | Regra de acesso |
| --- | --- | --- |
| Google Maps | Projeto/proprietario, APIs habilitadas, chave, restricoes de origem/IP e limites | Chave de desenvolvimento separada e restrita; nunca usar chave de producao localmente |
| FTP/cPanel | Hospedagem, metodo seguro de transferencia, conta individual e escopo de diretorios | Preferir SFTP/FTPS; evitar FTP sem criptografia; nao compartilhar usuario principal |
| Cobli | Organizacao/ambiente, chave de API, segredo de webhook e permissoes | Separar homologacao/producao; validar assinatura do webhook; rotacionar em suspeita |
| PostgreSQL | Ambiente, endpoint, base, usuario, permissao, certificado e processo de backup | Usuario proprio e privilegio minimo; producao somente com aprovacao; nao exportar dados sem autorizacao |
| SMTP | Provedor, conta de envio, remetente permitido e segredo | Conta de servico, sem reutilizar senha pessoal |
| Cron/integ. internas | Nome da integracao, escopo, dono e segredo associado | Token unico por ambiente, expiracao/rotacao e permissao restrita |
| Valhalla | Endpoint e controles de rede/autenticacao, quando implantado | Preferir rede privada; nao publicar endereco interno |

Os campos correspondentes da aplicacao sao documentados sem valores em
`.env.example`. O bootstrap atualmente exige `CHAVE_SECRETA`, `API_TOKEN` e
`CRON_TOKEN`. Configure tambem `JWT_SECRET` forte: alguns handlers o usam e
parte do codigo legado ainda tem fallback inseguro quando a variavel falta.
Confirme o codigo e o ambiente antes de configurar qualquer variavel; nao cole
aqui o conteudo do `.env`.

## Fluxo para novo desenvolvedor sem acesso ao Bitwarden

1. Obter autorizacao do responsavel tecnico e do dono do dado/servico.
2. Usar conta corporativa individual com MFA nos servicos que oferecem login
   individual; o custodiante nao fornece acesso a propria conta Bitwarden.
3. Solicitar inicialmente somente acessos e credenciais de
   desenvolvimento/homologacao, com privilegio minimo.
4. Receber dados sinteticos ou anonimizados para desenvolvimento; acesso a
   producao/dados pessoais e uma aprovacao separada, especifica e auditavel.
5. Criar `.env` local a partir de `.env.example` somente se ainda nao existir.
   Para segredos nao provisionaveis automaticamente, o custodiante envia apenas
   valores nao produtivos necessarios, um por vez, por Bitwarden Send com
   expiracao/limite de acesso se disponivel, ou por outro canal aprovado pela
   empresa. Nunca enviar o `.env` completo ou segredos por chat/e-mail comum.
6. Armazenar os valores recebidos localmente no `.env` ignorado pelo Git ou no
   gerenciador de segredos pessoal aprovado; confirmar com `git check-ignore
   .env` e `git status --short`.
7. Ao mudar de funcao ou encerrar o acesso, revogar as contas/chaves individuais
   nos servicos e eliminar copias locais de acordo com a politica da empresa.

Se a aplicacao puder rodar em um ambiente de desenvolvimento provisionado, e
preferivel o custodiante configurar os segredos nele sem revela-los ao
programador. Para integracao local que exige que o programador conheca uma
chave, use chave separada, restrita, revogavel e de desenvolvimento; nunca a
chave de producao.

## Incidente ou exposicao

Se um segredo aparecer em Git, log, ticket, chat ou maquina compartilhada:

1. Trate-o como comprometido; avise imediatamente o dono do servico e o
   responsavel de seguranca.
2. Revogue/rote o segredo no proprio provedor e atualize os ambientes autorizados.
3. Revise logs de acesso e possivel uso indevido; documente o incidente sem
   reproduzir o segredo.
4. Remover o texto de um commit nao o remove do historico Git. Siga o processo
   aprovado para limpeza do historico somente apos rotacao e coordenacao.
5. Se houver exposicao de dados pessoais, siga o processo formal de incidente
   da organizacao.

Nunca coloque segredos reais em exemplos, valores padrao de codigo ou arquivos
de continuidade. Acesso ao Bitwarden nao concede por si so autorizacao de acesso
a dados de producao.
