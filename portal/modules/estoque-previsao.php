<?php
$pageTitle = 'Estoque com Previsão | Nutricional';
$version = time();
$moduleJs = 'estoque-previsao.js';
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
    .ep-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
    .ep-hero { background: linear-gradient(135deg, #065f46 0%, #047857 50%, #059669 100%); }
    .ep-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
    .ep-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
    .ep-input { width: 100%; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.65rem 0.95rem; font-size: 0.9rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
    .ep-input:focus { outline: none; border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12); }
    .ep-kpi { border-radius: 1.25rem; padding: 1.15rem; transition: transform 0.2s; }
    .ep-kpi:hover { transform: translateY(-2px); }
    .ep-chip { font-size: 0.72rem; font-weight: 800; padding: 0.35rem 0.65rem; border-radius: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
    .table-row-hover { transition: background-color 0.15s ease; cursor: pointer; }
    .table-row-hover:hover { background-color: #f0fdf4 !important; }
    .pulse-dot { width: 8px; height: 8px; border-radius: 999px; background: #10b981; animation: pulseDot 2s infinite; }
    @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.2); } }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="ep-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-8">

    <!-- HERO HEADER -->
    <div class="ep-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="ep-chip bg-white/20 text-white backdrop-blur-sm"><i class="fa-solid fa-boxes-stacked"></i> Estoque & Previsão</span>
                        <span id="badgeUnificado" class="ep-chip bg-emerald-400/25 text-emerald-100 border border-emerald-300/30">
                            <span class="pulse-dot"></span> Visão Unificada Matriz + PR
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1">Estoque com Previsão de Chegada</h1>
                    <p class="text-xs sm:text-sm text-emerald-100 opacity-90 mt-0.5">Controle prévio integrado de estoque livre, carteira reservada e compras a caminho</p>
                </div>
            </div>

            <!-- FILIAL & ATALHOS -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="bg-white/15 backdrop-blur-md rounded-2xl p-2 px-3.5 border border-white/20 flex items-center gap-2">
                    <i class="fa-solid fa-building text-emerald-200 text-sm"></i>
                    <div>
                        <div class="text-[0.65rem] uppercase tracking-wider text-emerald-200 font-bold">Unidade de Estoque</div>
                        <select id="select-filial" class="bg-transparent text-white font-extrabold text-sm focus:outline-none cursor-pointer pr-2">
                            <option value="0" class="text-slate-800 font-bold">Unificado (Matriz 1 + Filial PR 6)</option>
                            <option value="1" class="text-slate-800 font-bold">Matriz 1 (Santa Catarina)</option>
                            <option value="6" class="text-slate-800 font-bold">Filial 6 (Paraná)</option>
                        </select>
                    </div>
                </div>

                <button id="btn-exportar" class="bg-white/20 hover:bg-white/30 text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm" onclick="estoquePrevisaoApp.exportarCsv()">
                    <i class="fa-solid fa-file-excel"></i> Exportar CSV
                </button>

                <div class="text-right hidden sm:block pl-2 border-l border-white/20">
                    <div class="font-mono text-base font-black text-white" id="relogio">00:00:00</div>
                    <div class="text-[10px] text-emerald-200 font-bold" id="user-display">USUÁRIO</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 6 KPI CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4 mb-5">
        <!-- KPI 1: Estoque Físico -->
        <div class="ep-kpi bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 shadow-sm">
            <div class="flex items-center justify-between text-slate-500 mb-1">
                <span class="ep-label text-slate-600">Estoque Físico</span>
                <i class="fa-solid fa-cubes text-xs"></i>
            </div>
            <div id="total-estoque" class="text-lg sm:text-2xl font-black text-slate-800">0</div>
            <div id="kpiDetalheEstoque" class="text-[0.65rem] text-slate-500 font-bold mt-1 truncate">M: 0 · PR: 0</div>
        </div>

        <!-- KPI 2: Carteira Reservada -->
        <div class="ep-kpi bg-gradient-to-br from-amber-50 to-yellow-50/70 border border-amber-200 shadow-sm">
            <div class="flex items-center justify-between text-amber-600 mb-1">
                <span class="ep-label text-amber-700">Reservado (Pedidos)</span>
                <i class="fa-solid fa-cart-shopping text-xs"></i>
            </div>
            <div id="total-reservado" class="text-lg sm:text-2xl font-black text-amber-600">0</div>
            <div id="kpiDetalheReservado" class="text-[0.65rem] text-amber-700/80 font-bold mt-1 truncate">Status 1 e 4</div>
        </div>

        <!-- KPI 3: Estoque Livre -->
        <div class="ep-kpi bg-gradient-to-br from-emerald-50 to-teal-50/70 border border-emerald-200 shadow-sm">
            <div class="flex items-center justify-between text-emerald-600 mb-1">
                <span class="ep-label text-emerald-700">Livre Disponível</span>
                <i class="fa-solid fa-circle-check text-xs"></i>
            </div>
            <div id="total-livre" class="text-lg sm:text-2xl font-black text-emerald-700">0</div>
            <div class="text-[0.65rem] text-emerald-700/80 font-bold mt-1 truncate">Físico - Reservado</div>
        </div>

        <!-- KPI 4: Previsão Chegada (OCs) -->
        <div class="ep-kpi bg-gradient-to-br from-blue-50 to-sky-50/70 border border-blue-200 shadow-sm">
            <div class="flex items-center justify-between text-blue-600 mb-1">
                <span class="ep-label text-blue-700">Previsão Compras</span>
                <i class="fa-solid fa-truck-fast text-xs"></i>
            </div>
            <div id="total-previsao" class="text-lg sm:text-2xl font-black text-blue-700">0</div>
            <div id="kpiDetalhePrevisao" class="text-[0.65rem] text-blue-700/80 font-bold mt-1 truncate">0 OCs a caminho</div>
        </div>

        <!-- KPI 5: Estoque Futuro -->
        <div class="ep-kpi bg-gradient-to-br from-purple-50 to-indigo-50/70 border border-purple-200 shadow-sm">
            <div class="flex items-center justify-between text-purple-600 mb-1">
                <span class="ep-label text-purple-700">Estoque Futuro</span>
                <i class="fa-solid fa-chart-line text-xs"></i>
            </div>
            <div id="total-futuro" class="text-lg sm:text-2xl font-black text-purple-700">0</div>
            <div class="text-[0.65rem] text-purple-700/80 font-bold mt-1 truncate">Livre + Previsão</div>
        </div>

        <!-- KPI 6: Zerados / Críticos -->
        <div class="ep-kpi bg-gradient-to-br from-rose-50 to-red-50/70 border border-rose-200 shadow-sm">
            <div class="flex items-center justify-between text-rose-600 mb-1">
                <span class="ep-label text-rose-700">Críticos / Zerados</span>
                <i class="fa-solid fa-triangle-exclamation text-xs"></i>
            </div>
            <div id="total-zerados" class="text-lg sm:text-2xl font-black text-rose-600">0</div>
            <div id="kpiDetalheCriticos" class="text-[0.65rem] text-rose-700/80 font-bold mt-1 truncate">0 críticos (≤10)</div>
        </div>
    </div>

    <!-- FILTROS DE BUSCA E CONTROLES -->
    <div class="ep-card p-4 sm:p-5 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
            <!-- Busca Textual -->
            <div class="lg:col-span-4">
                <span class="ep-label">Buscar Produto</span>
                <div class="relative mt-1">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input id="search-produto" type="text" placeholder="Nome, descrição ou referência..." class="ep-input pl-9 pr-8 text-xs sm:text-sm">
                    <button id="btn-limpar-busca" class="absolute right-3 top-2.5 text-slate-300 hover:text-slate-500 text-xs hidden" onclick="estoquePrevisaoApp.limparBusca()">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Marca -->
            <div class="lg:col-span-3">
                <span class="ep-label">Marca</span>
                <select id="filter-marca" class="ep-input text-xs sm:text-sm mt-1">
                    <option value="">Todas as marcas</option>
                </select>
            </div>

            <!-- Status do Estoque -->
            <div class="lg:col-span-3">
                <span class="ep-label">Situação do Estoque</span>
                <select id="filter-status" class="ep-input text-xs sm:text-sm mt-1">
                    <option value="">Todos os status</option>
                    <option value="previsao">🚚 Com Previsão de Chegada (Compras a Caminho)</option>
                    <option value="critico">⚠️ Crítico (≤ 10 un.)</option>
                    <option value="baixo">📉 Baixo estoque (11 a 50 un.)</option>
                    <option value="zerado">❌ Zerado (≤ 0 un.)</option>
                    <option value="ok">✅ Adequado (> 50 un.)</option>
                </select>
            </div>

            <!-- Ações -->
            <div class="lg:col-span-2 flex items-center gap-2">
                <button id="btn-recarregar" class="py-2.5 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex-1 flex items-center justify-center gap-1.5" onclick="estoquePrevisaoApp.carregarTudo()">
                    <i class="fa-solid fa-rotate"></i> Atualizar
                </button>
                <button id="btn-limpar" class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-500 font-bold text-xs transition" title="Limpar todos os filtros" onclick="estoquePrevisaoApp.reset()">
                    <i class="fa-solid fa-eraser"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- TABELA DE PRODUTOS -->
    <div class="ep-card overflow-hidden mb-5">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-boxes-packing text-emerald-600"></i> Produtos & Previsão de Entrega
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Clique em qualquer produto para abrir o raio-x com ordens de compra e histórico</p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-400">Itens por pág:</span>
                <select id="select-limit" class="py-1 px-2 border rounded-lg text-xs font-bold text-slate-700" onchange="estoquePrevisaoApp.mudarLimite(this.value)">
                    <option value="20">20</option>
                    <option value="50" selected>50</option>
                    <option value="100">100</option>
                </select>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[0.7rem] uppercase tracking-wider text-slate-400 font-extrabold select-none">
                        <th class="py-3 px-4 cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('produto')">
                            Produto & Referência <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-3 cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('marca')">
                            Marca <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-2 text-right cursor-pointer hover:text-emerald-700 transition" title="Estoque físico Matriz (SC)">
                            Matriz 1
                        </th>
                        <th class="py-3 px-2 text-right cursor-pointer hover:text-emerald-700 transition" title="Estoque físico Filial (PR)">
                            Filial PR
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('estoque')">
                            Total Físico <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('reservado')">
                            Reservado <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('liquido')">
                            Livre <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('previsao')">
                            A Chegar (OCs) <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('futuro')">
                            Futuro <i class="fa-solid fa-sort ml-1"></i>
                        </th>
                        <th class="py-3 px-3 text-center">Status</th>
                    </tr>
                </thead>
                <tbody id="table-body" class="divide-y divide-slate-100">
                    <tr>
                        <td colspan="10" class="py-12 text-center text-slate-400">
                            <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-600 block"></i>
                            Carregando estoque com previsão...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINAÇÃO -->
        <div class="p-4 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
            <div id="pagination-info" class="font-bold">Mostrando 0 de 0 produtos</div>
            <div id="pagination-buttons" class="flex items-center gap-1.5"></div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
