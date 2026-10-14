<?php
$pageTitle = 'Pedido de Amostra | Nutricional';
$moduleJs = 'pedido-amostra.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .pa-wrap{font-family:"Plus Jakarta Sans",system-ui,sans-serif}
  .pa-hero{background:linear-gradient(135deg,#047857 0%,#10b981 60%,#34d399 100%)}
  .pa-card{background:#fff;border-radius:1.5rem;box-shadow:0 8px 30px rgba(15,23,42,.06);border:1px solid #f1f5f9}
  .pa-label{font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
  .pa-input{width:100%;margin-top:.35rem;border:2px solid #e2e8f0;border-radius:1rem;padding:.8rem 1rem;font-size:1rem;background:#f8fafc;transition:.15s}
  .pa-input:focus{outline:none;border-color:#10b981;background:#fff;box-shadow:0 0 0 4px rgba(16,185,129,.12)}
  .pa-item{display:flex;align-items:center;gap:.75rem;padding:.85rem;border:2px solid #f1f5f9;border-radius:1rem;transition:.15s}
  .pa-item.on{border-color:#10b981;background:#ecfdf5}
  .pa-qty{display:flex;align-items:center;gap:.25rem}
  .pa-qty button{width:2.4rem;height:2.4rem;border-radius:.8rem;background:#f1f5f9;font-weight:800;font-size:1.1rem}
  .pa-qty input{width:3.6rem;text-align:center;font-weight:800;border:2px solid #e2e8f0;border-radius:.8rem;height:2.4rem}
  .pa-step{width:2rem;height:2rem;border-radius:999px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;background:#d1fae5;color:#047857;flex:none}
  .pa-bar{position:sticky;bottom:0;z-index:30;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);border-top:1px solid #e2e8f0;padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom))}
  .pa-btn{background:linear-gradient(135deg,#059669,#10b981);color:#fff;font-weight:800;border-radius:1rem;padding:.95rem 1.5rem;box-shadow:0 8px 20px rgba(16,185,129,.35);width:100%}
  .pa-btn:disabled{opacity:.6}
  .pa-ped{border:1px solid #f1f5f9;border-radius:1.1rem;padding:.9rem;display:flex;gap:.75rem;align-items:center}
  .pa-eye{width:2.6rem;height:2.6rem;border-radius:.9rem;background:#ecfdf5;color:#059669;flex:none}
  @media(min-width:1024px){.pa-bar{position:static;border:0;background:none;backdrop-filter:none;padding:0}.pa-btn{width:auto}}
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="pa-wrap max-w-3xl mx-auto px-3 sm:px-4 pt-3 pb-4">
    <div class="pa-hero rounded-3xl p-5 text-white shadow-lg mb-4 flex items-center gap-3">
        <a href="<?= $appBase ?>/portal/" class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white no-underline"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-lg sm:text-xl font-extrabold leading-tight">Pedido de Amostra</h1>
            <p class="text-xs opacity-90">Cliente, tipo, itens e observação</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center"><i class="fa-solid fa-gift text-xl"></i></div>
    </div>

    <div class="pa-card p-4 mb-4">
        <div class="flex items-center gap-2 mb-3"><span class="pa-step">1</span><b>Dados do pedido</b></div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <label><span class="pa-label">Filial</span><select id="selFilial" class="pa-input"></select></label>
            <label><span class="pa-label">Tipo de pedido</span><select id="selTransacao" class="pa-input"></select></label>
        </div>
        <div class="mt-3 relative">
            <span class="pa-label">Cliente</span>
            <input id="buscaCliente" type="search" autocomplete="off" placeholder="Código, CNPJ ou nome" class="pa-input">
            <div id="listaClientes" class="hidden absolute z-20 left-0 right-0 mt-1 bg-white border rounded-2xl shadow-xl max-h-64 overflow-auto"></div>
            <div id="clienteSel" class="hidden mt-2 p-3 rounded-2xl bg-emerald-50 text-emerald-800 text-sm font-bold flex items-center justify-between gap-2"></div>
        </div>
    </div>

    <div class="pa-card p-4 mb-4">
        <div class="flex items-center gap-2 mb-3"><span class="pa-step">2</span><b>Itens e quantidades</b></div>
        <div class="flex gap-2 mb-3">
            <input id="buscaItem" type="search" placeholder="Filtrar itens" class="pa-input !mt-0">
            <button id="btnRecarregar" class="px-4 rounded-2xl bg-slate-100 text-slate-600" title="Recarregar"><i class="fa-solid fa-rotate"></i></button>
        </div>
        <div id="listaItens" class="space-y-2 max-h-[55vh] overflow-auto"></div>
    </div>

    <div class="pa-card p-4 mb-4">
        <div class="flex items-center gap-2 mb-3"><span class="pa-step">3</span><b>Observação <span class="text-red-500">*</span></b></div>
        <textarea id="observacao" maxlength="500" rows="3" placeholder="Obrigatória: motivo / destino da amostra" class="pa-input !mt-0"></textarea>
        <div class="text-right text-xs text-slate-400 mt-1"><span id="contObs">0</span>/500</div>
    </div>

    <div class="pa-bar rounded-2xl">
        <div class="flex items-center justify-between mb-2 text-sm">
            <span id="resumoSel" class="font-bold text-slate-600">Nenhum item selecionado</span>
        </div>
        <button id="btnEnviar" class="pa-btn"><i class="fa-solid fa-paper-plane mr-1"></i> Enviar pedido</button>
    </div>

    <div class="pa-card p-4 mt-4">
        <div class="flex items-center justify-between mb-3">
            <b>Últimos pedidos</b>
            <button id="btnAtualizarPedidos" class="text-sm text-emerald-700 font-bold"><i class="fa-solid fa-rotate"></i> Atualizar</button>
        </div>
        <div id="listaPedidos" class="space-y-2"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
