<?php
$pageTitle = 'Inteligência de Recompra & Clientes Ativos Sem Compras | Nutricional';
$moduleJs = 'recompra-clientes.js';
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$version = @filemtime(__DIR__ . '/../assets/js/recompra-clientes.js') ?: 1;
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
    @media (max-width: 1023px) {
        .rc-header-desktop { display: none !important; }
        .rc-mobile-toolbar { display: block !important; }
        .rc-mobile-spacer { display: block !important; }
        .rc-filtros-desktop:not(.expandido-mobile) { display: none !important; }
        .rc-filtros-desktop.expandido-mobile { display: grid !important; }
    }
    @media (min-width: 1024px) {
        .rc-header-desktop { display: flex !important; }
        .rc-mobile-toolbar { display: none !important; }
        .rc-mobile-spacer { display: none !important; }
        .rc-filtros-desktop { display: grid !important; }
    }
</style>
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="min-h-screen bg-slate-50 pb-12" x-data="recompraClientesHandler()" x-init="init()">

    <!-- HEADER MOBILE -->
    <div class="mobile-toolbar rc-mobile-toolbar fixed top-0 left-0 right-0 z-50 shadow-lg">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="<?= $appBase ?>/portal/" class="flex items-center gap-1.5 no-underline text-white hover:text-rose-200 transition">
                <i class="fa-solid fa-arrow-left text-base"></i>
                <span class="text-xs font-extrabold tracking-wide">VOLTAR</span>
            </a>
            <div class="text-center">
                <span class="text-xs sm:text-sm font-black modulo-nome tracking-wide">RECOMPRA &amp; CLIENTES</span>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=recompra_clientes" class="px-2 py-1 rounded-lg bg-amber-400 text-slate-900 text-[10px] font-extrabold no-underline">
                    <i class="fa-solid fa-graduation-cap"></i>
                </a>
                <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
            </div>
        </div>

        <div class="flex items-center justify-between px-4 py-2 border-t border-white/10 text-xs">
            <button @click="filtrosAbertosMobile = !filtrosAbertosMobile"
                    class="flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/15 text-white font-bold text-xs">
                <i class="fa-solid fa-filter"></i>
                <span>Filtros</span>
                <span x-show="filters.filial || filters.gestor || filters.representante || filtroFaixa || filtroRegiao || buscaCliente" class="w-2 h-2 rounded-full bg-amber-400"></span>
            </button>
            <div class="flex items-center gap-2">
                <button @click="carregarDados()" class="px-2.5 py-1.5 rounded-lg bg-white/15 text-white text-xs font-bold">
                    <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
                </button>
                <button @click="exportarCSV()" class="px-2.5 py-1.5 rounded-lg bg-emerald-500 text-white text-xs font-extrabold">
                    <i class="fa-regular fa-file-excel"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="rc-mobile-spacer h-24"></div>

    <!-- CONTAINER PRINCIPAL -->
    <div class="max-w-[1720px] mx-auto px-3 sm:px-6 pt-2 lg:pt-6 space-y-5">

        <!-- HEADER DESKTOP -->
        <div class="rc-header-desktop bg-gradient-to-r from-rose-800 via-rose-700 to-orange-700 rounded-2xl shadow-lg p-5 text-white flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <a href="<?= $appBase ?>/portal/" class="px-3.5 py-2.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-black tracking-wide flex items-center gap-2 transition no-underline shadow-sm">
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>VOLTAR AO PORTAL</span>
                </a>
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="px-2.5 py-0.5 rounded-full bg-white/20 text-white text-[10px] font-extrabold uppercase tracking-wide">
                            <i class="fa-solid fa-user-clock mr-1"></i>Ação Comercial Estratégica
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-400 text-slate-900 text-[10px] font-extrabold uppercase">
                            Média Base 12 Meses
                        </span>
                    </div>
                    <h1 class="text-xl lg:text-2xl font-black tracking-tight text-white mt-1">
                        Inteligência de Recompra &amp; Clientes Ativos Sem Compras
                    </h1>
                    <p class="text-xs text-rose-100 mt-0.5">
                        Mapeamento cirúrgico da carteira ativa sem faturamento há 30+ dias, com histórico de 12 meses, peso físico e faturamento mensalizado.
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="<?= $appBase ?>/portal/modules/dashboard-vendas.php" 
                   class="px-3.5 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold transition flex items-center gap-2 no-underline">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard Vendas</span>
                </a>
                <button type="button" @click="exportarCSV()"
                        class="px-4 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-white text-xs font-black shadow-md transition flex items-center gap-2">
                    <i class="fa-regular fa-file-excel text-sm"></i>
                    <span>Exportar (.CSV)</span>
                </button>
                <button type="button" @click="carregarDados()"
                        class="px-4 py-2.5 rounded-xl bg-white text-rose-800 hover:bg-rose-50 text-xs font-black shadow-md transition flex items-center gap-2">
                    <i class="fa-solid fa-rotate text-sm" :class="{ 'fa-spin': loading }"></i>
                    <span>Atualizar</span>
                </button>
            </div>
        </div>

        <!-- BARRA DE FILTROS HIERÁRQUICOS -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4">
            <div class="rc-filtros-desktop grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end"
                 :class="{ 'expandido-mobile': filtrosAbertosMobile }">
                
                <!-- 1. Filial -->
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-500 uppercase mb-1 flex items-center justify-between">
                        <span><i class="fa-solid fa-building text-slate-400 mr-1"></i>Filial</span>
                        <span x-show="filiaisPermitidas.length === 1" class="text-[9px] text-emerald-600 font-black lowercase">(fixo)</span>
                    </label>
                    <select x-model="filters.filial" @change="mudarFilial()"
                            :disabled="filiaisPermitidas.length === 1"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-rose-500 disabled:opacity-75 disabled:cursor-not-allowed">
                        <option value="">Todas as Filiais Permitidas</option>
                        <template x-for="filial in filiaisPermitidas" :key="filial.idfilial">
                            <option :value="filial.idfilial" x-text="filial.idfilial + ' - ' + filial.nome"></option>
                        </template>
                    </select>
                </div>

                <!-- 2. Gestor -->
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-500 uppercase mb-1 flex items-center justify-between">
                        <span><i class="fa-solid fa-user-tie text-slate-400 mr-1"></i>Gestor / Supervisor</span>
                        <span x-show="gestoresDisponiveis.length === 1" class="text-[9px] text-purple-600 font-black lowercase">(gestor fixo)</span>
                    </label>
                    <select x-model="filters.gestor" @change="mudarGestor()"
                            :disabled="gestoresDisponiveis.length === 1"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-rose-500 disabled:opacity-75 disabled:cursor-not-allowed">
                        <option value="">Todos os Gestores</option>
                        <template x-for="gestor in gestoresDisponiveis" :key="gestor.id">
                            <option :value="gestor.id" x-text="gestor.nome"></option>
                        </template>
                    </select>
                </div>

                <!-- 3. Representante -->
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-500 uppercase mb-1 flex items-center justify-between">
                        <span><i class="fa-solid fa-id-badge text-slate-400 mr-1"></i>Representante</span>
                        <span x-show="representantesDisponiveis.length === 1" class="text-[9px] text-indigo-600 font-black lowercase">(rep fixo)</span>
                    </label>
                    <select x-model="filters.representante" @change="mudarRepresentante()"
                            :disabled="representantesDisponiveis.length === 1"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-rose-500 disabled:opacity-75 disabled:cursor-not-allowed">
                        <option value="">Todos os Representantes</option>
                        <template x-for="rep in representantesDisponiveis" :key="rep.id">
                            <option :value="rep.id" x-text="rep.nome"></option>
                        </template>
                    </select>
                </div>

                <!-- 4. Região -->
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-500 uppercase mb-1">
                        <i class="fa-solid fa-map-location-dot text-slate-400 mr-1"></i>Região
                    </label>
                    <select x-model="filtroRegiao"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="">Todas as Regiões do Escopo</option>
                        <template x-for="reg in regioesDisponiveis()" :key="reg.sigla">
                            <option :value="reg.sigla" x-text="reg.nome"></option>
                        </template>
                    </select>
                </div>

                <!-- 5. Faixa de Inatividade -->
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-500 uppercase mb-1">
                        <i class="fa-solid fa-clock-rotate-left text-slate-400 mr-1"></i>Faixa Inatividade
                    </label>
                    <select x-model="filtroFaixa"
                            class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-700 focus:bg-white focus:ring-2 focus:ring-rose-500">
                        <option value="">Todas as Faixas (+30 dias)</option>
                        <option value="30_45">⚠️ 30 a 45 dias (Alerta)</option>
                        <option value="46_60">🔶 46 a 60 dias (Atenção)</option>
                        <option value="61_90">🚨 61 a 90 dias (Risco Elevado)</option>
                        <option value="90_mais">🛑 +90 dias (Inativos Críticos)</option>
                    </select>
                </div>

                <!-- 6. Buscar Cliente / Cidade -->
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-500 uppercase mb-1">
                        <i class="fa-solid fa-magnifying-glass text-slate-400 mr-1"></i>Buscar Cliente
                    </label>
                    <input type="text" x-model="buscaCliente" placeholder="Código, nome ou cidade..."
                           class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-700 focus:bg-white focus:ring-2 focus:ring-rose-500">
                </div>
            </div>
        </div>

        <!-- 4 CARDS SUPER EXECUTIVOS: FAIXAS DE INATIVIDADE (MÉDIA BASE 12 MESES) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Faixa 1: 30 a 45 dias -->
            <div @click="alternarFaixa('30_45')"
                 :class="filtroFaixa === '30_45' ? 'ring-2 ring-amber-500 bg-amber-50/90 shadow-md' : 'bg-white hover:bg-amber-50/40 hover:shadow-md hover:-translate-y-0.5'"
                 class="p-4 rounded-2xl border border-amber-200 cursor-pointer transition-all flex flex-col justify-between group shadow-sm">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-amber-800 uppercase flex items-center gap-1.5">
                            <span>⚠️ Alerta Inicial</span>
                            <span class="text-[10px] font-semibold text-amber-600">(30-45d)</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-xs font-black"
                              x-text="dadosFaixa('30_45').clientes + ' cli'"></span>
                    </div>
                    <p class="text-lg sm:text-xl font-black text-slate-800 mt-2" x-text="formatMoney(dadosFaixa('30_45').valor)"></p>
                    <p class="text-xs font-bold text-amber-700 mt-0.5 flex items-center gap-1">
                        <i class="fa-solid fa-weight-hanging"></i>
                        <span x-text="formatPeso(dadosFaixa('30_45').peso) + ' hist.'"></span>
                    </p>
                </div>

                <div class="mt-3 pt-2 border-t border-amber-200/70 space-y-1 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Média / Mês (12m):</span>
                        <strong class="text-amber-900 font-extrabold" x-text="formatMoney(dadosFaixa('30_45').mediaMensal12m)"></strong>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">Méd/cli/mês:</span>
                        <strong class="text-slate-700 font-bold" x-text="formatMoney(dadosFaixa('30_45').mediaPorClienteMes)"></strong>
                    </div>
                    <div class="pt-1 text-right">
                        <span :class="filtroFaixa === '30_45' ? 'text-amber-800 font-black' : 'text-slate-400 group-hover:text-amber-700 font-bold'"
                              class="text-[10px]" x-text="filtroFaixa === '30_45' ? 'Filtrado Ativo ✓' : 'Clique para filtrar'"></span>
                    </div>
                </div>
            </div>

            <!-- Faixa 2: 46 a 60 dias -->
            <div @click="alternarFaixa('46_60')"
                 :class="filtroFaixa === '46_60' ? 'ring-2 ring-orange-500 bg-orange-50/90 shadow-md' : 'bg-white hover:bg-orange-50/40 hover:shadow-md hover:-translate-y-0.5'"
                 class="p-4 rounded-2xl border border-orange-200 cursor-pointer transition-all flex flex-col justify-between group shadow-sm">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-orange-800 uppercase flex items-center gap-1.5">
                            <span>🔶 Atenção</span>
                            <span class="text-[10px] font-semibold text-orange-600">(46-60d)</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 text-xs font-black"
                              x-text="dadosFaixa('46_60').clientes + ' cli'"></span>
                    </div>
                    <p class="text-lg sm:text-xl font-black text-slate-800 mt-2" x-text="formatMoney(dadosFaixa('46_60').valor)"></p>
                    <p class="text-xs font-bold text-orange-700 mt-0.5 flex items-center gap-1">
                        <i class="fa-solid fa-weight-hanging"></i>
                        <span x-text="formatPeso(dadosFaixa('46_60').peso) + ' hist.'"></span>
                    </p>
                </div>

                <div class="mt-3 pt-2 border-t border-orange-200/70 space-y-1 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Média / Mês (12m):</span>
                        <strong class="text-orange-900 font-extrabold" x-text="formatMoney(dadosFaixa('46_60').mediaMensal12m)"></strong>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">Méd/cli/mês:</span>
                        <strong class="text-slate-700 font-bold" x-text="formatMoney(dadosFaixa('46_60').mediaPorClienteMes)"></strong>
                    </div>
                    <div class="pt-1 text-right">
                        <span :class="filtroFaixa === '46_60' ? 'text-orange-800 font-black' : 'text-slate-400 group-hover:text-orange-700 font-bold'"
                              class="text-[10px]" x-text="filtroFaixa === '46_60' ? 'Filtrado Ativo ✓' : 'Clique para filtrar'"></span>
                    </div>
                </div>
            </div>

            <!-- Faixa 3: 61 a 90 dias -->
            <div @click="alternarFaixa('61_90')"
                 :class="filtroFaixa === '61_90' ? 'ring-2 ring-rose-500 bg-rose-50/90 shadow-md' : 'bg-white hover:bg-rose-50/40 hover:shadow-md hover:-translate-y-0.5'"
                 class="p-4 rounded-2xl border border-rose-200 cursor-pointer transition-all flex flex-col justify-between group shadow-sm">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-rose-800 uppercase flex items-center gap-1.5">
                            <span>🚨 Risco Elevado</span>
                            <span class="text-[10px] font-semibold text-rose-600">(61-90d)</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 text-xs font-black"
                              x-text="dadosFaixa('61_90').clientes + ' cli'"></span>
                    </div>
                    <p class="text-lg sm:text-xl font-black text-slate-800 mt-2" x-text="formatMoney(dadosFaixa('61_90').valor)"></p>
                    <p class="text-xs font-bold text-rose-700 mt-0.5 flex items-center gap-1">
                        <i class="fa-solid fa-weight-hanging"></i>
                        <span x-text="formatPeso(dadosFaixa('61_90').peso) + ' hist.'"></span>
                    </p>
                </div>

                <div class="mt-3 pt-2 border-t border-rose-200/70 space-y-1 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Média / Mês (12m):</span>
                        <strong class="text-rose-900 font-extrabold" x-text="formatMoney(dadosFaixa('61_90').mediaMensal12m)"></strong>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">Méd/cli/mês:</span>
                        <strong class="text-slate-700 font-bold" x-text="formatMoney(dadosFaixa('61_90').mediaPorClienteMes)"></strong>
                    </div>
                    <div class="pt-1 text-right">
                        <span :class="filtroFaixa === '61_90' ? 'text-rose-800 font-black' : 'text-slate-400 group-hover:text-rose-700 font-bold'"
                              class="text-[10px]" x-text="filtroFaixa === '61_90' ? 'Filtrado Ativo ✓' : 'Clique para filtrar'"></span>
                    </div>
                </div>
            </div>

            <!-- Faixa 4: +90 dias -->
            <div @click="alternarFaixa('90_mais')"
                 :class="filtroFaixa === '90_mais' ? 'ring-2 ring-red-700 bg-red-50/90 shadow-md' : 'bg-white hover:bg-red-50/40 hover:shadow-md hover:-translate-y-0.5'"
                 class="p-4 rounded-2xl border border-red-200 cursor-pointer transition-all flex flex-col justify-between group shadow-sm">
                <div>
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-red-900 uppercase flex items-center gap-1.5">
                            <span>🛑 Inativos Críticos</span>
                            <span class="text-[10px] font-semibold text-red-700">(+90d)</span>
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full bg-red-100 text-red-900 text-xs font-black"
                              x-text="dadosFaixa('90_mais').clientes + ' cli'"></span>
                    </div>
                    <p class="text-lg sm:text-xl font-black text-slate-800 mt-2" x-text="formatMoney(dadosFaixa('90_mais').valor)"></p>
                    <p class="text-xs font-bold text-red-700 mt-0.5 flex items-center gap-1">
                        <i class="fa-solid fa-weight-hanging"></i>
                        <span x-text="formatPeso(dadosFaixa('90_mais').peso) + ' hist.'"></span>
                    </p>
                </div>

                <div class="mt-3 pt-2 border-t border-red-200/70 space-y-1 text-[11px]">
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500 font-semibold">Média / Mês (12m):</span>
                        <strong class="text-red-950 font-extrabold" x-text="formatMoney(dadosFaixa('90_mais').mediaMensal12m)"></strong>
                    </div>
                    <div class="flex items-center justify-between text-[10px]">
                        <span class="text-slate-400">Méd/cli/mês:</span>
                        <strong class="text-slate-700 font-bold" x-text="formatMoney(dadosFaixa('90_mais').mediaPorClienteMes)"></strong>
                    </div>
                    <div class="pt-1 text-right">
                        <span :class="filtroFaixa === '90_mais' ? 'text-red-900 font-black' : 'text-slate-400 group-hover:text-red-700 font-bold'"
                              class="text-[10px]" x-text="filtroFaixa === '90_mais' ? 'Filtrado Ativo ✓' : 'Clique para filtrar'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- BARRA DE STATUS DO FILTRO E RESUMO CONSOLIDADO -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="font-extrabold text-slate-700">Filtro Ativo:</span>
                <span class="px-2.5 py-1 rounded-lg bg-rose-100 text-rose-800 font-bold" x-text="rotuloFiltroFaixa()"></span>
                <span x-show="filtroRegiao" class="px-2.5 py-1 rounded-lg bg-indigo-100 text-indigo-800 font-bold" x-text="'Região: ' + filtroRegiao"></span>
                <span x-show="buscaCliente" class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 font-bold" x-text="'Busca: ' + buscaCliente"></span>
                <button x-show="filtroFaixa || filtroRegiao || buscaCliente" 
                        @click="limparFiltrosSecundarios()"
                        class="text-xs text-rose-600 hover:text-rose-800 font-extrabold underline ml-2">
                    Limpar Filtros
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-4 text-xs font-bold">
                <div class="text-slate-600">
                    <span>Total Clientes: </span>
                    <strong class="text-slate-900" x-text="clientesFiltrados().length"></strong>
                </div>
                <div class="text-slate-600">
                    <span>Faturamento Histórico: </span>
                    <strong class="text-emerald-700" x-text="formatMoney(resumoFiltrado().valor)"></strong>
                </div>
                <div class="text-slate-600">
                    <span>Média Mensal Acumulada: </span>
                    <strong class="text-rose-700" x-text="formatMoney(resumoFiltrado().valor / 12)"></strong>
                </div>
                <div class="text-slate-600">
                    <span>Peso Total: </span>
                    <strong class="text-amber-700" x-text="formatPesoTon(resumoFiltrado().peso)"></strong>
                </div>
            </div>
        </div>

        <!-- TABELA EXECUTIVA DE CLIENTES PARA RECOMPRA -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-slate-800 uppercase flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-rose-600"></i>
                    <span>Clientes Mapeados para Ação Imediata</span>
                    <span class="text-xs font-normal text-slate-400" x-text="'(' + clientesFiltrados().length + ' registros encontrados)'"></span>
                </h3>
                <span class="text-xs text-slate-400">Clique na linha do cliente para ver histórico detalhado</span>
            </div>

            <div class="overflow-x-auto max-h-[640px]">
                <table class="w-full text-sm min-w-[960px]">
                    <thead class="bg-slate-50 border-b border-slate-200 sticky top-0 z-10 text-[11px] font-extrabold text-slate-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left cursor-pointer hover:text-rose-700" @click="ordenarPor('nome')">
                                Cliente <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-left cursor-pointer hover:text-rose-700" @click="ordenarPor('representante')">
                                Representante <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-center">Região / Cidade</th>
                            <th class="px-3 py-3 text-center cursor-pointer hover:text-rose-700" @click="ordenarPor('ultima_compra')">
                                Última Compra <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-center cursor-pointer hover:text-rose-700" @click="ordenarPor('dias_sem_comprar')">
                                Inatividade <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-right cursor-pointer hover:text-rose-700" @click="ordenarPor('valor_historico')">
                                Faturamento 12m <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-right cursor-pointer hover:text-rose-700" @click="ordenarPor('media_mensal')">
                                Média / Mês (12m) <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-right cursor-pointer hover:text-rose-700" @click="ordenarPor('peso_historico')">
                                Peso Hist. (Kg) <i class="fa-solid fa-sort ml-1 opacity-50"></i>
                            </th>
                            <th class="px-3 py-3 text-right">NFs Hist.</th>
                            <th class="px-4 py-3 text-center">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="c in clientesPaginados()" :key="c.id + '-' + (c.idrepresentante || 0)">
                            <tr class="hover:bg-rose-50/50 transition-colors cursor-pointer group" @click="verDetalhesCliente(c.id, c.nome)">
                                <td class="px-4 py-3">
                                    <p class="font-extrabold text-slate-800 group-hover:text-rose-700 transition" x-text="c.nome"></p>
                                    <span class="text-[11px] text-slate-400 font-mono" x-text="'Cód. #' + c.id"></span>
                                </td>
                                <td class="px-3 py-3 text-xs text-slate-600 font-semibold whitespace-nowrap" x-text="c.representante"></td>
                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 text-[10px] font-extrabold mr-1" x-text="c.regiao || 'SUL'"></span>
                                    <span class="text-xs text-slate-500" x-text="c.cidade_uf || '—'"></span>
                                </td>
                                <td class="px-3 py-3 text-center text-xs font-bold text-slate-700 whitespace-nowrap" x-text="c.ultima_fmt || '—'"></td>
                                <td class="px-3 py-3 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-black"
                                          :class="badgeInatividade(c.dias_sem_comprar)"
                                          x-text="c.dias_sem_comprar + ' dias'"></span>
                                </td>
                                <td class="px-3 py-3 text-right font-black text-emerald-700 whitespace-nowrap" x-text="formatMoney(c.valor_historico)"></td>
                                <td class="px-3 py-3 text-right font-black text-rose-700 whitespace-nowrap" x-text="formatMoney((c.valor_historico || 0) / 12)"></td>
                                <td class="px-3 py-3 text-right text-xs font-bold text-amber-700 whitespace-nowrap" x-text="formatPeso(c.peso_historico)"></td>
                                <td class="px-3 py-3 text-right text-xs font-bold text-blue-700 whitespace-nowrap" x-text="c.compras_anteriores || 0"></td>
                                <td class="px-4 py-3 text-center whitespace-nowrap" @click.stop>
                                    <button @click="verDetalhesCliente(c.id, c.nome)"
                                            class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-rose-100 text-slate-700 hover:text-rose-700 font-bold text-xs transition">
                                        <i class="fa-solid fa-eye mr-1"></i>Raio-X
                                    </button>
                                </td>
                            </tr>
                        </template>

                        <tr x-show="!loading && clientesFiltrados().length === 0">
                            <td colspan="10" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-users-slash text-3xl mb-2 block opacity-40"></i>
                                Nenhum cliente encontrado para os critérios selecionados.
                            </td>
                        </tr>
                        <tr x-show="loading">
                            <td colspan="10" class="text-center py-12 text-slate-400">
                                <i class="fa-solid fa-circle-notch fa-spin text-2xl text-rose-600 mb-2 block"></i>
                                Carregando clientes e calculando inteligência de recompra...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- CONTROLES DE PAGINAÇÃO -->
            <div class="p-4 border-t border-slate-100 flex flex-wrap items-center justify-between gap-3 text-xs text-slate-500" x-show="clientesFiltrados().length > 0">
                <div>
                    <span>Exibindo <strong x-text="((paginaAtual - 1) * itensPorPagina) + 1"></strong> até <strong x-text="Math.min(paginaAtual * itensPorPagina, clientesFiltrados().length)"></strong> de <strong x-text="clientesFiltrados().length"></strong> clientes</span>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="paginaAtual--" :disabled="paginaAtual === 1"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-bold disabled:opacity-40">
                        Anterior
                    </button>
                    <span class="px-2 font-bold text-slate-700" x-text="'Página ' + paginaAtual + ' de ' + totalPaginas()"></span>
                    <button @click="paginaAtual++" :disabled="paginaAtual >= totalPaginas()"
                            class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white font-bold disabled:opacity-40">
                        Próxima
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DE RAIO-X DO CLIENTE -->
    <div x-show="modalClienteOpen" x-cloak @click.self="modalClienteOpen = false" 
         class="fixed inset-0 z-[70] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm">
        <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
            <div class="bg-gradient-to-r from-rose-700 to-orange-600 px-6 py-4 flex items-center justify-between text-white">
                <div>
                    <span class="text-[10px] uppercase font-extrabold tracking-wider bg-white/20 px-2 py-0.5 rounded-full">Raio-X de Recompra</span>
                    <h3 class="text-base sm:text-lg font-black mt-1" x-text="clienteSelecionadoNome"></h3>
                </div>
                <button @click="modalClienteOpen = false" class="text-white/80 hover:text-white text-xl"><i class="fa-solid fa-times"></i></button>
            </div>

            <div class="p-6 overflow-y-auto space-y-4">
                <div x-show="loadingCliente" class="text-center py-10 text-slate-400">
                    <i class="fa-solid fa-circle-notch fa-spin text-3xl text-rose-600 mb-2 block"></i>
                    Carregando histórico do cliente...
                </div>

                <div x-show="!loadingCliente && clienteDetalhes" class="space-y-4">
                    <!-- Cards de resumo do cliente -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-center">
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Última Compra</span>
                            <strong class="text-sm text-slate-800" x-text="formatarDataBR(clienteDetalhes.ultima_compra) || '—'"></strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Dias sem Comprar</span>
                            <strong class="text-sm text-rose-700" x-text="(clienteDetalhes.dias_sem_comprar || 0) + ' dias'"></strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Faturamento 12m</span>
                            <strong class="text-sm text-emerald-700" x-text="formatMoney(clienteDetalhes.valor_historico || 0)"></strong>
                        </div>
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] font-bold text-slate-500 uppercase block">Média Mensal (12m)</span>
                            <strong class="text-sm text-rose-700" x-text="formatMoney((clienteDetalhes.valor_historico || 0) / 12)"></strong>
                        </div>
                    </div>

                    <!-- Informações cadastrais -->
                    <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 text-xs grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Representante</span>
                            <strong class="text-slate-800" x-text="clienteDetalhes.representante || '—'"></strong>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Região</span>
                            <strong class="text-slate-800" x-text="clienteDetalhes.regiao || '—'"></strong>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-bold uppercase text-[10px]">Cidade / UF</span>
                            <strong class="text-slate-800" x-text="clienteDetalhes.cidade_uf || '—'"></strong>
                        </div>
                    </div>

                    <!-- Histórico de NFs -->
                    <div>
                        <h4 class="text-xs font-extrabold uppercase text-slate-600 mb-2">Últimas Notas Fiscais Faturadas</h4>
                        <div class="overflow-x-auto border border-slate-200 rounded-xl">
                            <table class="w-full text-xs">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 uppercase font-bold">
                                    <tr>
                                        <th class="px-3 py-2 text-left">NF</th>
                                        <th class="px-3 py-2 text-center">Emissão</th>
                                        <th class="px-3 py-2 text-right">Peso (Kg)</th>
                                        <th class="px-3 py-2 text-right">Valor Líquido</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    <template x-for="nf in (clienteDetalhes.notas || [])" :key="nf.numero">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-3 py-2 font-bold text-slate-800" x-text="'NF ' + nf.numero"></td>
                                            <td class="px-3 py-2 text-center text-slate-600" x-text="formatarDataBR(nf.data)"></td>
                                            <td class="px-3 py-2 text-right text-amber-700 font-bold" x-text="formatPeso(nf.peso)"></td>
                                            <td class="px-3 py-2 text-right font-black text-emerald-700" x-text="formatMoney(nf.valor)"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="!clienteDetalhes.notas || clienteDetalhes.notas.length === 0">
                                        <td colspan="4" class="text-center py-4 text-slate-400">Nenhuma nota detalhada retornada.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="p-4 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button @click="modalClienteOpen = false" class="px-4 py-2 rounded-xl bg-slate-200 hover:bg-slate-300 font-bold text-xs text-slate-700">Fechar</button>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
