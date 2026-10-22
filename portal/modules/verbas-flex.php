<?php
$pageTitle = 'Gestão de Verbas Flex | Nutricional';
$moduleJs = 'verbas-flex.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
  .vf-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .vf-hero { background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #7c3aed 100%); }
  .vf-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .vf-label { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .vf-input { width: 100%; margin-top: 0.35rem; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.7rem 0.95rem; font-size: 0.95rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
  .vf-input:focus { outline: none; border-color: #8b5cf6; background: #ffffff; box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12); }
  .vf-tabs { display: flex; gap: 0.4rem; overflow-x: auto; padding: 0.35rem; background: #ffffff; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
  .vf-tab { flex: 1; white-space: nowrap; padding: 0.65rem 1.1rem; border-radius: 1rem; font-weight: 800; font-size: 0.82rem; color: #64748b; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; }
  .vf-tab.on { background: linear-gradient(135deg, #6d28d9, #8b5cf6); color: #ffffff; box-shadow: 0 6px 16px rgba(109, 40, 217, 0.3); }
  .vf-tab:not(.on):hover { background: #f8fafc; color: #1e293b; }
  .vf-kpi { border-radius: 1.25rem; padding: 1.1rem; transition: transform 0.2s; }
  .vf-kpi:hover { transform: translateY(-2px); }
  .vf-row { border: 1px solid #f1f5f9; border-radius: 1.15rem; padding: 0.9rem 1rem; display: flex; gap: 0.85rem; align-items: center; transition: all 0.15s ease; background: #ffffff; }
  .vf-row:hover { border-color: #c4b5fd; background: #faf5ff; transform: translateY(-1px); }
  .vf-chip { padding: 0.4rem 0.9rem; border-radius: 999px; background: #f1f5f9; color: #475569; font-size: 0.75rem; font-weight: 700; transition: all 0.15s; }
  .vf-chip.on { background: #6d28d9; color: #ffffff; box-shadow: 0 4px 12px rgba(109, 40, 217, 0.25); }
  .vf-chip:hover:not(.on) { background: #e2e8f0; color: #1e293b; }
  .rank-badge { width: 2.2rem; height: 2.2rem; border-radius: 0.75rem; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 0.9rem; flex-none; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="vf-wrap max-w-6xl mx-auto px-3 sm:px-6 pt-4 pb-12">
    <!-- HERO BANNER -->
    <div class="vf-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-10 opacity-10 pointer-events-none">
            <i class="fa-solid fa-sack-dollar text-[180px]"></i>
        </div>
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white transition no-underline">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-lg bg-white/20 text-purple-100 text-xs font-black uppercase tracking-wider">Módulo Comercial</span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-emerald-400/20 text-emerald-200 text-xs font-bold border border-emerald-300/20">
                            <i class="fa-solid fa-bolt mr-1"></i>Sincronizado Mercos
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">Gestão de Verbas Flex</h1>
                    <p class="text-xs sm:text-sm text-purple-200 font-medium mt-0.5">Painel analítico, ranking de consumo por representante e conciliação de empréstimos</p>
                </div>
            </div>
        </div>
    </div>

    <!-- FILTROS & PERÍODO -->
    <div class="vf-card p-4 sm:p-5 mb-4">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <label class="vf-label">Filial Operativa</label>
                <select id="selFilial" class="vf-input cursor-pointer"></select>
            </div>
            <div>
                <label class="vf-label">Data Inicial</label>
                <input id="dtIni" type="date" class="vf-input">
            </div>
            <div>
                <label class="vf-label">Data Final</label>
                <input id="dtFim" type="date" class="vf-input">
            </div>
            <div class="flex items-end">
                <button id="btnAplicar" class="w-full py-3 rounded-2xl bg-gradient-to-r from-violet-700 to-purple-600 hover:from-violet-800 hover:to-purple-700 text-white font-extrabold text-sm shadow-md shadow-violet-600/30 transition flex items-center justify-center gap-2">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Filtrar</span>
                </button>
            </div>
        </div>
        <div class="flex gap-2 mt-3.5 overflow-x-auto pb-1" id="atalhos">
            <button type="button" data-p="hoje" class="vf-chip">Hoje</button>
            <button type="button" data-p="7" class="vf-chip">7 dias</button>
            <button type="button" data-p="mes" class="vf-chip on">Este mês</button>
            <button type="button" data-p="ant" class="vf-chip">Mês anterior</button>
            <button type="button" data-p="30" class="vf-chip">30 dias</button>
            <button type="button" data-p="ano" class="vf-chip">Este ano</button>
        </div>
    </div>

    <!-- ABAS -->
    <div class="vf-tabs mb-4" id="abas">
        <button type="button" data-aba="painel" class="vf-tab on"><i class="fa-solid fa-chart-pie"></i> Painel & Dash</button>
        <button type="button" data-aba="ranking" class="vf-tab"><i class="fa-solid fa-ranking-star"></i> Ranking Consumo</button>
        <button type="button" data-aba="debitos" class="vf-tab"><i class="fa-solid fa-arrow-trend-down"></i> Débitos / Notas</button>
        <button type="button" data-aba="creditos" class="vf-tab"><i class="fa-solid fa-hand-holding-dollar"></i> Créditos / Empréstimos</button>
        <button type="button" data-aba="api" class="vf-tab"><i class="fa-solid fa-heart-pulse"></i> API Mercos</button>
        <a href="<?= $appBase ?>/portal/modules/transferencia-flex.php" class="vf-tab text-center no-underline"><i class="fa-solid fa-right-left"></i> Transferências</a>
    </div>

    <!-- ========================================== -->
    <!-- ABA 1: PAINEL & DASHBOARD                  -->
    <!-- ========================================== -->
    <section id="aba-painel" class="space-y-4">
        <!-- 6 KPIS PRINCIPAIS -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-3.5" id="kpis">
            <!-- Preenchido via JS -->
        </div>

        <!-- GRÁFICOS (CHART.JS) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- GRÁFICO 1: COMPARATIVO ÚLTIMOS 6 MESES -->
            <div class="vf-card p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="font-extrabold text-sm sm:text-base text-slate-800">Evolução dos Últimos 6 Meses</h3>
                        <p class="text-xs text-slate-400">Comparativo entre Bonificações Retiradas e Empréstimos</p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-xl bg-purple-50 text-purple-700">Mensal</span>
                </div>
                <div class="relative h-64">
                    <canvas id="chartMensal"></canvas>
                </div>
            </div>

            <!-- GRÁFICO 2: TENDÊNCIA DIÁRIA NO PERÍODO -->
            <div class="vf-card p-5">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="font-extrabold text-sm sm:text-base text-slate-800">Tendência Diária de Retiradas</h3>
                        <p class="text-xs text-slate-400">Volume de verba flex debitada por dia no período</p>
                    </div>
                    <span class="text-xs font-bold px-2.5 py-1 rounded-xl bg-red-50 text-red-700">Débitos</span>
                </div>
                <div class="relative h-64">
                    <canvas id="chartDiario"></canvas>
                </div>
            </div>
        </div>

        <!-- MINI RANKING DASHBOARD -->
        <div class="vf-card p-5">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="font-extrabold text-sm sm:text-base text-slate-800">Top Consumidores de Verba no Período</h3>
                    <p class="text-xs text-slate-400">Representantes com maior volume retirado em notas fiscais</p>
                </div>
                <button type="button" onclick="irParaAbaRanking()" class="text-xs font-black text-violet-700 hover:text-violet-800 flex items-center gap-1 transition">
                    <span>Ver Ranking Completo</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
            <div id="miniRanking" class="space-y-3"></div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- ABA 2: RANKING COMPLETO DE CONSUMO         -->
    <!-- ========================================== -->
    <section id="aba-ranking" class="hidden space-y-4">
        <div class="vf-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                <div>
                    <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-trophy text-amber-500"></i>
                        <span>Ranking Geral de Consumo de Verba Flex</span>
                    </h3>
                    <p class="text-xs text-slate-400">Classificação completa de todos os representantes que consumiram no período</p>
                </div>
                <button id="xlsRanking" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-file-excel text-emerald-600"></i>
                    <span>Exportar Ranking</span>
                </button>
            </div>

            <!-- CONTROLES DO RANKING -->
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 bg-slate-50 p-3 rounded-2xl border border-slate-100">
                <div class="flex items-center gap-1.5" id="filtroQtdRanking">
                    <button type="button" data-limit="5" class="vf-chip">Top 5</button>
                    <button type="button" data-limit="10" class="vf-chip">Top 10</button>
                    <button type="button" data-limit="all" class="vf-chip on">Ver Todos (<span id="contTotalReps">0</span>)</button>
                </div>
                <div class="w-full sm:w-64">
                    <input id="buscaRanking" type="search" placeholder="Buscar representante..." class="w-full px-3.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold focus:outline-violet-600">
                </div>
            </div>

            <!-- TABELA / CARDS DO RANKING -->
            <div id="listaRankingCompleto" class="space-y-2.5"></div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- ABA 3: DÉBITOS / RETIRADAS                 -->
    <!-- ========================================== -->
    <section id="aba-debitos" class="hidden">
        <div class="vf-card p-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="vf-label">Representante</label>
                    <select id="fRep" class="vf-input"></select>
                </div>
                <div>
                    <label class="vf-label">Buscar Débito</label>
                    <input id="fBuscaD" type="search" placeholder="Cliente, nota ou motivo..." class="vf-input">
                </div>
            </div>
            <div class="flex items-center justify-between mb-4 p-4 rounded-2xl bg-red-50/70 border border-red-100">
                <div>
                    <span class="vf-label text-red-700">Total Retirado em Bonificações</span>
                    <div id="totD" class="text-2xl sm:text-3xl font-black text-red-600">R$ 0,00</div>
                    <div id="qtdD" class="text-xs text-red-800/80 font-bold mt-0.5"></div>
                </div>
                <button id="xlsD" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-red-700 border border-red-200 text-xs font-bold flex items-center gap-1.5 shadow-xs transition">
                    <i class="fa-solid fa-file-excel text-emerald-600"></i>
                    <span>Baixar Excel</span>
                </button>
            </div>
            <div id="listaD" class="space-y-2"></div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- ABA 4: CRÉDITOS / EMPRÉSTIMOS              -->
    <!-- ========================================== -->
    <section id="aba-creditos" class="hidden">
        <div class="vf-card p-5 sm:p-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                <div>
                    <label class="vf-label">Buscar Motivo</label>
                    <input id="fBuscaC" type="search" placeholder="Motivo do empréstimo..." class="vf-input">
                </div>
                <div class="flex items-center pt-5">
                    <label class="flex items-center gap-2 text-xs font-bold text-slate-600 cursor-pointer select-none">
                        <input id="fZerados" type="checkbox" class="w-4 h-4 accent-violet-600 rounded">
                        <span>Mostrar lançamentos zerados / falhas técnicas</span>
                    </label>
                </div>
            </div>
            <div class="flex items-center justify-between mb-4 p-4 rounded-2xl bg-emerald-50/70 border border-emerald-100">
                <div>
                    <span class="vf-label text-emerald-700">Total Concedido em Empréstimos</span>
                    <div id="totC" class="text-2xl sm:text-3xl font-black text-emerald-700">R$ 0,00</div>
                    <div id="qtdC" class="text-xs text-emerald-800/80 font-bold mt-0.5"></div>
                </div>
                <button id="xlsC" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-emerald-800 border border-emerald-200 text-xs font-bold flex items-center gap-1.5 shadow-xs transition">
                    <i class="fa-solid fa-file-excel text-emerald-600"></i>
                    <span>Baixar Excel</span>
                </button>
            </div>
            <div id="listaC" class="space-y-2"></div>
        </div>
    </section>

    <!-- ========================================== -->
    <!-- ABA 5: API MERCOS                          -->
    <!-- ========================================== -->
    <section id="aba-api" class="hidden space-y-4">
        <div class="vf-card p-5">
            <div class="flex items-center gap-3.5">
                <div id="apiIco" class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center text-xl flex-none"><i class="fa-solid fa-circle-question"></i></div>
                <div class="flex-1 min-w-0">
                    <h3 id="apiTit" class="font-extrabold text-base text-slate-800">Status da API Mercos</h3>
                    <p id="apiSub" class="text-xs text-slate-400">Clique no botão para verificar conexão e tempo de resposta.</p>
                </div>
                <button id="btnTestar" class="rounded-2xl bg-violet-600 hover:bg-violet-700 text-white font-extrabold px-5 py-2.5 text-xs shadow-md shadow-violet-600/30 transition flex items-center gap-2">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Testar agora</span>
                </button>
            </div>
            <div id="apiGestores" class="mt-4 space-y-2"></div>
        </div>

        <div class="vf-card p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-extrabold text-sm sm:text-base text-slate-800">Disponibilidade e Histórico</h3>
                <div class="flex gap-1.5" id="apiPeriodo">
                    <button type="button" data-h="24" class="vf-chip on">24 h</button>
                    <button type="button" data-h="72" class="vf-chip">3 dias</button>
                    <button type="button" data-h="168" class="vf-chip">7 dias</button>
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mb-3 text-center" id="apiKpis"></div>
            <div id="apiBarras" class="flex gap-px h-8 mb-1"></div>
            <div class="flex justify-between text-[10px] text-slate-400 mb-4"><span id="apiDe"></span><span>agora</span></div>
            <h4 class="font-bold text-xs text-slate-700 mb-2">Quedas e Falhas no Período</h4>
            <div id="apiQuedas" class="space-y-2"></div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
