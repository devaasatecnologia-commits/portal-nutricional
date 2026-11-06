<?php
$pageTitle = 'Fluxo E-commerce | Nutricional';
$moduleJs = 'fluxo-ecomerce.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .fe-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .fe-hero { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%); }
  .fe-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .fe-label { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .fe-input { width: 100%; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.65rem 1rem; font-size: 0.9rem; background: #f8fafc; transition: all 0.2s ease; font-weight: 600; color: #1e293b; }
  .fe-input:focus { outline: none; border-color: #2563eb; background: #ffffff; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12); }
  .fe-tab-btn { padding: 0.65rem 1.4rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
  .fe-tab-btn.active { background: #1d4ed8; color: #ffffff; box-shadow: 0 4px 15px rgba(29, 78, 216, 0.35); }
  .fe-tab-btn:not(.active) { background: #f1f5f9; color: #64748b; }
  .fe-tab-btn:not(.active):hover { background: #e2e8f0; color: #1e293b; }
  .fe-quick-btn { padding: 0.35rem 0.85rem; border-radius: 0.85rem; font-weight: 800; font-size: 0.75rem; color: #64748b; transition: all 0.2s ease; cursor: pointer; border: none; background: transparent; }
  .fe-quick-btn:hover { color: #1e293b; background: rgba(255, 255, 255, 0.7); }
  .fe-quick-btn.active { background: #ffffff; color: #2563eb; box-shadow: 0 2px 8px rgba(37, 99, 235, 0.15); }
  .fe-item-card { border: 2px solid #f1f5f9; border-radius: 1.25rem; transition: all 0.2s ease; background: #ffffff; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; }
  .fe-item-card:hover { border-color: #93c5fd; transform: translateY(-2px); box-shadow: 0 10px 25px rgba(37, 99, 235, 0.08); }
  .fe-item-card.has-qty { border-color: #2563eb; background: #eff6ff; }
  .fe-qty-btn { width: 2.2rem; height: 2.2rem; border-radius: 0.75rem; background: #f1f5f9; font-weight: 900; font-size: 1.1rem; color: #1e293b; transition: 0.15s; display: flex; align-items: center; justify-content: center; cursor: pointer; }
  .fe-qty-btn:hover { background: #dbeafe; color: #1d4ed8; }
  .fe-qty-inp { width: 3.8rem; text-align: center; font-weight: 800; border: 2px solid #e2e8f0; border-radius: 0.75rem; height: 2.2rem; font-size: 0.95rem; background: #ffffff; }
  .fe-qty-inp:focus { outline: none; border-color: #2563eb; }
  .fe-sticky-bar { position: sticky; bottom: 1rem; z-index: 40; backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.95); border: 1px solid rgba(191, 219, 254, 0.8); box-shadow: 0 15px 35px rgba(30, 58, 138, 0.15); }
  .fe-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 0.6rem; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="fe-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-4 pb-16">
    <!-- HERO BANNER -->
    <div class="fe-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-6 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-8 opacity-10 pointer-events-none">
            <i class="fa-solid fa-cart-shopping text-[170px]"></i>
        </div>
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white transition no-underline">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-lg bg-blue-300/30 text-blue-100 text-xs font-black uppercase tracking-wider">Gestão Comercial</span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-blue-950/40 text-blue-100 text-xs font-bold border border-blue-400/30">
                            <i class="fa-solid fa-store mr-1"></i>E-commerce & Marketplaces
                        </span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-emerald-400/30 text-emerald-100 text-xs font-black uppercase tracking-wider">
                            <i class="fa-solid fa-check-circle mr-1"></i>Cliente 16736 • AZ PETS CO
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">Fluxo E-commerce</h1>
                    <p class="text-xs sm:text-sm text-blue-100/90 font-medium mt-0.5">Emissão ágil de pedidos vinculados à Tabela 15 ([WEB] ECOMERCE) e acompanhamento analítico</p>
                </div>
            </div>

            <!-- CONTROLES DE ABAS -->
            <div class="flex items-center gap-2 bg-blue-950/40 p-1.5 rounded-2xl border border-blue-400/20">
                <button type="button" id="tabBtnNovoPedido" class="fe-tab-btn active" onclick="trocarAba('pedido')">
                    <i class="fa-solid fa-cart-plus"></i>
                    <span>Novo Pedido</span>
                </button>
                <button type="button" id="tabBtnDashboard" class="fe-tab-btn" onclick="trocarAba('dashboard')">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Dashboard & Histórico</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- ABA 1: NOVO PEDIDO E-COMMERCE                                          -->
    <!-- ====================================================================== -->
    <div id="abaNovoPedido" class="space-y-5">
        <!-- BARRA COMPACTA COM PARÂMETROS CRAVADOS -->
        <div class="fe-card p-3.5 sm:p-4 bg-slate-900 text-white rounded-2xl border border-slate-800 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-blue-500/20 text-blue-400 border border-blue-400/30 flex items-center justify-center text-base font-black">
                        <i class="fa-solid fa-store"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] font-black uppercase tracking-wider text-blue-400">Cliente E-commerce</span>
                            <span class="px-2 py-0.5 rounded-md bg-emerald-500/20 text-emerald-300 text-[10px] font-extrabold border border-emerald-400/30">REGRAS CRAVADAS NO ERP</span>
                        </div>
                        <div class="text-sm font-black text-white flex items-center gap-2 flex-wrap mt-0.5">
                            <span>16736 &bull; AZ PETS CO</span>
                            <span class="text-xs text-slate-400 font-normal">CNPJ: 22.876.867/0001-48 &bull; SC</span>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-1.5 sm:gap-2 flex-wrap text-xs">
                    <div class="px-2.5 py-1 rounded-xl bg-slate-800 border border-slate-700/80 text-slate-200 flex items-center gap-1.5 font-bold" title="Tabela padrão vinculada">
                        <i class="fa-solid fa-tags text-emerald-400"></i>
                        <span>Tabela 15</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-xl bg-slate-800 border border-slate-700/80 text-slate-200 flex items-center gap-1.5 font-bold" title="Transação oficial e série">
                        <i class="fa-solid fa-file-invoice text-indigo-400"></i>
                        <span>Transação 4 (Série 0)</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-xl bg-slate-800 border border-slate-700/80 text-slate-200 flex items-center gap-1.5 font-bold" title="Condição de pagamento vinculada ao cliente (ou tabela)">
                        <i class="fa-solid fa-credit-card text-blue-400"></i>
                        <span id="badgeTopCondicao">Condição: Carregando...</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-xl bg-slate-800 border border-slate-700/80 text-slate-200 flex items-center gap-1.5 font-bold" title="Método de pagamento vinculado ao cliente (ou tabela)">
                        <i class="fa-solid fa-money-bill-wave text-amber-400"></i>
                        <span id="badgeTopMetodo">Método: Carregando...</span>
                    </div>
                    <div class="px-2.5 py-1 rounded-xl bg-slate-800 border border-slate-700/80 text-slate-200 flex items-center gap-1.5 font-bold" title="Filial Operativa">
                        <i class="fa-solid fa-warehouse text-slate-400"></i>
                        <span>Filial 1</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SELEÇÃO DE ITENS E CATÁLOGO -->
        <div class="fe-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-5 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 font-black text-sm flex items-center justify-center">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-800">Catálogo de Produtos &bull; Tabela 15</h2>
                        <p class="text-xs text-slate-400">Adicione as quantidades dos itens desejados. Clique na foto para ampliar.</p>
                    </div>
                </div>

                <!-- CAMPO DE BUSCA RÁPIDA -->
                <div class="w-full sm:w-80 relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input type="text" id="filtroItens" class="fe-input pl-9" placeholder="Buscar por código, ref ou nome..." oninput="filtrarItens()">
                </div>
            </div>

            <!-- GRADE DE PRODUTOS -->
            <div id="gridItens" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <!-- Preenchido via JavaScript -->
            </div>

            <!-- MENSAGEM QUANDO NÃO HÁ ITENS -->
            <div id="emptyItens" class="hidden py-12 text-center text-slate-400">
                <i class="fa-solid fa-box-open text-4xl mb-3 text-slate-300"></i>
                <p class="font-bold text-sm">Nenhum produto encontrado na Tabela 15 para este filtro.</p>
            </div>
        </div>

        <!-- STICKY BAR: RESUMO DO PEDIDO E BOTÃO DE ENVIAR -->
        <div class="fe-sticky-bar rounded-3xl p-4 sm:p-5">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-4">
                <!-- RESUMO DE MÉTRICAS -->
                <div class="flex flex-wrap items-center gap-4 sm:gap-6 w-full lg:w-auto">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-lg font-black">
                            <i class="fa-solid fa-boxes-packing"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Itens / Peças</span>
                            <div class="text-base sm:text-lg font-black text-slate-800">
                                <span id="barQtdItens">0</span> <span class="text-xs font-semibold text-slate-400">itens</span> &bull; 
                                <span id="barTotalUnidades">0</span> <span class="text-xs font-semibold text-slate-400">un</span>
                            </div>
                        </div>
                    </div>

                    <div class="hidden sm:block h-9 w-[1px] bg-slate-200"></div>

                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Peso Líquido Total</span>
                        <div class="text-base sm:text-lg font-black text-slate-800">
                            <span id="barPesoTotal">0,00</span> <span class="text-xs font-semibold text-slate-400">kg</span>
                        </div>
                    </div>

                    <div class="hidden sm:block h-9 w-[1px] bg-slate-200"></div>

                    <div>
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Valor Total Previsto</span>
                        <div class="text-base sm:text-lg font-black text-emerald-600">
                            R$ <span id="barValorTotal">0,00</span>
                        </div>
                    </div>
                </div>

                <!-- CAMPO OBSERVAÇÃO + AÇÕES -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 w-full lg:w-auto">
                    <div class="relative w-full sm:w-72">
                        <input type="text" id="inpObservacao" class="fe-input" placeholder="Observação opcional (ex: Pedido ML/Shopee)..." maxlength="500">
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="px-4 py-3 rounded-2xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition cursor-pointer" onclick="limparCarrinho()" title="Zerar quantidades">
                            <i class="fa-solid fa-trash-can mr-1"></i>Limpar
                        </button>
                        <button type="button" id="btnEnviarPedido" class="flex-1 sm:flex-none px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white font-extrabold text-sm shadow-lg shadow-blue-500/30 transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:pointer-events-none" onclick="confirmarEnviarPedido()">
                            <i class="fa-solid fa-paper-plane"></i>
                            <span>Enviar Pedido</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- ABA 2: DASHBOARD & HISTÓRICO                                           -->
    <!-- ====================================================================== -->
    <div id="abaDashboard" class="hidden space-y-6">
        <!-- BARRA SUPERIOR DE FILTROS & FILTROS RÁPIDOS -->
        <div class="fe-card p-4 sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-calendar-day text-blue-600"></i>
                        <span>Período de Análise:</span>
                    </span>
                    <span id="badgePeriodoAtivo" class="px-2.5 py-0.5 rounded-lg bg-blue-100 text-blue-800 text-xs font-black">
                        Este Mês
                    </span>
                </div>

                <!-- FILTROS RÁPIDOS -->
                <div class="flex flex-wrap items-center gap-1 bg-slate-100 p-1 rounded-2xl">
                    <button type="button" class="fe-quick-btn" data-rapido="hoje" onclick="aplicarFiltroRapido('hoje')">Hoje</button>
                    <button type="button" class="fe-quick-btn active" data-rapido="este_mes" onclick="aplicarFiltroRapido('este_mes')">Este Mês</button>
                    <button type="button" class="fe-quick-btn" data-rapido="mes_passado" onclick="aplicarFiltroRapido('mes_passado')">Mês Passado</button>
                    <button type="button" class="fe-quick-btn" data-rapido="ultimos_30" onclick="aplicarFiltroRapido('ultimos_30')">Últimos 30 dias</button>
                    <button type="button" class="fe-quick-btn" data-rapido="este_ano" onclick="aplicarFiltroRapido('este_ano')">Este Ano</button>
                    <button type="button" class="fe-quick-btn" data-rapido="tudo" onclick="aplicarFiltroRapido('tudo')">Todo o Histórico</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-3 border-t border-slate-100">
                <div>
                    <label class="fe-label">Data Inicial</label>
                    <input type="date" id="filtroDataInicio" class="fe-input" onchange="aoMudarFiltroManual()">
                </div>
                <div>
                    <label class="fe-label">Data Final</label>
                    <input type="date" id="filtroDataFim" class="fe-input" onchange="aoMudarFiltroManual()">
                </div>
                <div>
                    <label class="fe-label">Status do Pedido</label>
                    <select id="filtroStatus" class="fe-input cursor-pointer" onchange="aoMudarFiltroManual()">
                        <option value="todos">Todos os Status</option>
                        <option value="pendente">Apenas Pendentes (Palmtop)</option>
                        <option value="aberto">Apenas Abertos (ERP)</option>
                        <option value="faturado">Apenas Faturados (ERP)</option>
                    </select>
                </div>
                <div>
                    <label class="fe-label">Busca Rápida na Tabela</label>
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="filtroBuscaPedido" class="fe-input pl-9" placeholder="ID, observação ou solicitante..." oninput="filtrarPedidosLocal()">
                    </div>
                </div>
            </div>
        </div>

        <!-- CARDS DE KPIS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="fe-card p-5">
                <div class="flex items-center justify-between text-blue-600 mb-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">Total de Pedidos</span>
                    <i class="fa-solid fa-receipt text-xl"></i>
                </div>
                <div class="text-2xl font-black text-slate-800" id="kpiTotalPedidos">0</div>
                <div class="text-xs text-slate-400 mt-1" id="kpiPeriodoPedidos">Mês corrente</div>
            </div>

            <div class="fe-card p-5">
                <div class="flex items-center justify-between text-emerald-600 mb-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">Faturamento no Período</span>
                    <i class="fa-solid fa-sack-dollar text-xl"></i>
                </div>
                <div class="text-2xl font-black text-emerald-600" id="kpiFaturamento">R$ 0,00</div>
                <div class="text-xs text-slate-400 mt-1">Soma de pedidos faturados e abertos</div>
            </div>

            <div class="fe-card p-5">
                <div class="flex items-center justify-between text-indigo-600 mb-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">Ticket Médio</span>
                    <i class="fa-solid fa-chart-line text-xl"></i>
                </div>
                <div class="text-2xl font-black text-indigo-600" id="kpiTicketMedio">R$ 0,00</div>
                <div class="text-xs text-slate-400 mt-1">Valor médio por pedido emitido</div>
            </div>

            <div class="fe-card p-5">
                <div class="flex items-center justify-between text-amber-600 mb-2">
                    <span class="text-xs font-black uppercase tracking-wider text-slate-500">Pendentes no Palmtop</span>
                    <i class="fa-solid fa-clock-rotate-left text-xl"></i>
                </div>
                <div class="text-2xl font-black text-amber-600" id="kpiPendentes">0</div>
                <div class="text-xs text-slate-400 mt-1" id="kpiValorPendente">Aguardando importação do ERP</div>
            </div>
        </div>

        <!-- TOP ITENS MAIS VENDIDOS -->
        <div class="fe-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100 flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 font-black text-sm flex items-center justify-center">
                        <i class="fa-solid fa-crown text-amber-500"></i>
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-800">Top Itens Mais Vendidos no Período &bull; AZ PETS CO</h2>
                        <p class="text-xs text-slate-400">Ranking por volume de unidades e faturamento no período selecionado</p>
                    </div>
                </div>
                <span class="fe-badge bg-amber-50 text-amber-700 border border-amber-200">
                    <i class="fa-solid fa-fire text-amber-500"></i> Ranking Período
                </span>
            </div>

            <div id="gridTopItens" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Preenchido via JavaScript -->
            </div>
        </div>

        <!-- TABELA DE HISTÓRICO -->
        <div class="fe-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-blue-100 text-blue-700 font-black text-sm flex items-center justify-center">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-800">Histórico de Pedidos Emitidos</h2>
                        <p class="text-xs text-slate-400">Listagem de pedidos filtrados por período e status</p>
                    </div>
                </div>

                <!-- BOTÃO EXPORTAR CSV -->
                <button type="button" class="px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs shadow transition flex items-center gap-2 cursor-pointer" onclick="exportarCsvPedidos()">
                    <i class="fa-solid fa-file-excel"></i>
                    <span>Exportar CSV</span>
                </button>
            </div>

            <!-- TABELA -->
            <div class="overflow-x-auto rounded-2xl border border-slate-100">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50 text-[11px] font-black uppercase text-slate-400 tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="p-3.5">Pedido</th>
                            <th class="p-3.5">Data</th>
                            <th class="p-3.5">Origem / Status</th>
                            <th class="p-3.5">Solicitante</th>
                            <th class="p-3.5 text-center">Itens / Qtd</th>
                            <th class="p-3.5 text-right">Valor Total</th>
                            <th class="p-3.5">Observação</th>
                            <th class="p-3.5 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyHistorico" class="divide-y divide-slate-100 font-semibold">
                        <!-- Preenchido via JavaScript -->
                    </tbody>
                </table>
            </div>

            <div id="emptyHistorico" class="hidden py-8 text-center text-slate-400">
                <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300"></i>
                <p class="font-bold text-xs">Nenhum pedido encontrado para o período ou filtro informado.</p>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL: DETALHES DO PEDIDO SELECIONADO                                  -->
<!-- ====================================================================== -->
<div id="modalDetalhes" class="hidden fixed inset-0 z-50 flex items-center justify-center p-3 bg-slate-900/60 backdrop-blur-sm">
    <div class="bg-white rounded-3xl max-w-2xl w-full p-5 sm:p-6 shadow-2xl max-h-[90vh] flex flex-col">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-2xl bg-blue-100 text-blue-700 flex items-center justify-center text-base font-black">
                    <i class="fa-solid fa-file-lines"></i>
                </div>
                <div>
                    <h3 class="font-black text-slate-800 text-base" id="modalDetTitulo">Detalhes do Pedido</h3>
                    <p class="text-xs text-slate-400" id="modalDetSubtitulo">AZ PETS CO</p>
                </div>
            </div>
            <button type="button" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer" onclick="fecharModalDetalhes()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="overflow-y-auto py-4 space-y-3 flex-1" id="modalDetItens">
            <!-- Itens preenchidos via JS -->
        </div>

        <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
            <div class="text-xs text-slate-500 font-medium" id="modalDetObs"></div>
            <div class="text-right">
                <span class="text-xs text-slate-400 font-bold uppercase tracking-wider block">Total Geral</span>
                <span class="text-lg font-black text-emerald-600" id="modalDetTotal">R$ 0,00</span>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL: VISUALIZAÇÃO AMPLIADA DA FOTO DO PRODUTO                         -->
<!-- ====================================================================== -->
<div id="modalFoto" class="hidden fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md" onclick="fecharModalFoto()">
    <div class="relative max-w-lg w-full bg-white rounded-3xl p-5 shadow-2xl flex flex-col items-center" onclick="event.stopPropagation()">
        <button type="button" class="absolute top-4 right-4 w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center text-sm cursor-pointer" onclick="fecharModalFoto()">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="modalFotoImg" src="" alt="Produto" class="w-72 h-72 object-contain rounded-2xl mb-4 bg-slate-50 p-2">
        <h4 class="font-extrabold text-slate-800 text-sm text-center" id="modalFotoNome">Descrição do Produto</h4>
        <p class="text-xs text-slate-400 font-semibold mt-1" id="modalFotoRef">Ref: -</p>
    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
