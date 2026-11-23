<?php
$pageTitle = 'Programa 365 Comercial (T27) | Nutricional';
$moduleJs = 'programa-365.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  [x-cloak] { display: none !important; }
  .tatame-hero { background: linear-gradient(135deg, #2F5D50 0%, #1F3F36 65%, #0F231E 100%); }
  .gold-accent { color: #E8C87E; }
  .gold-bg { background-color: #E8C87E; color: #1F3F36; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="modulo-container max-w-7xl mx-auto px-4 lg:px-6 py-4" x-data="programa365App()" x-init="init()" x-cloak>

    <!-- Header / Hero T27 -->
    <div class="tatame-hero rounded-3xl p-5 lg:p-6 mb-5 text-white shadow-lg flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center gap-4">
            <a href="<?= $appBase ?>/portal/" class="w-10 h-10 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white no-underline transition">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 rounded-2xl gold-bg flex items-center justify-center shadow">
                <i class="fa-solid fa-medal text-xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-lg lg:text-xl font-extrabold tracking-tight" x-text="campanha?.nome || 'Programa 365 Comercial'"></h1>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-extrabold uppercase gold-bg" x-text="campanha?.codigo || 'T27'"></span>
                </div>
                <p class="text-xs text-emerald-100/80 mt-0.5" x-text="campanha?.subtitulo || 'Temporada 2027 - Jiu-Jitsu: O Caminho da Faixa Preta (1.440 XP)'"></p>
            </div>
        </div>

        <!-- Filtros Globais -->
        <div class="flex flex-wrap items-center gap-2">
            <select x-model.number="filtros.campanha_id" @change="carregarTudo()" class="bg-white/10 border border-white/20 text-white text-xs font-bold rounded-xl px-3 py-2 focus:outline-none">
                <template x-for="c in campanhas" :key="c.id">
                    <option class="text-slate-900" :value="c.id" x-text="c.codigo + ' - ' + c.temporada_ano"></option>
                </template>
            </select>

            <select x-model.number="filtros.mes" @change="carregarDashboard(); carregarRanking()" class="bg-white/10 border border-white/20 text-white text-xs font-bold rounded-xl px-3 py-2 focus:outline-none">
                <template x-for="m in meses" :key="m.id">
                    <option class="text-slate-900" :value="m.id" x-text="'Round ' + m.id + ' - ' + m.nome"></option>
                </template>
            </select>

            <template x-if="participantesDisponiveis.length > 1">
                <select x-model.number="filtros.participante_id" @change="carregarDashboard(); carregarPins(); carregarExtrato()" class="bg-white/10 border border-white/20 text-white text-xs font-bold rounded-xl px-3 py-2 focus:outline-none max-w-[210px]">
                    <template x-for="p in participantesDisponiveis" :key="p.id">
                        <option class="text-slate-900" :value="p.id" x-text="p.nome + ' (' + p.pts_total_mes + ' pts)'"></option>
                    </template>
                </select>
            </template>

            <button @click="carregarTudo()" class="px-3 py-2 rounded-xl bg-white/15 hover:bg-white/25 text-white text-xs font-bold transition">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>
    </div>

    <!-- Navegacao por Abas -->
    <div class="flex flex-wrap gap-2 mb-5">
        <button @click="aba='tatame'" :class="aba==='tatame' ? 'bg-[#2F5D50] text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 border border-slate-200">
            <i class="fa-solid fa-gauge-high"></i> Meu Tatame
        </button>
        <button @click="aba='ranking'; carregarRanking()" :class="aba==='ranking' ? 'bg-[#2F5D50] text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 border border-slate-200">
            <i class="fa-solid fa-trophy"></i> Ranking & Premiação
        </button>
        <button @click="aba='pins'; carregarPins()" :class="aba==='pins' ? 'bg-[#2F5D50] text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 border border-slate-200">
            <i class="fa-solid fa-award"></i> Faixas & PINs
        </button>
        <button @click="aba='extrato'; carregarExtrato()" :class="aba==='extrato' ? 'bg-[#2F5D50] text-white shadow' : 'bg-white text-slate-600 hover:bg-slate-50'" class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 border border-slate-200">
            <i class="fa-solid fa-file-invoice-dollar"></i> Extrato & Bônus
        </button>
        <template x-if="isAdmin">
            <button @click="aba='admin'; carregarAdmin()" :class="aba==='admin' ? 'bg-amber-600 text-white shadow' : 'bg-amber-50 text-amber-800 hover:bg-amber-100'" class="px-4 py-2.5 rounded-xl text-xs font-extrabold transition flex items-center gap-2 border border-amber-200 ml-auto">
                <i class="fa-solid fa-sliders"></i> Administração T27
            </button>
        </template>
    </div>

    <!-- ABA 1: MEU TATAME -->
    <template x-if="aba === 'tatame'">
        <div>
            <template x-if="!selecionado">
                <div class="bg-white rounded-3xl p-8 text-center border border-slate-200">
                    <i class="fa-solid fa-users-slash text-3xl text-slate-300 mb-3"></i>
                    <h3 class="text-base font-bold text-slate-700">Nenhum participante configurado para seu escopo nesta campanha.</h3>
                    <p class="text-xs text-slate-400 mt-1">Administradores podem cadastrar participantes e metas na aba <b>Administração T27</b>.</p>
                </div>
            </template>

            <template x-if="selecionado">
                <div class="space-y-5">
                    <!-- Resumo Faixa + Pontuacao + Bonus -->
                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <div class="lg:col-span-2 bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="text-[11px] font-bold uppercase text-slate-400" x-text="selecionado.equipe"></span>
                                    <h2 class="text-xl font-extrabold text-slate-800" x-text="selecionado.nome"></h2>
                                    <div class="flex items-center gap-2 mt-2">
                                        <span class="px-3 py-1 rounded-full text-xs font-extrabold border" :style="`background:${faixa.cor}; color:${faixa.texto}; border-color:${faixa.borda}`" x-text="faixa.nome + ' • ' + faixa.grau + 'º Grau'"></span>
                                        <template x-if="selecionado.fechado">
                                            <span class="px-2.5 py-1 rounded-full text-[10px] font-extrabold bg-slate-800 text-amber-300"><i class="fa-solid fa-lock mr-1"></i> Round Fechado</span>
                                        </template>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Posição Anual</span>
                                    <span class="text-2xl font-black text-[#2F5D50]" x-text="posicaoAnual ? '#' + posicaoAnual : '--'"></span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <div class="flex justify-between text-xs font-bold text-slate-600 mb-1">
                                    <span>Jornada 1.440 XP: <b x-text="xpAnual + ' XP'"></b></span>
                                    <span x-text="faixa.pct_jornada + '%'"></span>
                                </div>
                                <div class="w-full h-3 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full bg-gradient-to-r from-[#2F5D50] to-[#E8C87E] transition-all duration-500" :style="`width: ${faixa.pct_jornada}%`"></div>
                                </div>
                                <div class="text-[11px] text-slate-400 mt-1" x-text="faixa.xp_falta_proxima > 0 ? ('Faltam ' + faixa.xp_falta_proxima + ' XP para ' + faixa.proxima_faixa) : 'Faixa Preta Consagrada!'"></div>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                            <span class="text-[11px] font-bold uppercase text-slate-400">Pontuação do Round</span>
                            <div class="my-2">
                                <span class="text-4xl font-black text-[#2F5D50]" x-text="selecionado.pts_total_mes"></span>
                                <span class="text-sm font-bold text-slate-400"> / 120 pts</span>
                            </div>
                            <div class="text-xs text-slate-500">
                                Faturamento Líquido: <b class="text-slate-800" x-text="brl(selecionado.fat_liquido)"></b><br>
                                Meta Round: <b class="text-slate-800" x-text="brl(selecionado.meta_faturamento)"></b>
                            </div>
                        </div>

                        <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm flex flex-col justify-between">
                            <span class="text-[11px] font-bold uppercase text-slate-400">Bônus Performance (Mês)</span>
                            <div class="my-2">
                                <span class="text-3xl font-black text-emerald-600" x-text="brl(selecionado.bonus_mes_rs)"></span>
                            </div>
                            <div class="text-xs text-slate-500">
                                Acumulado Ano: <b class="text-emerald-700" x-text="brl(bonusAnual)"></b><br>
                                Devoluções Mês: <b class="text-rose-600" x-text="brl(selecionado.devolucoes_mes)"></b>
                            </div>
                        </div>
                    </div>

                    <!-- Cadencia Dia 10, Dia 20, Dia 30 + Positivacao -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-extrabold text-[#2F5D50]">DIA 10 • RASPAGEM</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-emerald-100 text-emerald-800" x-text="'+' + selecionado.pts_dia10 + ' pts'"></span>
                            </div>
                            <div class="text-xl font-black text-slate-800" x-text="selecionado.pct_dia10 + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="brl(selecionado.fat_dia10) + ' faturado até dia 10'"></div>
                            <div class="text-[10px] text-slate-400 mt-2">Alvos: 15% (+10) | 20% (+15) | 25% (+20)</div>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-extrabold text-[#2F5D50]">DIA 20 • PASSAGEM</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-emerald-100 text-emerald-800" x-text="'+' + selecionado.pts_dia20 + ' pts'"></span>
                            </div>
                            <div class="text-xl font-black text-slate-800" x-text="selecionado.pct_dia20 + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="brl(selecionado.fat_dia20) + ' faturado até dia 20'"></div>
                            <div class="text-[10px] text-slate-400 mt-2">Alvos: 50% (+10) | 55% (+15) | 60% (+20)</div>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-extrabold text-[#2F5D50]">DIA 30 • FINALIZAÇÃO</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-amber-100 text-amber-800" x-text="'+' + selecionado.pts_dia30 + ' pts'"></span>
                            </div>
                            <div class="text-xl font-black text-slate-800" x-text="selecionado.pct_dia30 + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="brl(selecionado.fat_liquido) + ' líquido no mês'"></div>
                            <div class="text-[10px] text-slate-400 mt-2">80% (+10) | 90% (+15) | 100% (+20) | 110% (+30)</div>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-xs font-extrabold text-[#2F5D50]">CONTROLE LATERAL</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black bg-blue-100 text-blue-800" x-text="'+' + selecionado.pts_positivacao + ' pts'"></span>
                            </div>
                            <div class="text-xl font-black text-slate-800" x-text="selecionado.pct_positivacao + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="selecionado.clientes_positivados + ' / ' + selecionado.carteira_efetiva + ' clientes (piso 60)'"></div>
                            <div class="text-[10px] text-slate-400 mt-2">Alvos: 50% (+10) | 60% (+15) | 70% (+20)</div>
                        </div>
                    </div>

                    <!-- Desafios do Mes (Kimono / Armlock / RNC) -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-extrabold text-slate-700">DESAFIO 01 • KIMONO</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black" :class="selecionado.pts_desafio1 > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'" x-text="'+' + selecionado.pts_desafio1 + ' pts'"></span>
                            </div>
                            <div class="text-lg font-black text-slate-800" x-text="selecionado.pct_desafio1 + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="getDesafioTitulo(1, 'Foco Mix Estratégico (>= 15%)')"></div>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-extrabold text-slate-700">DESAFIO 02 • ARMLOCK</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black" :class="selecionado.pts_desafio2 > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'" x-text="'+' + selecionado.pts_desafio2 + ' pts'"></span>
                            </div>
                            <div class="text-lg font-black text-slate-800" x-text="selecionado.pct_desafio2 + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="getDesafioTitulo(2, 'Foco Linha Premium (>= 20%)')"></div>
                        </div>

                        <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-xs font-extrabold text-slate-700">DESAFIO 03 • RNC</span>
                                <span class="px-2 py-0.5 rounded-lg text-xs font-black" :class="selecionado.pts_desafio3 > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'" x-text="'+' + selecionado.pts_desafio3 + ' pts'"></span>
                            </div>
                            <div class="text-lg font-black text-slate-800" x-text="selecionado.pct_desafio3 + '%'"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="getDesafioTitulo(3, 'Reativação / Abertura 90d (>= 25%)')"></div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- ABA 2: RANKING -->
    <template x-if="aba === 'ranking'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-extrabold text-slate-800 mb-3"><i class="fa-solid fa-crown text-amber-500 mr-1"></i> Ranking Anual T27 (Top 6 Premiados)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-100">
                                <th class="py-2">#</th>
                                <th class="py-2">Atleta / Equipe</th>
                                <th class="py-2">Faixa</th>
                                <th class="py-2 text-right">XP</th>
                                <th class="py-2 text-right">Fat. Líquido</th>
                                <th class="py-2 text-right">Prêmio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="r in rankingAnual" :key="r.idparticipante">
                                <tr class="border-b border-slate-50 hover:bg-slate-50">
                                    <td class="py-2.5 font-black" x-text="r.posicao + 'º'"></td>
                                    <td class="py-2.5">
                                        <div class="font-bold text-slate-800" x-text="r.nome"></div>
                                        <div class="text-[10px] text-slate-400" x-text="r.equipe"></div>
                                    </td>
                                    <td class="py-2.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border" :style="`background:${r.faixa.cor}; color:${r.faixa.texto}; border-color:${r.faixa.borda}`" x-text="r.faixa.nome.replace('Faixa ','') + ' ' + r.faixa.grau + 'º'"></span>
                                    </td>
                                    <td class="py-2.5 text-right font-black text-[#2F5D50]" x-text="r.xp_total"></td>
                                    <td class="py-2.5 text-right font-semibold text-slate-600" x-text="brl(r.fat_liquido_anual)"></td>
                                    <td class="py-2.5 text-right font-extrabold text-emerald-600" x-text="r.premio_anual_rs > 0 ? brl(r.premio_anual_rs) : '-'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-extrabold text-slate-800 mb-3"><i class="fa-solid fa-bolt text-emerald-600 mr-1"></i> Classificação do Round Atual</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-100">
                                <th class="py-2">#</th>
                                <th class="py-2">Atleta</th>
                                <th class="py-2 text-right">Cadência</th>
                                <th class="py-2 text-right">Posit.</th>
                                <th class="py-2 text-right">Desaf.</th>
                                <th class="py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(r, idx) in rankingRound" :key="r.idparticipante">
                                <tr class="border-b border-slate-50 hover:bg-slate-50">
                                    <td class="py-2.5 font-black" x-text="(idx + 1) + 'º'"></td>
                                    <td class="py-2.5">
                                        <div class="font-bold text-slate-800" x-text="r.nome"></div>
                                        <div class="text-[10px] text-slate-400" x-text="brl(r.fat_liquido) + ' (' + r.pct_dia30 + '%)'"></div>
                                    </td>
                                    <td class="py-2.5 text-right font-semibold" x-text="(r.pts_dia10 + r.pts_dia20 + r.pts_dia30)"></td>
                                    <td class="py-2.5 text-right font-semibold" x-text="r.pts_positivacao"></td>
                                    <td class="py-2.5 text-right font-semibold" x-text="(r.pts_desafio1 + r.pts_desafio2 + r.pts_desafio3)"></td>
                                    <td class="py-2.5 text-right font-black text-[#2F5D50]" x-text="r.pts_total_mes + ' pts'"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </template>

    <!-- ABA 3: FAIXAS & PINS -->
    <template x-if="aba === 'pins'">
        <div class="space-y-5">
            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-extrabold text-slate-800 mb-3">Trilha Oficial de Graduação (1.440 XP)</h3>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 text-xs">
                    <div class="p-3 rounded-2xl border border-slate-200 bg-slate-50"><b>Faixa Branca</b><div class="text-slate-500">0 a 287 XP</div></div>
                    <div class="p-3 rounded-2xl border border-blue-200 bg-blue-50 text-blue-900"><b>Faixa Azul</b><div class="opacity-80">288 a 575 XP</div></div>
                    <div class="p-3 rounded-2xl border border-purple-200 bg-purple-50 text-purple-900"><b>Faixa Roxa</b><div class="opacity-80">576 a 863 XP</div></div>
                    <div class="p-3 rounded-2xl border border-amber-300 bg-amber-50 text-amber-900"><b>Faixa Marrom</b><div class="opacity-80">864 a 1.151 XP</div></div>
                    <div class="p-3 rounded-2xl border border-amber-400 bg-slate-900 text-[#E8C87E]"><b>Faixa Preta</b><div class="opacity-90">1.152 a 1.440 XP</div></div>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-extrabold text-slate-800 mb-3">Galeria de PINs Conquistados</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <template x-for="pin in catalogoPins" :key="pin.codigo">
                        <div class="p-4 rounded-2xl border transition" :class="pinDesbloqueado(pin.codigo) ? 'border-amber-300 bg-amber-50/60' : 'border-slate-100 bg-slate-50/70 opacity-60'">
                            <div class="flex items-center justify-between mb-2">
                                <span class="w-9 h-9 rounded-xl flex items-center justify-center" :class="pinDesbloqueado(pin.codigo) ? 'bg-[#2F5D50] text-[#E8C87E]' : 'bg-slate-200 text-slate-400'">
                                    <i class="fa-solid" :class="pin.icone"></i>
                                </span>
                                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full" :class="pinDesbloqueado(pin.codigo) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-500'" x-text="pinDesbloqueado(pin.codigo) ? 'Desbloqueado' : pin.categoria"></span>
                            </div>
                            <div class="text-xs font-extrabold text-slate-800" x-text="pin.nome"></div>
                            <div class="text-[11px] text-slate-500 mt-1" x-text="pin.regra"></div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- ABA 4: EXTRATO & BONUS -->
    <template x-if="aba === 'extrato'">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
            <div class="lg:col-span-2 bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-extrabold text-slate-800 mb-3">Rounds Consolidados (Snapshots Fechados)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-100">
                                <th class="py-2">Round</th>
                                <th class="py-2 text-right">Fat. Líquido</th>
                                <th class="py-2 text-right">% Meta</th>
                                <th class="py-2 text-right">Pontos</th>
                                <th class="py-2 text-right">Bônus R$</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="r in extrato.rounds" :key="r.id">
                                <tr class="border-b border-slate-50">
                                    <td class="py-2 font-bold" x-text="r.mes + '/' + r.ano"></td>
                                    <td class="py-2 text-right" x-text="brl(r.fat_liquido)"></td>
                                    <td class="py-2 text-right" x-text="r.pct_dia30 + '%'"></td>
                                    <td class="py-2 text-right font-black text-[#2F5D50]" x-text="r.pts_total_mes"></td>
                                    <td class="py-2 text-right font-extrabold text-emerald-600" x-text="brl(r.bonus_mes_rs)"></td>
                                </tr>
                            </template>
                            <template x-if="!extrato.rounds.length">
                                <tr><td colspan="5" class="py-6 text-center text-slate-400">Nenhum round fechado ainda para este participante.</td></tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <h3 class="text-sm font-extrabold text-slate-800 mb-3">Pontos Extras & Auditorias</h3>
                <div class="space-y-2">
                    <template x-for="ex in extrato.extras" :key="ex.id">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                            <div class="flex justify-between font-bold">
                                <span x-text="ex.tipo + ' (' + ex.mes + '/' + ex.ano + ')'"></span>
                                <span class="text-emerald-700" x-text="(ex.pontos >= 0 ? '+' : '') + ex.pontos + ' pts'"></span>
                            </div>
                            <div class="text-slate-600 mt-1" x-text="ex.motivo"></div>
                            <div class="text-[10px] text-slate-400 mt-1" x-text="'Por ' + ex.criado_por"></div>
                        </div>
                    </template>
                    <template x-if="!extrato.extras.length">
                        <p class="text-xs text-slate-400 text-center py-6">Sem lançamentos extras registrados.</p>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- ABA 5: ADMINISTRACAO T27 -->
    <template x-if="aba === 'admin' && isAdmin">
        <div class="space-y-5">
            <!-- Sub-menu Admin -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <!-- Equipes -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-extrabold text-slate-800 mb-3">1. Equipes</h3>
                    <div class="flex gap-2 mb-3">
                        <input type="text" x-model="formEquipe.nome" placeholder="Nome da equipe" class="flex-1 px-3 py-2 border border-slate-200 rounded-xl text-xs">
                        <button @click="salvarEquipe()" class="px-3 py-2 bg-[#2F5D50] text-white rounded-xl text-xs font-bold">Salvar</button>
                    </div>
                    <div class="space-y-1 max-h-40 overflow-y-auto text-xs">
                        <template x-for="eq in admin.equipes" :key="eq.id">
                            <div class="px-3 py-2 rounded-lg bg-slate-50 flex justify-between">
                                <b x-text="eq.nome"></b>
                                <span class="text-slate-400" x-text="'#' + eq.id"></span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Participantes -->
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm lg:col-span-2">
                    <h3 class="text-sm font-extrabold text-slate-800 mb-3">2. Participantes (Representantes / Vendedores ERP)</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-4 gap-2 mb-3">
                        <select x-model.number="formPart.idvendedor" @change="preencherNomeVendedor()" class="px-3 py-2 border border-slate-200 rounded-xl text-xs sm:col-span-2">
                            <option value="">Selecione vendedor ERP...</option>
                            <template x-for="v in admin.vendedores_erp" :key="v.idvendedor">
                                <option :value="v.idvendedor" x-text="v.nome + ' (#' + v.idvendedor + ')'"></option>
                            </template>
                        </select>
                        <select x-model.number="formPart.idequipe" class="px-3 py-2 border border-slate-200 rounded-xl text-xs">
                            <option value="">Sem equipe</option>
                            <template x-for="eq in admin.equipes" :key="eq.id">
                                <option :value="eq.id" x-text="eq.nome"></option>
                            </template>
                        </select>
                        <div class="flex gap-2">
                            <input type="number" x-model.number="formPart.carteira_base" placeholder="Carteira" class="w-20 px-2 py-2 border border-slate-200 rounded-xl text-xs">
                            <button @click="salvarParticipante()" class="flex-1 px-3 py-2 bg-[#2F5D50] text-white rounded-xl text-xs font-bold">Adicionar</button>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5 max-h-32 overflow-y-auto">
                        <template x-for="p in admin.participantes" :key="p.id">
                            <span class="px-2.5 py-1 rounded-lg bg-slate-100 text-xs font-semibold text-slate-700" x-text="p.nome_exibicao + ' (' + (p.equipe_nome || 'Geral') + ')'"></span>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Metas do Round -->
            <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                <div class="flex flex-wrap justify-between items-center gap-2 mb-3">
                    <h3 class="text-sm font-extrabold text-slate-800">3. Metas do Round (<span x-text="'Mês ' + filtros.mes + '/' + filtros.ano"></span>)</h3>
                    <div class="flex gap-2">
                        <button @click="salvarMetas()" class="px-4 py-2 bg-[#2F5D50] text-white rounded-xl text-xs font-bold"><i class="fa-solid fa-floppy-disk mr-1"></i> Salvar Metas</button>
                        <button @click="fecharRound()" class="px-4 py-2 bg-amber-600 text-white rounded-xl text-xs font-bold"><i class="fa-solid fa-lock mr-1"></i> Fechar Round & Gerar PINs</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-100">
                                <th class="py-2">Participante</th>
                                <th class="py-2">Meta Faturamento (R$)</th>
                                <th class="py-2">Carteira Ativa (piso 60)</th>
                                <th class="py-2">Observação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="m in adminMetas" :key="m.idparticipante">
                                <tr class="border-b border-slate-50">
                                    <td class="py-2 font-bold text-slate-800" x-text="m.nome_exibicao"></td>
                                    <td class="py-2"><input type="number" step="0.01" x-model.number="m.meta_faturamento" class="w-40 px-2 py-1.5 border border-slate-200 rounded-lg"></td>
                                    <td class="py-2"><input type="number" x-model.number="m.carteira_ativa" class="w-28 px-2 py-1.5 border border-slate-200 rounded-lg"></td>
                                    <td class="py-2"><input type="text" x-model="m.observacao" class="w-full px-2 py-1.5 border border-slate-200 rounded-lg"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Desafios do Mes + Pontos Extras -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-extrabold text-slate-800 mb-3">4. Configurar Desafios do Round</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                        <select x-model.number="formDesafio.numero_desafio" class="px-3 py-2 border border-slate-200 rounded-xl">
                            <option value="1">Desafio 01 - KIMONO (15%)</option>
                            <option value="2">Desafio 02 - ARMLOCK (20%)</option>
                            <option value="3">Desafio 03 - RNC Reativação (25%)</option>
                        </select>
                        <input type="number" step="0.5" x-model.number="formDesafio.meta_percentual" placeholder="% Meta" class="px-3 py-2 border border-slate-200 rounded-xl">
                    </div>
                    <input type="text" x-model="formDesafio.titulo" placeholder="Título do desafio" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs mb-2">
                    <input type="text" x-model="formDesafio.itens_csv" placeholder="IDs dos itens foco separados por vírgula (opcional para RNC)" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs mb-3">
                    <button @click="salvarDesafio()" class="px-4 py-2 bg-[#2F5D50] text-white rounded-xl text-xs font-bold">Salvar Desafio</button>
                </div>

                <div class="bg-white rounded-3xl p-5 border border-slate-200 shadow-sm">
                    <h3 class="text-sm font-extrabold text-slate-800 mb-3">5. Lançar Ponto Extra (+5) ou Ajuste / Devolução</h3>
                    <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                        <select x-model.number="formExtra.idparticipante" class="px-3 py-2 border border-slate-200 rounded-xl">
                            <option value="">Participante...</option>
                            <template x-for="p in admin.participantes" :key="p.id">
                                <option :value="p.id" x-text="p.nome_exibicao"></option>
                            </template>
                        </select>
                        <select x-model="formExtra.tipo" class="px-3 py-2 border border-slate-200 rounded-xl">
                            <option value="EXTRA_5">Ponto Extra (+5)</option>
                            <option value="AJUSTE_PONTOS">Ajuste de Pontos</option>
                            <option value="AUDITORIA_DEVOLUCAO">Auditoria Devolução</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs mb-2">
                        <input type="number" x-model.number="formExtra.pontos" placeholder="Pontos (ex: 5)" class="px-3 py-2 border border-slate-200 rounded-xl">
                        <input type="number" step="0.01" x-model.number="formExtra.valor_ajuste" placeholder="Ajuste R$ (opcional)" class="px-3 py-2 border border-slate-200 rounded-xl">
                    </div>
                    <input type="text" x-model="formExtra.motivo" placeholder="Motivo / justificativa" class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs mb-3">
                    <button @click="lancarExtra()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-xs font-bold">Registrar Lançamento</button>
                </div>
            </div>
        </div>
    </template>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
