/**
 * Portal Nutricional - Transferências de Compras (Matriz <-> Filial)
 * Inteligência de cálculo de necessidade com dedução de estoque e reserva cliente 14523
 */

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

const estado = {
    registros: [],
    pendentes: [],
    timerBusca: null,
    timerPendentes: null,
};

const estadoPrevia = {
    dados: null,
    itens: [],
    filtroTab: 'todos',
    termoBusca: ''
};

// ==========================================================================
// FILTROS & HISTÓRICO
// ==========================================================================

const filtros = () => {
    const p = {};
    if ($('dtIni')?.value) p.inicio = $('dtIni').value;
    if ($('dtFim')?.value) p.fim = $('dtFim').value;
    if ($('busca')?.value.trim()) p.q = $('busca').value.trim();
    return p;
};
const filtrado = () => Object.keys(filtros()).length > 0;

function linhaHistorico(r, i) {
    if (ehMF) {
        return `
        <div class="tc-card p-4 hover:border-sky-300 transition-all cursor-pointer flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3" data-i="${i}">
            <div class="flex items-center gap-3.5 min-w-0">
                <div class="w-11 h-11 rounded-2xl ${r.remessa ? 'bg-purple-50 text-purple-600' : 'bg-sky-50 text-sky-600'} flex items-center justify-center text-lg flex-shrink-0">
                    <i class="fa-solid ${r.remessa ? 'fa-box-open' : 'fa-truck-fast'}"></i>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-extrabold text-slate-800 text-sm truncate">${esc(r.nomeembarque || 'Embarque #' + r.embarque)}</span>
                        <span class="tc-badge ${r.remessa ? 'bg-purple-100 text-purple-700' : 'bg-sky-100 text-sky-700'}">${r.remessa ? 'Remessa (Marca 60)' : 'Embarque Normal'}</span>
                    </div>
                    <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                        <span><b class="text-slate-600">Solicitação:</b> #${esc(r.idcompra)}</span>
                        <span>•</span>
                        <span><b class="text-slate-600">Data:</b> ${dataBR(r.data)}</span>
                        <span>•</span>
                        <span><b class="text-slate-600">Pedidos:</b> ${esc(r.qtpedido ?? 0)}</span>
                    </div>
                </div>
            </div>
            <div class="flex sm:flex-col items-center sm:items-end justify-between w-full sm:w-auto border-t sm:border-t-0 pt-2 sm:pt-0">
                <div class="text-right">
                    <div class="text-xs text-slate-400 font-semibold">Valor da Guia ST</div>
                    <div class="text-base font-black text-sky-700">${brl(r.valor_guia)}</div>
                </div>
                ${Number(r.erros_calculo || 0) > 0 ? '<span class="tc-badge bg-rose-100 text-rose-700 mt-1"><i class="fa-solid fa-triangle-exclamation"></i> Erro cálculo</span>' : ''}
            </div>
        </div>`;
    }
    return `
    <div class="tc-card p-4 hover:border-teal-300 transition-all cursor-pointer flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3" data-i="${i}">
        <div class="flex items-center gap-3.5 min-w-0">
            <div class="w-11 h-11 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-lg flex-shrink-0">
                <i class="fa-solid fa-file-invoice-dollar"></i>
            </div>
            <div class="min-w-0">
                <div class="font-extrabold text-slate-800 text-sm truncate">${esc(r.fornecedor || 'Fornecedor não informado')}</div>
                <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-2 flex-wrap">
                    <span><b class="text-slate-600">Solicitação:</b> #${esc(r.idcompra)}</span>
                    <span>•</span>
                    <span><b class="text-slate-600">Ordem:</b> #${esc(r.ordem)}</span>
                    <span>•</span>
                    <span><b class="text-slate-600">Data:</b> ${dataBR(r.data)}</span>
                    <span>•</span>
                    <span>${num(r.quantidade)} un (${num(r.peso)} kg)</span>
                </div>
            </div>
        </div>
        <div class="text-right w-full sm:w-auto border-t sm:border-t-0 pt-2 sm:pt-0">
            <div class="text-xs text-slate-400 font-semibold">Valor Total</div>
            <div class="text-base font-black text-teal-700">${brl(r.valor)}</div>
        </div>
    </div>`;
}

async function carregar() {
    const lista = $('lista');
    if (!lista) return;
    lista.innerHTML = `
        <div class="py-8 text-center text-sm text-slate-400">
            <i class="fa-solid fa-spinner fa-spin text-lg mr-2 text-sky-600"></i> Carregando registros...
        </div>`;

    try {
        const r = await apiFetch(base, 'GET', { ...filtros(), limite: filtrado() ? 500 : 15 });
        estado.registros = r.registros || [];

        $('rotulo').textContent = filtrado() ? `Filtrado (${r.quantidade} encontrados)` : 'Últimos registros';
        $('totais').textContent = `${r.quantidade} solicitação(ões)`;

        // Atualizar KPIs de topo
        if ($('kpiSolicitacoes')) $('kpiSolicitacoes').textContent = r.quantidade || 0;
        if ($('kpiSolicitacoesSub')) $('kpiSolicitacoesSub').textContent = filtrado() ? 'Filtrado' : 'Últimas 15 solicitações';
        if ($('kpiValorTotal')) $('kpiValorTotal').textContent = ehMF ? brl(r.total_guia) : brl(r.total_valor);

        lista.innerHTML = estado.registros.length
            ? estado.registros.map(linhaHistorico).join('')
            : '<div class="tc-card p-8 text-center text-sm text-slate-400">Nenhum registro encontrado no período informado.</div>';
    } catch (e) {
        lista.innerHTML = `<div class="tc-card p-6 text-center text-sm text-red-500"><i class="fa-solid fa-circle-exclamation mr-1.5"></i> ${esc(e.message)}</div>`;
    }
}

