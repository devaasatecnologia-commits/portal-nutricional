// ==========================================================================
// INTELIGÊNCIA DE RECOMPRA & CLIENTES SEM COMPRAS v1.0
// Portal Nutricional — Modo Especialista & Alta Performance
// Hierarquia: Filial → Gestor → Representante → Região → Faixa Inatividade
// Média dos Cards: Sempre baseada em 12 meses (Faturamento 12m / 12)
// ==========================================================================

function recompraClientesHandler() {
    return {
        loading: false,
        loadingCliente: false,
        erro: '',
        carregamentoInicial: true,
        filtrosAbertosMobile: false,

        // Hierarquia de Filtros
        filiaisPermitidas: [],
        gestoresDisponiveis: [],
        representantesDisponiveis: [],
        filters: {
            filial: '',
            gestor: '',
            representante: ''
        },

        // Filtros Secundários de Tela
        filtroRegiao: '',
        filtroFaixa: '',
        buscaCliente: '',

        // Dados
        clientes: [],
        resumoGeral: { total_clientes: 0, total_valor: 0, total_peso: 0 },

        // Ordenação e Paginação
        colunaOrdenacao: 'dias_sem_comprar',
        direcaoOrdenacao: 'desc',
        paginaAtual: 1,
        itensPorPagina: 50,

        // Modal Raio-X
        modalClienteOpen: false,
        clienteSelecionadoNome: '',
        clienteDetalhes: null,

        async init() {
            await this.carregarFiliais();
            if (this.filiaisPermitidas.length === 1) {
                this.filters.filial = String(this.filiaisPermitidas[0].idfilial);
            }

            await this.carregarGestores();
            if (this.gestoresDisponiveis.length === 1) {
                this.filters.gestor = String(this.gestoresDisponiveis[0].id);
                await this.carregarRepresentantes();
            }

            if (this.representantesDisponiveis.length === 1) {
                this.filters.representante = String(this.representantesDisponiveis[0].id);
            }

            this.carregamentoInicial = false;
            await this.carregarDados();
        },

        getToken() {
            return localStorage.getItem('authToken') || '';
        },

        async apiFetch(url, body = null) {
            const token = this.getToken();
            if (!token) {
                window.location.href = '/portal/login.php';
                throw new Error('Sessão não autenticada');
            }

            let resp;
            try {
                resp = await fetch(url, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: body ? JSON.stringify(body) : '{}'
                });
            } catch (e) {
                throw new Error('Falha de conexão com o servidor.');
            }

            if (resp.status === 401) {
                localStorage.clear();
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada');
            }

            const json = await resp.json().catch(() => null);
            if (!resp.ok || (json && json.success === false)) {
                throw new Error((json && json.error) || `Erro ${resp.status} ao consultar API.`);
            }
            return json;
        },

        async carregarFiliais() {
            try {
                const data = await this.apiFetch('/v1/vendas/cubo/filiais', null);
                this.filiaisPermitidas = data.filiais || [];
            } catch (e) {
                console.error('Erro filiais (cubo/filiais):', e);
                try {
                    const fallback = await this.apiFetch('/v1/vendas/dashboard/filiais-permitidas', null);
                    this.filiaisPermitidas = fallback.filiais || [];
                } catch (err2) {
                    this.filiaisPermitidas = [];
                }
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

                if (this.gestoresDisponiveis.length === 1) {
                    this.filters.gestor = String(this.gestoresDisponiveis[0].id);
                }
            } catch (e) {
                console.error('Erro gestores:', e);
                this.gestoresDisponiveis = [];
            }
        },

        async carregarRepresentantes() {
            try {
                const data = await this.apiFetch('/v1/vendas/cubo/representantes-por-supervisor', {
                    idsupervisor: parseInt(this.filters.gestor) || 0,
                    idfilial: parseInt(this.filters.filial) || 0
                });
                this.representantesDisponiveis = data.representantes || [];

                if (this.representantesDisponiveis.length === 1) {
                    this.filters.representante = String(this.representantesDisponiveis[0].id);
                }
            } catch (e) {
                console.error('Erro representantes:', e);
                this.representantesDisponiveis = [];
            }
        },

        async mudarFilial() {
            if (this.carregamentoInicial) return;
            this.filters.gestor = '';
            this.filters.representante = '';
            this.gestoresDisponiveis = [];
            this.representantesDisponiveis = [];
            await this.carregarGestores();
            if (this.filters.gestor) {
                await this.carregarRepresentantes();
            }
            await this.carregarDados();
        },

        async mudarGestor() {
            if (this.carregamentoInicial) return;
            this.filters.representante = '';
            this.representantesDisponiveis = [];
            if (this.filters.gestor) {
                await this.carregarRepresentantes();
            }
            await this.carregarDados();
        },

        async mudarRepresentante() {
            if (this.carregamentoInicial) return;
            await this.carregarDados();
        },

        corpoFiltros() {
            return {
                filial: this.filters.filial || undefined,
                gestor: this.filters.gestor || undefined,
                representante: this.filters.representante || undefined
            };
        },

        async carregarDados() {
            this.loading = true;
            this.erro = '';
            try {
                const resp = await this.apiFetch('/v1/vendas/dashboard/insights', this.corpoFiltros());
                this.clientes = resp.clientes_risco || [];
                this.resumoGeral = resp.resumo_risco || { total_clientes: 0, total_valor: 0, total_peso: 0 };
                this.paginaAtual = 1;
            } catch (e) {
                this.erro = e.message;
                this.clientes = [];
            } finally {
                this.loading = false;
            }
        },

        regioesDisponiveis() {
            const descricoes = {
                'SUL': 'SUL (RS + SC Sul/Serra)',
                'GRF': 'GRF (Grande Florianópolis)',
                'AVI': 'AVI (Alto Vale do Itajaí)',
                'NOR': 'NOR (Norte SC + Joinville)',
                'PLN': 'PLN (Planalto Norte + Curitibanos)',
                'RMC': 'RMC (Curitiba e Metropolitana)'
            };
            const encontradas = new Set();
            (this.clientes || []).forEach(c => {
                if (c.regiao) encontradas.add(String(c.regiao).trim().toUpperCase());
            });
            const lista = Array.from(encontradas).sort();
            if (lista.length === 0) {
                return Object.keys(descricoes).map(sigla => ({ sigla, nome: descricoes[sigla] }));
            }
            return lista.map(sigla => ({
                sigla,
                nome: descricoes[sigla] || `${sigla} (Região)`
            }));
        },

        // ======================================================================
        // CÁLCULO DOS CARDS DE FAIXAS (MÉDIA BASE 12 MESES)
        // ======================================================================
        dadosFaixa(faixa) {
            let lista = this.clientes;
            if (this.filtroRegiao) {
                lista = lista.filter(c => (c.regiao || 'SUL') === this.filtroRegiao);
            }

            let filtrados = [];
            if (faixa === '30_45') {
                filtrados = lista.filter(c => c.dias_sem_comprar >= 30 && c.dias_sem_comprar <= 45);
            } else if (faixa === '46_60') {
                filtrados = lista.filter(c => c.dias_sem_comprar >= 46 && c.dias_sem_comprar <= 60);
            } else if (faixa === '61_90') {
                filtrados = lista.filter(c => c.dias_sem_comprar >= 61 && c.dias_sem_comprar <= 90);
            } else if (faixa === '90_mais') {
                filtrados = lista.filter(c => c.dias_sem_comprar > 90);
            }

            const totalClientes = filtrados.length;
            const totalValor = filtrados.reduce((acc, c) => acc + (parseFloat(c.valor_historico) || 0), 0);
            const totalPeso = filtrados.reduce((acc, c) => acc + (parseFloat(c.peso_historico) || 0), 0);
            
            // Regra de Negócio: Média sempre calculada baseando-se em 12 meses
            const mediaMensal12m = totalValor / 12;
            const mediaPorClienteMes = totalClientes > 0 ? (mediaMensal12m / totalClientes) : 0;

            return {
                clientes: totalClientes,
                valor: totalValor,
                peso: totalPeso,
                mediaMensal12m,
                mediaPorClienteMes
            };
        },

        alternarFaixa(faixa) {
            this.filtroFaixa = (this.filtroFaixa === faixa) ? '' : faixa;
            this.paginaAtual = 1;
        },

        rotuloFiltroFaixa() {
            if (!this.filtroFaixa) return 'Todas as Faixas (+30 dias)';
            const map = {
                '30_45': '30 a 45 dias (Alerta Inicial)',
                '46_60': '46 a 60 dias (Atenção)',
                '61_90': '61 a 90 dias (Risco Elevado)',
                '90_mais': '+90 dias (Inativos Críticos)'
            };
            return map[this.filtroFaixa] || this.filtroFaixa;
        },

        limparFiltrosSecundarios() {
            this.filtroFaixa = '';
            this.filtroRegiao = '';
            this.buscaCliente = '';
            this.paginaAtual = 1;
        },

        badgeInatividade(dias) {
            const d = parseInt(dias || 0, 10);
            if (d > 90) return 'bg-red-100 text-red-900 border border-red-200';
            if (d > 60) return 'bg-rose-100 text-rose-800 border border-rose-200';
            if (d > 45) return 'bg-orange-100 text-orange-800 border border-orange-200';
            return 'bg-amber-100 text-amber-800 border border-amber-200';
        },

        // ======================================================================
        // FILTRAGEM, ORDENAÇÃO E PAGINAÇÃO
        // ======================================================================
        clientesFiltrados() {
            let res = this.clientes;

            if (this.filtroFaixa === '30_45') {
                res = res.filter(c => c.dias_sem_comprar >= 30 && c.dias_sem_comprar <= 45);
            } else if (this.filtroFaixa === '46_60') {
                res = res.filter(c => c.dias_sem_comprar >= 46 && c.dias_sem_comprar <= 60);
            } else if (this.filtroFaixa === '61_90') {
                res = res.filter(c => c.dias_sem_comprar >= 61 && c.dias_sem_comprar <= 90);
            } else if (this.filtroFaixa === '90_mais') {
                res = res.filter(c => c.dias_sem_comprar > 90);
            }

            if (this.filtroRegiao) {
                res = res.filter(c => (c.regiao || 'SUL') === this.filtroRegiao);
            }

            if (this.buscaCliente) {
                const termo = this.buscaCliente.toLowerCase().trim();
                res = res.filter(c => {
                    return String(c.id).includes(termo) ||
                           (c.nome && c.nome.toLowerCase().includes(termo)) ||
                           (c.cidade_uf && c.cidade_uf.toLowerCase().includes(termo)) ||
                           (c.representante && c.representante.toLowerCase().includes(termo));
                });
            }

            // Ordenação
            const col = this.colunaOrdenacao;
            const asc = this.direcaoOrdenacao === 'asc' ? 1 : -1;

            return [...res].sort((a, b) => {
                let va = a[col];
                let vb = b[col];
                if (col === 'media_mensal') {
                    va = (parseFloat(a.valor_historico) || 0) / 12;
                    vb = (parseFloat(b.valor_historico) || 0) / 12;
                } else if (col === 'valor_historico' || col === 'peso_historico' || col === 'dias_sem_comprar') {
                    va = parseFloat(va) || 0;
                    vb = parseFloat(vb) || 0;
                } else {
                    va = String(va || '').toLowerCase();
                    vb = String(vb || '').toLowerCase();
                }
                return va > vb ? asc : (va < vb ? -asc : 0);
            });
        },

        resumoFiltrado() {
            const list = this.clientesFiltrados();
            return {
                clientes: list.length,
                valor: list.reduce((a, b) => a + (parseFloat(b.valor_historico) || 0), 0),
                peso: list.reduce((a, b) => a + (parseFloat(b.peso_historico) || 0), 0)
            };
        },

        ordenarPor(col) {
            if (this.colunaOrdenacao === col) {
                this.direcaoOrdenacao = this.direcaoOrdenacao === 'asc' ? 'desc' : 'asc';
            } else {
                this.colunaOrdenacao = col;
                this.direcaoOrdenacao = 'desc';
            }
        },

        totalPaginas() {
            return Math.ceil(this.clientesFiltrados().length / this.itensPorPagina) || 1;
        },

        clientesPaginados() {
            const inicio = (this.paginaAtual - 1) * this.itensPorPagina;
            return this.clientesFiltrados().slice(inicio, inicio + this.itensPorPagina);
        },

        // ======================================================================
        // DETALHES DO CLIENTE (RAIO-X)
        // ======================================================================
        async verDetalhesCliente(idcliente, nome) {
            this.modalClienteOpen = true;
            this.clienteSelecionadoNome = nome || `Cliente #${idcliente}`;
            this.clienteDetalhes = null;
            this.loadingCliente = true;

            try {
                const dados = await this.apiFetch('/v1/vendas/dashboard/cliente-detalhes', { idcliente: parseInt(idcliente) });
                this.clienteDetalhes = dados.cliente || null;
            } catch (e) {
                console.error('Erro ao carregar detalhes do cliente:', e);
                // Fallback para os dados já existentes na lista
                const find = this.clientes.find(c => c.id == idcliente);
                if (find) {
                    this.clienteDetalhes = {
                        nome: find.nome,
                        representante: find.representante,
                        regiao: find.regiao,
                        cidade_uf: find.cidade_uf,
                        ultima_compra: find.ultima_compra,
                        dias_sem_comprar: find.dias_sem_comprar,
                        valor_historico: find.valor_historico,
                        peso_historico: find.peso_historico,
                        notas: []
                    };
                }
            } finally {
                this.loadingCliente = false;
            }
        },

        // ======================================================================
        // EXPORTAÇÃO PARA CSV (EXCEL UTF-8 BOM)
        // ======================================================================
        exportarCSV() {
            const lista = this.clientesFiltrados();
            if (lista.length === 0) {
                alert('Nenhum cliente disponível para exportar.');
                return;
            }

            const header = [
                'ID Cliente',
                'Nome do Cliente',
                'Representante',
                'Região',
                'Cidade / UF',
                'Última Compra',
                'Dias Inativo',
                'Faturamento Histórico (12m)',
                'Média Mensal (12m)',
                'Peso Histórico (Kg)',
                'Quantidade NFs'
            ];

            const rows = lista.map(c => [
                c.id,
                `"${(c.nome || '').replace(/"/g, '""')}"`,
                `"${(c.representante || '').replace(/"/g, '""')}"`,
                c.regiao || 'SUL',
                `"${(c.cidade_uf || '').replace(/"/g, '""')}"`,
                c.ultima_fmt || '',
                c.dias_sem_comprar || 0,
                (parseFloat(c.valor_historico) || 0).toFixed(2).replace('.', ','),
                ((parseFloat(c.valor_historico) || 0) / 12).toFixed(2).replace('.', ','),
                (parseFloat(c.peso_historico) || 0).toFixed(2).replace('.', ','),
                c.compras_anteriores || 0
            ]);

            const csvContent = '\uFEFF' + [header.join(';'), ...rows.map(r => r.join(';'))].join('\r\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            const dataHoje = new Date().toISOString().slice(0, 10);

            link.setAttribute('href', url);
            link.setAttribute('download', `recompra_clientes_${dataHoje}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        },

        // ======================================================================
        // UTILITÁRIOS DE FORMATAÇÃO
        // ======================================================================
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
        formatarDataBR(iso) {
            if (!iso) return '';
            const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso));
            return m ? `${m[3]}/${m[2]}/${m[1]}` : String(iso);
        }
    };
}
