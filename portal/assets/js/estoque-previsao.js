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
    modoExibicao: localStorage.getItem('ep_modo_exibicao') || 'unidade', // 'peso' ou 'unidade'
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

    formatarDataBR(dataStr) {
        if (!dataStr) return '-';
        const s = String(dataStr).trim();
        if (/^\d{4}-\d{2}-\d{2}/.test(s)) {
            const [ano, mes, dia] = s.split('T')[0].split('-');
            return `${dia}/${mes}/${ano}`;
        }
        return s;
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
        // A lista de produtos é SEMPRE exibida por unidades (peças) como número principal
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
        this.bindEvents();
        await this.carregarFiliais();
        await this.carregarTudo();
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
                this.atualizarBadgeFilial();
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

    atualizarBadgeFilial() {
        const badge = document.getElementById('badgeFilial');
        if (!badge) return;
        if (this.currentFilial === 0) {
            badge.innerHTML = '<span class="pulse-dot"></span> Matriz + Filial PR (1 + 6)';
            badge.className = 'ep-chip bg-emerald-400/25 text-emerald-100 border border-emerald-300/30';
        } else if (this.currentFilial === 1) {
            badge.innerHTML = '<i class="fa-solid fa-building mr-1"></i> Matriz 1 (SC)';
            badge.className = 'ep-chip bg-white/20 text-white';
        } else {
            badge.innerHTML = '<i class="fa-solid fa-truck-ramp-box mr-1"></i> Filial 6 (PR)';
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
        const mobileBody = document.getElementById('mobile-cards-body');
        if (!tbody) return;

        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="py-12 text-center text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-600 block"></i>
                    Carregando estoque com previsão...
                </td>
            </tr>
        `;
        if (mobileBody) {
            mobileBody.innerHTML = `
                <div class="py-10 text-center text-slate-400 text-xs">
                    <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 text-emerald-600 block"></i>
                    Carregando estoque com previsão...
                </div>
            `;
        }

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
            if (mobileBody) {
                mobileBody.innerHTML = `
                    <div class="py-8 px-4 text-center text-red-500 font-bold text-xs">
                        <i class="fa-solid fa-triangle-exclamation mb-1 text-base block"></i>
                        ${this.escapeHtml(e.message)}
                    </div>
                `;
            }
        }
    },

    renderTabela(produtos) {
        const tbody = document.getElementById('table-body');
        const mobileBody = document.getElementById('mobile-cards-body');
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
            if (mobileBody) {
                mobileBody.innerHTML = `
                    <div class="py-10 px-4 text-center text-slate-400 text-xs">
                        <i class="fa-solid fa-box-open text-2xl mb-2 text-slate-300 block"></i>
                        Nenhum produto encontrado com os filtros atuais.
                    </div>
                `;
            }
            return;
        }

        const isPeso = this.modoExibicao === 'peso';

        if (mobileBody) {
            mobileBody.innerHTML = produtos.map((p) => {
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

                let statusPill = '';
                if (estLivre <= 0) {
                    statusPill = '<span class="ep-chip bg-rose-50 text-rose-700 border border-rose-200 font-extrabold whitespace-nowrap">❌ Zerado</span>';
                } else if (estLivre <= 10) {
                    statusPill = '<span class="ep-chip bg-rose-50 text-rose-700 border border-rose-200 font-extrabold whitespace-nowrap">⚠️ Crítico</span>';
                } else if (estLivre <= 50) {
                    statusPill = '<span class="ep-chip bg-amber-50 text-amber-700 border border-amber-200 font-extrabold whitespace-nowrap">📉 Baixo</span>';
                } else {
                    statusPill = '<span class="ep-chip bg-emerald-50 text-emerald-700 border border-emerald-200 font-extrabold whitespace-nowrap">✅ Ok</span>';
                }

                return `
                    <div class="p-3.5 hover:bg-slate-50 active:bg-emerald-50/40 transition cursor-pointer" onclick="estoquePrevisaoApp.abrirModalDetalhe(${idItem})">
                        <div class="flex items-start gap-3">
                            ${fotoUrl ? `
                                <img src="${fotoUrl}" class="w-12 h-12 rounded-xl object-contain bg-slate-50 border p-0.5 flex-none" onerror="this.src='/portal/assets/img/sem-foto.png'">
                            ` : `
                                <div class="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center flex-none">
                                    <i class="fa-solid fa-box text-base"></i>
                                </div>
                            `}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="font-extrabold text-slate-800 text-xs sm:text-sm leading-snug line-clamp-2">${this.escapeHtml(nome)}</div>
                                    ${statusPill}
                                </div>
                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                    <span class="text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded">${this.escapeHtml(marca)}</span>
                                    <span class="text-[10px] font-bold text-slate-400">Ref: <b class="text-slate-600">${this.escapeHtml(ref)}</b></span>
                                    <span class="text-[10px] font-bold text-slate-400">ID: <b class="text-slate-600">${idItem}</b></span>
                                    ${pesoLiq > 0 ? `<span class="text-[10px] font-extrabold text-emerald-800 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">${pesoLiq.toLocaleString('pt-BR')} kg</span>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-4 gap-1.5 mt-3 pt-2.5 border-t border-slate-100 text-center">
                            <div class="bg-slate-50 rounded-xl p-1.5 border border-slate-200/70 min-w-0">
                                <div class="text-[9px] font-extrabold uppercase text-slate-400 truncate">Físico (1+6)</div>
                                <div class="text-xs font-black text-slate-800 truncate">${this.formatNumber(estTotal)}</div>
                                <div class="text-[9px] text-slate-400 font-bold truncate">M:${this.formatNumber(estMatriz)} PR:${this.formatNumber(estFilial)}</div>
                            </div>
                            <div class="bg-amber-50/60 rounded-xl p-1.5 border border-amber-200/70 min-w-0" ${cartReservado > 0 ? `onclick="event.stopPropagation(); estoquePrevisaoApp.abrirRastreioReservado(${idItem}, 0, '${this.escapeHtml(nome)}')"` : ''}>
                                <div class="text-[9px] font-extrabold uppercase text-amber-700 truncate">Reservado</div>
                                <div class="text-xs font-black ${cartReservado > 0 ? 'text-amber-600 underline' : 'text-slate-300'} truncate">
                                    ${this.formatNumber(cartReservado)}${cartReservado > 0 ? ' <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>' : ''}
                                </div>
                                <div class="text-[9px] text-amber-700/70 font-bold truncate">${cartReservado > 0 ? 'Ver pedidos' : 'Sem reserva'}</div>
                            </div>
                            <div class="bg-emerald-50/70 rounded-xl p-1.5 border border-emerald-200/80 min-w-0">
                                <div class="text-[9px] font-extrabold uppercase text-emerald-700 truncate">Livre</div>
                                <div class="text-xs font-black ${estLivre > 0 ? 'text-emerald-700' : 'text-rose-600'} truncate">${this.formatNumber(estLivre)}</div>
                                <div class="text-[9px] text-emerald-700/70 font-bold truncate">Disponível</div>
                            </div>
                            <div class="${prevChegada > 0 ? 'bg-blue-50/80 border-blue-200' : 'bg-purple-50/50 border-purple-200/60'} rounded-xl p-1.5 border min-w-0">
                                <div class="text-[9px] font-extrabold uppercase ${prevChegada > 0 ? 'text-blue-700' : 'text-purple-700'} truncate">${prevChegada > 0 ? 'A Chegar' : 'Futuro'}</div>
                                <div class="text-xs font-black ${prevChegada > 0 ? 'text-blue-700' : 'text-purple-700'} truncate">${prevChegada > 0 ? '+' + this.formatNumber(prevChegada) : this.formatNumber(estFuturo)}</div>
                                <div class="text-[9px] ${prevChegada > 0 ? 'text-blue-600' : 'text-purple-600/70'} font-bold truncate">Fut: ${this.formatNumber(estFuturo)}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

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
                                <div class="font-extrabold text-slate-800 hover:text-emerald-700 transition leading-snug truncate max-w-sm">
                                    ${this.escapeHtml(nome)}
                                </div>
                                <div class="flex items-center flex-wrap gap-2 mt-0.5">
                                    <span class="text-[0.65rem] font-bold text-slate-400">Ref: <b class="text-slate-600">${this.escapeHtml(ref)}</b></span>
                                    <span class="text-[0.65rem] font-bold text-slate-400">ID: <b class="text-slate-600">${idItem}</b></span>
                                    ${pesoLiq > 0 ? `
                                        <span class="text-[0.65rem] font-extrabold text-emerald-800 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">
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
                        <span class="font-bold ${estMatriz > 0 ? 'text-slate-700' : 'text-slate-300'}">${estMatriz > 0 ? this.formatNumber(estMatriz) : '--'}</span>
                        ${estMatriz > 0 && pesoLiq > 0 ? `<div class="text-[0.65rem] text-slate-400 font-semibold">${this.formatarPeso(pesoMatriz)}</div>` : ''}
                    </td>

                    <!-- Filial PR -->
                    <td class="py-3 px-2 text-right">
                        <span class="font-bold ${estFilial > 0 ? 'text-emerald-700' : 'text-slate-300'}">${estFilial > 0 ? this.formatNumber(estFilial) : (estTotal > 0 ? '0' : '--')}</span>
                        ${estFilial > 0 && pesoLiq > 0 ? `<div class="text-[0.65rem] text-emerald-600/70 font-semibold">${this.formatarPeso(pesoFilial)}</div>` : ''}
                    </td>

                    <!-- Total Físico -->
                    <td class="py-3 px-3 text-right">
                        <span class="font-black text-slate-800">${this.formatNumber(estTotal)}</span>
                        ${pesoLiq > 0 && estTotal > 0 ? `<div class="text-[0.65rem] text-slate-500 font-bold">${this.formatarPeso(pesoTotal)}</div>` : ''}
                    </td>

                    <!-- Carteira Reservada -->
                    <td class="py-3 px-3 text-right">
                        ${cartReservado > 0 ? `
                            <button type="button" onclick="event.stopPropagation(); estoquePrevisaoApp.abrirRastreioReservado(${idItem}, 0, '${this.escapeHtml(nome)}')" class="inline-flex flex-col items-end group/res cursor-pointer text-right" title="Clique para rastrear os pedidos em carteira">
                                <span class="font-black text-amber-600 group-hover/res:text-amber-700 group-hover/res:underline flex items-center gap-1">
                                    ${this.formatNumber(cartReservado)} <i class="fa-solid fa-arrow-up-right-from-square text-[8px] opacity-70"></i>
                                </span>
                                ${pesoLiq > 0 ? `<div class="text-[0.65rem] text-amber-600/80 font-bold">${this.formatarPeso(pesoReservado)}</div>` : ''}
                            </button>
                        ` : `
                            <span class="font-black text-slate-300">0</span>
                        `}
                    </td>

                    <!-- Livre Disponível -->
                    <td class="py-3 px-3 text-right">
                        <span class="font-black ${estLivre > 0 ? 'text-emerald-700' : 'text-rose-600'}">${this.formatNumber(estLivre)}</span>
                        ${pesoLiq > 0 ? `<div class="text-[0.65rem] ${estLivre > 0 ? 'text-emerald-600/80' : 'text-rose-500'} font-bold">${this.formatarPeso(pesoLivre)}</div>` : ''}
                    </td>

                    <!-- A Chegar (OCs Matriz + Filial) -->
                    <td class="py-3 px-3 text-right">
                        ${prevChegada > 0 ? `
                            <span class="ep-chip bg-blue-50 text-blue-700 border border-blue-200 font-black">
                                <i class="fa-solid fa-truck-fast text-[0.65rem]"></i> ${this.formatNumber(prevChegada)}
                            </span>
                            ${pesoLiq > 0 ? `<div class="text-[0.65rem] text-blue-600/80 font-bold mt-0.5">${this.formatarPeso(pesoPrev)}</div>` : ''}
                        ` : '<span class="text-slate-300 font-bold">--</span>'}
                    </td>

                    <!-- Estoque Futuro -->
                    <td class="py-3 px-3 text-right">
                        <span class="font-black ${estFuturo > 0 ? 'text-purple-700' : 'text-slate-400'}">${this.formatNumber(estFuturo)}</span>
                        ${pesoLiq > 0 && estFuturo > 0 ? `<div class="text-[0.65rem] text-purple-600/80 font-bold">${this.formatarPeso(pesoFuturo)}</div>` : ''}
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
        this.atualizarBadgeFilial();
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

            // Montar Tabela / Cards de Previsão de Entregas (OCs Matriz + Filial PR)
            let htmlPrevisoes = '';
            if (!previsoes.length) {
                htmlPrevisoes = '<div class="text-center py-6 text-slate-400 text-xs font-semibold">Nenhuma ordem de compra pendente para este item nas filiais.</div>';
            } else {
                const renderOcRows = previsoes.map((oc) => {
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
                    const numOc = oc.numero_oc || oc['Número OC'];
                    const forn = this.escapeHtml(oc.fornecedor || oc['Fornecedor']);
                    const qtdSaldo = this.formatNumber(oc.quantidade_saldo || oc['Quantidade Saldo']);
                    const dtPrev = this.formatarDataBR(oc.data_prevista || oc['Data Prevista']);

                    return {
                        card: `
                            <div class="bg-white rounded-xl p-2.5 border border-slate-200 shadow-xs space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-black text-slate-800 text-xs">#${numOc}</span>
                                        <span class="ep-chip ${isMatriz ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700'} whitespace-nowrap">
                                            ${isMatriz ? 'Matriz 1' : 'Filial PR 6'}
                                        </span>
                                    </div>
                                    <span class="ep-chip border ${corPrazo} whitespace-nowrap">${txtPrazo}</span>
                                </div>
                                <div class="text-xs font-bold text-slate-700 truncate">${forn}</div>
                                <div class="flex items-center justify-between pt-1 border-t border-slate-100 text-[11px]">
                                    <span class="text-slate-500">Prev: <b class="text-slate-700">${dtPrev}</b></span>
                                    <span class="text-blue-700 font-black">Saldo: ${qtdSaldo} un</span>
                                </div>
                            </div>
                        `,
                        row: `
                            <tr class="hover:bg-slate-50">
                                <td class="py-2 px-2.5 font-black text-slate-800 whitespace-nowrap">#${numOc}</td>
                                <td class="py-2 px-2.5 whitespace-nowrap">
                                    <span class="ep-chip ${isMatriz ? 'bg-slate-100 text-slate-700' : 'bg-emerald-50 text-emerald-700'} whitespace-nowrap">
                                        ${isMatriz ? 'Matriz 1' : 'Filial PR 6'}
                                    </span>
                                </td>
                                <td class="py-2 px-2.5 font-bold text-slate-700 truncate max-w-[200px]">${forn}</td>
                                <td class="py-2 px-2.5 text-right font-black text-blue-700 whitespace-nowrap">${qtdSaldo}</td>
                                <td class="py-2 px-2.5 text-center font-bold text-slate-600 whitespace-nowrap">${dtPrev}</td>
                                <td class="py-2 px-2.5 text-center whitespace-nowrap">
                                    <span class="ep-chip border ${corPrazo} whitespace-nowrap">${txtPrazo}</span>
                                </td>
                            </tr>
                        `
                    };
                });

                htmlPrevisoes = `
                    <!-- Mobile Cards de OCs -->
                    <div class="sm:hidden space-y-2 max-h-60 overflow-y-auto pr-0.5">
                        ${renderOcRows.map(r => r.card).join('')}
                    </div>

                    <!-- Desktop Table de OCs -->
                    <div class="hidden sm:block overflow-x-auto max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white">
                        <table class="w-full text-left text-xs min-w-[520px]">
                            <thead class="bg-slate-100 text-[0.65rem] uppercase tracking-wider text-slate-500 font-black sticky top-0">
                                <tr>
                                    <th class="py-2 px-2.5 whitespace-nowrap">OC #</th>
                                    <th class="py-2 px-2.5 whitespace-nowrap">Destino</th>
                                    <th class="py-2 px-2.5">Fornecedor</th>
                                    <th class="py-2 px-2.5 text-right whitespace-nowrap">Qtd Saldo</th>
                                    <th class="py-2 px-2.5 text-center whitespace-nowrap">Previsão</th>
                                    <th class="py-2 px-2.5 text-center whitespace-nowrap">Prazo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                ${renderOcRows.map(r => r.row).join('')}
                            </tbody>
                        </table>
                    </div>
                `;
            }

            Swal.fire({
                showConfirmButton: false,
                showCloseButton: true,
                width: '850px',
                customClass: { popup: 'rounded-3xl p-4 sm:p-5 max-w-[96vw]' },
                html: `
                    <div class="text-left space-y-4">
                        <!-- HEADER DO ITEM (pr-7 para não colidir com o botão X do modal) -->
                        <div class="flex items-start gap-3 sm:gap-4 pb-3 pr-7 border-b border-slate-100">
                            ${fotoUrl ? `
                                <img src="${fotoUrl}" class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl object-contain bg-slate-50 border p-1 shadow-sm flex-none">
                            ` : `
                                <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-emerald-100 text-emerald-700 flex items-center justify-center flex-none">
                                    <i class="fa-solid fa-boxes-packing text-xl sm:text-2xl"></i>
                                </div>
                            `}
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="ep-chip bg-slate-100 text-slate-700 font-black whitespace-nowrap">ID: ${item.id}</span>
                                    <span class="ep-chip bg-slate-100 text-slate-700 font-bold whitespace-nowrap">Ref: ${this.escapeHtml(item.referencia)}</span>
                                    <span class="ep-chip bg-emerald-100 text-emerald-800 font-bold whitespace-nowrap">${this.escapeHtml(item.marca || 'SEM MARCA')}</span>
                                    ${Number(item.peso_liquido || 0) > 0 ? `
                                        <span class="ep-chip bg-emerald-50 text-emerald-800 border border-emerald-200 font-black whitespace-nowrap">
                                            <i class="fa-solid fa-scale-balanced mr-1"></i>${Number(item.peso_liquido).toLocaleString('pt-BR')} kg
                                        </span>
                                    ` : ''}
                                </div>
                                <h3 class="text-sm sm:text-lg font-black text-slate-800 mt-1.5 leading-snug break-words">${this.escapeHtml(item.nome)}</h3>
                                <div class="text-xs text-slate-500 font-semibold mt-0.5">
                                    <i class="fa-solid fa-location-dot text-rose-500 mr-1"></i> Endereço Depósito: <b>${this.escapeHtml(item.enderecos || 'Nenhum')}</b>
                                </div>
                            </div>
                        </div>

                        <!-- GRID COMPARATIVO DE SALDOS (MATRIZ 1 E TOTAL 1 + 6) -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <!-- Matriz 1 (SC) -->
                            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                                <div class="text-[0.7rem] font-black uppercase text-slate-600 tracking-wider mb-2 flex items-center justify-between gap-2">
                                    <span><i class="fa-solid fa-building mr-1 text-slate-400"></i> Matriz 1 (SC)</span>
                                </div>
                                <div class="flex justify-between text-xs py-1"><span>Físico:</span><b class="text-slate-800">${this.formatNumber(item.estoque_matriz)} un</b></div>
                                <div class="flex justify-between text-xs py-1 items-center">
                                    <span>Reservado (Pedidos):</span>
                                    <button type="button" onclick="estoquePrevisaoApp.abrirRastreioReservado(${item.id}, 1, '${this.escapeHtml(item.nome)}')" class="inline-flex items-center gap-1 font-black text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded-lg border border-amber-200 transition-colors cursor-pointer whitespace-nowrap" title="Rastrear pedidos reservados na Matriz 1">
                                        ${this.formatNumber(item.reservado_matriz)} un <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                    </button>
                                </div>
                                <div class="flex justify-between text-xs py-1"><span>Livre para Venda:</span><b class="text-emerald-700 font-black">${this.formatNumber(item.livre_matriz)} un</b></div>
                                <div class="flex justify-between text-xs py-1 pt-1.5 border-t border-slate-200"><span>A Chegar (OCs):</span><b class="text-blue-700">${this.formatNumber(item.previsao_matriz)} un</b></div>
                            </div>

                            <!-- Matriz + Filial PR (1 + 6) -->
                            <div class="p-3.5 rounded-2xl bg-gradient-to-br from-emerald-50/60 to-teal-50/60 border border-emerald-200">
                                <div class="text-[0.7rem] font-black uppercase text-emerald-800 tracking-wider mb-2 flex items-center justify-between gap-2">
                                    <span><i class="fa-solid fa-cubes mr-1 text-emerald-600"></i> Matriz + Filial PR (1 + 6)</span>
                                    <span class="text-[0.65rem] font-black px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-800 whitespace-nowrap flex-none">Total</span>
                                </div>
                                <div class="flex justify-between text-xs py-1"><span>Total Físico:</span><b class="text-slate-900">${this.formatNumber(item.estoque_total)} un</b></div>
                                <div class="flex justify-between text-xs py-1 items-center">
                                    <span>Total Reservado:</span>
                                    <button type="button" onclick="estoquePrevisaoApp.abrirRastreioReservado(${item.id}, 0, '${this.escapeHtml(item.nome)}')" class="inline-flex items-center gap-1 font-black text-amber-600 hover:text-amber-700 bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded-lg border border-amber-200 transition-colors cursor-pointer whitespace-nowrap" title="Rastrear todos os pedidos reservados (1 + 6)">
                                        ${this.formatNumber(item.reservado_total)} un <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                    </button>
                                </div>
                                <div class="flex justify-between text-xs py-1"><span>Total Livre:</span><b class="text-emerald-700 font-black">${this.formatNumber(item.livre_total)} un</b></div>
                                <div class="flex justify-between text-xs py-1 pt-1.5 border-t border-emerald-200"><span class="font-extrabold text-emerald-950">Estoque Futuro:</span><b class="text-emerald-800 font-black text-sm">${this.formatNumber(item.estoque_futuro)} un</b></div>
                            </div>
                        </div>

                        <!-- TABELA DE ORDENS DE COMPRA / PREVISÃO -->
                        <div class="bg-slate-50 p-3 sm:p-3.5 rounded-2xl border border-slate-200">
                            <div class="flex flex-wrap justify-between items-center gap-2 mb-2.5">
                                <div class="text-xs font-black text-slate-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-truck-fast text-blue-600 flex-none"></i>
                                    <span>Ordens de Compra com Saldo a Chegar (Matriz + Filial PR)</span>
                                </div>
                                <span class="ep-chip bg-blue-100 text-blue-800 font-bold whitespace-nowrap flex-none">${this.formatNumber(item.previsao_total)} un. a caminho</span>
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
    },

    /**
     * Rastreia os pedidos em carteira que compõem o saldo reservado do produto,
     * respeitando a permissão de filial do usuário.
     */
    async abrirRastreioReservado(iditem, filial = 0, nomeItem = '') {
        Swal.fire({
            title: 'Carregando Pedidos Reservados...',
            text: 'Buscando carteira de pedidos em aberto...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            customClass: { popup: 'rounded-3xl' }
        });

        try {
            const res = await this.api(`item/${iditem}/pedidos-reservados?filial=${filial}`, null, 'GET');
            if (!res.success) {
                if (res.sem_permissao) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Acesso Restrito',
                        text: res.error || 'Você não possui permissão para visualizar os pedidos desta filial.',
                        confirmButtonText: 'Entendi',
                        confirmButtonColor: '#059669',
                        customClass: { popup: 'rounded-3xl' }
                    });
                    return;
                }
                throw new Error(res.error || 'Erro ao buscar pedidos reservados');
            }

            const data = res.data;
            const pedidos = data.pedidos || [];
            const item = data.item || {};
            const tituloFilial = filial === 1 ? 'Matriz 1 (SC)' : (filial === 6 ? 'Filial 6 (PR)' : 'Matriz + Filial PR (1 + 6)');

            if (!pedidos.length) {
                Swal.fire({
                    icon: 'info',
                    title: 'Nenhum Pedido em Aberto',
                    text: `Não foram encontrados pedidos pendentes de faturamento para o item ${item.referencia || ''} nesta filial.`,
                    confirmButtonText: 'Fechar',
                    confirmButtonColor: '#059669',
                    customClass: { popup: 'rounded-3xl' }
                });
                return;
            }

            const htmlTabela = `
                <div class="text-left space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-100">
                        <div>
                            <span class="text-[10px] font-black uppercase text-amber-700 bg-amber-50 px-2 py-0.5 rounded-md border border-amber-200">
                                <i class="fa-solid fa-cart-shopping mr-1"></i> CARTEIRA RESERVADA · ${tituloFilial}
                            </span>
                            <h4 class="font-black text-slate-800 text-sm mt-1">${this.escapeHtml(item.nome || nomeItem)}</h4>
                            <div class="text-[11px] text-slate-500 font-semibold">Ref: <b>${item.referencia || '-'}</b> · ID: <b>${item.id || iditem}</b></div>
                        </div>
                    </div>

                    <div class="overflow-x-auto max-h-72 overflow-y-auto rounded-xl border border-slate-200">
                        <table class="w-full text-left text-xs min-w-[760px] border-collapse">
                            <thead class="bg-slate-100 text-[0.68rem] uppercase tracking-wider text-slate-600 font-black sticky top-0 shadow-xs">
                                <tr>
                                    <th class="py-2.5 px-3 whitespace-nowrap">Pedido</th>
                                    <th class="py-2.5 px-2.5 whitespace-nowrap">Filial</th>
                                    <th class="py-2.5 px-2.5 whitespace-nowrap">Data</th>
                                    <th class="py-2.5 px-3">Cliente</th>
                                    <th class="py-2.5 px-3">Representante</th>
                                    <th class="py-2.5 px-3 text-right whitespace-nowrap">Saldo Res.</th>
                                    <th class="py-2.5 px-3 text-right whitespace-nowrap">Peso Res.</th>
                                    <th class="py-2.5 px-2.5 text-center whitespace-nowrap">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                ${pedidos.map(p => `
                                    <tr class="hover:bg-amber-50/40 transition-colors">
                                        <td class="py-2 px-3 font-mono font-black text-amber-700 whitespace-nowrap">#${p.idpedido}</td>
                                        <td class="py-2 px-2.5 whitespace-nowrap">
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold ${p.idfilial === 1 ? 'bg-slate-100 text-slate-700 border border-slate-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}">
                                                ${p.filial_nome}
                                            </span>
                                        </td>
                                        <td class="py-2 px-2.5 text-slate-600 text-[11px] whitespace-nowrap">${p.data_fmt}</td>
                                        <td class="py-2 px-3 font-bold text-slate-800 truncate max-w-[220px]" title="${this.escapeHtml(p.cliente)}">${this.escapeHtml(p.cliente)}</td>
                                        <td class="py-2 px-3 text-slate-600 truncate max-w-[150px] text-[11px]" title="${this.escapeHtml(p.representante)}">${this.escapeHtml(p.representante)}</td>
                                        <td class="py-2 px-3 text-right font-black text-slate-900 whitespace-nowrap">${this.formatNumber(p.qtd_reservada)} un</td>
                                        <td class="py-2 px-3 text-right font-bold text-amber-700 whitespace-nowrap">${this.formatarPeso(p.peso_reservado)}</td>
                                        <td class="py-2 px-2.5 text-center whitespace-nowrap">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${p.status_id === 4 ? 'bg-sky-50 text-sky-700 border border-sky-200' : 'bg-amber-50 text-amber-700 border border-amber-200'}">
                                                ${p.status_id === 4 ? 'Parcial' : 'Aberto'}
                                            </span>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-xs">
                        <span class="text-slate-500 font-bold">Total: <b>${pedidos.length}</b> pedido(s) em carteira</span>
                        <div class="flex items-center gap-3">
                            <span class="text-slate-700 font-extrabold">Qtd: <b class="text-amber-700">${this.formatNumber(data.total_qtd_reservada)} un</b></span>
                            <span class="text-slate-400">·</span>
                            <span class="text-slate-700 font-extrabold">Peso: <b class="text-amber-700">${this.formatarPeso(data.total_peso_reservado)}</b></span>
                        </div>
                    </div>
                </div>
            `;

            Swal.fire({
                html: htmlTabela,
                width: '980px',
                showConfirmButton: true,
                confirmButtonText: 'Voltar ao Raio-X do Produto',
                confirmButtonColor: '#059669',
                showCloseButton: true,
                customClass: { popup: 'rounded-3xl p-5 max-w-[96vw]' }
            }).then(() => {
                this.abrirModalDetalhe(iditem);
            });
        } catch (e) {
            Swal.fire({
                icon: 'warning',
                title: 'Aviso de Acesso',
                text: e.message || 'Não foi possível consultar os pedidos reservados.',
                confirmButtonColor: '#059669',
                customClass: { popup: 'rounded-3xl' }
            });
        }
    }
};

// Vinculação explícita no escopo global para handlers inline de HTML (onclick)
window.estoquePrevisaoApp = estoquePrevisaoApp;

// Auto inicialização
window.addEventListener('DOMContentLoaded', () => {
    estoquePrevisaoApp.init();
});


/**
 * Sistema Universal de Tooltip Moderno Flutuante para Estoque & Previsão
 */
function initModernTooltipsEstoque() {
    const tooltipEl = document.getElementById('modernTooltipEstoque');
    const badgeEl = document.getElementById('tooltipEstoqueBadge');
    const titleEl = document.getElementById('tooltipEstoqueTitle');
    const descEl = document.getElementById('tooltipEstoqueDesc');

    if (!tooltipEl || !titleEl || !descEl) return;
    if (window.__tooltipsEstoqueInitialized) return;
    window.__tooltipsEstoqueInitialized = true;

    let targetAtivo = null;

    document.addEventListener('mouseover', (e) => {
        const el = e.target.closest('[data-tooltip]');
        if (!el) return;

        targetAtivo = el;
        const rawContent = el.getAttribute('data-tooltip');
        if (!rawContent) return;

        let titulo = 'Informação';
        let descricao = rawContent;
        let badge = 'ESTOQUE';

        if (rawContent.includes('|')) {
            const partes = rawContent.split('|');
            titulo = partes[0].trim();
            descricao = partes.slice(1).join('|').trim();
        } else {
            titulo = el.innerText ? el.innerText.trim().slice(0, 30) : 'Estoque';
        }

        const descLower = (titulo + ' ' + descricao).toLowerCase();
        if (descLower.includes('reservado') || descLower.includes('pedido')) {
            badge = 'CARTEIRA';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-amber-400 bg-amber-950/90 px-2 py-0.5 rounded-md border border-amber-700/50';
        } else if (descLower.includes('livre') || descLower.includes('disponív')) {
            badge = 'DISPONÍVEL';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50';
        } else if (descLower.includes('previsão') || descLower.includes('compra') || descLower.includes('chegar') || descLower.includes('oc')) {
            badge = 'A CAMINHO';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-blue-400 bg-blue-950/90 px-2 py-0.5 rounded-md border border-blue-700/50';
        } else if (descLower.includes('futuro') || descLower.includes('projeção')) {
            badge = 'PROJEÇÃO';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-purple-400 bg-purple-950/90 px-2 py-0.5 rounded-md border border-purple-700/50';
        } else if (descLower.includes('crítico') || descLower.includes('zerad') || descLower.includes('atrasad') || descLower.includes('alerta')) {
            badge = 'ALERTA';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-rose-400 bg-rose-950/90 px-2 py-0.5 rounded-md border border-rose-700/50';
        } else if (descLower.includes('matriz') || descLower.includes('filial') || descLower.includes('unidade')) {
            badge = 'UNIDADE';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-indigo-400 bg-indigo-950/90 px-2 py-0.5 rounded-md border border-indigo-700/50';
        } else {
            badge = 'ESTOQUE';
            if (badgeEl) badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50';
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
    initModernTooltipsEstoque();
});
