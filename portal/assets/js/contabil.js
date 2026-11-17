// ==========================================================================
// MÓDULO CONTÁBIL & FISCAL — CONFERÊNCIA DOMÍNIO SISTEMAS (CHAMADO #50)
// Portal Nutricional — Filtros Avançados, Período Personalizado, DANFE Espelho
// ==========================================================================

function contabilHandler() {
    const mesAnterior = () => {
        const d = new Date();
        d.setDate(1);
        d.setMonth(d.getMonth() - 1);
        return d.toISOString().slice(0, 7);
    };

    const primeiroDiaMes = (ym) => `${ym}-01`;
    const ultimoDiaMes = (ym) => {
        const [y, m] = ym.split('-').map(Number);
        return new Date(y, m, 0).toISOString().slice(0, 10);
    };

    const apiBase = () => (typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : '/v1');

    return {
        loading: false,
        erro: '',
        abaAtiva: 'cfop',
        filtrosAvancadosAbertos: false,

        filtros: {
            modoPeriodo: 'mensal',
            mes: mesAnterior(),
            dataInicio: primeiroDiaMes(mesAnterior()),
            dataFim: ultimoDiaMes(mesAnterior()),
            filial: '0',
            modoCredito: 'creditado',
            tipoNota: 'TODAS',
            subtipoNota: '',
            transacaoSaida: '0',
            transacaoEntrada: '0',
            idgrupo: '0',
            idmarca: '0',
            itemBusca: ''
        },

        opcoesFiltros: {
            transacoes_saida: [],
            transacoes_entrada: [],
            grupos: [],
            marcas: []
        },

        filiaisDisponiveis: [],
        competenciaLabel: '',
        resumo: false,

        projecao: {
            icms: { debito: 0, credito: 0, credito_destacado: 0, base_saida: 0, base_entrada: 0, saldo: 0, situacao: 'A PAGAR' },
            pis: { debito: 0, credito: 0, credito_destacado: 0, base_saida: 0, base_entrada: 0, saldo: 0, situacao: 'A PAGAR' },
            cofins: { debito: 0, credito: 0, credito_destacado: 0, base_saida: 0, base_entrada: 0, saldo: 0, situacao: 'A PAGAR' },
            ipi: { debito: 0, credito: 0, destacado_entrada: 0, saldo: 0 },
            total_a_pagar: 0
        },
        totaisSaida: { qtd_notas: 0, valor_contabil: 0, base_icms: 0, icms_apurado: 0, pis_apurado: 0, cofins_apurado: 0, ipi_apurado: 0, valor_st: 0 },
        totaisEntrada: { qtd_notas: 0, valor_contabil: 0, icms_destacado: 0, icms_apurado: 0, pis_apurado: 0, cofins_apurado: 0, ipi_destacado: 0, valor_st: 0 },
        cfopsSaida: [],
        cfopsEntrada: [],
        auditoria: {
            saidas_canceladas_qtd: 0,
            saidas_canceladas_valor: 0,
            entradas_credito_parcial_notas: 0,
            entradas_icms_nao_creditado: 0
        },
        historico: [],
        chartInstance: null,

        // Aba Notas
        notasCarregadas: false,
        filtroNotas: { cfop: '', busca: '' },
        notasLista: [],
        notasPag: { total: 0, page: 1, pages: 1 },

        // Aba Itens
        itensCarregados: false,
        filtroItens: { cfop: '', busca: '' },
        itensLista: [],
        itensPag: { total: 0, page: 1, pages: 1 },
        itensTotais: { valor_total: 0, icms_dest: 0, icms_cred: 0, pis_cred: 0, cofins_cred: 0 },

        // Modal Itens da Nota
        modalNotaAberto: false,
        loadingNotaItens: false,
        notaSelecionada: null,
        notaItensLista: [],

        // Modal DANFE Espelho + Origem
        modalDanfeAberto: false,
        loadingDanfe: false,
        danfeNota: null,
        danfeItens: [],

        async init() {
            await Promise.all([this.carregarOpcoesFiltros(), this.carregarResumo()]);
        },

        getToken() {
            return localStorage.getItem('authToken') || '';
        },

        async apiGet(path, params = {}) {
            const token = this.getToken();
            if (!token) {
                window.location.href = (typeof PORTAL_BASE !== 'undefined' ? PORTAL_BASE : '/portal') + '/login.php';
                throw new Error('Sessão não autenticada');
            }
            const clean = {};
            Object.entries(params).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) clean[k] = v;
            });
            const qs = new URLSearchParams(clean).toString();
            const url = `${apiBase()}${path}${qs ? '?' + qs : ''}`;
            const resp = await fetch(url, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            if (resp.status === 401) {
                localStorage.clear();
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada');
            }
            const data = await resp.json().catch(() => null);
            if (!resp.ok || !data || data.success === false) {
                throw new Error(data?.error || `Erro HTTP ${resp.status}`);
            }
            return data;
        },

        async carregarOpcoesFiltros() {
            try {
                const d = await this.apiGet('/contabil/filtros');
                this.opcoesFiltros = {
                    transacoes_saida: d.transacoes_saida || [],
                    transacoes_entrada: d.transacoes_entrada || [],
                    grupos: d.grupos || [],
                    marcas: d.marcas || []
                };
            } catch (e) {
                // Silencioso para não bloquear carregamento principal
            }
        },

        paramsGlobais() {
            const p = {
                filial: this.filtros.filial,
                modo_credito: this.filtros.modoCredito,
                tipo_nota: this.filtros.tipoNota,
                subtipo_nota: this.filtros.subtipoNota,
                transacao_saida: this.filtros.transacaoSaida,
                transacao_entrada: this.filtros.transacaoEntrada,
                idgrupo: this.filtros.idgrupo,
                idmarca: this.filtros.idmarca,
                item_busca: this.filtros.itemBusca
            };
            if (this.filtros.modoPeriodo === 'personalizado') {
                p.data_inicio = this.filtros.dataInicio;
                p.data_fim = this.filtros.dataFim;
            } else {
                p.mes = this.filtros.mes;
            }
            return p;
        },

        temFiltroAvancado() {
            return (
                this.filtros.subtipoNota !== '' ||
                this.filtros.transacaoSaida !== '0' ||
                this.filtros.transacaoEntrada !== '0' ||
                this.filtros.idgrupo !== '0' ||
                this.filtros.idmarca !== '0' ||
                this.filtros.itemBusca.trim() !== ''
            );
        },

        async limparFiltrosAvancados() {
            this.filtros.subtipoNota = '';
            this.filtros.transacaoSaida = '0';
            this.filtros.transacaoEntrada = '0';
            this.filtros.idgrupo = '0';
            this.filtros.idmarca = '0';
            this.filtros.itemBusca = '';
            await this.onFiltroChange();
        },

        async onModoPeriodoChange() {
            if (this.filtros.modoPeriodo === 'personalizado') {
                this.filtros.dataInicio = primeiroDiaMes(this.filtros.mes);
                this.filtros.dataFim = ultimoDiaMes(this.filtros.mes);
            }
            await this.onFiltroChange();
        },

        async onFiltroChange() {
            this.notasCarregadas = false;
            this.itensCarregados = false;
            await this.carregarTudo();
        },

        async carregarTudo() {
            await this.carregarResumo();
            if (this.abaAtiva === 'notas') await this.carregarNotas(1);
            if (this.abaAtiva === 'itens') await this.carregarItens(1);
        },

        async carregarResumo() {
            this.loading = true;
            this.erro = '';
            try {
                const data = await this.apiGet('/contabil/resumo', this.paramsGlobais());
                this.filiaisDisponiveis = data.filiais_disponiveis || [];
                if (this.filiaisDisponiveis.length === 1) {
                    this.filtros.filial = String(this.filiaisDisponiveis[0].idfilial);
                }
                this.competenciaLabel = data.competencia?.label || this.filtros.mes;
                this.projecao = data.projecao;
                this.totaisSaida = data.totais_saida;
                this.totaisEntrada = data.totais_entrada;
                this.cfopsSaida = data.cfops_saida || [];
                this.cfopsEntrada = data.cfops_entrada || [];
                this.auditoria = data.auditoria || {};
                this.historico = data.historico || [];
                this.resumo = true;
                this.$nextTick(() => this.renderChart());
            } catch (e) {
                this.erro = e.message || 'Erro ao carregar apuração contábil.';
            } finally {
                this.loading = false;
            }
        },

        async carregarNotas(page = 1) {
            if (page < 1) return;
            this.loading = true;
            this.erro = '';
            try {
                const data = await this.apiGet('/contabil/notas', {
                    ...this.paramsGlobais(),
                    cfop: this.filtroNotas.cfop,
                    busca: this.filtroNotas.busca,
                    page
                });
                this.notasLista = data.notas || [];
                this.notasPag = { total: data.total || 0, page: data.page || 1, pages: data.pages || 1 };
                this.notasCarregadas = true;
            } catch (e) {
                this.erro = e.message;
            } finally {
                this.loading = false;
            }
        },

        async carregarItens(page = 1) {
            if (page < 1) return;
            this.loading = true;
            this.erro = '';
            try {
                const data = await this.apiGet('/contabil/itens', {
                    ...this.paramsGlobais(),
                    cfop: this.filtroItens.cfop,
                    busca: this.filtroItens.busca,
                    page
                });
                this.itensLista = data.itens || [];
                this.itensPag = { total: data.total || 0, page: data.page || 1, pages: data.pages || 1 };
                this.itensTotais = data.totais || this.itensTotais;
                this.itensCarregados = true;
            } catch (e) {
                this.erro = e.message;
            } finally {
                this.loading = false;
            }
        },

        async rastrearCfop(tipo, cfop, abaDestino) {
            this.filtros.tipoNota = tipo;
            if (abaDestino === 'notas') {
                this.filtroNotas.cfop = String(cfop);
                this.abaAtiva = 'notas';
                await this.carregarNotas(1);
            } else {
                this.filtroItens.cfop = String(cfop);
                this.abaAtiva = 'itens';
                await this.carregarItens(1);
            }
        },

        async abrirItensNota(nota) {
            this.notaSelecionada = nota;
            this.notaItensLista = [];
            this.modalNotaAberto = true;
            this.loadingNotaItens = true;
            try {
                const data = await this.apiGet(`/contabil/notas/${nota.tipo}/${nota.idfilial}/${nota.idnota}/itens`);
                this.notaItensLista = data.itens || [];
            } catch (e) {
                this.erro = e.message;
            } finally {
                this.loadingNotaItens = false;
            }
        },

        async abrirDanfeEspelho(tipo, idfilial, idnota) {
            this.danfeNota = null;
            this.danfeItens = [];
            this.modalDanfeAberto = true;
            this.loadingDanfe = true;
            try {
                const data = await this.apiGet(`/contabil/notas/${tipo}/${idfilial}/${idnota}/espelho`);
                this.danfeNota = data.nota;
                this.danfeItens = data.itens || [];
            } catch (e) {
                this.erro = e.message;
                this.modalDanfeAberto = false;
            } finally {
                this.loadingDanfe = false;
            }
        },

        imprimirDanfe() {
            document.body.classList.add('imprimindo-danfe');
            window.print();
            setTimeout(() => document.body.classList.remove('imprimindo-danfe'), 500);
        },

        async exportarItensCsv() {
            const token = this.getToken();
            const params = {
                ...this.paramsGlobais(),
                cfop: this.filtroItens.cfop,
                busca: this.filtroItens.busca,
                exportar: 'csv'
            };
            const clean = {};
            Object.entries(params).forEach(([k, v]) => {
                if (v !== '' && v !== null && v !== undefined) clean[k] = v;
            });
            const qs = new URLSearchParams(clean).toString();
            const resp = await fetch(`${apiBase()}/contabil/itens?${qs}`, {
                headers: { 'Authorization': `Bearer ${token}` }
            });
            if (!resp.ok) {
                this.erro = 'Falha ao exportar relatório CSV.';
                return;
            }
            const blob = await resp.blob();
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `relatorio_contabil_itens_${this.filtros.mes}.csv`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        },

        renderChart() {
            const canvas = document.getElementById('chartContabilHistorico');
            if (!canvas || typeof Chart === 'undefined') return;
            if (this.chartInstance) {
                this.chartInstance.destroy();
            }
            const labels = this.historico.map(h => h.ym);
            const debitos = this.historico.map(h => +(h.saidas_icms + h.saidas_piscofins).toFixed(2));
            const creditos = this.historico.map(h => +(h.entradas_icms + h.entradas_piscofins).toFixed(2));
            const liquidos = this.historico.map(h => h.liquido_total);

            this.chartInstance = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels,
                    datasets: [
                        { label: 'Débito Saídas (R$)', data: debitos, backgroundColor: 'rgba(225, 29, 72, 0.75)', borderRadius: 6 },
                        { label: 'Crédito Entradas (R$)', data: creditos, backgroundColor: 'rgba(16, 185, 129, 0.75)', borderRadius: 6 },
                        { label: 'Líquido a Pagar (R$)', data: liquidos, type: 'line', borderColor: '#312e81', backgroundColor: '#312e81', borderWidth: 2.5, tension: 0.3, pointRadius: 4 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11, weight: 'bold' } } }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: v => 'R$ ' + Number(v).toLocaleString('pt-BR') }
                        }
                    }
                }
            });
        },

        calcAliq(imposto, base) {
            if (!base || Number(base) <= 0) return '0,00%';
            return ((Number(imposto) / Number(base)) * 100).toFixed(2).replace('.', ',') + '%';
        },

        fmtMoeda(v) {
            return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        },

        fmtNum(v) {
            return Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        fmtData(d) {
            if (!d) return '-';
            const p = String(d).slice(0, 10).split('-');
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
        }
    };
}
