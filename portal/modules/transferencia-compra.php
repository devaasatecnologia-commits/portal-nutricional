<?php
$tipo = ($_GET['tipo'] ?? 'matriz-filial') === 'filial-matriz' ? 'filial-matriz' : 'matriz-filial';
$ehMF = $tipo === 'matriz-filial';
$pageTitle = ($ehMF ? 'Matriz → Filial' : 'Filial → Matriz') . ' | Nutricional';
$moduleJs = 'transferencia-compra.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$cor = $ehMF ? ['#0369a1', '#0ea5e9', '#7dd3fc'] : ['#0f766e', '#14b8a6', '#5eead4'];
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .tc-wrap{font-family:"Plus Jakarta Sans",system-ui,sans-serif}
  .tc-hero{background:linear-gradient(135deg,' . $cor[0] . ' 0%,' . $cor[1] . ' 60%,' . $cor[2] . ' 100%)}
  .tc-card{background:#fff;border-radius:1.5rem;box-shadow:0 8px 30px rgba(15,23,42,.06);border:1px solid #f1f5f9}
  .tc-label{font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
  .tc-input{width:100%;margin-top:.35rem;border:2px solid #e2e8f0;border-radius:1rem;padding:.7rem .9rem;font-size:.95rem;background:#f8fafc}
  .tc-input:focus{outline:none;border-color:' . $cor[1] . ';background:#fff}
  .tc-row{border:1px solid #f1f5f9;border-radius:1.1rem;padding:.85rem;display:flex;gap:.7rem;align-items:flex-start;cursor:pointer}
  .tc-row:hover{border-color:' . $cor[2] . ';background:#f8fafc}
  .tc-ico{width:2.5rem;height:2.5rem;border-radius:.9rem;display:flex;align-items:center;justify-content:center;flex:none;background:' . $cor[2] . '55;color:' . $cor[0] . '}
  .tc-chip{padding:.2rem .6rem;border-radius:999px;font-size:.68rem;font-weight:800}
  .tc-btn{border-radius:1rem;background:' . $cor[0] . ';color:#fff;font-weight:700;padding:.75rem 1rem}
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="tc-wrap max-w-4xl mx-auto px-3 sm:px-4 pt-3 pb-8" data-tipo="<?= $tipo ?>">
    <div class="tc-hero rounded-3xl p-5 text-white shadow-lg mb-4 flex items-center gap-3">
        <a href="<?= $appBase ?>/portal/" class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white no-underline"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-lg sm:text-xl font-extrabold leading-tight"><?= $ehMF ? 'Matriz → Filial' : 'Filial → Matriz' ?></h1>
            <p class="text-xs opacity-90"><?= $ehMF ? 'Solicitações por embarque/remessa e guia' : 'Solicitações geradas por ordem de compra' ?></p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center"><i class="fa-solid <?= $ehMF ? 'fa-truck-ramp-box' : 'fa-warehouse' ?> text-xl"></i></div>
    </div>

    <div class="tc-card p-4 mb-3">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <label><span class="tc-label">Data inicial</span><input id="dtIni" type="date" class="tc-input"></label>
            <label><span class="tc-label">Data final</span><input id="dtFim" type="date" class="tc-input"></label>
            <label class="col-span-2"><span class="tc-label">Buscar</span><input id="busca" type="search" placeholder="<?= $ehMF ? 'Embarque, placa ou carga' : 'Ordem ou fornecedor' ?>" class="tc-input"></label>
        </div>
        <div class="flex gap-2 mt-3">
            <button id="btnFiltrar" class="tc-btn flex-1"><i class="fa-solid fa-magnifying-glass"></i> Filtrar</button>
            <button id="btnLimpar" class="rounded-2xl bg-slate-100 text-slate-600 font-bold px-4">Limpar</button>
        </div>
    </div>

    <div class="tc-card p-4 mb-3">
        <div class="flex items-center justify-between mb-3">
            <div>
                <span class="tc-label" id="rotulo">Últimas 10</span>
                <div id="totais" class="text-lg font-extrabold text-slate-800">—</div>
            </div>
            <button id="btnXls" class="text-sm font-bold" style="color:<?= $cor[0] ?>"><i class="fa-solid fa-file-excel"></i> Baixar Excel</button>
        </div>
        <div id="lista" class="space-y-2"></div>
    </div>

    <div class="tc-card p-4 mb-3">
        <b class="block mb-1"><?= $ehMF ? 'Embarques aguardando (filial 6, não faturados)' : 'Ordens de compra disponíveis' ?></b>
        <p class="text-xs text-slate-400 mb-3">A solicitação continua sendo criada pelo ERP. Aqui você acompanha o que já virou solicitação.</p>
        <div id="pendentes" class="space-y-2"></div>
    </div>

    <?php if ($ehMF) { ?>
    <div class="tc-card p-4 hidden" id="cardGuia">
        <div class="flex items-center justify-between mb-2">
            <b>Alíquotas da guia</b>
            <button id="btnNovaGuia" class="text-sm font-bold hidden" style="color:<?= $cor[0] ?>"><i class="fa-solid fa-plus"></i> Nova vigência</button>
        </div>
        <p class="text-xs text-slate-400 mb-3">Valor da guia = base de ST × alíquota vigente na data da solicitação.</p>
        <div id="guias" class="space-y-2"></div>
    </div>
    <?php } ?>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
