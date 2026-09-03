<?php
// ==========================================================================
// DASHBOARD PRINCIPAL - PORTAL NUTRICIONAL
// ==========================================================================
$pageTitle = 'Dashboard | Nutricional';
require_once __DIR__ . '/estrutura/header.php'; 
?>

<div class="flex min-h-screen bg-[#f8f8f8]" x-data="portalDashboard()" x-init="init()">

    <!-- ====================================================================== -->
    <!-- OVERLAY MOBILE -->
    <!-- ====================================================================== -->
    <div x-show="sidebarOpen" @click="sidebarOpen = false" 
    class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 lg:hidden transition-opacity"
    x-transition.opacity></div>
    
    <!-- ====================================================================== -->
    <!-- SIDEBAR -->
    <!-- ====================================================================== -->
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    class="sidebar-premium lg:translate-x-0">

    <!-- Logo com botão FECHAR -->
    <div class="px-4 sm:px-6 py-4 sm:py-6 border-b border-white/5">
        <div class="flex items-center gap-3 justify-between">
            <div class="flex items-center gap-3">
                <img src="./assets/img/logo.png" class="h-7 sm:h-8 w-auto" alt="Nutricional">
            </div>
            <button @click="sidebarOpen = false" 
            class="btn-fechar-mobile lg:hidden w-8 h-8 bg-white/10 hover:bg-white/20 rounded-lg flex items-center justify-center text-white transition-colors"
            style="display:flex;">
            <i class="fa-solid fa-times text-sm"></i>
        </button>
    </div>
</div>

