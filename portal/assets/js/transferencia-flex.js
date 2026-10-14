// Modulo Transferencia de Flex: saldo vem da API Mercos e a regra de permissao e validada no servidor

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    let url = apiRoot + '/' + acao.replace(/^\/+/, '').replace(/^v1\//, '');
    const options = { method: metodo, headers: {}, credentials: 'include' };
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) options.headers.Authorization = 'Bearer ' + token;

    if (metodo === 'GET' && body) {
        url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();
    } else if (body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    const text = await response.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { error: text || 'Resposta inválida do servidor' }; }
    if (!response.ok) throw new Error(data.error || `Erro ${response.status}`);
    return data;
};

const $ = (id) => document.getElementById(id);
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const brl = (n) => Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
const dataHora = (d) => {
    const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : esc(d);
};

const swalBase = { confirmButtonColor: '#7c3aed', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = { reps: [], saldoDe: null, saldoPara: null, movs: [], enviando: false };

const repsDaFilial = () => estado.reps.filter((r) => r.filial === Number($('selFilial').value));
const opcoesReps = (excluir) => '<option value="">Selecione...</option>' + repsDaFilial()
    .filter((r) => r.id !== excluir)
    .map((r) => `<option value="${r.id}">${esc(r.nome)}${r.gestor ? ' — ' + esc(r.gestor) : ''}</option>`).join('');

function valorNumerico() {
    const digitos = $('valor').value.replace(/\D/g, '');
    return digitos ? Number(digitos) / 100 : 0;
}

function mostrarSaldo(el, info) {
    el.classList.remove('hidden');
    el.classList.toggle('off', !info.ativo);
    el.innerHTML = `<span><i class="fa-solid fa-coins mr-1"></i> Saldo</span><span>${brl(info.saldo)}${info.ativo ? '' : ' · Inativo'}</span>`;
}

async function carregarSaldo(id, destino) {
    const filial = Number($('selFilial').value);
    return apiFetch('flex/saldo', 'GET', { filial, id });
}

function validar() {
    const ok = estado.saldoDe && estado.saldoDe.ativo && estado.saldoPara && estado.saldoPara.ativo
        && valorNumerico() > 0 && $('motivo').value.trim().length >= 4;
    $('btnTransferir').disabled = !ok || estado.enviando;
}

function resetar(nivel) {
    if (nivel <= 1) {
        estado.saldoDe = null;
        $('saldoDe').classList.add('hidden');
        $('blocoPara').classList.add('hidden');
    }
    estado.saldoPara = null;
    $('saldoPara').classList.add('hidden');
    $('valor').value = '';
    $('motivo').value = '';
    $('blocoValor').classList.add('hidden');
    $('barraEnviar').classList.add('hidden');
    validar();
}

async function aoEscolherDe() {
    resetar(1);
    const id = Number($('selDe').value);
    if (!id) return;
    try {
        const s = await carregarSaldo(id);
        if (!s.ativo) {
            mostrarSaldo($('saldoDe'), s);
            aviso('info', 'Representante inativo', `${s.nome} está excluído e não pode transferir.`);
            return;
        }
        estado.saldoDe = s;
        mostrarSaldo($('saldoDe'), s);
        $('selPara').innerHTML = opcoesReps(id);
        $('blocoPara').classList.remove('hidden');
    } catch (e) {
        aviso('error', 'Erro ao buscar saldo', e.message);
    }
}

async function aoEscolherPara() {
    estado.saldoPara = null;
    $('saldoPara').classList.add('hidden');
    $('blocoValor').classList.add('hidden');
    $('barraEnviar').classList.add('hidden');
    const id = Number($('selPara').value);
    if (!id) return validar();
    try {
        const s = await carregarSaldo(id);
        mostrarSaldo($('saldoPara'), s);
        if (!s.ativo) {
            aviso('info', 'Representante inativo', `${s.nome} está excluído e não pode receber.`);
            return validar();
        }
        estado.saldoPara = s;
        $('blocoValor').classList.remove('hidden');
        $('barraEnviar').classList.remove('hidden');
        validar();
    } catch (e) {
        aviso('error', 'Erro ao buscar saldo', e.message);
    }
}

async function transferir() {
    const valor = valorNumerico();
    const motivo = $('motivo').value.trim();
    if (valor > estado.saldoDe.saldo) {
        return aviso('error', 'Saldo insuficiente', `${estado.saldoDe.nome} possui apenas ${brl(estado.saldoDe.saldo)}.`);
    }
    const conf = await Swal.fire({
        ...swalBase, icon: 'question', title: 'Confirmar transferência?', showCancelButton: true,
        confirmButtonText: 'Transferir', cancelButtonText: 'Cancelar',
        html: `<div class="text-left text-sm space-y-1">
            <div><b>DE:</b> ${esc(estado.saldoDe.nome)} <span class="text-slate-500">(${brl(estado.saldoDe.saldo)} → ${brl(estado.saldoDe.saldo - valor)})</span></div>
            <div><b>PARA:</b> ${esc(estado.saldoPara.nome)} <span class="text-slate-500">(${brl(estado.saldoPara.saldo)} → ${brl(estado.saldoPara.saldo + valor)})</span></div>
            <div><b>Valor:</b> ${brl(valor)}</div>
            <div><b>Motivo:</b> ${esc(motivo)}</div></div>`,
    });
    if (!conf.isConfirmed) return;

    estado.enviando = true;
    validar();
    Swal.fire({ ...swalBase, title: 'Transferindo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const r = await apiFetch('flex/transferir', 'POST', {
            filial: Number($('selFilial').value), de: estado.saldoDe.id, para: estado.saldoPara.id, valor, motivo,
        });
        await Swal.fire({ ...swalBase, icon: 'success', title: 'Transferência realizada', text: r.message + (r.aviso ? ' ' + r.aviso : ''), confirmButtonText: 'OK' });
        $('selDe').innerHTML = opcoesReps();
        resetar(1);
        carregarMovs();
    } catch (e) {
        aviso('error', 'Não foi possível transferir', e.message);
        carregarMovs();
    } finally {
        estado.enviando = false;
        validar();
    }
}

