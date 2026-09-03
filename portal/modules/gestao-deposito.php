<?php
$pageTitle = 'Gestão de Depósito | Nutricional';
$moduleJs = 'gestao-deposito.js';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../estrutura/header.php';
?>

<input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? '' ?>">
<input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? 'Operador' ?>">

<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">DEPÓSITO</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- Header -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-warehouse text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">GESTÃO DE DEPÓSITO</h2>
                <span class="text-xs text-slate-400 font-medium">Endereçamento e Localização de Lotes</span>
            </div>
        </div>
        <div class="flex gap-2">
            <button onclick="abrirModalEndereco()" class="px-4 py-2.5 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-plus"></i> Novo Endereço
            </button>
            <button onclick="abrirModalSecao()" class="px-4 py-2.5 bg-emerald-500 text-white rounded-xl font-bold hover:bg-emerald-600 transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-plus"></i> Nova Seção
            </button>
            <button onclick="carregarSecoes()" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all text-sm">
                <i class="fa-solid fa-rotate"></i>
            </button>
        </div>
    </div>

    <!-- Cards de Resumo -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Seções</span>
            <span class="block text-2xl font-black text-slate-800" id="resumoTotalSecoes">--</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Endereços</span>
            <span class="block text-2xl font-black text-blue-600" id="resumoTotalEnderecos">--</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Locais Ocupados</span>
            <span class="block text-2xl font-black text-amber-600" id="resumoOcupados">--</span>
        </div>
        <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm">
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1">Itens Armazenados</span>
            <span class="block text-2xl font-black text-emerald-600" id="resumoLotes">--</span>
        </div>
    </div>

    <!-- Lista de Seções -->
    <div id="listaSecoes" class="space-y-3">
        <p class="text-center text-slate-400 py-8">Carregando...</p>
    </div>
</div>

<!-- Modal de Seção -->
<div id="modalSecao" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalSecao()"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full shadow-2xl">
            <div class="bg-emerald-500 px-6 py-4 flex items-center justify-between rounded-t-3xl">
                <h3 class="text-lg font-bold text-white" id="modalSecaoTitulo">
                    <i class="fa-solid fa-warehouse mr-2"></i>Nova Seção
                </h3>
                <button onclick="fecharModalSecao()" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="secaoId">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Descrição *</label>
                    <input type="text" id="secaoDescricao" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold" 
                           placeholder="Ex: T - RAÇÕES 20KG">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Sigla</label>
                    <input type="text" id="secaoSigla" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold" 
                           placeholder="Ex: 20T" maxlength="10">
                </div>
                <div class="pt-4 flex gap-3">
                    <button onclick="salvarSecao()" class="flex-1 px-4 py-3 bg-emerald-500 text-white rounded-xl font-bold hover:bg-emerald-600 transition-all">
                        <i class="fa-solid fa-save mr-2"></i>Salvar
                    </button>
                    <button onclick="fecharModalSecao()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Endereço -->
<div id="modalEndereco" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalEndereco()"></div>
        <div class="relative bg-white rounded-3xl max-w-lg w-full shadow-2xl">
            <div class="bg-amber-500 px-6 py-4 flex items-center justify-between rounded-t-3xl">
                <h3 class="text-lg font-bold text-white" id="modalEnderecoTitulo">
                    <i class="fa-solid fa-map-pin mr-2"></i>Novo Endereço
                </h3>
                <button onclick="fecharModalEndereco()" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 space-y-4">
                <input type="hidden" id="enderecoId">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Seção *</label>
                    <select id="enderecoSecao" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white">
                        <option value="">Carregando...</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Rua (Letra)</label>
                        <input type="text" id="enderecoLinha" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold" placeholder="Ex: A" maxlength="2">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Posição (Número)</label>
                        <input type="text" id="enderecoColuna" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold" placeholder="Ex: 01" maxlength="3">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Sigla do Endereço</label>
                    <input type="text" id="enderecoSigla" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-bold bg-slate-50" placeholder="Gerado automaticamente" readonly>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Capacidade (Volumes)</label>
                        <input type="number" id="enderecoCapacidade" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold" placeholder="100" min="1" value="100">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nº da Linha</label>
                        <input type="number" id="enderecoNumLinha" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold" placeholder="1" min="1" value="1">
                    </div>
                </div>
                <div class="pt-4 flex gap-3">
                    <button onclick="salvarEndereco()" class="flex-1 px-4 py-3 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all">
                        <i class="fa-solid fa-save mr-2"></i>Salvar
                    </button>
                    <button onclick="fecharModalEndereco()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/portal/assets/js/gestao-deposito.js?v=<?= $version ?>"></script>

<?php require_once __DIR__ . '/../estrutura/footer.php'; ?>