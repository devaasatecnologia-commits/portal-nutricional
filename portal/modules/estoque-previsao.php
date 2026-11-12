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
    .ep-label { font-size: 0.65rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: #64748b; }
    .ep-input { width: 100%; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.65rem 0.95rem; font-size: 0.9rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
    .ep-input:focus { outline: none; border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12); }
    .ep-kpi { border-radius: 1.25rem; padding: 0.85rem; transition: transform 0.2s; min-width: 0; overflow: hidden; }
    @media (min-width: 640px) {
        .ep-kpi { padding: 1.15rem; }
        .ep-label { font-size: 0.72rem; letter-spacing: 0.05em; }
    }
    .ep-kpi:hover { transform: translateY(-2px); }
    .ep-chip { font-size: 0.68rem; font-weight: 800; padding: 0.3rem 0.6rem; border-radius: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
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
    <div class="ep-hero rounded-3xl p-4 sm:p-7 text-white shadow-xl mb-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <a href="<?= $appBase ?>/portal/" class="w-10 h-10 sm:w-11 sm:h-11 flex-none rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" aria-label="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        <span class="ep-chip bg-white/20 text-white backdrop-blur-sm"><i class="fa-solid fa-boxes-stacked"></i> Estoque & Previsão</span>
                        <span id="badgeFilial" class="ep-chip bg-emerald-400/25 text-emerald-100 border border-emerald-300/30">
                            <span class="pulse-dot"></span> Matriz + Filial PR (1 + 6)
                        </span>
                    </div>
                    <h1 class="text-lg sm:text-2xl font-black tracking-tight mt-1 leading-tight">Estoque com Previsão de Chegada</h1>
                    <p class="text-xs sm:text-sm text-emerald-100 opacity-90 mt-0.5">Controle prévio integrado de estoque livre, carteira reservada e compras a caminho</p>
                </div>
            </div>

            <!-- ATALHOS & EXPORTAR -->
            <div class="flex flex-wrap items-center gap-2 sm:gap-2.5">
                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=estoque-previsao" 
                   class="flex-1 sm:flex-none justify-center bg-amber-400 hover:bg-amber-300 text-slate-900 font-extrabold text-xs sm:text-sm py-2.5 px-3.5 sm:px-4 rounded-2xl transition flex items-center gap-2 shadow-sm no-underline whitespace-nowrap"
                   title="Treinamento e Manual Operacional do Módulo">
                    <i class="fa-solid fa-graduation-cap"></i> Treinamento & Manual
                </a>
                <button id="btn-exportar" class="flex-1 sm:flex-none justify-center bg-white/20 hover:bg-white/30 text-white font-bold text-xs sm:text-sm py-2.5 px-3.5 sm:px-4 rounded-2xl transition flex items-center gap-2 shadow-sm whitespace-nowrap" onclick="estoquePrevisaoApp.exportarCsv()">
                    <i class="fa-solid fa-file-excel"></i> Exportar CSV
                </button>

                <div class="text-right hidden sm:block pl-2 border-l border-white/20">
                    <div class="font-mono text-base font-black text-white" id="relogio">00:00:00</div>
                    <div class="text-[10px] text-emerald-200 font-bold" id="user-display">USUÁRIO</div>
                </div>
            </div>
        </div>
    </div>

    <!-- BARRA DE SELEÇÃO DE MÉTRICA (PESO VS UNIDADES) -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-3 px-1">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-extrabold text-slate-600 uppercase tracking-wider flex items-center gap-1.5" data-tooltip="Métrica dos Cards|Alterne a unidade de medida exibida exclusivamente nos 6 cards do topo.">
                <i class="fa-solid fa-sliders text-emerald-600"></i> Exibir Cards por:
            </span>
            <div class="inline-flex p-1 bg-slate-200/80 rounded-xl text-xs font-bold shadow-inner">
                <button type="button" id="btn-modo-peso" class="py-1.5 px-2.5 sm:px-3 rounded-lg bg-emerald-600 text-white shadow-sm transition flex items-center gap-1.5 whitespace-nowrap" onclick="estoquePrevisaoApp.setModoExibicao('peso')" data-tooltip="Cards por Peso|Exibe os 6 cards do topo em Toneladas (t) ou Quilos (kg).">
                    <i class="fa-solid fa-weight-hanging text-[10px]"></i> Peso (KG / Ton)
                </button>
                <button type="button" id="btn-modo-unidade" class="py-1.5 px-2.5 sm:px-3 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1.5 whitespace-nowrap" onclick="estoquePrevisaoApp.setModoExibicao('unidade')" data-tooltip="Cards por Unidades|Exibe os 6 cards do topo em quantidade de embalagens/peças.">
                    <i class="fa-solid fa-boxes-stacked text-[10px]"></i> Unidades (Peças)
                </button>
            </div>
        </div>
        <div class="text-[11px] text-slate-500 font-medium flex items-center gap-1.5">
            <i class="fa-solid fa-circle-info text-emerald-600 flex-none"></i> <span>Cards alternam entre peso e peças · Lista de produtos sempre exibida por unidades</span>
        </div>
    </div>

    <!-- 6 KPI CARDS (INTERATIVOS & RASTREÁVEIS) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2.5 sm:gap-4 mb-5 select-none">
        <!-- KPI 1: Estoque Físico -->
        <div id="kpi-card-fisico" class="ep-kpi bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 shadow-sm relative group cursor-pointer hover:shadow-md transition-all active:scale-[0.98]" 
             title="ESTOQUE FÍSICO TOTAL&#10;• Conceito: Saldo físico real estocado nos armazéns da Matriz SC (Filial 1) e Filial PR (Filial 6).&#10;• Clique para filtrar apenas os produtos com estoque físico em armazém."
             data-tooltip="Estoque Físico Total|Soma do saldo físico atual em armazém (Matriz SC + Filial PR). Clique para filtrar produtos com saldo físico."
             onclick="estoquePrevisaoApp.clicarCardKpi('fisico')">
            <div class="flex items-center justify-between gap-1 text-slate-500 mb-1 min-w-0">
                <span class="ep-label text-slate-600 truncate">Estoque Físico</span>
                <i class="fa-solid fa-cubes text-xs text-slate-400 group-hover:text-slate-700 transition flex-none"></i>
            </div>
            <div id="total-estoque" class="text-base sm:text-2xl font-black text-slate-800 tracking-tight truncate">0</div>
            <div id="kpiDetalheEstoque" class="text-[0.65rem] text-slate-500 font-bold mt-1 truncate">M: 0 · PR: 0</div>
        </div>

        <!-- KPI 2: Carteira Reservada -->
        <div id="kpi-card-reservado" class="ep-kpi bg-gradient-to-br from-amber-50 to-yellow-50/70 border border-amber-200 shadow-sm relative group cursor-pointer hover:shadow-md transition-all active:scale-[0.98]" 
             title="CARTEIRA RESERVADA (PEDIDOS)&#10;• Conceito: Total de mercadorias empenhadas em pedidos de venda em aberto ou em faturamento parcial (Status 1 e 4).&#10;• Impacto: Quantidade bloqueada e não disponível para novas vendas.&#10;• Clique para filtrar produtos com reserva em pedidos."
             data-tooltip="Carteira Reservada|Quantidade empenhada em pedidos de venda abertos ou aprovados (Status 1 e 4). Clique para filtrar itens reservados."
             onclick="estoquePrevisaoApp.clicarCardKpi('reservado')">
            <div class="flex items-center justify-between gap-1 text-amber-600 mb-1 min-w-0">
                <span class="ep-label text-amber-700 truncate">Reservado (Ped.)</span>
                <i class="fa-solid fa-cart-shopping text-xs text-amber-500 group-hover:text-amber-700 transition flex-none"></i>
            </div>
            <div id="total-reservado" class="text-base sm:text-2xl font-black text-amber-600 tracking-tight truncate">0</div>
            <div id="kpiDetalheReservado" class="text-[0.65rem] text-amber-700/80 font-bold mt-1 truncate">Status 1 e 4</div>
        </div>

        <!-- KPI 3: Estoque Livre -->
        <div id="kpi-card-livre" class="ep-kpi bg-gradient-to-br from-emerald-50 to-teal-50/70 border border-emerald-200 shadow-sm relative group cursor-pointer hover:shadow-md transition-all active:scale-[0.98]" 
             title="LIVRE DISPONÍVEL (IMEDIATO)&#10;• Fórmula: [Estoque Físico] - [Carteira Reservada].&#10;• Conceito: Saldo líquido 100% liberado para venda imediata sem risco de ruptura.&#10;• Clique para filtrar itens com estoque livre positivo."
             data-tooltip="Livre Disponível|Estoque real imediatamente liberado para novas vendas (Físico menos Reservado). Clique para filtrar itens disponíveis."
             onclick="estoquePrevisaoApp.clicarCardKpi('livre')">
            <div class="flex items-center justify-between gap-1 text-emerald-600 mb-1 min-w-0">
                <span class="ep-label text-emerald-700 truncate">Livre Disponível</span>
                <i class="fa-solid fa-circle-check text-xs text-emerald-500 group-hover:text-emerald-700 transition flex-none"></i>
            </div>
            <div id="total-livre" class="text-base sm:text-2xl font-black text-emerald-700 tracking-tight truncate">0</div>
            <div id="kpiDetalheLivre" class="text-[0.65rem] text-emerald-700/80 font-bold mt-1 truncate">Físico - Reservado</div>
        </div>

        <!-- KPI 4: Previsão Chegada (OCs) -->
        <div id="kpi-card-previsao" class="ep-kpi bg-gradient-to-br from-blue-50 to-sky-50/70 border border-blue-200 shadow-sm relative group cursor-pointer hover:shadow-md transition-all active:scale-[0.98]" 
             title="PREVISÃO DE COMPRAS (OCS EM TRÂNSITO)&#10;• Conceito: Volume de mercadorias com Ordens de Compra autorizadas e a caminho dos fornecedores.&#10;• Detalhe: Clique para ver os itens com entregas previstas no período."
             data-tooltip="Previsão de Compras (OCs)|Cargas compradas com fornecedores em trânsito com ordens de compra abertas. Clique para filtrar itens a caminho."
             onclick="estoquePrevisaoApp.clicarCardKpi('previsao')">
            <div class="flex items-center justify-between gap-1 text-blue-600 mb-1 min-w-0">
                <span class="ep-label text-blue-700 truncate">Previsão Compras</span>
                <i class="fa-solid fa-truck-fast text-xs text-blue-500 group-hover:text-blue-700 transition flex-none"></i>
            </div>
            <div id="total-previsao" class="text-base sm:text-2xl font-black text-blue-700 tracking-tight truncate">0</div>
            <div id="kpiDetalhePrevisao" class="text-[0.65rem] text-blue-700/80 font-bold mt-1 truncate">0 OCs a caminho</div>
        </div>

        <!-- KPI 5: Estoque Futuro -->
        <div id="kpi-card-futuro" class="ep-kpi bg-gradient-to-br from-purple-50 to-indigo-50/70 border border-purple-200 shadow-sm relative group cursor-pointer hover:shadow-md transition-all active:scale-[0.98]" 
             title="ESTOQUE FUTURO PROJETADO&#10;• Fórmula: [Livre Disponível] + [Previsão de Compras (OCs)].&#10;• Conceito: Visão de disponibilidade a curto prazo considerando o suprimento que está para desembarcar."
             data-tooltip="Estoque Futuro Projetado|Disponibilidade total no curto prazo (Estoque Livre + Compras a Chegar). Clique para ordenar pelos maiores volumes."
             onclick="estoquePrevisaoApp.clicarCardKpi('futuro')">
            <div class="flex items-center justify-between gap-1 text-purple-600 mb-1 min-w-0">
                <span class="ep-label text-purple-700 truncate">Estoque Futuro</span>
                <i class="fa-solid fa-chart-line text-xs text-purple-500 group-hover:text-purple-700 transition flex-none"></i>
            </div>
            <div id="total-futuro" class="text-base sm:text-2xl font-black text-purple-700 tracking-tight truncate">0</div>
            <div id="kpiDetalheFuturo" class="text-[0.65rem] text-purple-700/80 font-bold mt-1 truncate">Livre + Previsão</div>
        </div>

        <!-- KPI 6: Zerados / Críticos -->
        <div id="kpi-card-critico" class="ep-kpi bg-gradient-to-br from-rose-50 to-red-50/70 border border-rose-200 shadow-sm relative group cursor-pointer hover:shadow-md transition-all active:scale-[0.98]" 
             title="PRODUTOS ZERADOS OU CRÍTICOS&#10;• Conceito: Itens com Saldo Livre zerado ou abaixo do estoque mínimo de segurança (≤ 10 un).&#10;• Ação Recomendada: Alerta imediato para equipe de compras e PCP. Clique para isolar a lista de itens críticos."
             data-tooltip="Críticos / Zerados|Produtos com saldo livre zerado ou abaixo de 10 unidades para reposição imediata. Clique para filtrar."
             onclick="estoquePrevisaoApp.clicarCardKpi('critico')">
            <div class="flex items-center justify-between gap-1 text-rose-600 mb-1 min-w-0">
                <span class="ep-label text-rose-700 truncate">Críticos / Zerados</span>
                <i class="fa-solid fa-triangle-exclamation text-xs text-rose-500 group-hover:text-rose-700 transition flex-none"></i>
            </div>
            <div id="total-zerados" class="text-base sm:text-2xl font-black text-rose-600 tracking-tight truncate">0</div>
            <div id="kpiDetalheCriticos" class="text-[0.65rem] text-rose-700/80 font-bold mt-1 truncate">0 críticos (≤10)</div>
        </div>
    </div>

    <!-- FILTROS DE BUSCA E CONTROLES (STICKY APENAS NO DESKTOP) -->
    <div class="ep-card p-3.5 sm:p-5 mb-5 lg:sticky lg:top-3 z-30 bg-white/95 backdrop-blur-md shadow-md border border-slate-200/80 transition-all">
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
                    <option value="reservado">🛒 Com Pedidos Reservados em Carteira</option>
                    <option value="critico_zerado">🚨 Críticos ou Zerados (≤ 10 un.)</option>
                    <option value="critico">⚠️ Crítico (1 a 10 un.)</option>
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

    <!-- TABELA / CARDS DE PRODUTOS -->
    <div class="ep-card overflow-hidden mb-5">
        <div class="p-4 sm:p-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-boxes-packing text-emerald-600"></i> Produtos & Previsão de Entrega
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Toque ou clique em qualquer produto para abrir o raio-x com ordens de compra e histórico</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1.5 md:hidden flex-1">
                    <span class="text-[11px] font-bold text-slate-400">Ordenar:</span>
                    <select class="py-1.5 px-2 border rounded-lg text-xs font-bold text-slate-700 bg-white flex-1" onchange="estoquePrevisaoApp.ordenar(this.value)">
                        <option value="produto">Nome Produto</option>
                        <option value="liquido">Saldo Livre</option>
                        <option value="previsao">A Chegar (OCs)</option>
                        <option value="reservado">Reservado</option>
                        <option value="estoque">Total Físico</option>
                        <option value="futuro">Estoque Futuro</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-[11px] sm:text-xs font-bold text-slate-400">Pág:</span>
                    <select id="select-limit" class="py-1.5 px-2 border rounded-lg text-xs font-bold text-slate-700 bg-white" onchange="estoquePrevisaoApp.mudarLimite(this.value)">
                        <option value="20">20</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- VISÃO MOBILE: CARDS DE PRODUTOS (APP-FIRST) -->
        <div id="mobile-cards-body" class="md:hidden divide-y divide-slate-100">
            <div class="py-10 text-center text-slate-400 text-xs">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-600 block"></i>
                Carregando estoque com previsão...
            </div>
        </div>

        <!-- VISÃO DESKTOP: TABELA COMPLETA -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm min-w-[900px]">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="bg-slate-50 text-[0.7rem] uppercase tracking-wider text-slate-500 font-extrabold select-none">
                        <th class="py-3.5 px-4 cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('produto')" data-tooltip="Produto & Referência|Nome do item, referência, ID interno e peso líquido unitário. Clique para ordenar.">
                            Produto & Referência <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-3 cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('marca')" data-tooltip="Marca Comercial|Fabricante ou linha de produtos. Clique para ordenar.">
                            Marca <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-2 text-right cursor-pointer hover:text-emerald-700 transition" data-tooltip="Estoque Matriz 1 (SC)|Saldo físico em unidades/peças no armazém da Matriz (Içara/SC).">
                            Matriz 1
                        </th>
                        <th class="py-3.5 px-2 text-right cursor-pointer hover:text-emerald-700 transition" data-tooltip="Estoque Filial PR|Saldo físico em unidades/peças no armazém da Filial Paraná.">
                            Filial PR
                        </th>
                        <th class="py-3.5 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('estoque')" data-tooltip="Total Físico (1 + 6)|Soma do saldo físico em peças em todas as unidades ativas. Clique para ordenar.">
                            Total Físico <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('reservado')" data-tooltip="Carteira Reservada|Quantidade em peças comprometida em pedidos de venda (Status 1 e 4). Clique para ordenar.">
                            Reservado <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('liquido')" data-tooltip="Estoque Livre|Peças imediatamente liberadas para faturamento (Físico - Reservado). Clique para ordenar.">
                            Livre <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('previsao')" data-tooltip="A Chegar (OCs)|Peças compradas com fornecedores em Ordens de Compra a caminho. Clique para ordenar.">
                            A Chegar (OCs) <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-3 text-right cursor-pointer hover:text-emerald-700 transition" onclick="estoquePrevisaoApp.ordenar('futuro')" data-tooltip="Estoque Futuro Total|Projeção total em peças (Livre + A Chegar). Clique para ordenar.">
                            Futuro <i class="fa-solid fa-sort ml-1 text-slate-300"></i>
                        </th>
                        <th class="py-3.5 px-3 text-center" data-tooltip="Situação do Estoque|Classificação: Ok (>50 un), Baixo (11-50 un), Crítico (1-10 un) ou Zerado (0).">Status</th>
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
            <div id="pagination-buttons" class="flex flex-wrap items-center justify-center gap-1.5"></div>
        </div>
    </div>

</div>

<!-- =================================================== -->
<!-- TOOLTIP MODERNO FLUTUANTE (ESTOQUE & PREVISÃO)     -->
<!-- =================================================== -->
<div id="modernTooltipEstoque" class="pointer-events-none fixed z-[9999] hidden max-w-xs rounded-2xl bg-slate-900/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md border border-slate-700/60 transition-opacity duration-150">
    <div class="flex items-center justify-between gap-2 mb-1.5">
        <span id="tooltipEstoqueBadge" class="text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50">ESTOQUE</span>
        <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
    </div>
    <div id="tooltipEstoqueTitle" class="font-black text-white text-xs mb-1">Título</div>
    <div id="tooltipEstoqueDesc" class="text-slate-300 text-[11px] font-medium leading-relaxed">Descrição detalhada ao passar o mouse.</div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
