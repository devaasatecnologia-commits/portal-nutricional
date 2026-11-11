<?php
namespace Nutricional\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Nutricional\Services\ChamadosIaService;
use PDO;

/**
 * Controller da Central de Chamados & Suporte Inteligente do Portal Nutricional.
 * Integração bidirecional com a tabela nativa 'chamado' e 'chamado_operacao' do ERP.
 */
class ChamadosController
{
    private PDO $pdo;
    private ChamadosIaService $ia;

    public function __construct()
    {
        $this->pdo = \getPDO();
        $this->ia = new ChamadosIaService($this->pdo);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    private function erro(Response $response, string $msg, int $status = 400): Response
    {
        return $this->json($response, ['success' => false, 'error' => $msg], $status);
    }

    private function limpaWin1252(string $str): string
    {
        // Remove emojis de 4 bytes e caracteres incompatíveis com WIN1252 do Postgres legado
        return (string)preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $str);
    }

    private function parsedBody(Request $request): array
    {
        $body = $request->getParsedBody();
        if (is_array($body) && !empty($body)) {
            return $body;
        }
        $raw = (string)$request->getBody();
        if ($raw !== '') {
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }
        return [];
    }

    private function usuario(Request $request): array
    {
        $user = $request->getAttribute('user') ?? [];
        $idusuario = (int)($user['idusuario'] ?? 0);
        $idcliforemp = (int)($user['uid'] ?? $user['idcliforemp'] ?? 0);
        $username = strtoupper((string)($user['username'] ?? 'USUARIO'));
        
        $isAdmin = (bool)($user['is_admin'] ?? false) || in_array('admin', $user['permissoes'] ?? [], true);
        if (!$isAdmin && $idusuario > 0) {
            $stAdm = $this->pdo->prepare("SELECT 1 FROM usuarios_admin WHERE idusuario = :id AND ativo = true LIMIT 1");
            $stAdm->execute(['id' => $idusuario]);
            if ($stAdm->fetchColumn()) {
                $isAdmin = true;
            }
        }
        if (!$isAdmin && in_array($username, ['ALAN', 'MARCON', 'GERENTE', 'ROBSON'], true)) {
            $isAdmin = true;
        }

        $podeVerTodos = $isAdmin;
        $tiposPermitidos = 'TODOS';
        $permissoesModulos = (array)($user['permissoes'] ?? []);

        if ($idusuario > 0 || $idcliforemp > 0) {
            try {
                $stmtPol = $this->pdo->prepare("
                    SELECT idusuario, chamados_ver_todos, chamados_tipos_permitidos 
                    FROM usuario 
                    WHERE idusuario = :id OR idcliforemp = :idcliforemp 
                    LIMIT 1
                ");
                $stmtPol->execute([
                    'id' => $idusuario,
                    'idcliforemp' => $idcliforemp
                ]);
                $pol = $stmtPol->fetch(\PDO::FETCH_ASSOC);
                if ($pol) {
                    $idReal = (int)($pol['idusuario'] ?? $idusuario);
                    if (!$podeVerTodos && ($pol['chamados_ver_todos'] ?? 'N') === 'S') {
                        $podeVerTodos = true;
                    }
                    if (!$isAdmin && !empty($pol['chamados_tipos_permitidos'])) {
                        $tiposPermitidos = trim($pol['chamados_tipos_permitidos']);
                    }

                    // Se não for admin, obtém os módulos exatos liberados em usuario_permissoes
                    if (!$isAdmin && $idReal > 0) {
                        $stP = $this->pdo->prepare("
                            SELECT sm.slug 
                            FROM usuario_permissoes up
                            JOIN sistema_modulos sm ON sm.id = up.idmodulo
                            WHERE up.idusuario = :id AND sm.ativo = true
                        ");
                        $stP->execute(['id' => $idReal]);
                        $slugsBanco = $stP->fetchAll(\PDO::FETCH_COLUMN);
                        if (!empty($slugsBanco)) {
                            $permissoesModulos = array_unique(array_merge($permissoesModulos, $slugsBanco));
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silencioso se tabela não responder
            }
        }

        return [
            'idusuario' => $idusuario,
            'idcliforemp' => $idcliforemp,
            'username' => $username,
            'is_admin' => $isAdmin,
            'pode_ver_todos' => $podeVerTodos,
            'tipos_permitidos' => $tiposPermitidos,
            'permissoes' => $permissoesModulos
        ];
    }

    /**
     * GET /v1/chamados/dashboard
     * Retorna KPIs globais, lista dos módulos do Portal para filtros e categorias.
     */
    public function dashboard(Request $request, Response $response): Response
    {
        try {
            $u = $this->usuario($request);
            $pUser = ['uid' => $u['idcliforemp'], 'u' => $u['username']];

            // KPIs
            if ($u['pode_ver_todos']) {
                $kpiTotal = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado")->fetchColumn();
                $kpiAbertos = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1)")->fetchColumn();
                $kpiEmCorrecao = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status = 2")->fetchColumn();
                $kpiCriticos = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1, 2) AND criticidade = 'critica'")->fetchColumn();
                $kpiConcluidosMes = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status = 3 AND (concluido_em >= DATE_TRUNC('month', CURRENT_DATE) OR data >= DATE_TRUNC('month', CURRENT_DATE))")->fetchColumn();
            } else {
                $stK = $this->pdo->prepare("SELECT COUNT(*) FROM chamado WHERE (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)");
                $stK->execute($pUser);
                $kpiTotal = (int)$stK->fetchColumn();

                $stA = $this->pdo->prepare("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1) AND (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)");
                $stA->execute($pUser);
                $kpiAbertos = (int)$stA->fetchColumn();

                $stC = $this->pdo->prepare("SELECT COUNT(*) FROM chamado WHERE status = 2 AND (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)");
                $stC->execute($pUser);
                $kpiEmCorrecao = (int)$stC->fetchColumn();

                $stCr = $this->pdo->prepare("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1, 2) AND criticidade = 'critica' AND (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)");
                $stCr->execute($pUser);
                $kpiCriticos = (int)$stCr->fetchColumn();

                $stCm = $this->pdo->prepare("SELECT COUNT(*) FROM chamado WHERE status = 3 AND (concluido_em >= DATE_TRUNC('month', CURRENT_DATE) OR data >= DATE_TRUNC('month', CURRENT_DATE)) AND (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)");
                $stCm->execute($pUser);
                $kpiConcluidosMes = (int)$stCm->fetchColumn();
            }
            $taxaResolucao = $kpiTotal > 0 ? round(($kpiConcluidosMes / $kpiTotal) * 100, 1) : 0;

            // Distribuição por Sistema
            if ($u['pode_ver_todos']) {
                $porSistema = $this->pdo->query("
                    SELECT COALESCE(tipo_sistema, 'OUTROS') AS sistema, COUNT(*) AS total
                    FROM chamado
                    GROUP BY COALESCE(tipo_sistema, 'OUTROS')
                    ORDER BY total DESC
                ")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stS = $this->pdo->prepare("
                    SELECT COALESCE(tipo_sistema, 'OUTROS') AS sistema, COUNT(*) AS total
                    FROM chamado
                    WHERE (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)
                    GROUP BY COALESCE(tipo_sistema, 'OUTROS')
                    ORDER BY total DESC
                ");
                $stS->execute($pUser);
                $porSistema = $stS->fetchAll(PDO::FETCH_ASSOC);
            }

            // Distribuição por Criticidade
            if ($u['pode_ver_todos']) {
                $porCriticidade = $this->pdo->query("
                    SELECT COALESCE(criticidade, 'media') AS criticidade, COUNT(*) AS total
                    FROM chamado
                    GROUP BY COALESCE(criticidade, 'media')
                    ORDER BY CASE COALESCE(criticidade, 'media') WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END
                ")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stCr = $this->pdo->prepare("
                    SELECT COALESCE(criticidade, 'media') AS criticidade, COUNT(*) AS total
                    FROM chamado
                    WHERE (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)
                    GROUP BY COALESCE(criticidade, 'media')
                    ORDER BY CASE COALESCE(criticidade, 'media') WHEN 'critica' THEN 1 WHEN 'alta' THEN 2 WHEN 'media' THEN 3 ELSE 4 END
                ");
                $stCr->execute($pUser);
                $porCriticidade = $stCr->fetchAll(PDO::FETCH_ASSOC);
            }

            // Top Módulos com mais Demandas
            if ($u['pode_ver_todos']) {
                $topModulos = $this->pdo->query("
                    SELECT COALESCE(modulo_nome, modulo_slug, 'Geral') AS modulo, COUNT(*) AS total
                    FROM chamado
                    GROUP BY COALESCE(modulo_nome, modulo_slug, 'Geral')
                    ORDER BY total DESC
                    LIMIT 6
                ")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stM = $this->pdo->prepare("
                    SELECT COALESCE(modulo_nome, modulo_slug, 'Geral') AS modulo, COUNT(*) AS total
                    FROM chamado
                    WHERE (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)
                    GROUP BY COALESCE(modulo_nome, modulo_slug, 'Geral')
                    ORDER BY total DESC
                    LIMIT 6
                ");
                $stM->execute($pUser);
                $topModulos = $stM->fetchAll(PDO::FETCH_ASSOC);
            }

            // Últimos Chamados Resolvidos (Entregas Recentes)
            if ($u['pode_ver_todos']) {
                $resolvidosRecentes = $this->pdo->query("
                    SELECT idchamado, titulo, tipo_sistema, modulo_nome, atendente, versao_entrega, concluido_em, datahora
                    FROM chamado
                    WHERE status = 3
                    ORDER BY COALESCE(concluido_em, datahora) DESC
                    LIMIT 6
                ")->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $stR = $this->pdo->prepare("
                    SELECT idchamado, titulo, tipo_sistema, modulo_nome, atendente, versao_entrega, concluido_em, datahora
                    FROM chamado
                    WHERE status = 3 AND (idcliforemp = :uid OR solicitante ILIKE :u OR usuario ILIKE :u)
                    ORDER BY COALESCE(concluido_em, datahora) DESC
                    LIMIT 6
                ");
                $stR->execute($pUser);
                $resolvidosRecentes = $stR->fetchAll(PDO::FETCH_ASSOC);
            }

            foreach ($resolvidosRecentes as &$r) {
                $r['concluido_fmt'] = !empty($r['concluido_em']) ? date('d/m H:i', strtotime($r['concluido_em'])) : date('d/m', strtotime($r['datahora']));
            }

            // Módulos para os selects
            $modulos = $this->pdo->query("SELECT slug, nome, setor, icon FROM sistema_modulos WHERE ativo = true ORDER BY setor, nome")->fetchAll(PDO::FETCH_ASSOC);

            // Filtragem de módulos por permissão caso não seja admin
            if (!$u['is_admin']) {
                $perms = $u['permissoes'];
                $modulos = array_values(array_filter($modulos, function($m) use ($perms) {
                    return in_array($m['slug'], $perms, true);
                }));
            }

            // Sistemas corporativos suportados
            $sistemas = [
                ['id' => 'PORTAL',      'nome' => 'Portal Nutricional (Módulos Web)', 'icon' => 'fa-globe', 'badge' => 'bg-indigo-50 text-indigo-700 border-indigo-200'],
                ['id' => 'ERP_EMA',     'nome' => 'ERP Ema Desktop',                 'icon' => 'fa-database', 'badge' => 'bg-blue-50 text-blue-700 border-blue-200'],
                ['id' => 'MERCOS',      'nome' => 'Mercos (Força de Vendas)',        'icon' => 'fa-handshake', 'badge' => 'bg-emerald-50 text-emerald-700 border-emerald-200'],
                ['id' => 'COBLI',       'nome' => 'Cobli (Telemetria & Frotas)',     'icon' => 'fa-truck', 'badge' => 'bg-amber-50 text-amber-700 border-amber-200'],
                ['id' => 'LOGISOFT',    'nome' => 'Logisoft (CTe / MDFe / CIOT)',    'icon' => 'fa-file-invoice', 'badge' => 'bg-teal-50 text-teal-700 border-teal-200'],
                ['id' => 'EMAIL',       'nome' => 'Email Corporativo / Webmail',     'icon' => 'fa-envelope', 'badge' => 'bg-purple-50 text-purple-700 border-purple-200'],
                ['id' => 'SITE',        'nome' => 'Site Institucional / E-commerce', 'icon' => 'fa-cart-shopping', 'badge' => 'bg-rose-50 text-rose-700 border-rose-200'],
                ['id' => 'EQUIPAMENTO', 'nome' => 'Equipamentos (Impressora, Ramal, PC)', 'icon' => 'fa-print', 'badge' => 'bg-cyan-50 text-cyan-700 border-cyan-200'],
                ['id' => 'PLANILHAS',   'nome' => 'Planilhas Excel / Relatórios',    'icon' => 'fa-file-excel', 'badge' => 'bg-green-50 text-green-700 border-green-200'],
                ['id' => 'SERVIDOR',    'nome' => 'Servidores, Rede & VPN',          'icon' => 'fa-server', 'badge' => 'bg-slate-100 text-slate-700 border-slate-300'],
                ['id' => 'OUTROS',      'nome' => 'Outros Assuntos',                 'icon' => 'fa-circle-question', 'badge' => 'bg-gray-50 text-gray-700 border-gray-200']
            ];

            // Filtragem de sistemas pelo perfil de abertura permitido
            if ($u['tipos_permitidos'] !== 'TODOS') {
                $tiposPermitidosArr = array_map('trim', explode(',', $u['tipos_permitidos']));
                $sistemas = array_values(array_filter($sistemas, function($s) use ($tiposPermitidosArr) {
                    return in_array($s['id'], $tiposPermitidosArr, true);
                }));
            }

            return $this->json($response, [
                'success' => true,
                'kpis' => [
                    'total' => $kpiTotal,
                    'abertos' => $kpiAbertos,
                    'em_correcao' => $kpiEmCorrecao,
                    'criticos' => $kpiCriticos,
                    'concluidos_mes' => $kpiConcluidosMes,
                    'taxa_resolucao' => $taxaResolucao
                ],
                'metricas' => [
                    'por_sistema' => $porSistema,
                    'por_criticidade' => $porCriticidade,
                    'top_modulos' => $topModulos,
                    'resolvidos_recentes' => $resolvidosRecentes
                ],
                'modulos' => $modulos,
                'sistemas' => $sistemas,
                'usuario' => $u
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar dashboard de chamados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/chamados/listar
     * Lista com filtros por status, módulo, criticidade e termo de busca.
     */
    public function listar(Request $request, Response $response): Response
    {
        try {
            $q = $request->getQueryParams();
            $status = isset($q['status']) && $q['status'] !== '' ? (int)$q['status'] : null;
            $modulo = trim((string)($q['modulo'] ?? ''));
            $criticidade = trim((string)($q['criticidade'] ?? ''));
            $categoria = trim((string)($q['categoria'] ?? ''));
            $tipoSistema = trim((string)($q['tipo_sistema'] ?? ''));
            $busca = trim((string)($q['busca'] ?? ''));
            $dataInicio = trim((string)($q['data_inicio'] ?? ''));
            $dataFim = trim((string)($q['data_fim'] ?? ''));
            $atendente = trim((string)($q['atendente'] ?? ''));

            $u = $this->usuario($request);
            $where = ['1=1'];
            $params = [];

            if (!$u['pode_ver_todos']) {
                $where[] = '(c.idcliforemp = :uid_solicitante OR c.solicitante ILIKE :username_solicitante OR c.usuario ILIKE :username_solicitante)';
                $params['uid_solicitante'] = $u['idcliforemp'];
                $params['username_solicitante'] = $u['username'];
            } elseif ($atendente !== '' && $atendente !== 'todos') {
                if ($atendente === 'mim') {
                    $where[] = '(c.atendente ILIKE :atendente OR (c.atendente IS NULL AND :atendente = \'ALAN\'))';
                    $params['atendente'] = $u['username'];
                } elseif ($atendente === 'sem_atendente') {
                    $where[] = "(c.atendente IS NULL OR c.atendente = '' OR c.atendente = '.')";
                } else {
                    $where[] = 'c.atendente ILIKE :atendente';
                    $params['atendente'] = '%' . $atendente . '%';
                }
            }

            if ($status !== null) {
                $where[] = 'c.status = :status';
                $params['status'] = $status;
            }
            if ($tipoSistema !== '' && $tipoSistema !== 'todos') {
                $where[] = 'c.tipo_sistema = :tipoSistema';
                $params['tipoSistema'] = $tipoSistema;
            }
            if ($modulo !== '' && $modulo !== 'todos') {
                $where[] = 'c.modulo_slug = :modulo';
                $params['modulo'] = $modulo;
            }
            if ($criticidade !== '' && $criticidade !== 'todas') {
                $where[] = 'c.criticidade = :criticidade';
                $params['criticidade'] = $criticidade;
            }
            if ($categoria !== '' && $categoria !== 'todas') {
                $where[] = 'c.categoria = :categoria';
                $params['categoria'] = $categoria;
            }
            if ($dataInicio !== '') {
                $where[] = 'c.data >= :dataInicio::date';
                $params['dataInicio'] = $dataInicio;
            }
            if ($dataFim !== '') {
                $where[] = 'c.data <= :dataFim::date';
                $params['dataFim'] = $dataFim;
            }
            if ($busca !== '') {
                $where[] = '(c.titulo ILIKE :busca OR c.motivo ILIKE :busca OR c.solicitante ILIKE :busca OR c.observacao ILIKE :busca OR CAST(c.idchamado AS TEXT) ILIKE :busca)';
                $params['busca'] = '%' . $busca . '%';
            }

            $sql = "
                SELECT 
                    c.idchamado,
                    COALESCE(c.titulo, c.motivo, 'Sem título') AS titulo,
                    c.motivo,
                    COALESCE(c.tipo_sistema, 'PORTAL') AS tipo_sistema,
                    COALESCE(c.subtipo_item, 'Geral') AS subtipo_item,
                    c.modulo_slug,
                    COALESCE(c.modulo_nome, sm.nome, 'Portal Geral') AS modulo_nome,
                    COALESCE(c.categoria, 'bug') AS categoria,
                    COALESCE(c.criticidade, 'media') AS criticidade,
                    c.status,
                    c.solicitante,
                    c.atendente,
                    c.idfilial,
                    c.datahora,
                    c.concluido_em,
                    c.versao_entrega,
                    c.anexo_url,
                    (SELECT COUNT(*) FROM chamado_operacao op WHERE op.idchamado = c.idchamado) AS total_interacoes
                FROM chamado c
                LEFT JOIN sistema_modulos sm ON sm.slug = c.modulo_slug
                WHERE " . implode(' AND ', $where) . "
                ORDER BY 
                    CASE WHEN c.criticidade = 'critica' AND c.status IN (0,1,2) THEN 1
                         WHEN c.criticidade = 'alta' AND c.status IN (0,1,2) THEN 2
                         ELSE 3 END,
                    c.idchamado DESC
                LIMIT 150
            ";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapeia labels e badges
            $mapaStatus = [
                0 => ['label' => 'Aberto', 'cor' => 'bg-amber-100 text-amber-800 border-amber-300'],
                1 => ['label' => 'Em Análise', 'cor' => 'bg-blue-100 text-blue-800 border-blue-300'],
                2 => ['label' => 'Em Correção', 'cor' => 'bg-purple-100 text-purple-800 border-purple-300'],
                3 => ['label' => 'Concluído', 'cor' => 'bg-emerald-100 text-emerald-800 border-emerald-300'],
                4 => ['label' => 'Cancelado', 'cor' => 'bg-slate-100 text-slate-700 border-slate-300'],
            ];

            foreach ($itens as &$it) {
                $st = (int)$it['status'];
                $it['status_info'] = $mapaStatus[$st] ?? ['label' => 'Desconhecido', 'cor' => 'bg-gray-100 text-gray-700'];
                $it['data_fmt'] = !empty($it['datahora']) ? date('d/m/Y H:i', strtotime($it['datahora'])) : '';
                $it['concluido_fmt'] = !empty($it['concluido_em']) ? date('d/m/Y H:i', strtotime($it['concluido_em'])) : '';
            }

            return $this->json($response, ['success' => true, 'chamados' => $itens]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao listar chamados: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /v1/chamados/detalhes/{id}
     * Carrega o chamado completo com o Dossiê IA e a timeline de interações.
     */
    public function detalhes(Request $request, Response $response, array $args): Response
    {
        try {
            $id = (int)($args['id'] ?? 0);
            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            $stmt = $this->pdo->prepare("
                SELECT c.*, sm.nome AS modulo_oficial, sm.icon AS modulo_icon
                FROM chamado c
                LEFT JOIN sistema_modulos sm ON sm.slug = c.modulo_slug
                WHERE c.idchamado = :id
            ");
            $stmt->execute(['id' => $id]);
            $chamado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }

            $u = $this->usuario($request);
            if (!$u['pode_ver_todos']) {
                $eDono = ((int)($chamado['idcliforemp'] ?? 0) === $u['idcliforemp'])
                    || (strtoupper((string)($chamado['solicitante'] ?? '')) === $u['username'])
                    || (strtoupper((string)($chamado['usuario'] ?? '')) === $u['username']);
                if (!$eDono) {
                    return $this->erro($response, 'Acesso não autorizado aos detalhes deste chamado.', 403);
                }
            }

            // Timeline de operacoes
            $stmtOp = $this->pdo->prepare("
                SELECT 
                    idoperacao,
                    descricao,
                    datahora,
                    usuario,
                    acao,
                    COALESCE(tipo, 'comentario') AS tipo,
                    detalhes,
                    anexo_url
                FROM chamado_operacao
                WHERE idchamado = :id
                ORDER BY datahora ASC, idoperacao ASC
            ");
            $stmtOp->execute(['id' => $id]);
            $timeline = $stmtOp->fetchAll(PDO::FETCH_ASSOC);

            foreach ($timeline as &$op) {
                $op['data_fmt'] = !empty($op['datahora']) ? date('d/m/Y H:i', strtotime($op['datahora'])) : '';
            }

            $chamado['data_fmt'] = !empty($chamado['datahora']) ? date('d/m/Y H:i', strtotime($chamado['datahora'])) : '';
            $chamado['concluido_fmt'] = !empty($chamado['concluido_em']) ? date('d/m/Y H:i', strtotime($chamado['concluido_em'])) : '';

            return $this->json($response, [
                'success' => true,
                'chamado' => $chamado,
                'timeline' => $timeline
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao carregar detalhes: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/triagem-ia
     * Realiza a pré-análise e formatação com IA antes da submissão final.
     */
    public function triarIa(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $relato = trim((string)($body['relato'] ?? ''));
            $modulo = !empty($body['modulo']) ? trim((string)$body['modulo']) : null;
            $filial = (int)($body['filial'] ?? 1);
            $tipoSistema = !empty($body['tipo_sistema']) ? trim((string)$body['tipo_sistema']) : null;

            if (mb_strlen($relato) < 4) {
                return $this->erro($response, 'Por favor, descreva o chamado com mais detalhes para a IA analisar.');
            }

            $resultado = $this->ia->triar($relato, $modulo, $filial, $tipoSistema);

            return $this->json($response, [
                'success' => true,
                'sugestao' => $resultado
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro na triagem de IA: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/criar
     * Cria um novo chamado no banco e registra a abertura na timeline.
     */
    public function criar(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $u = $this->usuario($request);

            $relato = trim((string)($body['motivo'] ?? $body['relato'] ?? ''));
            if (mb_strlen($relato) < 4) {
                return $this->erro($response, 'Informe a descrição do chamado.');
            }

            $modulo = trim((string)($body['modulo_slug'] ?? 'geral'));
            $filial = (int)($body['idfilial'] ?? 1);
            $tipoSistema = !empty($body['tipo_sistema']) ? trim((string)$body['tipo_sistema']) : null;
            $subtipoItem = !empty($body['subtipo_item']) ? trim((string)$body['subtipo_item']) : null;
            $equipamentoDetalhe = !empty($body['equipamento_detalhe']) ? trim((string)$body['equipamento_detalhe']) : null;

            // Se o usuário não fez a triagem antes, a IA tria automaticamente no ato do salvamento
            $triagem = !empty($body['titulo']) ? [
                'titulo' => trim((string)$body['titulo']),
                'tipo_sistema' => $tipoSistema ?: 'PORTAL',
                'subtipo_item' => $subtipoItem ?: 'Geral',
                'modulo_slug' => $modulo,
                'modulo_nome' => trim((string)($body['modulo_nome'] ?? $modulo)),
                'categoria' => trim((string)($body['categoria'] ?? 'bug')),
                'criticidade' => trim((string)($body['criticidade'] ?? 'media')),
                'diagnostico_ia' => trim((string)($body['diagnostico_ia'] ?? '')),
            ] : $this->ia->triar($relato, $modulo, $filial, $tipoSistema);

            $tipoSistemaFinal = !empty($triagem['tipo_sistema']) ? $triagem['tipo_sistema'] : ($tipoSistema ?: 'PORTAL');
            $subtipoFinal = !empty($triagem['subtipo_item']) ? $triagem['subtipo_item'] : ($subtipoItem ?: 'Geral');
            $equipDetalheFinal = $equipamentoDetalhe ?: ($tipoSistemaFinal === 'EQUIPAMENTO' ? $subtipoFinal : null);

            // Validação de Permissão por Tipo de Sistema
            if ($u['tipos_permitidos'] !== 'TODOS') {
                $tiposPermitidosArr = array_map('trim', explode(',', $u['tipos_permitidos']));
                if (!in_array($tipoSistemaFinal, $tiposPermitidosArr, true)) {
                    return $this->erro($response, "Você não possui permissão para abrir chamados do tipo '{$tipoSistemaFinal}'.");
                }
            }

            // Se for Portal, validar se tem acesso ao módulo escolhido
            if ($tipoSistemaFinal === 'PORTAL' && !$u['is_admin']) {
                $moduloEscolhido = $triagem['modulo_slug'] ?? $modulo;
                if (!empty($moduloEscolhido) && $moduloEscolhido !== 'geral') {
                    if (!in_array($moduloEscolhido, $u['permissoes'], true)) {
                        return $this->erro($response, "Você não possui permissão no módulo '{$moduloEscolhido}' do Portal.");
                    }
                }
            }

            // Responsável técnico padrão para solução dos chamados: 'ALAN'
            $atendentePadrao = 'ALAN';

            // Próximo ID de chamado
            $idNovo = (int)$this->pdo->query("SELECT COALESCE(MAX(idchamado), 0) + 1 FROM chamado")->fetchColumn();

            $stmt = $this->pdo->prepare("
                INSERT INTO chamado (
                    idchamado, idtransacao, idfilial, idempresa, idcliforemp,
                    status, prioridade, data, hora, datahora,
                    solicitante, atendente, usuario, motivo, observacao,
                    tipo_sistema, subtipo_item, equipamento_detalhe,
                    modulo_slug, modulo_nome, categoria, criticidade,
                    titulo, diagnostico_ia, anexo_url
                ) VALUES (
                    :idchamado, 3, :idfilial, 1, :idcliforemp,
                    0, 10, CURRENT_DATE, NOW(), NOW(),
                    :solicitante, :atendente, :usuario, :motivo, :observacao,
                    :tipo_sistema, :subtipo_item, :equipamento_detalhe,
                    :modulo_slug, :modulo_nome, :categoria, :criticidade,
                    :titulo, :diagnostico_ia, :anexo_url
                )
            ");

            $stmt->execute([
                'idchamado' => $idNovo,
                'idfilial' => $filial,
                'idcliforemp' => $u['idcliforemp'] ?: 5297,
                'solicitante' => $this->limpaWin1252($u['username']),
                'atendente' => $atendentePadrao,
                'usuario' => $this->limpaWin1252($u['username']),
                'motivo' => $this->limpaWin1252($relato),
                'observacao' => $this->limpaWin1252($relato),
                'tipo_sistema' => $this->limpaWin1252($tipoSistemaFinal),
                'subtipo_item' => $this->limpaWin1252($subtipoFinal),
                'equipamento_detalhe' => $equipDetalheFinal ? $this->limpaWin1252($equipDetalheFinal) : null,
                'modulo_slug' => $this->limpaWin1252($triagem['modulo_slug'] ?? 'geral'),
                'modulo_nome' => $this->limpaWin1252($triagem['modulo_nome'] ?? 'Geral'),
                'categoria' => $this->limpaWin1252($triagem['categoria']),
                'criticidade' => $this->limpaWin1252($triagem['criticidade']),
                'titulo' => $this->limpaWin1252($triagem['titulo']),
                'diagnostico_ia' => $this->limpaWin1252($triagem['diagnostico_ia']),
                'anexo_url' => $this->limpaWin1252(trim((string)($body['anexo_url'] ?? '')))
            ]);

            // Registra primeira operação na timeline
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:idchamado, 1, 'Chamado aberto no Portal e direcionado para TI (ALAN)', NOW(), :usuario, 1, 'abertura', :detalhes)
            ");
            $stmtOp->execute([
                'idchamado' => $idNovo,
                'usuario' => $u['username'],
                'detalhes' => "Chamado [{$tipoSistemaFinal}] classificado como [{$triagem['categoria']}] com criticidade [{$triagem['criticidade']}]. Atendente responsável: {$atendentePadrao}."
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => 'Chamado #' . $idNovo . ' registrado com sucesso!',
                'idchamado' => $idNovo
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao criar chamado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/assumir
     * O atendente/técnico assume a responsabilidade pelo chamado.
     */
    public function assumir(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $u = $this->usuario($request);

            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            if (!$u['pode_ver_todos']) {
                return $this->erro($response, 'Apenas membros da equipe técnica podem assumir chamados.', 403);
            }

            $stmtCham = $this->pdo->prepare("SELECT idchamado, status, atendente FROM chamado WHERE idchamado = :id");
            $stmtCham->execute(['id' => $id]);
            $chamado = $stmtCham->fetch(PDO::FETCH_ASSOC);

            if (!$chamado) {
                return $this->erro($response, 'Chamado não encontrado.', 404);
            }

            $novoStatus = (int)$chamado['status'];
            // Se estiver Aberto (0), move automaticamente para Em Análise (1)
            if ($novoStatus === 0) {
                $novoStatus = 1;
            }

            $stmtUp = $this->pdo->prepare("UPDATE chamado SET atendente = :at, status = :st, atualizado_em = NOW() WHERE idchamado = :id");
            $stmtUp->execute([
                'at' => $u['username'],
                'st' => $novoStatus,
                'id' => $id
            ]);

            // Grava operação na timeline
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, 'Chamado assumido para atendimento técnico', NOW(), :user, 2, 'atendimento', :det)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'user' => $u['username'],
                'det' => "O técnico {$u['username']} assumiu a responsabilidade pela condução deste chamado."
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => "Chamado #{$id} atribuído com sucesso para {$u['username']}!"
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao assumir chamado: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/atualizar-status
     * Move o chamado para outro estágio (Kanban).
     */
    public function atualizarStatus(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $novoStatus = (int)($body['status'] ?? 0);
            $u = $this->usuario($request);

            if ($id <= 0) {
                return $this->erro($response, 'ID de chamado inválido.');
            }

            $concluidoSql = $novoStatus === 3 ? ', concluido_em = NOW()' : '';

            $stmt = $this->pdo->prepare("UPDATE chamado SET status = :st, atualizado_em = NOW() {$concluidoSql} WHERE idchamado = :id");
            $stmt->execute(['st' => $novoStatus, 'id' => $id]);

            // Próximo idoperacao
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();

            $mapaStatus = [
                0 => 'Aberto',
                1 => 'Em Análise',
                2 => 'Em Correção',
                3 => 'Concluído',
                4 => 'Cancelado'
            ];
            $nomeStatus = $mapaStatus[$novoStatus] ?? 'Alteração de Status';

            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, :desc, NOW(), :user, 2, 'mudanca_status', :det)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'desc' => "Status alterado para: {$nomeStatus}",
                'user' => $u['username'],
                'det' => trim((string)($body['observacao'] ?? 'Movido pelo painel de chamados'))
            ]);

            return $this->json($response, [
                'success' => true,
                'message' => "Chamado #{$id} atualizado para '{$nomeStatus}'!"
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao atualizar status: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/adicionar-interacao
     * Insere comentário, hotfix ou resolução na timeline.
     */
    public function adicionarInteracao(Request $request, Response $response): Response
    {
        try {
            $body = $this->parsedBody($request);
            $id = (int)($body['idchamado'] ?? 0);
            $mensagem = trim((string)($body['mensagem'] ?? ''));
            $tipo = trim((string)($body['tipo'] ?? 'comentario'));
            $ehResolucao = !empty($body['eh_resolucao']);
            $versaoEntrega = trim((string)($body['versao_entrega'] ?? ''));
            $anexoUrl = trim((string)($body['anexo_url'] ?? ''));
            $u = $this->usuario($request);

            if ($id <= 0 || (mb_strlen($mensagem) < 2 && empty($anexoUrl))) {
                return $this->erro($response, 'Informe o chamado e o conteúdo da mensagem ou anexo.');
            }

            // Próximo idoperacao
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();

            $desc = $ehResolucao ? 'Resolução Técnica Aplicada' : (!empty($anexoUrl) && empty($mensagem) ? 'Anexo / Print adicionado' : 'Novo Comentário');

            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes, anexo_url)
                VALUES (:id, :op, :desc, NOW(), :user, 3, :tipo, :det, :anexo_url)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'desc' => $desc,
                'user' => $u['username'],
                'tipo' => $tipo,
                'det' => $this->limpaWin1252($mensagem),
                'anexo_url' => $this->limpaWin1252($anexoUrl)
            ]);

            // Se for resolução, atualiza chamado
            if ($ehResolucao) {
                $stmtUp = $this->pdo->prepare("
                    UPDATE chamado 
                    SET status = 3,
                        concluido_em = NOW(),
                        solucao_tecnica = :sol,
                        versao_entrega = :ver,
                        atualizado_em = NOW()
                    WHERE idchamado = :id
                ");
                $stmtUp->execute([
                    'sol' => $this->limpaWin1252($mensagem),
                    'ver' => $versaoEntrega ?: 'v3.5',
                    'id' => $id
                ]);
            }

            return $this->json($response, [
                'success' => true,
                'message' => $ehResolucao ? 'Chamado marcado como Concluído com sucesso!' : 'Interação registrada com sucesso!'
            ]);
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro ao registrar interação: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /v1/chamados/upload-anexo
     * Upload de imagens, prints (clipboard/base64) ou arquivos anexos.
     */
    public function uploadAnexo(Request $request, Response $response): Response
    {
        try {
            $u = $this->usuario($request);
            $uploadDir = __DIR__ . '/../../../portal/uploads/chamados';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }

            $extensoesPermitidas = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'txt', 'log'];
            $maxBytes = 15 * 1024 * 1024; // 15MB

            $body = $this->parsedBody($request);

            // 1. Caso base64 (Print Screen / Ctrl+V)
            if (!empty($body['imagem_base64'])) {
                $rawBase64 = (string)$body['imagem_base64'];
                $ext = 'png';
                if (preg_match('/^data:image\/(\w+);base64,/', $rawBase64, $m)) {
                    $ext = strtolower($m[1]) === 'jpeg' ? 'jpg' : strtolower($m[1]);
                    $rawBase64 = substr($rawBase64, strpos($rawBase64, ',') + 1);
                }
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    $ext = 'png';
                }

                $data = base64_decode($rawBase64);
                if ($data === false || strlen($data) === 0) {
                    return $this->erro($response, 'Dados de imagem base64 inválidos.');
                }
                if (strlen($data) > $maxBytes) {
                    return $this->erro($response, 'Arquivo excede o limite de 15MB.');
                }

                $nomeArquivo = 'print_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $caminhoFisico = $uploadDir . '/' . $nomeArquivo;

                if (file_put_contents($caminhoFisico, $data) === false) {
                    return $this->erro($response, 'Falha ao salvar print no servidor.', 500);
                }

                $appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
                $urlRelativa = $appBase . '/portal/uploads/chamados/' . $nomeArquivo;

                return $this->json($response, [
                    'success' => true,
                    'url' => $urlRelativa,
                    'nome' => $nomeArquivo,
                    'tamanho' => strlen($data),
                    'tipo' => 'imagem'
                ]);
            }

            // 2. Caso Multipart / Upload tradicional ($_FILES ou PSR-7)
            $files = $request->getUploadedFiles();
            $uploadedFile = $files['anexo'] ?? $files['file'] ?? null;

            if (!$uploadedFile && !empty($_FILES['anexo'])) {
                $tmp = $_FILES['anexo']['tmp_name'];
                $origName = $_FILES['anexo']['name'];
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    return $this->erro($response, 'Extensão de arquivo não permitida.');
                }
                if ($_FILES['anexo']['size'] > $maxBytes) {
                    return $this->erro($response, 'Arquivo excede o limite de 15MB.');
                }
                $nomeArquivo = 'anexo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $nomeArquivo;
                if (!move_uploaded_file($tmp, $dest)) {
                    return $this->erro($response, 'Erro ao mover anexo.', 500);
                }
                $appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
                return $this->json($response, [
                    'success' => true,
                    'url' => $appBase . '/portal/uploads/chamados/' . $nomeArquivo,
                    'nome' => $origName,
                    'tipo' => in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']) ? 'imagem' : 'documento'
                ]);
            }

            if ($uploadedFile && $uploadedFile->getError() === UPLOAD_ERR_OK) {
                $origName = $uploadedFile->getClientFilename();
                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
                if (!in_array($ext, $extensoesPermitidas, true)) {
                    return $this->erro($response, 'Extensão de arquivo não permitida.');
                }
                if ($uploadedFile->getSize() > $maxBytes) {
                    return $this->erro($response, 'Arquivo excede o limite de 15MB.');
                }
                $nomeArquivo = 'anexo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . '/' . $nomeArquivo;
                $uploadedFile->moveTo($dest);

                $appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
                return $this->json($response, [
                    'success' => true,
                    'url' => $appBase . '/portal/uploads/chamados/' . $nomeArquivo,
                    'nome' => $origName,
                    'tipo' => in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp']) ? 'imagem' : 'documento'
                ]);
            }

            return $this->erro($response, 'Nenhum anexo ou print foi enviado.');
        } catch (\Throwable $e) {
            return $this->erro($response, 'Erro no upload: ' . $e->getMessage(), 500);
        }
    }
}
