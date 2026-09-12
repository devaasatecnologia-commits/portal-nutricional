<?php
// ======================================================================
// APP DO MOTORISTA - COM AUTENTICAÇÃO, PERFIL, LOGOUT E DRAWER
// ======================================================================

$pageTitle = 'Rota do Motorista | Nutricional Rotas';
$version = time();
$appBase = (strpos($_SERVER['REQUEST_URI'] ?? '', '/API/') === 0) ? '/API' : '';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$usuarioLogado = $_SESSION['uid'] ?? $_SESSION['idusuario'] ?? null;

if (!$usuarioLogado) {
    $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '/portal/modules/frota/motorista-offline.php');
    header('Location: /portal/login.php?redirect=' . $redirect);
    exit;
}

$motoristaVinculado = (int)($_SESSION['motorista_id'] ?? 0);
$permissoes = $_SESSION['permissoes'] ?? [];
$isAdmin = !empty($_SESSION['is_admin'])
        || in_array('admin', $permissoes, true)
        || in_array('frota', $permissoes, true)
        || in_array('gestao-cargas', $permissoes, true);

$motoristaId = 0;
if ($isAdmin && isset($_GET['motorista_id'])) {
    $motoristaId = (int)$_GET['motorista_id'];
} else {
    $motoristaId = $motoristaVinculado;
}

if (!$isAdmin && $motoristaId <= 0) {
    header('Location: /portal/?erro=sem_vinculo_motorista');
    exit;
}

$manifestUrl = $appBase . '/portal/modules/frota/manifest-motorista.json?v=' . $version;
$iconBase    = $appBase . '/portal/modules/frota/assets/icons';

$extraCss = '
<link rel="manifest" href="' . $manifestUrl . '">
<meta name="theme-color" content="#17342c">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Nutricional Rotas">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" sizes="180x180" href="' . $iconBase . '/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="' . $iconBase . '/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="' . $iconBase . '/favicon-16x16.png">
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">
<link rel="stylesheet" href="' . $appBase . '/portal/modules/frota/assets/motorista-offline.css?v=' . $version . '">';

$extraJs = '
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script src="' . $appBase . '/portal/assets/js/config.js?v=' . $version . '"></script>
<script src="' . $appBase . '/portal/modules/frota/assets/frota.js?v=' . $version . '"></script>
<script src="' . $appBase . '/portal/modules/frota/assets/motorista-offline.js?v=' . $version . '"></script>';

