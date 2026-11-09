<?php
define('ADMIN_AREA', true);
$pageTitle = 'Metas Financeiras | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- HEADER MOBILE FIXO -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/modules/financeiro.php" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">FINANCEIRO</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">METAS FINANCEIRAS</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" id="appMetasFin">

    <!-- Hub Superior de Governança de Metas -->
    <div class="flex items-center gap-2 mb-4 bg-slate-200/70 p-1.5 rounded-2xl w-fit text-xs font-black">
        <a href="metas-financeiro.php" class="px-4 py-2 bg-white text-slate-800 rounded-xl shadow-xs transition flex items-center gap-2 no-underline border border-slate-200">
            <i class="fa-solid fa-scale-balanced text-amber-500"></i> Metas do Financeiro
        </a>
        <a href="metas-marketing.php" class="px-4 py-2 text-slate-600 hover:text-slate-900 rounded-xl transition flex items-center gap-2 no-underline">
            <i class="fa-solid fa-bullseye text-emerald-500"></i> Metas do Marketing
        </a>
    </div>

    <!-- Header Desktop -->
    <div class="glass rounded-3xl p-5 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/modules/financeiro.php" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200" title="Voltar ao Módulo Financeiro">
                <i class="fa-solid fa-arrow-left text-slate-700"></i>
            </a>
            <div class="w-12 h-12 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center shadow-sm flex-shrink-0">
                <i class="fa-solid fa-bullseye text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">ADMINISTRAÇÃO DE METAS FINANCEIRAS</h2>
                <span class="text-xs text-slate-400 font-medium">Parâmetros executivos de IAG, IAP, Recuperação e Tetos de Inadimplência</span>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap w-full md:w-auto justify-end">
            <a href="/portal/modules/financeiro.php" class="px-4 py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition flex items-center gap-2 no-underline whitespace-nowrap">
                <i class="fa-solid fa-chart-line"></i> Dashboard Financeiro
            </a>
            <button onclick="abrirModalNovaMeta()" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:from-emerald-700 hover:to-teal-700 rounded-xl text-sm font-bold shadow-md transition flex items-center gap-2 whitespace-nowrap">
                <i class="fa-solid fa-plus"></i> Nova Meta Financeira
            </button>
        </div>
    </div>

    <!-- Cards de Resumo (Clicáveis) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <div onclick="selecionarAbaStatus('todas')" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-slate-300 transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Total de Metas</span>
                <span class="text-2xl font-black text-slate-800" id="card-total-metas">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-list-check"></i>
            </div>
        </div>
        <div onclick="selecionarAbaStatus('ativa')" class="bg-white p-4 rounded-2xl border border-emerald-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-emerald-300 hover:shadow-md transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-emerald-600 block mb-0.5">Metas Ativas (Foco)</span>
                <span class="text-2xl font-black text-emerald-600" id="card-ativas-metas">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <div onclick="filtrarPorIndicador('iag')" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-amber-300 transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">IAG & IAP (Inadimplência)</span>
                <span class="text-2xl font-black text-amber-600" id="card-inadimp-metas">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
        <div onclick="filtrarPorIndicador('recuperacao')" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-sky-300 transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Recuperação de Crédito</span>
                <span class="text-2xl font-black text-sky-600" id="card-recup-metas">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-600 flex items-center justify-center">
                <i class="fa-solid fa-hand-holding-dollar"></i>
            </div>
        </div>
    </div>

    <!-- Pílulas de Status Rápidas (Foco Imediato nas Ativas) -->
    <div class="bg-white p-3 rounded-2xl border border-slate-100 shadow-sm mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[10px] font-black uppercase text-slate-400 mr-2 tracking-wider">Filtro de Visão:</span>
            <button type="button" onclick="selecionarAbaStatus('ativa')" id="btn-aba-ativa"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-emerald-600 text-white shadow-xs flex items-center gap-1.5">
                <i class="fa-solid fa-circle-check"></i>
                <span>Metas Ativas</span>
                <span class="px-1.5 py-0.2 bg-white/20 rounded-md text-[10px]" id="badge-aba-ativas">0</span>
            </button>
            <button type="button" onclick="selecionarAbaStatus('pausada')" id="btn-aba-pausada"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-pause"></i>
                <span>Pausadas</span>
                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-md text-[10px]" id="badge-aba-pausadas">0</span>
            </button>
            <button type="button" onclick="selecionarAbaStatus('concluida')" id="btn-aba-concluida"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-flag-checkered"></i>
                <span>Concluídas</span>
                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-md text-[10px]" id="badge-aba-concluidas">0</span>
            </button>
            <button type="button" onclick="selecionarAbaStatus('todas')" id="btn-aba-todas"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-layer-group"></i>
                <span>Todas</span>
                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-md text-[10px]" id="badge-aba-todas">0</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-[11px] text-emerald-800 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5" id="banner-foco-ativas">
                <i class="fa-solid fa-bolt text-emerald-600"></i> Exibindo apenas metas ativas em vigência
            </span>
        </div>
    </div>

    <!-- Barra de Filtros Complementares -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-6 flex flex-wrap items-center gap-3">
        <input type="hidden" id="filtro-status" value="ativa">

        <div class="w-full sm:w-56">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Tipo de Indicador</label>
            <select id="filtro-tipo" onchange="carregarMetasAdmin()" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                <option value="">Todos os Indicadores</option>
                <option value="iag">IAG - Inadimplência Geral (%)</option>
                <option value="iap">IAP - Atraso Crítico +30d (%)</option>
                <option value="recuperacao">Taxa de Recuperação (%)</option>
                <option value="vencido_60d">Perda Potencial >60d (%)</option>
                <option value="vencidos_total">Carteira Vencida Máxima (R$)</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Buscar por Título / Descrição</label>
            <div class="relative">
                <i class="fa-solid fa-search absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                <input type="text" id="filtro-busca" oninput="filtrarCardsLocalmente()" placeholder="Digite para filtrar..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700">
            </div>
        </div>
        <div class="self-end pt-5">
            <button onclick="carregarMetasAdmin()" class="px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition flex items-center gap-1.5">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- Container dos Cards de Metas -->
    <div id="metas-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div class="col-span-full text-center py-12 text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-emerald-600"></i>
            <p class="text-sm font-semibold">Carregando metas financeiras...</p>
        </div>
    </div>

