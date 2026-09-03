<?php
// ============================================================
// MÓDULO DE INVENTÁRIO - PADRÃO DO SISTEMA
// ============================================================

$pageTitle = 'Inventário de Estoque | Nutricional Distribuidora';
$moduleJs = 'inventario.js'; // Agora o JS existe!
$version = time();

$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<style>
    .inventario-filters {
        background: white;
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    
    .filter-group {
        margin-bottom: 15px;
    }
    
    .filter-group label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 5px;
        display: block;
    }
    
    select.form-select-sm {
        font-size: 0.85rem;
        padding: 8px 12px;
    }
    
    .resumo-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .resumo-card {
        background: linear-gradient(135deg, #274036 0%, #3a5a4e 100%);
        border-radius: 16px;
        padding: 15px 20px;
        color: white;
    }
    
    .resumo-card .valor {
        font-size: 1.8rem;
        font-weight: 800;
    }
    
    .resumo-card .label {
        font-size: 0.7rem;
        opacity: 0.8;
        text-transform: uppercase;
    }
    
    .badge-validade {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
    }
    
    .badge-otimo { background: #dcfce7; color: #166534; }
    .badge-regular { background: #fef9c3; color: #854d0e; }
    .badge-ruim { background: #fee2e2; color: #991b1b; }
    
    .badge-devolucao {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 20px;
        font-size: 0.65rem;
        font-weight: 700;
        background: #fef3c7;
        color: #92400e;
    }
    
    .btn-detalhes {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 0.7rem;
        cursor: pointer;
    }
    
    .btn-detalhes:hover {
        background: #2563eb;
    }
    
    .table-inventario {
        font-size: 0.8rem;
    }
    
    .table-inventario th {
        background: #f8fafc;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        color: #64748b;
        padding: 12px;
    }
    
    .table-inventario td {
        padding: 10px 12px;
        vertical-align: middle;
    }
    
    .loading-spinner {
        text-align: center;
        padding: 40px;
    }
    
    @media (max-width: 768px) {
        .resumo-cards { grid-template-columns: repeat(2, 1fr); }
        .table-inventario { font-size: 0.7rem; }
        .table-inventario td, .table-inventario th { padding: 6px 8px; }
    }
</style>
';

require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? '' ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- HEADER DO MÓDULO -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-teal-100 text-teal-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-boxes-stacked text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">INVENTÁRIO</h2>
                <span class="text-xs text-slate-400 font-medium">Consulta de Estoque por Lote</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="inventario-filters">
        <div class="row g-3">
            <div class="col-md-3">
                <div class="filter-group">
                    <label><i class="fa-solid fa-building me-1"></i> Filiais</label>
                    <select id="selFiliais" class="form-select form-select-sm" multiple size="3">
                        <option value="">Carregando...</option>
                    </select>
                    <small class="text-muted" style="font-size: 0.65rem;">Segure Ctrl para múltiplas seleções</small>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="filter-group">
                    <label><i class="fa-solid fa-tag me-1"></i> Marcas</label>
                    <select id="selMarcas" class="form-select form-select-sm" multiple size="3">
                        <option value="">Carregando...</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="filter-group">
                    <label><i class="fa-solid fa-layer-group me-1"></i> Grupos</label>
                    <select id="selGrupos" class="form-select form-select-sm" multiple size="3">
                        <option value="">Carregando...</option>
                    </select>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="filter-group">
                    <label><i class="fa-solid fa-barcode me-1"></i> Itens</label>
                    <select id="selItens" class="form-select form-select-sm" multiple size="3">
                        <option value="">Selecione após busca</option>
                    </select>
                    <div class="input-group mt-2">
                        <input type="text" id="buscaItem" class="form-control form-control-sm" placeholder="Referência/Descrição/EAN...">
                        <button id="btnBuscarItem" class="btn btn-sm btn-outline-secondary">
                            <i class="fa-solid fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12 text-end">
                <button id="btnLimpar" class="btn btn-sm btn-secondary me-2">
                    <i class="fa-solid fa-eraser"></i> Limpar
                </button>
                <button id="btnPesquisar" class="btn btn-sm btn-primary me-2">
                    <i class="fa-solid fa-magnifying-glass"></i> Consultar
                </button>
                <button id="btnExportar" class="btn btn-sm btn-success">
                    <i class="fa-solid fa-file-excel"></i> Exportar
                </button>
            </div>
        </div>
    </div>

    <!-- CARDS DE RESUMO -->
    <div class="resumo-cards" id="resumoContainer" style="display: none;">
        <div class="resumo-card">
            <div class="valor" id="totalItens">0</div>
            <div class="label">Total de Itens (SKU)</div>
        </div>
        <div class="resumo-card">
            <div class="valor" id="totalLotes">0</div>
            <div class="label">Total de Lotes</div>
        </div>
        <div class="resumo-card">
            <div class="valor" id="saldoTotal">0</div>
            <div class="label">Saldo Total (Unidades)</div>
        </div>
        <div class="resumo-card">
            <div class="valor" id="lotesVencendo">0</div>
            <div class="label">Lotes com Validade Ruim</div>
        </div>
    </div>

    <!-- TABELA DE RESULTADOS -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="loading-spinner" id="loading">
            <i class="fa-solid fa-spinner fa-spin fa-2x text-slate-400"></i>
            <p class="mt-2 text-slate-400">Carregando inventário...</p>
        </div>
        
        <div id="tabelaResultados" style="display: none;">
            <div class="table-responsive">
                <table class="table table-hover table-inventario mb-0">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Referência</th>
                            <th>Lote</th>
                            <th>Validade</th>
                            <th>Status</th>
                            <th>Quantidade</th>
                            <th>Unidade</th>
                            <th>Filial</th>
                            <th>Devoluções</th>
                            <th style="width: 80px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyEstoque">
                        <tr><td colspan="10" class="text-center">Nenhum registro encontrado</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="p-3 bg-slate-50 border-t border-slate-100">
                <span class="text-xs text-slate-500" id="totalRegistros"></span>
            </div>
        </div>
    </div>
</div>

<script>
// Relógio (igual aos outros módulos)
setInterval(() => {
    const agora = new Date();
    const horaFormatada = agora.toLocaleTimeString('pt-br');
    const dataFormatada = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
    
    const relogio = document.getElementById('relogio');
    const dataTopo = document.getElementById('data-topo');
    
    if (relogio) relogio.innerText = horaFormatada;
    if (dataTopo) dataTopo.innerText = dataFormatada;
}, 1000);
</script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>