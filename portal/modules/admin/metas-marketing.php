<?php
define('ADMIN_AREA', true);
$pageTitle = 'Metas de Marketing | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- HEADER MOBILE FIXO -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/modules/marketing/dashboard.php" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">MARKETING</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">METAS MARKETING</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" id="appMetasMkt">

    <!-- Hub Superior de Governança de Metas -->
    <div class="flex items-center gap-2 mb-4 bg-slate-200/70 p-1.5 rounded-2xl w-fit text-xs font-black">
        <a href="metas-financeiro.php" class="px-4 py-2 text-slate-600 hover:text-slate-900 rounded-xl transition flex items-center gap-2 no-underline">
            <i class="fa-solid fa-scale-balanced text-amber-500"></i> Metas do Financeiro
        </a>
        <a href="metas-marketing.php" class="px-4 py-2 bg-white text-slate-800 rounded-xl shadow-xs transition flex items-center gap-2 no-underline border border-slate-200">
            <i class="fa-solid fa-bullseye text-emerald-500"></i> Metas do Marketing
        </a>
    </div>

    <!-- Header Desktop -->
    <div class="glass rounded-3xl p-5 mb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/modules/marketing/dashboard.php" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200" title="Voltar ao Dashboard de Marketing">
                <i class="fa-solid fa-arrow-left text-slate-700"></i>
            </a>
            <div class="w-12 h-12 bg-emerald-100 text-emerald-700 rounded-2xl flex items-center justify-center shadow-sm flex-shrink-0">
                <i class="fa-solid fa-bullseye text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">ADMINISTRAÇÃO DE METAS DE MARKETING</h2>
                <span class="text-xs text-slate-400 font-medium">Campanhas, Redes Sociais, Leads, Eventos e Objetivos de Comunicação</span>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap w-full md:w-auto justify-end">
            <a href="/portal/modules/marketing/admin/tipos-meta.php" class="px-4 py-2.5 bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 rounded-xl text-sm font-bold transition flex items-center gap-2 no-underline whitespace-nowrap">
                <i class="fa-solid fa-cubes"></i> Modelos & Tipos
            </a>
            <a href="/portal/modules/marketing/metas.php" class="px-4 py-2.5 bg-slate-100 text-slate-700 hover:bg-slate-200 rounded-xl text-sm font-bold transition flex items-center gap-2 no-underline whitespace-nowrap">
                <i class="fa-solid fa-eye"></i> Painel de Metas
            </a>
            <button onclick="abrirModalNovaMetaMkt()" class="px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 text-white hover:from-emerald-700 hover:to-teal-700 rounded-xl text-sm font-bold shadow-md transition flex items-center gap-2 whitespace-nowrap">
                <i class="fa-solid fa-plus"></i> Nova Meta de Marketing
            </button>
        </div>
    </div>

    <!-- Cards de Resumo (Clicáveis) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-4">
        <div onclick="selecionarAbaStatusMkt('todas')" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-slate-300 transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Total de Metas</span>
                <span class="text-2xl font-black text-slate-800" id="card-total-mkt">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center">
                <i class="fa-solid fa-list-check"></i>
            </div>
        </div>
        <div onclick="selecionarAbaStatusMkt('ativa')" class="bg-white p-4 rounded-2xl border border-emerald-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-emerald-300 hover:shadow-md transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-emerald-600 block mb-0.5">Metas Ativas (Foco)</span>
                <span class="text-2xl font-black text-emerald-600" id="card-ativas-mkt">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <i class="fa-solid fa-circle-check"></i>
            </div>
        </div>
        <div onclick="selecionarAbaStatusMkt('concluida')" class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between cursor-pointer hover:border-blue-300 transition-all">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Metas Concluídas</span>
                <span class="text-2xl font-black text-blue-600" id="card-concluidas-mkt">0</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i class="fa-solid fa-flag-checkered"></i>
            </div>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[10px] uppercase font-bold text-slate-400 block mb-0.5">Progresso Médio</span>
                <span class="text-2xl font-black text-indigo-600" id="card-progresso-mkt">0%</span>
            </div>
            <div class="w-10 h-10 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                <i class="fa-solid fa-chart-pie"></i>
            </div>
        </div>
    </div>

    <!-- Pílulas de Status Rápidas (Foco Imediato nas Ativas) -->
    <div class="bg-white p-3 rounded-2xl border border-slate-100 shadow-sm mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-[10px] font-black uppercase text-slate-400 mr-2 tracking-wider">Filtro de Visão:</span>
            <button type="button" onclick="selecionarAbaStatusMkt('ativa')" id="btn-aba-mkt-ativa"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-emerald-600 text-white shadow-xs flex items-center gap-1.5">
                <i class="fa-solid fa-circle-check"></i>
                <span>Metas Ativas</span>
                <span class="px-1.5 py-0.2 bg-white/20 rounded-md text-[10px]" id="badge-aba-mkt-ativas">0</span>
            </button>
            <button type="button" onclick="selecionarAbaStatusMkt('pausada')" id="btn-aba-mkt-pausada"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-pause"></i>
                <span>Pausadas</span>
                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-md text-[10px]" id="badge-aba-mkt-pausadas">0</span>
            </button>
            <button type="button" onclick="selecionarAbaStatusMkt('concluida')" id="btn-aba-mkt-concluida"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-flag-checkered"></i>
                <span>Concluídas</span>
                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-md text-[10px]" id="badge-aba-mkt-concluidas">0</span>
            </button>
            <button type="button" onclick="selecionarAbaStatusMkt('todas')" id="btn-aba-mkt-todas"
                    class="aba-status-btn px-3.5 py-1.5 rounded-xl text-xs font-black transition-all bg-slate-100 text-slate-600 hover:bg-slate-200 flex items-center gap-1.5">
                <i class="fa-solid fa-layer-group"></i>
                <span>Todas</span>
                <span class="px-1.5 py-0.2 bg-slate-200 text-slate-700 rounded-md text-[10px]" id="badge-aba-mkt-todas">0</span>
            </button>
        </div>

        <div class="flex items-center gap-2">
            <span class="text-[11px] text-emerald-800 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5" id="banner-foco-mkt">
                <i class="fa-solid fa-bolt text-emerald-600"></i> Exibindo apenas metas ativas em andamento
            </span>
        </div>
    </div>

    <!-- Barra de Filtros Complementares -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-6 flex flex-wrap items-center gap-3">
        <input type="hidden" id="filtro-mkt-status" value="ativa">

        <div class="w-full sm:w-56">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Modelo / Tipo</label>
            <select id="filtro-mkt-tipo" onchange="carregarMetasMktAdmin()" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700">
                <option value="">Todos os Tipos</option>
            </select>
        </div>
        <div class="flex-1 min-w-[200px]">
            <label class="block text-[10px] font-bold text-slate-400 uppercase mb-1">Buscar por Título / Descrição</label>
            <div class="relative">
                <i class="fa-solid fa-search absolute left-3.5 top-3 text-slate-400 text-xs"></i>
                <input type="text" id="filtro-mkt-busca" oninput="filtrarCardsMktLocalmente()" placeholder="Digite para filtrar..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700">
            </div>
        </div>
        <div class="self-end pt-5">
            <button onclick="carregarMetasMktAdmin()" class="px-4 py-2.5 bg-slate-800 text-white rounded-xl text-xs font-bold hover:bg-slate-900 transition flex items-center gap-1.5">
                <i class="fa-solid fa-rotate"></i> Atualizar
            </button>
        </div>
    </div>

    <!-- Grid de Metas do Marketing -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 mb-8" id="metas-mkt-container">
        <div class="col-span-full text-center py-12 text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-emerald-600"></i>
            <p class="text-sm font-semibold">Carregando metas de marketing...</p>
        </div>
    </div>

    <!-- Modal Nova / Editar Meta de Marketing -->
    <div id="modalMetaMkt" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" onclick="fecharModalMetaMkt()"></div>
            <div class="relative bg-white rounded-3xl max-w-xl w-full shadow-2xl overflow-hidden border border-slate-100 z-10">
                <div class="bg-gradient-to-r from-emerald-600 to-teal-700 px-6 py-4 flex items-center justify-between text-white">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-bullseye text-lg"></i>
                        <h3 class="text-base font-bold" id="modalMktTitulo">Nova Meta de Marketing</h3>
                    </div>
                    <button onclick="fecharModalMetaMkt()" class="w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center transition">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <form id="formMetaMkt" onsubmit="salvarMetaMktAdmin(event)" class="p-6 space-y-4">
                    <input type="hidden" id="mkt-id" value="0">
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Título da Meta *</label>
                        <input type="text" id="mkt-titulo" required placeholder="Ex: Alcance 50k Instagram Nov/Dez"
                               class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800 focus:bg-white focus:border-emerald-500">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Modelo / Tipo de Meta *</label>
                            <select id="mkt-tipo" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800">
                                <option value="">Selecione o Modelo...</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Status da Meta *</label>
                            <select id="mkt-status" class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800">
                                <option value="ativa">✅ Ativa</option>
                                <option value="pausada">⏸️ Pausada</option>
                                <option value="concluida">🏁 Concluída</option>
                                <option value="cancelada">❌ Cancelada</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data Início *</label>
                            <input type="date" id="mkt-dini" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data Fim *</label>
                            <input type="date" id="mkt-dfim" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Descrição / Instruções</label>
                        <textarea id="mkt-descricao" rows="2" placeholder="Descreva os canais, critérios e objetivos..."
                                  class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-normal text-slate-800"></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" onclick="fecharModalMetaMkt()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">Cancelar</button>
                        <button type="submit" id="btnSalvarMetaMkt" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-save"></i> Salvar Meta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Alimentar Meta de Marketing -->
    <div id="modalAlimentarMkt" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs" onclick="fecharModalAlimentarMkt()"></div>
            <div class="relative bg-white rounded-3xl max-w-md w-full shadow-2xl overflow-hidden border border-slate-100 z-10">
                <div class="bg-gradient-to-r from-blue-600 to-indigo-700 px-6 py-4 flex items-center justify-between text-white">
                    <div class="flex items-center gap-2.5">
                        <i class="fa-solid fa-plus-circle text-lg"></i>
                        <h3 class="text-base font-bold">Alimentar Progresso da Meta</h3>
                    </div>
                    <button onclick="fecharModalAlimentarMkt()" class="w-8 h-8 rounded-lg hover:bg-white/10 flex items-center justify-center transition">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
                <form id="formAlimentarMkt" onsubmit="salvarAlimentacaoMkt(event)" class="p-6 space-y-4">
                    <input type="hidden" id="alim-meta-id" value="0">
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase block mb-0.5">Meta Selecionada</span>
                        <h4 class="text-sm font-extrabold text-slate-800" id="alim-meta-titulo">---</h4>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Data do Registro *</label>
                            <input type="date" id="alim-data" required class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-semibold text-slate-800">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Valor Alcançado *</label>
                            <input type="number" step="any" id="alim-valor" required placeholder="0.00"
                                   class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-black text-slate-800">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Observações do Ciclo</label>
                        <input type="text" id="alim-obs" placeholder="Ex: postagem patrocinada, evento, etc."
                               class="w-full p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-700">
                    </div>
                    <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                        <button type="button" onclick="fecharModalAlimentarMkt()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">Cancelar</button>
                        <button type="submit" id="btnSalvarAlimMkt" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-check"></i> Registrar Progresso
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Verificação de permissão administrativa
(function() {
    try {
        const u = JSON.parse(localStorage.getItem('userData') || '{}');
        const perms = u.permissoes || [];
        const uid = Number(u.uid || u.idusuario || 0);
        const isAdm = perms.includes('admin') || perms.includes('marketing-admin') || u.nivel === 'admin' || u.is_admin === true || u.role === 'admin' || uid === 4;
        if (!isAdm) {
            window.location.replace('/portal/modules/marketing/dashboard.php');
        }
    } catch (e) {
        window.location.replace('/portal/modules/marketing/dashboard.php');
    }
})();