<!-- User Card com FOTO -->
<div class="mx-3 sm:mx-4 mt-4 sm:mt-5 mb-2">
    <div class="bg-white/5 rounded-2xl p-3 sm:p-4 border border-white/5">
        <div class="flex items-center gap-2 sm:gap-3">
            <div class="relative flex-shrink-0">
                <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-lg sm:rounded-xl flex items-center justify-center text-white font-bold shadow-lg overflow-hidden"
                :class="fotoPerfil ? 'bg-transparent' : 'bg-slate-600'">
                <img :src="fotoPerfil" x-show="fotoPerfil" class="w-full h-full object-cover"
                style="display:none;"
                @load="$el.style.display='block'; $el.nextElementSibling.style.display='none';"
                @error="$el.style.display='none'; $el.nextElementSibling.style.display='flex';">
                <span x-show="!fotoPerfil" x-text="userIniciais" style="display:flex; font-size:11px;" class="sm:text-sm"></span>
            </div>
            <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 sm:w-3.5 sm:h-3.5 bg-emerald-400 rounded-full border-2 border-[#1e293b]"></span>
        </div>
        <div class="flex-1 min-w-0">
            <p class="text-white font-bold text-xs sm:text-sm truncate" x-text="username"></p>
            <p class="text-slate-400 text-[8px] sm:text-[10px] uppercase tracking-wider truncate" x-text="nivelAcesso"></p>
        </div>
        <button @click="abrirPerfil()" class="p-1 sm:p-1.5 rounded-lg hover:bg-white/10 transition-colors flex-shrink-0" title="Editar Perfil">
            <i class="fa-solid fa-pen-to-square text-slate-400 text-[10px] sm:text-xs"></i>
        </button>
    </div>
</div>
</div>

<!-- Navegação -->
<nav class="mt-4 pb-6">
    <div class="px-6 mb-2"><span class="sidebar-section-title">Principal</span></div>
    <a href="/portal/" class="sidebar-link-premium active flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
        <i class="fa-solid fa-gauge-high w-5 text-center"></i><span>Dashboard</span>
    </a>
    <a href="#" @click.prevent="abrirPerfil()" class="sidebar-link-premium flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
        <i class="fa-solid fa-user-gear w-5 text-center"></i><span>Meu Perfil</span>
    </a>

    <div class="px-6 mt-5 mb-2"><span class="sidebar-section-title">Setores</span></div>
 <template x-for="setor in setoresVisiveis" :key="setor.nome">
    <a :href="`#setor-${setor.nome}`" @click.prevent="scrollToSetor(setor.nome); sidebarOpen = false"
       class="sidebar-link-premium flex items-center gap-3 px-4 py-2.5 text-sm font-medium transition-all"
       :class="setorAtivo === setor.nome ? 'bg-white/10 text-white shadow-sm' : 'text-slate-300 hover:bg-white/5'">
        <i :class="`fas ${setor.icone} w-5 text-center`"></i>
        <span x-text="setor.nome"></span>
        <span class="ml-auto text-[10px]" :class="setorAtivo === setor.nome ? 'text-white/60' : 'text-slate-500'" 
              x-text="setor.modulos.length"></span>
        <!-- Indicador visual de ativo -->
        <div x-show="setorAtivo === setor.nome" 
             class="absolute left-0 w-1 h-6 bg-amber-400 rounded-r-full"></div>
    </a>
</template>

<div class="px-6 mt-5 mb-2"><span class="sidebar-section-title">Comunicação</span></div>
<a href="/portal/modules/minhas-mensagens.php" class="sidebar-link-premium flex items-center gap-3 px-4 py-2.5 text-sm font-medium">
    <i class="fa-solid fa-comments w-5 text-center"></i><span>Mensagens</span>
    <span id="sidebarChatBadge" class="ml-auto px-2 py-0.5 bg-red-500 text-white rounded-full text-[10px] font-bold hidden">0</span>
</a>

<div class="px-6 mt-5 mb-2"><span class="sidebar-section-title">Sistema</span></div>
<a href="#" @click.prevent="logout()" class="sidebar-link-premium flex items-center gap-3 px-4 py-2.5 text-sm font-medium text-rose-300/70 hover:text-rose-300 hover:bg-rose-500/10">
    <i class="fa-solid fa-right-from-bracket w-5 text-center text-rose-400"></i><span>Sair do Sistema</span>
</a>
</nav>

<div class="px-6 py-4 border-t border-white/5 mt-auto">
    <p class="text-[9px] text-slate-500 text-center">© <?= date('Y') ?> Nutricional Distribuidora | Alan M. Santos</p>
</div>
</aside>

<!-- ====================================================================== -->
<!-- MAIN CONTENT -->
<!-- ====================================================================== -->
<div class="main-content-premium flex-1 flex flex-col min-h-screen">

    <!-- HEADER TOPO -->
    <div class="header-topo px-4 lg:px-8 py-3 flex items-center justify-between">
        <button @click="sidebarOpen = !sidebarOpen" 
        class="header-menu-mobile w-10 h-10 bg-slate-100 hover:bg-slate-200 rounded-xl flex items-center justify-center text-slate-600 transition-colors" 
        style="display:flex;">
        <i class="fa-solid fa-bars"></i>
    </button>

    <div class="header-saudacao-desktop items-center gap-3" style="display:none;">
        <div class="flex items-center gap-4">
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-[#375a4b] to-[#4a7a67] rounded-2xl flex items-center justify-center shadow-lg shadow-[#375a4b]/20">
                    <span class="text-white text-lg">👋</span>
                </div>
                <span class="absolute -top-1 -right-1 w-3 h-3 bg-amber-400 rounded-full border-2 border-white animate-pulse"></span>
            </div>
            <div>
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-widest" x-text="saudacao"></span>
                <h3 class="text-base font-extrabold text-slate-800 leading-tight">
                    <span x-text="username"></span><span class="text-amber-400 ml-1">!</span>
                </h3>
            </div>
            <div class="w-px h-10 bg-gradient-to-b from-transparent via-slate-200 to-transparent mx-2"></div>
            <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 rounded-xl border border-amber-100">
                <i class="fa-regular fa-calendar-check text-amber-500 text-sm"></i>
                <span class="text-xs font-semibold text-amber-700 capitalize tracking-wide" x-text="dataHoje"></span>
            </div>
        </div>
    </div>

    <div class="header-relogio-desktop items-center gap-4" style="display:none;">
        <div class="flex items-center gap-3 bg-slate-100 hover:bg-slate-200 transition-colors rounded-2xl px-5 py-2.5 cursor-default">
            <i class="fa-regular fa-clock text-[#375a4b] text-sm"></i>
            <div class="text-2xl font-black text-[#375a4b] font-['Chivo_Mono'] tracking-tight tabular-nums" id="relogio-dashboard">00:00:00</div>
        </div>
    </div>
    <!-- Sino de Notificações -->
    <div class="relative" x-data="notificacoesHandler()" x-init="init()">
        <button @click="abrirNotificacoes()" class="relative p-2 rounded-xl hover:bg-slate-100 transition-colors">
            <i class="fa-solid fa-bell text-slate-500 text-lg"></i>
            <span x-show="naoLidas > 0" x-text="naoLidas > 99 ? '99+' : naoLidas" 
              class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[9px] font-bold w-4 h-4 rounded-full flex items-center justify-center"
              style="display: none;"></span>
          </button>

          <!-- Dropdown -->
          <div x-show="open" @click.outside="open = false" x-transition
          class="absolute right-0 mt-2 w-80 bg-white rounded-2xl shadow-xl border border-slate-200 z-50 max-h-96 overflow-y-auto"
          style="display: none;">
          <div class="p-4 border-b border-slate-100 flex justify-between items-center">
            <h4 class="font-bold text-slate-800">Notificações</h4>
            <button @click="marcarTodasLidas()" class="text-xs text-[#375a4b] font-bold hover:underline">Marcar todas lidas</button>
        </div>
        <div class="divide-y divide-slate-100">
            <template x-for="n in notificacoes" :key="n.id">
                <a :href="n.link || '#'" @click="marcarLida(n.id)" 
                class="block p-4 hover:bg-slate-50 transition-colors cursor-pointer"
                :class="!n.lida ? 'bg-amber-50/50' : ''">
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0"
                    :class="n.tipo === 'meta_prazo' ? 'bg-amber-100 text-amber-600' : 
                    n.tipo === 'lead_parado' ? 'bg-rose-100 text-rose-600' : 
                    'bg-blue-100 text-blue-600'">
                    <i class="fa-solid text-xs" 
                    :class="n.tipo === 'meta_prazo' ? 'fa-clock' : 
                    n.tipo === 'lead_parado' ? 'fa-user-clock' : 'fa-bell'"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-bold text-slate-800" x-text="n.titulo"></p>
                    <p class="text-xs text-slate-500 mt-0.5" x-text="n.mensagem"></p>
                    <p class="text-[10px] text-slate-400 mt-1" x-text="new Date(n.created_at).toLocaleString('pt-BR')"></p>
                </div>
                <span x-show="!n.lida" class="w-2 h-2 bg-amber-400 rounded-full flex-shrink-0 mt-1"></span>
            </div>
        </a>
    </template>
    <div x-show="notificacoes.length === 0" class="p-8 text-center">
        <i class="fa-solid fa-bell-slash text-3xl text-slate-300 mb-2 block"></i>
        <p class="text-sm text-slate-400">Nenhuma notificação</p>
    </div>
</div>
</div>
</div>

<div class="header-mobile-info flex items-center gap-3" style="display:flex;">
    <div class="flex items-center gap-2 bg-slate-100 rounded-xl px-3 py-1.5">
        <i class="fa-regular fa-clock text-[#375a4b] text-xs"></i>
        <div class="text-sm font-bold text-[#375a4b] font-['Chivo_Mono']" id="relogio-dashboard-mobile">00:00</div>
    </div>
    <button @click="abrirPerfil()" 
    class="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-xs shadow-md overflow-hidden"
    :class="fotoPerfil ? 'bg-transparent' : 'bg-gradient-to-br from-[#375a4b] to-[#4a7a67]'">
    <img :src="fotoPerfil" x-show="fotoPerfil" class="w-full h-full object-cover"
    style="display:none;"
    @load="$el.style.display='block'; $el.nextElementSibling.style.display='none';"
    @error="$el.style.display='none'; $el.nextElementSibling.style.display='flex';">
    <span x-show="!fotoPerfil" x-text="userIniciais" style="display:flex;"></span>
</button>
</div>
</div>

<!-- CONTEÚDO -->
<div class="flex-1 p-4 lg:p-8">
    <div class="mb-8">
        <div class="relative max-w-md">
            <i class="fa-solid fa-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
            <input type="text" placeholder="Buscar módulo..." 
            @input="filtrarModulos($event.target.value)"
            class="w-full pl-11 pr-4 py-3 bg-white border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-[#375a4b] focus:border-[#375a4b] transition-all">
        </div>
    </div>

    <select class="pills-select-mobile" style="display:none;" @change="scrollToSetor($event.target.value)">
        <option value="todos">Todos os Setores</option>
        <template x-for="setor in setoresVisiveis" :key="setor.nome">
            <option :value="setor.nome" x-text="setor.nome"></option>
        </template>
    </select>

    <div class="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-hide">
        <button @click="scrollToSetor('todos')" 
        class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap"
        :class="setorAtivo === 'todos' ? 'bg-[#375a4b] text-white shadow-lg shadow-[#375a4b]/20' : 'bg-white text-slate-500 hover:bg-slate-100 border border-slate-200'">
        <i class="fa-solid fa-grid-2 text-[10px]"></i> Todos
    </button>
    <template x-for="setor in setoresVisiveis" :key="setor.nome">
        <button @click="scrollToSetor(setor.nome)" 
        class="flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap border"
        :class="setorAtivo === setor.nome ? 'bg-[#375a4b] text-white shadow-lg shadow-[#375a4b]/20 border-[#375a4b]' : 'bg-white text-slate-500 hover:bg-slate-100 border-slate-200'">
        <i :class="`fas ${setor.icone} text-[10px]`"></i>
        <span x-text="setor.nome"></span>
    </button>
</template>
</div>
<template x-for="setor in setoresFiltrados" :key="setor.nome">
    <div class="mb-10" :id="`setor-${setor.nome}`">
        <!-- Cabeçalho do setor COM BOTÃO CLICÁVEL -->
        <div class="flex items-center gap-3 mb-5 cursor-pointer group" 
             @click="toggleSetor(setor.nome)">
            <div :class="`w-10 h-10 ${setor.corBg} ${setor.corTexto} rounded-xl flex items-center justify-center shadow-sm group-hover:scale-105 transition-transform`">
                <i :class="`fas ${setor.icone} text-base`"></i>
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-2">
                    <h3 class="text-lg font-bold text-slate-800" x-text="setor.nome"></h3>
                    <span class="text-xs text-slate-400" x-text="`${setor.modulos.length} módulo(s)`"></span>
                </div>
            </div>
            <!-- Ícone de recolher/expandir -->
            <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-slate-100 text-slate-500 transition-transform duration-300"
                 :class="setor.recolhido ? '' : 'rotate-180'">
                <i class="fa-solid fa-chevron-up text-xs"></i>
            </div>
        </div>
        
        <!-- Grid dos módulos - usando x-transition NATIVA (SEM plugin) -->
        <div x-show="!setor.recolhido" 
             x-transition.opacity.duration.300ms
             class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-4">
            <template x-for="(mod, index) in setor.modulos" :key="mod.id">
                <a :href="mod.url" :style="`animation-delay: ${index * 50}ms`"
                   class="stat-card-premium animate-fadeInUp group relative flex flex-col h-full overflow-hidden bg-white rounded-xl p-4 shadow-sm hover:shadow-md transition-all border border-slate-100">
                    <div class="flex items-start justify-between mb-4">
                        <div :class="`w-12 h-12 ${mod.bgColor || mod.cor_bg || 'bg-slate-50'} ${mod.iconColor || mod.cor_text || 'text-slate-600'} rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300 shadow-sm`">
                            <i :class="`fas ${mod.icon} text-lg`"></i>
                        </div>
                        <template x-if="mod.id === 'admin'">
                            <span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[9px] font-bold">ADMIN</span>
                        </template>
                    </div>
                    <div class="flex-1">
                        <h4 class="font-bold text-slate-800 text-sm mb-1.5" x-text="mod.nome"></h4>
                        <p class="text-xs text-slate-400 leading-relaxed line-clamp-2" x-text="mod.desc || mod.descricao"></p>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-300 group-hover:text-[#375a4b] transition-colors">Acessar</span>
                        <i class="fa-solid fa-arrow-right text-[10px] text-slate-300 group-hover:text-[#375a4b] group-hover:translate-x-1 transition-all"></i>
                    </div>
                </a>
            </template>
        </div>
        
        <!-- Mensagem quando recolhido -->
        <div x-show="setor.recolhido" 
             x-transition.opacity.duration.200ms
             class="text-center py-4 text-slate-400 text-sm italic border border-dashed border-slate-200 rounded-2xl bg-slate-50/50">
            <i class="fa-solid fa-folder-closed mr-2"></i>
            Setor recolhido - clique para expandir
        </div>
    </div>
</template>

<div x-show="totalModulos === 0" class="text-center py-20">
    <div class="w-24 h-24 bg-slate-100 rounded-3xl flex items-center justify-center mx-auto mb-6">
        <i class="fa-solid fa-cubes text-slate-300 text-4xl"></i>
    </div>
    <h3 class="text-xl font-bold text-slate-600">Nenhum módulo encontrado</h3>
    <p class="text-slate-400 mt-2">Tente ajustar sua busca ou entre em contato com o administrador.</p>
</div>
</div>

<?php require_once __DIR__ . '/estrutura/footer.php'; ?>
</div>

<!-- ====================================================================== -->
<!-- MODAL DE PERFIL COMPLETO -->
<!-- ====================================================================== -->
<div x-show="modalPerfilOpen" x-cloak @click.self="modalPerfilOpen = false" 
class="fixed inset-0 z-[60] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm"
x-transition.opacity>

<div class="bg-white rounded-3xl shadow-2xl w-full max-w-lg overflow-hidden animate-fadeInUp" @click.outside="modalPerfilOpen = false">
    <div class="bg-gradient-to-r from-[#375a4b] to-[#4a7a67] px-6 py-5 flex items-center justify-between">
        <h3 class="text-lg font-bold text-white"><i class="fa-solid fa-user-gear mr-2"></i>Meu Perfil</h3>
        <button @click="modalPerfilOpen = false" class="p-1.5 rounded-lg hover:bg-white/10 text-white/70 hover:text-white transition-colors">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>

    <div class="p-6 space-y-5 max-h-[70vh] overflow-y-auto">
        <!-- Foto -->
        <div class="text-center">
            <div class="relative inline-block">
                <div class="w-24 h-24 rounded-full bg-slate-100 flex items-center justify-center overflow-hidden border-4 border-[#375a4b]/20 mx-auto shadow-lg">
                    <img :src="perfilFotoPreview || perfilFoto" x-show="perfilFotoPreview || perfilFoto" 
                    class="w-full h-full object-cover" style="display:none;"
                    @load="$el.style.display='block'; $el.nextElementSibling.style.display='none';"
                    @error="$el.style.display='none'; $el.nextElementSibling.style.display='flex';">
                    <span x-show="!perfilFotoPreview && !perfilFoto" x-text="userIniciais" 
                    class="text-3xl font-bold text-slate-400" style="display:flex;"></span>
                </div>
                <label for="inputFotoPerfil" class="absolute -bottom-1 -right-1 w-9 h-9 bg-[#375a4b] text-white rounded-full flex items-center justify-center cursor-pointer hover:bg-[#4a7a67] transition-colors shadow-lg">
                    <i class="fa-solid fa-camera text-sm"></i>
                </label>
                <input type="file" id="inputFotoPerfil" accept="image/*" @change="previewFotoPerfil($event)" class="hidden">
            </div>
            <p class="text-[10px] text-slate-400 mt-2">Clique na câmera para alterar a foto</p>
        </div>

        <!-- Dados Pessoais -->
        <div class="bg-slate-50 rounded-2xl p-4 space-y-3">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fa-solid fa-id-card mr-1"></i> Dados Pessoais</h4>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Nome</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.fantasia || '---'"></p></div>
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Usuário</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.username || '---'"></p></div>
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Cargo</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.cargo || '---'"></p></div>
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Nascimento</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.datanascimento ? new Date(perfilDados.datanascimento).toLocaleDateString('pt-BR') : '---'"></p></div>
            </div>
        </div>

        <!-- Contato -->
        <div class="bg-slate-50 rounded-2xl p-4 space-y-3">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fa-solid fa-address-book mr-1"></i> Contato</h4>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Email</label><p class="text-sm font-semibold text-slate-800 truncate" x-text="perfilDados.email || '---'"></p></div>
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Telefone</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.fone || '---'"></p></div>
            </div>
        </div>

        <!-- Endereço -->
        <div class="bg-slate-50 rounded-2xl p-4 space-y-3">
            <h4 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-3"><i class="fa-solid fa-location-dot mr-1"></i> Endereço</h4>
            <div class="space-y-2">
                <div><label class="text-[10px] font-bold text-slate-400 uppercase">Endereço</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.endereco || '---'"></p></div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-[10px] font-bold text-slate-400 uppercase">Bairro</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.bairro || '---'"></p></div>
                    <div><label class="text-[10px] font-bold text-slate-400 uppercase">Cidade/UF</label><p class="text-sm font-semibold text-slate-800" x-text="(perfilDados.cidade || '---') + '/' + (perfilDados.uf || '--')"></p></div>
                    <div><label class="text-[10px] font-bold text-slate-400 uppercase">CEP</label><p class="text-sm font-semibold text-slate-800" x-text="perfilDados.cep || '---'"></p></div>
                </div>
            </div>
        </div>

        <!-- Alterar Senha -->
        <div class="bg-amber-50 rounded-2xl p-4 border border-amber-100">
            <h4 class="text-xs font-bold text-amber-700 uppercase tracking-wider mb-3"><i class="fa-solid fa-lock mr-1"></i> Alterar Senha</h4>
            <form @submit.prevent="" class="space-y-3">
                <input type="text" x-model="perfilUsername" autocomplete="username" style="display:none; position:absolute; opacity:0; width:0; height:0; padding:0; margin:0; border:0;">
                <input type="password" x-model="perfilSenha" placeholder="Nova senha" autocomplete="new-password"
                class="w-full p-2.5 border-2 border-amber-200 rounded-xl text-sm focus:border-amber-400 transition-all">
                <input type="password" x-model="perfilSenhaConfirm" placeholder="Confirmar senha" autocomplete="new-password"
                class="w-full p-2.5 border-2 border-amber-200 rounded-xl text-sm focus:border-amber-400 transition-all">
            </form>
        </div>

        <div id="perfilError" class="hidden bg-rose-50 border border-rose-200 text-rose-600 px-4 py-3 rounded-xl text-sm font-medium"></div>
        <div id="perfilSuccess" class="hidden bg-emerald-50 border border-emerald-200 text-emerald-600 px-4 py-3 rounded-xl text-sm font-medium"></div>
    </div>

    <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3">
        <button @click="salvarPerfil()" class="flex-1 px-4 py-3 bg-[#375a4b] text-white rounded-xl font-bold hover:bg-[#4a7a67] transition-all">
            <i class="fa-solid fa-save mr-2"></i>Salvar
        </button>
        <button @click="modalPerfilOpen = false" class="px-4 py-3 bg-white border border-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-100 transition-all">
            Cancelar
        </button>
    </div>
</div>
</div>
</div>

<!-- ====================================================================== -->
<!-- SCRIPT DO RELÓGIO -->
<!-- ====================================================================== -->
<script>
    setInterval(() => {
        const agora = new Date();
        const horaFormatada = agora.toLocaleTimeString('pt-br');
        const relogioDesktop = document.getElementById('relogio-dashboard');
        const relogioMobile = document.getElementById('relogio-dashboard-mobile');
        if (relogioDesktop) relogioDesktop.innerText = horaFormatada;
        if (relogioMobile) relogioMobile.innerText = horaFormatada;
    }, 1000);
</script>

<!-- ====================================================================== -->
<!-- ALPINE.JS - LÓGICA DO DASHBOARD -->
<!-- ====================================================================== -->
<script>
function portalDashboard() {
    return {
        sidebarOpen: false,
        modalPerfilOpen: false,
        username: 'Usuário', userIniciais: 'UN', nivelAcesso: 'Usuário',
        saudacao: '', dataHoje: '', setores: [], setoresVisiveis: [], setoresFiltrados: [],
        setorAtivo: 'todos', totalModulos: 0,
        perfilUsername: '', perfilSenha: '', perfilSenhaConfirm: '',
        fotoPerfil: null, perfilDados: {}, perfilFoto: null, perfilFotoPreview: null, perfilFotoFile: null,
        scrollListener: null,
        init() {
            this.modalPerfilOpen = false;
            const userData = JSON.parse(localStorage.getItem('userData') || '{}');
            this.username = userData.username || 'Usuário';
            const foto = userData.foto_perfil;
           this.fotoPerfil = foto ? (foto.startsWith('http') ? foto : `https://api.nutricionalbr.com/${foto}`) : null;
            this.userIniciais = this.username.substring(0, 2).toUpperCase();
            this.perfilUsername = this.username;
            const permissoes = userData.permissoes || [];
            this.nivelAcesso = permissoes.includes('admin') ? 'Administrador' : 'Operador';
            const hora = new Date().getHours();
            this.saudacao = hora < 12 ? 'Bom dia' : hora < 18 ? 'Boa tarde' : 'Boa noite';
            this.dataHoje = new Date().toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
            
            // Carrega módulos e setores do banco via API
            this.carregarModulosSetores(permissoes);
              this.initScrollSpy(); 
        },

        async carregarModulosSetores(permissoes) {
            try {
                const resp = await fetch(`${API_URL}/sistema/modulos-setores`);
                const data = await resp.json();
                
                const setoresData = data.setores || [];
                const modulosData = data.modulos || [];
                
                // Mapa de setores do banco
                const setoresMap = {};
                setoresData.forEach(s => {
                    setoresMap[s.nome] = {
                        nome: s.nome,
                        icone: s.icone,
                        corBg: s.corbg || s.corBg,
                        corTexto: s.cortexto || s.corTexto
                    };
                });
                
                // Filtra módulos por permissão do usuário
                const modulosPermitidos = modulosData.filter(mod => permissoes.includes(mod.slug));
                
                // Agrupa por setor
                const modulosPorSetor = {};
                modulosPermitidos.forEach(mod => {
                    const sn = mod.setor || 'Geral';
                    if (!modulosPorSetor[sn]) modulosPorSetor[sn] = [];
                    modulosPorSetor[sn].push({
                        id: mod.slug,
                        nome: mod.nome,
                        desc: mod.desc || mod.descricao,
                        icon: mod.icon,
                        bgColor: mod.corbg || mod.corBg || 'bg-slate-50',
                        iconColor: mod.cortexto || mod.corTexto || 'text-slate-600',
                        url: mod.url,
                        setor: sn
                    });
                });
                
                // Ordena setores conforme banco
                const ordemSetores = setoresData.map(s => s.nome);
                
                this.setores = Object.keys(modulosPorSetor)
                    .map(nome => ({
                        ...(setoresMap[nome] || { nome, icone: 'fa-folder', corBg: 'bg-slate-100', corTexto: 'text-slate-600' }),
                        modulos: modulosPorSetor[nome],
                        recolhido: false
                    }))
                    .sort((a, b) => ordemSetores.indexOf(a.nome) - ordemSetores.indexOf(b.nome));
                
                this.setoresVisiveis = [...this.setores];
                this.setoresFiltrados = [...this.setores];
                this.totalModulos = modulosPermitidos.length;
                
            } catch (e) {
                console.error('Erro ao carregar módulos:', e);
            }
        },
        
        toggleSetor(nome) {
            const setor = this.setoresFiltrados.find(s => s.nome === nome);
            if (setor) {
                setor.recolhido = !setor.recolhido;
            }
        },
        
        filtrarModulos(busca) {
            if (!busca) {
                this.setoresFiltrados = [...this.setoresVisiveis];
                this.totalModulos = this.setoresVisiveis.reduce((acc, s) => acc + s.modulos.length, 0);
                return;
            }
            const termo = busca.toLowerCase();
            this.setoresFiltrados = this.setoresVisiveis.map(setor => ({
                ...setor,
                modulos: setor.modulos.filter(mod => 
                    mod.nome.toLowerCase().includes(termo) || 
                    mod.desc.toLowerCase().includes(termo)
                )
            })).filter(setor => setor.modulos.length > 0);
            this.totalModulos = this.setoresFiltrados.reduce((acc, s) => acc + s.modulos.length, 0);
        },
        
  

// Substitua a função scrollToSetor:
scrollToSetor(nome) {
    this.setorAtivo = nome;
    if (nome === 'todos') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
    }
    const el = document.getElementById(`setor-${nome}`);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
},

// Adicione esta função para scroll spy:
initScrollSpy() {
    if (this.scrollListener) window.removeEventListener('scroll', this.scrollListener);
    
    this.scrollListener = () => {
        const setores = this.setoresFiltrados;
        if (!setores.length) return;
        
        // Encontra qual setor está visível na viewport
        let setorAtual = 'todos';
        const scrollPosition = window.scrollY + 150; // offset do header
        
        for (const setor of setores) {
            const el = document.getElementById(`setor-${setor.nome}`);
            if (el) {
                const offsetTop = el.offsetTop;
                const offsetBottom = offsetTop + el.offsetHeight;
                if (scrollPosition >= offsetTop && scrollPosition < offsetBottom) {
                    setorAtual = setor.nome;
                    break;
                }
            }
        }
        
        this.setorAtivo = setorAtual;
    };
    
    window.addEventListener('scroll', this.scrollListener);
    // Executa uma vez para setar o estado inicial
    setTimeout(() => this.scrollListener(), 100);
},
        // ======================================================================
        // PERFIL
        // ======================================================================
        abrirPerfil() {
            this.sidebarOpen = false;
            this.perfilSenha = '';
            this.perfilSenhaConfirm = '';
            this.perfilFotoFile = null;
            this.perfilFotoPreview = null;
            const errEl = document.getElementById('perfilError');
            const sucEl = document.getElementById('perfilSuccess');
            if (errEl) errEl.classList.add('hidden');
            if (sucEl) sucEl.classList.add('hidden');
            this.modalPerfilOpen = true;
            this.carregarDadosPerfil();
        },
        
        async carregarDadosPerfil() {
            try {
                const token = localStorage.getItem('authToken');
                const resp = await fetch(`${API_URL}/perfil/dados`, { 
                    headers: { 'Authorization': 'Bearer ' + token } 
                });
                this.perfilDados = await resp.json();
                this.perfilFoto = this.perfilDados?.foto_url || null;
            } catch(e) {}
        },
        
        previewFotoPerfil(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (file.size > 5 * 1024 * 1024) { 
                alert('Foto muito grande. Máximo 5MB.'); 
                return; 
            }
            this.perfilFotoFile = file;
            const reader = new FileReader();
            reader.onload = (e) => { this.perfilFotoPreview = e.target.result; };
            reader.readAsDataURL(file);
        },
        
        async uploadFotoPerfil() {
            if (!this.perfilFotoFile) return true;
            const formData = new FormData();
            formData.append('foto', this.perfilFotoFile);
            formData.append('idusuario', JSON.parse(localStorage.getItem('userData') || '{}').idusuario || 0);
            const token = localStorage.getItem('authToken');
           const resp = await fetch(`${API_URL}/admin/upload-foto`, { 
                method: 'POST', 
                headers: { 'Authorization': 'Bearer ' + token }, 
                body: formData 
            });
            const data = await resp.json();
            if (data.success) { 
                const userData = JSON.parse(localStorage.getItem('userData') || '{}'); 
                userData.foto_perfil = data.foto_url; 
                localStorage.setItem('userData', JSON.stringify(userData)); 
                this.fotoPerfil = data.foto_url; 
                this.perfilFoto = data.foto_url; 
                return true; 
            }
            return false;
        },
        
        async salvarPerfil() {
            const errEl = document.getElementById('perfilError');
            const sucEl = document.getElementById('perfilSuccess');
            if (errEl) errEl.classList.add('hidden');
            if (sucEl) sucEl.classList.add('hidden');
            
            if (this.perfilSenha && this.perfilSenha !== this.perfilSenhaConfirm) { 
                if (errEl) { 
                    errEl.textContent = 'As senhas não conferem.'; 
                    errEl.classList.remove('hidden'); 
                } 
                return; 
            }
            
            if (this.perfilFotoFile) { 
                const fotoOk = await this.uploadFotoPerfil(); 
                if (!fotoOk) { 
                    if (errEl) { 
                        errEl.textContent = 'Erro ao enviar foto.'; 
                        errEl.classList.remove('hidden'); 
                    } 
                    return; 
                } 
            }
            
            if (this.perfilSenha) {
                try {
                    const token = localStorage.getItem('authToken');
                    const resp = await fetch(`${API_URL}/auth/alterar-senha`, { 
                        method: 'POST', 
                        headers: { 
                            'Content-Type': 'application/json', 
                            'Authorization': 'Bearer ' + token 
                        }, 
                        body: JSON.stringify({ senha: this.perfilSenha }) 
                    });
                    const data = await resp.json();
                    if (data.success) { 
                        if (sucEl) { 
                            sucEl.textContent = 'Perfil atualizado!'; 
                            sucEl.classList.remove('hidden'); 
                        } 
                        setTimeout(() => this.modalPerfilOpen = false, 1500); 
                    } else { 
                        if (errEl) { 
                            errEl.textContent = data.error || 'Erro.'; 
                            errEl.classList.remove('hidden'); 
                        } 
                    }
                } catch(e) { 
                    if (errEl) { 
                        errEl.textContent = 'Erro de conexão.'; 
                        errEl.classList.remove('hidden'); 
                    } 
                }
            } else if (this.perfilFotoFile) { 
                if (sucEl) { 
                    sucEl.textContent = 'Foto atualizada!'; 
                    sucEl.classList.remove('hidden'); 
                } 
                setTimeout(() => this.modalPerfilOpen = false, 1500); 
            }
        },
        
        logout() { 
            localStorage.removeItem('authToken'); 
            localStorage.removeItem('userData'); 
            window.location.href = '/portal/login.php'; 
        }
    }
}

function notificacoesHandler() {
    return {
        open: false,
        notificacoes: [],
        naoLidas: 0,
        
        async init() {
            await this.carregar();
            setInterval(() => this.carregar(), 60000);
        },
        
        async carregar() {
            try {
                const token = localStorage.getItem('authToken');
                const resp = await fetch(`${API_URL}/notificacoes`, {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const data = await resp.json();
                this.notificacoes = data.notificacoes || [];
                this.naoLidas = data.nao_lidas || 0;
            } catch(e) {}
        },
        
        abrirNotificacoes() {
            this.open = !this.open;
            if (this.open) this.carregar();
        },
        
        async marcarLida(id) {
            try {
                const token = localStorage.getItem('authToken');
                await fetch(`${API_URL}/notificacoes/${id}/ler`, {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                this.carregar();
            } catch(e) {}
        },
        
        async marcarTodasLidas() {
            try {
                const token = localStorage.getItem('authToken');
                await fetch(`${API_URL}/notificacoes/ler-todas`, {
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                this.carregar();
            } catch(e) {}
        }
    }
}
</script>

<!-- ====================================================================== -->
<!-- BADGE DE MENSAGENS -->
<!-- ====================================================================== -->
<script>
    async function atualizarChatBadge() {
        try {
            const resp = await fetch(`${API_URL}/chat/nao-lidas`, { headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') } });
            const data = await resp.json();
            const badge = document.getElementById('sidebarChatBadge');
            if (badge && data.total > 0) { badge.textContent = data.total > 99 ? '99+' : data.total; badge.classList.remove('hidden'); }
            else if (badge) { badge.classList.add('hidden'); }
        } catch(e) {}
    }
    atualizarChatBadge();
    setInterval(atualizarChatBadge, 30000);
</script>