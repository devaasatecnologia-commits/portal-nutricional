<?php
$pageTitle = 'Dashboard Vendas | Nutricional';
$moduleJs = 'dashboard-vendas.js';
$version = time();
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
        
        <!-- FILTROS -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 mb-6">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[180px]">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-regular fa-calendar mr-1"></i> Período
                    </label>
                    <div class="flex gap-2">
                        <input type="date" x-model="filters.data_inicio" @change="mudarPeriodo()" class="flex-1 p-2 border border-slate-200 rounded-lg text-sm">
                        <input type="date" x-model="filters.data_fim" @change="mudarPeriodo()" class="flex-1 p-2 border border-slate-200 rounded-lg text-sm">
                    </div>
                </div>
                <div class="w-44">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-building mr-1"></i> Filial
                    </label>
                    <select x-model="filters.filial" @change="mudarFilial()" class="w-full p-2 border border-slate-200 rounded-lg text-sm">
                        <option value="">Todas</option>
                        <template x-for="f in filiaisPermitidas" :key="f.idfilial">
                            <option :value="f.idfilial" x-text="f.nome"></option>
                        </template>
                    </select>
                </div>
                <div class="w-52">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-user-tie mr-1"></i> Gestor
                    </label>
                    <select x-model="filters.gestor" @change="mudarGestor()" class="w-full p-2 border border-amber-200 bg-amber-50 rounded-lg text-sm">
                        <option value="">Todos</option>
                        <template x-for="g in gestoresDisponiveis" :key="g.id">
                            <option :value="g.id" x-text="g.nome"></option>
                        </template>
                    </select>
                </div>
                <div class="w-52">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-user mr-1"></i> Representante
                    </label>
                    <select x-model="filters.representante" @change="mudarRepresentante()" class="w-full p-2 border border-slate-200 rounded-lg text-sm">
                        <option value="">Todos</option>
                        <template x-for="r in representantesDisponiveis" :key="r.id">
                            <option :value="r.id" x-text="r.nome"></option>
                        </template>
                    </select>
                </div>
                <button @click="carregarDados()" class="px-4 py-2 bg-emerald-600 text-white rounded-lg font-bold hover:bg-emerald-700 transition-all">
                    <i class="fa-solid fa-rotate-right mr-1"></i>Atualizar
                </button>
                <button @click="exportarDados()" class="px-4 py-2 border border-emerald-200 text-emerald-600 rounded-lg font-bold hover:bg-emerald-50 transition-all">
                    <i class="fa-regular fa-file-excel mr-1"></i>Exportar
                </button>
            </div>
        </div>

        <!-- CARDS PRINCIPAIS -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    
    <!-- Faturamento Total -->
    <div class="bg-gradient-to-br from-emerald-500 to-emerald-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Faturamento')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-emerald-100 text-xs uppercase tracking-wider font-bold">Faturamento Total</p>
                <p class="text-3xl font-black mt-2" x-text="formatMoney(kpis.faturamento)">R$ 0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-dollar-sign text-xl"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2 text-xs">
            <span :class="kpis.crescimento_faturamento >= 0 ? 'text-emerald-200' : 'text-rose-200'">
                <i :class="kpis.crescimento_faturamento >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                <span x-text="Math.abs(kpis.crescimento_faturamento) + '%'"></span>
            </span>
            <span class="text-emerald-100">vs mês anterior</span>
        </div>
    </div>

    <!-- Total de Pedidos -->
    <div class="bg-gradient-to-br from-blue-500 to-blue-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Pedidos')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-blue-100 text-xs uppercase tracking-wider font-bold">Total de Pedidos</p>
                <p class="text-3xl font-black mt-2" x-text="formatNumber(kpis.total_pedidos)">0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-cart-shopping text-xl"></i>
            </div>
        </div>
        <div class="mt-3 flex items-center gap-2 text-xs">
            <span :class="kpis.crescimento_pedidos >= 0 ? 'text-blue-200' : 'text-rose-200'">
                <i :class="kpis.crescimento_pedidos >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                <span x-text="Math.abs(kpis.crescimento_pedidos) + '%'"></span>
            </span>
            <span class="text-blue-100">vs mês anterior</span>
        </div>
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
            <span :class="kpis.crescimento_ticket >= 0 ? 'text-amber-200' : 'text-rose-200'">
                <i :class="kpis.crescimento_ticket >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'"></i>
                <span x-text="Math.abs(kpis.crescimento_ticket) + '%'"></span>
            </span>
            <span class="text-amber-100">vs mês anterior</span>
        </div>
    </div>

    <!-- Vendas por Dia -->
    <div class="bg-gradient-to-br from-purple-500 to-purple-700 rounded-2xl p-5 text-white shadow-lg cursor-pointer hover:scale-105 transition-transform" @click="abrirDetalhesCard('Vendas por Dia')">
        <div class="flex justify-between items-start">
            <div>
                <p class="text-purple-100 text-xs uppercase tracking-wider font-bold">Vendas por Dia</p>
                <p class="text-3xl font-black mt-2" x-text="formatMoney(kpis.media_diaria)">R$ 0</p>
            </div>
            <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-gauge-high text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-xs text-purple-100">
            <span>💰 R$ <span x-text="formatNumber(kpis.ticket_medio_diario)"></span> por dia útil</span>
        </div>
    </div>
