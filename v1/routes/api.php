<?php

use Nutricional\Middleware\JwtMiddleware;
use Nutricional\Middleware\BlacklistMiddleware;
use Nutricional\Middleware\RateLimitMiddleware;
use Nutricional\Middleware\LoggingMiddleware;
use Nutricional\Middleware\CsrfMiddleware;
use Nutricional\Controllers\SeparacaoController;
use Nutricional\Controllers\CarregamentoController;
use Nutricional\Controllers\MonitorController;
use Nutricional\Controllers\XmlController;
use Nutricional\Controllers\AuthController;
use Nutricional\Controllers\FinanceiroController;
use Nutricional\Controllers\MarketingController;
use Nutricional\Controllers\MarketingAdminController;
use Nutricional\Controllers\CronController;
use Nutricional\Controllers\AuditoriaController;
use Nutricional\Controllers\AdminController;
use Nutricional\Controllers\ConsultaController;
use Nutricional\Controllers\ChatController;
use Nutricional\Controllers\DesembarqueController;
use Nutricional\Controllers\DepositoController;
use Nutricional\Controllers\VendasCuboController;
use Nutricional\Controllers\VendasDashboardController;
use Nutricional\Controllers\EstoquePrevisaoController;
use Nutricional\Controllers\MetaBuilderController;
use Nutricional\Controllers\AnaliseCarteiraController;
use Nutricional\Controllers\InventarioController;
use Nutricional\Controllers\ClientePedidosController;
use Nutricional\Controllers\ReceitaController;

// ============================================================================
// 🚛 NOVOS CONTROLLERS - FROTA E ENTREGAS
// ============================================================================
use Nutricional\Controllers\Frota\EmbarqueController;
use Nutricional\Controllers\Frota\EntregaController;
use Nutricional\Controllers\Frota\MotoristaController;
use Nutricional\Controllers\Frota\RastreamentoController;
use Nutricional\Controllers\Frota\DashboardController;
use Nutricional\Controllers\Frota\ImportadorController;
use Nutricional\Controllers\Frota\VeiculoController;
use Nutricional\Controllers\Frota\GeocodificacaoController;
use Nutricional\Controllers\Frota\AcertoEmbarqueController; 

// Carregar configurações
$logger = require __DIR__ . '/../config/logger.php';

// ==========================================================================
// MIDDLEWARES GLOBAIS (executados em TODAS as rotas)
// ==========================================================================

// 1. Logging - registra todas as requisições
$app->add(new LoggingMiddleware($logger['app']));

// 2. Rate Limiting - protege contra ataques de força bruta
$app->add(new RateLimitMiddleware(300, 60));

// ==========================================================================
// ROTAS PÚBLICAS (SEM AUTENTICAÇÃO)
// ==========================================================================

// CORS para login
$app->options('/v1/auth/login', function ($request, $response) {
    return $response->withStatus(200);
});

// Login público
$app->post('/v1/auth/login', [new AuthController(), 'login']);

// Health check público
$app->get('/ping', function ($request, $response) {
    $payload = json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
    $response->getBody()->write($payload);
    return $response->withHeader('Content-Type', 'application/json');
});

