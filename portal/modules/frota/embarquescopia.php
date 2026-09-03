<?php
// ======================================================================
// MODULO FROTA - GESTAO DE EMBARQUES (INTEGRACAO ERP)
// ======================================================================

$pageTitle = 'Embarques | Gestao de Frotas | Nutricional';
$version = time();

// ================================================================
// CONFIGURAÇÕES DA DISTRIBUIDORA
// ================================================================
define('DISTRIBUIDORA_LAT', -28.979438954992666);
define('DISTRIBUIDORA_LNG', -49.53561648427039);
define('DISTRIBUIDORA_ENDERECO', 'R. Alameda Ascendino Moraes de Sá, 6151, Araranguá - SC, 88902-490');

// ================================================================
// HEADER E CSS COMPLETO
// ================================================================
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<link rel="stylesheet" href="/portal/modules/frota/assets/frota.css?v=' . $version . '">

<!-- PWA MANIFEST -->
<link rel="manifest" href="/portal/modules/frota/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="/portal/modules/frota/assets/icons/icon-192x192.png">

<style>
/* ================================================================
ROOT VARIABLES - TEMA CLARO APENAS
================================================================ */
:root {
    --nutri-primary: #1a3c34;
    --nutri-secondary: #2d5a4e;
    --nutri-accent: #10b981;
    --nutri-gold: #f59e0b;
    --nutri-bg: #f0f4f0;
    --nutri-card-bg: #ffffff;
    --nutri-text: #1a202c;
    --nutri-text-secondary: #475569;
    --nutri-border: #e2e8f0;
    --nutri-shadow: 0 4px 24px rgba(0, 0, 0, 0.06);
    --nutri-radius: 16px;
    --nutri-radius-sm: 10px;
    --nutri-radius-lg: 20px;
    --distribuidora-color: #8b5cf6;
    --entrega-color: #3b82f6;
    --entrega-ativa: #10b981;
    --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --transition-smooth: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
}

/* ================================================================
BASE
================================================================ */
* {
    font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif;
    box-sizing: border-box;
}

body {
    background: var(--nutri-bg) !important;
    color: var(--nutri-text) !important;
}

/* ================================================================
SKELETON LOADING
================================================================ */
.skeleton {
    animation: skeleton-loading 1.2s ease-in-out infinite;
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    border-radius: 8px;
}
@keyframes skeleton-loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.skeleton-text { height: 12px; margin: 4px 0; }
.skeleton-title { height: 20px; width: 60%; }
.skeleton-badge { height: 24px; width: 80px; border-radius: 999px; }

/* ================================================================
TOAST NOTIFICATIONS
================================================================ */
.toast-notification {
    position: fixed;
    top: 24px;
    right: 24px;
    background: var(--nutri-card-bg);
    border-left: 4px solid #3b82f6;
    padding: 16px 20px;
    border-radius: 14px;
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
    z-index: 9999;
    max-width: 420px;
    font-family: "Inter", sans-serif;
    font-size: 14px;
    color: var(--nutri-text);
    transform: translateX(120%);
    transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    display: flex;
    align-items: center;
    gap: 14px;
}
.toast-notification.show { transform: translateX(0); }
.toast-notification .icon { font-size: 22px; }
.toast-notification .close-btn {
    background: none;
    border: none;
    color: var(--nutri-text-secondary);
    cursor: pointer;
    font-size: 18px;
    padding: 0 4px;
    transition: var(--transition);
}
.toast-notification .close-btn:hover { color: var(--nutri-text); }

/* ================================================================
ERP OPTION
================================================================ */
.erp-option {
    background-color: #eff6ff !important;
    font-weight: 600 !important;
    color: var(--nutri-primary) !important;
    border-left: 3px solid #3b82f6 !important;
    padding-left: 8px !important;
}

/* ================================================================
SECTION CARD
================================================================ */
.section-card {
    background: var(--nutri-card-bg);
    border-radius: var(--nutri-radius);
    border: 1px solid var(--nutri-border);
    overflow: hidden;
    box-shadow: var(--nutri-shadow);
    transition: var(--transition);
}
.section-card:hover {
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08);
}
.section-card .section-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--nutri-border);
    background: rgba(0, 0, 0, 0.02);
}
.section-card .section-header * {
    color: var(--nutri-text) !important;
}
.section-card .section-header .font-bold {
    color: var(--nutri-primary) !important;
}
.section-card .section-header .font-bold.text-\[#1a3c34\] {
    color: var(--nutri-primary) !important;
}
.section-card .section-header .text-slate-400 {
    color: var(--nutri-text-secondary) !important;
}
.section-card .section-header .text-slate-500 {
    color: var(--nutri-text-secondary) !important;
}
.section-card .section-header .text-slate-600 {
    color: #475569 !important;
}
.section-card .section-header .text-emerald-600 {
    color: #059669 !important;
}
.section-card .section-body {
    padding: 16px 20px;
    color: var(--nutri-text) !important;
}
.section-card .section-body * {
    color: var(--nutri-text) !important;
}
.section-card .section-body .text-slate-400 {
    color: var(--nutri-text-secondary) !important;
}
.section-card .section-body .text-slate-500 {
    color: var(--nutri-text-secondary) !important;
}
.section-card .section-body .text-slate-600 {
    color: #475569 !important;
}
.section-card .section-body .font-bold {
    color: var(--nutri-primary) !important;
}

/* ================================================================
HEADER - PREMIUM
================================================================ */
.bg-gradient-to-r {
    background: linear-gradient(135deg, var(--nutri-primary) 0%, var(--nutri-secondary) 100%) !important;
    position: relative;
    overflow: hidden;
}
.bg-gradient-to-r::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    pointer-events: none;
}
.bg-gradient-to-r::after {
    content: "";
    position: absolute;
    bottom: -40%;
    left: 10%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.03);
    border-radius: 50%;
    pointer-events: none;
}
.bg-gradient-to-r,
.bg-gradient-to-r *,
.bg-gradient-to-r h1,
.bg-gradient-to-r p,
.bg-gradient-to-r span,
.bg-gradient-to-r i,
.bg-gradient-to-r .text-white,
.bg-gradient-to-r .text-emerald-200\/80 {
    color: #ffffff !important;
}
.bg-gradient-to-r .bg-white\/20 {
    background: rgba(255, 255, 255, 0.15) !important;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255, 255, 255, 0.1);
}
.bg-gradient-to-r .bg-white\/20:hover {
    background: rgba(255, 255, 255, 0.25) !important;
    transform: scale(1.05);
}
.bg-gradient-to-r .w-14 {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.08);
}

/* ================================================================
TABELA - PREMIUM
================================================================ */
.table-frota {
    width: 100%;
    border-collapse: collapse;
}
.table-frota thead {
    background: #f8fafc;
}
.table-frota th {
    font-weight: 600;
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--nutri-text-secondary) !important;
    border-bottom: 2px solid var(--nutri-border);
    padding: 12px 14px;
    text-align: left;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 2;
}
.table-frota td {
    padding: 12px 14px;
    vertical-align: middle;
    border-bottom: 1px solid var(--nutri-border);
    color: var(--nutri-text) !important;
    font-size: 0.85rem;
}
.table-frota tbody tr {
    transition: var(--transition);
    cursor: default;
}
.table-frota tbody tr:hover {
    background: rgba(0, 0, 0, 0.02);
}
.table-frota tr.selecionado {
    background: #dbeafe !important;
    border-left: 3px solid #3b82f6;
}
.table-frota td .font-bold {
    color: var(--nutri-primary) !important;
}
.table-frota td .font-medium {
    color: var(--nutri-primary) !important;
}
.table-frota td .text-xs {
    color: var(--nutri-text-secondary) !important;
}
.table-frota td .text-slate-400 {
    color: var(--nutri-text-secondary) !important;
}
.table-frota td .text-slate-500 {
    color: var(--nutri-text-secondary) !important;
}
.table-frota td .text-slate-600 {
    color: #475569 !important;
}
.table-frota td .text-emerald-600 {
    color: #059669 !important;
}
.table-frota td .text-blue-600 {
    color: #2563eb !important;
}
.table-frota td .text-purple-700 {
    color: #6b21a8 !important;
}
.table-frota td .text-red-600 {
    color: #dc2626 !important;
}
.table-frota td .text-yellow-600 {
    color: #d97706 !important;
}
.table-frota .bg-blue-100 {
    background: #dbeafe !important;
    color: #1e40af !important;
}
.table-frota .bg-purple-100 {
    background: #ede9fe !important;
    color: #6b21a8 !important;
}
.table-frota .bg-emerald-100 {
    background: #d1fae5 !important;
    color: #065f46 !important;
}
.table-frota .bg-red-100 {
    background: #fee2e2 !important;
    color: #991b1b !important;
}
.table-frota .bg-yellow-100 {
    background: #fef3c7 !important;
    color: #92400e !important;
}
.table-frota .bg-slate-100 {
    background: #f1f5f9 !important;
    color: var(--nutri-text) !important;
}

/* ================================================================
STATUS BADGE - PREMIUM
================================================================ */
.status-badge {
    padding: 4px 14px;
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.status-badge.planejado {
    background: #e2e8f0;
    color: var(--nutri-text) !important;
}
.status-badge.em_andamento {
    background: #fef3c7;
    color: #92400e !important;
}
.status-badge.finalizado {
    background: #d1fae5;
    color: #065f46 !important;
}
.status-badge.cancelado {
    background: #fee2e2;
    color: #991b1b !important;
}
.status-badge.problema {
    background: #fee2e2;
    color: #991b1b !important;
    border: 1px solid #fca5a5;
    animation: pulse-problema-badge 2s ease-in-out infinite;
}
@keyframes pulse-problema-badge {
    0%, 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.2); }
    50% { box-shadow: 0 0 0 6px rgba(239, 68, 68, 0); }
}

/* ================================================================
PROGRESS - PREMIUM
================================================================ */
.progress-thin {
    height: 4px;
    background: var(--nutri-border);
    border-radius: 999px;
    overflow: hidden;
    min-width: 40px;
}
.progress-thin .bar {
    height: 100%;
    border-radius: 999px;
    transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.progress-thin .bar.em-andamento {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.progress-thin .bar.concluido {
    background: linear-gradient(90deg, #10b981, #34d399);
}
.progress-thin .bar.problema {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
    animation: pulse-problema 1.5s ease-in-out infinite;
}
@keyframes pulse-problema {
    0%, 100% { opacity: 1; box-shadow: 0 0 4px rgba(245, 158, 11, 0.3); }
    50% { opacity: 0.6; box-shadow: 0 0 12px rgba(245, 158, 11, 0.5); }
}

/* ================================================================
MODAL - PREMIUM
================================================================ */
.modal-content {
    border-radius: var(--nutri-radius-lg) !important;
    border: none !important;
    box-shadow: 0 24px 80px rgba(0, 0, 0, 0.2) !important;
    background: var(--nutri-card-bg) !important;
    overflow: hidden;
}
.modal-header {
    background: linear-gradient(135deg, var(--nutri-primary), var(--nutri-secondary)) !important;
    padding: 20px 28px !important;
    border-bottom: none !important;
}
.modal-header .modal-title {
    color: #ffffff !important;
    font-size: 1.1rem;
    font-weight: 700;
}
.modal-header .modal-title i {
    margin-right: 10px;
    opacity: 0.8;
}
.modal-header .btn-close {
    filter: brightness(0) invert(1);
    opacity: 0.7;
    transition: var(--transition);
}
.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}
#detalhes-numero {
    background: rgba(255, 255, 255, 0.15);
    padding: 2px 14px;
    border-radius: 999px;
    font-size: 0.8rem;
    font-weight: 600;
    color: #ffffff !important;
}
.modal-body {
    padding: 24px 28px !important;
    color: var(--nutri-text) !important;
}
.modal-body .text-slate-400 { color: var(--nutri-text-secondary) !important; }
.modal-body .text-slate-500 { color: var(--nutri-text-secondary) !important; }
.modal-body .text-slate-600 { color: #475569 !important; }
.modal-body .text-slate-700 { color: #334155 !important; }
.modal-body .font-bold { color: var(--nutri-primary) !important; }
.modal-body .font-medium { color: var(--nutri-text) !important; }
.modal-body .text-emerald-600 { color: #059669 !important; }
.modal-body .text-blue-600 { color: #2563eb !important; }
.modal-body .text-purple-700 { color: #6b21a8 !important; }
.modal-body .text-red-600 { color: #dc2626 !important; }
.modal-body .text-yellow-600 { color: #d97706 !important; }
.modal-footer {
    padding: 16px 28px !important;
    border-top: 1px solid var(--nutri-border) !important;
    background: rgba(0, 0, 0, 0.01);
}

/* ================================================================
FORM - PREMIUM
================================================================ */
.form-control,
.form-select {
    border-radius: var(--nutri-radius-sm) !important;
    border: 1.5px solid var(--nutri-border) !important;
    padding: 10px 14px !important;
    font-size: 0.9rem !important;
    transition: var(--transition-smooth) !important;
    background: #ffffff !important;
    color: var(--nutri-text) !important;
}
.form-control:focus,
.form-select:focus {
    border-color: var(--nutri-accent) !important;
    box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.12) !important;
    outline: none !important;
}
.form-control::placeholder {
    color: #94a3b8 !important;
}
.form-control:hover,
.form-select:hover {
    border-color: #94a3b8;
}
.form-label {
    font-size: 0.7rem !important;
    font-weight: 700 !important;
    color: var(--nutri-text-secondary) !important;
    text-transform: uppercase !important;
    letter-spacing: 0.06em !important;
    margin-bottom: 4px !important;
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
}
.form-label i {
    font-size: 0.65rem;
    color: var(--nutri-accent);
}

/* ================================================================
BUTTONS - PREMIUM
================================================================ */
.btn-primary-nutri {
    background: var(--nutri-primary);
    color: #ffffff;
    border: none;
    padding: 10px 24px;
    border-radius: var(--nutri-radius-sm);
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition-smooth);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}
.btn-primary-nutri::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    transition: all 0.5s ease;
    transform: translate(-50%, -50%);
}
.btn-primary-nutri:hover::after {
    width: 300px;
    height: 300px;
}
.btn-primary-nutri:hover {
    background: var(--nutri-secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(26, 60, 52, 0.3);
    color: #ffffff;
}
.btn-primary-nutri:disabled {
    background: #94a3b8;
    color: #cbd5e1;
    cursor: not-allowed;
    transform: none !important;
}

.btn-success-nutri {
    background: var(--nutri-accent);
    color: #ffffff;
    border: none;
    padding: 10px 24px;
    border-radius: var(--nutri-radius-sm);
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition-smooth);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}
.btn-success-nutri::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.15);
    transition: all 0.5s ease;
    transform: translate(-50%, -50%);
}
.btn-success-nutri:hover::after {
    width: 300px;
    height: 300px;
}
.btn-success-nutri:hover {
    background: #059669;
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(16, 185, 129, 0.3);
    color: #ffffff;
}
.btn-success-nutri:disabled {
    background: #94a3b8;
    color: #cbd5e1;
    cursor: not-allowed;
    transform: none !important;
}

.btn-secondary-nutri {
    background: var(--nutri-border);
    color: var(--nutri-text);
    border: none;
    padding: 10px 24px;
    border-radius: var(--nutri-radius-sm);
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition-smooth);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-secondary-nutri:hover {
    background: #cbd5e1;
    transform: translateY(-2px);
    color: var(--nutri-text);
}

.btn-danger-nutri {
    background: #ef4444;
    color: #ffffff;
    border: none;
    padding: 10px 24px;
    border-radius: var(--nutri-radius-sm);
    font-weight: 600;
    font-size: 0.85rem;
    transition: var(--transition-smooth);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    position: relative;
    overflow: hidden;
}
.btn-danger-nutri::after {
    content: "";
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    transition: all 0.5s ease;
    transform: translate(-50%, -50%);
}
.btn-danger-nutri:hover::after {
    width: 300px;
    height: 300px;
}
.btn-danger-nutri:hover {
    background: #dc2626;
    transform: translateY(-2px);
    color: #ffffff;
}

.btn-icone {
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: var(--nutri-text-secondary);
    transition: var(--transition-smooth);
    cursor: pointer;
    position: relative;
}
.btn-icone:hover {
    background: var(--nutri-border);
    color: var(--nutri-primary);
    transform: scale(1.05);
}
.btn-icone.verde:hover {
    background: #d1fae5;
    color: #10b981;
}
.btn-icone.azul:hover {
    background: #dbeafe;
    color: #3b82f6;
}
.btn-icone.vermelho:hover {
    background: #fee2e2;
    color: #dc2626;
}
.btn-icone.amber:hover {
    background: #fef3c7;
    color: #d97706;
}

/* Botão Criar Rota */
.bg-emerald-600 {
    background: #059669 !important;
}
.bg-emerald-600 .text-white,
.bg-emerald-600.text-white {
    color: #ffffff !important;
}
.bg-emerald-600:hover {
    background: #047857 !important;
}
.bg-emerald-500 {
    background: #10b981 !important;
}
.bg-emerald-500 .text-white {
    color: #ffffff !important;
}
.bg-emerald-500:hover {
    background: #059669 !important;
}

/* Botão Rastrear */
.bg-blue-600.text-white {
    background: #2563eb !important;
    color: #ffffff !important;
}
.bg-blue-600.text-white:hover {
    background: #1d4ed8 !important;
    color: #ffffff !important;
}

/* ================================================================
EMBARQUE DISPONÍVEL ITEM - PREMIUM
================================================================ */
.embarque-disponivel-item {
    transition: var(--transition-smooth);
    border: 2px solid var(--nutri-border);
    border-radius: var(--nutri-radius-sm);
    padding: 16px 20px;
    background: var(--nutri-card-bg);
    cursor: pointer;
    position: relative;
}
.embarque-disponivel-item:hover {
    border-color: #94a3b8;
    transform: translateX(4px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
.embarque-disponivel-item.selecionado {
    border-color: #3b82f6 !important;
    background: #eff6ff !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
}
.embarque-disponivel-item .checkbox {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-smooth);
    flex-shrink: 0;
    background: white;
}
.embarque-disponivel-item.selecionado .checkbox {
    background: #3b82f6;
    border-color: #3b82f6;
    transform: scale(1.05);
}
.embarque-disponivel-item.selecionado .checkbox i {
    color: white;
}

/* ================================================================
EMBARQUE ERP ITEM - PREMIUM
================================================================ */
.embarque-erp-item {
    cursor: pointer;
    transition: var(--transition-smooth);
    border: 2px solid var(--nutri-border);
    border-radius: var(--nutri-radius-sm);
    padding: 16px 20px;
    background: var(--nutri-card-bg);
}
.embarque-erp-item:hover {
    transform: translateX(4px);
    border-color: #94a3b8;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
.embarque-erp-item.selecionado {
    border-color: #3b82f6 !important;
    background: #eff6ff !important;
    box-shadow: 0 4px 16px rgba(59, 130, 246, 0.12);
}
.embarque-erp-item .checkbox {
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-smooth);
    flex-shrink: 0;
    background: white;
}
.embarque-erp-item.selecionado .checkbox {
    background: #3b82f6;
    border-color: #3b82f6;
    transform: scale(1.05);
}
.embarque-erp-item.selecionado .checkbox i {
    color: white;
}

/* ================================================================
ABAS DISPONÍVEIS - PREMIUM
================================================================ */
.aba-disponivel {
    padding: 8px 18px;
    border-radius: 8px 8px 0 0;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition-smooth);
    color: var(--nutri-text-secondary);
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    position: relative;
}
.aba-disponivel:hover {
    color: var(--nutri-primary);
    background: rgba(0, 0, 0, 0.02);
}
.aba-disponivel.ativa {
    color: var(--nutri-primary);
    border-bottom-color: var(--nutri-accent);
    font-weight: 700;
}
.aba-disponivel .badge-aba {
    background: var(--nutri-border);
    color: var(--nutri-text-secondary);
    padding: 0 8px;
    border-radius: 999px;
    font-size: 0.6rem;
    margin-left: 6px;
    transition: var(--transition-smooth);
}
.aba-disponivel.ativa .badge-aba {
    background: var(--nutri-accent);
    color: #ffffff;
}

/* ================================================================
TOGGLE DA SEÇÃO DISPONÍVEIS
================================================================ */
#disponiveis-body {
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1),
        opacity 0.3s ease,
        padding 0.3s ease;
    max-height: 2000px;
    opacity: 1;
    overflow: hidden;
    padding: 16px 20px;
}
#disponiveis-body.recolhido {
    max-height: 0;
    opacity: 0;
    padding: 0 20px;
}
#toggle-disponiveis-icon {
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    display: inline-block;
    font-size: 0.9rem;
}
#toggle-disponiveis-icon.recolhido {
    transform: rotate(-90deg);
}
.section-header-toggle {
    cursor: pointer;
    user-select: none;
}
.section-header-toggle:hover {
    background: rgba(0, 0, 0, 0.02);
}

/* ================================================================
MAPA - PREMIUM
================================================================ */
#mapa-rota {
    height: 450px;
    width: 100%;
    border-radius: var(--nutri-radius);
    border: 1px solid var(--nutri-border);
    overflow: hidden;
    box-shadow: var(--nutri-shadow);
    transition: var(--transition-smooth);
}
#mapa-rota:hover {
    border-color: var(--nutri-primary);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
}
#mapa-rota .leaflet-control-zoom {
    border: none !important;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.12) !important;
}
#mapa-rota .leaflet-control-zoom a {
    background: #ffffff !important;
    color: var(--nutri-text) !important;
    border: none !important;
    border-radius: 8px !important;
    margin: 2px !important;
    transition: var(--transition) !important;
    width: 34px !important;
    height: 34px !important;
    line-height: 34px !important;
}
#mapa-rota .leaflet-control-zoom a:hover {
    background: var(--nutri-primary) !important;
    color: #ffffff !important;
}
.leaflet-popup-content {
    font-family: "Inter", sans-serif !important;
    font-size: 13px !important;
    min-width: 220px !important;
    max-width: 320px !important;
    color: var(--nutri-text) !important;
}
.leaflet-popup-content strong {
    color: var(--nutri-primary);
    font-weight: 600;
}

/* ================================================================
MAPA - MARCADORES PREMIUM
================================================================ */
.marker-distribuidora {
    background: #8b5cf6;
    border: 3px solid white;
    border-radius: 50%;
    box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    width: 44px;
    height: 44px;
    animation: pulse-distribuidora 2s infinite;
}
.marker-distribuidora i {
    color: white;
    font-size: 20px;
}
@keyframes pulse-distribuidora {
    0%, 100% { box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.3); }
    50% { box-shadow: 0 0 0 16px rgba(139, 92, 246, 0.1); }
}

.marker-entrega {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: 3px solid white;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.25);
    font-size: 11px;
    font-weight: 700;
    color: white;
    transition: var(--transition-smooth);
}
.marker-entrega:hover {
    transform: scale(1.15);
}
.marker-entrega.ativa {
    border-color: #10b981;
    box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.25);
    animation: pulse-entrega-ativa 1.5s infinite;
}
.marker-entrega.pendente { background: #3b82f6; }
.marker-entrega.em_entrega { background: #f59e0b; }
.marker-entrega.entregue { background: #10b981; }
.marker-entrega.falha { background: #ef4444; }
@keyframes pulse-entrega-ativa {
    0%, 100% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.25); }
    50% { box-shadow: 0 0 0 16px rgba(16, 185, 129, 0.1); }
}

/* ================================================================
LISTA DE ENTREGAS - PREMIUM
================================================================ */
.entrega-item {
    display: flex;
    align-items: stretch;
    padding: 14px 18px;
    background: var(--nutri-card-bg);
    border: 1px solid var(--nutri-border);
    border-radius: var(--nutri-radius);
    margin-bottom: 10px;
    transition: var(--transition-smooth);
    cursor: grab;
    position: relative;
    gap: 12px;
}
.entrega-item::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    border-radius: 4px 0 0 4px;
    background: transparent;
    transition: background 0.3s ease;
}
.entrega-item:hover {
    border-color: #94a3b8;
    transform: translateX(4px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06);
}
.entrega-item:hover::before {
    background: var(--nutri-accent);
}
.entrega-item.dragging {
    opacity: 0.5;
    cursor: grabbing;
}
.entrega-item .drag-handle {
    display: flex;
    align-items: center;
    padding-right: 4px;
    color: #94a3b8;
    font-size: 14px;
    cursor: grab;
    transition: color 0.2s;
}
.entrega-item .drag-handle:hover {
    color: var(--nutri-primary);
}
.entrega-item .ordem {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    background: var(--nutri-primary);
    color: white;
    border-radius: 50%;
    font-size: 0.7rem;
    font-weight: 700;
    flex-shrink: 0;
    transition: var(--transition-smooth);
}
.entrega-item:hover .ordem {
    transform: scale(1.05);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3);
}
.entrega-item .info {
    flex: 1;
    min-width: 0;
}
.entrega-item .info .cliente {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--nutri-text) !important;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.entrega-item .info .cliente .badge-entrega {
    font-size: 0.55rem;
    font-weight: 600;
    padding: 1px 10px;
    border-radius: 999px;
    background: var(--nutri-border);
    color: var(--nutri-text-secondary);
}
.entrega-item .info .cliente .badge-entrega.checkout {
    background: #d1fae5;
    color: #065f46;
}
.entrega-item .info .cliente .badge-entrega.fotos {
    background: #dbeafe;
    color: #1e40af;
}
.entrega-item .info .cliente .badge-entrega.recebedor {
    background: #fef3c7;
    color: #92400e;
}
.entrega-item .info .endereco {
    font-size: 0.75rem;
    color: var(--nutri-text-secondary) !important;
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 2px;
}
.entrega-item .info .endereco i {
    color: var(--nutri-accent);
    font-size: 0.65rem;
}
.entrega-item .info .detalhes {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 6px;
}
.entrega-item .info .detalhes span {
    font-size: 0.6rem;
    padding: 3px 12px;
    border-radius: 999px;
    font-weight: 500;
    background: var(--nutri-border);
    color: var(--nutri-text-secondary);
    display: flex;
    align-items: center;
    gap: 4px;
}
.entrega-item .info .detalhes .pedido {
    background: #dbeafe;
    color: #1e40af;
}
.entrega-item .info .detalhes .valor {
    background: #d1fae5;
    color: #065f46;
}
.entrega-item .info .detalhes .peso {
    background: #fef3c7;
    color: #92400e;
}
.entrega-item .info .detalhes .geo {
    background: #f1f5f9;
    color: var(--nutri-text-secondary);
}
.entrega-item .distancia {
    display: flex;
    align-items: center;
    padding: 0 8px;
    font-size: 0.7rem;
    color: var(--nutri-text-secondary);
    white-space: nowrap;
    gap: 4px;
}
.entrega-item .distancia i {
    color: #f59e0b;
}
.entrega-item .status-mini {
    font-size: 0.6rem;
    font-weight: 600;
    padding: 4px 14px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.entrega-item .status-mini.pendente {
    background: #dbeafe;
    color: #1e40af;
}
.entrega-item .status-mini.em_entrega {
    background: #fef3c7;
    color: #92400e;
}
.entrega-item .status-mini.entregue {
    background: #d1fae5;
    color: #065f46;
}
.entrega-item .status-mini.falha {
    background: #fee2e2;
    color: #991b1b;
}
.entrega-item .actions {
    display: flex;
    align-items: center;
    gap: 2px;
    flex-shrink: 0;
}
.entrega-item .actions .btn-acao {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    background: transparent;
    color: #94a3b8;
    transition: var(--transition-smooth);
    cursor: pointer;
}
.entrega-item .actions .btn-acao:hover {
    background: var(--nutri-border);
    color: var(--nutri-primary);
    transform: scale(1.05);
}
.entrega-item .actions .btn-acao.verde:hover {
    background: #d1fae5;
    color: #10b981;
}
.entrega-item .actions .btn-acao.azul:hover {
    background: #dbeafe;
    color: #3b82f6;
}
.entrega-item .actions .btn-acao.vermelho:hover {
    background: #fee2e2;
    color: #dc2626;
}
.entrega-item .actions .btn-acao.amber:hover {
    background: #fef3c7;
    color: #d97706;
}
.entrega-item.ativa {
    border-color: #10b981;
    background: #f0fdf4;
}
.entrega-item.ativa::before {
    background: #10b981;
}
.entrega-item.ativa .ordem {
    background: #10b981;
}
.entrega-item .checklist-info {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-top: 4px;
}
.entrega-item .checklist-info .tag {
    font-size: 0.55rem;
    padding: 1px 10px;
    border-radius: 999px;
    background: var(--nutri-border);
    color: var(--nutri-text-secondary);
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.entrega-item .checklist-info .tag.success {
    background: #d1fae5;
    color: #065f46;
}
.entrega-item .checklist-info .tag.warning {
    background: #fef3c7;
    color: #92400e;
}
.entrega-item .checklist-info .tag.danger {
    background: #fee2e2;
    color: #991b1b;
}
.entrega-item .checklist-info .btn-ver-itens {
    background: #3b82f6;
    color: white;
    border: none;
    border-radius: 999px;
    padding: 1px 14px;
    cursor: pointer;
    font-size: 0.55rem;
    font-weight: 600;
    transition: var(--transition-smooth);
}
.entrega-item .checklist-info .btn-ver-itens:hover {
    background: #2563eb;
    transform: scale(1.05);
}

/* ================================================================
LISTA ENTREGAS CONTAINER
================================================================ */
#lista-entregas-container {
    max-height: 500px;
    overflow-y: auto;
    padding-right: 4px;
}
#lista-entregas-container::-webkit-scrollbar {
    width: 5px;
}
#lista-entregas-container::-webkit-scrollbar-track {
    background: var(--nutri-border);
    border-radius: 4px;
}
#lista-entregas-container::-webkit-scrollbar-thumb {
    background: var(--nutri-primary);
    border-radius: 4px;
}
#lista-entregas-container::-webkit-scrollbar-thumb:hover {
    background: var(--nutri-secondary);
}

