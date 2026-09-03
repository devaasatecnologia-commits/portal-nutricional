<?php
$pageTitle = 'Análise de Carteira | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<style>
:root {
    --card-shadow: 0 1px 3px rgba(0,0,0,0.04);
    --hover-shadow: 0 10px 30px rgba(0,0,0,0.08);
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* KPIs */
.kpi-card {
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--hover-shadow);
}
.kpi-card::after {
    content: "";
    position: absolute;
    top: -20px; right: -20px;
    width: 80px; height: 80px;
    background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, transparent 70%);
    border-radius: 50%;
}

/* Badges */
.badge-critico {
    background: #fee2e2; color: #dc2626;
    padding: 5px 12px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 800;
    text-transform: uppercase;
}
.badge-atencao {
    background: #fef3c7; color: #d97706;
    padding: 5px 12px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 800;
    text-transform: uppercase;
}
.badge-saudavel {
    background: #dcfce7; color: #16a34a;
    padding: 5px 12px; border-radius: 20px;
    font-size: 0.65rem; font-weight: 800;
    text-transform: uppercase;
}
.badge-dias {
    background: #fee2e2; color: #ef4444;
    padding: 4px 10px; border-radius: 6px;
    font-size: 0.7rem; font-weight: 700;
}
.badge-evento {
    background: #dcfce7; color: #10b981;
    padding: 4px 10px; border-radius: 6px;
    font-size: 0.7rem; font-weight: 700;
}
.badge-sem-evento {
    background: #fef3c7; color: #d97706;
    padding: 4px 10px; border-radius: 6px;
    font-size: 0.7rem; font-weight: 700;
}

/* Tabela */
.table-hover tbody tr {
    transition: all 0.2s ease;
}
.table-hover tbody tr:hover {
    background: #f8fafc;
}
.table-hover tbody tr.row-selected {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}

/* Progresso */
.progress-mini {
    height: 4px;
    border-radius: 2px;
    background: #e2e8f0;
    overflow: hidden;
}
.progress-fill {
    height: 100%;
    border-radius: 2px;
    transition: width 1.5s ease;
}