// Módulos e setores (público para carregar menu antes do login)
$app->get('/v1/sistema/modulos-setores', function ($request, $response) {
    $pdo = \getPDO();
    $setores = $pdo->query("SELECT id, nome, slug, icone, cor_bg as corBg, cor_texto as corTexto, descricao FROM sistema_setores WHERE ativo = true ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);
    $modulos = $pdo->query("SELECT id, slug, nome, descricao as desc, icon, cor_bg as corBg, cor_text as corTexto, url, setor FROM sistema_modulos WHERE ativo = true ORDER BY ordem")->fetchAll(PDO::FETCH_ASSOC);

    $response->getBody()->write(json_encode(['setores' => $setores, 'modulos' => $modulos]));
    return $response->withHeader('Content-Type', 'application/json');
});

// ==========================================================================
// ROTAS PROTEGIDAS (COM JWT + BLACKLIST)
// ==========================================================================

$app->group('/v1', function ($group) {

    // ==========================================================================
    // ROTAS PÚBLICAS DENTRO DO GRUPO /V1
    // ==========================================================================
    $group->get('/ping', function ($request, $response) {
        $payload = json_encode(['status' => 'ok', 'time' => date('Y-m-d H:i:s')]);
        $response->getBody()->write($payload);
        return $response->withHeader('Content-Type', 'application/json');
    });

    // ==========================================================================
    // GRUPO PROTEGIDO (TODAS AS ROTAS AQUI DENTRO EXIGEM AUTENTICAÇÃO)
    // ==========================================================================
    $group->group('', function ($protected) {

        // ======================================================================
        // AUTENTICAÇÃO E PERFIL
        // ======================================================================
        $protected->post('/auth/logout', [new AuthController(), 'logout']);
        $protected->post('/auth/alterar-senha', [new AuthController(), 'alterarSenha']);
        $protected->get('/perfil/dados', [new AuthController(), 'getDadosPerfil']);

  // ======================================================================
        // 🚛 FROTA - GESTÃO DE EMBARQUES, ENTREGAS E RASTREAMENTO
        // ======================================================================
        $protected->group('/frota', function ($frota) {

            // ==================================================================
            // 1. DASHBOARD DA FROTA
            // ==================================================================
            $frota->group('/dashboard', function ($dash) {
                $controller = new DashboardController();
                $dash->get('/operacional', [$controller, 'operacional']);
                $dash->get('/motorista/{id}', [$controller, 'motorista']);
                $dash->get('/embarque/{id}', [$controller, 'embarque']);
                $dash->get('/entregas/hoje', [$controller, 'entregasHoje']);
                $dash->get('/graficos', [$controller, 'graficos']);
                $dash->get('/kpis', [$controller, 'kpis']);
                $dash->get('/mapa', [$controller, 'mapa']);
                $dash->get('/alertas', [$controller, 'alertas']);
            });

            // ==================================================================
            // 2. EMBARQUES
            // ==================================================================
            $frota->group('/embarques', function ($embarque) {
                $controller = new EmbarqueController();
                $importador = new ImportadorController();

                // CRUD Básico
                $embarque->get('', [$controller, 'listar']);
                $embarque->get('/{id}', [$controller, 'buscar']);
                $embarque->post('', [$controller, 'criar']);
                $embarque->put('/{id}', [$controller, 'atualizar']);
                $embarque->delete('/{id}', [$controller, 'deletar']);

                // Ações do Embarque
                $embarque->post('/{id}/iniciar', [$controller, 'iniciar']);
                $embarque->post('/{id}/finalizar', [$controller, 'finalizar']);
                $embarque->post('/{id}/cancelar', [$controller, 'cancelar']);
                $embarque->post('/{id}/reordenar', [$importador, 'reordenarEntregas']);
                $embarque->delete('/{embarqueId}/entregas/{entregaId}', [$controller, 'removerEntrega']);
                $embarque->post('/{id}/adicionar-embarque-erp', [$controller, 'adicionarEmbarqueERP']);
                $embarque->post('/{id}/adicionar-pedidos', [$controller, 'adicionarPedidos']);

                // Rotas e Otimização
                $embarque->post('/{id}/otimizar-rota', [$controller, 'otimizarRota']);
                $embarque->get('/{id}/rota', [$controller, 'rota']);
                $embarque->get('/{id}/entregas', [$controller, 'entregas']);
                $embarque->get('/{id}/motorista', [$controller, 'motorista']);
                $embarque->get('/{id}/veiculo', [$controller, 'veiculo']);

                // Histórico e Relatórios
                $embarque->get('/{id}/historico', [$controller, 'historico']);
                $embarque->get('/{id}/relatorio', [$controller, 'relatorio']);
                $embarque->post('/{id}/exportar-pdf', [$controller, 'exportarPDF']);
            });

            // ==================================================================
            // 3. ENTREGAS
            // ==================================================================
            $frota->group('/entregas', function ($entrega) {
                $controller = new EntregaController();

                // CRUD Básico
                $entrega->get('', [$controller, 'listar']);
                $entrega->get('/{id}', [$controller, 'buscar']);
                $entrega->post('', [$controller, 'criar']);
                $entrega->put('/{id}', [$controller, 'atualizar']);
                $entrega->delete('/{id}', [$controller, 'deletar']);

                // Rastreamento
                $entrega->get('/rastreamento/{codigo}', [$controller, 'buscarPorRastreamento']);
                $entrega->get('/{id}/rastreamento', [$controller, 'rastreamento']);

                // Ações da Entrega
                $entrega->post('/{id}/checkin', [$controller, 'checkin']);
                $entrega->post('/{id}/checkout', [$controller, 'checkout']);
                $entrega->post('/{id}/falha', [$controller, 'falha']);
                $entrega->put('/{id}/corrigir-endereco', [$controller, 'corrigirEndereco']);
                $entrega->post('/{id}/reagendar', [$controller, 'reagendar']);
                $entrega->post('/{id}/tentar-novamente', [$controller, 'tentarNovamente']);

                // Fotos e Comprovantes
                $entrega->post('/{id}/foto', [$controller, 'uploadFoto']);
                $entrega->post('/{id}/assinatura', [$controller, 'uploadAssinatura']);
                $entrega->get('/{id}/fotos', [$controller, 'getFotos']);
                $entrega->get('/{id}/comprovante', [$controller, 'getComprovante']);

                // Histórico
                $entrega->get('/{id}/historico', [$controller, 'historico']);
                $entrega->get('/{id}/ocorrencias', [$controller, 'ocorrencias']);
            });

            // ==================================================================
            // 4. MOTORISTAS
            // ==================================================================
            $frota->group('/motoristas', function ($motorista) {
                $controller = new MotoristaController();

                // CRUD Básico
                $motorista->get('', [$controller, 'listar']);
                $motorista->get('/{id}', [$controller, 'buscar']);
                $motorista->post('', [$controller, 'criar']);
                $motorista->put('/{id}', [$controller, 'atualizar']);
                $motorista->delete('/{id}', [$controller, 'deletar']);

                // Rotas e Entregas
                $motorista->get('/{id}/entregas', [$controller, 'entregas']);
                $motorista->get('/{id}/entregas/hoje', [$controller, 'entregasHoje']);
                $motorista->get('/{id}/rota-ativa', [$controller, 'rotaAtiva']);
                $motorista->post('/{id}/rota/iniciar', [$controller, 'iniciarRota']);
                $motorista->post('/{id}/rota/finalizar', [$controller, 'finalizarRota']);

                // Estatísticas
                $motorista->get('/{id}/estatisticas', [$controller, 'estatisticas']);
                $motorista->get('/{id}/ranking', [$controller, 'ranking']);
                $motorista->get('/{id}/performance', [$controller, 'performance']);

                // Posição e Rastreamento
                $motorista->post('/{id}/posicao', [$controller, 'atualizarPosicao']);
                $motorista->get('/{id}/posicao', [$controller, 'getPosicao']);
                $motorista->get('/{id}/historico-posicao', [$controller, 'historicoPosicao']);

                // Ocorrências e Notificações
                $motorista->post('/{id}/ocorrencia', [$controller, 'registrarOcorrencia']);
                $motorista->post('/{id}/notificacao', [$controller, 'enviarNotificacao']);
                $motorista->get('/{id}/notificacoes', [$controller, 'getNotificacoes']);
                $motorista->put('/{id}/notificacoes/{notif_id}/ler', [$controller, 'marcarNotificacaoLida']);

                // Jornada de Trabalho
                $motorista->post('/{id}/jornada/iniciar', [$controller, 'iniciarJornada']);
                $motorista->post('/{id}/jornada/finalizar', [$controller, 'finalizarJornada']);
                $motorista->get('/{id}/jornada/historico', [$controller, 'historicoJornada']);
            });

            // ==================================================================
            // 5. RASTREAMENTO EM TEMPO REAL
            // ==================================================================
            $frota->group('/rastreamento', function ($rastreamento) {
                $controller = new RastreamentoController();

                // Posição dos Veículos
                $rastreamento->get('/veiculo/{id}', [$controller, 'veiculo']);
                $rastreamento->get('/veiculo/{id}/historico', [$controller, 'historicoVeiculo']);
                $rastreamento->get('/veiculo/{id}/rota', [$controller, 'rotaVeiculo']);

                // Rastreamento de Embarques
                $rastreamento->get('/embarque/{id}', [$controller, 'embarque']);
                $rastreamento->get('/embarque/{id}/entregas', [$controller, 'entregasEmbarque']);

                // Rastreamento de Entregas
                $rastreamento->get('/entrega/{id}', [$controller, 'entrega']);
                $rastreamento->get('/entrega/{id}/historico', [$controller, 'historicoEntrega']);

                // Posição em Tempo Real
                $rastreamento->post('/posicao', [$controller, 'atualizarPosicao']);
                $rastreamento->get('/posicao/ultimas', [$controller, 'ultimasPosicoes']);

                // WebSocket
                $rastreamento->get('/websocket/token', [$controller, 'gerarTokenWebSocket']);
                $rastreamento->get('/websocket/status', [$controller, 'statusWebSocket']);

                // Alertas
                $rastreamento->get('/alertas', [$controller, 'getAlertas']);
                $rastreamento->post('/alertas/{id}/resolver', [$controller, 'resolverAlerta']);
            });

            // ==================================================================
            // 6. IMPORTAÇÃO DO ERP
            // ==================================================================
            $frota->group('/importar', function ($importar) {
                $controller = new ImportadorController();
                $importar->get('/embarques-erp', [$controller, 'getEmbarquesERP']);
                $importar->get('/embarque-detalhes/{id}', [$controller, 'getEmbarqueDetalhes']);
                $importar->post('/criar-embarque', [$controller, 'criarEmbarqueDoERP']);
                $importar->get('/buscar-pedidos', [$controller, 'buscarPedidos']);
                $importar->post('/embarques', [$controller, 'importarEmbarques']);
                $importar->post('/entregas', [$controller, 'importarEntregas']);
                $importar->post('/veiculos', [$controller, 'importarVeiculos']);
                $importar->post('/motoristas', [$controller, 'importarMotoristas']);
                $importar->post('/clientes', [$controller, 'importarClientes']);
                $importar->post('/itens-pedidos', [$controller, 'getItensPedidos']);
                $importar->post('/tudo', [$controller, 'importarTudo']);
                $importar->get('/status', [$controller, 'status']);
                $importar->post('/geocodificar', [$controller, 'geocodificar']);
            });

            // ==================================================================
            // 7. VEÍCULOS (GESTÃO DA FROTA)
            // ==================================================================
            $frota->group('/veiculos', function ($veiculo) {
                $controller = new VeiculoController();

                // ROTAS ESTÁTICAS (sem parâmetros) - SEMPRE PRIMEIRO!
                $veiculo->get('/disponiveis', [$controller, 'disponiveis']);

                // CRUD Básico
                $veiculo->get('', [$controller, 'listar']);
                $veiculo->post('', [$controller, 'criar']);

                // ROTAS DINÂMICAS (com parâmetros) - DEPOIS das estáticas
                $veiculo->get('/{id}', [$controller, 'buscar']);
                $veiculo->put('/{id}', [$controller, 'atualizar']);
                $veiculo->delete('/{id}', [$controller, 'deletar']);

                // Status e Disponibilidade
                $veiculo->post('/{id}/status', [$controller, 'atualizarStatus']);
                $veiculo->get('/{id}/historico-status', [$controller, 'historicoStatus']);

                // Manutenção
                $veiculo->get('/{id}/manutencoes', [$controller, 'manutencoes']);
                $veiculo->post('/{id}/manutencao', [$controller, 'registrarManutencao']);
                $veiculo->get('/{id}/proxima-manutencao', [$controller, 'proximaManutencao']);

                // Estatísticas
                $veiculo->get('/{id}/estatisticas', [$controller, 'estatisticas']);
                $veiculo->get('/{id}/relatorio', [$controller, 'relatorio']);
            });

            // ==================================================================
            // 8. GEOLOCALIZAÇÃO
            // ==================================================================
            $frota->group('/geocodificar', function ($geocodificacao) {
                $controller = new GeocodificacaoController();
                $geocodificacao->post('', [$controller, 'geocodificar']);
            });

            // ==================================================================
            // 9. GESTÃO DE CARGAS - ANÁLISE DE PROBLEMAS
            // ==================================================================
            $frota->group('/gestao-cargas', function ($cargas) {
                $controller = new DashboardController();

                // KPIS DE PROBLEMAS
                $cargas->get('/kpis-problemas', [$controller, 'kpisProblemas']);

                // LISTA DE PROBLEMAS COM FILTROS
                $cargas->get('/problemas', [$controller, 'problemas']);

                // ANÁLISE COMPLETA DE UMA ENTREGA
                $cargas->get('/entregas/{id}/analise', [$controller, 'analiseEntrega']);

                // RESOLVER PROBLEMA
                $cargas->put('/problemas/{id}/resolver', [$controller, 'resolverProblema']);

                // INICIAR ANÁLISE
                $cargas->put('/problemas/{id}/iniciar-analise', [$controller, 'iniciarAnalise']);

                // ADICIONAR ANÁLISE DO GESTOR
                $cargas->post('/entregas/{id}/analise', [$controller, 'adicionarAnalise']);

                // ESTATÍSTICAS RÁPIDAS
                $cargas->get('/estatisticas', function ($request, $response) use ($controller) {
                    return $controller->kpisProblemas($request, $response);
                });

                // RESUMO DE PROBLEMAS POR MOTORISTA
                $cargas->get('/resumo-motorista', [$controller, 'resumoMotorista']);

                // RESUMO DE PROBLEMAS POR VEÍCULO
                $cargas->get('/resumo-veiculo', [$controller, 'resumoVeiculo']);

                // EXPORTAR RELATÓRIO DE PROBLEMAS
                $cargas->post('/exportar', [$controller, 'exportarProblemas']);
            });

            // ==================================================================
            // 10. 🆕 ACERTO DE EMBARQUE - GESTÃO ADMINISTRATIVA
            // ==================================================================
            $frota->group('/acerto', function ($acerto) {
                $controller = new AcertoEmbarqueController();

                // ============================================================
                // EMBARQUES PARA ACERTO
                // ============================================================
                // Listar embarques prontos para acerto
                $acerto->get('/embarques', [$controller, 'listarParaAcerto']);

                // Detalhes completos do embarque para acerto (timeline, fotos, checklist)
                $acerto->get('/{embarqueId}/detalhes', [$controller, 'getDetalhesAcerto']);

                // ============================================================
                // GERENCIAMENTO DO ACERTO
                // ============================================================
                // Iniciar acerto
                $acerto->post('/iniciar', [$controller, 'iniciarAcerto']);

                // Finalizar acerto
                $acerto->post('/{id}/finalizar', [$controller, 'finalizarAcerto']);

                // Cancelar acerto
                $acerto->post('/{id}/cancelar', [$controller, 'cancelarAcerto']);

                // ============================================================
                // PEDIDOS DE PROBLEMA (FALTANTE/DEVOLUÇÃO)
                // ============================================================
                // Criar pedido de problema
                $acerto->post('/pedido-problema', [$controller, 'criarPedidoProblema']);

                // Listar pedidos de acerto
                $acerto->get('/pedidos', [$controller, 'listarPedidosAcerto']);

                // Detalhes de um pedido de acerto
                $acerto->get('/pedido/{id}', [$controller, 'getPedidoAcerto']);

                // Remover pedido de acerto
                $acerto->delete('/pedido/{id}', [$controller, 'removerPedidoAcerto']);

                // ============================================================
                // INTEGRAÇÃO COM ERP
                // ============================================================
                // Criar pedido no ERP a partir do pedido de acerto
                $acerto->post('/pedido/{id}/criar-erp', [$controller, 'criarPedidoERP']);

                // Listar transações disponíveis para criação de pedidos
                $acerto->get('/transacoes', [$controller, 'listarTransacoes']);

                // Listar itens disponíveis para adicionar ao pedido
                $acerto->get('/itens/buscar', [$controller, 'buscarItens']);

                // ============================================================
                // RELATÓRIOS E EXPORTAÇÃO
                // ============================================================
                // Exportar relatório do acerto
                $acerto->post('/{id}/exportar', [$controller, 'exportarRelatorio']);

                // Resumo dos acertos para dashboard
                $acerto->get('/resumo', [$controller, 'getResumoAcertos']);

                // ============================================================
                // TESTE (SANDBOX)
                // ============================================================
                $acerto->post('/testar', [$controller, 'testarCriacao']);
            });

            // ==================================================================
            // 11. RESUMO DO DASHBOARD (UNIFICADO)
            // ==================================================================
            $frota->get('/dashboard/resumo-completo', function ($request, $response) {
                $controller = new DashboardController();

                // Buscar KPIs gerais
                $kpisResponse = $controller->kpis($request, $response);
                $kpis = json_decode($kpisResponse->getBody(), true);

                // Buscar KPIs de problemas
                $problemasResponse = $controller->kpisProblemas($request, $response);
                $problemas = json_decode($problemasResponse->getBody(), true);

                // Buscar alertas
                $alertasResponse = $controller->alertas($request, $response);
                $alertas = json_decode($alertasResponse->getBody(), true);

                // Buscar entregas de hoje
                $entregasResponse = $controller->entregasHoje($request, $response);
                $entregas = json_decode($entregasResponse->getBody(), true);

                // Buscar resumo de acertos
                $acertoController = new AcertoEmbarqueController();
                $acertoResponse = $acertoController->getResumoAcertos($request, $response);
                $acerto = json_decode($acertoResponse->getBody(), true);

                $payload = json_encode([
                    'success' => true,
                    'data' => [
                        'kpis' => $kpis['data'] ?? [],
                        'problemas' => $problemas['data'] ?? [],
                        'alertas' => $alertas['data'] ?? [],
                        'entregas_hoje' => $entregas['data'] ?? [],
                        'acertos' => $acerto['data'] ?? []
                    ],
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE);

                $response->getBody()->write($payload);
                return $response
                    ->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            });

            // ==================================================================
            // 12. RESUMO DE PROBLEMAS POR PERÍODO
            // ==================================================================
            $frota->get('/dashboard/problemas-periodo', function ($request, $response) {
                $controller = new DashboardController();
                
                $params = $request->getQueryParams();
                $dias = (int)($params['dias'] ?? 30);
                
                $pdo = \getPDO();
                $stmt = $pdo->prepare("
                    SELECT 
                        DATE(created_at) as data,
                        COUNT(*) as total,
                        COUNT(CASE WHEN status_problema = 'pendente' THEN 1 END) as pendentes,
                        COUNT(CASE WHEN status_problema = 'resolvido' THEN 1 END) as resolvidos,
                        COUNT(CASE WHEN tipo_problema = 'faltante' THEN 1 END) as faltantes,
                        COUNT(CASE WHEN tipo_problema = 'devolucao' THEN 1 END) as devolucoes
                    FROM frota_entrega_problema
                    WHERE created_at >= CURRENT_DATE - INTERVAL :dias DAY
                    GROUP BY DATE(created_at)
                    ORDER BY data ASC
                ");
                $stmt->execute(['dias' => $dias]);
                $dados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $payload = json_encode([
                    'success' => true,
                    'data' => $dados,
                    'timestamp' => date('Y-m-d H:i:s')
                ], JSON_UNESCAPED_UNICODE);

                $response->getBody()->write($payload);
                return $response
                    ->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
            });

        }); // Fim do grupo /frota

        // ======================================================================
        // LOGÍSTICA - SEPARAÇÃO
        // ======================================================================
$protected->group('/separacao', function ($group) {
    $controller = new SeparacaoController();
    $group->get('/embarques-pendentes', [$controller, 'getEmbarquesPendentes']);
    $group->get('/itens/{idembarque}', [$controller, 'getItens']);
    $group->post('/confirmar', [$controller, 'confirmarItem']);
    $group->delete('/estornar/{iditem}/{idembarque}', [$controller, 'estornarItem']);
    $group->get('/resumo/{idembarque}', [$controller, 'getResumo']);
    $group->post('/finalizar/{idembarque}', [$controller, 'finalizarSeparacao']);
});

        // ======================================================================
        // LOGÍSTICA - CARREGAMENTO
        // ======================================================================
$protected->group('/carregamento', function ($group) {
    $controller = new CarregamentoController();
    $group->get('/embarques', [$controller, 'getEmbarques']);
    $group->get('/itens/{idembarque}', [$controller, 'getItens']);
    $group->post('/confirmar', [$controller, 'confirmarItem']);
    $group->delete('/estornar/{iditem}/{idembarque}', [$controller, 'estornarItem']);
    $group->post('/finalizar/{idembarque}', [$controller, 'finalizarEmbarque']);
    $group->post('/foto', [$controller, 'uploadFoto']);
    $group->get('/fotos/{idembarque}', [$controller, 'getFotos']);
    $group->get('/foto/{idfoto}', [$controller, 'getFoto']);
    $group->get('/resumo/{idembarque}', [$controller, 'getResumo']);
});

        // ======================================================================
        // LOGÍSTICA - MONITORAMENTO
        // ======================================================================
$protected->group('/monitor', function ($group) {
    $controller = new MonitorController();
    $group->get('/embarques', [$controller, 'getMonitoramento']);
    $group->get('/detalhes/{idembarque}', [$controller, 'getDetalhes']);
    $group->get('/historico', [$controller, 'getHistorico']);
});

        // ======================================================================
        // LOGÍSTICA - CONFERÊNCIA XML
        // ======================================================================
$protected->group('/xml', function ($group) {
    $controller = new XmlController();
    $group->get('/filiais', [$controller, 'getFiliais']);
    $group->get('/fornecedores/{idfilial}', [$controller, 'getFornecedores']);
    $group->get('/ordens-compra', [$controller, 'getOrdensCompra']);
    $group->get('/consulta-oc/{idoc}', [$controller, 'consultaOC']);
    $group->get('/buscar-notas', [$controller, 'buscarNotas']);
    $group->get('/itens-xml', [$controller, 'getItensXml']);
    $group->post('/deletar-item', [$controller, 'deletarItem']);
    $group->post('/buscar-notas-multiplas', [$controller, 'buscarNotasMultiplas']);
    $group->post('/atualizar-conferencia', [$controller, 'atualizarConferencia']);
    $group->post('/enviar-email', [$controller, 'enviarEmailDivergencia']);
    $group->post('/adicionar-item', [$controller, 'adicionarItem']);
    $group->get('/buscar-item', [$controller, 'buscarItem']);
});

        // ======================================================================
        // LOGÍSTICA - AUDITORIA
        // ======================================================================
$protected->group('/auditoria', function ($group) {
    $controller = new AuditoriaController();
    $group->get('/resumo', [$controller, 'getResumo']);
    $group->get('/ranking', [$controller, 'getRankingOperadores']);
    $group->get('/timeline/{idembarque}', [$controller, 'getTimeline']);
    $group->get('/embarque/{idembarque}', [$controller, 'getDetalhesEmbarque']);
    $group->get('/itens/{idembarque}', [$controller, 'getItensEmbarque']);
    $group->get('/itens/{idembarque}/{tipo}', [$controller, 'getItensEmbarque']);
    $group->get('/historico', [$controller, 'getHistoricoGerencial']);
    $group->get('/conferencia/{idembarque}', [$controller, 'getDetalhesConferencia']);
    $group->get('/exportar', [$controller, 'exportarRelatorio']);
});

        // ======================================================================
        // INVENTÁRIO (CONSULTA DE ESTOQUE)
        // ======================================================================
$protected->group('/inventario', function ($group) {
    $controller = new InventarioController();
    $group->get('/filiais', [$controller, 'getFiliais']);
    $group->get('/marcas', [$controller, 'getMarcas']);
    $group->get('/grupos', [$controller, 'getGrupos']);
    $group->get('/buscar-itens', [$controller, 'buscarItens']);
    $group->post('/consultar', [$controller, 'consultarInventario']);
    $group->get('/detalhes-lote/{iditem}/{lote}', [$controller, 'getDetalhesLote']);
    $group->get('/exportar-excel', [$controller, 'exportarExcel']);
});

        // ======================================================================
        // FINANCEIRO
        // ======================================================================
$protected->group('/financeiro', function ($group) {
    $controller = new FinanceiroController();
    $group->post('/dashboard', [$controller, 'getDashboard']);
    $group->post('/historico-kpi', [$controller, 'getHistoricoKpi']);
    $group->post('/lista-usuarios', [$controller, 'getListaUsuariosHistorico']);
    $group->post('/detalhes-kpi', [$controller, 'getDetalhesAnaliseKpi']);
});

        // ======================================================================
        // RECEITA FEDERAL - CONSULTA CNPJ
        // ======================================================================
$protected->post('/receita/consultar', [new ReceitaController(), 'consultar']);

        // ======================================================================
        // ROTAS OTIMIZADAS - MARKETING (NOVAS)
        // ======================================================================

        // ======================================================================
        // DASHBOARD (ROTA PRINCIPAL - UMA ÚNICA CONSULTA)
        // ======================================================================
$protected->get('/marketing/dashboard-final', [new MarketingController(), 'getDashboardFinal']);

        // ======================================================================
        // CRM - ESTATÍSTICAS OTIMIZADAS
        // ======================================================================
$protected->get('/marketing/crm-estatisticas', [new MarketingController(), 'getCrmEstatisticas']);

        // ======================================================================
        // CLIENTES - CONSULTA OTIMIZADA
        // ======================================================================
$protected->get('/marketing/clientes/consulta-otimizado', [new MarketingController(), 'consultarClientesUnificadoOtimizado']);
$protected->post('/marketing/clientes/mesclar-com-erp', [MarketingController::class, 'mesclarClienteComERP']);

        // ======================================================================
        // METAS - LISTAGEM OTIMIZADA (UMA ÚNICA CONSULTA)
        // ======================================================================
$protected->get('/marketing/metas-otimizadas', [new MarketingController(), 'getMetasOtimizadas']);

        // ======================================================================
        // METAS - HISTÓRICO DE ALIMENTAÇÃO
        // ======================================================================
$protected->get('/marketing/meta-historico/{id}', [new MarketingController(), 'getMetaHistorico']);

        // ======================================================================
        // MARKETING - ROTAS LEGADO (MANTIDAS PARA COMPATIBILIDADE)
        // ======================================================================
$protected->get('/marketing/dashboard-otimizado', [new MarketingController(), 'getDashboardOtimizado']);
$protected->get('/marketing/comparativo-otimizado', [new MarketingController(), 'getComparativoOtimizado']);
$protected->post('/marketing/atualizar-cache', [new MarketingController(), 'atualizarCacheDashboard']);
$protected->get('/marketing/clientes/comparativo-erp', [new MarketingController(), 'getComparativoERP']);
$protected->get('/marketing/dashboard-totals', [new MarketingController(), 'getDashboardTotals']);
$protected->get('/marketing/dashboard-taxas', [new MarketingController(), 'getDashboardTaxas']);
$protected->get('/marketing/dashboard-resumo', [new MarketingController(), 'getDashboardResumo']);
$protected->get('/marketing/clientes-compradores', [new MarketingController(), 'getClientesCompradoresNaoCompradores']);

        // ======================================================================
        // MARKETING - GRUPO PRINCIPAL
        // ======================================================================
$protected->group('/marketing', function ($group) {
    $controller = new MarketingController();
    $pedidosController = new ClientePedidosController();

            // ======================================================================
            // PEDIDOS ERP (NOVAS ROTAS)
            // ======================================================================
    $group->get('/clientes/{id}/pedidos-erp', [$pedidosController, 'getPedidosClienteCRM']);
    $group->post('/clientes/buscar-por-cnpj', [$pedidosController, 'buscarPorCnpj']);

            // ======================================================================
            // CLIENTES - CONSULTA E IMPORTAÇÃO
            // ======================================================================
    $group->get('/clientes/consulta', [$controller, 'consultarClientesUnificado']);
    $group->post('/clientes/importar-erp/{id}', [$controller, 'importarClienteERP']);
    $group->get('/clientes/erp/{id}', [$controller, 'getClienteERPDetalhes']);
    $group->get('/clientes/exportar/{formato}', [$controller, 'exportarClientes']);
    $group->post('/clientes/sincronizar-todos', [$controller, 'sincronizarTodosClientes']);
    $group->post('/clientes/sincronizar-dados', [$controller, 'sincronizarDadosCliente']);

            // ======================================================================
            // CLIENTES - CRUD
            // ======================================================================
    $group->get('/clientes', [$controller, 'getClientes']);
    $group->post('/clientes', [$controller, 'salvarCliente']);
    $group->get('/clientes/{id}', [$controller, 'getClienteDetalhes']);
    $group->put('/clientes/{id}', [$controller, 'atualizarCliente']);
    $group->delete('/clientes/{id}', [$controller, 'deletarCliente']);

            // ======================================================================
            // CLIENTES - INTERAÇÕES E ANEXOS
            // ======================================================================
    $group->get('/clientes/{id}/interacoes', [$controller, 'getInteracoes']);
    $group->post('/clientes/{id}/interacoes', [$controller, 'salvarInteracao']);
    $group->post('/clientes/{id}/anexos', [$controller, 'uploadAnexo']);
    $group->get('/clientes/{id}/anexos', [$controller, 'getAnexos']);
    $group->put('/clientes/{id}/tags', [$controller, 'atualizarTags']);
    $group->get('/clientes/{id}/tags', [$controller, 'getTags']);

            // ======================================================================
            // COMPROMISSOS
            // ======================================================================
    $group->get('/compromissos', [$controller, 'getCompromissos']);
    $group->get('/compromissos/proximos', [$controller, 'getProximosCompromissos']);
    $group->get('/compromissos/estatisticas', [$controller, 'getEstatisticasCompromissos']);
    $group->get('/compromissos/cliente/{id}', [$controller, 'getCompromissosPorCliente']);
    $group->post('/compromissos', [$controller, 'criarCompromisso']);
    $group->put('/compromissos/{id}', [$controller, 'atualizarCompromisso']);
    $group->put('/compromissos/{id}/concluir', [$controller, 'concluirCompromisso']);
    $group->delete('/compromissos/{id}', [$controller, 'deletarCompromisso']);
    $group->get('/compromissos/meus-proximos', [$controller, 'getMeusProximosCompromissos']);

            // ======================================================================
            // DASHBOARD E KPIS
            // ======================================================================
    $group->get('/dashboard', [$controller, 'getDashboard']);
    $group->get('/dashboard-completo', [$controller, 'getDashboardCompleto']);
    $group->get('/kpis', [$controller, 'getKPIs']);
    $group->get('/dados-grafico', [$controller, 'getDadosGrafico']);
    $group->post('/alimentar', [$controller, 'alimentarDiario']);
    $group->get('/historico-alimentacao', [$controller, 'getHistoricoAlimentacao']);

            // ======================================================================
            // METAS
            // ======================================================================
    $group->get('/metas', [$controller, 'getMetas']);
    $group->get('/metas/{id}', [$controller, 'getMetaDetalhes']);
    $group->post('/metas', [$controller, 'salvarMeta']);
    $group->put('/metas/{id}', [$controller, 'atualizarMeta']);
    $group->delete('/metas/{id}', [$controller, 'deletarMeta']);
    $group->get('/metas-dashboard', [$controller, 'getMetasDashboard']);
    $group->get('/metas-progresso', [$controller, 'getMetasProgresso']);

            // ======================================================================
            // LEADS
            // ======================================================================
    $group->post('/leads', [$controller, 'salvarLead']);
    $group->get('/leads', [$controller, 'getLeads']);
    $group->put('/leads/{id}', [$controller, 'atualizarLead']);
    $group->get('/leads/{id}', [$controller, 'getLeadById']);

            // ======================================================================
            // LEMBRETES
            // ======================================================================
    $group->get('/lembretes/alertas', [$controller, 'getLembretesAlertas']);
    $group->get('/lembretes', [$controller, 'getLembretes']);
    $group->post('/lembretes', [$controller, 'criarLembrete']);
    $group->put('/lembretes/{id}', [$controller, 'concluirLembrete']);
    $group->delete('/lembretes/{id}', [$controller, 'deletarLembrete']);
    $group->get('/lembretes-hoje', [$controller, 'getLembretesHoje']);

            // ======================================================================
            // CRM
            // ======================================================================
    $group->get('/crm-dashboard', [$controller, 'getCRMDashboard']);
    $group->get('/resumo-geral', [$controller, 'getResumoGeral']);
    $group->get('/comparativo-mensal', [$controller, 'getComparativoMensal']);

            // ======================================================================
            // ANEXOS
            // ======================================================================
    $group->get('/anexos/{id}/download', [$controller, 'downloadAnexo']);
    $group->delete('/anexos/{id}', [$controller, 'deletarAnexo']);

            // ======================================================================
            // EMAIL
            // ======================================================================
    $group->post('/enviar-relatorio-email', [$controller, 'enviarRelatorioEmail']);
    $group->post('/configurar-email-auto', [$controller, 'configurarEmailAuto']);
});

        // ======================================================================
        // MARKETING ADMIN (ACESSO RESTRITO)
        // ======================================================================
$protected->group('/marketing-admin', function ($group) {
    $controller = new MarketingAdminController();
    $group->get('/config', [$controller, 'getConfig']);
    $group->post('/salvar', [$controller, 'salvar']);
    $group->get('/dashboard', [$controller, 'getDashboard']);
});

        // ======================================================================
        // META BUILDER - SISTEMA MOLDÁVEL
        // ======================================================================
$protected->group('/meta-builder', function ($group) {
    $controller = new MetaBuilderController();
    $group->get('/tipos', [$controller, 'getTiposMeta']);
    $group->post('/tipos', [$controller, 'criarTipoMeta']);
    $group->get('/tipos/{id}/campos', [$controller, 'getCamposTipo']);
    $group->delete('/tipos/{id}', [$controller, 'deletarTipoMeta']);
    $group->post('/tipos/campos', [$controller, 'adicionarCampo']);
    $group->get('/tipos/{id}', [$controller, 'getTipoMeta']);
    $group->put('/tipos/{id}', [$controller, 'atualizarTipoMeta']);
    $group->post('/instancias', [$controller, 'criarInstanciaMeta']);
    $group->get('/instancias/ativas', [$controller, 'getMetasAtivas']);
    $group->post('/alimentar', [$controller, 'alimentarMeta']);
    $group->get('/alimentacao/{id}', [$controller, 'getAlimentacaoPorMeta']);
    $group->delete('/instancias/{id}', [$controller, 'deletarInstanciaMeta']);
    $group->get('/instancias/{id}', [$controller, 'getInstanciaMeta']);
    $group->get('/dashboard', [$controller, 'getDashboard']);
    $group->get('/progresso/{id}', [MetaBuilderController::class, 'getProgressoMeta']);
    $group->get('/campos/{id}', [MetaBuilderController::class, 'getCamposEditaveis']);
});

        // ======================================================================
        // DEPÓSITO (GESTÃO DE ENDEREÇOS)
        // ======================================================================
$protected->group('/deposito', function ($group) {
    $controller = new DepositoController();
    $group->get('/secoes', [$controller, 'getSecoes']);
    $group->get('/enderecos/{idsecao}', [$controller, 'getEnderecos']);
    $group->get('/lotes-endereco/{idendereco}', [$controller, 'getLotesPorEndereco']);
    $group->post('/endereco', [$controller, 'salvarEndereco']);
    $group->delete('/endereco/{idsecao}/{idendereco}', [$controller, 'deletarEndereco']);
    $group->get('/resumo', [$controller, 'getResumo']);
    $group->post('/secao', [$controller, 'salvarSecao']);
    $group->get('/secao/{idsecao}', [$controller, 'getSecao']);
});

        // ======================================================================
        // ESTOQUE COM PREVISÃO
        // ======================================================================
$protected->group('/estoque-previsao', function ($group) {
    $controller = new EstoquePrevisaoController();
    $group->get('/marcas', [$controller, 'getMarcas']);
    $group->post('/resumo', [$controller, 'getResumo']);
    $group->post('/produtos', [$controller, 'getProdutos']);
    $group->get('/buscar-item', [$controller, 'buscarItem']);
    $group->get('/item/{id}', [$controller, 'getItemDetalhe']);
    $group->get('/filiais', [$controller, 'getFiliais']);
    $group->post('/exportar', [$controller, 'exportar']);
});

        // ======================================================================
        // DESEMBARQUE (CONFERÊNCIA DE RECEBIMENTO)
        // ======================================================================
$protected->group('/desembarque', function ($group) {
    $controller = new DesembarqueController();
    $group->get('/ordens-compra', [$controller, 'getOrdensCompra']);
    $group->get('/itens/{idoc}', [$controller, 'getItens']);
    $group->get('/buscar-item/{idoc}', [$controller, 'buscarItem']);
    $group->post('/confirmar', [$controller, 'confirmarItem']);
    $group->post('/finalizar/{idoc}', [$controller, 'finalizarConferencia']);
    $group->get('/secoes', [$controller, 'getSecoes']);
    $group->post('/foto', [$controller, 'uploadFoto']);
    $group->get('/enderecos/{idsecao}', [$controller, 'getEnderecos']);
});

        // ======================================================================
        // NOTIFICAÇÕES
        // ======================================================================
$protected->get('/notificacoes', [new MarketingController(), 'getNotificacoes']);
$protected->put('/notificacoes/{id}/ler', [new MarketingController(), 'marcarNotificacaoLida']);
$protected->put('/notificacoes/ler-todas', [new MarketingController(), 'marcarTodasLidas']);

        // ======================================================================
        // NOTIFICAÇÕES DO CRM
        // ======================================================================
$protected->group('/crm', function ($group) {
    $controller = new MarketingController();
    $group->get('/notificacoes', [$controller, 'getNotificacoesCRM']);
    $group->put('/notificacoes/{id}/ler', [$controller, 'marcarNotificacaoCRM']);
    $group->put('/notificacoes/ler-todas', [$controller, 'marcarTodasNotificacoesCRM']);
    $group->get('/gerar-alertas', [$controller, 'gerarAlertasCRM']);
    $group->post('/notificacoes', function ($request, $response) {
        $controller = new MarketingController();
        return $controller->criarNotificacaoViaFrontend($request, $response);
    });
});

        // ======================================================================
        // CRON JOBS
        // ======================================================================
$protected->group('/cron', function ($group) {
    $controller = new CronController();
    $group->get('/auditoria', [$controller, 'getAuditoria']);
    $group->get('/auditoria/{id}', [$controller, 'getAuditoriaDetalhes']);
    $group->post('/executar', [$controller, 'executar']);
    $group->post('/limpar-links', [$controller, 'limparLinks']);
    $group->get('/dashboard', [$controller, 'getDashboard']);
    $group->get('/jobs', [$controller, 'getJobs']);
    $group->post('/jobs', [$controller, 'salvarJob']);
    $group->delete('/jobs/{id}', [$controller, 'deletarJob']);
    $group->get('/alertas-marketing', [new MarketingController(), 'cronAlertas']);
});

        // ======================================================================
        // VENDAS (CUBO + DASHBOARD)
        // ======================================================================
$protected->group('/vendas', function ($group) {
    $cubo = new VendasCuboController();
    $group->get('/cubo/config', [$cubo, 'getConfig']);
    $group->post('/cubo/data', [$cubo, 'getData']);
    $group->post('/cubo/ranking', [$cubo, 'getRanking']);
    $group->post('/cubo/filiais', [$cubo, 'getFiliais']);
    $group->post('/cubo/gestores', [$cubo, 'getGestores']);
    $group->post('/cubo/exportar', [$cubo, 'exportar']);
    $group->get('/cubo/filters/{field}', [$cubo, 'getFilterOptions']);
    $group->post('/cubo/supervisores-por-filial', [$cubo, 'getSupervisoresPorFilial']);
    $group->post('/cubo/representantes-por-supervisor', [$cubo, 'getRepresentantesPorSupervisor']);
    $group->post('/cubo/clientes-por-representante', [$cubo, 'getClientesPorRepresentante']);
    $group->post('/cubo/filtros-contextuais', [$cubo, 'getFiltrosContextuais']);
    $group->post('/cubo/detalhes', [$cubo, 'getDetalhes']);
    $group->post('/cubo/itens-documento', function ($request, $response) {
        $ctrl = new VendasCuboController();
        return $ctrl->getItensDocumento($request, $response);
    });

    $dash = new VendasDashboardController();
    $group->post('/dashboard/kpis-detalhes', [$dash, 'getKpisDetalhes']);
    $group->post('/dashboard/kpis', [$dash, 'getKpis']);
    $group->post('/dashboard/produto-detalhes', [$dash, 'getProdutoDetalhes']);
    $group->post('/dashboard/cliente-detalhes', [$dash, 'getClienteDetalhes']);
    $group->post('/dashboard/insights', [$dash, 'getInsights']);
    $group->post('/dashboard/detalhes-card', [$dash, 'getDetalhesCard']);
    $group->post('/dashboard/detalhes-representante', [$dash, 'getDetalhesRepresentante']);
    $group->post('/dashboard/detalhes-funil', [$dash, 'getDetalhesFunil']);
});

        // ======================================================================
        // ANÁLISE DE CARTEIRA (INADIMPLÊNCIA)
        // ======================================================================
$protected->group('/analise-carteira', function ($group) {
    $controller = new AnaliseCarteiraController();
    $group->get('/admin/gestores', [$controller, 'getTodosGestores']);
    $group->post('/gestores', [$controller, 'getGestores']);
    $group->post('/representantes', [$controller, 'getRepresentantes']);
    $group->post('/resumo-gestor', [$controller, 'getResumoGestor']);
    $group->post('/tabela-gestor', [$controller, 'getTabelaGestor']);
    $group->post('/titulos-representante', [$controller, 'getTitulosRepresentante']);
    $group->post('/exportar', [$controller, 'exportar']);
});

        // ======================================================================
        // ADMINISTRAÇÃO DO SISTEMA
        // ======================================================================
$protected->group('/admin', function ($group) {
    $controller = new AdminController();
    $group->get('/modulos', [$controller, 'getModulos']);
    $group->post('/modulos', [$controller, 'salvarModulo']);
    $group->get('/usuarios', [$controller, 'getUsuarios']);
    $group->get('/usuarios/{id}/permissoes', [$controller, 'getPermissoesUsuario']);
    $group->post('/usuarios/{id}/toggle', [$controller, 'toggleUsuario']);
    $group->post('/usuarios/editar', [$controller, 'editarUsuario']);
    $group->post('/upload-foto', [new AdminController(), 'uploadFotoPerfil']);
    $group->post('/permissoes', [$controller, 'salvarPermissoes']);
    $group->get('/gestores', [$controller, 'getGestores']);
    $group->get('/setores', [$controller, 'getSetores']);
    $group->get('/setores/{id}/modulos', [$controller, 'getModulosPorSetor']);
    $group->post('/permissoes-por-setor', [$controller, 'salvarPermissoesPorSetor']);
    $group->get('/api-tokens', [$controller, 'getTokens']);
    $group->post('/api-tokens', [$controller, 'criarToken']);
    $group->post('/api-tokens/{id}/revogar', [$controller, 'revogarToken']);
    $group->get('/escopos', [$controller, 'getEscopos']);
    $group->get('/logs', [$controller, 'getLogs']);
});

        // ======================================================================
        // CHAT INTERNO
        // ======================================================================
$protected->group('/chat', function ($group) {
    $controller = new ChatController();
    $group->get('/contatos', [$controller, 'getContatos']);
    $group->get('/mensagens/{outroUsuario}', [$controller, 'getMensagens']);
    $group->post('/enviar', [$controller, 'enviarMensagem']);
    $group->post('/marcar-lida/{remetente}', [$controller, 'marcarLida']);
    $group->get('/nao-lidas', [$controller, 'getNaoLidas']);
    $group->get('/minhas-conversas', [$controller, 'getMinhasConversas']);
});

        // ======================================================================
        // CONSULTA DE SALDO
        // ======================================================================
$protected->group('/consulta', function ($group) {
    $controller = new ConsultaController();
    $group->get('/saldos/{idembarque}', [$controller, 'getSaldos']);
    $group->get('/pedidos-item/{idembarque}/{iditem}', [$controller, 'getPedidosItem']);
    $group->post('/editar-pedido', [$controller, 'editarPedido']);
    $group->post('/remover-item-pedido', [$controller, 'removerItemPedido']);
});

$protected->get('/embarques-ativos', [new ConsultaController(), 'getEmbarquesAtivos']);

        // ==========================================================================
        // APLICAÇÃO DAS MIDDLEWARES NA ORDEM CORRETA
        // ==========================================================================
        // IMPORTANTE: A ordem é executada de BAIXO para CIMA (LIFO)
        // Ou seja: Blacklist -> JWT -> CSRF
        // ==========================================================================

    })->add(new CsrfMiddleware())      // 3º: Verifica CSRF (último a executar na ida, primeiro na volta)
      ->add(new JwtMiddleware())        // 2º: Valida o token JWT
      ->add(new BlacklistMiddleware()); // 1º: Verifica se token está na blacklist (primeiro a executar)

  });