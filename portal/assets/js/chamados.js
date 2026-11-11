/**
 * Central de Chamados & Suporte Inteligente — Portal Nutricional
 * Gerenciamento reativo com Alpine.js, Kanban, Modo Lista, Filtro de Período,
 * Upload e Colagem de Prints (Ctrl+V) com Lightbox e Triagem de IA.
 */
function chamadosApp() {
    return {
        modoExibicao: 'kanban', // 'kanban' ou 'lista'
        carregando: false,
        analisandoIa: false,
        salvandoChamado: false,
        anexoUploadando: false,

        // Modais
        modalNovoAberto: false,
        modalDetalhesAberto: false,
        triagemFeita: false,

        // Lightbox de Prints/Anexos
        lightboxAberto: false,
        lightboxUrl: '',
        lightboxTitulo: '',

        // Dados
        kpis: { total: 0, abertos: 0, em_correcao: 0, criticos: 0, concluidos_mes: 0, taxa_resolucao: 0 },
        metricas: {
            por_sistema: [],
            por_criticidade: [],
            top_modulos: [],
            resolvidos_recentes: []
        },
        modulos: [],
        sistemas: [],
        usuario: null,
        chamados: [],
        chamadoAtual: null,
        timeline: [],

        // Filtros
        filtroPeriodoAtivo: 'todos', // 'hoje', '7dias', 'mes', 'todos'
        filtroAtendente: 'todos', // 'todos', 'mim', 'sem_atendente'
        filtros: {
            status: '',
            tipo_sistema: 'todos',
            modulo: 'todos',
            criticidade: 'todas',
            categoria: 'todas',
            busca: '',
            data_inicio: '',
            data_fim: ''
        },

        // Formulário de Novo Chamado
        novoForm: {
            tipo_sistema: 'PORTAL',
            subtipo_item: '',
            equipamento_detalhe: '',
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
            versao_entrega: 'v3.5',
            anexo_url: ''
        },

        async init() {
            await this.carregarDashboard();
            await this.carregarChamados();
        },

        // Chamada à API com suporte a multipart ou json
        async api(endpoint, options = {}) {
            const url = endpoint.startsWith('/') ? `/v1${endpoint}` : `/v1/${endpoint}`;
            const isFormData = options.body instanceof FormData;

            const opts = {
                ...options
            };

            if (!isFormData) {
                opts.headers = {
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                };
                if (opts.body && typeof opts.body === 'object') {
                    opts.body = JSON.stringify(opts.body);
                }
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
                    this.metricas = res.metricas || {
                        por_sistema: [],
                        por_criticidade: [],
                        top_modulos: [],
                        resolvidos_recentes: []
                    };
                    this.modulos = res.modulos || [];
                    this.sistemas = res.sistemas || [];
                    this.usuario = res.usuario || {};
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
                if (this.filtros.tipo_sistema !== 'todos') params.append('tipo_sistema', this.filtros.tipo_sistema);
                if (this.filtros.modulo !== 'todos') params.append('modulo', this.filtros.modulo);
                if (this.filtros.criticidade !== 'todas') params.append('criticidade', this.filtros.criticidade);
                if (this.filtros.categoria !== 'todas') params.append('categoria', this.filtros.categoria);
                if (this.filtros.busca) params.append('busca', this.filtros.busca);
                if (this.filtros.data_inicio) params.append('data_inicio', this.filtros.data_inicio);
                if (this.filtros.data_fim) params.append('data_fim', this.filtros.data_fim);
                if (this.filtroAtendente && this.filtroAtendente !== 'todos') params.append('atendente', this.filtroAtendente);

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

        definirPeriodo(tipo) {
            this.filtroPeriodoAtivo = tipo;
            const hoje = new Date();
            const pad = (n) => String(n).padStart(2, '0');
            const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

            if (tipo === 'hoje') {
                this.filtros.data_inicio = fmt(hoje);
                this.filtros.data_fim = fmt(hoje);
            } else if (tipo === '7dias') {
                const d7 = new Date();
                d7.setDate(hoje.getDate() - 7);
                this.filtros.data_inicio = fmt(d7);
                this.filtros.data_fim = fmt(hoje);
            } else if (tipo === 'mes') {
                const dInicio = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
                const dFim = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0);
                this.filtros.data_inicio = fmt(dInicio);
                this.filtros.data_fim = fmt(dFim);
            } else {
                this.filtros.data_inicio = '';
                this.filtros.data_fim = '';
            }
            this.carregarChamados();
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

        badgeSistema(sistemaId) {
            const sis = (this.sistemas || []).find(s => s.id === sistemaId);
            return sis ? sis.badge : 'bg-slate-100 text-slate-700 border-slate-200';
        },

        nomeSistema(sistemaId) {
            const sis = (this.sistemas || []).find(s => s.id === sistemaId);
            return sis ? sis.nome : sistemaId;
        },

        abrirModalNovo() {
            const sistemaPadrao = (this.sistemas || []).some(s => s.id === 'PORTAL') 
                ? 'PORTAL' 
                : ((this.sistemas && this.sistemas.length > 0) ? this.sistemas[0].id : 'PORTAL');

            const filialPadrao = (this.usuario && this.usuario.idfilial) ? Number(this.usuario.idfilial) : 1;

            this.novoForm = {
                tipo_sistema: sistemaPadrao,
                subtipo_item: '',
                equipamento_detalhe: '',
                relato: '',
                modulo_slug: '',
                idfilial: filialPadrao,
                titulo: '',
                categoria: 'bug',
                criticidade: 'media',
                diagnostico_ia: '',
                anexo_url: ''
            };
            this.triagemFeita = false;
            this.modalNovoAberto = true;
        },

        // Upload de Print Screen / Colar via Clipboard (Ctrl+V)
        tratarColagemPrint(e, destino = 'novo') {
            if (!e.clipboardData || !e.clipboardData.items) return;

            for (let i = 0; i < e.clipboardData.items.length; i++) {
                const item = e.clipboardData.items[i];
                if (item.type.indexOf('image') !== -1) {
                    const blob = item.getAsFile();
                    if (blob) {
                        e.preventDefault();
                        this.enviarBlobComoPrint(blob, destino);
                        return;
                    }
                }
            }
        },

        async enviarBlobComoPrint(blob, destino = 'novo') {
            this.anexoUploadando = true;
            try {
                const reader = new FileReader();
                reader.onload = async (event) => {
                    const base64Data = event.target.result;
                    const res = await this.api('/chamados/upload-anexo', {
                        method: 'POST',
                        body: { imagem_base64: base64Data }
                    });

                    if (res.success && res.url) {
                        if (destino === 'novo') {
                            this.novoForm.anexo_url = res.url;
                        } else {
                            this.novaInteracao.anexo_url = res.url;
                        }

                        if (typeof Swal !== 'undefined') {
                            const Toast = Swal.mixin({
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 2500,
                                timerProgressBar: true
                            });
                            Toast.fire({
                                icon: 'success',
                                title: '📸 Print screen anexado com sucesso!'
                            });
                        }
                    }
                    this.anexoUploadando = false;
                };
                reader.readAsDataURL(blob);
            } catch (err) {
                this.anexoUploadando = false;
                console.error('Erro ao enviar print:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Falha no anexo', text: err.message });
                }
            }
        },

        async selecionarArquivoAnexo(e, destino = 'novo') {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            this.anexoUploadando = true;
            try {
                const formData = new FormData();
                formData.append('anexo', file);

                const res = await this.api('/chamados/upload-anexo', {
                    method: 'POST',
                    body: formData
                });

                if (res.success && res.url) {
                    if (destino === 'novo') {
                        this.novoForm.anexo_url = res.url;
                    } else {
                        this.novaInteracao.anexo_url = res.url;
                    }

                    if (typeof Swal !== 'undefined') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2500,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'success',
                            title: 'Arquivo anexado com sucesso!'
                        });
                    }
                }
            } catch (err) {
                console.error('Erro no upload de arquivo:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Falha no upload', text: err.message });
                }
            } finally {
                this.anexoUploadando = false;
                e.target.value = '';
            }
        },

        removerAnexo(destino = 'novo') {
            if (destino === 'novo') {
                this.novoForm.anexo_url = '';
            } else {
                this.novaInteracao.anexo_url = '';
            }
        },

        abrirLightbox(url, titulo = 'Visualização do Anexo') {
            if (!url) return;
            this.lightboxUrl = url;
            this.lightboxTitulo = titulo;
            this.lightboxAberto = true;
        },

        fecharLightbox() {
            this.lightboxAberto = false;
            this.lightboxUrl = '';
            this.lightboxTitulo = '';
        },

        ehImagem(url) {
            if (!url) return false;
            return /\.(png|jpe?g|gif|webp)(\?.*)?$/i.test(url);
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
                        filial: this.novoForm.idfilial,
                        tipo_sistema: this.novoForm.tipo_sistema
                    }
                });

                if (res.success && res.sugestao) {
                    const s = res.sugestao;
                    this.novoForm.titulo = s.titulo;
                    if (s.tipo_sistema) this.novoForm.tipo_sistema = s.tipo_sistema;
                    if (s.subtipo_item) this.novoForm.subtipo_item = s.subtipo_item;
                    if (s.modulo_slug) this.novoForm.modulo_slug = s.modulo_slug;
                    this.novoForm.categoria = s.categoria;
                    this.novoForm.criticidade = s.criticidade;
                    this.novoForm.diagnostico_ia = s.diagnostico_ia;
                    this.triagemFeita = true;

                    Swal.fire({
                        icon: 'success',
                        title: 'IA Triagem Concluída!',
                        text: `Classificado para [${s.tipo_sistema || 'PORTAL'}] com criticidade [${s.criticidade}].`,
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
                        versao_entrega: 'v3.5',
                        anexo_url: ''
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

        async assumirChamado(id) {
            try {
                const res = await this.api('/chamados/assumir', {
                    method: 'POST',
                    body: { idchamado: id }
                });
                if (res.success) {
                    await this.carregarDashboard();
                    await this.carregarChamados();
                    if (this.modalDetalhesAberto && this.chamadoAtual && this.chamadoAtual.idchamado == id) {
                        await this.abrirDetalhes(id);
                    }
                    if (typeof Swal !== 'undefined') {
                        const Toast = Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 2500,
                            timerProgressBar: true
                        });
                        Toast.fire({
                            icon: 'success',
                            title: res.message || 'Chamado assumido com sucesso!'
                        });
                    }
                }
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Erro ao Assumir', text: e.message });
                }
            }
        },

        async enviarInteracao() {
            if (!this.chamadoAtual) return;
            if (!this.novaInteracao.mensagem.trim() && !this.novaInteracao.anexo_url) return;

            try {
                const res = await this.api('/chamados/adicionar-interacao', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        mensagem: this.novaInteracao.mensagem,
                        tipo: this.novaInteracao.eh_resolucao ? 'resolucao' : this.novaInteracao.tipo,
                        eh_resolucao: this.novaInteracao.eh_resolucao,
                        versao_entrega: this.novaInteracao.versao_entrega,
                        anexo_url: this.novaInteracao.anexo_url
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
        },

        copiarBriefingDevOps() {
            if (!this.chamadoAtual) return;
            const c = this.chamadoAtual;
            const texto = `Assumir Chamado #${c.idchamado} [${c.modulo_nome || c.tipo_sistema}].\n` +
                `Objetivo: ${c.titulo}\n` +
                `Relato / Necessidade: ${c.motivo || c.observacao}\n` +
                (c.diagnostico_ia ? `\nDossiê Técnico:\n${c.diagnostico_ia}\n` : '') +
                (c.anexo_url ? `\nAnexo/Print: ${c.anexo_url}\n` : '') +
                `\nPor favor, analise os arquivos do módulo, execute as alterações necessárias no código conforme o checklist de 5 passos do Plano Diretor, teste a sintaxe, comite e registre a entrega técnica diretamente na timeline deste chamado no banco.`;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(texto).then(() => {
                    Swal.fire({
                        icon: 'success',
                        title: 'Dossiê Técnico Copiado!',
                        text: 'Dossiê copiado para a área de transferência.',
                        timer: 2000,
                        showConfirmButton: false
                    });
                }).catch(() => {
                    prompt('Copie o dossiê técnico abaixo:', texto);
                });
            } else {
                prompt('Copie o dossiê técnico abaixo:', texto);
            }
        },

        copiarBriefingAntigravity() {
            return this.copiarBriefingDevOps();
        }
    };
}

window.chamadosApp = chamadosApp;
