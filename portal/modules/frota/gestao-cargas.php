<?php
// ======================================================================
// MODULO FROTA - GESTAO DE CARGAS (PAINEL DO GESTOR)
// ======================================================================
// 🔥 REFATORADO 2026-09-22 (Bloco 5.A + 5.B.1):
//   - 5 abas: Dashboard / Problemas / Eficiência / Análises / Mapa
//   - Aba Dashboard preenchida (6 KPIs + 3 gráficos + 2 destaques)
//   - Aba Mapa com 3 sub-abas: Ao Vivo / Histórico / Calor
//   - Painéis antigos removidos (motoristas, veiculos, historico, cobli)
//     foram absorvidos por Eficiência/Mapa
// ======================================================================

$pageTitle = 'Gestão de Cargas | Frota | Nutricional';
$version = time();
// Mesmo cálculo de base usado em header.php/asset(), necessário aqui porque
// $extraCss/$extraJs são strings HTML cruas (não passam pela função asset()).
$assetBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') === 0) ? '/API' : '';

// ================================================================
// HEADER E CSS
// ================================================================
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="' . $assetBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/frota.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/acerto-embarque.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/gestao-cargas.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/cadastro-frota.css?v=' . $version . '">
';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script src="' . $assetBase . '/portal/modules/frota/assets/gestao-cargas.js?v=' . $version . '"></script>
';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<!-- CONTEÚDO DO MÓDULO -->
<div class="max-w-full mx-auto px-4 lg:px-6 py-4" style="background: var(--nutri-bg); min-height: 100vh;">
    
    <!-- HEADER -->
    <div class="bg-gradient-to-r from-[#1a3c34] to-[#2d5a4e] rounded-3xl p-6 lg:p-7 mb-6 shadow-xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5">
            <div class="flex items-center gap-4">
                <a href="<?= $assetBase ?>/portal/" class="flex w-10 h-10 rounded-xl items-center justify-center transition-colors no-underline bg-white/20 hover:bg-white/30" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div class="hero-icon-badge">
                    <i class="fa-solid fa-clipboard-list text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                        Gestão de Cargas
                        <span class="hero-live-dot" title="Atualizado em tempo real"></span>
                    </h1>
                    <p class="text-emerald-200/80 text-sm flex items-center gap-2 mt-0.5">
                        Análise de entregas, problemas e performance dos motoristas
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <div class="hero-stat-chip highlight">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="total-problemas">0</span>
                    <span class="hero-stat-label">problemas</span>
                </div>
                <div class="hero-stat-chip">
                    <i class="fa-solid fa-check-circle"></i>
                    <span id="total-resolvidos">0</span>
                    <span class="hero-stat-label">resolvidos</span>
                </div>
                <a href="<?= $assetBase ?>/portal/modules/frota/cadastro-frota.php" class="hero-refresh-btn" title="Cadastro de Frota (veículos e motoristas)">
                    <i class="fa-solid fa-id-card-clip"></i>
                </a>
                <button class="hero-refresh-btn" onclick="carregarDados()" title="Atualizar dados">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button class="theme-toggle theme-toggle-inline" onclick="toggleTheme()" title="Alternar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
        <div class="gold-accent-line"></div>
    </div>

    <!-- ================================================================
       ABAS PRINCIPAIS (5 abas)
       - Dashboard: visão executiva do dia (KPIs + gráficos + destaques)
       - Problemas: lista de problemas de entrega
       - Eficiência: ranking unificado (motoristas ↔ veículos)
       - Análises: gráficos e séries temporais
       - Mapa: rastreio (ao vivo + histórico + calor)
    ================================================================ -->
    <div class="cargas-tabs" id="cargas-tabs" role="tablist">
        <button type="button" class="cargas-tab" data-tab="dashboard" onclick="mudarAbaCargas('dashboard', this)" role="tab" aria-selected="false">
            <i class="fa-solid fa-chart-line"></i> Dashboard
        </button>
        <button type="button" class="cargas-tab active" data-tab="problemas" onclick="mudarAbaCargas('problemas', this)" role="tab" aria-selected="true">
            <i class="fa-solid fa-triangle-exclamation"></i> Problemas
        </button>
        <button type="button" class="cargas-tab" data-tab="eficiencia" onclick="mudarAbaCargas('eficiencia', this)" role="tab" aria-selected="false">
            <i class="fa-solid fa-ranking-star"></i> Eficiência
        </button>
        <button type="button" class="cargas-tab" data-tab="analises" onclick="mudarAbaCargas('analises', this)" role="tab" aria-selected="false">
            <i class="fa-solid fa-chart-pie"></i> Análises
        </button>
        <button type="button" class="cargas-tab" data-tab="mapa" onclick="mudarAbaCargas('mapa', this)" role="tab" aria-selected="false">
            <i class="fa-solid fa-satellite-dish"></i> Mapa
        </button>
    </div>

    <!-- ================================================================
       ABA: DASHBOARD
       🔥 IMPLEMENTADO 2026-09-22 (Bloco 5.B.1)
       Layout:
         - Linha 1: 6 KPIs compactos
         - Linha 2: 3 gráficos (ritmo 7d + status + ritmo mensal)
         - Linha 3: Top 5 motoristas + últimos 5 embarques
       Todos os cards são clicáveis (rastreabilidade).
    ================================================================ -->
    <div class="cargas-tab-panel" id="tab-dashboard" role="tabpanel" hidden>

        <!-- ============================================================
             LINHA 1 — 6 KPIs COMPACTOS
             ============================================================ -->
        <div class="cargas-kpi-grid" id="dash-kpis-grid">
            <!-- KPI 1: Entregas hoje -->
            <div class="cargas-kpi-card green" data-kpi="entregas"
                 onclick="dashIrPara('problemas')"
                 title="Ver entregas na aba Problemas">
                <div class="cargas-kpi-icon"><i class="fa-solid fa-box-open"></i></div>
                <div class="cargas-kpi-body">
                    <strong id="dash-kpi-entregas">--</strong>
                    <span>Entregas hoje</span>
                    <small id="dash-kpi-entregas-sub">carregando</small>
                </div>
            </div>

            <!-- KPI 2: Motoristas em rota -->
            <div class="cargas-kpi-card blue" data-kpi="motoristas"
                 onclick="dashIrPara('eficiencia')"
                 title="Ver ranking em Eficiência">
                <div class="cargas-kpi-icon"><i class="fa-solid fa-route"></i></div>
                <div class="cargas-kpi-body">
                    <strong id="dash-kpi-motoristas">--</strong>
                    <span>Motoristas em rota</span>
                    <small id="dash-kpi-motoristas-sub">carregando</small>
                </div>
            </div>

            <!-- KPI 3: Problemas pendentes -->
            <div class="cargas-kpi-card orange" data-kpi="problemas"
                 onclick="dashIrPara('problemas')"
                 title="Ver problemas pendentes">
                <div class="cargas-kpi-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="cargas-kpi-body">
                    <strong id="dash-kpi-problemas">--</strong>
                    <span>Problemas pendentes</span>
                    <small id="dash-kpi-problemas-sub">carregando</small>
                </div>
            </div>

            <!-- KPI 4: Faltantes / Devoluções -->
            <div class="cargas-kpi-card red" data-kpi="acerto"
                 onclick="dashAbrirModalAcerto()"
                 title="Ver detalhes de faltantes e devoluções">
                <div class="cargas-kpi-icon"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                <div class="cargas-kpi-body">
                    <strong id="dash-kpi-acerto">--</strong>
                    <span>Faltantes / Devoluções</span>
                    <small id="dash-kpi-acerto-sub">carregando</small>
                </div>
            </div>

            <!-- KPI 5: Taxa de acerto -->
            <div class="cargas-kpi-card purple" data-kpi="taxa"
                 onclick="dashIrPara('analises')"
                 title="Ver gráficos em Análises">
                <div class="cargas-kpi-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                <div class="cargas-kpi-body">
                    <strong id="dash-kpi-taxa">--</strong>
                    <span>Taxa de acerto</span>
                    <small id="dash-kpi-taxa-sub">carregando</small>
                </div>
            </div>

            <!-- KPI 6: Faturamento do mês -->
            <div class="cargas-kpi-card gold" data-kpi="faturamento"
                 onclick="dashIrPara('analises')"
                 title="Ver evolução em Análises">
                <div class="cargas-kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="cargas-kpi-body">
                    <strong id="dash-kpi-faturamento">--</strong>
                    <span>Faturamento do mês</span>
                    <small id="dash-kpi-faturamento-sub">carregando</small>
                </div>
            </div>
        </div>

        <!-- ============================================================
             LINHA 2 — 3 GRÁFICOS
             ============================================================ -->
        <div class="cargas-analise-grid mb-4">
            <div class="cargas-analise-card full">
                <h3>
                    <i class="fa-solid fa-chart-line" style="color:var(--nutri-accent); margin-right:6px;"></i>
                    Ritmo de entregas — últimos 7 dias
                </h3>
                <div class="analise-chart-wrap">
                    <canvas id="dash-chart-entregas"></canvas>
                </div>
            </div>
            <div class="cargas-analise-card">
                <h3>
                    <i class="fa-solid fa-chart-pie" style="color:var(--nutri-accent); margin-right:6px;"></i>
                    Status das entregas
                </h3>
                <div class="analise-chart-wrap">
                    <canvas id="dash-chart-status"></canvas>
                </div>
            </div>
            <div class="cargas-analise-card">
                <h3>
                    <i class="fa-solid fa-truck-fast" style="color:var(--nutri-accent); margin-right:6px;"></i>
                    Ritmo do mês
                </h3>
                <div class="analise-chart-wrap">
                    <canvas id="dash-chart-mes"></canvas>
                </div>
            </div>
        </div>

        <!-- ============================================================
             LINHA 3 — TOP 5 MOTORISTAS + ÚLTIMOS 5 EMBARQUES
             ============================================================ -->
        <div class="cargas-analise-grid">
            <!-- Top 5 motoristas -->
            <div class="cargas-analise-card">
                <h3>
                    <i class="fa-solid fa-ranking-star" style="color:var(--nutri-gold); margin-right:6px;"></i>
                    Top 5 motoristas (30 dias)
                </h3>
                <div id="dash-top-motoristas" class="cargas-ranking-list">
                    <div class="cargas-em-construcao-mini">Carregando...</div>
                </div>
            </div>

            <!-- Últimos 5 embarques -->
            <div class="cargas-analise-card">
                <h3>
                    <i class="fa-solid fa-clock-rotate-left" style="color:var(--nutri-accent); margin-right:6px;"></i>
                    Últimos embarques
                    <a href="javascript:void(0)" onclick="dashIrPara('mapa')"
                       style="float:right; font-size:0.75rem; font-weight:700; color:var(--nutri-accent); text-decoration:none;">
                        Ver histórico completo →
                    </a>
                </h3>
                <div id="dash-ultimos-embarques" class="cargas-ultimos-list">
                    <div class="cargas-em-construcao-mini">Carregando...</div>
                </div>
            </div>
        </div>

    </div> <!-- /#tab-dashboard -->

    <!-- ================================================================
       ABA: PROBLEMAS (ex-"Visão Geral")
    ================================================================ -->
    <div class="cargas-tab-panel" id="tab-problemas" role="tabpanel" hidden>

        <!-- ================================================================
           FILTROS RÁPIDOS
        ================================================================ -->
        <div class="quick-filters" id="quick-filters">
            <button type="button" class="quick-filter-pill active" data-filtro="todos" onclick="aplicarFiltro('todos', this)">
                <i class="fa-solid fa-layer-group"></i> Todos
            </button>
            <button type="button" class="quick-filter-pill" data-filtro="pendente" onclick="aplicarFiltro('pendente', this)">
                <i class="fa-regular fa-clock"></i> Pendentes
            </button>
            <button type="button" class="quick-filter-pill" data-filtro="em_analise" onclick="aplicarFiltro('em_analise', this)">
                <i class="fa-solid fa-magnifying-glass"></i> Em Análise
            </button>
            <button type="button" class="quick-filter-pill" data-filtro="resolvido" onclick="aplicarFiltro('resolvido', this)">
                <i class="fa-solid fa-check-circle"></i> Resolvidos
            </button>
            <button type="button" class="quick-filter-pill" data-filtro="cancelado" onclick="aplicarFiltro('cancelado', this)">
                <i class="fa-solid fa-ban"></i> Cancelados
            </button>
        </div>

        <div class="cargas-filter-bar" role="search" aria-label="Filtrar problemas de entrega">
            <label class="cargas-search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <input type="search" id="filtro-busca" placeholder="Buscar entrega, cliente ou motorista" autocomplete="off" aria-label="Buscar problemas">
            </label>
            <label class="cargas-priority">
                <span>Prioridade</span>
                <select id="filtro-prioridade" aria-label="Filtrar por prioridade">
                    <option value="todas">Todas</option>
                    <option value="critica">Crítica</option>
                    <option value="alta">Alta</option>
                    <option value="media">Média</option>
                    <option value="baixa">Baixa</option>
                </select>
            </label>
            <button type="button" class="cargas-clear-filter" id="limpar-filtros" hidden>
                <i class="fa-solid fa-rotate-left" aria-hidden="true"></i> Limpar filtros
            </button>
        </div>

        <!-- ================================================================
           KPI CARDS
        ================================================================ -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="kpi-cards">
            <!-- Gerado via JavaScript -->
        </div>

        <div class="section-card operational-overview mb-6">
            <div class="section-header flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="section-icon-badge"><i class="fa-solid fa-gauge-high"></i></div>
                    <div><span class="font-bold">Visão operacional</span><span class="text-xs text-slate-400 block">Acompanhamento da operação em tempo real</span></div>
                </div>
                <span class="live-caption"><span></span> Atualizado automaticamente</span>
            </div>
            <div class="operational-kpis" id="operational-kpis"><div class="operational-loading">Carregando indicadores...</div></div>
        </div>

        <!-- ================================================================
           TABELA DE PROBLEMAS
        ================================================================ -->
        <div class="section-card">
            <div class="section-header flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="section-icon-badge">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <span class="font-bold text-[#1a3c34]">Problemas de Entregas</span>
                        <span class="text-xs text-slate-400 block" id="info-registros">Carregando...</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
                    <button class="btn-secondary-nutri text-sm py-1.5 px-4" onclick="exportarCSV()">
                        <i class="fa-solid fa-file-export"></i> Exportar CSV
                    </button>
                </div>
            </div>
            <div class="section-body p-0 overflow-x-auto">
                <table class="table-frota w-full" id="tabela-problemas">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 45px;">#</th>
                            <th>Entrega</th>
                            <th>Cliente</th>
                            <th>Motorista</th>
                            <th>Problema</th>
                            <th class="text-center">Qtd</th>
                            <th class="text-center">Valor</th>
                            <th class="text-center">Prioridade</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="lista-problemas">
                        <tr>
                            <td colspan="10" class="text-center py-8">
                                <div class="flex flex-col items-center gap-2">
                                    <div class="skeleton skeleton-title mx-auto"></div>
                                    <div class="skeleton skeleton-text w-48 mx-auto"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- Paginação -->
            <div class="section-body border-t border-slate-200 flex justify-between items-center flex-wrap gap-2 py-3 px-4">
                <span class="text-sm text-slate-500" id="info-paginacao">Carregando...</span>
                <div class="flex gap-1">
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50" 
                            id="btn-anterior" onclick="mudarPagina('anterior')">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="px-3 py-1.5 text-sm font-bold text-slate-600" id="pagina-atual">1</span>
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50" 
                            id="btn-proximo" onclick="mudarPagina('proximo')">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div> <!-- /#tab-problemas -->

    <!-- ================================================================
       ABA: EFICIÊNCIA (motorista ↔ veículo com toggle)
       Conteúdo será preenchido no Bloco 5.C
    ================================================================ -->
    <div class="cargas-tab-panel" id="tab-eficiencia" role="tabpanel" hidden>
        <div class="section-card">
            <div class="section-body" style="text-align:center; padding:60px 20px;">
                <i class="fa-solid fa-ranking-star" style="font-size:2.5rem; color:var(--nutri-accent); opacity:0.4;"></i>
                <p style="margin-top:12px; color:var(--nutri-text-secondary);">
                    Ranking unificado (Motoristas ↔ Veículos) em construção — chega no Bloco 5.C.
                </p>
            </div>
        </div>
    </div>

    <!-- ================================================================
       ABA: ANÁLISES (ex-"Gráficos")
    ================================================================ -->
    <div class="cargas-tab-panel" id="tab-analises" role="tabpanel" hidden>
        <div class="section-card mb-6">
            <div class="section-header flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="section-icon-badge"><i class="fa-solid fa-chart-pie"></i></div>
                    <div><span class="font-bold text-[#1a3c34]">Painel de Gráficos</span></div>
                </div>
                <label class="cargas-priority">
                    <span>Período</span>
                    <select id="filtro-graficos-dias">
                        <option value="7">7 dias</option>
                        <option value="14" selected>14 dias</option>
                        <option value="30">30 dias</option>
                        <option value="90">90 dias</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="graficos-grid">
            <div class="section-card grafico-card grafico-full">
                <div class="section-header"><div class="flex items-center gap-3"><div class="section-icon-badge"><i class="fa-solid fa-chart-line"></i></div><span class="font-bold text-[#1a3c34]">Evolução de Problemas: Criados x Resolvidos</span></div></div>
                <div class="section-body"><canvas id="chart-evolucao" height="90"></canvas></div>
            </div>
            <div class="section-card grafico-card">
                <div class="section-header"><div class="flex items-center gap-3"><div class="section-icon-badge"><i class="fa-solid fa-chart-pie"></i></div><span class="font-bold text-[#1a3c34]">Distribuição por Tipo</span></div></div>
                <div class="section-body"><canvas id="chart-tipo" height="220"></canvas></div>
            </div>
            <div class="section-card grafico-card">
                <div class="section-header"><div class="flex items-center gap-3"><div class="section-icon-badge"><i class="fa-solid fa-layer-group"></i></div><span class="font-bold text-[#1a3c34]">Distribuição por Prioridade</span></div></div>
                <div class="section-body"><canvas id="chart-prioridade" height="220"></canvas></div>
            </div>
            <div class="section-card grafico-card">
                <div class="section-header"><div class="flex items-center gap-3"><div class="section-icon-badge"><i class="fa-solid fa-ranking-star"></i></div><span class="font-bold text-[#1a3c34]">Top 5 Motoristas com Mais Problemas</span></div></div>
                <div class="section-body"><canvas id="chart-top-motoristas" height="220"></canvas></div>
            </div>
            <div class="section-card grafico-card">
                <div class="section-header"><div class="flex items-center gap-3"><div class="section-icon-badge"><i class="fa-solid fa-truck"></i></div><span class="font-bold text-[#1a3c34]">Top 5 Caminhões com Mais Problemas</span></div></div>
                <div class="section-body"><canvas id="chart-top-veiculos" height="220"></canvas></div>
            </div>
        </div>
    </div> <!-- /#tab-analises -->

    <!-- ================================================================
       ABA: MAPA (Ao Vivo + Histórico + Calor)
       Conteúdo será preenchido no Bloco 5.D
    ================================================================ -->
    <div class="cargas-tab-panel" id="tab-mapa" role="tabpanel" hidden>
        <!-- Sub-abas internas -->
        <div class="cargas-subtabs" id="cargas-subtabs" role="tablist">
            <button type="button" class="cargas-subtab active" data-subtab="ao-vivo"
                    onclick="mudarSubAbaCargas('ao-vivo', this)" role="tab" aria-selected="true">
                <i class="fa-solid fa-map-location-dot"></i> Ao Vivo
            </button>
            <button type="button" class="cargas-subtab" data-subtab="historico"
                    onclick="mudarSubAbaCargas('historico', this)" role="tab" aria-selected="false">
                <i class="fa-solid fa-clock-rotate-left"></i> Histórico
            </button>
            <button type="button" class="cargas-subtab" data-subtab="calor"
                    onclick="mudarSubAbaCargas('calor', this)" role="tab" aria-selected="false">
                <i class="fa-solid fa-fire"></i> Calor
            </button>
        </div>

        <!-- Sub-painel: Ao Vivo -->
        <div class="cargas-subpanel" id="subtab-ao-vivo" role="tabpanel">
            <div class="section-card">
                <div class="section-body" style="text-align:center; padding:60px 20px;">
                    <i class="fa-solid fa-satellite-dish" style="font-size:2.5rem; color:var(--nutri-accent); opacity:0.4;"></i>
                    <p style="margin-top:12px; color:var(--nutri-text-secondary);">
                        Mapa ao vivo em construção — chega no Bloco 5.D.
                    </p>
                </div>
            </div>
        </div>

        <!-- Sub-painel: Histórico -->
        <div class="cargas-subpanel" id="subtab-historico" role="tabpanel" hidden>
            <div class="section-card">
                <div class="section-body" style="text-align:center; padding:60px 20px;">
                    <i class="fa-solid fa-clock-rotate-left" style="font-size:2.5rem; color:var(--nutri-accent); opacity:0.4;"></i>
                    <p style="margin-top:12px; color:var(--nutri-text-secondary);">
                        Histórico de embarques em construção — chega no Bloco 5.D.
                    </p>
                </div>
            </div>
        </div>

        <!-- Sub-painel: Calor -->
        <div class="cargas-subpanel" id="subtab-calor" role="tabpanel" hidden>
            <div class="section-card">
                <div class="section-body" style="text-align:center; padding:60px 20px;">
                    <i class="fa-solid fa-fire" style="font-size:2.5rem; color:var(--nutri-accent); opacity:0.4;"></i>
                    <p style="margin-top:12px; color:var(--nutri-text-secondary);">
                        Mapa de calor em construção — chega no Bloco 5.D.
                    </p>
                </div>
            </div>
        </div>
    </div> <!-- /#tab-mapa -->
</div>


<!-- ================================================================
   MODAL: DETALHE DO EMBARQUE (HISTÓRICO)
=============================================================== -->
<div class="modal fade" id="modalDetalheEmbarque" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-truck-fast mr-2"></i> Detalhe do Embarque
                    <span id="detalhe-embarque-numero" class="font-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhe-embarque-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   MODAL: DETALHE DO MOTORISTA / VEÍCULO (Ranking)
=============================================================== -->
<div class="modal fade" id="modalDetalheRanking" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-circle-info mr-2"></i>
                    <span id="detalhe-ranking-titulo">Detalhes</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhe-ranking-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   MODAL: ANÁLISE DA ENTREGA
=============================================================== -->
<div class="modal fade" id="modalAnalise" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-clipboard-check mr-2"></i> Análise da Entrega 
                    <span id="analise-numero" class="font-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="analise-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-success-nutri" onclick="resolverProblema()" id="btn-resolver-problema">
                    <i class="fa-solid fa-check mr-2"></i> Resolver Problema
                </button>
                <button type="button" class="btn-primary-nutri" onclick="adicionarAnalise()" id="btn-adicionar-analise">
                    <i class="fa-solid fa-pen mr-2"></i> Adicionar Análise
                </button>
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../estrutura/footer.php';
?>