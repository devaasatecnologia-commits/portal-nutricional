// Módulo Gestão de Verbas Flex
// Nutricional Distribuidora - Experiência Premium com Dashboards e Ranking Completo

function aplicarHierarquiaPermissoes() {
    try {
        const userData = JSON.parse(localStorage.getItem('userData') || '{}');
        const permissoes = userData.permissoes || [];
        const isAdmin = !!(userData.is_admin || permissoes.includes('admin') || userData.nivel === 'admin');
        estado.isAdmin = isAdmin;
        estado.podeTransferir = isAdmin || permissoes.includes('transferencia-flex');
        estado.podeMovimentar = isAdmin || permissoes.includes('movimentacao-flex');

        if (!estado.podeTransferir) {
            $('tabLinkTransferencias')?.classList.add('hidden');
        }
        if (!estado.podeMovimentar) {
            $('tabLinkMovimentacao')?.classList.add('hidden');
            $('heroBtnMovimentacao')?.classList.add('hidden');
        }
        if (!isAdmin) {
            $('tabBtnApi')?.classList.add('hidden');
            $('heroBtnApi')?.classList.add('hidden');
        }
    } catch (e) {
        console.error('Erro ao verificar hierarquia de permissões:', e);
    }
}

function initModernTooltips() {
    let tip = $('modernTooltip');
    let content = $('modernTooltipContent');
    if (!tip) {
        tip = document.createElement('div');
        tip.id = 'modernTooltip';
        tip.className = 'fixed pointer-events-none z-[99999] opacity-0 transition-opacity duration-150 ease-out mb-3';
        tip.style.pointerEvents = 'none';
        tip.innerHTML = '<div class="bg-slate-900/95 backdrop-blur-md text-white border border-slate-700/70 rounded-2xl p-3.5 shadow-2xl max-w-xs ring-1 ring-white/10" id="modernTooltipContent"></div>';
        document.body.appendChild(tip);
        content = tip.querySelector('#modernTooltipContent');
    }

    document.addEventListener('mouseover', (e) => {
        const target = e.target.closest('[data-tip-title], [data-tip-desc]');
        if (!target) return;

        const title = target.getAttribute('data-tip-title') || '';
        const desc = target.getAttribute('data-tip-desc') || '';
        const badge = target.getAttribute('data-tip-badge') || '';

        content.innerHTML = `
            ${badge ? `<div class="mb-1"><span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-purple-500/30 text-purple-200 border border-purple-400/30">${esc(badge)}</span></div>` : ''}
            ${title ? `<div class="font-extrabold text-xs text-white mb-1 flex items-center gap-1.5"><i class="fa-solid fa-circle-info text-purple-400 text-[11px]"></i> ${esc(title)}</div>` : ''}
            ${desc ? `<div class="text-[11px] text-slate-300 font-medium leading-relaxed">${desc}</div>` : ''}
        `;
        tip.classList.remove('opacity-0');
        tip.classList.add('opacity-100');
    });

    document.addEventListener('mousemove', (e) => {
        if (!tip || tip.classList.contains('opacity-0')) return;
        const x = Math.min(window.innerWidth - 180, Math.max(180, e.clientX));
        const tooltipHeight = tip.offsetHeight || 120;
        let y = e.clientY - 12;

        if (e.clientY < tooltipHeight + 25) {
            tip.style.transform = 'translate(-50%, 20px)';
        } else {
            tip.style.transform = 'translate(-50%, -100%)';
        }
        tip.style.left = `${x}px`;
        tip.style.top = `${y}px`;
    });

    document.addEventListener('mouseout', (e) => {
        const fromTarget = e.target.closest('[data-tip-title], [data-tip-desc]');
        if (!fromTarget) return;

        // Se o cursor ainda estiver dentro do mesmo elemento com tooltip, não esconder
        if (e.relatedTarget && fromTarget.contains(e.relatedTarget)) {
            return;
        }

        if (tip) {
            tip.classList.add('opacity-0');
            tip.classList.remove('opacity-100');
        }
    });
}

function trocarVisaoRanking(visao) {
    if (!visao) return;
    estado.visaoRanking = visao;
    document.querySelectorAll('#visoesRanking button').forEach((b) => {
        b.classList.toggle('on', b.getAttribute('data-visao') === visao);
    });

    if (!estado.resumo) {
        carregarRankingCompleto();
    } else {
        renderRankingCompleto();
    }
}
window.trocarVisaoRanking = trocarVisaoRanking;

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
const num = (n) => Number(n || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
const dataBR = (d) => { const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? `${m[3]}/${m[2]}/${m[1]}` : esc(d); };

const swalBase = { confirmButtonColor: '#6d28d9', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = {
    aba: 'painel',
    debitos: [],
    creditos: [],
    ranking: [],
    rankingTransferenciasCedidas: [],
    rankingTransferenciasRecebidas: [],
    balancoEquipe: [],
    visaoRanking: 'bonificacoes',
    filtroRankingQtd: 'all',
    buscaRanking: '',
    resumo: null,
    chartMensalInst: null,
    chartDiarioInst: null,
    isAdmin: false,
    podeTransferir: false,
    podeMovimentar: false,
};

const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

function filtroBase() {
    let fil = $('selFilial')?.value;
    if (!fil || fil === '0' || fil === '') {
        fil = '1';
    }
    return {
        filial: fil,
        inicio: $('dtIni')?.value || iso(new Date(new Date().getFullYear(), new Date().getMonth(), 1)),
        fim: $('dtFim')?.value || iso(new Date())
    };
}

function atalho(tipo, disparar = true) {
    const h = new Date();
    let a = new Date(h.getFullYear(), h.getMonth(), 1);
    let b = h;

    if (tipo === 'hoje') {
        a = h;
        b = h;
    } else if (tipo === '7') {
        a = new Date(h.getTime() - 6 * 86400000);
        b = h;
    } else if (tipo === 'ant') {
        a = new Date(h.getFullYear(), h.getMonth() - 1, 1);
        b = new Date(h.getFullYear(), h.getMonth(), 0);
    } else if (tipo === '30') {
        a = new Date(h.getTime() - 29 * 86400000);
        b = h;
    } else if (tipo === 'ano') {
        a = new Date(h.getFullYear(), 0, 1);
        b = h;
    }

    if ($('dtIni')) $('dtIni').value = iso(a);
    if ($('dtFim')) $('dtFim').value = iso(b);
    document.querySelectorAll('#atalhos .vf-chip').forEach((c) => c.classList.toggle('on', c.dataset.p === tipo));
    if (disparar) {
        recarregarAbaAtiva();
    }
}

function variacao(atual, anterior) {
    if (!anterior || Number(anterior) === 0) return '';
    const pct = ((Math.abs(atual) - Math.abs(anterior)) / Math.abs(anterior)) * 100;
    const positivo = pct >= 0;
    const seta = positivo ? '▲' : '▼';
    const cor = positivo ? 'text-emerald-100 bg-emerald-900/30' : 'text-purple-200 bg-purple-900/30';
    return `<span class="inline-block ml-1.5 px-2 py-0.5 rounded-md text-[10px] font-bold ${cor}">${seta} ${Math.abs(pct).toFixed(0)}% vs ant.</span>`;
}

function irParaAbaRanking() {
    ativarAba('ranking');
}

function ativarAba(aba) {
    estado.aba = aba;
    document.querySelectorAll('#abas .vf-tab').forEach((t) => {
        if (t.dataset.aba) t.classList.toggle('on', t.dataset.aba === aba);
    });

    ['painel', 'ranking', 'debitos', 'creditos', 'api'].forEach((a) => {
        const sec = $(`aba-${a}`);
        if (sec) sec.classList.toggle('hidden', a !== aba);
    });

    recarregarAbaAtiva();
}

function recarregarAbaAtiva() {
    if (estado.aba === 'painel') carregarPainel();
    if (estado.aba === 'ranking') carregarRankingCompleto();
    if (estado.aba === 'debitos') carregarDebitos();
    if (estado.aba === 'creditos') carregarCreditos();
    if (estado.aba === 'api') carregarApi();
}

// ==========================================
// ABA 1: PAINEL & DASHBOARD
// ==========================================

async function carregarPainel() {
    const boxKpi = $('kpis');
    boxKpi.innerHTML = '<div class="col-span-full py-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-violet-600 block mb-2"></i>Carregando indicadores e métricas...</div>';

    try {
        const r = await apiFetch('verbas-flex/resumo', 'GET', filtroBase());
        estado.resumo = r;
        estado.ranking = r.ranking || [];

        const deb = Math.abs(r.atual.debitos);
        const cred = r.atual.creditos;
        const resultado = cred - deb;
        const stats = r.estatisticas || {};

        estado.rankingTransferenciasCedidas = r.ranking_transferencias_cedidas || [];
        estado.rankingTransferenciasRecebidas = r.ranking_transferencias_recebidas || [];
        estado.balancoEquipe = r.balanco_equipe || [];
        const detalheDeb = r.detalhe_maior_debito || null;

        const tipMaiorDeb = detalheDeb
            ? `Maior Retirada Única:\n• Representante: ${detalheDeb.representante || 'N/D'} (#${detalheDeb.idrepresentante || ''})\n• Nota Fiscal: #${detalheDeb.nota || 'N/D'}\n• Cliente: ${detalheDeb.cliente || 'N/D'}\n• Data: ${detalheDeb.data ? dataBR(detalheDeb.data) : 'N/D'}\n• Valor: ${brl(detalheDeb.valor || 0)}`
            : 'Pico máximo debitado em uma única nota fiscal no período.';

        const tipMaiorDebHtml = detalheDeb
            ? `<b>Representante:</b> ${esc(detalheDeb.representante || 'N/D')}<br>` +
              `<b>Nota Fiscal:</b> #${esc(detalheDeb.nota || 'N/D')}<br>` +
              `<b>Cliente:</b> ${esc(detalheDeb.cliente || 'N/D')}<br>` +
              `<b>Data:</b> ${detalheDeb.data ? dataBR(detalheDeb.data) : 'N/D'}<br>` +
              `<b>Valor:</b> ${brl(detalheDeb.valor || 0)}`
            : 'Pico máximo individual debitado em uma única nota fiscal no período.';

        const tipMaiorDebTxt = detalheDeb
            ? `Maior Retirada Única:\n• Representante: ${detalheDeb.representante || 'N/D'} (#${detalheDeb.idrepresentante || ''})\n• Nota Fiscal: #${detalheDeb.nota || 'N/D'}\n• Cliente: ${detalheDeb.cliente || 'N/D'}\n• Data: ${detalheDeb.data ? dataBR(detalheDeb.data) : 'N/D'}\n• Valor: ${brl(detalheDeb.valor || 0)}`
            : 'Pico máximo individual debitado em uma única nota fiscal no período.';

        // 6 KPIs com Tooltips Flutuantes Modernos + Title de Fallback
        boxKpi.innerHTML = `
            <div class="vf-kpi bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-md cursor-pointer hover:shadow-lg transition-all"
                 title="Empréstimos Concedidos:\nTotal de saldo de flex liberado e aportado pelo gestor ou diretoria para a equipe no período selecionado."
                 data-tip-badge="Aporte Gestor"
                 data-tip-title="Empréstimos Concedidos"
                 data-tip-desc="Total de saldo de flex liberado e aportado pelo gestor ou diretoria para a equipe comercial no período selecionado.">
                <div class="flex items-center justify-between opacity-85 text-[11px] font-bold uppercase tracking-wider">
                    <span>Empréstimos Concedidos</span>
                    <i class="fa-solid fa-hand-holding-dollar text-base"></i>
                </div>
                <div class="text-2xl font-black mt-1">${brl(cred)}</div>
                <div class="text-xs opacity-90 mt-1 font-semibold">
                    <span>${stats.qtd_creditos || 0} lançamento(s)</span>
                    ${variacao(cred, r.anterior?.creditos)}
                </div>
            </div>

            <div class="vf-kpi bg-gradient-to-br from-rose-600 to-red-700 text-white shadow-md cursor-pointer hover:shadow-lg transition-all"
                 title="Consumo em Bonificações:\nTotal de verba flex debitada através de notas fiscais de bonificação faturadas para clientes no período selecionado."
                 data-tip-badge="Saída Comercial"
                 data-tip-title="Consumo em Bonificações"
                 data-tip-desc="Total de verba flex debitada através de notas fiscais de bonificação faturadas para clientes no período selecionado.">
                <div class="flex items-center justify-between opacity-85 text-[11px] font-bold uppercase tracking-wider">
                    <span>Consumo Bonificações</span>
                    <i class="fa-solid fa-receipt text-base"></i>
                </div>
                <div class="text-2xl font-black mt-1">${brl(deb)}</div>
                <div class="text-xs opacity-90 mt-1 font-semibold">
                    <span>${stats.qtd_debitos || 0} nota(s) fiscais</span>
                    ${variacao(deb, r.anterior?.debitos)}
                </div>
            </div>

            <div class="vf-kpi bg-gradient-to-br ${resultado >= 0 ? 'from-violet-700 to-purple-800' : 'from-amber-600 to-orange-700'} text-white shadow-md cursor-pointer hover:shadow-lg transition-all"
                 title="Saldo Líquido / Resultado:\nDiferença entre Empréstimos Concedidos (+) e Bonificações Consumidas (-)."
                 data-tip-badge="Resultado Líquido"
                 data-tip-title="Saldo Líquido / Resultado"
                 data-tip-desc="Diferença entre Empréstimos Concedidos (+) e Bonificações Consumidas (-). Saldo positivo indica aporte superior ao consumo da filial.">
                <div class="flex items-center justify-between opacity-85 text-[11px] font-bold uppercase tracking-wider">
                    <span>Saldo Líquido / Resultado</span>
                    <i class="fa-solid fa-scale-balanced text-base"></i>
                </div>
                <div class="text-2xl font-black mt-1">${brl(resultado)}</div>
                <div class="text-xs opacity-90 mt-1 font-semibold">
                    <span>${resultado >= 0 ? 'Saldo Positivo de Verba' : 'Déficit no Período'}</span>
                </div>
            </div>

            <div class="vf-kpi bg-slate-50 border border-slate-200 text-slate-800 shadow-xs cursor-pointer hover:shadow-md transition-all"
                 title="Média por Retirada:\nTicket médio de verba flex abatido por nota fiscal faturada no período."
                 data-tip-badge="Ticket Médio"
                 data-tip-title="Média por Retirada"
                 data-tip-desc="Média aritmética de saldo flex consumido por nota fiscal de bonificação emitida no período.">
                <div class="flex items-center justify-between text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                    <span>Média por Retirada</span>
                    <i class="fa-solid fa-calculator text-slate-400 text-base"></i>
                </div>
                <div class="text-xl font-black text-slate-800 mt-1">${brl(stats.ticket_medio || 0)}</div>
                <div class="text-xs text-slate-400 font-semibold mt-1">Ticket médio por nota fiscal</div>
            </div>

            <div class="vf-kpi bg-slate-50 border border-slate-200 text-slate-800 shadow-xs cursor-pointer group hover:border-purple-300 hover:shadow-md transition-all"
                 title="${esc(tipMaiorDebTxt)}"
                 data-tip-badge="Pico Máximo"
                 data-tip-title="Maior Retirada Única"
                 data-tip-desc="${esc(tipMaiorDebHtml)}"
                 onclick="irParaAbaDebitosComMaior()">
                <div class="flex items-center justify-between text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                    <span>Maior Retirada Única</span>
                    <i class="fa-solid fa-bolt text-amber-500 text-base group-hover:scale-125 transition-transform"></i>
                </div>
                <div class="text-xl font-black text-red-600 mt-1">${brl(stats.maior_debito || 0)}</div>
                <div class="text-xs mt-1 truncate">
                    ${detalheDeb?.representante
                        ? `<span class="text-purple-700 font-black inline-flex items-center gap-1 bg-purple-100/70 px-2 py-0.5 rounded-lg max-w-full truncate"><i class="fa-solid fa-user-tie text-[10px] flex-none"></i> <span class="truncate">${esc(detalheDeb.representante)}</span></span>`
                        : '<span class="text-slate-400 font-semibold">Pico máximo em uma única nota</span>'}
                </div>
            </div>

            <div class="vf-kpi bg-slate-50 border border-slate-200 text-slate-800 shadow-xs cursor-pointer hover:shadow-md transition-all"
                 title="Representantes com Consumo:\nContagem de representantes com bonificações no período."
                 data-tip-badge="Equipe Ativa"
                 data-tip-title="Representantes com Consumo"
                 data-tip-desc="Contagem de representantes comerciais com pelo menos uma bonificação faturada no período.">
                <div class="flex items-center justify-between text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                    <span>Representantes com Consumo</span>
                    <i class="fa-solid fa-users text-violet-600 text-base"></i>
                </div>
                <div class="text-xl font-black text-violet-700 mt-1">${stats.total_representantes || 0}</div>
                <div class="text-xs text-slate-400 font-semibold mt-1">Vendedores com retiradas ativas</div>
            </div>
        `;

        renderGraficos(r);
        renderMiniRanking(r.ranking || []);
    } catch (e) {
        boxKpi.innerHTML = `<div class="col-span-full text-center text-rose-500 text-sm font-bold py-4">${esc(e.message)}</div>`;
    }
}

function renderGraficos(r) {
    // 1. Gráfico Mensal (Últimos 6 meses)
    const ctxMensal = $('chartMensal');
    if (ctxMensal) {
        if (estado.chartMensalInst) estado.chartMensalInst.destroy();

        const labels = (r.serie || []).map((s) => s.mes);
        const dataDeb = (r.serie || []).map((s) => Math.abs(Number(s.debitos || 0)));
        const dataCred = (r.serie || []).map((s) => Number(s.creditos || 0));

        estado.chartMensalInst = new Chart(ctxMensal, {
            type: 'bar',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Retiradas (Débitos)',
                        data: dataDeb,
                        backgroundColor: 'rgba(239, 68, 68, 0.85)',
                        borderRadius: 8,
                    },
                    {
                        label: 'Empréstimos (Créditos)',
                        data: dataCred,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)',
                        borderRadius: 8,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font: { family: 'Plus Jakarta Sans', weight: 'bold' } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` ${ctx.dataset.label}: ${brl(ctx.parsed.y)}`
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: (v) => 'R$ ' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // 2. Gráfico Diário no Período
    const ctxDiario = $('chartDiario');
    if (ctxDiario) {
        if (estado.chartDiarioInst) estado.chartDiarioInst.destroy();

        const diario = r.serie_diaria || [];
        const labelsD = diario.map((d) => dataBR(d.dia).slice(0, 5));
        const valuesD = diario.map((d) => Number(d.debito || 0));

        estado.chartDiarioInst = new Chart(ctxDiario, {
            type: 'line',
            data: {
                labels: labelsD.length ? labelsD : ['Sem dados'],
                datasets: [{
                    label: 'Verba Retirada no Dia',
                    data: valuesD.length ? valuesD : [0],
                    borderColor: '#7c3aed',
                    backgroundColor: 'rgba(124, 58, 237, 0.12)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 3,
                    pointBackgroundColor: '#7c3aed',
                    pointRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => ` Retirado: ${brl(ctx.parsed.y)}`
                        }
                    }
                },
                scales: {
                    y: {
                        ticks: {
                            callback: (v) => 'R$ ' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                        },
                        grid: { color: '#f1f5f9' }
                    },
                    x: { grid: { display: false } }
                }
            }
        });
    }
}

function renderMiniRanking(ranking) {
    const host = $('miniRanking');
    if (!ranking.length) {
        host.innerHTML = '<div class="text-sm text-slate-400 py-4 text-center">Nenhuma retirada registrada no período.</div>';
        return;
    }

    const maxR = Math.max(1, ...ranking.map((x) => Number(x.total)));
    const top5 = ranking.slice(0, 5);

    host.innerHTML = top5.map((x, i) => `
        <div class="p-3 rounded-2xl border border-slate-100 bg-white hover:bg-slate-50 transition">
            <div class="flex items-center justify-between text-xs sm:text-sm font-bold text-slate-800 mb-1.5">
                <span class="flex items-center gap-2 truncate">
                    <span class="w-6 h-6 rounded-lg bg-violet-100 text-violet-800 text-xs font-black flex items-center justify-center flex-none">${i + 1}º</span>
                    <span class="truncate">${esc(x.nome)}</span>
                </span>
                <span class="text-violet-700 font-extrabold whitespace-nowrap ml-2">${brl(x.total)}</span>
            </div>
            <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden mb-1">
                <div class="h-full bg-gradient-to-r from-violet-600 to-purple-500 rounded-full" style="width:${(Number(x.total) / maxR) * 100}%"></div>
            </div>
            <div class="flex justify-between text-[11px] text-slate-400 font-semibold">
                <span>${x.qtd} nota(s) fiscais</span>
                <span class="text-violet-600 font-bold">${x.percentual || 0}% do consumo total</span>
            </div>
        </div>
    `).join('');
}

// ==========================================
// ABA 2: RANKING COMPLETO DE CONSUMO
// ==========================================

function irParaAbaDebitosComMaior() {
    ativarAba('debitos');
    const detalheDeb = estado.resumo?.detalhe_maior_debito;
    if (detalheDeb?.nota) {
        const inp = $('fBuscaD');
        if (inp) {
            inp.value = detalheDeb.nota;
            carregarDebitos();
        }
    }
}

function verExtratoVendedor(id, nome) {
    ativarAba('debitos');
    const sel = $('fRep');
    if (sel) {
        sel.value = id;
    }
    carregarDebitos();
    const sec = $('aba-debitos');
    if (sec) {
        sec.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

async function abrirHistoricoColaborador(id, nome) {
    const modal = $('modalHistColab');
    const nomeEl = $('histColabNome');
    const totalEl = $('histColabTotal');
    const listaEl = $('histColabLista');

    if (!modal || !nomeEl || !totalEl || !listaEl) return;

    nomeEl.textContent = `${nome} (#${id})`;
    totalEl.textContent = 'Carregando...';
    listaEl.innerHTML = '<div class="py-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-purple-600 block mb-2"></i>Buscando transferências no Mercos...</div>';
    modal.classList.remove('hidden');

    try {
        const filial = Number($('selFilial')?.value || 1);
        const r = await apiFetch('flex/movimentacoes', 'GET', {
            filial,
            colaborador: id,
            limite: 40
        });

        const movs = r.movimentacoes || [];
        totalEl.textContent = `${movs.length} operação(ões)`;

        if (!movs.length) {
            listaEl.innerHTML = '<div class="py-8 text-center text-slate-400 text-xs font-semibold">Nenhuma transferência de flex encontrada para este colaborador no período.</div>';
            return;
        }

        listaEl.innerHTML = movs.map((m) => {
            const enviou = Number(m.colaborador_de) === Number(id);
            const badgeCor = enviou ? 'bg-purple-100 text-purple-800 border-purple-200' : 'bg-emerald-100 text-emerald-800 border-emerald-200';
            const sinal = enviou ? '➔ Enviou para' : '⬅ Recebeu de';
            const parceiro = enviou ? m.nome_para : m.nome_de;
            const corValor = enviou ? 'text-purple-700' : 'text-emerald-600';

            return `
                <div class="p-3 rounded-2xl border border-slate-100 bg-white hover:bg-slate-50 transition flex items-center justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black border ${badgeCor}">
                                ${enviou ? 'CEDEU' : 'RECEBEU'}
                            </span>
                            <span class="text-xs font-bold text-slate-700 truncate">${sinal} <b>${esc(parceiro)}</b></span>
                        </div>
                        <div class="text-[11px] text-slate-400 truncate">
                            <span>${m.data_transferencia ? m.data_transferencia.slice(0, 16) : ''}</span>
                            ${m.motivo ? ` · <i>"${esc(m.motivo)}"</i>` : ''}
                            ${m.usuario ? ` · por <b>${esc(m.usuario)}</b>` : ''}
                        </div>
                    </div>
                    <div class="text-right flex-none">
                        <div class="text-sm font-black ${corValor}">${brl(m.valor)}</div>
                        <span class="text-[10px] font-bold ${m.sucesso ? 'text-emerald-600' : 'text-rose-600'}">
                            ${m.sucesso ? '<i class="fa-solid fa-check"></i> Concluída' : '<i class="fa-solid fa-xmark"></i> Falhou'}
                        </span>
                    </div>
                </div>
            `;
        }).join('');
    } catch (e) {
        listaEl.innerHTML = `<div class="py-6 text-center text-rose-500 text-xs font-bold">${esc(e.message)}</div>`;
    }
}

function fecharModalHistColab() {
    $('modalHistColab')?.classList.add('hidden');
}

async function carregarRankingCompleto() {
    const host = $('listaRankingCompleto');
    host.innerHTML = '<div class="py-12 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-violet-600 block mb-2"></i>Carregando painel analítico...</div>';

    try {
        if (!estado.resumo) {
            estado.resumo = await apiFetch('verbas-flex/resumo', 'GET', filtroBase());
        }
        estado.ranking = estado.resumo.ranking || [];
        estado.rankingTransferenciasCedidas = estado.resumo.ranking_transferencias_cedidas || [];
        estado.rankingTransferenciasRecebidas = estado.resumo.ranking_transferencias_recebidas || [];
        estado.balancoEquipe = estado.resumo.balanco_equipe || [];

        renderRankingCompleto();
    } catch (e) {
        host.innerHTML = `<div class="py-6 text-center text-rose-500 text-sm font-bold">${esc(e.message)}</div>`;
    }
}

function renderRankingCompleto() {
    const host = $('listaRankingCompleto');
    const q = estado.buscaRanking.toLowerCase();
    const limit = estado.filtroRankingQtd;
    const visao = estado.visaoRanking;

    // Atualizar títulos conforme a visão
    const titulos = {
        bonificacoes: {
            t: '<i class="fa-solid fa-receipt text-amber-500 mr-2"></i>Consumo em Bonificações (Notas Fiscais)',
            s: 'Representantes com maior volume debitado em notas fiscais faturadas no período.'
        },
        transferencias_cedidas: {
            t: '<i class="fa-solid fa-arrow-up-from-bracket text-purple-600 mr-2"></i>Consumo de Transferência (Quem Mais Cedeu/Transferiu)',
            s: 'Colaboradores e gestores que mais transferiram saldo flex para outros colegas da filial via Mercos.'
        },
        transferencias_recebidas: {
            t: '<i class="fa-solid fa-arrow-down-to-bracket text-emerald-600 mr-2"></i>Quem Mais Creditou (Recebeu Transferência)',
            s: 'Representantes e gestores que mais receberam saldo flex remanejado pela equipe via Mercos.'
        },
        balanco: {
            t: '<i class="fa-solid fa-scale-balanced text-indigo-600 mr-2"></i>Balanço Geral Consolidado da Equipe',
            s: 'Comparativo 360° entre saídas (bonificações + transferências cedidas) e entradas (transferências recebidas).'
        }
    };

    if ($('rankingTitulo')) $('rankingTitulo').innerHTML = titulos[visao]?.t || '';
    if ($('rankingSubtitulo')) $('rankingSubtitulo').textContent = titulos[visao]?.s || '';

    // ==========================================
    // VISÃO 4: BALANÇO GERAL CONSOLIDADO DA EQUIPE
    // ==========================================
    if (visao === 'balanco') {
        let lista = (estado.balancoEquipe || []).filter((r) => {
            if (q && !r.nome.toLowerCase().includes(q)) return false;
            return true;
        });

        $('contTotalReps').textContent = lista.length;
        if (limit === '5') lista = lista.slice(0, 5);
        else if (limit === '10') lista = lista.slice(0, 10);

        if (!lista.length) {
            host.innerHTML = '<div class="py-10 text-center text-slate-400 text-xs font-semibold">Nenhum registro encontrado no balanço da equipe para este período.</div>';
            return;
        }

        host.innerHTML = `
            <div class="overflow-x-auto rounded-2xl border border-slate-200">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 font-black border-b border-slate-200">
                            <th class="py-3 px-4">Colaborador / Vendedor</th>
                            <th class="py-3 px-3 text-right text-rose-700">Bonificações (-)</th>
                            <th class="py-3 px-3 text-right text-purple-700">Transferiu / Cedeu (-)</th>
                            <th class="py-3 px-3 text-right text-emerald-700">Recebeu Transferência (+)</th>
                            <th class="py-3 px-3 text-right font-black">Total Saídas</th>
                            <th class="py-3 px-3 text-right font-black">Saldo Líquido</th>
                            <th class="py-3 px-4 text-center font-black">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        ${lista.map((x) => {
                            const saldolq = Number(x.saldo_liquido || 0);
                            const corSaldo = saldolq > 0 ? 'text-emerald-600 bg-emerald-50' : saldolq < 0 ? 'text-rose-600 bg-rose-50' : 'text-slate-600 bg-slate-50';
                            return `
                                <tr class="hover:bg-purple-50/40 transition">
                                    <td class="py-3 px-4 font-bold text-slate-800">
                                        <div class="truncate max-w-[200px]" title="${esc(x.nome)}">${esc(x.nome)}</div>
                                        <div class="text-[10px] text-slate-400 font-semibold">ID #${x.id}</div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-extrabold text-rose-600">
                                        ${brl(x.bonificacoes)}
                                        <div class="text-[10px] text-slate-400 font-medium">${x.qtd_bonificacoes} nota(s)</div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-extrabold text-purple-700">
                                        ${brl(x.transferido_cedido)}
                                        <div class="text-[10px] text-slate-400 font-medium">${x.qtd_cedido} envio(s)</div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-extrabold text-emerald-700">
                                        ${brl(x.transferido_recebido)}
                                        <div class="text-[10px] text-slate-400 font-medium">${x.qtd_recebido} entrada(s)</div>
                                    </td>
                                    <td class="py-3 px-3 text-right font-black text-slate-800">
                                        ${brl(x.total_saidas)}
                                    </td>
                                    <td class="py-3 px-3 text-right">
                                        <span class="px-2.5 py-1 rounded-lg font-black ${corSaldo} inline-block">
                                            ${saldolq > 0 ? '+' : ''}${brl(saldolq)}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-1.5">
                                            ${x.qtd_bonificacoes > 0 ? `
                                            <button type="button" onclick="verExtratoVendedor(${x.id}, '${esc(x.nome)}')"
                                                    class="p-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 transition"
                                                    title="Ver notas fiscais de bonificação">
                                                <i class="fa-solid fa-receipt text-xs"></i>
                                            </button>` : ''}
                                            ${estado.podeTransferir ? `
                                            <a href="/portal/modules/transferencia-flex.php?para=${x.id}"
                                               class="p-1.5 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 transition no-underline"
                                               title="Transferir verba para ${esc(x.nome)}">
                                                <i class="fa-solid fa-right-left text-xs"></i>
                                            </a>` : ''}
                                            <button type="button" onclick="abrirHistoricoColaborador(${x.id}, '${esc(x.nome)}')"
                                                    class="p-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 transition"
                                                    title="Histórico de transferências">
                                                <i class="fa-solid fa-clock-rotate-left text-xs"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
        return;
    }

    // ==========================================
    // VISÕES DE RANKING (1, 2 e 3)
    // ==========================================
    let fonte = [];
    let corBarra = 'from-violet-600 via-purple-600 to-indigo-500';
    let labelQtd = 'nota(s) faturadas';
    let labelDestaque = 'Maior:';
    let badgeTxt = 'da verba';

    if (visao === 'bonificacoes') {
        fonte = estado.ranking || [];
        corBarra = 'from-rose-500 to-red-600';
        labelQtd = 'nota(s) faturadas';
        labelDestaque = 'Maior Retirada:';
        badgeTxt = 'do consumo';
    } else if (visao === 'transferencias_cedidas') {
        fonte = estado.rankingTransferenciasCedidas || [];
        corBarra = 'from-purple-600 to-violet-700';
        labelQtd = 'transferência(s) enviadas';
        labelDestaque = 'Maior Envio:';
        badgeTxt = 'do volume cedido';
    } else if (visao === 'transferencias_recebidas') {
        fonte = estado.rankingTransferenciasRecebidas || [];
        corBarra = 'from-emerald-500 to-teal-600';
        labelQtd = 'transferência(s) recebidas';
        labelDestaque = 'Maior Recebimento:';
        badgeTxt = 'do volume recebido';
    }

    let lista = fonte.filter((r) => {
        if (q && !r.nome.toLowerCase().includes(q)) return false;
        return true;
    });

    $('contTotalReps').textContent = lista.length;
    if (limit === '5') lista = lista.slice(0, 5);
    else if (limit === '10') lista = lista.slice(0, 10);

    if (!lista.length) {
        host.innerHTML = '<div class="py-10 text-center text-slate-400 text-xs font-semibold">Nenhum registro encontrado para esta visão no período.</div>';
        return;
    }

    const totalGeral = fonte.reduce((acc, cur) => acc + Number(cur.total || 0), 0);
    const maxR = Math.max(1, ...fonte.map((x) => Number(x.total || 0)));

    host.innerHTML = lista.map((x, i) => {
        const medalha = i === 0
            ? '<span class="rank-badge bg-amber-100 text-amber-700 border border-amber-300 shadow-xs"><i class="fa-solid fa-medal"></i> 1º</span>'
            : i === 1
            ? '<span class="rank-badge bg-slate-200 text-slate-700 border border-slate-300 shadow-xs"><i class="fa-solid fa-medal"></i> 2º</span>'
            : i === 2
            ? '<span class="rank-badge bg-amber-700/10 text-amber-900 border border-amber-600/30 shadow-xs"><i class="fa-solid fa-medal"></i> 3º</span>'
            : `<span class="rank-badge bg-slate-100 text-slate-500 font-bold">${i + 1}º</span>`;

        const pct = totalGeral > 0 ? ((Number(x.total || 0) / totalGeral) * 100).toFixed(1) : 0;
        const valDestaque = x.maior_retirada || x.maior_operacao || 0;

        return `
            <div class="p-4 rounded-2xl border border-slate-200 bg-white hover:border-violet-300 hover:shadow-md hover:-translate-y-0.5 transition-all duration-200">
                <div class="flex items-center gap-3 mb-2">
                    ${medalha}
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h4 class="font-black text-sm text-slate-800 truncate" title="${esc(x.nome)}">${esc(x.nome)}</h4>
                            <span class="text-base font-black text-violet-700">${brl(x.total)}</span>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-slate-400 mt-0.5">
                            <span>Código: <b>#${x.id}</b></span>
                            <span>·</span>
                            <span><b>${x.qtd}</b> ${labelQtd}</span>
                            ${valDestaque ? `<span>·</span><span>${labelDestaque} <b>${brl(valDestaque)}</b></span>` : ''}
                        </div>
                    </div>
                </div>

                <!-- Barra de Proporção -->
                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden my-2.5">
                    <div class="h-full bg-gradient-to-r ${corBarra} rounded-full transition-all duration-500" style="width:${(Number(x.total) / maxR) * 100}%"></div>
                </div>

                <!-- AÇÕES ANALÍTICAS E PARTICIPAÇÃO -->
                <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100">
                    <div class="flex items-center gap-1.5">
                        <button type="button" onclick="verExtratoVendedor(${x.id}, '${esc(x.nome)}')"
                                class="px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-black transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                            <i class="fa-solid fa-receipt text-[10px]"></i>
                            <span>Ver Notas</span>
                        </button>
                        ${estado.podeTransferir ? `
                        <a href="/portal/modules/transferencia-flex.php?para=${x.id}"
                           class="px-2.5 py-1 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-700 text-[11px] font-black transition flex items-center gap-1.5 no-underline cursor-pointer shadow-2xs">
                            <i class="fa-solid fa-right-left text-[10px]"></i>
                            <span>Transferir</span>
                        </a>` : ''}
                        <button type="button" onclick="abrirHistoricoColaborador(${x.id}, '${esc(x.nome)}')"
                                class="px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 text-[11px] font-black transition flex items-center gap-1.5 cursor-pointer shadow-2xs">
                            <i class="fa-solid fa-clock-rotate-left text-[10px]"></i>
                            <span>Histórico Flex</span>
                        </button>
                    </div>
                    <span class="text-xs font-black text-violet-800 bg-violet-100/70 px-2.5 py-0.5 rounded-lg flex-none">${pct}% ${badgeTxt}</span>
                </div>
            </div>
        `;
    }).join('');
}

// ==========================================
// ABA 3: DÉBITOS
// ==========================================

async function carregarDebitos() {
    const p = { ...filtroBase(), limite: 1000 };
    if ($('fRep').value) p.representante = $('fRep').value;
    if ($('fBuscaD').value.trim()) p.q = $('fBuscaD').value.trim();

    const host = $('listaD');
    host.innerHTML = '<div class="py-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-red-500 block mb-2"></i>Carregando débitos...</div>';

    try {
        const r = await apiFetch('verbas-flex/debitos', 'GET', p);
        estado.debitos = r.registros || [];
        $('totD').textContent = brl(Math.abs(r.total));
        $('qtdD').textContent = `${r.quantidade} lançamento(s)` + (r.quantidade > r.registros.length ? ` (exibindo ${r.registros.length})` : '');

        if (!$('fRep').dataset.carregado || $('fRep').dataset.chave !== `${p.filial}|${p.inicio}|${p.fim}`) {
            const atual = $('fRep').value;
            $('fRep').innerHTML = '<option value="">Todos os Representantes</option>' + (r.representantes || []).map((x) => `<option value="${x.id}">${esc(x.nome)}</option>`).join('');
            $('fRep').value = atual;
            $('fRep').dataset.carregado = '1';
            $('fRep').dataset.chave = `${p.filial}|${p.inicio}|${p.fim}`;
        }

        host.innerHTML = estado.debitos.length ? estado.debitos.map((d, i) => `
            <div class="vf-row" onclick="verDetalheDebito(${i})">
                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center font-bold text-sm flex-none">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-center gap-2">
                        <b class="text-xs sm:text-sm text-slate-800 truncate">${esc(d.cliente)}</b>
                        <b class="text-sm font-black text-red-600 whitespace-nowrap">${brl(Math.abs(d.valor_tirado))}</b>
                    </div>
                    <div class="text-[11px] text-slate-500 mt-0.5 truncate">
                        <span>Rep: <b>${esc(d.representante)}</b></span>
                        <span class="mx-1.5">·</span>
                        <span>Nota: <b>#${esc(d.nota)}</b></span>
                        ${d.gestor ? `<span class="mx-1.5">·</span><span>Gestor: <b>${esc(d.gestor)}</b></span>` : ''}
                    </div>
                    <div class="text-[10px] text-slate-400 mt-0.5">${dataBR(d.data)} · ${esc(d.motivo || 'Sem motivo')}</div>
                </div>
            </div>`).join('') : '<div class="text-center text-slate-400 py-8 text-xs font-semibold">Nenhum débito encontrado para os filtros selecionados.</div>';
    } catch (e) {
        host.innerHTML = `<div class="text-center text-rose-500 py-6 text-xs font-bold">${esc(e.message)}</div>`;
    }
}

function verDetalheDebito(i) {
    const d = estado.debitos[i];
    if (!d) return;

    Swal.fire({
        ...swalBase,
        title: `Nota Fiscal #${esc(d.nota)}`,
        html: `
            <div class="text-left text-xs bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                <div><span class="text-slate-400 font-bold uppercase">Cliente:</span><br><b class="text-sm text-slate-800">${esc(d.cliente)} (#${d.cdcliente})</b></div>
                <div><span class="text-slate-400 font-bold uppercase">Representante:</span><br><b>${esc(d.representante)}</b></div>
                ${d.gestor ? `<div><span class="text-slate-400 font-bold uppercase">Gestor / Supervisor:</span><br><b>${esc(d.gestor)}</b></div>` : ''}
                <div class="grid grid-cols-2 gap-2 pt-2 border-t border-slate-200">
                    <div><span class="text-slate-400 font-bold uppercase">Data:</span><br><b>${dataBR(d.data)}</b></div>
                    <div><span class="text-slate-400 font-bold uppercase">Valor da Nota:</span><br><b>${brl(d.valor_nota)}</b></div>
                </div>
                <div class="pt-2 border-t border-slate-200">
                    <span class="text-slate-400 font-bold uppercase">Valor Retirado de Verba:</span><br>
                    <b class="text-base text-red-600 font-black">${brl(Math.abs(d.valor_tirado))}</b>
                </div>
                <div><span class="text-slate-400 font-bold uppercase">Observação / Motivo:</span><br><i class="text-slate-600">${esc(d.motivo)}</i></div>
            </div>
        `,
        confirmButtonText: 'Fechar'
    });
}

// ==========================================
// ABA 4: CRÉDITOS / EMPRÉSTIMOS
// ==========================================

async function carregarCreditos() {
    const p = { ...filtroBase(), limite: 1000 };
    if ($('fBuscaC').value.trim()) p.q = $('fBuscaC').value.trim();
    if ($('fZerados').checked) p.zerados = 1;

    const host = $('listaC');
    host.innerHTML = '<div class="py-8 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-emerald-600 block mb-2"></i>Carregando créditos...</div>';

    try {
        const r = await apiFetch('verbas-flex/creditos', 'GET', p);
        estado.creditos = r.registros || [];
        $('totC').textContent = brl(r.total);
        $('qtdC').textContent = `${r.quantidade} lançamento(s)` + (r.quantidade > r.registros.length ? ` (exibindo ${r.registros.length})` : '');

        host.innerHTML = estado.creditos.length ? estado.creditos.map((c, i) => {
            const isZero = Number(c.valor_emprestimo) === 0;
            const isFalha = c.motivo && c.motivo.toLowerCase().includes('falha');

            return `
            <div class="vf-row" onclick="verDetalheCredito(${i})">
                <div class="w-10 h-10 rounded-xl ${isZero ? 'bg-amber-100 text-amber-700' : 'bg-emerald-50 text-emerald-700'} flex items-center justify-center font-bold text-sm flex-none">
                    <i class="fa-solid ${isZero ? 'fa-triangle-exclamation' : 'fa-hand-holding-dollar'}"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-center gap-2">
                        <b class="text-xs sm:text-sm text-slate-800">${dataBR(c.data)}</b>
                        <b class="text-sm font-black ${isZero ? 'text-amber-600' : 'text-emerald-700'} whitespace-nowrap">
                            ${isZero ? 'R$ 0,00 (Falha)' : brl(c.valor_emprestimo)}
                        </b>
                    </div>
                    <div class="text-xs text-slate-500 line-clamp-2 mt-0.5">${esc(c.motivo)}</div>
                </div>
            </div>`;
        }).join('') : '<div class="text-center text-slate-400 py-8 text-xs font-semibold">Nenhum crédito ou empréstimo encontrado no período.</div>';
    } catch (e) {
        host.innerHTML = `<div class="text-center text-rose-500 py-6 text-xs font-bold">${esc(e.message)}</div>`;
    }
}

function verDetalheCredito(i) {
    const c = estado.creditos[i];
    if (!c) return;

    Swal.fire({
        ...swalBase,
        title: `Lançamento de Empréstimo #${c.id}`,
        html: `
            <div class="text-left text-xs bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2">
                <div><span class="text-slate-400 font-bold uppercase">Data:</span><br><b class="text-sm text-slate-800">${dataBR(c.data)}</b></div>
                <div><span class="text-slate-400 font-bold uppercase">Valor do Empréstimo:</span><br><b class="text-base text-emerald-700 font-black">${brl(c.valor_emprestimo)}</b></div>
                <div class="pt-2 border-t border-slate-200"><span class="text-slate-400 font-bold uppercase">Motivo / Descrição:</span><br><i class="text-slate-700">${esc(c.motivo)}</i></div>
            </div>
        `,
        confirmButtonText: 'Fechar'
    });
}

// ==========================================
// ABA 5: API MERCOS
// ==========================================

const dataHora = (iso8601) => { const d = new Date(iso8601); return isNaN(d) ? '-' : d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }); };
const duracao = (seg) => {
    if (seg == null) return '-';
    if (seg < 60) return `${seg} s`;
    if (seg < 3600) return `${Math.round(seg / 60)} min`;
    return `${Math.floor(seg / 3600)} h ${Math.round((seg % 3600) / 60)} min`;
};

async function testarApi() {
    const btn = $('btnTestar');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Testando…';
    try {
        const r = await apiFetch('verbas-flex/api-status', 'GET', { filial: $('selFilial').value });
        renderApiStatus(r);
        await carregarApiHistorico();
    } catch (e) {
        aviso('error', 'Falha ao testar API', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Testar agora';
    }
}

function renderApiStatus(r) {
    const ico = $('apiIco');
    const tit = $('apiTit');
    const sub = $('apiSub');
    if (r.ok) {
        ico.className = 'w-12 h-12 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-xl flex-none';
        ico.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
        tit.textContent = `API Mercos Online (HTTP ${r.http} · ${r.ms} ms)`;
        sub.textContent = `Último teste: ${dataHora(r.testado_em)}`;
    } else {
        ico.className = 'w-12 h-12 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center text-xl flex-none';
        ico.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i>';
        tit.textContent = `Falha na API Mercos (HTTP ${r.http || '-'})`;
        sub.textContent = r.erro || 'Sem resposta do serviço';
    }

    const hostG = $('apiGestores');
    if (!r.gestores || !r.gestores.length) {
        hostG.innerHTML = '';
        return;
    }
    hostG.innerHTML = r.gestores.map((g) => {
        const saldoTxt = g.saldo === null ? 'não consultado' : brl(g.saldo);
        const alerta = g.abaixo_minimo ? '<span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-100 text-amber-800">Abaixo do mínimo</span>' : '';
        return `
            <div class="flex items-center justify-between p-3 rounded-xl border border-slate-100 bg-slate-50 text-xs">
                <div>
                    <b class="text-slate-800">${esc(g.nome)}</b>
                    <span class="text-slate-400 ml-2">Mínimo: ${brl(g.valor_minimo)}</span>
                </div>
                <div class="flex items-center gap-2">
                    ${alerta}
                    <b class="text-slate-700">${saldoTxt}</b>
                </div>
            </div>`;
    }).join('');
}

async function carregarApiHistorico() {
    const horas = document.querySelector('#apiPeriodo .vf-chip.on')?.dataset.h || '24';
    try {
        const h = await apiFetch('verbas-flex/api-historico', 'GET', { filial: $('selFilial').value, horas });
        $('apiKpis').innerHTML = `
            <div><small class="text-slate-400 block text-[10px] uppercase font-bold">Disponibilidade</small><b class="text-xl font-black text-slate-800">${h.uptime_percentual}%</b></div>
            <div><small class="text-slate-400 block text-[10px] uppercase font-bold">Tempo Médio</small><b class="text-xl font-black text-slate-800">${h.tempo_medio_ms} ms</b></div>
            <div><small class="text-slate-400 block text-[10px] uppercase font-bold">Quedas</small><b class="text-xl font-black ${h.quedas_total ? 'text-red-600' : 'text-slate-800'}">${h.quedas_total}</b></div>`;

        const barras = h.buckets || [];
        $('apiBarras').innerHTML = barras.map((b) => {
            const cor = b.ok === b.total ? 'bg-emerald-500' : b.ok === 0 ? 'bg-red-500' : 'bg-amber-400';
            return `<div class="flex-1 ${cor} rounded-xs" title="${dataHora(b.inicio)}: ${b.ok}/${b.total} ok (${b.ms_medio} ms)"></div>`;
        }).join('');
        $('apiDe').textContent = dataHora(h.de);

        $('apiQuedas').innerHTML = (h.quedas || []).length
            ? h.quedas.map((q) => `
                <div class="text-xs p-2.5 rounded-xl bg-red-50 text-red-800 flex justify-between gap-2 border border-red-100">
                    <div><b>${dataHora(q.inicio)}</b> <span class="text-red-500">(${duracao(q.duracao_seg)})</span></div>
                    <div class="truncate text-red-600 text-[11px]">${esc(q.motivo || 'Sem resposta')}</div>
                </div>`).join('')
            : '<div class="text-xs text-slate-400 py-3 text-center">Nenhuma queda registrada nas últimas ' + horas + ' horas.</div>';
    } catch (e) {
        $('apiQuedas').innerHTML = `<div class="text-xs text-red-500">${esc(e.message)}</div>`;
    }
}

async function carregarApi() {
    await testarApi();
}

// ==========================================
// EXPORTAÇÕES EXCEL (CSV UTF-8 COM BOM)
// ==========================================

function baixarCSV(nomeArquivo, colunas, linhas) {
    const header = colunas.join(';') + '\n';
    const body = linhas.map((l) => l.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';')).join('\n');
    const blob = new Blob(['\uFEFF' + header + body], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = nomeArquivo;
    a.click();
    URL.revokeObjectURL(url);
}

// ==========================================
// INICIALIZAÇÃO
// ==========================================

window.addEventListener('DOMContentLoaded', async () => {
    atalho('mes');

    try {
        const ctx = await apiFetch('verbas-flex/contexto');
        $('selFilial').innerHTML = (ctx.filiais || []).map((f) => `<option value="${f.idfilial}">${esc(f.idfilial)} - ${esc(f.razao)}</option>`).join('');
    } catch (e) {
        aviso('error', 'Erro ao carregar contexto', e.message);
        return;
    }

    $('selFilial').addEventListener('change', recarregarAbaAtiva);
    $('btnAplicar').addEventListener('click', recarregarAbaAtiva);

    document.querySelectorAll('#atalhos .vf-chip').forEach((b) => {
        b.addEventListener('click', () => atalho(b.dataset.p));
    });

    document.querySelectorAll('#abas .vf-tab').forEach((b) => {
        if (b.dataset.aba) {
            b.addEventListener('click', () => ativarAba(b.dataset.aba));
        }
    });

    // Pílulas / Visões do Ranking (Consumo Bonificações, Quem Cedeu, Quem Recebeu, Balanço Geral)
    document.querySelectorAll('#visoesRanking button').forEach((b) => {
        b.addEventListener('click', (e) => {
            const btn = e.currentTarget || b;
            const visao = btn.getAttribute('data-visao');
            trocarVisaoRanking(visao);
        });
    });

    // Filtros de Quantidade do Ranking (Top 5, Top 10, Todos)
    document.querySelectorAll('#filtroQtdRanking .vf-chip').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#filtroQtdRanking .vf-chip').forEach((c) => c.classList.remove('on'));
            b.classList.add('on');
            estado.filtroRankingQtd = b.dataset.limit;
            renderRankingCompleto();
        });
    });

    $('buscaRanking')?.addEventListener('input', (e) => {
        estado.buscaRanking = e.target.value.trim();
        renderRankingCompleto();
    });

    // Filtros de Débitos e Créditos
    $('fRep')?.addEventListener('change', carregarDebitos);
    $('fBuscaD')?.addEventListener('input', () => {
        clearTimeout(estado.timerD);
        estado.timerD = setTimeout(carregarDebitos, 350);
    });
    $('fBuscaC')?.addEventListener('input', () => {
        clearTimeout(estado.timerC);
        estado.timerC = setTimeout(carregarCreditos, 350);
    });
    $('fZerados')?.addEventListener('change', carregarCreditos);

    // Monitor API
    $('btnTestar')?.addEventListener('click', testarApi);
    document.querySelectorAll('#apiPeriodo .vf-chip').forEach((b) => {
        b.addEventListener('click', () => {
            document.querySelectorAll('#apiPeriodo .vf-chip').forEach((c) => c.classList.remove('on'));
            b.classList.add('on');
            carregarApiHistorico();
        });
    });

    // Exportações Excel
    $('xlsRanking')?.addEventListener('click', () => {
        const visao = estado.visaoRanking;
        if (visao === 'balanco') {
            const dados = estado.balancoEquipe || [];
            if (!dados.length) return aviso('warning', 'Sem Dados', 'Nenhum dado do balanço para exportar.');
            const cols = ['ID', 'Colaborador', 'Bonificações (R$)', 'Qtd Bonificações', 'Transferido Cedido (R$)', 'Qtd Cedido', 'Transferido Recebido (R$)', 'Qtd Recebido', 'Total Saídas (R$)', 'Saldo Líquido (R$)'];
            const rows = dados.map((d) => [
                d.id,
                d.nome,
                Number(d.bonificacoes || 0).toFixed(2).replace('.', ','),
                d.qtd_bonificacoes || 0,
                Number(d.transferido_cedido || 0).toFixed(2).replace('.', ','),
                d.qtd_cedido || 0,
                Number(d.transferido_recebido || 0).toFixed(2).replace('.', ','),
                d.qtd_recebido || 0,
                Number(d.total_saidas || 0).toFixed(2).replace('.', ','),
                Number(d.saldo_liquido || 0).toFixed(2).replace('.', ',')
            ]);
            return baixarCSV(`balanco_equipe_flex_${iso(new Date())}.csv`, cols, rows);
        }

        let fonte = [];
        let nomeArquivo = 'ranking_verbas_flex';
        let colOperacao = 'Qtd Notas';
        let colDestaque = 'Maior Retirada (R$)';

        if (visao === 'bonificacoes') {
            fonte = estado.ranking || [];
            nomeArquivo = 'ranking_consumo_bonificacoes';
            colOperacao = 'Qtd Notas';
            colDestaque = 'Maior Retirada (R$)';
        } else if (visao === 'transferencias_cedidas') {
            fonte = estado.rankingTransferenciasCedidas || [];
            nomeArquivo = 'ranking_transferencias_cedidas';
            colOperacao = 'Qtd Envios';
            colDestaque = 'Maior Envio (R$)';
        } else if (visao === 'transferencias_recebidas') {
            fonte = estado.rankingTransferenciasRecebidas || [];
            nomeArquivo = 'ranking_transferencias_recebidas';
            colOperacao = 'Qtd Recebidas';
            colDestaque = 'Maior Recebimento (R$)';
        }

        if (!fonte.length) return aviso('warning', 'Sem Dados', 'Nenhum dado para exportar nesta visão.');
        const totalGeral = fonte.reduce((acc, cur) => acc + Number(cur.total || 0), 0);
        const cols = ['Posição', 'ID', 'Colaborador', 'Total (R$)', colOperacao, colDestaque, '% Participação'];
        const rows = fonte.map((r, i) => [
            `${i + 1}º`,
            r.id,
            r.nome,
            Number(r.total || 0).toFixed(2).replace('.', ','),
            r.qtd,
            Number(r.maior_retirada || r.maior_operacao || 0).toFixed(2).replace('.', ','),
            totalGeral > 0 ? `${((Number(r.total || 0) / totalGeral) * 100).toFixed(1)}%` : '0%'
        ]);
        baixarCSV(`${nomeArquivo}_${iso(new Date())}.csv`, cols, rows);
    });

    $('xlsD')?.addEventListener('click', () => {
        if (!estado.debitos.length) return aviso('warning', 'Sem Dados', 'Nenhum débito para exportar.');
        const cols = ['Data', 'Nota Fiscal', 'Código Cliente', 'Cliente', 'Código Rep.', 'Representante', 'Gestor', 'Valor da Nota (R$)', 'Valor Retirado (R$)', 'Motivo'];
        const rows = estado.debitos.map((d) => [
            d.data,
            d.nota,
            d.cdcliente,
            d.cliente,
            d.cdrepre,
            d.representante,
            d.gestor || '',
            Number(d.valor_nota || 0).toFixed(2).replace('.', ','),
            Math.abs(Number(d.valor_tirado || 0)).toFixed(2).replace('.', ','),
            d.motivo || ''
        ]);
        baixarCSV(`debitos_verbas_flex_${iso(new Date())}.csv`, cols, rows);
    });

    $('xlsC')?.addEventListener('click', () => {
        if (!estado.creditos.length) return aviso('warning', 'Sem Dados', 'Nenhum crédito para exportar.');
        const cols = ['ID', 'Data', 'Valor do Empréstimo (R$)', 'Motivo'];
        const rows = estado.creditos.map((c) => [
            c.id,
            c.data,
            Number(c.valor_emprestimo || 0).toFixed(2).replace('.', ','),
            c.motivo || ''
        ]);
        baixarCSV(`creditos_verbas_flex_${iso(new Date())}.csv`, cols, rows);
    });

    aplicarHierarquiaPermissoes();
    initModernTooltips();
    carregarPainel();
});
