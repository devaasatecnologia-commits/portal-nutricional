<?php
$pageTitle = 'Cubo Vendas | Nutricional';
$moduleJs = 'cubo-vendas.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/cubo-vendas.css?v=' . $version . '">
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="min-h-screen bg-slate-50" x-data="cuboVendasHandler()" x-init="init()">
    
    <!-- HEADER MOBILE -->
    <div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="/portal/" class="flex items-center gap-2 no-underline">
                <i class="fa-solid fa-arrow-left text-lg"></i>
                <span class="text-sm font-bold">VOLTAR</span>
            </a>
            <div class="text-center">
                <span class="text-sm font-bold modulo-nome">CUBO VENDAS</span>
            </div>
            <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
        </div>
    </div>
    <div class="mobile-spacer block lg:hidden h-14"></div>

    <!-- HEADER DESKTOP -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-cube text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">CUBO DE VENDAS</h2>
                <span class="text-xs text-slate-400 font-medium">Análise multidimensional de vendas</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-base lg:text-xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <div class="max-w-full mx-auto px-4 lg:px-6">
        
        <!-- PAINEL DE FILTROS -->
        <!-- PAINEL DE FILTROS - HIERARQUIA CORRETA -->
        <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
            <div class="flex flex-wrap items-end gap-4">
                
                <!-- Período -->
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-regular fa-calendar mr-1"></i> Período
                    </label>
                    <div class="flex gap-2">
                        <input type="date" x-model="filters.data_inicio" class="flex-1 p-3 border-2 border-slate-200 rounded-xl text-sm">
                        <input type="date" x-model="filters.data_fim" class="flex-1 p-3 border-2 border-slate-200 rounded-xl text-sm">
                    </div>
                </div>
                
                <!-- Filial -->
                <div class="w-48">
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-building mr-1"></i> Filial
                    </label>
                    <select x-model="filialSelecionada" @change="mudarFilial()" 
                    class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer">
                    <option value="">Todas as Filiais</option>
                    <template x-for="f in filiaisPermitidas" :key="f.idfilial">
                        <option :value="f.idfilial" x-text="f.nome"></option>
                    </template>
                </select>
            </div>
            
            <!-- ✅ Gestor/Supervisor (É O MESMO) -->
            <div class="w-56">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-user-tie mr-1 text-amber-500"></i> Gestor
                </label>
                <select x-model="gestorSelecionado" @change="mudarGestor()" 
                class="w-full p-3 border-2 border-amber-200 bg-amber-50/50 rounded-xl text-sm font-semibold cursor-pointer">
                <option value="">Todos os Gestores</option>
                <template x-for="g in gestoresDisponiveis" :key="g.id">
                    <option :value="g.id" x-text="g.nome"></option>
                </template>
            </select>
        </div>
        
        <!-- Representante -->
        <div class="w-56">
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                <i class="fa-solid fa-user mr-1"></i> Representante
            </label>
            <select x-model="representanteSelecionado" @change="mudarRepresentante()" 
            class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer">
            <option value="">Todos os Representantes</option>
            <template x-for="r in representantesDisponiveis" :key="r.id">
                <option :value="r.id" x-text="r.nome"></option>
            </template>
        </select>
    </div>
    
    <!-- Agrupar por -->
    <div class="flex-1 min-w-[180px]">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
            <i class="fa-solid fa-chart-line mr-1"></i> Agrupar por
        </label>
        <select x-model="rowDimension" @change="carregarDados()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm bg-white cursor-pointer">
            <template x-for="(dim, key) in dimensionsDisponiveis" :key="key">
                <option :value="key" x-text="dim.label"></option>
            </template>
        </select>
    </div>
    
    <!-- Botões -->
    <div class="flex gap-2">
        <button @click="abrirModalFiltrosAvancados()" class="px-4 py-3 bg-slate-200 text-slate-700 rounded-xl font-bold hover:bg-slate-300 transition-all">
            <i class="fa-solid fa-filter mr-2"></i>Filtros
        </button>
        <button @click="abrirModalMetricas()" class="px-4 py-3 bg-slate-200 text-slate-700 rounded-xl font-bold hover:bg-slate-300 transition-all">
            <i class="fa-solid fa-chart-simple mr-2"></i>Métricas
        </button>
        <button @click="exportarDados()" class="px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
            <i class="fa-regular fa-file-excel mr-2"></i>Exportar
        </button>
    </div>
</div>
</div>

