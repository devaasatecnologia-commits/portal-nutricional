<?php
// ======================================================================
// MODULO FROTA - ACERTO DE EMBARQUE (ADMINISTRATIVO)
// ======================================================================

$pageTitle = 'Acerto de Embarque | Gestão de Frotas | Nutricional';
$version = time();

// ================================================================
// HEADER E CSS
// ================================================================
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/frota.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/acerto-embarque.css?v=' . $version . '">
';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<!-- ================================================================
   HIDDEN INPUTS
   ================================================================ -->
<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? $_SESSION['idusuario'] ?? 0 ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? $_SESSION['username'] ?? 'Gestor' ?>">

<!-- ================================================================
   CONTEÚDO PRINCIPAL
   ================================================================ -->
<div class="max-w-full mx-auto px-4 lg:px-6 py-4" style="background: var(--acerto-bg); min-height: 100vh;">

    <!-- HEADER -->
    <div class="hero-acerto">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5">
            <div class="flex items-center gap-4">
                <a href="/portal/modules/frota/embarques.php" class="flex w-10 h-10 rounded-xl items-center justify-center transition-colors no-underline bg-white/20 hover:bg-white/30">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div class="hero-icon-badge">
                    <i class="fa-solid fa-file-signature text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                        Acerto de Embarque
                        <span class="hero-live-dot" title="Atualizado em tempo real"></span>
                    </h1>
                    <p class="text-emerald-200/80 text-sm flex items-center gap-2 mt-0.5">
                        Finalização administrativa com o motorista
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <div class="hero-stat-chip highlight">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span id="total-acertos">0</span>
                    <span class="hero-stat-label">pendentes</span>
                </div>
                <button class="hero-refresh-btn" onclick="carregarEmbarquesParaAcerto(true)" title="Atualizar dados">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button class="theme-toggle theme-toggle-inline" onclick="toggleTheme()" title="Alternar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
        <div class="gold-accent-line"></div>
    </div>


<div class="quick-filters" id="quick-filters">
    <button type="button" class="quick-filter-pill active" data-status="" onclick="aplicarFiltro('', this)">
        <i class="fa-solid fa-layer-group"></i> Todos
    </button>
    <button type="button" class="quick-filter-pill" data-status="planejado" onclick="aplicarFiltro('planejado', this)">
        <i class="fa-regular fa-clock"></i> Planejados
    </button>
    <button type="button" class="quick-filter-pill" data-status="em_andamento" onclick="aplicarFiltro('em_andamento', this)">
        <i class="fa-solid fa-truck-fast"></i> Em Andamento
    </button>
    <button type="button" class="quick-filter-pill" data-status="finalizado" onclick="aplicarFiltro('finalizado', this)">
        <i class="fa-solid fa-circle-check"></i> Finalizados
    </button>
    <button type="button" class="quick-filter-pill" data-status="problema" onclick="aplicarFiltro('problema', this)">
        <i class="fa-solid fa-triangle-exclamation"></i> Com Problemas
    </button>
</div>

    <!-- BARRA DE FERRAMENTAS -->
    <div class="section-card">
        <div class="section-body">
            <div class="toolbar-row">
                <div class="toolbar-field toolbar-field-grow">
                    <label class="form-label"><i class="fa-solid fa-magnifying-glass"></i> Buscar embarque</label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-hashtag input-icon"></i>
                        <input type="text" class="form-control" id="filtro-busca" placeholder="Nº, veículo, motorista..." onkeyup="buscarEmbarques()">
                        <button class="input-icon-btn" onclick="carregarEmbarquesParaAcerto(true)" title="Buscar">
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </div>
                <div class="toolbar-field">
                    <label class="form-label"><i class="fa-regular fa-calendar"></i> Data Início</label>
                    <input type="date" class="form-control" id="filtro-data-inicio" onchange="carregarEmbarquesParaAcerto()">
                </div>
                <div class="toolbar-field">
                    <label class="form-label"><i class="fa-regular fa-calendar"></i> Data Fim</label>
                    <input type="date" class="form-control" id="filtro-data-fim" onchange="carregarEmbarquesParaAcerto()">
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA DE EMBARQUES PARA ACERTO -->
    <div class="section-card">
        <div class="section-header">
            <div class="flex items-center gap-3">
                <div class="section-icon-badge">
                    <i class="fa-solid fa-clipboard-list"></i>
                </div>
                <div>
                    <span class="font-bold text-[#1a3c34]" style="color: var(--acerto-text);">Embarques para Acerto</span>
                    <span class="text-xs text-slate-400 block" id="info-paginacao">Carregando...</span>
                </div>
            </div>
            <div class="flex items-center gap-3 flex-wrap">
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-400 font-medium">Mostrar:</label>
                    <select id="limite-por-pagina" onchange="mudarLimite()" 
                        class="border border-slate-200 rounded-lg px-2 py-1 text-sm bg-white dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50" selected>50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="flex gap-1">
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" 
                        id="btn-anterior" onclick="mudarPagina('anterior')">
                        <i class="fa-solid fa-chevron-left"></i>
                    </button>
                    <span class="px-3 py-1.5 text-sm font-bold text-slate-600 dark:text-slate-300" id="pagina-atual">1</span>
                    <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" 
                        id="btn-proximo" onclick="mudarPagina('proximo')">
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="section-body p-0 overflow-x-auto">
            <table class="table-frota w-full" id="tabela-acerto">
                <thead>
                    <tr>
                        <th class="text-center" style="width: 45px;">#</th>
                        <th>Embarque</th>
                        <th>Veículo</th>
                        <th>Motorista</th>
                        <th class="text-center">Entregas</th>
                        <th class="text-center">Problemas</th>
                        <th class="text-center">Valor</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width: 180px;">Ações</th>
                    </tr>
                </thead>
                <tbody id="lista-embarques">
                    <tr>
                        <td colspan="9" class="text-center py-8">
                            <div class="flex flex-col items-center gap-2">
                                <div class="skeleton skeleton-title mx-auto"></div>
                                <div class="skeleton skeleton-text w-48 mx-auto"></div>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ================================================================
   MODAL: DETALHES DO ACERTO
   ================================================================ -->
