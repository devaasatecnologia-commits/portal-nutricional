// ==========================================================================
// CUBO DE VENDAS
// Base: NFs faturadas + saldo de pedidos em carteira - NFs de devolução.
// ==========================================================================

function cuboVendasHandler() {
    const hoje = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const FILTROS_AVANCADOS = ['cliente', 'regiao', 'estado', 'cidade', 'grupo', 'subgrupo', 'tipo_produto', 'marca', 'produto', 'tipo_de_pedido'];

    return {
        // Estado
        filialSelecionada: '',
        gestorSelecionado: '',
        representanteSelecionado: '',
        carregamentoInicial: true,
        carregando: false,
        erro: '',
        aviso: '',
        reqSeq: 0,

        // Listas
        filiaisPermitidas: [],
        gestoresDisponiveis: [],
        representantesDisponiveis: [],

        // Modais
        modalFiltrosOpen: false,
        modalMetricasOpen: false,
        modalDetalhesOpen: false,
        modalDetalhesTitulo: 'Detalhamento',
        detalhesCarregando: false,
        detalhesErro: '',
        detalhesTruncado: false,
        documentosAgrupados: [],
        detalhesTotais: { valor_bruto: 0, valor_devolvido: 0, quantidade: 0, peso: 0, comissao: 0, qtd_documentos: 0 },

        // Filtros
        filters: {
            data_inicio: iso(new Date(hoje.getFullYear(), hoje.getMonth(), 1)),
            data_fim: iso(hoje),
            cliente: '', regiao: '', estado: '', cidade: '',
            grupo: '', subgrupo: '', tipo_produto: '',
            marca: '', produto: '', tipo_de_pedido: ''
        },

        filterOptions: {
            cliente: [], regiao: [], estado: [], cidade: [],
            grupo: [], subgrupo: [], tipo_produto: [],
            marca: [], produto: [], tipo_de_pedido: []
        },
        opcoesCarregadas: false,

        // Config
        dimensionsDisponiveis: {},
        metricsDisponiveis: {},
        metricsSelecionadas: ['comissao_total', 'valor_devolvido', 'valor_total', 'quantidade_total', 'peso_total'],
        rowDimension: 'data_documento',
        rowDimensionLabel: 'Data',

        // Dados
        totals: { valor_bruto: 0, valor_devolvido: 0, quantidade: 0, peso: 0, comissao: 0, qtd_documentos: 0, ticket_medio: 0 },
        comparativo: null,
        tableData: [],
        buscaTabela: '',
        periodoRapidoAtivo: 'mes_atual',
        ranking: [],
        timeSeriesData: { labels: [], values: [], series: {} },

        // ================================================================
        // INICIALIZAÇÃO
        // ================================================================
        async init() {
            window.__cuboVendasData = this;
            this.carregando = true;
            try {
                await this.carregarConfig();
                await this.carregarFiliais();

                if (this.filiaisPermitidas.length === 1) {
                    this.filialSelecionada = String(this.filiaisPermitidas[0].idfilial);
                }
                await this.carregarGestores();
                if (this.gestoresDisponiveis.length === 1) {
                    this.gestorSelecionado = String(this.gestoresDisponiveis[0].id);
                    await this.carregarRepresentantes();
                }
            } finally {
                this.carregamentoInicial = false;
            }
            await this.atualizar();
        },

        // ================================================================
        // API
        // ================================================================
        async apiFetch(endpoint, body = null) {
            const token = localStorage.getItem('authToken');
            if (!token) {
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada. Faça login novamente.');
            }

            const getEndpoints = endpoint === 'config' || endpoint.startsWith('filters/');
            const options = {
                method: getEndpoints ? 'GET' : 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                }
            };
            if (!getEndpoints) {
                options.body = body ? JSON.stringify(body) : '{}';
            }

            const resp = await fetch(`/v1/vendas/cubo/${endpoint}`, options);
            if (resp.status === 401) {
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada.');
            }

            const text = await resp.text();
            let json = null;
            try { json = JSON.parse(text); } catch (e) { /* tratado abaixo */ }
            if (!resp.ok) {
                throw new Error((json && json.error) || `Erro ${resp.status} ao consultar o servidor.`);
            }
            if (!json) throw new Error('Resposta inválida do servidor.');
            return json;
        },

        // ================================================================
        // FILTROS (única montagem, usada por dados, ranking, detalhes e exportação)
        // ================================================================
        montarFiltros() {
            const f = {};
            if (this.filters.data_inicio) f.data_inicio = this.filters.data_inicio;
            if (this.filters.data_fim) f.data_fim = this.filters.data_fim;
            if (this.filialSelecionada) f.filial = this.filialSelecionada;
            if (this.gestorSelecionado) f.supervisor = this.gestorSelecionado;
            if (this.representanteSelecionado) f.representante = this.representanteSelecionado;
            FILTROS_AVANCADOS.forEach(k => {
                const v = this.filters[k];
                if (v !== '' && v !== null && v !== undefined) f[k] = v;
            });
            return f;
        },

        validar() {
            if (!this.filters.data_inicio || !this.filters.data_fim) {
                return 'Informe o período (data inicial e final).';
            }
            if (this.filters.data_inicio > this.filters.data_fim) {
                return 'A data inicial não pode ser maior que a data final.';
            }
            if (!this.metricsSelecionadas.length) {
                return 'Selecione ao menos uma métrica.';
            }
            return '';
        },

        async atualizar() {
            const msg = this.validar();
            if (msg) {
                this.erro = msg;
                return;
            }
            this.erro = '';
            this.aviso = '';
            this.carregando = true;
            const seq = ++this.reqSeq;
            try {
                await Promise.all([this.carregarDados(seq), this.carregarRanking(seq)]);
            } finally {
                if (seq === this.reqSeq) this.carregando = false;
            }
        },

        // ================================================================
        // LOOKUPS EM CASCATA
        // ================================================================
        async carregarFiliais() {
            try {
                const data = await this.apiFetch('filiais');
                this.filiaisPermitidas = data.filiais || [];
            } catch (e) {
                this.erro = e.message;
            }
        },

        async carregarGestores() {
            try {
                const idFilial = parseInt(this.filialSelecionada) || 0;
                if (idFilial > 0) {
                    const data = await this.apiFetch('supervisores-por-filial', { idfilial: idFilial });
                    this.gestoresDisponiveis = data.supervisores || [];
                } else {
                    const data = await this.apiFetch('gestores');
                    this.gestoresDisponiveis = data.gestores || [];
                }
            } catch (e) {
                this.gestoresDisponiveis = [];
                this.erro = e.message;
            }
        },

        async carregarRepresentantes() {
            const idGestor = parseInt(this.gestorSelecionado) || 0;
            if (idGestor <= 0) {
                this.representantesDisponiveis = [];
                return;
            }
            try {
                const data = await this.apiFetch('representantes-por-supervisor', {
                    idsupervisor: idGestor,
                    idfilial: parseInt(this.filialSelecionada) || 0
                });
                this.representantesDisponiveis = data.representantes || [];
            } catch (e) {
                this.representantesDisponiveis = [];
                this.erro = e.message;
            }
        },

        async mudarFilial() {
            if (this.carregamentoInicial) return;
            this.gestorSelecionado = '';
            this.representanteSelecionado = '';
            this.representantesDisponiveis = [];
            this.gestoresDisponiveis = [];
            await this.carregarGestores();
            await this.atualizar();
        },

        async mudarGestor() {
            if (this.carregamentoInicial) return;
            this.representanteSelecionado = '';
            this.representantesDisponiveis = [];
            await this.carregarRepresentantes();
            await this.atualizar();
        },

        async mudarRepresentante() {
            if (this.carregamentoInicial) return;
            await this.atualizar();
        },

        // ================================================================
        // CONFIGURAÇÃO
        // ================================================================
        async carregarConfig() {
            try {
                const data = await this.apiFetch('config');
                this.dimensionsDisponiveis = data.dimensions || {};
                this.metricsDisponiveis = data.metrics || {};
                if (!this.dimensionsDisponiveis[this.rowDimension]) {
                    this.rowDimension = Object.keys(this.dimensionsDisponiveis)[0] || this.rowDimension;
                }
                this.rowDimensionLabel = this.dimensionsDisponiveis[this.rowDimension]?.label || this.rowDimensionLabel;
            } catch (e) {
                this.erro = e.message;
            }
        },

        // ================================================================
        // FILTROS AVANÇADOS
        // ================================================================
        async abrirModalFiltrosAvancados() {
            this.modalFiltrosOpen = true;
            if (this.opcoesCarregadas) return;
            const qs = `?data_inicio=${encodeURIComponent(this.filters.data_inicio)}&data_fim=${encodeURIComponent(this.filters.data_fim)}`;
            await Promise.all(FILTROS_AVANCADOS.map(async (campo) => {
                try {
                    const data = await this.apiFetch(`filters/${campo}${qs}`);
                    this.filterOptions[campo] = data.options || [];
                } catch (e) {
                    this.filterOptions[campo] = [];
                }
            }));
            this.opcoesCarregadas = true;
        },

        aplicarFiltroAvancado() { /* aplicado em lote pelo botão "Aplicar Filtros" */ },

        async aplicarTodosFiltros() {
            await this.atualizar();
        },

        async limparFiltrosAvancados() {
            FILTROS_AVANCADOS.forEach(k => { this.filters[k] = ''; });
            await this.atualizar();
        },

        get filtrosAvancadosAtivos() {
            return FILTROS_AVANCADOS.filter(k => this.filters[k] !== '' && this.filters[k] != null).length;
        },

        get linhasFiltradas() {
            const q = (this.buscaTabela || '').trim().toLowerCase();
            if (!q) return this.tableData;
            return this.tableData.filter(r => String(r.dimensao || '').toLowerCase().includes(q));
        },

        totalColuna(metric) {
            const rows = this.linhasFiltradas;
            if (!rows.length) return 0;
            if (metric === 'ticket_medio') {
                const v = rows.reduce((s, r) => s + (parseFloat(r.valor_total) || 0), 0);
                const d = rows.reduce((s, r) => s + (parseFloat(r.qtd_documentos) || 0), 0);
                return d > 0 ? v / d : 0;
            }
            return rows.reduce((s, r) => s + (parseFloat(r[metric]) || 0), 0);
        },

        async definirPeriodoRapido(tipo) {
            const ref = new Date();
            let di, df;
            if (tipo === 'hoje') {
                di = iso(ref);
                df = iso(ref);
            } else if (tipo === 'mes_atual') {
                di = iso(new Date(ref.getFullYear(), ref.getMonth(), 1));
                df = iso(ref);
            } else if (tipo === 'mes_passado') {
                di = iso(new Date(ref.getFullYear(), ref.getMonth() - 1, 1));
                df = iso(new Date(ref.getFullYear(), ref.getMonth(), 0));
            } else if (tipo === 'ultimos_30') {
                const d30 = new Date(ref);
                d30.setDate(d30.getDate() - 29);
                di = iso(d30);
                df = iso(ref);
            } else if (tipo === 'ano_atual') {
                di = iso(new Date(ref.getFullYear(), 0, 1));
                df = iso(ref);
            } else {
                return;
            }
            this.periodoRapidoAtivo = tipo;
            this.filters.data_inicio = di;
            this.filters.data_fim = df;
            this.opcoesCarregadas = false;
            await this.atualizar();
        },

        // ================================================================
        // DADOS
        // ================================================================
        async carregarDados(seq) {
            try {
                const data = await this.apiFetch('data', {
                    row_dimension: this.rowDimension,
                    metrics: this.metricsSelecionadas,
                    filters: this.montarFiltros(),
                    limit: 1000
                });
                if (seq !== undefined && seq !== this.reqSeq) return;

                const total = parseFloat(data.totals?.valor_bruto || 0);
                const isDateDim = this.rowDimension === 'data_documento' || this.rowDimension === 'data_nf';
                this.tableData = (data.data || []).map(row => {
                    const r = {
                        dimensao_id: row.dimensao_id,
                        dimensao: isDateDim ? this.formatDate(row.dimensao) : row.dimensao
                    };
                    Object.keys(this.metricsDisponiveis).forEach(m => { r[m] = parseFloat(row[m] || 0); });
                    r.percentual_participacao = total > 0 && row.valor_total !== undefined
                        ? ((parseFloat(row.valor_total || 0) / total) * 100).toFixed(2)
                        : null;
                    return r;
                });

                this.totals = {
                    valor_bruto: total,
                    valor_devolvido: parseFloat(data.totals?.valor_devolvido || 0),
                    quantidade: parseFloat(data.totals?.quantidade || 0),
                    peso: parseFloat(data.totals?.peso || 0),
                    comissao: parseFloat(data.totals?.comissao || 0),
                    qtd_documentos: parseInt(data.totals?.qtd_documentos || 0),
                    ticket_medio: parseFloat(data.totals?.ticket_medio || 0)
                };
                this.comparativo = data.comparativo || null;
                this.rowDimensionLabel = data.row_dimension_label || this.rowDimensionLabel;
                this.timeSeriesData = data.time_series || { labels: [], values: [], series: {} };

                const avisos = [];
                if (data.warning) avisos.push(data.warning);
                if (data.truncado) avisos.push('Tabela limitada às 1000 maiores linhas; os cards e o gráfico consideram todos os dados.');
                this.aviso = avisos.join(' ');

                this.$nextTick(() => this.renderGrafico());
            } catch (e) {
                if (seq !== undefined && seq !== this.reqSeq) return;
                this.erro = e.message;
                this.tableData = [];
                this.timeSeriesData = { labels: [], values: [], series: {} };
                this.renderGrafico();
            }
        },

        // ================================================================
        // RANKING
        // ================================================================
        async carregarRanking(seq) {
            try {
                const data = await this.apiFetch('ranking', {
                    dimension: 'cliente',
                    metric: 'valor_total',
                    limit: 10,
                    filters: this.montarFiltros()
                });
                if (seq !== undefined && seq !== this.reqSeq) return;
                this.ranking = data.ranking || [];
            } catch (e) {
                if (seq !== undefined && seq !== this.reqSeq) return;
                this.ranking = [];
                this.erro = this.erro || e.message;
            }
            this.atualizarRankingDOM();
        },

        esc(s) {
            return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        },

        atualizarRankingDOM() {
            const container = document.getElementById('rankingContainer');
            if (!container) return;

            if (!this.ranking?.length) {
                container.innerHTML = `<div class="text-center py-8 text-slate-400"><i class="fa-solid fa-chart-line text-3xl mb-2 block"></i><p class="text-sm">Sem dados para os filtros selecionados</p></div>`;
                return;
            }

            container.innerHTML = '';
            this.ranking.forEach((item, idx) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 cursor-pointer';
                const medal = idx === 0 ? 'bg-amber-500 text-white' : idx === 1 ? 'bg-slate-300' : idx === 2 ? 'bg-amber-700 text-white' : 'bg-slate-100';
                const nome = this.esc(item.nome || '---');
                div.innerHTML = `<div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${medal}">${idx + 1}</span><span class="text-sm truncate max-w-[150px]" title="${nome}">${nome}</span></div><div class="text-right"><span class="text-xs font-bold text-emerald-600">${this.esc(this.formatMoney(item.valor))}</span><span class="text-[10px] ml-1">(${this.esc(item.percentual)}%)</span></div>`;
                div.addEventListener('click', () => this.verDetalhes({ dimensao_id: item.id, dimensao: item.nome }, 'cliente'));
                container.appendChild(div);
            });
        },

        // ================================================================
        // GRÁFICO
        // ================================================================
        renderGrafico() {
            if (window.cuboChart) {
                window.cuboChart.destroy();
                window.cuboChart = null;
            }
            const canvas = document.getElementById('graficoEvolucao');
            if (!canvas) return;

            const metrica = document.getElementById('graficoTipo')?.value || 'valor_total';
            const rotulos = {
                valor_total: 'Valor Líquido (R$)',
                comissao_total: 'Comissão Ajustada (R$)',
                valor_devolvido: 'Devolução (R$)',
                quantidade_total: 'Quantidade',
                peso_total: 'Peso líquido (kg)'
            };
            const labels = this.timeSeriesData.labels || [];
            const serie = this.timeSeriesData.series?.[metrica] || (metrica === 'valor_total' ? this.timeSeriesData.values : []) || [];
            if (!labels.length) return;

            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            if (metrica === 'valor_devolvido') {
                gradient.addColorStop(0, '#dc2626');
                gradient.addColorStop(1, '#f87171');
            } else if (metrica === 'comissao_total') {
                gradient.addColorStop(0, '#d97706');
                gradient.addColorStop(1, '#fbbf24');
            } else {
                gradient.addColorStop(0, '#059669');
                gradient.addColorStop(1, '#34d399');
            }
            window.cuboChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{ label: rotulos[metrica] || metrica, data: serie.map(v => parseFloat(v) || 0), backgroundColor: gradient, borderRadius: 8 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        },

        // ================================================================
        // UTILITÁRIOS
        // ================================================================
        getMetricLabel(m) { return this.metricsDisponiveis[m]?.label || m; },
        getMetricClass(m) {
            const c = {
                valor_total: 'text-emerald-600 font-bold',
                valor_devolvido: 'text-red-600 font-bold',
                quantidade_total: 'text-blue-600',
                peso_total: 'text-indigo-600',
                comissao_total: 'text-amber-600 font-bold',
                qtd_documentos: 'text-slate-700',
                ticket_medio: 'text-teal-600'
            };
            return c[m] || '';
        },
        formatMetric(v, m) {
            return ['valor_total', 'valor_devolvido', 'comissao_total', 'ticket_medio'].includes(m)
                ? this.formatMoney(v)
                : this.formatNumber(v);
        },
        formatMoney(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(parseFloat(v) || 0); },
        formatNumber(v) { const n = parseFloat(v); return isNaN(n) ? '0' : n.toLocaleString('pt-BR', { maximumFractionDigits: 2 }); },
        formatDate(v) {
            if (!v) return '';
            const m = String(v).trim().match(/^(\d{4})-(\d{2})-(\d{2})$/);
            return m ? `${m[3]}/${m[2]}/${m[1]}` : String(v);
        },
        variacao(chave) {
            const v = this.comparativo?.[chave]?.variacao_pct;
            if (v === null || v === undefined) return '';
            return (v > 0 ? '▲ +' : v < 0 ? '▼ ' : '') + v.toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '% vs. período anterior';
        },
        variacaoClasse(chave) {
            const v = this.comparativo?.[chave]?.variacao_pct;
            if (chave === 'valor_devolvido') {
                return v > 0 ? 'text-red-500' : v < 0 ? 'text-emerald-600' : 'text-slate-400';
            }
            return v > 0 ? 'text-emerald-600' : v < 0 ? 'text-red-500' : 'text-slate-400';
        },

        abrirModalMetricas() { this.modalMetricasOpen = true; },

        async aplicarMetricas() {
            if (!this.metricsSelecionadas.length) {
                this.erro = 'Selecione ao menos uma métrica.';
                return;
            }
            this.modalMetricasOpen = false;
            await this.atualizar();
        },

        // ================================================================
        // EXPORTAÇÃO
        // ================================================================
        async exportarDados() {
            const msg = this.validar();
            if (msg) {
                this.erro = msg;
                return;
            }
            try {
                const r = await fetch('/v1/vendas/cubo/exportar', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken'), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ row_dimension: this.rowDimension, metrics: this.metricsSelecionadas, filters: this.montarFiltros() })
                });
                if (!r.ok) {
                    let texto = `Erro ${r.status} ao exportar.`;
                    try { texto = (await r.json()).error || texto; } catch (e) { /* mantém texto padrão */ }
                    throw new Error(texto);
                }
                const blob = await r.blob();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `cubo_vendas_${new Date().toISOString().slice(0, 10)}.csv`;
                a.click();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            } catch (e) {
                this.erro = e.message;
            }
        },

        // ================================================================
        // DRILL-DOWN
        // ================================================================
        async verDetalhes(row, dimensao) {
            const dim = dimensao || this.rowDimension;
            this.modalDetalhesTitulo = `Detalhamento: ${row.dimensao ?? ''}`;
            this.modalDetalhesOpen = true;
            this.detalhesCarregando = true;
            this.detalhesErro = '';
            this.documentosAgrupados = [];
            this.detalhesTotais = { valor_bruto: 0, valor_devolvido: 0, quantidade: 0, peso: 0, comissao: 0, qtd_documentos: 0 };
            try {
                const data = await this.apiFetch('detalhes', {
                    dimensao: dim,
                    id: row.dimensao_id ?? null,
                    valor: row.dimensao,
                    filters: this.montarFiltros()
                });
                const agrupado = {};
                (data.detalhes || []).forEach(item => {
                    const chave = `${item.tipo_documento}_${item.numero_nf}`;
                    if (!agrupado[chave]) {
                        agrupado[chave] = {
                            chave, numero_nf: item.numero_nf, numero_fiscal: item.numero_fiscal, cliente: item.cliente,
                            tipo_documento: item.tipo_documento, valor_bruto: 0, valor_devolvido: 0, quantidade: 0, peso: 0, comissao: 0,
                            qtd_itens: 0, expandido: false, carregando: false, itens: null
                        };
                    }
                    const d = agrupado[chave];
                    d.valor_bruto += parseFloat(item.valor_bruto || 0);
                    d.valor_devolvido += parseFloat(item.valor_devolvido || 0);
                    d.quantidade += parseFloat(item.quantidade || 0);
                    d.peso += parseFloat(item.peso || 0);
                    d.comissao += parseFloat(item.comissao || 0);
                    d.qtd_itens += 1;
                });
                this.documentosAgrupados = Object.values(agrupado);
                this.detalhesTruncado = !!data.truncado;
                const t = data.totais || {};
                this.detalhesTotais = {
                    valor_bruto: parseFloat(t.valor_bruto || 0),
                    valor_devolvido: parseFloat(t.valor_devolvido || 0),
                    quantidade: parseFloat(t.quantidade || 0),
                    peso: parseFloat(t.peso || 0),
                    comissao: parseFloat(t.comissao || 0),
                    qtd_documentos: parseInt(t.qtd_documentos || 0)
                };
            } catch (e) {
                this.detalhesErro = e.message;
            }
            this.detalhesCarregando = false;
        },

        toggleDocumento(doc) {
            doc.expandido = !doc.expandido;
            if (doc.expandido && !doc.itens) this.carregarItensDocumento(doc);
        },

        async carregarItensDocumento(doc) {
            if (doc.itens) return;
            doc.carregando = true;
            doc.erro = '';
            try {
                const data = await this.apiFetch('itens-documento', { tipo: doc.tipo_documento, numero_documento: doc.numero_nf });
                doc.itens = data.itens || [];
            } catch (e) {
                doc.itens = [];
                doc.erro = e.message;
            }
            doc.carregando = false;
        }
    };
}

// Troca da métrica do gráfico
function mudarTipoGrafico() {
    const el = document.querySelector('[x-data]');
    const data = el?._x_dataStack?.[0];
    if (data?.renderGrafico) data.renderGrafico();
}
window.mudarTipoGrafico = mudarTipoGrafico;
