<?php
$pageTitle = 'CRM Clientes | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<style>
:root {
    --crm-primary: #1e293b;
    --crm-secondary: #0f172a;
    --crm-accent: #10b981;
    --crm-danger: #ef4444;
    --crm-warning: #f59e0b;
    --crm-info: #3b82f6;
    --crm-purple: #8b5cf6;
}

* { font-family: "Inter", sans-serif; }



/* Timeline de Interações */
.timeline-item {
    position: relative;
    padding-left: 2rem;
    margin-bottom: 1.5rem;
}
.timeline-item::before {
    content: "";
    position: absolute;
    left: 0.5rem;
    top: 0;
    bottom: -1.5rem;
    width: 2px;
    background: linear-gradient(180deg, var(--timeline-color) 0%, #e2e8f0 100%);
}
.timeline-item:last-child::before { display: none; }
.timeline-icon {
    position: absolute;
    left: 0;
    top: 0;
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 2px solid var(--timeline-color);
}
.timeline-content {
    background: #f8fafc;
    border-radius: 12px;
    padding: 0.75rem 1rem;
}


/* Modal */
.modal-modern .modal-content {
    animation: slideUp 0.3s ease;
}
@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* Notificações CRM */
.notification-bell-crm {
    position: relative;
    cursor: pointer;
    transition: all 0.2s ease;
}
.notification-bell-crm:hover { transform: scale(1.05); }
.notification-badge-crm {
    position: absolute;
    top: -6px;
    right: -8px;
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
    border-radius: 20px;
    min-width: 18px;
    height: 18px;
    font-size: 10px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 5px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
    animation: pulse-crm 1.5s infinite;
}
@keyframes pulse-crm {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.15); }
}
.notification-dropdown-crm {
    position: absolute;
    top: 45px;
    right: 0;
    width: 380px;
    max-height: 500px;
    background: white;
    border-radius: 20px;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    z-index: 1000;
    overflow: hidden;
    animation: slideDownCrm 0.2s ease;
    border: 1px solid rgba(0,0,0,0.05);
}
@keyframes slideDownCrm {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
.notification-header-crm {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: white;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.notification-list-crm {
    max-height: 420px;
    overflow-y: auto;
}
.notification-item-crm {
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.2s;
}
.notification-item-crm:hover { background: #f8fafc; }
.notification-item-crm.unread {
    background: #eff6ff;
    border-left: 3px solid #3b82f6;
}
.notification-item-crm.unread:hover { background: #dbeafe; }
.notification-icon-crm {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.notification-title-crm {
    font-weight: 700;
    font-size: 13px;
    color: #1e293b;
}
.notification-message-crm {
    font-size: 11px;
    color: #64748b;
    margin-top: 3px;
    line-height: 1.4;
}
.notification-time-crm {
    font-size: 10px;
    color: #94a3b8;
    margin-top: 5px;
}
.mark-all-read-crm {
    font-size: 11px;
    color: #94a3b8;
    cursor: pointer;
    transition: color 0.2s;
    padding: 4px 8px;
    border-radius: 8px;
}
.mark-all-read-crm:hover {
    color: white;
    background: rgba(255,255,255,0.1);
}
.empty-notifications-crm {
    padding: 50px 20px;
    text-align: center;
    color: #94a3b8;
}
.notification-tag {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 9px;
    font-weight: bold;
    margin-left: 8px;
}
.tag-meta { background: #fef3c7; color: #d97706; }
.tag-lead { background: #fee2e2; color: #dc2626; }
.tag-compromisso { background: #dbeafe; color: #2563eb; }
.tag-interacao { background: #e0e7ff; color: #4f46e5; }

/* Agenda Premium */
.filter-agenda-btn {
    background: rgba(255,255,255,0.15);
    color: white;
}
.filter-agenda-btn:hover { background: rgba(255,255,255,0.25); }
.filter-agenda-btn.active {
    background: white;
    color: #3b82f6;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}
.fc-event {
    border-radius: 8px !important;
    border: none !important;
    padding: 2px 4px !important;
    font-size: 11px !important;
    font-weight: 500 !important;
    cursor: pointer !important;
    transition: transform 0.1s ease !important;
}
.fc-event:hover {
    transform: scale(1.02);
    filter: brightness(1.05);
}
.fc-event-title { font-weight: 600 !important; }
.fc-timegrid-event { min-height: 40px !important; }
.fc .fc-button-primary {
    background-color: #f1f5f9 !important;
    border-color: #e2e8f0 !important;
    color: #475569 !important;
    text-transform: capitalize !important;
    font-weight: 600 !important;
}
.fc .fc-button-primary:hover { background-color: #e2e8f0 !important; }
.fc .fc-toolbar-title {
    font-size: 1.1rem !important;
    font-weight: 700 !important;
    color: #1e293b !important;
}
.fc .fc-timegrid-now-indicator-line {
    border-color: #ef4444 !important;
    border-width: 2px !important;
}
.tipo-btn {
    transition: all 0.2s ease;
    cursor: pointer;
}
.btn-loading {
    position: relative;
    pointer-events: none;
    opacity: 0.7;
}
.btn-loading::after {
    content: "";
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}



</style>
';
require_once __DIR__ . '/../../estrutura/header.php';
?>

<div class="max-w-full mx-auto px-4 lg:px-6 py-4" x-data="crmProfissional()" x-init="init()" x-cloak>

    <!-- HEADER PROFISSIONAL -->
    <div class="bg-gradient-to-r from-slate-900 to-slate-800 rounded-3xl p-6 mb-8 shadow-2xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div class="flex items-center gap-4">
               <a href="/portal/modules/marketing/dashboard.php" class="btn-voltar flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-14 h-14 bg-gradient-to-br from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-crown text-white text-2xl"></i>
            </div>
            <div>
                <h1 class="text-2xl font-black text-white tracking-tight">CRM Clientes</h1>
                <p class="text-slate-300 text-sm">Gestão inteligente de relacionamento com clientes</p>
            </div>
        </div>

        <div class="flex gap-3 items-center">
            <!-- SINO DE NOTIFICAÇÕES CRM -->
            <div class="relative" x-data="notificacoesCRM()" x-init="init()" @click.outside="aberto = false">
                <button @click="toggleAbrir()" class="notification-bell-crm relative p-2.5 bg-white/10 backdrop-blur rounded-xl hover:bg-white/20 transition-all">
                    <i class="fa-regular fa-bell text-white text-xl"></i>
                    <span x-show="naoLidas > 0" x-text="naoLidas > 99 ? '99+' : naoLidas" 
                      class="notification-badge-crm" style="display: none;"></span>
                  </button>

                  <div x-show="aberto" x-transition.duration.200ms class="notification-dropdown-crm" style="display: none;">
                    <div class="notification-header-crm">
                        <span class="font-bold text-sm"><i class="fa-regular fa-bell mr-2"></i>Notificações</span>
                        <button @click="marcarTodasLidas()" class="mark-all-read-crm">
                            <i class="fa-regular fa-check-circle mr-1"></i>Marcar todas
                        </button>
                    </div>
                    <div class="notification-list-crm" x-ref="listaNotificacoes">
                        <template x-for="n in notificacoes" :key="n.id">
                            <div class="notification-item-crm" :class="{ 'unread': !n.lida }" @click="handleClick(n)">
                                <div class="flex gap-3">
                                    <div class="notification-icon-crm" :style="{ background: getIconBg(n.tipo) }">
                                        <i :class="getIconClass(n.tipo)" class="text-white"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="notification-title-crm" x-text="n.titulo"></span>
                                            <span class="notification-tag" :class="getTagClass(n.tipo)" x-text="getTagTexto(n.tipo)"></span>
                                        </div>
                                        <div class="notification-message-crm" x-text="n.mensagem"></div>
                                        <div class="notification-time-crm" x-text="formatarTempo(n.created_at)"></div>
                                    </div>
                                </div>
                            </div>
                        </template>
                        <div x-show="notificacoes.length === 0" class="empty-notifications-crm">
                            <i class="fa-regular fa-bell-slash text-4xl text-slate-300 mb-3 block"></i>
                            <p class="text-sm font-medium">Nenhuma notificação</p>
                            <p class="text-xs mt-1">Você está em dia com seus clientes!</p>
                        </div>
                    </div>
                </div>
            </div>

            
            <button onclick="abrirModalCliente()" class="px-5 py-2.5 bg-emerald-500 text-white rounded-xl font-bold hover:bg-emerald-600 transition-all shadow-lg flex items-center gap-2">
                <i class="fa-solid fa-plus-circle"></i> Novo Cliente
            </button>
            <button onclick="exportarCSV()" class="px-4 py-2.5 bg-slate-600 text-white rounded-xl font-bold hover:bg-slate-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-download"></i> Exportar
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mt-8">
        <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
            <div class="text-2xl font-black text-white" x-text="stats.total">0</div>
            <div class="text-xs text-slate-300 uppercase tracking-wide">Total Clientes</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
            <div class="text-2xl font-black text-emerald-400" x-text="stats.fechados">0</div>
            <div class="text-xs text-slate-300 uppercase tracking-wide">Fechados</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
            <div class="text-2xl font-black text-amber-400" x-text="stats.emNegociacao">0</div>
            <div class="text-xs text-slate-300 uppercase tracking-wide">Em Negociação</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
            <div class="text-2xl font-black text-blue-400" x-text="stats.pipelineValue">R$ 0</div>
            <div class="text-xs text-slate-300 uppercase tracking-wide">Pipeline</div>
        </div>
        <div class="bg-white/10 backdrop-blur rounded-xl p-3 text-center">
            <div class="text-2xl font-black text-purple-400" x-text="stats.conversao + '%'">0%</div>
            <div class="text-xs text-slate-300 uppercase tracking-wide">Conversão</div>
        </div>
    </div>
</div>


<!-- VIEW TABELA -->
<div>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
      <!-- FILTROS AVANÇADOS MODERNOS -->
<!-- FILTROS AVANÇADOS MODERNOS -->
<div class="bg-gradient-to-r from-slate-50 to-slate-100/50 p-4 border-b border-slate-200">

    <!-- ================================================================
    LINHA 1: BUSCA PRINCIPAL + BOTÕES
    ================================================================ -->
    <div class="flex flex-col md:flex-row gap-3 mb-3">
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i class="fa-solid fa-search text-slate-400"></i>
            </div>
            <input type="text" x-model="filtros.busca" @input="buscarComDebounce()" 
            placeholder="🔍 Buscar por nome, empresa, CNPJ, telefone..." 
            class="w-full pl-10 pr-4 py-3 bg-white border-2 border-slate-200 rounded-2xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all text-sm shadow-sm">
        </div>
        <button @click="limparFiltros()" class="px-5 py-3 bg-slate-200 hover:bg-slate-300 rounded-2xl text-sm font-bold text-slate-600 transition-all flex items-center gap-2 whitespace-nowrap">
            <i class="fa-solid fa-eraser"></i> Limpar
        </button>
        <button @click="carregarClientes()" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-emerald-600 hover:from-emerald-600 hover:to-emerald-700 rounded-2xl text-sm font-bold text-white transition-all shadow-md flex items-center gap-2 whitespace-nowrap">
            <i class="fa-solid fa-magnifying-glass"></i> Buscar
        </button>
    </div>
    
    <!-- ================================================================
LINHA 2: FILTROS ORGANIZADOS COM LABELS E MÚLTIPLA SELEÇÃO
=============================================================== -->
<div class="flex flex-wrap items-end gap-3">

    <!-- ============================================================
    1. TIPO DE PERÍODO (COMPRA / CADASTRO)
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            📌 Tipo de Período
        </label>
        <div class="flex items-center gap-1 bg-white border-2 border-slate-200 rounded-xl px-2 py-1">
            <button @click="filtros.tipo_periodo = 'compra'; carregarClientes()" 
            class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
            :class="filtros.tipo_periodo === 'compra' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
            <i class="fa-solid fa-shopping-cart mr-1"></i> Compra
        </button>
        <button @click="filtros.tipo_periodo = 'cadastro'; carregarClientes()" 
        class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all"
        :class="filtros.tipo_periodo === 'cadastro' ? 'bg-emerald-500 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'">
        <i class="fa-solid fa-user-plus mr-1"></i> Cadastro
    </button>
</div>
</div>

    <!-- ============================================================
    2. PERÍODO
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            📅 Período
        </label>
        <div class="relative">
            <select x-model="filtros.periodo" @change="carregarClientes()" 
            class="appearance-none px-4 py-2 pr-10 bg-white border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all cursor-pointer min-w-[150px]">
            <option value="">📋 Todos os períodos</option>
            <option value="hoje">📅 Hoje</option>
            <option value="7dias">📅 Últimos 7 dias</option>
            <option value="15dias">📅 Últimos 15 dias</option>
            <option value="30dias">📅 Últimos 30 dias</option>
            <option value="60dias">📅 Últimos 60 dias</option>
            <option value="90dias">📅 Últimos 90 dias</option>
            <option value="personalizado">📆 Personalizado</option>
        </select>
        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
            <i class="fa-solid fa-chevron-down text-slate-400 text-xs"></i>
        </div>
    </div>
</div>

    <!-- ============================================================
    DATAS PERSONALIZADAS
    ============================================================ -->
    <div x-show="filtros.periodo === 'personalizado'" 
    x-transition.duration.200ms 
    class="flex items-center gap-2 bg-white border-2 border-indigo-200 rounded-xl px-3 py-1.5 shadow-sm">
    <span class="text-xs text-slate-400 font-medium">📅 De:</span>
    <input type="date" x-model="filtros.data_inicio" 
    class="px-2 py-1 border border-indigo-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-200 transition-all w-32">
    <span class="text-xs text-slate-400 font-medium">📅 Até:</span>
    <input type="date" x-model="filtros.data_fim" 
    class="px-2 py-1 border border-indigo-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-200 transition-all w-32">
    <button @click="aplicarDatasPersonalizadas()" 
    class="px-3 py-1 bg-indigo-500 text-white rounded-lg text-xs font-bold hover:bg-indigo-600 transition-all">
    ✅ Aplicar
</button>
<button @click="limparDatasPersonalizadas()" 
class="px-2 py-1 text-xs text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-all" 
title="Limpar datas">
<i class="fa-solid fa-times"></i>
</button>
</div>

    <!-- ============================================================
    3. ORIGEM (CRM/ERP/AMBOS) - MÚLTIPLA SELEÇÃO
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            🌐 Origem
        </label>
        <div class="relative">
            <button @click="toggleDropdownOrigem()" 
            class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all cursor-pointer hover:bg-slate-50 min-w-[140px]">
            <i class="fa-solid fa-layer-group text-slate-400"></i>
            <span class="text-slate-600" x-text="textoFiltroOrigem()">🌐 Origens</span>
            <i class="fa-solid fa-chevron-down text-slate-400 text-xs" :class="{'rotate-180': dropdownOrigemAberto}"></i>
            <span x-show="origensSelecionadasCount > 0" 
              class="ml-1 px-1.5 py-0.5 bg-indigo-500 text-white rounded-full text-[10px] font-bold min-w-[18px] text-center"
              x-text="origensSelecionadasCount"></span>
          </button>

          <div x-show="dropdownOrigemAberto" 
          @click.outside="dropdownOrigemAberto = false"
          x-transition.duration.200ms
          class="absolute top-full left-0 mt-2 w-64 bg-white border-2 border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
          <div class="p-3 border-b border-slate-100">
            <span class="text-xs font-bold text-slate-400 uppercase">Selecione as origens</span>
            <button @click="selecionarTodasOrigens()" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Selecionar todas</button>
            <button @click="limparOrigens()" class="ml-2 text-xs text-rose-500 hover:text-rose-700 font-medium">Limpar</button>
        </div>
        <div class="p-2 space-y-1">
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origens" value="APENAS_CRM" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">💾 Apenas CRM</span>
                <span class="ml-auto text-xs text-slate-400">Clientes só no CRM</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origens" value="APENAS_ERP" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🏭 Apenas ERP</span>
                <span class="ml-auto text-xs text-slate-400">Clientes só no ERP</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origens" value="AMBOS" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🔄 Ambos</span>
                <span class="ml-auto text-xs text-slate-400">Cliente em ambos</span>
            </label>
        </div>
        <div class="p-2 border-t border-slate-100 flex justify-end gap-2">
            <button @click="dropdownOrigemAberto = false" 
            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 rounded-lg text-sm font-medium transition-colors">
            Fechar
        </button>
        <button @click="aplicarFiltroOrigem()" 
        class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium transition-colors">
        Aplicar
    </button>
</div>
</div>
</div>
</div>

    <!-- ============================================================
    4. STATUS - MÚLTIPLA SELEÇÃO
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            📊 Status
        </label>
        <div class="relative">
            <button @click="toggleDropdownStatus()" 
            class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all cursor-pointer hover:bg-slate-50 min-w-[140px]">
            <i class="fa-solid fa-circle text-slate-400"></i>
            <span class="text-slate-600" x-text="textoFiltroStatus()">📊 Status</span>
            <i class="fa-solid fa-chevron-down text-slate-400 text-xs" :class="{'rotate-180': dropdownStatusAberto}"></i>
            <span x-show="statusSelecionadosCount > 0" 
              class="ml-1 px-1.5 py-0.5 bg-indigo-500 text-white rounded-full text-[10px] font-bold min-w-[18px] text-center"
              x-text="statusSelecionadosCount"></span>
          </button>

          <div x-show="dropdownStatusAberto" 
          @click.outside="dropdownStatusAberto = false"
          x-transition.duration.200ms
          class="absolute top-full left-0 mt-2 w-64 bg-white border-2 border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
          <div class="p-3 border-b border-slate-100">
            <span class="text-xs font-bold text-slate-400 uppercase">Selecione os status</span>
            <button @click="selecionarTodosStatus()" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Selecionar todos</button>
            <button @click="limparStatus()" class="ml-2 text-xs text-rose-500 hover:text-rose-700 font-medium">Limpar</button>
        </div>
        <div class="p-2 space-y-1">
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.status_list" value="Novo" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🆕 Novo</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.status_list" value="Qualificado" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">⭐ Qualificado</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.status_list" value="Proposta" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">📄 Proposta</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.status_list" value="Fechado" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">✅ Fechado</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.status_list" value="Perdido" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">❌ Perdido</span>
            </label>
        </div>
        <div class="p-2 border-t border-slate-100 flex justify-end gap-2">
            <button @click="dropdownStatusAberto = false" 
            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 rounded-lg text-sm font-medium transition-colors">
            Fechar
        </button>
        <button @click="aplicarFiltroStatus()" 
        class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium transition-colors">
        Aplicar
    </button>
</div>
</div>
</div>
</div>

    <!-- ============================================================
    5. ORIGEM DO CLIENTE - MÚLTIPLA SELEÇÃO
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            📌 Origem Cliente
        </label>
        <div class="relative">
            <button @click="toggleDropdownOrigemCliente()" 
            class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all cursor-pointer hover:bg-slate-50 min-w-[150px]">
            <i class="fa-solid fa-globe text-slate-400"></i>
            <span class="text-slate-600" x-text="textoFiltroOrigemCliente()">📌 Origem</span>
            <i class="fa-solid fa-chevron-down text-slate-400 text-xs" :class="{'rotate-180': dropdownOrigemClienteAberto}"></i>
            <span x-show="origemClienteSelecionadasCount > 0" 
              class="ml-1 px-1.5 py-0.5 bg-indigo-500 text-white rounded-full text-[10px] font-bold min-w-[18px] text-center"
              x-text="origemClienteSelecionadasCount"></span>
          </button>

          <div x-show="dropdownOrigemClienteAberto" 
          @click.outside="dropdownOrigemClienteAberto = false"
          x-transition.duration.200ms
          class="absolute top-full left-0 mt-2 w-72 bg-white border-2 border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
          <div class="p-3 border-b border-slate-100">
            <span class="text-xs font-bold text-slate-400 uppercase">Selecione as origens</span>
            <button @click="selecionarTodasOrigemCliente()" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Selecionar todas</button>
            <button @click="limparOrigemCliente()" class="ml-2 text-xs text-rose-500 hover:text-rose-700 font-medium">Limpar</button>
        </div>
        <div class="p-2 space-y-1 max-h-48 overflow-y-auto">
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="Site" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🌐 Site</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="WhatsApp" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">💬 WhatsApp</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="Instagram" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">📷 Instagram</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="Bio do Instagram" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">📷 Bio do Instagram</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="Facebook" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">📘 Facebook</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="LandPage" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">📄 LandPage</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="Indicação" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">👥 Indicação</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.origem_cliente_list" value="Outros" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">📌 Outros</span>
            </label>
        </div>
        <div class="p-2 border-t border-slate-100 flex justify-end gap-2">
            <button @click="dropdownOrigemClienteAberto = false" 
            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 rounded-lg text-sm font-medium transition-colors">
            Fechar
        </button>
        <button @click="aplicarFiltroOrigemCliente()" 
        class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium transition-colors">
        Aplicar
    </button>
</div>
</div>
</div>
</div>

    <!-- ============================================================
    6. TERMÔMETRO - MÚLTIPLA SELEÇÃO
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            🌡️ Termômetro
        </label>
        <div class="relative">
            <button @click="toggleDropdownTermometro()" 
            class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all cursor-pointer hover:bg-slate-50 min-w-[140px]">
            <i class="fa-solid fa-temperature-high text-slate-400"></i>
            <span class="text-slate-600" x-text="textoFiltroTermometro()">🌡️ Termômetro</span>
            <i class="fa-solid fa-chevron-down text-slate-400 text-xs" :class="{'rotate-180': dropdownTermometroAberto}"></i>
            <span x-show="termometroSelecionadosCount > 0" 
              class="ml-1 px-1.5 py-0.5 bg-indigo-500 text-white rounded-full text-[10px] font-bold min-w-[18px] text-center"
              x-text="termometroSelecionadosCount"></span>
          </button>

          <div x-show="dropdownTermometroAberto" 
          @click.outside="dropdownTermometroAberto = false"
          x-transition.duration.200ms
          class="absolute top-full left-0 mt-2 w-56 bg-white border-2 border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
          <div class="p-3 border-b border-slate-100">
            <span class="text-xs font-bold text-slate-400 uppercase">Selecione os termômetros</span>
            <button @click="selecionarTodosTermometro()" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Selecionar todos</button>
            <button @click="limparTermometro()" class="ml-2 text-xs text-rose-500 hover:text-rose-700 font-medium">Limpar</button>
        </div>
        <div class="p-2 space-y-1">
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.termometro_list" value="Frio" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🥶 Frio</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.termometro_list" value="Morno" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🌤️ Morno</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.termometro_list" value="Quente" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">🔥 Quente</span>
            </label>
        </div>
        <div class="p-2 border-t border-slate-100 flex justify-end gap-2">
            <button @click="dropdownTermometroAberto = false" 
            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 rounded-lg text-sm font-medium transition-colors">
            Fechar
        </button>
        <button @click="aplicarFiltroTermometro()" 
        class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium transition-colors">
        Aplicar
    </button>
</div>
</div>
</div>
</div>

    <!-- ============================================================
    7. STATUS DE COMPRA - MÚLTIPLA SELEÇÃO
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            🛒 Status Compra
        </label>
        <div class="relative">
            <button @click="toggleDropdownCompra()" 
            class="flex items-center gap-2 px-4 py-2 bg-white border-2 border-slate-200 rounded-xl text-sm font-medium focus:border-indigo-400 focus:ring-2 focus:ring-indigo-200 transition-all cursor-pointer hover:bg-slate-50 min-w-[150px]">
            <i class="fa-solid fa-shopping-cart text-slate-400"></i>
            <span class="text-slate-600" x-text="textoFiltroCompra()">🛒 Status</span>
            <i class="fa-solid fa-chevron-down text-slate-400 text-xs" :class="{'rotate-180': dropdownCompraAberto}"></i>
            <span x-show="compraSelecionadosCount > 0" 
              class="ml-1 px-1.5 py-0.5 bg-indigo-500 text-white rounded-full text-[10px] font-bold min-w-[18px] text-center"
              x-text="compraSelecionadosCount"></span>
          </button>

          <div x-show="dropdownCompraAberto" 
          @click.outside="dropdownCompraAberto = false"
          x-transition.duration.200ms
          class="absolute top-full left-0 mt-2 w-56 bg-white border-2 border-slate-200 rounded-xl shadow-lg z-50 overflow-hidden">
          <div class="p-3 border-b border-slate-100">
            <span class="text-xs font-bold text-slate-400 uppercase">Selecione o status</span>
            <button @click="selecionarTodosCompra()" class="ml-2 text-xs text-indigo-600 hover:text-indigo-800 font-medium">Selecionar todos</button>
            <button @click="limparCompra()" class="ml-2 text-xs text-rose-500 hover:text-rose-700 font-medium">Limpar</button>
        </div>
        <div class="p-2 space-y-1">
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.compra_list" value="sim" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">✅ Já comprou</span>
            </label>
            <label class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 rounded-lg cursor-pointer transition-colors">
                <input type="checkbox" x-model="filtros.compra_list" value="nao" 
                class="w-4 h-4 text-indigo-600 focus:ring-indigo-500 border-slate-300 rounded">
                <span class="text-sm font-medium text-slate-700">❌ Nunca comprou</span>
            </label>
        </div>
        <div class="p-2 border-t border-slate-100 flex justify-end gap-2">
            <button @click="dropdownCompraAberto = false" 
            class="px-4 py-1.5 bg-slate-200 hover:bg-slate-300 rounded-lg text-sm font-medium transition-colors">
            Fechar
        </button>
        <button @click="aplicarFiltroCompra()" 
        class="px-4 py-1.5 bg-indigo-500 hover:bg-indigo-600 text-white rounded-lg text-sm font-medium transition-colors">
        Aplicar
    </button>
</div>
</div>
</div>
</div>

    <!-- ============================================================
    BOTÃO EXPORTAR
    ============================================================ -->
    <div>
        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">
            📤 Ações
        </label>
        <button onclick="exportarCSV()" 
        class="px-4 py-2 bg-emerald-100 hover:bg-emerald-200 text-emerald-700 rounded-xl text-sm font-bold transition-all flex items-center gap-2 w-full justify-center">
        <i class="fa-solid fa-file-export"></i> Exportar
    </button>
</div>

</div>

    <!-- ================================================================
    LINHA 3: INDICADOR DE FILTROS ATIVOS
    ================================================================ -->
    <div class="flex flex-wrap gap-2 mt-3" x-show="filtrosAtivos()">
        <template x-for="(filtro, key) in filtrosAtivosLista()" :key="key">
            <span class="inline-flex items-center gap-1 px-3 py-1 bg-indigo-100 text-indigo-700 rounded-full text-xs font-bold">
                <span x-text="filtro.label"></span>
                <button @click="removerFiltro(key)" class="hover:text-indigo-900">
                    <i class="fa-solid fa-times text-xs"></i>
                </button>
            </span>
        </template>
    </div>
</div>

<div class="overflow-x-auto">
    <table class="w-full text-sm" id="tabelaClientes">
        <thead class="bg-slate-50 border-b">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Termômetro</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Origem</th>
                <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Valor</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Último Contato</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status Compra</th>
                <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody id="clientesTableBody">
            <tr><td colspan="8" class="text-center py-8 text-slate-400">Carregando...</td></tr>
        </tbody>
    </table>
</div>
<div class="p-4 border-t border-slate-100 flex justify-between items-center">
    <span class="text-sm text-slate-400" x-text="`${pagination.total} clientes • Página ${pagination.atual} de ${pagination.totalPaginas}`"></span>
    <div class="flex gap-2">
        <button @click="mudarPagina(pagination.atual - 1)" :disabled="pagination.atual === 1" class="px-3 py-1 border rounded-lg disabled:opacity-50">Anterior</button>
        <button @click="mudarPagina(pagination.atual + 1)" :disabled="pagination.atual === pagination.totalPaginas" class="px-3 py-1 border rounded-lg">Próximo</button>
    </div>
</div>
</div>
</div>

<!-- SEÇÃO DE AGENDA PREMIUM -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-8">
    <div class="lg:col-span-2 bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-4 flex justify-between items-center">
            <div>
                <h3 class="font-bold text-white text-lg">
                    <i class="fa-regular fa-calendar-alt mr-2"></i>Agenda de Compromissos
                </h3>
                <p class="text-blue-100 text-xs mt-0.5">Clique em qualquer horário para agendar</p>
            </div>
            <div class="flex gap-2">
                <button onclick="filtrarAgenda('todos')" class="filter-agenda-btn active px-3 py-1.5 rounded-lg text-xs font-bold transition-all" data-filter="todos">
                    <i class="fa-solid fa-list mr-1"></i>Todos
                </button>
                <button onclick="filtrarAgenda('reuniao')" class="filter-agenda-btn px-3 py-1.5 rounded-lg text-xs font-bold transition-all" data-filter="reuniao">
                    <i class="fa-solid fa-users mr-1"></i>Reunião
                </button>
                <button onclick="filtrarAgenda('ligacao')" class="filter-agenda-btn px-3 py-1.5 rounded-lg text-xs font-bold transition-all" data-filter="ligacao">
                    <i class="fa-solid fa-phone mr-1"></i>Ligação
                </button>
                <button onclick="filtrarAgenda('visita')" class="filter-agenda-btn px-3 py-1.5 rounded-lg text-xs font-bold transition-all" data-filter="visita">
                    <i class="fa-solid fa-building mr-1"></i>Visita
                </button>
            </div>
        </div>
        <div id="calendarioAgenda" class="p-4" style="min-height: 550px;"></div>
    </div>

    <div class="space-y-4">
        <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl p-4 shadow-lg cursor-pointer hover:shadow-xl transition-all" onclick="abrirModalAgendamento()">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-emerald-100 text-xs uppercase tracking-wider">Novo Compromisso</p>
                    <p class="text-white font-bold text-sm mt-0.5">Agendar agora</p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fa-solid fa-plus text-white text-lg"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-lg border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-purple-500 to-pink-500 px-4 py-3">
                <h4 class="font-bold text-white text-sm flex items-center gap-2">
                    <i class="fa-regular fa-clock"></i>
                    <span>Próximos Compromissos</span>
                    <span class="ml-auto bg-white/20 px-2 py-0.5 rounded-full text-xs" x-text="proximosCompromissos.length"></span>
                </h4>
            </div>
            <div class="divide-y divide-slate-100 max-h-96 overflow-y-auto">
                <template x-for="comp in proximosCompromissos" :key="comp.id">
                    <div class="p-4 hover:bg-slate-50 cursor-pointer transition-all" @click="editarCliente(comp.id_cliente)">
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 shadow-sm"
                            :class="comp.tipo === 'reuniao' ? 'bg-blue-100 text-blue-600' : 
                            comp.tipo === 'ligacao' ? 'bg-amber-100 text-amber-600' : 
                            comp.tipo === 'visita' ? 'bg-purple-100 text-purple-600' : 
                            'bg-emerald-100 text-emerald-600'">
                            <i class="text-xl" :class="comp.tipo === 'reuniao' ? 'fa-regular fa-calendar' : 
                            comp.tipo === 'ligacao' ? 'fa-solid fa-phone' : 
                            comp.tipo === 'visita' ? 'fa-solid fa-building' : 
                            'fa-brands fa-whatsapp'"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex justify-between items-start">
                                <div>
                                    <p class="font-bold text-slate-800 text-sm truncate" x-text="comp.cliente_nome"></p>
                                    <p class="text-xs text-slate-400 truncate" x-text="comp.cliente_empresa || 'Cliente'"></p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full" 
                                :class="getProximidadeClass(comp.data_hora)" 
                                x-text="formatarDataProxima(comp.data_hora)"></span>
                            </div>
                            <p class="text-xs font-medium text-slate-700 mt-1.5 line-clamp-1" x-text="comp.titulo || comp.tipo"></p>
                            <div class="flex items-center gap-3 mt-2">
                                <div class="flex items-center gap-1">
                                    <i class="fa-regular fa-clock text-slate-300 text-[10px]"></i>
                                    <span class="text-xs text-slate-500" x-text="formatarHora(comp.data_hora)"></span>
                                </div>
                                <template x-if="comp.data_hora_fim">
                                    <div class="flex items-center gap-1">
                                        <i class="fa-regular fa-hourglass-half text-slate-300 text-[10px]"></i>
                                        <span class="text-xs text-slate-500" x-text="calcularDuracao(comp.data_hora, comp.data_hora_fim)"></span>
                                    </div>
                                </template>
                            </div>
                            <div x-show="comp.descricao" class="mt-2 p-2 bg-slate-50 rounded-lg">
                                <p class="text-xs text-slate-500 line-clamp-2" x-text="comp.descricao"></p>
                            </div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="text-[10px] px-2 py-0.5 rounded-full"
                                :class="comp.status === 'agendado' ? 'bg-blue-100 text-blue-600' : 
                                comp.status === 'concluido' ? 'bg-emerald-100 text-emerald-600' : 
                                'bg-rose-100 text-rose-600'"
                                x-text="comp.status === 'agendado' ? '📅 Agendado' : 
                                comp.status === 'concluido' ? '✅ Concluído' : '❌ Cancelado'">
                            </span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-500" 
                            x-text="comp.tipo === 'reuniao' ? 'Reunião' : 
                            comp.tipo === 'ligacao' ? 'Ligação' : 
                            comp.tipo === 'visita' ? 'Visita' : 
                            comp.tipo === 'whatsapp' ? 'WhatsApp' : 
                            comp.tipo === 'email' ? 'E-mail' : 'Outro'">
                        </span>
                    </div>
                </div>
                <div class="flex flex-col gap-1">
                    <button @click.stop="concluirCompromisso(comp.id)" class="p-1.5 rounded-lg bg-emerald-100 text-emerald-600 hover:bg-emerald-200 transition-all" title="Concluir compromisso">
                        <i class="fa-solid fa-check text-xs"></i>
                    </button>
                    <button @click.stop="abrirEdicaoCompromisso(comp.id, comp)" class="p-1.5 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all" title="Editar compromisso">
                        <i class="fa-solid fa-pen text-xs"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
    <div x-show="proximosCompromissos.length === 0" class="p-8 text-center">
        <i class="fa-regular fa-calendar-check text-4xl text-slate-300 mb-2 block"></i>
        <p class="text-sm text-slate-400">Nenhum compromisso agendado</p>
        <p class="text-xs text-slate-300 mt-1">Clique no botão acima para criar</p>
    </div>
</div>
</div>

<div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-2xl p-4">
    <div class="grid grid-cols-2 gap-3">
        <div class="text-center">
            <div class="text-2xl font-black text-white" x-text="stats.totalCompromissosMes || 0">0</div>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Este mês</p>
        </div>
        <div class="text-center">
            <div class="text-2xl font-black text-emerald-400" x-text="stats.concluidosMes || 0">0</div>
            <p class="text-[10px] text-slate-400 uppercase tracking-wider">Concluídos</p>
        </div>
    </div>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/pt-br.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/portal/assets/js/marketing-utils.js?v=<?= $version ?>"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

<script>
// ============================================================================
// VERIFICAR DEPENDÊNCIAS
// ============================================================================
    if (typeof MarketingUtils === 'undefined') {
        console.error('❌ MarketingUtils não carregado!');
        Swal.fire({
            title: 'Erro de carregamento',
            text: 'Recursos do marketing não carregaram corretamente. Recarregue a página.',
            icon: 'error',
            confirmButtonText: 'Recarregar'
        }).then(() => location.reload());
    }

// ============================================================================
// CRM PROFISSIONAL - COMPONENTE PRINCIPAL (ALPINE.JS)
// ============================================================================
    function crmProfissional() {

        return {
        // ====================================================================
        // ESTADO
        // ====================================================================
            clientesTabela: [],
            stats: {
                total: 0,
                fechados: 0,
                emNegociacao: 0,
                pipelineValue: 0,
                conversao: 0,
                totalCompromissosMes: 0,
                concluidosMes: 0
            },
            alertas: [],
            proximosCompromissos: [],
            lembretesHoje: [],
            filtros: {
                status: '',
                termometro: '',
                busca: '',
                origens: [],
                origem_cliente: '',
                status_list: [],           
                origem_cliente_list: [],  
                termometro_list: [],       
                compra_list: [],         
                periodo: '',
                ja_comprou: '',
                tipo_periodo: 'compra',
                data_inicio: '',
                data_fim: ''
            },
            dropdownOrigemAberto: false, 
            dropdownStatusAberto: false,          
            dropdownOrigemClienteAberto: false,    
            dropdownTermometroAberto: false,       
            dropdownCompraAberto: false,           
            pagination: {
                atual: 1,
                total: 0,
                totalPaginas: 1,
                limite: 10
            },
            viewMode: 'tabela',
            calendario: null,
            timeoutBusca: null,
            intervaloVerificacao: null,
            intervaloLembretes: null,
        // 🔥 NOVAS PROPRIEDADES PARA CONTROLE DE CONCORRÊNCIA
            _carregando: false,
            _inicializado: false,
            _clienteEncontrado: false,
            _clienteDestacado: false,

    // ====================================================================
// LIMPAR DATAS PERSONALIZADAS
// ====================================================================
            limparDatasPersonalizadas() {
                this.filtros.data_inicio = '';
                this.filtros.data_fim = '';
                this.filtros.periodo = '';
                this.carregarClientes();
            },
            // ====================================================================
// FUNÇÃO: APLICAR DATAS PERSONALIZADAS
// ====================================================================
            aplicarDatasPersonalizadas() {
                if (!this.filtros.data_inicio && !this.filtros.data_fim) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Atenção',
                        text: 'Selecione pelo menos uma data para aplicar o filtro.',
                        confirmButtonColor: '#274036'
                    });
                    return;
                }
                this.pagination.atual = 1;
                this.carregarClientes();
            },

        // ====================================================================
        // FUNÇÕES AUXILIARES DE FILTRO
        // ====================================================================
            filtrosAtivos() {
                const ativos = [];
                if (this.filtros.status) ativos.push('status');
                if (this.filtros.termometro) ativos.push('termometro');
                if (this.filtros.origens && this.filtros.origens.length > 0) ativos.push('origens');  
                if (this.filtros.ja_comprou) ativos.push('ja_comprou');
                if (this.filtros.periodo) ativos.push('periodo');
                if (this.filtros.busca) ativos.push('busca');
                return ativos.length > 0;
            },
            filtrosAtivosLista() {
                const lista = [];
                const labels = {
                    status: { label: 'Status: ' + (this.filtros.status_list ? this.filtros.status_list.join(', ') : ''), key: 'status' },
                    termometro: { label: 'Termômetro: ' + (this.filtros.termometro_list ? this.filtros.termometro_list.join(', ') : ''), key: 'termometro' },
                    origens: { label: this.textoFiltroOrigem(), key: 'origens' },
                    origem_cliente: { label: 'Origem Cliente: ' + (this.filtros.origem_cliente_list ? this.filtros.origem_cliente_list.join(', ') : ''), key: 'origem_cliente' },
                    compra: { label: this.textoFiltroCompra(), key: 'compra' },
                    periodo: { label: 'Período: ' + this.filtros.periodo, key: 'periodo' },
                    tipo_periodo: { label: 'Tipo: ' + (this.filtros.tipo_periodo === 'compra' ? 'Compra' : 'Cadastro'), key: 'tipo_periodo' },
                    busca: { label: 'Busca: ' + this.filtros.busca, key: 'busca' }
                };

                if (this.filtros.periodo === 'personalizado' && this.filtros.data_inicio) {
                    labels.data_periodo = {
                        label: `Data: ${this.filtros.data_inicio} ${this.filtros.data_fim ? 'até ' + this.filtros.data_fim : ''}`,
                        key: 'data_periodo'
                    };
                }

                Object.keys(labels).forEach(key => {
                    const val = this.filtros[key];
                    if (key === 'origens') {
                        if (this.filtros.origens && this.filtros.origens.length > 0) {
                            lista.push(labels[key]);
                        }
                    } else if (key === 'status' || key === 'termometro' || key === 'origem_cliente' || key === 'compra') {
                        if (this.filtros[key + '_list'] && this.filtros[key + '_list'].length > 0) {
                            lista.push(labels[key]);
                        }
                    } else if (val && val !== 'todos' && val !== '' && key !== 'tipo_periodo') {
                        lista.push(labels[key]);
                    } else if (key === 'tipo_periodo' && this.filtros.periodo && this.filtros.periodo !== '') {
                        lista.push(labels[key]);
                    }
                });
                return lista;
            },

            removerFiltro(key) {
                if (key === 'busca') {
                    this.filtros.busca = '';
                    this.carregarClientes();
                } else if (key === 'data_periodo') {
                    this.filtros.data_inicio = '';
                    this.filtros.data_fim = '';
                    this.filtros.periodo = '';
                    this.carregarClientes();
                } else if (key === 'origens') {
                    this.filtros.origens = [];
                    this.carregarClientes();
                } else {
                    this.filtros[key] = '';
                    this.carregarClientes();
                }
            },

        // ====================================================================
        // VERIFICAR PARÂMETROS DE BUSCA NA URL
        // ====================================================================
            verificarParametrosBusca() {
                const urlParams = new URLSearchParams(window.location.search);
                const buscar = urlParams.get('buscar');
                const origem = urlParams.get('origem');

                if (buscar) {
                    console.log('🔍 Buscando cliente por ID:', buscar);
                    this.filtros.busca = buscar;

                    if (origem) {
                        if (origem === 'APENAS_CRM') {
                            this.filtros.origens = ['APENAS_CRM'];
                        } else if (origem === 'APENAS_ERP') {
                            this.filtros.origens = ['APENAS_ERP'];
                        } else if (origem === 'AMBOS') {
                            this.filtros.origens = ['AMBOS'];
                        } else if (origem === 'CRM') {
                            this.filtros.origens = ['APENAS_CRM', 'AMBOS'];
                        }
                    }

                    this.pagination.atual = 1;
                    this.carregarClientes().then(() => {});

                    if (window.history && window.history.replaceState) {
                        const newUrl = window.location.pathname;
                        window.history.replaceState({}, document.title, newUrl);
                    }
                }
            },

        // ====================================================================
        // CARREGAR CLIENTE DOS DADOS DA URL
        // ====================================================================
            carregarClienteDaURL() {
                const urlParams = new URLSearchParams(window.location.search);
                const clienteData = urlParams.get('cliente_data');

                if (clienteData) {
                    try {
                        const cliente = JSON.parse(decodeURIComponent(clienteData));
                        console.log('📦 Cliente carregado da URL:', cliente);

                        this._clienteEncontrado = true;
                        this._clienteDestacado = false;
                        this.filtros.busca = cliente.nome || cliente.id;

                        this.clientesTabela = [{
                            id: cliente.id,
                            id_crm: cliente.id,
                            id_erp: cliente.id_erp || null,
                            nome: cliente.nome || '—',
                            empresa: cliente.empresa || '',
                            telefone: cliente.telefone || '',
                            email: cliente.email || '',
                            cidade: cliente.cidade || '',
                            uf: cliente.uf || '',
                            status: cliente.status || 'Novo',
                            termometro: cliente.termometro || 'Frio',
                            origem: cliente.origem || 'Site',
                            origem_dados: cliente.origem_dados || 'APENAS_CRM',
                            valor_negocio: cliente.valor_negocio || 0,
                            total_pedidos: cliente.total_pedidos || 0,
                            total_compras: cliente.total_compras || 0,
                            data_ultima_compra: cliente.data_ultima_compra || null,
                            ultima_interacao: cliente.ultima_interacao || null,
                            total_interacoes: cliente.total_interacoes || 0,
                            nome_vendedor: cliente.nome_vendedor || '',
                            cnpj_cpf: cliente.cnpj_cpf || '',
                            endereco: cliente.endereco || '',
                            numero: cliente.numero || '',
                            bairro: cliente.bairro || '',
                            cep: cliente.cep || '',
                            complemento: cliente.complemento || '',
                            observacoes: cliente.observacoes || '',
                            tags: cliente.tags || [],
                            meta_titulo: cliente.meta_titulo || '',
                            pode_acao: true
                        }];

                        this.pagination.total = 1;
                        this.pagination.totalPaginas = 1;
                        this.renderizarTabela();

                        setTimeout(() => {
                            this.aplicarDestaque(this.clientesTabela[0]);
                        }, 300);

                        return true;
                    } catch (e) {
                        console.error('❌ Erro ao carregar cliente da URL:', e);
                        this._clienteEncontrado = false;
                        return false;
                    }
                }
                return false;
            },

        // ====================================================================
        // DESTACAR CLIENTE NA TABELA
        // ====================================================================
            destacarCliente(busca) {
                let cliente = this.clientesTabela.find(c => 
                    c.id == busca || 
                    c.id_crm == busca || 
                    c.id_erp == busca ||
                    c.nome?.toLowerCase().includes(String(busca).toLowerCase()) ||
                    c.empresa?.toLowerCase().includes(String(busca).toLowerCase())
                    );

                if (!cliente && !isNaN(busca) && busca > 0) {
                    console.log('🔍 Cliente não encontrado na lista, tentando busca direta...');
                    this.buscarClientePorId(parseInt(busca));
                    return;
                }

                if (cliente) {
                    console.log('✅ Cliente encontrado, destacando:', cliente.nome);
                    this.aplicarDestaque(cliente);
                } else {
                    console.warn('⚠️ Cliente não encontrado');
                    Swal.fire({
                        icon: 'info',
                        title: 'Cliente não encontrado',
                        text: 'Verifique o ID ou ajuste os filtros',
                        timer: 3000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }
            },

        // ====================================================================
        // BUSCAR CLIENTE POR ID DIRETAMENTE
        // ====================================================================
            async buscarClientePorId(id) {
                try {
                    const token = this.getToken();
                    const resp = await fetch(`/v1/marketing/clientes/${id}`, {
                        headers: { 'Authorization': 'Bearer ' + token }
                    });

                    if (resp.ok) {
                        const data = await resp.json();
                        if (data && data.id) {
                            const cliente = {
                                id: data.id,
                                id_crm: data.id,
                                id_erp: data.cliforemp_id || null,
                                nome: data.nome || '—',
                                empresa: data.empresa || '',
                                telefone: data.telefone || '',
                                email: data.email || '',
                                cidade: data.cidade || '',
                                uf: data.uf || '',
                                status: data.status || 'Novo',
                                termometro: data.termometro || 'Frio',
                                origem: data.origem || 'Site',
                                origem_dados: 'APENAS_CRM',
                                valor_negocio: data.valor_negocio || 0,
                                total_pedidos: data.total_pedidos || 0,
                                total_compras: data.total_compras || 0,
                                data_ultima_compra: data.data_ultima_compra || null,
                                ultima_interacao: data.ultima_interacao || null,
                                total_interacoes: data.total_interacoes || 0,
                                nome_vendedor: data.nome_vendedor || '',
                                pode_acao: true
                            };

                            this.clientesTabela.unshift(cliente);
                            this.renderizarTabela();

                            setTimeout(() => {
                                this.aplicarDestaque(cliente);
                            }, 300);
                        }
                    }
                } catch (e) {
                    console.error('Erro ao buscar cliente por ID:', e);
                }
            },

        // ====================================================================
        // CARREGAR CLIENTES - PRINCIPAL (COM CONTROLE DE CONCORRÊNCIA)
        // ====================================================================
        // clientes.php - Função carregarClientes()

            async carregarClientes(manterFiltro = false) {
    // 🔥 EVITAR MÚLTIPLAS CHAMADAS CONCORRENTES
                if (this._carregando) {
                    console.log('⏳ Já está carregando, ignorando...');
                    return;
                }

                if (manterFiltro && this._clienteEncontrado) {
                    console.log('⏭️ Mantendo cliente encontrado, não recarregando');
                    return;
                }

                this._carregando = true;

                try {
        // ================================================================
        // 1. PREPARAR PARÂMETROS
        // ================================================================
                    const params = new URLSearchParams({
                        pagina: this.pagination.atual,
                        limite: this.pagination.limite,
                        status: this.filtros.status || '',
                        termometro: this.filtros.termometro || '',
                        busca: this.filtros.busca || '',
                        origem: this.filtros.origens ? this.filtros.origens.join(',') : '',
                        origem_cliente: this.filtros.origem_cliente || '',
                        periodo: this.filtros.periodo || '',
                        ja_comprou: this.filtros.ja_comprou || '',
                        tipo_periodo: this.filtros.tipo_periodo || 'compra',
                        data_inicio: this.filtros.data_inicio || '',
                        data_fim: this.filtros.data_fim || ''
                    });

        // ================================================================
        // 2. 🔥 BUSCA INTELIGENTE - NÃO ENVIA busca_id separadamente
        //    A função V2 já faz a busca inteligente no backend
        // ================================================================
        // REMOVEMOS a lógica de busca_id separada
        // Agora tudo vai como "busca" e o backend decide como processar

                    const url = `/v1/marketing/clientes/consulta-otimizado?${params}`;
                    console.log('🔍 Buscando clientes:', url);

        // ================================================================
        // 3. FAZER REQUISIÇÃO
        // ================================================================
                    const resp = await this.fetchWithAuth(url);
                    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

                    const data = await resp.json();
                    if (!data.success) throw new Error(data.error || 'Erro ao carregar clientes');

        // ================================================================
        // 4. PROCESSAR RESULTADOS
        // ================================================================
                    this.clientesTabela = (data.clientes || []).map(c => ({
                        id: c.id_crm || c.id_erp,
                        id_crm: c.id_crm,
                        id_erp: c.id_erp,
                        uid: `crm_${c.id_crm || 0}_erp_${c.id_erp || 0}`,
                        nome: c.nome || '—',
                        empresa: c.empresa || '',
                        telefone: c.telefone || '',
                        email: c.email || '',
                        cidade: c.cidade || '',
                        uf: c.uf || '',
                        status: c.status_crm || 'Novo',
                        termometro: c.termometro || 'Frio',
                        origem: c.origem_crm || (c.origem_dados === 'APENAS_ERP' ? 'ERP' : 'Site'),
                        valor_negocio: c.valor_negocio || 0,
                        data_cadastro: c.data_cadastro_crm || c.data_cadastro_erp,
                        ultima_interacao: c.ultima_interacao,
                        total_interacoes: c.total_interacoes || 0,
                        nome_vendedor: c.nome_vendedor,
                        origem_dados: c.origem_dados,
                        pode_acao: c.id_crm !== null,
                        total_pedidos: c.total_pedidos || 0,
                        total_compras: c.total_compras || 0,
                        data_ultima_compra: c.data_ultima_compra,
                        cnpj_cpf: c.cnpj_cpf || ''
                    }));

                    this.pagination.total = data.total || 0;
                    this.pagination.totalPaginas = data.total_paginas || 1;
                    this.renderizarTabela();

        // ================================================================
        // 5. SE BUSCOU POR ID, DESTACAR O CLIENTE
        // ================================================================
                    if (data.busca && data.busca.tipo === 'ID' && data.clientes && data.clientes.length > 0) {
                        const cliente = data.clientes[0];
                        setTimeout(() => {
                            this.aplicarDestaque(cliente);
                        }, 500);
                    }

        // Mostrar info da busca no console
                    if (data.busca) {
                        console.log(`🔍 Busca: "${data.busca.original}" → Tipo: ${data.busca.tipo} → Encontrados: ${data.total}`);
                    }

                } catch (e) {
                    console.error('❌ Erro ao carregar clientes:', e);
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro ao carregar clientes',
                        text: e.message,
                        confirmButtonText: 'Tentar novamente',
                        confirmButtonColor: '#10b981'
                    }).then(() => this.carregarClientes());
                } finally {
                    this._carregando = false;
                }
            },

        // ====================================================================
        // APLICAR DESTAQUE AO CLIENTE
        // ====================================================================
            aplicarDestaque(cliente) {
                const linhas = document.querySelectorAll('#clientesTableBody tr');
                let encontrou = false;

                if (this._clienteDestacado) return;
                this._clienteDestacado = true;

                linhas.forEach(linha => {
                    const idCliente = linha.getAttribute('data-cliente-id');
                    if (idCliente == cliente.id || idCliente == cliente.id_crm || idCliente == cliente.id_erp) {
                        encontrou = true;
                        linhas.forEach(l => {
                            l.style.backgroundColor = '';
                            l.style.borderLeft = '';
                            l.style.boxShadow = '';
                        });
                        linha.style.backgroundColor = '#f0fdf4';
                        linha.style.borderLeft = '4px solid #10b981';
                        linha.style.boxShadow = '0 2px 8px rgba(16, 185, 129, 0.2)';
                        linha.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        this._clienteEncontrado = true;
                    }
                });

                if (!encontrou) {
                    console.warn('⚠️ Cliente não encontrado na tabela visualizada');
                    Swal.fire({
                        icon: 'info',
                        title: 'Cliente não encontrado',
                        text: 'Verifique se o cliente está na lista atual',
                        timer: 2000,
                        showConfirmButton: false,
                        toast: true,
                        position: 'top-end'
                    });
                }

                setTimeout(() => {
                    this._clienteDestacado = false;
                }, 5000);
            },

        // ====================================================================
        // FUNÇÕES DE CONTROLE DE ORIGEM (MÚLTIPLA SELEÇÃO)
        // ====================================================================
            toggleDropdownOrigem() {
                this.dropdownOrigemAberto = !this.dropdownOrigemAberto;
            },
            toggleDropdownStatus() {
                this.dropdownStatusAberto = !this.dropdownStatusAberto;
            },
            toggleDropdownOrigemCliente() {
                this.dropdownOrigemClienteAberto = !this.dropdownOrigemClienteAberto;
            },
            toggleDropdownTermometro() {
                this.dropdownTermometroAberto = !this.dropdownTermometroAberto;
            },
            toggleDropdownCompra() {
                this.dropdownCompraAberto = !this.dropdownCompraAberto;
            },

            get origensSelecionadasCount() {
                return this.filtros.origens ? this.filtros.origens.length : 0;
            },
            get statusSelecionadosCount() {
                return this.filtros.status_list ? this.filtros.status_list.length : 0;
            },
            get origemClienteSelecionadasCount() {
                return this.filtros.origem_cliente_list ? this.filtros.origem_cliente_list.length : 0;
            },
            get termometroSelecionadosCount() {
                return this.filtros.termometro_list ? this.filtros.termometro_list.length : 0;
            },
            get compraSelecionadosCount() {
                return this.filtros.compra_list ? this.filtros.compra_list.length : 0;
            },

            textoFiltroOrigem() {
                if (!this.filtros.origens || this.filtros.origens.length === 0) {
                    return '🌐 Todas Origens';
                }
                if (this.filtros.origens.length === 1) {
                    const labels = {
                        'APENAS_CRM': '💾 CRM',
                        'APENAS_ERP': '🏭 ERP',
                        'AMBOS': '🔄 Ambos'
                    };
                    return labels[this.filtros.origens[0]] || this.filtros.origens[0];
                }
                return `🌐 ${this.filtros.origens.length} origens`;
            },

            textoFiltroStatus() {
                if (!this.filtros.status_list || this.filtros.status_list.length === 0) {
                    return '📊 Todos Status';
                }
                if (this.filtros.status_list.length === 1) {
                    const labels = {
                        'Novo': '🆕 Novo',
                        'Qualificado': '⭐ Qualificado',
                        'Proposta': '📄 Proposta',
                        'Fechado': '✅ Fechado',
                        'Perdido': '❌ Perdido'
                    };
                    return labels[this.filtros.status_list[0]] || this.filtros.status_list[0];
                }
                return `📊 ${this.filtros.status_list.length} status`;
            },
            textoFiltroOrigemCliente() {
                if (!this.filtros.origem_cliente_list || this.filtros.origem_cliente_list.length === 0) {
                    return '📌 Todas Origens';
                }
                if (this.filtros.origem_cliente_list.length === 1) {
                    const labels = {
                        'Site': '🌐 Site',
                        'WhatsApp': '💬 WhatsApp',
                        'Instagram': '📷 Instagram',
                        'Bio do Instagram': '📷 Bio',
                        'Facebook': '📘 Facebook',
                        'LandPage': '📄 LandPage',
                        'Indicação': '👥 Indicação',
                        'Outros': '📌 Outros'
                    };
                    return labels[this.filtros.origem_cliente_list[0]] || this.filtros.origem_cliente_list[0];
                }
                return `📌 ${this.filtros.origem_cliente_list.length} origens`;
            },
            textoFiltroTermometro() {
                if (!this.filtros.termometro_list || this.filtros.termometro_list.length === 0) {
                    return '🌡️ Todos';
                }
                if (this.filtros.termometro_list.length === 1) {
                    const labels = {
                        'Frio': '🥶 Frio',
                        'Morno': '🌤️ Morno',
                        'Quente': '🔥 Quente'
                    };
                    return labels[this.filtros.termometro_list[0]] || this.filtros.termometro_list[0];
                }
                return `🌡️ ${this.filtros.termometro_list.length} selecionados`;
            },
            textoFiltroCompra() {
                if (!this.filtros.compra_list || this.filtros.compra_list.length === 0) {
                    return '🛒 Todos';
                }
                if (this.filtros.compra_list.length === 1) {
                    return this.filtros.compra_list[0] === 'sim' ? '✅ Já comprou' : '❌ Nunca comprou';
                }
                return `🛒 ${this.filtros.compra_list.length} selecionados`;
            },

            selecionarTodasOrigens() {
                this.filtros.origens = ['APENAS_CRM', 'APENAS_ERP', 'AMBOS'];
            },

            selecionarTodosStatus() {
                this.filtros.status_list = ['Novo', 'Qualificado', 'Proposta', 'Fechado', 'Perdido'];
            },
            limparStatus() {
                this.filtros.status_list = [];
            },
            selecionarTodasOrigemCliente() {
                this.filtros.origem_cliente_list = ['Site', 'WhatsApp', 'Instagram', 'Bio do Instagram', 'Facebook', 'LandPage', 'Indicação', 'Outros'];
            },
            limparOrigemCliente() {
                this.filtros.origem_cliente_list = [];
            },
            selecionarTodosTermometro() {
                this.filtros.termometro_list = ['Frio', 'Morno', 'Quente'];
            },
            limparTermometro() {
                this.filtros.termometro_list = [];
            },
            selecionarTodosCompra() {
                this.filtros.compra_list = ['sim', 'nao'];
            },
            limparCompra() {
                this.filtros.compra_list = [];
            },
            limparOrigens() {
                this.filtros.origens = [];
                this.carregarClientes();
            },

            aplicarFiltroOrigem() {
                this.dropdownOrigemAberto = false;
                this.carregarClientes();
            },
            aplicarFiltroStatus() {
                this.dropdownStatusAberto = false;
    // Converter lista para string separada por vírgula ou usar como array
                this.carregarClientes();
            },
            aplicarFiltroOrigemCliente() {
                this.dropdownOrigemClienteAberto = false;
                this.carregarClientes();
            },
            aplicarFiltroTermometro() {
                this.dropdownTermometroAberto = false;
                this.carregarClientes();
            },
            aplicarFiltroCompra() {
                this.dropdownCompraAberto = false;
                this.carregarClientes();
            },

            limparFiltros() {
                this.filtros.status = '';
                this.filtros.termometro = '';
                this.filtros.busca = '';
                this.filtros.origens = [];
                this.filtros.origem_cliente = '';
                this.filtros.status_list = [];
                this.filtros.origem_cliente_list = [];
                this.filtros.termometro_list = [];
                this.filtros.compra_list = [];
                this.filtros.periodo = '';
                this.filtros.ja_comprou = '';
                this.filtros.tipo_periodo = 'compra';
                this.filtros.data_inicio = '';
                this.filtros.data_fim = '';
                this.dropdownOrigemAberto = false;
                this.dropdownStatusAberto = false;
                this.dropdownOrigemClienteAberto = false;
                this.dropdownTermometroAberto = false;
                this.dropdownCompraAberto = false;
                this.carregarClientes();
            },
        // ====================================================================
        // FUNÇÕES DE AUTENTICAÇÃO
        // ====================================================================
            getToken: MarketingUtils.getToken,

            async fetchWithAuth(url, options = {}) {
                const token = this.getToken();
                if (!token) {
                    window.location.href = '/portal/login.php';
                    throw new Error('Token não encontrado');
                }
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        ...(options.headers || {})
                    }
                });
                if (response.status === 401) {
                    localStorage.removeItem('authToken');
                    localStorage.removeItem('userData');
                    window.location.href = '/portal/login.php';
                    throw new Error('Sessão expirada');
                }
                return response;
            },

            escapeHtml: MarketingUtils.escapeHtml,
            formatarMoeda: (valor) => MarketingUtils.formatarValor(valor, 'moeda'),
            formatarData: MarketingUtils.formatarData,
            formatarDataRelativa: MarketingUtils.formatarDataRelativa,

        // ====================================================================
        // RENDERIZAR TABELA
        // ====================================================================
            renderizarTabela() {
                const tbody = document.getElementById('clientesTableBody');
                if (!this.clientesTabela.length) {
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Nenhum cliente encontrado</td></tr>';
                    return;
                }

                tbody.innerHTML = this.clientesTabela.map(c => {
                    const statusClass = {
                        'Novo': 'bg-blue-100 text-blue-700',
                        'Qualificado': 'bg-amber-100 text-amber-700',
                        'Proposta': 'bg-purple-100 text-purple-700',
                        'Fechado': 'bg-emerald-100 text-emerald-700',
                        'Perdido': 'bg-rose-100 text-rose-700'
                    }[c.status] || 'bg-slate-100';

                    const termoIcon = { 'Frio': '🥶', 'Morno': '🌤️', 'Quente': '🔥' }[c.termometro] || '';

                    let origemBadge = '';
                    if (c.origem_dados === 'APENAS_CRM') origemBadge = '<span class="ml-2 text-[10px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded-full">💾 CRM</span>';
                    else if (c.origem_dados === 'APENAS_ERP') origemBadge = '<span class="ml-2 text-[10px] bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded-full">🏭 ERP</span>';
                    else if (c.origem_dados === 'AMBOS') origemBadge = '<span class="ml-2 text-[10px] bg-purple-100 text-purple-600 px-1.5 py-0.5 rounded-full">🔄 Ambos</span>';

                    const btnImportar = (c.origem_dados === 'APENAS_ERP') ?
                `<button onclick="window.importarClienteERP(${c.id_erp}, '${this.escapeHtml(c.nome).replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-indigo-500 mr-2 transition-colors" title="Importar para CRM"><i class="fa-solid fa-cloud-arrow-down"></i></button>` :
                '';
                const btnEditar = c.id_crm ? `<button onclick="window.editarCliente(${c.id_crm}, 'CRM')" class="text-slate-400 hover:text-emerald-600 mr-2 transition-colors"><i class="fa-solid fa-pen"></i></button>` : '';
                const btnDeletar = c.id_crm ? `<button onclick="window.deletarCliente(${c.id_crm}, '${this.escapeHtml(c.nome).replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-trash"></i></button>` : '';
                const valorExibir = c.valor_negocio > 0 ? MarketingUtils.formatarValor(c.valor_negocio, 'moeda') : (c.total_compras > 0 ? MarketingUtils.formatarValor(c.total_compras, 'moeda') : '—');

                let statusCompra = '';
                let statusCompraClass = '';
                let ultimoValor = '';

                if (c.total_pedidos > 0) {
                    statusCompra = '✅ Já comprou';
                    statusCompraClass = 'bg-emerald-100 text-emerald-700';
                    ultimoValor = c.total_compras ? MarketingUtils.formatarValor(c.total_compras, 'moeda') : 'R$ 0,00';
                } else {
                    statusCompra = '❌ Nunca comprou';
                    statusCompraClass = 'bg-slate-100 text-slate-500';
                    ultimoValor = '—';
                }

                const clienteId = c.id_crm || c.id_erp || c.id;

                return `
                    <tr class="border-b hover:bg-slate-50 cursor-pointer transition-colors" 
                        data-cliente-id="${clienteId}"
                    onclick="${c.id_crm ? `window.editarCliente(${c.id_crm}, 'CRM')` : `window.visualizarClienteUnificado(${c.id_erp}, '${c.origem_dados}')`}">
                        <td class="px-4 py-3">
                            <div class="font-bold text-slate-800">
                                ${this.escapeHtml(c.nome)}${origemBadge}
                                ${!c.id_crm ? '<span class="ml-2 text-[9px] bg-slate-200 text-slate-500 px-1.5 py-0.5 rounded-full">Apenas leitura</span>' : ''}
                            </div>
                            <div class="text-xs text-slate-400">
                                ${c.empresa || ''} ${c.telefone ? '• ' + c.telefone : ''}
                                ${c.nome_vendedor ? '<br><span class="text-[10px]">Vend: ' + this.escapeHtml(c.nome_vendedor) + '</span>' : ''}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-lg text-xs font-bold ${statusClass}">${c.status}</span></td>
                        <td class="px-4 py-3 text-center text-xl">${termoIcon}</td>
                        <td class="px-4 py-3 text-center text-xs">${c.origem}</td>
                        <td class="px-4 py-3 text-right font-bold text-emerald-600">${valorExibir}</td>
                        <td class="px-4 py-3 text-center text-xs">${this.formatarDataRelativa(c.ultima_interacao || c.data_ultima_compra || c.data_cadastro)}</td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex flex-col items-center gap-0.5">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${statusCompraClass}">${statusCompra}</span>
                    ${c.total_pedidos > 0 ? `<span class="text-[9px] text-slate-400">Último: ${ultimoValor}</span>` : ''}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">${btnImportar}${btnEditar}${btnDeletar}</td>
                    </tr>
                `;
            }).join('');
},

        // ====================================================================
        // BUSCA COM DEBOUNCE
        // ====================================================================
buscarComDebounce() {
    if (this.timeoutBusca) clearTimeout(this.timeoutBusca);
    this.timeoutBusca = setTimeout(() => {
        this.pagination.atual = 1;
        this.carregarClientes();
    }, 500);
},

        // ====================================================================
        // MUDAR PÁGINA
        // ====================================================================
mudarPagina(pagina) {
    if (pagina < 1 || pagina > this.pagination.totalPaginas) return;
    this.pagination.atual = pagina;
    this.carregarClientes();
},

        // ====================================================================
        // ALERTAS E COMPROMISSOS
        // ====================================================================
async carregarAlertas() {
    try {
        const resp = await this.fetchWithAuth('/v1/marketing/crm-dashboard');
        const data = await resp.json();
        this.alertas = data.alertas || [];
    } catch (e) {
        console.error(e);
    }
},

        // ====================================================================
        // FUNÇÕES AUXILIARES PARA AGENDA DE COMPROMISSOS
        // ====================================================================
getProximidadeClass(dataHora) {
    if (!dataHora) return 'bg-slate-100 text-slate-500';
    const data = new Date(dataHora);
    const agora = new Date();
    const diffMs = data - agora;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 0) return 'bg-rose-100 text-rose-700';
    if (diffMin < 15) return 'bg-red-100 text-red-700 font-bold';
    if (diffMin < 60) return 'bg-amber-100 text-amber-700';
    if (diffMin < 1440) return 'bg-blue-100 text-blue-700';
    return 'bg-slate-100 text-slate-500';
},

formatarDataProxima(dataHora) {
    if (!dataHora) return '—';
    const data = new Date(dataHora);
    const agora = new Date();
    const diffMs = data - agora;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHoras = Math.floor(diffMs / 3600000);
    const diffDias = Math.floor(diffMs / 86400000);
    if (diffMin < 0) return 'Atrasado';
    if (diffMin < 1) return 'Agora';
    if (diffMin < 60) return `${diffMin} min`;
    if (diffHoras < 24) return `${diffHoras}h`;
    if (diffDias === 0) return 'Hoje';
    if (diffDias === 1) return 'Amanhã';
    if (diffDias < 7) return `${diffDias} dias`;
    return data.toLocaleDateString('pt-BR');
},

formatarHora(dataHora) {
    if (!dataHora) return '—';
    const data = new Date(dataHora);
    return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
},

calcularDuracao(inicio, fim) {
    if (!inicio || !fim) return '—';
    const inicioDate = new Date(inicio);
    const fimDate = new Date(fim);
    const diffMs = fimDate - inicioDate;
    const diffMin = Math.floor(diffMs / 60000);
    if (diffMin < 1) return '—';
    if (diffMin < 60) return `${diffMin} min`;
    const horas = Math.floor(diffMin / 60);
    const minutos = diffMin % 60;
    if (minutos === 0) return `${horas}h`;
    return `${horas}h${minutos}min`;
},

async carregarProximosCompromissos() {
    try {
        const resp = await this.fetchWithAuth('/v1/marketing/compromissos/proximos');
        const data = await resp.json();
        this.proximosCompromissos = data.data || [];
    } catch (e) {
        console.error(e);
    }
},

        // ====================================================================
        // LEMBRETES DE HOJE
        // ====================================================================
async carregarLembretesHoje() {
    try {
        const token = this.getToken();
        const resp = await fetch('/v1/marketing/lembretes-hoje', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        this.lembretesHoje = data || [];
        this.verificarLembretesPendentes();
    } catch (e) {
        console.error('Erro ao carregar lembretes de hoje:', e);
    }
},

verificarLembretesPendentes() {
    const agora = new Date();
    const horaAtual = agora.getHours();
    const minAtual = agora.getMinutes();

    this.lembretesHoje.forEach(lembrete => {
        if (lembrete.concluido) return;
        const [hora, min] = (lembrete.hora_lembrete || '09:00').split(':').map(Number);
        const diffMin = (hora * 60 + min) - (horaAtual * 60 + minAtual);
        if (diffMin >= 0 && diffMin <= 15) {
            this.mostrarAlertaLembrete(lembrete);
        }
    });
},

mostrarAlertaLembrete(lembrete) {
    const jaAlertado = localStorage.getItem(`lembrete_alert_${lembrete.id}`);
    if (jaAlertado) return;

    const clienteNome = lembrete.cliente_nome || 'Cliente';
    const hora = lembrete.hora_lembrete || '09:00';

    try {
        const audio = new Audio('data:audio/wav;base64,U3RlYWx0aCBzb3VuZA==');
        audio.play().catch(() => {});
    } catch(e) {}

    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('⏰ Lembrete pendente!', {
            body: `${clienteNome} - ${lembrete.descricao} às ${hora}`,
            icon: '/favicon.ico'
        });
    }

    Swal.fire({
        icon: 'info',
        title: '⏰ Lembrete pendente!',
        html: `
                    <div class="text-left">
                        <p><strong>Cliente:</strong> ${clienteNome}</p>
                        <p><strong>Descrição:</strong> ${lembrete.descricao}</p>
                        <p><strong>Horário:</strong> ${hora}</p>
                    </div>
            `,
            timer: 10000,
            showConfirmButton: true,
            confirmButtonText: 'Ver cliente',
            confirmButtonColor: '#f59e0b'
        }).then((result) => {
            if (result.isConfirmed && lembrete.id_cliente) {
                window.editarCliente(lembrete.id_cliente, 'CRM');
            }
        });

        localStorage.setItem(`lembrete_alert_${lembrete.id}`, 'true');
        setTimeout(() => {
            localStorage.removeItem(`lembrete_alert_${lembrete.id}`);
        }, 3600000);
    },

        // ====================================================================
        // INICIAR VERIFICAÇÃO DE LEMBRETES
        // ====================================================================
    iniciarVerificacaoLembretes() {
        this.carregarLembretesHoje();
        this.intervaloLembretes = setInterval(() => {
            this.carregarLembretesHoje();
        }, 30000);
    },

    limparVerificacaoLembretes() {
        if (this.intervaloLembretes) {
            clearInterval(this.intervaloLembretes);
            this.intervaloLembretes = null;
        }
    },

        // ====================================================================
        // VERIFICAÇÃO DE COMPROMISSOS EM TEMPO REAL
        // ====================================================================
    async verificarCompromissosProximos() {
        try {
            const token = this.getToken();
            if (!token) return;

            const resp = await fetch('/v1/marketing/compromissos/meus-proximos', {
                headers: { 'Authorization': 'Bearer ' + token }
            });

            if (!resp.ok) return;

            const data = await resp.json();

            if (data.success && data.compromissos) {
                const urgentes = data.compromissos.filter(c => 
                    c.urgencia === 'iminente' || c.urgencia === 'proximo'
                    );

                urgentes.forEach(comp => {
                    if (!comp.ja_notificado) {
                        this.mostrarAlertaCompromisso(comp);
                    }
                });
            }

        } catch (e) {
            console.error('Erro ao verificar compromissos:', e);
        }
    },

    mostrarAlertaCompromisso(comp) {
        const icone = comp.urgencia === 'iminente' ? '🔴' : '🟡';
        const titulo = comp.urgencia === 'iminente' ? 'Compromisso iminente!' : 'Compromisso em breve!';
        const clienteNome = comp.cliente_nome || 'Cliente';
        const dataHora = new Date(comp.data_hora).toLocaleString('pt-BR');

        try {
            const audio = new Audio('data:audio/wav;base64,U3RlYWx0aCBzb3VuZA==');
            audio.play().catch(() => {});
        } catch(e) {}

        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('📅 ' + titulo, {
                body: `${clienteNome} - ${comp.titulo || comp.tipo} às ${dataHora}`,
                icon: '/favicon.ico'
            });
        }

        Swal.fire({
            icon: 'warning',
            title: `${icone} ${titulo}`,
            html: `
                    <div class="text-left">
                        <p><strong>Cliente:</strong> ${clienteNome}</p>
                        <p><strong>Título:</strong> ${comp.titulo || comp.tipo}</p>
                        <p><strong>Data/Hora:</strong> ${dataHora}</p>
                        ${comp.horas_para_inicio <= 0.25 ? '<p class="text-red-500 font-bold mt-2">⚠️ Começa em menos de 15 minutos!</p>' : ''}
                    </div>
                `,
                timer: 15000,
                showConfirmButton: true,
                confirmButtonText: 'Ver cliente',
                confirmButtonColor: '#3b82f6',
                showCancelButton: true,
                cancelButtonText: 'Ignorar'
            }).then((result) => {
                if (result.isConfirmed && comp.id_cliente) {
                    window.editarCliente(comp.id_cliente, 'CRM');
                }
            });

            this.criarNotificacaoCompromisso(comp);
        },

        async criarNotificacaoCompromisso(comp) {
            try {
                const token = this.getToken();
                if (!token) return;

                const resp = await fetch('/v1/crm/notificacoes', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        titulo: `📅 ${comp.urgencia === 'iminente' ? 'Compromisso iminente!' : 'Compromisso em breve!'}`,
                        mensagem: `Compromisso com ${comp.cliente_nome || 'Cliente'} às ${new Date(comp.data_hora).toLocaleString('pt-BR')}`,
                        tipo: 'compromisso',
                        id_referencia: comp.id,
                        link: `/portal/modules/marketing/clientes.php?id=${comp.id_cliente}`
                    })
                });

                if (resp.ok) {
                    console.log('✅ Notificação criada no banco para compromisso:', comp.id);
                }
            } catch (e) {
                console.error('Erro ao criar notificação:', e);
            }
        },

        iniciarVerificacaoCompromissos() {
            this.verificarCompromissosProximos();
            this.intervaloVerificacao = setInterval(() => {
                this.verificarCompromissosProximos();
            }, 60000);
        },

        limparVerificacaoCompromissos() {
            if (this.intervaloVerificacao) {
                clearInterval(this.intervaloVerificacao);
                this.intervaloVerificacao = null;
            }
        },

        async carregarEstatisticasMes() {
            try {
                const token = this.getToken();
                const resp = await fetch('/v1/marketing/compromissos/estatisticas', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await resp.json();
                this.stats.totalCompromissosMes = data.total || 0;
                this.stats.concluidosMes = data.concluidos || 0;
            } catch (e) {
                console.error(e);
            }
        },

        async concluirCompromisso(id) {
            try {
                const token = this.getToken();
                await fetch(`/v1/marketing/compromissos/${id}/concluir`, {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Concluído!',
                    timer: 1500,
                    showConfirmButton: false
                });
                await this.atualizarTudoAposCadastro();
            } catch (e) {
                console.error(e);
            }
        },

        async atualizarTudoAposCadastro() {
            if (this.calendario) this.calendario.refetchEvents();
            await this.carregarProximosCompromissos();
            await this.carregarEstatisticasMes();
        },

        // ====================================================================
        // CALENDÁRIO
        // ====================================================================
        inicializarCalendario() {
            const calendarEl = document.getElementById('calendarioAgenda');
            if (!calendarEl) return;

            this.calendario = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                slotMinTime: '08:00:00',
                slotMaxTime: '20:00:00',
                slotDuration: '00:30:00',
                slotLabelInterval: '01:00',
                allDaySlot: false,
                nowIndicator: true,
                editable: true,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                weekends: true,
                selectMinDistance: 0,

                eventDidMount: (info) => {
                    const tipo = info.event.extendedProps.tipo;
                    const status = info.event.extendedProps.status;
                    let bgColor = '#3b82f6';
                    if (status === 'concluido') bgColor = '#10b981';
                    else if (status === 'cancelado') bgColor = '#ef4444';
                    else if (tipo === 'reuniao') bgColor = '#3b82f6';
                    else if (tipo === 'ligacao') bgColor = '#f59e0b';
                    else if (tipo === 'visita') bgColor = '#8b5cf6';
                    else if (tipo === 'whatsapp') bgColor = '#25D366';
                    else if (tipo === 'email') bgColor = '#6b7280';
                    info.el.style.backgroundColor = bgColor;
                    info.el.style.borderLeft = '3px solid rgba(255,255,255,0.5)';
                },

                select: (info) => {
                    this.abrirAgendamentoRapido(info.start, info.end);
                },
                eventResize: async (info) => {
                    await this.atualizarHorarioCompromisso(info.event.id, info.event.start, info.event.end);
                },
                eventDrop: async (info) => {
                    await this.atualizarHorarioCompromisso(info.event.id, info.event.start, info.event.end);
                },

                events: async (fetchInfo, successCallback) => {
                    try {
                        const token = this.getToken();
                        const filtroAtivo = window.filtroAgendaAtual || 'todos';
                        let url = `/v1/marketing/compromissos?inicio=${fetchInfo.start.toISOString()}&fim=${fetchInfo.end.toISOString()}`;
                        if (filtroAtivo !== 'todos') url += `&tipo=${filtroAtivo}`;
                        const resp = await fetch(url, {
                            headers: { 'Authorization': 'Bearer ' + token }
                        });
                        const data = await resp.json();
                        const eventos = (data.data || []).map(c => ({
                            id: c.id,
                            title: `${c.cliente_nome || 'Cliente'} - ${c.titulo || c.tipo}`,
                            start: c.data_hora,
                            end: c.data_hora_fim || null,
                            extendedProps: {
                                cliente_id: c.id_cliente,
                                cliente_nome: c.cliente_nome,
                                tipo: c.tipo,
                                status: c.status,
                                descricao: c.descricao,
                                data_hora: c.data_hora,
                                data_hora_fim: c.data_hora_fim
                            }
                        }));
                        successCallback(eventos);
                    } catch (e) {
                        successCallback([]);
                    }
                },

                eventClick: (info) => {
                    Swal.fire({
                        title: '<div class="flex items-center gap-2"><i class="fa-regular fa-calendar-check text-blue-500"></i> Detalhes do Compromisso</div>',
                        html: `
                            <div class="text-left space-y-3">
                                <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Cliente</p><p class="font-bold text-slate-800">${info.event.extendedProps.cliente_nome || '-'}</p></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Início</p><p class="font-bold text-slate-800">${new Date(info.event.start).toLocaleString('pt-BR')}</p></div>
                                    <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Término</p><p class="font-bold text-slate-800">${info.event.end ? new Date(info.event.end).toLocaleString('pt-BR') : '—'}</p></div>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Tipo</p><p class="font-bold">${info.event.extendedProps.tipo}</p></div>
                                <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Descrição</p><p class="text-sm text-slate-600">${info.event.extendedProps.descricao || '—'}</p></div>
                                <div class="bg-amber-50 p-3 rounded-xl"><p class="text-xs text-amber-600">Status</p><p class="font-bold">${info.event.extendedProps.status === 'agendado' ? '📅 Agendado' : (info.event.extendedProps.status === 'concluido' ? '✅ Concluído' : '❌ Cancelado')}</p></div>
                            </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: '<i class="fa-solid fa-pen mr-1"></i>Editar',
                            cancelButtonText: 'Fechar',
                            showDenyButton: info.event.extendedProps.status === 'agendado',
                            denyButtonText: '<i class="fa-solid fa-check mr-1"></i>Concluir',
                            showCloseButton: true,
                            confirmButtonColor: '#3b82f6',
                            denyButtonColor: '#10b981'
                        }).then(result => {
                            if (result.isConfirmed) this.abrirEdicaoCompromisso(info.event.id, info.event.extendedProps);
                            else if (result.isDenied) this.concluirCompromisso(info.event.id);
                        });
                    }
                });
this.calendario.render();
this.carregarEstatisticasMes();
},

async atualizarHorarioCompromisso(id, start, end) {
    try {
        const token = this.getToken();
        await fetch(`/v1/marketing/compromissos/${id}`, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                data_hora: start.toISOString(),
                data_hora_fim: end ? end.toISOString() : null
            })
        });
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Horário atualizado!',
            timer: 1500,
            showConfirmButton: false
        });
        await this.atualizarTudoAposCadastro();
    } catch (e) {
        console.error(e);
    }
},

        // ====================================================================
        // AGENDAMENTO RÁPIDO
        // ====================================================================
abrirAgendamentoRapido(start, end) {
    const formatarDateTime = (date) => {
        if (!date) return '';
        const ano = date.getFullYear();
        const mes = String(date.getMonth() + 1).padStart(2, '0');
        const dia = String(date.getDate()).padStart(2, '0');
        const horas = String(date.getHours()).padStart(2, '0');
        const minutos = String(date.getMinutes()).padStart(2, '0');
        return `${ano}-${mes}-${dia}T${horas}:${minutos}`;
    };

    const dataHoraInicio = formatarDateTime(start);
    const dataHoraFim = end && end > start ? formatarDateTime(end) : '';
    const duracaoMinutos = end && end > start ? Math.round((end - start) / 60000) : 0;

    Swal.fire({
        title: '<i class="fa-regular fa-calendar-plus mr-2 text-blue-500"></i>Novo Compromisso',
        html: `
                    <div class="space-y-3 text-left">
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Cliente *</label><select id="rapidoCliente" class="w-full p-2 border rounded-xl text-sm"><option value="">Carregando clientes...</option></select></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Início *</label><input type="datetime-local" id="rapidoDataHoraInicio" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraInicio}"></div>
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Término</label><input type="datetime-local" id="rapidoDataHoraFim" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraFim}"></div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Tipo</label>
                            <div class="flex gap-2">
                                <button type="button" data-tipo="reuniao" class="tipo-btn flex-1 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-blue-100 hover:text-blue-600"><i class="fa-regular fa-calendar mr-1"></i> Reunião</button>
                                <button type="button" data-tipo="ligacao" class="tipo-btn flex-1 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-amber-100 hover:text-amber-600"><i class="fa-solid fa-phone mr-1"></i> Ligação</button>
                                <button type="button" data-tipo="visita" class="tipo-btn flex-1 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-purple-100 hover:text-purple-600"><i class="fa-solid fa-building mr-1"></i> Visita</button>
                            </div>
                            <input type="hidden" id="rapidoTipo" value="reuniao">
                        </div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Título</label><input type="text" id="rapidoTitulo" class="w-full p-2 border rounded-xl text-sm" placeholder="Ex: Follow-up proposta"></div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Descrição</label><textarea id="rapidoDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm"></textarea></div>
            ${duracaoMinutos > 0 ? `<div class="bg-blue-50 p-3 rounded-lg border border-blue-200"><i class="fa-regular fa-clock text-blue-500 mr-1"></i><span class="text-sm text-blue-700">Intervalo selecionado: <strong>${duracaoMinutos} minutos</strong></span></div>` : '<div class="text-xs text-slate-400 bg-slate-50 p-2 rounded-lg"><i class="fa-regular fa-clock mr-1"></i>Clique e arraste no calendário para selecionar um intervalo</div>'}
                    </div>
            `,
            width: '550px',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-regular fa-save mr-1"></i>Agendar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            didOpen: async () => {
                await this.carregarClientesParaSelectRapido();

                const botoes = document.querySelectorAll('.tipo-btn');
                const atualizarEstiloBotoes = (btnAtivo) => {
                    botoes.forEach(btn => {
                        btn.classList.remove('bg-blue-500', 'bg-amber-500', 'bg-purple-500', 'text-white');
                        btn.classList.add('bg-slate-100', 'text-slate-600');
                    });
                    if (btnAtivo) {
                        btnAtivo.classList.remove('bg-slate-100', 'text-slate-600');
                        btnAtivo.classList.add('bg-blue-500', 'text-white');
                    }
                };
                botoes.forEach(btn => {
                    btn.removeEventListener('click', this.tipoClickHandler);
                    this.tipoClickHandler = () => {
                        document.getElementById('rapidoTipo').value = btn.getAttribute('data-tipo');
                        atualizarEstiloBotoes(btn);
                    };
                    btn.addEventListener('click', this.tipoClickHandler);
                });
                const primeiroBotao = document.querySelector('.tipo-btn[data-tipo="reuniao"]');
                if (primeiroBotao) atualizarEstiloBotoes(primeiroBotao);
            },
            preConfirm: () => {
                const clienteId = document.getElementById('rapidoCliente').value;
                const dataHoraInicio = document.getElementById('rapidoDataHoraInicio').value;
                const dataHoraFim = document.getElementById('rapidoDataHoraFim').value || null;
                const tipo = document.getElementById('rapidoTipo').value;
                const titulo = document.getElementById('rapidoTitulo').value;
                const descricao = document.getElementById('rapidoDescricao').value;
                if (!clienteId || !dataHoraInicio) {
                    Swal.showValidationMessage('Preencha cliente e data/hora de início');
                    return false;
                }
                if (dataHoraFim && new Date(dataHoraFim) <= new Date(dataHoraInicio)) {
                    Swal.showValidationMessage('A data/hora de término deve ser maior que a data/hora de início');
                    return false;
                }
                return { clienteId, dataHoraInicio, dataHoraFim, tipo, titulo, descricao };
            }
        }).then(async (result) => {
            if (result.isConfirmed) await this.salvarCompromissoRapido(result.value);
        });
    },

    async carregarClientesParaSelectRapido() {
        try {
            const token = this.getToken();
            const resp = await fetch('/v1/marketing/clientes/consulta-otimizado?limite=100', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await resp.json();
            const select = document.getElementById('rapidoCliente');
            const clientes = data.clientes || [];
            select.innerHTML = '<option value="">Selecione um cliente...</option>' +
            clientes.map(c => `<option value="${c.id_crm || c.id_erp}">${c.nome || c.empresa || 'Cliente'}</option>`).join('');
        } catch (e) {
            console.error(e);
        }
    },

    async salvarCompromissoRapido(dados) {
        try {
            const token = this.getToken();
            const resp = await fetch('/v1/marketing/compromissos', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_cliente: parseInt(dados.clienteId),
                    data_hora: dados.dataHoraInicio,
                    data_hora_fim: dados.dataHoraFim || null,
                    tipo: dados.tipo,
                    titulo: dados.titulo || dados.tipo,
                    descricao: dados.descricao,
                    status: 'agendado'
                })
            });
            const result = await resp.json();
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Agendado!',
                    timer: 1500,
                    showConfirmButton: false
                });
                await this.atualizarTudoAposCadastro();
            }
        } catch (e) {
            Swal.fire('Erro', e.message, 'error');
        }
    },

        // ====================================================================
        // MÉTODOS DO MODAL DE CLIENTE
        // ====================================================================
    async carregarInteracoes(id) {
        try {
            const token = this.getToken();
            const resp = await fetch(`/v1/marketing/clientes/${id}/interacoes`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const interacoes = await resp.json();
            const container = document.getElementById('historicoInteracoes');
            if (!container) return;
            if (!interacoes?.length) {
                container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhuma interação registrada</p>';
                return;
            }
            container.innerHTML = interacoes.map(i => `
                    <div class="timeline-item" style="--timeline-color: ${i.tipo === 'ligacao' ? '#f59e0b' : (i.tipo === 'whatsapp' ? '#25D366' : '#3b82f6')}">
                        <div class="timeline-icon"><i class="fa-solid ${i.tipo === 'ligacao' ? 'fa-phone' : (i.tipo === 'whatsapp' ? 'fa-whatsapp' : 'fa-envelope')} text-sm"></i></div>
                        <div class="timeline-content">
                            <div class="flex justify-between items-start"><span class="font-bold text-sm">${i.tipo}</span><span class="text-xs text-slate-400">${i.data_interacao} ${i.hora_interacao}</span></div>
                            <p class="text-sm mt-1">${i.descricao}</p><span class="text-xs text-slate-400">Por: ${i.usuario || 'Sistema'}</span>
                        </div>
                    </div>
            `).join('');
        } catch (e) {
            console.error(e);
        }
    },

    async carregarLembretes(id) {
        try {
            const token = this.getToken();
            const resp = await fetch('/v1/marketing/lembretes', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const lembretes = await resp.json();
            const filtrados = (lembretes || []).filter(l => l.id_cliente == id);
            const container = document.getElementById('historicoLembretes');
            if (!container) return;
            if (!filtrados.length) {
                container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum lembrete</p>';
                return;
            }
            container.innerHTML = filtrados.map(l => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border ${l.concluido ? 'border-emerald-200' : 'border-amber-200'}">
                        <div><div class="font-bold">${l.descricao}</div><div class="text-xs text-slate-400">${l.data_lembrete} ${l.hora_lembrete}</div></div>
                ${!l.concluido ? `<button onclick="window.concluirLembrete(${l.id})" class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold">Concluir</button>` : '<span class="text-emerald-600 text-xs">✅ Concluído</span>'}
                    </div>
            `).join('');
        } catch (e) {
            console.error(e);
        }
    },

    async carregarAnexos(id) {
        try {
            const token = this.getToken();
            const resp = await fetch(`/v1/marketing/clientes/${id}/anexos`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const anexos = await resp.json();
            const container = document.getElementById('historicoAnexos');
            if (!container) return;
            if (!anexos?.length) {
                container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum anexo</p>';
                return;
            }
            container.innerHTML = anexos.map(a => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border">
                        <div><span class="font-bold">${a.nome_original}</span><div class="text-xs text-slate-400">${new Date(a.created_at).toLocaleDateString('pt-BR')}</div></div>
                        <div class="flex gap-2">
                            <a href="/v1/marketing/anexos/${a.id}/download" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold">Download</a>
                            <button onclick="window.deletarAnexo(${a.id})" class="px-3 py-1.5 bg-rose-500 text-white rounded-lg text-xs font-bold">Excluir</button>
                        </div>
                    </div>
            `).join('');
        } catch (e) {
            console.error(e);
        }
    },

    async carregarCompromissosCliente(idCliente) {
        try {
            const token = this.getToken();
            const resp = await fetch(`/v1/marketing/compromissos/cliente/${idCliente}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const compromissos = await resp.json();
            const container = document.getElementById('listaCompromissos');
            if (!container) return;
            if (!compromissos.length) {
                container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum compromisso agendado</p>';
                return;
            }
            container.innerHTML = compromissos.map(c => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-200">
                        <div>
                            <div class="flex items-center gap-2"><span class="font-bold text-sm">${c.titulo || c.tipo}</span><span class="px-2 py-0.5 rounded-full text-xs ${c.status === 'agendado' ? 'bg-blue-100 text-blue-700' : (c.status === 'concluido' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700')}">${c.status === 'agendado' ? 'Agendado' : (c.status === 'concluido' ? 'Concluído' : 'Cancelado')}</span></div>
                            <div class="text-xs text-slate-400">${new Date(c.data_hora).toLocaleString('pt-BR')}</div>
                            <p class="text-sm mt-1">${c.descricao || ''}</p>
                        </div>
                        <div class="flex gap-1">
                ${c.status === 'agendado' ? `<button onclick="window.concluirCompromisso(${c.id})" class="p-1.5 text-emerald-600 hover:text-emerald-700" title="Concluir"><i class="fa-solid fa-check"></i></button>` : ''}
                            <button onclick="window.excluirCompromisso(${c.id})" class="p-1.5 text-rose-500 hover:text-rose-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
            `).join('');
        } catch (e) {
            console.error(e);
        }
    },

        // ====================================================================
        // AUTO REFRESH
        // ====================================================================
    iniciarAutoRefresh() {
        setInterval(() => {
            this.carregarAlertas();
            this.carregarProximosCompromissos();
            if (this.calendario) this.calendario.refetchEvents();
        }, 60000);
    },

        // ====================================================================
        // EDIÇÃO DE COMPROMISSO
        // ====================================================================
    abrirEdicaoCompromisso(id, dados) {
        const dataHoraInicio = dados.data_hora ? dados.data_hora.slice(0, 16) : '';
        const dataHoraFim = dados.data_hora_fim ? dados.data_hora_fim.slice(0, 16) : '';

        Swal.fire({
            title: 'Editar Compromisso',
            html: `
                    <div class="space-y-3 text-left">
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Início</label><input type="datetime-local" id="editDataHoraInicio" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraInicio}"></div>
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Término</label><input type="datetime-local" id="editDataHoraFim" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraFim}"></div>
                        </div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Tipo</label><select id="editTipo" class="w-full p-2 border rounded-xl text-sm">
                            <option value="reuniao" ${dados.tipo === 'reuniao' ? 'selected' : ''}>📅 Reunião</option>
                            <option value="ligacao" ${dados.tipo === 'ligacao' ? 'selected' : ''}>📞 Ligação</option>
                            <option value="visita" ${dados.tipo === 'visita' ? 'selected' : ''}>🏢 Visita</option>
                            <option value="whatsapp" ${dados.tipo === 'whatsapp' ? 'selected' : ''}>💬 WhatsApp</option>
                            <option value="email" ${dados.tipo === 'email' ? 'selected' : ''}>📧 Email</option>
                        </select></div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Título</label><input type="text" id="editTitulo" class="w-full p-2 border rounded-xl text-sm" value="${dados.titulo || ''}"></div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Descrição</label><textarea id="editDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm">${dados.descricao || ''}</textarea></div>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonText: 'Salvar',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const dataHoraInicio = document.getElementById('editDataHoraInicio').value;
                    const dataHoraFim = document.getElementById('editDataHoraFim').value || null;
                    if (!dataHoraInicio) {
                        Swal.showValidationMessage('Data/hora de início é obrigatória');
                        return false;
                    }
                    if (dataHoraFim && new Date(dataHoraFim) <= new Date(dataHoraInicio)) {
                        Swal.showValidationMessage('O término deve ser maior que o início');
                        return false;
                    }
                    return {
                        data_hora: dataHoraInicio,
                        data_hora_fim: dataHoraFim,
                        tipo: document.getElementById('editTipo').value,
                        titulo: document.getElementById('editTitulo').value,
                        descricao: document.getElementById('editDescricao').value
                    };
                }
            }).then(async (result) => {
                if (result.isConfirmed) await this.atualizarCompromissoCompleto(id, result.value);
            });
        },

        async atualizarCompromissoCompleto(id, dados) {
            try {
                const token = this.getToken();
                const resp = await fetch(`/v1/marketing/compromissos/${id}`, {
                    method: 'PUT',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(dados)
                });
                const result = await resp.json();
                if (result.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Atualizado!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                    await this.atualizarTudoAposCadastro();
                }
            } catch (e) {
                Swal.fire('Erro', e.message, 'error');
            }
        },

        editarCliente(id, origem) {
            window.editarCliente(id, origem);
        },

        // ====================================================================
        // INICIALIZAÇÃO - COM CONTROLE DE CONCORRÊNCIA
        // ====================================================================
        async init() {
            // 🔥 EVITAR INICIALIZAÇÃO DUPLICADA
            if (this._inicializado) return;
            this._inicializado = true;
            
            // 🔥 EVITAR CARREGAMENTO CONCORRENTE
            if (this._carregando) return;
            
            this.viewMode = 'tabela';
            localStorage.setItem('crm_view_mode', 'tabela');
            
            const temClienteURL = this.carregarClienteDaURL();
            
            await this.carregarDashboard();
            
            if (temClienteURL) {
                console.log('🚀 Cliente carregado da URL, pulando carregamento completo');
                this.carregarAlertas();
                this.carregarProximosCompromissos();
                this.iniciarVerificacaoCompromissos();
                this.inicializarCalendario();
                this.iniciarAutoRefresh();
                this.carregarLembretesHoje();
                this.iniciarVerificacaoLembretes();
                this.carregarMetasSelect();
                this.carregarEstatisticasMes();
                
                if ('Notification' in window && Notification.permission === 'default') {
                    Notification.requestPermission();
                }
                return;
            }
            
            console.log('📋 Carregando clientes normalmente');
            await this.carregarClientes();
            await this.carregarAlertas();
            await this.carregarProximosCompromissos();
            this.iniciarVerificacaoCompromissos();
            await this.carregarLembretesHoje();
            this.iniciarVerificacaoLembretes();
            await this.carregarMetasSelect();
            this.inicializarCalendario();
            this.iniciarAutoRefresh();
            await this.carregarEstatisticasMes();
            
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
            
            this.verificarParametrosBusca();
        },


        // ====================================================================
        // DASHBOARD E STATS
        // ====================================================================
        async carregarDashboard() {
            try {
                const resp = await this.fetchWithAuth('/v1/marketing/crm-dashboard');
                const data = await resp.json();
                this.stats.total = data.cards?.total_clientes || 0;
                this.stats.fechados = data.cards?.fechados || 0;
                this.stats.emNegociacao = data.cards?.em_negociacao || 0;
                this.stats.pipelineValue = data.cards?.total_faturado || 0;
                this.stats.conversao = this.stats.total > 0 ? ((this.stats.fechados / this.stats.total) * 100).toFixed(1) : 0;
            } catch (e) {
                console.error('Erro ao carregar dashboard:', e);
            }
        },

        async carregarMetasSelect() {
            try {
                const resp = await this.fetchWithAuth('/v1/meta-builder/instancias/ativas');
                const data = await resp.json();
                const metas = data.data || [];
                const select = document.getElementById('clienteMeta');
                if (select) {
                    select.innerHTML = '<option value="0">Sem meta</option>' +
                    metas.filter(m => m.status === 'ativa').map(m => `<option value="${m.id}">${m.titulo}</option>`).join('');
                }
            } catch (e) {
                console.error('Erro ao carregar metas:', e);
            }
        },

        // ====================================================================
        // CLIENTES
        // ====================================================================
        async carregarClientes(manterFiltro = false) {
    // 🔥 CONTROLE DE CONCORRÊNCIA
            if (this._carregando) {
                console.log('⏳ Já está carregando, ignorando...');
                return;
            }

            if (manterFiltro && this._clienteEncontrado) {
                console.log('⏭️ Mantendo cliente encontrado, não recarregando');
                return;
            }

            this._carregando = true;

            try {
        // ================================================================
        // 1. PREPARAR PARÂMETROS
        // ================================================================
              const params = new URLSearchParams({
                pagina: this.pagination.atual,
                limite: this.pagination.limite,
                status: this.filtros.status_list ? this.filtros.status_list.join(',') : '',
                termometro: this.filtros.termometro_list ? this.filtros.termometro_list.join(',') : '',
                busca: this.filtros.busca || '',
                origem: this.filtros.origens ? this.filtros.origens.join(',') : '',
                origem_cliente: this.filtros.origem_cliente_list ? this.filtros.origem_cliente_list.join(',') : '',
                periodo: this.filtros.periodo || '',
                ja_comprou: this.filtros.compra_list ? this.filtros.compra_list.join(',') : '',
                tipo_periodo: this.filtros.tipo_periodo || 'compra',
                data_inicio: this.filtros.data_inicio || '',
                data_fim: this.filtros.data_fim || ''
            });

        // ================================================================
        // 2. 🔥 LÓGICA INTELIGENTE PARA BUSCA_ID
        //    Só adiciona busca_id se for um número (ID) e NÃO for CNPJ/CPF
        // ================================================================
              const buscaStr = this.filtros.busca ? String(this.filtros.busca).trim() : '';

              if (buscaStr.length > 0) {
            // Remove tudo que não é número para verificar se é CNPJ/CPF
                const apenasNumeros = buscaStr.replace(/\D/g, '');

            // 🔥 Se for CNPJ (14 dígitos) ou CPF (11 dígitos), NÃO adiciona busca_id
            // A função V2 já busca em CNPJ/CPF via texto
                const isCNPJ = apenasNumeros.length === 14;
                const isCPF = apenasNumeros.length === 11;
                const isTelefone = (apenasNumeros.length === 10 || apenasNumeros.length === 11) && buscaStr.length === apenasNumeros.length;

                if (!isCNPJ && !isCPF && !isTelefone) {
                // Só adiciona busca_id se for um número inteiro (ID)
                    const buscaNumerica = parseInt(buscaStr);
                    if (!isNaN(buscaNumerica) && buscaStr === String(buscaNumerica)) {
                        params.append('busca_id', buscaNumerica);
                    }
                }
            }

            const url = `/v1/marketing/clientes/consulta-otimizado?${params}`;
            console.log('🔍 Buscando clientes:', url);

        // ================================================================
        // 3. FAZER REQUISIÇÃO
        // ================================================================
            const resp = await this.fetchWithAuth(url);
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            const data = await resp.json();
            if (!data.success) throw new Error(data.error || 'Erro ao carregar clientes');

        // ================================================================
        // 4. PROCESSAR RESULTADOS
        // ================================================================
            this.clientesTabela = (data.clientes || []).map(c => ({
                id: c.id_crm || c.id_erp,
                id_crm: c.id_crm,
                id_erp: c.id_erp,
                uid: `crm_${c.id_crm || 0}_erp_${c.id_erp || 0}`,
                nome: c.nome || '—',
                empresa: c.empresa || '',
                telefone: c.telefone || '',
                email: c.email || '',
                cidade: c.cidade || '',
                uf: c.uf || '',
                status: c.status_crm || 'Novo',
                termometro: c.termometro || 'Frio',
                origem: c.origem_crm || (c.origem_dados === 'APENAS_ERP' ? 'ERP' : 'Site'),
                valor_negocio: c.valor_negocio || 0,
                data_cadastro: c.data_cadastro_crm || c.data_cadastro_erp,
                ultima_interacao: c.ultima_interacao,
                total_interacoes: c.total_interacoes || 0,
                nome_vendedor: c.nome_vendedor,
                origem_dados: c.origem_dados,
                pode_acao: c.id_crm !== null,
                total_pedidos: c.total_pedidos || 0,
                total_compras: c.total_compras || 0,
                data_ultima_compra: c.data_ultima_compra,
                cnpj_cpf: c.cnpj_cpf || '',
                total_compras_periodo: c.total_compras_periodo || 0,
                total_pedidos_periodo: c.total_pedidos_periodo || 0
            }));

            this.pagination.total = data.total || 0;
            this.pagination.totalPaginas = data.total_paginas || 1;
            this.renderizarTabela();

        // ================================================================
        // 5. 🔥 DESTACAR CLIENTE (SÓ SE FOR ID, NÃO CNPJ/CPF)
        // ================================================================
            const buscaId = params.get('busca_id');
            if (buscaId) {
                setTimeout(() => {
                    this.destacarCliente(buscaId);
                }, 500);
            } else {
            // Se a busca foi por CNPJ/CPF e encontrou apenas 1 cliente, destaca
                if (this.clientesTabela.length === 1 && this.filtros.busca) {
                    const buscaStr2 = String(this.filtros.busca).trim();
                    const apenasNumeros2 = buscaStr2.replace(/\D/g, '');
                    const isCNPJ = apenasNumeros2.length === 14;
                    const isCPF = apenasNumeros2.length === 11;

                    if (isCNPJ || isCPF) {
                        setTimeout(() => {
                            this.aplicarDestaque(this.clientesTabela[0]);
                        }, 500);
                    }
                }
            }

        } catch (e) {
            console.error('❌ Erro ao carregar clientes:', e);
            Swal.fire({
                icon: 'error',
                title: 'Erro ao carregar clientes',
                text: e.message,
                confirmButtonText: 'Tentar novamente',
                confirmButtonColor: '#10b981'
            }).then(() => this.carregarClientes());
        } finally {
            this._carregando = false;
        }
    },
    renderizarTabela() {
        const tbody = document.getElementById('clientesTableBody');
        if (!this.clientesTabela.length) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Nenhum cliente encontrado</td></tr>';
            return;
        }

        tbody.innerHTML = this.clientesTabela.map(c => {
            const statusClass = {
                'Novo': 'bg-blue-100 text-blue-700',
                'Qualificado': 'bg-amber-100 text-amber-700',
                'Proposta': 'bg-purple-100 text-purple-700',
                'Fechado': 'bg-emerald-100 text-emerald-700',
                'Perdido': 'bg-rose-100 text-rose-700'
            }[c.status] || 'bg-slate-100';

            const termoIcon = { 'Frio': '🥶', 'Morno': '🌤️', 'Quente': '🔥' }[c.termometro] || '';

            let origemBadge = '';
            if (c.origem_dados === 'APENAS_CRM') origemBadge = '<span class="ml-2 text-[10px] bg-emerald-100 text-emerald-600 px-1.5 py-0.5 rounded-full">💾 CRM</span>';
            else if (c.origem_dados === 'APENAS_ERP') origemBadge = '<span class="ml-2 text-[10px] bg-amber-100 text-amber-600 px-1.5 py-0.5 rounded-full">🏭 ERP</span>';
            else if (c.origem_dados === 'AMBOS') origemBadge = '<span class="ml-2 text-[10px] bg-purple-100 text-purple-600 px-1.5 py-0.5 rounded-full">🔄 Ambos</span>';

        // 🔥 VALOR DO PERÍODO - PRIORIDADE
            let valorExibir = '—';
            let tooltipTexto = '';

            if (c.total_compras_periodo !== undefined && c.total_compras_periodo > 0) {
            // Tem período selecionado e cliente comprou no período
                valorExibir = MarketingUtils.formatarValor(c.total_compras_periodo, 'moeda');
                tooltipTexto = `Compras no período: ${valorExibir}`;
                if (c.total_compras > 0 && c.total_compras !== c.total_compras_periodo) {
                    tooltipTexto += ` | Total vida: ${MarketingUtils.formatarValor(c.total_compras, 'moeda')}`;
                }
            } else if (c.valor_negocio > 0) {
                valorExibir = MarketingUtils.formatarValor(c.valor_negocio, 'moeda');
                tooltipTexto = `Valor do negócio: ${valorExibir}`;
            } else if (c.total_compras > 0) {
                valorExibir = MarketingUtils.formatarValor(c.total_compras, 'moeda');
                tooltipTexto = `Total de compras: ${valorExibir}`;
            }

        // Status de compra
            let statusCompra = '';
            let statusCompraClass = '';
            let ultimoValor = '';

            if (c.total_pedidos_periodo !== undefined && c.total_pedidos_periodo > 0) {
            // Comprou no período
                statusCompra = '✅ Comprou no período';
                statusCompraClass = 'bg-emerald-100 text-emerald-700';
                ultimoValor = c.total_compras_periodo ? MarketingUtils.formatarValor(c.total_compras_periodo, 'moeda') : 'R$ 0,00';
            } else if (c.total_pedidos > 0) {
                statusCompra = '✅ Já comprou (vida)';
                statusCompraClass = 'bg-blue-100 text-blue-700';
                ultimoValor = c.total_compras ? MarketingUtils.formatarValor(c.total_compras, 'moeda') : 'R$ 0,00';
            } else {
                statusCompra = '❌ Nunca comprou';
                statusCompraClass = 'bg-slate-100 text-slate-500';
                ultimoValor = '—';
            }

            const btnImportar = (c.origem_dados === 'APENAS_ERP') ?
        `<button onclick="window.importarClienteERP(${c.id_erp}, '${this.escapeHtml(c.nome).replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-indigo-500 mr-2 transition-colors" title="Importar para CRM"><i class="fa-solid fa-cloud-arrow-down"></i></button>` :
        '';
        const btnEditar = c.id_crm ? `<button onclick="window.editarCliente(${c.id_crm}, 'CRM')" class="text-slate-400 hover:text-emerald-600 mr-2 transition-colors"><i class="fa-solid fa-pen"></i></button>` : '';
        const btnDeletar = c.id_crm ? `<button onclick="window.deletarCliente(${c.id_crm}, '${this.escapeHtml(c.nome).replace(/'/g, "\\'")}')" class="text-slate-400 hover:text-rose-500 transition-colors"><i class="fa-solid fa-trash"></i></button>` : '';

        const clienteId = c.id_crm || c.id_erp || c.id;

        return `
            <tr class="border-b hover:bg-slate-50 cursor-pointer transition-colors" 
                data-cliente-id="${clienteId}"
            onclick="${c.id_crm ? `window.editarCliente(${c.id_crm}, 'CRM')` : `window.visualizarClienteUnificado(${c.id_erp}, '${c.origem_dados}')`}">
                <td class="px-4 py-3">
                    <div class="font-bold text-slate-800">
                        ${this.escapeHtml(c.nome)}${origemBadge}
                        ${!c.id_crm ? '<span class="ml-2 text-[9px] bg-slate-200 text-slate-500 px-1.5 py-0.5 rounded-full">Apenas leitura</span>' : ''}
                    </div>
                    <div class="text-xs text-slate-400">
                        ${c.empresa || ''} ${c.telefone ? '• ' + c.telefone : ''}
                        ${c.nome_vendedor ? '<br><span class="text-[10px]">Vend: ' + this.escapeHtml(c.nome_vendedor) + '</span>' : ''}
                    </div>
                </td>
                <td class="px-4 py-3 text-center"><span class="px-2 py-1 rounded-lg text-xs font-bold ${statusClass}">${c.status}</span></td>
                <td class="px-4 py-3 text-center text-xl">${termoIcon}</td>
                <td class="px-4 py-3 text-center text-xs">${c.origem}</td>
                <td class="px-4 py-3 text-right font-bold text-emerald-600" title="${tooltipTexto}">
                    ${valorExibir}
            ${c.total_compras_periodo > 0 && c.total_compras > 0 && c.total_compras !== c.total_compras_periodo ? `<span class="text-[9px] text-slate-400 block">Vida: ${MarketingUtils.formatarValor(c.total_compras, 'moeda')}</span>` : ''}
                </td>
                <td class="px-4 py-3 text-center text-xs">${this.formatarDataRelativa(c.ultima_interacao || c.data_ultima_compra || c.data_cadastro)}</td>
                <td class="px-4 py-3 text-center">
                    <div class="flex flex-col items-center gap-0.5">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${statusCompraClass}">${statusCompra}</span>
            ${(c.total_pedidos_periodo > 0 || c.total_pedidos > 0) ? `<span class="text-[9px] text-slate-400">Último: ${ultimoValor}</span>` : ''}
            ${c.total_pedidos_periodo > 0 && c.total_pedidos > 0 ? `<span class="text-[8px] text-slate-400">(${c.total_pedidos_periodo} de ${c.total_pedidos} pedidos no período)</span>` : ''}
                    </div>
                </td>
                <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">${btnImportar}${btnEditar}${btnDeletar}</td>
            </tr>
        `;
    }).join('');
},

buscarComDebounce() {
    if (this.timeoutBusca) clearTimeout(this.timeoutBusca);
    this.timeoutBusca = setTimeout(() => {
        this.pagination.atual = 1;
        this.carregarClientes();
    }, 500);
},

mudarPagina(pagina) {
    if (pagina < 1 || pagina > this.pagination.totalPaginas) return;
    this.pagination.atual = pagina;
    this.carregarClientes();
},

        // ====================================================================
        // ALERTAS E COMPROMISSOS
        // ====================================================================
async carregarAlertas() {
    try {
        const resp = await this.fetchWithAuth('/v1/marketing/crm-dashboard');
        const data = await resp.json();
        this.alertas = data.alertas || [];
    } catch (e) {
        console.error(e);
    }
},

// ====================================================================
// FUNÇÕES AUXILIARES PARA AGENDA DE COMPROMISSOS
// ====================================================================

/**
 * Retorna a classe CSS baseada na proximidade do compromisso
 */
getProximidadeClass(dataHora) {
    if (!dataHora) return 'bg-slate-100 text-slate-500';
    
    const data = new Date(dataHora);
    const agora = new Date();
    const diffMs = data - agora;
    const diffMin = Math.floor(diffMs / 60000);
    
    if (diffMin < 0) return 'bg-rose-100 text-rose-700';
    if (diffMin < 15) return 'bg-red-100 text-red-700 font-bold';
    if (diffMin < 60) return 'bg-amber-100 text-amber-700';
    if (diffMin < 1440) return 'bg-blue-100 text-blue-700';
    return 'bg-slate-100 text-slate-500';
},

/**
 * Formata a data de forma resumida para a agenda
 */
formatarDataProxima(dataHora) {
    if (!dataHora) return '—';
    
    const data = new Date(dataHora);
    const agora = new Date();
    const diffMs = data - agora;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHoras = Math.floor(diffMs / 3600000);
    const diffDias = Math.floor(diffMs / 86400000);
    
    if (diffMin < 0) return 'Atrasado';
    if (diffMin < 1) return 'Agora';
    if (diffMin < 60) return `${diffMin} min`;
    if (diffHoras < 24) return `${diffHoras}h`;
    if (diffDias === 0) return 'Hoje';
    if (diffDias === 1) return 'Amanhã';
    if (diffDias < 7) return `${diffDias} dias`;
    
    return data.toLocaleDateString('pt-BR');
},

/**
 * Formata apenas a hora
 */
formatarHora(dataHora) {
    if (!dataHora) return '—';
    const data = new Date(dataHora);
    return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
},

/**
 * Calcula a duração entre duas datas
 */
calcularDuracao(inicio, fim) {
    if (!inicio || !fim) return '—';
    
    const inicioDate = new Date(inicio);
    const fimDate = new Date(fim);
    const diffMs = fimDate - inicioDate;
    const diffMin = Math.floor(diffMs / 60000);
    
    if (diffMin < 1) return '—';
    if (diffMin < 60) return `${diffMin} min`;
    
    const horas = Math.floor(diffMin / 60);
    const minutos = diffMin % 60;
    
    if (minutos === 0) return `${horas}h`;
    return `${horas}h${minutos}min`;
},
async carregarProximosCompromissos() {
    try {
        const resp = await this.fetchWithAuth('/v1/marketing/compromissos/proximos');
        const data = await resp.json();
        this.proximosCompromissos = data.data || [];
    } catch (e) {
        console.error(e);
    }
},
// ====================================================================
        // CARREGAR LEMBRETES DE HOJE
        // ====================================================================
async carregarLembretesHoje() {
    try {
        const token = this.getToken();
        const resp = await fetch('/v1/marketing/lembretes-hoje', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        this.lembretesHoje = data || [];
        
                // Verificar lembretes pendentes para alertar
        this.verificarLembretesPendentes();
    } catch (e) {
        console.error('Erro ao carregar lembretes de hoje:', e);
    }
},

        // ====================================================================
        // VERIFICAR LEMBRETES PENDENTES
        // ====================================================================
verificarLembretesPendentes() {
    const agora = new Date();
    const horaAtual = agora.getHours();
    const minAtual = agora.getMinutes();
    
    this.lembretesHoje.forEach(lembrete => {
        if (lembrete.concluido) return;
        
        const [hora, min] = (lembrete.hora_lembrete || '09:00').split(':').map(Number);
        const diffMin = (hora * 60 + min) - (horaAtual * 60 + minAtual);
        
                // Alertar se for dentro dos próximos 15 minutos
        if (diffMin >= 0 && diffMin <= 15) {
            this.mostrarAlertaLembrete(lembrete);
        }
    });
},

        // ====================================================================
        // MOSTRAR ALERTA DE LEMBRETE
        // ====================================================================
mostrarAlertaLembrete(lembrete) {
            // Evitar duplicatas
    const jaAlertado = localStorage.getItem(`lembrete_alert_${lembrete.id}`);
    if (jaAlertado) return;
    
    const clienteNome = lembrete.cliente_nome || 'Cliente';
    const hora = lembrete.hora_lembrete || '09:00';
    
            // Tocar som
    try {
        const audio = new Audio('data:audio/wav;base64,U3RlYWx0aCBzb3VuZA==');
        audio.play().catch(() => {});
    } catch(e) {}
    
            // Notificação do navegador
    if ('Notification' in window && Notification.permission === 'granted') {
        new Notification('⏰ Lembrete pendente!', {
            body: `${clienteNome} - ${lembrete.descricao} às ${hora}`,
            icon: '/favicon.ico'
        });
    }
    
            // Toast
    Swal.fire({
        icon: 'info',
        title: '⏰ Lembrete pendente!',
        html: `
                    <div class="text-left">
                        <p><strong>Cliente:</strong> ${clienteNome}</p>
                        <p><strong>Descrição:</strong> ${lembrete.descricao}</p>
                        <p><strong>Horário:</strong> ${hora}</p>
                    </div>
            `,
            timer: 10000,
            showConfirmButton: true,
            confirmButtonText: 'Ver cliente',
            confirmButtonColor: '#f59e0b'
        }).then((result) => {
            if (result.isConfirmed && lembrete.id_cliente) {
                window.editarCliente(lembrete.id_cliente, 'CRM');
            }
        });
        
            // Marcar como alertado
        localStorage.setItem(`lembrete_alert_${lembrete.id}`, 'true');
        
            // Limpar após 1 hora (para permitir novo alerta se necessário)
        setTimeout(() => {
            localStorage.removeItem(`lembrete_alert_${lembrete.id}`);
        }, 3600000);
    },
    
        // ====================================================================
        // INICIAR VERIFICAÇÃO DE LEMBRETES
        // ====================================================================
    iniciarVerificacaoLembretes() {
            // Verificar imediatamente
        this.carregarLembretesHoje();
        
            // Verificar a cada 30 segundos
        this.intervaloLembretes = setInterval(() => {
            this.carregarLembretesHoje();
        }, 30000);
    },
        // ====================================================================
        // LIMPAR INTERVALO DE LEMBRETES
        // ====================================================================
    limparVerificacaoLembretes() {
        if (this.intervaloLembretes) {
            clearInterval(this.intervaloLembretes);
            this.intervaloLembretes = null;
        }
    },
    

// ====================================================================
// VERIFICAÇÃO DE COMPROMISSOS EM TEMPO REAL (SEM CRON)
// ====================================================================

    async verificarCompromissosProximos() {
        try {
            const token = this.getToken();
            if (!token) return;
            
            const resp = await fetch('/v1/marketing/compromissos/meus-proximos', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            
            if (!resp.ok) return;
            
            const data = await resp.json();
            
            if (data.success && data.compromissos) {
                const urgentes = data.compromissos.filter(c => 
                    c.urgencia === 'iminente' || c.urgencia === 'proximo'
                    );
                
            // Para cada compromisso urgente não notificado
                urgentes.forEach(comp => {
                    if (!comp.ja_notificado) {
                        this.mostrarAlertaCompromisso(comp);
                    }
                });
            }
            
        } catch (e) {
            console.error('Erro ao verificar compromissos:', e);
        }
    },

    mostrarAlertaCompromisso(comp) {
        const icone = comp.urgencia === 'iminente' ? '🔴' : '🟡';
        const titulo = comp.urgencia === 'iminente' ? 'Compromisso iminente!' : 'Compromisso em breve!';
        const clienteNome = comp.cliente_nome || 'Cliente';
        const dataHora = new Date(comp.data_hora).toLocaleString('pt-BR');
        
    // Tocar som de notificação
        try {
            const audio = new Audio('data:audio/wav;base64,U3RlYWx0aCBzb3VuZA==');
            audio.play().catch(() => {});
        } catch(e) {}
        
    // Exibir notificação do navegador
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('📅 ' + titulo, {
                body: `${clienteNome} - ${comp.titulo || comp.tipo} às ${dataHora}`,
                icon: '/favicon.ico'
            });
        }
        
    // Exibir toast
        Swal.fire({
            icon: 'warning',
            title: `${icone} ${titulo}`,
            html: `
            <div class="text-left">
                <p><strong>Cliente:</strong> ${clienteNome}</p>
                <p><strong>Título:</strong> ${comp.titulo || comp.tipo}</p>
                <p><strong>Data/Hora:</strong> ${dataHora}</p>
                ${comp.horas_para_inicio <= 0.25 ? '<p class="text-red-500 font-bold mt-2">⚠️ Começa em menos de 15 minutos!</p>' : ''}
            </div>
                `,
                timer: 15000,
                showConfirmButton: true,
                confirmButtonText: 'Ver cliente',
                confirmButtonColor: '#3b82f6',
                showCancelButton: true,
                cancelButtonText: 'Ignorar'
            }).then((result) => {
                if (result.isConfirmed && comp.id_cliente) {
                    window.editarCliente(comp.id_cliente, 'CRM');
                }
            });

    // ⚠️ NOVO: Criar notificação no banco após exibir o alerta
            this.criarNotificacaoCompromisso(comp);
        },

/**
 * Criar notificação no banco após exibir o alerta
 */
        async criarNotificacaoCompromisso(comp) {
            try {
                const token = this.getToken();
                if (!token) return;

        // Criar notificação via API
                const resp = await fetch('/v1/crm/notificacoes', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        titulo: `📅 ${comp.urgencia === 'iminente' ? 'Compromisso iminente!' : 'Compromisso em breve!'}`,
                        mensagem: `Compromisso com ${comp.cliente_nome || 'Cliente'} às ${new Date(comp.data_hora).toLocaleString('pt-BR')}`,
                        tipo: 'compromisso',
                        id_referencia: comp.id,
                        link: `/portal/modules/marketing/clientes.php?id=${comp.id_cliente}`
                    })
                });

                if (resp.ok) {
                    console.log('✅ Notificação criada no banco para compromisso:', comp.id);
                }
            } catch (e) {
                console.error('Erro ao criar notificação:', e);
            }
        },

        iniciarVerificacaoCompromissos() {
    // Verificar imediatamente
            this.verificarCompromissosProximos();

    // Verificar a cada 30 segundos
            this.intervaloVerificacao = setInterval(() => {
                this.verificarCompromissosProximos();
            }, 60000);
        },

