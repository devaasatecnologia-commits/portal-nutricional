<?php
// ======================================================================
// MODULO FROTA - GESTAO DE CARGAS (PAINEL DO GESTOR)
// ======================================================================

$pageTitle = 'Gestão de Cargas | Frota | Nutricional';
$version = time();

// ================================================================
// HEADER E CSS
// ================================================================
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/frota.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/acerto-embarque.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/gestao-cargas.css?v=' . $version . '">
';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/portal/modules/frota/assets/gestao-cargas.js?v=' . $version . '"></script>
';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<!-- CONTEÚDO DO MÓDULO -->
<div class="max-w-full mx-auto px-4 lg:px-6 py-4" style="background: var(--nutri-bg); min-height: 100vh;">
    
    <!-- HEADER -->
    <div class="bg-gradient-to-r from-[#1a3c34] to-[#2d5a4e] rounded-3xl p-6 lg:p-7 mb-6 shadow-xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5">
            <div class="flex items-center gap-4">
                <a href="/portal/modules/frota/embarques.php" class="flex w-10 h-10 rounded-xl items-center justify-center transition-colors no-underline bg-white/20 hover:bg-white/30">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div class="hero-icon-badge">
                    <i class="fa-solid fa-clipboard-list text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                        Gestão de Cargas
                        <span class="hero-live-dot" title="Atualizado em tempo real"></span>
                    </h1>
                    <p class="text-emerald-200/80 text-sm flex items-center gap-2 mt-0.5">
                        Análise de entregas, problemas e performance dos motoristas
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <div class="hero-stat-chip highlight">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="total-problemas">0</span>
                    <span class="hero-stat-label">problemas</span>
                </div>
                <div class="hero-stat-chip">
                    <i class="fa-solid fa-check-circle"></i>
                    <span id="total-resolvidos">0</span>
                    <span class="hero-stat-label">resolvidos</span>
                </div>
                <button class="hero-refresh-btn" onclick="carregarDados()" title="Atualizar dados">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button class="theme-toggle theme-toggle-inline" onclick="toggleTheme()" title="Alternar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
        <div class="gold-accent-line"></div>
    </div>

    <!-- ================================================================
       FILTROS RÁPIDOS
    ================================================================ -->
    <div class="quick-filters" id="quick-filters">
        <button type="button" class="quick-filter-pill active" data-filtro="todos" onclick="aplicarFiltro('todos', this)">
            <i class="fa-solid fa-layer-group"></i> Todos
        </button>
        <button type="button" class="quick-filter-pill" data-filtro="pendente" onclick="aplicarFiltro('pendente', this)">
            <i class="fa-regular fa-clock"></i> Pendentes
        </button>
        <button type="button" class="quick-filter-pill" data-filtro="em_analise" onclick="aplicarFiltro('em_analise', this)">
            <i class="fa-solid fa-magnifying-glass"></i> Em Análise
        </button>
        <button type="button" class="quick-filter-pill" data-filtro="resolvido" onclick="aplicarFiltro('resolvido', this)">
            <i class="fa-solid fa-check-circle"></i> Resolvidos
        </button>
        <button type="button" class="quick-filter-pill" data-filtro="cancelado" onclick="aplicarFiltro('cancelado', this)">
            <i class="fa-solid fa-ban"></i> Cancelados
        </button>
    </div>

    <!-- ================================================================
       KPI CARDS
    ================================================================ -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6" id="kpi-cards">
        <!-- Gerado via JavaScript -->
    </div>

    <div class="section-card operational-overview mb-6">
        <div class="section-header flex justify-between items-center flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <div class="section-icon-badge"><i class="fa-solid fa-gauge-high"></i></div>
                <div><span class="font-bold">Visão operacional</span><span class="text-xs text-slate-400 block">Acompanhamento da operação em tempo real</span></div>
            </div>
            <span class="live-caption"><span></span> Atualizado automaticamente</span>
        </div>
        <div class="operational-kpis" id="operational-kpis"><div class="operational-loading">Carregando indicadores...</div></div>
    </div>

    <!-- ================================================================
       TABELA DE PROBLEMAS
    ================================================================ -->
    <div class="section-card">
        <div class="section-header flex justify-between items-center flex-wrap gap-2">
            <div class="flex items-center gap-3">
                <div class="section-icon-badge">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <div>
                    <span class="font-bold text-[#1a3c34]">Problemas de Entregas</span>
                    <span class="text-xs text-slate-400 block" id="info-registros">Carregando...</span>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <button class="btn-secondary-nutri text-sm py-1.5 px-4" onclick="exportarCSV()">
                    <i class="fa-solid fa-file-export"></i> Exportar CSV
                </button>
            </div>
        </div>
        <div class="section-body p-0 overflow-x-auto">
            <table class="table-frota w-full" id="tabela-problemas">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 45px;">#</th>
                        <th>Entrega</th>
                        <th>Cliente</th>
                        <th>Motorista</th>
                        <th>Problema</th>
                        <th class="text-center">Qtd</th>
                        <th class="text-center">Valor</th>
                        <th class="text-center">Prioridade</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 120px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-problemas">
                    <tr>
                        <td colspan="10" class="text-center py-8">
                            <div class="flex flex-col items-center gap-2">
                                <div class="skeleton skeleton-title mx-auto"></div>
                                <div class="skeleton skeleton-text w-48 mx-auto"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <!-- Paginação -->
        <div class="section-body border-t border-slate-200 flex justify-between items-center flex-wrap gap-2 py-3 px-4">
            <span class="text-sm text-slate-500" id="info-paginacao">Carregando...</span>
            <div class="flex gap-1">
                <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50" 
                        id="btn-anterior" onclick="mudarPagina('anterior')">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <span class="px-3 py-1.5 text-sm font-bold text-slate-600" id="pagina-atual">1</span>
                <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50" 
                        id="btn-proximo" onclick="mudarPagina('proximo')">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   MODAL: ANÁLISE DA ENTREGA
=============================================================== -->
<div class="modal fade" id="modalAnalise" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-clipboard-check mr-2"></i> Análise da Entrega 
                    <span id="analise-numero" class="font-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="analise-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-success-nutri" onclick="resolverProblema()" id="btn-resolver-problema">
                    <i class="fa-solid fa-check mr-2"></i> Resolver Problema
                </button>
                <button type="button" class="btn-primary-nutri" onclick="adicionarAnalise()" id="btn-adicionar-analise">
                    <i class="fa-solid fa-pen mr-2"></i> Adicionar Análise
                </button>
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../../estrutura/footer.php';
?>