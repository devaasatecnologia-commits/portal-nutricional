// Transferencias de compra Matriz<->Filial (consulta). O tipo vem de data-tipo na pagina.

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    let url = apiRoot + '/' + acao.replace(/^\/+/, '').replace(/^v1\//, '');
    const options = { method: metodo, headers: {}, credentials: 'include' };
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) options.headers.Authorization = 'Bearer ' + token;
    if (body && metodo === 'GET') url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();
    else if (body) { options.headers['Content-Type'] = 'application/json'; options.body = JSON.stringify(body); }

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
const num = (n, d = 0) => Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: d, maximumFractionDigits: d });
const dataBR = (d) => { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(d); };
const swalBase = { confirmButtonColor: '#0369a1', customClass: { popup: 'rounded-3xl' } };

const tipo = document.querySelector('[data-tipo]')?.dataset.tipo || 'matriz-filial';
const ehMF = tipo === 'matriz-filial';
const base = `transf-compras/${tipo}`;
const estado = { registros: [], timer: null };

const filtros = () => {
    const p = {};
    if ($('dtIni').value) p.inicio = $('dtIni').value;
    if ($('dtFim').value) p.fim = $('dtFim').value;
    if ($('busca').value.trim()) p.q = $('busca').value.trim();
    return p;
};
const filtrado = () => Object.keys(filtros()).length > 0;

function linha(r, i) {
    if (ehMF) {
        return `<div class="tc-row" data-i="${i}"><div class="tc-ico"><i class="fa-solid ${r.remessa ? 'fa-box' : 'fa-truck'}"></i></div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-slate-800 text-sm truncate">${esc(r.nomeembarque || 'Embarque ' + r.embarque)}</div>
                <div class="text-xs text-slate-400">Solicitação ${esc(r.idcompra)} · ${r.remessa ? 'Remessa' : 'Embarque'} ${esc(r.embarque)} · ${dataBR(r.data)} · ${esc(r.qtpedido ?? 0)} pedido(s)</div>
            </div>
            <div class="text-right"><div class="font-extrabold text-sky-700 text-sm">${brl(r.valor_guia)}</div><div class="text-[10px] text-slate-400">guia</div></div></div>`;
    }
    return `<div class="tc-row" data-i="${i}"><div class="tc-ico"><i class="fa-solid fa-file-invoice"></i></div>
        <div class="flex-1 min-w-0">
            <div class="font-bold text-slate-800 text-sm truncate">${esc(r.fornecedor || 'Fornecedor não encontrado')}</div>
            <div class="text-xs text-slate-400">Solicitação ${esc(r.idcompra)} · Ordem ${esc(r.ordem)} · ${dataBR(r.data)} · ${num(r.quantidade)} un · ${num(r.peso)} kg</div>
        </div>
        <div class="text-right"><div class="font-extrabold text-teal-700 text-sm">${brl(r.valor)}</div></div></div>`;
}

async function carregar() {
    $('lista').innerHTML = '<div class="text-sm text-slate-400">Carregando…</div>';
    try {
        const r = await apiFetch(base, 'GET', { ...filtros(), limite: filtrado() ? 500 : 10 });
        estado.registros = r.registros;
        $('rotulo').textContent = filtrado() ? 'Filtrado' : 'Últimas 10';
        $('totais').textContent = ehMF
            ? `${r.quantidade} solicitação(ões) · guia ${brl(r.total_guia)}`
            : `${r.quantidade} solicitação(ões) · ${brl(r.total_valor)}`;
        $('lista').innerHTML = r.registros.length ? r.registros.map(linha).join('') : '<div class="text-sm text-slate-400">Nenhum registro.</div>';
    } catch (e) {
        $('lista').innerHTML = `<div class="text-red-500 text-sm">${esc(e.message)}</div>`;
    }
}

