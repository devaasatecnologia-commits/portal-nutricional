<?php
$pageTitle = $pageTitle ?? 'Portal Operacional | Nutricional';

if (!function_exists('asset')) {
    function asset($path) {
        $version = time(); 
        return $path . '?v=' . $version;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta name="tailwind-version" content="3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.1.1/crypto-js.min.js"></script>
    <!-- Alpine.js com Collapse - VERSÃO CORRETA (SEM DUPLICAÇÃO) -->
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script src="/portal/assets/js/config.js"></script>
    <script>
       
        (function() {
            const token = localStorage.getItem('authToken');
            if (!token) return;
            
            try {
                const payload = JSON.parse(atob(token.split('.')[1]));
                const agora = Math.floor(Date.now() / 1000);
                
                if (payload.exp && payload.exp < agora) {
                    console.warn('⚠️ Token expirado - redirecionando para login');
                    localStorage.removeItem('authToken');
                    localStorage.removeItem('userData');
                    
                    if (!window.location.pathname.includes('login.php')) {
                        window.location.href = '/portal/login.php?expired=1';
                    }
                }
            } catch (e) {
                localStorage.removeItem('authToken');
                localStorage.removeItem('userData');
            }
        })();
        
       // Registra o plugin collapse - VERSÃO CORRETA
document.addEventListener('alpine:init', () => {
   
});
    </script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
<!-- Fonts + Icons -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
   
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/brands.min.css">
    <!-- Custom CSS -->
    <link href="<?= asset('/portal/assets/css/style.css') ?>" rel="stylesheet">
    
    <style>
        :root {
            --primary: #059669;
            --primary-dark: #047857;
            --sidebar-width: 280px;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body { 
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif; 
            background: #f8fafc; 
            color: #1e293b; 
            min-height: 100vh; 
            overflow-x: hidden;
        }
     
[x-cloak] { display: none !important; }
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        
        /* ================================================================ */
        /* SIDEBAR - CSS PURO (Fallback se Tailwind falhar) */
        /* ================================================================ */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 50;
            transition: transform 0.3s ease;
            overflow-y: auto;
            overflow-x: hidden;
            color: white;
        }
        
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
            margin: 2px 12px;
            border-radius: 12px;
            color: rgba(167, 243, 208, 0.7);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }
        
        .sidebar-link:hover {
            background: rgba(255, 255, 255, 0.06);
            color: white;
        }
        
        .sidebar-link.active {
            background: rgba(5, 150, 105, 0.15);
            color: #6ee7b7;
        }
        
        /* ================================================================ */
        /* MAIN CONTENT */
        /* ================================================================ */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            transition: margin 0.3s ease;
        }
        
        /* ================================================================ */
        /* CARDS */
        /* ================================================================ */
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            border: 1px solid #f1f5f9;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            position: relative;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
            border-color: #a7f3d0;
        }
        
        /* ================================================================ */
        /* GRID */
        /* ================================================================ */
        .grid-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
        }
        
        /* ================================================================ */
        /* ANIMAÇÕES */
        /* ================================================================ */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease-out forwards;
            opacity: 0;
        }
        
        /* ================================================================ */
        /* RESPONSIVO */
        /* ================================================================ */
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* ================================================================ */
        /* UTILITÁRIOS */
        /* ================================================================ */
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
        
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .hidden { display: none !important; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .gap-2 { gap: 8px; }
        .gap-3 { gap: 12px; }
        .gap-4 { gap: 16px; }
        .p-4 { padding: 16px; }
        .p-5 { padding: 20px; }
        .px-4 { padding-left: 16px; padding-right: 16px; }
        .px-6 { padding-left: 24px; padding-right: 24px; }
        .py-2 { padding-top: 8px; padding-bottom: 8px; }
        .py-3 { padding-top: 12px; padding-bottom: 12px; }
        .py-4 { padding-top: 16px; padding-bottom: 16px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-4 { margin-bottom: 16px; }
        .mb-5 { margin-bottom: 20px; }
        .mb-6 { margin-bottom: 24px; }
        .mb-8 { margin-bottom: 32px; }
        .mb-10 { margin-bottom: 40px; }
        .mt-1 { margin-top: 4px; }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
        .mt-5 { margin-top: 20px; }
        .mt-auto { margin-top: auto; }
        .ml-1 { margin-left: 4px; }
        .ml-2 { margin-left: 8px; }
        .ml-3 { margin-left: 12px; }
        .ml-auto { margin-left: auto; }
        .mr-1 { margin-right: 4px; }
        .mr-2 { margin-right: 8px; }
        
        .text-white { color: white; }
        .text-slate-300 { color: #cbd5e1; }
        .text-slate-400 { color: #94a3b8; }
        .text-slate-500 { color: #64748b; }
        .text-slate-600 { color: #475569; }
        .text-slate-700 { color: #334155; }
        .text-slate-800 { color: #1e293b; }
        .text-emerald-400 { color: #34d399; }
        .text-emerald-500 { color: #10b981; }
        .text-emerald-600 { color: #059669; }
        .text-rose-400 { color: #fb7185; }
        .text-rose-600 { color: #e11d48; }
        .text-amber-600 { color: #d97706; }
        .text-amber-700 { color: #b45309; }
        
        .bg-white { background: white; }
        .bg-slate-50 { background: #f8fafc; }
        .bg-slate-100 { background: #f1f5f9; }
        .bg-slate-200 { background: #e2e8f0; }
        .bg-slate-800 { background: #1e293b; }
        .bg-slate-900 { background: #0f172a; }
        .bg-emerald-50 { background: #ecfdf5; }
        .bg-emerald-100 { background: #d1fae5; }
        .bg-emerald-400 { background: #34d399; }
        .bg-emerald-500 { background: #10b981; }
        .bg-emerald-600 { background: #059669; }
        .bg-amber-50 { background: #fffbeb; }
        .bg-amber-100 { background: #fef3c7; }
        .bg-rose-50 { background: #fff1f2; }
        .bg-rose-100 { background: #ffe4e6; }
        .bg-sky-50 { background: #f0f9ff; }
        .bg-sky-100 { background: #e0f2fe; }
        .bg-purple-100 { background: #ede9fe; }
        .bg-blue-50 { background: #eff6ff; }
        .bg-blue-100 { background: #dbeafe; }
        .bg-indigo-50 { background: #eef2ff; }
        .bg-indigo-100 { background: #e0e7ff; }
        
        .rounded-xl { border-radius: 12px; }
        .rounded-2xl { border-radius: 16px; }
        .rounded-3xl { border-radius: 24px; }
        .rounded-full { border-radius: 9999px; }
        
        .shadow-sm { box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .shadow-lg { box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .shadow-xl { box-shadow: 0 20px 25px rgba(0,0,0,0.1); }
        
        .border { border: 1px solid #e2e8f0; }
        .border-t { border-top: 1px solid #e2e8f0; }
        .border-b { border-bottom: 1px solid #e2e8f0; }
        .border-white\/5 { border-color: rgba(255,255,255,0.05); }
        
        .font-bold { font-weight: 700; }
        .font-extrabold { font-weight: 800; }
        .font-medium { font-weight: 500; }
        .font-semibold { font-weight: 600; }
        
        .text-xs { font-size: 12px; }
        .text-sm { font-size: 14px; }
        .text-base { font-size: 16px; }
        .text-lg { font-size: 18px; }
        .text-xl { font-size: 20px; }
        .text-2xl { font-size: 24px; }
        .text-3xl { font-size: 30px; }
        .text-\[9px\] { font-size: 9px; }
        .text-\[10px\] { font-size: 10px; }
        
        .uppercase { text-transform: uppercase; }
        .tracking-wider { letter-spacing: 0.05em; }
        .tracking-\[0\.2em\] { letter-spacing: 0.2em; }
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        .transition-all { transition: all 0.2s ease; }
        .transition-colors { transition: color 0.2s, background-color 0.2s; }
        .transition-transform { transition: transform 0.3s ease; }
        .transition-opacity { transition: opacity 0.3s ease; }
        .duration-300 { transition-duration: 0.3s; }
        .duration-500 { transition-duration: 0.5s; }
        
        .cursor-pointer { cursor: pointer; }
        .overflow-hidden { overflow: hidden; }
        .overflow-x-auto { overflow-x: auto; }
        .overflow-y-auto { overflow-y: auto; }
        .relative { position: relative; }
        .absolute { position: absolute; }
        .fixed { position: fixed; }
        .sticky { position: sticky; }
        .inset-0 { top: 0; right: 0; bottom: 0; left: 0; }
        .top-0 { top: 0; }
        .z-40 { z-index: 40; }
        .z-50 { z-index: 50; }
        
        .w-4 { width: 16px; }
        .w-5 { width: 20px; }
        .w-8 { width: 32px; }
        .w-9 { width: 36px; }
        .w-10 { width: 40px; }
        .w-11 { width: 44px; }
        .w-12 { width: 48px; }
        .w-24 { width: 96px; }
        .h-8 { height: 32px; }
        .h-9 { height: 36px; }
        .h-10 { height: 40px; }
        .h-11 { height: 44px; }
        .h-12 { height: 48px; }
        .h-24 { height: 96px; }
        .h-full { height: 100%; }
        .min-h-screen { min-height: 100vh; }
        
        .flex-1 { flex: 1; }
        .flex-col { flex-direction: column; }
        .flex-shrink-0 { flex-shrink: 0; }
        .min-w-0 { min-width: 0; }
        
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        .leading-none { line-height: 1; }
        .leading-relaxed { line-height: 1.625; }
        
        .whitespace-nowrap { white-space: nowrap; }
        .pointer-events-none { pointer-events: none; }
        
        .hover\:bg-white\/10:hover { background: rgba(255,255,255,0.1); }
        .hover\:bg-slate-50:hover { background: #f8fafc; }
        .hover\:bg-slate-100:hover { background: #f1f5f9; }
        .hover\:bg-emerald-700:hover { background: #047857; }
        .hover\:text-white:hover { color: white; }
        .hover\:text-emerald-500:hover { color: #10b981; }
        .hover\:shadow-xl:hover { box-shadow: 0 20px 25px rgba(0,0,0,0.1); }
        .hover\:border-emerald-200:hover { border-color: #a7f3d0; }
        
        .group:hover .group-hover\:scale-110 { transform: scale(1.1); }
        .group:hover .group-hover\:translate-x-1 { transform: translateX(4px); }
        .group:hover .group-hover\:text-emerald-500 { color: #10b981; }
        
        @media (min-width: 640px) {
            .sm\:grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
            .sm\:block { display: block; }
        }
        @media (min-width: 768px) {
            .md\:flex { display: flex; }
            .md\:hidden { display: none; }
        }
        @media (min-width: 1024px) {
            .lg\:grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
            .lg\:translate-x-0 { transform: translateX(0); }
            .lg\:hidden { display: none; }
            .lg\:block { display: block; }
            .lg\:p-8 { padding: 32px; }
            .lg\:text-3xl { font-size: 30px; }
        }
        @media (min-width: 1280px) {
            .xl\:grid-cols-4 { grid-template-columns: repeat(4, 1fr); }
        }
        @media (min-width: 1536px) {
            .\32xl\:grid-cols-5 { grid-template-columns: repeat(5, 1fr); }
        }
    </style>

    <?php if (isset($extraCss)) echo $extraCss; ?>
</head>
<body class="bg-slate-50 text-slate-800">