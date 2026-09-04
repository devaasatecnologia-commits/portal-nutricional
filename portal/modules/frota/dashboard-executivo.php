<?php
$pageTitle = 'Dashboard Executivo | Frota | Nutricional';
$version = time();
$extraCss = '
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="/portal/modules/frota/assets/dashboard-executivo.css?v=' . $version . '">
';
$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
';
require_once __DIR__ . '/../../estrutura/header.php';
?>

<main class="fleet-dashboard" id="dashboard-executivo">
    <header class="dashboard-hero">
        <div>
            <span class="dashboard-kicker"><i class="fa-solid fa-chart-line"></i> Gestão executiva</span>
            <h1>Operação de Frota</h1>
            <p>Visão consolidada da distribuição, desempenho e exceções operacionais.</p>
        </div>
        <div class="dashboard-actions">
            <span class="last-update" id="ultima-atualizacao">Atualizando...</span>
            <button class="icon-button" id="btn-exportar" type="button" title="Exportar snapshot CSV" aria-label="Exportar snapshot CSV">
                <i class="fa-solid fa-download"></i>
            </button>
            <button class="icon-button" id="btn-atualizar" type="button" title="Atualizar dashboard" aria-label="Atualizar dashboard">
                <i class="fa-solid fa-rotate-right"></i>
            </button>
        </div>
    </header>

    <section class="kpi-grid" aria-label="Indicadores principais">
        <article class="kpi-card kpi-green"><i class="fa-solid fa-box-open"></i><div><span>Entregas hoje</span><strong id="kpi-entregas">--</strong><small id="kpi-entregas-sub">carregando</small></div></article>
        <article class="kpi-card kpi-blue"><i class="fa-solid fa-route"></i><div><span>Motoristas em rota</span><strong id="kpi-motoristas">--</strong><small id="kpi-motoristas-sub">carregando</small></div></article>
        <article class="kpi-card kpi-orange"><i class="fa-solid fa-triangle-exclamation"></i><div><span>Problemas pendentes</span><strong id="kpi-problemas">--</strong><small id="kpi-problemas-sub">carregando</small></div></article>
        <article class="kpi-card kpi-purple"><i class="fa-solid fa-clipboard-check"></i><div><span>Taxa de acerto</span><strong id="kpi-acerto">--</strong><small id="kpi-acerto-sub">embarques finalizados</small></div></article>
    </section>

    <section class="dashboard-grid charts-grid">
        <article class="dashboard-panel chart-panel"><div class="panel-heading"><div><span class="panel-label">Ritmo operacional</span><h2>Entregas nos últimos 7 dias</h2></div><i class="fa-solid fa-chart-area"></i></div><div class="chart-wrap"><canvas id="grafico-entregas"></canvas></div></article>
        <article class="dashboard-panel chart-panel"><div class="panel-heading"><div><span class="panel-label">Distribuição</span><h2>Status das entregas</h2></div><i class="fa-solid fa-chart-pie"></i></div><div class="chart-wrap chart-wrap-small"><canvas id="grafico-status"></canvas></div></article>
    </section>

    <section class="dashboard-grid bottom-grid">
        <article class="dashboard-panel map-panel"><div class="panel-heading"><div><span class="panel-label">Telemetria</span><h2>Mapa de calor da operação</h2></div><i class="fa-solid fa-map-location-dot"></i></div><div id="mapa-frota" class="fleet-map" aria-label="Mapa de calor da operação"></div></article>
        <article class="dashboard-panel ranking-panel"><div class="panel-heading"><div><span class="panel-label">Performance</span><h2>Ranking de motoristas</h2></div><i class="fa-solid fa-ranking-star"></i></div><div id="ranking-motoristas" class="ranking-list"><div class="empty-state">Carregando ranking...</div></div></article>
    </section>

    <section class="dashboard-grid detail-grid">
        <article class="dashboard-panel"><div class="panel-heading"><div><span class="panel-label">Atenção imediata</span><h2>Alertas operacionais</h2></div><i class="fa-solid fa-bell"></i></div><div id="lista-alertas" class="alert-list"><div class="empty-state">Carregando alertas...</div></div></article>
        <article class="dashboard-panel"><div class="panel-heading"><div><span class="panel-label">Acompanhamento</span><h2>Entregas de hoje</h2></div><i class="fa-solid fa-truck-fast"></i></div><div id="lista-entregas" class="delivery-list"><div class="empty-state">Carregando entregas...</div></div></article>
    </section>
</main>
<script src="/portal/modules/frota/assets/dashboard-executivo.js?v=<?= $version ?>"></script>
