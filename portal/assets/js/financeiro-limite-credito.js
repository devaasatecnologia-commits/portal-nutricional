/**
 * Módulo Financeiro — Gestão e Recálculo Automático de Limite de Crédito
 * Migração definitiva do Procedimento #6 do Portal DOX (Ema Studio).
 * Reativo com Alpine.js, Tailwind CSS e SweetAlert2.
 */
function limiteCreditoApp() {
    return {
        carregando: false,
        aplicando: false,
        enviandoEmail: false,
        carregandoPreview: false,

        // Modal de E-mail
        modalEmailAberto: false,
        emailDestino: 'financeiro@nutricionalbr.com',
        emailAssunto: 'Relatório de Alterações de Limite de Crédito - Portal Nutricional',
        emailHtmlPreview: '',

        // KPIs
        kpis: {
            total_analisados: 0,
            total_com_alteracao: 0,
            total_aumentos: 0,
            total_reducoes: 0,
            impacto_liquido: 0,
            impacto_aumentos: 0,
            impacto_reducoes: 0
        },

        // Filtros
        filtros: {
            busca: '',
            statuspagto: 'todos',
            tipo_alteracao: 'todos', // todos, aumento, reducao, sem_alteracao
            apenas_diferencas: true
        },

        // Paginação
        paginacao: {
            page: 1,
            limit: 50,
            total: 0,
            total_paginas: 1
        },

        itens: [],
        selecionados: [],
        selecionarTodos: false,

        async init() {
            await this.carregarDados();
        },

        async api(endpoint, options = {}) {
            const url = endpoint.startsWith('/') ? `/v1${endpoint}` : `/v1/${endpoint}`;
            const opts = {
                headers: {
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                },
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

        async carregarDados(pagina = 1) {
            this.carregando = true;
            this.paginacao.page = pagina;
            try {
                const params = new URLSearchParams({
                    page: this.paginacao.page,
                    limit: this.paginacao.limit,
                    busca: this.filtros.busca,
                    statuspagto: this.filtros.statuspagto,
                    tipo_alteracao: this.filtros.tipo_alteracao,
                    apenas_diferencas: this.filtros.apenas_diferencas ? '1' : '0'
                });

                const res = await this.api(`/financeiro/limite-credito/analise?${params.toString()}`);
                if (res.success) {
                    this.kpis = res.kpis || this.kpis;
                    this.paginacao = res.paginacao || this.paginacao;
                    this.itens = res.itens || [];
                    this.selecionarTodos = false;
                    this.selecionados = [];
                }
            } catch (e) {
                console.error('Erro ao listar análise de crédito:', e);
                Swal.fire({
                    icon: 'error',
                    title: 'Falha ao Carregar Análise',
                    text: e.message
                });
            } finally {
                this.carregando = false;
            }
        },

        toggleSelecionarTodos() {
            if (this.selecionarTodos) {
                this.selecionados = this.itens.map(i => parseInt(i.cdcliente, 10));
            } else {
                this.selecionados = [];
            }
        },

        toggleItem(id) {
            const numId = parseInt(id, 10);
            const idx = this.selecionados.indexOf(numId);
            if (idx > -1) {
                this.selecionados.splice(idx, 1);
                this.selecionarTodos = false;
            } else {
                this.selecionados.push(numId);
                if (this.selecionados.length === this.itens.length) {
                    this.selecionarTodos = true;
                }
            }
        },

        async abrirModalEmail() {
            this.modalEmailAberto = true;
            this.carregandoPreview = true;
            try {
                const res = await this.api('/financeiro/limite-credito/preview-email');
                if (res.success) {
                    this.emailHtmlPreview = res.html || '';
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro', text: 'Não foi possível carregar a pré-visualização: ' + e.message });
            } finally {
                this.carregandoPreview = false;
            }
        },

        async dispararEmail() {
            if (!this.emailDestino.trim()) {
                Swal.fire({ icon: 'warning', title: 'Destinatário Obrigatório', text: 'Informe um e-mail válido para envio.' });
                return;
            }

            this.enviandoEmail = true;
            try {
                const res = await this.api('/financeiro/limite-credito/enviar-email', {
                    method: 'POST',
                    body: {
                        email_destino: this.emailDestino,
                        assunto: this.emailAssunto
                    }
                });

                if (res.success) {
                    this.modalEmailAberto = false;
                    Swal.fire({
                        icon: 'success',
                        title: 'E-mail Enviado!',
                        text: res.message || 'Relatório transmitido com sucesso ao departamento financeiro.'
                    });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro no Envio', text: e.message });
            } finally {
                this.enviandoEmail = false;
            }
        },

        copiarHtmlEmail() {
            if (!this.emailHtmlPreview) return;
            navigator.clipboard.writeText(this.emailHtmlPreview).then(() => {
                Swal.fire({
                    icon: 'success',
                    title: 'HTML Copiado!',
                    text: 'O código HTML do relatório foi copiado para a área de transferência.',
                    timer: 1800,
                    showConfirmButton: false
                });
            });
        },

        async aplicarLimites(modo) {
            const totalAlvo = modo === 'selecionados' ? this.selecionados.length : this.kpis.total_com_alteracao;

            if (modo === 'selecionados' && totalAlvo === 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Nenhum Cliente Selecionado',
                    text: 'Marque pelo menos um cliente na tabela para aplicar a alteração.'
                });
                return;
            }

            const textoConfirm = modo === 'selecionados'
                ? `Você está prestes a atualizar o Limite de Crédito de ${totalAlvo} cliente(s) selecionado(s) diretamente no banco do ERP EMA.`
                : `ATENÇÃO: Você está prestes a recalcular e atualizar em lote o Limite de Crédito de TODOS os ${totalAlvo} clientes com sugestão de alteração no ERP EMA.`;

            const result = await Swal.fire({
                title: 'Confirmar Atualização no ERP?',
                text: textoConfirm,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: `<i class="fa-solid fa-check"></i> Sim, Atualizar no ERP`,
                cancelButtonText: 'Cancelar'
            });

            if (!result.isConfirmed) return;

            this.aplicando = true;
            try {
                const res = await this.api('/financeiro/limite-credito/aplicar', {
                    method: 'POST',
                    body: {
                        modo: modo,
                        clientes_ids: this.selecionados
                    }
                });

                if (res.success) {
                    await Swal.fire({
                        icon: 'success',
                        title: 'Limites Atualizados!',
                        text: res.message || `${res.total_atualizados} clientes atualizados no ERP com sucesso!`
                    });
                    await this.carregarDados(this.paginacao.page);
                }
            } catch (e) {
                Swal.fire({ icon: 'error', title: 'Erro ao Aplicar Limites', text: e.message });
            } finally {
                this.aplicando = false;
            }
        },

        // Helpers de Estilo e Formatação
        formatarMoeda(val) {
            const num = parseFloat(val) || 0;
            return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        },

        badgeStatus(status) {
            const st = String(status || '').toUpperCase();
            if (st.includes('EXCELENTE') || st === 'BOM') {
                return 'bg-emerald-50 text-emerald-700 border-emerald-200';
            }
            if (st.includes('REGULAR')) {
                return 'bg-amber-50 text-amber-700 border-amber-200';
            }
            if (st.includes('RUIM')) {
                return 'bg-rose-50 text-rose-700 border-rose-200';
            }
            return 'bg-slate-50 text-slate-700 border-slate-200';
        },

        badgeDiferenca(diff) {
            const num = parseFloat(diff) || 0;
            if (num > 0.99) {
                return 'text-emerald-600 font-black';
            }
            if (num < -0.99) {
                return 'text-rose-600 font-black';
            }
            return 'text-slate-400 font-medium';
        }
    };
}

window.limiteCreditoApp = limiteCreditoApp;