// Limpar intervalo quando sair
        limparVerificacaoCompromissos() {
            if (this.intervaloVerificacao) {
                clearInterval(this.intervaloVerificacao);
                this.intervaloVerificacao = null;
            }
        },

        async carregarEstatisticasMes() {
            try {
                const token = this.getToken();
                const resp = await fetch('/v1/marketing/compromissos/estatisticas', {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await resp.json();
                this.stats.totalCompromissosMes = data.total || 0;
                this.stats.concluidosMes = data.concluidos || 0;
            } catch (e) {
                console.error(e);
            }
        },

        async concluirCompromisso(id) {
            try {
                const token = this.getToken();
                await fetch(`/v1/marketing/compromissos/${id}/concluir`, {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Concluído!',
                    timer: 1500,
                    showConfirmButton: false
                });
                await this.atualizarTudoAposCadastro();
            } catch (e) {
                console.error(e);
            }
        },

        async atualizarTudoAposCadastro() {
            if (this.calendario) this.calendario.refetchEvents();
            await this.carregarProximosCompromissos();
            await this.carregarEstatisticasMes();
        },

        // ====================================================================
        // CALENDÁRIO
        // ====================================================================
        inicializarCalendario() {
            const calendarEl = document.getElementById('calendarioAgenda');
            if (!calendarEl) return;

            this.calendario = new FullCalendar.Calendar(calendarEl, {
                locale: 'pt-br',
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                slotMinTime: '08:00:00',
                slotMaxTime: '20:00:00',
                slotDuration: '00:30:00',
                slotLabelInterval: '01:00',
                allDaySlot: false,
                nowIndicator: true,
                editable: true,
                selectable: true,
                selectMirror: true,
                dayMaxEvents: true,
                weekends: true,
                selectMinDistance: 0,

                eventDidMount: (info) => {
                    const tipo = info.event.extendedProps.tipo;
                    const status = info.event.extendedProps.status;
                    let bgColor = '#3b82f6';
                    if (status === 'concluido') bgColor = '#10b981';
                    else if (status === 'cancelado') bgColor = '#ef4444';
                    else if (tipo === 'reuniao') bgColor = '#3b82f6';
                    else if (tipo === 'ligacao') bgColor = '#f59e0b';
                    else if (tipo === 'visita') bgColor = '#8b5cf6';
                    else if (tipo === 'whatsapp') bgColor = '#25D366';
                    else if (tipo === 'email') bgColor = '#6b7280';
                    info.el.style.backgroundColor = bgColor;
                    info.el.style.borderLeft = '3px solid rgba(255,255,255,0.5)';
                },

                select: (info) => {
                    this.abrirAgendamentoRapido(info.start, info.end);
                },
                eventResize: async (info) => {
                    await this.atualizarHorarioCompromisso(info.event.id, info.event.start, info.event.end);
                },
                eventDrop: async (info) => {
                    await this.atualizarHorarioCompromisso(info.event.id, info.event.start, info.event.end);
                },

                events: async (fetchInfo, successCallback) => {
                    try {
                        const token = this.getToken();
                        const filtroAtivo = window.filtroAgendaAtual || 'todos';
                        let url =
                    `/v1/marketing/compromissos?inicio=${fetchInfo.start.toISOString()}&fim=${fetchInfo.end.toISOString()}`;
                    if (filtroAtivo !== 'todos') url += `&tipo=${filtroAtivo}`;
                    const resp = await fetch(url, {
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                    const data = await resp.json();
                    const eventos = (data.data || []).map(c => ({
                        id: c.id,
                        title: `${c.cliente_nome || 'Cliente'} - ${c.titulo || c.tipo}`,
                        start: c.data_hora,
                        end: c.data_hora_fim || null,
                        extendedProps: {
                            cliente_id: c.id_cliente,
                            cliente_nome: c.cliente_nome,
                            tipo: c.tipo,
                            status: c.status,
                            descricao: c.descricao,
                            data_hora: c.data_hora,
                            data_hora_fim: c.data_hora_fim
                        }
                    }));
                    successCallback(eventos);
                } catch (e) {
                    successCallback([]);
                }
            },

            eventClick: (info) => {
                Swal.fire({
                    title: '<div class="flex items-center gap-2"><i class="fa-regular fa-calendar-check text-blue-500"></i> Detalhes do Compromisso</div>',
                    html: `
                            <div class="text-left space-y-3">
                                <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Cliente</p><p class="font-bold text-slate-800">${info.event.extendedProps.cliente_nome || '-'}</p></div>
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Início</p><p class="font-bold text-slate-800">${new Date(info.event.start).toLocaleString('pt-BR')}</p></div>
                                    <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Término</p><p class="font-bold text-slate-800">${info.event.end ? new Date(info.event.end).toLocaleString('pt-BR') : '—'}</p></div>
                                </div>
                                <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Tipo</p><p class="font-bold">${info.event.extendedProps.tipo}</p></div>
                                <div class="bg-slate-50 p-3 rounded-xl"><p class="text-xs text-slate-400">Descrição</p><p class="text-sm text-slate-600">${info.event.extendedProps.descricao || '—'}</p></div>
                                <div class="bg-amber-50 p-3 rounded-xl"><p class="text-xs text-amber-600">Status</p><p class="font-bold">${info.event.extendedProps.status === 'agendado' ? '📅 Agendado' : (info.event.extendedProps.status === 'concluido' ? '✅ Concluído' : '❌ Cancelado')}</p></div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: '<i class="fa-solid fa-pen mr-1"></i>Editar',
                        cancelButtonText: 'Fechar',
                        showDenyButton: info.event.extendedProps.status === 'agendado',
                        denyButtonText: '<i class="fa-solid fa-check mr-1"></i>Concluir',
                        showCloseButton: true,
                        confirmButtonColor: '#3b82f6',
                        denyButtonColor: '#10b981'
                    }).then(result => {
                        if (result.isConfirmed) this.abrirEdicaoCompromisso(info.event.id, info.event.extendedProps);
                        else if (result.isDenied) this.concluirCompromisso(info.event.id);
                    });
                }
            });
this.calendario.render();
this.carregarEstatisticasMes();
},

async atualizarHorarioCompromisso(id, start, end) {
    try {
        const token = this.getToken();
        await fetch(`/v1/marketing/compromissos/${id}`, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                data_hora: start.toISOString(),
                data_hora_fim: end ? end.toISOString() : null
            })
        });
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'success',
            title: 'Horário atualizado!',
            timer: 1500,
            showConfirmButton: false
        });
        await this.atualizarTudoAposCadastro();
    } catch (e) {
        console.error(e);
    }
},

        // ====================================================================
        // AGENDAMENTO RÁPIDO
        // ====================================================================
abrirAgendamentoRapido(start, end) {
    const formatarDateTime = (date) => {
        if (!date) return '';
        const ano = date.getFullYear();
        const mes = String(date.getMonth() + 1).padStart(2, '0');
        const dia = String(date.getDate()).padStart(2, '0');
        const horas = String(date.getHours()).padStart(2, '0');
        const minutos = String(date.getMinutes()).padStart(2, '0');
        return `${ano}-${mes}-${dia}T${horas}:${minutos}`;
    };

    const dataHoraInicio = formatarDateTime(start);
    const dataHoraFim = end && end > start ? formatarDateTime(end) : '';
    const duracaoMinutos = end && end > start ? Math.round((end - start) / 60000) : 0;

    Swal.fire({
        title: '<i class="fa-regular fa-calendar-plus mr-2 text-blue-500"></i>Novo Compromisso',
        html: `
                    <div class="space-y-3 text-left">
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Cliente *</label><select id="rapidoCliente" class="w-full p-2 border rounded-xl text-sm"><option value="">Carregando clientes...</option></select></div>
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Início *</label><input type="datetime-local" id="rapidoDataHoraInicio" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraInicio}"></div>
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Término</label><input type="datetime-local" id="rapidoDataHoraFim" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraFim}"></div>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 mb-1">Tipo</label>
                            <div class="flex gap-2">
                                <button type="button" data-tipo="reuniao" class="tipo-btn flex-1 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-blue-100 hover:text-blue-600"><i class="fa-regular fa-calendar mr-1"></i> Reunião</button>
                                <button type="button" data-tipo="ligacao" class="tipo-btn flex-1 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-amber-100 hover:text-amber-600"><i class="fa-solid fa-phone mr-1"></i> Ligação</button>
                                <button type="button" data-tipo="visita" class="tipo-btn flex-1 py-2 rounded-xl text-sm font-bold transition-all bg-slate-100 text-slate-600 hover:bg-purple-100 hover:text-purple-600"><i class="fa-solid fa-building mr-1"></i> Visita</button>
                            </div>
                            <input type="hidden" id="rapidoTipo" value="reuniao">
                        </div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Título</label><input type="text" id="rapidoTitulo" class="w-full p-2 border rounded-xl text-sm" placeholder="Ex: Follow-up proposta"></div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Descrição</label><textarea id="rapidoDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm"></textarea></div>
            ${duracaoMinutos > 0 ? `<div class="bg-blue-50 p-3 rounded-lg border border-blue-200"><i class="fa-regular fa-clock text-blue-500 mr-1"></i><span class="text-sm text-blue-700">Intervalo selecionado: <strong>${duracaoMinutos} minutos</strong></span></div>` : '<div class="text-xs text-slate-400 bg-slate-50 p-2 rounded-lg"><i class="fa-regular fa-clock mr-1"></i>Clique e arraste no calendário para selecionar um intervalo</div>'}
                    </div>
            `,
            width: '550px',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-regular fa-save mr-1"></i>Agendar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            didOpen: async () => {
                await this.carregarClientesParaSelectRapido();

                const botoes = document.querySelectorAll('.tipo-btn');
                const atualizarEstiloBotoes = (btnAtivo) => {
                    botoes.forEach(btn => {
                        btn.classList.remove('bg-blue-500', 'bg-amber-500', 'bg-purple-500', 'text-white');
                        btn.classList.add('bg-slate-100', 'text-slate-600');
                    });
                    if (btnAtivo) {
                        btnAtivo.classList.remove('bg-slate-100', 'text-slate-600');
                        btnAtivo.classList.add('bg-blue-500', 'text-white');
                    }
                };
                botoes.forEach(btn => {
                    btn.removeEventListener('click', this.tipoClickHandler);
                    this.tipoClickHandler = () => {
                        document.getElementById('rapidoTipo').value = btn.getAttribute('data-tipo');
                        atualizarEstiloBotoes(btn);
                    };
                    btn.addEventListener('click', this.tipoClickHandler);
                });
                const primeiroBotao = document.querySelector('.tipo-btn[data-tipo="reuniao"]');
                if (primeiroBotao) atualizarEstiloBotoes(primeiroBotao);
            },
            preConfirm: () => {
                const clienteId = document.getElementById('rapidoCliente').value;
                const dataHoraInicio = document.getElementById('rapidoDataHoraInicio').value;
                const dataHoraFim = document.getElementById('rapidoDataHoraFim').value || null;
                const tipo = document.getElementById('rapidoTipo').value;
                const titulo = document.getElementById('rapidoTitulo').value;
                const descricao = document.getElementById('rapidoDescricao').value;
                if (!clienteId || !dataHoraInicio) {
                    Swal.showValidationMessage('Preencha cliente e data/hora de início');
                    return false;
                }
                if (dataHoraFim && new Date(dataHoraFim) <= new Date(dataHoraInicio)) {
                    Swal.showValidationMessage('A data/hora de término deve ser maior que a data/hora de início');
                    return false;
                }
                return { clienteId, dataHoraInicio, dataHoraFim, tipo, titulo, descricao };
            }
        }).then(async (result) => {
            if (result.isConfirmed) await this.salvarCompromissoRapido(result.value);
        });
    },

    async carregarClientesParaSelectRapido() {
     try {
        const token = this.getToken();
        const resp = await fetch('/v1/marketing/clientes/consulta-otimizado?limite=100', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        const select = document.getElementById('rapidoCliente');
        const clientes = data.clientes || [];
        select.innerHTML = '<option value="">Selecione um cliente...</option>' +
        clientes.map(c => `<option value="${c.id_crm || c.id_erp}">${c.nome || c.empresa || 'Cliente'}</option>`).join('');
    } catch (e) {
        console.error(e);
    }
},

async salvarCompromissoRapido(dados) {
    try {
        const token = this.getToken();
        const resp = await fetch('/v1/marketing/compromissos', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_cliente: parseInt(dados.clienteId),
                data_hora: dados.dataHoraInicio,
                data_hora_fim: dados.dataHoraFim || null,
                tipo: dados.tipo,
                titulo: dados.titulo || dados.tipo,
                descricao: dados.descricao,
                status: 'agendado'
            })
        });
        const result = await resp.json();
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Agendado!',
                timer: 1500,
                showConfirmButton: false
            });
            await this.atualizarTudoAposCadastro();
        }
    } catch (e) {
        Swal.fire('Erro', e.message, 'error');
    }
},

        // ====================================================================
        // MÉTODOS DO MODAL DE CLIENTE
        // ====================================================================
async carregarInteracoes(id) {
    try {
        const token = this.getToken();
        const resp = await fetch(`/v1/marketing/clientes/${id}/interacoes`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const interacoes = await resp.json();
        const container = document.getElementById('historicoInteracoes');
        if (!container) return;
        if (!interacoes?.length) {
            container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhuma interação registrada</p>';
            return;
        }
        container.innerHTML = interacoes.map(i => `
                    <div class="timeline-item" style="--timeline-color: ${i.tipo === 'ligacao' ? '#f59e0b' : (i.tipo === 'whatsapp' ? '#25D366' : '#3b82f6')}">
                        <div class="timeline-icon"><i class="fa-solid ${i.tipo === 'ligacao' ? 'fa-phone' : (i.tipo === 'whatsapp' ? 'fa-whatsapp' : 'fa-envelope')} text-sm"></i></div>
                        <div class="timeline-content">
                            <div class="flex justify-between items-start"><span class="font-bold text-sm">${i.tipo}</span><span class="text-xs text-slate-400">${i.data_interacao} ${i.hora_interacao}</span></div>
                            <p class="text-sm mt-1">${i.descricao}</p><span class="text-xs text-slate-400">Por: ${i.usuario || 'Sistema'}</span>
                        </div>
                    </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
},

async carregarLembretes(id) {
    try {
        const token = this.getToken();
        const resp = await fetch('/v1/marketing/lembretes', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const lembretes = await resp.json();
        const filtrados = (lembretes || []).filter(l => l.id_cliente == id);
        const container = document.getElementById('historicoLembretes');
        if (!container) return;
        if (!filtrados.length) {
            container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum lembrete</p>';
            return;
        }
        container.innerHTML = filtrados.map(l => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border ${l.concluido ? 'border-emerald-200' : 'border-amber-200'}">
                        <div><div class="font-bold">${l.descricao}</div><div class="text-xs text-slate-400">${l.data_lembrete} ${l.hora_lembrete}</div></div>
            ${!l.concluido ? `<button onclick="window.concluirLembrete(${l.id})" class="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-bold">Concluir</button>` : '<span class="text-emerald-600 text-xs">✅ Concluído</span>'}
                    </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
},

async carregarAnexos(id) {
    try {
        const token = this.getToken();
        const resp = await fetch(`/v1/marketing/clientes/${id}/anexos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const anexos = await resp.json();
        const container = document.getElementById('historicoAnexos');
        if (!container) return;
        if (!anexos?.length) {
            container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum anexo</p>';
            return;
        }
        container.innerHTML = anexos.map(a => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border">
                        <div><span class="font-bold">${a.nome_original}</span><div class="text-xs text-slate-400">${new Date(a.created_at).toLocaleDateString('pt-BR')}</div></div>
                        <div class="flex gap-2">
                            <a href="/v1/marketing/anexos/${a.id}/download" class="px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-bold">Download</a>
                            <button onclick="window.deletarAnexo(${a.id})" class="px-3 py-1.5 bg-rose-500 text-white rounded-lg text-xs font-bold">Excluir</button>
                        </div>
                    </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
},

async carregarCompromissosCliente(idCliente) {
    try {
        const token = this.getToken();
        const resp = await fetch(`/v1/marketing/compromissos/cliente/${idCliente}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const compromissos = await resp.json();
        const container = document.getElementById('listaCompromissos');
        if (!container) return;
        if (!compromissos.length) {
            container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum compromisso agendado</p>';
            return;
        }
        container.innerHTML = compromissos.map(c => `
                    <div class="flex justify-between items-center bg-white p-3 rounded-xl border border-slate-200">
                        <div>
                            <div class="flex items-center gap-2"><span class="font-bold text-sm">${c.titulo || c.tipo}</span><span class="px-2 py-0.5 rounded-full text-xs ${c.status === 'agendado' ? 'bg-blue-100 text-blue-700' : (c.status === 'concluido' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700')}">${c.status === 'agendado' ? 'Agendado' : (c.status === 'concluido' ? 'Concluído' : 'Cancelado')}</span></div>
                            <div class="text-xs text-slate-400">${new Date(c.data_hora).toLocaleString('pt-BR')}</div>
                            <p class="text-sm mt-1">${c.descricao || ''}</p>
                        </div>
                        <div class="flex gap-1">
            ${c.status === 'agendado' ? `<button onclick="window.concluirCompromisso(${c.id})" class="p-1.5 text-emerald-600 hover:text-emerald-700" title="Concluir"><i class="fa-solid fa-check"></i></button>` : ''}
                            <button onclick="window.excluirCompromisso(${c.id})" class="p-1.5 text-rose-500 hover:text-rose-600" title="Excluir"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
},

        // ====================================================================
        // AUTO REFRESH
        // ====================================================================
iniciarAutoRefresh() {
    setInterval(() => {
        this.carregarAlertas();
        this.carregarProximosCompromissos();
        if (this.calendario) this.calendario.refetchEvents();
    }, 60000);
},

        // ====================================================================
        // EDIÇÃO DE COMPROMISSO
        // ====================================================================
abrirEdicaoCompromisso(id, dados) {
    const dataHoraInicio = dados.data_hora ? dados.data_hora.slice(0, 16) : '';
    const dataHoraFim = dados.data_hora_fim ? dados.data_hora_fim.slice(0, 16) : '';

    Swal.fire({
        title: 'Editar Compromisso',
        html: `
                    <div class="space-y-3 text-left">
                        <div class="grid grid-cols-2 gap-2">
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Início</label><input type="datetime-local" id="editDataHoraInicio" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraInicio}"></div>
                            <div><label class="block text-xs font-bold text-slate-400 mb-1">Término</label><input type="datetime-local" id="editDataHoraFim" class="w-full p-2 border rounded-xl text-sm" value="${dataHoraFim}"></div>
                        </div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Tipo</label><select id="editTipo" class="w-full p-2 border rounded-xl text-sm">
                            <option value="reuniao" ${dados.tipo === 'reuniao' ? 'selected' : ''}>📅 Reunião</option>
                            <option value="ligacao" ${dados.tipo === 'ligacao' ? 'selected' : ''}>📞 Ligação</option>
                            <option value="visita" ${dados.tipo === 'visita' ? 'selected' : ''}>🏢 Visita</option>
                            <option value="whatsapp" ${dados.tipo === 'whatsapp' ? 'selected' : ''}>💬 WhatsApp</option>
                            <option value="email" ${dados.tipo === 'email' ? 'selected' : ''}>📧 Email</option>
                        </select></div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Título</label><input type="text" id="editTitulo" class="w-full p-2 border rounded-xl text-sm" value="${dados.titulo || ''}"></div>
                        <div><label class="block text-xs font-bold text-slate-400 mb-1">Descrição</label><textarea id="editDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm">${dados.descricao || ''}</textarea></div>
                    </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Salvar',
            cancelButtonText: 'Cancelar',
            preConfirm: () => {
                const dataHoraInicio = document.getElementById('editDataHoraInicio').value;
                const dataHoraFim = document.getElementById('editDataHoraFim').value || null;
                if (!dataHoraInicio) {
                    Swal.showValidationMessage('Data/hora de início é obrigatória');
                    return false;
                }
                if (dataHoraFim && new Date(dataHoraFim) <= new Date(dataHoraInicio)) {
                    Swal.showValidationMessage('O término deve ser maior que o início');
                    return false;
                }
                return {
                    data_hora: dataHoraInicio,
                    data_hora_fim: dataHoraFim,
                    tipo: document.getElementById('editTipo').value,
                    titulo: document.getElementById('editTitulo').value,
                    descricao: document.getElementById('editDescricao').value
                };
            }
        }).then(async (result) => {
            if (result.isConfirmed) await this.atualizarCompromissoCompleto(id, result.value);
        });
    },

    async atualizarCompromissoCompleto(id, dados) {
        try {
            const token = this.getToken();
            const resp = await fetch(`/v1/marketing/compromissos/${id}`, {
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            });
            const result = await resp.json();
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Atualizado!',
                    timer: 1500,
                    showConfirmButton: false
                });
                await this.atualizarTudoAposCadastro();
            }
        } catch (e) {
            Swal.fire('Erro', e.message, 'error');
        }
    },

    editarCliente(id, origem) {
        window.editarCliente(id, origem);
    }
};
}

// ============================================================================
// FUNÇÕES GLOBAIS PARA AGENDA DE COMPROMISSOS
// ============================================================================

/**
 * Retorna a classe CSS baseada na proximidade do compromisso
 */
function getProximidadeClass(dataHora) {
    if (!dataHora) return 'bg-slate-100 text-slate-500';
    
    const data = new Date(dataHora);
    const agora = new Date();
    const diffMs = data - agora;
    const diffMin = Math.floor(diffMs / 60000);
    
    if (diffMin < 0) return 'bg-rose-100 text-rose-700';
    if (diffMin < 15) return 'bg-red-100 text-red-700 font-bold';
    if (diffMin < 60) return 'bg-amber-100 text-amber-700';
    if (diffMin < 1440) return 'bg-blue-100 text-blue-700';
    return 'bg-slate-100 text-slate-500';
}

/**
 * Formata a data de forma resumida para a agenda
 */
function formatarDataProxima(dataHora) {
    if (!dataHora) return '—';
    
    const data = new Date(dataHora);
    const agora = new Date();
    const diffMs = data - agora;
    const diffMin = Math.floor(diffMs / 60000);
    const diffHoras = Math.floor(diffMs / 3600000);
    const diffDias = Math.floor(diffMs / 86400000);
    
    if (diffMin < 0) return 'Atrasado';
    if (diffMin < 1) return 'Agora';
    if (diffMin < 60) return `${diffMin} min`;
    if (diffHoras < 24) return `${diffHoras}h`;
    if (diffDias === 0) return 'Hoje';
    if (diffDias === 1) return 'Amanhã';
    if (diffDias < 7) return `${diffDias} dias`;
    
    return data.toLocaleDateString('pt-BR');
}

/**
 * Formata apenas a hora
 */
function formatarHora(dataHora) {
    if (!dataHora) return '—';
    const data = new Date(dataHora);
    return data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

/**
 * Calcula a duração entre duas datas
 */
function calcularDuracao(inicio, fim) {
    if (!inicio || !fim) return '—';
    
    const inicioDate = new Date(inicio);
    const fimDate = new Date(fim);
    const diffMs = fimDate - inicioDate;
    const diffMin = Math.floor(diffMs / 60000);
    
    if (diffMin < 1) return '—';
    if (diffMin < 60) return `${diffMin} min`;
    
    const horas = Math.floor(diffMin / 60);
    const minutos = diffMin % 60;
    
    if (minutos === 0) return `${horas}h`;
    return `${horas}h${minutos}min`;
}
// ============================================================================
// NOTIFICAÇÕES CRM
// ============================================================================
function notificacoesCRM() {
    return {
        aberto: false,
        notificacoes: [],
        naoLidas: 0,
        intervalo: null,

        async init() {
            await this.carregarNotificacoes();
            this.iniciarPolling();
        },

        getToken() {
            return localStorage.getItem('authToken');
        },

        async fetchWithAuth(url, options = {}) {
            const token = this.getToken();
            if (!token) return null;
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                }
            });
            if (response.status === 401) return null;
            return response;
        },

        async carregarNotificacoes() {
            try {
                const resp = await this.fetchWithAuth('/v1/crm/notificacoes?limite=30');
                if (!resp) return;
                const data = await resp.json();
                if (data.success) {
                    this.notificacoes = data.notificacoes || [];
                    this.naoLidas = data.nao_lidas || 0;
                    if (this.naoLidas > 0 && !this.aberto && this.naoLidas !== (window._ultimoCount || 0)) this.tocarSom();
                    window._ultimoCount = this.naoLidas;
                }
            } catch (e) {
                console.error('Erro carregar notificações CRM:', e);
            }
        },

        iniciarPolling() {
            this.intervalo = setInterval(() => {
                this.carregarNotificacoes();
            }, 60000);
        },

        tocarSom() {
            try {
                new Audio('data:audio/wav;base64,U3RlYWx0aCBzb3VuZA==').play().catch(e => console.log('Som não suportado'));
            } catch (e) {}
        },

        toggleAbrir() {
            this.aberto = !this.aberto;
        },

        async marcarLida(id) {
            try {
                await this.fetchWithAuth(`/v1/crm/notificacoes/${id}/ler`, { method: 'PUT' });
                const notif = this.notificacoes.find(n => n.id === id);
                if (notif && !notif.lida) {
                    notif.lida = true;
                    this.naoLidas = Math.max(0, this.naoLidas - 1);
                }
            } catch (e) {
                console.error(e);
            }
        },

        async marcarTodasLidas() {
            try {
                await this.fetchWithAuth('/v1/crm/notificacoes/ler-todas', { method: 'PUT' });
                this.notificacoes.forEach(n => n.lida = true);
                this.naoLidas = 0;
            } catch (e) {
                console.error(e);
            }
        },

        handleClick(notif) {
            if (!notif.lida) this.marcarLida(notif.id);
            this.aberto = false;
            if (notif.tipo === 'compromisso' && notif.id_referencia) window.editarCliente(notif.id_referencia);
            else if (notif.tipo === 'lead_parado' && notif.id_referencia) window.editarCliente(notif.id_referencia);
            else if (notif.tipo === 'meta_prazo' && notif.link) window.location.href = notif.link;
            else if (notif.link) window.location.href = notif.link;
        },

        getIconClass(tipo) {
            const icons = {
                'compromisso': 'fa-regular fa-calendar-check',
                'lead_parado': 'fa-solid fa-hourglass-half',
                'meta_prazo': 'fa-solid fa-clock',
                'cliente_novo': 'fa-solid fa-user-plus',
                'interacao': 'fa-regular fa-comment',
                'sistema': 'fa-solid fa-bell'
            };
            return icons[tipo] || 'fa-solid fa-bell';
        },

        getIconBg(tipo) {
            const colors = {
                'compromisso': '#3b82f6',
                'lead_parado': '#f59e0b',
                'meta_prazo': '#ef4444',
                'cliente_novo': '#10b981',
                'interacao': '#8b5cf6',
                'sistema': '#64748b'
            };
            return colors[tipo] || '#64748b';
        },

        getTagClass(tipo) {
            const classes = {
                'compromisso': 'tag-compromisso',
                'lead_parado': 'tag-lead',
                'meta_prazo': 'tag-meta',
                'cliente_novo': 'tag-interacao',
                'interacao': 'tag-interacao'
            };
            return classes[tipo] || 'tag-meta';
        },

        getTagTexto(tipo) {
            const textos = {
                'compromisso': 'Agendamento',
                'lead_parado': 'Urgente',
                'meta_prazo': 'Prazo',
                'cliente_novo': 'Novo',
                'interacao': 'Interação'
            };
            return textos[tipo] || 'Info';
        },

        formatarTempo(dataISO) {
            if (!dataISO) return 'agora';
            const data = new Date(dataISO);
            const agora = new Date();
            const diffMs = agora - data;
            const diffMin = Math.floor(diffMs / 60000);
            const diffHoras = Math.floor(diffMs / 3600000);
            const diffDias = Math.floor(diffMs / 86400000);
            if (diffMin < 1) return 'agora mesmo';
            if (diffMin < 60) return `${diffMin} min atrás`;
            if (diffHoras < 24) return `${diffHoras} h atrás`;
            if (diffDias === 1) return 'ontem';
            if (diffDias < 7) return `${diffDias} dias atrás`;
            return data.toLocaleDateString('pt-BR');
        }
    };
}

// ============================================================================
// FUNÇÕES GLOBAIS
// ============================================================================
let tagsTemp = [];
let clienteAtualId = null;
let filtroAgendaAtual = 'todos';

function filtrarAgenda(tipo) {
    filtroAgendaAtual = tipo;
    document.querySelectorAll('.filter-agenda-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === tipo) btn.classList.add('active');
    });
    if (window.crmClientesApp && window.crmClientesApp.calendario) window.crmClientesApp.calendario.refetchEvents();
}

function abrirModalCliente() {
    const campos = [
        'clienteId',
        'clienteNome',
        'clienteEmpresa',
        'clienteTelefone',
        'clienteEmail',
        'clienteCidade',
        'clienteUF',
        'clienteValor',
        'clienteObs',
        'clienteCnpj',
        'clienteEndereco',
        'clienteNumero',
        'clienteBairro',
        'clienteCep',
        'clienteComplemento',
        'clienteDataCadastro'  // NOVO
    ];

    let todosExistem = true;
    campos.forEach(campo => {
        const el = document.getElementById(campo);
        if (el) {
            el.value = '';
        } else {
            console.warn('⚠️ Campo não encontrado:', campo);
            todosExistem = false;
        }
    });

    if (!todosExistem) {
        console.warn('⏳ Alguns campos não foram encontrados, tentando novamente em 500ms...');
        setTimeout(() => {
            abrirModalCliente();
        }, 500);
        return;
    }

    // Definir valores padrão
    const statusSelect = document.getElementById('clienteStatus');
    if (statusSelect) statusSelect.value = 'Novo';

    const termometroSelect = document.getElementById('clienteTermometro');
    if (termometroSelect) termometroSelect.value = 'Frio';

    const origemSelect = document.getElementById('clienteOrigem');
    if (origemSelect) origemSelect.value = 'Site';

    const metaSelect = document.getElementById('clienteMeta');
    if (metaSelect) metaSelect.value = '0';

    // ⭐ Definir data de cadastro como hoje
    const dataCadastroField = document.getElementById('clienteDataCadastro');
    if (dataCadastroField) {
        dataCadastroField.value = new Date().toISOString().split('T')[0];
    }

    tagsTemp = [];
    renderizarTags();

    const modal = document.getElementById('modalCliente');
    if (modal) {
        modal.classList.remove('hidden');
    } else {
        console.error('❌ Modal não encontrado!');
    }
}

function fecharModalCliente() {
    document.getElementById('modalCliente').classList.add('hidden');
}

function renderizarTags() {
    const container = document.getElementById('tagsContainer');
    if (!container) return;
    container.innerHTML = tagsTemp.map(t =>
`<span class="px-2 py-1 bg-violet-100 text-violet-700 rounded-full text-xs font-bold">${t}<button onclick="removerTag('${t}')" class="ml-1">×</button></span>`
).join('');
}

function adicionarTag() {
    const tag = document.getElementById('novaTag').value.trim();
    if (tag && !tagsTemp.includes(tag)) {
        tagsTemp.push(tag);
        renderizarTags();
        document.getElementById('novaTag').value = '';
    }
}

function removerTag(tag) {
    tagsTemp = tagsTemp.filter(t => t !== tag);
    renderizarTags();
}

async function salvarTags(idCliente) {
    if (!idCliente) return;
    try {
        const token = localStorage.getItem('authToken');
        await fetch(`/v1/marketing/clientes/${idCliente}/tags`, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ tags: tagsTemp })
        });
    } catch (e) {
        console.error(e);
    }
}

