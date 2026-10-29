<?php
define('ADMIN_AREA', true);
$pageTitle = 'Configurações dos Módulos | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<style>
    @media (max-width: 1023px) {
        aside.w-72 { position: static; width: 100%; height: auto; max-height: 45vh; }
        main.flex-1.ml-72 { margin-left: 0; padding: 1rem; }
        body > .flex.min-h-screen { display: block; }
    }
    .config-field { width: 100%; margin-top: .35rem; border: 1px solid #e2e8f0; border-radius: .75rem; padding: .65rem .75rem; font-size: .875rem; background-color: #ffffff; transition: all 0.2s; }
    .config-field:focus { outline: none; border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.15); }
    .config-label { display: block; color: #475569; font-size: .70rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .tab-btn.active { background-color: #ffffff; color: #0f172a; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1); border-color: #cbd5e1; }
</style>

<!-- Topo da Página / Hero -->
<div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white rounded-3xl p-6 mb-6 shadow-sm relative overflow-hidden">
    <div class="absolute -right-10 -bottom-10 w-60 h-60 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="flex flex-wrap items-center justify-between gap-4 relative z-10">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 bg-white/10 backdrop-blur-md text-amber-400 rounded-2xl flex items-center justify-center border border-white/10 shadow-inner">
                <i class="fa-solid fa-sliders text-2xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-xl font-black tracking-tight">CONFIGURAÇÕES DOS MÓDULOS</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-amber-400/20 text-amber-300 border border-amber-400/30">Motor Avançado</span>
                </div>
                <p class="text-xs text-slate-300 mt-1 max-w-xl">Parâmetros de negócio administrados por módulo com validação de regras fiscais, saldos de verbas, estoques e destinatários de e-mails.</p>
            </div>
        </div>

        <!-- Seletor de Módulos (Tabs) -->
        <div class="flex items-center bg-slate-800/80 p-1.5 rounded-2xl border border-slate-700 backdrop-blur-sm text-xs font-bold text-slate-300 flex-wrap gap-1">
            <button onclick="filtrarSecao('todos')" class="tab-btn px-3 py-1.5 rounded-xl transition active" data-tab="todos">
                <i class="fa-solid fa-layer-group mr-1.5"></i>Todos
            </button>
            <button onclick="filtrarSecao('emails')" class="tab-btn px-3 py-1.5 rounded-xl transition hover:text-white" data-tab="emails">
                <i class="fa-solid fa-envelope text-indigo-400 mr-1.5"></i>Destinatários & Listas
            </button>
            <button onclick="filtrarSecao('flex')" class="tab-btn px-3 py-1.5 rounded-xl transition hover:text-white" data-tab="flex">
                <i class="fa-solid fa-wallet text-violet-400 mr-1.5"></i>Verbas Flex
            </button>
            <button onclick="filtrarSecao('guia')" class="tab-btn px-3 py-1.5 rounded-xl transition hover:text-white" data-tab="guia">
                <i class="fa-solid fa-truck-ramp-box text-sky-400 mr-1.5"></i>Guia ST
            </button>
            <button onclick="filtrarSecao('amostras')" class="tab-btn px-3 py-1.5 rounded-xl transition hover:text-white" data-tab="amostras">
                <i class="fa-solid fa-gift text-emerald-400 mr-1.5"></i>Amostras PR
            </button>
        </div>
    </div>
</div>

<div class="space-y-6">

    <!-- SEÇÃO: DESTINATÁRIOS & LISTAS DE E-MAILS (NOVA) -->
    <section id="secao-emails" class="bg-white rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-100 transition-all">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-envelope-open-text text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Listas de E-mails & Destinatários (Crons & Notificações)</h3>
                    <p class="text-xs text-slate-400">Edite os e-mails das listas de cópia (CC), consolidado da diretoria e relatórios automáticos. As alterações entram em vigor imediatamente.</p>
                </div>
            </div>
            <button onclick="carregarConfiguracoes()" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition flex items-center gap-1.5">
                <i class="fa-solid fa-rotate"></i> Recarregar
            </button>
        </div>

        <div id="estadoEmails" class="text-sm text-slate-400 py-3">Carregando listas de e-mails...</div>
        <div id="listaEmailsContainer" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
    </section>

    <!-- SEÇÃO 1: VERBAS FLEX · FLEX MÍNIMO -->
    <section id="secao-flex" class="bg-white rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-100 transition-all">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-100 text-violet-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-wallet text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Verbas Flex · Flex Mínimo</h3>
                    <p class="text-xs text-slate-400">Gestores, filial, valores de empréstimo automático, motivo e destinatários de notificação.</p>
                </div>
            </div>
            <button id="btnNovoFlex" class="px-4 py-2.5 rounded-xl bg-violet-600 text-white text-xs font-bold hover:bg-violet-700 transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-plus"></i> Novo Gestor Flex
            </button>
        </div>

        <!-- Alerta Importante -->
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 mb-5 text-xs text-amber-900 flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-600 text-base mt-0.5 flex-shrink-0"></i>
            <div>
                <b>Aviso Operacional Obrigatório:</b> Antes de ativar um gestor aqui, verifique se a rotina equivalente foi <b>desligada no ERP legado</b> para evitar empréstimos duplicados na API Mercos. A execução automática é orquestrada pelo Dispatcher do Portal.
            </div>
        </div>

        <div id="estadoFlex" class="text-sm text-slate-400 py-3">Carregando configurações...</div>
        <div id="listaFlex" class="space-y-5"></div>
    </section>

    <!-- SEÇÃO 2: TRANSFERÊNCIAS DE COMPRA · GUIA ICMS-ST -->
    <section id="secao-guia" class="bg-white rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-100 transition-all">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-truck-ramp-box text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Transferências de Compra · Guia ICMS-ST</h3>
                    <p class="text-xs text-slate-400">Escolha os NCMs participantes da guia. Apenas eles entram na apuração dos itens ST 010 faturados.</p>
                </div>
            </div>
            <button id="btnNovaRegra" class="px-4 py-2.5 rounded-xl bg-sky-700 text-white text-xs font-bold hover:bg-sky-800 transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-plus"></i> Adicionar NCMs
            </button>
        </div>

        <div id="estadoGuia" class="text-sm text-slate-400 py-3">Carregando regras...</div>
        <div id="listaGuias" class="space-y-2.5"></div>

        <div class="rounded-2xl border border-sky-100 bg-sky-50/70 p-4 mt-5 text-xs text-sky-900 flex items-start gap-3">
            <i class="fa-solid fa-calculator text-sky-600 text-base mt-0.5 flex-shrink-0"></i>
            <div>
                <b>Fórmula Oficial da Guia ST (Igual à planilha da controladoria):</b><br>
                Soma Total dos Itens · Crédito ICMS Origem = Soma × crédito (12%) · Base de Cálculo ICMS-ST = Soma × (1 + MVA) · ICMS-ST = Base × alíquota PR (19,5%) · <b>Valor Guia = ICMS-ST − Crédito ICMS Origem</b>.
            </div>
        </div>
    </section>

    <!-- SEÇÃO 3: MATERIAL DE APOIO & AMOSTRAS -->
    <section id="secao-amostras" class="bg-white rounded-3xl p-5 sm:p-7 shadow-sm border border-slate-100 transition-all">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4 pb-4 border-b border-slate-100">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                    <i class="fa-solid fa-gift text-lg"></i>
                </div>
                <div>
                    <h3 class="text-base font-extrabold text-slate-800">Material de Apoio & Amostras · Estoque Filial PR (6)</h3>
                    <p class="text-xs text-slate-400">Como o estoque físico fica centralizado na Matriz (1), as solicitações da Filial PR aplicam retenção padrão e exceções por item.</p>
                </div>
            </div>
            <button id="btnNovaRegraAmostra" class="px-4 py-2.5 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition flex items-center gap-1.5 shadow-sm">
                <i class="fa-solid fa-plus"></i> Nova Regra por Item
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-3">
            <!-- Percentual Padrão Geral -->
            <div class="md:col-span-1 rounded-2xl border border-emerald-200/80 bg-emerald-50/50 p-5 flex flex-col justify-between">
                <div>
                    <div class="text-xs font-bold text-emerald-900 uppercase tracking-wider mb-1 flex items-center gap-1.5">
                        <i class="fa-solid fa-percent text-emerald-600"></i> Percentual Padrão para Filial PR
                    </div>
                    <div class="text-[11px] text-slate-500 mb-4">Estoque da Matriz liberado automaticamente para a Filial PR quando o item não tiver regra específica.</div>
                    
                    <form id="formAmostraGeral" class="space-y-3">
                        <div class="flex items-center gap-2">
                            <div class="relative flex-1">
                                <input id="amostraPercPadrao" type="number" min="0" max="100" step="0.5" class="config-field text-xl font-black text-emerald-700 pr-8 bg-white" required>
                                <span class="absolute right-3 top-3 text-sm font-black text-slate-400">%</span>
                            </div>
                            <button type="submit" class="px-4 py-3 rounded-xl bg-emerald-600 text-white text-xs font-bold hover:bg-emerald-700 transition shadow-sm">
                                Salvar
                            </button>
                        </div>
                    </form>
                </div>
                <div id="amostraResumoPadrao" class="text-xs font-semibold text-emerald-900 bg-white p-3 rounded-xl border border-emerald-200 mt-4 shadow-2xs">
                    Carregando...
                </div>
            </div>

            <!-- Regras Específicas por Item -->
            <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-slate-50/50 p-5">
                <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-200">
                    <div class="text-xs font-bold text-slate-700 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fa-solid fa-list-check text-slate-500"></i> Regras Específicas Cadastradas
                    </div>
                    <span id="contRegrasAmostra" class="text-xs font-bold text-emerald-700 bg-emerald-100 px-2.5 py-0.5 rounded-full">0 regras</span>
                </div>
                <div id="estadoAmostra" class="text-sm text-slate-400 py-3">Carregando regras...</div>
                <div id="listaAmostraRegras" class="space-y-2.5 max-h-96 overflow-y-auto pr-1"></div>
            </div>
        </div>
    </section>
</div>

<script>
const configApi = '/admin/config-modulos';
const configEsc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const configDate = (v) => {
    const m = String(v || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
};
const configState = { regras: [], ncms: [], impostos: [], flex: [], amostraGeral: null, amostraRegras: [], listasEmails: [] };

function filtrarSecao(secao) {
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.toggle('active', b.dataset.tab === secao);
    });

    const sFlex = document.getElementById('secao-flex');
    const sGuia = document.getElementById('secao-guia');
    const sAmostras = document.getElementById('secao-amostras');
    const sEmails = document.getElementById('secao-emails');

    if (secao === 'todos') {
        sFlex?.classList.remove('hidden');
        sGuia?.classList.remove('hidden');
        sAmostras?.classList.remove('hidden');
        sEmails?.classList.remove('hidden');
    } else {
        sFlex?.classList.toggle('hidden', secao !== 'flex');
        sGuia?.classList.toggle('hidden', secao !== 'guia');
        sAmostras?.classList.toggle('hidden', secao !== 'amostras');
        sEmails?.classList.toggle('hidden', secao !== 'emails');
    }
}

