<?php
$pageTitle = 'NUTRICIONAL | AUDITORIA LOGÍSTICA';
$moduleJs = 'auditoria.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/auditoria.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? 0 ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<!-- ====================================================================== -->
<!-- HEADER MOBILE FIXO (visível apenas em telas < 1024px) -->
<!-- ====================================================================== -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 bg-slate-800 text-white shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 text-white hover:text-emerald-400 transition-colors no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold text-emerald-400">AUDITORIA</span>
        </div>
        <div class="clock font-mono text-sm font-bold text-white" id="relogioMobile">00:00</div>
    </div>
</div>

<!-- Espaçador para compensar o header fixo no mobile -->
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="auditoria-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- Header Desktop -->
    <div class="glass rounded-3xl p-5 mb-4 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <!-- Botão Voltar (Desktop) -->
            <a href="/portal/" class="hidden sm:flex w-10 h-10 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            
            <div class="w-12 h-12 bg-slate-100 text-slate-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-clipboard-check text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">AUDITORIA LOGÍSTICA</h2>
                <span class="text-xs text-slate-400 font-medium">CENTRAL DE RASTREABILIDADE</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black text-slate-700" id="relogio">00:00:00</div>
            <div id="data-topo" class="text-[10px] lg:text-xs text-slate-400 font-bold uppercase tracking-wider">--/--/----</div>
        </div>
    </div>

    <!-- Filtro de Período -->
    <div class="bg-white p-4 lg:p-5 rounded-2xl shadow-sm border border-slate-100 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[10px] lg:text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    <i class="fa-solid fa-calendar mr-1"></i> Início
                </label>
                <input type="date" id="dataInicio" class="w-full p-2.5 lg:p-3 border border-slate-200 rounded-xl text-sm" 
                       value="<?= date('Y-m-d', strtotime('-7 days')) ?>">
            </div>
            <div class="flex-1 min-w-[140px]">
                <label class="block text-[10px] lg:text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
                    <i class="fa-solid fa-calendar mr-1"></i> Fim
                </label>
                <input type="date" id="dataFim" class="w-full p-2.5 lg:p-3 border border-slate-200 rounded-xl text-sm" 
                       value="<?= date('Y-m-d') ?>">
            </div>
            <button onclick="window.filtrarDados()" 
                    class="px-4 lg:px-6 py-2.5 lg:py-3 bg-slate-700 text-white rounded-xl font-bold hover:bg-slate-800 transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-magnifying-glass"></i> <span class="hidden sm:inline">FILTRAR</span>
            </button>
            <button onclick="window.exportarRelatorio()" 
                    class="px-4 lg:px-6 py-2.5 lg:py-3 bg-white border-2 border-slate-200 text-slate-700 rounded-xl font-bold hover:border-amber-400 hover:bg-amber-50 transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-download"></i> <span class="hidden sm:inline">EXPORTAR</span>
            </button>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 lg:gap-3 mb-4">
        <div class="bg-white p-3 lg:p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-2 lg:gap-3">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-boxes-stacked text-sm lg:text-lg"></i>
            </div>
            <div>
                <span class="block text-xl lg:text-2xl font-black text-slate-800" id="totalEmbarques">--</span>
                <span class="text-[9px] lg:text-[10px] uppercase font-bold text-slate-400">Embarques</span>
            </div>
        </div>
        <div class="bg-white p-3 lg:p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-2 lg:gap-3">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-circle-check text-sm lg:text-lg"></i>
            </div>
            <div>
                <span class="block text-xl lg:text-2xl font-black text-slate-800" id="embarquesFinalizados">--</span>
                <span class="text-[9px] lg:text-[10px] uppercase font-bold text-slate-400">Finalizados</span>
            </div>
        </div>
        <div class="bg-white p-3 lg:p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-2 lg:gap-3">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-spinner text-sm lg:text-lg"></i>
            </div>
            <div>
                <span class="block text-xl lg:text-2xl font-black text-slate-800" id="embarquesAndamento">--</span>
                <span class="text-[9px] lg:text-[10px] uppercase font-bold text-slate-400">Andamento</span>
            </div>
        </div>
        <div class="bg-white p-3 lg:p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-2 lg:gap-3">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-barcode text-sm lg:text-lg"></i>
            </div>
            <div>
                <span class="block text-xl lg:text-2xl font-black text-slate-800" id="totalBips">--</span>
                <span class="text-[9px] lg:text-[10px] uppercase font-bold text-slate-400">Bips</span>
            </div>
        </div>
        <div class="bg-white p-3 lg:p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center gap-2 lg:gap-3 col-span-2 sm:col-span-1">
            <div class="w-10 h-10 lg:w-12 lg:h-12 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fa-solid fa-weight-scale text-sm lg:text-lg"></i>
            </div>
            <div>
                <span class="block text-xl lg:text-2xl font-black text-slate-800" id="totalPeso">--</span>
                <span class="text-[9px] lg:text-[10px] uppercase font-bold text-slate-400">Peso (kg)</span>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="mb-3 border-b border-slate-200 overflow-x-auto scrollbar-hide">
        <div class="flex gap-1 min-w-max">
            <button class="tab-btn active px-4 lg:px-6 py-2.5 lg:py-3 rounded-t-xl font-bold text-xs lg:text-sm transition-all whitespace-nowrap" 
                    id="btnTabSeparacao" onclick="window.switchTab('separacao')">
                <i class="fa-solid fa-box mr-1.5"></i> SEPARAÇÃO 
                <span class="tab-count ml-1.5 px-2 py-0.5 bg-slate-200 rounded-full text-[10px]" id="countSeparacao">(0)</span>
            </button>
            <button class="tab-btn px-4 lg:px-6 py-2.5 lg:py-3 rounded-t-xl font-bold text-xs lg:text-sm transition-all text-slate-500 whitespace-nowrap" 
                    id="btnTabCarregamento" onclick="window.switchTab('carregamento')">
                <i class="fa-solid fa-truck mr-1.5"></i> CARREGAMENTO 
                <span class="tab-count ml-1.5 px-2 py-0.5 bg-slate-200 rounded-full text-[10px]" id="countCarregamento">(0)</span>
            </button>
            <button class="tab-btn px-4 lg:px-6 py-2.5 lg:py-3 rounded-t-xl font-bold text-xs lg:text-sm transition-all text-slate-500 whitespace-nowrap" 
                    id="btnTabRanking" onclick="window.switchTab('ranking')">
                <i class="fa-solid fa-trophy mr-1.5"></i> RANKING
            </button>
        </div>
    </div>

    <!-- Painel Separação -->
    <div class="tab-panel active" id="panelSeparacao">
        <div class="flex flex-col sm:flex-row gap-2 lg:gap-3 mb-3">
            <div class="flex-1 relative">
                <i class="fa-solid fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="searchSeparacao" placeholder="Buscar..." 
                       onkeyup="window.filtrarTabela('separacao')"
                       class="w-full pl-10 pr-4 py-2.5 lg:py-3 border border-slate-200 rounded-xl text-sm">
            </div>
            <select id="statusFilterSeparacao" onchange="window.filtrarTabela('separacao')" 
                    class="px-4 py-2.5 lg:py-3 border border-slate-200 rounded-xl text-sm">
                <option value="">Todos Status</option>
                <option value="PENDENTE">Pendente</option>
                <option value="SEPARACAO">Em Separação</option>
                <option value="CONCLUIDO">Concluído</option>
                <option value="CARREGADO">Carregado</option>
            </select>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full audit-table table-to-cards">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Emb.</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Rota</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Operador</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Início/Fim</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Bips</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Status</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-center text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tbodySeparacao">
                        <tr><td colspan="7" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Painel Carregamento -->
    <div class="tab-panel" id="panelCarregamento" style="display:none;">
        <div class="flex gap-2 lg:gap-3 mb-3">
            <div class="flex-1 relative">
                <i class="fa-solid fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" id="searchCarregamento" placeholder="Buscar por embarque, placa ou motorista..." 
                       onkeyup="window.filtrarTabela('carregamento')"
                       class="w-full pl-10 pr-4 py-2.5 lg:py-3 border border-slate-200 rounded-xl text-sm">
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full audit-table table-to-cards">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Emb.</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Rota</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Placa</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Motorista</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Peso</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Status</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-center text-[10px] lg:text-xs font-bold text-slate-400 uppercase whitespace-nowrap">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyCarregamento">
                        <tr><td colspan="7" class="text-center py-8 text-slate-400">Clique na aba Carregamento</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Painel Ranking -->
    <div class="tab-panel" id="panelRanking" style="display:none;">
        <div class="mb-3">
            <h3 class="text-base lg:text-lg font-bold text-slate-800">
                <i class="fa-solid fa-trophy text-amber-400 mr-2"></i> Top Operadores
            </h3>
            <p class="text-xs lg:text-sm text-slate-400">Baseado no período selecionado</p>
        </div>
        <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
               <table class="w-full ranking-table table-to-cards">
                    <thead class="bg-gradient-to-r from-slate-700 to-slate-800">
                        <tr>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-white uppercase">#</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-white uppercase">Operador</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-white uppercase">Emb.</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-white uppercase">Bips</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-white uppercase">Total</th>
                            <th class="px-3 lg:px-4 py-2.5 lg:py-3 text-left text-[10px] lg:text-xs font-bold text-white uppercase">Última Ativ.</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyRanking">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Footer Conquistas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 mt-4">
        <div class="bg-white p-3 lg:p-4 rounded-2xl border-l-4 lg:border-l-8 border-amber-400 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <i class="fa-solid fa-clipboard-list text-amber-500 text-sm"></i>
                <span class="text-[10px] lg:text-xs font-bold text-slate-500 uppercase">ÚLTIMAS - SEPARAÇÃO</span>
            </div>
            <div id="lista-auditoria-sep" class="flex flex-wrap gap-1.5">
                <span class="text-xs text-slate-400">Aguardando...</span>
            </div>
        </div>
        <div class="bg-white p-3 lg:p-4 rounded-2xl border-l-4 lg:border-l-8 border-emerald-500 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <i class="fa-solid fa-truck-fast text-emerald-500 text-sm"></i>
                <span class="text-[10px] lg:text-xs font-bold text-slate-500 uppercase">ÚLTIMAS - CARREGAMENTO</span>
            </div>
            <div id="lista-auditoria-car" class="flex flex-wrap gap-1.5">
                <span class="text-xs text-slate-400">Aguardando...</span>
            </div>
        </div>
    </div>

    <!-- Modal Timeline -->
    <div id="modalTimeline" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-end sm:items-center justify-center min-h-screen p-2 sm:p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="window.fecharModalTimeline()"></div>
            <div class="relative bg-white rounded-2xl sm:rounded-3xl w-full max-w-5xl max-h-[95vh] sm:max-h-[90vh] overflow-hidden shadow-2xl">
                <div class="bg-slate-700 px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between">
                    <h3 class="text-base sm:text-lg font-bold text-white">
                        <i class="fa-solid fa-clock-rotate-left mr-2"></i>
                        Timeline - Emb. <span id="modalEmbarqueId">#0</span>
                    </h3>
                    <button onclick="window.fecharModalTimeline()" class="text-white/70 hover:text-white transition-colors p-1">
                        <i class="fa-solid fa-times text-lg sm:text-xl"></i>
                    </button>
                </div>
                <div class="p-3 sm:p-6 overflow-y-auto max-h-[calc(95vh-60px)] sm:max-h-[calc(90vh-80px)]" id="timelineContent"></div>
            </div>
        </div>
    </div>

    <!-- Modal Foto Zoom -->
    <div id="modalFoto" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-2 sm:p-4">
            <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" onclick="window.fecharModalFoto()"></div>
            <div class="relative bg-transparent w-full max-w-4xl">
                <div class="bg-slate-800/90 rounded-t-2xl px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between">
                    <h3 class="text-base sm:text-lg font-bold text-white">
                        <i class="fa-solid fa-image mr-2"></i>
                        <span id="fotoZoomTitulo">Produto</span>
                    </h3>
                    <button onclick="window.fecharModalFoto()" class="text-white/70 hover:text-white transition-colors p-1">
                        <i class="fa-solid fa-times text-lg sm:text-xl"></i>
                    </button>
                </div>
                <div class="bg-black/90 rounded-b-2xl p-4 sm:p-8 text-center">
                    <img id="fotoZoomImagem" src="" alt="Produto" class="max-w-full max-h-[70vh] rounded-xl mx-auto">
                </div>
            </div>
        </div>
    </div>
</div>


<script>
function auditoriaHandler() { return { init() {} } }
</script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>