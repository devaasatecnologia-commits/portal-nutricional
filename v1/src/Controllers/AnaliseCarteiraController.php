<?php

namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Utils\UserScope;
use PDO;
use Throwable;

class AnaliseCarteiraController
{
    /** Limites de IAG (% vencido / carteira): > CRITICO = CRÍTICO; >= ATENCAO = ATENÇÃO; senão SAUDÁVEL. */
    private const IAG_CRITICO = 15.0;
    private const IAG_ATENCAO = 5.0;

    /** Tolerância de dias da regra de vencido (a mesma de vw_analise_receber_geral). */
    private const SQL_LIMITE_VENCIDO = "(CURRENT_DATE - INTERVAL '3 days')";

    /** Filtro base dos títulos em aberto (mesmo de vw_analise_receber_geral). */
    private const SQL_BASE_RECEBER = "r.excluido = 'N' AND r.vencimento >= DATE '2000-01-01' AND r.status IN (2, 3)
        AND r.tipodoc_fixo <> 4 AND r.simulacao = 'N'";

    private $pdo;

    public function __construct()
    {
        $this->pdo = \getPDO();
    }

    // ======================================================================
    // GESTORES
    // ======================================================================

    /** GET /v1/analise-carteira/admin/gestores (somente admin) */
    public function getTodosGestores(Request $request, Response $response): Response
    {
        return $this->executar($response, function () use ($request) {
            $scope = UserScope::fromRequest($request, $this->pdo);
            if (!$scope->isAdmin) {
                throw new \DomainException('Acesso restrito a administradores.');
            }
            return $this->listarGestores([], []);
        }, 'getTodosGestores');
    }

    /**
     * POST /v1/analise-carteira/gestores
     * Admin: todos os gestores. Demais: apenas os de usuario.dash_gestores.
     */
    public function getGestores(Request $request, Response $response): Response
    {
        return $this->executar($response, function () use ($request) {
            $scope = UserScope::fromRequest($request, $this->pdo);
            return $this->listarGestores(
                $scope->resolverGestores(null),
                $scope->resolverFiliais(null)
            );
        }, 'getGestores');
    }

    // ======================================================================
    // RESUMO / TABELA / TÍTULOS
    // ======================================================================

    /** POST /v1/analise-carteira/resumo-gestor */
    public function getResumoGestor(Request $request, Response $response): Response
    {
        $input = $this->lerCorpo($request);
        return $this->executar($response, function () use ($request, $input) {
            return $this->montarResumo($this->escopo($request, $input));
        }, 'getResumoGestor');
    }

    /** POST /v1/analise-carteira/tabela-gestor */
    public function getTabelaGestor(Request $request, Response $response): Response
    {
        $input = $this->lerCorpo($request);
        return $this->executar($response, function () use ($request, $input) {
            return $this->montarTabela($this->escopo($request, $input));
        }, 'getTabelaGestor');
    }

    /** POST /v1/analise-carteira/titulos-representante */
    public function getTitulosRepresentante(Request $request, Response $response): Response
    {
        $input = $this->lerCorpo($request);
        return $this->executar($response, function () use ($request, $input) {
            return $this->montarTitulos($this->escopo($request, $input), $input);
        }, 'getTitulosRepresentante');
    }

