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

  const $ = (id) => document.getElementById(id);
  const online = () => navigator.onLine;
  const authHeaders = () => {
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    return token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' };
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
    $('fila-pendente').textContent = offlineQueue.length;
    return offlineQueue;
  }
  function getQueue() { return offlineQueue; }
  async function salvarFila(queue) {
    offlineQueue = queue;
    $('fila-pendente').textContent = queue.length;
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

  function formatAddress(item) { return [item.endereco, item.numero, item.bairro, item.cidade, item.uf].filter(Boolean).join(', ') || 'Endereço não informado'; }
  function escapeHtml(value) { return String(value).replace(/[&<>'"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[c])); }
  function haversineKm(aLat, aLng, bLat, bLng) {
    const r = (v) => v * Math.PI / 180, R = 6371, dLat = r(bLat - aLat), dLng = r(bLng - aLng);
    const s = Math.sin(dLat / 2) ** 2 + Math.cos(r(aLat)) * Math.cos(r(bLat)) * Math.sin(dLng / 2) ** 2;
    return 2 * R * Math.atan2(Math.sqrt(s), Math.sqrt(1 - s));
  }
  function formatDistance(km) { if (km == null || Number.isNaN(km)) return ''; return km < 1 ? `${Math.round(km * 1000)} m` : `${km.toFixed(1)} km`; }
  function refPoint(item) { return driverPosition || truckPosition || (typeof item.veiculo_lat === 'number' && typeof item.veiculo_lng === 'number' ? { latitude: item.veiculo_lat, longitude: item.veiculo_lng } : null); }
  function distanciaEntrega(item) { return typeof item.latitude === 'number' && typeof item.longitude === 'number' && refPoint(item) ? haversineKm(refPoint(item).latitude, refPoint(item).longitude, item.latitude, item.longitude) : null; }

  function atualizarPainelRota() {
    const total = entregas.length;
    const concluidas = entregas.filter((item) => ['entregue', 'entregue_com_problema'].includes(item.status)).length;
    const percentual = total ? Math.round((concluidas / total) * 100) : 0;
    if ($('route-progress-label')) $('route-progress-label').textContent = `${percentual}% concluído`;
    if ($('route-progress-count')) $('route-progress-count').textContent = `${concluidas} de ${total}`;
    if ($('route-progress-bar')) $('route-progress-bar').style.width = `${percentual}%`;

    const proxima = entregas.find((item) => !['entregue', 'entregue_com_problema'].includes(item.status));
    const card = $('next-stop-card');
    if (!card) return;
    if (!proxima) { card.hidden = true; return; }
    card.hidden = false;
    $('next-stop-name').textContent = proxima.cliente_nome || `Entrega #${proxima.id}`;
    $('next-stop-address').textContent = formatAddress(proxima);
    const distancia = distanciaEntrega(proxima);
    $('next-stop-distance').textContent = distancia !== null ? `${formatDistance(distancia)} de distância` : 'Distância indisponível';
    $('next-stop-action').dataset.id = proxima.id;
    $('next-stop-action').disabled = proxima.status === 'em_entrega';
    $('next-stop-action').textContent = proxima.status === 'em_entrega' ? 'Em atendimento' : 'Cheguei';
  }

  function render() {
    const list = $('delivery-list');
    atualizarCardCaminhao();
    if (!entregas.length) {
      list.innerHTML = '<div class="empty-state">Nenhuma entrega encontrada para hoje.</div>';
      atualizarPainelRota();
      atualizarMapaRota();
      return;
    }
    list.innerHTML = entregas.map((item, index) => {
      const complete = ['entregue', 'entregue_com_problema'].includes(item.status);
      const d = distanciaEntrega(item);
      return `<article class="delivery-card${complete ? ' is-complete' : ''}"><div class="delivery-order"><span>Parada ${index + 1}</span><span class="order-actions"><button data-order="up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>Subir</button><button data-order="down" data-index="${index}" ${index === entregas.length - 1 ? 'disabled' : ''}>Descer</button></span></div><h2>${escapeHtml(item.cliente_nome || `Entrega #${item.id}`)}</h2><p class="delivery-address">${escapeHtml(formatAddress(item))}</p><div class="delivery-meta"><span>${escapeHtml(item.status || 'pendente')}</span>${item.codigo_rastreamento ? `<span>${escapeHtml(item.codigo_rastreamento)}</span>` : ''}${d != null ? `<span class="delivery-distance">${escapeHtml(formatDistance(d))} de distância</span>` : ''}</div><div class="delivery-actions"><button class="checkin" data-action="checkin" data-id="${item.id}" ${complete ? 'disabled' : ''}>Cheguei</button><button class="checkout" data-action="checkout" data-id="${item.id}" ${complete ? 'disabled' : ''}>Entregue</button><button class="failure" data-action="falha" data-id="${item.id}" ${complete ? 'disabled' : ''}>Problema</button></div></article>`;
    }).join('');
    atualizarPainelRota();
    atualizarMapaRota();
  }

  function updateSummary() {
    $('total-entregas').textContent = entregas.length;
    $('entregas-concluidas').textContent = entregas.filter((item) => ['entregue', 'entregue_com_problema'].includes(item.status)).length;
    $('fila-pendente').textContent = getQueue().length;
    atualizarDrawerFila();
    atualizarPainelRota();
  }
  function persist() { localStorage.setItem(cacheKey, JSON.stringify(entregas)); render(); updateSummary(); }
  function salvarPosicaoMotorista(position) { if (!position || typeof position.latitude !== 'number' || typeof position.longitude !== 'number') return; driverPosition = position; localStorage.setItem(positionKey, JSON.stringify(position)); atualizarMapaRota(); render(); }
  function salvarPosicaoCaminhao(position) { if (!position || typeof position.latitude !== 'number' || typeof position.longitude !== 'number') return; truckPosition = position; localStorage.setItem(truckKey, JSON.stringify(position)); atualizarMapaRota(); render(); }

  function inicializarMapa() {
    if (routeMap || !window.maplibregl || !$('route-map')) return;
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
    if ($('route-map-offline')) $('route-map-offline').hidden = true;

    const rota = entregas.filter((item) => typeof item.latitude === 'number' && typeof item.longitude === 'number');
    const idsAtuais = new Set();
    const bounds = new maplibregl.LngLatBounds();
    const coords = [];

    // ── 1. Entregas: atualiza marcadores existentes, cria os novos, remove os ausentes
    rota.forEach((stop, index) => {
      const key = `stop-${stop.id}`;
      idsAtuais.add(key);
      coords.push([stop.longitude, stop.latitude]);
      bounds.extend([stop.longitude, stop.latitude]);

      const isComplete = ['entregue', 'entregue_com_problema'].includes(stop.status);
      const existing = markerIndex.get(key);

      if (existing) {
        existing.marker.setLngLat([stop.longitude, stop.latitude]);
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
          .setLngLat([stop.longitude, stop.latitude])
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
      persist();
      $('motorista-status').textContent = 'Rota atualizada agora';
      await atualizarMapaComCobli();
    } catch {
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

  function abrirCheckout(item) {
    $('checkout-modal').hidden = false;
    $('checkout-form').dataset.deliveryId = item.id;
    $('receiver-name').value = '';
    $('romaneio-photo').value = '';
    $('checklist-fields').innerHTML = item.checklist?.length
      ? '<h3>Conferência dos itens</h3>' + item.checklist.map((entry, index) => `<div class="checklist-item"><div><strong>${escapeHtml(entry.referencia || entry.descricao || `Item ${entry.item_id}`)}</strong><small>Previsto: ${escapeHtml(entry.quantidade_prevista || 0)}</small></div><label>Qtd.<input type="number" min="0" step="any" data-check-index="${index}" value="${escapeHtml(entry.quantidade_prevista || 0)}"></label><input type="file" accept="image/*" capture="environment" data-photo-index="${index}"></div>`).join('')
      : '<p class="gps-status">Nenhum item de checklist cadastrado.</p>';
    limparAssinatura();
  }

  function limparAssinatura() { const canvas = $('signature-pad'); canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); }
  function capturarAssinatura() { return $('signature-pad').toDataURL('image/png'); }

  async function dadosCheckout() {
    const item = entregas.find((delivery) => Number(delivery.id) === Number($('checkout-form').dataset.deliveryId));
    const nomeRecebedor = $('receiver-name').value.trim();
    const romaneio = await lerArquivo($('romaneio-photo').files[0]);
    if (!item || !nomeRecebedor || !romaneio || capturarAssinatura() === 'data:image/png;base64,') {
      throw new Error('Preencha recebedor, foto e assinatura.');
    }
    const checklist = [];
    for (const entry of item.checklist || []) {
      const index = checklist.length;
      const foto = await lerArquivo(document.querySelector(`[data-photo-index="${index}"]`)?.files[0]);
      if (!foto) throw new Error('Adicione uma foto para cada item do checklist.');
      const quantidade = Number(document.querySelector(`[data-check-index="${index}"]`)?.value || 0);
      checklist.push({
        item_id: entry.item_id,
        referencia: entry.referencia,
        descricao: entry.descricao,
        quantidade_prevista: Number(entry.quantidade_prevista || 0),
        quantidade_entregue: quantidade,
        status: quantidade >= Number(entry.quantidade_prevista || 0) ? 'entregue' : 'faltante',
        motivo: quantidade >= Number(entry.quantidade_prevista || 0) ? null : 'Quantidade divergente',
        foto_item: foto
      });
    }
    return {
      motorista_id: motoristaId,
      desktop: false,
      nome_recebedor: nomeRecebedor,
      foto_romaneio: romaneio,
      assinatura_base64: capturarAssinatura(),
      checklist,
      tem_faltante: checklist.some((entry) => entry.status === 'faltante'),
      data_hora: new Date().toISOString()
    };
  }

  async function selecionarMotivoFalha() {
    const opcoes = { cliente_ausente: 'Cliente ausente', endereco_incorreto: 'Endereço incorreto', recusado: 'Recebimento recusado', nao_localizado: 'Local não localizado', outro: 'Outro motivo' };
    if (window.Swal) {
      const { value: motivo } = await Swal.fire({
        icon: 'question',
        title: 'Qual foi o problema?',
        input: 'select',
        inputOptions: opcoes,
        inputPlaceholder: 'Selecione um motivo',
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar'
      });
      return motivo || null;
    }
    const motivo = window.prompt('Motivo: cliente_ausente, endereco_incorreto, recusado, nao_localizado ou outro');
    return Object.keys(opcoes).includes(motivo) ? motivo : null;
  }

  async function obterDadosDaAcao(action) {
    if (action === 'checkout') return dadosCheckout();
    if (action === 'falha') {
      const motivo = await selecionarMotivoFalha();
      if (!motivo) return null;
      return { motorista_id: motoristaId, motivo, observacao: motivo, data_hora: new Date().toISOString() };
    }
    return { motorista_id: motoristaId, desktop: false, data_hora: new Date().toISOString() };
  }

  async function executarAcao(id, action) {
    const position = await getPositionFast();
    const body = { ...(await obterDadosDaAcao(action)), ...position };
    if (!body) return;
    const request = { id, endpoint: `${apiBase}/entregas/${id}/${action}`, action, body, operation_id: operationId(id, action, body) };
    request.body.operation_id = request.operation_id;
    if (!online()) {
      const queue = getQueue();
      if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]);
      aplicarStatusLocal(id, action);
      return;
    }
    try {
      const response = await fetch(request.endpoint, {
        method: 'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(request.body)
      });
      if (response.status >= 400 && response.status < 500) {
        let mensagem = 'Ação não aceita pelo servidor.';
        try { const payload = await response.json(); mensagem = payload.error || mensagem; } catch {}
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Não foi possível confirmar', text: mensagem, confirmButtonText: 'OK' });
        else window.alert(mensagem);
        return;
      }
      if (!response.ok) throw new Error('Ação não aceita');
      aplicarStatusLocal(id, action);
    } catch {
      const queue = getQueue();
      if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]);
      aplicarStatusLocal(id, action);
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
        } else if (!response.ok) {
          if (response.status >= 400 && response.status < 500) {
            let mensagem = 'Uma ação pendente não pôde ser confirmada e foi descartada.';
            try { const dados = await response.json(); if (dados?.message) mensagem = dados.message; } catch {}
            falhas.push({ id: request.id, action: request.action, mensagem });
          } else remaining.push(request);
        }
      } catch { remaining.push(request); }
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
    const button = event.target.closest('[data-action], [data-order]');
    if (!button) return;
    if (isAdminApp) return;
    if (button.dataset.action === 'checkout') {
      abrirCheckout(entregas.find((item) => Number(item.id) === Number(button.dataset.id)));
    } else if (button.dataset.action) {
      executarAcao(button.dataset.id, button.dataset.action);
    }
    if (button.dataset.order) mover(Number(button.dataset.index), button.dataset.order === 'up' ? -1 : 1);
  });

  $('next-stop-action')?.addEventListener('click', () => {
    const item = entregas.find((delivery) => Number(delivery.id) === Number($('next-stop-action').dataset.id));
    if (!item) return;
    if (item.status === 'em_entrega') abrirCheckout(item);
    else executarAcao(item.id, 'checkin');
  });

  $('checkout-cancel')?.addEventListener('click', () => { $('checkout-modal').hidden = true; });
  $('signature-clear')?.addEventListener('click', limparAssinatura);

  $('checkout-form')?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const id = $('checkout-form').dataset.deliveryId;
    let request = null;
    try {
      const dados = await obterDadosDaAcao('checkout');
      $('checkout-modal').hidden = true;
      aplicarStatusLocal(id, 'checkout');
      const position = await getPositionFast();
      const body = { ...dados, ...position };
      request = { id, endpoint: `${apiBase}/entregas/${id}/checkout`, action: 'checkout', body, operation_id: operationId(id, 'checkout', body) };
      request.body.operation_id = request.operation_id;
      if (!online()) { saveQueue([...getQueue(), request]); return; }
      const response = await fetch(request.endpoint, {
        method: 'POST',
        headers: { ...authHeaders(), 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify(body)
      });
      if (response.status >= 400 && response.status < 500) {
        let mensagem = 'Checkout não aceito pelo servidor.';
        try { const payload = await response.json(); mensagem = payload.error || mensagem; } catch {}
        aplicarStatusLocal(id, 'reverter-pendente');
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Não foi possível confirmar', text: mensagem, confirmButtonText: 'OK' });
        else window.alert(mensagem);
        return;
      }
      if (!response.ok) throw new Error('Checkout não aceito');
    } catch (error) {
      if (request) {
        const queue = getQueue();
        if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]);
        window.alert('Não foi possível concluir o checkout online. Ele foi salvo e será sincronizado automaticamente.');
      } else {
        window.alert(error.message);
      }
    }
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

  $('romaneio-photo')?.addEventListener('change', (event) => {
    const file = event.target.files[0];
    const previewContainer = $('romaneio-preview');
    const previewImg = $('romaneio-preview-img');
    if (file && previewContainer && previewImg) {
      const reader = new FileReader();
      reader.onload = (e) => {
        previewImg.src = e.target.result;
        previewContainer.hidden = false;
      };
      reader.readAsDataURL(file);
    }
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
  carregarPainelAdmin();
  carregarPerfil();
  carregarListaMotoristas();
  inicializarMapa();
  carregarEntregas();
  carregarNotificacoes();
  sincronizarFila();
  abrirPendenciasSeExistirem();
  iniciarPollingCobli();
}());