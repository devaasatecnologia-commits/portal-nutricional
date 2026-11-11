// ==========================================================================
// ANÁLISE DE CARTEIRA - CARDS RASTREÁVEIS + GRÁFICOS INTERATIVOS
// Identidade, perfil admin e escopo de gestores são decididos pelo backend (JWT).
// ==========================================================================

const analiseCarteiraApp = {
    gestorSelecionado: '',
    representanteSelecionado: null,
    nomeRepresentante: '',
    nomeGestor: 'Todos os Gestores',
    periodo: 'este_mes',
    tipoPeriodo: 'vencimento',
    dataInicio: '',
    dataFim: '',
    todosGestoresPermitido: false,
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
        
        // Padrão: Mês Corrente
        const hoje = new Date();
        const ano = hoje.getFullYear();
        const mes = hoje.getMonth();
        const pad = (n) => String(n).padStart(2, '0');
        const ultDia = new Date(ano, mes + 1, 0);
        this.dataInicio = `${ano}-${pad(mes + 1)}-01`;
        this.dataFim = `${ano}-${pad(mes + 1)}-${pad(ultDia.getDate())}`;

        this.bindEvents();
        await this.carregarGestores();
    },

    getToken() {
        return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token') || '';
    },

    // ----- utilidades -----
    resolverUrl(endpoint) {
        if (/^https?:\/\//i.test(endpoint)) return endpoint;
        
        let clean = String(endpoint || '').replace(/^\/+/, '');
        if (clean.startsWith('v1/')) clean = clean.substring(3);
        clean = clean.replace(/^\/+/, '');

        if (typeof window !== 'undefined' && window.API_URL) {
            const apiRoot = window.API_URL.replace(/\/$/, '');
            if (apiRoot.includes('api_route=')) {
                return apiRoot + '/' + clean;
            }
            return apiRoot + '/' + clean;
        }

        const isLocal = ['localhost', '127.0.0.1', '::1'].includes(window.location.hostname) || 
                        window.location.hostname.startsWith('192.168.');
        if (isLocal) {
            const appBase = window.location.pathname.split('/portal/')[0] || '';
            return window.location.origin + appBase + '/index.php?api_route=/' + clean;
        }
        return 'https://api.nutricionalbr.com/v1/' + clean;
    },

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
        const appBase = window.location.pathname.split('/portal/')[0] || '';
        if (!token) { window.location.href = appBase + '/portal/login.php'; throw new Error('Sem token'); }
        const finalUrl = this.resolverUrl(url);
        const resp = await fetch(finalUrl, {
            ...options,
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', ...(options.headers || {}) }
        });
        if (resp.status === 401) {
            localStorage.clear(); sessionStorage.clear();
            window.location.href = appBase + '/portal/login.php';
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
            this.gestorSelecionado = v !== '' ? parseInt(v, 10) : '';
            this.representanteSelecionado = null;
            this.nomeRepresentante = '';
            this.nomeGestor = e.target.selectedOptions[0]?.text || 'Todos os Gestores';
            selRep.value = '';
            document.getElementById('secao-titulos').style.display = 'none';
            if (this.abortTitulos) this.abortTitulos.abort();
            await this.carregarDadosRepresentantes();
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

    // ----- controle de períodos rápidos -----
    aplicarPeriodoRapido(tipo) {
        this.periodo = tipo;
        const painelData = document.getElementById('painel-datas-personalizadas');
        if (painelData) painelData.style.display = 'none';

        document.querySelectorAll('.btn-periodo').forEach(b => {
            if (b.dataset.periodo === tipo) {
                b.className = 'btn-periodo px-3 py-1 rounded-lg text-xs font-bold bg-emerald-600 text-white shadow-xs transition';
            } else {
                b.className = 'btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition';
            }
        });

        const hoje = new Date();
        const ano = hoje.getFullYear();
        const mes = hoje.getMonth();
        const pad = (n) => String(n).padStart(2, '0');
        const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

        if (tipo === 'hoje') {
            this.dataInicio = fmt(hoje);
            this.dataFim = fmt(hoje);
        } else if (tipo === 'este_mes') {
            const ultDia = new Date(ano, mes + 1, 0);
            this.dataInicio = `${ano}-${pad(mes + 1)}-01`;
            this.dataFim = fmt(ultDia);
        } else if (tipo === 'mes_passado') {
            const pDia = new Date(ano, mes - 1, 1);
            const uDia = new Date(ano, mes, 0);
            this.dataInicio = fmt(pDia);
            this.dataFim = fmt(uDia);
        } else if (tipo === '30dias') {
            const d30 = new Date();
            d30.setDate(d30.getDate() - 30);
            this.dataInicio = fmt(d30);
            this.dataFim = fmt(hoje);
        } else if (tipo === 'este_ano') {
            this.dataInicio = `${ano}-01-01`;
            this.dataFim = fmt(hoje);
        } else if (tipo === 'todos') {
            this.dataInicio = '';
            this.dataFim = '';
        }

        this.carregarDadosRepresentantes();
        if (this.representanteSelecionado !== null) {
            this.carregarTitulos();
        }
    },

    togglePersonalizado() {
        const p = document.getElementById('painel-datas-personalizadas');
        if (!p) return;
        const aberto = p.style.display !== 'none';
        p.style.display = aberto ? 'none' : 'flex';
        if (!aberto) {
            const hoje = new Date();
            const ano = hoje.getFullYear();
            const mes = hoje.getMonth();
            const pad = (n) => String(n).padStart(2, '0');
            const ultDia = new Date(ano, mes + 1, 0);
            const inIni = document.getElementById('input-data-inicio');
            const inFim = document.getElementById('input-data-fim');
            if (inIni) inIni.value = this.dataInicio || `${ano}-${pad(mes + 1)}-01`;
            if (inFim) inFim.value = this.dataFim || `${ano}-${pad(mes + 1)}-${pad(ultDia.getDate())}`;
        }
    },

    aplicarDatasPersonalizadas() {
        const di = document.getElementById('input-data-inicio')?.value;
        const df = document.getElementById('input-data-fim')?.value;
        if (!di || !df) {
            Swal.fire('Atenção', 'Selecione a data inicial e final para filtrar.', 'warning');
            return;
        }
        this.periodo = 'personalizado';
        this.dataInicio = di;
        this.dataFim = df;

        document.querySelectorAll('.btn-periodo').forEach(b => {
            if (b.dataset.periodo === 'personalizado') {
                b.className = 'btn-periodo px-2.5 py-1 rounded-lg text-xs font-bold bg-blue-600 text-white shadow-xs transition';
            } else {
                b.className = 'btn-periodo px-3 py-1 rounded-lg text-xs font-bold text-slate-600 hover:bg-white hover:shadow-xs transition';
            }
        });

        this.carregarDadosRepresentantes();
        if (this.representanteSelecionado !== null) {
            this.carregarTitulos();
        }
    },

    setTipoPeriodo(tipo) {
        if (this.tipoPeriodo === tipo) return;
        this.tipoPeriodo = tipo;
        const btnV = document.getElementById('btn-base-vencimento');
        const btnE = document.getElementById('btn-base-emissao');
        if (tipo === 'vencimento') {
            if (btnV) btnV.className = 'px-2.5 py-1 rounded-lg text-xs font-bold bg-white text-slate-800 shadow-xs transition';
            if (btnE) btnE.className = 'px-2.5 py-1 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-800 transition';
        } else {
            if (btnE) btnE.className = 'px-2.5 py-1 rounded-lg text-xs font-bold bg-white text-slate-800 shadow-xs transition';
            if (btnV) btnV.className = 'px-2.5 py-1 rounded-lg text-xs font-bold text-slate-500 hover:text-slate-800 transition';
        }
        this.carregarDadosRepresentantes();
        if (this.representanteSelecionado !== null) {
            this.carregarTitulos();
        }
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
        const badgeGestor = document.getElementById('badge-gestor-escopo');
        try {
            const resp = await this.fetchWithAuth('/v1/analise-carteira/gestores', { method: 'POST', body: '{}' });
            const data = await resp.json();
            const lista = Array.isArray(data) ? data : (data.gestores || []);
            this.todosGestoresPermitido = Array.isArray(data) ? (lista.length > 1) : (!!data.todos_permitido || lista.length > 1);

            if (!lista.length) {
                select.innerHTML = '<option value="">Nenhum gestor disponível</option>';
                return;
            }

            let optionsHtml = '';
            if (this.todosGestoresPermitido || lista.length > 1) {
                optionsHtml += '<option value="">👔 Todos os Gestores</option>';
            }
            optionsHtml += lista.map(g => `<option value="${this.esc(g.id)}">${this.esc(g.id === 0 ? 'Sem gestor' : g.nome)}</option>`).join('');
            select.innerHTML = optionsHtml;

            if (lista.length === 1 && !this.todosGestoresPermitido) {
                select.value = String(lista[0].id);
                this.gestorSelecionado = parseInt(lista[0].id, 10);
                this.nomeGestor = lista[0].nome;
                if (badgeGestor) badgeGestor.innerText = 'escopo exclusivo';
            } else {
                select.value = '';
                this.gestorSelecionado = '';
                this.nomeGestor = 'Todos os Gestores';
                if (badgeGestor) badgeGestor.innerText = `${lista.length} gestores no escopo`;
            }

            await this.carregarDadosRepresentantes();
        } catch (e) {
            console.error('Erro gestores:', e);
            select.innerHTML = '<option value="">Erro ao carregar gestores</option>';
            if (e.message !== 'Sessão expirada') Swal.fire('Erro', e.message, 'error');
        }
    },

    async carregarDadosRepresentantes() {
        if (this.abortDados) this.abortDados.abort();
        const ctrl = this.abortDados = new AbortController();
        const seq = ++this.seqDados;
        const corpo = {
            id_gestor: this.gestorSelecionado !== null && this.gestorSelecionado !== '' ? this.gestorSelecionado : null,
            periodo: this.periodo,
            tipo_periodo: this.tipoPeriodo,
            data_inicio: this.dataInicio,
            data_fim: this.dataFim
        };
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
        const badgeRep = document.getElementById('badge-rep-escopo');
        const reps = this.dadosRepresentantes
            .map(r => ({ id: r.id_representante, nome: r.nome_representante || 'Sem representante' }))
            .sort((a, b) => a.nome.localeCompare(b.nome));

        select.innerHTML = '<option value="">🤝 Todos os Representantes</option>' +
            reps.map(r => `<option value="${this.esc(r.id)}">${this.esc(r.nome)}</option>`).join('');
        select.value = this.representanteSelecionado !== null ? String(this.representanteSelecionado) : '';
        if (badgeRep) badgeRep.innerText = `${reps.length} ativos`;
    },

    async carregarTitulos() {
        if (this.representanteSelecionado === null) return false;
        if (this.abortTitulos) this.abortTitulos.abort();
        const ctrl = this.abortTitulos = new AbortController();
        const seq = ++this.seqTitulos;
        this.showShimmer();
        try {
            const dados = await this.postJson('/v1/analise-carteira/titulos-representante', {
                id_gestor: this.gestorSelecionado !== null && this.gestorSelecionado !== '' ? this.gestorSelecionado : null,
                id_representante: this.representanteSelecionado,
                periodo: this.periodo,
                tipo_periodo: this.tipoPeriodo,
                data_inicio: this.dataInicio,
                data_fim: this.dataFim
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
                `<tr><td colspan="9" class="text-center py-8 text-rose-500">${this.esc(e.message)}</td></tr>`;
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
        if (!dados.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-slate-400">Nenhum título vencido</td></tr>'; return; }
        tbody.innerHTML = dados.map(t => {
            const da = parseInt(t.dias_atraso || 0, 10);
            const de = parseInt(t.ult_evento_dias || 0, 10);
            const ba = da > 60 ? 'badge-critico' : (da > 30 ? 'badge-atencao' : 'badge-dias');
            const idCli = parseInt(t.id_cliente || 0, 10);
            const nomeCli = this.esc(t.nome_fantasia || '-');
            const nomeCliAttr = nomeCli.replace(/'/g, "\\'");
            return `<tr class="border-b border-slate-100 hover:bg-slate-50 transition-all" data-chave="${this.esc(t.chave)}">
                <td class="px-3 py-2.5 font-semibold text-slate-800">
                    <button type="button" onclick="analiseCarteiraApp.abrirModalCliente(${idCli}, '${nomeCliAttr}')" class="text-left text-blue-600 hover:text-blue-800 hover:underline flex items-center gap-1.5 group" title="Clique para ver ficha completa e histórico">
                        <i class="fa-solid fa-address-card text-slate-400 group-hover:text-blue-600 text-xs"></i>
                        <span>${nomeCli}</span>
                    </button>
                </td>
                <td class="px-3 py-2.5 font-mono text-xs">${this.esc(t.documento || '-')}</td>
                <td class="px-3 py-2.5 text-center text-xs">${this.esc(this.fmtData(t.vencimento))}</td>
                <td class="px-3 py-2.5 text-right font-bold text-rose-600">${this.fmtMoeda(t.valor_saldo)}</td>
                <td class="px-3 py-2.5 text-center"><span class="${ba}">${da} d</span></td>
                <td class="px-3 py-2.5 text-center">${de > 0 ? `<span class="badge-evento">${de} d</span>` : '<span class="badge-sem-evento">Sem evento</span>'}</td>
                <td class="px-3 py-2.5 text-xs text-slate-600">${this.esc(t.usuario || '-')}</td>
                <td class="px-3 py-2.5 text-xs">${t.evento ? `<span class="font-medium text-emerald-700">${this.esc(t.evento)}</span>` : '<span class="text-rose-500 font-bold">Sem apontamento</span>'}</td>
                <td class="px-3 py-2.5 text-center">
                    <button type="button" onclick="analiseCarteiraApp.abrirModalCliente(${idCli}, '${nomeCliAttr}', 'novo')" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white rounded-lg text-xs font-bold transition shadow-sm inline-flex items-center gap-1" title="Registrar evento de cobrança/contato">
                        <i class="fa-solid fa-plus text-[10px]"></i> Evento
                    </button>
                </td>
            </tr>`;
        }).join('');
    },

    // ======================================================================
    // MODAL DE DETALHES DO CLIENTE & EVENTOS DE COBRANÇA
    // ======================================================================
    clienteModalAtual: null,

    async abrirModalCliente(idCliente, nomeCliente, abaInicial = 'historico') {
        if (!idCliente) return;
        this.showShimmer();
        Swal.fire({
            title: 'Carregando detalhes...',
            html: '<div class="p-4 text-center text-slate-500"><i class="fa-solid fa-circle-notch fa-spin text-3xl text-rose-600 mb-2"></i><p class="text-xs">Buscando cadastro, títulos e histórico de eventos...</p></div>',
            allowOutsideClick: false,
            showConfirmButton: false,
            width: '450px'
        });

        try {
            const resp = await this.fetchWithAuth(`/v1/analise-carteira/cliente/${idCliente}/detalhes`);
            const dados = await resp.json();
            if (dados.error) throw new Error(dados.error);
            this.clienteModalAtual = dados;
            this.exibirModalCliente(abaInicial);
        } catch (e) {
            console.error('Erro ao buscar detalhes do cliente:', e);
            Swal.fire('Erro', e.message || 'Falha ao buscar dados do cliente', 'error');
        } finally {
            this.hideShimmer();
        }
    },

    exibirModalCliente(abaAtiva = 'historico') {
        const d = this.clienteModalAtual;
        if (!d || !d.cliente) return;
        const c = d.cliente;
        const evs = Array.isArray(d.eventos) ? d.eventos : [];
        const tits = Array.isArray(d.titulos) ? d.titulos : [];
        const tipos = Array.isArray(d.tipos_evento) ? d.tipos_evento : [];
        const tiposPermitemVazio = [9, 8, 2, 10, 4];

        const html = `
        <div class="text-left font-sans -mt-2">
            <!-- Header do Cliente -->
            <div class="border-b border-slate-200 pb-3 mb-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pr-7">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-mono text-xs font-bold">#${this.esc(c.idcliforemp)}</span>
                            <span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded-md text-xs font-bold">Filial ${this.esc(c.idfilial)}</span>
                        </div>
                        <h2 class="text-base sm:text-lg font-black text-slate-900 leading-tight mt-1 break-words">${this.esc(c.razao || c.fantasia)}</h2>
                        <div class="text-xs font-semibold text-slate-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                            ${c.fantasia && c.fantasia !== c.razao ? `<span><i class="fa-solid fa-store mr-1 text-slate-400"></i>${this.esc(c.fantasia)}</span>` : ''}
                            ${c.cnpj_cpf ? `<span><i class="fa-solid fa-id-card mr-1 text-slate-400"></i>${this.esc(c.cnpj_cpf)}</span>` : ''}
                            ${c.cidade ? `<span><i class="fa-solid fa-location-dot mr-1 text-slate-400"></i>${this.esc(c.cidade)}/${this.esc(c.uf || '')}</span>` : ''}
                            ${c.fone ? `<span><i class="fa-solid fa-phone mr-1 text-slate-400"></i>${this.esc(c.fone)}</span>` : ''}
                        </div>
                    </div>
                </div>

                <!-- Mini Cards de Saldo -->
                <div class="grid grid-cols-3 gap-1.5 sm:gap-2 mt-3 text-center">
                    <div class="bg-rose-50 border border-rose-200 rounded-xl p-2 min-w-0">
                        <span class="text-[0.65rem] text-rose-600 font-bold block uppercase tracking-wider truncate">Vencido</span>
                        <span class="text-xs sm:text-base font-black text-rose-700 block truncate">${this.fmtMoeda(c.total_vencido)}</span>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-2 min-w-0">
                        <span class="text-[0.65rem] text-emerald-600 font-bold block uppercase tracking-wider truncate">À Vencer</span>
                        <span class="text-xs sm:text-base font-black text-emerald-700 block truncate">${this.fmtMoeda(c.total_a_vencer)}</span>
                    </div>
                    <div class="bg-blue-50 border border-blue-200 rounded-xl p-2 min-w-0">
                        <span class="text-[0.65rem] text-blue-600 font-bold block uppercase tracking-wider truncate">Carteira Total</span>
                        <span class="text-xs sm:text-base font-black text-blue-800 block truncate">${this.fmtMoeda(c.total_carteira)}</span>
                    </div>
                </div>
            </div>

            <!-- Abas de Navegação -->
            <div class="flex items-center gap-2 border-b border-slate-200 pb-2 mb-3">
                <button type="button" id="tab-btn-historico" class="px-3.5 py-1.5 rounded-lg text-xs font-black transition flex items-center gap-1.5 ${abaAtiva === 'historico' ? 'bg-slate-900 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'}">
                    <i class="fa-solid fa-clock-rotate-left"></i> Histórico de Eventos
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] ${abaAtiva === 'historico' ? 'bg-white/20 text-white' : 'bg-slate-300 text-slate-700'}">${evs.length}</span>
                </button>
                <button type="button" id="tab-btn-novo" class="px-3.5 py-1.5 rounded-lg text-xs font-black transition flex items-center gap-1.5 ${abaAtiva === 'novo' ? 'bg-amber-600 text-white shadow-sm' : 'bg-amber-50 text-amber-700 hover:bg-amber-100'}">
                    <i class="fa-solid fa-plus-circle"></i> Novo Evento / Cobrança
                </button>
            </div>

            <!-- Conteúdo da Aba: Histórico -->
            <div id="tab-pane-historico" style="${abaAtiva === 'historico' ? 'display:block' : 'display:none'}">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-slate-500">Últimos ${evs.length} registro(s) no ERP</span>
                    <button type="button" id="btn-ir-novo-evento" class="text-xs text-amber-700 font-black hover:underline flex items-center gap-1">
                        <i class="fa-solid fa-plus"></i> Registrar Novo Evento
                    </button>
                </div>
                <div class="overflow-y-auto max-h-[380px] pr-1 space-y-2.5" id="timeline-eventos">
                    ${!evs.length ? `
                        <div class="text-center py-8 bg-slate-50 rounded-xl border border-dashed border-slate-200 text-slate-400">
                            <i class="fa-regular fa-comment-dots text-3xl mb-1 text-slate-300"></i>
                            <p class="text-xs font-semibold">Nenhum evento registrado para este cliente até o momento.</p>
                        </div>
                    ` : evs.map(e => `
                        <div class="bg-white border border-slate-200/90 rounded-xl p-3 shadow-xs hover:border-slate-300 transition">
                            <div class="flex flex-wrap items-center justify-between gap-1.5 mb-1.5">
                                <div class="flex items-center gap-2">
                                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase text-white tracking-wide" style="background-color: ${this.esc(e.cor_evento || '#475569')}">
                                        ${this.esc(e.tipo_evento || 'Evento')}
                                    </span>
                                    <span class="text-[11px] font-bold text-slate-700">
                                        <i class="fa-regular fa-calendar-days text-slate-400 mr-1"></i>${this.esc(this.fmtData(e.data))} às ${this.esc((e.hora || '').slice(0, 5))}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 text-[11px]">
                                    <span class="text-slate-500 font-semibold"><i class="fa-solid fa-user text-slate-400 mr-1"></i>${this.esc(e.usuario || 'SISTEMA')}</span>
                                    ${e.documento && e.documento !== '.' ? `<span class="bg-slate-100 text-slate-700 font-mono font-bold px-1.5 py-0.2 rounded border border-slate-200 text-[10px]">Doc: ${this.esc(e.documento)}</span>` : '<span class="text-slate-400 text-[10px] italic">Sem título vinculado</span>'}
                                </div>
                            </div>
                            <div class="text-xs text-slate-700 bg-slate-50/70 p-2 rounded-lg border border-slate-100 font-medium whitespace-pre-wrap leading-relaxed">
                                ${this.esc(e.motivo || '-')}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>

            <!-- Conteúdo da Aba: Novo Evento -->
            <div id="tab-pane-novo" style="${abaAtiva === 'novo' ? 'display:block' : 'display:none'}">
                <form id="form-novo-evento" onsubmit="return false;" class="space-y-3.5">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                                Escolha o Tipo de Evento <span class="text-rose-500">*</span>
                            </label>
                            <span id="badge-regra-titulo" class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600">
                                Selecione um tipo...
                            </span>
                        </div>
                        <select id="modal-ev-tipoevento" class="w-full text-xs font-bold text-slate-800 bg-slate-50 border-2 border-slate-200 rounded-xl p-2.5 focus:border-amber-500 focus:bg-white outline-none transition">
                            <option value="">Selecione o tipo do evento...</option>
                            ${tipos.map(tp => `<option value="${this.esc(tp.idtipoevento)}">${this.esc(tp.descricao)}</option>`).join('')}
                        </select>
                    </div>

                    <!-- Grade de Títulos em Aberto -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                                Vincular Títulos em Aberto (${tits.length})
                            </label>
                            ${tits.length > 0 ? `
                                <label class="inline-flex items-center gap-1.5 text-xs font-bold text-blue-700 cursor-pointer select-none">
                                    <input type="checkbox" id="modal-ev-marcar-todos" class="rounded text-blue-600 focus:ring-0">
                                    <span>Marcar Todos</span>
                                </label>
                            ` : ''}
                        </div>
                        
                        <div class="border border-slate-200 rounded-xl overflow-x-auto max-h-[160px] overflow-y-auto">
                            ${!tits.length ? `
                                <div class="p-3 text-center text-xs text-slate-400 bg-slate-50">
                                    Nenhum título em aberto localizado para este cliente.
                                </div>
                            ` : `
                                <table class="w-full min-w-[480px] text-xs text-left whitespace-nowrap" id="modal-tabela-titulos">
                                    <thead class="bg-slate-100 text-[10px] uppercase font-black text-slate-500 sticky top-0">
                                        <tr>
                                            <th class="p-2 w-8 text-center">Sel</th>
                                            <th class="p-2">Documento</th>
                                            <th class="p-2 text-center">Vencimento</th>
                                            <th class="p-2 text-right">Saldo Devedor</th>
                                            <th class="p-2 text-center">Atraso</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        ${tits.map(t => {
                                            const da = parseInt(t.dias_atraso || 0, 10);
                                            const atrasoText = da > 0 ? `${da} dias` : 'No prazo';
                                            const atrasoClass = da > 30 ? 'text-rose-600 font-black' : (da > 0 ? 'text-amber-600 font-bold' : 'text-emerald-600 font-medium');
                                            return `
                                                <tr class="hover:bg-blue-50/50 cursor-pointer transition">
                                                    <td class="p-2 text-center">
                                                        <input type="checkbox" name="titulos_evento" value="${this.esc(t.documento)}" class="chk-titulo-modal rounded text-blue-600 focus:ring-0 cursor-pointer">
                                                    </td>
                                                    <td class="p-2 font-mono font-bold text-slate-800">${this.esc(t.documento)}</td>
                                                    <td class="p-2 text-center text-slate-600">${this.esc(this.fmtData(t.vencimento))}</td>
                                                    <td class="p-2 text-right font-bold text-rose-600">${this.fmtMoeda(t.valorsaldo)}</td>
                                                    <td class="p-2 text-center ${atrasoClass}">${atrasoText}</td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            `}
                        </div>
                    </div>

                    <!-- Observação / Motivo -->
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-xs font-black text-slate-700 uppercase tracking-wider">
                                Descrição / Motivo / Observação <span class="text-rose-500">*</span>
                            </label>
                            <span class="text-[10px] text-slate-400 font-bold" id="contador-caracteres">Mínimo 3 caracteres</span>
                        </div>
                        <textarea id="modal-ev-motivo" rows="3" class="w-full text-xs text-slate-800 bg-slate-50 border-2 border-slate-200 rounded-xl p-2.5 focus:border-amber-500 focus:bg-white outline-none transition resize-none" placeholder="Ex: Cliente contatado, solicitou envio de boleto atualizado e prometeu pagamento para 25/10..."></textarea>
                    </div>

                    <!-- Botão de Ação -->
                    <div class="pt-1 flex items-center justify-end gap-2">
                        <button type="button" id="btn-cancelar-modal" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 transition">
                            Voltar
                        </button>
                        <button type="button" id="btn-salvar-evento" class="px-5 py-2.5 rounded-xl text-xs font-black text-white bg-amber-600 hover:bg-amber-700 shadow-md transition flex items-center gap-1.5 active:scale-95">
                            <i class="fa-solid fa-floppy-disk"></i> Gravar Evento no Cliente
                        </button>
                    </div>
                </form>
            </div>
        </div>
        `;

        Swal.fire({
            html,
            width: '780px',
            showConfirmButton: false,
            showCloseButton: true,
            focusConfirm: false,
            customClass: {
                popup: 'rounded-2xl p-3.5 sm:p-6 max-w-[96vw]'
            },
            didOpen: (popup) => {
                const tabBtnHist = popup.querySelector('#tab-btn-historico');
                const tabBtnNovo = popup.querySelector('#tab-btn-novo');
                const tabPaneHist = popup.querySelector('#tab-pane-historico');
                const tabPaneNovo = popup.querySelector('#tab-pane-novo');
                const btnIrNovo = popup.querySelector('#btn-ir-novo-evento');
                const btnCancelar = popup.querySelector('#btn-cancelar-modal');
                const selTipo = popup.querySelector('#modal-ev-tipoevento');
                const badgeRegra = popup.querySelector('#badge-regra-titulo');
                const chkTodos = popup.querySelector('#modal-ev-marcar-todos');
                const btnSalvar = popup.querySelector('#btn-salvar-evento');

                const switchTab = (tab) => {
                    if (tab === 'novo') {
                        tabBtnNovo.className = 'px-3.5 py-1.5 rounded-lg text-xs font-black transition flex items-center gap-1.5 bg-amber-600 text-white shadow-sm';
                        tabBtnHist.className = 'px-3.5 py-1.5 rounded-lg text-xs font-black transition flex items-center gap-1.5 bg-slate-100 text-slate-600 hover:bg-slate-200';
                        tabPaneNovo.style.display = 'block';
                        tabPaneHist.style.display = 'none';
                    } else {
                        tabBtnHist.className = 'px-3.5 py-1.5 rounded-lg text-xs font-black transition flex items-center gap-1.5 bg-slate-900 text-white shadow-sm';
                        tabBtnNovo.className = 'px-3.5 py-1.5 rounded-lg text-xs font-black transition flex items-center gap-1.5 bg-amber-50 text-amber-700 hover:bg-amber-100';
                        tabPaneHist.style.display = 'block';
                        tabPaneNovo.style.display = 'none';
                    }
                };

                tabBtnHist?.addEventListener('click', () => switchTab('historico'));
                tabBtnNovo?.addEventListener('click', () => switchTab('novo'));
                btnIrNovo?.addEventListener('click', () => switchTab('novo'));
                btnCancelar?.addEventListener('click', () => switchTab('historico'));

                selTipo?.addEventListener('change', (e) => {
                    const val = parseInt(e.target.value, 10);
                    if (!val) {
                        badgeRegra.innerText = 'Selecione um tipo...';
                        badgeRegra.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600';
                    } else if (tiposPermitemVazio.includes(val)) {
                        badgeRegra.innerText = 'Vínculo de títulos opcional';
                        badgeRegra.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800';
                    } else {
                        badgeRegra.innerText = 'Exige ao menos 1 título marcado';
                        badgeRegra.className = 'text-[10px] font-bold px-2 py-0.5 rounded bg-amber-100 text-amber-800';
                    }
                });

                chkTodos?.addEventListener('change', (e) => {
                    const checked = e.target.checked;
                    popup.querySelectorAll('.chk-titulo-modal').forEach(cb => cb.checked = checked);
                });

                btnSalvar?.addEventListener('click', () => {
                    this.salvarNovoEvento(c.idcliforemp, btnSalvar);
                });
            }
        });
    },

    async salvarNovoEvento(idCliente, btnElement) {
        const selTipo = document.getElementById('modal-ev-tipoevento');
        const txtMotivo = document.getElementById('modal-ev-motivo');
        const tiposPermitemVazio = [9, 8, 2, 10, 4];

        const tipoevento = parseInt(selTipo?.value, 10);
        if (!tipoevento) {
            Swal.fire('Atenção', 'Por favor, selecione o Tipo de Evento.', 'warning');
            selTipo?.focus();
            return;
        }

        const motivo = (txtMotivo?.value || '').trim();
        if (motivo.length < 3) {
            Swal.fire('Atenção', 'Por favor, preencha a observação/motivo (mínimo 3 caracteres).', 'warning');
            txtMotivo?.focus();
            return;
        }

        const titulos = [];
        document.querySelectorAll('.chk-titulo-modal:checked').forEach(cb => {
            if (cb.value && cb.value.trim() !== '') titulos.push(cb.value.trim());
        });

        if (titulos.length === 0 && !tiposPermitemVazio.includes(tipoevento)) {
            Swal.fire('Atenção', 'Este tipo de evento exige a seleção de ao menos um título da lista.', 'warning');
            return;
        }

        const btnOriginalHtml = btnElement ? btnElement.innerHTML : '';
        if (btnElement) {
            btnElement.disabled = true;
            btnElement.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Gravando...';
        }

        try {
            const resp = await this.postJson(`/v1/analise-carteira/cliente/${idCliente}/eventos`, {
                tipoevento,
                motivo,
                titulos
            });

            const totalGravados = resp.sucessos || 1;
            const toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true
            });
            toast.fire({
                icon: 'success',
                title: `${totalGravados} evento(s) gravado(s) com sucesso!`
            });

            if (this.representanteSelecionado !== null) {
                this.carregarTitulos();
            }

            await this.abrirModalCliente(idCliente, '', 'historico');

        } catch (e) {
            console.error('Erro ao gravar evento:', e);
            Swal.fire('Erro', 'Erro ao gravar evento: ' + (e.message || 'Falha na comunicação.'), 'error');
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = btnOriginalHtml;
            }
        }
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
        document.getElementById('tabela-titulos-body').innerHTML = '<tr><td colspan="9" class="text-center py-8 text-slate-400">Selecione um representante</td></tr>';
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

/**
 * Sistema Universal de Tooltip Moderno Flutuante
 */
function initModernTooltipsDash() {
    const tooltipEl = document.getElementById('modernTooltipDash');
    const badgeEl = document.getElementById('tooltipDashBadge');
    const titleEl = document.getElementById('tooltipDashTitle');
    const descEl = document.getElementById('tooltipDashDesc');

    if (!tooltipEl || !titleEl || !descEl) return;
    if (window.__tooltipsCarteiraInitialized) return;
    window.__tooltipsCarteiraInitialized = true;

    let targetAtivo = null;

    document.addEventListener('mouseover', (e) => {
        const el = e.target.closest('[data-tooltip], [title]');
        if (!el) return;

        targetAtivo = el;
        const rawContent = el.getAttribute('data-tooltip') || el.getAttribute('title');
        if (!rawContent) return;

        // Limpa o title nativo para evitar tooltip duplo feio do navegador
        if (el.hasAttribute('title')) {
            el.setAttribute('data-tooltip-backup', el.getAttribute('title'));
            el.removeAttribute('title');
        }

        let titulo = 'Informação';
        let descricao = rawContent;
        let badge = 'CRÉDITO';

        if (rawContent.includes('|')) {
            const partes = rawContent.split('|');
            titulo = partes[0].trim();
            descricao = partes.slice(1).join('|').trim();
        } else {
            titulo = el.innerText ? el.innerText.trim().slice(0, 30) : 'Indicador';
        }

        const descLower = (titulo + ' ' + descricao).toLowerCase();
        if (descLower.includes('vencid') || descLower.includes('inadimpl') || descLower.includes('+60')) {
            badge = 'COBRANÇA';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-rose-400 bg-rose-950/90 px-2 py-0.5 rounded-md border border-rose-700/50';
        } else if (descLower.includes('iag') || descLower.includes('atraso') || descLower.includes('60 dias')) {
            badge = 'RISCO / IAG';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-amber-400 bg-amber-950/90 px-2 py-0.5 rounded-md border border-amber-700/50';
        } else if (descLower.includes('vencer') || descLower.includes('saudável') || descLower.includes('30 dias')) {
            badge = 'CRÉDITO';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50';
        } else if (descLower.includes('carteira') || descLower.includes('título')) {
            badge = 'CARTEIRA';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-sky-400 bg-sky-950/90 px-2 py-0.5 rounded-md border border-sky-700/50';
        } else if (descLower.includes('filtro') || descLower.includes('período') || descLower.includes('base')) {
            badge = 'FILTRO';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-indigo-400 bg-indigo-950/90 px-2 py-0.5 rounded-md border border-indigo-700/50';
        } else {
            badge = 'DETALHE';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-slate-300 bg-slate-800 px-2 py-0.5 rounded-md border border-slate-700';
        }

        if (badgeEl) badgeEl.textContent = badge;
        titleEl.textContent = titulo;
        descEl.textContent = descricao;

        tooltipEl.classList.remove('hidden');
        tooltipEl.style.opacity = '1';
    });

    document.addEventListener('mousemove', (e) => {
        if (!targetAtivo || tooltipEl.classList.contains('hidden')) return;

        const offset = 14;
        let left = e.clientX + offset;
        let top = e.clientY + offset;

        const tipWidth = tooltipEl.offsetWidth || 270;
        const tipHeight = tooltipEl.offsetHeight || 95;

        if (left + tipWidth > window.innerWidth - 12) {
            left = e.clientX - tipWidth - offset;
        }
        if (top + tipHeight > window.innerHeight - 12) {
            top = e.clientY - tipHeight - offset;
        }

        tooltipEl.style.left = `${Math.max(10, left)}px`;
        tooltipEl.style.top = `${Math.max(10, top)}px`;
    });

    document.addEventListener('mouseout', (e) => {
        if (targetAtivo && (e.target === targetAtivo || targetAtivo.contains(e.target))) {
            const related = e.relatedTarget;
            if (related && targetAtivo.contains(related)) return;

            tooltipEl.classList.add('hidden');
            targetAtivo = null;
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    window.analiseCarteiraApp = analiseCarteiraApp;
    analiseCarteiraApp.init();
    initModernTooltipsDash();
});
