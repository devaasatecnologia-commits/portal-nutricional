<?php
$pageTitle = 'Avaliação C.H.A.P.D | Nutricional';
$moduleJs = 'avaliacao-chapd.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .ch-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .ch-hero { background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #0d9488 100%); }
  .ch-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .ch-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .ch-input { width: 100%; margin-top: 0.35rem; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.75rem 1rem; font-size: 0.95rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
  .ch-input:focus { outline: none; border-color: #059669; background: #ffffff; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
  .ch-tabs { 
    display: grid; 
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.35rem; 
    padding: 0.35rem; 
    background: #ffffff; 
    border-radius: 1.25rem; 
    border: 1px solid #f1f5f9; 
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); 
    overflow: hidden;
  }
  @media (min-width: 640px) {
    .ch-tabs {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (min-width: 1024px) {
    .ch-tabs {
      grid-template-columns: repeat(6, minmax(0, 1fr));
    }
  }
  .ch-tab { 
    width: 100%;
    white-space: nowrap; 
    padding: 0.65rem 0.4rem; 
    border-radius: 1rem; 
    font-weight: 800; 
    font-size: 0.75rem; 
    color: #64748b; 
    transition: all 0.2s ease; 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    gap: 0.35rem; 
    cursor: pointer; 
  }
  @media (min-width: 1280px) {
    .ch-tab {
      font-size: 0.8rem;
      padding: 0.65rem 0.75rem;
      gap: 0.45rem;
    }
  }
  .ch-tab.on { background: linear-gradient(135deg, #047857, #10b981); color: #ffffff; box-shadow: 0 6px 16px rgba(4, 120, 87, 0.3); }
  .ch-tab:not(.on):hover { background: #f8fafc; color: #1e293b; }
  .ch-kpi { border-radius: 1.25rem; padding: 1.15rem; transition: transform 0.2s; }
  .ch-kpi:hover { transform: translateY(-2px); }
  .ch-btn { background: linear-gradient(135deg, #059669, #10b981); color: #ffffff; font-weight: 800; border-radius: 1rem; padding: 0.85rem 1.5rem; box-shadow: 0 8px 22px rgba(5, 150, 105, 0.3); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; }
  .ch-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(5, 150, 105, 0.4); }
  .ch-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .ch-chip { font-size: 0.72rem; font-weight: 800; padding: 0.35rem 0.65rem; border-radius: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
  
  /* Botões de Opção da Nota (Pergunta por Pergunta) */
  .score-btn { border-radius: 1.25rem; padding: 1.1rem 1rem; font-weight: 800; text-align: center; border: 2px solid #e2e8f0; background: #ffffff; transition: all 0.2s; cursor: pointer; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.25rem; }
  .score-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.06); }
  .score-btn.sel-10 { background: #ecfdf5; border-color: #10b981; color: #065f46; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.25); }
  .score-btn.sel-8  { background: #eff6ff; border-color: #3b82f6; color: #1e40af; box-shadow: 0 8px 20px rgba(59, 130, 246, 0.25); }
  .score-btn.sel-6  { background: #fffbeb; border-color: #f59e0b; color: #92400e; box-shadow: 0 8px 20px rgba(245, 158, 11, 0.25); }
  .score-btn.sel-4  { background: #fff7ed; border-color: #f97316; color: #9a3412; box-shadow: 0 8px 20px rgba(249, 115, 22, 0.25); }
  .score-btn.sel-2  { background: #fef2f2; border-color: #ef4444; color: #991b1b; box-shadow: 0 8px 20px rgba(239, 68, 68, 0.25); }

  /* Cards de Letras Conceito */
  .letra-card { border-radius: 1.25rem; padding: 1rem; border: 2px solid #e2e8f0; background: #ffffff; cursor: pointer; transition: all 0.2s; }
  .letra-card:hover { border-color: #cbd5e1; transform: translateY(-1px); }
  .letra-card.selecionada { border-color: #059669; background: #f0fdf4; box-shadow: 0 8px 20px rgba(5, 150, 105, 0.15); }

  /* Indicadores de Pilar CHAPD */
  .pilar-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.3rem 0.75rem; border-radius: 999px; font-weight: 800; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; }
  .pilar-C { background: #e0f2fe; color: #0369a1; }
  .pilar-H { background: #ede9fe; color: #6d28d9; }
  .pilar-A { background: #fef3c7; color: #b45309; }
  .pilar-P { background: #dcfce7; color: #15803d; }
  .pilar-D { background: #ffe4e6; color: #be123c; }

  /* Impressão Premium A4 Portrait */
  @media print {
    @page { size: A4 portrait; margin: 8mm; }
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    body { background: #ffffff !important; margin: 0 !important; padding: 0 !important; }
    header, nav, footer, aside,
    .ch-hero, .ch-tabs,
    #painelAvaliar, #painelDashboard, #painelColaboradores, #painelMatriz, #painelHistorico, #painelPerguntas,
    #modalManual, #modalPergunta, #modalFotoColab, #modalEditarPerfilColab, #modernTooltipChapd,
    .no-print { display: none !important; }
    .ch-wrap { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
    #modalFicha {
      position: static !important;
      display: block !important;
      background: #ffffff !important;
      padding: 0 !important;
      inset: auto !important;
      backdrop-filter: none !important;
    }
    #modalFicha > div {
      max-width: 100% !important;
      max-height: none !important;
      height: auto !important;
      border-radius: 0 !important;
      box-shadow: none !important;
      border: none !important;
      overflow: visible !important;
    }
    #modalFichaConteudo {
      position: static !important;
      width: 100% !important;
      max-height: none !important;
      overflow: visible !important;
      padding: 0 !important;
    }
    .print-avoid-break { break-inside: avoid; page-break-inside: avoid; }
    tr { break-inside: avoid; page-break-inside: avoid; }
  }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="ch-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-8">

    <!-- HERO HEADER -->
    <div class="ch-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="ch-chip bg-white/20 text-white backdrop-blur-sm">
                            <i class="fa-solid fa-users-gear"></i> Gestão de Pessoas & RH
                        </span>
                        <span class="ch-chip bg-emerald-400/25 text-emerald-100 border border-emerald-300/30">
                            <i class="fa-solid fa-award"></i> Avaliação C.H.A.P.D
                        </span>
                        <span class="ch-chip bg-teal-400/20 text-teal-100">
                            <i class="fa-solid fa-mobile-screen"></i> Mobile-First
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1.5">Avaliação de Desempenho Contínua</h1>
                    <p class="text-xs sm:text-sm text-emerald-100 opacity-90 mt-0.5">
                        Conhecimento • Habilidade • Atitude • Postura • Disciplina
                    </p>
                </div>
            </div>

            <!-- ATALHOS HEADER -->
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" onclick="iniciarNovaAvaliacao()" class="bg-white text-emerald-900 hover:bg-emerald-50 font-extrabold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm cursor-pointer">
                    <i class="fa-solid fa-circle-plus text-emerald-600"></i> Nova Avaliação
                </button>
                <a href="<?= $appBase ?>/portal/modules/plano-acao-chapd.php" class="bg-rose-500 hover:bg-rose-400 text-white font-extrabold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm no-underline">
                    <i class="fa-solid fa-list-check"></i>
                    <span>Plano de Ação & PDI</span>
                    <span id="badgeHeaderPdiCount" class="hidden px-2 py-0.5 text-[10px] font-black rounded-full bg-white text-rose-700">0</span>
                </a>
                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=avaliacao-chapd" class="bg-amber-400 hover:bg-amber-300 text-slate-900 font-extrabold text-xs sm:text-sm py-2.5 px-3.5 rounded-2xl transition flex items-center gap-2 shadow-sm no-underline">
                    <i class="fa-solid fa-graduation-cap"></i> Treinamento & Manual
                </a>
            </div>
        </div>
    </div>

    <!-- ABAS DE NAVEGAÇÃO -->
    <div class="ch-tabs mb-5">
        <button type="button" id="tabBtnDashboard" onclick="alternarAba('dashboard')" class="ch-tab on">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard Analytics</span>
        </button>
        <button type="button" id="tabBtnAvaliar" onclick="alternarAba('avaliar')" class="ch-tab">
            <i class="fa-solid fa-clipboard-check"></i> <span>Avaliar Colaborador</span>
        </button>
        <button type="button" id="tabBtnColaboradores" onclick="alternarAba('colaboradores')" class="ch-tab" data-tooltip="Gestão de Colaboradores: Edite cargos, setores, fotos de perfil em área separada e mantenha sincronizado com o usuário do Portal.">
            <i class="fa-solid fa-users-gear"></i> <span>Colaboradores & Equipe</span>
        </button>
        <button type="button" id="tabBtnMatriz" onclick="alternarAba('matriz')" class="ch-tab">
            <i class="fa-solid fa-table-cells"></i> <span>Matriz Anual (Jan a Dez)</span>
        </button>
        <button type="button" id="tabBtnHistorico" onclick="alternarAba('historico')" class="ch-tab">
            <i class="fa-solid fa-clock-rotate-left"></i> <span>Histórico & Fichas</span>
        </button>
        <button type="button" id="tabBtnPerguntas" onclick="alternarAba('perguntas')" class="ch-tab">
            <i class="fa-solid fa-sliders"></i> <span>Banco de Perguntas</span>
        </button>
    </div>

    <!-- =================================================== -->
    <!-- ABA 1: MODO AVALIAR (WIZARD INTERATIVO MOBILE-FIRST) -->
    <!-- =================================================== -->
    <div id="painelAvaliar" class="hidden space-y-4">

        <!-- BANNER MODO EDIÇÃO DE AVALIAÇÃO -->
        <div id="bannerModoEdicaoAvaliacao" class="hidden rounded-2xl bg-amber-50 border-2 border-amber-300 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500 text-white flex items-center justify-center text-base flex-none shadow-xs">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div>
                    <div class="text-xs font-black text-amber-950 uppercase tracking-wider" id="bannerEdicaoTitulo">
                        Editando Avaliação #--
                    </div>
                    <div class="text-[11px] font-bold text-amber-800" id="bannerEdicaoSubtitulo">
                        Avaliação ainda não finalizada. Você pode ajustar dados, respostas das perguntas ou resultado/conceito.
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
                <button type="button" onclick="irParaEtapaEdicao(1)" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-[11px] transition cursor-pointer">
                    1. Dados
                </button>
                <button type="button" onclick="irParaEtapaEdicao(2)" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-[11px] transition cursor-pointer">
                    2. Alterar Respostas
                </button>
                <button type="button" onclick="irParaEtapaEdicao(3)" class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-amber-100 text-amber-900 border border-amber-300 font-extrabold text-[11px] transition cursor-pointer">
                    3. Resultado &amp; Conceito
                </button>
                <button type="button" onclick="iniciarNovaAvaliacao()" class="px-2.5 py-1.5 rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-800 font-extrabold text-[11px] transition cursor-pointer">
                    <i class="fa-solid fa-xmark mr-1"></i> Cancelar Edição
                </button>
            </div>
        </div>

        <!-- ETAPA 1: SELEÇÃO DO COLABORADOR E PERÍODO -->
        <div id="stepSelecao" class="ch-card p-5 sm:p-7">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
                <div>
                    <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-black">1</span>
                        Identificação da Avaliação
                    </h2>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Selecione o colaborador ativo no ERP e informe o período de referência</p>
                </div>
                <span class="ch-chip bg-slate-100 text-slate-600 font-bold">Passo 1 de 3</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <!-- Colaborador -->
                <div class="lg:col-span-2">
                    <label class="ch-label">Colaborador Avaliado <span class="text-rose-500">*</span></label>
                    <select id="selColaborador" onchange="aoSelecionarColaborador()" class="ch-input">
                        <option value="">Selecione um colaborador ativo no ERP...</option>
                    </select>
                </div>

                <!-- Período: Mês e Ano -->
                <div>
                    <label class="ch-label">Mês / Ano de Referência <span class="text-rose-500">*</span></label>
                    <div class="grid grid-cols-2 gap-2 mt-1">
                        <select id="selMes" onchange="verificarAvaliacaoExistentePeriodo()" class="ch-input mt-0">
                            <!-- JS preenche 1 a 12 -->
                        </select>
                        <select id="selAno" onchange="verificarAvaliacaoExistentePeriodo()" class="ch-input mt-0">
                            <option value="2026">2026</option>
                            <option value="2025">2025</option>
                        </select>
                    </div>
                </div>

                <!-- Setor -->
                <div>
                    <label class="ch-label">Setor</label>
                    <input type="text" id="inputSetor" placeholder="Ex: Comercial, Logística, TI..." class="ch-input">
                </div>

                <!-- Célula de Trabalho -->
                <div>
                    <label class="ch-label">Célula de Trabalho</label>
                    <input type="text" id="inputCelula" placeholder="Ex: Matriz SC, Filial PR, Cargas..." class="ch-input">
                </div>

                <!-- Avaliador (Elaboração) -->
                <div>
                    <label class="ch-label">Avaliador (Elaboração)</label>
                    <input type="text" id="inputAvaliador" class="ch-input" value="Sandro Rafo">
                </div>

                <!-- Aprovador -->
                <div class="sm:col-span-2 lg:col-span-1">
                    <label class="ch-label">Aprovação Final</label>
                    <input type="text" id="inputAprovador" class="ch-input" value="Robson Becker">
                </div>
            </div>

            <!-- Card Resumo do Colaborador Selecionado -->
            <div id="cardResumoColab" class="hidden mt-5 p-4 rounded-2xl bg-emerald-50/70 border border-emerald-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="relative w-14 h-14 rounded-2xl bg-emerald-600 text-white font-black text-base flex items-center justify-center shadow-md overflow-hidden flex-none border-2 border-white" id="colabAvatar">
                        <img id="colabFotoImg" class="w-full h-full object-cover hidden" alt="Foto">
                        <span id="colabIniciais">--</span>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span id="colabNome" class="font-black text-slate-800 text-sm sm:text-base">Nome do Colaborador</span>
                            <span id="badgePerfilCustom" class="hidden text-[9px] font-extrabold px-1.5 py-0.5 rounded-md bg-indigo-100 text-indigo-800 border border-indigo-200" title="Perfil possui dados customizados na área separada">Área C.H.A.P.D</span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" onclick="abrirModalCapturaFoto()" class="p-1 px-2.5 rounded-lg bg-white hover:bg-emerald-100 text-emerald-800 border border-emerald-300 text-[11px] font-bold transition shadow-2xs flex items-center gap-1 cursor-pointer" data-tip-title="Foto do Colaborador" data-tip-desc="Bata uma foto com a câmera ou envie um arquivo para vincular ao prontuário do colaborador." data-tip-badge="FOTO / AVATAR">
                                    <i class="fa-solid fa-camera text-emerald-600"></i>
                                    <span id="colabFotoBtnTexto">Foto</span>
                                </button>
                                <button type="button" onclick="abrirModalEditarPerfilColab()" class="p-1 px-2.5 rounded-lg bg-white hover:bg-indigo-50 text-indigo-800 border border-indigo-200 text-[11px] font-bold transition shadow-2xs flex items-center gap-1 cursor-pointer" data-tip-title="Editar Dados do Colaborador" data-tip-desc="Altere nome, cargo, setor, filial e observações do colaborador em área separada preservando o ERP via JOIN." data-tip-badge="ÁREA SEPARADA / ERP">
                                    <i class="fa-solid fa-user-pen text-indigo-600"></i>
                                    <span>Editar Dados</span>
                                </button>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-semibold mt-0.5">
                            <span id="colabCargo" data-tip-title="Cargo e Função" data-tip-desc="Função registrada no ERP oficial Nutricional." data-tip-badge="ERP"><i class="fa-solid fa-briefcase text-emerald-600"></i> Cargo</span>
                            <span>•</span>
                            <span id="colabFilial" data-tip-title="Filial Operativa" data-tip-desc="Unidade de lotação do colaborador." data-tip-badge="FILIAL"><i class="fa-solid fa-building text-emerald-600"></i> Filial</span>
                            <span>•</span>
                            <span id="colabAdmissao" data-tip-title="Data de Admissão" data-tip-desc="Tempo de casa e histórico na empresa." data-tip-badge="ADMISSÃO"><i class="fa-solid fa-calendar text-emerald-600"></i> Admissão</span>
                        </div>
                    </div>
                </div>
                <div id="statusAvalAnterior" class="text-xs font-bold text-slate-500 bg-white/80 px-3 py-1.5 rounded-xl border border-emerald-100" data-tip-title="Status no Período" data-tip-desc="Indica se o colaborador já possui avaliação cadastrada para o mês e ano selecionados." data-tip-badge="STATUS">
                    Nenhuma avaliação neste mês
                </div>
            </div>

            <!-- Botão Iniciar Perguntas -->
            <div class="mt-6 flex justify-end">
                <button type="button" id="btnIniciarQuestionario" onclick="iniciarQuestionario()" class="ch-btn w-full sm:w-auto">
                    <span>Iniciar Avaliação Pergunta por Pergunta</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ETAPA 2: QUESTIONÁRIO PERGUNTA POR PERGUNTA (MOBILE-FIRST CARD) -->
        <div id="stepQuestionario" class="hidden ch-card p-5 sm:p-7">
            <!-- Barra de Progresso Superior -->
            <div class="mb-5">
                <div class="flex items-center justify-between text-xs font-extrabold mb-1.5">
                    <span id="progressoTexto" class="text-slate-600">Pergunta 1 de 14</span>
                    <span id="progressoPercentual" class="text-emerald-700 font-black">7% Concluído</span>
                </div>
                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                    <div id="barraProgresso" class="bg-gradient-to-r from-emerald-500 to-teal-500 h-full rounded-full transition-all duration-300" style="width: 7%"></div>
                </div>
            </div>

            <!-- Card da Pergunta Atual -->
            <div class="p-5 sm:p-7 rounded-3xl bg-slate-50/70 border border-slate-100 mb-6">
                <!-- Badges da Pergunta -->
                <div class="flex items-center justify-between gap-2 mb-3">
                    <span id="badgePilar" class="pilar-badge pilar-C">
                        <i class="fa-solid fa-brain"></i> <span id="badgePilarNome">Conhecimento</span>
                    </span>
                    <span id="badgeCodigo" class="text-xs font-extrabold text-slate-400">#C1</span>
                </div>

                <!-- Enunciado da Pergunta -->
                <h3 id="textoPergunta" class="text-base sm:text-xl font-black text-slate-800 leading-snug mb-2">
                    Carregando pergunta...
                </h3>
                <p id="descricaoAuxiliar" class="text-xs sm:text-sm text-slate-500 font-semibold leading-relaxed">
                    Descrição auxiliar do critério de avaliação...
                </p>
            </div>

            <!-- 5 BOTÕES DE PONTUAÇÃO (TOUCH TARGETS OTIMIZADOS PARA CELULAR) -->
            <div class="mb-6">
                <label class="ch-label block mb-2.5 text-center sm:text-left">Selecione o nível de desempenho verificado:</label>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 sm:gap-3">
                    <!-- 10 - Sempre -->
                    <button type="button" onclick="responderPergunta(10)" class="score-btn" id="btnNota10">
                        <div class="text-2xl font-black text-emerald-600">10</div>
                        <div class="text-xs font-extrabold text-slate-800">SEMPRE</div>
                        <div class="text-[0.65rem] text-slate-400 font-semibold hidden sm:block">Desempenho exemplar</div>
                    </button>

                    <!-- 08 - Frequentemente -->
                    <button type="button" onclick="responderPergunta(8)" class="score-btn" id="btnNota8">
                        <div class="text-2xl font-black text-blue-600">08</div>
                        <div class="text-xs font-extrabold text-slate-800">FREQUENTEMENTE</div>
                        <div class="text-[0.65rem] text-slate-400 font-semibold hidden sm:block">Na grande maioria</div>
                    </button>

                    <!-- 06 - Às Vezes -->
                    <button type="button" onclick="responderPergunta(6)" class="score-btn" id="btnNota6">
                        <div class="text-2xl font-black text-amber-600">06</div>
                        <div class="text-xs font-extrabold text-slate-800">ÀS VEZES</div>
                        <div class="text-[0.65rem] text-slate-400 font-semibold hidden sm:block">Comportamento irregular</div>
                    </button>

                    <!-- 04 - Raramente -->
                    <button type="button" onclick="responderPergunta(4)" class="score-btn" id="btnNota4">
                        <div class="text-2xl font-black text-orange-600">04</div>
                        <div class="text-xs font-extrabold text-slate-800">RARAMENTE</div>
                        <div class="text-[0.65rem] text-slate-400 font-semibold hidden sm:block">Quase nunca demonstra</div>
                    </button>

                    <!-- 02 - Nunca -->
                    <button type="button" onclick="responderPergunta(2)" class="score-btn col-span-2 sm:col-span-1" id="btnNota2">
                        <div class="text-2xl font-black text-rose-600">02</div>
                        <div class="text-xs font-extrabold text-slate-800">NUNCA</div>
                        <div class="text-[0.65rem] text-slate-400 font-semibold hidden sm:block">Inexistente na rotina</div>
                    </button>
                </div>
            </div>

            <!-- Observação Pontual Opcional do Item -->
            <div class="mb-6">
                <div class="flex items-center justify-between">
                    <label class="ch-label">Observação / Fato Específico Desta Pergunta (Opcional)</label>
                    <span class="text-[0.68rem] text-slate-400 font-semibold">Ex: Casos práticos observados</span>
                </div>
                <input type="text" id="obsPerguntaAtual" placeholder="Ex: Demonstrou proatividade exemplar no projeto de inventário..." class="ch-input text-xs sm:text-sm">
            </div>

            <!-- Navegação Inferior (Anterior / Próxima) -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100 gap-2">
                <button type="button" onclick="perguntaAnterior()" id="btnPerguntaAnterior" class="p-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs sm:text-sm transition flex items-center gap-2">
                    <i class="fa-solid fa-arrow-left"></i> Anterior
                </button>

                <div class="text-xs font-black text-slate-400">
                    Pontos Parciais: <span id="pontosParciais" class="text-emerald-700">0</span>
                </div>

                <button type="button" onclick="perguntaProxima()" id="btnPerguntaProxima" class="ch-btn py-2.5 px-5 text-xs sm:text-sm">
                    Próxima <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>
        </div>

        <!-- ETAPA 3: SÍNTESE, CONCEITO E PLANO DE AÇÃO -->
        <div id="stepSintese" class="hidden ch-card p-5 sm:p-7">
            <div class="border-b border-slate-100 pb-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <span class="w-7 h-7 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center text-xs font-black">3</span>
                        Resultado & Fechamento da Avaliação
                    </h2>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Pontuação final apurada, atribuição do conceito e plano de desenvolvimento</p>
                </div>
                <button type="button" onclick="voltarAoQuestionario()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition flex items-center gap-1.5 self-start sm:self-auto">
                    <i class="fa-solid fa-pen-to-square"></i> Revisar Respostas
                </button>
            </div>

            <!-- PLACAR DE PONTUAÇÃO & CLASSIFICAÇÃO COM CONJUNTOS DUPLOS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <!-- Pontos Totais -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md flex items-center justify-between" data-tip-title="Pontuação Geral C.H.A.P.D" data-tip-desc="Soma total dos pontos atribuídos nas 14 questões. Escala máxima: 140 pontos." data-tip-badge="SCORE">
                    <div>
                        <div class="text-xs uppercase font-extrabold text-emerald-100 tracking-wider">Pontuação Total</div>
                        <div class="text-3xl font-black mt-1" id="resumoPontosTotal">0 / 140</div>
                        <div class="text-xs text-emerald-100 font-semibold mt-1" id="resumoPercentual">0% de aproveitamento</div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center text-xl text-white">
                        <i class="fa-solid fa-trophy"></i>
                    </div>
                </div>

                <!-- Classificação por Pontos -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between" data-tip-title="Faixa de Desempenho" data-tip-desc="Classificação oficial conforme pontuação apurada na tabela corporativa." data-tip-badge="FAIXA">
                    <div>
                        <div class="text-xs uppercase font-extrabold text-slate-400 tracking-wider">Classificação Oficial</div>
                        <div class="text-lg font-black text-slate-800 mt-1" id="resumoClassificacao">--</div>
                    </div>
                    <div id="resumoBadgeFaixa" class="mt-2 text-xs font-bold inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-200 text-slate-700 self-start">
                        Calculando faixa...
                    </div>
                </div>

                <!-- Conjunto Duplo de Letras (A e B / C e D / E e F) -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-indigo-50 to-violet-50 border border-indigo-100 flex flex-col justify-between" data-tip-title="Conjunto Duplo de Letras" data-tip-desc="Cálculo combinado em duplas: A&B (Soma Máxima / Alta Performance), C&D (Médio Potencial) e E&F (Pontos Críticos/PDI)." data-tip-badge="METODOLOGIA">
                    <div>
                        <div class="text-xs uppercase font-extrabold text-indigo-700 tracking-wider flex items-center gap-1.5">
                            <i class="fa-solid fa-layer-group"></i> Conjunto Duplo
                        </div>
                        <div class="text-2xl font-black text-indigo-950 mt-1" id="resumoConjuntoDuplo">A &amp; B</div>
                    </div>
                    <div id="resumoConjuntoBadge" class="mt-2 text-[11px] font-black inline-flex items-center gap-1 px-2.5 py-1 rounded-xl bg-indigo-200/80 text-indigo-900 self-start">
                        Alta Performance
                    </div>
                </div>

                <!-- Conceito Letra Escolhida -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between" data-tip-title="Conceito Oficial Atribuído" data-tip-desc="Letra individual selecionada pelo gestor dentro do conjunto de desempenho." data-tip-badge="CONCEITO">
                    <div>
                        <div class="text-xs uppercase font-extrabold text-slate-400 tracking-wider">Conceito Atribuído</div>
                        <div class="text-3xl font-black text-slate-800 mt-1" id="resumoLetraConceito">--</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1 line-clamp-1" id="resumoLetraTexto">Selecione abaixo</div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-slate-200 text-slate-600 flex items-center justify-center text-xl font-black" id="resumoLetraBox">
                        -
                    </div>
                </div>
            </div>

            <!-- BARRAS DOS 5 PILARES C.H.A.P.D -->
            <div class="p-5 rounded-2xl bg-slate-50/70 border border-slate-100 mb-6">
                <div class="text-xs font-black uppercase tracking-wider text-slate-500 mb-3 flex items-center justify-between">
                    <span class="flex items-center gap-2"><i class="fa-solid fa-chart-simple text-emerald-600"></i> Desempenho por Pilar C.H.A.P.D</span>
                    <span class="text-[11px] text-slate-400 font-bold hidden sm:inline">Passe o mouse para ver critérios de cada pilar</span>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div class="p-3 bg-white rounded-xl border border-slate-100 cursor-help transition hover:border-sky-300 hover:shadow-xs" data-tip-title="Pilar C: Conhecimento" data-tip-desc="Domínio técnico dos processos, rotinas, sistemas ERP e normas da empresa. Peso: 4 questões (Máximo 40 pontos)." data-tip-badge="CONHECIMENTO (C)">
                        <div class="text-[0.68rem] font-bold text-sky-700">CONHECIMENTO (C)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalC">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraC" class="bg-sky-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100 cursor-help transition hover:border-purple-300 hover:shadow-xs" data-tip-title="Pilar H: Habilidade" data-tip-desc="Capacidade prática de executar tarefas com precisão, agilidade, destreza e qualidade. Peso: 2 questões (Máximo 20 pontos)." data-tip-badge="HABILIDADE (H)">
                        <div class="text-[0.68rem] font-bold text-purple-700">HABILIDADE (H)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalH">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraH" class="bg-purple-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100 cursor-help transition hover:border-amber-300 hover:shadow-xs" data-tip-title="Pilar A: Atitude" data-tip-desc="Iniciativa, proatividade, vontade de resolver, engajamento e trabalho em equipe. Peso: 3 questões (Máximo 30 pontos)." data-tip-badge="ATITUDE (A)">
                        <div class="text-[0.68rem] font-bold text-amber-700">ATITUDE (A)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalA">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraA" class="bg-amber-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100 cursor-help transition hover:border-emerald-300 hover:shadow-xs" data-tip-title="Pilar P: Postura" data-tip-desc="Inteligência emocional, relacionamento interpessoal, respeito, ética e postura profissional. Peso: 3 questões (Máximo 30 pontos)." data-tip-badge="POSTURA (P)">
                        <div class="text-[0.68rem] font-bold text-emerald-700">POSTURA (P)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalP">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraP" class="bg-emerald-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100 col-span-2 sm:col-span-1 cursor-help transition hover:border-rose-300 hover:shadow-xs" data-tip-title="Pilar D: Disciplina" data-tip-desc="Pontualidade, assiduidade, cumprimento de normas internas, uso de uniforme e segurança. Peso: 2 questões (Máximo 20 pontos)." data-tip-badge="DISCIPLINA (D)">
                        <div class="text-[0.68rem] font-bold text-rose-700">DISCIPLINA (D)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalD">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraD" class="bg-rose-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SELETOR DE CONCEITO / LETRAS (A, B, C, D, E, F - CONFORME PLANILHA) -->
            <div class="mb-6">
                <label class="ch-label block mb-2">Selecione o Conceito de Desempenho (Letras A a F) <span class="text-rose-500">*</span></label>
                <div id="gradeConceitosLetras" class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <!-- Preenchido via JS com os 3 grupos da planilha -->
                </div>
            </div>

            <!-- ATIVAÇÃO DE PDI / PLANO DE AÇÃO 5W2H -->
            <div id="cardAtivacaoPdi" class="p-4 sm:p-5 rounded-2xl bg-slate-50 border border-slate-200 mb-5 transition-all">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <label class="relative inline-flex items-center cursor-pointer mt-0.5 select-none">
                            <input type="checkbox" id="chkRequerPdi" onchange="aoAlternarRequerPdi()" class="w-5 h-5 rounded-lg text-rose-600 focus:ring-rose-500 border-slate-300 cursor-pointer">
                        </label>
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <label for="chkRequerPdi" class="text-sm font-black text-slate-800 cursor-pointer">
                                    Encaminhar Colaborador para PDI / Plano de Ação (5W2H)
                                </label>
                                <span id="badgeMotivoPdi" class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-700">
                                    Opcional pelo Avaliador
                                </span>
                            </div>
                            <p id="textoAjudaPdi" class="text-xs text-slate-500 font-semibold mt-0.5">
                                Marque esta opção se o colaborador necessita de acompanhamento no módulo de Plano de Ação & PDI (ativado automaticamente quando a nota é menor que 85 pontos).
                            </p>
                        </div>
                    </div>
                    <a href="<?= $appBase ?>/portal/modules/plano-acao-chapd.php" target="_blank" class="px-3.5 py-2 rounded-xl bg-white hover:bg-rose-50 text-rose-700 border border-rose-200 font-extrabold text-xs transition flex items-center gap-1.5 self-start sm:self-center no-underline shadow-2xs">
                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Módulo Plano de Ação
                    </a>
                </div>
            </div>

            <!-- PONTOS FORTES, PONTOS A DESENVOLVER E PLANO DE AÇÃO -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="ch-label">Principais Pontos Fortes e Diferenciais</label>
                    <textarea id="inputPontosFortes" rows="3" placeholder="Cite as características mais marcantes e elogios merecidos..." class="ch-input text-xs sm:text-sm"></textarea>
                </div>
                <div>
                    <label class="ch-label">Pontos de Atenção e Oportunidades de Melhoria</label>
                    <textarea id="inputPontosDesenvolver" rows="3" placeholder="O que o colaborador precisa ajustar ou desenvolver?..." class="ch-input text-xs sm:text-sm"></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="ch-label">Resumo Inicial para o Plano de Ação / PDI (Opcional)</label>
                    <textarea id="inputPlanoAcao" rows="2" placeholder="Ações práticas iniciais, metas de capacitação ou direcionamento para o módulo 5W2H..." class="ch-input text-xs sm:text-sm"></textarea>
                </div>
            </div>

            <!-- BOTÕES SALVAR / FINALIZAR AVALIAÇÃO -->
            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-100">
                <button type="button" onclick="cancelarAvaliacao()" class="p-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs sm:text-sm transition cursor-pointer">
                    Descartar
                </button>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" id="btnSalvarEAbrirPdi" onclick="salvarAvaliacaoFinal(true, false)" class="hidden py-2.5 px-4 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs sm:text-sm transition flex items-center gap-2 shadow-md cursor-pointer">
                        <i class="fa-solid fa-list-check"></i>
                        <span>Salvar e Abrir Plano 5W2H</span>
                    </button>
                    <button type="button" id="btnSalvarAvaliacao" onclick="abrirEscolhaSalvarAvaliacao()" class="py-2.5 px-5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs sm:text-sm transition flex items-center gap-2 shadow-sm cursor-pointer">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Salvar Avaliação</span>
                    </button>
                    <button type="button" id="btnFinalizarImprimirAvaliacao" onclick="salvarAvaliacaoFinal(false, true)" class="ch-btn py-2.5 px-6 text-xs sm:text-sm cursor-pointer">
                        <i class="fa-solid fa-print"></i>
                        <span>Finalizar e Imprimir p/ Assinatura</span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <!-- =================================================== -->
    <!-- ABA 2: DASHBOARD & PEOPLE ANALYTICS                 -->
    <!-- =================================================== -->
    <div id="painelDashboard" class="space-y-4">
        <!-- 4 CARDS KPIS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="ch-kpi bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100 shadow-sm cursor-help transition hover:shadow-md" data-tip-title="Total de Avaliações" data-tip-desc="Volume acumulado de avaliações C.H.A.P.D registradas conforme os filtros de ano, mês e filial selecionados." data-tip-badge="KPI OPERACIONAL">
                <div class="flex items-center justify-between text-emerald-600 mb-1">
                    <span class="ch-label text-emerald-700">Total Avaliações</span>
                    <i class="fa-solid fa-users text-sm"></i>
                </div>
                <div id="kpiDashTotal" class="text-2xl font-black text-slate-800">0</div>
                <div class="text-[0.7rem] text-slate-500 font-semibold mt-1">no período filtrado</div>
            </div>

            <div class="ch-kpi bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 shadow-sm cursor-help transition hover:shadow-md" data-tip-title="Média Geral de Pontos" data-tip-desc="Média aritmética das pontuações apuradas no período sobre o total máximo de 140 pontos por colaborador." data-tip-badge="DESEMPENHO MÉDIO">
                <div class="flex items-center justify-between text-blue-600 mb-1">
                    <span class="ch-label text-blue-700">Média Geral</span>
                    <i class="fa-solid fa-chart-line text-sm"></i>
                </div>
                <div id="kpiDashMedia" class="text-2xl font-black text-slate-800">0.0 pts</div>
                <div id="kpiDashPercentual" class="text-[0.7rem] text-blue-600 font-bold mt-1">0% de aproveitamento</div>
            </div>

            <div class="ch-kpi bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-100 shadow-sm cursor-help transition hover:shadow-md" data-tip-title="Quadro de Destaques (> 125 pts)" data-tip-desc="Colaboradores de alta performance (Conjunto A & B) com nota superior a 125 pontos, elegíveis a reconhecimento e mérito." data-tip-badge="ALTA PERFORMANCE">
                <div class="flex items-center justify-between text-purple-600 mb-1">
                    <span class="ch-label text-purple-700">Destaques (>125)</span>
                    <i class="fa-solid fa-star text-sm"></i>
                </div>
                <div id="kpiDashExcelentes" class="text-2xl font-black text-slate-800">0</div>
                <div class="text-[0.7rem] text-slate-500 font-semibold mt-1">quadro de excelência</div>
            </div>

            <div onclick="window.location.href='<?= $appBase ?>/portal/modules/plano-acao-chapd.php'" class="ch-kpi bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 shadow-sm cursor-pointer transition hover:shadow-md" data-tip-title="Necessitam PDI / Plano de Ação" data-tip-desc="Colaboradores com pontuação abaixo de 85 pontos ou encaminhados pelo avaliador para PDI. Clique para abrir o módulo Plano de Ação 5W2H." data-tip-badge="PLANO DE AÇÃO & PDI">
                <div class="flex items-center justify-between text-amber-600 mb-1">
                    <span class="ch-label text-amber-700">Necessitam PDI / PA</span>
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
                <div id="kpiDashAtencao" class="text-2xl font-black text-slate-800">0</div>
                <div class="text-[0.7rem] text-rose-600 font-bold mt-1 flex items-center gap-1">
                    <span>&lt; 85 pts ou indicado</span> <i class="fa-solid fa-arrow-right text-[9px]"></i>
                </div>
            </div>
        </div>

        <!-- FILTROS DO DASHBOARD -->
        <div class="ch-card p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-500">Ano:</span>
                    <select id="filtroDashAno" onchange="carregarDashboard()" class="ch-input mt-0 py-1.5 px-3 text-xs w-28">
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-500">Mês:</span>
                    <select id="filtroDashMes" onchange="carregarDashboard()" class="ch-input mt-0 py-1.5 px-3 text-xs w-36">
                        <option value="">Todos os meses</option>
                    </select>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-bold text-slate-500">Filial:</span>
                    <select id="filtroDashFilial" onchange="carregarDashboard()" class="ch-input mt-0 py-1.5 px-3 text-xs w-40">
                        <option value="">Todas (1, 2 e 6)</option>
                        <option value="1">Matriz 1 (SC)</option>
                        <option value="2">Filial 2 (SC)</option>
                        <option value="6">Filial 6 (PR)</option>
                    </select>
                </div>
            </div>
            <button type="button" onclick="carregarDashboard()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center gap-1.5">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </button>
        </div>

        <!-- DESTAQUES & RANKING -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Top Colaboradores -->
            <div class="ch-card p-5">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                    <h3 class="font-black text-slate-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-medal text-amber-500"></i> Top Colaboradores do Período
                    </h3>
                    <span class="text-[0.7rem] text-slate-400 font-bold">Maiores Pontuações</span>
                </div>
                <div id="listaTopColaboradores" class="space-y-2">
                    <div class="text-center py-6 text-slate-400 text-xs font-semibold">Carregando destaques...</div>
                </div>
            </div>

            <!-- Colaboradores que Precisam de Atenção / PDI -->
            <div class="ch-card p-5">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3 mb-3">
                    <div>
                        <h3 class="font-black text-slate-800 text-sm flex items-center gap-2">
                            <i class="fa-solid fa-hand-holding-heart text-rose-500"></i> Foco de Desenvolvimento (PDI & Plano de Ação)
                        </h3>
                        <span class="text-[0.68rem] text-rose-500 font-bold">Pontuação &lt; 85 ou Indicado pelo Avaliador</span>
                    </div>
                    <a href="<?= $appBase ?>/portal/modules/plano-acao-chapd.php" class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-extrabold text-[11px] transition flex items-center gap-1.5 no-underline">
                        <i class="fa-solid fa-list-check"></i> Gerir Planos 5W2H
                    </a>
                </div>
                <div id="listaAtencaoColaboradores" class="space-y-2">
                    <div class="text-center py-6 text-slate-400 text-xs font-semibold">Nenhum colaborador pendente de PDI no período.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- ABA: GESTÃO DE COLABORADORES & EQUIPE (ÁREA SEPARADA)-->
    <!-- =================================================== -->
    <div id="painelColaboradores" class="hidden space-y-4">
        <!-- MÉTRICAS DE EQUIPE -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="ch-card p-4 flex items-center gap-3 border-l-4 border-l-emerald-500">
                <div class="w-11 h-11 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-users"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Total Colaboradores</div>
                    <div id="colabMetricaTotal" class="text-xl sm:text-2xl font-black text-slate-800">--</div>
                </div>
            </div>

            <div class="ch-card p-4 flex items-center gap-3 border-l-4 border-l-teal-500">
                <div class="w-11 h-11 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-camera"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Com Foto Cadastrada</div>
                    <div id="colabMetricaComFoto" class="text-xl sm:text-2xl font-black text-slate-800">--</div>
                </div>
            </div>

            <div class="ch-card p-4 flex items-center gap-3 border-l-4 border-l-indigo-500">
                <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-user-pen"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Perfis Customizados</div>
                    <div id="colabMetricaCustom" class="text-xl sm:text-2xl font-black text-slate-800">--</div>
                </div>
            </div>

            <div class="ch-card p-4 flex items-center gap-3 border-l-4 border-l-blue-500">
                <div class="w-11 h-11 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg flex-none">
                    <i class="fa-solid fa-id-badge"></i>
                </div>
                <div class="overflow-hidden">
                    <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider truncate">Usuários Portal</div>
                    <div id="colabMetricaUsuarios" class="text-xl sm:text-2xl font-black text-slate-800">--</div>
                </div>
            </div>
        </div>

        <!-- CARD PRINCIPAL: FILTROS E GRID DE COLABORADORES -->
        <div class="ch-card p-5">
            <!-- Header do Card -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-users-viewfinder text-indigo-600"></i> Prontuário de Colaboradores & Fotos
                    </h2>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Gerencie fotos corporativas, edite cargos/setores na área customizada e sincronize com a conta do Portal</p>
                </div>

                <div class="flex items-center gap-2 text-xs">
                    <span class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-shield-halved text-emerald-600"></i> Integrado ao ERP via JOIN
                    </span>
                </div>
            </div>

            <!-- Barra de Filtros e Busca Rápida -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
                <!-- Busca Textual -->
                <div class="lg:col-span-2 relative">
                    <input type="text" id="filtroColabBusca" onkeyup="filtrarColaboradoresGrid()" placeholder="Buscar por nome, cargo, setor ou @usuário..." class="ch-input pl-10 pr-8 py-2.5 text-xs sm:text-sm">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <button type="button" onclick="limparFiltroBuscaColab()" id="btnLimparBuscaColab" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <!-- Filtro Filial -->
                <div>
                    <select id="filtroColabFilial" onchange="filtrarColaboradoresGrid()" class="ch-input py-2.5 text-xs sm:text-sm">
                        <option value="">Todas as Filiais (1, 2 e 6)</option>
                        <option value="1">Matriz 1 (SC)</option>
                        <option value="2">Filial 2 (SC)</option>
                        <option value="6">Filial 6 (PR)</option>
                    </select>
                </div>

                <!-- Filtro Foto / Status -->
                <div>
                    <select id="filtroColabFoto" onchange="filtrarColaboradoresGrid()" class="ch-input py-2.5 text-xs sm:text-sm">
                        <option value="">Todas as Fotos</option>
                        <option value="com_foto">Com Foto Cadastrada</option>
                        <option value="sem_foto">Sem Foto</option>
                        <option value="customizado">Apenas Perfis Editados</option>
                        <option value="com_usuario">Com Usuário Portal</option>
                    </select>
                </div>
            </div>

            <!-- Contador de resultados -->
            <div class="flex items-center justify-between text-xs text-slate-500 font-semibold mb-3 px-1">
                <span id="colabContadorExibidos">Exibindo todos os colaboradores</span>
                <span class="text-[11px] text-slate-400">Clique no ícone de câmera para foto rápida ou em Editar para ajustar o perfil</span>
            </div>

            <!-- GRID RESPONSIVO DE CARDS DE COLABORADORES -->
            <div id="gridColaboradores" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <!-- JS preenche dinamicamente -->
            </div>

            <!-- EMPTY STATE QUANDO NENHUM COLABORADOR É ENCONTRADO -->
            <div id="emptyColaboradores" class="hidden py-12 text-center">
                <div class="w-16 h-16 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center text-2xl mx-auto mb-3">
                    <i class="fa-solid fa-user-slash"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-700">Nenhum colaborador encontrado</h4>
                <p class="text-xs text-slate-400 mt-1">Tente ajustar o termo de pesquisa ou os filtros selecionados.</p>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- ABA 3: MATRIZ ANUAL (JAN A DEZ) - IDÊNTICA À PLANILHA -->
    <!-- =================================================== -->
    <div id="painelMatriz" class="hidden space-y-4">
        <div class="ch-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h2 class="text-lg font-black text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-calendar-days text-emerald-600"></i> Matriz Anual de Desempenho (Jan a Dez)
                    </h2>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Visão consolidada do ano por colaborador, idêntica à planilha de acompanhamento mensal</p>
                </div>
                <div class="flex items-center gap-2">
                    <select id="filtroMatrizAno" onchange="carregarMatrizAnual()" class="ch-input mt-0 py-1.5 px-3 text-xs w-28">
                        <option value="2026">Ano 2026</option>
                        <option value="2025">Ano 2025</option>
                    </select>
                    <select id="filtroMatrizFilial" onchange="carregarMatrizAnual()" class="ch-input mt-0 py-1.5 px-3 text-xs w-40">
                        <option value="">Todas Filiais (1, 2 e 6)</option>
                        <option value="1">Matriz 1 (SC)</option>
                        <option value="2">Filial 2 (SC)</option>
                        <option value="6">Filial 6 (PR)</option>
                    </select>
                    <button type="button" onclick="carregarMatrizAnual()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            </div>

            <!-- TABELA MATRIZ RESPONSIVA COM SCROLL HORIZONTAL -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 text-slate-400 uppercase tracking-wider font-extrabold bg-slate-50">
                            <th class="py-3 px-3 min-w-[200px]">Colaborador</th>
                            <th class="py-3 px-2 text-center">Jan</th>
                            <th class="py-3 px-2 text-center">Fev</th>
                            <th class="py-3 px-2 text-center">Mar</th>
                            <th class="py-3 px-2 text-center">Abr</th>
                            <th class="py-3 px-2 text-center">Mai</th>
                            <th class="py-3 px-2 text-center">Jun</th>
                            <th class="py-3 px-2 text-center">Jul</th>
                            <th class="py-3 px-2 text-center">Ago</th>
                            <th class="py-3 px-2 text-center">Set</th>
                            <th class="py-3 px-2 text-center">Out</th>
                            <th class="py-3 px-2 text-center">Nov</th>
                            <th class="py-3 px-2 text-center">Dez</th>
                            <th class="py-3 px-3 text-center min-w-[90px] bg-slate-100 text-slate-700">Média</th>
                            <th class="py-3 px-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaMatrizCorpo" class="divide-y divide-slate-100 font-semibold text-slate-700">
                        <tr><td colspan="15" class="text-center py-8 text-slate-400">Carregando matriz anual...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- ABA 4: HISTÓRICO & FICHAS                           -->
    <!-- =================================================== -->
    <div id="painelHistorico" class="hidden space-y-4">
        <div class="ch-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Histórico de Avaliações Registradas</h2>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Consulte, visualize detalhes e imprima a ficha formal de cada avaliação</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select id="filtroHistAno" onchange="carregarHistorico()" class="ch-input mt-0 py-1.5 px-3 text-xs w-28">
                        <option value="">Todos Anos</option>
                        <option value="2026">2026</option>
                        <option value="2025">2025</option>
                    </select>
                    <select id="filtroHistMes" onchange="carregarHistorico()" class="ch-input mt-0 py-1.5 px-3 text-xs w-32">
                        <option value="">Todos Meses</option>
                    </select>
                    <button type="button" onclick="carregarHistorico()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                        <i class="fa-solid fa-rotate"></i> Atualizar
                    </button>
                </div>
            </div>

            <!-- Tabela de Histórico -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider font-extrabold">
                            <th class="py-3 px-3">Período</th>
                            <th class="py-3 px-3">Colaborador</th>
                            <th class="py-3 px-3">Cargo / Setor</th>
                            <th class="py-3 px-3 text-center">Pontuação</th>
                            <th class="py-3 px-3 text-center">Conceito</th>
                            <th class="py-3 px-3">Classificação</th>
                            <th class="py-3 px-3">Avaliador</th>
                            <th class="py-3 px-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaHistoricoCorpo" class="divide-y divide-slate-100 font-semibold text-slate-700">
                        <tr><td colspan="8" class="text-center py-8 text-slate-400">Carregando histórico...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- ABA 5: BANCO DE PERGUNTAS & CRITÉRIOS               -->
    <!-- =================================================== -->
    <div id="painelPerguntas" class="hidden space-y-4">
        <div class="ch-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4 mb-4">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Banco de Perguntas & Critérios C.H.A.P.D</h2>
                    <p class="text-xs text-slate-400 font-semibold mt-0.5">Gerencie os critérios avaliativos, pilares correspondentes, pesos e ordem</p>
                </div>
                <button type="button" onclick="abrirModalNovaPergunta()" class="ch-btn py-2 px-4 text-xs">
                    <i class="fa-solid fa-plus"></i> Nova Pergunta
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider font-extrabold">
                            <th class="py-3 px-3 text-center">Ordem</th>
                            <th class="py-3 px-3 text-center">Pilar</th>
                            <th class="py-3 px-3">Código</th>
                            <th class="py-3 px-3">Enunciado da Pergunta</th>
                            <th class="py-3 px-3">Descrição Auxiliar</th>
                            <th class="py-3 px-3 text-center">Status</th>
                            <th class="py-3 px-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaPerguntasCorpo" class="divide-y divide-slate-100 font-semibold text-slate-700">
                        <tr><td colspan="7" class="text-center py-8 text-slate-400">Carregando perguntas...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- MODAL 1: FICHA FORMAL DE AVALIAÇÃO / IMPRESSÃO PDF  -->
    <!-- =================================================== -->
    <div id="modalFicha" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
        <div class="bg-white rounded-3xl max-w-4xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <!-- Header Modal -->
            <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-800 to-teal-800 text-white flex flex-wrap items-center justify-between gap-3 no-print">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white flex-none">
                        <i class="fa-solid fa-file-invoice text-lg"></i>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base sm:text-lg font-black tracking-tight" id="fichaModalTitulo">Ficha de Avaliação C.H.A.P.D</h3>
                            <span id="fichaStatusBadge" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-300 text-slate-900">Em Edição</span>
                        </div>
                        <p class="text-xs text-emerald-100 font-semibold" id="fichaModalSubtitulo">Registro Oficial de Desempenho</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1.5">
                    <button type="button" id="btnFichaEditar" onclick="editarAvaliacaoDaFicha()" class="p-2 px-3 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-900 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer" title="Abrir avaliação para alterar notas, conceito ou textos">
                        <i class="fa-solid fa-pen-to-square"></i> Editar
                    </button>
                    <button type="button" id="btnFichaFinalizar" onclick="finalizarAvaliacaoDaFicha(false)" class="p-2 px-3 rounded-xl bg-emerald-400 hover:bg-emerald-300 text-slate-900 font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer" title="Finalizar avaliação (trava edição e exclusão)">
                        <i class="fa-solid fa-lock"></i> Finalizar
                    </button>
                    <button type="button" id="btnFichaExcluir" onclick="excluirAvaliacaoDaFicha()" class="p-2 px-3 rounded-xl bg-rose-500 hover:bg-rose-600 text-white font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer" title="Excluir avaliação enquanto não finalizada">
                        <i class="fa-solid fa-trash"></i> Excluir
                    </button>
                    <button type="button" onclick="imprimirFicha()" id="btnFichaImprimir" class="p-2 px-3.5 rounded-xl bg-white/20 hover:bg-white/30 text-white font-bold text-xs transition flex items-center gap-1.5 cursor-pointer">
                        <i class="fa-solid fa-print"></i> <span id="btnFichaImprimirTexto">Imprimir p/ Assinatura</span>
                    </button>
                    <button type="button" onclick="fecharModalFicha()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Conteúdo da Ficha (Área de Impressão A4 Premium) -->
            <div id="modalFichaConteudo" class="p-6 sm:p-8 overflow-y-auto space-y-5 text-sm text-slate-700 bg-white">
                <!-- Cabeçalho Oficial Executivo A4 -->
                <div class="border-2 border-slate-800 rounded-2xl overflow-hidden print-avoid-break shadow-xs">
                    <div class="bg-gradient-to-r from-slate-900 via-emerald-950 to-teal-900 text-white px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2 text-[0.65rem] uppercase font-black text-emerald-300 tracking-widest">
                                <span>NUTRICIONAL DISTRIBUIDORA</span>
                                <span>•</span>
                                <span>GENTE &amp; GESTÃO / PERFORMANCE</span>
                            </div>
                            <h2 class="text-lg sm:text-xl font-black text-white tracking-tight mt-0.5">FICHA DE AVALIAÇÃO C.H.A.P.D</h2>
                            <div class="text-[11px] font-semibold text-emerald-100/90">Conhecimento • Habilidade • Atitude • Postura • Disciplina</div>
                        </div>
                        <div class="flex flex-col sm:items-end gap-1">
                            <div class="flex items-center gap-2">
                                <span id="fichaNumeroTopo" class="px-2.5 py-0.5 rounded-lg bg-white/15 text-white font-black text-xs border border-white/20">Ficha #--</span>
                                <span id="fichaStatusTopo" class="px-2.5 py-0.5 rounded-lg bg-amber-300 text-slate-900 font-black text-[10px] uppercase">EM EDIÇÃO</span>
                            </div>
                            <div class="text-xs font-black text-white" id="fichaPeriodo">Outubro / 2026</div>
                            <div class="text-[10px] font-semibold text-emerald-100" id="fichaDataAvaliacao">Avaliada em --/--/----</div>
                        </div>
                    </div>

                    <!-- Prontuário Completo com Foto 3x4 do Colaborador -->
                    <div class="p-4 sm:p-5 bg-slate-50 flex flex-col sm:flex-row items-center sm:items-stretch gap-4">
                        <div class="flex-none flex flex-col items-center justify-center">
                            <div class="w-20 h-24 rounded-xl border-2 border-slate-300 bg-white shadow-sm overflow-hidden flex items-center justify-center" id="fichaFotoContainer">
                                <img id="fichaColabFoto" class="w-full h-full object-cover hidden" alt="Foto Colaborador">
                                <div id="fichaColabIniciais" class="w-full h-full bg-emerald-100 text-emerald-800 font-black flex items-center justify-center text-xl">--</div>
                            </div>
                        </div>
                        <div class="flex-1 grid grid-cols-2 sm:grid-cols-4 gap-x-4 gap-y-2.5 text-xs w-full">
                            <div class="col-span-2">
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Colaborador Avaliado</span>
                                <span class="font-black text-slate-900 text-sm break-words" id="fichaColaborador">--</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Código ERP</span>
                                <span class="font-black text-slate-800" id="fichaCodigoErp">#--</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Data de Admissão</span>
                                <span class="font-extrabold text-slate-800" id="fichaAdmissao">--</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Cargo / Função</span>
                                <span class="font-extrabold text-slate-800 break-words" id="fichaCargo">--</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Unidade / Filial</span>
                                <span class="font-extrabold text-slate-800" id="fichaFilial">--</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Setor</span>
                                <span class="font-extrabold text-slate-800 break-words" id="fichaSetor">--</span>
                            </div>
                            <div>
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block">Célula de Trabalho</span>
                                <span class="font-extrabold text-slate-800 break-words" id="fichaCelula">--</span>
                            </div>
                            <div class="col-span-2 sm:col-span-4 pt-1.5 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-600">
                                <span><b class="text-slate-800">Avaliador:</b> <span id="fichaAvaliadorTopo">--</span></span>
                                <span><b class="text-slate-800">Aprovação / Diretoria:</b> <span id="fichaAprovadorTopo">--</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumo dos Resultados -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 print-avoid-break">
                    <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 text-center">
                        <div class="text-[0.65rem] uppercase font-bold text-slate-400">PONTUAÇÃO TOTAL</div>
                        <div class="text-2xl font-black text-slate-900 mt-0.5" id="fichaPontos">0 / 140</div>
                        <div class="text-[0.68rem] text-slate-500 font-bold mt-0.5" id="fichaPercentual">0% de aproveitamento</div>
                    </div>
                    <div class="p-3 rounded-xl border border-slate-200 bg-slate-50 text-center">
                        <div class="text-[0.65rem] uppercase font-bold text-slate-400">CLASSIFICAÇÃO</div>
                        <div class="text-sm font-black text-slate-900 mt-1 break-words" id="fichaClassificacao">--</div>
                    </div>
                    <div class="p-3 rounded-xl border border-indigo-200 bg-indigo-50/70 text-center">
                        <div class="text-[0.65rem] uppercase font-bold text-indigo-600">CONJUNTO DUPLO</div>
                        <div class="text-xl font-black text-indigo-900 mt-1" id="fichaConjuntoDuplo">A &amp; B</div>
                    </div>
                    <div class="p-3 rounded-xl border border-emerald-200 bg-emerald-50/60 text-center">
                        <div class="text-[0.65rem] uppercase font-bold text-emerald-700">CONCEITO ATRIBUÍDO</div>
                        <div class="text-2xl font-black text-emerald-800 mt-0.5" id="fichaLetra">--</div>
                    </div>
                </div>

                <!-- Desempenho por Pilar C.H.A.P.D na Ficha -->
                <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50/80 print-avoid-break">
                    <div class="text-[10px] font-black uppercase tracking-wider text-slate-500 mb-2">Desempenho Detalhado por Pilar C.H.A.P.D</div>
                    <div class="grid grid-cols-5 gap-2 text-xs">
                        <div class="p-2 rounded-lg bg-white border border-sky-200">
                            <div class="text-[9px] font-black text-sky-700 uppercase">Conhecimento (C)</div>
                            <div class="font-black text-slate-900 text-sm" id="fichaPilarC">0 / 40</div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden"><div id="fichaBarraPilarC" class="h-full bg-sky-500 rounded-full" style="width:0%"></div></div>
                        </div>
                        <div class="p-2 rounded-lg bg-white border border-purple-200">
                            <div class="text-[9px] font-black text-purple-700 uppercase">Habilidade (H)</div>
                            <div class="font-black text-slate-900 text-sm" id="fichaPilarH">0 / 20</div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden"><div id="fichaBarraPilarH" class="h-full bg-purple-500 rounded-full" style="width:0%"></div></div>
                        </div>
                        <div class="p-2 rounded-lg bg-white border border-amber-200">
                            <div class="text-[9px] font-black text-amber-700 uppercase">Atitude (A)</div>
                            <div class="font-black text-slate-900 text-sm" id="fichaPilarA">0 / 30</div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden"><div id="fichaBarraPilarA" class="h-full bg-amber-500 rounded-full" style="width:0%"></div></div>
                        </div>
                        <div class="p-2 rounded-lg bg-white border border-emerald-200">
                            <div class="text-[9px] font-black text-emerald-700 uppercase">Postura (P)</div>
                            <div class="font-black text-slate-900 text-sm" id="fichaPilarP">0 / 30</div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden"><div id="fichaBarraPilarP" class="h-full bg-emerald-500 rounded-full" style="width:0%"></div></div>
                        </div>
                        <div class="p-2 rounded-lg bg-white border border-rose-200">
                            <div class="text-[9px] font-black text-rose-700 uppercase">Disciplina (D)</div>
                            <div class="font-black text-slate-900 text-sm" id="fichaPilarD">0 / 20</div>
                            <div class="w-full h-1.5 bg-slate-100 rounded-full mt-1 overflow-hidden"><div id="fichaBarraPilarD" class="h-full bg-rose-500 rounded-full" style="width:0%"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Tabela de Respostas Pergunta por Pergunta -->
                <div>
                    <h4 class="font-black text-slate-800 text-xs uppercase tracking-wider mb-2">Detalhamento das Questões Respondidas</h4>
                    <table class="w-full text-left text-xs border border-slate-300 rounded-xl overflow-hidden">
                        <thead class="bg-slate-800 text-white font-extrabold">
                            <tr>
                                <th class="p-2 text-center w-12">Pilar</th>
                                <th class="p-2">Critério Avaliado</th>
                                <th class="p-2 text-center w-28">Nota / Nível</th>
                                <th class="p-2">Observações</th>
                            </tr>
                        </thead>
                        <tbody id="fichaTabelaRespostas" class="divide-y divide-slate-200 font-medium text-slate-700">
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Observações e PDI -->
                <div class="p-4 rounded-xl border border-slate-300 bg-slate-50 space-y-3 text-xs print-avoid-break">
                    <div>
                        <b class="text-slate-900 uppercase text-[10px] tracking-wider block">Pontos Fortes e Diferenciais:</b>
                        <span id="fichaPontosFortes" class="text-slate-700 block mt-0.5 whitespace-pre-wrap break-words leading-relaxed">--</span>
                    </div>
                    <div class="pt-2 border-t border-slate-200">
                        <b class="text-slate-900 uppercase text-[10px] tracking-wider block">Pontos a Desenvolver / Atenção:</b>
                        <span id="fichaPontosDesenvolver" class="text-slate-700 block mt-0.5 whitespace-pre-wrap break-words leading-relaxed">--</span>
                    </div>
                    <div class="pt-2 border-t border-slate-200">
                        <b class="text-slate-900 uppercase text-[10px] tracking-wider block">Resumo do Plano de Ação (PDI):</b>
                        <span id="fichaPlanoAcao" class="text-slate-700 block mt-0.5 whitespace-pre-wrap break-words leading-relaxed">--</span>
                    </div>
                </div>

                <!-- Feedback e Ciência do Colaborador -->
                <div class="p-4 rounded-xl border border-indigo-200 bg-indigo-50/40 space-y-2.5 text-xs print-avoid-break">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="font-black text-indigo-950 flex items-center gap-1.5">
                            <i class="fa-solid fa-comments text-indigo-600"></i> Feedback &amp; Ciência do Colaborador (Reunião 1:1)
                        </div>
                        <span id="fichaCienciaStatus" class="text-[10px] font-extrabold px-2.5 py-0.5 rounded-full bg-slate-200 text-slate-600">Aguardando feedback</span>
                    </div>
                    <div id="fichaFeedbackTextoExibicao" class="text-slate-700 font-medium hidden whitespace-pre-wrap break-words leading-relaxed"></div>
                    <div class="no-print space-y-2 pt-1">
                        <textarea id="fichaInputFeedbackColab" rows="2" placeholder="Registre a percepção, comentários ou compromisso assumido pelo colaborador durante o feedback..." class="ch-input bg-white text-xs mt-0"></textarea>
                        <div class="flex justify-end">
                            <button type="button" onclick="salvarFeedbackFichaAvaliacao()" class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-extrabold text-xs transition flex items-center gap-1.5 cursor-pointer">
                                <i class="fa-solid fa-signature"></i> Registrar Ciência / Feedback do Colaborador
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Selo de Finalização (visível quando finalizada) -->
                <div id="fichaSeloFinalizacao" class="hidden p-3 rounded-xl border border-emerald-200 bg-emerald-50/70 text-[11px] font-bold text-emerald-900 flex items-center justify-between print-avoid-break">
                    <span><i class="fa-solid fa-lock text-emerald-700 mr-1.5"></i> Documento finalizado oficialmente para colheita de assinaturas</span>
                    <span id="fichaSeloInfo">--</span>
                </div>

                <!-- Assinaturas Formais (Colaborador, Avaliador e Aprovador) -->
                <div class="pt-10 border-t border-slate-300 grid grid-cols-1 sm:grid-cols-3 gap-8 text-center text-xs mt-6 print-avoid-break">
                    <div>
                        <div class="border-b border-slate-500 pb-1 mb-1 font-black text-slate-900" id="fichaAssinaturaColaborador">Colaborador Avaliado</div>
                        <div class="text-slate-500 font-bold uppercase text-[0.65rem]">Ciente / Colaborador Avaliado</div>
                        <div class="text-slate-400 text-[0.65rem] mt-1">Data: _____ / _____ / _________</div>
                    </div>
                    <div>
                        <div class="border-b border-slate-500 pb-1 mb-1 font-black text-slate-900" id="fichaAssinaturaAvaliador">Sandro Rafo</div>
                        <div class="text-slate-500 font-bold uppercase text-[0.65rem]">Elaboração / Avaliador</div>
                        <div class="text-slate-400 text-[0.65rem] mt-1">Data: _____ / _____ / _________</div>
                    </div>
                    <div>
                        <div class="border-b border-slate-500 pb-1 mb-1 font-black text-slate-900" id="fichaAssinaturaAprovador">Robson Becker</div>
                        <div class="text-slate-500 font-bold uppercase text-[0.65rem]">Aprovação / Diretoria</div>
                        <div class="text-slate-400 text-[0.65rem] mt-1">Data: _____ / _____ / _________</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- MODAL 2: MANUAL & CRITÉRIOS DE NEGÓCIO              -->
    <!-- =================================================== -->
    <div id="modalManual" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
        <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <div class="p-5 bg-gradient-to-r from-emerald-700 to-teal-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-book-bookmark text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black tracking-tight">Guia Oficial de Critérios C.H.A.P.D</h3>
                        <p class="text-xs text-emerald-100 font-semibold">Metodologia Nutricional Distribuidora</p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalManual()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4 text-xs sm:text-sm text-slate-600">
                <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-100">
                    <h4 class="font-black text-emerald-900 mb-1">O que é a Avaliação C.H.A.P.D?</h4>
                    <p class="text-xs text-slate-700 leading-relaxed">
                        É a ferramenta de gestão por competências da Nutricional que avalia continuamente 5 dimensões humanas e profissionais:
                        <b>Conhecimento</b> (saber fazer), <b>Habilidade</b> (fazer com qualidade), <b>Atitude</b> (querer fazer e agir),
                        <b>Postura</b> (relacionamento e inteligência emocional) e <b>Disciplina</b> (zelo, horários e normas).
                    </p>
                </div>

                <!-- Escala de Pontuação -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <h4 class="font-black text-slate-800 mb-2">Escala de Pontos por Questão:</h4>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs">
                        <div class="p-2 rounded-xl bg-emerald-100 text-emerald-900 font-bold">10 - SEMPRE</div>
                        <div class="p-2 rounded-xl bg-blue-100 text-blue-900 font-bold">08 - FREQUENTEMENTE</div>
                        <div class="p-2 rounded-xl bg-amber-100 text-amber-900 font-bold">06 - ÀS VEZES</div>
                        <div class="p-2 rounded-xl bg-orange-100 text-orange-900 font-bold">04 - RARAMENTE</div>
                        <div class="p-2 rounded-xl bg-rose-100 text-rose-900 font-bold">02 - NUNCA</div>
                    </div>
                </div>

                <!-- Faixas de Classificação -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <h4 class="font-black text-slate-800 mb-2">Classificação por Faixa de Pontos (Total 140 pts):</h4>
                    <ul class="space-y-1.5 text-xs text-slate-700">
                        <li><b class="text-emerald-700">&gt; 125 Pontos:</b> Excelente (Referência de entrega e engajamento).</li>
                        <li><b class="text-blue-700">100 a 125 Pontos:</b> Aceitável e com Potencial (Bom desempenho, apto a novos desafios).</li>
                        <li><b class="text-amber-700">85 a 99 Pontos:</b> Médio Potencial / Em Observação.</li>
                        <li><b class="text-rose-700">&lt; 85 Pontos:</b> Baixo Potencial / Atenção Imediata (Exige PDI corretivo).</li>
                    </ul>
                </div>

                <!-- Letras Conceito -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <h4 class="font-black text-slate-800 mb-2">Conceitos por Letras:</h4>
                    <div class="space-y-2 text-xs">
                        <div class="p-2 rounded-xl bg-white border border-emerald-200 text-slate-700">
                            <b>A / B (Diferencial):</b> Altamente engajado e competente nas metas; excelente desempenho, busca aperfeiçoamento.
                        </div>
                        <div class="p-2 rounded-xl bg-white border border-blue-200 text-slate-700">
                            <b>C / D (Potencial de Desenvolvimento):</b> Médio potencial, falta desenvolver proatividade; tarefas de rotina com potencial.
                        </div>
                        <div class="p-2 rounded-xl bg-white border border-rose-200 text-slate-700">
                            <b>E / F (Pontos de Atenção):</b> Baixo desempenho, decisões e dinamismo não fazem parte do perfil atual.
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-slate-50 border-t border-slate-100 flex justify-end">
                <button type="button" onclick="fecharModalManual()" class="py-2 px-5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition">
                    Fechar Guia
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- MODAL 3: NOVA / EDITAR PERGUNTA DINÂMICA            -->
    <!-- =================================================== -->
    <div id="modalPergunta" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
        <div class="bg-white rounded-3xl max-w-xl w-full flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <div class="p-5 bg-gradient-to-r from-emerald-700 to-teal-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-pen-ruler text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black" id="modalPerguntaTitulo">Nova Pergunta C.H.A.P.D</h3>
                        <p class="text-xs text-emerald-100 font-semibold">Configuração do Critério Avaliativo</p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalPergunta()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <form id="formPergunta" onsubmit="salvarPergunta(event)" class="p-5 sm:p-6 space-y-4 text-xs sm:text-sm">
                <input type="hidden" id="perguntaId" value="">

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="ch-label">Pilar C.H.A.P.D <span class="text-rose-500">*</span></label>
                        <select id="perguntaPilar" class="ch-input" required>
                            <option value="C">C - Conhecimento</option>
                            <option value="H">H - Habilidade</option>
                            <option value="A">A - Atitude</option>
                            <option value="P">P - Postura</option>
                            <option value="D">D - Disciplina</option>
                        </select>
                    </div>
                    <div>
                        <label class="ch-label">Código Interno</label>
                        <input type="text" id="perguntaCodigo" placeholder="Ex: C5, H3, P4..." class="ch-input">
                    </div>
                </div>

                <div>
                    <label class="ch-label">Enunciado da Pergunta <span class="text-rose-500">*</span></label>
                    <textarea id="perguntaTexto" rows="3" placeholder="Digite a pergunta clara e objetiva para o avaliador..." class="ch-input" required></textarea>
                </div>

                <div>
                    <label class="ch-label">Descrição Auxiliar / Critérios de Avaliação</label>
                    <textarea id="perguntaDescAux" rows="2" placeholder="Explique o que deve ser observado na prática..." class="ch-input"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="ch-label">Ordem de Exibição</label>
                        <input type="number" id="perguntaOrdem" value="1" min="1" max="99" class="ch-input">
                    </div>
                    <div>
                        <label class="ch-label">Status</label>
                        <select id="perguntaAtivo" class="ch-input">
                            <option value="1">Ativa</option>
                            <option value="0">Inativa</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" onclick="fecharModalPergunta()" class="p-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition">
                        Cancelar
                    </button>
                    <button type="submit" class="ch-btn py-2.5 px-5 text-xs">
                        Salvar Pergunta
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- MODAL 4: FOTO DO COLABORADOR (CÂMERA OU ARQUIVO)    -->
    <!-- =================================================== -->
    <div id="modalFotoColab" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
        <div class="bg-white rounded-3xl max-w-lg w-full flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <!-- Header Modal Foto -->
            <div class="p-5 bg-gradient-to-r from-emerald-700 to-teal-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-camera text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black">Foto do Colaborador</h3>
                        <p class="text-xs text-emerald-100 font-semibold" id="modalFotoNomeColab">Selecione ou capture uma foto</p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalCapturaFoto()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Miniatura da Foto Atual -->
            <div id="modalFotoAtualContainer" class="px-5 pt-4 flex items-center gap-3">
                <div class="w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-800 font-black text-sm flex items-center justify-center border border-emerald-200 shadow-2xs overflow-hidden flex-none">
                    <img id="modalFotoAtualImg" class="w-full h-full object-cover hidden" alt="Foto Atual">
                    <span id="modalFotoAtualIniciais">--</span>
                </div>
                <div class="text-[11px] text-slate-600">
                    <span class="font-bold text-slate-700">Foto atual no cadastro</span>
                    <div id="modalFotoAtualStatus" class="text-[10px] text-slate-400">Nenhuma foto cadastrada</div>
                </div>
            </div>

            <!-- Abas Câmera vs Enviar Arquivo -->
            <div class="p-5 space-y-4">
                <div class="flex rounded-xl bg-slate-100 p-1 text-xs font-bold text-slate-600">
                    <button type="button" id="abaFotoModoWebcam" onclick="alternarModoFoto('webcam')" class="flex-1 py-2 rounded-lg bg-white text-emerald-800 shadow-xs flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-video text-emerald-600"></i> Bater Foto (Câmera)
                    </button>
                    <button type="button" id="abaFotoModoArquivo" onclick="alternarModoFoto('arquivo')" class="flex-1 py-2 rounded-lg text-slate-600 flex items-center justify-center gap-1.5 transition">
                        <i class="fa-solid fa-upload"></i> Enviar Arquivo
                    </button>
                </div>

                <!-- Painel Modo 1: Webcam / Câmera Celular -->
                <div id="painelFotoWebcam" class="space-y-3">
                    <div class="relative w-full aspect-4/3 bg-slate-900 rounded-2xl overflow-hidden flex items-center justify-center border-2 border-slate-200">
                        <video id="videoWebcam" autoplay playsinline muted class="w-full h-full object-cover"></video>
                        <canvas id="canvasWebcam" class="hidden"></canvas>
                        <img id="previewFotoCapturada" class="w-full h-full object-cover hidden" alt="Foto Capturada">
                        
                        <!-- Overlay quando câmera não iniciou -->
                        <div id="overlayCameraInativa" class="absolute inset-0 bg-slate-900/80 flex flex-col items-center justify-center text-white p-4 text-center">
                            <i class="fa-solid fa-video-slash text-3xl mb-2 text-slate-400"></i>
                            <p class="text-xs font-bold">Câmera desligada</p>
                            <button type="button" onclick="iniciarWebcam()" class="mt-3 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs transition flex items-center gap-1.5 shadow-md">
                                <i class="fa-solid fa-power-off"></i> Ligar Câmera
                            </button>
                        </div>
                    </div>

                    <div class="flex items-center justify-center gap-2">
                        <button type="button" id="btnDispararFoto" onclick="capturarFotoWebcam()" class="ch-btn py-2.5 px-6 text-xs shadow-md">
                            <i class="fa-solid fa-camera"></i> Bater Foto Agora
                        </button>
                        <button type="button" id="btnRepetirFoto" onclick="reiniciarCapturaWebcam()" class="hidden p-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs transition flex items-center gap-1.5">
                            <i class="fa-solid fa-rotate-left"></i> Tirar Outra
                        </button>
                    </div>
                </div>

                <!-- Painel Modo 2: Enviar Arquivo de Imagem -->
                <div id="painelFotoArquivo" class="hidden space-y-3">
                    <label for="inputArquivoFoto" class="border-2 border-dashed border-slate-300 hover:border-emerald-500 rounded-2xl p-6 flex flex-col items-center justify-center cursor-pointer bg-slate-50 hover:bg-emerald-50/40 transition">
                        <i class="fa-solid fa-cloud-arrow-up text-3xl text-slate-400 mb-2"></i>
                        <span class="text-xs font-bold text-slate-700 text-center">Clique aqui ou arraste uma foto para enviar</span>
                        <span class="text-[10px] text-slate-400 mt-1">Formatos aceitos: JPG, PNG, WEBP (Máx. 10MB)</span>
                        <input type="file" id="inputArquivoFoto" accept="image/jpeg,image/png,image/webp" onchange="aoSelecionarArquivoFoto(event)" class="hidden">
                    </label>

                    <div id="previewArquivoContainer" class="hidden flex items-center gap-3 p-3 bg-emerald-50 rounded-xl border border-emerald-200">
                        <img id="previewFotoArquivo" class="w-14 h-14 rounded-xl object-cover border border-emerald-300 flex-none" alt="Preview">
                        <div class="overflow-hidden">
                            <div id="previewArquivoNome" class="text-xs font-bold text-slate-800 truncate">foto.jpg</div>
                            <div id="previewArquivoTamanho" class="text-[10px] text-slate-500 font-semibold">-- KB</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Modal Foto -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <button type="button" onclick="fecharModalCapturaFoto()" class="p-2.5 px-4 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition">
                    Cancelar
                </button>
                <button type="button" id="btnSalvarFotoUpload" onclick="salvarFotoColaborador()" class="ch-btn py-2.5 px-6 text-xs" disabled>
                    <i class="fa-solid fa-check"></i> Salvar Foto no Prontuário
                </button>
            </div>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- MODAL 5: EDITAR DADOS DO COLABORADOR (ÁREA SEPARADA) -->
    <!-- =================================================== -->
    <div id="modalEditarPerfilColab" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
        <div class="bg-white rounded-3xl max-w-xl w-full max-h-[92vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100">
            <!-- Header Modal Perfil -->
            <div class="p-5 bg-gradient-to-r from-indigo-700 to-purple-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-user-gear text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black">Editar Perfil do Colaborador</h3>
                        <p class="text-xs text-indigo-100 font-semibold" id="modalPerfilSubtitulo">Área Separada C.H.A.P.D • Integrada ao ERP via JOIN</p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalEditarPerfilColab()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Formulário de Edição -->
            <form id="formEditarPerfilColab" onsubmit="salvarPerfilColaborador(event)" class="p-5 sm:p-6 overflow-y-auto space-y-4 text-xs sm:text-sm">
                <input type="hidden" id="editPerfilIdcliforemp">

                <!-- Miniatura da Foto do Colaborador no Modal de Edição -->
                <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="relative w-14 h-14 rounded-2xl bg-indigo-600 text-white font-black text-sm flex items-center justify-center shadow-sm overflow-hidden flex-none border-2 border-white">
                            <img id="editPerfilColabFotoImg" class="w-full h-full object-cover hidden" alt="Foto">
                            <span id="editPerfilColabIniciais">--</span>
                        </div>
                        <div>
                            <div class="font-extrabold text-slate-800 text-xs sm:text-sm" id="editPerfilColabNomeDestaque">Nome do Colaborador</div>
                            <div class="text-[10px] text-slate-500 font-semibold" id="editPerfilColabSubInfo">Código: -- • Sem foto personalizada</div>
                        </div>
                    </div>
                    <button type="button" onclick="abrirModalCapturaFoto(parseInt($('editPerfilIdcliforemp').value))" class="py-2 px-3 rounded-xl bg-white hover:bg-emerald-50 text-emerald-800 border border-emerald-300 font-bold text-xs transition shadow-2xs flex items-center gap-1.5 cursor-pointer flex-none" title="Tirar foto com câmera ou enviar arquivo">
                        <i class="fa-solid fa-camera text-emerald-600"></i>
                        <span>Alterar Foto</span>
                    </button>
                </div>

                <!-- Alerta Informativo da Área Separada -->
                <div class="p-3.5 rounded-2xl bg-indigo-50/80 border border-indigo-100 flex items-start gap-2.5">
                    <i class="fa-solid fa-shield-halved text-indigo-600 mt-0.5 text-sm flex-none"></i>
                    <div class="text-[11px] text-indigo-950 leading-relaxed">
                        <b>Preservação da Integridade do ERP:</b> Os dados personalizados abaixo são salvos em tabela própria exclusiva do C.H.A.P.D e combinados via <code>LEFT JOIN</code>. O cadastro fiscal/RH nativo do ERP permanece 100% intacto.
                    </div>
                </div>

                <!-- Nome / Apelido -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="ch-label mb-0">Nome de Exibição / Apelido</label>
                        <span class="text-[10px] text-slate-400 font-semibold truncate max-w-[200px]" id="editPerfilNomeErpLabel">ERP: --</span>
                    </div>
                    <input type="text" id="editPerfilNome" placeholder="Ex: Nome usual ou como o colaborador é conhecido..." class="ch-input">
                </div>

                <!-- Cargo / Função -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="ch-label mb-0">Cargo / Função no C.H.A.P.D</label>
                        <span class="text-[10px] text-slate-400 font-semibold truncate max-w-[200px]" id="editPerfilCargoErpLabel">ERP: --</span>
                    </div>
                    <input type="text" id="editPerfilCargo" placeholder="Ex: Assistente de Logística, Motorista Truck..." class="ch-input">
                </div>

                <!-- Setor e Célula de Trabalho -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="ch-label">Setor Operativo</label>
                        <input type="text" id="editPerfilSetor" placeholder="Ex: Logística, Comercial, TI..." class="ch-input">
                    </div>
                    <div>
                        <label class="ch-label">Célula de Trabalho</label>
                        <input type="text" id="editPerfilCelula" placeholder="Ex: Expedição Noturna, Balcão SC..." class="ch-input">
                    </div>
                </div>

                <!-- Filial e Data de Admissão -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="ch-label">Filial / Lotação</label>
                        <select id="editPerfilFilial" class="ch-input">
                            <option value="1">Matriz 1 (SC)</option>
                            <option value="2">Filial 2 (SC)</option>
                            <option value="6">Filial 6 (PR)</option>
                        </select>
                    </div>
                    <div>
                        <label class="ch-label">Data de Admissão</label>
                        <input type="date" id="editPerfilAdmissao" class="ch-input">
                    </div>
                </div>

                <!-- Observações do Perfil / Histórico -->
                <div>
                    <label class="ch-label">Observações Internas do Perfil / Observações do RH</label>
                    <textarea id="editPerfilObservacoes" rows="2" placeholder="Informações relevantes sobre histórico ou evolução do colaborador..." class="ch-input text-xs"></textarea>
                </div>

                <!-- Footer do Formulário -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-2.5 pt-3 border-t border-slate-100">
                    <button type="button" onclick="restaurarPadraoErpColaborador()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-600 hover:text-rose-700 font-bold text-xs transition flex items-center gap-1.5 self-start sm:self-auto cursor-pointer" title="Limpar personalizações e voltar ao padrão original do ERP">
                        <i class="fa-solid fa-rotate-left"></i> Restaurar ERP
                    </button>
                    <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                        <button type="button" onclick="fecharModalEditarPerfilColab()" class="p-2.5 px-4 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition">
                            Cancelar
                        </button>
                        <button type="submit" id="btnSalvarPerfilColab" class="ch-btn py-2.5 px-5 text-xs bg-indigo-600 hover:bg-indigo-700">
                            <i class="fa-solid fa-check"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- =================================================== -->
    <!-- TOOLTIP MODERNO FLUTUANTE (RICH HOVER POPOVER)       -->
    <!-- =================================================== -->
    <div id="modernTooltipChapd" class="pointer-events-none fixed z-[9999] hidden max-w-xs rounded-2xl bg-slate-900/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md border border-slate-700/60 transition-opacity duration-150">
        <div class="flex items-center justify-between gap-2 mb-1.5">
            <span id="tooltipChapdBadge" class="text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50">INFORMAÇÃO</span>
            <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
        </div>
        <div id="tooltipChapdTitle" class="font-black text-white text-xs mb-1">Título do Recurso</div>
        <div id="tooltipChapdDesc" class="text-slate-300 text-[11px] font-medium leading-relaxed">Explicação do item ao passar o mouse.</div>
    </div>

</div>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>

