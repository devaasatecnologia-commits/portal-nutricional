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
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="min-h-screen bg-slate-50 pb-14" x-data="contabilHandler()" x-init="init()">

    <!-- HEADER MOBILE -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-indigo-950 text-white shadow-lg">
        <div class="flex items-center justify-between px-4 py-3">
            <a href="<?= $appBase ?>/portal/" class="flex items-center gap-1.5 text-white no-underline text-xs font-extrabold">
                <i class="fa-solid fa-arrow-left"></i> VOLTAR
            </a>
            <span class="text-xs font-black tracking-wide">CONTÁBIL &amp; FISCAL</span>
            <button @click="carregarTudo()" class="px-2.5 py-1 rounded-lg bg-white/15 text-xs font-bold">
                <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
            </button>
        </div>
    </div>
    <div class="lg:hidden h-12"></div>

    <div class="max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 pt-6 space-y-5">

        <!-- TOP BAR DESKTOP -->
        <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-indigo-900 rounded-2xl p-5 sm:p-6 text-white shadow-xl border border-indigo-800/40">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex items-start gap-4">
                    <a href="<?= $appBase ?>/portal/"
                       class="hidden lg:inline-flex items-center justify-center w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 text-white transition no-underline shrink-0"
                       title="Voltar ao Portal">
                        <i class="fa-solid fa-arrow-left"></i>
                    </a>
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-indigo-500/30 text-indigo-200 border border-indigo-400/30">
                                Conferência Domínio Sistemas
                            </span>
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-200 border border-emerald-400/30"
                                  x-text="filiaisDisponiveis.length + ' filial(is) na hierarquia'"></span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight">
                            Apuração Contábil &amp; Rastreio Fiscal (ICMS · PIS · COFINS)
                        </h1>
                        <p class="text-xs sm:text-sm text-indigo-200/80 mt-0.5">
                            Projeção mensal de impostos a pagar (Saídas − Entradas), detalhamento por CFOP, notas fiscais e relatório completo por itens.
                        </p>
                    </div>
                </div>

                <!-- FILTROS GLOBAIS -->
                <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-end gap-2.5">
                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Competência</label>
                        <input type="month" x-model="filtros.mes" @change="onFiltroChange()"
                               class="px-3 py-2 rounded-xl bg-white/10 border border-white/20 text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Filial (Hierarquia)</label>
                        <select x-model="filtros.filial" @change="onFiltroChange()"
                                class="px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400 min-w-[170px]">
                            <option value="0" x-show="filiaisDisponiveis.length > 1">Todas Permitidas</option>
                            <template x-for="f in filiaisDisponiveis" :key="f.idfilial">
                                <option :value="f.idfilial" x-text="f.idfilial + ' - ' + f.nome + ' (' + f.uf + ')'"></option>
                            </template>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold uppercase tracking-wider text-indigo-200 mb-1">Crédito Entrada</label>
                        <select x-model="filtros.modoCredito" @change="onFiltroChange()"
                                class="px-3 py-2 rounded-xl bg-slate-900/80 border border-white/20 text-white text-xs font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <option value="creditado">Creditado (Domínio)</option>
                            <option value="destacado">Destacado NF</option>
                        </select>
                    </div>

                    <button @click="carregarTudo()" :disabled="loading"
                            class="px-4 py-2 rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white text-xs font-extrabold shadow-lg transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-rotate" :class="{ 'fa-spin': loading }"></i>
                        <span>Atualizar</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ALERTA DE ERRO -->
        <div x-show="erro" x-cloak class="bg-rose-50 border border-rose-200 text-rose-800 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-rose-600"></i>
            <span x-text="erro"></span>
        </div>

        <!-- CARDS DE PROJEÇÃO DE IMPOSTOS A PAGAR -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" x-show="resumo">
            <!-- ICMS -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm relative overflow-hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">ICMS Mensal</span>
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
                    <div class="flex justify-between text-[11px] text-slate-400" x-show="Math.abs(projecao.icms.credito_destacado - projecao.icms.credito) > 0.01">
                        <span>Destacado Entradas:</span>
                        <span class="font-mono" x-text="fmtMoeda(projecao.icms.credito_destacado)"></span>
                    </div>
                </div>
            </div>

            <!-- PIS -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">PIS Mensal</span>
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
            </div>

            <!-- COFINS -->
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-slate-500">COFINS Mensal</span>
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
            </div>

            <!-- TOTAL PROJETADO A PAGAR -->
            <div class="bg-gradient-to-br from-indigo-900 to-slate-900 text-white rounded-2xl p-5 shadow-md">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-indigo-200">Projeção Total a Pagar</span>
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
                    <div class="flex justify-between text-[11px] text-amber-300" x-show="auditoria.saidas_canceladas_qtd > 0">
                        <span>Canceladas excluídas:</span>
                        <span class="font-bold" x-text="auditoria.saidas_canceladas_qtd + ' NFs'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALERTA AUDITORIA PRÉ-FECHAMENTO (CRÉDITO PARCIAL / DEVOLUÇÕES) -->
        <div x-show="auditoria.entradas_credito_parcial_notas > 0" x-cloak
             class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-amber-900">
            <div class="flex items-start sm:items-center gap-2.5">
                <i class="fa-solid fa-magnifying-glass-chart text-amber-600 text-base mt-0.5 sm:mt-0"></i>
                <div>
                    <strong>Auditoria Pré-Fechamento:</strong>
                    Existem <strong x-text="auditoria.entradas_credito_parcial_notas"></strong> nota(s) de entrada onde o imposto destacado difere do creditado
                    (ex.: devoluções 1202 ou itens sem aproveitamento de crédito) — diferença de
                    <strong class="font-mono" x-text="fmtMoeda(auditoria.entradas_icms_nao_creditado)"></strong> em ICMS.
                </div>
            </div>
            <button @click="abaAtiva = 'notas'; filtroNotas.tipo = 'ENTRADA'; carregarNotas(1)"
                    class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white font-bold shrink-0">
                Conferir Entradas
            </button>
        </div>

        <!-- NAVEGAÇÃO POR ABAS -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex flex-wrap border-b border-slate-200 bg-slate-50/70 px-3 pt-2 gap-1">
                <button @click="abaAtiva = 'cfop'"
                        :class="abaAtiva === 'cfop' ? 'bg-white text-indigo-700 border-slate-200 border-b-white shadow-sm' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                        class="px-4 py-2.5 text-xs font-extrabold rounded-t-xl border border-b-0 transition flex items-center gap-2">
                    <i class="fa-solid fa-layer-group"></i> Apuração por CFOP &amp; Evolução
                </button>
                <button @click="abaAtiva = 'notas'; if(!notasCarregadas) carregarNotas(1)"
                        :class="abaAtiva === 'notas' ? 'bg-white text-indigo-700 border-slate-200 border-b-white shadow-sm' : 'text-slate-600 hover:text-slate-900 border-transparent'"
                        class="px-4 py-2.5 text-xs font-extrabold rounded-t-xl border border-b-0 transition flex items-center gap-2">
                    <i class="fa-solid fa-file-invoice"></i> Notas Fiscais (Raio-X)
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
                <!-- GRÁFICO 6 MESES -->
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

                <!-- TABELA CFOP SAÍDAS -->
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
                                    <td colspan="11" class="px-4 py-6 text-center text-slate-400">Nenhuma saída registrada na competência.</td>
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

                <!-- TABELA CFOP ENTRADAS -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                            ENTRADAS POR CFOP (Créditos de Impostos — Modo: <span class="text-indigo-700" x-text="filtros.modoCredito === 'creditado' ? 'Creditado Domínio' : 'Destacado NF'"></span>)
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
                                    <td colspan="11" class="px-4 py-6 text-center text-slate-400">Nenhuma entrada registrada na competência.</td>
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

            <!-- ABA 2: NOTAS FISCAIS (RAIO-X) -->
            <div x-show="abaAtiva === 'notas'" x-cloak class="p-5 space-y-4">
                <div class="flex flex-wrap items-end gap-3 bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                    <div>
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Operação</label>
                        <select x-model="filtroNotas.tipo" @change="carregarNotas(1)" class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-bold bg-white">
                            <option value="TODAS">Todas (Saídas + Entradas)</option>
                            <option value="SAIDA">Somente Saídas</option>
                            <option value="ENTRADA">Somente Entradas</option>
                        </select>
                    </div>
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
                                <th class="px-3 py-2.5 text-left">Tipo</th>
                                <th class="px-3 py-2.5 text-left">NF / Data</th>
                                <th class="px-3 py-2.5 text-left">Filial</th>
                                <th class="px-3 py-2.5 text-left">Cliente / Fornecedor</th>
                                <th class="px-3 py-2.5 text-left">CFOPs</th>
                                <th class="px-3 py-2.5 text-right">Total NF</th>
                                <th class="px-3 py-2.5 text-right">Base ICMS</th>
                                <th class="px-3 py-2.5 text-right">ICMS</th>
                                <th class="px-3 py-2.5 text-right">PIS</th>
                                <th class="px-3 py-2.5 text-right">COFINS</th>
                                <th class="px-3 py-2.5 text-center">Itens</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="n in notasLista" :key="n.tipo + '-' + n.idfilial + '-' + n.idnota">
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold"
                                              :class="n.tipo === 'SAIDA' ? 'bg-rose-100 text-rose-800' : 'bg-emerald-100 text-emerald-800'"
                                              x-text="n.tipo"></span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="font-mono font-extrabold text-slate-900" x-text="'NF ' + n.numeronf"></div>
                                        <div class="text-[11px] text-slate-500" x-text="fmtData(n.data_doc)"></div>
                                    </td>
                                    <td class="px-3 py-2 text-[11px] text-slate-600" x-text="'F' + n.idfilial"></td>
                                    <td class="px-3 py-2 max-w-[260px]">
                                        <div class="font-semibold text-slate-800 truncate" :title="n.parceiro" x-text="n.parceiro"></div>
                                        <div class="text-[10px] text-slate-400 font-mono" x-text="(n.doc_parceiro || '-') + ' • ' + n.uf"></div>
                                    </td>
                                    <td class="px-3 py-2 font-mono text-[11px] text-indigo-700 font-bold" x-text="n.cfops"></td>
                                    <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(n.valortotal)"></td>
                                    <td class="px-3 py-2 text-right font-mono text-slate-600" x-text="fmtMoeda(n.base_icms)"></td>
                                    <td class="px-3 py-2 text-right font-mono font-bold" x-text="fmtMoeda(n.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? n.icms_cred : n.icms_dest)"></td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="fmtMoeda(n.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? n.pis_cred : n.pis_dest)"></td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="fmtMoeda(n.tipo === 'ENTRADA' && filtros.modoCredito === 'creditado' ? n.cofins_cred : n.cofins_dest)"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button @click="abrirItensNota(n)" class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[11px]">
                                            <i class="fa-solid fa-list-ul mr-1"></i> Itens
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="notasLista.length === 0">
                                <td colspan="11" class="px-4 py-8 text-center text-slate-400">Nenhuma nota encontrada para os filtros aplicados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- PAGINAÇÃO NOTAS -->
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
                        <label class="block text-[10px] font-bold uppercase text-slate-500 mb-1">Operação</label>
                        <select x-model="filtroItens.tipo" @change="carregarItens(1)" class="px-3 py-2 rounded-lg border border-slate-300 text-xs font-bold bg-white">
                            <option value="TODAS">Todas (Saídas + Entradas)</option>
                            <option value="SAIDA">Somente Saídas</option>
                            <option value="ENTRADA">Somente Entradas</option>
                        </select>
                    </div>
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

                <!-- TOTAIS DO FILTRO DE ITENS -->
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
                                <th class="px-2.5 py-2 text-left">Item / Ref / NCM</th>
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
                                        <span class="font-mono font-bold text-slate-900" x-text="it.numeronf"></span>
                                    </td>
                                    <td class="px-2.5 py-2 whitespace-nowrap">
                                        <div x-text="fmtData(it.data_doc)"></div>
                                        <div class="text-[10px] text-slate-400" x-text="'F' + it.idfilial + ' • ' + it.uf"></div>
                                    </td>
                                    <td class="px-2.5 py-2 max-w-[260px]">
                                        <div class="font-semibold text-slate-800 truncate" :title="it.item_desc" x-text="it.iditem + ' - ' + it.item_desc"></div>
                                        <div class="text-[10px] text-slate-400 font-mono" x-text="'Ref: ' + (it.referencia || '-') + ' | NCM: ' + (it.ncm || '-')"></div>
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

                <!-- PAGINAÇÃO ITENS -->
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
                        <p class="text-xs text-slate-500">Competência <strong x-text="competenciaLabel"></strong> · Base para conferência mensal de guias.</p>
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

    <!-- MODAL DETALHES DOS ITENS DA NOTA (z-[70]) -->
    <div x-show="modalNotaAberto" x-cloak class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-900/70 p-4 backdrop-blur-sm">
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

</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
