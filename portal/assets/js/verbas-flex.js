// Modulo Verbas Flex: filtros e permissoes (dash_filiais / dash_gestores) sao aplicados no servidor

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    let url = apiRoot + '/' + acao.replace(/^\/+/, '').replace(/^v1\//, '');
    const options = { method: metodo, headers: {}, credentials: 'include' };
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) options.headers.Authorization = 'Bearer ' + token;
    if (body) url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();

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
const dataBR = (d) => { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(d); };

const swalBase = { confirmButtonColor: '#7c3aed', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = { aba: 'painel', debitos: [], creditos: [], timer: null };
const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

function filtroBase() {
    return { filial: $('selFilial').value, inicio: $('dtIni').value, fim: $('dtFim').value };
}

function atalho(tipo) {
    const h = new Date();
    let a = new Date(h.getFullYear(), h.getMonth(), 1);
    let b = h;
    if (tipo === 'ant') { a = new Date(h.getFullYear(), h.getMonth() - 1, 1); b = new Date(h.getFullYear(), h.getMonth(), 0); }
    if (tipo === '30') a = new Date(h.getTime() - 29 * 86400000);
    if (tipo === 'ano') a = new Date(h.getFullYear(), 0, 1);
    $('dtIni').value = iso(a);
    $('dtFim').value = iso(b);
    document.querySelectorAll('#atalhos .vf-chip').forEach((c) => c.classList.toggle('on', c.dataset.p === tipo));
}

function variacao(atual, anterior) {
    if (!anterior) return '';
    const pct = ((Math.abs(atual) - Math.abs(anterior)) / Math.abs(anterior)) * 100;
    const seta = pct >= 0 ? '▲' : '▼';
    return `<i class="t">${seta} ${Math.abs(pct).toFixed(0)}% vs. período anterior</i>`;
}

async function carregarPainel() {
    try {
        const r = await apiFetch('verbas-flex/resumo', 'GET', filtroBase());
        const deb = Math.abs(r.atual.debitos);
        const cred = r.atual.creditos;
        $('kpis').innerHTML = `
            <div class="vf-kpi" style="background:linear-gradient(135deg,#dc2626,#f87171)"><small>Total retirado</small><b>${brl(deb)}</b>${variacao(r.atual.debitos, r.anterior.debitos)}</div>
            <div class="vf-kpi" style="background:linear-gradient(135deg,#059669,#34d399)"><small>Empréstimos ao gestor</small><b>${brl(cred)}</b>${variacao(r.atual.creditos, r.anterior.creditos)}</div>
            <div class="vf-kpi col-span-2" style="background:linear-gradient(135deg,#6d28d9,#a78bfa)"><small>Resultado (empréstimo − retirado)</small><b>${brl(cred - deb)}</b></div>`;

        const maxS = Math.max(1, ...r.serie.map((s) => Math.max(Math.abs(s.debitos), s.creditos)));
        $('serie').innerHTML = r.serie.map((s) => `
            <div>
                <div class="flex justify-between text-xs font-bold text-slate-500 mb-1"><span>${esc(s.mes)}</span><span>${brl(Math.abs(s.debitos))} / ${brl(s.creditos)}</span></div>
                <div class="vf-bar mb-1"><span style="width:${(Math.abs(s.debitos) / maxS) * 100}%;background:#f87171"></span></div>
                <div class="vf-bar"><span style="width:${(s.creditos / maxS) * 100}%;background:#34d399"></span></div>
            </div>`).join('') + '<div class="text-xs text-slate-400 mt-2"><span class="text-red-400">●</span> retirado &nbsp; <span class="text-emerald-400">●</span> empréstimo</div>';

        const maxR = Math.max(1, ...r.ranking.map((x) => Math.abs(Number(x.total))));
        $('ranking').innerHTML = r.ranking.length ? r.ranking.map((x, i) => `
            <div>
                <div class="flex justify-between text-sm font-bold text-slate-700 mb-1"><span>${i + 1}. ${esc(x.nome)}</span><span>${brl(Math.abs(Number(x.total)))}</span></div>
                <div class="vf-bar"><span style="width:${(Math.abs(Number(x.total)) / maxR) * 100}%;background:linear-gradient(90deg,#7c3aed,#a78bfa)"></span></div>
                <div class="text-xs text-slate-400 mt-1">${x.qtd} retirada(s)</div>
            </div>`).join('') : '<div class="text-sm text-slate-400">Sem retiradas no período.</div>';
    } catch (e) {
        $('kpis').innerHTML = `<div class="col-span-2 text-red-500 text-sm">${esc(e.message)}</div>`;
    }
}

async function carregarDebitos() {
    const p = { ...filtroBase(), limite: 500 };
    if ($('fRep').value) p.representante = $('fRep').value;
    if ($('fBuscaD').value.trim()) p.q = $('fBuscaD').value.trim();
    try {
        const r = await apiFetch('verbas-flex/debitos', 'GET', p);
        estado.debitos = r.registros;
        $('totD').textContent = brl(Math.abs(r.total));
        $('qtdD').textContent = `${r.quantidade} lançamento(s)` + (r.quantidade > r.registros.length ? ` — exibindo ${r.registros.length}` : '');

        if (!$('fRep').dataset.carregado || $('fRep').dataset.chave !== `${p.filial}|${p.inicio}|${p.fim}`) {
            const atual = $('fRep').value;
            $('fRep').innerHTML = '<option value="">Todos</option>' + r.representantes.map((x) => `<option value="${x.id}">${esc(x.nome)}</option>`).join('');
            $('fRep').value = atual;
            $('fRep').dataset.carregado = '1';
            $('fRep').dataset.chave = `${p.filial}|${p.inicio}|${p.fim}`;
        }

        $('listaD').innerHTML = r.registros.length ? r.registros.map((d, i) => `
            <div class="vf-row" data-d="${i}">
                <div class="vf-ico bg-red-50 text-red-500"><i class="fa-solid fa-arrow-trend-down"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between gap-2"><b class="text-sm text-slate-700 truncate">${esc(d.cliente)}</b><b class="text-sm text-red-600 whitespace-nowrap">${brl(Math.abs(d.valor_tirado))}</b></div>
                    <div class="text-xs text-slate-500 truncate">${esc(d.representante)} · Nota ${esc(d.nota)}</div>
                    <div class="text-xs text-slate-400">${dataBR(d.data)}</div>
                </div>
            </div>`).join('') : '<div class="text-center text-slate-400 py-6 text-sm">Nenhum débito no período.</div>';
    } catch (e) {
        $('listaD').innerHTML = `<div class="text-center text-red-500 py-4 text-sm">${esc(e.message)}</div>`;
    }
}

async function carregarCreditos() {
    const p = { ...filtroBase(), limite: 500 };
    if ($('fBuscaC').value.trim()) p.q = $('fBuscaC').value.trim();
    if ($('fZerados').checked) p.zerados = 1;
    try {
        const r = await apiFetch('verbas-flex/creditos', 'GET', p);
        estado.creditos = r.registros;
        $('totC').textContent = brl(r.total);
        $('qtdC').textContent = `${r.quantidade} lançamento(s)` + (r.quantidade > r.registros.length ? ` — exibindo ${r.registros.length}` : '');
        $('listaC').innerHTML = r.registros.length ? r.registros.map((c, i) => `
            <div class="vf-row" data-c="${i}">
                <div class="vf-ico bg-emerald-50 text-emerald-600"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between gap-2"><b class="text-sm text-slate-700">${dataBR(c.data)}</b><b class="text-sm text-emerald-600 whitespace-nowrap">${brl(c.valor_emprestimo)}</b></div>
                    <div class="text-xs text-slate-500 line-clamp-2">${esc(c.motivo)}</div>
                </div>
            </div>`).join('') : '<div class="text-center text-slate-400 py-6 text-sm">Nenhum crédito no período.</div>';
    } catch (e) {
        $('listaC').innerHTML = `<div class="text-center text-red-500 py-4 text-sm">${esc(e.message)}</div>`;
    }
}

function carregarAba() {
    if (estado.aba === 'painel') carregarPainel();
    else if (estado.aba === 'debitos') carregarDebitos();
    else carregarCreditos();
}

function detalheDebito(d) {
    Swal.fire({
        ...swalBase, title: 'Débito de verba', confirmButtonText: 'Fechar',
        html: `<div class="text-left text-sm space-y-1">
            <div><b>Data:</b> ${dataBR(d.data)}</div><div><b>Nota:</b> ${esc(d.nota)}</div>
            <div><b>Cliente:</b> ${esc(d.cdcliente)} - ${esc(d.cliente)}</div>
            <div><b>Representante:</b> ${esc(d.cdrepre)} - ${esc(d.representante)}</div>
            <div><b>Gestor:</b> ${esc(d.gestor || '-')}</div>
            <div><b>Valor da nota:</b> ${brl(d.valor_nota)}</div>
            <div><b>Valor retirado:</b> <span class="text-red-600 font-bold">${brl(Math.abs(d.valor_tirado))}</span></div>
            <div><b>Motivo:</b> ${esc(d.motivo)}</div></div>`,
    });
}

function detalheCredito(c) {
    Swal.fire({
        ...swalBase, title: 'Empréstimo ao gestor', confirmButtonText: 'Fechar',
        html: `<div class="text-left text-sm space-y-1"><div><b>Data:</b> ${dataBR(c.data)}</div>
            <div><b>Valor:</b> <span class="text-emerald-600 font-bold">${brl(c.valor_emprestimo)}</span></div>
            <div><b>Motivo:</b> ${esc(c.motivo)}</div></div>`,
    });
}

function csv(nome, cab, linhas) {
    if (!linhas.length) return aviso('info', 'Sem dados', 'Não há registros para exportar.');
    const cel = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const blob = new Blob(['\ufeff' + [cab, ...linhas].map((l) => l.map(cel).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `${nome}_${$('dtIni').value}_${$('dtFim').value}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

const dec = (v) => Number(v ?? 0).toFixed(2).replace('.', ',');

async function exportarDebitos() {
    try {
        const p = { ...filtroBase(), limite: 2000 };
        if ($('fRep').value) p.representante = $('fRep').value;
        if ($('fBuscaD').value.trim()) p.q = $('fBuscaD').value.trim();
        const r = await apiFetch('verbas-flex/debitos', 'GET', p);
        csv('debitos_flex', ['Data', 'Nota', 'Cód. cliente', 'Cliente', 'Cód. repre', 'Representante', 'Gestor', 'Valor nota', 'Valor retirado', 'Motivo'],
            r.registros.map((d) => [dataBR(d.data), d.nota, d.cdcliente, d.cliente, d.cdrepre, d.representante, d.gestor, dec(d.valor_nota), dec(d.valor_tirado), d.motivo]));
    } catch (e) { aviso('error', 'Erro ao exportar', e.message); }
}

async function exportarCreditos() {
    try {
        const p = { ...filtroBase(), limite: 2000 };
        if ($('fBuscaC').value.trim()) p.q = $('fBuscaC').value.trim();
        if ($('fZerados').checked) p.zerados = 1;
        const r = await apiFetch('verbas-flex/creditos', 'GET', p);
        csv('creditos_flex', ['Data', 'Valor empréstimo', 'Motivo'], r.registros.map((c) => [dataBR(c.data), dec(c.valor_emprestimo), c.motivo]));
    } catch (e) { aviso('error', 'Erro ao exportar', e.message); }
}

window.addEventListener('DOMContentLoaded', async () => {
    atalho('mes');
    try {
        const ctx = await apiFetch('verbas-flex/contexto');
        $('selFilial').innerHTML = ctx.filiais.map((f) => `<option value="${f.idfilial}">${esc(f.idfilial)} - ${esc(f.razao)}</option>`).join('');
        if (!ctx.filiais.length) return aviso('warning', 'Sem filial', 'Seu usuário não possui filial liberada. Peça ao administrador.');
    } catch (e) {
        return aviso('error', 'Erro ao carregar', e.message);
    }

    document.querySelectorAll('#atalhos .vf-chip').forEach((b) => b.addEventListener('click', () => { atalho(b.dataset.p); carregarAba(); }));
    document.querySelectorAll('#abas [data-aba]').forEach((b) => b.addEventListener('click', () => {
        estado.aba = b.dataset.aba;
        document.querySelectorAll('#abas [data-aba]').forEach((x) => x.classList.toggle('on', x === b));
        ['painel', 'debitos', 'creditos'].forEach((a) => $('aba-' + a).classList.toggle('hidden', a !== estado.aba));
        carregarAba();
    }));
    $('btnAplicar').addEventListener('click', () => {
        document.querySelectorAll('#atalhos .vf-chip').forEach((c) => c.classList.remove('on'));
        carregarAba();
    });
    $('selFilial').addEventListener('change', () => { $('fRep').value = ''; carregarAba(); });
    $('fRep').addEventListener('change', carregarDebitos);
    $('fZerados').addEventListener('change', carregarCreditos);
    const adiar = (fn) => () => { clearTimeout(estado.timer); estado.timer = setTimeout(fn, 400); };
    $('fBuscaD').addEventListener('input', adiar(carregarDebitos));
    $('fBuscaC').addEventListener('input', adiar(carregarCreditos));
    $('xlsD').addEventListener('click', exportarDebitos);
    $('xlsC').addEventListener('click', exportarCreditos);
    $('listaD').addEventListener('click', (ev) => { const r = ev.target.closest('[data-d]'); if (r) detalheDebito(estado.debitos[Number(r.dataset.d)]); });
    $('listaC').addEventListener('click', (ev) => { const r = ev.target.closest('[data-c]'); if (r) detalheCredito(estado.creditos[Number(r.dataset.c)]); });

    carregarPainel();
});
