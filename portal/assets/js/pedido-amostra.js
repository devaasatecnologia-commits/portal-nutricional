// Módulo Pedido de Amostra: itens/saldo vêm de amostra_brindes_com_saldo (validado no servidor)

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
const num = (n) => Number(n).toLocaleString('pt-BR', { maximumFractionDigits: 3 });
const dataBR = (d) => { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(d); };

const swalBase = { confirmButtonColor: '#059669', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = { cliente: null, qtd: {}, pedidos: [], filtro: 'todos', timerCliente: null, timerItem: null };

window.addEventListener('DOMContentLoaded', async () => {
    try {
        const ctx = await apiFetch('amostras/contexto');
        $('selFilial').innerHTML = ctx.filiais.map((f) => `<option value="${f.idfilial}">${esc(f.idfilial)} - ${esc(f.razao)}</option>`).join('');
        $('selTransacao').innerHTML = ctx.transacoes.map((t) => `<option value="${t.codigo}">${esc(t.nome)}</option>`).join('');
        if (!ctx.filiais.length) aviso('warning', 'Sem filial', 'Seu usuário não possui filial liberada para este módulo.');
    } catch (e) {
        aviso('error', 'Erro ao carregar', e.message);
        return;
    }

    $('selFilial').addEventListener('change', carregarItens);
    $('selTransacao').addEventListener('change', carregarItens);
    $('btnRecarregar').addEventListener('click', carregarItens);
    $('btnAtualizarPedidos').addEventListener('click', carregarPedidos);
    document.querySelectorAll('#filtroPedidos .pa-chip').forEach((b) => b.addEventListener('click', () => {
        estado.filtro = b.dataset.f;
        document.querySelectorAll('#filtroPedidos .pa-chip').forEach((c) => c.classList.toggle('on', c === b));
        renderPedidos();
    }));
    $('buscaItem').addEventListener('input', () => { clearTimeout(estado.timerItem); estado.timerItem = setTimeout(carregarItens, 350); });
    $('buscaCliente').addEventListener('input', () => { clearTimeout(estado.timerCliente); estado.timerCliente = setTimeout(buscarClientes, 350); });
    $('observacao').addEventListener('input', () => { $('contObs').textContent = $('observacao').value.length; });
    $('btnEnviar').addEventListener('click', enviar);

    carregarItens();
    carregarPedidos();
});

function setQtd(id, valor, max) {
    let v = Math.floor(Number(valor) || 0);
    if (v > max) {
        v = max;
        Swal.fire({ ...swalBase, toast: true, position: 'top', icon: 'warning', title: `Máximo disponível: ${num(max)}`, showConfirmButton: false, timer: 1800 });
    }
    if (v < 0) v = 0;
    if (v > 0) estado.qtd[id] = v; else delete estado.qtd[id];
    const row = document.querySelector(`.pa-item[data-id="${id}"]`);
    if (row) {
        row.classList.toggle('on', v > 0);
        row.querySelector('input').value = v || '';
    }
    atualizarResumo();
}

async function carregarItens() {
    estado.qtd = {};
    atualizarResumo();
    const box = $('listaItens');
    box.innerHTML = '<div class="p-4 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    try {
        const resp = await apiFetch('amostras/itens', 'GET', {
            idfilial: $('selFilial').value,
            idtransacao: $('selTransacao').value,
            q: $('buscaItem').value.trim(),
        });
        box.innerHTML = resp.itens.map((i) => `
            <div class="pa-item" data-id="${i.iditem}" data-max="${i.quantidade_estoque}">
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm leading-tight">${esc(i.descricao)}</div>
                    <div class="text-xs text-slate-500 mt-1">Cód. ${esc(i.iditem)} · Saldo <b class="text-emerald-700">${num(i.quantidade_estoque)}</b></div>
                </div>
                <div class="pa-qty">
                    <button type="button" data-a="-">−</button>
                    <input type="number" inputmode="numeric" min="0" max="${i.quantidade_estoque}" placeholder="0">
                    <button type="button" data-a="+">+</button>
                </div>
            </div>`).join('') || '<div class="p-6 text-center text-slate-400 text-sm">Nenhum item com saldo</div>';

        box.querySelectorAll('.pa-item').forEach((row) => {
            const id = row.dataset.id, max = Number(row.dataset.max), inp = row.querySelector('input');
            row.querySelectorAll('button').forEach((b) => b.addEventListener('click', () =>
                setQtd(id, (estado.qtd[id] || 0) + (b.dataset.a === '+' ? 1 : -1), max)));
            inp.addEventListener('change', () => setQtd(id, inp.value, max));
        });
    } catch (e) {
        box.innerHTML = `<div class="p-4 text-red-600 text-sm">${esc(e.message)}</div>`;
    }
}

