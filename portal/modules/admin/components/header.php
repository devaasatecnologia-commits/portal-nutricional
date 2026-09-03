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