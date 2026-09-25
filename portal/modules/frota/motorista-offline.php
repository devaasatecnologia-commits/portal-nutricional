<?php
// ======================================================================
// APP DO MOTORISTA - COM AUTENTICAÇÃO, PERFIL, LOGOUT E DRAWER
// ======================================================================

$pageTitle = 'Rota do Motorista | Nutricional Rotas';
$version = time();
$appBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') === 0) ? '/API' : '';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// ================================================================
// MODO TREINAMENTO — ativa quando ?treino=1 na URL
// Permite checkin/checkout em qualquer distância
// NÃO grava lat/lng se estiver fora do raio
// ================================================================
$modoTreinamento = filter_var($_GET['treino'] ?? false, FILTER_VALIDATE_BOOLEAN);

$usuarioLogado = $_SESSION['uid'] ?? $_SESSION['idusuario'] ?? null;

if (!$usuarioLogado) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/portal/modules/frota/motorista-offline.php');
    header('Location: /portal/login.php?redirect=' . $redirect);
    exit;
}

$motoristaVinculado = (int)($_SESSION['motorista_id'] ?? 0);
$permissoes = $_SESSION['permissoes'] ?? [];
$isAdmin = !empty($_SESSION['is_admin'])
        || in_array('admin', $permissoes, true)
        || in_array('frota', $permissoes, true)
        || in_array('gestao-cargas', $permissoes, true);

$motoristaId = 0;
if ($isAdmin && isset($_GET['motorista_id'])) {
    $motoristaId = (int)$_GET['motorista_id'];
} else {
    $motoristaId = $motoristaVinculado;
}

if (!$isAdmin && $motoristaId <= 0) {
    header('Location: /portal/?erro=sem_vinculo_motorista');
    exit;
}

$manifestUrl = $appBase . '/portal/modules/frota/manifest-motorista.json?v=' . $version;
$iconBase    = $appBase . '/portal/modules/frota/assets/icons';

$extraCss = '
<link rel="manifest" href="' . $manifestUrl . '">
<meta name="theme-color" content="#17342c">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Nutricional Rotas">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" sizes="180x180" href="' . $iconBase . '/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="' . $iconBase . '/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="' . $iconBase . '/favicon-16x16.png">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="' . $appBase . '/portal/modules/frota/assets/motorista-offline.css?v=' . $version . '">';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script src="' . $appBase . '/portal/assets/js/config.js?v=' . $version . '"></script>
<script src="' . $appBase . '/portal/modules/frota/assets/frota.js?v=' . $version . '"></script>
<script src="' . $appBase . '/portal/modules/frota/assets/motorista-offline.js?v=' . $version . '"></script>';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<!-- ================================================================
     VARIÁVEIS GLOBAIS INJETADAS PELO PHP
     ================================================================ -->
<script>
window.MOTORISTA_ID_INICIAL   = <?= (int)$motoristaId ?>;
window.MOTORISTA_ID_VINCULADO = <?= (int)$motoristaVinculado ?>;
window.IS_ADMIN_APP           = <?= $isAdmin ? 'true' : 'false' ?>;
window.MOTORISTA_NOME_SESSAO  = <?= json_encode($_SESSION['uname'] ?? $_SESSION['username'] ?? 'Motorista') ?>;
window.MODO_TREINAMENTO       = <?= $modoTreinamento ? 'true' : 'false' ?>;
</script>

