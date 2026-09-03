<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<aside class="w-72 bg-gradient-to-b from-[#1e293b] to-[#0f172a] text-white fixed h-screen overflow-y-auto shadow-2xl z-30">
    
    <!-- Logo -->
    <div class="p-6 border-b border-white/10">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-10 h-10 bg-amber-400 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-shield-halved text-[#1e293b] text-lg"></i>
            </div>
            <div>
                <h3 class="font-extrabold text-lg leading-none">ADMIN</h3>
                <small class="text-white/60 text-xs font-medium">Nutricional</small>
            </div>
        </div>
        
        <!-- User Card com FOTO -->
        <div class="mt-4 p-3 bg-white/5 rounded-xl">
            <div class="flex items-center gap-3">
                <!-- Foto do usuário -->
                <div class="w-10 h-10 rounded-xl bg-slate-600 flex items-center justify-center overflow-hidden flex-shrink-0">
                   <img id="sidebarFoto" src="" class="w-full h-full object-cover hidden" 
     onerror="this.style.display='none'; document.getElementById('sidebarIniciais').style.display='flex';"
     loading="lazy">
                    <span id="sidebarIniciais" class="text-white font-bold text-sm">AD</span>
                </div>
                <div class="min-w-0">
                    <span class="text-amber-400 font-bold text-sm block truncate" id="sidebarUsername">Carregando...</span>
                    <span class="text-white/40 text-[10px] uppercase tracking-wider" id="sidebarNivel">Usuário</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Navegação -->
    <nav class="py-4">
        <div class="px-4 mb-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-white/40">Principal</span>
        </div>
        
        <a href="index.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/70 hover:text-white hover:bg-white/5 transition-all <?= $currentPage == 'index.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-gauge-high w-5 text-center text-white/50"></i>
            <span>Dashboard</span>
        </a>
            <a href="usuarios.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/70 hover:text-white hover:bg-white/5 transition-all <?= $currentPage == 'usuarios.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-users w-5 text-center text-white/50"></i>
            <span>Usuários</span>
        </a>
        
        <a href="permissoes.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/70 hover:text-white hover:bg-white/5 transition-all <?= $currentPage == 'permissoes.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-lock w-5 text-center text-white/50"></i>
            <span>Permissões</span>
        </a>
        
        <div class="px-4 mt-6 mb-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-white/40">API & Integrações</span>
        </div>
        
        <a href="api-tokens.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/70 hover:text-white hover:bg-white/5 transition-all <?= $currentPage == 'api-tokens.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-key w-5 text-center text-white/50"></i>
            <span>API Tokens</span>
        </a>
        
        <a href="crons.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/70 hover:text-white hover:bg-white/5 transition-all <?= $currentPage == 'crons.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clock w-5 text-center text-white/50"></i>
            <span>Motor de Crons</span>
        </a>
        
        <div class="px-4 mt-6 mb-3">
            <span class="text-[10px] font-bold uppercase tracking-widest text-white/40">Sistema</span>
        </div>
        
        <a href="auditoria.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/70 hover:text-white hover:bg-white/5 transition-all <?= $currentPage == 'auditoria.php' ? 'active' : '' ?>">
            <i class="fa-solid fa-clipboard-list w-5 text-center text-white/50"></i>
            <span>Auditoria</span>
        </a>
        
        <hr class="border-white/10 mx-4 my-4">
        
        <a href="/portal/index.php" class="sidebar-link-admin flex items-center gap-3 px-6 py-3 text-sm font-semibold text-white/50 hover:text-white hover:bg-white/5 transition-all">
            <i class="fa-solid fa-arrow-left w-5 text-center"></i>
            <span>Voltar ao Portal</span>
        </a>
    </nav>
</aside>

<!-- Main Content -->
<main class="flex-1 ml-72 p-8">

<style>
/* ==========================================================================
   SIDEBAR ADMIN - ESTILOS
   ========================================================================== */
.sidebar-link-admin {
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
}

.sidebar-link-admin:hover {
    border-left-color: rgba(255,255,255,0.2);
}

.sidebar-link-admin.active {
    background: rgba(251, 191, 36, 0.08);
    color: #f7be2f !important;
    border-left-color: #f7be2f;
}

.sidebar-link-admin.active i {
    color: #f7be2f !important;
}
</style>

<script>
// ======================================================================
// ATUALIZA SIDEBAR COM DADOS DO USUÁRIO LOGADO
// ======================================================================
document.addEventListener('DOMContentLoaded', function() {
    try {
        const userData = JSON.parse(localStorage.getItem('userData') || '{}');
        const username = userData.username || 'Admin';
        const permissoes = userData.permissoes || [];
        const fotoPerfil = userData.foto_perfil || null;
        
        // Nome do usuário
        const usernameEl = document.getElementById('sidebarUsername');
        if (usernameEl) usernameEl.textContent = username;
        
        // Nível de acesso
        const nivelEl = document.getElementById('sidebarNivel');
        if (nivelEl) {
            nivelEl.textContent = permissoes.includes('admin') ? 'Administrador' : 'Usuário';
        }
        
        // Foto de perfil
        const fotoEl = document.getElementById('sidebarFoto');
        const iniciaisEl = document.getElementById('sidebarIniciais');
        
        if (fotoPerfil) {
            const fotoUrl = fotoPerfil.startsWith('http') 
                ? fotoPerfil 
                : 'https://api.nutricionalbr.com/' + fotoPerfil;
            
            if (fotoEl) {
                fotoEl.src = fotoUrl;
                fotoEl.classList.remove('hidden');
                fotoEl.style.display = 'block';
            }
            if (iniciaisEl) {
                iniciaisEl.style.display = 'none';
            }
        } else {
            // Mostrar iniciais
            if (fotoEl) fotoEl.style.display = 'none';
            if (iniciaisEl) {
                iniciaisEl.style.display = 'flex';
                iniciaisEl.textContent = username.substring(0, 2).toUpperCase();
            }
        }
    } catch(e) {
        const usernameEl = document.getElementById('sidebarUsername');
        if (usernameEl) usernameEl.textContent = 'Admin';
    }
});
</script>