function atualizarResumo() {
    const ids = Object.keys(estado.qtd);
    const total = ids.reduce((s, k) => s + estado.qtd[k], 0);
    $('resumoSel').textContent = ids.length ? `${ids.length} item(ns) · ${num(total)} un.` : 'Nenhum item selecionado';
}

async function buscarClientes() {
    const q = $('buscaCliente').value.trim();
    const box = $('listaClientes');
    if (q.length < 2) { box.classList.add('hidden'); return; }
    try {
        const resp = await apiFetch('amostras/clientes', 'GET', { q });
        box.innerHTML = resp.clientes.map((c) => `<div class="p-3 border-b last:border-0 active:bg-emerald-50 cursor-pointer text-sm" data-id="${c.idcliforemp}" data-nome="${esc(c.fantasia || c.razao)}"><b>${esc(c.fantasia || c.razao)}</b><div class="text-xs text-slate-500">Cód. ${esc(c.idcliforemp)}</div></div>`).join('') || '<div class="p-3 text-slate-400 text-sm">Nenhum cliente encontrado</div>';
        box.classList.remove('hidden');
        box.querySelectorAll('[data-id]').forEach((el) => el.addEventListener('click', () => {
            selecionarCliente({ id: el.dataset.id, nome: el.dataset.nome });
            box.classList.add('hidden');
        }));
    } catch (e) {
        box.innerHTML = `<div class="p-3 text-red-600 text-sm">${esc(e.message)}</div>`;
        box.classList.remove('hidden');
    }
}

function selecionarCliente(c) {
    estado.cliente = c;
    const el = $('clienteSel');
    if (!c) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    $('buscaCliente').value = '';
    el.classList.remove('hidden');
    el.innerHTML = `<span><i class="fa-solid fa-circle-check mr-1"></i>${esc(c.nome)} <small class="font-normal">(${esc(c.id)})</small></span><button type="button" id="limparCliente" class="text-emerald-700"><i class="fa-solid fa-xmark"></i></button>`;
    $('limparCliente').addEventListener('click', () => selecionarCliente(null));
}

