<?php
$pageTitle = 'Transferência de Flex | Nutricional';
$moduleJs = 'transferencia-flex.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .fx-wrap{font-family:"Plus Jakarta Sans",system-ui,sans-serif}
  .fx-hero{background:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 60%,#a78bfa 100%)}
  .fx-card{background:#fff;border-radius:1.5rem;box-shadow:0 8px 30px rgba(15,23,42,.06);border:1px solid #f1f5f9}
  .fx-label{font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
  .fx-input{width:100%;margin-top:.35rem;border:2px solid #e2e8f0;border-radius:1rem;padding:.8rem 1rem;font-size:1rem;background:#f8fafc;transition:.15s}
  .fx-input:focus{outline:none;border-color:#8b5cf6;background:#fff;box-shadow:0 0 0 4px rgba(139,92,246,.12)}
  .fx-step{width:2rem;height:2rem;border-radius:999px;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;background:#ede9fe;color:#6d28d9;flex:none}
  .fx-saldo{border-radius:1.1rem;padding:.8rem 1rem;margin-top:.6rem;display:flex;align-items:center;justify-content:space-between;background:#f5f3ff;color:#5b21b6;font-weight:800}
  .fx-saldo.off{background:#fef2f2;color:#b91c1c}
  .fx-btn{background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;font-weight:800;border-radius:1rem;padding:.95rem 1.5rem;box-shadow:0 8px 20px rgba(139,92,246,.35);width:100%}
  .fx-btn:disabled{opacity:.55}
  .fx-bar{position:sticky;bottom:0;z-index:30;background:rgba(255,255,255,.95);backdrop-filter:blur(8px);border-top:1px solid #e2e8f0;padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom))}
  .fx-mov{border:1px solid #f1f5f9;border-radius:1.1rem;padding:.9rem}
  .fx-mov.falha{background:#fef2f2;border-color:#fecaca}
  @media(min-width:1024px){.fx-bar{position:static;border:0;background:none;backdrop-filter:none;padding:0}.fx-btn{width:auto}}
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="fx-wrap max-w-3xl mx-auto px-3 sm:px-4 pt-3 pb-4">
    <div class="fx-hero rounded-3xl p-5 text-white shadow-lg mb-4 flex items-center gap-3">
        <a href="<?= $appBase ?>/portal/" class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white no-underline"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-lg sm:text-xl font-extrabold leading-tight">Transferência de Flex</h1>
            <p class="text-xs opacity-90">De um representante para outro, com saldo e histórico</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center"><i class="fa-solid fa-right-left text-xl"></i></div>
    </div>

    <div class="fx-card p-4 mb-4">
        <div class="flex items-center gap-2 mb-3"><span class="fx-step">1</span><b>Quem envia (DE)</b></div>
        <label class="block mb-3"><span class="fx-label">Filial</span><select id="selFilial" class="fx-input"></select></label>
        <label class="block"><span class="fx-label">Representante / gestor DE</span><select id="selDe" class="fx-input"></select></label>
        <div id="saldoDe" class="fx-saldo hidden"></div>
    </div>

    <div id="blocoPara" class="fx-card p-4 mb-4 hidden">
        <div class="flex items-center gap-2 mb-3"><span class="fx-step">2</span><b>Quem recebe (PARA)</b></div>
        <label class="block"><span class="fx-label">Representante / gestor PARA</span><select id="selPara" class="fx-input"></select></label>
        <div id="saldoPara" class="fx-saldo hidden"></div>
    </div>

    <div id="blocoValor" class="fx-card p-4 mb-4 hidden">
        <div class="flex items-center gap-2 mb-3"><span class="fx-step">3</span><b>Valor e motivo</b></div>
        <label class="block mb-3"><span class="fx-label">Valor a transferir</span>
            <input id="valor" inputmode="numeric" autocomplete="off" placeholder="R$ 0,00" class="fx-input"></label>
        <label class="block"><span class="fx-label">Motivo <span class="text-red-500">*</span></span>
            <textarea id="motivo" maxlength="255" rows="3" placeholder="Obrigatório: explique a transferência" class="fx-input"></textarea></label>
    </div>

    <div id="barraEnviar" class="fx-bar rounded-2xl hidden">
        <button id="btnTransferir" class="fx-btn" disabled><i class="fa-solid fa-right-left mr-1"></i> Transferir</button>
    </div>

    <div class="fx-card p-4 mt-4">
        <div class="flex items-center justify-between mb-3">
            <div><b>Movimentações</b> <span id="modoMov" class="text-xs text-slate-400 font-bold ml-1">Últimas 10</span></div>
            <button id="btnExcel" class="text-sm text-violet-700 font-bold"><i class="fa-solid fa-file-excel"></i> Baixar Excel</button>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-2">
            <label><span class="fx-label">Quem enviou (DE)</span><select id="fDe" class="fx-input"></select></label>
            <label><span class="fx-label">Quem recebeu (PARA)</span><select id="fPara" class="fx-input"></select></label>
            <label><span class="fx-label">Data inicial</span><input id="dtIni" type="date" class="fx-input"></label>
            <label><span class="fx-label">Data final</span><input id="dtFim" type="date" class="fx-input"></label>
        </div>
        <div class="grid grid-cols-2 gap-2 mb-3">
            <button id="btnFiltrar" class="py-2.5 rounded-2xl bg-violet-600 text-white font-bold"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
            <button id="btnLimpar" class="py-2.5 rounded-2xl bg-slate-100 text-slate-600 font-bold"><i class="fa-solid fa-eraser"></i> Limpar</button>
        </div>
        <div id="listaMov" class="space-y-2"></div>
    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
