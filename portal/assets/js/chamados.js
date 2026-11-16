/**
 * Central de Chamados & Suporte Inteligente — Portal Nutricional
 * Service Desk completo com Alpine.js, Kanban Drag-and-Drop (colunas e prioridade),
 * Abertura Expressa, Edição Total (título, texto, criticidade, prioridade, categoria, sistema, atendente),
 * Galeria de Prints/Anexos com Ctrl+V, Lightbox, Filtros Avançados e Notificações Integradas.
 */
function chamadosApp() {
    return {
        modoExibicao: 'kanban', // 'kanban' | 'lista' | 'dashboard'
        carregando: false,
        analisandoIa: false,
        salvandoChamado: false,
        salvandoEdicao: false,
        salvandoOrdem: false,
        anexoUploadando: false,

        // Modais & Painéis
        modalNovoAberto: false,
        modalDetalhesAberto: false,
        triagemFeita: false,
        painelAvisosAberto: false,
        editandoChamado: false,

        // Lightbox de Prints/Anexos
        lightboxAberto: false,
        lightboxUrl: '',
        lightboxTitulo: '',

        // Rastreamento no Dashboard
        usuarioRastreado: '',

        // Drag and Drop Kanban
        draggingId: null,
        dragOverStatus: null,
        dragOverCardId: null,

        // Dados
        kpis: { total: 0, abertos: 0, em_correcao: 0, criticos: 0, concluidos_mes: 0, concluidos_total: 0, taxa_resolucao: 0, tempo_medio_horas: 0 },
        metricas: {
            por_sistema: [],
            por_criticidade: [],
            top_modulos: [],
            resolvidos_recentes: [],
            ranking_solicitantes: [],
            por_status: [],
            por_categoria: [],
            performance_atendentes: []
        },
        modulos: [],
        sistemas: [],
        filiais: [],
        solicitantes: [],
        usuariosSolicitantes: [],
        atendentes: [],
        avisosRecentes: [],
        notificacoesNaoLidas: 0,
        usuario: null,
        chamados: [],
        chamadoAtual: null,
        timeline: [],
        anexos: [],
        timelineFiltro: 'todos',

        // Filtros
        filtroPeriodoAtivo: 'todos',
        filtroAtendente: 'todos',
        filtros: {
            status: '',
            tipo_sistema: 'todos',
            modulo: 'todos',
            criticidade: 'todas',
            categoria: 'todas',
            solicitante: 'todos',
            ordenacao: 'prioridade',
            busca: '',
            data_inicio: '',
            data_fim: ''
        },

        // Barra de Abertura Expressa (Quick Ticket)
        quickForm: {
            relato: '',
            tipo_sistema: 'PORTAL',
            criticidade: 'media',
            categoria: 'bug',
            solicitante: '',
            anexo_url: '',
            salvando: false
        },

        // Formulário Completo de Novo Chamado
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
            solicitante: '',
            diagnostico_ia: '',
            anexo_url: ''
        },

        // Formulário de Edição do Chamado no Modal
        editForm: {
            titulo: '',
            motivo: '',
            categoria: 'bug',
            tipo_sistema: 'PORTAL',
            subtipo_item: '',
            equipamento_detalhe: '',
            modulo_slug: '',
            modulo_nome: '',
            atendente: 'ALAN',
            solicitante: 'ALAN',
            criticidade: 'media',
            prioridade: 10
        },
        complementoTexto: '',

        // Nova Interação / Comentário
        novaInteracao: {
            mensagem: '',
            tipo: 'comentario',
            eh_resolucao: false,
            versao_entrega: 'v3.5',
            anexo_url: ''
        },

        async init() {
            await Promise.all([this.carregarDashboard(), this.carregarChamados()]);
            try {
                const params = new URLSearchParams(window.location.search);
                const idUrl = parseInt(params.get('id') || '0', 10);
                if (idUrl > 0) {
                    await this.abrirDetalhes(idUrl);
                }
            } catch (_) {}
        },

        toast(icon, title, timer = 2200) {
            if (typeof Swal === 'undefined') return;
            Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer,
                timerProgressBar: true
            }).fire({ icon, title });
        },

        async api(endpoint, options = {}) {
            const url = endpoint.startsWith('/') ? `/v1${endpoint}` : `/v1/${endpoint}`;
            const isFormData = options.body instanceof FormData;
            const opts = { ...options };

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
            } catch (_) {
                data = null;
            }
            if (!resp.ok) {
                throw new Error((data && (data.error || data.message)) || `Erro HTTP ${resp.status}`);
            }
            return data;
        },

        ehTiLiberado() {
            return Boolean(this.usuario && (this.usuario.pode_ver_todos || this.usuario.pode_gerenciar_ti || this.usuario.is_admin));
        },

        selecionarUsuarioRastreamento(username) {
            const alvo = String(username || '').toUpperCase().trim();
            if (!alvo) return;
            this.usuarioRastreado = (this.usuarioRastreado === alvo) ? '' : alvo;
        },

        chamadosDoUsuarioRastreado() {
            const alvo = String(this.usuarioRastreado || '').toUpperCase().trim();
            if (!alvo) return [];
            return (this.chamados || []).filter((c) => {
                const sol = String(c.solicitante || '').toUpperCase().trim();
                const usr = String(c.usuario || '').toUpperCase().trim();
                if (alvo === 'INTERNO') {
                    return sol === '.' || sol === '' || usr === '.';
                }
                return sol === alvo || usr === alvo;
            });
        },

        filtrarListaPorSolicitante(username, modo = 'lista') {
            const alvo = String(username || '').toUpperCase().trim();
            if (!alvo || alvo === 'INTERNO' || alvo === 'SISTEMA') {
                this.filtros.solicitante = 'todos';
            } else {
                this.filtros.solicitante = alvo;
            }
            this.modoExibicao = modo;
            this.carregarChamados();
        },

        labelCategoria(cat) {
            const mapa = {
                bug: '🐛 Bug / Erro',
                melhoria: '🚀 Melhoria / Ajuste',
                duvida: '❓ Dúvida / Orientação',
                permissao: '🔐 Permissão / Acesso',
                equipamento: '🖨️ Equipamento / Infra'
            };
            return mapa[String(cat || 'bug').toLowerCase()] || String(cat || 'Geral').toUpperCase();
        },

        async carregarDashboard() {
            try {
                const res = await this.api('/chamados/dashboard');
                if (res.success) {
                    this.kpis = res.kpis || {};
                    const met = res.metricas || {};
                    this.metricas = {
                        por_sistema: met.por_sistema || [],
                        por_criticidade: met.por_criticidade || [],
                        top_modulos: met.top_modulos || [],
                        resolvidos_recentes: met.resolvidos_recentes || [],
                        ranking_solicitantes: met.ranking_solicitantes || [],
                        por_status: met.por_status || [],
                        por_categoria: met.por_categoria || [],
                        performance_atendentes: met.performance_atendentes || []
                    };
                    this.modulos = res.modulos || [];
                    this.sistemas = res.sistemas || [];
                    this.filiais = res.filiais || [];
                    this.solicitantes = res.solicitantes || [];
                    this.usuariosSolicitantes = res.usuarios_solicitantes || res.solicitantes || [];
                    this.atendentes = res.atendentes || [];
                    this.avisosRecentes = res.avisos_recentes || [];
                    this.notificacoesNaoLidas = Number(res.notificacoes_nao_lidas || 0);
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
                if (this.filtros.solicitante && this.filtros.solicitante !== 'todos') params.append('solicitante', this.filtros.solicitante);
                if (this.filtros.ordenacao) params.append('ordenacao', this.filtros.ordenacao);
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

        limparFiltros() {
            this.filtroPeriodoAtivo = 'todos';
            this.filtroAtendente = 'todos';
            this.filtros = {
                status: '',
                tipo_sistema: 'todos',
                modulo: 'todos',
                criticidade: 'todas',
                categoria: 'todas',
                solicitante: 'todos',
                ordenacao: 'prioridade',
                busca: '',
                data_inicio: '',
                data_fim: ''
            };
            this.carregarChamados();
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
            return this.chamados
                .filter(c => parseInt(c.status, 10) === statusNum)
                .sort((a, b) => (Number(a.prioridade) || 10) - (Number(b.prioridade) || 10));
        },

        statusInfoMapa(st) {
            const mapa = {
                0: { label: 'Aberto', cor: 'bg-amber-100 text-amber-800 border-amber-300' },
                1: { label: 'Em Análise', cor: 'bg-blue-100 text-blue-800 border-blue-300' },
                2: { label: 'Em Correção', cor: 'bg-purple-100 text-purple-800 border-purple-300' },
                3: { label: 'Concluído', cor: 'bg-emerald-100 text-emerald-800 border-emerald-300' },
                4: { label: 'Cancelado', cor: 'bg-slate-100 text-slate-700 border-slate-300' }
            };
            return mapa[Number(st)] || { label: 'Desconhecido', cor: 'bg-gray-100 text-gray-700 border-gray-300' };
        },

        badgeCriticidade(crit) {
            switch (String(crit || 'media').toLowerCase()) {
                case 'critica':
                    return 'bg-rose-100 text-rose-700 border border-rose-300';
                case 'alta':
                    return 'bg-amber-100 text-amber-800 border border-amber-300';
                case 'media':
                    return 'bg-blue-100 text-blue-700 border border-blue-200';
                case 'baixa':
                default:
                    return 'bg-emerald-100 text-emerald-700 border border-emerald-200';
            }
        },

        bordaEsquerdaCriticidade(crit) {
            switch (String(crit || 'media').toLowerCase()) {
                case 'critica': return 'border-l-4 border-l-rose-500';
                case 'alta':    return 'border-l-4 border-l-amber-500';
                case 'media':   return 'border-l-4 border-l-blue-500';
                case 'baixa':
                default:        return 'border-l-4 border-l-emerald-500';
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

        // ======================== KANBAN DRAG & DROP ========================

        onDragStart(e, chamado) {
            if (!this.ehTiLiberado()) {
                e.preventDefault();
                return;
            }
            this.draggingId = Number(chamado.idchamado);
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(chamado.idchamado));
            }
        },

        onDragEnd() {
            this.draggingId = null;
            this.dragOverStatus = null;
            this.dragOverCardId = null;
        },

        onDragOverCol(e, statusNum) {
            if (!this.ehTiLiberado()) return;
            e.preventDefault();
            this.dragOverStatus = Number(statusNum);
        },

        onDragLeaveCol(e, statusNum) {
            if (e.currentTarget && !e.currentTarget.contains(e.relatedTarget) && this.dragOverStatus === Number(statusNum)) {
                this.dragOverStatus = null;
            }
        },

        onDragOverCard(e, targetCard) {
            if (!this.ehTiLiberado()) return;
            e.preventDefault();
            this.dragOverStatus = Number(targetCard.status);
            if (Number(targetCard.idchamado) !== this.draggingId) {
                this.dragOverCardId = Number(targetCard.idchamado);
            }
        },

        async onDropCol(e, targetStatus) {
            e.preventDefault();
            if (!this.ehTiLiberado()) {
                this.onDragEnd();
                return;
            }
            const draggedId = this.draggingId || parseInt(e.dataTransfer?.getData('text/plain') || '0', 10);
            this.onDragEnd();
            if (!draggedId) return;
            await this.aplicarReordenacaoKanban(draggedId, Number(targetStatus), null);
        },

        async onDropCard(e, targetCard) {
            e.preventDefault();
            if (!this.ehTiLiberado()) {
                this.onDragEnd();
                return;
            }
            const draggedId = this.draggingId || parseInt(e.dataTransfer?.getData('text/plain') || '0', 10);
            const targetId = Number(targetCard.idchamado);
            const targetStatus = Number(targetCard.status);
            this.onDragEnd();
            if (!draggedId || draggedId === targetId) return;
            await this.aplicarReordenacaoKanban(draggedId, targetStatus, targetId);
        },

        async aplicarReordenacaoKanban(draggedId, targetStatus, targetCardId = null) {
            if (!this.ehTiLiberado()) {
                this.toast('warning', 'Apenas o TI liberado pode alterar estágio ou prioridade.');
                return;
            }
            const dragged = this.chamados.find(c => Number(c.idchamado) === Number(draggedId));
            if (!dragged) return;

            const statusAnterior = Number(dragged.status);
            const colLista = this.colunaChamados(targetStatus).filter(c => Number(c.idchamado) !== Number(draggedId));

            dragged.status = targetStatus;
            dragged.status_info = this.statusInfoMapa(targetStatus);

            if (targetCardId) {
                const idx = colLista.findIndex(c => Number(c.idchamado) === Number(targetCardId));
                if (idx >= 0) {
                    colLista.splice(idx, 0, dragged);
                } else {
                    colLista.push(dragged);
                }
            } else {
                colLista.push(dragged);
            }

            colLista.forEach((item, index) => {
                item.prioridade = index + 1;
            });

            // Dispara reatividade do Alpine preservando os demais itens
            this.chamados = [...this.chamados];

            this.salvandoOrdem = true;
            try {
                const ordem = colLista.map((c, idx) => ({
                    idchamado: Number(c.idchamado),
                    status: Number(targetStatus),
                    prioridade: idx + 1
                }));
                const res = await this.api('/chamados/ordenar', {
                    method: 'POST',
                    body: { ordem }
                });
                if (res.success) {
                    const msg = statusAnterior !== targetStatus
                        ? `Chamado #${draggedId} movido para ${dragged.status_info.label}!`
                        : `Prioridade na fila atualizada!`;
                    this.toast('success', msg, 1800);
                    this.carregarDashboard();
                }
            } catch (err) {
                this.toast('error', err.message || 'Erro ao salvar ordem no Kanban');
                await this.carregarChamados();
            } finally {
                this.salvandoOrdem = false;
            }
        },

        // ======================== PRIORIDADE & CRITICIDADE ========================

        async moverParaTopo(chamado) {
            if (!this.ehTiLiberado()) {
                this.toast('warning', 'Apenas o TI liberado pode alterar a prioridade.');
                return;
            }
            try {
                const res = await this.api('/chamados/atualizar-prioridade', {
                    method: 'POST',
                    body: {
                        idchamado: chamado.idchamado,
                        topo: true,
                        observacao: 'Movido para o topo da prioridade (#1)'
                    }
                });
                if (res.success) {
                    this.toast('success', `Chamado #${chamado.idchamado} enviado para o topo (#1)!`);
                    await Promise.all([this.carregarDashboard(), this.carregarChamados()]);
                    if (this.modalDetalhesAberto && this.chamadoAtual?.idchamado == chamado.idchamado) {
                        await this.abrirDetalhes(chamado.idchamado);
                    }
                }
            } catch (e) {
                this.toast('error', e.message);
            }
        },

        async alterarCriticidadeRapida(chamado, novaCrit) {
            if (!this.ehTiLiberado()) {
                this.toast('warning', 'Apenas o TI liberado pode alterar a criticidade após a abertura.');
                return;
            }
            if (!chamado || chamado.criticidade === novaCrit) return;
            const anterior = chamado.criticidade;
            chamado.criticidade = novaCrit;
            try {
                const res = await this.api('/chamados/atualizar-prioridade', {
                    method: 'POST',
                    body: {
                        idchamado: chamado.idchamado,
                        criticidade: novaCrit,
                        observacao: `Criticidade alterada para ${String(novaCrit).toUpperCase()}`
                    }
                });
                if (res.success) {
                    this.toast('success', `Criticidade do #${chamado.idchamado}: ${novaCrit.toUpperCase()}`);
                    await Promise.all([this.carregarDashboard(), this.carregarChamados()]);
                    if (this.modalDetalhesAberto && this.chamadoAtual?.idchamado == chamado.idchamado) {
                        await this.abrirDetalhes(chamado.idchamado);
                    }
                }
            } catch (e) {
                chamado.criticidade = anterior;
                this.toast('error', e.message);
            }
        },

        async alterarPrioridadeModal(novaPrio) {
            if (!this.ehTiLiberado()) {
                this.toast('warning', 'Apenas o TI liberado pode alterar a prioridade.');
                return;
            }
            if (!this.chamadoAtual) return;
            const prioNum = parseInt(novaPrio, 10);
            if (!prioNum || prioNum <= 0) return;
            try {
                const res = await this.api('/chamados/atualizar-prioridade', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        prioridade: prioNum,
                        observacao: `Ordem na fila ajustada para #${prioNum}`
                    }
                });
                if (res.success) {
                    this.toast('success', `Prioridade ajustada para #${prioNum}`);
                    await Promise.all([
                        this.carregarDashboard(),
                        this.carregarChamados(),
                        this.abrirDetalhes(this.chamadoAtual.idchamado)
                    ]);
                }
            } catch (e) {
                this.toast('error', e.message);
            }
        },

        // ======================== ABERTURA EXPRESSA & MODAL NOVO ========================

        async criarChamadoRapido() {
            if (!this.quickForm.relato || this.quickForm.relato.trim().length < 4) {
                this.toast('warning', 'Descreva brevemente o chamado antes de enviar.');
                return;
            }
            this.quickForm.salvando = true;
            try {
                const filialPadrao = (this.filiais && this.filiais.length > 0)
                    ? Number(this.filiais[0].idfilial)
                    : ((this.usuario && this.usuario.idfilial) ? Number(this.usuario.idfilial) : 1);

                const res = await this.api('/chamados/criar', {
                    method: 'POST',
                    body: {
                        relato: this.quickForm.relato.trim(),
                        tipo_sistema: this.quickForm.tipo_sistema || 'PORTAL',
                        criticidade: this.quickForm.criticidade || 'media',
                        categoria: this.quickForm.categoria || 'bug',
                        solicitante: this.ehTiLiberado() ? (this.quickForm.solicitante || '') : '',
                        criticidade_manual: true,
                        idfilial: filialPadrao,
                        anexo_url: this.quickForm.anexo_url || ''
                    }
                });
                if (res.success) {
                    this.quickForm.relato = '';
                    this.quickForm.anexo_url = '';
                    this.quickForm.solicitante = '';
                    this.toast('success', res.message || 'Chamado aberto com sucesso!', 2800);
                    await Promise.all([this.carregarDashboard(), this.carregarChamados()]);
                }
            } catch (e) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Erro ao Abrir Chamado', text: e.message });
                }
            } finally {
                this.quickForm.salvando = false;
            }
        },

        abrirModalNovo(preencherDoQuick = false) {
            const sistemaPadrao = preencherDoQuick && this.quickForm.tipo_sistema
                ? this.quickForm.tipo_sistema
                : ((this.sistemas || []).some(s => s.id === 'PORTAL')
                    ? 'PORTAL'
                    : ((this.sistemas && this.sistemas.length > 0) ? this.sistemas[0].id : 'PORTAL'));

            const filialPadrao = (this.filiais && this.filiais.length > 0)
                ? Number(this.filiais[0].idfilial)
                : ((this.usuario && this.usuario.idfilial) ? Number(this.usuario.idfilial) : 1);

            const solicitantePadrao = (preencherDoQuick && this.quickForm.solicitante)
                ? this.quickForm.solicitante
                : ((this.usuario && this.usuario.username) ? this.usuario.username : 'ALAN');

            this.novoForm = {
                tipo_sistema: sistemaPadrao,
                subtipo_item: '',
                equipamento_detalhe: '',
                relato: preencherDoQuick ? (this.quickForm.relato || '') : '',
                modulo_slug: '',
                idfilial: filialPadrao,
                titulo: '',
                categoria: preencherDoQuick ? (this.quickForm.categoria || 'bug') : 'bug',
                criticidade: preencherDoQuick ? (this.quickForm.criticidade || 'media') : 'media',
                solicitante: solicitantePadrao,
                diagnostico_ia: '',
                anexo_url: preencherDoQuick ? (this.quickForm.anexo_url || '') : ''
            };
            this.triagemFeita = false;
            this.modalNovoAberto = true;
        },

        // ======================== UPLOADS & PRINTS (CTRL+V) ========================

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

        async atribuirUrlAnexo(destino, url, msgSucesso = 'Print anexado com sucesso!') {
            if (destino === 'novo') {
                this.novoForm.anexo_url = url;
            } else if (destino === 'quick') {
                this.quickForm.anexo_url = url;
            } else if ((destino === 'modal_anexo' || destino === 'edicao') && this.chamadoAtual) {
                await this.registrarAnexoDiretoNoChamado(url);
                return;
            } else {
                this.novaInteracao.anexo_url = url;
            }
            this.toast('success', msgSucesso);
        },

        async registrarAnexoDiretoNoChamado(url) {
            if (!this.chamadoAtual || !url) return;
            const res = await this.api('/chamados/adicionar-interacao', {
                method: 'POST',
                body: {
                    idchamado: this.chamadoAtual.idchamado,
                    mensagem: 'Print / evidência anexado diretamente ao chamado.',
                    tipo: 'evidencia',
                    anexo_url: url
                }
            });
            if (res.success) {
                this.toast('success', '📸 Print adicionado à galeria do chamado!');
                await Promise.all([
                    this.abrirDetalhes(this.chamadoAtual.idchamado),
                    this.carregarChamados(),
                    this.carregarDashboard()
                ]);
            }
        },

        async enviarBlobComoPrint(blob, destino = 'novo') {
            this.anexoUploadando = true;
            try {
                const base64Data = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = (ev) => resolve(ev.target.result);
                    reader.onerror = () => reject(new Error('Falha ao ler imagem colada.'));
                    reader.readAsDataURL(blob);
                });
                const res = await this.api('/chamados/upload-anexo', {
                    method: 'POST',
                    body: { imagem_base64: base64Data, arquivo_base64: base64Data }
                });
                if (res.success && res.url) {
                    await this.atribuirUrlAnexo(destino, res.url, '📸 Print screen anexado com sucesso!');
                } else {
                    throw new Error(res?.error || 'Não foi possível processar o print.');
                }
            } catch (err) {
                console.error('Erro ao enviar print:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Falha no anexo', text: err.message });
                }
            } finally {
                this.anexoUploadando = false;
            }
        },

        async selecionarArquivoAnexo(e, destino = 'novo') {
            const file = e.target.files && e.target.files[0];
            if (!file) return;

            if (file.size > 15 * 1024 * 1024) {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'warning', title: 'Arquivo muito grande', text: 'O limite máximo permitido é 15MB.' });
                }
                e.target.value = '';
                return;
            }

            this.anexoUploadando = true;
            try {
                const base64Data = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = (ev) => resolve(ev.target.result);
                    reader.onerror = () => reject(new Error('Não foi possível ler o arquivo selecionado.'));
                    reader.readAsDataURL(file);
                });

                const res = await this.api('/chamados/upload-anexo', {
                    method: 'POST',
                    body: {
                        arquivo_base64: base64Data,
                        imagem_base64: base64Data,
                        nome_original: file.name
                    }
                });
                if (res.success && res.url) {
                    await this.atribuirUrlAnexo(destino, res.url, 'Arquivo anexado com sucesso!');
                } else {
                    throw new Error(res?.error || 'Falha no retorno do servidor ao salvar o arquivo.');
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
            } else if (destino === 'quick') {
                this.quickForm.anexo_url = '';
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

        // ======================== IA & CRIAÇÃO COMPLETA ========================

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
                    body: {
                        ...this.novoForm,
                        criticidade_manual: true
                    }
                });

                if (res.success) {
                    this.modalNovoAberto = false;
                    await Promise.all([this.carregarDashboard(), this.carregarChamados()]);

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

        // ======================== DETALHES & EDIÇÃO TOTAL ========================

        async abrirDetalhes(id) {
            try {
                const res = await this.api(`/chamados/detalhes/${id}`);
                if (res.success) {
                    this.chamadoAtual = res.chamado;
                    this.timeline = res.timeline || [];
                    this.anexos = res.anexos || [];
                    this.editandoChamado = false;
                    this.complementoTexto = '';
                    this.timelineFiltro = 'todos';
                    this.novaInteracao = {
                        mensagem: '',
                        tipo: 'comentario',
                        eh_resolucao: false,
                        versao_entrega: 'v3.5',
                        anexo_url: ''
                    };
                    this.modalDetalhesAberto = true;

                    this.avisosRecentes = this.avisosRecentes.filter(a => Number(a.idchamado) !== Number(id));
                    this.notificacoesNaoLidas = this.avisosRecentes.length;

                    if (window.PortalNotificacoes && typeof window.PortalNotificacoes.atualizarAgora === 'function') {
                        window.PortalNotificacoes.atualizarAgora();
                    }
                    this.carregarDashboard();
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro', text: e.message });
            }
        },

        async limparNotificacoesChamados(idnotificacao = null, idchamado = null) {
            const limparTodas = !idnotificacao && !idchamado;
            const prevAvisos = [...this.avisosRecentes];
            const prevNaoLidas = this.notificacoesNaoLidas;

            if (limparTodas) {
                this.avisosRecentes = [];
                this.notificacoesNaoLidas = 0;
            } else {
                this.avisosRecentes = this.avisosRecentes.filter((a) => {
                    if (idnotificacao && Number(a.idnotificacao) === Number(idnotificacao)) return false;
                    if (idchamado && !idnotificacao && Number(a.idchamado) === Number(idchamado)) return false;
                    return true;
                });
                this.notificacoesNaoLidas = this.avisosRecentes.length;
            }

            try {
                const res = await this.api('/chamados/limpar-notificacoes', {
                    method: 'POST',
                    body: {
                        idnotificacao: idnotificacao ? Number(idnotificacao) : 0,
                        idchamado: idchamado ? Number(idchamado) : 0
                    }
                });
                if (res.success) {
                    if (limparTodas) {
                        this.toast('success', 'Notificações limpas!');
                    }
                    if (window.PortalNotificacoes && typeof window.PortalNotificacoes.atualizarAgora === 'function') {
                        window.PortalNotificacoes.atualizarAgora();
                    }
                    await this.carregarDashboard();
                }
            } catch (e) {
                this.avisosRecentes = prevAvisos;
                this.notificacoesNaoLidas = prevNaoLidas;
                this.toast('error', e.message || 'Erro ao limpar notificações.');
            }
        },

        iniciarEdicaoChamado() {
            if (!this.chamadoAtual) return;
            const c = this.chamadoAtual;
            this.editForm = {
                titulo: c.titulo || '',
                motivo: c.motivo || c.observacao || '',
                categoria: c.categoria || 'bug',
                tipo_sistema: c.tipo_sistema || 'PORTAL',
                subtipo_item: c.subtipo_item || '',
                equipamento_detalhe: c.equipamento_detalhe || '',
                modulo_slug: c.modulo_slug || '',
                modulo_nome: c.modulo_nome || '',
                atendente: c.atendente || 'ALAN',
                solicitante: c.solicitante || 'ALAN',
                criticidade: c.criticidade || 'media',
                prioridade: parseInt(c.prioridade || 10, 10)
            };
            this.complementoTexto = '';
            this.editandoChamado = true;
        },

        async alterarSolicitanteRapido(novoSolicitante) {
            if (!this.ehTiLiberado() || !this.chamadoAtual) return;
            const alvo = String(novoSolicitante || '').toUpperCase().trim();
            const atual = String(this.chamadoAtual.solicitante || '').toUpperCase().trim();
            if (!alvo || alvo === atual) return;

            try {
                const res = await this.api('/chamados/editar', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        solicitante: alvo
                    }
                });
                if (res.success) {
                    this.toast('success', `Solicitante alterado para ${alvo}!`);
                    await Promise.all([
                        this.carregarDashboard(),
                        this.carregarChamados(),
                        this.abrirDetalhes(this.chamadoAtual.idchamado)
                    ]);
                }
            } catch (e) {
                this.toast('error', e.message || 'Erro ao alterar solicitante.');
            }
        },

        adicionarComplementoAoTexto() {
            const comp = (this.complementoTexto || '').trim();
            if (!comp) return;
            const atual = (this.editForm.motivo || '').trim();
            const agora = new Date();
            const dataFmt = agora.toLocaleDateString('pt-BR') + ' ' + agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            this.editForm.motivo = atual
                ? `${atual}\n\n[Complemento em ${dataFmt}]: ${comp}`
                : comp;
            this.complementoTexto = '';
            this.toast('info', 'Complemento inserido no texto. Clique em Salvar para confirmar.');
        },

        async salvarEdicaoChamado() {
            if (!this.chamadoAtual) return;
            if (!this.editForm.titulo || this.editForm.titulo.trim().length < 3) {
                this.toast('warning', 'Informe um título válido para o chamado.');
                return;
            }
            if (!this.editForm.motivo || this.editForm.motivo.trim().length < 3) {
                this.toast('warning', 'Informe a descrição/texto do chamado.');
                return;
            }
            this.salvandoEdicao = true;
            try {
                const payload = this.ehTiLiberado()
                    ? { idchamado: this.chamadoAtual.idchamado, ...this.editForm }
                    : {
                        idchamado: this.chamadoAtual.idchamado,
                        titulo: this.editForm.titulo,
                        motivo: this.editForm.motivo
                    };
                const res = await this.api('/chamados/editar', {
                    method: 'POST',
                    body: payload
                });
                if (res.success) {
                    this.editandoChamado = false;
                    this.toast('success', res.message || 'Chamado atualizado com sucesso!');
                    await Promise.all([
                        this.carregarDashboard(),
                        this.carregarChamados(),
                        this.abrirDetalhes(this.chamadoAtual.idchamado)
                    ]);
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro ao Salvar Edição', text: e.message });
            } finally {
                this.salvandoEdicao = false;
            }
        },

        timelineFiltrada() {
            if (this.timelineFiltro === 'comentarios') {
                return (this.timeline || []).filter(op => ['comentario', 'evidencia', 'duvida', 'progresso', 'resolucao'].includes(op.tipo));
            }
            if (this.timelineFiltro === 'mudancas') {
                return (this.timeline || []).filter(op => ['abertura', 'atendimento', 'mudanca_status', 'mudanca_prioridade', 'edicao', 'ordem_drag'].includes(op.tipo));
            }
            return this.timeline || [];
        },

        async trocarStatus(novoStatus) {
            if (!this.chamadoAtual) return;
            if (!this.ehTiLiberado()) {
                this.toast('warning', 'Apenas o TI liberado pode alterar o estágio do chamado.');
                return;
            }
            try {
                const res = await this.api('/chamados/atualizar-status', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        status: Number(novoStatus),
                        observacao: 'Status alterado pelo painel'
                    }
                });
                if (res.success) {
                    this.toast('success', res.message || 'Status atualizado!');
                    await Promise.all([
                        this.carregarDashboard(),
                        this.carregarChamados(),
                        this.abrirDetalhes(this.chamadoAtual.idchamado)
                    ]);
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro ao Mudar Status', text: e.message });
            }
        },

        async assumirChamado(id) {
            if (!this.ehTiLiberado()) {
                this.toast('warning', 'Apenas membros da equipe técnica podem assumir chamados.');
                return;
            }
            try {
                const res = await this.api('/chamados/assumir', {
                    method: 'POST',
                    body: { idchamado: id }
                });
                if (res.success) {
                    this.toast('success', res.message || 'Chamado assumido com sucesso!', 2500);
                    await Promise.all([this.carregarDashboard(), this.carregarChamados()]);
                    if (this.modalDetalhesAberto && this.chamadoAtual && this.chamadoAtual.idchamado == id) {
                        await this.abrirDetalhes(id);
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

            const ehTi = this.ehTiLiberado();
            const tipoPermitido = ehTi
                ? (this.novaInteracao.eh_resolucao ? 'resolucao' : this.novaInteracao.tipo)
                : (['comentario', 'evidencia', 'duvida'].includes(this.novaInteracao.tipo) ? this.novaInteracao.tipo : 'comentario');

            try {
                const res = await this.api('/chamados/adicionar-interacao', {
                    method: 'POST',
                    body: {
                        idchamado: this.chamadoAtual.idchamado,
                        mensagem: this.novaInteracao.mensagem,
                        tipo: tipoPermitido,
                        eh_resolucao: ehTi ? Boolean(this.novaInteracao.eh_resolucao) : false,
                        versao_entrega: ehTi ? this.novaInteracao.versao_entrega : '',
                        anexo_url: this.novaInteracao.anexo_url
                    }
                });

                if (res.success) {
                    this.toast('success', res.message || 'Interação registrada!', 2000);
                    await Promise.all([
                        this.carregarDashboard(),
                        this.carregarChamados(),
                        this.abrirDetalhes(this.chamadoAtual.idchamado)
                    ]);
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
                    this.toast('success', 'Dossiê Técnico Copiado!');
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