let listaMetasMkt = [];
let tiposMetasMkt = [];

// Helper robusto de requisições compatível com a estrutura de Admin (apiFetch / token)
async function apiMktFetch(endpoint, method = 'GET', body = null) {
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

function selecionarAbaStatusMkt(status) {
    document.getElementById('filtro-mkt-status').value = status;

    const botoes = {
        'ativa': document.getElementById('btn-aba-mkt-ativa'),
        'pausada': document.getElementById('btn-aba-mkt-pausada'),
        'concluida': document.getElementById('btn-aba-mkt-concluida'),
        'todas': document.getElementById('btn-aba-mkt-todas')
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

    const bannerFoco = document.getElementById('banner-foco-mkt');
    if (bannerFoco) {
        if (status === 'ativa') {
            bannerFoco.innerHTML = '<i class="fa-solid fa-bolt text-emerald-600"></i> Exibindo apenas metas ativas em andamento';
            bannerFoco.className = 'text-[11px] text-emerald-800 bg-emerald-50 px-3 py-1 rounded-xl border border-emerald-200 font-bold flex items-center gap-1.5';
        } else {
            bannerFoco.innerHTML = `<i class="fa-solid fa-filter text-slate-500"></i> Filtro: exibindo metas com status '${status}'`;
            bannerFoco.className = 'text-[11px] text-slate-700 bg-slate-100 px-3 py-1 rounded-xl border border-slate-200 font-bold flex items-center gap-1.5';
        }
    }

    carregarMetasMktAdmin();
}

async function carregarTiposMkt() {
    try {
        const res = await apiMktFetch('/v1/meta-builder/tipos', 'GET');
        tiposMetasMkt = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);
        
        const selFiltro = document.getElementById('filtro-mkt-tipo');
        const selModal = document.getElementById('mkt-tipo');

        const opts = tiposMetasMkt.map(t => `<option value="${t.id}">${t.nome}</option>`).join('');
        if (selFiltro) selFiltro.innerHTML = '<option value="">Todos os Tipos</option>' + opts;
        if (selModal) selModal.innerHTML = '<option value="">Selecione o Modelo...</option>' + opts;
    } catch (e) {
        console.warn('Erro ao carregar tipos de meta de marketing:', e);
    }
}

async function carregarMetasMktAdmin() {
    const container = document.getElementById('metas-mkt-container');
    container.innerHTML = `
        <div class="col-span-full text-center py-12 text-slate-400">
            <i class="fa-solid fa-circle-notch fa-spin text-2xl mb-2 text-emerald-600"></i>
            <p class="text-sm font-semibold">Carregando metas de marketing...</p>
        </div>`;

    const statusFiltro = document.getElementById('filtro-mkt-status').value;
    const tipoFiltro = document.getElementById('filtro-mkt-tipo').value;

    try {
        const res = await apiMktFetch('/v1/meta-builder/instancias', 'GET');
        const todas = (res && res.data) ? res.data : (Array.isArray(res) ? res : []);

        // Atualiza contadores globais
        const ativas = todas.filter(m => (m.status || 'ativa').toLowerCase() === 'ativa').length;
        const pausadas = todas.filter(m => (m.status || '').toLowerCase() === 'pausada').length;
        const concluidas = todas.filter(m => (m.status || '').toLowerCase() === 'concluida').length;

        document.getElementById('card-total-mkt').textContent = todas.length;
        document.getElementById('card-ativas-mkt').textContent = ativas;
        document.getElementById('card-concluidas-mkt').textContent = concluidas;

        document.getElementById('badge-aba-mkt-ativas').textContent = ativas;
        document.getElementById('badge-aba-mkt-pausadas').textContent = pausadas;
        document.getElementById('badge-aba-mkt-concluidas').textContent = concluidas;
        document.getElementById('badge-aba-mkt-todas').textContent = todas.length;

        // Média de progresso
        if (todas.length > 0) {
            const somaProg = todas.reduce((acc, m) => acc + (parseFloat(m.progresso || m.percentual || 0)), 0);
            const media = Math.round(somaProg / todas.length);
            document.getElementById('card-progresso-mkt').textContent = `${media}%`;
        }

        // Filtra para visualização
        let filtradas = todas;
        if (statusFiltro !== 'todas') {
            filtradas = filtradas.filter(m => (m.status || 'ativa').toLowerCase() === statusFiltro.toLowerCase());
        }
        if (tipoFiltro) {
            filtradas = filtradas.filter(m => String(m.id_tipo || m.idtipo) === String(tipoFiltro));
        }

        listaMetasMkt = filtradas;
        renderizarCardsMetasMkt(listaMetasMkt);
    } catch (e) {
        container.innerHTML = `
            <div class="col-span-full text-center py-8 text-rose-500 bg-rose-50 border border-rose-200 rounded-2xl">
                <i class="fa-solid fa-triangle-exclamation text-xl mb-1"></i>
                <p class="text-xs font-bold">Erro ao carregar metas de marketing: ${e.message}</p>
            </div>`;
    }
}

function filtrarCardsMktLocalmente() {
    const q = (document.getElementById('filtro-mkt-busca').value || '').toLowerCase();
    if (!q) {
        renderizarCardsMetasMkt(listaMetasMkt);
        return;
    }
    const filtradas = listaMetasMkt.filter(m =>
        (m.titulo || '').toLowerCase().includes(q) ||
        (m.descricao || '').toLowerCase().includes(q) ||
        (m.tipo_nome || '').toLowerCase().includes(q)
    );
    renderizarCardsMetasMkt(filtradas);
}

function renderizarCardsMetasMkt(metas) {
    const container = document.getElementById('metas-mkt-container');
    if (!metas || metas.length === 0) {
        container.innerHTML = `
            <div class="col-span-full text-center py-12 text-slate-400 bg-white rounded-3xl border border-slate-100 shadow-sm p-8">
                <div class="w-16 h-16 bg-slate-50 text-slate-400 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i class="fa-solid fa-bullseye text-2xl"></i>
                </div>
                <h4 class="text-sm font-bold text-slate-700 mb-1">Nenhuma meta de marketing encontrada</h4>
                <p class="text-xs text-slate-400 max-w-sm mx-auto mb-4">Crie metas para monitorar campanhas, engajamento e métricas de comunicação.</p>
                <button onclick="abrirModalNovaMetaMkt()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold hover:bg-emerald-700 transition">
                    + Cadastrar Primeira Meta
                </button>
            </div>`;
        return;
    }

    container.innerHTML = metas.map(m => {
        const prog = Math.min(100, Math.max(0, parseFloat(m.progresso || m.percentual || 0)));
        const corProg = prog >= 100 ? 'bg-emerald-500' : (prog >= 70 ? 'bg-indigo-500' : (prog >= 40 ? 'bg-amber-500' : 'bg-rose-500'));
        const dIni = m.data_inicio ? m.data_inicio.split('-').reverse().join('/') : '--/--/----';
        const dFim = m.data_fim ? m.data_fim.split('-').reverse().join('/') : '--/--/----';
        const status = (m.status || 'ativa').toLowerCase();
        const badgeCls = status === 'ativa'
            ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
            : (status === 'concluida' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-slate-100 text-slate-600 border-slate-200');

        return `
            <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm hover:shadow-md transition-all flex flex-col justify-between group">
                <div>
                    <!-- Topo do Card -->
                    <div class="flex items-center justify-between gap-2 mb-3">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-purple-50 text-purple-700 border border-purple-200">
                            <i class="fa-solid fa-cube"></i> ${m.tipo_nome || 'Meta Geral'}
                        </span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase border ${badgeCls}">
                            ${status}
                        </span>
                    </div>

                    <!-- Título & Descrição -->
                    <h3 class="font-extrabold text-slate-800 text-sm mb-1 group-hover:text-emerald-700 transition-colors leading-snug">${m.titulo}</h3>
                    <p class="text-xs text-slate-400 line-clamp-2 mb-4 font-normal leading-relaxed">${m.descricao || 'Sem descrição informada.'}</p>

                    <!-- Barra de Progresso Executiva -->
                    <div class="bg-slate-50/80 rounded-2xl p-3 mb-4 border border-slate-100">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-[10px] font-bold uppercase text-slate-400">Progresso Apurado</span>
                            <span class="text-xs font-black text-slate-800">${prog.toFixed(1)}%</span>
                        </div>
                        <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500 ${corProg}" style="width: ${prog}%"></div>
                        </div>
                    </div>

                    <!-- Vigência -->
                    <div class="space-y-1 text-[11px] text-slate-500 mb-4">
                        <div class="flex items-center gap-1.5">
                            <i class="fa-solid fa-calendar text-[10px] text-slate-400"></i>
                            <span>Ciclo: <b>${dIni}</b> até <b>${dFim}</b></span>
                        </div>
                    </div>
                </div>

                <!-- Rodapé com Ações -->
                <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button onclick="abrirAlimentarMkt(${m.id}, '${m.titulo.replace(/'/g, "\\'")}')" class="px-3 py-1.5 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                        <i class="fa-solid fa-plus-circle"></i>
                        <span>Alimentar</span>
                    </button>
                    <div class="flex items-center gap-1.5">
                        <a href="/portal/modules/marketing/metas.php?meta=${m.id}" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition no-underline" title="Ver Detalhes">
                            <i class="fa-solid fa-up-right-from-square text-xs"></i>
                        </a>
                        <button onclick="excluirMetaMktAdmin(${m.id})" class="w-8 h-8 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center transition" title="Excluir Meta">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>
            </div>`;
    }).join('');
}

function abrirModalNovaMetaMkt() {
    document.getElementById('modalMktTitulo').textContent = 'Nova Meta de Marketing';
    document.getElementById('mkt-id').value = '0';
    document.getElementById('mkt-titulo').value = '';
    document.getElementById('mkt-tipo').value = '';
    document.getElementById('mkt-status').value = 'ativa';
    document.getElementById('mkt-descricao').value = '';
    
    const hoje = new Date();
    const y = hoje.getFullYear();
    const m = String(hoje.getMonth() + 1).padStart(2, '0');
    const lastDay = new Date(y, hoje.getMonth() + 1, 0).getDate();
    document.getElementById('mkt-dini').value = `${y}-${m}-01`;
    document.getElementById('mkt-dfim').value = `${y}-${m}-${lastDay}`;

    document.getElementById('modalMetaMkt').classList.remove('hidden');
}

function fecharModalMetaMkt() {
    document.getElementById('modalMetaMkt').classList.add('hidden');
}

async function salvarMetaMktAdmin(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarMetaMkt');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';

    const payload = {
        titulo: document.getElementById('mkt-titulo').value.trim(),
        id_tipo: parseInt(document.getElementById('mkt-tipo').value, 10),
        status: document.getElementById('mkt-status').value,
        data_inicio: document.getElementById('mkt-dini').value,
        data_fim: document.getElementById('mkt-dfim').value,
        descricao: document.getElementById('mkt-descricao').value.trim()
    };

    try {
        const res = await apiMktFetch('/v1/meta-builder/instancias', 'POST', payload);
        if (res && (res.success === true || res.success === 'true')) {
            fecharModalMetaMkt();
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Meta de marketing criada com sucesso.',
                timer: 1600,
                showConfirmButton: false
            });
            await carregarMetasMktAdmin();
        } else {
            throw new Error(res?.error || 'Erro ao salvar meta.');
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Ops!', text: err.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-save"></i> Salvar Meta';
    }
}

function abrirAlimentarMkt(id, titulo) {
    document.getElementById('alim-meta-id').value = id;
    document.getElementById('alim-meta-titulo').textContent = titulo;
    document.getElementById('alim-valor').value = '';
    document.getElementById('alim-obs').value = '';

    const hoje = new Date().toISOString().split('T')[0];
    document.getElementById('alim-data').value = hoje;

    document.getElementById('modalAlimentarMkt').classList.remove('hidden');
}

function fecharModalAlimentarMkt() {
    document.getElementById('modalAlimentarMkt').classList.add('hidden');
}

async function salvarAlimentacaoMkt(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSalvarAlimMkt');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gravando...';

    const id = parseInt(document.getElementById('alim-meta-id').value, 10);
    const dataReg = document.getElementById('alim-data').value;
    const valor = parseFloat(document.getElementById('alim-valor').value);
    const obs = document.getElementById('alim-obs').value.trim();

    try {
        const res = await apiMktFetch('/v1/meta-builder/alimentar', 'POST', {
            id_meta: id,
            data_registro: dataReg,
            valores: { valor_alcancado: valor, observacao: obs }
        });
        if (res && (res.success === true || res.success === 'true')) {
            fecharModalAlimentarMkt();
            Swal.fire({
                icon: 'success',
                title: 'Alimentado!',
                text: 'Progresso da meta registrado com sucesso.',
                timer: 1500,
                showConfirmButton: false
            });
            await carregarMetasMktAdmin();
        } else {
            throw new Error(res?.error || 'Erro ao registrar progresso.');
        }
    } catch (err) {
        Swal.fire({ icon: 'error', title: 'Ops!', text: err.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Registrar Progresso';
    }
}

async function excluirMetaMktAdmin(id) {
    const confirm = await Swal.fire({
        title: 'Excluir Meta de Marketing?',
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
            const res = await apiMktFetch(`/v1/meta-builder/instancias/${id}`, 'DELETE');
            if (res && (res.success === true || res.success === 'true')) {
                Swal.fire({
                    icon: 'success',
                    title: 'Excluída!',
                    text: 'A meta foi removida com sucesso.',
                    timer: 1500,
                    showConfirmButton: false
                });
                await carregarMetasMktAdmin();
            } else {
                throw new Error(res?.error || 'Erro ao excluir');
            }
        } catch (e) {
            Swal.fire({ icon: 'error', title: 'Erro!', text: e.message });
        }
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    await carregarTiposMkt();
    await carregarMetasMktAdmin();
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