async function configRequest(path, method = 'GET', body = null) {
    const data = await apiFetch(`${configApi}/${path}`, method, body);
    if (!data || data.success !== true) throw new Error(data?.error || 'Não foi possível concluir a operação.');
    return data;
}

// ======================================================================
// RENDERIZAÇÃO DAS LISTAS DE E-MAILS (NOVO)
// ======================================================================
function renderListasEmails() {
    const host = document.getElementById('listaEmailsContainer');
    const estadoEl = document.getElementById('estadoEmails');
    const listas = configState.listasEmails || [];

    if (!listas.length) {
        estadoEl.textContent = 'Nenhuma lista de e-mail encontrada.';
        if (host) host.innerHTML = '';
        return;
    }
    estadoEl.textContent = '';

    if (!host) return;

    host.innerHTML = listas.map(l => {
        const isClientesIsentos = l.chave === 'clientes_isento';
        const rawEmails = l.emails || [];

        if (isClientesIsentos) {
            const geral = Array.isArray(rawEmails.geral) ? rawEmails.geral.join(', ') : '';
            const f1 = (rawEmails.filiais && Array.isArray(rawEmails.filiais['1'])) ? rawEmails.filiais['1'].join(', ') : '';
            const f6 = (rawEmails.filiais && Array.isArray(rawEmails.filiais['6'])) ? rawEmails.filiais['6'].join(', ') : '';

            return `
            <div class="md:col-span-2 rounded-2xl border border-indigo-200 bg-white p-5 shadow-xs">
                <div class="flex items-center justify-between pb-3 mb-3 border-b border-indigo-100">
                    <div>
                        <div class="flex items-center gap-2">
                            <h4 class="font-bold text-sm text-slate-800">${configEsc(l.titulo)}</h4>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-teal-100 text-teal-800">Filiais & Geral</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">${configEsc(l.descricao)}</p>
                    </div>
                    <span class="text-[10px] text-slate-400">Atualizado: ${configDate(l.atualizado_em)}</span>
                </div>

                <form onsubmit="salvarListaIsentos(event, '${configEsc(l.chave)}')" class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <label class="block">
                            <span class="config-label text-indigo-700">Visão Geral (Diretoria / Todas Filiais)</span>
                            <textarea name="geral" rows="2" class="config-field text-xs">${configEsc(geral)}</textarea>
                        </label>
                        <label class="block">
                            <span class="config-label text-sky-700">Filial 1 · Matriz SC</span>
                            <textarea name="filial_1" rows="2" class="config-field text-xs">${configEsc(f1)}</textarea>
                        </label>
                        <label class="block">
                            <span class="config-label text-purple-700">Filial 6 · PR</span>
                            <textarea name="filial_6" rows="2" class="config-field text-xs">${configEsc(f6)}</textarea>
                        </label>
                    </div>
                    <div class="flex justify-end pt-2">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar Destinatários Clientes Isentos
                        </button>
                    </div>
                </form>
            </div>
            `;
        }

        const emailsStr = Array.isArray(rawEmails) ? rawEmails.join(', ') : String(rawEmails);
        const count = Array.isArray(rawEmails) ? rawEmails.length : (emailsStr ? emailsStr.split(',').length : 0);

        return `
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                    <h4 class="font-bold text-sm text-slate-800">${configEsc(l.titulo)}</h4>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 text-slate-600">${count} e-mail(s)</span>
                </div>
                <p class="text-xs text-slate-400 mb-3">${configEsc(l.descricao)}</p>

                <form onsubmit="salvarListaSimples(event, '${configEsc(l.chave)}')">
                    <label class="block mb-3">
                        <span class="config-label">E-mails (separados por vírgula)</span>
                        <textarea name="emails" rows="3" class="config-field text-xs font-mono" placeholder="email1@empresa.com, email2@empresa.com">${configEsc(emailsStr)}</textarea>
                    </label>
                    <div class="flex items-center justify-between pt-1">
                        <span class="text-[10px] text-slate-400">Modificado por ${configEsc(l.atualizado_por || '—')}</span>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm">
                            <i class="fa-solid fa-floppy-disk"></i> Salvar Lista
                        </button>
                    </div>
                </form>
            </div>
        </div>
        `;
    }).join('');
}

