<?php
$pageTitle = 'Transferência de Flex | Nutricional';
$moduleJs = 'transferencia-flex.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .tf-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .tf-hero { background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 50%, #7c3aed 100%); }
  .tf-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .tf-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .tf-input { width: 100%; margin-top: 0.35rem; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.75rem 1rem; font-size: 0.95rem; background: #f8fafc; font-weight: 600; color: #1e293b; transition: all 0.2s; }
  .tf-input:focus { outline: none; border-color: #8b5cf6; background: #ffffff; box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.12); }
  .tf-tabs { display: flex; gap: 0.4rem; overflow-x: auto; padding: 0.35rem; background: #ffffff; border-radius: 1.25rem; border: 1px solid #f1f5f9; box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04); }
  .tf-tab { flex: 1; white-space: nowrap; padding: 0.7rem 1.2rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; color: #64748b; transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; cursor: pointer; }
  .tf-tab.on { background: linear-gradient(135deg, #6d28d9, #8b5cf6); color: #ffffff; box-shadow: 0 6px 16px rgba(109, 40, 217, 0.3); }
  .tf-tab:not(.on):hover { background: #f8fafc; color: #1e293b; }
  .tf-kpi { border-radius: 1.25rem; padding: 1.15rem; transition: transform 0.2s; }
  .tf-kpi:hover { transform: translateY(-2px); }
  .tf-btn { background: linear-gradient(135deg, #7c3aed, #8b5cf6); color: #ffffff; font-weight: 800; border-radius: 1rem; padding: 0.95rem 1.6rem; box-shadow: 0 8px 22px rgba(139, 92, 246, 0.35); transition: all 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
  .tf-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 10px 26px rgba(139, 92, 246, 0.45); }
  .tf-btn:disabled { opacity: 0.5; cursor: not-allowed; }
  .tf-chip { font-size: 0.72rem; font-weight: 800; padding: 0.35rem 0.65rem; border-radius: 0.75rem; display: inline-flex; align-items: center; gap: 0.35rem; }
  .tf-saldo-box { border-radius: 1.25rem; padding: 1rem; transition: all 0.2s; }
  .tf-mov-card { border: 1px solid #f1f5f9; border-radius: 1.25rem; padding: 1rem; background: #ffffff; transition: all 0.15s ease; }
  .tf-mov-card:hover { border-color: #c4b5fd; background: #faf5ff; }
  .tf-mov-card.falha { background: #fef2f2; border-color: #fecaca; }
  .pulse-dot { width: 8px; height: 8px; border-radius: 999px; background: #10b981; animation: pulseDot 2s infinite; }
  @keyframes pulseDot { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.2); } }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="tf-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-8">

    <!-- HERO HEADER -->
    <div class="tf-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white no-underline transition shadow-sm" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="tf-chip bg-white/20 text-white backdrop-blur-sm"><i class="fa-solid fa-right-left"></i> Gestão de Verbas</span>
                        <span id="badgeApi" class="tf-chip bg-emerald-500/25 text-emerald-200 border border-emerald-400/30">
                            <span class="pulse-dot"></span> Mercos Online
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1">Transferência de Verba Flex</h1>
                    <p class="text-xs sm:text-sm text-purple-100 opacity-90 mt-0.5">Manejo seguro e transparente de saldo flex entre representantes (ERP + Mercos)</p>
                </div>
            </div>

            <!-- FILIAL & ATALHOS -->
            <div class="flex flex-wrap items-center gap-2.5">
                <div class="bg-white/15 backdrop-blur-md rounded-2xl p-2 px-3.5 border border-white/20 flex items-center gap-2">
                    <i class="fa-solid fa-building text-violet-200 text-sm"></i>
                    <div>
                        <div class="text-[0.65rem] uppercase tracking-wider text-violet-200 font-bold">Filial Selecionada</div>
                        <select id="selFilial" class="bg-transparent text-white font-extrabold text-sm focus:outline-none cursor-pointer pr-2">
                            <option value="1" class="text-slate-800 font-bold">Matriz 1</option>
                            <option value="6" class="text-slate-800 font-bold">Filial 6 (PR)</option>
                        </select>
                    </div>
                </div>

                <a href="<?= $appBase ?>/portal/modules/verbas-flex.php" class="bg-white/20 hover:bg-white/30 text-white font-bold text-xs sm:text-sm py-2.5 px-4 rounded-2xl transition flex items-center gap-2 no-underline shadow-sm">
                    <i class="fa-solid fa-chart-pie"></i> Verbas Flex
                </a>
            </div>
        </div>
    </div>

    <!-- 4 KPI CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <!-- KPI 1: Transferido no Mês -->
        <div class="tf-kpi bg-gradient-to-br from-violet-50 to-indigo-50/70 border border-violet-100 shadow-sm">
            <div class="flex items-center justify-between text-violet-600 mb-2">
                <span class="tf-label text-violet-700">Transferido no Mês</span>
                <div class="w-8 h-8 rounded-xl bg-violet-100 flex items-center justify-center"><i class="fa-solid fa-money-bill-transfer text-xs"></i></div>
            </div>
            <div id="kpiTotalMes" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div id="kpiQtdMes" class="text-xs text-slate-500 font-semibold mt-1">0 operações concluídas</div>
        </div>

        <!-- KPI 2: Saldo Total Equipe -->
        <div class="tf-kpi bg-gradient-to-br from-emerald-50 to-teal-50/70 border border-emerald-100 shadow-sm">
            <div class="flex items-center justify-between text-emerald-600 mb-2">
                <span class="tf-label text-emerald-700">Saldo Total Equipe</span>
                <div class="w-8 h-8 rounded-xl bg-emerald-100 flex items-center justify-center"><i class="fa-solid fa-wallet text-xs"></i></div>
            </div>
            <div id="kpiSaldoEquipe" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div id="kpiTotalReps" class="text-xs text-slate-500 font-semibold mt-1">0 representantes ativos</div>
        </div>

        <!-- KPI 3: Meu Saldo Gestor -->
        <div class="tf-kpi bg-gradient-to-br from-purple-50 to-pink-50/70 border border-purple-100 shadow-sm">
            <div class="flex items-center justify-between text-purple-600 mb-2">
                <span class="tf-label text-purple-700">Meu Saldo Gestor</span>
                <div class="w-8 h-8 rounded-xl bg-purple-100 flex items-center justify-center"><i class="fa-solid fa-user-shield text-xs"></i></div>
            </div>
            <div id="kpiSaldoGestor" class="text-xl sm:text-2xl font-black text-slate-800">R$ 0,00</div>
            <div id="kpiNomeGestor" class="text-xs text-slate-500 font-semibold mt-1 truncate">Conta do Gestor</div>
        </div>

        <!-- KPI 4: Última Transferência -->
        <div class="tf-kpi bg-gradient-to-br from-blue-50 to-cyan-50/70 border border-blue-100 shadow-sm">
            <div class="flex items-center justify-between text-blue-600 mb-2">
                <span class="tf-label text-blue-700">Última Operação</span>
                <div class="w-8 h-8 rounded-xl bg-blue-100 flex items-center justify-center"><i class="fa-solid fa-clock-rotate-left text-xs"></i></div>
            </div>
            <div id="kpiUltimaValor" class="text-xl sm:text-2xl font-black text-slate-800">--</div>
            <div id="kpiUltimaDesc" class="text-xs text-slate-500 font-semibold mt-1 truncate">Nenhuma recente</div>
        </div>
    </div>

    <!-- ABAS DO MÓDULO -->
    <div class="tf-tabs mb-5">
        <button id="tabBtnTransferir" class="tf-tab on" onclick="alternarAba('transferir')">
            <i class="fa-solid fa-right-left"></i> Realizar Transferência
        </button>
        <button id="tabBtnEquipe" class="tf-tab" onclick="alternarAba('equipe')">
            <i class="fa-solid fa-users"></i> Saldos da Equipe <span id="badgeCountReps" class="ml-1 text-[0.65rem] bg-violet-100 text-violet-700 px-2 py-0.5 rounded-full font-black">0</span>
        </button>
        <button id="tabBtnHistorico" class="tf-tab" onclick="alternarAba('historico')">
            <i class="fa-solid fa-clock-rotate-left"></i> Histórico & Auditoria
        </button>
    </div>

    <!-- ========================================== -->
    <!-- ABA 1: REALIZAR TRANSFERÊNCIA (COCKPIT)    -->
    <!-- ========================================== -->
    <div id="painelTransferir" class="space-y-5">

        <!-- GRID 3 COLUNAS: DE | VALOR/AÇÃO | PARA -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">

            <!-- COLUNA 1: ORIGEM (DE) - 4 COLS -->
            <div class="lg:col-span-4 tf-card p-4 sm:p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-violet-100 text-violet-700 font-black text-xs flex items-center justify-center">1</span>
                            <span class="font-extrabold text-sm text-slate-800">Origem dos Recursos (DE)</span>
                        </div>
                        <span class="tf-chip bg-violet-50 text-violet-700 border border-violet-200">Quem cede verba</span>
                    </div>

                    <label class="block mb-3">
                        <span class="tf-label">Representante / Gestor Emissor</span>
                        <select id="selDe" class="tf-input">
                            <option value="">Carregando representantes...</option>
                        </select>
                    </label>

                    <!-- CARD DE DETALHES DO EMISSOR (DE) -->
                    <div id="cardDetalhesDe" class="tf-saldo-box bg-slate-50 border border-slate-200 mt-3 hidden">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-2xl bg-violet-600 text-white font-black flex items-center justify-center text-sm shadow-sm" id="avatarDe">DE</div>
                            <div class="flex-1 min-w-0">
                                <div id="nomeDe" class="text-sm font-extrabold text-slate-800 truncate">--</div>
                                <div id="subDe" class="text-xs text-slate-500 font-semibold truncate">ID: -- · Gestor: --</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/80">
                            <div>
                                <span class="text-[0.65rem] font-extrabold uppercase text-slate-500">Saldo Atual Mercos</span>
                                <div id="saldoAtualDe" class="text-base font-black text-violet-700">R$ 0,00</div>
                            </div>
                            <div>
                                <span class="text-[0.65rem] font-extrabold uppercase text-slate-500">Saldo Pós-Débito</span>
                                <div id="saldoPosDe" class="text-base font-black text-slate-700">R$ 0,00</div>
                            </div>
                        </div>

                        <div id="avisoSaldoInsuficiente" class="mt-2.5 p-2 rounded-xl bg-red-50 border border-red-200 text-red-600 text-xs font-bold flex items-center gap-2 hidden">
                            <i class="fa-solid fa-triangle-exclamation"></i> Saldo insuficiente para este valor!
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400 font-semibold">
                    <span><i class="fa-solid fa-check-double text-emerald-500 mr-1"></i> Apenas ativos ERP</span>
                    <span><i class="fa-solid fa-cloud text-violet-500 mr-1"></i> Vínculo Mercos</span>
                </div>
            </div>

            <!-- COLUNA 2: VALOR, MOTIVO E CONFIRMAÇÃO - 4 COLS -->
            <div class="lg:col-span-4 tf-card p-4 sm:p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-indigo-100 text-indigo-700 font-black text-xs flex items-center justify-center">2</span>
                            <span class="font-extrabold text-sm text-slate-800">Valor e Motivo</span>
                        </div>
                        <span class="tf-chip bg-indigo-50 text-indigo-700 border border-indigo-200">Operação</span>
                    </div>

                    <!-- VALOR COM BOTÕES RÁPIDOS -->
                    <label class="block mb-2">
                        <span class="tf-label">Valor a Transferir <span class="text-red-500">*</span></span>
                        <div class="relative mt-1">
                            <input id="valor" type="text" inputmode="numeric" placeholder="R$ 0,00" class="tf-input text-center text-xl font-black text-violet-700 pl-4 pr-4">
                        </div>
                    </label>

                    <div class="flex flex-wrap gap-1.5 mb-3">
                        <button type="button" class="btn-fast-val text-xs font-bold py-1 px-2.5 rounded-lg bg-slate-100 hover:bg-violet-100 text-slate-700 hover:text-violet-700 transition" onclick="adicionarValor(50)">+50</button>
                        <button type="button" class="btn-fast-val text-xs font-bold py-1 px-2.5 rounded-lg bg-slate-100 hover:bg-violet-100 text-slate-700 hover:text-violet-700 transition" onclick="adicionarValor(100)">+100</button>
                        <button type="button" class="btn-fast-val text-xs font-bold py-1 px-2.5 rounded-lg bg-slate-100 hover:bg-violet-100 text-slate-700 hover:text-violet-700 transition" onclick="adicionarValor(200)">+200</button>
                        <button type="button" class="btn-fast-val text-xs font-bold py-1 px-2.5 rounded-lg bg-slate-100 hover:bg-violet-100 text-slate-700 hover:text-violet-700 transition" onclick="adicionarValor(500)">+500</button>
                        <button type="button" class="btn-fast-val text-xs font-bold py-1 px-2.5 rounded-lg bg-amber-50 hover:bg-amber-100 text-amber-800 transition" onclick="usarSaldoTotal()">Saldo Todo</button>
                        <button type="button" class="text-xs font-bold py-1 px-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 transition ml-auto" onclick="zerarValor()">Limpar</button>
                    </div>

                    <!-- MOTIVO -->
                    <label class="block mb-3">
                        <div class="flex justify-between items-center">
                            <span class="tf-label">Motivo da Transferência <span class="text-red-500">*</span></span>
                            <span id="charCount" class="text-[0.65rem] font-bold text-slate-400">0 / 255</span>
                        </div>
                        <textarea id="motivo" maxlength="255" rows="2" placeholder="Ex: Auxílio verba para fechamento de pedido..." class="tf-input text-xs sm:text-sm font-semibold resize-none"></textarea>
                    </label>

                    <!-- PREVIEW DA OBSERVAÇÃO QUE VAI PARA O MERCOS (TRANSPARÊNCIA TOTAL) -->
                    <div class="p-3 rounded-2xl bg-violet-50/70 border border-dashed border-violet-300 text-xs">
                        <div class="font-extrabold text-violet-900 flex items-center gap-1.5 mb-1">
                            <i class="fa-solid fa-file-contract text-violet-600"></i> Observação registrada no Mercos:
                        </div>
                        <div id="previewObsMercos" class="text-violet-800 font-semibold italic break-words leading-relaxed">
                            Preencha os campos para visualizar a auditoria exata...
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100">
                    <button id="btnTransferir" class="tf-btn w-full" disabled onclick="confirmarTransferencia()">
                        <i class="fa-solid fa-right-left"></i> Realizar Transferência
                    </button>
                </div>
            </div>

            <!-- COLUNA 3: DESTINO (PARA) - 4 COLS -->
            <div class="lg:col-span-4 tf-card p-4 sm:p-5 flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700 font-black text-xs flex items-center justify-center">3</span>
                            <span class="font-extrabold text-sm text-slate-800">Destino dos Recursos (PARA)</span>
                        </div>
                        <span class="tf-chip bg-emerald-50 text-emerald-700 border border-emerald-200">Quem recebe verba</span>
                    </div>

                    <label class="block mb-3">
                        <span class="tf-label">Representante / Gestor Destinatário</span>
                        <select id="selPara" class="tf-input">
                            <option value="">Selecione o emissor primeiro...</option>
                        </select>
                    </label>

                    <!-- CARD DE DETALHES DO DESTINATÁRIO (PARA) -->
                    <div id="cardDetalhesPara" class="tf-saldo-box bg-slate-50 border border-slate-200 mt-3 hidden">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 rounded-2xl bg-emerald-600 text-white font-black flex items-center justify-center text-sm shadow-sm" id="avatarPara">PARA</div>
                            <div class="flex-1 min-w-0">
                                <div id="nomePara" class="text-sm font-extrabold text-slate-800 truncate">--</div>
                                <div id="subPara" class="text-xs text-slate-500 font-semibold truncate">ID: -- · Gestor: --</div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200/80">
                            <div>
                                <span class="text-[0.65rem] font-extrabold uppercase text-slate-500">Saldo Atual Mercos</span>
                                <div id="saldoAtualPara" class="text-base font-black text-emerald-700">R$ 0,00</div>
                            </div>
                            <div>
                                <span class="text-[0.65rem] font-extrabold uppercase text-slate-500">Saldo Pós-Crédito</span>
                                <div id="saldoPosPara" class="text-base font-black text-emerald-800 flex items-center gap-1">
                                    <span>R$ 0,00</span>
                                </div>
                            </div>
                        </div>

                        <div id="tagIncrementoPara" class="mt-2.5 p-2 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs font-bold flex items-center justify-between hidden">
                            <span><i class="fa-solid fa-arrow-trend-up mr-1"></i> Incremento:</span>
                            <span id="valorIncremento" class="font-black text-emerald-800">+ R$ 0,00</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400 font-semibold">
                    <span><i class="fa-solid fa-shield-halved text-emerald-500 mr-1"></i> Validação em 2 etapas</span>
                    <span><i class="fa-solid fa-bolt text-amber-500 mr-1"></i> Efeito imediato</span>
                </div>
            </div>

        </div>

    </div>

    <!-- ========================================== -->
    <!-- ABA 2: SALDOS DA EQUIPE                    -->
    <!-- ========================================== -->
    <div id="painelEquipe" class="tf-card p-4 sm:p-6 hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-users text-violet-600"></i> Representantes & Gestores com Conta Flex
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Visão unificada de saldo atual e atalhos rápidos para transferir</p>
            </div>
            <div class="flex items-center gap-2">
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                    <input id="buscaEquipe" type="text" placeholder="Buscar por nome ou ID..." class="tf-input py-2 pl-9 pr-3 text-xs w-60 sm:w-72" oninput="filtrarEquipe()">
                </div>
                <button onclick="carregarContexto(true)" class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold text-xs transition" title="Atualizar saldos">
                    <i class="fa-solid fa-rotate"></i>
                </button>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-[0.7rem] uppercase tracking-wider text-slate-400 font-extrabold">
                        <th class="py-3 px-3">Representante / Razão</th>
                        <th class="py-3 px-3">Gestor</th>
                        <th class="py-3 px-3 text-right">Saldo Mercos</th>
                        <th class="py-3 px-3 text-center">Status</th>
                        <th class="py-3 px-3 text-center">Ações Rápidas</th>
                    </tr>
                </thead>
                <tbody id="tabelaEquipe" class="divide-y divide-slate-100">
                    <tr><td colspan="5" class="py-8 text-center text-slate-400">Carregando representantes...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- ABA 3: HISTÓRICO & AUDITORIA               -->
    <!-- ========================================== -->
    <div id="painelHistorico" class="tf-card p-4 sm:p-6 hidden">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4 pb-3 border-b border-slate-100">
            <div>
                <h3 class="text-base font-extrabold text-slate-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-violet-600"></i> Histórico de Transferências
                </h3>
                <p class="text-xs text-slate-500 mt-0.5">Auditoria completa com registros no Mercos e log interno</p>
            </div>
            <div class="flex items-center gap-2">
                <button id="btnExcel" class="py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-sm transition flex items-center gap-2" onclick="baixarExcel()">
                    <i class="fa-solid fa-file-excel"></i> Exportar Excel
                </button>
            </div>
        </div>

        <!-- FILTROS DO HISTÓRICO -->
        <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-3">
                <label>
                    <span class="tf-label">Emissor (DE)</span>
                    <select id="fDe" class="tf-input text-xs"><option value="">Todos</option></select>
                </label>
                <label>
                    <span class="tf-label">Destinatário (PARA)</span>
                    <select id="fPara" class="tf-input text-xs"><option value="">Todos</option></select>
                </label>
                <label>
                    <span class="tf-label">Data Inicial</span>
                    <input id="dtIni" type="date" class="tf-input text-xs">
                </label>
                <label>
                    <span class="tf-label">Data Final</span>
                    <input id="dtFim" type="date" class="tf-input text-xs">
                </label>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-200/80">
                <div class="flex items-center gap-1.5">
                    <span class="tf-label mr-1">Atalhos:</span>
                    <button type="button" class="text-xs font-bold py-1 px-2.5 rounded-lg bg-white border border-slate-200 hover:bg-violet-50 hover:text-violet-700 transition" onclick="filtroDataRapido('hoje')">Hoje</button>
                    <button type="button" class="text-xs font-bold py-1 px-2.5 rounded-lg bg-white border border-slate-200 hover:bg-violet-50 hover:text-violet-700 transition" onclick="filtroDataRapido('7d')">7 Dias</button>
                    <button type="button" class="text-xs font-bold py-1 px-2.5 rounded-lg bg-white border border-slate-200 hover:bg-violet-50 hover:text-violet-700 transition" onclick="filtroDataRapido('mes')">Este Mês</button>
                </div>

                <div class="flex items-center gap-2 ml-auto">
                    <button class="py-2 px-4 rounded-xl bg-violet-600 hover:bg-violet-700 text-white font-bold text-xs transition" onclick="carregarMovimentacoes()">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i> Filtrar
                    </button>
                    <button class="py-2 px-3 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-700 font-bold text-xs transition" onclick="limparFiltros()">
                        Limpar
                    </button>
                </div>
            </div>
        </div>

        <!-- LISTA / TABELA DE HISTÓRICO -->
        <div id="listaMovimentacoes" class="space-y-3">
            <div class="text-center py-10 text-slate-400 text-sm">Carregando histórico...</div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