// ============================================================================
// WINDOW FUNCTIONS
// ============================================================================
window.editarCliente = async function(id, origem = null) {
    console.log('✏️ Editando cliente - ID:', id, 'Origem:', origem);

    if (origem === 'APENAS_ERP' || origem === 'ERP') {
        const idErp = id;

        try {
            const token = localStorage.getItem('authToken');
            const checkResp = await fetch(`/v1/marketing/clientes/consulta-otimizado?busca=${idErp}&limite=5`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const checkData = await checkResp.json();

            if (checkData.success && checkData.clientes && checkData.clientes.length > 0) {
                const clienteExistente = checkData.clientes.find(c => c.id_erp == idErp);
                if (clienteExistente && clienteExistente.id_crm) {
                    window.editarCliente(clienteExistente.id_crm, 'CRM');
                    return;
                }
            }
        } catch (e) {
            console.warn('Erro ao verificar cliente:', e);
        }

        const result = await Swal.fire({
            title: '🏭 Cliente do ERP',
            html: `<div class="text-left">
                <p>Este cliente está apenas no sistema ERP.</p>
                <p class="text-sm text-slate-500 mt-2">Para gerenciar interações, compromissos e lembretes, você precisa importá-lo para o CRM.</p>
                <div class="bg-amber-50 p-3 rounded-lg mt-3">
                    <i class="fa-solid fa-info-circle text-amber-500 mr-1"></i>
                    <span class="text-xs text-amber-700">Os dados do cliente serão mantidos, apenas será possível adicionar ações de CRM.</span>
                </div>
            </div>`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-cloud-arrow-down mr-1"></i>Importar para CRM',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981'
        });

        if (result.isConfirmed) {
            await window.importarClienteERP(idErp, '');
        }
        return;
    }

    if (!id) {
        Swal.fire('Atenção', 'ID do cliente não informado', 'warning');
        return;
    }

    clienteAtualId = id;
    Swal.fire({ title: 'Carregando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`/v1/marketing/clientes/${id}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (!resp.ok) throw new Error(`HTTP ${resp.status} - Cliente não encontrado`);

        const responseData = await resp.json();
        let c = responseData;
        if (responseData.data && !responseData.id) c = responseData.data;

        if (!c || (!c.id && !c.id_crm)) throw new Error('Dados do cliente inválidos');

        const clienteId = c.id || c.id_crm;

        // Preencher campos principais com verificação
        const clienteIdField = document.getElementById('clienteId');
        if (clienteIdField) clienteIdField.value = clienteId || '';

        const nomeField = document.getElementById('clienteNome');
        if (nomeField) nomeField.value = c.nome || '';

        const empresaField = document.getElementById('clienteEmpresa');
        if (empresaField) empresaField.value = c.empresa || '';

        const telefoneField = document.getElementById('clienteTelefone');
        if (telefoneField) telefoneField.value = c.telefone || '';

        const emailField = document.getElementById('clienteEmail');
        if (emailField) emailField.value = c.email || '';

        const cidadeField = document.getElementById('clienteCidade');
        if (cidadeField) cidadeField.value = c.cidade || '';

        const ufField = document.getElementById('clienteUF');
        if (ufField) ufField.value = c.uf || '';

        const statusField = document.getElementById('clienteStatus');
        if (statusField) statusField.value = c.status || 'Novo';

        const termometroField = document.getElementById('clienteTermometro');
        if (termometroField) termometroField.value = c.termometro || 'Frio';

        const origemField = document.getElementById('clienteOrigem');
        if (origemField) origemField.value = c.origem || 'Site';

        const valorField = document.getElementById('clienteValor');
        if (valorField) valorField.value = c.valor_negocio || '';

        const metaField = document.getElementById('clienteMeta');
        if (metaField) metaField.value = c.id_meta || '0';

        const obsField = document.getElementById('clienteObs');
        if (obsField) obsField.value = c.observacoes || '';

        const cnpjField = document.getElementById('clienteCnpj');
        if (cnpjField) cnpjField.value = c.cnpj_cpf || '';

        const enderecoField = document.getElementById('clienteEndereco');
        if (enderecoField) enderecoField.value = c.endereco || '';

        const numeroField = document.getElementById('clienteNumero');
        if (numeroField) numeroField.value = c.numero || '';

        const bairroField = document.getElementById('clienteBairro');
        if (bairroField) bairroField.value = c.bairro || '';

        const cepField = document.getElementById('clienteCep');
        if (cepField) cepField.value = c.cep || '';

        const complementoField = document.getElementById('clienteComplemento');
        if (complementoField) complementoField.value = c.complemento || '';

        const dataCadastroField = document.getElementById('clienteDataCadastro');
        if (dataCadastroField) {
            // Priorizar data do CRM, depois ERP
            const dataCadastro = c.data_cadastro_crm || c.data_cadastro_erp || c.data_cadastro;
            if (dataCadastro) {
                const data = new Date(dataCadastro);
                dataCadastroField.value = data.toISOString().split('T')[0];
            } else {
                dataCadastroField.value = new Date().toISOString().split('T')[0];
            }
        }

        if (c.tags) {
            tagsTemp = Array.isArray(c.tags) ? c.tags : c.tags.split(', ');
        } else {
            tagsTemp = [];
        }
        renderizarTags();

        if (window.crmClientesApp) {
            await Promise.all([
                window.crmClientesApp.carregarInteracoes(clienteId),
                window.crmClientesApp.carregarLembretes(clienteId),
                window.crmClientesApp.carregarAnexos(clienteId),
                window.crmClientesApp.carregarCompromissosCliente(clienteId)
            ]);
        }

        await carregarPedidosERP(clienteId);

        const modal = document.getElementById('modalCliente');
        if (modal) modal.classList.remove('hidden');

        const tituloModal = document.getElementById('modalClienteTitulo');
        if (tituloModal) tituloModal.innerHTML = `<i class="fa-solid fa-user-edit mr-2"></i>✏️ ${c.nome || 'Cliente'}`;
        Swal.close();

    } catch (e) {
        Swal.close();
        console.error('❌ Erro ao editar cliente:', e);
        Swal.fire('Erro', e.message || 'Falha ao carregar dados do cliente', 'error');
    }
};

window.importarClienteERP = async function(idErp, nome) {
    const nomeCliente = nome || `ID ${idErp}`;
    const result = await Swal.fire({
        title: 'Importar Cliente do ERP',
        html: `<div class="text-left">
            <p>Deseja importar o cliente <strong>${nomeCliente}</strong> do ERP para o CRM?</p>
            <p class="text-sm text-slate-500 mt-2">Após importar, você poderá:</p>
            <ul class="text-sm text-slate-500 mt-1 list-disc list-inside">
                <li>Registrar interações</li>
                <li>Criar compromissos na agenda</li>
                <li>Adicionar lembretes e follow-ups</li>
                <li>Anexar documentos</li>
                <li><strong>Arrastar entre colunas do Kanban</strong></li>
            </ul>
        </div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-cloud-arrow-down mr-1"></i>Importar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981'
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Importando...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(`/v1/marketing/clientes/importar-erp/${idErp}`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                }
            });

            const data = await resp.json();
            Swal.close();

            if (data.success || data.id_crm) {
                const idCrm = data.id_crm;
                if (window.crmClientesApp) {
                    await window.crmClientesApp.carregarClientes();
                }

                Swal.fire({
                    icon: 'success',
                    title: 'Importado!',
                    text: 'Cliente importado com sucesso para o CRM',
                    timer: 1500,
                    showConfirmButton: false
                });

                setTimeout(() => {
                    window.editarCliente(idCrm, 'CRM');
                }, 500);
            } else {
                Swal.fire('Erro', data.error || 'Falha na importação', 'error');
            }
        } catch (e) {
            Swal.close();
            Swal.fire('Erro', 'Erro ao conectar com o servidor: ' + e.message, 'error');
        }
    }
    return false;
};

window.visualizarClienteUnificado = async function(id, origem) {
    try {
        const token = localStorage.getItem('authToken');
        let url = origem === 'APENAS_ERP' || origem === 'ERP' ? `/v1/marketing/clientes/erp/${id}` : `/v1/marketing/clientes/${id}`;
        const resp = await fetch(url, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        const cliente = data.data || data;

        Swal.fire({
            title: `<div class="flex items-center gap-2"><i class="fa-solid fa-user-circle text-emerald-500"></i> ${cliente.nome || cliente.nome_crm || 'Cliente'}</div>`,
            html: `
                <div class="text-left space-y-3 max-h-96 overflow-y-auto">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div class="bg-slate-50 p-2 rounded"><span class="text-xs text-slate-400">Empresa</span><p class="font-medium">${cliente.empresa || '—'}</p></div>
                        <div class="bg-slate-50 p-2 rounded"><span class="text-xs text-slate-400">Telefone</span><p class="font-medium">${cliente.telefone || '—'}</p></div>
                        <div class="bg-slate-50 p-2 rounded"><span class="text-xs text-slate-400">Email</span><p class="font-medium truncate">${cliente.email || '—'}</p></div>
                        <div class="bg-slate-50 p-2 rounded"><span class="text-xs text-slate-400">CNJ/CPF</span><p class="font-medium">${cliente.cnpj_cpf || cliente.cnpj || cliente.cpf || '—'}</p></div>
                    </div>
                ${cliente.endereco_completo ? `<div class="bg-slate-50 p-2 rounded"><span class="text-xs text-slate-400">Endereço</span><p class="text-sm">${cliente.endereco_completo}</p></div>` : ''}
                ${cliente.total_compras > 0 ? `<div class="bg-emerald-50 p-3 rounded-lg"><div class="grid grid-cols-3 gap-2 text-center"><div><span class="text-xs text-slate-400">Total Compras</span><p class="font-bold text-emerald-600">${MarketingUtils.formatarValor(cliente.total_compras, 'moeda')}</p></div><div><span class="text-xs text-slate-400">Qtd. Compras</span><p class="font-bold">${cliente.qtde_compras || 0}</p></div><div><span class="text-xs text-slate-400">Última Compra</span><p class="font-bold text-sm">${cliente.data_ultima_compra ? new Date(cliente.data_ultima_compra).toLocaleDateString('pt-BR') : '—'}</p></div></div></div>` : ''}
                    <div class="flex gap-2 mt-2 pt-2 border-t">
                        <span class="text-xs px-2 py-1 rounded-full ${cliente.status_crm === 'Fechado' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${cliente.status_crm || 'Novo'}</span>
                        <span class="text-xs px-2 py-1 rounded-full ${cliente.termometro === 'Quente' ? 'bg-rose-100 text-rose-700' : (cliente.termometro === 'Morno' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700')}">${cliente.termometro || 'Frio'}</span>
                        <span class="text-xs px-2 py-1 rounded-full bg-slate-100">${cliente.origem_dados === 'APENAS_CRM' ? '💾 CRM' : (cliente.origem_dados === 'APENAS_ERP' ? '🏭 ERP' : '🔄 Ambos')}</span>
                    </div>
                </div>
                `,
                width: '500px',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-pen mr-1"></i>Editar',
                cancelButtonText: 'Fechar',
                showDenyButton: cliente.origem_dados === 'APENAS_ERP',
                denyButtonText: '<i class="fa-solid fa-cloud-arrow-down mr-1"></i>Importar'
            }).then(result => {
                if (result.isConfirmed) window.editarCliente(cliente.id_crm || cliente.id_erp, cliente.id_crm ? 'CRM' :
                    'APENAS_ERP');
                    else if (result.isDenied) window.importarClienteERP(cliente.id_erp, cliente.nome);
            });
        } catch (e) {
            console.error(e);
            Swal.fire('Erro', 'Não foi possível carregar os detalhes', 'error');
        }
    };

    function removerEmojis(texto) {
        if (!texto) return '';
        return texto.replace(/[\u{1F600}-\u{1F9FF}]/gu, '').trim();
    }
    window.salvarCliente = async function() {
        const idField = document.getElementById('clienteId');
        const id = idField ? idField.value : '';

        const dados = {
            nome:  removerEmojis(document.getElementById('clienteNome')?.value || ''),
            empresa:  removerEmojis(document.getElementById('clienteEmpresa')?.value || ''),
            telefone: removerEmojis(document.getElementById('clienteTelefone')?.value || ''),
            email:  removerEmojis(document.getElementById('clienteEmail')?.value || ''),
            cnpj_cpf: removerEmojis(document.getElementById('clienteCnpj')?.value || ''), 
            cidade:  removerEmojis(document.getElementById('clienteCidade')?.value || ''),
            uf:  removerEmojis(document.getElementById('clienteUF')?.value || ''),
            status:  removerEmojis(document.getElementById('clienteStatus')?.value || 'Novo'),
            termometro:  removerEmojis(document.getElementById('clienteTermometro')?.value || 'Frio'),
            origem:  removerEmojis(document.getElementById('clienteOrigem')?.value || 'Site'),
            valor_negocio: parseFloat(document.getElementById('clienteValor')?.value || 0),
            id_meta: parseInt(document.getElementById('clienteMeta')?.value) || null,
            observacoes:  removerEmojis(document.getElementById('clienteObs')?.value || ''),
            endereco: removerEmojis(document.getElementById('clienteEndereco')?.value || ''),
            numero:  removerEmojis(document.getElementById('clienteNumero')?.value || ''),
            bairro:  removerEmojis(document.getElementById('clienteBairro')?.value || ''),
            cep:  removerEmojis(document.getElementById('clienteCep')?.value || ''),
            complemento:  removerEmojis(document.getElementById('clienteComplemento')?.value || ''),
            cnpj_cpf:  removerEmojis(document.getElementById('clienteCnpj')?.value || ''),
            data_cadastro:  removerEmojis(document.getElementById('clienteDataCadastro')?.value || new Date().toISOString().split('T')[0])
        };

        if (!dados.nome) {
            Swal.fire('Atenção', 'Nome é obrigatório', 'warning');
            return;
        }

        try {
            const token = localStorage.getItem('authToken');
            const url = id ? `/v1/marketing/clientes/${id}` : '/v1/marketing/clientes';
            const method = id ? 'PUT' : 'POST';

            const resp = await fetch(url, {
                method,
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            });

            const result = await resp.json();

            if (result.success) {
                if (clienteAtualId) await salvarTags(clienteAtualId);

                Swal.fire({
                    icon: 'success',
                    title: id ? 'Atualizado!' : 'Cadastrado!',
                    timer: 1500,
                    showConfirmButton: false
                });

                fecharModalCliente();

                if (window.crmClientesApp) {
                    window.crmClientesApp.carregarClientes();
                    window.crmClientesApp.carregarDashboard();
                    window.crmClientesApp.carregarAlertas();
                }
            } else {
                Swal.fire('Erro', result.error || 'Falha ao salvar cliente', 'error');
            }
        } catch (e) {
            Swal.fire('Erro', e.message || 'Erro ao conectar com o servidor', 'error');
        }
    };

// ============================================================================
// FUNÇÃO: MESCLAR CLIENTE COM ERP
// ============================================================================
    window.mesclarClienteERP = async function() {
        const idField = document.getElementById('clienteId');
        const id = idField ? idField.value : '';

        if (!id) {
            Swal.fire('Atenção', 'Salve o cliente primeiro', 'warning');
            return;
        }

        const cnpjField = document.getElementById('clienteCnpj');
        const telefoneField = document.getElementById('clienteTelefone');
        const emailField = document.getElementById('clienteEmail');

        const dados = {
            cnpj_cpf: cnpjField ? cnpjField.value : null,
            telefone: telefoneField ? telefoneField.value : null,
            email: emailField ? emailField.value : null
        };

    // Verificar se tem pelo menos um dado para buscar
        if (!dados.cnpj_cpf && !dados.telefone && !dados.email) {
            Swal.fire({
                icon: 'info',
                title: 'Dados insuficientes',
                text: 'Preencha o CNPJ, telefone ou email para buscar no ERP',
                confirmButtonText: 'OK'
            });
            return;
        }

        try {
            const token = localStorage.getItem('authToken');
            if (!token) {
                Swal.fire('Erro', 'Faça login novamente', 'error');
                return;
            }

            Swal.fire({
                title: '🔄 Mesclando com ERP...',
                html: 'Buscando cliente no ERP...',
                didOpen: () => Swal.showLoading(),
                allowOutsideClick: false
            });

            const resp = await fetch('/v1/marketing/clientes/mesclar-com-erp', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_crm: parseInt(id),
                    cnpj_cpf: dados.cnpj_cpf || null,
                    telefone: dados.telefone || null,
                    email: dados.email || null
                })
            });

            const result = await resp.json();
            Swal.close();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '✅ Mesclagem realizada!',
                    html: `
                    <div class="text-left">
                        <p><strong>Cliente mesclado com sucesso!</strong></p>
                        <p><strong>CRM ID:</strong> ${result.id_crm}</p>
                        <p><strong>ERP ID:</strong> ${result.id_erp}</p>
                        <p><strong>Origem:</strong> ${result.origem}</p>
                        ${result.dados_erp ? `
                            <div class="mt-2 p-2 bg-slate-50 rounded-lg">
                                <p class="text-xs font-bold text-slate-400">Dados do ERP:</p>
                                <p class="text-sm">${result.dados_erp.nome || ''}</p>
                                <p class="text-sm">${result.dados_erp.telefone || ''}</p>
                            </div>
                        ` : ''}
                    </div>
                        `,
                        timer: 4000,
                        showConfirmButton: true,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#10b981'
                    });

            // Recarregar a lista
                if (window.crmClientesApp) {
                    await window.crmClientesApp.carregarClientes();
                // Reabrir o cliente editado
                    setTimeout(() => {
                        window.editarCliente(id, 'CRM');
                    }, 500);
                }

                return result;
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Cliente não encontrado no ERP',
                    html: `
                    <div class="text-left">
                        <p>Não foi possível encontrar este cliente no ERP.</p>
                        <p class="text-sm text-slate-500 mt-2">Verifique os dados e tente novamente.</p>
                        <p class="text-xs text-slate-400 mt-1">Dicas:</p>
                        <ul class="text-xs text-slate-400 list-disc list-inside">
                            <li>Verifique o CNPJ/CPF</li>
                            <li>Verifique o telefone</li>
                            <li>Verifique o email</li>
                        </ul>
                    </div>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#f59e0b'
                    });
                return result;
            }

        } catch (error) {
            Swal.close();
            console.error('❌ Erro ao mesclar cliente:', error);
            Swal.fire({
                icon: 'error',
                title: 'Erro ao mesclar',
                text: error.message || 'Falha ao conectar com o servidor',
                confirmButtonText: 'OK'
            });
            return null;
        }
    };

    window.deletarCliente = async function(id, nome) {
        const result = await Swal.fire({
            title: 'Excluir Cliente?',
            text: `${nome} será removido permanentemente`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                const token = localStorage.getItem('authToken');
                const resp = await fetch(`/v1/marketing/clientes/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + token }
                });

                const data = await resp.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Excluído!',
                        timer: 1500,
                        showConfirmButton: false
                    });

                    if (window.crmClientesApp) {
                        window.crmClientesApp.carregarClientes();
                        window.crmClientesApp.carregarDashboard();
                    }
                } else {
                    Swal.fire('Erro', data.error || 'Falha ao excluir cliente', 'error');
                }
            } catch (e) {
                Swal.fire('Erro', e.message || 'Erro ao conectar com o servidor', 'error');
            }
        }
    };

    window.salvarInteracao = async function() {
        const idClienteField = document.getElementById('clienteId');
        const idCliente = idClienteField ? idClienteField.value : '';

        if (!idCliente) {
            Swal.fire('Atenção', 'Salve o cliente primeiro', 'warning');
            return;
        }

        const dados = {
            tipo: document.getElementById('interacaoTipo')?.value || 'whatsapp',
            descricao: document.getElementById('interacaoDescricao')?.value || '',
            data_interacao: document.getElementById('interacaoData')?.value || new Date().toISOString().split('T')[0],
            hora_interacao: document.getElementById('interacaoHora')?.value || new Date().toTimeString().slice(0, 5),
            usuario: (document.getElementById('user_nome')?.value) || (window.userNome) || 'Sistema'
        };

        if (!dados.descricao) {
            Swal.fire('Atenção', 'Descreva a interação', 'warning');
            return;
        }

        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(`/v1/marketing/clientes/${idCliente}/interacoes`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            });

            const result = await resp.json();

            if (result.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Registrado!',
                    timer: 1500,
                    showConfirmButton: false
                });

                const descField = document.getElementById('interacaoDescricao');
                if (descField) descField.value = '';

                if (window.crmClientesApp) {
                    await window.crmClientesApp.carregarInteracoes(idCliente);
                }
            } else {
                Swal.fire('Erro', result.error || 'Falha ao registrar interação', 'error');
            }
        } catch (e) {
            Swal.fire('Erro', e.message || 'Falha ao registrar', 'error');
        }
    };

    window.criarLembrete = async function() {
        const idClienteField = document.getElementById('clienteId');
        const idCliente = idClienteField ? idClienteField.value : '';

        if (!idCliente) {
            Swal.fire('Atenção', 'Salve o cliente primeiro', 'warning');
            return;
        }

        const dados = {
            id_cliente: idCliente,
            descricao: document.getElementById('lembreteDescricao')?.value || '',
            data_lembrete: document.getElementById('lembreteData')?.value || new Date().toISOString().split('T')[0],
            hora_lembrete: document.getElementById('lembreteHora')?.value || '09:00'
        };

        if (!dados.descricao) {
            Swal.fire('Atenção', 'Descreva o lembrete', 'warning');
            return;
        }

        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch('/v1/marketing/lembretes', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(dados)
            });

            const result = await resp.json();

            if (result.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Lembrete criado!',
                    timer: 1500,
                    showConfirmButton: false
                });

                const descField = document.getElementById('lembreteDescricao');
                if (descField) descField.value = '';

                if (window.crmClientesApp) {
                    await window.crmClientesApp.carregarLembretes(idCliente);
                }
            } else {
                Swal.fire('Erro', result.error || 'Falha ao criar lembrete', 'error');
            }
        } catch (e) {
            Swal.fire('Erro', e.message || 'Falha ao criar', 'error');
        }
    };

    window.concluirLembrete = async function(id) {
        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(`/v1/marketing/lembretes/${id}`, {
                method: 'PUT',
                headers: { 'Authorization': 'Bearer ' + token }
            });

            const result = await resp.json();

            if (result.success) {
                const idClienteField = document.getElementById('clienteId');
                const idCliente = idClienteField ? idClienteField.value : '';

                if (idCliente && window.crmClientesApp) {
                    await window.crmClientesApp.carregarLembretes(idCliente);
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Lembrete concluído!',
                    timer: 1500,
                    showConfirmButton: false
                });
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Erro', 'Falha ao concluir lembrete', 'error');
        }
    };

    window.uploadAnexo = async function() {
        const idClienteField = document.getElementById('clienteId');
        const idCliente = idClienteField ? idClienteField.value : '';

        if (!idCliente) {
            Swal.fire('Atenção', 'Salve o cliente primeiro', 'warning');
            return;
        }

        const fileInput = document.getElementById('anexoFile');
        const file = fileInput ? fileInput.files[0] : null;

        if (!file) {
            Swal.fire('Atenção', 'Selecione um arquivo', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('anexo', file);

        Swal.fire({
            title: 'Enviando...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(`/v1/marketing/clientes/${idCliente}/anexos`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token },
                body: formData
            });

            const result = await resp.json();
            Swal.close();

            if (result.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Enviado!',
                    timer: 1500,
                    showConfirmButton: false
                });

                if (fileInput) fileInput.value = '';

                if (window.crmClientesApp) {
                    await window.crmClientesApp.carregarAnexos(idCliente);
                }
            } else {
                Swal.fire('Erro', result.error || 'Falha ao enviar anexo', 'error');
            }
        } catch (e) {
            Swal.close();
            Swal.fire('Erro', e.message || 'Falha ao enviar', 'error');
        }
    };

    window.deletarAnexo = async function(id) {
        const result = await Swal.fire({
            title: 'Excluir anexo?',
            text: 'Este arquivo será removido permanentemente',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                const token = localStorage.getItem('authToken');
                const resp = await fetch(`/v1/marketing/anexos/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + token }
                });

                const data = await resp.json();

                if (data.success) {
                    const idClienteField = document.getElementById('clienteId');
                    const idCliente = idClienteField ? idClienteField.value : '';

                    if (idCliente && window.crmClientesApp) {
                        await window.crmClientesApp.carregarAnexos(idCliente);
                    }

                    Swal.fire({
                        toast: true,
                        position: 'top-end',
                        icon: 'success',
                        title: 'Excluído!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            } catch (e) {
                console.error(e);
                Swal.fire('Erro', 'Falha ao excluir anexo', 'error');
            }
        }
    };

    window.salvarCompromisso = async function() {
        const idClienteField = document.getElementById('clienteId');
        const idCliente = idClienteField ? idClienteField.value : '';

        if (!idCliente) {
            Swal.fire('Atenção', 'Salve o cliente primeiro', 'warning');
            return;
        }

        const dataHora = document.getElementById('compromissoDataHora')?.value || '';
        const dataHoraFim = document.getElementById('compromissoDataHoraFim')?.value || '';
        const tipo = document.getElementById('compromissoTipo')?.value || 'reuniao';
        const titulo = document.getElementById('compromissoTitulo')?.value || '';
        const descricao = document.getElementById('compromissoDescricao')?.value || '';

        if (!dataHora) {
            Swal.fire('Atenção', 'Informe a data/hora', 'warning');
            return;
        }
        if (dataHoraFim && new Date(dataHoraFim) <= new Date(dataHora)) {
            Swal.fire('Atenção', 'O término deve ser após o início', 'warning');
            return;
        }

        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch('/v1/marketing/compromissos', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    id_cliente: parseInt(idCliente),
                    data_hora: dataHora,
                    data_hora_fim: dataHoraFim || null,
                    tipo: tipo,
                    titulo: titulo,
                    descricao: descricao,
                    status: 'agendado'
                })
            });

            const result = await resp.json();

            if (result.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Compromisso agendado!',
                    timer: 1500,
                    showConfirmButton: false
                });

                const dataHoraField = document.getElementById('compromissoDataHora');
                if (dataHoraField) dataHoraField.value = '';

                const tituloField = document.getElementById('compromissoTitulo');
                if (tituloField) tituloField.value = '';

                const descricaoField = document.getElementById('compromissoDescricao');
                if (descricaoField) descricaoField.value = '';

                if (window.crmClientesApp) {
                    await window.crmClientesApp.carregarCompromissosCliente(idCliente);
                    if (window.crmClientesApp.calendario) {
                        window.crmClientesApp.calendario.refetchEvents();
                    }
                }
            } else {
                Swal.fire('Erro', result.error || 'Falha ao agendar', 'error');
            }
        } catch (e) {
            Swal.fire('Erro', e.message || 'Falha ao conectar', 'error');
        }
    };

    window.concluirCompromisso = async function(id) {
        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(`/v1/marketing/compromissos/${id}/concluir`, {
                method: 'PUT',
                headers: { 'Authorization': 'Bearer ' + token }
            });

            const result = await resp.json();

            if (result.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Concluído!',
                    timer: 1500,
                    showConfirmButton: false
                });

                const idClienteField = document.getElementById('clienteId');
                const idCliente = idClienteField ? idClienteField.value : '';

                if (idCliente && window.crmClientesApp) {
                    await window.crmClientesApp.carregarCompromissosCliente(idCliente);
                    if (window.crmClientesApp.calendario) {
                        window.crmClientesApp.calendario.refetchEvents();
                    }
                }
            }
        } catch (e) {
            console.error(e);
            Swal.fire('Erro', 'Falha ao concluir compromisso', 'error');
        }
    };

    window.excluirCompromisso = async function(id) {
        const result = await Swal.fire({
            title: 'Excluir?',
            text: 'Este compromisso será removido',
            icon: 'warning',
            showCancelButton: true
        });

        if (result.isConfirmed) {
            try {
                const token = localStorage.getItem('authToken');
                const resp = await fetch(`/v1/marketing/compromissos/${id}`, {
                    method: 'DELETE',
                    headers: { 'Authorization': 'Bearer ' + token }
                });

                const data = await resp.json();

                if (data.success) {
                    const idClienteField = document.getElementById('clienteId');
                    const idCliente = idClienteField ? idClienteField.value : '';

                    if (idCliente && window.crmClientesApp) {
                        await window.crmClientesApp.carregarCompromissosCliente(idCliente);
                        if (window.crmClientesApp.calendario) {
                            window.crmClientesApp.calendario.refetchEvents();
                        }
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Excluído!',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            } catch (e) {
                Swal.fire('Erro', e.message || 'Falha ao excluir', 'error');
            }
        }
    };

    function cancelarCompromissoAtual() {
        document.getElementById('compromissoDataHora').value = '';
        document.getElementById('compromissoTitulo').value = '';
        document.getElementById('compromissoDescricao').value = '';
    }

    function abrirModalAgendamento() {
        carregarClientesParaSelect();
        document.getElementById('agendamentoDataHora').value = '';
        document.getElementById('agendamentoDataHoraFim').value = '';
        document.getElementById('agendamentoDuracao').value = '30';
        document.getElementById('agendamentoTitulo').value = '';
        document.getElementById('agendamentoDescricao').value = '';
        document.getElementById('modalAgendamento').classList.remove('hidden');
    }

    function fecharModalAgendamento() {
        document.getElementById('modalAgendamento').classList.add('hidden');
    }

    async function carregarClientesParaSelect() {
     try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch('/v1/marketing/clientes/consulta-otimizado?limite=200', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        const select = document.getElementById('agendamentoCliente');
        const clientes = data.clientes || [];
        select.innerHTML = '<option value="">Selecione um cliente...</option>' +
        clientes.map(c => `<option value="${c.id_crm || c.id_erp}">${c.nome || c.empresa || 'Cliente'}</option>`).join('');
    } catch (e) {
        console.error(e);
    }
}

async function salvarAgendamentoGlobal() {
    const clienteId = document.getElementById('agendamentoCliente').value;
    const dataHora = document.getElementById('agendamentoDataHora').value;
    const dataHoraFim = document.getElementById('agendamentoDataHoraFim').value;
    const duracao = parseInt(document.getElementById('agendamentoDuracao')?.value || 30);
    const tipo = document.getElementById('agendamentoTipo').value;
    const titulo = document.getElementById('agendamentoTitulo').value;
    const descricao = document.getElementById('agendamentoDescricao').value;

    if (!clienteId || !dataHora) {
        Swal.fire('Atenção', 'Selecione um cliente e data/hora de início', 'warning');
        return;
    }

    // Se não informou término, calcular com base na duração
    let dataHoraFimFinal = dataHoraFim;
    if (!dataHoraFimFinal && duracao > 0) {
        const dataInicio = new Date(dataHora);
        dataInicio.setMinutes(dataInicio.getMinutes() + duracao);
        dataHoraFimFinal = dataInicio.toISOString().slice(0, 16);
    }

    // Validar término
    if (dataHoraFimFinal && new Date(dataHoraFimFinal) <= new Date(dataHora)) {
        Swal.fire('Atenção', 'O término deve ser após o início', 'warning');
        return;
    }

    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch('/v1/marketing/compromissos', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id_cliente: parseInt(clienteId),
                data_hora: dataHora,
                data_hora_fim: dataHoraFimFinal || null,
                tipo: tipo,
                titulo: titulo || tipo,
                descricao: descricao,
                status: 'agendado'
            })
        });
        const result = await resp.json();
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Agendado!',
                timer: 1500,
                showConfirmButton: false
            });
            fecharModalAgendamento();
            if (window.crmClientesApp?.calendario) window.crmClientesApp.calendario.refetchEvents();
            if (window.crmClientesApp) window.crmClientesApp.carregarProximosCompromissos();
        } else {
            Swal.fire('Erro', result.error, 'error');
        }
    } catch (e) {
        Swal.fire('Erro', e.message, 'error');
    }
}

