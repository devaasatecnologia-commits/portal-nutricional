<?php
$pageTitle = 'Conferência XML | Nutricional';
$moduleJs = 'xml.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/xml.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? 0 ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<!-- ====================================================================== -->
<!-- HEADER MOBILE FIXO -->
<!-- ====================================================================== -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">CONFERÊNCIA XML</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>

<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" style="overflow: visible !important; min-height: auto !important;">
    
    <!-- ====================================================================== -->
    <!-- HEADER DESKTOP -->
    <!-- ====================================================================== -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-slate-700 text-white rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-file-invoice text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">CONFERÊNCIA XML</h2>
                <span class="text-xs text-slate-400 font-medium">VALIDAÇÃO DE NOTAS FISCAIS</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- Layout Principal -->
    <div class="flex flex-col lg:flex-row gap-6" style="min-height: calc(100vh - 250px);">
        
        <!-- Sidebar -->
        <aside class="w-full lg:w-[380px] flex-shrink-0 bg-white border border-slate-200 p-6 rounded-2xl lg:rounded-3xl overflow-y-auto flex flex-col gap-6 shadow-sm">
            
            <!-- Brand -->
            <div class="flex items-center gap-4 pb-5 border-b-2 border-slate-100">
                <div class="w-12 h-12 bg-slate-700 text-white rounded-2xl flex items-center justify-center">
                    <i class="fa-solid fa-truck-ramp-box text-xl"></i>
                </div>
                <div>
                    <strong class="block text-slate-800 font-extrabold text-sm">Nutricional</strong>
                    <small class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Logística & Estoque</small>
                </div>
            </div>

            <!-- Filtros -->
            <div class="space-y-5">
                <!-- Filial -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                        <i class="fa-solid fa-building mr-1"></i> 1. Filial
                    </label>
                    <select id="selFilial" onchange="carregarFornecedores()" 
                    class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-slate-300 transition-all">
                    <option>Carregando...</option>
                </select>
            </div>

            <!-- Fornecedor -->
            <div>
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-truck mr-1"></i> 2. Fornecedor
                </label>
                <select id="selForn" onchange="carregarOCs()" 
                class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-slate-300 transition-all">
                <option>Aguardando...</option>
            </select>
        </div>

        <!-- Ordem de Compra -->
        <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                <i class="fa-solid fa-file-invoice mr-1"></i> 3. Ordem de Compra (OC)
            </label>
            <select id="selOC" onchange="buscarNotas()" 
            class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-slate-300 transition-all">
            <option>Aguardando...</option>
        </select>
    </div>

    <!-- Container Notas -->
    <div id="containerNotas" style="display:none;">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
            <i class="fa-solid fa-receipt mr-1"></i> 4. Selecione a(s) Nota(s) (CRM)
        </label>
        
        <div class="bg-amber-50 border-2 border-amber-200 rounded-xl p-3 mb-4 text-xs text-amber-700">
            <i class="fa fa-info-circle"></i> 
            Selecione uma ou mais notas. Os itens serão somados automaticamente.
        </div>
        
        <div id="listaNotasCRM" class="space-y-2 mb-4"></div>
        
<!-- Upload XML Manual -->
<div onclick="document.getElementById('fileXml').click()" 
     class="mt-4 p-5 border-2 border-dashed border-slate-300 rounded-2xl text-center cursor-pointer bg-slate-50 hover:border-amber-400 hover:bg-white transition-all">
    <i class="fa-solid fa-cloud-arrow-up text-2xl text-slate-400 mb-2 block"></i>
    <small class="font-extrabold text-slate-600 uppercase text-xs">Importar XML Manual (múltiplos arquivos)</small>
    <p class="text-[10px] text-slate-400 mt-1">Selecione um ou mais arquivos XML</p>
    <input type="file" id="fileXml" hidden accept=".xml" multiple onchange="importarXMLManual(this)">
</div>
</div>
</div>
</aside>

