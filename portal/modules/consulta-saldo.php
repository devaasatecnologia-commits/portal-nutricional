<?php
$pageTitle = 'NUTRICIONAL | ESTOQUE SALDO DISPONÍVEL';
$moduleJs = 'consulta-saldo.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? 0 ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<!-- HEADER MOBILE -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">SALDO</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- Header Desktop -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-[#375a4b]/10 text-[#375a4b] rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-scale-balanced text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">ESTOQUE SALDO DISPONÍVEL</h2>
                <span class="text-xs text-slate-400 font-medium">Análise de Estoque por Embarque</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black text-[#375a4b]" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[250px]">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-truck mr-1"></i> Selecione o Embarque
                </label>
                <select id="selectEmbarque" onchange="carregarSaldos()" 
                        class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-[#375a4b] transition-all">
                    <option value="">Selecione um embarque...</option>
                </select>
            </div>            
           
            
           <button onclick="exportarPDF()" 
        class="px-6 py-3 bg-white border-2 border-[#375a4b] text-[#375a4b] rounded-xl font-bold hover:bg-[#375a4b] hover:text-white transition-all flex items-center gap-2">
    <i class="fa-solid fa-file-pdf"></i> PDF
</button>
        </div>
    </div>

    <!-- 🆕 Card de Informações do Embarque -->
    <div class="bg-gradient-to-r from-[#375a4b] to-[#4a7a67] p-5 rounded-2xl shadow-lg mb-6" id="cardEmbarque" style="display:none;">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-truck-fast text-white text-lg"></i>
            </div>
            <h3 class="text-white font-bold text-lg">Informações do Embarque</h3>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Embarque</span>
                <span class="text-white font-black text-xl" id="infoEmbarque">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Rota</span>
                <span class="text-white font-bold text-sm" id="infoRota">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Entregador</span>
                <span class="text-white font-bold text-sm" id="infoEntregador">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Placa</span>
                <span class="text-white font-bold text-sm font-mono" id="infoPlaca">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Transportadora</span>
                <span class="text-white font-bold text-xs" id="infoTransportadora">---</span>
            </div>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-3">
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Peso Bruto</span>
                <span class="text-white font-bold" id="infoPesoBruto">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Valor Total</span>
                <span class="text-white font-bold" id="infoValorTotal">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Qt Pedidos</span>
                <span class="text-white font-bold" id="infoQtPedidos">---</span>
            </div>
            <div class="bg-white/10 rounded-xl p-3 backdrop-blur-sm">
                <span class="text-white/60 text-[10px] uppercase font-bold block mb-1">Data</span>
                <span class="text-white font-bold" id="infoData">---</span>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6" id="cardsResumo" style="display:none;">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-slate-100 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-boxes text-slate-600 text-sm"></i>
                </div>
                <span class="text-[10px] uppercase font-bold text-slate-400">Total Itens</span>
            </div>
            <span class="block text-2xl font-black text-slate-800" id="totalItens">0</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-emerald-100 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-circle-check text-emerald-600 text-sm"></i>
                </div>
                <span class="text-[10px] uppercase font-bold text-slate-400">Estoque OK</span>
            </div>
            <span class="block text-2xl font-black text-emerald-600" id="itensOk">0</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-amber-100 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-triangle-exclamation text-amber-600 text-sm"></i>
                </div>
                <span class="text-[10px] uppercase font-bold text-slate-400">Estoque Baixo</span>
            </div>
            <span class="block text-2xl font-black text-amber-600" id="itensAlerta">0</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm hover:shadow-md transition-all">
            <div class="flex items-center gap-2 mb-2">
                <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 text-sm"></i>
                </div>
                <span class="text-[10px] uppercase font-bold text-slate-400">Sem Estoque</span>
            </div>
            <span class="block text-2xl font-black text-rose-600" id="itensCritico">0</span>
        </div>
    </div>

    <!-- Tabela Principal -->
    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm" id="tabelaContainer" style="display:none;">
        <div class="overflow-x-auto">
            <table class="w-full audit-table table-to-cards">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">REF</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Produto</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Estoque</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Qtd Embarque</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Saldo</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody id="tbodySaldos">
                    <tr><td colspan="7" class="text-center py-8 text-slate-400">Selecione um embarque e clique em CONSULTAR</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Detalhes do Pedido -->
<div id="modalPedido" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalPedido()"></div>
        <div class="relative bg-white rounded-3xl max-w-4xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="bg-[#375a4b] px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-white" id="modalPedidoTitulo">
                    <i class="fa-solid fa-box mr-2"></i>Detalhes do Pedido
                </h3>
                <button onclick="fecharModalPedido()" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]" id="modalPedidoConteudo"></div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>