</div>

<!-- ====================================================================== -->
<!-- MODAL DE CRIAÇÃO / EDIÇÃO DE META FINANCEIRA -->
<!-- ====================================================================== -->
<div id="modalMetaFin" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="fecharModalMeta()"></div>
        <div class="relative bg-white rounded-3xl max-w-2xl w-full shadow-2xl overflow-hidden z-10">
            <!-- Topo do Modal -->
            <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 px-6 py-4 flex items-center justify-between text-white border-b border-slate-700">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center border border-amber-500/30">
                        <i class="fa-solid fa-bullseye text-base"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black leading-tight" id="modalTitulo">Nova Meta Financeira</h3>
                        <p class="text-[11px] text-slate-400">Defina o alvo, regras e limites de alerta</p>
                    </div>
                </div>
                <button onclick="fecharModalMeta()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-slate-300 hover:text-white flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <!-- Corpo do Modal -->
            <form id="formMetaFin" onsubmit="salvarMetaAdmin(event)" class="p-6 space-y-4">
                <input type="hidden" id="meta-id" value="0">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Tipo de Indicador -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Tipo de Indicador *</label>
                        <select id="meta-tipo" required onchange="ajustarMascaraIndicador()" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                            <option value="iag">Inadimplência Geral - IAG (%)</option>
                            <option value="iap">Atraso Crítico +30d - IAP (%)</option>
                            <option value="recuperacao">Taxa de Recuperação de Crédito (%)</option>
                            <option value="vencido_60d">Perda Potencial >60d (%)</option>
                            <option value="vencidos_total">Carteira Vencida Máxima (R$)</option>
                        </select>
                    </div>

                    <!-- Filial -->
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Filial de Aplicação *</label>
                        <select id="meta-filial" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                            <option value="0">🏢 Todas as Filiais (Consolidado)</option>
                            <option value="1">Filial 1 - Matriz</option>
                            <option value="6">Filial 6 - Filial SC</option>
                        </select>
                    </div>
                </div>

                <!-- Título -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Título da Meta *</label>
                    <input type="text" id="meta-titulo" required placeholder="Ex: Meta IAG Máximo 1º Semestre"
                           class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                </div>

                <!-- Descrição -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Descrição / Diretriz Executiva</label>
                    <textarea id="meta-descricao" rows="2" placeholder="Explique o racional e ações de cobrança esperadas..."
                              class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700"></textarea>
                </div>

                <!-- Valores Alvo & Direção -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1" id="label-valor-meta">Valor Alvo da Meta *</label>
                        <input type="number" step="0.01" id="meta-valor" required placeholder="Ex: 4.50"
                               class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-sm font-black text-emerald-700">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Tolerância / Alerta</label>
                        <input type="number" step="0.01" id="meta-tolerancia" placeholder="Ex: 5.00"
                               class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-sm font-bold text-amber-600" title="Valor a partir do qual entra em sinal amarelo">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">Direção da Meta</label>
                        <select id="meta-direcao" class="w-full p-2.5 bg-white border border-slate-300 rounded-xl text-xs font-bold text-slate-700">
                            <option value="menor_melhor">⬇️ Menor é Melhor (Teto)</option>
                            <option value="maior_melhor">⬆️ Maior é Melhor (Alvo)</option>
                        </select>
                    </div>
                </div>

                <!-- Período de Vigência -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-bold text-slate-500 uppercase">Período de Vigência *</label>
                        <div class="flex gap-1 text-[10px]">
                            <button type="button" onclick="definirPeriodoRapido('mes_atual')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded font-semibold text-slate-600">Este Mês</button>
                            <button type="button" onclick="definirPeriodoRapido('ano_todo')" class="px-2 py-0.5 bg-slate-100 hover:bg-slate-200 rounded font-semibold text-slate-600">Ano Todo</button>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <input type="date" id="meta-dini" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                        </div>
                        <div>
                            <input type="date" id="meta-dfim" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Status da Meta</label>
                    <select id="meta-status" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800">
                        <option value="ativa">✅ Ativa</option>
                        <option value="pausada">⏸️ Pausada</option>
                        <option value="concluida">🏁 Concluída</option>
                        <option value="cancelada">❌ Cancelada</option>
                    </select>
                </div>

                <!-- Botões de Ação -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" onclick="fecharModalMeta()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl text-xs font-bold transition">
                        Cancelar
                    </button>
                    <button type="submit" id="btnSalvarMeta" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black shadow-md transition flex items-center gap-2">
                        <i class="fa-solid fa-save"></i> Salvar Meta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Verificação de permissão administrativa defensiva
