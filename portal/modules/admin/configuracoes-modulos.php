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
    .config-field { width: 100%; margin-top: .35rem; border: 1px solid #cbd5e1; border-radius: .75rem; padding: .65rem .75rem; font-size: .875rem; }
    .config-label { display: block; color: #64748b; font-size: .68rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
</style>

<div class="bg-white rounded-3xl p-5 mb-6 flex items-center gap-3 shadow-sm border border-slate-100">
    <div class="w-12 h-12 bg-amber-100 text-amber-700 rounded-2xl flex items-center justify-center">
        <i class="fa-solid fa-sliders text-xl"></i>
    </div>
    <div>
        <h2 class="text-lg font-bold text-slate-800 leading-tight">CONFIGURAÇÕES DOS MÓDULOS</h2>
        <p class="text-xs text-slate-400">Parâmetros de negócio administrados por módulo, com validação própria.</p>
    </div>
</div>

<div class="space-y-6">
    <section class="bg-white rounded-3xl p-4 sm:p-6 shadow-sm border border-slate-100">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-base font-extrabold text-slate-800"><i class="fa-solid fa-truck-ramp-box text-sky-600 mr-2"></i>Transferências de compra · Guia</h3>
                <p class="text-xs text-slate-400 mt-1">Escolha os NCMs que participam da guia. Só eles entram no cálculo dos itens ST 010 faturados; os demais são ignorados.</p>
            </div>
            <button id="btnNovaRegra" class="px-4 py-2.5 rounded-xl bg-sky-700 text-white text-sm font-bold hover:bg-sky-800">
                <i class="fa-solid fa-plus mr-1"></i>Adicionar NCMs
            </button>
        </div>
        <div id="estadoGuia" class="text-sm text-slate-400 py-3">Carregando regras...</div>
        <div id="listaGuias" class="space-y-2"></div>
        <div class="rounded-2xl border border-sky-100 bg-sky-50 p-3 mt-4 text-xs text-sky-900">
            <b>Cálculo por NCM (igual à planilha):</b> Soma Total dos Itens · Crédito ICMS Origem = Soma × crédito (12%) · Base de Cálculo ICMS-ST = Soma × (1 + MVA) · ICMS-ST = Base × alíquota PR (19,5%) · <b>Valor Guia = ICMS-ST − Crédito ICMS Origem</b>.
            Os percentuais e as operações podem ser alterados ao adicionar os NCMs; para mudar um valor, adicione uma nova vigência.
        </div>
    </section>

    <section class="bg-white rounded-3xl p-4 sm:p-6 shadow-sm border border-slate-100">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <h3 class="text-base font-extrabold text-slate-800"><i class="fa-solid fa-wallet text-violet-600 mr-2"></i>Verbas Flex · Flex Mínimo</h3>
                <p class="text-xs text-slate-400 mt-1">Gestores, filial, valores, motivo e notificações da rotina automática.</p>
            </div>
            <button id="btnNovoFlex" class="px-4 py-2.5 rounded-xl bg-violet-700 text-white text-sm font-bold hover:bg-violet-800">
                <i class="fa-solid fa-plus mr-1"></i>Novo gestor
            </button>
        </div>
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-3 mb-4 text-xs text-amber-900">
            <b>Importante:</b> antes de ativar um gestor aqui, desligue no ERP a rotina equivalente para evitar empréstimos duplicados.
            A execução também depende do cron do Flex Mínimo estar instalado e configurado.
        </div>
        <div id="estadoFlex" class="text-sm text-slate-400 py-3">Carregando configurações...</div>
        <div id="listaFlex" class="space-y-4"></div>
    </section>
</div>

<script>
const configApi = '/admin/config-modulos';
const configEsc = (v) => String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const configDate = (v) => {
    const m = String(v || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]}` : '—';
};
const configState = { regras: [], ncms: [], impostos: [], flex: [] };

async function configRequest(path, method = 'GET', body = null) {
    const data = await apiFetch(`${configApi}/${path}`, method, body);
    if (!data || data.success !== true) throw new Error(data?.error || 'Não foi possível concluir a operação.');
    return data;
}

function renderGuia() {
    const host = document.getElementById('listaGuias');
    document.getElementById('estadoGuia').textContent = configState.regras.length ? '' : 'Nenhuma regra cadastrada.';
    host.innerHTML = configState.regras.map(g => `
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-100 p-3 ${g.ativo ? '' : 'opacity-60'}">
            <div class="w-10 h-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center"><i class="fa-solid fa-percent"></i></div>
            <div class="flex-1 min-w-[180px]">
                <div class="font-bold text-sm text-slate-800">NCM ${configEsc(g.ncm)}</div>
                <div class="text-xs text-slate-500">${configEsc(g.descricao || 'Sem descrição')}</div>
                <div class="text-xs text-slate-400">MVA ${Number(g.mva_percentual).toLocaleString('pt-BR')}% · PR ${Number(g.aliquota_interna_percentual).toLocaleString('pt-BR')}% · Crédito ${Number(g.credito_interestadual_percentual).toLocaleString('pt-BR')}%</div>
                <div class="text-xs text-slate-400">Base MVA: ${configEsc(g.operador_mva)} · Guia: ${configEsc(g.operador_guia)} · Vigência ${configDate(g.vigencia_inicio)} · ${g.ativo ? 'Ativa' : 'Desativada'}</div>
                <div class="text-[10px] text-slate-400">Alterada por ${configEsc(g.atualizado_por || '—')}</div>
            </div>
            ${g.ativo ? `<button data-disable-guide="${Number(g.id)}" class="rounded-xl px-3 py-2 text-xs font-bold text-rose-600 bg-rose-50 hover:bg-rose-100">Remover</button>` : ''}
        </div>`).join('');
}

function campoFlex(rotulo, nome, valor, tipo = 'text', extras = '') {
    return `<label class="config-label">${rotulo}<input class="config-field" name="${nome}" type="${tipo}" value="${configEsc(valor)}" ${extras}></label>`;
}

function renderFlex() {
    const host = document.getElementById('listaFlex');
    document.getElementById('estadoFlex').textContent = configState.flex.length ? '' : 'Nenhum gestor configurado.';
    host.innerHTML = configState.flex.map((c, index) => `
        <form class="rounded-2xl border border-slate-200 p-4" data-flex-form="${configEsc(c.id || 'novo-' + index)}" data-active="${c.ativo ? '1' : '0'}">
            <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                <div class="font-bold text-sm text-slate-800">${c.id ? 'Gestor ' + configEsc(c.gestor_id) + ' · ' + configEsc(c.nome) : 'Novo gestor Flex'}</div>
                <label class="flex items-center gap-2 text-xs font-bold ${c.ativo ? 'text-emerald-700' : 'text-slate-500'}">
                    <input name="ativo" type="checkbox" class="w-4 h-4 accent-emerald-600" ${c.ativo ? 'checked' : ''}> Rotina ativa
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
                ${campoFlex('E-mails (separados por vírgula)', 'emails', c.emails || '', 'text', 'placeholder="nome@empresa.com"')}
                <label class="config-label sm:col-span-2 lg:col-span-4">Motivo do empréstimo<textarea class="config-field" name="motivo" rows="2" maxlength="1000" required>${configEsc(c.motivo)}</textarea></label>
            </div>
            ${c.ultima_verificacao ? `<div class="mt-3 text-xs text-slate-400">Última verificação: ${configEsc(c.ultima_verificacao)} · Saldo: ${c.ultimo_saldo == null ? '—' : 'R$ ' + Number(c.ultimo_saldo).toLocaleString('pt-BR', {minimumFractionDigits:2})} · Falhas seguidas: ${Number(c.falhas_seguidas || 0)}</div>` : ''}
            ${c.ultimo_erro ? `<div class="mt-2 text-xs text-rose-600">Último erro: ${configEsc(c.ultimo_erro)}</div>` : ''}
            <div class="flex justify-end mt-3"><button type="submit" class="px-4 py-2.5 rounded-xl bg-slate-800 text-white text-xs font-bold hover:bg-slate-900"><i class="fa-solid fa-floppy-disk mr-1"></i>Salvar configuração</button></div>
        </form>`).join('');
}

async function carregarConfiguracoes() {
    document.getElementById('estadoGuia').textContent = 'Carregando regras...';
    document.getElementById('estadoFlex').textContent = 'Carregando configurações...';
    const results = await Promise.allSettled([
        configRequest('guia'),
        configRequest('guia/ncms'),
        configRequest('flex-minimo'),
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
    } else {
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
                <label class="block text-xs font-bold">                MVA do Paraná (%)<input id="guiaMva" type="number" min="0" max="1000" step="0.0001" class="swal2-input" style="${campo}">
                                    ${ajuda('Margem de valor agregado aplicada sobre a Soma Total dos Itens para formar a Base de Cálculo ICMS-ST. <span id="guiaMvaErp"></span>')}</label>
                                <label class="block text-xs font-bold">Base de Cálculo ICMS-ST · como aplicar a MVA<select id="guiaOperadorMva" class="swal2-input" style="${campo}"><option value="*">Multiplicar: Soma × (1 + MVA) — padrão</option><option value="+">Somar: Soma + (Soma × MVA)</option><option value="-">Subtrair: Soma − (Soma × MVA)</option><option value="/">Dividir: Soma ÷ (1 + MVA)</option></select>
                                    ${ajuda('Forma de calcular a Base de Cálculo ICMS-ST a partir da Soma Total dos Itens. Obs.: na nota fiscal esse valor já vem calculado.')}</label>
                                <label class="block text-xs font-bold">ICMS-ST · Alíquota interna do PR (%)<input id="guiaInterna" type="number" min="0" max="100" step="0.0001" class="swal2-input" style="${campo}">
                                    ${ajuda('Percentual aplicado sobre a Base de Cálculo ICMS-ST para achar o ICMS-ST.')}</label>
                                <label class="block text-xs font-bold">Crédito ICMS Origem (%)<input id="guiaCredito" type="number" min="0" max="100" step="0.0001" class="swal2-input" style="${campo}">
                                    ${ajuda('Crédito do ICMS destacado na nota (SC → PR = 12%), calculado sobre a Soma Total dos Itens.')}</label>
                                <label class="block text-xs font-bold sm:col-span-2">Valor Guia · operação final
                                    <select id="guiaOperadorFinal" class="swal2-input" style="${campo}"><option value="-">Subtrair: ICMS-ST − Crédito ICMS Origem — padrão</option><option value="+">Somar: ICMS-ST + Crédito ICMS Origem</option><option value="*">Multiplicar: ICMS-ST × Crédito ICMS Origem</option><option value="/">Dividir: ICMS-ST ÷ Crédito ICMS Origem</option></select>
                                    ${ajuda('Operação final entre o ICMS-ST e o Crédito ICMS Origem, que resulta no Valor Guia.')}</label>
            </div>
            <label class="block text-xs font-bold mt-1">Vigência a partir de<input id="guiaVigencia" type="date" class="swal2-input" style="${campo}">
                ${ajuda('Data inicial da regra. Para mudar percentuais depois, cadastre uma nova vigência; as solicitações usam a regra vigente na data delas.')}</label>

            ${secao(3, 'Simulação (confira antes de salvar)')}
            <div class="grid grid-cols-1 sm:grid-cols-[150px_1fr] gap-3 items-start">
                <label class="block text-xs font-bold">                Soma Total dos Itens (exemplo, R$)<input id="guiaExemplo" type="number" min="0" step="0.01" value="10000" class="swal2-input" style="${campo}">
                                    ${ajuda('No relatório é a soma dos itens ST 010 do NCM na nota faturada, calculada automaticamente.')}</label>
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
                el('guiaSimulacao').innerHTML =                 `<div>Crédito ICMS Origem = ${brl(e)} × ${c.credito}% = <b>${brl(r.f)}</b></div>
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
    const confirm = await confirmar('Remover este NCM da guia?', 'O NCM deixa de participar do cálculo, inclusive nas solicitações já consultadas. Para uma nova taxa, prefira cadastrar outra vigência.', 'Remover');
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

window.addEventListener('DOMContentLoaded', carregarConfiguracoes);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