/* Shimmer */
.shimmer-bar {
    position: fixed; top: 0; left: 0;
    width: 100%; height: 3px;
    background: linear-gradient(90deg, #ef4444, #f59e0b, #10b981, #3b82f6, #8b5cf6, #ef4444);
    background-size: 300% 100%;
    z-index: 9999;
    animation: shimmerFlow 1.5s infinite linear;
    display: none;
}
@keyframes shimmerFlow {
    0% { background-position: 300% 0; }
    100% { background-position: 0% 0; }
}

/* Tooltip personalizado */
.tooltip-custom {
    position: relative;
}
.tooltip-custom:hover .tooltip-text {
    display: block;
}
.tooltip-text {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: #1e293b;
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.7rem;
    white-space: nowrap;
    z-index: 50;
}
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" id="analise-carteira-app">
    
    <!-- Shimmer Loading -->
    <div id="shimmer-bar" class="shimmer-bar"></div>

    <!-- ====================================================================== -->
    <!-- HEADER -->
    <!-- ====================================================================== -->
    <div class="rounded-3xl p-5 mb-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 shadow-sm bg-white border border-slate-100">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="flex w-10 h-10 rounded-xl items-center justify-center bg-slate-100 hover:bg-slate-200 transition-colors no-underline">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-red-600 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-chart-pie text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">ANÁLISE DE CARTEIRA</h2>
                <span class="text-xs text-slate-400 font-medium" id="subtitulo-header">Inadimplência por Gestor e Representante</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="exportarExcelCompleto()" class="px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-file-excel"></i> Baixar Excel Completo
            </button>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- FILTROS -->
    <!-- ====================================================================== -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-user-tie mr-1 text-rose-500"></i> Gestor
                </label>
                <select id="select-gestor" 
                        class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-rose-300 transition-all">
                    <option value="">Selecione um gestor...</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-user mr-1 text-blue-500"></i> Representante
                </label>
                <select id="select-representante" 
                        class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-blue-300 transition-all">
                    <option value="">Todos os representantes</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- KPIs CARDS -->
    <!-- ====================================================================== -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6" id="kpi-cards">
        <div class="kpi-card bg-white p-4 rounded-2xl border-l-4 border-rose-500 shadow-sm" onclick="scrollToTable('tabela-representantes')">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Vencidos</span>
            <span class="block text-xl lg:text-2xl font-black text-rose-600" id="kpi-vencidos">R$ 0</span>
            <span class="text-[10px] text-slate-400 mt-1" id="kpi-vencidos-perc">0% da carteira</span>
        </div>
        <div class="kpi-card bg-white p-4 rounded-2xl border-l-4 border-amber-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">IAG</span>
            <span class="block text-xl lg:text-2xl font-black text-amber-600" id="kpi-iag">0%</span>
            <span class="text-[10px] text-slate-400 mt-1">Inadimplência Geral</span>
        </div>
        <div class="kpi-card bg-white p-4 rounded-2xl border-l-4 border-blue-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Carteira Total</span>
            <span class="block text-xl lg:text-2xl font-black text-blue-600" id="kpi-carteira">R$ 0</span>
            <span class="text-[10px] text-slate-400 mt-1" id="kpi-carteira-info">0 títulos | 0 clientes</span>
        </div>
        <div class="kpi-card bg-white p-4 rounded-2xl border-l-4 border-purple-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Títulos Vencidos</span>
            <span class="block text-xl lg:text-2xl font-black text-purple-600" id="kpi-titulos">0</span>
            <span class="text-[10px] text-slate-400 mt-1" id="kpi-titulos-clientes">0 clientes afetados</span>
        </div>
        <div class="kpi-card bg-white p-4 rounded-2xl border-l-4 border-emerald-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">À Vencer</span>
            <span class="block text-xl lg:text-2xl font-black text-emerald-600" id="kpi-a-vencer">R$ 0</span>
            <span class="text-[10px] text-slate-400 mt-1">Próximos 30 dias</span>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- GRÁFICOS -->
    <!-- ====================================================================== -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-6">
        <!-- Pizza - Distribuição -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <h3 class="font-bold text-slate-700 mb-1 text-sm">
                <i class="fa-solid fa-chart-pie mr-2 text-rose-500"></i>Distribuição do Vencido
            </h3>
            <p class="text-[10px] text-slate-400 mb-3">Por faixa de atraso</p>
            <div id="chart-pizza" style="height: 280px;"></div>
            <div class="grid grid-cols-3 gap-2 mt-3 text-center text-xs">
                <div class="bg-blue-50 rounded-lg p-2">
                    <span class="text-slate-500 block">30 Dias</span>
                    <span class="font-bold text-blue-600" id="pizza-30d">R$ 0</span>
                    <span class="text-[10px] text-slate-400" id="pizza-30d-perc">0%</span>
                </div>
                <div class="bg-amber-50 rounded-lg p-2">
                    <span class="text-slate-500 block">60 Dias</span>
                    <span class="font-bold text-amber-600" id="pizza-60d">R$ 0</span>
                    <span class="text-[10px] text-slate-400" id="pizza-60d-perc">0%</span>
                </div>
                <div class="bg-rose-50 rounded-lg p-2">
                    <span class="text-slate-500 block">+60 Dias</span>
                    <span class="font-bold text-rose-600" id="pizza-90d">R$ 0</span>
                    <span class="text-[10px] text-slate-400" id="pizza-90d-perc">0%</span>
                </div>
            </div>
        </div>

        <!-- Ranking Representantes -->
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 lg:col-span-2">
            <h3 class="font-bold text-slate-700 mb-1 text-sm">
                <i class="fa-solid fa-ranking-star mr-2 text-amber-500"></i>Ranking de Representantes
            </h3>
            <p class="text-[10px] text-slate-400 mb-3">Top 10 por valor vencido com IAG</p>
            <div id="chart-ranking" style="height: 300px;"></div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- TABELA: RESUMO GESTOR -->
    <!-- ====================================================================== -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6" id="secao-resumo-gestor" style="display: none;">
        <div class="p-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
            <h3 class="font-bold text-slate-700 text-sm">
                <i class="fa-solid fa-building mr-2 text-slate-500"></i>DADOS RECEBER GESTOR (HOJE MENOS 3 DIAS)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs" id="tabela-resumo-gestor">
                <thead class="bg-slate-50 border-b-2 border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left font-bold text-slate-500 uppercase">Gestor</th>
                        <th class="px-4 py-3 text-right font-bold text-slate-500 uppercase">Vencidos</th>
                        <th class="px-4 py-3 text-right font-bold text-slate-500 uppercase">30 Dias</th>
                        <th class="px-4 py-3 text-center font-bold text-slate-500 uppercase">%30</th>
                        <th class="px-4 py-3 text-right font-bold text-slate-500 uppercase">60 Dias</th>
                        <th class="px-4 py-3 text-center font-bold text-slate-500 uppercase">%60</th>
                        <th class="px-4 py-3 text-right font-bold text-slate-500 uppercase">+60 Dias</th>
                        <th class="px-4 py-3 text-center font-bold text-slate-500 uppercase">%+60</th>
                        <th class="px-4 py-3 text-center font-bold text-slate-500 uppercase">Performance</th>
                    </tr>
                </thead>
                <tbody id="tabela-resumo-gestor-body"></tbody>
            </table>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- TABELA: REPRESENTANTES -->
    <!-- ====================================================================== -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6" id="tabela-representantes">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-slate-50 to-white">
            <div>
                <h3 class="font-bold text-slate-700 text-sm">
                    <i class="fa-solid fa-users mr-2 text-blue-500"></i>DADOS RECEBER POR REPRESENTANTE (HOJE MENOS 3 DIAS)
                </h3>
            </div>
            <span class="text-xs text-slate-400" id="tabela-rep-info"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs table-hover" id="tabela-rep">
                <thead class="bg-slate-50 border-b-2 border-slate-200">
                    <tr>
                        <th class="px-3 py-3 text-left font-bold text-slate-500 uppercase">Representante</th>
                        <th class="px-3 py-3 text-right font-bold text-slate-500 uppercase">Vencidos</th>
                        <th class="px-3 py-3 text-right font-bold text-slate-500 uppercase">30 Dias</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">%30</th>
                        <th class="px-3 py-3 text-right font-bold text-slate-500 uppercase">60 Dias</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">%60</th>
                        <th class="px-3 py-3 text-right font-bold text-slate-500 uppercase">+60 Dias</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">%+60</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">% Total</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">Títulos</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">Clientes</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">Performance</th>
                    </tr>
                </thead>
                <tbody id="tabela-rep-body">
                    <tr><td colspan="12" class="text-center py-12 text-slate-400">
                        <i class="fa-solid fa-arrow-up text-2xl block mb-2 text-slate-300"></i>
                        Selecione um gestor para visualizar os dados
                    </td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- TABELA: TÍTULOS DO REPRESENTANTE -->
    <!-- ====================================================================== -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden" id="secao-titulos" style="display: none;">
        <div class="p-4 border-b border-slate-100 flex justify-between items-center bg-gradient-to-r from-rose-50 to-white">
            <div>
                <h3 class="font-bold text-slate-700 text-sm">
                    <i class="fa-solid fa-file-invoice mr-2 text-rose-500"></i>DADOS REPRESENTANTE - TÍTULOS EM ABERTO (HOJE MENOS 3 DIAS)
                </h3>
                <span class="text-[10px] text-slate-400" id="titulos-nome-rep"></span>
            </div>
            <div class="flex gap-2">
                <span class="text-xs text-slate-400 self-center" id="tabela-titulos-info"></span>
                <button onclick="exportarExcelTitulos()" class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold hover:bg-emerald-600 transition-all">
                    <i class="fa-solid fa-download mr-1"></i>Excel
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs table-hover" id="tabela-titulos">
                <thead class="bg-slate-50 border-b-2 border-slate-200">
                    <tr>
                        <th class="px-3 py-3 text-left font-bold text-slate-500 uppercase">Nome Fantasia</th>
                        <th class="px-3 py-3 text-left font-bold text-slate-500 uppercase">Documento</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">Vencimento</th>
                        <th class="px-3 py-3 text-right font-bold text-slate-500 uppercase">Valor Saldo</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">Dias Atraso</th>
                        <th class="px-3 py-3 text-center font-bold text-slate-500 uppercase">Dias Últ. Evento</th>
                        <th class="px-3 py-3 text-left font-bold text-slate-500 uppercase">Usuário</th>
                        <th class="px-3 py-3 text-left font-bold text-slate-500 uppercase">Registro do Evento</th>
                    </tr>
                </thead>
                <tbody id="tabela-titulos-body">
                    <tr><td colspan="8" class="text-center py-8 text-slate-400">Selecione um representante para ver os títulos</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/portal/assets/js/analise-carteira.js?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>