// ==========================================================================
// EMBARQUES PENDENTES (MATRIZ -> FILIAL)
// ==========================================================================

async function carregarPendentes() {
    const container = $('listaPendentes');
    if (!container) return;

    const termo = $('buscaPendentes')?.value.trim() || '';
    const todos = $('chkTodosEmbarques')?.checked ? 1 : 0;

    container.innerHTML = `
        <div class="col-span-full py-8 text-center text-sm text-slate-400">
            <i class="fa-solid fa-spinner fa-spin text-lg mr-2 text-sky-600"></i> Buscando embarques da filial PR...
        </div>`;

    try {
        const queryParams = { faturados: todos };
        if (termo) queryParams.q = termo;

        const r = await apiFetch('transf-compras/matriz-filial/pendentes', 'GET', queryParams);
        estado.pendentes = r.registros || [];

        const qtdTotal = estado.pendentes.length;
        const qtdNaoSolicitados = estado.pendentes.filter(p => !p.ja_solicitado).length;

        if ($('badgeQtdPendentes')) {
            $('badgeQtdPendentes').textContent = `${qtdNaoSolicitados} aguardando`;
            $('badgeQtdPendentes').className = `tc-badge ${qtdNaoSolicitados > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`;
        }
        if ($('kpiPendentes')) {
            $('kpiPendentes').textContent = qtdNaoSolicitados;
        }

        if (!qtdTotal) {
            container.innerHTML = `
                <div class="col-span-full p-8 text-center tc-card border-dashed">
                    <i class="fa-solid fa-circle-check text-3xl text-emerald-500 mb-2 block"></i>
                    <div class="font-extrabold text-slate-700 text-sm">Nenhum embarque pendente no momento!</div>
                    <p class="text-xs text-slate-400 mt-1">Todos os embarques já foram solicitados ou não há novos pedidos na Filial PR.</p>
                </div>`;
            return;
        }

        container.innerHTML = estado.pendentes.map((p) => {
            const jaSol = p.ja_solicitado;
            const badgeSol = jaSol
                ? `<span class="tc-badge bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-check-double text-[10px]"></i> Solicitação #${p.idsolicitacao}</span>`
                : `<span class="tc-badge bg-amber-100 text-amber-800 border border-amber-200"><i class="fa-solid fa-clock text-[10px]"></i> Aguardando Solicitação</span>`;

            const badgeFaturado = p.faturado === 'S'
                ? `<span class="tc-badge bg-slate-100 text-slate-600 text-[10px]">Faturado</span>`
                : `<span class="tc-badge bg-sky-100 text-sky-700 text-[10px]">Não Faturado</span>`;

            return `
            <div class="tc-card p-4 hover:shadow-md transition-all flex flex-col justify-between border-slate-200/80">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2.5 py-1 rounded-xl bg-slate-900 text-white font-black text-xs">
                            #${esc(p.embarque)}
                        </span>
                        <div class="flex items-center gap-1.5">
                            ${badgeFaturado}
                            ${badgeSol}
                        </div>
                    </div>
                    
                    <div class="font-bold text-slate-800 text-sm mb-1 truncate" title="${esc(p.nome_embarque)}">
                        ${esc(p.nome_embarque || 'Embarque ' + p.embarque)}
                    </div>
                    
                    <div class="text-xs text-slate-500 space-y-0.5 mb-3 bg-slate-50 p-2 rounded-xl">
                        <div class="flex justify-between">
                            <span>Placa Veículo:</span>
                            <b class="text-slate-700 uppercase">${esc(p.placa || 'Sem Placa')}</b>
                        </div>
                        <div class="flex justify-between">
                            <span>Data do Embarque:</span>
                            <b class="text-slate-700">${dataBR(p.data)}</b>
                        </div>
                        <div class="flex justify-between">
                            <span>Pedidos / Carga:</span>
                            <b class="text-slate-700">${esc(p.qt_pedido)} pedido(s) · ${num(p.peso)} kg</b>
                        </div>
                    </div>
                </div>

                ${jaSol ? `
                    <button onclick="abrirModalPrevia(${p.embarque})" class="tc-btn-secondary w-full text-xs flex items-center justify-center gap-2 !py-2.5">
                        <i class="fa-solid fa-calculator"></i>
                        <span>Ver Análise (Solicitação #${p.idsolicitacao})</span>
                    </button>
                ` : `
                    <div class="grid grid-cols-2 gap-2">
                        <button onclick="abrirModalPrevia(${p.embarque})" class="tc-btn-secondary text-xs flex items-center justify-center gap-1.5 !py-2.5" title="Ver cálculo detalhado item a item">
                            <i class="fa-solid fa-calculator"></i> Prévia
                        </button>
                        <button onclick="gerarSolicitacaoDireta(${p.embarque})" class="tc-btn-primary text-xs flex items-center justify-center gap-1.5 !py-2.5 !bg-emerald-600 hover:!bg-emerald-700 font-black shadow-sm" title="Gerar solicitação no ERP">
                            <i class="fa-solid fa-file-circle-plus"></i> Gerar no ERP
                        </button>
                    </div>
                `}
            </div>`;
        }).join('');

    } catch (e) {
        container.innerHTML = `<div class="col-span-full tc-card p-6 text-center text-sm text-red-500"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i> ${esc(e.message)}</div>`;
    }
}

// ==========================================================================
// MODAL DE PRÉVIA & ANÁLISE DE NECESSIDADE
// ==========================================================================

async function abrirModalPrevia(embarqueId) {
    const modal = $('modalPrevia');
    if (!modal) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    $('previaTitulo').textContent = `Embarque #${embarqueId}`;
    $('previaSubtitulo').textContent = 'Consultando estoque filial PR e pedidos do cliente 14523...';
    $('previaCorpoTabela').innerHTML = `
        <tr>
            <td colspan="8" class="text-center py-12 text-slate-400">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-sky-600 block mb-2"></i>
                Calculando necessidade de transferência...
            </td>
        </tr>`;

    try {
        const r = await apiFetch(`transf-compras/matriz-filial/previa/${embarqueId}`);
        estadoPrevia.dados = r;
        estadoPrevia.itens = r.itens || [];
        estadoPrevia.filtroTab = 'todos';
        estadoPrevia.termoBusca = '';

        const emb = r.embarque;
        const res = r.resumo;

        $('previaTitulo').textContent = `Embarque #${emb.idembarque} — ${emb.observacao || 'Filial PR'}`;
        $('previaSubtitulo').textContent = `Placa: ${emb.placa || 'Sem placa'} · Data: ${dataBR(emb.data)} · ${emb.qt_pedido} pedidos · ${num(emb.totalpesoliquido)} kg`;

        // Badge de status
        const badgeStatus = $('previaBadgeStatus');
        if (badgeStatus) {
            if (res.ja_solicitado) {
                const idSol = r.solicitacoes_existentes[0]?.idsolicitacaocompra || '';
                badgeStatus.className = 'tc-badge bg-emerald-400/20 text-emerald-300 border border-emerald-400/30';
                badgeStatus.innerHTML = `<i class="fa-solid fa-check"></i> Solicitado (#${idSol})`;
            } else {
                badgeStatus.className = 'tc-badge bg-amber-400/20 text-amber-300 border border-amber-400/30';
                badgeStatus.innerHTML = `<i class="fa-solid fa-clock"></i> Pronto para Solicitar`;
            }
        }

        // Aviso da reserva 14523
        const avisoReserva = $('previaAvisoReserva');
        if (avisoReserva) {
            avisoReserva.textContent = `${res.itens_ajustados_reserva} item(ns) com estoque reservado`;
        }

        // KPIs da Modal
        $('previaKpiTotalItens').textContent = res.total_itens;
        $('previaKpiTotalSolicitar').textContent = `${num(res.qtd_total_solicitar)} un`;
        $('previaKpiItensFalta').textContent = `${res.itens_a_solicitar} item(ns) com necessidade`;

        $('previaKpiNormais').textContent = `${num(res.normais_unidades)} un`;
        $('previaKpiNormaisItens').textContent = `${res.normais_itens} item(ns) comerciais`;

        $('previaKpiRemessas').textContent = `${num(res.remessa_unidades)} un`;
        $('previaKpiRemessasItens').textContent = `${res.remessa_itens} item(ns) de apoio/amostra`;

        // Botão de gerar solicitação na modal
        const btnGerar = $('btnGerarSolicitacaoPrevia');
        if (btnGerar) {
            if (res.ja_solicitado) {
                btnGerar.style.display = 'none';
            } else if (res.qtd_total_solicitar > 0) {
                btnGerar.style.display = 'inline-flex';
                btnGerar.disabled = false;
                btnGerar.className = 'tc-btn-primary text-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none !bg-emerald-600 hover:!bg-emerald-700 font-black shadow-md shadow-emerald-600/25';
                btnGerar.innerHTML = `<i class="fa-solid fa-file-circle-plus text-sm"></i> Gerar Solicitação no ERP (${num(res.qtd_total_solicitar)} un)`;
            } else {
                btnGerar.style.display = 'inline-flex';
                btnGerar.disabled = true;
                btnGerar.className = 'tc-btn-secondary text-xs flex items-center justify-center gap-1.5 flex-1 sm:flex-none opacity-60 cursor-not-allowed';
                btnGerar.innerHTML = `<i class="fa-solid fa-check"></i> Estoque PR Suficiente`;
            }
        }

        // Resetar campo de busca do modal
        if ($('buscaItemPrevia')) $('buscaItemPrevia').value = '';

        filtrarItensPrevia('todos');

    } catch (e) {
        $('previaCorpoTabela').innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-8 text-rose-500 font-bold">
                    <i class="fa-solid fa-triangle-exclamation mr-1.5"></i> Erro ao carregar prévia: ${esc(e.message)}
                </td>
            </tr>`;
    }
}

function fecharModalPrevia() {
    const modal = $('modalPrevia');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = '';
}

function filtrarItensPrevia(tab) {
    estadoPrevia.filtroTab = tab;

    // Atualizar classes das tabs
    ['todos', 'solicitar', 'normais', 'remessa'].forEach(t => {
        const btn = $(`tabPrevia_${t}`);
        if (btn) {
            if (t === tab) btn.classList.add('active');
            else btn.classList.remove('active');
        }
    });

    renderizarTabelaPrevia();
}

function renderizarTabelaPrevia() {
    const tbody = $('previaCorpoTabela');
    if (!tbody) return;

    const termo = ($('buscaItemPrevia')?.value || '').toLowerCase().trim();
    const tab = estadoPrevia.filtroTab;

    // Contadores para as tabs
    const todosCount = estadoPrevia.itens.length;
    const solicitarCount = estadoPrevia.itens.filter(i => i.qtd_solicitar > 0).length;
    const normaisCount = estadoPrevia.itens.filter(i => !i.is_remessa).length;
    const remessaCount = estadoPrevia.itens.filter(i => i.is_remessa).length;

    if ($('countPrevia_todos')) $('countPrevia_todos').textContent = todosCount;
    if ($('countPrevia_solicitar')) $('countPrevia_solicitar').textContent = solicitarCount;
    if ($('countPrevia_normais')) $('countPrevia_normais').textContent = normaisCount;
    if ($('countPrevia_remessa')) $('countPrevia_remessa').textContent = remessaCount;

    // Filtragem dos itens exibidos
    let itensFiltrados = estadoPrevia.itens.filter(it => {
        // Filtro da aba
        if (tab === 'solicitar' && it.qtd_solicitar <= 0) return false;
        if (tab === 'normais' && it.is_remessa) return false;
        if (tab === 'remessa' && !it.is_remessa) return false;

        // Filtro de busca textual
        if (termo) {
            const buscaNoItem = `${it.iditem} ${it.referencia || ''} ${it.descricao || ''} ${it.marca || ''}`.toLowerCase();
            if (!buscaNoItem.includes(termo)) return false;
        }

        return true;
    });

    if (!itensFiltrados.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="text-center py-10 text-slate-400">
                    Nenhum item corresponde aos critérios de filtro.
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = itensFiltrados.map((it, idx) => {
        const ehNecessario = it.qtd_solicitar > 0;
        const temReserva = it.ajustado_reserva;

        const badgeTipo = it.is_remessa
            ? '<span class="tc-badge bg-purple-100 text-purple-700 border border-purple-200">Remessa (60)</span>'
            : '<span class="tc-badge bg-slate-100 text-slate-600">Normal</span>';

        const badgeReserva = temReserva
            ? `<span class="tc-badge bg-indigo-100 text-indigo-700 border border-indigo-200 text-[10px]" title="Reserva de ${it.reserva_14523} un protegida pelo pedido aberto do cliente 14523">
                 <i class="fa-solid fa-lock text-[9px]"></i> ${num(it.reserva_14523)}
               </span>`
            : `<span class="text-slate-400 text-xs">0</span>`;

        const badgeSolicitar = ehNecessario
            ? `<span class="tc-badge bg-sky-100 text-sky-800 border border-sky-300 font-black text-xs px-2.5 py-1">
                 <i class="fa-solid fa-arrow-down-to-bracket text-[10px]"></i> ${num(it.qtd_solicitar)} ${esc(it.unidade || 'UN')}
               </span>`
            : `<span class="text-emerald-700 text-xs font-bold flex items-center justify-end gap-1"><i class="fa-solid fa-check text-[10px]"></i> Suficiente</span>`;

        const bgLinha = ehNecessario ? 'hover:bg-sky-50/50' : 'opacity-80 hover:bg-slate-50';

        return `
        <tr class="${bgLinha} transition-colors">
            <td class="text-slate-400 text-xs font-mono">${idx + 1}</td>
            <td>
                <div class="font-extrabold text-slate-800 text-sm leading-tight">${esc(it.descricao)}</div>
                <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-2">
                    <span class="font-mono text-slate-600 font-bold">#${esc(it.iditem)}</span>
                    ${it.referencia ? `<span>• Ref: ${esc(it.referencia)}</span>` : ''}
                    ${it.marca ? `<span>• Marca: ${esc(it.marca)}</span>` : ''}
                </div>
            </td>
            <td>${badgeTipo}</td>
            <td class="text-right font-bold text-slate-800">${num(it.qtd_embarque)} <span class="text-[10px] text-slate-400">${esc(it.unidade || '')}</span></td>
            <td class="text-right font-medium text-slate-600">${num(it.estoque_pr)}</td>
            <td class="text-right">${badgeReserva}</td>
            <td class="text-right font-medium text-slate-600">${num(it.estoque_livre_pr)}</td>
            <td class="text-right">${badgeSolicitar}</td>
        </tr>`;
    }).join('');
}

function copiarResumoPrevia() {
    if (!estadoPrevia.dados) return;
    const r = estadoPrevia.dados;
    const emb = r.embarque;
    const res = r.resumo;

    let texto = `*PRÉVIA DE TRANSFERÊNCIA - MATRIZ -> FILIAL PR*\n`;
    texto += `Embarque: #${emb.idembarque} (${emb.observacao || ''})\n`;
    texto += `Placa: ${emb.placa || 'Sem placa'} | Data: ${dataBR(emb.data)} | Pedidos: ${emb.qt_pedido}\n`;
    texto += `--------------------------------------------------\n`;
    texto += `Itens a Solicitar: ${res.itens_a_solicitar} de ${res.total_itens}\n`;
    texto += `Total a Transferir: ${num(res.qtd_total_solicitar)} unidades\n`;
    texto += ` - Normais: ${res.normais_itens} itens (${num(res.normais_unidades)} un)\n`;
    texto += ` - Remessas (Marca 60): ${res.remessa_itens} itens (${num(res.remessa_unidades)} un)\n`;
    texto += ` - Itens com reserva protegida (14523): ${res.itens_ajustados_reserva}\n`;
    texto += `--------------------------------------------------\n`;
    texto += `ITENS COM NECESSIDADE:\n`;

    const aSolicitar = estadoPrevia.itens.filter(i => i.qtd_solicitar > 0);
    aSolicitar.forEach((it, idx) => {
        texto += `${idx + 1}. [${it.referencia || it.iditem}] ${it.descricao}: Solicitar ${num(it.qtd_solicitar)} ${it.unidade || 'UN'} (Emb: ${num(it.qtd_embarque)} | Est Livre: ${num(it.estoque_livre_pr)})${it.is_remessa ? ' [REMESSA]' : ''}\n`;
    });

    navigator.clipboard.writeText(texto).then(() => {
        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Resumo Copiado!',
            text: 'O resumo da transferência foi copiado para a área de transferência.',
            timer: 2000,
            showConfirmButton: false
        });
    }).catch(() => {
        Swal.fire({ ...swalBase, icon: 'info', title: 'Copie manualmente', html: `<textarea class="w-full text-xs p-2 border rounded" rows="8">${texto}</textarea>` });
    });
}