<!-- Main Content -->
<main class="flex-1 p-4 lg:p-8 overflow-y-auto bg-slate-50 rounded-2xl lg:rounded-3xl border border-slate-200">
    
    <!-- Placeholder -->
    <div id="placeholder" class="flex flex-col items-center justify-center h-full text-slate-300 py-20">
        <i class="fa-solid fa-barcode text-6xl lg:text-8xl mb-6"></i>
        <h3 class="text-lg lg:text-xl font-bold text-slate-400 text-center px-4">Selecione a OC e a Nota Fiscal ao lado para iniciar.</h3>
    </div>

    <!-- Painel Conferência -->
    <div id="painelConferencia" style="display:none;">
        
        <!-- Alerta de Auditoria -->
        <div id="auditAlert" class="hidden bg-rose-50 border-2 border-rose-200 text-rose-800 p-5 rounded-2xl mb-6 items-center gap-4 font-bold">
            <i class="fa-solid fa-triangle-exclamation text-3xl"></i>
            <div>
                <strong>ERRO CRÍTICO DE CONFORMIDADE</strong>
                <p class="text-sm font-medium mt-1">Mais de 50% dos itens divergem da OC! Revise os dados antes de prosseguir.</p>
            </div>
        </div>

        <!-- Header Conferência -->
        <div class="bg-white p-4 lg:p-6 rounded-2xl lg:rounded-3xl border border-slate-200 shadow-sm mb-6">
            <div class="flex flex-col lg:flex-row justify-between items-start gap-4">
                <div>
                    <h2 id="txt-oc-titulo" class="text-xl lg:text-2xl font-extrabold text-slate-800 mb-1">Conferência OC #----</h2>
                    <p id="txt-forn-subtitulo" class="text-slate-500 font-semibold mb-3">---</p>
                    
                    <div onclick="copiarChaveAcesso()" 
                    class="inline-flex items-center gap-3 bg-slate-100 px-4 py-2 rounded-xl border border-slate-200 cursor-pointer hover:bg-slate-200 transition-all">
                    <i class="fa-regular fa-copy text-slate-500"></i>
                    <span id="txt-chave-nf" class="text-xs font-mono font-bold text-slate-600">CHAVE: 0000...</span>
                </div>
            </div>
            
            <div class="flex gap-4">
                <div class="px-4 lg:px-6 py-3 lg:py-4 bg-slate-100 rounded-2xl text-right min-w-[140px] lg:min-w-[180px]">
                    <small class="text-xs font-bold text-slate-400 uppercase block mb-1">Total Pedido</small>
                    <strong id="val-total-oc" class="text-xl lg:text-2xl font-black text-slate-800">R$ 0,00</strong>
                </div>
                <div class="px-4 lg:px-6 py-3 lg:py-4 bg-amber-100 rounded-2xl text-right min-w-[140px] lg:min-w-[180px]">
                    <small class="text-xs font-bold text-amber-700 uppercase block mb-1">Total Nota</small>
                    <strong id="val-total-xml" class="text-xl lg:text-2xl font-black text-slate-800">R$ 0,00</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Grid Header -->
    <div class="hidden lg:grid grid-cols-[2.5fr_0.8fr_1.2fr_1.5fr_0.7fr_1fr] gap-4 px-4 py-3 bg-slate-100 rounded-xl text-xs font-bold text-slate-500 uppercase tracking-wider mb-3 no-print">
        <span>Produto / Descrição / Dados</span>
        <span class="text-center">Qtd OC</span>
        <span class="text-center">Qtd Nota</span>
        <span>Preços OC / NOTA</span>
        <span class="text-center">Ação</span>
        <span class="text-center">Status</span>
    </div>

    <!-- Corpo de Itens -->
    <div id="corpoItens" class="space-y-3 mb-6"></div>

    <!-- Actions Bar -->
    <div class="sticky bottom-0 bg-slate-50/95 backdrop-blur-sm py-4 lg:py-6 flex flex-col sm:flex-row gap-3 no-print border-t border-slate-200">
        <button id="btnDesfazerExclusao" onclick="desfazerExclusoes()" 
        class="hidden px-6 py-4 bg-slate-600 text-white rounded-2xl font-bold hover:bg-slate-700 transition-all items-center gap-2">
        <i class="fa fa-undo"></i> DESFAZER EXCLUSÕES (<span id="countExcluidos">0</span>)
    </button>
    
    <button class="flex-1 px-6 py-4 bg-slate-700 text-white rounded-2xl font-bold hover:bg-slate-800 transition-all flex items-center justify-center gap-2" 
    id="btnFinalizar" onclick="finalizarSincronizacao()">
    <i class="fa-solid fa-circle-check"></i> FINALIZAR
</button>

<button class="px-6 py-4 bg-white border-2 border-rose-500 text-rose-500 rounded-2xl font-bold hover:bg-rose-50 transition-all flex items-center justify-center gap-2" 
onclick="location.reload()">
<i class="fa-solid fa-xmark"></i> CANCELAR
</button>
</div>
</div>
</main>
</div>
</div>

<style>
/* Estilos para cards de nota e itens (gerados dinamicamente pelo JS) */
.card-nota-mini {
    padding: 16px;
    border: 2px solid #e2e8f0;
    border-radius: 14px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
    margin-bottom: 8px;
}
.card-nota-mini:hover {
    border-color: #f7be2f;
    transform: translateX(4px);
}
.card-nota-mini.active {
    border-color: #375a4b;
    background: #f0f4f2;
    border-width: 2px;
}
.item-card {
    background: white;
    padding: 16px;
    border-radius: 18px;
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    align-items: start;
    border: 1px solid #e2e8f0;
    border-left: 6px solid #cbd5e1;
    transition: all 0.2s;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.item-card .valor-xml-destaque,
.item-card .info-precos-auditoria {
    display: inline-block;
    white-space: normal;
}
@media (min-width: 1024px) {
    .item-card {
        grid-template-columns: 2.5fr 0.8fr 1.2fr 1.5fr 0.7fr 1fr;
        padding: 20px;
        align-items: center;
    }
}
.item-card.divergente {
    border-left-color: #ef4444;
    background: #fef2f2;
}

.item-card.ok {
    border-left-color: #10b981;
}
.qtd-input {
    width: 90px;
    padding: 12px;
    border-radius: 12px;
    border: 2px solid #375a4b;
    text-align: center;
    font-weight: 800;
    font-size: 1.1rem;
    color: #375a4b;
}
.valor-xml-destaque {
    color: #4338ca;
    font-weight: 800;
}

/* Mobile: cards empilhados */
@media (max-width: 1023px) {
    .item-card {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }
    .item-card > *:first-child {
        grid-column: 1 / -1;
    }
}
@media print {
    .no-print { display: none !important; }
}
</style>

<!-- Script Relógio -->
<script>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>



<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>