(function() {
    try {
        const u = JSON.parse(localStorage.getItem('userData') || '{}');
        const perms = u.permissoes || [];
        const uid = Number(u.uid || u.idusuario || 0);
        const isAdm = perms.includes('admin') || perms.includes('financeiro-admin') || u.nivel === 'admin' || u.is_admin === true || u.role === 'admin' || uid === 4;
        if (!isAdm) {
            window.location.replace('/portal/modules/financeiro.php');
        }
    } catch (e) {
        window.location.replace('/portal/modules/financeiro.php');
    }
})();

let listaMetasGlobal = [];
let contagemStatus = { todas: 0, ativa: 0, pausada: 0, concluida: 0 };

// Helper robusto de requisições compatível com a estrutura de Admin (apiFetch / token)
async function apiFinFetch(endpoint, method = 'GET', body = null) {
    if (typeof apiFetch === 'function') {
        return await apiFetch(endpoint, method, body);
    }
    const token = (typeof getAuthToken === 'function') ? getAuthToken() : (localStorage.getItem('authToken') || sessionStorage.getItem('authToken'));
    if (!token) {
        window.location.href = '/portal/login.php';
        throw new Error('Sessão expirada. Faça login novamente.');
    }
    const cleanEndpoint = endpoint.startsWith('/v1/') ? endpoint.substring(4) : endpoint;
    const url = `${window.location.origin}/index.php?api_route=${cleanEndpoint.startsWith('/') ? cleanEndpoint : '/' + cleanEndpoint}`;
    const csrf = (typeof getCsrfToken === 'function') ? getCsrfToken(token) : '';
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token,
            'X-CSRF-Token': csrf
        }
    };
    if (body) options.body = JSON.stringify(body);
    const res = await fetch(url, options);
    const text = await res.text();
    try {
        return JSON.parse(text);
    } catch (e) {
        throw new Error(text || 'Erro na requisição');
    }
}

