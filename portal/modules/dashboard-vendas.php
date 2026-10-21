<?php
$pageTitle = 'Dashboard Vendas | Nutricional';
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

<div class="min-h-screen bg-slate-50" x-data="dashboardVendasHandler()" x-init="init()">
    
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
            <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
        </div>
    </div>
    <div class="mobile-spacer block lg:hidden h-14"></div>

    <!-- HEADER DESKTOP -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-amber-500 text-white rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">DASHBOARD DE VENDAS</h2>
                <span class="text-xs text-slate-400 font-medium">Análise executiva de performance comercial</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-base lg:text-xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <div class="max-w-full mx-auto px-4 lg:px-6">
        
        <!-- PAINEL DE FILTROS EXECUTIVO (ESTILO CUBO DE VENDAS) -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
            <!-- Barra Superior: Atalhos Rápidos de Período + Botões de Ação -->
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 mb-4 border-b border-slate-100">
                <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">
                        <i class="fa-solid fa-bolt text-amber-500 mr-1"></i>Período rápido:
                    </span>
                    <button type="button" @click="definirPeriodoRapido('hoje')"
                        :class="periodoRapidoAtivo === 'hoje' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Hoje</button>
                    <button type="button" @click="definirPeriodoRapido('mes_atual')"
                        :class="periodoRapidoAtivo === 'mes_atual' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Este Mês</button>
                    <button type="button" @click="definirPeriodoRapido('mes_passado')"
                        :class="periodoRapidoAtivo === 'mes_passado' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Mês Passado</button>
                    <button type="button" @click="definirPeriodoRapido('ultimos_30')"
                        :class="periodoRapidoAtivo === 'ultimos_30' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Últimos 30 dias</button>
                    <button type="button" @click="definirPeriodoRapido('ultimos_90')"
                        :class="periodoRapidoAtivo === 'ultimos_90' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Últimos 90 dias</button>
                    <button type="button" @click="definirPeriodoRapido('ano_atual')"
                        :class="periodoRapidoAtivo === 'ano_atual' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                        class="px-2.5 py-1 rounded-lg text-xs font-bold transition-all">Este Ano</button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button @click="carregarDados()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 shadow-sm transition-all">
                        <i class="fa-solid fa-rotate-right mr-1.5"></i>Atualizar
                    </button>
                    <button @click="exportarDados()" class="px-4 py-2 border border-emerald-200 text-emerald-700 rounded-xl text-xs font-bold hover:bg-emerald-50 transition-all">
                        <i class="fa-regular fa-file-excel mr-1.5"></i>Exportar CSV
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
                        <i class="fa-solid fa-user-tie mr-1 text-amber-500"></i> Gestor
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
        <div x-show="loading" x-cloak class="mb-4 text-xs text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando indicadores...</div>

        <!-- CARDS PRINCIPAIS -->
<div :class="loading ? 'opacity-60' : ''" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    
    <!-- Faturamento Total -->
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Faturamento')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-emerald-100 text-xs uppercase tracking-wider font-bold">Faturamento (NFs faturadas)</p>
                <p class="text-3xl font-black mt-2" x-text="formatMoney(kpis.faturamento)">R$ 0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-dollar-sign text-xl"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2 text-xs">
            <span :class="variacaoPositiva(kpis.variacao_faturamento) ? 'text-emerald-200' : 'text-rose-200'">
                <i x-show="kpis.variacao_faturamento !== null" :class="variacaoPositiva(kpis.variacao_faturamento) ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                <span x-text="variacaoTexto(kpis.variacao_faturamento)"></span>
            </span>
            <span class="text-emerald-100" x-text="variacaoRotulo()"></span>
        </div>
        <p class="mt-2 text-[10px] text-emerald-100/80 leading-tight">Soma das NFs emitidas e válidas no período (mesma regra do Cubo de Vendas), com IPI/ST e frete.</p>
    </div>

    <!-- Total de Pedidos -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Notas', 'NFs emitidas')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-blue-100 text-xs uppercase tracking-wider font-bold">NFs emitidas</p>
                <p class="text-3xl font-black mt-2" x-text="formatNumber(kpis.notas)">0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-cart-shopping text-xl"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2 text-xs">
            <span :class="variacaoPositiva(kpis.variacao_notas) ? 'text-blue-200' : 'text-rose-200'">
                <i x-show="kpis.variacao_notas !== null" :class="variacaoPositiva(kpis.variacao_notas) ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                <span x-text="variacaoTexto(kpis.variacao_notas)"></span>
            </span>
            <span class="text-blue-100" x-text="variacaoRotulo()"></span>
        </div>
        <p class="mt-2 text-[10px] text-blue-100/80 leading-tight">Quantidade de NFs faturadas distintas no período.</p>
    </div>

    <!-- Ticket Médio -->
    <div class="bg-gradient-to-br from-amber-500 to-amber-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Ticket Médio')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-amber-100 text-xs uppercase tracking-wider font-bold">Ticket Médio</p>
                <p class="text-3xl font-black mt-2" x-text="formatMoney(kpis.ticket_medio)">R$ 0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-ticket text-xl"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2 text-xs">
            <span :class="variacaoPositiva(kpis.variacao_ticket) ? 'text-amber-200' : 'text-rose-200'">
                <i x-show="kpis.variacao_ticket !== null" :class="variacaoPositiva(kpis.variacao_ticket) ? 'fa-solid fa-arrow-up' : 'fa-solid fa-arrow-down'"></i>
                <span x-text="variacaoTexto(kpis.variacao_ticket)"></span>
            </span>
            <span class="text-amber-100" x-text="variacaoRotulo()"></span>
        </div>
        <p class="mt-2 text-[10px] text-amber-100/80 leading-tight">Faturamento ÷ nº de NFs distintas.</p>
    </div>

    <!-- Vendas por Dia -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Vendas por Dia', 'Faturamento por dia')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-purple-100 text-xs uppercase tracking-wider font-bold">Média por dia útil</p>
                <p class="text-3xl font-black mt-2" x-text="formatMoney(kpis.media_diaria)">R$ 0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-gauge-high text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-purple-100">
            <span x-text="formatMoney(kpis.media_dia_com_venda) + ' por dia com venda (' + kpis.dias_com_venda + ' dias)'"></span>
        </div>
        <p class="mt-2 text-[10px] text-purple-100/80 leading-tight">Faturamento ÷ dias úteis (seg–sex, até hoje) = <span x-text="kpis.dias_uteis"></span> dias. Feriados não descontados.</p>
    </div>

    <!-- Carteira de pedidos (separada do faturamento) -->
    <div class="bg-gradient-to-br from-sky-500 to-sky-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Carteira', 'Carteira de pedidos')">
        <p class="text-sky-100 text-xs uppercase tracking-wider font-bold">Carteira de pedidos</p>
        <p class="text-2xl font-black mt-2" x-text="formatMoney(kpis.carteira.valor)">R$ 0</p>
        <p class="mt-1 text-xs text-sky-100"><span x-text="formatNumber(kpis.carteira.pedidos)"></span> pedidos | <span x-text="formatNumber(kpis.carteira.quantidade)"></span> un.</p>
        <p class="mt-2 text-[10px] text-sky-100/80 leading-tight">Pedidos abertos/parciais ainda não faturados. NÃO inclui o faturamento.</p>
    </div>

    <!-- Cancelados -->
    <div class="bg-gradient-to-br from-rose-500 to-rose-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Cancelados', 'NFs canceladas')">
        <p class="text-rose-100 text-xs uppercase tracking-wider font-bold">NFs canceladas</p>
        <p class="text-2xl font-black mt-2" x-text="formatMoney(kpis.cancelados.valor)">R$ 0</p>
        <p class="mt-1 text-xs text-rose-100"><span x-text="formatNumber(kpis.cancelados.notas)"></span> notas</p>
        <p class="mt-2 text-[10px] text-rose-100/80 leading-tight">Valor das NFs de venda canceladas emitidas no período.</p>
    </div>

    <!-- Devoluções -->
    <div class="bg-gradient-to-br from-orange-500 to-orange-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Devoluções', 'Devoluções de venda')">
        <p class="text-orange-100 text-xs uppercase tracking-wider font-bold">Devoluções</p>
        <p class="text-2xl font-black mt-2" x-text="formatMoney(kpis.devolucoes.valor)">R$ 0</p>
        <p class="mt-1 text-xs text-orange-100"><span x-text="formatNumber(kpis.devolucoes.notas)"></span> NFs de entrada</p>
        <p class="mt-2 text-[10px] text-orange-100/80 leading-tight">NFs de entrada por devolução de venda (bonificações excluídas). Não abatidas do faturamento.</p>
    </div>

    <!-- Positivação -->
    <div class="bg-gradient-to-br from-teal-500 to-teal-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Positivação', 'Positivação da carteira')">
        <p class="text-teal-100 text-xs uppercase tracking-wider font-bold">Positivação</p>
        <p class="text-2xl font-black mt-2" x-text="kpis.positivacao.percentual.toLocaleString('pt-BR') + '%'">0%</p>
        <p class="mt-1 text-xs text-teal-100"><span x-text="formatNumber(kpis.positivacao.positivados)"></span> de <span x-text="formatNumber(kpis.positivacao.base)"></span> clientes ativos</p>
        <p class="mt-2 text-[10px] text-teal-100/80 leading-tight">Clientes com NF no período ÷ clientes ativos da carteira do escopo.</p>
    </div>