    /** GET /v1/analise-carteira/cliente/{id}/detalhes */
    public function getClienteDetalhes(Request $request, Response $response, array $args): Response
    {
        $idcliente = (int)($args['id'] ?? 0);
        return $this->executar($response, function () use ($request, $idcliente) {
            if ($idcliente <= 0) {
                throw new \InvalidArgumentException('Cliente inválido.');
            }

            // 1. Dados cadastrais do cliente
            $stmtCli = $this->pdo->prepare("
                SELECT c.idcliforemp, c.razao, c.fantasia, COALESCE(c.cnpj, c.cpf) as cnpj_cpf,
                       c.fone, c.tipocliforemp, c.idfilial, cid.descricao as cidade, cid.uf
                FROM cliforemp c
                LEFT JOIN cidade cid ON cid.idcidade = c.idcidade
                WHERE c.idcliforemp = :id
            ");
            $stmtCli->execute(['id' => $idcliente]);
            $cliente = $stmtCli->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                throw new \DomainException('Cliente não encontrado.');
            }

            // 2. Títulos em aberto do cliente
            $stmtTit = $this->pdo->prepare("
                SELECT r.idreceber, r.documento, r.vencimento::text as vencimento,
                       r.valorsaldo::float as valorsaldo,
                       (CURRENT_DATE - r.vencimento)::int as dias_atraso,
                       r.idfilial, r.observacao as historico
                FROM receber r
                WHERE r.idcliforemp = :id
                  AND r.excluido = 'N'
                  AND r.status IN (2, 3)
                  AND r.tipodoc_fixo <> 4
                  AND r.simulacao = 'N'
                  AND r.valorsaldo > 0
                ORDER BY r.vencimento ASC
            ");
            $stmtTit->execute(['id' => $idcliente]);
            $titulos = $stmtTit->fetchAll(PDO::FETCH_ASSOC);

            $totalVencido = 0.0;
            $totalAVencer = 0.0;
            foreach ($titulos as $t) {
                if ($t['dias_atraso'] > 3) {
                    $totalVencido += $t['valorsaldo'];
                } else {
                    $totalAVencer += $t['valorsaldo'];
                }
            }
            $cliente['total_vencido'] = round($totalVencido, 2);
            $cliente['total_a_vencer'] = round($totalAVencer, 2);
            $cliente['total_carteira'] = round($totalVencido + $totalAVencer, 2);

            // 3. Tipos de Eventos de Contato permitidos
            $stmtTipos = $this->pdo->query("
                SELECT idtipoevento, descricao, cor 
                FROM tipo_evento_contato 
                WHERE idtipoevento NOT IN (2, 8) AND inativo = 'N' 
                ORDER BY descricao
            ");
            $tiposEvento = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

            // 4. Últimos Eventos de Cobrança / Contato do Cliente
            $stmtEv = $this->pdo->prepare("
                SELECT ce.sequencial, ce.idtipoevento, tec.descricao as tipo_evento,
                       COALESCE(tec.cor, ce.corevento, '#000000') as cor_evento,
                       ce.data::text as data, ce.hora::text as hora, ce.usuario,
                       ce.descricao as motivo, ce.local as documento
                FROM contato_evento ce
                LEFT JOIN tipo_evento_contato tec ON tec.idtipoevento = ce.idtipoevento
                WHERE ce.idcliforemp = :id
                ORDER BY ce.datahora DESC, ce.sequencial DESC
                LIMIT 30
            ");
            $stmtEv->execute(['id' => $idcliente]);
            $eventos = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

            return [
                'cliente' => $cliente,
                'titulos' => $titulos,
                'tipos_evento' => $tiposEvento,
                'eventos' => $eventos,
            ];
        }, 'getClienteDetalhes');
    }

    /** POST /v1/analise-carteira/cliente/{id}/eventos */
    public function criarEventoCliente(Request $request, Response $response, array $args): Response
    {
        $idcliente = (int)($args['id'] ?? 0);
        $input = $this->lerCorpo($request);
        return $this->executar($response, function () use ($request, $idcliente, $input) {
            if ($idcliente <= 0) {
                throw new \InvalidArgumentException('Cliente não identificado.');
            }

            $tipoevento = (int)($input['tipoevento'] ?? 0);
            if ($tipoevento <= 0) {
                throw new \InvalidArgumentException('Tipo de evento não selecionado.');
            }

            $motivo = trim((string)($input['motivo'] ?? ''));
            if (mb_strlen($motivo) < 3) {
                throw new \InvalidArgumentException('Motivo/observação não preenchido ou muito curto.');
            }

            // Tipos de evento que permitem título vazio: 9, 8, 2, 10, 4
            $tiposPermitemVazio = [9, 8, 2, 10, 4];
            $permiteTituloVazio = in_array($tipoevento, $tiposPermitemVazio, true);

            $titulos = (array)($input['titulos'] ?? []);
            $titulosValidos = array_values(array_filter(array_map('trim', $titulos), fn($t) => $t !== ''));

            if (empty($titulosValidos) && !$permiteTituloVazio) {
                throw new \InvalidArgumentException('Nenhum título foi selecionado e o tipo de evento exige título vinculado.');
            }

            // Dados adicionais do cliente
            $stmtDados = $this->pdo->prepare("
                SELECT c.tipocliforemp, c.idfilial,
                       (SELECT cor FROM tipo_evento_contato WHERE idtipoevento = :tipoevento) as cor
                FROM cliforemp c
                WHERE c.idcliforemp = :id
            ");
            $stmtDados->execute(['tipoevento' => $tipoevento, 'id' => $idcliente]);
            $dados = $stmtDados->fetch(PDO::FETCH_ASSOC);

            if (!$dados) {
                throw new \DomainException('Cliente não encontrado na base de dados.');
            }

            $corEvento = !empty($dados['cor']) ? $dados['cor'] : '#000000';

            // Dados do operador criador
            $user = $request->getAttribute('user') ?? [];
            $nomeusuario = strtoupper((string)($user['username'] ?? 'PORTAL'));
            $cdusuario = (int)($user['idusuario'] ?? 0);

            // Documentos para gravar
            $documentosParaInserir = empty($titulosValidos) ? ['.'] : $titulosValidos;

            $sqlInsert = "
                INSERT INTO public.contato_evento
                (
                    idcontato, sequencial, idtipoevento, \"data\", datahora, hora, 
                    usuario, descricao, idcliforemp, ok, dataok, tipocliforemp, 
                    origem, \"local\", datahorainicio, datahorafim, titulo, horafim, 
                    agendardiatodo, particular, idempresa, idfilial, televenda, 
                    usuariocriador, idcriador, idorigem, corevento, idprocesso, 
                    datahoraultimaatualizacao, descricaohint, status, geolocalizacao
                )
                VALUES (
                    0,
                    nextval('gi_contato_evento'),
                    :tipoevento,
                    CURRENT_DATE,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIME,
                    :usuario,
                    :descricao,
                    :idcliforemp,
                    'N',
                    '1900-01-01',
                    :tipocliforemp,
                    1,
                    :local,
                    '1900-01-01 00:00:00',
                    '1900-01-01 00:00:00',
                    'Evento vindo pelo Portal',
                    '00:00:00',
                    'N',
                    'N',
                    1,
                    :idfilial,
                    'N',
                    :usuariocriador,
                    :idcriador,
                    1,
                    :corevento,
                    0,
                    CURRENT_TIMESTAMP,
                    '.',
                    0,
                    '.'
                )
                RETURNING sequencial
            ";
            $stmtIns = $this->pdo->prepare($sqlInsert);

            $sucessos = 0;
            $sequenciais = [];

            foreach ($documentosParaInserir as $doc) {
                $stmtIns->execute([
                    'tipoevento' => $tipoevento,
                    'usuario' => $nomeusuario,
                    'descricao' => $motivo,
                    'idcliforemp' => $idcliente,
                    'tipocliforemp' => (int)($dados['tipocliforemp'] ?? 0),
                    'local' => $doc,
                    'idfilial' => (int)($dados['idfilial'] ?? 1),
                    'usuariocriador' => $nomeusuario,
                    'idcriador' => $cdusuario,
                    'corevento' => $corEvento,
                ]);
                $seq = $stmtIns->fetchColumn();
                if ($seq) {
                    $sucessos++;
                    $sequenciais[] = (int)$seq;
                }
            }

            return [
                'success' => true,
                'message' => $sucessos === 1
                    ? 'Evento cadastrado com sucesso!'
                    : "Foram cadastrados {$sucessos} eventos com sucesso para os títulos selecionados!",
                'sucessos' => $sucessos,
                'sequenciais' => $sequenciais,
            ];
        }, 'criarEventoCliente');
    }

    /** POST /v1/analise-carteira/exportar  (tipo: representantes | titulos) */
    public function exportar(Request $request, Response $response): Response
    {
        $input = $this->lerCorpo($request);
        $tipo = $input['tipo'] ?? 'representantes';
        return $this->executar($response, function () use ($request, $input, $tipo) {
            if ($tipo === 'representantes') {
                return $this->montarTabela($this->escopo($request, $input));
            }
            if ($tipo === 'titulos') {
                return $this->montarTitulos($this->escopo($request, $input), $input);
            }
            throw new \InvalidArgumentException('Tipo inválido.');
        }, 'exportar');
    }

    // ======================================================================
    // CONSULTAS
    // ======================================================================

    private function listarGestores(array $gestores, array $filiais): array
    {
        $params = [];
        $where = $this->filtroEscopo(
            ['gestores' => $gestores, 'sem_gestor' => false, 'filiais' => $filiais],
            'varg.idsupervisor',
            'varg.idfilial',
            $params
        );
        $sql = "SELECT DISTINCT
                    COALESCE(varg.idsupervisor, 0) AS id,
                    CASE WHEN COALESCE(varg.idsupervisor, 0) = 0 THEN 'Sem gestor'
                         ELSE COALESCE(NULLIF(TRIM(varg.nomegestor), ''), 'Gestor ' || varg.idsupervisor) END AS nome
                FROM vw_analise_receber_geral varg
                WHERE 1=1 $where
                ORDER BY nome";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
        }
        return $rows;
    }

    /** Linhas por representante: valores da view + contagens reais (DISTINCT) da base. */
    private function montarTabela(array $esc): array
    {
        $params = [];
        $where = $this->filtroEscopo($esc, 'varg.idsupervisor', 'varg.idfilial', $params);
        $sql = "SELECT
                    varg.idvendrepre AS id_representante,
                    MAX(varg.nomerepresentante) AS nome_representante,
                    COALESCE(SUM(varg.vencidos), 0)::float AS vencidos,
                    COALESCE(SUM(varg.total_receber), 0)::float AS total_receber,
                    COALESCE(SUM(varg.a_vencer), 0)::float AS a_vencer,
                    COALESCE(SUM(varg.prox_30_dias), 0)::float AS prox_30_dias,
                    COALESCE(SUM(varg.dias_30), 0)::float AS dias_30,
                    COALESCE(SUM(varg.dias_60), 0)::float AS dias_60,
                    COALESCE(SUM(varg.mais_60_dias), 0)::float AS mais_60
                FROM vw_analise_receber_geral varg
                WHERE 1=1 $where
                GROUP BY varg.idvendrepre
                ORDER BY vencidos DESC, nome_representante";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $params = [];
        $where = $this->filtroEscopo($esc, 'r.idsupervisor', 'r.idfilial', $params);
        $sql = "SELECT
                    r.idvendrepre AS id_representante,
                    COUNT(*) AS titulos_total,
                    COUNT(*) FILTER (WHERE r.vencimento < " . self::SQL_LIMITE_VENCIDO . ") AS titulos_vencidos,
                    COUNT(DISTINCT r.idcliforemp) AS clientes_total,
                    COUNT(DISTINCT r.idcliforemp) FILTER (WHERE r.vencimento < " . self::SQL_LIMITE_VENCIDO . ") AS clientes_vencidos
                FROM receber r
                WHERE " . self::SQL_BASE_RECEBER . " $where
                GROUP BY r.idvendrepre";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $contagens = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
            $contagens[(string)$c['id_representante']] = $c;
        }

        $totalVencidos = 0.0;
        foreach ($rows as $r) {
            $totalVencidos += (float)$r['vencidos'];
        }

        $saida = [];
        foreach ($rows as $r) {
            $c = $contagens[(string)$r['id_representante']] ?? [];
            $venc = (float)$r['vencidos'];
            $total = (float)$r['total_receber'];
            $iag = $this->pct($venc, $total);
            $saida[] = [
                'id_representante' => $r['id_representante'] === null ? 0 : (int)$r['id_representante'],
                'nome_representante' => trim((string)$r['nome_representante']) !== '' ? $r['nome_representante'] : 'Sem representante',
                'vencidos' => $venc,
                'total_receber' => $total,
                'a_vencer' => (float)$r['a_vencer'],
                'prox_30_dias' => (float)$r['prox_30_dias'],
                'dias_30' => (float)$r['dias_30'],
                'dias_60' => (float)$r['dias_60'],
                'mais_60' => (float)$r['mais_60'],
                'perc_30' => $this->pct((float)$r['dias_30'], $venc),
                'perc_60' => $this->pct((float)$r['dias_60'], $venc),
                'perc_mais_60' => $this->pct((float)$r['mais_60'], $venc),
                'iag' => $iag,
                'performance' => $this->performance($iag),
                'percentual' => $this->pct($venc, $totalVencidos),
                'titulos_total' => (int)($c['titulos_total'] ?? 0),
                'titulos_vencidos' => (int)($c['titulos_vencidos'] ?? 0),
                'clientes_total' => (int)($c['clientes_total'] ?? 0),
                'clientes_vencidos' => (int)($c['clientes_vencidos'] ?? 0),
            ];
        }
        return $saida;
    }

    private function montarResumo(array $esc): array
    {
        $params = [];
        $where = $this->filtroEscopo($esc, 'varg.idsupervisor', 'varg.idfilial', $params);
        $sql = "SELECT
                    COALESCE(SUM(varg.vencidos), 0)::float AS vencidos,
                    COALESCE(SUM(varg.total_receber), 0)::float AS total_receber,
                    COALESCE(SUM(varg.dias_30), 0)::float AS dias_30,
                    COALESCE(SUM(varg.dias_60), 0)::float AS dias_60,
                    COALESCE(SUM(varg.mais_60_dias), 0)::float AS mais_60,
                    COALESCE(SUM(varg.a_vencer), 0)::float AS a_vencer,
                    COALESCE(SUM(varg.prox_30_dias), 0)::float AS prox_30_dias
                FROM vw_analise_receber_geral varg
                WHERE 1=1 $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $k = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        // Contagens reais da base (clientes distintos no total, não soma por linha)
        $params = [];
        $where = $this->filtroEscopo($esc, 'r.idsupervisor', 'r.idfilial', $params);
        $sql = "SELECT
                    COUNT(*) AS titulos_total,
                    COUNT(*) FILTER (WHERE r.vencimento < " . self::SQL_LIMITE_VENCIDO . ") AS titulos_vencidos,
                    COUNT(DISTINCT r.idcliforemp) AS clientes_total,
                    COUNT(DISTINCT r.idcliforemp) FILTER (WHERE r.vencimento < " . self::SQL_LIMITE_VENCIDO . ") AS clientes_vencidos
                FROM receber r
                WHERE " . self::SQL_BASE_RECEBER . " $where";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $c = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $venc = (float)($k['vencidos'] ?? 0);
        $total = (float)($k['total_receber'] ?? 0);
        $iag = $this->pct($venc, $total);

        return [
            'kpis' => [
                'vencidos' => $venc,
                'total_receber' => $total,
                'a_vencer' => (float)($k['a_vencer'] ?? 0),
                'prox_30_dias' => (float)($k['prox_30_dias'] ?? 0),
                'dias_30' => (float)($k['dias_30'] ?? 0),
                'dias_60' => (float)($k['dias_60'] ?? 0),
                'mais_60' => (float)($k['mais_60'] ?? 0),
                'iag' => $iag,
                'performance' => $this->performance($iag),
                'titulos_total' => (int)($c['titulos_total'] ?? 0),
                'titulos_vencidos' => (int)($c['titulos_vencidos'] ?? 0),
                'clientes_total' => (int)($c['clientes_total'] ?? 0),
                'clientes_vencidos' => (int)($c['clientes_vencidos'] ?? 0),
            ],
            'dados_pizza' => [
                ['faixa' => '30 Dias', 'valor' => (float)($k['dias_30'] ?? 0)],
                ['faixa' => '60 Dias', 'valor' => (float)($k['dias_60'] ?? 0)],
                ['faixa' => '+60 Dias', 'valor' => (float)($k['mais_60'] ?? 0)],
            ],
            'top_representantes' => array_slice($this->montarTabela($esc), 0, 5),
        ];
    }

    /**
     * Títulos vencidos (mesma regra da tabela: vencimento < hoje - 3 dias) do representante,
     * dentro do gestor selecionado/permitido.
     */
    private function montarTitulos(array $esc, array $input): array
    {
        $idRep = $input['id_representante'] ?? null;
        if (!is_scalar($idRep) || !ctype_digit((string)$idRep)) {
            throw new \InvalidArgumentException('Representante inválido.');
        }

        $params = ['id_rep' => (int)$idRep];
        $where = $this->filtroEscopo($esc, 'vfe.idgestor', 'vfe.idfilial', $params);
        $sql = "SELECT
                    vfe.idfilial,
                    vfe.idcliforemp AS id_cliente,
                    vfe.nomefantasia AS nome_fantasia,
                    vfe.documento,
                    vfe.vencimento::text AS vencimento,
                    vfe.valorsaldo::float AS valor_saldo,
                    (CURRENT_DATE - vfe.vencimento) AS dias_atraso,
                    vfe.ult_evento_dias,
                    vfe.usuario,
                    vfe.evento
                FROM vw_financeiro_eventos vfe
                WHERE COALESCE(vfe.idrepresentante, 0) = :id_rep
                    AND vfe.vencimento < " . self::SQL_LIMITE_VENCIDO . "
                    AND vfe.valorsaldo > 0
                    $where
                ORDER BY vfe.vencimento ASC, vfe.nomefantasia, vfe.documento";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$t) {
            $t['chave'] = implode('|', [$t['idfilial'], $t['id_cliente'], $t['documento'], $t['vencimento']]);
            $t['valor_saldo'] = (float)$t['valor_saldo'];
            $t['dias_atraso'] = (int)$t['dias_atraso'];
            $t['ult_evento_dias'] = $t['ult_evento_dias'] === null ? null : (int)$t['ult_evento_dias'];
        }
        return $rows;
    }

    // ======================================================================
    // APOIO
    // ======================================================================

    /**
     * Escopo efetivo da requisição. Identidade/admin vêm do JWT (UserScope), nunca do corpo.
     * id_gestor ausente/vazio = todos os permitidos; 0 = "Sem gestor" (só sem restrição de gestores).
     *
     * @throws \DomainException gestor fora do escopo (403)
     * @throws \InvalidArgumentException parâmetro inválido (400)
     */
    private function escopo(Request $request, array $input): array
    {
        $scope = UserScope::fromRequest($request, $this->pdo);

        $raw = $input['id_gestor'] ?? null;
        $semGestor = false;
        $gestores = [];

        if ($raw === null || $raw === '') {
            $gestores = $scope->resolverGestores(null);
        } else {
            if (!is_scalar($raw) || !ctype_digit(trim((string)$raw))) {
                throw new \InvalidArgumentException('Gestor inválido.');
            }
            if ((int)$raw === 0) {
                if ($scope->gestorRestrito()) {
                    throw new \DomainException('Acesso negado: gestor fora do seu escopo.');
                }
                $semGestor = true;
            } else {
                $gestores = $scope->resolverGestores((int)$raw);
            }
        }

        return [
            'gestores' => $gestores,
            'sem_gestor' => $semGestor,
            'filiais' => $scope->resolverFiliais(null),
        ];
    }

    /** Monta " AND ..." com parâmetros nomeados para gestor/filial. */
    private function filtroEscopo(array $esc, string $colGestor, string $colFilial, array &$params): string
    {
        $sql = '';
        if (!empty($esc['sem_gestor'])) {
            $sql .= " AND COALESCE($colGestor, 0) = 0";
        } elseif (!empty($esc['gestores'])) {
            $sql .= " AND $colGestor IN (" . $this->listaParams('g', $esc['gestores'], $params) . ')';
        }
        if (!empty($esc['filiais'])) {
            $sql .= " AND $colFilial IN (" . $this->listaParams('f', $esc['filiais'], $params) . ')';
        }
        return $sql;
    }

    private function listaParams(string $prefixo, array $ids, array &$params): string
    {
        $nomes = [];
        foreach (array_values($ids) as $i => $id) {
            $n = $prefixo . $i;
            $params[$n] = (int)$id;
            $nomes[] = ':' . $n;
        }
        return implode(',', $nomes);
    }

    private function pct(float $parte, float $total): float
    {
        return $total > 0 ? round($parte * 100 / $total, 2) : 0.0;
    }

    private function performance(float $iag): string
    {
        if ($iag > self::IAG_CRITICO) {
            return 'CRÍTICO';
        }
        return $iag >= self::IAG_ATENCAO ? 'ATENÇÃO' : 'SAUDÁVEL';
    }

    private function lerCorpo(Request $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed)) {
            return $parsed;
        }
        $body = $request->getBody();
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $dados = json_decode((string)$body, true);
        return is_array($dados) ? $dados : [];
    }

    private function executar(Response $response, callable $fn, string $contexto): Response
    {
        try {
            return $this->json($response, $fn());
        } catch (\DomainException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return $this->json($response, ['error' => $e->getMessage()], 400);
        } catch (Throwable $e) {
            error_log("AnaliseCarteira::$contexto " . get_class($e) . ' code=' . $e->getCode());
            return $this->json($response, ['error' => 'Erro ao processar a consulta.'], 500);
        }
    }

    private function json(Response $response, $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