<!-- CARDS TOTAIS -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white p-4 rounded-2xl border-l-4 border-emerald-500 shadow-sm">
        <span class="text-[10px] uppercase font-bold text-slate-400 block">Valor Total</span>
        <span class="block text-2xl font-black text-emerald-600" x-text="formatMoney(totals.valor_bruto)">R$ 0</span>
    </div>
    <div class="bg-white p-4 rounded-2xl border-l-4 border-blue-500 shadow-sm">
        <span class="text-[10px] uppercase font-bold text-slate-400 block">Quantidade</span>
        <span class="block text-2xl font-black text-blue-600" x-text="formatNumber(totals.quantidade)">0</span>
    </div>
    <div class="bg-white p-4 rounded-2xl border-l-4 border-indigo-500 shadow-sm">
        <span class="text-[10px] uppercase font-bold text-slate-400 block">Peso (kg)</span>
        <span class="block text-2xl font-black text-indigo-600" x-text="formatNumber(totals.peso)">0</span>
    </div>
    <div class="bg-white p-4 rounded-2xl border-l-4 border-amber-500 shadow-sm">
        <span class="text-[10px] uppercase font-bold text-slate-400 block">Comissão</span>
        <span class="block text-2xl font-black text-amber-600" x-text="formatMoney(totals.comissao)">R$ 0</span>
    </div>
</div>

<!-- GRÁFICO + RANKING (mesmo padrão do Financeiro) -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
  <!-- GRÁFICO -->
  <div class="lg:col-span-2 bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
    <div class="flex justify-between items-center mb-4">
        <h4 class="text-sm font-bold text-slate-700 uppercase">
            <i class="fa-regular fa-chart-line mr-2 text-emerald-500"></i> EVOLUÇÃO MENSAL
        </h4>
        <select id="graficoTipo" onchange="mudarTipoGrafico()" class="px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold">
            <option value="valor_bruto">Valor (R$)</option>
            <option value="quantidade">Quantidade</option>
            <option value="peso">Peso (kg)</option>
        </select>
    </div>
    <div style="height: 250px; width: 100%;">
        <canvas id="graficoEvolucao"></canvas>
    </div>
</div>     
<!-- RANKING -->
<div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100">
    <h4 class="text-sm font-bold text-slate-700 uppercase mb-4">
        <i class="fa-solid fa-trophy mr-2 text-amber-500"></i> TOP 10 CLIENTES
    </h4>
    <div class="space-y-2 max-h-80 overflow-y-auto" id="rankingContainer">
        <div class="text-center py-8 text-slate-400" id="rankingVazio">
            <i class="fa-solid fa-chart-line text-3xl mb-2 block"></i>
            <p class="text-sm">Selecione filtros para ver o ranking</p>
        </div>
    </div>
</div>
</div>

<!-- TABELA DE DADOS -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <div class="bg-slate-50 px-5 py-3 border-b border-slate-200 flex justify-between items-center">
        <h4 class="text-sm font-bold text-slate-700 uppercase">
            <i class="fa-solid fa-table mr-2 text-emerald-600"></i> 
            DETALHAMENTO POR <span x-text="rowDimensionLabel.toUpperCase()"></span>
        </h4>
        <span class="text-xs text-slate-400" x-text="tableData.length + ' linha(s)'"></span>
    </div>
    <div class="overflow-x-auto" id="tabelaContainer">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase" x-text="rowDimensionLabel"></th>
                    <template x-for="metric in metricsSelecionadas" :key="metric">
                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase" x-text="getMetricLabel(metric)"></th>
                    </template>
                    <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">% Participação</th>
                </tr>
            </thead>
            <tbody>
                <template x-for="row in tableData" :key="row.dimensao">
                 <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer" 
                 @click="verDetalhes(row.dimensao)">
                 <td class="px-4 py-3 font-medium text-slate-700" x-text="row.dimensao"></td>
                 <template x-for="metric in metricsSelecionadas" :key="metric">
                    <td class="px-4 py-3 text-right" :class="getMetricClass(metric)" x-text="formatMetric(row[metric], metric)"></td>
                </template>
                <td class="px-4 py-3 text-right text-emerald-600 font-bold" x-text="row.percentual_participacao + '%'"></td>
            </tr>
        </template>
        <tr x-show="tableData.length === 0">
            <td colspan="10" class="text-center py-12 text-slate-400">
                <i class="fa-solid fa-chart-simple text-4xl mb-3 block"></i>
                <p>Nenhum dado encontrado para os filtros selecionados</p>
            </td>
        </tr>
    </tbody>
</table>
</div>
</div>
</div>

