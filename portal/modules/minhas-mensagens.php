<?php
// ==========================================================================
// BATE-PAPO & CHAT INTERNO NUTRICIONAL - VERSÃO PREMIUM WHATSAPP WEB STYLE
// ==========================================================================
$pageTitle = 'Mensagens & Bate-Papo Interno | Nutricional';
require_once __DIR__ . '/../estrutura/header.php';
?>
<style>
    html, body {
        height: 100%;
        margin: 0;
        overflow: hidden;
    }
    .chat-widget, .portal-footer-simple {
        display: none !important;
    }
    @media (max-width: 767px) {
        .chat-mobile-oculto {
            display: none !important;
        }
    }
    .chat-scroll::-webkit-scrollbar {
        width: 6px;
    }
    .chat-scroll::-webkit-scrollbar-track {
        background: transparent;
    }
    .chat-scroll::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.45);
        border-radius: 9999px;
    }
    .chat-scroll::-webkit-scrollbar-thumb:hover {
        background: rgba(100, 116, 139, 0.7);
    }
</style>

<div class="h-screen w-full bg-slate-100 flex flex-col overflow-hidden font-['Plus_Jakarta_Sans',sans-serif]" x-data="chatProfissional()" x-init="init()">

    <!-- Topo da Aplicação -->
    <header class="bg-gradient-to-r from-slate-900 via-emerald-950 to-slate-900 text-white px-4 lg:px-6 py-3 shadow-md flex items-center justify-between flex-shrink-0 z-20">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="p-2 rounded-xl bg-white/10 hover:bg-white/20 text-white transition flex items-center gap-2 text-xs font-bold no-underline">
                <i class="fa-solid fa-arrow-left"></i>
                <span class="hidden sm:inline">Dashboard</span>
            </a>
            <div class="h-6 w-px bg-white/10 mx-1"></div>
            <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 flex items-center justify-center font-bold text-lg">
                    <i class="fa-brands fa-whatsapp"></i>
                </div>
                <div>
                    <h1 class="text-sm lg:text-base font-extrabold text-white leading-tight">Chat Interno &amp; Bate-Papo</h1>
                    <p class="text-[10px] text-slate-400">Comunicação corporativa direta entre colaboradores</p>
                </div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <!-- Indicador de Usuários Online (Sólido e Elegante, Sem Piscar) -->
            <div class="flex items-center gap-2 px-3 py-1.5 bg-white/10 rounded-xl border border-white/10 text-xs">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                <span class="text-slate-200 font-semibold" x-text="totalOnline + ' Online agora'"></span>
            </div>
            <button @click="carregarContatos(true)" class="p-2 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition" title="Atualizar conversas">
                <i class="fa-solid fa-rotate" :class="{ 'fa-spin': carregando }"></i>
            </button>
        </div>
    </header>

    <!-- Container Principal Split-Screen Preenchendo 100% da Tela -->
    <main class="flex-1 flex w-full min-h-0 p-0 sm:p-2 lg:p-3 overflow-hidden">
        <div class="bg-white sm:rounded-2xl shadow-xl border border-slate-200/80 flex-1 flex w-full h-full min-h-0 overflow-hidden">

            <!-- =============================================================== -->
            <!-- COLUNA ESQUERDA: LISTA DE COLABORADORES COM ROLAGEM INDEPENDENTE -->
            <!-- =============================================================== -->
            <aside class="w-full md:w-80 lg:w-96 border-r border-slate-200 flex flex-col h-full min-h-0 bg-white flex-shrink-0"
                   :class="{ 'chat-mobile-oculto': contatoAtivo !== null }">
                
                <!-- Barra de Busca de Usuários (Fixa no topo da lista) -->
                <div class="p-3.5 border-b border-slate-100 bg-slate-50/80 flex-shrink-0">
                    <div class="relative">
                        <i class="fa-solid fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" x-model="termoBusca" @input="filtrarContatos()" placeholder="Buscar colaborador pelo nome..."
                               class="w-full pl-9 pr-8 py-2.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition shadow-xs">
                        <button x-show="termoBusca" @click="termoBusca = ''; filtrarContatos()" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Filtros rápidos: Todos / Online -->
                    <div class="flex items-center gap-1.5 mt-2.5">
                        <button type="button" @click="filtroApenasOnline = false; filtrarContatos()"
                                class="px-3 py-1 rounded-lg text-[11px] font-bold transition"
                                :class="!filtroApenasOnline ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                            Todos (<span x-text="contatos.length"></span>)
                        </button>
                        <button type="button" @click="filtroApenasOnline = true; filtrarContatos()"
                                class="px-3 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1.5"
                                :class="filtroApenasOnline ? 'bg-emerald-600 text-white shadow-xs' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                            Online (<span x-text="totalOnline"></span>)
                        </button>
                    </div>
                </div>

                <!-- Lista de Contatos com Rolagem Própria -->
                <div class="flex-1 overflow-y-auto min-h-0 divide-y divide-slate-100 chat-scroll">
                    <template x-for="c in contatosFiltrados" :key="c.idusuario">
                        <div @click="abrirConversa(c)" 
                             class="p-3.5 flex items-center gap-3 cursor-pointer transition-all hover:bg-slate-50 select-none relative"
                             :class="{ 'bg-emerald-50/80 border-l-4 border-emerald-600 shadow-xs': contatoAtivo && contatoAtivo.idusuario === c.idusuario }">
                            
                            <!-- Avatar com Indicador Sólido (Sem Piscar) -->
                            <div class="relative flex-shrink-0">
                                <template x-if="c.foto_url">
                                    <img :src="c.foto_url" class="w-12 h-12 rounded-full object-cover shadow-xs border border-slate-200"
                                         @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'">
                                </template>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-emerald-600 via-teal-700 to-slate-800 text-white flex items-center justify-center font-bold text-xs shadow-xs"
                                     :style="c.foto_url ? 'display:none;' : 'display:flex;'"
                                     x-text="getIniciais(c.username)"></div>
                                
                                <!-- Indicador de Presença Sólido -->
                                <template x-if="c.status_online === 'online'">
                                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-emerald-500 border-2 border-white rounded-full"></span>
                                </template>
                                <template x-if="c.status_online === 'ausente'">
                                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-amber-400 border-2 border-white rounded-full"></span>
                                </template>
                                <template x-if="c.status_online === 'offline'">
                                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 bg-slate-300 border-2 border-white rounded-full"></span>
                                </template>
                            </div>

                            <!-- Informações do Usuário -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="font-bold text-xs text-slate-800 truncate" 
                                          :class="{ 'text-emerald-950 font-extrabold': contatoAtivo && contatoAtivo.idusuario === c.idusuario }"
                                          x-text="c.username"></span>
                                    
                                    <!-- Horário da última mensagem se houver -->
                                    <span x-show="c.ultima_data" class="text-[10px] text-slate-400 font-mono" x-text="formatarHora(c.ultima_data)"></span>
                                </div>

                                <div class="flex items-center justify-between gap-1">
                                    <!-- Status / Mensagem / Digitando -->
                                    <p class="text-[11px] truncate">
                                        <!-- Se estiver digitando agora -->
                                        <template x-if="c.digitando">
                                            <span class="text-emerald-600 font-bold flex items-center gap-1">
                                                <i class="fa-solid fa-pen text-[9px]"></i> digitando...
                                            </span>
                                        </template>

                                        <!-- Se tiver mensagens não lidas -->
                                        <template x-if="!c.digitando && c.nao_lidas > 0">
                                            <span class="font-bold text-emerald-800" x-text="c.ultima_mensagem || (c.nao_lidas + ' nova(s) mensagem(ns)')"></span>
                                        </template>

                                        <!-- Se tiver última mensagem -->
                                        <template x-if="!c.digitando && !c.nao_lidas && c.ultima_mensagem">
                                            <span class="text-slate-500" x-text="c.ultima_mensagem"></span>
                                        </template>

                                        <!-- Se sem mensagens, mostra status -->
                                        <template x-if="!c.digitando && !c.nao_lidas && !c.ultima_mensagem">
                                            <span :class="c.status_online === 'online' ? 'text-emerald-600 font-semibold' : 'text-slate-400'"
                                                  x-text="c.status_texto"></span>
                                        </template>
                                    </p>

                                    <!-- Badge Não Lidas (Elegante e Fixo, Sem Piscar) -->
                                    <span x-show="c.nao_lidas > 0" 
                                          class="px-2 py-0.5 bg-emerald-600 text-white font-extrabold text-[10px] rounded-full shadow-xs flex-shrink-0"
                                          x-text="c.nao_lidas"></span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="contatosFiltrados.length === 0" class="p-8 text-center">
                        <i class="fa-regular fa-comment-dots text-3xl text-slate-300 mb-2 block"></i>
                        <p class="text-xs font-bold text-slate-600">Nenhum colaborador encontrado</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">Tente outro nome ou remova os filtros.</p>
                    </div>
                </div>
            </aside>

            <!-- =============================================================== -->
            <!-- COLUNA DIREITA: CONVERSA ATIVA (TROCA COM APENAS 1 CLIQUE) -->
            <!-- =============================================================== -->
            <section class="flex-1 flex flex-col h-full min-h-0 min-w-0 bg-[#efeae2] relative overflow-hidden"
                     :class="{ 'chat-mobile-oculto': contatoAtivo === null }">

                <!-- Estado Inicial: Caso nenhum contato esteja selecionado -->
                <div x-show="contatoAtivo === null" class="flex-1 flex flex-col items-center justify-center p-8 text-center bg-slate-50">
                    <div class="w-20 h-20 rounded-3xl bg-emerald-100/60 text-emerald-700 flex items-center justify-center text-4xl mb-4 shadow-sm border border-emerald-200">
                        <i class="fa-solid fa-comments"></i>
                    </div>
                    <h3 class="text-lg font-extrabold text-slate-800 mb-1">Bate-Papo Corporativo Nutricional</h3>
                    <p class="text-xs text-slate-500 max-w-sm mb-6 leading-relaxed">
                        Selecione qualquer colaborador na lista ao lado para conversar em tempo real. A lista permanece sempre visível para trocar de conversa a qualquer instante.
                    </p>
                </div>

                <!-- Painel da Conversa Selecionada -->
                <template x-if="contatoAtivo !== null">
                    <div class="flex-1 flex flex-col h-full min-h-0 overflow-hidden">
                        
                        <!-- Cabeçalho da Conversa Estilo WhatsApp Web -->
                        <div class="px-4 py-3 bg-[#075e54] text-white flex items-center justify-between shadow-md z-10 flex-shrink-0">
                            <div class="flex items-center gap-3 min-w-0">
                                <!-- Botão Voltar (Aparece SOMENTE no Mobile) -->
                                <button @click="contatoAtivo = null" class="md:hidden p-1.5 text-white/80 hover:text-white rounded-lg">
                                    <i class="fa-solid fa-arrow-left text-sm"></i>
                                </button>

                                <!-- Avatar do Contato Ativo -->
                                <div class="relative flex-shrink-0">
                                    <template x-if="contatoAtivo.foto_url">
                                        <img :src="contatoAtivo.foto_url" class="w-10 h-10 rounded-full object-cover border border-white/30"
                                             @error="$event.target.style.display='none'; $event.target.nextElementSibling.style.display='flex'">
                                    </template>
                                    <div class="w-10 h-10 rounded-full bg-white/20 text-white flex items-center justify-center font-bold text-xs"
                                         :style="contatoAtivo.foto_url ? 'display:none;' : 'display:flex;'"
                                         x-text="getIniciais(contatoAtivo.username)"></div>
                                    
                                    <!-- Bolinha de status sólida -->
                                    <template x-if="contatoAtivo.status_online === 'online'">
                                        <span class="absolute bottom-0 right-0 w-3 h-3 bg-emerald-400 border-2 border-[#075e54] rounded-full"></span>
                                    </template>
                                    <template x-if="contatoAtivo.status_online === 'ausente'">
                                        <span class="absolute bottom-0 right-0 w-3 h-3 bg-amber-400 border-2 border-[#075e54] rounded-full"></span>
                                    </template>
                                </div>

                                <!-- Nome e Status / Digitando em Tempo Real -->
                                <div class="min-w-0">
                                    <h4 class="font-extrabold text-sm text-white truncate leading-tight" x-text="contatoAtivo.username"></h4>
                                    
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <!-- Se estiver digitando -->
                                        <template x-if="contatoAtivo.digitando">
                                            <span class="flex items-center gap-1 text-[11px] text-emerald-300 font-bold">
                                                <i class="fa-solid fa-pencil text-[9px]"></i> digitando...
                                            </span>
                                        </template>

                                        <!-- Se não estiver digitando -->
                                        <template x-if="!contatoAtivo.digitando">
                                            <div>
                                                <template x-if="contatoAtivo.status_online === 'online'">
                                                    <span class="flex items-center gap-1.5 text-[11px] text-emerald-200 font-semibold">
                                                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                                                        Online agora
                                                    </span>
                                                </template>
                                                <template x-if="contatoAtivo.status_online === 'ausente'">
                                                    <span class="flex items-center gap-1.5 text-[11px] text-amber-200">
                                                        <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                                        Visto há instantes
                                                    </span>
                                                </template>
                                                <template x-if="contatoAtivo.status_online === 'offline'">
                                                    <span class="text-[11px] text-slate-300" x-text="contatoAtivo.status_texto || 'Offline'"></span>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </div>

                            <!-- Ações Rápidas do Cabeçalho -->
                            <div class="flex items-center gap-2">
                                <button type="button" @click="carregarMensagens(contatoAtivo.idusuario, true)" 
                                        class="p-2 text-white/80 hover:text-white rounded-lg hover:bg-white/10 transition" title="Recarregar histórico">
                                    <i class="fa-solid fa-rotate-right text-xs" :class="{ 'fa-spin': carregandoMensagens }"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Histórico de Mensagens com Textura WhatsApp e Balões Estáveis (Sem Flicker) -->
                        <div class="flex-1 overflow-y-auto min-h-0 p-4 space-y-3 chat-scroll" id="mensagensContainerTelaCheia"
                             style="background-image: url('data:image/svg+xml,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'><rect fill=\'%23efeae2\' width=\'100\' height=\'100\'/><circle cx=\'50\' cy=\'50\' r=\'40\' fill=\'none\' stroke=\'%23d4cfc4\' stroke-width=\'0.5\' opacity=\'0.25\'/></svg>');">
                            
                            <template x-for="m in mensagens" :key="m.id || m.idmensagem">
                                <div class="flex flex-col select-text" :class="ehMinha(m) ? 'items-end' : 'items-start'">
                                    <div class="max-w-[85%] sm:max-w-[70%] rounded-2xl px-3.5 py-2 shadow-xs text-xs relative leading-relaxed transition-all"
                                         :class="ehMinha(m) ? 'bg-[#d9fdd3] text-slate-900 rounded-tr-xs' : 'bg-white text-slate-900 rounded-tl-xs'">
                                        <p class="whitespace-pre-wrap break-words" x-text="m.mensagem"></p>
                                        
                                        <!-- Rodapé da Mensagem: Horário + Indicador de 1 Check (Entregue) ou 2 Checks Azuis (Visualizado) -->
                                        <div class="flex items-center justify-end gap-1.5 mt-1 text-[10px] text-slate-500 font-mono">
                                            <span x-text="formatarHora(m.datahora)"></span>
                                            
                                            <!-- Indicadores Estilo WhatsApp para Minhas Mensagens -->
                                            <template x-if="ehMinha(m)">
                                                <span class="inline-flex items-center">
                                                    <!-- Dois checks azuis quando o destinatário visualizou -->
                                                    <template x-if="m.lida">
                                                        <i class="fa-solid fa-check-double text-[#53bdeb] text-xs font-bold" title="Visualizado pelo colaborador"></i>
                                                    </template>
                                                    <!-- Um check cinza quando entregue mas ainda não visualizado -->
                                                    <template x-if="!m.lida">
                                                        <i class="fa-solid fa-check text-slate-400 text-xs" title="Entregue no servidor"></i>
                                                    </template>
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div x-show="mensagens.length === 0" class="py-12 text-center">
                                <div class="inline-block p-4 bg-white/85 backdrop-blur rounded-2xl shadow-xs text-slate-600 text-xs border border-slate-200/50">
                                    Nenhuma mensagem anterior com <strong x-text="contatoAtivo.username"></strong>. Envie uma saudação! 👋
                                </div>
                            </div>
                        </div>

                        <!-- Indicador Flutuante "Fulano está digitando..." -->
                        <div x-show="contatoAtivo.digitando" 
                             class="px-4 py-1.5 bg-[#efeae2] flex items-center gap-2 text-xs text-emerald-800 transition-all flex-shrink-0">
                            <div class="flex items-center gap-1.5 bg-white/90 backdrop-blur px-3 py-1 rounded-full shadow-xs border border-emerald-200">
                                <span class="flex gap-1 items-center">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-bounce"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-bounce" style="animation-delay: 0.15s"></span>
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-bounce" style="animation-delay: 0.3s"></span>
                                </span>
                                <span class="font-bold text-[11px]" x-text="contatoAtivo.username + ' está digitando...'"></span>
                            </div>
                        </div>

                        <!-- Barra de Envio de Mensagens -->
                        <div class="p-3 bg-[#f0f2f5] border-t border-slate-200/80 flex items-center gap-2 flex-shrink-0">
                            <input type="text" x-model="textoMensagem" 
                                   id="inputMensagemChat"
                                   @input="aoDigitar()"
                                   @keydown.enter.prevent="enviar()" 
                                   placeholder="Digite sua mensagem e pressione Enter..."
                                   class="flex-1 px-4 py-2.5 bg-white rounded-full text-xs text-slate-800 placeholder-slate-400 border border-slate-200 shadow-xs focus:ring-2 focus:ring-[#075e54] outline-none">
                            <button type="button" @click="enviar()" :disabled="!textoMensagem.trim()"
                                    class="w-10 h-10 rounded-full bg-[#075e54] hover:bg-[#128C7E] disabled:opacity-40 text-white flex items-center justify-center transition shadow-md flex-shrink-0">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                            </button>
                        </div>

                    </div>
                </template>

            </section>
        </div>
    </main>

