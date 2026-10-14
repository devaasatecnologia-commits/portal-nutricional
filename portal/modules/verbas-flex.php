<?php
$pageTitle = 'Verbas Flex | Nutricional';
$moduleJs = 'verbas-flex.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
<style>
  .vf-wrap{font-family:"Plus Jakarta Sans",system-ui,sans-serif}
  .vf-hero{background:linear-gradient(135deg,#6d28d9 0%,#8b5cf6 60%,#a78bfa 100%)}
  .vf-card{background:#fff;border-radius:1.5rem;box-shadow:0 8px 30px rgba(15,23,42,.06);border:1px solid #f1f5f9}
  .vf-label{font-size:.7rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#64748b}
  .vf-input{width:100%;margin-top:.35rem;border:2px solid #e2e8f0;border-radius:1rem;padding:.7rem .9rem;font-size:.95rem;background:#f8fafc}
  .vf-input:focus{outline:none;border-color:#8b5cf6;background:#fff;box-shadow:0 0 0 4px rgba(139,92,246,.12)}
  .vf-tabs{display:flex;gap:.4rem;overflow-x:auto;padding:.25rem;background:#fff;border-radius:1.2rem;border:1px solid #f1f5f9;box-shadow:0 6px 20px rgba(15,23,42,.05)}
  .vf-tab{flex:1;white-space:nowrap;padding:.7rem 1rem;border-radius:.95rem;font-weight:800;font-size:.85rem;color:#64748b}
  .vf-tab.on{background:linear-gradient(135deg,#7c3aed,#8b5cf6);color:#fff;box-shadow:0 6px 16px rgba(139,92,246,.35)}
  .vf-kpi{border-radius:1.3rem;padding:1rem;color:#fff}
  .vf-kpi small{opacity:.85;font-weight:700;font-size:.7rem;letter-spacing:.03em;text-transform:uppercase}
  .vf-kpi b{display:block;font-size:1.35rem;font-weight:800;margin-top:.2rem}
  .vf-kpi i.t{font-size:.72rem;font-style:normal;font-weight:700;opacity:.9}
  .vf-row{border:1px solid #f1f5f9;border-radius:1.1rem;padding:.85rem;display:flex;gap:.7rem;align-items:flex-start;cursor:pointer;transition:.15s}
  .vf-row:hover{border-color:#c4b5fd;background:#faf5ff}
  .vf-ico{width:2.5rem;height:2.5rem;border-radius:.9rem;display:flex;align-items:center;justify-content:center;flex:none}
  .vf-bar{height:.55rem;border-radius:999px;background:#f1f5f9;overflow:hidden}
  .vf-bar>span{display:block;height:100%;border-radius:999px}
  .vf-chip{padding:.35rem .8rem;border-radius:999px;background:#f1f5f9;color:#475569;font-size:.75rem;font-weight:700}
  .vf-chip.on{background:#6d28d9;color:#fff}
</style>
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="vf-wrap max-w-4xl mx-auto px-3 sm:px-4 pt-3 pb-8">
    <div class="vf-hero rounded-3xl p-5 text-white shadow-lg mb-4 flex items-center gap-3">
        <a href="<?= $appBase ?>/portal/" class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white no-underline"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="flex-1">
            <h1 class="text-lg sm:text-xl font-extrabold leading-tight">Verbas Flex</h1>
            <p class="text-xs opacity-90">Painel, débitos e créditos</p>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center"><i class="fa-solid fa-sack-dollar text-xl"></i></div>
    </div>

    <div class="vf-card p-4 mb-3">
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <label class="col-span-2 sm:col-span-1"><span class="vf-label">Filial</span><select id="selFilial" class="vf-input"></select></label>
            <label><span class="vf-label">Data inicial</span><input id="dtIni" type="date" class="vf-input"></label>
            <label><span class="vf-label">Data final</span><input id="dtFim" type="date" class="vf-input"></label>
            <button id="btnAplicar" class="col-span-2 sm:col-span-1 mt-5 rounded-2xl bg-violet-600 text-white font-bold py-3"><i class="fa-solid fa-magnifying-glass"></i> Aplicar</button>
        </div>
        <div class="flex gap-2 mt-3 overflow-x-auto" id="atalhos">
            <button type="button" data-p="mes" class="vf-chip on">Este mês</button>
            <button type="button" data-p="ant" class="vf-chip">Mês anterior</button>
            <button type="button" data-p="30" class="vf-chip">30 dias</button>
            <button type="button" data-p="ano" class="vf-chip">Este ano</button>
        </div>
    </div>

    <div class="vf-tabs mb-3" id="abas">
        <button type="button" data-aba="painel" class="vf-tab on"><i class="fa-solid fa-chart-pie mr-1"></i> Painel</button>
        <button type="button" data-aba="debitos" class="vf-tab"><i class="fa-solid fa-arrow-trend-down mr-1"></i> Débitos</button>
        <button type="button" data-aba="creditos" class="vf-tab"><i class="fa-solid fa-hand-holding-dollar mr-1"></i> Créditos</button>
        <a href="<?= $appBase ?>/portal/modules/transferencia-flex.php" class="vf-tab text-center no-underline"><i class="fa-solid fa-right-left mr-1"></i> Transferências</a>
    </div>

    <section id="aba-painel">
        <div class="grid grid-cols-2 gap-3 mb-3" id="kpis"></div>
        <div class="vf-card p-4 mb-3"><b class="block mb-3">Últimos 6 meses</b><div id="serie" class="space-y-3"></div></div>
        <div class="vf-card p-4"><b class="block mb-3">Quem mais retirou no período</b><div id="ranking" class="space-y-3"></div></div>
    </section>

    <section id="aba-debitos" class="hidden">
        <div class="vf-card p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                <label><span class="vf-label">Representante</span><select id="fRep" class="vf-input"></select></label>
                <label><span class="vf-label">Buscar</span><input id="fBuscaD" type="search" placeholder="Cliente, motivo, nota" class="vf-input"></label>
            </div>
            <div class="flex items-center justify-between mb-3">
                <div><span class="vf-label">Total retirado</span><div id="totD" class="text-2xl font-extrabold text-red-600">R$ 0,00</div><div id="qtdD" class="text-xs text-slate-400"></div></div>
                <button id="xlsD" class="text-sm text-violet-700 font-bold"><i class="fa-solid fa-file-excel"></i> Baixar Excel</button>
            </div>
            <div id="listaD" class="space-y-2"></div>
        </div>
    </section>

    <section id="aba-creditos" class="hidden">
        <div class="vf-card p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mb-3">
                <label><span class="vf-label">Buscar motivo</span><input id="fBuscaC" type="search" placeholder="Motivo" class="vf-input"></label>
                <label class="flex items-center gap-2 mt-6 text-sm font-bold text-slate-600"><input id="fZerados" type="checkbox"> Mostrar lançamentos zerados / falhas</label>
            </div>
            <div class="flex items-center justify-between mb-3">
                <div><span class="vf-label">Total empréstimo</span><div id="totC" class="text-2xl font-extrabold text-emerald-600">R$ 0,00</div><div id="qtdC" class="text-xs text-slate-400"></div></div>
                <button id="xlsC" class="text-sm text-violet-700 font-bold"><i class="fa-solid fa-file-excel"></i> Baixar Excel</button>
            </div>
            <div id="listaC" class="space-y-2"></div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