// ==========================================================================
// AÇÕES DE CRIAÇÃO / INSERT DE SOLICITAÇÃO NO ERP
// ==========================================================================

async function gerarSolicitacaoPreviaModal() {
    if (!estadoPrevia.dados || !estadoPrevia.dados.embarque) return;
    const emb = estadoPrevia.dados.embarque;
    const res = estadoPrevia.dados.resumo;
    await executarGeracaoMatrizFilial(emb.idembarque, emb.placa, res.itens_a_solicitar, res.qtd_total_solicitar);
}

async function gerarSolicitacaoDireta(embarqueId) {
    const p = estado.pendentes.find(x => x.embarque == embarqueId);
    const placa = p ? p.placa : '';
    await executarGeracaoMatrizFilial(embarqueId, placa);
}

async function executarGeracaoMatrizFilial(embarqueId, placa = '', itensNecessarios = null, unidadesTotal = null) {
    let detalheTexto = `Confirma a geração da Solicitação de Transferência no ERP para o <b>Embarque #${embarqueId}</b> (Placa: ${placa || 'Sem placa'})?`;
    if (itensNecessarios !== null && unidadesTotal !== null) {
        detalheTexto += `<br><span class="text-xs text-slate-500 mt-2 block">Serão inseridos <b>${itensNecessarios} item(ns)</b> totalizando <b>${num(unidadesTotal)} unidade(s)</b> calculadas para suprir a carga da Filial PR.</span>`;
    }

    const conf = await Swal.fire({
        ...swalBase,
        title: 'Gerar Solicitação no ERP?',
        html: detalheTexto,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-file-circle-plus mr-1"></i> Sim, Gerar Solicitação',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#059669',
        cancelButtonColor: '#64748b',
    });

    if (!conf.isConfirmed) return;

    Swal.fire({
        ...swalBase,
        title: 'Gerando solicitação...',
        html: '<div class="p-3 text-center text-slate-500"><i class="fa-solid fa-circle-notch fa-spin text-3xl text-emerald-600 mb-2"></i><p class="text-xs">Gravando registros em solicitacao_compra e solicitacao_compra_item no ERP...</p></div>',
        allowOutsideClick: false,
        showConfirmButton: false,
    });

    try {
        const resp = await apiFetch('transf-compras/matriz-filial/gerar', 'POST', { idembarque: embarqueId });
        
        let msgHtml = '';
        if (resp.tem_remessa) {
            msgHtml = `
                <div class="text-left bg-slate-50 p-3.5 rounded-2xl border border-slate-200 text-xs space-y-2 text-slate-700">
                    <div class="font-extrabold text-slate-800 text-sm border-b pb-1.5 flex items-center justify-between">
                        <span>2 Solicitações Criadas no ERP</span>
                        <span class="tc-badge bg-emerald-100 text-emerald-800">Sucesso</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-gift text-purple-600"></i> <b>Remessa (Marca 60):</b></span>
                        <span class="tc-badge bg-purple-100 text-purple-800 font-mono font-bold">#${resp.idsolicitacao_remessa} (${resp.total_itens_remessa} itens)</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-box text-sky-600"></i> <b>Embarque Normal:</b></span>
                        <span class="tc-badge bg-sky-100 text-sky-800 font-mono font-bold">#${resp.idsolicitacao_normal} (${resp.total_itens_normais} itens)</span>
                    </div>
                    <div class="text-[11px] text-slate-500 pt-1 border-t text-right">
                        Total transferido: <b>${num(resp.total_unidades)} unidades</b>
                    </div>
                </div>`;
        } else {
            msgHtml = `
                <div class="text-center text-sm font-semibold text-slate-700">
                    A <b>Solicitação #${resp.idsolicitacao}</b> foi gerada no ERP!<br>
                    <span class="text-xs text-slate-500 mt-1 block">${resp.total_itens} item(ns) · ${num(resp.total_unidades)} unidades</span>
                </div>`;
        }

        await Swal.fire({
            ...swalBase,
            icon: 'success',
            title: resp.tem_remessa ? 'Solicitações Geradas!' : 'Solicitação Criada!',
            html: msgHtml,
            confirmButtonText: 'OK',
            confirmButtonColor: '#059669'
        });

        // Recarrega listagens
        if (ehMF) {
            carregarPendentes();
            carregar();
            if ($('modalPrevia') && !$('modalPrevia').classList.contains('hidden')) {
                abrirModalPrevia(embarqueId);
            }
        }
    } catch (e) {
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Erro ao Gerar Solicitação',
            text: e.message || 'Falha ao registrar solicitação no ERP.'
        });
    }
}