async function salvarListaSimples(event, chave) {
    event.preventDefault();
    const form = event.target;
    const emailsStr = form.elements.emails.value.trim();

    try {
        await configRequest('emails', 'POST', {
            chave: chave,
            emails: emailsStr
        });
        showToast('Lista de e-mails salva com sucesso!');
        await carregarConfiguracoes();
    } catch (e) {
        showError('Erro ao salvar', e.message);
    }
}

async function salvarListaIsentos(event, chave) {
    event.preventDefault();
    const form = event.target;
    const geral = form.elements.geral.value.split(',').map(s => s.trim()).filter(Boolean);
    const f1 = form.elements.filial_1.value.split(',').map(s => s.trim()).filter(Boolean);
    const f6 = form.elements.filial_6.value.split(',').map(s => s.trim()).filter(Boolean);

    const payload = {
        chave: chave,
        emails: {
            geral: geral,
            filiais: {
                '1': f1,
                '6': f6
            }
        }
    };

    try {
        await configRequest('emails', 'POST', payload);
        showToast('Destinatários de Clientes Isentos salvos com sucesso!');
        await carregarConfiguracoes();
    } catch (e) {
        showError('Erro ao salvar', e.message);
    }
}

// ======================================================================
// RENDERIZAÇÃO DE GUIA ST, FLEX E AMOSTRAS
// ======================================================================
function renderGuia() {
    const host = document.getElementById('listaGuias');
    document.getElementById('estadoGuia').textContent = configState.regras.length ? '' : 'Nenhuma regra cadastrada.';
    host.innerHTML = configState.regras.map(g => `
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3.5 shadow-2xs ${g.ativo ? '' : 'opacity-60 bg-slate-50'}">
            <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-800 flex items-center justify-center font-bold text-sm flex-shrink-0"><i class="fa-solid fa-percent"></i></div>
            <div class="flex-1 min-w-[200px]">
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-sm text-slate-800">NCM ${configEsc(g.ncm)}</span>
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${g.ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600'}">${g.ativo ? 'Ativa' : 'Desativada'}</span>
                </div>
                <div class="text-xs text-slate-500 font-medium">${configEsc(g.descricao || 'Sem descrição')}</div>
                <div class="text-xs text-slate-500 mt-1 flex flex-wrap gap-2">
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700">MVA: <b>${Number(g.mva_percentual).toLocaleString('pt-BR')}%</b></span>
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700">Alíquota PR: <b>${Number(g.aliquota_interna_percentual).toLocaleString('pt-BR')}%</b></span>
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700">Crédito: <b>${Number(g.credito_interestadual_percentual).toLocaleString('pt-BR')}%</b></span>
                    <span class="px-2 py-0.5 rounded bg-slate-100 text-slate-700">Vigência: <b>${configDate(g.vigencia_inicio)}</b></span>
                </div>
            </div>
            ${g.ativo ? `<button data-disable-guide="${Number(g.id)}" class="rounded-xl px-3 py-2 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100 transition"><i class="fa-solid fa-trash mr-1"></i>Remover</button>` : ''}
        </div>`).join('');
}

function campoFlex(rotulo, nome, valor, tipo = 'text', extras = '') {
    return `<label class="config-label">${rotulo}<input class="config-field" name="${nome}" type="${tipo}" value="${configEsc(valor)}" ${extras}></label>`;
}

