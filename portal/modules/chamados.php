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
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-indigo-500/30 text-indigo-200 border border-indigo-400/30 flex items-center gap-1.5">
                            <i class="fa-solid fa-bolt text-amber-300"></i> Service Desk Inteligente
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-400/30 flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 pulse-ia"></span> IA Triagem Ativa
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1 flex items-center gap-2">
                        Central de Chamados & Suporte
                    </h1>
                    <p class="text-xs sm:text-sm text-indigo-200 opacity-90 mt-0.5">
                        Governança, triagem assistida por inteligência artificial e resolução ágil dos 40 módulos do Portal
                    </p>
                </div>
            </div>

            <!-- BOTÕES DE AÇÃO DO TOPO -->
            <div class="flex flex-wrap items-center gap-2.5">
                <button @click="abrirModalNovo()" class="px-4 py-2.5 rounded-2xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-900 text-xs font-black flex items-center gap-2 transition shadow-lg shadow-amber-500/20">
                    <i class="fa-solid fa-plus-circle text-sm"></i>
                    <span>Abrir Chamado</span>
                </button>

                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=chamados" class="px-4 py-2.5 rounded-2xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold flex items-center gap-2 transition no-underline shadow-sm border border-white/15">
                    <i class="fa-solid fa-graduation-cap"></i>
                    <span>Treinamento</span>
                </a>
            </div>
        </div>
    </div>

    <!-- 4 KPI CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-5">
        <!-- KPI 1: Total Geral -->
        <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Total Registrado</span>
                <span class="text-2xl font-black text-slate-800" x-text="kpis.total || 0">0</span>
                <span class="text-[10px] text-slate-400 font-semibold block mt-0.5">Histórico integrado ao ERP</span>
            </div>
            <div class="w-11 h-11 rounded-2xl bg-slate-100 text-slate-600 flex items-center justify-center text-lg">
                <i class="fa-solid fa-ticket"></i>
            </div>
        </div>

        <!-- KPI 2: Abertos & Em Análise -->
        <div class="bg-white rounded-2xl p-4 border border-amber-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-amber-600 uppercase tracking-wider block mb-0.5">Abertos / Fila</span>
                <span class="text-2xl font-black text-amber-600" x-text="kpis.abertos || 0">0</span>
                <span class="text-[10px] text-amber-700/70 font-semibold block mt-0.5">Aguardando atendimento</span>
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
                <span class="text-[11px] font-bold text-rose-600 uppercase tracking-wider block mb-0.5">Críticos em Aberto</span>
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
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-5">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Busca Textual -->
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" x-model="filtros.busca" @input.debounce.300ms="carregarChamados()" placeholder="Buscar por número, título, motivo ou solicitante..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-indigo-500 focus:bg-white transition">
            </div>

            <!-- Filtros em Selects -->
            <div class="flex flex-wrap items-center gap-2">
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

                <!-- Alternador Kanban / Lista -->
                <div class="bg-slate-100 p-0.5 rounded-xl border border-slate-200 flex items-center">
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
                </div>
            </div>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL: MODO KANBAN -->
    <div x-show="modoExibicao === 'kanban'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                            <span class="truncate max-w-[130px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <span x-text="c.solicitante">User</span>
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
                            <span class="truncate max-w-[130px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <span x-text="c.solicitante">User</span>
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
                            <span class="truncate max-w-[130px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <span x-text="c.solicitante">User</span>
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
                            <span class="truncate max-w-[130px] font-semibold text-slate-600" x-text="c.tipo_sistema === 'PORTAL' ? c.modulo_nome : (c.subtipo_item || c.tipo_sistema)">Módulo</span>
                            <span x-text="c.concluido_fmt || c.data_fmt">Data</span>
                        </div>
                    </div>
                </template>
                <div x-show="colunaChamados(3).length === 0" class="text-center py-8 text-xs text-slate-400">
                    Nenhum concluído nesta lista
                </div>
            </div>
        </div>
    </div>

    <!-- CONTEÚDO PRINCIPAL: MODO LISTA -->
    <div x-show="modoExibicao === 'lista'" class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
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
                            <td class="py-3 px-4 text-slate-400 text-[11px]" x-text="c.data_fmt">10/11/2026</td>
                            <td class="py-3 px-4 text-center">
                                <button class="px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-600 hover:bg-indigo-100 font-bold text-xs transition">
                                    Ver
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="chamados.length === 0">
                        <td colspan="10" class="py-8 text-center text-xs text-slate-400">
                            Nenhum chamado encontrado com os filtros selecionados.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL 1: NOVO CHAMADO COM TRIAGEM DE IA -->
    <div x-show="modalNovoAberto" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-2xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[90vh] overflow-hidden" @click.outside="modalNovoAberto = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center">
                        <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Abertura de Chamado Inteligente</h3>
                        <p class="text-xs text-slate-400">Relate com suas próprias palavras; a IA formata e enriquece o chamado</p>
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
                            <option value="1">Filial 1 - Matriz SC</option>
                            <option value="6">Filial 6 - Paraná (PR)</option>
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

                <!-- Botão de Triagem com IA -->
                <div class="flex items-center justify-between bg-indigo-50/60 p-3 rounded-2xl border border-indigo-100">
                    <div class="flex items-center gap-2 text-xs text-indigo-900 font-semibold">
                        <i class="fa-solid fa-robot text-indigo-600 text-sm"></i>
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
                        <label class="text-[10px] font-bold text-slate-400 uppercase block mb-0.5">Dossiê Técnico (Para Desenvolvedor & Antigravity)</label>
                        <pre class="bg-slate-900 text-slate-100 p-3 rounded-xl text-[10px] font-mono leading-relaxed whitespace-pre-wrap overflow-x-auto max-h-40" x-text="novoForm.diagnostico_ia"></pre>
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
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

    <!-- MODAL 2: DETALHES, DOSSIÊ & RESOLUÇÃO -->
    <div x-show="modalDetalhesAberto" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-3xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[92vh] overflow-hidden" @click.outside="modalDetalhesAberto = false">
            <template x-if="chamadoAtual">
                <div class="flex flex-col h-full overflow-hidden">
                    <!-- Topo do Modal -->
                    <div class="flex items-start justify-between pb-4 border-b border-slate-100">
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
                                Origem: <strong class="text-indigo-600" x-text="chamadoAtual.tipo_sistema === 'PORTAL' ? chamadoAtual.modulo_nome : (chamadoAtual.equipamento_detalhe || chamadoAtual.subtipo_item || chamadoAtual.tipo_sistema)">Módulo</strong>
                            </p>
                        </div>
                        <button @click="modalDetalhesAberto = false" class="text-slate-400 hover:text-slate-600 w-8 h-8 rounded-lg flex items-center justify-center">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <!-- Corpo com Scroll -->
                    <div class="py-4 space-y-4 overflow-y-auto custom-scrollbar flex-1 pr-1">
                        <!-- BARRA DE VÍNCULO DIRETO COM O AGENTE ANTIGRAVITY -->
                        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-purple-950 text-white p-3.5 rounded-2xl border border-indigo-700/50 shadow-md flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-base border border-indigo-500/30">
                                    <i class="fa-solid fa-robot"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-black uppercase tracking-wider text-indigo-300">Conector Antigravity IDE</span>
                                        <span class="text-[9px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-1.5 py-0.2 rounded font-bold">Online</span>
                                    </div>
                                    <span class="text-xs font-bold text-white block">Vincular resolução no código com o agente</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="copiarBriefingAntigravity()"
                                        class="w-full sm:w-auto px-4 py-2 rounded-xl bg-white text-indigo-950 hover:bg-indigo-50 active:scale-95 font-black text-xs flex items-center justify-center gap-2 transition shadow-xs">
                                    <i class="fa-solid fa-copy text-indigo-600"></i>
                                    <span>Copiar Briefing para o Chat</span>
                                </button>
                            </div>
                        </div>

                        <!-- Relato Original -->
                        <div class="bg-slate-50 p-3.5 rounded-2xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Relato Original</span>
                            <p class="text-xs text-slate-700 whitespace-pre-wrap font-medium" x-text="chamadoAtual.motivo || chamadoAtual.observacao">Descrição</p>
                        </div>

                        <!-- Dossiê Técnico da IA (Se existir) -->
                        <div x-show="chamadoAtual.diagnostico_ia" class="bg-slate-900 text-slate-100 p-4 rounded-2xl border border-slate-800">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-[11px] font-black uppercase text-indigo-400 flex items-center gap-1.5">
                                    <i class="fa-solid fa-robot"></i> Parecer Técnico de Engenharia (IA)
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
                                         :class="op.usuario === 'ANTIGRAVITY' ? 'bg-purple-50/70 border-purple-200 ring-1 ring-purple-100' : 'bg-slate-50 border-slate-200/60'">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 text-xs mt-0.5"
                                             :class="op.usuario === 'ANTIGRAVITY' ? 'bg-purple-600 text-white shadow-xs' : 'bg-indigo-100 text-indigo-600'">
                                            <i class="fa-solid fa-robot" x-show="op.usuario === 'ANTIGRAVITY'"></i>
                                            <i class="fa-solid fa-comment" x-show="op.usuario !== 'ANTIGRAVITY' && op.tipo === 'comentario'"></i>
                                            <i class="fa-solid fa-arrows-rotate" x-show="op.usuario !== 'ANTIGRAVITY' && op.tipo === 'mudanca_status'"></i>
                                            <i class="fa-solid fa-check" x-show="op.usuario !== 'ANTIGRAVITY' && op.tipo === 'resolucao'"></i>
                                            <i class="fa-solid fa-plus" x-show="op.usuario !== 'ANTIGRAVITY' && op.tipo === 'abertura'"></i>
                                        </div>
                                        <div class="flex-1">
                                            <div class="flex items-center justify-between text-[11px] mb-0.5">
                                                <div class="flex items-center gap-1.5">
                                                    <strong :class="op.usuario === 'ANTIGRAVITY' ? 'text-purple-900 font-black' : 'text-slate-800'" x-text="op.usuario">User</strong>
                                                    <span x-show="op.usuario === 'ANTIGRAVITY'" class="text-[9px] font-black uppercase px-1.5 py-0.2 rounded bg-purple-200 text-purple-800">IA Agente</span>
                                                </div>
                                                <span class="text-slate-400 text-[10px]" x-text="op.data_fmt">Data</span>
                                            </div>
                                            <div class="font-medium" :class="op.usuario === 'ANTIGRAVITY' ? 'text-purple-900 font-bold' : 'text-slate-600'" x-text="op.descricao">Ação</div>
                                            <div x-show="op.detalhes" class="text-[11px] mt-1 whitespace-pre-wrap font-mono leading-relaxed p-2 rounded-lg"
                                                 :class="op.usuario === 'ANTIGRAVITY' ? 'bg-purple-900/10 text-purple-950 border border-purple-200/50' : 'text-slate-500 italic bg-white/70 border border-slate-100'" x-text="op.detalhes"></div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Formulário de Interação / Conclusão -->
                        <div class="bg-indigo-50/50 p-4 rounded-2xl border border-indigo-100 space-y-3">
                            <span class="text-[11px] font-black uppercase text-indigo-900 block">Registrar Interação / Solução</span>
                            <textarea x-model="novaInteracao.mensagem" rows="2" placeholder="Digite uma observação técnica, commit ou descrição da resolução..."
                                      class="w-full p-2.5 bg-white border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-500"></textarea>

                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <label class="text-xs text-slate-700 font-bold flex items-center gap-1.5 cursor-pointer">
                                        <input type="checkbox" x-model="novaInteracao.eh_resolucao" class="rounded text-indigo-600 focus:ring-0">
                                        <span>Marcar como Concluído / Resolvido</span>
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

                                    <button @click="enviarInteracao()" :disabled="!novaInteracao.mensagem"
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

</div>

<!-- SCRIPT DO MÓDULO -->
<script src="<?= $appBase ?>/portal/assets/js/<?= $moduleJs ?>?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
