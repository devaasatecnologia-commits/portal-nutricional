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
                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=analise-carteira" 
                   class="px-4 py-2.5 bg-amber-400 hover:bg-amber-500 text-slate-900 rounded-2xl font-extrabold text-xs sm:text-sm transition-all shadow-md flex items-center gap-2 no-underline">
                    <i class="fa-solid fa-graduation-cap"></i> Treinamento &amp; Manual
                </a>
                <button onclick="exportarExcelCompleto()" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm border border-emerald-500/30">
                    <i class="fa-solid fa-file-excel"></i> Baixar Excel Completo
                </button>
            </div>
        </div>
    </div>

    <!-- BARRA DE PERÍODOS RÁPIDOS & FILTROS (GESTOR & REPRESENTANTE) -->
    <div class="ac-card p-4 sm:p-5 mb-5 space-y-4">
        <!-- Linha 1: Períodos Rápidos -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-100">
            <div class="flex flex-wrap items-center gap-2">
                <span class="ac-label text-slate-400 flex items-center gap-1">
                    <i class="fa-solid fa-bolt text-amber-500"></i> Período Rápido:
                </span>
                <div class="flex flex-wrap items-center gap-1 bg-slate-100/80 p-1 rounded-xl" id="botoes-periodo">
                    <button type="button" onclick="analiseCarteiraApp.aplicarPeriodoRapido('hoje')" data-periodo="hoje"
                        data-tooltip="Filtro Hoje|Filtra apenas os títulos com vencimento ou emissão na data de hoje."
                        class="btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition">Hoje</button>
                    <button type="button" onclick="analiseCarteiraApp.aplicarPeriodoRapido('este_mes')" data-periodo="este_mes"
                        data-tooltip="Este Mês|Filtra do primeiro até o último dia do mês corrente (padrão de cobrança)."
                        class="btn-periodo px-3 py-1 rounded-lg text-xs font-bold bg-emerald-600 text-white shadow-xs transition">Este Mês</button>
                    <button type="button" onclick="analiseCarteiraApp.aplicarPeriodoRapido('mes_passado')" data-periodo="mes_passado"
                        data-tooltip="Mês Passado|Filtra o mês civil imediatamente anterior para conciliação contábil."
                        class="btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition">Mês Passado</button>
                    <button type="button" onclick="analiseCarteiraApp.aplicarPeriodoRapido('30dias')" data-periodo="30dias"
                        data-tooltip="Últimos 30 Dias|Filtra o intervalo móvel dos últimos 30 dias retroativos a partir de hoje."
                        class="btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition">30 dias</button>
                    <button type="button" onclick="analiseCarteiraApp.aplicarPeriodoRapido('este_ano')" data-periodo="este_ano"
                        data-tooltip="Este Ano|Filtra desde 01 de janeiro até a data atual do ano fiscal vigente."
                        class="btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition">Este Ano</button>
                    <button type="button" onclick="analiseCarteiraApp.aplicarPeriodoRapido('todos')" data-periodo="todos"
                        data-tooltip="Todos os Períodos|Consulta toda a carteira histórica completa em aberto sem restrição de data."
                        class="btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition">Todos</button>
                    <button type="button" onclick="analiseCarteiraApp.togglePersonalizado()" data-periodo="personalizado"
                        data-tooltip="Datas Personalizadas|Permite escolher livremente a data inicial e final para filtrar os títulos."
                        class="btn-periodo px-2.5 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition">
                        <i class="fa-regular fa-calendar-days"></i>
                    </button>
                </div>
            </div>

            <!-- Base do Período (Vencimento vs Emissão) -->
            <div class="flex items-center gap-1.5">
                <span class="ac-label text-slate-400">Base:</span>
                <div class="inline-flex rounded-xl bg-slate-100 p-0.5" id="botoes-base">
                    <button type="button" onclick="analiseCarteiraApp.setTipoPeriodo('vencimento')" id="btn-base-vencimento"
                        data-tooltip="Base por Vencimento|Calcula os prazos e inadimplência tomando como base a data de vencimento dos boletos."
                        class="px-2.5 py-1 rounded-lg text-xs font-bold bg-white text-slate-800 shadow-xs transition">Vencimento</button>
                    <button type="button" onclick="analiseCarteiraApp.setTipoPeriodo('emissao')" id="btn-base-emissao"
                        data-tooltip="Base por Emissão|Calcula a carteira considerando a data em que os documentos e notas fiscais foram emitidos."
                        class="px-2.5 py-1 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-800 transition">Emissão</button>
                </div>
            </div>
        </div>

        <!-- Linha Personalizada (De / Até) -->
        <div id="painel-datas-personalizadas" style="display: none;" class="flex flex-wrap items-center gap-2 p-2.5 bg-blue-50/50 border border-blue-200/60 rounded-xl">
            <span class="text-xs font-bold text-slate-600">Período De:</span>
            <input type="date" id="input-data-inicio" class="px-2 py-1 text-xs border border-slate-300 rounded-lg bg-white font-semibold">
            <span class="text-xs font-bold text-slate-600">Até:</span>
            <input type="date" id="input-data-fim" class="px-2 py-1 text-xs border border-slate-300 rounded-lg bg-white font-semibold">
            <button type="button" onclick="analiseCarteiraApp.aplicarDatasPersonalizadas()"
                class="px-3 py-1 bg-blue-600 text-white rounded-lg text-xs font-bold hover:bg-blue-700 transition">Filtrar</button>
        </div>

        <!-- Linha 2: Filtros de Gestor e Representante -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block">
                    <span class="ac-label flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user-tie text-blue-600"></i> Gestor Responsável</span>
                        <span id="badge-gestor-escopo" class="text-[10px] text-slate-500 font-semibold"></span>
                    </span>
                    <select id="select-gestor" class="ac-input mt-1.5 cursor-pointer">
                        <option value="">Carregando gestores...</option>
                    </select>
                </label>
            </div>
            <div>
                <label class="block">
                    <span class="ac-label flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-user text-blue-500"></i> Representante Comercial</span>
                        <span id="badge-rep-escopo" class="text-[10px] text-slate-500 font-semibold"></span>
                    </span>
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
        <div class="kpi-card bg-gradient-to-br from-rose-50 to-red-50/70 border border-rose-200 shadow-sm hover:scale-[1.02] transition-all cursor-pointer" 
             onclick="scrollToTable('tabela-representantes')"
             data-tooltip="Total Vencido|Soma de duplicatas e boletos em aberto com atraso superior a 3 dias úteis (tolerância operacional da empresa). Clique para rolar até a tabela de representantes.">
            <div class="flex items-center justify-between text-rose-600 mb-1">
                <span class="ac-label text-rose-700 flex items-center gap-1">
                    Total Vencido <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                </span>
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
            </div>
            <div id="kpi-vencidos" class="text-xl sm:text-2xl font-black text-rose-600">R$ 0,00</div>
            <div id="kpi-vencidos-perc" class="text-[0.65rem] text-rose-700 font-bold mt-1">0% da carteira</div>
        </div>

        <!-- KPI 2: IAG (Índice de Atraso Geral) -->
        <div class="kpi-card bg-gradient-to-br from-amber-50 to-orange-50/70 border border-amber-200 shadow-sm hover:scale-[1.02] transition-all cursor-pointer"
             data-tooltip="IAG (Índice de Atraso Geral)|Percentual de inadimplência da carteira: (Total Vencido / Carteira Total) * 100. Classificação: Saudável (<5%), Atenção (5% a 15%) e Crítico (>15%).">
            <div class="flex items-center justify-between text-amber-600 mb-1">
                <span class="ac-label text-amber-700 flex items-center gap-1">
                    IAG (Inadimplência) <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                </span>
                <i class="fa-solid fa-percent text-xs"></i>
            </div>
            <div id="kpi-iag" class="text-xl sm:text-2xl font-black text-amber-600">0%</div>
            <div class="text-[0.65rem] text-amber-700 font-bold mt-1">Índice Atraso Geral</div>
        </div>

        <!-- KPI 3: Títulos Vencidos & Clientes -->
        <div class="kpi-card bg-gradient-to-br from-purple-50 to-indigo-50/70 border border-purple-200 shadow-sm hover:scale-[1.02] transition-all cursor-pointer"
             data-tooltip="Títulos Vencidos & Clientes Afetados|Quantidade física de boletos vencidos e contagem real de clientes únicos inadimplentes no escopo selecionado.">
            <div class="flex items-center justify-between text-purple-600 mb-1">
                <span class="ac-label text-purple-700 flex items-center gap-1">
                    Títulos Vencidos <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                </span>
                <i class="fa-solid fa-file-circle-xmark text-xs"></i>
            </div>
            <div id="kpi-titulos" class="text-xl sm:text-2xl font-black text-purple-700">0</div>
            <div id="kpi-titulos-clientes" class="text-[0.65rem] text-purple-700 font-bold mt-1 truncate">0 clientes afetados</div>
        </div>

        <!-- KPI 4: À Vencer (Saudável) -->
        <div class="kpi-card bg-gradient-to-br from-emerald-50 to-teal-50/70 border border-emerald-200 shadow-sm hover:scale-[1.02] transition-all cursor-pointer"
             data-tooltip="Carteira À Vencer|Saldo de títulos a vencer no prazo regular somado aos títulos com até 3 dias de atraso (dentro do prazo de tolerância).">
            <div class="flex items-center justify-between text-emerald-600 mb-1">
                <span class="ac-label text-emerald-700 flex items-center gap-1">
                    Carteira À Vencer <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                </span>
                <i class="fa-solid fa-calendar-check text-xs"></i>
            </div>
            <div id="kpi-a-vencer" class="text-xl sm:text-2xl font-black text-emerald-700">R$ 0,00</div>
            <div id="kpi-a-vencer-sub" class="text-[0.65rem] text-emerald-700 font-bold mt-1 truncate" title="Janela dos próximos 30 dias">Próx. 30 dias</div>
        </div>

        <!-- KPI 5: Carteira Total -->
        <div class="kpi-card bg-gradient-to-br from-blue-50 to-slate-100 border border-blue-200 shadow-sm hover:scale-[1.02] transition-all cursor-pointer"
             data-tooltip="Carteira Total|Volume total sob custódia de crédito a receber (Vencidos + À Vencer) de todos os clientes do escopo selecionado.">
            <div class="flex items-center justify-between text-blue-600 mb-1">
                <span class="ac-label text-blue-700 flex items-center gap-1">
                    Carteira Total <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                </span>
                <i class="fa-solid fa-wallet text-xs"></i>
            </div>
            <div id="kpi-carteira" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div id="kpi-carteira-info" class="text-[0.65rem] text-slate-500 font-bold mt-1 truncate">0 títulos | 0 clientes</div>
        </div>
    </div>
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
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-2 hover:bg-blue-100/70 transition cursor-help"
                     data-tooltip="Faixa 30 Dias|Títulos vencidos entre 4 e 33 dias atrás (atraso inicial recente). Contato amigável e reenvio de boleto.">
                    <span class="text-slate-500 block text-[0.65rem] font-bold flex items-center justify-center gap-1">
                        30 Dias <i class="fa-solid fa-circle-question text-[9px] opacity-75"></i>
                    </span>
                    <span class="font-black text-blue-700" id="pizza-30d">R$ 0</span>
                    <span class="text-[0.65rem] text-slate-400 block" id="pizza-30d-perc">0%</span>
                </div>
                <div class="bg-amber-50 border border-amber-100 rounded-xl p-2 hover:bg-amber-100/70 transition cursor-help"
                     data-tooltip="Faixa 60 Dias|Títulos vencidos entre 34 e 63 dias atrás (faixa de atenção / cobrança ativa e bloqueio preventivo de novos pedidos).">
                    <span class="text-slate-500 block text-[0.65rem] font-bold flex items-center justify-center gap-1">
                        60 Dias <i class="fa-solid fa-circle-question text-[9px] opacity-75"></i>
                    </span>
                    <span class="font-black text-amber-700" id="pizza-60d">R$ 0</span>
                    <span class="text-[0.65rem] text-slate-400 block" id="pizza-60d-perc">0%</span>
                </div>
                <div class="bg-rose-50 border border-rose-100 rounded-xl p-2 hover:bg-rose-100/70 transition cursor-help"
                     data-tooltip="Faixa +60 Dias|Títulos vencidos há mais de 63 dias atrás (risco elevado / encaminhamento para assessoria de cobrança jurídica).">
                    <span class="text-slate-500 block text-[0.65rem] font-bold flex items-center justify-center gap-1">
                        +60 Dias <i class="fa-solid fa-circle-question text-[9px] opacity-75"></i>
                    </span>
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

<!-- =================================================== -->
<!-- TOOLTIP MODERNO FLUTUANTE (RICH HOVER POPOVER DASH) -->
<!-- =================================================== -->
<div id="modernTooltipDash" class="pointer-events-none fixed z-[9999] hidden max-w-xs rounded-2xl bg-slate-900/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md border border-slate-700/60 transition-opacity duration-150">
    <div class="flex items-center justify-between gap-2 mb-1.5">
        <span id="tooltipDashBadge" class="text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50">INDICADOR</span>
        <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
    </div>
    <div id="tooltipDashTitle" class="font-black text-white text-xs mb-1">Título do Indicador</div>
    <div id="tooltipDashDesc" class="text-slate-300 text-[11px] font-medium leading-relaxed whitespace-pre-line">Explicação do item ao passar o mouse.</div>
</div>

<script src="<?= $appBase ?>/portal/assets/js/analise-carteira.js?v=<?= $jsVersion ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
