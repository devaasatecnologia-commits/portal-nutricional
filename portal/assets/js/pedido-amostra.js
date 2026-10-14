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

const estado = { cliente: null, itens: [], qtd: {}, timerCliente: null, timerItem: null };

window.addEventListener('DOMContentLoaded', async () => {
    try {
        const ctx = await apiFetch('amostras/contexto');
        $('selFilial').innerHTML = ctx.filiais.map((f) => `<option value="${f.idfilial}">${esc(f.idfilial)} - ${esc(f.razao)}</option>`).join('');
        $('selTransacao').innerHTML = ctx.transacoes.map((t) => `<option value="${t.codigo}">${esc(t.nome)}</option>`).join('');
        if (!ctx.filiais.length) alert('Seu usuário não possui filial liberada para este módulo.');
    } catch (e) {
        alert(e.message);
        return;
    }

    $('selFilial').addEventListener('change', carregarItens);
    $('selTransacao').addEventListener('change', carregarItens);
    $('btnRecarregar').addEventListener('click', carregarItens);
    $('buscaItem').addEventListener('input', () => { clearTimeout(estado.timerItem); estado.timerItem = setTimeout(carregarItens, 350); });
    $('buscaCliente').addEventListener('input', () => { clearTimeout(estado.timerCliente); estado.timerCliente = setTimeout(buscarClientes, 350); });
    $('btnEnviar').addEventListener('click', enviar);

    carregarItens();
    carregarPedidos();
});

async function carregarItens() {
    estado.qtd = {};
    atualizarResumo();
    try {
        const resp = await apiFetch('amostras/itens', 'GET', {
            idfilial: $('selFilial').value,
            idtransacao: $('selTransacao').value,
            q: $('buscaItem').value.trim(),
        });
        estado.itens = resp.itens;
        $('tbItens').innerHTML = resp.itens.map((i) => `
            <tr class="border-t">
                <td class="p-2">${esc(i.iditem)} - ${esc(i.descricao)}</td>
                <td class="p-2 text-right">${num(i.quantidade_estoque)}</td>
                <td class="p-2"><input type="number" min="0" max="${i.quantidade_estoque}" step="1" data-id="${i.iditem}" class="qtd w-full border rounded-lg p-1 text-right"></td>
            </tr>`).join('') || '<tr><td colspan="3" class="p-4 text-center text-gray-400">Nenhum item com saldo</td></tr>';
        document.querySelectorAll('.qtd').forEach((inp) => inp.addEventListener('input', () => {
            const v = parseFloat(inp.value);
            if (v > 0) estado.qtd[inp.dataset.id] = v; else delete estado.qtd[inp.dataset.id];
            atualizarResumo();
        }));
    } catch (e) {
        $('tbItens').innerHTML = `<tr><td colspan="3" class="p-4 text-red-600">${esc(e.message)}</td></tr>`;
    }
}

function atualizarResumo() {
    const n = Object.keys(estado.qtd).length;
    $('resumoSel').textContent = n ? `${n} item(ns) selecionado(s)` : '';
}

async function buscarClientes() {
    const q = $('buscaCliente').value.trim();
    const box = $('listaClientes');
    if (q.length < 2) { box.classList.add('hidden'); return; }
    try {
        const resp = await apiFetch('amostras/clientes', 'GET', { q });
        box.innerHTML = resp.clientes.map((c) => `<div class="p-2 hover:bg-gray-100 cursor-pointer" data-id="${c.idcliforemp}" data-nome="${esc(c.fantasia || c.razao)}">${esc(c.idcliforemp)} - ${esc(c.fantasia || c.razao)}</div>`).join('') || '<div class="p-2 text-gray-400">Nada encontrado</div>';
        box.classList.remove('hidden');
        box.querySelectorAll('[data-id]').forEach((el) => el.addEventListener('click', () => {
            estado.cliente = { id: el.dataset.id, nome: el.dataset.nome };
            $('clienteSel').textContent = `${el.dataset.id} - ${el.dataset.nome}`;
            $('buscaCliente').value = '';
            box.classList.add('hidden');
        }));
    } catch (e) {
        box.innerHTML = `<div class="p-2 text-red-600">${esc(e.message)}</div>`;
        box.classList.remove('hidden');
    }
}

async function enviar() {
    const itens = Object.entries(estado.qtd).map(([iditem, quantidade]) => ({ iditem: Number(iditem), quantidade }));
    if (!estado.cliente || !itens.length) { alert('Sem dados para enviar, favor revisar.'); return; }
    if (!confirm('Confirmar envio do pedido?')) return;

    const btn = $('btnEnviar');
    btn.disabled = true;
    try {
        const resp = await apiFetch('amostras/pedidos', 'POST', {
            idfilial: Number($('selFilial').value),
            idtransacao: Number($('selTransacao').value),
            idcliente: Number(estado.cliente.id),
            observacao: $('observacao').value.trim(),
            itens,
        });
        const aviso = $('avisoSandbox');
        aviso.textContent = resp.sandbox ? resp.message : '';
        aviso.classList.toggle('hidden', !resp.sandbox);
        if (!resp.sandbox) alert(resp.message);
        $('observacao').value = '';
        estado.cliente = null;
        $('clienteSel').textContent = '';
        await carregarItens();
    } catch (e) {
        alert(e.message);
    } finally {
        btn.disabled = false;
        carregarPedidos();
    }
}

async function carregarPedidos() {
    try {
        const resp = await apiFetch('amostras/pedidos');
        const linha = (p, pendente) => `
            <tr class="border-t align-top">
                <td class="p-2 whitespace-nowrap">${esc(String(p.data || '').slice(0, 10))}</td>
                <td class="p-2">${esc(p.idpedido)}${pendente ? ' <span class="text-xs text-amber-600">(aguardando ERP)</span>' : ''}</td>
                <td class="p-2">${esc(p.fantasia)}</td>
                <td class="p-2" style="white-space:pre-line">${esc(p.itens)}</td>
                <td class="p-2">${esc(p.observacao)}</td>
            </tr>`;
        $('tbPedidos').innerHTML = resp.pendentes.map((p) => linha(p, true)).join('') + resp.pedidos.map((p) => linha(p, false)).join('')
            || '<tr><td colspan="5" class="p-4 text-center text-gray-400">Sem pedidos</td></tr>';
    } catch (e) {
        $('tbPedidos').innerHTML = `<tr><td colspan="5" class="p-4 text-red-600">${esc(e.message)}</td></tr>`;
    }
}