</div>

        <!-- GRÁFICO DE EVOLUÇÃO + PROJEÇÃO -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase">
                        <i class="fa-regular fa-chart-line mr-2 text-emerald-500"></i> EVOLUÇÃO DE VENDAS <span class="text-[10px] text-slate-400 normal-case" x-text="evolucao.granularidade === 'dia' ? '(diária, NFs faturadas)' : '(mensal, NFs faturadas)'"></span>
                    </h3>
                    <div class="flex gap-2">
                        <button @click="mudarTipoGrafico('valor')" :class="tipoGrafico === 'valor' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">Valor (R$)</button>
                        <button @click="mudarTipoGrafico('quantidade')" :class="tipoGrafico === 'quantidade' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">Quantidade</button>
                    </div>
                </div>
                <div x-show="!loading && evolucao.itens.length === 0" class="text-sm text-slate-400 text-center py-16">Sem vendas no período.</div>
                <div id="chartEvolucao" x-show="evolucao.itens.length > 0" style="height: 320px; width: 100%;"></div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <h3 class="text-sm font-bold text-slate-700 uppercase mb-4">
                    <i class="fa-solid fa-chart-simple mr-2 text-amber-500"></i> PROJEÇÃO DE VENDAS (BASE: MÊS ATUAL)
                </h3>
                <p class="text-[10px] text-slate-400 -mt-3 mb-3">Sempre referenciada ao mês atual: 3 meses anteriores + mês atual (realizado e projeção de fechamento).</p>
                <div id="projecaoContainer" class="space-y-2.5 max-h-[340px] overflow-y-auto pr-1">
                    <div x-show="loadingPesados && projecao.length === 0" class="space-y-3">
                        <div class="bg-slate-50 rounded-xl p-3 animate-pulse">
                            <div class="flex justify-between items-center mb-2">
                                <div class="h-3 bg-slate-200 rounded w-20"></div>
                                <div class="h-4 bg-slate-200 rounded w-24"></div>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2"></div>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-3 animate-pulse">
                            <div class="flex justify-between items-center mb-2">
                                <div class="h-3 bg-slate-200 rounded w-16"></div>
                                <div class="h-4 bg-slate-200 rounded w-20"></div>
                            </div>
                            <div class="w-full bg-slate-200 rounded-full h-2"></div>
                        </div>
                    </div>
                    <div x-show="projecao.length > 0" class="space-y-2.5">
                        <template x-for="(item, idx) in projecao" :key="idx">
                            <div class="rounded-xl p-3 border transition-all"
                                 :class="item.atual ? 'bg-indigo-50/70 border-indigo-200' : (item.realizado ? 'bg-slate-50 border-slate-100' : 'bg-amber-50/40 border-amber-100')">
                                <div class="flex justify-between items-center mb-1.5">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-xs font-bold text-slate-700" x-text="item.mes_label"></span>
                                        <span x-show="item.atual" class="px-1.5 py-0.5 rounded bg-indigo-600 text-white text-[9px] font-bold uppercase">Mês Atual</span>
                                    </div>
                                    <span :class="item.atual ? 'text-indigo-700' : (item.realizado ? 'text-emerald-600' : 'text-amber-600')" class="text-sm font-black">
                                        <span x-text="formatMoney(item.atual ? item.valor_projetado : item.valor)"></span>
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-2">
                                    <div class="h-2 rounded-full"
                                         :class="item.atual ? 'bg-indigo-600' : (item.realizado ? 'bg-emerald-500' : 'bg-amber-500')"
                                         :style="'width: ' + Math.min(((item.atual ? item.valor_projetado : item.valor) / maxProjecao * 100), 100) + '%'"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-[10px] text-slate-500">
                                    <span x-show="item.realizado">✓ Realizado fechado</span>
                                    <span x-show="item.atual" x-text="'Realizado até hoje: ' + formatMoney(item.valor) + ' (Proj. fechamento)'"></span>
                                    <span x-show="!item.realizado && !item.atual">📈 Projeção média móvel</span>
                                </div>
                            </div>
                        </template>
                    </div>
                    <div x-show="erroPesados" x-cloak class="text-xs text-rose-600" x-text="erroPesados"></div>
                    <div x-show="mensagemPesados" x-cloak class="text-xs text-amber-700" x-text="mensagemPesados"></div>
                    <div x-show="!loadingPesados && projecao.length === 0" class="text-center py-8 text-slate-400">
                        <i class="fa-solid fa-chart-line text-3xl mb-2 block"></i>
                        <p class="text-sm">Nenhuma projeção disponível</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOP PRODUTOS + TOP CLIENTES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                    <h3 class="text-sm font-bold text-slate-700 uppercase">
                        <i class="fa-solid fa-cube mr-2 text-emerald-500"></i> TOP 5 PRODUTOS
                    </h3>
                </div>
                <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto" x-show="topProdutos.length > 0">
                    <template x-for="(item, idx) in topProdutos" :key="idx">
                        <div class="p-3 hover:bg-slate-50 transition-colors cursor-pointer" @click="verDetalhesProduto(item.iditem, item.produto)">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="{'bg-amber-500 text-white': idx === 0, 'bg-slate-300 text-slate-700': idx === 1, 'bg-amber-700 text-white': idx === 2, 'bg-slate-100 text-slate-500': idx > 2}" x-text="idx + 1"></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800 truncate max-w-[180px]" x-text="item.produto"></p>
                                        <p class="text-[10px] text-slate-400" x-text="item.grupo"></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-emerald-600" x-text="formatMoney(item.valor)"></p>
                                    <p class="text-[10px] text-slate-400" x-text="formatNumber(item.quantidade) + ' unid.'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="topProdutos.length === 0" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-cube text-3xl mb-2 block"></i>
                    <p class="text-sm">Nenhum produto encontrado</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                    <h3 class="text-sm font-bold text-slate-700 uppercase">
                        <i class="fa-solid fa-users mr-2 text-blue-500"></i> TOP 5 CLIENTES
                    </h3>
                </div>
                <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto" x-show="topClientes.length > 0">
                    <template x-for="(item, idx) in topClientes" :key="idx">
                        <div class="p-3 hover:bg-slate-50 transition-colors cursor-pointer" @click="verDetalhesCliente(item.idcliforemp, item.cliente)">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="{'bg-amber-500 text-white': idx === 0, 'bg-slate-300 text-slate-700': idx === 1, 'bg-amber-700 text-white': idx === 2, 'bg-slate-100 text-slate-500': idx > 2}" x-text="idx + 1"></span>
                                    <div>
                                        <p class="text-sm font-semibold text-slate-800 truncate max-w-[180px]" x-text="item.cliente"></p>
                                        <p class="text-[10px] text-slate-400" x-text="item.regiao"></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-emerald-600" x-text="formatMoney(item.valor)"></p>
                                    <p class="text-[10px] text-slate-400" x-text="item.total_notas + ' NFs'"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="topClientes.length === 0" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-users text-3xl mb-2 block"></i>
                    <p class="text-sm">Nenhum cliente encontrado</p>
                </div>
            </div>
        </div>

        <!-- DISTRIBUIÇÃO + MATRIZ CROSS-SELLING -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <h3 class="text-sm font-bold text-slate-700 uppercase mb-4">
                    <i class="fa-solid fa-map-marker-alt mr-2 text-indigo-500"></i> DISTRIBUIÇÃO POR REGIÃO
                </h3>
                <div id="chartRegiao" style="height: 280px; width: 100%;"></div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <h3 class="text-sm font-bold text-slate-700 uppercase mb-4">
                    <i class="fa-solid fa-code-branch mr-2 text-purple-500"></i> PRODUTOS COMPRADOS JUNTOS
                </h3>
                <div class="space-y-3 max-h-80 overflow-y-auto" x-show="matrizCrossSelling.length > 0">
                    <template x-for="(item, idx) in matrizCrossSelling" :key="idx">
                        <div class="bg-gradient-to-r from-purple-50 to-white rounded-xl p-3 border border-purple-100">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="w-7 h-7 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center text-xs font-bold" x-text="idx + 1"></span>
                                    <div>
                                        <p class="text-xs font-semibold text-slate-700" x-text="item.produto1 + ' + ' + item.produto2"></p>
                                        <p class="text-[10px] text-slate-400">Comprados juntos <span x-text="item.vezes_comprados_juntos"></span> vezes</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-purple-600" x-text="formatMoney(item.valor_total)"></p>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div x-show="loadingPesados && matrizCrossSelling.length === 0" class="space-y-3">
                    <div class="bg-slate-100 rounded-xl p-3 animate-pulse">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-slate-200 rounded-lg"></div>
                            <div>
                                <div class="h-3 bg-slate-200 rounded w-40 mb-1"></div>
                                <div class="h-2 bg-slate-200 rounded w-24"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-slate-100 rounded-xl p-3 animate-pulse">
                        <div class="flex items-center gap-2">
                            <div class="w-7 h-7 bg-slate-200 rounded-lg"></div>
                            <div>
                                <div class="h-3 bg-slate-200 rounded w-36 mb-1"></div>
                                <div class="h-2 bg-slate-200 rounded w-20"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div x-show="!loadingPesados && matrizCrossSelling.length === 0" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-code-branch text-3xl mb-2 block"></i>
                    <p class="text-sm">Nenhum relacionamento encontrado</p>
                </div>
            </div>
        </div>

        <!-- MARGEM POR PRODUTO -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 mb-6 overflow-hidden">
            <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-700 uppercase">
                        <i class="fa-solid fa-chart-pie mr-2 text-rose-500"></i> RECEITA, COMISSÃO E MARGEM POR PRODUTO
                    </h3>
                    <p class="text-[10px] text-slate-400 mt-1">Mesma regra do Cubo de Vendas: Receita Líquida = faturado − devoluções; Comissão Ajustada = comissão faturada − comissão de devoluções.</p>
                </div>
                <span class="text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-1 rounded-full">
                    <i class="fa-solid fa-check-double mr-1"></i> Padrão Cubo de Vendas
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Produto</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Receita Líquida</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Comissão Ajustada</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">% Comissão</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Margem Gerencial</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Qtd</th>
                        </tr>
                    </thead>
                    <tbody x-show="margemProdutos.length > 0">
                        <template x-for="(item, idx) in margemProdutos" :key="idx">
                            <tr class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer" @click="verDetalhesProduto(item.iditem, item.produto)">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800" x-text="item.produto || '---'"></span>
                                    <br><span class="text-[10px] text-slate-400" x-text="item.referencia || '---'"></span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-emerald-600 font-bold" x-text="formatMoney(item.receita || 0)"></span>
                                    <template x-if="(item.valor_devolvido || 0) > 0">
                                        <div class="text-[10px] text-rose-500" x-text="'Dev: -' + formatMoney(item.valor_devolvido)"></div>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="text-amber-600 font-bold" x-text="formatMoney(item.comissao || 0)"></span>
                                    <template x-if="(item.comissao_devolvida || 0) > 0">
                                        <div class="text-[10px] text-rose-500" x-text="'Estorno: -' + formatMoney(item.comissao_devolvida)"></div>
                                    </template>
                                </td>
                                <td class="px-4 py-3 text-center text-slate-700 font-bold" x-text="(item.comissao_percentual ?? 0) + '%'"></td>
                                <td class="px-4 py-3 text-center text-slate-700 font-bold" x-text="item.margem_percentual === null || item.margem_percentual === undefined ? '—' : item.margem_percentual + '%'"></td>
                                <td class="px-4 py-3 text-right text-blue-600 font-medium" x-text="formatNumber(item.quantidade || 0)"></td>
                            </tr>
                        </template>
                    </tbody>
                    <tbody x-show="loadingPesados && margemProdutos.length === 0">
                        <tr>
                            <td colspan="6" class="px-4 py-3">
                                <div class="animate-pulse space-y-3">
                                    <div class="h-4 bg-slate-200 rounded w-full"></div>
                                    <div class="h-4 bg-slate-200 rounded w-3/4"></div>
                                    <div class="h-4 bg-slate-200 rounded w-5/6"></div>
                                    <div class="h-4 bg-slate-200 rounded w-2/3"></div>
                                    <div class="h-4 bg-slate-200 rounded w-4/5"></div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    <tbody x-show="!loadingPesados && margemProdutos.length === 0">
                        <tr>
                            <td colspan="6" class="text-center py-8 text-slate-400">
                                <i class="fa-solid fa-chart-pie text-3xl mb-2 block"></i>
                                <p class="text-sm">Nenhum dado encontrado</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