<div class="modal" id="modalAcerto" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- HEADER -->
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-file-signature"></i> 
                    Acerto do Embarque <span id="acerto-numero" class="font-bold" style="color: #f6d365;"></span>
                    <span id="acerto-status-badge" class="ml-2" style="display: none;"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar">×</button>
            </div>
            
            <!-- BODY -->
            <div class="modal-body" id="acerto-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin text-3xl text-emerald-500"></i>
                    <p class="mt-3 text-slate-400">Carregando detalhes do embarque...</p>
                </div>
            </div>
            
            <!-- FOOTER -->
            <div class="modal-footer" id="acerto-footer">
                <button type="button" class="btn btn-primary-nutri" onclick="iniciarAcerto()" id="btn-iniciar-acerto">
                    <i class="fa-solid fa-play"></i> Iniciar Acerto
                </button>
                <button type="button" class="btn btn-success-nutri" onclick="finalizarAcerto()" id="btn-finalizar-acerto" style="display: none;">
                    <i class="fa-solid fa-check-double"></i> Finalizar Acerto
                </button>
                <button type="button" class="btn btn-danger-nutri" onclick="cancelarAcerto()" id="btn-cancelar-acerto" style="display: none;">
                    <i class="fa-solid fa-ban"></i> Cancelar
                </button>
                <button type="button" class="btn btn-secondary-nutri" data-bs-dismiss="modal">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   MODAL: CRIAR PEDIDO DE PROBLEMA
   ================================================================ -->
<div class="modal" id="modalPedidoProblema" tabindex="-1" style="display: none;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-plus-circle"></i>
                    Criar Pedido de <span id="problema-tipo-label">Faltante</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar">×</button>
            </div>
            <div class="modal-body">
                <form id="form-pedido-problema">
                    <input type="hidden" id="pp-acerto-id" value="">
                    <input type="hidden" id="pp-entrega-id" value="">
                    <input type="hidden" id="pp-tipo-problema" value="faltante">

                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" class="form-control" id="pp-cliente-nome" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motivo</label>
                        <select class="form-select" id="pp-motivo">
                            <option value="cliente_ausente">Cliente ausente</option>
                            <option value="endereco_incorreto">Endereço incorreto</option>
                            <option value="recusado">Cliente recusou</option>
                            <option value="avaria">Avaria no produto</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea class="form-control" id="pp-observacoes" rows="2"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Itens</label>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered" id="pp-itens-tabela">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Referência</th>
                                        <th>Qtd.</th>
                                        <th>Valor Unit.</th>
                                        <th>Total</th>
                                        <th>Ação</th>
                                    </tr>
                                </thead>
                                <tbody id="pp-itens-body"></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="adicionarItemProblema()">
                            <i class="fa-solid fa-plus"></i> Adicionar Item
                        </button>
                    </div>

                    <div class="mb-3 text-end">
                        <strong>Total: R$ <span id="pp-total-valor">0,00</span></strong>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary-nutri" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success-nutri" onclick="salvarPedidoProblema()">
                    <i class="fa-solid fa-save"></i> Salvar Pedido
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   SCRIPTS
   ================================================================ -->
<script>
// Função para obter token (mesma do sistema)
function getToken() {
    const token = localStorage.getItem('authToken') || 
                  sessionStorage.getItem('authToken') ||
                  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    return token || '';
}
</script>
<script src="/portal/modules/frota/assets/frota.js?v=<?= $version ?>"></script>
<script src="/portal/modules/frota/assets/acerto-embarque.js?v=<?= $version ?>"></script>

<?php
require_once __DIR__ . '/../../estrutura/footer.php';
?>