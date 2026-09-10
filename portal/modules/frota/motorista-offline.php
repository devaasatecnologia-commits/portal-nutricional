<?php
$pageTitle = 'Rota do Motorista | Nutricional';
$version = time();
$appBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') === 0) ? '/API' : '';
$motoristaId = (int)($_GET['motorista_id'] ?? $_SESSION['motorista_id'] ?? 0);
$extraCss = '<link rel="manifest" href="' . $appBase . '/portal/modules/frota/manifest-motorista.json">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="' . $appBase . '/portal/modules/frota/assets/motorista-offline.css?v=' . $version . '">';
$extraJs = '<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script src="' . $appBase . '/portal/modules/frota/assets/motorista-offline.js?v=' . $version . '"></script>';
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
    <section class="driver-progress-card" aria-label="Progresso da rota">
        <div class="driver-progress-head">
            <div><span class="eyebrow">Progresso da rota</span><strong id="route-progress-label">0% concluído</strong></div>
            <span id="route-progress-count">0 de 0</span>
        </div>
        <div class="driver-progress-track"><span id="route-progress-bar"></span></div>
    </section>
    <section class="next-stop-card" id="next-stop-card" hidden aria-label="Próxima parada">
        <div class="next-stop-icon"><i class="fa-solid fa-location-dot"></i></div>
        <div class="next-stop-content">
            <span class="eyebrow">Próxima parada</span>
            <h2 id="next-stop-name">-</h2>
            <p id="next-stop-address">-</p>
            <span class="next-stop-distance" id="next-stop-distance"></span>
        </div>
        <button type="button" class="next-stop-action" id="next-stop-action">Cheguei</button>
    </section>
    <div class="offline-notice" id="offline-notice" hidden>Sem conexão. As ações ficam salvas neste aparelho e serão enviadas automaticamente quando a internet voltar.</div>
    <div class="route-conflict" id="route-conflict" hidden><strong>Rota atualizada pelo gestor</strong><span>A ordenação feita offline não foi aplicada para evitar sobrescrever a versão mais recente.</span><div><button type="button" id="route-conflict-refresh">Atualizar rota</button><button type="button" id="route-conflict-discard">Descartar ordenação local</button></div></div>
    <div class="driver-alert" id="driver-alert" hidden></div>
    <section class="route-map-wrap" id="route-map-wrap" hidden>
        <div class="route-map-head">
            <span>Mapa da rota</span>
            <span class="route-map-hint" id="route-map-hint">Sua posição e o caminhão</span>
        </div>
        <div id="route-map" class="route-map"></div>
    </section>
    <div class="route-map-offline" id="route-map-offline" hidden>Sem conexão para exibir o mapa — mostrando distância estimada de cada parada.</div>
    <section class="route-tools" aria-label="Ferramentas da rota">
        <button type="button" id="btn-refresh-route" class="route-tool">Atualizar rota</button>
        <span id="gps-status" class="gps-status"><i class="fa-solid fa-location-crosshairs"></i> GPS aguardando</span>
    </section>
    <section class="delivery-list" id="delivery-list" aria-live="polite"><div class="empty-state">Carregando sua rota...</div></section>
    <div class="driver-modal" id="checkout-modal" hidden>
        <form class="driver-modal-card" id="checkout-form">
            <div class="driver-modal-head"><div><span class="eyebrow">Comprovante digital</span><h2>Finalizar entrega</h2></div><button type="button" class="modal-close" id="checkout-cancel">Fechar</button></div>
            <label>Nome de quem recebeu<input id="receiver-name" required maxlength="120" autocomplete="name"></label>
            <div id="checklist-fields"></div>
            <label>Foto do romaneio assinado<input id="romaneio-photo" type="file" accept="image/*" capture="environment" required></label>
            <div><span class="field-label">Assinatura do recebedor</span><canvas id="signature-pad" width="560" height="180"></canvas><button type="button" class="signature-clear" id="signature-clear">Limpar assinatura</button></div>
            <button class="checkout-submit" type="submit">Salvar entrega no aparelho</button>
        </form>
    </div>
</main>
<script>window.MOTORISTA_ID_INICIAL = <?= $motoristaId ?>;</script>
<?= $extraJs ?>
