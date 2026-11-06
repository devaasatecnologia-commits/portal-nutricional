<?php
$pageTitle = 'Dashboard Executivo de Vendas | Nutricional';
$moduleJs = 'dashboard-vendas.js';
$version = @filemtime(__DIR__ . '/../assets/js/dashboard-vendas.js') ?: 1;
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/dashboard-vendas.css?v=' . $version . '">
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="min-h-screen bg-slate-50 pb-12" x-data="dashboardVendasHandler()" x-init="init()">
    
    <!-- HEADER MOBILE -->
    <div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="/portal/" class="flex items-center gap-2 no-underline">
                <i class="fa-solid fa-arrow-left text-lg"></i>
                <span class="text-sm font-bold">VOLTAR</span>
            </a>
            <div class="text-center">
                <span class="text-sm font-bold modulo-nome">DASHBOARD VENDAS</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="/portal/modules/treinamentos.php?modulo=dashboard-vendas" class="px-2 py-1 rounded-lg bg-amber-400 text-slate-900 text-[10px] font-extrabold no-underline">
                    <i class="fa-solid fa-graduation-cap"></i>
                </a>
                <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
            </div>
        </div>
    </div>
    <div class="mobile-spacer block lg:hidden h-14"></div>

    <!-- HEADER DESKTOP PREMIUM -->
    <div class="glass rounded-3xl p-5 mb-6 flex flex-wrap justify-between items-center gap-4 shadow-sm bg-white border border-slate-100">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-1 no-underline">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-emerald-600 to-teal-700 text-white rounded-2xl flex items-center justify-center shadow-md">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-extrabold text-slate-800 leading-none">DASHBOARD EXECUTIVO DE VENDAS</h2>
                    <span class="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-extrabold uppercase tracking-wider">Especialista v3.0</span>
                </div>
                <span class="text-xs text-slate-400 font-medium">Visão 360° de Valor (R$), Peso Líquido (Kg/Ton), Quantidade (Un), 6 Regiões Unificadas e Projeções</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/portal/modules/treinamentos.php?modulo=dashboard-vendas"
               class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-amber-400 hover:bg-amber-500 text-slate-900 text-xs font-extrabold shadow-sm transition-all no-underline">
                <i class="fa-solid fa-graduation-cap"></i>
                <span>Treinamento &amp; Manual</span>
            </a>
            <div class="text-right hidden sm:block pl-2 border-l border-slate-100">
                <div class="clock font-mono text-base lg:text-xl font-black text-slate-800" id="relogio">00:00:00</div>
                <div class="data-topo text-[10px] lg:text-xs text-slate-400" id="data-topo">--/--/----</div>
            </div>
        </div>
    </div>

    <div class="max-w-full mx-auto px-4 lg:px-6">
        
        <!-- PAINEL DE FILTROS EXECUTIVO -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-5">
            <!-- Barra Superior: Atalhos Rápidos de Período + Botões de Ação -->
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">
                        <i class="fa-solid fa-bolt text-amber-500 mr-1"></i>Período rápido:
                    </span>
                    <button type="button" @click="definirPeriodoRapido('hoje')" data-tooltip="Filtro Hoje|Filtra apenas as vendas faturadas na data corrente."
                        :class="periodoRapidoAtivo === 'hoje' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Hoje</button>
                    <button type="button" @click="definirPeriodoRapido('mes_atual')" data-tooltip="Este Mês|Filtra do primeiro até o último dia do mês corrente."
                        :class="periodoRapidoAtivo === 'mes_atual' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Este Mês</button>
                    <button type="button" @click="definirPeriodoRapido('mes_passado')" data-tooltip="Mês Passado|Filtra o mês civil imediatamente anterior por completo."
                        :class="periodoRapidoAtivo === 'mes_passado' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Mês Passado</button>
                    <button type="button" @click="definirPeriodoRapido('ultimos_30')" data-tooltip="Últimos 30 Dias|Filtra o intervalo de 30 dias retroativos a partir de hoje."
                        :class="periodoRapidoAtivo === 'ultimos_30' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Últimos 30 dias</button>
                    <button type="button" @click="definirPeriodoRapido('ultimos_90')" data-tooltip="Últimos 90 Dias|Filtra o trimestre retroativo para análise de tendência média."
                        :class="periodoRapidoAtivo === 'ultimos_90' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Últimos 90 dias</button>
                    <button type="button" @click="definirPeriodoRapido('ano_atual')" data-tooltip="Este Ano|Filtra desde 01 de janeiro até 31 de dezembro do ano corrente."
                        :class="periodoRapidoAtivo === 'ano_atual' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Este Ano</button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button @click="abrirModalProjecao('representantes')" data-tooltip="Projeção Detalhada|Abre o simulador e detalhamento de metas e projeções por representante e região." class="px-3.5 py-2 bg-purple-50 text-purple-700 border border-purple-200 rounded-xl text-xs font-bold hover:bg-purple-100 transition-all">
                        <i class="fa-solid fa-bullseye mr-1.5"></i>Projeção Detalhada
                    </button>
                    <button @click="carregarDados()" data-tooltip="Atualizar Dados|Recarrega todos os KPIs, gráficos e tabelas com os filtros selecionados." class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-sm transition-all">
                        <i class="fa-solid fa-rotate-right mr-1.5" :class="loading ? 'fa-spin' : ''"></i>Atualizar
                    </button>
                    <button @click="exportarDados()" data-tooltip="Exportar CSV|Baixa uma planilha completa com todos os dados executivos do dashboard." class="px-4 py-2 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-bold hover:bg-emerald-50 transition-all">
                        <i class="fa-regular fa-file-excel mr-1.5"></i>Exportar Excel (.CSV)
                    </button>
                </div>
            </div>

            <!-- Grade Simétrica de Filtros Principais -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                <div class="sm:col-span-2 lg:col-span-2">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                        <i class="fa-regular fa-calendar mr-1"></i> Período Personalizado
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="date" x-model="filters.data_inicio" @change="mudarPeriodo()" class="w-full min-w-0 p-2.5 border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-emerald-500 outline-none">
                        <input type="date" x-model="filters.data_fim" @change="mudarPeriodo()" class="w-full min-w-0 p-2.5 border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-emerald-500 outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-building mr-1"></i> Filial
                    </label>
                    <select x-model="filters.filial" @change="mudarFilial()" class="w-full p-2.5 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-emerald-300 transition-all">
                        <option value="">Todas as Filiais</option>
                        <template x-for="f in filiaisPermitidas" :key="f.idfilial">
                            <option :value="f.idfilial" x-text="f.nome"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-user-tie mr-1 text-amber-500"></i> Gestor Comercial
                    </label>
                    <select x-model="filters.gestor" @change="mudarGestor()" class="w-full p-2.5 border-2 border-amber-200 bg-amber-50/50 rounded-xl text-sm font-semibold cursor-pointer hover:border-amber-400 transition-all">
                        <option value="">Todos os Gestores</option>
                        <template x-for="g in gestoresDisponiveis" :key="g.id">
                            <option :value="g.id" x-text="g.nome"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                        <i class="fa-solid fa-user mr-1"></i> Representante
                    </label>
                    <select x-model="filters.representante" @change="mudarRepresentante()" class="w-full p-2.5 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-emerald-300 transition-all">
                        <option value="">Todos os Representantes</option>
                        <template x-for="r in representantesDisponiveis" :key="r.id">
                            <option :value="r.id" x-text="r.nome"></option>
                        </template>
                    </select>
                </div>
            </div>
        </div>

        <div x-show="erro" x-cloak class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm"><i class="fa-solid fa-circle-exclamation mr-1"></i><span x-text="erro"></span></div>
        <div x-show="avisoPeriodo" x-cloak class="mb-4 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-700 text-sm"><i class="fa-solid fa-triangle-exclamation mr-1"></i><span x-text="avisoPeriodo"></span></div>
        <div x-show="loading" x-cloak class="mb-4 text-xs text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Atualizando indicadores executivos...</div>

        <!-- ================================================================== -->
        <!-- 8 CARDS PRINCIPAIS RASTREÁVEIS COM DESCRIÇÃO HOVER E TRIPLO FOCO   -->
        <!-- ================================================================== -->
        <div :class="loading ? 'opacity-60' : ''" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            
            <!-- 1. Faturamento Total -->
            <div class="group relative bg-gradient-to-br from-emerald-600 to-emerald-800 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Faturamento', 'Faturamento (NFs Faturadas)')"
                 data-tooltip="Faturamento Líquido|Soma de NFs de venda autorizadas (status=2, idtransacao=1) com encargos fiscais. Clique para auditar detalhes e rastreio de NFs.">
                <!-- Tooltip ao passar o mouse -->
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-emerald-100 text-[11px] uppercase tracking-wider font-extrabold">
                            Faturamento (NFs) <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl lg:text-3xl font-black mt-1" x-text="formatMoney(kpis.faturamento)">R$ 0,00</p>
                    </div>
                    <div class="w-11 h-11 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-dollar-sign text-lg"></i>
                    </div>
                </div>
                <!-- Chips de Peso e Quantidade em Foco -->
                <div class="mt-3 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-emerald-200 block text-[9px] uppercase font-bold">Peso Líquido</span>
                        <span class="font-extrabold text-white" x-text="formatPeso(kpis.peso_faturado)">0 kg</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-emerald-200 block text-[9px] uppercase font-bold">Volume / Clientes</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.quantidade_faturada) + ' un • ' + kpis.clientes_faturados + ' cli'"></span>
                    </div>
                </div>
                <div class="mt-2.5 flex items-center justify-between text-[11px]">
                    <span :class="variacaoPositiva(kpis.variacao_faturamento) ? 'text-emerald-200' : 'text-rose-200'" class="font-bold">
                        <i x-show="kpis.variacao_faturamento !== null" :class="variacaoPositiva(kpis.variacao_faturamento) ? 'fa-solid fa-arrow-trend-up' : 'fa-solid fa-arrow-trend-down'"></i>
                        <span x-text="variacaoTexto(kpis.variacao_faturamento)"></span>
                        <span class="font-normal text-emerald-100/80 ml-0.5" x-text="variacaoRotulo()"></span>
                    </span>
                    <span class="text-[10px] font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 2. NFs Emitidas & Volume Físico -->
            <div class="group relative bg-gradient-to-br from-blue-600 to-blue-800 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Notas', 'NFs Emitidas & Volume Faturado')"
                 data-tooltip="NFs Emitidas & Volume|Total de NFs faturadas no período, peso médio por nota e volume médio de unidades por pedido. Clique para listar todas as notas.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-blue-100 text-[11px] uppercase tracking-wider font-extrabold">
                            NFs Emitidas &amp; Volume <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl lg:text-3xl font-black mt-1">
                            <span x-text="formatNumber(kpis.notas)">0</span>
                            <span class="text-sm font-bold text-blue-200">NFs</span>
                        </p>
                    </div>
                    <div class="w-11 h-11 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-file-invoice text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-blue-200 block text-[9px] uppercase font-bold">Peso Médio / NF</span>
                        <span class="font-extrabold text-white" x-text="formatPeso(kpis.media_peso_nf)">0 kg</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-blue-200 block text-[9px] uppercase font-bold">Média Itens / NF</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.media_itens_nf) + ' un/NF'">0 un</span>
                    </div>
                </div>
                <div class="mt-2.5 flex items-center justify-between text-[11px]">
                    <span :class="variacaoPositiva(kpis.variacao_notas) ? 'text-blue-200' : 'text-rose-200'" class="font-bold">
                        <i x-show="kpis.variacao_notas !== null" :class="variacaoPositiva(kpis.variacao_notas) ? 'fa-solid fa-arrow-trend-up' : 'fa-solid fa-arrow-trend-down'"></i>
                        <span x-text="variacaoTexto(kpis.variacao_notas)"></span>
                        <span class="font-normal text-blue-100/80 ml-0.5">em nº de NFs</span>
                    </span>
                    <span class="text-[10px] font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 3. Ticket Médio & Preço Médio por Kg -->
            <div class="group relative bg-gradient-to-br from-amber-500 to-amber-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Ticket Médio', 'Auditoria de Ticket Médio & Preço/Kg')"
                 data-tooltip="Ticket Médio & Preço/Kg|Ticket médio por nota fiscal faturada e rentabilidade média apurada por quilo (R$/kg) e unidade (R$/un). Clique para auditar.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-amber-100 text-[11px] uppercase tracking-wider font-extrabold">
                            Ticket Médio / Preço Kg <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl lg:text-3xl font-black mt-1" x-text="formatMoney(kpis.ticket_medio)">R$ 0,00</p>
                    </div>
                    <div class="w-11 h-11 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-ticket text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-amber-100 block text-[9px] uppercase font-bold">Preço Médio / Kg</span>
                        <span class="font-extrabold text-white" x-text="formatMoney(kpis.preco_medio_kg) + '/kg'">R$ 0/kg</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-amber-100 block text-[9px] uppercase font-bold">Preço Médio / Un</span>
                        <span class="font-extrabold text-white" x-text="formatMoney(kpis.preco_medio_un) + '/un'">R$ 0/un</span>
                    </div>
                </div>
                <div class="mt-2.5 flex items-center justify-between text-[11px]">
                    <span :class="variacaoPositiva(kpis.variacao_ticket) ? 'text-amber-100' : 'text-rose-200'" class="font-bold">
                        <i x-show="kpis.variacao_ticket !== null" :class="variacaoPositiva(kpis.variacao_ticket) ? 'fa-solid fa-arrow-trend-up' : 'fa-solid fa-arrow-trend-down'"></i>
                        <span x-text="variacaoTexto(kpis.variacao_ticket)"></span>
                        <span class="font-normal text-amber-100/80 ml-0.5">vs anterior</span>
                    </span>
                    <span class="text-[10px] font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 4. Média por Dia Útil (Ritmo Diário) -->
            <div class="group relative bg-gradient-to-br from-purple-600 to-purple-800 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Vendas por Dia', 'Ritmo Diário de Vendas (Valor, Peso e Qtd)')"
                 data-tooltip="Média por Dia Útil|Ritmo diário de vendas (Faturamento, Peso e Qtd dividido pelos dias úteis decorridos). Base da projeção mensal. Clique para ver desempenho diário.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-purple-100 text-[11px] uppercase tracking-wider font-extrabold">
                            Média por Dia Útil <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl lg:text-3xl font-black mt-1" x-text="formatMoney(kpis.media_diaria)">R$ 0,00</p>
                    </div>
                    <div class="w-11 h-11 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-gauge-high text-lg"></i>
                    </div>
                </div>
                <div class="mt-3 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-purple-200 block text-[9px] uppercase font-bold">Peso / Dia Útil</span>
                        <span class="font-extrabold text-white" x-text="formatPeso(kpis.media_peso_diario) + '/d'">0 kg/d</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-purple-200 block text-[9px] uppercase font-bold">Qtd / Dia Útil</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.media_qtd_diaria) + ' un/d'">0 un/d</span>
                    </div>
                </div>
                <div class="mt-2.5 flex items-center justify-between text-[11px]">
                    <span class="text-purple-100 truncate" x-text="kpis.dias_uteis + ' dias úteis (' + kpis.dias_com_venda + ' dias c/ NF)'"></span>
                    <span class="text-[10px] font-bold bg-white/20 px-2 py-0.5 rounded-full shrink-0">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 5. Carteira de Pedidos em Aberto -->
            <div class="group relative bg-gradient-to-br from-sky-600 to-sky-800 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Carteira', 'Carteira de Pedidos em Aberto / Parciais')"
                 data-tooltip="Carteira de Pedidos|Saldo pendente em pedidos abertos (status=1) ou faturados parcialmente (status=4). Clique para rastrear cada pedido em aberto.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-sky-100 text-[11px] uppercase tracking-wider font-extrabold">
                            Carteira de Pedidos <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl font-black mt-1" x-text="formatMoney(kpis.carteira.valor)">R$ 0,00</p>
                    </div>
                    <div class="w-10 h-10 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-clipboard-check text-base"></i>
                    </div>
                </div>
                <div class="mt-2.5 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-sky-200 block text-[9px] uppercase font-bold">Peso em Carteira</span>
                        <span class="font-extrabold text-white" x-text="formatPeso(kpis.carteira.peso)">0 kg</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-sky-200 block text-[9px] uppercase font-bold">Pedidos / Itens</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.carteira.pedidos) + ' ped • ' + formatNumber(kpis.carteira.quantidade) + ' un'"></span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[10px] text-sky-100">
                    <span>Saldo pendente de faturamento</span>
                    <span class="font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 6. NFs Canceladas -->
            <div class="group relative bg-gradient-to-br from-rose-600 to-rose-800 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Cancelados', 'Auditorias de NFs Canceladas')"
                 data-tooltip="NFs Canceladas|Auditoria de notas fiscais de venda canceladas (status=3) no período. Já excluídas automaticamente do faturamento. Clique para auditar.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-rose-100 text-[11px] uppercase tracking-wider font-extrabold">
                            NFs Canceladas <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl font-black mt-1" x-text="formatMoney(kpis.cancelados.valor)">R$ 0,00</p>
                    </div>
                    <div class="w-10 h-10 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-ban text-base"></i>
                    </div>
                </div>
                <div class="mt-2.5 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-rose-200 block text-[9px] uppercase font-bold">Peso Cancelado</span>
                        <span class="font-extrabold text-white" x-text="formatPeso(kpis.cancelados.peso)">0 kg</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-rose-200 block text-[9px] uppercase font-bold">Qtd. Documentos</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.cancelados.notas) + ' NFs'">0 NFs</span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[10px] text-rose-100">
                    <span>Excluídas do faturamento líquido</span>
                    <span class="font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 7. Devoluções de Venda -->
            <div class="group relative bg-gradient-to-br from-orange-500 to-orange-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Devoluções', 'Auditoria de Devoluções de Venda')"
                 data-tooltip="Devoluções de Venda|Notas de devolução de venda emitidas (NFe entrada). Abatimentos e impacto sobre o faturamento bruto. Clique para rastrear.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-orange-100 text-[11px] uppercase tracking-wider font-extrabold">
                            Devoluções <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl font-black mt-1" x-text="formatMoney(kpis.devolucoes.valor)">R$ 0,00</p>
                    </div>
                    <div class="w-10 h-10 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-rotate-left text-base"></i>
                    </div>
                </div>
                <div class="mt-2.5 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-orange-200 block text-[9px] uppercase font-bold">Peso Devolvido</span>
                        <span class="font-extrabold text-white" x-text="formatPeso(kpis.devolucoes.peso)">0 kg</span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-orange-200 block text-[9px] uppercase font-bold">NFs / Itens Dev.</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.devolucoes.notas) + ' NFs • ' + formatNumber(kpis.devolucoes.quantidade) + ' un'"></span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[10px] text-orange-100">
                    <span x-text="(kpis.devolucoes.percentual_faturamento || 0) + '% sobre o faturamento'"></span>
                    <span class="font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>

            <!-- 8. Positivação da Carteira -->
            <div class="group relative bg-gradient-to-br from-teal-600 to-teal-800 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:-translate-y-1 transition-all"
                 @click="abrirDetalhesCard('Positivação', 'Positivação da Carteira por Representante')"
                 data-tooltip="Positivação da Carteira|Clientes com ao menos 1 compra no período dividido pela base de clientes ativos da carteira do escopo. Clique para ranking de positivação.">
                
                <div class="flex justify-between items-start">
                    <div>
                        <span class="inline-flex items-center gap-1 text-teal-100 text-[11px] uppercase tracking-wider font-extrabold">
                            Positivação Carteira <i class="fa-solid fa-circle-question text-[10px] opacity-75"></i>
                        </span>
                        <p class="text-2xl font-black mt-1" x-text="(kpis.positivacao.percentual || 0).toLocaleString('pt-BR') + '%'">0%</p>
                    </div>
                    <div class="w-10 h-10 bg-white/15 rounded-xl flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-user-check text-base"></i>
                    </div>
                </div>
                <div class="mt-2.5 grid grid-cols-2 gap-1.5 bg-black/15 rounded-xl p-2 text-[11px]">
                    <div>
                        <span class="text-teal-200 block text-[9px] uppercase font-bold">Positivados / Base</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.positivacao.positivados) + ' / ' + formatNumber(kpis.positivacao.base)"></span>
                    </div>
                    <div class="border-l border-white/15 pl-2">
                        <span class="text-teal-200 block text-[9px] uppercase font-bold">Sem Compra Período</span>
                        <span class="font-extrabold text-white" x-text="formatNumber(kpis.positivacao.nao_positivados || Math.max((kpis.positivacao.base || 0) - (kpis.positivacao.positivados || 0), 0)) + ' cli'"></span>
                    </div>
                </div>
                <div class="mt-2 flex items-center justify-between text-[10px] text-teal-100">
                    <span>Cobertura ativa do escopo</span>
                    <span class="font-bold bg-white/20 px-2 py-0.5 rounded-full">Rastrear <i class="fa-solid fa-arrow-up-right-from-square ml-0.5 text-[9px]"></i></span>
                </div>
            </div>
        </div>

        <!-- ================================================================== -->
        <!-- GRÁFICO DE EVOLUÇÃO MULTIMÉTRICA + PROJEÇÃO MENSAL E TENDÊNCIA     -->
        <!-- ================================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Evolução de Vendas -->
            <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex flex-col">
                <div class="flex flex-wrap justify-between items-start gap-3 mb-3">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase flex items-center gap-2">
                            <i class="fa-solid fa-chart-area text-emerald-600"></i>
                            <span>EVOLUÇÃO DE VENDAS — VALOR, PESO E VOLUME</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Alterne entre visão combinada (R$ + Kg), apenas Valor, Peso Líquido, Quantidade ou NFs e agrupe por Dia, Semana ou Mês.</p>
                    </div>
                    <!-- Seletor de Agrupamento de Período -->
                    <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl">
                        <span class="text-[10px] font-bold text-slate-400 px-2 uppercase">Período:</span>
                        <button type="button" @click="mudarPeriodoEvolucao('auto')" :class="periodoEvolucao === 'auto' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-2 py-1 rounded-lg text-[11px] font-bold transition-all">Auto</button>
                        <button type="button" @click="mudarPeriodoEvolucao('dia')" :class="periodoEvolucao === 'dia' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-2 py-1 rounded-lg text-[11px] font-bold transition-all">Diário</button>
                        <button type="button" @click="mudarPeriodoEvolucao('semana')" :class="periodoEvolucao === 'semana' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-2 py-1 rounded-lg text-[11px] font-bold transition-all">Semanal</button>
                        <button type="button" @click="mudarPeriodoEvolucao('mes')" :class="periodoEvolucao === 'mes' ? 'bg-white text-slate-800 shadow-sm' : 'text-slate-500 hover:text-slate-700'" class="px-2 py-1 rounded-lg text-[11px] font-bold transition-all">Mensal</button>
                    </div>
                </div>

                <!-- Botões de Métrica do Gráfico -->
                <div class="flex flex-wrap items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-100">
                    <div class="flex flex-wrap gap-1.5">
                        <button type="button" @click="mudarTipoGrafico('combinado')" :class="tipoGrafico === 'combinado' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">
                            <i class="fa-solid fa-layer-group mr-1"></i>Combinado (R$ + Kg)
                        </button>
                        <button type="button" @click="mudarTipoGrafico('valor')" :class="tipoGrafico === 'valor' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">
                            Valor (R$)
                        </button>
                        <button type="button" @click="mudarTipoGrafico('peso')" :class="tipoGrafico === 'peso' ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">
                            Peso Líquido (Kg)
                        </button>
                        <button type="button" @click="mudarTipoGrafico('quantidade')" :class="tipoGrafico === 'quantidade' ? 'bg-blue-100 text-blue-800 border border-blue-300' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">
                            Quantidade (Un)
                        </button>
                        <button type="button" @click="mudarTipoGrafico('notas')" :class="tipoGrafico === 'notas' ? 'bg-purple-100 text-purple-800 border border-purple-300' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">
                            Nº de NFs
                        </button>
                    </div>

                    <!-- Mini-resumo da série exibida -->
                    <div class="flex flex-wrap items-center gap-3 text-[11px] bg-slate-50 px-3 py-1 rounded-xl border border-slate-100" x-show="itensEvolucaoAtiva().length > 0">
                        <span>Média: <strong class="text-emerald-700" x-text="formatMoney(resumoEvolucaoAtiva().media_valor)"></strong> (<strong class="text-amber-700" x-text="formatPeso(resumoEvolucaoAtiva().media_peso)"></strong>)</span>
                        <span class="text-slate-300">|</span>
                        <span>Pico (<span x-text="resumoEvolucaoAtiva().pico_label"></span>): <strong class="text-slate-800" x-text="formatMoney(resumoEvolucaoAtiva().pico_valor)"></strong></span>
                    </div>
                </div>

                <div x-show="!loading && itensEvolucaoAtiva().length === 0" class="text-sm text-slate-400 text-center py-16">Sem vendas registradas para o período selecionado.</div>
                <div id="chartEvolucao" x-show="itensEvolucaoAtiva().length > 0" style="height: 320px; width: 100%;"></div>
            </div>

            <!-- Projeção Histórica & Fechamento (M-3 a M+1) -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex flex-col">
                <div class="flex items-start justify-between gap-2 mb-2">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase">
                            <i class="fa-solid fa-chart-line mr-1.5 text-indigo-600"></i> HISTÓRICO &amp; TENDÊNCIA MULTIMENSAL (M-3 A M+1)
                        </h3>
                        <p class="text-[10px] text-slate-400 mt-0.5">Histórico comparativo de 3 meses + Mês Atual e Tendência estimada M+1</p>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <button type="button" @click="abrirModalProjecao('representantes')" title="Expandir Projeção por Representante e Região"
                            class="px-2.5 py-1 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 text-[11px] font-bold transition-all">
                            <i class="fa-solid fa-up-right-from-square mr-1"></i>Expandir
                        </button>
                        <button type="button" @click="exportarProjecaoExcel()" title="Exportar Projeção Completa para Excel"
                            class="px-2 py-1 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 text-[11px] font-bold transition-all">
                            <i class="fa-regular fa-file-excel"></i>
                        </button>
                    </div>
                </div>

                <div id="projecaoContainer" class="space-y-2.5 flex-1 overflow-y-auto pr-1 mt-2 max-h-[340px]">
                    <div x-show="loadingPesados && projecao.length === 0" class="space-y-3">
                        <div class="bg-slate-50 rounded-xl p-3 animate-pulse h-16"></div>
                        <div class="bg-slate-50 rounded-xl p-3 animate-pulse h-16"></div>
                    </div>
                    <div x-show="projecao.length > 0" class="space-y-2">
                        <template x-for="(item, idx) in projecao" :key="idx">
                            <div class="rounded-xl p-3 border transition-all cursor-pointer hover:shadow-sm"
                                 @click="abrirModalProjecao(item.atual ? 'representantes' : 'historico')"
                                 :class="item.atual ? 'bg-indigo-50/80 border-indigo-200' : (item.realizado ? 'bg-slate-50 border-slate-100' : 'bg-amber-50/60 border-amber-200')">
                                <div class="flex justify-between items-center mb-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-extrabold text-slate-800" x-text="item.mes_label"></span>
                                        <span x-show="item.atual" class="px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[9px] font-bold uppercase">Mês Atual</span>
                                        <span x-show="item.tendencia" class="px-1.5 py-0.5 rounded bg-amber-500 text-white text-[9px] font-bold uppercase">Tendência M+1</span>
                                    </div>
                                    <span :class="item.atual ? 'text-indigo-700' : (item.realizado ? 'text-emerald-600' : 'text-amber-700')" class="text-sm font-black">
                                        <span x-text="formatMoney(item.atual ? item.valor_projetado : item.valor)"></span>
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5 mb-1.5">
                                    <div class="h-1.5 rounded-full"
                                         :class="item.atual ? 'bg-indigo-600' : (item.realizado ? 'bg-emerald-500' : 'bg-amber-500')"
                                         :style="'width: ' + Math.min(((item.atual ? item.valor_projetado : item.valor) / maxProjecao * 100), 100) + '%'"></div>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-1 text-[10px] text-slate-600">
                                    <span class="font-semibold text-amber-700">
                                        <i class="fa-solid fa-weight-hanging mr-0.5"></i>
                                        <span x-text="formatPeso(item.atual ? item.peso_projetado : item.peso)"></span>
                                    </span>
                                    <span x-show="item.realizado" x-text="formatNumber(item.quantidade) + ' un • ' + item.notas + ' NFs'"></span>
                                    <span x-show="item.atual" class="text-indigo-700 font-bold" x-text="'Realizado: ' + formatMoney(item.valor) + ' (' + formatPesoTon(item.peso) + ')' + (item.carteira_aberta > 0 ? ' • Cart: ' + formatMoney(item.carteira_aberta) : '')"></span>
                                    <span x-show="item.tendencia" x-text="'Média ponderada (' + item.dias_uteis + 'd úteis)'"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="erroPesados" x-cloak class="text-xs text-rose-600" x-text="erroPesados"></div>
                    <div x-show="!loadingPesados && projecao.length === 0" class="text-center py-8 text-slate-400">
                        <i class="fa-solid fa-chart-line text-3xl mb-2 block"></i>
                        <p class="text-sm">Nenhuma projeção disponível</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ================================================================== -->
        <!-- TOP 10 PRODUTOS + TOP 10 CLIENTES (FONTE AJUSTADA SEM TRUNCAR)     -->
        <!-- ================================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Top 10 Produtos -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase">
                            <i class="fa-solid fa-cube mr-2 text-emerald-600"></i> TOP 10 PRODUTOS MAIS VENDIDOS
                        </h3>
                        <p class="text-[10px] text-slate-400">Clique em qualquer produto para abrir a ficha analítica com Top Clientes e evolução.</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] font-bold">Top 10</span>
                </div>
                <div class="divide-y divide-slate-100 max-h-[430px] overflow-y-auto" x-show="topProdutos.length > 0">
                    <template x-for="(item, idx) in topProdutos" :key="idx">
                        <div class="px-4 py-2.5 hover:bg-emerald-50/40 transition-colors cursor-pointer" @click="verDetalhesProduto(item.iditem, item.produto)">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                    <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[11px] font-extrabold shrink-0 mt-0.5"
                                          :class="{'bg-amber-500 text-white': idx === 0, 'bg-slate-300 text-slate-800': idx === 1, 'bg-amber-700 text-white': idx === 2, 'bg-slate-100 text-slate-600': idx > 2}"
                                          x-text="idx + 1"></span>
                                    <div class="min-w-0 flex-1">
                                        <!-- Fonte ajustada (text-xs leading-snug break-words) para exibir o nome completo sem cortar -->
                                        <p class="text-xs font-bold text-slate-800 leading-snug break-words" x-text="item.produto"></p>
                                        <div class="flex flex-wrap items-center gap-2 mt-0.5 text-[10px] text-slate-400">
                                            <span class="font-medium text-slate-500" x-text="item.grupo"></span>
                                            <span>•</span>
                                            <span x-text="(item.notas || 0) + ' NFs'"></span>
                                            <span>•</span>
                                            <span x-text="(item.clientes || 0) + ' clientes'"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-black text-emerald-600" x-text="formatMoney(item.valor)"></p>
                                    <p class="text-[10px] font-bold text-amber-600" x-text="formatPeso(item.peso)"></p>
                                    <p class="text-[10px] text-slate-400" x-text="formatNumber(item.quantidade) + ' un.'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="topProdutos.length === 0" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-cube text-3xl mb-2 block"></i>
                    <p class="text-sm">Nenhum produto encontrado no período</p>
                </div>
            </div>

            <!-- Top 10 Clientes -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase">
                            <i class="fa-solid fa-users mr-2 text-blue-600"></i> TOP 10 CLIENTES POR FATURAMENTO
                        </h3>
                        <p class="text-[10px] text-slate-400">Clique no cliente para auditar o mix de produtos comprados, peso e ticket médio.</p>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200 text-[10px] font-bold">Top 10</span>
                </div>
                <div class="divide-y divide-slate-100 max-h-[430px] overflow-y-auto" x-show="topClientes.length > 0">
                    <template x-for="(item, idx) in topClientes" :key="idx">
                        <div class="px-4 py-2.5 hover:bg-blue-50/40 transition-colors cursor-pointer" @click="verDetalhesCliente(item.idcliforemp, item.cliente)">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                    <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[11px] font-extrabold shrink-0 mt-0.5"
                                          :class="{'bg-amber-500 text-white': idx === 0, 'bg-slate-300 text-slate-800': idx === 1, 'bg-amber-700 text-white': idx === 2, 'bg-slate-100 text-slate-600': idx > 2}"
                                          x-text="idx + 1"></span>
                                    <div class="min-w-0 flex-1">
                                        <!-- Fonte ajustada (text-xs leading-snug break-words) para exibir o nome completo sem cortar -->
                                        <p class="text-xs font-bold text-slate-800 leading-snug break-words" x-text="item.cliente"></p>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-0.5 text-[10px] text-slate-400">
                                            <span class="px-1.5 py-0.2 rounded bg-indigo-50 text-indigo-700 font-bold" x-text="item.regiao"></span>
                                            <span x-show="item.cidade" x-text="item.cidade"></span>
                                            <span>•</span>
                                            <span x-text="'Ticket: ' + formatMoney(item.ticket_medio)"></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-black text-emerald-600" x-text="formatMoney(item.valor)"></p>
                                    <p class="text-[10px] font-bold text-amber-600" x-text="formatPeso(item.peso)"></p>
                                    <p class="text-[10px] text-slate-400" x-text="formatNumber(item.quantidade) + ' un • ' + item.total_notas + ' NFs'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="topClientes.length === 0" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-users text-3xl mb-2 block"></i>
                    <p class="text-sm">Nenhum cliente encontrado no período</p>
                </div>
            </div>
        </div>

        <!-- ================================================================== -->
        <!-- DISTRIBUIÇÃO PELAS 6 REGIÕES UNIFICADAS (SUL, GRF, AVI, NOR, PLN, RMC) -->
        <!-- ================================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Painel das 6 Regiões Unificadas (2 colunas) -->
            <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4 pb-3 border-b border-slate-100">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase flex items-center gap-2">
                            <i class="fa-solid fa-map-location-dot text-indigo-600"></i>
                            <span>DISTRIBUIÇÃO POR REGIÃO DO ESCOPO</span>
                        </h3>
                        <p class="text-[11px] text-slate-400 mt-0.5">Macro-regiões comerciais ativas no escopo liberado. Clique em qualquer região para auditar representantes e NFs.</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="mudarMetricaRegiao('valor')" :class="metricaRegiao === 'valor' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">Por Valor (R$)</button>
                        <button type="button" @click="mudarMetricaRegiao('peso')" :class="metricaRegiao === 'peso' ? 'bg-amber-500 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">Por Peso (Kg)</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center">
                    <!-- Gráfico Donut -->
                    <div class="md:col-span-5">
                        <div id="chartRegiao" style="height: 290px; width: 100%;"></div>
                    </div>

                    <!-- Grade de Cards Rastreáveis das 6 Regiões -->
                    <div class="md:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <template x-for="(reg, rIdx) in distribuicaoRegiao" :key="reg.regiao">
                            <div @click="abrirDetalhesRegiao(reg)"
                                 class="p-3 rounded-xl border border-slate-200/80 hover:border-indigo-300 bg-slate-50/60 hover:bg-indigo-50/30 transition-all cursor-pointer group">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="'background-color:' + (reg.cor || '#059669')"></span>
                                        <span class="text-xs font-black text-slate-800" x-text="reg.regiao"></span>
                                        <span class="text-[10px] text-slate-500 truncate" x-text="reg.nome"></span>
                                    </div>
                                    <span class="text-[10px] font-extrabold px-1.5 py-0.5 rounded bg-white border border-slate-200 text-slate-700"
                                          x-text="(metricaRegiao === 'peso' ? reg.participacao_peso : reg.participacao) + '%'"></span>
                                </div>
                                <div class="flex items-baseline justify-between mt-1">
                                    <span class="text-sm font-black text-emerald-700" x-text="formatMoney(reg.valor)"></span>
                                    <span class="text-[11px] font-bold text-amber-700" x-text="formatPeso(reg.peso)"></span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-1.5 my-1.5">
                                    <div class="h-1.5 rounded-full transition-all"
                                         :style="'background-color:' + (reg.cor || '#059669') + '; width:' + Math.min(metricaRegiao === 'peso' ? reg.participacao_peso : reg.participacao, 100) + '%'"></div>
                                </div>
                                <div class="flex items-center justify-between text-[10px] text-slate-500">
                                    <span x-text="formatNumber(reg.quantidade) + ' un • ' + reg.notas + ' NFs'"></span>
                                    <span class="group-hover:text-indigo-600 font-semibold"><span x-text="reg.clientes + ' cli'"></span> <i class="fa-solid fa-magnifying-glass-chart ml-0.5"></i></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Matriz Cross-Selling (Produtos Comprados Juntos) -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100 flex flex-col">
                <div class="mb-3 pb-2 border-b border-slate-100">
                    <h3 class="text-sm font-extrabold text-slate-800 uppercase">
                        <i class="fa-solid fa-code-branch mr-2 text-purple-600"></i> PRODUTOS COMPRADOS JUNTOS
                    </h3>
                    <p class="text-[10px] text-slate-400 mt-0.5">Top pares de itens com maior recorrência na mesma NF (Cross-Selling).</p>
                </div>
                <div class="space-y-2.5 max-h-[330px] overflow-y-auto flex-1 pr-1" x-show="matrizCrossSelling.length > 0">
                    <template x-for="(item, idx) in matrizCrossSelling" :key="idx">
                        <div class="bg-gradient-to-r from-purple-50/70 to-white rounded-xl p-3 border border-purple-100">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-start gap-2 min-w-0 flex-1">
                                    <span class="w-6 h-6 bg-purple-100 text-purple-700 rounded-lg flex items-center justify-center text-[11px] font-extrabold shrink-0 mt-0.5" x-text="idx + 1"></span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-[11px] font-bold text-slate-800 leading-snug break-words" x-text="item.produto1 + ' + ' + item.produto2"></p>
                                        <p class="text-[10px] text-slate-500 mt-0.5">
                                            Juntos em <strong class="text-purple-700" x-text="item.vezes_comprados_juntos"></strong> NFs
                                            • <span class="text-amber-700 font-semibold" x-text="formatPeso(item.peso_total || 0)"></span>
                                        </p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-black text-purple-700" x-text="formatMoney(item.valor_total)"></p>
                                    <p class="text-[10px] text-slate-400" x-text="formatNumber(item.qtd_total || 0) + ' un.'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="loadingPesados && matrizCrossSelling.length === 0" class="space-y-2.5">
                    <div class="bg-slate-100 rounded-xl p-3 animate-pulse h-14"></div>
                    <div class="bg-slate-100 rounded-xl p-3 animate-pulse h-14"></div>
                </div>
                <div x-show="!loadingPesados && matrizCrossSelling.length === 0" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-code-branch text-3xl mb-2 block"></i>
                    <p class="text-xs" x-text="mensagemPesados || 'Nenhum relacionamento encontrado no período'"></p>
                </div>
            </div>
        </div>

        <!-- ================================================================== -->
        <!-- TOP 10 REPRESENTANTES + FUNIL DE PEDIDOS + PROJEÇÃO DE FECHAMENTO  -->
        <!-- ================================================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div x-show="erroInsights" x-cloak class="lg:col-span-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm" x-text="erroInsights"></div>
            
            <!-- Top 10 Representantes -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase">
                            <i class="fa-solid fa-ranking-star mr-2 text-amber-500"></i> TOP 10 REPRESENTANTES
                        </h3>
                        <p class="text-[10px] text-slate-400">Clique no representante para ver carteira, produtos e evolução.</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 border border-amber-200 text-[10px] font-bold">Top 10</span>
                </div>
                <div class="divide-y divide-slate-100 max-h-[380px] overflow-y-auto flex-1" x-show="rankingRepresentantes.length > 0">
                    <template x-for="(item, idx) in rankingRepresentantes" :key="idx">
                        <div class="px-4 py-2.5 hover:bg-amber-50/40 cursor-pointer transition-colors" @click="verDetalhesRepresentante(item.id, item.nome)">
                            <div class="flex items-start justify-between gap-2.5">
                                <div class="flex items-start gap-2.5 min-w-0 flex-1">
                                    <span class="w-6 h-6 rounded-lg flex items-center justify-center text-[11px] font-extrabold shrink-0 mt-0.5"
                                          :class="{'bg-amber-500 text-white': idx === 0, 'bg-slate-300 text-slate-800': idx === 1, 'bg-amber-700 text-white': idx === 2, 'bg-slate-100 text-slate-600': idx > 2}"
                                          x-text="idx + 1"></span>
                                    <div class="min-w-0 flex-1">
                                        <!-- Fonte ajustada sem truncar o nome do representante -->
                                        <p class="text-xs font-bold text-slate-800 leading-snug break-words" x-text="item.nome"></p>
                                        <p class="text-[10px] text-slate-400 mt-0.5" x-text="item.total_notas + ' NFs • ' + item.clientes_ativos + ' cli • Ticket: ' + formatMoney(item.ticket_medio)"></p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-xs font-black text-emerald-600" x-text="formatMoney(item.valor)"></p>
                                    <p class="text-[10px] font-bold text-amber-600" x-text="formatPeso(item.peso)"></p>
                                    <p class="text-[10px] text-slate-400" x-text="formatNumber(item.quantidade) + ' un.'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="rankingRepresentantes.length === 0" class="text-center py-8 text-slate-400">
                    <p class="text-sm">Nenhum dado disponível</p>
                </div>
            </div>

            <!-- Funil de Pedidos (com Valor, Peso e Quantidade) -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                    <h3 class="text-sm font-extrabold text-slate-800 uppercase">
                        <i class="fa-solid fa-filter-circle-dollar mr-2 text-blue-600"></i> FUNIL DE PEDIDOS DO PERÍODO
                    </h3>
                    <p class="text-[10px] text-slate-400 mt-0.5">Clique na etapa para auditar os pedidos com valor, peso e itens.</p>
                </div>
                <div class="px-4 pt-3 flex flex-wrap gap-3 text-xs" x-show="funilPedidos.length > 0">
                    <span class="px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">
                        Conversão NFs: <span x-text="funilTaxas.conversao === null ? '—' : funilTaxas.conversao + '%'"></span>
                    </span>
                    <span class="px-2.5 py-1 rounded-lg bg-rose-50 text-rose-700 font-bold border border-rose-200">
                        Cancelamento: <span x-text="funilTaxas.cancelamento === null ? '—' : funilTaxas.cancelamento + '%'"></span>
                    </span>
                </div>
                <div class="p-4 space-y-3.5 flex-1" x-show="funilPedidos.length > 0">
                    <template x-for="(item, idx) in funilPedidos" :key="idx">
                        <div class="cursor-pointer p-2.5 rounded-xl bg-slate-50/70 hover:bg-blue-50/50 border border-slate-100 transition-all" @click="verDetalhesFunil(item.etapa)">
                            <div class="flex justify-between items-center text-xs mb-1">
                                <span class="font-extrabold text-slate-700" x-text="item.etapa"></span>
                                <span class="font-bold text-slate-600" x-text="item.quantidade + ' pedidos (' + formatNumber(item.itens || 0) + ' un)'"></span>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2 mb-1.5">
                                <div class="h-2 rounded-full" :class="corFunil(item)" :style="'width: ' + larguraFunil(item) + '%'"></div>
                            </div>
                            <div class="flex justify-between items-center text-[11px]">
                                <span class="font-black text-emerald-700" x-text="formatMoney(item.valor)"></span>
                                <span class="font-bold text-amber-700"><i class="fa-solid fa-weight-hanging mr-1"></i><span x-text="formatPeso(item.peso || 0)"></span></span>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="funilPedidos.length === 0" class="text-center py-8 text-slate-400">
                    <p class="text-sm">Nenhum dado disponível</p>
                </div>
            </div>

            <!-- Card Executivo de Projeção de Fechamento do Mês + Botões Expandir & Excel -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
                <div class="bg-gradient-to-r from-purple-700 to-indigo-700 px-5 py-3.5 flex items-center justify-between text-white">
                    <div>
                        <h3 class="text-sm font-extrabold uppercase flex items-center gap-1.5">
                            <i class="fa-solid fa-rocket text-amber-300"></i> PROJEÇÃO DO MÊS ATUAL (FECHAMENTO ESTIMADO)
                        </h3>
                        <p class="text-[10px] text-purple-100 mt-0.5">Cálculo matemático por ritmo/dia útil decorrido + Carteira Aberta do mês vigente</p>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <button type="button" @click="abrirModalProjecao('representantes')"
                                class="px-2.5 py-1 rounded-lg bg-white/15 hover:bg-white/25 text-white text-[11px] font-bold transition-all">
                            <i class="fa-solid fa-up-right-from-square mr-1"></i>Expandir
                        </button>
                        <button type="button" @click="exportarProjecaoExcel()" title="Exportar Projeção Completa para Excel"
                                class="px-2.5 py-1 rounded-lg bg-emerald-500 hover:bg-emerald-600 text-white text-[11px] font-bold shadow-sm transition-all">
                            <i class="fa-regular fa-file-excel mr-1"></i>Excel
                        </button>
                    </div>
                </div>

                <div class="p-4 flex-1 flex flex-col justify-between space-y-3" x-show="projecaoFechamento.carregado">
                    <!-- Destaque Principal da Projeção -->
                    <div class="bg-gradient-to-br from-purple-50 to-indigo-50/70 border border-purple-200/80 rounded-2xl p-3.5 text-center">
                        <div class="flex items-center justify-between text-[10px] font-bold text-purple-700 uppercase mb-1">
                            <span>Projeção Linear Fim do Mês</span>
                            <span class="px-2 py-0.5 rounded-full bg-purple-100 text-purple-800" x-text="projecaoFechamento.dias_uteis_decorridos + '/' + projecaoFechamento.dias_uteis_totais + ' dias úteis (' + projecaoFechamento.pct_tempo_decorrido + '%)'"></span>
                        </div>
                        <p class="text-2xl lg:text-3xl font-black text-purple-800" x-text="formatMoney(projecaoFechamento.projecao)">R$ 0,00</p>
                        <div class="mt-1.5 flex items-center justify-center gap-3 text-xs font-bold">
                            <span class="text-amber-700"><i class="fa-solid fa-weight-hanging mr-1"></i><span x-text="formatPeso(projecaoFechamento.peso_projetado)"></span></span>
                            <span class="text-slate-300">•</span>
                            <span class="text-blue-700"><span x-text="formatNumber(projecaoFechamento.quantidade_projetada) + ' un'"></span></span>
                            <span class="text-slate-300">•</span>
                            <span class="text-slate-600"><span x-text="(projecaoFechamento.notas_projetadas || 0) + ' NFs'"></span></span>
                        </div>
                    </div>

                    <!-- Decomposição Matemática da Projeção -->
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Realizado até Hoje</p>
                            <p class="font-black text-emerald-700 text-sm mt-0.5" x-text="formatMoney(projecaoFechamento.realizado)"></p>
                            <p class="text-[10px] text-amber-700 font-semibold" x-text="formatPeso(projecaoFechamento.peso_realizado)"></p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-100">
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Ritmo / Dia Útil</p>
                            <p class="font-black text-purple-700 text-sm mt-0.5" x-text="formatMoney(projecaoFechamento.media_dia_util)"></p>
                            <p class="text-[10px] text-amber-700 font-semibold" x-text="formatPeso(projecaoFechamento.media_peso_dia_util) + '/dia'"></p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-sky-50/70 border border-sky-100">
                            <p class="text-[10px] font-bold text-sky-700 uppercase">Carteira Aberta Mês</p>
                            <p class="font-black text-sky-800 text-sm mt-0.5" x-text="formatMoney(projecaoFechamento.carteira_valor)"></p>
                            <p class="text-[10px] text-sky-600 font-semibold" x-text="formatPeso(projecaoFechamento.carteira_peso) + ' (' + (projecaoFechamento.carteira_pedidos || 0) + ' ped)'"></p>
                        </div>
                        <div class="p-2.5 rounded-xl bg-emerald-50/70 border border-emerald-200">
                            <p class="text-[10px] font-bold text-emerald-700 uppercase">Potencial c/ Carteira</p>
                            <p class="font-black text-emerald-800 text-sm mt-0.5" x-text="formatMoney(projecaoFechamento.projecao_com_carteira)"></p>
                            <p class="text-[10px] text-emerald-600 font-semibold" x-text="'Mês Ant: ' + formatMoney(projecaoFechamento.mes_anterior_valor)"></p>
                        </div>
                    </div>

                    <button type="button" @click="abrirModalProjecao('representantes')"
                            class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-700 text-white text-xs font-extrabold shadow-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-table-list"></i>
                        <span>Ver Projeção por Representante &amp; Região (Exportar Excel)</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ================================================================== -->
        <!-- NOVO PAINEL EXECUTIVO: INTELIGÊNCIA DE RECOMPRA & CLIENTES SEM COMPRAS -->
        <!-- (Substitui o antigo relatório de Margem por Produto)               -->
        <!-- ================================================================== -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 mb-6 overflow-hidden">
            <div class="bg-gradient-to-r from-rose-700 via-rose-600 to-orange-600 px-5 py-4 flex flex-wrap items-center justify-between gap-3 text-white">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm lg:text-base font-extrabold uppercase">
                            <i class="fa-solid fa-user-clock mr-1.5"></i> INTELIGÊNCIA DE RECOMPRA &amp; CLIENTES ATIVOS SEM COMPRAS (+30 / +60 / +90 DIAS)
                        </h3>
                        <span class="px-2.5 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-extrabold uppercase">Ação Comercial Imediata</span>
                    </div>
                    <p class="text-[11px] text-rose-100 mt-0.5">Mapeamento completo da carteira ativa sem faturamento há 30+ dias, com Histórico de NFs, Peso Líquido (Kg), Quantidade e Faturamento dos últimos 12 meses.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="exportarClientesRiscoExcel()"
                            class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-extrabold shadow-sm transition-all">
                        <i class="fa-regular fa-file-excel mr-1.5"></i>Exportar Clientes (.CSV)
                    </button>
                    <button type="button" @click="modalRiscoOpen = true"
                            class="px-3.5 py-2 rounded-xl bg-white text-rose-700 hover:bg-rose-50 text-xs font-extrabold shadow-sm transition-all">
                        <i class="fa-solid fa-up-right-from-square mr-1.5"></i>Expandir Tela Cheia (<span x-text="clientesRiscoFiltrados().length"></span>)
                    </button>
                </div>
            </div>

            <div class="p-5 space-y-4">
                <!-- 4 Cards de Faixas de Inatividade (Clicáveis para filtrar) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div @click="alternarFaixaRisco('30_45')"
                         :class="filtroRiscoFaixa === '30_45' ? 'ring-2 ring-amber-500 bg-amber-50' : 'bg-slate-50 hover:bg-amber-50/40'"
                         class="p-3.5 rounded-xl border border-amber-200/80 cursor-pointer transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-amber-800 uppercase">⚠️ Alerta Inicial (30 a 45 dias)</span>
                            <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-black" x-text="(resumoRisco.faixas?.['30_45']?.clientes || 0) + ' cli'"></span>
                        </div>
                        <p class="text-base font-black text-slate-800 mt-1.5" x-text="formatMoney(resumoRisco.faixas?.['30_45']?.valor || 0)"></p>
                        <p class="text-[11px] font-bold text-amber-700 mt-0.5"><i class="fa-solid fa-weight-hanging mr-1"></i><span x-text="formatPeso(resumoRisco.faixas?.['30_45']?.peso || 0) + ' histórico'"></span></p>
                    </div>

                    <div @click="alternarFaixaRisco('46_60')"
                         :class="filtroRiscoFaixa === '46_60' ? 'ring-2 ring-orange-500 bg-orange-50' : 'bg-slate-50 hover:bg-orange-50/40'"
                         class="p-3.5 rounded-xl border border-orange-200/80 cursor-pointer transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-orange-800 uppercase">🔶 Atenção (46 a 60 dias)</span>
                            <span class="px-2 py-0.5 rounded-full bg-orange-100 text-orange-800 text-xs font-black" x-text="(resumoRisco.faixas?.['46_60']?.clientes || 0) + ' cli'"></span>
                        </div>
                        <p class="text-base font-black text-slate-800 mt-1.5" x-text="formatMoney(resumoRisco.faixas?.['46_60']?.valor || 0)"></p>
                        <p class="text-[11px] font-bold text-orange-700 mt-0.5"><i class="fa-solid fa-weight-hanging mr-1"></i><span x-text="formatPeso(resumoRisco.faixas?.['46_60']?.peso || 0) + ' histórico'"></span></p>
                    </div>

                    <div @click="alternarFaixaRisco('61_90')"
                         :class="filtroRiscoFaixa === '61_90' ? 'ring-2 ring-rose-500 bg-rose-50' : 'bg-slate-50 hover:bg-rose-50/40'"
                         class="p-3.5 rounded-xl border border-rose-200/80 cursor-pointer transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-rose-800 uppercase">🚨 Risco Elevado (61 a 90 dias)</span>
                            <span class="px-2 py-0.5 rounded-full bg-rose-100 text-rose-800 text-xs font-black" x-text="(resumoRisco.faixas?.['61_90']?.clientes || 0) + ' cli'"></span>
                        </div>
                        <p class="text-base font-black text-slate-800 mt-1.5" x-text="formatMoney(resumoRisco.faixas?.['61_90']?.valor || 0)"></p>
                        <p class="text-[11px] font-bold text-rose-700 mt-0.5"><i class="fa-solid fa-weight-hanging mr-1"></i><span x-text="formatPeso(resumoRisco.faixas?.['61_90']?.peso || 0) + ' histórico'"></span></p>
                    </div>

                    <div @click="alternarFaixaRisco('90_mais')"
                         :class="filtroRiscoFaixa === '90_mais' ? 'ring-2 ring-red-700 bg-red-50' : 'bg-slate-50 hover:bg-red-50/40'"
                         class="p-3.5 rounded-xl border border-red-200/80 cursor-pointer transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-extrabold text-red-900 uppercase">🛑 Inativos Críticos (+90 dias)</span>
                            <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-900 text-xs font-black" x-text="(resumoRisco.faixas?.['90_mais']?.clientes || 0) + ' cli'"></span>
                        </div>
                        <p class="text-base font-black text-slate-800 mt-1.5" x-text="formatMoney(resumoRisco.faixas?.['90_mais']?.valor || 0)"></p>
                        <p class="text-[11px] font-bold text-red-700 mt-0.5"><i class="fa-solid fa-weight-hanging mr-1"></i><span x-text="formatPeso(resumoRisco.faixas?.['90_mais']?.peso || 0) + ' histórico'"></span></p>
                    </div>
                </div>

                <!-- Barra de Filtros do Painel de Clientes Sem Compras -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end bg-slate-50 p-3.5 rounded-xl border border-slate-200/70">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filtrar Representante</label>
                        <select x-model="filtroRiscoRep" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700">
                            <option value="">Todos os Representantes (<span x-text="clientesRisco.length"></span>)</option>
                            <template x-for="rep in representantesRisco()" :key="rep.id">
                                <option :value="rep.id" x-text="rep.nome + ' (' + rep.qtd + ' cli)'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filtrar Região</label>
                        <select x-model="filtroRiscoRegiao" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700">
                            <option value="">Todas as Regiões do Escopo</option>
                            <template x-for="reg in distribuicaoRegiao" :key="reg.regiao">
                                <option :value="reg.regiao" x-text="reg.regiao + ' — ' + reg.nome"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Faixa de Inatividade</label>
                        <select x-model="filtroRiscoFaixa" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700">
                            <option value="">Todas (+30 dias)</option>
                            <option value="30_45">30 a 45 dias</option>
                            <option value="46_60">46 a 60 dias</option>
                            <option value="61_90">61 a 90 dias</option>
                            <option value="90_mais">+90 dias</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Buscar Cliente ou Cidade</label>
                        <input type="text" x-model="buscaRiscoCliente" placeholder="Nome, código ou cidade..."
                               class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-white text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                    <div class="bg-white border border-rose-200 rounded-lg px-3 py-1.5 flex items-center justify-between">
                        <div>
                            <p class="text-[9px] font-bold text-rose-600 uppercase">Filtro Atual (<span x-text="resumoRiscoFiltrado().clientes"></span> cli)</p>
                            <p class="text-xs font-black text-slate-800" x-text="formatMoney(resumoRiscoFiltrado().valor)"></p>
                            <p class="text-[10px] font-bold text-amber-700" x-text="formatPesoTon(resumoRiscoFiltrado().peso) + ' • ' + formatNumber(resumoRiscoFiltrado().nfs) + ' NFs'"></p>
                        </div>
                        <button x-show="filtroRiscoRep || filtroRiscoRegiao || filtroRiscoFaixa || buscaRiscoCliente"
                                @click="filtroRiscoRep=''; filtroRiscoRegiao=''; filtroRiscoFaixa=''; buscaRiscoCliente=''"
                                class="text-[10px] font-bold text-rose-600 hover:underline">Limpar</button>
                    </div>
                </div>

                <!-- Tabela Executiva de Clientes Sem Compras -->
                <div class="overflow-x-auto border border-slate-200 rounded-xl max-h-[460px]">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Cliente</th>
                                <th class="px-3 py-3 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-slate-500 uppercase">Região / Cidade</th>
                                <th class="px-3 py-3 text-center text-xs font-bold text-slate-500 uppercase">Última Compra</th>
                                <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">NFs Hist.</th>
                                <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Peso Hist. (Kg)</th>
                                <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Qtd. Hist.</th>
                                <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Faturamento Hist.</th>
                                <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Inatividade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" x-show="clientesRiscoFiltrados().length > 0">
                            <template x-for="c in clientesRiscoFiltrados().slice(0, limiteRiscoPainel)" :key="c.id + '-' + (c.idrepresentante || 0)">
                                <tr class="hover:bg-rose-50/40 cursor-pointer transition-colors" @click="verDetalhesCliente(c.id, c.nome)">
                                    <td class="px-4 py-2.5">
                                        <p class="text-xs font-bold text-slate-800 leading-snug" x-text="c.nome"></p>
                                        <span class="text-[10px] text-slate-400 font-mono" x-text="'Cód. #' + c.id"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-xs text-slate-600 font-medium" x-text="c.representante"></td>
                                    <td class="px-3 py-2.5 text-center">
                                        <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-extrabold mr-1" x-text="c.regiao || 'SUL'"></span>
                                        <span class="text-xs text-slate-500" x-text="c.cidade_uf || '—'"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center text-xs font-semibold text-slate-700" x-text="c.ultima_fmt || '—'"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold text-blue-700" x-text="formatNumber(c.compras_anteriores || 0)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(c.peso_historico || 0)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs text-slate-600" x-text="formatNumber(c.quantidade_historico || 0) + ' un'"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-black text-emerald-600" x-text="formatMoney(c.valor_historico || 0)"></td>
                                    <td class="px-3 py-2.5 text-right">
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold"
                                              :class="c.dias_sem_comprar > 90 ? 'bg-red-100 text-red-800' : (c.dias_sem_comprar > 60 ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800')"
                                              x-text="c.dias_sem_comprar + ' dias'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tbody x-show="!loadingInsights && clientesRiscoFiltrados().length === 0">
                            <tr>
                                <td colspan="9" class="text-center py-8 text-slate-400 text-sm">Nenhum cliente sem compras encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Rodapé de paginação rápida do painel -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-xs text-slate-500" x-show="clientesRiscoFiltrados().length > limiteRiscoPainel">
                    <span>Exibindo <strong x-text="Math.min(limiteRiscoPainel, clientesRiscoFiltrados().length)"></strong> de <strong x-text="clientesRiscoFiltrados().length"></strong> clientes filtrados.</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="limiteRiscoPainel += 50" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold">Mostrar +50 linhas</button>
                        <button type="button" @click="modalRiscoOpen = true" class="px-3 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold">Ver Todos em Tela Cheia</button>
                    </div>
                </div>
            </div>
        </div>

    <!-- ================================================================== -->
    <!-- MODAL EXECUTIVO: PROJEÇÃO DE FECHAMENTO EXPANDIDA + EXPORTAR EXCEL -->
    <!-- ================================================================== -->
    <div x-show="modalProjecaoOpen" x-cloak @click.self="modalProjecaoOpen = false" class="fixed inset-0 z-[65] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-6xl shadow-2xl max-h-[92vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-purple-800 via-purple-700 to-indigo-700 px-6 py-4 flex flex-wrap justify-between items-center gap-3 text-white">
                <div>
                    <h3 class="font-extrabold text-base flex items-center gap-2">
                        <i class="fa-solid fa-rocket text-amber-300"></i>
                        <span>PROJEÇÃO EXECUTIVA DE FECHAMENTO DO MÊS (<span x-text="projecaoFechamento.mes_referencia || 'Atual'"></span>)</span>
                    </h3>
                    <p class="text-[11px] text-purple-100 mt-0.5">
                        Matemática: Realizado até hoje + (Média por Dia Útil × Dias Úteis Restantes) + Visão de Potencial Máximo com Carteira em Aberto
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="exportarProjecaoExcel()"
                            class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-extrabold shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fa-regular fa-file-excel"></i>
                        <span>Exportar para Excel (.CSV)</span>
                    </button>
                    <button @click="modalProjecaoOpen = false" class="text-white/80 hover:text-white ml-2"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(92vh-80px)] space-y-4">
                <!-- KPIs Globais da Projeção -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 text-center">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-emerald-700 uppercase">Realizado Faturado</p>
                        <p class="text-base font-black text-emerald-800 mt-0.5" x-text="formatMoney(projecaoFechamento.realizado)"></p>
                        <p class="text-[10px] font-bold text-amber-700" x-text="formatPeso(projecaoFechamento.peso_realizado)"></p>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-slate-600 uppercase">Média / Dia Útil</p>
                        <p class="text-base font-black text-slate-800 mt-0.5" x-text="formatMoney(projecaoFechamento.media_dia_util)"></p>
                        <p class="text-[10px] font-bold text-amber-700" x-text="formatPeso(projecaoFechamento.media_peso_dia_util) + '/dia'"></p>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-purple-700 uppercase">Projeção Linear</p>
                        <p class="text-base font-black text-purple-800 mt-0.5" x-text="formatMoney(projecaoFechamento.projecao)"></p>
                        <p class="text-[10px] font-bold text-amber-700" x-text="formatPeso(projecaoFechamento.peso_projetado)"></p>
                    </div>
                    <div class="bg-sky-50 border border-sky-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-sky-700 uppercase">Carteira em Aberto</p>
                        <p class="text-base font-black text-sky-800 mt-0.5" x-text="formatMoney(projecaoFechamento.carteira_valor)"></p>
                        <p class="text-[10px] font-bold text-sky-600" x-text="formatPeso(projecaoFechamento.carteira_peso) + ' (' + (projecaoFechamento.carteira_pedidos || 0) + ' ped)'"></p>
                    </div>
                    <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-indigo-700 uppercase">Potencial c/ Carteira</p>
                        <p class="text-base font-black text-indigo-800 mt-0.5" x-text="formatMoney(projecaoFechamento.projecao_com_carteira)"></p>
                        <p class="text-[10px] font-bold text-indigo-600" x-text="formatPeso((projecaoFechamento.peso_projetado || 0) + (projecaoFechamento.carteira_peso || 0))"></p>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-xl p-3">
                        <p class="text-[10px] font-bold text-amber-800 uppercase">Mês Anterior Fechado</p>
                        <p class="text-base font-black text-amber-900 mt-0.5" x-text="formatMoney(projecaoFechamento.mes_anterior_valor)"></p>
                        <p class="text-[10px] font-bold" :class="variacaoPositiva(projecaoFechamento.variacao_vs_mes_anterior) ? 'text-emerald-700' : 'text-rose-700'"
                           x-text="'Var. Proj: ' + (projecaoFechamento.variacao_vs_mes_anterior !== null && projecaoFechamento.variacao_vs_mes_anterior !== undefined ? projecaoFechamento.variacao_vs_mes_anterior + '%' : '—')"></p>
                    </div>
                </div>

                <!-- Abas do Modal de Projeção -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-slate-200">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button type="button" @click="abaProjecao = 'representantes'"
                                :class="abaProjecao === 'representantes' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all">
                            <i class="fa-solid fa-user-tie mr-1"></i>Por Representante (<span x-text="(projecaoFechamento.por_representante || []).length"></span>)
                        </button>
                        <button type="button" @click="abaProjecao = 'regioes'"
                                :class="abaProjecao === 'regioes' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all">
                            <i class="fa-solid fa-map-location-dot mr-1"></i>Pelas 6 Regiões Unificadas (<span x-text="(projecaoFechamento.por_regiao || []).length"></span>)
                        </button>
                        <button type="button" @click="abaProjecao = 'historico'"
                                :class="abaProjecao === 'historico' ? 'bg-purple-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all">
                            <i class="fa-solid fa-calendar-days mr-1"></i>Comparativo Mensal (M-3 a M+1)
                        </button>
                    </div>

                    <div class="relative w-full sm:w-64" x-show="abaProjecao === 'representantes'">
                        <i class="fa-solid fa-search absolute left-3 top-2.5 text-xs text-slate-400"></i>
                        <input type="text" x-model="buscaProjecao" placeholder="Filtrar representante..."
                               class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs focus:outline-none focus:ring-2 focus:ring-purple-500">
                    </div>
                </div>

                <!-- ABA 1: PROJEÇÃO POR REPRESENTANTE -->
                <div x-show="abaProjecao === 'representantes'" class="overflow-x-auto border border-slate-200 rounded-xl max-h-[52vh]">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Realizado (R$)</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Peso Real. (Kg)</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Média/Dia Útil</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Carteira Aberta</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-purple-700 uppercase">Projeção Linear</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-amber-700 uppercase">Peso Proj. (Kg)</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-indigo-700 uppercase">Potencial c/ Cart.</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Mês Ant. (R$)</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Var. %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="r in projecaoRepsFiltrados()" :key="r.id">
                                <tr class="hover:bg-purple-50/30 cursor-pointer" @click="modalProjecaoOpen = false; verDetalhesRepresentante(r.id, r.representante)">
                                    <td class="px-3 py-2.5">
                                        <p class="text-xs font-bold text-slate-800" x-text="r.representante"></p>
                                        <span class="text-[10px] text-slate-400" x-text="r.notas + ' NFs • ' + r.clientes + ' cli • ' + formatNumber(r.qtd_realizada) + ' un'"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold text-emerald-700" x-text="formatMoney(r.realizado)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-semibold text-amber-700" x-text="formatPeso(r.peso_realizado)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs text-slate-600" x-text="formatMoney(r.media_dia_util)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-semibold text-sky-700">
                                        <span x-text="formatMoney(r.carteira_valor)"></span>
                                        <span class="block text-[9px] text-slate-400" x-show="r.carteira_peso > 0" x-text="formatPeso(r.carteira_peso)"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right text-xs font-black text-purple-700 bg-purple-50/40" x-text="formatMoney(r.projecao)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold text-amber-700 bg-amber-50/30" x-text="formatPeso(r.peso_projetado)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-black text-indigo-700" x-text="formatMoney(r.projecao_com_carteira)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs text-slate-500" x-text="formatMoney(r.mes_anterior)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold"
                                        :class="variacaoPositiva(r.variacao_projecao) ? 'text-emerald-600' : 'text-rose-600'"
                                        x-text="r.variacao_projecao === null || r.variacao_projecao === undefined ? '—' : (r.variacao_projecao > 0 ? '+' : '') + r.variacao_projecao + '%'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- ABA 2: PROJEÇÃO PELAS 6 REGIÕES UNIFICADAS -->
                <div x-show="abaProjecao === 'regioes'" class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Macro-Região</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Realizado (R$)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Peso Realizado (Kg)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Quantidade (Un)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">NFs / Clientes</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Média / Dia Útil</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-purple-700 uppercase">Projeção Fechamento (R$)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-amber-700 uppercase">Peso Projetado (Kg)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">% Part.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="rg in (projecaoFechamento.por_regiao || [])" :key="rg.regiao">
                                <tr class="hover:bg-indigo-50/40 cursor-pointer" @click="modalProjecaoOpen = false; abrirDetalhesRegiao(rg)">
                                    <td class="px-4 py-3">
                                        <span class="px-2 py-0.5 rounded bg-indigo-100 text-indigo-800 text-xs font-black mr-1.5" x-text="rg.regiao"></span>
                                        <span class="text-xs font-bold text-slate-700" x-text="rg.nome"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-xs font-bold text-emerald-700" x-text="formatMoney(rg.realizado)"></td>
                                    <td class="px-4 py-3 text-right text-xs font-bold text-amber-700" x-text="formatPeso(rg.peso_realizado)"></td>
                                    <td class="px-4 py-3 text-right text-xs text-slate-600" x-text="formatNumber(rg.quantidade_realizada) + ' un'"></td>
                                    <td class="px-4 py-3 text-right text-xs text-slate-600" x-text="rg.notas + ' NFs • ' + rg.clientes + ' cli'"></td>
                                    <td class="px-4 py-3 text-right text-xs text-slate-600" x-text="formatMoney(rg.media_dia_util)"></td>
                                    <td class="px-4 py-3 text-right text-xs font-black text-purple-700 bg-purple-50/40" x-text="formatMoney(rg.projecao)"></td>
                                    <td class="px-4 py-3 text-right text-xs font-bold text-amber-700 bg-amber-50/30" x-text="formatPeso(rg.peso_projetado)"></td>
                                    <td class="px-4 py-3 text-right text-xs font-extrabold text-slate-700" x-text="rg.participacao + '%'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- ABA 3: COMPARATIVO MENSAL (M-3 A M+1) -->
                <div x-show="abaProjecao === 'historico'" class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Mês / Ano</th>
                                <th class="px-4 py-3 text-center text-xs font-bold text-slate-500 uppercase">Tipo</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Realizado (R$)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-purple-700 uppercase">Projetado (R$)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-amber-700 uppercase">Peso (Kg)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Quantidade (Un)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">NFs</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Dias Úteis</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Média / Dia Útil</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(m, idx) in projecao" :key="idx">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 font-bold text-slate-800" x-text="m.mes_label"></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                              :class="m.atual ? 'bg-indigo-100 text-indigo-800' : (m.realizado ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800')"
                                              x-text="m.atual ? 'Mês Atual' : (m.realizado ? 'Fechado' : 'Tendência M+1')"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-emerald-700" x-text="formatMoney(m.valor)"></td>
                                    <td class="px-4 py-3 text-right font-black text-purple-700" x-text="formatMoney(m.valor_projetado)"></td>
                                    <td class="px-4 py-3 text-right font-bold text-amber-700" x-text="formatPeso(m.atual || m.tendencia ? m.peso_projetado : m.peso)"></td>
                                    <td class="px-4 py-3 text-right text-slate-600" x-text="formatNumber(m.atual || m.tendencia ? m.quantidade_projetada : m.quantidade) + ' un'"></td>
                                    <td class="px-4 py-3 text-right text-slate-600" x-text="m.notas || '—'"></td>
                                    <td class="px-4 py-3 text-right text-slate-600" x-text="m.dias_uteis"></td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-700" x-text="formatMoney(m.media_diaria)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- MODAL DETALHES PRODUTO (COM PESO, QUANTIDADE E VALOR)              -->
    <!-- ================================================================== -->
    <div x-show="modalProdutoOpen" x-cloak @click.self="fecharModalProduto()" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold text-sm lg:text-base"><i class="fa-solid fa-cube mr-2"></i> <span x-text="produtoDetalhes.nome"></span></h3>
                <button @click="fecharModalProduto()" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div x-show="produtoLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
                <div x-show="produtoErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="produtoErro"></div>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6 text-center">
                    <div class="bg-emerald-50 rounded-xl p-3"><p class="text-[10px] text-emerald-600 uppercase font-bold">Receita Líquida</p><p class="text-base font-black text-emerald-700" x-text="formatMoney(produtoDetalhes.valor)">R$ 0</p></div>
                    <div class="bg-amber-50 rounded-xl p-3"><p class="text-[10px] text-amber-700 uppercase font-bold">Peso Líquido</p><p class="text-base font-black text-amber-800" x-text="formatPeso(produtoDetalhes.peso)">0 kg</p></div>
                    <div class="bg-blue-50 rounded-xl p-3"><p class="text-[10px] text-blue-600 uppercase font-bold">Quantidade</p><p class="text-base font-black text-blue-700" x-text="formatNumber(produtoDetalhes.quantidade) + ' un'">0</p></div>
                    <div class="bg-purple-50 rounded-xl p-3"><p class="text-[10px] text-purple-600 uppercase font-bold">Preço Médio / Un</p><p class="text-base font-black text-purple-700" x-text="formatMoney(produtoDetalhes.preco_medio)">R$ 0</p></div>
                    <div class="bg-slate-50 rounded-xl p-3"><p class="text-[10px] text-slate-600 uppercase font-bold">Clientes / NFs</p><p class="text-base font-black text-slate-800" x-text="formatNumber(produtoDetalhes.total_clientes) + ' / ' + formatNumber(produtoDetalhes.total_notas || 0)">0</p></div>
                </div>
                <h4 class="text-sm font-bold text-slate-700 mb-3">Top 10 Clientes que mais compram este produto</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto mb-6">
                    <template x-for="(item, idx) in produtoDetalhes.clientes" :key="idx">
                        <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/70 hover:bg-slate-100 cursor-pointer" @click="fecharModalProduto(); verDetalhesCliente(item.idcliente, item.cliente)">
                            <div class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-white border border-slate-200 shrink-0" x-text="idx + 1"></span>
                                <span class="text-xs font-bold text-slate-800 break-words" x-text="item.cliente"></span>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <span class="text-xs font-black text-emerald-600" x-text="formatMoney(item.valor)"></span>
                                <span class="text-[11px] font-bold text-amber-700 ml-2" x-text="formatPeso(item.peso || 0)"></span>
                                <span class="text-[10px] text-slate-400 ml-2" x-text="formatNumber(item.quantidade) + ' un.'"></span>
                            </div>
                        </div>
                    </template>
                </div>
                <div id="chartProdutoEvolucao" style="height: 250px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- MODAL DETALHES CLIENTE (COM DADOS CADASTRAIS, PESO, QT E HISTÓRICO) -->
    <!-- ================================================================== -->
    <div x-show="modalClienteOpen" x-cloak @click.self="modalClienteOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4 flex justify-between items-center text-white">
                <div>
                    <h3 class="font-black text-sm lg:text-base flex items-center gap-2">
                        <i class="fa-solid fa-building-user"></i>
                        <span x-text="clienteDetalhes.nome"></span>
                    </h3>
                    <div class="flex flex-wrap items-center gap-3 text-[11px] text-blue-100 mt-1">
                        <span x-show="clienteDetalhes.cnpj"><i class="fa-solid fa-id-card mr-1"></i>CNPJ: <span x-text="clienteDetalhes.cnpj"></span></span>
                        <span x-show="clienteDetalhes.cidade"><i class="fa-solid fa-location-dot mr-1"></i><span x-text="clienteDetalhes.cidade + (clienteDetalhes.estado ? '/' + clienteDetalhes.estado : '')"></span></span>
                        <span x-show="clienteDetalhes.representante"><i class="fa-solid fa-user-tie mr-1"></i>Rep: <span x-text="clienteDetalhes.representante"></span></span>
                    </div>
                </div>
                <button @click="modalClienteOpen = false" class="text-white/70 hover:text-white transition"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div x-show="clienteLoading" class="text-center py-8 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Carregando detalhes do cliente...</div>
                <div x-show="clienteErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="clienteErro"></div>

                <div x-show="!clienteLoading && !clienteErro">
                    <!-- Banner de Contexto (Histórico vs Período) -->
                    <div x-show="clienteDetalhes.modo === 'historico'" class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-xl flex flex-wrap items-center justify-between gap-2 text-xs text-amber-900">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-info text-amber-600 text-base"></i>
                            <div>
                                <p class="font-bold">Sem compras no período filtrado do dashboard.</p>
                                <p class="text-[11px] text-amber-700">Exibindo histórico consolidado dos últimos 12 meses e hábitos de compra do cliente.</p>
                            </div>
                        </div>
                        <span x-show="clienteDetalhes.ultima_compra" class="px-2.5 py-1 rounded-lg bg-amber-200/80 font-bold text-amber-950 text-[11px]">
                            <i class="fa-regular fa-calendar-check mr-1"></i>Última compra: <span x-text="formatDate(clienteDetalhes.ultima_compra)"></span>
                            <span x-show="clienteDetalhes.dias_sem_comprar" x-text="' (' + clienteDetalhes.dias_sem_comprar + ' dias)'"></span>
                        </span>
                    </div>

                    <div x-show="clienteDetalhes.modo === 'periodo'" class="mb-4 p-2.5 bg-blue-50 border border-blue-100 rounded-xl flex flex-wrap items-center justify-between gap-2 text-xs text-blue-900">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-circle-check text-blue-600"></i>
                            <span class="font-semibold">Compras faturadas no período selecionado no dashboard.</span>
                        </div>
                        <span x-show="clienteDetalhes.ultima_compra" class="px-2 py-0.5 rounded bg-blue-100 text-blue-900 font-bold text-[11px]">
                            Última compra: <span x-text="formatDate(clienteDetalhes.ultima_compra)"></span>
                        </span>
                    </div>

                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6 text-center">
                        <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3"><p class="text-[10px] text-emerald-600 uppercase font-bold">Valor Total</p><p class="text-base font-black text-emerald-700" x-text="formatMoney(clienteDetalhes.valor)">R$ 0</p></div>
                        <div class="bg-amber-50 border border-amber-100 rounded-xl p-3"><p class="text-[10px] text-amber-700 uppercase font-bold">Peso Comprado</p><p class="text-base font-black text-amber-800" x-text="formatPeso(clienteDetalhes.peso)">0 kg</p></div>
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-3"><p class="text-[10px] text-blue-600 uppercase font-bold">Quantidade</p><p class="text-base font-black text-blue-700" x-text="formatNumber(clienteDetalhes.quantidade) + ' un'">0</p></div>
                        <div class="bg-purple-50 border border-purple-100 rounded-xl p-3"><p class="text-[10px] text-purple-600 uppercase font-bold">Ticket Médio</p><p class="text-base font-black text-purple-700" x-text="formatMoney(clienteDetalhes.ticket_medio)">R$ 0</p></div>
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-3"><p class="text-[10px] text-slate-600 uppercase font-bold">Total NFs</p><p class="text-base font-black text-slate-800" x-text="formatNumber(clienteDetalhes.total_notas)">0</p></div>
                    </div>

                    <div class="flex justify-between items-center mb-3">
                        <h4 class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                            <i class="fa-solid fa-boxes-stacked text-blue-600"></i>
                            <span x-text="clienteDetalhes.modo === 'historico' ? 'Top 10 Produtos mais comprados pelo cliente (Histórico Geral)' : 'Top 10 Produtos comprados por este cliente no período'"></span>
                        </h4>
                        <span class="text-xs text-slate-400 font-semibold" x-text="(clienteDetalhes.produtos || []).length + ' produto(s)'"></span>
                    </div>

                    <div class="space-y-2 max-h-72 overflow-y-auto">
                        <template x-for="(item, idx) in clienteDetalhes.produtos" :key="idx">
                            <div class="flex items-center justify-between p-2.5 rounded-lg bg-slate-50/70 hover:bg-slate-100 transition">
                                <div class="flex items-center gap-2 min-w-0 flex-1">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-white border border-slate-200 shrink-0 text-slate-700" x-text="idx + 1"></span>
                                    <span class="text-xs font-bold text-slate-800 break-words" x-text="item.produto"></span>
                                </div>
                                <div class="text-right shrink-0 ml-3">
                                    <span class="text-xs font-black text-emerald-600" x-text="formatMoney(item.valor)"></span>
                                    <span class="text-[11px] font-bold text-amber-700 ml-2" x-text="formatPeso(item.peso || 0)"></span>
                                    <span class="text-[10px] text-slate-500 ml-2" x-text="formatNumber(item.quantidade) + ' un.'"></span>
                                </div>
                            </div>
                        </template>
                        <div x-show="!clienteDetalhes.produtos || clienteDetalhes.produtos.length === 0" class="text-center py-6 text-slate-400 text-xs">
                            Nenhum produto registrado no histórico deste cliente.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- MODAL DETALHES DO CARD & REGIÃO (AUDITORIA + RASTREIO + EXCEL)     -->
    <!-- ================================================================== -->
    <div x-show="modalCardOpen" x-cloak @click.self="modalCardOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-6xl shadow-2xl max-h-[92vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600 px-6 py-4 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-white font-extrabold text-base flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <span x-text="modalCardTitulo"></span>
                    </h3>
                    <p class="text-[11px] text-emerald-100 mt-0.5" x-text="cardVisao || 'Auditoria executiva de Valor (R$), Peso Líquido (Kg), Quantidade e Rastreio de Documentos'"></p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="exportarCardExcel()"
                            class="px-3.5 py-1.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-extrabold transition-all flex items-center gap-1.5">
                        <i class="fa-regular fa-file-excel"></i>
                        <span>Exportar Excel (.CSV)</span>
                    </button>
                    <button @click="modalCardOpen = false" class="text-white/80 hover:text-white ml-2"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(92vh-80px)] space-y-4">
                <div x-show="cardLoading" class="text-center py-12 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Carregando detalhes e rastreio...</div>
                <div x-show="cardErro" class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="cardErro"></div>
                <div x-show="!cardLoading && !cardErro && cardDetalhes.length === 0 && cardRastreio.length === 0" class="text-center py-12 text-slate-400">
                    <i class="fa-solid fa-chart-simple text-4xl mb-3 block"></i>
                    <p>Sem dados para o período e filtros selecionados.</p>
                </div>

                <div x-show="!cardLoading && !cardErro && (cardDetalhes.length > 0 || cardRastreio.length > 0)">
                    <!-- KPIs Superiores do Modal -->
                    <template x-if="modalCardTipo === 'positivacao'">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-center">
                            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">
                                <p class="text-[10px] text-emerald-600 uppercase font-bold">Faturamento Positivado</p>
                                <p class="text-lg font-black text-emerald-700" x-text="formatMoney(cardTotal ? cardTotal.valor : 0)"></p>
                            </div>
                            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3">
                                <p class="text-[10px] text-amber-700 uppercase font-bold">Peso Positivado</p>
                                <p class="text-lg font-black text-amber-800" x-text="formatPeso(cardTotal ? cardTotal.peso : 0)"></p>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3">
                                <p class="text-[10px] text-blue-600 uppercase font-bold">Positivados / QT Carteira</p>
                                <p class="text-lg font-black text-blue-700">
                                    <span x-text="formatNumber(cardTotal ? (cardTotal.positivados ?? cardTotal.documentos ?? 0) : 0)"></span>
                                    <span class="text-xs font-normal text-slate-400">/</span>
                                    <span x-text="formatNumber(cardTotal ? (cardTotal.qt_carteira ?? 0) : 0)"></span>
                                </p>
                            </div>
                            <div class="bg-purple-50 border border-purple-100 rounded-xl p-3">
                                <p class="text-[10px] text-purple-600 uppercase font-bold">% Positivação da Carteira</p>
                                <p class="text-lg font-black text-purple-700" x-text="(cardTotal ? (cardTotal.percentual ?? 0) : 0) + '%'"></p>
                            </div>
                        </div>
                    </template>

                    <template x-if="modalCardTipo !== 'positivacao'">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4 text-center">
                            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">
                                <p class="text-[10px] text-emerald-600 uppercase font-bold">Valor Total</p>
                                <p class="text-base lg:text-lg font-black text-emerald-700" x-text="formatMoney(cardTotal ? cardTotal.valor : 0)"></p>
                            </div>
                            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3">
                                <p class="text-[10px] text-amber-700 uppercase font-bold">Peso Líquido Total</p>
                                <p class="text-base lg:text-lg font-black text-amber-800" x-text="formatPeso(cardTotal ? cardTotal.peso : 0)"></p>
                            </div>
                            <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-3">
                                <p class="text-[10px] text-indigo-600 uppercase font-bold">Quantidade Total</p>
                                <p class="text-base lg:text-lg font-black text-indigo-700" x-text="formatNumber(cardTotal ? cardTotal.quantidade : 0) + ' un'"></p>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3">
                                <p class="text-[10px] text-blue-600 uppercase font-bold" x-text="cardRotulo || 'Documentos'"></p>
                                <p class="text-base lg:text-lg font-black text-blue-700" x-text="formatNumber(cardTotal ? cardTotal.documentos : 0)"></p>
                            </div>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Ticket Médio</p>
                                <p class="text-base lg:text-lg font-black text-slate-800" x-text="formatMoney(cardTotal ? cardTotal.ticket_medio : 0)"></p>
                            </div>
                        </div>
                    </template>

                    <!-- Barra de Abas + Busca Instantânea -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button type="button"
                                x-show="cardDetalhes.length > 0"
                                @click="cardAba = 'resumo'"
                                :class="cardAba === 'resumo' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                <i class="fa-solid fa-table-list mr-1"></i>
                                <span x-text="modalCardTipo === 'positivacao' ? 'Por Representante (QT Carteira & %)' : 'Visão Executiva por Representante'"></span>
                                (<span x-text="cardDetalhes.length"></span>)
                            </button>
                            <button type="button"
                                x-show="cardRastreio.length > 0"
                                @click="cardAba = 'rastreio'"
                                :class="cardAba === 'rastreio' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                <i class="fa-solid fa-receipt mr-1"></i>
                                <span x-text="modalCardTipo === 'positivacao' ? 'Rastreio de Clientes Positivados' : 'Rastreio de Documentos'"></span>
                                (<span x-text="cardRastreio.length"></span>)
                            </button>
                            <button type="button"
                                x-show="cardPorDia.length > 0"
                                @click="cardAba = 'por_dia'"
                                :class="cardAba === 'por_dia' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                <i class="fa-solid fa-calendar-day mr-1"></i> Evolução Diária (<span x-text="cardPorDia.length"></span>)
                            </button>
                        </div>

                        <div class="relative w-full sm:w-64">
                            <i class="fa-solid fa-search absolute left-3 top-2.5 text-xs text-slate-400"></i>
                            <input type="text" x-model="cardBusca" placeholder="Buscar cliente, NF, pedido, rep..."
                                class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        </div>
                    </div>

                    <!-- ABA 1: RESUMO EXECUTIVO -->
                    <div x-show="cardAba === 'resumo'" class="overflow-x-auto border border-slate-200 rounded-xl">
                        <template x-if="modalCardTipo === 'positivacao'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Valor Faturado</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Peso (Kg)</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Clientes Positivados</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">QT Carteira</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">% Positivação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-2.5 font-bold text-xs text-slate-800" x-text="item.label || item.periodo"></td>
                                            <td class="px-4 py-2.5 text-right text-emerald-600 font-bold text-xs" x-text="formatMoney(item.valor)"></td>
                                            <td class="px-4 py-2.5 text-right text-amber-700 font-semibold text-xs" x-text="formatPeso(item.peso || 0)"></td>
                                            <td class="px-4 py-2.5 text-right font-bold text-blue-600 text-xs" x-text="formatNumber(item.positivados ?? item.documentos ?? 0)"></td>
                                            <td class="px-4 py-2.5 text-right font-bold text-slate-700 text-xs" x-text="formatNumber(item.qt_carteira ?? 0)"></td>
                                            <td class="px-4 py-2.5 text-right">
                                                <div class="flex items-center justify-end gap-2">
                                                    <div class="w-16 bg-slate-200 rounded-full h-1.5 hidden sm:block">
                                                        <div class="h-1.5 rounded-full bg-emerald-500" :style="'width: ' + Math.min(100, item.percentual || 0) + '%'"></div>
                                                    </div>
                                                    <span class="font-black text-purple-700 text-xs" x-text="(item.percentual ?? 0) + '%'"></span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>

                        <template x-if="modalCardTipo === 'ticket_medio'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Peso Total (Kg)</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">NFs / Clientes</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Menor NF</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Maior NF</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Faturamento</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-2.5 font-bold text-xs text-slate-800" x-text="item.label"></td>
                                            <td class="px-4 py-2.5 text-right font-black text-amber-600 text-xs" x-text="formatMoney(item.ticket_medio || 0)"></td>
                                            <td class="px-4 py-2.5 text-right font-semibold text-amber-700 text-xs" x-text="formatPeso(item.peso || 0)"></td>
                                            <td class="px-4 py-2.5 text-right text-slate-600 text-xs" x-text="formatNumber(item.documentos || 0) + ' NFs • ' + formatNumber(item.clientes || 0) + ' cli'"></td>
                                            <td class="px-4 py-2.5 text-right text-xs text-slate-500" x-text="formatMoney(item.menor_nf || 0)"></td>
                                            <td class="px-4 py-2.5 text-right text-xs text-blue-600 font-bold" x-text="formatMoney(item.maior_nf || 0)"></td>
                                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600 text-xs" x-text="formatMoney(item.valor || 0)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>

                        <template x-if="!['positivacao', 'ticket_medio'].includes(modalCardTipo)">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase" x-text="modalCardTipo === 'vendas_dia' ? 'Data / Dia da Semana' : 'Representante / Agrupador'"></th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase" x-text="cardRotulo || 'Documentos'"></th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Clientes</th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-amber-700 uppercase">Peso Líquido (Kg)</th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-blue-700 uppercase">Quantidade (Un)</th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">Valor (R$)</th>
                                        <th class="px-3 py-3 text-right text-xs font-bold text-slate-500 uppercase">% Part.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-2.5">
                                                <span class="font-bold text-xs text-slate-800" x-text="item.label || item.periodo"></span>
                                                <span x-show="item.sublabel" class="ml-1.5 text-[10px] text-slate-400" x-text="'(' + item.sublabel + ')'"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-right text-xs text-slate-600 font-semibold" x-text="formatNumber(item.documentos || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs text-slate-600" x-text="formatNumber(item.clientes || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(item.peso || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs text-blue-700 font-semibold" x-text="formatNumber(item.quantidade || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs text-amber-600" x-text="formatMoney(item.ticket_medio || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs text-emerald-600 font-black" x-text="formatMoney(item.valor || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs font-bold text-slate-500" x-text="(item.percentual ?? item.participacao ?? 0) + '%'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                    </div>

                    <!-- ABA 2: RASTREIO DE DOCUMENTOS / CLIENTES -->
                    <div x-show="cardAba === 'rastreio'" class="overflow-x-auto border border-slate-200 rounded-xl max-h-[52vh]">
                        <template x-if="modalCardTipo === 'positivacao'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Cliente Positivado</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">NFs</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-amber-700 uppercase">Peso (Kg)</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-blue-700 uppercase">Qtd (Un)</th>
                                        <th class="px-4 py-2.5 text-center text-xs font-bold text-slate-500 uppercase">Última Compra</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Valor Comprado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(r, idx) in cardListaRastreioFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50 cursor-pointer" @click="verDetalhesCliente(r.idcliente || r.id_doc, r.cliente)">
                                            <td class="px-4 py-2.5 font-bold text-xs text-slate-800" x-text="r.cliente"></td>
                                            <td class="px-4 py-2.5 text-xs text-slate-600" x-text="r.representante"></td>
                                            <td class="px-4 py-2.5 text-right text-xs font-bold text-blue-600" x-text="formatNumber(r.documentos || r.notas || 0)"></td>
                                            <td class="px-4 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(r.peso || 0)"></td>
                                            <td class="px-4 py-2.5 text-right text-xs text-slate-600" x-text="formatNumber(r.quantidade || 0)"></td>
                                            <td class="px-4 py-2.5 text-center text-xs text-slate-500" x-text="r.data"></td>
                                            <td class="px-4 py-2.5 text-right font-black text-xs text-emerald-600" x-text="formatMoney(r.valor || 0)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>

                        <template x-if="modalCardTipo !== 'positivacao'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                                    <tr>
                                        <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Documento</th>
                                        <th class="px-3 py-2.5 text-center text-xs font-bold text-slate-500 uppercase">Data</th>
                                        <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Ref / Situação</th>
                                        <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Cliente</th>
                                        <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-3 py-2.5 text-right text-xs font-bold text-amber-700 uppercase">Peso (Kg)</th>
                                        <th class="px-3 py-2.5 text-right text-xs font-bold text-blue-700 uppercase">Qtd</th>
                                        <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Valor</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(r, idx) in cardListaRastreioFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2.5 font-mono text-xs font-bold text-emerald-700" x-text="r.numero || r.documento || ('#' + r.id_doc)"></td>
                                            <td class="px-3 py-2.5 text-center text-xs text-slate-500" x-text="r.data"></td>
                                            <td class="px-3 py-2.5 text-xs">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-700" x-text="r.pedido || r.situacao || '—'"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-xs font-bold text-slate-800">
                                                <span class="hover:text-blue-600 hover:underline cursor-pointer" @click="verDetalhesCliente(r.idcliente, r.cliente)" x-text="r.cliente"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-xs text-slate-600" x-text="r.representante"></td>
                                            <td class="px-3 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(r.peso || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs text-blue-700 font-semibold" x-text="formatNumber(r.quantidade || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs font-black text-emerald-600" x-text="formatMoney(r.valor || 0)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                    </div>

                    <!-- ABA 3: EVOLUÇÃO DIÁRIA -->
                    <div x-show="cardAba === 'por_dia'" class="overflow-x-auto border border-slate-200 rounded-xl max-h-[50vh]">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Data</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase" x-text="cardRotulo || 'Documentos'"></th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Clientes</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-amber-700 uppercase">Peso Líquido (Kg)</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-blue-700 uppercase">Quantidade (Un)</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(d, idx) in cardListaPorDiaFiltrada()" :key="idx">
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-2.5 font-bold text-xs text-slate-800">
                                            <span x-text="d.label || d.periodo"></span>
                                            <span x-show="d.sublabel" class="ml-1 text-[10px] text-slate-400" x-text="'(' + d.sublabel + ')'"></span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-xs text-slate-600 font-semibold" x-text="formatNumber(d.documentos || 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-xs text-slate-600" x-text="formatNumber(d.clientes || 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(d.peso || 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-xs text-blue-700 font-semibold" x-text="formatNumber(d.quantidade || 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-xs text-amber-600" x-text="formatMoney(d.ticket_medio || 0)"></td>
                                        <td class="px-4 py-2.5 text-right font-black text-xs text-emerald-600" x-text="formatMoney(d.valor || 0)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="cardExtra && cardExtra.por_dia_semana && cardExtra.por_dia_semana.length" class="mt-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <h4 class="text-xs font-bold text-slate-600 uppercase mb-2">Média de Faturamento e Peso por Dia da Semana</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                            <template x-for="d in (cardExtra.por_dia_semana || [])" :key="d.dow">
                                <div class="bg-white p-2.5 rounded-lg border border-slate-100 text-center">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase" x-text="d.dia"></p>
                                    <p class="text-xs font-black text-emerald-600 mt-0.5" x-text="formatMoney(d.media)"></p>
                                    <p class="text-[10px] font-bold text-amber-700" x-text="formatPeso(d.media_peso || 0)"></p>
                                    <p class="text-[9px] text-slate-400" x-text="d.dias + ' dias'"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- MODAL EXPANDIDO: TODOS OS CLIENTES +30 DIAS SEM COMPRAS            -->
    <!-- ================================================================== -->
    <div x-show="modalRiscoOpen" x-cloak @click.self="modalRiscoOpen = false" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-6xl shadow-2xl max-h-[92vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-rose-700 to-rose-600 px-6 py-4 flex flex-wrap justify-between items-center gap-3">
                <div>
                    <h3 class="text-white font-extrabold text-base">
                        <i class="fa-solid fa-user-clock mr-2"></i> Clientes +<span x-text="riscoCriterio.dias_sem_comprar"></span> Dias sem Compras [Visão Completa]
                    </h3>
                    <p class="text-[11px] text-rose-100 mt-0.5">Relação completa de clientes ativos da carteira sem faturamento nos últimos <span x-text="riscoCriterio.dias_sem_comprar"></span>+ dias com NFs, Peso e Valor Histórico</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="exportarClientesRiscoExcel()"
                            class="px-3.5 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-extrabold shadow-sm transition-all">
                        <i class="fa-regular fa-file-excel mr-1.5"></i>Exportar para Excel (.CSV)
                    </button>
                    <button @click="modalRiscoOpen = false" class="text-white/80 hover:text-white ml-2"><i class="fa-solid fa-times text-xl"></i></button>
                </div>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(92vh-80px)] space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filtrar Representante</label>
                        <select x-model="filtroRiscoRep" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-700">
                            <option value="">Todos os representantes (<span x-text="clientesRisco.length"></span>)</option>
                            <template x-for="rep in representantesRisco()" :key="rep.id">
                                <option :value="rep.id" x-text="rep.nome + ' (' + rep.qtd + ' clientes)'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Filtrar Região</label>
                        <select x-model="filtroRiscoRegiao" class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-xs font-medium text-slate-700">
                            <option value="">Todas as 6 Regiões</option>
                            <option value="SUL">SUL — Sul Catarinense</option>
                            <option value="GRF">GRF — Grande Florianópolis</option>
                            <option value="AVI">AVI — Vale do Itajaí &amp; Litoral</option>
                            <option value="NOR">NOR — Norte &amp; Planalto Norte</option>
                            <option value="PLN">PLN — Planalto Serrano &amp; Alto Vale</option>
                            <option value="RMC">RMC — Curitiba &amp; Litoral PR</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Buscar Cliente ou Cidade</label>
                        <input type="text" x-model="buscaRiscoCliente" placeholder="Digite o nome do cliente, código ou cidade..."
                            class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                    <div class="bg-rose-50 border border-rose-100 rounded-xl p-3 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-rose-600 uppercase">Total Exibido (<span x-text="clientesRiscoFiltrados().length"></span> cli)</p>
                            <p class="text-sm font-black text-rose-700" x-text="formatMoney(resumoRiscoFiltrado().valor)"></p>
                            <p class="text-[10px] font-bold text-amber-700" x-text="formatPeso(resumoRiscoFiltrado().peso) + ' • ' + formatNumber(resumoRiscoFiltrado().nfs) + ' NFs'"></p>
                        </div>
                        <i class="fa-solid fa-triangle-exclamation text-2xl text-rose-400"></i>
                    </div>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-xl max-h-[60vh]">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Cliente</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                <th class="px-3 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Região / Cidade</th>
                                <th class="px-3 py-2.5 text-center text-xs font-bold text-slate-500 uppercase">Última Compra</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">NFs Hist.</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-amber-700 uppercase">Peso Hist. (Kg)</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-blue-700 uppercase">Qtd. Hist.</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Faturamento Hist.</th>
                                <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Inatividade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="c in clientesRiscoFiltrados()" :key="c.id + '-' + (c.idrepresentante || 0)">
                                <tr class="hover:bg-rose-50/40 cursor-pointer" @click="modalRiscoOpen = false; verDetalhesCliente(c.id, c.nome)">
                                    <td class="px-4 py-2.5 font-bold text-xs text-slate-800" x-text="c.nome"></td>
                                    <td class="px-3 py-2.5 text-xs text-slate-600 font-medium" x-text="c.representante"></td>
                                    <td class="px-3 py-2.5 text-xs text-slate-500">
                                        <span class="px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-bold mr-1" x-text="c.regiao || 'SUL'"></span>
                                        <span x-text="c.cidade_uf || '—'"></span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center text-xs text-slate-600" x-text="c.ultima_fmt || 'Sem compra recente'"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold text-blue-700" x-text="formatNumber(c.compras_anteriores || 0)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(c.peso_historico || 0)"></td>
                                    <td class="px-3 py-2.5 text-right text-xs text-slate-600" x-text="formatNumber(c.quantidade_historico || 0) + ' un'"></td>
                                    <td class="px-3 py-2.5 text-right text-xs font-black text-emerald-600" x-text="formatMoney(c.valor_historico || 0)"></td>
                                    <td class="px-3 py-2.5 text-right">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-rose-100 text-rose-700" x-text="c.dias_sem_comprar + ' dias'"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- MODAL DETALHES REPRESENTANTE (COM PESO, QUANTIDADE E VALOR)        -->
    <!-- ================================================================== -->
    <div x-show="modalRepresentanteOpen" x-cloak @click.self="modalRepresentanteOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-amber-700 to-amber-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold"><i class="fa-solid fa-user-tie mr-2"></i> <span x-text="representanteDetalhes.nome"></span></h3>
                <button @click="modalRepresentanteOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div x-show="repLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
                <div x-show="repErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="repErro"></div>
                <div x-show="representanteDetalhes.total" class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4 text-center">
                    <div class="bg-emerald-50 rounded-xl p-2.5"><p class="text-[10px] text-emerald-600 uppercase font-bold">Faturamento</p><p class="font-black text-emerald-700 text-sm" x-text="formatMoney(representanteDetalhes.total ? representanteDetalhes.total.valor : 0)"></p></div>
                    <div class="bg-amber-50 rounded-xl p-2.5"><p class="text-[10px] text-amber-700 uppercase font-bold">Peso Líquido</p><p class="font-black text-amber-800 text-sm" x-text="formatPeso(representanteDetalhes.total ? representanteDetalhes.total.peso : 0)"></p></div>
                    <div class="bg-indigo-50 rounded-xl p-2.5"><p class="text-[10px] text-indigo-600 uppercase font-bold">Quantidade</p><p class="font-black text-indigo-700 text-sm" x-text="formatNumber(representanteDetalhes.total ? representanteDetalhes.total.quantidade : 0) + ' un'"></p></div>
                    <div class="bg-blue-50 rounded-xl p-2.5"><p class="text-[10px] text-blue-600 uppercase font-bold">NFs</p><p class="font-black text-blue-700 text-sm" x-text="formatNumber(representanteDetalhes.total ? representanteDetalhes.total.notas : 0)"></p></div>
                    <div class="bg-slate-50 rounded-xl p-2.5"><p class="text-[10px] text-slate-600 uppercase font-bold">Clientes</p><p class="font-black text-slate-800 text-sm" x-text="formatNumber(representanteDetalhes.total ? representanteDetalhes.total.clientes : 0)"></p></div>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-bold text-slate-700 mb-3">Top Produtos Vendidos</h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <template x-for="(item, idx) in representanteDetalhes.produtos" :key="idx">
                                <div class="flex justify-between items-center p-2.5 bg-slate-50 rounded-lg gap-2">
                                    <span class="text-xs font-bold text-slate-700 break-words flex-1" x-text="item.produto"></span>
                                    <div class="text-right shrink-0">
                                        <span class="text-xs font-black text-emerald-600 block" x-text="formatMoney(item.valor)"></span>
                                        <span class="text-[10px] font-bold text-amber-700" x-text="formatPeso(item.peso || 0) + ' • ' + formatNumber(item.quantidade || 0) + ' un'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-slate-700 mb-3">Clientes Positivados no Período</h4>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
                            <template x-for="c in representanteDetalhes.clientes" :key="c.id">
                                <div class="flex justify-between items-center p-2.5 bg-slate-50 hover:bg-blue-50/50 rounded-lg text-xs cursor-pointer gap-2" @click="modalRepresentanteOpen = false; verDetalhesCliente(c.id, c.nome)">
                                    <span class="font-bold text-slate-700 break-words flex-1" x-text="c.nome"></span>
                                    <div class="text-right shrink-0">
                                        <span class="font-black text-emerald-600 block" x-text="formatMoney(c.valor)"></span>
                                        <span class="text-[10px] font-bold text-amber-700" x-text="formatPeso(c.peso || 0) + ' • ' + c.total_notas + ' NFs'"></span>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================== -->
    <!-- MODAL DETALHES FUNIL (COM PESO, QUANTIDADE E VALOR)                -->
    <!-- ================================================================== -->
    <div x-show="modalFunilOpen" x-cloak @click.self="modalFunilOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold"><i class="fa-solid fa-list-check mr-2"></i> Pedidos na Etapa: <span x-text="funilDetalhes.etapa"></span></h3>
                <button @click="modalFunilOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div x-show="funilLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
                <div x-show="funilErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="funilErro"></div>
                <p x-show="funilDetalhes.total" class="text-xs text-slate-500 mb-3">
                    Total: <b x-text="funilDetalhes.total ? funilDetalhes.total.quantidade : 0"></b> pedidos |
                    Valor: <b class="text-emerald-700" x-text="formatMoney(funilDetalhes.total ? funilDetalhes.total.valor : 0)"></b> |
                    Peso: <b class="text-amber-700" x-text="formatPeso(funilDetalhes.total ? funilDetalhes.total.peso : 0)"></b>
                    <span x-show="funilDetalhes.truncado" class="text-amber-600"> — exibindo os 100 maiores pedidos.</span>
                </p>
                <div x-show="!funilLoading && !funilErro && funilDetalhes.pedidos.length === 0" class="text-center py-8 text-slate-400 text-sm">Nenhum pedido nesta etapa.</div>
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Pedido</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Data</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Representante</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-amber-700 uppercase">Peso (Kg)</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-blue-700 uppercase">Qtd</th>
                                <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Valor</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="p in funilDetalhes.pedidos" :key="p.idpedido">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-2.5 font-mono text-xs font-bold text-emerald-600" x-text="'#' + p.idpedido"></td>
                                    <td class="px-4 py-2.5 text-xs" x-text="p.data"></td>
                                    <td class="px-4 py-2.5 text-xs font-bold text-slate-800 cursor-pointer hover:text-blue-600" @click="modalFunilOpen = false; verDetalhesCliente(p.idcliente, p.cliente)" x-text="p.cliente"></td>
                                    <td class="px-4 py-2.5 text-xs text-slate-600" x-text="p.representante"></td>
                                    <td class="px-4 py-2.5 text-right text-xs font-bold text-amber-700" x-text="formatPeso(p.peso || 0)"></td>
                                    <td class="px-4 py-2.5 text-right text-xs text-blue-700 font-semibold" x-text="formatNumber(p.quantidade)"></td>
                                    <td class="px-4 py-2.5 text-right text-xs font-black text-emerald-600" x-text="formatMoney(p.valor)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    </div>
</div>

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


<!-- =================================================== -->
<!-- TOOLTIP MODERNO FLUTUANTE (RICH HOVER POPOVER DASH) -->
<!-- =================================================== -->
<div id="modernTooltipDash" class="pointer-events-none fixed z-[9999] hidden max-w-xs rounded-2xl bg-slate-900/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md border border-slate-700/60 transition-opacity duration-150">
    <div class="flex items-center justify-between gap-2 mb-1.5">
        <span id="tooltipDashBadge" class="text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50">INDICADOR</span>
        <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
    </div>
    <div id="tooltipDashTitle" class="font-black text-white text-xs mb-1">Título do Indicador</div>
    <div id="tooltipDashDesc" class="text-slate-300 text-[11px] font-medium leading-relaxed">Explicação do item ao passar o mouse.</div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
