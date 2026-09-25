(function () {
  'use strict';
  const app = document.querySelector('.motorista-app');
  if (!app) return;

  const safeParse = (value, fallback = null) => { try { return JSON.parse(value); } catch { return fallback; } };

  // ================================================================
  // AUTORIZAÇÃO VIA PHP
  // ================================================================
  const isAdminApp = window.IS_ADMIN_APP === true;
  const motoristaVinculado = Number(window.MOTORISTA_ID_VINCULADO || 0);
  const podeTrocarMotorista = isAdminApp;

  // ================================================================
  // MODO TREINAMENTO — ativado via ?treino=1 na URL (PHP injeta em window)
  // NÃO bloqueia por distância e NÃO grava lat/lng fora do raio
  // ================================================================
  const isTrainingMode = window.MODO_TREINAMENTO === true;

  let motoristaId = Number(window.MOTORISTA_ID_INICIAL || app.dataset.motoristaId || 0);
  if (!podeTrocarMotorista && motoristaVinculado > 0) motoristaId = motoristaVinculado;

  let cacheKey = `frota.motorista.${motoristaId}.entregas`;
  let queueKey = `frota.motorista.${motoristaId}.offline.queue`;
  let positionKey = `frota.motorista.${motoristaId}.posicao`;
  let truckKey = `frota.motorista.${motoristaId}.truck`;
  const apiBase = `${window.API_URL || '/v1'}/frota`;
  const apiRoot = (window.API_URL || '/v1').replace(/\/frota$/, '');

  let entregas = [];
  let rotaVersion = null;
  let offlineQueue = [];
  let queueReady;
  let driverPosition = null;
  let truckPosition = null;
  let routeMap = null;
  let routeMarkers = [];
  let markerIndex = new Map();
  let truckMarker = null;
  let driverMarker = null;
  let routeSourceId = 'driver-route-source';
  let watchId = null;
  let lastFitBounds = 0;
  let painelMotoristas = [];

  // 🔥 Pacote 1.5 — filtro de chips
  let filtroStatusAtual = 'pendente';

  // 🔥 Pacote 3 — M3: rastreador da próxima parada
  let ultimaProximaId = null;
  let primeiraRenderizacao = true;
  let ultimoToastProximaEm = 0;
  const TOAST_PROXIMA_INTERVALO_MS = 5000;

  // 🔥 Pacote 3 — M4: timer do chip de distância
  let chipDistanciaTimer = null;
  const CHIP_DISTANCIA_INTERVALO_MS = 30 * 1000;
  const VELOCIDADE_MEDIA_KMH = 40;

  // 🔥 Pacote 2 — cache do painel
  const PAINEL_CACHE_TTL = 5 * 60 * 1000;

  const $ = (id) => document.getElementById(id);
  const online = () => navigator.onLine;
  const authHeaders = () => {
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    const headers = token
      ? { Authorization: `Bearer ${token}`, Accept: 'application/json' }
      : { Accept: 'application/json' };

    // Modo treinamento: avisa o backend para relaxar validação de distância
    if (isTrainingMode) headers['X-Training-Mode'] = '1';

    return headers;
  };

  // ================================================================
  // PERFIL DO MOTORISTA
  // ================================================================
  let perfilDados = safeParse(localStorage.getItem(`frota.motorista.${motoristaId}.perfil`) || 'null', null);
  let perfilPendente = safeParse(localStorage.getItem(`frota.motorista.${motoristaId}.perfil.pendente`) || 'null', null);

  function urlFotoPerfil(fotoPath) {
    if (!fotoPath) return null;
    if (fotoPath.startsWith('http')) return fotoPath;
    return `https://api.nutricionalbr.com/${fotoPath}`;
  }

  function iniciais(nome) {
    return String(nome || '?').trim().split(/\s+/).slice(0, 2).map(p => p[0] || '').join('').toUpperCase() || '?';
  }

  function renderizarAvatar() {
    const nome = perfilDados?.fantasia || window.MOTORISTA_NOME_SESSAO || 'Motorista';
    const fotoPendente = perfilPendente?.fotoDataUrl || null;
    const foto = fotoPendente || urlFotoPerfil(perfilDados?.foto_perfil);
    const ini = iniciais(nome);

    const imgMini = $('driver-avatar-mini');
    const spanMini = $('driver-avatar-iniciais');
    if (imgMini && spanMini) {
      if (foto) {
        imgMini.src = foto;
        imgMini.hidden = false;
        spanMini.hidden = true;
      } else {
        imgMini.hidden = true;
        spanMini.hidden = false;
        spanMini.textContent = ini;
      }
    }

    const imgDrawer = $('drawer-avatar');
    const spanDrawer = $('drawer-avatar-iniciais');
    if (imgDrawer && spanDrawer) {
      if (foto) {
        imgDrawer.src = foto;
        imgDrawer.hidden = false;
        spanDrawer.hidden = true;
      } else {
        imgDrawer.hidden = true;
        spanDrawer.hidden = false;
        spanDrawer.textContent = ini;
      }
    }

    if ($('drawer-nome')) $('drawer-nome').textContent = nome;
    if ($('drawer-cargo')) $('drawer-cargo').textContent = perfilDados?.cargo || 'Motorista';
  }

  async function carregarPerfil() {
    if (perfilDados) renderizarAvatar();
    if (!online()) return;
    try {
      const endpoint = isAdminApp && motoristaId
        ? `${apiBase}/motoristas/${motoristaId}`
        : `${apiRoot}/perfil/dados`;
      const resp = await fetch(endpoint, {
        headers: authHeaders(),
        credentials: 'include'
      });
      if (!resp.ok) return;
      const payload = await resp.json();
      const dados = isAdminApp ? payload.data : payload;
      if (dados && !dados.error) {
        if (isAdminApp) dados.fantasia = dados.nome;
        perfilDados = dados;
        localStorage.setItem(`frota.motorista.${motoristaId}.perfil`, JSON.stringify(dados));
        renderizarAvatar();
      }
    } catch (e) {
      console.warn('Perfil offline:', e);
    }
  }

  // ================================================================
  // DRAWER
  // ================================================================
  function abrirDrawer() {
    const drawer = $('driver-drawer');
    const backdrop = $('driver-drawer-backdrop');
    if (drawer) drawer.hidden = false;
    if (backdrop) backdrop.hidden = false;
    document.body.classList.add('drawer-open');
    atualizarDrawerFila();
    atualizarDrawerTema();
  }

  function fecharDrawer() {
    const drawer = $('driver-drawer');
    const backdrop = $('driver-drawer-backdrop');
    if (drawer) drawer.hidden = true;
    if (backdrop) backdrop.hidden = true;
    document.body.classList.remove('drawer-open');
  }

  function atualizarDrawerFila() {
    const badge = $('drawer-fila-badge');
    if (!badge) return;
    const n = getQueue().length;
    if (n > 0) { badge.textContent = n > 99 ? '99+' : n; badge.hidden = false; }
    else badge.hidden = true;
  }

  function atualizarDrawerTema() {
    const dark = document.documentElement.dataset.driverTheme === 'dark';
    if ($('drawer-tema-label')) $('drawer-tema-label').textContent = dark ? 'Tema claro' : 'Tema escuro';
    if ($('drawer-tema-icon')) $('drawer-tema-icon').className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
  }

  // ================================================================
  // MODAL DE PERFIL
  // ================================================================
  function abrirPerfilModal() {
    const dados = perfilDados || {};
    if ($('perfil-modal-nome')) $('perfil-modal-nome').textContent = dados.fantasia || window.MOTORISTA_NOME_SESSAO || 'Motorista';
    if ($('perfil-username')) $('perfil-username').textContent = dados.username || '-';
    if ($('perfil-email')) $('perfil-email').textContent = dados.email || '-';
    if ($('perfil-fone')) $('perfil-fone').textContent = dados.fone || '-';
    if ($('perfil-endereco')) {
      $('perfil-endereco').textContent = [dados.endereco, dados.bairro].filter(Boolean).join(', ') || '-';
    }
    if ($('perfil-cidade')) {
      $('perfil-cidade').textContent = [dados.cidade, dados.uf].filter(Boolean).join('/') || '-';
    }
    if ($('perfil-cargo')) $('perfil-cargo').textContent = dados.cargo || '-';
    const modal = $('perfil-modal');
    if (modal) modal.hidden = false;
    fecharDrawer();
  }

  function fecharPerfilModal() {
    const modal = $('perfil-modal');
    if (modal) modal.hidden = true;
  }

  // ================================================================
  // TROCA DE FOTO (com fila offline)
  // ================================================================
  function abrirSeletorFoto() {
    if (isAdminApp) return;
    const input = $('input-foto-perfil');
    if (input) input.click();
    fecharDrawer();
  }

  async function processarFotoPerfil(file) {
    if (!file) return;
    if (file.size > 8 * 1024 * 1024) {
      Swal.fire({ icon: 'warning', title: 'Foto muito grande', text: 'Use uma imagem de até 8MB.', confirmButtonText: 'OK' });
      return;
    }

    const blobRedimensionado = await new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          const size = Math.min(img.width, img.height);
          const sx = (img.width - size) / 2;
          const sy = (img.height - size) / 2;
          canvas.width = 512;
          canvas.height = 512;
          canvas.getContext('2d').drawImage(img, sx, sy, size, size, 0, 0, 512, 512);
          canvas.toBlob((b) => resolve(b), 'image/jpeg', 0.88);
        };
        img.onerror = () => resolve(null);
        img.src = e.target.result;
      };
      reader.onerror = () => resolve(null);
      reader.readAsDataURL(file);
    });

    if (!blobRedimensionado) {
      Swal.fire({ icon: 'error', title: 'Não foi possível processar a imagem', confirmButtonText: 'OK' });
      return;
    }

    const previewDataUrl = await new Promise((resolve) => {
      const r = new FileReader();
      r.onload = (e) => resolve(e.target.result);
      r.readAsDataURL(blobRedimensionado);
    });

    perfilPendente = { fotoDataUrl: previewDataUrl, criado_em: new Date().toISOString() };
    localStorage.setItem(`frota.motorista.${motoristaId}.perfil.pendente`, JSON.stringify(perfilPendente));
    renderizarAvatar();

    if (!online()) {
      Swal.fire({
        icon: 'info',
        title: 'Foto salva localmente',
        text: 'Sem conexão. A foto será enviada automaticamente quando a internet voltar.',
        confirmButtonText: 'OK'
      });
      registrarFotoParaEnvio(blobRedimensionado);
      return;
    }

    Swal.fire({ title: 'Enviando foto...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
      const form = new FormData();
      form.append('foto', blobRedimensionado, 'perfil.jpg');
      form.append('idusuario', perfilDados?.idusuario || 0);

      const resp = await fetch(`${apiRoot}/admin/upload-foto`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('authToken') || '') },
        credentials: 'include',
        body: form
      });
      const data = await resp.json();

      if (data.success && data.foto_url) {
        perfilDados = { ...perfilDados, foto_perfil: data.caminho || data.foto_url };
        localStorage.setItem(`frota.motorista.${motoristaId}.perfil`, JSON.stringify(perfilDados));
        perfilPendente = null;
        localStorage.removeItem(`frota.motorista.${motoristaId}.perfil.pendente`);
        renderizarAvatar();

        try {
          const ud = JSON.parse(localStorage.getItem('userData') || '{}');
          ud.foto_perfil = data.caminho || data.foto_url;
          localStorage.setItem('userData', JSON.stringify(ud));
        } catch {}

        Swal.fire({ icon: 'success', title: 'Foto atualizada!', timer: 1500, showConfirmButton: false });
      } else {
        Swal.fire({ icon: 'error', title: 'Erro ao enviar', text: data.error || 'Tente novamente.', confirmButtonText: 'OK' });
      }
    } catch (e) {
      Swal.fire({ icon: 'error', title: 'Erro de conexão', text: 'Não foi possível enviar.', confirmButtonText: 'OK' });
    }
  }

  function registrarFotoParaEnvio(blob) {
    const reader = new FileReader();
    reader.onload = () => {
      try {
        localStorage.setItem(`frota.motorista.${motoristaId}.foto.pendente`, reader.result);
      } catch (e) {
        console.warn('Não foi possível guardar foto pendente:', e);
      }
    };
    reader.readAsDataURL(blob);
  }

  async function enviarFotoPendente() {
    const dataUrl = localStorage.getItem(`frota.motorista.${motoristaId}.foto.pendente`);
    if (!dataUrl) return;

    try {
      const blob = await fetch(dataUrl).then(r => r.blob());
      const form = new FormData();
      form.append('foto', blob, 'perfil.jpg');
      form.append('idusuario', perfilDados?.idusuario || 0);

      const resp = await fetch(`${apiRoot}/admin/upload-foto`, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + (localStorage.getItem('authToken') || '') },
        credentials: 'include',
        body: form
      });
      const data = await resp.json();

      if (data.success && data.foto_url) {
        perfilDados = { ...perfilDados, foto_perfil: data.caminho || data.foto_url };
        localStorage.setItem(`frota.motorista.${motoristaId}.perfil`, JSON.stringify(perfilDados));
        perfilPendente = null;
        localStorage.removeItem(`frota.motorista.${motoristaId}.perfil.pendente`);
        localStorage.removeItem(`frota.motorista.${motoristaId}.foto.pendente`);
        renderizarAvatar();
      }
    } catch (e) {
      console.warn('Falha ao enviar foto pendente (tentará na próxima):', e);
    }
  }

  // ================================================================
  // LOGOUT
  // ================================================================
  async function fazerLogout() {
    const fila = getQueue();
    if (fila.length > 0) {
      const r = await Swal.fire({
        icon: 'warning',
        title: 'Ainda há ações pendentes',
        html: `Existem <b>${fila.length}</b> ações não sincronizadas.<br>Sair agora pode perder essas ações.`,
        showCancelButton: true,
        confirmButtonText: 'Sincronizar primeiro',
        cancelButtonText: 'Sair mesmo assim',
        confirmButtonColor: '#16845e',
        cancelButtonColor: '#c94b45'
      });
      if (r.isConfirmed) {
        await sincronizarFila();
        return;
      }
    }

    Swal.fire({ title: 'Saindo...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
      if (typeof window.logout === 'function') {
        await window.logout();
        return;
      }
    } catch (e) {
      console.warn('Falha no logout remoto, limpando local:', e);
    }

    localStorage.removeItem('authToken');
    localStorage.removeItem('userData');
    sessionStorage.clear();
    const appBase = window.location.pathname.startsWith('/API/') ? '/API' : '';
    window.location.href = appBase + '/portal/login.php';
  }

  // ================================================================
  // TEMA
  // ================================================================
  function aplicarTema() {
    const saved = localStorage.getItem('frota.motorista.theme');
    const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.driverTheme = dark ? 'dark' : 'light';
    atualizarDrawerTema();
  }
  function alternarTema() {
    localStorage.setItem('frota.motorista.theme', document.documentElement.dataset.driverTheme === 'dark' ? 'light' : 'dark');
    aplicarTema();
  }

  // ================================================================
  // FILA OFFLINE (IndexedDB)
  // ================================================================
  function abrirBancoOffline() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(`frota-motorista-${motoristaId}`, 1);
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains('fila')) {
          db.createObjectStore('fila', { keyPath: 'operation_id' });
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
  }
  // ================================================================
  // 🩹 CORRIGIDO 2026-09-25 (Pacote 1.5): #fila-pendente foi removido
  // do HTML no Pacote 1. Atualização agora é opcional (guarda).
  // ================================================================
  async function carregarFila() {
    try {
      const db = await abrirBancoOffline();
      offlineQueue = await new Promise((resolve, reject) => {
        const request = db.transaction('fila').objectStore('fila').getAll();
        request.onsuccess = () => resolve(request.result || []);
        request.onerror = () => reject(request.error);
      });
      const legacy = safeParse(localStorage.getItem(queueKey) || '[]', []);
      if (legacy.length) {
        offlineQueue = [...offlineQueue, ...legacy.filter((item) => !offlineQueue.some((saved) => saved.operation_id === item.operation_id))];
        await salvarFila(offlineQueue);
        localStorage.removeItem(queueKey);
      }
    } catch {
      offlineQueue = safeParse(localStorage.getItem(queueKey) || '[]', []);
    }

    const filaEl = $('fila-pendente');
    if (filaEl) filaEl.textContent = offlineQueue.length;

    atualizarDrawerFila();
    return offlineQueue;
  }
   function getQueue() { return offlineQueue; }

  // ================================================================
  // 🩹 CORRIGIDO 2026-09-25 (Pacote 1.5): #fila-pendente é opcional.
  // ================================================================
  async function salvarFila(queue) {
    offlineQueue = queue;

    const filaEl = $('fila-pendente');
    if (filaEl) filaEl.textContent = queue.length;

    atualizarDrawerFila();

    try {
      const db = await abrirBancoOffline();
      const tx = db.transaction('fila', 'readwrite');
      const store = tx.objectStore('fila');
      store.clear();
      queue.forEach((item) => store.put(item));
    } catch {
      localStorage.setItem(queueKey, JSON.stringify(queue));
    }
  }
  function saveQueue(queue) {
    void salvarFila(queue);
    atualizarConflitoRota();
    void navigator.serviceWorker?.ready.then((r) => r.sync?.register('frota-offline-sync')).catch(() => {});
  }
  queueReady = carregarFila();
  function operationId(id, action, body) { return `${motoristaId}:${id}:${action}:${body.data_hora}`; }

  // ================================================================
  // GPS
  // ================================================================
  function getPosition() {
    return new Promise((resolve) => {
      if (!navigator.geolocation) return resolve({});
      $('gps-status').textContent = 'Obtendo GPS...';
      navigator.geolocation.getCurrentPosition((position) => {
        driverPosition = { latitude: position.coords.latitude, longitude: position.coords.longitude, precisao: position.coords.accuracy };
        localStorage.setItem(positionKey, JSON.stringify(driverPosition));
        $('gps-status').textContent = 'GPS confirmado';
        atualizarMapaRota();
        resolve(driverPosition);
      }, () => { $('gps-status').textContent = 'GPS indisponível'; resolve({}); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 });
    });
  }
  function getPositionFast(timeoutMs = 4000) {
    return new Promise((resolve) => {
      const cached = driverPosition || safeParse(localStorage.getItem(positionKey) || 'null', null);
      if (!navigator.geolocation) return resolve(cached || {});
      let resolved = false;
      const finish = (value) => { if (resolved) return; resolved = true; resolve(value); };
      const timer = setTimeout(() => finish(cached || {}), timeoutMs);
      navigator.geolocation.getCurrentPosition((position) => {
        clearTimeout(timer);
        driverPosition = { latitude: position.coords.latitude, longitude: position.coords.longitude, precisao: position.coords.accuracy };
        localStorage.setItem(positionKey, JSON.stringify(driverPosition));
        finish(driverPosition);
      }, () => { clearTimeout(timer); finish(cached || {}); }, { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 60000 });
    });
  }

  // ================================================================
  // ESTADO DE CONEXÃO
  // ================================================================
  function setConnectionState() {
    const state = $('connection-state'); if (!state) return;
    state.classList.toggle('is-offline', !online());
    state.querySelector('span:last-child').textContent = online() ? 'Online' : 'Offline';
    if ($('offline-notice')) $('offline-notice').hidden = online();
    if ($('route-map-offline')) $('route-map-offline').hidden = online();
    if ($('route-map-wrap')) $('route-map-wrap').hidden = !online();
    atualizarCardCaminhao();
  }
  function atualizarConflitoRota() {
    const el = $('route-conflict');
    if (el) el.hidden = !getQueue().some((item) => item.type === 'reordenar' && item.conflict);
  }

  // ================================================================
  // CARD DO CAMINHÃO
  // ================================================================
  function atualizarCardCaminhao() {
    const card = $('truck-card');
    if (!card) return;
    const primeira = entregas.find((item) => item.placa || item.veiculo_id);
    if (!primeira) { card.hidden = true; return; }
    card.hidden = false;
    $('truck-card-placa').textContent = primeira.placa || 'Veículo não vinculado';
    $('truck-card-modelo').textContent = primeira.modelo || '—';
    const tracking = $('truck-card-tracking');
    const label = $('truck-tracking-label');
    if (!tracking || !label) return;
    if (online() && truckPosition && truckPosition.fonte === 'cobli') {
      tracking.className = 'truck-card-tracking is-live';
      label.textContent = 'Rastreio ao vivo (Cobli)';
    } else if (truckPosition) {
      tracking.className = 'truck-card-tracking is-cache';
      label.textContent = online() ? 'Aguardando Cobli...' : 'Última posição salva (offline)';
    } else {
      tracking.className = 'truck-card-tracking';
      label.textContent = online() ? 'Sem rastreio Cobli' : 'Rastreio indisponível offline';
    }
  }

  function abrirNavegacao(item, appTarget) {
    if (!item) return;
    const lat = item.latitude;
    const lng = item.longitude;
    const addr = formatAddress(item);
    let url = '';
    if (appTarget === 'waze') {
      url = (typeof lat === 'number' && typeof lng === 'number')
        ? 'https://waze.com/ul?ll=' + lat + ',' + lng + '&navigate=yes'
        : 'https://waze.com/ul?q=' + encodeURIComponent(addr);
    } else {
      url = (typeof lat === 'number' && typeof lng === 'number')
        ? 'https://www.google.com/maps/dir/?api=1&destination=' + lat + ',' + lng
        : 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(addr);
    }
    window.open(url, '_blank', 'noopener,noreferrer');
  }

  async function carregarListaMotoristas() {
    const selectorCard = $('driver-selector-card');
    if (!podeTrocarMotorista) {
      if (selectorCard) selectorCard.hidden = true;
      try {
        const response = await fetch(`${apiBase}/motoristas/${motoristaId}`, { headers: authHeaders(), credentials: 'include' });
        if (response.ok) {
          const payload = await response.json();
          const atual = payload.data;
          if (atual && $('driver-current-name')) {
            $('driver-current-name').textContent = 'Motorista: ' + atual.nome + (atual.veiculo_placa ? ' | Veículo: ' + atual.veiculo_placa : '');
          }
        }
      } catch (e) { console.warn('Erro ao identificar motorista:', e); }
      return;
    }
    const select = $('driver-select-input');
    if (!select) return;
    try {
      if (!painelMotoristas.length) await carregarPainelAdmin();
      const list = painelMotoristas;
      select.innerHTML = '<option value="">-- Selecione um motorista --</option>' +
        list.map((m) => '<option value="' + m.id + '" ' + (Number(m.id) === motoristaId ? 'selected' : '') + '>' + escapeHtml(m.nome) + ' (' + escapeHtml(m.veiculo_placa || 'Sem veículo') + ')</option>').join('');
      if (motoristaId) {
        const atual = list.find((m) => Number(m.id) === motoristaId);
        if (atual && $('driver-current-name')) {
          $('driver-current-name').textContent = 'Motorista: ' + atual.nome + (atual.veiculo_placa ? ' | Veículo: ' + atual.veiculo_placa : '');
        }
      }
    } catch (e) {
      console.warn('Erro ao carregar lista de motoristas:', e);
    }
  }

  function statusRotaMotorista(motorista) {
    if (motorista.embarque_status === 'em_andamento') return ['Em rota', 'is-running'];
    if (motorista.embarque_status === 'planejado') return ['Planejada', ''];
    if (motorista.embarque_id) return ['Finalizada', ''];
    return ['Sem rota', ''];
  }

  function renderizarPainelAdmin() {
    if (!isAdminApp) return;
    const termo = String($('driver-admin-search')?.value || '').trim().toLowerCase();
    const filtrados = painelMotoristas.filter((motorista) =>
      !termo || `${motorista.nome || ''} ${motorista.veiculo_placa || ''}`.toLowerCase().includes(termo)
    );
    const list = $('driver-admin-list');
    if (!list) return;
    if (!filtrados.length) {
      list.innerHTML = '<div class="empty-state">Nenhum motorista encontrado.</div>';
      return;
    }
    list.innerHTML = filtrados.map((motorista) => {
      const [statusLabel, statusClass] = statusRotaMotorista(motorista);
      const problemas = Number(motorista.entregas_problemas || 0);
      return `<button type="button" class="driver-overview-card${Number(motorista.id) === motoristaId ? ' is-selected' : ''}" data-driver-id="${Number(motorista.id)}">
        <span class="driver-overview-card-head"><span><h2>${escapeHtml(motorista.nome || 'Motorista')}</h2><small>${escapeHtml(motorista.veiculo_placa || 'Sem veículo vinculado')}</small></span><span class="driver-route-status ${statusClass}">${statusLabel}</span></span>
        <span class="driver-overview-progress"><span style="width:${Math.max(0, Math.min(100, Number(motorista.progresso || 0)))}%"></span></span>
        <span class="driver-overview-meta"><span>${Number(motorista.entregas_concluidas || 0)} de ${Number(motorista.total_entregas || 0)} concluídas</span><span class="${problemas ? 'has-problem' : ''}">${problemas} problema${problemas === 1 ? '' : 's'}</span></span>
      </button>`;
    }).join('');
  }

  async function carregarPainelAdmin() {
    if (!isAdminApp || !online()) return;
    try {
      const response = await fetch(`${apiBase}/motoristas/painel-app`, { headers: authHeaders(), credentials: 'include' });
      if (!response.ok) throw new Error('Falha ao carregar painel');
      const payload = await response.json();
      painelMotoristas = payload.data || [];
      const resumo = payload.resumo || {};
      if ($('admin-total-motoristas')) $('admin-total-motoristas').textContent = resumo.motoristas || 0;
      if ($('admin-em-rota')) $('admin-em-rota').textContent = resumo.em_rota || 0;
      if ($('admin-entregas-concluidas')) $('admin-entregas-concluidas').textContent = resumo.entregas_concluidas || 0;
      if ($('admin-entregas-pendentes')) $('admin-entregas-pendentes').textContent = resumo.entregas_pendentes || 0;
      if ($('admin-problemas')) $('admin-problemas').textContent = resumo.problemas || 0;
      renderizarPainelAdmin();
    } catch (error) {
      if ($('driver-admin-list')) $('driver-admin-list').innerHTML = '<div class="empty-state">Não foi possível atualizar os motoristas.</div>';
      console.warn('Erro no painel de motoristas:', error);
    }
  }

  async function selecionarMotorista(novoMotoristaId) {
    if (!podeTrocarMotorista || !novoMotoristaId) return;
    motoristaId = Number(novoMotoristaId);
    localStorage.setItem('motoristaId', motoristaId);
    app.dataset.motoristaId = motoristaId;
    cacheKey = `frota.motorista.${motoristaId}.entregas`;
    queueKey = `frota.motorista.${motoristaId}.offline.queue`;
    positionKey = `frota.motorista.${motoristaId}.posicao`;
    truckKey = `frota.motorista.${motoristaId}.truck`;
    perfilDados = safeParse(localStorage.getItem(`frota.motorista.${motoristaId}.perfil`) || 'null', null);
    if ($('driver-select-input')) $('driver-select-input').value = String(motoristaId);
    renderizarPainelAdmin();
    await carregarEntregas();
    await carregarListaMotoristas();
    await carregarPerfil();
    renderizarAvatar();
    $('driver-selector-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  // ================================================================
  // HELPERS DO NOVO LAYOUT
  // ================================================================
  function normalizarTexto(texto) {
    return String(texto || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function formatarMoedaMotorista(valor) {
    const n = Number(valor);
    if (isNaN(n)) return 'R$ 0,00';
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  // ================================================================
  // TABS — Minha Rota / Meu Painel
  // ================================================================
  function mudarAbaMotorista(aba, btn) {
    document.querySelectorAll('.driver-tab').forEach((b) => {
      const ativo = b === btn;
      b.classList.toggle('active', ativo);
      b.setAttribute('aria-selected', ativo ? 'true' : 'false');
    });
    document.querySelectorAll('.driver-tab-panel').forEach((p) => {
      p.hidden = p.id !== `driver-tab-${aba}`;
    });

    // 🔥 Pacote 2 vai implementar carregarPainelMotorista()
    if (aba === 'painel' && typeof carregarPainelMotorista === 'function') {
      carregarPainelMotorista();
    }
    if (aba === 'rota') {
      setTimeout(() => {
        if (routeMap) routeMap.resize();
      }, 100);
    }
  }

   function toggleDetalhesParada(entregaId) {
    const card = document.querySelector(`.delivery-card[data-entrega-id="${entregaId}"]`);
    if (!card) return;

    // 🩹 Pacote 1.5: cards CONCLUÍDOS abrem o modal de detalhes em vez de expandir
    const status = card.dataset.status;
    const concluida = status === 'entregue'
                   || status === 'entregue_com_problema'
                   || status === 'falha'
                   || status === 'cancelada';
    if (concluida) {
      abrirDetalhesEntrega(Number(entregaId));
      return;
    }

    card.classList.toggle('is-expanded');
  }

  // ================================================================
  // BUSCA NA LISTA DE PARADAS
  // ================================================================
  function aplicarBuscaRota() {
    render();
    const clearBtn = $('route-search-clear');
    if (clearBtn) {
      clearBtn.hidden = !($('route-search-input')?.value || '');
    }
  }

  // ================================================================
  // MAPA RECOLHÍVEL
  // ================================================================
  function toggleMapaMotorista() {
    const wrap = $('route-map-wrap');
    if (!wrap) return;
    const isCollapsed = wrap.classList.toggle('is-collapsed');
    try { localStorage.setItem('frota.motorista.mapaRecolhido', isCollapsed ? '1' : '0'); } catch (e) {}

    const label = $('route-map-toggle-label');
    if (label) label.textContent = isCollapsed ? 'Mostrar' : 'Ocultar';

    if (!isCollapsed && routeMap) {
      setTimeout(() => routeMap.resize(), 100);
    }
  }

  function restaurarEstadoMapa() {
    const wrap = $('route-map-wrap');
    if (!wrap) return;
    let recolhido = false;
    try { recolhido = localStorage.getItem('frota.motorista.mapaRecolhido') === '1'; } catch (e) {}
    // Em telas pequenas, começa recolhido por padrão
    if (window.innerWidth < 480 && localStorage.getItem('frota.motorista.mapaRecolhido') === null) {
      recolhido = true;
    }
    if (recolhido) {
      wrap.classList.add('is-collapsed');
      const label = $('route-map-toggle-label');
      if (label) label.textContent = 'Mostrar';
    }
  }
  function formatAddress(item) { return [item.endereco, item.numero, item.bairro, item.cidade, item.uf].filter(Boolean).join(', ') || 'Endereço não informado'; }
  function escapeHtml(value) { return String(value).replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }
   function haversineKm(aLat, aLng, bLat, bLng) {
    const r = (v) => v * Math.PI / 180, R = 6371, dLat = r(bLat - aLat), dLng = r(bLng - aLng);
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(r(aLat)) * Math.cos(r(bLat)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
  }

  /**
   * 🔥 Pacote 3 — M4-fix (2026-09-25)
   * Converte valores que podem vir como string (NUMERIC do PostgreSQL
   * via PDO) para number. Retorna null se não for numérico.
   *
   * Motivo: o backend serializa colunas NUMERIC como string no JSON,
   * então `typeof entrega.latitude === 'number'` retorna false e várias
   * funções (mapa, chip, distância) silenciosamente ignoram a entrega.
   */
  function paraNumero(valor) {
    if (valor === null || valor === undefined || valor === '') return null;
    const n = Number(valor);
    return Number.isFinite(n) ? n : null;
  }
  function formatDistance(km) { if (km == null || Number.isNaN(km)) return ''; return km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(1)} km`; }
  function refPoint(item) {
    if (driverPosition) return driverPosition;
    if (truckPosition) return truckPosition;
    const vLat = paraNumero(item?.veiculo_lat);
    const vLng = paraNumero(item?.veiculo_lng);
    return (vLat !== null && vLng !== null) ? { latitude: vLat, longitude: vLng } : null;
  }
  function distanciaEntrega(item) {
    const lat = paraNumero(item?.latitude);
    const lng = paraNumero(item?.longitude);
    if (lat === null || lng === null) return null;
    const ref = refPoint(item);
    if (!ref) return null;
    return haversineKm(ref.latitude, ref.longitude, lat, lng);
  }
   // ================================================================
  // PAINEL DE PROGRESSO + KPI + NEXT-STOP — v2 (2026-09-25)
  // ================================================================
  function atualizarPainelRota() {
    const total = entregas.length;
    const concluidas = entregas.filter((item) => ['entregue', 'entregue_com_problema'].includes(item.status)).length;
    const emRota = entregas.filter((item) => item.status === 'em_entrega').length;
    const problemas = entregas.filter((item) => ['falha', 'entregue_com_problema'].includes(item.status)).length;
    const faltam = total - concluidas;
    const percentual = total ? Math.round((concluidas / total) * 100) : 0;

    // Barra de progresso
    if ($('route-progress-label')) $('route-progress-label').textContent = `${percentual}% concluído`;
    if ($('route-progress-count')) $('route-progress-count').textContent = `${concluidas} de ${total}`;
    if ($('route-progress-bar')) $('route-progress-bar').style.width = `${percentual}%`;

    // KPI grid
    if ($('kpi-faltam'))    $('kpi-faltam').textContent    = faltam;
    if ($('kpi-concluidas')) $('kpi-concluidas').textContent = concluidas;
    if ($('kpi-em-rota'))   $('kpi-em-rota').textContent   = emRota;
    if ($('kpi-problemas')) $('kpi-problemas').textContent = problemas;

    // Próxima parada
    const proxima = entregas.find((item) => !['entregue', 'entregue_com_problema'].includes(item.status));
    const card = $('next-stop-card');
    if (!card) return;

    if (!proxima) {
      card.hidden = true;
      return;
    }

    card.hidden = false;

    const num = Number(proxima.ordem_entrega) || entregas.indexOf(proxima) + 1;
    if ($('next-stop-num')) $('next-stop-num').textContent = num;
    if ($('next-stop-name')) $('next-stop-name').textContent = proxima.cliente_nome || `Entrega #${proxima.id}`;
    if ($('next-stop-address')) $('next-stop-address').textContent = formatAddress(proxima);

    // Chips
    const distancia = distanciaEntrega(proxima);
    if ($('next-stop-distance')) {
      $('next-stop-distance').textContent = distancia !== null ? formatDistance(distancia) : '—';
    }

    // ETA: 40 km/h + 5 min por parada (estimativa)
    if ($('next-stop-eta')) {
      if (distancia !== null) {
        const minutos = Math.max(2, Math.round((distancia / 40) * 60));
        const agora = new Date();
        agora.setMinutes(agora.getMinutes() + minutos);
        const hora = agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        $('next-stop-eta').textContent = `~${minutos} min · chegar ${hora}`;
      } else {
        $('next-stop-eta').textContent = '—';
      }
    }

    // Pedidos
    if ($('next-stop-pedidos')) {
      const pedidosArr = String(proxima.pedidos_ids || '').split(',').map(s => s.trim()).filter(Boolean);
      $('next-stop-pedidos').textContent = pedidosArr.length === 0
        ? 'sem pedido'
        : pedidosArr.length === 1
          ? `#${pedidosArr[0]}`
          : `${pedidosArr.length} pedidos`;
    }

    // Valor
    if ($('next-stop-valor')) {
      $('next-stop-valor').textContent = proxima.valor_total
        ? formatarMoedaMotorista(proxima.valor_total)
        : '—';
    }

        // Botão Cheguei
    const btnAction = $('next-stop-action');
    if (btnAction) {
      btnAction.dataset.id = proxima.id;
      btnAction.disabled = proxima.status === 'em_entrega';
      btnAction.textContent = proxima.status === 'em_entrega' ? 'Em atendimento' : 'Cheguei';
    }

    // 🔥 Pacote 3 — M4: mantém o chip do cabeçalho em sincronia
    atualizarChipDistancia();
  }
  // ================================================================
  // RENDERIZAR LISTA DE ENTREGAS — v2 (2026-09-25)
  //
  // Layout novo:
  //   - Cada card tem [num] [cliente + pedido + meta] [status] [chevron]
  //   - Próxima parada vem primeiro e já expandida
  //   - Restantes colapsadas
  //   - Botões Cheguei/Entregue/Problema dentro do corpo expandido
  //
  // 🔥 AJUSTES 2026-09-25:
  //   - Removido chip de distância duplicado (fica só no body)
  //   - Ordenação por ordem_entrega primeiro (respeita rota original)
  //   - Permite colapsar a "próxima" (mantém destaque visual)
  // ================================================================
  function render() {
    const list = $('delivery-list');
    if (!list) return;

    atualizarCardCaminhao();

    if (!entregas.length) {
      list.innerHTML = '<div class="empty-state">Nenhuma entrega encontrada para hoje.</div>';
      atualizarPainelRota();
      atualizarMapaRota();
      return;
    }

    // ── Filtro de busca ──────────────────────────────────────────
    const termo = normalizarTexto($('route-search-input')?.value || '');
    const termoNumeros = termo.replace(/\D/g, '');

    // ── Ordenação ────────────────────────────────────────────────
    //  1. Status (em rota → pendente → problema → concluída)
    //  2. Ordem de entrega (respeita a rota original)
    const ordemStatus = {
      'em_entrega': 0,
      'pendente': 1,
      'entregue_com_problema': 2,
      'falha': 3,
      'entregue': 4,
      'cancelada': 5
    };

    const entregasOrdenadas = [...entregas].sort((a, b) => {
      const oa = ordemStatus[a.status] ?? 99;
      const ob = ordemStatus[b.status] ?? 99;
      if (oa !== ob) return oa - ob;

      const ordemA = Number(a.ordem_entrega) || 999999;
      const ordemB = Number(b.ordem_entrega) || 999999;
      return ordemA - ordemB;
    });

    // Próxima parada = primeiro não concluído na ordem
    const proxima = entregasOrdenadas.find(
      (item) => !['entregue', 'entregue_com_problema'].includes(item.status)
    );

    // ── Aplica filtro de busca ───────────────────────────────────
    const entregasVisiveis = entregasOrdenadas.filter((item) => {
      if (!termo) return true;

      const pedidos = String(item.pedidos_ids || '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

      const haystack = normalizarTexto([
        item.cliente_nome,
        item.endereco,
        item.numero,
        item.bairro,
        item.cidade,
        item.codigo_rastreamento,
        pedidos.join(' ')
      ].filter(Boolean).join(' '));

      if (haystack.includes(termo)) return true;

      // Busca por número de pedido (match exato dos dígitos)
      if (termoNumeros && pedidos.some((p) => p.includes(termoNumeros))) return true;

      return false;
    });

        // ── Filtro por status (chips) — Pacote 1.5
    if (!['todas', 'pendente', 'em_entrega', 'entregue', 'problema'].includes(filtroStatusAtual)) {
      filtroStatusAtual = 'todas';
    }

    const entregasFiltradas = filtroStatusAtual === 'todas'
      ? entregasVisiveis
      : entregasVisiveis.filter(item => {
          if (filtroStatusAtual === 'entregue') {
            return item.status === 'entregue' || item.status === 'entregue_com_problema';
          }
          if (filtroStatusAtual === 'problema') {
            return item.status === 'falha' || item.status === 'entregue_com_problema';
          }
          return item.status === filtroStatusAtual;
        });

    if (!entregasFiltradas.length) {
      list.innerHTML = '<div class="empty-state">Nenhuma parada corresponde ao filtro.</div>';
      atualizarPainelRota();
      atualizarMapaRota();
      atualizarContadoresChips();
      return;
    }

    // ── Renderiza cada card ──────────────────────────────────────
       list.innerHTML = entregasFiltradas.map((item) => {
      const isProxima = proxima && Number(proxima.id) === Number(item.id);
      const status = item.status || 'pendente';
      const complete = ['entregue', 'entregue_com_problema'].includes(status);
      const emEntrega = status === 'em_entrega';

      // Número da parada (ordem original da rota)
      const num = Number(item.ordem_entrega)
        || (entregas.indexOf(item) + 1);

      // ── Pedidos ────────────────────────────────────────────────
      const pedidosArr = String(item.pedidos_ids || '')
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);

      const pedidosLabel = pedidosArr.length === 0
        ? ''
        : pedidosArr.length === 1
          ? `Pedido #${pedidosArr[0]}`
          : `${pedidosArr.length} pedidos (#${pedidosArr[0]} +${pedidosArr.length - 1})`;

      // ── Distância até essa parada ──────────────────────────────
      const d = distanciaEntrega(item);

      // ── Chips do body expandido ────────────────────────────────
      const chips = [];

      if (pedidosArr.length) {
        chips.push(
          `<span class="chip-pedidos"><i class="fa-solid fa-receipt"></i> ${escapeHtml(pedidosLabel)}</span>`
        );
      }
      if (item.valor_total) {
        chips.push(
          `<span class="chip-valor"><i class="fa-solid fa-sack-dollar"></i> ${formatarMoedaMotorista(item.valor_total)}</span>`
        );
      }
      if (d != null) {
        chips.push(
          `<span class="chip-distancia"><i class="fa-solid fa-route"></i> ${escapeHtml(formatDistance(d))}</span>`
        );
      }
      if (item.codigo_rastreamento) {
        chips.push(
          `<span><i class="fa-solid fa-qrcode"></i> ${escapeHtml(item.codigo_rastreamento)}</span>`
        );
      }

      // ── Status label ──────────────────────────────────────────
      const statusLabel = {
        'pendente':              'Pendente',
        'em_entrega':            'Em rota',
        'entregue':              'Entregue',
        'entregue_com_problema': 'Com problema',
        'falha':                 'Falha',
        'cancelada':             'Cancelada'
      }[status] || status;

      // ── Botões conforme o estado ──────────────────────────────
      let botoes;
      if (complete) {
        botoes = `
          <button class="checkin" disabled>Cheguei</button>
          <button class="checkout" disabled>Entregue</button>
          <button class="failure" disabled>Problema</button>
        `;
      } else if (emEntrega) {
        botoes = `
          <button class="checkin" disabled title="Check-in já realizado">
            <i class="fa-solid fa-check"></i> Cheguei
          </button>
          <button class="checkout" data-action="checkout" data-id="${item.id}">Entregue</button>
          <button class="failure" data-action="falha" data-id="${item.id}">Problema</button>
        `;
      } else {
        botoes = `
          <button class="checkin" data-action="checkin" data-id="${item.id}">Cheguei</button>
          <button class="checkout" disabled title="Faça o check-in primeiro">Entregue</button>
          <button class="failure" data-action="falha" data-id="${item.id}">Problema</button>
        `;
      }

      // ── Botões de reordenar ───────────────────────────────────
      const idxOriginal = entregas.indexOf(item);
      const orderActions = `
        <div class="delivery-card-order-actions">
          <button data-order="up" data-index="${idxOriginal}" ${idxOriginal === 0 ? 'disabled' : ''}>
            <i class="fa-solid fa-arrow-up"></i> Subir
          </button>
          <button data-order="down" data-index="${idxOriginal}" ${idxOriginal === entregas.length - 1 ? 'disabled' : ''}>
            <i class="fa-solid fa-arrow-down"></i> Descer
          </button>
        </div>
      `;

      // ── Classes do card ───────────────────────────────────────
      const classes = [
        'delivery-card',
        complete ? 'is-complete' : '',
        isProxima ? 'is-next is-expanded' : '',
      ].filter(Boolean).join(' ');

      return `
        <article class="${classes}"
                 data-status="${status}"
                 data-entrega-id="${item.id}"
                 data-ordem="${num}"
                 title="${escapeHtml(item.cliente_nome || `Entrega #${item.id}`)}">
          <header class="delivery-card-head" onclick="toggleDetalhesParada(${item.id})">
            <span class="delivery-card-num">${num}</span>
            <div class="delivery-card-info">
              <div class="delivery-card-client">
                ${escapeHtml(item.cliente_nome || `Entrega #${item.id}`)}
              </div>
              <div class="delivery-card-meta">
                ${pedidosArr.length === 1
                  ? `<span class="pedido-tag">#${escapeHtml(pedidosArr[0])}</span>`
                  : ''}
                ${pedidosArr.length > 1
                  ? `<span class="pedido-tag">${escapeHtml(pedidosLabel)}</span>`
                  : ''}
                ${isProxima ? `<span class="chip-proxima"><i class="fa-solid fa-star"></i> Próxima</span>` : ''}
              </div>
            </div>
            <div class="delivery-card-status-wrap">
              <span class="delivery-card-status status-${status}">
                ${escapeHtml(statusLabel)}
              </span>
              <i class="fa-solid fa-chevron-down delivery-card-chevron"></i>
            </div>
          </header>

                 <div class="delivery-card-body">
            <p class="delivery-address">
              <i class="fa-solid fa-location-dot"></i>
              <span>${escapeHtml(formatAddress(item))}</span>
            </p>

            ${chips.length ? `<div class="delivery-card-chips">${chips.join('')}</div>` : ''}

            ${!isAdminApp ? renderBotoesContato(item) : ''}

            <div class="delivery-actions">${botoes}</div>

            ${!isAdminApp ? orderActions : ''}
          </div>
        </article>
      `;
    }).join('');

      atualizarPainelRota();
    atualizarMapaRota();
    atualizarContadoresChips();
    detectarMudancaProximaParada(); 
  }

  // ================================================================
  // 🩹 CORRIGIDO 2026-09-25 (Pacote 1.5): alguns IDs antigos
  // (#total-entregas, #entregas-concluidas, #fila-pendente) foram
  // removidos do HTML no Pacote 1. Estes elementos agora são
  // opcionais — a função não deve quebrar se não existirem.
  // ================================================================
  function updateSummary() {
    const totalEl = $('total-entregas');
    if (totalEl) totalEl.textContent = entregas.length;

    const concluidasEl = $('entregas-concluidas');
    if (concluidasEl) {
      concluidasEl.textContent = entregas.filter((item) =>
        ['entregue', 'entregue_com_problema'].includes(item.status)
      ).length;
    }

    const filaEl = $('fila-pendente');
    if (filaEl) filaEl.textContent = getQueue().length;

    atualizarDrawerFila();
    atualizarPainelRota();
  }
  function persist() {
    localStorage.setItem(cacheKey, JSON.stringify(entregas));
    render();
    updateSummary();
  }
  function salvarPosicaoMotorista(position) {
    if (!position || typeof position.latitude !== 'number' || typeof position.longitude !== 'number') return;
    driverPosition = position;
    localStorage.setItem(positionKey, JSON.stringify(position));
    atualizarMapaRota();
    atualizarChipDistancia(); // 🔥 Pacote 3 — M4
    render();
  }
  function salvarPosicaoCaminhao(position) { if (!position || typeof position.latitude !== 'number' || typeof position.longitude !== 'number') return; truckPosition = position; localStorage.setItem(truckKey, JSON.stringify(position)); atualizarMapaRota(); render(); }

  // ================================================================
  // 🩹 CORRIGIDO 2026-09-25 (Pacote 1.5): silencia warning conhecido
  // do MapLibre 4.x + OpenFreeMap ("Expected value to be of type
  // number, but found null instead"). Não é bug nosso — é ruído do
  // worker interno do MapLibre durante o parse do style.
  // ================================================================
  function instalarFiltroErrosMapLibre() {
    if (window.__mapErrorFilterInstalled) return;
    window.__mapErrorFilterInstalled = true;

    const _origError = console.error;
    console.error = function (...args) {
      const first = args[0];
      if (first && typeof first === 'string' &&
          (first.includes('Expected value to be of type number') ||
           first.includes('but found null instead'))) {
        return;
      }
      _origError.apply(console, args);
    };

    window.addEventListener('error', (e) => {
      if (e.message && e.message.includes('Expected value to be of type number')) {
        e.preventDefault();
        return true;
      }
    });
  }

  function inicializarMapa() {
    if (routeMap || !window.maplibregl || !$('route-map')) return;

    instalarFiltroErrosMapLibre();

    routeMap = new maplibregl.Map({
      container: 'route-map',
      style: 'https://tiles.openfreemap.org/styles/liberty',
      center: [-49.53561648427039, -28.979438954992666],
      zoom: 11,
      attributionControl: true
    });
    routeMap.addControl(new maplibregl.NavigationControl(), 'top-right');
    routeMap.on('load', () => {
      if (!routeMap.getSource(routeSourceId)) {
        routeMap.addSource(routeSourceId, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
        routeMap.addLayer({ id: 'driver-route-line', type: 'line', source: routeSourceId, paint: { 'line-color': '#16845e', 'line-width': 4, 'line-opacity': 0.9 } });
      }
      atualizarMapaRota();
    });
  }

  function atualizarLinhaRota(coords) {
    if (!routeMap || !routeMap.getSource(routeSourceId)) return;
    routeMap.getSource(routeSourceId).setData(coords.length > 1 ? { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'LineString', coordinates: coords }, properties: {} }] } : { type: 'FeatureCollection', features: [] });
  }

  function atualizarMapaRota() {
    if (!routeMap) return;
        if (!online()) {
      if ($('route-map-wrap')) $('route-map-wrap').hidden = true;
      if ($('route-map-offline')) $('route-map-offline').hidden = false;
      return;
    }
        if ($('route-map-wrap')) $('route-map-wrap').hidden = false;
    // Se estiver recolhido, não precisa redimensionar
    const wrap = $('route-map-wrap');
    if (wrap && !wrap.classList.contains('is-collapsed') && routeMap) {
      setTimeout(() => routeMap.resize(), 50);
    }
    if ($('route-map-offline')) $('route-map-offline').hidden = true;

        // 🔥 M4-fix: normaliza coords (backend manda como string)
    const rota = entregas
      .map((item) => ({
        ...item,
        __lat: paraNumero(item.latitude),
        __lng: paraNumero(item.longitude),
      }))
      .filter((item) => item.__lat !== null && item.__lng !== null);

    const idsAtuais = new Set();
    const bounds = new maplibregl.LngLatBounds();
    const coords = [];

    // ── 1. Entregas: atualiza marcadores existentes, cria os novos, remove os ausentes
        rota.forEach((stop, index) => {
      const key = `stop-${stop.id}`;
      idsAtuais.add(key);
      coords.push([stop.__lng, stop.__lat]);
      bounds.extend([stop.__lng, stop.__lat]);

      const isComplete = ['entregue', 'entregue_com_problema'].includes(stop.status);
      const existing = markerIndex.get(key);

      if (existing) {
        existing.marker.setLngLat([stop.__lng, stop.__lat]);
        const el = existing.marker.getElement();
        if (el) {
          el.textContent = String(index + 1);
          el.className = `stop-marker${isComplete ? ' is-complete' : ''}`;
        }
        existing.marker.getPopup()?.setHTML(
          `<strong>${escapeHtml(stop.cliente_nome || `Entrega #${stop.id}`)}</strong><br>${escapeHtml(formatAddress(stop))}<br>Status: ${escapeHtml(stop.status || 'pendente')}`
        );
      } else {
        const el = document.createElement('div');
        el.className = `stop-marker${isComplete ? ' is-complete' : ''}`;
        el.textContent = String(index + 1);
        const marker = new maplibregl.Marker({ element: el })
          .setLngLat([stop.__lng, stop.__lat])
          .setPopup(new maplibregl.Popup({ offset: 24 }).setHTML(
            `<strong>${escapeHtml(stop.cliente_nome || `Entrega #${stop.id}`)}</strong><br>${escapeHtml(formatAddress(stop))}<br>Status: ${escapeHtml(stop.status || 'pendente')}`
          ))
          .addTo(routeMap);
        markerIndex.set(key, { marker, type: 'stop' });
      }
    });

    // Remove marcadores de entregas que sumiram
    for (const [key, entry] of markerIndex) {
      if (key.startsWith('stop-') && !idsAtuais.has(key)) {
        entry.marker.remove();
        markerIndex.delete(key);
      }
    }

    // ── 2. Caminhão
    if (truckPosition && typeof truckPosition.longitude === 'number' && typeof truckPosition.latitude === 'number') {
      if (truckMarker) {
        truckMarker.setLngLat([truckPosition.longitude, truckPosition.latitude]);
        const el = truckMarker.getElement();
        if (el) {
          const balloon = el.querySelector('.driver-truck-balloon');
          if (balloon) balloon.textContent = truckPosition.placa || 'Caminhão';
        }
        truckMarker.getPopup()?.setHTML(
          `<strong>${escapeHtml(truckPosition.placa || 'Caminhão')}</strong><br>${escapeHtml(truckPosition.modelo || '')}<br>Fonte: ${truckPosition.fonte === 'cobli' ? 'Cobli (ao vivo)' : 'Cadastro'}`
        );
      } else {
        const el = document.createElement('div');
        el.className = 'driver-truck-marker';
        el.innerHTML = `<div class="driver-truck-balloon">${escapeHtml(truckPosition.placa || 'Caminhão')}</div><div class="driver-truck-icon"><i class="fa-solid fa-truck"></i></div>`;
        truckMarker = new maplibregl.Marker({ element: el })
          .setLngLat([truckPosition.longitude, truckPosition.latitude])
          .setPopup(new maplibregl.Popup({ offset: 24 }).setHTML(
            `<strong>${escapeHtml(truckPosition.placa || 'Caminhão')}</strong><br>${escapeHtml(truckPosition.modelo || '')}<br>Fonte: ${truckPosition.fonte === 'cobli' ? 'Cobli (ao vivo)' : 'Cadastro'}`
          ))
          .addTo(routeMap);
      }
      bounds.extend([truckPosition.longitude, truckPosition.latitude]);
    } else if (truckMarker) {
      truckMarker.remove();
      truckMarker = null;
    }

    // ── 3. Motorista
    if (driverPosition && typeof driverPosition.longitude === 'number' && typeof driverPosition.latitude === 'number') {
      if (driverMarker) {
        driverMarker.setLngLat([driverPosition.longitude, driverPosition.latitude]);
      } else {
        const el = document.createElement('div');
        el.className = 'driver-me-marker';
        driverMarker = new maplibregl.Marker({ element: el })
          .setLngLat([driverPosition.longitude, driverPosition.latitude])
          .setPopup(new maplibregl.Popup({ offset: 24 }).setHTML('<strong>Posição do motorista</strong><br>GPS do celular'))
          .addTo(routeMap);
      }
      bounds.extend([driverPosition.longitude, driverPosition.latitude]);
    } else if (driverMarker) {
      driverMarker.remove();
      driverMarker = null;
    }

    // ── 4. Linha da rota
    atualizarLinhaRota(coords);

    if (!bounds.isEmpty()) {
      if (Date.now() - lastFitBounds > 5000) {
        routeMap.fitBounds(bounds, { padding: 50, maxZoom: 14 });
        lastFitBounds = Date.now();
      }
    }
  }

  async function atualizarMapaComCobli() {
    inicializarMapa();
    if (!online()) { atualizarMapaRota(); atualizarCardCaminhao(); return; }

    const primeira = entregas.find((item) => item.veiculo_id != null && item.veiculo_id !== '');
    if (!primeira) { atualizarMapaRota(); atualizarCardCaminhao(); return; }

    const veiculoId = Number(primeira.veiculo_id);
    if (!veiculoId) { atualizarMapaRota(); atualizarCardCaminhao(); return; }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 8000);

    try {
      const response = await fetch(
        `${apiBase}/cobli/veiculo/${veiculoId}/posicao`,
        { headers: authHeaders(), credentials: 'include', signal: controller.signal }
      );
      clearTimeout(timeoutId);

      if (response.ok) {
        const payload = await response.json();
        const loc = payload.data?.localizacao;
        const veiculo = payload.data?.veiculo;
        if (loc && typeof loc.latitude === 'number' && typeof loc.longitude === 'number') {
          salvarPosicaoCaminhao({
            latitude: loc.latitude,
            longitude: loc.longitude,
            placa: veiculo?.plate || veiculo?.placa || primeira.placa,
            modelo: veiculo?.model || primeira.modelo,
            fonte: 'cobli'
          });
        } else if (typeof primeira.veiculo_lat === 'number' && typeof primeira.veiculo_lng === 'number') {
          salvarPosicaoCaminhao({
            latitude: primeira.veiculo_lat,
            longitude: primeira.veiculo_lng,
            placa: primeira.placa,
            modelo: primeira.modelo,
            fonte: 'cadastro'
          });
        }
      }
    } catch (err) {
      clearTimeout(timeoutId);
      if (err.name === 'AbortError') {
        console.warn('Cobli timeout (8s)');
      } else {
        console.warn('Erro Cobli:', err);
      }
      if (typeof primeira.veiculo_lat === 'number' && typeof primeira.veiculo_lng === 'number') {
        salvarPosicaoCaminhao({
          latitude: primeira.veiculo_lat,
          longitude: primeira.veiculo_lng,
          placa: primeira.placa,
          modelo: primeira.modelo,
          fonte: 'cadastro'
        });
      }
    }

    atualizarMapaRota();
    atualizarCardCaminhao();
  }

  let cobliPollTimer = null;
  function iniciarPollingCobli() {
    if (cobliPollTimer) clearInterval(cobliPollTimer);
    cobliPollTimer = setInterval(() => { if (online()) atualizarMapaComCobli(); }, 45000);
  }

  async function carregarEntregas() {
    if (!motoristaId) {
      $('motorista-status').textContent = 'Informe o motorista para carregar a rota';
      $('delivery-list').innerHTML = '<div class="empty-state">A rota ainda não foi vinculada a um motorista.</div>';
      return;
    }
    try {
      const response = await fetch(`${apiBase}/motoristas/${motoristaId}/entregas/hoje`, { headers: authHeaders(), credentials: 'include' });
      if (!response.ok) throw new Error('Falha ao carregar rota');
        const payload = await response.json();
      entregas = payload.data?.entregas || payload.entregas || [];
      rotaVersion = payload.data?.rota_ativa?.updated_at || entregas[0]?.embarque_updated_at || null;
      driverPosition = safeParse(localStorage.getItem(positionKey) || 'null', null);
      truckPosition = safeParse(localStorage.getItem(truckKey) || 'null', null);

      // Pacote 1.5: renderiza cabeçalho do embarque + restaura estado
      renderCabecalhoEmbarque(payload.data?.embarque_info || null);
      restaurarEstadoCabecalho();

      persist();
      $('motorista-status').textContent = 'Rota atualizada agora';
      await atualizarMapaComCobli();
    } catch {
            renderCabecalhoEmbarque(null);
      entregas = safeParse(localStorage.getItem(cacheKey) || '[]', []);
      rotaVersion = entregas[0]?.embarque_updated_at || null;
      driverPosition = safeParse(localStorage.getItem(positionKey) || 'null', null);
      truckPosition = safeParse(localStorage.getItem(truckKey) || 'null', null);
      render();
      updateSummary();
      atualizarCardCaminhao();
      $('motorista-status').textContent = entregas.length ? 'Usando a última rota salva neste aparelho' : 'Não foi possível carregar a rota';
    }
  }

  async function carregarNotificacoes() {
    if (!motoristaId || !online()) return;
    try {
      const response = await fetch(`${apiBase}/motoristas/${motoristaId}/notificacoes?limite=5`, { headers: authHeaders(), credentials: 'include' });
      if (!response.ok) return;
      const payload = await response.json();
      const notification = (payload.data || []).find((item) => !item.lida);
      const alert = $('driver-alert');
      if (!alert) return;
      if (notification) {
        alert.hidden = false;
        alert.textContent = `${notification.titulo}: ${notification.mensagem}`;
      } else {
        alert.hidden = true;
        alert.textContent = '';
      }
    } catch {}
  }

  function aplicarStatusLocal(id, action) {
    const item = entregas.find((delivery) => Number(delivery.id) === Number(id));
    if (!item) return;
    if (action === 'reverter-pendente') {
      item.status = 'pendente';
    } else if (action === 'checkout') {
      item.status = 'entregue';
    } else if (action === 'falha') {
      item.status = 'pendente';
    } else {
      item.status = 'em_entrega';
    }
    persist();
  }

  function lerArquivo(file) {
    return new Promise((resolve) => {
      if (!file) return resolve(null);
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = () => resolve(null);
      reader.readAsDataURL(file);
    });
  }

  // ================================================================
  // CHECKOUT — abrir modal com checklist item a item
  //
  // 🔥 REESCRITO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   - Tenta recuperar rascunho salvo em localStorage
  //   - Se houver rascunho do MESMO dia, restaura estado (levas, fotos, etc)
  //   - Se houver rascunho de OUTRO dia, oferece descartar ou continuar
  // ================================================================
  function abrirCheckout(item) {
    if (!item) return;

    const modal = $('checkout-modal');
    const form  = $('checkout-form');
    if (!modal || !form) return;

    form.dataset.deliveryId = item.id;

    // Reset do form
    $('receiver-name').value = '';
    $('romaneio-photo').value = '';
    $('romaneio-preview').hidden = true;
    $('romaneio-remove')?.setAttribute('hidden', '');

    limparAssinatura();

    // 🔥 NOVO: tenta recuperar rascunho ANTES de renderizar
    const rascunhoRecuperado = recuperarRascunho(item.id);
    if (rascunhoRecuperado) {
      // Rascunho é do mesmo dia → restaura estado
      if (rascunhoRecuperado.mesmoDia) {
        window.__checkoutItens = rascunhoRecuperado.itens;
        if (rascunhoRecuperado.receiverName) {
          $('receiver-name').value = rascunhoRecuperado.receiverName;
        }
        if (typeof Swal !== 'undefined') {
          const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3500,
            timerProgressBar: true
          });
          Toast.fire({
            icon: 'info',
            title: 'Rascunho recuperado',
            text: 'Continuando de onde você parou.'
          });
        }
      } else {
        // Rascunho de outro dia → usa os itens previstos do ERP (limpos)
        renderizarChecklistCheckout(item);
      }
    } else {
      // Sem rascunho → renderiza do zero
      renderizarChecklistCheckout(item);
    }

    atualizarProgressoCheckout();
    modal.hidden = false;
    document.body.classList.add('driver-modal-open');
  }

  // ================================================================
  // RENDERIZAR CHECKLIST DO CHECKOUT (item a item)
  //
  // 🔥 REESCRITO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   - Cada item vira um card com:
  //     • Previsto / Entregue até agora / Faltam
  //     • Lista de levas registradas (com foto + quantidade)
  //     • Botão "+ Registrar descida"
  //     • Botões de decisão final (Entregue total / Faltante / Devolução / Em aberto)
  //   - Enquanto entregue < previsto, "Entregue total" fica desabilitado
  // ================================================================
  function renderizarChecklistCheckout(item) {
    const container = $('checklist-fields');
    if (!container) return;

    const checklist = Array.isArray(item.checklist) ? item.checklist : [];

    if (!checklist.length) {
      container.innerHTML = `
        <div class="checkout-item is-aberto">
          <div class="checkout-item-photo">
            <div class="placeholder">
              <i class="fa-solid fa-box-open"></i>
              <span>Sem itens</span>
            </div>
          </div>
          <div class="checkout-item-info">
            <strong>Nenhum item cadastrado</strong>
            <small>Esta entrega não possui checklist. Você pode finalizar apenas com o romaneio e a assinatura.</small>
          </div>
        </div>
      `;
      return;
    }

    // Estado inicial de cada item (do zero)
    window.__checkoutItens = checklist.map((entry, index) => {
      // ⚠️ ERP trabalha com quantidades inteiras.
      const previsto = Math.max(0, Math.round(Number(entry.quantidade_prevista || 0)));

      return {
        index,
        item_id: entry.item_id,
        referencia: entry.referencia || `Item ${entry.item_id}`,
        descricao: entry.descricao || '',
        quantidade_prevista: previsto,
        quantidade_entregue: 0,        // 🔥 começa em 0 (motorista vai registrar levas)
        status: 'pendente',            // pendente | entregue | faltante | devolvido | aberto
        motivo: null,
        foto_item: null,
        levas: []                      // 🔥 array de levas
      };
    });

    redesenharChecklistCheckout();
  }

  // ================================================================
  // REDESENHAR CHECKLIST (a partir do estado atual)
  //
  // 🔥 REESCRITO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   - Mostra barra de progresso por item (entregue / previsto)
  //   - Lista cada leva com foto + quantidade + botão remover
  //   - Botão "+ Registrar descida" enquanto entregue < previsto
  //   - Botões de decisão final: [Entregue total] [Faltante] [Devolução]
  //   - "Entregue total" desabilitado se entregue < previsto
  // ================================================================
  function redesenharChecklistCheckout() {
    const container = $('checklist-fields');
    if (!container) return;

    const itens = window.__checkoutItens || [];
    if (!itens.length) return;

    container.innerHTML = itens.map((it, idx) => {
      const entregue = Math.round(it.quantidade_entregue || 0);
      const previsto = Math.round(it.quantidade_prevista || 0);
      const falta = Math.max(0, previsto - entregue);
      const podeFechar = entregue >= previsto;
      const statusClass = `is-${it.status}`;
      const temLevas = it.levas.length > 0;

      // Barra de progresso do item
      const progresso = previsto > 0 ? Math.min(100, Math.round((entregue / previsto) * 100)) : 0;

      // Bloco de levas
      let levasHtml = '';
      if (temLevas) {
        levasHtml = `
          <div class="checkout-levas">
            <div class="checkout-levas-head">
              <span><i class="fa-solid fa-layer-group"></i> ${it.levas.length} leva${it.levas.length > 1 ? 's' : ''} registrada${it.levas.length > 1 ? 's' : ''}</span>
              <button type="button" class="checkout-levas-ver" data-ver-levas="${idx}">
                <i class="fa-solid fa-eye"></i> Ver
              </button>
            </div>
            ${it.levas.map((leva, lIdx) => `
              <div class="checkout-leva">
                <span class="checkout-leva-num">#${lIdx + 1}</span>
                <span class="checkout-leva-qtd">${leva.quantidade} un</span>
                <span class="checkout-leva-hora">${leva.hora || ''}</span>
                ${leva.foto_item ? `<span class="checkout-leva-foto-ok" title="Foto registrada"><i class="fa-solid fa-camera"></i></span>` : ''}
                <button type="button" class="checkout-leva-remover" data-remover-leva="${idx}:${lIdx}" title="Remover leva">
                  <i class="fa-solid fa-xmark"></i>
                </button>
              </div>
            `).join('')}
          </div>
        `;
      }

      return `
        <div class="checkout-item ${statusClass}" data-idx="${idx}">
                   <div class="checkout-item-photo ${it.foto_item ? 'has-foto' : ''}"
               title="${it.foto_item
                 ? `Última leva registrada (${it.levas.length}) — toque em Ver para ver todas`
                 : 'Nenhuma leva registrada ainda'}">
            ${it.foto_item
              ? `<img src="${it.foto_item}" alt="${escapeHtml(it.referencia)}">
                 <span class="badge"><i class="fa-solid fa-check"></i></span>`
              : `<div class="placeholder">
                   <i class="fa-solid fa-box"></i>
                   <span>Sem foto</span>
                 </div>`}
          </div>

          <div class="checkout-item-info">
            <span class="ref">${escapeHtml(it.referencia)}</span>
            <strong>${escapeHtml(it.descricao || it.referencia)}</strong>

            <div class="checkout-item-progresso">
              <div class="checkout-item-progresso-track">
                <span style="width:${progresso}%"></span>
              </div>
              <small>Entregue <b>${entregue}</b> de <b>${previsto}</b>${falta > 0 ? ` · Faltam <b class="falta">${falta}</b>` : ''}</small>
            </div>

            ${levasHtml}

            ${!podeFechar && it.status !== 'aberto' ? `
              <button type="button" class="checkout-btn-descida" data-registrar-descida="${idx}">
                <i class="fa-solid fa-truck-ramp-box"></i> Registrar descida
              </button>
            ` : ''}

            <div class="checkout-item-decisao">
              <button type="button"
                      class="checkout-btn-entregue ${it.status === 'entregue' ? 'ativo' : ''}"
                      data-finalizar-entregue="${idx}"
                      ${!podeFechar ? 'disabled title="Registre todas as descidas primeiro"' : ''}>
                <i class="fa-solid fa-check"></i> Entregue total
              </button>
              <button type="button"
                      class="checkout-btn-faltante ${it.status === 'faltante' ? 'ativo' : ''}"
                      data-finalizar-faltante="${idx}">
                <i class="fa-solid fa-triangle-exclamation"></i> Faltante
              </button>
              <button type="button"
                      class="checkout-btn-devolucao ${it.status === 'devolvido' ? 'ativo' : ''}"
                      data-finalizar-devolucao="${idx}">
                <i class="fa-solid fa-rotate-left"></i> Devolução
              </button>
            </div>

            ${it.motivo ? `<div class="checkout-item-motivo"><i class="fa-solid fa-circle-info"></i> ${escapeHtml(it.motivo)}</div>` : ''}
          </div>
        </div>
      `;
    }).join('');

    bindEventosChecklist();
  }

  // ================================================================
  // BIND DE EVENTOS DO CHECKLIST
  //
  // 🔥 REESCRITO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   Eventos suportados:
  //     - [data-registrar-descida]   → registrarDescida
  //     - [data-ver-levas]           → verLevasItem
  //     - [data-remover-leva]        → remove leva e recalcula
  //     - [data-finalizar-entregue]  → marca entregue (só se completo)
  //     - [data-finalizar-faltante]  → abre Swal de motivo e marca faltante
  //     - [data-finalizar-devolucao] → abre Swal de motivo e marca devolvido
  // ================================================================
  function bindEventosChecklist() {
    const container = $('checklist-fields');
    if (!container) return;

    // Remove listener antigo para evitar duplicação
    if (container.__bound) {
      container.removeEventListener('click', container.__bound);
    }
    const handler = (ev) => {
      const target = ev.target.closest('[data-registrar-descida],[data-ver-levas],[data-remover-leva],[data-finalizar-entregue],[data-finalizar-faltante],[data-finalizar-devolucao]');
      if (!target) return;
      ev.preventDefault();
      ev.stopPropagation();

      if (target.dataset.registrarDescida !== undefined) {
        const idx = Number(target.dataset.registrarDescida);
        registrarDescida(idx);
        return;
      }
      if (target.dataset.verLevas !== undefined) {
        const idx = Number(target.dataset.verLevas);
        verLevasItem(idx);
        return;
      }
      if (target.dataset.removerLeva) {
        const [idxStr, lIdxStr] = target.dataset.removerLeva.split(':');
        const idx = Number(idxStr);
        const lIdx = Number(lIdxStr);
        const item = window.__checkoutItens[idx];
        if (!item) return;
        const leva = item.levas[lIdx];
        if (!leva) return;

        const confirmar = typeof Swal !== 'undefined'
          ? Swal.fire({
              icon: 'warning',
              title: 'Remover leva?',
              html: `Leva <b>#${lIdx + 1}</b> (${leva.quantidade} un) será removida.`,
              showCancelButton: true,
              confirmButtonText: 'Remover',
              cancelButtonText: 'Cancelar',
              confirmButtonColor: '#c94b45'
            })
          : Promise.resolve({ isConfirmed: window.confirm('Remover esta leva?') });

        confirmar.then((r) => {
          if (!r.isConfirmed) return;
          item.levas.splice(lIdx, 1);
          item.quantidade_entregue = item.levas.reduce((s, l) => s + Math.round(l.quantidade), 0);
          // Foto principal = última leva restante
          item.foto_item = item.levas.length > 0 ? item.levas[item.levas.length - 1].foto_item : null;
          if (item.quantidade_entregue < item.quantidade_prevista) {
            if (item.status === 'entregue') item.status = 'pendente';
          }
          redesenharChecklistCheckout();
          atualizarProgressoCheckout();
          salvarRascunho();
        });
        return;
      }
      if (target.dataset.finalizarEntregue !== undefined) {
        const idx = Number(target.dataset.finalizarEntregue);
        finalizarItemEntregue(idx);
        return;
      }
      if (target.dataset.finalizarFaltante !== undefined) {
        const idx = Number(target.dataset.finalizarFaltante);
        finalizarItemDivergente(idx, 'faltante');
        return;
      }
      if (target.dataset.finalizarDevolucao !== undefined) {
        const idx = Number(target.dataset.finalizarDevolucao);
        finalizarItemDivergente(idx, 'devolvido');
        return;
      }
    };

    container.addEventListener('click', handler);
    container.__bound = handler;
  }
    // ================================================================
  // FINALIZAR ITEM COMO ENTREGUE TOTAL
  // (só faz sentido quando entregue === previsto)
  // ================================================================
  function finalizarItemEntregue(idx) {
    const item = window.__checkoutItens[idx];
    if (!item) return;

    if (Math.round(item.quantidade_entregue) < Math.round(item.quantidade_prevista)) {
      if (typeof Swal !== 'undefined') {
        Swal.fire('Faltam unidades', `Registre mais descidas ou marque como faltante.`, 'warning');
      }
      return;
    }

    item.status = 'entregue';
    item.motivo = null;

    redesenharChecklistCheckout();
    atualizarProgressoCheckout();
    salvarRascunho();
  }
    // ================================================================
  // FINALIZAR ITEM COMO FALTANTE OU DEVOLUÇÃO
  // Abre Swal de motivo obrigatório + observação opcional
  // ================================================================
  async function finalizarItemDivergente(idx, tipo) {
    const item = window.__checkoutItens[idx];
    if (!item) return;

    const motivosPorTipo = {
      faltante: {
        nao_carregou:    'Não carregou no veículo',
        erro_separacao:  'Erro de separação (veio a menos)',
        extravio:        'Extravio durante a rota',
        avaria:          'Produto avariado',
        outro:           'Outro (descrever)'
      },
      devolvido: {
        cliente_recusou:  'Cliente recusou',
        produto_avariado: 'Produto avariado / quebrado',
        validade_vencida: 'Validade vencida',
        pedido_errado:    'Produto errado',
        outro:            'Outro (descrever)'
      }
    };

    const labelTipo = tipo === 'faltante' ? 'Faltante' : 'Devolução';
    const falta = Math.max(0, Math.round(item.quantidade_prevista) - Math.round(item.quantidade_entregue));

    if (typeof Swal === 'undefined') {
      const tipoEscolhido = window.confirm(`${labelTipo}? OK = confirmar, Cancelar = sair`);
      if (!tipoEscolhido) return;
      const motivo = window.prompt('Motivo (obrigatório):') || 'Não informado';
      item.status = tipo;
      item.motivo = motivo;
      redesenharChecklistCheckout();
      atualizarProgressoCheckout();
      salvarRascunho();
      return;
    }

    const etapaMotivo = await Swal.fire({
      icon: 'question',
      title: `Motivo — ${labelTipo}`,
      html: `
        <div style="text-align:left;font-size:0.88rem;">
          <p style="margin:0 0 8px;"><b>${escapeHtml(item.referencia)}</b></p>
          <p style="margin:0 0 10px;color:#64748b;">${escapeHtml(item.descricao || '')}</p>
          ${falta > 0 ? `<p style="padding:8px 12px;background:#fef3c7;border-radius:8px;color:#92400e;margin:0 0 10px;">
            Faltam <b>${falta}</b> un · será marcado como <b>${labelTipo}</b>
          </p>` : ''}
        </div>
      `,
      input: 'select',
      inputOptions: motivosPorTipo[tipo],
      inputPlaceholder: 'Selecione um motivo',
      inputValidator: (v) => v ? null : 'Escolha um motivo.',
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#16845e',
      allowOutsideClick: false
    });

    if (!etapaMotivo.isConfirmed || !etapaMotivo.value) return;

    let motivoFinal = motivosPorTipo[tipo][etapaMotivo.value];

    if (etapaMotivo.value === 'outro') {
      const etapa3 = await Swal.fire({
        icon: 'question',
        title: 'Descreva o motivo',
        input: 'textarea',
        inputPlaceholder: 'Ex: ...',
        inputAttributes: { maxlength: 300 },
        inputValidator: (v) => (v && v.trim()) ? null : 'Digite uma descrição.',
        showCancelButton: true,
        confirmButtonText: 'Salvar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16845e',
        allowOutsideClick: false
      });

      if (!etapa3.isConfirmed || !etapa3.value) return;
      motivoFinal = etapa3.value.trim();
    }

    item.status = tipo;
    item.motivo = motivoFinal;

    redesenharChecklistCheckout();
    atualizarProgressoCheckout();
    salvarRascunho();

    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2500,
      timerProgressBar: true
    });
    Toast.fire({
      icon: 'success',
      title: `Item marcado como ${labelTipo.toLowerCase()}`
    });
  }
    // ================================================================
  // ATUALIZAR APENAS 1 CARD (evita redesenhar tudo e perder foco)
  // ================================================================
  function atualizarCardCheckout(idx) {
    const container = $('checklist-fields');
    if (!container) return;

    const card = container.querySelector(`.checkout-item[data-idx="${idx}"]`);
    const item = window.__checkoutItens[idx];
    if (!card || !item) return;

    // Atualiza classe de status
    card.className = `checkout-item is-${item.status}`;

    // Atualiza input
    const input = card.querySelector('input[data-qtd-index]');
    if (input) input.value = item.quantidade_entregue;

    // Atualiza select
    const select = card.querySelector('select[data-status-index]');
    if (select) select.value = item.status;

    // Recalcula aviso de quantidade
    const info = card.querySelector('.checkout-item-info');
    if (info) {
      const falta = Math.round(item.quantidade_prevista) - Math.round(item.quantidade_entregue);
      let aviso = info.querySelector('.checkout-item-erro');

      if (falta > 0) {
        if (!aviso) {
          aviso = document.createElement('div');
          aviso.className = 'checkout-item-erro';
          info.appendChild(aviso);
        }
        aviso.innerHTML = `
          <i class="fa-solid fa-triangle-exclamation"></i>
          Faltam ${falta} un
        `;
      } else if (aviso) {
        aviso.remove();
      }
    }
  }

    // ================================================================
  // PERGUNTAR STATUS DE DIVERGÊNCIA (2-3 etapas)
  //
  // ETAPA 1: o que aconteceu? (faltante / devolução / aberto)
  // ETAPA 2: por quê? (motivo obrigatório, lista por tipo)
  // ETAPA 3: se "Outro", texto livre obrigatório
  //
  // 🔥 REESCRITO 2026-09-24:
  //   - Motivo é OBRIGATÓRIO — sem ele, o item não é marcado
  //   - Motivos padronizados por tipo, facilitando o acerto do gestor
  //   - "Outro" abre campo de texto livre
  // ================================================================
  async function perguntarStatusDivergencia(idx) {
    const item = window.__checkoutItens[idx];
    if (!item) return;

    // Normaliza para inteiros ANTES de qualquer cálculo
    const previsto = Math.round(item.quantidade_prevista);
    const entregue = Math.round(item.quantidade_entregue);
    const falta    = previsto - entregue;

    // Fallback sem Swal
    if (typeof Swal === 'undefined') {
      const tipo = window.confirm('É faltante? OK = Faltante, Cancelar = Devolução')
        ? 'faltante'
        : 'devolvido';
      const motivo = window.prompt('Motivo (obrigatório):') || 'Não informado';
      item.status = tipo;
      item.motivo = motivo;
      atualizarCardCheckout(idx);
      atualizarProgressoCheckout();
      return;
    }

    // ─────────────────────────────────────────────────────────────
    // ETAPA 1: o que aconteceu com o item?
    // ─────────────────────────────────────────────────────────────
    const etapa1 = await Swal.fire({
      icon: 'question',
      title: 'O que aconteceu com o item?',
      html: `
        <div style="text-align:left; font-size:0.9rem;">
          <p style="margin:0 0 6px;"><b>${escapeHtml(item.referencia)}</b></p>
          <p style="margin:0 0 10px; color:#64748b;">${escapeHtml(item.descricao || '')}</p>
          <p style="margin:0 0 6px;">Previsto: <b>${previsto}</b> un</p>
          <p style="margin:0 0 12px;">Entregue: <b>${entregue}</b> un</p>
          <p style="margin:0; padding:10px 12px; background:#fef3c7; border-radius:8px; color:#92400e; font-size:0.82rem;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Faltam <b>${falta}</b> un.
          </p>
        </div>
      `,
      showDenyButton: true,
      showCancelButton: true,
      confirmButtonText: '⚠️ Faltante (não entreguei)',
      denyButtonText: '🔄 Devolução (cliente devolveu)',
      cancelButtonText: '🔓 Deixar em aberto',
      confirmButtonColor: '#d5a84b',
      denyButtonColor: '#e07a3c',
      cancelButtonColor: '#6b7280',
      allowOutsideClick: false
    });

    let tipo = null;
    if (etapa1.isConfirmed)      tipo = 'faltante';
    else if (etapa1.isDenied)    tipo = 'devolvido';
    else if (etapa1.dismiss === Swal.DismissReason.cancel) tipo = 'aberto';
    else return; // usuário fechou de outro jeito — não altera nada

    // ─────────────────────────────────────────────────────────────
    // ETAPA 2: qual o motivo? (OBRIGATÓRIO)
    // ─────────────────────────────────────────────────────────────
    const motivosPorTipo = {
      faltante: {
        nao_carregou:    'Não carregou no veículo',
        erro_separacao:  'Erro de separação (veio a menos)',
        extravio:        'Extravio durante a rota',
        avaria:          'Produto avariado',
        outro:           'Outro (descrever)'
      },
      devolvido: {
        cliente_recusou:  'Cliente recusou',
        produto_avariado: 'Produto avariado / quebrado',
        validade_vencida: 'Validade vencida',
        pedido_errado:    'Produto errado',
        outro:            'Outro (descrever)'
      },
      aberto: {
        aguardando_conf:  'Aguardando conferência do cliente',
        aguardando_aprov: 'Aguardando aprovação do gestor',
        cliente_duvida:   'Cliente com dúvida',
        outro:            'Outro (descrever)'
      }
    };

    const labelTipo = {
      faltante:  'Faltante',
      devolvido: 'Devolução',
      aberto:    'Em aberto'
    }[tipo];

    const etapa2 = await Swal.fire({
      icon: 'question',
      title: `Motivo — ${labelTipo}`,
      html: `
        <div style="text-align:left; font-size:0.88rem;">
          <p style="margin:0 0 10px; color:#64748b;">
            <b>${escapeHtml(item.referencia)}</b> — Faltam <b>${falta}</b> un
          </p>
          <p style="margin:0 0 6px; font-weight:700;">Por que aconteceu?</p>
        </div>
      `,
      input: 'select',
      inputOptions: motivosPorTipo[tipo],
      inputPlaceholder: 'Selecione um motivo',
      inputValidator: (value) => {
        if (!value) return 'Escolha um motivo para continuar.';
        return null;
      },
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Voltar',
      confirmButtonColor: '#16845e',
      cancelButtonColor: '#6b7280',
      allowOutsideClick: false
    });

    if (etapa2.dismiss === Swal.DismissReason.cancel) return; // voltou sem escolher
    if (!etapa2.isConfirmed || !etapa2.value)          return;

    const motivoKey = etapa2.value;

    // ─────────────────────────────────────────────────────────────
    // ETAPA 3 (condicional): se escolheu "outro", pede texto livre
    // ─────────────────────────────────────────────────────────────
    let motivoFinal = motivosPorTipo[tipo][motivoKey];

    if (motivoKey === 'outro') {
      const etapa3 = await Swal.fire({
        icon: 'question',
        title: 'Descreva o motivo',
        input: 'textarea',
        inputPlaceholder: 'Ex: cliente pediu para devolver porque fechou o mercado hoje...',
        inputAttributes: { maxlength: 500 },
        inputValidator: (value) => {
          if (!value || !value.trim()) return 'Digite uma descrição.';
          return null;
        },
        showCancelButton: true,
        confirmButtonText: 'Salvar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#16845e',
        allowOutsideClick: false
      });

      if (!etapa3.isConfirmed || !etapa3.value) return;
      motivoFinal = etapa3.value.trim();
    }

    // ─────────────────────────────────────────────────────────────
    // Aplica o resultado ao item
    // ─────────────────────────────────────────────────────────────
    item.status = tipo;
    item.motivo = motivoFinal;

    // Se foi para "aberto", zera quantidade entregue (não sabemos ainda)
    if (tipo === 'aberto') {
      item.quantidade_entregue = 0;
    }

    atualizarCardCheckout(idx);
    atualizarProgressoCheckout();

    // Feedback visual rápido
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2500,
      timerProgressBar: true
    });
    Toast.fire({
      icon: 'success',
      title: `Item marcado como ${labelTipo.toLowerCase()}`
    });
  }

    // ================================================================
  // ABRIR CÂMERA PARA FOTOGRAFAR UM ITEM
  // ================================================================
  function abrirCameraItemCheckout(idx) {
    const item = window.__checkoutItens[idx];
    if (!item) return;

    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.capture = 'environment';

    input.addEventListener('change', async (ev) => {
      const file = ev.target.files?.[0];
      if (!file) return;

      // Redimensiona (mesmo padrão da foto de perfil)
      const blob = await redimensionarImagem(file, 800, 0.82);
      if (!blob) {
        if (typeof Swal !== 'undefined') Swal.fire('Erro', 'Não foi possível processar a imagem.', 'error');
        return;
      }

      const dataUrl = await blobParaDataUrl(blob);
      item.foto_item = dataUrl;

      redesenharChecklistCheckout();
      atualizarProgressoCheckout();

      if (typeof Swal !== 'undefined') {
        const Toast = Swal.mixin({
          toast: true,
          position: 'top-end',
          showConfirmButton: false,
          timer: 2000,
          timerProgressBar: true
        });
        Toast.fire({
          icon: 'success',
          title: `📸 Foto do item registrada`
        });
      }
    });

    input.click();
  }

    // ================================================================
  // REGISTRAR DESCIDA (LEVA) — NOVO 2026-09-25
  //
  // Fluxo:
  //   1. Abre câmera do celular
  //   2. Redimensiona a foto
  //   3. Pede a quantidade da leva
  //   4. Pede observação opcional
  //   5. Soma ao total entregue
  //   6. Se entregue === previsto → auto-marca como 'entregue'
  // ================================================================
  async function registrarDescida(idx) {
    const item = window.__checkoutItens[idx];
    if (!item) return;

    const falta = Math.round(item.quantidade_prevista) - Math.round(item.quantidade_entregue);
    if (falta <= 0) {
      if (typeof Swal !== 'undefined') {
        Swal.fire('Item completo', 'Este item já foi totalmente entregue.', 'info');
      }
      return;
    }

    // ─── ETAPA 1: câmera ───
    const fotoBase64 = await capturarFotoLeva(item.referencia);
    if (!fotoBase64) return; // cancelou

    // ─── ETAPA 2: quantidade + observação ───
    if (typeof Swal === 'undefined') {
      // Fallback sem Swal
      const qtdStr = window.prompt(`Quantas unidades nesta descida? (faltam ${falta})`, String(falta));
      const qtd = Math.round(Number(qtdStr));
      if (!qtd || qtd <= 0 || qtd > falta) {
        window.alert('Quantidade inválida.');
        return;
      }
      item.levas.push({
        quantidade: qtd,
        foto_item: fotoBase64,
        registrado_em: new Date().toISOString(),
        hora: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        observacao: null
      });
      item.quantidade_entregue += qtd;
      item.foto_item = fotoBase64;

      if (item.quantidade_entregue >= item.quantidade_prevista) {
        item.status = 'entregue';
      }
      redesenharChecklistCheckout();
      atualizarProgressoCheckout();
      salvarRascunho();
      return;
    }

    const etapa2 = await Swal.fire({
      icon: 'question',
      title: 'Registrar descida',
      html: `
        <div style="text-align:left;font-size:0.9rem;">
          <p style="margin:0 0 6px;"><b>${escapeHtml(item.referencia)}</b></p>
          <p style="margin:0 0 12px;color:#64748b;">${escapeHtml(item.descricao || '')}</p>
          <p style="margin:0 0 12px;padding:8px 12px;background:#e8f4ef;border-radius:8px;color:#0f5f43;">
            Entregue até agora: <b>${item.quantidade_entregue}</b> de <b>${item.quantidade_prevista}</b><br>
            Falta entregar: <b>${falta}</b> un
          </p>
        </div>
      `,
      input: 'number',
      inputLabel: 'Quantas unidades nesta descida?',
      inputValue: falta,
      inputAttributes: {
        min: 1,
        max: falta,
        step: 1,
        inputmode: 'numeric'
      },
      inputValidator: (value) => {
        const v = Math.round(Number(value));
        if (!v || v <= 0) return 'Digite uma quantidade maior que zero.';
        if (v > falta) return `Você só pode registrar até ${falta} un nesta descida.`;
        return null;
      },
      showCancelButton: true,
      confirmButtonText: 'Próximo',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#16845e',
      allowOutsideClick: false
    });

    if (!etapa2.isConfirmed) return;

    const quantidadeLeva = Math.round(Number(etapa2.value));

    // ─── ETAPA 3: observação opcional ───
    const etapa3 = await Swal.fire({
      icon: 'question',
      title: 'Observação (opcional)',
      input: 'textarea',
      inputPlaceholder: 'Ex: descarregado pela entrada lateral, produto com avaria leve...',
      inputAttributes: { maxlength: 300 },
      showCancelButton: true,
      confirmButtonText: 'Registrar descida',
      cancelButtonText: 'Pular',
      confirmButtonColor: '#16845e',
      allowOutsideClick: false
    });

    const observacao = (etapa3.isConfirmed && etapa3.value) ? String(etapa3.value).trim() : null;

    // ─── APLICA ───
    const agora = new Date();
    item.levas.push({
      quantidade: quantidadeLeva,
      foto_item: fotoBase64,
      registrado_em: agora.toISOString(),
      hora: agora.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
      observacao
    });

    item.quantidade_entregue += quantidadeLeva;

    // Foto principal = última leva
    item.foto_item = fotoBase64;

    // 🔥 Auto-finaliza se atingiu o total
    if (item.quantidade_entregue >= item.quantidade_prevista) {
      item.status = 'entregue';
      item.motivo = null;
    } else {
      item.status = 'pendente';
    }

    redesenharChecklistCheckout();
    atualizarProgressoCheckout();
    salvarRascunho();

    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 2500,
      timerProgressBar: true
    });
    Toast.fire({
      icon: 'success',
      title: item.status === 'entregue'
        ? `✅ Item completo! (${item.quantidade_entregue}/${item.quantidade_prevista})`
        : `📦 Leva registrada (${item.quantidade_entregue}/${item.quantidade_prevista})`
    });
  }
    // ================================================================
  // CAPTURAR FOTO DA LEVA — helper interno
  // Abre câmera, redimensiona e retorna base64
  // ================================================================
  function capturarFotoLeva(referencia) {
    return new Promise((resolve) => {
      const input = document.createElement('input');
      input.type = 'file';
      input.accept = 'image/*';
      input.capture = 'environment';

      input.addEventListener('change', async (ev) => {
        const file = ev.target.files?.[0];
        if (!file) {
          resolve(null);
          return;
        }
        try {
          const blob = await redimensionarImagem(file, 800, 0.82);
          if (!blob) {
            if (typeof Swal !== 'undefined') Swal.fire('Erro', 'Não foi possível processar a imagem.', 'error');
            resolve(null);
            return;
          }
          const dataUrl = await blobParaDataUrl(blob);
          resolve(dataUrl);
        } catch (e) {
          console.warn('Erro ao processar foto da leva:', e);
          resolve(null);
        }
      });

      input.click();
    });
  }

    // ================================================================
  // VER LEVAS DO ITEM — modal com histórico de descidas
  // ================================================================
  function verLevasItem(idx) {
    const item = window.__checkoutItens[idx];
    if (!item || !item.levas.length) return;

    const totalEntregue = item.levas.reduce((s, l) => s + Math.round(l.quantidade), 0);

    if (typeof Swal === 'undefined') {
      window.alert(`Levas: ${item.levas.map((l, i) => `#${i + 1} ${l.quantidade}un`).join(', ')}`);
      return;
    }

    const levasHtml = item.levas.map((leva, i) => `
      <div style="display:flex;gap:10px;align-items:center;padding:8px 10px;border-bottom:1px solid #e5e7eb;">
        <span style="width:28px;height:28px;border-radius:50%;background:#16845e;color:#fff;font-weight:800;font-size:0.75rem;display:grid;place-items:center;">${i + 1}</span>
        <div style="flex:1;min-width:0;">
          <div style="font-weight:700;font-size:0.88rem;">${leva.quantidade} un</div>
          <div style="font-size:0.72rem;color:#64748b;">${escapeHtml(leva.hora || '')}${leva.observacao ? ' · ' + escapeHtml(leva.observacao) : ''}</div>
        </div>
        ${leva.foto_item ? `
          <button type="button" class="ver-leva-foto" data-foto="${leva.foto_item}" data-label="${escapeHtml(item.referencia)} · leva ${i + 1}"
                  style="border:0;background:transparent;cursor:pointer;padding:4px;font-size:1.1rem;color:#3b82f6;">
            <i class="fa-solid fa-image"></i>
          </button>
        ` : ''}
      </div>
    `).join('');

    Swal.fire({
      title: `Levas — ${escapeHtml(item.referencia)}`,
      html: `
        <div style="text-align:left;">
          <p style="margin:0 0 10px;font-size:0.85rem;color:#64748b;">
            Previsto: <b>${item.quantidade_prevista}</b> · Entregue até agora: <b>${totalEntregue}</b>
          </p>
          <div style="max-height:320px;overflow-y:auto;border:1px solid #e5e7eb;border-radius:10px;background:#fff;">
            ${levasHtml}
          </div>
        </div>
      `,
      width: '520px',
      confirmButtonText: 'Fechar',
      confirmButtonColor: '#10b981',
      didOpen: (modal) => {
        modal.querySelectorAll('.ver-leva-foto').forEach(btn => {
          btn.addEventListener('click', () => {
            abrirZoomFoto(btn.dataset.foto, btn.dataset.label);
          });
        });
      }
    });
  }
    // ================================================================
  // RASCUNHO DO CHECKOUT EM localStorage
  //
  // 🔥 NOVO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   - Salva o estado a cada mudança (leva, status, recebedor)
  //   - Recupera ao reabrir o modal
  //   - Se o rascunho for de outro dia, NÃO restaura — descarta
  //     e o item entra como faltante automático (ver abaixo)
  // ================================================================
  function rascunhoKey(deliveryId) {
    return `frota.motorista.${motoristaId}.checkout.rascunho.${deliveryId}`;
  }

  function salvarRascunho() {
    const form = $('checkout-form');
    const deliveryId = form?.dataset?.deliveryId;
    if (!deliveryId) return;

    const itens = window.__checkoutItens || [];
    if (!itens.length) return;

    // Se não há mudança relevante (sem leva, sem status final, sem motivo), NÃO salva
    const temConteudo = itens.some(it =>
      it.levas.length > 0
      || it.status === 'faltante'
      || it.status === 'devolvido'
      || it.status === 'aberto'
      || it.motivo
    );
    if (!temConteudo) return;

    const rascunho = {
      deliveryId: Number(deliveryId),
      clienteNome: entregas.find(e => Number(e.id) === Number(deliveryId))?.cliente_nome || '',
      criadoEm: new Date().toISOString(),
      data: new Date().toISOString().slice(0, 10), // YYYY-MM-DD
      receiverName: $('receiver-name')?.value || '',
      itens: itens.map(it => ({
        item_id: it.item_id,
        referencia: it.referencia,
        descricao: it.descricao,
        quantidade_prevista: it.quantidade_prevista,
        quantidade_entregue: it.quantidade_entregue,
        status: it.status,
        motivo: it.motivo,
        foto_item: it.foto_item,
        levas: it.levas
      }))
    };

    try {
      localStorage.setItem(rascunhoKey(deliveryId), JSON.stringify(rascunho));
    } catch (e) {
      console.warn('Não foi possível salvar rascunho:', e);
    }
  }

  function recuperarRascunho(deliveryId) {
    try {
      const raw = localStorage.getItem(rascunhoKey(deliveryId));
      if (!raw) return null;

      const rascunho = JSON.parse(raw);
      if (!rascunho || !Array.isArray(rascunho.itens)) return null;

      const hoje = new Date().toISOString().slice(0, 10);
      const mesmoDia = rascunho.data === hoje;

      return {
        mesmoDia,
        itens: rascunho.itens.map((it, idx) => ({
          index: idx,
          item_id: it.item_id,
          referencia: it.referencia,
          descricao: it.descricao,
          quantidade_prevista: Math.round(Number(it.quantidade_prevista || 0)),
          quantidade_entregue: Math.round(Number(it.quantidade_entregue || 0)),
          status: it.status || 'pendente',
          motivo: it.motivo || null,
          foto_item: it.foto_item || null,
          levas: Array.isArray(it.levas) ? it.levas : []
        })),
        receiverName: rascunho.receiverName || '',
        criadoEm: rascunho.criadoEm
      };
    } catch (e) {
      console.warn('Erro ao recuperar rascunho:', e);
      return null;
    }
  }

  function limparRascunho(deliveryId) {
    try {
      localStorage.removeItem(rascunhoKey(deliveryId));
    } catch (e) {}
  }
    // ================================================================
  // VERIFICAR RASCUNHOS DE OUTRO DIA — FALTANTE AUTOMÁTICO
  //
  // 🔥 NOVO 2026-09-25:
  //   Ao abrir o app, procura rascunhos no localStorage.
  //   Se houver rascunho de OUTRO dia, marca o item como faltante
  //   automaticamente e avisa o motorista.
  //
  //   Exceção: só marca como faltante se a entrega AINDA EXISTE no
  //   banco (senão simplesmente descarta o rascunho órfão).
  // ================================================================
  async function verificarRascunhosPendentes() {
    const prefixo = `frota.motorista.${motoristaId}.checkout.rascunho.`;
    const hoje = new Date().toISOString().slice(0, 10);
    const rascunhosAntigos = [];

    try {
      for (let i = 0; i < localStorage.length; i++) {
        const chave = localStorage.key(i);
        if (!chave || !chave.startsWith(prefixo)) continue;

        const raw = localStorage.getItem(chave);
        if (!raw) continue;

        try {
          const rascunho = JSON.parse(raw);
          if (!rascunho || !rascunho.data) continue;
          if (rascunho.data === hoje) continue; // rascunho de hoje, ignora
          if (!rascunho.itens || !rascunho.itens.length) {
            localStorage.removeItem(chave);
            continue;
          }
          rascunhosAntigos.push({ chave, rascunho });
        } catch (e) {
          // JSON inválido — descarta
          localStorage.removeItem(chave);
        }
      }
    } catch (e) {
      console.warn('Erro ao varrer rascunhos:', e);
      return;
    }

    if (!rascunhosAntigos.length) return;

    for (const { chave, rascunho } of rascunhosAntigos) {
      try {
        await finalizarRascunhoComoFaltante(rascunho);
        localStorage.removeItem(chave);
      } catch (e) {
        console.warn('Falha ao finalizar rascunho como faltante:', e);
        // Mantém o rascunho para tentar de novo na próxima
      }
    }

    // Aviso único ao motorista
    if (typeof Swal !== 'undefined') {
      await Swal.fire({
        icon: 'warning',
        title: 'Checkout não finalizado',
        html: `
          <div style="text-align:left;font-size:0.9rem;">
            <p>Você tinha <b>${rascunhosAntigos.length}</b> checkout(s) em andamento que não foram finalizados no dia anterior.</p>
            <p style="padding:10px 12px;background:#fef3c7;border-radius:8px;color:#92400e;margin-top:10px;">
              <i class="fa-solid fa-triangle-exclamation"></i>
              Os itens foram marcados como <b>faltantes automaticamente</b> e o gestor foi notificado.
            </p>
            <p style="color:#64748b;font-size:0.85rem;margin-top:10px;">
              Motivo registrado: <i>checkout_incompleto</i>.
            </p>
          </div>
        `,
        confirmButtonText: 'Entendi',
        confirmButtonColor: '#d5a84b',
        allowOutsideClick: false
      });
    }
  }

  // ================================================================
  // FINALIZAR RASCUNHO COMO FALTANTE
  //
  // Envia o rascunho antigo como um checkout parcial, marcando os
  // itens não concluídos como 'faltante' com motivo automático.
  // ================================================================
  async function finalizarRascunhoComoFaltante(rascunho) {
    const deliveryId = Number(rascunho.deliveryId);
    if (!deliveryId) throw new Error('Rascunho sem deliveryId');

    // Monta o checklist final: cada item vira:
    //   - 'entregue' se já estava completo no rascunho
    //   - 'faltante' nos demais casos
    const checklist = rascunho.itens.map(it => {
      const previsto = Math.round(Number(it.quantidade_prevista || 0));
      const entregue = Math.round(Number(it.quantidade_entregue || 0));
      const completo = entregue >= previsto;

      const ultimaLeva = (it.levas || []).slice(-1)[0];

      return {
        item_id: it.item_id,
        referencia: it.referencia,
        descricao: it.descricao,
        quantidade_prevista: previsto,
        quantidade_entregue: completo ? previsto : entregue,
        status: completo ? 'entregue' : 'faltante',
        motivo: completo ? null : 'checkout_incompleto',
        foto_item: ultimaLeva?.foto_item || it.foto_item || null,
        levas: it.levas || []
      };
    });

    const temFaltante = checklist.some(c => c.status === 'faltante');
    if (!temFaltante) return; // nada a fazer

    // Usa a última foto de leva como fallback do romaneio
    const primeiraLeva = rascunho.itens.flatMap(it => it.levas || [])[0];
    const fotoRomaneioFallback = primeiraLeva?.foto_item || null;
    if (!fotoRomaneioFallback) {
      throw new Error('Rascunho sem foto — não é possível finalizar como faltante');
    }

    const body = {
      motorista_id: motoristaId,
      desktop: false,
      nome_recebedor: rascunho.receiverName || 'Não informado (auto)',
      foto_romaneio: fotoRomaneioFallback,
      assinatura_base64: null, // sem assinatura: é auto-faltante
      checklist,
      tem_faltante: true,
      tem_devolucao: false,
      tem_aberto: false,
      observacao_automatica: `Faltante gerado automaticamente: checkout não finalizado em ${rascunho.criadoEm}.`,
      data_hora: new Date().toISOString()
    };

    const url = `${apiBase}/entregas/${deliveryId}/checkout`;

    const resp = await fetch(url, {
      method: 'POST',
      headers: { ...authHeaders(), 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify(body)
    });

    if (!resp.ok) {
      const txt = await resp.text().catch(() => '');
      throw new Error(`HTTP ${resp.status}: ${txt.substring(0, 200)}`);
    }

    return await resp.json().catch(() => ({}));
  }
  // ================================================================
  // ATUALIZAR BARRA DE PROGRESSO DO CHECKOUT
  //
  // 🔥 REESCRITO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   - Considera "pronto" o item que tem:
  //     • Status !== 'pendente' (ou seja, foi decidido)
  //     • E tem pelo menos 1 leva OU quantidade_entregue > 0 (quando entregue total)
  //   - Bloqueia submit se houver item 'pendente'
  //   - Avisa se houver item 'aberto' (aviso visual, mas permite enviar)
  // ================================================================
  function atualizarProgressoCheckout() {
    const itens = window.__checkoutItens || [];
    const total = itens.length;
    const fill  = $('checkout-progress-fill');
    const label = $('checkout-progress-label');
    const submit = $('checkout-submit');

    if (!total) {
      if (fill) fill.style.width = '0%';
      if (label) label.textContent = 'Sem itens para conferir';
      if (submit) submit.disabled = false;
      return;
    }

    // Item "pronto" = já teve decisão final (entregue / faltante / devolvido / aberto)
    const prontos = itens.filter((it) => {
      if (it.status === 'entregue')  return it.quantidade_entregue > 0 || it.levas.length > 0;
      if (it.status === 'faltante')  return true;
      if (it.status === 'devolvido') return true;
      if (it.status === 'aberto')    return true;
      return false;
    }).length;

    const pendentes = total - prontos;
    const percent = Math.round((prontos / total) * 100);

    if (fill) fill.style.width = percent + '%';

    if (label) {
      if (pendentes === 0) {
        const abertos = itens.filter(i => i.status === 'aberto').length;
        label.textContent = abertos > 0
          ? `${total} de ${total} itens · ${abertos} em aberto`
          : `${total} de ${total} itens prontos`;
      } else {
        label.textContent = `${prontos} de ${total} itens prontos · faltam ${pendentes}`;
      }
    }

    // Só libera o submit quando todos os itens tiverem decisão
    if (submit) submit.disabled = (pendentes > 0);
  }

    // ================================================================
  // HELPERS DE IMAGEM
  // ================================================================
  function redimensionarImagem(file, maxLado = 800, qualidade = 0.82) {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          let { width, height } = img;
          if (width > height && width > maxLado) {
            height = Math.round((height * maxLado) / width);
            width = maxLado;
          } else if (height > maxLado) {
            width = Math.round((width * maxLado) / height);
            height = maxLado;
          }
          const canvas = document.createElement('canvas');
          canvas.width = width;
          canvas.height = height;
          canvas.getContext('2d').drawImage(img, 0, 0, width, height);
          canvas.toBlob((b) => resolve(b), 'image/jpeg', qualidade);
        };
        img.onerror = () => resolve(null);
        img.src = e.target.result;
      };
      reader.onerror = () => resolve(null);
      reader.readAsDataURL(file);
    });
  }

  function blobParaDataUrl(blob) {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => resolve(e.target.result);
      reader.readAsDataURL(blob);
    });
  }
  function limparAssinatura() { const canvas = $('signature-pad'); canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); }
  function capturarAssinatura() { return $('signature-pad').toDataURL('image/png'); }

  // ================================================================
  // MONTAR PAYLOAD DO CHECKOUT (a partir do estado interno)
  //
  // 🔥 REESCRITO 2026-09-25 (ENTREGAS PARCIAIS / LEVAS):
  //   - Envia `levas[]` por item
  //   - A `foto_item` principal é a foto da última leva (fallback)
  //   - Valida: se status === 'entregue', precisa ter pelo menos 1 leva
  //   - Valida: se status !== 'entregue', precisa ter motivo
  // ================================================================
  async function dadosCheckout() {
    const itemId = Number($('checkout-form').dataset.deliveryId);
    const item = entregas.find((delivery) => Number(delivery.id) === itemId);
    if (!item) throw new Error('Entrega não encontrada');

    const nomeRecebedor = $('receiver-name').value.trim();
    if (!nomeRecebedor) throw new Error('Informe o nome de quem recebeu.');

    // Foto do romaneio
    const romaneioFile = $('romaneio-photo').files[0];
    if (!romaneioFile) throw new Error('Tire foto do romaneio assinado.');
    const romaneio = await lerArquivo(romaneioFile);
    if (!romaneio) throw new Error('Não foi possível processar a foto do romaneio.');

    // Assinatura
    const assinatura = capturarAssinatura();
    if (assinatura === 'data:image/png;base64,') throw new Error('Colete a assinatura do recebedor.');

    const itens = window.__checkoutItens || [];
    const checklist = [];
    const faltantes = [];

    for (const it of itens) {
      // 🔥 REGRAS:
      //   - 'entregue'  → precisa ter entregue === previsto
      //   - 'faltante'  → precisa ter motivo
      //   - 'devolvido' → precisa ter motivo
      //   - 'aberto'    → item segue para próximo checkout (mas grava com status aberto)
      //   - 'pendente'  → NUNCA deve chegar aqui (submit bloqueado)

      if (it.status === 'pendente') {
        throw new Error(`Item "${it.referencia}" ainda não tem decisão. Registre uma descida ou marque como faltante/devolução.`);
      }

      if (it.status === 'entregue' && Math.round(it.quantidade_entregue) < Math.round(it.quantidade_prevista)) {
        throw new Error(`Item "${it.referencia}" está marcado como entregue, mas ainda faltam ${it.quantidade_prevista - it.quantidade_entregue} un.`);
      }

      if ((it.status === 'faltante' || it.status === 'devolvido') && !it.motivo) {
        throw new Error(`Item "${it.referencia}" precisa de um motivo (faltante/devolução).`);
      }

      // Foto principal = última leva
      const ultimaLeva = it.levas.length > 0 ? it.levas[it.levas.length - 1] : null;
      const fotoPrincipal = ultimaLeva?.foto_item || it.foto_item || null;

      checklist.push({
        item_id: it.item_id,
        referencia: it.referencia,
        descricao: it.descricao,
        quantidade_prevista: Math.round(it.quantidade_prevista),
        quantidade_entregue: Math.round(it.quantidade_entregue),
        status: it.status,
        motivo: it.motivo,
        foto_item: fotoPrincipal,
        // 🔥 NOVO: array de levas com foto, quantidade e timestamp
        levas: it.levas.map(l => ({
          quantidade: Math.round(l.quantidade),
          foto_item: l.foto_item,
          registrado_em: l.registrado_em,
          observacao: l.observacao || null
        }))
      });

      if (it.status === 'faltante' || it.status === 'devolvido') {
        faltantes.push(it);
      }
    }

    return {
      motorista_id: motoristaId,
      desktop: false,
      nome_recebedor: nomeRecebedor,
      foto_romaneio: romaneio,
      assinatura_base64: assinatura,
      checklist,
      tem_faltante:  faltantes.some(x => x.status === 'faltante'),
      tem_devolucao: faltantes.some(x => x.status === 'devolvido'),
      tem_aberto:    itens.some(it => it.status === 'aberto'),
      data_hora: new Date().toISOString()
    };
  }

   // ================================================================
  // SELECIONAR MOTIVO DE FALHA (entrega não realizada)
  //
  // 🔥 AJUSTADO 2026-09-24:
  //   - Motivo obrigatório
  //   - Adiciona opções reais do dia a dia (veículo com problema,
  //     cliente fechado fora do horário, etc)
  //   - "Outro" abre campo de texto livre
  // ================================================================
  async function selecionarMotivoFalha() {
    const opcoes = {
      cliente_ausente:    'Cliente ausente no local',
      endereco_incorreto: 'Endereço incorreto / não localizado',
      recusado:           'Recebimento recusado',
      nao_localizado:     'Local não localizado',
      veiculo_problema:   'Problema mecânico no veículo',
      cliente_fechado:    'Cliente fechado (fora do horário)',
      outro:              'Outro (descrever)'
    };

    // Fallback sem Swal
    if (!window.Swal) {
      const motivo = window.prompt(
        'Motivo:\n' +
        'cliente_ausente, endereco_incorreto, recusado, nao_localizado, ' +
        'veiculo_problema, cliente_fechado ou outro'
      );
      return motivo && Object.keys(opcoes).includes(motivo) ? motivo : null;
    }

    const result = await Swal.fire({
      icon: 'question',
      title: 'Qual foi o problema?',
      input: 'select',
      inputOptions: opcoes,
      inputPlaceholder: 'Selecione um motivo',
      inputValidator: (value) => {
        if (!value) return 'Escolha um motivo para continuar.';
        return null;
      },
      showCancelButton: true,
      confirmButtonText: 'Confirmar',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#c94b45',
      allowOutsideClick: false
    });

    if (!result.isConfirmed || !result.value) return null;

    if (result.value === 'outro') {
      const etapa2 = await Swal.fire({
        icon: 'question',
        title: 'Descreva o problema',
        input: 'textarea',
        inputPlaceholder: 'Ex: portão estava trancado, liguei no telefone e não atenderam...',
        inputAttributes: { maxlength: 500 },
        inputValidator: (value) => {
          if (!value || !value.trim()) return 'Digite uma descrição.';
          return null;
        },
        showCancelButton: true,
        confirmButtonText: 'Registrar falha',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#c94b45',
        allowOutsideClick: false
      });

      if (!etapa2.isConfirmed || !etapa2.value) return null;
      return etapa2.value.trim();
    }

    return result.value;
  }

  // ================================================================
  // MONTAR PAYLOAD DE CADA AÇÃO
  //
  // 🔥 AJUSTADO 2026-09-24:
  //   - `falha` agora envia { motivo, observacao } e o backend aceita
  //     tanto a chave padronizada (cliente_ausente) quanto texto livre
  //     vindo de "Outro"
  // ================================================================
  async function obterDadosDaAcao(action) {
    if (action === 'checkout') return dadosCheckout();

    if (action === 'falha') {
      const motivo = await selecionarMotivoFalha();
      if (!motivo) return null;

      // Se o motivo for uma das chaves padronizadas, envia ele
      // direto. Se for texto livre (veio de "Outro"), envia como
      // observação e marca `motivo = 'outro'` para o backend
      // não recusar por não estar na lista de válidos.
      const motivosValidos = [
        'cliente_ausente', 'endereco_incorreto', 'recusado',
        'nao_localizado', 'veiculo_problema', 'cliente_fechado', 'outro'
      ];

      const ehPadronizado = motivosValidos.includes(motivo);

      return {
        motorista_id: motoristaId,
        motivo: ehPadronizado ? motivo : 'outro',
        observacao: ehPadronizado ? motivo : motivo,
        data_hora: new Date().toISOString()
      };
    }

    return {
      motorista_id: motoristaId,
      desktop: false,
      data_hora: new Date().toISOString()
    };
  }

    // ================================================================
  // 🎓 MODO TREINAMENTO — helpers de UX
  // ================================================================

  /**
   * Mostra o modal de confirmação "fora do raio".
   * Retorna true se o motorista confirmar que quer continuar.
   */
  async function confirmarForaDoRaio(aviso) {
    if (typeof Swal === 'undefined') {
      return window.confirm(
        `Você está a ${(aviso.distancia_atual_m / 1000).toFixed(1)} km do cliente.\n` +
        `Se continuar, sua posição GPS não será registrada.\n\nDeseja continuar?`
      );
    }

    const distanciaAtual = (Number(aviso.distancia_atual_m || 0) / 1000).toFixed(1);
    const distanciaMax   = (Number(aviso.distancia_maxima_m || 1000) / 1000).toFixed(1);

    const result = await Swal.fire({
      icon: 'warning',
      title: 'Você está longe do cliente',
      html: `
        <div style="text-align:left;font-size:0.9rem;line-height:1.5;">
          <p style="margin:0 0 6px;"><b>Distância atual:</b> ${distanciaAtual} km</p>
          <p style="margin:0 0 12px;"><b>Máximo permitido:</b> ${distanciaMax} km</p>
          <div style="padding:12px;background:#fef3c7;border-radius:10px;border:1px solid #fcd34d;color:#92400e;">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <b>Atenção:</b> se você continuar, sua posição GPS <b>NÃO</b> será registrada
            nesta etapa. O gestor verá que a ação foi feita fora do raio.
          </div>
          <p style="margin:12px 0 0;font-size:0.82rem;color:#64748b;">
            <i class="fa-solid fa-graduation-cap"></i>
            MODO TREINAMENTO — em produção você seria bloqueado.
          </p>
        </div>
      `,
      showCancelButton: true,
      confirmButtonText: 'Continuar mesmo assim',
      cancelButtonText: 'Cancelar',
      confirmButtonColor: '#d5a84b',
      cancelButtonColor: '#6d5725',
      allowOutsideClick: false
    });

    return result.isConfirmed;
  }

  /**
   * Toast discreto avisando que a ação foi salva offline.
   */
  function avisarSalvoOffline(action) {
    if (typeof Swal === 'undefined') return;
    if (window.__ultimoOfflineToast && Date.now() - window.__ultimoOfflineToast < 2500) return;
    window.__ultimoOfflineToast = Date.now();

    const labels = {
      checkin: 'Check-in',
      checkout: 'Checkout',
      falha: 'Falha'
    };
    const label = labels[action] || 'Ação';

    const Toast = Swal.mixin({
      toast: true,
      position: 'bottom',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      background: '#fff8e8',
      color: '#6d5725',
      iconColor: '#d5a84b',
      didOpen: (t) => {
        t.addEventListener('mouseenter', Swal.stopTimer);
        t.addEventListener('mouseleave', Swal.resumeTimer);
      }
    });

    Toast.fire({
      icon: 'info',
      title: `${label} salvo no aparelho`,
      text: 'Será sincronizado quando a internet voltar.'
    });
  }

  async function executarAcao(id, action) {
    const position = await getPositionFast();
    const body = { ...(await obterDadosDaAcao(action)), ...position };
    if (!body) return;

    const request = {
      id,
      endpoint: `${apiBase}/entregas/${id}/${action}`,
      action,
      body,
      operation_id: operationId(id, action, body)
    };
    request.body.operation_id = request.operation_id;

    // ── Offline: enfileira direto
    if (!online()) {
      const queue = getQueue();
      if (!queue.some((item) => item.operation_id === request.operation_id)) {
        saveQueue([...queue, request]);
      }
      aplicarStatusLocal(id, action);
      avisarSalvoOffline(action);
      return;
    }

    try {
      let response = await fetch(request.endpoint, {
        method: 'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(request.body)
      });

      // ════════════════════════════════════════════════════════════
      // 🎓 MODO TREINAMENTO: backend devolve 422 com code=FORA_DO_RAIO
      //    (não bloqueia em produção, só no modo treino para o motorista
      //     entender o que aconteceria)
      // ════════════════════════════════════════════════════════════
      if (response.status === 422) {
        const aviso = await response.json().catch(() => ({}));

        if (aviso.code === 'FORA_DO_RAIO') {
          const confirmou = await confirmarForaDoRaio(aviso);
          if (!confirmou) return;

          // Reenvia com a flag de confirmação
          request.body.confirmar_fora_raio = true;
          request.body.operation_id = operationId(id, action, request.body);

          response = await fetch(request.endpoint, {
            method: 'POST',
            headers: { ...authHeaders(), 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(request.body)
          });
        }
      }

      // ── Erro 4xx: bloqueio real do servidor
      if (response.status >= 400 && response.status < 500) {
        let mensagem = 'Ação não aceita pelo servidor.';
        try { const payload = await response.json(); mensagem = payload.error || mensagem; } catch {}
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Não foi possível confirmar', text: mensagem, confirmButtonText: 'OK' });
        } else {
          window.alert(mensagem);
        }
        return;
      }

      if (!response.ok) throw new Error('Ação não aceita');
      aplicarStatusLocal(id, action);

    } catch {
      // ── Falha de rede/5xx: enfileira
      const queue = getQueue();
      if (!queue.some((item) => item.operation_id === request.operation_id)) {
        saveQueue([...queue, request]);
      }
      aplicarStatusLocal(id, action);
      avisarSalvoOffline(action);
    }
  }

  async function salvarOrdem() {
    const embarqueId = entregas[0]?.embarque_id;
    if (!embarqueId) return;
    const operationIdValue = `${motoristaId}:ordem:${embarqueId}:${entregas.map((item) => item.id).join('-')}`;
    const body = { ordem: entregas.map((item) => item.id), operation_id: operationIdValue, expected_updated_at: rotaVersion };
    const request = { endpoint: `${apiBase}/embarques/${embarqueId}/reordenar`, method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body };
    if (!online()) {
      const queue = getQueue().filter((item) => item.type !== 'reordenar');
      saveQueue([...queue, { ...request, type: 'reordenar', operation_id: operationIdValue }]);
      return;
    }
    const response = await fetch(request.endpoint, request);
    if (response.status === 409) {
      $('motorista-status').textContent = 'Conflito: o gestor alterou a rota. Atualize antes de salvar novamente.';
      throw new Error('A rota foi alterada pelo gestor');
    }
    if (!response.ok) throw new Error('Não foi possível salvar a ordem');
  }

  async function mover(index, delta) {
    const target = index + delta;
    if (target < 0 || target >= entregas.length) return;
    [entregas[index], entregas[target]] = [entregas[target], entregas[index]];
    persist();
    try { await salvarOrdem(); }
    catch { $('motorista-status').textContent = 'Ordem alterada localmente; será salva quando houver conexão'; }
  }

  async function sincronizarFila() {
    if (!online()) return;
    await queueReady;

    const remaining = [];
    const falhas = [];

    for (const request of [...getQueue()]) {
      try {
        const response = await fetch(request.endpoint, {
          method: 'POST',
          headers: { ...authHeaders(), 'Content-Type': 'application/json' },
          credentials: 'include',
          body: JSON.stringify(request.body)
        });

        if (response.status === 409) {
          request.conflict = true;
          remaining.push(request);
          $('motorista-status').textContent = 'Há uma alteração de rota pendente de revisão.';

        } else if (response.status === 422) {
          // 🎓 Modo treino: o backend avisa "fora do raio".
          // Confirma automaticamente (o motorista já autorizou no momento da ação).
          const aviso = await response.json().catch(() => ({}));
          if (aviso.code === 'FORA_DO_RAIO') {
            request.body.confirmar_fora_raio = true;
            request.body.operation_id = operationId(request.id, request.action, request.body);

            const retry = await fetch(request.endpoint, {
              method: 'POST',
              headers: { ...authHeaders(), 'Content-Type': 'application/json' },
              credentials: 'include',
              body: JSON.stringify(request.body)
            });

            if (retry.ok) {
              // ok, não devolve à fila
              continue;
            }
          }
          remaining.push(request);

        } else if (!response.ok) {
          if (response.status >= 400 && response.status < 500) {
            let mensagem = 'Uma ação pendente não pôde ser confirmada e foi descartada.';
            try {
              const dados = await response.json();
              if (dados?.message) mensagem = dados.message;
              else if (dados?.error) mensagem = dados.error;
            } catch {}
            falhas.push({ id: request.id, action: request.action, mensagem });
          } else {
            remaining.push(request);
          }
        }
      } catch {
        remaining.push(request);
      }
    }

    await salvarFila(remaining);
    atualizarConflitoRota();
    await enviarFotoPendente();

    if (falhas.length) {
      await carregarEntregas();
      const resumo = falhas
        .map((falha) => `Parada (${escapeHtml(falha.action)}): ${escapeHtml(falha.mensagem)}`)
        .join('<br>');

      if (window.Swal) {
        await Swal.fire({
          icon: 'warning',
          title: 'Ações pendentes não confirmadas',
          html: resumo,
          confirmButtonText: 'OK'
        });
      } else {
        window.alert(resumo.replace(/<br>/g, '\n'));
      }
    }
  }

  async function abrirPendenciasSeExistirem() {
    const pendencias = safeParse(sessionStorage.getItem('cadfrota_pendencias_erp') || 'null', null);
    if (!pendencias) return;
    sessionStorage.removeItem('cadfrota_pendencias_erp');
    if (!(pendencias.motoristas?.length || pendencias.veiculos?.length)) return;
    await Swal.fire({
      icon: 'info',
      title: 'Cadastro de Frota aberto',
      html: 'Os dados pendentes do ERP foram enviados para o módulo de Cadastro de Frota e estão pré-preenchidos.',
      confirmButtonText: 'OK'
    });
    window.open('/portal/modules/frota/cadastro-frota.php', '_blank', 'noopener,noreferrer');
  }

  // ================================================================
  // HANDLERS
  // ================================================================
  $('driver-menu-btn')?.addEventListener('click', abrirDrawer);
  $('drawer-close')?.addEventListener('click', fecharDrawer);
  $('driver-drawer-backdrop')?.addEventListener('click', fecharDrawer);
  $('perfil-modal-close')?.addEventListener('click', fecharPerfilModal);
  $('perfil-modal-fechar-btn')?.addEventListener('click', fecharPerfilModal);
    // 🔥 Busca na lista
  $('route-search-input')?.addEventListener('input', () => {
    clearTimeout(window.__buscaRotaTimer);
    window.__buscaRotaTimer = setTimeout(aplicarBuscaRota, 250);
  });

  $('route-search-clear')?.addEventListener('click', () => {
    const inp = $('route-search-input');
    if (inp) inp.value = '';
    aplicarBuscaRota();
    inp?.focus();
  });

  // 🔥 Toggle do mapa
  restaurarEstadoMapa();
  $('input-foto-perfil')?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (file) processarFotoPerfil(file);
  });

  document.addEventListener('click', (event) => {
    const btn = event.target.closest('[data-drawer-action]');
    if (!btn) return;
    const acao = btn.dataset.drawerAction;
    if (acao === 'perfil') abrirPerfilModal();
    else if (acao === 'foto') abrirSeletorFoto();
    else if (acao === 'tema') { alternarTema(); }
    else if (acao === 'sync') { fecharDrawer(); sincronizarFila().then(() => carregarEntregas()); }
    else if (acao === 'logout') fazerLogout();
  });

  document.addEventListener('click', (event) => {
    const driverCard = event.target.closest('[data-driver-id]');
    if (driverCard) {
      selecionarMotorista(driverCard.dataset.driverId);
      return;
    }

     // ── Botões de contato (WhatsApp / Ligar) — Pacote 3
    const contactBtn = event.target.closest('[data-contact]');
    if (contactBtn) {
      if (isAdminApp) return;
      const id = Number(contactBtn.dataset.id);
      if (contactBtn.dataset.contact === 'whatsapp') abrirWhatsAppCliente(id);
      else if (contactBtn.dataset.contact === 'ligar') ligarParaCliente(id);
      return;
    }

    // ── Botões de ação (Cheguei / Entregue / Problema / Subir / Descer)
    const button = event.target.closest('[data-action], [data-order]');
    if (button) {
      if (isAdminApp) return;
      if (button.dataset.action === 'checkout') {
        abrirCheckout(entregas.find((item) => Number(item.id) === Number(button.dataset.id)));
      } else if (button.dataset.action) {
        executarAcao(button.dataset.id, button.dataset.action);
      }
      if (button.dataset.order) mover(Number(button.dataset.index), button.dataset.order === 'up' ? -1 : 1);
      return;
    }

    // ── Pacote 1.5: clique no header de um card CONCLUÍDO abre o modal de detalhes
    const cardHead = event.target.closest('.delivery-card-head');
    if (cardHead && !isAdminApp) {
      const card = cardHead.closest('.delivery-card');
      if (card) {
        const status = card.dataset.status;
        const concluida = status === 'entregue'
                       || status === 'entregue_com_problema'
                       || status === 'falha'
                       || status === 'cancelada';
        if (concluida) {
          event.preventDefault();
          event.stopPropagation();
          abrirDetalhesEntrega(Number(card.dataset.entregaId));
        }
      }
    }
  });

  $('next-stop-action')?.addEventListener('click', () => {
    const item = entregas.find((delivery) => Number(delivery.id) === Number($('next-stop-action').dataset.id));
    if (!item) return;
    if (item.status === 'em_entrega') abrirCheckout(item);
    else executarAcao(item.id, 'checkin');
  });

  $('checkout-cancel')?.addEventListener('click', () => { $('checkout-modal').hidden = true; });
  $('signature-clear')?.addEventListener('click', limparAssinatura);

    // ================================================================
  // SUBMIT DO CHECKOUT
  // ================================================================
  $('checkout-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();

    const id = Number($('checkout-form').dataset.deliveryId);
    if (!id) return;

    let dados;
    try {
      dados = await dadosCheckout();
    } catch (err) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'warning', title: 'Atenção', text: err.message, confirmButtonColor: '#d5a84b' });
      } else {
        window.alert(err.message);
      }
      return;
    }

    const position = await getPositionFast();
    const body = { ...dados, ...position };

    const request = {
      id,
      endpoint: `${apiBase}/entregas/${id}/checkout`,
      action: 'checkout',
      body,
      operation_id: operationId(id, 'checkout', body),
    };
    request.body.operation_id = request.operation_id;

    // ── Fecha o modal e aplica status local
    $('checkout-modal').hidden = true;
    document.body.classList.remove('driver-modal-open');
    aplicarStatusLocal(id, 'checkout');

    // ── Offline
    if (!online()) {
      saveQueue([...getQueue(), request]);
      avisarSalvoOffline('checkout');
      return;
    }

    // ── Online
    try {
      let response = await fetch(request.endpoint, {
        method: 'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body),
      });

      // Modo treino: 422 com code FORA_DO_RAIO
      if (response.status === 422) {
        const aviso = await response.json().catch(() => ({}));
        if (aviso.code === 'FORA_DO_RAIO') {
          const confirmou = await confirmarForaDoRaio(aviso);
          if (!confirmou) {
            aplicarStatusLocal(id, 'reverter-pendente');
            return;
          }
          request.body.confirmar_fora_raio = true;
          request.body.operation_id = operationId(id, 'checkout', request.body);

          response = await fetch(request.endpoint, {
            method: 'POST',
            headers: { ...authHeaders(), 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(request.body),
          });
        }
      }

      if (response.status >= 400 && response.status < 500) {
        let mensagem = 'Checkout não aceito pelo servidor.';
        try {
          const payload = await response.json();
          mensagem = payload.error || mensagem;
        } catch {}
        aplicarStatusLocal(id, 'reverter-pendente');
        if (typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Não foi possível confirmar', text: mensagem, confirmButtonText: 'OK' });
        } else {
          window.alert(mensagem);
        }
        return;
      }

      if (!response.ok) throw new Error('Checkout não aceito');

      // Sucesso — limpa rascunho e recarrega
  const payload = await response.json();

  // 🔥 NOVO: limpa rascunho após checkout confirmado
  limparRascunho(id);

  if (typeof Swal !== 'undefined') {
    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
    });
    Toast.fire({
      icon: 'success',
      title: 'Entrega concluída!',
      text: payload?.data?.total_levas > 0
        ? `${payload.data.total_levas} leva(s) registrada(s).`
        : (payload?.data?.embarque_auto_finalizado
            ? 'Todas as entregas do embarque foram concluídas.'
            : '')
    });
  }
  await carregarEntregas();
    } catch (err) {
      const queue = getQueue();
      if (!queue.some((item) => item.operation_id === request.operation_id)) {
        saveQueue([...queue, request]);
      }
      avisarSalvoOffline('checkout');
    }
  });

  // Botões de cancelar
  $('checkout-cancel')?.addEventListener('click', () => {
    $('checkout-modal').hidden = true;
    document.body.classList.remove('driver-modal-open');
  });
  $('checkout-cancel-2')?.addEventListener('click', () => {
    $('checkout-modal').hidden = true;
    document.body.classList.remove('driver-modal-open');
  });

  const signatureCanvas = $('signature-pad');
  let drawing = false;
  signatureCanvas?.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    drawing = true;
    signatureCanvas.setPointerCapture(event.pointerId);
    const rect = signatureCanvas.getBoundingClientRect();
    const ctx = signatureCanvas.getContext('2d');
    ctx.beginPath();
    ctx.moveTo((event.clientX - rect.left) * signatureCanvas.width / rect.width, (event.clientY - rect.top) * signatureCanvas.height / rect.height);
  });
  signatureCanvas?.addEventListener('pointermove', (event) => {
    if (!drawing) return;
    const rect = signatureCanvas.getBoundingClientRect();
    const ctx = signatureCanvas.getContext('2d');
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineTo((event.clientX - rect.left) * signatureCanvas.width / rect.width, (event.clientY - rect.top) * signatureCanvas.height / rect.height);
    ctx.stroke();
  });
  signatureCanvas?.addEventListener('pointerup', () => { drawing = false; });

  $('btn-refresh-route')?.addEventListener('click', carregarEntregas);
  $('route-conflict-refresh')?.addEventListener('click', async () => { await carregarEntregas(); $('route-conflict').hidden = true; });
  $('route-conflict-discard')?.addEventListener('click', async () => {
    await salvarFila(getQueue().filter((item) => !(item.type === 'reordenar' && item.conflict)));
    $('route-conflict').hidden = true;
    $('motorista-status').textContent = 'Ordenação local descartada; rota do gestor preservada.';
    await carregarEntregas();
  });

  $('btn-confirm-driver')?.addEventListener('click', async () => {
    if (!podeTrocarMotorista) return;
    const val = Number($('driver-select-input')?.value || 0);
    if (!val) {
      if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Selecione um motorista', confirmButtonText: 'OK' });
      else alert('Selecione um motorista');
      return;
    }
    await selecionarMotorista(val);
  });

  $('driver-admin-search')?.addEventListener('input', renderizarPainelAdmin);

  $('next-stop-waze')?.addEventListener('click', () => {
    const proxima = entregas.find((item) => !['entregue', 'entregue_com_problema'].includes(item.status));
    if (proxima) abrirNavegacao(proxima, 'waze');
  });
  $('next-stop-gmaps')?.addEventListener('click', () => {
    const proxima = entregas.find((item) => !['entregue', 'entregue_com_problema'].includes(item.status));
    if (proxima) abrirNavegacao(proxima, 'gmaps');
  });

  $('btn-sync-now')?.addEventListener('click', async () => {
    $('btn-sync-now').disabled = true;
    $('btn-sync-now').innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sincronizando...';
    await sincronizarFila();
    await carregarEntregas();
    $('btn-sync-now').disabled = false;
    $('btn-sync-now').innerHTML = '<i class="fa-solid fa-rotate"></i> Sincronizar Agora';
  });

   // ================================================================
  // PREVIEW DA FOTO DO ROMANEIO
  // ================================================================
  $('romaneio-photo')?.addEventListener('change', async (event) => {
    const file = event.target.files[0];
    const previewContainer = $('romaneio-preview');
    const previewImg = $('romaneio-preview-img');
    const removeBtn = $('romaneio-remove');

    if (!file) {
      if (previewContainer) previewContainer.hidden = true;
      if (removeBtn) removeBtn.hidden = true;
      return;
    }

    // Redimensiona
    const blob = await redimensionarImagem(file, 1200, 0.85);
    if (!blob) {
      if (typeof Swal !== 'undefined') Swal.fire('Erro', 'Não foi possível processar a imagem.', 'error');
      return;
    }

    const dataUrl = await blobParaDataUrl(blob);
    if (previewImg) previewImg.src = dataUrl;
    if (previewContainer) previewContainer.hidden = false;
    if (removeBtn) removeBtn.hidden = false;
  });

  $('romaneio-remove')?.addEventListener('click', (ev) => {
    ev.preventDefault();
    ev.stopPropagation();
    $('romaneio-photo').value = '';
    $('romaneio-preview').hidden = true;
    $('romaneio-remove').hidden = true;
  });

  window.addEventListener('online', () => {
    setConnectionState();
    carregarPerfil();
    sincronizarFila();
    carregarEntregas();
    carregarNotificacoes();
    iniciarPollingCobli();
  });
  window.addEventListener('offline', () => {
    setConnectionState();
    atualizarMapaRota();
    atualizarCardCaminhao();
  });

  navigator.serviceWorker?.addEventListener('message', (event) => {
    if (event.data?.type === 'frota-offline-sync') sincronizarFila();
  });

  if ('serviceWorker' in navigator) {
    const appBase = window.location.pathname.split('/portal/')[0];
    navigator.serviceWorker.register(`${appBase}/portal/modules/frota/service-worker.js`).catch(() => {});
  }

  if (!isAdminApp && navigator.geolocation && online()) {
    watchId = navigator.geolocation.watchPosition(
      (position) => salvarPosicaoMotorista({ latitude: position.coords.latitude, longitude: position.coords.longitude, precisao: position.coords.accuracy }),
      () => {},
      { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 }
    );
  }

 // ─── BOOT ───
  aplicarTema();
  setConnectionState();
  bindNextStopContact();       
  bindChipDistancia();            
  iniciarTimerChipDistancia();    
  carregarPainelAdmin();
  carregarPerfil();
  carregarListaMotoristas();
  inicializarMapa();
  carregarEntregas();
  carregarNotificacoes();
  sincronizarFila();

  // 🔥 NOVO 2026-09-25: verifica rascunhos de outro dia e converte em faltante automático
  verificarRascunhosPendentes().catch(err => {
    console.warn('Falha ao verificar rascunhos pendentes:', err);
  });

  abrirPendenciasSeExistirem();
  iniciarPollingCobli();
    // ================================================================
  // EXPOSIÇÃO PARA DEBUG / TESTES (DevTools)
  // Em produção não é chamado por ninguém, mas ajuda a inspecionar.
  // ================================================================
  if (window.location.hostname === 'localhost' || window.location.search.includes('debug=1')) {
      window.__motoristaDebug = {
      abrirCheckout,
      registrarDescida,
      verLevasItem,
      finalizarItemEntregue,
      finalizarItemDivergente,
      salvarRascunho,
      recuperarRascunho,
      limparRascunho,
      verificarRascunhosPendentes,
      get itens() { return window.__checkoutItens; },
      get entregas() { return entregas; },
      get motoristaId() { return motoristaId; },
      // 🔥 Pacote 3 — M3 (2026-09-25): helpers de debug para o toast
      render,
      detectarMudancaProximaParada,
      get ultimaProximaId() { return ultimaProximaId; },
      get primeiraRenderizacao() { return primeiraRenderizacao; }
    };
    console.log('🐞 Debug exposto em window.__motoristaDebug');
  }

    // ════════════════════════════════════════════════════════════════
  // PACOTE 1.5 — CABEÇALHO DO EMBARQUE (colapsável) + FILTRO DE CHIPS
  // ════════════════════════════════════════════════════════════════

  // Estado global do filtro de status (não persistido — reseta ao recarregar)
  // Nota: `filtroStatusAtual`, `ultimaProximaId`, `primeiraRenderizacao`,
  // `ultimoToastProximaEm`, `chipDistanciaTimer` e as constantes
  // associadas foram movidas para o topo do IIFE (junto das outras
  // variáveis de estado). Motivo: o BOOT roda antes das declarações,
  // e `let` não tem hoisting de valor (temporal dead zone).

  /**
   * Renderiza o card de cabeçalho do embarque com os dados de `embarque_info`.
   * Se `info` for null/vazio, esconde o card inteiro.
   */
  function renderCabecalhoEmbarque(info) {
    const card = $('embarque-header-card');
    if (!card) return;

    if (!info || !info.numero_embarque) {
      card.hidden = true;
      return;
    }

    card.hidden = false;

    // ── Cabeçalho colapsado
    const numeroEl = $('embarque-header-numero');
    if (numeroEl) numeroEl.textContent = info.numero_embarque || '—';

    const erpEl = $('embarque-header-erp');
    if (erpEl) {
      if (info.erp_id) {
        erpEl.textContent = 'ERP #' + info.erp_id;
        erpEl.hidden = false;
      } else {
        erpEl.hidden = true;
      }
    }

    const concluidas = Number(info.entregas_concluidas || 0);
    const total = Number(info.total_entregas || 0);
    const progressoEl = $('embarque-header-progresso');
    if (progressoEl) progressoEl.textContent = `${concluidas}/${total}`;

    const statusEl = $('embarque-header-status');
    if (statusEl) {
      const label = {
        'planejado':    'Planejado',
        'em_andamento': 'Em andamento',
        'finalizado':   'Finalizado',
        'cancelado':    'Cancelado',
        'problema':     'Problema'
      }[info.status_embarque] || info.status_embarque || '';

      if (label) {
        statusEl.textContent = label;
        statusEl.className = 'embarque-header-status status-' + (info.status_embarque || 'planejado');
        statusEl.hidden = false;
      } else {
        statusEl.hidden = true;
      }
    }

    // ── Corpo expandido
    const setTxt = (id, val) => { const el = $(id); if (el) el.textContent = val; };

    setTxt('embarque-header-rota', info.rota || '—');
    setTxt('embarque-header-veiculo',
      [info.veiculo_placa, info.veiculo_modelo].filter(Boolean).join(' · ') || '—');
    setTxt('embarque-header-motorista', info.motorista_nome || '—');
    setTxt('embarque-header-valor', formatarMoedaMotorista(info.valor_total || 0));
    setTxt('embarque-header-peso', formatarPesoMotorista(info.peso_total || 0));
    setTxt('embarque-header-saida', info.data_saida ? formatarDataMotorista(info.data_saida) : '—');

    // Barra de progresso interna
    const percentual = total > 0 ? Math.round((concluidas / total) * 100) : 0;
    const bar = $('embarque-header-progress-bar');
    if (bar) bar.style.width = percentual + '%';

    const label = $('embarque-header-progress-label');
    if (label) label.textContent = `${percentual}% concluído`;
  }

  /**
   * Alterna o estado colapsado/expandido do cabeçalho do embarque.
   * Persiste em localStorage por motorista.
   */
  function toggleCabecalhoEmbarque() {
    const card = $('embarque-header-card');
    if (!card || card.hidden) return;

    const isColapsado = card.classList.toggle('is-collapsed');
    const head = $('embarque-header-head');
    if (head) head.setAttribute('aria-expanded', String(!isColapsado));

    try {
      localStorage.setItem(
        `frota.motorista.${motoristaId}.cabecalhoAberto`,
        isColapsado ? '0' : '1'
      );
    } catch (e) { /* silencioso */ }
  }

  /**
   * Restaura o estado do cabeçalho salvo em localStorage.
   */
  function restaurarEstadoCabecalho() {
    const card = $('embarque-header-card');
    if (!card || card.hidden) return;

    let aberto = false;
    try {
      aberto = localStorage.getItem(`frota.motorista.${motoristaId}.cabecalhoAberto`) === '1';
    } catch (e) { /* silencioso */ }

    card.classList.toggle('is-collapsed', !aberto);

    const head = $('embarque-header-head');
    if (head) head.setAttribute('aria-expanded', String(aberto));
  }

  /**
   * Aplica um filtro de status à lista de paradas.
   */
  function aplicarFiltroStatus(status, btn) {
    filtroStatusAtual = status || 'todas';

    document.querySelectorAll('.route-filter-chips .filter-chip').forEach(chip => {
      const ativo = chip.dataset.status === filtroStatusAtual;
      chip.classList.toggle('active', ativo);
      chip.setAttribute('aria-pressed', String(ativo));
    });

    render();
  }

  /**
   * Atualiza os contadores dos chips com base nas entregas atuais.
   */
  function atualizarContadoresChips() {
    const contagens = {
      todas: entregas.length,
      pendente: 0,
      em_entrega: 0,
      entregue: 0,
      problema: 0
    };

    entregas.forEach(e => {
      if (e.status === 'entregue' || e.status === 'entregue_com_problema') {
        contagens.entregue++;
      }
      if (e.status === 'falha' || e.status === 'entregue_com_problema') {
        contagens.problema++;
      }
      if (e.status === 'pendente')    contagens.pendente++;
      if (e.status === 'em_entrega')  contagens.em_entrega++;
    });

    Object.entries(contagens).forEach(([key, valor]) => {
      const el = $('chip-count-' + key);
      if (el) el.textContent = valor;
    });
  }

  // ════════════════════════════════════════════════════════════════
  // PACOTE 2 — MEU PAINEL (dashboard pessoal do motorista)
  // 2026-09-25
  //
  // Estrutura de dados esperada do backend:
  //   {
  //     resumo_mes: { total_entregas, entregas_concluidas, falhas, valor_total, tempo_medio_min, km_rodados },
  //     cobli:      { score, variacao, km_rodados, eventos, velocidade_media, atualizado_em },
  //     ranking:    { posicao, total_motoristas, percentil },
  //     caminhao_hoje: { id, placa, modelo, marca, odometro_atual, velocidade_atual,
  //                      latitude, longitude, ultima_posicao, tempo_parado_min, status } | null,
  //     ultimos_embarques: [ { id, numero_embarque, status, data_saida, data_retorno,
  //                            distancia_total_km, veiculo_placa, total_entregas, entregas_concluidas } ]
  //   }
  // ════════════════════════════════════════════════════════════════

  // Cache do painel (5 min) — decisão E do Pacote 2
  // Nota: PAINEL_CACHE_TTL foi movido para o topo do IIFE.
  const painelCacheKey = () => `frota.motorista.${motoristaId}.painel.cache`;

  /** Lê o cache (se válido) do painel. Retorna null se expirou ou não existe. */
  function lerCachePainel() {
    try {
      const raw = sessionStorage.getItem(painelCacheKey());
      if (!raw) return null;
      const cache = JSON.parse(raw);
      if (!cache || !cache.expiraEm || Date.now() > cache.expiraEm) {
        sessionStorage.removeItem(painelCacheKey());
        return null;
      }
      return cache.dados;
    } catch (e) {
      return null;
    }
  }

  /** Salva o painel no cache com TTL de 5 min. */
  function salvarCachePainel(dados) {
    try {
      sessionStorage.setItem(painelCacheKey(), JSON.stringify({
        expiraEm: Date.now() + PAINEL_CACHE_TTL,
        dados
      }));
    } catch (e) { /* silencioso */ }
  }

  /** Limpa o cache do painel (forçar reload). */
  function limparCachePainel() {
    try { sessionStorage.removeItem(painelCacheKey()); } catch (e) {}
  }

  /** Mostra o loading e esconde o conteúdo. */
  function mostrarLoadingPainel() {
    const loading = $('painel-loading');
    const conteudo = $('painel-conteudo');
    if (loading) loading.hidden = false;
    if (conteudo) conteudo.hidden = true;
  }

  /** Esconde o loading e mostra o conteúdo. */
  function mostrarConteudoPainel() {
    const loading = $('painel-loading');
    const conteudo = $('painel-conteudo');
    if (loading) loading.hidden = true;
    if (conteudo) conteudo.hidden = false;
  }

  /**
   * Entry point — chamado por mudarAbaMotorista('painel', ...).
   * Usa cache de 5 min se disponível. Senão, busca do backend.
   */
  async function carregarPainelMotorista(forceRefresh = false) {
    if (!motoristaId) {
      const loading = $('painel-loading');
      if (loading) {
        loading.innerHTML = `
          <i class="fa-solid fa-circle-exclamation"></i>
          <span>Nenhum motorista vinculado.</span>
        `;
      }
      return;
    }

    // 1. Tenta cache
    if (!forceRefresh) {
      const cache = lerCachePainel();
      if (cache) {
        renderizarPainel(cache);
        return;
      }
    }

    // 2. Mostra loading e busca do backend
    mostrarLoadingPainel();

    try {
      const resp = await fetch(`${apiBase}/motoristas/${motoristaId}/painel`, {
        headers: authHeaders(),
        credentials: 'include'
      });

      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

      const payload = await resp.json();
      if (!payload.success || !payload.data) {
        throw new Error(payload.error || 'Resposta inválida do servidor');
      }

      salvarCachePainel(payload.data);
      renderizarPainel(payload.data);

    } catch (err) {
      console.warn('[Painel] Erro ao carregar:', err);
      const loading = $('painel-loading');
      if (loading) {
        loading.innerHTML = `
          <i class="fa-solid fa-wifi"></i>
          <span>Não foi possível carregar o painel.</span>
          <small style="color:var(--driver-muted);font-size:.75rem;">Verifique a conexão e tente novamente.</small>
          <button type="button" class="route-tool" style="margin-top:8px;" onclick="carregarPainelMotorista(true)">
            <i class="fa-solid fa-rotate-right"></i> Tentar novamente
          </button>
        `;
      }
    }
  }

  /**
   * Renderiza o painel inteiro a partir do payload do backend.
   */
  function renderizarPainel(dados) {
    renderScorePainel(dados.cobli || {});
    renderRankingPainel(dados.ranking || {});
    renderResumoMesPainel(dados.resumo_mes || {});
    renderCaminhaoPainel(dados.caminhao_hoje || null);
    renderUltimosEmbarquesPainel(dados.ultimos_embarques || []);

    // Rodapé: quando foi atualizado
    const rodape = $('painel-atualizado-em');
    if (rodape) {
      rodape.textContent = 'Atualizado em ' + new Date().toLocaleString('pt-BR', {
        day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit'
      });
    }

    mostrarConteudoPainel();
  }

  // ─────────────────────────────────────────────────────────────
  // BLOCO 1: SCORE DE DESEMPENHO (hero)
  // ─────────────────────────────────────────────────────────────
  function renderScorePainel(cobli) {
    const body   = document.querySelector('#painel-score-card .painel-score-body');
    const vazio  = $('painel-score-vazio');
    const gauge  = $('painel-score-fill');
    const numero = $('painel-score-numero');
    const periodo = $('painel-score-periodo');

    const temScore = cobli && cobli.score !== null && cobli.score !== undefined && !Number.isNaN(Number(cobli.score));

    // ── Estado vazio (sem dados Cobli)
    if (!temScore) {
      if (body)  body.hidden = true;
      if (vazio) vazio.hidden = false;
      return;
    }

    if (body)  body.hidden = false;
    if (vazio) vazio.hidden = true;

    // ── Gauge circular
    const score = Math.max(0, Math.min(100, Number(cobli.score)));
    const CIRCUNFERENCIA = 2 * Math.PI * 52; // r=52 → ~326.7
    const offset = CIRCUNFERENCIA * (1 - score / 100);

    if (gauge) {
      gauge.setAttribute('stroke-dasharray', String(CIRCUNFERENCIA.toFixed(2)));
      gauge.setAttribute('stroke-dashoffset', String(offset.toFixed(2)));
      gauge.classList.remove('is-warn', 'is-danger');
      if (score < 40) gauge.classList.add('is-danger');
      else if (score < 70) gauge.classList.add('is-warn');
    }
    if (numero) numero.textContent = Math.round(score);

    // ── Info lateral
    if ($('painel-score-km'))        $('painel-score-km').textContent        = formatarKmPainel(cobli.km_rodados);
    if ($('painel-score-eventos'))   $('painel-score-eventos').textContent   = String(cobli.eventos ?? 0);
    if ($('painel-score-velocidade')) $('painel-score-velocidade').textContent = (Number(cobli.velocidade_media) || 0).toFixed(0) + ' km/h';

    // ── Variação (opcional)
    const variacaoEl = $('painel-score-variacao');
    const variacaoTxt = $('painel-score-variacao-texto');
    if (variacaoEl && variacaoTxt) {
      const v = Number(cobli.variacao);
      if (!Number.isNaN(v) && v !== 0) {
        variacaoEl.hidden = false;
        variacaoEl.classList.remove('is-up', 'is-down');
        const subiu = v > 0;
        variacaoEl.classList.add(subiu ? 'is-up' : 'is-down');
        variacaoEl.querySelector('i').className = subiu
          ? 'fa-solid fa-arrow-trend-up'
          : 'fa-solid fa-arrow-trend-down';
        variacaoTxt.textContent = (subiu ? '+' : '') + v.toFixed(1) + ' pts vs. período anterior';
      } else {
        variacaoEl.hidden = true;
      }
    }

    // ── Período
    if (periodo && cobli.atualizado_em) {
      periodo.textContent = 'atualizado em ' + formatarDataCurtaPainel(cobli.atualizado_em);
    } else if (periodo) {
      periodo.textContent = 'últimos 30 dias';
    }
  }

  // ─────────────────────────────────────────────────────────────
  // BLOCO 2: RANKING (anônimo)
  // ─────────────────────────────────────────────────────────────
  function renderRankingPainel(ranking) {
    const titulo = $('painel-ranking-titulo');
    const sub    = $('painel-ranking-subtitulo');
    const posBox = $('painel-ranking-posicao');
    const num    = $('painel-ranking-numero');
    const total  = $('painel-ranking-total');

    const temPosicao = ranking
      && ranking.posicao !== null
      && ranking.posicao !== undefined
      && Number(ranking.posicao) > 0;

    if (!temPosicao) {
      if (titulo) titulo.textContent = 'Sem posição ainda';
      if (sub)    sub.textContent    = 'Complete viagens para entrar no ranking.';
      if (posBox) posBox.hidden = true;
      return;
    }

    const pos = Number(ranking.posicao);
    const tot = Number(ranking.total_motoristas) || 0;
    const perc = Number(ranking.percentil);

    // Texto amigável conforme a posição
    let msg;
    if (pos === 1)      msg = 'Você lidera o ranking! 🥇';
    else if (pos <= 3)  msg = 'Você está no pódio! 🏆';
    else if (pos <= Math.max(3, Math.ceil(tot * 0.10))) msg = 'Você está no top 10% da equipe!';
    else if (pos <= Math.ceil(tot * 0.30)) msg = 'Você está no top 30% da equipe.';
    else                msg = 'Continue assim — cada viagem melhora sua posição.';

    if (titulo) titulo.textContent = msg;
    if (sub && !Number.isNaN(perc)) {
      sub.textContent = `Você está à frente de ${perc}% dos motoristas.`;
    } else if (sub) {
      sub.textContent = 'Ranking calculado com base no seu desempenho de segurança.';
    }

    if (posBox) posBox.hidden = false;
    if (num)   num.textContent = `${pos}º`;
    if (total) total.textContent = `de ${tot} motoristas`;
  }

  // ─────────────────────────────────────────────────────────────
  // BLOCO 3: RESUMO DO MÊS
  // ─────────────────────────────────────────────────────────────
  function renderResumoMesPainel(resumo) {
    // Mês atual por extenso
    const mesEl = $('painel-resumo-mes');
    if (mesEl) {
      mesEl.textContent = new Date().toLocaleDateString('pt-BR', {
        month: 'long', year: 'numeric'
      });
    }

    const concluidas = Number(resumo.entregas_concluidas) || 0;
    const total      = Number(resumo.total_entregas) || 0;
    const falhas     = Number(resumo.falhas) || 0;
    const valor      = Number(resumo.valor_total) || 0;
    const km         = Number(resumo.km_rodados) || 0;
    const tempoMed   = Number(resumo.tempo_medio_min) || 0;

    if ($('painel-resumo-entregas')) $('painel-resumo-entregas').textContent = concluidas;
    if ($('painel-resumo-valor'))    $('painel-resumo-valor').textContent    = formatarMoedaPainel(valor);
    if ($('painel-resumo-km'))       $('painel-resumo-km').textContent       = formatarKmPainel(km);
    if ($('painel-resumo-tempo'))    $('painel-resumo-tempo').textContent    = formatarMinutosPainel(tempoMed);

    // Alerta se houver falhas
    const alerta = $('painel-resumo-alerta');
    const alertaTxt = $('painel-resumo-alerta-texto');
    if (alerta && alertaTxt) {
      if (falhas > 0) {
        alerta.hidden = false;
        alertaTxt.textContent = falhas === 1
          ? '1 entrega com problema neste mês'
          : `${falhas} entregas com problema neste mês`;
      } else {
        alerta.hidden = true;
      }
    }
  }

  // ─────────────────────────────────────────────────────────────
  // BLOCO 4: CAMINHÃO HOJE
  // ─────────────────────────────────────────────────────────────
  function renderCaminhaoPainel(caminhao) {
    const card = $('painel-caminhao-card');
    if (!card) return;

    // Sem veículo vinculado
    if (!caminhao) {
      card.hidden = true;
      return;
    }

    card.hidden = false;

    // ── Cabeçalho
    if ($('painel-caminhao-placa'))  $('painel-caminhao-placa').textContent  = caminhao.placa || '—';
    if ($('painel-caminhao-modelo')) {
      $('painel-caminhao-modelo').textContent = [caminhao.marca, caminhao.modelo].filter(Boolean).join(' ') || '—';
    }

    // ── Status (badge)
    const statusEl = $('painel-caminhao-status');
    if (statusEl) {
      statusEl.classList.remove('is-parado', 'is-off');
      const statusLabelMap = {
        'em_rota':     'Em rota',
        'disponivel':  'Disponível',
        'manutencao':  'Manutenção',
        'inativo':     'Inativo'
      };
      const label = statusLabelMap[caminhao.status] || (caminhao.status || '—');
      statusEl.textContent = label;

      const vel = Number(caminhao.velocidade_atual) || 0;
      if (caminhao.status === 'em_rota' && vel < 1) statusEl.classList.add('is-parado');
      if (caminhao.status === 'inativo' || caminhao.status === 'manutencao') statusEl.classList.add('is-off');
    }

    // ── Grid de dados
    if ($('painel-caminhao-odometro')) {
      const odo = Number(caminhao.odometro_atual) || 0;
      $('painel-caminhao-odometro').textContent = odo > 0
        ? odo.toLocaleString('pt-BR') + ' km'
        : '—';
    }

    if ($('painel-caminhao-velocidade')) {
      const v = Number(caminhao.velocidade_atual) || 0;
      $('painel-caminhao-velocidade').textContent = v.toFixed(0) + ' km/h';
    }

    if ($('painel-caminhao-parado')) {
      const min = caminhao.tempo_parado_min;
      $('painel-caminhao-parado').textContent = (min !== null && min !== undefined)
        ? formatarMinutosPainel(min)
        : '—';
    }

    if ($('painel-caminhao-localizacao')) {
      const el = $('painel-caminhao-localizacao');
      if (caminhao.latitude !== null && caminhao.longitude !== null
          && !Number.isNaN(Number(caminhao.latitude)) && !Number.isNaN(Number(caminhao.longitude))) {
        el.innerHTML = '<a href="https://www.google.com/maps?q=' + caminhao.latitude + ',' + caminhao.longitude + '" target="_blank" rel="noopener" style="color:var(--driver-success);text-decoration:none;font-weight:800;">Ver no mapa <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:.7rem;"></i></a>';
      } else {
        el.textContent = 'Sem sinal';
      }
    }
  }

  // ─────────────────────────────────────────────────────────────
  // BLOCO 5: ÚLTIMOS EMBARQUES
  // ─────────────────────────────────────────────────────────────
  function renderUltimosEmbarquesPainel(embarques) {
    const list = $('painel-embarques-list');
    if (!list) return;

    if (!embarques.length) {
      list.innerHTML = `
        <div class="empty-state" style="padding:24px 12px;">
          <i class="fa-solid fa-clock-rotate-left" style="font-size:2rem;color:var(--driver-border);display:block;margin-bottom:8px;"></i>
          <span style="font-size:.82rem;">Nenhum embarque finalizado ainda.</span>
        </div>
      `;
      return;
    }

    list.innerHTML = embarques.map((e) => {
      const status = e.status || 'finalizado';
      const statusIcon = {
        'finalizado': 'fa-flag-checkered',
        'problema':   'fa-triangle-exclamation',
        'cancelado':  'fa-ban'
      }[status] || 'fa-flag-checkered';

      const statusClass = status === 'problema'  ? 'is-problema'
                        : status === 'cancelado' ? 'is-cancelado'
                        : '';

      const total      = Number(e.total_entregas) || 0;
      const concluidas = Number(e.entregas_concluidas) || 0;
      const perc       = total > 0 ? Math.round((concluidas / total) * 100) : 0;

      const dataSaida = e.data_saida ? formatarDataCurtaPainel(e.data_saida) : '—';

      return `
        <div class="painel-embarque-item">
          <div class="painel-embarque-item-icon ${statusClass}">
            <i class="fa-solid ${statusIcon}"></i>
          </div>
          <div class="painel-embarque-item-info">
            <strong>${escapeHtml(e.numero_embarque || 'Embarque #' + e.id)}</strong>
            <small>
              <span><i class="fa-regular fa-calendar"></i>${escapeHtml(dataSaida)}</span>
              ${e.veiculo_placa ? `<span><i class="fa-solid fa-truck"></i>${escapeHtml(e.veiculo_placa)}</span>` : ''}
            </small>
          </div>
          <div class="painel-embarque-item-progresso">
            <strong>${concluidas}/${total}</strong>
            <small>${perc}% concluído</small>
          </div>
        </div>
      `;
    }).join('');
  }

  // ─────────────────────────────────────────────────────────────
  // HELPERS DE FORMATAÇÃO DO PAINEL
  // ─────────────────────────────────────────────────────────────
  function formatarMoedaPainel(valor) {
    const n = Number(valor);
    if (isNaN(n) || n === 0) return 'R$ 0';
    if (n >= 1000) {
      return 'R$ ' + (n / 1000).toFixed(1).replace('.', ',') + 'k';
    }
    return 'R$ ' + n.toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function formatarKmPainel(km) {
    const n = Number(km);
    if (isNaN(n) || n === 0) return '0 km';
    if (n >= 1000) return (n / 1000).toFixed(1).replace('.', ',') + ' mil km';
    return n.toFixed(0) + ' km';
  }

  function formatarMinutosPainel(min) {
    const n = Number(min);
    if (isNaN(n) || n <= 0) return '—';
    if (n < 60) return Math.round(n) + ' min';
    const h = Math.floor(n / 60);
    const m = Math.round(n % 60);
    return m > 0 ? `${h}h ${m}min` : `${h}h`;
  }

  function formatarDataCurtaPainel(str) {
    if (!str) return '—';
    try {
      // ISO ou YYYY-MM-DD
      const d = new Date(String(str).length === 10 ? str + 'T00:00:00' : str);
      if (isNaN(d.getTime())) return str;
      return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: '2-digit' });
    } catch { return str; }
  }
  // ════════════════════════════════════════════════════════════════
  // PACOTE 3 — M1+M2: CONTATO COM CLIENTE (WhatsApp + Ligar)
  // 2026-09-25
  //
  // - Botões no next-stop-card (topo) e no card expandido.
  // - WhatsApp: link wa.me com mensagem pronta (motorista revisa e envia).
  // - Ligar: link tel: direto.
  // - Só exibe botões se a entrega tiver cliente_telefone.
  // ════════════════════════════════════════════════════════════════

  /** Normaliza telefone para formato wa.me (dígitos, com DDI 55). */
  function normalizarTelefoneWhatsApp(telefone) {
    if (!telefone) return null;
    const digitos = String(telefone).replace(/\D/g, '');
    if (digitos.length < 10) return null;

    // Já tem DDI 55 → usa como está
    if (digitos.startsWith('55') && digitos.length >= 12) return digitos;

    // Sem DDI: adiciona 55 (Brasil)
    return '55' + digitos;
  }

  /** Normaliza telefone para link tel: (mantém formato discável). */
  function normalizarTelefoneLigar(telefone) {
    if (!telefone) return null;
    const digitos = String(telefone).replace(/\D/g, '');
    if (digitos.length < 8) return null;
    return digitos;
  }

  /** Monta a mensagem pronta de WhatsApp para uma entrega. */
  function montarMensagemWhatsApp(entrega) {
    const nomeCliente = (entrega.cliente_nome || '').split(' ')[0] || 'cliente';
    const nomeMotorista = (perfilDados?.fantasia
                        || window.MOTORISTA_NOME_SESSAO
                        || 'o motorista').split(' ')[0];

    // Pedidos: pode ser 1 ou vários
    const pedidosArr = String(entrega.pedidos_ids || '')
      .split(',')
      .map(s => s.trim())
      .filter(Boolean);

    let pedidosTxt;
    if (pedidosArr.length === 0) {
      pedidosTxt = `Pedido #${entrega.pedido_id || entrega.id}`;
    } else if (pedidosArr.length === 1) {
      pedidosTxt = `Pedido #${pedidosArr[0]}`;
    } else {
      pedidosTxt = `Pedidos #${pedidosArr[0]} + ${pedidosArr.length - 1} outros`;
    }

    // Endereço curto: rua + número
    const enderecoCurto = [entrega.endereco, entrega.numero]
      .filter(Boolean)
      .join(', ') || 'endereço cadastrado';

    return [
      `Olá, ${nomeCliente}! 👋`,
      ``,
      `Aqui é o ${nomeMotorista}, motorista da Nutricional.`,
      ``,
      `Estou a caminho com o seu pedido e devo chegar em breve.`,
      ``,
      `📦 ${pedidosTxt}`,
      `📍 ${enderecoCurto}`,
      ``,
      `Se precisar de algo, é só me chamar por aqui. Até já! 🚛`
    ].join('\n');
  }

  /** Abre WhatsApp com a mensagem pronta. Se não tiver telefone, avisa. */
  function abrirWhatsAppCliente(entregaId) {
    const entrega = entregas.find(e => Number(e.id) === Number(entregaId));
    if (!entrega) return;

    const tel = normalizarTelefoneWhatsApp(entrega.cliente_telefone);
    if (!tel) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'info',
          title: 'Sem telefone cadastrado',
          text: 'Esta entrega não tem telefone do cliente. Verifique no cadastro.',
          confirmButtonText: 'OK',
          confirmButtonColor: '#16845e'
        });
      } else {
        window.alert('Esta entrega não tem telefone do cliente cadastrado.');
      }
      return;
    }

    const mensagem = montarMensagemWhatsApp(entrega);
    const url = `https://wa.me/${tel}?text=${encodeURIComponent(mensagem)}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  }

  /** Abre o discador do celular. Se não tiver telefone, avisa. */
  function ligarParaCliente(entregaId) {
    const entrega = entregas.find(e => Number(e.id) === Number(entregaId));
    if (!entrega) return;

    const tel = normalizarTelefoneLigar(entrega.cliente_telefone);
    if (!tel) {
      if (typeof Swal !== 'undefined') {
        Swal.fire({
          icon: 'info',
          title: 'Sem telefone cadastrado',
          text: 'Esta entrega não tem telefone do cliente. Verifique no cadastro.',
          confirmButtonText: 'OK',
          confirmButtonColor: '#16845e'
        });
      } else {
        window.alert('Esta entrega não tem telefone do cliente cadastrado.');
      }
      return;
    }

    window.location.href = `tel:${tel}`;
  }

  /** Gera o HTML dos botões de contato (para usar no card de cada entrega). */
  function renderBotoesContato(entrega) {
    const temTelefone = !!normalizarTelefoneWhatsApp(entrega.cliente_telefone);

    return `
      <div class="delivery-card-contact">
        <button type="button"
                class="contact-btn is-whatsapp"
                data-contact="whatsapp"
                data-id="${entrega.id}"
                ${!temTelefone ? 'disabled title="Sem telefone cadastrado"' : 'title="Enviar WhatsApp ao cliente"'}>
          <i class="fa-brands fa-whatsapp"></i> WhatsApp
        </button>
        <button type="button"
                class="contact-btn is-call"
                data-contact="ligar"
                data-id="${entrega.id}"
                ${!temTelefone ? 'disabled title="Sem telefone cadastrado"' : 'title="Ligar para o cliente"'}>
          <i class="fa-solid fa-phone"></i> Ligar
        </button>
      </div>
    `;
  }

  /** Bind estático dos botões do next-stop-card. */
  function bindNextStopContact() {
    $('next-stop-whatsapp')?.addEventListener('click', () => {
      const id = $('next-stop-action')?.dataset.id;
      if (id) abrirWhatsAppCliente(id);
    });
    $('next-stop-ligar')?.addEventListener('click', () => {
      const id = $('next-stop-action')?.dataset.id;
      if (id) ligarParaCliente(id);
    });
  }
    // ════════════════════════════════════════════════════════════════
  // PACOTE 3 — M3: TOAST "PRÓXIMA PARADA MUDOU"
  // 2026-09-25
  //
  // Detecta quando o id da próxima parada muda entre dois renders.
  // NÃO dispara:
  //   - na primeira renderização (evita toast toda vez que abre o app)
  //   - se o motorista acabou de reordenar/entregar (ele já sabe)
  //   - se já mostrou um toast nos últimos 5s (anti-spam)
  // ════════════════════════════════════════════════════════════════

  /** Marca a "primeira renderização" como concluída. */
  function marcarPrimeiraRenderizacaoFeita() {
    if (primeiraRenderizacao) {
      primeiraRenderizacao = false;
    }
  }

  /** Dispara o toast de "próxima parada atualizada". */
  function mostrarToastProximaParada(entrega) {
    const agora = Date.now();
    if (agora - ultimoToastProximaEm < TOAST_PROXIMA_INTERVALO_MS) return;
    ultimoToastProximaEm = agora;

    if (typeof Swal === 'undefined') {
      // Fallback silencioso (não polui UX com alert nativo)
      console.log('[M3] Próxima parada mudou para:', entrega.cliente_nome);
      return;
    }

    const Toast = Swal.mixin({
      toast: true,
      position: 'top-end',
      showConfirmButton: false,
      timer: 4000,
      timerProgressBar: true,
      didOpen: (t) => {
        t.addEventListener('mouseenter', Swal.stopTimer);
        t.addEventListener('mouseleave', Swal.resumeTimer);
        // Clicar no toast leva direto para a parada
        t.style.cursor = 'pointer';
        t.addEventListener('click', () => {
          Swal.close();
          const card = document.querySelector(`.delivery-card[data-entrega-id="${entrega.id}"]`);
          if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.classList.add('is-expanded');
          }
        });
      }
    });

    Toast.fire({
      icon: 'info',
      title: 'Próxima parada atualizada',
      html: `<span style="font-size:.82rem;">Agora é: <b>${escapeHtml(entrega.cliente_nome || `Entrega #${entrega.id}`)}</b></span>`
    });
  }

  /**
   * Compara a próxima parada atual com a anterior.
   * Chamada no fim de render(), depois do list.innerHTML ser montado.
   *
   * Não precisa saber quem causou a mudança — apenas detecta.
   * A "primeira renderização" é ignorada para não tocar sempre que abrir o app.
   */
  function detectarMudancaProximaParada() {
    // Recalcula a próxima parada igual ao render() faz
    const proxima = entregas.find(
      (item) => !['entregue', 'entregue_com_problema'].includes(item.status)
    );

    // Primeira vez: só memoriza e sai
    if (primeiraRenderizacao) {
      ultimaProximaId = proxima ? Number(proxima.id) : null;
      marcarPrimeiraRenderizacaoFeita();
      return;
    }

    const proximaIdAtual = proxima ? Number(proxima.id) : null;

    // Sem próxima (rota toda concluída): não mostra toast, só limpa
    if (!proxima) {
      ultimaProximaId = null;
      return;
    }

    // Mudou em relação ao que já tínhamos
    if (proximaIdAtual !== ultimaProximaId) {
      ultimaProximaId = proximaIdAtual;
      mostrarToastProximaParada(proxima);
    }
  }
  // ════════════════════════════════════════════════════════════════
  // PACOTE 3 — M4: DISTÂNCIA + ETA NO CABEÇALHO DO EMBARQUE
  // 2026-09-25
  //
  // Atualiza um chip no cabeçalho do embarque com:
  //   [📍 2,3 km · ~8 min]
  //
  // NÃO chama render() — atualiza só o DOM do chip, para não
  // disparar o toast do M3 nem re-renderizar a lista toda.
  //
  // Clicável: abre o Google Maps com a rota até a próxima parada.
  // ════════════════════════════════════════════════════════════════

  /** Formata o ETA de minutos para "~8 min" ou "~1h 15min". */
  function formatarEtaMotorista(distanciaKm) {
    if (distanciaKm === null || distanciaKm === undefined || isNaN(distanciaKm)) {
      return null;
    }
    const minutos = Math.max(1, Math.round((distanciaKm / VELOCIDADE_MEDIA_KMH) * 60));
    if (minutos < 60) return `~${minutos} min`;
    const h = Math.floor(minutos / 60);
    const m = minutos % 60;
    return m > 0 ? `~${h}h ${m}min` : `~${h}h`;
  }

  /**
   * Recalcula a distância até a próxima parada e atualiza o chip.
   * Chamado:
   *   - por atualizarPainelRota() (sincronizado com o resto do painel)
   *   - por setInterval de 30s (para não congelar o ETA)
   *   - quando o GPS do motorista atualiza (watchPosition)
   */
    function atualizarChipDistancia() {
    const chip = $('embarque-header-distancia');
    const texto = $('embarque-header-distancia-texto');
    if (!chip || !texto) return;

    // A próxima parada = 1º não concluído
    const proxima = entregas.find(
      (item) => !['entregue', 'entregue_com_problema'].includes(item.status)
    );

    // Sem próxima → esconde o chip
    if (!proxima) {
      chip.hidden = true;
      return;
    }

    // 🔥 M4-fix: normalizar coords (backend manda como string)
    const pLat = paraNumero(proxima.latitude);
    const pLng = paraNumero(proxima.longitude);
    if (pLat === null || pLng === null) {
      chip.hidden = true;
      return;
    }

    // Sem referência (nem motorista, nem caminhão) → esconde
    const ref = refPoint(proxima);
    if (!ref) {
      chip.hidden = true;
      return;
    }

    // Calcula distância
    const km = haversineKm(ref.latitude, ref.longitude, pLat, pLng);
    const eta = formatarEtaMotorista(km);

    // Atualiza texto
    texto.textContent = `${formatDistance(km)} · ${eta || '—'}`;

    // Cor por distância (longe = amarelo)
    chip.classList.toggle('is-longe', km > 15);

    // Guarda id da entrega para o clique
    chip.dataset.entregaId = proxima.id;

    chip.hidden = false;
  }

  /** Bind do clique no chip → abre Google Maps. */
  function bindChipDistancia() {
    const chip = $('embarque-header-distancia');
    if (!chip) return;
    chip.addEventListener('click', (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      const id = chip.dataset.entregaId;
      if (!id) return;
      const proxima = entregas.find((e) => Number(e.id) === Number(id));
      if (proxima) abrirNavegacao(proxima, 'gmaps');
    });
  }

  /** Inicia o timer de atualização automática (30s). */
  function iniciarTimerChipDistancia() {
    if (chipDistanciaTimer) clearInterval(chipDistanciaTimer);
    chipDistanciaTimer = setInterval(() => {
      // Só atualiza se a aba "Minha Rota" está visível
      const painelRota = $('driver-tab-rota');
      if (painelRota && painelRota.hidden) return;
      atualizarChipDistancia();
    }, CHIP_DISTANCIA_INTERVALO_MS);
  }

  // Helpers de formatação (adicionais ao escopo)
  function formatarPesoMotorista(peso) {
    const v = Number(peso);
    if (!v || isNaN(v)) return '0 kg';
    if (v >= 1000) return (v / 1000).toFixed(1).replace('.', ',') + ' t';
    return v.toFixed(0) + ' kg';
  }

  function formatarDataMotorista(dateStr) {
    if (!dateStr) return '—';
    try {
      const d = new Date(dateStr + 'T00:00:00');
      if (isNaN(d.getTime())) return dateStr;
      return d.toLocaleDateString('pt-BR');
    } catch { return dateStr; }
  }

  // ── Delegação de eventos: clique no cabeçalho do embarque
  document.addEventListener('click', function (ev) {
    const head = ev.target.closest('#embarque-header-head');
    if (head) toggleCabecalhoEmbarque();
  });

  document.addEventListener('keydown', function (ev) {
    if ((ev.key === 'Enter' || ev.key === ' ') && ev.target.id === 'embarque-header-head') {
      ev.preventDefault();
      toggleCabecalhoEmbarque();
    }
  });
  // ════════════════════════════════════════════════════════════════
  // PACOTE 1.5 — MODAL DE DETALHES DA ENTREGA
  // ════════════════════════════════════════════════════════════════

  /**
   * Abre o modal de detalhes de uma entrega (concluída ou não).
   * Mostra: dados gerais + checklist de itens + galeria de fotos.
   */
  async function abrirDetalhesEntrega(entregaId) {
    const modal = $('entrega-detalhes-modal');
    const conteudo = $('detalhes-conteudo');
    const titulo = $('detalhes-titulo');
    if (!modal || !conteudo) return;

    conteudo.innerHTML = `
      <div class="text-center py-8">
        <i class="fa-solid fa-spinner fa-spin text-3xl text-emerald-500"></i>
        <p class="mt-3 text-slate-400">Carregando detalhes...</p>
      </div>
    `;
    modal.hidden = false;
    document.body.classList.add('driver-modal-open');

    let entrega = entregas.find(e => Number(e.id) === Number(entregaId));

    if (!entrega || !Array.isArray(entrega.checklist) || !entrega.checklist.length) {
      try {
        const resp = await fetch(`${apiBase}/entregas/${entregaId}`, {
          headers: authHeaders(),
          credentials: 'include'
        });
        if (resp.ok) {
          const payload = await resp.json();
          const doBackend = payload.data || null;
          if (doBackend) {
            entrega = { ...(entrega || {}), ...doBackend };
            if (!entrega.checklist || !entrega.checklist.length) {
              entrega.checklist = doBackend.checklist || [];
            }
          }
        }
      } catch (e) {
        console.warn('Falha ao buscar detalhes da entrega:', e);
      }
    }

    if (!entrega) {
      conteudo.innerHTML = `<div class="detalhes-vazio"><i class="fa-solid fa-circle-question"></i>Entrega não encontrada.</div>`;
      return;
    }

    titulo.textContent = entrega.cliente_nome || `Entrega #${entrega.id}`;
    conteudo.innerHTML = renderDetalhesEntrega(entrega);
    bindEventosDetalhesEntrega();
  }

  /**
   * Gera o HTML do conteúdo do modal de detalhes.
   */
  function renderDetalhesEntrega(entrega) {
    const status = entrega.status || 'pendente';
    const statusLabel = {
      'pendente': 'Pendente',
      'em_entrega': 'Em rota',
      'entregue': 'Entregue',
      'entregue_com_problema': 'Com problema',
      'falha': 'Falha',
      'cancelada': 'Cancelada'
    }[status] || status;

    const pedidosArr = String(entrega.pedidos_ids || '')
      .split(',').map(s => s.trim()).filter(Boolean);

    const pedidosLabel = pedidosArr.length === 0
      ? '—'
      : pedidosArr.length === 1
        ? `#${pedidosArr[0]}`
        : `${pedidosArr[0]} +${pedidosArr.length - 1} (${pedidosArr.length} pedidos)`;

    const blocoTopo = `
      <div class="detalhes-bloco">
        <h3>${escapeHtml(entrega.cliente_nome || `Entrega #${entrega.id}`)}</h3>
        <p><i class="fa-solid fa-location-dot"></i> ${escapeHtml(formatAddress(entrega))}</p>
        <p><i class="fa-solid fa-receipt"></i> Pedido: <strong>${escapeHtml(pedidosLabel)}</strong></p>
      </div>
    `;

    const linhas = [];
    linhas.push(`<div class="detalhes-linha"><span>Status</span><strong>${escapeHtml(statusLabel)}</strong></div>`);
    if (entrega.valor_total) {
      linhas.push(`<div class="detalhes-linha"><span>Valor</span><strong>${formatarMoedaMotorista(entrega.valor_total)}</strong></div>`);
    }
    if (entrega.peso_total) {
      linhas.push(`<div class="detalhes-linha"><span>Peso</span><strong>${formatarPesoMotorista(entrega.peso_total)}</strong></div>`);
    }
    if (entrega.nome_recebedor) {
      linhas.push(`<div class="detalhes-linha"><span>Recebedor</span><strong>${escapeHtml(entrega.nome_recebedor)}</strong></div>`);
    }
    if (entrega.horario_checkin) {
      linhas.push(`<div class="detalhes-linha"><span>Check-in</span><strong>${formatarDataHoraMotorista(entrega.horario_checkin)}</strong></div>`);
    }
    if (entrega.horario_entrega) {
      linhas.push(`<div class="detalhes-linha"><span>Entrega</span><strong>${formatarDataHoraMotorista(entrega.horario_entrega)}</strong></div>`);
    }
    if (entrega.codigo_rastreamento) {
      linhas.push(`<div class="detalhes-linha"><span>Rastreio</span><strong>${escapeHtml(entrega.codigo_rastreamento)}</strong></div>`);
    }
    if (entrega.observacoes) {
      linhas.push(`<div class="detalhes-linha"><span>Observações</span><strong>${escapeHtml(entrega.observacoes)}</strong></div>`);
    }
    const blocoDados = `<div class="detalhes-bloco">${linhas.join('')}</div>`;

    const checklist = Array.isArray(entrega.checklist) ? entrega.checklist : [];
    const blocoItens = checklist.length ? `
      <div>
        <div class="detalhes-secao-titulo"><i class="fa-solid fa-clipboard-list"></i> Itens (${checklist.length})</div>
        <div class="detalhes-itens">
          ${checklist.map(item => renderDetalhesItem(item)).join('')}
        </div>
      </div>
    ` : '';

    const fotos = [];
    if (entrega.foto_romaneio_url) fotos.push({ tipo: 'Romaneio', url: entrega.foto_romaneio_url });
    if (entrega.foto_checkin_url)  fotos.push({ tipo: 'Check-in', url: entrega.foto_checkin_url });
    if (entrega.foto_item_url)     fotos.push({ tipo: 'Item',     url: entrega.foto_item_url });
    if (Array.isArray(entrega.fotos)) {
      entrega.fotos.forEach(f => {
        if (f.url && !fotos.some(x => x.url === f.url)) {
          fotos.push({ tipo: f.tipo || 'Foto', url: f.url, referencia: f.referencia });
        }
      });
    }
    checklist.forEach(it => {
      if (Array.isArray(it.levas)) {
        it.levas.forEach(l => {
          if (l.foto_url && !fotos.some(x => x.url === l.foto_url)) {
            fotos.push({ tipo: `Leva ${it.referencia || ''}`.trim(), url: l.foto_url, referencia: it.referencia });
          }
        });
      }
    });

    const blocoFotos = fotos.length ? `
      <div>
        <div class="detalhes-secao-titulo"><i class="fa-solid fa-images"></i> Fotos (${fotos.length})</div>
        <div class="detalhes-fotos-grid">
          ${fotos.map(f => `
            <div class="detalhes-foto" data-foto-url="${escapeHtml(f.url)}" data-foto-tipo="${escapeHtml(f.tipo)}">
              <img src="${escapeHtml(f.url)}" alt="${escapeHtml(f.tipo)}" loading="lazy">
              <span class="detalhes-foto-tag">${escapeHtml(f.tipo)}</span>
            </div>
          `).join('')}
        </div>
      </div>
    ` : '';

    return blocoTopo + blocoDados + blocoItens + blocoFotos;
  }

  /**
   * Renderiza um item do checklist dentro do modal.
   */
  function renderDetalhesItem(item) {
    const qtdPrev = Math.round(Number(item.quantidade_prevista || 0));
    const qtdEntr = Math.round(Number(item.quantidade_entregue || 0));
    const status = item.status || 'pendente';
    const levas = Array.isArray(item.levas) ? item.levas : [];

    const statusLabel = {
      'entregue': 'Entregue',
      'faltante': 'Faltante',
      'devolvido': 'Devolvido',
      'aberto': 'Aberto'
    }[status] || status;

    const levasHtml = levas.length ? `
      <div class="detalhes-levas">
        ${levas.map((l, i) => `
          <span class="detalhes-leva" data-foto-url="${escapeHtml(l.foto_url || '')}" data-foto-tipo="Leva ${i + 1} · ${item.referencia || ''}">
            <i class="fa-solid fa-camera"></i>
            Leva ${i + 1}: ${Math.round(Number(l.quantidade))}un
          </span>
        `).join('')}
      </div>
    ` : '';

    return `
      <div class="detalhes-item">
        <div class="detalhes-item-head">
          <span class="detalhes-item-ref">${escapeHtml(item.referencia || `Item ${item.item_id}`)}</span>
          <span class="detalhes-item-status s-${status}">${escapeHtml(statusLabel)}</span>
        </div>
        <div class="detalhes-item-desc">${escapeHtml(item.descricao || '')}</div>
        <div class="detalhes-item-qtd">
          Previsto: <b>${qtdPrev}</b> · Entregue: <b>${qtdEntr}</b>
          ${item.motivo ? ` · <i>${escapeHtml(item.motivo)}</i>` : ''}
        </div>
        ${levasHtml}
      </div>
    `;
  }

  /**
   * Formata data/hora no padrão DD/MM HH:MM.
   */
  function formatarDataHoraMotorista(str) {
    if (!str) return '—';
    try {
      const d = new Date(str);
      if (isNaN(d.getTime())) return str;
      return d.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
    } catch { return str; }
  }

  /**
   * Delegação de eventos: clique em qualquer foto do modal abre zoom.
   */
  function bindEventosDetalhesEntrega() {
    const modal = $('entrega-detalhes-modal');
    if (!modal) return;
    modal.querySelectorAll('[data-foto-url]').forEach(el => {
      const url = el.dataset.fotoUrl;
      if (!url) return;
      el.style.cursor = 'pointer';
      el.addEventListener('click', () => abrirZoomFoto(url, el.dataset.fotoTipo || ''));
    });
  }

  /**
   * Zoom simples de foto em modal.
   */
  function abrirZoomFoto(url, titulo) {
    if (!url) return;
    if (typeof Swal !== 'undefined') {
      Swal.fire({
        imageUrl: url,
        imageAlt: titulo || 'Foto',
        title: titulo || '',
        showConfirmButton: false,
        showCloseButton: true,
        width: 'auto',
        padding: '1rem'
      });
    } else {
      window.open(url, '_blank', 'noopener,noreferrer');
    }
  }

  // ── Fechar modal de detalhes
  $('detalhes-modal-close')?.addEventListener('click', () => {
    const m = $('entrega-detalhes-modal');
    if (m) m.hidden = true;
    document.body.classList.remove('driver-modal-open');
  });
  $('entrega-detalhes-modal')?.addEventListener('click', (ev) => {
    if (ev.target.id === 'entrega-detalhes-modal') {
      ev.currentTarget.hidden = true;
      document.body.classList.remove('driver-modal-open');
    }
  });

  // ── Expor funções para handlers globais (onclick inline no HTML)
  window.abrirDetalhesEntrega   = abrirDetalhesEntrega;
  window.toggleDetalhesParada   = toggleDetalhesParada;
  window.aplicarFiltroStatus    = aplicarFiltroStatus;
  window.mudarAbaMotorista      = mudarAbaMotorista;
  window.toggleMapaMotorista    = toggleMapaMotorista;
  window.abrirZoomFoto          = abrirZoomFoto;
  window.carregarPainelMotorista = carregarPainelMotorista; 
  window.abrirWhatsAppCliente   = abrirWhatsAppCliente;     
  window.ligarParaCliente       = ligarParaCliente;       
  window.atualizarChipDistancia = atualizarChipDistancia;   
}());