function exportarCSV() {
    window.open('/v1/marketing/clientes/exportar/csv', '_blank');
}

// ============================================================================
// PEDIDOS ERP - FUNÇÕES
// ============================================================================
async function carregarPedidosERP(idCliente) {
    if (!idCliente) {
        const container = document.getElementById('listaPedidosERP');
        if (container) {
            container.innerHTML = '<p class="text-center text-slate-400 py-4">ID do cliente não informado</p>';
        }
        return;
    }

    try {
        const token = localStorage.getItem('authToken');
        if (!token) {
            window.location.href = '/portal/login.php';
            return;
        }

        const resp = await fetch(`/v1/marketing/clientes/${idCliente}/pedidos-erp`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (resp.status === 401) {
            window.location.href = '/portal/login.php';
            return;
        }

        const data = await resp.json();
        renderizarPedidosERP(data);

        const totalBadge = document.getElementById('totalPedidosERP');
        if (totalBadge) {
            totalBadge.textContent = `${data.total_pedidos || 0} pedidos`;
        }

        if (data.cliente_erp_encontrado && data.cliforemp_id) {
            const cnpjField = document.getElementById('clienteCnpj');
            if (cnpjField && !cnpjField.value) {
                await buscarCnpjDoERP(data.cliforemp_id);
            }
        }

        return data;

    } catch (e) {
        console.error('Erro ao carregar pedidos ERP:', e);
        const container = document.getElementById('listaPedidosERP');
        if (container) {
            container.innerHTML = `
                <div class="bg-rose-50 p-4 rounded-xl text-center">
                    <i class="fa-solid fa-exclamation-triangle text-rose-500 text-xl mb-2 block"></i>
                    <p class="text-sm text-rose-600">Erro ao carregar pedidos</p>
                    <p class="text-xs text-rose-400">${e.message}</p>
                </div>
            `;
        }
    }
}

function renderizarPedidosERP(data) {
    const container = document.getElementById('listaPedidosERP');
    if (!container) return;

    if (!data.success) {
        if (data.message) {
            container.innerHTML = `
                <div class="bg-amber-50 p-4 rounded-xl text-center">
                    <i class="fa-regular fa-circle-info text-amber-500 text-xl mb-2 block"></i>
                    <p class="text-sm text-amber-600">${data.message}</p>
                </div>
            `;
        } else {
            container.innerHTML = `
                <div class="bg-slate-50 p-4 rounded-xl text-center">
                    <i class="fa-regular fa-building text-slate-300 text-xl mb-2 block"></i>
                    <p class="text-sm text-slate-400">Cliente não encontrado no ERP</p>
                    <p class="text-xs text-slate-400 mt-1">Cadastre o CNPJ/CPF ou importe o cliente</p>
                </div>
            `;
        }
        return;
    }

    if (!data.pedidos || data.pedidos.length === 0) {
        container.innerHTML = `
            <div class="bg-slate-50 p-4 rounded-xl text-center">
                <i class="fa-regular fa-box-empty text-slate-300 text-3xl mb-2 block"></i>
                <p class="text-sm text-slate-400">Nenhum pedido encontrado para este cliente</p>
            </div>
        `;
        return;
    }

    container.innerHTML = data.pedidos.map(pedido => `
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm hover:shadow-md transition-all">
            <div class="bg-gradient-to-r from-slate-50 to-slate-100 px-4 py-3 flex justify-between items-center cursor-pointer" onclick="togglePedidoItens(${pedido.idpedido})">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-bold text-indigo-600">#${pedido.idpedido}</span>
                    <span class="px-2 py-1 rounded-full text-xs font-bold ${getStatusPedidoClass(pedido.status_pedido)}">${pedido.status_pedido || 'N/A'}</span>
        ${pedido.situacao_desc ? `<span class="px-2 py-1 rounded-full text-xs font-bold ${getSituacaoPedidoClass(pedido.situacao_desc)}">${pedido.situacao_desc}</span>` : ''}
                    <span class="text-xs text-slate-400">${pedido.dtemissao_formatada || '—'}</span>
        ${pedido.filial_nome ? `<span class="text-xs text-slate-400">| ${pedido.filial_nome}</span>` : ''}
                </div>
                <div class="flex items-center gap-4">
                    <span class="font-bold text-emerald-600">${pedido.valor_total_formatado || 'R$ 0,00'}</span>
                    <span class="text-xs text-slate-400">${pedido.total_itens || 0} itens</span>
                    <i class="fa-solid fa-chevron-down text-slate-400 text-xs transition-transform" id="icon_pedido_${pedido.idpedido}"></i>
                </div>
            </div>
            <div id="itens_pedido_${pedido.idpedido}" class="hidden px-4 py-3 bg-slate-50/50 border-t border-slate-100">
        ${pedido.itens && pedido.itens.length > 0 ? `
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-xs text-slate-400 border-b">
                                    <th class="text-left py-1">Código</th>
                                    <th class="text-left py-1">Descrição</th>
                                    <th class="text-center py-1">Qtd</th>
                                    <th class="text-right py-1">Preço</th>
                                    <th class="text-right py-1">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
            ${pedido.itens.map(item => `
                                    <tr class="border-b border-slate-200/50 last:border-0">
                                        <td class="py-1 text-xs font-mono">${item.codigo || '—'}</td>
                                        <td class="py-1 text-xs">${item.descricao || '—'}</td>
                                        <td class="py-1 text-center text-xs">${item.quantidade || 0}</td>
                                        <td class="py-1 text-right text-xs">R$ ${parseFloat(item.preco || 0).toFixed(2)}</td>
                                        <td class="py-1 text-right text-xs font-bold">R$ ${parseFloat(item.subtotal || 0).toFixed(2)}</td>
                                    </tr>
                `).join('')}
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-right font-bold text-sm pt-2">Total:</td>
                                    <td class="text-right font-bold text-emerald-600 text-sm pt-2">${pedido.valor_total_formatado || 'R$ 0,00'}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
            ` : '<p class="text-sm text-slate-400">Nenhum item encontrado</p>'}
            </div>
        </div>
        `).join('');
}

function togglePedidoItens(id) {
    const container = document.getElementById(`itens_pedido_${id}`);
    const icon = document.getElementById(`icon_pedido_${id}`);
    if (container) {
        container.classList.toggle('hidden');
        if (icon) {
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        }
    }
}

function getStatusPedidoClass(status) {
    const classes = {
        'Aberto': 'bg-blue-100 text-blue-700',
        'Faturado': 'bg-emerald-100 text-emerald-700',
        'Cancelado': 'bg-rose-100 text-rose-700',
        'Faturado Parcial': 'bg-amber-100 text-amber-700',
        'Saldo cancelado': 'bg-red-100 text-red-700',
        'Desconhecido': 'bg-slate-100 text-slate-700'
    };
    return classes[status] || 'bg-slate-100 text-slate-700';
}

function getSituacaoPedidoClass(situacao) {
    const classes = {
        'Aprovado comercial': 'bg-emerald-100 text-emerald-700',
        'Aguardando aprovação': 'bg-amber-100 text-amber-700',
        'Enviado embarque': 'bg-blue-100 text-blue-700',
        'Rejeitado': 'bg-rose-100 text-rose-700',
        'Enviado produção': 'bg-purple-100 text-purple-700',
        'Reaberto': 'bg-orange-100 text-orange-700',
        'Alterado por nota fiscal': 'bg-yellow-100 text-yellow-700',
        'Saldo cancelado': 'bg-red-100 text-red-700',
        'Reaberto parcial': 'bg-orange-100 text-orange-700',
        'Pré-venda (cupom fiscal)': 'bg-cyan-100 text-cyan-700',
        'Renovado validade': 'bg-indigo-100 text-indigo-700',
        'Conferindo expedição': 'bg-teal-100 text-teal-700',
        'Agrupado': 'bg-violet-100 text-violet-700',
        'Devolução entrega futura': 'bg-rose-100 text-rose-700',
        'DAV (orçamento)': 'bg-slate-100 text-slate-700',
        'Alterado vendedor/representante': 'bg-slate-100 text-slate-700',
        'DAV faturada (CF)': 'bg-emerald-100 text-emerald-700',
        'Transformado': 'bg-gray-100 text-gray-700'
    };
    return classes[situacao] || 'bg-slate-100 text-slate-700';
}

async function buscarCnpjDoERP(idErp) {
    try {
        const token = localStorage.getItem('authToken');
        if (!token) return;

        const resp = await fetch(`/v1/marketing/clientes/erp/${idErp}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        if (data.success && data.data) {
            const cnpj = data.data.cnpj || data.data.cpf || '';
            const cnpjField = document.getElementById('clienteCnpj');
            if (cnpjField && !cnpjField.value) {
                cnpjField.value = formatarCnpjCpf(cnpj);
            }
        }
    } catch (e) {
        console.warn('Erro ao buscar CNPJ:', e);
    }
}

// Adicione no clientes.php, na seção de funções globais

/**
 * Formata CNPJ ou CPF
 */
function formatarCnpjCpf(valor) {
    if (!valor) return '';
    
    // Remove tudo que não é número
    const numeros = valor.replace(/\D/g, '');
    
    if (numeros.length === 14) {
        // CNPJ: 00.000.000/0000-00
        return numeros.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    } else if (numeros.length === 11) {
        // CPF: 000.000.000-00
        return numeros.replace(/^(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    }
    
    return valor;
}

/**
 * Valida se é CNPJ
 */
function isCNPJ(valor) {
    const numeros = valor.replace(/\D/g, '');
    return numeros.length === 14;
}

/**
 * Valida se é CPF
 */
function isCPF(valor) {
    const numeros = valor.replace(/\D/g, '');
    return numeros.length === 11;
}

/**
 * Valida se é telefone
 */
function isTelefone(valor) {
    const numeros = valor.replace(/\D/g, '');
    return numeros.length === 10 || numeros.length === 11;
}
async function buscarClienteERP() {
    const cnpj = document.getElementById('clienteCnpj')?.value || '';
    if (!cnpj) {
        Swal.fire('Atenção', 'Digite o CNPJ/CPF para buscar', 'warning');
        return;
    }
    await buscarPedidosPorCnpj(cnpj);
}

async function buscarPedidosPorCnpj(cnpj = null) {
    const cnpjInput = cnpj || document.getElementById('buscaCnpj')?.value || '';
    if (!cnpjInput) {
        Swal.fire('Atenção', 'Digite o CNPJ/CPF para buscar', 'warning');
        return;
    }

    const cnpjLimpo = cnpjInput.replace(/\D/g, '');
    if (cnpjLimpo.length < 11) {
        Swal.fire('Atenção', 'CNPJ/CPF inválido. Digite pelo menos 11 dígitos.', 'warning');
        return;
    }

    try {
        const token = localStorage.getItem('authToken');
        if (!token) {
            window.location.href = '/portal/login.php';
            return;
        }

        const resp = await fetch('/v1/marketing/clientes/buscar-por-cnpj', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ cnpj_cpf: cnpjLimpo })
        });

        if (resp.status === 401) {
            window.location.href = '/portal/login.php';
            return;
        }

        const data = await resp.json();

        const container = document.getElementById('resultadoBuscaCnpj');
        if (container) {
            container.classList.remove('hidden');
        }

        if (data.success) {
            const cliente = data.cliente;
            if (cliente) {
                const nomeField = document.getElementById('clienteNome');
                const empresaField = document.getElementById('clienteEmpresa');
                const telefoneField = document.getElementById('clienteTelefone');
                const emailField = document.getElementById('clienteEmail');
                const cidadeField = document.getElementById('clienteCidade');
                const ufField = document.getElementById('clienteUF');
                const cnpjField = document.getElementById('clienteCnpj');

                if (nomeField) nomeField.value = cliente.fantasia || cliente.razao || '';
                if (empresaField) empresaField.value = cliente.razao || cliente.fantasia || '';
                if (telefoneField) telefoneField.value = cliente.fone || '';
                if (emailField) emailField.value = cliente.email || '';
                if (cidadeField) cidadeField.value = cliente.cidade || '';
                if (ufField) ufField.value = cliente.uf || '';
                if (cnpjField) cnpjField.value = formatarCnpjCpf(cliente.cnpj || cliente.cpf || '');
            }

            renderizarPedidosERP(data);

            if (container) {
                container.innerHTML = `
                    <div class="bg-emerald-50 p-3 rounded-xl flex items-center gap-3">
                        <i class="fa-regular fa-circle-check text-emerald-500 text-xl"></i>
                        <div>
                            <p class="text-sm font-bold text-emerald-700">Cliente encontrado no ERP!</p>
                            <p class="text-xs text-emerald-600">${data.total_pedidos || 0} pedidos encontrados</p>
                        </div>
                    </div>
                `;
            }

            const totalBadge = document.getElementById('totalPedidosERP');
            if (totalBadge) {
                totalBadge.textContent = `${data.total_pedidos || 0} pedidos`;
            }

            Swal.fire({
                icon: 'success',
                title: 'Cliente encontrado!',
                text: `${data.total_pedidos || 0} pedidos encontrados no ERP`,
                timer: 2000,
                showConfirmButton: false
            });

        } else {
            if (container) {
                container.innerHTML = `
                    <div class="bg-amber-50 p-3 rounded-xl flex items-center gap-3">
                        <i class="fa-regular fa-circle-question text-amber-500 text-xl"></i>
                        <div>
                            <p class="text-sm font-bold text-amber-700">Cliente não encontrado</p>
                            <p class="text-xs text-amber-600">${data.error || 'Nenhum cliente encontrado com este CNPJ/CPF'}</p>
                        </div>
                    </div>
                `;
            }
        }

    } catch (e) {
        console.error('Erro ao buscar pedidos por CNPJ:', e);
        Swal.fire('Erro', 'Falha ao buscar pedidos: ' + e.message, 'error');
    }
}

// ============================================================================
// BUSCA DE DADOS - CNPJ (ERP + RECEITA)
// ============================================================================
async function buscarDadosCompletos() {
    const cnpjInput = document.getElementById('clienteCnpj');
    let cnpj = cnpjInput ? cnpjInput.value : '';

    cnpj = cnpj.replace(/\D/g, '');

    if (cnpj.length < 11) {
        Swal.fire('Atenção', 'Digite um CNPJ/CPF válido', 'warning');
        return;
    }

    const btn = event?.target || document.querySelector('[onclick="buscarDadosCompletos()"]');
    const originalText = btn?.innerHTML || 'Buscar ERP';
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Buscando...';
        btn.disabled = true;
    }

    try {
        const erpResult = await buscarClienteNoERPCompleto(cnpj);

        if (erpResult) {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            await verificarClienteExistente(cnpj);
            Swal.fire({
                icon: 'success',
                title: '✅ Cliente encontrado no ERP!',
                text: 'Dados preenchidos automaticamente',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        const receitaResult = await buscarDadosReceitaCompleta(cnpj);

        if (receitaResult) {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            Swal.fire({
                icon: 'success',
                title: '✅ Dados encontrados na Receita!',
                text: 'Preencha os campos restantes manualmente',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }

        Swal.fire({
            icon: 'info',
            title: 'CNPJ não encontrado',
            text: 'Preencha os dados manualmente',
            confirmButtonText: 'OK'
        });

    } catch (e) {
        console.error('Erro ao buscar dados:', e);
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
        Swal.fire('Erro', 'Falha ao buscar dados: ' + e.message, 'error');
    }
}

async function buscarClienteNoERPCompleto(cnpj) {
    cnpj = cnpj.replace(/\D/g, '');

    if (cnpj.length < 11) {
        return false;
    }

    try {
        const token = localStorage.getItem('authToken');
        if (!token) return false;

        const resp = await fetch('/v1/marketing/clientes/buscar-por-cnpj', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ cnpj_cpf: cnpj })
        });

        const data = await resp.json();

        if (data.success && data.cliente) {
            const cliente = data.cliente;

            document.getElementById('clienteNome').value = cliente.fantasia || cliente.razao || '';
            document.getElementById('clienteEmpresa').value = cliente.razao || cliente.fantasia || '';
            document.getElementById('clienteTelefone').value = cliente.fone || '';
            document.getElementById('clienteEmail').value = cliente.email || '';
            document.getElementById('clienteCidade').value = cliente.cidade || '';
            document.getElementById('clienteUF').value = cliente.uf || '';
            document.getElementById('clienteCnpj').value = formatarCnpjCpf(cliente.cnpj || cliente.cpf || '');

            const enderecoField = document.getElementById('clienteEndereco');
            if (enderecoField) {
                enderecoField.value = cliente.endereco || '';
            }

            const numeroField = document.getElementById('clienteNumero');
            if (numeroField) {
                numeroField.value = cliente.numero || '';
            }

            const bairroField = document.getElementById('clienteBairro');
            if (bairroField) {
                bairroField.value = cliente.bairro || '';
            }

            const cepField = document.getElementById('clienteCep');
            if (cepField && cliente.cep) {
                cepField.value = cliente.cep;
            }

            const complementoField = document.getElementById('clienteComplemento');
            if (complementoField && cliente.complemento) {
                complementoField.value = cliente.complemento;
            }

            if (data.total_pedidos > 0) {
                const badge = document.getElementById('totalPedidosERP');
                if (badge) {
                    badge.textContent = `${data.total_pedidos} pedidos`;
                }
                const clienteId = document.getElementById('clienteId')?.value;
                if (clienteId) {
                    await carregarPedidosERP(clienteId);
                }
            }

            return true;
        }

        return false;

    } catch (e) {
        console.error('Erro ao buscar no ERP:', e);
        return false;
    }
}

async function buscarDadosReceitaCompleta(cnpj) {
    cnpj = cnpj.replace(/\D/g, '');

    if (cnpj.length !== 14) {
        return false;
    }

    try {
        const token = localStorage.getItem('authToken');
        if (!token) return false;

        const resp = await fetch('/v1/receita/consultar', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ cnpj: cnpj })
        });

        const data = await resp.json();

        if (data.success && data.dados) {
            const dados = data.dados;

            document.getElementById('clienteNome').value = dados.nome_fantasia || dados.razao_social || '';
            document.getElementById('clienteEmpresa').value = dados.razao_social || dados.nome_fantasia || '';
            document.getElementById('clienteTelefone').value = dados.telefone || '';
            document.getElementById('clienteEmail').value = dados.email || '';
            document.getElementById('clienteCidade').value = dados.municipio || '';
            document.getElementById('clienteUF').value = dados.uf || '';
            document.getElementById('clienteCnpj').value = formatarCnpjCpf(dados.cnpj || cnpj);

            const enderecoField = document.getElementById('clienteEndereco');
            if (enderecoField) {
                enderecoField.value = dados.logradouro || '';
            }

            const numeroField = document.getElementById('clienteNumero');
            if (numeroField) {
                numeroField.value = dados.numero || '';
            }

            const bairroField = document.getElementById('clienteBairro');
            if (bairroField) {
                bairroField.value = dados.bairro || '';
            }

            const cepField = document.getElementById('clienteCep');
            if (cepField && dados.cep) {
                cepField.value = dados.cep;
            }

            const complementoField = document.getElementById('clienteComplemento');
            if (complementoField && dados.complemento) {
                complementoField.value = dados.complemento;
            }

            let infoExtra = '';
            if (dados.situacao_cadastral) {
                infoExtra += `<p class="text-xs"><strong>Situação:</strong> ${dados.situacao_cadastral}</p>`;
            }
            if (dados.porte) {
                infoExtra += `<p class="text-xs"><strong>Porte:</strong> ${dados.porte}</p>`;
            }
            if (dados.capital_social > 0) {
                infoExtra += `<p class="text-xs"><strong>Capital Social:</strong> R$ ${dados.capital_social.toFixed(2)}</p>`;
            }

            if (infoExtra) {
                Swal.fire({
                    icon: 'info',
                    title: '📋 Dados da Receita',
                    html: infoExtra,
                    timer: 3000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }

            return true;
        }

        return false;

    } catch (e) {
        console.error('Erro ao buscar na Receita:', e);
        return false;
    }
}

   // ============================================================================
// BUSCA DE DADOS - CNPJ (ERP + RECEITA)
// ============================================================================
async function buscarDadosCompletos() {
    const cnpjInput = document.getElementById('clienteCnpj');
    let cnpj = cnpjInput ? cnpjInput.value : '';

    cnpj = cnpj.replace(/\D/g, '');

    if (cnpj.length < 11) {
        Swal.fire('Atenção', 'Digite um CNPJ/CPF válido', 'warning');
        return;
    }

    const btn = event?.target || document.querySelector('[onclick="buscarDadosCompletos()"]');
    const originalText = btn?.innerHTML || 'Buscar Dados';
    if (btn) {
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Buscando...';
        btn.disabled = true;
    }

    try {
        // 1º TENTATIVA: Buscar no ERP
        const erpResult = await buscarClienteNoERPCompleto(cnpj);

        if (erpResult) {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            await verificarClienteExistente(cnpj);
            Swal.fire({
                icon: 'success',
                title: '✅ Cliente encontrado no ERP!',
                text: 'Dados preenchidos automaticamente',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        // 2º TENTATIVA: Buscar na Receita (via API)
        const receitaResult = await buscarDadosReceitaCompleta(cnpj);

        if (receitaResult) {
            if (btn) {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
            Swal.fire({
                icon: 'success',
                title: '✅ Dados encontrados na Receita!',
                text: 'Preencha os campos restantes manualmente',
                timer: 1500,
                showConfirmButton: false
            });
            return;
        }

        // Nenhuma fonte encontrou
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }

        Swal.fire({
            icon: 'info',
            title: 'CNPJ não encontrado',
            text: 'Preencha os dados manualmente',
            confirmButtonText: 'OK'
        });

    } catch (e) {
        console.error('Erro ao buscar dados:', e);
        if (btn) {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
        Swal.fire('Erro', 'Falha ao buscar dados: ' + e.message, 'error');
    }
}

function limparCamposEndereco() {
    const enderecoField = document.getElementById('clienteEndereco');
    const cepField = document.getElementById('clienteCep');
    const numeroField = document.getElementById('clienteNumero');
    const complementoField = document.getElementById('clienteComplemento');
    const bairroField = document.getElementById('clienteBairro');

    if (enderecoField) enderecoField.value = '';
    if (cepField) cepField.value = '';
    if (numeroField) numeroField.value = '';
    if (complementoField) complementoField.value = '';
    if (bairroField) bairroField.value = '';

    Swal.fire({
        icon: 'success',
        title: 'Campos de endereço limpos!',
        timer: 800,
        showConfirmButton: false,
        toast: true,
        position: 'top-end'
    });
}

async function verificarClienteExistente(cnpj) {
   try {
    const token = localStorage.getItem('authToken');
    const resp = await fetch(`/v1/marketing/clientes/consulta-otimizado?busca=${cnpj}&limite=5`, {
        headers: { 'Authorization': 'Bearer ' + token }
    });
    const data = await resp.json();

    if (data.success && data.clientes && data.clientes.length > 0) {
        const existente = data.clientes[0];

        const result = await Swal.fire({
            title: 'Cliente já cadastrado!',
            html: `
                    <div class="text-left">
                        <p><strong>Nome:</strong> ${existente.nome}</p>
                        <p><strong>Empresa:</strong> ${existente.empresa || '—'}</p>
                        <p><strong>Status:</strong> ${existente.status_crm || 'Novo'}</p>
                        <p><strong>Última compra:</strong> ${existente.data_ultima_compra ? new Date(existente.data_ultima_compra).toLocaleDateString('pt-BR') : 'Nunca'}</p>
                    </div>
                `,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Abrir cliente',
                cancelButtonText: 'Continuar cadastro'
            });

        if (result.isConfirmed) {
            window.editarCliente(existente.id_crm, 'CRM');
            fecharModalCliente();
        }
    }

} catch (e) {
    console.error('Erro ao verificar cliente existente:', e);
}
}

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================
// Auto-calcular término com base na duração
document.addEventListener('DOMContentLoaded', function() {
    const dataHoraInput = document.getElementById('agendamentoDataHora');
    const duracaoSelect = document.getElementById('agendamentoDuracao');
    const dataHoraFimInput = document.getElementById('agendamentoDataHoraFim');

    function calcularTermino() {
        if (dataHoraInput.value && duracaoSelect.value) {
            const inicio = new Date(dataHoraInput.value);
            const duracaoMin = parseInt(duracaoSelect.value);
            inicio.setMinutes(inicio.getMinutes() + duracaoMin);
            dataHoraFimInput.value = inicio.toISOString().slice(0, 16);
        }
    }

    dataHoraInput.addEventListener('change', calcularTermino);
    duracaoSelect.addEventListener('change', calcularTermino);
});

document.addEventListener('DOMContentLoaded', () => {
    const dicaData = document.getElementById('dicaDataCadastro');
    if (dicaData) {
        const hoje = new Date();
        const ontem = new Date(hoje);
        ontem.setDate(hoje.getDate() - 1);
        const formatar = (date) => date.toLocaleDateString('pt-BR');
        dicaData.textContent = `📅 Hoje: ${formatar(hoje)} | Ontem: ${formatar(ontem)}`;
    }
    // Abas do modal
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active', 'border-[#375a4b]', 'text-[#375a4b]'));
            btn.classList.add('active', 'border-[#375a4b]', 'text-[#375a4b]');
            document.querySelectorAll('#modalCliente [id^="tab"]').forEach(t => t.classList.add('hidden'));
            const tabName = btn.dataset.tab.charAt(0).toUpperCase() + btn.dataset.tab.slice(1);
            const targetTab = document.getElementById(`tab${tabName}`);
            if (targetTab) targetTab.classList.remove('hidden');
        });
    });

    window.crmClientesApp = crmProfissional();
    window.crmClientesApp.init();
});
</script>

<!-- MODAL CLIENTE -->
<div id="modalCliente" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="fecharModalCliente()"></div>
        <div class="relative bg-white rounded-3xl max-w-3xl w-full shadow-2xl max-h-[90vh] overflow-y-auto modal-modern">
            <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 flex items-center justify-between rounded-t-3xl sticky top-0 z-10">
                <h3 class="text-xl font-bold text-white" id="modalClienteTitulo"><i class="fa-solid fa-user-plus mr-2"></i>Novo Cliente</h3>
                <button onclick="fecharModalCliente()" class="text-white/70 hover:text-white transition-colors"><i class="fa-solid fa-times text-xl"></i></button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="clienteId">
                
                <div class="flex border-b border-slate-200 overflow-x-auto">
                    <button class="tab-btn active px-4 py-2 text-sm font-bold border-b-2 border-emerald-500 text-emerald-600" data-tab="dados">📋 Dados</button>
                    <button class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-slate-400" data-tab="interacoes">💬 Interações</button>
                    <button class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-slate-400" data-tab="compromissos">📅 Compromissos</button>
                    <button class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-slate-400" data-tab="lembretes">⏰ Lembretes</button>
                    <button class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-slate-400" data-tab="anexos">📎 Anexos</button>
                    <button class="tab-btn px-4 py-2 text-sm font-bold border-b-2 border-transparent text-slate-400" data-tab="pedidos">📦 Pedidos ERP</button>
                </div>
                
                <div id="tabDados" class="space-y-4 pt-4">
                    <!-- Nome e Empresa -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Nome *</label>
                            <input type="text" id="clienteNome" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Empresa</label>
                            <input type="text" id="clienteEmpresa" class="w-full p-3 border rounded-xl">
                        </div>
                    </div>

                    <!-- CNPJ/CPF com busca integrada -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-1">CNPJ/CPF</label>
                            <input type="text" id="clienteCnpj" 
                            class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-indigo-400" 
                            placeholder="00.000.000/0000-00"
                            oninput="this.value = this.value.replace(/[^0-9.\-\/]/g, '')">
                        </div>
                        <div class="md:col-span-2 flex items-end gap-2">
                            <button onclick="buscarDadosCompletos()" 
                            class="flex-1 px-4 py-3 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-xl font-bold hover:from-indigo-600 hover:to-indigo-700 transition-all flex items-center justify-center gap-2 shadow-md">
                            <i class="fa-solid fa-magnifying-glass"></i> Buscar Dados
                        </button>
                        <button onclick="limparCamposEndereco()" 
                        class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all" 
                        title="Limpar endereço">
                        <i class="fa-solid fa-eraser"></i>
                    </button>
                </div>
            </div>
<!-- Dica rápida -->
<div class="text-xs text-slate-400 mt-1">
    <i class="fa-regular fa-circle-info"></i> 
    Busca automática no ERP e na Receita Federal
</div>

<!-- Telefone e Email -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Telefone</label>
        <input type="tel" id="clienteTelefone" class="w-full p-3 border rounded-xl">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Email</label>
        <input type="email" id="clienteEmail" class="w-full p-3 border rounded-xl">
    </div>
</div>

<!-- Cidade e UF -->
<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Cidade</label>
        <input type="text" id="clienteCidade" class="w-full p-3 border rounded-xl">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">UF</label>
        <input type="text" id="clienteUF" class="w-full p-3 border rounded-xl" maxlength="2">
    </div>
</div>

<!-- Campos de Endereço -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="md:col-span-2">
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Endereço</label>
        <input type="text" id="clienteEndereco" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400" placeholder="Rua, Av...">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Número</label>
        <input type="text" id="clienteNumero" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400" placeholder="Nº">
    </div>
</div>
<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Bairro</label>
        <input type="text" id="clienteBairro" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400" placeholder="Bairro">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">CEP</label>
        <input type="text" id="clienteCep" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400" placeholder="00000-000">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Complemento</label>
        <input type="text" id="clienteComplemento" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400" placeholder="Complemento">
    </div>
</div>

<!-- Data de Cadastro, Status, Termômetro e Origem -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">

    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1 flex items-center gap-1">
            Data Cadastro
            <span class="relative inline-block" 
            onmouseenter="this.querySelector('.tooltip-content').style.display='block'"
            onmouseleave="this.querySelector('.tooltip-content').style.display='none'">
            <i class="fa-regular fa-circle-question text-slate-400 text-xs cursor-help hover:text-indigo-500 transition-colors"></i>

            <div class="tooltip-content absolute bottom-full left-1/2 -translate-x-1/2 mb-2 bg-slate-800 text-white text-xs rounded-lg px-4 py-3 w-64 z-[999] shadow-xl border border-slate-700" 
            style="display: none;">
            <div class="flex items-center gap-2 mb-1">
                <i class="fa-regular fa-calendar text-emerald-400 text-sm"></i>
                <span class="font-bold text-emerald-400">Dica:</span>
            </div>
            <p class="text-slate-200 text-sm" id="dicaDataCadastro"></p>
            <p class="text-slate-400 text-[10px] mt-1">Clique no campo para alterar</p>
            <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-800"></div>
        </div>
    </span>
</label>
<input type="date" id="clienteDataCadastro" 
class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400">
</div>
<!-- Status ocupa 1 coluna -->
<div>
    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Status</label>
    <select id="clienteStatus" class="w-full p-3 border rounded-xl">
        <option value="Novo">🆕 Novo</option>
        <option value="Qualificado">⭐ Qualificado</option>
        <option value="Proposta">📄 Proposta</option>
        <option value="Fechado">✅ Fechado</option>
        <option value="Perdido">❌ Perdido</option>
    </select>
</div>
</div>

<!-- Termômetro e Origem (2 colunas) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Termômetro</label>
        <select id="clienteTermometro" class="w-full p-3 border rounded-xl">
            <option value="Frio">🥶 Frio</option>
            <option value="Morno">🌤️ Morno</option>
            <option value="Quente">🔥 Quente</option>
        </select>
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Origem</label>
        <select id="clienteOrigem" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-emerald-400 transition-all">
            <option value="Site">🌐 Site</option>
            <option value="WhatsApp">💬 WhatsApp</option>
            <option value="Instagram">📷 Instagram</option>
            <option value="Bio do Instagram">📷 Bio do Instagram</option>
            <option value="Facebook">📘 Facebook</option>
            <option value="LandPage">📄 LandPage</option>
            <option value="Indicação">👥 Indicação</option>
            <option value="Outros">📌 Outros</option>
        </select>
    </div>
</div>

<!-- Valor Negócio e Meta Vinculada -->
<div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Valor Negócio (R$)</label>
        <input type="number" id="clienteValor" class="w-full p-3 border rounded-xl" step="0.01">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Meta Vinculada</label>
        <select id="clienteMeta" class="w-full p-3 border rounded-xl">
            <option value="0">Sem meta</option>
        </select>
    </div>
</div>

<!-- Observações -->
<div>
    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Observações</label>
    <textarea id="clienteObs" rows="3" class="w-full p-3 border rounded-xl"></textarea>
</div>

<!-- Tags -->
<div>
    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tags</label>
    <div id="tagsContainer" class="flex flex-wrap gap-2 mb-2"></div>
    <div class="flex gap-2">
        <input type="text" id="novaTag" class="flex-1 p-2 border rounded-xl text-sm" placeholder="Digite uma tag">
        <button onclick="adicionarTag()" class="px-3 py-2 bg-slate-200 rounded-xl hover:bg-slate-300">+ Adicionar</button>
    </div>
</div>
</div>
<div id="tabInteracoes" class="hidden space-y-4 pt-4">
    <div class="bg-slate-50 p-4 rounded-xl">
        <h4 class="font-bold mb-3">Nova Interação</h4>
        <div class="grid grid-cols-3 gap-3 mb-3">
            <select id="interacaoTipo" class="p-2 border rounded-xl text-sm"><option value="whatsapp">💬 WhatsApp</option><option value="ligacao">📞 Ligação</option><option value="email">📧 Email</option><option value="visita">🏢 Visita</option></select>
            <input type="date" id="interacaoData" class="p-2 border rounded-xl text-sm" value="<?= date('Y-m-d') ?>">
            <input type="time" id="interacaoHora" class="p-2 border rounded-xl text-sm" value="<?= date('H:i') ?>">
        </div>
        <textarea id="interacaoDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm" placeholder="Descreva a interação..."></textarea>
        <button onclick="window.salvarInteracao()" class="mt-3 px-4 py-2 bg-emerald-500 text-white rounded-xl text-sm font-bold hover:bg-emerald-600">Registrar</button>
    </div>
    <div id="historicoInteracoes" class="space-y-3 max-h-64 overflow-y-auto"></div>
</div>

<div id="tabCompromissos" class="hidden space-y-4 pt-4">
    <div class="bg-blue-50 p-4 rounded-xl">
        <h4 class="font-bold mb-3">Novo Compromisso</h4>
        <div class="grid grid-cols-2 gap-3 mb-3">
            <input type="datetime-local" id="compromissoDataHora" class="p-2 border rounded-xl text-sm">
            <input type="datetime-local" id="compromissoDataHoraFim" class="p-2 border rounded-xl text-sm">
            <select id="compromissoTipo" class="p-2 border rounded-xl text-sm"><option value="reuniao">📅 Reunião</option><option value="ligacao">📞 Ligação</option><option value="visita">🏢 Visita</option><option value="whatsapp">💬 WhatsApp</option><option value="email">📧 Email</option></select>
        </div>
        <input type="text" id="compromissoTitulo" class="w-full p-2 border rounded-xl text-sm mb-2" placeholder="Título do compromisso">
        <textarea id="compromissoDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm" placeholder="Descrição..."></textarea>
        <div class="flex gap-2 mt-2"><button onclick="window.salvarCompromisso()" class="px-4 py-2 bg-blue-500 text-white rounded-xl text-sm font-bold hover:bg-blue-600">Salvar</button><button onclick="cancelarCompromissoAtual()" class="px-4 py-2 bg-gray-300 rounded-xl text-sm">Limpar</button></div>
    </div>
    <div id="listaCompromissos" class="space-y-3 max-h-64 overflow-y-auto"></div>
</div>

<div id="tabLembretes" class="hidden space-y-4 pt-4">
    <div class="bg-amber-50 p-4 rounded-xl">
        <h4 class="font-bold mb-3">Novo Lembrete</h4>
        <div class="grid grid-cols-2 gap-3 mb-3"><input type="date" id="lembreteData" class="p-2 border rounded-xl text-sm" value="<?= date('Y-m-d', strtotime('+1 day')) ?>"><input type="time" id="lembreteHora" class="p-2 border rounded-xl text-sm" value="09:00"></div>
        <textarea id="lembreteDescricao" rows="2" class="w-full p-2 border rounded-xl text-sm" placeholder="O que precisa ser feito?"></textarea>
        <button onclick="window.criarLembrete()" class="mt-3 px-4 py-2 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600">Criar Lembrete</button>
    </div>
    <div id="historicoLembretes" class="space-y-3 max-h-64 overflow-y-auto"></div>
</div>

<div id="tabAnexos" class="hidden space-y-4 pt-4">
    <div class="bg-purple-50 p-4 rounded-xl">
        <input type="file" id="anexoFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.png" class="w-full p-2 border rounded-xl text-sm bg-white">
        <button onclick="window.uploadAnexo()" class="mt-3 px-4 py-2 bg-purple-500 text-white rounded-xl text-sm font-bold hover:bg-purple-600">Enviar Arquivo</button>
    </div>
    <div id="historicoAnexos" class="space-y-3 max-h-64 overflow-y-auto"></div>
</div>

<!-- ABA PEDIDOS ERP -->
<div id="tabPedidos" class="hidden space-y-4 pt-4">
    <div class="bg-indigo-50 p-4 rounded-xl">
        <div class="flex items-center gap-3 mb-3">
            <i class="fa-solid fa-truck text-indigo-500 text-xl"></i>
            <h4 class="font-bold text-indigo-800">Últimos Pedidos no ERP</h4>
            <span id="totalPedidosERP" class="ml-auto text-sm bg-indigo-200 px-3 py-1 rounded-full text-indigo-700">0 pedidos</span>
        </div>
        <div id="listaPedidosERP" class="space-y-3 max-h-96 overflow-y-auto">
            <p class="text-center text-slate-400 py-4">Carregando pedidos...</p>
        </div>
    </div>

    <!-- Busca por CNPJ manual -->
    <div class="bg-slate-50 p-4 rounded-xl border-2 border-dashed border-slate-200">
        <h5 class="font-bold text-sm mb-2 flex items-center gap-2">
            <i class="fa-solid fa-search text-indigo-500"></i> Buscar Cliente por CNPJ/CPF
        </h5>
        <p class="text-xs text-slate-400 mb-2">Digite o CNPJ ou CPF para buscar pedidos no ERP</p>
        <div class="flex gap-2">
            <input type="text" id="buscaCnpj" class="flex-1 p-2 border rounded-xl text-sm" placeholder="00.000.000/0000-00">
            <button onclick="buscarPedidosPorCnpj()" class="px-4 py-2 bg-indigo-500 text-white rounded-xl text-sm font-bold hover:bg-indigo-600 transition-all flex items-center gap-2">
                <i class="fa-solid fa-magnifying-glass"></i> Buscar
            </button>
        </div>
        <div id="resultadoBuscaCnpj" class="mt-3 hidden"></div>
    </div>
</div>


<div class="pt-4 flex gap-3 border-t border-slate-100">
    <button onclick="window.salvarCliente()" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
        💾 Salvar Cliente
    </button>
    

    <button onclick="window.mesclarClienteERP()" 
    class="px-4 py-3 bg-indigo-600 text-white rounded-xl font-bold hover:bg-indigo-700 transition-all flex items-center gap-2" 
    title="Mesclar com ERP">
    <i class="fa-solid fa-link"></i> Mesclar ERP
</button>

<button onclick="fecharModalCliente()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
    Cancelar
</button>
</div>
</div>
</div>
</div>
</div>

<!-- MODAL DE AGENDAMENTO GLOBAL -->
<div id="modalAgendamento" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalAgendamento()"></div>
        <div class="relative bg-white rounded-3xl max-w-md w-full shadow-2xl">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 flex items-center justify-between rounded-t-3xl">
                <h3 class="text-lg font-bold text-white">
                    <i class="fa-regular fa-calendar-plus mr-2"></i>Novo Agendamento
                </h3>
                <button onclick="fecharModalAgendamento()" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <!-- Cliente -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Cliente *</label>
                    <select id="agendamentoCliente" class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all">
                        <option value="">Carregando clientes...</option>
                    </select>
                </div>
                
                <!-- Data/Hora Início e Término -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Início *</label>
                        <input type="datetime-local" id="agendamentoDataHora" 
                        class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Término</label>
                        <input type="datetime-local" id="agendamentoDataHoraFim" 
                        class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all">
                    </div>
                </div>
                
                <!-- Tipo e Duração -->
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tipo</label>
                        <select id="agendamentoTipo" class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all">
                            <option value="reuniao">📅 Reunião</option>
                            <option value="ligacao">📞 Ligação</option>
                            <option value="visita">🏢 Visita</option>
                            <option value="whatsapp">💬 WhatsApp</option>
                            <option value="email">📧 Email</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Duração</label>
                        <select id="agendamentoDuracao" class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all">
                            <option value="15">15 min</option>
                            <option value="30" selected>30 min</option>
                            <option value="45">45 min</option>
                            <option value="60">1 hora</option>
                            <option value="90">1h30</option>
                            <option value="120">2 horas</option>
                        </select>
                    </div>
                </div>
                
                <!-- Título -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Título</label>
                    <input type="text" id="agendamentoTitulo" 
                    class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all" 
                    placeholder="Ex: Follow-up proposta">
                </div>
                
                <!-- Descrição -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Descrição</label>
                    <textarea id="agendamentoDescricao" rows="2" 
                    class="w-full p-2 border rounded-xl text-sm focus:ring-2 focus:ring-blue-400 transition-all" 
                    placeholder="Detalhes do compromisso..."></textarea>
                </div>
                
                <!-- Botão Agendar -->
                <button onclick="salvarAgendamentoGlobal()" 
                class="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 text-white rounded-xl font-bold hover:from-blue-700 hover:to-blue-800 transition-all shadow-md flex items-center justify-center gap-2">
                <i class="fa-regular fa-calendar-check"></i> Agendar
            </button>
        </div>
    </div>
</div>
</div>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>