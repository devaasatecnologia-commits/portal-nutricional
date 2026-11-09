<?php
$pageTitle = 'Dashboard Financeiro | Nutricional';
$moduleJs = 'financeiro.js';
$verJs = @filemtime(__DIR__ . '/../assets/js/financeiro.js') ?: 1;
$verCss = @filemtime(__DIR__ . '/../assets/css/financeiro.css') ?: 1;
$verBase = @filemtime(__DIR__ . '/../assets/css/module-base.css') ?: 1;
$version = $verJs;
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $verBase . '">
<link rel="stylesheet" href="/portal/assets/css/financeiro.css?v=' . $verCss . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<!-- ====================================================================== -->
<!-- HEADER MOBILE FIXO -->
<!-- ====================================================================== -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">FINANCEIRO</span>
        </div>
        <a href="/portal/modules/treinamentos.php?modulo=financeiro" class="text-amber-400 text-lg no-underline" title="Treinamento e Manual">
            <i class="fa-solid fa-graduation-cap"></i>
        </a>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">

    <!-- Shimmer Loading -->
    <div id="shimmer" class="fixed top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 via-emerald-700 to-amber-400 bg-[length:200%_100%] animate-shimmer z-50 hidden"></div>

    <!-- ====================================================================== -->
    <!-- HEADER DESKTOP -->
    <!-- ====================================================================== -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">CONTROLADORIA FINANCEIRA</h2>
                <span id="user-display" class="text-xs text-slate-400 font-medium">CARREGANDO...</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/portal/modules/treinamentos.php?modulo=financeiro" 
               class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-slate-900 rounded-xl font-extrabold text-xs flex items-center gap-2 transition-all shadow-sm no-underline"
               title="Treinamento e Manual Operacional da Controladoria Financeira">
                <i class="fa-solid fa-graduation-cap"></i> Treinamento & Manual
            </a>
            <a href="/portal/modules/admin/metas-financeiro.php" id="btn-gerenciar-metas" style="display: none;" class="hidden px-4 py-2 bg-amber-500/10 hover:bg-amber-500/20 text-amber-800 border border-amber-300 rounded-xl text-xs font-black transition items-center gap-1.5 no-underline shadow-xs" title="Administrar Metas do Financeiro (Restrito a Administradores)">
                <i class="fa-solid fa-bullseye text-amber-600"></i>
                <span>GERENCIAR METAS</span>
            </a>
            <div class="text-right hidden sm:block pl-2 border-l border-slate-200">
                <div class="clock font-mono text-base lg:text-xl font-black" id="relogio">00:00:00</div>
                <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="flex flex-wrap items-end gap-4">
		<!-- Usuário (Visão) -->
<div class="flex-1 min-w-[200px]" id="container-user">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
        <i class="fa-solid fa-user mr-1"></i> Usuário (Visão)
    </label>
    <select id="global-user-filter" style="display:none;" onchange="app.changeUser(this.value)"
            class="w-full p-3 border-2 border-amber-200 bg-amber-50/50 rounded-xl text-sm font-semibold cursor-pointer hover:border-amber-400 transition-all">
    </select>
</div>

<!-- Filial -->
<div class="flex-1 min-w-[200px]" id="container-filial">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
        <i class="fa-solid fa-building mr-1"></i> Filial
    </label>
    <select id="select-filial" onchange="app.changeFilial(this.value)"
            class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-emerald-300 transition-all">
    </select>