<!-- MODAL FILTROS AVANÇADOS -->
<div x-show="modalFiltrosOpen" x-cloak @click.self="modalFiltrosOpen = false" 
class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
<div class="bg-white rounded-2xl w-full max-w-2xl shadow-2xl max-h-[90vh] overflow-hidden">
    <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
        <h3 class="text-white font-bold"><i class="fa-solid fa-filter mr-2"></i>Filtros Avançados</h3>
        <button @click="modalFiltrosOpen = false" class="text-white/70 hover:text-white">
            <i class="fa-solid fa-times text-xl"></i>
        </button>
    </div>
    <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Cliente</label>
                <select x-model="filters.cliente" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.cliente" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Região</label>
                <select x-model="filters.regiao" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todas</option>
                    <template x-for="opt in filterOptions.regiao" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">UF</label>
                <select x-model="filters.estado" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.estado" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Cidade</label>
                <select x-model="filters.cidade" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todas</option>
                    <template x-for="opt in filterOptions.cidade" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Grupo</label>
                <select x-model="filters.grupo" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.grupo" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Subgrupo</label>
                <select x-model="filters.subgrupo" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.subgrupo" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Tipo Produto</label>
                <select x-model="filters.tipo_produto" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.tipo_produto" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Marca</label>
                <select x-model="filters.marca" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todas</option>
                    <template x-for="opt in filterOptions.marca" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Produto</label>
                <select x-model="filters.produto" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.produto" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Tipo Pedido</label>
                <select x-model="filters.tipo_de_pedido" @change="aplicarFiltroAvancado()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                    <option value="">Todos</option>
                    <template x-for="opt in filterOptions.tipo_de_pedido" :key="opt.value">
                        <option :value="opt.value" x-text="opt.label"></option>
                    </template>
                </select>
            </div>
        </div>
        <div class="flex gap-3 mt-6 pt-4 border-t border-slate-100">
            <button @click="aplicarTodosFiltros(); modalFiltrosOpen = false" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700">
                Aplicar Filtros
            </button>
            <button @click="limparFiltrosAvancados()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300">
                Limpar todos
            </button>
        </div>
    </div>
</div>
</div>

<!-- MODAL MÉTRICAS -->
<div x-show="modalMetricasOpen" x-cloak @click.self="modalMetricasOpen = false" 
class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
<div class="bg-white rounded-2xl w-full max-w-md shadow-2xl">
    <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
        <h3 class="text-white font-bold"><i class="fa-solid fa-chart-simple mr-2"></i>Selecionar Métricas</h3>
        <button @click="modalMetricasOpen = false" class="text-white/70 hover:text-white">
            <i class="fa-solid fa-times text-xl"></i>
        </button>
    </div>
    <div class="p-6">
        <div class="space-y-3">
            <template x-for="(metric, key) in metricsDisponiveis" :key="key">
                <label class="flex items-center gap-3 p-3 hover:bg-slate-50 rounded-xl cursor-pointer">
                    <input type="checkbox" :value="key" x-model="metricsSelecionadas" class="w-5 h-5 text-emerald-600 rounded">
                    <span class="text-sm font-medium text-slate-700" x-text="metric.label"></span>
                </label>
            </template>
        </div>
        <div class="flex gap-3 mt-6">
            <button @click="modalMetricasOpen = false; carregarDados()" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700">
                Aplicar
            </button>
            <button @click="modalMetricasOpen = false" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300">
                Cancelar
            </button>
        </div>
    </div>