</div>

        <!-- GRÁFICO DE EVOLUÇÃO + PROJEÇÃO -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-sm font-bold text-slate-700 uppercase">
                        <i class="fa-regular fa-chart-line mr-2 text-emerald-500"></i> EVOLUÇÃO DE VENDAS
                    </h3>
                    <div class="flex gap-2">
                        <button @click="tipoGrafico = 'valor'; renderizarGraficoEvolucao()" :class="tipoGrafico === 'valor' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">Valor (R$)</button>
                        <button @click="tipoGrafico = 'quantidade'; renderizarGraficoEvolucao()" :class="tipoGrafico === 'quantidade' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-500'" class="px-3 py-1 rounded-lg text-xs font-bold transition-all">Quantidade</button>
                    </div>
                </div>
                <div id="chartEvolucao" style="height: 320px; width: 100%;"></div>
            </div>
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
                <h3 class="text-sm font-bold text-slate-700 uppercase mb-4">
                    <i class="fa-solid fa-chart-simple mr-2 text-amber-500"></i> PROJEÇÃO PRÓXIMOS MESES
                </h3>
                <div id="projecaoContainer" class="space-y-3">
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
                    <div x-show="projecao.length > 0">
                        <template x-for="(item, idx) in projecao" :key="idx">
                            <div class="bg-slate-50 rounded-xl p-3">
                                <div class="flex justify-between items-center mb-2">
                                    <span class="text-xs font-bold text-slate-500" x-text="item.mes_label"></span>
                                    <span :class="item.realizado > 0 ? 'text-emerald-600' : 'text-amber-600'" class="text-sm font-black">
                                        R$ <span x-text="formatNumber(item.valor)"></span>
                                    </span>
                                </div>
                                <div class="w-full bg-slate-200 rounded-full h-2">
                                    <div class="h-2 rounded-full" :class="item.realizado > 0 ? 'bg-emerald-500' : 'bg-amber-500'" :style="'width: ' + (item.valor / maxProjecao * 100) + '%'"></div>
                                </div>
                                <div class="flex justify-between mt-1 text-[10px] text-slate-400">
                                    <span x-show="item.realizado > 0">✓ Realizado</span>
                                    <span x-show="item.realizado === 0">📈 Projeção</span>
                                </div>
                            </div>
                        </template>
                    </div>
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
                                    <p class="text-[10px] text-slate-400" x-text="item.total_pedidos + ' pedidos'"></p>
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
            <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
                <h3 class="text-sm font-bold text-slate-700 uppercase">
                    <i class="fa-solid fa-chart-pie mr-2 text-rose-500"></i> MARGEM POR PRODUTO
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Produto</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Receita</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Comissão</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Margem</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Qtd</th>
                            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
                        </tr>
                    </thead>
                    <tbody x-show="margemProdutos.length > 0">
                        <template x-for="(item, idx) in margemProdutos" :key="idx">
                            <tr class="border-b border-slate-100 hover:bg-slate-50 cursor-pointer" @click="verDetalhesProduto(item.iditem, item.produto)">
                                <td class="px-4 py-3">
                                    <span class="font-medium text-slate-800" x-text="item.produto || '---'"></span>
                                    <br><span class="text-[10px] text-slate-400" x-text="item.referencia || '---'"></span>
                                </td>
                                <td class="px-4 py-3 text-right text-emerald-600 font-bold" x-text="formatMoney(item.receita || 0)"></td>
                                <td class="px-4 py-3 text-right text-amber-600" x-text="formatMoney(item.comissao || 0)"></td>
                                <td class="px-4 py-3 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <div class="w-16 h-2 bg-slate-200 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full" :class="(item.margem_percentual || 0) >= 10 ? 'bg-green-500' : ((item.margem_percentual || 0) >= 5 ? 'bg-yellow-500' : 'bg-red-500')" :style="'width: ' + Math.min((item.margem_percentual || 0), 100) + '%'"></div>
                                        </div>
                                        <span class="text-xs font-bold" :class="(item.margem_percentual || 0) >= 10 ? 'text-green-600' : ((item.margem_percentual || 0) >= 5 ? 'text-yellow-600' : 'text-red-600')" x-text="(item.margem_percentual || 0) + '%'"></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right text-blue-600" x-text="formatNumber(item.quantidade || 0)"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2 py-1 rounded-full text-[10px] font-bold" :class="(item.margem_percentual || 0) >= 10 ? 'bg-green-100 text-green-700' : ((item.margem_percentual || 0) >= 5 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700')">
                                        <span x-text="(item.margem_percentual || 0) >= 10 ? 'Lucrativo' : ((item.margem_percentual || 0) >= 5 ? 'Atenção' : 'Crítico')"></span>
                                    </span>
                                </td>
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
    </div>
