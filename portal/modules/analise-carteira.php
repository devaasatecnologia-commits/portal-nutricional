<?php
$pageTitle = 'Análise de Carteira | Nutricional';
$version = time();
$jsVersion = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
:root {
    --transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}

.ac-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
.ac-hero { background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #334155 100%); }
.ac-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
.ac-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
.ac-input { width: 100%; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.7rem 1rem; font-size: 0.92rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
.ac-input:focus { outline: none; border-color: #3b82f6; background: #ffffff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.12); }

/* KPIs */
.kpi-card {
    transition: var(--transition);
    cursor: pointer;
    position: relative;
    overflow: hidden;
    border-radius: 1.25rem;
    padding: 1.15rem;
}
.kpi-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 28px -6px rgba(15, 23, 42, 0.08);
}

/* Badges */
.badge-critico { background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.badge-atencao { background: #fef3c7; color: #d97706; padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.badge-saudavel { background: #dcfce7; color: #16a34a; padding: 4px 10px; border-radius: 20px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.badge-dias { background: #fee2e2; color: #ef4444; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; }
.badge-evento { background: #dcfce7; color: #10b981; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; }
.badge-sem-evento { background: #fef3c7; color: #d97706; padding: 3px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: 700; }
.ac-chip { font-size: 0.72rem; font-weight: 800; padding: 0.35rem 0.65rem; border-radius: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }

/* Tabela */
.table-hover tbody tr { transition: all 0.15s ease; cursor: pointer; }
.table-hover tbody tr:hover { background: #f8fafc; }
.table-hover tbody tr.row-selected { background: #eff6ff !important; border-left: 3px solid #3b82f6; }

/* Timeline de Eventos */
.timeline-item { position: relative; padding-left: 1.75rem; padding-bottom: 1.25rem; }
.timeline-item::before { content: ""; position: absolute; left: 0.45rem; top: 1.25rem; bottom: 0; width: 2px; background: #e2e8f0; }
.timeline-item:last-child::before { display: none; }
.timeline-dot { position: absolute; left: 0; top: 0.25rem; width: 1rem; height: 1rem; border-radius: 999px; border: 2px solid #fff; box-shadow: 0 0 0 2px #cbd5e1; }

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
@keyframes shimmerFlow { 0% { background-position: 300% 0; } 100% { background-position: 0% 0; } }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="ac-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-8" id="analise-carteira-app">
    
    <!-- Shimmer Loading -->
    <div id="shimmer-bar" class="shimmer-bar"></div>

    <!-- HERO HEADER -->
    <div class="ac-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5 border border-slate-700/40">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white no-underline transition shadow-sm border border-white/10" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="ac-chip bg-white/10 text-white backdrop-blur-sm border border-white/15"><i class="fa-solid fa-chart-pie text-amber-400"></i> Gestão de Crédito</span>
                        <span class="ac-chip bg-amber-500/20 text-amber-200 border border-amber-400/30">Tolerância 3 Dias</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1 text-white">Análise de Carteira & Inadimplência</h1>
                    <p class="text-xs sm:text-sm text-slate-300 mt-0.5" id="subtitulo-header">Controle de títulos vencidos por gestor, representante e ocorrências de clientes</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <button onclick="exportarExcelCompleto()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm border border-emerald-500/30">
                    <i class="fa-solid fa-file-excel"></i> Baixar Excel Completo
                </button>
            </div>
        </div>
    </div>

    <!-- FILTROS (GESTOR & REPRESENTANTE) -->
    <div class="ac-card p-4 sm:p-5 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block">
                    <span class="ac-label flex items-center gap-1.5"><i class="fa-solid fa-user-tie text-blue-600"></i> Gestor Responsável</span>
                    <select id="select-gestor" class="ac-input mt-1.5 cursor-pointer">
                        <option value="">Carregando gestores...</option>
                    </select>
                </label>
            </div>
            <div>
                <label class="block">
                    <span class="ac-label flex items-center gap-1.5"><i class="fa-solid fa-user text-blue-500"></i> Representante Comercial</span>
                    <select id="select-representante" class="ac-input mt-1.5 cursor-pointer">
                        <option value="">Todos os representantes</option>
                    </select>
                </label>
            </div>
        </div>
    </div>

    <!-- 5 KPIS REORGANIZADOS COM HIERARQUIA VISUAL CLARA -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4 mb-5" id="kpi-cards">
        <!-- KPI 1: Vencidos (Inadimplência) -->
        <div class="kpi-card bg-gradient-to-br from-rose-50 to-red-50/70 border border-rose-200 shadow-sm" onclick="scrollToTable('tabela-representantes')">
            <div class="flex items-center justify-between text-rose-600 mb-1">
                <span class="ac-label text-rose-700">Total Vencido</span>
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
            </div>
            <div id="kpi-vencidos" class="text-xl sm:text-2xl font-black text-rose-600">R$ 0,00</div>
            <div id="kpi-vencidos-perc" class="text-[0.65rem] text-rose-700 font-bold mt-1">0% da carteira</div>
        </div>

        <!-- KPI 2: IAG (Índice de Atraso Geral) -->
        <div class="kpi-card bg-gradient-to-br from-amber-50 to-orange-50/70 border border-amber-200 shadow-sm">
            <div class="flex items-center justify-between text-amber-600 mb-1">
                <span class="ac-label text-amber-700">IAG (Inadimplência)</span>
                <i class="fa-solid fa-percent text-xs"></i>
            </div>
            <div id="kpi-iag" class="text-xl sm:text-2xl font-black text-amber-600">0%</div>
            <div class="text-[0.65rem] text-amber-700 font-bold mt-1">Índice Atraso Geral</div>
        </div>

        <!-- KPI 3: Títulos Vencidos & Clientes -->
        <div class="kpi-card bg-gradient-to-br from-purple-50 to-indigo-50/70 border border-purple-200 shadow-sm">
            <div class="flex items-center justify-between text-purple-600 mb-1">
                <span class="ac-label text-purple-700">Títulos Vencidos</span>
                <i class="fa-solid fa-file-circle-xmark text-xs"></i>
            </div>
            <div id="kpi-titulos" class="text-xl sm:text-2xl font-black text-purple-700">0</div>
            <div id="kpi-titulos-clientes" class="text-[0.65rem] text-purple-700 font-bold mt-1 truncate">0 clientes afetados</div>
        </div>

        <!-- KPI 4: À Vencer (Saudável) -->
        <div class="kpi-card bg-gradient-to-br from-emerald-50 to-teal-50/70 border border-emerald-200 shadow-sm">
            <div class="flex items-center justify-between text-emerald-600 mb-1">
                <span class="ac-label text-emerald-700">Carteira À Vencer</span>
                <i class="fa-solid fa-calendar-check text-xs"></i>
            </div>
            <div id="kpi-a-vencer" class="text-xl sm:text-2xl font-black text-emerald-700">R$ 0,00</div>
            <div id="kpi-a-vencer-sub" class="text-[0.65rem] text-emerald-700 font-bold mt-1 truncate" title="Janela dos próximos 30 dias">Próx. 30 dias</div>
        </div>

        <!-- KPI 5: Carteira Total -->
        <div class="kpi-card bg-gradient-to-br from-blue-50 to-slate-100 border border-blue-200 shadow-sm">
            <div class="flex items-center justify-between text-blue-600 mb-1">
                <span class="ac-label text-blue-700">Carteira Total</span>
                <i class="fa-solid fa-wallet text-xs"></i>
            </div>
            <div id="kpi-carteira" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div id="kpi-carteira-info" class="text-[0.65rem] text-slate-500 font-bold mt-1 truncate">0 títulos | 0 clientes</div>
        </div>
    </div>

    <!-- GRÁFICOS (DISTRIBUIÇÃO POR FAIXA & RANKING) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">
        <!-- Pizza - Faixas de Atraso -->
        <div class="ac-card p-4 sm:p-5">
            <div class="flex justify-between items-center mb-1">
                <h3 class="font-extrabold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-rose-500"></i> Distribuição do Vencido
                </h3>
            </div>
            <p class="text-[0.7rem] text-slate-400 font-semibold mb-3">Clique em uma fatia para ver representantes</p>
            <div id="chart-pizza" style="height: 260px;"></div>
            <div class="grid grid-cols-3 gap-2 mt-2 text-center text-xs">
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-2">
                    <span class="text-slate-500 block text-[0.65rem] font-bold">30 Dias</span>
                    <span class="font-black text-blue-700" id="pizza-30d">R$ 0</span>
                    <span class="text-[0.65rem] text-slate-400 block" id="pizza-30d-perc">0%</span>
                </div>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-2">
                    <span class="text-slate-500 block text-[0.65rem] font-bold">60 Dias</span>
                    <span class="font-black text-amber-700" id="pizza-60d">R$ 0</span>
                    <span class="text-[0.65rem] text-slate-400 block" id="pizza-60d-perc">0%</span>
                </div>
                <div class="bg-rose-50 border border-rose-100 rounded-xl p-2">
                    <span class="text-slate-500 block text-[0.65rem] font-bold">+60 Dias</span>
                    <span class="font-black text-rose-700" id="pizza-90d">R$ 0</span>
                    <span class="text-[0.65rem] text-slate-400 block" id="pizza-90d-perc">0%</span>
                </div>
            </div>
        </div>

        <!-- Ranking Representantes -->
        <div class="ac-card p-4 sm:p-5 lg:col-span-2">
            <div class="flex justify-between items-center mb-1">
                <h3 class="font-extrabold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-ranking-star text-amber-500"></i> Top 10 Representantes em Inadimplência
                </h3>
            </div>
            <p class="text-[0.7rem] text-slate-400 font-semibold mb-3">Volume vencido em R$ e taxa de IAG (%)</p>
            <div id="chart-ranking" style="height: 310px;"></div>
        </div>
    </div>

    <!-- TABELA: RESUMO DO GESTOR -->
    <div class="ac-card overflow-hidden mb-5" id="secao-resumo-gestor" style="display: none;">
        <div class="p-4 bg-slate-50 border-b border-slate-100">
            <h3 class="font-extrabold text-slate-800 text-xs sm:text-sm flex items-center gap-2">
                <i class="fa-solid fa-building text-slate-500"></i> Dados Consolidados do Gestor (Tolerância de 3 Dias)
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs" id="tabela-resumo-gestor">
                <thead class="bg-slate-100/70 border-b border-slate-200 text-[0.65rem] uppercase tracking-wider text-slate-500 font-black">
                    <tr>
                        <th class="px-4 py-3 text-left">Gestor</th>
                        <th class="px-4 py-3 text-right">Vencidos</th>
                        <th class="px-4 py-3 text-right">30 Dias</th>
                        <th class="px-4 py-3 text-center">% 30</th>
                        <th class="px-4 py-3 text-right">60 Dias</th>
                        <th class="px-4 py-3 text-center">% 60</th>
                        <th class="px-4 py-3 text-right">+60 Dias</th>
                        <th class="px-4 py-3 text-center">% +60</th>
                        <th class="px-4 py-3 text-center">Performance</th>
                        <th class="px-4 py-3 text-right">À Vencer</th>
                    </tr>
                </thead>
                <tbody id="tabela-resumo-gestor-body"></tbody>
            </table>
        </div>
    </div>

    <!-- TABELA: DADOS POR REPRESENTANTE -->
    <div class="ac-card overflow-hidden mb-5" id="tabela-representantes">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="font-extrabold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-users text-blue-600"></i> Inadimplência por Representante
                </h3>
                <p class="text-xs text-slate-400 mt-0.5">Clique em um representante para detalhar seus títulos e clientes</p>
            </div>
            <span class="text-xs font-bold text-slate-500" id="tabela-rep-info"></span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs table-hover" id="tabela-rep">
                <thead class="bg-slate-50 border-b border-slate-200 text-[0.65rem] uppercase tracking-wider text-slate-500 font-black">
                    <tr>
                        <th class="px-3 py-3 text-left">Representante</th>
                        <th class="px-3 py-3 text-right">Vencidos</th>
                        <th class="px-3 py-3 text-right">30 Dias</th>
                        <th class="px-3 py-3 text-center">%30</th>
                        <th class="px-3 py-3 text-right">60 Dias</th>
                        <th class="px-3 py-3 text-center">%60</th>
                        <th class="px-3 py-3 text-right">+60 Dias</th>
                        <th class="px-3 py-3 text-center">%+60</th>
                        <th class="px-3 py-3 text-center">% Total</th>
                        <th class="px-3 py-3 text-center">Títulos</th>
                        <th class="px-3 py-3 text-center">Clientes</th>
                        <th class="px-3 py-3 text-center">Performance</th>
                        <th class="px-3 py-3 text-right">À Vencer</th>
                    </tr>
                </thead>
                <tbody id="tabela-rep-body">
                    <tr>
                        <td colspan="13" class="text-center py-12 text-slate-400">
                            <i class="fa-solid fa-user-tie text-2xl block mb-2 text-slate-300"></i>
                            Selecione um gestor para visualizar os dados de carteira
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TABELA: TÍTULOS DO REPRESENTANTE & EVENTOS DO CLIENTE -->
    <div class="ac-card overflow-hidden" id="secao-titulos" style="display: none;">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-gradient-to-r from-rose-50/50 to-white">
            <div>
                <h3 class="font-extrabold text-slate-800 text-sm flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice-dollar text-rose-600"></i> Títulos em Aberto do Representante
                </h3>
                <span class="text-xs text-rose-700 font-bold mt-0.5 block" id="titulos-nome-rep"></span>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500" id="tabela-titulos-info"></span>
                <button onclick="exportarExcelTitulos()" class="py-2 px-3.5 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-file-excel"></i> Exportar Títulos
                </button>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs table-hover" id="tabela-titulos">
                <thead class="bg-slate-50 border-b border-slate-200 text-[0.65rem] uppercase tracking-wider text-slate-500 font-black">
                    <tr>
                        <th class="px-3 py-3 text-left">Cliente / Razão</th>
                        <th class="px-3 py-3 text-left">Documento</th>
                        <th class="px-3 py-3 text-center">Vencimento</th>
                        <th class="px-3 py-3 text-right">Saldo Devedor</th>
                        <th class="px-3 py-3 text-center">Atraso</th>
                        <th class="px-3 py-3 text-center">Último Evento</th>
                        <th class="px-3 py-3 text-left">Operador</th>
                        <th class="px-3 py-3 text-left">Apontamento / Histórico</th>
                        <th class="px-3 py-3 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody id="tabela-titulos-body" class="divide-y divide-slate-100">
                    <tr><td colspan="9" class="text-center py-8 text-slate-400">Selecione um representante para listar os títulos</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script src="<?= $appBase ?>/portal/assets/js/analise-carteira.js?v=<?= $jsVersion ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
