<?php
$pageTitle = 'Dashboard Financeiro | Nutricional';
$moduleJs = 'financeiro.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/financeiro.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

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
            <span class="text-sm font-bold modulo-nome">FINANCEIRO</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" x-data="financeiroHandler()" x-init="init()">
    
    <!-- Shimmer Loading -->
    <div id="shimmer" class="fixed top-0 left-0 w-full h-1 bg-gradient-to-r from-amber-400 via-emerald-700 to-amber-400 bg-[length:200%_100%] animate-shimmer z-50 hidden"></div>

    <!-- ====================================================================== -->
    <!-- HEADER DESKTOP -->
    <!-- ====================================================================== -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">CONTROLADORIA FINANCEIRA</h2>
                <span id="user-display" class="text-xs text-slate-400 font-medium">CARREGANDO...</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <!--span class="block text-xl font-black text-emerald-700 tracking-tighter" id="kpi-iag-quick">0.00%</span-->
            <div class="clock font-mono text-base lg:text-xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="flex flex-wrap items-end gap-4">
		<!-- Usuário (Visão) -->
<div class="flex-1 min-w-[200px]" id="container-user">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
        <i class="fa-solid fa-user mr-1"></i> Usuário (Visão)
    </label>
    <select id="global-user-filter" style="display:none;" onchange="app.changeUser(this.value)"
            class="w-full p-3 border-2 border-amber-200 bg-amber-50/50 rounded-xl text-sm font-semibold cursor-pointer hover:border-amber-400 transition-all">
    </select>
</div>

<!-- Filial -->
<div class="flex-1 min-w-[200px]" id="container-filial">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
        <i class="fa-solid fa-building mr-1"></i> Filial
    </label>
    <select id="select-filial" onchange="app.changeFilial(this.value)" 
            class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-emerald-300 transition-all">
    </select>
</div>
<!-- Gestor -->
<div class="flex-1 min-w-[200px]" id="container-gestor">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
        <i class="fa-solid fa-user-tie mr-1"></i> Gestor
    </label>
    <select id="select-gestor" onchange="app.changeGestor(this.value)" style="display:none;"
            class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-emerald-300 transition-all">
        <option value="">Selecione um gestor...</option>
    </select>
