
<?php
// login.php - Versão Moderna Tailwind
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Restrito | Portal Nutricional</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .bg-pattern {
            background-color: #0f172a;
            background-image: radial-gradient(#1e293b 1px, transparent 1px);
            background-size: 20px 20px;
        }
    </style>
</head>
<body class="bg-pattern min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-md bg-white/95 backdrop-blur-xl rounded-3xl shadow-2xl overflow-hidden border border-white/20">

        <div class="px-8 pt-10 pb-6 text-center">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-emerald-50 text-emerald-600 mb-6 shadow-sm">
             <img src="https://nutricionalbr.com/uploads/4f3c371e21e9a3e57a9f73504763a4a7.png" alt="Nutricional" width="180">
         </div>
         <h2 class="text-2xl font-bold text-slate-800 tracking-tight">Portal Operacional</h2>
         <?php if (isset($_GET['expired'])): ?>
            <div class="bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm font-medium mb-4">
                <i class="fa fa-clock mr-2"></i> Sua sessão expirou. Faça login novamente.
            </div>
        <?php endif; ?>
        <p class="text-sm text-slate-500 mt-2">Faça login para acessar o painel da Nutricional</p>
    </div>

    <div class="px-8 pb-10">
        <form id="loginForm" class="space-y-5">
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Usuário</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-user text-slate-400"></i>
                    </div>
                  <input type="text" id="username" required autocomplete="username"
       class="block w-full pl-10 pr-3 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50 transition-colors"
       placeholder="Digite seu nome de usuário">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1">Senha</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fa fa-lock text-slate-400"></i>
                    </div>
                <input type="password" id="password" required autocomplete="current-password"
       class="block w-full pl-10 pr-3 py-3 border border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 bg-slate-50 transition-colors"
       placeholder="••••••••">
                </div>
            </div>

            <div id="errorMessage" class="hidden bg-rose-50 border border-rose-200 text-rose-600 px-4 py-3 rounded-xl text-sm font-medium flex items-center gap-2">
                <i class="fa fa-exclamation-circle"></i>
                <span id="errorText">Credenciais inválidas.</span>
            </div>

            <button type="submit" class="w-full flex justify-center items-center gap-2 py-3.5 px-4 border border-transparent rounded-xl shadow-sm text-sm font-bold text-white bg-emerald-600 hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-emerald-500 transition-all hover:shadow-lg hover:-translate-y-0.5">
                <i class="fa fa-sign-in-alt"></i> Entrar no Sistema
            </button>
        </form>
    </div>

    <div class="bg-slate-50 px-8 py-4 text-center border-t border-slate-100">
        <p class="text-xs text-slate-400">Nutricional Distribuidora | Alan Marcon © <?= date('Y') ?></p>
    </div>
</div>

<script>
    // ==========================================================================
    // CONFIGURAÇÃO DA API - DETECÇÃO AUTOMÁTICA DE AMBIENTE
    // ==========================================================================
    const hostname = window.location.hostname;
    const isLocal = hostname === 'localhost' || 
                    hostname === '127.0.0.1' ||
                    hostname === '::1' ||
                    hostname.startsWith('192.168.') ||
                    hostname === '192.168.1.99';

    const appBase = window.location.pathname.split('/portal/')[0];
    const API_URL = isLocal ? `${window.location.origin}${appBase}/index.php?api_route=/v1` : 'https://api.nutricionalbr.com/v1';
    
    console.log(`🌐 Ambiente: ${isLocal ? 'DESENVOLVIMENTO' : 'PRODUÇÃO'}`);
    console.log(`🌐 API_URL: ${API_URL}`);

    // ==========================================================================
    // FUNÇÃO DE LOGIN
    // ==========================================================================
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const username = document.getElementById('username').value;
        const password = document.getElementById('password').value;
        const errorDiv = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');
        const submitBtn = e.target.querySelector('button[type="submit"]');

        // Estado de carregamento
        submitBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Autenticando...';
        submitBtn.disabled = true;
        submitBtn.classList.add('opacity-75');
        errorDiv.classList.add('hidden');
        
        try {
            // Chama a API com a URL correta (automática)
            const response = await fetch(`${API_URL}/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user: username, pass: password })
            });
            
            const data = await response.json();
            
            if (!response.ok) throw new Error(data.error || 'Credenciais inválidas');
            
            // Armazena token e dados com segurança
            localStorage.setItem('authToken', data.token);
            localStorage.setItem('userData', JSON.stringify(data.user));
            
            // Sucesso: Redireciona para o portal
            submitBtn.classList.replace('bg-emerald-600', 'bg-green-500');
            submitBtn.innerHTML = '<i class="fa fa-check"></i> Acesso Liberado!';
            
            setTimeout(() => {
                window.location.href = `${appBase}/portal/`;
            }, 500);

        } catch (error) {
            // Reverte botão e exibe erro
            submitBtn.innerHTML = '<i class="fa fa-sign-in-alt"></i> Entrar no Sistema';
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-75');
            
            errorText.textContent = error.message;
            errorDiv.classList.remove('hidden');
        }
    });
</script>
</body>
</html>