<?php
$pageTitle = 'Carregamento Nutricional | Coletor Oficial';
$moduleJs = 'carregamento.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/carregamento.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? '' ?>">
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
            <span class="text-sm font-bold modulo-nome">CARREGAMENTO</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>

<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- ====================================================================== -->
    <!-- HEADER DESKTOP -->
    <!-- ====================================================================== -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-truck-loading text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">CARREGAMENTO</h2>
                <span id="label-embarque" class="text-xs text-slate-400 font-medium">NF PENDENTE...</span>
            </div>
        </div>
      <div class="text-right hidden sm:block">
    <span id="contagem-itens-header" class="block text-xl font-black text-blue-600 tracking-tighter">0/0 ITENS</span>
    <div class="clock font-mono text-xl lg:text-2xl font-black" id="relogio">00:00:00</div>
    <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
</div>
    </div>

    <!-- Seleção de Carga -->
    <div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 mb-6 card-selection">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Selecione a Carga</label>
        
        <div id="btnAbrirSelecao" onclick="toggleMenuEmbarques()" class="w-full p-4 rounded-xl border-2 border-slate-200 font-bold bg-slate-50 flex justify-between items-center text-slate-700 cursor-pointer hover:border-blue-500 transition-colors">
            <span id="textoSelecao">Toque para selecionar...</span>
            <i class="fa-solid fa-chevron-down"></i>
        </div>
        
        <div id="menuEmbarques" class="mt-2 border border-slate-100 rounded-xl overflow-hidden shadow-lg bg-white" style="display: none;"></div>
        <select id="selEmbarque" style="display: none;"></select>
    </div>

    <!-- Área Operacional -->
    <div id="areaOperacional" style="display:none;">
        
        <div class="grid grid-cols-3 gap-3 mb-6 resumo-embarque">
            <div class="bg-white p-3 rounded-2xl border border-slate-100 text-center shadow-sm resumo-card">
                <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">Carregados</p>
                <span id="resumo-total-itens" class="text-sm font-bold text-slate-700">0/0</span>
            </div>
            <div class="bg-white p-3 rounded-2xl border border-slate-100 text-center shadow-sm resumo-card">
                <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">Peso Bruto</p>
                <span id="resumo-peso" class="text-sm font-bold text-slate-700">0kg</span>
            </div>
            <div class="bg-white p-3 rounded-2xl border border-slate-100 text-center shadow-sm resumo-card">
                <p class="text-[10px] uppercase font-bold text-slate-400 mb-1">Pedidos</p>
                <span id="resumo-pedidos" class="text-sm font-bold text-slate-700">0</span>
            </div>
        </div>

        <div class="flex gap-2 mb-6 toggle-container">
            <button id="btnASC" class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-700 text-white shadow-md toggle-btn active" onclick="alterarOrdem('ASC')">
                <i class="fa-solid fa-arrow-down-short-wide"></i> PADRÃO
            </button>
            <button id="btnDESC" class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-200 text-slate-600 hover:bg-slate-300 transition-colors toggle-btn" onclick="alterarOrdem('DESC')">
                <i class="fa-solid fa-arrow-up-wide-short"></i> INVERTER
            </button>
        </div>

        <div id="reader" style="display:none;" class="rounded-2xl overflow-hidden border-4 border-slate-700 mb-4 shadow-2xl bg-black"></div>
        <!-- Seleção de Doca -->
<div class="bg-white p-4 rounded-2xl shadow-sm border border-slate-100 mb-4" id="selecaoDoca" style="display:none;">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
        <i class="fa-solid fa-warehouse mr-1"></i> Doca de Carregamento
    </label>
    <div class="flex gap-3">
        <button onclick="selecionarDoca('DOCA 1')" id="btnDoca1" 
                class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-100 text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-all doca-btn">
            🏭 DOCA 1
        </button>
        <button onclick="selecionarDoca('DOCA 2')" id="btnDoca2" 
                class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-100 text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-all doca-btn">
            🏭 DOCA 2
        </button>
        <button onclick="selecionarDoca('DOCA 3')" id="btnDoca3" 
                class="flex-1 py-3 rounded-xl font-bold text-sm bg-slate-100 text-slate-600 hover:bg-blue-50 hover:text-blue-600 transition-all doca-btn">
            🏭 DOCA 3
        </button>
    </div>
    <div id="docaSelecionada" class="mt-3 text-center text-sm font-bold text-blue-600 hidden">
        ✅ Doca selecionada: <span id="nomeDocaSelecionada"></span>
    </div>
</div>

        <div class="sticky top-20 z-40 pb-4 scan-container">
            <div class="relative group">
                <input type="text" id="barcode-input" 
                       class="w-full bg-white border-2 border-slate-200 rounded-2xl py-4 pl-12 pr-14 text-lg font-bold focus:border-slate-700 focus:ring-4 focus:ring-slate-700/10 transition-all shadow-lg text-slate-800"
                       placeholder="Bipe para carregar..." 
                       inputmode="numeric" 
                       autocomplete="off"
                       readonly 
                       onclick="this.removeAttribute('readonly'); this.focus();">
                <i class="fa-solid fa-expand absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-slate-700 transition-colors"></i>
                <button onclick="toggleCamera()" class="absolute right-2 top-1/2 -translate-y-1/2 w-10 h-10 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-700 hover:text-white transition-all flex items-center justify-center btn-cam">
                    <i class="fa-solid fa-camera"></i>
                </button>
            </div>
        </div>

        <div id="container-finalizar" style="display:none;" class="mb-6">
            <button onclick="finalizarCargaOficial()" class="w-full bg-slate-700 text-white py-4 rounded-2xl font-bold shadow-xl hover:bg-slate-800 transition-all flex items-center justify-center gap-3 active:scale-95">
                <i class="fa-solid fa-truck-fast text-xl"></i> CONCLUIR CARREGAMENTO
            </button>
        </div>

        <div id="listaItens" class="space-y-1 pb-24"></div>
    </div>
</div>

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

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>