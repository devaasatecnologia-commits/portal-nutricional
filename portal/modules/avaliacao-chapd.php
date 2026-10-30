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
  .ch-tabs { display: flex; gap: 0.4rem; overflow-x: auto; padding: 0.35rem; background: #ffffff; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
  .ch-tab { flex: 1; white-space: nowrap; padding: 0.75rem 1.25rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; color: #64748b; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; }
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

  /* Impressão */
  @media print {
    body * { visibility: hidden; }
    #modalFichaConteudo, #modalFichaConteudo * { visibility: visible; }
    #modalFichaConteudo { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
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
                <button type="button" onclick="iniciarNovaAvaliacao()" class="bg-white text-emerald-900 hover:bg-emerald-50 font-extrabold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-plus text-emerald-600"></i> Nova Avaliação
                </button>
                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=avaliacao-chapd" class="bg-amber-400 hover:bg-amber-300 text-slate-900 font-extrabold text-xs sm:text-sm py-2.5 px-3.5 rounded-2xl transition flex items-center gap-2 shadow-sm no-underline">
                    <i class="fa-solid fa-graduation-cap"></i> Treinamento & Manual
                </a>
            </div>
        </div>
    </div>

    <!-- ABAS DE NAVEGAÇÃO -->
    <div class="ch-tabs mb-5">
        <button type="button" id="tabBtnAvaliar" onclick="alternarAba('avaliar')" class="ch-tab on">
            <i class="fa-solid fa-clipboard-check"></i> <span>Avaliar Colaborador</span>
        </button>
        <button type="button" id="tabBtnDashboard" onclick="alternarAba('dashboard')" class="ch-tab">
            <i class="fa-solid fa-chart-pie"></i> <span>Dashboard Analytics</span>
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
    <div id="painelAvaliar" class="space-y-4">

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
                        <select id="selMes" class="ch-input mt-0">
                            <!-- JS preenche 1 a 12 -->
                        </select>
                        <select id="selAno" class="ch-input mt-0">
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
                    <div class="w-12 h-12 rounded-2xl bg-emerald-600 text-white font-black text-base flex items-center justify-center shadow-md" id="colabAvatar">
                        --
                    </div>
                    <div>
                        <div id="colabNome" class="font-black text-slate-800 text-sm sm:text-base">Nome do Colaborador</div>
                        <div class="flex flex-wrap items-center gap-2 text-xs text-slate-500 font-semibold mt-0.5">
                            <span id="colabCargo"><i class="fa-solid fa-briefcase text-emerald-600"></i> Cargo</span>
                            <span>•</span>
                            <span id="colabFilial"><i class="fa-solid fa-building text-emerald-600"></i> Filial</span>
                            <span>•</span>
                            <span id="colabAdmissao"><i class="fa-solid fa-calendar text-emerald-600"></i> Admissão</span>
                        </div>
                    </div>
                </div>
                <div id="statusAvalAnterior" class="text-xs font-bold text-slate-500 bg-white/80 px-3 py-1.5 rounded-xl border border-emerald-100">
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

            <!-- PLACAR DE PONTUAÇÃO & CLASSIFICAÇÃO -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <!-- Pontos Totais -->
                <div class="p-5 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white shadow-md flex items-center justify-between">
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
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 flex flex-col justify-between">
                    <div>
                        <div class="text-xs uppercase font-extrabold text-slate-400 tracking-wider">Classificação Oficial</div>
                        <div class="text-lg font-black text-slate-800 mt-1" id="resumoClassificacao">--</div>
                    </div>
                    <div id="resumoBadgeFaixa" class="mt-2 text-xs font-bold inline-flex items-center gap-1.5 px-3 py-1 rounded-xl bg-slate-200 text-slate-700 self-start">
                        Calculando faixa...
                    </div>
                </div>

                <!-- Conceito Letra Escolhida -->
                <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
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
                <div class="text-xs font-black uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-chart-simple text-emerald-600"></i> Desempenho por Pilar C.H.A.P.D
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    <div class="p-3 bg-white rounded-xl border border-slate-100">
                        <div class="text-[0.68rem] font-bold text-sky-700">CONHECIMENTO (C)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalC">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraC" class="bg-sky-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100">
                        <div class="text-[0.68rem] font-bold text-purple-700">HABILIDADE (H)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalH">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraH" class="bg-purple-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100">
                        <div class="text-[0.68rem] font-bold text-amber-700">ATITUDE (A)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalA">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraA" class="bg-amber-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100">
                        <div class="text-[0.68rem] font-bold text-emerald-700">POSTURA (P)</div>
                        <div class="text-lg font-black text-slate-800 mt-0.5" id="pilarTotalP">0 pts</div>
                        <div class="w-full bg-slate-100 h-1.5 rounded-full mt-1.5 overflow-hidden">
                            <div id="pilarBarraP" class="bg-emerald-500 h-full rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                    <div class="p-3 bg-white rounded-xl border border-slate-100 col-span-2 sm:col-span-1">
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
                    <label class="ch-label">Plano de Ação / PDI (Plano de Desenvolvimento Individual)</label>
                    <textarea id="inputPlanoAcao" rows="2" placeholder="Ações práticas, metas de capacitação, prazos acordados..." class="ch-input text-xs sm:text-sm"></textarea>
                </div>
            </div>

            <!-- BOTÃO SALVAR AVALIAÇÃO FINAL -->
            <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                <button type="button" onclick="cancelarAvaliacao()" class="p-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs sm:text-sm transition">
                    Descartar
                </button>
                <button type="button" id="btnSalvarAvaliacao" onclick="salvarAvaliacaoFinal()" class="ch-btn py-3 px-7 text-sm">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span>Concluir e Salvar Avaliação</span>
                </button>
            </div>
        </div>

    </div>

    <!-- =================================================== -->
    <!-- ABA 2: DASHBOARD & PEOPLE ANALYTICS                 -->
    <!-- =================================================== -->
    <div id="painelDashboard" class="hidden space-y-4">
        <!-- 4 CARDS KPIS -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="ch-kpi bg-gradient-to-br from-emerald-50 to-teal-50 border border-emerald-100 shadow-sm">
                <div class="flex items-center justify-between text-emerald-600 mb-1">
                    <span class="ch-label text-emerald-700">Total Avaliações</span>
                    <i class="fa-solid fa-users text-sm"></i>
                </div>
                <div id="kpiDashTotal" class="text-2xl font-black text-slate-800">0</div>
                <div class="text-[0.7rem] text-slate-500 font-semibold mt-1">no período filtrado</div>
            </div>

            <div class="ch-kpi bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 shadow-sm">
                <div class="flex items-center justify-between text-blue-600 mb-1">
                    <span class="ch-label text-blue-700">Média Geral</span>
                    <i class="fa-solid fa-chart-line text-sm"></i>
                </div>
                <div id="kpiDashMedia" class="text-2xl font-black text-slate-800">0.0 pts</div>
                <div id="kpiDashPercentual" class="text-[0.7rem] text-blue-600 font-bold mt-1">0% de aproveitamento</div>
            </div>

            <div class="ch-kpi bg-gradient-to-br from-purple-50 to-pink-50 border border-purple-100 shadow-sm">
                <div class="flex items-center justify-between text-purple-600 mb-1">
                    <span class="ch-label text-purple-700">Destaques (>125)</span>
                    <i class="fa-solid fa-star text-sm"></i>
                </div>
                <div id="kpiDashExcelentes" class="text-2xl font-black text-slate-800">0</div>
                <div class="text-[0.7rem] text-slate-500 font-semibold mt-1">quadro de excelência</div>
            </div>

            <div class="ch-kpi bg-gradient-to-br from-amber-50 to-orange-50 border border-amber-100 shadow-sm">
                <div class="flex items-center justify-between text-amber-600 mb-1">
                    <span class="ch-label text-amber-700">Necessitam PDI (<85)</span>
                    <i class="fa-solid fa-triangle-exclamation text-sm"></i>
                </div>
                <div id="kpiDashAtencao" class="text-2xl font-black text-slate-800">0</div>
                <div class="text-[0.7rem] text-slate-500 font-semibold mt-1">baixo potencial / suporte</div>
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
                    <select id="filtroDashFilial" onchange="carregarDashboard()" class="ch-input mt-0 py-1.5 px-3 text-xs w-36">
                        <option value="">Todas</option>
                        <option value="1">Matriz 1 (SC)</option>
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
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
                    <h3 class="font-black text-slate-800 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-hand-holding-heart text-rose-500"></i> Foco de Desenvolvimento (PDI)
                    </h3>
                    <span class="text-[0.7rem] text-rose-500 font-bold">Pontuação &lt; 85</span>
                </div>
                <div id="listaAtencaoColaboradores" class="space-y-2">
                    <div class="text-center py-6 text-slate-400 text-xs font-semibold">Nenhum colaborador em nível crítico no período.</div>
                </div>
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
                    <select id="filtroMatrizFilial" onchange="carregarMatrizAnual()" class="ch-input mt-0 py-1.5 px-3 text-xs w-36">
                        <option value="">Todas Filiais</option>
                        <option value="1">Matriz 1 (SC)</option>
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
            <div class="p-4 sm:p-5 bg-gradient-to-r from-emerald-800 to-teal-800 text-white flex items-center justify-between no-print">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-file-invoice text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black tracking-tight" id="fichaModalTitulo">Ficha de Avaliação C.H.A.P.D</h3>
                        <p class="text-xs text-emerald-100 font-semibold" id="fichaModalSubtitulo">Registro Oficial de Desempenho</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" onclick="imprimirFicha()" class="p-2 px-3.5 rounded-xl bg-white/20 hover:bg-white/30 text-white font-bold text-xs transition flex items-center gap-1.5">
                        <i class="fa-solid fa-print"></i> Imprimir / PDF
                    </button>
                    <button type="button" onclick="fecharModalFicha()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <!-- Conteúdo da Ficha (Área de Impressão) -->
            <div id="modalFichaConteudo" class="p-6 sm:p-8 overflow-y-auto space-y-6 text-sm text-slate-700 bg-white">
                <!-- Cabeçalho Oficial Tipo Planilha -->
                <div class="border-2 border-slate-800 rounded-2xl p-4 sm:p-5 bg-slate-50">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b-2 border-slate-800 pb-3 mb-3 gap-2">
                        <div>
                            <div class="text-[0.7rem] uppercase font-black text-slate-500 tracking-wider">NUTRICIONAL DISTRIBUIDORA</div>
                            <h2 class="text-xl font-black text-slate-900 tracking-tight">AVALIAÇÃO C.H.A.P.D</h2>
                            <div class="text-xs font-bold text-slate-600">Conhecimento • Habilidade • Atitude • Postura • Disciplina</div>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-bold text-slate-500">Período de Referência:</div>
                            <div class="text-base font-black text-slate-900" id="fichaPeriodo">Outubro / 2026</div>
                        </div>
                    </div>

                    <!-- Dados Cadastrais -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 font-bold block">COLABORADOR:</span>
                            <span class="font-black text-slate-900" id="fichaColaborador">--</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">CARGO / FUNÇÃO:</span>
                            <span class="font-extrabold text-slate-800" id="fichaCargo">--</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">SETOR:</span>
                            <span class="font-extrabold text-slate-800" id="fichaSetor">--</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">CÉLULA DE TRABALHO:</span>
                            <span class="font-extrabold text-slate-800" id="fichaCelula">--</span>
                        </div>
                    </div>
                </div>

                <!-- Resumo dos Resultados -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50 text-center">
                        <div class="text-[0.68rem] uppercase font-bold text-slate-400">PONTUAÇÃO TOTAL</div>
                        <div class="text-2xl font-black text-slate-900 mt-0.5" id="fichaPontos">0 / 140</div>
                        <div class="text-[0.7rem] text-slate-500 font-bold mt-0.5" id="fichaPercentual">0% de aproveitamento</div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50 text-center">
                        <div class="text-[0.68rem] uppercase font-bold text-slate-400">CLASSIFICAÇÃO</div>
                        <div class="text-base font-black text-slate-900 mt-1" id="fichaClassificacao">--</div>
                    </div>
                    <div class="p-3.5 rounded-xl border border-slate-200 bg-slate-50 text-center">
                        <div class="text-[0.68rem] uppercase font-bold text-slate-400">CONCEITO CARACTERÍSTICA</div>
                        <div class="text-2xl font-black text-emerald-700 mt-0.5" id="fichaLetra">--</div>
                    </div>
                </div>

                <!-- Tabela de Respostas Pergunta por Pergunta -->
                <div>
                    <h4 class="font-black text-slate-800 text-xs uppercase tracking-wider mb-2">Detalhamento das Questões Respondidas</h4>
                    <table class="w-full text-left text-xs border border-slate-200 rounded-xl overflow-hidden">
                        <thead class="bg-slate-100 text-slate-500 font-extrabold">
                            <tr>
                                <th class="p-2 text-center w-12">Pilar</th>
                                <th class="p-2">Critério Avaliado</th>
                                <th class="p-2 text-center w-28">Nota / Nível</th>
                                <th class="p-2">Observações</th>
                            </tr>
                        </thead>
                        <tbody id="fichaTabelaRespostas" class="divide-y divide-slate-100 font-medium text-slate-700">
                            <!-- Preenchido via JS -->
                        </tbody>
                    </table>
                </div>

                <!-- Observações e PDI -->
                <div class="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2.5 text-xs">
                    <div>
                        <b class="text-slate-800">Pontos Fortes:</b>
                        <span id="fichaPontosFortes" class="text-slate-600 block mt-0.5">--</span>
                    </div>
                    <div>
                        <b class="text-slate-800">Pontos a Desenvolver:</b>
                        <span id="fichaPontosDesenvolver" class="text-slate-600 block mt-0.5">--</span>
                    </div>
                    <div>
                        <b class="text-slate-800">Plano de Ação (PDI):</b>
                        <span id="fichaPlanoAcao" class="text-slate-600 block mt-0.5">--</span>
                    </div>
                </div>

                <!-- Assinaturas Formais (conforme planilha: Sandro Rafo / Robson Becker) -->
                <div class="pt-8 border-t border-slate-300 grid grid-cols-2 gap-8 text-center text-xs mt-6">
                    <div>
                        <div class="border-b border-slate-400 pb-1 mb-1 font-black text-slate-800" id="fichaAssinaturaAvaliador">Sandro Rafo</div>
                        <div class="text-slate-400 font-bold uppercase text-[0.68rem]">Elaboração / Avaliador</div>
                    </div>
                    <div>
                        <div class="border-b border-slate-400 pb-1 mb-1 font-black text-slate-800" id="fichaAssinaturaAprovador">Robson Becker</div>
                        <div class="text-slate-400 font-bold uppercase text-[0.68rem]">Aprovação / Diretoria</div>
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

</div>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>