<main class="motorista-app<?= $isAdmin ? ' is-admin-view' : '' ?>" data-motorista-id="<?= (int)$motoristaId ?>">

    <?php if ($modoTreinamento): ?>
    <div class="training-banner" role="status" aria-live="polite">
        <i class="fa-solid fa-graduation-cap"></i>
        <div>
            <strong>MODO TREINAMENTO</strong>
            <span>Checkin/checkout liberados em qualquer distância — posição GPS não será gravada</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- ================================================================
         PAINEL ADMIN (SÓ PARA GESTOR)
         ================================================================ -->
    <section class="driver-admin-panel" id="driver-admin-panel" <?= $isAdmin ? '' : 'hidden' ?>>
        <div class="driver-admin-head">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-chart-line"></i> Operação de hoje</span>
                <h1>Visão geral dos motoristas</h1>
                <p>Acompanhe o andamento das rotas e abra os detalhes de cada motorista.</p>
            </div>
            <label class="driver-admin-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search" id="driver-admin-search" placeholder="Buscar motorista ou placa" autocomplete="off">
            </label>
        </div>
        <div class="driver-admin-summary" aria-label="Resumo dos motoristas">
            <div><strong id="admin-total-motoristas">0</strong><span>motoristas</span></div>
            <div><strong id="admin-em-rota">0</strong><span>em rota</span></div>
            <div><strong id="admin-entregas-concluidas">0</strong><span>concluídas</span></div>
            <div><strong id="admin-entregas-pendentes">0</strong><span>pendentes</span></div>
            <div class="is-alert"><strong id="admin-problemas">0</strong><span>problemas</span></div>
        </div>
        <div class="driver-admin-list" id="driver-admin-list">
            <div class="empty-state">Carregando motoristas...</div>
        </div>
    </section>

    <!-- ================================================================
         SELECTOR DE MOTORISTA (SÓ PARA ADMIN/GESTOR)
         ================================================================ -->
    <section class="driver-selector-card" id="driver-selector-card" <?= $isAdmin ? '' : 'hidden' ?>>
        <div class="driver-selector-inner">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-id-card"></i> Identificação do Motorista</span>
                <h3 id="driver-current-name">Selecione um motorista para visualizar a rota</h3>
            </div>
            <div class="driver-selector-controls">
                <select id="driver-select-input" class="driver-select-input">
                    <option value="">Carregando motoristas...</option>
                </select>
                <button type="button" id="btn-confirm-driver" class="driver-confirm-btn">Entrar na Rota</button>
            </div>
        </div>
    </section>

    <!-- ================================================================
         HEADER
         ================================================================ -->
    <header class="motorista-header">
        <div class="motorista-header-info">
            <span class="eyebrow"><i class="fa-solid fa-route"></i> Rota do dia</span>
            <h1><?= $isAdmin ? 'Detalhes da rota' : 'Minhas entregas' ?></h1>
            <p id="motorista-status">Preparando dados para uso offline</p>
        </div>
        <div class="header-right-actions">
            <div class="connection-state" id="connection-state" aria-live="polite">
                <span class="connection-dot"></span>
                <span>Online</span>
            </div>
            <button type="button" class="driver-menu-btn" id="driver-menu-btn" aria-label="Abrir menu">
                <img id="driver-avatar-mini" src="" alt="" hidden>
                <span id="driver-avatar-iniciais">?</span>
            </button>
        </div>
    </header>

    <!-- ============================================================
         TABS — Minha Rota / Meu Painel
         ============================================================ -->
    <nav class="driver-tabs" id="driver-tabs" role="tablist">
        <button type="button"
                class="driver-tab active"
                data-tab="rota"
                onclick="mudarAbaMotorista('rota', this)"
                role="tab"
                aria-selected="true">
            <i class="fa-solid fa-route"></i> Minha Rota
        </button>
        <button type="button"
                class="driver-tab"
                data-tab="painel"
                onclick="mudarAbaMotorista('painel', this)"
                role="tab"
                aria-selected="false">
            <i class="fa-solid fa-chart-line"></i> Meu Painel
        </button>
    </nav>

    <!-- ============================================================
         ABA: MINHA ROTA
         ============================================================ -->
    <div class="driver-tab-panel" id="driver-tab-rota" role="tabpanel">

        <!-- CARD DO CAMINHÃO -->
        <section class="truck-card" id="truck-card" hidden aria-label="Meu veículo">
            <div class="truck-card-icon"><i class="fa-solid fa-truck"></i></div>
            <div class="truck-card-body">
                <span class="eyebrow">Meu veículo</span>
                <h3 id="truck-card-placa">-</h3>
                <p id="truck-card-modelo">-</p>
            </div>
            <div class="truck-card-tracking" id="truck-card-tracking">
                <span class="truck-tracking-dot" id="truck-tracking-dot"></span>
                <span id="truck-tracking-label">Rastreio indisponível</span>
            </div>
        </section>

        <!-- ============================================================
             CABEÇALHO DO EMBARQUE — colapsável
             Colapsado: [EMB-XXX] [ERP #] [20/35] [status] [▾]
             Expandido: rota, veículo, motorista, valor, peso, datas
             ============================================================ -->
        <section class="embarque-header-card is-collapsed" id="embarque-header-card" hidden aria-label="Cabeçalho do embarque">
                  <header class="embarque-header-head" id="embarque-header-head" role="button" tabindex="0" aria-expanded="false">
                <div class="embarque-header-main">
                    <span class="embarque-header-numero" id="embarque-header-numero">—</span>
                    <span class="embarque-header-erp" id="embarque-header-erp" hidden></span>
                    <span class="embarque-header-progresso" id="embarque-header-progresso">0/0</span>
                    <span class="embarque-header-status" id="embarque-header-status" hidden></span>
                    <button type="button"
                            class="embarque-header-distancia"
                            id="embarque-header-distancia"
                            hidden
                            title="Abrir no Google Maps"
                            aria-label="Distância até a próxima parada">
                        <i class="fa-solid fa-location-arrow"></i>
                        <span id="embarque-header-distancia-texto">— km · ~— min</span>
                    </button>
                </div>
                <i class="fa-solid fa-chevron-down embarque-header-chevron" aria-hidden="true"></i>
            </header>

            <div class="embarque-header-body">
                <div class="embarque-header-grid">
                    <div class="embarque-header-item">
                        <span class="embarque-header-label"><i class="fa-solid fa-route"></i> Rota</span>
                        <strong id="embarque-header-rota">—</strong>
                    </div>
                    <div class="embarque-header-item">
                        <span class="embarque-header-label"><i class="fa-solid fa-truck"></i> Veículo</span>
                        <strong id="embarque-header-veiculo">—</strong>
                    </div>
                    <div class="embarque-header-item">
                        <span class="embarque-header-label"><i class="fa-solid fa-user"></i> Motorista</span>
                        <strong id="embarque-header-motorista">—</strong>
                    </div>
                    <div class="embarque-header-item">
                        <span class="embarque-header-label"><i class="fa-solid fa-sack-dollar"></i> Valor</span>
                        <strong id="embarque-header-valor">—</strong>
                    </div>
                    <div class="embarque-header-item">
                        <span class="embarque-header-label"><i class="fa-solid fa-weight-hanging"></i> Peso</span>
                        <strong id="embarque-header-peso">—</strong>
                    </div>
                    <div class="embarque-header-item">
                        <span class="embarque-header-label"><i class="fa-regular fa-calendar"></i> Saída</span>
                        <strong id="embarque-header-saida">—</strong>
                    </div>
                </div>

                <div class="embarque-header-progress">
                    <div class="embarque-header-progress-track">
                        <span id="embarque-header-progress-bar" style="width:0%"></span>
                    </div>
                    <small class="embarque-header-progress-label" id="embarque-header-progress-label">0% concluído</small>
                </div>
            </div>
        </section>

        <!-- KPI GRID -->
        <section class="route-kpi-grid" aria-label="Resumo da rota">
            <div class="route-kpi-card is-pending">
                <span class="route-kpi-icon"><i class="fa-solid fa-hourglass-half"></i></span>
                <strong id="kpi-faltam">0</strong>
                <small>faltam</small>
            </div>
            <div class="route-kpi-card is-done">
                <span class="route-kpi-icon"><i class="fa-solid fa-circle-check"></i></span>
                <strong id="kpi-concluidas">0</strong>
                <small>concluídas</small>
            </div>
            <div class="route-kpi-card is-route">
                <span class="route-kpi-icon"><i class="fa-solid fa-truck-fast"></i></span>
                <strong id="kpi-em-rota">0</strong>
                <small>em rota</small>
            </div>
            <div class="route-kpi-card is-problem">
                <span class="route-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></span>
                <strong id="kpi-problemas">0</strong>
                <small>problemas</small>
            </div>
        </section>

        <!-- BARRA DE PROGRESSO GERAL -->
        <section class="driver-progress-card" aria-label="Progresso da rota">
            <div class="driver-progress-head">
                <div><span class="eyebrow">Progresso da rota</span><strong id="route-progress-label">0% concluído</strong></div>
                <span id="route-progress-count">0 de 0</span>
            </div>
            <div class="driver-progress-track"><span id="route-progress-bar"></span></div>
        </section>

        <!-- PRÓXIMA PARADA — HERO -->
        <section class="next-stop-card" id="next-stop-card" hidden aria-label="Próxima parada">
            <div class="next-stop-badge" id="next-stop-badge">
                <span class="next-stop-badge-num" id="next-stop-num">1</span>
                <span class="next-stop-badge-label">PRÓXIMA</span>
            </div>
            <div class="next-stop-content">
                <h2 id="next-stop-name">-</h2>
                <p id="next-stop-address">-</p>
                <div class="next-stop-chips">
                    <span class="next-stop-distance" id="next-stop-distance"></span>
                    <span class="next-stop-eta" id="next-stop-eta"></span>
                    <span class="next-stop-pedidos" id="next-stop-pedidos"></span>
                    <span class="next-stop-valor" id="next-stop-valor"></span>
                </div>
            </div>
                    <div class="next-stop-actions-group">
                <button type="button" class="next-stop-nav-btn waze-btn" id="next-stop-waze" title="Navegar com Waze">
                    <i class="fa-brands fa-waze"></i> Waze
                </button>
                <button type="button" class="next-stop-nav-btn gmaps-btn" id="next-stop-gmaps" title="Navegar com Google Maps">
                    <i class="fa-solid fa-map-location-dot"></i> Maps
                </button>
                <button type="button" class="next-stop-contact-btn is-whatsapp" id="next-stop-whatsapp" title="Enviar WhatsApp ao cliente">
                    <i class="fa-brands fa-whatsapp"></i> WhatsApp
                </button>
                <button type="button" class="next-stop-contact-btn is-call" id="next-stop-ligar" title="Ligar para o cliente">
                    <i class="fa-solid fa-phone"></i> Ligar
                </button>
                <button type="button" class="next-stop-action" id="next-stop-action">Cheguei</button>
            </div>
        </section>

        <!-- MAPA RECOLHÍVEL -->
        <div class="route-map-wrap" id="route-map-wrap" hidden>
            <div class="route-map-head">
                <span><i class="fa-solid fa-map"></i> Mapa da rota</span>
                <button type="button" class="route-map-toggle" id="route-map-toggle" onclick="toggleMapaMotorista()">
                    <span id="route-map-toggle-label">Mostrar</span>
                    <i class="fa-solid fa-chevron-down" id="route-map-toggle-icon"></i>
                </button>
            </div>
            <div id="route-map" class="route-map"></div>
        </div>

        <div class="route-map-offline" id="route-map-offline" hidden>
            <i class="fa-solid fa-signal"></i> Sem conexão para exibir o mapa — mostrando distância estimada de cada parada.
        </div>

        <!-- FERRAMENTAS -->
        <section class="route-tools" aria-label="Ferramentas da rota">
            <button type="button" id="btn-refresh-route" class="route-tool">
                <i class="fa-solid fa-rotate-right"></i> Atualizar rota
            </button>
            <span id="gps-status" class="gps-status"><i class="fa-solid fa-location-crosshairs"></i> GPS aguardando</span>
        </section>

        <!-- BUSCA NA LISTA DE PARADAS -->
        <div class="route-search-wrap">
            <label class="route-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="search"
                       id="route-search-input"
                       placeholder="Buscar por pedido, cliente ou endereço..."
                       autocomplete="off">
                <button type="button" class="route-search-clear" id="route-search-clear" hidden aria-label="Limpar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </label>
        </div>

        <!-- ============================================================
             CHIPS DE FILTRO DE STATUS
             Todas | Pendentes | Em rota | Entregues | Problema
             Filtra APENAS a lista (próxima parada não é afetada)
             ============================================================ -->
       <nav class="route-filter-chips" id="route-filter-chips" aria-label="Filtrar paradas por status">
    <button type="button" class="filter-chip" data-status="todas" onclick="aplicarFiltroStatus('todas', this)">
        <span>Todas</span>
        <span class="filter-chip-count" id="chip-count-todas">0</span>
    </button>
    <button type="button" class="filter-chip active" data-status="pendente" onclick="aplicarFiltroStatus('pendente', this)">
                <span>Pendentes</span>
                <span class="filter-chip-count" id="chip-count-pendente">0</span>
            </button>
            <button type="button" class="filter-chip" data-status="em_entrega" onclick="aplicarFiltroStatus('em_entrega', this)">
                <span>Em rota</span>
                <span class="filter-chip-count" id="chip-count-em_entrega">0</span>
            </button>
            <button type="button" class="filter-chip" data-status="entregue" onclick="aplicarFiltroStatus('entregue', this)">
                <span>Entregues</span>
                <span class="filter-chip-count" id="chip-count-entregue">0</span>
            </button>
            <button type="button" class="filter-chip" data-status="problema" onclick="aplicarFiltroStatus('problema', this)">
                <span>Problema</span>
                <span class="filter-chip-count" id="chip-count-problema">0</span>
            </button>
        </nav>

        <!-- ============================================================
             LISTA DE PARADAS
             🔥 RESTAURADO 2026-09-25: esta <section> havia sido removida
             acidentalmente ao inserir os chips. Sem ela, o render() não
             tem onde desenhar os cards (delivery-list era null).
             ============================================================ -->
        <section class="delivery-list" id="delivery-list" aria-live="polite">
            <div class="empty-state">Carregando sua rota...</div>
        </section>
    </div>

      <!-- ============================================================
         ABA: MEU PAINEL — Pacote 2 (2026-09-25)
         ============================================================ -->
    <div class="driver-tab-panel" id="driver-tab-painel" role="tabpanel" hidden>

        <!-- ESTADO DE CARREGAMENTO -->
        <div class="painel-loading" id="painel-loading">
            <i class="fa-solid fa-spinner fa-spin"></i>
            <span>Carregando seu painel...</span>
        </div>

        <!-- CONTEÚDO DO PAINEL (escondido até carregar) -->
        <div class="painel-conteudo" id="painel-conteudo" hidden>

            <!-- ══════════════════════════════════════════════════
                 BLOCO 1: SCORE DE DESEMPENHO (hero)
                 ══════════════════════════════════════════════════ -->
            <section class="painel-score-card" id="painel-score-card" aria-label="Meu desempenho">
                <div class="painel-score-head">
                    <span class="eyebrow"><i class="fa-solid fa-shield-halved"></i> Meu desempenho</span>
                    <span class="painel-score-periodo" id="painel-score-periodo">últimos 30 dias</span>
                </div>

                <div class="painel-score-body">
                    <div class="painel-score-gauge" id="painel-score-gauge">
                        <svg viewBox="0 0 120 120" class="painel-score-svg">
                            <circle class="painel-score-bg" cx="60" cy="60" r="52"></circle>
                            <circle class="painel-score-fill" id="painel-score-fill" cx="60" cy="60" r="52"
                                    stroke-dasharray="326.7" stroke-dashoffset="326.7"></circle>
                        </svg>
                        <div class="painel-score-valor">
                            <strong id="painel-score-numero">—</strong>
                            <small>pontos</small>
                        </div>
                    </div>

                    <div class="painel-score-info">
                        <div class="painel-score-linha">
                            <span><i class="fa-solid fa-road"></i> Km rodados</span>
                            <strong id="painel-score-km">0 km</strong>
                        </div>
                        <div class="painel-score-linha">
                            <span><i class="fa-solid fa-triangle-exclamation"></i> Eventos</span>
                            <strong id="painel-score-eventos">0</strong>
                        </div>
                        <div class="painel-score-linha">
                            <span><i class="fa-solid fa-gauge-high"></i> Vel. média</span>
                            <strong id="painel-score-velocidade">0 km/h</strong>
                        </div>
                        <div class="painel-score-variacao" id="painel-score-variacao" hidden>
                            <i class="fa-solid fa-arrow-trend-up"></i>
                            <span id="painel-score-variacao-texto"></span>
                        </div>
                    </div>
                </div>

                <div class="painel-score-vazio" id="painel-score-vazio" hidden>
                    <i class="fa-solid fa-shield-halved"></i>
                    <p>Sem dados de segurança ainda</p>
                    <small>Assim que você começar a dirigir com o rastreador Cobli, seu score aparecerá aqui.</small>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════
                 BLOCO 2: RANKING (anônimo)
                 ══════════════════════════════════════════════════ -->
            <section class="painel-ranking-card" id="painel-ranking-card" aria-label="Minha posição no ranking">
                <div class="painel-ranking-icon">
                    <i class="fa-solid fa-trophy"></i>
                </div>
                <div class="painel-ranking-info">
                    <span class="eyebrow">Ranking da equipe</span>
                    <h3 id="painel-ranking-titulo">Sem posição ainda</h3>
                    <p id="painel-ranking-subtitulo">Complete viagens para entrar no ranking.</p>
                </div>
                <div class="painel-ranking-posicao" id="painel-ranking-posicao" hidden>
                    <strong id="painel-ranking-numero">—</strong>
                    <small id="painel-ranking-total">de —</small>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════
                 BLOCO 3: RESUMO DO MÊS
                 ══════════════════════════════════════════════════ -->
                      <section class="painel-resumo-card" id="painel-resumo-card" aria-label="Resumo do mês">
                <div class="painel-resumo-head">
                    <span class="eyebrow"><i class="fa-solid fa-calendar-days"></i> Este mês</span>
                    <strong id="painel-resumo-mes">—</strong>
                </div>

                <div class="painel-resumo-grid">
                    <div class="painel-resumo-item">
                        <span class="painel-resumo-icon is-success"><i class="fa-solid fa-circle-check"></i></span>
                        <strong id="painel-resumo-entregas">0</strong>
                        <small>entregas</small>
                    </div>
                    <div class="painel-resumo-item">
                        <span class="painel-resumo-icon is-money"><i class="fa-solid fa-sack-dollar"></i></span>
                        <strong id="painel-resumo-valor">R$ 0</strong>
                        <small>em valor</small>
                    </div>
                    <div class="painel-resumo-item">
                        <span class="painel-resumo-icon is-km"><i class="fa-solid fa-road"></i></span>
                        <strong id="painel-resumo-km">0 km</strong>
                        <small>rodados</small>
                    </div>
                    <div class="painel-resumo-item">
                        <span class="painel-resumo-icon is-time"><i class="fa-solid fa-clock"></i></span>
                        <strong id="painel-resumo-tempo">—</strong>
                        <small>tempo médio</small>
                    </div>
                </div>

                <div class="painel-resumo-alerta" id="painel-resumo-alerta" hidden>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="painel-resumo-alerta-texto"></span>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════
                 BLOCO 4: CAMINHÃO HOJE
                 ══════════════════════════════════════════════════ -->
            <section class="painel-caminhao-card" id="painel-caminhao-card" aria-label="Meu caminhão hoje" hidden>
                <div class="painel-caminhao-head">
                    <span class="eyebrow"><i class="fa-solid fa-truck"></i> Meu caminhão hoje</span>
                    <span class="painel-caminhao-status" id="painel-caminhao-status">—</span>
                </div>

                <div class="painel-caminhao-main">
                    <div class="painel-caminhao-placa" id="painel-caminhao-placa">—</div>
                    <div class="painel-caminhao-modelo" id="painel-caminhao-modelo">—</div>
                </div>

                <div class="painel-caminhao-grid">
                    <div class="painel-caminhao-item">
                        <span><i class="fa-solid fa-gauge"></i> Odômetro</span>
                        <strong id="painel-caminhao-odometro">—</strong>
                    </div>
                    <div class="painel-caminhao-item">
                        <span><i class="fa-solid fa-tachometer-alt"></i> Velocidade</span>
                        <strong id="painel-caminhao-velocidade">—</strong>
                    </div>
                    <div class="painel-caminhao-item">
                        <span><i class="fa-solid fa-pause"></i> Tempo parado</span>
                        <strong id="painel-caminhao-parado">—</strong>
                    </div>
                    <div class="painel-caminhao-item">
                        <span><i class="fa-solid fa-location-dot"></i> Localização</span>
                        <strong id="painel-caminhao-localizacao">—</strong>
                    </div>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════
                 BLOCO 5: ÚLTIMOS EMBARQUES
                 ══════════════════════════════════════════════════ -->
            <section class="painel-embarques-card" aria-label="Últimos embarques">
                <div class="painel-embarques-head">
                    <span class="eyebrow"><i class="fa-solid fa-clock-rotate-left"></i> Últimos embarques</span>
                </div>

                <div class="painel-embarques-list" id="painel-embarques-list">
                    <div class="empty-state">Carregando histórico...</div>
                </div>
            </section>

            <!-- ══════════════════════════════════════════════════
                 RODAPÉ
                 ══════════════════════════════════════════════════ -->
            <div class="painel-rodape">
                <small id="painel-atualizado-em">—</small>
            </div>

        </div>
    </div>

    <!-- ================================================================
         MODAL DE CHECKOUT (mantido igual)
         ================================================================ -->
    <div class="driver-modal" id="checkout-modal" hidden>
        <form class="driver-modal-card checkout-modal-card" id="checkout-form">

            <div class="driver-modal-head">
                <div>
                    <span class="eyebrow"><i class="fa-solid fa-certificate"></i> Comprovante digital</span>
                    <h2>Finalizar entrega</h2>
                </div>
                <button type="button" class="modal-close" id="checkout-cancel" aria-label="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="checkout-progress">
                <div class="checkout-progress-bar">
                    <span id="checkout-progress-fill" style="width:0%"></span>
                </div>
                <span class="checkout-progress-label" id="checkout-progress-label">
                    0 de 0 itens prontos
                </span>
            </div>

            <label class="checkout-field">
                <span><i class="fa-solid fa-user"></i> Nome de quem recebeu *</span>
                <input id="receiver-name" required maxlength="120" autocomplete="name" placeholder="Ex: João da Silva">
            </label>

            <div class="checkout-section">
                <div class="checkout-section-head">
                    <h3><i class="fa-solid fa-clipboard-list"></i> Itens do pedido</h3>
                    <small id="checkout-itens-resumo">—</small>
                </div>
                <div id="checklist-fields" class="checkout-list"></div>
            </div>

            <div class="checkout-section">
                <div class="checkout-section-head">
                    <h3><i class="fa-solid fa-file-signature"></i> Romaneio assinado *</h3>
                </div>
                <label class="checkout-photo-upload" id="romaneio-upload-label">
                    <input id="romaneio-photo" type="file" accept="image/*" capture="environment" required hidden>
                    <i class="fa-solid fa-camera"></i>
                    <span>Toque para tirar foto do romaneio</span>
                    <div id="romaneio-preview" class="checkout-photo-preview" hidden>
                        <img id="romaneio-preview-img" src="" alt="Romaneio">
                        <button type="button" class="checkout-photo-remove" id="romaneio-remove">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </label>
            </div>

            <div class="checkout-section">
                <div class="checkout-section-head">
                    <h3><i class="fa-solid fa-pen-nib"></i> Assinatura do recebedor *</h3>
                </div>
                <div class="checkout-signature-wrap">
                    <canvas id="signature-pad" width="560" height="180"></canvas>
                    <button type="button" class="signature-clear" id="signature-clear">
                        <i class="fa-solid fa-eraser"></i> Limpar
                    </button>
                </div>
            </div>

            <div class="checkout-footer">
                <button type="button" class="checkout-btn-cancel" id="checkout-cancel-2">
                    Cancelar
                </button>
                <button class="checkout-submit" type="submit" id="checkout-submit">
                    <i class="fa-solid fa-check"></i> Salvar entrega
                </button>
            </div>

        </form>
    </div>
        <!-- ================================================================
             MODAL DE DETALHES DA ENTREGA (NOVO)
             Exibe o histórico completo de uma entrega finalizada ou com problema.
             ================================================================ -->
        <div class="driver-modal" id="entrega-detalhes-modal" hidden>
            <div class="driver-modal-card entrega-detalhes-card">
                <div class="driver-modal-head">
                    <div>
                        <span class="eyebrow"><i class="fa-solid fa-file-invoice"></i> Detalhes da Entrega</span>
                        <h2 id="detalhes-titulo">Detalhes da Parada</h2>
                    </div>
                    <button type="button" class="modal-close" id="detalhes-modal-close" aria-label="Fechar">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div id="detalhes-conteudo" class="entrega-detalhes-conteudo">
                    <div class="text-center py-8">
                        <i class="fa-solid fa-spinner fa-spin text-3xl text-emerald-500"></i>
                        <p class="mt-3 text-slate-400">Carregando detalhes...</p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ================================================================
         DRAWER LATERAL (MENU DO MOTORISTA)
         ================================================================ -->
    <!-- ================================================================
         DRAWER LATERAL (MENU DO MOTORISTA)
         ================================================================ -->
    <aside class="driver-drawer" id="driver-drawer" hidden>
        <div class="drawer-header">
            <div class="drawer-avatar-wrap">
                <img id="drawer-avatar" src="" alt="" hidden>
                <span id="drawer-avatar-iniciais">?</span>
            </div>
            <div class="drawer-user-info">
                <strong id="drawer-nome">-</strong>
                <span id="drawer-cargo">Motorista</span>
            </div>
            <button type="button" class="drawer-close" id="drawer-close" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="drawer-menu">
            <button type="button" data-drawer-action="perfil">
                <i class="fa-solid fa-user"></i>
                <span>Meu perfil</span>
            </button>
            <button type="button" data-drawer-action="foto">
                <i class="fa-solid fa-camera"></i>
                <span>Alterar foto</span>
            </button>
            <button type="button" data-drawer-action="tema">
                <i class="fa-solid fa-moon" id="drawer-tema-icon"></i>
                <span id="drawer-tema-label">Tema escuro</span>
            </button>
            <button type="button" data-drawer-action="sync">
                <i class="fa-solid fa-rotate"></i>
                <span>Sincronizar agora</span>
                <span class="drawer-badge" id="drawer-fila-badge" hidden>0</span>
            </button>
            <hr class="drawer-sep">
            <button type="button" class="danger" data-drawer-action="logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sair</span>
            </button>
        </nav>

        <div class="drawer-footer">
            <span>Nutricional Rotas</span>
            <small>v<?= date('Ymd') ?></small>
        </div>
    </aside>
    <div class="driver-drawer-backdrop" id="driver-drawer-backdrop" hidden></div>

    <!-- ================================================================
         MODAL DE PERFIL
         ================================================================ -->
    <div class="driver-modal" id="perfil-modal" hidden>
        <div class="driver-modal-card perfil-modal-card">
            <div class="driver-modal-head">
                <div>
                    <span class="eyebrow"><i class="fa-solid fa-user"></i> Meu perfil</span>
                    <h2 id="perfil-modal-nome">-</h2>
                </div>
                <button type="button" class="modal-close" id="perfil-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="perfil-modal-body">
                <div class="perfil-row">
                    <span class="perfil-label">Usuário</span>
                    <strong id="perfil-username">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Email</span>
                    <strong id="perfil-email">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Telefone</span>
                    <strong id="perfil-fone">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Endereço</span>
                    <strong id="perfil-endereco">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Cidade/UF</span>
                    <strong id="perfil-cidade">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Cargo</span>
                    <strong id="perfil-cargo">-</strong>
                </div>
            </div>

            <div class="perfil-modal-foot">
                <button type="button" class="btn-perfil-fechar" id="perfil-modal-fechar-btn">Fechar</button>
            </div>
        </div>
    </div>

    <!-- INPUT DE FOTO (oculto) -->
    <input type="file" id="input-foto-perfil" accept="image/*" capture="user" hidden>
</main>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>