// ==========================================================================
// ORDENS PENDENTES (FILIAL PR -> MATRIZ)
// ==========================================================================

async function carregarOrdensPendentes() {
    const container = $('listaOrdensPendentes');
    if (!container) return;

    const termo = ($('buscaOrdensPendentes')?.value || '').toLowerCase().trim();

    container.innerHTML = `
        <div class="col-span-full py-8 text-center text-sm text-slate-400">
            <i class="fa-solid fa-spinner fa-spin text-lg mr-2 text-teal-600"></i> Buscando ordens de compra...
        </div>`;

    try {
        const r = await apiFetch('transf-compras/filial-matriz/pendentes', 'GET');
        let ordens = r.registros || [];

        if (termo) {
            ordens = ordens.filter(o => 
                String(o.ordem).includes(termo) || 
                (o.fornecedor || '').toLowerCase().includes(termo)
            );
        }

        const qtdNaoSolicitadas = ordens.filter(o => !o.ja_solicitado).length;

        if ($('badgeQtdOrdensPendentes')) {
            $('badgeQtdOrdensPendentes').textContent = `${qtdNaoSolicitadas} pendentes`;
            $('badgeQtdOrdensPendentes').className = `tc-badge ${qtdNaoSolicitadas > 0 ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-600'}`;
        }
        if ($('kpiPendentes')) {
            $('kpiPendentes').textContent = qtdNaoSolicitadas;
        }

        if (!ordens.length) {
            container.innerHTML = `
                <div class="col-span-full p-8 text-center tc-card border-dashed">
                    <i class="fa-solid fa-circle-check text-3xl text-teal-500 mb-2 block"></i>
                    <div class="font-extrabold text-slate-700 text-sm">Nenhuma ordem de compra pendente no momento!</div>
                    <p class="text-xs text-slate-400 mt-1">Todas as ordens já foram solicitadas ou não há novos pedidos para transferir.</p>
                </div>`;
            return;
        }

        container.innerHTML = ordens.map(o => {
            const jaSol = o.ja_solicitado;
            const badgeSol = jaSol
                ? `<span class="tc-badge bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-check-double text-[10px]"></i> Solicitação #${o.idsolicitacao}</span>`
                : `<span class="tc-badge bg-amber-100 text-amber-800 border border-amber-200"><i class="fa-solid fa-clock text-[10px]"></i> Aguardando Solicitação</span>`;

            return `
            <div class="tc-card p-4 hover:shadow-md transition-all flex flex-col justify-between border-slate-200/80">
                <div>
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="px-2.5 py-1 rounded-xl bg-slate-900 text-white font-black text-xs">
                            Ordem #${esc(o.ordem)}
                        </span>
                        ${badgeSol}
                    </div>

                    <div class="font-bold text-slate-800 text-sm mb-1 truncate" title="${esc(o.fornecedor)}">
                        ${esc(o.fornecedor || 'Fornecedor não informado')}
                    </div>

                    <div class="text-xs text-slate-500 space-y-0.5 mb-3 bg-slate-50 p-2 rounded-xl">
                        <div class="flex justify-between">
                            <span>Data da Ordem:</span>
                            <b class="text-slate-700">${dataBR(o.data)}</b>
                        </div>
                        <div class="flex justify-between">
                            <span>Quantidade / Peso:</span>
                            <b class="text-slate-700">${num(o.quantidade)} un · ${num(o.peso)} kg</b>
                        </div>
                        <div class="flex justify-between">
                            <span>Valor Total:</span>
                            <b class="text-teal-700 font-extrabold">${brl(o.valor)}</b>
                        </div>
                    </div>
                </div>

                ${jaSol ? `
                    <button class="tc-btn-secondary w-full text-xs flex items-center justify-center gap-1.5 !py-2.5 opacity-70 cursor-default" disabled>
                        <i class="fa-solid fa-check"></i> Solicitação #${o.idsolicitacao} Criada
                    </button>
                ` : `
                    <button onclick="gerarSolicitacaoOrdem(${o.ordem})" class="tc-btn-primary w-full text-xs flex items-center justify-center gap-1.5 !py-2.5 !bg-teal-600 hover:!bg-teal-700 font-black shadow-sm">
                        <i class="fa-solid fa-file-circle-plus"></i> Gerar Solicitação no ERP
                    </button>
                `}
            </div>`;
        }).join('');

    } catch (e) {
        container.innerHTML = `<div class="col-span-full tc-card p-6 text-center text-sm text-red-500"><i class="fa-solid fa-triangle-exclamation mr-1.5"></i> ${esc(e.message)}</div>`;
    }
}