function selecionarAbaStatus(status) {
    document.getElementById('filtro-status').value = status;

    const botoes = {
        'ativa': document.getElementById('btn-aba-ativa'),
        'pausada': document.getElementById('btn-aba-pausada'),
        'concluida': document.getElementById('btn-aba-concluida'),
        'todas': document.getElementById('btn-aba-todas')
    };

    Object.keys(botoes).forEach(k => {
        const el = botoes[k];
        if (!el) return;
        if (k === status) {
            el.className = 'aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-emerald-600 text-white shadow-xs flex items-center gap-1.5';
        } else {
            el.className = 'aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5';
        }
    });

    const bannerFoco = document.getElementById('banner-foco-ativas');
    if (bannerFoco) {
        if (status === 'ativa') {
            bannerFoco.innerHTML = '<i class="fa-solid fa-bolt text-emerald-600"></i> Exibindo apenas metas ativas em vigência';
            bannerFoco.className = 'text-[11px] text-emerald-800 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5';
        } else {
            bannerFoco.innerHTML = `<i class="fa-solid fa-filter text-slate-500"></i> Filtro: exibindo metas com status '${status}'`;
            bannerFoco.className = 'text-[11px] text-slate-700 bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 font-bold flex items-center gap-1.5';
        }
    }

    carregarMetasAdmin();
}

function filtrarPorIndicador(tipo) {
    const elTipo = document.getElementById('filtro-tipo');
    if (elTipo) {
        elTipo.value = elTipo.value === tipo ? '' : tipo;
        carregarMetasAdmin();
    }
}