</div>
</div>
<!-- MODAL DETALHES (UNIVERSAL - AGRUPADO POR DOCUMENTO) -->
<div x-show="modalDetalhesOpen" x-cloak @click.self="modalDetalhesOpen = false" 
class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
<div class="bg-white rounded-2xl w-full max-w-5xl shadow-2xl max-h-[90vh] overflow-hidden">
    <div class="bg-gradient-to-r from-emerald-700 to-emerald-600 px-6 py-4 flex justify-between items-center">
        <h3 class="text-white font-bold">
            <i class="fa-solid fa-file-invoice mr-2"></i> 
            <span x-text="modalDetalhesTitulo"></span>
        </h3>
        <button @click="modalDetalhesOpen = false" class="text-white/70 hover:text-white">
            <i class="fa-solid fa-times text-xl"></i>
        </button>
    </div>
    <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
        <div class="mb-4 flex justify-between items-center flex-wrap gap-3">
            <div class="text-sm text-slate-500">
                <i class="fa-regular fa-calendar mr-1"></i> Período: 
                <span x-text="filters.data_inicio + ' até ' + filters.data_fim"></span>
            </div>
            <div class="text-sm text-slate-500">
                <i class="fa-solid fa-list mr-1"></i> Documentos: 
                <span x-text="documentosAgrupados.length"></span>
            </div>
        </div>
        
        <!-- Tabela de Documentos (AGRUPADO) -->
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase w-8"></th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Documento</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Valor</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Qtd</th>
                        <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Peso</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase w-10">Itens</th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(doc, docIdx) in documentosAgrupados" :key="doc.numero_nf">
                        <tr>
                            <td colspan="8" class="p-0 border-0">
                                <!-- Linha principal -->
                                <table class="w-full text-sm">
                                    <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer"
                                    @click="toggleDocumento(doc, docIdx)">
                                    <td class="px-4 py-3 text-center w-8">
                                        <i class="fa-solid text-xs text-slate-400 transition-transform" 
                                        :class="doc.expandido ? 'fa-chevron-down' : 'fa-chevron-right'"></i>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs font-bold text-emerald-600" x-text="doc.numero_nf || '---'"></td>
                                    <td class="px-4 py-3 text-slate-700 text-xs max-w-[180px] truncate" :title="doc.cliente" x-text="doc.cliente || '---'"></td>
                                    <td class="px-4 py-3 text-right text-emerald-600 font-bold text-xs whitespace-nowrap" x-text="formatMoney(doc.valor_bruto)"></td>
                                    <td class="px-4 py-3 text-right text-blue-600 text-xs" x-text="formatNumber(doc.quantidade)"></td>
                                    <td class="px-4 py-3 text-right text-indigo-600 text-xs" x-text="formatNumber(doc.peso)"></td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold" 
                                        :class="doc.tipo_documento === 'NF' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'"
                                        x-text="doc.tipo_documento || '---'"></span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded-full" 
                                        x-text="doc.qtd_itens || '-'"></span>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Linha expandida com itens -->
                            <div x-show="doc.expandido" class="bg-slate-50/50 px-4 py-3 border-b border-slate-100">
                                <div x-show="doc.carregando" class="text-center py-4 text-slate-400 text-xs">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando itens...
                                </div>
                                <div x-show="!doc.carregando && doc.itens && doc.itens.length > 0">
                                    <table class="w-full text-xs border border-slate-200 rounded-lg overflow-hidden">
                                        <thead class="bg-slate-100">
                                            <tr>
                                                <th class="px-3 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Produto</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Qtd</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Vl. Unit.</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Vl. Total</th>
                                                <th class="px-3 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Peso</th>
                                                <th class="px-3 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Grupo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="item in doc.itens" :key="item.produto">
                                                <tr class="border-b border-slate-100">
                                                    <td class="px-3 py-2 text-slate-600" x-text="item.produto"></td>
                                                    <td class="px-3 py-2 text-right text-blue-600" x-text="formatNumber(item.quantidade)"></td>
                                                    <td class="px-3 py-2 text-right text-slate-500" x-text="formatMoney(item.valor_unitario)"></td>
                                                    <td class="px-3 py-2 text-right text-emerald-600 font-bold" x-text="formatMoney(item.valor_total)"></td>
                                                    <td class="px-3 py-2 text-right text-indigo-500" x-text="formatNumber(item.peso)"></td>
                                                    <td class="px-3 py-2 text-slate-400" x-text="item.grupo || '---'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div x-show="!doc.carregando && (!doc.itens || doc.itens.length === 0)" class="text-center py-4 text-slate-400 text-xs">
                                    Nenhum item encontrado
                                </div>
                            </div>
                        </td>
                    </tr>
                </template>
            </tbody>
        </table>
    </div>
    
    <!-- Resumo dos Totais -->
    <div class="mt-4 p-4 bg-slate-50 rounded-xl">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Valor</span>
                <span class="text-lg font-black text-emerald-600" x-text="formatMoney(detalhesTotais.valor_bruto)">R$ 0</span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Quantidade</span>
                <span class="text-lg font-black text-blue-600" x-text="formatNumber(detalhesTotais.quantidade)">0</span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Peso</span>
                <span class="text-lg font-black text-indigo-600" x-text="formatNumber(detalhesTotais.peso)">0</span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Comissão</span>
                <span class="text-lg font-black text-amber-600" x-text="formatMoney(detalhesTotais.comissao || 0)">R$ 0</span>
            </div>
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block">Total Documentos</span>
                <span class="text-lg font-black text-slate-700" x-text="documentosAgrupados.length"></span>
            </div>
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

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>