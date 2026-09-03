<?php
// portal/modulos/auditoria-estoque.php

$pageTitle = 'Auditoria de Estoque | Gestão e Movimentações';
$moduleJs = 'auditoria-estoque.js';
$version = time();

$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/auditoria-estoque.css?v=' . $version . '">
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? '' ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<!-- MOBILE HEADER -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">AUDITORIA</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>

<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- HEADER DESKTOP -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-clipboard-list text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">AUDITORIA DE ESTOQUE</h2>
                <span id="label-status" class="text-xs text-slate-400 font-medium">Gestão e Movimentações</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- CARDS DE ESTOQUE -->
    <!-- ====================================================================== -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div onclick="selecionarEstoque(10117, 'CONTAINER', '#10b981')" 
             class="estoque-card bg-white rounded-2xl p-5 text-center cursor-pointer border-2 border-transparent hover:border-green-500 transition-all shadow-sm hover:shadow-md" data-estoque="10117">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <i class="fa-solid fa-boxes-packing text-2xl"></i>
            </div>
            <div class="font-black text-lg text-slate-800">ESTOQUE CONTAINER</div>
            <div class="text-xs text-slate-400 mt-1">Produtos avariados / para descarte</div>
            <div id="badge-container-10117" class="mt-2"></div>
        </div>
        
        <div onclick="selecionarEstoque(16595, 'MATÉRIA-PRIMA', '#3b82f6')" 
             class="estoque-card bg-white rounded-2xl p-5 text-center cursor-pointer border-2 border-transparent hover:border-blue-500 transition-all shadow-sm hover:shadow-md" data-estoque="16595">
            <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <i class="fa-solid fa-industry text-2xl"></i>
            </div>
            <div class="font-black text-lg text-slate-800">ESTOQUE MATÉRIA-PRIMA</div>
            <div class="text-xs text-slate-400 mt-1">Para aproveitamento na produção</div>
            <div id="badge-container-16595" class="mt-2"></div>
        </div>
        
        <div onclick="selecionarEstoque(16596, 'REVENDA', '#f59e0b')" 
             class="estoque-card bg-white rounded-2xl p-5 text-center cursor-pointer border-2 border-transparent hover:border-amber-500 transition-all shadow-sm hover:shadow-md" data-estoque="16596">
            <div class="w-16 h-16 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-3">
                <i class="fa-solid fa-store text-2xl"></i>
            </div>
            <div class="font-black text-lg text-slate-800">ESTOQUE REVENDA</div>
            <div class="text-xs text-slate-400 mt-1">Produtos aptos para revenda</div>
            <div id="badge-container-16596" class="mt-2"></div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- INFO DO ESTOQUE SELECIONADO -->
    <!-- ====================================================================== -->
    <div id="infoEstoque" style="display:none;" class="bg-gradient-to-r from-slate-700 to-slate-800 rounded-2xl p-4 mb-6">
        <div class="flex justify-between items-center flex-wrap gap-3">
            <div>
                <div class="text-white/60 text-xs uppercase tracking-wider">ESTOQUE ATIVO</div>
                <div class="text-white font-black text-xl" id="estoqueNome">--</div>
                <div class="text-white/80 text-sm">Lote #<span id="loteNumero">--</span></div>
            </div>
            <div class="text-right">
                <div class="text-white/60 text-xs uppercase tracking-wider">PRODUTOS</div>
                <div class="text-white font-black text-2xl" id="totalProdutos">0</div>
            </div>
            <button onclick="atualizarEstoque()" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-xl text-sm font-bold transition-colors">
                <i class="fa-solid fa-rotate-right"></i> ATUALIZAR
            </button>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- BUSCA -->
    <!-- ====================================================================== -->
    <div id="areaBusca" style="display:none;" class="mb-6">
        <div class="relative">
            <input type="text" id="buscaProduto" 
                   class="w-full bg-white border-2 border-slate-200 rounded-2xl py-3 pl-12 pr-4 text-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 transition-all"
                   placeholder="Buscar produto por nome, código ou referência...">
            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- CONTEÚDO PRINCIPAL (Produtos + Movimentações) -->
    <!-- ====================================================================== -->
    <div id="areaConteudo" style="display:none;" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        
        <!-- PRODUTOS DO ESTOQUE -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-5 py-3">
                <div class="flex items-center gap-2 text-white">
                    <i class="fa-solid fa-boxes"></i>
                    <span class="font-bold text-sm">PRODUTOS NO ESTOQUE</span>
                </div>
            </div>
            <div id="listaProdutos" class="max-h-[500px] overflow-y-auto divide-y divide-slate-100">
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i>
                    Carregando produtos...
                </div>
            </div>
        </div>

        <!-- MOVIMENTAÇÕES RECENTES -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-5 py-3">
                <div class="flex items-center gap-2 text-white">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span class="font-bold text-sm">MOVIMENTAÇÕES RECENTES</span>
                </div>
            </div>
            <div id="listaMovimentacoes" class="max-h-[500px] overflow-y-auto divide-y divide-slate-100">
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i>
                    Carregando movimentações...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL AJUSTAR PRODUTO -->