async function carregarMetasAdmin() {
    const container = document.getElementById('metas-container');
    container.innerHTML = `
        <div class="col-span-full text-center py-12 text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-emerald-600"></i>
            <p class="text-sm font-semibold">Carregando metas...</p>
        </div>`;

    const status = document.getElementById('filtro-status').value;
    const tipo = document.getElementById('filtro-tipo').value;

    try {
        // Carrega metas com o filtro atual
        const json = await apiFinFetch('/v1/financeiro/metas', 'POST', { status, tipo_indicador: tipo });
        if (json && json.success === false && json.error) {
            throw new Error(json.error);
        }
        listaMetasGlobal = (json && json.data) ? json.data : [];
        renderizarCardsMetas(listaMetasGlobal);

        // Atualiza contadores globais em background
        atualizarContadoresGlobais();
    } catch (e) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-rose-500 bg-rose-50 border border-rose-200 rounded-2xl">
                <i class="fa-solid fa-triangle-exclamation text-xl mb-1"></i>
                <p class="text-xs font-bold">Erro ao carregar metas: ${e.message}</p>
            </div>`;
    }
}

async function atualizarContadoresGlobais() {
    try {
        const res = await apiFinFetch('/v1/financeiro/metas', 'POST', { status: 'todas', tipo_indicador: '' });
        const todas = (res && res.data) ? res.data : [];

        const ativas = todas.filter(m => m.status === 'ativa').length;
        const pausadas = todas.filter(m => m.status === 'pausada').length;
        const concluidas = todas.filter(m => m.status === 'concluida').length;

        // Atualiza cards de resumo superiores
        document.getElementById('card-total-metas').textContent = todas.length;
        document.getElementById('card-ativas-metas').textContent = ativas;
        document.getElementById('card-inadimp-metas').textContent = todas.filter(m => ['iag', 'iap', 'vencido_60d'].includes(m.tipo_indicador)).length;
        document.getElementById('card-recup-metas').textContent = todas.filter(m => m.tipo_indicador === 'recuperacao').length;

        // Atualiza badges das abas
        document.getElementById('badge-aba-ativas').textContent = ativas;
        document.getElementById('badge-aba-pausadas').textContent = pausadas;
        document.getElementById('badge-aba-concluidas').textContent = concluidas;
        document.getElementById('badge-aba-todas').textContent = todas.length;
    } catch (e) {
        console.warn('Erro ao atualizar contadores globais:', e);
    }
}

function atualizarCardsResumo(metas) {
    document.getElementById('card-total-metas').textContent = metas.length;
    document.getElementById('card-ativas-metas').textContent = metas.filter(m => m.status === 'ativa').length;
    document.getElementById('card-inadimp-metas').textContent = metas.filter(m => ['iag', 'iap', 'vencido_60d'].includes(m.tipo_indicador)).length;
    document.getElementById('card-recup-metas').textContent = metas.filter(m => m.tipo_indicador === 'recuperacao').length;
}

function filtrarCardsLocalmente() {
    const q = (document.getElementById('filtro-busca').value || '').toLowerCase();
    if (!q) {
        renderizarCardsMetas(listaMetasGlobal);
        return;
    }
    const filtradas = listaMetasGlobal.filter(m =>
        (m.titulo || '').toLowerCase().includes(q) ||
        (m.descricao || '').toLowerCase().includes(q) ||
        (m.tipo_indicador || '').toLowerCase().includes(q)
    );
    renderizarCardsMetas(filtradas);
}

function renderizarCardsMetas(metas) {
    const container = document.getElementById('metas-container');
    if (!metas || metas.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12 text-slate-400 bg-white rounded-3xl border border-slate-100 shadow-sm p-8">
                <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-bullseye text-2xl"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-700 mb-1">Nenhuma meta encontrada</h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto mb-4">Cadastre metas para acompanhar o IAG, IAP e recuperação no painel executivo.</p>
                <button onclick="abrirModalNovaMeta()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition">
                    + Cadastrar Primeira Meta
                </button>
            </div>`;
        return;
    }

    const mapaTipo = {
        'iag': { nome: 'IAG - Inadimplência Geral', icone: 'fa-chart-pie', cor: 'rose', un: '%' },
        'iap': { nome: 'IAP - Atraso Crítico (+30d)', icone: 'fa-triangle-exclamation', cor: 'amber', un: '%' },
        'recuperacao': { nome: 'Taxa de Recuperação', icone: 'fa-hand-holding-dollar', cor: 'emerald', un: '%' },
        'vencido_60d': { nome: 'Perda Potencial >60d', icone: 'fa-shield-halved', cor: 'purple', un: '%' },
        'vencidos_total': { nome: 'Carteira Vencida Máxima', icone: 'fa-wallet', cor: 'blue', un: 'R$' }
    };

    const statusBadge = {
        'ativa': 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'pausada': 'bg-amber-50 text-amber-700 border-amber-200',
        'concluida': 'bg-blue-50 text-blue-700 border-blue-200',
        'cancelada': 'bg-slate-100 text-slate-600 border-slate-200'
    };

    container.innerHTML = metas.map(m => {
        const tInfo = mapaTipo[m.tipo_indicador] || { nome: m.tipo_indicador, icone: 'fa-bullseye', cor: 'slate', un: '%' };
        const badgeCls = statusBadge[m.status] || 'bg-slate-100 text-slate-600 border-slate-200';
        const valorFmt = tInfo.un === 'R$'
            ? 'R$ ' + Number(m.valor_meta).toLocaleString('pt-BR', { minimumFractionDigits: 2 })
            : Number(m.valor_meta).toFixed(2) + '%';
        const tolFmt = m.tolerancia_alerta > 0
            ? (tInfo.un === 'R$' ? 'R$ ' + Number(m.tolerancia_alerta).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : Number(m.tolerancia_alerta).toFixed(2) + '%')
            : '—';
        const direcaoTxt = m.direcao === 'maior_melhor' ? 'Alvo Mínimo (Maior é Melhor)' : 'Teto Máximo (Menor é Melhor)';
        const direcaoIcon = m.direcao === 'maior_melhor' ? 'fa-arrow-trend-up text-emerald-500' : 'fa-arrow-trend-down text-rose-500';

        const dIni = m.data_inicio ? m.data_inicio.split('-').reverse().join('/') : '--/--/----';
        const dFim = m.data_fim ? m.data_fim.split('-').reverse().join('/') : '--/--/----';

        return `
            <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                <div>
                    <!-- Topo do Card -->
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-${tInfo.cor}-50 text-${tInfo.cor}-700 border border-${tInfo.cor}-200">
                            <i class="fa-solid ${tInfo.icone}"></i> ${tInfo.nome}
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase border ${badgeCls}">
                            ${m.status}
                        </span>
                    </div>

                    <!-- Título & Descrição -->
                    <h3 class="font-extrabold text-slate-800 text-sm mb-1 group-hover:text-emerald-700 transition-colors leading-snug">${m.titulo}</h3>
                    <p class="text-xs text-slate-400 line-clamp-2 mb-4 font-normal leading-relaxed">${m.descricao || 'Sem descrição informada.'}</p>

                    <!-- Bloco de Valores -->
                    <div class="bg-slate-50/80 rounded-2xl p-3 mb-4 border border-slate-100">
                        <div class="flex items-baseline justify-between mb-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Meta Estabelecida</span>
                            <span class="text-base font-black text-slate-800">${valorFmt}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-slate-500 pt-1.5 border-t border-slate-200/60">
                            <span>Tolerância / Alerta: <b>${tolFmt}</b></span>
                            <span class="flex items-center gap-1 text-[10px] font-bold text-slate-600">
                                <i class="fa-solid ${direcaoIcon}"></i> ${direcaoTxt}
                            </span>
                        </div>
                    </div>

                    <!-- Filial e Vigência -->
                    <div class="space-y-1 text-[11px] text-slate-500 mb-4">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-building text-[10px] text-slate-400"></i>
                            <span>Filial: <b>${m.filial_nome || 'Todas as Filiais'}</b></span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-calendar text-[10px] text-slate-400"></i>
                            <span>Vigência: <b>${dIni}</b> até <b>${dFim}</b></span>
                        </div>
                    </div>
                </div>

                <!-- Rodapé com Ações -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button onclick="alternarStatusMeta(${m.id}, '${m.status}')" class="px-3 py-1.5 rounded-lg text-xs font-bold text-slate-600 hover:bg-slate-100 transition flex items-center gap-1" title="Alternar status">
                        <i class="fa-solid ${m.status === 'ativa' ? 'fa-pause text-amber-500' : 'fa-play text-emerald-500'}"></i>
                        <span>${m.status === 'ativa' ? 'Pausar' : 'Ativar'}</span>
                    </button>
                    <div class="flex items-center gap-1.5">
                        <button onclick='editarMeta(${JSON.stringify(m)})' class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition" title="Editar Meta">
                            <i class="fa-solid fa-pen text-xs"></i>
                        </button>
                        <button onclick="excluirMetaAdmin(${m.id})" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center transition" title="Excluir Meta">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>`;
    }).join('');
}

