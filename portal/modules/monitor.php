<?php
$pageTitle = 'NUTRICIONAL | CENTRAL DE COMANDO';
$moduleJs = 'monitor.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/assets/css/monitor.css?v=' . $version . '">
<style>
    /* ====================================================================== */
    /* MODO TV - TEMA ESCURO (preservado) */
    /* ====================================================================== */
    :root {
        --cor-primaria: #375a4b;
        --cor-secundaria: #f7be2f;
        --tv-bg: #020617;
        --tv-card: rgba(15, 23, 42, 0.7);
        --tv-border: rgba(51, 65, 85, 0.5);
    }
    
    /* Override do body para modo TV escuro */
    body { 
        background-color: var(--tv-bg) !important; 
        color: #f8fafc !important; 
    }
    
    /* Esconder elementos do portal no modo TV */
    nav.glass { display: none !important; }
    main { 
        padding: 0 !important; 
        max-width: 100% !important; 
        margin: 0 !important; 
        height: 100vh; 
        overflow: hidden; 
    }
    
    /* Esconder header mobile no modo TV (se acessado pela TV) */
    .mobile-toolbar.tv-hide,
    .mobile-spacer.tv-hide {
        display: none !important;
    }
    
    /* Efeitos de TV e Glow */
    .tv-glass { 
        background: rgba(15, 23, 42, 0.7); 
        backdrop-filter: blur(16px); 
        border: 1px solid rgba(51, 65, 85, 0.5); 
    }
    
    .text-glow { 
        text-shadow: 0 0 20px rgba(247, 190, 47, 0.4); 
    }
    
    /* Header mobile em modo escuro */
    .mobile-toolbar.tv-mode {
        background: #0f172a !important;
        border-bottom: 3px solid var(--cor-secundaria) !important;
    }
    
    /* Ajustar container do módulo */
    .monitor-wrapper {
        display: flex;
        flex-direction: column;
        height: 100vh;
        width: 100%;
        background: var(--tv-bg);
        overflow: hidden;
    }
    
    /* Custom scrollbar escura */
    .custom-scrollbar::-webkit-scrollbar { width: 12px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 8px; border: 2px solid #0f172a; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
    
    /* Responsivo */
    @media (max-width: 1024px) {
        .tv-glass:first-child {
            padding: 12px 16px;
        }
        .tv-glass h1 {
            font-size: 1.5rem;
        }
        #relogio {
            font-size: 1.5rem;
        }
    }
    /* ====================================================================== */
/* CORREÇÕES MONITOR - SETA + CORES DOS CARDS */
/* ====================================================================== */

/* 1. FORÇAR SETA VOLTAR VISÍVEL NO DESKTOP */
@media (min-width: 1024px) {
    a[href="/portal/"].hidden.lg\:flex,
    .tv-glass a[href="/portal/"].hidden.lg\:flex {
        display: flex !important;
        visibility: visible !important;
        opacity: 1 !important;
    }
}

/* 2. CORRIGIR CORES DOS CARDS GERADOS PELO JS */
.card-emb {
    background: #ffffff !important;
    color: #1e293b !important;
}

.card-emb .route-title {
    color: #375a4b !important;
}

.card-emb .sub-details {
    color: #64748b !important;
}

.card-emb .prog-label {
    color: #375a4b !important;
}

.card-emb .item-box {
    background: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
}

.card-emb .item-box b,
.card-emb .item-box .fw-bold {
    color: #1e293b !important;
}

.card-emb .item-box .text-muted,
.card-emb .item-box small {
    color: #64748b !important;
}

.card-emb .border-top {
    border-color: #e2e8f0 !important;
}

.card-emb .text-primary {
    color: #375a4b !important;
}

/* 3. CORRIGIR MINI-BADGES NO RODAPÉ */
#box-sep .mini-badge,
#box-car .mini-badge {
    background: #1e293b !important;
    color: #f8fafc !important;
    border: 1px solid #334155 !important;
}

#box-car .mini-badge {
    border-color: #10b981 !important;
    color: #10b981 !important;
}

/* 4. BOTÃO VOLTAR - MELHOR VISIBILIDADE */
.tv-glass a[href="/portal/"] {
    color: #94a3b8 !important;
    border: 1px solid #475569 !important;
}

.tv-glass a[href="/portal/"]:hover {
    color: #f7be2f !important;
    border-color: #f7be2f !important;
    background: rgba(247, 190, 47, 0.1) !important;
}

/* 5. CORRIGIR FOOTER CONQUISTAS - TEXTO BRANCO */
#box-sep h3,
#box-car h3 {
    color: #cbd5e1 !important;
}

/* 6. CORRIGIR PLACEHOLDER "SEM EMBARQUES" */
#monitor-grid .text-muted {
    color: #94a3b8 !important;
}
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<!-- ====================================================================== -->
<!-- HEADER MOBILE FIXO (Modo escuro para TV) -->
<!-- ====================================================================== -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg tv-mode">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">MONITOR</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>

