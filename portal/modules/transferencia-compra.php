<?php
$tipo = ($_GET['tipo'] ?? 'matriz-filial') === 'filial-matriz' ? 'filial-matriz' : 'matriz-filial';
$ehMF = $tipo === 'matriz-filial';
$pageTitle = ($ehMF ? 'Matriz → Filial' : 'Filial → Matriz') . ' | Transferência de Compras | Nutricional';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$cor = $ehMF ? ['#0369a1', '#0284c7', '#38bdf8'] : ['#0f766e', '#0d9488', '#2dd4bf'];

$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .tc-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .tc-hero { background: linear-gradient(135deg, ' . $cor[0] . ' 0%, ' . $cor[1] . ' 50%, ' . $cor[2] . ' 100%); }
  .tc-card { background: #ffffff; border-radius: 1.25rem; box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .tc-label { font-size: 0.7rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .tc-input { width: 100%; border: 1.5px solid #e2e8f0; border-radius: 0.85rem; padding: 0.65rem 0.85rem; font-size: 0.9rem; background: #f8fafc; transition: all 0.2s; }
  .tc-input:focus { outline: none; border-color: ' . $cor[1] . '; background: #ffffff; box-shadow: 0 0 0 3px ' . $cor[2] . '33; }
  .tc-btn-primary { background: linear-gradient(135deg, ' . $cor[0] . ', ' . $cor[1] . '); color: #ffffff; border-radius: 0.85rem; font-weight: 700; padding: 0.65rem 1.2rem; transition: all 0.2s; }
  .tc-btn-primary:hover { opacity: 0.95; transform: translateY(-1px); box-shadow: 0 4px 12px ' . $cor[0] . '40; }
  .tc-btn-secondary { background: #f1f5f9; color: #475569; border-radius: 0.85rem; font-weight: 700; padding: 0.65rem 1.2rem; transition: all 0.2s; }
  .tc-btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
  .tc-tab-btn { padding: 0.5rem 1rem; border-radius: 0.75rem; font-weight: 700; font-size: 0.82rem; transition: all 0.2s; }
  .tc-tab-btn.active { background: #ffffff; color: ' . $cor[0] . '; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
  .tc-tab-btn:not(.active) { color: #64748b; }
  .tc-tab-btn:not(.active):hover { color: #1e293b; }
  .tc-table th { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; color: #64748b; padding: 0.75rem 0.85rem; border-bottom: 2px solid #f1f5f9; background: #f8fafc; }
  .tc-table td { padding: 0.75rem 0.85rem; border-bottom: 1px solid #f1f5f9; font-size: 0.85rem; vertical-align: middle; }
  .tc-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 800; }
</style>
';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="' . $appBase . '/portal/assets/js/transferencia-compra.js?v=' . $version . '"></script>
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="tc-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-4 pb-12" data-tipo="<?= $tipo ?>">

    <!-- HERO HEADER -->
    <div class="tc-hero rounded-3xl p-6 sm:p-7 text-white shadow-xl mb-6 relative overflow-hidden">
        <div class="absolute -right-8 -bottom-8 opacity-10 pointer-events-none text-9xl">
            <i class="fa-solid <?= $ehMF ? 'fa-truck-ramp-box' : 'fa-warehouse' ?>"></i>
        </div>
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 relative z-10">
            <div class="flex items-center gap-4">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/20 hover:bg-white/30 backdrop-blur-sm flex items-center justify-center text-white transition-all shadow-sm no-underline">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/25 text-white">Transferência entre Unidades</span>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-400/90 text-amber-950">Inteligente</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight leading-tight"><?= $ehMF ? 'Matriz → Filial PR' : 'Filial PR → Matriz' ?></h1>
                    <p class="text-xs sm:text-sm text-white/90 mt-0.5 font-medium">
                        <?= $ehMF ? 'Cálculo de necessidade com reserva do cliente 14523, remessa marca 60 e apuração de Guia ST' : 'Acompanhamento de ordens de compra e solicitações de transferência' ?>
                    </p>
                </div>
            </div>
            
            <!-- SWITCHER DE FLUXO -->
            <div class="bg-black/20 backdrop-blur-md p-1.5 rounded-2xl flex items-center gap-1 self-stretch sm:self-auto">
                <a href="?tipo=matriz-filial" class="tc-tab-btn flex-1 sm:flex-none text-center <?= $ehMF ? 'active' : 'text-white/80 hover:text-white' ?>" style="text-decoration:none;">
                    <i class="fa-solid fa-truck-arrow-right mr-1.5"></i> Matriz → Filial
                </a>
                <a href="?tipo=filial-matriz" class="tc-tab-btn flex-1 sm:flex-none text-center <?= !$ehMF ? 'active' : 'text-white/80 hover:text-white' ?>" style="text-decoration:none;">
                    <i class="fa-solid fa-warehouse mr-1.5"></i> Filial → Matriz
                </a>
            </div>
        </div>
    </div>

    <!-- CARDS DE KPIS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
        <div class="tc-card p-4 flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-sky-50 flex items-center justify-center text-sky-600 flex-shrink-0 text-xl font-bold">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div class="min-w-0">
                <span class="tc-label">Solicitações</span>
                <div id="kpiSolicitacoes" class="text-xl sm:text-2xl font-black text-slate-800 leading-none mt-1">—</div>
                <span class="text-[11px] text-slate-400 mt-0.5 block truncate" id="kpiSolicitacoesSub">No filtro selecionado</span>
            </div>
        </div>

        <div class="tc-card p-4 flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 flex items-center justify-center text-emerald-600 flex-shrink-0 text-xl font-bold">
                <i class="fa-solid <?= $ehMF ? 'fa-calculator' : 'fa-coins' ?>"></i>
            </div>
            <div class="min-w-0">
                <span class="tc-label"><?= $ehMF ? 'Total Guia ST' : 'Valor Total' ?></span>
                <div id="kpiValorTotal" class="text-xl sm:text-2xl font-black text-emerald-700 leading-none mt-1 truncate">—</div>
                <span class="text-[11px] text-slate-400 mt-0.5 block truncate"><?= $ehMF ? 'ICMS-ST apurado PR' : 'Ordens de compra' ?></span>
            </div>
        </div>

        <div class="tc-card p-4 flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-amber-50 flex items-center justify-center text-amber-600 flex-shrink-0 text-xl font-bold">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="min-w-0">
                <span class="tc-label"><?= $ehMF ? 'Embarques Filial 6' : 'Ordens Disponíveis' ?></span>
                <div id="kpiPendentes" class="text-xl sm:text-2xl font-black text-amber-600 leading-none mt-1">—</div>
                <span class="text-[11px] text-slate-400 mt-0.5 block truncate">Aguardando solicitação</span>
            </div>
        </div>

        <div class="tc-card p-4 flex items-center gap-3.5">
            <div class="w-12 h-12 rounded-2xl bg-purple-50 flex items-center justify-center text-purple-600 flex-shrink-0 text-xl font-bold">
                <i class="fa-solid fa-shield-heart"></i>
            </div>
            <div class="min-w-0">
                <span class="tc-label">Reserva Filial PR</span>
                <div class="text-sm font-extrabold text-purple-700 leading-tight mt-1">Cliente #14523</div>
                <span class="text-[11px] text-slate-400 mt-0.5 block truncate">Proteção de estoque ativa</span>
            </div>
        </div>
    </div>

    <!-- SEÇÃO: EMBARQUES AGUARDANDO SOLICITAÇÃO (SOMENTE MATRIZ -> FILIAL) -->
    <?php if ($ehMF): ?>
    <div class="tc-card p-5 mb-6 border-l-4 border-l-amber-500">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base sm:text-lg font-extrabold text-slate-800">Embarques da Filial PR (Aguardando Solicitação)</h2>
                    <span id="badgeQtdPendentes" class="tc-badge bg-amber-100 text-amber-800">0 pendentes</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Consulte a prévia com a dedução automática de estoque e proteção do pedido 14523 antes de gerar a solicitação no ERP.
                </p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input id="buscaPendentes" type="search" placeholder="Filtrar por embarque ou placa..." class="tc-input !pl-8 !py-2 text-xs">
                </div>
                <label class="flex items-center gap-1.5 text-xs text-slate-600 font-semibold cursor-pointer whitespace-nowrap bg-slate-50 px-3 py-2 rounded-xl border border-slate-200">
                    <input type="checkbox" id="chkTodosEmbarques" class="rounded text-sky-600">
                    <span>Ver faturados</span>
                </label>
                <button id="btnRecarregarPendentes" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" title="Recarregar">
                    <i class="fa-solid fa-arrows-rotate text-xs"></i>
                </button>
            </div>
        </div>

        <div id="listaPendentes" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="col-span-full py-8 text-center text-sm text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-lg mr-2 text-sky-600"></i> Carregando embarques da filial PR...
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- SEÇÃO: ORDENS DE COMPRA DISPONÍVEIS (SOMENTE FILIAL PR -> MATRIZ) -->
    <div class="tc-card p-5 mb-6 border-l-4 border-l-teal-600">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-base sm:text-lg font-extrabold text-slate-800">Ordens de Compra Disponíveis (Filial PR → Matriz)</h2>
                    <span id="badgeQtdOrdensPendentes" class="tc-badge bg-teal-100 text-teal-800">0 pendentes</span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Gere solicitações de transferência de compra da Filial PR para a Matriz com base nas ordens disponíveis.
                </p>
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <div class="relative flex-1 sm:w-64">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input id="buscaOrdensPendentes" type="search" placeholder="Filtrar por ordem ou fornecedor..." class="tc-input !pl-8 !py-2 text-xs">
                </div>
                <button id="btnRecarregarOrdens" class="p-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 transition-colors" title="Recarregar">
                    <i class="fa-solid fa-arrows-rotate text-xs"></i>
                </button>
            </div>
        </div>

        <div id="listaOrdensPendentes" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div class="col-span-full py-8 text-center text-sm text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-lg mr-2 text-teal-600"></i> Carregando ordens de compra...
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SEÇÃO: HISTÓRICO DE SOLICITAÇÕES / GUIAS APURADAS -->
    <div class="tc-card p-5 mb-6">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <div>
                <h2 class="text-base sm:text-lg font-extrabold text-slate-800">
                    <?= $ehMF ? 'Histórico de Solicitações Geradas & Apuração de Guia ST' : 'Histórico de Transferências Filial → Matriz' ?>
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">
                    <?= $ehMF ? 'Acompanhe as solicitações criadas, valores de guias ST calculados por NCM e memória fiscal.' : 'Solicitações de compra geradas a partir de ordens da filial.' ?>
                </p>
            </div>
            <button id="btnXls" class="tc-btn-secondary text-xs flex items-center gap-2 !py-2">
                <i class="fa-solid fa-file-excel text-emerald-600"></i> Exportar Planilha
            </button>
        </div>

        <!-- FILTROS -->
        <div class="bg-slate-50/70 p-3.5 rounded-2xl border border-slate-200/80 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-2.5">
                <div>
                    <span class="tc-label">Data Inicial</span>
                    <input id="dtIni" type="date" class="tc-input text-xs !py-1.5">
                </div>
                <div>
                    <span class="tc-label">Data Final</span>
                    <input id="dtFim" type="date" class="tc-input text-xs !py-1.5">
                </div>
                <div class="sm:col-span-2">
                    <span class="tc-label">Buscar</span>
                    <div class="flex gap-2">
                        <input id="busca" type="search" placeholder="<?= $ehMF ? 'Número da solicitação, embarque ou placa...' : 'Ordem de compra ou fornecedor...' ?>" class="tc-input text-xs !py-1.5">
                        <button id="btnFiltrar" class="tc-btn-primary text-xs !py-1.5 px-3 flex items-center gap-1.5">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <button id="btnLimpar" class="tc-btn-secondary text-xs !py-1.5 px-3" title="Limpar filtros">
                            <i class="fa-solid fa-eraser"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- LISTAGEM -->
        <div class="flex items-center justify-between mb-2 px-1">
            <span class="tc-label" id="rotulo">Últimos registros</span>
            <span id="totais" class="text-xs font-bold text-slate-600">—</span>
        </div>
        <div id="lista" class="space-y-2">
            <div class="py-8 text-center text-sm text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-lg mr-2 text-sky-600"></i> Carregando registros...
            </div>
        </div>
    </div>

</div>

<!-- ====================================================================== -->
<!-- MODAL DE PRÉVIA DE TRANSFERÊNCIA (ANÁLISE DE NECESSIDADE)               -->
<!-- ====================================================================== -->
<div id="modalPrevia" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[70] hidden flex items-center justify-center p-3 sm:p-4 transition-opacity">
    <div class="bg-white w-full max-w-5xl max-h-[92vh] rounded-3xl shadow-2xl flex flex-col overflow-hidden border border-slate-100 animate-in fade-in zoom-in-95 duration-200">
        
        <!-- HEADER DA MODAL -->
        <div class="p-5 sm:p-6 bg-gradient-to-r from-slate-900 via-sky-950 to-slate-900 text-white flex items-center justify-between gap-4 flex-shrink-0">
            <div class="flex items-center gap-3.5">
                <div class="w-12 h-12 rounded-2xl bg-sky-500/20 border border-sky-400/30 flex items-center justify-center text-sky-300 text-xl font-bold">
                    <i class="fa-solid fa-boxes-packing"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-black tracking-widest text-sky-400 uppercase">Prévia de Transferência</span>
                        <span id="previaBadgeStatus" class="tc-badge bg-amber-400/20 text-amber-300 border border-amber-400/30">Análise</span>
                    </div>
                    <h3 id="previaTitulo" class="text-lg sm:text-xl font-black text-white mt-0.5">Embarque #—</h3>
                    <p id="previaSubtitulo" class="text-xs text-slate-300 font-medium">Placa: — · Pedidos: — · Peso: — kg</p>
                </div>
            </div>
            <button onclick="fecharModalPrevia()" class="w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <!-- AVISO DE REGRA DE PROTEÇÃO -->
        <div class="bg-indigo-50/80 border-b border-indigo-100 px-5 py-2.5 flex items-center justify-between gap-2 flex-wrap text-xs text-indigo-900 flex-shrink-0">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-shield-halved text-indigo-600 text-sm"></i>
                <span><b>Regra Nutricional Filial PR:</b> O saldo livre desconta a reserva do cliente <b>14523</b> (pedidos em aberto).</span>
            </div>
            <span id="previaAvisoReserva" class="font-bold text-indigo-700 bg-indigo-100/70 px-2 py-0.5 rounded-md">0 itens ajustados</span>
        </div>

        <!-- KPIS DA MODAL -->
        <div class="p-4 sm:p-5 grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 border-b border-slate-100 flex-shrink-0">
            <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="tc-label">Total Itens no Embarque</span>
                <div id="previaKpiTotalItens" class="text-xl font-black text-slate-800 mt-0.5">0</div>
                <span class="text-[11px] text-slate-400">Cadastrados no ERP</span>
            </div>
            <div class="bg-white p-3 rounded-2xl border border-sky-200/80 shadow-sm">
                <span class="tc-label text-sky-700">Qtd a Solicitar Matriz</span>
                <div id="previaKpiTotalSolicitar" class="text-xl font-black text-sky-600 mt-0.5">0</div>
                <span id="previaKpiItensFalta" class="text-[11px] text-sky-700/80 font-bold">0 itens com necessidade</span>
            </div>
            <div class="bg-white p-3 rounded-2xl border border-slate-200/80 shadow-sm">
                <span class="tc-label">Normais (Venda Comercial)</span>
                <div id="previaKpiNormais" class="text-xl font-black text-slate-800 mt-0.5">0 un</div>
                <span id="previaKpiNormaisItens" class="text-[11px] text-slate-400">0 item(ns)</span>
            </div>
            <div class="bg-white p-3 rounded-2xl border border-purple-200/80 shadow-sm">
                <span class="tc-label text-purple-700">Remessa (Marca 60)</span>
                <div id="previaKpiRemessas" class="text-xl font-black text-purple-700 mt-0.5">0 un</div>
                <span id="previaKpiRemessasItens" class="text-[11px] text-purple-600 font-bold">Amostras / Apoio</span>
            </div>
        </div>

        <!-- FILTROS INTERNOS DA MODAL -->
        <div class="px-5 py-3 border-b border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 bg-white flex-shrink-0">
            <div class="flex items-center gap-1.5 overflow-x-auto w-full sm:w-auto p-1 bg-slate-100 rounded-xl">
                <button type="button" onclick="filtrarItensPrevia('todos')" id="tabPrevia_todos" class="tc-tab-btn active text-xs !py-1 px-3 whitespace-nowrap">Todos (<span id="countPrevia_todos">0</span>)</button>
                <button type="button" onclick="filtrarItensPrevia('solicitar')" id="tabPrevia_solicitar" class="tc-tab-btn text-xs !py-1 px-3 whitespace-nowrap">Apenas a Solicitar (<span id="countPrevia_solicitar">0</span>)</button>
                <button type="button" onclick="filtrarItensPrevia('normais')" id="tabPrevia_normais" class="tc-tab-btn text-xs !py-1 px-3 whitespace-nowrap">Normais (<span id="countPrevia_normais">0</span>)</button>
                <button type="button" onclick="filtrarItensPrevia('remessa')" id="tabPrevia_remessa" class="tc-tab-btn text-xs !py-1 px-3 whitespace-nowrap">Remessas (<span id="countPrevia_remessa">0</span>)</button>
            </div>
            <div class="relative w-full sm:w-64">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input id="buscaItemPrevia" type="search" placeholder="Buscar por descrição ou código..." class="tc-input !pl-8 !py-1.5 text-xs">
            </div>
        </div>

        <!-- TABELA DE ITENS DA MODAL -->
        <div class="flex-1 overflow-y-auto p-0 min-h-[220px]">
            <table class="w-full text-left tc-table">
                <thead class="sticky top-0 z-10 shadow-sm">
                    <tr>
                        <th class="w-12">#</th>
                        <th>Item / Referência</th>
                        <th>Tipo</th>
                        <th class="text-right">Qtd Embarque</th>
                        <th class="text-right">Estoque PR</th>
                        <th class="text-right">Reserva 14523</th>
                        <th class="text-right">Estoque Livre</th>
                        <th class="text-right bg-sky-50/70 text-sky-900 font-black">Qtd Solicitar</th>
                    </tr>
                </thead>
                <tbody id="previaCorpoTabela" class="divide-y divide-slate-100">
                    <!-- Gerado dinamicamente -->
                </tbody>
            </table>
        </div>

        <!-- FOOTER DA MODAL -->
        <div class="p-4 sm:p-5 bg-slate-50 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 flex-shrink-0">
            <div class="text-xs text-slate-500 text-center sm:text-left">
                * Valores calculados com base no saldo físico atual da Filial 6 e pedidos em aberto do cliente #14523.
            </div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <button onclick="copiarResumoPrevia()" class="tc-btn-secondary text-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none">
                    <i class="fa-solid fa-copy"></i> Copiar Resumo
                </button>
                <button id="btnGerarSolicitacaoPrevia" onclick="gerarSolicitacaoPreviaModal()" class="tc-btn-primary text-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none !bg-emerald-600 hover:!bg-emerald-700 font-black shadow-md shadow-emerald-600/25">
                    <i class="fa-solid fa-file-circle-plus text-sm"></i> Gerar Solicitação no ERP
                </button>
                <button onclick="fecharModalPrevia()" class="tc-btn-secondary text-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none">
                    Fechar
                </button>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