function renderFlex() {
    const host = document.getElementById('listaFlex');
    document.getElementById('estadoFlex').textContent = configState.flex.length ? '' : 'Nenhum gestor configurado.';
    host.innerHTML = configState.flex.map((c, index) => {
        const isMatriz = Number(c.filial) === 1;
        return `
        <form class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition-all hover:border-violet-300" data-flex-form="${configEsc(c.id || 'novo-' + index)}" data-active="${c.ativo ? '1' : '0'}">
            <div class="flex flex-wrap justify-between items-center gap-3 mb-4 pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl ${c.ativo ? 'bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-400'} flex items-center justify-center font-bold text-sm">
                        <i class="fa-solid fa-user-tie"></i>
                    </div>
                    <div>
                        <div class="font-extrabold text-sm text-slate-800 flex items-center gap-2">
                            ${c.id ? 'Gestor #' + configEsc(c.gestor_id) + ' · ' + configEsc(c.nome) : 'Novo Gestor Flex'}
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-bold ${isMatriz ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'}">
                                ${isMatriz ? 'Matriz (1)' : 'Filial PR (6)'}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-400">Código ERP/Mercos: ${configEsc(c.gestor_id || 'Não definido')}</div>
                    </div>
                </div>
                
                <label class="flex items-center gap-2 text-xs font-bold cursor-pointer ${c.ativo ? 'text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl border border-emerald-200' : 'text-slate-500 bg-slate-100 px-3 py-1.5 rounded-xl'}">
                    <input name="ativo" type="checkbox" class="w-4 h-4 accent-emerald-600 rounded" ${c.ativo ? 'checked' : ''}> 
                    <span>${c.ativo ? '● Rotina Ativa' : '○ Rotina Pausada'}</span>
                </label>
            </div>
            
            <input type="hidden" name="id" value="${configEsc(c.id || '')}">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                ${campoFlex('Código do gestor', 'gestor_id', c.gestor_id, 'number', 'min="1" required')}
                ${campoFlex('Nome / identificação', 'nome', c.nome, 'text', 'maxlength="120" required')}
                <label class="config-label">Filial<select class="config-field" name="filial"><option value="1" ${Number(c.filial) === 1 ? 'selected' : ''}>1 · Matriz</option><option value="6" ${Number(c.filial) === 6 ? 'selected' : ''}>6 · Filial</option></select></label>
                ${campoFlex('Saldo mínimo (R$)', 'valor_minimo', c.valor_minimo, 'number', 'min="0.01" step="0.01" required')}
                ${campoFlex('Valor por empréstimo (R$)', 'valor_emprestimo', c.valor_emprestimo, 'number', 'min="0.01" step="0.01" required')}
                ${campoFlex('Máximo de múltiplos', 'max_multiplos', c.max_multiplos || 5, 'number', 'min="1" max="50" required')}
                ${campoFlex('Falhas para alertar', 'falhas_para_alertar', c.falhas_para_alertar || 5, 'number', 'min="1" max="100" required')}
                ${campoFlex('E-mails para notificação (vírgula)', 'emails', c.emails || '', 'text', 'placeholder="tiago@empresa.com, financeiro@..."')}
                <label class="config-label sm:col-span-2 lg:col-span-4">Motivo do empréstimo registrado no ERP/Mercos<textarea class="config-field" name="motivo" rows="2" maxlength="1000" required>${configEsc(c.motivo)}</textarea></label>
            </div>

            <!-- Painel de Telemetria / Última Execução -->
            <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex flex-wrap items-center gap-3 text-slate-500">
                    <span class="px-2.5 py-1 bg-slate-50 rounded-lg border border-slate-200">
                        Último Saldo: <b class="text-slate-800">${c.ultimo_saldo == null ? '—' : 'R$ ' + Number(c.ultimo_saldo).toLocaleString('pt-BR', {minimumFractionDigits:2})}</b>
                    </span>
                    <span class="px-2.5 py-1 bg-slate-50 rounded-lg border border-slate-200">
                        Última Verificação: <b class="text-slate-800">${c.ultima_verificacao ? configEsc(c.ultima_verificacao) : 'Nunca verificado'}</b>
                    </span>
                    <span class="px-2.5 py-1 bg-slate-50 rounded-lg border border-slate-200">
                        Falhas Seguidas: <b class="${Number(c.falhas_seguidas || 0) > 0 ? 'text-rose-600' : 'text-emerald-700'}">${Number(c.falhas_seguidas || 0)}</b>
                    </span>
                    ${c.ultimo_erro ? `<span class="text-rose-600 font-bold"><i class="fa-solid fa-circle-exclamation mr-1"></i>${configEsc(c.ultimo_erro)}</span>` : ''}
                </div>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-violet-700 text-white text-xs font-bold hover:bg-violet-800 transition flex items-center gap-1.5 shadow-sm">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar Gestor
                </button>
            </div>
        </form>`;
    }).join('');
}

function renderAmostra() {
    const geral = configState.amostraGeral || { percentual_padrao: 30 };
    const perc = Number(geral.percentual_padrao) || 0;
    const inp = document.getElementById('amostraPercPadrao');
    if (inp) inp.value = perc;

    const resEl = document.getElementById('amostraResumoPadrao');
    if (resEl) {
        resEl.innerHTML = `<div class="flex items-center gap-2"><i class="fa-solid fa-circle-check text-emerald-600"></i><div><b>${perc}% liberado</b> para Filial PR · <b>${(100 - perc).toFixed(1).replace('.0', '')}% retido</b> na Matriz</div></div>`;
    }

    const host = document.getElementById('listaAmostraRegras');
    const estadoEl = document.getElementById('estadoAmostra');
    const contEl = document.getElementById('contRegrasAmostra');
    const regras = configState.amostraRegras || [];

    if (contEl) contEl.textContent = `${regras.length} regra(s) cadastrada(s)`;
    if (estadoEl) estadoEl.textContent = regras.length ? '' : 'Nenhuma regra específica cadastrada. Todos os itens usam o percentual padrão.';

    if (host) {
        host.innerHTML = regras.map(r => `
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-3 shadow-2xs ${r.ativo ? '' : 'opacity-60 bg-slate-50'}">
                <img src="${configEsc(r.foto_url || 'https://placehold.co/100x100?text=S/F')}" 
                     class="w-12 h-12 object-contain rounded-xl bg-slate-50 border border-slate-100 flex-none" 
                     onerror="this.src='https://placehold.co/100x100?text=S/F'">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-800 truncate">${configEsc(r.descricao)}</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-slate-100 text-slate-600">#${configEsc(r.iditem)}</span>
                        <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-500">Ref: ${configEsc(r.referencia || '---')}</span>
                    </div>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="px-2 py-0.5 rounded-lg text-xs font-extrabold ${Number(r.percentual_liberado) === 0 ? 'bg-rose-100 text-rose-700 border border-rose-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200'}">
                            ${Number(r.percentual_liberado)}% Liberado
                        </span>
                        ${r.motivo ? `<span class="text-[11px] text-slate-400 italic truncate">${configEsc(r.motivo)}</span>` : ''}
                    </div>
                </div>
                <div class="flex items-center gap-1.5 flex-none">
                    <button type="button" data-toggle-amostra="${configEsc(r.id)}" data-item="${configEsc(r.iditem)}" data-perc="${configEsc(r.percentual_liberado)}" data-ativo="${r.ativo ? '1' : '0'}"
                            class="px-2.5 py-1.5 rounded-xl text-xs font-bold ${r.ativo ? 'bg-slate-100 text-slate-600 hover:bg-slate-200' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}">
                        ${r.ativo ? 'Pausar' : 'Ativar'}
                    </button>
                    <button type="button" data-delete-amostra="${configEsc(r.id)}"
                            class="px-2.5 py-1.5 rounded-xl text-xs font-bold bg-rose-50 text-rose-600 hover:bg-rose-100">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
        `).join('');
    }
}