async function enviar() {
    const itens = Object.entries(estado.qtd).map(([iditem, quantidade]) => ({ iditem: Number(iditem), quantidade }));
    const observacao = $('observacao').value.trim();

    if (!estado.cliente) return aviso('warning', 'Cliente', 'Selecione o cliente.');
    if (!itens.length) return aviso('warning', 'Itens', 'Informe a quantidade de ao menos um item.');
    if (!observacao) return aviso('warning', 'Observação obrigatória', 'Preencha a observação do pedido.');

    const filialTxt = $('selFilial').selectedOptions[0]?.text || '';
    const tipoTxt = $('selTransacao').selectedOptions[0]?.text || '';
    const ok = await Swal.fire({
        ...swalBase,
        icon: 'question',
        title: 'Confirmar envio?',
        html: `<div class="text-left text-sm"><b>${esc(estado.cliente.nome)}</b><br>${esc(tipoTxt)} · Filial ${esc(filialTxt)}<br>${itens.length} item(ns)<br><i>${esc(observacao)}</i></div>`,
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        cancelButtonText: 'Revisar',
    });
    if (!ok.isConfirmed) return;

    const btn = $('btnEnviar');
    btn.disabled = true;
    Swal.fire({ ...swalBase, title: 'Enviando...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    try {
        const resp = await apiFetch('amostras/pedidos', 'POST', {
            idfilial: Number($('selFilial').value),
            idtransacao: Number($('selTransacao').value),
            idcliente: Number(estado.cliente.id),
            observacao,
            itens,
        });

        if (resp.sandbox) {
            const sql = resp.sql ? [resp.sql.pedido, ...[].concat(resp.sql.itens || []), resp.sql.update].join('\n\n') : '';
            await Swal.fire({
                ...swalBase, icon: 'info', title: 'Modo sandbox', width: 700,
                html: `<p class="text-sm mb-2">${esc(resp.message)}</p><pre style="text-align:left;max-height:300px;overflow:auto;font-size:11px;background:#0f172a;color:#86efac;padding:10px;border-radius:12px;white-space:pre-wrap">${esc(sql)}</pre>`,
            });
        } else {
            await aviso('success', 'Pedido enviado', resp.message);
        }

        $('observacao').value = '';
        $('contObs').textContent = '0';
        selecionarCliente(null);
        await carregarItens();
    } catch (e) {
        await aviso('error', 'Não foi possível enviar', e.message);
    } finally {
        btn.disabled = false;
        carregarPedidos();
    }
}

const statusTag = (p) => p.cancelado
    ? '<span class="text-red-600 font-bold">cancelado</span>'
    : (p.pendente ? '<span class="text-amber-600 font-bold">aguardando ERP</span>' : '<span class="text-emerald-600 font-bold">no ERP</span>');

function renderPedidos() {
    const box = $('listaPedidos');
    const f = estado.filtro;
    const lista = estado.pedidos
        .map((p, i) => ({ p, i }))
        .filter(({ p }) => f === 'todos' || (f === 'cancelados' ? p.cancelado : !p.cancelado));
    box.innerHTML = lista.map(({ p, i }) => `
        <div class="pa-ped ${p.cancelado ? 'cancel' : ''}">
            <div class="flex-1 min-w-0">
                <div class="font-bold text-sm truncate">${esc(p.fantasia)}</div>
                <div class="text-xs text-slate-500">#${esc(p.idpedido)} · ${dataBR(p.data)} · ${statusTag(p)}</div>
            </div>
            <button type="button" class="pa-eye" data-i="${i}" title="Ver pedido"><i class="fa-solid fa-eye"></i></button>
        </div>`).join('') || '<div class="p-6 text-center text-slate-400 text-sm">Sem pedidos</div>';
    box.querySelectorAll('.pa-eye').forEach((b) => b.addEventListener('click', () => verPedido(estado.pedidos[b.dataset.i])));
}

async function carregarPedidos() {
    try {
        const resp = await apiFetch('amostras/pedidos');
        estado.pedidos = [
            ...resp.pendentes.map((p) => ({ ...p, pendente: true })),
            ...resp.pedidos.map((p) => ({ ...p, pendente: false })),
        ];
        renderPedidos();
    } catch (e) {
        $('listaPedidos').innerHTML = `<div class="p-4 text-red-600 text-sm">${esc(e.message)}</div>`;
    }
}

function verPedido(p) {
    const itens = String(p.itens || '').split('\n').filter(Boolean)
        .map((l) => `<li class="py-1 border-b last:border-0">${esc(l)}</li>`).join('');
    Swal.fire({
        ...swalBase,
        title: `Pedido #${esc(p.idpedido)}`,
        html: `<div class="text-left text-sm">
            <div class="mb-2"><span class="text-xs text-slate-500">Cliente</span><br><b>${esc(p.fantasia)}</b></div>
            <div class="mb-2"><span class="text-xs text-slate-500">Data</span><br><b>${dataBR(p.data)}</b></div>
            <div class="mb-2"><span class="text-xs text-slate-500">Situação</span><br>${statusTag(p)}</div>
            <div class="mb-2"><span class="text-xs text-slate-500">Itens</span><ul class="list-none p-0 m-0">${itens}</ul></div>
            <div><span class="text-xs text-slate-500">Observação</span><br>${esc(p.observacao) || '<i>—</i>'}</div>
        </div>`,
        confirmButtonText: 'Fechar',
    });
}