function ajustarMascaraIndicador() {
    const tipo = document.getElementById('meta-tipo').value;
    const label = document.getElementById('label-valor-meta');
    const dir = document.getElementById('meta-direcao');

    if (tipo === 'vencidos_total') {
        label.textContent = 'Teto em Reais (R$) *';
        dir.value = 'menor_melhor';
    } else if (tipo === 'recuperacao') {
        label.textContent = 'Alvo Percentual (%) *';
        dir.value = 'maior_melhor';
    } else {
        label.textContent = 'Teto Percentual (%) *';
        dir.value = 'menor_melhor';
    }
}

function definirPeriodoRapido(tipo) {
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth() + 1).padStart(2, '0');

    if (tipo === 'mes_atual') {
        const lastDay = new Date(y, hoje.getMonth() + 1, 0).getDate();
        document.getElementById('meta-dini').value = `${y}-${m}-01`;
        document.getElementById('meta-dfim').value = `${y}-${m}-${lastDay}`;
    } else if (tipo === 'ano_todo') {
        document.getElementById('meta-dini').value = `${y}-01-01`;
        document.getElementById('meta-dfim').value = `${y}-12-31`;
    }
}

function abrirModalNovaMeta() {
    document.getElementById('modalTitulo').textContent = 'Nova Meta Financeira';
    document.getElementById('meta-id').value = '0';
    document.getElementById('meta-titulo').value = '';
    document.getElementById('meta-tipo').value = 'iag';
    document.getElementById('meta-descricao').value = '';
    document.getElementById('meta-filial').value = '0';
    document.getElementById('meta-valor').value = '';
    document.getElementById('meta-tolerancia').value = '';
    document.getElementById('meta-status').value = 'ativa';
    ajustarMascaraIndicador();
    definirPeriodoRapido('mes_atual');
    document.getElementById('modalMetaFin').classList.remove('hidden');
}

