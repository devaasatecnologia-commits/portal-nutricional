// ==========================================================================
// DASHBOARD DE VENDAS
// Hierarquia: Filial → Gestor → Representante (escopo validado no backend)
// Faturamento = NFs faturadas | Carteira = pedidos abertos/parciais (separados)
// ==========================================================================

function dashboardVendasHandler() {
    const MAX_DIAS_PESADO = 90;
    const MESES = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    return {
        // Estado
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

        // Modais
        modalCardOpen: false,
        modalCardTitulo: '',
        modalCardTipo: '',
        cardDetalhes: [],
        cardTotal: null,
        cardRotulo: 'Docs',
        cardExtra: {},
        cardLoading: false,
        cardErro: '',
        modalRepresentanteOpen: false,
        modalFunilOpen: false,
        representanteDetalhes: { nome: '', clientes: [], produtos: [], evolucao: { itens: [] }, total: null },
        repLoading: false,
        repErro: '',
        funilDetalhes: { etapa: '', pedidos: [], total: null, truncado: false },
        funilLoading: false,
        funilErro: '',

        // Filtros
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

        // KPIs
        kpis: {
            faturamento: 0, variacao_faturamento: null,
            notas: 0, variacao_notas: null,
            ticket_medio: 0, variacao_ticket: null,
            media_diaria: 0, media_dia_com_venda: 0, dias_uteis: 0, dias_com_venda: 0,
            carteira: { valor: 0, pedidos: 0, quantidade: 0 },
            cancelados: { valor: 0, notas: 0 },
            devolucoes: { valor: 0, notas: 0 },
            positivacao: { positivados: 0, base: 0, percentual: 0 }
        },
        periodoAnterior: null,
        porDiaSemana: [],

        // Dados
        topProdutos: [],
        topClientes: [],
        distribuicaoRegiao: [],
        margemProdutos: [],
        evolucao: { granularidade: 'mes', itens: [] },
        matrizCrossSelling: [],
        projecao: [],
        rankingRepresentantes: [],
        funilPedidos: [],
        funilTaxas: { conversao: null, cancelamento: null },
        projecaoFechamento: {},
        clientesRisco: [],
        riscoCriterio: { dias_sem_comprar: 30, janela_dias: 180 },

        // Gráficos
        tipoGrafico: 'valor',
        chartEvolucao: null,
        chartRegiao: null,
        chartProdutoEvolucao: null,

        // Modais de detalhes
        modalProdutoOpen: false,
        modalClienteOpen: false,
        produtoDetalhes: { nome: '', valor: 0, quantidade: 0, preco_medio: 0, total_clientes: 0, clientes: [], evolucao: [] },
        produtoLoading: false,
        produtoErro: '',
        clienteDetalhes: { nome: '', valor: 0, total_notas: 0, ticket_medio: 0, quantidade: 0, produtos: [] },
        clienteLoading: false,
        clienteErro: '',

        maxProjecao: 1,

        // ======================================================================
        // INICIALIZAÇÃO
        // ======================================================================
        async init() {
            if (this._inicializado) return;
            this._inicializado = true;

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
        // API
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
            try { json = JSON.parse(text); } catch (e) { /* resposta não-JSON tratada abaixo */ }

            if (!resp.ok || (json && json.success === false)) {
                throw new Error((json && json.error) || `Erro ${resp.status} ao consultar o servidor.`);
            }
            if (!json) throw new Error('Resposta inválida do servidor.');
            return json;
        },

        // Controle de corrida: cancela a requisição anterior do mesmo grupo e descarta respostas antigas
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
        // HIERARQUIA
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
                // idfilial 0 = todas as filiais (o backend do cubo resolve)
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
                this.avisoPeriodo = `Período acima de ${MAX_DIAS_PESADO} dias: "Margem por produto" e "Produtos comprados juntos" não serão calculados. Reduza o período para vê-los.`;
            }
            return true;
        },

        // ======================================================================
        // CARREGAMENTO
        // ======================================================================
        async carregarDados() {
            this.erro = '';
            if (!this.validarPeriodo()) {
                this.zerarDados();
                return;
            }

            const { id, signal } = this.iniciar('kpis');
            this.loading = true;
            // Pesados e insights são independentes e disparam uma única vez por carga
            this.carregarDadosPesados();
            this.carregarInsights();

            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/kpis', this.corpoFiltros(), signal);
                if (!this.atual('kpis', id)) return;

                this.kpis = {
                    faturamento: data.faturamento?.valor || 0,
                    variacao_faturamento: data.faturamento?.variacao ?? null,
                    notas: data.notas?.atual || 0,
                    variacao_notas: data.notas?.variacao ?? null,
                    ticket_medio: data.ticket_medio?.atual || 0,
                    variacao_ticket: data.ticket_medio?.variacao ?? null,
                    media_diaria: data.velocidade_venda?.media_diaria || 0,
                    media_dia_com_venda: data.velocidade_venda?.media_dia_com_venda || 0,
                    dias_uteis: data.velocidade_venda?.dias_uteis || 0,
                    dias_com_venda: data.velocidade_venda?.dias_com_venda || 0,
                    carteira: data.carteira || { valor: 0, pedidos: 0, quantidade: 0 },
                    cancelados: data.cancelados || { valor: 0, notas: 0 },
                    devolucoes: data.devolucoes || { valor: 0, notas: 0 },
                    positivacao: data.positivacao || { positivados: 0, base: 0, percentual: 0 }
                };
                this.periodoAnterior = data.periodo_anterior || null;
                this.porDiaSemana = data.velocidade_venda?.por_dia_semana || [];
                this.topProdutos = data.top_produtos || [];
                this.topClientes = data.top_clientes || [];
                this.distribuicaoRegiao = data.distribuicao_regiao || [];
                this.evolucao = data.evolucao || { granularidade: 'mes', itens: [] };

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
            this.evolucao = { granularidade: 'mes', itens: [] };
            this.margemProdutos = [];
            this.matrizCrossSelling = [];
            this.projecao = [];
            this.rankingRepresentantes = [];
            this.funilPedidos = [];
            this.projecaoFechamento = {};
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
                this.margemProdutos = dados.margem_produtos || [];
                this.matrizCrossSelling = dados.matriz_cross_selling || [];
                this.projecao = this.formatarProjecao(dados.projecao_vendas || []);
                this.maxProjecao = this.projecao.length > 0 ? Math.max(...this.projecao.map(p => p.valor), 1) : 1;
                this.mensagemPesados = dados.mensagem || '';
            } catch (e) {
                if (e.name === 'AbortError' || !this.atual('pesados', id)) return;
                console.error('Erro dados pesados:', e);
                this.erroPesados = e.message;
                this.margemProdutos = [];
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
                    cancelamento: dados.funil_pedidos?.taxa_cancelamento ?? null
                };
                this.projecaoFechamento = dados.projecao_fechamento || {};
                this.clientesRisco = dados.clientes_risco || [];
                this.riscoCriterio = dados.risco_criterio || this.riscoCriterio;
            } catch (e) {
                if (e.name === 'AbortError' || !this.atual('insights', id)) return;
                console.error('Erro insights:', e);
                this.erroInsights = e.message;
                this.rankingRepresentantes = [];
                this.funilPedidos = [];
                this.projecaoFechamento = {};
                this.clientesRisco = [];
            } finally {
                if (this.atual('insights', id)) this.loadingInsights = false;
            }
        },

        // ======================================================================
        // GRÁFICOS
        // ======================================================================
        destruirGrafico(nome) {
            if (this[nome]) {
                try { this[nome].destroy(); } catch (e) { /* já destruído */ }
                this[nome] = null;
            }
        },

        renderizarGraficoEvolucao() {
            this.destruirGrafico('chartEvolucao');
            const el = document.querySelector('#chartEvolucao');
            const itens = this.evolucao?.itens || [];
            if (!el) return;
            el.innerHTML = '';
            if (!itens.length) return;

            const quantidade = this.tipoGrafico === 'quantidade';
            const labels = itens.map(m => m.label);
            const dados = itens.map(m => parseFloat(quantidade ? m.quantidade : m.valor) || 0);
            const fmt = (v) => quantidade
                ? v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' un.'
                : 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            this.chartEvolucao = new ApexCharts(el, {
                chart: { type: 'area', height: 320, toolbar: { show: false } },
                series: [{ name: quantidade ? 'Quantidade faturada' : 'Faturamento (R$)', data: dados }],
                xaxis: { categories: labels, tickAmount: Math.min(labels.length, 12) },
                yaxis: { labels: { formatter: (v) => quantidade ? v.toLocaleString('pt-BR', { maximumFractionDigits: 0 }) : 'R$ ' + (v / 1000).toLocaleString('pt-BR', { maximumFractionDigits: 0 }) + ' mil' } },
                colors: [quantidade ? '#3b82f6' : '#059669'],
                dataLabels: { enabled: false },
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
                stroke: { curve: 'smooth', width: 3 },
                grid: { borderColor: '#e2e8f0' },
                tooltip: { y: { formatter: fmt } }
            });
            this.chartEvolucao.render();
        },

        mudarTipoGrafico(tipo) {
            this.tipoGrafico = tipo;
            this.renderizarGraficoEvolucao();
        },

        renderizarGraficoRegiao() {
            this.destruirGrafico('chartRegiao');
            const el = document.querySelector('#chartRegiao');
            if (!el) return;
            el.innerHTML = '';
            if (!this.distribuicaoRegiao?.length) return;

            this.chartRegiao = new ApexCharts(el, {
                chart: { type: 'donut', height: 280 },
                series: this.distribuicaoRegiao.map(r => parseFloat(r.valor || 0)),
                labels: this.distribuicaoRegiao.map(r => r.regiao),
                colors: ['#059669', '#3b82f6', '#f59e0b', '#ef4444', '#8b5cf6'],
                legend: { position: 'bottom', fontSize: '11px' },
                tooltip: { y: { formatter: (v) => 'R$ ' + v.toLocaleString('pt-BR') } }
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
                chart: { type: 'bar', height: 250, toolbar: { show: false } },
                series: [{ name: 'Faturamento (R$)', data: evo.map(m => parseFloat(m.valor) || 0) }],
                xaxis: { categories: evo.map(m => m.label) },
                colors: ['#059669'],
                dataLabels: { enabled: false },
                title: { text: 'Evolução mensal do produto (últimos 6 meses)', style: { fontSize: '12px' } },
                tooltip: { y: { formatter: (v) => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) } }
            });
            this.chartProdutoEvolucao.render();
        },

        // ======================================================================
        // DETALHES (modais)
        // ======================================================================
        async verDetalhesProduto(idproduto, nome) {
            this.modalProdutoOpen = true;
            this.produtoLoading = true;
            this.produtoErro = '';
            this.destruirGrafico('chartProdutoEvolucao');
            this.produtoDetalhes = { nome, valor: 0, quantidade: 0, preco_medio: 0, total_clientes: 0, clientes: [], evolucao: [] };

            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/produto-detalhes', this.corpoFiltros({ idproduto }));
                this.produtoDetalhes = {
                    nome,
                    valor: data.produto?.valor || 0,
                    quantidade: data.produto?.quantidade || 0,
                    preco_medio: data.produto?.preco_medio || 0,
                    total_clientes: data.produto?.total_clientes || 0,
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
            this.modalClienteOpen = true;
            this.clienteLoading = true;
            this.clienteErro = '';
            this.clienteDetalhes = { nome, valor: 0, total_notas: 0, ticket_medio: 0, quantidade: 0, produtos: [] };

            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/cliente-detalhes', this.corpoFiltros({ idcliente }));
                this.clienteDetalhes = {
                    nome,
                    valor: data.cliente?.valor || 0,
                    total_notas: data.cliente?.total_notas || 0,
                    ticket_medio: data.cliente?.ticket_medio || 0,
                    quantidade: data.cliente?.quantidade || 0,
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
                // O id do ranking prevalece sobre o filtro de representante
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

        async abrirDetalhesCard(tipo, titulo) {
            this.modalCardTitulo = titulo || tipo;
            this.modalCardTipo = tipo;
            this.modalCardOpen = true;
            this.cardLoading = true;
            this.cardErro = '';
            this.cardDetalhes = [];
            this.cardTotal = null;
            this.cardExtra = {};
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-card', this.corpoFiltros({ tipo }));
                this.cardDetalhes = dados.dados || [];
                this.cardTotal = dados.total || null;
                this.cardRotulo = dados.rotulo_documentos || 'Docs';
                this.cardExtra = { por_dia_semana: dados.por_dia_semana || [], positivacao: dados.positivacao || null };
            } catch (e) {
                console.error('Erro card:', e);
                this.cardErro = e.message;
            } finally {
                this.cardLoading = false;
            }
        },

        // ======================================================================
        // UTILITÁRIOS
        // ======================================================================
        formatarProjecao(projecao) {
            return (projecao || [])
                .filter(item => item?.mes)
                .map(item => {
                    const [ano, mes] = String(item.mes).split('-');
                    return {
                        mes_label: `${MESES[parseInt(mes, 10) - 1]}/${ano}`,
                        valor: parseFloat(item.valor || 0),
                        realizado: item.tipo === 'realizado'
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
            return `vs ${f(p.inicio)} a ${f(p.fim)}${p.parcial ? ' (mesmo nº de dias)' : ''}`;
        },
        formatarDataBR(iso) {
            const d = this.parseISO(iso);
            return d ? d.toLocaleDateString('pt-BR') : '';
        },

        // Data local, sem conversão UTC
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

        formatMoney(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v || 0); },
        formatNumber(v) { return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(v || 0); },

        larguraFunil(item) {
            const max = Math.max(...this.funilPedidos.map(f => f.valor), 0);
            return max > 0 ? Math.min((item.valor / max) * 100, 100) : 0;
        },
        corFunil(item) {
            return { 1: 'bg-blue-500', 4: 'bg-amber-500', 5: 'bg-emerald-500', 3: 'bg-rose-500' }[item.status] || 'bg-slate-400';
        },

        // ======================================================================
        // EXPORTAR (CSV dos dados já carregados, respeitando os filtros atuais)
        // ======================================================================
        exportarDados() {
            if (this.loading) {
                this.showToast('Aguarde o carregamento dos dados.', 'info');
                return;
            }
            const esc = (v) => {
                if (v === null || v === undefined) return '';
                if (typeof v === 'number') return String(v).replace('.', ',');
                const s = String(v);
                return /[";\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
            };
            const linhas = [];
            const add = (...c) => linhas.push(c.map(esc).join(';'));
            const k = this.kpis;

            add('Dashboard de Vendas');
            add('Período', this.filters.data_inicio, this.filters.data_fim);
            add('Filial', this.filters.filial || 'Todas', 'Gestor', this.filters.gestor || 'Todos', 'Representante', this.filters.representante || 'Todos');
            add();
            add('INDICADOR', 'VALOR');
            add('Faturamento (NFs faturadas)', k.faturamento);
            add('NFs emitidas', k.notas);
            add('Ticket médio (faturamento / NFs)', k.ticket_medio);
            add('Média por dia útil', k.media_diaria);
            add('Carteira de pedidos (abertos e parciais)', k.carteira.valor);
            add('NFs canceladas', k.cancelados.valor);
            add('Devoluções', k.devolucoes.valor);
            add('Positivação (%)', k.positivacao.percentual);
            add();
            add('EVOLUÇÃO (' + (this.evolucao.granularidade === 'dia' ? 'diária' : 'mensal') + ')', 'Faturamento', 'Quantidade', 'NFs');
            (this.evolucao.itens || []).forEach(i => add(i.label, i.valor, i.quantidade, i.notas));
            add();
            add('TOP PRODUTOS', 'Valor', 'Quantidade');
            this.topProdutos.forEach(i => add(i.produto, i.valor, i.quantidade));
            add();
            add('TOP CLIENTES', 'Valor', 'NFs');
            this.topClientes.forEach(i => add(i.cliente, i.valor, i.total_notas));
            add();
            add('REGIÃO', 'Valor');
            this.distribuicaoRegiao.forEach(i => add(i.regiao, i.valor));
            add();
            add('TOP REPRESENTANTES', 'Valor', 'NFs', 'Clientes');
            this.rankingRepresentantes.forEach(i => add(i.nome, i.valor, i.total_notas, i.clientes_ativos));
            add();
            add('FUNIL DE PEDIDOS', 'Pedidos', 'Valor');
            this.funilPedidos.forEach(i => add(i.etapa, i.quantidade, i.valor));
            add();
            add('MARGEM POR PRODUTO', 'Receita', 'Comissão', '% Comissão', 'Margem gerencial %', 'Quantidade');
            this.margemProdutos.forEach(i => add(i.produto, i.receita, i.comissao, i.comissao_percentual, i.margem_percentual, i.quantidade));

            const blob = new Blob(['\uFEFF' + linhas.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `dashboard-vendas_${this.filters.data_inicio}_${this.filters.data_fim}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        },

        showToast(msg, type = 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: type, title: msg, showConfirmButton: false, timer: 3000 });
            }
        }
    };
}