/* ================================================================
RESUMO ROTA - PREMIUM
================================================================ */
.resumo-rota {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    padding: 14px 20px;
    background: linear-gradient(135deg, var(--nutri-border), var(--nutri-card-bg));
    border-radius: var(--nutri-radius);
    margin: 12px 0;
    border: 1px solid var(--nutri-border);
}
.resumo-rota .item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.8rem;
    color: var(--nutri-text-secondary) !important;
}
.resumo-rota .item i {
    font-size: 1rem;
    color: var(--nutri-accent);
}
.resumo-rota .item .numero {
    font-weight: 700;
    color: var(--nutri-primary) !important;
    font-size: 1rem;
}
.resumo-rota .item .distancia-total {
    font-weight: 700;
    color: #f59e0b !important;
    font-size: 1rem;
}

/* ================================================================
GEO BADGE - PREMIUM
================================================================ */
.geo-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 10px;
    border-radius: 999px;
    font-size: 0.55rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.geo-badge.frota_cliente {
    background: #dbeafe;
    color: #1e40af !important;
}
.geo-badge.google_maps {
    background: #fee2e2;
    color: #991b1b !important;
}
.geo-badge.sem_geo {
    background: var(--nutri-border);
    color: var(--nutri-text-secondary) !important;
}

/* ================================================================
IMPORTAÇÃO ERP SECTION - PREMIUM
================================================================ */
.importacao-erp-section {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 50%, #e0f2fe 100%);
    border: 2px solid #93c5fd;
    border-radius: var(--nutri-radius);
    padding: 24px;
    margin-bottom: 24px;
    transition: var(--transition-smooth);
    animation: fadeInUp 0.6s ease forwards;
    position: relative;
    overflow: hidden;
}
.importacao-erp-section::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: rgba(59, 130, 246, 0.05);
    border-radius: 50%;
    pointer-events: none;
}
.importacao-erp-section:hover {
    border-color: #3b82f6;
    box-shadow: 0 4px 24px rgba(59, 130, 246, 0.15);
}
.importacao-erp-section * {
    color: var(--nutri-text) !important;
}
.importacao-erp-section .badge-count {
    background: #3b82f6;
    color: #ffffff;
    padding: 2px 12px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}
.importacao-erp-section .badge-count.zero {
    background: #94a3b8;
}
.importacao-erp-section .btn-importar {
    background: var(--nutri-primary);
    color: #ffffff;
    border: none;
    padding: 10px 28px;
    border-radius: var(--nutri-radius-sm);
    font-weight: 600;
    transition: var(--transition-smooth);
    white-space: nowrap;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.importacao-erp-section .btn-importar:hover {
    background: var(--nutri-secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(26, 60, 52, 0.3);
    color: #ffffff;
}
.importacao-erp-section .resumo-cards {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.importacao-erp-section .resumo-card {
    background: var(--nutri-card-bg);
    border-radius: var(--nutri-radius-sm);
    padding: 12px 18px;
    flex: 1;
    min-width: 100px;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(255, 255, 255, 0.5);
    transition: var(--transition-smooth);
}
.importacao-erp-section .resumo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
}
.importacao-erp-section .resumo-card .numero {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--nutri-primary);
}
.importacao-erp-section .resumo-card .label {
    font-size: 0.65rem;
    color: var(--nutri-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.04em;
}
.importacao-erp-section .resumo-card .label i {
    margin-right: 4px;
}
.importacao-erp-section .resumo-card.destaque {
    border-color: #3b82f6;
    background: #eff6ff;
}
.importacao-erp-section .resumo-card.destaque .numero {
    color: #3b82f6;
}
.importacao-erp-section .embarques-disponiveis {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
}
.importacao-erp-section .embarque-tag {
    background: var(--nutri-card-bg);
    border: 1px solid var(--nutri-border);
    border-radius: 8px;
    padding: 4px 14px;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition-smooth);
}
.importacao-erp-section .embarque-tag:hover {
    border-color: #3b82f6;
    transform: translateY(-1px);
}
.importacao-erp-section .embarque-tag .id {
    font-weight: 700;
    color: #3b82f6;
}

/* ================================================================
CHECKOUT TABLE - PREMIUM
================================================================ */
.checkout-table th {
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 1;
    font-weight: 600;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--nutri-text-secondary);
    padding: 10px 12px;
}
.checkout-table td {
    padding: 8px 12px;
    vertical-align: middle;
    font-size: 0.85rem;
}
.checkout-table .qtd-entregue {
    width: 70px;
    text-align: center;
}

/* ================================================================
CARDS DE CHECKLIST - PREMIUM
================================================================ */
.card-item {
    background: var(--nutri-card-bg);
    border-radius: var(--nutri-radius-sm);
    padding: 14px 18px;
    margin-bottom: 10px;
    border: 1px solid var(--nutri-border);
    transition: var(--transition-smooth);
}
.card-item:hover {
    border-color: var(--nutri-primary);
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    transform: translateY(-2px);
}
.card-item .item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.card-item .item-header .nome {
    font-weight: 600;
    font-size: 0.9rem;
    color: var(--nutri-text);
}
.card-item .item-header .status-badge-item {
    font-size: 0.6rem;
    padding: 2px 12px;
    border-radius: 999px;
    font-weight: 600;
}
.card-item .item-header .status-badge-item.entregue {
    background: #d1fae5;
    color: #065f46;
}
.card-item .item-header .status-badge-item.faltante {
    background: #fef3c7;
    color: #92400e;
}
.card-item .item-header .status-badge-item.devolvido {
    background: #fee2e2;
    color: #991b1b;
}
.card-item .item-details {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 6px;
    font-size: 0.75rem;
    color: var(--nutri-text-secondary);
}
.card-item .item-details span {
    display: flex;
    align-items: center;
    gap: 4px;
}

