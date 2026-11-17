<?php
$pageTitle = 'Contábil & Fiscal — Conferência Domínio | Nutricional';
$moduleJs = 'contabil.js';
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$version = @filemtime(__DIR__ . '/../assets/js/contabil.js') ?: 1;
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
@media print {
    body.imprimindo-danfe * { visibility: hidden; }
    body.imprimindo-danfe #modalDanfePrintArea,
    body.imprimindo-danfe #modalDanfePrintArea * { visibility: visible; }
    body.imprimindo-danfe #modalDanfePrintArea {
        position: absolute; left: 0; top: 0; width: 100%; background: #fff; padding: 0; margin: 0;
    }
    .no-print { display: none !important; }
}
</style>
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="min-h-screen bg-slate-50 pb-14" x-data="contabilHandler()" x-init="init()">

    <!-- HEADER MOBILE -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-indigo-950 text-white shadow-lg no-print">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="<?= $appBase ?>/portal/" class="flex items-center gap-1.5 text-white no-underline text-xs font-extrabold">
                <i class="fa-solid fa-arrow-left"></i> VOLTAR
            </a>
            <span class="text-xs font-black tracking-wide">CONTÁBIL &amp; FISCAL</span>
            <div class="flex items-center gap-2">
                <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=contabil" class="px-2 py-1 rounded-lg bg-amber-400 text-slate-900 text-[10px] font-extrabold no-underline" title="Treinamento do Módulo">
                    <i class="fa-solid fa-graduation-cap"></i>
                </a>
                <button @click="carregarTudo()" class="px-2.5 py-1 rounded-lg bg-white/15 text-xs font-bold">
                    <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
                </button>
            </div>
        </div>
    </div>
    <div class="lg:hidden h-12 no-print"></div>

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-5">

        <!-- TOP BAR DESKTOP -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-indigo-900 rounded-2xl p-5 sm:p-6 text-white shadow-xl border border-indigo-800/40 no-print">
            <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="<?= $appBase ?>/portal/"
                           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-extrabold transition no-underline border border-white/20">
                            <i class="fa-solid fa-arrow-left"></i>
                            <span>Voltar ao Portal</span>
                        </a>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                            Conferência Domínio Sistemas
                        </span>
                        <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-200 border border-emerald-400/30"
                              x-text="filiaisDisponiveis.length + ' filial(is) na hierarquia'"></span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                        Apuração Contábil &amp; Rastreio Fiscal (ICMS · PIS · COFINS)
                    </h1>
                    <p class="text-xs sm:text-sm text-indigo-200/80">
                        Projeção de impostos a pagar (Saídas − Entradas), filtros por período, transação, grupo e marca, além de DANFE Espelho e origem da nota.
                    </p>
                </div>

                <!-- AÇÕES PRINCIPAIS -->
                <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                    <button @click="filtrosAvancadosAbertos = !filtrosAvancadosAbertos"
                            class="px-3.5 py-2 rounded-xl bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-extrabold transition flex items-center gap-2">
                        <i class="fa-solid fa-sliders"></i>
                        <span>Filtros Avançados</span>
                        <span x-show="temFiltroAvancado()" class="w-2 h-2 rounded-full bg-amber-400"></span>
                    </button>

                    <button @click="carregarTudo()" :disabled="loading"
                            class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white text-xs font-extrabold shadow-lg transition flex items-center gap-2">
                        <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
                        <span>Atualizar</span>
                    </button>

                    <a href="<?= $appBase ?>/portal/modules/treinamentos.php?modulo=contabil"
                       class="px-3.5 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-900 text-xs font-extrabold shadow-lg transition flex items-center gap-1.5 no-underline">
                        <i class="fa-solid fa-graduation-cap"></i>
                        <span>Treinamento</span>
                    </a>
                </div>
            </div>

            <!-- BARRA DE FILTROS PRIMÁRIOS -->
            <div class="mt-4 pt-4 border-t border-white/15 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 items-end">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Tipo de Período</label>
                    <select x-model="filtros.modoPeriodo" @change="onModoPeriodoChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="mensal">Competência (Mês)</option>
                        <option value="personalizado">Período Personalizado</option>
                    </select>
                </div>

                <div x-show="filtros.modoPeriodo === 'mensal'">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Competência</label>
                    <input type="month" x-model="filtros.mes" @change="onFiltroChange()"
                           class="w-full px-3 py-2 rounded-xl bg-white/10 border border-white/20 text-white text-xs font-bold">
                </div>

                <div x-show="filtros.modoPeriodo === 'personalizado'" x-cloak>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Data Início</label>
                    <input type="date" x-model="filtros.dataInicio" @change="onFiltroChange()"
                           class="w-full px-3 py-2 rounded-xl bg-white/10 border border-white/20 text-white text-xs font-bold">
                </div>

                <div x-show="filtros.modoPeriodo === 'personalizado'" x-cloak>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Data Fim</label>
                    <input type="date" x-model="filtros.dataFim" @change="onFiltroChange()"
                           class="w-full px-3 py-2 rounded-xl bg-white/10 border border-white/20 text-white text-xs font-bold">
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Filial (Hierarquia)</label>
                    <select x-model="filtros.filial" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="0" x-show="filiaisDisponiveis.length > 1">Todas Permitidas</option>
                        <template x-for="f in filiaisDisponiveis" :key="f.idfilial">
                            <option :value="f.idfilial" x-text="f.idfilial + ' - ' + f.nome + ' (' + f.uf + ')'"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Crédito Entrada</label>
                    <select x-model="filtros.modoCredito" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="creditado">Creditado (Domínio)</option>
                        <option value="destacado">Destacado NF</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Tipo de Nota</label>
                    <select x-model="filtros.tipoNota" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="TODAS">Todas (Saídas + Entradas)</option>
                        <option value="SAIDA">Somente Saídas</option>
                        <option value="ENTRADA">Somente Entradas</option>
                    </select>
                </div>

                <div class="sm:col-span-2 lg:col-span-6">
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Natureza da Operação (CFOP)</label>
                    <select x-model="filtros.cfop" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="0">Todas as Naturezas de Operação (CFOP)</option>
                        <template x-for="c in opcoesFiltros.cfops" :key="'cf-'+c.id">
                            <option :value="c.id" x-text="c.id + ' - ' + c.descricao"></option>
                        </template>
                    </select>
                </div>
            </div>

            <!-- FILTROS AVANÇADOS (TRANSAÇÃO, GRUPO, MARCA, SUBTIPO, ITEM) -->
            <div x-show="filtrosAvancadosAbertos" x-collapse x-cloak
                 class="mt-3 pt-3 border-t border-white/10 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 items-end">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Natureza / Tipo</label>
                    <select x-model="filtros.subtipoNota" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="">Todas as Naturezas</option>
                        <option value="VENDA_COMPRA">Vendas &amp; Compras</option>
                        <option value="DEVOLUCAO">Devoluções</option>
                        <option value="TRANSFERENCIA">Transferências</option>
                        <option value="BONIFICACAO">Bonificações / Amostras</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Transação Saída</label>
                    <select x-model="filtros.transacaoSaida" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="0">Todas Saídas</option>
                        <template x-for="t in opcoesFiltros.transacoes_saida" :key="'ts-'+t.id">
                            <option :value="t.id" x-text="t.id + ' - ' + t.descricao"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Transação Entrada</label>
                    <select x-model="filtros.transacaoEntrada" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="0">Todas Entradas</option>
                        <template x-for="t in opcoesFiltros.transacoes_entrada" :key="'te-'+t.id">
                            <option :value="t.id" x-text="t.id + ' - ' + t.descricao"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Grupo de Item</label>
                    <select x-model="filtros.idgrupo" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="0">Todos os Grupos</option>
                        <template x-for="g in opcoesFiltros.grupos" :key="'g-'+g.id">
                            <option :value="g.id" x-text="g.descricao"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Marca</label>
                    <select x-model="filtros.idmarca" @change="onFiltroChange()"
                            class="w-full px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold">
                        <option value="0">Todas as Marcas</option>
                        <template x-for="m in opcoesFiltros.marcas" :key="'m-'+m.id">
                            <option :value="m.id" x-text="m.descricao"></option>
                        </template>
                    </select>
                </div>

                <div class="flex gap-1.5 items-end">
                    <div class="flex-1">
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Item / Ref / NCM</label>
                        <input type="text" x-model="filtros.itemBusca" @keyup.enter="onFiltroChange()" placeholder="Código, ref, NCM..."
                               class="w-full px-3 py-2 rounded-xl bg-white/10 border border-white/20 text-white text-xs placeholder-indigo-200/50">
                    </div>
                    <button @click="limparFiltrosAvancados()" title="Limpar filtros avançados"
                            class="px-3 py-2 rounded-xl bg-rose-500/30 hover:bg-rose-500/50 text-white text-xs font-bold">
                        <i class="fa-solid fa-eraser"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ALERTA DE ERRO -->
        <div x-show="erro" x-cloak class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-2 no-print">
            <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
            <span x-text="erro"></span>
        </div>

        <!-- CARDS DE PROJEÇÃO COM TOOLTIP DETALHADO AO PASSAR O MOUSE -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 no-print" x-show="resumo">
            <!-- ICMS -->
            <div @mouseenter="cardHover = 'icms'" @mouseleave="cardHover = null"
                 class="relative bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:border-indigo-400 hover:shadow-md transition cursor-help">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        ICMS <i class="fa-solid fa-circle-info text-indigo-500 text-[11px]"></i>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                          :class="projecao.icms.saldo >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                          x-text="projecao.icms.situacao"></span>
                </div>
                <div class="text-2xl font-extrabold font-mono text-slate-900" x-text="fmtMoeda(Math.abs(projecao.icms.saldo))"></div>
                <div class="mt-3 pt-3 border-t border-slate-100 space-y-1 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Débito Saídas:</span>
                        <span class="font-bold text-rose-600 font-mono" x-text="fmtMoeda(projecao.icms.debito)"></span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Crédito Entradas:</span>
                        <span class="font-bold text-emerald-600 font-mono" x-text="fmtMoeda(projecao.icms.credito)"></span>
                    </div>
                </div>
                <div x-show="cardHover === 'icms'" x-transition.opacity x-cloak
                     class="absolute left-2 right-2 top-full mt-2 z-50 bg-slate-900 text-white text-[11px] rounded-xl p-3.5 shadow-2xl border border-indigo-400/50 pointer-events-none">
                    <div class="font-extrabold text-indigo-300 mb-1">Memória ICMS (Domínio Sistemas)</div>
                    <p class="text-slate-300 mb-2">Fórmula: <code>Débito Saídas − Crédito Entradas</code></p>
                    <div class="space-y-1 text-[10px]">
                        <div class="flex justify-between"><span>Base ICMS Saídas:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.icms.base_saida)"></span></div>
                        <div class="flex justify-between"><span>Base ICMS Entradas:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.icms.base_entrada)"></span></div>
                        <div class="flex justify-between"><span>Alíquota Efetiva Saída:</span><span class="font-mono font-bold" x-text="calcAliq(projecao.icms.debito, projecao.icms.base_saida)"></span></div>
                        <div class="flex justify-between text-amber-300"><span>Destacado na Entrada:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.icms.credito_destacado)"></span></div>
                    </div>
                </div>
            </div>

            <!-- PIS -->
            <div @mouseenter="cardHover = 'pis'" @mouseleave="cardHover = null"
                 class="relative bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:border-indigo-400 hover:shadow-md transition cursor-help">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        PIS <i class="fa-solid fa-circle-info text-indigo-500 text-[11px]"></i>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                          :class="projecao.pis.saldo >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                          x-text="projecao.pis.situacao"></span>
                </div>
                <div class="text-2xl font-extrabold font-mono text-slate-900" x-text="fmtMoeda(Math.abs(projecao.pis.saldo))"></div>
                <div class="mt-3 pt-3 border-t border-slate-100 space-y-1 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Débito Saídas:</span>
                        <span class="font-bold text-rose-600 font-mono" x-text="fmtMoeda(projecao.pis.debito)"></span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Crédito Entradas:</span>
                        <span class="font-bold text-emerald-600 font-mono" x-text="fmtMoeda(projecao.pis.credito)"></span>
                    </div>
                </div>
                <div x-show="cardHover === 'pis'" x-transition.opacity x-cloak
                     class="absolute left-2 right-2 top-full mt-2 z-50 bg-slate-900 text-white text-[11px] rounded-xl p-3.5 shadow-2xl border border-indigo-400/50 pointer-events-none">
                    <div class="font-extrabold text-indigo-300 mb-1">Memória PIS Não-Cumulativo</div>
                    <p class="text-slate-300 mb-2">Origem: <code>nfs_item.valor_pis</code> − <code>nfe_item.valorpiscreditado</code></p>
                    <div class="space-y-1 text-[10px]">
                        <div class="flex justify-between"><span>Base PIS Saídas:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.pis.base_saida)"></span></div>
                        <div class="flex justify-between"><span>Base PIS Entradas:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.pis.base_entrada)"></span></div>
                        <div class="flex justify-between"><span>Alíquota Efetiva Saída:</span><span class="font-mono font-bold" x-text="calcAliq(projecao.pis.debito, projecao.pis.base_saida)"></span></div>
                    </div>
                </div>
            </div>

            <!-- COFINS -->
            <div @mouseenter="cardHover = 'cofins'" @mouseleave="cardHover = null"
                 class="relative bg-white rounded-2xl p-5 border border-slate-200 shadow-sm hover:border-indigo-400 hover:shadow-md transition cursor-help">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                        COFINS <i class="fa-solid fa-circle-info text-indigo-500 text-[11px]"></i>
                    </span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                          :class="projecao.cofins.saldo >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                          x-text="projecao.cofins.situacao"></span>
                </div>
                <div class="text-2xl font-extrabold font-mono text-slate-900" x-text="fmtMoeda(Math.abs(projecao.cofins.saldo))"></div>
                <div class="mt-3 pt-3 border-t border-slate-100 space-y-1 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Débito Saídas:</span>
                        <span class="font-bold text-rose-600 font-mono" x-text="fmtMoeda(projecao.cofins.debito)"></span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Crédito Entradas:</span>
                        <span class="font-bold text-emerald-600 font-mono" x-text="fmtMoeda(projecao.cofins.credito)"></span>
                    </div>
                </div>
                <div x-show="cardHover === 'cofins'" x-transition.opacity x-cloak
                     class="absolute left-2 right-2 top-full mt-2 z-50 bg-slate-900 text-white text-[11px] rounded-xl p-3.5 shadow-2xl border border-indigo-400/50 pointer-events-none">
                    <div class="font-extrabold text-indigo-300 mb-1">Memória COFINS Não-Cumulativa</div>
                    <p class="text-slate-300 mb-2">Origem: <code>nfs_item.valor_cofins</code> − <code>nfe_item.valorcofinscreditado</code></p>
                    <div class="space-y-1 text-[10px]">
                        <div class="flex justify-between"><span>Base COFINS Saídas:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.cofins.base_saida)"></span></div>
                        <div class="flex justify-between"><span>Base COFINS Entradas:</span><span class="font-mono font-bold" x-text="fmtMoeda(projecao.cofins.base_entrada)"></span></div>
                        <div class="flex justify-between"><span>Alíquota Efetiva Saída:</span><span class="font-mono font-bold" x-text="calcAliq(projecao.cofins.debito, projecao.cofins.base_saida)"></span></div>
                    </div>
                </div>
            </div>

            <!-- TOTAL PROJETADO A PAGAR -->
            <div @mouseenter="cardHover = 'total'" @mouseleave="cardHover = null"
                 class="relative bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-2xl p-5 shadow-md hover:shadow-xl transition cursor-help">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-200 flex items-center gap-1.5">
                        Projeção a Pagar <i class="fa-solid fa-circle-info text-indigo-300 text-[11px]"></i>
                    </span>
                    <i class="fa-solid fa-scale-balanced text-indigo-300"></i>
                </div>
                <div class="text-2xl font-extrabold font-mono" x-text="fmtMoeda(projecao.total_a_pagar)"></div>
                <div class="mt-3 pt-3 border-t border-white/15 space-y-1 text-xs text-indigo-100">
                    <div class="flex justify-between">
                        <span>Saídas (<span x-text="totaisSaida.qtd_notas"></span> NFs):</span>
                        <span class="font-bold font-mono" x-text="fmtMoeda(totaisSaida.valor_contabil)"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Entradas (<span x-text="totaisEntrada.qtd_notas"></span> NFs):</span>
                        <span class="font-bold font-mono" x-text="fmtMoeda(totaisEntrada.valor_contabil)"></span>
                    </div>
                </div>
                <div x-show="cardHover === 'total'" x-transition.opacity x-cloak
                     class="absolute left-2 right-2 top-full mt-2 z-50 bg-slate-900 text-white text-[11px] rounded-xl p-3.5 shadow-2xl border border-indigo-400/50 pointer-events-none">
                    <div class="font-extrabold text-amber-300 mb-1">Resumo Executivo do Período</div>
                    <div class="space-y-1 text-[10px]">
                        <div class="flex justify-between"><span>Período ativo:</span><span class="font-bold" x-text="competenciaLabel"></span></div>
                        <div class="flex justify-between"><span>IPI Saídas / Entradas:</span><span class="font-mono" x-text="fmtMoeda(projecao.ipi.debito) + ' / ' + fmtMoeda(projecao.ipi.destacado_entrada)"></span></div>
                        <div class="flex justify-between"><span>ICMS ST (Saída / Entrada):</span><span class="font-mono" x-text="fmtMoeda(totaisSaida.valor_st) + ' / ' + fmtMoeda(totaisEntrada.valor_st)"></span></div>
                        <div class="flex justify-between text-rose-300"><span>Canceladas excluídas:</span><span class="font-bold" x-text="auditoria.saidas_canceladas_qtd + ' NFs (' + fmtMoeda(auditoria.saidas_canceladas_valor) + ')'"></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALERTA AUDITORIA PRÉ-FECHAMENTO -->
        <div x-show="auditoria.entradas_credito_parcial_notas > 0" x-cloak
             class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-amber-900 no-print">
            <div class="flex items-start sm:items-center gap-2.5">
                <i class="fa-solid fa-magnifying-glass-chart text-amber-600 text-base mt-0.5 sm:mt-0"></i>
                <div>
                    <strong>Auditoria Pré-Fechamento:</strong>
                    Existem <strong x-text="auditoria.entradas_credito_parcial_notas"></strong> nota(s) de entrada onde o imposto destacado difere do creditado
                    (ex.: devoluções 1202) — diferença de <strong class="font-mono" x-text="fmtMoeda(auditoria.entradas_icms_nao_creditado)"></strong> em ICMS.
                </div>
            </div>
            <button @click="abaAtiva = 'notas'; filtros.tipoNota = 'ENTRADA'; carregarNotas(1)"
                    class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold shrink-0">
                Conferir Entradas
            </button>
        </div>

        <!-- NAVEGAÇÃO POR ABAS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden no-print">
            <div class="flex flex-wrap border-b border-slate-200 bg-slate-50/70 px-3 pt-2 gap-1">
                <button @click="abaAtiva = 'cfop'"
                        :class="abaAtiva === 'cfop' ? 'bg-white text-indigo-700 border-slate-200 border-b-white shadow-sm' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                        class="px-4 py-2.5 text-xs font-extrabold rounded-t-xl border border-b-0 transition flex items-center gap-2">
                    <i class="fa-solid fa-layer-group"></i> Apuração por CFOP &amp; Evolução
                </button>
                <button @click="abaAtiva = 'notas'; if(!notasCarregadas) carregarNotas(1)"
                        :class="abaAtiva === 'notas' ? 'bg-white text-indigo-700 border-slate-200 border-b-white shadow-sm' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                        class="px-4 py-2.5 text-xs font-extrabold rounded-t-xl border border-b-0 transition flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice"></i> Notas Fiscais (Raio-X &amp; DANFE)
                </button>
                <button @click="abaAtiva = 'itens'; if(!itensCarregados) carregarItens(1)"
                        :class="abaAtiva === 'itens' ? 'bg-white text-indigo-700 border-slate-200 border-b-white shadow-sm' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                        class="px-4 py-2.5 text-xs font-extrabold rounded-t-xl border border-b-0 transition flex items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked"></i> Relatório Completo por Itens
                </button>
                <button @click="abaAtiva = 'memoria'"
                        :class="abaAtiva === 'memoria' ? 'bg-white text-indigo-700 border-slate-200 border-b-white shadow-sm' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                        class="px-4 py-2.5 text-xs font-extrabold rounded-t-xl border border-b-0 transition flex items-center gap-2">
                    <i class="fa-solid fa-calculator"></i> Memória Domínio Sistemas
                </button>
            </div>

            <!-- ABA 1: APURAÇÃO POR CFOP & GRÁFICO -->
            <div x-show="abaAtiva === 'cfop'" class="p-5 space-y-6">
                <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-700">
                            Evolução 6 Meses — Débito Saídas × Crédito Entradas × Imposto Líquido
                        </h3>
                        <span class="text-[11px] text-slate-500">ICMS + PIS + COFINS</span>
                    </div>
                    <div class="h-60">
                        <canvas id="chartContabilHistorico"></canvas>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-rose-500"></span>
                            SAÍDAS POR CFOP (Débitos de Impostos)
                        </h3>
                        <span class="text-xs text-slate-500">Clique no CFOP para rastrear notas ou itens</span>
                    </div>
                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-xs">
                            <thead class="bg-slate-800 text-white">
                                <tr>
                                    <th class="px-3 py-2.5 text-left">CFOP</th>
                                    <th class="px-3 py-2.5 text-left">Natureza da Operação</th>
                                    <th class="px-3 py-2.5 text-right">NFs</th>
                                    <th class="px-3 py-2.5 text-right">Itens</th>
                                    <th class="px-3 py-2.5 text-right">Valor Contábil</th>
                                    <th class="px-3 py-2.5 text-right">Base ICMS</th>
                                    <th class="px-3 py-2.5 text-right">ICMS</th>
                                    <th class="px-3 py-2.5 text-right">PIS</th>
                                    <th class="px-3 py-2.5 text-right">COFINS</th>
                                    <th class="px-3 py-2.5 text-right">IPI / ST</th>
                                    <th class="px-3 py-2.5 text-center">Rastrear</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="c in cfopsSaida" :key="'s-' + c.idcfop">
                                    <tr class="hover:bg-indigo-50/40 transition">
                                        <td class="px-3 py-2 font-mono font-extrabold text-indigo-700" x-text="c.idcfop"></td>
                                        <td class="px-3 py-2 font-medium text-slate-700 max-w-xs truncate" :title="c.descricao" x-text="c.descricao"></td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="c.qtd_notas"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-500" x-text="c.qtd_itens"></td>
                                        <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(c.valor_contabil)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-600" x-text="fmtMoeda(c.base_icms)"></td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-rose-700" x-text="fmtMoeda(c.icms_apurado)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700" x-text="fmtMoeda(c.pis_apurado)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700" x-text="fmtMoeda(c.cofins_apurado)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-500" x-text="fmtMoeda(c.ipi_apurado + c.valor_st)"></td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            <button @click="rastrearCfop('SAIDA', c.idcfop, 'notas')" class="px-2 py-1 rounded bg-slate-100 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px] mr-1">Notas</button>
                                            <button @click="rastrearCfop('SAIDA', c.idcfop, 'itens')" class="px-2 py-1 rounded bg-slate-100 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px]">Itens</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="cfopsSaida.length === 0">
                                    <td colspan="11" class="px-4 py-6 text-center text-slate-400">Nenhuma saída registrada para os filtros atuais.</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-100 font-extrabold text-slate-800" x-show="cfopsSaida.length > 0">
                                <tr>
                                    <td colspan="4" class="px-3 py-2.5 text-right uppercase">Totais Saídas:</td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisSaida.valor_contabil)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisSaida.base_icms)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono text-rose-700" x-text="fmtMoeda(totaisSaida.icms_apurado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisSaida.pis_apurado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisSaida.cofins_apurado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisSaida.ipi_apurado + totaisSaida.valor_st)"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            ENTRADAS POR CFOP (Créditos — Modo: <span class="text-indigo-700" x-text="filtros.modoCredito === 'creditado' ? 'Creditado Domínio' : 'Destacado NF'"></span>)
                        </h3>
                    </div>
                    <div class="overflow-x-auto border border-slate-200 rounded-xl">
                        <table class="w-full text-xs">
                            <thead class="bg-emerald-900 text-white">
                                <tr>
                                    <th class="px-3 py-2.5 text-left">CFOP</th>
                                    <th class="px-3 py-2.5 text-left">Natureza da Operação</th>
                                    <th class="px-3 py-2.5 text-right">NFs</th>
                                    <th class="px-3 py-2.5 text-right">Itens</th>
                                    <th class="px-3 py-2.5 text-right">Valor Contábil</th>
                                    <th class="px-3 py-2.5 text-right">ICMS Destacado</th>
                                    <th class="px-3 py-2.5 text-right">ICMS Apurado</th>
                                    <th class="px-3 py-2.5 text-right">PIS Apurado</th>
                                    <th class="px-3 py-2.5 text-right">COFINS Apurado</th>
                                    <th class="px-3 py-2.5 text-right">IPI Dest.</th>
                                    <th class="px-3 py-2.5 text-center">Rastrear</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <template x-for="c in cfopsEntrada" :key="'e-' + c.idcfop">
                                    <tr class="hover:bg-emerald-50/40 transition">
                                        <td class="px-3 py-2 font-mono font-extrabold text-emerald-700" x-text="c.idcfop"></td>
                                        <td class="px-3 py-2 font-medium text-slate-700 max-w-xs truncate" :title="c.descricao" x-text="c.descricao"></td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="c.qtd_notas"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-500" x-text="c.qtd_itens"></td>
                                        <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(c.valor_contabil)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-500" x-text="fmtMoeda(c.icms_destacado)"></td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-emerald-700" x-text="fmtMoeda(c.icms_apurado)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700" x-text="fmtMoeda(c.pis_apurado)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-700" x-text="fmtMoeda(c.cofins_apurado)"></td>
                                        <td class="px-3 py-2 text-right font-mono text-slate-500" x-text="fmtMoeda(c.ipi_destacado)"></td>
                                        <td class="px-3 py-2 text-center whitespace-nowrap">
                                            <button @click="rastrearCfop('ENTRADA', c.idcfop, 'notas')" class="px-2 py-1 rounded bg-slate-100 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px] mr-1">Notas</button>
                                            <button @click="rastrearCfop('ENTRADA', c.idcfop, 'itens')" class="px-2 py-1 rounded bg-slate-100 hover:bg-emerald-100 text-emerald-700 font-bold text-[10px]">Itens</button>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="cfopsEntrada.length === 0">
                                    <td colspan="11" class="px-4 py-6 text-center text-slate-400">Nenhuma entrada registrada para os filtros atuais.</td>
                                </tr>
                            </tbody>
                            <tfoot class="bg-slate-100 font-extrabold text-slate-800" x-show="cfopsEntrada.length > 0">
                                <tr>
                                    <td colspan="4" class="px-3 py-2.5 text-right uppercase">Totais Entradas:</td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisEntrada.valor_contabil)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono text-slate-500" x-text="fmtMoeda(totaisEntrada.icms_destacado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono text-emerald-700" x-text="fmtMoeda(totaisEntrada.icms_apurado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisEntrada.pis_apurado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisEntrada.cofins_apurado)"></td>
                                    <td class="px-3 py-2.5 text-right font-mono" x-text="fmtMoeda(totaisEntrada.ipi_destacado)"></td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ABA 2: NOTAS FISCAIS (RAIO-X & DANFE) -->
            <div x-show="abaAtiva === 'notas'" x-cloak class="p-5 space-y-4">
                <div class="flex flex-wrap items-end gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">CFOP</label>
                        <input type="text" x-model="filtroNotas.cfop" @keyup.enter="carregarNotas(1)" placeholder="Ex: 5102"
                               class="w-24 px-3 py-2 rounded-lg border border-slate-300 text-xs font-mono bg-white">
                    </div>
                    <div class="flex-1 min-w-[220px]">
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Buscar NF / Parceiro / CNPJ</label>
                        <input type="text" x-model="filtroNotas.busca" @keyup.enter="carregarNotas(1)" placeholder="Número da NF, razão social ou CNPJ..."
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs bg-white">
                    </div>
                    <button @click="carregarNotas(1)" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i> Filtrar Notas
                    </button>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-800 text-white">
                            <tr>
                                <th class="px-3 py-2.5 text-left">Tipo / Transação</th>
                                <th class="px-3 py-2.5 text-left">NF / Data</th>
                                <th class="px-3 py-2.5 text-left">Filial</th>
                                <th class="px-3 py-2.5 text-left">Cliente / Fornecedor</th>
                                <th class="px-3 py-2.5 text-left">CFOPs</th>
                                <th class="px-3 py-2.5 text-right">Total NF</th>
                                <th class="px-3 py-2.5 text-right">Base ICMS</th>
                                <th class="px-3 py-2.5 text-right">ICMS</th>
                                <th class="px-3 py-2.5 text-right">PIS / COFINS</th>
                                <th class="px-3 py-2.5 text-center">Auditoria</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="n in notasLista" :key="n.tipo + '-' + n.idfilial + '-' + n.idnota">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                                              :class="n.tipo === 'SAIDA' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                              x-text="n.tipo"></span>
                                        <div class="text-[10px] text-slate-500 truncate max-w-[140px] mt-0.5" :title="n.transacao_desc" x-text="n.idtransacao + ' - ' + n.transacao_desc"></div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="font-mono font-extrabold text-slate-900" x-text="'NF ' + n.numeronf"></div>
                                        <div class="text-[11px] text-slate-500" x-text="fmtData(n.data_doc)"></div>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-600" x-text="'F' + n.idfilial"></td>
                                    <td class="px-3 py-2 max-w-[240px]">
                                        <div class="font-semibold text-slate-800 truncate" :title="n.parceiro" x-text="n.parceiro"></div>
                                        <div class="text-[10px] text-slate-400 font-mono" x-text="(n.doc_parceiro || '-') + ' • ' + n.uf"></div>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-[11px] text-indigo-700 font-bold" x-text="n.cfops"></td>
                                    <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(n.valortotal)"></td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-600" x-text="fmtMoeda(n.base_icms)"></td>
                                    <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(n.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? n.icms_cred : n.icms_dest)"></td>
                                    <td class="px-3 py-2 text-right font-mono text-[11px]"
                                        x-text="fmtMoeda((n.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? n.pis_cred + n.cofins_cred : n.pis_dest + n.cofins_dest))"></td>
                                    <td class="px-3 py-2 text-center whitespace-nowrap">
                                        <button @click="abrirDanfeEspelho(n.tipo, n.idfilial, n.idnota)"
                                                class="px-2.5 py-1 rounded-lg bg-slate-900 hover:bg-indigo-700 text-white font-bold text-[10px] mr-1" title="Ver DANFE Espelho e Origem">
                                            <i class="fa-solid fa-print mr-1"></i> DANFE / Origem
                                        </button>
                                        <button @click="abrirItensNota(n)" class="px-2 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px]">
                                            Itens
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="notasLista.length === 0">
                                <td colspan="10" class="px-4 py-8 text-center text-slate-400">Nenhuma nota encontrada para os filtros aplicados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-600">
                    <span>Total: <strong x-text="notasPag.total"></strong> notas (Página <span x-text="notasPag.page"></span> de <span x-text="notasPag.pages || 1"></span>)</span>
                    <div class="flex gap-1.5">
                        <button @click="carregarNotas(notasPag.page - 1)" :disabled="notasPag.page <= 1"
                                class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white disabled:opacity-40 font-bold">Anterior</button>
                        <button @click="carregarNotas(notasPag.page + 1)" :disabled="notasPag.page >= notasPag.pages"
                                class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white disabled:opacity-40 font-bold">Próxima</button>
                    </div>
                </div>
            </div>

            <!-- ABA 3: RELATÓRIO COMPLETO POR ITENS -->
            <div x-show="abaAtiva === 'itens'" x-cloak class="p-5 space-y-4">
                <div class="flex flex-wrap items-end gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">CFOP</label>
                        <input type="text" x-model="filtroItens.cfop" @keyup.enter="carregarItens(1)" placeholder="Ex: 5102"
                               class="w-24 px-3 py-2 rounded-lg border border-slate-300 text-xs font-mono bg-white">
                    </div>
                    <div class="flex-1 min-w-[240px]">
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Buscar Item / Ref / NCM / NF / Parceiro</label>
                        <input type="text" x-model="filtroItens.busca" @keyup.enter="carregarItens(1)" placeholder="ID item, referência, descrição, NCM, NF..."
                               class="w-full px-3 py-2 rounded-lg border border-slate-300 text-xs bg-white">
                    </div>
                    <button @click="carregarItens(1)" class="px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-extrabold">
                        <i class="fa-solid fa-magnifying-glass mr-1"></i> Filtrar Itens
                    </button>
                    <button @click="exportarItensCsv()" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold">
                        <i class="fa-regular fa-file-excel mr-1"></i> Exportar Excel (CSV)
                    </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-xs">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-[10px] font-bold uppercase text-slate-500">Valor Itens</div>
                        <div class="font-mono font-extrabold text-sm text-slate-900" x-text="fmtMoeda(itensTotais.valor_total)"></div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-[10px] font-bold uppercase text-slate-500">ICMS Destacado</div>
                        <div class="font-mono font-extrabold text-sm text-slate-800" x-text="fmtMoeda(itensTotais.icms_dest)"></div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-[10px] font-bold uppercase text-slate-500">ICMS Creditado/Apur.</div>
                        <div class="font-mono font-extrabold text-sm text-indigo-700" x-text="fmtMoeda(itensTotais.icms_cred)"></div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-[10px] font-bold uppercase text-slate-500">PIS Apurado</div>
                        <div class="font-mono font-extrabold text-sm text-slate-800" x-text="fmtMoeda(itensTotais.pis_cred)"></div>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-3">
                        <div class="text-[10px] font-bold uppercase text-slate-500">COFINS Apurado</div>
                        <div class="font-mono font-extrabold text-sm text-slate-800" x-text="fmtMoeda(itensTotais.cofins_cred)"></div>
                    </div>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-[11px]">
                        <thead class="bg-slate-800 text-white">
                            <tr>
                                <th class="px-2.5 py-2 text-left">Tipo / NF</th>
                                <th class="px-2.5 py-2 text-left">Data / Filial</th>
                                <th class="px-2.5 py-2 text-left">Item / Grupo / Marca / NCM</th>
                                <th class="px-2.5 py-2 text-left">CFOP / CST</th>
                                <th class="px-2.5 py-2 text-right">Qtd</th>
                                <th class="px-2.5 py-2 text-right">V. Total</th>
                                <th class="px-2.5 py-2 text-right">Base ICMS</th>
                                <th class="px-2.5 py-2 text-right">Alíq.</th>
                                <th class="px-2.5 py-2 text-right">ICMS</th>
                                <th class="px-2.5 py-2 text-right">PIS</th>
                                <th class="px-2.5 py-2 text-right">COFINS</th>
                                <th class="px-2.5 py-2 text-right">IPI / ST</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(it, idx) in itensLista" :key="idx">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-2.5 py-2 whitespace-nowrap">
                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold mr-1"
                                              :class="it.tipo === 'SAIDA' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                              x-text="it.tipo === 'SAIDA' ? 'S' : 'E'"></span>
                                        <button @click="abrirDanfeEspelho(it.tipo, it.idfilial, it.idnota)" class="font-mono font-bold text-indigo-700 hover:underline" x-text="it.numeronf"></button>
                                    </td>
                                    <td class="px-2.5 py-2 whitespace-nowrap">
                                        <div x-text="fmtData(it.data_doc)"></div>
                                        <div class="text-[10px] text-slate-400" x-text="'F' + it.idfilial + ' • ' + it.uf"></div>
                                    </td>
                                    <td class="px-2.5 py-2 max-w-[270px]">
                                        <div class="font-semibold text-slate-800 truncate" :title="it.item_desc" x-text="it.iditem + ' - ' + it.item_desc"></div>
                                        <div class="text-[10px] text-slate-400 font-mono truncate" x-text="(it.grupo_desc || '-') + ' • ' + (it.marca_desc || '-') + ' | NCM: ' + (it.ncm || '-')"></div>
                                    </td>
                                    <td class="px-2.5 py-2 whitespace-nowrap font-mono">
                                        <span class="font-extrabold text-indigo-700" x-text="it.idcfop"></span>
                                        <span class="text-slate-400 text-[10px]" x-text="'(' + (it.cst_icms || '-') + ')'"></span>
                                    </td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtNum(it.quantidade)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono font-bold" x-text="fmtMoeda(it.valor_total)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono text-slate-600" x-text="fmtMoeda(it.base_icms)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono text-slate-500" x-text="it.aliq_icms + '%'"></td>
                                    <td class="px-2.5 py-2 text-right font-mono font-bold" x-text="fmtMoeda(it.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? it.icms_cred : it.icms_dest)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtMoeda(it.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? it.pis_cred : it.pis_dest)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtMoeda(it.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? it.cofins_cred : it.cofins_dest)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono text-slate-500" x-text="fmtMoeda(it.ipi + it.st)"></td>
                                </tr>
                            </template>
                            <tr x-show="itensLista.length === 0">
                                <td colspan="12" class="px-4 py-8 text-center text-slate-400">Nenhum item encontrado para os filtros aplicados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-600">
                    <span>Total: <strong x-text="itensPag.total"></strong> registros (Página <span x-text="itensPag.page"></span> de <span x-text="itensPag.pages || 1"></span>)</span>
                    <div class="flex gap-1.5">
                        <button @click="carregarItens(itensPag.page - 1)" :disabled="itensPag.page <= 1"
                                class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white disabled:opacity-40 font-bold">Anterior</button>
                        <button @click="carregarItens(itensPag.page + 1)" :disabled="itensPag.page >= itensPag.pages"
                                class="px-3 py-1.5 rounded-lg border border-slate-300 bg-white disabled:opacity-40 font-bold">Próxima</button>
                    </div>
                </div>
            </div>

            <!-- ABA 4: MEMÓRIA DOMÍNIO SISTEMAS -->
            <div x-show="abaAtiva === 'memoria'" x-cloak class="p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Demonstrativo de Apuração Contábil — Espelho Domínio Sistemas</h3>
                        <p class="text-xs text-slate-500">Período <strong x-text="competenciaLabel"></strong> · Base para conferência mensal de guias.</p>
                    </div>
                    <button onclick="window.print()" class="px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-900 text-white text-xs font-bold">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir Conferência
                    </button>
                </div>

                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-900 text-white">
                            <tr>
                                <th class="px-4 py-3 text-left">Tributo Federal / Estadual</th>
                                <th class="px-4 py-3 text-right">(+) Débito Saídas</th>
                                <th class="px-4 py-3 text-right">(−) Crédito Entradas</th>
                                <th class="px-4 py-3 text-right">(=) Saldo Apurado</th>
                                <th class="px-4 py-3 text-center">Situação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <tr>
                                <td class="px-4 py-3 font-extrabold text-slate-800">ICMS (Apuração Própria)</td>
                                <td class="px-4 py-3 text-right font-mono text-rose-700 font-bold" x-text="fmtMoeda(projecao.icms.debito)"></td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-700 font-bold" x-text="fmtMoeda(projecao.icms.credito)"></td>
                                <td class="px-4 py-3 text-right font-mono font-extrabold text-slate-900" x-text="fmtMoeda(projecao.icms.saldo)"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold"
                                          :class="projecao.icms.saldo >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                          x-text="projecao.icms.situacao"></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-extrabold text-slate-800">PIS (Contribuição Não-Cumulativa)</td>
                                <td class="px-4 py-3 text-right font-mono text-rose-700 font-bold" x-text="fmtMoeda(projecao.pis.debito)"></td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-700 font-bold" x-text="fmtMoeda(projecao.pis.credito)"></td>
                                <td class="px-4 py-3 text-right font-mono font-extrabold text-slate-900" x-text="fmtMoeda(projecao.pis.saldo)"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold"
                                          :class="projecao.pis.saldo >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                          x-text="projecao.pis.situacao"></span>
                                </td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 font-extrabold text-slate-800">COFINS (Contribuição Não-Cumulativa)</td>
                                <td class="px-4 py-3 text-right font-mono text-rose-700 font-bold" x-text="fmtMoeda(projecao.cofins.debito)"></td>
                                <td class="px-4 py-3 text-right font-mono text-emerald-700 font-bold" x-text="fmtMoeda(projecao.cofins.credito)"></td>
                                <td class="px-4 py-3 text-right font-mono font-extrabold text-slate-900" x-text="fmtMoeda(projecao.cofins.saldo)"></td>
                                <td class="px-4 py-3 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold"
                                          :class="projecao.cofins.saldo >= 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                                          x-text="projecao.cofins.situacao"></span>
                                </td>
                            </tr>
                            <tr class="bg-slate-50">
                                <td class="px-4 py-3 font-bold text-slate-600">IPI (Informativo / Custo Entrada)</td>
                                <td class="px-4 py-3 text-right font-mono" x-text="fmtMoeda(projecao.ipi.debito)"></td>
                                <td class="px-4 py-3 text-right font-mono" x-text="fmtMoeda(projecao.ipi.destacado_entrada)"></td>
                                <td class="px-4 py-3 text-right font-mono text-slate-500" x-text="fmtMoeda(projecao.ipi.saldo)"></td>
                                <td class="px-4 py-3 text-center text-[10px] text-slate-500 font-bold">INFORMATIVO</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-indigo-950 text-white font-extrabold">
                            <tr>
                                <td colspan="3" class="px-4 py-3 text-right uppercase">Total Projetado de Guias a Pagar (ICMS + PIS + COFINS):</td>
                                <td class="px-4 py-3 text-right font-mono text-base" x-text="fmtMoeda(projecao.total_a_pagar)"></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL ITENS DA NOTA (z-[70]) -->
    <div x-show="modalNotaAberto" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm no-print">
        <div @click.away="modalNotaAberto = false" class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[90vh] flex flex-col overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-4 flex items-center justify-between">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-extrabold"
                              :class="notaSelecionada?.tipo === 'SAIDA' ? 'bg-rose-500 text-white' : 'bg-emerald-500 text-white'"
                              x-text="notaSelecionada?.tipo"></span>
                        <h3 class="text-sm sm:text-base font-extrabold" x-text="'NF ' + (notaSelecionada?.numeronf || '') + ' — ' + (notaSelecionada?.parceiro || '')"></h3>
                    </div>
                    <p class="text-xs text-slate-300 mt-0.5" x-text="'Filial ' + (notaSelecionada?.idfilial || '') + ' • Data: ' + fmtData(notaSelecionada?.data_doc) + ' • Total NF: ' + fmtMoeda(notaSelecionada?.valortotal || 0)"></p>
                </div>
                <button @click="modalNotaAberto = false" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto flex-1">
                <div x-show="loadingNotaItens" class="py-10 text-center text-slate-500 text-xs font-bold">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando itens e tributação...
                </div>
                <div x-show="!loadingNotaItens" class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-[11px]">
                        <thead class="bg-slate-100 text-slate-700 font-bold">
                            <tr>
                                <th class="px-2.5 py-2 text-left">Item / NCM</th>
                                <th class="px-2.5 py-2 text-left">CFOP</th>
                                <th class="px-2.5 py-2 text-left">CST ICMS/PIS/COF</th>
                                <th class="px-2.5 py-2 text-right">Qtd</th>
                                <th class="px-2.5 py-2 text-right">Total</th>
                                <th class="px-2.5 py-2 text-right">Base ICMS</th>
                                <th class="px-2.5 py-2 text-right">ICMS (Dest/Cred)</th>
                                <th class="px-2.5 py-2 text-right">PIS</th>
                                <th class="px-2.5 py-2 text-right">COFINS</th>
                                <th class="px-2.5 py-2 text-right">IPI / ST</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="it in notaItensLista" :key="it.seq">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-2.5 py-2 max-w-[260px]">
                                        <div class="font-semibold text-slate-800 truncate" x-text="it.iditem + ' - ' + it.descricao"></div>
                                        <div class="text-[10px] text-slate-400 font-mono" x-text="'Ref: ' + (it.referencia || '-') + ' | NCM: ' + (it.ncm || '-')"></div>
                                    </td>
                                    <td class="px-2.5 py-2 font-mono font-extrabold text-indigo-700" x-text="it.idcfop"></td>
                                    <td class="px-2.5 py-2 font-mono text-[10px] text-slate-500" x-text="(it.cst_icms || '-') + ' / ' + (it.cst_pis || '-') + ' / ' + (it.cst_cofins || '-')"></td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtNum(it.quantidade)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono font-bold" x-text="fmtMoeda(it.valor_total)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtMoeda(it.base_icms) + ' (' + it.aliq_icms + '%)'"></td>
                                    <td class="px-2.5 py-2 text-right font-mono font-bold">
                                        <span x-text="fmtMoeda(it.icms_dest)"></span>
                                        <span x-show="it.icms_dest !== it.icms_cred" class="block text-[10px] text-emerald-700" x-text="'Cred: ' + fmtMoeda(it.icms_cred)"></span>
                                    </td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtMoeda(it.pis_cred)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono" x-text="fmtMoeda(it.cofins_cred)"></td>
                                    <td class="px-2.5 py-2 text-right font-mono text-slate-500" x-text="fmtMoeda(it.ipi + it.st)"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DANFE ESPELHO & RASTREABILIDADE DE ORIGEM (z-[75]) -->
    <div x-show="modalDanfeAberto" x-cloak class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-900/75 p-3 sm:p-5 backdrop-blur-sm">
        <div class="bg-white rounded-2xl shadow-2xl max-w-6xl w-full max-h-[94vh] flex flex-col overflow-hidden">
            <div class="bg-slate-900 text-white px-5 py-3.5 flex items-center justify-between no-print">
                <div class="flex items-center gap-2.5">
                    <i class="fa-solid fa-file-invoice text-indigo-300"></i>
                    <h3 class="text-sm sm:text-base font-extrabold">DANFE Espelho &amp; Rastreio de Geração da Nota</h3>
                </div>
                <div class="flex items-center gap-2">
                    <button @click="imprimirDanfe()" class="px-3.5 py-1.5 rounded-lg bg-indigo-500 hover:bg-indigo-400 text-white text-xs font-extrabold">
                        <i class="fa-solid fa-print mr-1"></i> Imprimir Nota
                    </button>
                    <button @click="modalDanfeAberto = false" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 flex items-center justify-center">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div id="modalDanfePrintArea" class="p-5 overflow-y-auto flex-1 space-y-4 text-xs text-slate-800">
                <div x-show="loadingDanfe" class="py-12 text-center text-slate-500 font-bold">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Montando espelho da Nota Fiscal...
                </div>

                <template x-if="!loadingDanfe && danfeNota">
                    <div class="space-y-4">
                        <!-- BLOCO: COMO FOI GERADA (RASTREABILIDADE) -->
                        <div class="bg-indigo-50 border border-indigo-200 rounded-xl p-3.5">
                            <div class="text-[11px] font-extrabold uppercase tracking-wider text-indigo-900 mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-route"></i> Rastreabilidade — Como Esta Nota Foi Gerada
                            </div>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-[11px]">
                                <div>
                                    <span class="text-slate-500 block text-[10px]">Transação Utilizada</span>
                                    <strong x-text="danfeNota.idtransacao + ' - ' + (danfeNota.transacao_desc || '-')"></strong>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-[10px]">Usuário / Data-Hora Geração</span>
                                    <strong x-text="(danfeNota.usuario_geracao || '-') + ' em ' + (danfeNota.datahora_geracao || '-')"></strong>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-[10px]" x-text="danfeNota.tipo === 'SAIDA' ? 'Pedido(s) de Origem' : 'Ordem(ns) de Compra (OC)'"></span>
                                    <strong class="font-mono text-indigo-700" x-text="danfeNota.pedidos || 'Lançamento Direto / Sem vínculo'"></strong>
                                </div>
                                <div>
                                    <span class="text-slate-500 block text-[10px]">Protocolo SEFAZ</span>
                                    <strong class="font-mono" x-text="danfeNota.protocolo || '-'"></strong>
                                </div>
                            </div>
                            <div x-show="danfeNota.observacao_origem" class="mt-2 pt-2 border-t border-indigo-200/70 text-[11px] text-indigo-950">
                                <strong>Obs. Origem:</strong> <span x-text="danfeNota.observacao_origem"></span>
                            </div>
                        </div>

                        <!-- ============================================================ -->
                        <!-- LAYOUT 1: DANFE OFICIAL NUTRICIONAL (SAÍDAS)                 -->
                        <!-- ============================================================ -->
                        <div x-show="danfeNota.tipo === 'SAIDA'" class="bg-white text-black font-sans text-[10px] leading-tight space-y-2">
                            <!-- CANHOTO -->
                            <table class="w-full border-collapse border border-black">
                                <tr>
                                    <td colspan="2" class="border border-black p-1.5 text-[9px]">
                                        <span x-text="danfeNota.parc_razao"></span> Recebeu de <strong x-text="danfeNota.filial_razao"></strong> os produtos/serviços constantes na nota fiscal indicada ao lado
                                    </td>
                                    <td rowspan="2" class="border border-black p-1.5 text-center w-44 align-middle">
                                        <div class="font-bold text-[10px]">NF-e</div>
                                        <div class="font-bold text-xs" x-text="'Nº ' + fmtNfPad(danfeNota.numeronf)"></div>
                                        <div class="flex justify-between text-[9px] mt-1 px-1">
                                            <span x-text="'SÉRIE ' + (danfeNota.idserie || '1')"></span>
                                            <span x-text="danfeNota.pedidos || ''"></span>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-1.5 w-40 align-top text-[9px]">Data de recebimento<br>&nbsp;</td>
                                    <td class="border border-black p-1.5 align-top text-[9px]">Identificação e assinatura do recebedor<br>&nbsp;</td>
                                </tr>
                            </table>

                            <div class="border-t border-dashed border-slate-500 my-1"></div>

                            <!-- CABEÇALHO 3 COLUNAS -->
                            <table class="w-full border-collapse border border-black">
                                <tr>
                                    <!-- LOGO + EMITENTE -->
                                    <td class="border border-black p-2 w-[38%] align-middle">
                                        <div class="flex items-center gap-2.5">
                                            <img src="<?= $appBase ?>/portal/assets/img/logo.png" alt="Nutricional" class="h-12 w-auto object-contain">
                                            <div class="text-[9px] leading-snug">
                                                <div class="font-bold text-[10px]" x-text="danfeNota.filial_razao"></div>
                                                <div x-text="(danfeNota.filial_end || '') + ' - ' + (danfeNota.filial_bairro || '') + ' / ' + (danfeNota.filial_uf || '')"></div>
                                                <div class="mt-0.5"><i class="fa-solid fa-envelope mr-1"></i> nfe@nutricionalbr.com</div>
                                                <div><i class="fa-brands fa-instagram mr-1"></i> @nutricionalbr</div>
                                            </div>
                                        </div>
                                    </td>
                                    <!-- DANFE -->
                                    <td class="border border-black p-2 w-[18%] text-center align-middle">
                                        <div class="font-bold text-sm">DANFE</div>
                                        <div class="text-[8px]">Documento Auxiliar da Nota Fiscal Eletrônica</div>
                                        <div class="flex items-center justify-center gap-2 my-1 text-[9px]">
                                            <div class="text-left leading-tight">0 - Entrada<br>1 - Saída</div>
                                            <div class="border border-black px-2 py-0.5 font-bold text-xs">1</div>
                                        </div>
                                        <div class="font-bold text-[10px]" x-text="'Nº ' + fmtNfPad(danfeNota.numeronf)"></div>
                                        <div class="font-bold text-[9px]" x-text="'SÉRIE: ' + (danfeNota.idserie || '1')"></div>
                                    </td>
                                    <!-- BARCODE + CHAVE + PROTOCOLO -->
                                    <td class="border border-black p-0 w-[44%] align-top">
                                        <div class="p-1.5 border-b border-black text-center">
                                            <!-- Barras visuais estilizadas -->
                                            <div class="h-8 mx-auto w-11/12 opacity-85" style="background: repeating-linear-gradient(90deg, #000 0px, #000 2px, #fff 2px, #fff 4px, #000 4px, #000 5px, #fff 5px, #fff 8px);"></div>
                                        </div>
                                        <div class="p-1.5 border-b border-black">
                                            <div class="text-[8px] font-bold uppercase">CHAVE DE ACESSO</div>
                                            <div class="font-mono text-[9px] font-bold tracking-wider" x-text="danfeNota.chave_nfe || danfeNota.protocolo || 'Consulte chave no XML/SEFAZ'"></div>
                                        </div>
                                        <div class="p-1.5 border-b border-black text-center text-[8px]">
                                            Consulta de autenticidade no portal nacional da NF-e<br>
                                            <strong>www.nfe.fazenda.gov.br/portal</strong> ou no site da Sefaz autorizadora
                                        </div>
                                        <div class="p-1.5">
                                            <div class="text-[8px] font-bold uppercase">PROTOCOLO DE AUTORIZAÇÃO DE USO</div>
                                            <div class="font-mono text-[9px] font-bold" x-text="(danfeNota.protocolo || '-') + '  ' + (danfeNota.datahora_geracao || '')"></div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- NATUREZA + IE/CNPJ -->
                            <table class="w-full border-collapse border border-black -mt-[1px]">
                                <tr>
                                    <td colspan="2" class="border border-black p-1">
                                        <div class="text-[8px]">Natureza da Operação</div>
                                        <div class="font-bold text-[10px]" x-text="danfeNota.natureza_operacao || danfeNota.transacao_desc"></div>
                                    </td>
                                    <td class="border border-black p-1 w-24">
                                        <div class="text-[8px]">CFOP</div>
                                        <div class="font-bold font-mono text-[10px]" x-text="danfeNota.cfop_principal || '-'"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-1 w-1/3">
                                        <div class="text-[8px]">Inscrição estadual</div>
                                        <div class="font-mono" x-text="danfeNota.filial_ie || '-'"></div>
                                    </td>
                                    <td class="border border-black p-1 w-1/3">
                                        <div class="text-[8px]">Inscrição estadual subst. tributária</div>
                                        <div>&nbsp;</div>
                                    </td>
                                    <td class="border border-black p-1 w-1/3">
                                        <div class="text-[8px]">CNPJ</div>
                                        <div class="font-mono" x-text="danfeNota.filial_cnpj || '-'"></div>
                                    </td>
                                </tr>
                            </table>

                            <!-- DESTINATÁRIO / REMETENTE -->
                            <div class="font-bold text-[9px] pt-0.5">Destinatário / Remetente</div>
                            <table class="w-full border-collapse border border-black">
                                <tr>
                                    <td colspan="2" class="border border-black p-1"><div class="text-[8px]">Nome/Razão Social</div><div class="font-bold" x-text="danfeNota.parc_razao"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">CNPJ/CPF</div><div class="font-mono" x-text="danfeNota.parc_doc || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Inscrição Estadual</div><div class="font-mono" x-text="danfeNota.parc_ie || '-'"></div></td>
                                    <td class="border border-black p-1 w-24"><div class="text-[8px]">Data da Emissão</div><div x-text="fmtData(danfeNota.dataemissao)"></div></td>
                                </tr>
                                <tr>
                                    <td colspan="2" class="border border-black p-1"><div class="text-[8px]">Endereço</div><div x-text="danfeNota.parc_end || '-'"></div></td>
                                    <td colspan="2" class="border border-black p-1"><div class="text-[8px]">Bairro</div><div x-text="danfeNota.parc_bairro || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Data Saída</div><div x-text="fmtData(danfeNota.data_mov)"></div></td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-1"><div class="text-[8px]">Fone/Fax</div><div x-text="danfeNota.parc_fone || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Município</div><div x-text="danfeNota.parc_cidade || '-'"></div></td>
                                    <td class="border border-black p-1 w-12"><div class="text-[8px]">UF</div><div x-text="danfeNota.parc_uf || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">CEP</div><div x-text="danfeNota.parc_cep || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Hora Saída</div><div x-text="(danfeNota.datahora_geracao || '').slice(11,19)"></div></td>
                                </tr>
                            </table>

                            <!-- FATURA / DUPLICATAS -->
                            <div x-show="danfeDuplicatas.length > 0">
                                <div class="font-bold text-[9px] pt-0.5">Fatura</div>
                                <div class="border border-black flex flex-wrap">
                                    <template x-for="d in danfeDuplicatas" :key="d.documento + '-' + d.parcela">
                                        <div class="border-r border-black p-1.5 text-[8.5px] min-w-[110px]">
                                            <div class="font-bold font-mono" x-text="d.documento + '-' + d.parcela"></div>
                                            <div class="font-mono" x-text="fmtMoeda(d.valor)"></div>
                                            <div x-text="fmtData(d.vencimento)"></div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- CÁLCULO DO IMPOSTO (COM BASE PIS/COFINS IGUAL AO PDF) -->
                            <div class="font-bold text-[9px] pt-0.5">Cálculo do imposto</div>
                            <table class="w-full border-collapse border border-black text-right">
                                <tr>
                                    <td class="border border-black p-1 text-left"><div class="text-[8px]">Base Cálculo ICMS</div><div class="font-mono font-bold" x-text="fmtNum(danfeNota.baseicms)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor do ICMS</div><div class="font-mono font-bold" x-text="fmtNum(danfeNota.valoricms)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Base ICMS ST</div><div class="font-mono" x-text="fmtNum(danfeNota.base_st)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor ICMS Subst</div><div class="font-mono" x-text="fmtNum(danfeNota.valor_st)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Base PIS</div><div class="font-mono" x-text="fmtNum(danfeNota.base_pis)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor PIS</div><div class="font-mono" x-text="fmtNum(danfeNota.valorpis)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Base COFINS</div><div class="font-mono" x-text="fmtNum(danfeNota.base_cofins)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor COFINS</div><div class="font-mono" x-text="fmtNum(danfeNota.valorcofins)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor Total Produtos</div><div class="font-mono font-bold" x-text="fmtNum(danfeNota.valortotalitens)"></div></td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor do Frete</div><div class="font-mono" x-text="fmtNum(danfeNota.valorfrete)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Valor do Seguro</div><div class="font-mono" x-text="fmtNum(danfeNota.valorseguro)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Desconto</div><div class="font-mono" x-text="fmtNum(danfeNota.valortotaldesconto)"></div></td>
                                    <td colspan="2" class="border border-black p-1"><div class="text-[8px]">Outras Despesas Acessórias</div><div class="font-mono" x-text="fmtNum(danfeNota.valoroutras)"></div></td>
                                    <td colspan="2" class="border border-black p-1"><div class="text-[8px]">Valor Total do IPI</div><div class="font-mono" x-text="fmtNum(danfeNota.valoripi)"></div></td>
                                    <td colspan="2" class="border border-black p-1 bg-slate-100"><div class="text-[8px] font-bold">Valor Total da Nota</div><div class="font-mono font-extrabold text-xs" x-text="fmtNum(danfeNota.valortotalnota)"></div></td>
                                </tr>
                            </table>

                            <!-- TRANSPORTADOR / VOLUMES -->
                            <div class="font-bold text-[9px] pt-0.5">Transportador / Volumes transportados</div>
                            <table class="w-full border-collapse border border-black">
                                <tr>
                                    <td colspan="2" class="border border-black p-1"><div class="text-[8px]">Nome/Razão Social</div><div x-text="danfeNota.transp_razao || 'PROPRIO / NAO INFORMADO'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Frete por Conta</div><div x-text="danfeNota.tipofrete + ' (0-Emit / 1-Dest)'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Placa Veículo</div><div class="font-mono" x-text="danfeNota.placa || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">UF</div><div x-text="danfeNota.transp_uf || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">CNPJ/CPF</div><div class="font-mono" x-text="danfeNota.transp_doc || '-'"></div></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="border border-black p-1"><div class="text-[8px]">Endereço</div><div x-text="danfeNota.transp_end || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Município</div><div x-text="danfeNota.transp_cidade || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">UF</div><div x-text="danfeNota.transp_uf || '-'"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Inscrição Estadual</div><div class="font-mono" x-text="danfeNota.transp_ie || '-'"></div></td>
                                </tr>
                                <tr>
                                    <td class="border border-black p-1"><div class="text-[8px]">Quantidade</div><div class="font-mono" x-text="danfeNota.quant_volumes || 0"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Espécie</div><div>.</div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Marca</div><div>.</div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Número</div><div>0</div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Peso Bruto</div><div class="font-mono" x-text="fmtNum(danfeNota.pesobruto)"></div></td>
                                    <td class="border border-black p-1"><div class="text-[8px]">Peso Líquido</div><div class="font-mono" x-text="fmtNum(danfeNota.pesoliquido)"></div></td>
                                </tr>
                            </table>

                            <!-- DADOS DOS PRODUTOS -->
                            <div class="font-bold text-[9px] pt-0.5">Dados dos Produtos</div>
                            <table class="w-full border-collapse border border-black text-[9px]">
                                <thead>
                                    <tr class="bg-slate-100">
                                        <th class="border border-black p-1 text-left">Código Barras</th>
                                        <th class="border border-black p-1 text-left">Descrição</th>
                                        <th class="border border-black p-1 text-left">NCM/SH</th>
                                        <th class="border border-black p-1 text-center">CST</th>
                                        <th class="border border-black p-1 text-center">CFOP</th>
                                        <th class="border border-black p-1 text-center">UN.</th>
                                        <th class="border border-black p-1 text-right">Qtde</th>
                                        <th class="border border-black p-1 text-right">Valor unit.</th>
                                        <th class="border border-black p-1 text-right">Valor total</th>
                                        <th class="border border-black p-1 text-right">B. ICMS</th>
                                        <th class="border border-black p-1 text-right">%ICMS</th>
                                        <th class="border border-black p-1 text-right">V. ICMS</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="it in danfeItens" :key="'dfs-'+it.seq">
                                        <tr>
                                            <td class="border border-black p-1 font-mono" x-text="it.ean || it.iditem"></td>
                                            <td class="border border-black p-1">
                                                <div class="font-bold" x-text="it.descricao"></div>
                                                <div x-show="it.descricaotecnica" class="text-[8px] text-slate-600" x-text="it.descricaotecnica"></div>
                                            </td>
                                            <td class="border border-black p-1 font-mono" x-text="it.ncm"></td>
                                            <td class="border border-black p-1 font-mono text-center" x-text="it.cst_icms"></td>
                                            <td class="border border-black p-1 font-mono text-center" x-text="it.idcfop"></td>
                                            <td class="border border-black p-1 text-center" x-text="it.unidade"></td>
                                            <td class="border border-black p-1 text-right font-mono" x-text="fmtNum(it.quantidade)"></td>
                                            <td class="border border-black p-1 text-right font-mono" x-text="fmtNum(it.valor_unitario)"></td>
                                            <td class="border border-black p-1 text-right font-mono font-bold" x-text="fmtNum(it.valor_total)"></td>
                                            <td class="border border-black p-1 text-right font-mono" x-text="fmtNum(it.base_icms)"></td>
                                            <td class="border border-black p-1 text-right font-mono" x-text="fmtNum(it.aliq_icms)"></td>
                                            <td class="border border-black p-1 text-right font-mono" x-text="fmtNum(it.icms_dest)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>

                            <!-- DADOS ADICIONAIS -->
                            <div class="font-bold text-[9px] pt-0.5">Dados adicionais</div>
                            <table class="w-full border-collapse border border-black">
                                <tr>
                                    <td class="border border-black p-1.5 w-2/3 align-top text-[9px] whitespace-pre-line">
                                        <div class="text-[8px] font-bold">Informações complementares</div>
                                        <div x-text="(danfeNota.observacao || '') + (danfeNota.idembarque ? '\nEMBARQUE: ' + danfeNota.idembarque : '')"></div>
                                    </td>
                                    <td class="border border-black p-1.5 w-1/3 align-top text-[9px]">
                                        <div class="text-[8px] font-bold">Reservado ao Fisco</div>
                                        <div x-text="danfeNota.observacao_fisco || ''"></div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <!-- ============================================================ -->
                        <!-- LAYOUT 2: DANFE TRADICIONAL SEFAZ (ENTRADAS)                 -->
                        <!-- ============================================================ -->
                        <div x-show="danfeNota.tipo === 'ENTRADA'" class="border-2 border-slate-800 rounded-lg overflow-hidden bg-white">
                            <div class="grid grid-cols-1 md:grid-cols-3 border-b-2 border-slate-800">
                                <div class="p-3 border-b md:border-b-0 md:border-r-2 border-slate-800">
                                    <div class="text-[10px] font-bold uppercase text-slate-500">Emitente (Fornecedor)</div>
                                    <div class="font-extrabold text-sm" x-text="danfeNota.parc_razao"></div>
                                    <div class="text-[11px]" x-text="(danfeNota.parc_end || '') + ' - ' + (danfeNota.parc_bairro || '') + ' • ' + (danfeNota.parc_cidade || '') + '/' + (danfeNota.parc_uf || '')"></div>
                                    <div class="font-mono text-[11px] mt-1" x-text="'CNPJ/CPF: ' + (danfeNota.parc_doc || '-') + ' | IE: ' + (danfeNota.parc_ie || '-')"></div>
                                </div>
                                <div class="p-3 text-center border-b md:border-b-0 md:border-r-2 border-slate-800 flex flex-col justify-center">
                                    <div class="font-black text-base tracking-wide">DANFE TRADICIONAL</div>
                                    <div class="text-[10px] font-bold uppercase">0 - ENTRADA</div>
                                    <div class="font-mono font-black text-lg text-slate-900 mt-1" x-text="'Nº ' + fmtNfPad(danfeNota.numeronf)"></div>
                                    <div class="text-[10px] font-mono" x-text="'Série: ' + (danfeNota.idserie || '-')"></div>
                                </div>
                                <div class="p-3 flex flex-col justify-center">
                                    <div class="text-[10px] font-bold uppercase text-slate-500">Chave de Acesso NF-e</div>
                                    <div class="font-mono text-[10px] break-all font-bold mt-1" x-text="danfeNota.chave_nfe || 'Não informada'"></div>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-[10px]">
                                        <div><span class="text-slate-500">Emissão:</span> <strong x-text="fmtData(danfeNota.dataemissao)"></strong></div>
                                        <div><span class="text-slate-500">Entrada:</span> <strong x-text="fmtData(danfeNota.data_mov)"></strong></div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 border-b-2 border-slate-800 bg-slate-50/70">
                                <div class="text-[10px] font-extrabold uppercase text-slate-500 mb-1">Destinatário (Filial Nutricional)</div>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-[11px]">
                                    <div><strong>Razão:</strong> <span x-text="danfeNota.filial_razao"></span></div>
                                    <div><strong>CNPJ:</strong> <span class="font-mono" x-text="danfeNota.filial_cnpj || '-'"></span></div>
                                    <div><strong>IE:</strong> <span class="font-mono" x-text="danfeNota.filial_ie || '-'"></span></div>
                                    <div class="sm:col-span-2"><strong>Endereço:</strong> <span x-text="(danfeNota.filial_end || '') + ' - ' + (danfeNota.filial_bairro || '')"></span></div>
                                    <div><strong>UF:</strong> <span x-text="danfeNota.filial_uf || '-'"></span></div>
                                </div>
                            </div>

                            <div class="p-3 border-b-2 border-slate-800">
                                <div class="text-[10px] font-extrabold uppercase text-slate-500 mb-1.5">Cálculo do Imposto</div>
                                <div class="grid grid-cols-2 sm:grid-cols-6 gap-2 text-[11px]">
                                    <div class="border border-slate-300 rounded p-1.5"><span class="block text-[9px] text-slate-500">Base ICMS</span><strong class="font-mono" x-text="fmtMoeda(danfeNota.baseicms)"></strong></div>
                                    <div class="border border-slate-300 rounded p-1.5"><span class="block text-[9px] text-slate-500">Valor ICMS</span><strong class="font-mono" x-text="fmtMoeda(danfeNota.valoricms)"></strong></div>
                                    <div class="border border-slate-300 rounded p-1.5"><span class="block text-[9px] text-slate-500">Valor ICMS ST</span><strong class="font-mono" x-text="fmtMoeda(danfeNota.valor_st)"></strong></div>
                                    <div class="border border-slate-300 rounded p-1.5"><span class="block text-[9px] text-slate-500">Valor PIS</span><strong class="font-mono" x-text="fmtMoeda(danfeNota.valorpis)"></strong></div>
                                    <div class="border border-slate-300 rounded p-1.5"><span class="block text-[9px] text-slate-500">Valor COFINS</span><strong class="font-mono" x-text="fmtMoeda(danfeNota.valorcofins)"></strong></div>
                                    <div class="border border-slate-800 bg-slate-100 rounded p-1.5"><span class="block text-[9px] text-slate-700 font-bold">Total da Nota</span><strong class="font-mono text-sm" x-text="fmtMoeda(danfeNota.valortotalnota)"></strong></div>
                                </div>
                            </div>

                            <div class="p-3">
                                <div class="text-[10px] font-extrabold uppercase text-slate-500 mb-1.5">Dados dos Produtos / Serviços</div>
                                <table class="w-full text-[10px] border border-slate-300">
                                    <thead class="bg-slate-200 text-slate-800">
                                        <tr>
                                            <th class="px-2 py-1 text-left">Cód / Descrição</th>
                                            <th class="px-2 py-1 text-left">NCM</th>
                                            <th class="px-2 py-1 text-left">CST</th>
                                            <th class="px-2 py-1 text-left">CFOP</th>
                                            <th class="px-2 py-1 text-right">Qtd</th>
                                            <th class="px-2 py-1 text-right">V. Unit</th>
                                            <th class="px-2 py-1 text-right">V. Total</th>
                                            <th class="px-2 py-1 text-right">BC ICMS</th>
                                            <th class="px-2 py-1 text-right">ICMS (Dest/Cred)</th>
                                            <th class="px-2 py-1 text-right">IPI</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <template x-for="it in danfeItens" :key="'dfe-'+it.seq">
                                            <tr>
                                                <td class="px-2 py-1 font-medium" x-text="it.iditem + ' - ' + it.descricao"></td>
                                                <td class="px-2 py-1 font-mono" x-text="it.ncm"></td>
                                                <td class="px-2 py-1 font-mono" x-text="it.cst_icms"></td>
                                                <td class="px-2 py-1 font-mono font-bold" x-text="it.idcfop"></td>
                                                <td class="px-2 py-1 text-right font-mono" x-text="fmtNum(it.quantidade)"></td>
                                                <td class="px-2 py-1 text-right font-mono" x-text="fmtMoeda(it.valor_unitario)"></td>
                                                <td class="px-2 py-1 text-right font-mono font-bold" x-text="fmtMoeda(it.valor_total)"></td>
                                                <td class="px-2 py-1 text-right font-mono" x-text="fmtMoeda(it.base_icms)"></td>
                                                <td class="px-2 py-1 text-right font-mono" x-text="fmtMoeda(it.icms_dest) + ' / Cred: ' + fmtMoeda(it.icms_cred)"></td>
                                                <td class="px-2 py-1 text-right font-mono" x-text="fmtMoeda(it.ipi)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-3 border-t-2 border-slate-800 bg-slate-50 text-[10px]" x-show="danfeNota.observacao || danfeNota.observacao_fisco">
                                <div class="font-extrabold uppercase text-slate-500 mb-1">Dados Adicionais</div>
                                <div x-show="danfeNota.observacao"><strong>Obs.:</strong> <span x-text="danfeNota.observacao"></span></div>
                                <div x-show="danfeNota.observacao_fisco"><strong>Obs. Fisco:</strong> <span x-text="danfeNota.observacao_fisco"></span></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
