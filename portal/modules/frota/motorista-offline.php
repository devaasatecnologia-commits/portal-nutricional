<?php
$pageTitle = 'Rota do Motorista | Nutricional';
$version = time();
$appBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') === 0) ? '/API' : '';
$motoristaId = (int)($_GET['motorista_id'] ?? $_SESSION['motorista_id'] ?? 0);
$extraCss = '<link rel="manifest" href="' . $appBase . '/portal/modules/frota/manifest-motorista.json"><link rel="stylesheet" href="' . $appBase . '/portal/modules/frota/assets/motorista-offline.css?v=' . $version . '">';
$extraJs = '<script src="' . $appBase . '/portal/modules/frota/assets/motorista-offline.js?v=' . $version . '"></script>';
require_once __DIR__ . '/../../estrutura/header.php';
?>
<main class="motorista-app" data-motorista-id="<?= $motoristaId ?>">
    <header class="motorista-header">
        <div><span class="eyebrow">Rota do dia</span><h1>Minhas entregas</h1><p id="motorista-status">Preparando dados para uso offline</p></div>
        <div class="connection-state" id="connection-state" aria-live="polite"><span class="connection-dot"></span><span>Online</span></div>
        <button type="button" class="driver-theme-toggle" id="driver-theme-toggle" aria-label="Alternar tema">Tema escuro</button>
    </header>
    <section class="route-summary" aria-label="Resumo da rota">
        <div><strong id="total-entregas">0</strong><span>entregas</span></div>
        <div><strong id="entregas-concluidas">0</strong><span>concluídas</span></div>
        <div><strong id="fila-pendente">0</strong><span>pendentes</span></div>
    </section>
    <div class="offline-notice" id="offline-notice" hidden>Sem conexão. As ações ficam salvas neste aparelho e serão enviadas automaticamente quando a internet voltar.</div>
    <div class="driver-alert" id="driver-alert" hidden></div>
    <section class="route-tools" aria-label="Ferramentas da rota">
        <button type="button" id="btn-refresh-route" class="route-tool">Atualizar rota</button>
        <span id="gps-status" class="gps-status">GPS aguardando</span>
    </section>
    <section class="delivery-list" id="delivery-list" aria-live="polite"><div class="empty-state">Carregando sua rota...</div></section>
</main>
<script>window.MOTORISTA_ID_INICIAL = <?= $motoristaId ?>;</script>
<?= $extraJs ?>
