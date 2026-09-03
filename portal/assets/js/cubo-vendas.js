// ==========================================================================
// CUBO VENDAS - VERSÃO COMPLETA COM DEBUG
// ==========================================================================

function cuboVendasHandler() {
    return {
        // Estado
        filialSelecionada: '',
        gestorSelecionado: '',
        representanteSelecionado: '',
        carregamentoInicial: true,
        
        // Listas
        filiaisPermitidas: [],
        gestoresDisponiveis: [],
        representantesDisponiveis: [],
        
        // Modais
        modalFiltrosOpen: false,
        modalMetricasOpen: false,
        modalDetalhesOpen: false,
        modalDetalhesTitulo: 'Detalhamento',
        documentosAgrupados: [],
        detalhesTotais: { valor_bruto: 0, quantidade: 0, peso: 0 },
        
        // Filtros
        filters: {
            data_inicio: new Date().toISOString().slice(0, 8) + '01',
            data_fim: new Date().toISOString().slice(0, 10),
            cliente: '', regiao: '', estado: '', cidade: '',
            grupo: '', subgrupo: '', tipo_produto: '',
            marca: '', produto: '', tipo_de_pedido: ''
        },
        
        filterOptions: {
            cliente: [], regiao: [], estado: [], cidade: [], 
            grupo: [], subgrupo: [], tipo_produto: [], 
            marca: [], produto: [], tipo_de_pedido: []
        },
        
        // Config
        dimensionsDisponiveis: {},
        metricsDisponiveis: {},
        metricsSelecionadas: ['valor_bruto', 'quantidade', 'peso', 'comissao'],
        rowDimension: 'data_nf',
        rowDimensionLabel: 'Data',
        
        // Dados
        totals: { valor_bruto: 0, quantidade: 0, peso: 0, comissao: 0 },
        tableData: [],
        ranking: [],
        timeSeriesData: { labels: [], values: [] },

        // ================================================================
        // INICIALIZAÇÃO
        // ================================================================
        async init() {
            console.log('🚀 init() iniciado');
            window.__cuboVendasData = this;
            
            await this.carregarFiliais();
            
            if (this.filiaisPermitidas.length === 1) {
                this.filialSelecionada = String(this.filiaisPermitidas[0].idfilial);
                this.filters.filial = this.filialSelecionada;
            }
            
            await this.carregarGestores();
            
            if (this.gestoresDisponiveis.length === 1) {
                this.gestorSelecionado = String(this.gestoresDisponiveis[0].id);
                this.filters.gestor = this.gestorSelecionado;
            }
            
            await this.carregarRepresentantes();
            await this.carregarConfig();
            await this.carregarDados();
            await this.carregarRanking();
            
            this.carregamentoInicial = false;
            console.log('✅ init() finalizado');
        },

        // ================================================================
        // API FETCH COM DEBUG
        // ================================================================
        async apiFetch(endpoint, body = null) {
            const token = localStorage.getItem('authToken');
            
            if (!token) {
                alert('Sessão expirada. Faça login novamente.');
                window.location.href = '/portal/login.php';
                throw new Error('Token não encontrado');
            }
            
            let url = `/v1/vendas/cubo/${endpoint}`;
            
    // ✅ Definir método correto baseado no endpoint
    const getEndpoints = ['config']; // Rotas que usam GET
    const method = getEndpoints.includes(endpoint) ? 'GET' : 'POST';
    
    const options = {
        method: method,
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    };
    
    // ✅ Só adiciona body se for POST e tiver dados
    if (method === 'POST') {
        options.body = body ? JSON.stringify(body) : '{}';
    }
    
    console.log('📡', method, url);
    
    const resp = await fetch(url, options);
    console.log('📥 Status:', resp.status, url);
    
    if (resp.status === 401) {
        alert('Sessão expirada.');
        window.location.href = '/portal/login.php';
        throw new Error('Não autorizado');
    }
    
    const text = await resp.text();
    try {
        const json = JSON.parse(text);
        if (!resp.ok) throw new Error(json.error || `HTTP ${resp.status}`);
        return json;
    } catch (e) {
        console.error('❌ Resposta inválida:', text.substring(0, 200));
        throw new Error('Erro na API');
    }
},

        // ================================================================
        // CARREGAR FILIAIS
        // ================================================================
        async carregarFiliais() {
            try {
                const data = await this.apiFetch('filiais', null);
                this.filiaisPermitidas = data.filiais || [];
                console.log('📦 Filiais:', this.filiaisPermitidas.length);
            } catch (e) {
                console.error('Erro filiais:', e);
            }
        },

     // ======================================================================
// CARREGAR GESTORES (filtrados pela filial selecionada)
// ======================================================================
async carregarGestores() {
    try {
        const idFilial = parseInt(this.filialSelecionada) || 0;
        
        // ✅ Se tem filial selecionada, filtra gestores por ela
        if (idFilial > 0) {
            const data = await this.apiFetch('supervisores-por-filial', {
                idfilial: idFilial
            });
            this.gestoresDisponiveis = data.supervisores || [];
        } else {
            // Sem filial, carrega todos os gestores liberados
            const data = await this.apiFetch('gestores', null);
            this.gestoresDisponiveis = data.gestores || [];
        }
        
        // ✅ Se só tem 1 gestor, auto-seleciona
        if (this.gestoresDisponiveis.length === 1 && this.carregamentoInicial) {
            this.gestorSelecionado = String(this.gestoresDisponiveis[0].id);
            this.filters.gestor = this.gestorSelecionado;
            await this.carregarRepresentantes();
        }
    } catch (e) {
        console.error('Erro gestores:', e);
        this.gestoresDisponiveis = [];
    }
},

        // ================================================================
        // CARREGAR REPRESENTANTES
        // ================================================================
    // ================================================================

    async carregarRepresentantes() {
        try {
            const idGestor = parseInt(this.gestorSelecionado) || 0;
            const idFilial = parseInt(this.filialSelecionada) || 0;
            
            console.log('🔍 Buscando representantes - Gestor:', idGestor, 'Filial:', idFilial);
            
            if (idGestor <= 0) {
                console.warn('⚠️ Nenhum gestor selecionado, não vou buscar representantes');
                this.representantesDisponiveis = [];
                return;
            }
            
            const data = await this.apiFetch('representantes-por-supervisor', {
                idsupervisor: idGestor,
                idfilial: idFilial
            });
            
        // ✅ Atribuição direta
        this.representantesDisponiveis = data.representantes || [];
        console.log('✅ Representantes carregados:', this.representantesDisponiveis.length);
        
    } catch (e) {
        console.error('Erro representantes:', e);
        this.representantesDisponiveis = [];
    }
},
  // ======================================================================
// CASCATA: Filial
// ======================================================================
async mudarFilial() {
    if (this.carregamentoInicial) return;
    
    this.gestorSelecionado = '';
    this.representanteSelecionado = '';
    this.representantesDisponiveis = [];
    this.gestoresDisponiveis = [];
    
    if (this.filialSelecionada) {
        this.filters.filial = this.filialSelecionada;
        // ✅ Recarregar gestores baseado na nova filial
        await this.carregarGestores();
    } else {
        delete this.filters.filial;
        // ✅ Sem filial, carrega todos os gestores liberados
        await this.carregarGestores();
    }
    
    await this.carregarDados();
    await this.carregarRanking();
},

        // ================================================================
        // CASCATA: Gestor
        // ================================================================
        async mudarGestor() {
            if (this.carregamentoInicial) return;
            this.representanteSelecionado = '';
            this.representantesDisponiveis = [];
            if (this.gestorSelecionado) {
                this.filters.gestor = this.gestorSelecionado;
                await this.carregarRepresentantes();
            } else {
                delete this.filters.gestor;
            }
            await this.carregarDados();
            await this.carregarRanking();
        },

        // ================================================================
        // CASCATA: Representante
        // ================================================================
        async mudarRepresentante() {
            if (this.carregamentoInicial) return;
            if (this.representanteSelecionado) {
                this.filters.representante = this.representanteSelecionado;
            } else {
                delete this.filters.representante;
            }
            await this.carregarDados();
            await this.carregarRanking();
        },

        // ================================================================
        // CONFIGURAÇÃO
        // ================================================================
        async carregarConfig() {
            try {
                const data = await this.apiFetch('config');
                this.dimensionsDisponiveis = data.dimensions || {};
                this.metricsDisponiveis = data.metrics || {};
                console.log('📦 Config carregada');
            } catch (e) {
                console.error('Erro config:', e);
            }
        },

        // ================================================================
        // DADOS
        // ================================================================
        async carregarDados() {
            try {
                const data = await this.apiFetch('data', {
                    row_dimension: this.rowDimension,
                    metrics: this.metricsSelecionadas,
                    filters: this.filters,
                    limit: 200
                });
                
                if (data.success) {
                    this.tableData = (data.data || []).map(row => ({
                        dimensao: row.dimensao,
                        valor_bruto: parseFloat(row.valor_total || 0),
                        quantidade: parseFloat(row.quantidade_total || 0),
                        peso: parseFloat(row.peso_total || 0),
                        comissao: parseFloat(row.comissao_total || 0),
                        percentual_participacao: '0.00'
                    }));
                    
                    this.totals = {
                        valor_bruto: parseFloat(data.totals?.valor_bruto || 0),
                        quantidade: parseFloat(data.totals?.quantidade || 0),
                        peso: parseFloat(data.totals?.peso || 0),
                        comissao: parseFloat(data.totals?.comissao || 0)
                    };
                    
                    this.rowDimensionLabel = data.row_dimension_label || 'Data';
                    this.timeSeriesData = data.time_series || { labels: [], values: [] };
                    
                    const totalGeral = this.totals.valor_bruto;
                    this.tableData.forEach(row => {
                        row.percentual_participacao = totalGeral > 0 
                        ? ((row.valor_bruto / totalGeral) * 100).toFixed(2)
                        : '0.00';
                    });
                    
                    setTimeout(() => this.renderGrafico(), 300);
                    console.log('📦 Dados carregados:', this.tableData.length, 'linhas');
                }
            } catch (e) {
                console.error('Erro dados:', e);
            }
        },

        // ================================================================
        // RANKING
        // ================================================================
        async carregarRanking() {
            try {
                const data = await this.apiFetch('ranking', {
                    dimension: 'cliente',
                    metric: 'valor_bruto',
                    limit: 10,
                    filters: this.filters
                });
                if (data.success) {
                    this.ranking = data.ranking || [];
                    this.atualizarRankingDOM();
                }
            } catch (e) {
                console.error('Erro ranking:', e);
            }
        },

        atualizarRankingDOM() {
            const container = document.getElementById('rankingContainer');
            if (!container) return;
            
            if (!this.ranking?.length) {
                container.innerHTML = `<div class="text-center py-8 text-slate-400"><i class="fa-solid fa-chart-line text-3xl mb-2 block"></i><p class="text-sm">Selecione filtros</p></div>`;
                return;
            }
            
            container.innerHTML = '';
            this.ranking.forEach((item, idx) => {
                const div = document.createElement('div');
                div.className = 'flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 cursor-pointer';
                let medal = idx === 0 ? 'bg-amber-500 text-white' : idx === 1 ? 'bg-slate-300' : idx === 2 ? 'bg-amber-700 text-white' : 'bg-slate-100';
                div.innerHTML = `<div class="flex items-center gap-2"><span class="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold ${medal}">${idx+1}</span><span class="text-sm truncate max-w-[150px]">${item.nome||'---'}</span></div><div class="text-right"><span class="text-xs font-bold text-emerald-600">${this.formatMoney(item.valor)}</span><span class="text-[10px] ml-1">(${item.percentual}%)</span></div>`;
                div.addEventListener('click', () => this.verDetalhes(item.nome, 'cliente'));
                container.appendChild(div);
            });
        },

        // ================================================================
        // GRÁFICO
        // ================================================================
        renderGrafico() {
            const canvas = document.getElementById('graficoEvolucao');
            if (!canvas || !this.timeSeriesData.labels?.length) return;
            if (window.cuboChart) window.cuboChart.destroy();
            const ctx = canvas.getContext('2d');
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, "#059669");
            gradient.addColorStop(1, "#34d399");
            window.cuboChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: this.timeSeriesData.labels,
                    datasets: [{ label: 'Valor (R$)', data: this.timeSeriesData.values.map(v => parseFloat(v)||0), backgroundColor: gradient, borderRadius: 8 }]
                },
                options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        },

        // ================================================================
        // UTILITÁRIOS
        // ================================================================
        getMetricLabel(m) { return this.metricsDisponiveis[m]?.label || m; },
        getMetricClass(m) { const c = { valor_bruto: 'text-emerald-600 font-bold', quantidade: 'text-blue-600', peso: 'text-indigo-600', comissao: 'text-amber-600' }; return c[m] || ''; },
        formatMetric(v, m) { return m === 'valor_bruto' || m === 'comissao' ? this.formatMoney(v) : this.formatNumber(v); },
        formatMoney(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v||0); },
        formatNumber(v) { const n = parseFloat(v); return isNaN(n) ? '0' : n.toLocaleString('pt-BR', { maximumFractionDigits: 2 }); },
        
        abrirModalFiltrosAvancados() { this.modalFiltrosOpen = true; },
        abrirModalMetricas() { this.modalMetricasOpen = true; },
        
        async exportarDados() {
            try {
                const r = await fetch('/v1/vendas/cubo/exportar', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken'), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ row_dimension: this.rowDimension, metrics: this.metricsSelecionadas, filters: this.filters })
                });
                const blob = await r.blob();
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = `cubo_vendas_${new Date().toISOString().slice(0,10)}.csv`;
                a.click();
            } catch(e) { console.error('Erro exportar:', e); }
        },

        async verDetalhes(valor, dimensao) {
            this.modalDetalhesTitulo = `Detalhamento: ${valor}`;
            this.modalDetalhesOpen = true;
            try {
                const data = await this.apiFetch('detalhes', { dimensao: dimensao || this.rowDimension, valor, filters: this.filters });
                if (data.success) {
                    const agrupado = {};
                    (data.detalhes||[]).forEach(item => {
                        const chave = `${item.tipo_documento}_${item.numero_nf}`;
                        if (!agrupado[chave]) agrupado[chave] = { numero_nf: item.numero_nf, cliente: item.cliente, tipo_documento: item.tipo_documento, valor_bruto:0, quantidade:0, peso:0, comissao:0, qtd_itens:0, expandido:false, carregando:false, itens:null };
                        agrupado[chave].valor_bruto += parseFloat(item.valor_bruto||0);
                        agrupado[chave].quantidade += parseFloat(item.quantidade||0);
                        agrupado[chave].peso += parseFloat(item.peso||0);
                        agrupado[chave].comissao += parseFloat(item.comissao||0);
                        agrupado[chave].qtd_itens += 1;
                    });
                    this.documentosAgrupados = Object.values(agrupado);
                    this.detalhesTotais = { valor_bruto: parseFloat(data.totais?.valor_bruto||0), quantidade: parseFloat(data.totais?.quantidade||0), peso: parseFloat(data.totais?.peso||0) };
                }
            } catch(e) { console.error('Erro detalhes:', e); }
        },

        toggleDocumento(doc) {
            doc.expandido = !doc.expandido;
            if (doc.expandido && !doc.itens) this.carregarItensDocumento(doc);
        },

        async carregarItensDocumento(doc) {
            if (doc.itens) return;
            doc.carregando = true;
            try {
                const data = await this.apiFetch('itens-documento', { tipo: doc.tipo_documento, numero_documento: doc.numero_nf });
                doc.itens = data.success ? (data.itens||[]) : [];
            } catch(e) { doc.itens = []; }
            doc.carregando = false;
        }
    };
}

// Função global
function mudarTipoGrafico() {
    const el = document.querySelector('[x-data]');
    if (el?._x_dataStack?.[0]?.renderGrafico) el._x_dataStack[0].renderGrafico();
}
window.mudarTipoGrafico = mudarTipoGrafico;