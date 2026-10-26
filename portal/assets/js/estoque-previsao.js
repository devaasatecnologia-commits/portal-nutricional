// ==========================================================================
// MÓDULO ESTOQUE COM PREVISÃO — VISÃO ESPECIALIZADA UNIFICADA (MATRIZ + FILIAL PR)
// ==========================================================================

const estoquePrevisaoApp = {
    currentFilial: 0, // 0 = Unificado (Matriz 1 + Filial PR 6)
    currentPage: 1,
    currentLimit: 50,
    currentOrder: 'produto',
    currentOrderDir: 'ASC',
    currentSearch: '',
    currentMarca: '',
    currentStatus: '',
    totalPages: 0,
    chartDetalhe: null,
    debounceTimer: null,
    modoExibicao: localStorage.getItem('ep_modo_exibicao') || 'peso', // 'peso' ou 'unidade'
    kpiFiltroAtivo: null, // 'fisico', 'reservado', 'livre', 'previsao', 'futuro', 'critico'
    resumoData: null,
    produtosData: [],

    // ==========================================================================
    // FORMATAÇÃO DE URL DE IMAGEM VIA CDN
    // ==========================================================================
    formatarUrlImagem(path_foto_master) {
        if (!path_foto_master) return null;
        const normalized = String(path_foto_master).trim().replace(/\\/g, '/');
        if (!normalized || ['.', '-', 'null', 'undefined'].includes(normalized.toLowerCase())) return null;
        if (/^https?:\/\//i.test(normalized)) return normalized.replace(/ /g, '%20');

        const marker = 'Fotos para o Site/';
        const markerIndex = normalized.toLowerCase().indexOf(marker.toLowerCase());
        if (markerIndex < 0) return null;

        const relativePath = normalized.substring(markerIndex + marker.length)
            .split('/').map((segment) => encodeURIComponent(segment)).join('/');
        return relativePath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + relativePath : null;
    },

    formatNumber(n) {
        return Number(n || 0).toLocaleString('pt-BR', { maximumFractionDigits: 2 });
    },

    formatarPeso(kg) {
        const val = Number(kg || 0);
        if (Math.abs(val) >= 1000) {
            return (val / 1000).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' t';
        }
        return val.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 1 }) + ' kg';
    },

    formatarPesoExtenso(kg) {
        const val = Number(kg || 0);
        return val.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' kg';
    },

    setModoExibicao(modo) {
        this.modoExibicao = modo;
        try {
            localStorage.setItem('ep_modo_exibicao', modo);
        } catch (e) {}
        this.atualizarBotoesModo();
        this.renderResumo();
        this.renderTabela(this.produtosData);
    },

    atualizarBotoesModo() {
        const btnPeso = document.getElementById('btn-modo-peso');
        const btnUnidade = document.getElementById('btn-modo-unidade');
        if (!btnPeso || !btnUnidade) return;

        if (this.modoExibicao === 'peso') {
            btnPeso.className = 'py-1 px-3 rounded-lg bg-emerald-600 text-white shadow-sm transition flex items-center gap-1.5';
            btnUnidade.className = 'py-1 px-3 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1.5';
        } else {
            btnUnidade.className = 'py-1 px-3 rounded-lg bg-emerald-600 text-white shadow-sm transition flex items-center gap-1.5';
            btnPeso.className = 'py-1 px-3 rounded-lg text-slate-600 hover:text-slate-900 transition flex items-center gap-1.5';
        }
    },

    escapeHtml(text) {
        return String(text ?? '').replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    },

    // ==========================================================================
    // COMUNICAÇÃO COM A API REST
    // ==========================================================================
    async api(endpoint, body = null, method = 'POST') {
        const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
        let url = `${apiRoot}/estoque-previsao/${endpoint.replace(/^\/+/, '')}`;
        const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');

        const headers = { 'Accept': 'application/json' };
        if (token) headers['Authorization'] = 'Bearer ' + token;

        const options = { method, headers, credentials: 'include' };

        if (method === 'GET' && body) {
            url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();
        } else if (body) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        const resp = await fetch(url, options);
        if (resp.status === 401) {
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada. Faça login novamente.');
        }

        const text = await resp.text();
        let data = {};
        try {
            data = text ? JSON.parse(text) : {};
        } catch {
            data = { error: text || 'Resposta inválida do servidor' };
        }
        if (!resp.ok) throw new Error(data.error || `Erro HTTP ${resp.status}`);
        return data;
    },

    // ==========================================================================
    // INICIALIZAÇÃO DO MÓDULO & TOOLTIPS MODERNOS
    // ==========================================================================
    async init() {
        this.iniciarRelogio();
        this.atualizarBotoesModo();
        this.initNutriTooltips();
        this.bindEvents();
        await this.carregarFiliais();
        await this.carregarTudo();
    },

    initNutriTooltips() {
        let tooltipEl = document.getElementById('nutri-tooltip');
        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.id = 'nutri-tooltip';
            document.body.appendChild(tooltipEl);
        }

        // Converte titles existentes para data-tooltip para evitar o balão nativo feio do navegador
        const converterTitles = () => {
            document.querySelectorAll('[title]').forEach((el) => {
                const txt = el.getAttribute('title');
                if (txt && !el.hasAttribute('data-tooltip')) {
                    el.setAttribute('data-tooltip', txt);
                    el.removeAttribute('title');
                }
            });
        };
        converterTitles();

        document.addEventListener('mouseover', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (!target) return;
            const text = target.getAttribute('data-tooltip');
            if (!text) return;

            tooltipEl.textContent = text;
            tooltipEl.className = 'nutri-tooltip-visible arrow-bottom';

            const rect = target.getBoundingClientRect();
            const tipRect = tooltipEl.getBoundingClientRect();

            let top = rect.top - tipRect.height - 8;
            let arrowClass = 'arrow-bottom';

            if (top < 10) {
                // Se estiver muito perto do topo, posiciona logo abaixo do elemento
                top = rect.bottom + 8;
                arrowClass = 'arrow-top';
            }

            let left = rect.left + (rect.width / 2) - (tipRect.width / 2);
            if (left < 10) left = 10;
            if (left + tipRect.width > window.innerWidth - 10) {
                left = window.innerWidth - tipRect.width - 10;
            }

            tooltipEl.style.top = `${top}px`;
            tooltipEl.style.left = `${left}px`;
            tooltipEl.className = `nutri-tooltip-visible ${arrowClass}`;
        });

        document.addEventListener('mouseout', (e) => {
            const target = e.target.closest('[data-tooltip]');
            if (target && tooltipEl) {
                tooltipEl.classList.remove('nutri-tooltip-visible');
            }
        });
    },

    // ==========================================================================
    // CARDS RASTREÁVEIS (FILTRO INTERATIVO POR CLIQUE NO CARD)
    // ==========================================================================
    clicarCardKpi(tipo) {
        if (this.kpiFiltroAtivo === tipo) {
            // Se o usuário clicar de novo no card ativo, desliga o filtro (toggle)
            this.kpiFiltroAtivo = null;
            this.currentStatus = '';
            const selStatus = document.getElementById('filter-status');
            if (selStatus) selStatus.value = '';
            if (tipo === 'futuro') {
                this.currentOrder = 'produto';
                this.currentOrderDir = 'ASC';
            }
        } else {
            this.kpiFiltroAtivo = tipo;
            const selStatus = document.getElementById('filter-status');

            if (tipo === 'fisico') {
                // Estoque Físico: mostra produtos com saldo físico > 0
                this.currentStatus = 'fisico';
                if (selStatus) selStatus.value = '';
            } else if (tipo === 'reservado') {
                // Reservado: filtra SKUs com pedidos em carteira
                this.currentStatus = 'reservado';
                if (selStatus) selStatus.value = 'reservado';
            } else if (tipo === 'livre') {
                // Livre: filtra SKUs com saldo adequado livre
                this.currentStatus = 'ok';
                if (selStatus) selStatus.value = 'ok';
            } else if (tipo === 'previsao') {
                // Previsão: filtra SKUs com OCs a caminho
                this.currentStatus = 'previsao';
                if (selStatus) selStatus.value = 'previsao';
            } else if (tipo === 'futuro') {
                // Estoque Futuro: ordena a tabela pelos maiores estoques futuros
                this.currentOrder = 'futuro';
                this.currentOrderDir = 'DESC';
                this.currentStatus = '';
                if (selStatus) selStatus.value = '';
            } else if (tipo === 'critico') {
                // Críticos/Zerados: filtra SKUs <= 10 un
                this.currentStatus = 'critico_zerado';
                if (selStatus) selStatus.value = 'critico_zerado';
            }
        }

        this.atualizarDestaqueCardsKpi();
        this.currentPage = 1;
        this.carregarProdutos();
    },

    atualizarDestaqueCardsKpi() {
        const mapaCards = {
            'fisico': document.getElementById('kpi-card-fisico'),
            'reservado': document.getElementById('kpi-card-reservado'),
            'livre': document.getElementById('kpi-card-livre'),
            'previsao': document.getElementById('kpi-card-previsao'),
            'futuro': document.getElementById('kpi-card-futuro'),
            'critico': document.getElementById('kpi-card-critico'),
        };

        const classesDestaque = ['ring-2', 'ring-emerald-500', 'shadow-lg', 'scale-[1.03]', 'border-emerald-500', 'bg-white'];

        Object.entries(mapaCards).forEach(([k, card]) => {
            if (!card) return;
            const badge = card.querySelector('.kpi-filtro-badge');
            if (this.kpiFiltroAtivo === k) {
                card.classList.add(...classesDestaque);
                if (!badge) {
                    const b = document.createElement('span');
                    b.className = 'kpi-filtro-badge absolute top-2 right-2 text-[9px] font-black uppercase tracking-wider text-emerald-800 bg-emerald-100 border border-emerald-300 px-1.5 py-0.5 rounded-md flex items-center gap-1 shadow-sm';
                    b.innerHTML = '<i class="fa-solid fa-filter text-[8px]"></i> Filtrando';
                    card.appendChild(b);
                }
            } else {
                card.classList.remove(...classesDestaque);
                if (badge) badge.remove();
            }
        });
    },

    bindEvents() {
        const selFilial = document.getElementById('select-filial');
        if (selFilial) {
            selFilial.addEventListener('change', () => {
                this.currentFilial = Number(selFilial.value);
                this.atualizarBadgeUnificado();
                this.currentPage = 1;
                this.carregarTudo();
            });
        }

        const searchInput = document.getElementById('search-produto');
        const btnLimparBusca = document.getElementById('btn-limpar-busca');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                clearTimeout(this.debounceTimer);
                if (btnLimparBusca) btnLimparBusca.classList.toggle('hidden', !searchInput.value);
                this.debounceTimer = setTimeout(() => {
                    this.currentSearch = searchInput.value.trim();
                    this.currentPage = 1;
                    this.carregarProdutos();
                }, 300);
            });
        }

        const selMarca = document.getElementById('filter-marca');
        if (selMarca) {
            selMarca.addEventListener('change', () => {
                this.currentMarca = selMarca.value;
                this.currentPage = 1;
                this.carregarProdutos();
            });
        }

        const selStatus = document.getElementById('filter-status');
        if (selStatus) {
            selStatus.addEventListener('change', () => {
                this.currentStatus = selStatus.value;
                if (this.currentStatus === 'previsao') this.kpiFiltroAtivo = 'previsao';
                else if (this.currentStatus === 'reservado') this.kpiFiltroAtivo = 'reservado';
                else if (this.currentStatus === 'critico_zerado') this.kpiFiltroAtivo = 'critico';
                else if (this.currentStatus === 'ok') this.kpiFiltroAtivo = 'livre';
                else if (this.currentStatus === '') this.kpiFiltroAtivo = null;
                this.atualizarDestaqueCardsKpi();
                this.currentPage = 1;
                this.carregarProdutos();
            });
        }
    },

    atualizarBadgeUnificado() {
        const badge = document.getElementById('badgeUnificado');
        if (!badge) return;
        if (this.currentFilial === 0) {
            badge.innerHTML = '<span class="pulse-dot"></span> Visão Unificada Matriz + PR';
            badge.className = 'ep-chip bg-emerald-400/25 text-emerald-100 border border-emerald-300/30';
        } else if (this.currentFilial === 1) {
            badge.innerHTML = '<i class="fa-solid fa-building mr-1"></i> Matriz 1 (Santa Catarina)';
            badge.className = 'ep-chip bg-white/20 text-white';
        } else {
            badge.innerHTML = '<i class="fa-solid fa-truck-ramp-box mr-1"></i> Filial 6 (Paraná)';
            badge.className = 'ep-chip bg-amber-400/25 text-amber-100 border border-amber-300/30';
        }
    },

    iniciarRelogio() {
        const relogio = document.getElementById('relogio');
        if (!relogio) return;
        const atualizar = () => {
            const d = new Date();
            relogio.textContent = d.toLocaleTimeString('pt-BR');
        };
        atualizar();
        setInterval(atualizar, 1000);
    },

    async carregarTudo() {
        await Promise.all([
            this.carregarResumo(),
            this.carregarMarcas(),
            this.carregarProdutos(),
        ]);
    },

    // ==========================================================================
    // CARREGAR FILIAIS
    // ==========================================================================
    async carregarFiliais() {
        try {
            const res = await this.api('filiais', null, 'GET');
            const select = document.getElementById('select-filial');
            if (select && res.data && res.data.length > 0) {
                select.innerHTML = res.data.map((f) => `
                    <option value="${f.idfilial}" ${f.idfilial === this.currentFilial ? 'selected' : ''} class="text-slate-800 font-bold">
                        ${this.escapeHtml(f.nome)}
                    </option>
                `).join('');
            }
        } catch (e) {
            console.warn('Falha ao carregar filiais:', e.message);
        }
    },

    // ==========================================================================
    // CARREGAR RESUMO / KPIS
    // ==========================================================================
    async carregarResumo() {
        try {
            const res = await this.api('resumo', { filtro_id: this.currentFilial });
            if (!res.success) return;

            this.resumoData = res.data;
            this.renderResumo();

            const userEl = document.getElementById('user-display');
            if (userEl && res.config && res.config.usuario) {
                userEl.textContent = res.config.usuario;
            }
        } catch (e) {
            console.error('Erro ao carregar resumo:', e);
        }
    },

    renderResumo() {
        if (!this.resumoData) return;
        const d = this.resumoData;
        const det = d.detalhes || {};

        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        };

        if (this.modoExibicao === 'peso') {
            // MODO PESO (KG / TONELADAS)
            setTxt('total-estoque', this.formatarPeso(d.total_estoque_kg));
            setTxt('total-reservado', this.formatarPeso(d.total_reservado_kg));
            setTxt('total-livre', this.formatarPeso(d.total_livre_kg));
            setTxt('total-previsao', this.formatarPeso(d.total_previsao_kg));
            setTxt('total-futuro', this.formatarPeso(d.total_futuro_kg));
            setTxt('total-zerados', this.formatNumber(d.produtos_zerados));

            // Subtítulos de apoio com quantidades em peças e detalhes por filial
            const unMatriz = this.formatNumber(det.estoque_matriz);
            const unFilial = this.formatNumber(det.estoque_filial);
            setTxt('kpiDetalheEstoque', `${this.formatNumber(d.total_estoque)} un (M: ${unMatriz} · PR: ${unFilial})`);
            setTxt('kpiDetalheReservado', `${this.formatNumber(d.total_reservado)} un (Status 1 e 4)`);
            setTxt('kpiDetalheLivre', `${this.formatNumber(d.total_livre)} un disponíveis`);
            setTxt('kpiDetalhePrevisao', `${this.formatNumber(d.total_previsao)} un · ${d.total_ocs_abertas || 0} OCs a caminho`);
            setTxt('kpiDetalheFuturo', `${this.formatNumber(d.total_futuro)} un projetadas`);
            setTxt('kpiDetalheCriticos', `${d.produtos_criticos || 0} críticos (≤10 un)`);
        } else {
            // MODO UNIDADES (PEÇAS / EMBALAGENS)
            setTxt('total-estoque', this.formatNumber(d.total_estoque));
            setTxt('total-reservado', this.formatNumber(d.total_reservado));
            setTxt('total-livre', this.formatNumber(d.total_livre));
            setTxt('total-previsao', this.formatNumber(d.total_previsao));
            setTxt('total-futuro', this.formatNumber(d.total_futuro));
            setTxt('total-zerados', this.formatNumber(d.produtos_zerados));

            // Subtítulos de apoio com peso total correspondente
            setTxt('kpiDetalheEstoque', `Peso: ${this.formatarPeso(d.total_estoque_kg)} · M: ${this.formatNumber(det.estoque_matriz)} · PR: ${this.formatNumber(det.estoque_filial)}`);
            setTxt('kpiDetalheReservado', `Peso: ${this.formatarPeso(d.total_reservado_kg)} · Status 1 e 4`);
            setTxt('kpiDetalheLivre', `Peso: ${this.formatarPeso(d.total_livre_kg)} · Físico - Reservado`);
            setTxt('kpiDetalhePrevisao', `Peso: ${this.formatarPeso(d.total_previsao_kg)} · ${d.total_ocs_abertas || 0} OCs a caminho`);
            setTxt('kpiDetalheFuturo', `Peso: ${this.formatarPeso(d.total_futuro_kg)} · Livre + Previsão`);
            setTxt('kpiDetalheCriticos', `${d.produtos_criticos || 0} críticos (≤10 un)`);
        }
    },

    // ==========================================================================
    // CARREGAR MARCAS
    // ==========================================================================
    async carregarMarcas() {
        try {
            const res = await this.api('marcas', null, 'GET');
            const select = document.getElementById('filter-marca');
            if (select && res.data) {
                const selAtual = select.value;
                select.innerHTML = '<option value="">Todas as marcas</option>' + res.data.map((m) => `
                    <option value="${this.escapeHtml(m.nome)}" ${m.nome === selAtual ? 'selected' : ''}>
                        ${this.escapeHtml(m.nome)}
                    </option>
                `).join('');
            }
        } catch (e) {
            console.warn('Erro ao carregar marcas:', e);
        }
    },

    // ==========================================================================
    // CARREGAR PRODUTOS (TABELA)
    // ==========================================================================
    async carregarProdutos() {
        const tbody = document.getElementById('table-body');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="py-12 text-center text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-600 block"></i>
                    Carregando estoque com previsão...
                </td>
            </tr>
        `;

        try {
            const payload = {
                filtro_id: this.currentFilial,
                page: this.currentPage,
                limit: this.currentLimit,
                search: this.currentSearch,
                marca: this.currentMarca,
                status: this.currentStatus,
                order_by: this.currentOrder,
                order_dir: this.currentOrderDir,
            };

            const res = await this.api('produtos', payload);
            if (!res.success) throw new Error(res.error || 'Erro ao carregar produtos');

            this.produtosData = res.data || [];
            this.renderTabela(this.produtosData);
            this.renderPaginacao(res.pagination || {});
        } catch (e) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="py-10 text-center text-red-500 font-bold text-xs">
                        <i class="fa-solid fa-triangle-exclamation mr-1 text-base block mb-1"></i>
                        ${this.escapeHtml(e.message)}
                    </td>
                </tr>
            `;
        }
    },

    renderTabela(produtos) {
        const tbody = document.getElementById('table-body');
        if (!tbody) return;

        if (!produtos || !produtos.length) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="10" class="py-12 text-center text-slate-400">
                        <i class="fa-solid fa-box-open text-2xl mb-2 text-slate-300 block"></i>
                        Nenhum produto encontrado com os filtros atuais.
                    </td>
                </tr>
            `;
            return;
        }

        const isPeso = this.modoExibicao === 'peso';

        tbody.innerHTML = produtos.map((p) => {
            const idItem = p.id_item || p['ID Item'];
            const ref = p.referencia || p['Referência'] || '-';
            const nome = p.produto || p['Produto'] || '-';
            const marca = p.marca || p['Marca'] || '-';
            const fotoUrl = this.formatarUrlImagem(p.foto || p['Foto']);
            const pesoLiq = Number(p.peso_liquido || 0);

            const estMatriz = Number(p.estoque_matriz || 0);
            const estFilial = Number(p.estoque_filial || 0);
            const estTotal = Number(p.estoque_total || p['Estoque Disponível'] || 0);
            const cartReservado = Number(p.qtd_reservada || p['Quantidade em Carteira'] || 0);
            const estLivre = Number(p.estoque_livre || p['Estoque Líquido (s/ Previsão)'] || 0);
            const prevChegada = Number(p.qtd_previsao || p['Previsão de Compra Total'] || 0);
            const estFuturo = Number(p.estoque_futuro || p['Estoque Líquido (c/ Previsão)'] || 0);

            const pesoMatriz = estMatriz * pesoLiq;
            const pesoFilial = estFilial * pesoLiq;
            const pesoTotal = Number(p.peso_estoque_total || (estTotal * pesoLiq));
            const pesoReservado = Number(p.peso_reservado || (cartReservado * pesoLiq));
            const pesoLivre = Number(p.peso_livre || (estLivre * pesoLiq));
            const pesoPrev = Number(p.peso_previsao || (prevChegada * pesoLiq));
            const pesoFuturo = Number(p.peso_futuro || (estFuturo * pesoLiq));

            // Badge Status
            let statusPill = '';
            if (estLivre <= 0) {
                statusPill = '<span class="ep-chip bg-rose-50 text-rose-700 border border-rose-200 font-extrabold">❌ Zerado</span>';
            } else if (estLivre <= 10) {
                statusPill = '<span class="ep-chip bg-rose-50 text-rose-700 border border-rose-200 font-extrabold">⚠️ Crítico</span>';
            } else if (estLivre <= 50) {
                statusPill = '<span class="ep-chip bg-amber-50 text-amber-700 border border-amber-200 font-extrabold">📉 Baixo</span>';
            } else {
                statusPill = '<span class="ep-chip bg-emerald-50 text-emerald-700 border border-emerald-200 font-extrabold">✅ Ok</span>';
            }

            return `
                <tr class="table-row-hover border-b border-slate-100" onclick="estoquePrevisaoApp.abrirModalDetalhe(${idItem})">
                    <!-- Produto e Foto -->
                    <td class="py-3 px-4">
                        <div class="flex items-center gap-3">
                            ${fotoUrl ? `
                                <img src="${fotoUrl}" class="w-10 h-10 rounded-xl object-contain bg-slate-50 border p-0.5 flex-none" onerror="this.src='/portal/assets/img/sem-foto.png'">
                            ` : `
                                <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center flex-none">
                                    <i class="fa-solid fa-box text-sm"></i>
                                </div>
                            `}
                            <div class="min-w-0">
                                <div class="font-extrabold text-slate-800 hover:text-emerald-700 transition leading-snug truncate max-w-sm" title="${this.escapeHtml(nome)}">
                                    ${this.escapeHtml(nome)}
                                </div>
                                <div class="flex items-center flex-wrap gap-2 mt-0.5">
                                    <span class="text-[0.65rem] font-bold text-slate-400">Ref: <b class="text-slate-600">${this.escapeHtml(ref)}</b></span>
                                    <span class="text-[0.65rem] font-bold text-slate-400">ID: <b class="text-slate-600">${idItem}</b></span>
                                    ${pesoLiq > 0 ? `
                                        <span class="text-[0.65rem] font-extrabold text-emerald-800 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200" title="Peso líquido da embalagem">
                                            <i class="fa-solid fa-scale-balanced text-[9px] mr-0.5"></i>${pesoLiq.toLocaleString('pt-BR')} kg
                                        </span>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </td>

                    <!-- Marca -->
                    <td class="py-3 px-3">
                        <span class="ep-chip bg-slate-100 text-slate-700 font-bold">${this.escapeHtml(marca)}</span>
                    </td>

                    <!-- Matriz 1 -->
                    <td class="py-3 px-2 text-right">
                        ${isPeso ? `
                            <span class="font-bold ${estMatriz > 0 ? 'text-slate-700' : 'text-slate-300'}">${estMatriz > 0 ? this.formatarPeso(pesoMatriz) : '--'}</span>
                            ${estMatriz > 0 ? `<div class="text-[0.65rem] text-slate-400 font-semibold">${this.formatNumber(estMatriz)} un</div>` : ''}
                        ` : `
                            <span class="font-bold ${estMatriz > 0 ? 'text-slate-700' : 'text-slate-300'}">${this.formatNumber(estMatriz)}</span>
                            ${estMatriz > 0 && pesoLiq > 0 ? `<div class="text-[0.65rem] text-slate-400 font-semibold">${this.formatarPeso(pesoMatriz)}</div>` : ''}
                        `}
                    </td>

                    <!-- Filial PR -->
                    <td class="py-3 px-2 text-right">
                        ${isPeso ? `
                            <span class="font-bold ${estFilial > 0 ? 'text-emerald-700' : 'text-slate-300'}">${estFilial > 0 ? this.formatarPeso(pesoFilial) : '--'}</span>
                            ${estFilial > 0 ? `<div class="text-[0.65rem] text-emerald-600/70 font-semibold">${this.formatNumber(estFilial)} un</div>` : ''}
                        ` : `
                            <span class="font-bold ${estFilial > 0 ? 'text-emerald-700' : 'text-slate-300'}">${this.formatNumber(estFilial)}</span>
                            ${estFilial > 0 && pesoLiq > 0 ? `<div class="text-[0.65rem] text-emerald-600/70 font-semibold">${this.formatarPeso(pesoFilial)}</div>` : ''}
                        `}
                    </td>

                    <!-- Total Físico -->
                    <td class="py-3 px-3 text-right">
                        ${isPeso ? `
                            <span class="font-black text-slate-800">${this.formatarPeso(pesoTotal)}</span>
                            <div class="text-[0.65rem] text-slate-500 font-bold">${this.formatNumber(estTotal)} un</div>
                        ` : `
                            <span class="font-black text-slate-800">${this.formatNumber(estTotal)}</span>
                            ${pesoLiq > 0 ? `<div class="text-[0.65rem] text-slate-500 font-bold">${this.formatarPeso(pesoTotal)}</div>` : ''}
                        `}
                    </td>

                    <!-- Carteira Reservada -->
                    <td class="py-3 px-3 text-right">
                        ${isPeso ? `
                            <span class="font-black ${cartReservado > 0 ? 'text-amber-600' : 'text-slate-300'}">${cartReservado > 0 ? this.formatarPeso(pesoReservado) : '0'}</span>
                            ${cartReservado > 0 ? `<div class="text-[0.65rem] text-amber-600/80 font-bold">${this.formatNumber(cartReservado)} un</div>` : ''}
                        ` : `
                            <span class="font-black ${cartReservado > 0 ? 'text-amber-600' : 'text-slate-300'}">${this.formatNumber(cartReservado)}</span>
                            ${cartReservado > 0 && pesoLiq > 0 ? `<div class="text-[0.65rem] text-amber-600/80 font-bold">${this.formatarPeso(pesoReservado)}</div>` : ''}
                        `}
                    </td>

                    <!-- Livre Disponível -->
                    <td class="py-3 px-3 text-right">
                        ${isPeso ? `
                            <span class="font-black ${estLivre > 0 ? 'text-emerald-700' : 'text-rose-600'}">${this.formatarPeso(pesoLivre)}</span>
                            <div class="text-[0.65rem] ${estLivre > 0 ? 'text-emerald-600/80' : 'text-rose-500'} font-bold">${this.formatNumber(estLivre)} un</div>
                        ` : `
                            <span class="font-black ${estLivre > 0 ? 'text-emerald-700' : 'text-rose-600'}">${this.formatNumber(estLivre)}</span>
                            ${pesoLiq > 0 ? `<div class="text-[0.65rem] ${estLivre > 0 ? 'text-emerald-600/80' : 'text-rose-500'} font-bold">${this.formatarPeso(pesoLivre)}</div>` : ''}
                        `}
                    </td>

                    <!-- A Chegar (OCs Matriz + Filial) -->
                    <td class="py-3 px-3 text-right">
                        ${prevChegada > 0 ? (isPeso ? `
                            <span class="ep-chip bg-blue-50 text-blue-700 border border-blue-200 font-black">
                                <i class="fa-solid fa-truck-fast text-[0.65rem]"></i> ${this.formatarPeso(pesoPrev)}
                            </span>
                            <div class="text-[0.65rem] text-blue-600/80 font-bold mt-0.5">${this.formatNumber(prevChegada)} un</div>
                        ` : `
                            <span class="ep-chip bg-blue-50 text-blue-700 border border-blue-200 font-black">
                                <i class="fa-solid fa-truck-fast text-[0.65rem]"></i> ${this.formatNumber(prevChegada)}
                            </span>
                            ${pesoLiq > 0 ? `<div class="text-[0.65rem] text-blue-600/80 font-bold mt-0.5">${this.formatarPeso(pesoPrev)}</div>` : ''}
                        `) : '<span class="text-slate-300 font-bold">--</span>'}
                    </td>

                    <!-- Estoque Futuro -->
                    <td class="py-3 px-3 text-right">
                        ${isPeso ? `
                            <span class="font-black ${estFuturo > 0 ? 'text-purple-700' : 'text-slate-400'}">${this.formatarPeso(pesoFuturo)}</span>
                            <div class="text-[0.65rem] text-purple-600/80 font-bold">${this.formatNumber(estFuturo)} un</div>
                        ` : `
                            <span class="font-black ${estFuturo > 0 ? 'text-purple-700' : 'text-slate-400'}">${this.formatNumber(estFuturo)}</span>
                            ${pesoLiq > 0 ? `<div class="text-[0.65rem] text-purple-600/80 font-bold">${this.formatarPeso(pesoFuturo)}</div>` : ''}
                        `}
                    </td>

                    <!-- Status -->
                    <td class="py-3 px-3 text-center">
                        ${statusPill}
                    </td>
                </tr>
            `;
        }).join('');
    },

    renderPaginacao(pag) {
        const info = document.getElementById('pagination-info');
        const btns = document.getElementById('pagination-buttons');
        if (!info || !btns) return;

        const total = pag.total || 0;
        const page = pag.page || 1;
        const pages = pag.pages || 1;
        const limit = pag.limit || this.currentLimit;

        const ini = total === 0 ? 0 : (page - 1) * limit + 1;
        const fim = Math.min(page * limit, total);

        info.textContent = `Mostrando ${ini} a ${fim} de ${this.formatNumber(total)} produtos`;

        let html = '';
        if (page > 1) {
            html += `<button class="py-1 px-2.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-bold" onclick="estoquePrevisaoApp.irPagina(${page - 1})"><i class="fa-solid fa-chevron-left"></i></button>`;
        }
        html += `<span class="py-1 px-3 text-xs font-black text-slate-700">Pág ${page} / ${pages}</span>`;
        if (page < pages) {
            html += `<button class="py-1 px-2.5 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 font-bold" onclick="estoquePrevisaoApp.irPagina(${page + 1})"><i class="fa-solid fa-chevron-right"></i></button>`;
        }
        btns.innerHTML = html;
    },

    irPagina(p) {
        this.currentPage = p;
        this.carregarProdutos();
    },

    mudarLimite(l) {
        this.currentLimit = Number(l);
        this.currentPage = 1;
        this.carregarProdutos();
    },

    ordenar(col) {
        if (this.currentOrder === col) {
            this.currentOrderDir = this.currentOrderDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            this.currentOrder = col;
            this.currentOrderDir = 'ASC';
        }
        this.carregarProdutos();
    },

    limparBusca() {
        const input = document.getElementById('search-produto');
        if (input) input.value = '';
        this.currentSearch = '';
        const btn = document.getElementById('btn-limpar-busca');
        if (btn) btn.classList.add('hidden');
        this.carregarProdutos();
    },

    reset() {
        this.currentSearch = '';
        this.currentMarca = '';
        this.currentStatus = '';
        this.currentFilial = 0;
        this.currentPage = 1;
        this.kpiFiltroAtivo = null;
        this.currentOrder = 'produto';
        this.currentOrderDir = 'ASC';

        const sBusca = document.getElementById('search-produto');
        if (sBusca) sBusca.value = '';
        const sMarca = document.getElementById('filter-marca');
        if (sMarca) sMarca.value = '';
        const sStatus = document.getElementById('filter-status');
        if (sStatus) sStatus.value = '';
        const sFilial = document.getElementById('select-filial');
        if (sFilial) sFilial.value = '0';

        this.atualizarDestaqueCardsKpi();
        this.atualizarBadgeUnificado();
        this.carregarTudo();
    },

    // ==========================================================================
    // MODAL DETALHE DO ITEM (RAIO-X COM PREVISÃO E HISTÓRICO)
    // ==========================================================================
    async abrirModalDetalhe(iditem) {
        Swal.fire({
            title: 'Carregando Raio-X do Produto...',
            text: 'Buscando saldos nas filiais, ordens de compra e histórico...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            customClass: { popup: 'rounded-3xl' }
        });

        try {
            const res = await this.api(`item/${iditem}`, null, 'GET');
            if (!res.success) throw new Error(res.error || 'Erro ao carregar detalhes');

            const item = res.data.item;
            const previsoes = res.data.previsoes || [];
            const historico = res.data.historico || [];
            const fotoUrl = this.formatarUrlImagem(item.foto);

            // Montar Tabela de Previsão de Entregas (OCs Matriz + Filial PR)
            let htmlPrevisoes = '';
            if (!previsoes.length) {
                htmlPrevisoes = '<div class="text-center py-6 text-slate-400 text-xs font-semibold">Nenhuma ordem de compra pendente para este item nas filiais.</div>';
            } else {
                htmlPrevisoes = `
                    <div class="overflow-x-auto max-h-56 overflow-y-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 text-[0.65rem] uppercase tracking-wider text-slate-500 font-black sticky top-0">
                                <tr>
                                    <th class="py-2 px-2.5">OC #</th>
                                    <th class="py-2 px-2.5">Destino</th>
                                    <th class="py-2 px-2.5">Fornecedor</th>
                                    <th class="py-2 px-2.5 text-right">Qtd Saldo</th>
                                    <th class="py-2 px-2.5 text-center">Previsão</th>
                                    <th class="py-2 px-2.5 text-center">Prazo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                ${previsoes.map((oc) => {
                                    const dias = Number(oc.dias_para_chegada ?? oc['Dias para Chegada'] ?? 0);
                                    let corPrazo = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                                    let txtPrazo = `${dias} dias`;
                                    if (dias < 0) {
                                        corPrazo = 'bg-rose-50 text-rose-700 border-rose-200';
                                        txtPrazo = `${Math.abs(dias)}d atrasado`;
                                    } else if (dias === 0) {
                                        corPrazo = 'bg-amber-50 text-amber-700 border-amber-200';
                                        txtPrazo = 'Hoje';
                                    } else if (dias <= 7) {
                                        corPrazo = 'bg-amber-50 text-amber-700 border-amber-200';
                                    }

                                    const isMatriz = (Number(oc.idfilial) === 1);

                                    return `
                                        <tr class="hover:bg-slate-50">
                                            <td class="py-2 px-2.5 font-black text-slate-800">#${oc.numero_oc || oc['Número OC']}</td>
                                            <td class="py-2 px-2.5">
                                                <span class="ep-chip ${isMatriz ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700'}">
                                                    ${isMatriz ? 'Matriz 1' : 'Filial PR 6'}
                                                </span>
                                            </td>
                                            <td class="py-2 px-2.5 font-bold text-slate-700 truncate max-w-xs">${this.escapeHtml(oc.fornecedor || oc['Fornecedor'])}</td>
                                            <td class="py-2 px-2.5 text-right font-black text-blue-700">${this.formatNumber(oc.quantidade_saldo || oc['Quantidade Saldo'])}</td>
                                            <td class="py-2 px-2.5 text-center font-bold text-slate-600">${oc.data_prevista || oc['Data Prevista'] || '-'}</td>
                                            <td class="py-2 px-2.5 text-center">
                                                <span class="ep-chip border ${corPrazo}">${txtPrazo}</span>
                                            </td>
                                        </tr>
                                    `;
                                }).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            Swal.fire({
                showConfirmButton: false,
                showCloseButton: true,
                width: '850px',
                customClass: { popup: 'rounded-3xl p-5' },
                html: `
                    <div class="text-left space-y-4">
                        <!-- HEADER DO ITEM -->
                        <div class="flex items-start gap-4 pb-3 border-b border-slate-100">
                            ${fotoUrl ? `
                                <img src="${fotoUrl}" class="w-16 h-16 rounded-2xl object-contain bg-slate-50 border p-1 shadow-sm flex-none">
                            ` : `
                                <div class="w-16 h-16 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-none">
                                    <i class="fa-solid fa-boxes-packing text-2xl"></i>
                                </div>
                            `}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="ep-chip bg-slate-100 text-slate-700 font-black">ID: ${item.id}</span>
                                    <span class="ep-chip bg-slate-100 text-slate-700 font-bold">Ref: ${this.escapeHtml(item.referencia)}</span>
                                    <span class="ep-chip bg-emerald-100 text-emerald-800 font-bold">${this.escapeHtml(item.marca || 'SEM MARCA')}</span>
                                    ${Number(item.peso_liquido || 0) > 0 ? `
                                        <span class="ep-chip bg-emerald-50 text-emerald-800 border border-emerald-200 font-black">
                                            <i class="fa-solid fa-scale-balanced mr-1"></i>${Number(item.peso_liquido).toLocaleString('pt-BR')} kg
                                        </span>
                                    ` : ''}
                                </div>
                                <h3 class="text-base sm:text-lg font-black text-slate-800 mt-1 leading-snug">${this.escapeHtml(item.nome)}</h3>
                                <div class="text-xs text-slate-500 font-semibold mt-0.5">
                                    <i class="fa-solid fa-location-dot text-rose-500 mr-1"></i> Endereço Depósito: <b>${this.escapeHtml(item.enderecos || 'Nenhum')}</b>
                                </div>
                            </div>
                        </div>

                        <!-- GRID COMPARATIVO DE SALDOS -->
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                            <!-- Matriz 1 -->
                            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                                <div class="text-[0.65rem] font-black uppercase text-slate-500 tracking-wider mb-1">Matriz 1 (SC)</div>
                                <div class="flex justify-between text-xs py-0.5"><span>Físico:</span><b class="text-slate-800">${this.formatNumber(item.estoque_matriz)}</b></div>
                                <div class="flex justify-between text-xs py-0.5"><span>Reservado:</span><b class="text-amber-600">${this.formatNumber(item.reservado_matriz)}</b></div>
                                <div class="flex justify-between text-xs py-0.5"><span>Livre:</span><b class="text-emerald-700">${this.formatNumber(item.livre_matriz)}</b></div>
                                <div class="flex justify-between text-xs py-0.5 pt-1 border-t border-slate-200"><span>A Chegar (OCs):</span><b class="text-blue-700">${this.formatNumber(item.previsao_matriz)}</b></div>
                            </div>

                            <!-- Filial 6 PR -->
                            <div class="p-3 rounded-2xl bg-emerald-50/60 border border-emerald-200">
                                <div class="text-[0.65rem] font-black uppercase text-emerald-700 tracking-wider mb-1">Filial 6 (PR)</div>
                                <div class="flex justify-between text-xs py-0.5"><span>Físico:</span><b class="text-slate-800">${this.formatNumber(item.estoque_filial)}</b></div>
                                <div class="flex justify-between text-xs py-0.5"><span>Reservado:</span><b class="text-amber-600">${this.formatNumber(item.reservado_filial)}</b></div>
                                <div class="flex justify-between text-xs py-0.5"><span>Livre:</span><b class="text-emerald-700">${this.formatNumber(item.livre_filial)}</b></div>
                                <div class="flex justify-between text-xs py-0.5 pt-1 border-t border-emerald-200"><span>A Chegar (OCs):</span><b class="text-blue-700">${this.formatNumber(item.previsao_filial)}</b></div>
                            </div>

                            <!-- Total Consolidado -->
                            <div class="p-3 rounded-2xl bg-purple-50/60 border border-purple-200">
                                <div class="text-[0.65rem] font-black uppercase text-purple-700 tracking-wider mb-1">Consolidado Total (1 + 6)</div>
                                <div class="flex justify-between text-xs py-0.5"><span>Total Físico:</span><b class="text-slate-900">${this.formatNumber(item.estoque_total)}</b></div>
                                <div class="flex justify-between text-xs py-0.5"><span>Total Reservado:</span><b class="text-amber-600">${this.formatNumber(item.reservado_total)}</b></div>
                                <div class="flex justify-between text-xs py-0.5"><span>Total Livre:</span><b class="text-emerald-700">${this.formatNumber(item.livre_total)}</b></div>
                                <div class="flex justify-between text-xs py-0.5 pt-1 border-t border-purple-200"><span class="font-extrabold text-purple-900">Estoque Futuro:</span><b class="text-purple-700 text-sm">${this.formatNumber(item.estoque_futuro)}</b></div>
                            </div>
                        </div>

                        <!-- TABELA DE ORDENS DE COMPRA / PREVISÃO -->
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                            <div class="flex justify-between items-center mb-2">
                                <div class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-truck-fast text-blue-600"></i> Ordens de Compra com Saldo a Chegar (Matriz + Filial PR)
                                </div>
                                <span class="ep-chip bg-blue-100 text-blue-800 font-bold">${this.formatNumber(item.previsao_total)} un. a caminho</span>
                            </div>
                            ${htmlPrevisoes}
                        </div>

                        <!-- HISTÓRICO 30 DIAS (CHART.JS) -->
                        <div class="bg-white p-3.5 rounded-2xl border border-slate-200">
                            <div class="text-xs font-black text-slate-700 mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-chart-line text-emerald-600"></i> Movimentação Física nos Últimos 30 Dias (Entradas vs Saídas)
                            </div>
                            <div style="height: 140px;">
                                <canvas id="chartModalHistorico"></canvas>
                            </div>
                        </div>
                    </div>
                `,
                didOpen: () => {
                    // Renderiza o gráfico do modal
                    const ctx = document.getElementById('chartModalHistorico');
                    if (ctx && historico.length) {
                        const labels = historico.map((h) => {
                            const p = String(h.data).split('-');
                            return p.length === 3 ? `${p[2]}/${p[1]}` : h.data;
                        });
                        const entradas = historico.map((h) => Number(h.entradas || 0));
                        const saidas = historico.map((h) => Number(h.saidas || 0));

                        new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels,
                                datasets: [
                                    { label: 'Entradas', data: entradas, backgroundColor: '#10b981', borderRadius: 4 },
                                    { label: 'Saídas', data: saidas, backgroundColor: '#f43f5e', borderRadius: 4 },
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: { legend: { position: 'top', labels: { boxWidth: 10, font: { size: 10 } } } },
                                scales: {
                                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { size: 9 } } },
                                    x: { grid: { display: false }, ticks: { font: { size: 9 } } }
                                }
                            }
                        });
                    }
                }
            });
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Erro ao abrir detalhes',
                text: e.message,
                customClass: { popup: 'rounded-3xl' }
            });
        }
    },

    // ==========================================================================
    // EXPORTAÇÃO CSV
    // ==========================================================================
    async exportarCsv() {
        Swal.fire({
            title: 'Gerando Exportação...',
            text: 'Preparando dados consolidados de estoque e previsões...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            customClass: { popup: 'rounded-3xl' }
        });

        try {
            const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
            const url = `${apiRoot}/estoque-previsao/exportar`;
            const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');

            const payload = {
                filtro_id: this.currentFilial,
                search: this.currentSearch,
                marca: this.currentMarca,
                status: this.currentStatus,
            };

            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': token ? `Bearer ${token}` : ''
                },
                body: JSON.stringify(payload),
            });

            if (!resp.ok) throw new Error('Falha ao exportar dados do servidor');

            const blob = await resp.blob();
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = `estoque_previsao_${new Date().toISOString().slice(0, 10)}.csv`;
            a.click();
            URL.revokeObjectURL(a.href);

            Swal.close();
        } catch (e) {
            Swal.fire({
                icon: 'error',
                title: 'Erro ao exportar',
                text: e.message,
                customClass: { popup: 'rounded-3xl' }
            });
        }
    }
};

// Auto inicialização
window.addEventListener('DOMContentLoaded', () => {
    estoquePrevisaoApp.init();
});