<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="monitor-wrapper">
    
    <!-- ====================================================================== -->
    <!-- HEADER TOPO (TV) -->
    <!-- ====================================================================== -->
    <div class="tv-glass px-4 lg:px-8 py-3 lg:py-6 flex justify-between items-center shadow-2xl z-10 border-b border-slate-800">
        <div class="flex items-center gap-3 lg:gap-6">
            <!-- Botão Voltar (desktop) -->
            <a href="/portal/" class="hidden lg:flex w-10 h-10 bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white rounded-xl items-center justify-center transition-colors mr-2 no-underline border border-slate-700" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
           
            <div>
                <h1 class="text-xl lg:text-4xl font-black text-white tracking-tight leading-none mb-1">
                    CONTROLE <span class="text-amber-400 text-glow"> SEPARAÇÃO E CARREGAMENTO</span>
                </h1>
                <h2 class="text-[10px] lg:text-xs font-bold text-slate-400 tracking-[0.4em] uppercase">Central de Monitoramento Logístico</h2>
            </div>
        </div>
        <div class="text-right">
            <div id="relogio" class="text-2xl lg:text-5xl font-['Chivo_Mono'] font-bold text-white tracking-tighter drop-shadow-lg">00:00:00</div>
            <div id="data-topo" class="text-amber-400 font-bold text-xs lg:text-sm uppercase tracking-widest mt-1">--/--/----</div>
        </div>
    </div>

    <!-- ====================================================================== -->
    <!-- GRID DE CARDS -->
    <!-- ====================================================================== -->
    <div class="flex-1 overflow-y-auto p-4 lg:p-8 custom-scrollbar">
        <div id="monitor-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 lg:gap-6"></div>
    </div>

    <!-- ====================================================================== -->
    <!-- FOOTER CONQUISTAS -->
    <!-- ====================================================================== -->
    <div class="tv-glass z-10 grid grid-cols-2 divide-x divide-slate-700 shadow-[0_-10px_30px_rgba(0,0,0,0.5)]">
        
        <div class="p-4 lg:p-6" id="box-sep">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-amber-500/20 flex items-center justify-center border border-amber-500/30">
                    <i class="fa-solid fa-box text-amber-400 text-lg"></i>
                </div>
                <h3 class="text-xs lg:text-sm font-black text-slate-300 uppercase tracking-widest">Últimas Separações Concluídas</h3>
            </div>
            <div class="flex flex-wrap gap-2" id="lista-sep">
                <span class="px-3 py-1 rounded-md bg-slate-800 text-slate-500 text-xs font-bold border border-slate-700">Aguardando...</span>
            </div>
        </div>
        
        <div class="p-4 lg:p-6" id="box-car">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-emerald-500/20 flex items-center justify-center border border-emerald-500/30">
                    <i class="fa-solid fa-truck-fast text-emerald-400 text-lg"></i>
                </div>
                <h3 class="text-xs lg:text-sm font-black text-slate-300 uppercase tracking-widest">Últimos Carregamentos Finalizados</h3>
            </div>
            <div class="flex flex-wrap gap-2" id="lista-car">
                <span class="px-3 py-1 rounded-md bg-slate-800 text-slate-500 text-xs font-bold border border-slate-700">Aguardando...</span>
            </div>
        </div>

    </div>

    <!-- ====================================================================== -->
    <!-- ANIMAÇÃO CAMINHÃO -->
    <!-- ====================================================================== -->
    <div id="pet-truck-stage" style="display:none;" class="fixed inset-0 bg-[#020617]/95 backdrop-blur-md z-[9999] flex flex-col items-center justify-center">
        
        <div id="info-box" class="absolute top-[10%] text-center z-10 animate-fade-in-down">
            <div class="inline-block bg-amber-400 text-[#020617] px-6 lg:px-8 py-2 rounded-full font-black text-lg lg:text-xl tracking-widest uppercase mb-4 shadow-[0_0_30px_rgba(251,191,36,0.5)]">
                Embarque Finalizado
            </div>
            <div id="anim-id" class="text-5xl lg:text-[8rem] font-black text-white leading-none tracking-tighter drop-shadow-2xl mb-8">#0000</div>
            
            <div class="flex flex-col lg:flex-row gap-4 lg:gap-8 justify-center text-base lg:text-xl font-bold text-slate-300 bg-slate-800/50 p-4 lg:p-6 rounded-2xl border border-slate-700 backdrop-blur-sm">
                <div class="flex items-center gap-3"><i class="fa-solid fa-route text-amber-400"></i> <span id="anim-rota">ROTA</span></div>
                <div class="hidden lg:block w-px bg-slate-600"></div>
                <div class="flex items-center gap-3"><i class="fa-solid fa-user-tie text-emerald-400"></i> <span id="anim-motorista">MOTORISTA</span></div>
                <div class="hidden lg:block w-px bg-slate-600"></div>
                <div class="flex items-center gap-3"><i class="fa-solid fa-truck text-blue-400"></i> <span id="anim-placa">PLACA</span></div>
            </div>
        </div>

        <video id="video-caminhao" class="w-full max-w-5xl rounded-3xl shadow-2xl border border-slate-800" preload="auto" muted>
            <source src="/uploads/teste2.webm" type="video/webm">
        </video>
        
    </div>

</div>

<!-- ====================================================================== -->
<!-- SCRIPT RELÓGIO (Desktop + Mobile) -->
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

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>