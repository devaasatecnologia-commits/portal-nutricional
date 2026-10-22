// Módulo Pedido de Material de Apoio & Amostras
// Nutricional Distribuidora - Experiência Premium

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
const num = (n) => Number(n || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
const dataBR = (d) => { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(d); };

function getImageUrl(foto) {
    const sem = 'https://placehold.co/100x100?text=S/F';
    if (!foto) return sem;
    const n = String(foto).trim().replace(/\\/g, '/');
    if (!n || ['.', '-', 'null', 'undefined'].includes(n.toLowerCase())) return sem;
    if (/^https?:\/\//i.test(n)) return n.replace(/ /g, '%20');
    const marker = 'fotos para o site/';
    const idx = n.toLowerCase().indexOf(marker);
    const rel = idx >= 0 ? n.substring(idx + marker.length) : n.replace(/^\/+/, '').replace(/^fotos\//i, '');
    if (!rel) return sem;
    return 'https://acesso.nutricionalbr.com:2053/fotos/' . concat(rel.split('/').map(encodeURIComponent).join('/'));
}

const swalBase = { confirmButtonColor: '#059669', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = {
    abaAtiva: 'solicitar',
    cliente: null,
    qtd: {},
    itensCatalogo: [],
    pedidos: [],
    pendentes: [],
    metricas: {},
    configFilialPr: { percentual_padrao: 30 },
    timerCliente: null,
    timerItem: null,
};

function trocarAba(aba) {
    estado.abaAtiva = aba;
    const btnSol = $('tabBtnSolicitar');
    const btnHist = $('tabBtnHistorico');
    const abSol = $('abaSolicitar');
    const abHist = $('abaHistorico');

    if (aba === 'solicitar') {
        btnSol.classList.add('active');
        btnHist.classList.remove('active');
        abSol.classList.remove('hidden');
        abHist.classList.add('hidden');
    } else {
        btnHist.classList.add('active');
        btnSol.classList.remove('active');
        abHist.classList.remove('hidden');
        abSol.classList.add('hidden');
        carregarPedidos();
    }
}

window.addEventListener('DOMContentLoaded', async () => {
    try {
        const ctx = await apiFetch('amostras/contexto');
        $('selFilial').innerHTML = ctx.filiais.map((f) => `<option value="${f.idfilial}">${esc(f.idfilial)} - ${esc(f.razao)}</option>`).join('');
        $('selTransacao').innerHTML = ctx.transacoes.map((t) => `<option value="${t.codigo}">${esc(t.nome)}</option>`).join('');
        
        if (ctx.config_filial_pr) {
            estado.configFilialPr = ctx.config_filial_pr;
        }

        atualizarRegraEstoqueVisual();

        if (!ctx.filiais.length) {
            aviso('warning', 'Sem filial', 'Seu usuário não possui filial liberada para este módulo.');
        }
    } catch (e) {
        aviso('error', 'Erro ao carregar', e.message);
        return;
    }

    $('selFilial').addEventListener('change', () => {
        atualizarRegraEstoqueVisual();
        carregarItens();
    });
    $('selTransacao').addEventListener('change', carregarItens);
    $('btnRecarregar').addEventListener('click', carregarItens);
    $('btnAtualizarPedidos').addEventListener('click', carregarPedidos);

    $('buscaItem').addEventListener('input', () => {
        clearTimeout(estado.timerItem);
        estado.timerItem = setTimeout(carregarItens, 350);
    });

    $('buscaCliente').addEventListener('input', () => {
        clearTimeout(estado.timerCliente);
        estado.timerCliente = setTimeout(buscarClientes, 350);
    });

    $('observacao').addEventListener('input', () => {
        $('contObs').textContent = $('observacao').value.length;
    });

    $('btnEnviar').addEventListener('click', enviar);

    // Filtros do histórico
    $('buscaHistorico').addEventListener('input', renderPedidos);
    $('filtroOrigem').addEventListener('change', renderPedidos);
    $('filtroStatus').addEventListener('change', renderPedidos);

    // Fecha modal clicando fora
    $('modalDetalhesPedido').addEventListener('click', (e) => {
        if (e.target === $('modalDetalhesPedido')) fecharModalDetalhes();
    });

    carregarItens();
});

function atualizarRegraEstoqueVisual() {
    const filial = Number($('selFilial').value);
    const badge = $('badgeRegraEstoque');
    const txtInfo = $('txtInfoRegraFilial');

    if (badge) {
        badge.innerHTML = filial === 6
            ? `<i class="fa-solid fa-location-dot mr-1"></i>Filial 6 (PR)`
            : `<i class="fa-solid fa-location-dot mr-1"></i>Matriz 1`;
    }
    if (txtInfo) {
        txtInfo.innerHTML = `Disponibilidade de Estoque em Tempo Real`;
    }
}

function setQtd(id, valor, max) {
    let v = Math.floor(Number(valor) || 0);
    if (v > max) {
        v = max;
        Swal.fire({
            ...swalBase,
            toast: true,
            position: 'top',
            icon: 'warning',
            title: `Saldo máximo disponível: ${num(max)} un.`,
            showConfirmButton: false,
            timer: 2000
        });
    }
    if (v < 0) v = 0;
    if (v > 0) estado.qtd[id] = v; else delete estado.qtd[id];

    const card = document.querySelector(`.pa-item-card[data-id="${id}"]`);
    if (card) {
        card.classList.toggle('has-qty', v > 0);
        const inp = card.querySelector('.pa-qty-inp');
        if (inp) inp.value = v || '';
    }
    atualizarResumo();
}

async function carregarItens() {
    estado.qtd = {};
    atualizarResumo();
    const box = $('listaItens');
    box.innerHTML = '<div class="col-span-full py-12 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-2xl text-emerald-600 block mb-2"></i>Carregando catálogo e estoques...</div>';

    try {
        const resp = await apiFetch('amostras/itens', 'GET', {
            idfilial: $('selFilial').value,
            idtransacao: $('selTransacao').value,
            q: $('buscaItem').value.trim(),
        });

        estado.itensCatalogo = resp.itens || [];

        if (!estado.itensCatalogo.length) {
            box.innerHTML = '<div class="col-span-full py-12 text-center text-slate-400"><i class="fa-solid fa-box-open text-3xl mb-2 text-slate-300 block"></i>Nenhum item com saldo liberado para esta filial.</div>';
            return;
        }

        box.innerHTML = estado.itensCatalogo.map((i) => {
            const fotoUrl = i.foto_url || getImageUrl(i.foto);
            const saldoLiberado = Number(i.saldo_estoque || 0);

            return `
            <div class="pa-item-card p-3.5 flex flex-col justify-between" data-id="${i.iditem}" data-max="${saldoLiberado}">
                <div class="flex items-start gap-3 mb-2.5">
                    <img src="${esc(fotoUrl)}" 
                         class="w-16 h-16 object-contain rounded-xl bg-slate-50 border border-slate-100 flex-none"
                         loading="lazy"
                         onerror="this.src='https://placehold.co/100x100?text=S/F'">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 mb-0.5">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-slate-100 text-slate-700">#${esc(i.iditem)}</span>
                            ${i.referencia ? `<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-500">Ref: ${esc(i.referencia)}</span>` : ''}
                        </div>
                        <h4 class="font-bold text-xs text-slate-800 line-clamp-2 leading-snug" title="${esc(i.descricao)}">${esc(i.descricao)}</h4>
                    </div>
                </div>

                <div class="bg-slate-50 rounded-xl p-2.5 mb-3 border border-slate-100 flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-500">Saldo Disponível:</span>
                    <b class="text-emerald-700 font-black text-sm">${num(saldoLiberado)} un.</b>
                </div>

                <div class="flex items-center justify-between pt-1 border-t border-slate-100">
                    <span class="text-[11px] font-bold text-slate-400">Solicitar:</span>
                    <div class="flex items-center gap-1.5">
                        <button type="button" class="pa-qty-btn" data-a="-" title="Diminuir">−</button>
                        <input type="number" class="pa-qty-inp" inputmode="numeric" min="0" max="${saldoLiberado}" placeholder="0">
                        <button type="button" class="pa-qty-btn" data-a="+" title="Aumentar">+</button>
                    </div>
                </div>
            </div>`;
        }).join('');

        box.querySelectorAll('.pa-item-card').forEach((card) => {
            const id = card.dataset.id;
            const max = Number(card.dataset.max);
            const inp = card.querySelector('.pa-qty-inp');

            card.querySelectorAll('.pa-qty-btn').forEach((b) => {
                b.addEventListener('click', () => {
                    const atual = estado.qtd[id] || 0;
                    setQtd(id, atual + (b.dataset.a === '+' ? 1 : -1), max);
                });
            });

            inp.addEventListener('input', () => setQtd(id, inp.value, max));
        });
    } catch (e) {
        box.innerHTML = `<div class="col-span-full py-8 text-center text-rose-600 text-xs font-bold">${esc(e.message)}</div>`;
    }
}

function atualizarResumo() {
    const ids = Object.keys(estado.qtd);
    const totalUn = ids.reduce((s, k) => s + estado.qtd[k], 0);
    const resumoEl = $('resumoSel');

    if (ids.length) {
        resumoEl.innerHTML = `<span class="text-emerald-700 font-black">${ids.length} item(ns)</span> selecionado(s) · <span class="text-emerald-800 font-black">${num(totalUn)} unidades</span>`;
    } else {
        resumoEl.textContent = 'Nenhum item selecionado';
    }
}

async function buscarClientes() {
    const q = $('buscaCliente').value.trim();
    const box = $('listaClientes');
    if (q.length < 2) {
        box.classList.add('hidden');
        return;
    }

    try {
        const resp = await apiFetch('amostras/clientes', 'GET', { q });
        const clientes = resp.clientes || [];

        if (!clientes.length) {
            box.innerHTML = '<div class="p-3.5 text-center text-slate-400 text-xs">Nenhum cliente ou representante encontrado</div>';
            box.classList.remove('hidden');
            return;
        }

        box.innerHTML = clientes.map((c) => `
            <div class="p-3 border-b last:border-0 hover:bg-emerald-50 active:bg-emerald-100 cursor-pointer text-xs transition"
                 data-id="${c.idcliforemp}" data-nome="${esc(c.fantasia || c.razao)}">
                <div class="font-extrabold text-slate-800 text-sm">${esc(c.fantasia || c.razao)}</div>
                <div class="text-[11px] text-slate-500 mt-0.5">
                    <span>Cód: <b>#${esc(c.idcliforemp)}</b></span>
                    ${c.cnpj ? `<span class="ml-2">CNPJ: ${esc(c.cnpj)}</span>` : ''}
                    ${c.razao && c.razao !== c.fantasia ? `<span class="ml-2 text-slate-400">(${esc(c.razao)})</span>` : ''}
                </div>
            </div>
        `).join('');

        box.classList.remove('hidden');

        box.querySelectorAll('[data-id]').forEach((el) => {
            el.addEventListener('click', () => {
                selecionarCliente({ id: el.dataset.id, nome: el.dataset.nome });
                box.classList.add('hidden');
            });
        });
    } catch (e) {
        box.innerHTML = `<div class="p-3 text-rose-600 text-xs">${esc(e.message)}</div>`;
        box.classList.remove('hidden');
    }
}

function selecionarCliente(c) {
    estado.cliente = c;
    const el = $('clienteSel');
    if (!c) {
        el.classList.add('hidden');
        el.innerHTML = '';
        return;
    }
    $('buscaCliente').value = '';
    el.classList.remove('hidden');
    el.innerHTML = `
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="w-8 h-8 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-bold text-xs flex-none">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <div class="truncate">
                <div class="text-xs font-black truncate">${esc(c.nome)}</div>
                <div class="text-[11px] font-semibold text-emerald-700">Código Cliente: #${esc(c.id)}</div>
            </div>
        </div>
        <button type="button" id="limparCliente" class="px-2.5 py-1 rounded-xl bg-emerald-200/60 hover:bg-emerald-200 text-emerald-900 text-xs font-bold transition flex items-center gap-1">
            <i class="fa-solid fa-xmark"></i> Alterar
        </button>
    `;
    $('limparCliente').addEventListener('click', () => selecionarCliente(null));
}

async function enviar() {
    const itens = Object.entries(estado.qtd).map(([iditem, quantidade]) => ({ iditem: Number(iditem), quantidade }));
    const observacao = $('observacao').value.trim();

    if (!estado.cliente) return aviso('warning', 'Cliente Obrigatório', 'Selecione o cliente / destinatário do pedido.');
    if (!itens.length) return aviso('warning', 'Nenhum Item', 'Informe a quantidade de ao menos um item de apoio.');
    if (!observacao) return aviso('warning', 'Observação Obrigatória', 'Preencha a observação / justificativa do pedido.');

    const filialTxt = $('selFilial').selectedOptions[0]?.text || '';
    const tipoTxt = $('selTransacao').selectedOptions[0]?.text || '';
    const totalUn = itens.reduce((s, i) => s + i.quantidade, 0);

    const ok = await Swal.fire({
        ...swalBase,
        icon: 'question',
        title: 'Confirmar Envio da Solicitação?',
        html: `
            <div class="text-left text-xs bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                <div><span class="text-slate-400 uppercase font-bold">Destinatário:</span><br><b class="text-slate-800 text-sm">${esc(estado.cliente.nome)} (#${estado.cliente.id})</b></div>
                <div><span class="text-slate-400 uppercase font-bold">Filial & Tipo:</span><br><b>${esc(filialTxt)}</b> · ${esc(tipoTxt)}</div>
                <div><span class="text-slate-400 uppercase font-bold">Resumo:</span><br><b>${itens.length} item(ns)</b> somando <b>${num(totalUn)} unidades</b></div>
                <div><span class="text-slate-400 uppercase font-bold">Observação:</span><br><i class="text-slate-600">${esc(observacao)}</i></div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-paper-plane mr-1"></i> Confirmar e Enviar',
        cancelButtonText: 'Voltar e Revisar',
        confirmButtonColor: '#059669',
    });
    if (!ok.isConfirmed) return;

    const btn = $('btnEnviar');
    btn.disabled = true;
    Swal.fire({ ...swalBase, title: 'Criando Pedido...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resp = await apiFetch('amostras/pedidos', 'POST', {
            idfilial: Number($('selFilial').value),
            idtransacao: Number($('selTransacao').value),
            idcliente: Number(estado.cliente.id),
            observacao,
            itens,
        });

        if (resp.sandbox) {
            await Swal.fire({
                ...swalBase,
                icon: 'info',
                title: 'Modo Sandbox',
                text: resp.message || 'Pedido validado em ambiente sandbox.',
            });
        } else {
            await Swal.fire({
                ...swalBase,
                icon: 'success',
                title: 'Solicitação Criada com Sucesso!',
                html: `<div class="text-sm font-bold text-slate-700">${esc(resp.message)}</div><div class="text-xs text-slate-400 mt-2">O pedido já está disponível para rastreamento no painel.</div>`,
                confirmButtonText: 'Ver Histórico',
            });
        }

        $('observacao').value = '';
        $('contObs').textContent = '0';
        selecionarCliente(null);
        await carregarItens();
        trocarAba('historico');
    } catch (e) {
        await aviso('error', 'Não foi possível enviar', e.message);
    } finally {
        btn.disabled = false;
    }
}

// ==========================================
// HISTÓRICO & RASTREAMENTO
// ==========================================

async function carregarPedidos() {
    const corpo = $('tabelaPedidosCorpo');
    corpo.innerHTML = '<tr><td colspan="9" class="p-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-emerald-600 block mb-2"></i>Carregando pedidos e métricas...</td></tr>';

    try {
        const resp = await apiFetch('amostras/pedidos');
        estado.pedidos = resp.pedidos || [];
        estado.pendentes = resp.pendentes || [];
        estado.metricas = resp.metricas || {};

        // Atualiza KPIs
        $('kpiTotalPedidos').textContent = num(estado.metricas.total_pedidos || 0);
        $('kpiPendentesErp').textContent = num(estado.metricas.total_pendentes || 0);
        $('kpiAbertos').textContent = num(estado.metricas.total_abertos || 0);
        $('kpiFaturados').textContent = num(estado.metricas.total_faturados || 0);

        renderPedidos();
    } catch (e) {
        corpo.innerHTML = `<tr><td colspan="9" class="p-8 text-center text-rose-600 text-xs font-bold">${esc(e.message)}</td></tr>`;
    }
}

function renderPedidos() {
    const corpo = $('tabelaPedidosCorpo');
    const vazio = $('pedidosVazio');
    const fStatus = $('filtroStatus').value;
    const fOrigem = $('filtroOrigem').value;
    const busca = $('buscaHistorico').value.trim().toLowerCase();

    // Combina pendentes (portal ainda não importado) + pedidos ERP
    const todos = [
        ...estado.pendentes.map((p) => ({ ...p, pendente: true })),
        ...estado.pedidos.map((p) => ({ ...p, pendente: false })),
    ];

    const filtrados = todos.filter((p) => {
        // Filtro Status
        if (fStatus === 'pendentes' && !p.pendente) return false;
        if (fStatus === 'ativos' && (p.pendente || Number(p.status_pedido) === 5)) return false;
        if (fStatus === 'faturados' && (p.pendente || Number(p.status_pedido) !== 5)) return false;

        // Filtro Origem
        if (fOrigem !== 'todos' && p.origem !== fOrigem) return false;

        // Busca textual
        if (busca) {
            const numPed = String(p.idpedido || '').toLowerCase();
            const cli = String(p.fantasia || p.razao_social || '').toLowerCase();
            const sol = String(p.solicitante || '').toLowerCase();
            const obs = String(p.observacao || '').toLowerCase();
            if (!numPed.includes(busca) && !cli.includes(busca) && !sol.includes(busca) && !obs.includes(busca)) {
                return false;
            }
        }
        return true;
    });

    if (!filtrados.length) {
        corpo.innerHTML = '';
        vazio.classList.remove('hidden');
        return;
    }

    vazio.classList.add('hidden');
    corpo.innerHTML = filtrados.map((p) => {
        const isPortal = p.origem === 'PORTAL';
        const badgeOrigem = isPortal
            ? `<span class="px-2 py-0.5 rounded-lg bg-purple-100 text-purple-800 font-extrabold text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-laptop text-[10px]"></i>PORTAL</span>`
            : `<span class="px-2 py-0.5 rounded-lg bg-sky-100 text-sky-800 font-extrabold text-[11px] inline-flex items-center gap-1"><i class="fa-solid fa-server text-[10px]"></i>ERP</span>`;

        let badgeStatus = '';
        if (p.pendente) {
            badgeStatus = `<span class="px-2 py-0.5 rounded-lg bg-amber-100 text-amber-800 font-bold text-[11px]"><i class="fa-solid fa-clock mr-1"></i>Aguardando ERP</span>`;
        } else if (Number(p.status_pedido) === 5) {
            badgeStatus = `<span class="px-2 py-0.5 rounded-lg bg-blue-100 text-blue-800 font-bold text-[11px]"><i class="fa-solid fa-circle-check mr-1"></i>Faturado</span>`;
        } else {
            badgeStatus = `<span class="px-2 py-0.5 rounded-lg bg-emerald-100 text-emerald-800 font-bold text-[11px]"><i class="fa-solid fa-folder-open mr-1"></i>Em Aberto</span>`;
        }

        const filialBadge = Number(p.idfilial) === 6
            ? `<span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-900 border border-amber-200 font-bold text-[10px]">Filial 6 (PR)</span>`
            : `<span class="px-2 py-0.5 rounded-md bg-slate-100 text-slate-700 font-bold text-[10px]">Matriz 1</span>`;

        return `
            <tr class="hover:bg-slate-50 transition border-b border-slate-100">
                <td class="py-3 px-3 font-black text-slate-800">
                    #${esc(p.idpedido)}
                    ${p.pendente ? '<span class="ml-1 px-1.5 py-0.2 rounded bg-amber-500 text-white text-[9px] font-black">NOVO</span>' : ''}
                </td>
                <td class="py-3 px-3 text-slate-500 font-semibold whitespace-nowrap">${dataBR(p.data)}</td>
                <td class="py-3 px-3">
                    <div class="font-extrabold text-slate-800 truncate max-w-xs" title="${esc(p.fantasia)}">${esc(p.fantasia)}</div>
                    ${p.supervisor_nome ? `<div class="text-[10px] text-slate-400">Gestor: ${esc(p.supervisor_nome)}</div>` : ''}
                </td>
                <td class="py-3 px-3">${badgeOrigem}</td>
                <td class="py-3 px-3">
                    <span class="font-bold text-slate-700 flex items-center gap-1.5">
                        <i class="fa-solid fa-user text-slate-400 text-[10px]"></i>${esc(p.solicitante || '—')}
                    </span>
                </td>
                <td class="py-3 px-3">${filialBadge}</td>
                <td class="py-3 px-3 text-center whitespace-nowrap">
                    <span class="font-black text-emerald-800 text-xs">${num(p.total_unidades || 0)} un.</span>
                    <span class="text-[10px] text-slate-400 block">${p.total_itens || 0} item(ns)</span>
                </td>
                <td class="py-3 px-3">${badgeStatus}</td>
                <td class="py-3 px-3 text-right">
                    <button type="button" onclick="verDetalhesPedido(${p.idpedido})" 
                            class="px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-600 text-xs font-bold transition flex items-center gap-1 ml-auto">
                        <i class="fa-solid fa-eye"></i>
                        <span>Ver</span>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function verDetalhesPedido(id) {
    Swal.fire({
        ...swalBase,
        title: 'Carregando pedido...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const resp = await apiFetch(`amostras/pedidos/${id}`);
        Swal.close();

        const p = resp.pedido;
        if (!p) throw new Error('Pedido não encontrado.');

        $('modalPedidoTitulo').textContent = `Pedido #${p.idpedido}`;
        $('modalPedidoSubtitulo').textContent = `${dataBR(p.data)} · ${p.fantasia || 'Cliente'}`;

        const isPortal = p.origem === 'PORTAL';
        $('modalOrigem').innerHTML = isPortal
            ? `<span class="text-purple-700 font-extrabold"><i class="fa-solid fa-laptop mr-1"></i>PORTAL</span>`
            : `<span class="text-sky-700 font-extrabold"><i class="fa-solid fa-server mr-1"></i>ERP</span>`;

        $('modalSolicitante').textContent = p.solicitante || '—';
        $('modalFilial').textContent = Number(p.idfilial) === 6 ? '6 - Filial PR' : '1 - Matriz';

        let statusTxt = '';
        if (p.pendente) {
            statusTxt = '<span class="text-amber-600 font-bold">Aguardando ERP</span>';
        } else if (Number(p.status_pedido) === 5) {
            statusTxt = '<span class="text-blue-600 font-bold">Faturado</span>';
        } else {
            statusTxt = '<span class="text-emerald-600 font-bold">Em Aberto</span>';
        }
        $('modalStatus').innerHTML = statusTxt;

        $('modalObservacao').textContent = p.observacao || 'Nenhuma observação informada.';

        // Renderiza itens com fotos
        const itens = p.itens || [];
        const boxItens = $('modalListaItens');

        if (!itens.length) {
            boxItens.innerHTML = '<div class="p-3 text-center text-slate-400 text-xs">Nenhum item listado</div>';
        } else {
            boxItens.innerHTML = itens.map((it) => {
                const fotoUrl = it.foto_url || getImageUrl(it.path_foto_master);
                return `
                    <div class="flex items-center gap-3 p-2.5 rounded-2xl border border-slate-100 bg-white hover:bg-slate-50 transition">
                        <img src="${esc(fotoUrl)}" 
                             class="w-12 h-12 object-contain rounded-xl bg-slate-50 border border-slate-100 flex-none"
                             onerror="this.src='https://placehold.co/100x100?text=S/F'">
                        <div class="flex-1 min-w-0">
                            <div class="font-extrabold text-xs text-slate-800 truncate">${esc(it.descricao)}</div>
                            <div class="text-[10px] text-slate-400 mt-0.5">
                                <span>Cód: <b>#${it.iditem}</b></span>
                                ${it.referencia ? `<span class="ml-2">Ref: ${esc(it.referencia)}</span>` : ''}
                            </div>
                        </div>
                        <div class="text-right flex-none">
                            <span class="text-sm font-black text-emerald-700">${num(it.qt)} un.</span>
                        </div>
                    </div>
                `;
            }).join('');
        }

        $('modalDetalhesPedido').classList.remove('hidden');
    } catch (e) {
        aviso('error', 'Erro ao carregar detalhes', e.message);
    }
}

function fecharModalDetalhes() {
    $('modalDetalhesPedido').classList.add('hidden');
}