function renderMovs() {
    const box = $('listaMov');
    if (!estado.movs.length) {
        box.innerHTML = '<div class="text-center text-slate-400 py-6 text-sm">Nenhuma movimentação no período.</div>';
        return;
    }
    box.innerHTML = estado.movs.map((m) => `
        <div class="fx-mov ${m.sucesso === false || m.sucesso === 'f' ? 'falha' : ''}">
            <div class="flex justify-between items-start gap-2">
                <div class="text-sm font-extrabold text-slate-700">${esc(m.nome_de)} <i class="fa-solid fa-arrow-right text-violet-500 mx-1"></i> ${esc(m.nome_para)}</div>
                <div class="text-sm font-extrabold text-violet-700 whitespace-nowrap">${brl(m.valor)}</div>
            </div>
            <div class="text-xs text-slate-500 mt-1">${dataHora(m.data_transferencia)} · ${esc(m.usuario)}</div>
            <div class="text-xs text-slate-600 mt-1">${esc(m.motivo)}</div>
            ${m.sucesso === false || m.sucesso === 'f' ? `<div class="text-xs text-red-600 font-bold mt-1">${esc(m.mensagem)}</div>`
                : `<div class="text-xs text-slate-400 mt-1">Saldos: ${brl(m.valor_novo_saldo_de)} / ${brl(m.valor_novo_saldo_para)}</div>`}
        </div>`).join('');
}

const paramsFiltro = () => {
    const p = { filial: Number($('selFilial').value) };
    if ($('fDe').value) p.de = $('fDe').value;
    if ($('fPara').value) p.para = $('fPara').value;
    if ($('dtIni').value) p.inicio = $('dtIni').value;
    if ($('dtFim').value) p.fim = $('dtFim').value;
    return p;
};
const temFiltro = () => ['fDe', 'fPara', 'dtIni', 'dtFim'].some((id) => $(id).value);

