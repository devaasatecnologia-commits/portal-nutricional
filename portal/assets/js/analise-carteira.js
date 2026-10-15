// ==========================================================================
// ANÁLISE DE CARTEIRA - CARDS RASTREÁVEIS + GRÁFICOS INTERATIVOS
// Identidade, perfil admin e escopo de gestores são decididos pelo backend (JWT).
// ==========================================================================

const analiseCarteiraApp = {
    gestorSelecionado: null,
    representanteSelecionado: null,
    nomeRepresentante: '',
    nomeGestor: '',
    dadosRepresentantes: [],
    dadosTitulos: [],
    chartPizza: null,
    chartRanking: null,
    kpis: null,
    abortDados: null,
    abortTitulos: null,
    seqDados: 0,
    seqTitulos: 0,
    carregando: 0,

    async init() {
        if (!this.getToken()) { window.location.href = '/portal/login.php'; return; }
        this.bindEvents();
        await this.carregarGestores();
    },

    getToken() {
        return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
    },

    // ----- utilidades -----
    esc(v) {
        return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    },

    fmtMoeda(v) {
        return 'R$ ' + (parseFloat(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    fmtPerc(v, casas = 1) {
        return (parseFloat(v) || 0).toFixed(casas) + '%';
    },

    fmtData(iso) {
        const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
        return m ? `${m[3]}/${m[2]}/${m[1]}` : (iso || '-');
    },

    slug(s) {
        return String(s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^A-Za-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 60) || 'sem_nome';
    },

    classePerformance(p) {
        return p === 'CRÍTICO' ? 'badge-critico' : (p === 'ATENÇÃO' ? 'badge-atencao' : 'badge-saudavel');
    },

    async fetchWithAuth(url, options = {}) {
        const token = this.getToken();
        if (!token) { window.location.href = '/portal/login.php'; throw new Error('Sem token'); }
        const resp = await fetch(url, {
            ...options,
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', ...(options.headers || {}) }
        });
        if (resp.status === 401) {
            localStorage.clear(); sessionStorage.clear();
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada');
        }
        if (!resp.ok) {
            let msg = `Erro ${resp.status} ao consultar.`;
            try { const j = await resp.json(); if (j && j.error) msg = j.error; } catch { /* corpo não JSON */ }
            throw new Error(msg);
        }
        return resp;
    },

    async postJson(url, corpo, signal) {
        const resp = await this.fetchWithAuth(url, { method: 'POST', body: JSON.stringify(corpo), signal });
        const dados = await resp.json();
        if (dados && dados.error) throw new Error(dados.error);
        return dados;
    },

    ehAbort(e) { return e && e.name === 'AbortError'; },

    // ----- eventos -----
    bindEvents() {
        const selGestor = document.getElementById('select-gestor');
        const selRep = document.getElementById('select-representante');

        selGestor.addEventListener('change', async (e) => {
            const v = e.target.value;
            this.gestorSelecionado = v !== '' ? parseInt(v, 10) : null;
            this.representanteSelecionado = null;
            this.nomeRepresentante = '';
            this.nomeGestor = e.target.selectedOptions[0]?.text || '';
            selRep.value = '';
            document.getElementById('secao-titulos').style.display = 'none';
            if (this.abortTitulos) this.abortTitulos.abort();
            if (this.gestorSelecionado !== null) await this.carregarDadosRepresentantes();
            else { if (this.abortDados) this.abortDados.abort(); this.limparTudo(); }
        });

        selRep.addEventListener('change', async (e) => {
            if (e.target.value !== '') {
                await this.selecionarRepresentante(parseInt(e.target.value, 10), e.target.selectedOptions[0]?.text || '');
            } else {
                this.representanteSelecionado = null;
                if (this.abortTitulos) this.abortTitulos.abort();
                document.getElementById('secao-titulos').style.display = 'none';
            }
        });

        document.getElementById('tabela-rep-body').addEventListener('click', (e) => {
            const tr = e.target.closest('tr[data-rep-id]');
            if (tr) this.selecionarRepresentante(parseInt(tr.dataset.repId, 10), tr.dataset.repNome || '');
        });
    },

    async selecionarRepresentante(id, nome) {
        this.representanteSelecionado = id;
        this.nomeRepresentante = nome;
        const sel = document.getElementById('select-representante');
        if (sel) sel.value = String(id);
        const ok = await this.carregarTitulos();
        if (!ok) return;
        const secao = document.getElementById('secao-titulos');
        secao.style.display = 'block';
        document.getElementById('titulos-nome-rep').innerText = `Representante: ${nome} | Gestor: ${this.nomeGestor}`;
        secao.scrollIntoView({ behavior: 'smooth' });
    },

    // ----- carregamento -----
    async carregarGestores() {
        const select = document.getElementById('select-gestor');
        try {
            const resp = await this.fetchWithAuth('/v1/analise-carteira/gestores', { method: 'POST', body: '{}' });
            const gestores = await resp.json();
            const lista = Array.isArray(gestores) ? gestores : [];
            if (!lista.length) { select.innerHTML = '<option value="">Nenhum gestor disponível</option>'; return; }
            select.innerHTML = '<option value="">Selecione um gestor...</option>' +
                lista.map(g => `<option value="${this.esc(g.id)}">${this.esc(g.id === 0 ? 'Sem gestor' : g.nome)}</option>`).join('');
            if (lista.length === 1) {
                select.value = String(lista[0].id);
                this.gestorSelecionado = parseInt(lista[0].id, 10);
                this.nomeGestor = select.selectedOptions[0]?.text || '';
                await this.carregarDadosRepresentantes();
            }
        } catch (e) {
            console.error('Erro gestores:', e);
            select.innerHTML = '<option value="">Erro ao carregar gestores</option>';
            if (e.message !== 'Sessão expirada') Swal.fire('Erro', e.message, 'error');
        }
    },

    async carregarDadosRepresentantes() {
        if (this.gestorSelecionado === null) return;
        if (this.abortDados) this.abortDados.abort();
        const ctrl = this.abortDados = new AbortController();
        const seq = ++this.seqDados;
        const corpo = { id_gestor: this.gestorSelecionado };
        this.showShimmer();
        try {
            const [tabela, resumo] = await Promise.all([
                this.postJson('/v1/analise-carteira/tabela-gestor', corpo, ctrl.signal),
                this.postJson('/v1/analise-carteira/resumo-gestor', corpo, ctrl.signal)
            ]);
            if (seq !== this.seqDados) return;
            this.dadosRepresentantes = Array.isArray(tabela) ? tabela : [];
            this.kpis = resumo.kpis || null;
            this.popularSelectRepresentantes();
            this.atualizarKPIs();
            this.renderizarResumoGestor();
            this.renderizarTabelaRepresentantes();
            this.renderizarGraficoPizza();
            this.renderizarGraficoRanking();
            document.getElementById('subtitulo-header').innerText = `Gestor: ${this.nomeGestor} | ${this.dadosRepresentantes.length} representantes`;
        } catch (e) {
            if (this.ehAbort(e) || seq !== this.seqDados) return;
            console.error('Erro dados:', e);
            this.mostrarErro(e.message);
            if (e.message !== 'Sessão expirada') Swal.fire('Erro', e.message, 'error');
        } finally { this.hideShimmer(); }
    },

    popularSelectRepresentantes() {
        const select = document.getElementById('select-representante');
        const reps = this.dadosRepresentantes
            .map(r => ({ id: r.id_representante, nome: r.nome_representante || 'Sem representante' }))
            .sort((a, b) => a.nome.localeCompare(b.nome));
        select.innerHTML = '<option value="">Todos os representantes</option>' +
            reps.map(r => `<option value="${this.esc(r.id)}">${this.esc(r.nome)}</option>`).join('');
        select.value = this.representanteSelecionado !== null ? String(this.representanteSelecionado) : '';
    },

    async carregarTitulos() {
        if (this.representanteSelecionado === null) return false;
        if (this.abortTitulos) this.abortTitulos.abort();
        const ctrl = this.abortTitulos = new AbortController();
        const seq = ++this.seqTitulos;
        this.showShimmer();
        try {
            const dados = await this.postJson('/v1/analise-carteira/titulos-representante', {
                id_gestor: this.gestorSelecionado,
                id_representante: this.representanteSelecionado
            }, ctrl.signal);
            if (seq !== this.seqTitulos) return false;
            this.dadosTitulos = Array.isArray(dados) ? dados : [];
            const total = this.dadosTitulos.reduce((s, t) => s + (parseFloat(t.valor_saldo) || 0), 0);
            document.getElementById('tabela-titulos-info').innerText = `${this.dadosTitulos.length} título(s) | Total: ${this.fmtMoeda(total)}`;
            this.renderizarTabelaTitulos(this.dadosTitulos);
            return true;
        } catch (e) {
            if (this.ehAbort(e) || seq !== this.seqTitulos) return false;
            console.error('Erro títulos:', e);
            this.dadosTitulos = [];
            document.getElementById('tabela-titulos-info').innerText = '';
            document.getElementById('tabela-titulos-body').innerHTML =
                `<tr><td colspan="8" class="text-center py-8 text-rose-500">${this.esc(e.message)}</td></tr>`;
            document.getElementById('secao-titulos').style.display = 'block';
            if (e.message !== 'Sessão expirada') Swal.fire('Erro', e.message, 'error');
            return false;
        } finally { this.hideShimmer(); }
    },

    mostrarErro(msg) {
        this.dadosRepresentantes = [];
        this.kpis = null;
        this.zerarKPIs();
        document.getElementById('secao-resumo-gestor').style.display = 'none';
        document.getElementById('tabela-rep-info').innerText = '';
        document.getElementById('tabela-rep-body').innerHTML =
            `<tr><td colspan="13" class="text-center py-12 text-rose-500">${this.esc(msg || 'Erro ao carregar os dados')}</td></tr>`;
        this.mensagemGraficos('Erro ao carregar');
    },

    mensagemGraficos(txt) {
        if (this.chartPizza) { this.chartPizza.destroy(); this.chartPizza = null; }
        if (this.chartRanking) { this.chartRanking.destroy(); this.chartRanking = null; }
        ['#chart-pizza', '#chart-ranking'].forEach(s => {
            const el = document.querySelector(s);
            if (el) el.innerHTML = `<div class="flex items-center justify-center h-full text-slate-400">${this.esc(txt)}</div>`;
        });
    },

    // ======================================================================
    // KPIs - todos vindos do backend (clientes distintos reais, sem soma por linha)
    // ======================================================================
    atualizarKPIs() {
        const k = this.kpis;
        if (!k) { this.zerarKPIs(); return; }
        const totalVencido = k.dias_30 + k.dias_60 + k.mais_60;

        this.setText('kpi-vencidos', this.fmtMoeda(k.vencidos));
        this.setText('kpi-vencidos-perc', this.fmtPerc(k.iag) + ' da carteira');
        this.setText('kpi-iag', this.fmtPerc(k.iag, 2));
        this.setText('kpi-carteira', this.fmtMoeda(k.total_receber));
        this.setText('kpi-carteira-info', `${k.titulos_total} títulos | ${k.clientes_total} clientes`);
        this.setText('kpi-titulos', k.titulos_vencidos);
        this.setText('kpi-titulos-clientes', `${k.clientes_vencidos} clientes afetados`);
        this.setText('kpi-a-vencer', this.fmtMoeda(k.a_vencer));
        this.setText('kpi-a-vencer-sub', `Janela de 30 dias (hoje-3 a hoje+27): ${this.fmtMoeda(k.prox_30_dias)}`);

        this.setText('pizza-30d', this.fmtMoeda(k.dias_30));
        this.setText('pizza-60d', this.fmtMoeda(k.dias_60));
        this.setText('pizza-90d', this.fmtMoeda(k.mais_60));
        const p = (v) => totalVencido > 0 ? this.fmtPerc(v / totalVencido * 100) : '0%';
        this.setText('pizza-30d-perc', p(k.dias_30));
        this.setText('pizza-60d-perc', p(k.dias_60));
        this.setText('pizza-90d-perc', p(k.mais_60));
    },

    zerarKPIs() {
        const padroes = {
            'kpi-vencidos': 'R$ 0,00', 'kpi-vencidos-perc': '0% da carteira', 'kpi-iag': '0%',
            'kpi-carteira': 'R$ 0,00', 'kpi-carteira-info': '0 títulos | 0 clientes',
            'kpi-titulos': '0', 'kpi-titulos-clientes': '0 clientes afetados',
            'kpi-a-vencer': 'R$ 0,00', 'kpi-a-vencer-sub': 'Janela de 30 dias (hoje-3 a hoje+27)',
            'pizza-30d': 'R$ 0,00', 'pizza-60d': 'R$ 0,00', 'pizza-90d': 'R$ 0,00',
            'pizza-30d-perc': '0%', 'pizza-60d-perc': '0%', 'pizza-90d-perc': '0%'
        };
        Object.entries(padroes).forEach(([id, txt]) => this.setText(id, txt));
    },

    // ======================================================================
    // RESUMO GESTOR
    // ======================================================================
    renderizarResumoGestor() {
        const k = this.kpis;
        const container = document.getElementById('secao-resumo-gestor');
        if (!k || !this.dadosRepresentantes.length) { container.style.display = 'none'; return; }
        const pv = (v) => k.vencidos > 0 ? this.fmtPerc(v / k.vencidos * 100) : '0%';

        container.style.display = 'block';
        document.getElementById('tabela-resumo-gestor-body').innerHTML = `
        <tr class="bg-gradient-to-r from-amber-50 to-white font-bold">
            <td class="px-4 py-3"><i class="fa-solid fa-user-tie mr-2 text-amber-600"></i>${this.esc(this.nomeGestor)}</td>
            <td class="px-4 py-3 text-right text-rose-600">${this.fmtMoeda(k.vencidos)}</td>
            <td class="px-4 py-3 text-right">${this.fmtMoeda(k.dias_30)}</td>
            <td class="px-4 py-3 text-center text-blue-600">${pv(k.dias_30)}</td>
            <td class="px-4 py-3 text-right">${this.fmtMoeda(k.dias_60)}</td>
            <td class="px-4 py-3 text-center text-amber-600">${pv(k.dias_60)}</td>
            <td class="px-4 py-3 text-right">${this.fmtMoeda(k.mais_60)}</td>
            <td class="px-4 py-3 text-center text-rose-600">${pv(k.mais_60)}</td>
            <td class="px-4 py-3 text-center"><span class="${this.classePerformance(k.performance)}">${this.esc(k.performance)} (${this.fmtPerc(k.iag)})</span></td>
            <td class="px-4 py-3 text-right text-emerald-600">${this.fmtMoeda(k.a_vencer)}</td>
        </tr>`;
    },

    // ======================================================================
    // TABELA REPRESENTANTES
    // ======================================================================
    renderizarTabelaRepresentantes() {
        const d = this.dadosRepresentantes;
        document.getElementById('tabela-rep-info').innerText = `${d.length} representante(s) | Gestor: ${this.nomeGestor}`;
        const tbody = document.getElementById('tabela-rep-body');
        if (!d.length) { tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nenhum representante com títulos em aberto</td></tr>'; return; }

        tbody.innerHTML = d.map(r => `<tr class="border-b border-slate-100 hover:bg-blue-50 cursor-pointer transition-all"
                        data-rep-id="${this.esc(r.id_representante)}" data-rep-nome="${this.esc(r.nome_representante)}"
                        title="Clique para ver títulos">
                <td class="px-3 py-3 font-bold text-slate-800">${this.esc(r.nome_representante)}</td>
                <td class="px-3 py-3 text-right font-bold text-rose-600">${this.fmtMoeda(r.vencidos)}</td>
                <td class="px-3 py-3 text-right">${this.fmtMoeda(r.dias_30)}</td>
                <td class="px-3 py-3 text-center text-blue-600 font-bold">${this.fmtPerc(r.perc_30)}</td>
                <td class="px-3 py-3 text-right">${this.fmtMoeda(r.dias_60)}</td>
                <td class="px-3 py-3 text-center text-amber-600 font-bold">${this.fmtPerc(r.perc_60)}</td>
                <td class="px-3 py-3 text-right">${this.fmtMoeda(r.mais_60)}</td>
                <td class="px-3 py-3 text-center text-rose-600 font-bold">${this.fmtPerc(r.perc_mais_60)}</td>
                <td class="px-3 py-3 text-center font-bold text-purple-600">${this.fmtPerc(r.percentual)}</td>
                <td class="px-3 py-3 text-center font-bold">${this.esc(r.titulos_vencidos)}</td>
                <td class="px-3 py-3 text-center">${this.esc(r.clientes_vencidos)}</td>
                <td class="px-3 py-3 text-center"><span class="${this.classePerformance(r.performance)}">${this.esc(r.performance)} (${this.fmtPerc(r.iag)})</span></td>
                <td class="px-3 py-3 text-right text-emerald-600 font-medium">${this.fmtMoeda(r.a_vencer)}</td>
            </tr>`).join('');
    },

    // ======================================================================
    // TABELA TÍTULOS
    // ======================================================================
    renderizarTabelaTitulos(dados) {
        const tbody = document.getElementById('tabela-titulos-body');
        if (!dados.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Nenhum título vencido</td></tr>'; return; }
        tbody.innerHTML = dados.map(t => {
            const da = parseInt(t.dias_atraso || 0, 10);
            const de = parseInt(t.ult_evento_dias || 0, 10);
            const ba = da > 60 ? 'badge-critico' : (da > 30 ? 'badge-atencao' : 'badge-dias');
            return `<tr class="border-b border-slate-100 hover:bg-slate-50 transition-all" data-chave="${this.esc(t.chave)}">
                <td class="px-3 py-2.5 font-medium">${this.esc(t.nome_fantasia || '-')}</td>
                <td class="px-3 py-2.5 font-mono text-xs">${this.esc(t.documento || '-')}</td>
                <td class="px-3 py-2.5 text-center text-xs">${this.esc(this.fmtData(t.vencimento))}</td>
                <td class="px-3 py-2.5 text-right font-bold text-rose-600">${this.fmtMoeda(t.valor_saldo)}</td>
                <td class="px-3 py-2.5 text-center"><span class="${ba}">${da} d</span></td>
                <td class="px-3 py-2.5 text-center">${de > 0 ? `<span class="badge-evento">${de} d</span>` : '<span class="badge-sem-evento">Sem evento</span>'}</td>
                <td class="px-3 py-2.5 text-xs">${this.esc(t.usuario || '-')}</td>
                <td class="px-3 py-2.5 text-xs">${t.evento ? `<span class="font-medium text-emerald-700">${this.esc(t.evento)}</span>` : '<span class="text-rose-500 font-bold">Sem apontamento</span>'}</td>
            </tr>`;
        }).join('');
    },

    // ======================================================================
    // GRÁFICO PIZZA (clique na fatia lista os representantes da faixa)
    // ======================================================================
    renderizarGraficoPizza() {
        if (this.chartPizza) { this.chartPizza.destroy(); this.chartPizza = null; }
        const k = this.kpis || { dias_30: 0, dias_60: 0, mais_60: 0 };
        const el = document.querySelector('#chart-pizza');
        if (!el) return;
        if (k.dias_30 + k.dias_60 + k.mais_60 === 0) { el.innerHTML = '<div class="flex items-center justify-center h-full text-slate-400">Sem vencidos</div>'; return; }
        el.innerHTML = '';
        this.chartPizza = new ApexCharts(el, {
            series: [k.dias_30, k.dias_60, k.mais_60],
            chart: { type: 'donut', height: 280, toolbar: { show: false },
                events: {
                    dataPointSelection: (event, chartContext, config) => {
                        const faixas = ['30 Dias', '60 Dias', '+60 Dias'];
                        const faixa = faixas[config.dataPointIndex];
                        if (faixa) this.mostrarRepresentantesPorFaixa(faixa);
                    }
                }
            },
            labels: ['30 Dias', '60 Dias', '+60 Dias'],
            colors: ['#3b82f6', '#f59e0b', '#ef4444'],
            plotOptions: { pie: { donut: { size: '55%',
                labels: { show: true, name: { fontSize: '10px' },
                    value: { fontSize: '12px', fontWeight: 'bold', formatter: (v) => 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) },
                    total: { show: true, label: 'Total Vencido', fontSize: '12px', fontWeight: 'bold',
                        formatter: (w) => 'R$ ' + w.globals.seriesTotals.reduce((a, b) => a + b, 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) }
                } } } },
            legend: { position: 'bottom', fontSize: '11px' },
            tooltip: { y: { formatter: (v) => this.fmtMoeda(v) } }
        });
        this.chartPizza.render();
    },

    mostrarRepresentantesPorFaixa(faixa) {
        const campo = faixa === '30 Dias' ? 'dias_30' : (faixa === '60 Dias' ? 'dias_60' : 'mais_60');
        const reps = this.dadosRepresentantes
            .filter(r => r[campo] > 0)
            .sort((a, b) => b[campo] - a[campo]);

        if (!reps.length) {
            Swal.fire('Informação', `Nenhum representante com vencidos em ${faixa}`, 'info');
            return;
        }

        const linhas = reps.map(r => `<tr class="border-b hover:bg-blue-50">
                <td class="p-2 font-medium">${this.esc(r.nome_representante)}</td>
                <td class="p-2 text-right font-bold text-rose-600">${this.fmtMoeda(r[campo])}</td>
                <td class="p-2 text-center">
                    <button type="button" data-rep-id="${this.esc(r.id_representante)}" data-rep-nome="${this.esc(r.nome_representante)}"
                            class="px-2 py-1 bg-blue-500 text-white rounded text-xs font-bold hover:bg-blue-600">Ver Títulos</button>
                </td>
            </tr>`).join('');

        Swal.fire({
            title: `Representantes com vencidos em ${faixa}`,
            html: `<div class="text-left text-xs" style="max-height: 400px; overflow-y: auto;">
                <table class="w-full">
                <thead><tr class="bg-slate-100">
                    <th class="p-2 text-left">Representante</th>
                    <th class="p-2 text-right">Valor</th>
                    <th class="p-2 text-center">Ação</th>
                </tr></thead><tbody>${linhas}</tbody></table></div>`,
            width: '650px',
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#375a4b',
            didOpen: (popup) => {
                popup.querySelectorAll('button[data-rep-id]').forEach(btn => btn.addEventListener('click', () => {
                    Swal.close();
                    this.selecionarRepresentante(parseInt(btn.dataset.repId, 10), btn.dataset.repNome || '');
                }));
            }
        });
    },

    // ======================================================================
    // GRÁFICO RANKING: barras verticais (vencido R$) + linha (IAG %)
    // ======================================================================
    renderizarGraficoRanking() {
        if (this.chartRanking) { this.chartRanking.destroy(); this.chartRanking = null; }
        const top10 = [...this.dadosRepresentantes].sort((a, b) => b.vencidos - a.vencidos).slice(0, 10);
        const el = document.querySelector('#chart-ranking');
        if (!el) return;
        if (!top10.length) { el.innerHTML = '<div class="flex items-center justify-center h-full text-slate-400">Sem dados</div>'; return; }
        el.innerHTML = '';
        const nomes = top10.map(r => r.nome_representante || 'Sem representante');
        const abreviar = (s) => s.length > 16 ? s.slice(0, 15) + '…' : s;

        this.chartRanking = new ApexCharts(el, {
            series: [
                { name: 'Vencidos (R$)', type: 'column', data: top10.map(r => r.vencidos) },
                { name: 'IAG (%)', type: 'line', data: top10.map(r => r.iag) }
            ],
            chart: { height: 320, type: 'line', toolbar: { show: false },
                events: {
                    // única fonte de clique (evita disparo duplo)
                    dataPointSelection: (event, chartContext, config) => {
                        const rep = top10[config.dataPointIndex];
                        if (rep) this.selecionarRepresentante(rep.id_representante, rep.nome_representante);
                    }
                }
            },
            colors: ['#ef4444', '#f59e0b'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
            stroke: { width: [0, 3], curve: 'smooth' },
            markers: { size: [0, 5], colors: ['#f59e0b'] },
            dataLabels: { enabled: false },
            xaxis: { categories: nomes, labels: { rotate: -35, rotateAlways: true, trim: false, style: { fontSize: '10px' }, formatter: (v) => abreviar(String(v)) } },
            yaxis: [
                { title: { text: 'R$' }, labels: { formatter: (v) => 'R$ ' + (v / 1000).toFixed(0) + 'k' } },
                { opposite: true, title: { text: 'IAG %' }, labels: { formatter: (v) => v.toFixed(0) + '%' } }
            ],
            tooltip: { shared: true, intersect: false,
                x: { formatter: (v, o) => nomes[(o && o.dataPointIndex) ?? 0] || String(v) },
                y: [
                    { formatter: (v) => this.fmtMoeda(v) },
                    { formatter: (v) => v.toFixed(2) + '%' }
                ]
            },
            legend: { position: 'bottom', fontSize: '11px' },
            grid: { borderColor: '#f1f5f9' }
        });
        this.chartRanking.render();
    },

    setText(id, text) { const el = document.getElementById(id); if (el) el.innerText = text; },

    limparTudo() {
        this.dadosRepresentantes = [];
        this.dadosTitulos = [];
        this.kpis = null;
        document.getElementById('secao-titulos').style.display = 'none';
        document.getElementById('secao-resumo-gestor').style.display = 'none';
        document.getElementById('select-representante').innerHTML = '<option value="">Todos os representantes</option>';
        this.zerarKPIs();
        document.getElementById('tabela-rep-info').innerText = '';
        document.getElementById('tabela-rep-body').innerHTML = '<tr><td colspan="13" class="text-center py-12 text-slate-400">Selecione um gestor</td></tr>';
        document.getElementById('tabela-resumo-gestor-body').innerHTML = '';
        document.getElementById('tabela-titulos-body').innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Selecione um representante</td></tr>';
        document.getElementById('subtitulo-header').innerText = 'Inadimplência por Gestor e Representante';
        this.mensagemGraficos('Aguardando gestor');
    },

    showShimmer() { this.carregando++; const b = document.getElementById('shimmer-bar'); if (b) b.style.display = 'block'; },
    hideShimmer() { this.carregando = Math.max(0, this.carregando - 1); if (this.carregando === 0) { const b = document.getElementById('shimmer-bar'); if (b) b.style.display = 'none'; } }
};

// ===== FUNÇÕES GLOBAIS (usadas por onclick estático do módulo) =====
function scrollToTable(id) { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' }); }

// Aplica formato numérico (#,##0.00) às colunas de valor da planilha.
function xlsxFormatar(ws, colunas, formato) {
    const ref = XLSX.utils.decode_range(ws['!ref']);
    for (let r = ref.s.r + 1; r <= ref.e.r; r++) {
        colunas.forEach(c => {
            const cel = ws[XLSX.utils.encode_cell({ r, c })];
            if (cel && typeof cel.v === 'number') cel.z = formato;
        });
    }
}

function baixarXlsx(linhas, aba, nomeBase, colsMoeda, colsPerc) {
    const ws = XLSX.utils.json_to_sheet(linhas);
    xlsxFormatar(ws, colsMoeda, '#,##0.00');
    xlsxFormatar(ws, colsPerc, '0.00');
    ws['!cols'] = Object.keys(linhas[0]).map(k => ({ wch: Math.max(12, k.length + 2) }));
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, aba);
    XLSX.writeFile(wb, `${nomeBase}-${new Date().toISOString().split('T')[0]}.xlsx`);
}

async function exportarExcelCompleto() {
    const app = analiseCarteiraApp;
    if (!app.dadosRepresentantes.length) { Swal.fire('Atenção', 'Nenhum dado', 'warning'); return; }
    const linhas = app.dadosRepresentantes.map(r => ({
        'Representante': r.nome_representante,
        'Vencidos (R$)': r.vencidos,
        '30 Dias (R$)': r.dias_30,
        '% 30 Dias': r.perc_30,
        '60 Dias (R$)': r.dias_60,
        '% 60 Dias': r.perc_60,
        '+60 Dias (R$)': r.mais_60,
        '% +60 Dias': r.perc_mais_60,
        '% do Total Vencido': r.percentual,
        'Títulos Vencidos': r.titulos_vencidos,
        'Clientes Vencidos': r.clientes_vencidos,
        'IAG (%)': r.iag,
        'Performance': r.performance,
        'À Vencer (R$)': r.a_vencer,
        'Carteira Total (R$)': r.total_receber
    }));
    baixarXlsx(linhas, 'Representantes', `analise-carteira-${app.slug(app.nomeGestor)}`, [1, 2, 4, 6, 13, 14], [3, 5, 7, 8, 11]);
}

async function exportarExcelTitulos() {
    const app = analiseCarteiraApp;
    if (!app.dadosTitulos.length) { Swal.fire('Atenção', 'Nenhum título', 'warning'); return; }
    const linhas = app.dadosTitulos.map(t => ({
        'Nome Fantasia': t.nome_fantasia,
        'Documento': t.documento,
        'Vencimento': app.fmtData(t.vencimento),
        'Valor Saldo (R$)': t.valor_saldo,
        'Dias desde o Vencimento': t.dias_atraso,
        'Dias do Último Evento': t.ult_evento_dias ?? '',
        'Usuário': t.usuario || '',
        'Registro do Evento': t.evento || 'Sem apontamento'
    }));
    baixarXlsx(linhas, 'Titulos', `titulos-${app.slug(app.nomeRepresentante)}`, [3], []);
}

document.addEventListener('DOMContentLoaded', () => {
    window.analiseCarteiraApp = analiseCarteiraApp;
    analiseCarteiraApp.init();
});
