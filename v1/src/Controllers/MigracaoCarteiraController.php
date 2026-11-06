<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use PDO;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/**
 * Controller para o módulo MIGRAÇÃO DE CARTEIRA DE CLIENTES
 * Transfere clientes entre representantes comerciais atualizando:
 *  - palmtop & palmtop_vendrepre (dispositivos móveis e parâmetros do vendedor)
 *  - cliforemp, cliente e cliente_vendedor_mov (vínculo primário de vendedor)
 *  - receber (títulos em aberto com status 2 ou 3)
 *  - log_provider (sincronização via integrador Mercos)
 *  - Notificação por e-mail para Novo Repre, Antigo Repre, Supervisor e CCs configuradas
 */
class MigracaoCarteiraController
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function usuario(Request $request): array
    {
        $user = $request->getAttribute('user') ?? [];
        $user = is_object($user) ? (array)$user : (array)$user;
        $username = trim((string)($user['username'] ?? ($user['login'] ?? 'PORTAL')));
        if ($username === '') {
            $username = 'PORTAL';
        }
        return [
            'idusuario' => (int)($user['idusuario'] ?? 0),
            'username' => strtoupper($username),
            'nome' => (string)($user['nome'] ?? $username)
        ];
    }

    /**
     * GET /v1/migracao-carteira/representantes
     * Lista todos os representantes ativos no ERP e o total de clientes na carteira
     */
    public function listarRepresentantes(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT 
                    c.idcliforemp,
                    c.fantasia,
                    c.razao AS razaosocial,
                    c.cnpj AS cnpjcpf,
                    c.email,
                    c.idfilial,
                    f.idsupervisor,
                    sup.fantasia AS supervisor_nome,
                    sup.email AS supervisor_email,
                    COALESCE(cli_tot.total_clientes, 0) AS total_clientes,
                    COALESCE(rec_tot.total_titulos, 0) AS total_titulos_abertos
                FROM cliforemp c
                INNER JOIN fornecedor f ON f.idcliforemp = c.idcliforemp
                LEFT JOIN cliforemp sup ON sup.idcliforemp = f.idsupervisor
                LEFT JOIN (
                    SELECT idvendedor, COUNT(*) AS total_clientes
                    FROM cliforemp
                    WHERE inativo = 'N'
                    GROUP BY idvendedor
                ) cli_tot ON cli_tot.idvendedor = c.idcliforemp
                LEFT JOIN (
                    SELECT idvendrepre, COUNT(*) AS total_titulos
                    FROM receber
                    WHERE status IN (2, 3)
                    GROUP BY idvendrepre
                ) rec_tot ON rec_tot.idvendrepre = c.idcliforemp
                WHERE c.tipocliforemp = 1
                  AND f.representante = 'S'
                  AND c.inativo = 'N'
                ORDER BY c.fantasia ASC
            ";

            $stmt = $this->pdo->query($sql);
            $representantes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'sucesso' => true,
                'representantes' => $representantes
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar representantes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/migracao-carteira/mercos-status?idrepresentante=X
     * Verifica na API Mercos (saldo_flex) se o representante possui cadastro ativo
     */
    public function verificarMercos(Request $request, Response $response): Response
    {
        $params = array_merge($_GET, $request->getQueryParams());
        $idrepre = (int)($params['idrepresentante'] ?? 0);
        if ($idrepre <= 0 && preg_match('/idrepresentante=(\d+)/', (string)($params['api_route'] ?? ''), $m)) {
            $idrepre = (int)$m[1];
        }

        if ($idrepre <= 0) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Informe o ID do representante para verificação Mercos.'
            ], 400);
        }

        try {
            // Obter filial e dados do representante
            $stF = $this->pdo->prepare("
                SELECT c.idfilial, c.fantasia, c.email
                FROM cliforemp c
                LEFT JOIN fornecedor f ON f.idcliforemp = c.idcliforemp
                WHERE c.idcliforemp = :id AND c.tipocliforemp = 1 AND f.representante = 'S'
                LIMIT 1
            ");
            $stF->execute([':id' => $idrepre]);
            $rep = $stF->fetch(PDO::FETCH_ASSOC);

            if (!$rep) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Representante não encontrado no ERP.'
                ], 404);
            }

            $filial = (int)$rep['idfilial'];
            $url = ($filial === 1) 
                ? 'http://mercosf1.nutricionalbr.com:8081/saldo_flex' 
                : 'http://mercosf1.nutricionalbr.com:8086/saldo_flex';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 4);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($resp === false || $httpCode !== 200) {
                return $this->json($response, [
                    'sucesso' => true,
                    'no_mercos' => null, // Não foi possível checar no momento
                    'filial' => $filial,
                    'url' => $url,
                    'aviso' => 'Não foi possível validar o endpoint Mercos no momento (' . ($curlError ?: "HTTP $httpCode") . ').'
                ]);
            }

            $dados = json_decode($resp, true);
            if (!is_array($dados)) {
                return $this->json($response, [
                    'sucesso' => true,
                    'no_mercos' => null,
                    'filial' => $filial,
                    'url' => $url,
                    'aviso' => 'Retorno inválido do endpoint Mercos.'
                ]);
            }

            $idsMercos = [];
            foreach ($dados as $item) {
                if (isset($item['cliforemp_id'])) {
                    $idsMercos[] = (int)$item['cliforemp_id'];
                }
            }

            $existeNoMercos = in_array($idrepre, $idsMercos, true);

            return $this->json($response, [
                'sucesso' => true,
                'no_mercos' => $existeNoMercos,
                'filial' => $filial,
                'url' => $url,
                'email' => $rep['email'],
                'mensagem' => $existeNoMercos 
                    ? 'Representante cadastrado e ativo no Mercos.' 
                    : 'Atenção: Usuário NÃO cadastrado no Mercos para esta filial.'
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao verificar Mercos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/migracao-carteira/clientes?idrepresentante=X
     * Lista todos os clientes sob responsabilidade do representante de origem
     */
    public function listarClientesCarteira(Request $request, Response $response): Response
    {
        $params = array_merge($_GET, $request->getQueryParams());
        $idrepre = (int)($params['idrepresentante'] ?? 0);
        if ($idrepre <= 0 && preg_match('/idrepresentante=(\d+)/', (string)($params['api_route'] ?? ''), $m)) {
            $idrepre = (int)$m[1];
        }

        if ($idrepre <= 0) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Informe o ID do representante de origem.'
            ], 400);
        }

        try {
            $sql = "
                SELECT 
                    c.idcliforemp AS idcliente,
                    c.fantasia AS cliente,
                    c.razao AS razaosocial,
                    c.cnpj AS cnpjcpf,
                    c.idfilial,
                    c.inativo,
                    cid.descricao AS cidade,
                    cid.uf,
                    c.idvendedor AS idrepresentante_antigo,
                    rep_ant.fantasia AS representante_antigo,
                    COALESCE(tit.qtd_titulos, 0) AS titulos_abertos,
                    COALESCE(tit.total_aberto, 0) AS valor_aberto
                FROM cliforemp c
                LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                LEFT JOIN cliforemp rep_ant ON rep_ant.idcliforemp = c.idvendedor
                LEFT JOIN (
                    SELECT 
                        r.idcliforemp,
                        COUNT(*) AS qtd_titulos,
                        SUM(COALESCE(r.valorsaldo, r.valor)) AS total_aberto
                    FROM receber r
                    WHERE r.status IN (2, 3)
                      AND r.idvendrepre = :idrepre
                    GROUP BY r.idcliforemp
                ) tit ON tit.idcliforemp = c.idcliforemp
                WHERE c.idvendedor = :idrepre
                  AND c.inativo = 'N'
                ORDER BY c.fantasia ASC
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([':idrepre' => $idrepre]);
            $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Formata valores numéricos para exibição
            foreach ($clientes as &$cli) {
                $cli['idcliente'] = (int)$cli['idcliente'];
                $cli['titulos_abertos'] = (int)$cli['titulos_abertos'];
                $cli['valor_aberto'] = (float)$cli['valor_aberto'];
            }

            return $this->json($response, [
                'sucesso' => true,
                'total' => count($clientes),
                'clientes' => $clientes
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar carteira de clientes: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * POST /v1/migracao-carteira/executar
     * Executa a migração em transação atômica conforme regras legadas do ERP/Mercos
     */
    public function executarMigracao(Request $request, Response $response): Response
    {
        $body = $request->getParsedBody();
        if (!is_array($body)) {
            $body = json_decode((string)$request->getBody(), true) ?? [];
        }

        $idrepreAntigo = (int)($body['idrepresentante_antigo'] ?? 0);
        $idrepreNovo = (int)($body['idrepresentante_novo'] ?? 0);
        $clientesIds = $body['clientes_ids'] ?? [];
        $motivo = trim((string)($body['motivo'] ?? ''));

        if ($idrepreAntigo <= 0 || $idrepreNovo <= 0) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Selecione os representantes de origem e destino.'
            ], 400);
        }

        if ($idrepreAntigo === $idrepreNovo) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Os representantes de origem e destino são iguais. Nenhuma atualização necessária.'
            ], 400);
        }

        $usuarioLogado = $this->usuario($request)['username'];

        try {
            // 1. Dados do Representante NOVO
            $stNovo = $this->pdo->prepare("
                SELECT c.idcliforemp, c.fantasia, c.email, c.idfilial, f.idsupervisor
                FROM public.cliforemp c
                LEFT JOIN public.fornecedor f ON f.idcliforemp = c.idcliforemp
                WHERE c.idcliforemp = :id
            ");
            $stNovo->execute([':id' => $idrepreNovo]);
            $repreNovo = $stNovo->fetch(PDO::FETCH_ASSOC);

            if (!$repreNovo) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Representante de destino não encontrado.'
                ], 404);
            }

            // 2. Dados do Representante ANTIGO
            $stAnt = $this->pdo->prepare("
                SELECT idcliforemp, fantasia, email, idfilial
                FROM public.cliforemp
                WHERE idcliforemp = :id
            ");
            $stAnt->execute([':id' => $idrepreAntigo]);
            $repreAnt = $stAnt->fetch(PDO::FETCH_ASSOC);

            if (!$repreAnt) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Representante de origem não encontrado.'
                ], 404);
            }

            // 3. Dados do Supervisor do Representante Novo
            $supervisor = null;
            if (!empty($repreNovo['idsupervisor'])) {
                $stSup = $this->pdo->prepare("
                    SELECT idcliforemp, fantasia, email
                    FROM public.cliforemp
                    WHERE idcliforemp = :id
                ");
                $stSup->execute([':id' => $repreNovo['idsupervisor']]);
                $supervisor = $stSup->fetch(PDO::FETCH_ASSOC);
            }

            // 4. Determina quais clientes serão migrados
            if (empty($clientesIds) || !is_array($clientesIds)) {
                // Se nenhum cliente foi marcado explicitamente, seleciona todos da carteira
                $stAll = $this->pdo->prepare("
                    SELECT idcliforemp, fantasia, razao AS razaosocial
                    FROM public.cliforemp
                    WHERE idvendedor = :idantigo AND inativo = 'N'
                ");
                $stAll->execute([':idantigo' => $idrepreAntigo]);
                $clientesParaMigrar = $stAll->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Filtra apenas os IDs selecionados válidos que realmente pertencem ao repre antigo
                $placeholders = [];
                $binds = [':idantigo' => $idrepreAntigo];
                foreach (array_values($clientesIds) as $idx => $cid) {
                    $cid = (int)$cid;
                    if ($cid > 0) {
                        $p = ":c_{$idx}";
                        $placeholders[] = $p;
                        $binds[$p] = $cid;
                    }
                }

                if (empty($placeholders)) {
                    return $this->json($response, [
                        'sucesso' => false,
                        'mensagem' => 'Nenhum cliente válido selecionado para migração.'
                    ], 400);
                }

                $sqlSel = "
                    SELECT idcliforemp, fantasia, razao AS razaosocial
                    FROM public.cliforemp
                    WHERE idvendedor = :idantigo
                      AND inativo = 'N'
                      AND idcliforemp IN (" . implode(',', $placeholders) . ")
                ";
                $stSel = $this->pdo->prepare($sqlSel);
                $stSel->execute($binds);
                $clientesParaMigrar = $stSel->fetchAll(PDO::FETCH_ASSOC);
            }

            if (empty($clientesParaMigrar)) {
                return $this->json($response, [
                    'sucesso' => false,
                    'mensagem' => 'Nenhum cliente ativo localizado para transferência com os filtros informados.'
                ], 400);
            }

            $totalClientes = count($clientesParaMigrar);
            $nomeRepreNovo = (string)$repreNovo['fantasia'];
            $nomeRepreAnt = (string)$repreAnt['fantasia'];
            $emailRepreNovo = (string)($repreNovo['email'] ?? '');
            $emailRepreAnt = (string)($repreAnt['email'] ?? '');
            $emailSupervisor = (string)($supervisor['email'] ?? '');

            // ============================================================
            // INÍCIO DA TRANSAÇÃO ATÔMICA
            // ============================================================
            $this->pdo->beginTransaction();

            // ------------------------------------------------------------
            // AÇÃO 1: LOCALIZA OU CADASTRA O DISPOSITIVO NA TABELA PALMTOP
            // ------------------------------------------------------------
            $stPalmCheck = $this->pdo->prepare("
                SELECT idpalmtop 
                FROM public.palmtop 
                WHERE observacao = (SELECT fantasia FROM cliforemp WHERE idcliforemp = :idnovo LIMIT 1)
                LIMIT 1
            ");
            $stPalmCheck->execute([':idnovo' => $idrepreNovo]);
            $idPalmtopArmazenado = $stPalmCheck->fetchColumn();

            if ($idPalmtopArmazenado) {
                $idPalmtopArmazenado = (int)$idPalmtopArmazenado;
            } else {
                // Procura menor inativo para reutilizar
                $stInat = $this->pdo->query("SELECT min(idpalmtop) AS idpalmtop FROM public.palmtop WHERE inativo = 'S'");
                $idInativo = $stInat->fetchColumn();

                if ($idInativo) {
                    $idPalmtopArmazenado = (int)$idInativo;
                    $stUpInat = $this->pdo->prepare("
                        UPDATE public.palmtop
                        SET observacao = (SELECT fantasia FROM cliforemp WHERE idcliforemp = :idnovo LIMIT 1),
                            datahora = NOW(),
                            usuario = :usuario,
                            inativo = 'N'
                        WHERE idpalmtop = :idpalmtop
                    ");
                    $stUpInat->execute([
                        ':idnovo' => $idrepreNovo,
                        ':usuario' => $usuarioLogado,
                        ':idpalmtop' => $idPalmtopArmazenado
                    ]);
                } else {
                    // Gera novo dispositivo
                    $stMax = $this->pdo->query("SELECT COALESCE(MAX(idpalmtop), 0) + 1 AS maxid FROM public.palmtop");
                    $novoIdPalm = (int)$stMax->fetchColumn();

                    $stNext = $this->pdo->query("SELECT nextval('gi_palmtop') AS idp");
                    $idPalmtopArmazenado = (int)$stNext->fetchColumn();

                    $stInsPalm = $this->pdo->prepare("
                        INSERT INTO public.palmtop (idpalmtop, descricao, marca, modelo, memoria, observacao, inativo, datahora, usuario)
                        VALUES (:idpalmtop, :descricao, '.', '.', '.', :observacao, 'N', NOW(), :usuario)
                    ");
                    $stInsPalm->execute([
                        ':idpalmtop' => $idPalmtopArmazenado,
                        ':descricao' => 'DISPOSITIVO ' . $novoIdPalm,
                        ':observacao' => $nomeRepreNovo,
                        ':usuario' => $usuarioLogado
                    ]);
                }
            }

            // ------------------------------------------------------------
            // AÇÃO 2: LOCALIZA OU INSERE NA TABELA PALMTOP_VENDREPRE
            // ------------------------------------------------------------
            $stVpCheck = $this->pdo->prepare("
                SELECT idvendrepre 
                FROM public.palmtop_vendrepre 
                WHERE idvendrepre = :idnovo
            ");
            $stVpCheck->execute([':idnovo' => $idrepreNovo]);
            $existeVendrepre = (bool)$stVpCheck->fetchColumn();

            if ($existeVendrepre) {
                $stUpVp = $this->pdo->prepare("
                    UPDATE public.palmtop_vendrepre
                    SET idpalmtop = :idpalmtop,
                        datahora = CURRENT_TIMESTAMP,
                        usuario = :usuario
                    WHERE idvendrepre = :idnovo
                ");
                $stUpVp->execute([
                    ':idpalmtop' => $idPalmtopArmazenado,
                    ':usuario' => $usuarioLogado,
                    ':idnovo' => $idrepreNovo
                ]);
            } else {
                $stInsVp = $this->pdo->prepare("
                    INSERT INTO public.palmtop_vendrepre (
                        idvendrepre, idpalmtop, idcidade, idempresa, idfilial, idtransacao,
                        perc_desconto_max_item, perc_margem_min_item, perc_margem_min_pedido,
                        permite_venda_abaixo_custo, datahora, usuario, inativo, uf, idtabelapreco,
                        desconto_personal, edita_valor_venda, mostracusto, mostramargem,
                        informanumeropedform, exportaresumo, enviaclientesemvendedor, alteradescitempedido,
                        usadescontoflex, exportaextratovendas, exportaextratocomissao, usa_impressora,
                        vias_impressao, usamalotepedido, permite_venda_abaixo_pauta, enviaclientesgeral,
                        tipo_pda, permite_cadastrar_cliente, idcliforemp_novo, filiais_representante,
                        implantoumov, tipovendrepre
                    )
                    SELECT 
                        idcliforemp, :idpalmtop, idcidade, idempresa, idfilial, 0,
                        0, 0, 0,
                        'N', CURRENT_TIMESTAMP, :usuario, 'N', uf, 0,
                        '.', 'N', 'N', 'N',
                        'N', 'N', 'N', 'N',
                        'N', 'N', 'N', 'N',
                        0, 'N', 'S', 'N',
                        0, 'N', 0, idfilial,
                        'N', 2
                    FROM public.cliforemp 
                    WHERE idcliforemp = :idnovo
                ");
                $stInsVp->execute([
                    ':idpalmtop' => $idPalmtopArmazenado,
                    ':usuario' => $usuarioLogado,
                    ':idnovo' => $idrepreNovo
                ]);
            }

            // ------------------------------------------------------------
            // AÇÕES 3, 4 E 5: MIGRAÇÃO DOS CLIENTES, RECEBER E LOGS MERCOS
            // ------------------------------------------------------------
            $totalTitulosAtualizados = 0;

            // Prepared statements reutilizáveis
            $stUpCliforemp = $this->pdo->prepare("
                UPDATE public.cliforemp
                SET idvendedor = :idnovo,
                    usuarioalteracao = :usuario,
                    datahoraalteracao = NOW(),
                    datahoraultimaatualizacao = NOW()
                WHERE idvendedor = :idantigo
                  AND idcliforemp = :idcliente
            ");

            $stUpEmaMov = $this->pdo->prepare("
                UPDATE public.cliente_vendedor_mov
                SET idvendedor = :idnovo
                WHERE idvendedor = :idantigo
                  AND idcliforemp = :idcliente
            ");

            $stInsEmaMovFallback = $this->pdo->prepare("
                INSERT INTO public.cliente_vendedor_mov (idcliforemp, idvendedor, tipovendrepre)
                SELECT :idcliente, :idnovo, 2
                WHERE NOT EXISTS (
                    SELECT 1 FROM public.cliente_vendedor_mov 
                    WHERE idcliforemp = :idcliente AND idvendedor = :idnovo
                )
            ");

            $stUpCliente = $this->pdo->prepare("
                UPDATE public.cliente
                SET idvendedor = :idnovo
                WHERE idvendedor = :idantigo
                  AND idcliforemp = :idcliente
            ");

            $stUpReceber = $this->pdo->prepare("
                UPDATE public.receber
                SET idvendrepre = :idnovo
                WHERE idvendrepre = :idantigo
                  AND idcliforemp = :idcliente
                  AND status IN (2, 3)
            ");

            // Statements para sincronização com Mercos (log_provider)
            $stLogCliforemp = $this->pdo->prepare("
                INSERT INTO public.log_provider (idlog, usuario, datahora, tabela, valores, maquina, ip, id)
                SELECT DISTINCT 
                    nextval('gi_log_provider'), 
                    :usuario::character varying AS usuario, 
                    CURRENT_TIMESTAMP AS datahora, 
                    'CLIFOREMP'::character varying AS tabela,
                    replace(
                        'usuarioalteracao.............. [ANTES]' || COALESCE(DADOS.usuarioalteracao, '') || ' - [NOVO] ' || :usuario || E'\n' ||
                        'datahoraalteracao............. [ANTES] ' || COALESCE(DADOS.datahoraalteracao::text, '') || ' - [NOVO] ' || current_timestamp::text || E'\n' ||
                        'datahoraultimaatualizacao..... [ANTES] ' || COALESCE(DADOS.datahoraultimaatualizacao::text, '') || ' - [NOVO] ' || current_timestamp::text || E'\n' ||
                        'idvendedor.................... [ANTES] ' || COALESCE(DADOS.IDVENDEDOR::text, '') || ' - [NOVO] ' || COALESCE(DADOS.NOVOVENDEDOR::text, '') || E'\n' ||
                        'fantasia...................... [ANTES]' || COALESCE(DADOS.CLIENTE, '') || '.. - [NOVO]' || COALESCE(DADOS.CLIENTE, ''), 
                    E'\n', E'\n') AS valores,
                    'PORTAL DOX'::character varying AS maquina,
                    '192.168.1.99'::character varying AS ip,
                    CAST(dados.cod_cliente AS text) AS id
                FROM (
                    SELECT DISTINCT 
                        cliforemp.idcliforemp AS cod_cliente,
                        cliforemp.datahoraalteracao,
                        cliforemp.datahoraultimaatualizacao,
                        cliforemp.idvendedor,
                        emamov.idvendedor AS novovendedor,
                        cliforemp.usuarioalteracao, 
                        cliforemp.fantasia AS cliente
                    FROM cliforemp 
                    JOIN cliente ON cliente.idcliforemp = cliforemp.idcliforemp 
                    LEFT JOIN cliente_vendedor_mov emamov ON emamov.idcliforemp = cliforemp.idcliforemp AND emamov.idvendedor = :idnovo
                    WHERE cliforemp.idcliforemp = :idcliente
                ) dados
            ");

            $stLogCliente = $this->pdo->prepare("
                INSERT INTO public.log_provider (idlog, usuario, datahora, tabela, valores, maquina, ip, id)
                SELECT DISTINCT 
                    nextval('gi_log_provider'), 
                    :usuario::character varying AS usuario, 
                    CURRENT_TIMESTAMP AS datahora, 
                    'CLIENTE'::character varying AS tabela,
                    replace(
                        'idvendedor.................... [ANTES] ' || COALESCE(DADOS.IDVENDEDOR::text, '') || ' - [NOVO] ' || COALESCE(DADOS.NOVOVENDEDOR::text, '') || E'\n',
                    E'\n', E'\n') AS valores,
                    'PORTAL DOX'::character varying AS maquina,
                    '192.168.1.99'::character varying AS ip,
                    CAST(dados.cod_cliente AS text) AS id
                FROM (
                    SELECT DISTINCT 
                        cliforemp.idcliforemp AS cod_cliente,
                        cliforemp.datahoraalteracao,
                        cliforemp.datahoraultimaatualizacao,
                        cliforemp.idvendedor,
                        emamov.idvendedor AS novovendedor,
                        cliforemp.usuarioalteracao, 
                        cliforemp.fantasia AS cliente
                    FROM cliforemp 
                    JOIN cliente ON cliente.idcliforemp = cliforemp.idcliforemp 
                    LEFT JOIN cliente_vendedor_mov emamov ON emamov.idcliforemp = cliforemp.idcliforemp AND emamov.idvendedor = :idnovo
                    WHERE cliforemp.idcliforemp = :idcliente
                ) dados
            ");

            foreach ($clientesParaMigrar as $cli) {
                $idCli = (int)$cli['idcliforemp'];

                // 3.1 Atualiza cliforemp
                $stUpCliforemp->execute([
                    ':idnovo' => $idrepreNovo,
                    ':usuario' => $usuarioLogado,
                    ':idantigo' => $idrepreAntigo,
                    ':idcliente' => $idCli
                ]);

                // 3.2 Atualiza cliente_vendedor_mov
                $stUpEmaMov->execute([
                    ':idnovo' => $idrepreNovo,
                    ':idantigo' => $idrepreAntigo,
                    ':idcliente' => $idCli
                ]);
                if ($stUpEmaMov->rowCount() === 0) {
                    $stInsEmaMovFallback->execute([
                        ':idcliente' => $idCli,
                        ':idnovo' => $idrepreNovo
                    ]);
                }

                // 3.3 Atualiza cliente
                $stUpCliente->execute([
                    ':idnovo' => $idrepreNovo,
                    ':idantigo' => $idrepreAntigo,
                    ':idcliente' => $idCli
                ]);

                // 4. Atualiza receber
                $stUpReceber->execute([
                    ':idnovo' => $idrepreNovo,
                    ':idantigo' => $idrepreAntigo,
                    ':idcliente' => $idCli
                ]);
                $totalTitulosAtualizados += $stUpReceber->rowCount();

                // 5. Gera logs para sincronização com Mercos
                $stLogCliforemp->execute([
                    ':usuario' => $usuarioLogado,
                    ':idnovo' => $idrepreNovo,
                    ':idcliente' => $idCli
                ]);

                $stLogCliente->execute([
                    ':usuario' => $usuarioLogado,
                    ':idnovo' => $idrepreNovo,
                    ':idcliente' => $idCli
                ]);
            }

            // ------------------------------------------------------------
            // BUSCAR LISTA DE CC DA CONFIGURAÇÃO DE MÓDULOS
            // ------------------------------------------------------------
            $emailsCC = [];
            try {
                $stCC = $this->pdo->prepare("SELECT emails_json FROM config_email_listas WHERE chave = 'migracao_carteira_cc'");
                $stCC->execute();
                $ccRaw = $stCC->fetchColumn();
                if ($ccRaw) {
                    $dec = json_decode($ccRaw, true);
                    if (is_array($dec)) {
                        $emailsCC = $dec;
                    }
                }
            } catch (\Throwable $e) {
                $emailsCC = ['alan@nutricionalbr.com', 'robson@nutricionalbr.com'];
            }
            if (empty($emailsCC)) {
                $emailsCC = ['alan@nutricionalbr.com', 'robson@nutricionalbr.com'];
            }

            // ------------------------------------------------------------
            // REGISTRO DE HISTÓRICO
            // ------------------------------------------------------------
            $stHist = $this->pdo->prepare("
                INSERT INTO migracao_carteira_historico (
                    usuario, idrepre_antigo, nome_repre_antigo,
                    idrepre_novo, nome_repre_novo, total_clientes,
                    clientes_json, titulos_atualizados, email_novo,
                    email_antigo, email_supervisor, emails_cc,
                    email_status, observacao, datahora
                ) VALUES (
                    :usuario, :idantigo, :nomeantigo,
                    :idnovo, :nomenovo, :total,
                    :clientes::jsonb, :titulos, :emailnovo,
                    :emailantigo, :emailsup, :emailscc::jsonb,
                    'PENDENTE', :obs, NOW()
                ) RETURNING id
            ");
            $stHist->execute([
                ':usuario' => $usuarioLogado,
                ':idantigo' => $idrepreAntigo,
                ':nomeantigo' => $nomeRepreAnt,
                ':idnovo' => $idrepreNovo,
                ':nomenovo' => $nomeRepreNovo,
                ':total' => $totalClientes,
                ':clientes' => json_encode($clientesParaMigrar, JSON_UNESCAPED_UNICODE),
                ':titulos' => $totalTitulosAtualizados,
                ':emailnovo' => $emailRepreNovo,
                ':emailantigo' => $emailRepreAnt,
                ':emailsup' => $emailSupervisor,
                ':emailscc' => json_encode($emailsCC, JSON_UNESCAPED_UNICODE),
                ':obs' => $motivo
            ]);
            $idHistorico = (int)$stHist->fetchColumn();

            // COMMIT DA TRANSAÇÃO NO BANCO DE DADOS
            $this->pdo->commit();

            // ============================================================
            // DISPARO DE E-MAIL DE NOTIFICAÇÃO
            // ============================================================
            $emailEnviado = $this->dispararEmailNotificacao(
                $nomeRepreNovo,
                $emailRepreNovo,
                $nomeRepreAnt,
                $emailRepreAnt,
                $supervisor['fantasia'] ?? '',
                $emailSupervisor,
                $emailsCC,
                $clientesParaMigrar,
                $usuarioLogado,
                $motivo
            );

            // Atualiza status do e-mail no histórico
            if ($idHistorico > 0) {
                $stUpHist = $this->pdo->prepare("
                    UPDATE migracao_carteira_historico
                    SET email_status = :st
                    WHERE id = :id
                ");
                $stUpHist->execute([
                    ':st' => $emailEnviado ? 'ENVIADO' : 'FALHA_ENVIO',
                    ':id' => $idHistorico
                ]);
            }

            return $this->json($response, [
                'sucesso' => true,
                'mensagem' => "Migração de {$totalClientes} cliente(s) de {$nomeRepreAnt} para {$nomeRepreNovo} concluída com sucesso!",
                'total_clientes' => $totalClientes,
                'titulos_atualizados' => $totalTitulosAtualizados,
                'email_enviado' => $emailEnviado,
                'novo_representante' => $nomeRepreNovo,
                'antigo_representante' => $nomeRepreAnt,
                'id_historico' => $idHistorico
            ]);

        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao executar migração de carteira: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET /v1/migracao-carteira/historico
     * Lista as últimas migrações realizadas
     */
    public function listarHistorico(Request $request, Response $response): Response
    {
        try {
            $sql = "
                SELECT 
                    id,
                    usuario,
                    idrepre_antigo,
                    nome_repre_antigo,
                    idrepre_novo,
                    nome_repre_novo,
                    total_clientes,
                    clientes_json,
                    titulos_atualizados,
                    email_novo,
                    email_antigo,
                    email_supervisor,
                    emails_cc,
                    email_status,
                    observacao,
                    TO_CHAR(datahora, 'DD/MM/YYYY HH24:MI') AS datahora_fmt
                FROM migracao_carteira_historico
                ORDER BY id DESC
                LIMIT 50
            ";
            $stmt = $this->pdo->query($sql);
            $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($historico as &$h) {
                $h['clientes'] = json_decode($h['clientes_json'] ?? '[]', true) ?? [];
                $h['emails_cc'] = json_decode($h['emails_cc'] ?? '[]', true) ?? [];
                unset($h['clientes_json']);
            }

            return $this->json($response, [
                'sucesso' => true,
                'historico' => $historico
            ]);
        } catch (\Throwable $e) {
            return $this->json($response, [
                'sucesso' => false,
                'mensagem' => 'Erro ao carregar histórico: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Envia e-mail formatado via PHPMailer
     */
    private function dispararEmailNotificacao(
        string $nomeRepreNovo,
        string $emailRepreNovo,
        string $nomeRepreAnt,
        string $emailRepreAnt,
        string $nomeSupervisor,
        string $emailSupervisor,
        array $emailsCC,
        array $clientes,
        string $usuario,
        string $motivo = ''
    ): bool {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'mail.nutricionalbr.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com';
            $mail->Password = \Nutricional\Services\SecretVault::get('MAIL_PASS');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = (int)($_ENV['MAIL_PORT'] ?? 465);
            $mail->CharSet = 'UTF-8';
            $mail->isHTML(true);
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->setFrom($_ENV['MAIL_USER'] ?? 'nao-responder@nutricionalbr.com', 'Portal Nutricional');

            $temDestinatario = false;

            // 1. Destinatário principal: Representante Novo
            if (filter_var($emailRepreNovo, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($emailRepreNovo, $nomeRepreNovo);
                $temDestinatario = true;
            }

            // 2. Cópia: Representante Antigo
            if (filter_var($emailRepreAnt, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($emailRepreAnt, $nomeRepreAnt);
                $temDestinatario = true;
            }

            // 3. Cópia: Supervisor
            if (filter_var($emailSupervisor, FILTER_VALIDATE_EMAIL)) {
                $mail->addCC($emailSupervisor, $nomeSupervisor ?: 'Supervisor Comercial');
                $temDestinatario = true;
            }

            // 4. Cópias adicionais configuradas no módulo (config_email_listas)
            foreach ($emailsCC as $cc) {
                $cc = trim((string)$cc);
                if (filter_var($cc, FILTER_VALIDATE_EMAIL)) {
                    $mail->addCC($cc);
                    $temDestinatario = true;
                }
            }

            if (!$temDestinatario) {
                // Fallback de segurança se nenhum e-mail estiver cadastrado
                $mail->addAddress('alan@nutricionalbr.com', 'Gestão Nutricional');
            }

            $mail->Subject = "Atualização de Carteira de Clientes · {$nomeRepreNovo}";

            // Constrói linhas da tabela de clientes
            $linhasClientes = '';
            foreach ($clientes as $idx => $cli) {
                $num = $idx + 1;
                $cid = (int)$cli['idcliforemp'];
                $fantasia = htmlspecialchars((string)$cli['fantasia'], ENT_QUOTES, 'UTF-8');
                $razao = htmlspecialchars((string)($cli['razaosocial'] ?? ''), ENT_QUOTES, 'UTF-8');
                $linhasClientes .= "
                    <tr style=\"border-bottom: 1px solid #e2e8f0;\">
                        <td style=\"padding: 8px 12px; font-weight: bold; color: #475569;\">{$num}</td>
                        <td style=\"padding: 8px 12px; font-weight: 700; color: #1e293b;\">{$cid}</td>
                        <td style=\"padding: 8px 12px; font-weight: 600; color: #0f172a;\">{$fantasia}</td>
                        <td style=\"padding: 8px 12px; color: #64748b; font-size: 12px;\">{$razao}</td>
                    </tr>
                ";
            }

            $dataHoraAtual = date('d/m/Y H:i');
            $totalQtd = count($clientes);

            $html = "
            <!DOCTYPE html>
            <html lang=\"pt-BR\">
            <head>
                <meta charset=\"UTF-8\">
                <title>Atualização de Carteira de Clientes</title>
            </head>
            <body style=\"margin: 0; padding: 20px; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;\">
                <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"max-width: 680px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;\">
                    <!-- HEADER -->
                    <tr>
                        <td style=\"background: linear-gradient(135deg, #047857 0%, #059669 50%, #10b981 100%); padding: 28px 32px; text-align: left;\">
                            <span style=\"display: inline-block; background: rgba(255,255,255,0.2); color: #ffffff; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase;\">
                                Comercial · Portal Nutricional
                            </span>
                            <h1 style=\"color: #ffffff; font-size: 22px; margin: 12px 0 4px 0; font-weight: 800;\">
                                Atualização na Carteira de Clientes
                            </h1>
                            <p style=\"color: #d1fae5; font-size: 13px; margin: 0;\">
                                Transferência oficial de clientes entre representantes comerciais
                            </p>
                        </td>
                    </tr>

                    <!-- CORPO -->
                    <tr>
                        <td style=\"padding: 28px 32px;\">
                            <p style=\"font-size: 15px; line-height: 1.5; color: #334155; margin-top: 0;\">
                                Olá! Foi realizada uma <strong>atualização de carteira de clientes</strong> no ERP / Mercos.
                            </p>

                            <!-- CARD DE RESUMO -->
                            <table width=\"100%\" style=\"background: #f1f5f9; border-radius: 12px; margin: 16px 0; padding: 16px; border: 1px solid #e2e8f0; font-size: 13px;\">
                                <tr>
                                    <td style=\"padding: 4px 0; width: 40%; font-weight: bold; color: #475569;\">Novo Representante:</td>
                                    <td style=\"padding: 4px 0; font-weight: 800; color: #047857;\">{$nomeRepreNovo}</td>
                                </tr>
                                <tr>
                                    <td style=\"padding: 4px 0; font-weight: bold; color: #475569;\">Representante Anterior:</td>
                                    <td style=\"padding: 4px 0; font-weight: 600; color: #64748b;\">{$nomeRepreAnt}</td>
                                </tr>
                                " . ($nomeSupervisor ? "
                                <tr>
                                    <td style=\"padding: 4px 0; font-weight: bold; color: #475569;\">Supervisor:</td>
                                    <td style=\"padding: 4px 0; font-weight: 600; color: #334155;\">{$nomeSupervisor}</td>
                                </tr>
                                " : "") . "
                                <tr>
                                    <td style=\"padding: 4px 0; font-weight: bold; color: #475569;\">Total de Clientes Transferidos:</td>
                                    <td style=\"padding: 4px 0; font-weight: 800; color: #0f172a;\">{$totalQtd} cliente(s)</td>
                                </tr>
                                <tr>
                                    <td style=\"padding: 4px 0; font-weight: bold; color: #475569;\">Data / Horário da Operação:</td>
                                    <td style=\"padding: 4px 0; color: #475569;\">{$dataHoraAtual}</td>
                                </tr>
                                <tr>
                                    <td style=\"padding: 4px 0; font-weight: bold; color: #475569;\">Operador do Portal:</td>
                                    <td style=\"padding: 4px 0; color: #475569;\">{$usuario}</td>
                                </tr>
                                " . ($motivo ? "
                                <tr>
                                    <td style=\"padding: 4px 0; font-weight: bold; color: #475569;\">Motivo / Observações:</td>
                                    <td style=\"padding: 4px 0; color: #334155; font-style: italic;\">{$motivo}</td>
                                </tr>
                                " : "") . "
                            </table>

                            <!-- TABELA DE CLIENTES -->
                            <h3 style=\"font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 24px 0 10px 0;\">
                                Relação dos Clientes Transferidos ({$totalQtd})
                            </h3>
                            <div style=\"overflow-x: auto;\">
                                <table width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" style=\"border-collapse: collapse; font-size: 13px; text-align: left;\">
                                    <thead>
                                        <tr style=\"background: #f8fafc; border-bottom: 2px solid #cbd5e1; color: #475569; font-size: 11px; text-transform: uppercase;\">
                                            <th style=\"padding: 8px 12px;\">#</th>
                                            <th style=\"padding: 8px 12px;\">Cód ERP</th>
                                            <th style=\"padding: 8px 12px;\">Fantasia</th>
                                            <th style=\"padding: 8px 12px;\">Razão Social</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$linhasClientes}
                                    </tbody>
                                </table>
                            </div>

                            <!-- INSTRUÇÕES MERCOS -->
                            <div style=\"margin-top: 28px; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 18px 20px;\">
                                <h4 style=\"margin: 0 0 6px 0; color: #065f46; font-size: 14px; font-weight: 800;\">
                                    Acesso ao MERCOS
                                </h4>
                                <p style=\"margin: 0 0 12px 0; font-size: 13px; color: #047857;\">
                                    O e-mail configurado para acesso do representante é: <strong>" . ($emailRepreNovo ?: 'Consulte a gestão comercial') . "</strong>
                                </p>
                                <a href=\"https://nutricionalbr.com/treinamento\" target=\"_blank\" style=\"display: inline-block; background: #059669; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 13px; padding: 10px 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(5,150,105,0.3);\">
                                    Acessar Treinamento & Tutoriais Mercos &rarr;
                                </a>
                            </div>

                            <p style=\"font-size: 12px; color: #94a3b8; margin-top: 24px; margin-bottom: 0; text-align: center;\">
                                Mensagem automática gerada pelo Módulo de Migração de Carteira do Portal Nutricional.
                            </p>
                        </td>
                    </tr>
                </table>
            </body>
            </html>
            ";

            $mail->Body = $html;
            $mail->send();
            return true;
        } catch (\Throwable $e) {
            error_log('[MigracaoCarteira] Erro ao disparar e-mail: ' . $e->getMessage());
            return false;
        }
    }
}
