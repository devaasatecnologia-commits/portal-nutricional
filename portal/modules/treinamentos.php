<?php
$pageTitle = 'Nutricional Academy | Central de Treinamentos & Manuais';
$moduleJs = 'treinamentos.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .tr-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .font-mono-code { font-family: "JetBrains Mono", monospace; }
  .tr-hero {
    background: linear-gradient(135deg, #064e3b 0%, #047857 45%, #0f766e 100%);
    position: relative;
    overflow: hidden;
  }
  .tr-hero::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 60%;
    height: 200%;
    background: radial-gradient(circle, rgba(52, 211, 153, 0.15) 0%, transparent 70%);
    pointer-events: none;
  }
  .tr-card {
    background: #ffffff;
    border-radius: 1.5rem;
    box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.04), 0 0 0 1px rgba(226, 232, 240, 0.8);
    transition: all 0.2s ease;
  }
  .tr-sidebar-item {
    border-radius: 1rem;
    padding: 0.75rem 0.9rem;
    transition: all 0.18s ease-in-out;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid transparent;
  }
  .tr-sidebar-item:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
    transform: translateX(2px);
  }
  .tr-sidebar-item.ativo {
    background: #ecfdf5;
    border-color: #10b981;
    color: #065f46;
    box-shadow: 0 4px 14px -2px rgba(16, 185, 129, 0.15);
  }
  .tr-tab {
    padding: 0.65rem 1.15rem;
    border-radius: 0.9rem;
    font-weight: 800;
    font-size: 0.78rem;
    color: #64748b;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    border: 1px solid #f1f5f9;
    background: #f8fafc;
  }
  .tr-tab.on {
    background: #047857;
    color: #ffffff;
    border-color: #047857;
    box-shadow: 0 6px 18px rgba(4, 120, 87, 0.28);
  }
  .tr-tab:not(.on):hover {
    background: #f1f5f9;
    color: #0f172a;
    border-color: #cbd5e1;
  }
  .tr-badge {
    font-size: 0.68rem;
    font-weight: 800;
    padding: 0.3rem 0.65rem;
    border-radius: 999px;
    letter-spacing: 0.02em;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
  }
  .tr-filter-pill {
    padding: 0.35rem 0.75rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    border: 1px solid #e2e8f0;
    background: #ffffff;
    color: #64748b;
  }
  .tr-filter-pill:hover {
    background: #f1f5f9;
    color: #1e293b;
  }
  .tr-filter-pill.active {
    background: #047857;
    color: #ffffff;
    border-color: #047857;
    box-shadow: 0 2px 8px rgba(4, 120, 87, 0.2);
  }
  @media print {
    body * { visibility: hidden; }
    #areaManualConteudo, #areaManualConteudo * { visibility: visible; }
    #areaManualConteudo { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; border: none; }
    .no-print { display: none !important; }
  }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="tr-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-12">

    <!-- HERO HEADER PREMIUM -->
    <div class="tr-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-6">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-5 relative z-10">
            <div class="flex items-center gap-3.5">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="tr-badge bg-white/20 text-white backdrop-blur-sm">
                            <i class="fa-solid fa-graduation-cap"></i> Nutricional Academy
                        </span>
                        <span class="tr-badge bg-emerald-400/25 text-emerald-100 border border-emerald-300/30">
                            <i class="fa-solid fa-shield-halved text-emerald-300"></i> Base de Conhecimento Oficial
                        </span>
                        <span id="badgeControleAcesso" class="tr-badge bg-amber-400/25 text-amber-100 border border-amber-300/30 hidden">
                            <i class="fa-solid fa-user-lock"></i> Acesso Restrito aos Seus Módulos
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1.5 flex items-center gap-2.5">
                        Central de Treinamentos & Manuais
                    </h1>
                    <p class="text-xs sm:text-sm text-emerald-100 opacity-90 mt-0.5">
                        Guias práticos, dicionário de métricas, catálogo de ações e regras de negócio homologadas
                    </p>
                </div>
            </div>

            <!-- BUSCA RÁPIDA NO HEADER -->
            <div class="w-full md:w-88">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3.5 text-white/60 text-xs"></i>
                    <input type="text" id="inputBuscaManual" onkeyup="aoBuscarManual()" placeholder="Buscar manual, fórmula, botão, regra..." class="w-full bg-white/15 text-white placeholder-white/60 text-xs sm:text-sm font-semibold rounded-2xl pl-9 pr-3 py-2.5 border border-white/25 focus:outline-none focus:bg-white/25 focus:border-white transition shadow-inner">
                </div>
            </div>
        </div>
    </div>

    <!-- CORPO PRINCIPAL (SIDEBAR + LEITOR) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

        <!-- LADO ESQUERDO: LISTA DE MÓDULOS / CATÁLOGO -->
        <div class="lg:col-span-4 space-y-4">
            <div class="tr-card p-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                    <h2 class="text-xs font-black uppercase tracking-wider text-slate-500 flex items-center gap-2">
                        <i class="fa-solid fa-book-bookmark text-emerald-600"></i> Módulos Disponíveis
                    </h2>
                    <span id="totalManuaisBadge" class="tr-badge bg-emerald-50 text-emerald-700 font-extrabold border border-emerald-100">
                        Carregando...
                    </span>
                </div>

                <!-- Pílulas de Filtro por Setor -->
                <div id="pillsSetoresFiltro" class="flex flex-wrap gap-1.5 mb-3.5 pb-2 border-b border-slate-100">
                    <button type="button" onclick="filtrarPorSetor('')" class="tr-filter-pill active" data-setor="">Todos</button>
                </div>

                <!-- Lista de Itens -->
                <div id="listaManuaisSidebar" class="space-y-1 max-h-[580px] overflow-y-auto pr-1">
                    <div class="text-center py-10 text-slate-400 text-xs font-semibold">
                        <i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando seus manuais autorizados...
                    </div>
                </div>
            </div>

            <!-- Card Dica Especialista -->
            <div class="tr-card p-4 bg-gradient-to-br from-emerald-50/70 via-teal-50/40 to-slate-50 border border-emerald-100">
                <div class="flex items-center gap-2 text-emerald-800 font-extrabold text-xs mb-1">
                    <i class="fa-solid fa-circle-question text-emerald-600"></i> Acesso Direto nos Módulos
                </div>
                <p class="text-[0.72rem] text-slate-600 leading-relaxed">
                    Você pode abrir o treinamento de qualquer tela clicando no botão <b>"Treinamento & Manual"</b> presente no cabeçalho do próprio módulo.
                </p>
            </div>
        </div>

        <!-- LADO DIREITO: LEITOR DO MANUAL SELECIONADO -->
        <div class="lg:col-span-8">
            <div class="tr-card p-5 sm:p-7 relative" id="areaManualConteudo">

                <!-- TELA DE BLOQUEIO / ACESSO RESTRITO (Oculta por padrão) -->
                <div id="telaAcessoRestrito" class="hidden py-14 px-4 text-center">
                    <div class="w-16 h-16 rounded-3xl bg-rose-50 text-rose-500 flex items-center justify-center text-2xl mx-auto mb-4 border border-rose-100 shadow-sm">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <span class="tr-badge bg-rose-100 text-rose-800 font-black mb-2">ACESSO RESTRITO</span>
                    <h3 class="text-lg sm:text-xl font-black text-slate-900 mt-1">Manual Bloqueado para o seu Perfil</h3>
                    <p id="msgAcessoRestrito" class="text-xs sm:text-sm text-slate-600 max-w-md mx-auto mt-2 leading-relaxed">
                        Você não possui permissão para visualizar o treinamento deste módulo.
                    </p>
                    <div class="mt-6">
                        <button type="button" onclick="voltarParaPrimeiroManual()" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition inline-flex items-center gap-2 shadow-sm">
                            <i class="fa-solid fa-arrow-left"></i> Ver Manuais Liberados
                        </button>
                    </div>
                </div>

                <!-- CONTEÚDO DO MANUAL (Exibido quando autorizado) -->
                <div id="conteudoManualValido">

                    <!-- Cabeçalho do Manual -->
                    <div class="border-b border-slate-100 pb-5 mb-5 flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span id="manualSetorBadge" class="tr-badge bg-emerald-100 text-emerald-800 font-extrabold">
                                    Setor
                                </span>
                                <span id="manualVersaoBadge" class="tr-badge bg-slate-100 text-slate-600 font-bold">
                                    Versão 2.0
                                </span>
                                <span id="manualTempoLeituraBadge" class="tr-badge bg-teal-50 text-teal-700 border border-teal-100 font-semibold">
                                    <i class="fa-regular fa-clock"></i> 5 min de leitura
                                </span>
                                <span id="manualStatusPermissao" class="tr-badge bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">
                                    <i class="fa-solid fa-circle-check"></i> Autorizado no seu Perfil
                                </span>
                            </div>
                            <h2 id="manualTitulo" class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight">
                                Selecione um manual
                            </h2>
                            <p id="manualSubtitulo" class="text-xs sm:text-sm text-slate-500 font-semibold mt-1 leading-relaxed">
                                Clique em um dos módulos na barra lateral para abrir o guia operacional completo
                            </p>
                        </div>

                        <div class="flex items-center gap-2 flex-shrink-0 no-print self-start">
                            <a id="btnIrParaModulo" href="#" target="_blank" class="p-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs transition flex items-center gap-2 shadow-sm no-underline">
                                <span>Acessar Módulo</span>
                                <i class="fa-solid fa-arrow-up-right-from-square text-[0.7rem]"></i>
                            </a>
                            <button type="button" onclick="window.print()" class="p-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition shadow-xs" title="Imprimir Manual">
                                <i class="fa-solid fa-print"></i>
                            </button>
                        </div>
                    </div>

                    <!-- ABAS DO MANUAL -->
                    <div class="flex flex-wrap gap-2 mb-6 border-b border-slate-100 pb-3 no-print">
                        <button type="button" id="tabManualObjetivo" onclick="trocarAbaManual('objetivo')" class="tr-tab on">
                            <i class="fa-solid fa-bullseye"></i> <span>1. Objetivo & Visão Geral</span>
                        </button>
                        <button type="button" id="tabManualKpis" onclick="trocarAbaManual('kpis')" class="tr-tab">
                            <i class="fa-solid fa-chart-pie"></i> <span>2. Dicionário de KPIs</span>
                        </button>
                        <button type="button" id="tabManualAcoes" onclick="trocarAbaManual('acoes')" class="tr-tab">
                            <i class="fa-solid fa-hand-pointer"></i> <span>3. Ações & Botões</span>
                        </button>
                        <button type="button" id="tabManualRelatorios" onclick="trocarAbaManual('relatorios')" class="tr-tab">
                            <i class="fa-solid fa-table-list"></i> <span>4. Relatórios & Tabelas</span>
                        </button>
                        <button type="button" id="tabManualDuvidas" onclick="trocarAbaManual('duvidas')" class="tr-tab">
                            <i class="fa-solid fa-circle-question"></i> <span>5. Regras & FAQ</span>
                        </button>
                    </div>

                    <!-- CONTEÚDO DAS ABAS -->

                    <!-- 1. OBJETIVO & VISÃO GERAL -->
                    <div id="painelManualObjetivo" class="space-y-4 text-xs sm:text-sm text-slate-700">
                        <div class="p-4 sm:p-5 rounded-2xl bg-slate-50/80 border border-slate-200">
                            <h3 class="font-black text-slate-800 text-xs sm:text-sm uppercase tracking-wider mb-2 flex items-center gap-2">
                                <i class="fa-solid fa-circle-info text-emerald-600"></i> Finalidade & Propósito do Módulo
                            </h3>
                            <p id="manualObjetivoTexto" class="leading-relaxed text-slate-700 text-xs sm:text-sm font-medium">
                                Carregando objetivo...
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                                <h4 class="font-black text-emerald-950 text-xs mb-1 flex items-center gap-1.5">
                                    <i class="fa-solid fa-users text-emerald-600"></i> Público-Alvo do Treinamento
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Líderes de setor, operadores designados e gerência responsável pela rotina operacional deste módulo.
                                </p>
                            </div>
                            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                                <h4 class="font-black text-slate-900 text-xs mb-1 flex items-center gap-1.5">
                                    <i class="fa-solid fa-shield-halved text-slate-500"></i> Governança & Auditoria
                                </h4>
                                <p class="text-xs text-slate-600 leading-relaxed">
                                    Ações sensíveis gravam logs com ID do usuário, data e dados alterados para conformidade do ERP.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- 2. DICIONÁRIO DE KPIS & CARDS -->
                    <div id="painelManualKpis" class="hidden space-y-3">
                        <div class="text-xs font-semibold text-slate-500 mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-calculator text-emerald-600"></i> Explicação técnica dos cards e indicadores do topo da tela:
                        </div>
                        <div id="manualKpisLista" class="space-y-3">
                            <!-- JS preenche -->
                        </div>
                    </div>

                    <!-- 3. GUIA DE AÇÕES & BOTÕES -->
                    <div id="painelManualAcoes" class="hidden space-y-3">
                        <div class="text-xs font-semibold text-slate-500 mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-hand-pointer text-emerald-600"></i> Passo a passo dos fluxos, botões e formulários:
                        </div>
                        <div id="manualAcoesLista" class="space-y-3">
                            <!-- JS preenche -->
                        </div>
                    </div>

                    <!-- 4. RELATÓRIOS & TABELAS -->
                    <div id="painelManualRelatorios" class="hidden space-y-3">
                        <div class="text-xs font-semibold text-slate-500 mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-table text-emerald-600"></i> Como interpretar as tabelas, filtros e exportações:
                        </div>
                        <div id="manualRelatoriosLista" class="space-y-3">
                            <!-- JS preenche -->
                        </div>
                    </div>

                    <!-- 5. REGRAS DE NEGÓCIO & FAQ -->
                    <div id="painelManualDuvidas" class="hidden space-y-3">
                        <div class="text-xs font-semibold text-slate-500 mb-2 flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-question text-emerald-600"></i> Regras essenciais, travas do sistema e perguntas frequentes:
                        </div>
                        <div id="manualDuvidasLista" class="space-y-3">
                            <!-- JS preenche -->
                        </div>
                    </div>

                </div>

            </div>
        </div>

    </div>

</div>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>
