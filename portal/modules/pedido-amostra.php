<?php
$pageTitle = 'Material de Apoio e Amostras | Nutricional';
$moduleJs = 'pedido-amostra.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .pa-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .pa-hero { background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #059669 100%); }
  .pa-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .pa-label { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .pa-input { width: 100%; margin-top: 0.35rem; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.75rem 1rem; font-size: 0.95rem; background: #f8fafc; transition: all 0.2s ease; font-weight: 600; color: #1e293b; }
  .pa-input:focus { outline: none; border-color: #10b981; background: #ffffff; box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12); }
  .pa-tab-btn { padding: 0.65rem 1.4rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; transition: all 0.2s ease; display: flex; items-center; gap: 0.5rem; }
  .pa-tab-btn.active { background: #047857; color: #ffffff; box-shadow: 0 4px 15px rgba(4, 120, 87, 0.3); }
  .pa-tab-btn:not(.active) { background: #f1f5f9; color: #64748b; }
  .pa-tab-btn:not(.active):hover { background: #e2e8f0; color: #1e293b; }
  .pa-item-card { border: 2px solid #f1f5f9; border-radius: 1.25rem; transition: all 0.2s ease; background: #ffffff; overflow: hidden; }
  .pa-item-card:hover { border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 0, 0, 0.04); }
  .pa-item-card.has-qty { border-color: #10b981; background: #f0fdf4; }
  .pa-qty-btn { width: 2.2rem; height: 2.2rem; border-radius: 0.75rem; background: #f1f5f9; font-weight: 900; font-size: 1.1rem; color: #334155; transition: 0.15s; display: flex; align-items: center; justify-content: center; }
  .pa-qty-btn:hover { background: #e2e8f0; color: #047857; }
  .pa-qty-inp { width: 3.5rem; text-align: center; font-weight: 800; border: 2px solid #e2e8f0; border-radius: 0.75rem; height: 2.2rem; font-size: 0.95rem; background: #ffffff; }
  .pa-qty-inp:focus { outline: none; border-color: #10b981; }
  .pa-sticky-bar { position: sticky; bottom: 1rem; z-index: 40; backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.92); border: 1px solid rgba(226, 232, 240, 0.8); box-shadow: 0 15px 35px rgba(15, 23, 42, 0.12); }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="pa-wrap max-w-6xl mx-auto px-3 sm:px-6 pt-4 pb-12">
    <!-- HERO BANNER -->
    <div class="pa-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-6 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-8 opacity-10 pointer-events-none">
            <i class="fa-solid fa-gift text-[160px]"></i>
        </div>
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white transition no-underline">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-lg bg-emerald-400/30 text-emerald-200 text-xs font-black uppercase tracking-wider">Gestão Comercial</span>
                        <span id="badgeRegraEstoque" class="px-2.5 py-0.5 rounded-lg bg-emerald-950/40 text-emerald-100 text-xs font-bold border border-emerald-400/30">
                            <i class="fa-solid fa-boxes-stacked mr-1"></i>Material de Apoio
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">Material de Apoio & Amostras</h1>
                    <p class="text-xs sm:text-sm text-emerald-100/90 font-medium mt-0.5">Criação ágil para gestores e representantes com controle inteligente de saldo</p>
                </div>
            </div>

            <!-- TAB CONTROLS -->
            <div class="flex items-center gap-2 bg-emerald-950/40 p-1.5 rounded-2xl border border-emerald-400/20">
                <button type="button" id="tabBtnSolicitar" class="pa-tab-btn active" onclick="trocarAba('solicitar')">
                    <i class="fa-solid fa-cart-plus"></i>
                    <span>Nova Solicitação</span>
                </button>
                <button type="button" id="tabBtnHistorico" class="pa-tab-btn" onclick="trocarAba('historico')">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Histórico & Rastreamento</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- ABA 1: NOVA SOLICITAÇÃO                    -->
    <!-- ========================================== -->
    <div id="abaSolicitar" class="space-y-5">
        <!-- 1. DADOS DO PEDIDO -->
        <div class="pa-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 font-black text-sm flex items-center justify-center">1</div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-800">Parâmetros da Solicitação</h2>
                        <p class="text-xs text-slate-400">Selecione a filial operativa, o tipo de transação e o cliente destinatário</p>
                    </div>
                </div>
                <div id="infoRegraFilial" class="hidden sm:flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-50 text-emerald-800 border border-emerald-200">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span id="txtInfoRegraFilial">Disponibilidade de Estoque em Tempo Real</span>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="pa-label">Filial Operativa</label>
                    <select id="selFilial" class="pa-input cursor-pointer"></select>
                </div>
                <div>
                    <label class="pa-label">Tipo de Pedido (Transação)</label>
                    <select id="selTransacao" class="pa-input cursor-pointer"></select>
                </div>
            </div>

            <!-- CLIENTE AUTOCOMPLETE -->
            <div class="mt-4 relative">
                <label class="pa-label">Cliente / Destinatário (Gestor ou Representante)</label>
                <div class="relative">
                    <input id="buscaCliente" type="search" autocomplete="off" placeholder="Digite código, CNPJ ou nome fantasia..." class="pa-input pr-10">
                    <div class="absolute right-3 top-3 text-slate-400 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass"></i>
                    </div>
                </div>
                <div id="listaClientes" class="hidden absolute z-30 left-0 right-0 mt-1.5 bg-white border border-slate-200 rounded-2xl shadow-2xl max-h-64 overflow-y-auto"></div>
                
                <div id="clienteSel" class="hidden mt-3 p-3.5 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm font-bold flex items-center justify-between gap-3 shadow-xs"></div>
            </div>
        </div>

        <!-- 2. CATÁLOGO DE ITENS COM FOTOS -->
        <div class="pa-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 font-black text-sm flex items-center justify-center">2</div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-800">Catálogo de Materiais & Amostras</h2>
                        <p class="text-xs text-slate-400">Estoque consultado em tempo real com fotos dos produtos</p>
                    </div>
                </div>

                <!-- CONTROLES DO CATÁLOGO -->
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="relative flex-1 sm:w-64">
                        <input id="buscaItem" type="search" placeholder="Filtrar por nome ou código..." class="w-full px-3.5 py-2 border-2 border-slate-200 rounded-xl text-xs font-semibold focus:outline-emerald-600">
                    </div>
                    <button id="btnRecarregar" class="w-9 h-9 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 flex items-center justify-center transition flex-none" title="Recarregar catálogo">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            </div>

            <!-- CONTAINER DOS ITENS (GRID MODERNO) -->
            <div id="listaItens" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 max-h-[60vh] overflow-y-auto pr-1"></div>
        </div>

        <!-- 3. OBSERVAÇÃO -->
        <div class="pa-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 font-black text-sm flex items-center justify-center">3</div>
                    <div>
                        <h2 class="text-sm sm:text-base font-extrabold text-slate-800">Observação / Justificativa <span class="text-rose-500">*</span></h2>
                        <p class="text-xs text-slate-400">Informação obrigatória para validação comercial no ERP</p>
                    </div>
                </div>
                <div class="text-xs text-slate-400 font-bold"><span id="contObs">0</span>/500</div>
            </div>
            <textarea id="observacao" maxlength="500" rows="3" placeholder="Ex: Material solicitado para o evento X / Entregar para o Representante Fulano..." class="pa-input resize-none"></textarea>
        </div>

        <!-- BARRA FLUTUANTE DE ENVIO -->
        <div class="pa-sticky-bar rounded-3xl p-4 sm:p-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-xl flex-none">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <div>
                    <div id="resumoSel" class="text-sm sm:text-base font-black text-slate-800">Nenhum item selecionado</div>
                    <div class="text-xs text-slate-400 font-semibold">Revise as quantidades antes de confirmar o envio</div>
                </div>
            </div>

            <button id="btnEnviar" class="px-7 py-3.5 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white font-extrabold text-sm sm:text-base shadow-lg shadow-emerald-600/30 transition flex items-center gap-2 cursor-pointer w-full sm:w-auto justify-center disabled:opacity-50">
                <i class="fa-solid fa-paper-plane"></i>
                <span>Enviar Solicitação</span>
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- ABA 2: HISTÓRICO & RASTREAMENTO            -->
    <!-- ========================================== -->
    <div id="abaHistorico" class="hidden space-y-5">
        <!-- 4 KPI CARDS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5">
            <div class="pa-card p-4 flex items-center gap-3.5 border-l-4 border-l-emerald-600">
                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Total Pedidos</div>
                    <div id="kpiTotalPedidos" class="text-xl font-black text-slate-800">0</div>
                </div>
            </div>

            <div class="pa-card p-4 flex items-center gap-3.5 border-l-4 border-l-amber-500">
                <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-700 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Aguardando ERP</div>
                    <div id="kpiPendentesErp" class="text-xl font-black text-amber-700">0</div>
                </div>
            </div>

            <div class="pa-card p-4 flex items-center gap-3.5 border-l-4 border-l-emerald-500">
                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-700 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Em Aberto</div>
                    <div id="kpiAbertos" class="text-xl font-black text-emerald-800">0</div>
                </div>
            </div>

            <div class="pa-card p-4 flex items-center gap-3.5 border-l-4 border-l-blue-600">
                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-700 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div>
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Faturados</div>
                    <div id="kpiFaturados" class="text-xl font-black text-blue-700">0</div>
                </div>
            </div>
        </div>

        <!-- LISTAGEM E FILTROS -->
        <div class="pa-card p-5 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
                <div>
                    <h2 class="text-base font-extrabold text-slate-800">Histórico de Pedidos</h2>
                    <p class="text-xs text-slate-400">Rastreie o solicitante, canal de origem (Portal vs ERP) e status</p>
                </div>
                <button id="btnAtualizarPedidos" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-rotate"></i>
                    <span>Atualizar</span>
                </button>
            </div>

            <!-- BARRA DE FILTROS -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div>
                    <label class="pa-label">Buscar</label>
                    <input id="buscaHistorico" type="search" placeholder="Pedido, solicitante ou cliente..." class="w-full px-3.5 py-2 border-2 border-slate-200 rounded-xl text-xs font-semibold focus:outline-emerald-600">
                </div>
                <div>
                    <label class="pa-label">Filtrar por Origem</label>
                    <select id="filtroOrigem" class="w-full px-3.5 py-2 border-2 border-slate-200 rounded-xl text-xs font-bold focus:outline-emerald-600 bg-white">
                        <option value="todos">Todas as Origens</option>
                        <option value="PORTAL">PORTAL (Via Sistema Web)</option>
                        <option value="ERP">ERP (Lançado Direto no ERP)</option>
                    </select>
                </div>
                <div>
                    <label class="pa-label">Filtrar por Status</label>
                    <select id="filtroStatus" class="w-full px-3.5 py-2 border-2 border-slate-200 rounded-xl text-xs font-bold focus:outline-emerald-600 bg-white">
                        <option value="todos">Todos os Status</option>
                        <option value="pendentes">Aguardando Importação ERP</option>
                        <option value="ativos">Em Aberto no ERP</option>
                        <option value="faturados">Faturados / Concluídos</option>
                    </select>
                </div>
            </div>

            <!-- TABELA DE PEDIDOS -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase font-black tracking-wider">
                            <th class="py-3 px-3"># Pedido</th>
                            <th class="py-3 px-3">Data</th>
                            <th class="py-3 px-3">Cliente / Destinatário</th>
                            <th class="py-3 px-3">Origem</th>
                            <th class="py-3 px-3">Solicitante</th>
                            <th class="py-3 px-3">Filial</th>
                            <th class="py-3 px-3 text-center">Itens / Qtd</th>
                            <th class="py-3 px-3">Situação</th>
                            <th class="py-3 px-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaPedidosCorpo" class="divide-y divide-slate-100">
                        <!-- Preenchido via JavaScript -->
                    </tbody>
                </table>
            </div>

            <div id="pedidosVazio" class="hidden p-8 text-center text-slate-400 text-sm">
                <i class="fa-solid fa-inbox text-3xl mb-2 text-slate-300 block"></i>
                Nenhum pedido encontrado para os filtros selecionados.
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE DETALHES DO PEDIDO -->
<div id="modalDetalhesPedido" class="hidden fixed inset-0 z-[70] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5">
    <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-100 overflow-hidden animate-scale-up">
        <!-- HEADER MODAL -->
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <div>
                    <h3 id="modalPedidoTitulo" class="text-base font-extrabold text-slate-800 leading-tight">Detalhes do Pedido</h3>
                    <p id="modalPedidoSubtitulo" class="text-xs text-slate-500 font-medium"></p>
                </div>
            </div>
            <button type="button" onclick="fecharModalDetalhes()" class="w-8 h-8 rounded-lg bg-slate-200/70 hover:bg-slate-300 text-slate-600 flex items-center justify-center transition">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <!-- CORPO MODAL -->
        <div class="p-5 overflow-y-auto space-y-4 flex-1">
            <!-- CARDS DE METADADOS -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-xs">
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Origem</span>
                    <span id="modalOrigem" class="font-bold text-slate-700"></span>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Solicitante</span>
                    <span id="modalSolicitante" class="font-bold text-slate-700"></span>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Filial</span>
                    <span id="modalFilial" class="font-bold text-slate-700"></span>
                </div>
                <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Situação</span>
                    <span id="modalStatus" class="font-bold"></span>
                </div>
            </div>

            <!-- OBSERVAÇÃO -->
            <div class="bg-amber-50/70 border border-amber-200/70 p-3 rounded-2xl text-xs">
                <span class="font-bold text-amber-900 block mb-0.5"><i class="fa-solid fa-message mr-1"></i>Observação do Pedido:</span>
                <span id="modalObservacao" class="text-amber-800 font-medium"></span>
            </div>

            <!-- LISTA DE ITENS COM FOTOS -->
            <div>
                <h4 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-2">Itens Solicitados</h4>
                <div id="modalListaItens" class="space-y-2 max-h-64 overflow-y-auto pr-1"></div>
            </div>
        </div>

        <!-- FOOTER MODAL -->
        <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button type="button" onclick="fecharModalDetalhes()" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white text-xs font-bold hover:bg-slate-900 transition">
                Fechar
            </button>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
