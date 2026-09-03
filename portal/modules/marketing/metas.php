<?php
$pageTitle = 'Metas & Objetivos | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">

<style>
:root {
    --meta-primary: #10b981;
    --meta-secondary: #3b82f6;
    --meta-warning: #f59e0b;
    --meta-danger: #ef4444;
}

.meta-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
}
.meta-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15);
}
.meta-card-expanded {
    border-left: 4px solid var(--meta-primary);
}

/* Progress Ring */
.progress-ring {
    transition: stroke-dashoffset 0.5s ease;
}

/* Timeline alimentação */
.timeline-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.timeline-line {
    position: absolute;
    left: 3px;
    top: 20px;
    bottom: 0;
    width: 2px;
    background: linear-gradient(180deg, #e2e8f0 0%, #cbd5e1 100%);
}

/* Modal alimentação */
.modal-alimentacao {
    animation: slideUp 0.3s ease;
}
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* KPI Cards */
.kpi-card {
    transition: all 0.2s ease;
    cursor: pointer;
}
.kpi-card:hover {
    transform: translateY(-2px);
}

/* Filtros modernos */
.filter-chip {
    transition: all 0.2s ease;
    cursor: pointer;
}
.filter-chip.active {
    background: var(--meta-primary) !important;
    color: white !important;
    border-color: var(--meta-primary) !important;
}

/* Cards de indicadores dentro da meta */
.indicator-card {
    background: #f8fafc;
    border-radius: 12px;
    padding: 12px;
    transition: all 0.2s ease;
}
.indicator-card:hover {
    background: #f1f5f9;
    transform: translateY(-1px);
}
</style>
';
require_once __DIR__ . '/../../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" x-data="metasUnificado()" x-init="init()">

    <!-- Header -->
    <div class="rounded-3xl p-5 mb-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="dashboard.php" class="btn-voltar flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-bullseye text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">METAS & OBJETIVOS</h2>
                <span class="text-xs text-slate-400 font-medium">Acompanhamento e Alimentação</span>
            </div>
        </div>
        <div class="flex gap-2 w-full lg:w-auto">
            <button x-show="podeCriarMeta" @click="abrirModalNovaMeta()" class="flex-1 lg:flex-none px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-plus"></i> Nova Meta
            </button>
            <button @click="exportarRelatorio" class="flex-1 lg:flex-none px-4 py-2 bg-slate-600 text-white rounded-xl text-sm font-bold hover:bg-slate-700 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-download"></i> Exportar
            </button>
        </div>
    </div>

    <!-- KPIs Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="kpi-card bg-gradient-to-br from-emerald-500 to-emerald-600 rounded-2xl p-4 text-white shadow-lg" @click="filtrarStatus('ativas')">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-emerald-100 text-[10px] uppercase font-bold">Metas Ativas</span>
                    <div class="text-3xl font-black mt-1" x-text="totais.ativas">0</div>
                </div>
                <i class="fa-solid fa-bullseye text-3xl text-white/30"></i>
            </div>
        </div>
        <div class="kpi-card bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl p-4 text-white shadow-lg" @click="scrollToMetas()">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-blue-100 text-[10px] uppercase font-bold">Progresso Médio</span>
                    <div class="text-3xl font-black mt-1" x-text="totais.progressoMedio + '%'">0%</div>
                </div>
                <i class="fa-solid fa-chart-line text-3xl text-white/30"></i>
            </div>
            <div class="w-full bg-white/20 rounded-full h-1.5 mt-2">
                <div class="bg-white rounded-full h-1.5 transition-all duration-700" :style="`width: ${totais.progressoMedio}%`"></div>
            </div>
        </div>
        <div class="kpi-card bg-gradient-to-br from-amber-500 to-amber-600 rounded-2xl p-4 text-white shadow-lg" @click="scrollToMetas()">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-amber-100 text-[10px] uppercase font-bold">Metas Concluídas</span>
                    <div class="text-3xl font-black mt-1" x-text="totais.concluidas">0</div>
                </div>
                <i class="fa-solid fa-check-circle text-3xl text-white/30"></i>
            </div>
        </div>
        <div class="kpi-card bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-4 text-white shadow-lg" @click="scrollToMetas()">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-purple-100 text-[10px] uppercase font-bold">Total Geral</span>
                    <div class="text-3xl font-black mt-1" x-text="totais.total">0</div>
                </div>
                <i class="fa-solid fa-chart-simple text-3xl text-white/30"></i>
            </div>
        </div>
    </div>

    <!-- Filtros Modernos -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-6">
        <div class="flex flex-wrap gap-3 items-center">
            <div class="flex gap-2">
                <button @click="filtroStatus = 'todas'; filtrar()" 
                class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold transition-all"
                :class="filtroStatus === 'todas' ? 'active bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'">
                Todas
            </button>
            <button @click="filtroStatus = 'ativa'; filtrar()" 
            class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold transition-all"
            :class="filtroStatus === 'ativa' ? 'active bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'">
            Ativas
        </button>
        <button @click="filtroStatus = 'concluida'; filtrar()" 
        class="filter-chip px-3 py-1.5 rounded-full text-xs font-bold transition-all"
        :class="filtroStatus === 'concluida' ? 'active bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'">
        Concluídas
    </button>
</div>
<div class="flex-1 relative">
    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
    <input type="text" x-model="filtroBusca" @input="filtrar()" 
    placeholder="Buscar meta..." 
    class="w-full pl-9 pr-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-400 outline-none">
</div>
<span class="text-xs text-slate-400" x-text="`${metasFiltradas.length} meta(s)`"></span>
</div>
</div>

<!-- Lista de Metas - Cards Expandíveis -->
<div class="space-y-4" id="metasList">
    <template x-for="meta in metasFiltradas" :key="meta.id">
        <div class="meta-card bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden"
        :class="{ 'meta-card-expanded': meta.expandido }"
        @click="toggleExpandir(meta.id)">

        <!-- Cabeçalho do Card -->
        <div class="p-5">
            <div class="flex flex-wrap justify-between items-start gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <i class="fa-solid" :class="meta.icone || 'fa-bullseye'" 
                        :style="'color: ' + (meta.cor === 'emerald' ? '#10b981' : meta.cor === 'blue' ? '#3b82f6' : meta.cor === 'purple' ? '#8b5cf6' : '#64748b')"></i>
                        <h3 class="text-lg font-bold text-slate-800 truncate" x-text="meta.titulo"></h3>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                        :class="meta.status === 'ativa' ? 'bg-emerald-100 text-emerald-700' : 
                        (meta.status === 'concluida' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600')"
                        x-text="meta.status === 'ativa' ? 'Ativa' : (meta.status === 'concluida' ? 'Concluída' : meta.status)">
                    </span>
                </div>
                <p class="text-sm text-slate-400 mt-1" x-text="meta.tipo_nome || 'Meta Padrão'"></p>
            </div>

            <!-- Progresso Circular -->
            <div class="relative w-14 h-14 flex-shrink-0">
                <svg class="w-full h-full transform -rotate-90">
                    <circle cx="28" cy="28" r="24" fill="none" stroke="#e2e8f0" stroke-width="4"/>
                    <circle cx="28" cy="28" r="24" fill="none" :stroke="meta.status === 'concluida' ? '#3b82f6' : '#10b981'" 
                    stroke-width="4" stroke-linecap="round"
                    :stroke-dasharray="`${2 * Math.PI * 24}`"
                    :stroke-dashoffset="`${2 * Math.PI * 24 * (1 - (meta.progresso || 0) / 100)}`"
                    class="progress-ring"/>
                </svg>
                <div class="absolute inset-0 flex items-center justify-center">
                    <span class="text-xs font-bold" :class="meta.progresso >= 100 ? 'text-emerald-600' : 'text-slate-600'" 
                      x-text="(meta.progresso || 0).toFixed(0) + '%'"></span>
                  </div>
              </div>
          </div>

          <!-- ✅ ABORDAGEM HÍBRIDA - Cards de Indicadores -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mt-4">
            <!-- Valor da Semana (Último Registro) -->
            <div class="indicator-card text-center">
                <div class="flex items-center justify-center gap-1 mb-1">
                    <i class="fa-solid fa-calendar-week text-amber-500 text-xs"></i>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">Esta Semana</span>
                </div>
                <div class="text-lg font-black text-amber-600" x-text="formatarValor(meta.valor_semana, 'valor')">0</div>
                <div class="text-[9px] text-slate-400 mt-0.5" x-text="meta.periodo_semana || 'último registro'"></div>
            </div>

            <!-- Total Acumulado -->
            <div class="indicator-card text-center bg-emerald-50">
                <div class="flex items-center justify-center gap-1 mb-1">
                    <i class="fa-solid fa-chart-line text-emerald-500 text-xs"></i>
                    <span class="text-[10px] text-emerald-600 uppercase font-bold">Acumulado</span>
                </div>
                <div class="text-lg font-black text-emerald-600" x-text="formatarValor(meta.valor_acumulado, 'valor')">0</div>
                <div class="text-[9px] text-emerald-400 mt-0.5">progresso total</div>
            </div>

            <!-- Meta Final -->
            <div class="indicator-card text-center">
                <div class="flex items-center justify-center gap-1 mb-1">
                    <i class="fa-solid fa-bullseye text-slate-500 text-xs"></i>
                    <span class="text-[10px] text-slate-400 uppercase font-bold">Meta Final</span>
                </div>
                <div class="text-lg font-black text-slate-700" x-text="formatarValor(meta.meta_final, 'meta')">0</div>
                <div class="text-[9px] text-slate-400 mt-0.5">objetivo a atingir</div>
            </div>
        </div>

      <!-- Barra de Progresso Acumulado -->
<div class="mt-4">
    <div class="flex justify-between text-xs mb-1">
        <span class="text-slate-500">📊 Progresso Acumulado</span>
        <span class="font-bold text-emerald-600" x-text="Math.max(0, Math.min(100, meta.progresso || 0)).toFixed(1) + '%'"></span>
    </div>
    <div class="w-full h-2.5 bg-slate-200 rounded-full overflow-hidden">
        <div class="h-full rounded-full transition-all duration-500 bg-gradient-to-r from-emerald-500 to-teal-500"
        :style="`width: ${Math.max(0, Math.min(100, meta.progresso || 0))}%`"></div>
    </div>
</div>



        <!-- Informações Rápidas -->
        <div class="flex flex-wrap justify-between items-center mt-3 text-xs text-slate-400">
            <div class="flex items-center gap-3">
                <span><i class="fa-regular fa-calendar mr-1"></i> <span x-text="formatarData(meta.data_inicio)"></span> → <span x-text="formatarData(meta.data_fim)"></span></span>
                <span class="hidden sm:inline">•</span>
                <span :class="diasRestantes(meta) <= 7 && meta.status === 'ativa' ? 'text-rose-600 font-bold' : ''">
                    <i class="fa-regular fa-clock mr-1"></i> <span x-text="textoDiasRestantes(meta)"></span>
                </span>
            </div>
            <div class="flex gap-2 mt-2 sm:mt-0" @click.stop>
                <button @click="abrirAlimentacao(meta)" 
                class="px-3 py-1.5 bg-emerald-50 text-emerald-700 rounded-lg text-xs font-bold hover:bg-emerald-100 transition-all flex items-center gap-1">
                <i class="fa-solid fa-pen"></i> Alimentar
            </button>
            <button x-show="podeEditar" @click="editarMeta(meta)" 
            class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-200 transition-all flex items-center gap-1">
            <i class="fa-solid fa-pen"></i> Editar
        </button>
        <button @click="toggleExpandir(meta.id)" class="px-3 py-1.5 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-200 transition-all">
            <i class="fa-solid" :class="meta.expandido ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>
    </div>
</div>
</div>

<!-- Conteúdo Expandido (Histórico de Alimentação) -->
<div x-show="meta.expandido" x-transition.duration.300ms class="border-t border-slate-100 bg-slate-50">
    <div class="p-5">
        <!-- Campos da Meta -->
        <div x-show="meta.valores" class="mb-4">
            <p class="text-xs font-bold text-slate-400 uppercase mb-2 flex items-center gap-2">
                <i class="fa-solid fa-sliders-h text-xs"></i> Metas Específicas
            </p>
            <div class="flex flex-wrap gap-2">
                <template x-for="(valor, campo) in parseValores(meta.valores)" :key="campo">
                    <div class="bg-white rounded-lg px-3 py-1.5 shadow-sm">
                        <span class="text-xs text-slate-500" x-text="formatarCampo(campo)"></span>
                        <span class="text-sm font-bold text-slate-700 ml-1" x-text="formatarValor(valor, campo)"></span>
                    </div>
                </template>
            </div>
        </div>

        <!-- Timeline de Alimentação -->
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase mb-3 flex items-center gap-2">
                <i class="fa-solid fa-history text-xs"></i> Histórico de Alimentação
                <span class="text-[10px] bg-slate-200 px-2 py-0.5 rounded-full" x-text="(meta.historico || []).length + ' registros'"></span>
            </p>

            <div x-show="!meta.historico || meta.historico.length === 0" class="text-center py-6">
                <i class="fa-regular fa-chart-line text-3xl text-slate-300 mb-2 block"></i>
                <p class="text-sm text-slate-400">Nenhum registro de alimentação</p>
                <button @click="abrirAlimentacao(meta)" class="mt-2 text-xs text-emerald-600 font-bold hover:text-emerald-700">
                    <i class="fa-solid fa-plus mr-1"></i> Registrar primeiro
                </button>
            </div>

            <div x-show="meta.historico && meta.historico.length > 0" class="space-y-3 max-h-80 overflow-y-auto">
                <template x-for="(reg, idx) in meta.historico" :key="reg.id">
                    <div class="bg-white rounded-xl p-3 shadow-sm hover:shadow-md transition-all relative">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="timeline-dot" :class="idx === 0 ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                                    <span class="text-sm font-bold text-slate-700">
                                        <i class="fa-regular fa-calendar mr-1"></i> <span x-text="formatarData(reg.data_registro)"></span>
                                    </span>
                                    <span class="text-[10px] text-slate-400" x-show="idx === 0">(último)</span>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="(val, campo) in parseValores(reg.valores)" :key="campo">
                                        <div class="text-xs bg-slate-50 px-2 py-1 rounded-lg">
                                            <span class="text-slate-500" x-text="formatarCampo(campo)"></span>:
                                            <span class="font-bold text-slate-700 ml-1" x-text="formatarValor(val, campo)"></span>
                                        </div>
                                    </template>
                                </div>
                                <p class="text-[10px] text-slate-400 mt-2" x-show="reg.usuario_nome">
                                    <i class="fa-regular fa-user mr-1"></i> <span x-text="reg.usuario_nome"></span>
                                </p>
                            </div>
                            <button x-show="podeEditar" @click="editarRegistro(meta, reg)" 
                            class="text-slate-400 hover:text-emerald-600 transition-all p-1">
                            <i class="fa-solid fa-pen text-xs"></i>
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Resumo de Acumulado por Período -->
        <div x-show="meta.historico && meta.historico.length > 0" class="mt-4 p-3 bg-white rounded-xl">
            <p class="text-xs font-bold text-slate-600 mb-2">📈 Evolução Acumulada</p>
            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                <div>
                    <span class="text-slate-400 block">Este Mês</span>
                    <span class="font-bold text-emerald-600" x-text="formatarValor(meta.acumulado_mes, 'valor')"></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Últimos 30d</span>
                    <span class="font-bold text-blue-600" x-text="formatarValor(meta.acumulado_30d, 'valor')"></span>
                </div>
                <div>
                    <span class="text-slate-400 block">Total</span>
                    <span class="font-bold text-purple-600" x-text="formatarValor(meta.valor_acumulado, 'valor')"></span>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>
</template>

<!-- Empty State -->
<div x-show="metasFiltradas.length === 0" class="bg-white rounded-2xl p-12 text-center">
    <i class="fa-solid fa-bullseye text-5xl text-slate-300 mb-4 block"></i>
    <h3 class="text-lg font-bold text-slate-700 mb-2">Nenhuma meta encontrada</h3>
    <p class="text-slate-400 text-sm mb-4">Comece criando uma nova meta para acompanhar seus resultados</p>
    <button x-show="podeCriarMeta" @click="abrirModalNovaMeta()" 
    class="px-5 py-2.5 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all inline-flex items-center gap-2">
    <i class="fa-solid fa-plus"></i> Criar Nova Meta
</button>
</div>
</div>

<!-- MODAL DE ALIMENTAÇÃO -->
<div id="modalAlimentacao" class="fixed inset-0 z-50 hidden overflow-y-auto" x-show="modalAlimentacaoAberta" x-cloak>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="fecharModalAlimentacao"></div>
        <div class="relative bg-white rounded-3xl max-w-md w-full shadow-2xl modal-alimentacao">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 px-6 py-4 flex items-center justify-between rounded-t-3xl">
                <h3 class="text-xl font-bold text-white">
                    <i class="fa-solid fa-chart-line mr-2"></i>
                    <span x-text="metaSelecionada?.titulo || 'Alimentar Meta'"></span>
                </h3>
                <button @click="fecharModalAlimentacao" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <!-- Data -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Data do Registro</label>
                    <input type="date" x-model="alimentacaoData" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-blue-400">
                </div>
                
                <!-- Campo de Valor Atual -->
                <template x-if="campoValorAtual">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">
                            <span x-text="campoValorAtual.rotulo || 'Valor Alcançado'"></span>
                            <template x-if="campoValorAtual.unidade">
                                <span class="text-slate-300" x-text="' (' + campoValorAtual.unidade + ')'"></span>
                            </template>
                        </label>
                        <input type="number" step="0.01" :placeholder="campoValorAtual.rotulo || 'Valor alcançado'"
                               x-model="alimentacaoValor" class="w-full p-3 border rounded-xl focus:ring-2 focus:ring-blue-400 text-lg font-bold">
                        <p class="text-xs text-slate-400 mt-1">
                            <i class="fa-solid fa-info-circle mr-1"></i>
                            Este valor será SOMADO ao acumulado atual
                        </p>
                    </div>
                </template>
                
                <!-- Informações da Meta -->
                <div class="bg-slate-50 rounded-xl p-4">
                    <div class="grid grid-cols-2 gap-3 text-center">
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase">Acumulado Atual</span>
                            <p class="text-lg font-bold text-emerald-600" x-text="formatarValor(metaValorAcumulado, 'valor')"></p>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 uppercase">Meta Final</span>
                            <p class="text-lg font-bold text-slate-700" x-text="formatarValor(metaMetaFinal, 'meta')"></p>
                        </div>
                    </div>
                    <div class="mt-3 pt-2 border-t border-slate-200">
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-500">Progresso Acumulado</span>
                            <span class="font-bold" x-text="calcularProgressoAtual() + '%'"></span>
                        </div>
                        <div class="w-full h-2 bg-slate-200 rounded-full overflow-hidden">
                            <div class="h-full bg-emerald-500 rounded-full transition-all" :style="`width: ${calcularProgressoAtual()}%`"></div>
                        </div>
                        <p class="text-xs text-emerald-600 mt-2 text-center" x-show="calcularAposSalvar() > 0">
                            Após salvar: +<span x-text="formatarValor(alimentacaoValor, 'valor')"></span> → 
                            <span x-text="formatarValor(metaValorAcumulado + alimentacaoValor, 'valor')"></span> 
                            (<span x-text="calcularProgressoAposSalvar() + '%'"></span>)
                        </p>
                    </div>
                </div>
                
                <!-- Botões -->
                <div class="pt-4 flex gap-3">
                    <button @click="salvarAlimentacao" class="flex-1 px-4 py-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all">
                        <i class="fa-solid fa-save mr-1"></i>Salvar Registro
                    </button>
                    <button @click="fecharModalAlimentacao" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE NOVA META -->
<div id="modalNovaMeta" class="fixed inset-0 z-50 hidden overflow-y-auto" x-show="modalNovaMetaAberta" x-cloak>
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="fecharModalNovaMeta"></div>
        <div class="relative bg-white rounded-3xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between rounded-t-3xl sticky top-0 z-10">
                <h3 class="text-xl font-bold text-white">
                    <i class="fa-solid fa-plus-circle mr-2"></i> Nova Meta
                </h3>
                <button @click="fecharModalNovaMeta" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Tipo de Meta *</label>
                    <select x-model="novaMetaTipoId" class="w-full p-3 border rounded-xl text-sm focus:ring-2 focus:ring-emerald-400">
                        <option value="">Selecione um tipo...</option>
                        <template x-for="tipo in tiposMeta" :key="tipo.id">
                            <option :value="tipo.id" x-text="tipo.nome"></option>
                        </template>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Título *</label>
                    <input type="text" x-model="novaMetaTitulo" class="w-full p-3 border rounded-xl text-sm" placeholder="Ex: Black Friday 2024">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Data Início</label>
                        <input type="date" x-model="novaMetaInicio" class="w-full p-3 border rounded-xl text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Data Fim</label>
                        <input type="date" x-model="novaMetaFim" class="w-full p-3 border rounded-xl text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-1">Valores</label>
                    <div id="novaMetaCampos" class="space-y-3"></div>
                </div>
                <div class="pt-4 flex gap-3">
                    <button @click="salvarNovaMeta" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
                        <i class="fa-solid fa-save mr-1"></i> Criar Meta
                    </button>
                    <button @click="fecharModalNovaMeta" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/portal/assets/js/marketing-utils.js?v=<?= $version ?>"></script>

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
// METAS UNIFICADO - COMPONENTE PRINCIPAL (ALPINE.JS)
// ============================================================================

    function metasUnificado() {
        return {
        // Estado
            metas: [],
            tiposMeta: [],
            metasFiltradas: [],
            filtroStatus: 'todas',
            filtroBusca: '',
            totais: { ativas: 0, concluidas: 0, total: 0, progressoMedio: 0 },
            podeEditar: false,
            podeCriarMeta: false,

        // Modal Alimentação
            modalAlimentacaoAberta: false,
            metaSelecionada: null,
            alimentacaoData: new Date().toISOString().slice(0, 10),
            alimentacaoValor: 0,
            campoValorAtual: null,
            metaTaxaInicial: 0,
            metaMetaFinal: 0,
            metaValorAcumulado: 0,

        // Modal Nova Meta
            modalNovaMetaAberta: false,
            novaMetaTipoId: '',
            novaMetaTitulo: '',
            novaMetaInicio: new Date().toISOString().slice(0, 10),
            novaMetaFim: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10),
            novaMetaCampos: [],

        // ====================================================================
        // FUNÇÕES DE AUTENTICAÇÃO (usando MarketingUtils)
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
            formatarValor: MarketingUtils.formatarValor,
            formatarData: MarketingUtils.formatarData,

        // ====================================================================
        // INICIALIZAÇÃO
        // ====================================================================
            async init() {
                await Promise.all([
                    this.carregarTiposMeta(),
                    this.carregarMetas()
                ]);
                await this.verificarPermissoes();
                
                // ⭐ VERIFICAR SE TEM PARÂMETRO NA URL PARA ABRIR ALIMENTAÇÃO
                const urlParams = new URLSearchParams(window.location.search);
                const metaId = urlParams.get('meta');
                const acao = urlParams.get('acao');
                
                if (metaId && acao === 'alimentar') {
                    setTimeout(() => {
                        const meta = this.metas.find(m => m.id == metaId);
                        if (meta) {
                            this.abrirAlimentacao(meta);
                        }
                    }, 800);
                }
            },

            isAdminOrSupervisor() {
                try {
                    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
                    const permissoes = userData.permissoes || [];
                    const uid = userData.uid || userData.idusuario || 0;
                    if (uid === 4) return true;
                    if (permissoes.includes(22) || permissoes.includes('22')) return true;
                    if (permissoes.includes('admin') || permissoes.includes('marketing-admin')) return true;
                    return false;
                } catch(e) { return false; }
            },

            async verificarPermissoes() {
                this.podeEditar = this.isAdminOrSupervisor();
                this.podeCriarMeta = this.podeEditar;
            },

        // ====================================================================
        // TIPOS DE META
        // ====================================================================
            async carregarTiposMeta() {
                try {
                    const resp = await this.fetchWithAuth('/v1/meta-builder/tipos');
                    const data = await resp.json();
                    if (data.success) this.tiposMeta = data.data || [];
                } catch(e) { console.error('Erro tipos:', e); }
            },

// ============================================================
// CORREÇÃO: Cálculo de acumulados
// ============================================================

async carregarMetas() {
    try {
        const resp = await this.fetchWithAuth('/v1/meta-builder/instancias/ativas');
        const data = await resp.json();

        if (data.success && data.data) {
            for (const meta of data.data) {
                meta.expandido = false;
                meta.historico = await this.carregarHistoricoMeta(meta.id);

                let valores = {};
                try {
                    valores = typeof meta.valores === 'string' ? JSON.parse(meta.valores) : (meta.valores || {});
                } catch(e) { valores = {}; }
                meta.valores = valores;

                // Identificar campos da meta
                let taxaInicial = 0, metaFinal = 0, nomeValorAtual = 'valor_alcancado';
                if (meta.campos && meta.campos.length > 0) {
                    const campoInicial = meta.campos.find(c => c.tipo_comparacao === 'taxa_inicial');
                    const campoFinal = meta.campos.find(c => c.tipo_comparacao === 'meta_final');
                    const campoValor = meta.campos.find(c => c.tipo_comparacao === 'valor_atual');
                    if (campoInicial) taxaInicial = parseFloat(valores[campoInicial.nome_campo] || 0);
                    if (campoFinal) metaFinal = parseFloat(valores[campoFinal.nome_campo] || 0);
                    if (campoValor) nomeValorAtual = campoValor.nome_campo;
                }
                meta.taxa_inicial = taxaInicial;
                meta.meta_final = metaFinal;
                meta.nome_valor_atual = nomeValorAtual;

                // ============================================================
                // ✅ CÁLCULO CORRIGIDO DOS ACUMULADOS
                // ============================================================
                let valorAcumulado = taxaInicial; // Começa com a taxa inicial
                let ultimoValor = 0;
                let ultimaData = null;
                let acumuladoMes = 0;
                let acumulado30d = 0;
                
                const hoje = new Date();
                // ⭐ CORREÇÃO: Data sem timezone para comparação
                const hojeStr = hoje.toISOString().slice(0, 10);
                const inicioMesStr = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-01`;
                const inicio30d = new Date(hoje);
                inicio30d.setDate(hoje.getDate() - 30);
                const inicio30dStr = inicio30d.toISOString().slice(0, 10);

                if (meta.historico && meta.historico.length > 0) {
                    // Ordenar do mais antigo para o mais novo
                    const historicoOrdenado = [...meta.historico].sort((a, b) => 
                        new Date(a.data_registro) - new Date(b.data_registro)
                    );

                    for (const reg of historicoOrdenado) {
                        // ⭐ CORREÇÃO: Usar a data como string, não converter para Date
                        const dataRegStr = reg.data_registro; // Já está no formato YYYY-MM-DD
                        const vals = typeof reg.valores === 'string' ? JSON.parse(reg.valores) : (reg.valores || {});
                        const valorRegistro = parseFloat(vals[nomeValorAtual] || 0);

                        if (valorRegistro > 0) {
                            // Acumulado total: soma todos os registros (já começa com taxa_inicial)
                            valorAcumulado += valorRegistro;
                            
                            ultimoValor = valorRegistro;
                            ultimaData = reg.data_registro;

                            // ⭐ CORREÇÃO: Comparar strings, não Date (evita timezone)
                            // Este mês (a partir do dia 1)
                            if (dataRegStr >= inicioMesStr) {
                                acumuladoMes += valorRegistro;
                            }
                            
                            // Últimos 30 dias
                            if (dataRegStr >= inicio30dStr) {
                                acumulado30d += valorRegistro;
                            }
                        }
                    }
                }

                // ⭐ CORREÇÃO: Calcular progresso com base no valor acumulado
                meta.valor_acumulado = valorAcumulado;
                meta.valor_semana = ultimoValor;
                meta.periodo_semana = ultimaData ? `semana de ${this.formatarData(ultimaData)}` : 'sem dados';
                meta.acumulado_mes = acumuladoMes;
                meta.acumulado_30d = acumulado30d;

                // Calcular progresso
                let progresso = 0;
                const necessario = metaFinal - taxaInicial;

                if (necessario > 0) {
                    const conquistado = valorAcumulado - taxaInicial;
                    progresso = Math.max(0, Math.min(100, (conquistado / necessario) * 100));
                } else if (necessario < 0) {
                    const necessarioReducao = taxaInicial - metaFinal;
                    if (necessarioReducao > 0) {
                        const reduzido = taxaInicial - valorAcumulado;
                        progresso = Math.max(0, Math.min(100, (reduzido / necessarioReducao) * 100));
                    }
                } else if (metaFinal > 0) {
                    progresso = Math.max(0, Math.min(100, (valorAcumulado / metaFinal) * 100));
                } else {
                    progresso = 0;
                }

                meta.progresso = progresso;
            }

            this.metas = data.data;
            this.filtrar();
            this.calcularTotais();
        }
    } catch(e) { 
        console.error('Erro metas:', e); 
        this.metas = [];
        this.metasFiltradas = [];
    }
},

            async carregarHistoricoMeta(metaId) {
                try {
                    const resp = await this.fetchWithAuth(`/v1/meta-builder/alimentacao/${metaId}`);
                    const data = await resp.json();
                    return data.success ? (data.data || []) : [];
                } catch(e) { return []; }
            },

            parseValores(valores) {
                if (!valores) return {};
                try {
                    return typeof valores === 'string' ? JSON.parse(valores) : valores;
                } catch(e) { return {}; }
            },

            filtrar() {
                let resultado = [...this.metas];
                if (this.filtroStatus !== 'todas') {
                    resultado = resultado.filter(m => m.status === this.filtroStatus);
                }
                if (this.filtroBusca) {
                    const termo = this.filtroBusca.toLowerCase();
                    resultado = resultado.filter(m => 
                        m.titulo?.toLowerCase().includes(termo) ||
                        m.tipo_nome?.toLowerCase().includes(termo)
                        );
                }
                this.metasFiltradas = resultado;
            },

       calcularTotais() {
    const ativas = this.metas.filter(m => m.status === 'ativa').length;
    const concluidas = this.metas.filter(m => m.status === 'concluida' || m.progresso >= 100).length;
    let somaProgresso = 0;
    this.metas.forEach(m => { 
        // ⭐ GARANTIR QUE O PROGRESSO NUNCA SEJA NEGATIVO
        somaProgresso += Math.max(0, Math.min(100, m.progresso || 0)); 
    });
    const progressoMedio = this.metas.length > 0 ? Math.round(somaProgresso / this.metas.length) : 0;
    this.totais = { 
        ativas, 
        concluidas, 
        total: this.metas.length, 
        progressoMedio 
    };
},

            toggleExpandir(id) {
                const meta = this.metas.find(m => m.id === id);
                if (meta) meta.expandido = !meta.expandido;
            },

            diasRestantes(meta) {
                if (meta.status === 'concluida') return 0;
                if (!meta.data_fim) return 0;
                const fim = new Date(meta.data_fim);
                const hoje = new Date();
                const diff = Math.ceil((fim - hoje) / (1000 * 60 * 60 * 24));
                return diff > 0 ? diff : 0;
            },

            textoDiasRestantes(meta) {
                const dias = this.diasRestantes(meta);
                if (dias === 0) return 'Finalizada';
                if (dias === 1) return 'Último dia!';
                return `${dias} dias restantes`;
            },

            formatarCampo(campo) {
                const mapa = {
                    'meta_leads': 'Leads', 'meta_faturamento': 'Faturamento',
                    'leads': 'Leads', 'faturamento': 'Faturamento',
                    'investimento': 'Investimento', 'roas_alvo': 'ROAS Alvo',
                    'taxa_atual': 'Taxa Inicial', 'meta_final': 'Meta Final',
                    'valor_alcancado': 'Valor Alcançado'
                };
                return mapa[campo] || campo.replace(/_/g, ' ').toUpperCase();
            },

            calcularProgressoAtual() {
                if (!this.metaSelecionada) return 0;
                const meta = this.metaSelecionada;
                const valor = this.metaValorAcumulado + this.alimentacaoValor;

                if (meta.meta_final > meta.taxa_inicial) {
                    const necessario = meta.meta_final - meta.taxa_inicial;
                    const conquistado = valor - meta.taxa_inicial;
                    return Math.min(Math.round((conquistado / necessario) * 100), 100);
                } else if (meta.meta_final > 0) {
                    return Math.min(Math.round((valor / meta.meta_final) * 100), 100);
                }
                return 0;
            },

            calcularProgressoAposSalvar() {
                if (!this.metaSelecionada) return 0;
                const meta = this.metaSelecionada;
                const novoAcumulado = this.metaValorAcumulado + this.alimentacaoValor;

                if (meta.meta_final > meta.taxa_inicial) {
                    const necessario = meta.meta_final - meta.taxa_inicial;
                    const conquistado = novoAcumulado - meta.taxa_inicial;
                    return Math.min(Math.round((conquistado / necessario) * 100), 100);
                } else if (meta.meta_final > 0) {
                    return Math.min(Math.round((novoAcumulado / meta.meta_final) * 100), 100);
                }
                return 0;
            },

            calcularAposSalvar() {
                return this.alimentacaoValor > 0;
            },

           async abrirAlimentacao(meta) {
    this.metaSelecionada = meta;
    this.alimentacaoData = new Date().toISOString().slice(0, 10);
    this.metaTaxaInicial = meta.taxa_inicial || 0;
    this.metaMetaFinal = meta.meta_final || 0;
    this.metaValorAcumulado = meta.valor_acumulado || 0;

    // ✅ Garantir que campoValorAtual nunca seja null
    this.campoValorAtual = null;
    
    if (meta.campos && meta.campos.length > 0) {
        // Tentar encontrar campo do tipo 'valor_atual' que é editável
        this.campoValorAtual = meta.campos.find(c => 
            c.tipo_comparacao === 'valor_atual' && c.editavel !== false
        );
        
        // Se não encontrou, pegar qualquer campo editável
        if (!this.campoValorAtual) {
            this.campoValorAtual = meta.campos.find(c => c.editavel !== false);
        }
    }
    
    // ✅ Fallback: criar objeto padrão se ainda for null
    if (!this.campoValorAtual) {
        this.campoValorAtual = {
            nome_campo: 'valor_alcancado',
            rotulo: 'Valor Alcançado',
            tipo_campo: 'number',
            unidade: this.metaMetaFinal > 1000 ? 'R$' : '',
            obrigatorio: true,
            editavel: true
        };
    }

    // Buscar valor existente para a data atual
    this.alimentacaoValor = 0;
    if (meta.historico && meta.historico.length > 0) {
        const regHoje = meta.historico.find(r => r.data_registro === this.alimentacaoData);
        if (regHoje && regHoje.valores) {
            try {
                const vals = typeof regHoje.valores === 'string' ? JSON.parse(regHoje.valores) : (regHoje.valores || {});
                const valorExistente = parseFloat(vals[this.campoValorAtual.nome_campo] || 0);
                if (valorExistente > 0) {
                    this.alimentacaoValor = valorExistente;
                }
            } catch(e) {
                console.warn('Erro ao parsear valores existentes:', e);
            }
        }
    }

    // Abrir modal
    this.modalAlimentacaoAberta = true;
    document.getElementById('modalAlimentacao').classList.remove('hidden');
},

            fecharModalAlimentacao() {
                this.modalAlimentacaoAberta = false;
                document.getElementById('modalAlimentacao').classList.add('hidden');
                this.metaSelecionada = null;
            },

            async salvarAlimentacao() {
                if (!this.metaSelecionada) return;

                if (this.alimentacaoValor <= 0) {
                    Swal.fire('Atenção', 'Informe um valor maior que zero', 'warning');
                    return;
                }

                try {
                    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
                    const usuarioId = userData.uid || userData.idusuario || 0;
                    const valores = {};
                    valores[this.campoValorAtual.nome_campo] = parseFloat(this.alimentacaoValor) || 0;

                    Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

                    const resp = await this.fetchWithAuth('/v1/meta-builder/alimentar', {
                        method: 'POST',
                        body: JSON.stringify({
                            id_meta_instancia: this.metaSelecionada.id,
                            data_registro: this.alimentacaoData,
                            valores: valores,
                            usuario_id: usuarioId
                        })
                    });

                    const data = await resp.json();
                    Swal.close();

                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Registrado!', timer: 1500, showConfirmButton: false });
                        await this.carregarMetas();
                        this.fecharModalAlimentacao();
                    } else {
                        throw new Error(data.error || 'Erro ao registrar');
                    }
                } catch(e) {
                    Swal.close();
                    Swal.fire('Erro', e.message, 'error');
                }
            },

            abrirModalNovaMeta() {
                this.novaMetaTipoId = '';
                this.novaMetaTitulo = '';
                this.novaMetaInicio = new Date().toISOString().slice(0, 10);
                this.novaMetaFim = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().slice(0, 10);
                this.novaMetaCampos = [];
                this.modalNovaMetaAberta = true;
                document.getElementById('modalNovaMeta').classList.remove('hidden');

                this.$watch('novaMetaTipoId', async (value) => {
                    if (value) {
                        await this.carregarCamposTipoMeta(value);
                    } else {
                        document.getElementById('novaMetaCampos').innerHTML = '';
                    }
                });
            },

            async carregarCamposTipoMeta(tipoId) {
                try {
                    const resp = await this.fetchWithAuth(`/v1/meta-builder/tipos/${tipoId}/campos`);
                    const data = await resp.json();

                    if (data.success && data.data) {
                        const container = document.getElementById('novaMetaCampos');
                        container.innerHTML = data.data.map(campo => `
                        <div>
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-2">
                                ${campo.rotulo || campo.nome_campo}
                            ${campo.unidade ? `<span class="text-slate-400 ml-1">(${campo.unidade})</span>` : ''}
                            </label>
                            <input type="number" 
                                   step="${campo.unidade === 'R$' ? '0.01' : '1'}"
                                   data-nome="${campo.nome_campo}"
                                   placeholder="${campo.rotulo || campo.nome_campo}"
                                   class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                        </div>
                        `).join('');
                    }
                } catch(e) {
                    console.error('Erro carregar campos:', e);
                }
            },

            fecharModalNovaMeta() {
                this.modalNovaMetaAberta = false;
                document.getElementById('modalNovaMeta').classList.add('hidden');
            },

            async salvarNovaMeta() {
                if (!this.novaMetaTipoId || !this.novaMetaTitulo) {
                    Swal.fire('Atenção', 'Preencha tipo e título da meta', 'warning');
                    return;
                }

                Swal.fire({ title: 'Criando meta...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

                try {
                    const campos = [];
                    const inputs = document.querySelectorAll('#novaMetaCampos input');
                    inputs.forEach(input => {
                        const nome = input.getAttribute('data-nome');
                        const valor = parseFloat(input.value) || 0;
                        if (nome) campos.push({ nome: nome, valor: valor });
                    });

                    const resp = await this.fetchWithAuth('/v1/meta-builder/instancias', {
                        method: 'POST',
                        body: JSON.stringify({
                            id_tipo_meta: parseInt(this.novaMetaTipoId),
                            titulo: this.novaMetaTitulo,
                            data_inicio: this.novaMetaInicio,
                            data_fim: this.novaMetaFim,
                            status: 'ativa',
                            campos: campos
                        })
                    });

                    const data = await resp.json();
                    Swal.close();

                    if (data.success) {
                        Swal.fire({ icon: 'success', title: 'Meta criada!', timer: 1500, showConfirmButton: false });
                        this.fecharModalNovaMeta();
                        await this.carregarMetas();
                    } else {
                        Swal.fire('Erro', data.error || 'Falha ao criar', 'error');
                    }
                } catch(e) {
                    Swal.close();
                    Swal.fire('Erro', e.message, 'error');
                }
            },

            editarMeta(meta) {
                window.open(`/portal/modules/marketing/admin/instancias.php?id=${meta.id}`, '_blank');
            },

            editarRegistro(meta, registro) {
                this.abrirAlimentacao(meta);
                this.alimentacaoData = registro.data_registro;
                const vals = typeof registro.valores === 'string' ? JSON.parse(registro.valores) : (registro.valores || {});
                const valorExistente = parseFloat(vals[this.campoValorAtual?.nome_campo || 'valor_alcancado'] || 0);
                if (valorExistente > 0) this.alimentacaoValor = valorExistente;
            },

            exportarRelatorio() {
                window.open('/portal/modules/marketing/relatorios.php', '_blank');
            },

            scrollToMetas() {
                document.getElementById('metasList')?.scrollIntoView({ behavior: 'smooth' });
            },

            filtrarStatus(status) {
                this.filtroStatus = status;
                this.filtrar();
            }
        };
    }

// ============================================================================
// FUNÇÕES GLOBAIS
// ============================================================================
    window.verDetalhesMeta = async function(id, titulo) {
        // Redirecionar para a página de metas com a meta selecionada
        window.location.href = `/portal/modules/marketing/metas.php?meta=${id}`;
    };

    window.isAdminOrSupervisor = function() {
        try {
            const userData = JSON.parse(localStorage.getItem('userData') || '{}');
            const permissoes = userData.permissoes || [];
            const uid = userData.uid || userData.idusuario || 0;
            if (uid === 4) return true;
            if (permissoes.includes(22) || permissoes.includes('22')) return true;
            if (permissoes.includes('admin') || permissoes.includes('marketing-admin')) return true;
            return false;
        } catch(e) { return false; }
    };

// Inicialização
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.metasApp = metasUnificado();
        window.metasApp.init();
    });
} else {
    window.metasApp = metasUnificado();
    window.metasApp.init();
}
</script>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>