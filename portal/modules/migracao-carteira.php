<?php
$pageTitle = 'Migração de Carteira | Nutricional';
$moduleJs = 'migracao-carteira.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .mc-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .mc-hero { background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%); }
  .mc-card { background: #ffffff; border-radius: 1.5rem; box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.05); border: 1px solid #f1f5f9; }
  .mc-label { font-size: 0.72rem; font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; color: #64748b; }
  .mc-input { width: 100%; border: 2px solid #e2e8f0; border-radius: 1rem; padding: 0.65rem 1rem; font-size: 0.9rem; background: #f8fafc; transition: all 0.2s ease; font-weight: 600; color: #1e293b; }
  .mc-input:focus { outline: none; border-color: #0d9488; background: #ffffff; box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.12); }
  .mc-tab-btn { padding: 0.65rem 1.4rem; border-radius: 1rem; font-weight: 800; font-size: 0.85rem; transition: all 0.2s ease; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
  .mc-tab-btn.active { background: #0f766e; color: #ffffff; box-shadow: 0 4px 15px rgba(15, 118, 110, 0.35); }
  .mc-tab-btn:not(.active) { background: rgba(255, 255, 255, 0.2); color: #e6fffa; }
  .mc-tab-btn:not(.active):hover { background: rgba(255, 255, 255, 0.3); color: #ffffff; }
  .mc-badge { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.65rem; border-radius: 0.6rem; font-size: 0.72rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.03em; }
  .mc-row-selected { background-color: #f0fdfa !important; border-color: #5eead4 !important; }
  .custom-checkbox { width: 1.25rem; height: 1.25rem; accent-color: #0d9488; cursor: pointer; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="mc-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-4 pb-20">
    <!-- HERO BANNER -->
    <div class="mc-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-6 relative overflow-hidden">
        <div class="absolute -right-6 -bottom-8 opacity-10 pointer-events-none">
            <i class="fa-solid fa-users-gear text-[170px]"></i>
        </div>
        <div class="relative z-10 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/15 hover:bg-white/25 flex items-center justify-center text-white transition no-underline">
                    <i class="fa-solid fa-arrow-left text-lg"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-lg bg-teal-300/30 text-teal-100 text-xs font-black uppercase tracking-wider">Gestão Comercial</span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-teal-950/40 text-teal-100 text-xs font-bold border border-teal-400/30">
                            <i class="fa-solid fa-arrows-split-up-and-left mr-1"></i>Carteiras & Vendedores
                        </span>
                        <span class="px-2.5 py-0.5 rounded-lg bg-emerald-400/30 text-emerald-100 text-xs font-black uppercase tracking-wider">
                            <i class="fa-solid fa-bolt mr-1"></i>Sincronização Mercos
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight leading-tight">Migração de Carteira de Clientes</h1>
                    <p class="text-xs sm:text-sm text-teal-100/90 font-medium mt-0.5">Transferência segura de carteiras entre representantes comerciais com reflexo imediato no ERP e no Mercos</p>
                </div>
            </div>

            <!-- CONTROLES DE ABAS -->
            <div class="flex items-center gap-2 bg-teal-950/40 p-1.5 rounded-2xl border border-teal-400/20">
                <button type="button" id="tabBtnNovaMigracao" class="mc-tab-btn active" onclick="trocarAba('migracao')">
                    <i class="fa-solid fa-people-arrows"></i>
                    <span>Nova Migração</span>
                </button>
                <button type="button" id="tabBtnHistorico" class="mc-tab-btn" onclick="trocarAba('historico')">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                    <span>Histórico & Auditoria</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ABA 1: NOVA MIGRAÇÃO -->
    <div id="abaMigracao" class="space-y-6">
        <!-- SELEÇÃO DE REPRESENTANTES (DE -> PARA) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- ORIGEM (DE) -->
            <div class="mc-card p-5 sm:p-6 border-l-4 border-l-rose-500">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center font-black text-sm">
                            DE
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wide">Representante de Origem</h2>
                            <p class="text-xs text-slate-500 font-medium">Quem deixará de atender os clientes</p>
                        </div>
                    </div>
                    <span id="badgeQtdClientesOrigem" class="hidden mc-badge bg-rose-100 text-rose-800 border border-rose-200">
                        0 clientes
                    </span>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="mc-label block mb-1.5" for="selRepreOrigem">Selecione o Representante Atual *</label>
                        <select id="selRepreOrigem" class="mc-input cursor-pointer" onchange="aoMudarRepreOrigem()">
                            <option value="">-- Carregando representantes... --</option>
                        </select>
                    </div>

                    <!-- CARD DE DETALHES DO REPRE ORIGEM -->
                    <div id="detalhesRepreOrigem" class="hidden bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 text-xs space-y-1.5">
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="font-bold">Razão Social:</span>
                            <span id="infoOrigemRazao" class="font-semibold text-slate-800 text-right truncate max-w-[240px]">-</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="font-bold">E-mail:</span>
                            <span id="infoOrigemEmail" class="font-semibold text-slate-800 truncate max-w-[240px]">-</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="font-bold">Filial / UF:</span>
                            <span id="infoOrigemFilial" class="font-semibold text-slate-800">-</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600 pt-1 border-t border-slate-200">
                            <span class="font-bold text-rose-700">Total Carteira Ativa:</span>
                            <span id="infoOrigemTotalCli" class="font-black text-rose-700 text-sm">0 clientes</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DESTINO (PARA) -->
            <div class="mc-card p-5 sm:p-6 border-l-4 border-l-teal-600">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-8 h-8 rounded-xl bg-teal-100 text-teal-800 flex items-center justify-center font-black text-sm">
                            PARA
                        </div>
                        <div>
                            <h2 class="text-sm font-black text-slate-800 uppercase tracking-wide">Representante de Destino</h2>
                            <p class="text-xs text-slate-500 font-medium">Quem passará a atender os clientes</p>
                        </div>
                    </div>
                    <div id="badgeMercosStatus" class="hidden">
                        <!-- Badge Dinâmico Mercos -->
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <label class="mc-label block mb-1.5" for="selRepreDestino">Selecione o Novo Representante *</label>
                        <select id="selRepreDestino" class="mc-input cursor-pointer" onchange="aoMudarRepreDestino()">
                            <option value="">-- Selecione o representante de destino --</option>
                        </select>
                    </div>

                    <!-- CARD DE DETALHES DO REPRE DESTINO -->
                    <div id="detalhesRepreDestino" class="hidden bg-slate-50 rounded-2xl p-3.5 border border-slate-200/80 text-xs space-y-1.5">
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="font-bold">Razão Social:</span>
                            <span id="infoDestinoRazao" class="font-semibold text-slate-800 text-right truncate max-w-[240px]">-</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="font-bold">E-mail Mercos:</span>
                            <span id="infoDestinoEmail" class="font-semibold text-slate-800 truncate max-w-[240px]">-</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="font-bold">Supervisor Comercial:</span>
                            <span id="infoDestinoSupervisor" class="font-semibold text-slate-800">-</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600 pt-1 border-t border-slate-200">
                            <span class="font-bold text-teal-700">Validação Mercos:</span>
                            <span id="infoDestinoMercosTxt" class="font-extrabold text-teal-800">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRADE DE CLIENTES -->
        <div id="secaoClientes" class="mc-card p-5 sm:p-6 hidden">
            <!-- TOPO DA GRADE & FILTROS -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-address-book"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Clientes da Carteira</h3>
                        <p id="lblContadorSelecao" class="text-xs text-slate-500 font-semibold">
                            Selecione os clientes que serão transferidos
                        </p>
                    </div>
                </div>

                <!-- AÇÕES RÁPIDAS & BUSCA -->
                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                    <div class="relative flex-1 sm:w-64">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400 text-xs"></i>
                        <input type="text" id="filtroTextoClientes" placeholder="Buscar cliente, CNPJ ou cidade..." class="mc-input pl-8 py-1.5 text-xs" oninput="filtrarGradeClientes()">
                    </div>
                    <button type="button" onclick="selecionarTodosClientes(true)" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700 font-bold text-xs transition whitespace-nowrap cursor-pointer">
                        <i class="fa-solid fa-check-double mr-1 text-teal-600"></i>Marcar Todos
                    </button>
                    <button type="button" onclick="selecionarTodosClientes(false)" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-rose-50 text-slate-700 hover:text-rose-700 font-bold text-xs transition whitespace-nowrap cursor-pointer">
                        <i class="fa-solid fa-xmark mr-1 text-rose-500"></i>Desmarcar
                    </button>
                </div>
            </div>

            <!-- AVISO DE SELEÇÃO AUTOMÁTICA -->
            <div id="alertaSelecaoGeral" class="mt-4 p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 text-xs font-semibold flex items-center gap-2.5">
                <i class="fa-solid fa-circle-info text-amber-600 text-sm flex-none"></i>
                <span>Nenhum cliente marcado individualmente. Se prosseguir assim, <strong>todos os clientes da grade serão transferidos</strong>.</span>
            </div>

            <!-- TABELA DE CLIENTES -->
            <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-extrabold uppercase text-[11px] tracking-wider">
                            <th class="p-3 w-12 text-center">
                                <input type="checkbox" id="chkTodosCabecalho" class="custom-checkbox" onchange="alternarTodosCabecalho(this.checked)">
                            </th>
                            <th class="p-3 w-20">Cód ERP</th>
                            <th class="p-3">Nome Fantasia / Cliente</th>
                            <th class="p-3">Razão Social</th>
                            <th class="p-3">CNPJ / CPF</th>
                            <th class="p-3">Cidade / UF</th>
                            <th class="p-3 text-right">Títulos Abertos</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyClientes" class="divide-y divide-slate-100 font-medium text-slate-700">
                        <!-- Linhas geradas via JavaScript -->
                    </tbody>
                </table>
            </div>

            <!-- RESUMO DA OPERAÇÃO & BOTÃO DE MIGRAÇÃO -->
            <div class="mt-6 pt-5 border-t border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4">
                <div class="w-full md:w-1/2">
                    <label class="mc-label block mb-1" for="txtMotivoMigracao">Motivo / Observações da Transferência (Opcional)</label>
                    <input type="text" id="txtMotivoMigracao" placeholder="Ex: Ajuste geográfico de rota, encerramento de contrato..." class="mc-input text-xs py-2">
                </div>

                <div class="w-full md:w-auto flex items-center gap-3 justify-end">
                    <div class="text-right hidden sm:block">
                        <div class="text-xs font-bold text-slate-500">Pronto para transferir:</div>
                        <div id="lblResumoTransferencia" class="text-sm font-black text-teal-800">0 cliente(s) selecionado(s)</div>
                    </div>
                    <button type="button" id="btnExecutarMigracao" onclick="confirmarExecutarMigracao()" class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-700 hover:to-emerald-700 text-white font-black text-sm shadow-lg shadow-teal-600/25 transition transform active:scale-95 flex items-center justify-center gap-2 cursor-pointer">
                        <i class="fa-solid fa-bolt"></i>
                        <span>Executar Migração de Carteira</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ABA 2: HISTÓRICO & AUDITORIA -->
    <div id="abaHistorico" class="space-y-5 hidden">
        <div class="mc-card p-5 sm:p-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-700 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Histórico de Migrações</h3>
                        <p class="text-xs text-slate-500 font-semibold">Registro de auditoria de todas as transferências de carteira realizadas pelo portal</p>
                    </div>
                </div>
                <button type="button" onclick="carregarHistorico()" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700 font-bold text-xs transition cursor-pointer">
                    <i class="fa-solid fa-rotate-right mr-1 text-teal-600"></i>Atualizar
                </button>
            </div>

            <!-- TABELA DE HISTÓRICO -->
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-extrabold uppercase text-[11px] tracking-wider">
                            <th class="p-3">Data / Hora</th>
                            <th class="p-3">Operador</th>
                            <th class="p-3">De (Origem)</th>
                            <th class="p-3">Para (Destino)</th>
                            <th class="p-3 text-center">Clientes</th>
                            <th class="p-3 text-center">Títulos</th>
                            <th class="p-3 text-center">E-mail</th>
                            <th class="p-3 text-right">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyHistorico" class="divide-y divide-slate-100 font-medium text-slate-700">
                        <tr>
                            <td colspan="8" class="p-6 text-center text-slate-400">
                                <i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando histórico...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA VISUALIZAR CLIENTES DA MIGRAÇÃO DO HISTÓRICO -->
<div id="modalClientesHistorico" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs hidden">
    <div class="bg-white rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden border border-slate-100 max-h-[85vh] flex flex-col">
        <div class="p-5 border-b border-slate-100 flex items-center justify-between bg-slate-50">
            <div>
                <h3 id="modalHistTitulo" class="text-base font-black text-slate-800">Clientes Transferidos</h3>
                <p id="modalHistSubtitulo" class="text-xs text-slate-500 font-semibold">Relação completa de clientes desta migração</p>
            </div>
            <button type="button" onclick="fecharModalHistorico()" class="w-8 h-8 rounded-xl bg-slate-200 hover:bg-slate-300 text-slate-600 flex items-center justify-center text-sm transition cursor-pointer">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <div class="p-5 overflow-y-auto flex-1">
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold uppercase text-[10px]">
                            <th class="p-2.5">Cód</th>
                            <th class="p-2.5">Fantasia</th>
                            <th class="p-2.5">Razão Social</th>
                        </tr>
                    </thead>
                    <tbody id="modalHistTbody" class="divide-y divide-slate-100">
                    </tbody>
                </table>
            </div>
        </div>
        <div class="p-4 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button type="button" onclick="fecharModalHistorico()" class="px-5 py-2 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition cursor-pointer">
                Fechar
            </button>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>
