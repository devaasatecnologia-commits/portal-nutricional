// ==========================================================================
// PORTAL DO CLIENTE & HIERARQUIA COMERCIAL B2B + AUTH 2FA (#66)
// ==========================================================================

function portalClienteHandler(appBase = '') {
    const apiBase = () => (typeof API_BASE_URL !== 'undefined' ? API_BASE_URL : `${appBase}/v1`);

    return {
        autenticado: false,
        temTokenPortalInterno: false,
        tokenAtivo: '',

        // Auth UI
        modoAuth: 'login',
        etapa2fa: false,
        etapaReset: false,
        loadingAuth: false,
        erroAuth: '',
        msgAuth: '',
        emailMascarado: '',
        desafioId: 0,

        turnstileAtivo: false,
        turnstileSiteKey: '',
        turnstileToken: '',

        formLogin: { identificador: '', senha: '' },
        form2fa: { codigo: '' },
        formCadastro: { cnpj_cpf: '', email: '', senha: '' },
        formReset: { identificador: '', codigo: '', nova_senha: '' },

        // Área Logada
        escopo: { perfil: '', nome: '' },
        representantes: [],
        clientes: [],
        filtroRep: '0',
        buscaCliente: '',
        clienteSelecionadoId: '',
        clienteAtual: null,
        resumoCli: { boletos_qtd: 0, boletos_aberto: 0, boletos_vencido: 0, notas_qtd: 0, pedidos_qtd: 0 },
        boletos: [],
        notas: [],
        pedidos: [],
        aba: 'boletos',

        modalItens: false,
        modalTitulo: '',
        itensModal: [],

        async init() {
            this.temTokenPortalInterno = Boolean(localStorage.getItem('authToken'));
            const b2bTok = localStorage.getItem('b2bToken') || '';
            if (b2bTok) {
                this.tokenAtivo = b2bTok;
                this.autenticado = true;
                await this.carregarContexto();
                return;
            }
            await this.carregarAuthConfig();
        },

        async carregarAuthConfig() {
            try {
                const r = await fetch(`${apiBase()}/portal-cliente/auth/config`);
                const d = await r.json();
                this.turnstileAtivo = Boolean(d.turnstile_ativo && d.turnstile_site_key);
                this.turnstileSiteKey = d.turnstile_site_key || '';
                if (this.turnstileAtivo) {
                    this.montarTurnstile();
                }
            } catch (e) {}
        },

        montarTurnstile() {
            const s = document.createElement('script');
            s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
            s.async = true;
            s.defer = true;
            s.onload = () => {
                if (window.turnstile && document.getElementById('turnstile-container')) {
                    window.turnstile.render('#turnstile-container', {
                        sitekey: this.turnstileSiteKey,
                        callback: (t) => { this.turnstileToken = t; }
                    });
                }
            };
            document.head.appendChild(s);
        },

        async apiPublic(path, body) {
            const resp = await fetch(`${apiBase()}${path}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...body, cf_turnstile_response: this.turnstileToken })
            });
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || data.success === false) {
                throw new Error(data.error || `Erro HTTP ${resp.status}`);
            }
            return data;
        },

        async apiAuth(path) {
            const resp = await fetch(`${apiBase()}${path}`, {
                headers: { 'Authorization': `Bearer ${this.tokenAtivo}` }
            });
            if (resp.status === 401) {
                this.sair();
                throw new Error('Sessão expirada.');
            }
            const data = await resp.json().catch(() => ({}));
            if (!resp.ok || data.success === false) {
                throw new Error(data.error || `Erro HTTP ${resp.status}`);
            }
            return data;
        },

        async entrarComSessaoPortal() {
            const tok = localStorage.getItem('authToken') || '';
            if (!tok) return;
            this.tokenAtivo = tok;
            this.autenticado = true;
            await this.carregarContexto();
        },

        async enviarLogin() {
            this.erroAuth = '';
            this.msgAuth = '';
            this.loadingAuth = true;
            try {
                const d = await this.apiPublic('/portal-cliente/auth/login', this.formLogin);
                this.desafioId = d.desafio_id;
                this.emailMascarado = d.email_mascarado;
                this.etapa2fa = true;
                this.msgAuth = d.message || 'Código enviado por e-mail.';
            } catch (e) {
                this.erroAuth = e.message;
            } finally {
                this.loadingAuth = false;
            }
        },

        async confirmar2fa() {
            this.erroAuth = '';
            this.loadingAuth = true;
            try {
                const d = await this.apiPublic('/portal-cliente/auth/verificar-2fa', {
                    desafio_id: this.desafioId,
                    codigo: this.form2fa.codigo
                });
                localStorage.setItem('b2bToken', d.token);
                this.tokenAtivo = d.token;
                this.autenticado = true;
                this.etapa2fa = false;
                await this.carregarContexto();
            } catch (e) {
                this.erroAuth = e.message;
            } finally {
                this.loadingAuth = false;
            }
        },

        async enviarCadastro() {
            this.erroAuth = '';
            this.msgAuth = '';
            this.loadingAuth = true;
            try {
                const d = await this.apiPublic('/portal-cliente/auth/solicitar-cadastro', this.formCadastro);
                this.msgAuth = d.message;
                this.formCadastro = { cnpj_cpf: '', email: '', senha: '' };
            } catch (e) {
                this.erroAuth = e.message;
            } finally {
                this.loadingAuth = false;
            }
        },

        async solicitarReset() {
            this.erroAuth = '';
            this.msgAuth = '';
            this.loadingAuth = true;
            try {
                const d = await this.apiPublic('/portal-cliente/auth/esqueceu-senha', { identificador: this.formReset.identificador });
                this.desafioId = d.desafio_id;
                this.etapaReset = true;
                this.msgAuth = d.message;
            } catch (e) {
                this.erroAuth = e.message;
            } finally {
                this.loadingAuth = false;
            }
        },

        async confirmarReset() {
            this.erroAuth = '';
            this.loadingAuth = true;
            try {
                const d = await this.apiPublic('/portal-cliente/auth/redefinir-senha', {
                    desafio_id: this.desafioId,
                    codigo: this.formReset.codigo,
                    nova_senha: this.formReset.nova_senha
                });
                this.msgAuth = d.message;
                this.etapaReset = false;
                this.modoAuth = 'login';
            } catch (e) {
                this.erroAuth = e.message;
            } finally {
                this.loadingAuth = false;
            }
        },

        async carregarContexto() {
            const qs = new URLSearchParams({
                idvendedor: this.filtroRep,
                busca: this.buscaCliente
            }).toString();
            const d = await this.apiAuth(`/portal-cliente/contexto?${qs}`);
            this.escopo = d.escopo || {};
            this.representantes = d.representantes || [];
            this.clientes = d.clientes || [];
            if (this.clientes.length > 0) {
                const jaExiste = this.clientes.some(c => String(c.idcliforemp) === String(this.clienteSelecionadoId));
                if (!jaExiste) {
                    this.clienteSelecionadoId = String(this.clientes[0].idcliforemp);
                }
                await this.carregarClienteResumo(this.clienteSelecionadoId);
            } else {
                this.clienteAtual = null;
                this.boletos = [];
                this.notas = [];
                this.pedidos = [];
            }
        },

        async carregarClienteResumo(id) {
            if (!id) return;
            const d = await this.apiAuth(`/portal-cliente/cliente/${id}/resumo`);
            this.clienteAtual = d.cliente;
            this.resumoCli = d.resumo;
            this.boletos = d.boletos || [];
            this.notas = d.notas || [];
            this.pedidos = d.pedidos || [];
        },

        async abrirItens(tipo, obj) {
            const id = tipo === 'nota' ? obj.idnfs : obj.idpedido;
            this.modalTitulo = tipo === 'nota' ? `Itens da NF ${obj.numeronf}` : `Itens do Pedido #${obj.idpedido}`;
            this.itensModal = [];
            this.modalItens = true;
            const d = await this.apiAuth(`/portal-cliente/${tipo}/${obj.idfilial}/${id}/itens`);
            this.itensModal = d.itens || [];
        },

        copiarTexto(txt) {
            navigator.clipboard?.writeText(txt);
            alert('Copiado: ' + txt);
        },

        sair() {
            localStorage.removeItem('b2bToken');
            this.tokenAtivo = '';
            this.autenticado = false;
        },

        fmtMoeda(v) {
            return Number(v || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        },

        fmtData(d) {
            if (!d) return '-';
            const p = String(d).slice(0, 10).split('-');
            return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : d;
        }
    };
}