<!-- INSIGHTS: RANKING + FUNIL + ALERTAS -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    
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
                            <p class="text-[10px] text-slate-400" x-text="item.total_pedidos + ' pedidos | ' + item.clientes_ativos + ' clientes'"></p>
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
        </div>
        <div class="p-4 space-y-3" x-show="funilPedidos.length > 0">
            <template x-for="(item, idx) in funilPedidos" :key="idx">
                <div class="cursor-pointer" @click="verDetalhesFunil(item.etapa)">
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-bold text-slate-600" x-text="item.etapa"></span>
                        <span class="text-slate-500" x-text="item.quantidade + ' pedidos'"></span>
                    </div>
                    <div class="w-full bg-slate-200 rounded-full h-2">
                        <div class="h-2 rounded-full" :class="idx === 0 ? 'bg-blue-500' : 'bg-emerald-500'" :style="'width: ' + Math.min((item.valor / funilPedidos[0].valor) * 100, 100) + '%'"></div>
                    </div>
                    <p class="text-[10px] text-slate-400 mt-1" x-text="formatMoney(item.valor)"></p>
                </div>
            </template>
        </div>
        <div x-show="funilPedidos.length === 0" class="text-center py-8 text-slate-400">
            <p class="text-sm">Nenhum dado disponível</p>
        </div>
    </div>

    <!-- Projeção + Alertas -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-slate-50 px-5 py-3 border-b border-slate-200">
            <h3 class="text-sm font-bold text-slate-700 uppercase">
                <i class="fa-solid fa-chart-line mr-2 text-purple-500"></i> PROJEÇÃO DE FECHAMENTO
            </h3>
        </div>
        <div class="p-4" x-show="projecaoFechamento.realizado">
            <div class="text-center mb-4">
                <p class="text-[10px] text-slate-400 uppercase">Projeção para fim do mês</p>
                <p class="text-2xl font-black text-purple-600" x-text="formatMoney(projecaoFechamento.projecao)">R$ 0</p>
                <p class="text-xs text-slate-400 mt-1">
                    <span x-text="formatMoney(projecaoFechamento.realizado)"></span> realizado + 
                    <span x-text="formatMoney(projecaoFechamento.projecao_adicional)"></span> projetado
                </p>
                <p class="text-[10px] text-slate-400 mt-1" x-text="projecaoFechamento.dias_uteis_restantes + ' dias úteis restantes'"></p>
            </div>
            <!-- Alertas -->
            <div x-show="clientesRisco.length > 0" class="mt-4">
                <p class="text-[10px] font-bold text-rose-500 uppercase mb-2">⚠️ Clientes sem comprar (30+ dias)</p>
                <template x-for="c in clientesRisco.slice(0, 3)" :key="c.id">
                    <div class="flex justify-between text-xs py-1 border-b border-slate-100 cursor-pointer hover:bg-red-50" @click="verDetalhesCliente(c.id, c.nome)">
                        <span class="text-slate-600 truncate max-w-[120px]" x-text="c.nome"></span>
                        <span class="text-rose-500 font-bold" x-text="c.dias_sem_comprar + 'd'"></span>
                    </div>
                </template>
            </div>
        </div>
        <div x-show="!projecaoFechamento.realizado" class="text-center py-8 text-slate-400">
            <p class="text-sm">Carregando...</p>
        </div>
    </div>