</div>

            <div class="w-40" title="Janela de recuperação: considera apenas títulos vencidos nos últimos N dias (1 a 365). Vale para a Taxa de Recuperação e para os 3 cenários.">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-calendar mr-1"></i> Janela (dias)
                </label>
                <input type="number" id="filtro-dias-recup" value="120" min="1" max="365" onchange="app.onParamChange()"
                       class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-bold text-amber-600">
            </div>

            <div class="w-40" title="Cenário 1 (a cobrar): título vencido, sem apontamento, com atraso maior ou igual a este número de dias. É independente da janela.">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-hourglass-half mr-1"></i> Atraso mín. cen. 1
                </label>
                <input type="number" id="filtro-corte-atraso" value="8" min="1" max="365" onchange="app.onParamChange()"
                       class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-bold text-amber-600">
            </div>

            <button onclick="app.reset()"
                    class="px-6 py-3 bg-slate-700 text-white rounded-xl font-bold hover:bg-slate-800 transition-all flex items-center gap-2">
                <i class="fa-solid fa-rotate-left"></i> REINICIAR
            </button>


        </div>
    </div>

    <!-- Cards da Carteira -->
    <div id="erro-cards" class="fin-estado erro mb-4" style="display:none;"></div>
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Carteira Total</span>
            <span class="block text-2xl font-black text-slate-800" id="f-total">R$ 0,00</span>
            <span class="fin-card-formula">A vencer + vencido (saldo em aberto)</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">A vencer</span>
            <span class="block text-2xl font-black text-sky-600" id="f-avencer">R$ 0,00</span>
            <span class="fin-card-formula">Saldo de títulos ainda não vencidos</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Vencido 1–30d</span>
            <span class="block text-2xl font-black text-emerald-600" id="f-30">R$ 0,00</span>
            <span class="fin-card-formula">Saldo vencido há 1 a 30 dias</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Vencido 31–60d</span>
            <span class="block text-2xl font-black text-amber-600" id="f-60">R$ 0,00</span>
            <span class="fin-card-formula">Saldo vencido há 31 a 60 dias</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Vencido &gt; 60d</span>
            <span class="block text-2xl font-black text-rose-600" id="f-90">R$ 0,00</span>
            <span class="fin-card-formula">Saldo vencido há mais de 60 dias</span>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- METAS FINANCEIRAS ATIVAS (ACOMPANHAMENTO EXECUTIVO) -->
    <!-- ====================================================================== -->
    <div id="secao-metas-ativas" class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-bullseye text-amber-600"></i>
                <h4 class="text-xs font-extrabold text-slate-700 uppercase tracking-wider">METAS FINANCEIRAS & VIGÊNCIA</h4>
                <span class="text-[10px] text-slate-400 font-medium hidden sm:inline">• Atingimento corporativo</span>
            </div>
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] font-bold text-slate-400 uppercase mr-1">Ciclo:</span>
                <button type="button" onclick="app.setPeriodoMetas('mes_atual')" data-periodo="mes_atual" class="btn-periodo-meta px-2.5 py-1 rounded-lg text-[10px] font-black transition bg-amber-500 text-white shadow-xs">Este Mês</button>
                <button type="button" onclick="app.setPeriodoMetas('mes_passado')" data-periodo="mes_passado" class="btn-periodo-meta px-2.5 py-1 rounded-lg text-[10px] font-bold transition bg-slate-100 hover:bg-slate-200 text-slate-600">Mês Passado</button>
                <button type="button" onclick="app.setPeriodoMetas('trimestre')" data-periodo="trimestre" class="btn-periodo-meta px-2.5 py-1 rounded-lg text-[10px] font-bold transition bg-slate-100 hover:bg-slate-200 text-slate-600">Trimestre</button>
                <button type="button" onclick="app.setPeriodoMetas('ano_atual')" data-periodo="ano_atual" class="btn-periodo-meta px-2.5 py-1 rounded-lg text-[10px] font-bold transition bg-slate-100 hover:bg-slate-200 text-slate-600">Este Ano</button>
                <button type="button" onclick="app.setPeriodoMetas('todas')" data-periodo="todas" class="btn-periodo-meta px-2.5 py-1 rounded-lg text-[10px] font-bold transition bg-slate-100 hover:bg-slate-200 text-slate-600">Todas</button>
                <a href="/portal/modules/admin/metas-financeiro.php" class="ml-2 text-[11px] font-bold text-amber-700 hover:text-amber-800 flex items-center gap-1 no-underline">
                    <span>Configurar</span> <i class="fa-solid fa-chevron-right text-[9px]"></i>
                </a>
            </div>
        </div>
        <div id="grid-metas-progresso" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="col-span-full py-4 text-center text-xs text-slate-400 bg-white rounded-2xl border border-slate-100 shadow-sm">
                <i class="fa-solid fa-spinner fa-spin mr-1.5 text-amber-500"></i> Carregando metas ativas...
            </div>
        </div>
    </div>

    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-5 rounded-2xl border-l-8 border-rose-500 shadow-sm cursor-pointer hover:shadow-lg transition-all" onclick="app.showAudit('iag')">
            <label class="text-xs font-bold text-slate-400 uppercase block mb-1">Inadimplência Geral (IAG)</label>
            <span class="text-[10px] text-slate-400 block mb-2" title="IAG = saldo vencido ÷ carteira total. Semáforo da tabela: acima de 10% = CRÍTICO; de 5% a 10% = ATENÇÃO; abaixo de 5% = OK.">IAG = Saldo vencido ÷ Carteira total<br>Crítico &gt; 10% · Atenção ≥ 5% · OK &lt; 5%</span>
            <h3 class="text-3xl font-black text-slate-800 mb-1" id="kpi-iag">0.00%</h3>
            <div id="kpi-iag-valor" class="text-sm text-slate-500 font-semibold mb-3">R$ 0,00</div>
            <div class="text-xs font-bold text-amber-500 uppercase flex items-center gap-1">
                VER AUDITORIA <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border-l-8 border-amber-500 shadow-sm cursor-pointer hover:shadow-lg transition-all" onclick="app.showAudit('iap')">
            <label class="text-xs font-bold text-slate-400 uppercase block mb-1">Atraso Crítico (IAP)</label>
            <span class="text-[10px] text-slate-400 block mb-2">IAP = Saldo vencido há mais de 30 dias ÷ Carteira total<br>(faixas 31–60d + &gt;60d)</span>
            <h3 class="text-3xl font-black text-slate-800 mb-1" id="kpi-iap">0.00%</h3>
            <div id="kpi-iap-valor" class="text-sm text-slate-500 font-semibold mb-3">R$ 0,00</div>
            <div class="text-xs font-bold text-amber-500 uppercase flex items-center gap-1">
                VER AUDITORIA <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border-l-8 border-emerald-500 shadow-sm cursor-pointer hover:shadow-lg transition-all" onclick="app.showAudit('recup')">
            <label class="text-xs font-bold text-slate-400 uppercase block mb-1">Taxa de Recuperação</label>
            <span class="text-[10px] text-slate-400 block mb-2" title="Base = títulos vencidos na janela, com apontamento de cobrança, excluindo status 5 (renegociado/cancelado). Pago = saldo zerado. O apontamento só conta se foi feito até a data do pagamento (aproximada por vencimento + dias de atraso). Cenário 1 (sem apontamento) não entra na base.">Recuperação = Títulos pagos ÷ Títulos trabalhados<br>Trabalhados = com apontamento (cen. 2 + 3) · status 5 excluído · apontamento até a data do pagamento</span>
            <h3 class="text-3xl font-black text-slate-800 mb-1" id="kpi-recup">0.00%</h3>
            <div id="kpi-recup-quant" class="text-sm text-slate-500 font-semibold mb-3">0 de 0 títulos</div>
            <div class="text-xs font-bold text-amber-500 uppercase flex items-center gap-1">
                VER OS 3 CENÁRIOS <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- HISTÓRICO DE PERFORMANCE & ANÁLISE TEMPORAL (IAP / IAG / RECUPERAÇÃO) -->
    <!-- ====================================================================== -->
    <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 mb-6">
        <div class="flex justify-between items-start lg:items-center mb-4 flex-wrap gap-3 pb-3 border-b border-slate-100">
            <div>
                <h4 class="text-sm font-black text-slate-800 uppercase flex items-center gap-2">
                    <i class="fa-solid fa-chart-column text-emerald-700"></i>
                    <span>HISTÓRICO & AUDITORIA TEMPORAL (IAG / IAP / RECUPERAÇÃO)</span>
                </h4>
                <p class="text-[11px] text-slate-400 mt-0.5">Resultados diários gravados em snapshot com auto soma e médias pelo período apurado.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <!-- Seletor do Indicador -->
                <select id="hist-type" onchange="app.onHistoryParamChange()"
                        class="px-3 py-2 border-2 border-slate-200 bg-slate-50 rounded-xl text-xs font-bold text-slate-800">
                    <option value="iag_calculado" selected>Inadimplência Geral (IAG %)</option>
                    <option value="iap_calculado">Atraso Crítico +30d (IAP %)</option>
                    <option value="taxa_recuperacao">Taxa de Recuperação (%)</option>
                </select>

                <!-- Seletor de Dia da Semana -->
                <select id="hist-day" onchange="app.onHistoryParamChange()"
                        class="px-3 py-2 border-2 border-slate-200 bg-slate-50 rounded-xl text-xs font-bold text-slate-700">
                    <option value="todos" selected>Todos os Dias da Semana</option>
                    <option value="1">Apenas Segundas</option>
                    <option value="2">Apenas Terças</option>
                    <option value="3">Apenas Quartas</option>
                    <option value="4">Apenas Quintas</option>
                    <option value="5">Apenas Sextas</option>
                    <option value="6">Apenas Sábados</option>
                    <option value="0">Apenas Domingos</option>
                </select>
            </div>
        </div>

        <!-- Filtros Rápidos de Período -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 p-3 bg-slate-50/80 rounded-2xl border border-slate-200/80">
            <div class="flex flex-wrap items-center gap-1.5" id="botoes-periodo-hist">
                <span class="text-[10px] font-bold uppercase text-slate-400 mr-1.5 flex items-center gap-1">
                    <i class="fa-solid fa-clock-rotate-left"></i> Período:
                </span>
                <button type="button" onclick="app.setPeriodoHistorico('hoje')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="hoje">Hoje</button>
                <button type="button" onclick="app.setPeriodoHistorico('7dias')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="7dias">7 dias</button>
                <button type="button" onclick="app.setPeriodoHistorico('este_mes')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all bg-emerald-600 text-white shadow-xs" data-p="este_mes">Este Mês</button>
                <button type="button" onclick="app.setPeriodoHistorico('mes_passado')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="mes_passado">Mês Passado</button>
                <button type="button" onclick="app.setPeriodoHistorico('30dias')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="30dias">30 dias</button>
                <button type="button" onclick="app.setPeriodoHistorico('este_ano')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="este_ano">Este Ano</button>
                <button type="button" onclick="app.setPeriodoHistorico('todos')" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="todos">Todos</button>
                <button type="button" onclick="app.togglePeriodoPersonalizado()" class="btn-periodo-hist px-2.5 py-1 rounded-lg text-xs font-bold transition-all text-slate-600 hover:bg-slate-200" data-p="personalizado" title="Datas Personalizadas">
                    <i class="fa-regular fa-calendar-days"></i>
                </button>
            </div>

            <!-- Inputs Personalizados (aparece ao clicar) -->
            <div id="box-datas-personalizadas" class="hidden flex items-center gap-2">
                <input type="date" id="hist-data-inicio" class="p-1.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-700 bg-white">
                <span class="text-xs text-slate-400 font-bold">até</span>
                <input type="date" id="hist-data-fim" class="p-1.5 border border-slate-300 rounded-lg text-xs font-bold text-slate-700 bg-white">
                <button type="button" onclick="app.aplicarDatasPersonalizadas()" class="px-3 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-bold hover:bg-slate-900 transition">
                    Filtrar
                </button>
            </div>
        </div>

        <!-- ====================================================================== -->
        <!-- CARDS DE AUTO SOMA E MÉDIA PELO PERÍODO APURADO -->
        <!-- ====================================================================== -->
        <div id="resumo-apurado-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
            <div class="bg-gradient-to-br from-slate-50 to-slate-100/70 p-3.5 rounded-2xl border border-slate-200/80">
                <span class="text-[10px] font-bold uppercase text-slate-400 block mb-0.5">Dias Apurados</span>
                <span class="text-lg font-black text-slate-800" id="hist-resumo-dias">0</span>
                <span class="text-[10px] text-slate-500 block mt-0.5" id="hist-resumo-faixa">Intervalo</span>
            </div>
            <div class="bg-gradient-to-br from-emerald-50 to-emerald-100/40 p-3.5 rounded-2xl border border-emerald-200/80">
                <span class="text-[10px] font-bold uppercase text-emerald-700 block mb-0.5">Média Apurada</span>
                <span class="text-lg font-black text-emerald-800" id="hist-resumo-media">0,00%</span>
                <span class="text-[10px] text-emerald-600 block mt-0.5">Média simples</span>
            </div>
            <div class="bg-gradient-to-br from-indigo-50 to-indigo-100/40 p-3.5 rounded-2xl border border-indigo-200/80">
                <span class="text-[10px] font-bold uppercase text-indigo-700 block mb-0.5">Média Ponderada</span>
                <span class="text-lg font-black text-indigo-800" id="hist-resumo-ponderada">0,00%</span>
                <span class="text-[10px] text-indigo-600 block mt-0.5">Pelo volume total</span>
            </div>
            <div class="bg-gradient-to-br from-amber-50 to-amber-100/40 p-3.5 rounded-2xl border border-amber-200/80">
                <span class="text-[10px] font-bold uppercase text-amber-700 block mb-0.5">Auto Soma Vencidos</span>
                <span class="text-lg font-black text-amber-800" id="hist-resumo-soma-venc">R$ 0,00</span>
                <span class="text-[10px] text-amber-600 block mt-0.5">Soma no período</span>
            </div>
            <div class="bg-gradient-to-br from-sky-50 to-sky-100/40 p-3.5 rounded-2xl border border-sky-200/80">
                <span class="text-[10px] font-bold uppercase text-sky-700 block mb-0.5">Média Vencidos/Dia</span>
                <span class="text-lg font-black text-sky-800" id="hist-resumo-media-venc">R$ 0,00</span>
                <span class="text-[10px] text-sky-600 block mt-0.5">Exposição média</span>
            </div>
            <div class="bg-gradient-to-br from-purple-50 to-purple-100/40 p-3.5 rounded-2xl border border-purple-200/80">
                <span class="text-[10px] font-bold uppercase text-purple-700 block mb-0.5">Meta Corporativa</span>
                <span class="text-lg font-black text-purple-800" id="hist-resumo-meta">Sem meta</span>
                <span class="text-[10px] text-purple-600 block mt-0.5" id="hist-resumo-meta-status">—</span>
            </div>
        </div>

        <div class="text-[11px] text-slate-500 bg-amber-50 border border-amber-200 rounded-xl px-3 py-2 mb-3" id="hist-aviso">
            <i class="fa-solid fa-circle-info mr-1 text-amber-600"></i>
            <b>Histórico consolidado de fechamento:</b> clique sobre qualquer barra do gráfico para auditar os detalhes e saldos do fechamento registrado naquele dia.
        </div>
        <div style="height: 290px; width: 100%; position: relative;">
            <canvas id="chartHistory"></canvas>
            <div id="chart-estado" class="fin-estado"></div>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div id="b-path" class="inline-block px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold uppercase text-slate-600 mb-4">
        PAINEL GLOBAL

    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
       <button onclick="app.gerarRelatorio()"
        class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all flex items-center gap-2 shadow-md">
    <i class="fa-solid fa-file-pdf text-lg"></i>
    <span>GERAR RELATÓRIO DETALHADO</span>
</button>
	   <div class="overflow-x-auto">
            <div id="t-estado" class="fin-estado" style="display:none;"></div>
            <table class="w-full">
                <thead id="t-head" class="bg-slate-50 border-b border-slate-200"></thead>
                <tbody id="t-body"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- SCRIPT RELÓGIO -->
<!-- ====================================================================== -->
<script>
setInterval(() => {
    const agora = new Date();
    const horaFormatada = agora.toLocaleTimeString('pt-br');
    const dataFormatada = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
    const relogio = document.getElementById('relogio');
    const relogioMobile = document.getElementById('relogioMobile');
    const dataTopo = document.getElementById('data-topo');
    if (relogio) relogio.innerText = horaFormatada;
    if (relogioMobile) relogioMobile.innerText = horaFormatada;
    if (dataTopo) dataTopo.innerText = dataFormatada;
}, 1000);
</script>

<style>
@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.animate-shimmer {
    animation: shimmer 2s infinite linear;
}
.badge-atraso {
    background: #fee2e2;
    color: #ef4444;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 800;
}
.badge-sucesso {
    background: #dcfce7;
    color: #10b981;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 800;
}
</style>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>