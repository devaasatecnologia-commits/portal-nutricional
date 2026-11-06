<?php
$pageTitle = 'Plano de Ação 5W2H & PDI | Nutricional';
$moduleJs = 'plano-acao-chapd.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
    :root {
        --pa-font: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif;
    }
    body {
        font-family: var(--pa-font);
        background: #f1f5f9;
        color: #0f172a;
    }
    .pa-hero {
        background: linear-gradient(135deg, #881337 0%, #be123c 55%, #e11d48 100%);
        position: relative;
        overflow: hidden;
    }
    .pa-hero::before {
        content: "";
        position: absolute;
        right: -60px;
        top: -60px;
        width: 240px;
        height: 240px;
        border-radius: 9999px;
        background: radial-gradient(circle, rgba(255,255,255,0.14) 0%, transparent 70%);
        pointer-events: none;
    }
    .pa-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        box-shadow: 0 4px 20px -4px rgba(15, 23, 42, 0.05);
    }
    .pa-tabs {
        display: flex;
        gap: 0.5rem;
        overflow-x: auto;
        padding: 0.35rem;
        background: #e2e8f0;
        border-radius: 1rem;
    }
    .pa-tab {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.65rem 1.15rem;
        border-radius: 0.75rem;
        font-size: 0.78rem;
        font-weight: 800;
        color: #475569;
        white-space: nowrap;
        transition: all 0.2s;
        cursor: pointer;
        border: none;
        background: transparent;
    }
    .pa-tab:hover { color: #0f172a; background: rgba(255,255,255,0.5); }
    .pa-tab.on {
        background: #ffffff;
        color: #be123c;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
    }
    .pa-label {
        display: block;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
    }
    .pa-input {
        width: 100%;
        margin-top: 0.25rem;
        padding: 0.6rem 0.85rem;
        border-radius: 0.75rem;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        font-size: 0.82rem;
        font-weight: 600;
        color: #0f172a;
        transition: all 0.15s;
    }
    .pa-input:focus {
        outline: none;
        border-color: #e11d48;
        background: #ffffff;
        box-shadow: 0 0 0 3px rgba(225, 29, 72, 0.12);
    }
    /* Cores fiéis à legenda da planilha PLANO DE AÇÃO */
    .st-dentro-prazo { background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc; }
    .st-concluida { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .st-atraso { background: #fef3c7; color: #b45309; border: 1px solid #fcd34d; }
    .st-comprometido { background: #ffe4e6; color: #be123c; border: 1px solid #fda4af; }

    .ev-P { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .ev-OK { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
    .ev-NOK { background: #ffe4e6; color: #be123c; border: 1px solid #fda4af; }

    @media print {
        @page { size: A4 landscape; margin: 8mm; }
        body { background: #fff !important; }
        .no-print, header, nav, .pa-hero, .pa-tabs { display: none !important; }
        #painelVisaoGeral { display: none !important; }
        #painelEditor5W2H { display: block !important; }
        .pa-card { border: 1px solid #94a3b8 !important; box-shadow: none !important; }
    }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-10">

    <!-- HERO HEADER -->
    <div class="pa-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5 no-print">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/modules/avaliacao-chapd.php" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" title="Voltar para Avaliação C.H.A.P.D">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-white/20 text-white backdrop-blur-sm">
                            <i class="fa-solid fa-list-check mr-1"></i> Metodologia 5W2H • PDI
                        </span>
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold bg-rose-400/30 text-rose-100 border border-rose-300/30">
                            <i class="fa-solid fa-award mr-1"></i> Integrado ao C.H.A.P.D
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1.5">Plano de Ação & Desenvolvimento Individual (PDI)</h1>
                    <p class="text-xs sm:text-sm text-rose-100 opacity-90 mt-0.5">
                        Captura automática de avaliações &lt; 85 pontos ou indicadas pelo avaliador • Estrutura 5W2H Oficial
                    </p>
                </div>
            </div>

            <!-- ATALHOS HEADER -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="abrirNovoPlanoAvulso()" class="bg-white text-rose-900 hover:bg-rose-50 font-extrabold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm cursor-pointer">
                    <i class="fa-solid fa-circle-plus text-rose-600"></i> Novo Plano de Ação 5W2H
                </button>
                <a href="<?= $appBase ?>/portal/modules/avaliacao-chapd.php" class="bg-rose-950/50 hover:bg-rose-950/70 text-white border border-white/20 font-extrabold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 no-underline">
                    <i class="fa-solid fa-chart-pie"></i> Dashboard C.H.A.P.D
                </a>
            </div>
        </div>
    </div>

    <!-- BARRA DE LEGENDA / KPIS OFICIAIS DA PLANILHA PLANO DE AÇÃO -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-5 no-print">
        <!-- Capturados Aguardando Plano -->
        <div onclick="alternarAbaPa('visao')" class="pa-card p-4 bg-gradient-to-br from-rose-50 to-orange-50 border-rose-200 cursor-pointer transition hover:shadow-md">
            <div class="flex items-center justify-between text-rose-700 mb-1">
                <span class="text-[10px] font-black uppercase tracking-wider">Capturados p/ PDI</span>
                <i class="fa-solid fa-user-clock text-sm"></i>
            </div>
            <div id="kpiPaPendentesSemPlano" class="text-2xl font-black text-rose-800">0</div>
            <div class="text-[11px] text-rose-600 font-bold mt-0.5">aguardando plano 5W2H</div>
        </div>

        <!-- DENTRO DO PRAZO -->
        <div class="pa-card p-4 border-l-4 border-l-sky-500">
            <div class="flex items-center justify-between text-sky-700 mb-1">
                <span class="text-[10px] font-black uppercase tracking-wider">Dentro do Prazo</span>
                <i class="fa-solid fa-clock text-sm"></i>
            </div>
            <div id="kpiPaDentroPrazo" class="text-2xl font-black text-slate-800">0</div>
            <div class="text-[11px] text-slate-500 font-semibold mt-0.5">ações em andamento</div>
        </div>

        <!-- AÇÃO CONCLUÍDA -->
        <div class="pa-card p-4 border-l-4 border-l-emerald-500">
            <div class="flex items-center justify-between text-emerald-700 mb-1">
                <span class="text-[10px] font-black uppercase tracking-wider">Ação Concluída</span>
                <i class="fa-solid fa-circle-check text-sm"></i>
            </div>
            <div id="kpiPaConcluidas" class="text-2xl font-black text-slate-800">0</div>
            <div class="text-[11px] text-emerald-600 font-bold mt-0.5">etapas finalizadas</div>
        </div>

        <!-- ATRASO RECUPERÁVEL -->
        <div class="pa-card p-4 border-l-4 border-l-amber-500">
            <div class="flex items-center justify-between text-amber-700 mb-1">
                <span class="text-[10px] font-black uppercase tracking-wider">Atraso Recuperável</span>
                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
            </div>
            <div id="kpiPaAtraso" class="text-2xl font-black text-slate-800">0</div>
            <div class="text-[11px] text-amber-700 font-semibold mt-0.5">atenção ao prazo</div>
        </div>

        <!-- CRONOGRAMA COMPROMETIDO -->
        <div class="pa-card p-4 border-l-4 border-l-rose-600 col-span-2 sm:col-span-1">
            <div class="flex items-center justify-between text-rose-700 mb-1">
                <span class="text-[10px] font-black uppercase tracking-wider">Comprometido</span>
                <i class="fa-solid fa-circle-exclamation text-sm"></i>
            </div>
            <div id="kpiPaComprometido" class="text-2xl font-black text-slate-800">0</div>
            <div class="text-[11px] text-rose-600 font-bold mt-0.5">cronograma crítico</div>
        </div>
    </div>

    <!-- ABAS DE NAVEGAÇÃO -->
    <div class="pa-tabs mb-5 no-print">
        <button type="button" id="tabPaVisao" onclick="alternarAbaPa('visao')" class="pa-tab on">
            <i class="fa-solid fa-users-viewfinder"></i>
            <span>Fila de Captura PDI & Planos Registrados</span>
        </button>
        <button type="button" id="tabPaEditor" onclick="alternarAbaPa('editor')" class="pa-tab">
            <i class="fa-solid fa-table-list"></i>
            <span>Planilha 5W2H (Editor Plano de Ação)</span>
        </button>
    </div>

    <!-- =================================================================== -->
    <!-- ABA 1: FILA DE CAPTURA DE COLABORADORES (PDI) + LISTA DE PLANOS     -->
    <!-- =================================================================== -->
    <div id="painelVisaoGeral" class="space-y-5">

        <!-- SEÇÃO 1: CAPTURA DE COLABORADORES QUE NECESSITAM DE PDI (< 85 OU INDICADOS) -->
        <div class="pa-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-bullseye"></i>
                        </span>
                        Colaboradores Capturados para PDI / Plano de Ação
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">
                        Colaboradores com nota menor que 85 pontos (Conjunto E &amp; F) ou encaminhados diretamente pelo avaliador no fechamento C.H.A.P.D
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="filtroPdiAno" onchange="carregarTudoPlanoAcao()" class="pa-input mt-0 py-1.5 px-3 text-xs w-28">
                        <option value="">Todos Anos</option>
                        <option value="2026" selected>2026</option>
                        <option value="2025">2025</option>
                    </select>
                    <select id="filtroPdiFilial" onchange="carregarTudoPlanoAcao()" class="pa-input mt-0 py-1.5 px-3 text-xs w-36">
                        <option value="">Filiais (1, 2 e 6)</option>
                        <option value="1">Matriz 1 (SC)</option>
                        <option value="2">Filial 2 (SC)</option>
                        <option value="6">Filial 6 (PR)</option>
                    </select>
                    <button type="button" onclick="carregarTudoPlanoAcao()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-rotate"></i> Atualizar
                    </button>
                </div>
            </div>

            <div id="gridPendentesPdi" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="col-span-full text-center py-8 text-slate-400 text-xs font-semibold">
                    <i class="fa-solid fa-spinner fa-spin mr-1"></i> Buscando colaboradores que necessitam de PDI...
                </div>
            </div>
        </div>

        <!-- SEÇÃO 2: PLANOS DE AÇÃO 5W2H CADASTRADOS -->
        <div class="pa-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h2 class="text-base sm:text-lg font-black text-slate-800 flex items-center gap-2">
                        <span class="w-8 h-8 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </span>
                        Planos de Ação 5W2H em Acompanhamento
                    </h2>
                    <p class="text-xs text-slate-500 font-semibold mt-0.5">
                        Histórico e controle de execução das etapas 5W2H, prazos, observações e auditoria de evidências
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" id="filtroPlanoBusca" onkeyup="carregarPlanosAcao()" placeholder="Buscar colaborador, problema ou responsável..." class="pa-input mt-0 py-1.5 px-3 text-xs w-56">
                    <select id="filtroPlanoStatus" onchange="carregarPlanosAcao()" class="pa-input mt-0 py-1.5 px-3 text-xs w-48">
                        <option value="">Todos os Status</option>
                        <option value="DENTRO_DO_PRAZO">Dentro do Prazo</option>
                        <option value="ACAO_CONCLUIDA">Ação Concluída</option>
                        <option value="ATRASO_RECUPERAVEL">Atraso Recuperável</option>
                        <option value="CRONOGRAMA_COMPROMETIDO">Cronograma Comprometido</option>
                    </select>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider font-extrabold bg-slate-50">
                            <th class="py-3 px-3">Plano / Colaborador</th>
                            <th class="py-3 px-3">Problema &amp; Objetivo</th>
                            <th class="py-3 px-3">Responsável</th>
                            <th class="py-3 px-3 text-center">Etapas 5W2H</th>
                            <th class="py-3 px-3 text-center">Próx. Término</th>
                            <th class="py-3 px-3 text-center">Status Geral</th>
                            <th class="py-3 px-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaPlanosCorpo" class="divide-y divide-slate-100 font-semibold text-slate-700">
                        <tr><td colspan="7" class="text-center py-8 text-slate-400">Carregando planos de ação...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================================== -->
    <!-- ABA 2: EDITOR / PLANILHA OFICIAL PLANO DE AÇÃO (5W2H)               -->
    <!-- =================================================================== -->
    <div id="painelEditor5W2H" class="hidden space-y-5">

        <!-- CABEÇALHO FIEL À PLANILHA "PLANO DE AÇÃO" -->
        <div class="pa-card p-5 sm:p-6">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-200 pb-4 mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-rose-600 text-white font-black text-lg flex items-center justify-center shadow-sm">
                        5W2H
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-lg sm:text-xl font-black text-slate-900 tracking-tight" id="tituloEditorPlano">PLANO DE AÇÃO &amp; PDI</h2>
                            <span id="badgeIdPlanoEditor" class="hidden px-2.5 py-0.5 rounded-full text-xs font-black bg-slate-200 text-slate-800">#Novo</span>
                        </div>
                        <p class="text-xs text-slate-500 font-semibold">Estrutura Oficial 5W2H (O Que • Como • Onde • Quem • Início • Término • Status • Evidência)</p>
                    </div>
                </div>

                <!-- QUADRO DE LEGENDA DE STATUS IDÊNTICO AO TOPO DIREITO DA PLANILHA -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px] font-black uppercase">
                    <div class="px-2.5 py-1.5 rounded-lg st-dentro-prazo flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-sky-600"></span> Dentro do Prazo
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg st-comprometido flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-600"></span> Cronograma Comprometido
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg st-concluida flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-600"></span> Ação Concluída
                    </div>
                    <div class="px-2.5 py-1.5 rounded-lg st-atraso flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-600"></span> Atraso Recuperável
                    </div>
                </div>
            </div>

            <input type="hidden" id="paId" value="">
            <input type="hidden" id="paIdAvaliacao" value="">
            <input type="hidden" id="paOrigemTipo" value="PDI_ABAIXO_85">

            <!-- BANNER DE VÍNCULO COM AVALIAÇÃO C.H.A.P.D (QUANDO ORIGINADO DE UMA AVALIAÇÃO) -->
            <div id="bannerOrigemAvaliacao" class="hidden mb-5 p-3.5 rounded-2xl bg-rose-50/80 border border-rose-200 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <img id="bannerColabFoto" class="w-11 h-11 rounded-xl object-cover border border-rose-300 hidden" alt="Foto">
                    <div id="bannerColabIniciais" class="w-11 h-11 rounded-xl bg-rose-600 text-white font-black text-sm flex items-center justify-center">PDI</div>
                    <div>
                        <div class="text-xs font-black text-rose-950" id="bannerTextoColab">Avaliação C.H.A.P.D Vinculada</div>
                        <div class="text-[11px] font-bold text-rose-700" id="bannerSubtextoColab">Pontuação e Conceito de origem</div>
                    </div>
                </div>
                <button type="button" id="btnVerFichaOrigem" class="px-3 py-1.5 rounded-xl bg-white hover:bg-rose-100 text-rose-800 border border-rose-200 font-extrabold text-xs transition cursor-pointer no-print">
                    <i class="fa-solid fa-file-lines mr-1"></i> Ver Avaliação Origem
                </button>
            </div>

            <!-- CAMPOS DO CABEÇALHO DA PLANILHA -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Colaborador -->
                <div class="md:col-span-5">
                    <label class="pa-label">Colaborador (PDI) ou Área / Equipe</label>
                    <select id="paSelectColaborador" onchange="aoSelecionarColabNoPlano()" class="pa-input">
                        <option value="">Plano Corporativo / Geral (ou selecione o colaborador)...</option>
                    </select>
                </div>

                <!-- Cargo / Setor -->
                <div class="md:col-span-3">
                    <label class="pa-label">Cargo / Função</label>
                    <input type="text" id="paCargo" placeholder="Cargo do colaborador..." class="pa-input">
                </div>

                <!-- Setor / Filial -->
                <div class="md:col-span-2">
                    <label class="pa-label">Setor</label>
                    <input type="text" id="paSetor" placeholder="Ex: Logística, Comercial..." class="pa-input">
                </div>

                <!-- Filial -->
                <div class="md:col-span-2">
                    <label class="pa-label">Unidade / Filial</label>
                    <select id="paFilial" class="pa-input">
                        <option value="1">Matriz 1 (SC)</option>
                        <option value="2">Filial 2 (SC)</option>
                        <option value="6">Filial 6 (PR)</option>
                    </select>
                </div>

                <!-- DIAGNÓSTICO ESPECIALISTA PDI & METAS DE EVOLUÇÃO C.H.A.P.D -->
                <div class="md:col-span-12 p-4 rounded-2xl bg-slate-50 border border-slate-200/90 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-xs font-black">
                                <i class="fa-solid fa-chart-pie"></i>
                            </span>
                            <div>
                                <div class="text-xs font-black text-slate-900 uppercase tracking-wider">Diagnóstico Especialista PDI &amp; Metas de Reavaliação C.H.A.P.D</div>
                                <div class="text-[11px] text-slate-500 font-semibold">Mapeamento de gaps por pilar (máx. 30 pts/pilar) + Metodologia 70-20-10 (70% Prática • 20% Mentoria • 10% Treinamento)</div>
                            </div>
                        </div>
                        <div id="resumo702010Badge" class="flex flex-wrap items-center gap-1.5 text-[10px] font-black">
                            <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-200">70% Prática On-the-Job</span>
                            <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 border border-amber-200">20% Mentoria &amp; Feedback 1:1</span>
                            <span class="px-2.5 py-1 rounded-lg bg-sky-100 text-sky-800 border border-sky-200">10% Treinamento Formal</span>
                        </div>
                    </div>

                    <!-- Raio-X Visual dos 5 Pilares (aparece quando vinculado a avaliação) -->
                    <div id="painelRaioXPilaresPdi" class="hidden grid grid-cols-2 sm:grid-cols-5 gap-2.5 pt-1">
                        <!-- Renderizado via JS -->
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                        <div>
                            <label class="pa-label text-indigo-800">Meta de Pontuação Alvo (Próximo Ciclo)</label>
                            <input type="number" id="paMetaPontuacao" min="85" max="150" step="1" value="90" class="pa-input bg-white font-black text-indigo-900" placeholder="Ex: 90 pontos">
                        </div>
                        <div>
                            <label class="pa-label text-indigo-800">Ciclo de Reavaliação PDI</label>
                            <select id="paPrazoReavaliacao" class="pa-input bg-white font-bold">
                                <option value="30" selected>Ciclo Curto — 30 Dias (Intensivo)</option>
                                <option value="60">Ciclo Médio — 60 Dias (Consolidação)</option>
                                <option value="90">Ciclo Trimestral — 90 Dias (Estrutural)</option>
                            </select>
                        </div>
                        <div>
                            <label class="pa-label text-indigo-800">Pilares Críticos Foco (C.H.A.P.D)</label>
                            <input type="text" id="paPilaresCriticos" placeholder="Ex: C, H, D (identificados no diagnóstico)" class="pa-input bg-white font-bold">
                        </div>
                    </div>
                </div>

                <!-- PROBLEMA (Campo C3 da Planilha) -->
                <div class="md:col-span-6">
                    <label class="pa-label text-rose-700">PROBLEMA / GAPS DE COMPETÊNCIA IDENTIFICADOS <span class="text-rose-500">*</span></label>
                    <textarea id="paProblema" rows="2" placeholder="Descreva o problema identificado ou os pontos de melhoria apurados na avaliação C.H.A.P.D..." class="pa-input"></textarea>
                </div>

                <!-- OBJETIVO (Campo C4 da Planilha) -->
                <div class="md:col-span-6">
                    <label class="pa-label text-emerald-700">OBJETIVO ESPERADO / INDICADOR DE SUCESSO <span class="text-rose-500">*</span></label>
                    <textarea id="paObjetivo" rows="2" placeholder="Qual o resultado prático esperado ao concluir este Plano de Ação?..." class="pa-input"></textarea>
                </div>

                <!-- Responsável (Campo C5 da Planilha) -->
                <div class="md:col-span-4">
                    <label class="pa-label">Responsável pelo Plano <span class="text-rose-500">*</span></label>
                    <input type="text" id="paResponsavel" value="Sandro Rafo" class="pa-input">
                </div>

                <!-- Aprovador -->
                <div class="md:col-span-4">
                    <label class="pa-label">Aprovação / Gestor</label>
                    <input type="text" id="paAprovador" value="Robson Becker" class="pa-input">
                </div>

                <!-- Revisão (Campo C6 da Planilha) -->
                <div class="md:col-span-4">
                    <label class="pa-label">Revisão / Data de Referência</label>
                    <input type="text" id="paRevisao" value="<?= date('d/m/Y') ?> - Rev. 01" class="pa-input">
                </div>
            </div>
        </div>

        <!-- TABELA DE ETAPAS 5W2H (IDÊNTICA À PLANILHA EXCEL + TRILHA PDI 70-20-10) -->
        <div class="pa-card p-5 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-4 mb-3">
                <div>
                    <h3 class="text-base font-black text-slate-900 flex items-center gap-2">
                        <i class="fa-solid fa-table text-rose-600"></i>
                        Tabela de Ações e Cronograma 5W2H + Trilha PDI (70-20-10)
                    </h3>
                    <p class="text-xs text-slate-500 font-semibold">
                        Dica da planilha: Em <b>"O QUE"</b> inicie sempre com verbo de ação; em <b>"COMO"</b> descreva as etapas de realização.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2 no-print">
                    <button type="button" onclick="inserirEtapasSugeridasPdi()" class="py-2 px-3.5 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer" title="Preenche a trilha completa 70-20-10 de PDI C.H.A.P.D">
                        <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i> Gerar Trilha 70-20-10 Completa
                    </button>
                    <button type="button" onclick="adicionarLinha5W2H()" class="py-2 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs transition flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <i class="fa-solid fa-plus"></i> Adicionar Etapa (Linha)
                    </button>
                </div>
            </div>

            <!-- BIBLIOTECA ESPECIALISTA DE RECOMENDAÇÕES POR PILAR C.H.A.P.D -->
            <div class="mb-4 p-3 rounded-2xl bg-slate-50 border border-slate-200/80 flex flex-wrap items-center justify-between gap-2 no-print">
                <span class="text-[11px] font-black text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-solid fa-lightbulb text-amber-500"></i> Biblioteca Especialista PDI (Inserir ação por pilar):
                </span>
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" onclick="adicionarRecomendacaoPorPilar('C')" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-sky-50 text-sky-800 border border-sky-200 font-extrabold text-[11px] transition cursor-pointer">
                        + <b>C</b>onhecimento
                    </button>
                    <button type="button" onclick="adicionarRecomendacaoPorPilar('H')" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-indigo-50 text-indigo-800 border border-indigo-200 font-extrabold text-[11px] transition cursor-pointer">
                        + <b>H</b>abilidade
                    </button>
                    <button type="button" onclick="adicionarRecomendacaoPorPilar('A')" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-200 font-extrabold text-[11px] transition cursor-pointer">
                        + <b>A</b>titude
                    </button>
                    <button type="button" onclick="adicionarRecomendacaoPorPilar('P')" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-amber-50 text-amber-800 border border-amber-200 font-extrabold text-[11px] transition cursor-pointer">
                        + <b>P</b>ostura
                    </button>
                    <button type="button" onclick="adicionarRecomendacaoPorPilar('D')" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-rose-50 text-rose-800 border border-rose-200 font-extrabold text-[11px] transition cursor-pointer">
                        + <b>D</b>isciplina
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse min-w-[1220px]">
                    <thead>
                        <tr class="bg-slate-800 text-white uppercase tracking-wider font-extrabold text-[11px]">
                            <th class="py-3 px-2 text-center w-9 rounded-tl-xl">#</th>
                            <th class="py-3 px-2.5 w-40" title="Pilar C.H.A.P.D e Metodologia 70-20-10">
                                PILAR &amp; TRILHA PDI <span class="block text-[9px] text-slate-300 font-normal normal-case">C.H.A.P.D • 70-20-10</span>
                            </th>
                            <th class="py-3 px-3 min-w-[200px]" title="Iniciar com verbo de ação">
                                O QUE <span class="block text-[9px] text-slate-300 font-normal normal-case">Iniciar com verbo de ação</span>
                            </th>
                            <th class="py-3 px-3 min-w-[200px]" title="Etapas da realização do O QUE">
                                COMO <span class="block text-[9px] text-slate-300 font-normal normal-case">Etapas da realização do "O QUE"</span>
                            </th>
                            <th class="py-3 px-2.5 w-28" title="Local onde será realizada a ação/tarefa">
                                ONDE <span class="block text-[9px] text-slate-300 font-normal normal-case">Local/Setor</span>
                            </th>
                            <th class="py-3 px-2.5 w-36" title="Responsável pela ação/tarefa">
                                QUEM <span class="block text-[9px] text-slate-300 font-normal normal-case">Responsável</span>
                            </th>
                            <th class="py-3 px-2.5 w-32 text-center">
                                DATA DE INÍCIO
                            </th>
                            <th class="py-3 px-2.5 w-32 text-center">
                                DATA DE TÉRMINO
                            </th>
                            <th class="py-3 px-2.5 w-44 text-center">
                                STATUS
                            </th>
                            <th class="py-3 px-2.5 min-w-[140px]">
                                OBSERVAÇÃO
                            </th>
                            <th class="py-3 px-2.5 w-44 text-center">
                                EVIDÊNCIA / AUDITORIA
                            </th>
                            <th class="py-3 px-2 text-center w-10 rounded-tr-xl no-print"></th>
                        </tr>
                    </thead>
                    <tbody id="corpoTabela5W2H" class="divide-y divide-slate-200 bg-white">
                        <!-- Linhas renderizadas via JS -->
                    </tbody>
                </table>
            </div>

            <!-- ACORDO DE DESENVOLVIMENTO & FEEDBACK DO COLABORADOR (REUNIÃO 1:1) -->
            <div class="mt-5 p-4 rounded-2xl bg-emerald-50/60 border border-emerald-200">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                    <label class="text-xs font-black text-emerald-950 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-handshake text-emerald-600"></i>
                        Termo de Compromisso, Alinhamento 1:1 &amp; Feedback do Colaborador
                    </label>
                    <span id="badgeCienciaPlano" class="text-[10px] font-bold text-emerald-700">Registro de ciência e percepção do colaborador sobre o PDI</span>
                </div>
                <textarea id="paFeedbackColaborador" rows="2" class="pa-input bg-white" placeholder="Registre aqui os comentários, percepção ou compromisso assumido pelo colaborador durante a apresentação deste PDI / Plano de Ação..."></textarea>
            </div>

            <!-- RODAPÉ DE AÇÕES DO EDITOR -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-5 mt-5 border-t border-slate-200 no-print">
                <button type="button" onclick="alternarAbaPa('visao')" class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                    <i class="fa-solid fa-arrow-left"></i> Voltar para Lista
                </button>

                <div class="flex flex-wrap items-center gap-2.5">
                    <button type="button" onclick="window.print()" class="py-2.5 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-extrabold text-xs sm:text-sm transition flex items-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-print"></i> Imprimir Planilha A4
                    </button>
                    <button type="button" id="btnSalvarPlano5W2H" onclick="salvarPlano5W2H()" class="py-2.5 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs sm:text-sm transition flex items-center gap-2 shadow-md cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar Plano de Ação 5W2H
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>


