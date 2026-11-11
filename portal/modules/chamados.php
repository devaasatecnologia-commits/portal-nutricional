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
  .kanban-col { min-height: 520px; max-height: calc(100vh - 360px); overflow-y: auto; }
  .kanban-card { transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
  .kanban-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08); }
  .pulse-ia { animation: pulseIa 2s infinite; }
  @keyframes pulseIa { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.6; transform: scale(1.15); } }
  .custom-scrollbar::-webkit-scrollbar { width: 5px; height: 5px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
  .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="chm-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-12" x-data="chamadosApp()" x-init="init()">

    <!-- HERO HEADER -->
    <div class="chm-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5 border border-indigo-900/50">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white no-underline transition shadow-sm border border-white/10" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt text-amber-300"></i> Service Desk Inteligente
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-ia"></span> IA Triagem Ativa
                        </span>
                        <span x-show="usuario && usuario.pode_ver_todos" class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-purple-500/30 text-purple-200 border border-purple-400/30 flex items-center gap-1.5">
                            <i class="fa-solid fa-crown text-amber-300"></i> Painel de Solução (TI)
                        </span>
                        <span x-show="usuario && !usuario.pode_ver_todos" class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-blue-500/30 text-blue-200 border border-blue-400/30 flex items-center gap-1.5" style="display: none;">
                            <i class="fa-solid fa-user-lock"></i> Visão Solicitante
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1 flex items-center gap-2">
                        <span x-text="usuario && !usuario.pode_ver_todos ? 'Central de Atendimento & Meus Chamados' : 'Central de Solução & Service Desk'">Central de Chamados & Suporte</span>
                    </h1>
                    <p class="text-xs sm:text-sm text-indigo-200 opacity-90 mt-0.5">
                        <span x-text="usuario && !usuario.pode_ver_todos ? 'Abra novos chamados de qualquer módulo liberado e acompanhe a resolução em tempo real com o responsável técnico (ALAN).' : 'Fila unificada de incidentes corporativos, triagem de IA e solução técnica integrada ao ERP.'">Governança, triagem assistida por inteligência artificial e resolução ágil dos módulos corporativos</span>
                    </p>
                </div>
            </div>

            <!-- BOTÕES DE AÇÃO DO TOPO -->
            <div class="flex flex-wrap items-center gap-2.5">
                <button @click="abrirModalNovo()" class="px-5 py-2.5 rounded-2xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 text-xs font-black flex items-center gap-2 transition shadow-lg shadow-amber-500/25 active:scale-95">
                    <i class="fa-solid fa-plus-circle text-base"></i>
                    <span>Abrir Novo Chamado</span>
                </button>

                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=chamados" class="px-4 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold flex items-center gap-2 transition no-underline shadow-sm border border-white/15">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Treinamento</span>
                </a>
            </div>
        </div>
    </div>

    <!-- BANNER CONVIDATIVO DE ABERTURA PARA USUÁRIO SOLICITANTE -->
    <div x-show="usuario && !usuario.pode_ver_todos" class="bg-gradient-to-r from-indigo-50/80 via-white to-amber-50/40 rounded-3xl p-5 border border-indigo-100 shadow-sm mb-5 flex flex-col md:flex-row items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-xl shadow-md shadow-indigo-600/20 shrink-0">
                <i class="fa-solid fa-headset"></i>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-indigo-600 flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-check"></i> Suporte Direto & Resolução Rápida
                </span>
                <h3 class="text-sm sm:text-base font-black text-slate-800 mt-0.5">
                    Precisa de ajuda ou encontrou alguma dúvida no sistema?
                </h3>
                <p class="text-xs text-slate-500 font-medium mt-0.5 leading-relaxed">
                    Ao abrir o chamado, ele cairá automaticamente na fila do responsável técnico (<strong>ALAN</strong>) para triagem e resolução prioritária.
                </p>
            </div>
        </div>
        <button type="button" @click="abrirModalNovo()"
                class="w-full md:w-auto px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs flex items-center justify-center gap-2 transition shadow-md shadow-indigo-600/20 shrink-0">
            <i class="fa-solid fa-paper-plane"></i>
            <span>Criar Solicitação Agora</span>
        </button>
    </div>

    <!-- 4 KPI CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <!-- KPI 1: Total Geral -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5"
                      x-text="usuario && !usuario.pode_ver_todos ? 'Minhas Demandas' : 'Total Registrado'">Total Registrado</span>
                <span class="text-2xl font-black text-slate-800" x-text="kpis.total || 0">0</span>
                <span class="text-[10px] text-slate-400 font-semibold block mt-0.5"
                      x-text="usuario && !usuario.pode_ver_todos ? 'Histórico de suas solicitações' : 'Histórico integrado ao ERP'">Histórico integrado ao ERP</span>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-ticket"></i>
            </div>
        </div>

        <!-- KPI 2: Abertos & Em Análise -->
        <div class="bg-white rounded-2xl p-4 border border-amber-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider block mb-0.5"
                      x-text="usuario && !usuario.pode_ver_todos ? 'Aguardando Suporte' : 'Abertos / Fila'">Abertos / Fila</span>
                <span class="text-2xl font-black text-amber-600" x-text="kpis.abertos || 0">0</span>
                <span class="text-[10px] text-amber-700/70 font-semibold block mt-0.5"
                      x-text="usuario && !usuario.pode_ver_todos ? 'Na fila do técnico (ALAN)' : 'Aguardando atendimento'">Aguardando atendimento</span>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-clock"></i>
            </div>
        </div>

        <!-- KPI 3: Em Correção -->
        <div class="bg-white rounded-2xl p-4 border border-indigo-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-indigo-600 uppercase tracking-wider block mb-0.5">Em Correção / Dev</span>
                <span class="text-2xl font-black text-indigo-600" x-text="kpis.em_correcao || 0">0</span>
                <span class="text-[10px] text-indigo-700/70 font-semibold block mt-0.5">Hotfix sendo aplicado</span>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-code"></i>
            </div>
        </div>

        <!-- KPI 4: Críticos -->
        <div class="bg-white rounded-2xl p-4 border border-rose-100 shadow-sm flex items-center justify-between"
             :class="kpis.criticos > 0 ? 'bg-rose-50/50 border-rose-300' : ''">
            <div>
                <span class="text-[11px] font-bold text-rose-600 uppercase tracking-wider block mb-0.5"
                      x-text="usuario && !usuario.pode_ver_todos ? 'Demandas Críticas' : 'Críticos em Aberto'">Críticos em Aberto</span>
                <span class="text-2xl font-black text-rose-600" x-text="kpis.criticos || 0">0</span>
                <span class="text-[10px] text-rose-600/70 font-semibold block mt-0.5" x-text="kpis.criticos > 0 ? 'Exigem ação prioritária!' : 'Nenhum bloqueio crítico'">Nenhum bloqueio</span>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-lg"
                 :class="kpis.criticos > 0 ? 'animate-bounce text-rose-700' : ''">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
        </div>
    </div>

    <!-- BARRA DE CONTROLE: FILTROS + VISUALIZAÇÃO -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-5 space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Busca Textual -->
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" x-model="filtros.busca" @input.debounce.300ms="carregarChamados()" placeholder="Buscar por número, título, motivo ou solicitante..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Filtros em Selects -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Filtro Atendente (Exclusivo para Administrador / TI ALAN) -->
                <div x-show="usuario && usuario.pode_ver_todos" class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200">
                    <button type="button" @click="filtroAtendente = 'todos'; carregarChamados()"
                            class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition"
                            :class="filtroAtendente === 'todos' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Todos
                    </button>
                    <button type="button" @click="filtroAtendente = 'mim'; carregarChamados()"
                            class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition flex items-center gap-1.5"
                            :class="filtroAtendente === 'mim' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        <i class="fa-solid fa-user-check text-emerald-400"></i>
                        <span>Atribuídos a Mim (ALAN)</span>
                    </button>
                    <button type="button" @click="filtroAtendente = 'sem_atendente'; carregarChamados()"
                            class="px-2.5 py-1.5 rounded-lg font-bold text-xs transition"
                            :class="filtroAtendente === 'sem_atendente' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Sem Atendente
                    </button>
                </div>

                <!-- Filtro Sistema -->
                <select x-model="filtros.tipo_sistema" @change="carregarChamados()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                    <option value="todos">Todos os Sistemas</option>
                    <template x-for="s in sistemas" :key="s.id">
                        <option :value="s.id" x-text="s.nome"></option>
                    </template>
                </select>

                <!-- Filtro Módulo (Relevante quando sistema for Portal ou todos) -->
                <select x-model="filtros.modulo" @change="carregarChamados()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500"
                        x-show="filtros.tipo_sistema === 'todos' || filtros.tipo_sistema === 'PORTAL'">
                    <option value="todos">Todos os Módulos Web</option>
                    <template x-for="m in modulos" :key="m.slug">
                        <option :value="m.slug" x-text="`${m.nome} (${m.setor})`"></option>
                    </template>
                </select>

                <!-- Filtro Criticidade -->
                <select x-model="filtros.criticidade" @change="carregarChamados()" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                    <option value="todas">Todas Criticidades</option>
                    <option value="critica">🔴 Crítica</option>
                    <option value="alta">🟡 Alta</option>
                    <option value="media">🔵 Média</option>
                    <option value="baixa">🟢 Baixa</option>
                </select>

                <!-- Alternador Kanban / Lista / Dashboard (Somente para Atendentes / Admin) -->
                <div x-show="usuario && usuario.pode_ver_todos" class="bg-slate-100 p-0.5 rounded-xl border border-slate-200 flex items-center">
                    <button @click="modoExibicao = 'kanban'" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition"
                            :class="modoExibicao === 'kanban' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                        <i class="fa-solid fa-table-columns"></i>
                        <span>Kanban</span>
                    </button>
                    <button @click="modoExibicao = 'lista'" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition"
                            :class="modoExibicao === 'lista' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                        <i class="fa-solid fa-list"></i>
                        <span>Lista</span>
                    </button>
                    <button @click="modoExibicao = 'dashboard'" class="px-3 py-1.5 rounded-lg text-xs font-bold flex items-center gap-1.5 transition"
                            :class="modoExibicao === 'dashboard' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-800'">
                        <i class="fa-solid fa-chart-pie"></i>
                        <span>Painel & KPIs</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- LINHA DE FILTRO DE PERÍODO COMPLETO (HOJE, 7 DIAS, MÊS, DATAS LIVRES) -->
        <div class="pt-2.5 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                    <i class="fa-regular fa-calendar text-slate-500"></i> Período:
                </span>

                <!-- Atalhos rápidos -->
                <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-xl border border-slate-200">
                    <button type="button" @click="definirPeriodo('hoje')"
                            class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === 'hoje' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Hoje
                    </button>
                    <button type="button" @click="definirPeriodo('7dias')"
                            class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === '7dias' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        7 Dias
                    </button>
                    <button type="button" @click="definirPeriodo('mes')"
                            class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === 'mes' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Este Mês
                    </button>
                    <button type="button" @click="definirPeriodo('todos')"
                            class="px-2.5 py-1 rounded-lg font-bold text-[11px] transition"
                            :class="filtroPeriodoAtivo === 'todos' ? 'bg-indigo-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'">
                        Todos
                    </button>
                </div>
            </div>

            <!-- Inputs de Data De / Até -->
            <div class="flex items-center gap-2 flex-wrap">
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">De:</span>
                    <input type="date" x-model="filtros.data_inicio" @change="filtroPeriodoAtivo = 'custom'; carregarChamados()"
                           class="bg-transparent border-0 text-xs font-semibold text-slate-700 focus:outline-none">
                </div>
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1">
                    <span class="text-[10px] font-bold text-slate-400 uppercase">Até:</span>
                    <input type="date" x-model="filtros.data_fim" @change="filtroPeriodoAtivo = 'custom'; carregarChamados()"
                           class="bg-transparent border-0 text-xs font-semibold text-slate-700 focus:outline-none">
                </div>
                <button type="button" x-show="filtros.data_inicio || filtros.data_fim" @click="definirPeriodo('todos')"
                        class="text-[11px] font-bold text-rose-500 hover:text-rose-700 px-2 py-1 rounded-lg hover:bg-rose-50 transition" title="Limpar filtro de data">
                    <i class="fa-solid fa-xmark"></i> Limpar
                </button>
            </div>
        </div>
    </div>

    <!-- VISÃO EXCLUSIVA: MEUS CHAMADOS (USUÁRIO SOLICITANTE) -->
    <div x-show="usuario && !usuario.pode_ver_todos" class="space-y-4 mb-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-700 uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-list-check text-indigo-600"></i> Minhas Solicitações Registradas
            </h3>
            <span class="text-xs font-bold text-slate-500" x-text="`${chamados.length} chamado(s) encontrado(s)`">0 chamados</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" x-show="chamados.length > 0">
            <template x-for="c in chamados" :key="c.idchamado">
                <div class="bg-white rounded-2xl p-5 border border-slate-200/90 shadow-xs hover:shadow-md hover:border-indigo-300 transition-all cursor-pointer flex flex-col justify-between"
                     @click="abrirDetalhes(c.idchamado)">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span class="text-xs font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-lg border border-indigo-100" x-text="`#${c.idchamado}`">#0</span>
                            <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full border" :class="c.status_info.cor" x-text="c.status_info.label">Aberto</span>
                        </div>

                        <h4 class="text-sm font-black text-slate-800 line-clamp-2 mb-2 leading-snug" x-text="c.titulo || c.motivo">Título</h4>
                        <p class="text-xs text-slate-500 line-clamp-2 mb-3 font-medium leading-relaxed" x-text="c.motivo">Relato</p>

                        <div class="flex items-center gap-2 flex-wrap mb-3">
                            <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded border" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            <span class="text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded" :class="badgeCriticidade(c.criticidade)" x-text="c.criticidade">Média</span>
                        </div>
                    </div>

                    <div>
                        <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full" :class="c.status == 3 ? 'bg-emerald-500' : 'bg-amber-400'"></span>
                                <span class="font-semibold text-slate-600">
                                    Técnico: <strong class="text-indigo-700" x-text="c.atendente || 'ALAN'">ALAN</strong>
                                </span>
                            </div>
                            <span class="font-medium" x-text="c.data_fmt">11/11/2026</span>
                        </div>

                        <div class="mt-3 pt-1">
                            <button type="button" class="w-full py-2 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2">
                                <i class="fa-regular fa-comments"></i>
                                <span>Acompanhar Andamento</span>
                            </button>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Empty State Solicitante -->
        <div x-show="chamados.length === 0 && !carregando" class="bg-white rounded-3xl p-10 border border-slate-200/80 shadow-xs text-center max-w-lg mx-auto my-6">
            <div class="w-16 h-16 rounded-3xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mx-auto mb-4">
                <i class="fa-solid fa-clipboard-check"></i>
            </div>
            <h4 class="text-base font-black text-slate-800">Você não possui chamados em aberto</h4>
            <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">
                Toda solicitação que você abrir cairá diretamente para a equipe técnica de TI (<strong>ALAN</strong>) e você poderá acompanhar o status em tempo real aqui.
            </p>
            <button type="button" @click="abrirModalNovo()"
                    class="mt-5 px-5 py-2.5 rounded-2xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs inline-flex items-center gap-2 transition shadow-md shadow-indigo-600/20">
                <i class="fa-solid fa-plus-circle"></i>
                <span>Abrir Novo Chamado</span>
            </button>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL: MODO KANBAN (PARA ADMIN / SERVICE DESK) -->
    <div x-show="usuario && usuario.pode_ver_todos && modoExibicao === 'kanban'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- COLUNA 1: ABERTOS (Status 0) -->
        <div class="bg-slate-50/70 rounded-2xl p-3 border border-slate-200/80 flex flex-col">
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Abertos</h3>
                </div>
                <span class="text-xs font-black text-slate-500 px-2 py-0.5 bg-white rounded-lg border border-slate-200 shadow-2xs"
                      x-text="colunaChamados(0).length">0</span>
            </div>
            <div class="kanban-col custom-scrollbar space-y-2.5 flex-1 pr-1">
                <template x-for="c in colunaChamados(0)" :key="c.idchamado">
                    <div class="kanban-card bg-white p-3 rounded-xl border border-slate-200/70 shadow-xs cursor-pointer hover:border-indigo-300"
                         @click="abrirDetalhes(c.idchamado)">
                        <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                            <span class="text-[10px] font-black text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded border border-indigo-100" x-text="`#${c.idchamado}`">#0</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded ml-auto" :class="badgeCriticidade(c.criticidade)" x-text="c.criticidade">Média</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-800 line-clamp-2 mb-1.5" x-text="c.titulo">Título</h4>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1.5 border-t border-slate-100">
                            <span class="truncate max-w-[110px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <div class="flex items-center gap-1">
                                <span class="font-bold text-slate-700 text-[9px] bg-slate-100 px-1 py-0.2 rounded" x-text="c.atendente || 'ALAN'">ALAN</span>
                                <button type="button" x-show="usuario && usuario.pode_ver_todos && c.atendente !== usuario.username"
                                        @click.stop="assumirChamado(c.idchamado)"
                                        class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded hover:bg-emerald-100 border border-emerald-200"
                                        title="Assumir chamado para mim">
                                    Assumir
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="colunaChamados(0).length === 0" class="text-center py-8 text-xs text-slate-400">
                    Nenhum chamado aberto
                </div>
            </div>
        </div>

        <!-- COLUNA 2: EM ANÁLISE (Status 1) -->
        <div class="bg-slate-50/70 rounded-2xl p-3 border border-slate-200/80 flex flex-col">
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Em Análise</h3>
                </div>
                <span class="text-xs font-black text-slate-500 px-2 py-0.5 bg-white rounded-lg border border-slate-200 shadow-2xs"
                      x-text="colunaChamados(1).length">0</span>
            </div>
            <div class="kanban-col custom-scrollbar space-y-2.5 flex-1 pr-1">
                <template x-for="c in colunaChamados(1)" :key="c.idchamado">
                    <div class="kanban-card bg-white p-3 rounded-xl border border-slate-200/70 shadow-xs cursor-pointer hover:border-blue-300"
                         @click="abrirDetalhes(c.idchamado)">
                        <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                            <span class="text-[10px] font-black text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded border border-blue-100" x-text="`#${c.idchamado}`">#0</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded ml-auto" :class="badgeCriticidade(c.criticidade)" x-text="c.criticidade">Média</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-800 line-clamp-2 mb-1.5" x-text="c.titulo">Título</h4>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1.5 border-t border-slate-100">
                            <span class="truncate max-w-[110px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <div class="flex items-center gap-1">
                                <span class="font-bold text-slate-700 text-[9px] bg-slate-100 px-1 py-0.2 rounded" x-text="c.atendente || 'ALAN'">ALAN</span>
                                <button type="button" x-show="usuario && usuario.pode_ver_todos && c.atendente !== usuario.username"
                                        @click.stop="assumirChamado(c.idchamado)"
                                        class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded hover:bg-emerald-100 border border-emerald-200"
                                        title="Assumir chamado para mim">
                                    Assumir
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="colunaChamados(1).length === 0" class="text-center py-8 text-xs text-slate-400">
                    Nenhum em análise
                </div>
            </div>
        </div>

        <!-- COLUNA 3: EM CORREÇÃO (Status 2) -->
        <div class="bg-slate-50/70 rounded-2xl p-3 border border-slate-200/80 flex flex-col">
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Em Correção</h3>
                </div>
                <span class="text-xs font-black text-slate-500 px-2 py-0.5 bg-white rounded-lg border border-slate-200 shadow-2xs"
                      x-text="colunaChamados(2).length">0</span>
            </div>
            <div class="kanban-col custom-scrollbar space-y-2.5 flex-1 pr-1">
                <template x-for="c in colunaChamados(2)" :key="c.idchamado">
                    <div class="kanban-card bg-white p-3 rounded-xl border border-slate-200/70 shadow-xs cursor-pointer hover:border-purple-300"
                         @click="abrirDetalhes(c.idchamado)">
                        <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                            <span class="text-[10px] font-black text-purple-600 bg-purple-50 px-1.5 py-0.5 rounded border border-purple-100" x-text="`#${c.idchamado}`">#0</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded ml-auto" :class="badgeCriticidade(c.criticidade)" x-text="c.criticidade">Média</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-800 line-clamp-2 mb-1.5" x-text="c.titulo">Título</h4>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1.5 border-t border-slate-100">
                            <span class="truncate max-w-[110px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <div class="flex items-center gap-1">
                                <span class="font-bold text-slate-700 text-[9px] bg-slate-100 px-1 py-0.2 rounded" x-text="c.atendente || 'ALAN'">ALAN</span>
                                <button type="button" x-show="usuario && usuario.pode_ver_todos && c.atendente !== usuario.username"
                                        @click.stop="assumirChamado(c.idchamado)"
                                        class="text-[9px] font-bold text-emerald-700 bg-emerald-50 px-1 py-0.2 rounded hover:bg-emerald-100 border border-emerald-200"
                                        title="Assumir chamado para mim">
                                    Assumir
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="colunaChamados(2).length === 0" class="text-center py-8 text-xs text-slate-400">
                    Nenhum em correção
                </div>
            </div>
        </div>

        <!-- COLUNA 4: CONCLUÍDOS (Status 3) -->
        <div class="bg-slate-50/70 rounded-2xl p-3 border border-slate-200/80 flex flex-col">
            <div class="flex items-center justify-between mb-3 px-1">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    <h3 class="text-xs font-black text-slate-700 uppercase tracking-wider">Concluídos</h3>
                </div>
                <span class="text-xs font-black text-slate-500 px-2 py-0.5 bg-white rounded-lg border border-slate-200 shadow-2xs"
                      x-text="colunaChamados(3).length">0</span>
            </div>
            <div class="kanban-col custom-scrollbar space-y-2.5 flex-1 pr-1">
                <template x-for="c in colunaChamados(3)" :key="c.idchamado">
                    <div class="kanban-card bg-white p-3 rounded-xl border border-slate-200/70 shadow-xs cursor-pointer hover:border-emerald-300 opacity-80 hover:opacity-100"
                         @click="abrirDetalhes(c.idchamado)">
                        <div class="flex items-center gap-1.5 mb-1.5 flex-wrap">
                            <span class="text-[10px] font-black text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-100" x-text="`#${c.idchamado}`">#0</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded border" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            <span class="text-[9px] font-black uppercase px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 ml-auto">Resolvido</span>
                        </div>
                        <h4 class="text-xs font-bold text-slate-700 line-clamp-2 mb-1.5" x-text="c.titulo">Título</h4>
                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1.5 border-t border-slate-100">
                            <span class="truncate max-w-[110px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <div class="flex items-center gap-1">
                                <span class="font-bold text-slate-700 text-[9px] bg-slate-100 px-1 py-0.2 rounded" x-text="c.atendente || 'ALAN'">ALAN</span>
                                <span class="text-[9px] text-slate-400" x-text="c.concluido_fmt || c.data_fmt">Data</span>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="colunaChamados(3).length === 0" class="text-center py-8 text-xs text-slate-400">
                    Nenhum concluído nesta lista
                </div>
            </div>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL: MODO LISTA (ADMIN / SERVICE DESK) -->
    <div x-show="usuario && usuario.pode_ver_todos && modoExibicao === 'lista'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                        <th class="py-3 px-4"># ID</th>
                        <th class="py-3 px-4">Sistema / Origem</th>
                        <th class="py-3 px-4">Título / Problema</th>
                        <th class="py-3 px-4">Módulo / Item</th>
                        <th class="py-3 px-4">Categoria</th>
                        <th class="py-3 px-4 text-center">Criticidade</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Solicitante</th>
                        <th class="py-3 px-4">Técnico TI</th>
                        <th class="py-3 px-4">Data Abertura</th>
                        <th class="py-3 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-semibold text-slate-600">
                    <template x-for="c in chamados" :key="c.idchamado">
                        <tr class="hover:bg-slate-50/70 transition cursor-pointer" @click="abrirDetalhes(c.idchamado)">
                            <td class="py-3 px-4 font-black text-indigo-600" x-text="`#${c.idchamado}`">#1</td>
                            <td class="py-3 px-4">
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border inline-block" :class="badgeSistema(c.tipo_sistema)" x-text="c.tipo_sistema">PORTAL</span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-800 block line-clamp-1" x-text="c.titulo">Título</span>
                                <span class="text-[10px] text-slate-400 line-clamp-1 mt-0.5" x-text="c.motivo">Motivo</span>
                            </td>
                            <td class="py-3 px-4 text-slate-700 font-bold" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</td>
                            <td class="py-3 px-4">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-slate-100 text-slate-600" x-text="c.categoria">Bug</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-full" :class="badgeCriticidade(c.criticidade)" x-text="c.criticidade">Média</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full border" :class="c.status_info.cor" x-text="c.status_info.label">Aberto</span>
                            </td>
                            <td class="py-3 px-4 text-slate-500" x-text="c.solicitante">User</td>
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-1.5">
                                    <span class="font-bold text-slate-800" x-text="c.atendente || 'ALAN'">ALAN</span>
                                    <button type="button" x-show="usuario && usuario.pode_ver_todos && c.atendente !== usuario.username"
                                            @click.stop="assumirChamado(c.idchamado)"
                                            class="text-[9px] font-bold text-emerald-700 hover:text-emerald-900 px-1.5 py-0.5 rounded bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition"
                                            title="Assumir este chamado para mim">
                                        Assumir
                                    </button>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-slate-400 text-[11px]" x-text="c.data_fmt">10/11/2026</td>
                            <td class="py-3 px-4 text-center">
                                <button class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-bold text-xs transition">
                                    Ver
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="chamados.length === 0">
                        <td colspan="11" class="py-8 text-center text-xs text-slate-400">
                            Nenhum chamado encontrado com os filtros selecionados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL: MODO DASHBOARD / PAINEL & KPIS EXECUTIVOS -->
    <div x-show="modoExibicao === 'dashboard'" class="space-y-6">
        <!-- KPIS EXECUTIVOS DE TOPO -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- Taxa de Resolução -->
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Taxa de Resolução</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-emerald-600" x-text="`${kpis.taxa_resolucao || 0}%`">0%</span>
                        <span class="text-[11px] font-bold text-slate-400">do total</span>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1" x-text="`${kpis.concluidos_mes || 0} chamados concluídos`">0 concluídos</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>

            <!-- Fila Ativa / Backlog -->
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Fila Ativa de TI</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-indigo-600" x-text="(parseInt(kpis.abertos || 0) + parseInt(kpis.em_correcao || 0))">0</span>
                        <span class="text-[11px] font-bold text-slate-400">em andamento</span>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1" x-text="`${kpis.em_correcao || 0} em análise técnica`">0 em análise</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-bars-progress"></i>
                </div>
            </div>

            <!-- Críticos / Atenção Imediata -->
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
                          x-text="kpis.criticos > 0 ? 'Processos corporativos em risco' : 'Operação 100% normal'">Nenhum bloqueio</span>
                </div>
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl"
                     :class="kpis.criticos > 0 ? 'bg-rose-100 text-rose-600 animate-pulse' : 'bg-slate-100 text-slate-500'">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
            </div>

            <!-- Total Histórico -->
            <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Total de Incidentes</span>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-black text-slate-800" x-text="kpis.total || 0">0</span>
                        <span class="text-[11px] font-bold text-slate-400">registrados</span>
                    </div>
                    <span class="text-[10px] text-slate-500 font-semibold block mt-1">Base de conhecimento ativa</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 2: GRÁFICOS & DISTRIBUIÇÕES -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Card 1: Distribuição por Sistema Corporativo -->
            <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-network-wired"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black uppercase text-slate-800">Demandas por Sistema Corporativo</h3>
                                <p class="text-[10px] text-slate-400 font-medium">Divisão do volume de chamados por ecossistema</p>
                            </div>
                        </div>
                        <span class="text-xs font-black text-slate-400" x-text="`${(metricas.por_sistema || []).length} sistemas`">0 sistemas</span>
                    </div>

                    <div class="space-y-3.5">
                        <template x-for="sis in (metricas.por_sistema || [])" :key="sis.sistema">
                            <div>
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border inline-block"
                                              :class="badgeSistema(sis.sistema)" x-text="sis.sistema">PORTAL</span>
                                        <span class="font-bold text-slate-700" x-text="nomeSistema(sis.sistema)">Nome</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-black text-slate-800" x-text="sis.total">0</span>
                                        <span class="text-[10px] font-semibold text-slate-400"
                                              x-text="`(${Math.round((parseInt(sis.total) / (parseInt(kpis.total) || 1)) * 100)}%)`">(0%)</span>
                                    </div>
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

            <!-- Card 2: Matriz de Criticidade & Top Módulos -->
            <div class="space-y-6">
                <!-- Matriz de Criticidade -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black uppercase text-slate-800">Matriz de Criticidade & SLA</h3>
                                <p class="text-[10px] text-slate-400 font-medium">Severidade e priorização de atendimento</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <template x-for="crit in (metricas.por_criticidade || [])" :key="crit.criticidade">
                            <div class="p-3 rounded-xl border text-center transition"
                                 :class="badgeCriticidade(crit.criticidade)">
                                <span class="text-[10px] font-black uppercase tracking-wider block mb-0.5" x-text="crit.criticidade">Média</span>
                                <span class="text-2xl font-black block" x-text="crit.total">0</span>
                                <span class="text-[10px] font-semibold opacity-75"
                                      x-text="`${Math.round((parseInt(crit.total) / (parseInt(kpis.total) || 1)) * 100)}% do volume`">0%</span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Top Módulos Mais Demandados -->
                <div class="bg-white rounded-2xl p-5 border border-slate-100 shadow-sm">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-3">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm">
                                <i class="fa-solid fa-fire"></i>
                            </div>
                            <div>
                                <h3 class="text-xs font-black uppercase text-slate-800">Top Módulos / Áreas Demandadas</h3>
                                <p class="text-[10px] text-slate-400 font-medium">Áreas com maior incidência de manutenção</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <template x-for="(mod, idx) in (metricas.top_modulos || [])" :key="idx">
                            <div class="bg-slate-50 border border-slate-200/80 p-2.5 rounded-xl flex items-center justify-between">
                                <div class="truncate pr-2">
                                    <span class="text-[11px] font-bold text-slate-800 block truncate" x-text="mod.modulo">Módulo</span>
                                    <span class="text-[9px] text-slate-400 font-semibold uppercase">Fila TI</span>
                                </div>
                                <span class="text-xs font-black px-2 py-0.5 rounded-lg bg-indigo-100 text-indigo-700" x-text="mod.total">0</span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEÇÃO 3: TABELA DE ENTREGAS TÉCNICAS RECENTES -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-8 h-8 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-code-commit"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-black uppercase text-slate-800">Entregas Técnicas & Resoluções Recentes</h3>
                        <p class="text-[10px] text-slate-400 font-medium">Incidentes resolvidos e liberados em produção pela equipe de Engenharia</p>
                    </div>
                </div>
                <button type="button" @click="modoExibicao = 'lista'; filtros.status = '3'; carregarChamados()"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1 transition">
                    <span>Ver todos concluídos</span>
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                            <th class="py-3 px-4"># ID</th>
                            <th class="py-3 px-4">Sistema / Origem</th>
                            <th class="py-3 px-4">Título do Incidente</th>
                            <th class="py-3 px-4">Módulo / Contexto</th>
                            <th class="py-3 px-4">Versão</th>
                            <th class="py-3 px-4">Responsável Técnico</th>
                            <th class="py-3 px-4">Data Conclusão</th>
                            <th class="py-3 px-4 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-600">
                        <template x-for="r in (metricas.resolvidos_recentes || [])" :key="r.idchamado">
                            <tr class="hover:bg-slate-50/80 transition cursor-pointer" @click="abrirDetalhes(r.idchamado)">
                                <td class="py-3 px-4 font-black text-indigo-600" x-text="`#${r.idchamado}`">#0</td>
                                <td class="py-3 px-4">
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border inline-block"
                                          :class="badgeSistema(r.tipo_sistema)" x-text="r.tipo_sistema">PORTAL</span>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-800">
                                    <span class="line-clamp-1" x-text="r.titulo">Título</span>
                                </td>
                                <td class="py-3 px-4 text-slate-600 font-semibold" x-text="r.modulo_nome || r.tipo_sistema">Módulo</td>
                                <td class="py-3 px-4">
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200"
                                          x-text="r.versao_entrega || 'v3.5'">v3.5</span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-[10px] font-bold">
                                            <i class="fa-solid fa-user-check"></i>
                                        </div>
                                        <span class="font-bold text-slate-800" x-text="r.atendente || 'MARCON'">MARCON</span>
                                        <span class="text-[9px] font-black uppercase px-1 py-0.2 rounded bg-indigo-50 text-indigo-600 border border-indigo-100">DevOps / TI</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-400 text-[11px]" x-text="r.concluido_fmt">10/11</td>
                                <td class="py-3 px-4 text-center">
                                    <button class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-bold text-xs transition">
                                        Dossiê
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="(metricas.resolvidos_recentes || []).length === 0">
                            <td colspan="8" class="py-8 text-center text-xs text-slate-400">
                                Nenhuma entrega recente registrada.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL 1: NOVO CHAMADO COM TRIAGEM DE IA & ANEXO/PRINT -->
    <div x-show="modalNovoAberto" @paste="tratarColagemPrint($event, 'novo')" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-hidden" @click.outside="modalNovoAberto = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Abertura de Chamado Inteligente</h3>
                        <p class="text-xs text-slate-400">Relate com suas próprias palavras; anexe prints (Ctrl+V) e deixe a IA formatar</p>
                    </div>
                </div>
                <button @click="modalNovoAberto = false" class="text-slate-400 hover:text-slate-600 w-8 h-8 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="py-4 space-y-4 overflow-y-auto custom-scrollbar flex-1 pr-1">
                <!-- 1. SELEÇÃO DO SISTEMA OU ORIGEM -->
                <div>
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">
                        Sistema ou Equipamento Envolvido <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-2">
                        <template x-for="s in sistemas" :key="s.id">
                            <button type="button" @click="novoForm.tipo_sistema = s.id"
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

                <!-- 2. CAMPOS CONTEXTUAIS CONFORME SISTEMA -->
                <!-- SE PORTAL: Módulos Web Liberados -->
                <div x-show="novoForm.tipo_sistema === 'PORTAL'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Módulo do Portal</label>
                        <select x-model="novoForm.modulo_slug" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="">Deixar IA Detectar pelo Relato</option>
                            <template x-for="m in modulos" :key="m.slug">
                                <option :value="m.slug" x-text="`${m.nome} (${m.setor})`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Filial da Ocorrência</label>
                        <select x-model="novoForm.idfilial" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <template x-for="f in filiais" :key="f.idfilial">
                                <option :value="f.idfilial" x-text="f.nome || `${f.idfilial} - ${f.razao}`"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <!-- SE EQUIPAMENTO: Impressora, Ramal, Computador -->
                <div x-show="novoForm.tipo_sistema === 'EQUIPAMENTO'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Tipo de Equipamento</label>
                        <select x-model="novoForm.subtipo_item" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="">Selecione o tipo...</option>
                            <option value="Impressora">Impressora / Toner / Etiquetas</option>
                            <option value="Telefonia / Ramal">Telefone / Ramal / PABX</option>
                            <option value="Computador / Notebook">Computador / Notebook</option>
                            <option value="Coletor / Leitor de Barras">Leitor / Coletor de Barras (Expedição)</option>
                            <option value="Monitor / Teclado / Mouse">Monitor / Teclado / Mouse</option>
                            <option value="Nobreak / Estabilizador">Nobreak / Estabilizador</option>
                            <option value="Outro Dispositivo">Outro Dispositivo Físico</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Identificação / Setor do Aparelho</label>
                        <input type="text" x-model="novoForm.equipamento_detalhe" placeholder="Ex: HP LaserJet Expedição, Ramal 204, PC Fiscal 02"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- SE ERP EMA: Rotina / Módulo Desktop -->
                <div x-show="novoForm.tipo_sistema === 'ERP_EMA'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Rotina do ERP Ema</label>
                        <select x-model="novoForm.subtipo_item" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="">Selecione a rotina do Ema...</option>
                            <option value="Pedidos de Venda">Pedidos de Venda / Orçamentos</option>
                            <option value="Faturamento / Emissão de NF-e">Faturamento / Emissão de NF-e</option>
                            <option value="Compras / Entrada de NF">Compras / Entrada de NF</option>
                            <option value="Estoque / Saldos / Lotes">Estoque / Saldos / Lotes</option>
                            <option value="Fiscal / Sped / CFOP">Fiscal / Sped / CFOP</option>
                            <option value="Financeiro / Contas a Receber/Pagar">Financeiro / Contas a Receber/Pagar</option>
                            <option value="Cadastros Gerais">Cadastros de Clientes/Produtos</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Nº do Pedido / Nota / Tela</label>
                        <input type="text" x-model="novoForm.equipamento_detalhe" placeholder="Ex: NF 300808, Pedido 145920, Tela 0401"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- SE LOGISOFT: CT-e / MDF-e / CIOT -->
                <div x-show="novoForm.tipo_sistema === 'LOGISOFT'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Operação Logisoft</label>
                        <select x-model="novoForm.subtipo_item" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="Emissão de CT-e">Emissão de Conhecimento (CT-e)</option>
                            <option value="Manifesto MDF-e">Manifesto Eletrônico (MDF-e)</option>
                            <option value="Registro de CIOT">Registro / Encerramento de CIOT</option>
                            <option value="Averbação de Seguro">Averbação de Carga / Seguro</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Nº CTe / Manifesto / Placa</label>
                        <input type="text" x-model="novoForm.equipamento_detalhe" placeholder="Ex: CTe 12440, MDF-e 342, Placa ABC-1234"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- SE COBLI: Rastreamento & Frotas -->
                <div x-show="novoForm.tipo_sistema === 'COBLI'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Recurso Cobli</label>
                        <select x-model="novoForm.subtipo_item" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="Rastreamento / Mapa">Localização / Rastreador Offline</option>
                            <option value="Telemetria de Velocidade">Telemetria / Alertas de Velocidade</option>
                            <option value="Jornada de Motorista">Controle de Jornada / Paradas</option>
                            <option value="Dispositivo Veicular">Troca / Defeito de Equipamento na Cabine</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Placa do Veículo / Motorista</label>
                        <input type="text" x-model="novoForm.equipamento_detalhe" placeholder="Ex: Caminhão Mercedes Placa XYZ-9876"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- SE MERCOS: Força de Vendas -->
                <div x-show="novoForm.tipo_sistema === 'MERCOS'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">Recurso Mercos</label>
                        <select x-model="novoForm.subtipo_item" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-indigo-500">
                            <option value="Sincronização de Pedidos">Sincronização de Pedidos</option>
                            <option value="Catálogo / Produtos">Catálogo de Produtos / Fotos</option>
                            <option value="Tabela de Preços">Tabelas de Preços / Condições</option>
                            <option value="App Vendedor Mobile">Aplicativo no Celular do RCA</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">RCA / Pedido Mercos</label>
                        <input type="text" x-model="novoForm.equipamento_detalhe" placeholder="Ex: RCA Roberto, Pedido Mercos #84102"
                               class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500">
                    </div>
                </div>

                <!-- 3. RELATO LIVRE DO USUÁRIO -->
                <div>
                    <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block mb-1">
                        O que aconteceu? (Descreva o erro, solicitação ou dúvida) <span class="text-rose-500">*</span>
                    </label>
                    <textarea x-model="novoForm.relato" rows="4" placeholder="Ex: Impressora HP da expedição está puxando duas folhas e dando atolamento de papel... OU No módulo Verbas Flex a nota 300808 não deduziu o saldo..."
                              class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-500 focus:bg-white transition"></textarea>
                    <span class="text-[10px] text-slate-400 font-semibold block mt-1">
                        💡 Dica: Você pode apenas descrever o ocorrido e clicar em <strong>Analisar com IA</strong> que ela identificará o sistema e os termos técnicos automaticamente!
                    </span>
                </div>

                <!-- 4. ANEXAR IMAGEM / PRINT SCREEN (UPLOAD & CTRL+V) -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block">
                            Anexo / Print da Tela <span class="text-slate-400 font-normal lowercase">(opcional)</span>
                        </label>
                        <span class="text-[10px] text-indigo-700 font-bold bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-200/60 flex items-center gap-1">
                            <i class="fa-solid fa-keyboard text-indigo-500"></i> Cole print com <kbd class="font-mono bg-white px-1 py-0.2 rounded border border-indigo-200">Ctrl+V</kbd>
                        </span>
                    </div>

                    <!-- Input file oculto -->
                    <input type="file" x-ref="novoAnexoInput" @change="selecionarArquivoAnexo($event, 'novo')" accept="image/*,.pdf,.txt,.log" class="hidden">

                    <!-- Dropzone / Área de Clique e Colagem -->
                    <div x-show="!novoForm.anexo_url"
                         @click="$refs.novoAnexoInput.click()"
                         class="border-2 border-dashed border-slate-200 hover:border-indigo-400 hover:bg-indigo-50/20 rounded-2xl p-4 text-center cursor-pointer transition flex flex-col items-center justify-center gap-1.5 group">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 group-hover:bg-indigo-100 text-slate-400 group-hover:text-indigo-600 flex items-center justify-center text-base transition">
                            <i class="fa-solid fa-cloud-arrow-up" x-show="!anexoUploadando"></i>
                            <i class="fa-solid fa-spinner fa-spin text-indigo-600" x-show="anexoUploadando"></i>
                        </div>
                        <div>
                            <span class="text-xs font-bold text-slate-700 block">
                                <span class="text-indigo-600 hover:underline">Clique para selecionar imagem</span> ou pressione <strong>Ctrl+V</strong> para colar print
                            </span>
                            <span class="text-[10px] text-slate-400 font-medium">PNG, JPG, WebP, PDF ou TXT (máx. 15MB)</span>
                        </div>
                    </div>

                    <!-- Preview do Anexo Carregado -->
                    <div x-show="novoForm.anexo_url" class="bg-indigo-50/70 border border-indigo-200 rounded-2xl p-3 flex items-center justify-between gap-3 shadow-xs">
                        <div class="flex items-center gap-3 overflow-hidden">
                            <template x-if="ehImagem(novoForm.anexo_url)">
                                <img :src="novoForm.anexo_url" class="w-14 h-14 object-cover rounded-xl border border-indigo-200 cursor-pointer shadow-xs hover:opacity-90 transition"
                                     @click="abrirLightbox(novoForm.anexo_url, 'Print Anexado ao Chamado')" title="Clique para ampliar o print">
                            </template>
                            <template x-if="!ehImagem(novoForm.anexo_url)">
                                <div class="w-12 h-12 rounded-xl bg-indigo-200 text-indigo-700 flex items-center justify-center text-lg shrink-0">
                                    <i class="fa-solid fa-file-lines"></i>
                                </div>
                            </template>
                            <div class="truncate">
                                <span class="text-xs font-black text-indigo-950 block truncate">Print / Arquivo Anexado</span>
                                <button type="button" @click="abrirLightbox(novoForm.anexo_url, 'Print Anexado')" class="text-[11px] text-indigo-600 font-bold hover:underline flex items-center gap-1 mt-0.5">
                                    <i class="fa-solid fa-up-right-and-down-left-from-center text-[10px]"></i> Visualizar Ampliado
                                </button>
                            </div>
                        </div>
                        <button type="button" @click="removerAnexo('novo')" class="w-8 h-8 rounded-xl bg-white hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-200 hover:border-rose-200 flex items-center justify-center transition shrink-0" title="Remover anexo">
                            <i class="fa-solid fa-trash-can text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Botão de Triagem com IA -->
                <div class="flex items-center justify-between bg-indigo-50/60 p-3 rounded-2xl border border-indigo-100">
                    <div class="flex items-center gap-2 text-xs text-indigo-900 font-semibold">
                        <i class="fa-solid fa-microchip text-indigo-600 text-sm"></i>
                        <span>Otimizar título, criticidade e diagnóstico de engenharia</span>
                    </div>
                    <button type="button" @click="analisarComIa()" :disabled="analisandoIa || !novoForm.relato"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs rounded-xl flex items-center gap-2 transition shadow-sm">
                        <i class="fa-solid fa-wand-magic-sparkles" :class="analisandoIa ? 'fa-spin' : ''"></i>
                        <span x-text="analisandoIa ? 'Analisando...' : 'Analisar com IA'">Analisar com IA</span>
                    </button>
                </div>

                <!-- Resultados da Triagem IA (Exibidos se já analisado) -->
                <div x-show="triagemFeita" class="space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-black uppercase text-indigo-700 flex items-center gap-1.5">
                            <i class="fa-solid fa-check-circle"></i> Triagem da IA Concluída
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded" :class="badgeCriticidade(novoForm.criticidade)" x-text="`Criticidade: ${novoForm.criticidade}`">Média</span>
                            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded bg-slate-200 text-slate-700" x-text="`Tipo: ${novoForm.categoria}`">Bug</span>
                        </div>
                    </div>

                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">Título Sugerido</label>
                        <input type="text" x-model="novoForm.titulo" class="w-full px-3 py-1.5 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-800">
                    </div>

                    <div>
                        <label class="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">Dossiê Técnico de Engenharia</label>
                        <pre class="bg-slate-900 text-slate-100 p-3 rounded-xl text-[10px] font-mono leading-relaxed whitespace-pre-wrap overflow-x-auto max-h-40" x-text="novoForm.diagnostico_ia"></pre>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                <span class="text-[11px] text-slate-500 font-medium flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-halved text-emerald-500"></i>
                    <span>Direcionamento automático para a fila técnica: <strong>ALAN</strong></span>
                </span>
                <div class="flex items-center gap-2.5 w-full sm:w-auto justify-end">
                    <button type="button" @click="modalNovoAberto = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition">
                        Cancelar
                    </button>
                    <button type="button" @click="salvarNovoChamado()" :disabled="salvandoChamado || !novoForm.relato"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-black flex items-center gap-2 transition shadow-md shadow-indigo-600/20">
                        <i class="fa-solid fa-paper-plane" x-show="!salvandoChamado"></i>
                        <i class="fa-solid fa-spinner fa-spin" x-show="salvandoChamado"></i>
                        <span>Confirmar & Abrir Chamado</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL 2: DETALHES, DOSSIÊ & RESOLUÇÃO -->
    <div x-show="modalDetalhesAberto" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-3xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[92vh] overflow-hidden" @click.outside="modalDetalhesAberto = false">
            <template x-if="chamadoAtual">
                <div class="flex flex-col h-full overflow-hidden">
                    <!-- Topo do Modal -->
                    <div class="flex items-start justify-between pb-4 border-b border-slate-100 gap-3">
                        <div>
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span class="text-xs font-black text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100" x-text="`CHAMADO #${chamadoAtual.idchamado}`">#1</span>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded border" :class="badgeSistema(chamadoAtual.tipo_sistema)" x-text="chamadoAtual.tipo_sistema">PORTAL</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700" x-show="chamadoAtual.subtipo_item && chamadoAtual.subtipo_item !== 'Geral'" x-text="chamadoAtual.subtipo_item">Item</span>
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded" :class="badgeCriticidade(chamadoAtual.criticidade)" x-text="chamadoAtual.criticidade">Média</span>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700" x-text="chamadoAtual.categoria">Bug</span>
                            </div>
                            <h2 class="text-base sm:text-lg font-black text-slate-800" x-text="chamadoAtual.titulo || chamadoAtual.motivo">Título</h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                Solicitado por <strong class="text-slate-700" x-text="chamadoAtual.solicitante">User</strong> em <span x-text="chamadoAtual.data_fmt">Data</span> · 
                                Origem: <strong class="text-indigo-600" x-text="chamadoAtual.tipo_sistema === 'PORTAL' ? chamadoAtual.modulo_nome : (chamadoAtual.equipamento_detalhe || chamadoAtual.subtipo_item || chamadoAtual.tipo_sistema)">Módulo</strong> · 
                                Técnico: <strong class="text-emerald-700 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200" x-text="chamadoAtual.atendente || 'ALAN'">ALAN</strong>
                            </p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" x-show="usuario && usuario.pode_ver_todos && chamadoAtual.atendente !== usuario.username"
                                    @click="assumirChamado(chamadoAtual.idchamado)"
                                    class="px-3 py-1.5 rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-700 hover:from-emerald-700 hover:to-emerald-800 text-white font-black text-xs flex items-center gap-1.5 shadow-sm active:scale-95 transition"
                                    title="Atribuir este chamado para mim">
                                <i class="fa-solid fa-user-check"></i>
                                <span>Assumir Chamado</span>
                            </button>
                            <button @click="modalDetalhesAberto = false" class="text-slate-400 hover:text-slate-600 w-8 h-8 rounded-lg flex items-center justify-center">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Corpo com Scroll -->
                    <div class="py-4 space-y-4 overflow-y-auto custom-scrollbar flex-1 pr-1">
                        <!-- BARRA DE ENGENHARIA / TERMINAL DEVOPS TI -->
                        <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white p-3.5 rounded-2xl border border-slate-700/60 shadow-md flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-base border border-indigo-500/30">
                                    <i class="fa-solid fa-laptop-code"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-300">Terminal de Engenharia / DevOps</span>
                                        <span class="text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1.5 py-0.2 rounded font-bold">Ativo</span>
                                    </div>
                                    <span class="text-xs font-bold text-white block">Dossiê Técnico para IDE / Resolução no Código</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="copiarBriefingDevOps()"
                                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-white text-slate-900 hover:bg-slate-100 active:scale-95 font-black text-xs flex items-center justify-center gap-2 transition shadow-xs">
                                    <i class="fa-solid fa-code text-indigo-600"></i>
                                    <span>Copiar Dossiê Técnico para IDE</span>
                                </button>
                            </div>
                        </div>

                        <!-- Relato Original -->
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Relato Original</span>
                            <p class="text-xs text-slate-700 whitespace-pre-wrap font-medium" x-text="chamadoAtual.motivo || chamadoAtual.observacao">Descrição</p>
                        </div>

                        <!-- Card de Anexo / Print do Chamado (Se existir) -->
                        <div x-show="chamadoAtual.anexo_url" class="bg-slate-50 p-3.5 rounded-2xl border border-indigo-100 flex items-center justify-between gap-3 shadow-xs">
                            <div class="flex items-center gap-3 overflow-hidden">
                                <template x-if="ehImagem(chamadoAtual.anexo_url)">
                                    <img :src="chamadoAtual.anexo_url" class="w-16 h-16 object-cover rounded-xl border border-indigo-200 cursor-pointer shadow-xs hover:opacity-90 transition shrink-0"
                                         @click="abrirLightbox(chamadoAtual.anexo_url, `Print do Chamado #${chamadoAtual.idchamado}`)" title="Clique para ampliar o print">
                                </template>
                                <template x-if="!ehImagem(chamadoAtual.anexo_url)">
                                    <div class="w-14 h-14 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-xl shrink-0">
                                        <i class="fa-solid fa-file-arrow-down"></i>
                                    </div>
                                </template>
                                <div class="truncate">
                                    <span class="text-xs font-black text-slate-800 block truncate">Anexo / Printscreen Comprobatório</span>
                                    <span class="text-[11px] text-slate-400 block truncate" x-text="chamadoAtual.anexo_url">url</span>
                                    <div class="flex items-center gap-2 mt-1">
                                        <button type="button" @click="abrirLightbox(chamadoAtual.anexo_url, `Print do Chamado #${chamadoAtual.idchamado}`)"
                                                class="text-[11px] font-bold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                                            <i class="fa-solid fa-up-right-and-down-left-from-center text-[10px]"></i> Visualizar Ampliado
                                        </button>
                                        <span class="text-slate-300">·</span>
                                        <a :href="chamadoAtual.anexo_url" target="_blank" download class="text-[11px] font-bold text-slate-500 hover:text-slate-800 flex items-center gap-1 no-underline">
                                            <i class="fa-solid fa-download text-[10px]"></i> Baixar Arquivo
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dossiê Técnico de Engenharia (Se existir) -->
                        <div x-show="chamadoAtual.diagnostico_ia" class="bg-slate-900 text-slate-100 p-4 rounded-2xl border border-slate-800">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-black uppercase text-indigo-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-microchip"></i> Parecer Técnico de Engenharia
                                </span>
                                <span class="text-[10px] text-slate-400 font-mono">Dossiê Preliminar</span>
                            </div>
                            <pre class="text-[10.5px] font-mono leading-relaxed whitespace-pre-wrap text-slate-200 overflow-x-auto max-h-48" x-text="chamadoAtual.diagnostico_ia"></pre>
                        </div>

                        <!-- Se já concluído: Exibe a Solução Aplicada -->
                        <div x-show="chamadoAtual.status == 3" class="bg-emerald-50 p-4 rounded-2xl border border-emerald-200">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-[11px] font-black uppercase text-emerald-800 flex items-center gap-1.5">
                                    <i class="fa-solid fa-circle-check"></i> Resolução Técnica Aplicada
                                </span>
                                <span class="text-[10px] font-bold text-emerald-700" x-text="`Versão: ${chamadoAtual.versao_entrega || 'v3.5'}`">v3.5</span>
                            </div>
                            <p class="text-xs text-emerald-900 font-medium whitespace-pre-wrap mt-1" x-text="chamadoAtual.solucao_tecnica || 'Solução registrada com sucesso.'"></p>
                        </div>

                        <!-- Linha do Tempo / Timeline -->
                        <div>
                            <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-2">Linha do Tempo de Resolução</span>
                            <div class="space-y-2.5">
                                <template x-for="op in timeline" :key="op.idoperacao">
                                    <div class="flex items-start gap-2.5 text-xs p-3 rounded-xl border transition"
                                         :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'bg-indigo-50/70 border-indigo-200 ring-1 ring-indigo-100' : 'bg-slate-50 border-slate-200/60'">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs mt-0.5"
                                             :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-200 text-slate-700'">
                                            <i class="fa-solid fa-laptop-code" x-show="op.usuario === 'MARCON' || op.usuario === 'ALAN'"></i>
                                            <i class="fa-solid fa-comment" x-show="op.usuario !== 'MARCON' && op.usuario !== 'ALAN' && op.tipo === 'comentario'"></i>
                                            <i class="fa-solid fa-arrows-rotate" x-show="op.usuario !== 'MARCON' && op.usuario !== 'ALAN' && op.tipo === 'mudanca_status'"></i>
                                            <i class="fa-solid fa-check" x-show="op.usuario !== 'MARCON' && op.usuario !== 'ALAN' && op.tipo === 'resolucao'"></i>
                                            <i class="fa-solid fa-plus" x-show="op.usuario !== 'MARCON' && op.usuario !== 'ALAN' && op.tipo === 'abertura'"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between text-[11px] mb-0.5">
                                                <div class="flex items-center gap-1.5">
                                                    <strong :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'text-indigo-950 font-black' : 'text-slate-800'" x-text="op.usuario">User</strong>
                                                    <span x-show="op.usuario === 'MARCON' || op.usuario === 'ALAN'" class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-indigo-200 text-indigo-900 font-bold">Suporte / TI</span>
                                                </div>
                                                <span class="text-slate-400 text-[10px]" x-text="op.data_fmt">Data</span>
                                            </div>
                                            <div class="font-medium" :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'text-indigo-950 font-bold' : 'text-slate-600'" x-text="op.descricao">Ação</div>
                                            <div x-show="op.detalhes" class="text-[11px] mt-1 whitespace-pre-wrap font-mono leading-relaxed p-2 rounded-lg"
                                                 :class="(op.usuario === 'MARCON' || op.usuario === 'ALAN') ? 'bg-indigo-900/5 text-indigo-950 border border-indigo-200/60' : 'text-slate-500 italic bg-white/70 border border-slate-100'" x-text="op.detalhes"></div>
                                            
                                            <!-- Anexo da Operação (Se houver) -->
                                            <div x-show="op.anexo_url" class="mt-2">
                                                <template x-if="ehImagem(op.anexo_url)">
                                                    <img :src="op.anexo_url" class="max-w-[180px] max-h-[120px] rounded-lg border border-slate-200 cursor-pointer shadow-xs hover:opacity-90 transition"
                                                         @click="abrirLightbox(op.anexo_url, `Anexo da Interação por ${op.usuario}`)" title="Clique para ampliar">
                                                </template>
                                                <template x-if="!ehImagem(op.anexo_url)">
                                                    <a :href="op.anexo_url" target="_blank" download class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 text-[10px] font-bold border border-indigo-200 hover:bg-indigo-100 no-underline transition">
                                                        <i class="fa-solid fa-file-arrow-down"></i> Baixar Anexo
                                                    </a>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Formulário de Interação / Conclusão com Suporte a Prints -->
                        <div @paste="tratarColagemPrint($event, 'interacao')" class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-[11px] font-black uppercase text-indigo-900 block">Registrar Interação / Solução</span>
                                <span class="text-[10px] text-slate-500 font-medium">Cole prints com <kbd class="font-mono bg-white px-1 py-0.2 rounded border border-indigo-200">Ctrl+V</kbd></span>
                            </div>
                            <textarea x-model="novaInteracao.mensagem" rows="2" placeholder="Digite uma observação técnica, commit ou descrição da resolução..."
                                      class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-500"></textarea>

                            <!-- Preview de Anexo na Interação -->
                            <div x-show="novaInteracao.anexo_url" class="bg-white border border-indigo-200 rounded-xl p-2.5 flex items-center justify-between gap-2 shadow-2xs">
                                <div class="flex items-center gap-2 overflow-hidden">
                                    <template x-if="ehImagem(novaInteracao.anexo_url)">
                                        <img :src="novaInteracao.anexo_url" class="w-10 h-10 object-cover rounded-lg border border-indigo-200 cursor-pointer"
                                             @click="abrirLightbox(novaInteracao.anexo_url, 'Print Anexado à Interação')">
                                    </template>
                                    <span class="text-[11px] font-bold text-slate-700 truncate">Print / Arquivo pronto para envio</span>
                                </div>
                                <button type="button" @click="removerAnexo('interacao')" class="text-rose-500 hover:text-rose-700 text-xs px-2 py-1 font-bold transition">
                                    <i class="fa-solid fa-trash-can"></i> Remover
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <input type="file" x-ref="interacaoAnexoInput" @change="selecionarArquivoAnexo($event, 'interacao')" accept="image/*,.pdf,.txt,.log" class="hidden">
                                    <button type="button" @click="$refs.interacaoAnexoInput.click()"
                                            class="px-2.5 py-1.5 rounded-xl bg-white hover:bg-slate-100 border border-slate-200 text-slate-600 font-bold text-xs flex items-center gap-1.5 transition shadow-2xs">
                                        <i class="fa-solid fa-paperclip text-slate-400"></i>
                                        <span>Anexar Print</span>
                                    </button>

                                    <label class="text-xs text-slate-700 font-bold flex items-center gap-1.5 cursor-pointer ml-1">
                                        <input type="checkbox" x-model="novaInteracao.eh_resolucao" class="rounded text-indigo-600 focus:ring-0">
                                        <span>Marcar como Concluído</span>
                                    </label>
                                    <input type="text" x-show="novaInteracao.eh_resolucao" x-model="novaInteracao.versao_entrega" placeholder="Versão (ex: v3.5)"
                                           class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs font-bold text-slate-700 w-24">
                                </div>

                                <div class="flex items-center gap-2">
                                    <select x-model="chamadoAtual.status" @change="trocarStatus(chamadoAtual.status)"
                                            class="px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none">
                                        <option value="0">Status: Aberto</option>
                                        <option value="1">Status: Em Análise</option>
                                        <option value="2">Status: Em Correção</option>
                                        <option value="3">Status: Concluído</option>
                                        <option value="4">Status: Cancelado</option>
                                    </select>

                                    <button @click="enviarInteracao()" :disabled="!novaInteracao.mensagem && !novaInteracao.anexo_url"
                                            class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white font-bold text-xs flex items-center gap-1.5 transition shadow-xs">
                                        <i class="fa-solid fa-paper-plane"></i>
                                        <span>Registrar</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>

    <!-- MODAL 3: LIGHTBOX AMPLIAÇÃO DE PRINTS/IMAGENS -->
    <div x-show="lightboxAberto" class="fixed inset-0 z-[80] flex items-center justify-center p-3 sm:p-6 bg-slate-950/85 backdrop-blur-md" style="display: none;"
         @keydown.escape.window="fecharLightbox()">
        <div class="relative max-w-5xl w-full flex flex-col items-center max-h-[95vh]" @click.outside="fecharLightbox()">
            <!-- Barra superior do Lightbox -->
            <div class="w-full flex items-center justify-between text-white pb-3 px-2">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-image text-indigo-400"></i>
                    <span class="text-xs sm:text-sm font-bold" x-text="lightboxTitulo">Anexo Ampliado</span>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="lightboxUrl" target="_blank" download class="px-3 py-1.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-1.5 transition no-underline">
                        <i class="fa-solid fa-download"></i> Baixar Original
                    </a>
                    <button type="button" @click="fecharLightbox()" class="w-8 h-8 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                </div>
            </div>

            <!-- Imagem ampliada -->
            <div class="bg-slate-900/60 rounded-2xl p-2 border border-white/10 shadow-2xl overflow-auto max-h-[85vh] flex items-center justify-center w-full">
                <img :src="lightboxUrl" class="max-h-[80vh] w-auto max-w-full rounded-xl object-contain shadow-2xl">
            </div>
        </div>
    </div>

</div>

<!-- SCRIPT DO MÓDULO -->
<script src="<?= $appBase ?>/portal/assets/js/<?= $moduleJs ?>?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