async function novaRegraAmostra() {
    let itensEncontrados = [];
    let itemSelecionado = null;

    const htmlModal = `
        <div class="text-left space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Buscar Item (Material / Amostra)</label>
                <div class="relative">
                    <input id="buscaItemAmostraModal" type="search" placeholder="Código, referência ou descrição..." 
                           class="w-full px-3 py-2 border rounded-xl text-sm focus:outline-emerald-600">
                    <div id="resultadoBuscaAmostra" class="hidden absolute left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-56 overflow-y-auto z-50"></div>
                </div>
            </div>

            <div id="previewItemAmostra" class="hidden p-3 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center gap-3">
                <img id="prevImgAmostra" src="" class="w-12 h-12 object-contain rounded-xl bg-white border border-slate-200">
                <div class="flex-1 min-w-0">
                    <div id="prevDescAmostra" class="text-xs font-bold text-emerald-950 truncate"></div>
                    <div id="prevRefAmostra" class="text-[11px] text-emerald-700"></div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">% Liberado para Filial PR (6)</label>
                <div class="relative">
                    <input id="percLiberadoAmostraModal" type="number" min="0" max="100" step="0.5" value="50"
                           class="w-full px-3 py-2 border rounded-xl text-base font-bold text-emerald-800 focus:outline-emerald-600 pr-8">
                    <span class="absolute right-3 top-2.5 text-sm font-bold text-slate-400">%</span>
                </div>
                <div class="text-[11px] text-slate-400 mt-1">Informe 0% para bloquear este item para a Filial PR, ou 50%, 100%, etc.</div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-600 uppercase mb-1">Motivo / Observação (Opcional)</label>
                <input id="motivoAmostraModal" type="text" maxlength="200" placeholder="Ex: Material exclusivo, lote restrito..."
                       class="w-full px-3 py-2 border rounded-xl text-xs focus:outline-emerald-600">
            </div>
        </div>
    `;

    const res = await Swal.fire({
        title: 'Nova Regra por Item',
        html: htmlModal,
        showCancelButton: true,
        confirmButtonText: 'Salvar Regra',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#059669',
        focusConfirm: false,
        didOpen: () => {
            const inputBusca = document.getElementById('buscaItemAmostraModal');
            const resBox = document.getElementById('resultadoBuscaAmostra');
            let timerBusca = null;

            inputBusca.addEventListener('input', () => {
                clearTimeout(timerBusca);
                const q = inputBusca.value.trim();
                if (q.length < 2) {
                    resBox.classList.add('hidden');
                    return;
                }
                timerBusca = setTimeout(async () => {
                    resBox.innerHTML = '<div class="p-3 text-center text-xs text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Buscando...</div>';
                    resBox.classList.remove('hidden');
                    try {
                        const data = await configRequest(`amostra/itens-busca?q=${encodeURIComponent(q)}`);
                        itensEncontrados = data.itens || [];
                        if (!itensEncontrados.length) {
                            resBox.innerHTML = '<div class="p-3 text-center text-xs text-slate-400">Nenhum item encontrado</div>';
                            return;
                        }
                        resBox.innerHTML = itensEncontrados.map((it, idx) => `
                            <div class="flex items-center gap-2 p-2 hover:bg-emerald-50 cursor-pointer border-b last:border-0 text-left" data-item-idx="${idx}">
                                <img src="${configEsc(it.foto_url)}" class="w-8 h-8 object-contain rounded bg-white border" onerror="this.src='https://placehold.co/100x100?text=S/F'">
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-bold text-slate-800 truncate">${configEsc(it.descricao)}</div>
                                    <div class="text-[10px] text-slate-500">Cód: ${it.iditem} · Ref: ${configEsc(it.referencia || '---')}</div>
                                </div>
                            </div>
                        `).join('');

                        resBox.querySelectorAll('[data-item-idx]').forEach(el => {
                            el.addEventListener('click', () => {
                                const it = itensEncontrados[Number(el.dataset.itemIdx)];
                                itemSelecionado = it;
                                document.getElementById('prevImgAmostra').src = it.foto_url || 'https://placehold.co/100x100?text=S/F';
                                document.getElementById('prevDescAmostra').textContent = it.descricao;
                                document.getElementById('prevRefAmostra').textContent = `Cód: ${it.iditem} · Ref: ${it.referencia || '---'}`;
                                document.getElementById('previewItemAmostra').classList.remove('hidden');
                                resBox.classList.add('hidden');
                                inputBusca.value = it.descricao;
                            });
                        });
                    } catch (err) {
                        resBox.innerHTML = `<div class="p-3 text-center text-xs text-rose-500">${configEsc(err.message)}</div>`;
                    }
                }, 300);
            });
        },
        preConfirm: () => {
            if (!itemSelecionado) {
                Swal.showValidationMessage('Selecione um item da lista!');
                return false;
            }
            const perc = Number(document.getElementById('percLiberadoAmostraModal').value);
            if (isNaN(perc) || perc < 0 || perc > 100) {
                Swal.showValidationMessage('O percentual deve estar entre 0% e 100%!');
                return false;
            }
            return {
                iditem: itemSelecionado.iditem,
                percentual_liberado: perc,
                motivo: document.getElementById('motivoAmostraModal').value.trim(),
                ativo: true,
            };
        }
    });

    if (!res.isConfirmed || !res.value) return;

    try {
        await configRequest('amostra/regra-item', 'POST', res.value);
        showToast('Regra de item salva com sucesso!');
        await carregarConfiguracoes();
    } catch (e) {
        showError('Erro ao salvar regra', e.message);
    }
}

