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

        return [
            'idusuario' => $idusuario,
            'idcliforemp' => $idcliforemp,
            'username' => $username,
            'is_admin' => $isAdmin,
            'permissoes' => (array)($user['permissoes'] ?? [])
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

            // KPIs
            $kpiTotal = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado")->fetchColumn();
            $kpiAbertos = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1)")->fetchColumn();
            $kpiEmCorrecao = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status = 2")->fetchColumn();
            $kpiCriticos = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status IN (0, 1, 2) AND criticidade = 'critica'")->fetchColumn();
            $kpiConcluidosMes = (int)$this->pdo->query("SELECT COUNT(*) FROM chamado WHERE status = 3 AND (concluido_em >= DATE_TRUNC('month', CURRENT_DATE) OR data >= DATE_TRUNC('month', CURRENT_DATE))")->fetchColumn();

            // Módulos para os selects
            $modulos = $this->pdo->query("SELECT slug, nome, setor, icon FROM sistema_modulos WHERE ativo = true ORDER BY setor, nome")->fetchAll(PDO::FETCH_ASSOC);

            return $this->json($response, [
                'success' => true,
                'kpis' => [
                    'total' => $kpiTotal,
                    'abertos' => $kpiAbertos,
                    'em_correcao' => $kpiEmCorrecao,
                    'criticos' => $kpiCriticos,
                    'concluidos_mes' => $kpiConcluidosMes,
                ],
                'modulos' => $modulos,
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
            $busca = trim((string)($q['busca'] ?? ''));

            $where = ['1=1'];
            $params = [];

            if ($status !== null) {
                $where[] = 'c.status = :status';
                $params['status'] = $status;
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
            if ($busca !== '') {
                $where[] = '(c.titulo ILIKE :busca OR c.motivo ILIKE :busca OR c.solicitante ILIKE :busca OR c.observacao ILIKE :busca OR CAST(c.idchamado AS TEXT) ILIKE :busca)';
                $params['busca'] = '%' . $busca . '%';
            }

            $sql = "
                SELECT 
                    c.idchamado,
                    COALESCE(c.titulo, c.motivo, 'Sem título') AS titulo,
                    c.motivo,
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

            // Timeline de operacoes
            $stmtOp = $this->pdo->prepare("
                SELECT 
                    idoperacao,
                    descricao,
                    datahora,
                    usuario,
                    acao,
                    COALESCE(tipo, 'comentario') AS tipo,
                    detalhes
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

            if (mb_strlen($relato) < 4) {
                return $this->erro($response, 'Por favor, descreva o chamado com mais detalhes para a IA analisar.');
            }

            $resultado = $this->ia->triar($relato, $modulo, $filial);

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

            // Se o usuário não fez a triagem antes, a IA tria automaticamente no ato do salvamento
            $triagem = !empty($body['titulo']) ? [
                'titulo' => trim((string)$body['titulo']),
                'modulo_slug' => $modulo,
                'modulo_nome' => trim((string)($body['modulo_nome'] ?? $modulo)),
                'categoria' => trim((string)($body['categoria'] ?? 'bug')),
                'criticidade' => trim((string)($body['criticidade'] ?? 'media')),
                'diagnostico_ia' => trim((string)($body['diagnostico_ia'] ?? '')),
            ] : $this->ia->triar($relato, $modulo, $filial);

            // Próximo ID de chamado
            $idNovo = (int)$this->pdo->query("SELECT COALESCE(MAX(idchamado), 0) + 1 FROM chamado")->fetchColumn();

            $stmt = $this->pdo->prepare("
                INSERT INTO chamado (
                    idchamado, idtransacao, idfilial, idempresa, idcliforemp,
                    status, prioridade, data, hora, datahora,
                    solicitante, atendente, usuario, motivo, observacao,
                    modulo_slug, modulo_nome, categoria, criticidade,
                    titulo, diagnostico_ia, anexo_url
                ) VALUES (
                    :idchamado, 3, :idfilial, 1, :idcliforemp,
                    0, 10, CURRENT_DATE, NOW(), NOW(),
                    :solicitante, 'DEVAASA', :usuario, :motivo, :observacao,
                    :modulo_slug, :modulo_nome, :categoria, :criticidade,
                    :titulo, :diagnostico_ia, :anexo_url
                )
            ");

            $stmt->execute([
                'idchamado' => $idNovo,
                'idfilial' => $filial,
                'idcliforemp' => $u['idcliforemp'] ?: 5297,
                'solicitante' => $this->limpaWin1252($u['username']),
                'usuario' => $this->limpaWin1252($u['username']),
                'motivo' => $this->limpaWin1252($relato),
                'observacao' => $this->limpaWin1252($relato),
                'modulo_slug' => $this->limpaWin1252($triagem['modulo_slug']),
                'modulo_nome' => $this->limpaWin1252($triagem['modulo_nome']),
                'categoria' => $this->limpaWin1252($triagem['categoria']),
                'criticidade' => $this->limpaWin1252($triagem['criticidade']),
                'titulo' => $this->limpaWin1252($triagem['titulo']),
                'diagnostico_ia' => $this->limpaWin1252($triagem['diagnostico_ia']),
                'anexo_url' => $this->limpaWin1252(trim((string)($body['anexo_url'] ?? '')))
            ]);

            // Registra primeira operação na timeline
            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:idchamado, 1, 'Chamado aberto no Portal com Triagem de IA', NOW(), :usuario, 1, 'abertura', :detalhes)
            ");
            $stmtOp->execute([
                'idchamado' => $idNovo,
                'usuario' => $u['username'],
                'detalhes' => "Chamado classificado como [{$triagem['categoria']}] com criticidade [{$triagem['criticidade']}]."
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
            $u = $this->usuario($request);

            if ($id <= 0 || mb_strlen($mensagem) < 2) {
                return $this->erro($response, 'Informe o chamado e o conteúdo da mensagem.');
            }

            // Próximo idoperacao
            $nextOp = (int)$this->pdo->query("SELECT COALESCE(MAX(idoperacao), 0) + 1 FROM chamado_operacao WHERE idchamado = {$id}")->fetchColumn();

            $desc = $ehResolucao ? 'Resolução Técnica Aplicada' : 'Novo Comentário';

            $stmtOp = $this->pdo->prepare("
                INSERT INTO chamado_operacao (idchamado, idoperacao, descricao, datahora, usuario, acao, tipo, detalhes)
                VALUES (:id, :op, :desc, NOW(), :user, 3, :tipo, :det)
            ");
            $stmtOp->execute([
                'id' => $id,
                'op' => $nextOp,
                'desc' => $desc,
                'user' => $u['username'],
                'tipo' => $tipo,
                'det' => $mensagem
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
                    'sol' => $mensagem,
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
}
