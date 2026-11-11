<?php
$pageTitle = 'Análise de Limite de Crédito | Nutricional';
$moduleJs = 'financeiro-limite-credito.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  .lim-wrap { font-family: "Plus Jakarta Sans", system-ui, sans-serif; }
  .lim-hero { background: linear-gradient(135deg, #064e3b 0%, #065f46 50%, #047857 100%); }
  .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
  .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
  .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 999px; }
  .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="lim-wrap max-w-7xl mx-auto px-3 sm:px-6 pt-3 pb-12" x-data="limiteCreditoApp()" x-init="init()">

    <!-- HERO HEADER -->
    <div class="lim-hero rounded-3xl p-5 sm:p-7 text-white shadow-xl mb-5 border border-emerald-800/40">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <a href="<?= $appBase ?>/portal/" class="w-11 h-11 rounded-2xl bg-white/10 hover:bg-white/20 flex items-center justify-center text-white no-underline transition shadow-sm border border-white/10" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-emerald-500/30 text-emerald-200 border border-emerald-400/30 flex items-center gap-1.5">
                            <i class="fa-solid fa-scale-balanced text-amber-300"></i> Política de Crédito Automatizada
                        </span>
                        <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-white/10 text-white border border-white/20 flex items-center gap-1.5">
                            <i class="fa-solid fa-check text-emerald-400"></i> DOX #6 Integrado
                        </span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black tracking-tight mt-1 flex items-center gap-2">
                        Análise & Alteração de Limite de Crédito
                    </h1>
                    <p class="text-xs sm:text-sm text-emerald-100 opacity-90 mt-0.5 max-w-2xl">
                        Recálculo automatizado com base na média de vendas por boleto dos últimos 6 meses, histórico de pontualidade e piso mínimo de R$ 200,00.
                    </p>
                </div>
            </div>

            <!-- Botões de Ação Superior -->
            <div class="flex items-center gap-2.5 flex-wrap">
                <button type="button" @click="abrirModalEmail()"
                        class="px-4 py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold flex items-center gap-2 transition border border-white/15 shadow-sm active:scale-95">
                    <i class="fa-solid fa-envelope-open-text text-amber-300"></i>
                    <span>Relatório por E-mail</span>
                </button>

                <button type="button" @click="aplicarLimites('todos')" :disabled="aplicando || kpis.total_com_alteracao === 0"
                        class="px-5 py-2.5 rounded-xl bg-emerald-400 hover:bg-emerald-300 text-emerald-950 font-black text-xs flex items-center gap-2 transition shadow-lg shadow-emerald-950/20 active:scale-95 disabled:opacity-50">
                    <i class="fa-solid fa-bolt" x-show="!aplicando"></i>
                    <i class="fa-solid fa-spinner fa-spin" x-show="aplicando"></i>
                    <span>Atualizar Todos no ERP</span>
                </button>
            </div>
        </div>
    </div>

    <!-- KPIS EXECUTIVOS -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <!-- 1. Total com Alteração -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Sugestões de Alteração</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-slate-800" x-text="kpis.total_com_alteracao || 0">0</span>
                    <span class="text-[11px] font-bold text-slate-400">clientes</span>
                </div>
                <span class="text-[10px] text-slate-500 font-semibold block mt-1" x-text="`De ${kpis.total_analisados || 0} analisados`">De 0 analisados</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-user-pen"></i>
            </div>
        </div>

        <!-- 2. Aumentos de Limite (+60%) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-emerald-600 uppercase tracking-wider block mb-0.5">Expansão de Crédito (+60%)</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-emerald-600" x-text="kpis.total_aumentos || 0">0</span>
                    <span class="text-[11px] font-bold text-emerald-600/70">clientes bons</span>
                </div>
                <span class="text-[10px] text-emerald-700 font-bold block mt-1" x-text="`+${formatarMoeda(kpis.impacto_aumentos)}`">+R$ 0,00</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-arrow-trend-up"></i>
            </div>
        </div>

        <!-- 3. Reduções de Limite (-30%) -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-rose-600 uppercase tracking-wider block mb-0.5">Contração por Atraso (-30%)</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-3xl font-black text-rose-600" x-text="kpis.total_reducoes || 0">0</span>
                    <span class="text-[11px] font-bold text-rose-600/70">clientes c/ risco</span>
                </div>
                <span class="text-[10px] text-rose-700 font-bold block mt-1" x-text="`-${formatarMoeda(kpis.impacto_reducoes)}`">-R$ 0,00</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-arrow-trend-down"></i>
            </div>
        </div>

        <!-- 4. Impacto Financeiro Líquido -->
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm flex items-center justify-between">
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Variação Líquida de Limite</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-black" :class="parseFloat(kpis.impacto_liquido || 0) >= 0 ? 'text-emerald-600' : 'text-rose-600'" x-text="formatarMoeda(kpis.impacto_liquido)">R$ 0,00</span>
                </div>
                <span class="text-[10px] text-slate-400 font-medium block mt-1">Saldo acumulado da carteira</span>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-slate-50 text-slate-600 flex items-center justify-center text-xl">
                <i class="fa-solid fa-coins"></i>
            </div>
        </div>
    </div>

    <!-- BARRA DE CONTROLE: BUSCA + FILTROS -->
    <div class="bg-white rounded-2xl p-4 border border-slate-100 shadow-sm mb-5 space-y-3">
        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3">
            <!-- Busca Textual -->
            <div class="relative flex-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                <input type="text" x-model="filtros.busca" @input.debounce.300ms="carregarDados(1)" placeholder="Buscar cliente por razão, fantasia ou código..."
                       class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-emerald-500 focus:bg-white transition">
            </div>

            <!-- Filtros em Selects -->
            <div class="flex flex-wrap items-center gap-2">
                <!-- Filtro Tipo de Alteração -->
                <select x-model="filtros.tipo_alteracao" @change="carregarDados(1)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-emerald-500">
                    <option value="todos">Todas as Diferenças</option>
                    <option value="aumento">🟢 Apenas Aumentos (+60%)</option>
                    <option value="reducao">🔴 Apenas Reduções (-30%)</option>
                    <option value="sem_alteracao">⚪ Sem Alteração</option>
                </select>

                <!-- Filtro Classificação Pagamento -->
                <select x-model="filtros.statuspagto" @change="carregarDados(1)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-700 focus:outline-none focus:border-emerald-500">
                    <option value="todos">Todas Classificações</option>
                    <option value="BOM EXCELENTE">BOM EXCELENTE (Atraso &le; 3d)</option>
                    <option value="BOM">BOM (Atraso &le; 5d)</option>
                    <option value="REGULAR">REGULAR (Atraso &le; 10d)</option>
                    <option value="RUIM">RUIM (Atraso &gt; 10d ou S/ Histórico)</option>
                </select>

                <!-- Botão Aplicar Selecionados (Aparece se houver seleção) -->
                <button type="button" x-show="selecionados.length > 0" @click="aplicarLimites('selecionados')"
                        class="px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-black text-xs flex items-center gap-1.5 transition shadow-sm active:scale-95" style="display: none;">
                    <i class="fa-solid fa-check-double"></i>
                    <span x-text="`Atualizar (${selecionados.length}) no ERP`">Atualizar</span>
                </button>
            </div>
        </div>
    </div>

    <!-- TABELA PRINCIPAL DE ANÁLISE -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-5">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/80 border-b border-slate-100 text-[10px] font-black uppercase text-slate-400 tracking-wider">
                        <th class="py-3 px-4 w-10 text-center">
                            <input type="checkbox" x-model="selecionarTodos" @change="toggleSelecionarTodos()" class="w-4 h-4 text-emerald-600 rounded">
                        </th>
                        <th class="py-3 px-4 w-20">Código</th>
                        <th class="py-3 px-4">Razão Social / Fantasia</th>
                        <th class="py-3 px-4 text-right">Limite Atual</th>
                        <th class="py-3 px-4 text-right">Novo Limite</th>
                        <th class="py-3 px-4 text-right">Variação</th>
                        <th class="py-3 px-4 text-center">Classificação</th>
                        <th class="py-3 px-4 text-right">Média 6 Meses</th>
                        <th class="py-3 px-4 text-center">Maior Atraso</th>
                        <th class="py-3 px-4 text-center">Prazo Médio</th>
                        <th class="py-3 px-4 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-xs font-medium text-slate-600">
                    <template x-for="item in itens" :key="item.cdcliente">
                        <tr class="hover:bg-slate-50/80 transition" :class="selecionados.includes(parseInt(item.cdcliente)) ? 'bg-emerald-50/40' : ''">
                            <td class="py-3 px-4 text-center">
                                <input type="checkbox" :value="parseInt(item.cdcliente)" :checked="selecionados.includes(parseInt(item.cdcliente))" @change="toggleItem(item.cdcliente)" class="w-4 h-4 text-emerald-600 rounded">
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-slate-700" x-text="item.cdcliente">0</td>
                            <td class="py-3 px-4">
                                <span class="font-bold text-slate-800 block line-clamp-1" x-text="item.cliente">Cliente</span>
                                <span class="text-[10px] text-slate-400" x-text="`Atividade: ${item.status_atividade || 'N/A'}`">Atividade</span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-600" x-text="formatarMoeda(item.limiteanterior)">R$ 0,00</td>
                            <td class="py-3 px-4 text-right font-black text-slate-900" x-text="formatarMoeda(item.novolimite)">R$ 0,00</td>
                            <td class="py-3 px-4 text-right" :class="badgeDiferenca(item.diferenca)">
                                <span x-text="(parseFloat(item.diferenca) >= 0 ? '+' : '') + formatarMoeda(item.diferenca)">R$ 0,00</span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded border inline-block" :class="badgeStatus(item.statuspagto)" x-text="item.statuspagto">BOM</span>
                            </td>
                            <td class="py-3 px-4 text-right font-mono text-slate-500" x-text="formatarMoeda(item.media_mensal_vendas)">R$ 0,00</td>
                            <td class="py-3 px-4 text-center">
                                <span class="text-xs font-bold" :class="parseInt(item.maior_atraso) > 5 ? 'text-rose-600 font-black' : 'text-slate-600'" x-text="`${item.maior_atraso}d`">0d</span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono text-slate-400" x-text="`${item.media_dias_prazo || 0}d`">0d</td>
                            <td class="py-3 px-4 text-center">
                                <button type="button" @click="selecionados = [parseInt(item.cdcliente)]; aplicarLimites('selecionados')"
                                        class="px-2 py-1 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 font-bold text-[11px] transition">
                                    Aplicar
                                </button>
                            </td>
                        </tr>
                    </template>
                    <tr x-show="itens.length === 0 && !carregando">
                        <td colspan="11" class="py-8 text-center text-xs text-slate-400">
                            Nenhum cliente com os filtros selecionados.
                        </td>
                    </tr>
                    <tr x-show="carregando">
                        <td colspan="11" class="py-8 text-center text-xs text-slate-400">
                            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando clientes e calculando análise financeira...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PAGINAÇÃO -->
        <div class="p-4 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <span class="text-slate-500 font-medium" x-text="`Exibindo página ${paginacao.page} de ${paginacao.total_paginas} (${paginacao.total} registros)`">
                Exibindo página 1 de 1
            </span>

            <div class="flex items-center gap-1.5">
                <button type="button" @click="carregarDados(paginacao.page - 1)" :disabled="paginacao.page <= 1"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 disabled:opacity-40 transition">
                    <i class="fa-solid fa-chevron-left"></i> Anterior
                </button>
                <button type="button" @click="carregarDados(paginacao.page + 1)" :disabled="paginacao.page >= paginacao.total_paginas"
                        class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 disabled:opacity-40 transition">
                    Próxima <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- MODAL DE PRÉ-VISUALIZAÇÃO & DISPARO DE E-MAIL -->
    <div x-show="modalEmailAberto" class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-xs" style="display: none;">
        <div class="bg-white rounded-3xl max-w-4xl w-full p-6 shadow-2xl border border-slate-100 flex flex-col max-h-[92vh] overflow-hidden" @click.outside="modalEmailAberto = false">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                        <i class="fa-solid fa-envelope-open-text text-sm"></i>
                    </div>
                    <div>
                        <h3 class="text-base font-black text-slate-800">Relatório Executivo de Limites de Crédito</h3>
                        <p class="text-xs text-slate-400">Pré-visualização do e-mail automático gerado para a Controladoria e Financeiro</p>
                    </div>
                </div>
                <button @click="modalEmailAberto = false" class="text-slate-400 hover:text-slate-600 w-8 h-8 rounded-lg flex items-center justify-center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Destinatário e Assunto -->
            <div class="py-3 grid grid-cols-1 sm:grid-cols-2 gap-3 border-b border-slate-100">
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">E-mail Destinatário</label>
                    <input type="email" x-model="emailDestino" placeholder="ex: financeiro@nutricionalbr.com"
                           class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-emerald-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Assunto da Mensagem</label>
                    <input type="text" x-model="emailAssunto"
                           class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-semibold text-slate-700 focus:outline-none focus:border-emerald-500">
                </div>
            </div>

            <!-- Corpo HTML Preview -->
            <div class="py-4 overflow-y-auto custom-scrollbar flex-1 pr-1">
                <div x-show="carregandoPreview" class="text-center py-12 text-slate-400 text-xs">
                    <i class="fa-solid fa-spinner fa-spin text-lg mb-2 block"></i> Gerando visualização do relatório...
                </div>
                <div x-show="!carregandoPreview" class="border border-slate-200 rounded-2xl p-4 bg-slate-50/50">
                    <div x-html="emailHtmlPreview"></div>
                </div>
            </div>

            <!-- Rodapé do Modal -->
            <div class="pt-4 border-t border-slate-100 flex items-center justify-between gap-2.5">
                <button type="button" @click="copiarHtmlEmail()" class="px-3.5 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 flex items-center gap-1.5 transition">
                    <i class="fa-solid fa-copy"></i>
                    <span>Copiar HTML</span>
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" @click="modalEmailAberto = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-500 hover:bg-slate-100 transition">
                        Fechar
                    </button>
                    <button type="button" @click="dispararEmail()" :disabled="enviandoEmail"
                            class="px-5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black flex items-center gap-2 transition shadow-md shadow-emerald-600/20 disabled:opacity-50">
                        <i class="fa-solid fa-paper-plane" x-show="!enviandoEmail"></i>
                        <i class="fa-solid fa-spinner fa-spin" x-show="enviandoEmail"></i>
                        <span>Enviar por E-mail</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- SCRIPT DO MÓDULO -->
<script src="<?= $appBase ?>/portal/assets/js/<?= $moduleJs ?>?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