async function carregarConfiguracoes() {
    document.getElementById('estadoGuia').textContent = 'Carregando regras...';
    document.getElementById('estadoFlex').textContent = 'Carregando configurações...';
    if (document.getElementById('estadoEmails')) {
        document.getElementById('estadoEmails').textContent = 'Carregando listas de e-mails...';
    }

    const results = await Promise.allSettled([
        configRequest('guia'),
        configRequest('guia/ncms'),
        configRequest('flex-minimo'),
        configRequest('amostra'),
        configRequest('emails'),
    ]);

    if (results[0].status === 'fulfilled') {
        configState.regras = results[0].value.regras || [];
        renderGuia();
    } else {
        document.getElementById('estadoGuia').innerHTML = `<span class="text-rose-600">${configEsc(results[0].reason.message)}</span>`;
    }

    if (results[1].status === 'fulfilled') {
        configState.ncms = results[1].value.ncms || [];
        configState.impostos = results[1].value.impostos || [];
    } else {
        configState.ncms = [];
        configState.impostos = [];
    }

    if (results[2].status === 'fulfilled') {
        configState.flex = results[2].value.configuracoes || [];
        renderFlex();
    }

    if (results[3] && results[3].status === 'fulfilled') {
        configState.amostraGeral = results[3].value.geral || null;
        configState.amostraRegras = results[3].value.regras || [];
        renderAmostra();
    } else if (results[3]) {
        document.getElementById('estadoAmostra').innerHTML = `<span class="text-rose-600">${configEsc(results[3].reason.message)}</span>`;
    }

    if (results[4] && results[4].status === 'fulfilled') {
        configState.listasEmails = results[4].value.listas || [];
        renderListasEmails();
    } else if (results[4]) {
        if (document.getElementById('estadoEmails')) {
            document.getElementById('estadoEmails').innerHTML = `<span class="text-rose-600">${configEsc(results[4].reason.message)}</span>`;
        }
    }

    if (results[2].status !== "fulfilled") {
        document.getElementById('estadoFlex').innerHTML = `<span class="text-rose-600">${configEsc(results[2].reason.message)}</span>`;
    }
}

const GUIA_PADRAO = { mva: 59.6, interna: 19.5, credito: 12, opMva: '*', opFinal: '-' };

function simularGuia(e, c) {
    const aplica = (op, a, b) => op === '+' ? a + b : op === '-' ? a - b : op === '*' ? a * b : (b === 0 ? NaN : a / b);
    const fator = c.opMva === '+' || c.opMva === '-' ? e * (c.mva / 100) : 1 + c.mva / 100;
    const g = c.opMva === '*' ? e * fator : c.opMva === '/' ? (fator === 0 ? NaN : e / fator) : aplica(c.opMva, e, fator);
    const f = e * c.credito / 100;
    const h = g * c.interna / 100;
    return { f, g, h, i: aplica(c.opFinal, h, f) };
}

