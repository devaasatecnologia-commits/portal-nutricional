<?php
// ======================================================================
// MODULO FROTA - GESTAO DE EMBARQUES (INTEGRACAO ERP)
// ======================================================================

$pageTitle = 'Embarques | Gestao de Frotas | Nutricional';
$version = time();

// ================================================================
// CONFIGURAÇÕES DA DISTRIBUIDORA
// ================================================================
define('DISTRIBUIDORA_LAT', -28.979438954992666);
define('DISTRIBUIDORA_LNG', -49.53561648427039);
define('DISTRIBUIDORA_ENDERECO', 'R. Alameda Ascendino Moraes de Sá, 6151, Araranguá - SC, 88902-490');

// ================================================================
// HEADER E CSS COMPLETO
// ================================================================
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/frota.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/embarquesClaude.css?v=' . $version . '">

<!-- PWA MANIFEST -->
<link rel="manifest" href="/portal/modules/frota/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="/portal/modules/frota/assets/icons/icon-192x192.png">';

$extraJs = '
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<!-- ================================================================
   HIDDEN INPUTS
   ================================================================ -->
   <input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? $_SESSION['idusuario'] ?? 0 ?>">
   <input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? $_SESSION['username'] ?? 'Operador' ?>">
   <input type="hidden" id="distribuidora_lat" value="<?= DISTRIBUIDORA_LAT ?>">
   <input type="hidden" id="distribuidora_lng" value="<?= DISTRIBUIDORA_LNG ?>">
   <input type="hidden" id="distribuidora_endereco" value="<?= DISTRIBUIDORA_ENDERECO ?>">

<!-- ================================================================
   CONTEÚDO PRINCIPAL
   ================================================================ -->
   <div class="max-w-full mx-auto px-4 lg:px-6 py-4" style="background: var(--nutri-bg); min-height: 100vh;">

    <!-- HEADER -->
    <div class="hero-embarques bg-gradient-to-r from-[#1a3c34] to-[#2d5a4e] rounded-3xl p-6 lg:p-7 mb-6 shadow-xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5">
            <div class="flex items-center gap-4">
                <a href="/portal/modules/frota/gestao-frota.php" class="flex w-10 h-10 rounded-xl items-center justify-center transition-colors no-underline bg-white/20 hover:bg-white/30">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div class="hero-icon-badge">
                    <i class="fa-solid fa-truck text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                        Embarques
                        <span class="hero-live-dot" title="Atualizado em tempo real"></span>
                    </h1>
                    <p class="text-emerald-200/80 text-sm flex items-center gap-2 mt-0.5">
                        Gestão de rotas e frota
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <div class="hero-stat-chip">
                    <i class="fa-solid fa-plug-circle-bolt"></i>
                    <span>Integração ERP</span>
                </div>
                <div class="hero-stat-chip highlight">
                    <i class="fa-solid fa-box-open"></i>
                    <span id="total-embarques">0</span>
                    <span class="hero-stat-label">registros</span>
                </div>
                <span id="cache-indicator" class="hero-stat-chip hidden">
                    <i class="fa-regular fa-clock"></i> <span id="cache-tempo">0s</span>
                </span>
                <button class="hero-refresh-btn" onclick="carregarEmbarques(true)" title="Atualizar dados">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button class="theme-toggle theme-toggle-inline" onclick="toggleTheme()" title="Alternar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
        <div class="gold-accent-line"></div>
    </div>

    <!-- FILTROS RÁPIDOS -->
    <div class="quick-filters" id="quick-filters">
        <button type="button" class="quick-filter-pill active" data-status="" onclick="aplicarFiltroRapido('', this)">
            <i class="fa-solid fa-layer-group"></i> Todos
        </button>
        <button type="button" class="quick-filter-pill" data-status="planejado" onclick="aplicarFiltroRapido('planejado', this)">
            <i class="fa-regular fa-clock"></i> Planejado
        </button>
        <button type="button" class="quick-filter-pill" data-status="em_andamento" onclick="aplicarFiltroRapido('em_andamento', this)">
            <i class="fa-solid fa-truck-fast"></i> Em Andamento
        </button>
        <button type="button" class="quick-filter-pill" data-status="finalizado" onclick="aplicarFiltroRapido('finalizado', this)">
            <i class="fa-solid fa-circle-check"></i> Finalizado
        </button>
        <button type="button" class="quick-filter-pill" data-status="problema" onclick="aplicarFiltroRapido('problema', this)">
            <i class="fa-solid fa-triangle-exclamation"></i> Problema
        </button>
        <button type="button" class="quick-filter-pill" data-status="cancelado" onclick="aplicarFiltroRapido('cancelado', this)">
            <i class="fa-solid fa-ban"></i> Cancelado
        </button>
    </div>

<!-- ================================================================
   BARRA DE FERRAMENTAS UNIFICADA (Filtros + Busca + Rastreamento)
   ================================================================ -->
