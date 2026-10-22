// Módulo Gestão de Verbas Flex
// Nutricional Distribuidora - Experiência Premium com Dashboards e Ranking Completo

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
    filtroRankingQtd: 'all',
    buscaRanking: '',
    resumo: null,
    chartMensalInst: null,
    chartDiarioInst: null,
};

const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);

function filtroBase() {
    return {
        filial: $('selFilial').value,
        inicio: $('dtIni').value,
        fim: $('dtFim').value
    };
}

function atalho(tipo) {
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

    $('dtIni').value = iso(a);
    $('dtFim').value = iso(b);
    document.querySelectorAll('#atalhos .vf-chip').forEach((c) => c.classList.toggle('on', c.dataset.p === tipo));
    recarregarAbaAtiva();
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

        // 6 KPIs
        boxKpi.innerHTML = `
            <div class="vf-kpi bg-gradient-to-br from-emerald-600 to-teal-700 text-white shadow-md">
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

            <div class="vf-kpi bg-gradient-to-br from-rose-600 to-red-700 text-white shadow-md">
                <div class="flex items-center justify-between opacity-85 text-[11px] font-bold uppercase tracking-wider">
                    <span>Retiradas em Bonificações</span>
                    <i class="fa-solid fa-arrow-trend-down text-base"></i>
                </div>
                <div class="text-2xl font-black mt-1">${brl(deb)}</div>
                <div class="text-xs opacity-90 mt-1 font-semibold">
                    <span>${stats.qtd_debitos || 0} nota(s) fiscais</span>
                    ${variacao(deb, r.anterior?.debitos)}
                </div>
            </div>

            <div class="vf-kpi bg-gradient-to-br ${resultado >= 0 ? 'from-violet-700 to-purple-800' : 'from-amber-600 to-orange-700'} text-white shadow-md">
                <div class="flex items-center justify-between opacity-85 text-[11px] font-bold uppercase tracking-wider">
                    <span>Saldo Líquido / Resultado</span>
                    <i class="fa-solid fa-scale-balanced text-base"></i>
                </div>
                <div class="text-2xl font-black mt-1">${brl(resultado)}</div>
                <div class="text-xs opacity-90 mt-1 font-semibold">
                    <span>${resultado >= 0 ? 'Saldo Positivo de Verba' : 'Déficit no Período'}</span>
                </div>
            </div>

            <div class="vf-kpi bg-slate-50 border border-slate-200 text-slate-800 shadow-xs">
                <div class="flex items-center justify-between text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                    <span>Média por Retirada</span>
                    <i class="fa-solid fa-calculator text-slate-400 text-base"></i>
                </div>
                <div class="text-xl font-black text-slate-800 mt-1">${brl(stats.ticket_medio || 0)}</div>
                <div class="text-xs text-slate-400 font-semibold mt-1">Ticket médio por nota fiscal</div>
            </div>

            <div class="vf-kpi bg-slate-50 border border-slate-200 text-slate-800 shadow-xs">
                <div class="flex items-center justify-between text-slate-400 text-[11px] font-bold uppercase tracking-wider">
                    <span>Maior Retirada Única</span>
                    <i class="fa-solid fa-bolt text-amber-500 text-base"></i>
                </div>
                <div class="text-xl font-black text-red-600 mt-1">${brl(stats.maior_debito || 0)}</div>
                <div class="text-xs text-slate-400 font-semibold mt-1">Pico máximo em uma única nota</div>
            </div>

            <div class="vf-kpi bg-slate-50 border border-slate-200 text-slate-800 shadow-xs">
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

async function carregarRankingCompleto() {
    const host = $('listaRankingCompleto');
    host.innerHTML = '<div class="py-12 text-center text-slate-400"><i class="fa-solid fa-spinner fa-spin text-xl text-violet-600 block mb-2"></i>Carregando ranking geral...</div>';

    try {
        if (!estado.resumo) {
            estado.resumo = await apiFetch('verbas-flex/resumo', 'GET', filtroBase());
        }
        estado.ranking = estado.resumo.ranking || [];
        $('contTotalReps').textContent = estado.ranking.length;
        renderRankingCompleto();
    } catch (e) {
        host.innerHTML = `<div class="py-6 text-center text-rose-500 text-sm font-bold">${esc(e.message)}</div>`;
    }
}

function renderRankingCompleto() {
    const host = $('listaRankingCompleto');
    const q = estado.buscaRanking.toLowerCase();
    const limit = estado.filtroRankingQtd;

    let lista = estado.ranking.filter((r) => {
        if (q && !r.nome.toLowerCase().includes(q)) return false;
        return true;
    });

    if (limit === '5') lista = lista.slice(0, 5);
    else if (limit === '10') lista = lista.slice(0, 10);

    if (!lista.length) {
        host.innerHTML = '<div class="py-10 text-center text-slate-400 text-xs font-semibold">Nenhum representante encontrado para este filtro.</div>';
        return;
    }

    const maxR = Math.max(1, ...estado.ranking.map((x) => Number(x.total)));

    host.innerHTML = lista.map((x, i) => {
        const medalha = i === 0
            ? '<span class="rank-badge bg-amber-100 text-amber-700 border border-amber-300 shadow-xs"><i class="fa-solid fa-medal"></i> 1º</span>'
            : i === 1
            ? '<span class="rank-badge bg-slate-200 text-slate-700 border border-slate-300 shadow-xs"><i class="fa-solid fa-medal"></i> 2º</span>'
            : i === 2
            ? '<span class="rank-badge bg-amber-700/10 text-amber-900 border border-amber-600/30 shadow-xs"><i class="fa-solid fa-medal"></i> 3º</span>'
            : `<span class="rank-badge bg-slate-100 text-slate-500 font-bold">${i + 1}º</span>`;

        return `
            <div class="p-4 rounded-2xl border border-slate-200 bg-white hover:border-violet-300 hover:shadow-xs transition">
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
                            <span><b>${x.qtd}</b> nota(s) faturadas</span>
                            ${x.maior_retirada ? `<span>·</span><span>Maior: <b>${brl(x.maior_retirada)}</b></span>` : ''}
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3 mt-3 pt-2.5 border-t border-slate-100">
                    <div class="flex-1 bg-slate-100 h-3 rounded-full overflow-hidden">
                        <div class="h-full bg-gradient-to-r from-violet-600 via-purple-600 to-indigo-500 rounded-full" style="width:${(Number(x.total) / maxR) * 100}%"></div>
                    </div>
                    <span class="text-xs font-black text-violet-800 bg-violet-100/70 px-2.5 py-0.5 rounded-lg flex-none">${x.percentual || 0}% da verba</span>
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

    // Filtros de Ranking
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
        if (!estado.ranking.length) return aviso('warning', 'Sem Dados', 'Nenhum dado de ranking para exportar.');
        const cols = ['Posição', 'Código Rep.', 'Representante', 'Total Consumido (R$)', 'Qtd Notas', 'Maior Retirada (R$)', '% Participação'];
        const rows = estado.ranking.map((r, i) => [
            `${i + 1}º`,
            r.id,
            r.nome,
            r.total.toFixed(2).replace('.', ','),
            r.qtd,
            (r.maior_retirada || 0).toFixed(2).replace('.', ','),
            `${r.percentual || 0}%`
        ]);
        baixarCSV(`ranking_verbas_flex_${iso(new Date())}.csv`, cols, rows);
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

    carregarPainel();
});