</div>
            
            <div class="w-40">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-calendar mr-1"></i> Dias Recup.
                </label>
                <input type="number" id="filtro-dias-recup" value="120" min="1" onchange="app.load()"
                       class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-bold text-amber-600">
            </div>
            
            <button onclick="app.reset()" 
                    class="px-6 py-3 bg-slate-700 text-white rounded-xl font-bold hover:bg-slate-800 transition-all flex items-center gap-2">
                <i class="fa-solid fa-rotate-left"></i> REINICIAR
            </button>
			
	
        </div>
    </div>

    <!-- Cards da Carteira -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Carteira Total</span>
            <span class="block text-2xl font-black text-slate-800" id="f-total">R$ 0,00</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Vencendo 30d</span>
            <span class="block text-2xl font-black text-emerald-600" id="f-30">R$ 0,00</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Vencendo 60d</span>
            <span class="block text-2xl font-black text-amber-600" id="f-60">R$ 0,00</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Mais de 60 Dias</span>
            <span class="block text-2xl font-black text-rose-600" id="f-90">R$ 0,00</span>
        </div>
    </div>

    <!-- KPIs Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white p-5 rounded-2xl border-l-8 border-rose-500 shadow-sm cursor-pointer hover:shadow-lg transition-all" onclick="app.showAudit('iag')">
            <label class="text-xs font-bold text-slate-400 uppercase block mb-1">Inadimplência Geral (IAG)</label>
            <span class="text-[10px] text-slate-400 block mb-2">(Total Vencido / Carteira Total)</span>
            <h3 class="text-3xl font-black text-slate-800 mb-1" id="kpi-iag">0.00%</h3>
            <div id="kpi-iag-valor" class="text-sm text-slate-500 font-semibold mb-3">R$ 0,00</div>
            <div class="text-xs font-bold text-amber-500 uppercase flex items-center gap-1">
                VER AUDITORIA <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-2xl border-l-8 border-amber-500 shadow-sm cursor-pointer hover:shadow-lg transition-all" onclick="app.showAudit('iap')">
            <label class="text-xs font-bold text-slate-400 uppercase block mb-1">Atraso Crítico (IAP)</label>
            <span class="text-[10px] text-slate-400 block mb-2">(Títulos > 30 Dias / Carteira Total)</span>
            <h3 class="text-3xl font-black text-slate-800 mb-1" id="kpi-iap">0.00%</h3>
            <div id="kpi-iap-valor" class="text-sm text-slate-500 font-semibold mb-3">R$ 0,00</div>
            <div class="text-xs font-bold text-amber-500 uppercase flex items-center gap-1">
                VER AUDITORIA <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-2xl border-l-8 border-emerald-500 shadow-sm cursor-pointer hover:shadow-lg transition-all" onclick="app.showAudit('recup')">
            <label class="text-xs font-bold text-slate-400 uppercase block mb-1">Taxa de Recuperação</label>
            <span class="text-[10px] text-slate-400 block mb-2">(Qtd Pagos / Qtd Trabalhados)</span>
            <h3 class="text-3xl font-black text-slate-800 mb-1" id="kpi-recup">0.00%</h3>
            <div id="kpi-recup-quant" class="text-sm text-slate-500 font-semibold mb-3">0 de 0 títulos</div>
            <div class="text-xs font-bold text-amber-500 uppercase flex items-center gap-1">
                VER OS 3 CENÁRIOS <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </div>
        </div>
    </div>

    <!-- Histórico -->
    <div class="bg-white p-5 rounded-2xl shadow-sm border border-slate-100 mb-6">
        <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
            <h4 class="text-sm font-bold text-slate-700 uppercase">
                <i class="fa-solid fa-chart-column mr-2"></i> HISTÓRICO DE PERFORMANCE
            </h4>
            <div class="flex gap-2">
                <select id="hist-type" onchange="app.loadHistory()" 
                        class="px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold">
                    <option value="iag_calculado" selected>IAG (%)</option>
                    <option value="iap_calculado">IAP (%)</option>
                    <option value="taxa_recuperacao">RECUPERAÇÃO (%)</option>
                </select>
                <select id="hist-day" onchange="app.loadHistory()"
                        class="px-3 py-2 border border-slate-200 rounded-lg text-xs font-bold">
                    <option value="0">DOMINGO</option>
                    <option value="1">SEGUNDA</option>
                    <option value="2">TERÇA</option>
                    <option value="3">QUARTA</option>
                    <option value="4">QUINTA</option>
                    <option value="5">SEXTA</option>
                    <option value="6">SÁBADO</option>
                </select>
            </div>
        </div>
        <div style="height: 250px; width: 100%;">
            <canvas id="chartHistory"></canvas>
        </div>
    </div>

    <!-- Breadcrumb -->
    <div id="b-path" class="inline-block px-4 py-2 bg-slate-100 rounded-xl text-xs font-bold uppercase text-slate-600 mb-4">
        PAINEL GLOBAL
				
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl border border-slate-100 overflow-hidden shadow-sm">
       <button onclick="app.gerarRelatorio()" 
        class="px-6 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all flex items-center gap-2 shadow-md">
    <i class="fa-solid fa-file-pdf text-lg"></i> 
    <span>GERAR RELATÓRIO DETALHADO</span>
</button>
	   <div class="overflow-x-auto">
            <table class="w-full">
                <thead id="t-head" class="bg-slate-50 border-b border-slate-200"></thead>
                <tbody id="t-body"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ====================================================================== -->
<!-- SCRIPT RELÓGIO -->
<!-- ====================================================================== -->
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

<style>
@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.animate-shimmer {
    animation: shimmer 2s infinite linear;
}
.badge-atraso {
    background: #fee2e2;
    color: #ef4444;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 800;
}
.badge-sucesso {
    background: #dcfce7;
    color: #10b981;
    padding: 5px 10px;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 800;
}
</style>

<script>
function financeiroHandler() {
    return {
        init() {}
    }
}
</script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>