async function novaRegraGuia() {
    let ncmsTela = configState.ncms;
    const marcados = new Set();
    const brl = (n) => Number.isFinite(n) ? n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : '—';
    const campo = 'width:100%;margin:.25rem 0;box-sizing:border-box';
    const ajuda = (t) => `<span class="block text-[11px] font-normal text-slate-500 leading-snug">${t}</span>`;
    const secao = (n, t) => `<div class="text-xs font-extrabold text-slate-700 mt-3 mb-1"><span class="inline-flex w-5 h-5 rounded-full bg-sky-700 text-white items-center justify-center text-[10px] mr-1">${n}</span>${t}</div>`;
    const opcoesImposto = configState.impostos.map(i => `<option value="${Number(i.idimposto)}">${i.st_pr ? '★ ' : ''}${configEsc(i.descricao)} · ${Number(i.quantidade_itens)} item(ns)</option>`).join('');

    const result = await Swal.fire({
        title: 'Adicionar NCMs à guia',
        confirmButtonText: 'Adicionar selecionados',
        cancelButtonText: 'Cancelar',
        showCancelButton: true,
        confirmButtonColor: '#0369a1',
        width: 820,
        html: `<div class="text-left">
            ${secao(1, 'Escolha os NCMs que participam')}
            <label class="block text-xs font-bold">Filtrar pelo imposto do cadastro (Matriz)
                <select id="guiaImposto" class="swal2-input" style="${campo}"><option value="">Todos os impostos</option>${opcoesImposto}</select>
                ${ajuda('★ = imposto com ST no Paraná no ERP (situação 010). Usa o imposto do estoque da filial 1, o mesmo da consulta de itens.')}
            </label>
            <input id="guiaBusca" class="swal2-input" style="${campo}" placeholder="Buscar por NCM ou nome do item">
            <div class="flex items-center justify-between text-xs"><button type="button" id="guiaMarcarTodos" class="font-bold text-sky-700">Marcar todos os visíveis</button><span id="guiaContagem" class="text-slate-500"></span></div>
            <div id="guiaListaNcm" class="rounded-xl border border-slate-200 p-2" style="max-height:190px;overflow:auto"></div>

            ${secao(2, 'Parâmetros do cálculo')}
            <div class="flex items-center justify-between gap-2 rounded-xl bg-sky-50 border border-sky-100 p-2 text-[11px] text-sky-900">
                <span>Padrão da planilha: MVA 59,6% · alíquota PR 19,5% · crédito 12% · Valor Guia = ICMS-ST − Crédito ICMS Origem.</span>
                <button type="button" id="guiaRestaurar" class="font-bold text-sky-700 whitespace-nowrap">Restaurar padrão</button>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 mt-2">
                <label class="block text-xs font-bold">MVA do Paraná (%)<input id="guiaMva" type="number" min="0" max="1000" step="0.0001" class="swal2-input" style="${campo}">
                    ${ajuda('Margem de valor agregado aplicada sobre a Soma Total dos Itens para formar a Base de Cálculo ICMS-ST. <span id="guiaMvaErp"></span>')}</label>
                <label class="block text-xs font-bold">Base de Cálculo ICMS-ST · como aplicar a MVA<select id="guiaOperadorMva" class="swal2-input" style="${campo}"><option value="*">Multiplicar: Soma × (1 + MVA) — padrão</option><option value="+">Somar: Soma + (Soma × MVA)</option><option value="-">Subtrair: Soma − (Soma × MVA)</option><option value="/">Dividir: Soma ÷ (1 + MVA)</option></select>
                    ${ajuda('Forma de calcular a Base de Cálculo ICMS-ST a partir da Soma Total dos Itens.')}</label>
                <label class="block text-xs font-bold">ICMS-ST · Alíquota interna do PR (%)<input id="guiaInterna" type="number" min="0" max="100" step="0.0001" class="swal2-input" style="${campo}">
                    ${ajuda('Percentual aplicado sobre a Base de Cálculo ICMS-ST para achar o ICMS-ST.')}</label>
                <label class="block text-xs font-bold">Crédito ICMS Origem (%)<input id="guiaCredito" type="number" min="0" max="100" step="0.0001" class="swal2-input" style="${campo}">
                    ${ajuda('Crédito do ICMS destacado na nota (SC → PR = 12%), calculado sobre a Soma Total dos Itens.')}</label>
                <label class="block text-xs font-bold sm:col-span-2">Valor Guia · operação final
                    <select id="guiaOperadorFinal" class="swal2-input" style="${campo}"><option value="-">Subtrair: ICMS-ST − Crédito ICMS Origem — padrão</option><option value="+">Somar: ICMS-ST + Crédito ICMS Origem</option><option value="*">Multiplicar: ICMS-ST × Crédito ICMS Origem</option><option value="/">Dividir: ICMS-ST ÷ Crédito ICMS Origem</option></select>
                    ${ajuda('Operação final entre o ICMS-ST e o Crédito ICMS Origem, que resulta no Valor Guia.')}</label>
            </div>
            <label class="block text-xs font-bold mt-1">Vigência a partir de<input id="guiaVigencia" type="date" class="swal2-input" style="${campo}">
                ${ajuda('Data inicial da regra. Para mudar percentuais depois, cadastre uma nova vigência.')}</label>

            ${secao(3, 'Simulação (confira antes de salvar)')}
            <div class="grid grid-cols-1 sm:grid-cols-[150px_1fr] gap-3 items-start">
                <label class="block text-xs font-bold">Soma Total dos Itens (exemplo, R$)<input id="guiaExemplo" type="number" min="0" step="0.01" value="10000" class="swal2-input" style="${campo}">
                    ${ajuda('No relatório é a soma dos itens ST 010 do NCM na nota faturada.')}</label>
                <div id="guiaSimulacao" class="rounded-xl bg-slate-50 border border-slate-200 p-2 text-[11px] text-slate-700 space-y-0.5"></div>
            </div>
        </div>`,
        didOpen: () => {
            const el = (id) => document.getElementById(id);
            const lista = el('guiaListaNcm');
            const contagem = () => { el('guiaContagem').textContent = `${marcados.size} selecionado(s)`; };
            const lerConfig = () => ({
                mva: parseFloat(el('guiaMva').value), interna: parseFloat(el('guiaInterna').value),
                credito: parseFloat(el('guiaCredito').value), opMva: el('guiaOperadorMva').value, opFinal: el('guiaOperadorFinal').value,
            });
            const simular = () => {
                const c = lerConfig(); const e = parseFloat(el('guiaExemplo').value) || 0;
                if ([c.mva, c.interna, c.credito].some(Number.isNaN)) { el('guiaSimulacao').textContent = 'Preencha os percentuais.'; return; }
                const r = simularGuia(e, c);
                el('guiaSimulacao').innerHTML = `<div>Crédito ICMS Origem = ${brl(e)} × ${c.credito}% = <b>${brl(r.f)}</b></div>
                    <div>Base de Cálculo ICMS-ST = <b>${brl(r.g)}</b></div>
                    <div>ICMS-ST = ${brl(r.g)} × ${c.interna}% = <b>${brl(r.h)}</b></div>
                    <div class="text-sky-800">Valor Guia = ICMS-ST ${c.opFinal} Crédito = <b>${brl(r.i)}</b></div>`;
            };
            const restaurar = () => {
                el('guiaMva').value = GUIA_PADRAO.mva; el('guiaInterna').value = GUIA_PADRAO.interna; el('guiaCredito').value = GUIA_PADRAO.credito;
                el('guiaOperadorMva').value = GUIA_PADRAO.opMva; el('guiaOperadorFinal').value = GUIA_PADRAO.opFinal; simular();
            };
            const desenhar = () => {
                const termo = el('guiaBusca').value.trim().toLowerCase();
                const visiveis = ncmsTela.filter(n => !termo || n.ncm.includes(termo) || String(n.exemplo_item || '').toLowerCase().includes(termo));
                lista.innerHTML = visiveis.length ? visiveis.map(n => `
                    <label class="flex items-start gap-2 py-1 text-xs ${n.participa ? 'opacity-50' : ''}">
                        <input type="checkbox" data-ncm="${configEsc(n.ncm)}" ${marcados.has(n.ncm) ? 'checked' : ''} ${n.participa ? 'disabled' : ''} class="mt-0.5">
                        <span><b>${configEsc(n.ncm)}</b> · ${Number(n.quantidade_itens)} item(ns) · ${configEsc(n.exemplo_item || '')}${n.mva_erp ? ` · MVA ERP ${Number(n.mva_erp).toLocaleString('pt-BR')}%` : ''}${n.participa ? ' · <i>já participa</i>' : ''}</span>
                    </label>`).join('') : '<div class="text-xs text-slate-400 p-2">Nenhum NCM encontrado.</div>';
                contagem();
            };
            lista.addEventListener('change', (e) => {
                const ncm = e.target.dataset.ncm;
                if (!ncm) return;
                e.target.checked ? marcados.add(ncm) : marcados.delete(ncm);
                contagem();
            });
            el('guiaBusca').addEventListener('input', desenhar);
            el('guiaMarcarTodos').addEventListener('click', () => {
                lista.querySelectorAll('input[data-ncm]:not(:disabled)').forEach(c => { c.checked = true; marcados.add(c.dataset.ncm); });
                contagem();
            });
            el('guiaRestaurar').addEventListener('click', restaurar);
            ['guiaMva', 'guiaInterna', 'guiaCredito', 'guiaOperadorMva', 'guiaOperadorFinal', 'guiaExemplo'].forEach(id => el(id).addEventListener('input', simular));
            el('guiaImposto').addEventListener('change', async (e) => {
                lista.innerHTML = '<div class="text-xs text-slate-400 p-2">Carregando...</div>';
                const imp = configState.impostos.find(i => String(i.idimposto) === e.target.value);
                el('guiaMvaErp').textContent = imp?.mva_erp ? `MVA no ERP para este imposto: ${Number(imp.mva_erp).toLocaleString('pt-BR')}%.` : '';
                try {
                    const q = e.target.value === '' ? 'guia/ncms' : `guia/ncms?idimposto=${encodeURIComponent(e.target.value)}`;
                    ncmsTela = (await configRequest(q)).ncms || [];
                } catch (err) {
                    ncmsTela = [];
                }
                desenhar();
            });
            restaurar();
            desenhar();
        },
        preConfirm: () => {
            if (!marcados.size) {
                Swal.showValidationMessage('Selecione ao menos um NCM.');
                return false;
            }
            if (!document.getElementById('guiaVigencia').value) {
                Swal.showValidationMessage('Informe a data de início da vigência.');
                return false;
            }
            const imp = document.getElementById('guiaImposto');
            return {
                ncms: [...marcados],
                mva_percentual: document.getElementById('guiaMva').value,
                aliquota_interna_percentual: document.getElementById('guiaInterna').value,
                credito_interestadual_percentual: document.getElementById('guiaCredito').value,
                operador_mva: document.getElementById('guiaOperadorMva').value,
                operador_guia: document.getElementById('guiaOperadorFinal').value,
                vigencia_inicio: document.getElementById('guiaVigencia').value,
                descricao: imp.value ? imp.selectedOptions[0].textContent.replace(/^★ /, '').split(' · ')[0].slice(0, 120) : '',
            };
        },
    });
    if (!result.isConfirmed) return;
    try {
        const r = await configRequest('guia', 'POST', result.value);
        await carregarConfiguracoes();
        showToast(`${r.criados} NCM(s) adicionados${r.ja_existiam ? `, ${r.ja_existiam} já participavam` : ''}.`);
    } catch (e) {
        showError('Não foi possível salvar', e.message);
    }
}

