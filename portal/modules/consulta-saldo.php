<?php
$pageTitle = 'NUTRICIONAL | ESTOQUE SALDO DISPONÍVEL';
$moduleJs = 'consulta-saldo.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? 0 ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<!-- HEADER MOBILE -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline text-slate-700" data-tooltip="Voltar ao Início|Retorna para a tela principal de módulos do Portal">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome text-[#375a4b]">CONSULTA SALDO</span>
        </div>
        <div class="clock font-mono text-sm font-bold text-[#375a4b]" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- Header Desktop -->
    <div class="glass rounded-3xl p-5 mb-5 flex justify-between items-center shadow-sm bg-white border border-slate-100">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline text-slate-600 hover:bg-slate-100" data-tooltip="Voltar ao Portal|Retornar à página inicial de módulos">
                <i class="fa-solid fa-arrow-left text-base"></i>
            </a>
            <div class="w-12 h-12 bg-emerald-50 text-[#375a4b] rounded-2xl flex items-center justify-center shadow-xs">
                <i class="fa-solid fa-scale-balanced text-xl"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg font-black text-slate-800 leading-tight">ESTOQUE SALDO DISPONÍVEL</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 uppercase tracking-wider" data-tooltip="Segurança ERP|Integrado com cálculo de estoque físico, reservas de clientes e log de auditoria">Seguro ERP</span>
                </div>
                <span class="text-xs text-slate-400 font-semibold">Análise de Estoque por Embarque • Simulação e Edição Preventiva de Pedidos</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black text-[#375a4b]" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs text-slate-400 font-semibold" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- Barra de Filtros e Ferramentas -->
    <div class="bg-white p-5 rounded-3xl shadow-sm border border-slate-100 mb-5">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <!-- Seleção de Embarque -->
            <div class="md:col-span-5">
                <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1.5 flex items-center justify-between">
                    <span><i class="fa-solid fa-truck-moving text-emerald-600 mr-1.5"></i> Embarque Aberto</span>
                    <button type="button" onclick="carregarEmbarques()" class="text-emerald-700 hover:text-emerald-800 text-[11px] font-bold inline-flex items-center gap-1 cursor-pointer" data-tooltip="Atualizar Lista|Recarrega os embarques ativos no ERP">
                        <i class="fa-solid fa-rotate text-[10px]"></i> Atualizar
                    </button>
                </label>
                <div class="relative">
                    <select id="selectEmbarque" onchange="carregarSaldos()" 
                            class="w-full p-3 pr-9 border-2 border-slate-200 rounded-2xl text-xs sm:text-sm font-bold text-slate-700 bg-white cursor-pointer hover:border-[#375a4b] focus:border-[#375a4b] transition-all outline-hidden"
                            data-tooltip="Selecionar Embarque|Escolha o embarque ativo para analisar o saldo de cada produto solicitado">
                        <option value="">Carregando embarques disponíveis...</option>
                    </select>
                </div>
            </div>

            <!-- Filtro de Visualização (Apenas Negativos / Todos) -->
            <div class="md:col-span-3">
                <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1.5">
                    <i class="fa-solid fa-filter text-emerald-600 mr-1.5"></i> Exibição
                </label>
                <div class="flex rounded-2xl bg-slate-100 p-1 border border-slate-200 text-xs font-bold text-slate-600">
                    <button type="button" id="btnFiltroCriticos" onclick="alternarFiltroModo(true)" 
                            class="flex-1 py-2 px-2 rounded-xl bg-white text-[#375a4b] shadow-xs transition text-center font-black"
                            data-tooltip="Apenas Falta|Exibe apenas os itens cujo saldo final projetado é negativo (insuficiente)">
                        <i class="fa-solid fa-triangle-exclamation text-rose-500 mr-1"></i> Faltantes
                    </button>
                    <button type="button" id="btnFiltroTodos" onclick="alternarFiltroModo(false)" 
                            class="flex-1 py-2 px-2 rounded-xl text-slate-600 hover:text-slate-900 transition text-center"
                            data-tooltip="Todos os Itens|Exibe todos os produtos solicitados no embarque, inclusive os com saldo positivo">
                        <i class="fa-solid fa-list-ul mr-1"></i> Todos
                    </button>
                </div>
            </div>

            <!-- Busca Textual Instantânea -->
            <div class="md:col-span-4 flex items-center gap-2">
                <div class="relative flex-1">
                    <input type="text" id="filtroBuscaItem" onkeyup="filtrarTabelaLocal()" placeholder="Buscar por REF ou nome do item..." 
                           class="w-full pl-9 pr-8 py-3 border-2 border-slate-200 rounded-2xl text-xs sm:text-sm font-semibold bg-white hover:border-[#375a4b] focus:border-[#375a4b] transition-all outline-hidden"
                           data-tooltip="Pesquisa Rápida|Filtra os itens da tabela em tempo real pelo código de referência ou descrição">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <button type="button" onclick="limparBuscaItem()" id="btnLimparBuscaItem" class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs cursor-pointer" data-tooltip="Limpar Busca|Restaura a exibição de todos os itens">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>

                <!-- Botão PDF -->
                <button type="button" onclick="exportarPDF()" 
                        class="px-4 py-3 bg-white border-2 border-[#375a4b] text-[#375a4b] rounded-2xl font-black text-xs sm:text-sm hover:bg-[#375a4b] hover:text-white transition-all flex items-center gap-2 cursor-pointer shadow-xs"
                        data-tooltip="Exportar Relatório PDF|Gera relatório profissional com dados completos do embarque e pedidos detalhados de cada item">
                    <i class="fa-solid fa-file-pdf text-rose-600 text-base"></i>
                    <span class="hidden sm:inline">PDF</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Card de Informações do Embarque -->
    <div class="bg-gradient-to-r from-[#2c493c] via-[#375a4b] to-[#466f5e] p-5 rounded-3xl shadow-lg mb-5 text-white" id="cardEmbarque" style="display:none;">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-3 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-2xl flex items-center justify-center">
                    <i class="fa-solid fa-truck-fast text-white text-lg"></i>
                </div>
                <div>
                    <h3 class="text-white font-black text-base sm:text-lg leading-tight flex items-center gap-2">
                        <span>Embarque <span id="infoEmbarque">---</span></span>
                        <span id="infoStatusEmbarque" class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white/20 text-white border border-white/30">ATIVO</span>
                    </h3>
                    <p class="text-xs text-white/70 font-semibold">Parâmetros logísticos consolidados do ERP</p>
                </div>
            </div>
            <div class="text-xs font-semibold text-white/80 flex items-center gap-1.5">
                <i class="fa-regular fa-clock"></i>
                <span>Data: <b id="infoData" class="text-white">---</b></span>
            </div>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm border border-white/10" data-tooltip="Rota do Embarque|Itinerário programado cadastrado no ERP">
                <span class="text-white/70 text-[10px] uppercase font-black block mb-1">Rota / Itinerário</span>
                <span class="text-white font-bold text-xs sm:text-sm truncate block" id="infoRota">---</span>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm border border-white/10" data-tooltip="Motorista / Entregador|Responsável pela condução da carga">
                <span class="text-white/70 text-[10px] uppercase font-black block mb-1">Entregador</span>
                <span class="text-white font-bold text-xs sm:text-sm truncate block" id="infoEntregador">---</span>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm border border-white/10" data-tooltip="Placa do Veículo|Identificação do caminhão alocado ao embarque">
                <span class="text-white/70 text-[10px] uppercase font-black block mb-1">Placa</span>
                <span class="text-white font-black font-mono text-xs sm:text-sm truncate block" id="infoPlaca">---</span>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm border border-white/10" data-tooltip="Transportadora|Empresa parceira de transporte rodoviário">
                <span class="text-white/70 text-[10px] uppercase font-black block mb-1">Transportadora</span>
                <span class="text-white font-bold text-xs sm:text-sm truncate block" id="infoTransportadora">---</span>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm border border-white/10" data-tooltip="Total de Pedidos|Quantidade de pedidos de clientes vinculados neste embarque">
                <span class="text-white/70 text-[10px] uppercase font-black block mb-1">Pedidos Vinculados</span>
                <span class="text-white font-black text-sm sm:text-base" id="infoQtPedidos">---</span>
            </div>
        </div>
        
        <div class="grid grid-cols-2 sm:grid-cols-2 gap-3 mt-3 pt-3 border-t border-white/10">
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm flex items-center justify-between" data-tooltip="Peso Bruto Total|Soma do peso de todos os itens ativos do embarque">
                <span class="text-white/70 text-xs uppercase font-bold">Peso Bruto Total:</span>
                <span class="text-white font-black text-base sm:text-lg" id="infoPesoBruto">--- kg</span>
            </div>
            <div class="bg-white/10 rounded-2xl p-3 backdrop-blur-sm flex items-center justify-between" data-tooltip="Valor Total do Embarque|Soma financeira líquida de todos os pedidos deste embarque">
                <span class="text-white/70 text-xs uppercase font-bold">Valor Total Geral:</span>
                <span class="text-emerald-300 font-black text-base sm:text-lg" id="infoValorTotal">R$ ---</span>
            </div>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5" id="cardsResumo" style="display:none;">
        <div class="bg-white p-4 rounded-3xl border border-slate-100 shadow-xs hover:shadow-md transition-all flex items-center gap-3" data-tooltip="Total de Itens Distintos|Quantidade de produtos diferentes solicitados neste embarque">
            <div class="w-12 h-12 bg-slate-100 text-slate-700 rounded-2xl flex items-center justify-center flex-none text-lg">
                <i class="fa-solid fa-boxes-stacked"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-[10px] uppercase font-black text-slate-400 block tracking-wider truncate">Total de Itens</span>
                <span class="text-2xl font-black text-slate-800" id="totalItens">0</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-3xl border border-slate-100 shadow-xs hover:shadow-md transition-all flex items-center gap-3" data-tooltip="Estoque Suficiente|Itens cujo saldo disponível atende 100% da demanda do embarque">
            <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center flex-none text-lg">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-[10px] uppercase font-black text-slate-400 block tracking-wider truncate">Estoque OK</span>
                <span class="text-2xl font-black text-emerald-600" id="itensOk">0</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-3xl border border-slate-100 shadow-xs hover:shadow-md transition-all flex items-center gap-3" data-tooltip="Estoque Parcial|Há estoque físico, porém insuficiente para cobrir todo o embarque">
            <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center flex-none text-lg">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-[10px] uppercase font-black text-slate-400 block tracking-wider truncate">Estoque Baixo</span>
                <span class="text-2xl font-black text-amber-600" id="itensAlerta">0</span>
            </div>
        </div>

        <div class="bg-white p-4 rounded-3xl border border-slate-100 shadow-xs hover:shadow-md transition-all flex items-center gap-3" data-tooltip="Sem Estoque Físico|Estoque zerado no almoxarifado; o embarque não pode sair sem ajuste">
            <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center flex-none text-lg">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-[10px] uppercase font-black text-slate-400 block tracking-wider truncate">Sem Estoque</span>
                <span class="text-2xl font-black text-rose-600" id="itensCritico">0</span>
            </div>
        </div>
    </div>

    <!-- Tabela Principal de Saldos -->
    <div class="bg-white rounded-3xl border border-slate-100 overflow-hidden shadow-sm mb-6" id="tabelaContainer" style="display:none;">
        <div class="p-4 border-b border-slate-100 flex flex-wrap items-center justify-between gap-3 bg-slate-50/60">
            <div class="flex items-center gap-2">
                <h4 class="text-xs sm:text-sm font-black text-slate-800 uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-table-list text-emerald-700"></i> Comparativo de Saldos e Reservas
                </h4>
                <span id="badgeContadorTabela" class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-slate-200 text-slate-700">0 itens</span>
            </div>
            <div class="text-[11px] font-semibold text-slate-400 flex items-center gap-2">
                <i class="fa-solid fa-hand-pointer text-slate-400"></i>
                <span>Passe o mouse nos botões e cabeçalhos para ver as explicações</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full audit-table table-to-cards text-xs sm:text-sm">
                <thead class="bg-slate-100/80 border-b border-slate-200 text-slate-600">
                    <tr>
                        <th class="px-4 py-3.5 text-left text-[11px] font-black uppercase tracking-wider" data-tooltip="Código de Referência|Identificador alfanumérico cadastrado no catálogo do ERP">REF</th>
                        <th class="px-4 py-3.5 text-left text-[11px] font-black uppercase tracking-wider" data-tooltip="Descrição do Produto|Nome oficial do item no ERP">Produto</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Estoque Físico|Quantidade física total existente nas filiais 1 e 6">Estoque Físico</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Reservas Prioritárias|Quantidade reservada para clientes especiais contratados">Reserva</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Estoque Disponível|Estoque Físico menos a Reserva Prioritária">Disponível</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Quantidade no Embarque|Soma das quantidades pedidas por todos os clientes neste embarque">No Embarque</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Saldo Projetado|Estoque Disponível menos a Quantidade no Embarque (se negativo, falta mercadoria)">Saldo Projetado</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Status Logístico|Diagnóstico da situação do item para o embarque">Status</th>
                        <th class="px-4 py-3.5 text-center text-[11px] font-black uppercase tracking-wider" data-tooltip="Ações Disponíveis|Abrir detalhamento de pedidos para editar quantidade ou remover item">Ações</th>
                    </tr>
                </thead>
                <tbody id="tbodySaldos" class="divide-y divide-slate-100">
                    <tr>
                        <td colspan="9" class="text-center py-12 text-slate-400">
                            <i class="fa-solid fa-truck-ramp-box text-3xl mb-2 text-slate-300 block"></i>
                            <span class="font-bold">Selecione um embarque acima para analisar os saldos</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- =================================================== -->
<!-- MODAL: DETALHES DOS PEDIDOS DO ITEM                -->
<!-- =================================================== -->
<div id="modalPedido" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="fecharModalPedido()"></div>
        <div class="relative bg-white rounded-3xl max-w-4xl w-full max-h-[92vh] overflow-hidden shadow-2xl border border-slate-100 flex flex-col">
            <!-- Header do Modal -->
            <div class="bg-gradient-to-r from-[#2c493c] to-[#375a4b] px-6 py-4 flex items-center justify-between text-white flex-none">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-boxes-packing text-lg"></i>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-black" id="modalPedidoTitulo">
                            Detalhes do Pedido
                        </h3>
                        <p class="text-xs text-emerald-100 font-semibold" id="modalPedidoSubtitulo">
                            Gerencie os pedidos que contêm este produto no embarque
                        </p>
                    </div>
                </div>
                <button type="button" onclick="fecharModalPedido()" class="w-8 h-8 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition cursor-pointer" data-tooltip="Fechar Janela|Retorna para a listagem principal">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            <!-- Conteúdo Scrollável -->
            <div class="p-6 overflow-y-auto flex-1 space-y-4" id="modalPedidoConteudo">
                <!-- Preenchido via JavaScript -->
            </div>
        </div>
    </div>
</div>

<!-- =================================================== -->
<!-- TOOLTIP MODERNO FLUTUANTE (RICH HOVER POPOVER)       -->
<!-- =================================================== -->
<div id="modernTooltipSaldo" class="pointer-events-none fixed z-[9999] hidden max-w-xs rounded-2xl bg-slate-900/95 p-3.5 text-xs text-white shadow-2xl backdrop-blur-md border border-slate-700/60 transition-opacity duration-150">
    <div class="flex items-center justify-between gap-2 mb-1.5">
        <span id="tooltipSaldoBadge" class="text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50">AÇÃO</span>
        <i class="fa-solid fa-circle-info text-[10px] text-slate-400"></i>
    </div>
    <div id="tooltipSaldoTitle" class="font-black text-white text-xs mb-1">Título da Ação</div>
    <div id="tooltipSaldoDesc" class="text-slate-300 text-[11px] font-medium leading-relaxed">Explicação do item ao passar o mouse.</div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