<!-- ====================================================================== -->
<div id="modalAjustar" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="display:none;">
    <div class="bg-white rounded-2xl max-w-md w-full max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-5 py-4 flex justify-between items-center">
            <h3 class="font-black text-slate-800">
                <i class="fa-solid fa-pen text-amber-500 mr-2"></i>
                AJUSTAR PRODUTO
            </h3>
            <button onclick="fecharModalAjustar()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div class="bg-slate-50 p-3 rounded-xl">
                <div class="text-xs text-slate-500">PRODUTO</div>
                <div class="font-bold text-slate-800" id="ajustarProdutoNome">--</div>
                <div class="text-xs text-slate-400" id="ajustarProdutoCodigo">--</div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">QUANTIDADE ATUAL</label>
                <input type="text" id="ajustarQuantAtual" readonly class="w-full bg-slate-100 border-2 border-slate-200 rounded-xl px-4 py-3 font-bold">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">NOVA QUANTIDADE *</label>
                <input type="number" id="ajustarNovaQuant" step="any" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 font-bold text-lg">
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">
                    PESO REAL (KG) 
                    <span class="text-slate-400 font-normal">(opcional)</span>
                </label>
                <input type="number" id="ajustarPesoReal" step="any" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">LOTE</label>
                    <input type="text" id="ajustarLote" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">VALIDADE</label>
                    <input type="date" id="ajustarValidade" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                </div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">MOTIVO</label>
                <select id="ajustarMotivo" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                    <option value="AJUSTE_INVENTARIO">📊 Ajuste de Inventário</option>
                    <option value="AVARIA">⚠️ Produto Avariado</option>
                    <option value="PERDA">❌ Perda/Extraviado</option>
                    <option value="OUTROS">📝 Outros</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">OBSERVAÇÃO</label>
                <textarea id="ajustarObservacao" rows="3" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 resize-none"></textarea>
            </div>
            
            <div class="flex gap-3 pt-3">
                <button onclick="confirmarAjuste()" class="flex-1 bg-slate-700 text-white py-3 rounded-xl font-bold hover:bg-slate-800">
                    <i class="fa-solid fa-check"></i> CONFIRMAR
                </button>
                <button onclick="fecharModalAjustar()" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold">
                    CANCELAR
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL MOVER PRODUTO -->
<!-- ====================================================================== -->
<div id="modalMover" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="display:none;">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="border-b px-5 py-4 flex justify-between items-center">
            <h3 class="font-black text-slate-800">
                <i class="fa-solid fa-right-left text-blue-500 mr-2"></i>
                MOVER PRODUTO
            </h3>
            <button onclick="fecharModalMover()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <div class="bg-slate-50 p-3 rounded-xl">
                <div class="text-xs text-slate-500">PRODUTO</div>
                <div class="font-bold text-slate-800" id="moverProdutoNome">--</div>
                <div class="text-xs text-slate-400" id="moverProdutoCodigo">--</div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">QUANTIDADE A MOVER</label>
                <input type="number" id="moverQuantidade" step="any" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 font-bold text-lg">
                <div class="text-xs text-slate-400 mt-1">Disponível: <span id="moverDisponivel">0</span> uni</div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">DESTINO</label>
                <div class="grid grid-cols-3 gap-2">
                    <button onclick="setMoverDestino(10117, 'CONTAINER', '#10b981')" 
                            class="mover-destino-btn py-2 rounded-lg text-xs font-bold bg-slate-100 text-slate-600">
                        CONTAINER
                    </button>
                    <button onclick="setMoverDestino(16595, 'MATÉRIA-PRIMA', '#3b82f6')" 
                            class="mover-destino-btn py-2 rounded-lg text-xs font-bold bg-slate-100 text-slate-600">
                        MATÉRIA-PRIMA
                    </button>
                    <button onclick="setMoverDestino(16596, 'REVENDA', '#f59e0b')" 
                            class="mover-destino-btn py-2 rounded-lg text-xs font-bold bg-slate-100 text-slate-600">
                        REVENDA
                    </button>
                </div>
                <div id="moverDestinoSelecionado" class="text-xs text-center mt-2 text-slate-500"></div>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">MOTIVO</label>
                <select id="moverMotivo" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                    <option value="TRANSFERENCIA">🚚 Transferência</option>
                    <option value="OUTROS">📝 Outros</option>
                </select>
            </div>
            
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">OBSERVAÇÃO</label>
                <textarea id="moverObservacao" rows="2" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 resize-none"></textarea>
            </div>
            
            <div class="flex gap-3 pt-3">
                <button onclick="confirmarMover()" class="flex-1 bg-slate-700 text-white py-3 rounded-xl font-bold hover:bg-slate-800">
                    <i class="fa-solid fa-check"></i> CONFIRMAR
                </button>
                <button onclick="fecharModalMover()" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold">
                    CANCELAR
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- MODAL HISTÓRICO DO PRODUTO -->
<!-- ====================================================================== -->
<div id="modalHistorico" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4" style="display:none;">
    <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="sticky top-0 bg-white border-b px-5 py-4 flex justify-between items-center">
            <h3 class="font-black text-slate-800">
                <i class="fa-solid fa-timeline text-purple-500 mr-2"></i>
                HISTÓRICO DO PRODUTO
            </h3>
            <button onclick="fecharModalHistorico()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200">
                <i class="fa-solid fa-times"></i>
            </button>
        </div>
        <div id="historicoConteudo" class="p-5 space-y-3">
            <div class="text-center py-8 text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i>
                Carregando histórico...
            </div>
        </div>
    </div>
</div>

<script>
// ==========================================================================
// RELÓGIO
// ==========================================================================
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