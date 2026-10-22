<?php
// portal/modulos/retorno.php

$pageTitle = 'Retorno de Item | Redirecionamento de Produtos';
$moduleJs = 'retorno.js';
$version = time();

$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/retorno.css?v=' . $version . '">
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
            <span class="text-sm font-bold modulo-nome">RETORNO DE ITEM</span>
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
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-arrow-right-to-bracket text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">RETORNO DE ITEM</h2>
                <span id="label-status" class="text-xs text-slate-400 font-medium">Redirecionamento de produtos</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- SCANNER / LEITOR DE CÓDIGO DE BARRAS -->
    <!-- ====================================================================== -->
    <div class="sticky top-20 z-40 pb-4 scan-container">
        <div class="relative group">
            <input type="text" id="barcode-input" 
                   class="w-full bg-white border-2 border-slate-200 rounded-2xl py-4 pl-12 pr-14 text-lg font-bold focus:border-slate-700 focus:ring-4 focus:ring-slate-700/10 transition-all shadow-lg text-slate-800"
                   placeholder="Bipe o código de barras do produto..." 
                   inputmode="none" 
                   autocomplete="off">
            <i class="fa-solid fa-expand absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-slate-700 transition-colors"></i>
            <button onclick="toggleCamera()" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-700 hover:text-white transition-all flex items-center justify-center btn-cam">
                <i class="fa-solid fa-camera"></i>
            </button>
        </div>
    </div>

    <div id="reader" style="display:none;" class="rounded-2xl overflow-hidden border-4 border-slate-700 mb-4 shadow-2xl bg-black"></div>

    <!-- RETORNO POR EMBARQUE: carregado x acerto x destinado -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 mb-6 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-5 py-3 flex items-center gap-2 text-white">
            <i class="fa-solid fa-truck-ramp-box"></i>
            <span class="font-bold text-sm">RETORNO POR EMBARQUE</span>
        </div>
        <div class="p-4">
            <div class="flex gap-2">
                <input type="number" id="embarqueBusca" inputmode="numeric" placeholder="Nº do embarque"
                       class="flex-1 border-2 border-slate-200 rounded-xl px-4 py-3 font-bold"
                       onkeydown="if(event.key==='Enter'){buscarEmbarqueRetorno();}">
                <button onclick="buscarEmbarqueRetorno()" class="px-5 bg-slate-800 text-white rounded-xl font-bold">
                    <i class="fa-solid fa-magnifying-glass"></i> BUSCAR
                </button>
            </div>
            <div id="embarqueResultado" class="mt-4 hidden"></div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- PRODUTO ENCONTRADO -->
    <!-- ====================================================================== -->
    <div id="produtoEncontrado" style="display:none;" class="bg-white rounded-3xl shadow-sm border border-slate-100 mb-6 overflow-hidden animate-in fade-in duration-200">
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-5 py-3.5 flex items-center justify-between text-white">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-box-open text-amber-400"></i>
                <span class="font-extrabold text-sm tracking-wide">PRODUTO IDENTIFICADO</span>
            </div>
            <span class="text-xs text-slate-300 font-mono" id="produtoBadgeItem">#--</span>
        </div>
        <div class="p-5">
            <div class="flex flex-col sm:flex-row gap-4 items-start sm:items-center">
                <img id="produtoFoto" src="" class="w-24 h-24 object-contain rounded-2xl bg-slate-50 p-2 border border-slate-200 shadow-sm flex-shrink-0">
                <div class="flex-1 min-w-0">
                    <div class="font-black text-lg sm:text-xl text-slate-800 leading-snug" id="produtoNome">--</div>
                    <div class="text-xs sm:text-sm text-slate-500 mt-1 flex items-center gap-2 flex-wrap">
                        <span id="produtoCodigo" class="font-mono bg-slate-100 px-2 py-0.5 rounded-md font-bold">--</span>
                        <span>•</span>
                        <span id="produtoReferencia" class="font-semibold text-slate-600">--</span>
                    </div>

                    <!-- Mini painel de pesos e totais cadastrais -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-3 pt-3 border-t border-slate-100 text-xs">
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <span class="text-slate-400 block text-[10px] uppercase font-black tracking-wider">Peso Líquido Unit.</span>
                            <b class="text-slate-700 text-sm" id="produtoPeso">0.00 kg</b>
                        </div>
                        <div class="bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <span class="text-slate-400 block text-[10px] uppercase font-black tracking-wider">Peso Bruto Unit.</span>
                            <b class="text-slate-700 text-sm" id="produtoPesoBruto">0.00 kg</b>
                        </div>
                        <div class="bg-emerald-50/70 p-2.5 rounded-xl border border-emerald-100">
                            <span class="text-emerald-700 block text-[10px] uppercase font-black tracking-wider">Total nos Estoques</span>
                            <b class="text-emerald-800 text-sm" id="produtoTotalEstoqueQtd">0 un</b>
                        </div>
                        <div class="bg-emerald-50/70 p-2.5 rounded-xl border border-emerald-100">
                            <span class="text-emerald-700 block text-[10px] uppercase font-black tracking-wider">Peso Total nos Estoques</span>
                            <b class="text-emerald-800 text-sm" id="produtoTotalEstoquePeso">0.00 kg</b>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- DESTINOS (4 CARDS COM SITUAÇÃO EM TEMPO REAL) -->
    <!-- ====================================================================== -->
    <div id="areaDestinos" style="display:none;" class="mb-6 animate-in fade-in duration-200">
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 px-5 py-3.5 flex items-center justify-between text-white">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-warehouse text-sky-400"></i>
                    <span class="font-extrabold text-sm tracking-wide">PANORAMA DOS ESTOQUES / SELECIONE O DESTINO</span>
                </div>
                <span class="text-xs text-slate-300">Clique para movimentar</span>
            </div>
            <div class="p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="cardsDestinosContainer">
                    <!-- CONTAINER -->
                    <div onclick="selecionarDestino(10117, 'CONTAINER', '#10b981')" 
                         class="destino-card bg-white rounded-2xl p-4 text-center cursor-pointer border-2 border-slate-200/80 hover:border-emerald-500 hover:shadow-md transition-all relative overflow-hidden" data-destino="10117">
                        <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center mx-auto mb-2">
                            <i class="fa-solid fa-boxes-packing text-xl"></i>
                        </div>
                        <div class="font-black text-slate-800 text-sm">CONTAINER</div>
                        <div class="text-[11px] text-slate-400">Produtos avariados</div>
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex flex-col items-center">
                            <span class="dest-qtd-badge px-2.5 py-1 rounded-xl text-xs font-black bg-slate-100 text-slate-500" id="destQtd_10117">—</span>
                            <span class="text-[11px] text-slate-500 font-semibold mt-1" id="destPeso_10117">Peso: —</span>
                            <span class="text-[10px] text-slate-400 font-mono mt-0.5" id="destPed_10117"></span>
                        </div>
                    </div>

                    <!-- MATÉRIA-PRIMA -->
                    <div onclick="selecionarDestino(16595, 'MATÉRIA-PRIMA', '#3b82f6')" 
                         class="destino-card bg-white rounded-2xl p-4 text-center cursor-pointer border-2 border-slate-200/80 hover:border-blue-500 hover:shadow-md transition-all relative overflow-hidden" data-destino="16595">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-2">
                            <i class="fa-solid fa-industry text-xl"></i>
                        </div>
                        <div class="font-black text-slate-800 text-sm">MATÉRIA-PRIMA</div>
                        <div class="text-[11px] text-slate-400">Aproveitamento produção</div>
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex flex-col items-center">
                            <span class="dest-qtd-badge px-2.5 py-1 rounded-xl text-xs font-black bg-slate-100 text-slate-500" id="destQtd_16595">—</span>
                            <span class="text-[11px] text-slate-500 font-semibold mt-1" id="destPeso_16595">Peso: —</span>
                            <span class="text-[10px] text-slate-400 font-mono mt-0.5" id="destPed_16595"></span>
                        </div>
                    </div>

                    <!-- REVENDA -->
                    <div onclick="selecionarDestino(16596, 'REVENDA', '#f59e0b')" 
                         class="destino-card bg-white rounded-2xl p-4 text-center cursor-pointer border-2 border-slate-200/80 hover:border-amber-500 hover:shadow-md transition-all relative overflow-hidden" data-destino="16596">
                        <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-2">
                            <i class="fa-solid fa-store text-xl"></i>
                        </div>
                        <div class="font-black text-slate-800 text-sm">REVENDA</div>
                        <div class="text-[11px] text-slate-400">Produtos aptos revenda</div>
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex flex-col items-center">
                            <span class="dest-qtd-badge px-2.5 py-1 rounded-xl text-xs font-black bg-slate-100 text-slate-500" id="destQtd_16596">—</span>
                            <span class="text-[11px] text-slate-500 font-semibold mt-1" id="destPeso_16596">Peso: —</span>
                            <span class="text-[10px] text-slate-400 font-mono mt-0.5" id="destPed_16596"></span>
                        </div>
                    </div>

                    <!-- TROCAS FORNECEDOR -->
                    <div onclick="selecionarDestino(16688, 'TROCAS FORNECEDOR', '#8b5cf6')" 
                         class="destino-card bg-white rounded-2xl p-4 text-center cursor-pointer border-2 border-slate-200/80 hover:border-violet-500 hover:shadow-md transition-all relative overflow-hidden" data-destino="16688">
                        <div class="w-12 h-12 bg-violet-100 text-violet-600 rounded-2xl flex items-center justify-center mx-auto mb-2">
                            <i class="fa-solid fa-right-left text-xl"></i>
                        </div>
                        <div class="font-black text-slate-800 text-sm">TROCAS FORNECEDOR</div>
                        <div class="text-[11px] text-slate-400">Devolução ao fornecedor</div>
                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex flex-col items-center">
                            <span class="dest-qtd-badge px-2.5 py-1 rounded-xl text-xs font-black bg-slate-100 text-slate-500" id="destQtd_16688">—</span>
                            <span class="text-[11px] text-slate-500 font-semibold mt-1" id="destPeso_16688">Peso: —</span>
                            <span class="text-[10px] text-slate-400 font-mono mt-0.5" id="destPed_16688"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- OPERAÇÃO E DETALHES -->
    <!-- ====================================================================== -->
    <div id="areaOperacao" style="display:none;" class="bg-white rounded-2xl shadow-sm border border-slate-100 mb-6 overflow-hidden">
        <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-5 py-3">
            <div class="flex items-center gap-2 text-white">
                <i class="fa-solid fa-arrows-spin"></i>
                <span id="tituloOperacao" class="font-bold text-sm">ESCOLHA A OPERAÇÃO</span>
            </div>
        </div>
        <div class="p-5">
            <!-- Botões de operação -->
            <div id="botoesOperacao" class="flex gap-3 mb-6">
                <button id="btnEntrada" onclick="setTipoMovimentacao('ENTRADA')" 
                        class="flex-1 py-3 rounded-xl font-bold text-sm bg-green-500 text-white transition-all">
                    <i class="fa-solid fa-arrow-right-to-bracket"></i> ENTRADA
                </button>
                <button id="btnSaida" onclick="setTipoMovimentacao('SAIDA')" 
                        class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-200 text-slate-600 transition-all">
                    <i class="fa-solid fa-arrow-right-from-bracket"></i> SAÍDA
                </button>
                <button id="btnAjuste" onclick="setTipoMovimentacao('AJUSTE')" 
                        class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-200 text-slate-600 transition-all">
                    <i class="fa-solid fa-pen"></i> AJUSTE
                </button>
            </div>

            <!-- Informações do item no estoque (se já existir) -->
            <div id="infoItemExistente" style="display:none;" class="bg-slate-50 rounded-xl p-4 mb-4">
                <div class="text-xs text-slate-500 mb-2">DADOS ATUAIS NO ESTOQUE</div>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="font-bold">Quantidade:</span> <span id="infoQuantAtual">0</span> uni</div>
                    <div><span class="font-bold">Peso Total:</span> <span id="infoPesoAtual">0</span> kg</div>
                </div>
            </div>

            <!-- Campos do formulário -->
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">QUANTIDADE *</label>
                    <input type="number" id="quantMov" step="any" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 font-bold text-lg">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">
                        PESO REAL (KG) 
                        <span class="text-slate-400 font-normal">(opcional - para avarias/perda)</span>
                    </label>
                    <input type="number" id="pesoRealMov" step="any" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">MOTIVO</label>
                    <select id="motivoMov" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                        <option value="DEVOLUCAO">📦 Devolução de Cliente</option>
                        <option value="RETORNO_CAMINHAO">🚚 Retorno do Caminhão</option>
                        <option value="AVARIA">⚠️ Produto Avariado</option>
                        <option value="PERDA">❌ Perda/Extraviado</option>
                        <option value="DESCARTE">🗑️ Descarte</option>
                        <option value="AJUSTE_INVENTARIO">📊 Ajuste de Inventário</option>
                        <option value="TRANSFERENCIA">🚚 Transferência</option>
                        <option value="OUTROS">📝 Outros</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">LOTE</label>
                        <input type="text" id="loteMov" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 mb-1">VALIDADE</label>
                        <input type="date" id="validadeMov" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3">
                    </div>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">OBSERVAÇÃO</label>
                    <textarea id="observacaoMov" rows="3" class="w-full border-2 border-slate-200 rounded-xl px-4 py-3 resize-none" placeholder="Detalhe o motivo da movimentação..."></textarea>
                </div>
                
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">FOTO (OPCIONAL)</label>
                    <input type="file" id="fotoMov" accept="image/jpeg,image/jpg,image/png,image/webp" class="w-full border-2 border-slate-200 rounded-xl px-4 py-2">
                    <div id="previewFoto" class="mt-2 hidden">
                        <img id="previewImagem" class="w-20 h-20 object-cover rounded-lg border">
                    </div>
                </div>
            </div>

            <div class="flex gap-3 mt-6">
                <button onclick="confirmarMovimentacao()" class="flex-1 bg-slate-700 text-white py-3 rounded-xl font-bold hover:bg-slate-800">
                    <i class="fa-solid fa-check"></i> CONFIRMAR
                </button>
                <button onclick="limparTudo()" class="flex-1 bg-slate-100 text-slate-600 py-3 rounded-xl font-bold hover:bg-slate-200">
                    <i class="fa-solid fa-times"></i> CANCELAR
                </button>
            </div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- MOVIMENTAÇÕES DO DIA -->
    <!-- ====================================================================== -->
    <div class="mt-8">
        <div class="flex justify-between items-center mb-4">
            <h3 class="font-bold text-slate-700">
                <i class="fa-solid fa-clock-rotate-left text-amber-500 mr-2"></i>
                MOVIMENTAÇÕES DE HOJE
            </h3>
            <button onclick="carregarMovimentacoesHoje()" class="text-xs bg-slate-100 px-3 py-1 rounded-full hover:bg-slate-200">
                <i class="fa-solid fa-rotate-right"></i> ATUALIZAR
            </button>
        </div>
        <div id="movimentacoesHoje" class="space-y-2 max-h-[300px] overflow-y-auto"></div>
        <div id="semMovimentacoes" class="text-center py-8 text-slate-400 hidden">
            <i class="fa-solid fa-inbox text-4xl mb-2 block"></i>
            Nenhuma movimentação hoje
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