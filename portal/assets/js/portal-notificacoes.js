/**
 * Portal Nutricional — Gerenciador Universal de Notificações Web & Mobile
 * Suporte a Web Notifications API, Service Worker (PWA Mobile & Desktop Windows),
 * Badging API (Ícone na Barra de Tarefas e Celular), Favicon Dinâmico SVG e Alertas Globais.
 */

(function (window) {
    'use strict';

    const PortalNotificacoes = {
        swRegistration: null,
        compromissosNotificados: new Set(),
        notificacoesProcessadas: new Set(),
        mensagensChatNotificadas: new Set(),
        chamadosNotificados: new Set(),
        audioCtx: null,
        tituloOriginal: document.title,
        faviconOriginal: null,
        totalNaoLidas: 0,
        intervaloPiscarTitulo: null,
        estaVisivel: !document.hidden,

        /**
         * Inicialização do Gerenciador de Notificações
         */
        async init() {
            this.tituloOriginal = document.title;
            this.salvarFaviconOriginal();

            // Recupera IDs notificados na sessão
            try {
                const salvosComp = sessionStorage.getItem('compromissos_notificados_crm');
                if (salvosComp) JSON.parse(salvosComp).forEach(id => this.compromissosNotificados.add(id));
                const salvosChat = sessionStorage.getItem('chat_mensagens_notificadas');
                if (salvosChat) JSON.parse(salvosChat).forEach(id => this.mensagensChatNotificadas.add(id));
                const salvosCham = sessionStorage.getItem('chamados_notificados');
                if (salvosCham) JSON.parse(salvosCham).forEach(id => this.chamadosNotificados.add(id));
            } catch (_) {}

            // Aguarda Service Worker estar pronto (PWA Mobile e Desktop)
            if ('serviceWorker' in navigator) {
                try {
                    this.swRegistration = await navigator.serviceWorker.ready;
                } catch (e) {
                    console.log('[Notificações] Service worker não pronto:', e);
                }
            }

            // Monitora visibilidade da aba para gerenciar alertas no título e badges
            document.addEventListener('visibilitychange', () => {
                this.estaVisivel = !document.hidden;
                if (this.estaVisivel) {
                    this.pararPiscarTitulo();
                    this.atualizarTituloAba();
                }
            });

            // Inicia o monitoramento unificado em tempo real
            this.iniciarMonitoramentoUnificado();
            this.verificarBannerPermissao();
        },

        /**
         * Salva o favicon original da página para restauração dinâmica
         */
        salvarFaviconOriginal() {
            const link = document.querySelector("link[rel*='icon']");
            if (link) {
                this.faviconOriginal = link.href;
            } else {
                const appBase = (window.location.pathname.startsWith('/API/') || window.location.pathname === '/API') ? '/API' : '';
                this.faviconOriginal = `${appBase}/portal/assets/icons/android-chrome-192x192.png`;
            }
        },

        /**
         * Helper para requisições unificadas (respeita local XAMPP e produção)
         */
        async requisitar(endpoint, options = {}) {
            const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
            let clean = endpoint || '';
            if (clean.startsWith('/v1/')) clean = clean.substring(4);
            if (clean.startsWith('v1/')) clean = clean.substring(3);
            clean = clean.replace(/^\/+/, '');

            const baseUrl = window.API_URL || '/v1';
            let url;
            if (baseUrl.endsWith('=')) {
                url = baseUrl + '/' + clean;
            } else {
                url = baseUrl.replace(/\/+$/, '') + '/' + clean;
            }

            const headers = Object.assign({
                'Content-Type': 'application/json'
            }, token ? { 'Authorization': 'Bearer ' + token } : {}, options.headers || {});

            try {
                const resp = await fetch(url, Object.assign({}, options, { headers }));
                if (!resp.ok) return null;
                return await resp.json();
            } catch (e) {
                console.warn('[Notificações] Falha na requisição:', e);
                return null;
            }
        },

        /**
         * Toca um chime corporativo suave e agradável via Web Audio API (sem arquivos externos)
         */
        tocarSom() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                
                if (!this.audioCtx) {
                    this.audioCtx = new AudioContext();
                }
                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }

                const agora = this.audioCtx.currentTime;
                
                // Acorde elegante 1 (880Hz - A5)
                const osc1 = this.audioCtx.createOscillator();
                const gain1 = this.audioCtx.createGain();
                osc1.type = 'sine';
                osc1.frequency.setValueAtTime(880, agora);
                gain1.gain.setValueAtTime(0.12, agora);
                gain1.gain.exponentialRampToValueAtTime(0.001, agora + 0.35);
                osc1.connect(gain1);
                gain1.connect(this.audioCtx.destination);
                osc1.start(agora);
                osc1.stop(agora + 0.35);

                // Acorde elegante 2 (1320Hz - E6)
                const osc2 = this.audioCtx.createOscillator();
                const gain2 = this.audioCtx.createGain();
                osc2.type = 'sine';
                osc2.frequency.setValueAtTime(1320, agora + 0.12);
                gain2.gain.setValueAtTime(0.15, agora + 0.12);
                gain2.gain.exponentialRampToValueAtTime(0.001, agora + 0.55);
                osc2.connect(gain2);
                gain2.connect(this.audioCtx.destination);
                osc2.start(agora + 0.12);
                osc2.stop(agora + 0.55);
            } catch (e) {
                console.warn('[Notificações] Falha ao sintetizar som:', e);
            }
        },

        /**
         * Solicita permissão do navegador para notificações na barra/celular
         */
        async solicitarPermissao() {
            if (!('Notification' in window)) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Navegador sem suporte',
                        text: 'Este navegador não suporta notificações nativas na barra do sistema.',
                        confirmButtonColor: '#274036'
                    });
                }
                return false;
            }

            try {
                const permissao = await Notification.requestPermission();
                if (permissao === 'granted') {
                    this.esconderBannerPermissao();
                    this.notificar({
                        titulo: '🔔 Notificações Ativadas!',
                        corpo: 'Você receberá alertas de chamados, agenda, compromissos e chat no seu dispositivo.',
                        som: true
                    });
                    return true;
                }
                return false;
            } catch (e) {
                console.error('[Notificações] Erro ao solicitar permissão:', e);
                return false;
            }
        },

        /**
         * Atualiza os Badges Nativos no Ícone do App (Barra de Tarefas Windows & Mobile) e no Favicon SVG
         */
        atualizarBadges(totalNotifs, totalChat = null) {
            this.totalNotifs = Math.max(0, parseInt(totalNotifs) || 0);
            if (totalChat !== null) {
                this.totalChat = Math.max(0, parseInt(totalChat) || 0);
            } else if (typeof this.totalChat === 'undefined') {
                this.totalChat = 0;
            }

            this.totalNaoLidas = this.totalNotifs + this.totalChat;

            // 1. App Badging API da W3C (Ícone do Windows na barra de tarefas e ícone do PWA no Celular)
            if ('setAppBadge' in navigator) {
                if (this.totalNaoLidas > 0) {
                    navigator.setAppBadge(this.totalNaoLidas).catch(() => {});
                } else if ('clearAppBadge' in navigator) {
                    navigator.clearAppBadge().catch(() => {});
                }
            }

            // 2. Favicon Dinâmico com SVG Data URI (Imediato, livre de CORS)
            this.atualizarFaviconComBadge(this.totalNaoLidas);

            // 3. Título da Aba
            this.atualizarTituloAba();

            // 4. Badges do Sino do Portal (Central de Alertas) - SEPARADO DO CHAT
            const badgesSino = document.querySelectorAll('.notification-badge-geral');
            badgesSino.forEach(badge => {
                if (this.totalNotifs > 0) {
                    badge.textContent = this.totalNotifs > 99 ? '99+' : this.totalNotifs;
                    badge.classList.remove('hidden');
                    badge.style.display = 'flex';
                } else {
                    badge.classList.add('hidden');
                    badge.style.display = 'none';
                }
            });

            // 5. Badges do Chat Corporativo (Mensagens) - CANAL EXCLUSIVO
            const badgesChat = document.querySelectorAll('.sidebar-chat-badge, #chatBadge, #sidebarChatBadge');
            badgesChat.forEach(badge => {
                if (this.totalChat > 0) {
                    badge.textContent = this.totalChat > 99 ? '99+' : this.totalChat;
                    badge.classList.remove('hidden');
                    badge.style.display = 'flex';
                } else {
                    badge.classList.add('hidden');
                    badge.style.display = 'none';
                }
            });
        },

        /**
         * Gera Favicon SVG vetorial nítido com badge vermelho instantâneo (sem CORS)
         */
        atualizarFaviconComBadge(count) {
            try {
                if (count <= 0) {
                    if (this.faviconOriginal) {
                        const antigos = document.querySelectorAll("link[rel*='icon']");
                        antigos.forEach(el => el.remove());
                        const link = document.createElement('link');
                        link.rel = 'icon';
                        link.href = this.faviconOriginal;
                        document.head.appendChild(link);
                    }
                    return;
                }

                const texto = count > 99 ? '99+' : String(count);
                const svg = `
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
                    <rect width="64" height="64" rx="14" fill="#047857"/>
                    <path d="M32 16 C22 16 18 26 18 36 C18 46 25 48 32 48 C39 48 46 46 46 36 C46 26 42 16 32 16 Z" fill="#34d399"/>
                    <line x1="32" y1="16" x2="32" y2="48" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/>
                    <circle cx="48" cy="16" r="15" fill="#ef4444" stroke="#ffffff" stroke-width="2.5"/>
                    <text x="48" y="21" font-size="${texto.length > 2 ? '11' : '14'}" font-family="Arial, sans-serif" font-weight="900" fill="#ffffff" text-anchor="middle">${texto}</text>
                </svg>
                `.trim();

                const dataUri = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
                
                // Remove favicons anteriores para forçar navegadores baseados em Chromium a repintar imediatamente a aba
                const antigos = document.querySelectorAll("link[rel*='icon']");
                antigos.forEach(el => el.remove());

                const novoIcon = document.createElement('link');
                novoIcon.rel = 'icon';
                novoIcon.type = 'image/svg+xml';
                novoIcon.href = dataUri;
                document.head.appendChild(novoIcon);

                const novoShortcut = document.createElement('link');
                novoShortcut.rel = 'shortcut icon';
                novoShortcut.type = 'image/svg+xml';
                novoShortcut.href = dataUri;
                document.head.appendChild(novoShortcut);
            } catch (e) {
                console.warn('[Notificações] Falha ao atualizar favicon SVG:', e);
            }
        },

        /**
         * Atualiza o título da aba com o contador de pendências
         */
        atualizarTituloAba() {
            if (this.intervaloPiscarTitulo) return;
            if (this.totalNaoLidas > 0) {
                document.title = `(${this.totalNaoLidas}) ${this.tituloOriginal}`;
            } else {
                document.title = this.tituloOriginal;
            }
        },

        /**
         * Faz o título da aba piscar para chamar a atenção caso a página esteja minimizada
         */
        piscarTituloAba(mensagemAlerta) {
            this.pararPiscarTitulo();
            let estado = false;
            this.intervaloPiscarTitulo = setInterval(() => {
                if (this.estaVisivel) {
                    this.pararPiscarTitulo();
                    this.atualizarTituloAba();
                    return;
                }
                document.title = estado 
                    ? `🔔 ${mensagemAlerta}` 
                    : (this.totalNaoLidas > 0 ? `(${this.totalNaoLidas}) ${this.tituloOriginal}` : this.tituloOriginal);
                estado = !estado;
            }, 1200);
        },

        pararPiscarTitulo() {
            if (this.intervaloPiscarTitulo) {
                clearInterval(this.intervaloPiscarTitulo);
                this.intervaloPiscarTitulo = null;
            }
        },

        /**
         * Toca um som suave e elegante sintetizado via Web Audio API (estilo chime/pop discreto)
         */
        tocarSom() {
            try {
                const AudioContext = window.AudioContext || window.webkitAudioContext;
                if (!AudioContext) return;
                if (!this.audioCtx) {
                    this.audioCtx = new AudioContext();
                }
                if (this.audioCtx.state === 'suspended') {
                    this.audioCtx.resume();
                }
                
                const now = this.audioCtx.currentTime;
                const osc = this.audioCtx.createOscillator();
                const gain = this.audioCtx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, now); // D5
                osc.frequency.exponentialRampToValueAtTime(880, now + 0.08); // A5

                gain.gain.setValueAtTime(0.15, now);
                gain.gain.exponentialRampToValueAtTime(0.001, now + 0.28);

                osc.connect(gain);
                gain.connect(this.audioCtx.destination);

                osc.start(now);
                osc.stop(now + 0.30);
            } catch (_) {}
        },

        /**
         * Dispara uma notificação para o usuário (Navegador Desktop + PWA Mobile + Toast na tela)
         */
        async notificar({ titulo, corpo, icone, url, tag, som = true, dados = {} }) {
            if (som) {
                this.tocarSom();
            }

            const appBase = (window.location.pathname.startsWith('/API/') || window.location.pathname === '/API') ? '/API' : '';
            const iconePadrao = icone || `${appBase}/portal/assets/icons/android-chrome-192x192.png`;
            const urlDestino = url || window.location.href;
            const notifTag = tag || ('notif_' + Date.now());

            // Alerta no título caso a janela esteja oculta/minimizada
            if (!this.estaVisivel) {
                this.piscarTituloAba(titulo);
            }

            // 1. Notificação nativa no sistema operacional (Desktop Windows barra / Celular barra)
            if ('Notification' in window && Notification.permission === 'granted') {
                try {
                    // Tenta via Service Worker Registration (PWA Mobile e Background)
                    if (this.swRegistration && 'showNotification' in this.swRegistration) {
                        await this.swRegistration.showNotification(titulo, {
                            body: corpo,
                            icon: iconePadrao,
                            badge: `${appBase}/portal/assets/icons/apple-touch-icon.png`,
                            data: { url: urlDestino, ...dados },
                            tag: notifTag,
                            renotify: true,
                            vibrate: [200, 100, 200]
                        });
                    } else {
                        // Fallback construtor de Notificação
                        const n = new Notification(titulo, {
                            body: corpo,
                            icon: iconePadrao,
                            tag: notifTag,
                            data: { url: urlDestino, ...dados }
                        });
                        n.onclick = function (event) {
                            event.preventDefault();
                            window.focus();
                            if (urlDestino && urlDestino !== window.location.href) {
                                window.location.href = urlDestino;
                            }
                            n.close();
                        };
                    }
                } catch (errNotif) {
                    console.warn('[Notificações] Falha ao disparar notificação nativa:', errNotif);
                }
            }

            // 2. Banner/Toast em tela se o portal estiver visível
            this.exibirToastEmTela(titulo, corpo, urlDestino);
        },

        /**
         * Exibe um toast popover elegante na página
         */
        exibirToastEmTela(titulo, corpo, urlDestino) {
            let container = document.getElementById('portalToastContainerGlobal');
            if (!container) {
                container = document.createElement('div');
                container.id = 'portalToastContainerGlobal';
                container.className = 'fixed bottom-4 right-4 z-[99999] flex flex-col gap-2.5 pointer-events-none max-w-sm w-full px-3';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto bg-slate-900/95 text-white border border-slate-700/80 rounded-2xl p-4 shadow-2xl backdrop-blur-md transition-all duration-300 transform translate-y-3 opacity-0 flex items-start gap-3 cursor-pointer hover:bg-slate-800';
            toast.innerHTML = `
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center flex-shrink-0 mt-0.5">
                    <i class="fa-solid fa-bell text-sm"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="font-black text-xs text-white leading-snug truncate">${this.escapeHtml(titulo)}</div>
                    <div class="text-[11px] text-slate-300 leading-relaxed mt-0.5 line-clamp-2">${this.escapeHtml(corpo)}</div>
                </div>
                <button type="button" class="text-slate-400 hover:text-white p-1" onclick="event.stopPropagation(); this.closest('div').remove()">
                    <i class="fa-solid fa-times text-xs"></i>
                </button>
            `;

            toast.onclick = () => {
                if (urlDestino && urlDestino !== window.location.href) {
                    window.location.href = urlDestino;
                }
                toast.remove();
            };

            container.appendChild(toast);

            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-3', 'opacity-0');
            });

            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-x-4');
                setTimeout(() => toast.remove(), 300);
            }, 8000);
        },

        /**
         * Monitoramento Central Unificado Global (executado em todo o portal)
         */
        iniciarMonitoramentoUnificado() {
            const checar = async () => {
                const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
                if (!token) return;

                try {
                    const data = await this.requisitar('/notificacoes/resumo-geral');
                    if (!data || !data.success) return;

                    this.ultimosDados = data;

                    const appBase = (window.location.pathname.startsWith('/API/') || window.location.pathname === '/API') ? '/API' : '';

                    // 1. Mensagens de Chat
                    if (data.chat && Array.isArray(data.chat.mensagens)) {
                        data.chat.mensagens.forEach(msg => {
                            const idMsg = msg.id || msg.idmensagem;
                            const chaveMsg = `chat_msg_${idMsg}`;
                            if (!this.mensagensChatNotificadas.has(chaveMsg)) {
                                this.mensagensChatNotificadas.add(chaveMsg);
                                try {
                                    sessionStorage.setItem('chat_mensagens_notificadas', JSON.stringify([...this.mensagensChatNotificadas]));
                                } catch (_) {}

                                this.notificar({
                                    titulo: `💬 Mensagem de ${msg.remetente_nome || 'Usuário'}`,
                                    corpo: msg.mensagem || 'Você recebeu uma nova mensagem no chat corporativo.',
                                    url: `${appBase}/portal/modules/minhas-mensagens.php?contato=${msg.idusuario_remetente}`,
                                    tag: chaveMsg,
                                    som: true
                                });
                            }
                        });
                    }

                    // 2. Compromissos de Agenda (Lembrete Prévio e Alarme no Horário Exato)
                    if (data.compromissos && Array.isArray(data.compromissos.lista)) {
                        data.compromissos.lista.forEach(c => {
                            const min = Math.round(Number(c.minutos_para_inicio) || 0);
                            const horaFormatada = c.data_hora ? new Date(c.data_hora).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : '';
                            const cliente = c.cliente_nome || 'Cliente';
                            const pauta = c.titulo || c.tipo || 'Compromisso';

                            // CASO A: Horário do compromisso atingido (Faltam 2 minutos ou atrasado em até 8 minutos)
                            if (min <= 2 && min >= -8) {
                                const chaveNaHora = `comp_${c.id}_alarme_na_hora`;
                                if (!this.compromissosNotificados.has(chaveNaHora)) {
                                    this.compromissosNotificados.add(chaveNaHora);
                                    try {
                                        sessionStorage.setItem('compromissos_notificados_crm', JSON.stringify([...this.compromissosNotificados]));
                                    } catch (_) {}

                                    this.notificar({
                                        titulo: `⏰ HORÁRIO DO COMPROMISSO: ${pauta}`,
                                        corpo: `${cliente} agendado para ${horaFormatada} (Iniciando agora!)`,
                                        url: `${appBase}/portal/modules/marketing/clientes.php?buscar=${c.id_cliente || ''}`,
                                        tag: chaveNaHora,
                                        som: true
                                    });
                                }
                            }
                            // CASO B: Lembrete com antecedência (de 3 a 15 minutos antes)
                            else if (min > 2 && min <= 15) {
                                const chavePrevio = `comp_${c.id}_lembrete_previo`;
                                if (!this.compromissosNotificados.has(chavePrevio)) {
                                    this.compromissosNotificados.add(chavePrevio);
                                    try {
                                        sessionStorage.setItem('compromissos_notificados_crm', JSON.stringify([...this.compromissosNotificados]));
                                    } catch (_) {}

                                    this.notificar({
                                        titulo: `📅 Lembrete de Compromisso: ${pauta}`,
                                        corpo: `${cliente} às ${horaFormatada} (Começa em ${min} minutos)`,
                                        url: `${appBase}/portal/modules/marketing/clientes.php?buscar=${c.id_cliente || ''}`,
                                        tag: chavePrevio,
                                        som: true
                                    });
                                }
                            }
                        });
                    }

                    // 3. Chamados Corporativos (Abertos e Atualizados)
                    if (data.chamados && Array.isArray(data.chamados.lista)) {
                        data.chamados.lista.forEach(ch => {
                            const chaveChamado = `chamado_${ch.idchamado}_${ch.datahora || ''}`;
                            if (!this.chamadosNotificados.has(chaveChamado)) {
                                this.chamadosNotificados.add(chaveChamado);
                                try {
                                    sessionStorage.setItem('chamados_notificados', JSON.stringify([...this.chamadosNotificados]));
                                } catch (_) {}

                                const critIcon = (ch.criticidade === 'critica' || ch.criticidade === 'alta') ? '🚨' : '📋';
                                this.notificar({
                                    titulo: `${critIcon} Chamado #${ch.idchamado}: ${ch.titulo || 'Suporte'}`,
                                    corpo: `Módulo: ${ch.modulo_nome || 'Geral'} | Solicitante: ${ch.solicitante || 'Equipe'}`,
                                    url: `${appBase}/portal/modules/chamados.php?id=${ch.idchamado}`,
                                    tag: chaveChamado,
                                    som: true
                                });
                            }
                        });
                    }

                    // 4. Notificações do Sistema Gerais
                    if (data.sistema && Array.isArray(data.sistema.lista)) {
                        data.sistema.lista.forEach(n => {
                            const chave = `sys_notif_${n.id}`;
                            if (!this.notificacoesProcessadas.has(chave)) {
                                this.notificacoesProcessadas.add(chave);

                                this.notificar({
                                    titulo: n.titulo || 'Notificação Nutricional',
                                    corpo: n.mensagem || 'Você possui um novo aviso no portal.',
                                    url: n.link ? `${appBase}${n.link}` : `${appBase}/portal/`,
                                    tag: chave,
                                    som: true
                                });
                            }
                        });
                    }

                    // 5. Atualiza Badges Globais Separados (Sino e Chat com contadores independentes)
                    const totalChatAtual = (data.chat && typeof data.chat.total !== 'undefined') ? data.chat.total : 0;
                    this.atualizarBadges(data.total_geral || 0, totalChatAtual);

                    // 6. Emite evento global para que qualquer componente do portal sincronize
                    window.dispatchEvent(new CustomEvent('notificacoes-atualizadas', { detail: data }));

                } catch (e) {
                    // Silencioso em caso de falha transitória
                }
            };

            this.checarAgora = checar;

            // Primeira checagem rápida após 600ms, depois a cada 5s
            setTimeout(checar, 600);
            setInterval(checar, 5000);
        },

        /**
         * Força atualização imediata sob demanda
         */
        atualizarAgora() {
            if (typeof this.checarAgora === 'function') {
                return this.checarAgora();
            }
        },

        obterDados() {
            return this.ultimosDados || null;
        },

        /**
         * Hook público para o Chat Interno
         */
        notificarMensagemChat(remetente, texto, conversaId = null) {
            const appBase = (window.location.pathname.startsWith('/API/') || window.location.pathname === '/API') ? '/API' : '';
            this.notificar({
                titulo: `💬 ${remetente}`,
                corpo: texto || 'Enviou uma nova mensagem no chat.',
                url: `${appBase}/portal/modules/chat.php`,
                tag: `chat_live_${Date.now()}`,
                som: true
            });
        },

        /**
         * Teste em 1 clique para validar som, pop-up no Windows e badges
         */
        testarNotificacao() {
            if ('Notification' in window && Notification.permission !== 'granted') {
                this.solicitarPermissao().then(concedido => {
                    if (concedido) {
                        this.dispararTeste();
                    } else if (window.Swal) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Permissão Necessária',
                            html: '<div class="text-left text-sm text-slate-600 space-y-2">' +
                                  '<p>Para receber alertas no Windows com o navegador minimizado, clique no <b>ícone de cadeado/ajustes</b> ao lado da barra de endereços (URL) e marque <b>Notificações: Permitir</b>.</p>' +
                                  '</div>',
                            confirmButtonColor: '#274036'
                        });
                    }
                });
            } else {
                this.dispararTeste();
            }
        },

        dispararTeste() {
            this.notificar({
                titulo: '🔔 Teste de Alerta do Sistema',
                corpo: 'Seu computador/celular está sincronizado! Avisos de compromissos, chamados e chat aparecerão aqui.',
                som: true
            });
            this.atualizarBadges((this.totalNaoLidas || 0) + 1);
        },

        /**
         * Exibe banner sugerindo permissão se ainda não foi concedida nem negada
         */
        verificarBannerPermissao() {
            if (!('Notification' in window)) return;
            if (Notification.permission !== 'default') return;

            const dispensado = localStorage.getItem('banner_notif_dispensado');
            if (dispensado && (Date.now() - Number(dispensado) < 24 * 3600 * 1000)) {
                return;
            }

            const banner = document.createElement('div');
            banner.id = 'bannerPermissaoNotificacoes';
            banner.className = 'fixed top-3 left-1/2 -translate-x-1/2 z-[99999] bg-gradient-to-r from-slate-900 via-slate-800 to-emerald-950 text-white px-4 py-2.5 rounded-2xl shadow-2xl border border-emerald-500/40 flex items-center gap-3 text-xs font-semibold max-w-lg w-[94%] sm:w-auto transition-all';
            banner.innerHTML = `
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-bell text-amber-400 text-sm animate-pulse"></i>
                    <span>Ativar alertas de chamados, agenda e chat no seu Windows e celular?</span>
                </div>
                <div class="flex items-center gap-1.5 ml-auto">
                    <button type="button" id="btnAtivarNotificacoesTop" class="px-3 py-1 bg-emerald-500 hover:bg-emerald-600 text-white rounded-xl text-xs font-black shadow-xs transition-all">
                        Ativar
                    </button>
                    <button type="button" id="btnDispensarNotificacoesTop" class="p-1 text-slate-400 hover:text-white">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            `;
            document.body.appendChild(banner);

            document.getElementById('btnAtivarNotificacoesTop')?.addEventListener('click', () => {
                this.solicitarPermissao();
            });
            document.getElementById('btnDispensarNotificacoesTop')?.addEventListener('click', () => {
                localStorage.setItem('banner_notif_dispensado', Date.now());
                banner.remove();
            });
        },

        esconderBannerPermissao() {
            const b = document.getElementById('bannerPermissaoNotificacoes');
            if (b) b.remove();
        },

        escapeHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    };

    // Exporta globalmente
    window.PortalNotificacoes = PortalNotificacoes;

    // Inicializa no carregamento do DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => PortalNotificacoes.init());
    } else {
        PortalNotificacoes.init();
    }

})(window);
