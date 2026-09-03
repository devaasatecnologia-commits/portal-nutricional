<?php
$pageTitle = 'Estoque com Previsão | Nutricional';
$version = time();
$moduleJs = 'estoque-previsao.js';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<style>
    .stat-card { transition: all 0.3s ease; }
    .stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .btn-exportar { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .btn-exportar:hover { background: linear-gradient(135deg, #059669 0%, #047857 100%); }
    @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    .animate-shimmer { animation: shimmer 2s infinite linear; }
    .cursor-pointer { cursor: pointer; }
    tr { cursor: pointer; }
    .table-row-hover:hover { background-color: #f8fafc; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <div id="shimmer" class="fixed top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 via-emerald-700 to-amber-400 bg-[length:200%_100%] animate-shimmer z-50 hidden"></div>
    
    <!-- Header -->
    <div class="rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">ESTOQUE COM PREVISÃO</h2>
                <span id="user-display" class="text-xs text-slate-400 font-medium">CARREGANDO...</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button id="btn-exportar" class="btn-exportar px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-medium hover:bg-emerald-700 transition-all flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-download"></i> Exportar CSV
            </button>
            <div class="text-right hidden sm:block">
                <div class="clock font-mono text-base lg:text-xl font-black text-slate-700" id="relogio">00:00:00</div>
                <div class="data-topo text-[10px] lg:text-xs text-slate-400" id="data-topo">--/--/----</div>
            </div>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-building mr-1"></i> Unidade
                </label>
                <select id="select-filial" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white focus:border-emerald-400 focus:outline-none">
                    <option value="">Carregando...</option>
                </select>
            </div>
            
            <button onclick="estoquePrevisaoApp.reset()" 
                    class="px-6 py-3 bg-slate-700 text-white rounded-xl font-bold hover:bg-slate-800 transition-all flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-rotate-left"></i> REINICIAR
            </button>
        </div>
    </div>
    
    <!-- Cards Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
        <div class="stat-card bg-white p-4 rounded-2xl border-l-4 border-emerald-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Estoque Disponível</span>
            <span class="block text-2xl font-black text-slate-800" id="total-estoque">0</span>
        </div>
        <div class="stat-card bg-white p-4 rounded-2xl border-l-4 border-amber-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Reservado (Carteira)</span>
            <span class="block text-2xl font-black text-amber-600" id="total-reservado">0</span>
        </div>
        <div class="stat-card bg-white p-4 rounded-2xl border-l-4 border-blue-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Previsão de Chegada</span>
            <span class="block text-2xl font-black text-blue-600" id="total-previsao">0</span>
        </div>
        <div class="stat-card bg-white p-4 rounded-2xl border-l-4 border-purple-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Estoque Futuro</span>
            <span class="block text-2xl font-black text-purple-600" id="total-futuro">0</span>
        </div>
        <div class="stat-card bg-white p-4 rounded-2xl border-l-4 border-rose-500 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Produtos Zerados</span>
            <span class="block text-2xl font-black text-rose-600" id="total-zerados">0</span>
        </div>
    </div>
    
    <!-- Gráfico de Resumo -->
    <div class="bg-white rounded-2xl p-5 mb-6 shadow-sm border border-slate-100">
        <div class="flex justify-between items-center mb-4">
            <h4 class="text-sm font-bold text-slate-700 uppercase">
                <i class="fa-solid fa-chart-pie mr-2 text-emerald-600"></i> DISTRIBUIÇÃO DO ESTOQUE
            </h4>
        </div>
        <div style="height: 250px;">
            <canvas id="chartResumo"></canvas>
        </div>
    </div>
    
    <!-- Filtros de Busca -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="relative">
                <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="search-produto" placeholder="Buscar produto ou referência..." 
                       class="w-full pl-9 pr-3 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 transition-all outline-none">
            </div>
            <select id="filter-marca" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none bg-white">
                <option value="">Todas as marcas</option>
            </select>
            <select id="filter-status" class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400 outline-none bg-white">
                <option value="">Todos os status</option>
                <option value="critico">⚠️ Crítico (≤10 unidades)</option>
                <option value="baixo">📉 Baixo estoque (11-50)</option>
                <option value="zerado">❌ Zerado</option>
                <option value="ok">✅ Ok (>50)</option>
            </select>
            <button id="btn-limpar" class="px-4 py-2.5 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-50 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-eraser"></i> Limpar filtros
            </button>
        </div>
    </div>
    
    <!-- Tabela de Produtos -->
    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="produto">
                            Produto <i class="fa-solid fa-arrow-down-wide-short text-[10px] ml-1 text-slate-400"></i>
                        </th>
                        <th class="text-left px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="marca">
                            Marca
                        </th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="estoque">
                            Disponível
                        </th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="reservado">
                            Reservado
                        </th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="liquido">
                            Líquido
                        </th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="previsao">
                            Previsão
                        </th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-600 cursor-pointer hover:bg-slate-100 transition-colors" data-order="futuro">
                            Futuro
                        </th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-600">Status</th>
                    </tr>
                </thead>
                <tbody id="table-body">
                    <tr>
                        <td colspan="8" class="text-center py-12">
                            <i class="fa-solid fa-spinner-third fa-spin text-2xl text-slate-400 mb-2 block"></i>
                            <p class="text-slate-400">Carregando produtos...</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row justify-between items-center gap-3">
            <div class="text-sm text-slate-500" id="pagination-info"></div>
            <div class="flex gap-2" id="pagination-buttons"></div>
        </div>
    </div>
</div>



<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>