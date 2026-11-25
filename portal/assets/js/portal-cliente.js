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
        whatsappAtivo: false,

        formLogin: { identificador: '', senha: '', canal_2fa: 'auto' },
        modalPref2fa: false,
        formPref2fa: { whatsapp: '', preferencia_2fa: 'email' },
        msgPref2fa: '',
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
        resumoCli: { boletos_qtd: 0, boletos_aberto: 0, boletos_vencido: 0, notas_qtd: 0, pedidos_qtd: 0, itens_qtd: 0, limite_credito: 0, limite_utilizado: 0, limite_disponivel: 0, total_comprado_12m: 0, ultima_compra: null },
        boletos: [],
        notas: [],
        pedidos: [],
        itensComprados: [],
        aba: 'boletos',
        dadosAbertosMobile: false,
        clienteAlvoUrl: '',
        toastMsg: '',
        _toastTimer: null,

        filtroTexto: '',
        filtroDataIni: '',
        filtroDataFim: '',
        filtroTituloTipo: 'todos',

        modalTituloDetalhe: false,
        tituloDetalhe: null,

        modalItens: false,
        modalTitulo: '',
        itensModal: [],

        modalDanfe: false,
        danfeNota: null,
        danfeDuplicatas: [],
        danfeItens: [],

        modalBoleto: false,
        boletoDoc: null,
        boletoBarcodeSvg: '',

        async init() {
            this.temTokenPortalInterno = Boolean(localStorage.getItem('authToken'));
            const params = new URLSearchParams(window.location.search);
            const modo = (params.get('modo') || '').toLowerCase();
            const idCliUrl = (params.get('idcliente') || params.get('cliente') || '').trim();
            if (idCliUrl) {
                this.clienteAlvoUrl = idCliUrl;
                this.buscaCliente = idCliUrl;
            }
            const b2bTok = localStorage.getItem('b2bToken') || '';

            // 1. Se abriu o módulo pelo Portal Interno (Admin, Gestor ou Representante já autenticado) e não está forçando modo=cliente: entra direto!
            if (this.temTokenPortalInterno && modo !== 'cliente') {
                try {
                    this.tokenAtivo = localStorage.getItem('authToken') || '';
                    this.autenticado = true;
                    await this.carregarContexto();
                    return;
                } catch (_) {
                    this.autenticado = false;
                    this.tokenAtivo = '';
                }
            }

            // 2. Se já possui sessão B2B ativa (Cliente ou Representante logado via /cliente ou /representante): entra direto!
            if (b2bTok) {
                try {
                    this.tokenAtivo = b2bTok;
                    this.autenticado = true;
                    await this.carregarContexto();
                    return;
                } catch (_) {
                    localStorage.removeItem('b2bToken');
                    this.autenticado = false;
                    this.tokenAtivo = '';
                }
            }

            // 3. Caso ainda não esteja autenticado, carrega configurações de login (Turnstile / WhatsApp)
            await this.carregarAuthConfig();
        },

        async carregarAuthConfig() {
            try {
                const r = await fetch(`${apiBase()}/portal-cliente/auth/config`);
                const d = await r.json();
                this.turnstileAtivo = Boolean(d.turnstile_ativo && d.turnstile_site_key);
                this.turnstileSiteKey = d.turnstile_site_key || '';
                this.whatsappAtivo = Boolean(d.whatsapp_ativo);
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
            const exigeTurnstile = this.turnstileAtivo && !path.includes('verificar-2fa') && !path.includes('redefinir-senha');
            if (exigeTurnstile && !this.turnstileToken) {
                throw new Error('Aguarde a verificação de segurança (Cloudflare) concluir antes de continuar.');
            }
            const resp = await fetch(`${apiBase()}${path}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...body, cf_turnstile_response: this.turnstileToken })
            });
            const data = await resp.json().catch(() => ({}));
            if (exigeTurnstile && window.turnstile) {
                this.turnstileToken = '';
                try { window.turnstile.reset(); } catch (_) {}
            }
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
                if (d.direct_token) {
                    localStorage.setItem('authToken', d.direct_token);
                    if (d.portal_user) {
                        localStorage.setItem('userData', JSON.stringify(d.portal_user));
                    }
                    this.temTokenPortalInterno = true;
                    this.tokenAtivo = d.direct_token;
                    this.autenticado = true;
                    this.etapa2fa = false;
                    await this.carregarContexto();
                    return;
                }
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
                if (d.portal_token) {
                    localStorage.setItem('authToken', d.portal_token);
                    if (d.portal_user) {
                        localStorage.setItem('userData', JSON.stringify(d.portal_user));
                    }
                    this.temTokenPortalInterno = true;
                }
                this.tokenAtivo = d.portal_token || d.token;
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
            if (this.escopo.conta_b2b) {
                this.formPref2fa = {
                    whatsapp: this.escopo.whatsapp || '',
                    preferencia_2fa: this.escopo.preferencia_2fa || 'email'
                };
            }
            this.representantes = d.representantes || [];
            this.clientes = d.clientes || [];
            if (this.clientes.length > 0) {
                if (this.clienteAlvoUrl && this.clientes.some(c => String(c.idcliforemp) === String(this.clienteAlvoUrl))) {
                    this.clienteSelecionadoId = String(this.clienteAlvoUrl);
                    this.clienteAlvoUrl = '';
                } else {
                    const jaExiste = this.clientes.some(c => String(c.idcliforemp) === String(this.clienteSelecionadoId));
                    if (!jaExiste) {
                        this.clienteSelecionadoId = String(this.clientes[0].idcliforemp);
                    }
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
            this.itensComprados = d.itens_comprados || [];
        },

        formatarUrlImagem(pathFoto) {
            if (!pathFoto || pathFoto === '.') return null;
            const norm = String(pathFoto).trim().replace(/\\/g, '/');
            const idx = norm.toLowerCase().indexOf('fotos para o site/');
            if (idx === -1) return null;
            const rel = norm.slice(idx + 'fotos para o site/'.length).replace(/^\/+/, '');
            return rel ? 'https://acesso.nutricionalbr.com:2053/fotos/' + encodeURI(rel) : null;
        },

        abrirTituloDetalhe(b) {
            this.tituloDetalhe = b;
            this.modalTituloDetalhe = true;
        },

        boletosFiltrados() {
            const q = this.filtroTexto.trim().toLowerCase();
            return this.boletos.filter(b => {
                if (this.filtroTituloTipo === 'vencidos' && b.dias_atraso <= 0) return false;
                if (this.filtroTituloTipo === 'avencer' && b.dias_atraso > 0) return false;
                if (this.filtroTituloTipo === 'boleto' && !b.tem_boleto) return false;
                if (this.filtroTituloTipo === 'outros' && b.tem_boleto) return false;
                if (this.filtroDataIni && String(b.vencimento || '') < this.filtroDataIni) return false;
                if (this.filtroDataFim && String(b.vencimento || '') > this.filtroDataFim) return false;
                if (q && !`${b.documento} ${b.nossonumero} ${b.portador_nome} ${b.numeronf || ''} ${b.pedidos || ''}`.toLowerCase().includes(q)) return false;
                return true;
            });
        },

        notasFiltradas() {
            const q = this.filtroTexto.trim().toLowerCase();
            return this.notas.filter(n => {
                if (this.filtroDataIni && String(n.dataemissao || '') < this.filtroDataIni) return false;
                if (this.filtroDataFim && String(n.dataemissao || '') > this.filtroDataFim) return false;
                if (q && !`${n.numeronf} ${n.chave_nfe} ${n.pedidos}`.toLowerCase().includes(q)) return false;
                return true;
            });
        },

        pedidosFiltrados() {
            const q = this.filtroTexto.trim().toLowerCase();
            return this.pedidos.filter(p => {
                if (this.filtroDataIni && String(p.data || '') < this.filtroDataIni) return false;
                if (this.filtroDataFim && String(p.data || '') > this.filtroDataFim) return false;
                if (q && !`${p.idpedido} ${p.vendedor} ${p.numeronf || ''} ${p.status_label || ''}`.toLowerCase().includes(q)) return false;
                return true;
            });
        },

        itensFiltrados() {
            const q = this.filtroTexto.trim().toLowerCase();
            return this.itensComprados.filter(it => !q || `${it.iditem} ${it.referencia} ${it.descricao} ${it.marca}`.toLowerCase().includes(q));
        },

        async abrirItens(tipo, obj) {
            const id = tipo === 'nota' ? obj.idnfs : obj.idpedido;
            this.modalTitulo = tipo === 'nota' ? `Itens da NF ${obj.numeronf}` : `Itens do Pedido #${obj.idpedido}`;
            this.itensModal = [];
            this.modalItens = true;
            try {
                const d = await this.apiAuth(`/portal-cliente/${tipo}/${obj.idfilial}/${id}/itens`);
                this.itensModal = d.itens || [];
            } catch (e) {
                this.mostrarToast(e.message || 'Erro ao carregar itens.');
            }
        },

        async abrirDanfe(n) {
            try {
                const d = await this.apiAuth(`/portal-cliente/nota/${n.idfilial}/${n.idnfs}/danfe`);
                this.danfeNota = d.nota;
                this.danfeDuplicatas = d.duplicatas || [];
                this.danfeItens = d.itens || [];
                this.modalDanfe = true;
            } catch (e) {
                this.mostrarToast(e.message || 'Erro ao carregar DANFE.');
            }
        },

        async abrirBoleto(b) {
            try {
                const d = await this.apiAuth(`/portal-cliente/boleto/${b.idfilial}/${b.idreceber}/detalhe`);
                this.boletoDoc = d.boleto;
                this.boletoBarcodeSvg = this.gerarSvgItf25(d.boleto?.codigobarra || '');
                this.modalBoleto = true;
                setTimeout(() => {
                    const box = document.getElementById('boleto-qrcode-box');
                    if (box) {
                        box.innerHTML = '';
                        const qr = d.boleto?.shipayqrcode || '';
                        if (qr && typeof QRCode !== 'undefined') {
                            new QRCode(box, { text: qr, width: 108, height: 108, correctLevel: QRCode.CorrectLevel.M });
                        }
                    }
                }, 60);
            } catch (e) {
                this.mostrarToast(e.message || 'Erro ao carregar boleto.');
            }
        },

        limparFone(f) {
            return String(f || '').replace(/\D/g, '');
        },

        linkWhats(f, msg = '') {
            const d = this.limparFone(f);
            if (!d) return '#';
            const num = d.startsWith('55') ? d : '55' + d;
            return `https://wa.me/${num}` + (msg ? `?text=${encodeURIComponent(msg)}` : '');
        },

        montarMensagemContato(destino, docTipo = '', docRef = '', extra = '') {
            const cod = this.clienteAtual?.idcliforemp || '';
            const nome = this.clienteAtual?.razao || '';
            const prefixo = destino === 'financeiro'
                ? 'Olá Financeiro Nutricional!'
                : 'Olá!';
            const ident = cod ? ` Código do Cliente: ${cod} (${nome}).` : '';
            const docParte = docTipo && docRef
                ? ` Referente ao ${docTipo} ${docRef}${extra ? ' - ' + extra : ''},`
                : '';
            return `${prefixo}${ident}${docParte} gostaria de tirar a seguinte dúvida: `;
        },

        whatsFinanceiro(docTipo = '', docRef = '', extra = '') {
            return this.linkWhats('4835246543', this.montarMensagemContato('financeiro', docTipo, docRef, extra));
        },

        whatsRepresentante(docTipo = '', docRef = '', extra = '') {
            const fone = this.clienteAtual?.vendedor_celular || this.clienteAtual?.vendedor_fone || '4835246543';
            return this.linkWhats(fone, this.montarMensagemContato('representante', docTipo, docRef, extra));
        },

        abrirNoCrm() {
            if (!this.clienteAtual || this.escopo?.perfil === 'CLIENTE') return;
            window.location.href = `${appBase}/portal/modules/marketing/clientes.php?cliente=${this.clienteAtual.idcliforemp}`;
        },

        imprimirDocumento(tipo) {
            const cls = tipo === 'boleto' ? 'print-boleto' : 'print-danfe';
            document.body.classList.add(cls);
            window.print();
            setTimeout(() => document.body.classList.remove(cls), 500);
        },

        gerarSvgItf25(codigo) {
            const dig = String(codigo || '').replace(/\D/g, '');
            if (dig.length < 44 || dig.length % 2 !== 0) return '';
            const tbl = ['00110','10001','01001','11000','00101','10100','01100','00011','10010','01010'];
            let bits = '0000';
            for (let i = 0; i < dig.length; i += 2) {
                const b = tbl[Number(dig[i])];
                const s = tbl[Number(dig[i + 1])];
                for (let j = 0; j < 5; j++) bits += b[j] + s[j];
            }
            bits += '100';
            let x = 0;
            let rects = '';
            for (let i = 0; i < bits.length; i++) {
                const w = bits[i] === '1' ? 3 : 1;
                if (i % 2 === 0) rects += `<rect x="${x}" y="0" width="${w}" height="52" fill="#000"/>`;
                x += w;
            }
            return `<svg viewBox="0 0 ${x} 52" class="w-full max-w-[420px] h-[52px]" preserveAspectRatio="none">${rects}</svg>`;
        },

        fmtChave(chave) {
            const d = String(chave || '').replace(/\D/g, '');
            return d ? d.replace(/(.{4})/g, '$1 ').trim() : '-';
        },

        async salvarPref2fa() {
            this.msgPref2fa = '';
            const resp = await fetch(`${apiBase()}/portal-cliente/preferencias-2fa`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Authorization': `Bearer ${this.tokenAtivo}` },
                body: JSON.stringify(this.formPref2fa)
            });
            const d = await resp.json().catch(() => ({}));
            if (!resp.ok || d.success === false) {
                this.mostrarToast(d.error || 'Erro ao salvar preferências.');
                return;
            }
            this.msgPref2fa = d.message;
            if (this.escopo) {
                this.escopo.whatsapp = d.whatsapp || '';
                this.escopo.preferencia_2fa = d.preferencia_2fa || 'email';
            }
        },

        irParaAba(nome) {
            this.aba = nome;
            this.$nextTick(() => {
                const el = document.getElementById('b2b-secao-abas');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },

        mostrarToast(msg) {
            this.toastMsg = msg;
            if (this._toastTimer) clearTimeout(this._toastTimer);
            this._toastTimer = setTimeout(() => { this.toastMsg = ''; }, 2600);
        },

        copiarTexto(txt, label = 'Copiado') {
            if (!txt) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(txt).then(() => {
                    this.mostrarToast(`${label} com sucesso!`);
                }).catch(() => {
                    window.prompt('Copie o código abaixo:', txt);
                });
            } else {
                window.prompt('Copie o código abaixo:', txt);
            }
        },

        sair() {
            localStorage.removeItem('b2bToken');
            this.tokenAtivo = '';
            this.autenticado = false;
            if (!this.turnstileSiteKey) {
                this.carregarAuthConfig();
            }
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