<!-- INSIGHTS: RANKING + FUNIL + ALERTAS -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <div x-show="erroInsights" x-cloak class="lg:col-span-3 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm" x-text="erroInsights"></div>
    
    <!-- Ranking de Representantes -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 uppercase">
                <i class="fa-solid fa-ranking-star mr-2 text-amber-500"></i> TOP 5 REPRESENTANTES
            </h3>
        </div>
        <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto" x-show="rankingRepresentantes.length > 0">
            <template x-for="(item, idx) in rankingRepresentantes" :key="idx">
                <div  class="p-3 hover:bg-slate-50 cursor-pointer" @click="verDetalhesRepresentante(item.id, item.nome)">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold" :class="{'bg-amber-500 text-white': idx === 0, 'bg-slate-300 text-slate-700': idx === 1, 'bg-amber-700 text-white': idx === 2, 'bg-slate-100 text-slate-500': idx > 2}" x-text="idx + 1"></span>
                            <span class="text-sm font-medium text-slate-700 truncate max-w-[130px]" x-text="item.nome"></span>
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-bold text-emerald-600" x-text="formatMoney(item.valor)"></p>
                            <p class="text-[10px] text-slate-400" x-text="item.total_notas + ' NFs | ' + item.clientes_ativos + ' clientes'"></p>
                        </div>
                    </div>
                </div>
            </template>
        </div>
        <div x-show="rankingRepresentantes.length === 0" class="text-center py-8 text-slate-400">
            <p class="text-sm">Nenhum dado disponível</p>
        </div>
    </div>

    <!-- Funil de Pedidos -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 uppercase">
                <i class="fa-solid fa-filter-circle-dollar mr-2 text-blue-500"></i> FUNIL DE PEDIDOS
            </h3>
            <p class="text-[10px] text-slate-400 mt-1">Pedidos do período por situação. Conversão = faturados (total + parcial) ÷ pedidos válidos.</p>
        </div>
        <div class="px-4 pt-3 flex gap-4 text-xs" x-show="funilPedidos.length > 0">
            <span class="text-emerald-600 font-bold">Conversão: <span x-text="funilTaxas.conversao === null ? '—' : funilTaxas.conversao + '%'"></span></span>
            <span class="text-rose-600 font-bold">Cancelamento: <span x-text="funilTaxas.cancelamento === null ? '—' : funilTaxas.cancelamento + '%'"></span></span>
        </div>
        <div class="p-4 space-y-3" x-show="funilPedidos.length > 0">
            <template x-for="(item, idx) in funilPedidos" :key="idx">
                <div class="cursor-pointer" @click="verDetalhesFunil(item.etapa)">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-bold text-slate-600" x-text="item.etapa"></span>
                        <span class="text-slate-500" x-text="item.quantidade + ' pedidos'"></span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2">
                        <div class="h-2 rounded-full" :class="corFunil(item)" :style="'width: ' + larguraFunil(item) + '%'"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1" x-text="formatMoney(item.valor)"></p>
                </div>
            </template>
        </div>
        <div x-show="funilPedidos.length === 0" class="text-center py-8 text-slate-400">
            <p class="text-sm">Nenhum dado disponível</p>
        </div>
    </div>

    <!-- Projeção + Alertas (+30 dias sem comprar por representante) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden flex flex-col">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-bold text-slate-700 uppercase">
                    <i class="fa-solid fa-chart-line mr-2 text-purple-500"></i> PROJEÇÃO DE FECHAMENTO
                </h3>
                <p class="text-[10px] text-slate-400 mt-1">Realizado do mês + média por dia útil × dias úteis restantes.</p>
            </div>
            <button x-show="clientesRisco.length > 0" @click="modalRiscoOpen = true" class="text-[11px] font-bold text-rose-600 hover:text-rose-700 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-2.5 py-1 rounded-lg transition-colors">
                <i class="fa-solid fa-up-right-from-square mr-1"></i> Expandir (<span x-text="clientesRiscoFiltrados().length"></span>)
            </button>
        </div>
        <div class="p-4 flex-1 flex flex-col">
            <div x-show="!projecaoFechamento.carregado" class="text-center py-3 mb-2 bg-slate-50 rounded-xl text-xs text-slate-400">
                Projeção mensal disponível quando o período inclui o mês corrente.
            </div>
            <div class="text-center mb-3 pb-3 border-b border-slate-100" x-show="projecaoFechamento.carregado">
                <p class="text-[10px] text-slate-400 uppercase">Projeção para fim do mês</p>
                <p class="text-2xl font-black text-purple-600" x-text="formatMoney(projecaoFechamento.projecao)">R$ 0</p>
                <p class="text-xs text-slate-400 mt-1">
                    <span x-text="formatMoney(projecaoFechamento.realizado)"></span> realizado + 
                    <span x-text="formatMoney(projecaoFechamento.projecao_adicional)"></span> projetado
                </p>
                <p class="text-[10px] text-slate-400 mt-0.5" x-text="projecaoFechamento.dias_uteis_restantes + ' dias úteis restantes'"></p>
            </div>

            <!-- Clientes +30 dias sem comprar [por representante] -->
            <div x-show="clientesRisco.length > 0" class="flex-1 flex flex-col">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-[10px] font-bold text-rose-600 uppercase">
                        ⚠️ Clientes +<span x-text="riscoCriterio.dias_sem_comprar"></span> dias sem compras [Por Representante]
                    </p>
                    <span class="text-[10px] font-bold bg-rose-100 text-rose-700 px-2 py-0.5 rounded-full" x-text="clientesRiscoFiltrados().length + ' clientes'"></span>
                </div>

                <!-- Filtro rápido por representante dentro do card -->
                <div class="mb-2" x-show="representantesRisco().length > 1">
                    <select x-model="filtroRiscoRep" class="w-full px-2.5 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs text-slate-700 focus:outline-none focus:ring-2 focus:ring-rose-500">
                        <option value="">Todos os representantes (<span x-text="clientesRisco.length"></span> clientes)</option>
                        <template x-for="rep in representantesRisco()" :key="rep.id">
                            <option :value="rep.id" x-text="rep.nome + ' (' + rep.qtd + ' clientes)'"></option>
                        </template>
                    </select>
                </div>

                <div class="divide-y divide-slate-100 max-h-56 overflow-y-auto pr-1 border border-slate-100 rounded-xl bg-slate-50/40">
                    <template x-for="c in clientesRiscoFiltrados()" :key="c.id + '-' + (c.idrepresentante || 0)">
                        <div class="flex items-center justify-between px-2.5 py-2 text-xs hover:bg-rose-50/60 cursor-pointer transition-colors" @click="verDetalhesCliente(c.id, c.nome)">
                            <div class="min-w-0 pr-2">
                                <p class="font-semibold text-slate-700 truncate" x-text="c.nome"></p>
                                <p class="text-[10px] text-slate-400 truncate">
                                    <i class="fa-solid fa-user-tie mr-1 text-slate-400"></i><span x-text="c.representante || 'Sem Representante'"></span>
                                    <span x-show="c.ultima_fmt" x-text="' • Últ. NF: ' + c.ultima_fmt"></span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700" x-text="c.dias_sem_comprar + 'd'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
            <div x-show="clientesRisco.length === 0" class="text-center py-4 text-xs text-slate-400">
                Nenhum cliente com +30 dias sem compras para o filtro selecionado.
            </div>
        </div>
    </div>