async function carregarMovs() {
    const filtrado = temFiltro();
    $('modoMov').textContent = filtrado ? 'Filtrado' : 'Últimas 10';
    try {
        const r = await apiFetch('flex/movimentacoes', 'GET', { ...paramsFiltro(), limite: filtrado ? 500 : 10 });
        estado.movs = r.movimentacoes;
        renderMovs();
    } catch (e) {
        $('listaMov').innerHTML = `<div class="text-center text-red-500 py-4 text-sm">${esc(e.message)}</div>`;
    }
}

function limparFiltros() {
    ['fDe', 'fPara', 'dtIni', 'dtFim'].forEach((id) => { $(id).value = ''; });
    carregarMovs();
}

function opcoesFiltro() {
    const base = '<option value="">Todos</option>' + repsDaFilial()
        .map((r) => `<option value="${r.id}">${esc(r.nome)}</option>`).join('');
    $('fDe').innerHTML = base;
    $('fPara').innerHTML = base;
}

async function baixarExcel() {
    let lista;
    try {
        // o download traz tudo do filtro (ou os últimos 30 dias no modo geral), não só as 10 exibidas
        const p = { ...paramsFiltro(), limite: 2000 };
        if (!temFiltro()) {
            const d = new Date(Date.now() - 30 * 86400000);
            p.inicio = new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
        }
        lista = (await apiFetch('flex/movimentacoes', 'GET', p)).movimentacoes;
    } catch (e) {
        return aviso('error', 'Erro ao exportar', e.message);
    }
    if (!lista.length) return aviso('info', 'Sem dados', 'Não há movimentações para exportar.');
    const cab = ['Data', 'De', 'Para', 'Valor', 'Saldo anterior DE', 'Novo saldo DE', 'Saldo anterior PARA', 'Novo saldo PARA', 'Motivo', 'Usuário', 'Situação'];
    const cel = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const num = (v) => Number(v ?? 0).toFixed(2).replace('.', ',');
    const linhas = lista.map((m) => [
        dataHora(m.data_transferencia), m.nome_de, m.nome_para, num(m.valor), num(m.valor_saldo_anterior_de), num(m.valor_novo_saldo_de),
        num(m.valor_saldo_anterior_para), num(m.valor_novo_saldo_para), m.motivo, m.usuario,
        (m.sucesso === false || m.sucesso === 'f') ? 'Falhou' : 'OK',
    ].map(cel).join(';'));
    const blob = new Blob(['\ufeff' + [cab.map(cel).join(';'), ...linhas].join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `transferencias_flex_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

window.addEventListener('DOMContentLoaded', async () => {
    try {
        const ctx = await apiFetch('flex/contexto');
        estado.reps = ctx.representantes;
        $('selFilial').innerHTML = ctx.filiais.map((f) => `<option value="${f.idfilial}">${esc(f.idfilial)} - ${esc(f.razao)}</option>`).join('');
        if (!ctx.filiais.length) aviso('warning', 'Sem filial', 'Seu usuário não possui filial liberada para este módulo.');
        $('selDe').innerHTML = opcoesReps();
        opcoesFiltro();
    } catch (e) {
        aviso('error', 'Erro ao carregar', e.message);
        return;
    }

    $('selFilial').addEventListener('change', () => { $('selDe').innerHTML = opcoesReps(); opcoesFiltro(); limparFiltros(); resetar(1); });
    $('selDe').addEventListener('change', aoEscolherDe);
    $('selPara').addEventListener('change', aoEscolherPara);
    $('valor').addEventListener('input', () => { $('valor').value = valorNumerico() ? brl(valorNumerico()) : ''; validar(); });
    $('motivo').addEventListener('input', validar);
    $('btnTransferir').addEventListener('click', transferir);
    $('btnFiltrar').addEventListener('click', carregarMovs);
    $('btnLimpar').addEventListener('click', limparFiltros);
    $('btnExcel').addEventListener('click', baixarExcel);

    carregarMovs();
});