document.getElementById('btnNovaRegra').addEventListener('click', novaRegraGuia);
document.getElementById('btnNovoFlex').addEventListener('click', () => {
    configState.flex.push({ gestor_id: '', nome: '', filial: 1, valor_minimo: 1000, valor_emprestimo: 1000, max_multiplos: 5, falhas_para_alertar: 5, motivo: '', emails: '', ativo: false });
    renderFlex();
    document.querySelector('#listaFlex form:last-child input[name="gestor_id"]')?.focus();
});

document.getElementById('listaGuias').addEventListener('click', async (event) => {
    const button = event.target.closest('[data-disable-guide]');
    if (!button) return;
    const confirm = await confirmar('Remover este NCM da guia?', 'O NCM deixa de participar do cálculo, inclusive nas solicitações já consultadas.', 'Remover');
    if (!confirm.isConfirmed) return;
    try {
        await configRequest('guia', 'POST', { desativar_id: Number(button.dataset.disableGuide) });
        await carregarConfiguracoes();
        showToast('NCM removido da guia.');
    } catch (e) {
        showError('Não foi possível desativar', e.message);
    }
});

document.getElementById('listaFlex').addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-flex-form]');
    if (!form) return;
    event.preventDefault();
    const values = Object.fromEntries(new FormData(form).entries());
    values.ativo = form.elements.ativo.checked;
    values.id = values.id ? Number(values.id) : 0;
    values.gestor_id = Number(values.gestor_id);
    values.filial = Number(values.filial);
    values.max_multiplos = Number(values.max_multiplos);
    values.falhas_para_alertar = Number(values.falhas_para_alertar);
    if (values.ativo && form.dataset.active !== '1') {
        const confirm = await Swal.fire({
            icon: 'warning',
            title: 'Ativar o Flex Mínimo?',
            text: 'Confirme que a rotina equivalente foi desligada no ERP. Sem isso, pode ocorrer empréstimo duplicado.',
            showCancelButton: true,
            confirmButtonText: 'Ativar',
            cancelButtonText: 'Voltar',
            confirmButtonColor: '#b45309',
        });
        if (!confirm.isConfirmed) return;
    }
    try {
        await configRequest('flex-minimo', 'POST', values);
        await carregarConfiguracoes();
        showToast('Configuração do Flex Mínimo salva.');
    } catch (e) {
        showError('Não foi possível salvar', e.message);
    }
});

document.getElementById('btnNovaRegraAmostra')?.addEventListener('click', novaRegraAmostra);

document.getElementById('formAmostraGeral')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const perc = Number(document.getElementById('amostraPercPadrao').value);
    if (isNaN(perc) || perc < 0 || perc > 100) {
        showError('Valor inválido', 'O percentual padrão deve ser entre 0% e 100%.');
        return;
    }
    try {
        await configRequest('amostra/geral', 'POST', { percentual_padrao: perc });
        showToast('Percentual padrão salvo com sucesso!');
        await carregarConfiguracoes();
    } catch (err) {
        showError('Erro ao salvar', err.message);
    }
});

document.getElementById('listaAmostraRegras')?.addEventListener('click', async (e) => {
    const btnToggle = e.target.closest('[data-toggle-amostra]');
    if (btnToggle) {
        const iditem = Number(btnToggle.dataset.item);
        const perc = Number(btnToggle.dataset.perc);
        const novoAtivo = btnToggle.dataset.ativo !== '1';
        try {
            await configRequest('amostra/regra-item', 'POST', {
                iditem,
                percentual_liberado: perc,
                ativo: novoAtivo,
            });
            showToast(`Regra ${novoAtivo ? 'ativada' : 'pausada'}.`);
            await carregarConfiguracoes();
        } catch (err) {
            showError('Erro ao atualizar regra', err.message);
        }
        return;
    }

    const btnDel = e.target.closest('[data-delete-amostra]');
    if (btnDel) {
        const id = Number(btnDel.dataset.deleteAmostra);
        const confirm = await confirmar('Excluir regra deste item?', 'O item voltará a seguir o percentual padrão da Filial PR.', 'Excluir');
        if (!confirm.isConfirmed) return;
        try {
            await configRequest(`amostra/regra-item/${id}`, 'DELETE');
            showToast('Regra excluída.');
            await carregarConfiguracoes();
        } catch (err) {
            showError('Erro ao excluir regra', err.message);
        }
    }
});

window.addEventListener('DOMContentLoaded', () => {
    carregarConfiguracoes();
    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');
    if (tab) {
        filtrarSecao(tab);
    }
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