</div>
    <!-- MODAL DETALHES PRODUTO -->
    <div x-show="modalProdutoOpen" x-cloak @click.self="modalProdutoOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold"><i class="fa-solid fa-cube mr-2"></i> <span x-text="produtoDetalhes.nome"></span></h3>
                <button @click="modalProdutoOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center"><p class="text-[10px] text-emerald-600 uppercase font-bold">Valor Total</p><p class="text-xl font-black text-emerald-700" x-text="formatMoney(produtoDetalhes.valor)">R$ 0</p></div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center"><p class="text-[10px] text-blue-600 uppercase font-bold">Quantidade</p><p class="text-xl font-black text-blue-700" x-text="formatNumber(produtoDetalhes.quantidade)">0</p></div>
                    <div class="bg-purple-50 rounded-xl p-3 text-center"><p class="text-[10px] text-purple-600 uppercase font-bold">Preço Médio</p><p class="text-xl font-black text-purple-700" x-text="formatMoney(produtoDetalhes.preco_medio)">R$ 0</p></div>
                    <div class="bg-amber-50 rounded-xl p-3 text-center"><p class="text-[10px] text-amber-600 uppercase font-bold">Total Clientes</p><p class="text-xl font-black text-amber-700" x-text="formatNumber(produtoDetalhes.total_clientes)">0</p></div>
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
    <div x-show="modalClienteOpen" x-cloak @click.self="modalClienteOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold"><i class="fa-solid fa-user mr-2"></i> <span x-text="clienteDetalhes.nome"></span></h3>
                <button @click="modalClienteOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-emerald-50 rounded-xl p-3 text-center"><p class="text-[10px] text-emerald-600 uppercase font-bold">Valor Total</p><p class="text-xl font-black text-emerald-700" x-text="formatMoney(clienteDetalhes.valor)">R$ 0</p></div>
                    <div class="bg-blue-50 rounded-xl p-3 text-center"><p class="text-[10px] text-blue-600 uppercase font-bold">Total Pedidos</p><p class="text-xl font-black text-blue-700" x-text="formatNumber(clienteDetalhes.total_pedidos)">0</p></div>
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
    <!-- MODAL DETALHES DO CARD -->
<div x-show="modalCardOpen" x-cloak @click.self="modalCardOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl max-h-[85vh] overflow-hidden">
        <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold"><i class="fa-solid fa-magnifying-glass-chart mr-2"></i> <span x-text="modalCardTitulo"></span></h3>
            <button @click="modalCardOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(85vh-80px)]">
            <div x-show="cardDetalhes.length === 0" class="text-center py-12 text-slate-400">
                <i class="fa-solid fa-chart-simple text-4xl mb-3 block"></i>
                <p>Carregando detalhes...</p>
            </div>
            <div x-show="cardDetalhes.length > 0">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Período</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Valor</th>
                            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Qtd</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="(item, idx) in cardDetalhes" :key="idx">
                            <tr class="border-b border-slate-100">
                                <td class="px-4 py-3 text-slate-700" x-text="item.label || item.periodo"></td>
                                <td class="px-4 py-3 text-right text-emerald-600 font-bold" x-text="formatMoney(item.valor)"></td>
                                <td class="px-4 py-3 text-right text-blue-600" x-text="formatNumber(item.quantidade || 0)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<!-- MODAL DETALHES REPRESENTANTE -->
<div x-show="modalRepresentanteOpen" x-cloak @click.self="modalRepresentanteOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-amber-700 to-amber-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold"><i class="fa-solid fa-user-tie mr-2"></i> <span x-text="representanteDetalhes.nome"></span></h3>
            <button @click="modalRepresentanteOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-bold text-slate-700 mb-3">Top 5 Produtos</h4>
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
                                <span class="text-slate-400" x-text="c.total_pedidos + ' ped'"></span>
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
<div x-show="modalFunilOpen" x-cloak @click.self="modalFunilOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 to-blue-600 px-6 py-4 flex justify-between items-center">
            <h3 class="text-white font-bold"><i class="fa-solid fa-list-check mr-2"></i> Pedidos <span x-text="funilDetalhes.etapa"></span></h3>
            <button @click="modalFunilOpen = false" class="text-white/70 hover:text-white"><i class="fa-solid fa-times text-xl"></i></button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
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