<div class="section-card mb-6 toolbar-card">
    <div class="section-body toolbar-body">
        <div class="toolbar-row">
            <div class="toolbar-field toolbar-field-grow">
                <label class="form-label"><i class="fa-solid fa-magnifying-glass"></i> Buscar embarque</label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-hashtag input-icon"></i>
                    <input type="text" class="form-control" id="filtro-busca" placeholder="Nº, veículo, motorista...">
                    <button class="input-icon-btn" onclick="cacheEmbarques.dados=null;cacheEmbarques.timestamp=null;carregarEmbarques();" title="Buscar">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            <div class="toolbar-field toolbar-field-grow">
                <label class="form-label"><i class="fa-solid fa-location-crosshairs"></i> Rastrear entrega</label>
                <div class="input-icon-group">
                    <i class="fa-solid fa-route input-icon"></i>
                    <input type="text" id="codigo-rastreamento" class="form-control"
                           placeholder="Código de rastreamento (ex: TRK76BADDB5)"
                           onkeypress="if(event.key==='Enter') rastrearEntrega()">
                    <button class="input-icon-btn" onclick="rastrearEntrega()" title="Rastrear">
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                </div>
            </div>
            <button type="button" class="toolbar-advanced-toggle" onclick="toggleFiltrosAvancados()" id="btn-filtros-avancados">
                <i class="fa-solid fa-sliders"></i> Filtros
                <i class="fa-solid fa-chevron-down toggle-caret"></i>
            </button>
        </div>

        <div id="resultado-rastreamento" class="mt-3 hidden"></div>

        <div class="toolbar-advanced" id="filtros-avancados">
            <div class="toolbar-advanced-grid">
                <div>
                    <label class="form-label">Status</label>
                    <select class="form-select" id="filtro-status">
                        <option value="">Todos</option>
                        <option value="planejado">📋 Planejado</option>
                        <option value="em_andamento">🚚 Em Andamento</option>
                        <option value="finalizado">✅ Finalizado</option>
                        <option value="cancelado">🚫 Cancelado</option>
                        <option value="problema">⚠️ Problema</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Data Início</label>
                    <input type="date" class="form-control" id="filtro-data-inicio">
                </div>
                <div>
                    <label class="form-label">Data Fim</label>
                    <input type="date" class="form-control" id="filtro-data-fim">
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   SEÇÃO: DISPONÍVEIS PARA ROTA (EMBARQUES DO ERP)
   ================================================================ -->
   <div class="section-card mb-6" id="secao-disponiveis">
    <!-- Cabeçalho clicável -->
    <div class="section-header section-header-toggle flex justify-between items-center" onclick="toggleDisponiveis()">
        <div class="flex items-center gap-3">
            <div class="section-icon-badge">
                <i class="fa-regular fa-clock"></i>
            </div>
            <div>
                <span class="font-bold text-[#1a3c34]">
                    Embarques Disponíveis para Rota
                </span>
                <span class="text-xs text-slate-400 block" id="info-disponiveis">Carregando...</span>
            </div>
        </div>
        <div class="flex gap-2 items-center">
            <span id="toggle-disponiveis-icon" class="text-slate-400"><i class="fa-solid fa-chevron-down"></i></span>
            <button class="btn-success-nutri px-4 py-1.5 text-sm" onclick="event.stopPropagation(); criarRotasSelecionadas()" id="btn-criar-rotas-disponiveis" disabled>
                <i class="fa-solid fa-route mr-1"></i> Criar Rota (<span id="total-selecionados-disponiveis">0</span>)
            </button>
            <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors" onclick="event.stopPropagation(); atualizarDisponiveis()" title="Atualizar">
                <i class="fa-solid fa-sync-alt"></i>
            </button>
        </div>
    </div>
    <!-- Corpo (recolhível) -->
    <div class="section-body" id="disponiveis-body">
        <div class="flex flex-wrap gap-1 border-b border-slate-200 mb-4" id="abas-disponiveis">
            <!-- Abas geradas via JavaScript -->
        </div>
        <div id="lista-disponiveis" class="space-y-3">
            <div class="text-center py-4 text-slate-400">Carregando embarques disponíveis...</div>
        </div>
    </div>
</div>

<!-- TABELA DE ROTAS CRIADAS (GRUPOS) -->
<div class="section-card">
    <div class="section-header flex justify-between items-center flex-wrap gap-2">
        <div class="flex items-center gap-3">
            <div class="section-icon-badge">
                <i class="fa-solid fa-route"></i>
            </div>
            <div>
                <span class="font-bold text-[#1a3c34]">Rotas Criadas</span>
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
    <table class="table-frota w-full">
        <thead>
            <tr>
                <th class="text-center" style="width: 45px;">#</th>
                <th>Embarque</th>
                <th>Rota</th>
                <th>Veículo</th>
                <th>Motorista</th>
                <th class="text-center">Entregas</th>
                <th class="text-center">Valor</th>
                <th class="text-center">Status</th>
                <th class="text-center" style="width: 140px;">Ações</th>
            </tr>
        </thead>
        <tbody id="lista-embarques">
            <tr>
                <td colspan="9" class="text-center py-8">
                    <div class="flex flex-col items-center gap-2">
                        <div class="skeleton skeleton-title mx-auto"></div>
                        <div class="skeleton skeleton-text w-48 mx-auto"></div>
                        <div class="skeleton skeleton-text w-32 mx-auto"></div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
</div>
</div>

<!-- ================================================================
   MODAL: DETALHES DO EMBARQUE
   ================================================================ -->
   <div class="modal fade" id="modalDetalhes" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-truck mr-2"></i> Detalhes do Embarque <span id="detalhes-numero" class="font-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhes-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-nutri" onclick="exportarRota()" id="btn-exportar-rota">
                    <i class="fa-solid fa-file-export mr-2"></i> Exportar CSV
                </button>
                <button type="button" class="btn-primary-nutri" onclick="otimizarRota()" id="btn-otimizar-rota">
                    <i class="fa-solid fa-route mr-2"></i> Otimizar Rota
                </button>
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!-- ================================================================
   SCRIPTS COMPLETOS
   ================================================================ -->
   <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="/portal/modules/frota/assets/frota.js?v=<?= $version ?>"></script>
   <script src="/portal/modules/frota/assets/embarquesClaude.js?v=<?= $version ?>"></script>
<?php
require_once __DIR__ . '/../../estrutura/footer.php';
?>