async function gerarSolicitacaoOrdem(idoc) {
    const conf = await Swal.fire({
        ...swalBase,
        title: 'Gerar Solicitação Filial PR → Matriz?',
        html: `Confirma a geração da solicitação de transferência para a <b>Ordem de Compra #${idoc}</b> no ERP?<br><span class="text-xs text-slate-500 mt-2 block">Os itens da ordem serão inseridos automaticamente em solicitacao_compra e solicitacao_compra_item.</span>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-file-circle-plus mr-1"></i> Sim, Gerar no ERP',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#64748b'
    });

    if (!conf.isConfirmed) return;

    Swal.fire({
        ...swalBase,
        title: 'Gerando solicitação...',
        html: '<div class="p-3 text-center text-slate-500"><i class="fa-solid fa-circle-notch fa-spin text-3xl text-teal-600 mb-2"></i><p class="text-xs">Gravando registros no ERP...</p></div>',
        allowOutsideClick: false,
        showConfirmButton: false
    });

    try {
        const resp = await apiFetch('transf-compras/filial-matriz/gerar', 'POST', { idoc });
        
        await Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Solicitação Criada!',
            html: `<div class="text-center text-sm font-semibold text-slate-700">
                     A <b>Solicitação #${resp.idsolicitacao}</b> foi gerada com sucesso no ERP!<br>
                     <span class="text-xs text-slate-500 mt-1 block">${resp.total_itens} item(ns) vinculados</span>
                   </div>`,
            confirmButtonText: 'OK',
            confirmButtonColor: '#0d9488'
        });

        carregarOrdensPendentes();
        carregar();
    } catch (e) {
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Erro ao Gerar Solicitação',
            text: e.message || 'Falha ao registrar solicitação no ERP.'
        });
    }
}

// ==========================================================================
// DETALHE DE SOLICITAÇÃO (SWEETALERT MEMÓRIA FISCAL GUIA ST)
// ==========================================================================

function detalhe(r) {
    const html = ehMF
        ? `
        <div class="space-y-2 text-xs text-slate-700">
            <div class="grid grid-cols-2 gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                <div><b>Solicitação:</b> #${esc(r.idcompra)}</div>
                <div><b>${r.remessa ? 'Remessa' : 'Embarque'}:</b> #${esc(r.embarque)}</div>
                <div><b>Data Solicitação:</b> ${dataBR(r.data)}</div>
                <div><b>Qtd Pedidos:</b> ${esc(r.qtpedido ?? 0)}</div>
                <div><b>Base Total NCM:</b> ${brl(r.valor_st)}</div>
                <div><b>Valor Guia ST:</b> <span class="font-extrabold text-sky-700">${brl(r.valor_guia)}</span></div>
            </div>

            ${Number(r.erros_calculo || 0) ? `<div class="p-2 rounded-xl bg-red-50 text-red-700 font-bold border border-red-200"><i class="fa-solid fa-triangle-exclamation"></i> ${esc(r.erros_calculo)} item(ns) não calculados por divisão inválida.</div>` : ''}

            <div class="pt-2 font-black text-slate-800 text-sm flex items-center justify-between">
                <span>Memória de Cálculo por NCM</span>
                <span class="text-slate-400 font-normal text-xs">${(r.detalhes_ncm || []).length} NCM(s)</span>
            </div>

            ${(r.detalhes_ncm || []).length ? '' : '<div class="text-slate-400 text-center py-4">Nenhum item faturado de NCM com regra na guia.</div>'}

            <div class="max-h-60 overflow-y-auto space-y-2 pr-1">
                ${(r.detalhes_ncm || []).map((n) => `
                <div class="rounded-xl bg-slate-50 p-2.5 border border-slate-200/80">
                    <div class="flex items-center justify-between font-bold text-slate-800 mb-1">
                        <span>NCM ${esc(n.ncm)}</span>
                        <span>Base: ${brl(n.base)}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 space-y-0.5">
                        <div>Base ICMS-ST (MVA ${num(n.mva_percentual, 4)}% ${esc(n.operador_mva)}): <b>${brl(n.base_ajustada)}</b> · ICMS-ST Bruto: <b>${brl(n.icms_st_bruto)}</b></div>
                        <div>Crédito Interestadual (${num(n.credito_interestadual_percentual, 4)}%): <b>${brl(n.credito_origem)}</b></div>
                        <div class="pt-1 border-t border-slate-200 flex justify-between font-bold text-sky-800">
                            <span>Valor da Guia (ST ${esc(n.operador_guia)} Crédito):</span>
                            <span>${n.erro_calculo ? '<span class="text-red-600">Erro na fórmula</span>' : brl(n.valor_guia)}</span>
                        </div>
                    </div>
                </div>`).join('')}
            </div>
        </div>`
        : `
        <div class="space-y-2 text-xs text-slate-700 bg-slate-50 p-3 rounded-xl border border-slate-200">
            <div><b>Solicitação:</b> #${esc(r.idcompra)}</div>
            <div><b>Ordem de Compra:</b> #${esc(r.ordem)}</div>
            <div><b>Fornecedor:</b> ${esc(r.fornecedor)}</div>
            <div><b>Data:</b> ${dataBR(r.data)}</div>
            <div><b>Quantidade:</b> ${num(r.quantidade)} un</div>
            <div><b>Peso Líquido:</b> ${num(r.peso)} kg</div>
            <div class="text-sm font-extrabold text-teal-800 pt-1 border-t"><b>Valor Total:</b> ${brl(r.valor)}</div>
        </div>`;

    Swal.fire({
        ...swalBase,
        title: `Detalhes da Solicitação #${r.idcompra}`,
        html: `<div class="text-left">${html}</div>`,
        confirmButtonText: 'Fechar',
        width: '600px'
    });
}

