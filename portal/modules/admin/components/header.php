<?php
if (!defined('ADMIN_AREA')) {
    define('ADMIN_AREA', true);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Admin' ?> | Nutricional</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    window.tailwind = { config: { important: false } };
    const origWarn = console.warn;
    console.warn = (...a) => { if (!String(a[0]).includes('cdn.tailwindcss.com')) origWarn.apply(console, a); };
</script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Global Auth & API Bridge para todos os módulos Admin -->
    <script>
    window.API_BASE = `${window.location.origin}/index.php?api_route=`;
    window.getAuthToken = function() {
        return localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    };
    window.getCsrfToken = function(token = window.getAuthToken()) {
        try {
            if (!token) return '';
            const payload = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
            const uid = Number(payload.uid || 0);
            const today = new Date();
            const date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
            const value = uid ? `${uid}${date}` : '';
            return value && window.CryptoJS ? CryptoJS.MD5(value).toString() : (value ? btoa(value).substring(0, 32) : '');
        } catch (e) {
            return '';
        }
    };
    window.apiFetch = async function(endpoint, method = 'GET', body = null) {
        const token = window.getAuthToken();
        if (!token) {
            window.location.href = '/portal/login.php';
            throw new Error('Não autenticado');
        }
        const options = {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + token,
                'X-CSRF-Token': window.getCsrfToken(token)
            }
        };
        if (body) {
            if (window.__waCodigo && method !== 'GET' && typeof body === 'object' && !body.codigo_whatsapp) {
                body.codigo_whatsapp = window.__waCodigo;
                window.__waCodigo = null;
            }
            options.body = JSON.stringify(body);
        }
        let [pathPart, queryPart] = endpoint.split('?');
        const cleanEndpoint = pathPart.startsWith('/v1/') ? pathPart.substring(4) : pathPart;
        const cleanPath = cleanEndpoint.startsWith('/') ? cleanEndpoint : '/' + cleanEndpoint;
        let url = `${window.API_BASE}${cleanPath}`;
        if (queryPart) {
            url += (url.includes('?') ? '&' : '?') + queryPart;
        }
        const response = await fetch(url, options);
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            throw new Error(text || 'Erro na requisição');
        }
    };
    // Shim universal de compatibilidade com componentes que chamam fetchWithAuth
    window.fetchWithAuth = async function(endpoint, options = {}) {
        const method = options.method || 'GET';
        let body = options.body || null;
        if (typeof body === 'string') {
            try { body = JSON.parse(body); } catch(e) {}
        }
        return await window.apiFetch(endpoint, method, body);
    };
    </script>
    
    <style>
        :root {
            --primary: #274036;
            --accent: #f7be2f;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
            background: #f8fafc;
        }
        
        .sidebar-link {
            transition: all 0.2s ease;
        }
        
        .sidebar-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(4px);
        }
        
        .sidebar-link.active {
            background: rgba(255,255,255,0.15);
            border-left: 4px solid #f7be2f;
        }
        
        .stat-card {
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="min-h-screen">
<div class="flex min-h-screen">