async function carregarPendentes() {
    try {
        const r = await apiFetch(base + '/pendentes');
        $('pendentes').innerHTML = r.registros.length ? r.registros.map((p) => {
            const chip = p.ja_solicitado ? '<span class="tc-chip bg-emerald-100 text-emerald-700">Solicitado</span>' : '<span class="tc-chip bg-amber-100 text-amber-700">Aguardando</span>';
            const titulo = ehMF ? (p.nome_embarque || 'Embarque ' + p.embarque) : (p.fornecedor || '');
            const sub = ehMF ? `Embarque ${esc(p.embarque)} · ${esc(p.placa || '')} · ${dataBR(p.data)} · ${esc(p.qt_pedido)} pedido(s) · ${num(p.peso)} kg` : `Ordem ${esc(p.ordem)} · filial ${esc(p.idfilial)}`;
            return `<div class="tc-row" style="cursor:default"><div class="flex-1 min-w-0"><div class="font-bold text-sm text-slate-800 truncate">${esc(titulo)}</div><div class="text-xs text-slate-400">${sub}</div></div>${chip}</div>`;
        }).join('') : '<div class="text-sm text-slate-400">Nada aguardando.</div>';
    } catch (e) {
        $('pendentes').innerHTML = `<div class="text-red-500 text-sm">${esc(e.message)}</div>`;
    }
}

function detalhe(r) {
    const html = ehMF
        ? `<div><b>Solicitação:</b> ${esc(r.idcompra)}</div><div><b>${r.remessa ? 'Remessa' : 'Embarque'}:</b> ${esc(r.embarque)} – ${esc(r.nomeembarque)}</div>
           <div><b>Data:</b> ${dataBR(r.data)}</div><div><b>Pedidos:</b> ${esc(r.qtpedido ?? 0)}</div>
           <div><b>Base ST:</b> ${brl(r.valor_st)}</div><div><b>Alíquota:</b> ${r.aliquota == null ? '<span class="text-red-600">sem regra vigente</span>' : num(Number(r.aliquota) * 100, 4) + '%'}</div>
           <div><b>Valor da guia:</b> <span class="font-bold text-sky-700">${brl(r.valor_guia)}</span></div>`
        : `<div><b>Solicitação:</b> ${esc(r.idcompra)}</div><div><b>Ordem de compra:</b> ${esc(r.ordem)}</div><div><b>Fornecedor:</b> ${esc(r.fornecedor)}</div>
           <div><b>Data:</b> ${dataBR(r.data)}</div><div><b>Quantidade:</b> ${num(r.quantidade)}</div><div><b>Peso:</b> ${num(r.peso)} kg</div><div><b>Valor:</b> ${brl(r.valor)}</div>`;
    Swal.fire({ ...swalBase, title: 'Solicitação ' + r.idcompra, html: `<div class="text-left text-sm space-y-1">${html}</div>`, confirmButtonText: 'Fechar' });
}

