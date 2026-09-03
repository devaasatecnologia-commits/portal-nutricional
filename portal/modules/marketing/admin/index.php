<?php
$pageTitle = 'Admin Marketing | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../../../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <div class="rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/modules/marketing/dashboard.php" class="btn-voltar sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-chart-simple text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">ADMIN MARKETING</h2>
                <span class="text-xs text-slate-400 font-medium">Configuração do Sistema</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/portal/modules/marketing/admin/tipos-meta.php" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-bold hover:bg-purple-700">
                <i class="fa-solid fa-cubes mr-2"></i>Tipos de Meta
            </a>
            <a href="/portal/modules/marketing/admin/instancias.php" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">
                <i class="fa-solid fa-bullseye mr-2"></i>Nova Meta
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="bg-white rounded-2xl p-6 border shadow-sm text-center hover:shadow-md transition-all">
            <div class="w-16 h-16 bg-purple-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-cubes text-purple-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Tipos de Meta</h3>
            <p class="text-sm text-slate-400 mb-4">Crie modelos de metas com campos personalizados</p>
            <a href="tipos-meta.php" class="inline-block px-6 py-2.5 bg-purple-600 text-white rounded-xl font-bold">Gerenciar</a>
        </div>
        
        <div class="bg-white rounded-2xl p-6 border shadow-sm text-center hover:shadow-md transition-all">
            <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fa-solid fa-bullseye text-emerald-600 text-2xl"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Instâncias de Meta</h3>
            <p class="text-sm text-slate-400 mb-4">Crie metas específicas baseadas nos tipos</p>
            <a href="instancias.php" class="inline-block px-6 py-2.5 bg-emerald-600 text-white rounded-xl font-bold">Gerenciar Metas</a>
        </div>
    </div>

    <div class="mt-6 bg-amber-50 rounded-2xl p-4 border border-amber-200">
        <div class="flex items-center gap-3">
            <i class="fa-solid fa-info-circle text-amber-600 text-xl"></i>
            <div>
                <strong class="text-amber-800">Área Restrita</strong>
                <p class="text-sm text-amber-700">Esta área é destinada apenas para administradores e supervisores de marketing.</p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../../estrutura/footer.php'; ?>