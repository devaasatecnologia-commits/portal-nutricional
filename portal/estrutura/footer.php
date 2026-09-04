<footer class="portal-footer-simple">
    <div class="footer-content">
        <span class="copyright">
            © <?= date('Y') ?> Nutricional Distribuidora.
        </span>
        <!--span class="footer-separator">|</span>
        <span class="developer-credit">
            <i class="fa fa-code"></i>
            Desenvolvido por T.I Nutricional
        </span-->
    </div>
</footer>

<!-- ====================================================================== -->
<!-- CHAT FLUTUANTE - MÚLTIPLOS BALÕES -->
<!-- ====================================================================== -->
<div id="chatWidget" class="chat-widget">
    <!-- Botão flutuante -->
    <button id="chatToggle" onclick="toggleChat()" class="chat-toggle-btn">
        <i class="fa-solid fa-comments"></i>
        <span id="chatBadge" class="chat-badge hidden">0</span>
    </button>
    
    <!-- Janela do Chat -->
    <div id="chatWindow" class="chat-window hidden">
        <!-- Cabeçalho -->
      <div class="chat-header">
    <div class="header-title">
        <i class="fa-brands fa-whatsapp text-xl"></i>
        <span>Chat Interno</span>
    </div>
    <div class="header-actions">
        <!-- Botão Nova Conversa -->
        <button onclick="mostrarContatos()" class="chat-btn-icon" title="Nova Conversa">
            <i class="fa-solid fa-plus"></i>
        </button>
        <button onclick="minimizarTodasConversas()" class="chat-btn-icon" title="Conversas">
            <i class="fa-solid fa-message"></i>
        </button>
        <button onclick="toggleChat()" class="chat-btn-icon" title="Fechar">
            <i class="fa-solid fa-times"></i>
        </button>
    </div>
</div>
        
        <!-- Corpo -->
        <div id="chatBody" style="display:flex; flex-direction:column; height:calc(100% - 50px);">
            <!-- Abas de conversas -->
            <div id="chatTabs" class="chat-tabs"></div>
            
            <!-- Área de conversa ativa -->
            <div id="chatConversaAtiva" class="chat-conversa-ativa" style="flex:1; display:flex; flex-direction:column; overflow:hidden;">
                <!-- Lista de Contatos (inicial) -->
                <div id="listaContatos" style="flex:1; overflow-y:auto;">
                    <div class="chat-search">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchContato" placeholder="Buscar usuário..." oninput="filtrarContatos()">
                    </div>
                    <div id="contatosLista" class="contatos-lista">
                        <p class="text-center text-slate-400 text-xs py-4">Carregando...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- CSS do Chat -->
<link rel="stylesheet" href="<?= asset('/portal/assets/css/chat.css') ?>">

<!-- Silenciar console em produção (ANTES dos outros scripts) -->
<script>
if (window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
    console.log = function() {};
    console.debug = function() {};
    console.info = function() {};
}
</script>

<!-- Scripts essenciais -->
<script src="https://unpkg.com/html5-qrcode"></script>
<script src="<?= asset('/portal/assets/js/auth.js') ?>"></script>
<script src="<?= asset('/portal/assets/js/portal.js') ?>"></script>


<!-- Script do Chat (DEPOIS do core.js) -->
<script src="<?= asset('/portal/assets/js/chat.js') ?>"></script>

<?php if (isset($moduleJs)): ?>
    <script src="<?= asset('/portal/assets/js/' . $moduleJs) ?>"></script>
<?php endif; ?>

<!-- Atualiza elementos do header legado -->
<script>
(function() {
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    const username = userData.username || 'Usuário';
    const userIniciais = username.substring(0, 2).toUpperCase();
    const permissoes = userData.permissoes || [];
    const isAdmin = permissoes.includes('admin');
    const nivel = isAdmin ? 'Administrador' : 'Operador';
    const uid = userData.uid || '---';

    function updateElement(id, value) {
        const el = document.getElementById(id);
        if (el && value && (!el.textContent.trim() || el.textContent === 'Carregando...' || el.textContent === 'UN' || el.textContent === 'Usuário' || el.textContent === 'Operador')) {
            el.textContent = value;
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        updateElement('userDisplay', username);
        updateElement('userAvatar', userIniciais);
        updateElement('userAvatarLarge', userIniciais);
        updateElement('userNivel', nivel);
        updateElement('userNivelDropdown', nivel);
        updateElement('userNameDropdown', username);
        updateElement('usernameHeader', username);
        updateElement('saudacaoHeader', '');
        updateElement('nivelHeader', nivel);
        updateElement('userIdDropdown', 'ID: ' + uid);
    });
})();
</script>

<!-- Logout handler -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const token = localStorage.getItem('authToken');
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    
    if (!token || !userData.uid) {
        const portalBase = window.location.pathname.startsWith('/API/') ? '/API' : '';
        window.location.href = portalBase + '/portal/login.php';
        return;
    }
    
    function doLogout() {
        localStorage.clear();
        window.location.href = '/portal/login.php';
    }
    
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            doLogout();
        });
    }
});
</script>

<?php if (isset($extraJs)) echo $extraJs; ?>
</body>
</html>