async function baixar() {
    try {
        const r = await apiFetch(base, 'GET', { ...filtros(), limite: 2000 });
        if (!r.registros.length) return Swal.fire({ ...swalBase, icon: 'info', title: 'Sem dados', text: 'Não há registros para exportar.' });
        const dec = (v) => Number(v ?? 0).toFixed(2).replace('.', ',');
        const cab = ehMF ? ['Solicitação', 'Tipo', 'Embarque/Remessa', 'Descrição', 'Data', 'Pedidos', 'Base ST', 'Alíquota %', 'Valor guia']
            : ['Solicitação', 'Ordem', 'Data', 'Fornecedor', 'Quantidade', 'Peso', 'Valor'];
        const linhas = r.registros.map((x) => ehMF
            ? [x.idcompra, x.remessa ? 'Remessa' : 'Embarque', x.embarque, x.nomeembarque, dataBR(x.data), x.qtpedido, dec(x.valor_st), x.aliquota == null ? '' : dec(Number(x.aliquota) * 100), dec(x.valor_guia)]
            : [x.idcompra, x.ordem, dataBR(x.data), x.fornecedor, x.quantidade, x.peso, dec(x.valor)]);
        const cel = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const blob = new Blob(['\ufeff' + [cab, ...linhas].map((l) => l.map(cel).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `transferencia_${tipo}_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
    } catch (e) { Swal.fire({ ...swalBase, icon: 'error', title: 'Erro ao exportar', text: e.message }); }
}

// ---- Guia (somente Matriz -> Filial) ----
async function carregarGuias() {
    if (!ehMF) return;
    try {
        const r = await apiFetch(base + '/guia-config');
        $('cardGuia').classList.remove('hidden');
        $('btnNovaGuia').classList.toggle('hidden', !r.pode_editar);
        $('guias').innerHTML = r.regras.map((g) => `
            <div class="tc-row" style="cursor:default;${g.ativo ? '' : 'opacity:.5'}">
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-800">${g.tipo === 'NCM' ? 'NCM ' + esc(g.ncm) : 'ST'} · ${num(Number(g.aliquota) * 100, 4)}%</div>
                    <div class="text-xs text-slate-400">desde ${dataBR(g.vigencia_inicio)} · ${esc(g.descricao || '')} ${g.ativo ? '' : '· desativada'}</div>
                </div>
                ${r.pode_editar && g.ativo ? `<button data-des="${g.id}" class="text-xs font-bold text-red-500">Desativar</button>` : ''}
            </div>`).join('') || '<div class="text-sm text-slate-400">Nenhuma regra.</div>';
    } catch { /* sem permissao de ver: mantem oculto */ }
}

async function novaGuia() {
    const { value } = await Swal.fire({
        ...swalBase, title: 'Nova vigência da guia', showCancelButton: true, confirmButtonText: 'Salvar', cancelButtonText: 'Cancelar',
        html: `<div class="text-left space-y-2">
            <label class="block text-xs font-bold">Tipo<select id="gTipo" class="swal2-input" style="margin:.25rem 0"><option value="ST">ST</option><option value="NCM">NCM</option></select></label>
            <label class="block text-xs font-bold">NCM (só para tipo NCM)<input id="gNcm" class="swal2-input" style="margin:.25rem 0" inputmode="numeric"></label>
            <label class="block text-xs font-bold">Alíquota (%)<input id="gAliq" class="swal2-input" style="margin:.25rem 0" inputmode="decimal" placeholder="19,0322"></label>
            <label class="block text-xs font-bold">Vigência a partir de<input id="gVig" type="date" class="swal2-input" style="margin:.25rem 0"></label>
            <label class="block text-xs font-bold">Descrição<input id="gDesc" class="swal2-input" style="margin:.25rem 0" maxlength="120"></label></div>`,
        preConfirm: () => ({
            tipo: $('gTipo').value, ncm: $('gNcm').value, aliquota_percentual: $('gAliq').value,
            vigencia_inicio: $('gVig').value, descricao: $('gDesc').value,
        }),
    });
    if (!value) return;
    try {
        await apiFetch(base + '/guia-config', 'POST', value);
        carregarGuias();
        carregar();
    } catch (e) { Swal.fire({ ...swalBase, icon: 'error', title: 'Não salvou', text: e.message }); }
}

window.addEventListener('DOMContentLoaded', () => {
    $('btnFiltrar').addEventListener('click', carregar);
    $('btnLimpar').addEventListener('click', () => { $('dtIni').value = ''; $('dtFim').value = ''; $('busca').value = ''; carregar(); });
    $('busca').addEventListener('input', () => { clearTimeout(estado.timer); estado.timer = setTimeout(carregar, 400); });
    $('btnXls').addEventListener('click', baixar);
    $('lista').addEventListener('click', (ev) => { const r = ev.target.closest('[data-i]'); if (r) detalhe(estado.registros[Number(r.dataset.i)]); });
    if (ehMF) {
        $('btnNovaGuia').addEventListener('click', novaGuia);
        $('guias').addEventListener('click', async (ev) => {
            const b = ev.target.closest('[data-des]');
            if (!b) return;
            const ok = await Swal.fire({ ...swalBase, icon: 'question', title: 'Desativar esta regra?', showCancelButton: true, confirmButtonText: 'Desativar', cancelButtonText: 'Cancelar' });
            if (!ok.isConfirmed) return;
            try { await apiFetch(base + '/guia-config', 'POST', { desativar_id: Number(b.dataset.des) }); carregarGuias(); carregar(); }
            catch (e) { Swal.fire({ ...swalBase, icon: 'error', title: 'Erro', text: e.message }); }
        });
    }
    carregar();
    carregarPendentes();
    carregarGuias();
});
