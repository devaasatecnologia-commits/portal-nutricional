/**
 * Central de Chamados & Suporte Inteligente — Portal Nutricional
 * Gerenciamento reativo com Alpine.js, Kanban, Lista e Triagem de IA.
 */
function chamadosApp() {
    return {
        modoExibicao: 'kanban', // 'kanban' ou 'lista'
        carregando: false,
        analisandoIa: false,
        salvandoChamado: false,

        // Modais
        modalNovoAberto: false,
        modalDetalhesAberto: false,
        triagemFeita: false,

        // Dados
        kpis: { total: 0, abertos: 0, em_correcao: 0, criticos: 0, concluidos_mes: 0 },
        modulos: [],
        chamados: [],
        chamadoAtual: null,
        timeline: [],

        // Filtros
        filtros: {
            status: '',
            modulo: 'todos',
            criticidade: 'todas',
            categoria: 'todas',
            busca: ''
        },

        // Formulário de Novo Chamado
        novoForm: {
            relato: '',
            modulo_slug: '',
            idfilial: 1,
            titulo: '',
            categoria: 'bug',
            criticidade: 'media',
            diagnostico_ia: '',
            anexo_url: ''
        },

        // Nova Interação
        novaInteracao: {
            mensagem: '',
            tipo: 'comentario',
            eh_resolucao: false,
            versao_entrega: 'v3.5'
        },

        async init() {
            await this.carregarDashboard();
            await this.carregarChamados();
        },

        // Chamada à API
        async api(endpoint, options = {}) {
            const url = endpoint.startsWith('/') ? `/v1${endpoint}` : `/v1/${endpoint}`;
            const opts = {
                headers: { 'Content-Type': 'application/json' },
                ...options
            };
            if (opts.body && typeof opts.body === 'object') {
                opts.body = JSON.stringify(opts.body);
            }

            const fetchFn = window.fetchWithAuth || window.apiFetch || fetch;
            const resp = await fetchFn(url, opts);
            let data = null;
            try {
                data = await resp.json();
            } catch (e) {
                data = null;
            }
            if (!resp.ok) {
                throw new Error((data && (data.error || data.message)) || `Erro HTTP ${resp.status}`);
            }
            return data;
        },

        async carregarDashboard() {
            try {
                const res = await this.api('/chamados/dashboard');
                if (res.success) {
                    this.kpis = res.kpis || {};
                    this.modulos = res.modulos || [];
                }
            } catch (e) {
                console.error('Erro ao carregar dashboard:', e);
            }
        },

        async carregarChamados() {
            this.carregando = true;
            try {
                const params = new URLSearchParams();
                if (this.filtros.status !== '') params.append('status', this.filtros.status);
                if (this.filtros.modulo !== 'todos') params.append('modulo', this.filtros.modulo);
                if (this.filtros.criticidade !== 'todas') params.append('criticidade', this.filtros.criticidade);
                if (this.filtros.categoria !== 'todas') params.append('categoria', this.filtros.categoria);
                if (this.filtros.busca) params.append('busca', this.filtros.busca);

                const res = await this.api(`/chamados/listar?${params.toString()}`);
                if (res.success) {
                    this.chamados = res.chamados || [];
                }
            } catch (e) {
                console.error('Erro ao listar chamados:', e);
            } finally {
                this.carregando = false;
            }
        },

        colunaChamados(statusNum) {
            return this.chamados.filter(c => parseInt(c.status, 10) === statusNum);
        },

        badgeCriticidade(crit) {
            switch (String(crit).toLowerCase()) {
                case 'critica':
                    return 'bg-rose-100 text-rose-700 border border-rose-200';
                case 'alta':
                    return 'bg-amber-100 text-amber-700 border border-amber-200';
                case 'media':
                    return 'bg-blue-100 text-blue-700 border border-blue-200';
                case 'baixa':
                default:
                    return 'bg-emerald-100 text-emerald-700 border border-emerald-200';
            }
        },

        abrirModalNovo() {
            this.novoForm = {
                relato: '',
                modulo_slug: '',
                idfilial: 1,
                titulo: '',
                categoria: 'bug',
                criticidade: 'media',
                diagnostico_ia: '',
                anexo_url: ''
            };
            this.triagemFeita = false;
            this.modalNovoAberto = true;
        },

        async analisarComIa() {
            if (!this.novoForm.relato || this.novoForm.relato.trim().length < 4) {
                Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Descreva o problema para a IA analisar.' });
                return;
            }

            this.analisandoIa = true;
            try {
                const res = await this.api('/chamados/triagem-ia', {
                    method: 'POST',
                    body: {
                        relato: this.novoForm.relato,
                        modulo: this.novoForm.modulo_slug,
                        filial: this.novoForm.idfilial
                    }
                });

                if (res.success && res.sugestao) {
                    const s = res.sugestao;
                    this.novoForm.titulo = s.titulo;
                    this.novoForm.modulo_slug = s.modulo_slug;
                    this.novoForm.categoria = s.categoria;
                    this.novoForm.criticidade = s.criticidade;
                    this.novoForm.diagnostico_ia = s.diagnostico_ia;
                    this.triagemFeita = true;

                    Swal.fire({
                        icon: 'success',
                        title: 'IA Triagem Concluída!',
                        text: `Classificado como [${s.categoria}] com criticidade [${s.criticidade}].`,
                        timer: 2000,
                        showConfirmButton: false
                    });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro na Triagem', text: e.message });
            } finally {
                this.analisandoIa = false;
            }
        },

        async salvarNovoChamado() {
            if (!this.novoForm.relato || this.novoForm.relato.trim().length < 4) {
                Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Descreva o chamado.' });
                return;
            }

            this.salvandoChamado = true;
            try {
                const res = await this.api('/chamados/criar', {
                    method: 'POST',
                    body: this.novoForm
                });

                if (res.success) {
                    this.modalNovoAberto = false;
                    await this.carregarDashboard();
                    await this.carregarChamados();

                    Swal.fire({
                        icon: 'success',
                        title: 'Chamado Registrado!',
                        text: res.message || 'Seu chamado já está na fila com triagem de IA.'
                    });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro ao Abrir Chamado', text: e.message });
            } finally {
                this.salvandoChamado = false;
            }
        },

        async abrirDetalhes(id) {
            try {
                const res = await this.api(`/chamados/detalhes/${id}`);
                if (res.success) {
                    this.chamadoAtual = res.chamado;
                    this.timeline = res.timeline || [];
                    this.novaInteracao = {
                        mensagem: '',
                        tipo: 'comentario',
                        eh_resolucao: false,
                        versao_entrega: 'v3.5'
                    };
                    this.modalDetalhesAberto = true;
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro', text: e.message });
            }
        },

        async trocarStatus(novoStatus) {
            if (!this.chamadoAtual) return;
            try {
                const res = await this.api('/chamados/atualizar-status', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        status: novoStatus,
                        observacao: 'Status alterado pelo painel'
                    }
                });
                if (res.success) {
                    await this.carregarDashboard();
                    await this.carregarChamados();
                    await this.abrirDetalhes(this.chamadoAtual.idchamado);
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro ao Mudar Status', text: e.message });
            }
        },

        async enviarInteracao() {
            if (!this.chamadoAtual || !this.novaInteracao.mensagem.trim()) return;

            try {
                const res = await this.api('/chamados/adicionar-interacao', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        mensagem: this.novaInteracao.mensagem,
                        tipo: this.novaInteracao.eh_resolucao ? 'resolucao' : this.novaInteracao.tipo,
                        eh_resolucao: this.novaInteracao.eh_resolucao,
                        versao_entrega: this.novaInteracao.versao_entrega
                    }
                });

                if (res.success) {
                    await this.carregarDashboard();
                    await this.carregarChamados();
                    await this.abrirDetalhes(this.chamadoAtual.idchamado);

                    Swal.fire({
                        icon: 'success',
                        title: 'Sucesso!',
                        text: res.message,
                        timer: 1800,
                        showConfirmButton: false
                    });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro ao Registrar', text: e.message });
            }
        }
    };
}

window.chamadosApp = chamadosApp;
