<?php
$pageTitle = 'NUTRICIONAL | MINHAS MENSAGENS';
$moduleJs = 'minhas-mensagens.js';
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
            <span class="text-sm font-bold modulo-nome">CHAT</span>
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
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-comments text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">MINHAS MENSAGENS</h2>
                <span class="text-xs text-slate-400 font-medium">Histórico de conversas</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black text-[#375a4b]" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-6">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Total Conversas</span>
            <span class="block text-2xl font-black text-slate-800" id="totalConversas">0</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Mensagens Enviadas</span>
            <span class="block text-2xl font-black text-emerald-600" id="totalEnviadas">0</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Mensagens Recebidas</span>
            <span class="block text-2xl font-black text-blue-600" id="totalRecebidas">0</span>
        </div>
    </div>

    <!-- Lista de Conversas -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100">
            <h3 class="font-bold text-slate-700">
                <i class="fa-solid fa-history mr-2"></i>Conversas Recentes
            </h3>
        </div>
        <div id="conversasLista" class="divide-y divide-slate-100">
            <p class="text-center text-slate-400 text-sm py-8">Carregando...</p>
        </div>
    </div>
</div>

<script src="/portal/assets/js/minhas-mensagens.js?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>