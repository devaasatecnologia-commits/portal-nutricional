<?php
$pageTitle = 'Dashboard Marketing | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="/portal/assets/js/marketing-utils.js?v=' . $version . '"></script>
<style>
:root {
    --primary: #0f172a;
    --secondary: #1e293b;
    --accent: #10b981;
    --accent-light: #34d399;
    --danger: #ef4444;
    --warning: #f59e0b;
    --info: #3b82f6;
    --purple: #8b5cf6;
    --rose: #f43f5e;
    --teal: #14b8a6;
    --indigo: #4f46e5;
    --cyan: #06b6d4;
}

* { font-family: "Inter", sans-serif; }

/* ========== KPI CARDS ELEGANTES ========== */
.kpi-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
    border: 1px solid rgba(226, 232, 240, 0.6);
    border-radius: 16px;
    padding: 18px 20px;
    background: white;
    position: relative;
    overflow: hidden;
}
.kpi-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    border-radius: 16px 16px 0 0;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.08);
}
.kpi-card .kpi-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}
.kpi-card .kpi-value {
    font-size: 1.6rem;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1.2;
}
.kpi-card .kpi-label {
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
}
.kpi-card .kpi-sub {
    font-size: 0.6rem;
    color: #94a3b8;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 2px;
}
.kpi-card .kpi-sub .tag {
    background: #f1f5f9;
    padding: 1px 8px;
    border-radius: 999px;
    font-weight: 500;
}
.kpi-card .kpi-sub .tag-erp { background: #fef3c7; color: #d97706; }
.kpi-card .kpi-sub .tag-crm { background: #dbeafe; color: #1d4ed8; }
.kpi-card .kpi-sub .tag-ambos { background: #ddd6fe; color: #6d28d9; }
.kpi-card .kpi-sub .tag-geral { background: #e2e8f0; color: #475569; }

.kpi-card.primary::before { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.kpi-card.success::before { background: linear-gradient(90deg, #10b981, #34d399); }
.kpi-card.warning::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.kpi-card.danger::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.kpi-card.purple::before { background: linear-gradient(90deg, #8b5cf6, #a78bfa); }
.kpi-card.teal::before { background: linear-gradient(90deg, #14b8a6, #2dd4bf); }
.kpi-card.indigo::before { background: linear-gradient(90deg, #4f46e5, #818cf8); }
.kpi-card.cyan::before { background: linear-gradient(90deg, #06b6d4, #22d3ee); }
.kpi-card.rose::before { background: linear-gradient(90deg, #f43f5e, #fb7185); }

/* ========== COMPARATIVO ========== */
.comparativo-card {
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 20px;
    transition: all 0.3s ease;
    background: white;
}
.comparativo-card:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.comparativo-bar {
    height: 8px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
    margin-top: 6px;
}
.comparativo-bar .fill {
    height: 100%;
    border-radius: 999px;
    transition: width 1s ease;
}

/* ========== META CARD ========== */
.meta-card {
    transition: all 0.2s ease;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    background: white;
    cursor: pointer;
}
.meta-card:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}
.meta-card .meta-progress {
    height: 6px;
    border-radius: 999px;
    background: #e2e8f0;
    overflow: hidden;
}
.meta-card .meta-progress .fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.8s ease;
}

/* ========== COMPRADOR ROW ========== */
.comprador-row {
    transition: all 0.15s ease;
    cursor: pointer;
    padding: 10px 14px;
    border-radius: 10px;
}
.comprador-row:hover {
    background: #f1f5f9;
}

/* ========== BADGES ========== */
.badge-origem {
    font-size: 0.55rem;
    padding: 2px 10px;
    border-radius: 999px;
    font-weight: 600;
}
.badge-crm { background: #dbeafe; color: #1d4ed8; }
.badge-ambos { background: #ddd6fe; color: #6d28d9; }
.badge-erp { background: #fef3c7; color: #d97706; }
.badge-mes { background: #dcfce7; color: #16a34a; }
.badge-nunca { background: #fee2e2; color: #dc2626; }

/* ========== INSIGHTS ========== */
.insight-card {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px 16px;
    background: white;
}
.insight-card .insight-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ========== ANIMACOES ========== */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.fade-in-up {
    animation: fadeInUp 0.5s ease forwards;
}

.clock {
    font-family: "Chivo Mono", monospace;
    font-weight: 700;
}
.btn-voltar {
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
';
require_once __DIR__ . '/../../estrutura/header.php';
?>

<div class="max-w-full mx-auto px-4 lg:px-6 py-4" id="dashboardApp">

    <!-- HEADER -->
    <div class="rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-[#375a4b] to-[#4a7a67] rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-chart-line text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">DASHBOARD MARKETING</h2>
                <span class="text-xs text-slate-400 font-medium">Visao Consolidada de Resultados</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/portal/modules/marketing/clientes.php" class="px-4 py-2 bg-violet-500 text-white rounded-xl text-sm font-medium hover:bg-violet-600 transition-all">
                <i class="fa-solid fa-users mr-2"></i>CRM
            </a>
            <a href="/portal/modules/marketing/metas.php" class="px-4 py-2 bg-emerald-500 text-white rounded-xl text-sm font-medium hover:bg-emerald-600 transition-all">
                <i class="fa-solid fa-bullseye mr-2"></i>Metas
            </a>
            <div class="text-right hidden sm:block">
                <div class="clock font-mono text-base lg:text-xl font-black text-slate-700" id="relogio">00:00:00</div>
                <div class="data-topo text-[10px] lg:text-xs text-slate-400" id="data-topo">--/--/----</div>
            </div>
        </div>
    </div>

    <!-- KPIs - 6 CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="kpi-card indigo">
            <div class="flex items-center justify-between mb-1">
                <span class="kpi-label">Total Clientes</span>
                <div class="kpi-icon bg-indigo-50 text-indigo-600">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>
            <span class="kpi-value text-indigo-600" id="kpiTotalGeral">0</span>
            <div class="kpi-sub">
                <span class="tag tag-erp" id="kpiTotalERP">ERP: 0</span>
                <span class="tag tag-crm" id="kpiTotalCRM">CRM: 0</span>
                <span class="tag tag-ambos" id="kpiTotalAMBOS">AMBOS: 0</span>
            </div>
        </div>
        <div class="kpi-card success">
            <div class="flex items-center justify-between mb-1">
                <span class="kpi-label">Compradores Mes</span>
                <div class="kpi-icon bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-shopping-cart"></i>
                </div>
            </div>
            <span class="kpi-value text-emerald-600" id="kpiCompradoresMesGeral">0</span>
            <div class="kpi-sub">
                <span class="tag tag-erp" id="kpiCompradoresMesERP">ERP: 0</span>
                <span class="tag tag-crm" id="kpiCompradoresMesCRM">CRM: 0</span>
            </div>
        </div>
        <div class="kpi-card purple">
            <div class="flex items-center justify-between mb-1">
                <span class="kpi-label">Taxa Conversao</span>
                <div class="kpi-icon bg-purple-50 text-purple-600">
                    <i class="fa-solid fa-chart-simple"></i>
                </div>
            </div>
            <span class="kpi-value text-purple-600" id="kpiTaxaGeral">0%</span>
            <div class="kpi-sub">
                <span class="tag tag-erp" id="kpiTaxaERP">ERP: 0%</span>
                <span class="tag tag-crm" id="kpiTaxaCRM">CRM: 0%</span>
            </div>
        </div>
        <div class="kpi-card teal">
            <div class="flex items-center justify-between mb-1">
                <span class="kpi-label">Faturamento Mes</span>
                <div class="kpi-icon bg-teal-50 text-teal-600">
                    <i class="fa-solid fa-coins"></i>
                </div>
            </div>
            <span class="kpi-value text-teal-600" id="kpiFaturamentoMesCRM">R$ 0</span>
            <div class="kpi-sub">
                <span class="tag tag-erp" id="kpiFaturamentoMesERP">ERP: R$ 0</span>
            </div>
        </div>
        <div class="kpi-card warning">
            <div class="flex items-center justify-between mb-1">
                <span class="kpi-label">CRM + AMBOS</span>
                <div class="kpi-icon bg-amber-50 text-amber-600">
                    <i class="fa-solid fa-user-check"></i>
                </div>
            </div>
            <span class="kpi-value text-amber-600" id="kpiCRMAmbosCompradores">0</span>
            <div class="kpi-sub">
                <span class="tag tag-crm" id="kpiCRMAmbosPct">0% do total</span>
            </div>
        </div>
        <div class="kpi-card rose">
            <div class="flex items-center justify-between mb-1">
                <span class="kpi-label">Metas Ativas</span>
                <div class="kpi-icon bg-rose-50 text-rose-600">
                    <i class="fa-solid fa-bullseye"></i>
                </div>
            </div>
            <span class="kpi-value text-rose-600" id="kpiMetasAtivas">0</span>
            <div class="kpi-sub">
                <span class="tag tag-geral" id="kpiMetasProgresso">progresso: 0%</span>
            </div>
        </div>
    </div>

    <!-- INSIGHTS RAPIDOS -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="insight-card fade-in-up" style="animation-delay: 0.1s;">
            <div class="flex items-center gap-3">
                <div class="insight-icon bg-emerald-50 text-emerald-600">
                    <i class="fa-solid fa-arrow-up"></i>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Destaque do Mes</div>
                    <div class="font-bold text-sm text-slate-700" id="insightDestaque">Carregando...</div>
                </div>
            </div>
        </div>
        <div class="insight-card fade-in-up" style="animation-delay: 0.2s;">
            <div class="flex items-center gap-3">
                <div class="insight-icon bg-rose-50 text-rose-600">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Precisa Atencao</div>
                    <div class="font-bold text-sm text-slate-700" id="insightAtencao">Carregando...</div>
                </div>
            </div>
        </div>
        <div class="insight-card fade-in-up" style="animation-delay: 0.3s;">
            <div class="flex items-center gap-3">
                <div class="insight-icon bg-blue-50 text-blue-600">
                    <i class="fa-solid fa-lightbulb"></i>
                </div>
                <div>
                    <div class="text-xs text-slate-400">Oportunidade</div>
                    <div class="font-bold text-sm text-slate-700" id="insightOportunidade">Carregando...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTRO RAPIDO -->
    <div class="bg-white rounded-2xl border border-slate-100 p-3 mb-6 flex flex-wrap items-center gap-3 shadow-sm">
        <div class="flex items-center gap-2">
            <i class="fa-solid fa-sliders text-slate-400 text-xs"></i>
            <span class="text-xs font-bold text-slate-400">FILTROS:</span>
        </div>
        <select id="filtroMeta" onchange="carregarDados()" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-emerald-400">
            <option value="">Todas as metas</option>
        </select>
        <select id="filtroSemana" onchange="carregarDados()" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm font-medium bg-white focus:ring-2 focus:ring-emerald-400">
            <option value="">Todas as semanas</option>
        </select>
    </div>

    <!-- GRAFICO - EVOLUCAO SEMANAL -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6" id="graficoSection">
        <div class="px-5 py-3 border-b border-slate-100 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-slate-700 text-sm">
                    <i class="fa-solid fa-chart-line mr-2 text-emerald-500"></i>Evolucao Semanal
                </h3>
                <span class="text-[10px] text-slate-400" id="metaGraficoTitulo">Todas as metas</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-[10px] text-slate-400" id="semanaValor">-</span>
            </div>
        </div>
        <div class="p-4">
            <div id="chartEvolucao" style="height: 320px;"></div>
        </div>
    </div>

    <!-- COMPARATIVO: COMPROU VS NAO COMPROU -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6" id="comparativoSection">
        <div class="px-5 py-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
            <h3 class="font-bold text-slate-700 text-sm">
                <i class="fa-solid fa-chart-pie mr-2 text-blue-500"></i>Comparativo: Compradores vs Nao Compradores
            </h3>
        </div>
        <div class="p-4 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- ERP -->
            <div class="comparativo-card" id="erpSection">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-building text-amber-600 text-sm"></i>
                    </div>
                    <span class="font-bold text-sm text-slate-700">ERP</span>
                    <span class="ml-auto text-xs bg-slate-100 px-2 py-0.5 rounded-full" id="totalERPClientesCard">0 clientes</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-100">
                        <span class="text-xs text-slate-400">Comprou (mes)</span>
                        <div class="text-2xl font-bold text-emerald-600" id="comprouERPQuantidade">0</div>
                        <div class="text-sm font-bold text-emerald-600" id="comprouERPValor">R$ 0</div>
                        <div class="comparativo-bar mt-2">
                            <div class="fill bg-emerald-500" id="barraComprouERP" style="width:0%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400" id="pctComprouERP">0%</span>
                    </div>
                    <div class="bg-rose-50 rounded-xl p-4 text-center border border-rose-100">
                        <span class="text-xs text-slate-400">Nunca comprou</span>
                        <div class="text-2xl font-bold text-rose-600" id="naoComprouERPQuantidade">0</div>
                        <div class="text-sm font-bold text-rose-600">R$ 0</div>
                        <div class="comparativo-bar mt-2">
                            <div class="fill bg-rose-500" id="barraNaoComprouERP" style="width:0%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400" id="pctNaoComprouERP">0%</span>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 flex justify-between text-xs text-slate-400">
                    <span>Taxa conversao: <strong class="text-emerald-600" id="taxaConversaoERP">0%</strong></span>
                    <span>Ticket medio: <strong class="text-blue-600" id="ticketMedioERP">R$ 0</strong></span>
                </div>
            </div>
            <!-- CRM + AMBOS -->
            <div class="comparativo-card" id="crmSection">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fa-solid fa-users text-purple-600 text-sm"></i>
                    </div>
                    <span class="font-bold text-sm text-slate-700">CRM + AMBOS</span>
                    <span class="ml-auto text-xs bg-slate-100 px-2 py-0.5 rounded-full" id="totalCRMClientesCard">0 clientes</span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="bg-emerald-50 rounded-xl p-4 text-center border border-emerald-100">
                        <span class="text-xs text-slate-400">Comprou</span>
                        <div class="text-2xl font-bold text-emerald-600" id="comprouCRMQuantidade">0</div>
                        <div class="text-sm font-bold text-emerald-600" id="comprouCRMValor">R$ 0</div>
                        <div class="comparativo-bar mt-2">
                            <div class="fill bg-emerald-500" id="barraComprouCRM" style="width:0%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400" id="pctComprouCRM">0%</span>
                    </div>
                    <div class="bg-rose-50 rounded-xl p-4 text-center border border-rose-100">
                        <span class="text-xs text-slate-400">Nao comprou</span>
                        <div class="text-2xl font-bold text-rose-600" id="naoComprouCRMQuantidade">0</div>
                        <div class="text-sm font-bold text-rose-600" id="naoComprouCRMValor">R$ 0</div>
                        <div class="comparativo-bar mt-2">
                            <div class="fill bg-rose-500" id="barraNaoComprouCRM" style="width:0%"></div>
                        </div>
                        <span class="text-[10px] text-slate-400" id="pctNaoComprouCRM">0%</span>
                    </div>
                </div>
                <div class="mt-3 pt-2 border-t border-slate-100 flex justify-between text-xs text-slate-400">
                    <span>Taxa conversao: <strong class="text-emerald-600" id="taxaConversaoCRM">0%</strong></span>
                    <span>Ticket medio: <strong class="text-blue-600" id="ticketMedioCRM">R$ 0</strong></span>
                </div>
            </div>
        </div>
    </div>

    <!-- METAS EM ANDAMENTO -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6" id="metasSection">
        <div class="px-5 py-3 border-b border-slate-100 flex justify-between items-center">
            <h3 class="font-bold text-slate-700 text-sm">
                <i class="fa-solid fa-bullseye mr-2 text-rose-500"></i>Metas em Andamento
            </h3>
            <span class="text-[10px] text-slate-400" id="totalMetasAtivas">0 ativas</span>
        </div>
        <div id="metasContainer" class="divide-y divide-slate-100 max-h-80 overflow-y-auto">
            <div class="p-6 text-center text-slate-400 text-sm">Carregando metas...</div>
        </div>
    </div>

    <!-- LISTAS DE COMPRADORES -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <!-- CRM + AMBOS -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-purple-50 to-white">
                <div>
                    <h3 class="font-bold text-slate-700 text-sm">
                        <i class="fa-solid fa-users mr-1 text-purple-500"></i>CRM + AMBOS
                    </h3>
                    <span class="text-[10px] text-slate-400">Ultimos compradores</span>
                </div>
                <span class="text-[10px] bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-bold" id="totalCompradoresCRMBadge">0</span>
            </div>
            <div id="compradoresCRMContainer" class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                <div class="p-6 text-center text-slate-400 text-sm">Carregando...</div>
            </div>
        </div>
        <!-- ERP - Compraram no Mes -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-emerald-50 to-white">
                <div>
                    <h3 class="font-bold text-slate-700 text-sm">
                        <i class="fa-solid fa-building mr-1 text-emerald-500"></i>ERP - Compraram no Mes
                    </h3>
                    <span class="text-[10px] text-slate-400">Ultimos compradores</span>
                </div>
                <span class="text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold" id="totalCompradoresERPBadge">0</span>
            </div>
            <div id="compradoresERPContainer" class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                <div class="p-6 text-center text-slate-400 text-sm">Carregando...</div>
            </div>
        </div>
        <!-- ERP - Nunca Compraram -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-rose-50 to-white">
                <div>
                    <h3 class="font-bold text-slate-700 text-sm">
                        <i class="fa-solid fa-user-slash mr-1 text-rose-500"></i>ERP - Nunca Compraram
                    </h3>
                    <span class="text-[10px] text-slate-400">Potenciais clientes</span>
                </div>
                <span class="text-[10px] bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full font-bold" id="totalNuncaERPBadge">0</span>
            </div>
            <div id="clientesNuncaERPContainer" class="divide-y divide-slate-100 max-h-64 overflow-y-auto">
                <div class="p-6 text-center text-slate-400 text-sm">Carregando...</div>
            </div>
        </div>
    </div>

    <!-- RESUMO RAPIDO -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
            <span class="text-xs text-slate-400">Meta destaque</span>
            <p class="text-sm font-bold text-slate-700 truncate" id="resumoDestaque">-</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
            <span class="text-xs text-slate-400">Precisa atencao</span>
            <p class="text-sm font-bold text-slate-700 truncate" id="resumoAtencao">-</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
            <span class="text-xs text-slate-400">Total compras mes</span>
            <p class="text-sm font-bold text-teal-600" id="resumoTotalCompras">R$ 0</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 p-3 text-center shadow-sm">
            <span class="text-xs text-slate-400">Ticket medio</span>
            <p class="text-sm font-bold text-blue-600" id="resumoTicketMedio">R$ 0</p>
        </div>
    </div>
</div>

<script>
// ============================================================================
// DEPENDENCIAS
// ============================================================================
if (typeof MarketingUtils === 'undefined') {
    console.error('MarketingUtils nao carregado!');
    location.reload();
}

// ============================================================================
// DASHBOARD PRINCIPAL - VERSAO OTIMIZADA (UMA UNICA CONSULTA)
// ============================================================================
(function() {
    'use strict';

    if (window.dashInicializado) return;
    window.dashInicializado = true;

    // ========================================================================
    // AUTENTICACAO E HELPERS
    // ========================================================================
    function getToken() {
        return localStorage.getItem('authToken');
    }

    function getHeaders() {
        return { 
            'Authorization': 'Bearer ' + getToken(), 
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache, no-store, must-revalidate',
            'Pragma': 'no-cache'
        };
    }

    async function fetchAuth(url, options = {}) {
        const token = getToken();
        if (!token) { 
            window.location.href = '/portal/login.php'; 
            return; 
        }
        const separator = url.includes('?') ? '&' : '?';
        const urlWithCache = url + separator + '_=' + Date.now();
        const resp = await fetch(urlWithCache, { 
            ...options, 
            headers: { 
                ...getHeaders(), 
                ...(options.headers || {}) 
            },
            cache: 'no-store'
        });
        if (resp.status === 401) {
            localStorage.removeItem('authToken');
            window.location.href = '/portal/login.php';
        }
        return resp;
    }

    function escapeHtml(text) {
        return MarketingUtils.escapeHtml(text);
    }

    function formatarMoeda(valor) {
        if (!valor || isNaN(valor)) return 'R$ 0,00';
        return 'R$ ' + parseFloat(valor).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatarNumero(valor) {
        if (!valor) return '0';
        return parseInt(valor).toLocaleString('pt-BR');
    }

    function formatarData(data) {
        if (!data) return '—';
        try {
            return new Date(data).toLocaleDateString('pt-BR');
        } catch(e) {
            return data;
        }
    }

    // ========================================================================
    // ESTADO
    // ========================================================================
    const state = {
        chartEvolucao: null,
        metas: [],
        compradoresCRM: [],
        compradoresERP: [],
        carregando: false
    };

    // ========================================================================
    // RELOGIO
    // ========================================================================
    function iniciarRelogio() {
        const atualizar = () => {
            const agora = new Date();
            const hora = agora.toLocaleTimeString('pt-BR');
            const data = agora.toLocaleDateString('pt-BR', { 
                weekday: 'long', 
                day: '2-digit', 
                month: 'long', 
                year: 'numeric' 
            });
            const relogio = document.getElementById('relogio');
            const dataTopo = document.getElementById('data-topo');
            if (relogio) relogio.innerText = hora;
            if (dataTopo) dataTopo.innerText = data;
        };
        atualizar();
        setInterval(atualizar, 1000);
    }

    // ========================================================================
    // RENDERIZAR LISTA CRM+AMBOS
    // ========================================================================
    function renderizarListaCRMAmbos(clientes) {
        const container = document.getElementById('compradoresCRMContainer');
        const badge = document.getElementById('totalCompradoresCRMBadge');
        if (!container) return;

        if (!clientes || !clientes.length) {
            container.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Nenhum comprador CRM + AMBOS</div>';
            if (badge) badge.textContent = '0';
            return;
        }

        container.innerHTML = clientes.slice(0, 10).map(c => {
            const nome = c.nome || '—';
            const empresa = c.empresa || '';
            const totalValor = c.total_compras || 0;
            const ultimaCompra = c.ultima_compra ? new Date(c.ultima_compra).toLocaleDateString('pt-BR') : '—';
            const origem = c.origem === 'AMBOS' ? 'AMBOS' : 'CRM';
            const classeOrigem = c.origem === 'AMBOS' ? 'badge-ambos' : 'badge-crm';
            const clienteId = c.id_crm || c.id_erp;
            return `
            <div class="comprador-row flex items-center justify-between" 
                 onclick="window.editarCliente(${clienteId}, '${origem}')">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm text-slate-800 truncate">${escapeHtml(nome)}</span>
                        <span class="badge-origem ${classeOrigem}">${origem}</span>
                    </div>
                    <div class="text-xs text-slate-400 truncate">${escapeHtml(empresa)}</div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <div class="text-sm font-bold text-emerald-600">${formatarMoeda(totalValor)}</div>
                    <div class="text-[10px] text-slate-400">${ultimaCompra}</div>
                </div>
            </div>
            `;
        }).join('');

        if (badge) badge.textContent = clientes.length;
    }

    // ========================================================================
    // RENDERIZAR CLIENTES ERP QUE NUNCA COMPRARAM
    // ========================================================================
    function renderizarClientesERPNunca(clientes) {
        const container = document.getElementById('clientesNuncaERPContainer');
        if (!container) return;
        if (!clientes || !clientes.length) {
            container.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Todos os clientes ERP ja compraram!</div>';
            return;
        }
        const badge = document.getElementById('totalNuncaERPBadge');
        if (badge) badge.innerText = clientes.length;
        container.innerHTML = clientes.map(c => {
            const nome = c.nome || '—';
            const empresa = c.empresa || '';
            const telefone = c.telefone || '';
            return `
            <div class="comprador-row flex items-center justify-between" 
                 onclick="window.editarCliente(${c.id_erp}, 'APENAS_ERP')">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-sm text-slate-800 truncate">${escapeHtml(nome)}</span>
                        <span class="badge-origem badge-nunca">Nunca comprou</span>
                    </div>
                    <div class="text-xs text-slate-400 truncate">${escapeHtml(empresa)} ${telefone ? '• ' + telefone : ''}</div>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <button onclick="event.stopPropagation(); window.editarCliente(${c.id_erp}, 'APENAS_ERP')" 
                            class="px-3 py-1 bg-violet-500 text-white rounded-lg text-xs font-bold hover:bg-violet-600 transition-all">
                        <i class="fa-solid fa-pen mr-1"></i> Ativar
                    </button>
                </div>
            </div>
            `;
        }).join('');
    }

    // ========================================================================
    // RENDERIZAR LISTAS DO DASHBOARD
    // ========================================================================
    function renderizarListasDashboard(listas) {
        // Compradores ERP
        const containerCompradores = document.getElementById('compradoresERPContainer');
        if (containerCompradores && listas.compradores) {
            if (listas.compradores.length === 0) {
                containerCompradores.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Nenhum comprador ERP neste mes</div>';
            } else {
                containerCompradores.innerHTML = listas.compradores.map(c => `
                    <div class="comprador-row flex items-center justify-between" 
                         onclick="window.editarCliente(${c.id_erp}, 'APENAS_ERP')">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm text-slate-800 truncate">${escapeHtml(c.nome || '—')}</span>
                                <span class="badge-origem badge-mes">Este mes</span>
                            </div>
                            <div class="text-xs text-slate-400 truncate">${escapeHtml(c.empresa || '')}</div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <div class="text-sm font-bold text-emerald-600">${formatarMoeda(c.total_compras || 0)}</div>
                            <div class="text-[10px] text-slate-400">${c.total_pedidos || 0} compras</div>
                        </div>
                    </div>
                `).join('');
            }
            document.getElementById('totalCompradoresERPBadge').textContent = listas.compradores.length;
        }

        // Nunca compraram
        const containerNunca = document.getElementById('clientesNuncaERPContainer');
        if (containerNunca && listas.nunca_compraram) {
            if (listas.nunca_compraram.length === 0) {
                containerNunca.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Todos os clientes ERP ja compraram!</div>';
            } else {
                containerNunca.innerHTML = listas.nunca_compraram.map(c => `
                    <div class="comprador-row flex items-center justify-between" 
                         onclick="window.editarCliente(${c.id_erp}, 'APENAS_ERP')">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-sm text-slate-800 truncate">${escapeHtml(c.nome || '—')}</span>
                                <span class="badge-origem badge-nunca">Nunca comprou</span>
                            </div>
                            <div class="text-xs text-slate-400 truncate">${escapeHtml(c.empresa || '')}</div>
                        </div>
                        <div class="text-right flex-shrink-0 ml-3">
                            <button onclick="event.stopPropagation(); window.editarCliente(${c.id_erp}, 'APENAS_ERP')" 
                                    class="px-3 py-1 bg-violet-500 text-white rounded-lg text-xs font-bold hover:bg-violet-600 transition-all">
                                <i class="fa-solid fa-pen mr-1"></i> Ativar
                            </button>
                        </div>
                    </div>
                `).join('');
            }
            document.getElementById('totalNuncaERPBadge').textContent = listas.nunca_compraram.length;
        }
    }

    // ========================================================================
    // RENDERIZAR METAS
    // ========================================================================
    function renderizarMetas(metas) {
        const container = document.getElementById('metasContainer');
        if (!container) return;
        
        document.getElementById('totalMetasAtivas').textContent = metas.length + ' ativas';
        
        if (!metas.length) {
            container.innerHTML = '<div class="p-6 text-center text-slate-400 text-sm">Nenhuma meta ativa</div>';
            return;
        }

        container.innerHTML = metas.map(m => {
            let pct = Math.max(0, Math.min(100, m.progresso_percentual || 0));
            const corBarra = pct >= 100 ? '#10b981' : pct >= 70 ? '#34d399' : pct >= 40 ? '#f59e0b' : '#ef4444';
            const status = pct >= 100 ? 'Concluida' : pct >= 70 ? 'Avancada' : pct >= 40 ? 'Em andamento' : 'Iniciando';
            const dias = m.dias_restantes || 0;
            const diasText = dias > 0 ? `${dias}d` : 'Vencida';
            const tituloEsc = escapeHtml(m.titulo || 'Sem titulo');
            const valorAtual = m.total_alcancado || 0;
            const metaFinal = m.meta_final || 0;

            return `
                <div class="meta-card fade-in" onclick="window.abrirAlimentacaoMeta(${m.id}, '${tituloEsc.replace(/'/g, "\\'")}')">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="font-bold text-slate-800 text-sm">${tituloEsc}</span>
                            <span class="ml-2 text-[10px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded-full">${m.tipo_nome || 'Meta'}</span>
                        </div>
                        <span class="text-xs font-bold ${pct >= 70 ? 'text-emerald-600' : pct >= 40 ? 'text-amber-600' : 'text-rose-600'}">${pct.toFixed(1)}%</span>
                    </div>
                    <div class="flex justify-between text-xs text-slate-400 mt-1">
                        <span>${formatarNumero(valorAtual)} / ${formatarNumero(metaFinal)}</span>
                        <span>${diasText}</span>
                    </div>
                    <div class="meta-progress mt-1.5">
                        <div class="fill" style="width:${Math.min(pct, 100)}%;background:${corBarra}"></div>
                    </div>
                    <div class="flex justify-between items-center mt-1.5">
                        <span class="text-[10px] text-slate-400">${status}</span>
                        <span class="text-[10px] text-slate-400">${dias > 0 ? dias + ' dias' : 'Vencida'}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    // ========================================================================
    // CARREGAR EVOLUCAO SEMANAL
    // ========================================================================
    async function carregarEvolucao(metaId, semana) {
        const cacheKey = `evolucao_${metaId || 'todas'}_${semana || 'todas'}`;
        const cacheTime = 60000;

        if (window._evolucaoCache && window._evolucaoCache[cacheKey]) {
            const cached = window._evolucaoCache[cacheKey];
            if (Date.now() - cached.timestamp < cacheTime) {
                console.log('Usando cache do grafico:', cacheKey);
                renderizarGraficoEvolucao(cached.semanas, cached.series);
                return;
            }
        }

        try {
            let metas = [];
            if (metaId) {
                const resp = await fetchAuth(`/v1/meta-builder/instancias/${metaId}`);
                const data = await resp.json();
                if (data.success && data.data) metas = [data.data];
            } else {
                const resp = await fetchAuth('/v1/meta-builder/instancias/ativas');
                const data = await resp.json();
                if (data.success && data.data) metas = data.data;
            }

            if (!metas.length) {
                document.getElementById('metaGraficoTitulo').innerText = 'Nenhuma meta ativa';
                return;
            }

            document.getElementById('metaGraficoTitulo').innerText = metaId ? metas[0].titulo : 'Todas as metas';

            let todasSemanas = [];
            const series = [];

            for (const m of metas) {
                if (m.status !== 'ativa' && m.status !== 'Ativa') continue;

                const respAlim = await fetchAuth(`/v1/meta-builder/alimentacao/${m.id}`);
                const alimData = await respAlim.json();
                const registros = alimData.data || [];

                let nomeVA = 'valor_alcancado';
                if (m.campos) {
                    const cva = m.campos.find(c => c.tipo_comparacao === 'valor_atual');
                    if (cva) nomeVA = cva.nome_campo;
                }

                const semanas = agruparPorSemana(registros, m.data_inicio, m.data_fim, nomeVA);
                let semanasFiltradas = semanas;
                if (semana && semana !== '') {
                    semanasFiltradas = semanas.filter(s => s.semana == semana);
                }

                semanasFiltradas.forEach(s => {
                    if (!todasSemanas.find(ts => ts.label === 'S' + s.semana)) {
                        todasSemanas.push({ label: 'S' + s.semana, periodo: s.periodo });
                    }
                });

                const titulo = m.titulo || 'Meta ' + m.id;
                const nomeCurto = titulo.length > 20 ? titulo.substring(0, 18) + '...' : titulo;
                series.push({
                    name: nomeCurto,
                    data: semanasFiltradas.map(s => s.valor),
                    type: 'line'
                });
            }

            todasSemanas.sort((a, b) => parseInt(a.label.replace('S', '')) - parseInt(b.label.replace('S', '')));

            if (todasSemanas.length > 0) {
                const ultima = todasSemanas[todasSemanas.length - 1];
                document.getElementById('semanaValor').innerText = semana ? `Semana ${semana}` : ultima.periodo || '-';
            }

            renderizarGraficoEvolucao(todasSemanas, series);

            if (window._evolucaoCache === undefined) {
                window._evolucaoCache = {};
            }
            window._evolucaoCache[cacheKey] = {
                semanas: todasSemanas,
                series: series,
                timestamp: Date.now()
            };

        } catch (e) {
            console.error('Erro ao carregar evolucao:', e);
        }
    }

    function agruparPorSemana(registros, dataInicio, dataFim, nomeCampoValor) {
        if (!registros || !registros.length) return [];
        const inicio = new Date(dataInicio || new Date());
        const fim = new Date(dataFim || new Date());
        const semanas = [];
        let semanaAtual = 1;
        let inicioSemana = new Date(inicio);
        while (inicioSemana <= fim) {
            const fimSemana = new Date(inicioSemana);
            fimSemana.setDate(fimSemana.getDate() + 6);
            if (fimSemana > fim) fimSemana.setTime(fim.getTime());
            const registrosSemana = registros.filter(r => {
                const dataReg = new Date(r.data_registro);
                return dataReg >= inicioSemana && dataReg <= fimSemana;
            });
            let valorTotal = 0;
            registrosSemana.forEach(r => {
                const vals = typeof r.valores === 'string' ? JSON.parse(r.valores) : (r.valores || {});
                valorTotal += parseFloat(vals[nomeCampoValor] || vals.valor_alcancado || 0);
            });
            semanas.push({
                semana: semanaAtual,
                periodo: `${inicioSemana.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'})} - ${fimSemana.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'})}`,
                valor: valorTotal,
                registros: registrosSemana.length
            });
            inicioSemana.setDate(inicioSemana.getDate() + 7);
            semanaAtual++;
        }
        return semanas;
    }

    function renderizarGraficoEvolucao(semanas, series) {
        const el = document.getElementById('chartEvolucao');
        if (!el) return;
        if (state.chartEvolucao) {
            state.chartEvolucao.destroy();
            state.chartEvolucao = null;
        }
        if (!semanas.length || !series.length || series.every(s => s.data.every(d => d === 0))) {
            el.innerHTML = `
            <div class="flex items-center justify-center h-64 text-slate-400">
                <div class="text-center">
                    <i class="fa-regular fa-chart-line text-3xl mb-2 block text-slate-300"></i>
                    <p class="text-sm">Sem dados para exibir</p>
                    <p class="text-xs mt-1">Alimente suas metas para ver a evolucao</p>
                </div>
            </div>
            `;
            return;
        }
        el.innerHTML = '';
        const cores = ['#10b981', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];
        const options = {
            series: series.map((s, i) => ({
                name: s.name,
                data: s.data,
                type: 'line',
                color: cores[i % cores.length]
            })),
            chart: {
                height: 320,
                type: 'line',
                toolbar: { show: false },
                animations: { enabled: true, speed: 600 },
                zoom: { enabled: false }
            },
            stroke: { width: 2.5, curve: 'smooth' },
            markers: { size: 4, hover: { size: 6 } },
            xaxis: {
                categories: semanas.map(s => s.label),
                labels: { style: { fontSize: '10px', fontWeight: '500' } }
            },
            yaxis: {
                labels: { style: { fontSize: '10px' } },
                title: { text: 'Valor', style: { fontSize: '10px' } }
            },
            tooltip: { theme: 'dark', shared: true, y: { formatter: (val) => val.toLocaleString() } },
            legend: { position: 'bottom', fontSize: '10px', horizontalAlign: 'center' },
            grid: { borderColor: '#f1f5f9', strokeDashArray: 3 }
        };
        state.chartEvolucao = new ApexCharts(el, options);
        state.chartEvolucao.render();
    }

    // ========================================================================
    // CARREGAR LISTA CRM+AMBOS
    // ========================================================================
    async function carregarListaCRMAmbos() {
        try {
            const resp = await fetchAuth('/v1/marketing/clientes-compradores');
            const data = await resp.json();
            if (data.success) {
                const crmAmbosCompradores = data.crm_ambos_compradores || [];
                renderizarListaCRMAmbos(crmAmbosCompradores);
            }
        } catch (e) {
            console.error('Erro ao carregar lista CRM+AMBOS:', e);
        }
    }

    // ========================================================================
    // CARREGAR METAS SELECT
    // ========================================================================
    async function carregarMetasSelect() {
        try {
            const resp = await fetchAuth('/v1/meta-builder/instancias/ativas');
            const data = await resp.json();
            if (data.success && data.data) {
                const select = document.getElementById('filtroMeta');
                select.innerHTML = '<option value="">Todas as metas</option>' +
                    data.data.map(m => `<option value="${m.id}">${m.titulo}</option>`).join('');

                select.addEventListener('change', function() {
                    const metaId = this.value;
                    if (metaId) {
                        const meta = data.data.find(m => m.id == metaId);
                        popularSemanas(metaId, meta ? [meta] : data.data);
                    } else {
                        document.getElementById('filtroSemana').innerHTML = '<option value="">Todas as semanas</option>';
                    }
                });

                const metaId = select.value;
                if (metaId) {
                    const meta = data.data.find(m => m.id == metaId);
                    popularSemanas(metaId, meta ? [meta] : data.data);
                }
            }
        } catch (e) {
            console.error('Erro ao carregar metas:', e);
        }
    }

    function popularSemanas(metaId, metas) {
        const select = document.getElementById('filtroSemana');
        const meta = metas.find(m => m.id == metaId);
        let html = '<option value="">Todas as semanas</option>';
        if (meta && meta.data_inicio && meta.data_fim) {
            const inicio = new Date(meta.data_inicio);
            const fim = new Date(meta.data_fim);
            const diffWeeks = Math.ceil((fim - inicio) / (1000 * 60 * 60 * 24 * 7));
            for (let i = 1; i <= Math.min(diffWeeks, 30); i++) {
                const inicioSemana = new Date(inicio);
                inicioSemana.setDate(inicioSemana.getDate() + (i - 1) * 7);
                const fimSemana = new Date(inicioSemana);
                fimSemana.setDate(fimSemana.getDate() + 6);
                if (fimSemana > fim) fimSemana.setTime(fim.getTime());
                const periodo = `${inicioSemana.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'})} - ${fimSemana.toLocaleDateString('pt-BR', {day:'2-digit', month:'2-digit'})}`;
                html += `<option value="${i}">Semana ${i} (${periodo})</option>`;
            }
        } else {
            for (let i = 1; i <= 10; i++) {
                html += `<option value="${i}">Semana ${i}</option>`;
            }
        }
        select.innerHTML = html;
    }
// ========================================================================
// CARREGAR DADOS - UMA UNICA CONSULTA
// ========================================================================
window.carregarDados = async function() {
    if (state.carregando) return;
    state.carregando = true;

    try {
        // UMA UNICA CHAMADA PARA TUDO
        const resp = await fetchAuth('/v1/marketing/dashboard-final');
        const data = await resp.json();

        if (!data.success) {
            console.warn('Falha no dashboard-final');
            state.carregando = false;
            return;
        }

        console.log('Dashboard carregado com sucesso!');
        console.log('Dados:', data);

        const kpis = data.kpis;

        // ================================================================
        // 1. KPIs - TOTAIS DE CLIENTES
        // ================================================================
        document.getElementById('kpiTotalGeral').textContent = formatarNumero(kpis.total_clientes.geral);
        document.getElementById('kpiTotalERP').textContent = 'ERP: ' + formatarNumero(kpis.total_clientes.erp);
        document.getElementById('kpiTotalCRM').textContent = 'CRM: ' + formatarNumero(kpis.total_clientes.crm);
        document.getElementById('kpiTotalAMBOS').textContent = 'AMBOS: ' + formatarNumero(kpis.total_clientes.ambos);

        // ================================================================
        // 2. KPIs - COMPRADORES
        // ================================================================
        document.getElementById('kpiCompradoresMesGeral').textContent = formatarNumero(kpis.compradores_mes.geral);
        document.getElementById('kpiCompradoresMesERP').textContent = 'ERP: ' + formatarNumero(kpis.compradores_mes.erp);
        document.getElementById('kpiCompradoresMesCRM').textContent = 'CRM: ' + formatarNumero(kpis.compradores_mes.crm);

        // ================================================================
        // 3. KPIs - TAXAS
        // ================================================================
        document.getElementById('kpiTaxaGeral').textContent = kpis.taxas.geral + '%';
        document.getElementById('kpiTaxaERP').textContent = 'ERP: ' + kpis.taxas.erp + '%';
        document.getElementById('kpiTaxaCRM').textContent = 'CRM: ' + kpis.taxas.crm + '%';

        // ================================================================
        // 4. KPIs - FATURAMENTO
        // ================================================================
        document.getElementById('kpiFaturamentoMesCRM').textContent = formatarMoeda(kpis.faturamento.crm);
        document.getElementById('kpiFaturamentoMesERP').textContent = 'ERP: ' + formatarMoeda(kpis.faturamento.erp);

        // ================================================================
        // 5. KPIs - CRM + AMBOS
        // ================================================================
        document.getElementById('kpiCRMAmbosCompradores').textContent = formatarNumero(kpis.crm_ambos.quantidade);
        document.getElementById('kpiCRMAmbosPct').textContent = kpis.crm_ambos.percentual + '% do total';

        // ================================================================
        // 6. KPIs - METAS
        // ================================================================
        document.getElementById('kpiMetasAtivas').textContent = kpis.metas.ativas || 0;
        document.getElementById('kpiMetasProgresso').textContent = 'progresso: ' + (kpis.metas.progresso_medio || 0) + '%';

        // ================================================================
        // 7. INSIGHTS
        // ================================================================
        if (data.insights) {
            document.getElementById('insightDestaque').textContent = data.insights.destaque;
            document.getElementById('insightAtencao').textContent = data.insights.atencao;
            document.getElementById('insightOportunidade').textContent = data.insights.oportunidade;
        }

        // ================================================================
        // 8. LISTAS
        // ================================================================
        if (data.listas) {
            renderizarListasDashboard(data.listas);
        }

        // ================================================================
        // 9. METAS
        // ================================================================
        if (data.metas && data.metas.length > 0) {
            renderizarMetas(data.metas);
        } else {
            document.getElementById('metasContainer').innerHTML = 
                '<div class="p-6 text-center text-slate-400 text-sm">Nenhuma meta ativa</div>';
        }

        // ================================================================
        // 10. COMPARATIVO ERP - CORRIGIDO
        // ================================================================
        const totalERP = kpis.total_clientes.erp || 0;
        const compradoresERP = kpis.compradores_mes.erp || 0;
        const nuncaCompraramERP = totalERP - compradoresERP;
        const faturamentoERP = kpis.faturamento.erp || 0;
        const ticketMedioERP = compradoresERP > 0 ? (faturamentoERP / compradoresERP) : 0;
        const pctComprouERP = totalERP > 0 ? ((compradoresERP / totalERP) * 100) : 0;
        const pctNuncaERP = totalERP > 0 ? ((nuncaCompraramERP / totalERP) * 100) : 0;

        document.getElementById('totalERPClientesCard').innerText = formatarNumero(totalERP) + ' clientes';
        document.getElementById('comprouERPQuantidade').innerText = formatarNumero(compradoresERP);
        document.getElementById('comprouERPValor').innerText = formatarMoeda(faturamentoERP);
        document.getElementById('naoComprouERPQuantidade').innerText = formatarNumero(nuncaCompraramERP);
        document.getElementById('pctComprouERP').innerText = pctComprouERP.toFixed(1) + '%';
        document.getElementById('pctNaoComprouERP').innerText = pctNuncaERP.toFixed(1) + '%';
        document.getElementById('taxaConversaoERP').innerText = pctComprouERP.toFixed(1) + '%';
        document.getElementById('ticketMedioERP').innerText = formatarMoeda(ticketMedioERP);
        document.getElementById('barraComprouERP').style.width = Math.min(pctComprouERP, 100) + '%';
        document.getElementById('barraNaoComprouERP').style.width = Math.min(pctNuncaERP, 100) + '%';

        // ================================================================
        // 11. COMPARATIVO CRM - CORRIGIDO
        // ================================================================
        const totalCRM = kpis.total_clientes.crm || 0;
        const compradoresCRM = kpis.compradores_mes.crm || 0;
        const naoComprouCRM = totalCRM - compradoresCRM;
        const faturamentoCRM = kpis.faturamento.crm || 0;
        const ticketMedioCRM = compradoresCRM > 0 ? (faturamentoCRM / compradoresCRM) : 0;
        const pctComprouCRM = totalCRM > 0 ? ((compradoresCRM / totalCRM) * 100) : 0;
        const pctNaoComprouCRM = totalCRM > 0 ? ((naoComprouCRM / totalCRM) * 100) : 0;

        document.getElementById('totalCRMClientesCard').textContent = totalCRM + ' clientes';
        document.getElementById('comprouCRMQuantidade').textContent = compradoresCRM;
        document.getElementById('comprouCRMValor').textContent = formatarMoeda(faturamentoCRM);
        document.getElementById('naoComprouCRMQuantidade').textContent = naoComprouCRM;
        document.getElementById('pctComprouCRM').textContent = pctComprouCRM.toFixed(1) + '%';
        document.getElementById('pctNaoComprouCRM').textContent = pctNaoComprouCRM.toFixed(1) + '%';
        document.getElementById('taxaConversaoCRM').textContent = pctComprouCRM.toFixed(1) + '%';
        document.getElementById('ticketMedioCRM').textContent = formatarMoeda(ticketMedioCRM);
        document.getElementById('barraComprouCRM').style.width = Math.min(pctComprouCRM, 100) + '%';
        document.getElementById('barraNaoComprouCRM').style.width = Math.min(pctNaoComprouCRM, 100) + '%';

        // ================================================================
        // 12. RESUMO RAPIDO
        // ================================================================
        document.getElementById('resumoTotalCompras').textContent = formatarMoeda(kpis.faturamento.geral);
        document.getElementById('resumoTicketMedio').textContent = formatarMoeda(kpis.ticket_medio.geral);

        // ================================================================
        // 13. DESTAQUE E ATENCAO (METAS)
        // ================================================================
        if (data.metas && data.metas.length > 0) {
            const sorted = [...data.metas].sort((a, b) => {
                const pctA = a.progresso_percentual || 0;
                const pctB = b.progresso_percentual || 0;
                return pctB - pctA;
            });
            document.getElementById('resumoDestaque').textContent = sorted[0]?.titulo || '-';
            const ultimo = sorted[sorted.length - 1];
            document.getElementById('resumoAtencao').textContent = (ultimo && ultimo.progresso_percentual < 50) ? ultimo.titulo : 'OK';
        }

        // ================================================================
        // 14. GRAFICO E LISTAS ADICIONAIS
        // ================================================================
        const metaId = document.getElementById('filtroMeta').value;
        const semana = document.getElementById('filtroSemana').value;
        
        await Promise.all([
            carregarEvolucao(metaId, semana),
            carregarListaCRMAmbos()
        ]);

        state.carregando = false;

    } catch (e) {
        console.error('Erro no carregamento:', e);
        state.carregando = false;
    }
};
    // ========================================================================
    // FUNCOES GLOBAIS
    // ========================================================================
    window.editarCliente = function(id, origem = null) {
        if (!id) {
            Swal.fire('Atencao', 'ID do cliente nao informado', 'warning');
            return;
        }
        const url = `/portal/modules/marketing/clientes.php?buscar=${id}&origem=${origem || 'CRM'}`;
        window.open(url, '_blank');
    };

    window.abrirAlimentacaoMeta = async function(id, titulo) {
        if (typeof Swal === 'undefined') return;
        Swal.fire({
            title: `<div class="flex items-center gap-2"><i class="fa-solid fa-chart-line text-emerald-500"></i> Alimentar: ${titulo}</div>`,
            html: `<div id="alimMeta-${id}" class="text-center py-4">Carregando...</div>`,
            width: '500px',
            showCloseButton: true,
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#64748b',
            didOpen: async () => {
                try {
                    const resp = await fetchAuth(`/v1/meta-builder/instancias/${id}`);
                    const result = await resp.json();
                    if (!result.success) {
                        document.getElementById(`alimMeta-${id}`).innerHTML = '<p class="text-rose-500">Meta nao encontrada</p>';
                        return;
                    }
                    const m = result.data;
                    let valores = {};
                    if (m.valores) {
                        try { valores = typeof m.valores === 'string' ? JSON.parse(m.valores) : m.valores; } catch(e) {}
                    }
                    let html = `
                        <div class="text-left space-y-3">
                            <div class="bg-slate-50 p-3 rounded-lg text-xs text-slate-400">
                                Meta: <span class="font-bold text-slate-700">${m.titulo}</span>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Data</label>
                                <input type="date" id="dataAlim_${id}" class="w-full p-2 border rounded-lg text-sm" value="${new Date().toISOString().slice(0,10)}">
                            </div>
                    `;
                    const respCampos = await fetchAuth(`/v1/meta-builder/campos/${id}`);
                    const camposData = await respCampos.json();
                    const campos = camposData.success ? camposData.data : [];
                    if (campos.length) {
                        campos.forEach(campo => {
                            const val = valores[campo.nome_campo] || 0;
                            html += `
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">${campo.rotulo || campo.nome_campo}</label>
                                    <input type="number" step="0.01" id="campo_${campo.nome_campo}_${id}" 
                                           data-nome="${campo.nome_campo}" value="${val}" 
                                           class="w-full p-2 border rounded-lg text-sm focus:ring-2 focus:ring-emerald-400">
                                </div>
                            `;
                        });
                    } else {
                        html += `
                            <div>
                                <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Valor Alcantado</label>
                                <input type="number" step="0.01" id="campo_valor_${id}" data-nome="valor_alcancado" 
                                       class="w-full p-2 border rounded-lg text-sm">
                            </div>
                        `;
                    }
                    html += `
                            <button onclick="salvarAlimentacao(${id})" class="w-full py-2 bg-emerald-600 text-white rounded-lg text-sm font-bold hover:bg-emerald-700 transition-all">
                                <i class="fa-solid fa-save mr-1"></i> Salvar
                            </button>
                        </div>
                    `;
                    document.getElementById(`alimMeta-${id}`).innerHTML = html;
                } catch(e) {
                    document.getElementById(`alimMeta-${id}`).innerHTML = '<p class="text-rose-500">Erro ao carregar</p>';
                }
            }
        });
    };

    window.salvarAlimentacao = async function(idMeta) {
        const data = document.getElementById(`dataAlim_${idMeta}`).value;
        const valores = {};
        document.querySelectorAll(`#alimMeta-${idMeta} input[type="number"]`).forEach(input => {
            const nome = input.getAttribute('data-nome');
            const valor = parseFloat(input.value) || 0;
            if (nome) valores[nome] = valor;
        });
        if (!data) {
            Swal.fire('Atencao', 'Selecione uma data', 'warning');
            return;
        }
        const hasValue = Object.values(valores).some(v => v > 0);
        if (!hasValue) {
            Swal.fire('Atencao', 'Informe pelo menos um valor', 'warning');
            return;
        }
        Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        try {
            const userData = JSON.parse(localStorage.getItem('userData') || '{}');
            const usuarioId = userData.uid || userData.idusuario || 0;
            const resp = await fetchAuth('/v1/meta-builder/alimentar', {
                method: 'POST',
                body: JSON.stringify({
                    id_meta_instancia: idMeta,
                    data_registro: data,
                    valores: valores,
                    usuario_id: usuarioId
                })
            });
            const result = await resp.json();
            Swal.close();
            if (result.success) {
                Swal.fire({ icon: 'success', title: 'Registrado!', timer: 1500, showConfirmButton: false });
                setTimeout(() => window.carregarDados(), 1000);
            } else {
                Swal.fire('Erro', result.error || 'Falha ao salvar', 'error');
            }
        } catch(e) {
            Swal.close();
            Swal.fire('Erro', e.message, 'error');
        }
    };

    // ========================================================================
    // INICIALIZACAO
    // ========================================================================
    async function init() {
        iniciarRelogio();
        await carregarMetasSelect();
        await window.carregarDados();
    }

    document.addEventListener('DOMContentLoaded', init);

})();
</script>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>