</div>
    <!-- MODAL DETALHES PRODUTO -->
    <div x-show="modalProdutoOpen" x-cloak @click.self="fecharModalProduto()" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold"><i class="fa-solid fa-cube mr-2"></i> <span x-text="produtoDetalhes.nome"></span></h3>
                <button @click="fecharModalProduto()" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div x-show="produtoLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
                <div x-show="produtoErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="produtoErro"></div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center"><p class="text-[10px] text-emerald-600 uppercase font-bold">Receita Líquida</p><p class="text-xl font-black text-emerald-700" x-text="formatMoney(produtoDetalhes.valor)">R$ 0</p></div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center"><p class="text-[10px] text-amber-600 uppercase font-bold">Comissão Ajustada</p><p class="text-xl font-black text-amber-700" x-text="formatMoney(produtoDetalhes.comissao || 0)">R$ 0</p></div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center"><p class="text-[10px] text-purple-600 uppercase font-bold">Preço Médio</p><p class="text-xl font-black text-purple-700" x-text="formatMoney(produtoDetalhes.preco_medio)">R$ 0</p></div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center"><p class="text-[10px] text-blue-600 uppercase font-bold">Total Clientes</p><p class="text-xl font-black text-blue-700" x-text="formatNumber(produtoDetalhes.total_clientes)">0</p></div>
                </div>
                <h4 class="text-sm font-bold text-slate-700 mb-3">Top 10 Clientes que mais compram este produto</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto mb-6">
                    <template x-for="(item, idx) in produtoDetalhes.clientes" :key="idx">
                        <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50">
                            <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-slate-100" x-text="idx + 1"></span><span class="text-sm font-medium" x-text="item.cliente"></span></div>
                            <div class="text-right"><span class="text-xs font-bold text-emerald-600" x-text="formatMoney(item.valor)"></span><span class="text-[10px] text-slate-400 ml-2" x-text="formatNumber(item.quantidade) + ' unid.'"></span></div>
                        </div>
                    </template>
                </div>
                <div id="chartProdutoEvolucao" style="height: 250px; width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- MODAL DETALHES CLIENTE -->
    <div x-show="modalClienteOpen" x-cloak @click.self="modalClienteOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold"><i class="fa-solid fa-user mr-2"></i> <span x-text="clienteDetalhes.nome"></span></h3>
                <button @click="modalClienteOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div x-show="clienteLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
                <div x-show="clienteErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="clienteErro"></div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center"><p class="text-[10px] text-emerald-600 uppercase font-bold">Valor Total</p><p class="text-xl font-black text-emerald-700" x-text="formatMoney(clienteDetalhes.valor)">R$ 0</p></div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center"><p class="text-[10px] text-blue-600 uppercase font-bold">Total NFs</p><p class="text-xl font-black text-blue-700" x-text="formatNumber(clienteDetalhes.total_notas)">0</p></div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center"><p class="text-[10px] text-purple-600 uppercase font-bold">Ticket Médio</p><p class="text-xl font-black text-purple-700" x-text="formatMoney(clienteDetalhes.ticket_medio)">R$ 0</p></div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center"><p class="text-[10px] text-amber-600 uppercase font-bold">Quantidade</p><p class="text-xl font-black text-amber-700" x-text="formatNumber(clienteDetalhes.quantidade)">0</p></div>
                </div>
                <h4 class="text-sm font-bold text-slate-700 mb-3">Top 10 Produtos comprados por este cliente</h4>
                <div class="space-y-2 max-h-60 overflow-y-auto">
                    <template x-for="(item, idx) in clienteDetalhes.produtos" :key="idx">
                        <div class="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50">
                            <div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold bg-slate-100" x-text="idx + 1"></span><span class="text-sm font-medium" x-text="item.produto"></span></div>
                            <div class="text-right"><span class="text-xs font-bold text-emerald-600" x-text="formatMoney(item.valor)"></span><span class="text-[10px] text-slate-400 ml-2" x-text="formatNumber(item.quantidade) + ' unid.'"></span></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DETALHES DO CARD (PREMIUM + RASTREIO) -->
    <div x-show="modalCardOpen" x-cloak @click.self="modalCardOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-emerald-700 via-emerald-600 to-teal-600 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 class="text-white font-bold text-base flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                        <span x-text="modalCardTitulo"></span>
                    </h3>
                    <p class="text-[11px] text-emerald-100 mt-0.5" x-text="cardVisao || 'Auditoria executiva e rastreio de documentos do período'"></p>
                </div>
                <button @click="modalCardOpen = false" class="text-white/80 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>

            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)] space-y-4">
                <div x-show="cardLoading" class="text-center py-12 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Carregando detalhes e rastreio...</div>
                <div x-show="cardErro" class="p-3 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="cardErro"></div>
                <div x-show="!cardLoading && !cardErro && cardDetalhes.length === 0 && cardRastreio.length === 0" class="text-center py-12 text-slate-400">
                    <i class="fa-solid fa-chart-simple text-4xl mb-3 block"></i>
                    <p>Sem dados para o período e filtros selecionados.</p>
                </div>

                <div x-show="!cardLoading && !cardErro && (cardDetalhes.length > 0 || cardRastreio.length > 0)">
                    <!-- KPIs Superiores Customizados para POSITIVAÇÃO vs DEMAIS CARDS -->
                    <template x-if="modalCardTipo === 'positivacao'">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4 text-center">
                            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">
                                <p class="text-[10px] text-emerald-600 uppercase font-bold">Faturamento Positivado</p>
                                <p class="text-lg font-black text-emerald-700" x-text="formatMoney(cardTotal ? cardTotal.valor : 0)"></p>
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
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4 text-center">
                            <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-3">
                                <p class="text-[10px] text-emerald-600 uppercase font-bold">Valor Total</p>
                                <p class="text-lg font-black text-emerald-700" x-text="formatMoney(cardTotal ? cardTotal.valor : 0)"></p>
                            </div>
                            <div class="bg-blue-50 border border-blue-100 rounded-xl p-3">
                                <p class="text-[10px] text-blue-600 uppercase font-bold" x-text="cardRotulo || 'Documentos'"></p>
                                <p class="text-lg font-black text-blue-700" x-text="formatNumber(cardTotal ? cardTotal.documentos : 0)"></p>
                            </div>
                            <div class="bg-amber-50 border border-amber-100 rounded-xl p-3">
                                <p class="text-[10px] text-amber-600 uppercase font-bold">Ticket Médio</p>
                                <p class="text-lg font-black text-amber-700" x-text="formatMoney(cardTotal ? cardTotal.ticket_medio : 0)"></p>
                            </div>
                            <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                                <p class="text-[10px] text-slate-500 uppercase font-bold">Registros Rastreáveis</p>
                                <p class="text-lg font-black text-slate-700" x-text="formatNumber(cardRastreio.length || cardDetalhes.length)"></p>
                            </div>
                        </div>
                    </template>

                    <!-- Barra de Abas + Busca Instantânea -->
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-200">
                        <div class="flex flex-wrap items-center gap-1.5">
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
                                x-show="cardDetalhes.length > 0"
                                @click="cardAba = 'resumo'"
                                :class="cardAba === 'resumo' ? 'bg-emerald-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all">
                                <i class="fa-solid fa-table-list mr-1"></i>
                                <span x-text="modalCardTipo === 'positivacao' ? 'Por Representante (QT Carteira & %)' : 'Visão Executiva'"></span>
                                (<span x-text="cardDetalhes.length"></span>)
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
                        <!-- Tabela específica para POSITIVAÇÃO DA CARTEIRA -->
                        <template x-if="modalCardTipo === 'positivacao'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Valor Faturado</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Clientes Positivados</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">QT Carteira</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">% Positivação</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-800" x-text="item.label || item.periodo"></td>
                                            <td class="px-4 py-3 text-right text-emerald-600 font-bold" x-text="formatMoney(item.valor)"></td>
                                            <td class="px-4 py-3 text-right font-bold text-blue-600" x-text="formatNumber(item.positivados ?? item.documentos ?? 0)"></td>
                                            <td class="px-4 py-3 text-right font-bold text-slate-700" x-text="formatNumber(item.qt_carteira ?? 0)"></td>
                                            <td class="px-4 py-3 text-right">
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
                                <tfoot class="bg-slate-50 border-t-2 border-slate-200 font-bold text-slate-800" x-show="cardTotal">
                                    <tr>
                                        <td class="px-4 py-2.5">Total Geral</td>
                                        <td class="px-4 py-2.5 text-right text-emerald-700" x-text="formatMoney(cardTotal ? cardTotal.valor : 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-blue-700" x-text="formatNumber(cardTotal ? (cardTotal.positivados ?? cardTotal.documentos ?? 0) : 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-slate-800" x-text="formatNumber(cardTotal ? (cardTotal.qt_carteira ?? 0) : 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-purple-700" x-text="(cardTotal ? (cardTotal.percentual ?? 0) : 0) + '%'"></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </template>

                        <!-- Tabela específica para TICKET MÉDIO -->
                        <template x-if="modalCardTipo === 'ticket_medio'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">NFs</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Clientes</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Menor NF</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Maior NF</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Faturamento</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-800" x-text="item.label"></td>
                                            <td class="px-4 py-3 text-right font-black text-amber-600" x-text="formatMoney(item.ticket_medio || 0)"></td>
                                            <td class="px-4 py-3 text-right text-slate-600" x-text="formatNumber(item.documentos || 0)"></td>
                                            <td class="px-4 py-3 text-right text-slate-600" x-text="formatNumber(item.clientes || 0)"></td>
                                            <td class="px-4 py-3 text-right text-xs text-slate-500" x-text="formatMoney(item.menor_nf || 0)"></td>
                                            <td class="px-4 py-3 text-right text-xs text-blue-600 font-bold" x-text="formatMoney(item.maior_nf || 0)"></td>
                                            <td class="px-4 py-3 text-right font-bold text-emerald-600" x-text="formatMoney(item.valor || 0)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>

                        <!-- Tabela específica para VENDAS POR DIA -->
                        <template x-if="modalCardTipo === 'vendas_dia'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Data</th>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Dia da Semana</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">NFs Emitidas</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Clientes</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Faturamento</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-800" x-text="item.label"></td>
                                            <td class="px-4 py-3 text-xs text-slate-500" x-text="item.dia_semana || '—'"></td>
                                            <td class="px-4 py-3 text-right text-slate-600" x-text="formatNumber(item.documentos || 0)"></td>
                                            <td class="px-4 py-3 text-right text-slate-600" x-text="formatNumber(item.clientes || 0)"></td>
                                            <td class="px-4 py-3 text-right text-amber-600 font-semibold" x-text="formatMoney(item.ticket_medio || 0)"></td>
                                            <td class="px-4 py-3 text-right font-bold text-emerald-600" x-text="formatMoney(item.valor || 0)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>

                        <!-- Tabela executiva para FATURAMENTO, NOTAS, CARTEIRA, CANCELADOS, DEVOLUÇÕES -->
                        <template x-if="!['positivacao', 'ticket_medio', 'vendas_dia'].includes(modalCardTipo)">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase" x-text="cardRotulo || 'Documentos'"></th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Clientes</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase" x-show="modalCardTipo === 'faturamento'">Comissão</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">Valor</th>
                                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-500 uppercase">% Part.</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(item, idx) in cardListaResumoFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-800" x-text="item.label || item.periodo"></td>
                                            <td class="px-4 py-3 text-right text-slate-600 font-semibold" x-text="formatNumber(item.documentos || 0)"></td>
                                            <td class="px-4 py-3 text-right text-slate-600" x-text="formatNumber(item.clientes || 0)"></td>
                                            <td class="px-4 py-3 text-right text-amber-600" x-text="formatMoney(item.ticket_medio || 0)"></td>
                                            <td class="px-4 py-3 text-right text-amber-700 font-semibold" x-show="modalCardTipo === 'faturamento'" x-text="formatMoney(item.comissao || 0)"></td>
                                            <td class="px-4 py-3 text-right text-emerald-600 font-bold" x-text="formatMoney(item.valor || 0)"></td>
                                            <td class="px-4 py-3 text-right text-xs font-bold text-slate-500" x-text="(item.percentual ?? item.participacao ?? 0) + '%'"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </template>
                    </div>

                    <!-- ABA 2: RASTREIO DE DOCUMENTOS / CLIENTES -->
                    <div x-show="cardAba === 'rastreio'" class="overflow-x-auto border border-slate-200 rounded-xl max-h-[50vh]">
                        <template x-if="modalCardTipo === 'positivacao'">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Cliente Positivado</th>
                                        <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">NFs no Período</th>
                                        <th class="px-4 py-2.5 text-center text-xs font-bold text-slate-500 uppercase">Última Compra</th>
                                        <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Valor Comprado</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="(r, idx) in cardListaRastreioFiltrada()" :key="idx">
                                        <tr class="hover:bg-slate-50 cursor-pointer" @click="verDetalhesCliente(r.idcliente || r.id_doc, r.cliente)">
                                            <td class="px-4 py-2.5 font-medium text-slate-800" x-text="r.cliente"></td>
                                            <td class="px-4 py-2.5 text-xs text-slate-600" x-text="r.representante"></td>
                                            <td class="px-4 py-2.5 text-right text-xs font-bold text-blue-600" x-text="formatNumber(r.documentos || r.notas || 0)"></td>
                                            <td class="px-4 py-2.5 text-center text-xs text-slate-500" x-text="r.data"></td>
                                            <td class="px-4 py-2.5 text-right font-bold text-emerald-600" x-text="formatMoney(r.valor || 0)"></td>
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
                                        <th class="px-3 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Comissão</th>
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
                                            <td class="px-3 py-2.5 text-xs font-medium text-slate-800 truncate max-w-[200px]" :title="r.cliente">
                                                <span class="hover:text-blue-600 hover:underline cursor-pointer" @click="verDetalhesCliente(r.idcliente, r.cliente)" x-text="r.cliente"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-xs text-slate-600 truncate max-w-[160px]" :title="r.representante" x-text="r.representante"></td>
                                            <td class="px-3 py-2.5 text-right text-xs text-amber-700 font-semibold" x-text="formatMoney(r.comissao || 0)"></td>
                                            <td class="px-3 py-2.5 text-right text-xs font-bold text-emerald-600" x-text="formatMoney(r.valor || 0)"></td>
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
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Ticket Médio</th>
                                    <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="(d, idx) in cardListaPorDiaFiltrada()" :key="idx">
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-2.5 font-medium text-slate-800" x-text="d.label || d.periodo"></td>
                                        <td class="px-4 py-2.5 text-right text-slate-600 font-semibold" x-text="formatNumber(d.documentos || 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-slate-600" x-text="formatNumber(d.clientes || 0)"></td>
                                        <td class="px-4 py-2.5 text-right text-amber-600" x-text="formatMoney(d.ticket_medio || 0)"></td>
                                        <td class="px-4 py-2.5 text-right font-bold text-emerald-600" x-text="formatMoney(d.valor || 0)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>

                    <div x-show="cardExtra && cardExtra.por_dia_semana && cardExtra.por_dia_semana.length" class="mt-4 bg-slate-50 p-4 rounded-xl border border-slate-200">
                        <h4 class="text-xs font-bold text-slate-600 uppercase mb-2">Média de Faturamento por Dia da Semana</h4>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                            <template x-for="d in (cardExtra.por_dia_semana || [])" :key="d.dow">
                                <div class="bg-white p-2.5 rounded-lg border border-slate-100 text-center">
                                    <p class="text-[10px] font-bold text-slate-500 uppercase" x-text="d.dia"></p>
                                    <p class="text-xs font-black text-emerald-600 mt-0.5" x-text="formatMoney(d.media)"></p>
                                    <p class="text-[9px] text-slate-400" x-text="d.dias + ' dias'"></p>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EXPANDIDO: TODOS OS CLIENTES +30 DIAS SEM COMPRAS [POR REPRESENTANTE] -->
    <div x-show="modalRiscoOpen" x-cloak @click.self="modalRiscoOpen = false" class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl max-h-[90vh] overflow-hidden flex flex-col">
            <div class="bg-gradient-to-r from-rose-700 to-rose-600 px-6 py-4 flex justify-between items-center">
                <div>
                    <h3 class="text-white font-bold text-base">
                        <i class="fa-solid fa-user-clock mr-2"></i> Clientes +<span x-text="riscoCriterio.dias_sem_comprar"></span> Dias sem Compras [Por Representante]
                    </h3>
                    <p class="text-[11px] text-rose-100 mt-0.5">Relação completa de clientes ativos da carteira sem faturamento nos últimos <span x-text="riscoCriterio.dias_sem_comprar"></span>+ dias</p>
                </div>
                <button @click="modalRiscoOpen = false" class="text-white/80 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)] space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
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
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Buscar Cliente ou Cidade</label>
                        <input type="text" x-model="buscaRiscoCliente" placeholder="Digite o nome do cliente, código ou cidade..."
                            class="w-full px-3 py-2 rounded-lg border border-slate-200 bg-slate-50 text-xs focus:outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                    <div class="bg-rose-50 border border-rose-100 rounded-xl p-3 flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-bold text-rose-600 uppercase">Total Exibido</p>
                            <p class="text-xl font-black text-rose-700" x-text="clientesRiscoFiltrados().length + ' clientes'"></p>
                        </div>
                        <i class="fa-solid fa-triangle-exclamation text-2xl text-rose-400"></i>
                    </div>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-xl max-h-[60vh]">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200 sticky top-0">
                            <tr>
                                <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Cliente</th>
                                <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Representante</th>
                                <th class="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase">Cidade/UF</th>
                                <th class="px-4 py-2.5 text-center text-xs font-bold text-slate-500 uppercase">Última Compra</th>
                                <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">NFs Hist.</th>
                                <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Faturamento Hist.</th>
                                <th class="px-4 py-2.5 text-right text-xs font-bold text-slate-500 uppercase">Inatividade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="c in clientesRiscoFiltrados()" :key="c.id + '-' + (c.idrepresentante || 0)">
                                <tr class="hover:bg-rose-50/40 cursor-pointer" @click="modalRiscoOpen = false; verDetalhesCliente(c.id, c.nome)">
                                    <td class="px-4 py-2.5 font-semibold text-slate-800" x-text="c.nome"></td>
                                    <td class="px-4 py-2.5 text-xs text-slate-600 font-medium" x-text="c.representante"></td>
                                    <td class="px-4 py-2.5 text-xs text-slate-500" x-text="c.cidade_uf || '—'"></td>
                                    <td class="px-4 py-2.5 text-center text-xs text-slate-600" x-text="c.ultima_fmt || 'Sem compra recente'"></td>
                                    <td class="px-4 py-2.5 text-right text-xs text-slate-600" x-text="formatNumber(c.compras_anteriores || 0)"></td>
                                    <td class="px-4 py-2.5 text-right text-xs font-bold text-emerald-600" x-text="formatMoney(c.valor_historico || 0)"></td>
                                    <td class="px-4 py-2.5 text-right">
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
<!-- MODAL DETALHES REPRESENTANTE -->
<div x-show="modalRepresentanteOpen" x-cloak @click.self="modalRepresentanteOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-amber-700 to-amber-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold"><i class="fa-solid fa-user-tie mr-2"></i> <span x-text="representanteDetalhes.nome"></span></h3>
            <button @click="modalRepresentanteOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
            <div x-show="repLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
            <div x-show="repErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="repErro"></div>
            <div x-show="representanteDetalhes.total" class="grid grid-cols-3 gap-3 mb-4 text-center">
                <div class="bg-emerald-50 rounded-xl p-2"><p class="text-[10px] text-emerald-600 uppercase font-bold">Faturamento</p><p class="font-black text-emerald-700" x-text="formatMoney(representanteDetalhes.total ? representanteDetalhes.total.valor : 0)"></p></div>
                <div class="bg-blue-50 rounded-xl p-2"><p class="text-[10px] text-blue-600 uppercase font-bold">NFs</p><p class="font-black text-blue-700" x-text="formatNumber(representanteDetalhes.total ? representanteDetalhes.total.notas : 0)"></p></div>
                <div class="bg-amber-50 rounded-xl p-2"><p class="text-[10px] text-amber-600 uppercase font-bold">Clientes</p><p class="font-black text-amber-700" x-text="formatNumber(representanteDetalhes.total ? representanteDetalhes.total.clientes : 0)"></p></div>
            </div>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-bold text-slate-700 mb-3">Produtos</h4>
                                <div class="space-y-2">
                                    <template x-for="(item, idx) in representanteDetalhes.produtos" :key="idx">
                            <div class="flex justify-between p-2 bg-slate-50 rounded-lg">
                                <span class="text-sm text-slate-700 truncate max-w-[200px]" x-text="item.produto"></span>
                                <span class="text-sm font-bold text-emerald-600" x-text="formatMoney(item.valor)"></span>
                            </div>
                        </template>
                    </div>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-700 mb-3">Clientes Ativos</h4>
                    <div class="space-y-2 max-h-60 overflow-y-auto">
                        <template x-for="c in representanteDetalhes.clientes" :key="c.id">
                            <div class="flex justify-between p-2 bg-slate-50 rounded-lg text-xs">
                                <span class="text-slate-700 truncate max-w-[150px]" x-text="c.nome"></span>
                                <span class="text-slate-400" x-text="c.total_notas + ' NFs'"></span>
                                <span class="font-bold text-emerald-600" x-text="formatMoney(c.valor)"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DETALHES FUNIL -->
