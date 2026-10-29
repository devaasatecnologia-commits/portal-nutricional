<?php
$pageTitle = 'Movimentação de Flex | Nutricional';
$moduleJs = 'movimentacao-flex.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .mf-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .mf-hero { background: linear-gradient(135deg, #064e3b 0%, #047857 50%, #059669 100%); }
  .mf-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .mf-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .mf-input { width: 100%; margin-top: 0.35rem; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.75rem 1rem; font-size: 0.95rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
  .mf-input:focus { outline: none; border-color: #059669; background: #ffffff; box-shadow: 0 0 0 4px rgba(5, 150, 105, 0.12); }
  .mf-tabs { display: flex; gap: 0.4rem; overflow-x: auto; padding: 0.35rem; background: #ffffff; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
  .mf-tab { flex: 1; white-space: nowrap; padding: 0.7rem 1.2rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; color: #64748b; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; }
  .mf-tab.on { background: linear-gradient(135deg, #047857, #10b981); color: #ffffff; box-shadow: 0 6px 16px rgba(4, 120, 87, 0.3); }
  .mf-tab:not(.on):hover { background: #f8fafc; color: #1e293b; }
  .mf-kpi { border-radius: 1.25rem; padding: 1.15rem; transition: transform 0.2s; }
  .mf-kpi:hover { transform: translateY(-2px); }
  .mf-btn { background: linear-gradient(135deg, #059669, #10b981); color: #ffffff; font-weight: 800; border-radius: 1rem; padding: 0.95rem 1.6rem; box-shadow: 0 8px 22px rgba(5, 150, 105, 0.35); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
  .mf-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(5, 150, 105, 0.45); }
  .mf-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .mf-chip { font-size: 0.72rem; font-weight: 800; padding: 0.35rem 0.65rem; border-radius: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
  .pulse-dot { width: 8px; height: 8px; border-radius: 999px; background: #10b981; animation: pulseDot 2s infinite; }
  @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.2); } }
  .type-btn { padding: 0.75rem 1rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; cursor: pointer; border: 2px solid transparent; }
  .type-btn.active-credito { background: #ecfdf5; border-color: #059669; color: #047857; }
  .type-btn.active-debito { background: #fef2f2; border-color: #dc2626; color: #b91c1c; }
  .type-btn.inactive { background: #f8fafc; border-color: #e2e8f0; color: #64748b; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="mf-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-8">

    <!-- HERO HEADER -->
    <div class="mf-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="mf-chip bg-white/20 text-white backdrop-blur-sm"><i class="fa-solid fa-money-bill-transfer"></i> Gestão de Verbas</span>
                        <span id="badgeApi" class="mf-chip bg-emerald-500/25 text-emerald-200 border border-emerald-400/30">
                            <span class="pulse-dot"></span> Mercos Online
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1">Movimentação de Verba Flex</h1>
                    <p class="text-xs sm:text-sm text-emerald-100 opacity-90 mt-0.5">Adicione ou remova saldo de representantes com opção de cobertura automática do Gestor</p>
                </div>
            </div>

            <!-- FILIAL & ATALHOS -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="bg-white/15 backdrop-blur-md rounded-2xl p-2 px-3.5 border border-white/20 flex items-center gap-2">
                    <i class="fa-solid fa-building text-emerald-200 text-sm"></i>
                    <div>
                        <div class="text-[0.65rem] uppercase tracking-wider text-emerald-200 font-bold">Filial Selecionada</div>
                        <select id="selFilial" class="bg-transparent text-white font-extrabold text-sm focus:outline-none cursor-pointer pr-2">
                            <option value="1" class="text-slate-800 font-bold">Matriz 1 (SC)</option>
                            <option value="6" class="text-slate-800 font-bold">Filial 6 (PR)</option>
                        </select>
                    </div>
                </div>

                <a href="<?= $appBase ?>/portal/modules/verbas-flex.php" class="bg-white/20 hover:bg-white/30 text-white font-bold text-xs sm:text-sm py-2.5 px-3.5 rounded-2xl transition flex items-center gap-2 no-underline shadow-sm">
                    <i class="fa-solid fa-chart-pie"></i> Verbas Flex
                </a>

                <a href="<?= $appBase ?>/portal/modules/transferencia-flex.php" class="bg-white/20 hover:bg-white/30 text-white font-bold text-xs sm:text-sm py-2.5 px-3.5 rounded-2xl transition flex items-center gap-2 no-underline shadow-sm">
                    <i class="fa-solid fa-right-left"></i> Transferências
                </a>

                <button type="button" onclick="abrirModalManual()" class="bg-amber-400/90 hover:bg-amber-400 text-slate-900 font-extrabold text-xs sm:text-sm py-2.5 px-3.5 rounded-2xl transition flex items-center gap-2 shadow-sm cursor-pointer">
                    <i class="fa-solid fa-book-open"></i> Manual & Regras
                </button>
            </div>
        </div>
    </div>

    <!-- 4 KPI CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <!-- KPI 1: Saldo do Gestor da Filial -->
        <div class="mf-kpi bg-gradient-to-br from-emerald-50 to-teal-50/70 border border-emerald-100 shadow-sm">
            <div class="flex items-center justify-between text-emerald-600 mb-2">
                <span class="mf-label text-emerald-700">Saldo Gestor Filial</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center"><i class="fa-solid fa-user-shield text-xs"></i></div>
            </div>
            <div id="kpiSaldoGestor" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div id="kpiNomeGestor" class="text-xs text-slate-500 font-semibold mt-1 truncate">Carregando gestor...</div>
        </div>

        <!-- KPI 2: Total de Representantes com Vínculo -->
        <div class="mf-kpi bg-gradient-to-br from-blue-50 to-indigo-50/70 border border-blue-100 shadow-sm">
            <div class="flex items-center justify-between text-blue-600 mb-2">
                <span class="mf-label text-blue-700">Equipe Ativa</span>
                <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center"><i class="fa-solid fa-users text-xs"></i></div>
            </div>
            <div id="kpiTotalReps" class="text-xl sm:text-2xl font-black text-slate-800">0</div>
            <div class="text-xs text-slate-500 font-semibold mt-1">representantes ativos no Mercos</div>
        </div>

        <!-- KPI 3: Saldo da Carteira da Filial -->
        <div class="mf-kpi bg-gradient-to-br from-purple-50 to-pink-50/70 border border-purple-100 shadow-sm">
            <div class="flex items-center justify-between text-purple-600 mb-2">
                <span class="mf-label text-purple-700">Saldo Total Equipe</span>
                <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center"><i class="fa-solid fa-wallet text-xs"></i></div>
            </div>
            <div id="kpiSaldoEquipe" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div class="text-xs text-slate-500 font-semibold mt-1">soma de saldos da filial</div>
        </div>

        <!-- KPI 4: Última Movimentação -->
        <div class="mf-kpi bg-gradient-to-br from-amber-50 to-orange-50/70 border border-amber-100 shadow-sm">
            <div class="flex items-center justify-between text-amber-600 mb-2">
                <span class="mf-label text-amber-700">Última Movimentação</span>
                <div class="w-8 h-8 rounded-xl bg-amber-100 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left text-xs"></i></div>
            </div>
            <div id="kpiUltimaValor" class="text-xl sm:text-2xl font-black text-slate-800">--</div>
            <div id="kpiUltimaDesc" class="text-xs text-slate-500 font-semibold mt-1 truncate">Nenhuma recente</div>
        </div>
    </div>

    <!-- ABAS DO MÓDULO -->
    <div class="mf-tabs mb-5">
        <button id="tabBtnMovimentar" class="mf-tab on" onclick="alternarAba('movimentar')">
            <i class="fa-solid fa-sliders"></i> Realizar Movimentação
        </button>
        <button id="tabBtnEquipe" class="mf-tab" onclick="alternarAba('equipe')">
            <i class="fa-solid fa-users"></i> Saldos da Equipe <span id="badgeCountReps" class="ml-1 text-[0.65rem] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-black">0</span>
        </button>
        <button id="tabBtnHistorico" class="mf-tab" onclick="alternarAba('historico')">
            <i class="fa-solid fa-clock-rotate-left"></i> Histórico & Auditoria
        </button>
    </div>

    <!-- ========================================== -->
    <!-- ABA 1: REALIZAR MOVIMENTAÇÃO (COCKPIT)     -->
    <!-- ========================================== -->
    <div id="painelMovimentar" class="space-y-5">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

            <!-- COLUNA ESQUERDA: REPRESENTANTE E SALDOS (5 COLS) -->
            <div class="lg:col-span-5 space-y-4">

                <!-- CARD 1: SELETOR DE REPRESENTANTE -->
                <div class="mf-card p-5">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 font-black text-xs flex items-center justify-center">1</span>
                            <span class="font-extrabold text-sm text-slate-800">Representante Comercial</span>
                        </div>
                        <span class="mf-chip bg-emerald-50 text-emerald-700 border border-emerald-200">Mercos Ativo</span>
                    </div>

                    <label class="block mb-4">
                        <span class="mf-label">Selecione o Representante</span>
                        <select id="selRep" class="mf-input">
                            <option value="">Carregando representantes...</option>
                        </select>
                    </label>

                    <!-- CARD DE SALDO DO REPRESENTANTE SELECIONADO -->
                    <div id="boxSaldoRep" class="p-4 rounded-2xl border transition-all bg-slate-50 border-slate-200">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Saldo Atual no Mercos</span>
                            <span id="badgeStatusSaldoRep" class="text-[0.7rem] font-extrabold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700">Selecione</span>
                        </div>
                        <div id="txtSaldoRep" class="text-2xl font-black text-slate-700 mt-1">R$ 0,00</div>
                        <div id="txtDetalheRep" class="text-xs text-slate-400 font-semibold mt-1">Nenhum colaborador selecionado</div>
                    </div>
                </div>

                <!-- CARD 2: GESTOR DE COBERTURA DA FILIAL -->
                <div class="mf-card p-5 bg-gradient-to-br from-slate-50 to-emerald-50/40">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-emerald-600 text-white font-black text-xs flex items-center justify-center"><i class="fa-solid fa-shield"></i></span>
                            <span class="font-extrabold text-sm text-slate-800">Gestor de Cobertura</span>
                        </div>
                        <span class="mf-chip bg-white text-emerald-700 border border-emerald-200 font-bold">Fonte de Empréstimo</span>
                    </div>

                    <div class="p-3.5 bg-white rounded-2xl border border-emerald-100 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div>
                                <div id="txtNomeGestorCard" class="font-extrabold text-sm text-slate-800">TIAGO (MOV)</div>
                                <div id="txtCargoGestorCard" class="text-xs text-slate-400 font-semibold">Gestor Matriz (Filial 1)</div>
                            </div>
                            <div class="text-right">
                                <div class="text-[0.65rem] uppercase font-bold text-slate-400">Saldo Disponível</div>
                                <div id="txtSaldoGestorCard" class="text-lg font-black text-emerald-700">R$ 0,00</div>
                            </div>
                        </div>
                    </div>
                    <p class="text-[0.75rem] text-slate-500 mt-3 leading-relaxed">
                        <i class="fa-solid fa-circle-info text-emerald-600 mr-1"></i>
                        Se a opção de cobertura estiver ativa e o representante não possuir saldo suficiente, o valor necessário sairá automaticamente deste gestor.
                    </p>
                </div>
            </div>

            <!-- COLUNA DIREITA: CONFIGURAÇÃO DA MOVIMENTAÇÃO (7 COLS) -->
            <div class="lg:col-span-7 space-y-4">
                <div class="mf-card p-5 sm:p-6 space-y-5">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 font-black text-xs flex items-center justify-center">2</span>
                            <span class="font-extrabold text-sm sm:text-base text-slate-800">Parâmetros da Movimentação</span>
                        </div>
                        <span class="text-xs text-slate-400 font-bold">Validação em tempo real</span>
                    </div>

                    <!-- TIPO DE MOVIMENTAÇÃO (CREDITO / DEBITO) -->
                    <div>
                        <span class="mf-label mb-2 block">Tipo de Operação</span>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" id="btnTipoCredito" onclick="definirTipo('CREDITO')" class="type-btn inactive">
                                <i class="fa-solid fa-circle-plus text-base"></i>
                                <span>Adicionar Saldo (+)</span>
                            </button>
                            <button type="button" id="btnTipoDebito" onclick="definirTipo('DEBITO')" class="type-btn active-debito">
                                <i class="fa-solid fa-circle-minus text-base"></i>
                                <span>Remover Saldo (-)</span>
                            </button>
                        </div>
                    </div>

                    <!-- VALOR -->
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="mf-label">Valor da Operação (R$)</span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" onclick="adicionarValor(50)" class="text-[0.7rem] font-bold px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">+50</button>
                                <button type="button" onclick="adicionarValor(100)" class="text-[0.7rem] font-bold px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">+100</button>
                                <button type="button" onclick="adicionarValor(200)" class="text-[0.7rem] font-bold px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">+200</button>
                                <button type="button" onclick="adicionarValor(500)" class="text-[0.7rem] font-bold px-2 py-0.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 transition">+500</button>
                                <button type="button" onclick="zerarValor()" class="text-[0.7rem] font-bold px-2 py-0.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 transition">Limpar</button>
                            </div>
                        </div>
                        <input id="inpValor" type="text" placeholder="R$ 0,00" class="mf-input text-xl sm:text-2xl font-black text-slate-800" oninput="formatarInputMoeda()">
                    </div>

                    <!-- OBSERVAÇÃO (OBRIGATÓRIA) -->
                    <div>
                        <div class="flex items-center justify-between">
                            <span class="mf-label">Observação / Justificativa <b class="text-rose-500">*OBRIGATÓRIA</b></span>
                            <span id="charCount" class="text-xs text-slate-400 font-bold">0 / 255</span>
                        </div>
                        <textarea id="inpObservacao" rows="2" maxlength="255" placeholder="Ex: Remoção para ajuste de saldo..." class="mf-input resize-none font-semibold text-sm" oninput="atualizarPrevisoes()"></textarea>
                    </div>

                    <!-- CHECKBOX: TIRAR SALDO DO GESTOR -->
                    <div id="boxOpcaoGestor" class="p-4 rounded-2xl border-2 border-emerald-200 bg-emerald-50/50 transition-all">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input id="chkUsarGestor" type="checkbox" checked onchange="atualizarPrevisoes()" class="mt-1 w-5 h-5 rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer">
                            <div>
                                <span class="font-extrabold text-sm text-slate-800 block">Tirar saldo do gestor se não houver saldo suficiente</span>
                                <span id="txtExplicacaoGestor" class="text-xs text-slate-600 font-medium leading-relaxed block mt-0.5">
                                    Se o representante possuir saldo menor que o solicitado (ou estiver zerado/negativo), a diferença necessária será retirada automaticamente do Gestor da filial como empréstimo.
                                </span>
                            </div>
                        </label>
                    </div>

                    <!-- PREVIEW DINÂMICO DAS ETAPAS -->
                    <div id="boxPreview" class="p-4 rounded-2xl border border-slate-200 bg-slate-50 space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-black uppercase text-slate-600 tracking-wider flex items-center gap-1.5">
                                <i class="fa-solid fa-list-check text-emerald-600"></i> Simulação das Ações no Mercos
                            </span>
                            <span id="badgePreviewStatus" class="text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600">Aguardando dados</span>
                        </div>
                        <div id="listaEtapasPreview" class="space-y-2 text-xs">
                            <p class="text-slate-400 italic">Selecione um representante e informe o valor para simular a operação.</p>
                        </div>
                    </div>

                    <!-- BOTÃO DE DISPARO -->
                    <div>
                        <button type="button" id="btnExecutar" onclick="confirmarExecucao()" disabled class="mf-btn w-full py-4 text-base tracking-wide">
                            <i class="fa-solid fa-bolt"></i> Executar Movimentação no Mercos
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- ABA 2: SALDOS DA EQUIPE                    -->
    <!-- ========================================== -->
    <div id="painelEquipe" class="hidden space-y-4">
        <div class="mf-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Saldos da Equipe Flex</h2>
                    <p class="text-xs text-slate-400 font-semibold">Representantes ativos no ERP e integrados à API Mercos na filial selecionada</p>
                </div>
                <div class="flex items-center gap-2">
                    <input id="filtroEquipe" type="text" placeholder="Buscar por nome ou código..." oninput="filtrarEquipe()" class="px-3.5 py-2 text-xs rounded-xl border border-slate-200 font-semibold focus:outline-none focus:border-emerald-500 w-64 bg-slate-50">
                    <button type="button" onclick="carregarRepresentantes(true)" class="p-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition" title="Atualizar saldos no Mercos">
                        <i class="fa-solid fa-rotate"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider font-extrabold">
                            <th class="py-3 px-3">Cód.</th>
                            <th class="py-3 px-3">Representante / Nome</th>
                            <th class="py-3 px-3">Razão Social</th>
                            <th class="py-3 px-3">Gestor</th>
                            <th class="py-3 px-3 text-right">Saldo Atual (Mercos)</th>
                            <th class="py-3 px-3 text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaEquipeCorpo" class="divide-y divide-slate-100 font-semibold text-slate-700">
                        <tr><td colspan="6" class="text-center py-8 text-slate-400">Carregando dados da equipe...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- ABA 3: HISTÓRICO & AUDITORIA               -->
    <!-- ========================================== -->
    <div id="painelHistorico" class="hidden space-y-4">
        <div class="mf-card p-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h2 class="text-lg font-black text-slate-800">Histórico de Movimentações Manuais</h2>
                    <p class="text-xs text-slate-400 font-semibold">Registro de auditoria com rastreamento completo de operadores e empréstimos de gestor</p>
                </div>
                <button type="button" onclick="carregarHistorico()" class="p-2 px-3.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-bold transition flex items-center gap-1.5 self-start sm:self-auto">
                    <i class="fa-solid fa-rotate"></i> Atualizar
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-slate-400 uppercase tracking-wider font-extrabold">
                            <th class="py-3 px-3">Data / Hora</th>
                            <th class="py-3 px-3">Operador</th>
                            <th class="py-3 px-3">Filial</th>
                            <th class="py-3 px-3">Representante</th>
                            <th class="py-3 px-3">Tipo</th>
                            <th class="py-3 px-3 text-right">Valor Operação</th>
                            <th class="py-3 px-3 text-center">Gestor</th>
                            <th class="py-3 px-3">Observação</th>
                            <th class="py-3 px-3 text-center">Detalhes</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaHistoricoCorpo" class="divide-y divide-slate-100 font-semibold text-slate-700">
                        <tr><td colspan="9" class="text-center py-8 text-slate-400">Carregando histórico de auditoria...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- MODAL MANUAL DE USABILIDADE & REGRAS       -->
    <!-- ========================================== -->
    <div id="modalManual" class="fixed inset-0 z-50 hidden bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-3 sm:p-5">
        <div class="bg-white rounded-3xl max-w-3xl w-full max-h-[90vh] flex flex-col shadow-2xl overflow-hidden border border-slate-100 animate-fadeIn">
            <!-- Header do Modal -->
            <div class="p-5 sm:p-6 bg-gradient-to-r from-emerald-700 to-teal-700 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-book-bookmark text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-black tracking-tight">Manual & Guia de Regras de Negócio</h3>
                        <p class="text-xs text-emerald-100 font-semibold">Módulo: Movimentação de Verba Flex (Mercos) • MOD-MOVIMENTACAO-FLEX</p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalManual()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Corpo do Modal -->
            <div class="p-5 sm:p-6 overflow-y-auto space-y-5 text-sm text-slate-600">
                
                <!-- Card 1: Como Funciona -->
                <div class="p-4 rounded-2xl bg-emerald-50/60 border border-emerald-100">
                    <h4 class="font-black text-emerald-900 flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-circle-info text-emerald-600"></i> Como Funciona o Módulo
                    </h4>
                    <p class="text-xs text-slate-700 leading-relaxed">
                        Este módulo comunica-se diretamente com a <strong>API da Mercos (/saldo_flex)</strong> em tempo real. Ele permite aos gestores e à diretoria ajustar os saldos Flex dos representantes comerciais das duas filiais (Matriz 1 SC e Filial 6 PR) sem depender de intervenções manuais lentas.
                    </p>
                </div>

                <!-- Card 2: Os Gestores -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-[0.68rem] font-bold uppercase text-slate-400">Filial 1 (Matriz SC)</div>
                        <div class="font-extrabold text-slate-800 text-sm mt-0.5">Gestor: TIAGO (MOV)</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1">ID Mercos: 12335</div>
                    </div>
                    <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                        <div class="text-[0.68rem] font-bold uppercase text-slate-400">Filial 6 (Paraná PR)</div>
                        <div class="font-extrabold text-slate-800 text-sm mt-0.5">Gestor: TALES FERNANDO BINDE</div>
                        <div class="text-xs text-slate-500 font-semibold mt-1">ID Mercos: 15816</div>
                    </div>
                </div>

                <!-- Card 3: Regra do Empréstimo Automático -->
                <div class="p-4 rounded-2xl bg-amber-50/70 border border-amber-200">
                    <h4 class="font-black text-amber-900 flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-hand-holding-dollar text-amber-600"></i> Regra de Débito com Cobertura do Gestor
                    </h4>
                    <p class="text-xs text-amber-900 leading-relaxed mb-3">
                        Se você precisa remover um valor superior ao saldo do representante (ou caso ele esteja com saldo zerado ou negativo), marque a opção <strong>"Tirar saldo do gestor se faltar"</strong>. O sistema calcula automaticamente a diferença e executa 3 etapas ordenadas no histórico da Mercos:
                    </p>
                    <div class="space-y-2 text-xs">
                        <div class="p-2.5 rounded-xl bg-white border border-amber-200 flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-red-100 text-red-700 font-bold flex items-center justify-center flex-shrink-0 text-[0.65rem]">1</span>
                            <div>
                                <span class="font-bold text-slate-800">Débito no Gestor:</span> Retira apenas a diferença necessária.<br>
                                <span class="text-slate-500 italic">"Emprestimo para o representante [Nome] para movimentação : [Observação]"</span>
                            </div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white border border-amber-200 flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 font-bold flex items-center justify-center flex-shrink-0 text-[0.65rem]">2</span>
                            <div>
                                <span class="font-bold text-slate-800">Crédito no Representante:</span> Coloca o valor do empréstimo.<br>
                                <span class="text-slate-500 italic">"Emprestimo para movimentação : [Observação]"</span>
                            </div>
                        </div>
                        <div class="p-2.5 rounded-xl bg-white border border-amber-200 flex items-start gap-2.5">
                            <span class="w-5 h-5 rounded-full bg-blue-100 text-blue-700 font-bold flex items-center justify-center flex-shrink-0 text-[0.65rem]">3</span>
                            <div>
                                <span class="font-bold text-slate-800">Débito Final no Representante:</span> Retira o valor solicitado originalmente.<br>
                                <span class="text-slate-500 italic">"[Observação digitada pelo operador]"</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Bloqueios e Segurança -->
                <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200">
                    <h4 class="font-black text-slate-800 flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-shield-halved text-blue-600"></i> Travas de Segurança Ativas
                    </h4>
                    <ul class="list-disc list-inside text-xs text-slate-600 space-y-1">
                        <li><strong>Observação Obrigatória:</strong> Nunca é permitido lançar movimentação sem justificativa.</li>
                        <li><strong>Bloqueio de Saldo:</strong> Se a caixa do gestor não for marcada e o representante não tiver saldo suficiente, a transação é <strong>bloqueada</strong>.</li>
                        <li><strong>Saldo do Gestor Insuficiente:</strong> Se o gestor não possuir verba para cobrir o empréstimo, a transação é <strong>bloqueada</strong> com aviso explicativo.</li>
                        <li><strong>Auditoria Permanente:</strong> Toda movimentação é registrada na tabela <code>movimentacoes_flex_mercos_log</code> no PostgreSQL com dados do operador, IP e histórico.</li>
                    </ul>
                </div>

            </div>

            <!-- Rodapé do Modal -->
            <div class="p-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between">
                <span class="text-xs text-slate-400 font-semibold">Documentação técnica: <code>docs/modulos/MOD-MOVIMENTACAO-FLEX.md</code></span>
                <button type="button" onclick="fecharModalManual()" class="py-2 px-4 rounded-xl bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs transition">
                    Entendido, fechar
                </button>
            </div>
        </div>
    </div>

</div>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>

