// ==========================================================================
// DASHBOARD EXECUTIVO DE VENDAS v3.0 — MODO ESPECIALISTA & PREMIUM
// Hierarquia: Filial → Gestor → Representante (escopo validado no backend)
// Foco simultâneo: Valor (R$), Peso Líquido (Kg/Ton) e Quantidade (Un/NFs)
// 6 Macro-Regiões Unificadas: SUL, GRF, AVI, NOR, PLN e RMC
// ==========================================================================

function dashboardVendasHandler() {
    const MAX_DIAS_PESADO = 90;
    const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    return {
        // Estado de carregamento
        loading: false,
        loadingPesados: false,
        loadingInsights: false,
        erro: '',
        erroPesados: '',
        erroInsights: '',
        avisoPeriodo: '',
        mensagemPesados: '',
        _inicializado: false,
        carregamentoInicial: true,
        _seq: { kpis: 0, pesados: 0, insights: 0 },
        _ctrl: { kpis: null, pesados: null, insights: null },

        // Modais de Rastreio de Card / Região
        modalCardOpen: false,
        modalCardTitulo: '',
        modalCardTipo: '',
        cardVisao: '',
        cardSubregioes: '',
        cardAba: 'resumo',
        cardBusca: '',
        cardDetalhes: [],
        cardPorDia: [],
        cardRastreio: [],
        cardTotal: null,
        cardRotulo: 'Docs',
        cardExtra: {},
        cardLoading: false,
        cardErro: '',

        // Modal Expandido de Projeção de Fechamento
        modalProjecaoOpen: false,
        abaProjecao: 'representantes',
        buscaProjecao: '',

        // Modal e Filtros de Clientes Sem Compras (+30d / +60d / +90d)
        modalRiscoOpen: false,
        filtroRiscoRep: '',
        filtroRiscoRegiao: '',
        filtroRiscoFaixa: '',
        buscaRiscoCliente: '',
        limiteRiscoPainel: 25,

        // Modais de Entidades (Representante, Funil, Produto, Cliente)
        modalRepresentanteOpen: false,
        modalFunilOpen: false,
        modalProdutoOpen: false,
        modalClienteOpen: false,
        representanteDetalhes: { nome: '', clientes: [], produtos: [], evolucao: { itens: [] }, total: null },
        repLoading: false,
        repErro: '',
        funilDetalhes: { etapa: '', pedidos: [], total: null, truncado: false },
        funilLoading: false,
        funilErro: '',
        produtoDetalhes: { nome: '', valor: 0, quantidade: 0, peso: 0, preco_medio: 0, preco_medio_kg: 0, total_clientes: 0, total_notas: 0, comissao: 0, clientes: [], evolucao: [] },
        produtoLoading: false,
        produtoErro: '',
        clienteDetalhes: { nome: '', valor: 0, total_notas: 0, ticket_medio: 0, quantidade: 0, peso: 0, produtos: [] },
        clienteLoading: false,
        clienteErro: '',

        // Filtros Globais
        periodoRapidoAtivo: 'mes_atual',
        filters: {
            data_inicio: '',
            data_fim: '',
            filial: '',
            gestor: '',
            representante: ''
        },

        // Listas da hierarquia
        filiaisPermitidas: [],
        gestoresDisponiveis: [],
        representantesDisponiveis: [],
        erroRepresentantes: '',

        // KPIs Principais (com Valor, Peso e Quantidade em foco)
        kpis: {
            faturamento: 0,
            faturamento_anterior: 0,
            variacao_faturamento: null,
            peso_faturado: 0,
            peso_anterior: 0,
            variacao_peso: null,
            quantidade_faturada: 0,
            quantidade_anterior: 0,
            variacao_quantidade: null,
            clientes_faturados: 0,
            notas: 0,
            notas_anterior: 0,
            variacao_notas: null,
            media_itens_nf: 0,
            media_peso_nf: 0,
            ticket_medio: 0,
            ticket_anterior: 0,
            variacao_ticket: null,
            preco_medio_kg: 0,
            preco_medio_un: 0,
            media_diaria: 0,
            media_peso_diario: 0,
            media_qtd_diaria: 0,
            media_notas_diaria: 0,
            media_dia_com_venda: 0,
            dias_uteis: 0,
            dias_com_venda: 0,
            carteira: { valor: 0, pedidos: 0, quantidade: 0, peso: 0, clientes: 0 },
            cancelados: { valor: 0, notas: 0, peso: 0 },
            devolucoes: { valor: 0, notas: 0, quantidade: 0, peso: 0, percentual_faturamento: 0 },
            positivacao: { positivados: 0, base: 0, nao_positivados: 0, percentual: 0 }
        },
        periodoInfo: { inicio: '', fim: '', dias: 0, dias_uteis: 0 },
        periodoAnterior: null,
        porDiaSemana: [],

        // Dados de Listas e Gráficos
        topProdutos: [],
        topClientes: [],
        distribuicaoRegiao: [],
        evolucao: { granularidade: 'dia', itens: [], series_periodo: {}, totais: {} },
        matrizCrossSelling: [],
        projecao: [],
        rankingRepresentantes: [],
        funilPedidos: [],
        funilTaxas: { conversao: null, conversao_valor: null, cancelamento: null },
        projecaoFechamento: { carregado: false, por_representante: [], por_regiao: [] },
        clientesRisco: [],
        resumoRisco: {
            total_clientes: 0,
            valor_historico_total: 0,
            peso_historico_total: 0,
            quantidade_historico_total: 0,
            nfs_historico_total: 0,
            faixas: {
                '30_45': { label: '30 a 45 dias', clientes: 0, valor: 0, peso: 0 },
                '46_60': { label: '46 a 60 dias', clientes: 0, valor: 0, peso: 0 },
                '61_90': { label: '61 a 90 dias', clientes: 0, valor: 0, peso: 0 },
                '90_mais': { label: '+90 dias', clientes: 0, valor: 0, peso: 0 }
            }
        },
        riscoCriterio: { dias_sem_comprar: 30, janela_dias: 365 },

        // Controles de Gráficos
        tipoGrafico: 'combinado', // 'combinado' | 'valor' | 'peso' | 'quantidade' | 'notas'
        periodoEvolucao: 'auto',  // 'auto' | 'dia' | 'semana' | 'mes'
        metricaRegiao: 'valor',   // 'valor' | 'peso'
        chartEvolucao: null,
        chartRegiao: null,
        chartProdutoEvolucao: null,
        maxProjecao: 1,

        // ======================================================================
        // INICIALIZAÇÃO
        // ======================================================================
        async init() {
            if (this._inicializado) return;
            this._inicializado = true;
            initModernTooltipsDash();

            if (!this.filters.data_inicio) {
                this.filters.data_inicio = this.getPrimeiroDiaMes();
                this.filters.data_fim = this.getUltimoDiaMes();
            }

            await this.carregarFiliais();
            if (this.filiaisPermitidas.length === 1) {
                this.filters.filial = String(this.filiaisPermitidas[0].idfilial);
            }

            await this.carregarGestores();
            if (this.gestoresDisponiveis.length === 1) {
                this.filters.gestor = String(this.gestoresDisponiveis[0].id);
                await this.carregarRepresentantes();
            }

            this.carregamentoInicial = false;
            await this.carregarDados();
        },

        // ======================================================================
        // API & CONTROLE DE CONCORRÊNCIA
        // ======================================================================
        getToken() {
            return localStorage.getItem('authToken') || '';
        },

        async apiFetch(url, body = null, signal = undefined) {
            const token = this.getToken();
            if (!token) {
                window.location.href = '/portal/login.php';
                throw new Error('Sem token');
            }

            let resp;
            try {
                resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: body ? JSON.stringify(body) : '{}',
                    signal
                });
            } catch (e) {
                if (e.name === 'AbortError') throw e;
                throw new Error('Falha de conexão com o servidor.');
            }

            if (resp.status === 401) {
                localStorage.clear();
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada');
            }

            const text = await resp.text();
            let json = null;
            try { json = JSON.parse(text); } catch (e) { /* tratado abaixo */ }

            if (!resp.ok || (json && json.success === false)) {
                throw new Error((json && json.error) || `Erro ${resp.status} ao consultar o servidor.`);
            }
            if (!json) throw new Error('Resposta inválida do servidor.');
            return json;
        },

        iniciar(grupo) {
            if (this._ctrl[grupo]) this._ctrl[grupo].abort();
            this._ctrl[grupo] = new AbortController();
            this._seq[grupo]++;
            return { id: this._seq[grupo], signal: this._ctrl[grupo].signal };
        },
        atual(grupo, id) {
            return this._seq[grupo] === id;
        },

        corpoFiltros(extra = {}) {
            return Object.assign({
                data_inicio: this.filters.data_inicio,
                data_fim: this.filters.data_fim,
                filial: this.filters.filial || '',
                gestor: this.filters.gestor || '',
                representante: this.filters.representante || ''
            }, extra);
        },

        // ======================================================================
        // HIERARQUIA DE FILTROS
        // ======================================================================
        async carregarFiliais() {
            try {
                const data = await this.apiFetch('/v1/vendas/cubo/filiais', null);
                this.filiaisPermitidas = data.filiais || [];
            } catch (e) {
                console.error('Erro filiais:', e);
                this.erro = e.message;
            }
        },

        async carregarGestores() {
            try {
                const idFilial = parseInt(this.filters.filial) || 0;
                if (idFilial > 0) {
                    const data = await this.apiFetch('/v1/vendas/cubo/supervisores-por-filial', { idfilial: idFilial });
                    this.gestoresDisponiveis = data.supervisores || [];
                } else {
                    const data = await this.apiFetch('/v1/vendas/cubo/gestores', null);
                    this.gestoresDisponiveis = data.gestores || [];
                }

                if (this.gestoresDisponiveis.length === 1 && this.carregamentoInicial) {
                    this.filters.gestor = String(this.gestoresDisponiveis[0].id);
                    await this.carregarRepresentantes();
                }
            } catch (e) {
                console.error('Erro gestores:', e);
                this.gestoresDisponiveis = [];
                this.erro = e.message;
            }
        },

        async carregarRepresentantes() {
            this.erroRepresentantes = '';
            try {
                const data = await this.apiFetch('/v1/vendas/cubo/representantes-por-supervisor', {
                    idsupervisor: parseInt(this.filters.gestor) || 0,
                    idfilial: parseInt(this.filters.filial) || 0
                });
                this.representantesDisponiveis = data.representantes || [];
            } catch (e) {
                console.error('Erro representantes:', e);
                this.representantesDisponiveis = [];
                this.erroRepresentantes = e.message;
            }
        },

        async mudarFilial() {
            if (this.carregamentoInicial) return;
            this.filters.gestor = '';
            this.filters.representante = '';
            this.representantesDisponiveis = [];
            this.gestoresDisponiveis = [];
            await this.carregarGestores();
            await this.carregarDados();
        },

        async mudarGestor() {
            if (this.carregamentoInicial) return;
            this.filters.representante = '';
            this.representantesDisponiveis = [];
            if (this.filters.gestor) await this.carregarRepresentantes();
            await this.carregarDados();
        },

        mudarRepresentante() {
            if (this.carregamentoInicial) return;
            this.carregarDados();
        },

        mudarPeriodo() {
            if (this.carregamentoInicial) return;
            this.periodoRapidoAtivo = '';
            this.carregarDados();
        },

        // ======================================================================
        // VALIDAÇÃO DO PERÍODO
        // ======================================================================
        validarPeriodo() {
            const a = this.parseISO(this.filters.data_inicio);
            const b = this.parseISO(this.filters.data_fim);
            this.avisoPeriodo = '';
            if (!a || !b) {
                this.erro = 'Informe as datas inicial e final.';
                return false;
            }
            if (a > b) {
                this.erro = 'A data inicial não pode ser maior que a data final.';
                return false;
            }
            const dias = Math.round((b - a) / 86400000) + 1;
            if (dias > 400) {
                this.erro = 'Período máximo de 400 dias.';
                return false;
            }
            if (dias > MAX_DIAS_PESADO) {
                this.avisoPeriodo = `Período acima de ${MAX_DIAS_PESADO} dias: a matriz "Produtos comprados juntos" fica desativada para preservar a performance.`;
            }
            return true;
        },

        // ======================================================================
        // CARREGAMENTO DOS DADOS
        // ======================================================================
        async carregarDados() {
            this.erro = '';
            if (!this.validarPeriodo()) {
                this.zerarDados();
                return;
            }

            const { id, signal } = this.iniciar('kpis');
            this.loading = true;
            this.carregarDadosPesados();
            this.carregarInsights();

            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/kpis', this.corpoFiltros(), signal);
                if (!this.atual('kpis', id)) return;

                this.periodoInfo = data.periodo || { inicio: this.filters.data_inicio, fim: this.filters.data_fim, dias: 0, dias_uteis: 0 };
                this.kpis = {
                    faturamento: data.faturamento?.valor || 0,
                    faturamento_anterior: data.faturamento?.anterior || 0,
                    variacao_faturamento: data.faturamento?.variacao ?? null,
                    peso_faturado: data.faturamento?.peso || 0,
                    peso_anterior: data.faturamento?.peso_anterior || 0,
                    variacao_peso: data.faturamento?.variacao_peso ?? null,
                    quantidade_faturada: data.faturamento?.quantidade || 0,
                    quantidade_anterior: data.faturamento?.quantidade_anterior || 0,
                    variacao_quantidade: data.faturamento?.variacao_quantidade ?? null,
                    clientes_faturados: data.faturamento?.clientes || 0,
                    notas: data.notas?.atual || 0,
                    notas_anterior: data.notas?.anterior || 0,
                    variacao_notas: data.notas?.variacao ?? null,
                    media_itens_nf: data.notas?.media_itens_nf || 0,
                    media_peso_nf: data.notas?.media_peso_nf || 0,
                    ticket_medio: data.ticket_medio?.atual || 0,
                    ticket_anterior: data.ticket_medio?.anterior || 0,
                    variacao_ticket: data.ticket_medio?.variacao ?? null,
                    preco_medio_kg: data.ticket_medio?.preco_medio_kg || 0,
                    preco_medio_un: data.ticket_medio?.preco_medio_un || 0,
                    media_diaria: data.velocidade_venda?.media_diaria || 0,
                    media_peso_diario: data.velocidade_venda?.media_peso_diario || 0,
                    media_qtd_diaria: data.velocidade_venda?.media_qtd_diaria || 0,
                    media_notas_diaria: data.velocidade_venda?.media_notas_diaria || 0,
                    media_dia_com_venda: data.velocidade_venda?.media_dia_com_venda || 0,
                    dias_uteis: data.velocidade_venda?.dias_uteis || 0,
                    dias_com_venda: data.velocidade_venda?.dias_com_venda || 0,
                    carteira: data.carteira || { valor: 0, pedidos: 0, quantidade: 0, peso: 0, clientes: 0 },
                    cancelados: data.cancelados || { valor: 0, notas: 0, peso: 0 },
                    devolucoes: data.devolucoes || { valor: 0, notas: 0, quantidade: 0, peso: 0, percentual_faturamento: 0 },
                    positivacao: data.positivacao || { positivados: 0, base: 0, nao_positivados: 0, percentual: 0 }
                };
                this.periodoAnterior = data.periodo_anterior || null;
                this.porDiaSemana = data.velocidade_venda?.por_dia_semana || [];
                this.topProdutos = data.top_produtos || [];
                this.topClientes = data.top_clientes || [];
                this.distribuicaoRegiao = data.distribuicao_regiao || [];
                this.evolucao = data.evolucao || { granularidade: 'dia', itens: [], series_periodo: {}, totais: {} };

                setTimeout(() => {
                    this.renderizarGraficoEvolucao();
                    this.renderizarGraficoRegiao();
                }, 50);
            } catch (e) {
                if (e.name === 'AbortError' || !this.atual('kpis', id)) return;
                console.error('Erro dados:', e);
                this.erro = e.message;
                this.zerarDados();
            } finally {
                if (this.atual('kpis', id)) this.loading = false;
            }
        },

        zerarDados() {
            this.topProdutos = [];
            this.topClientes = [];
            this.distribuicaoRegiao = [];
            this.evolucao = { granularidade: 'dia', itens: [], series_periodo: {}, totais: {} };
            this.matrizCrossSelling = [];
            this.projecao = [];
            this.rankingRepresentantes = [];
            this.funilPedidos = [];
            this.projecaoFechamento = { carregado: false, por_representante: [], por_regiao: [] };
            this.clientesRisco = [];
            this.destruirGrafico('chartEvolucao');
            this.destruirGrafico('chartRegiao');
        },

        async carregarDadosPesados() {
            const { id, signal } = this.iniciar('pesados');
            this.loadingPesados = true;
            this.erroPesados = '';
            this.mensagemPesados = '';
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/kpis-detalhes', this.corpoFiltros(), signal);
                if (!this.atual('pesados', id)) return;
                this.matrizCrossSelling = dados.matriz_cross_selling || [];
                this.projecao = this.formatarProjecao(dados.projecao_vendas || []);
                this.maxProjecao = this.projecao.length > 0
                    ? Math.max(...this.projecao.map(p => Math.max(p.valor || 0, p.valor_projetado || 0, p.projecao_com_carteira || 0)), 1)
                    : 1;
                this.mensagemPesados = dados.mensagem || '';
            } catch (e) {
                if (e.name === 'AbortError' || !this.atual('pesados', id)) return;
                console.error('Erro dados pesados:', e);
                this.erroPesados = e.message;
                this.matrizCrossSelling = [];
                this.projecao = [];
            } finally {
                if (this.atual('pesados', id)) this.loadingPesados = false;
            }
        },

        async carregarInsights() {
            const { id, signal } = this.iniciar('insights');
            this.loadingInsights = true;
            this.erroInsights = '';
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/insights', this.corpoFiltros(), signal);
                if (!this.atual('insights', id)) return;
                this.rankingRepresentantes = dados.ranking_representantes || [];
                this.funilPedidos = dados.funil_pedidos?.etapas || [];
                this.funilTaxas = {
                    conversao: dados.funil_pedidos?.taxa_conversao ?? null,
                    conversao_valor: dados.funil_pedidos?.taxa_conversao_valor ?? null,
                    cancelamento: dados.funil_pedidos?.taxa_cancelamento ?? null
                };
                this.projecaoFechamento = dados.projecao_fechamento || { carregado: false, por_representante: [], por_regiao: [] };
                this.clientesRisco = dados.clientes_risco || [];
                if (dados.resumo_risco) {
                    this.resumoRisco = dados.resumo_risco;
                }
                this.riscoCriterio = dados.risco_criterio || this.riscoCriterio;
            } catch (e) {
                if (e.name === 'AbortError' || !this.atual('insights', id)) return;
                console.error('Erro insights:', e);
                this.erroInsights = e.message;
                this.rankingRepresentantes = [];
                this.funilPedidos = [];
                this.projecaoFechamento = { carregado: false, por_representante: [], por_regiao: [] };
                this.clientesRisco = [];
            } finally {
                if (this.atual('insights', id)) this.loadingInsights = false;
            }
        },

        // ======================================================================
        // GRÁFICOS AVANÇADOS (EVOLUÇÃO MULTIMÉTRICA + MACRO-REGIÕES OFICIAIS)
        // ======================================================================
        destruirGrafico(nome) {
            if (this[nome]) {
                try { this[nome].destroy(); } catch (e) { /* já destruído */ }
                this[nome] = null;
            }
        },

        itensEvolucaoAtiva() {
            const sp = this.evolucao?.series_periodo || {};
            if (this.periodoEvolucao !== 'auto' && Array.isArray(sp[this.periodoEvolucao]) && sp[this.periodoEvolucao].length > 0) {
                return sp[this.periodoEvolucao];
            }
            return this.evolucao?.itens || [];
        },

        resumoEvolucaoAtiva() {
            const itens = this.itensEvolucaoAtiva();
            if (!itens.length) {
                return { valor: 0, peso: 0, quantidade: 0, notas: 0, media_valor: 0, media_peso: 0, pico_label: '—', pico_valor: 0 };
            }
            let valor = 0, peso = 0, quantidade = 0, notas = 0, picoValor = 0, picoLabel = '—';
            for (const i of itens) {
                const v = parseFloat(i.valor || 0);
                valor += v;
                peso += parseFloat(i.peso || 0);
                quantidade += parseFloat(i.quantidade || 0);
                notas += parseInt(i.notas || 0, 10);
                if (v > picoValor) {
                    picoValor = v;
                    picoLabel = i.label || i.periodo || '—';
                }
            }
            return {
                valor,
                peso,
                quantidade,
                notas,
                media_valor: valor / itens.length,
                media_peso: peso / itens.length,
                pico_label: picoLabel,
                pico_valor: picoValor
            };
        },

        mudarTipoGrafico(tipo) {
            this.tipoGrafico = tipo;
            this.renderizarGraficoEvolucao();
        },

        mudarPeriodoEvolucao(periodo) {
            this.periodoEvolucao = periodo;
            this.renderizarGraficoEvolucao();
        },

        mudarMetricaRegiao(metrica) {
            this.metricaRegiao = metrica;
            this.renderizarGraficoRegiao();
        },

        renderizarGraficoEvolucao() {
            this.destruirGrafico('chartEvolucao');
            const el = document.querySelector('#chartEvolucao');
            const itens = this.itensEvolucaoAtiva();
            if (!el) return;
            el.innerHTML = '';
            if (!itens.length) return;

            const labels = itens.map(m => m.label);
            const vals = itens.map(m => Math.round((parseFloat(m.valor) || 0) * 100) / 100);
            const pesos = itens.map(m => Math.round((parseFloat(m.peso) || 0) * 100) / 100);
            const qtds = itens.map(m => Math.round((parseFloat(m.quantidade) || 0) * 100) / 100);
            const nfs = itens.map(m => parseInt(m.notas || 0, 10));

            if (this.tipoGrafico === 'combinado') {
                this.chartEvolucao = new ApexCharts(el, {
                    chart: { type: 'line', height: 320, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
                    series: [
                        { name: 'Faturamento (R$)', type: 'column', data: vals },
                        { name: 'Peso Líquido (Kg)', type: 'line', data: pesos }
                    ],
                    xaxis: { categories: labels, tickAmount: Math.min(labels.length, 14), labels: { style: { fontSize: '10px' } } },
                    yaxis: [
                        {
                            title: { text: 'Faturamento (R$)', style: { color: '#059669', fontSize: '11px', fontWeight: 700 } },
                            labels: {
                                formatter: (v) => v >= 1000000
                                    ? 'R$ ' + (v / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + 'M'
                                    : 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 'k'
                            }
                        },
                        {
                            opposite: true,
                            title: { text: 'Peso Líquido (Kg)', style: { color: '#d97706', fontSize: '11px', fontWeight: 700 } },
                            labels: {
                                formatter: (v) => v >= 1000
                                    ? (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' t'
                                    : v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' kg'
                            }
                        }
                    ],
                    colors: ['#059669', '#f59e0b'],
                    stroke: { width: [0, 3], curve: 'smooth' },
                    plotOptions: { bar: { borderRadius: 5, columnWidth: '55%' } },
                    dataLabels: { enabled: false },
                    grid: { borderColor: '#f1f5f9' },
                    legend: { position: 'top', horizontalAlign: 'right', fontSize: '11px', fontWeight: 600 },
                    responsive: [{
                        breakpoint: 640,
                        options: {
                            chart: { height: 260 },
                            xaxis: { tickAmount: Math.min(labels.length, 6), labels: { style: { fontSize: '9px' } } },
                            yaxis: [
                                {
                                    title: { text: '' },
                                    labels: {
                                        style: { fontSize: '9px' },
                                        formatter: (v) => v >= 1000000
                                            ? (v / 1000000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + 'M'
                                            : (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 'k'
                                    }
                                },
                                {
                                    opposite: true,
                                    title: { text: '' },
                                    labels: {
                                        style: { fontSize: '9px' },
                                        formatter: (v) => v >= 1000
                                            ? (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 't'
                                            : v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 'kg'
                                    }
                                }
                            ],
                            legend: { position: 'bottom', horizontalAlign: 'center', fontSize: '10px' }
                        }
                    }],
                    tooltip: {
                        shared: true,
                        intersect: false,
                        y: {
                            formatter: (v, { seriesIndex, dataPointIndex }) => {
                                const row = itens[dataPointIndex] || {};
                                if (seriesIndex === 0) {
                                    return this.formatMoney(v) + ` (${row.notas || 0} NFs)`;
                                }
                                return this.formatPeso(v) + ` (${this.formatNumber(row.quantidade || 0)} un.)`;
                            }
                        }
                    }
                });
                this.chartEvolucao.render();
                return;
            }

            const mapaConfig = {
                valor: {
                    name: 'Faturamento (R$)',
                    data: vals,
                    color: '#059669',
                    yFmt: (v) => 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 'k',
                    tipFmt: (v, idx) => `${this.formatMoney(v)} • ${this.formatPeso(pesos[idx] || 0)} • ${nfs[idx] || 0} NFs`
                },
                peso: {
                    name: 'Peso Líquido (Kg)',
                    data: pesos,
                    color: '#d97706',
                    yFmt: (v) => v >= 1000 ? (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' t' : v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' kg',
                    tipFmt: (v, idx) => `${this.formatPeso(v)} • ${this.formatMoney(vals[idx] || 0)} • ${this.formatNumber(qtds[idx] || 0)} un.`
                },
                quantidade: {
                    name: 'Quantidade (Unid.)',
                    data: qtds,
                    color: '#2563eb',
                    yFmt: (v) => v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' un',
                    tipFmt: (v, idx) => `${this.formatNumber(v)} un. • ${this.formatPeso(pesos[idx] || 0)} • ${this.formatMoney(vals[idx] || 0)}`
                },
                notas: {
                    name: 'NFs Faturadas',
                    data: nfs,
                    color: '#7c3aed',
                    yFmt: (v) => v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' NFs',
                    tipFmt: (v, idx) => `${v} NFs • ${this.formatMoney(vals[idx] || 0)} • ${this.formatPeso(pesos[idx] || 0)}`
                }
            };
            const cfg = mapaConfig[this.tipoGrafico] || mapaConfig.valor;

            this.chartEvolucao = new ApexCharts(el, {
                chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'Plus Jakarta Sans, sans-serif' },
                series: [{ name: cfg.name, data: cfg.data }],
                xaxis: { categories: labels, tickAmount: Math.min(labels.length, 14), labels: { style: { fontSize: '10px' } } },
                yaxis: { labels: { formatter: cfg.yFmt } },
                colors: [cfg.color],
                dataLabels: { enabled: false },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.05 } },
                stroke: { curve: 'smooth', width: 3 },
                grid: { borderColor: '#f1f5f9' },
                tooltip: {
                    y: {
                        formatter: (v, { dataPointIndex }) => cfg.tipFmt(v, dataPointIndex)
                    }
                }
            });
            this.chartEvolucao.render();
        },

        renderizarGraficoRegiao() {
            this.destruirGrafico('chartRegiao');
            const el = document.querySelector('#chartRegiao');
            if (!el) return;
            el.innerHTML = '';
            const isPeso = this.metricaRegiao === 'peso';
            const regs = (this.distribuicaoRegiao || []).filter(r => (parseFloat(isPeso ? r.peso : r.valor) || 0) > 0 || (r.notas || 0) > 0);
            if (!regs.length) return;

            const series = regs.map(r => parseFloat(isPeso ? r.peso : r.valor) || 0);
            const labels = regs.map(r => `${r.regiao} — ${r.nome || ''}`);
            const colors = regs.map(r => r.cor || '#059669');

            this.chartRegiao = new ApexCharts(el, {
                chart: {
                    type: 'donut',
                    height: 290,
                    fontFamily: 'Plus Jakarta Sans, sans-serif',
                    events: {
                        dataPointSelection: (event, chartContext, config) => {
                            const r = regs[config.dataPointIndex];
                            if (r) this.abrirDetalhesRegiao(r);
                        }
                    }
                },
                series,
                labels,
                colors,
                plotOptions: {
                    pie: {
                        donut: {
                            size: '66%',
                            labels: {
                                show: true,
                                name: { show: true, fontSize: '11px', fontWeight: 700 },
                                value: {
                                    show: true,
                                    fontSize: '14px',
                                    fontWeight: 800,
                                    formatter: (v) => isPeso ? this.formatPeso(parseFloat(v)) : this.formatMoney(parseFloat(v))
                                },
                                total: {
                                    show: true,
                                    label: isPeso ? 'Peso Total Regiões' : 'Total Regiões',
                                    fontSize: '10px',
                                    fontWeight: 700,
                                    color: '#64748b',
                                    formatter: (w) => {
                                        const tot = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                        return isPeso ? this.formatPeso(tot) : this.formatMoney(tot);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: true, formatter: (val) => val.toFixed(1) + '%' },
                legend: { position: 'bottom', fontSize: '11px', fontWeight: 600 },
                tooltip: {
                    y: {
                        formatter: (v, { seriesIndex }) => {
                            const r = regs[seriesIndex] || {};
                            return `${this.formatMoney(r.valor || 0)} | ${this.formatPeso(r.peso || 0)} | ${r.notas || 0} NFs (${r.clientes || 0} cli)`;
                        }
                    }
                }
            });
            this.chartRegiao.render();
        },

        renderizarGraficoProduto() {
            this.destruirGrafico('chartProdutoEvolucao');
            const el = document.querySelector('#chartProdutoEvolucao');
            if (!el) return;
            el.innerHTML = '';
            const evo = this.produtoDetalhes.evolucao || [];
            if (!evo.length) return;

            this.chartProdutoEvolucao = new ApexCharts(el, {
                chart: { type: 'line', height: 250, toolbar: { show: false } },
                series: [
                    { name: 'Faturamento (R$)', type: 'column', data: evo.map(m => parseFloat(m.valor) || 0) },
                    { name: 'Peso (Kg)', type: 'line', data: evo.map(m => parseFloat(m.peso) || 0) }
                ],
                xaxis: { categories: evo.map(m => m.label) },
                yaxis: [
                    { labels: { formatter: (v) => 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + 'k' } },
                    { opposite: true, labels: { formatter: (v) => this.formatPeso(v) } }
                ],
                colors: ['#059669', '#f59e0b'],
                stroke: { width: [0, 3], curve: 'smooth' },
                dataLabels: { enabled: false },
                title: { text: 'Evolução mensal do produto — Valor (R$) e Peso (Kg)', style: { fontSize: '12px' } },
                tooltip: {
                    y: {
                        formatter: (v, { seriesIndex }) => seriesIndex === 0 ? this.formatMoney(v) : this.formatPeso(v)
                    }
                }
            });
            this.chartProdutoEvolucao.render();
        },

        // ======================================================================
        // MODAIS DE DETALHES & RASTREIO (CARDS, REGIÕES, PRODUTOS, CLIENTES)
        // ======================================================================
        async verDetalhesProduto(idproduto, nome) {
            this.modalProdutoOpen = true;
            this.produtoLoading = true;
            this.produtoErro = '';
            this.destruirGrafico('chartProdutoEvolucao');
            this.produtoDetalhes = { nome, valor: 0, quantidade: 0, peso: 0, preco_medio: 0, preco_medio_kg: 0, total_clientes: 0, total_notas: 0, comissao: 0, clientes: [], evolucao: [] };

            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/produto-detalhes', this.corpoFiltros({ idproduto }));
                this.produtoDetalhes = {
                    nome,
                    valor: data.produto?.valor || 0,
                    quantidade: data.produto?.quantidade || 0,
                    peso: data.produto?.peso || 0,
                    preco_medio: data.produto?.preco_medio || 0,
                    preco_medio_kg: data.produto?.preco_medio_kg || 0,
                    total_clientes: data.produto?.total_clientes || 0,
                    total_notas: data.produto?.total_notas || 0,
                    comissao: data.produto?.comissao || 0,
                    clientes: data.clientes || [],
                    evolucao: data.evolucao || []
                };
                setTimeout(() => this.renderizarGraficoProduto(), 50);
            } catch (e) {
                console.error('Erro produto:', e);
                this.produtoErro = e.message;
            } finally {
                this.produtoLoading = false;
            }
        },

        fecharModalProduto() {
            this.modalProdutoOpen = false;
            this.destruirGrafico('chartProdutoEvolucao');
        },

        async verDetalhesCliente(idcliente, nome) {
            if (!idcliente) return;
            this.modalClienteOpen = true;
            this.clienteLoading = true;
            this.clienteErro = '';
            this.clienteDetalhes = {
                id: idcliente,
                nome: nome || 'Cliente #' + idcliente,
                cnpj: '',
                cidade: '',
                estado: '',
                regiao: '',
                subregiao: '',
                representante: '',
                ultima_compra: null,
                dias_sem_comprar: null,
                modo: 'periodo',
                periodo_label: '',
                valor: 0,
                total_notas: 0,
                ticket_medio: 0,
                quantidade: 0,
                peso: 0,
                produtos: []
            };

            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/cliente-detalhes', this.corpoFiltros({ idcliente }));
                const c = data.cliente || {};
                this.clienteDetalhes = {
                    id: idcliente,
                    nome: c.cliente || nome || ('Cliente #' + idcliente),
                    cnpj: c.cnpj || '',
                    cidade: c.cidade || '',
                    estado: c.estado || '',
                    regiao: c.regiao || '',
                    subregiao: c.subregiao || '',
                    representante: c.representante || '—',
                    ultima_compra: c.ultima_compra || null,
                    dias_sem_comprar: c.dias_sem_comprar ?? null,
                    modo: c.modo || 'periodo',
                    periodo_label: c.periodo_label || (c.modo === 'historico' ? 'Histórico Geral (Últimos 12 meses)' : 'Período Selecionado'),
                    valor: c.valor || 0,
                    total_notas: c.total_notas || 0,
                    ticket_medio: c.ticket_medio || 0,
                    quantidade: c.quantidade || 0,
                    peso: c.peso || 0,
                    produtos: data.top_produtos || []
                };
            } catch (e) {
                console.error('Erro cliente:', e);
                this.clienteErro = e.message;
            } finally {
                this.clienteLoading = false;
            }
        },

        async verDetalhesRepresentante(id, nome) {
            this.modalRepresentanteOpen = true;
            this.repLoading = true;
            this.repErro = '';
            this.representanteDetalhes = { nome, clientes: [], produtos: [], evolucao: { itens: [] }, total: null };
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-representante', this.corpoFiltros({ id, representante: '' }));
                this.representanteDetalhes = {
                    nome,
                    clientes: dados.clientes || [],
                    produtos: dados.produtos || [],
                    evolucao: dados.evolucao || { itens: [] },
                    total: dados.total || null
                };
            } catch (e) {
                console.error('Erro representante:', e);
                this.repErro = e.message;
            } finally {
                this.repLoading = false;
            }
        },

        async verDetalhesFunil(etapa) {
            this.modalFunilOpen = true;
            this.funilLoading = true;
            this.funilErro = '';
            this.funilDetalhes = { etapa, pedidos: [], total: null, truncado: false };
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-funil', this.corpoFiltros({ etapa }));
                this.funilDetalhes = { etapa, pedidos: dados.pedidos || [], total: dados.total || null, truncado: !!dados.truncado };
            } catch (e) {
                console.error('Erro funil:', e);
                this.funilErro = e.message;
            } finally {
                this.funilLoading = false;
            }
        },

        abrirDetalhesRegiao(regiaoObj) {
            if (!regiaoObj || !regiaoObj.regiao) return;
            this.abrirDetalhesCard(
                'Regiao',
                `Macro-Região ${regiaoObj.regiao} — ${regiaoObj.nome || ''}`,
                { regiao: regiaoObj.regiao, subregioes: regiaoObj.subregioes || '' }
            );
        },

        async abrirDetalhesCard(tipo, titulo, extraPayload = {}) {
            this.modalCardTitulo = titulo || tipo;
            const mapaTipos = {
                'Faturamento': 'faturamento',
                'Regiao': 'regiao',
                'Notas': 'notas',
                'Pedidos': 'notas',
                'Ticket Médio': 'ticket_medio',
                'Vendas por Dia': 'vendas_dia',
                'Carteira': 'carteira',
                'Cancelados': 'cancelados',
                'Devoluções': 'devolucoes',
                'Positivação': 'positivacao'
            };
            this.modalCardTipo = mapaTipos[tipo] || String(tipo).toLowerCase();
            this.modalCardOpen = true;
            this.cardLoading = true;
            this.cardErro = '';
            this.cardBusca = '';
            this.cardSubregioes = (extraPayload && extraPayload.subregioes) || '';
            this.cardAba = 'resumo';
            this.cardDetalhes = [];
            this.cardPorDia = [];
            this.cardRastreio = [];
            this.cardTotal = null;
            this.cardExtra = {};
            try {
                const payload = Object.assign({ tipo }, extraPayload || {});
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-card', this.corpoFiltros(payload));
                this.cardDetalhes = dados.dados || [];
                this.cardPorDia = dados.por_dia || [];
                this.cardRastreio = dados.rastreio || [];
                this.cardAba = dados.aba_padrao || 'resumo';
                this.cardTotal = dados.total || null;
                this.cardRotulo = dados.rotulo_documentos || 'Docs';
                if (dados.extra && dados.extra.subregioes) {
                    this.cardSubregioes = dados.extra.subregioes;
                }
                this.cardExtra = { por_dia_semana: dados.por_dia_semana || [], positivacao: dados.positivacao || null };
            } catch (e) {
                console.error('Erro card:', e);
                this.cardErro = e.message;
            } finally {
                this.cardLoading = false;
            }
        },

        cardListaResumoFiltrada() {
            const q = (this.cardBusca || '').trim().toLowerCase();
            if (!q) return this.cardDetalhes || [];
            return (this.cardDetalhes || []).filter(item => {
                const texto = `${item.label || ''} ${item.sublabel || ''}`.toLowerCase();
                return texto.includes(q);
            });
        },

        cardListaPorDiaFiltrada() {
            const q = (this.cardBusca || '').trim().toLowerCase();
            if (!q) return this.cardPorDia || [];
            return (this.cardPorDia || []).filter(item => {
                const texto = `${item.label || ''} ${item.sublabel || ''}`.toLowerCase();
                return texto.includes(q);
            });
        },

        cardListaRastreioFiltrada() {
            const q = (this.cardBusca || '').trim().toLowerCase();
            if (!q) return this.cardRastreio || [];
            return (this.cardRastreio || []).filter(item => {
                const texto = `${item.numero || ''} ${item.documento || ''} ${item.pedido || ''} ${item.data || ''} ${item.situacao || ''} ${item.cliente || ''} ${item.representante || ''} ${item.filial || ''}`.toLowerCase();
                return texto.includes(q);
            });
        },

        // ======================================================================
        // INTELIGÊNCIA DE RECOMPRA & CLIENTES SEM COMPRAS (+30 / +60 / +90 DIAS)
        // ======================================================================
        representantesRisco() {
            const mapa = new Map();
            for (const c of (this.clientesRisco || [])) {
                const id = String(c.idrepresentante || 0);
                const nome = c.representante || ('REP ' + id);
                if (!mapa.has(id)) {
                    mapa.set(id, { id, nome, qtd: 0, valor: 0, peso: 0 });
                }
                const item = mapa.get(id);
                item.qtd += 1;
                item.valor += parseFloat(c.valor_historico || 0);
                item.peso += parseFloat(c.peso_historico || 0);
            }
            return Array.from(mapa.values()).sort((a, b) => a.nome.localeCompare(b.nome, 'pt-BR'));
        },

        clientesRiscoBase() {
            const repFiltro = String(this.filtroRiscoRep || '').trim();
            const regFiltro = String(this.filtroRiscoRegiao || '').trim().toUpperCase();
            const q = (this.buscaRiscoCliente || '').trim().toLowerCase();

            return (this.clientesRisco || []).filter(c => {
                if (repFiltro) {
                    const matchId = String(c.idrepresentante || 0) === repFiltro;
                    const matchNome = String(c.representante || '').trim() === repFiltro;
                    if (!matchId && !matchNome) return false;
                }
                if (regFiltro && String(c.regiao || '').toUpperCase() !== regFiltro) {
                    return false;
                }
                if (!q) return true;
                const texto = `${c.id || ''} ${c.nome || ''} ${c.representante || ''} ${c.cidade_uf || ''} ${c.regiao || ''} ${c.ultima_fmt || ''}`.toLowerCase();
                return texto.includes(q);
            });
        },

        clientesRiscoFiltrados() {
            const base = this.clientesRiscoBase();
            const faixaFiltro = String(this.filtroRiscoFaixa || '').trim();
            if (!faixaFiltro) return base;
            return base.filter(c => c.faixa === faixaFiltro);
        },

        dadosFaixaRisco(faixaKey) {
            const base = this.clientesRiscoBase();
            let qtd = 0, valor = 0, peso = 0, nfs = 0;
            for (const c of base) {
                if (c.faixa === faixaKey) {
                    qtd++;
                    valor += parseFloat(c.valor_historico || 0);
                    peso += parseFloat(c.peso_historico || 0);
                    nfs += parseInt(c.compras_anteriores || 0, 10);
                }
            }
            const totalGeral = this.resumoRisco.faixas?.[faixaKey]?.clientes || 0;
            const ticketMedio = qtd > 0 ? (valor / qtd) : 0;
            return {
                clientes: qtd,
                totalGeral,
                valor,
                peso,
                nfs,
                ticketMedio
            };
        },

        tooltipFaixaRisco(faixaKey) {
            const d = this.dadosFaixaRisco(faixaKey);
            const titulos = {
                '30_45': '⚠️ ALERTA INICIAL (30 a 45 dias sem compras)',
                '46_60': '🔶 ATENÇÃO (46 a 60 dias sem compras)',
                '61_90': '🚨 RISCO ELEVADO (61 a 90 dias sem compras)',
                '90_mais': '🛑 INATIVOS CRÍTICOS (+90 dias sem compras)'
            };
            const acoes = {
                '30_45': 'Ação Preventiva: Contato de rotina para reposição de estoque antes de esfriar a conta.',
                '46_60': 'Ação Comercial: Oferta direcionada e verificação de giro/estoque do cliente.',
                '61_90': 'Ação Crítica: Risco iminente de perda. Visita presencial do representante.',
                '90_mais': 'Recuperação de Carteira: Diagnóstico de motivo de parada e condição especial de reativação.'
            };

            const linhas = [
                titulos[faixaKey] || 'Faixa de Inatividade',
                '',
                `• Clientes no Filtro: ${d.clientes} cli${d.totalGeral !== d.clientes ? ` (de ${d.totalGeral} totais no escopo)` : ''}`,
                `• Faturamento Histórico (12m): ${this.formatMoney(d.valor)}`,
                `• Volume Físico Histórico: ${this.formatPeso(d.peso)} (${d.nfs} NFs emitidas)`,
                `• Ticket Médio por Cliente: ${this.formatMoney(d.ticketMedio)}`,
                '',
                acoes[faixaKey] || '',
                '',
                this.filtroRiscoFaixa === faixaKey ? '👉 [Filtro Ativo] Clique para remover o filtro e ver todas as faixas.' : '👉 Clique para filtrar a tabela apenas por esta faixa.'
            ];
            return linhas.join('\n');
        },

        resumoRiscoFiltrado() {
            const lista = this.clientesRiscoFiltrados();
            let valor = 0, peso = 0, qtd = 0, nfs = 0;
            for (const c of lista) {
                valor += parseFloat(c.valor_historico || 0);
                peso += parseFloat(c.peso_historico || 0);
                qtd += parseFloat(c.quantidade_historico || 0);
                nfs += parseInt(c.compras_anteriores || 0, 10);
            }
            return {
                clientes: lista.length,
                valor,
                peso,
                quantidade: qtd,
                nfs
            };
        },

        alternarFaixaRisco(faixa) {
            this.filtroRiscoFaixa = (this.filtroRiscoFaixa === faixa) ? '' : faixa;
        },

        // ======================================================================
        // PROJEÇÃO DE FECHAMENTO EXPANDIDA (MODAL & FILTROS)
        // ======================================================================
        abrirModalProjecao(aba = 'representantes') {
            this.abaProjecao = aba;
            this.buscaProjecao = '';
            this.modalProjecaoOpen = true;
        },

        projecaoRepsFiltrados() {
            const reps = this.projecaoFechamento?.por_representante || [];
            const q = (this.buscaProjecao || '').trim().toLowerCase();
            if (!q) return reps;
            return reps.filter(r => String(r.representante || '').toLowerCase().includes(q));
        },

        // ======================================================================
        // ATALHOS DE PERÍODO E FORMATADORES
        // ======================================================================
        definirPeriodoRapido(tipo) {
            const hoje = new Date();
            const ano = hoje.getFullYear();
            const mes = hoje.getMonth();
            let ini, fim;

            switch (tipo) {
                case 'hoje':
                    ini = new Date(ano, mes, hoje.getDate());
                    fim = new Date(ano, mes, hoje.getDate());
                    break;
                case 'mes_atual':
                    ini = new Date(ano, mes, 1);
                    fim = new Date(ano, mes + 1, 0);
                    break;
                case 'mes_passado':
                    ini = new Date(ano, mes - 1, 1);
                    fim = new Date(ano, mes, 0);
                    break;
                case 'ultimos_30':
                    fim = new Date(ano, mes, hoje.getDate());
                    ini = new Date(ano, mes, hoje.getDate() - 29);
                    break;
                case 'ultimos_90':
                    fim = new Date(ano, mes, hoje.getDate());
                    ini = new Date(ano, mes, hoje.getDate() - 89);
                    break;
                case 'ano_atual':
                    ini = new Date(ano, 0, 1);
                    fim = new Date(ano, mes, hoje.getDate());
                    break;
                default:
                    return;
            }

            this.periodoRapidoAtivo = tipo;
            this.filters.data_inicio = this.toISO(ini);
            this.filters.data_fim = this.toISO(fim);
            this.carregarDados();
        },

        formatarProjecao(projecao) {
            return (projecao || [])
                .filter(item => item?.mes)
                .map(item => {
                    const [ano, mes] = String(item.mes).split('-');
                    return {
                        mes_label: `${MESES[parseInt(mes, 10) - 1]}/${ano}`,
                        valor: parseFloat(item.valor || 0),
                        valor_projetado: parseFloat(item.valor_projetado || item.valor || 0),
                        carteira_aberta: parseFloat(item.carteira_aberta || 0),
                        projecao_com_carteira: parseFloat(item.projecao_com_carteira || item.valor_projetado || item.valor || 0),
                        peso: parseFloat(item.peso || 0),
                        peso_projetado: parseFloat(item.peso_projetado || item.peso || 0),
                        quantidade: parseFloat(item.quantidade || 0),
                        quantidade_projetada: parseFloat(item.quantidade_projetada || item.quantidade || 0),
                        notas: parseInt(item.notas || 0, 10),
                        clientes: parseInt(item.clientes || 0, 10),
                        dias_uteis: parseInt(item.dias_uteis || 0, 10),
                        media_diaria: parseFloat(item.media_diaria || 0),
                        tipo: item.tipo || 'projecao',
                        realizado: item.tipo === 'realizado',
                        atual: item.tipo === 'atual',
                        tendencia: item.tipo === 'projecao'
                    };
                });
        },

        variacaoTexto(v) {
            return v === null || v === undefined ? '—' : Math.abs(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + '%';
        },
        variacaoPositiva(v) {
            return v === null || v === undefined || v >= 0;
        },
        variacaoRotulo() {
            const p = this.periodoAnterior;
            if (!p) return 'vs período anterior';
            const f = (s) => this.formatarDataBR(s);
            return `vs ${f(p.inicio)} a ${f(p.fim)}${p.parcial ? ' (mesmo nº dias)' : ''}`;
        },
        formatarDataBR(iso) {
            const d = this.parseISO(iso);
            return d ? d.toLocaleDateString('pt-BR') : '';
        },

        parseISO(s) {
            const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
            if (!m) return null;
            return new Date(parseInt(m[1], 10), parseInt(m[2], 10) - 1, parseInt(m[3], 10));
        },
        toISO(d) {
            const p = (n) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())}`;
        },
        getPrimeiroDiaMes() {
            const d = new Date();
            return this.toISO(new Date(d.getFullYear(), d.getMonth(), 1));
        },
        getUltimoDiaMes() {
            const d = new Date();
            return this.toISO(new Date(d.getFullYear(), d.getMonth() + 1, 0));
        },

        formatMoney(v) {
            return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0);
        },
        formatNumber(v) {
            return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(v || 0);
        },
        formatPeso(kg) {
            const val = parseFloat(kg || 0);
            return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 1 }).format(val) + ' kg';
        },
        formatPesoTon(kg) {
            const val = parseFloat(kg || 0);
            if (Math.abs(val) >= 1000) {
                return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(val / 1000) + ' ton';
            }
            return this.formatPeso(val);
        },

        larguraFunil(item) {
            const max = Math.max(...this.funilPedidos.map(f => f.valor), 0);
            return max > 0 ? Math.min((item.valor / max) * 100, 100) : 0;
        },
        corFunil(item) {
            return { 1: 'bg-blue-500', 4: 'bg-amber-500', 5: 'bg-emerald-500', 3: 'bg-rose-500' }[item.status] || 'bg-slate-400';
        },

        // ======================================================================
        // EXPORTAÇÕES PARA EXCEL (.CSV UTF-8 BOM)
        // ======================================================================
        _baixarCsv(nomeArquivo, linhas) {
            const blob = new Blob(['\uFEFF' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = nomeArquivo;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        },

        _escCsv(v) {
            if (v === null || v === undefined) return '';
            if (typeof v === 'number') return String(v).replace('.', ',');
            const s = String(v);
            return /[";\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        },

        /** Exporta o Fechamento e Projeção Completa (Global + Por Representante + Macro-Regiões + Histórico) */
        exportarProjecaoExcel() {
            const pf = this.projecaoFechamento || {};
            if (!pf.carregado) {
                this.showToast('Projeção ainda não carregada.', 'info');
                return;
            }
            const linhas = [];
            const add = (...c) => linhas.push(c.map(v => this._escCsv(v)).join(';'));

            add('RELATÓRIO EXECUTIVO DE PROJEÇÃO DE FECHAMENTO DE VENDAS — NUTRICIONAL');
            add('Mês Referência', pf.mes_referencia || 'Atual', 'Dias Úteis Decorridos', pf.dias_uteis_decorridos, 'Dias Úteis Restantes', pf.dias_uteis_restantes, 'Dias Úteis Totais', pf.dias_uteis_totais);
            add();

            add('1. RESUMO EXECUTIVO DE PROJEÇÃO DO MÊS');
            add('Indicador', 'Valor (R$)', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'NFs / Pedidos');
            add('Realizado Faturado até Hoje', pf.realizado || 0, pf.peso_realizado || 0, pf.quantidade_realizada || 0, pf.notas_realizadas || 0);
            add('Média por Dia Útil', pf.media_dia_util || 0, pf.media_peso_dia_util || 0, pf.media_qtd_dia_util || 0, '');
            add('Projeção Adicional (Dias Restantes)', pf.projecao_adicional || 0, Math.max((pf.peso_projetado || 0) - (pf.peso_realizado || 0), 0), Math.max((pf.quantidade_projetada || 0) - (pf.quantidade_realizada || 0), 0), '');
            add('Projeção de Fechamento (Ritmo Linear)', pf.projecao || 0, pf.peso_projetado || 0, pf.quantidade_projetada || 0, pf.notas_projetadas || 0);
            add('Carteira de Pedidos em Aberto no Mês', pf.carteira_valor || 0, pf.carteira_peso || 0, pf.carteira_quantidade || 0, pf.carteira_pedidos || 0);
            add('Potencial Máximo (Projeção + Carteira)', pf.projecao_com_carteira || 0, (pf.peso_projetado || 0) + (pf.carteira_peso || 0), (pf.quantidade_projetada || 0) + (pf.carteira_quantidade || 0), '');
            add('Mês Anterior Fechado (Comparativo)', pf.mes_anterior_valor || 0, pf.mes_anterior_peso || 0, '', pf.mes_anterior_notas || 0);
            add('Variação Projeção vs Mês Anterior (%)', pf.variacao_vs_mes_anterior ?? '');
            add();

            add('2. DESDOBRAMENTO DE PROJEÇÃO POR REPRESENTANTE');
            add('Código Rep', 'Representante', 'Realizado (R$)', 'Peso Realizado (Kg)', 'Qtd Realizada (Un)', 'NFs', 'Clientes Positivados', 'Média/Dia Útil (R$)', 'Média Peso/Dia (Kg)', 'Carteira Aberta (R$)', 'Carteira Peso (Kg)', 'Projeção Fechamento (R$)', 'Peso Projetado (Kg)', 'Qtd Projetada (Un)', 'Potencial c/ Carteira (R$)', 'Mês Anterior (R$)', 'Peso Mês Anterior (Kg)', 'Var. Projeção vs Mês Ant. (%)');
            for (const r of this.projecaoRepsFiltrados()) {
                add(
                    r.id,
                    r.representante,
                    r.realizado,
                    r.peso_realizado,
                    r.qtd_realizada,
                    r.notas,
                    r.clientes,
                    r.media_dia_util,
                    r.media_peso_dia,
                    r.carteira_valor,
                    r.carteira_peso,
                    r.projecao,
                    r.peso_projetado,
                    r.qtd_projetada,
                    r.projecao_com_carteira,
                    r.mes_anterior,
                    r.peso_anterior,
                    r.variacao_projecao ?? ''
                );
            }
            add();

            add('3. DESDOBRAMENTO DE PROJEÇÃO POR MACRO-REGIÃO');
            add('Sigla Região', 'Nome da Região', 'Realizado (R$)', 'Peso Realizado (Kg)', 'Quantidade (Un)', 'NFs', 'Clientes', 'Média/Dia Útil (R$)', 'Projeção Fechamento (R$)', 'Peso Projetado (Kg)', '% Participação');
            for (const rg of (pf.por_regiao || [])) {
                add(
                    rg.regiao,
                    rg.nome,
                    rg.realizado,
                    rg.peso_realizado,
                    rg.quantidade_realizada,
                    rg.notas,
                    rg.clientes,
                    rg.media_dia_util,
                    rg.projecao,
                    rg.peso_projetado,
                    rg.participacao
                );
            }
            add();

            add('4. HISTÓRICO MENSAL E TENDÊNCIA (M-3 A M+1)');
            add('Mês/Ano', 'Status', 'Realizado (R$)', 'Projetado (R$)', 'Peso (Kg)', 'Peso Projetado (Kg)', 'Quantidade (Un)', 'NFs', 'Dias Úteis', 'Média Diária (R$)');
            for (const m of (this.projecao || [])) {
                add(
                    m.mes_label,
                    m.atual ? 'Mês Atual' : (m.realizado ? 'Realizado Fechado' : 'Projeção M+1'),
                    m.valor,
                    m.valor_projetado,
                    m.peso,
                    m.peso_projetado,
                    m.quantidade,
                    m.notas,
                    m.dias_uteis,
                    m.media_diaria
                );
            }

            this._baixarCsv(`projecao-fechamento-vendas_${pf.mes_referencia || 'atual'}.csv`, linhas);
        },

        /** Exporta a Base de Clientes Sem Compras (+30d / +60d / +90d) filtrada */
        exportarClientesRiscoExcel() {
            const lista = this.clientesRiscoFiltrados();
            if (!lista.length) {
                this.showToast('Nenhum cliente sem compras para exportar no filtro atual.', 'info');
                return;
            }
            const linhas = [];
            const add = (...c) => linhas.push(c.map(v => this._escCsv(v)).join(';'));

            add('INTELIGÊNCIA DE RECOMPRA — CLIENTES ATIVOS SEM COMPRAS (+30 DIAS)');
            add('Filtro Representante', this.filtroRiscoRep || 'Todos', 'Filtro Região', this.filtroRiscoRegiao || 'Todas as Regiões', 'Filtro Faixa', this.filtroRiscoFaixa || 'Todas');
            add('Total Clientes Exportados', lista.length);
            add();
            add('Código Cliente', 'Cliente', 'Representante', 'Região Unificada', 'Cidade/UF', 'Última Compra', 'Dias Sem Comprar', 'Faixa Inatividade', 'NFs Históricas (12m)', 'Peso Histórico (Kg)', 'Quantidade Histórica (Un)', 'Faturamento Histórico (R$)', 'Ticket Médio Histórico (R$)');

            for (const c of lista) {
                add(
                    c.id,
                    c.nome,
                    c.representante,
                    c.regiao || 'SUL',
                    c.cidade_uf || '',
                    c.ultima_fmt || '',
                    c.dias_sem_comprar,
                    c.faixa || '',
                    c.compras_anteriores || 0,
                    c.peso_historico || 0,
                    c.quantidade_historico || 0,
                    c.valor_historico || 0,
                    c.ticket_medio || 0
                );
            }

            this._baixarCsv(`clientes-sem-compras_${this.filters.data_inicio}_${this.filters.data_fim}.csv`, linhas);
        },

        /** Exporta os dados do Modal de Detalhes / Rastreio de Card aberto */
        exportarCardExcel() {
            const linhas = [];
            const add = (...c) => linhas.push(c.map(v => this._escCsv(v)).join(';'));

            add(`AUDITORIA E RASTREIO — ${this.modalCardTitulo}`);
            add('Período', this.filters.data_inicio, 'até', this.filters.data_fim, 'Aba Exportada', this.cardAba);
            add();

            if (this.cardAba === 'rastreio') {
                const lista = this.cardListaRastreioFiltrada();
                if (this.modalCardTipo === 'positivacao') {
                    add('Código Cliente', 'Cliente Positivado', 'Representante', 'NFs no Período', 'Peso (Kg)', 'Quantidade (Un)', 'Última Compra', 'Valor Comprado (R$)');
                    lista.forEach(r => add(r.idcliente || r.id_doc, r.cliente, r.representante, r.documentos || r.notas || 0, r.peso || 0, r.quantidade || 0, r.data, r.valor || 0));
                } else {
                    add('Documento', 'Data', 'Pedido / Situação', 'Código Cliente', 'Cliente', 'Representante', 'Filial', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'Comissão (R$)', 'Valor (R$)');
                    lista.forEach(r => add(r.numero || r.documento || ('#' + r.id_doc), r.data, r.pedido || r.situacao || '', r.idcliente || '', r.cliente, r.representante, r.filial || '', r.peso || 0, r.quantidade || 0, r.comissao || 0, r.valor || 0));
                }
            } else if (this.cardAba === 'por_dia') {
                const lista = this.cardListaPorDiaFiltrada();
                add('Data', 'Dia da Semana', this.cardRotulo || 'Documentos', 'Clientes', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'Ticket Médio (R$)', 'Valor (R$)');
                lista.forEach(d => add(d.label || d.periodo, d.sublabel || '', d.documentos || 0, d.clientes || 0, d.peso || 0, d.quantidade || 0, d.ticket_medio || 0, d.valor || 0));
            } else {
                const lista = this.cardListaResumoFiltrada();
                if (this.modalCardTipo === 'positivacao') {
                    add('Representante', 'Valor Faturado (R$)', 'Peso (Kg)', 'Clientes Positivados', 'QT Carteira', '% Positivação');
                    lista.forEach(i => add(i.label, i.valor || 0, i.peso || 0, i.positivados || 0, i.qt_carteira || 0, i.percentual || 0));
                } else {
                    add('Agrupador / Representante', this.cardRotulo || 'Documentos', 'Clientes', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'Ticket Médio (R$)', 'Comissão (R$)', 'Valor (R$)', '% Participação');
                    lista.forEach(i => add(i.label || i.periodo, i.documentos || 0, i.clientes || 0, i.peso || 0, i.quantidade || 0, i.ticket_medio || 0, i.comissao || 0, i.valor || 0, i.percentual ?? i.participacao ?? 0));
                }
            }

            const slug = String(this.modalCardTipo || 'card').replace(/[^a-z0-9_-]/gi, '_');
            this._baixarCsv(`rastreio-${slug}_${this.filters.data_inicio}_${this.filters.data_fim}.csv`, linhas);
        },

        /** Exporta o Dashboard Completo (KPIs, Evolução, Top 10, Macro-Regiões, Projeção e Clientes Sem Compras) */
        exportarDados() {
            if (this.loading) {
                this.showToast('Aguarde o carregamento dos dados.', 'info');
                return;
            }
            const linhas = [];
            const add = (...c) => linhas.push(c.map(v => this._escCsv(v)).join(';'));
            const k = this.kpis;

            add('DASHBOARD EXECUTIVO DE VENDAS v3.0 — NUTRICIONAL');
            add('Período', this.filters.data_inicio, 'a', this.filters.data_fim);
            add('Filial', this.filters.filial || 'Todas', 'Gestor', this.filters.gestor || 'Todos', 'Representante', this.filters.representante || 'Todos');
            add();

            add('INDICADOR', 'VALOR (R$)', 'PESO LÍQUIDO (KG)', 'QUANTIDADE (UN)', 'DOCUMENTOS / INFO');
            add('Faturamento (NFs faturadas)', k.faturamento, k.peso_faturado, k.quantidade_faturada, k.notas + ' NFs');
            add('Ticket Médio por NF', k.ticket_medio, k.media_peso_nf, k.media_itens_nf, 'Preço/Kg: R$ ' + k.preco_medio_kg);
            add('Média por Dia Útil (' + k.dias_uteis + 'd)', k.media_diaria, k.media_peso_diario, k.media_qtd_diaria, k.media_notas_diaria + ' NFs/dia');
            add('Carteira de Pedidos em Aberto', k.carteira.valor, k.carteira.peso, k.carteira.quantidade, k.carteira.pedidos + ' pedidos');
            add('NFs Canceladas', k.cancelados.valor, k.cancelados.peso, '', k.cancelados.notas + ' NFs');
            add('Devoluções de Venda', k.devolucoes.valor, k.devolucoes.peso, k.devolucoes.quantidade, k.devolucoes.notas + ' NFs');
            add('Positivação da Carteira (%)', k.positivacao.percentual + '%', '', '', `${k.positivacao.positivados} de ${k.positivacao.base} clientes`);
            add();

            add('DISTRIBUIÇÃO POR MACRO-REGIÕES DO ESCOPO', 'Nome', 'Faturamento (R$)', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'NFs', 'Clientes', '% Participação');
            this.distribuicaoRegiao.forEach(r => add(r.regiao, r.nome, r.valor, r.peso, r.quantidade, r.notas, r.clientes, r.participacao));
            add();

            add('EVOLUÇÃO DE VENDAS', 'Faturamento (R$)', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'NFs', 'Ticket Médio (R$)');
            this.itensEvolucaoAtiva().forEach(i => add(i.label, i.valor, i.peso, i.quantidade, i.notas, i.ticket_medio));
            add();

            add('TOP 10 PRODUTOS', 'Grupo', 'Faturamento (R$)', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'NFs', 'Clientes');
            this.topProdutos.forEach(i => add(i.produto, i.grupo, i.valor, i.peso, i.quantidade, i.notas, i.clientes));
            add();

            add('TOP 10 CLIENTES', 'Região', 'Cidade', 'Faturamento (R$)', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'NFs', 'Ticket Médio (R$)');
            this.topClientes.forEach(i => add(i.cliente, i.regiao, i.cidade, i.valor, i.peso, i.quantidade, i.total_notas, i.ticket_medio));
            add();

            add('TOP 10 REPRESENTANTES', 'Faturamento (R$)', 'Peso Líquido (Kg)', 'Quantidade (Un)', 'NFs', 'Clientes Positivados', 'Ticket Médio (R$)', 'Comissão (R$)');
            this.rankingRepresentantes.forEach(i => add(i.nome, i.valor, i.peso, i.quantidade, i.total_notas, i.clientes_ativos, i.ticket_medio, i.comissao));
            add();

            add('CLIENTES +30 DIAS SEM COMPRAS (TOP 100)', 'Representante', 'Região', 'Cidade/UF', 'Última Compra', 'Dias Sem Comprar', 'NFs Hist.', 'Peso Hist. (Kg)', 'Qtd Hist.', 'Faturamento Hist. (R$)');
            this.clientesRisco.slice(0, 100).forEach(c => add(c.nome, c.representante, c.regiao, c.cidade_uf, c.ultima_fmt, c.dias_sem_comprar, c.compras_anteriores, c.peso_historico, c.quantidade_historico, c.valor_historico));

            this._baixarCsv(`dashboard-vendas-executivo_${this.filters.data_inicio}_${this.filters.data_fim}.csv`, linhas);
        },

        showToast(msg, type = 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: type, title: msg, showConfirmButton: false, timer: 3000 });
            }
        }
    };
}


/**
 * Sistema Universal de Tooltip Moderno Flutuante do Dashboard de Vendas
 */
function initModernTooltipsDash() {
    const tooltipEl = document.getElementById('modernTooltipDash');
    const badgeEl = document.getElementById('tooltipDashBadge');
    const titleEl = document.getElementById('tooltipDashTitle');
    const descEl = document.getElementById('tooltipDashDesc');

    if (!tooltipEl || !titleEl || !descEl) return;
    if (window.__tooltipsDashInitialized) return;
    window.__tooltipsDashInitialized = true;

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
        let badge = 'ANÁLISE';

        if (rawContent.includes('|')) {
            const partes = rawContent.split('|');
            titulo = partes[0].trim();
            descricao = partes.slice(1).join('|').trim();
        } else {
            titulo = el.innerText ? el.innerText.trim().slice(0, 30) : 'Indicador';
        }

        const descLower = (titulo + ' ' + descricao).toLowerCase();
        if (descLower.includes('devolu') || descLower.includes('cancel')) {
            badge = 'AUDITORIA';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-rose-400 bg-rose-950/90 px-2 py-0.5 rounded-md border border-rose-700/50';
        } else if (descLower.includes('comiss') || descLower.includes('ticket')) {
            badge = 'RENTABILIDADE';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-amber-400 bg-amber-950/90 px-2 py-0.5 rounded-md border border-amber-700/50';
        } else if (descLower.includes('proj') || descLower.includes('ritmo') || descLower.includes('meta')) {
            badge = 'PROJEÇÃO';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-purple-400 bg-purple-950/90 px-2 py-0.5 rounded-md border border-purple-700/50';
        } else if (descLower.includes('fatur') || descLower.includes('venda') || descLower.includes('positiv')) {
            badge = 'COMERCIAL';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50';
        } else if (descLower.includes('carteira') || descLower.includes('pedido')) {
            badge = 'CARTEIRA';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-sky-400 bg-sky-950/90 px-2 py-0.5 rounded-md border border-sky-700/50';
        } else if (descLower.includes('regi') || descLower.includes('macro') || descLower.includes('escopo')) {
            badge = 'ESCOPO';
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

window.addEventListener('DOMContentLoaded', () => {
    initModernTooltipsDash();
});