function editarMeta(meta) {
    document.getElementById('modalTitulo').textContent = 'Editar Meta Financeira';
    document.getElementById('meta-id').value = meta.id;
    document.getElementById('meta-titulo').value = meta.titulo || '';
    document.getElementById('meta-tipo').value = meta.tipo_indicador || 'iag';
    document.getElementById('meta-descricao').value = meta.descricao || '';
    document.getElementById('meta-filial').value = meta.idfilial || 0;
    document.getElementById('meta-valor').value = meta.valor_meta || '';
    document.getElementById('meta-tolerancia').value = meta.tolerancia_alerta || '';
    document.getElementById('meta-direcao').value = meta.direcao || 'menor_melhor';
    document.getElementById('meta-dini').value = meta.data_inicio || '';
    document.getElementById('meta-dfim').value = meta.data_fim || '';
    document.getElementById('meta-status').value = meta.status || 'ativa';
    ajustarMascaraIndicador();
    document.getElementById('modalMetaFin').classList.remove('hidden');
}

function fecharModalMeta() {
    document.getElementById('modalMetaFin').classList.add('hidden');
}

async function salvarMetaAdmin(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarMeta');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const payload = {
        id: parseInt(document.getElementById('meta-id').value || '0', 10),
        titulo: document.getElementById('meta-titulo').value.trim(),
        tipo_indicador: document.getElementById('meta-tipo').value,
        descricao: document.getElementById('meta-descricao').value.trim(),
        idfilial: parseInt(document.getElementById('meta-filial').value || '0', 10),
        valor_meta: document.getElementById('meta-valor').value,
        tolerancia_alerta: document.getElementById('meta-tolerancia').value,
        direcao: document.getElementById('meta-direcao').value,
        data_inicio: document.getElementById('meta-dini').value,
        data_fim: document.getElementById('meta-dfim').value,
        status: document.getElementById('meta-status').value
    };

    try {
        const res = await apiFinFetch('/v1/financeiro/metas/salvar', 'POST', payload);
        if (res && (res.success === true || res.success === 'true')) {
            fecharModalMeta();
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: res.message || 'Meta financeira salva com sucesso.',
                timer: 1600,
                showConfirmButton: false
            });
            await carregarMetasAdmin();
        } else {
            throw new Error(res?.error || 'Erro ao salvar meta.');
        }
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Ops!',
            text: err.message
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-save"></i> Salvar Meta';
    }
}

async function alternarStatusMeta(id, statusAtual) {
    const novoStatus = statusAtual === 'ativa' ? 'pausada' : 'ativa';
    try {
        const meta = listaMetasGlobal.find(m => m.id === id);
        if (!meta) return;

        const res = await apiFinFetch('/v1/financeiro/metas/salvar', 'POST', {
            ...meta,
            status: novoStatus
        });
        if (res && (res.success === true || res.success === 'true')) {
            await carregarMetasAdmin();
        }
    } catch (e) {
        console.error(e);
    }
}

async function excluirMetaAdmin(id) {
    const confirm = await Swal.fire({
        title: 'Excluir Meta Financeira?',
        text: 'Esta ação não poderá ser desfeita.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    });

    if (confirm.isConfirmed) {
        try {
            const res = await apiFinFetch('/v1/financeiro/metas/excluir', 'POST', { id });
            if (res && (res.success === true || res.success === 'true')) {
                Swal.fire({
                    icon: 'success',
                    title: 'Excluída!',
                    text: 'A meta foi removida com sucesso.',
                    timer: 1500,
                    showConfirmButton: false
                });
                await carregarMetasAdmin();
            } else {
                throw new Error(res?.error || 'Erro ao excluir meta.');
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro!', text: e.message });
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    carregarMetasAdmin();
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
