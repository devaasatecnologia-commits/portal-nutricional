<?php
$pageTitle = 'Pedido de Amostra | Nutricional';
$moduleJs = 'pedido-amostra.js';
$version = time();
$appBase = strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') !== false ? '/API' : '';
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="' . $appBase . '/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<div class="modulo-container max-w-6xl mx-auto px-4 lg:px-6 py-4">
    <div class="rounded-3xl p-5 mb-6 flex items-center gap-3 shadow-sm bg-white">
        <a href="<?= $appBase ?>/portal/" class="w-10 h-10 rounded-xl flex items-center justify-center no-underline"><i class="fa-solid fa-arrow-left"></i></a>
        <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center"><i class="fa-solid fa-gift text-xl"></i></div>
        <div>
            <h1 class="text-xl font-extrabold">Pedido de Amostra</h1>
            <p class="text-xs text-gray-500">Amostras e brindes conforme saldo disponível</p>
        </div>
    </div>

    <div id="avisoSandbox" class="hidden mb-4 p-3 rounded-xl bg-amber-50 text-amber-700 text-sm font-semibold"></div>
    <pre id="sqlSandbox" class="hidden mb-4 p-3 rounded-xl bg-gray-900 text-green-300 text-xs overflow-auto max-h-96 whitespace-pre-wrap"></pre>

    <div class="bg-white rounded-3xl p-5 shadow-sm mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <label class="text-xs font-bold text-gray-500">Filial
                <select id="selFilial" class="w-full mt-1 border rounded-xl p-2 text-sm"></select>
            </label>
            <label class="text-xs font-bold text-gray-500">Tipo de pedido
                <select id="selTransacao" class="w-full mt-1 border rounded-xl p-2 text-sm"></select>
            </label>
            <div class="text-xs font-bold text-gray-500 relative">Cliente
                <input id="buscaCliente" type="text" autocomplete="off" placeholder="Código, CNPJ ou nome" class="w-full mt-1 border rounded-xl p-2 text-sm">
                <div id="listaClientes" class="hidden absolute z-20 left-0 right-0 bg-white border rounded-xl shadow max-h-60 overflow-auto"></div>
                <div id="clienteSel" class="mt-1 text-emerald-700 text-sm"></div>
            </div>
        </div>

        <div class="flex gap-2 mb-3">
            <input id="buscaItem" type="text" placeholder="Filtrar itens" class="flex-1 border rounded-xl p-2 text-sm">
            <button id="btnRecarregar" class="px-4 rounded-xl bg-gray-100 text-sm font-bold"><i class="fa-solid fa-rotate"></i></button>
        </div>

        <div class="overflow-auto max-h-96 border rounded-xl">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 sticky top-0">
                    <tr><th class="p-2 text-left">Item</th><th class="p-2 text-right">Saldo</th><th class="p-2 w-28">Qtd</th></tr>
                </thead>
                <tbody id="tbItens"></tbody>
            </table>
        </div>

        <label class="block mt-4 text-xs font-bold text-gray-500">Observação
            <textarea id="observacao" maxlength="500" rows="2" class="w-full mt-1 border rounded-xl p-2 text-sm"></textarea>
        </label>

        <div class="mt-4 flex items-center justify-between">
            <span id="resumoSel" class="text-sm text-gray-500"></span>
            <button id="btnEnviar" class="px-6 py-3 rounded-xl bg-emerald-600 text-white font-bold text-sm">
                <i class="fa-solid fa-paper-plane mr-1"></i> Enviar pedido
            </button>
        </div>
    </div>

    <div class="bg-white rounded-3xl p-5 shadow-sm">
        <h2 class="font-extrabold mb-3">Últimos pedidos</h2>
        <div class="overflow-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500">
                    <tr><th class="p-2 text-left">Data</th><th class="p-2 text-left">Pedido</th><th class="p-2 text-left">Cliente</th><th class="p-2 text-left">Itens</th><th class="p-2 text-left">Observação</th></tr>
                </thead>
                <tbody id="tbPedidos"></tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>
