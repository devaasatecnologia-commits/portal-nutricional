<?php
$pageTitle = 'Central de Chamados & IA | Nutricional';
$moduleJs = 'chamados.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  .chm-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .chm-hero { background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%); }
  .kanban-col { min-height: 460px; max-height: calc(100vh - 320px); overflow-y: auto; }
  .kanban-card { transition: transform 0.18s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.18s ease, border-color 0.18s ease; }
  .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px -8px rgba(15, 23, 42, 0.12); }
  .pulse-ia { animation: pulseIa 2s infinite; }
  @keyframes pulseIa { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.15); } }
  .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
  .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="chm-wrap max-w-[1440px] mx-auto px-3 sm:px-6 pt-3 pb-14" x-data="chamadosApp()" x-init="init()">

    <!-- HERO HEADER -->
    <div class="chm-hero rounded-3xl p-5 sm:p-6 text-white shadow-xl mb-4 border border-indigo-900/50">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white no-underline transition shadow-sm border border-white/10 shrink-0" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt text-amber-300"></i> Service Desk Pro
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-ia"></span> IA Triagem Ativa
                        </span>
                        <span x-show="usuario && usuario.pode_ver_todos" class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-purple-500/30 text-purple-200 border border-purple-400/30 flex items-center gap-1.5" style="display: none;">
                            <i class="fa-solid fa-crown text-amber-300"></i> Gestão TI & Kanban
                        </span>
                        <span x-show="salvandoOrdem" class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-amber-400/20 text-amber-200 border border-amber-400/30 flex items-center gap-1.5" style="display: none;">
                            <i class="fa-solid fa-spinner fa-spin"></i> Salvando ordem...
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1">
                        Central de Chamados, Prioridades & Soluções
                    </h1>
                    <p class="text-xs sm:text-sm text-indigo-200/90 mt-0.5">
                        Arraste cards no Kanban, reordene prioridades, edite textos, anexe prints (<kbd class="bg-white/10 px-1.5 py-0.5 rounded text-[10px] font-mono">Ctrl+V</kbd>) e acompanhe notificações em tempo real.
                    </p>
                </div>
            </div>

            <!-- BOTÕES DE AÇÃO DO TOPO -->
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" @click="painelAvisosAberto = !painelAvisosAberto"
                        class="relative px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 transition border border-white/15">
                    <i class="fa-solid fa-bell text-amber-300" :class="notificacoesNaoLidas > 0 ? 'animate-bounce' : ''"></i>
                    <span>Notificações</span>
                    <span class="px-1.5 py-0.5 rounded-full text-[10px] font-black"
                          :class="notificacoesNaoLidas > 0 ? 'bg-rose-500 text-white' : 'bg-white/20 text-indigo-100'"
                          x-text="notificacoesNaoLidas">0</span>
                </button>

                <button type="button" @click="abrirModalNovo(false)"
                        class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 text-xs font-black flex items-center gap-2 transition shadow-lg shadow-amber-500/25 active:scale-95">
                    <i class="fa-solid fa-plus-circle text-base"></i>
                    <span>Abrir Novo Chamado</span>
                </button>

                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=chamados"
                   class="px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 transition no-underline border border-white/15">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span class="hidden sm:inline">Treinamento</span>
                </a>
            </div>
        </div>
    </div>

    <!-- PAINEL RETRÁTIL DE NOTIFICAÇÕES & ATIVIDADES RECENTES -->
    <div x-show="painelAvisosAberto" x-transition class="bg-white rounded-3xl p-4 sm:p-5 border border-indigo-200 shadow-lg mb-4" style="display: none;">
        <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3 gap-2 flex-wrap">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Notificações de Chamados Pendentes</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Ao abrir o chamado, a notificação sai automaticamente. Você também pode dispensar ou limpar todas.</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" x-show="avisosRecentes.length > 0" @click="limparNotificacoesChamados()"
                        class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-600 border border-rose-200 text-xs font-black flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-broom"></i> Limpar Todas
                </button>
                <button type="button" @click="painelAvisosAberto = false" class="text-slate-400 hover:text-slate-700 px-2 py-1 rounded-lg text-xs font-bold">
                    <i class="fa-solid fa-xmark"></i> Fechar
                </button>
            </div>
        </div>

        <div x-show="avisosRecentes.length === 0" class="py-6 text-center text-xs text-slate-400 font-medium">
            Nenhuma notificação pendente no momento.
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2.5" x-show="avisosRecentes.length > 0">
            <template x-for="av in avisosRecentes" :key="av.idnotificacao || `${av.idchamado}-${av.data_fmt}`">
                <div @click="abrirDetalhes(av.idchamado)"
                     class="p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50/70 border border-slate-200/80 hover:border-indigo-300 cursor-pointer transition flex items-start justify-between gap-2.5">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                            <span class="text-[10px] font-black text-indigo-700 bg-indigo-100 px-1.5 py-0.5 rounded" x-text="`#${av.idchamado}`">#0</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded" :class="badgeCriticidade(av.criticidade)" x-text="av.criticidade">media</span>
                            <span class="text-[10px] font-bold text-slate-500 truncate" x-text="av.usuario">USER</span>
                        </div>
                        <p class="text-xs font-bold text-slate-800 line-clamp-1" x-text="av.titulo">Título</p>
                        <p class="text-[11px] text-slate-500 line-clamp-1 mt-0.5" x-text="av.descricao">Descrição</p>
                    </div>
                    <div class="flex flex-col items-end justify-between gap-1.5 shrink-0">
                        <span class="text-[10px] font-semibold text-slate-400" x-text="av.data_fmt">12:00</span>
                        <button type="button"
                                @click.stop="limparNotificacoesChamados(av.idnotificacao, av.idchamado)"
                                title="Dispensar notificação"
                                class="w-6 h-6 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 flex items-center justify-center transition">
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- BARRA DE ABERTURA EXPRESSA (QUICK TICKET + CTRL+V) -->
    <div @paste="tratarColagemPrint($event, 'quick')"
         class="bg-gradient-to-r from-indigo-50/90 via-white to-amber-50/60 rounded-3xl p-4 sm:p-5 border border-indigo-200/70 shadow-sm mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center gap-3">
            <div class="flex items-center gap-3 shrink-0">
                <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-md shadow-indigo-600/20">
                    <i class="fa-solid fa-bolt"></i>
                </div>
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 block">Abertura Expressa</span>
                    <h2 class="text-xs sm:text-sm font-black text-slate-800">Registrar Solicitação Rápida</h2>
                </div>
            </div>

            <div class="flex-1 grid grid-cols-1 md:grid-cols-12 gap-2.5 items-center">
                <div :class="ehTiLiberado() ? 'md:col-span-4' : 'md:col-span-5'">
                    <input type="text" x-model="quickForm.relato" @keydown.enter.prevent="criarChamadoRapido()"
                           placeholder="Descreva o erro ou solicitação e pressione Enter (ou cole um print com Ctrl+V aqui)..."
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-200 focus:border-indigo-500 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none shadow-2xs">
                </div>

                <div class="md:col-span-2">
                    <select x-model="quickForm.tipo_sistema"
                            class="w-full px-2.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                        <template x-for="s in sistemas" :key="s.id">
                            <option :value="s.id" x-text="s.id"></option>
                        </template>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <select x-model="quickForm.criticidade"
                            class="w-full px-2.5 py-2.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                        <option value="critica">🔴 Crítica</option>
                        <option value="alta">🟠 Alta</option>
                        <option value="media">🔵 Média</option>
                        <option value="baixa">🟢 Baixa</option>
                    </select>
                </div>

                <div x-show="ehTiLiberado()" class="md:col-span-2" style="display: none;">
                    <select x-model="quickForm.solicitante"
                            class="w-full px-2.5 py-2.5 bg-white border border-indigo-200 rounded-xl text-xs font-bold text-indigo-800 focus:outline-none focus:border-indigo-500"
                            title="Vincular chamado a um solicitante específico (Admin/TI)">
                        <option value="">👤 Solicitante: Eu</option>
                        <template x-for="usr in usuariosSolicitantes" :key="`qk-${usr.username}`">
                            <option :value="usr.username" x-text="`👤 ${usr.username}`"></option>
                        </template>
                    </select>
                </div>

                <div :class="ehTiLiberado() ? 'md:col-span-2' : 'md:col-span-3'" class="flex items-center gap-2">
                    <input type="file" x-ref="quickAnexoInput" @change="selecionarArquivoAnexo($event, 'quick')" accept="image/*,.pdf,.txt,.log" class="hidden">

                    <button type="button" @click="$refs.quickAnexoInput.click()"
                            class="px-3 py-2.5 rounded-xl border text-xs font-bold flex items-center gap-1.5 transition shrink-0"
                            :class="quickForm.anexo_url ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'"
                            title="Anexar imagem ou colar print Ctrl+V">
                        <i class="fa-solid" :class="anexoUploadando ? 'fa-spinner fa-spin' : (quickForm.anexo_url ? 'fa-image' : 'fa-paperclip')"></i>
                        <span class="hidden xl:inline" x-text="quickForm.anexo_url ? 'Print OK' : 'Print'">Print</span>
                    </button>

                    <button type="button" @click="criarChamadoRapido()" :disabled="quickForm.salvando || !quickForm.relato.trim()"
                            class="flex-1 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-black flex items-center justify-center gap-1.5 transition shadow-sm">
                        <i class="fa-solid" :class="quickForm.salvando ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                        <span>Abrir Agora</span>
                    </button>

                    <button type="button" @click="abrirModalNovo(true)"
                            class="px-2.5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition shrink-0"
                            title="Abrir formulário completo com IA">
                        <i class="fa-solid fa-wand-magic-sparkles text-indigo-600"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Miniatura do print na abertura expressa -->
        <div x-show="quickForm.anexo_url" class="mt-3 pt-3 border-t border-indigo-100 flex items-center justify-between gap-3" style="display: none;">
            <div class="flex items-center gap-2.5">
                <template x-if="ehImagem(quickForm.anexo_url)">
                    <img :src="quickForm.anexo_url" @click="abrirLightbox(quickForm.anexo_url, 'Print na Abertura Expressa')"
                         class="w-12 h-12 object-cover rounded-xl border border-indigo-200 cursor-pointer hover:opacity-90">
                </template>
                <span class="text-xs font-bold text-indigo-900">Print / anexo pronto para envio na abertura expressa</span>
            </div>
            <button type="button" @click="removerAnexo('quick')" class="text-xs font-bold text-rose-600 hover:text-rose-800 px-2 py-1 rounded-lg hover:bg-rose-50">
                <i class="fa-solid fa-trash-can"></i> Remover
            </button>
        </div>
    </div>

    <!-- AVISOS DE PRAZO PRÓXIMO & CONCLUÍDOS PARA O USUÁRIO -->
    <div x-show="alertasPrazo && alertasPrazo.length > 0" x-cloak class="mb-4 bg-white rounded-2xl border border-indigo-200 p-3 shadow-sm">
        <div class="flex items-center justify-between gap-2 mb-2">
            <span class="text-[11px] font-black uppercase tracking-wider text-indigo-800 flex items-center gap-1.5">
                <i class="fa-solid fa-bell text-amber-500"></i> Avisos de Prazo &amp; Chamados Concluídos
            </span>
            <span class="text-[10px] font-bold text-slate-400">Clique no item para abrir</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <template x-for="ap in alertasPrazo" :key="'ap-'+ap.idchamado">
                <button type="button" @click="abrirDetalhes(ap.idchamado)"
                        class="text-left px-3 py-1.5 rounded-xl border text-[11px] font-bold flex items-center gap-2 transition hover:shadow-xs"
                        :class="ap.tipo_alerta === 'concluido' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : (ap.tipo_alerta === 'atrasado' ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-amber-50 border-amber-200 text-amber-800')">
                    <i class="fa-solid" :class="ap.tipo_alerta === 'concluido' ? 'fa-circle-check text-emerald-600' : (ap.tipo_alerta === 'atrasado' ? 'fa-clock-rotate-left text-rose-600' : 'fa-hourglass-half text-amber-600')"></i>
                    <span x-text="'#' + ap.idchamado"></span>
                    <span class="truncate max-w-[180px]" x-text="ap.titulo"></span>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase"
                          :class="ap.tipo_alerta === 'concluido' ? 'bg-emerald-200/70' : (ap.tipo_alerta === 'atrasado' ? 'bg-rose-200/70' : 'bg-amber-200/70')"
                          x-text="ap.tipo_alerta === 'concluido' ? 'Concluído!' : (ap.tipo_alerta === 'atrasado' ? 'Atrasado (' + ap.prazo_fmt + ')' : 'Próx. Solução (' + ap.prazo_fmt + ')')"></span>
                </button>
            </template>
        </div>
    </div>

    <!-- 6 KPI CARDS CLICÁVEIS COM TOOLTIP AO PASSAR O MOUSE -->
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
        <!-- Total -->
        <div @click="filtrarPorCard({})" @mouseenter="cardHover = 'total'" @mouseleave="cardHover = null"
             class="relative bg-white rounded-2xl p-4 border border-slate-100 hover:border-indigo-300 cursor-pointer shadow-sm flex items-center justify-between transition">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5"
                      x-text="usuario && !usuario.pode_ver_todos ? 'Minhas Demandas' : 'Total Registrado'">Total</span>
                <span class="text-2xl font-black text-slate-800" x-text="kpis.total || 0">0</span>
                <span class="text-[10px] text-slate-400 font-semibold block mt-0.5">Ver todos</span>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center text-base"><i class="fa-solid fa-ticket"></i></div>
            <div x-show="cardHover === 'total'" x-transition.opacity x-cloak class="absolute left-1 right-1 top-full mt-2 z-50 bg-slate-900 text-white text-[10px] rounded-xl p-3 shadow-2xl pointer-events-none">
                <strong class="text-indigo-300 block mb-0.5">Visão Geral</strong>
                Total de chamados registrados (<span x-text="kpis.concluidos_total || 0"></span> concluídos, taxa <span x-text="kpis.taxa_resolucao || 0"></span>%). Clique para limpar filtros.
            </div>
        </div>

        <!-- Abertos / Análise -->
        <div @click="filtrarPorCard({ filtro_sla: 'abertos', modo: 'kanban' })" @mouseenter="cardHover = 'abertos'" @mouseleave="cardHover = null"
             class="relative bg-white rounded-2xl p-4 border border-amber-100 hover:border-amber-300 cursor-pointer shadow-sm flex items-center justify-between transition">
            <div>
                <span class="text-[10px] font-bold text-amber-600 uppercase tracking-wider block mb-0.5">Abertos / Triagem</span>
                <span class="text-2xl font-black text-amber-600" x-text="kpis.abertos || 0">0</span>
                <span class="text-[10px] text-amber-700/70 font-semibold block mt-0.5">Aguardando / Análise</span>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-base"><i class="fa-solid fa-clock"></i></div>
            <div x-show="cardHover === 'abertos'" x-transition.opacity x-cloak class="absolute left-1 right-1 top-full mt-2 z-50 bg-slate-900 text-white text-[10px] rounded-xl p-3 shadow-2xl pointer-events-none">
                <strong class="text-amber-300 block mb-0.5">Fila de Entrada</strong>
                Chamados em Aberto (0) ou Em Análise (1). Clique para filtrar somente os em andamento.
            </div>
        </div>

        <!-- Em Correção / Dev -->
        <div @click="filtrarPorCard({ status: '2', modo: 'lista' })" @mouseenter="cardHover = 'dev'" @mouseleave="cardHover = null"
             class="relative bg-white rounded-2xl p-4 border border-indigo-100 hover:border-indigo-300 cursor-pointer shadow-sm flex items-center justify-between transition">
            <div>
                <span class="text-[10px] font-bold text-indigo-600 uppercase tracking-wider block mb-0.5">Em Correção / Dev</span>
                <span class="text-2xl font-black text-indigo-600" x-text="kpis.em_correcao || 0">0</span>
                <span class="text-[10px] text-indigo-700/70 font-semibold block mt-0.5">Hotfix em andamento</span>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-base"><i class="fa-solid fa-code"></i></div>
            <div x-show="cardHover === 'dev'" x-transition.opacity x-cloak class="absolute left-1 right-1 top-full mt-2 z-50 bg-slate-900 text-white text-[10px] rounded-xl p-3 shadow-2xl pointer-events-none">
                <strong class="text-indigo-300 block mb-0.5">Em Desenvolvimento</strong>
                Chamados em execução pela TI (status 2). Clique para listar apenas os em correção.
            </div>
        </div>

        <!-- Críticos -->
        <div @click="filtrarPorCard({ criticidade: 'critica', filtro_sla: 'abertos' })" @mouseenter="cardHover = 'critica'" @mouseleave="cardHover = null"
             class="relative bg-white rounded-2xl p-4 border border-rose-100 hover:border-rose-300 cursor-pointer shadow-sm flex items-center justify-between transition"
             :class="kpis.criticos > 0 ? 'bg-rose-50/50 border-rose-300' : ''">
            <div>
                <span class="text-[10px] font-bold text-rose-600 uppercase tracking-wider block mb-0.5">Críticos Abertos</span>
                <span class="text-2xl font-black text-rose-600" x-text="kpis.criticos || 0">0</span>
                <span class="text-[10px] text-rose-600/70 font-semibold block mt-0.5" x-text="kpis.criticos > 0 ? 'SLA 1 dia • Filtrar' : 'Sem bloqueios'"></span>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-base" :class="kpis.criticos > 0 ? 'animate-bounce text-rose-700' : ''"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div x-show="cardHover === 'critica'" x-transition.opacity x-cloak class="absolute left-1 right-1 top-full mt-2 z-50 bg-slate-900 text-white text-[10px] rounded-xl p-3 shadow-2xl pointer-events-none">
                <strong class="text-rose-300 block mb-0.5">SLA por Criticidade</strong>
                Crítica: 1 dia · Alta: 3 dias · Média: 5 dias · Baixa: 7 dias.
            </div>
        </div>

        <!-- Dentro do Prazo -->
        <div @click="filtrarPorCard({ filtro_sla: 'no_prazo', modo: 'lista' })" @mouseenter="cardHover = 'sla_ok'" @mouseleave="cardHover = null"
             class="relative bg-white rounded-2xl p-4 border border-emerald-100 hover:border-emerald-300 cursor-pointer shadow-sm flex items-center justify-between transition">
            <div>
                <span class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider block mb-0.5">Dentro do Prazo</span>
                <span class="text-2xl font-black text-emerald-700" x-text="(kpis.sla_no_prazo || 0) + (kpis.sla_vencendo || 0)">0</span>
                <span class="text-[10px] text-emerald-700/80 font-semibold block mt-0.5" x-text="(kpis.sla_percentual_abertos || 100) + '% no SLA • ' + (kpis.sla_vencendo || 0) + ' vencendo'"></span>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-base"><i class="fa-solid fa-calendar-check"></i></div>
            <div x-show="cardHover === 'sla_ok'" x-transition.opacity x-cloak class="absolute left-1 right-1 top-full mt-2 z-50 bg-slate-900 text-white text-[10px] rounded-xl p-3 shadow-2xl pointer-events-none">
                <strong class="text-emerald-300 block mb-0.5">Chamados no Tempo</strong>
                <span x-text="kpis.sla_no_prazo || 0"></span> folgados (+2d) e <span x-text="kpis.sla_vencendo || 0"></span> próximos da solução (hoje/amanhã). Clique para filtrar.
            </div>
        </div>

        <!-- Fora do Prazo (Atrasados) -->
        <div @click="filtrarPorCard({ filtro_sla: 'atrasado', modo: 'lista' })" @mouseenter="cardHover = 'sla_atraso'" @mouseleave="cardHover = null"
             class="relative bg-white rounded-2xl p-4 border border-rose-200 hover:border-rose-400 cursor-pointer shadow-sm flex items-center justify-between transition"
             :class="(kpis.sla_atrasados || 0) > 0 ? 'bg-rose-50/70 border-rose-300' : ''">
            <div>
                <span class="text-[10px] font-bold text-rose-700 uppercase tracking-wider block mb-0.5">Fora do Prazo</span>
                <span class="text-2xl font-black text-rose-700" x-text="kpis.sla_atrasados || 0">0</span>
                <span class="text-[10px] text-rose-700/80 font-semibold block mt-0.5" x-text="(kpis.sla_atrasados || 0) > 0 ? 'Atrasados • Filtrar' : 'Nenhum atraso!'"></span>
            </div>
            <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-700 flex items-center justify-center text-base"><i class="fa-solid fa-hourglass-end"></i></div>
            <div x-show="cardHover === 'sla_atraso'" x-transition.opacity x-cloak class="absolute left-1 right-1 top-full mt-2 z-50 bg-slate-900 text-white text-[10px] rounded-xl p-3 shadow-2xl pointer-events-none">
                <strong class="text-rose-300 block mb-0.5">Chamados Fora do Tempo</strong>
                Chamados abertos cujo <code>prazo_entrega</code> já venceu. Clique para listar e priorizar agora.
            </div>
        </div>
    </div>

    <!-- BARRA DE CONTROLE: FILTROS COMPLETOS + ALTERNADOR DE VISÃO -->
    <div class="bg-white rounded-3xl p-4 border border-slate-100 shadow-sm mb-5 space-y-3">
        <!-- Linha 1: Busca + Visualização + Ordenação -->
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" x-model="filtros.busca" @input.debounce.300ms="carregarChamados()"
                       placeholder="Buscar por #ID, título, texto completo, solicitante ou observação..."
                       class="w-full pl-9 pr-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Ordenação -->
                <select x-model="filtros.ordenacao" @change="carregarChamados()"
                        class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                    <option value="prioridade">↕ Ordem: Prioridade Kanban</option>
                    <option value="criticidade">🔥 Ordem: Maior Criticidade</option>
                    <option value="recentes">🕒 Ordem: Mais Recentes</option>
                </select>

                <!-- Alternador Kanban / Lista / Painel (Disponível para todos) -->
                <div class="bg-slate-100 p-0.5 rounded-xl border border-slate-200 flex items-center">
                    <button type="button" @click="modoExibicao = 'kanban'" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition"
                            :class="modoExibicao === 'kanban' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                        <i class="fa-solid fa-table-columns"></i>
                        <span>Kanban</span>
                    </button>
                    <button type="button" @click="modoExibicao = 'lista'" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition"
                            :class="modoExibicao === 'lista' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                        <i class="fa-solid fa-list"></i>
                        <span>Lista</span>
                    </button>
                    <button type="button" @click="modoExibicao = 'dashboard'" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition"
                            :class="modoExibicao === 'dashboard' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Painel & KPIs</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Linha 2: Filtros de Quem Solicitou, Atendente, Sistema, Módulo, Categoria -->
        <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100">
            <!-- Quem Solicitou -->
            <select x-model="filtros.solicitante" @change="carregarChamados()"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                <option value="todos">👤 Solicitante: Todos</option>
                <option value="mim">🙋‍♂️ Apenas Meus Chamados</option>
                <template x-for="sol in solicitantes" :key="sol.username">
                    <option :value="sol.username" x-text="`👤 ${sol.username} (${sol.total})`"></option>
                </template>
            </select>

            <!-- Filtro Atendente (Admin/TI) -->
            <div x-show="usuario && usuario.pode_ver_todos" class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200" style="display: none;">
                <button type="button" @click="filtroAtendente = 'todos'; carregarChamados()"
                        class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition"
                        :class="filtroAtendente === 'todos' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    Todos TI
                </button>
                <button type="button" @click="filtroAtendente = 'mim'; carregarChamados()"
                        class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition flex items-center gap-1"
                        :class="filtroAtendente === 'mim' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    <i class="fa-solid fa-user-check text-emerald-400"></i>
                    <span>Meus Atend.</span>
                </button>
                <button type="button" @click="filtroAtendente = 'sem_atendente'; carregarChamados()"
                        class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition"
                        :class="filtroAtendente === 'sem_atendente' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                    Sem Técnico
                </button>
            </div>

            <!-- Filtro Sistema -->
            <select x-model="filtros.tipo_sistema" @change="carregarChamados()"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                <option value="todos">💻 Todos os Sistemas</option>
                <template x-for="s in sistemas" :key="s.id">
                    <option :value="s.id" x-text="s.nome"></option>
                </template>
            </select>

            <!-- Filtro Módulo -->
            <select x-model="filtros.modulo" @change="carregarChamados()"
                    x-show="filtros.tipo_sistema === 'todos' || filtros.tipo_sistema === 'PORTAL'"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                <option value="todos">🧩 Todos os Módulos Web</option>
                <template x-for="m in modulos" :key="m.slug">
                    <option :value="m.slug" x-text="`${m.nome} (${m.setor})`"></option>
                </template>
            </select>

            <!-- Filtro Categoria -->
            <select x-model="filtros.categoria" @change="carregarChamados()"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                <option value="todas">🏷️ Todas Categorias</option>
                <option value="bug">🐛 Bug / Erro</option>
                <option value="melhoria">🚀 Melhoria / Ajuste</option>
                <option value="duvida">❓ Dúvida / Orientação</option>
                <option value="permissao">🔐 Permissão / Acesso</option>
                <option value="equipamento">🖨️ Equipamento / Infra</option>
            </select>

            <!-- Status filtro rápido (útil na Lista) -->
            <select x-model="filtros.status" @change="carregarChamados()"
                    class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                <option value="">📌 Todos Status</option>
                <option value="0">Aberto</option>
                <option value="1">Em Análise</option>
                <option value="2">Em Correção</option>
                <option value="3">Concluído</option>
                <option value="4">Cancelado</option>
            </select>
        </div>

        <!-- Linha 3: Pills Rápidos de Criticidade + Período -->
        <div class="pt-2.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <!-- Pills de Criticidade -->
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Prioridade:</span>
                <template x-for="cr in [
                    {val:'todas', label:'Todas'},
                    {val:'critica', label:'🔴 Crítica'},
                    {val:'alta', label:'🟠 Alta'},
                    {val:'media', label:'🔵 Média'},
                    {val:'baixa', label:'🟢 Baixa'}
                ]" :key="cr.val">
                    <button type="button" @click="filtros.criticidade = cr.val; carregarChamados()"
                            class="px-2.5 py-1 rounded-xl text-[11px] font-bold border transition"
                            :class="filtros.criticidade === cr.val ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'"
                            x-text="cr.label"></button>
                </template>
            </div>

            <!-- Filtro de Período -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200">
                    <button type="button" @click="definirPeriodo('hoje')" class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === 'hoje' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">Hoje</button>
                    <button type="button" @click="definirPeriodo('7dias')" class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === '7dias' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">7 Dias</button>
                    <button type="button" @click="definirPeriodo('mes')" class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === 'mes' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">Mês</button>
                    <button type="button" @click="definirPeriodo('todos')" class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === 'todos' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">Todos</button>
                </div>

                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2 py-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">De:</span>
                    <input type="date" x-model="filtros.data_inicio" @change="filtroPeriodoAtivo = 'custom'; carregarChamados()"
                           class="bg-transparent border-0 text-[11px] font-semibold text-slate-700 focus:outline-none">
                </div>
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2 py-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Até:</span>
                    <input type="date" x-model="filtros.data_fim" @change="filtroPeriodoAtivo = 'custom'; carregarChamados()"
                           class="bg-transparent border-0 text-[11px] font-semibold text-slate-700 focus:outline-none">
                </div>
                <button type="button" @click="limparFiltros()"
                        class="text-[11px] font-bold text-rose-600 hover:text-rose-800 px-2.5 py-1 rounded-xl bg-rose-50 hover:bg-rose-100 transition">
                    <i class="fa-solid fa-rotate-left"></i> Resetar
                </button>
            </div>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL 1: MODO KANBAN INTERATIVO COM DRAG-AND-DROP -->
    <div x-show="modoExibicao === 'kanban'" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <template x-for="col in [
            {st: 0, nome: 'Abertos', dot: 'bg-amber-400', colBg: 'bg-amber-50/40 border-amber-200/70', badge: 'text-amber-700 bg-amber-100', vazio: 'Arraste um chamado para Abertos'},
            {st: 1, nome: 'Em Análise', dot: 'bg-blue-500', colBg: 'bg-blue-50/40 border-blue-200/70', badge: 'text-blue-700 bg-blue-100', vazio: 'Arraste um chamado para Em Análise'},
            {st: 2, nome: 'Em Correção', dot: 'bg-purple-500', colBg: 'bg-purple-50/40 border-purple-200/70', badge: 'text-purple-700 bg-purple-100', vazio: 'Arraste um chamado para Em Correção'},
            {st: 3, nome: 'Concluídos', dot: 'bg-emerald-500', colBg: 'bg-emerald-50/40 border-emerald-200/70', badge: 'text-emerald-700 bg-emerald-100', vazio: 'Arraste aqui ao concluir'}
        ]" :key="col.st">
            <div class="rounded-2xl p-3 border flex flex-col transition-all duration-150"
                 :class="dragOverStatus === col.st ? 'ring-2 ring-indigo-500 bg-indigo-50/60 border-indigo-400 shadow-lg' : col.colBg"
                 @dragover.prevent="onDragOverCol($event, col.st)"
                 @dragleave="onDragLeaveCol($event, col.st)"
                 @drop="onDropCol($event, col.st)">

                <!-- Cabeçalho da Coluna -->
                <div class="flex items-center justify-between mb-3 px-1">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :class="col.dot"></span>
                        <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider" x-text="col.nome">Coluna</h3>
                    </div>
                    <span class="text-xs font-black px-2.5 py-0.5 rounded-lg border border-white/80 shadow-2xs"
                          :class="col.badge"
                          x-text="colunaChamados(col.st).length">0</span>
                </div>

                <!-- Lista de Cards Arrastáveis (drag apenas para TI liberado) -->
                <div class="kanban-col custom-scrollbar space-y-2.5 flex-1 pr-1">
                    <template x-for="(c, idx) in colunaChamados(col.st)" :key="c.idchamado">
                        <div :draggable="ehTiLiberado() ? 'true' : 'false'"
                             @dragstart="onDragStart($event, c)"
                             @dragend="onDragEnd()"
                             @dragover.prevent="onDragOverCard($event, c)"
                             @drop.stop="onDropCard($event, c)"
                             @click="abrirDetalhes(c.idchamado)"
                             class="kanban-card bg-white p-3.5 rounded-2xl border border-slate-200/90 shadow-xs cursor-pointer hover:border-indigo-300 group select-none"
                             :class="[
                                bordaEsquerdaCriticidade(c.criticidade),
                                draggingId === c.idchamado ? 'opacity-40 scale-95 ring-2 ring-indigo-400' : '',
                                dragOverCardId === c.idchamado ? 'border-t-4 border-t-indigo-600 pt-4' : '',
                                col.st === 3 ? 'opacity-85 hover:opacity-100' : ''
                             ]">

                            <!-- Topo do Card: Grip + ID + Posição + Ações Rápidas -->
                            <div class="flex items-center justify-between gap-1.5 mb-2">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <i x-show="ehTiLiberado()" class="fa-solid fa-grip-vertical text-slate-300 group-hover:text-indigo-400 cursor-grab text-xs" title="Arraste com o mouse"></i>
                                    <span class="text-[10px] font-black text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded-md border border-indigo-100" x-text="`#${c.idchamado}`">#0</span>
                                    <span class="text-[9px] font-black text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded-md" :title="`Posição #${c.prioridade} na fila`" x-text="`${idx + 1}º`">1º</span>
                                    <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                                </div>

                                <div class="flex items-center gap-1 shrink-0" @click.stop>
                                    <button type="button" x-show="ehTiLiberado() && idx > 0 && col.st !== 3"
                                            @click.stop="moverParaTopo(c)"
                                            class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-indigo-600 text-slate-500 hover:text-white flex items-center justify-center text-[10px] transition"
                                            title="Enviar para o topo da prioridade (#1)">
                                        <i class="fa-solid fa-arrow-up"></i>
                                    </button>
                                    <select x-show="ehTiLiberado()"
                                            :value="c.criticidade"
                                            @change.stop="alterarCriticidadeRapida(c, $event.target.value)"
                                            @click.stop
                                            class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded-lg cursor-pointer focus:outline-none"
                                            :class="badgeCriticidade(c.criticidade)"
                                            title="Alterar criticidade rapidamente">
                                        <option value="critica">CRÍTICA</option>
                                        <option value="alta">ALTA</option>
                                        <option value="media">MÉDIA</option>
                                        <option value="baixa">BAIXA</option>
                                    </select>
                                    <span x-show="!ehTiLiberado()"
                                          class="text-[9px] font-black uppercase px-2 py-0.5 rounded-lg"
                                          :class="badgeCriticidade(c.criticidade)"
                                          x-text="String(c.criticidade || 'media').toUpperCase()">MÉDIA</span>
                                </div>
                            </div>

                            <!-- Título e Resumo -->
                            <h4 class="text-xs font-extrabold text-slate-800 line-clamp-2 leading-snug mb-1" x-text="c.titulo">Título</h4>
                            <p class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed mb-2" x-text="c.motivo">Relato</p>

                            <!-- Preview rápido de print se houver -->
                            <div x-show="c.anexo_url && ehImagem(c.anexo_url)" class="mb-2" @click.stop="abrirLightbox(c.anexo_url, `Print do Chamado #${c.idchamado}`)">
                                <img :src="c.anexo_url" class="w-full h-24 object-cover rounded-xl border border-slate-200 hover:opacity-90 transition" loading="lazy">
                            </div>

                            <!-- Tags de Contexto + Contadores -->
                            <div class="flex items-center justify-between gap-1.5 text-[10px] mb-2">
                                <span class="truncate max-w-[140px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md"
                                      x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>

                                <div class="flex items-center gap-2 text-slate-400 font-bold shrink-0">
                                    <span x-show="c.total_anexos > 0" class="flex items-center gap-0.5 text-indigo-600" title="Anexos / Prints">
                                        <i class="fa-solid fa-paperclip"></i>
                                        <span x-text="c.total_anexos">0</span>
                                    </span>
                                    <span class="flex items-center gap-0.5" title="Interações na timeline">
                                        <i class="fa-regular fa-comments"></i>
                                        <span x-text="c.total_interacoes || 0">0</span>
                                    </span>
                                </div>
                            </div>

                            <!-- Rodapé: Solicitante + Atendente + Data -->
                            <div class="flex items-center justify-between text-[10px] text-slate-400 pt-2 border-t border-slate-100 gap-1">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="font-bold text-slate-700 truncate max-w-[85px]" :title="`Solicitante: ${c.solicitante}`">
                                        <i class="fa-regular fa-user text-indigo-500 mr-0.5"></i><span x-text="c.solicitante || 'USER'">USER</span>
                                    </span>
                                    <span class="text-slate-300">→</span>
                                    <span class="font-bold text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded truncate max-w-[70px]" :title="`Responsável: ${c.atendente || 'ALAN'}`"
                                          x-text="c.atendente || 'ALAN'">ALAN</span>
                                </div>

                                <div class="flex items-center gap-1 shrink-0">
                                    <button type="button" x-show="usuario && usuario.pode_ver_todos && c.atendente !== usuario.username"
                                            @click.stop="assumirChamado(c.idchamado)"
                                            class="text-[9px] font-black text-emerald-700 bg-emerald-50 px-1.5 py-0.5 rounded hover:bg-emerald-100 border border-emerald-200 transition"
                                            title="Assumir chamado para mim">
                                        Assumir
                                    </button>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded border"
                                          :class="badgeSla(c.sla_status)"
                                          x-show="c.prazo_fmt"
                                          :title="'Prazo SLA: ' + c.prazo_fmt"
                                          x-text="c.sla_label"></span>
                                    <span class="text-[9px] font-semibold" x-text="(c.data_fmt || '').slice(0, 10)">Data</span>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div x-show="colunaChamados(col.st).length === 0"
                         class="text-center py-10 px-3 text-xs text-slate-400 border-2 border-dashed border-slate-200/80 rounded-2xl"
                         x-text="ehTiLiberado() ? col.vazio : 'Nenhum chamado nesta etapa'">
                        Nenhum chamado nesta coluna
                    </div>
                </div>
            </div>
        </template>
    </div>

    <!-- CONTEÚDO PRINCIPAL 2: MODO LISTA -->
    <div x-show="modoExibicao === 'lista'" class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden" style="display: none;">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                        <th class="py-3 px-4"># ID / Fila</th>
                        <th class="py-3 px-4">Sistema</th>
                        <th class="py-3 px-4">Título / Solicitação</th>
                        <th class="py-3 px-4">Módulo / Item</th>
                        <th class="py-3 px-4 text-center">Criticidade</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Solicitante</th>
                        <th class="py-3 px-4">Técnico TI</th>
                        <th class="py-3 px-4 text-center">Prints / Msg</th>
                        <th class="py-3 px-4">Abertura</th>
                        <th class="py-3 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-600">
                    <template x-for="c in chamados" :key="c.idchamado">
                        <tr class="hover:bg-indigo-50/40 transition cursor-pointer" @click="abrirDetalhes(c.idchamado)">
                            <td class="py-3 px-4">
                                <span class="font-black text-indigo-600 block" x-text="`#${c.idchamado}`">#1</span>
                                <span class="text-[10px] text-slate-400 font-bold" x-text="`Fila #${c.prioridade}`">Fila #1</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border inline-block" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            </td>
                            <td class="py-3 px-4 max-w-xs">
                                <span class="font-bold text-slate-800 block line-clamp-1" x-text="c.titulo">Título</span>
                                <span class="text-[10px] text-slate-400 line-clamp-1 mt-0.5" x-text="c.motivo">Motivo</span>
                            </td>
                            <td class="py-3 px-4 text-slate-700 font-bold" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</td>
                            <td class="py-3 px-4 text-center" @click.stop>
                                <select x-show="ehTiLiberado()" :value="c.criticidade" @change.stop="alterarCriticidadeRapida(c, $event.target.value)"
                                        class="text-[9px] font-black uppercase px-2 py-1 rounded-full cursor-pointer focus:outline-none"
                                        :class="badgeCriticidade(c.criticidade)">
                                    <option value="critica">CRÍTICA</option>
                                    <option value="alta">ALTA</option>
                                    <option value="media">MÉDIA</option>
                                    <option value="baixa">BAIXA</option>
                                </select>
                                <span x-show="!ehTiLiberado()"
                                      class="text-[9px] font-black uppercase px-2.5 py-1 rounded-full inline-block"
                                      :class="badgeCriticidade(c.criticidade)"
                                      x-text="String(c.criticidade || 'media').toUpperCase()">MÉDIA</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full border" :class="c.status_info.cor" x-text="c.status_info.label">Aberto</span>
                            </td>
                            <td class="py-3 px-4 font-bold text-slate-700" x-text="c.solicitante">User</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-slate-800" x-text="c.atendente || 'ALAN'">ALAN</span>
                                    <button type="button" x-show="usuario && usuario.pode_ver_todos && c.atendente !== usuario.username"
                                            @click.stop="assumirChamado(c.idchamado)"
                                            class="text-[9px] font-bold text-emerald-700 hover:text-emerald-900 px-1.5 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition">
                                        Assumir
                                    </button>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-center text-[11px]">
                                <span x-show="c.total_anexos > 0" class="text-indigo-600 font-bold mr-2"><i class="fa-solid fa-paperclip"></i> <span x-text="c.total_anexos"></span></span>
                                <span class="text-slate-500"><i class="fa-regular fa-comments"></i> <span x-text="c.total_interacoes || 0"></span></span>
                            </td>
                            <td class="py-3 px-4 text-slate-400 text-[11px]" x-text="c.data_fmt">10/11/2026</td>
                            <td class="py-3 px-4 text-center" @click.stop>
                                <div class="flex items-center justify-center gap-1">
                                    <button type="button" @click="moverParaTopo(c)" x-show="ehTiLiberado() && c.prioridade > 1 && c.status != 3"
                                            class="px-2 py-1 rounded-lg bg-amber-50 text-amber-700 hover:bg-amber-100 font-bold text-[10px]" title="Mover para Topo (#1)">
                                        <i class="fa-solid fa-arrow-up"></i>
                                    </button>
                                    <button type="button" @click="abrirDetalhes(c.idchamado)"
                                            class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-bold text-xs transition"
                                            x-text="ehTiLiberado() ? 'Gerenciar / Ver' : 'Acompanhar'">
                                        Acompanhar
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="chamados.length === 0">
                        <td colspan="11" class="py-10 text-center text-xs text-slate-400">
                            Nenhum chamado encontrado com os filtros selecionados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL 3: MODO DASHBOARD EXECUTIVO & RASTREAMENTO POR USUÁRIO -->
    <div x-show="modoExibicao === 'dashboard'" class="space-y-6" style="display: none;">

        <!-- BANNER EXECUTIVO SERVICE DESK ANALYTICS -->
        <div class="rounded-3xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-5 sm:p-6 shadow-xl border border-indigo-800/40">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                        <span class="text-[10px] font-black uppercase tracking-widest px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                            <i class="fa-solid fa-radar text-amber-300 mr-1"></i> Inteligência &amp; Rastreamento Service Desk
                        </span>
                        <span class="text-[10px] font-bold text-emerald-300 bg-emerald-500/15 px-2.5 py-0.5 rounded-full border border-emerald-400/30"
                              x-text="`${(metricas.ranking_solicitantes || []).length} solicitante(s) mapeado(s)`">0 solicitantes</span>
                    </div>
                    <h2 class="text-lg sm:text-xl font-black tracking-tight">Painel de Demandas por Solicitante, SLA &amp; Rastreamento Operacional</h2>
                    <p class="text-xs text-indigo-200/80 mt-0.5">
                        Acompanhe em tempo real quem mais demanda chamados, gargalos por sistema/módulo e clique em qualquer usuário para rastrear todas as solicitações abertas.
                    </p>
                </div>

                <!-- Destaque Dinâmico: Líder de Aberturas + MTTR -->
                <div class="flex flex-wrap items-center gap-3 shrink-0">
                    <div class="bg-white/10 backdrop-blur-xs border border-white/15 rounded-2xl px-4 py-2.5 min-w-[190px]"
                         x-show="(metricas.ranking_solicitantes || []).length > 0">
                        <span class="text-[9px] font-black uppercase tracking-wider text-amber-300 block">🏆 Top Solicitante (#1)</span>
                        <div class="flex items-center justify-between gap-3 mt-0.5">
                            <span class="text-sm font-black text-white truncate" x-text="(metricas.ranking_solicitantes || [])[0]?.username || '-'">-</span>
                            <span class="text-xs font-black px-2 py-0.5 rounded-lg bg-amber-400 text-slate-950"
                                  x-text="`${(metricas.ranking_solicitantes || [])[0]?.total || 0} chamados`">0</span>
                        </div>
                        <span class="text-[10px] text-indigo-200 block mt-0.5"
                              x-text="`${(metricas.ranking_solicitantes || [])[0]?.percentual || 0}% do volume • ${(metricas.ranking_solicitantes || [])[0]?.pendentes || 0} pendente(s)`"></span>
                    </div>

                    <div class="bg-white/10 backdrop-blur-xs border border-white/15 rounded-2xl px-4 py-2.5 min-w-[145px]">
                        <span class="text-[9px] font-black uppercase tracking-wider text-emerald-300 block">⏱️ Tempo Médio (MTTR)</span>
                        <span class="text-lg font-black text-white block mt-0.5"
                              x-text="kpis.tempo_medio_horas ? `${kpis.tempo_medio_horas}h` : '--'">--</span>
                        <span class="text-[10px] text-indigo-200 block" x-text="`${kpis.concluidos_total || 0} resolvidos no total`">0 resolvidos</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 KPI CARDS EXECUTIVOS -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Taxa Global de Resolução</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-emerald-600" x-text="`${kpis.taxa_resolucao || 0}%`">0%</span>
                        <span class="text-[11px] font-bold text-slate-400">concluídos</span>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1" x-text="`${kpis.concluidos_total || 0} total (${kpis.concluidos_mes || 0} no mês)`">0 concluídos</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Fila Ativa de TI</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-indigo-600" x-text="(parseInt(kpis.abertos || 0) + parseInt(kpis.em_correcao || 0))">0</span>
                        <span class="text-[11px] font-bold text-slate-400">pendentes</span>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1" x-text="`${kpis.abertos || 0} triagem/análise · ${kpis.em_correcao || 0} em dev`">0 em andamento</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-bars-progress"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between"
                 :class="kpis.criticos > 0 ? 'ring-2 ring-rose-300 bg-rose-50/20' : ''">
                <div>
                    <span class="text-[11px] font-bold uppercase tracking-wider block mb-0.5"
                          :class="kpis.criticos > 0 ? 'text-rose-600' : 'text-slate-400'">Atenção Imediata</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black" :class="kpis.criticos > 0 ? 'text-rose-600' : 'text-slate-800'" x-text="kpis.criticos || 0">0</span>
                        <span class="text-[11px] font-bold text-slate-400">críticos</span>
                    </div>
                    <span class="text-[10px] font-semibold block mt-1"
                          :class="kpis.criticos > 0 ? 'text-rose-600 font-bold' : 'text-slate-400'"
                          x-text="kpis.criticos > 0 ? 'Processos corporativos em risco' : 'Operação 100% normal'">Normal</span>
                </div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl"
                     :class="kpis.criticos > 0 ? 'bg-rose-100 text-rose-600 animate-pulse' : 'bg-slate-100 text-slate-500'">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>

            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Total de Incidentes</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-slate-800" x-text="kpis.total || 0">0</span>
                        <span class="text-[11px] font-bold text-slate-400">registrados</span>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Base histórica completa</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
        </div>

        <!-- GRÁFICO PRINCIPAL: TOP USUÁRIOS QUE MAIS ABREM CHAMADOS (BARRAS EMPILHADAS) -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-slate-200/80 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-5">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 block">Gráfico Comparativo de Solicitantes</span>
                    <h3 class="text-sm sm:text-base font-black text-slate-800">Ranking: Usuários que Mais Abrem Chamados &amp; Rastreamento de Status</h3>
                </div>

                <!-- Legenda das Barras Empilhadas -->
                <div class="flex flex-wrap items-center gap-3 text-[11px] font-bold">
                    <span class="flex items-center gap-1.5 text-slate-600">
                        <span class="w-3 h-3 rounded-sm bg-emerald-500 inline-block"></span> Concluídos
                    </span>
                    <span class="flex items-center gap-1.5 text-slate-600">
                        <span class="w-3 h-3 rounded-sm bg-indigo-500 inline-block"></span> Em Análise / Dev
                    </span>
                    <span class="flex items-center gap-1.5 text-slate-600">
                        <span class="w-3 h-3 rounded-sm bg-amber-400 inline-block"></span> Abertos
                    </span>
                </div>
            </div>

            <div x-show="(metricas.ranking_solicitantes || []).length === 0" class="py-10 text-center text-xs text-slate-400">
                Nenhum dado de solicitante encontrado para exibir no gráfico.
            </div>

            <div class="space-y-3" x-show="(metricas.ranking_solicitantes || []).length > 0">
                <template x-for="(sol, idxSol) in (metricas.ranking_solicitantes || [])" :key="sol.username">
                    <div class="p-3.5 rounded-2xl border transition"
                         :class="usuarioRastreado === sol.username ? 'bg-indigo-50/70 border-indigo-400 ring-1 ring-indigo-300 shadow-sm' : 'bg-slate-50/60 border-slate-200/70 hover:bg-white hover:border-indigo-200'">
                        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5 mb-2">
                            <div class="flex items-center gap-3 min-w-0">
                                <!-- Posição no Ranking -->
                                <span class="w-7 h-7 rounded-xl text-xs font-black flex items-center justify-center shrink-0"
                                      :class="idxSol === 0 ? 'bg-amber-400 text-slate-950 shadow-xs' : (idxSol === 1 ? 'bg-slate-300 text-slate-900' : (idxSol === 2 ? 'bg-amber-700/20 text-amber-900' : 'bg-slate-200 text-slate-600'))"
                                      x-text="`${idxSol + 1}º`">1º</span>

                                <!-- Avatar Iniciais -->
                                <div class="w-9 h-9 rounded-full bg-indigo-600 text-white font-black text-xs flex items-center justify-center shrink-0 shadow-2xs"
                                     x-text="String(sol.username || 'US').slice(0, 2)">US</div>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="text-xs sm:text-sm font-black text-slate-800" x-text="sol.username">USUARIO</span>
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-800"
                                              x-text="`${sol.total} chamado(s) (${sol.percentual}%)`">0</span>
                                        <span x-show="sol.criticos > 0"
                                              class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-rose-100 text-rose-700 border border-rose-200"
                                              x-text="`🔴 ${sol.criticos} Crítico(s)`"></span>
                                        <span x-show="sol.altos > 0"
                                              class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-200"
                                              x-text="`🟠 ${sol.altos} Alto(s)`"></span>
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-medium flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                                        <span>Foco: <strong class="text-slate-700" x-text="`${sol.sistema_principal} • ${sol.modulo_principal}`">PORTAL</strong></span>
                                        <span>·</span>
                                        <span>Última abertura: <strong class="text-slate-700" x-text="sol.ultimo_chamado_fmt">-</strong></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Ações de Rastreamento + KPIs Inline -->
                            <div class="flex flex-wrap items-center gap-2 shrink-0">
                                <span class="text-[10px] font-bold text-emerald-700 bg-emerald-50 px-2 py-1 rounded-lg border border-emerald-200"
                                      x-text="`✔ ${sol.concluidos} Concluídos (${sol.taxa_conclusao}%)`"></span>
                                <span class="text-[10px] font-bold text-amber-700 bg-amber-50 px-2 py-1 rounded-lg border border-amber-200"
                                      x-text="`⏳ ${sol.pendentes} Pendentes`"></span>

                                <button type="button" @click="selecionarUsuarioRastreamento(sol.username)"
                                        class="px-3 py-1.5 rounded-xl text-[11px] font-black flex items-center gap-1.5 transition border"
                                        :class="usuarioRastreado === sol.username ? 'bg-indigo-600 text-white border-indigo-600 shadow-xs' : 'bg-white text-indigo-700 border-indigo-200 hover:bg-indigo-50'">
                                    <i class="fa-solid fa-crosshairs"></i>
                                    <span x-text="usuarioRastreado === sol.username ? 'Ocultar Rastreio' : 'Rastrear Aqui'">Rastrear Aqui</span>
                                </button>

                                <button type="button" @click="filtrarListaPorSolicitante(sol.username, 'lista')"
                                        class="px-2.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-[11px] font-bold flex items-center gap-1 transition"
                                        title="Abrir todos os chamados deste solicitante na Lista">
                                    <i class="fa-solid fa-list-ul"></i>
                                    <span class="hidden sm:inline">Filtrar Lista</span>
                                </button>
                            </div>
                        </div>

                        <!-- Barra Horizontal Empilhada Proporcional ao Top #1 -->
                        <div class="w-full h-4 bg-slate-200/80 rounded-full overflow-hidden p-0.5 flex items-center">
                            <div class="h-full rounded-full overflow-hidden flex transition-all duration-500"
                                 :style="`width: ${Math.max(sol.percentual_barra || 0, 2)}%`">
                                <div x-show="sol.concluidos > 0"
                                     class="h-full bg-emerald-500 transition-all duration-500"
                                     :style="`width: ${Math.round((sol.concluidos / (sol.total || 1)) * 100)}%`"
                                     :title="`Concluídos: ${sol.concluidos}`"></div>
                                <div x-show="(sol.em_analise + sol.em_correcao) > 0"
                                     class="h-full bg-indigo-500 transition-all duration-500"
                                     :style="`width: ${Math.round(((sol.em_analise + sol.em_correcao) / (sol.total || 1)) * 100)}%`"
                                     :title="`Em Análise / Dev: ${sol.em_analise + sol.em_correcao}`"></div>
                                <div x-show="sol.abertos > 0"
                                     class="h-full bg-amber-400 transition-all duration-500"
                                     :style="`width: ${Math.round((sol.abertos / (sol.total || 1)) * 100)}%`"
                                     :title="`Abertos: ${sol.abertos}`"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- PAINEL INTERATIVO DRILL-DOWN DE RASTREAMENTO DO USUÁRIO SELECIONADO -->
        <div x-show="usuarioRastreado !== ''" x-transition class="bg-white rounded-3xl p-5 sm:p-6 border-2 border-indigo-400 shadow-lg" style="display: none;">
            <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-100 mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-600 text-white flex items-center justify-center shadow-sm">
                        <i class="fa-solid fa-magnifying-glass-chart"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 block">Rastreamento Individual de Demandas</span>
                        <h3 class="text-sm sm:text-base font-black text-slate-800">
                            Chamados de <span class="text-indigo-700" x-text="usuarioRastreado">USUARIO</span>
                            (<span x-text="chamadosDoUsuarioRastreado().length">0</span> carregado(s) na visão atual)
                        </h3>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="limparFiltros(); modoExibicao = 'dashboard'"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition">
                        <i class="fa-solid fa-rotate"></i> Mostrar Base Completa
                    </button>
                    <button type="button" @click="usuarioRastreado = ''"
                            class="px-3 py-1.5 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-black transition">
                        <i class="fa-solid fa-xmark"></i> Fechar Rastreio
                    </button>
                </div>
            </div>

            <div x-show="chamadosDoUsuarioRastreado().length === 0" class="py-8 text-center text-xs text-slate-500 bg-slate-50 rounded-2xl border border-dashed border-slate-200">
                Nenhum chamado visível com os filtros ativos no topo. Clique em <strong>"Mostrar Base Completa"</strong> ou <strong>"Filtrar Lista"</strong> para visualizar todos os chamados deste solicitante.
            </div>

            <div class="overflow-x-auto" x-show="chamadosDoUsuarioRastreado().length > 0">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-black uppercase text-slate-400">
                            <th class="py-2.5 px-3">#ID</th>
                            <th class="py-2.5 px-3">Sistema / Módulo</th>
                            <th class="py-2.5 px-3">Título &amp; Relato</th>
                            <th class="py-2.5 px-3 text-center">Criticidade</th>
                            <th class="py-2.5 px-3 text-center">Estágio</th>
                            <th class="py-2.5 px-3">TI Responsável</th>
                            <th class="py-2.5 px-3">Abertura</th>
                            <th class="py-2.5 px-3 text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="ch in chamadosDoUsuarioRastreado()" :key="`rast-${ch.idchamado}`">
                            <tr @click="abrirDetalhes(ch.idchamado)" class="hover:bg-indigo-50/50 cursor-pointer transition">
                                <td class="py-2.5 px-3 font-black text-indigo-700" x-text="`#${ch.idchamado}`">#0</td>
                                <td class="py-2.5 px-3">
                                    <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border mr-1" :class="badgeSistema(ch.tipo_sistema)" x-text="ch.tipo_sistema">PORTAL</span>
                                    <span class="text-[11px] font-semibold text-slate-600" x-text="ch.modulo_nome || ch.subtipo_item || '-'">Módulo</span>
                                </td>
                                <td class="py-2.5 px-3 max-w-md">
                                    <span class="font-bold text-slate-800 block line-clamp-1" x-text="ch.titulo">Título</span>
                                    <span class="text-[10px] text-slate-400 line-clamp-1" x-text="ch.motivo">Motivo</span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full" :class="badgeCriticidade(ch.criticidade)" x-text="ch.criticidade">media</span>
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full border" :class="ch.status_info.cor" x-text="ch.status_info.label">Aberto</span>
                                </td>
                                <td class="py-2.5 px-3 font-bold text-emerald-700" x-text="ch.atendente || 'ALAN'">ALAN</td>
                                <td class="py-2.5 px-3 text-[11px] text-slate-400" x-text="ch.data_fmt">-</td>
                                <td class="py-2.5 px-3 text-center">
                                    <span class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 font-bold text-[11px]">Abrir</span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- TABELA ANALÍTICA COMPLETA DE RASTREAMENTO POR SOLICITANTE -->
        <div class="bg-white rounded-3xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-black uppercase tracking-wider text-slate-800">Matriz Analítica de Rastreamento por Solicitante</h3>
                    <p class="text-[11px] text-slate-400 font-medium">Consolidado histórico com volume, backlog aberto, criticidade e taxa de resolução</p>
                </div>
                <span class="text-[11px] font-black px-2.5 py-1 rounded-xl bg-slate-100 text-slate-700"
                      x-text="`${(metricas.ranking_solicitantes || []).length} usuário(s)`">0</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                            <th class="py-3 px-4">#</th>
                            <th class="py-3 px-4">Solicitante</th>
                            <th class="py-3 px-4 text-center">Total (%)</th>
                            <th class="py-3 px-4 text-center">Abertos</th>
                            <th class="py-3 px-4 text-center">Análise / Dev</th>
                            <th class="py-3 px-4 text-center">Concluídos</th>
                            <th class="py-3 px-4 text-center">Críticos / Altos</th>
                            <th class="py-3 px-4">Foco Principal</th>
                            <th class="py-3 px-4 text-center">Conclusão</th>
                            <th class="py-3 px-4">Última Abertura</th>
                            <th class="py-3 px-4 text-center">Rastrear</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-semibold text-slate-600">
                        <template x-for="(sol, idxT) in (metricas.ranking_solicitantes || [])" :key="`tab-${sol.username}`">
                            <tr class="hover:bg-indigo-50/30 transition">
                                <td class="py-2.5 px-4 font-black text-slate-400" x-text="idxT + 1">1</td>
                                <td class="py-2.5 px-4 font-black text-slate-800" x-text="sol.username">USER</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="font-black text-indigo-700" x-text="sol.total">0</span>
                                    <span class="text-[10px] text-slate-400" x-text="`(${sol.percentual}%)`"></span>
                                </td>
                                <td class="py-2.5 px-4 text-center font-bold text-amber-600" x-text="sol.abertos">0</td>
                                <td class="py-2.5 px-4 text-center font-bold text-purple-600" x-text="sol.em_analise + sol.em_correcao">0</td>
                                <td class="py-2.5 px-4 text-center font-bold text-emerald-600" x-text="sol.concluidos">0</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-black text-[10px]" x-text="sol.criticos">0</span>
                                    <span class="text-slate-300 mx-0.5">/</span>
                                    <span class="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-black text-[10px]" x-text="sol.altos">0</span>
                                </td>
                                <td class="py-2.5 px-4 text-[11px] text-slate-600" x-text="`${sol.sistema_principal} (${sol.modulo_principal})`">-</td>
                                <td class="py-2.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black"
                                          :class="sol.taxa_conclusao >= 75 ? 'bg-emerald-100 text-emerald-700' : (sol.taxa_conclusao >= 40 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700')"
                                          x-text="`${sol.taxa_conclusao}%`">0%</span>
                                </td>
                                <td class="py-2.5 px-4 text-[11px] text-slate-400" x-text="sol.ultimo_chamado_fmt">-</td>
                                <td class="py-2.5 px-4 text-center">
                                    <button type="button" @click="selecionarUsuarioRastreamento(sol.username)"
                                            class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition"
                                            :class="usuarioRastreado === sol.username ? 'bg-indigo-600 text-white' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'">
                                        <span x-text="usuarioRastreado === sol.username ? 'Ativo' : 'Rastrear'">Rastrear</span>
                                    </button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- BLOCO SLA: DENTRO DO TEMPO x FORA DO TEMPO -->
        <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-slate-100 mb-4">
                <div>
                    <h3 class="text-xs font-black uppercase text-slate-800 flex items-center gap-2">
                        <i class="fa-solid fa-stopwatch text-indigo-600"></i> Controle de Prazo de Entrega (SLA)
                    </h3>
                    <p class="text-[11px] text-slate-400">Regra automática IA: Crítica (1d) · Alta (3d) · Média (5d) · Baixa (7d)</p>
                </div>
                <div class="flex items-center gap-3 text-xs font-black">
                    <span class="text-emerald-700 bg-emerald-50 px-3 py-1 rounded-full border border-emerald-200" x-text="(kpis.sla_percentual_abertos || 100) + '% Abertos no Prazo'"></span>
                    <span class="text-indigo-700 bg-indigo-50 px-3 py-1 rounded-full border border-indigo-200" x-text="(kpis.sla_percentual_concluidos || 100) + '% Entregues no Prazo'"></span>
                </div>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div @click="filtrarPorCard({ filtro_sla: 'no_prazo', modo: 'lista' })" class="p-3.5 rounded-xl bg-emerald-50/70 border border-emerald-200 cursor-pointer hover:border-emerald-400 transition">
                    <span class="text-[10px] font-black uppercase text-emerald-700 block">Dentro do Tempo (+2 dias)</span>
                    <span class="text-2xl font-black text-emerald-800" x-text="kpis.sla_no_prazo || 0">0</span>
                </div>
                <div @click="filtrarPorCard({ filtro_sla: 'vencendo', modo: 'lista' })" class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200 cursor-pointer hover:border-amber-400 transition">
                    <span class="text-[10px] font-black uppercase text-amber-700 block">Próximos da Solução (Hoje / Amanhã)</span>
                    <span class="text-2xl font-black text-amber-800" x-text="kpis.sla_vencendo || 0">0</span>
                </div>
                <div @click="filtrarPorCard({ filtro_sla: 'atrasado', modo: 'lista' })" class="p-3.5 rounded-xl bg-rose-50/80 border border-rose-300 cursor-pointer hover:border-rose-500 transition">
                    <span class="text-[10px] font-black uppercase text-rose-700 block">Fora do Tempo (Atrasados)</span>
                    <span class="text-2xl font-black text-rose-800" x-text="kpis.sla_atrasados || 0">0</span>
                </div>
            </div>
        </div>

        <!-- GRID ANALÍTICO 360° -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Funil de Estágios + Demandas por Sistema -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <h3 class="text-xs font-black uppercase text-slate-800">Funil de Estágios de Atendimento</h3>
                        <span class="text-[11px] font-bold text-slate-400" x-text="`${kpis.total || 0} total`">0 total</span>
                    </div>
                    <div class="space-y-3">
                        <template x-for="stItem in (metricas.por_status || [])" :key="stItem.status">
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="font-bold text-slate-700" x-text="stItem.label">Status</span>
                                    <span class="font-black text-slate-800" x-text="`${stItem.total} (${stItem.percentual}%)`">0</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2.5 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-500"
                                         :class="stItem.status == 3 ? 'bg-emerald-500' : (stItem.status == 2 ? 'bg-purple-500' : (stItem.status == 1 ? 'bg-blue-500' : (stItem.status == 0 ? 'bg-amber-400' : 'bg-slate-400')))"
                                         :style="`width: ${stItem.percentual || 0}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <h3 class="text-xs font-black uppercase text-slate-800">Demandas por Sistema Corporativo</h3>
                        <span class="text-xs font-black text-slate-400" x-text="`${(metricas.por_sistema || []).length} sistemas`">0</span>
                    </div>
                    <div class="space-y-3.5">
                        <template x-for="sis in (metricas.por_sistema || [])" :key="sis.sistema">
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border inline-block" :class="badgeSistema(sis.sistema)" x-text="sis.sistema">PORTAL</span>
                                        <span class="font-bold text-slate-700" x-text="nomeSistema(sis.sistema)">Nome</span>
                                    </div>
                                    <span class="text-xs font-black text-slate-800" x-text="sis.total">0</span>
                                </div>
                                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                                    <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500"
                                         :style="`width: ${Math.round((parseInt(sis.total) / (parseInt(kpis.total) || 1)) * 100)}%`"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Criticidade, Categorias, Módulos e Entregas Recentes -->
            <div class="space-y-6">
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <h3 class="text-xs font-black uppercase text-slate-800 pb-3 border-b border-slate-100 mb-4">Matriz de Criticidade &amp; Natureza</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                        <template x-for="crit in (metricas.por_criticidade || [])" :key="crit.criticidade">
                            <div class="p-3 rounded-xl border text-center" :class="badgeCriticidade(crit.criticidade)">
                                <span class="text-[10px] font-black uppercase block mb-0.5" x-text="crit.criticidade">Média</span>
                                <span class="text-2xl font-black block" x-text="crit.total">0</span>
                            </div>
                        </template>
                    </div>
                    <div class="pt-3 border-t border-slate-100 grid grid-cols-2 sm:grid-cols-3 gap-2">
                        <template x-for="cat in (metricas.por_categoria || [])" :key="cat.categoria">
                            <div class="bg-slate-50 border border-slate-200/70 rounded-xl px-3 py-2 flex items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-slate-700 truncate" x-text="labelCategoria(cat.categoria)">Cat</span>
                                <span class="text-[11px] font-black text-indigo-700" x-text="`${cat.total} (${cat.percentual}%)`">0</span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <h3 class="text-xs font-black uppercase text-slate-800 pb-3 border-b border-slate-100 mb-3">Top Módulos Mais Demandados</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <template x-for="(mod, idx) in (metricas.top_modulos || [])" :key="idx">
                            <div class="bg-slate-50 border border-slate-200/80 p-2.5 rounded-xl flex items-center justify-between">
                                <span class="text-[11px] font-bold text-slate-800 truncate pr-2" x-text="mod.modulo">Módulo</span>
                                <span class="text-xs font-black px-2 py-0.5 rounded-lg bg-indigo-100 text-indigo-700" x-text="mod.total">0</span>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <h3 class="text-xs font-black uppercase text-slate-800 pb-3 border-b border-slate-100 mb-3">Últimas Entregas Técnicas Concluídas</h3>
                    <div class="space-y-2">
                        <template x-for="ent in (metricas.resolvidos_recentes || [])" :key="ent.idchamado">
                            <div @click="abrirDetalhes(ent.idchamado)"
                                 class="p-2.5 rounded-xl bg-emerald-50/50 hover:bg-emerald-50 border border-emerald-200/70 flex items-center justify-between gap-2 cursor-pointer transition">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px] font-black text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded" x-text="`#${ent.idchamado}`">#0</span>
                                        <span class="text-xs font-bold text-slate-800 truncate" x-text="ent.titulo">Entrega</span>
                                    </div>
                                    <span class="text-[10px] text-slate-500 font-medium block mt-0.5"
                                          x-text="`${ent.modulo_nome || ent.tipo_sistema} · TI: ${ent.atendente || 'ALAN'} · ${ent.versao_entrega || 'v3.5'}`"></span>
                                </div>
                                <span class="text-[10px] font-black text-emerald-700 shrink-0" x-text="ent.concluido_fmt">-</span>
                            </div>
                        </template>
                        <div x-show="(metricas.resolvidos_recentes || []).length === 0" class="text-center py-4 text-xs text-slate-400">
                            Nenhuma entrega concluída ainda.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 1: ABERTURA DE CHAMADO COMPLETA COM IA & PRINT -->
    <div x-show="modalNovoAberto" @paste="tratarColagemPrint($event, 'novo')" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Abertura de Chamado Inteligente</h3>
                        <p class="text-xs text-slate-400">Escolha sistema, criticidade, cole prints (Ctrl+V) ou acione a triagem com IA</p>
                    </div>
                </div>
                <button type="button" @click="fecharModalNovo()" class="text-slate-400 hover:text-slate-600 w-8 h-8 rounded-lg flex items-center justify-center" title="Fechar (rascunho mantido automaticamente)">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Indicador de Auto-Rascunho Persistente -->
            <div x-show="temRascunhoPreenchido()" class="mt-3 px-3.5 py-2 rounded-2xl bg-emerald-50 border border-emerald-200 flex items-center justify-between gap-2 text-[11px]" style="display: none;">
                <span class="font-bold text-emerald-800 flex items-center gap-1.5">
                    <i class="fa-solid fa-floppy-disk text-emerald-600"></i>
                    <span>💾 Rascunho salvo automaticamente</span>
                    <span x-show="rascunhoSalvoEm" class="text-emerald-600 font-semibold" x-text="`(${rascunhoSalvoEm})`"></span>
                </span>
                <button type="button" @click="limparRascunhoNovo(true)"
                        class="px-2.5 py-1 rounded-lg bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 font-bold text-[10px] transition">
                    Limpar Rascunho
                </button>
            </div>

            <div class="py-4 space-y-4 overflow-y-auto custom-scrollbar flex-1 pr-1">
                <div>
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1.5">Sistema ou Equipamento Envolvido *</label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <template x-for="s in sistemas" :key="s.id">
                            <button type="button" @click="novoForm.tipo_sistema = s.id; marcarCampoManual('tipo_sistema'); salvarRascunhoNovo()"
                                    class="p-2.5 rounded-xl border text-left transition flex flex-col items-start gap-1 text-xs font-bold"
                                    :class="novoForm.tipo_sistema === s.id ? 'bg-indigo-50/80 border-indigo-500 text-indigo-900 shadow-xs ring-1 ring-indigo-400' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'">
                                <div class="flex items-center gap-1.5 w-full">
                                    <i class="fa-solid text-sm" :class="[s.icon, novoForm.tipo_sistema === s.id ? 'text-indigo-600' : 'text-slate-400']"></i>
                                    <span class="truncate font-black" x-text="s.id">ID</span>
                                </div>
                                <span class="text-[10px] text-slate-400 line-clamp-1 font-semibold" x-text="s.nome">Nome</span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Criticidade + Categoria + Filial + Prazo -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Criticidade / Urgência</label>
                        <select x-model="novoForm.criticidade" @change="marcarCampoManual('criticidade'); salvarRascunhoNovo()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="critica">🔴 Crítica (1 dia)</option>
                            <option value="alta">🟠 Alta (3 dias)</option>
                            <option value="media">🔵 Média (5 dias)</option>
                            <option value="baixa">🟢 Baixa (7 dias)</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Categoria</label>
                        <select x-model="novoForm.categoria" @change="marcarCampoManual('categoria'); salvarRascunhoNovo()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="bug">🐛 Bug / Erro</option>
                            <option value="melhoria">🚀 Melhoria / Novo Recurso</option>
                            <option value="duvida">❓ Dúvida / Orientação</option>
                            <option value="permissao">🔐 Permissão / Acesso</option>
                            <option value="equipamento">🖨️ Equipamento / Infra</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Filial</label>
                        <select x-model="novoForm.idfilial" @change="salvarRascunhoNovo()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <template x-for="f in filiais" :key="f.idfilial">
                                <option :value="f.idfilial" x-text="f.nome || `${f.idfilial} - ${f.razao}`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Prazo Entrega (SLA)</label>
                        <input type="date" x-model="novoForm.prazo_entrega" :readonly="!ehTiLiberado()" @change="salvarRascunhoNovo()"
                               title="Definido automaticamente pela criticidade (1d/3d/5d/7d) ou manualmente pelo TI"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- Vincular Solicitante (Exclusivo Admin / TI Liberado) -->
                <div x-show="ehTiLiberado()" class="bg-indigo-50/70 p-3 rounded-2xl border border-indigo-200/80">
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <label class="text-[11px] font-black text-indigo-900 uppercase tracking-wider">Solicitante do Chamado (Vincular Usuário)</label>
                        <span class="text-[10px] font-semibold text-indigo-600">Exclusivo Admin / TI</span>
                    </div>
                    <select x-model="novoForm.solicitante" @change="salvarRascunhoNovo()" class="w-full px-3 py-2 bg-white border border-indigo-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-indigo-500">
                        <template x-for="usr in usuariosSolicitantes" :key="`nv-${usr.username}`">
                            <option :value="usr.username" x-text="`${usr.username}${usr.nome && usr.nome !== usr.username ? ' (' + usr.nome + ')' : ''}`"></option>
                        </template>
                    </select>
                </div>

                <!-- Módulo do Portal (se PORTAL) ou Detalhe do Item -->
                <div x-show="novoForm.tipo_sistema === 'PORTAL'">
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Módulo do Portal</label>
                    <select x-model="novoForm.modulo_slug" @change="marcarCampoManual('modulo_slug'); salvarRascunhoNovo()" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                        <option value="">Deixar IA Detectar pelo Relato</option>
                        <option value="geral">🌐 Portal Geral / Novo Módulo / Arquitetura</option>
                        <template x-for="m in modulos" :key="m.slug">
                            <option :value="m.slug" x-text="`${m.nome} (${m.setor})`"></option>
                        </template>
                    </select>
                </div>

                <div x-show="novoForm.tipo_sistema !== 'PORTAL'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Rotina / Subtipo</label>
                        <input type="text" x-model="novoForm.subtipo_item" @input="salvarRascunhoNovo()" placeholder="Ex: Impressora, Pedido, CT-e, Rastreamento..."
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Nº Documento / Placa / Identificação</label>
                        <input type="text" x-model="novoForm.equipamento_detalhe" @input="salvarRascunhoNovo()" placeholder="Ex: NF 300808, Placa ABC-1234, Ramal 204..."
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <div>
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Título Resumido <span class="text-slate-400 font-normal lowercase">(opcional - IA gera se vazio)</span></label>
                    <input type="text" x-model="novoForm.titulo" @input="marcarCampoManual('titulo'); salvarRascunhoNovo()" placeholder="Ex: Erro ao filtrar saldo de estoque..."
                           class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                </div>

                <div>
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Descrição Completa do Problema / Solicitação *</label>
                    <textarea x-model="novoForm.relato" @input="salvarRascunhoNovo()" rows="4" placeholder="Descreva em detalhes o que ocorreu ou o que você precisa..."
                              class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-500 focus:bg-white transition"></textarea>
                </div>

                <!-- Anexo / Print Screen -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Anexo / Print da Tela</label>
                        <span class="text-[10px] text-indigo-700 font-bold bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-200/60">Cole print com Ctrl+V</span>
                    </div>
                    <input type="file" x-ref="novoAnexoInput" @change="selecionarArquivoAnexo($event, 'novo')" accept="image/*,.pdf,.txt,.log" class="hidden">

                    <div x-show="!novoForm.anexo_url" @click="$refs.novoAnexoInput.click()"
                         class="border-2 border-dashed border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/20 rounded-2xl p-4 text-center cursor-pointer transition">
                        <i class="fa-solid fa-cloud-arrow-up text-lg text-slate-400 mb-1"></i>
                        <span class="text-xs font-bold text-slate-700 block">Clique para selecionar imagem ou cole (Ctrl+V)</span>
                        <span class="text-[10px] text-slate-400">PNG, JPG, WebP, PDF ou TXT (máx. 15MB)</span>
                    </div>

                    <div x-show="novoForm.anexo_url" class="bg-indigo-50/70 border border-indigo-200 rounded-2xl p-3 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <template x-if="ehImagem(novoForm.anexo_url)">
                                <img :src="novoForm.anexo_url" class="w-14 h-14 object-cover rounded-xl border border-indigo-200 cursor-pointer"
                                     @click="abrirLightbox(novoForm.anexo_url, 'Print do Chamado')">
                            </template>
                            <span class="text-xs font-black text-indigo-950 truncate">Print / Arquivo Anexado</span>
                        </div>
                        <button type="button" @click="removerAnexo('novo')" class="w-8 h-8 rounded-xl bg-white hover:bg-rose-50 text-rose-500 border border-slate-200 flex items-center justify-center">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Botão IA -->
                <div class="flex items-center justify-between bg-indigo-50/60 p-3 rounded-2xl border border-indigo-100">
                    <span class="text-xs text-indigo-900 font-semibold"><i class="fa-solid fa-microchip text-indigo-600 mr-1.5"></i> Gerar diagnóstico técnico e título automático com IA</span>
                    <button type="button" @click="analisarComIa()" :disabled="analisandoIa || !novoForm.relato"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs rounded-xl flex items-center gap-2 transition">
                        <i class="fa-solid fa-wand-magic-sparkles" :class="analisandoIa ? 'fa-spin' : ''"></i>
                        <span x-text="analisandoIa ? 'Analisando...' : 'Analisar com IA'">Analisar com IA</span>
                    </button>
                </div>

                <div x-show="triagemFeita" class="bg-slate-900 text-slate-100 p-3.5 rounded-2xl">
                    <span class="text-[10px] font-black uppercase text-indigo-300 block mb-1">Dossiê Preliminar IA</span>
                    <pre class="text-[10px] font-mono leading-relaxed whitespace-pre-wrap max-h-36 overflow-y-auto" x-text="novoForm.diagnostico_ia"></pre>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                <button type="button" @click="fecharModalNovo()" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100">Fechar / Salvar Rascunho</button>
                <button type="button" @click="salvarNovoChamado()" :disabled="salvandoChamado || !novoForm.relato"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-black flex items-center gap-2 shadow-md">
                    <i class="fa-solid" :class="salvandoChamado ? 'fa-spinner fa-spin' : 'fa-paper-plane'"></i>
                    <span>Confirmar & Abrir Chamado</span>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL 2: DETALHES COMPLETOS, EDIÇÃO TOTAL, PRINTS E TIMELINE -->
    <div x-show="modalDetalhesAberto" @paste="tratarColagemPrint($event, 'modal_anexo')"
         class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 bg-slate-900/65 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-5 sm:p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[94vh] overflow-hidden">
            <template x-if="chamadoAtual">
                <div class="flex flex-col h-full overflow-hidden">

                    <!-- CABEÇALHO DO MODAL -->
                    <div class="flex items-start justify-between pb-3 border-b border-slate-100 gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                                <span class="text-xs font-black text-indigo-700 bg-indigo-50 px-2.5 py-0.5 rounded-lg border border-indigo-200" x-text="`CHAMADO #${chamadoAtual.idchamado}`">#1</span>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded border" :class="badgeSistema(chamadoAtual.tipo_sistema)" x-text="chamadoAtual.tipo_sistema">PORTAL</span>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded" :class="badgeCriticidade(chamadoAtual.criticidade)" x-text="chamadoAtual.criticidade">media</span>
                                <span class="text-[10px] font-black px-2 py-0.5 rounded bg-slate-100 text-slate-700" x-text="`Fila #${chamadoAtual.prioridade || 10}`">Fila #1</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600" x-text="chamadoAtual.categoria">bug</span>
                            </div>
                            <h2 class="text-base sm:text-lg font-black text-slate-800 leading-snug" x-text="chamadoAtual.titulo || chamadoAtual.motivo">Título</h2>
                            <p class="text-[11px] text-slate-500 mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                <span>Solicitado por</span>
                                <strong x-show="!ehTiLiberado()" class="text-slate-800" x-text="chamadoAtual.solicitante">User</strong>
                                <select x-show="ehTiLiberado()"
                                        :value="chamadoAtual.solicitante"
                                        @change="alterarSolicitanteRapido($event.target.value)"
                                        class="px-2 py-0.5 bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 rounded-lg text-[11px] font-black text-indigo-800 focus:outline-none cursor-pointer"
                                        title="Alterar solicitante vinculado (Admin/TI)">
                                    <template x-for="usr in usuariosSolicitantes" :key="`hdr-${usr.username}`">
                                        <option :value="usr.username" x-text="usr.username"></option>
                                    </template>
                                </select>
                                <span>em <span x-text="chamadoAtual.data_fmt">Data</span> ·</span>
                                <span>Origem: <strong class="text-indigo-600" x-text="chamadoAtual.tipo_sistema === 'PORTAL' ? chamadoAtual.modulo_nome : (chamadoAtual.equipamento_detalhe || chamadoAtual.subtipo_item || chamadoAtual.tipo_sistema)">Módulo</strong> ·</span>
                                <span>Responsável: <strong class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200" x-text="chamadoAtual.atendente || 'ALAN'">ALAN</strong></span>
                            </p>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" x-show="usuario && usuario.pode_ver_todos && chamadoAtual.atendente !== usuario.username"
                                    @click="assumirChamado(chamadoAtual.idchamado)"
                                    class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs flex items-center gap-1.5 shadow-sm transition">
                                <i class="fa-solid fa-user-check"></i>
                                <span>Assumir</span>
                            </button>
                            <button type="button" x-show="ehTiLiberado() || Number(chamadoAtual.status) < 3"
                                    @click="editandoChamado ? (editandoChamado = false) : iniciarEdicaoChamado()"
                                    class="px-3 py-1.5 rounded-xl font-black text-xs flex items-center gap-1.5 border transition"
                                    :class="editandoChamado ? 'bg-amber-100 text-amber-800 border-amber-300' : 'bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border-indigo-200'">
                                <i class="fa-solid fa-pen-to-square"></i>
                                <span x-text="editandoChamado ? 'Cancelar Edição' : (ehTiLiberado() ? 'Editar Chamado' : 'Editar Texto / Título')">Editar Chamado</span>
                            </button>
                            <button type="button" @click="modalDetalhesAberto = false" class="text-slate-400 hover:text-slate-700 w-8 h-8 rounded-xl bg-slate-100 hover:bg-slate-200 flex items-center justify-center">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <!-- STEPPER VISUAL DE STATUS (INTERATIVO SOMENTE PARA TI LIBERADO) -->
                    <div class="py-2.5 border-b border-slate-100 bg-slate-50/70 -mx-5 sm:-mx-6 px-5 sm:px-6">
                        <div x-show="ehTiLiberado()" class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] font-black uppercase text-slate-400 mr-1">Estágio:</span>
                                <template x-for="stOpt in [
                                    {v:0, l:'0. Aberto', cls:'bg-amber-500 text-white border-amber-600'},
                                    {v:1, l:'1. Em Análise', cls:'bg-blue-600 text-white border-blue-700'},
                                    {v:2, l:'2. Em Correção', cls:'bg-purple-600 text-white border-purple-700'},
                                    {v:3, l:'3. Concluído', cls:'bg-emerald-600 text-white border-emerald-700'},
                                    {v:4, l:'4. Cancelado', cls:'bg-slate-700 text-white border-slate-800'}
                                ]" :key="stOpt.v">
                                    <button type="button" @click="trocarStatus(stOpt.v)"
                                            class="px-2.5 py-1 rounded-xl text-[11px] font-bold border transition"
                                            :class="Number(chamadoAtual.status) === stOpt.v ? stOpt.cls + ' shadow-xs' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'"
                                            x-text="stOpt.l"></button>
                                </template>
                            </div>

                            <!-- Controles Rápidos de Criticidade e Fila (TI) -->
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <template x-for="cr in [
                                    {v:'critica', l:'🔴 Crítica'},
                                    {v:'alta', l:'🟠 Alta'},
                                    {v:'media', l:'🔵 Média'},
                                    {v:'baixa', l:'🟢 Baixa'}
                                ]" :key="cr.v">
                                    <button type="button" @click="alterarCriticidadeRapida(chamadoAtual, cr.v)"
                                            class="px-2 py-1 rounded-lg text-[10px] font-black border transition"
                                            :class="chamadoAtual.criticidade === cr.v ? badgeCriticidade(cr.v) + ' ring-1 ring-offset-1 ring-indigo-400' : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-100'"
                                            x-text="cr.l"></button>
                                </template>

                                <button type="button" @click="moverParaTopo(chamadoAtual)"
                                        class="px-2.5 py-1 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-800 border border-amber-300 text-[10px] font-black transition"
                                        title="Colocar no topo da prioridade (#1)">
                                    ⚡ Topo #1
                                </button>
                                <div class="flex items-center bg-white border border-slate-200 rounded-lg px-1.5 py-0.5">
                                    <span class="text-[10px] font-bold text-slate-400 mr-1">Pos:</span>
                                    <input type="number" min="1" max="999" :value="chamadoAtual.prioridade || 10"
                                           @change="alterarPrioridadeModal($event.target.value)"
                                           class="w-10 text-xs font-black text-indigo-700 focus:outline-none text-center">
                                </div>
                            </div>
                        </div>

                        <!-- Visualização Somente-Leitura para Solicitante -->
                        <div x-show="!ehTiLiberado()" class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[10px] font-black uppercase text-slate-400 mr-1">Andamento:</span>
                                <template x-for="stOpt in [
                                    {v:0, l:'0. Aberto', cls:'bg-amber-500 text-white border-amber-600'},
                                    {v:1, l:'1. Em Análise', cls:'bg-blue-600 text-white border-blue-700'},
                                    {v:2, l:'2. Em Correção', cls:'bg-purple-600 text-white border-purple-700'},
                                    {v:3, l:'3. Concluído', cls:'bg-emerald-600 text-white border-emerald-700'},
                                    {v:4, l:'4. Cancelado', cls:'bg-slate-700 text-white border-slate-800'}
                                ]" :key="`ro-${stOpt.v}`">
                                    <span class="px-2.5 py-1 rounded-xl text-[11px] font-bold border"
                                          :class="Number(chamadoAtual.status) === stOpt.v ? stOpt.cls + ' shadow-xs' : 'bg-white/70 text-slate-400 border-slate-200'"
                                          x-text="stOpt.l"></span>
                                </template>
                            </div>
                            <span class="text-[10px] font-bold text-slate-500 bg-white px-2.5 py-1 rounded-xl border border-slate-200 flex items-center gap-1.5">
                                <i class="fa-solid fa-lock text-indigo-500"></i>
                                Estágio e prioridade gerenciados pelo TI
                            </span>
                        </div>
                    </div>

                    <!-- CORPO PRINCIPAL DO MODAL COM SCROLL -->
                    <div class="py-4 space-y-4 overflow-y-auto custom-scrollbar flex-1 pr-1">

                        <!-- BARRA DEVOPS TI (EXCLUSIVA TI LIBERADO) -->
                        <div x-show="ehTiLiberado()" class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white p-3 rounded-2xl border border-slate-700/60 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center border border-indigo-500/30">
                                    <i class="fa-solid fa-laptop-code text-xs"></i>
                                </div>
                                <div>
                                    <span class="text-[9px] font-black uppercase tracking-wider text-indigo-300 block">Terminal de Engenharia / DevOps</span>
                                    <span class="text-xs font-bold text-white">Dossiê Técnico para IDE / Análise Rápida</span>
                                </div>
                            </div>
                            <button type="button" @click="copiarBriefingDevOps()"
                                    class="px-3.5 py-1.5 rounded-xl bg-white text-slate-900 hover:bg-slate-100 font-black text-xs flex items-center justify-center gap-1.5 transition">
                                <i class="fa-solid fa-code text-indigo-600"></i>
                                <span>Copiar Dossiê Técnico</span>
                            </button>
                        </div>

                        <!-- MODO EDIÇÃO INLINE DO CHAMADO -->
                        <div x-show="editandoChamado" class="bg-amber-50/70 p-4 rounded-2xl border-2 border-amber-300 space-y-3" style="display: none;">
                            <div class="flex items-center justify-between gap-2 flex-wrap">
                                <span class="text-xs font-black uppercase text-amber-900 flex items-center gap-1.5">
                                    <i class="fa-solid fa-pen-ruler"></i>
                                    <span x-text="ehTiLiberado() ? 'Edição Completa do Chamado (TI)' : 'Editar Título & Descrição do Chamado'">Edição do Chamado</span>
                                </span>
                                <span class="text-[10px] font-semibold text-amber-700"
                                      x-text="ehTiLiberado() ? 'Altere título, texto, criticidade, sistema ou atendente e clique em Salvar' : 'Atualize título, relato ou anexe novos prints (prioridade e estágio ficam com o TI)'"></span>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Título do Chamado</label>
                                    <input type="text" x-model="editForm.titulo"
                                           class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-indigo-500">
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Sistema</label>
                                    <select x-model="editForm.tipo_sistema" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-700">
                                        <template x-for="s in sistemas" :key="s.id">
                                            <option :value="s.id" x-text="s.nome"></option>
                                        </template>
                                    </select>
                                </div>

                                <div x-show="ehTiLiberado() && editForm.tipo_sistema === 'PORTAL'">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Módulo Web</label>
                                    <select x-model="editForm.modulo_slug" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-700">
                                        <option value="geral">Portal Geral</option>
                                        <template x-for="m in modulos" :key="m.slug">
                                            <option :value="m.slug" x-text="`${m.nome} (${m.setor})`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div x-show="ehTiLiberado() && editForm.tipo_sistema !== 'PORTAL'">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Detalhe / Subtipo / Equipamento</label>
                                    <input type="text" x-model="editForm.equipamento_detalhe" placeholder="Detalhe..."
                                           class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-semibold text-slate-700">
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Categoria</label>
                                    <select x-model="editForm.categoria" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-700">
                                        <option value="bug">Bug / Erro</option>
                                        <option value="melhoria">Melhoria / Ajuste</option>
                                        <option value="duvida">Dúvida / Orientação</option>
                                        <option value="permissao">Permissão / Acesso</option>
                                        <option value="equipamento">Equipamento / Infra</option>
                                    </select>
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Responsável Técnico (Atendente)</label>
                                    <select x-model="editForm.atendente" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-700">
                                        <template x-for="at in atendentes" :key="at">
                                            <option :value="at" x-text="at"></option>
                                        </template>
                                    </select>
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-indigo-700 uppercase block mb-1">Solicitante (Usuário Vinculado)</label>
                                    <select x-model="editForm.solicitante" class="w-full px-3 py-2 bg-white border border-indigo-300 rounded-xl text-xs font-bold text-indigo-900">
                                        <template x-for="usr in usuariosSolicitantes" :key="`ed-${usr.username}`">
                                            <option :value="usr.username" x-text="`${usr.username}${usr.nome && usr.nome !== usr.username ? ' (' + usr.nome + ')' : ''}`"></option>
                                        </template>
                                    </select>
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Criticidade</label>
                                    <select x-model="editForm.criticidade" class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-700">
                                        <option value="critica">🔴 Crítica</option>
                                        <option value="alta">🟠 Alta</option>
                                        <option value="media">🔵 Média</option>
                                        <option value="baixa">🟢 Baixa</option>
                                    </select>
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Prioridade Numérica (1 = Topo)</label>
                                    <input type="number" min="1" max="999" x-model.number="editForm.prioridade"
                                           class="w-full px-3 py-2 bg-white border border-amber-200 rounded-xl text-xs font-bold text-slate-800">
                                </div>

                                <div x-show="ehTiLiberado()">
                                    <label class="text-[10px] font-bold text-indigo-700 uppercase block mb-1">Prazo de Entrega (Data SLA)</label>
                                    <input type="date" x-model="editForm.prazo_entrega"
                                           class="w-full px-3 py-2 bg-white border border-indigo-300 rounded-xl text-xs font-bold text-indigo-900">
                                </div>
                            </div>

                            <div>
                                <label class="text-[10px] font-bold text-slate-500 uppercase block mb-1">Texto Completo da Solicitação / Relato</label>
                                <textarea x-model="editForm.motivo" rows="4"
                                          class="w-full p-3 bg-white border border-amber-200 rounded-xl text-xs font-medium text-slate-800 focus:outline-none focus:border-indigo-500"></textarea>
                            </div>

                            <!-- Adicionar Complemento Rápido ao Texto -->
                            <div class="flex items-center gap-2 bg-white p-2 rounded-xl border border-amber-200">
                                <input type="text" x-model="complementoTexto" @keydown.enter.prevent="adicionarComplementoAoTexto()"
                                       placeholder="Escreva uma informação extra para acrescentar ao texto acima..."
                                       class="flex-1 text-xs font-medium text-slate-700 px-2 focus:outline-none">
                                <button type="button" @click="adicionarComplementoAoTexto()"
                                        class="px-3 py-1.5 rounded-lg bg-amber-100 hover:bg-amber-200 text-amber-900 font-bold text-[11px]">
                                    + Acrescentar ao Texto
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                                <button type="button" @click="$refs.modalDiretoAnexoInput.click()"
                                        class="px-3 py-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 text-xs font-bold flex items-center gap-1.5 transition">
                                    <i class="fa-solid fa-camera"></i>
                                    <span>+ Anexar Novo Print / Arquivo</span>
                                </button>
                                <div class="flex items-center gap-2">
                                    <button type="button" @click="editandoChamado = false" class="px-4 py-2 rounded-xl bg-white border border-slate-200 text-slate-600 text-xs font-bold">Cancelar</button>
                                    <button type="button" @click="salvarEdicaoChamado()" :disabled="salvandoEdicao"
                                            class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black flex items-center gap-1.5 shadow-sm">
                                        <i class="fa-solid" :class="salvandoEdicao ? 'fa-spinner fa-spin' : 'fa-floppy-disk'"></i>
                                        <span>Salvar Alterações</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- RELATO ORIGINAL (MODO LEITURA) -->
                        <div x-show="!editandoChamado" class="bg-slate-50 p-4 rounded-2xl border border-slate-200">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Descrição / Solicitação</span>
                                <button type="button" x-show="ehTiLiberado() || Number(chamadoAtual.status) < 3"
                                        @click="iniciarEdicaoChamado()"
                                        class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                    <i class="fa-solid fa-pen"></i>
                                    <span x-text="ehTiLiberado() ? 'Editar texto / dados' : 'Editar título / descrição'">Editar texto</span>
                                </button>
                            </div>
                            <p class="text-xs text-slate-700 whitespace-pre-wrap font-medium leading-relaxed" x-text="chamadoAtual.motivo || chamadoAtual.observacao">Descrição</p>
                        </div>

                        <!-- GALERIA UNIFICADA DE PRINTS & ANEXOS + UPLOAD DIRETO -->
                        <div class="bg-slate-50/90 p-4 rounded-2xl border border-indigo-100 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <span class="text-[11px] font-black uppercase text-indigo-900 flex items-center gap-1.5">
                                        <i class="fa-solid fa-images text-indigo-600"></i>
                                        <span>Prints & Anexos do Chamado (<span x-text="anexos.length">0</span>)</span>
                                    </span>
                                    <span class="text-[10px] text-slate-400 font-medium">Pressione <strong>Ctrl+V</strong> em qualquer parte deste modal para anexar um novo print na hora!</span>
                                </div>

                                <div class="flex items-center gap-2">
                                    <input type="file" x-ref="modalDiretoAnexoInput" @change="selecionarArquivoAnexo($event, 'modal_anexo')" accept="image/*,.pdf,.txt,.log" class="hidden">
                                    <button type="button" @click="$refs.modalDiretoAnexoInput.click()"
                                            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold flex items-center gap-1.5 transition shadow-2xs">
                                        <i class="fa-solid" :class="anexoUploadando ? 'fa-spinner fa-spin' : 'fa-camera'"></i>
                                        <span>+ Anexar Novo Print / Arquivo</span>
                                    </button>
                                </div>
                            </div>

                            <div x-show="anexos.length === 0" class="text-center py-4 text-xs text-slate-400 border border-dashed border-slate-200 rounded-xl">
                                Nenhum print ou arquivo anexado ainda. Cole com Ctrl+V ou clique acima para anexar.
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" x-show="anexos.length > 0">
                                <template x-for="(ax, idxAx) in anexos" :key="`${ax.origem}-${ax.idoperacao}-${idxAx}`">
                                    <div class="bg-white rounded-xl border border-slate-200 p-2 flex flex-col justify-between shadow-2xs group">
                                        <template x-if="ehImagem(ax.url)">
                                            <img :src="ax.url" @click="abrirLightbox(ax.url, `Print #${chamadoAtual.idchamado} (${ax.autor})`)"
                                                 class="w-full h-24 object-cover rounded-lg cursor-pointer hover:opacity-90 transition mb-1.5">
                                        </template>
                                        <template x-if="!ehImagem(ax.url)">
                                            <a :href="ax.url" target="_blank" download class="w-full h-24 rounded-lg bg-indigo-50 text-indigo-700 flex flex-col items-center justify-center gap-1 no-underline hover:bg-indigo-100 mb-1.5">
                                                <i class="fa-solid fa-file-arrow-down text-xl"></i>
                                                <span class="text-[10px] font-bold">Baixar Arquivo</span>
                                            </a>
                                        </template>
                                        <div class="flex items-center justify-between text-[10px] text-slate-500 px-0.5">
                                            <span class="font-bold truncate max-w-[80px]" x-text="ax.autor">Autor</span>
                                            <span class="text-[9px] text-slate-400" x-text="ax.origem === 'abertura' ? 'Abertura' : 'Anexo'">Origem</span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- DOSSIÊ TÉCNICO IA -->
                        <div x-show="chamadoAtual.diagnostico_ia || ehTiLiberado()" class="bg-slate-900 text-slate-100 p-4 rounded-2xl border border-slate-800">
                            <div class="flex items-center justify-between gap-2 mb-2 flex-wrap">
                                <span class="text-[11px] font-black uppercase text-indigo-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-microchip"></i> Parecer Técnico de Engenharia &amp; Prévia da Solução
                                </span>
                                <button type="button" x-show="ehTiLiberado() || Number(chamadoAtual.status) < 3"
                                        @click="reprocessarIaChamado()" :disabled="reprocessandoIa"
                                        class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-[10px] font-black flex items-center gap-1.5 transition">
                                    <i class="fa-solid fa-wand-magic-sparkles" :class="reprocessandoIa ? 'fa-spin' : ''"></i>
                                    <span x-text="reprocessandoIa ? 'Reprocessando...' : '✨ Reprocessar Diagnóstico IA'">✨ Reprocessar Diagnóstico IA</span>
                                </button>
                            </div>
                            <pre x-show="chamadoAtual.diagnostico_ia" class="text-[10.5px] font-mono leading-relaxed whitespace-pre-wrap text-slate-200 overflow-x-auto max-h-60" x-text="chamadoAtual.diagnostico_ia"></pre>
                            <p x-show="!chamadoAtual.diagnostico_ia" class="text-[11px] text-slate-400 italic">Sem parecer técnico gerado ainda. Clique no botão acima para analisar com o Token de Sabedoria.</p>
                        </div>

                        <!-- SOLUÇÃO APLICADA (SE CONCLUÍDO) -->
                        <div x-show="chamadoAtual.status == 3" class="bg-emerald-50 p-4 rounded-2xl border border-emerald-200">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] font-black uppercase text-emerald-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check"></i> Resolução Técnica Aplicada
                                </span>
                                <span class="text-[10px] font-bold text-emerald-700" x-text="`Versão: ${chamadoAtual.versao_entrega || 'v3.5'}`">v3.5</span>
                            </div>
                            <p class="text-xs text-emerald-900 font-medium whitespace-pre-wrap mt-1" x-text="chamadoAtual.solucao_tecnica || 'Solução registrada com sucesso.'"></p>
                        </div>

                        <!-- TIMELINE / HISTÓRICO COM FILTRO -->
                        <div>
                            <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5">
                                <span class="text-[11px] font-black text-slate-500 uppercase tracking-wider">Linha do Tempo & Comentários</span>
                                <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200 text-[10px] font-bold">
                                    <button type="button" @click="timelineFiltro = 'todos'" class="px-2.5 py-1 rounded-lg transition"
                                            :class="timelineFiltro === 'todos' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'">Tudo</button>
                                    <button type="button" @click="timelineFiltro = 'comentarios'" class="px-2.5 py-1 rounded-lg transition"
                                            :class="timelineFiltro === 'comentarios' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'">Comentários & Prints</button>
                                    <button type="button" @click="timelineFiltro = 'mudancas'" class="px-2.5 py-1 rounded-lg transition"
                                            :class="timelineFiltro === 'mudancas' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'">Status & Edições</button>
                                </div>
                            </div>

                            <div class="space-y-2.5">
                                <template x-for="op in timelineFiltrada()" :key="op.idoperacao">
                                    <div class="flex items-start gap-2.5 text-xs p-3 rounded-xl border transition"
                                         :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'bg-indigo-50/70 border-indigo-200' : 'bg-slate-50 border-slate-200/70'">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs mt-0.5"
                                             :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-700'">
                                            <i class="fa-solid fa-laptop-code" x-show="op.usuario === 'MARCON' || op.usuario === 'ALAN'"></i>
                                            <i class="fa-solid fa-comment" x-show="op.usuario !== 'MARCON' && op.usuario !== 'ALAN'"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center justify-between text-[11px] mb-0.5">
                                                <div class="flex items-center gap-1.5">
                                                    <strong class="text-slate-800" x-text="op.usuario">User</strong>
                                                    <span x-show="op.usuario === 'MARCON' || op.usuario === 'ALAN'" class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-indigo-200 text-indigo-900">TI / Suporte</span>
                                                    <span class="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded bg-white border border-slate-200 text-slate-500" x-text="op.tipo">tipo</span>
                                                </div>
                                                <span class="text-slate-400 text-[10px]" x-text="op.data_fmt">Data</span>
                                            </div>
                                            <div class="font-bold text-slate-700" x-text="op.descricao">Ação</div>
                                            <div x-show="op.detalhes" class="text-[11px] mt-1 whitespace-pre-wrap leading-relaxed p-2 rounded-lg bg-white/80 border border-slate-200/60 text-slate-700" x-text="op.detalhes"></div>

                                            <div x-show="op.anexo_url" class="mt-2">
                                                <template x-if="ehImagem(op.anexo_url)">
                                                    <img :src="op.anexo_url" class="max-w-[200px] max-h-[130px] rounded-lg border border-slate-200 cursor-pointer hover:opacity-90"
                                                         @click="abrirLightbox(op.anexo_url, `Anexo de ${op.usuario}`)">
                                                </template>
                                                <template x-if="!ehImagem(op.anexo_url)">
                                                    <a :href="op.anexo_url" target="_blank" download class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-[10px] font-bold border border-indigo-200 no-underline">
                                                        <i class="fa-solid fa-file-arrow-down"></i> Baixar Anexo
                                                    </a>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- FORMULÁRIO DE NOVO COMENTÁRIO / RESOLUÇÃO -->
                        <div @paste.stop="tratarColagemPrint($event, 'interacao')" class="bg-indigo-50/60 p-4 rounded-2xl border border-indigo-200/80 space-y-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[11px] font-black uppercase text-indigo-900"
                                      x-text="ehTiLiberado() ? 'Adicionar Comentário / Solução / Evidência' : 'Adicionar Comentário ou Novo Print (Ctrl+V)'">Adicionar Comentário</span>
                                <select x-show="ehTiLiberado()" x-model="novaInteracao.tipo" class="px-2.5 py-1 bg-white border border-indigo-200 rounded-lg text-[11px] font-bold text-slate-700">
                                    <option value="comentario">💬 Comentário Geral</option>
                                    <option value="progresso">🛠️ Progresso Técnico</option>
                                    <option value="evidencia">📸 Evidência / Print</option>
                                    <option value="duvida">⚠️ Dúvida / Retorno</option>
                                </select>
                            </div>

                            <textarea x-model="novaInteracao.mensagem" rows="2" placeholder="Escreva um comentário, atualização técnica ou cole um print (Ctrl+V)..."
                                      class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-500"></textarea>

                            <div x-show="novaInteracao.anexo_url" class="bg-white border border-indigo-200 rounded-xl p-2 flex items-center justify-between gap-2">
                                <div class="flex items-center gap-2 overflow-hidden">
                                    <template x-if="ehImagem(novaInteracao.anexo_url)">
                                        <img :src="novaInteracao.anexo_url" class="w-10 h-10 object-cover rounded-lg border border-indigo-200 cursor-pointer"
                                             @click="abrirLightbox(novaInteracao.anexo_url, 'Print da Interação')">
                                    </template>
                                    <span class="text-[11px] font-bold text-slate-700 truncate">Print / arquivo pronto para envio</span>
                                </div>
                                <button type="button" @click="removerAnexo('interacao')" class="text-rose-500 hover:text-rose-700 text-xs px-2 py-1 font-bold">
                                    <i class="fa-solid fa-trash-can"></i> Remover
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <input type="file" x-ref="interacaoAnexoInput" @change="selecionarArquivoAnexo($event, 'interacao')" accept="image/*,.pdf,.txt,.log" class="hidden">
                                    <button type="button" @click="$refs.interacaoAnexoInput.click()"
                                            class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-700 font-bold text-xs flex items-center gap-1.5">
                                        <i class="fa-solid fa-paperclip text-slate-400"></i>
                                        <span>Anexar Print</span>
                                    </button>

                                    <label x-show="ehTiLiberado()" class="text-xs text-slate-700 font-bold flex items-center gap-1.5 cursor-pointer ml-1">
                                        <input type="checkbox" x-model="novaInteracao.eh_resolucao" class="rounded text-indigo-600 focus:ring-0">
                                        <span>Marcar como Concluído</span>
                                    </label>
                                    <input type="text" x-show="ehTiLiberado() && novaInteracao.eh_resolucao" x-model="novaInteracao.versao_entrega" placeholder="Versão (ex: v3.5)"
                                           class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 w-24">
                                </div>

                                <button type="button" @click="enviarInteracao()" :disabled="!novaInteracao.mensagem && !novaInteracao.anexo_url"
                                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-black text-xs flex items-center gap-1.5 transition shadow-xs">
                                    <i class="fa-solid fa-paper-plane"></i>
                                    <span x-text="ehTiLiberado() ? 'Registrar Comentário' : 'Enviar Comentário'">Registrar Comentário</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- MODAL 3: LIGHTBOX AMPLIAÇÃO DE PRINTS/IMAGENS (Z-[80]) -->
    <div x-show="lightboxAberto" class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6 bg-slate-950/85 backdrop-blur-md" style="display: none;"
         @keydown.escape.window="fecharLightbox()">
        <div class="relative max-w-5xl w-full flex flex-col items-center max-h-[95vh]" @click.outside="fecharLightbox()">
            <div class="w-full flex items-center justify-between text-white pb-3 px-2">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-image text-indigo-400"></i>
                    <span class="text-xs sm:text-sm font-bold" x-text="lightboxTitulo">Anexo Ampliado</span>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="lightboxUrl" target="_blank" download class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-1.5 no-underline">
                        <i class="fa-solid fa-download"></i> Baixar Original
                    </a>
                    <button type="button" @click="fecharLightbox()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </div>

            <div class="bg-slate-900/60 rounded-2xl p-2 border border-white/10 shadow-2xl overflow-auto max-h-[85vh] flex items-center justify-center w-full">
                <img :src="lightboxUrl" class="max-h-[80vh] w-auto max-w-full rounded-xl object-contain shadow-2xl">
            </div>
        </div>
    </div>

</div>

<!-- SCRIPT DO MÓDULO -->
<script src="<?= $appBase ?>/portal/assets/js/<?= $moduleJs ?>?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
