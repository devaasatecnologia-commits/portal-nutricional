<?php
// ======================================================================
// MODULO FROTA - CADASTRO DE FROTA (VEÍCULOS + MOTORISTAS + MAPA AO VIVO)
// ======================================================================

$pageTitle = 'Cadastro de Frota | Frota | Nutricional';
$version = time();
// Mesmo cálculo de base usado em header.php/asset(), necessário aqui porque
// $extraCss/$extraJs são strings HTML cruas (não passam pela função asset()).
$assetBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') === 0) ? '/API' : '';

// ================================================================
// HEADER E CSS
// ================================================================
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="' . $assetBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/frota.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/acerto-embarque.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/gestao-cargas.css?v=' . $version . '">
<link rel="stylesheet" href="' . $assetBase . '/portal/modules/frota/assets/cadastro-frota.css?v=' . $version . '">
';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script src="' . $assetBase . '/portal/modules/frota/assets/cadastro-frota.js?v=' . $version . '"></script>
';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<div class="max-w-full mx-auto px-4 lg:px-6 py-4" style="background: var(--nutri-bg); min-height: 100vh;">

    <!-- HEADER -->
    <div class="bg-gradient-to-r from-[#1a3c34] to-[#2d5a4e] rounded-3xl p-6 lg:p-7 mb-6 shadow-xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-5">
            <div class="flex items-center gap-4">
                <a href="/portal/" class="flex w-10 h-10 rounded-xl items-center justify-center transition-colors no-underline bg-white/20 hover:bg-white/30" title="Voltar ao Portal">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div class="hero-icon-badge">
                    <i class="fa-solid fa-id-card-clip text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight flex items-center gap-2">
                        Cadastro de Frota
                    </h1>
                    <p class="text-emerald-200/80 text-sm flex items-center gap-2 mt-0.5">
                        Veículos e motoristas — integrado com ERP e Cobli
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <div class="hero-stat-chip highlight">
                    <i class="fa-solid fa-truck"></i>
                    <span id="total-veiculos-cad">0</span>
                    <span class="hero-stat-label">veículos</span>
                </div>
                <div class="hero-stat-chip">
                    <i class="fa-solid fa-id-badge"></i>
                    <span id="total-motoristas-cad">0</span>
                    <span class="hero-stat-label">motoristas</span>
                </div>
                <button class="hero-refresh-btn" onclick="recarregarAbaAtualCadFrota()" title="Atualizar dados">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
                <button class="theme-toggle theme-toggle-inline" onclick="toggleTheme()" title="Alternar tema">
                    <i class="fa-solid fa-moon"></i>
                </button>
            </div>
        </div>
        <div class="gold-accent-line"></div>
    </div>

    <!-- ABAS -->
    <div class="cadfrota-tabs" id="cadfrota-tabs" role="tablist">
        <button type="button" class="cadfrota-tab active" data-tab="veiculos" onclick="mudarAbaCadFrota('veiculos', this)" role="tab" aria-selected="true">
            <i class="fa-solid fa-truck"></i> Veículos
        </button>
        <button type="button" class="cadfrota-tab" data-tab="motoristas" onclick="mudarAbaCadFrota('motoristas', this)" role="tab" aria-selected="false">
            <i class="fa-solid fa-id-badge"></i> Motoristas
        </button>
        <button type="button" class="cadfrota-tab" data-tab="mapa" onclick="mudarAbaCadFrota('mapa', this)" role="tab" aria-selected="false">
            <i class="fa-solid fa-map-location-dot"></i> Mapa ao vivo
        </button>
    </div>

    <!-- ================================================================
       ABA: VEÍCULOS
    ================================================================ -->
    <div class="cadfrota-tab-panel" id="cadfrota-tab-veiculos" role="tabpanel">
        <div class="section-card">
            <div class="section-header flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="section-icon-badge"><i class="fa-solid fa-truck"></i></div>
                    <div>
                        <span class="font-bold text-[#1a3c34]">Veículos cadastrados</span>
                        <span class="text-xs text-slate-400 block">Placa, modelo, status Cobli e vínculo com o ERP</span>
                    </div>
                </div>
                <button type="button" class="btn-premium" onclick="abrirFormVeiculo()">
                    <i class="fa-solid fa-plus"></i> Novo veículo
                </button>
            </div>
            <div class="section-body p-0">
                <div class="cadfrota-toolbar p-4 pb-0">
                    <div class="cargas-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="cadfrota-busca-veiculo" placeholder="Buscar por placa, modelo ou marca..." oninput="debounceCarregarVeiculosCad()">
                    </div>
                    <label class="cargas-priority">
                        <span>Status</span>
                        <select id="cadfrota-filtro-status-veiculo" onchange="carregarVeiculosCad()">
                            <option value="">Todos</option>
                            <option value="disponivel">Disponível</option>
                            <option value="em_uso">Em uso</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </label>
                </div>
                <div id="cadfrota-lista-veiculos" class="cadfrota-grid">
                    <div class="cadfrota-empty"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando veículos...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
       ABA: MOTORISTAS
    ================================================================ -->
    <div class="cadfrota-tab-panel" id="cadfrota-tab-motoristas" role="tabpanel" hidden>
        <div class="section-card">
            <div class="section-header flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="section-icon-badge"><i class="fa-solid fa-id-badge"></i></div>
                    <div>
                        <span class="font-bold text-[#1a3c34]">Motoristas cadastrados</span>
                        <span class="text-xs text-slate-400 block">Dados pessoais, CNH e vínculo com o ERP</span>
                    </div>
                </div>
                <button type="button" class="btn-premium" onclick="abrirFormMotorista()">
                    <i class="fa-solid fa-plus"></i> Novo motorista
                </button>
            </div>
            <div class="section-body p-0">
                <div class="cadfrota-toolbar p-4 pb-0">
                    <div class="cargas-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="cadfrota-busca-motorista" placeholder="Buscar por nome, CPF ou telefone..." oninput="debounceCarregarMotoristasCad()">
                    </div>
                    <label class="cargas-priority">
                        <span>Status</span>
                        <select id="cadfrota-filtro-status-motorista" onchange="carregarMotoristasCad()">
                            <option value="">Todos</option>
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="ferias">Férias</option>
                            <option value="afastado">Afastado</option>
                        </select>
                    </label>
                </div>
                <div id="cadfrota-lista-motoristas" class="cadfrota-grid">
                    <div class="cadfrota-empty"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando motoristas...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ================================================================
       ABA: MAPA AO VIVO
    ================================================================ -->
    <div class="cadfrota-tab-panel" id="cadfrota-tab-mapa" role="tabpanel" hidden>
        <div class="section-card">
            <div class="section-header flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <div class="section-icon-badge"><i class="fa-solid fa-map-location-dot"></i></div>
                    <div>
                        <span class="font-bold text-[#1a3c34]">Posição atual da frota</span>
                        <span class="text-xs text-slate-400 block">Rastreamento em tempo real via Cobli — mapa MapLibre (OpenFreeMap)</span>
                    </div>
                </div>
                <button type="button" class="cargas-clear-filter" onclick="carregarMapaCadFrota()">
                    <i class="fa-solid fa-rotate-right"></i> Atualizar posições
                </button>
            </div>
            <div class="section-body p-0">
                <div id="cadfrota-mapa-vazio" class="cadfrota-empty" style="display:none;">
                    Nenhum veículo vinculado à Cobli no momento. Cadastre e vincule na aba Veículos.
                </div>
                <div id="cadfrota-mapa"></div>
            </div>
        </div>
    </div>