/* ================================================================
HISTÓRICO - TIMELINE PREMIUM
================================================================ */
.historico-timeline {
    padding: 16px;
    background: var(--nutri-card-bg);
    border: 1px solid var(--nutri-border);
    border-radius: var(--nutri-radius);
    max-height: 400px;
    overflow-y: auto;
}
.historico-timeline::-webkit-scrollbar {
    width: 5px;
}
.historico-timeline::-webkit-scrollbar-track {
    background: var(--nutri-border);
    border-radius: 4px;
}
.historico-timeline::-webkit-scrollbar-thumb {
    background: var(--nutri-primary);
    border-radius: 4px;
}
.historico-timeline::-webkit-scrollbar-thumb:hover {
    background: var(--nutri-secondary);
}
.historico-timeline .timeline-item {
    position: relative;
    padding: 12px 16px 12px 40px;
    border-left: 2px solid var(--nutri-border);
    margin-left: 12px;
    transition: var(--transition-smooth);
}
.historico-timeline .timeline-item:last-child {
    border-left-color: transparent;
}
.historico-timeline .timeline-item .dot {
    position: absolute;
    left: -9px;
    top: 14px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid var(--nutri-border);
    background: var(--nutri-card-bg);
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition-smooth);
}
.historico-timeline .timeline-item .dot i {
    font-size: 8px;
    color: var(--nutri-text-secondary);
}
.historico-timeline .timeline-item:hover .dot {
    transform: scale(1.2);
    border-color: var(--nutri-accent);
}
.historico-timeline .timeline-item .dot.checkin {
    border-color: #3b82f6;
    background: #dbeafe;
}
.historico-timeline .timeline-item .dot.checkin i { color: #3b82f6; }
.historico-timeline .timeline-item .dot.checkout {
    border-color: #10b981;
    background: #d1fae5;
}
.historico-timeline .timeline-item .dot.checkout i { color: #10b981; }
.historico-timeline .timeline-item .dot.falha {
    border-color: #ef4444;
    background: #fee2e2;
}
.historico-timeline .timeline-item .dot.falha i { color: #ef4444; }
.historico-timeline .timeline-item .dot.iniciar {
    border-color: #f59e0b;
    background: #fef3c7;
}
.historico-timeline .timeline-item .dot.iniciar i { color: #f59e0b; }
.historico-timeline .timeline-item .dot.finalizar {
    border-color: #10b981;
    background: #d1fae5;
}
.historico-timeline .timeline-item .dot.finalizar i { color: #10b981; }
.historico-timeline .timeline-item .dot.cancelar {
    border-color: #ef4444;
    background: #fee2e2;
}
.historico-timeline .timeline-item .dot.cancelar i { color: #ef4444; }
.historico-timeline .timeline-item .header {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.historico-timeline .timeline-item .header .title {
    font-weight: 600;
    font-size: 0.85rem;
    color: var(--nutri-text);
}
.historico-timeline .timeline-item .header .time {
    font-size: 0.65rem;
    color: var(--nutri-text-secondary);
}
.historico-timeline .timeline-item .header .user {
    font-size: 0.65rem;
    color: var(--nutri-text-secondary);
    background: var(--nutri-border);
    padding: 1px 10px;
    border-radius: 999px;
}
.historico-timeline .timeline-item .descricao {
    font-size: 0.8rem;
    color: var(--nutri-text-secondary);
    margin-top: 4px;
    padding-left: 4px;
}
.historico-timeline .timeline-item .descricao strong {
    color: var(--nutri-text);
}

/* ================================================================
DETALHES CARDS - PREMIUM
================================================================ */
.detalhes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}
.detalhes-card {
    background: var(--nutri-card-bg);
    border-radius: var(--nutri-radius);
    padding: 16px 20px;
    border: 1px solid var(--nutri-border);
    transition: var(--transition-smooth);
    position: relative;
    overflow: hidden;
}
.detalhes-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: var(--nutri-primary);
    opacity: 0;
    transition: opacity 0.3s ease;
}
.detalhes-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    border-color: var(--nutri-primary);
}
.detalhes-card:hover::before { opacity: 1; }
.detalhes-card .label {
    font-size: 0.6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--nutri-text-secondary);
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}
.detalhes-card .label i {
    font-size: 0.7rem;
    color: var(--nutri-accent);
}
.detalhes-card .value {
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--nutri-text);
}
.detalhes-card .value .sub {
    font-size: 0.75rem;
    font-weight: 400;
    color: var(--nutri-text-secondary);
    display: block;
}
.detalhes-card .value .badge-status {
    display: inline-block;
    padding: 2px 12px;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
}

/* Status Card Destaque */
.detalhes-card.status-card {
    background: linear-gradient(135deg, var(--nutri-primary), var(--nutri-secondary));
    border-color: var(--nutri-primary);
}
.detalhes-card.status-card .label {
    color: rgba(255, 255, 255, 0.7);
}
.detalhes-card.status-card .value {
    color: #ffffff;
}
.detalhes-card.status-card .value .badge-status {
    background: rgba(255, 255, 255, 0.2);
    color: #ffffff;
}
.detalhes-card.status-card .value .badge-status.problema {
    background: rgba(239, 68, 68, 0.3);
    color: #fca5a5;
}
.detalhes-card.status-card .value .badge-status.finalizado {
    background: rgba(16, 185, 129, 0.3);
    color: #6ee7b7;
}
.detalhes-card.status-card .value .badge-status.em_andamento {
    background: rgba(245, 158, 11, 0.3);
    color: #fbbf24;
}

/* ================================================================
PROGRESSO EM DESTAQUE - PREMIUM
================================================================ */
.progress-destaque {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 12px 16px;
    background: var(--nutri-border);
    border-radius: var(--nutri-radius-sm);
    margin: 12px 0;
}
.progress-destaque .progress-info {
    display: flex;
    align-items: center;
    gap: 12px;
    flex: 1;
}
.progress-destaque .progress-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--nutri-text-secondary);
    white-space: nowrap;
}
.progress-destaque .progress-track {
    flex: 1;
    height: 8px;
    background: rgba(0, 0, 0, 0.08);
    border-radius: 999px;
    overflow: hidden;
    position: relative;
}
.progress-destaque .progress-track .progress-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}
.progress-destaque .progress-track .progress-fill::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    animation: shimmer 2s infinite;
}
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.progress-destaque .progress-track .progress-fill.em-andamento {
    background: linear-gradient(90deg, #f59e0b, #fbbf24);
}
.progress-destaque .progress-track .progress-fill.concluido {
    background: linear-gradient(90deg, #10b981, #34d399);
}
.progress-destaque .progress-track .progress-fill.problema {
    background: linear-gradient(90deg, #f59e0b, #ef4444);
    animation: pulse-problema 1.5s ease-in-out infinite;
}
.progress-destaque .progress-percent {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--nutri-primary);
    min-width: 48px;
    text-align: right;
}

/* ================================================================
ANIMAÇÕES
================================================================ */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInZoom {
    from { opacity: 0; transform: scale(0.9); }
    to { opacity: 1; transform: scale(1); }
}
@keyframes fadeOutZoom {
    from { opacity: 1; transform: scale(1); }
    to { opacity: 0; transform: scale(0.9); }
}
@keyframes zoomIn {
    from { transform: scale(0.8); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
@keyframes slideIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Animações de entrada para entregas */
.entrega-item {
    animation: slideIn 0.3s ease forwards;
}
.entrega-item:nth-child(1) { animation-delay: 0.05s; }
.entrega-item:nth-child(2) { animation-delay: 0.1s; }
.entrega-item:nth-child(3) { animation-delay: 0.15s; }
.entrega-item:nth-child(4) { animation-delay: 0.2s; }
.entrega-item:nth-child(5) { animation-delay: 0.25s; }

/* Galeria de fotos */
.galeria-fotos-modal .swal2-popup {
    max-height: 90vh !important;
}
.galeria-fotos-modal .swal2-html-container {
    overflow: hidden !important;
    padding: 0 !important;
}
.foto-thumbnail {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.foto-thumbnail:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* ================================================================
INDICADOR DE CARREGAMENTO
================================================================ */
.skeleton-loading {
    position: relative;
    overflow: hidden;
}
.skeleton-loading::after {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.1) 50%,
        transparent 100%
    );
    animation: skeleton-shimmer 1.5s infinite;
}
@keyframes skeleton-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* ================================================================
SPINNER OVERLAY - PREMIUM
================================================================ */
.spinner-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    animation: fadeInOverlay 0.3s ease;
}
@keyframes fadeInOverlay {
    from { opacity: 0; }
    to { opacity: 1; }
}
.spinner-container {
    background: var(--nutri-card-bg);
    border-radius: var(--nutri-radius-lg);
    padding: 40px 48px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    max-width: 90%;
    animation: scaleIn 0.3s ease;
}
@keyframes scaleIn {
    from { transform: scale(0.9); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.spinner-container .spinner {
    width: 60px;
    height: 60px;
    border: 4px solid var(--nutri-border);
    border-top-color: var(--nutri-primary);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px;
}
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
.spinner-container .spinner-text {
    font-size: 1rem;
    font-weight: 600;
    color: var(--nutri-text);
}
.spinner-container .spinner-subtext {
    font-size: 0.85rem;
    color: var(--nutri-text-secondary);
    margin-top: 4px;
}
.spinner-container .progress-bar-container {
    width: 100%;
    height: 4px;
    background: var(--nutri-border);
    border-radius: 999px;
    margin-top: 16px;
    overflow: hidden;
}
.spinner-container .progress-bar-container .progress-fill {
    height: 100%;
    background: var(--nutri-primary);
    border-radius: 999px;
    transition: width 0.3s ease;
    width: 0%;
}

/* ================================================================
PAGINAÇÃO - PREMIUM
================================================================ */
.page-link {
    color: var(--nutri-primary) !important;
    background: #ffffff !important;
    border-color: var(--nutri-border) !important;
    transition: var(--transition-smooth);
    border-radius: 8px !important;
    margin: 0 2px;
}
.page-link:hover {
    background: var(--nutri-primary) !important;
    color: #ffffff !important;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.2);
}
.page-item.active .page-link {
    background: var(--nutri-primary) !important;
    color: #ffffff !important;
    border-color: var(--nutri-primary) !important;
    box-shadow: 0 4px 12px rgba(26, 60, 52, 0.2);
}
.page-item.disabled .page-link {
    color: #94a3b8 !important;
    background: #f1f5f9 !important;
}

/* ================================================================
CHECKBOX E RADIO - PREMIUM
================================================================ */
.form-check-label {
    color: var(--nutri-text) !important;
    font-weight: 500;
    font-size: 0.9rem;
}
.form-check-input {
    border-color: #d1d5db !important;
    background: #ffffff !important;
    width: 18px;
    height: 18px;
    cursor: pointer;
    transition: var(--transition-smooth);
}
.form-check-input:checked {
    background-color: var(--nutri-primary) !important;
    border-color: var(--nutri-primary) !important;
}
.form-check-input:focus {
    border-color: var(--nutri-primary) !important;
    box-shadow: 0 0 0 3px rgba(26, 60, 52, 0.1) !important;
}

/* ================================================================
TEXTOS GERAIS - GARANTIA DE CORES
================================================================ */
.text-slate-400 { color: var(--nutri-text-secondary) !important; }
.text-slate-500 { color: var(--nutri-text-secondary) !important; }
.text-slate-600 { color: #475569 !important; }
.text-slate-700 { color: #334155 !important; }
.text-slate-800 { color: #1e293b !important; }
.text-slate-900 { color: #0f172a !important; }
.text-emerald-600 { color: #059669 !important; }
.text-emerald-700 { color: #047857 !important; }
.text-blue-600 { color: #2563eb !important; }
.text-blue-700 { color: #1d4ed8 !important; }
.text-purple-700 { color: #6b21a8 !important; }
.text-red-600 { color: #dc2626 !important; }
.text-red-700 { color: #b91c1c !important; }
.text-yellow-600 { color: #d97706 !important; }
.text-yellow-700 { color: #b45309 !important; }
.text-gray-500 { color: var(--nutri-text-secondary) !important; }
.text-gray-600 { color: #475569 !important; }
.text-gray-700 { color: #334155 !important; }
.text-gray-800 { color: #1e293b !important; }
.text-gray-900 { color: #0f172a !important; }
.text-black { color: var(--nutri-text) !important; }
.font-bold { color: var(--nutri-text) !important; }
.font-bold.text-\[#1a3c34\] { color: var(--nutri-primary) !important; }
.font-medium { color: var(--nutri-text) !important; }
.font-medium.text-\[#1a3c34\] { color: var(--nutri-primary) !important; }
h1, h2, h3, h4, h5, h6 { color: var(--nutri-text) !important; }

/* ================================================================
RESPONSIVO - PREMIUM
================================================================ */
@media (max-width: 1024px) {
    .importacao-erp-section .resumo-cards { gap: 12px; }
    .importacao-erp-section .resumo-card { min-width: 80px; padding: 10px 14px; }
    .importacao-erp-section .resumo-card .numero { font-size: 1.2rem; }
    .detalhes-grid { grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); }
}

@media (max-width: 768px) {
    .table-frota { font-size: 0.7rem; }
    .table-frota th,
    .table-frota td { padding: 6px 8px; }
    .table-frota th { font-size: 0.55rem; }
    #mapa-rota { height: 300px; }
    .entrega-item {
        flex-wrap: wrap;
        padding: 12px 14px;
        gap: 8px;
    }
    .entrega-item .distancia { display: none; }
    .resumo-rota { flex-direction: column; gap: 6px; }
    .modal-dialog { margin: 8px; }
    .importacao-erp-section { padding: 16px; }
    .importacao-erp-section .resumo-cards { flex-direction: column; }
    .importacao-erp-section .resumo-card { min-width: unset; }
    .embarque-erp-item { padding: 12px; }
    .embarque-erp-item .flex-wrap { gap: 4px; }
    .btn-primary-nutri,
    .btn-success-nutri,
    .btn-secondary-nutri {
        padding: 8px 16px;
        font-size: 0.8rem;
    }
    .modal-header { padding: 16px 20px !important; }
    .modal-body { padding: 16px 20px !important; }
    .modal-footer { padding: 12px 20px !important; }
    .detalhes-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
    .detalhes-card { padding: 12px 16px; }
}

@media (max-width: 480px) {
    .table-frota th,
    .table-frota td { padding: 4px 6px; font-size: 0.6rem; }
    .table-frota .btn-icone { width: 28px; height: 28px; font-size: 0.7rem; }
    .entrega-item .info .cliente { font-size: 0.8rem; }
    .entrega-item .info .endereco { font-size: 0.65rem; }
    .entrega-item .ordem { width: 24px; height: 24px; font-size: 0.6rem; margin-right: 6px; }
    .status-badge { font-size: 0.55rem; padding: 2px 8px; }
    .importacao-erp-section .badge-count { font-size: 0.6rem; padding: 1px 8px; }
    .toast-notification { max-width: 90%; right: 5%; top: 16px; font-size: 12px; padding: 12px 16px; }
    .detalhes-grid { grid-template-columns: 1fr; }
}

/* ================================================================
EXCEÇÕES - ELEMENTOS QUE DEVEM FICAR BRANCOS
================================================================ */
.text-white,
.text-white *,
.bg-gradient-to-r .text-white,
.bg-gradient-to-r .text-white *,
.modal-header .text-white,
.modal-header .text-white *,
#detalhes-numero,
.btn-primary-nutri .text-white,
.btn-success-nutri .text-white,
.btn-danger-nutri .text-white,
.btn-importar .text-white,
.badge-count,
.badge-count *,
.bg-emerald-600 .text-white,
.bg-emerald-500 .text-white,
.bg-blue-600 .text-white {
    color: #ffffff !important;
}
</style>
';

$extraJs = '
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<!-- REMOVER OU COMENTAR AS LINHAS ABAIXO -->
<!-- <script src="/portal/assets/js/module-base.js?v=' . $version . '"></script> -->
<!-- <script src="/portal/modules/frota/assets/frota.js?v=' . $version . '"></script> -->
';

require_once __DIR__ . '/../../estrutura/header.php';
?>

<!-- ================================================================
   HIDDEN INPUTS
   ================================================================ -->
   <input type="hidden" id="user_id" value="<?= $_SESSION['uid'] ?? $_SESSION['idusuario'] ?? 0 ?>">
   <input type="hidden" id="user_nome" value="<?= $_SESSION['uname'] ?? $_SESSION['username'] ?? 'Operador' ?>">
   <input type="hidden" id="distribuidora_lat" value="<?= DISTRIBUIDORA_LAT ?>">
   <input type="hidden" id="distribuidora_lng" value="<?= DISTRIBUIDORA_LNG ?>">
   <input type="hidden" id="distribuidora_endereco" value="<?= DISTRIBUIDORA_ENDERECO ?>">

<!-- Botão Tema Escuro -->
<button class="theme-toggle" onclick="toggleTheme()" title="Alternar tema">
    <i class="fa-solid fa-moon"></i>
</button>

<!-- ================================================================
   CONTEÚDO PRINCIPAL
   ================================================================ -->
   <div class="max-w-full mx-auto px-4 lg:px-6 py-4" style="background: var(--nutri-bg); min-height: 100vh;">

    <!-- HEADER -->
    <div class="bg-gradient-to-r from-[#1a3c34] to-[#2d5a4e] rounded-3xl p-6 mb-6 shadow-xl">
        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4">
            <div class="flex items-center gap-4">
                <a href="/portal/modules/frota/gestao-frota.php" class="flex w-10 h-10 rounded-xl items-center justify-center transition-colors no-underline bg-white/20 hover:bg-white/30">
                    <i class="fa-solid fa-arrow-left text-white"></i>
                </a>
                <div class="w-14 h-14 bg-white/20 backdrop-blur rounded-2xl flex items-center justify-center shadow-lg">
                    <i class="fa-solid fa-truck text-white text-2xl"></i>
                </div>
                <div>
                    <h1 class="text-2xl font-black text-white tracking-tight">Embarques</h1>
                    <p class="text-emerald-200/80 text-sm flex items-center gap-2">
                        <i class="fa-solid fa-circle text-[6px]"></i>
                        Integração com ERP
                        <i class="fa-solid fa-circle text-[6px]"></i>
                        <span id="total-embarques">0</span> registros
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 items-center">
                <span id="cache-indicator" class="text-xs text-emerald-200/60 hidden">
                    <i class="fa-regular fa-clock"></i> <span id="cache-tempo">0s</span>
                </span>
                <button class="px-4 py-2.5 bg-white/20 text-white rounded-xl font-bold hover:bg-white/30 transition-all flex items-center gap-2" onclick="carregarEmbarques(true)">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>
            </div>
        </div>
    </div>

<!-- ================================================================
   SEÇÃO: DISPONÍVEIS PARA ROTA (EMBARQUES DO ERP)
   ================================================================ -->
   <div class="section-card mb-6" id="secao-disponiveis">
    <!-- Cabeçalho clicável -->
    <div class="section-header section-header-toggle flex justify-between items-center" onclick="toggleDisponiveis()">
        <div>
            <span class="font-bold text-[#1a3c34]">
                <i class="fa-regular fa-clock mr-2"></i> 
                Embarques Disponíveis para Rota
            </span>
            <span class="text-xs text-slate-400 ml-2" id="info-disponiveis">Carregando...</span>
        </div>
        <div class="flex gap-2 items-center">
            <span id="toggle-disponiveis-icon" class="text-slate-400"><i class="fa-solid fa-chevron-down"></i></span>
            <button class="btn-success-nutri px-4 py-1.5 text-sm" onclick="event.stopPropagation(); criarRotasSelecionadas()" id="btn-criar-rotas-disponiveis" disabled>
                <i class="fa-solid fa-route mr-1"></i> Criar Rota (<span id="total-selecionados-disponiveis">0</span>)
            </button>
            <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors" onclick="event.stopPropagation(); atualizarDisponiveis()" title="Atualizar">
                <i class="fa-solid fa-sync-alt"></i>
            </button>
        </div>
    </div>
    <!-- Corpo (recolhível) -->
    <div class="section-body" id="disponiveis-body">
        <div class="flex flex-wrap gap-1 border-b border-slate-200 mb-4" id="abas-disponiveis">
            <!-- Abas geradas via JavaScript -->
        </div>
        <div id="lista-disponiveis" class="space-y-3">
            <div class="text-center py-4 text-slate-400">Carregando embarques disponíveis...</div>
        </div>
    </div>
</div>
<!-- FILTROS -->
<div class="section-card mb-6">
    <div class="section-body">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="form-label">Status</label>
                <select class="form-select" id="filtro-status" onchange="carregarEmbarques()">
                    <option value="">Todos</option>
                    <option value="planejado">📋 Planejado</option>
                    <option value="em_andamento">🚚 Em Andamento</option>
                    <option value="finalizado">✅ Finalizado</option>
                    <option value="cancelado">🚫 Cancelado</option>
                    <option value="problema">⚠️ Problema</option> 
                </select>
            </div>
            <div>
                <label class="form-label">Data Início</label>
                <input type="date" class="form-control" id="filtro-data-inicio" onchange="carregarEmbarques()">
            </div>
            <div>
                <label class="form-label">Data Fim</label>
                <input type="date" class="form-control" id="filtro-data-fim" onchange="carregarEmbarques()">
            </div>
            <div>
                <label class="form-label">Busca</label>
                <div class="flex gap-2">
                    <input type="text" class="form-control" id="filtro-busca" placeholder="Nº, veículo, motorista..." onkeypress="if(event.key==='Enter') carregarEmbarques()">
                    <button class="btn-primary-nutri px-4 py-2 rounded-xl" onclick="carregarEmbarques()">
                        <i class="fa-solid fa-search"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================================================================
   SEÇÃO: RASTREAMENTO DE ENTREGA
   ================================================================ -->
<div class="section-card mb-6">
    <div class="section-body">
        <div class="flex flex-wrap items-center gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="form-label flex items-center gap-2">
                    <i class="fa-solid fa-search text-emerald-600"></i> Rastrear Entrega
                </label>
                <div class="flex gap-2">
                    <input type="text" id="codigo-rastreamento" 
                           class="form-control flex-1" 
                           placeholder="Digite o código de rastreamento (ex: TRK76BADDB5)" 
                           onkeypress="if(event.key==='Enter') rastrearEntrega()">
                    <button class="btn-primary-nutri px-4 py-2" onclick="rastrearEntrega()">
                        <i class="fa-solid fa-search"></i> Rastrear
                    </button>
                    <button class="btn-secondary-nutri px-4 py-2" onclick="limparRastreamento()" title="Limpar">
                        <i class="fa-solid fa-times"></i>
                    </button>
                </div>
            </div>
            <div class="text-xs text-slate-400">
                <i class="fa-solid fa-info-circle"></i> Digite o código de rastreamento da entrega
            </div>
        </div>
        <div id="resultado-rastreamento" class="mt-3 hidden"></div>
    </div>
</div>

<!-- TABELA DE ROTAS CRIADAS (GRUPOS) -->
<div class="section-card">
    <div class="section-header flex justify-between items-center flex-wrap gap-2">
        <div>
            <span class="font-bold text-[#1a3c34]"><i class="fa-solid fa-list mr-2"></i> Rotas Criadas</span>
            <span class="text-xs text-slate-400 ml-2" id="info-paginacao">Carregando...</span>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-2">
                <label class="text-xs text-slate-400 font-medium">Mostrar:</label>
                <select id="limite-por-pagina" onchange="mudarLimite()" 
                class="border border-slate-200 rounded-lg px-2 py-1 text-sm bg-white dark:bg-slate-800 dark:border-slate-700 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50" selected>50</option>
                <option value="100">100</option>
            </select>
        </div>
        <div class="flex gap-1">
            <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" 
            id="btn-anterior" onclick="mudarPagina('anterior')">
            <i class="fa-solid fa-chevron-left"></i>
        </button>
        <span class="px-3 py-1.5 text-sm font-bold text-slate-600 dark:text-slate-300" id="pagina-atual">1</span>
        <button class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50 transition-colors disabled:opacity-50 dark:border-slate-700 dark:hover:bg-slate-800" 
        id="btn-proximo" onclick="mudarPagina('proximo')">
        <i class="fa-solid fa-chevron-right"></i>
    </button>
</div>
</div>
</div>
<div class="section-body p-0 overflow-x-auto">
    <table class="table-frota w-full">
        <thead>
            <tr>
                <th class="text-center" style="width: 45px;">#</th>
                <th>Embarque</th>
                <th>Rota</th>
                <th>Veículo</th>
                <th>Motorista</th>
                <th class="text-center">Entregas</th>
                <th class="text-center">Valor</th>
                <th class="text-center">Status</th>
                <th class="text-center" style="width: 140px;">Ações</th>
            </tr>
        </thead>
        <tbody id="lista-embarques">
            <tr>
                <td colspan="9" class="text-center py-8">
                    <div class="flex flex-col items-center gap-2">
                        <div class="skeleton skeleton-title mx-auto"></div>
                        <div class="skeleton skeleton-text w-48 mx-auto"></div>
                        <div class="skeleton skeleton-text w-32 mx-auto"></div>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
</div>
</div>

<!-- ================================================================
   MODAL: DETALHES DO EMBARQUE
   ================================================================ -->
   <div class="modal fade" id="modalDetalhes" tabindex="-1" data-bs-backdrop="static" style="display: none;">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa-solid fa-truck mr-2"></i> Detalhes do Embarque <span id="detalhes-numero" class="font-bold"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalhes-conteudo">
                <div class="text-center py-8">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary-nutri" onclick="exportarRota()" id="btn-exportar-rota">
                    <i class="fa-solid fa-file-export mr-2"></i> Exportar CSV
                </button>
                <button type="button" class="btn-primary-nutri" onclick="otimizarRota()" id="btn-otimizar-rota">
                    <i class="fa-solid fa-route mr-2"></i> Otimizar Rota
                </button>
                <button type="button" class="btn btn-secondary rounded-xl" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>
<!-- ================================================================
   SCRIPTS COMPLETOS
   ================================================================ -->
   <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
   <script src="/portal/modules/frota/assets/frota.js?v=<?= $version ?>"></script>

   <script>
// ======================================================================
// CONFIGURAÇÕES
// ======================================================================
    const DISTRIBUIDORA_LAT = parseFloat(document.getElementById('distribuidora_lat').value);
    const DISTRIBUIDORA_LNG = parseFloat(document.getElementById('distribuidora_lng').value);
    const DISTRIBUIDORA_ENDERECO = document.getElementById('distribuidora_endereco').value;

// ======================================================================
// ESTADO
// ======================================================================
    let paginaAtual = 1;
    let totalPaginas = 1;
    let totalRegistros = 0;
    let limitePorPagina = 50; 
    let embarqueIdDetalhes = 0;
    let mapaRota = null;
    let rotaMarkers = [];
    let rotaPolyline = null;
    let entregasAtuais = [];
    let entregaSelecionadaId = null;
    let embarquesSelecionados = [];
    let abaAtual = 'todos';
    let dadosEmbarquesERP = [];
    //CACHE 
    let cacheEmbarques = {
    dados: null,
    timestamp: null,
    validade: 60000 
};

// ======================================================================
// FUNÇÕES AUXILIARES
// ======================================================================
    function getAuthToken() {
        const token = localStorage.getItem('authToken');
        if (!token && !window.location.pathname.includes('login.php')) {
            window.location.href = '/portal/login.php';
        }
        return token;
    }

    function formatarDataHora(dataString) {
        if (!dataString) return '-';
        try {
            const data = new Date(dataString);
            if (isNaN(data.getTime())) return dataString;
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        } catch (e) {
            return dataString;
        }
    }

    function formatarData(dataString) {
        if (!dataString) return '-';
        try {
            const data = new Date(dataString);
            if (isNaN(data.getTime())) return dataString;
            return data.toLocaleDateString('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        } catch (e) {
            return dataString;
        }
    }

    function formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(valor);
    }

    function formatarPeso(peso) {
        const valor = parseFloat(peso);
        if (isNaN(valor) || valor === 0) return '0 kg';
        if (valor >= 1000) {
            return (valor / 1000).toFixed(1) + ' t';
        }
        return valor.toFixed(1) + ' kg';
    }

    function debounce(fn, delay) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function getUserId() {
        const el = document.getElementById('user_id');
        if (el && el.value && el.value !== '0') return parseInt(el.value);
        const userData = JSON.parse(localStorage.getItem('userData') || '{}');
        return userData.uid || userData.idusuario || 0;
    }

    function fecharModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $(el).modal('hide');
        } else {
            el.style.display = 'none';
            el.classList.remove('show');
            document.body.classList.remove('modal-open');
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) backdrop.remove();
        }
    }

    function mostrarNotificacao(mensagem, tipo) {
        tipo = tipo || 'info';
        const cores = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#3b82f6' };
        const icones = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };

        const old = document.querySelector('.toast-notification');
        if (old) old.remove();

        const toast = document.createElement('div');
        toast.className = 'toast-notification';
        toast.style.borderLeftColor = cores[tipo] || cores.info;
        toast.innerHTML = `
        <span class="icon">${icones[tipo] || icones.info}</span>
        <span style="flex:1;">${mensagem}</span>
        <button class="close-btn" onclick="this.parentElement.remove()">×</button>
        `;
        document.body.appendChild(toast);

        setTimeout(() => toast.classList.add('show'), 100);
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 5000);
    }

// ======================================================================
// SPINNER DE CARREGAMENTO MELHORADO
// ======================================================================

let spinnerAtivo = false;

function mostrarSpinner(texto, subtexto, progresso = 0) {
    // Remover spinner existente
    fecharSpinner();
    
    spinnerAtivo = true;
    
    const overlay = document.createElement('div');
    overlay.id = 'spinner-overlay';
    overlay.className = 'spinner-overlay';
    overlay.innerHTML = `
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="spinner-text">${texto || 'Carregando...'}</div>
            ${subtexto ? `<div class="spinner-subtext">${subtexto}</div>` : ''}
            <div class="progress-bar-container">
                <div class="progress-fill" style="width: ${progresso}%"></div>
            </div>
        </div>
    `;
    
    document.body.appendChild(overlay);
    
    // Impedir scroll
    document.body.style.overflow = 'hidden';
}

function atualizarSpinner(texto, subtexto, progresso) {
    const overlay = document.getElementById('spinner-overlay');
    if (!overlay) return;
    
    const textEl = overlay.querySelector('.spinner-text');
    const subtextEl = overlay.querySelector('.spinner-subtext');
    const progressEl = overlay.querySelector('.progress-fill');
    
    if (textEl && texto) textEl.textContent = texto;
    if (subtextEl && subtexto !== undefined) {
        if (subtexto) {
            subtextEl.textContent = subtexto;
            subtextEl.style.display = 'block';
        } else {
            subtextEl.style.display = 'none';
        }
    }
    if (progressEl && progresso !== undefined) {
        progressEl.style.width = Math.min(100, Math.max(0, progresso)) + '%';
    }
}

function fecharSpinner() {
    const overlay = document.getElementById('spinner-overlay');
    if (overlay) {
        overlay.style.animation = 'fadeInOverlay 0.3s ease reverse';
        setTimeout(() => {
            overlay.remove();
            document.body.style.overflow = '';
        }, 300);
    }
    spinnerAtivo = false;
}
// ======================================================================
// TEMA ESCURO
// ======================================================================
    function toggleTheme() {
        const html = document.documentElement;
        const current = html.getAttribute('data-theme');
        const newTheme = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        const icon = document.querySelector('.theme-toggle i');
        if (icon) icon.className = newTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }

    document.addEventListener('DOMContentLoaded', function() {
        const saved = localStorage.getItem('theme') || 'light';
        document.documentElement.setAttribute('data-theme', saved);
        const icon = document.querySelector('.theme-toggle i');
        if (icon) icon.className = saved === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    });

// ======================================================================
// CÁLCULOS
// ======================================================================
    function calcularDistancia(lat1, lng1, lat2, lng2) {
        if (!lat1 || !lng1 || !lat2 || !lng2) return null;
        const R = 6371;
        const dLat = deg2rad(lat2 - lat1);
        const dLng = deg2rad(lng2 - lng1);
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
        Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
        Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return Math.round((R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a))) * 100) / 100;
    }

    function deg2rad(deg) { return deg * (Math.PI / 180); }

    function gerarNomeEmbarque(embarques) {
        if (!embarques || embarques.length === 0) return 'Novo Embarque';
        if (embarques.length === 1) return embarques[0].rota || 'EMB-' + embarques[0].idembarque;

        const nomes = embarques.map(e => (e.rota || '').split(' ')[0] || 'Rota');
        const unicos = [...new Set(nomes.filter(n => n.length > 0))];
        if (unicos.length === 1) return unicos[0] + ' - Grupo ' + embarques.length;
        return 'Grupo ' + embarques.length + ' (' + unicos.slice(0, 3).join(', ') + (unicos.length > 3 ? '...' : '') + ')';
    }

// ======================================================================
// CARREGAR EMBARQUES DISPONÍVEIS DO ERP
// ======================================================================
    async function carregarDisponiveis() {
        const token = getAuthToken();
        if (!token) return;

        try {
            const response = await fetch('/v1/frota/importar/embarques-erp', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const dados = await response.json();

            if (dados.success && dados.data && dados.data.length > 0) {
                dadosEmbarquesERP = dados.data;
                renderizarAbas(dados.data);
                renderizarDisponiveis(dados.data, abaAtual);
            } else {
                document.getElementById('lista-disponiveis').innerHTML = `
                <div class="text-center py-4 text-slate-400">
                    <i class="fa-regular fa-circle-check text-2xl block mb-2"></i>
                    ${dados.mensagem || 'Nenhum embarque disponível'}
                </div>
                `;
                document.getElementById('info-disponiveis').textContent = '0 embarques';
                document.getElementById('abas-disponiveis').innerHTML = '';
            }
        } catch (error) {
            document.getElementById('lista-disponiveis').innerHTML = `
            <div class="text-center py-4 text-red-500">Erro ao carregar dados</div>
            `;
        }
    }

// ======================================================================
// RENDERIZAR ABAS DINAMICAMENTE
// ======================================================================
    function renderizarAbas(embarques) {
        const container = document.getElementById('abas-disponiveis');
        if (!container) return;

        const statusCount = { 'todos': embarques.length };
        embarques.forEach(emb => {
            const status = emb.status_logistico || 'PENDENTE';
            statusCount[status] = (statusCount[status] || 0) + 1;
        });

        const ordem = ['todos', 'PENDENTE', 'SEPARADO', 'CARREGADO'];
        const labels = {
            'todos': 'Todos',
            'PENDENTE': 'Pendentes',
            'SEPARADO': 'Separados',
            'CARREGADO': 'Carregados'
        };

        let html = '';
        ordem.forEach(key => {
            if (statusCount[key] && statusCount[key] > 0) {
                const ativa = abaAtual === key ? 'ativa' : '';
                html += `
                <button class="aba-disponivel ${ativa}" data-aba="${key}" onclick="mudarAba('${key}')">
                    ${labels[key] || key}
                    <span class="badge-aba">${statusCount[key]}</span>
                </button>
                `;
            }
        });
        container.innerHTML = html;
    }

// ======================================================================
// MUDAR ABA
// ======================================================================
    function mudarAba(aba) {
        abaAtual = aba;
        document.querySelectorAll('.aba-disponivel').forEach(btn => {
            btn.classList.toggle('ativa', btn.dataset.aba === aba);
        });
        renderizarDisponiveis(dadosEmbarquesERP, aba);
        embarquesSelecionados = [];
        atualizarContadorSelecao();
    }

// ======================================================================
// RENDERIZAR LISTA DE DISPONÍVEIS
// ======================================================================
    function renderizarDisponiveis(embarques, aba) {
        const container = document.getElementById('lista-disponiveis');

        const abaNormalizada = aba.toUpperCase();
        let filtrados = embarques;
        if (aba !== 'todos') {
            filtrados = embarques.filter(emb => {
                const statusERP = (emb.status_logistico || 'PENDENTE').toUpperCase();
                return statusERP === abaNormalizada;
            });
        }

        let totalPedidos = 0;
        let totalValor = 0;
        let filiais = new Set();
        filtrados.forEach(emb => {
            totalPedidos += emb.total_pedidos || 0;
            totalValor += emb.valor_total || 0;
            if (emb.idfilial) filiais.add(emb.idfilial);
        });

        document.getElementById('info-disponiveis').textContent =
    `${filtrados.length} embarques • ${totalPedidos} pedidos • R$ ${totalValor.toFixed(2)} • ${filiais.size} filiais`;

    if (filtrados.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4 text-slate-400">
                <i class="fa-regular fa-inbox text-2xl block mb-2"></i>
                Nenhum embarque com este status
            </div>
        `;
        return;
    }

    let html = '';
    filtrados.forEach(emb => {
        const statusClass = {
            'PENDENTE': 'bg-yellow-100 text-yellow-700',
            'SEPARADO': 'bg-blue-100 text-blue-700',
            'CARREGADO': 'bg-green-100 text-green-700'
        } [emb.status_logistico] || 'bg-slate-100 text-slate-700';

        const clientesNomes = (emb.clientes || []).slice(0, 3).map(c => c.nome || c.razao || 'Cliente');
        const temMais = (emb.clientes || []).length > 3;

        html += `
            <div class="embarque-disponivel-item flex items-start gap-4 p-4 border border-slate-200 rounded-xl hover:shadow-md transition-all cursor-pointer ${embarquesSelecionados.includes(emb.idembarque) ? 'selecionado' : ''}"
                 data-id="${emb.idembarque}" onclick="toggleSelecionarDisponivel(${emb.idembarque})">
                <div class="checkbox flex-shrink-0 mt-1">
                    <i class="fa-solid fa-check ${embarquesSelecionados.includes(emb.idembarque) ? 'text-white' : 'text-transparent'}"></i>
                </div>
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold text-[#1a3c34]">#${emb.idembarque}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold ${statusClass}">${emb.status_logistico || 'PENDENTE'}</span>
                        ${emb.gerou_nf === 'S' ? '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">NF Gerada</span>' : ''}
                        ${emb.pex_conferido === 'S' ? '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Separado</span>' : ''}
                    </div>
                    <p class="text-sm text-slate-500 mt-1">${emb.rota || 'Sem descrição'}</p>
                    <div class="flex flex-wrap gap-3 mt-2 text-xs">
                        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">📦 ${emb.total_pedidos || 0} pedidos</span>
                        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">💰 ${formatarMoeda(emb.valor_total || 0)}</span>
                        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">🏢 Filial ${emb.idfilial || '-'}</span>
            ${emb.placa ? `<span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">🚛 ${emb.placa}</span>` : ''}
                    </div>
            ${clientesNomes.length ? `
                        <div class="flex flex-wrap gap-1 mt-2">
                ${clientesNomes.map(n => `<span class="text-xs bg-slate-200 px-2 py-0.5 rounded-full">${n}</span>`).join('')}
                ${temMais ? `<span class="text-xs text-slate-400">+${emb.clientes.length - 3}</span>` : ''}
                        </div>
                ` : ''}
                </div>
            </div>
            `;
        });

    container.innerHTML = html;
    atualizarContadorSelecao();
}

// ======================================================================
// SELECIONAR/DESELECIONAR DISPONÍVEL
// ======================================================================
function toggleSelecionarDisponivel(id) {
    const index = embarquesSelecionados.indexOf(id);
    if (index > -1) {
        embarquesSelecionados.splice(index, 1);
    } else {
        embarquesSelecionados.push(id);
    }
    document.querySelectorAll('.embarque-disponivel-item').forEach(el => {
        const itemId = parseInt(el.dataset.id);
        if (embarquesSelecionados.includes(itemId)) {
            el.classList.add('selecionado');
            el.querySelector('.checkbox i').className = 'fa-solid fa-check text-white';
        } else {
            el.classList.remove('selecionado');
            el.querySelector('.checkbox i').className = 'fa-solid fa-check text-transparent';
        }
    });
    atualizarContadorSelecao();
}

// ======================================================================
// TOGGLE DA SEÇÃO DISPONÍVEIS
// ======================================================================
function toggleDisponiveis() {
    const body = document.getElementById('disponiveis-body');
    const icon = document.getElementById('toggle-disponiveis-icon');
    if (!body) return;

    const isRecolhido = body.classList.toggle('recolhido');
    icon.classList.toggle('recolhido');

    try {
        localStorage.setItem('frota_disponiveis_recolhido', isRecolhido ? '1' : '0');
    } catch (e) {}
}

function restaurarEstadoToggle() {
    try {
        const recolhido = localStorage.getItem('frota_disponiveis_recolhido');
        if (recolhido === '1') {
            const body = document.getElementById('disponiveis-body');
            const icon = document.getElementById('toggle-disponiveis-icon');
            if (body && !body.classList.contains('recolhido')) {
                body.classList.add('recolhido');
                icon.classList.add('recolhido');
            }
        }
    } catch (e) {}
}

// ======================================================================
// ATUALIZAR CONTADOR DE SELEÇÃO
// ======================================================================
function atualizarContadorSelecao() {
    const total = embarquesSelecionados.length;
    document.getElementById('total-selecionados-disponiveis').textContent = total;
    const btn = document.getElementById('btn-criar-rotas-disponiveis');
    if (btn) btn.disabled = total === 0;
}

// ======================================================================
// RECARREGAR LISTA DE DISPONÍVEIS
// ======================================================================
function atualizarDisponiveis() {
    embarquesSelecionados = [];
    carregarDisponiveis();
}

// ======================================================================
// MUDAR LIMITE POR PÁGINA
// ======================================================================
function mudarLimite() {
    const select = document.getElementById('limite-por-pagina');
    if (select) {
        limitePorPagina = parseInt(select.value);
        paginaAtual = 1;  // Voltar para página 1 ao mudar o limite
        carregarEmbarques();
    }
}

// ======================================================================
// INICIALIZAÇÃO
// ======================================================================
document.addEventListener('DOMContentLoaded', function() {
    restaurarEstadoToggle();
    
    // Restaurar valor do select de limite
    const selectLimite = document.getElementById('limite-por-pagina');
    if (selectLimite) {
        selectLimite.value = limitePorPagina;
    }
    
    // Carregar dados iniciais
    carregarDisponiveis();
    carregarEmbarques();
    
    // Atualização automática a cada 60 segundos
    setInterval(atualizarDisponiveis, 60000);
    
    // ================================================================
    // 🔥 EVENT LISTENERS DOS FILTROS - LIMPAR CACHE AO MUDAR
    // ================================================================
    
    // Filtro Status
    const filtroStatus = document.getElementById('filtro-status');
    if (filtroStatus) {
        filtroStatus.addEventListener('change', function() {
            cacheEmbarques.dados = null;
            cacheEmbarques.timestamp = null;
            carregarEmbarques();
        });
    }
    
    // Filtro Data Início
    const filtroDataInicio = document.getElementById('filtro-data-inicio');
    if (filtroDataInicio) {
        filtroDataInicio.addEventListener('change', function() {
            cacheEmbarques.dados = null;
            cacheEmbarques.timestamp = null;
            carregarEmbarques();
        });
    }
    
    // Filtro Data Fim
    const filtroDataFim = document.getElementById('filtro-data-fim');
    if (filtroDataFim) {
        filtroDataFim.addEventListener('change', function() {
            cacheEmbarques.dados = null;
            cacheEmbarques.timestamp = null;
            carregarEmbarques();
        });
    }
    
    // Filtro Busca (Enter)
    const filtroBusca = document.getElementById('filtro-busca');
    if (filtroBusca) {
        filtroBusca.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                cacheEmbarques.dados = null;
                cacheEmbarques.timestamp = null;
                carregarEmbarques();
            }
        });
    }
    
    // ================================================================
    // 🔥 ATUALIZAR INDICADOR DE CACHE A CADA 5 SEGUNDOS
    // ================================================================
    setInterval(atualizarIndicadorCache, 5000);
});

// ======================================================================
// SELECIONAR ENTREGA NO MAPA
// ======================================================================
function selecionarEntregaNoMapa(entregaId) {
    entregaSelecionadaId = entregaId;
    document.querySelectorAll('.entrega-item').forEach(function(item) {
        item.classList.toggle('ativa', parseInt(item.dataset.id) === entregaId);
    });
    centralizarNoMapa(entregaId);
}

function centralizarNoMapa(entregaId) {
    const entrega = entregasAtuais.find(function(e) { return e.id === entregaId; });
    if (!entrega || !entrega.latitude || !entrega.longitude) return;
    if (mapaRota) {
        mapaRota.setView([entrega.latitude, entrega.longitude], 15);
    }
}

// ======================================================================
// EXPORTAR ROTA
// ======================================================================
function exportarRota() {
    if (!entregasAtuais || entregasAtuais.length === 0) {
        mostrarNotificacao('Nenhuma entrega para exportar', 'warning');
        return;
    }
    let csv = 'Ordem,Cliente,Endereco,Valor,Peso,Status,Telefone,Pedidos\n';
    entregasAtuais.forEach(function(e, i) {
        const statusMap = {
            'pendente': 'Pendente',
            'em_entrega': 'Em Entrega',
            'entregue': 'Entregue',
            'falha': 'Falha',
            'entregue_com_problema': 'Entregue c/ Problema'
        };
        csv += (i + 1) + ',"' + (e.cliente_nome || 'Cliente') + '",';
        csv += '"' + (e.endereco || '') + ' ' + (e.numero || '') + ' ' + (e.bairro || '') + ' ' + (e.cidade || '') + '",';
        csv += (e.valor_total || 0) + ',' + (e.peso_total || 0) + ',' + (statusMap[e.status] || e.status) + ',';
        csv += '"' + (e.cliente_telefone || '') + '",';
        csv += '"' + (e.pedidos_ids || '') + '"\n';
    });
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'rota_' + embarqueIdDetalhes + '_' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
    mostrarNotificacao('Relatorio exportado com sucesso!', 'success');
}

// ======================================================================
// RASTREAMENTO DE ENTREGA POR CÓDIGO
// ======================================================================

async function rastrearEntrega() {
    const codigo = document.getElementById('codigo-rastreamento').value.trim();
    if (!codigo) {
        mostrarNotificacao('Digite um código de rastreamento', 'warning');
        return;
    }

    const token = getAuthToken();
    if (!token) return;

    const container = document.getElementById('resultado-rastreamento');
    container.classList.remove('hidden');
    container.innerHTML = `
        <div class="text-center py-4">
            <i class="fa-solid fa-spinner fa-spin text-2xl text-emerald-600"></i>
            <p class="text-sm text-slate-400 mt-2">Buscando entrega...</p>
        </div>
    `;

    try {
        const response = await fetch(`/v1/frota/entregas/rastreamento/${encodeURIComponent(codigo)}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        const dados = await response.json();

        if (!dados.success) {
            container.innerHTML = `
                <div class="p-4 border border-red-200 rounded-xl bg-red-50 dark:bg-red-900/20">
                    <p class="text-red-600 dark:text-red-400"><i class="fa-solid fa-exclamation-circle"></i> ${dados.error || 'Código não encontrado'}</p>
                    <p class="text-sm text-slate-400 mt-1">Verifique o código e tente novamente.</p>
                </div>
            `;
            return;
        }

        const entrega = dados.data;
        renderizarResultadoRastreamento(entrega);

    } catch (error) {
        container.innerHTML = `
            <div class="p-4 border border-red-200 rounded-xl bg-red-50 dark:bg-red-900/20">
                <p class="text-red-600 dark:text-red-400"><i class="fa-solid fa-exclamation-circle"></i> Erro ao rastrear: ${error.message}</p>
            </div>
        `;
    }
}

function renderizarResultadoRastreamento(entrega) {
    const container = document.getElementById('resultado-rastreamento');
    
    const statusMap = {
        'pendente': { label: '⏳ Pendente', color: '#3b82f6', bg: '#dbeafe' },
        'em_entrega': { label: '🚚 Em Rota', color: '#f59e0b', bg: '#fef3c7' },
        'entregue': { label: '✅ Entregue', color: '#10b981', bg: '#d1fae5' },
        'entregue_com_problema': { label: '⚠️ Entregue c/ Problema', color: '#f59e0b', bg: '#fef3c7' },
        'falha': { label: '❌ Falha', color: '#ef4444', bg: '#fee2e2' },
        'cancelada': { label: '🚫 Cancelada', color: '#64748b', bg: '#e2e8f0' }
    };

    const statusInfo = statusMap[entrega.status] || { label: entrega.status || 'Desconhecido', color: '#64748b', bg: '#e2e8f0' };

    // Timeline de eventos
    let timelineHtml = '';
    if (entrega.timeline && entrega.timeline.length > 0) {
        timelineHtml = `
            <div class="mt-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-lg">
                <p class="text-xs font-bold text-slate-400 uppercase mb-2">📋 Linha do Tempo</p>
                ${entrega.timeline.map(event => `
                    <div class="flex items-center gap-3 py-1 border-b border-slate-100 dark:border-slate-700 last:border-0">
                        <span class="text-xs text-slate-400 whitespace-nowrap">${formatarDataHora(event.data_hora)}</span>
                        <span class="text-sm text-slate-600 dark:text-slate-300">${event.descricao}</span>
                        ${event.foto_url ? `<span class="text-xs text-blue-600">📸</span>` : ''}
                    </div>
                `).join('')}
            </div>
        `;
    }

    container.innerHTML = `
        <div class="p-4 border border-emerald-200 rounded-xl bg-emerald-50 dark:bg-emerald-900/20 dark:border-emerald-800">
            <div class="flex flex-wrap justify-between items-start gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-bold text-[#1a3c34] dark:text-white text-lg">${entrega.cliente_nome || 'Cliente'}</p>
                        <span class="text-xs px-2 py-1 rounded-full" style="background: ${statusInfo.bg}; color: ${statusInfo.color};">
                            ${statusInfo.label}
                        </span>
                    </div>
                    <p class="text-sm text-slate-600 dark:text-slate-300 mt-1">${entrega.endereco || ''} ${entrega.numero || ''} - ${entrega.cidade || ''}/${entrega.uf || ''}</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        ${entrega.motorista_nome ? `<span class="text-xs bg-slate-200 dark:bg-slate-700 px-2 py-1 rounded-full">👤 ${entrega.motorista_nome}</span>` : ''}
                        ${entrega.placa ? `<span class="text-xs bg-slate-200 dark:bg-slate-700 px-2 py-1 rounded-full">🚛 ${entrega.placa}</span>` : ''}
                        ${entrega.nome_recebedor ? `<span class="text-xs bg-emerald-200 dark:bg-emerald-800 px-2 py-1 rounded-full">📝 ${entrega.nome_recebedor}</span>` : ''}
                    </div>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-xs text-slate-400">Código</p>
                    <p class="font-mono font-bold text-sm text-blue-600 dark:text-blue-400">${entrega.codigo_rastreamento}</p>
                    ${entrega.horario_entrega ? `<p class="text-xs text-slate-400 mt-1">📅 ${formatarDataHora(entrega.horario_entrega)}</p>` : ''}
                </div>
            </div>
            ${timelineHtml}
            <div class="mt-3 flex gap-2 flex-wrap">
                <button onclick="verDetalhes(${entrega.id})" class="btn-primary-nutri text-sm py-1.5 px-4">
                    <i class="fa-solid fa-eye"></i> Ver Detalhes
                </button>
                <button onclick="copiarCodigoRastreamento('${entrega.codigo_rastreamento}')" class="btn-secondary-nutri text-sm py-1.5 px-4">
                    <i class="fa-solid fa-copy"></i> Copiar
                </button>
                <button onclick="limparRastreamento()" class="btn-secondary-nutri text-sm py-1.5 px-4">
                    <i class="fa-solid fa-times"></i> Fechar
                </button>
            </div>
        </div>
    `;
    
    container.classList.remove('hidden');
}

function limparRastreamento() {
    document.getElementById('codigo-rastreamento').value = '';
    document.getElementById('resultado-rastreamento').classList.add('hidden');
    document.getElementById('resultado-rastreamento').innerHTML = '';
}

function copiarCodigoRastreamento(codigo) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(codigo).then(() => {
            mostrarNotificacao('✅ Código copiado para a área de transferência!', 'success');
        }).catch(() => {
            fallbackCopiarCodigo(codigo);
        });
    } else {
        fallbackCopiarCodigo(codigo);
    }
}

function fallbackCopiarCodigo(codigo) {
    const input = document.createElement('input');
    input.value = codigo;
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    mostrarNotificacao('✅ Código copiado para a área de transferência!', 'success');
}

// ======================================================================
// CARREGAR EMBARQUES (COM CACHE)
// ======================================================================
async function carregarEmbarques(forceRefresh = false) {
    const token = getAuthToken();
    if (!token) return;

    // 🔥 Verificar cache - se não for força refresh e o cache for válido
    const agora = Date.now();
    if (!forceRefresh && cacheEmbarques.dados && 
        (agora - cacheEmbarques.timestamp) < cacheEmbarques.validade) {
        console.log('📦 Usando cache de embarques (', Math.round((agora - cacheEmbarques.timestamp) / 1000), 's atrás)');
        renderizarEmbarques(cacheEmbarques.dados.data, cacheEmbarques.dados.pagination);
        return;
    }

    const status = document.getElementById('filtro-status').value;
    const busca = document.getElementById('filtro-busca').value;
    const dataInicio = document.getElementById('filtro-data-inicio').value;
    const dataFim = document.getElementById('filtro-data-fim').value;

    let url = '/v1/frota/embarques?pagina=' + paginaAtual + '&limite=' + limitePorPagina;
    if (status) url += '&status=' + status;
    if (busca) url += '&busca=' + encodeURIComponent(busca);
    if (dataInicio) url += '&data_inicio=' + dataInicio;
    if (dataFim) url += '&data_fim=' + dataFim;

    try {
        const response = await fetch(url, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (response.status === 401) {
            if (!window.location.pathname.includes('login.php')) {
                window.location.href = '/portal/login.php';
            }
            return;
        }

        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                // 🔥 Salvar no cache
                cacheEmbarques.dados = dados;
                cacheEmbarques.timestamp = Date.now();
                
                renderizarEmbarques(dados.data, dados.pagination);
            }
        }
    } catch (error) {
        console.error('Erro ao carregar embarques:', error);
        mostrarNotificacao('Erro ao carregar embarques', 'error');
    }
}

// ======================================================================
// ATUALIZAR INDICADOR DE CACHE
// ======================================================================
function atualizarIndicadorCache() {
    const indicator = document.getElementById('cache-indicator');
    const tempoEl = document.getElementById('cache-tempo');
    
    if (!indicator || !tempoEl) return;
    
    if (cacheEmbarques.dados && cacheEmbarques.timestamp) {
        const idade = Math.round((Date.now() - cacheEmbarques.timestamp) / 1000);
        if (idade < cacheEmbarques.validade / 1000) {
            indicator.classList.remove('hidden');
            tempoEl.textContent = idade + 's';
        } else {
            indicator.classList.add('hidden');
        }
    } else {
        indicator.classList.add('hidden');
    }
}

// Atualizar a cada 5 segundos
setInterval(atualizarIndicadorCache, 5000);

// ======================================================================
// RENDERIZAR EMBARQUES NA TABELA - VERSÃO COMPLETA
// ======================================================================
function renderizarEmbarques(embarques, pagination) {
    const tbody = document.getElementById('lista-embarques');

    if (!embarques || embarques.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-slate-400">
            <i class="fa-regular fa-truck text-3xl block mb-2"></i>
            Nenhum embarque encontrado
    </td></tr>`;
    return;
}

let html = '';
embarques.forEach((emb, index) => {
        // Status com ícones
    const statusIcon = {
        'planejado': '📋',
        'em_andamento': '🚚',
        'finalizado': '✅',
        'cancelado': '🚫',
        'problema': '⚠️'
    } [emb.status] || '';

    const statusClass = {
        'planejado': 'planejado',
        'em_andamento': 'em_andamento',
        'finalizado': 'finalizado',
        'cancelado': 'cancelado',
        'problema': 'problema'
    } [emb.status] || 'planejado';

    const statusText = {
        'planejado': 'Planejado',
        'em_andamento': 'Em Andamento',
        'finalizado': 'Finalizado',
        'cancelado': 'Cancelado',
        'problema': 'Problema'
    } [emb.status] || emb.status;

    const total = emb.total_entregas || 0;
    const concluidas = emb.entregas_concluidas || 0;
    const progresso = total > 0 ? Math.round((concluidas / total) * 100) : 0;

    let barClass = 'em-andamento';
    if (emb.status === 'problema') {
        barClass = 'problema';
    } else if (progresso >= 100) {
        barClass = 'concluido';
    }

    const valorTotal = emb.valor_total_entregas || 0;
    const pesoTotal = emb.peso_total_entregas || emb.peso_total || 0;

    const placa = emb.veiculo_placa || '';
    const modelo = emb.veiculo_modelo || '';
    const temVeiculo = placa && placa.trim() !== '' && placa !== 'SEM VEÍCULO';

    const veiculoDisplay = temVeiculo ?
`<span class="font-medium text-[#1a3c34] dark:text-white">${placa}</span>` :
'<span class="text-slate-400 text-xs">Não definido</span>';

const veiculoModelo = temVeiculo && modelo ?
`<span class="text-xs text-slate-400 block">${modelo}</span>` :
'';

const nomeRota = emb.nome_embarque || emb.observacoes || emb.rota || '-';

const isGrupo = (emb.total_embarques_agrupados && emb.total_embarques_agrupados > 1) || false;
const qtdEmbarques = isGrupo ? ` (${emb.total_embarques_agrupados} embarques)` : '';

let idsParaAcao = [emb.id];
if (isGrupo && emb.erp_ids_agrupados) {
    idsParaAcao = emb.erp_ids_agrupados.split(',').map(Number);
}
const idsString = idsParaAcao.join(',');

        // 🔥 Botão específico para status problema
const btnProblema = emb.status === 'problema' ? `
            <button class="btn-icone amber" onclick="verDetalhes(${emb.id})" title="Ver problemas">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </button>
` : '';

html += `
            <tr>
                <td class="text-center font-bold text-slate-400">${index + 1}</td>
                <td>
                    <div class="font-bold text-[#1a3c34] dark:text-white">
                        ${emb.numero_embarque || '#' + emb.id}
                        ${qtdEmbarques}
                    </div>
                    <div class="text-xs text-slate-400">${emb.nome_embarque || ''}</div>
                    ${emb.erp_embarque_id ? '<span class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-1.5 py-0.5 rounded-full">ERP: #' + emb.erp_embarque_id + '</span>' : ''}
                    ${isGrupo ? '<span class="text-xs bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 px-1.5 py-0.5 rounded-full">📦 Grupo</span>' : ''}
                </td>
                <td>
                    <span class="text-sm">${nomeRota}</span>
                </td>
                <td>
                    ${veiculoDisplay}
                    ${veiculoModelo}
                </td>
                <td>${emb.motorista_nome || '-'}</td>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-sm font-bold">${concluidas}/${total}</span>
                        <div class="progress-thin w-16">
                            <div class="bar ${barClass}" style="width: ${progresso}%"></div>
                        </div>
                    </div>
                </td>
                <td class="text-center font-medium text-emerald-600 dark:text-emerald-400">${formatarMoeda(valorTotal)}</td>
                <td class="text-center font-medium text-slate-600 dark:text-slate-300">${formatarPeso(pesoTotal)}</td>
                <td class="text-center">
                    <span class="status-badge ${statusClass}">
                        ${statusIcon} ${statusText}
                    </span>
                </td>
                <td class="text-center">
                    <div class="flex items-center justify-center gap-1 flex-wrap">
    ${isGrupo ? `
                            <button class="btn-icone azul" onclick="verDetalhesGrupo([${idsString}])" title="Ver detalhes do grupo">
                                <i class="fa-solid fa-layer-group"></i>
                            </button>
        ` : `
                            <button class="btn-icone azul" onclick="verDetalhes(${emb.id})" title="Ver detalhes">
                                <i class="fa-solid fa-eye"></i>
                            </button>
        `}
                        <button class="btn-icone azul" onclick="abrirModalEditarGrupo([${idsString}])" title="Editar grupo">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn-icone vermelho" onclick="removerEntregaGrupo([${idsString}])" title="Remover entrega">
                            <i class="fa-solid fa-trash-alt"></i>
                        </button>
        ${emb.status === 'planejado' ? `
                            <button class="btn-icone verde" onclick="iniciarGrupo([${idsString}])" title="Iniciar todos">
                                <i class="fa-solid fa-play"></i>
                            </button>
            ` : ''}
            ${emb.status === 'em_andamento' || emb.status === 'problema' ? `
                            <button class="btn-icone amber" onclick="finalizarGrupo([${idsString}])" title="Finalizar todos">
                                <i class="fa-solid fa-flag-checkered"></i>
                            </button>
                ` : ''}
                ${emb.status !== 'finalizado' && emb.status !== 'cancelado' ? `
                            <button class="btn-icone vermelho" onclick="cancelarGrupo([${idsString}])" title="Cancelar todos">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                    ` : ''}
                        ${btnProblema}
                    </div>
                </td>
            </tr>
                `;
            });

tbody.innerHTML = html;

if (pagination) {
    totalPaginas = pagination.total_paginas || 1;
    totalRegistros = pagination.total || 0;
    document.getElementById('info-paginacao').textContent =
    totalRegistros + ' registros • Página ' + (pagination.pagina || 1) + ' de ' + totalPaginas;
    document.getElementById('pagina-atual').textContent = pagination.pagina || 1;
    paginaAtual = pagination.pagina || 1;
    document.getElementById('total-embarques').textContent = totalRegistros;
}
}

function mudarPagina(direcao) {
    if (direcao === 'anterior' && paginaAtual > 1) paginaAtual--;
    else if (direcao === 'proximo' && paginaAtual < totalPaginas) paginaAtual++;
    carregarEmbarques();
}

// ======================================================================
// FUNÇÕES DE GRUPO
// ======================================================================
async function cancelarGrupo(ids) {
    let listaIds = [];
    if (Array.isArray(ids)) {
        listaIds = ids;
    } else if (typeof ids === 'string') {
        listaIds = ids.split(',').map(Number);
    } else if (typeof ids === 'number') {
        listaIds = [ids];
    }
    listaIds = listaIds.filter(id => id && !isNaN(id));

    if (listaIds.length === 0) {
        mostrarNotificacao('Nenhum ID válido', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'Cancelar todos os embarques?',
        text: `${listaIds.length} embarques serão cancelados. Esta ação não pode ser desfeita.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sim, cancelar todos',
        cancelButtonText: 'Voltar'
    });

    if (!result.isConfirmed) return;

    const token = getAuthToken();
    if (!token) return;

    let sucessos = 0;
    let erros = 0;

    for (const id of listaIds) {
        try {
            const response = await fetch('/v1/frota/embarques/' + id + '/cancelar', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (response.ok) {
                sucessos++;
            } else {
                erros++;
            }
        } catch (e) {
            erros++;
        }
    }

    mostrarNotificacao(`✅ ${sucessos} cancelados, ${erros} falhas`, sucessos > 0 ? 'warning' : 'error');
    carregarEmbarques();
}

async function verDetalhesGrupo(ids) {
    let listaIds = [];
    if (Array.isArray(ids)) {
        listaIds = ids;
    } else if (typeof ids === 'string') {
        listaIds = ids.split(',').map(Number);
    } else if (typeof ids === 'number') {
        listaIds = [ids];
    }
    listaIds = listaIds.filter(id => id && !isNaN(id));

    if (listaIds.length === 0) {
        mostrarNotificacao('Nenhum ID válido', 'error');
        return;
    }

    if (listaIds.length === 1) {
        verDetalhes(listaIds[0]);
        return;
    }

    const token = getAuthToken();
    if (!token) return;

    try {
        let todosEmbarques = [];
        let totalEntregas = 0;
        let totalConcluidas = 0;
        let totalValor = 0;
        let totalPeso = 0;
        let totalProblemas = 0;

        for (const id of listaIds) {
            const response = await fetch('/v1/frota/embarques/' + id, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (response.ok) {
                const dados = await response.json();
                if (dados.success) {
                    todosEmbarques.push(dados.data);
                    totalEntregas += dados.data.total_entregas || 0;
                    totalConcluidas += dados.data.entregas_concluidas || 0;
                    totalValor += dados.data.valor_total_entregas || 0;
                    totalPeso += dados.data.peso_total_entregas || 0;
                    if (dados.data.status === 'problema') totalProblemas++;
                }
            }
        }

        if (todosEmbarques.length === 0) {
            mostrarNotificacao('Nenhum embarque encontrado', 'error');
            return;
        }

        const progresso = totalEntregas > 0 ? Math.round((totalConcluidas / totalEntregas) * 100) : 0;

        let html = `
            <div class="text-left">
                <div style="background: #f0fdf4; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; border: 1px solid #bbf7d0;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px;">
                        <div><strong>📦 Embarques:</strong> ${todosEmbarques.length}</div>
                        <div><strong>📋 Entregas:</strong> ${totalConcluidas}/${totalEntregas}</div>
                        <div><strong>💰 Valor:</strong> ${formatarMoeda(totalValor)}</div>
                        <div><strong>⚖️ Peso:</strong> ${formatarPeso(totalPeso)}</div>
                        <div><strong>📊 Progresso:</strong> ${progresso}%</div>
            ${totalProblemas > 0 ? `<div><strong>⚠️ Problemas:</strong> ${totalProblemas}</div>` : ''}
                    </div>
                </div>
                <div class="max-h-[200px] overflow-y-auto">
        `;

        todosEmbarques.forEach(emb => {
            const statusIcon = emb.status === 'problema' ? '⚠️' : '📦';
            const statusClass = emb.status === 'problema' ? 'problema' : emb.status;
            html += `
                <div class="flex items-center justify-between py-1 border-b border-slate-100 last:border-0">
                    <span class="text-sm font-medium">${statusIcon} ${emb.numero_embarque || '#' + emb.id}</span>
                    <span class="text-xs text-slate-400">${emb.veiculo_placa || '-'} | ${emb.motorista_nome || '-'}</span>
                    <span class="text-xs">${emb.entregas_concluidas || 0}/${emb.total_entregas || 0}</span>
                    <span class="status-badge ${statusClass}">${emb.status || 'planejado'}</span>
                </div>
            `;
        });

        html += `</div></div>`;

        Swal.fire({
            title: '📦 Grupo de Embarques',
            html: html,
            width: '600px',
            confirmButtonText: 'OK',
            confirmButtonColor: '#10b981'
        });

    } catch (error) {
        mostrarNotificacao('Erro ao carregar grupo', 'error');
    }
}

// ======================================================================
// INICIAR GRUPO (COM SPINNER MELHORADO)
// ======================================================================
async function iniciarGrupo(ids) {
    let listaIds = [];
    if (Array.isArray(ids)) {
        listaIds = ids;
    } else if (typeof ids === 'string') {
        listaIds = ids.split(',').map(Number);
    } else if (typeof ids === 'number') {
        listaIds = [ids];
    }
    listaIds = listaIds.filter(id => id && !isNaN(id));

    if (listaIds.length === 0) {
        mostrarNotificacao('Nenhum ID válido', 'error');
        return;
    }

    const result = await Swal.fire({
        title: 'Iniciar todos os embarques?',
        text: `${listaIds.length} embarques serão iniciados.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sim, iniciar todos',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    const token = getAuthToken();
    if (!token) return;

    // 🔥 SPINNER MELHORADO
    mostrarSpinner(
        'Iniciando embarques...',
        `Processando ${listaIds.length} embarques...`,
        0
    );

    let sucessos = 0;
    let erros = 0;
    let errosDetalhes = [];

    for (let i = 0; i < listaIds.length; i++) {
        const id = listaIds[i];
        try {
            const response = await fetch('/v1/frota/embarques/' + id + '/iniciar', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await response.json();
            if (data.success) {
                sucessos++;
            } else {
                erros++;
                errosDetalhes.push(`Embarque #${id}: ${data.error || 'Erro desconhecido'}`);
            }
        } catch (e) {
            erros++;
            errosDetalhes.push(`Embarque #${id}: ${e.message}`);
        }

        // Atualizar progresso
        const progresso = Math.round(((i + 1) / listaIds.length) * 100);
        atualizarSpinner(
            'Iniciando embarques...',
            `${sucessos} iniciados, ${erros} falhas`,
            progresso
        );
    }

    setTimeout(() => fecharSpinner(), 300);

    if (erros === 0) {
        Swal.fire({
            icon: 'success',
            title: '✅ Todos iniciados!',
            text: `${sucessos} embarque${sucessos > 1 ? 's' : ''} iniciado${sucessos > 1 ? 's' : ''} com sucesso.`,
            timer: 3000,
            showConfirmButton: false
        });
    } else if (sucessos > 0) {
        Swal.fire({
            icon: 'warning',
            title: '⚠️ Início parcial',
            html: `
                <div style="text-align: left;">
                    <p>✅ <strong>${sucessos}</strong> embarque${sucessos > 1 ? 's' : ''} iniciado${sucessos > 1 ? 's' : ''}</p>
                    <p>❌ <strong>${erros}</strong> embarque${erros > 1 ? 's' : ''} com erro</p>
                    ${errosDetalhes.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${errosDetalhes.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#f59e0b'
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: '❌ Falha ao iniciar',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi iniciado.</p>
                    ${errosDetalhes.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${errosDetalhes.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626'
        });
    }

    carregarEmbarques();
}

// ======================================================================
// FINALIZAR GRUPO (COM SPINNER MELHORADO)
// ======================================================================
async function finalizarGrupo(ids) {
    let listaIds = [];
    if (Array.isArray(ids)) {
        listaIds = ids;
    } else if (typeof ids === 'string') {
        listaIds = ids.split(',').map(Number);
    } else if (typeof ids === 'number') {
        listaIds = [ids];
    }
    listaIds = listaIds.filter(id => id && !isNaN(id));

    if (listaIds.length === 0) {
        mostrarNotificacao('Nenhum ID válido', 'error');
        return;
    }

    const token = getAuthToken();
    if (!token) return;

    try {
        let todasEntregas = [];
        for (const id of listaIds) {
            const resp = await fetch(`/v1/frota/embarques/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await resp.json();
            if (data.success && data.data.entregas) {
                todasEntregas = todasEntregas.concat(data.data.entregas);
            }
        }

        const pendentes = todasEntregas.filter(e =>
            e.status !== 'entregue' &&
            e.status !== 'falha' &&
            e.status !== 'entregue_com_problema' &&
            e.status !== 'cancelada'
        );

        if (pendentes.length > 0) {
            Swal.fire({
                title: 'Atenção',
                html: `
                    <div style="text-align: left;">
                        <p>Existem <strong>${pendentes.length} entregas pendentes</strong> neste grupo:</p>
                        <ul style="margin: 8px 0; padding-left: 20px;">
                            ${pendentes.map(e => `<li>${e.cliente_nome || 'Cliente'} - ${e.status || 'PENDENTE'}</li>`).join('')}
                        </ul>
                        <p>Finalize todas as entregas antes de concluir o embarque.</p>
                    </div>
                `,
                icon: 'warning',
                confirmButtonText: 'OK',
                confirmButtonColor: '#f59e0b'
            });
            return;
        }

        const finalizadas = todasEntregas.filter(e =>
            e.status === 'entregue' ||
            e.status === 'falha' ||
            e.status === 'entregue_com_problema' ||
            e.status === 'cancelada'
        );

        if (finalizadas.length === 0) {
            Swal.fire({
                title: 'Atenção',
                text: 'Nenhuma entrega foi finalizada. Finalize pelo menos uma entrega antes de concluir o embarque.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        const result = await Swal.fire({
            title: 'Finalizar todos os embarques?',
            html: `
                <div style="text-align: left;">
                    <p><strong>${listaIds.length}</strong> embarque${listaIds.length > 1 ? 's' : ''} serão finalizados.</p>
                    <p style="font-size: 0.85rem; color: #64748b; margin-top: 8px;">
                        📦 ${todasEntregas.length} entregas no total<br>
                        ✅ ${finalizadas.length} entregas concluídas
                        ${pendentes.length > 0 ? `<br>⚠️ ${pendentes.length} entregas pendentes` : ''}
                    </p>
                    ${pendentes.length > 0 ? '<p style="color: #dc2626; font-weight: 600; margin-top: 8px;">⚠️ Atenção: há entregas pendentes!</p>' : ''}
                </div>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Sim, finalizar todos',
            cancelButtonText: 'Cancelar'
        });

        if (!result.isConfirmed) return;

        // 🔥 SPINNER MELHORADO
        mostrarSpinner(
            'Finalizando embarques...',
            `Processando ${listaIds.length} embarques...`,
            0
        );

        let sucessos = 0;
        let erros = 0;
        let errosDetalhes = [];

        for (let i = 0; i < listaIds.length; i++) {
            const id = listaIds[i];
            try {
                const response = await fetch('/v1/frota/embarques/' + id + '/finalizar', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token }
                });

                const data = await response.json();

                if (data.success) {
                    sucessos++;
                } else {
                    erros++;
                    errosDetalhes.push(`Embarque #${id}: ${data.error || 'Erro desconhecido'}`);
                }
            } catch (e) {
                erros++;
                errosDetalhes.push(`Embarque #${id}: ${e.message}`);
            }

            // Atualizar progresso
            const progresso = Math.round(((i + 1) / listaIds.length) * 100);
            atualizarSpinner(
                'Finalizando embarques...',
                `${sucessos} concluídos, ${erros} falhas`,
                progresso
            );
        }

        setTimeout(() => fecharSpinner(), 300);

        if (erros === 0) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                html: `
                    <div style="text-align: left;">
                        <p>✅ <strong>${sucessos}</strong> embarque${sucessos > 1 ? 's' : ''} finalizado${sucessos > 1 ? 's' : ''} com sucesso!</p>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 8px;">
                            📦 ${todasEntregas.length} entregas concluídas
                        </p>
                    </div>
                `,
                timer: 3000,
                showConfirmButton: false
            });
        } else if (sucessos > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Finalização parcial',
                html: `
                    <div style="text-align: left;">
                        <p>✅ <strong>${sucessos}</strong> embarque${sucessos > 1 ? 's' : ''} finalizado${sucessos > 1 ? 's' : ''}</p>
                        <p>❌ <strong>${erros}</strong> embarque${erros > 1 ? 's' : ''} com erro</p>
                        ${errosDetalhes.length > 0 ? `
                            <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                                ${errosDetalhes.join('<br>')}
                            </div>
                        ` : ''}
                    </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#f59e0b'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro!',
                html: `
                    <div style="text-align: left;">
                        <p>❌ Nenhum embarque foi finalizado.</p>
                        ${errosDetalhes.length > 0 ? `
                            <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                                ${errosDetalhes.join('<br>')}
                            </div>
                        ` : ''}
                    </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626'
            });
        }

        carregarEmbarques();

    } catch (error) {
        fecharSpinner();
        Swal.fire('Erro', 'Falha ao finalizar embarques: ' + error.message, 'error');
    }
}

// ======================================================================
// REGISTRAR CHECKIN
// ======================================================================
async function registrarCheckin(entregaId) {
    const result = await Swal.fire({
        title: 'Check-in',
        text: 'Confirmar chegada ao cliente?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, cheguei',
        cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return;

    const token = getAuthToken();
    try {
        const lat = DISTRIBUIDORA_LAT;
        const lng = DISTRIBUIDORA_LNG;
        const response = await fetch(`/v1/frota/entregas/${entregaId}/checkin`, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify({
                latitude: lat,
                longitude: lng,
                desktop: true
            })
        });
        const data = await response.json();
        if (data.success) {
            mostrarNotificacao('Check-in registrado!', 'success');
            verDetalhes(embarqueIdDetalhes);
        } else {
            mostrarNotificacao(data.error || 'Erro no check-in', 'error');
        }
    } catch (error) {
        mostrarNotificacao('Erro ao registrar check-in', 'error');
    }
}

// ======================================================================
// REGISTRAR CHECKOUT
// ======================================================================
async function registrarCheckout(entregaId) {
    const token = getAuthToken();
    let entrega = null;
    let itens = [];

    try {
        const resp = await fetch(`/v1/frota/entregas/${entregaId}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        if (data.success) {
            entrega = data.data;
        } else {
            mostrarNotificacao('Erro ao carregar dados da entrega', 'error');
            return;
        }
    } catch (e) {
        mostrarNotificacao('Erro ao carregar dados da entrega', 'error');
        return;
    }

    if (!entrega) return;

    if (entrega.pedidos_ids) {
        const ids = entrega.pedidos_ids.split(',').map(id => parseInt(id.trim())).filter(id => id > 0);
        if (ids.length > 0) {
            try {
                const resp = await fetch('/v1/frota/importar/itens-pedidos', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pedidos_ids: ids })
                });
                const data = await resp.json();
                if (data.success && data.data) {
                    const itensMap = {};
                    data.data.forEach(pedido => {
                        if (pedido.itens) {
                            pedido.itens.forEach(item => {
                                const key = item.iditem;
                                if (!itensMap[key]) {
                                    itensMap[key] = {
                                        id: key,
                                        referencia: item.referencia || '-',
                                        descricao: item.descricao || 'Sem descrição',
                                        quantidade_total: parseFloat(item.quantidade_total) || 0,
                                        quantidade_entregue: 0,
                                        foto_item: null
                                    };
                                } else {
                                    itensMap[key].quantidade_total += parseFloat(item.quantidade_total) || 0;
                                }
                            });
                        }
                    });
                    itens = Object.values(itensMap);
                }
            } catch (e) {}
        }
    }

    const clienteNome = entrega.cliente_nome || 'Cliente';
    const endereco = entrega.endereco || '';
    const numero = entrega.numero || '';
    const cidade = entrega.cidade || '';
    const uf = entrega.uf || '';
    const codigoRastreamento = entrega.codigo_rastreamento || '';
    const pedidosIds = entrega.pedidos_ids || '';

    let htmlItens = '';
    if (itens.length === 0) {
        htmlItens = `
            <div class="alert alert-info mt-3" style="background: #e0f2fe; border: 1px solid #7dd3fc; border-radius: 12px; padding: 16px;">
                <i class="fa-solid fa-info-circle"></i> Esta entrega não possui itens para checklist. 
                Você pode concluí-la apenas com foto do romaneio e nome do recebedor.
            </div>
        `;
    } else {
        htmlItens = `
            <div style="max-height: 400px; overflow-y: auto; padding-right: 8px;">
            ${itens.map((item, idx) => `
                    <div class="card-item" style="background: #f9fafb; border-radius: 12px; padding: 12px 16px; margin-bottom: 12px; border: 1px solid #e5e7eb;">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                            <div style="flex: 2; min-width: 150px;">
                                <div style="font-weight: 600; font-size: 0.95rem; color: #1a3c34;">${item.referencia}</div>
                                <div style="font-size: 0.8rem; color: #64748b;">${item.descricao}</div>
                                <div style="font-size: 0.75rem; color: #64748b; margin-top: 4px;">Total: <strong>${item.quantidade_total}</strong> un</div>
                            </div>
                            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px; flex: 3;">
                                <div style="display: flex; align-items: center; gap: 4px;">
                                    <span style="font-size: 0.7rem; color: #64748b;">Entregue</span>
                                    <input type="number" class="qtd-entregue" data-idx="${idx}" value="${item.quantidade_total}" 
                                           min="0" max="${item.quantidade_total}" step="1"
                                           style="width: 60px; padding: 4px 6px; border-radius: 6px; border: 1px solid #d1d5db; text-align: center; font-size: 0.85rem;">
                                </div>
                                <select class="item-status" data-idx="${idx}" style="padding: 4px 8px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 0.8rem; background: white;">
                                    <option value="entregue">✅ Entregue</option>
                                    <option value="faltante">⚠️ Faltante</option>
                                    <option value="devolvido">🔄 Devolvido</option>
                                </select>
                                <input type="text" class="item-motivo" data-idx="${idx}" placeholder="Motivo" disabled style="flex: 1; min-width: 100px; padding: 4px 8px; border-radius: 6px; border: 1px solid #d1d5db; font-size: 0.8rem;">
                                <button class="btn-foto-item" data-idx="${idx}" style="background: #10b981; color: white; border: none; border-radius: 8px; padding: 6px 10px; cursor: pointer; font-size: 0.8rem; display: flex; align-items: center; gap: 4px; white-space: nowrap;">
                                    <i class="fa-solid fa-camera"></i> Foto
                                </button>
                                <span class="foto-status" data-idx="${idx}" style="font-size: 0.7rem; color: #10b981; display: none;">✓</span>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
            <div class="mt-2 text-muted small" style="font-size: 0.75rem; color: #64748b;">
                <i class="fa-solid fa-camera"></i> Clique no ícone da câmera para tirar foto do item descarregado.
            </div>
        `;
    }

    const { value: formData } = await Swal.fire({
        title: '<span style="font-size: 1.3rem;">📦 Finalizar Entrega</span>',
        html: `
            <div style="text-align: left; max-width: 100%; font-family: 'Inter', sans-serif;">
                <div style="background: linear-gradient(135deg, #1a3c34 0%, #2d5a4e 100%); color: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px;">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700;">${clienteNome}</div>
                            <div style="font-size: 0.85rem; opacity: 0.9;">${endereco} ${numero} - ${cidade}/${uf}</div>
                        </div>
                        <div style="text-align: right;">
            ${codigoRastreamento ? `<div style="font-size: 0.75rem; opacity: 0.8;">🔍 ${codigoRastreamento}</div>` : ''}
            ${pedidosIds ? `<div style="font-size: 0.7rem; opacity: 0.7;">Pedidos: ${pedidosIds}</div>` : ''}
                        </div>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
                    <div style="flex: 1; min-width: 180px;">
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 4px;">📷 Romaneio *</label>
                        <input type="file" id="foto-romaneio" accept="image/*" capture="environment" style="width: 100%; padding: 6px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 0.8rem;">
                        <small style="color: #64748b; font-size: 0.7rem;">Canhoto assinado</small>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label style="font-weight: 600; font-size: 0.85rem; display: block; margin-bottom: 4px;">👤 Recebedor *</label>
                        <input type="text" id="nome-recebedor" placeholder="Nome completo" style="width: 100%; padding: 8px 12px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 0.9rem;">
                        <small style="color: #64748b; font-size: 0.7rem;">Quem recebeu</small>
                    </div>
                </div>

                <hr style="border: 0; border-top: 2px solid #e5e7eb; margin: 8px 0 16px 0;">

                <div>
                    <p style="font-weight: 700; font-size: 0.95rem; margin-bottom: 8px;">📋 Itens do Pedido</p>
                    ${htmlItens}
                </div>
            </div>
            `,
            width: '1000px',
            showCancelButton: true,
            confirmButtonText: '✅ Concluir Entrega',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#dc2626',
            didOpen: (modal) => {
                const statusSelects = modal.querySelectorAll('.item-status');
                const motivosInputs = modal.querySelectorAll('.item-motivo');

                statusSelects.forEach((sel, idx) => {
                    sel.addEventListener('change', () => {
                        const motivo = motivosInputs[idx];
                        if (sel.value === 'entregue') {
                            motivo.disabled = true;
                            motivo.value = '';
                            motivo.placeholder = 'Não se aplica';
                        } else {
                            motivo.disabled = false;
                            motivo.placeholder = 'Ex: avaria, troca...';
                        }
                    });
                    sel.dispatchEvent(new Event('change'));
                });

                const btnFotos = modal.querySelectorAll('.btn-foto-item');

                btnFotos.forEach((btn, idx) => {
                    const previewContainer = document.createElement('div');
                    previewContainer.style.cssText = `
                    width: 70px;
                    height: 70px;
                    border: 2px dashed #d1d5db;
                    border-radius: 8px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    overflow: hidden;
                    background: #f9fafb;
                    position: relative;
                    margin-bottom: 4px;
                    flex-shrink: 0;
                    `;
                    previewContainer.id = `preview-container-${idx}`;

                    const previewLabel = document.createElement('span');
                    previewLabel.style.cssText = `
                    color: #9ca3af;
                    font-size: 0.55rem;
                    text-align: center;
                    `;
                    previewLabel.id = `preview-label-${idx}`;
                    previewLabel.innerHTML = `<i class="fa-solid fa-camera" style="display:block;font-size:1.2rem;"></i> Foto`;

                    const previewImg = document.createElement('img');
                    previewImg.style.cssText = `
                    display: none;
                    width: 100%;
                    height: 100%;
                    object-fit: cover;
                    `;
                    previewImg.id = `preview-img-${idx}`;

                    previewContainer.appendChild(previewLabel);
                    previewContainer.appendChild(previewImg);

                    const parent = btn.parentElement;
                    const container = document.createElement('div');
                    container.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:4px;';
                    container.appendChild(previewContainer);

                    const newBtn = document.createElement('button');
                    newBtn.style.cssText = `
                    background: #3b82f6;
                    color: white;
                    border: none;
                    border-radius: 6px;
                    padding: 4px 12px;
                    cursor: pointer;
                    font-size: 0.7rem;
                    transition: background 0.2s;
                    width: 100%;
                    `;
                    newBtn.innerHTML = '<i class="fa-solid fa-camera"></i> Foto';
                    newBtn.onmouseover = () => newBtn.style.background = '#2563eb';
                    newBtn.onmouseout = () => newBtn.style.background = '#3b82f6';

                    newBtn.onclick = (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.accept = 'image/*';
                        input.capture = 'environment';
                        input.style.display = 'none';
                        document.body.appendChild(input);

                        input.addEventListener('change', (ev) => {
                            if (input.files && input.files[0]) {
                                const reader = new FileReader();
                                reader.onload = (event) => {
                                    const base64 = event.target.result;
                                    itens[idx].foto_item = base64;

                                    const previewImgEl = document.getElementById(`preview-img-${idx}`);
                                    const previewLabelEl = document.getElementById(`preview-label-${idx}`);
                                    if (previewImgEl && previewLabelEl) {
                                        previewImgEl.src = base64;
                                        previewImgEl.style.display = 'block';
                                        previewLabelEl.style.display = 'none';
                                    }

                                    newBtn.innerHTML = '<i class="fa-solid fa-check"></i> OK';
                                    newBtn.style.background = '#10b981';
                                    mostrarNotificacao('📸 Foto do item capturada!', 'success');
                                };
                                reader.readAsDataURL(input.files[0]);
                            }
                            input.remove();
                        });
                        input.click();
                    };

                    container.appendChild(newBtn);
                    parent.replaceChild(container, btn);
                });
},
preConfirm: () => {
    const fotoRomaneio = document.getElementById('foto-romaneio');
    const nomeRecebedor = document.getElementById('nome-recebedor').value.trim();

    if (!fotoRomaneio.files || fotoRomaneio.files.length === 0) {
        Swal.showValidationMessage('A foto do romaneio assinado é obrigatória.');
        return false;
    }
    if (!nomeRecebedor) {
        Swal.showValidationMessage('O nome do recebedor é obrigatório.');
        return false;
    }

    let checklist = [];
    let temFaltante = false;
    let temDevolucao = false;

    if (itens.length > 0) {
        const statusSelects = document.querySelectorAll('.item-status');
        const motivosInputs = document.querySelectorAll('.item-motivo');
        const qtdEntregues = document.querySelectorAll('.qtd-entregue');

        for (let i = 0; i < statusSelects.length; i++) {
            const status = statusSelects[i].value;
            const motivo = motivosInputs[i].value.trim();
            const qtdEntregue = parseFloat(qtdEntregues[i].value) || 0;
            const qtdTotal = itens[i].quantidade_total;

            if (qtdEntregue > qtdTotal) {
                Swal.showValidationMessage(`Quantidade entregue do item "${itens[i].referencia}" não pode ser maior que ${qtdTotal}.`);
                return false;
            }

            if (qtdEntregue < qtdTotal) {
                if (status === 'entregue') {
                    Swal.showValidationMessage(`Item "${itens[i].referencia}" tem quantidade entregue menor que total. Selecione "Faltante" ou "Devolvido".`);
                    return false;
                }
                if (!motivo) {
                    Swal.showValidationMessage(`Motivo é obrigatório para o item "${itens[i].referencia}" (faltante ou devolvido).`);
                    return false;
                }
                if (status === 'faltante') temFaltante = true;
                if (status === 'devolvido') temDevolucao = true;
            } else {
                if (status !== 'entregue') {
                    Swal.showValidationMessage(`Item "${itens[i].referencia}" com quantidade total entregue deve ter status "Entregue".`);
                    return false;
                }
            }

            checklist.push({
                item_id: itens[i].id,
                referencia: itens[i].referencia,
                descricao: itens[i].descricao || '—',
                quantidade_prevista: qtdTotal,
                quantidade_entregue: qtdEntregue,
                status: status,
                motivo: motivo || null,
                foto_item: itens[i].foto_item || null
            });
        }
    }

    return new Promise((resolve) => {
        const readerRomaneio = new FileReader();
        readerRomaneio.onload = (e) => {
            resolve({
                foto_romaneio: e.target.result,
                nome_recebedor: nomeRecebedor,
                checklist: checklist,
                tem_faltante: temFaltante,
                tem_devolucao: temDevolucao
            });
        };
        readerRomaneio.readAsDataURL(fotoRomaneio.files[0]);
    });
}
});

if (!formData) return;

const { foto_romaneio, nome_recebedor, checklist, tem_faltante, tem_devolucao } = formData;

try {
    const response = await fetch(`/v1/frota/entregas/${entregaId}/checkout`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
        body: JSON.stringify({
            desktop: true,
            latitude: 0,
            longitude: 0,
            nome_recebedor: nome_recebedor,
            foto_romaneio: foto_romaneio,
            checklist: checklist,
            tem_faltante: tem_faltante,
            tem_devolucao: tem_devolucao
        })
    });
    const data = await response.json();
    if (data.success) {
        mostrarNotificacao(data.message || 'Entrega concluída!', 'success');
        if (data.embarque_status === 'problema') {
            mostrarNotificacao('⚠️ Atenção: há itens faltantes ou devoluções. Embarque marcado como problema.', 'warning');
        }
        verDetalhes(embarqueIdDetalhes);
    } else {
        mostrarNotificacao(data.error || 'Erro no checkout', 'error');
    }
} catch (error) {
    mostrarNotificacao('Erro ao registrar checkout', 'error');
}
}

// ======================================================================
// REGISTRAR FALHA
// ======================================================================
async function registrarFalha(entregaId) {
    const { value: motivo } = await Swal.fire({
        title: 'Motivo da falha',
        input: 'select',
        inputOptions: {
            'cliente_ausente': 'Cliente ausente',
            'endereco_incorreto': 'Endereço incorreto',
            'recusa': 'Recusa de recebimento',
            'outro': 'Outro'
        },
        showCancelButton: true,
        confirmButtonText: 'Registrar falha',
        cancelButtonText: 'Cancelar'
    });
    if (!motivo) return;

    const token = getAuthToken();
    try {
        const response = await fetch(`/v1/frota/entregas/${entregaId}/falha`, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify({ motivo, observacao: 'Registrado pelo gestor' })
        });
        const data = await response.json();
        if (data.success) {
            mostrarNotificacao('Falha registrada!', 'warning');
            verDetalhes(embarqueIdDetalhes);
        } else {
            mostrarNotificacao(data.error || 'Erro ao registrar falha', 'error');
        }
    } catch (error) {
        mostrarNotificacao('Erro ao registrar falha', 'error');
    }
}

// ======================================================================
// AÇÕES DO EMBARQUE
// ======================================================================
async function iniciarEmbarque(id) {
    const result = await Swal.fire({
        title: 'Iniciar Embarque?',
        text: 'O veículo será marcado como "Em Rota" e as entregas serão liberadas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sim, iniciar',
        cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return;

    const token = getAuthToken();
    if (!token) return;

    try {
        const response = await fetch('/v1/frota/embarques/' + id + '/iniciar', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                mostrarNotificacao('Embarque iniciado com sucesso!', 'success');
                carregarEmbarques();
            }
        }
    } catch (error) {
        mostrarNotificacao('Erro ao iniciar embarque', 'error');
    }
}

async function finalizarEmbarque(id) {
    const result = await Swal.fire({
        title: 'Finalizar Embarque?',
        text: 'Todas as entregas devem estar concluídas.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sim, finalizar',
        cancelButtonText: 'Cancelar'
    });
    if (!result.isConfirmed) return;

    const token = getAuthToken();
    if (!token) return;

    try {
        const response = await fetch('/v1/frota/embarques/' + id + '/finalizar', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                mostrarNotificacao('Embarque finalizado com sucesso!', 'success');
                carregarEmbarques();
            }
        }
    } catch (error) {
        mostrarNotificacao('Erro ao finalizar embarque', 'error');
    }
}

async function cancelarEmbarque(id) {
    const result = await Swal.fire({
        title: 'Cancelar Embarque?',
        text: 'Esta ação não pode ser desfeita.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar'
    });
    if (!result.isConfirmed) return;

    const token = getAuthToken();
    if (!token) return;

    try {
        const response = await fetch('/v1/frota/embarques/' + id + '/cancelar', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                mostrarNotificacao('Embarque cancelado com sucesso!', 'warning');
                carregarEmbarques();
            }
        }
    } catch (error) {
        mostrarNotificacao('Erro ao cancelar embarque', 'error');
    }
}
// ======================================================================
// VER DETALHES DO EMBARQUE
// ======================================================================
async function verDetalhes(id) {
    embarqueIdDetalhes = id;
    const token = getAuthToken();
    if (!token) return;

    try {
        const response = await fetch('/v1/frota/embarques/' + id, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const dados = await response.json();
        if (!dados.success) throw new Error(dados.error || 'Erro desconhecido');

        const emb = dados.data;
        entregasAtuais = emb.entregas || [];

        // ================================================================
        // PROCESSAR DADOS DAS ENTREGAS
        // ================================================================
        entregasAtuais = entregasAtuais.map(function(e, index) {
            e.id = e.id || index + 1;
            e.cliente_nome = e.cliente_nome || 'Cliente';
            e.endereco = e.endereco || '';
            e.numero = e.numero || '';
            e.bairro = e.bairro || '';
            e.cidade = e.cidade || '';
            e.uf = e.uf || '';
            e.pedido_id = e.pedido_id || '';
            e.valor_total = e.valor_total || 0;
            e.peso_total = e.peso_total || 0;
            e.status = e.status || 'PENDENTE';
            e.origem_geolocalizacao = e.origem_geolocalizacao || '';
            e.total_pedidos_agrupados = e.total_pedidos_agrupados || 1;
            e.pedidos_ids = e.pedidos_ids || '';
            e.erp_embarques_ids = e.erp_embarques_ids || '';
            e.foto_romaneio_url = e.foto_romaneio_url || null;
            e.foto_item_url = e.foto_item_url || null;

            e.distancia_distribuidora = (e.latitude && e.longitude) ?
                calcularDistancia(DISTRIBUIDORA_LAT, DISTRIBUIDORA_LNG, e.latitude, e.longitude) :
                null;
            e.ordem_original = index + 1;
            return e;
        });

        entregasAtuais.sort(function(a, b) {
            if (a.distancia_distribuidora === null) return 1;
            if (b.distancia_distribuidora === null) return -1;
            return a.distancia_distribuidora - b.distancia_distribuidora;
        });

        entregasAtuais.forEach(function(e, i) { e.ordem_entrega = i + 1; });

        document.getElementById('detalhes-numero').textContent = '#' + (emb.numero_embarque || emb.id);

        // ================================================================
        // CALCULAR STATS
        // ================================================================
        const statusIcon = {
            'planejado': '📋',
            'em_andamento': '🚚',
            'finalizado': '✅',
            'cancelado': '🚫',
            'problema': '⚠️'
        } [emb.status] || '';

        const statusClass = {
            'planejado': 'planejado',
            'em_andamento': 'em_andamento',
            'finalizado': 'finalizado',
            'cancelado': 'cancelado',
            'problema': 'problema'
        } [emb.status] || 'planejado';

        const statusText = {
            'planejado': 'Planejado',
            'em_andamento': 'Em Andamento',
            'finalizado': 'Finalizado',
            'cancelado': 'Cancelado',
            'problema': 'Problema'
        } [emb.status] || emb.status;

        const totalEntregas = parseInt(emb.total_entregas) || 0;
        const entregasConcluidas = parseInt(emb.entregas_concluidas) || 0;
        const progresso = totalEntregas > 0 ? Math.round((entregasConcluidas / totalEntregas) * 100) : 0;

        let distanciaTotal = 0;
        let ultimoPonto = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };
        entregasAtuais.forEach(function(e) {
            if (e.latitude && e.longitude) {
                const d = calcularDistancia(ultimoPonto.lat, ultimoPonto.lng, e.latitude, e.longitude);
                if (d !== null) { distanciaTotal += d; }
                ultimoPonto.lat = e.latitude;
                ultimoPonto.lng = e.longitude;
            }
        });

        // ================================================================
        // GERAR HTML DAS ENTREGAS
        // ================================================================
        let entregasHtml = '';
        if (entregasAtuais.length > 0) {
            entregasHtml = `
                <div class="mt-4">
                    <div class="flex justify-between items-center mb-2">
                        <h6 class="font-bold text-[#1a3c34] text-sm dark:text-white">
                            <i class="fa-solid fa-list mr-2"></i> Entregas (${entregasAtuais.length})
                            <span class="text-xs font-normal text-slate-400">(arraste para reordenar)</span>
                        </h6>
                        <span class="text-xs text-slate-500">
                            <i class="fa-solid fa-route mr-1"></i> Total: ${distanciaTotal.toFixed(2)} km
                        </span>
                    </div>
                    <div id="lista-entregas-container">
                        ${entregasAtuais.map(function(e, index) {
                            const statusClasse = e.status === 'entregue' ? 'entregue' : 
                                e.status === 'falha' ? 'falha' : 
                                e.status === 'em_entrega' ? 'em_entrega' : 'pendente';

                            const temCheckout = e.status === 'entregue' || e.status === 'entregue_com_problema' || e.status === 'falha';
                            const temFotos = e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url));

                            const checkoutBadge = temCheckout ? `<span class="checkout-badge" style="background: #d1fae5; color: #065f46; font-size: 0.6rem; padding: 2px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px; margin-left: 6px;">
                                <i class="fa-solid fa-check-double"></i> Checkout
                            </span>` : '';

                            const fotosBadge = temFotos ? `<span class="fotos-badge" style="background: #dbeafe; color: #1e40af; font-size: 0.6rem; padding: 2px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px; margin-left: 4px;">
                                <i class="fa-regular fa-images"></i> 📸
                            </span>` : '';

                            const recebedorInfo = e.nome_recebedor ? `<span style="font-size: 0.7rem; color: #065f46; background: #d1fae5; padding: 2px 10px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px; margin-left: 4px;">
                                <i class="fa-solid fa-user"></i> ${e.nome_recebedor}
                            </span>` : '';

                            let checklistInfo = '';
                            if (e.checklist && e.checklist.length > 0) {
                                const totalItens = e.checklist.length;
                                const entregues = e.checklist.filter(item => item.status === 'entregue').length;
                                const faltantes = e.checklist.filter(item => item.status === 'faltante').length;
                                const devolvidos = e.checklist.filter(item => item.status === 'devolvido').length;
                                const comFoto = e.checklist.filter(item => item.foto_url).length;

                                let statusText = '';
                                let statusColor = '';
                                let statusIcon = '';
                                if (faltantes > 0 || devolvidos > 0) {
                                    statusText = `${faltantes} faltante${faltantes > 1 ? 's' : ''}${devolvidos > 0 ? `, ${devolvidos} devolvido${devolvidos > 1 ? 's' : ''}` : ''}`;
                                    statusColor = '#dc2626';
                                    statusIcon = '⚠️';
                                } else {
                                    statusText = `${entregues}/${totalItens} itens`;
                                    statusColor = '#10b981';
                                    statusIcon = '✅';
                                }

                                checklistInfo = `
                                    <div style="font-size: 0.7rem; display: flex; align-items: center; gap: 8px; margin-top: 2px; flex-wrap: wrap;">
                                        <span style="display: flex; align-items: center; gap: 4px; background: ${statusColor}20; padding: 2px 10px; border-radius: 999px; color: ${statusColor};">
                                            <i class="fa-solid fa-clipboard-list"></i>
                                            ${statusIcon} ${statusText}
                                        </span>
                                        ${comFoto > 0 ? `<span style="display: flex; align-items: center; gap: 4px; background: #dbeafe; padding: 2px 10px; border-radius: 999px; color: #1e40af;">
                                            <i class="fa-regular fa-images"></i> ${comFoto} foto${comFoto > 1 ? 's' : ''}
                                        </span>` : ''}
                                        <button class="btn-ver-itens" data-id="${e.id}" 
                                                onclick="event.stopPropagation(); event.preventDefault(); verItensCheckout(${e.id})" 
                                                style="background: #3b82f6; color: white; border: none; border-radius: 999px; 
                                                       padding: 2px 14px; cursor: pointer; font-size: 0.65rem; font-weight: 600;
                                                       transition: background 0.2s;"
                                                onmouseover="this.style.background='#2563eb'"
                                                onmouseout="this.style.background='#3b82f6'">
                                            <i class="fa-solid fa-eye"></i> Ver Itens
                                        </button>
                                    </div>
                                `;
                            }

                            let geoBadge = '';
                            if (e.origem_geolocalizacao) {
                                const cls = e.origem_geolocalizacao === 'frota_cliente' ? 'frota_cliente' : 
                                    e.origem_geolocalizacao === 'google_maps' ? 'google_maps' : 'sem_geo';
                                const label = e.origem_geolocalizacao === 'frota_cliente' ? 'GPS' : 
                                    e.origem_geolocalizacao === 'google_maps' ? 'Maps' : e.origem_geolocalizacao;
                                geoBadge = '<span class="geo-badge ' + cls + '"><i class="fa-solid fa-location-dot"></i> ' + label + '</span>';
                            }

                            const distanciaStr = e.distancia_distribuidora !== null ? 
                                e.distancia_distribuidora.toFixed(1) + ' km' : '--';

                            const totalPedidos = parseInt(e.total_pedidos_agrupados) || 1;
                            const pedidosLabel = totalPedidos > 1 ? 
                                `<span class="pedido"><i class="fa-regular fa-file-lines"></i> ${totalPedidos} pedidos</span>` : 
                                (e.pedido_id ? `<span class="pedido"><i class="fa-regular fa-file-lines"></i> Pedido #${e.pedido_id}</span>` : '');

                            const pedidosIds = e.pedidos_ids || '';
                            const pedidosTooltip = pedidosIds ? ` title="Pedidos: ${pedidosIds}"` : '';

                            let erpBadge = '';
                            if (e.erp_embarques_ids) {
                                erpBadge = `<span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full"><i class="fa-solid fa-layer-group"></i> ${e.erp_embarques_ids}</span>`;
                            }

                            const btnCheckin = (e.status === 'pendente' || e.status === 'em_entrega') ? 
                                `<button class="btn-icone verde btn-checkin" data-id="${e.id}" onclick="event.stopPropagation(); registrarCheckin(${e.id})" title="Check-in"><i class="fa-solid fa-check"></i></button>` : '';

                            const btnCheckout = (e.status === 'pendente' || e.status === 'em_entrega') ? 
                                `<button class="btn-icone azul btn-checkout" data-id="${e.id}" onclick="event.stopPropagation(); registrarCheckout(${e.id})" title="Checkout"><i class="fa-solid fa-flag-checkered"></i></button>` : '';

                            const btnFalha = (e.status === 'pendente' || e.status === 'em_entrega') ? 
                                `<button class="btn-icone vermelho btn-falha" data-id="${e.id}" onclick="event.stopPropagation(); registrarFalha(${e.id})" title="Falha"><i class="fa-solid fa-times"></i></button>` : '';

                            const btnFotos = (e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url))) ? 
                                `<button class="btn-icone azul btn-fotos" data-id="${e.id}" onclick="event.stopPropagation(); abrirGaleriaFotos(${e.id})" title="Ver fotos">
                                    <i class="fa-regular fa-images"></i>
                                    ${temCheckout ? '<i class="fa-solid fa-circle-check" style="color: #10b981; font-size: 0.6rem; margin-left: -2px;"></i>' : ''}
                                </button>` : '';

                            const codigoRastreamento = e.codigo_rastreamento ? `<span class="text-xs text-slate-400 ml-1">🔍 ${e.codigo_rastreamento}</span>` : '';

                            return `
                                <div class="entrega-item ${entregaSelecionadaId === e.id ? 'ativa' : ''}" 
                                     data-id="${e.id}" data-lat="${e.latitude || ''}" data-lng="${e.longitude || ''}"
                                     onclick="selecionarEntregaNoMapa(${e.id})"${pedidosTooltip}>
                                    <div class="drag-handle"><i class="fa-solid fa-grip-lines"></i></div>
                                    <span class="ordem">${index + 1}</span>
                                    <div class="info">
                                        <div class="cliente">
                                            ${e.cliente_nome || 'Cliente'} 
                                            ${codigoRastreamento}
                                            ${checkoutBadge}
                                            ${fotosBadge}
                                            ${recebedorInfo}
                                        </div>
                                        <div class="endereco">${e.endereco || ''}${e.numero ? ', ' + e.numero : ''}${e.bairro ? ', ' + e.bairro : ''}${e.cidade ? ', ' + e.cidade : ''}${e.uf ? ', ' + e.uf : ''}</div>
                                        ${checklistInfo}
                                        <div class="detalhes">
                                            ${pedidosLabel}
                                            ${e.valor_total ? `<span class="valor"><i class="fa-regular fa-money-bill-1"></i> ${formatarMoeda(e.valor_total)}</span>` : ''}
                                            ${e.peso_total ? `<span class="peso"><i class="fa-regular fa-weight-scale"></i> ${formatarPeso(e.peso_total)}</span>` : ''}
                                            ${geoBadge}
                                            ${erpBadge}
                                        </div>
                                    </div>
                                    <div class="distancia"><i class="fa-solid fa-location-arrow"></i> ${distanciaStr}</div>
                                    <span class="status-mini ${statusClasse}">${e.status || 'PENDENTE'}</span>
                                    <button class="btn-mapa" onclick="event.stopPropagation(); centralizarNoMapa(${e.id})" title="Centralizar no mapa">
                                        <i class="fa-solid fa-crosshairs"></i>
                                    </button>
                                    ${btnCheckin}
                                    ${btnCheckout}
                                    ${btnFalha}
                                    ${btnFotos}
                                </div>
                            `;
                        }).join('')}
                    </div>
                    <div class="resumo-rota mt-3">
                        <div class="item"><i class="fa-solid fa-flag-checkered"></i> <span><span class="numero">${entregasAtuais.length}</span> entregas</span></div>
                        <div class="item"><i class="fa-solid fa-route"></i> <span>Distância total: <span class="distancia-total">${distanciaTotal.toFixed(2)} km</span></span></div>
                        <div class="item"><i class="fa-solid fa-clock"></i> <span>Previsto: ${distanciaTotal > 0 ? Math.round(distanciaTotal / 40 * 60) : 0} min</span></div>
                        ${emb.total_embarques_agrupados > 1 ? `
                            <div class="item"><i class="fa-solid fa-layer-group"></i> <span><span class="numero">${emb.total_embarques_agrupados}</span> embarques agrupados</span></div>
                        ` : ''}
                    </div>
                </div>
            `;
        } else {
            entregasHtml = `<div class="text-center text-slate-400 py-4"><i class="fa-regular fa-box text-2xl block mb-1"></i>Nenhuma entrega vinculada</div>`;
        }

        // ================================================================
        // EXIBIR EMBARQUES AGRUPADOS
        // ================================================================
        let embarquesAgrupadosHtml = '';
        if (emb.erp_ids_agrupados) {
            const ids = emb.erp_ids_agrupados.split(',');
            embarquesAgrupadosHtml = `
                <div class="mt-2 p-3 bg-purple-50 rounded-lg border border-purple-200 dark:bg-purple-900/20 dark:border-purple-800">
                    <p class="text-xs text-purple-700 font-bold uppercase flex items-center gap-2 dark:text-purple-300">
                        <i class="fa-solid fa-layer-group"></i> Embarques Agrupados (${ids.length})
                    </p>
                    <div class="flex flex-wrap gap-1 mt-1">
                        ${ids.map(id => `<span class="text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full dark:bg-purple-900 dark:text-purple-300">#${id}</span>`).join('')}
                    </div>
                </div>
            `;
        }

        // ================================================================
        // 🔥 HISTÓRICO DE AÇÕES - VERSÃO MELHORADA
        // ================================================================
        let historicoHtml = '';
        if (emb.historico && emb.historico.length > 0) {
            const acaoConfig = {
                // Ações do embarque
                'iniciar': { icone: 'fa-solid fa-play', cor: '#3b82f6', label: '🚀 Embarque iniciado', bg: '#dbeafe' },
                'finalizar': { icone: 'fa-solid fa-flag-checkered', cor: '#10b981', label: '🏁 Embarque finalizado', bg: '#d1fae5' },
                'cancelar': { icone: 'fa-solid fa-ban', cor: '#ef4444', label: '🚫 Embarque cancelado', bg: '#fee2e2' },
                
                // Ações de entrega
                'checkin': { icone: 'fa-solid fa-location-dot', cor: '#f59e0b', label: '📍 Check-in realizado', bg: '#fef3c7' },
                'checkout': { icone: 'fa-solid fa-check-double', cor: '#10b981', label: '✅ Checkout finalizado', bg: '#d1fae5' },
                'falha': { icone: 'fa-solid fa-times-circle', cor: '#ef4444', label: '❌ Falha na entrega', bg: '#fee2e2' },
                'reagendar': { icone: 'fa-solid fa-calendar-plus', cor: '#f59e0b', label: '📅 Entrega reagendada', bg: '#fef3c7' },
                'corrigir_endereco': { icone: 'fa-solid fa-map-pin', cor: '#3b82f6', label: '📍 Endereço corrigido', bg: '#dbeafe' },
                'remover_entrega': { icone: 'fa-solid fa-trash-alt', cor: '#ef4444', label: '🗑️ Entrega removida', bg: '#fee2e2' },

                // Ações de edição
                'editar': { icone: 'fa-solid fa-pen', cor: '#8b5cf6', label: '✏️ Embarque editado', bg: '#ede9fe' },
                
                // Ações de problema
                'problema': { icone: 'fa-solid fa-triangle-exclamation', cor: '#f59e0b', label: '⚠️ Problema identificado', bg: '#fef3c7' },
                'resolver_problema': { icone: 'fa-solid fa-check-circle', cor: '#10b981', label: '✅ Problema resolvido', bg: '#d1fae5' }
            };

            // Função para extrair detalhes adicionais
            function extrairDetalhesHistorico(descricao) {
                if (!descricao) return null;
                const clienteMatch = descricao.match(/cliente[:\s]+([^,]+)/i);
                const pedidoMatch = descricao.match(/pedido[#:\s]+(\d+)/i);
                const motivoMatch = descricao.match(/motivo[:\s]+([^.]+)/i);
                
                return {
                    cliente: clienteMatch ? clienteMatch[1].trim() : null,
                    pedido: pedidoMatch ? pedidoMatch[1] : null,
                    motivo: motivoMatch ? motivoMatch[1].trim() : null
                };
            }

            historicoHtml = `
                <div class="mt-4">
                    <div class="flex items-center justify-between mb-3">
                        <h6 class="font-bold text-[#1a3c34] text-sm flex items-center gap-2 dark:text-white">
                            <i class="fa-solid fa-clock-rotate-left"></i> Histórico de Ações
                            <span class="text-xs text-slate-400 font-normal">(${emb.historico.length} eventos)</span>
                        </h6>
                        <button onclick="expandirHistorico()" class="text-xs text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            <i class="fa-solid fa-expand"></i> Expandir
                        </button>
                    </div>
                    <div class="historico-timeline" style="max-height: 350px; overflow-y: auto; border: 1px solid var(--nutri-border); border-radius: 12px; padding: 12px; background: var(--nutri-card-bg);">
                        <div class="relative" style="padding-left: 28px;">
            `;

            emb.historico.forEach((log, index) => {
                const config = acaoConfig[log.acao] || { 
                    icone: 'fa-solid fa-circle', 
                    cor: '#94a3b8', 
                    label: log.acao || 'Ação',
                    bg: 'var(--nutri-border)'
                };
                
                const detalhes = extrairDetalhesHistorico(log.descricao);
                const isUltimo = index === emb.historico.length - 1;
                const isCheckout = log.acao === 'checkout';
                const dataHora = formatarDataHora(log.data_hora);

                historicoHtml += `
                    <div class="relative ${!isUltimo ? 'pb-5' : ''}" style="border-left: 2px solid ${isUltimo ? 'transparent' : 'var(--nutri-border)'}; padding-left: 20px; margin-left: -6px;">
                        <div style="position: absolute; left: -10px; top: 4px; width: 20px; height: 20px; border-radius: 50%; background: ${config.bg}; border: 2px solid ${config.cor}; display: flex; align-items: center; justify-content: center;">
                            <i class="${config.icone}" style="font-size: 10px; color: ${config.cor};"></i>
                        </div>
                        
                        <div style="background: ${isCheckout ? '#d1fae5' : 'transparent'}; border-radius: 8px; padding: ${isCheckout ? '8px 12px' : '4px 0'}; ${isCheckout ? 'border: 1px solid #6ee7b7;' : ''}">
                            <div class="flex flex-wrap items-center gap-2">
                                <span style="font-weight: ${isCheckout ? '700' : '600'}; color: ${isCheckout ? '#065f46' : 'var(--nutri-text)'};">
                                    ${config.label}
                                    ${isCheckout ? ' 📦' : ''}
                                </span>
                                <span class="text-xs text-slate-400">${dataHora}</span>
                                <span class="text-xs text-slate-500">por <strong>${log.usuario_nome || 'Sistema'}</strong></span>
                            </div>

                            ${log.descricao && log.descricao !== log.acao ? `
                                <div class="text-sm text-slate-600 dark:text-slate-300 mt-1">${log.descricao}</div>
                            ` : ''}
                            
                            ${detalhes ? `
                                <div class="flex flex-wrap gap-2 mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    ${detalhes.cliente ? `<span>👤 ${detalhes.cliente}</span>` : ''}
                                    ${detalhes.pedido ? `<span>📦 Pedido #${detalhes.pedido}</span>` : ''}
                                    ${detalhes.motivo ? `<span>⚠️ ${detalhes.motivo}</span>` : ''}
                                </div>
                            ` : ''}
                            
                            ${isCheckout ? `
                                <div class="flex flex-wrap gap-1 mt-1">
                                    <span class="text-xs bg-emerald-100 text-emerald-700 dark:bg-emerald-900 dark:text-emerald-300 px-2 py-0.5 rounded-full">✅ Entrega concluída</span>
                                    ${log.descricao && log.descricao.includes('faltante') ? '<span class="text-xs bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 px-2 py-0.5 rounded-full">⚠️ Itens faltantes</span>' : ''}
                                    ${log.descricao && log.descricao.includes('devolução') ? '<span class="text-xs bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 px-2 py-0.5 rounded-full">🔄 Devoluções</span>' : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `;
            });

            historicoHtml += `
                        </div>
                    </div>
                </div>
            `;
        }

        // ================================================================
        // MONTAR HTML COMPLETO DO MODAL
        // ================================================================
        const container = document.getElementById('detalhes-conteudo');
        container.innerHTML = `
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Número</p>
                            <p class="font-bold text-[#1a3c34] dark:text-white">${emb.numero_embarque || '#' + emb.id}</p>
                            ${emb.erp_embarque_id ? '<p class="text-xs text-blue-600 dark:text-blue-400">ERP: #' + emb.erp_embarque_id + '</p>' : ''}
                            ${embarquesAgrupadosHtml}
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Status</p>
                            <span class="status-badge ${statusClass}">
                                ${statusIcon} ${statusText}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Progresso</p>
                            <div class="flex items-center gap-2">
                                <div class="progress-thin flex-1">
                                    <div class="bar ${progresso >= 100 ? 'concluido' : 'em-andamento'}" style="width: ${progresso}%"></div>
                                </div>
                                <span class="text-sm font-bold">${progresso}%</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Veículo</p>
                            <p class="font-medium dark:text-white">${emb.veiculo_placa || '-'}</p>
                            <p class="text-sm text-slate-500">${emb.veiculo_modelo || ''}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Motorista</p>
                            <p class="font-medium dark:text-white">${emb.motorista_nome || '-'}</p>
                            ${emb.motorista_telefone ? `<p class="text-xs text-slate-400">${emb.motorista_telefone}</p>` : ''}
                        </div>
                    </div>
                </div>
                <div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Data Saída</p>
                            <p class="font-medium dark:text-white">${formatarDataHora(emb.data_saida)}</p>
                        </div>
                        <div>
                            <p class="text-xs text-slate-400 font-bold uppercase">Data Retorno</p>
                            <p class="font-medium dark:text-white">${formatarDataHora(emb.data_retorno)}</p>
                        </div>
                        ${emb.observacoes ? `
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase">Observações</p>
                                <p class="text-sm text-slate-600 dark:text-slate-300">${emb.observacoes}</p>
                            </div>
                        ` : ''}
                        ${emb.total_embarques_agrupados > 1 ? `
                            <div>
                                <p class="text-xs text-slate-400 font-bold uppercase">Total Agrupado</p>
                                <p class="text-sm font-medium dark:text-white">${emb.total_embarques_agrupados} embarques</p>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
            <hr class="my-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div>
                    ${entregasHtml}
                    ${historicoHtml}
                </div>
                <div>
                    <h6 class="font-bold text-[#1a3c34] text-sm mb-2 dark:text-white">
                        <i class="fa-solid fa-map mr-2"></i> Mapa da Rota
                        <span class="text-xs font-normal text-slate-400">(clique na entrega para ver detalhes)</span>
                    </h6>
                    <div id="mapa-rota"></div>
                </div>
            </div>
        `;

        // ================================================================
        // INICIALIZAR MAPA E SORTABLE
        // ================================================================
        setTimeout(function() { inicializarMapaRota(entregasAtuais); }, 300);
        setTimeout(function() { initSortable(); }, 500);

        // ================================================================
        // ABRIR MODAL
        // ================================================================
        if (typeof $ !== 'undefined' && $.fn.modal) {
            $('#modalDetalhes').modal('show');
        } else {
            const el = document.getElementById('modalDetalhes');
            el.style.display = 'block';
            el.classList.add('show');
            document.body.classList.add('modal-open');
            if (!document.querySelector('.modal-backdrop')) {
                const b = document.createElement('div');
                b.className = 'modal-backdrop fade show';
                document.body.appendChild(b);
            }
        }
    } catch (error) {
        mostrarNotificacao('Erro ao carregar detalhes do embarque: ' + error.message, 'error');
    }
}

// ======================================================================
// ABRIR GALERIA DE FOTOS
// ======================================================================
                                                async function abrirGaleriaFotos(entregaId) {
                                                    const token = getAuthToken();
                                                    try {
                                                        const resp = await fetch(`/v1/frota/entregas/${entregaId}`, {
                                                            headers: { 'Authorization': 'Bearer ' + token }
                                                        });
                                                        const data = await resp.json();
                                                        if (!data.success) {
                                                            mostrarNotificacao('Erro ao carregar fotos', 'error');
                                                            return;
                                                        }
                                                        const entrega = data.data;

                                                        let fotos = [];
                                                        if (entrega.foto_romaneio_url) {
                                                            fotos.push({ url: entrega.foto_romaneio_url, label: '📷 Romaneio' });
                                                        }
                                                        if (entrega.foto_item_url) {
                                                            fotos.push({ url: entrega.foto_item_url, label: '📦 Item' });
                                                        }
                                                        if (entrega.checklist && entrega.checklist.length > 0) {
                                                            entrega.checklist.forEach(item => {
                                                                if (item.foto_url) {
                                                                    fotos.push({ url: item.foto_url, label: `📦 ${item.referencia || 'Item'}` });
                                                                }
                                                            });
                                                        }

                                                        if (fotos.length === 0) {
                                                            Swal.fire('Atenção', 'Nenhuma foto disponível para esta entrega.', 'info');
                                                            return;
                                                        }

                                                        let html = `
            <div style="max-height: 550px; overflow-y: auto; padding: 8px; display: flex; flex-wrap: wrap; gap: 16px; justify-content: center;">
                                                        `;

                                                        fotos.forEach((foto, index) => {
                                                            html += `
                <div style="text-align: center; width: 200px; cursor: pointer;" onclick="abrirZoomFoto('${foto.url}', '${foto.label}')">
                    <img src="${foto.url}" 
                         style="width: 100%; height: 150px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s;" 
                         onmouseover="this.style.transform='scale(1.05)'" 
                         onmouseout="this.style.transform='scale(1)'"
                         onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'padding:20px;background:#f3f4f6;border-radius:8px;color:#9ca3af;\\'><i class=\\'fa-regular fa-image\\' style=\\'font-size:2rem;display:block;margin-bottom:8px;\\'></i>Imagem não disponível</div>'">
                    <div style="font-size: 0.8rem; margin-top: 4px; color: var(--nutri-text);">${foto.label}</div>
                    <div style="font-size: 0.65rem; color: var(--nutri-text-secondary);">Clique para ampliar</div>
                </div>
                                                            `;
                                                        });

                                                        html += `</div>`;

                                                        Swal.fire({
                                                            title: '📸 Fotos da Entrega',
                                                            html: html,
                                                            width: '800px',
                                                            showConfirmButton: true,
                                                            confirmButtonText: 'Fechar',
                                                            confirmButtonColor: '#10b981',
                                                            customClass: {
                                                                popup: 'galeria-fotos-modal'
                                                            }
                                                        });

                                                    } catch (error) {
                                                        mostrarNotificacao('Erro ao carregar fotos', 'error');
                                                    }
                                                }

// ======================================================================
// ABRIR FOTO COM ZOOM
// ======================================================================
                                                function abrirZoomFoto(url, label) {
                                                    const backdrop = document.createElement('div');
                                                    backdrop.id = 'zoom-backdrop';
                                                    backdrop.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.85);
        z-index: 99999;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        animation: fadeInZoom 0.3s ease;
                                                    `;

                                                    const container = document.createElement('div');
                                                    container.style.cssText = `
        position: relative;
        max-width: 90%;
        max-height: 90%;
        display: flex;
        flex-direction: column;
        align-items: center;
        animation: zoomIn 0.3s ease;
                                                    `;

                                                    const img = document.createElement('img');
                                                    img.src = url;
                                                    img.style.cssText = `
        max-width: 100%;
        max-height: 80vh;
        border-radius: 12px;
        box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        object-fit: contain;
        background: white;
        padding: 4px;
                                                    `;

                                                    img.onerror = function() {
                                                        this.style.display = 'none';
                                                        const errorMsg = document.createElement('div');
                                                        errorMsg.style.cssText = `
            color: white;
            font-size: 1.2rem;
            text-align: center;
            padding: 40px;
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            min-width: 200px;
                                                        `;
                                                        errorMsg.innerHTML = `
            <i class="fa-regular fa-image" style="font-size: 3rem; display: block; margin-bottom: 16px;"></i>
            ❌ Imagem não disponível
            <br><small style="font-size: 0.8rem; opacity: 0.7;">${label || 'Foto'}</small>
                                                        `;
                                                        container.insertBefore(errorMsg, container.firstChild);
                                                        caption.innerHTML = `
            <span style="color: #f87171;">❌ ${label || 'Foto'} - não carregou</span>
            <button onclick="event.stopPropagation(); fecharZoom()" 
                    style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 16px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;"
                    onmouseover="this.style.background='rgba(255,255,255,0.3)'" 
                    onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                ✕ Fechar
            </button>
                                                        `;
                                                    };

                                                    const caption = document.createElement('div');
                                                    caption.style.cssText = `
        color: white;
        font-size: 1rem;
        margin-top: 16px;
        font-weight: 500;
        text-shadow: 0 2px 8px rgba(0,0,0,0.5);
        display: flex;
        align-items: center;
        gap: 12px;
                                                    `;
                                                    caption.innerHTML = `
        <span>${label || 'Foto'}</span>
        <button onclick="event.stopPropagation(); fecharZoom()" 
                style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 16px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;"
                onmouseover="this.style.background='rgba(255,255,255,0.3)'" 
                onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            ✕ Fechar
        </button>
                                                    `;

                                                    const zoomControls = document.createElement('div');
                                                    zoomControls.style.cssText = `
        position: absolute;
        bottom: 80px;
        right: 20px;
        display: flex;
        flex-direction: column;
        gap: 8px;
                                                    `;

                                                    let currentZoom = 1;

                                                    const btnZoomIn = document.createElement('button');
                                                    btnZoomIn.innerHTML = '➕';
                                                    btnZoomIn.style.cssText = `
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        transition: background 0.2s;
        backdrop-filter: blur(4px);
                                                    `;
                                                    btnZoomIn.onmouseover = () => btnZoomIn.style.background = 'rgba(255,255,255,0.3)';
                                                    btnZoomIn.onmouseout = () => btnZoomIn.style.background = 'rgba(255,255,255,0.2)';
                                                    btnZoomIn.onclick = (e) => {
                                                        e.stopPropagation();
                                                        currentZoom = Math.min(currentZoom + 0.25, 3);
                                                        img.style.transform = `scale(${currentZoom})`;
                                                        img.style.transition = 'transform 0.2s ease';
                                                    };

                                                    const btnZoomOut = document.createElement('button');
                                                    btnZoomOut.innerHTML = '➖';
                                                    btnZoomOut.style.cssText = `
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        transition: background 0.2s;
        backdrop-filter: blur(4px);
                                                    `;
                                                    btnZoomOut.onmouseover = () => btnZoomOut.style.background = 'rgba(255,255,255,0.3)';
                                                    btnZoomOut.onmouseout = () => btnZoomOut.style.background = 'rgba(255,255,255,0.2)';
                                                    btnZoomOut.onclick = (e) => {
                                                        e.stopPropagation();
                                                        currentZoom = Math.max(currentZoom - 0.25, 0.5);
                                                        img.style.transform = `scale(${currentZoom})`;
                                                        img.style.transition = 'transform 0.2s ease';
                                                    };

                                                    const btnReset = document.createElement('button');
                                                    btnReset.innerHTML = '⟲';
                                                    btnReset.style.cssText = `
        background: rgba(255,255,255,0.2);
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 1.2rem;
        transition: background 0.2s;
        backdrop-filter: blur(4px);
                                                    `;
                                                    btnReset.onmouseover = () => btnReset.style.background = 'rgba(255,255,255,0.3)';
                                                    btnReset.onmouseout = () => btnReset.style.background = 'rgba(255,255,255,0.2)';
                                                    btnReset.onclick = (e) => {
                                                        e.stopPropagation();
                                                        currentZoom = 1;
                                                        img.style.transform = 'scale(1)';
                                                        img.style.transition = 'transform 0.2s ease';
                                                    };

                                                    zoomControls.appendChild(btnZoomIn);
                                                    zoomControls.appendChild(btnZoomOut);
                                                    zoomControls.appendChild(btnReset);

                                                    container.appendChild(img);
                                                    container.appendChild(caption);
                                                    container.appendChild(zoomControls);
                                                    backdrop.appendChild(container);
                                                    document.body.appendChild(backdrop);

                                                    backdrop.onclick = (e) => {
                                                        if (e.target === backdrop) {
                                                            fecharZoom();
                                                        }
                                                    };

                                                    const handleEsc = (e) => {
                                                        if (e.key === 'Escape') {
                                                            fecharZoom();
                                                        }
                                                    };
                                                    document.addEventListener('keydown', handleEsc);
                                                    backdrop._handleEsc = handleEsc;

                                                    document.body.style.overflow = 'hidden';
                                                }

// ======================================================================
// FECHAR ZOOM
// ======================================================================
                                                function fecharZoom() {
                                                    const backdrop = document.getElementById('zoom-backdrop');
                                                    if (backdrop) {
                                                        backdrop.style.animation = 'fadeOutZoom 0.2s ease';
                                                        setTimeout(() => {
                                                            backdrop.remove();
                                                            document.body.style.overflow = '';
                                                            if (backdrop._handleEsc) {
                                                                document.removeEventListener('keydown', backdrop._handleEsc);
                                                            }
                                                        }, 200);
                                                    }
                                                }

// ======================================================================
// MAPA
// ======================================================================
                                                function inicializarMapaRota(entregas) {
                                                    const container = document.getElementById('mapa-rota');
                                                    if (!container) return;
                                                    if (mapaRota) { mapaRota.remove();
                                                    mapaRota = null;
                                                    rotaMarkers = [];
                                                    rotaPolyline = null; }

                                                    const pontos = [{ lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG, tipo: 'distribuidora' }];
                                                    entregas.forEach(function(e) {
                                                        if (e.latitude && e.longitude) pontos.push({ lat: e.latitude, lng: e.longitude, tipo: 'entrega', entrega: e });
                                                    });

                                                    if (pontos.length === 1) {
                                                        container.innerHTML = `<div class="flex items-center justify-center h-[450px] text-slate-400">
            <div class="text-center"><i class="fa-regular fa-map text-3xl block mb-2"></i><p>Sem coordenadas para exibir</p></div>
                                                    </div>`;
                                                    return;
                                                }

                                                mapaRota = L.map(container, { center: [DISTRIBUIDORA_LAT, DISTRIBUIDORA_LNG], zoom: 12, zoomControl: false });
                                                L.control.zoom({ position: 'topright' }).addTo(mapaRota);
                                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                                                    maxZoom: 19
                                                }).addTo(mapaRota);

                                                const latLngs = pontos.map(function(p) { return [p.lat, p.lng]; });
                                                rotaPolyline = L.polyline(latLngs, { color: '#1a3c34', weight: 4, opacity: 0.7, dashArray: '8, 6' }).addTo(mapaRota);

                                                const distribuidoraIcon = L.divIcon({
                                                    className: 'marker-distribuidora',
                                                    html: '<i class="fa-solid fa-building" style="color:#1a3c34; font-size:22px;"></i>',
                                                    iconSize: [44, 44],
                                                    iconAnchor: [22, 22]
                                                });
                                                L.marker([DISTRIBUIDORA_LAT, DISTRIBUIDORA_LNG], { icon: distribuidoraIcon })
                                                .addTo(mapaRota)
                                                .bindPopup(`<div style="font-family:Inter;padding:6px;"><p class="font-bold text-[#1a3c34]">🏢 Nutricional Distribuidora</p><p class="text-sm">${DISTRIBUIDORA_ENDERECO}</p><p class="text-xs text-slate-400">Ponto de partida</p></div>`);

                                                entregas.forEach(function(e, index) {
                                                    if (!e.latitude || !e.longitude) return;
                                                    const isAtiva = entregaSelecionadaId === e.id;
                                                    const entregaIcon = L.divIcon({
                                                        className: 'marker-entrega ' + (isAtiva ? 'ativa' : '') + ' ' + (e.status || 'pendente'),
                                                        html: '' + (index + 1),
                                                        iconSize: [34, 34],
                                                        iconAnchor: [17, 17]
                                                    });
                                                    const marker = L.marker([e.latitude, e.longitude], { icon: entregaIcon })
                                                    .addTo(mapaRota)
                                                    .bindPopup(`
                <div style="font-family:Inter;padding:6px;min-width:200px;">
                    <p class="font-bold">${index + 1}. ${e.cliente_nome || 'Cliente'}</p>
                    <p class="text-sm">${e.endereco || ''}${e.numero ? ', ' + e.numero : ''}</p>
                    <p class="text-sm text-slate-500">${e.bairro ? e.bairro + ', ' : ''}${e.cidade || ''}${e.uf ? ', ' + e.uf : ''}</p>
                    <hr style="margin:4px 0;">
                    ${e.pedido_id ? '<p class="text-xs"><strong>Pedido:</strong> #' + e.pedido_id + '</p>' : ''}
                    ${e.valor_total ? '<p class="text-xs"><strong>Valor:</strong> ' + formatarMoeda(e.valor_total) + '</p>' : ''}
                    ${e.peso_total ? '<p class="text-xs"><strong>Peso:</strong> ' + formatarPeso(e.peso_total) + '</p>' : ''}
                                                        ${e.origem_geolocalizacao ? `<p class="text-xs mt-1"><span class="geo-badge ${e.origem_geolocalizacao === 'frota_cliente' ? 'frota_cliente' : 'google_maps'}"><i class="fa-solid fa-location-dot"></i> ${e.origem_geolocalizacao}</span></p>` : ''}
                    <p class="text-xs text-slate-400 mt-1">Status: ${e.status || 'pendente'}</p>
                </div>
                                                    `);
                                                    marker.on('click', function() { selecionarEntregaNoMapa(e.id); });
                                                    rotaMarkers.push(marker);
                                                });

                                                const allPoints = pontos.map(function(p) { return [p.lat, p.lng]; });
                                                mapaRota.fitBounds(L.latLngBounds(allPoints), { padding: [50, 50] });
                                            }

// ======================================================================
// AGRUPAR EMBARQUES
// ======================================================================
                                            function agruparEmbarques(embarques) {
                                                if (!embarques || embarques.length === 0) return [];

                                                const grupos = {};

                                                embarques.forEach(emb => {
                                                    if (emb.total_embarques_agrupados && emb.total_embarques_agrupados > 1) {
                                                        const chave = 'grupo_' + emb.id;
                                                        if (!grupos[chave]) {
                                                            grupos[chave] = {
                                                                veiculo_id: emb.veiculo_id,
                                                                veiculo_placa: emb.veiculo_placa || 'Não definido',
                                                                veiculo_modelo: emb.veiculo_modelo || '',
                                                                motorista_id: emb.motorista_id,
                                                                motorista_nome: emb.motorista_nome || 'Não definido',
                                                                embarques: [emb],
                                                                total_entregas: emb.total_entregas || 0,
                                                                entregas_concluidas: emb.entregas_concluidas || 0,
                                                                valor_total: emb.valor_total_entregas || 0,
                                                                peso_total: emb.peso_total_entregas || 0,
                                                                total_embarques: emb.total_embarques_agrupados,
                                                                erp_ids: emb.erp_ids_agrupados ? emb.erp_ids_agrupados.split(',') : [],
                                                                status: emb.status
                                                            };
                                                        }
                                                        return;
                                                    }

                                                    const chave = `${emb.veiculo_id || 'sem_veiculo'}_${emb.motorista_id || 'sem_motorista'}`;
                                                    if (!grupos[chave]) {
                                                        grupos[chave] = {
                                                            veiculo_id: emb.veiculo_id,
                                                            veiculo_placa: emb.veiculo_placa || 'Não definido',
                                                            veiculo_modelo: emb.veiculo_modelo || '',
                                                            motorista_id: emb.motorista_id,
                                                            motorista_nome: emb.motorista_nome || 'Não definido',
                                                            embarques: [],
                                                            total_entregas: 0,
                                                            entregas_concluidas: 0,
                                                            valor_total: 0,
                                                            peso_total: 0,
                                                            total_embarques: 0,
                                                            erp_ids: [],
                                                            status: 'planejado'
                                                        };
                                                    }
                                                    grupos[chave].embarques.push(emb);
                                                    grupos[chave].total_entregas += parseInt(emb.total_entregas) || 0;
                                                    grupos[chave].entregas_concluidas += parseInt(emb.entregas_concluidas) || 0;
                                                    grupos[chave].valor_total += parseFloat(emb.valor_total_entregas) || 0;
                                                    grupos[chave].peso_total += parseFloat(emb.peso_total_entregas) || 0;
                                                    grupos[chave].total_embarques++;
                                                    if (emb.erp_embarque_id) {
                                                        grupos[chave].erp_ids.push(emb.erp_embarque_id);
                                                    }
                                                    if (emb.status !== 'planejado') {
                                                        grupos[chave].status = 'em_andamento';
                                                    }
                                                });

return Object.values(grupos);
}

// ======================================================================
// SORTABLE
// ======================================================================
function initSortable() {
    const container = document.getElementById('lista-entregas-container');
    if (!container) return;
    new Sortable(container, {
        animation: 150,
        handle: '.drag-handle',
        onStart: function(e) { e.item.classList.add('dragging'); },
        onEnd: function(e) {
            e.item.classList.remove('dragging');
            atualizarOrdemAposDrag();
        }
    });
}

function atualizarOrdemAposDrag() {
    const items = document.querySelectorAll('.entrega-item');
    const novaOrdem = [];
    items.forEach(function(item, index) {
        const id = parseInt(item.dataset.id);
        novaOrdem.push(id);
        const ordemSpan = item.querySelector('.ordem');
        if (ordemSpan) ordemSpan.textContent = index + 1;
    });
    const novaLista = [];
    novaOrdem.forEach(function(id) {
        const entrega = entregasAtuais.find(function(e) { return e.id === id; });
        if (entrega) novaLista.push(entrega);
    });
    entregasAtuais = novaLista;
    recalcularDistanciaTotal();
    atualizarPolilinhaRota();
    salvarOrdemEntregas(embarqueIdDetalhes, novaOrdem);
}

function recalcularDistanciaTotal() {
    let total = 0;
    let ultimo = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };
    entregasAtuais.forEach(function(e) {
        if (e.latitude && e.longitude) {
            const d = calcularDistancia(ultimo.lat, ultimo.lng, e.latitude, e.longitude);
            if (d !== null) total += d;
            ultimo.lat = e.latitude;
            ultimo.lng = e.longitude;
        }
    });
    const el = document.querySelector('.distancia-total');
    if (el) el.textContent = total.toFixed(2) + ' km';
}

function atualizarPolilinhaRota() {
    if (!mapaRota) return;
    if (rotaPolyline) mapaRota.removeLayer(rotaPolyline);
    const pontos = [{ lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG }];
    entregasAtuais.forEach(function(e) {
        if (e.latitude && e.longitude) pontos.push({ lat: e.latitude, lng: e.longitude });
    });
    const latLngs = pontos.map(function(p) { return [p.lat, p.lng]; });
    rotaPolyline = L.polyline(latLngs, { color: '#1a3c34', weight: 4, opacity: 0.7, dashArray: '8, 6' }).addTo(mapaRota);
}

async function salvarOrdemEntregas(embarqueId, novaOrdem) {
    const token = getAuthToken();
    if (!token) return;
    try {
        await fetch('/v1/frota/embarques/' + embarqueId + '/reordenar', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify({ ordem: novaOrdem })
        });
    } catch (e) {}
}

// ======================================================================
// OTIMIZAR ROTA
// ======================================================================
async function otimizarRota() {
    const id = embarqueIdDetalhes;
    if (!id) return;
    const token = getAuthToken();
    if (!token) return;
    try {
        const response = await fetch('/v1/frota/embarques/' + id + '/otimizar-rota', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                mostrarNotificacao('Rota otimizada com sucesso!', 'success');
                verDetalhes(id);
            }
        }
    } catch (e) {
        mostrarNotificacao('Erro ao otimizar rota', 'error');
    }
}

// ======================================================================
// VER ITENS DO CHECKOUT
// ======================================================================
async function verItensCheckout(entregaId) {
    const token = getAuthToken();
    if (!token) {
        mostrarNotificacao('Token não encontrado', 'error');
        return;
    }

    try {
        let entrega = null;
        let embarqueId = embarqueIdDetalhes || 1;

        if (entregasAtuais && entregasAtuais.length > 0) {
            entrega = entregasAtuais.find(e => e.id === entregaId);
        }

        if (!entrega || !entrega.checklist || entrega.checklist.length === 0) {
            const resp = await fetch(`/v1/frota/embarques/${embarqueId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await resp.json();
            if (data.success && data.data.entregas) {
                entrega = data.data.entregas.find(e => e.id === entregaId);
            }
        }

        if (!entrega) {
            const resp = await fetch(`/v1/frota/entregas/${entregaId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await resp.json();
            if (data.success && data.data) {
                entrega = data.data;
            }
        }

        if (!entrega) {
            Swal.fire({
                title: 'Atenção',
                text: 'Não foi possível encontrar os dados da entrega.',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
            return;
        }

        if (!entrega.checklist || entrega.checklist.length === 0) {
            if (entrega.foto_romaneio_url) {
                Swal.fire({
                    title: '📸 Fotos da Entrega',
                    html: `
                        <div style="text-align: left; padding: 8px;">
                            <p style="color: #f59e0b; margin-bottom: 12px;">
                                <i class="fa-solid fa-info-circle"></i> 
                                Esta entrega possui foto do romaneio, mas não tem itens registrados no checklist.
                            </p>
                            <div style="margin: 8px 0;">
                                <strong>📷 Romaneio:</strong>
                                <span onclick="event.stopPropagation(); abrirZoomFoto('${entrega.foto_romaneio_url}', '📷 Romaneio')" 
                                      style="color: #3b82f6; text-decoration: underline; cursor: pointer; transition: color 0.2s;"
                                      onmouseover="this.style.color='#2563eb'" 
                                      onmouseout="this.style.color='#3b82f6'">
                                    <i class="fa-regular fa-image"></i> Ver foto
                                </span>
                            </div>
                        ${entrega.nome_recebedor ? `<div style="margin: 8px 0;"><strong>👤 Recebedor:</strong> ${entrega.nome_recebedor}</div>` : ''}
                        </div>
                        `,
                        confirmButtonText: 'Fechar',
                        confirmButtonColor: '#10b981'
                    });
                return;
            }

            Swal.fire({
                title: 'Atenção',
                html: `
                    <div style="text-align: left; padding: 8px;">
                        <p>Esta entrega <strong>não possui itens</strong> registrados no checklist.</p>
                        <p style="font-size: 0.85rem; color: #64748b; margin-top: 8px;">
                            Isso pode ocorrer quando:
                            <br>• A entrega não tem pedidos associados
                            <br>• O checkout foi feito sem checklist de itens
                            <br>• Os itens ainda não foram sincronizados
                        </p>
                    ${entrega.nome_recebedor ? `<div style="margin-top: 12px; padding: 8px; background: #f0fdf4; border-radius: 8px;"><strong>👤 Recebedor:</strong> ${entrega.nome_recebedor}</div>` : ''}
                    </div>
                    `,
                    icon: 'info',
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#10b981'
                });
            return;
        }

        function formatarNumero(valor) {
            if (valor === undefined || valor === null) return '0';
            const num = parseFloat(valor);
            if (isNaN(num)) return '0';
            if (Number.isInteger(num)) {
                return num.toString();
            }
            return num.toFixed(2);
        }

        let html = `
            <div style="max-height: 500px; overflow-y: auto; padding: 8px;">
                <div style="background: #f0fdf4; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #bbf7d0;">
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; justify-content: space-between;">
                        <div>
                            <span style="font-weight: 600;">👤 Recebedor:</span> 
                            <span style="color: #065f46;">${entrega.nome_recebedor || 'Não informado'}</span>
                        </div>
                        <div>
                            <span style="font-weight: 600;">📷 Romaneio:</span>
                            ${entrega.foto_romaneio_url 
                                ? `<span onclick="event.stopPropagation(); abrirZoomFoto('${entrega.foto_romaneio_url}', '📷 Romaneio')" 
                                      style="color: #3b82f6; text-decoration: underline; cursor: pointer; transition: color 0.2s;"
                                      onmouseover="this.style.color='#2563eb'" 
                                      onmouseout="this.style.color='#3b82f6'">
                                      <i class="fa-regular fa-image"></i> Ver foto
                                </span>` 
                                : 'Não possui'
                            }
                        </div>
                        <div>
                            <span style="font-weight: 600;">📦 Total Itens:</span>
                            <span style="color: #1a3c34; font-weight: 700;">${entrega.checklist.length}</span>
                        </div>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; min-width: 650px;">
                        <thead style="background: var(--nutri-border); position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="padding: 8px 12px; text-align: left; font-weight: 600; white-space: nowrap;">Referência</th>
                                <th style="padding: 8px 12px; text-align: left; font-weight: 600; min-width: 150px;">Produto</th>
                                <th style="padding: 8px 12px; text-align: center; font-weight: 600; white-space: nowrap;">Previsto</th>
                                <th style="padding: 8px 12px; text-align: center; font-weight: 600; white-space: nowrap;">Entregue</th>
                                <th style="padding: 8px 12px; text-align: center; font-weight: 600; white-space: nowrap;">Status</th>
                                <th style="padding: 8px 12px; text-align: left; font-weight: 600; min-width: 100px;">Motivo</th>
                                <th style="padding: 8px 12px; text-align: center; font-weight: 600; white-space: nowrap;">Foto</th>
                            </tr>
                        </thead>
                        <tbody>
                            `;

                            entrega.checklist.forEach(item => {
                                const statusColor = item.status === 'entregue' ? '#10b981' : 
                                item.status === 'faltante' ? '#f59e0b' : '#dc2626';
                                const statusLabel = item.status === 'entregue' ? '✅ Entregue' : 
                                item.status === 'faltante' ? '⚠️ Faltante' : '🔄 Devolvido';

                                const temFoto = item.foto_url 
                                ? `<span onclick="event.stopPropagation(); abrirZoomFoto('${item.foto_url}', '📦 ${item.referencia || 'Item'}')" 
                       style="cursor: pointer; font-size: 1.1rem; color: #3b82f6; transition: transform 0.2s; display: inline-block;"
                       onmouseover="this.style.transform='scale(1.2)'" 
                       onmouseout="this.style.transform='scale(1)'"
                                title="Clique para ampliar">📸</span>` 
                                : '—';

                                const qtdPrevista = formatarNumero(item.quantidade_prevista);
                                const qtdEntregue = formatarNumero(item.quantidade_entregue);
                                const isProblema = parseFloat(item.quantidade_entregue || 0) < parseFloat(item.quantidade_prevista || 0);
                                const nomeProduto = item.descricao || item.nome_produto || item.produto_nome || '—';

                                html += `
                <tr style="border-bottom: 1px solid var(--nutri-border); ${isProblema ? 'background: #fef2f2;' : ''}">
                    <td style="padding: 8px 12px; font-weight: 500; white-space: nowrap;">${item.referencia || '—'}</td>
                    <td style="padding: 8px 12px; font-size: 0.8rem; color: var(--nutri-text); max-width: 200px; word-break: break-word;">${nomeProduto}</td>
                    <td style="padding: 8px 12px; text-align: center;">${qtdPrevista}</td>
                    <td style="padding: 8px 12px; text-align: center; font-weight: ${isProblema ? '700' : '400'}; color: ${isProblema ? '#dc2626' : 'var(--nutri-text)'};">${qtdEntregue}</td>
                    <td style="padding: 8px 12px; text-align: center;">
                        <span style="background: ${statusColor}20; color: ${statusColor}; padding: 2px 12px; border-radius: 999px; font-size: 0.7rem; font-weight: 600; white-space: nowrap;">
                            ${statusLabel}
                        </span>
                    </td>
                    <td style="padding: 8px 12px; font-size: 0.75rem; color: ${item.motivo ? '#dc2626' : 'var(--nutri-text-secondary)'};">${item.motivo || '—'}</td>
                    <td style="padding: 8px 12px; text-align: center;">${temFoto}</td>
                </tr>
                                `;
                            });

                            const itensProblema = entrega.checklist.filter(item => item.status !== 'entregue');

                            html += `
                        </tbody>
                    </table>
                </div>

                                ${itensProblema.length > 0 ? `
                    <div style="margin-top: 12px; padding: 10px 16px; background: #fef2f2; border-radius: 8px; border: 1px solid #fca5a5;">
                        <span style="color: #dc2626; font-weight: 600;">
                            ⚠️ ${itensProblema.length} item(ns) com problema (faltante/devolvido)
                        </span>
                    </div>
                                    ` : `
                    <div style="margin-top: 12px; padding: 10px 16px; background: #d1fae5; border-radius: 8px; border: 1px solid #6ee7b7;">
                        <span style="color: #065f46; font-weight: 600;">
                            ✅ Todos os itens foram entregues!
                        </span>
                    </div>
                                    `}
            </div>
                                `;

                                Swal.fire({
                                    title: '📋 Itens do Checkout',
                                    html: html,
                                    width: '1000px',
                                    confirmButtonText: 'Fechar',
                                    confirmButtonColor: '#10b981',
                                    customClass: {
                                        popup: 'checkout-items-modal'
                                    }
                                });

                            } catch (error) {
                                mostrarNotificacao('Erro ao carregar itens: ' + error.message, 'error');
                            }
                        }

// ======================================================================
// CRIAR ROTAS SELECIONADAS (COM SPINNER MELHORADO)
// ======================================================================
async function criarRotasSelecionadas() {
    if (embarquesSelecionados.length === 0) {
        Swal.fire('Atenção', 'Selecione pelo menos um embarque', 'warning');
        return;
    }

    const token = getAuthToken();
    if (!token) {
        window.location.href = '/portal/login.php';
        return;
    }

    const totalSelecionados = embarquesSelecionados.length;
    const isMultiplo = totalSelecionados > 1;

    let motoristasERP = [];
    let veiculosERP = [];
    let dadosEmbarques = [];

    try {
        Swal.fire({
            title: 'Buscando dados do ERP...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        for (const id of embarquesSelecionados) {
            const response = await fetch(`/v1/frota/importar/embarque-detalhes/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (response.ok) {
                const data = await response.json();
                if (data.success) {
                    dadosEmbarques.push(data.data);
                    if (data.data.idmotorista) {
                        const nomeMotorista = data.data.motorista_nome || data.data.motorista_razao || `Motorista ERP #${data.data.idmotorista}`;
                        if (!motoristasERP.find(m => m.id === data.data.idmotorista)) {
                            motoristasERP.push({
                                id: data.data.idmotorista,
                                nome: nomeMotorista,
                                cpf: data.data.motorista_cpf || '',
                                telefone: data.data.motorista_telefone || '',
                                email: data.data.motorista_email || '',
                                endereco: data.data.motorista_endereco || '',
                                bairro: data.data.motorista_bairro || '',
                                cidade: data.data.motorista_cidade || '',
                                uf: data.data.motorista_uf || '',
                                cep: data.data.motorista_cep || '',
                                complemento: data.data.motorista_complemento || '',
                                numero: data.data.motorista_numero || '',
                                existe: false,
                                id_sistema: null
                            });
                        }
                    }
                    if (data.data.placa) {
                        if (!veiculosERP.find(v => v.placa === data.data.placa)) {
                            veiculosERP.push({
                                placa: data.data.placa,
                                modelo: '',
                                marca: '',
                                ano: '',
                                capacidade_peso: '',
                                existe: false,
                                id_sistema: null
                            });
                        }
                    }
                }
            }
        }
        Swal.close();
    } catch (error) {
        Swal.close();
        mostrarNotificacao('Erro ao carregar dados dos embarques', 'error');
        return;
    }

    let veiculosSistema = [];
    let motoristasSistema = [];

    try {
        const [respVeiculos, respMotoristas] = await Promise.all([
            fetch('/v1/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch('/v1/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
        ]);

        if (respVeiculos.ok) {
            const d = await respVeiculos.json();
            if (d.success) veiculosSistema = d.data || [];
        }
        if (respMotoristas.ok) {
            const d = await respMotoristas.json();
            if (d.success) motoristasSistema = d.data || [];
        }

        motoristasERP.forEach(m => {
            const encontrado = motoristasSistema.find(ms => ms.erp_id == m.id || (ms.nome && m.nome && ms.nome.toLowerCase() === m.nome.toLowerCase()));
            if (encontrado) {
                m.existe = true;
                m.id_sistema = encontrado.id;
                m.nome_sistema = encontrado.nome;
            }
        });

        veiculosERP.forEach(v => {
            const encontrado = veiculosSistema.find(vs => vs.placa && v.placa && vs.placa.toUpperCase() === v.placa.toUpperCase());
            if (encontrado) {
                v.existe = true;
                v.id_sistema = encontrado.id;
                v.modelo = encontrado.modelo || '';
            }
        });

        const motoristasNaoExistentes = motoristasERP.filter(m => !m.existe);
        const veiculosNaoExistentes = veiculosERP.filter(v => !v.existe);

        if (motoristasNaoExistentes.length > 0 || veiculosNaoExistentes.length > 0) {
            const cadastrados = await abrirModalCadastroCompleto(motoristasNaoExistentes, veiculosNaoExistentes);
            if (!cadastrados) return;

            const [rv2, rm2] = await Promise.all([
                fetch('/v1/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
                fetch('/v1/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
            ]);
            if (rv2.ok) {
                const d = await rv2.json();
                if (d.success) veiculosSistema = d.data || [];
            }
            if (rm2.ok) {
                const d = await rm2.json();
                if (d.success) motoristasSistema = d.data || [];
            }

            motoristasERP.forEach(m => {
                const e = motoristasSistema.find(ms => ms.erp_id == m.id || ms.nome === m.nome);
                if (e) { m.existe = true;
                    m.id_sistema = e.id; }
            });
            veiculosERP.forEach(v => {
                const e = veiculosSistema.find(vs => vs.placa.toUpperCase() === v.placa.toUpperCase());
                if (e) { v.existe = true;
                    v.id_sistema = e.id;
                    v.modelo = e.modelo || ''; }
            });
        }
    } catch (error) {
        mostrarNotificacao('Erro ao verificar dados no sistema', 'error');
        return;
    }

    const nomeSugerido = gerarNomeEmbarque(dadosEmbarques);

    let veiculoOptions = '<option value="">Selecione um veículo</option>';
    veiculosERP.filter(v => v.existe).forEach(v => {
        veiculoOptions += `<option value="${v.id_sistema}" class="erp-option">🚛 ${v.placa} - ${v.modelo || 'ERP'} ✅</option>`;
    });
    veiculosSistema.filter(vs => {
        return !veiculosERP.find(ve => ve.placa === vs.placa);
    }).forEach(v => {
        veiculoOptions += `<option value="${v.id}">🚛 ${v.placa} - ${v.modelo}</option>`;
    });
    if (veiculosERP.length > 0 && veiculosERP.filter(v => v.existe).length === 0) {
        veiculoOptions += `<option value="0" class="text-emerald-600 font-bold">🔄 Criar veículo automaticamente (${veiculosERP[0].placa})</option>`;
    }

    let motoristaOptions = '<option value="">Selecione um motorista</option>';
    motoristasERP.filter(m => m.existe).forEach(m => {
        motoristaOptions += `<option value="${m.id_sistema}" class="erp-option">👤 ${m.nome} ✅</option>`;
    });
    motoristasSistema.filter(ms => {
        return !motoristasERP.find(me => me.id_sistema === ms.id);
    }).forEach(m => {
        motoristaOptions += `<option value="${m.id}">👤 ${m.nome}</option>`;
    });
    if (motoristasERP.length > 0 && motoristasERP.filter(m => m.existe).length === 0) {
        motoristaOptions += `<option value="0" class="text-emerald-600 font-bold">🔄 Criar motorista automaticamente (${motoristasERP[0].nome})</option>`;
    }

    let infoEmbarques = '';
    if (dadosEmbarques.length > 0) {
        infoEmbarques = `
            <div class="bg-blue-50 rounded-lg p-3 mb-3 text-sm border border-blue-200 dark:bg-blue-900/20 dark:border-blue-800">
                <p class="font-bold text-[#1a3c34] dark:text-white">📋 Embarques Selecionados (${dadosEmbarques.length})</p>
                <div class="max-h-[100px] overflow-y-auto">
                    ${dadosEmbarques.map(e => `
                        <div class="flex items-center gap-2 py-1 border-b border-blue-100 dark:border-blue-800 last:border-0">
                            <span class="font-bold text-xs text-blue-600 dark:text-blue-400">#${e.idembarque}</span>
                            <span class="text-xs text-slate-600 dark:text-slate-300">${e.rota || 'Sem nome'}</span>
                            ${e.placa ? `<span class="text-xs bg-slate-200 dark:bg-slate-700 px-1.5 py-0.5 rounded">${e.placa}</span>` : ''}
                            ${e.idmotorista ? `<span class="text-xs text-slate-400">Motorista: ${e.motorista_nome || '#' + e.idmotorista}</span>` : ''}
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    const result = await Swal.fire({
        title: isMultiplo ? `🚛 Criar Grupo (${totalSelecionados} embarques)` : '🚛 Criar Rota',
        html: `
            <div class="text-left">
                <p class="text-sm text-slate-500 mb-3 dark:text-slate-400">
                    <strong>${totalSelecionados}</strong> embarque${totalSelecionados > 1 ? 's' : ''} 
                    ${isMultiplo ? 'serão consolidados em um único grupo' : 'será convertido em rota'}
                </p>
                ${infoEmbarques}
                <div class="mb-3">
                    <label class="form-label">Nome do Embarque / Rota</label>
                    <input type="text" id="nome-embarque-massa" class="form-control" value="${nomeSugerido}" placeholder="Digite o nome do embarque">
                    <small class="text-xs text-slate-400 mt-1">${dadosEmbarques.length === 1 ? '💡 Nome baseado na rota do ERP' : '💡 Nome sugerido baseado nos embarques selecionados'}</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Veículo</label>
                    <select id="veiculo-select-massa" class="form-select">${veiculoOptions}</select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Motorista</label>
                    <select id="motorista-select-massa" class="form-select">${motoristaOptions}</select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Data/Hora Saída</label>
                    <input type="datetime-local" id="data-saida-massa" class="form-control" value="${new Date().toISOString().slice(0, 16)}">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: isMultiplo ? 'Criar Grupo' : 'Criar Rota',
        cancelButtonColor: '#dc2626',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        width: '650px',
        preConfirm: function() {
            const nomeEmbarque = document.getElementById('nome-embarque-massa').value.trim();
            let veiculoId = document.getElementById('veiculo-select-massa').value;
            let motoristaId = document.getElementById('motorista-select-massa').value;
            const dataSaida = document.getElementById('data-saida-massa').value;
            if (!nomeEmbarque) {
                Swal.showValidationMessage('O nome do embarque é obrigatório');
                return false;
            }
            if (veiculoId === '0' || veiculoId === '') veiculoId = 0;
            else veiculoId = parseInt(veiculoId);
            if (motoristaId === '0' || motoristaId === '') motoristaId = 0;
            else motoristaId = parseInt(motoristaId);
            return { nomeEmbarque, veiculoId, motoristaId, dataSaida };
        }
    });

    if (!result.isConfirmed) return;
    const { nomeEmbarque, veiculoId, motoristaId, dataSaida } = result.value;

    const payload = {
        veiculo_id: veiculoId,
        motorista_id: motoristaId,
        data_saida: dataSaida,
        usuario_id: getUserId(),
        nome_embarque: nomeEmbarque
    };

    if (isMultiplo) {
        payload.ids_agrupados = embarquesSelecionados;
    } else {
        payload.id_embarque_erp = embarquesSelecionados[0];
    }

    // 🔥 SPINNER MELHORADO
    mostrarSpinner(
        isMultiplo ? 'Criando grupo de embarques...' : 'Criando rota...',
        isMultiplo ? `Consolidando ${totalSelecionados} embarques...` : 'Processando...',
        0
    );

    try {
        const response = await fetch('/v1/frota/importar/criar-embarque', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        atualizarSpinner(
            isMultiplo ? 'Criando grupo de embarques...' : 'Criando rota...',
            'Aguarde, processando resposta...',
            70
        );

        const dados = await response.json();

        atualizarSpinner(
            isMultiplo ? 'Criando grupo de embarques...' : 'Criando rota...',
            'Finalizando...',
            90
        );

        setTimeout(() => fecharSpinner(), 300);

        if (dados.success) {
            let msg = isMultiplo ?
                `✅ Grupo criado com ${totalSelecionados} embarques consolidados!` :
                '✅ Rota criada com sucesso!';
            if (dados.motorista_criado) msg += `\n🚛 Motorista criado: ${dados.motorista_criado.nome}`;
            if (dados.veiculo_criado) msg += `\n🚗 Veículo criado: ${dados.veiculo_criado.placa}`;
            if (dados.data && dados.data.total_entregas) {
                msg += `\n📦 ${dados.data.total_entregas} entregas geradas`;
            }
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: msg,
                timer: 4000,
                showConfirmButton: false
            });
            carregarDisponiveis();
            carregarEmbarques();

        } else {
            Swal.fire('Erro', dados.error || 'Falha ao criar rota', 'error');
        }
    } catch (error) {
        fecharSpinner();
        Swal.fire('Erro', error.message || 'Falha ao criar rotas', 'error');
    }
}

// ======================================================================
// MODAL CADASTRO COMPLETO
// ======================================================================
                    async function abrirModalCadastroCompleto(motoristasNaoExistentes, veiculosNaoExistentes) {
                        return new Promise(function(resolve) {
                            let html = `
            <div class="text-left">
                <p class="text-sm text-amber-600 font-bold mb-3">
                    ⚠️ Os seguintes dados não foram encontrados no sistema. 
                    <br>Os campos já estão pré-preenchidos com as informações do ERP.
                    <br>Complete as informações faltantes e clique em "Cadastrar".
                </p>
                            `;

                            if (motoristasNaoExistentes.length > 0) {
                                html += `
                <div class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <h4 class="font-bold text-[#1a3c34] text-sm mb-2">
                        <i class="fa-solid fa-user mr-2"></i> Motoristas a cadastrar (${motoristasNaoExistentes.length})
                    </h4>
                                `;
                                motoristasNaoExistentes.forEach(function(m, index) {
                                    html += `
                    <div class="mb-3 p-3 bg-white rounded-lg border border-slate-200">
                        <p class="text-sm font-bold text-[#1a3c34] mb-2">Motorista ${index + 1}: ${m.nome}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="col-span-2">
                                <label class="text-[10px] font-bold text-slate-500">Nome *</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-nome" data-id="${m.id}" value="${m.nome}" readonly style="background:#f1f5f9;">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">CPF</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-cpf" data-id="${m.id}" value="${m.cpf || ''}" placeholder="000.000.000-00">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Telefone</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-telefone" data-id="${m.id}" value="${m.telefone || ''}" placeholder="(00) 00000-0000">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">E-mail</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-email" data-id="${m.id}" value="${m.email || ''}" placeholder="email@exemplo.com">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Endereço</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-endereco" data-id="${m.id}" value="${m.endereco || ''}" placeholder="Endereço completo">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Bairro</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-bairro" data-id="${m.id}" value="${m.bairro || ''}">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Cidade</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-cidade" data-id="${m.id}" value="${m.cidade || ''}">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">UF</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-uf" data-id="${m.id}" value="${m.uf || ''}" maxlength="2">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">CEP</label>
                                <input type="text" class="form-control form-control-sm cadastro-motorista-cep" data-id="${m.id}" value="${m.cep || ''}">
                            </div>
                        </div>
                    </div>
                                    `;
                                });
                                html += `</div>`;
                            }

                            if (veiculosNaoExistentes.length > 0) {
                                html += `
                <div class="mb-4 p-3 bg-slate-50 rounded-lg border border-slate-200">
                    <h4 class="font-bold text-[#1a3c34] text-sm mb-2">
                        <i class="fa-solid fa-truck mr-2"></i> Veículos a cadastrar (${veiculosNaoExistentes.length})
                    </h4>
                                `;
                                veiculosNaoExistentes.forEach(function(v, index) {
                                    html += `
                    <div class="mb-3 p-3 bg-white rounded-lg border border-slate-200">
                        <p class="text-sm font-bold text-[#1a3c34] mb-2">Veículo ${index + 1}: ${v.placa}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Placa *</label>
                                <input type="text" class="form-control form-control-sm cadastro-veiculo-placa" data-placa="${v.placa}" value="${v.placa}" readonly style="background:#f1f5f9;">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Modelo *</label>
                                <input type="text" class="form-control form-control-sm cadastro-veiculo-modelo" data-placa="${v.placa}" value="${v.modelo || ''}" placeholder="Ex: Caminhão Mercedes">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Marca *</label>
                                <input type="text" class="form-control form-control-sm cadastro-veiculo-marca" data-placa="${v.placa}" value="${v.marca || ''}" placeholder="Ex: Mercedes">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Tipo *</label>
                                <select class="form-control form-control-sm cadastro-veiculo-tipo" data-placa="${v.placa}">
                                    <option value="bau">Baú</option>
                                    <option value="carreta">Carreta</option>
                                </select>
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Ano</label>
                                <input type="number" class="form-control form-control-sm cadastro-veiculo-ano" data-placa="${v.placa}" value="${v.ano || ''}" placeholder="2024">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Capacidade (kg)</label>
                                <input type="number" class="form-control form-control-sm cadastro-veiculo-capacidade" data-placa="${v.placa}" value="${v.capacidade_peso || ''}" placeholder="10000">
                            </div>
                            <div>
                                <label class="text-[10px] font-bold text-slate-500">Cor</label>
                                <input type="text" class="form-control form-control-sm cadastro-veiculo-cor" data-placa="${v.placa}" value="" placeholder="Ex: Branco">
                            </div>
                        </div>
                    </div>
                                    `;
                                });
                                html += `</div>`;
                            }

                            html += `
            <div class="text-xs text-slate-400 mt-2">
                <i class="fa-solid fa-info-circle mr-1"></i>
                Campos com * são obrigatórios. Os dados em cinza vieram do ERP.
            </div>
                            </div>`;

                            Swal.fire({
                                title: '📝 Cadastro de Dados Faltantes',
                                html: html,
                                width: '750px',
                                showCancelButton: true,
                                confirmButtonText: '✅ Cadastrar e Continuar',
                                cancelButtonText: 'Cancelar',
                                confirmButtonColor: '#10b981',
                                cancelButtonColor: '#dc2626',
                                preConfirm: async function() {
                                    const motoristasParaCadastrar = [];
                                    const motoristaElements = document.querySelectorAll('.cadastro-motorista-nome');
                                    for (const input of motoristaElements) {
                                        const id = parseInt(input.dataset.id);
                                        const container = input.closest('.p-3');
                                        const nome = input.value;
                                        const cpf = container.querySelector('.cadastro-motorista-cpf')?.value || '';
                                        const telefone = container.querySelector('.cadastro-motorista-telefone')?.value || '';
                                        const email = container.querySelector('.cadastro-motorista-email')?.value || '';
                                        const endereco = container.querySelector('.cadastro-motorista-endereco')?.value || '';
                                        const bairro = container.querySelector('.cadastro-motorista-bairro')?.value || '';
                                        const cidade = container.querySelector('.cadastro-motorista-cidade')?.value || '';
                                        const uf = container.querySelector('.cadastro-motorista-uf')?.value || '';
                                        const cep = container.querySelector('.cadastro-motorista-cep')?.value || '';
                                        if (!nome) { Swal.showValidationMessage('Nome do motorista é obrigatório'); return false; }
                                        motoristasParaCadastrar.push({
                                            erp_id: id,
                                            nome: nome,
                                            cpf: cpf || null,
                                            telefone: telefone || null,
                                            email: email || null,
                                            endereco: endereco || null,
                                            bairro: bairro || null,
                                            cidade: cidade || null,
                                            uf: uf || null,
                                            cep: cep || null,
                                            status: 'ativo'
                                        });
                                    }

                                    const veiculosParaCadastrar = [];
                                    const veiculoElements = document.querySelectorAll('.cadastro-veiculo-placa');
                                    for (const input of veiculoElements) {
                                        const placa = input.value;
                                        const container = input.closest('.p-3');
                                        const modelo = container.querySelector('.cadastro-veiculo-modelo')?.value || '';
                                        const marca = container.querySelector('.cadastro-veiculo-marca')?.value || '';
                                        const tipo = container.querySelector('.cadastro-veiculo-tipo')?.value || 'bau';
                                        const ano = container.querySelector('.cadastro-veiculo-ano')?.value || null;
                                        const capacidade = container.querySelector('.cadastro-veiculo-capacidade')?.value || null;
                                        const cor = container.querySelector('.cadastro-veiculo-cor')?.value || '';
                                        if (!modelo) { Swal.showValidationMessage('Modelo do veículo ' + placa + ' é obrigatório'); return false; }
                                        if (!marca) { Swal.showValidationMessage('Marca do veículo ' + placa + ' é obrigatória'); return false; }
                                        veiculosParaCadastrar.push({
                                            placa: placa,
                                            modelo: modelo || 'Veículo ERP',
                                            marca: marca || 'Não Informada',
                                            tipo: tipo,
                                            ano: ano || null,
                                            capacidade_peso: capacidade || null,
                                            cor: cor || null,
                                            status: 'disponivel'
                                        });
                                    }

                                    try {
                                        const token = getAuthToken();
                                        const resultados = [];
                                        for (const m of motoristasParaCadastrar) {
                                            const r = await fetch('/v1/frota/motoristas', {
                                                method: 'POST',
                                                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                                                body: JSON.stringify(m)
                                            });
                                            resultados.push(await r.json());
                                        }
                                        for (const v of veiculosParaCadastrar) {
                                            const r = await fetch('/v1/frota/veiculos', {
                                                method: 'POST',
                                                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                                                body: JSON.stringify(v)
                                            });
                                            resultados.push(await r.json());
                                        }
                                        const todosSucesso = resultados.every(function(r) { return r.success !== false; });
                                        if (!todosSucesso) {
                                            const erros = resultados.filter(function(r) { return r.success === false; }).map(function(r) { return r.error; }).join('\n');
                                            Swal.showValidationMessage('Erro ao cadastrar: ' + erros);
                                            return false;
                                        }
                                        return true;
                                    } catch (error) {
                                        Swal.showValidationMessage('Erro ao cadastrar: ' + error.message);
                                        return false;
                                    }
                                }
                            }).then(function(result) {
                                resolve(result.isConfirmed ? true : false);
                            });
                        });
}

// ======================================================================
// EDITAR GRUPO
// ======================================================================
async function abrirModalEditarGrupo(ids) {
    let listaIds = [];
    if (Array.isArray(ids)) {
        listaIds = ids;
    } else if (typeof ids === 'string') {
        listaIds = ids.split(',').map(Number);
    } else if (typeof ids === 'number') {
        listaIds = [ids];
    }
    listaIds = listaIds.filter(id => id && !isNaN(id));

    if (listaIds.length === 0) {
        mostrarNotificacao('Nenhum ID válido', 'error');
        return;
    }

    const token = getAuthToken();
    if (!token) return;

    try {
        const primeiroId = listaIds[0];
        const resp = await fetch(`/v1/frota/embarques/${primeiroId}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        if (!data.success) {
            Swal.fire('Erro', data.error || 'Falha ao carregar dados', 'error');
            return;
        }
        const emb = data.data;

        const [respVeiculos, respMotoristas] = await Promise.all([
            fetch('/v1/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch('/v1/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
        ]);
        const veiculos = (await respVeiculos.json()).data || [];
        const motoristas = (await respMotoristas.json()).data || [];

        let veiculoOptions = '<option value="">Selecione</option>';
        veiculos.forEach(v => {
            const selected = v.id == emb.veiculo_id ? 'selected' : '';
            veiculoOptions += `<option value="${v.id}" ${selected}>${v.placa} - ${v.modelo || ''}</option>`;
        });
        let motoristaOptions = '<option value="">Selecione</option>';
        motoristas.forEach(m => {
            const selected = m.id == emb.motorista_id ? 'selected' : '';
            motoristaOptions += `<option value="${m.id}" ${selected}>${m.nome}</option>`;
        });

        let todasEntregas = [];
        for (const id of listaIds) {
            const respEntrega = await fetch(`/v1/frota/embarques/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const dataEntrega = await respEntrega.json();
            if (dataEntrega.success && dataEntrega.data.entregas) {
                todasEntregas = todasEntregas.concat(dataEntrega.data.entregas.map(e => ({
                    ...e,
                    embarque_id: id
                })));
            }
        }

        const entregasHtml = todasEntregas.length > 0 ? todasEntregas.map(e => {
            const statusLabel = e.status || 'pendente';
            const statusColor = statusLabel === 'entregue' ? 'bg-green-100 text-green-700' :
            statusLabel === 'falha' ? 'bg-red-100 text-red-700' :
            statusLabel === 'em_entrega' ? 'bg-yellow-100 text-yellow-700' :
            'bg-blue-100 text-blue-700';

            const temFotos = e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url));
            const fotosBadge = temFotos ? '<span class="fotos-badge"><i class="fa-regular fa-images"></i> 📸</span>' : '';

            const temCheckout = statusLabel === 'entregue' || statusLabel === 'entregue_com_problema' || statusLabel === 'falha';
            const checkoutBadge = temCheckout ? '<span class="checkout-badge"><i class="fa-solid fa-check-double"></i> ✅</span>' : '';

            return `
                <div class="entrega-edit-item" data-entrega-id="${e.id}" data-embarque-id="${e.embarque_id}">
                    <div class="entrega-edit-info">
                        <div class="cliente">
                            ${e.cliente_nome || 'Cliente'}
                            ${fotosBadge}
                            ${checkoutBadge}
                        </div>
                        <div class="detalhes">
                            <span class="pedido">💰 ${formatarMoeda(e.valor || 0)}</span>
                            <span class="peso">⚖️ ${formatarPeso(e.peso_total || 0)}</span>
                            <span class="status ${statusColor}">${statusLabel}</span>
                            <span class="embarque-ref">📦 Embarque #${e.embarque_id}</span>
                        </div>
                    </div>
                    <button class="btn-remover-entrega-edit" data-entrega-id="${e.id}" data-embarque-id="${e.embarque_id}" title="Remover esta entrega">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            `;
        }).join('') : '<p class="text-muted">Nenhuma entrega</p>';

        const modalHtml = `
            <div class="edit-modal-container">
                <div class="edit-modal-header">
                    <h3><i class="fa-solid fa-pen-to-square"></i> Editar Grupo (${listaIds.length} embarque${listaIds.length > 1 ? 's' : ''})</h3>
                </div>
                <div class="edit-modal-body">
                    <div class="form-group">
                        <label>Nome do Embarque / Rota</label>
                        <input type="text" id="edit-nome" class="form-control" value="${emb.nome_embarque || ''}" placeholder="Digite o nome da rota">
                        <small>Será aplicado a todos os embarques do grupo</small>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Veículo</label>
                            <select id="edit-veiculo" class="form-select">${veiculoOptions}</select>
                        </div>
                        <div class="form-group">
                            <label>Motorista</label>
                            <select id="edit-motorista" class="form-select">${motoristaOptions}</select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Data/Hora Saída</label>
                            <input type="datetime-local" id="edit-data" class="form-control" value="${emb.data_saida ? emb.data_saida.slice(0,16) : ''}">
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select id="edit-status" class="form-select">
                                <option value="planejado" ${emb.status === 'planejado' ? 'selected' : ''}>Planejado</option>
                                <option value="em_andamento" ${emb.status === 'em_andamento' ? 'selected' : ''}>Em Andamento</option>
                                <option value="finalizado" ${emb.status === 'finalizado' ? 'selected' : ''}>Finalizado</option>
                                <option value="cancelado" ${emb.status === 'cancelado' ? 'selected' : ''}>Cancelado</option>
                            </select>
                        </div>
                    </div>
                    <hr>
                    <div class="entregas-section">
                        <div class="entregas-header">
                            <h5><i class="fa-solid fa-list"></i> Entregas do Grupo (${todasEntregas.length})</h5>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button class="btn-add-embarque" id="btn-adicionar-embarque-erp" style="background: #3b82f6; color: white; border: none; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; cursor: pointer;">
                                    <i class="fa-solid fa-plus"></i> Adicionar Embarque ERP
                                </button>
                                <button class="btn-add-pedidos" id="btn-adicionar-pedidos" style="background: #10b981; color: white; border: none; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; cursor: pointer;">
                                    <i class="fa-solid fa-plus"></i> Adicionar Pedidos
                                </button>
                            </div>
                        </div>
                        <div id="lista-entregas-edit" class="entregas-list">
                            ${entregasHtml}
                        </div>
                    </div>
                </div>
                <div class="edit-modal-footer">
                    <button class="btn-secondary" id="btn-cancelar-editar">Cancelar</button>
                    <button class="btn-primary" id="btn-salvar-editar">💾 Salvar</button>
                </div>
            </div>
        `;

        if (!document.getElementById('edit-modal-styles')) {
            const style = document.createElement('style');
            style.id = 'edit-modal-styles';
            style.textContent = `
                .edit-modal-container { font-family: 'Inter', sans-serif; background: var(--nutri-card-bg); border-radius: 20px; padding: 24px; max-width: 720px; margin: 0 auto; box-shadow: 0 20px 60px rgba(0,0,0,0.15); }
                .edit-modal-header { border-bottom: 2px solid var(--nutri-border); padding-bottom: 12px; margin-bottom: 20px; }
                .edit-modal-header h3 { font-size: 1.25rem; font-weight: 700; color: var(--nutri-primary); }
                .edit-modal-header h3 i { color: #3b82f6; margin-right: 8px; }
                .edit-modal-body .form-group { margin-bottom: 16px; }
                .edit-modal-body .form-group label { display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: var(--nutri-text-secondary); margin-bottom: 4px; }
                .edit-modal-body .form-group small { font-size: 0.65rem; color: var(--nutri-text-secondary); display: block; margin-top: 2px; }
                .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
                .edit-modal-body hr { margin: 20px 0; border: 0; border-top: 2px solid var(--nutri-border); }
                .entregas-section .entregas-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
                .entregas-section .entregas-header h5 { font-size: 0.95rem; font-weight: 600; color: var(--nutri-primary); margin: 0; }
                .entregas-section .entregas-header h5 i { margin-right: 6px; color: #3b82f6; }
                .entregas-list { max-height: 250px; overflow-y: auto; padding-right: 4px; }
                .entregas-list::-webkit-scrollbar { width: 4px; }
                .entregas-list::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
                .entrega-edit-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; border: 1px solid var(--nutri-border); border-radius: 12px; margin-bottom: 8px; background: var(--nutri-card-bg); transition: all 0.2s; }
                .entrega-edit-item:hover { border-color: #94a3b8; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
                .entrega-edit-info .cliente { font-weight: 600; font-size: 0.9rem; color: var(--nutri-text); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
                .entrega-edit-info .cliente .fotos-badge { background: #dbeafe; color: #1e40af; font-size: 0.6rem; padding: 1px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 2px; }
                .entrega-edit-info .cliente .checkout-badge { background: #d1fae5; color: #065f46; font-size: 0.6rem; padding: 1px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 2px; }
                .entrega-edit-info .detalhes { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px; }
                .entrega-edit-info .detalhes span { font-size: 0.65rem; padding: 2px 10px; border-radius: 6px; background: var(--nutri-border); color: #475569; }
                .entrega-edit-info .detalhes .pedido { background: #d1fae5; color: #065f46; }
                .entrega-edit-info .detalhes .peso { background: #fef3c7; color: #92400e; }
                .entrega-edit-info .detalhes .status { background: #dbeafe; color: #1e40af; }
                .entrega-edit-info .detalhes .embarque-ref { background: #f3e8ff; color: #6b21a8; }
                .btn-remover-entrega-edit { background: none; border: none; color: #94a3b8; font-size: 1.1rem; padding: 6px 10px; border-radius: 8px; transition: all 0.2s; cursor: pointer; }
                .btn-remover-entrega-edit:hover { background: #fee2e2; color: #dc2626; }
                .edit-modal-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 2px solid var(--nutri-border); }
                .edit-modal-footer .btn-secondary { background: var(--nutri-border); color: var(--nutri-text); border: none; padding: 10px 24px; border-radius: 12px; font-weight: 600; transition: all 0.2s; cursor: pointer; }
                .edit-modal-footer .btn-secondary:hover { background: #cbd5e1; }
                .edit-modal-footer .btn-primary { background: var(--nutri-primary); color: white; border: none; padding: 10px 28px; border-radius: 12px; font-weight: 600; transition: all 0.2s; cursor: pointer; }
                .edit-modal-footer .btn-primary:hover { background: var(--nutri-secondary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(26, 60, 52, 0.3); }
                .btn-add-embarque, .btn-add-pedidos { transition: all 0.2s; }
                .btn-add-embarque:hover, .btn-add-pedidos:hover { transform: scale(1.05); opacity: 0.9; }
                @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .edit-modal-container { padding: 16px; } .entregas-section .entregas-header { flex-direction: column; align-items: stretch; } .entregas-section .entregas-header div { justify-content: center; } }
            `;
            document.head.appendChild(style);
        }

        await Swal.fire({
            title: '',
            html: modalHtml,
            showConfirmButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            width: '800px',
            customClass: {
                popup: 'edit-modal-popup'
            },
            didOpen: () => {
                document.getElementById('btn-cancelar-editar').addEventListener('click', () => {
                    Swal.close();
                });

                document.getElementById('btn-salvar-editar').addEventListener('click', async () => {
                    const nome = document.getElementById('edit-nome').value.trim();
                    const veiculo = parseInt(document.getElementById('edit-veiculo').value) || 0;
                    const motorista = parseInt(document.getElementById('edit-motorista').value) || 0;
                    const data = document.getElementById('edit-data').value;
                    const status = document.getElementById('edit-status').value;
                    if (!nome) {
                        Swal.fire('Atenção', 'O nome do embarque é obrigatório', 'warning');
                        return;
                    }
                    const payload = { nome_embarque: nome, veiculo_id: veiculo, motorista_id: motorista, data_saida: data, status };
                    let sucessos = 0;
                    for (const id of listaIds) {
                        const respUpdate = await fetch(`/v1/frota/embarques/${id}`, {
                            method: 'PUT',
                            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const resultUpdate = await respUpdate.json();
                        if (resultUpdate.success) sucessos++;
                    }
                    if (sucessos === listaIds.length) {
                        Swal.fire('Sucesso', `Grupo atualizado (${sucessos} embarques)`, 'success');
                        carregarEmbarques();
                        Swal.close();
                    } else {
                        Swal.fire('Aviso', `Atualizados ${sucessos} de ${listaIds.length} embarques`, 'warning');
                        carregarEmbarques();
                    }
                });

                document.querySelectorAll('.btn-remover-entrega-edit').forEach(btn => {
                    btn.addEventListener('click', async (e) => {
                        e.stopPropagation();
                        const entregaId = parseInt(btn.dataset.entregaId);
                        const embId = parseInt(btn.dataset.embarqueId);

                        const confirm = await Swal.fire({
                            title: 'Remover Entrega',
                            text: `Remover entrega #${entregaId} do embarque #${embId}?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            confirmButtonText: 'Sim, remover',
                            cancelButtonText: 'Cancelar'
                        });
                        if (!confirm.isConfirmed) return;

                        try {
                            const respDel = await fetch(`/v1/frota/embarques/${embId}/entregas/${entregaId}`, {
                                method: 'DELETE',
                                headers: { 'Authorization': 'Bearer ' + token }
                            });
                            const resultDel = await respDel.json();
                            if (resultDel.success) {
                                Swal.fire('Removido', 'Entrega removida com sucesso', 'success');
                                Swal.close();
                                abrirModalEditarGrupo(listaIds);
                                carregarEmbarques();
                            } else {
                                Swal.fire('Erro', resultDel.error || 'Falha ao remover', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Falha ao remover entrega', 'error');
                        }
                    });
                });

                document.getElementById('btn-adicionar-embarque-erp')?.addEventListener('click', async () => {
                    try {
                        Swal.fire({
                            title: 'Carregando embarques ERP...',
                            didOpen: () => Swal.showLoading(),
                            allowOutsideClick: false
                        });

                        const respErp = await fetch('/v1/frota/importar/embarques-erp', {
                            headers: { 'Authorization': 'Bearer ' + token }
                        });
                        const dadosErp = await respErp.json();
                        Swal.close();

                        if (!dadosErp.success || !dadosErp.data || dadosErp.data.length === 0) {
                            Swal.fire('Aviso', 'Nenhum embarque ERP disponível para adicionar', 'info');
                            return;
                        }

                        const options = dadosErp.data.map(emb => `
                            <option value="${emb.idembarque}">
                                #${emb.idembarque} - ${emb.rota || 'Sem rota'} (${emb.total_pedidos || 0} pedidos)
                                ${emb.placa ? ' - ' + emb.placa : ''}
                            </option>
                        `).join('');

                        const { value: erpId } = await Swal.fire({
                            title: 'Adicionar Embarque do ERP',
                            html: `
                                <div class="text-left">
                                    <label class="form-label">Selecione um embarque ERP</label>
                                    <select id="select-erp-embarque" class="form-select">${options}</select>
                                    <small class="text-muted">Todos os pedidos deste embarque serão adicionados ao grupo</small>
                                </div>
                                `,
                                showCancelButton: true,
                                confirmButtonText: 'Adicionar',
                                confirmButtonColor: '#3b82f6',
                                preConfirm: () => {
                                    const val = document.getElementById('select-erp-embarque').value;
                                    if (!val) {
                                        Swal.showValidationMessage('Selecione um embarque');
                                        return false;
                                    }
                                    return parseInt(val);
                                }
                            });

                        if (!erpId) return;

                        const respAdd = await fetch(`/v1/frota/embarques/${listaIds[0]}/adicionar-embarque-erp`, {
                            method: 'POST',
                            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                            body: JSON.stringify({ erp_embarque_id: erpId })
                        });
                        const resultAdd = await respAdd.json();

                        if (resultAdd.success) {
                            Swal.fire('Sucesso', resultAdd.message, 'success');
                            Swal.close();
                            await verDetalhes(listaIds[0]);
                            carregarEmbarques();
                        } else {
                            Swal.fire('Erro', resultAdd.error || 'Falha ao adicionar', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Falha ao adicionar embarque', 'error');
                    }
                });

                document.getElementById('btn-adicionar-pedidos')?.addEventListener('click', async () => {
                    const { value: resultado } = await Swal.fire({
                        title: 'Adicionar Pedidos',
                        html: `
                            <div class="text-left">
                                <div class="mb-3">
                                    <label class="form-label">Buscar pedidos (por número ou cliente)</label>
                                    <input type="text" id="busca-pedidos" class="form-control" placeholder="Digite o número do pedido ou cliente...">
                                </div>
                                <div id="resultado-pedidos" style="max-height: 250px; overflow-y: auto; border: 1px solid var(--nutri-border); border-radius: 8px; padding: 8px;">
                                    <p class="text-muted text-center">Digite para buscar</p>
                                </div>
                                <div class="mt-3">
                                    <label class="form-label">ID do Embarque ERP (opcional)</label>
                                    <input type="number" id="erp-embarque-id-pedidos" class="form-control" placeholder="Ex: 9170" value="0">
                                    <small class="text-muted">Preencha se os pedidos vierem de um novo embarque ERP (para agrupar corretamente)</small>
                                </div>
                                <small class="text-muted">Selecione um ou mais pedidos para adicionar ao grupo</small>
                            </div>
                            `,
                            showCancelButton: true,
                            confirmButtonText: 'Adicionar Selecionados',
                            confirmButtonColor: '#10b981',
                            preConfirm: () => {
                                const checkboxes = document.querySelectorAll('#resultado-pedidos input[type="checkbox"]:checked');
                                if (checkboxes.length === 0) {
                                    Swal.showValidationMessage('Selecione pelo menos um pedido');
                                    return false;
                                }
                                const erpId = parseInt(document.getElementById('erp-embarque-id-pedidos').value) || 0;
                                return {
                                    pedidos: Array.from(checkboxes).map(cb => parseInt(cb.value)),
                                    erp_embarque_id: erpId
                                };
                            },
                            didOpen: () => {
                                const input = document.getElementById('busca-pedidos');
                                const resultados = document.getElementById('resultado-pedidos');

                                input.addEventListener('input', debounce(async () => {
                                    const termo = input.value.trim();
                                    if (termo.length < 2) {
                                        resultados.innerHTML = '<p class="text-muted text-center">Digite pelo menos 2 caracteres</p>';
                                        return;
                                    }
                                    try {
                                        const resp = await fetch(`/v1/frota/importar/buscar-pedidos?q=${encodeURIComponent(termo)}`, {
                                            headers: { 'Authorization': 'Bearer ' + token }
                                        });
                                        const dados = await resp.json();
                                        if (dados.success && dados.data.length > 0) {
                                            const html = dados.data.map(p => `
                                            <div class="flex items-center gap-2 p-2 border-b hover:bg-slate-50" style="display:flex; align-items:center; gap:8px; padding:6px 8px; border-bottom:1px solid var(--nutri-border);">
                                                <input type="checkbox" value="${p.idpedido}" style="width:16px; height:16px;">
                                                <span style="font-weight:600;">#${p.idpedido}</span>
                                                <span style="flex:1;">${p.cliente_nome || p.cliente_razao || 'Cliente'}</span>
                                                <span style="color:#10b981; font-weight:600;">${formatarMoeda(p.valortotalpedido)}</span>
                                            </div>
                                            `).join('');
                                            resultados.innerHTML = html || '<p class="text-muted text-center">Nenhum pedido encontrado</p>';
                                        } else {
                                            resultados.innerHTML = '<p class="text-muted text-center">Nenhum pedido encontrado</p>';
                                        }
                                    } catch (error) {
                                        resultados.innerHTML = '<p class="text-red-500">Erro ao buscar pedidos</p>';
                                    }
                                }, 400));
                            }
                        });

if (resultado && resultado.pedidos.length > 0) {
    try {
        const respAdd = await fetch(`/v1/frota/embarques/${listaIds[0]}/adicionar-pedidos`, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify({
                pedidos_ids: resultado.pedidos,
                erp_embarque_id: resultado.erp_embarque_id
            })
        });
        const resultAdd = await respAdd.json();

        if (resultAdd.success) {
            Swal.fire('Sucesso', resultAdd.message, 'success');
            Swal.close();
            setTimeout(() => {
                abrirModalEditarGrupo(listaIds);
                carregarEmbarques();
            }, 300);
        } else {
            Swal.fire('Erro', resultAdd.error || 'Falha ao adicionar', 'error');
        }
    } catch (error) {
        Swal.fire('Erro', 'Falha ao adicionar pedidos', 'error');
    }
}
});
}
});
} catch (error) {
    Swal.fire('Erro', 'Falha ao carregar dados para edição', 'error');
}
}

// ======================================================================
// REMOVER ENTREGA DE UM GRUPO
// ======================================================================
async function removerEntregaGrupo(ids) {
    let listaIds = [];
    if (Array.isArray(ids)) {
        listaIds = ids;
    } else if (typeof ids === 'string') {
        listaIds = ids.split(',').map(Number);
    } else if (typeof ids === 'number') {
        listaIds = [ids];
    }
    listaIds = listaIds.filter(id => id && !isNaN(id));

    if (listaIds.length === 0) {
        mostrarNotificacao('Nenhum ID válido', 'error');
        return;
    }

    const token = getAuthToken();
    if (!token) return;

    try {
        let todasEntregas = [];
        for (const id of listaIds) {
            const resp = await fetch(`/v1/frota/embarques/${id}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await resp.json();
            if (data.success && data.data.entregas) {
                todasEntregas = todasEntregas.concat(data.data.entregas.map(e => ({
                    ...e,
                    embarque_id: id
                })));
            }
        }

        if (todasEntregas.length === 0) {
            Swal.fire('Aviso', 'Nenhuma entrega encontrada neste grupo', 'info');
            return;
        }

        const options = todasEntregas.map(e => `
            <option value="${e.id}|${e.embarque_id}">
                #${e.id} - ${e.cliente_nome || 'Cliente'} - 
                ${formatarMoeda(e.valor || 0)} - 
                Embarque #${e.embarque_id}
            </option>
        `).join('');

        const { value: selecao } = await Swal.fire({
            title: 'Selecione a entrega para remover',
            html: `<select id="select-entrega-remover" class="form-select">${options}</select>`,
            showCancelButton: true,
            confirmButtonText: 'Remover',
            confirmButtonColor: '#dc2626',
            preConfirm: () => {
                const val = document.getElementById('select-entrega-remover').value;
                if (!val) {
                    Swal.showValidationMessage('Selecione uma entrega');
                    return false;
                }
                const [entregaId, embId] = val.split('|').map(Number);
                return { entregaId, embId };
            }
        });

        if (!selecao) return;
        const { entregaId, embId } = selecao;

        const confirm = await Swal.fire({
            title: 'Confirmar remoção',
            text: `Remover entrega #${entregaId} do embarque #${embId}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Sim, remover'
        });
        if (!confirm.isConfirmed) return;

        const respDel = await fetch(`/v1/frota/embarques/${embId}/entregas/${entregaId}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const resultDel = await respDel.json();
        if (resultDel.success) {
            Swal.fire('Removido', 'Entrega removida com sucesso', 'success');
            carregarEmbarques();
        } else {
            Swal.fire('Erro', resultDel.error || 'Falha ao remover', 'error');
        }

    } catch (error) {
        Swal.fire('Erro', 'Falha ao remover entrega', 'error');
    }
}

// ======================================================================
// EXPORTAÇÕES GLOBAIS
// ======================================================================
window.verItensCheckout = verItensCheckout;
window.abrirModalEditarGrupo = abrirModalEditarGrupo;
window.removerEntregaGrupo = removerEntregaGrupo;
window.carregarEmbarques = carregarEmbarques;
window.mudarPagina = mudarPagina;
window.verDetalhes = verDetalhes;
window.verDetalhesGrupo = verDetalhesGrupo;
window.iniciarEmbarque = iniciarEmbarque;
window.iniciarGrupo = iniciarGrupo;
window.finalizarEmbarque = finalizarEmbarque;
window.finalizarGrupo = finalizarGrupo;
window.cancelarEmbarque = cancelarEmbarque;
window.cancelarGrupo = cancelarGrupo;
window.otimizarRota = otimizarRota;
window.criarRotasSelecionadas = criarRotasSelecionadas;
window.fecharModal = fecharModal;
window.selecionarEntregaNoMapa = selecionarEntregaNoMapa;
window.centralizarNoMapa = centralizarNoMapa;
window.toggleTheme = toggleTheme;
window.mostrarNotificacao = mostrarNotificacao;
window.exportarRota = exportarRota;
window.abrirGaleriaFotos = abrirGaleriaFotos;
window.abrirZoomFoto = abrirZoomFoto;
window.fecharZoom = fecharZoom;
</script>

<?php
require_once __DIR__ . '/../../estrutura/footer.php';
?>