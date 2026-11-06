/**
 * Módulo Fluxo E-commerce (AZ PETS CO • Tabela 15 • Transação 1)
 * Nutricional Distribuidora
 */

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
const formatMoeda = (n) => Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatQtd = (n) => Number(n || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
const dataBR = (d) => { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(d); };

const swalBase = { confirmButtonColor: '#2563eb', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = {
    abaAtiva: 'pedido',
    contexto: null,
    itensCatalogo: [],
    quantidades: {},
    pedidos: [],
    pedidosFiltrados: [],
    filtroRapidoAtivo: 'este_mes',
    condicaoSelecionada: 1002,
    metodoSelecionado: 1000
};

function trocarAba(aba) {
    estado.abaAtiva = aba;
    const btnPed = $('tabBtnNovoPedido');
    const btnDash = $('tabBtnDashboard');
    const abPed = $('abaNovoPedido');
    const abDash = $('abaDashboard');

    if (aba === 'pedido') {
        btnPed.classList.add('active');
        btnDash.classList.remove('active');
        abPed.classList.remove('hidden');
        abDash.classList.add('hidden');
    } else {
        btnDash.classList.add('active');
        btnPed.classList.remove('active');
        abDash.classList.remove('hidden');
        abPed.classList.add('hidden');
        
        // Se ainda não inicializou as datas, aplica o filtro do mês corrente
        if (!$('filtroDataInicio').value && !$('filtroDataFim').value) {
            aplicarFiltroRapido('este_mes');
        } else {
            atualizarDashboardEHistorico();
        }
    }
}

// ==========================================================================
// CATÁLOGO E SELEÇÃO DE ITENS
// ==========================================================================

async function carregarContextoEItens() {
    try {
        const ctx = await apiFetch('fluxo-ecomerce/contexto');
        estado.contexto = ctx;

        if (ctx.cliente) {
            if ($('txtClienteNome')) $('txtClienteNome').textContent = `${ctx.cliente.idcliforemp} - ${ctx.cliente.fantasia || ctx.cliente.razao}`;
            if ($('txtClienteCnpj')) $('txtClienteCnpj').textContent = `CNPJ: ${ctx.cliente.cnpj || '-'} • ${ctx.cliente.uf || 'SC'}`;
        }
        if (ctx.tabela && $('txtTabelaNome')) {
            $('txtTabelaNome').textContent = `Tabela ${ctx.tabela.idtabela} - ${ctx.tabela.nome}`;
            if ($('txtTabelaCondMet')) {
                $('txtTabelaCondMet').textContent = `Tabela: 1002 (PIX) • Cliente: 1072 (CREDIARIO)`;
            }
        }
        if (ctx.transacao && $('txtTransacaoNome')) {
            $('txtTransacaoNome').textContent = `${ctx.transacao.idtransacao} - ${ctx.transacao.nome}`;
        }

        // Mantém as regras dinâmicas do ERP (1º Cliente -> 2º Tabela)
        if (ctx.padroes) {
            estado.condicaoSelecionada = parseInt(ctx.padroes.idcondicao, 10);
            estado.condicaoNome = ctx.padroes.condicao_nome || '';
            estado.metodoSelecionado = parseInt(ctx.padroes.idmetodo, 10);
            estado.metodoNome = ctx.padroes.metodo_nome || '';

            if ($('badgeTopCondicao')) {
                $('badgeTopCondicao').textContent = `${estado.condicaoSelecionada} (${estado.condicaoNome})`;
            }
            if ($('badgeTopMetodo')) {
                $('badgeTopMetodo').textContent = `${estado.metodoSelecionado} (${estado.metodoNome})`;
            }
        }

        const resItens = await apiFetch('fluxo-ecomerce/itens');
        estado.itensCatalogo = resItens.itens || [];
        renderizarCatalogo(estado.itensCatalogo);
        atualizarResumoBarra();
    } catch (e) {
        aviso('error', 'Falha ao carregar catálogo', e.message);
    }
}

function popularSelectCondicoes(ctx) {
    const sel = $('selCondicaoPagto');
    if (!sel) return;

    const condicoes = ctx.condicoes || [];
    const idPadrao = parseInt(ctx.padroes?.idcondicao || 1002, 10);
    estado.condicaoSelecionada = idPadrao;

    let html = '';
    condicoes.forEach((c) => {
        const id = parseInt(c.idcondicao, 10);
        let tag = '';
        if (id === 1002) tag = ' (Padrão Tabela 15)';
        else if (id === 1072) tag = ' (Padrão Cliente)';

        const selAttr = (id === idPadrao) ? 'selected' : '';
        html += `<option value="${id}" ${selAttr}>${id} - ${esc(c.descricao)}${tag}</option>`;
    });

    sel.innerHTML = html;
    atualizarBadgeCondicao();
}

function popularSelectMetodos(ctx) {
    const sel = $('selMetodoPagto');
    if (!sel) return;

    const metodos = ctx.metodos || [];
    const idPadrao = parseInt(ctx.padroes?.idmetodo || 1000, 10);
    estado.metodoSelecionado = idPadrao;

    let html = '';
    metodos.forEach((m) => {
        const id = parseInt(m.idmetodo, 10);
        let tag = '';
        if (id === 1000) tag = ' (Padrão Tabela 15)';
        else if (id === 14) tag = ' (Padrão Cliente)';

        const selAttr = (id === idPadrao) ? 'selected' : '';
        html += `<option value="${id}" ${selAttr}>${id} - ${esc(m.descricao)}${tag}</option>`;
    });

    sel.innerHTML = html;
    atualizarBadgeMetodo();
}

function aoMudarCondicao() {
    const sel = $('selCondicaoPagto');
    if (sel) {
        estado.condicaoSelecionada = parseInt(sel.value, 10);
        atualizarBadgeCondicao();
    }
}

function aoMudarMetodo() {
    const sel = $('selMetodoPagto');
    if (sel) {
        estado.metodoSelecionado = parseInt(sel.value, 10);
        atualizarBadgeMetodo();
    }
}

function definirCondicao(id) {
    const sel = $('selCondicaoPagto');
    if (sel) {
        sel.value = id;
        estado.condicaoSelecionada = parseInt(id, 10);
        atualizarBadgeCondicao();
    }
}

function definirMetodo(id) {
    const sel = $('selMetodoPagto');
    if (sel) {
        sel.value = id;
        estado.metodoSelecionado = parseInt(id, 10);
        atualizarBadgeMetodo();
    }
}

function atualizarBadgeCondicao() {
    const badge = $('badgeCondicaoAtiva');
    if (!badge) return;
    const sel = $('selCondicaoPagto');
    const texto = sel ? sel.options[sel.selectedIndex]?.text || '' : '';
    const cleanTxt = texto.split(' - ')[1] || texto;
    badge.textContent = `Marcada: ${cleanTxt}`;
}

function atualizarBadgeMetodo() {
    const badge = $('badgeMetodoAtivo');
    if (!badge) return;
    const sel = $('selMetodoPagto');
    const texto = sel ? sel.options[sel.selectedIndex]?.text || '' : '';
    const cleanTxt = texto.split(' - ')[1] || texto;
    badge.textContent = `Marcado: ${cleanTxt}`;
}

function filtrarItens() {
    const q = ($('filtroItens').value || '').trim().toLowerCase();
    if (!q) {
        renderizarCatalogo(estado.itensCatalogo);
        return;
    }
    const filtrados = estado.itensCatalogo.filter((it) => {
        return String(it.iditem).toLowerCase().includes(q)
            || String(it.referencia || '').toLowerCase().includes(q)
            || String(it.descricao || '').toLowerCase().includes(q);
    });
    renderizarCatalogo(filtrados);
}

function renderizarCatalogo(itens) {
    const grid = $('gridItens');
    const empty = $('emptyItens');

    if (!itens || !itens.length) {
        grid.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');

    grid.innerHTML = itens.map((it) => {
        const qtdAtual = estado.quantidades[it.iditem] || 0;
        const temQtd = qtdAtual > 0;
        const saldo = Number(it.saldo_estoque || 0);
        const semSaldo = saldo <= 0;

        return `
            <div class="fe-item-card p-4 ${temQtd ? 'has-qty' : ''}" id="card-item-${it.iditem}">
                <div>
                    <!-- FOTO COM BOTÃO DE ZOOM -->
                    <div class="relative bg-slate-50 rounded-2xl p-2 mb-3 text-center overflow-hidden group">
                        <img src="${esc(it.foto_url)}" alt="${esc(it.descricao)}" 
                             class="w-full h-36 object-contain rounded-xl transition duration-300 group-hover:scale-105 cursor-pointer"
                             onclick="abrirModalFoto('${esc(it.foto_url)}', '${esc(it.descricao)}', '${esc(it.referencia)}')"
                             onerror="this.src='https://placehold.co/120x120?text=S/F'">
                        <button type="button" class="absolute bottom-2 right-2 w-8 h-8 rounded-lg bg-white/90 hover:bg-white shadow text-slate-600 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 transition cursor-pointer"
                                onclick="abrirModalFoto('${esc(it.foto_url)}', '${esc(it.descricao)}', '${esc(it.referencia)}')" title="Ampliar Imagem">
                            <i class="fa-solid fa-up-right-and-down-left-from-center"></i>
                        </button>
                    </div>

                    <!-- IDENTIFICAÇÃO DO PRODUTO -->
                    <div class="flex items-center justify-between gap-1 mb-1">
                        <span class="text-[11px] font-extrabold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md">ID: ${it.iditem}</span>
                        <span class="text-[11px] font-bold text-slate-400">Ref: ${esc(it.referencia || '-')}</span>
                    </div>
                    <h3 class="font-extrabold text-xs text-slate-800 line-clamp-2 leading-tight mb-2" title="${esc(it.descricao)}">
                        ${esc(it.descricao)}
                    </h3>
                </div>

                <div>
                    <!-- PREÇO DA TABELA 15 & SALDO EM ESTOQUE -->
                    <div class="pt-2 border-t border-slate-100 mb-3">
                        <div class="flex items-baseline justify-between">
                            <span class="text-[11px] font-bold text-slate-400 uppercase">Preço Tabela 15</span>
                            <span class="text-base font-black text-emerald-600">R$ ${formatMoeda(it.valor_unitario)}</span>
                        </div>
                        <div class="flex items-center justify-between text-[11px] mt-0.5">
                            <span class="font-semibold text-slate-400">Estoque Livre:</span>
                            <span class="font-extrabold ${semSaldo ? 'text-rose-600' : 'text-slate-700'}">
                                ${formatQtd(saldo)} un
                            </span>
                        </div>
                    </div>

                    <!-- CONTROLE DE QUANTIDADE -->
                    <div class="flex items-center justify-between gap-2">
                        <button type="button" class="fe-qty-btn" onclick="alterarQtd(${it.iditem}, -1)">-</button>
                        <input type="number" min="0" max="${saldo}" value="${qtdAtual}" class="fe-qty-inp"
                               id="inp-qtd-${it.iditem}"
                               onchange="definirQtd(${it.iditem}, this.value)">
                        <button type="button" class="fe-qty-btn" onclick="alterarQtd(${it.iditem}, 1)">+</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function alterarQtd(iditem, delta) {
    const it = estado.itensCatalogo.find((x) => x.iditem === iditem);
    if (!it) return;

    const saldo = Number(it.saldo_estoque || 0);
    const atual = Number(estado.quantidades[iditem] || 0);
    let nova = atual + delta;

    if (nova < 0) nova = 0;
    if (nova > saldo) {
        nova = saldo;
        aviso('warning', 'Estoque Limite', `A quantidade não pode ultrapassar o saldo em estoque (${formatQtd(saldo)} un).`);
    }

    if (nova === 0) {
        delete estado.quantidades[iditem];
    } else {
        estado.quantidades[iditem] = nova;
    }

    const inp = $(`inp-qtd-${iditem}`);
    if (inp) inp.value = nova;

    const card = $(`card-item-${iditem}`);
    if (card) {
        if (nova > 0) card.classList.add('has-qty');
        else card.classList.remove('has-qty');
    }

    atualizarResumoBarra();
}

function definirQtd(iditem, valor) {
    const it = estado.itensCatalogo.find((x) => x.iditem === iditem);
    if (!it) return;

    const saldo = Number(it.saldo_estoque || 0);
    let nova = parseInt(valor, 10);

    if (isNaN(nova) || nova < 0) nova = 0;
    if (nova > saldo) {
        nova = saldo;
        aviso('warning', 'Estoque Limite', `A quantidade foi ajustada para o saldo máximo disponível (${formatQtd(saldo)} un).`);
    }

    if (nova === 0) {
        delete estado.quantidades[iditem];
    } else {
        estado.quantidades[iditem] = nova;
    }

    const inp = $(`inp-qtd-${iditem}`);
    if (inp) inp.value = nova;

    const card = $(`card-item-${iditem}`);
    if (card) {
        if (nova > 0) card.classList.add('has-qty');
        else card.classList.remove('has-qty');
    }

    atualizarResumoBarra();
}

function limparCarrinho() {
    estado.quantidades = {};
    document.querySelectorAll('.fe-qty-inp').forEach((inp) => { inp.value = 0; });
    document.querySelectorAll('.fe-item-card').forEach((c) => { c.classList.remove('has-qty'); });
    $('inpObservacao').value = '';
    atualizarResumoBarra();
}

function atualizarResumoBarra() {
    let itensCount = 0;
    let totalUnidades = 0;
    let pesoTotal = 0;
    let valorTotal = 0;

    for (const [iditem, qt] of Object.entries(estado.quantidades)) {
        if (qt > 0) {
            itensCount++;
            totalUnidades += qt;
            const it = estado.itensCatalogo.find((x) => x.iditem === parseInt(iditem, 10));
            if (it) {
                pesoTotal += (Number(it.pesoliquido || 0) * qt);
                valorTotal += (Number(it.valor_unitario || 0) * qt);
            }
        }
    }

    $('barQtdItens').textContent = itensCount;
    $('barTotalUnidades').textContent = formatQtd(totalUnidades);
    $('barPesoTotal').textContent = formatMoeda(pesoTotal);
    $('barValorTotal').textContent = formatMoeda(valorTotal);

    const btn = $('btnEnviarPedido');
    if (btn) {
        btn.disabled = (totalUnidades <= 0);
    }
}

// ==========================================================================
// CRIAÇÃO E ENVIO DO PEDIDO
// ==========================================================================

async function confirmarEnviarPedido() {
    const itensParaEnvio = [];
    let resumoHtml = '<div class="text-left text-xs space-y-1.5 max-h-56 overflow-y-auto mb-3 p-3 bg-slate-50 rounded-2xl">';
    let totalGeral = 0;
    let unidadesGerais = 0;

    for (const [iditem, qt] of Object.entries(estado.quantidades)) {
        if (qt > 0) {
            const it = estado.itensCatalogo.find((x) => x.iditem === parseInt(iditem, 10));
            if (it) {
                const sub = qt * Number(it.valor_unitario || 0);
                totalGeral += sub;
                unidadesGerais += qt;
                itensParaEnvio.push({ iditem: it.iditem, quantidade: qt });
                resumoHtml += `
                    <div class="flex justify-between items-center border-b border-slate-200/60 pb-1">
                        <div>
                            <span class="font-extrabold text-slate-800">${qt}x</span>
                            <span class="text-slate-600">${esc(it.descricao)}</span>
                        </div>
                        <span class="font-bold text-emerald-600">R$ ${formatMoeda(sub)}</span>
                    </div>
                `;
            }
        }
    }
    resumoHtml += '</div>';

    if (!itensParaEnvio.length) {
        aviso('warning', 'Carrinho Vazio', 'Adicione ao menos um item ao pedido antes de enviar.');
        return;
    }

    const obs = ($('inpObservacao').value || '').trim();

    const result = await Swal.fire({
        ...swalBase,
        icon: 'question',
        title: 'Confirmar Pedido E-commerce?',
        html: `
            <div class="p-2.5 mb-3 bg-slate-100/90 rounded-2xl border border-slate-200 text-xs text-left text-slate-700 space-y-1">
                <div><span class="font-bold text-slate-500">Cliente:</span> <b>16736 - AZ PETS CO</b> &bull; Tabela: <b>15 (E-commerce)</b></div>
                <div><span class="font-bold text-slate-500">Regras ERP:</span> <b>Transação 4 (Série 0) &bull; Condição: ${estado.condicaoSelecionada} (${esc(estado.condicaoNome)}) &bull; Método: ${estado.metodoSelecionado} (${esc(estado.metodoNome)})</b></div>
            </div>
            ${resumoHtml}
            <div class="flex justify-between items-center text-sm font-black text-slate-800 px-3 py-1.5 bg-emerald-50 rounded-xl border border-emerald-200">
                <span>Total (${unidadesGerais} un):</span>
                <span class="text-emerald-700 text-base">R$ ${formatMoeda(totalGeral)}</span>
            </div>
            ${obs ? `<p class="text-xs text-slate-600 mt-2 text-left p-2 bg-amber-50 rounded-xl border border-amber-200"><b>Observação do Pedido:</b> ${esc(obs)}</p>` : ''}
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-paper-plane mr-1"></i> Confirmar e Enviar',
        cancelButtonText: 'Revisar'
    });

    if (!result.isConfirmed) return;

    try {
        Swal.fire({
            title: 'Gravando pedido...',
            text: 'Registrando no Palmtop do ERP Nutricional...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        const res = await apiFetch('fluxo-ecomerce/pedidos', 'POST', {
            itens: itensParaEnvio,
            observacao: obs,
            idcondicao: estado.condicaoSelecionada || 1002,
            idmetodo: estado.metodoSelecionado || 1000
        });

        await Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Pedido Gerado com Sucesso!',
            html: `
                <div class="p-4 bg-emerald-50 rounded-2xl text-emerald-900 text-xs text-left mb-2">
                    <p class="text-base font-black text-emerald-700 mb-1">Pedido Palmtop #${res.idpedido}</p>
                    <p><b>Cliente:</b> 16736 - AZ PETS CO</p>
                    <p><b>Itens:</b> ${res.detalhes.itens_count} produtos (${res.detalhes.total_unidades} unidades)</p>
                    <p><b>Valor:</b> R$ ${formatMoeda(res.detalhes.valor_total)}</p>
                </div>
                <p class="text-xs text-slate-400">O pedido está disponível para faturamento imediato no ERP.</p>
            `,
            confirmButtonText: 'OK'
        });

        limparCarrinho();
        carregarContextoEItens();
    } catch (e) {
        aviso('error', 'Falha ao gerar pedido', e.message);
    }
}

// ==========================================================================
// FILTROS RÁPIDOS & CONTROLE DE PERÍODO (MÊS CORRENTE POR PADRÃO)
// ==========================================================================

function formatarDataIso(d) {
    const ano = d.getFullYear();
    const mes = String(d.getMonth() + 1).padStart(2, '0');
    const dia = String(d.getDate()).padStart(2, '0');
    return `${ano}-${mes}-${dia}`;
}

function aplicarFiltroRapido(tipo) {
    estado.filtroRapidoAtivo = tipo;
    const hoje = new Date();
    let dataInicio = '';
    let dataFim = '';
    let labelBadge = '';

    switch (tipo) {
        case 'hoje': {
            dataInicio = formatarDataIso(hoje);
            dataFim = formatarDataIso(hoje);
            labelBadge = 'Hoje';
            break;
        }
        case 'este_mes': {
            const primeiroDia = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
            dataInicio = formatarDataIso(primeiroDia);
            dataFim = formatarDataIso(hoje);
            labelBadge = 'Este Mês';
            break;
        }
        case 'mes_passado': {
            const primeiroDiaMesPassado = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
            const ultimoDiaMesPassado = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
            dataInicio = formatarDataIso(primeiroDiaMesPassado);
            dataFim = formatarDataIso(ultimoDiaMesPassado);
            labelBadge = 'Mês Passado';
            break;
        }
        case 'ultimos_30': {
            const trintaDiasAtras = new Date();
            trintaDiasAtras.setDate(hoje.getDate() - 29);
            dataInicio = formatarDataIso(trintaDiasAtras);
            dataFim = formatarDataIso(hoje);
            labelBadge = 'Últimos 30 dias';
            break;
        }
        case 'este_ano': {
            const primeiroDiaAno = new Date(hoje.getFullYear(), 0, 1);
            dataInicio = formatarDataIso(primeiroDiaAno);
            dataFim = formatarDataIso(hoje);
            labelBadge = 'Este Ano';
            break;
        }
        case 'tudo': {
            dataInicio = '';
            dataFim = '';
            labelBadge = 'Todo o Histórico';
            break;
        }
    }

    $('filtroDataInicio').value = dataInicio;
    $('filtroDataFim').value = dataFim;

    const badge = $('badgePeriodoAtivo');
    if (badge) badge.textContent = labelBadge;

    document.querySelectorAll('.fe-quick-btn').forEach((btn) => {
        if (btn.getAttribute('data-rapido') === tipo) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    atualizarDashboardEHistorico();
}

function aoMudarFiltroManual() {
    estado.filtroRapidoAtivo = 'personalizado';
    document.querySelectorAll('.fe-quick-btn').forEach((btn) => btn.classList.remove('active'));

    const badge = $('badgePeriodoAtivo');
    if (badge) badge.textContent = 'Personalizado';

    atualizarDashboardEHistorico();
}

function atualizarDashboardEHistorico() {
    carregarDashboard();
    carregarHistoricoPedidos();
}

// ==========================================================================
// DASHBOARD & HISTÓRICO ANALÍTICO
// ==========================================================================

async function carregarDashboard() {
    try {
        const dtIni = $('filtroDataInicio').value || '';
        const dtFim = $('filtroDataFim').value || '';
        const status = $('filtroStatus').value || 'todos';

        const params = {
            data_inicio: dtIni,
            data_fim: dtFim,
            status: status
        };

        if (estado.filtroRapidoAtivo === 'tudo' || (!dtIni && !dtFim)) {
            params.historico_completo = '1';
        }

        const res = await apiFetch('fluxo-ecomerce/dashboard', 'GET', params);
        const kpi = res.metricas || {};

        $('kpiTotalPedidos').textContent = kpi.total_pedidos || 0;
        $('kpiFaturamento').textContent = 'R$ ' + formatMoeda(kpi.faturamento_total);
        $('kpiTicketMedio').textContent = 'R$ ' + formatMoeda(kpi.ticket_medio);
        $('kpiPendentes').textContent = kpi.pedidos_pendentes || 0;
        $('kpiValorPendente').textContent = `Total: R$ ${formatMoeda(kpi.valor_pendente)} aguardando ERP`;

        const descPeriodo = $('kpiPeriodoPedidos');
        if (descPeriodo) {
            if (dtIni && dtFim) {
                descPeriodo.textContent = `${dataBR(dtIni)} até ${dataBR(dtFim)}`;
            } else if (dtIni) {
                descPeriodo.textContent = `A partir de ${dataBR(dtIni)}`;
            } else if (dtFim) {
                descPeriodo.textContent = `Até ${dataBR(dtFim)}`;
            } else {
                descPeriodo.textContent = 'Histórico consolidado AZ PETS CO';
            }
        }

        renderizarTopItens(res.top_itens || []);
    } catch (e) {
        console.error('Erro ao carregar dashboard', e);
    }
}

function renderizarTopItens(topItens) {
    const grid = $('gridTopItens');
    if (!topItens || !topItens.length) {
        grid.innerHTML = '<p class="col-span-4 text-center text-xs text-slate-400 py-6">Nenhum item vendido neste período para a AZ PETS CO.</p>';
        return;
    }

    grid.innerHTML = topItens.map((it, idx) => `
        <div class="p-3.5 bg-slate-50 hover:bg-blue-50/50 rounded-2xl border border-slate-200/80 transition flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-2">
                    <span class="w-6 h-6 rounded-lg bg-blue-600 text-white font-black text-xs flex items-center justify-center">#${idx + 1}</span>
                    <span class="text-[11px] font-bold text-slate-400">Ref: ${esc(it.referencia || '-')}</span>
                </div>
                <div class="flex items-center gap-3 mb-2">
                    <img src="${esc(it.foto_url)}" class="w-12 h-12 object-contain rounded-xl bg-white p-1 border border-slate-100"
                         onerror="this.src='https://placehold.co/100x100?text=S/F'">
                    <h4 class="font-bold text-xs text-slate-800 line-clamp-2 leading-tight" title="${esc(it.descricao)}">
                        ${esc(it.descricao)}
                    </h4>
                </div>
            </div>
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-xs font-black">
                <span class="text-blue-700">${formatQtd(it.total_quantidade)} un</span>
                <span class="text-emerald-600">R$ ${formatMoeda(it.total_valor)}</span>
            </div>
        </div>
    `).join('');
}

async function carregarHistoricoPedidos() {
    try {
        const dtIni = $('filtroDataInicio').value || '';
        const dtFim = $('filtroDataFim').value || '';
        const status = $('filtroStatus').value || 'todos';

        const params = {
            data_inicio: dtIni,
            data_fim: dtFim,
            status: status
        };

        if (estado.filtroRapidoAtivo === 'tudo' || (!dtIni && !dtFim)) {
            params.historico_completo = '1';
        }

        const res = await apiFetch('fluxo-ecomerce/pedidos', 'GET', params);
        estado.pedidos = res.pedidos || [];
        filtrarPedidosLocal();
    } catch (e) {
        console.error('Erro ao listar histórico', e);
    }
}

function filtrarPedidosLocal() {
    const q = ($('filtroBuscaPedido').value || '').trim().toLowerCase();
    if (!q) {
        estado.pedidosFiltrados = estado.pedidos;
    } else {
        estado.pedidosFiltrados = estado.pedidos.filter((p) => {
            return String(p.idpedido).toLowerCase().includes(q)
                || String(p.observacao || '').toLowerCase().includes(q)
                || String(p.solicitante || '').toLowerCase().includes(q)
                || String(p.resumo_itens || '').toLowerCase().includes(q);
        });
    }
    renderizarHistorico(estado.pedidosFiltrados);
}

function renderizarHistorico(pedidos) {
    const tbody = $('tbodyHistorico');
    const empty = $('emptyHistorico');

    if (!pedidos || !pedidos.length) {
        tbody.innerHTML = '';
        empty.classList.remove('hidden');
        return;
    }

    empty.classList.add('hidden');

    tbody.innerHTML = pedidos.map((p) => {
        let badgeStatus = '';
        if (p.pendente) {
            badgeStatus = '<span class="fe-badge bg-amber-50 text-amber-700 border border-amber-200"><i class="fa-solid fa-clock mr-1"></i>Pendente Palmtop</span>';
        } else if (p.status_pedido == 5) {
            badgeStatus = '<span class="fe-badge bg-emerald-50 text-emerald-700 border border-emerald-200"><i class="fa-solid fa-check mr-1"></i>Faturado ERP</span>';
        } else {
            badgeStatus = '<span class="fe-badge bg-blue-50 text-blue-700 border border-blue-200"><i class="fa-solid fa-spinner mr-1"></i>Aberto ERP</span>';
        }

        const origemBadge = p.origem === 'PORTAL'
            ? '<span class="px-1.5 py-0.5 rounded text-[10px] bg-indigo-50 text-indigo-700 font-extrabold mr-1">PORTAL</span>'
            : '<span class="px-1.5 py-0.5 rounded text-[10px] bg-slate-100 text-slate-700 font-extrabold mr-1">ERP</span>';

        return `
            <tr class="hover:bg-slate-50/80 transition">
                <td class="p-3.5 font-extrabold text-slate-800">#${p.idpedido}</td>
                <td class="p-3.5 text-slate-600">${dataBR(p.data)}</td>
                <td class="p-3.5">${origemBadge} ${badgeStatus}</td>
                <td class="p-3.5 text-slate-600">${esc(p.solicitante)}</td>
                <td class="p-3.5 text-center font-bold text-slate-700">${p.total_itens} itens (${formatQtd(p.total_unidades)} un)</td>
                <td class="p-3.5 text-right font-black text-emerald-600">R$ ${formatMoeda(p.valortotal)}</td>
                <td class="p-3.5 text-slate-500 max-w-xs truncate" title="${esc(p.observacao)}">${esc(p.observacao || '-')}</td>
                <td class="p-3.5 text-center">
                    <button type="button" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-blue-100 text-slate-700 hover:text-blue-700 font-bold text-xs transition cursor-pointer"
                            onclick="verDetalhesPedido(${p.idpedido})">
                        <i class="fa-solid fa-eye mr-1"></i>Itens
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function verDetalhesPedido(id) {
    try {
        const res = await apiFetch(`fluxo-ecomerce/pedidos/${id}`);
        const p = res.pedido;
        if (!p) return;

        $('modalDetTitulo').textContent = `Pedido #${p.idpedido} (${p.origem})`;
        $('modalDetSubtitulo').textContent = `${p.cliente_nome} • ${dataBR(p.data)}`;
        $('modalDetObs').textContent = p.observacao ? `Obs: ${p.observacao}` : '';
        $('modalDetTotal').textContent = 'R$ ' + formatMoeda(p.valortotal);

        const container = $('modalDetItens');
        container.innerHTML = (p.itens || []).map((it) => `
            <div class="flex items-center justify-between p-3 bg-slate-50 rounded-2xl border border-slate-100">
                <div class="flex items-center gap-3">
                    <img src="${esc(it.foto_url)}" class="w-12 h-12 object-contain rounded-xl bg-white p-1 border border-slate-200"
                         onerror="this.src='https://placehold.co/100x100?text=S/F'">
                    <div>
                        <h4 class="font-bold text-xs text-slate-800 line-clamp-1">${esc(it.descricao)}</h4>
                        <span class="text-[11px] text-slate-400 font-semibold">Ref: ${esc(it.referencia || '-')} • Qtd: <b>${formatQtd(it.qt)} un</b></span>
                    </div>
                </div>
                <div class="text-right">
                    <span class="text-[11px] text-slate-400 block">Unit: R$ ${formatMoeda(it.valor_unitario)}</span>
                    <span class="font-black text-emerald-600 text-xs">R$ ${formatMoeda(it.valortotal)}</span>
                </div>
            </div>
        `).join('');

        $('modalDetalhes').classList.remove('hidden');
    } catch (e) {
        aviso('error', 'Erro ao carregar detalhes', e.message);
    }
}

function fecharModalDetalhes() {
    $('modalDetalhes').classList.add('hidden');
}

// ==========================================================================
// MODAL DE FOTO DO PRODUTO
// ==========================================================================

function abrirModalFoto(url, nome, ref) {
    $('modalFotoImg').src = url;
    $('modalFotoNome').textContent = nome;
    $('modalFotoRef').textContent = ref ? `Referência: ${ref}` : '';
    $('modalFoto').classList.remove('hidden');
}

function fecharModalFoto() {
    $('modalFoto').classList.add('hidden');
}

// ==========================================================================
// EXPORTAÇÃO CSV
// ==========================================================================

function exportarCsvPedidos() {
    const pedidos = estado.pedidosFiltrados || [];
    if (!pedidos.length) {
        aviso('info', 'Sem Dados', 'Não há pedidos para exportar com os filtros atuais.');
        return;
    }

    const headers = ['Pedido', 'Data', 'Origem', 'Status', 'Solicitante', 'Qtd_Itens', 'Total_Unidades', 'Valor_Total', 'Observacao'];
    const rows = pedidos.map((p) => [
        p.idpedido,
        p.data,
        p.origem,
        p.pendente ? 'PENDENTE' : p.status_label,
        `"${String(p.solicitante || '').replace(/"/g, '""')}"`,
        p.total_itens || 0,
        p.total_unidades || 0,
        Number(p.valortotal || 0).toFixed(2),
        `"${String(p.observacao || '').replace(/"/g, '""')}"`
    ]);

    const csvContent = '\uFEFF' + [headers.join(';'), ...rows.map((r) => r.join(';'))].join('\r\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `pedidos_ecomerce_az_pets_${new Date().toISOString().slice(0, 10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================

window.addEventListener('DOMContentLoaded', () => {
    carregarContextoEItens();
});
