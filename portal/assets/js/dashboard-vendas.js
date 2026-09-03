// ==========================================================================
// DASHBOARD DE VENDAS - COM HIERARQUIA AUTOMÁTICA
// Filial → Gestor → Representante
// ==========================================================================

function dashboardVendasHandler() {
    return {
        // Estado
        loading: false,
        loadingPesados: false,
        _inicializado: false,
        carregamentoInicial: true,
        
        // Modais
        modalCardOpen: false,
        modalCardTitulo: '',
        modalCardTipo: '',
        cardDetalhes: [],
        modalRepresentanteOpen: false,
        modalFunilOpen: false,
        representanteDetalhes: { nome: '', clientes: [], produtos: [], evolucao: [] },
        funilDetalhes: { etapa: '', pedidos: [] },
        
        // Filtros
        filters: {
            data_inicio: '',
            data_fim: '',
            filial: '',
            gestor: '',
            representante: ''
        },
        
        // ✅ Listas para hierarquia
        filiaisPermitidas: [],
        gestoresDisponiveis: [],
        representantesDisponiveis: [],
        
        // KPIs
        kpis: {
            faturamento: 0,
            crescimento_faturamento: 0,
            total_pedidos: 0,
            crescimento_pedidos: 0,
            ticket_medio: 0,
            crescimento_ticket: 0,
            media_diaria: 0,
            ticket_medio_diario: 0
        },
        
        // Dados
        topProdutos: [],
        topClientes: [],
        distribuicaoRegiao: [],
        margemProdutos: [],
        evolucaoMensal: [],
        matrizCrossSelling: [],
        projecao: [],
        rankingRepresentantes: [],
        funilPedidos: [],
        projecaoFechamento: {},
        clientesRisco: [],
        
        // Gráficos
        tipoGrafico: 'valor',
        chartEvolucao: null,
        chartRegiao: null,
        chartProdutoEvolucao: null,
        
        // Modais de detalhes
        modalProdutoOpen: false,
        modalClienteOpen: false,
        produtoDetalhes: { nome: '', valor: 0, quantidade: 0, preco_medio: 0, total_clientes: 0, clientes: [], evolucao: [] },
        clienteDetalhes: { nome: '', valor: 0, total_pedidos: 0, ticket_medio: 0, quantidade: 0, produtos: [] },
        
        maxProjecao: 1,

        // ======================================================================
        // INICIALIZAÇÃO COM HIERARQUIA AUTOMÁTICA
        // ======================================================================
        async init() {
            if (this._inicializado) return;
            this._inicializado = true;
            
            if (!this.filters.data_inicio) {
                this.filters.data_inicio = this.getPrimeiroDiaMes();
                this.filters.data_fim = this.getUltimoDiaMes();
            }
            
            // ✅ 1. Carregar filiais
            await this.carregarFiliais();
            
            // ✅ 2. Se 1 filial, auto-seleciona
            if (this.filiaisPermitidas.length === 1) {
                this.filters.filial = String(this.filiaisPermitidas[0].idfilial);
            }
            
            // ✅ 3. Carregar gestores liberados
            await this.carregarGestores();
            
            // ✅ 4. Se 1 gestor, auto-seleciona
            if (this.gestoresDisponiveis.length === 1) {
                this.filters.gestor = String(this.gestoresDisponiveis[0].id);
                await this.carregarRepresentantes();
            }
            
            // ✅ 5. Carregar dados
            this.carregamentoInicial = false;
            await this.carregarDados();
        },

        // ======================================================================
        // API FETCH
        // ======================================================================
        getToken() {
            return localStorage.getItem('authToken') || '';
        },

        async apiFetch(url, body = null) {
            const token = this.getToken();
            if (!token) {
                window.location.href = '/portal/login.php';
                throw new Error('Sem token');
            }
            
    // ✅ Definir método: se tem body é POST, senão verifica a URL
    let method = 'POST'; // Padrão: POST para a maioria das rotas de vendas
    
    // ✅ Rotas que são GET
    const getRoutes = ['/v1/vendas/cubo/config'];
    if (getRoutes.includes(url)) {
        method = 'GET';
    }
    
    const options = {
        method: method,
        headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json'
        }
    };
    
    if (method === 'POST') {
        options.body = body ? JSON.stringify(body) : '{}';
    }
    
    const resp = await fetch(url, options);
    if (resp.status === 401) {
        localStorage.clear();
        window.location.href = '/portal/login.php';
        throw new Error('Sessão expirada');
    }
    
    const text = await resp.text();
    try {
        const json = JSON.parse(text);
        if (!resp.ok) throw new Error(json.error || `HTTP ${resp.status}`);
        return json;
    } catch (e) {
        throw new Error('Erro na API');
    }
},
        // ======================================================================
        // CARREGAR FILIAIS
        // ======================================================================
        async carregarFiliais() {
            try {
                const data = await this.apiFetch('/v1/vendas/cubo/filiais', null);
                this.filiaisPermitidas = data.filiais || [];
            } catch (e) {
                console.error('Erro filiais:', e);
            }
        },

        // ======================================================================
// CARREGAR GESTORES (filtrados pela filial selecionada)
// ======================================================================
async carregarGestores() {
    try {
        const idFilial = parseInt(this.filters.filial) || 0;
        
        // ✅ Se tem filial selecionada, filtra gestores por ela
        if (idFilial > 0) {
            const data = await this.apiFetch('/v1/vendas/cubo/supervisores-por-filial', {
                idfilial: idFilial
            });
            this.gestoresDisponiveis = data.supervisores || [];
        } else {
            // Sem filial, carrega todos os gestores liberados
            const data = await this.apiFetch('/v1/vendas/cubo/gestores', null);
            this.gestoresDisponiveis = data.gestores || [];
        }
        
        // ✅ Se só tem 1 gestor, auto-seleciona
        if (this.gestoresDisponiveis.length === 1 && this.carregamentoInicial) {
            this.filters.gestor = String(this.gestoresDisponiveis[0].id);
            await this.carregarRepresentantes();
        }
    } catch (e) {
        console.error('Erro gestores:', e);
        this.gestoresDisponiveis = [];
    }
},
        // ======================================================================
        // CARREGAR REPRESENTANTES (filhos do gestor)
        // ======================================================================
        async carregarRepresentantes() {
            try {
                const data = await this.apiFetch('/v1/vendas/cubo/representantes-por-supervisor', {
                    idsupervisor: parseInt(this.filters.gestor) || 0,
                    idfilial: parseInt(this.filters.filial) || 0
                });
                this.representantesDisponiveis = data.representantes || [];
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
    
    this.filters.gestor = '';
    this.filters.representante = '';
    this.representantesDisponiveis = [];
    this.gestoresDisponiveis = [];
    
    // ✅ Recarregar gestores baseado na filial selecionada
    await this.carregarGestores();
    
    await this.carregarDados();
},

        // ======================================================================
        // CASCATA: Gestor
        // ======================================================================
        async mudarGestor() {
            if (this.carregamentoInicial) return;
            this.filters.representante = '';
            this.representantesDisponiveis = [];
            
            if (this.filters.gestor) {
                await this.carregarRepresentantes();
            }
            
            await this.carregarDados();
            setTimeout(() => this.carregarDadosPesados(), 500);
        },

        // ======================================================================
        // CASCATA: Representante
        // ======================================================================
        mudarRepresentante() {
            if (this.carregamentoInicial) return;
            this.carregarDados();
            if (this.filters.representante) {
                setTimeout(() => this.carregarDadosPesados(), 500);
            }
        },

        mudarPeriodo() {
            const d1 = new Date(this.filters.data_inicio);
            const d2 = new Date(this.filters.data_fim);
            const diff = (d2 - d1) / (1000 * 60 * 60 * 24);
            if (diff > 60 && !this.filters.gestor) {
                this.showToast('Períodos longos exigem selecionar um Gestor', 'warning');
            }
            this.carregarDados();
        },

        // ======================================================================
        // CARREGAR DADOS PRINCIPAIS
        // ======================================================================
        async carregarDados() {
            this.loading = true;
            
            try {
        // ✅ Se tem gestores restritos e nenhum selecionado, enviar lista de gestores
        const body = {
            data_inicio: this.filters.data_inicio,
            data_fim: this.filters.data_fim,
            filial: this.filters.filial || '',
            gestor: this.filters.gestor || '',
            representante: this.filters.representante || ''
        };
        
        const data = await this.apiFetch('/v1/vendas/dashboard/kpis', body);
        
        if (data.success) {
            this.kpis = {
                faturamento: data.evolucao_mensal?.reduce((acc, m) => acc + (parseFloat(m.valor) || 0), 0) || 0,
                total_pedidos: data.evolucao_mensal?.reduce((acc, m) => acc + m.pedidos, 0) || 0,
                ticket_medio: data.ticket_medio?.atual || 0,
                crescimento_faturamento: data.ticket_medio?.variacao || 0,
                crescimento_pedidos: 0,
                crescimento_ticket: data.ticket_medio?.variacao || 0,
                media_diaria: data.velocidade_venda?.media_diaria || 0,
                ticket_medio_diario: data.velocidade_venda?.ticket_medio_diario || 0
            };
            
            this.topProdutos = data.top_produtos || [];
            this.topClientes = data.top_clientes || [];
            this.distribuicaoRegiao = data.distribuicao_regiao || [];
            this.evolucaoMensal = data.evolucao_mensal || [];
            
            setTimeout(() => {
                this.renderizarGraficoEvolucao();
                this.renderizarGraficoRegiao();
            }, 300);
            
            this.carregarDadosPesados();
            this.carregarInsights();
        }
    } catch (e) {
        console.error('Erro dados:', e);
    } finally {
        this.loading = false;
    }
},
        // ======================================================================
        // DADOS PESADOS
        // ======================================================================
        async carregarDadosPesados() {
            this.loadingPesados = true;
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/kpis-detalhes', {
                    data_inicio: this.filters.data_inicio,
                    data_fim: this.filters.data_fim,
                    filial: this.filters.filial || '',
                    gestor: this.filters.gestor || '',
                    representante: this.filters.representante || ''
                });
                
                if (dados.success) {
                    this.margemProdutos = dados.margem_produtos || [];
                    this.matrizCrossSelling = dados.matriz_cross_selling || [];
                    this.projecao = this.formatarProjecao(dados.projecao_vendas || []);
                    this.maxProjecao = this.projecao.length > 0 ? Math.max(...this.projecao.map(p => p.valor), 1) : 1;
                }
            } catch (e) {
                console.error('Erro dados pesados:', e);
            } finally {
                this.loadingPesados = false;
            }
        },

        // ======================================================================
        // INSIGHTS
        // ======================================================================
        async carregarInsights() {
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/insights', {
                    data_inicio: this.filters.data_inicio,
                    data_fim: this.filters.data_fim,
                    gestor: this.filters.gestor || '',
                    representante: this.filters.representante || ''
                });
                
                if (dados.success) {
                    this.rankingRepresentantes = dados.ranking_representantes || [];
                    this.funilPedidos = dados.funil_pedidos || [];
                    this.projecaoFechamento = dados.projecao_fechamento || {};
                    this.clientesRisco = dados.clientes_risco || [];
                }
            } catch (e) {
                console.error('Erro insights:', e);
            }
        },

        // ======================================================================
        // GRÁFICOS
        // ======================================================================
        renderizarGraficoEvolucao() {
            const el = document.querySelector('#chartEvolucao');
            if (!el || !this.evolucaoMensal?.length) return;
            
            if (this.chartEvolucao) { this.chartEvolucao.destroy(); this.chartEvolucao = null; }
            
            const labels = this.evolucaoMensal.map(m => m.label);
            const valores = this.evolucaoMensal.map(m => parseFloat(m.valor || 0));
            
            this.chartEvolucao = new ApexCharts(el, {
                chart: { type: 'area', height: 320, toolbar: { show: false } },
                series: [{ name: 'Faturamento (R$)', data: valores }],
                xaxis: { categories: labels },
                colors: ['#059669'],
                fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.3, opacityTo: 0.05 } },
                stroke: { curve: 'smooth', width: 3 },
                grid: { borderColor: '#e2e8f0' },
                tooltip: { y: { formatter: (v) => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) } }
            });
            this.chartEvolucao.render();
        },

        renderizarGraficoRegiao() {
            const el = document.querySelector('#chartRegiao');
            if (!el || !this.distribuicaoRegiao?.length) return;
            
            if (this.chartRegiao) { this.chartRegiao.destroy(); this.chartRegiao = null; }
            
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

        // ======================================================================
        // DETALHES
        // ======================================================================
        async verDetalhesProduto(idproduto, nome) {
            this.modalProdutoOpen = true;
            this.produtoDetalhes = { nome, valor: 0, quantidade: 0, preco_medio: 0, total_clientes: 0, clientes: [], evolucao: [] };
            
            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/produto-detalhes', {
                    idproduto, data_inicio: this.filters.data_inicio, data_fim: this.filters.data_fim
                });
                if (data.success) {
                    this.produtoDetalhes = {
                        nome, valor: data.produto?.valor || 0,
                        quantidade: data.produto?.quantidade || 0,
                        preco_medio: data.produto?.preco_medio || 0,
                        total_clientes: data.produto?.total_clientes || 0,
                        clientes: data.clientes || [], evolucao: data.evolucao || []
                    };
                }
            } catch (e) { console.error('Erro produto:', e); }
        },

        async verDetalhesCliente(idcliente, nome) {
            this.modalClienteOpen = true;
            this.clienteDetalhes = { nome, valor: 0, total_pedidos: 0, ticket_medio: 0, quantidade: 0, produtos: [] };
            
            try {
                const data = await this.apiFetch('/v1/vendas/dashboard/cliente-detalhes', {
                    idcliente, data_inicio: this.filters.data_inicio, data_fim: this.filters.data_fim
                });
                if (data.success) {
                    this.clienteDetalhes = {
                        nome, valor: data.cliente?.valor || 0,
                        total_pedidos: data.cliente?.total_pedidos || 0,
                        ticket_medio: data.cliente?.ticket_medio || 0,
                        quantidade: data.cliente?.quantidade || 0,
                        produtos: data.top_produtos || []
                    };
                }
            } catch (e) { console.error('Erro cliente:', e); }
        },

        async verDetalhesRepresentante(id, nome) {
            this.modalRepresentanteOpen = true;
            this.representanteDetalhes = { nome, clientes: [], produtos: [], evolucao: [] };
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-representante', {
                    id, data_inicio: this.filters.data_inicio, data_fim: this.filters.data_fim
                });
                if (dados.success) {
                    this.representanteDetalhes = {
                        nome, clientes: dados.clientes || [],
                        produtos: dados.produtos || [], evolucao: dados.evolucao || []
                    };
                }
            } catch (e) { console.error('Erro representante:', e); }
        },

        async verDetalhesFunil(etapa) {
            this.modalFunilOpen = true;
            this.funilDetalhes = { etapa, pedidos: [] };
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-funil', {
                    etapa, data_inicio: this.filters.data_inicio, data_fim: this.filters.data_fim,
                    gestor: this.filters.gestor || '', representante: this.filters.representante || ''
                });
                if (dados.success) this.funilDetalhes = { etapa, pedidos: dados.pedidos || [] };
            } catch (e) { console.error('Erro funil:', e); }
        },

        async abrirDetalhesCard(tipo) {
            this.modalCardTitulo = tipo;
            this.modalCardOpen = true;
            this.cardDetalhes = [];
            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/detalhes-card', {
                    tipo, data_inicio: this.filters.data_inicio, data_fim: this.filters.data_fim,
                    gestor: this.filters.gestor || '', representante: this.filters.representante || ''
                });
                if (dados.success) this.cardDetalhes = dados.dados || [];
            } catch (e) { console.error('Erro card:', e); }
        },

        // ======================================================================
        // UTILITÁRIOS
        // ======================================================================
        formatarProjecao(projecao) {
            if (!projecao?.length) return [];
            const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
            return projecao
            .filter(item => item?.mes)
            .map(item => {
                const data = new Date(item.mes);
                return {
                    mes_label: `${meses[data.getMonth()]}/${data.getFullYear()}`,
                    valor: parseFloat(item.projecao || item.realizado || 0),
                    realizado: parseFloat(item.realizado || 0)
                };
            })
            .filter(Boolean).reverse();
        },

        formatMoney(v) { return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(v||0); },
        formatNumber(v) { return new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(v||0); },
        
        getPrimeiroDiaMes() {
            const d = new Date();
            return new Date(d.getFullYear(), d.getMonth(), 1).toISOString().split('T')[0];
        },
        getUltimoDiaMes() {
            const d = new Date();
            return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().split('T')[0];
        },
        
        async exportarDados() {
            window.open('/v1/vendas/cubo/exportar', '_blank');
        },
        
        showToast(msg, type = 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', icon: type, title: msg, showConfirmButton: false, timer: 3000 });
            }
        }
    };
}