// ==========================================================================
// EXPORTAÇÃO CSV
// ==========================================================================

async function baixar() {
    try {
        const r = await apiFetch(base, 'GET', { ...filtros(), limite: 2000 });
        if (!r.registros?.length) {
            return Swal.fire({ ...swalBase, icon: 'info', title: 'Sem dados', text: 'Não há registros para exportar.' });
        }
        const dec = (v) => Number(v ?? 0).toFixed(2).replace('.', ',');
        const cab = ehMF
            ? ['Solicitação', 'Tipo', 'Embarque/Remessa', 'Descrição', 'Data', 'Pedidos', 'Base NCMs da guia', 'Valor guia', 'Erros de cálculo']
            : ['Solicitação', 'Ordem', 'Data', 'Fornecedor', 'Quantidade', 'Peso', 'Valor'];

        const linhas = r.registros.map((x) => ehMF
            ? [x.idcompra, x.remessa ? 'Remessa' : 'Embarque', x.embarque, x.nomeembarque, dataBR(x.data), x.qtpedido, dec(x.valor_st), dec(x.valor_guia), x.erros_calculo]
            : [x.idcompra, x.ordem, dataBR(x.data), x.fornecedor, x.quantidade, x.peso, dec(x.valor)]);

        const cel = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const blob = new Blob(['\ufeff' + [cab, ...linhas].map((l) => l.map(cel).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `transferencias_${tipo}_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
    } catch (e) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro ao exportar', text: e.message });
    }
}

// ==========================================================================
// INICIALIZAÇÃO E EVENT LISTENERS
// ==========================================================================

window.addEventListener('DOMContentLoaded', () => {
    // Histórico e Filtros
    $('btnFiltrar')?.addEventListener('click', carregar);
    $('btnLimpar')?.addEventListener('click', () => {
        if ($('dtIni')) $('dtIni').value = '';
        if ($('dtFim')) $('dtFim').value = '';
        if ($('busca')) $('busca').value = '';
        carregar();
    });

    $('busca')?.addEventListener('input', () => {
        clearTimeout(estado.timerBusca);
        estado.timerBusca = setTimeout(carregar, 400);
    });

    $('btnXls')?.addEventListener('click', baixar);

    $('lista')?.addEventListener('click', (ev) => {
        const card = ev.target.closest('[data-i]');
        if (card) detalhe(estado.registros[Number(card.dataset.i)]);
    });

    // Embarques Pendentes (Matriz -> Filial)
    $('buscaPendentes')?.addEventListener('input', () => {
        clearTimeout(estado.timerPendentes);
        estado.timerPendentes = setTimeout(carregarPendentes, 400);
    });

    $('chkTodosEmbarques')?.addEventListener('change', carregarPendentes);
    $('btnRecarregarPendentes')?.addEventListener('click', carregarPendentes);

    // Ordens Pendentes (Filial -> Matriz)
    $('buscaOrdensPendentes')?.addEventListener('input', () => {
        clearTimeout(estado.timerPendentes);
        estado.timerPendentes = setTimeout(carregarOrdensPendentes, 400);
    });
    $('btnRecarregarOrdens')?.addEventListener('click', carregarOrdensPendentes);

    // Busca rápida dentro da modal de prévia
    $('buscaItemPrevia')?.addEventListener('input', renderizarTabelaPrevia);

    // Carregar dados iniciais
    carregar();
    if (ehMF) carregarPendentes();
    else carregarOrdensPendentes();
});