</div>

<!-- ================================================================
   MODAL: FORMULÁRIO DE VEÍCULO
================================================================ -->
<div class="modal fade" id="modalVeiculoCad" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-truck mr-2"></i> <span id="modal-veiculo-titulo">Novo veículo</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="veiculo-cad-id">
                <div class="cadfrota-form-grid">
                    <div class="cadfrota-field">
                        <label>Placa *</label>
                        <input type="text" id="veiculo-cad-placa" maxlength="8" style="text-transform:uppercase;">
                    </div>
                    <div class="cadfrota-field">
                        <label>Tipo</label>
                        <select id="veiculo-cad-tipo">
                            <option value="bau">Baú</option>
                            <option value="carreta">Carreta</option>
                        </select>
                    </div>
                    <div class="cadfrota-field">
                        <label>Marca</label>
                        <input type="text" id="veiculo-cad-marca" placeholder="Ex: Mercedes">
                    </div>
                    <div class="cadfrota-field">
                        <label>Modelo</label>
                        <input type="text" id="veiculo-cad-modelo" placeholder="Ex: Atego 1719">
                    </div>
                    <div class="cadfrota-field">
                        <label>Ano</label>
                        <input type="number" id="veiculo-cad-ano" placeholder="2024">
                    </div>
                    <div class="cadfrota-field">
                        <label>Cor</label>
                        <input type="text" id="veiculo-cad-cor">
                    </div>
                    <div class="cadfrota-field">
                        <label>Capacidade (kg)</label>
                        <input type="number" id="veiculo-cad-capacidade" placeholder="10000">
                    </div>
                    <div class="cadfrota-field">
                        <label>Status</label>
                        <select id="veiculo-cad-status">
                            <option value="disponivel">Disponível</option>
                            <option value="em_uso">Em uso</option>
                            <option value="manutencao">Manutenção</option>
                            <option value="inativo">Inativo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-premium" onclick="salvarVeiculoCad()">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   MODAL: FORMULÁRIO DE MOTORISTA
================================================================ -->
<div class="modal fade" id="modalMotoristaCad" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-id-badge mr-2"></i> <span id="modal-motorista-titulo">Novo motorista</span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="motorista-cad-id">
                <div class="cadfrota-form-grid">
                    <div class="cadfrota-field full">
                        <label>Nome *</label>
                        <input type="text" id="motorista-cad-nome">
                    </div>
                    <div class="cadfrota-field">
                        <label>CPF</label>
                        <input type="text" id="motorista-cad-cpf" placeholder="000.000.000-00">
                    </div>
                    <div class="cadfrota-field">
                        <label>CNH</label>
                        <input type="text" id="motorista-cad-cnh">
                    </div>
                    <div class="cadfrota-field">
                        <label>Telefone</label>
                        <input type="text" id="motorista-cad-telefone" placeholder="(00) 00000-0000">
                    </div>
                    <div class="cadfrota-field">
                        <label>E-mail</label>
                        <input type="text" id="motorista-cad-email">
                    </div>
                    <div class="cadfrota-field full">
                        <label>Endereço</label>
                        <input type="text" id="motorista-cad-endereco">
                    </div>
                    <div class="cadfrota-field">
                        <label>Status</label>
                        <select id="motorista-cad-status">
                            <option value="ativo">Ativo</option>
                            <option value="inativo">Inativo</option>
                            <option value="ferias">Férias</option>
                            <option value="afastado">Afastado</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-premium" onclick="salvarMotoristaCad()">
                    <i class="fa-solid fa-floppy-disk"></i> Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>