require_once __DIR__ . '/../../estrutura/header.php';
?>
<main class="motorista-app" data-motorista-id="<?= (int)$motoristaId ?>">
    <!-- SELECTOR DE MOTORISTA (SÓ PARA ADMIN/GESTOR) -->
    <section class="driver-selector-card" id="driver-selector-card" <?= $isAdmin ? '' : 'hidden' ?>>
        <div class="driver-selector-inner">
            <div>
                <span class="eyebrow"><i class="fa-solid fa-id-card"></i> Identificação do Motorista</span>
                <h3 id="driver-current-name">Selecione um motorista para visualizar a rota</h3>
            </div>
            <div class="driver-selector-controls">
                <select id="driver-select-input" class="driver-select-input">
                    <option value="">Carregando motoristas...</option>
                </select>
                <button type="button" id="btn-confirm-driver" class="driver-confirm-btn">Entrar na Rota</button>
            </div>
        </div>
    </section>

    <header class="motorista-header">
        <div class="motorista-header-info">
            <span class="eyebrow"><i class="fa-solid fa-route"></i> Rota do dia</span>
            <h1>Minhas entregas</h1>
            <p id="motorista-status">Preparando dados para uso offline</p>
        </div>
        <div class="header-right-actions">
            <div class="connection-state" id="connection-state" aria-live="polite">
                <span class="connection-dot"></span>
                <span>Online</span>
            </div>
            <button type="button" class="driver-menu-btn" id="driver-menu-btn" aria-label="Abrir menu">
                <img id="driver-avatar-mini" src="" alt="" hidden>
                <span id="driver-avatar-iniciais">?</span>
            </button>
        </div>
    </header>

    <section class="truck-card" id="truck-card" hidden aria-label="Meu veículo">
        <div class="truck-card-icon"><i class="fa-solid fa-truck"></i></div>
        <div class="truck-card-body">
            <span class="eyebrow">Meu veículo</span>
            <h3 id="truck-card-placa">-</h3>
            <p id="truck-card-modelo">-</p>
        </div>
        <div class="truck-card-tracking" id="truck-card-tracking">
            <span class="truck-tracking-dot" id="truck-tracking-dot"></span>
            <span id="truck-tracking-label">Rastreio indisponível</span>
        </div>
    </section>

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
        <div class="next-stop-actions-group">
            <button type="button" class="next-stop-nav-btn waze-btn" id="next-stop-waze" title="Navegar com Waze">
                <i class="fa-brands fa-waze"></i> Waze
            </button>
            <button type="button" class="next-stop-nav-btn gmaps-btn" id="next-stop-gmaps" title="Navegar com Google Maps">
                <i class="fa-solid fa-map-location-dot"></i> Maps
            </button>
            <button type="button" class="next-stop-action" id="next-stop-action">Cheguei</button>
        </div>
    </section>

    <div class="offline-notice" id="offline-notice" hidden>
        <div class="offline-notice-text">
            <i class="fa-solid fa-wifi-slash"></i>
            <span>Sem conexão. As ações ficam salvas neste aparelho e serão sincronizadas quando a internet voltar.</span>
        </div>
        <button type="button" id="btn-sync-now" class="btn-sync-now" hidden>
            <i class="fa-solid fa-rotate"></i> Sincronizar Agora
        </button>
    </div>

    <div class="route-conflict" id="route-conflict" hidden>
        <strong><i class="fa-solid fa-triangle-exclamation"></i> Rota atualizada pelo gestor</strong>
        <span>A ordenação feita offline não foi aplicada para evitar sobrescrever a versão mais recente.</span>
        <div>
            <button type="button" id="route-conflict-refresh">Atualizar rota</button>
            <button type="button" id="route-conflict-discard">Descartar ordenação local</button>
        </div>
    </div>

    <div class="driver-alert" id="driver-alert" hidden></div>

    <section class="route-map-wrap" id="route-map-wrap" hidden>
        <div class="route-map-head">
            <span><i class="fa-solid fa-map"></i> Mapa da rota</span>
            <span class="route-map-hint" id="route-map-hint">Sua posição e o caminhão</span>
        </div>
        <div id="route-map" class="route-map"></div>
    </section>

    <div class="route-map-offline" id="route-map-offline" hidden>
        <i class="fa-solid fa-signal"></i> Sem conexão para exibir o mapa — mostrando distância estimada de cada parada.
    </div>

    <section class="route-tools" aria-label="Ferramentas da rota">
        <button type="button" id="btn-refresh-route" class="route-tool">
            <i class="fa-solid fa-rotate-right"></i> Atualizar rota
        </button>
        <span id="gps-status" class="gps-status"><i class="fa-solid fa-location-crosshairs"></i> GPS aguardando</span>
    </section>

    <section class="delivery-list" id="delivery-list" aria-live="polite">
        <div class="empty-state">Carregando sua rota...</div>
    </section>

    <div class="driver-modal" id="checkout-modal" hidden>
        <form class="driver-modal-card" id="checkout-form">
            <div class="driver-modal-head">
                <div>
                    <span class="eyebrow"><i class="fa-solid fa-certificate"></i> Comprovante digital</span>
                    <h2>Finalizar entrega</h2>
                </div>
                <button type="button" class="modal-close" id="checkout-cancel"><i class="fa-solid fa-xmark"></i></button>
            </div>

            <label>
                <span>Nome de quem recebeu *</span>
                <input id="receiver-name" required maxlength="120" autocomplete="name" placeholder="Ex: João da Silva">
            </label>

            <div id="checklist-fields"></div>

            <label>
                <span>Foto do romaneio assinado *</span>
                <input id="romaneio-photo" type="file" accept="image/*" capture="environment" required>
                <div id="romaneio-preview" class="photo-preview-container" hidden>
                    <img id="romaneio-preview-img" src="" alt="Romaneio">
                </div>
            </label>

            <div>
                <span class="field-label">Assinatura do recebedor *</span>
                <canvas id="signature-pad" width="560" height="180"></canvas>
                <button type="button" class="signature-clear" id="signature-clear">
                    <i class="fa-solid fa-eraser"></i> Limpar assinatura
                </button>
            </div>

            <button class="checkout-submit" type="submit">
                <i class="fa-solid fa-check"></i> Salvar entrega no aparelho
            </button>
        </form>
    </div>

    <!-- ============ DRAWER LATERAL (MENU DO MOTORISTA) ============ -->
    <aside class="driver-drawer" id="driver-drawer" hidden>
        <div class="drawer-header">
            <div class="drawer-avatar-wrap">
                <img id="drawer-avatar" src="" alt="" hidden>
                <span id="drawer-avatar-iniciais">?</span>
            </div>
            <div class="drawer-user-info">
                <strong id="drawer-nome">-</strong>
                <span id="drawer-cargo">Motorista</span>
            </div>
            <button type="button" class="drawer-close" id="drawer-close" aria-label="Fechar">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <nav class="drawer-menu">
            <button type="button" data-drawer-action="perfil">
                <i class="fa-solid fa-user"></i>
                <span>Meu perfil</span>
            </button>
            <button type="button" data-drawer-action="foto">
                <i class="fa-solid fa-camera"></i>
                <span>Alterar foto</span>
            </button>
            <button type="button" data-drawer-action="tema">
                <i class="fa-solid fa-moon" id="drawer-tema-icon"></i>
                <span id="drawer-tema-label">Tema escuro</span>
            </button>
            <button type="button" data-drawer-action="sync">
                <i class="fa-solid fa-rotate"></i>
                <span>Sincronizar agora</span>
                <span class="drawer-badge" id="drawer-fila-badge" hidden>0</span>
            </button>
            <hr class="drawer-sep">
            <button type="button" class="danger" data-drawer-action="logout">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sair</span>
            </button>
        </nav>

        <div class="drawer-footer">
            <span>Nutricional Rotas</span>
            <small>v<?= date('Ymd') ?></small>
        </div>
    </aside>
    <div class="driver-drawer-backdrop" id="driver-drawer-backdrop" hidden></div>

    <!-- ============ MODAL DE PERFIL ============ -->
    <div class="driver-modal" id="perfil-modal" hidden>
        <div class="driver-modal-card perfil-modal-card">
            <div class="driver-modal-head">
                <div>
                    <span class="eyebrow"><i class="fa-solid fa-user"></i> Meu perfil</span>
                    <h2 id="perfil-modal-nome">-</h2>
                </div>
                <button type="button" class="modal-close" id="perfil-modal-close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="perfil-modal-body">
                <div class="perfil-row">
                    <span class="perfil-label">Usuário</span>
                    <strong id="perfil-username">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Email</span>
                    <strong id="perfil-email">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Telefone</span>
                    <strong id="perfil-fone">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Endereço</span>
                    <strong id="perfil-endereco">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Cidade/UF</span>
                    <strong id="perfil-cidade">-</strong>
                </div>
                <div class="perfil-row">
                    <span class="perfil-label">Cargo</span>
                    <strong id="perfil-cargo">-</strong>
                </div>
            </div>

            <div class="perfil-modal-foot">
                <button type="button" class="btn-perfil-fechar" id="perfil-modal-fechar-btn">Fechar</button>
            </div>
        </div>
    </div>

    <!-- INPUT DE FOTO (oculto) -->
    <input type="file" id="input-foto-perfil" accept="image/*" capture="user" hidden>
</main>

<script>
window.MOTORISTA_ID_INICIAL   = <?= (int)$motoristaId ?>;
window.MOTORISTA_ID_VINCULADO = <?= (int)$motoristaVinculado ?>;
window.IS_ADMIN_APP           = <?= $isAdmin ? 'true' : 'false' ?>;
window.MOTORISTA_NOME_SESSAO  = <?= json_encode($_SESSION['uname'] ?? $_SESSION['username'] ?? 'Motorista') ?>;
</script>

<?= $extraJs ?>