<div x-show="modalFunilOpen" x-cloak @click.self="modalFunilOpen = false" class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold"><i class="fa-solid fa-list-check mr-2"></i> Pedidos <span x-text="funilDetalhes.etapa"></span></h3>
            <button @click="modalFunilOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
            <div x-show="funilLoading" class="text-center py-6 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando...</div>
            <div x-show="funilErro" class="p-3 mb-4 rounded-xl bg-rose-50 text-rose-700 text-sm" x-text="funilErro"></div>
            <p x-show="funilDetalhes.total" class="text-xs text-slate-500 mb-3">
                Total: <b x-text="funilDetalhes.total ? funilDetalhes.total.quantidade : 0"></b> pedidos / <b x-text="formatMoney(funilDetalhes.total ? funilDetalhes.total.valor : 0)"></b>
                <span x-show="funilDetalhes.truncado" class="text-amber-600"> — exibindo apenas os 50 maiores.</span>
            </p>
            <div x-show="!funilLoading && !funilErro && funilDetalhes.pedidos.length === 0" class="text-center py-8 text-slate-400 text-sm">Nenhum pedido nesta etapa.</div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400">Pedido</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400">Data</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400">Cliente</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400">Representante</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-400">Valor</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-400">Qtd</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="p in funilDetalhes.pedidos" :key="p.idpedido">
                        <tr class="border-b border-slate-100">
                            <td class="px-4 py-3 font-mono text-xs text-emerald-600" x-text="'#' + p.idpedido"></td>
                            <td class="px-4 py-3 text-xs" x-text="p.data"></td>
                            <td class="px-4 py-3 text-xs truncate max-w-[150px]" x-text="p.cliente"></td>
                            <td class="px-4 py-3 text-xs" x-text="p.representante"></td>
                            <td class="px-4 py-3 text-right text-xs font-bold text-emerald-600" x-text="formatMoney(p.valor)"></td>
                            <td class="px-4 py-3 text-right text-xs" x-text="p.quantidade"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
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

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>