</div>

<script>
function chatProfissional() {
    return {
        contatos: [],
        contatosFiltrados: [],
        contatoAtivo: null,
        mensagens: [],
        termoBusca: '',
        textoMensagem: '',
        filtroApenasOnline: false,
        carregando: false,
        carregandoMensagens: false,
        intervalo: null,
        timerDigitando: null,
        ultimoAvisoDigitando: 0,
        meuId: 0,

        get totalOnline() {
            return this.contatos.filter(c => c.status_online === 'online').length;
        },

        async init() {
            const userData = JSON.parse(localStorage.getItem('userData') || '{}');
            this.meuId = parseInt(userData.idusuario) || 0;

            await this.carregarContatos();

            // Verifica se veio contato específico pela URL (?contato=ID)
            const params = new URLSearchParams(window.location.search);
            const contatoUrlId = parseInt(params.get('contato')) || 0;

            if (contatoUrlId > 0 && this.contatos.length > 0) {
                const alvo = this.contatos.find(c => c.idusuario == contatoUrlId);
                if (alvo) {
                    this.abrirConversa(alvo);
                }
            }

            // Auto-selecionar o primeiro contato que tiver mensagens não lidas ou o primeiro online
            if (!this.contatoAtivo && this.contatos.length > 0) {
                const comNaoLidas = this.contatos.find(c => c.nao_lidas > 0);
                const primeiro = comNaoLidas || this.contatos[0];
                if (primeiro) {
                    this.abrirConversa(primeiro);
                }
            }

            // Polling inteligente e leve a cada 2.5 segundos (Sem flicker)
            this.intervalo = setInterval(async () => {
                if (this.contatoAtivo) {
                    await this.carregarMensagens(this.contatoAtivo.idusuario, false);
                }
                await this.atualizarStatusSilencioso();
            }, 2500);
        },

        async carregarContatos(forcarVisual = false) {
            if (forcarVisual) this.carregando = true;
            try {
                let dados = null;
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    dados = await window.PortalNotificacoes.requisitar('/chat/contatos');
                } else {
                    const token = localStorage.getItem('authToken');
                    const resp = await fetch(`${API_URL}/chat/contatos`, {
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                    if (resp.ok) dados = await resp.json();
                }

                if (Array.isArray(dados)) {
                    this.contatos = dados;
                    this.filtrarContatos();

                    if (this.contatoAtivo) {
                        const atualizado = this.contatos.find(c => c.idusuario === this.contatoAtivo.idusuario);
                        if (atualizado) {
                            this.contatoAtivo.status_online = atualizado.status_online;
                            this.contatoAtivo.status_texto = atualizado.status_texto;
                            this.contatoAtivo.digitando = !!atualizado.digitando;
                        }
                    }
                }
            } catch(e) {
                console.warn('[Chat] Erro ao listar contatos:', e);
            } finally {
                if (forcarVisual) this.carregando = false;
            }
        },

        filtrarContatos() {
            const b = (this.termoBusca || '').toLowerCase().trim();
            this.contatosFiltrados = this.contatos.filter(c => {
                const bateNome = !b || (c.username || '').toLowerCase().includes(b);
                const bateOnline = !this.filtroApenasOnline || c.status_online === 'online';
                return bateNome && bateOnline;
            });
        },

        async abrirConversa(contato) {
            this.contatoAtivo = contato;
            this.mensagens = [];
            await this.carregarMensagens(contato.idusuario, true);
            await this.marcarComoLida(contato.idusuario);

            this.$nextTick(() => {
                const inp = document.getElementById('inputMensagemChat');
                if (inp) inp.focus();
            });
        },

        async carregarMensagens(outroId, forcarScroll = false) {
            try {
                if (forcarScroll) this.carregandoMensagens = true;
                let dados = null;
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    dados = await window.PortalNotificacoes.requisitar(`/chat/mensagens/${outroId}`);
                } else {
                    const token = localStorage.getItem('authToken');
                    const resp = await fetch(`${API_URL}/chat/mensagens/${outroId}`, {
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                    if (resp.ok) dados = await resp.json();
                }

                if (Array.isArray(dados)) {
                    const qtdAtual = this.mensagens.length;
                    const qtdNova = dados.length;
                    const ultimoAtual = qtdAtual > 0 ? this.mensagens[qtdAtual - 1].id : null;
                    const ultimoNovo = qtdNova > 0 ? dados[qtdNova - 1].id : null;

                    // Verifica se houve nova mensagem ou mudança de status lida
                    let precisaAtualizar = false;
                    if (qtdAtual !== qtdNova || ultimoAtual !== ultimoNovo) {
                        precisaAtualizar = true;
                    } else {
                        // Checa se mudou algum status de leitura
                        for (let i = 0; i < qtdAtual; i++) {
                            if (this.mensagens[i].lida !== dados[i].lida) {
                                precisaAtualizar = true;
                                break;
                            }
                        }
                    }

                    if (precisaAtualizar || forcarScroll) {
                        this.mensagens = dados;
                        if (forcarScroll || qtdNova > qtdAtual) {
                            this.$nextTick(() => {
                                const c = document.getElementById('mensagensContainerTelaCheia');
                                if (c) c.scrollTop = c.scrollHeight;
                            });
                        }
                    }
                }
            } catch(e) {
            } finally {
                if (forcarScroll) this.carregandoMensagens = false;
            }
        },

        aoDigitar() {
            if (!this.contatoAtivo) return;

            const agora = Date.now();
            // Notifica o servidor no máximo 1 vez a cada 2 segundos
            if (agora - this.ultimoAvisoDigitando > 2000) {
                this.ultimoAvisoDigitando = agora;
                this.notificarDigitandoServidor(this.contatoAtivo.idusuario);
            }

            // Cancela timer anterior e agenda parada após 3 segundos sem digitar
            if (this.timerDigitando) clearTimeout(this.timerDigitando);
            this.timerDigitando = setTimeout(() => {
                this.pararDigitandoServidor(this.contatoAtivo?.idusuario);
            }, 3000);
        },

        async notificarDigitandoServidor(destId) {
            if (!destId) return;
            try {
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    await window.PortalNotificacoes.requisitar('/chat/digitando', {
                        method: 'POST',
                        body: JSON.stringify({ para: destId })
                    });
                } else {
                    const token = localStorage.getItem('authToken');
                    await fetch(`${API_URL}/chat/digitando`, {
                        method: 'POST',
                        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ para: destId })
                    });
                }
            } catch(_) {}
        },

        async pararDigitandoServidor(destId) {
            if (!destId) return;
            try {
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    await window.PortalNotificacoes.requisitar('/chat/parar-digitando', {
                        method: 'POST',
                        body: JSON.stringify({ para: destId })
                    });
                } else {
                    const token = localStorage.getItem('authToken');
                    await fetch(`${API_URL}/chat/parar-digitando`, {
                        method: 'POST',
                        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                        body: JSON.stringify({ para: destId })
                    });
                }
            } catch(_) {}
        },

        async enviar() {
            const txt = (this.textoMensagem || '').trim();
            if (!txt || !this.contatoAtivo) return;

            const destId = this.contatoAtivo.idusuario;
            this.textoMensagem = '';

            // Avisa servidor que parou de digitar
            if (this.timerDigitando) clearTimeout(this.timerDigitando);
            this.pararDigitandoServidor(destId);

            try {
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    await window.PortalNotificacoes.requisitar('/chat/enviar', {
                        method: 'POST',
                        body: JSON.stringify({ destinatario: destId, mensagem: txt })
                    });
                } else {
                    const token = localStorage.getItem('authToken');
                    await fetch(`${API_URL}/chat/enviar`, {
                        method: 'POST',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ destinatario: destId, mensagem: txt })
                    });
                }

                await this.carregarMensagens(destId, true);
                await this.atualizarStatusSilencioso();
            } catch(e) {
                console.error('[Chat] Erro ao enviar mensagem:', e);
            }
        },

        async marcarComoLida(remetenteId) {
            try {
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    await window.PortalNotificacoes.requisitar(`/chat/marcar-lida/${remetenteId}`, { method: 'POST' });
                } else {
                    const token = localStorage.getItem('authToken');
                    await fetch(`${API_URL}/chat/marcar-lida/${remetenteId}`, {
                        method: 'POST',
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                }

                // Zera no contato localmente
                const c = this.contatos.find(item => item.idusuario === remetenteId);
                if (c) c.nao_lidas = 0;

                if (typeof atualizarChatBadge === 'function') {
                    atualizarChatBadge();
                }
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.atualizarAgora === 'function') {
                    window.PortalNotificacoes.atualizarAgora();
                }
            } catch(e) {}
        },

        async atualizarStatusSilencioso() {
            try {
                let atualizados = null;
                if (window.PortalNotificacoes && typeof window.PortalNotificacoes.requisitar === 'function') {
                    atualizados = await window.PortalNotificacoes.requisitar('/chat/contatos');
                }
                if (Array.isArray(atualizados)) {
                    // Atualiza propriedades in-place sem recriar o array (sem piscar a lista)
                    atualizados.forEach(at => {
                        const local = this.contatos.find(item => item.idusuario === at.idusuario);
                        if (local) {
                            local.status_online = at.status_online;
                            local.status_texto = at.status_texto;
                            local.digitando = !!at.digitando;
                            local.nao_lidas = at.nao_lidas;
                            local.ultima_mensagem = at.ultima_mensagem;
                            local.ultima_data = at.ultima_data;
                        }
                    });

                    if (this.contatoAtivo) {
                        const atual = atualizados.find(c => c.idusuario === this.contatoAtivo.idusuario);
                        if (atual) {
                            this.contatoAtivo.status_online = atual.status_online;
                            this.contatoAtivo.status_texto = atual.status_texto;
                            this.contatoAtivo.digitando = !!atual.digitando;
                        }
                    }
                }
            } catch(_) {}
        },

        ehMinha(m) {
            return parseInt(m.idusuario_remetente) === this.meuId;
        },

        getIniciais(str) {
            if (!str) return '??';
            return str.substring(0, 2).toUpperCase();
        },

        formatarHora(dataHoraStr) {
            if (!dataHoraStr) return '';
            try {
                const d = new Date(dataHoraStr.replace(/-/g, '/'));
                return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            } catch(_) {
                return '';
            }
        }
    }
}
</script>

<?php
require_once __DIR__ . '/../estrutura/footer.php';
?>