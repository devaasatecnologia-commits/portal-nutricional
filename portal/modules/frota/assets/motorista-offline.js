(function () {
  'use strict';
  const app = document.querySelector('.motorista-app');
  if (!app) return;

  const safeParse = (value, fallback = null) => { try { return JSON.parse(value); } catch { return fallback; } };
  const userData = safeParse(localStorage.getItem('userData') || 'null', null);
  const userPermissoes = userData?.permissoes || [];
  // Motorista comum (sem permissao de gestao de frota) fica travado no proprio
  // cadastro: nao pode ver nem trocar para outro motorista/usuario.
  const podeTrocarMotorista = Boolean(userData?.is_admin) || userPermissoes.includes('admin') || userPermissoes.includes('frota') || userPermissoes.includes('gestao-cargas');
  const motoristaVinculado = Number(userData?.motorista_id || 0);

  let motoristaId = Number(window.MOTORISTA_ID_INICIAL || app.dataset.motoristaId || localStorage.getItem('motoristaId') || 0);
  if (motoristaVinculado > 0 && !podeTrocarMotorista) motoristaId = motoristaVinculado;
  let cacheKey = `frota.motorista.${motoristaId}.entregas`;
  let queueKey = `frota.motorista.${motoristaId}.offline.queue`;
  let positionKey = `frota.motorista.${motoristaId}.posicao`;
  let truckKey = `frota.motorista.${motoristaId}.truck`;
  const apiBase = `${window.API_URL || '/v1'}/frota`;

  let entregas = [];
  let rotaVersion = null;
  let offlineQueue = [];
  let queueReady;
  let driverPosition = null;
  let truckPosition = null;
  let routeMap = null;
  let routeMarkers = [];
  let routeSourceId = 'driver-route-source';
  let watchId = null;

  const $ = (id) => document.getElementById(id);
  const online = () => navigator.onLine;
  const authHeaders = () => {
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
    return token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' };
  };

  function aplicarTema() {
    const saved = localStorage.getItem('frota.motorista.theme');
    const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
    document.documentElement.dataset.driverTheme = dark ? 'dark' : 'light';
    const button = $('driver-theme-toggle'); if (button) button.textContent = dark ? 'Tema claro' : 'Tema escuro';
  }
  function alternarTema() { localStorage.setItem('frota.motorista.theme', document.documentElement.dataset.driverTheme === 'dark' ? 'light' : 'dark'); aplicarTema(); }
  function abrirBancoOffline() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(`frota-motorista-${motoristaId}`, 1);
      request.onupgradeneeded = () => request.result.createObjectStore('fila', { keyPath: 'operation_id' });
      request.onsuccess = () => resolve(request.result); request.onerror = () => reject(request.error);
    });
  }
  async function carregarFila() {
    try {
      const db = await abrirBancoOffline();
      offlineQueue = await new Promise((resolve, reject) => {
        const request = db.transaction('fila').objectStore('fila').getAll();
        request.onsuccess = () => resolve(request.result || []); request.onerror = () => reject(request.error);
      });
      const legacy = safeParse(localStorage.getItem(queueKey) || '[]', []);
      if (legacy.length) {
        offlineQueue = [...offlineQueue, ...legacy.filter((item) => !offlineQueue.some((saved) => saved.operation_id === item.operation_id))];
        await salvarFila(offlineQueue); localStorage.removeItem(queueKey);
      }
    } catch {
      offlineQueue = safeParse(localStorage.getItem(queueKey) || '[]', []);
    }
    $('fila-pendente').textContent = offlineQueue.length;
    return offlineQueue;
  }
  function getQueue() { return offlineQueue; }
  async function salvarFila(queue) {
    offlineQueue = queue; $('fila-pendente').textContent = queue.length;
    try {
      const db = await abrirBancoOffline(); const tx = db.transaction('fila', 'readwrite'); const store = tx.objectStore('fila'); store.clear(); queue.forEach((item) => store.put(item));
    } catch { localStorage.setItem(queueKey, JSON.stringify(queue)); }
  }
  function saveQueue(queue) { void salvarFila(queue); atualizarConflitoRota(); void navigator.serviceWorker?.ready.then((r) => r.sync?.register('frota-offline-sync')).catch(() => {}); }
  queueReady = carregarFila();
  function operationId(id, action, body) { return `${motoristaId}:${id}:${action}:${body.data_hora}`; }
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
  // Posição rápida para ações de campo (checkin/checkout): usa a última posição
  // conhecida em cache imediatamente e só busca uma nova leitura de GPS com um
  // tempo limite curto, para não travar a ação em locais com sinal fraco.
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
  function setConnectionState() {
    const state = $('connection-state'); if (!state) return;
    state.classList.toggle('is-offline', !online()); state.querySelector('span:last-child').textContent = online() ? 'Online' : 'Offline';
    if ($('offline-notice')) $('offline-notice').hidden = online();
    if ($('route-map-offline')) $('route-map-offline').hidden = online();
    if ($('route-map-wrap')) $('route-map-wrap').hidden = !online();
    atualizarCardCaminhao();
  }
  function atualizarConflitoRota() { $('route-conflict').hidden = !getQueue().some((item) => item.type === 'reordenar' && item.conflict); }

  // ================================================================
  // CARD DO VEÍCULO/CAMINHÃO DO MOTORISTA
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
    window.open(url, '_blank');
  }

  async function carregarListaMotoristas() {
    const selectorCard = $('driver-selector-card');
    // Motorista comum: nunca exibe a lista de outros motoristas/usuários,
    // apenas identifica o próprio nome e esconde o seletor.
    if (motoristaVinculado > 0 && !podeTrocarMotorista) {
      if (selectorCard) selectorCard.hidden = true;
      try {
        const response = await fetch(`${apiBase}/motoristas/${motoristaId}`, { headers: authHeaders(), credentials: 'include' });
        if (response.ok) {
          const payload = await response.json(); const atual = payload.data;
          if (atual && $('driver-current-name')) $('driver-current-name').textContent = 'Motorista: ' + atual.nome + (atual.veiculo_placa ? ' | Veículo: ' + atual.veiculo_placa : '');
        }
      } catch (e) { console.warn('Erro ao identificar motorista:', e); }
      return;
    }
    const select = $('driver-select-input');
    if (!select) return;
    try {
      const response = await fetch(apiBase + '/motoristas?status=ativo&limite=100', { headers: authHeaders(), credentials: 'include' });
      if (!response.ok) return;
      const payload = await response.json();
      const list = payload.data || [];
      select.innerHTML = '<option value="">-- Selecione seu nome --</option>' + 
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
    const progressLabel = $('route-progress-label');
    const progressCount = $('route-progress-count');
    const progressBar = $('route-progress-bar');
    if (progressLabel) progressLabel.textContent = `${percentual}% concluído`;
    if (progressCount) progressCount.textContent = `${concluidas} de ${total}`;
    if (progressBar) progressBar.style.width = `${percentual}%`;

    const proxima = entregas.find((item) => !['entregue', 'entregue_com_problema'].includes(item.status));
    const card = $('next-stop-card');
    if (!card) return;
    if (!proxima) {
      card.hidden = true;
      return;
    }
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
    if (!entregas.length) { list.innerHTML = '<div class="empty-state">Nenhuma entrega encontrada para hoje.</div>'; atualizarPainelRota(); atualizarMapaRota(); return; }
    list.innerHTML = entregas.map((item, index) => {
      const complete = ['entregue', 'entregue_com_problema'].includes(item.status);
      const d = distanciaEntrega(item);
      return `<article class="delivery-card${complete ? ' is-complete' : ''}"><div class="delivery-order"><span>Parada ${index + 1}</span><span class="order-actions"><button data-order="up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>Subir</button><button data-order="down" data-index="${index}" ${index === entregas.length - 1 ? 'disabled' : ''}>Descer</button></span></div><h2>${escapeHtml(item.cliente_nome || `Entrega #${item.id}`)}</h2><p class="delivery-address">${escapeHtml(formatAddress(item))}</p><div class="delivery-meta"><span>${escapeHtml(item.status || 'pendente')}</span>${item.codigo_rastreamento ? `<span>${escapeHtml(item.codigo_rastreamento)}</span>` : ''}${d != null ? `<span class="delivery-distance">${escapeHtml(formatDistance(d))} de distância</span>` : ''}</div><div class="delivery-actions"><button class="checkin" data-action="checkin" data-id="${item.id}" ${complete ? 'disabled' : ''}>Cheguei</button><button class="checkout" data-action="checkout" data-id="${item.id}" ${complete ? 'disabled' : ''}>Entregue</button><button class="failure" data-action="falha" data-id="${item.id}" ${complete ? 'disabled' : ''}>Problema</button></div></article>`;
    }).join('');
    atualizarPainelRota();
    atualizarMapaRota();
  }
  function updateSummary() { $('total-entregas').textContent = entregas.length; $('entregas-concluidas').textContent = entregas.filter((item) => ['entregue', 'entregue_com_problema'].includes(item.status)).length; $('fila-pendente').textContent = getQueue().length; atualizarPainelRota(); }
  function persist() { localStorage.setItem(cacheKey, JSON.stringify(entregas)); render(); updateSummary(); }
  function salvarPosicaoMotorista(position) { if (!position || typeof position.latitude !== 'number' || typeof position.longitude !== 'number') return; driverPosition = position; localStorage.setItem(positionKey, JSON.stringify(position)); atualizarMapaRota(); render(); }
  function salvarPosicaoCaminhao(position) { if (!position || typeof position.latitude !== 'number' || typeof position.longitude !== 'number') return; truckPosition = position; localStorage.setItem(truckKey, JSON.stringify(position)); atualizarMapaRota(); render(); }

  function inicializarMapa() {
    if (routeMap || !window.maplibregl || !$('route-map')) return;
    routeMap = new maplibregl.Map({ container: 'route-map', style: 'https://tiles.openfreemap.org/styles/liberty', center: [-49.53561648427039, -28.979438954992666], zoom: 11, attributionControl: true });
    routeMap.addControl(new maplibregl.NavigationControl(), 'top-right');
    routeMap.on('load', () => {
      if (!routeMap.getSource(routeSourceId)) {
        routeMap.addSource(routeSourceId, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
        routeMap.addLayer({ id: 'driver-route-line', type: 'line', source: routeSourceId, paint: { 'line-color': '#16845e', 'line-width': 4, 'line-opacity': 0.9 } });
      }
      atualizarMapaRota();
    });
  }
  function limparMarcadoresMapa() { routeMarkers.forEach((m) => m.remove()); routeMarkers = []; }
  function atualizarLinhaRota(coords) {
    if (!routeMap || !routeMap.getSource(routeSourceId)) return;
    routeMap.getSource(routeSourceId).setData(coords.length > 1 ? { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'LineString', coordinates: coords }, properties: {} }] } : { type: 'FeatureCollection', features: [] });
  }
  function atualizarMapaRota() {
    if (!routeMap) return;
    if (!online()) { if ($('route-map-wrap')) $('route-map-wrap').hidden = true; if ($('route-map-offline')) $('route-map-offline').hidden = false; return; }
    if ($('route-map-wrap')) $('route-map-wrap').hidden = false; if ($('route-map-offline')) $('route-map-offline').hidden = true;
    const rota = entregas.filter((item) => typeof item.latitude === 'number' && typeof item.longitude === 'number');
    limparMarcadoresMapa(); const coords = []; const bounds = new maplibregl.LngLatBounds();
    rota.forEach((stop, index) => {
      coords.push([stop.longitude, stop.latitude]); bounds.extend([stop.longitude, stop.latitude]);
      const el = document.createElement('div'); el.className = `stop-marker${['entregue', 'entregue_com_problema'].includes(stop.status) ? ' is-complete' : ''}`; el.textContent = String(index + 1);
      routeMarkers.push(new maplibregl.Marker({ element: el }).setLngLat([stop.longitude, stop.latitude]).setPopup(new maplibregl.Popup({ offset: 24 }).setHTML(`<strong>${escapeHtml(stop.cliente_nome || `Entrega #${stop.id}`)}</strong><br>${escapeHtml(formatAddress(stop))}<br>Status: ${escapeHtml(stop.status || 'pendente')}`)).addTo(routeMap));
    });
    atualizarLinhaRota(coords);
    if (truckPosition && typeof truckPosition.longitude === 'number' && typeof truckPosition.latitude === 'number') {
      const el = document.createElement('div'); el.className = 'driver-truck-marker'; el.innerHTML = `<div class="driver-truck-balloon">${escapeHtml(truckPosition.placa || 'Caminhão')}</div><div class="driver-truck-icon"><i class="fa-solid fa-truck"></i></div>`;
      routeMarkers.push(new maplibregl.Marker({ element: el }).setLngLat([truckPosition.longitude, truckPosition.latitude]).setPopup(new maplibregl.Popup({ offset: 24 }).setHTML(`<strong>${escapeHtml(truckPosition.placa || 'Caminhão')}</strong><br>${escapeHtml(truckPosition.modelo || '')}<br>Fonte: ${truckPosition.fonte === 'cobli' ? 'Cobli (ao vivo)' : 'Cadastro'}`)).addTo(routeMap));
      bounds.extend([truckPosition.longitude, truckPosition.latitude]);
    }
    if (driverPosition && typeof driverPosition.longitude === 'number' && typeof driverPosition.latitude === 'number') {
      const el = document.createElement('div'); el.className = 'driver-me-marker';
      routeMarkers.push(new maplibregl.Marker({ element: el }).setLngLat([driverPosition.longitude, driverPosition.latitude]).setPopup(new maplibregl.Popup({ offset: 24 }).setHTML('<strong>Posição do motorista</strong><br>GPS do celular')).addTo(routeMap));
      bounds.extend([driverPosition.longitude, driverPosition.latitude]);
    }
    if (!bounds.isEmpty()) routeMap.fitBounds(bounds, { padding: 50, maxZoom: 14 });
  }
  async function atualizarMapaComCobli() {
    inicializarMapa(); if (!online()) { atualizarMapaRota(); atualizarCardCaminhao(); return; }
    const primeira = entregas.find((item) => typeof item.veiculo_id === 'number');
    if (primeira?.veiculo_id) {
      try {
        const response = await fetch(`${apiBase}/cobli/veiculo/${primeira.veiculo_id}/posicao`, { headers: authHeaders(), credentials: 'include' });
        if (response.ok) {
          const payload = await response.json(); const loc = payload.data?.localizacao; const veiculo = payload.data?.veiculo;
          if (loc && typeof loc.latitude === 'number' && typeof loc.longitude === 'number') salvarPosicaoCaminhao({ latitude: loc.latitude, longitude: loc.longitude, placa: veiculo?.plate || veiculo?.placa || primeira.placa, modelo: veiculo?.model || primeira.modelo, fonte: 'cobli' });
          else if (typeof primeira.veiculo_lat === 'number' && typeof primeira.veiculo_lng === 'number') salvarPosicaoCaminhao({ latitude: primeira.veiculo_lat, longitude: primeira.veiculo_lng, placa: primeira.placa, modelo: primeira.modelo, fonte: 'cadastro' });
        }
      } catch {
        if (typeof primeira.veiculo_lat === 'number' && typeof primeira.veiculo_lng === 'number') salvarPosicaoCaminhao({ latitude: primeira.veiculo_lat, longitude: primeira.veiculo_lng, placa: primeira.placa, modelo: primeira.modelo, fonte: 'cadastro' });
      }
    }
    atualizarMapaRota();
    atualizarCardCaminhao();
  }
  // Mantém a posição do caminhão sincronizada com o Cobli enquanto o app
  // estiver online; quando offline, o intervalo é ignorado e a última
  // posição em cache continua sendo exibida (ver atualizarCardCaminhao).
  let cobliPollTimer = null;
  function iniciarPollingCobli() {
    if (cobliPollTimer) clearInterval(cobliPollTimer);
    cobliPollTimer = setInterval(() => { if (online()) atualizarMapaComCobli(); }, 45000);
  }


  async function carregarEntregas() {
    if (!motoristaId) { $('motorista-status').textContent = 'Informe o motorista para carregar a rota'; $('delivery-list').innerHTML = '<div class="empty-state">A rota ainda não foi vinculada a um motorista.</div>'; return; }
    try {
      const response = await fetch(`${apiBase}/motoristas/${motoristaId}/entregas/hoje`, { headers: authHeaders(), credentials: 'include' });
      if (!response.ok) throw new Error('Falha ao carregar rota');
      const payload = await response.json();
      entregas = payload.data?.entregas || payload.entregas || [];
      rotaVersion = payload.data?.rota_ativa?.updated_at || entregas[0]?.embarque_updated_at || null;
      driverPosition = safeParse(localStorage.getItem(positionKey) || 'null', null);
      truckPosition = safeParse(localStorage.getItem(truckKey) || 'null', null);
      persist(); $('motorista-status').textContent = 'Rota atualizada agora';
      await atualizarMapaComCobli();
    } catch {
      entregas = safeParse(localStorage.getItem(cacheKey) || '[]', []);
      rotaVersion = entregas[0]?.embarque_updated_at || null;
      driverPosition = safeParse(localStorage.getItem(positionKey) || 'null', null);
      truckPosition = safeParse(localStorage.getItem(truckKey) || 'null', null);
      render(); updateSummary(); atualizarCardCaminhao();
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
      if (notification) { $('driver-alert').hidden = false; $('driver-alert').textContent = `${notification.titulo}: ${notification.mensagem}`; }
    } catch {}
  }
  function aplicarStatusLocal(id, action) { const item = entregas.find((delivery) => Number(delivery.id) === Number(id)); if (!item) return; item.status = action === 'reverter-pendente' ? 'em_entrega' : action === 'checkout' ? 'entregue' : action === 'falha' ? 'pendente' : 'em_entrega'; persist(); }
  function lerArquivo(file) { return new Promise((resolve) => { if (!file) return resolve(null); const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = () => resolve(null); reader.readAsDataURL(file); }); }
  function abrirCheckout(item) {
    $('checkout-modal').hidden = false; $('checkout-form').dataset.deliveryId = item.id; $('receiver-name').value = ''; $('romaneio-photo').value = '';
    $('checklist-fields').innerHTML = item.checklist?.length ? '<h3>Conferência dos itens</h3>' + item.checklist.map((entry, index) => `<div class="checklist-item"><div><strong>${escapeHtml(entry.referencia || entry.descricao || `Item ${entry.item_id}`)}</strong><small>Previsto: ${escapeHtml(entry.quantidade_prevista || 0)}</small></div><label>Qtd.<input type="number" min="0" step="any" data-check-index="${index}" value="${escapeHtml(entry.quantidade_prevista || 0)}"></label><input type="file" accept="image/*" capture="environment" data-photo-index="${index}"></div>`).join('') : '<p class="gps-status">Nenhum item de checklist cadastrado.</p>';
    limparAssinatura();
  }
  function limparAssinatura() { const canvas = $('signature-pad'); canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); }
  function capturarAssinatura() { return $('signature-pad').toDataURL('image/png'); }
  async function dadosCheckout() {
    const item = entregas.find((delivery) => Number(delivery.id) === Number($('checkout-form').dataset.deliveryId));
    const nomeRecebedor = $('receiver-name').value.trim(); const romaneio = await lerArquivo($('romaneio-photo').files[0]);
    if (!item || !nomeRecebedor || !romaneio || capturarAssinatura() === 'data:image/png;base64,') throw new Error('Preencha recebedor, foto e assinatura.');
    const checklist = [];
    for (const entry of item.checklist || []) {
      const index = checklist.length; const foto = await lerArquivo(document.querySelector(`[data-photo-index="${index}"]`)?.files[0]);
      if (!foto) throw new Error('Adicione uma foto para cada item do checklist.');
      const quantidade = Number(document.querySelector(`[data-check-index="${index}"]`)?.value || 0);
      checklist.push({ item_id: entry.item_id, referencia: entry.referencia, descricao: entry.descricao, quantidade_prevista: Number(entry.quantidade_prevista || 0), quantidade_entregue: quantidade, status: quantidade >= Number(entry.quantidade_prevista || 0) ? 'entregue' : 'faltante', motivo: quantidade >= Number(entry.quantidade_prevista || 0) ? null : 'Quantidade divergente', foto_item: foto });
    }
    return { motorista_id: motoristaId, desktop: false, nome_recebedor: nomeRecebedor, foto_romaneio: romaneio, assinatura_base64: capturarAssinatura(), checklist, tem_faltante: checklist.some((entry) => entry.status === 'faltante'), data_hora: new Date().toISOString() };
  }
  async function selecionarMotivoFalha() {
    const opcoes = { cliente_ausente: 'Cliente ausente', endereco_incorreto: 'Endereço incorreto', recusado: 'Recebimento recusado', nao_localizado: 'Local não localizado', outro: 'Outro motivo' };
    if (window.Swal) {
      const { value: motivo } = await Swal.fire({ icon: 'question', title: 'Qual foi o problema?', input: 'select', inputOptions: opcoes, inputPlaceholder: 'Selecione um motivo', showCancelButton: true, confirmButtonText: 'Confirmar', cancelButtonText: 'Cancelar' });
      return motivo || null;
    }
    const motivo = window.prompt('Motivo: cliente_ausente, endereco_incorreto, recusado, nao_localizado ou outro');
    return Object.keys(opcoes).includes(motivo) ? motivo : null;
  }
  async function obterDadosDaAcao(action) { if (action === 'checkout') return dadosCheckout(); if (action === 'falha') { const motivo = await selecionarMotivoFalha(); if (!motivo) return null; return { motorista_id: motoristaId, motivo, observacao: motivo, data_hora: new Date().toISOString() }; } return { motorista_id: motoristaId, desktop: false, data_hora: new Date().toISOString() }; }
  async function executarAcao(id, action) {
    const position = await getPositionFast(); const body = { ...(await obterDadosDaAcao(action)), ...position }; if (!body) return;
    const request = { id, endpoint: `${apiBase}/entregas/${id}/${action}`, action, body, operation_id: operationId(id, action, body) }; request.body.operation_id = request.operation_id;
    if (!online()) { const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]); aplicarStatusLocal(id, action); return; }
    try {
      const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(request.body) });
      if (response.status >= 400 && response.status < 500) {
        // Erro de validação do servidor (ex.: sem GPS, distância excedida, sem
        // autorização): NÃO enfileira nem marca como concluído — o motorista
        // precisa ser avisado e corrigir antes de tentar novamente.
        let mensagem = 'Ação não aceita pelo servidor.';
        try { const payload = await response.json(); mensagem = payload.error || mensagem; } catch {}
        if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Não foi possível confirmar', text: mensagem, confirmButtonText: 'OK' });
        else window.alert(mensagem);
        return;
      }
      if (!response.ok) throw new Error('Ação não aceita');
      aplicarStatusLocal(id, action);
    } catch {
      // Falha de rede (sem resposta do servidor): mantém o comportamento
      // offline-first, enfileirando para sincronizar quando a conexão voltar.
      const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]); aplicarStatusLocal(id, action);
    }
  }
  async function salvarOrdem() {
    const embarqueId = entregas[0]?.embarque_id; if (!embarqueId) return;
    const operationIdValue = `${motoristaId}:ordem:${embarqueId}:${entregas.map((item) => item.id).join('-')}`;
    const body = { ordem: entregas.map((item) => item.id), operation_id: operationIdValue, expected_updated_at: rotaVersion };
    const request = { endpoint: `${apiBase}/embarques/${embarqueId}/reordenar`, method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body };
    if (!online()) { const queue = getQueue().filter((item) => item.type !== 'reordenar'); saveQueue([...queue, { ...request, type: 'reordenar', operation_id: operationIdValue }]); return; }
    const response = await fetch(request.endpoint, request); if (response.status === 409) { $('motorista-status').textContent = 'Conflito: o gestor alterou a rota. Atualize antes de salvar novamente.'; throw new Error('A rota foi alterada pelo gestor'); } if (!response.ok) throw new Error('Não foi possível salvar a ordem');
  }
  async function mover(index, delta) { const target = index + delta; if (target < 0 || target >= entregas.length) return; [entregas[index], entregas[target]] = [entregas[target], entregas[index]]; persist(); try { await salvarOrdem(); } catch { $('motorista-status').textContent = 'Ordem alterada localmente; será salva quando houver conexão'; } }
  async function sincronizarFila() {
    if (!online()) return; await queueReady; const remaining = []; const falhas = [];
    for (const request of [...getQueue()]) {
      try {
        const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(request.body) });
        if (response.status === 409) { request.conflict = true; remaining.push(request); $('motorista-status').textContent = 'Há uma alteração de rota pendente de revisão.'; } else if (!response.ok) {
          if (response.status >= 400 && response.status < 500) {
            // Erro de validação/permissão do servidor: não adianta retentar, descarta e informa o motorista.
            let mensagem = 'Uma ação pendente não pôde ser confirmada e foi descartada.';
            try { const dados = await response.json(); if (dados?.message) mensagem = dados.message; } catch {}
            falhas.push({ id: request.id, action: request.action, mensagem });
          } else remaining.push(request);
        }
      } catch { remaining.push(request); }
    }
    await salvarFila(remaining); atualizarConflitoRota();
    if (falhas.length) {
      await carregarEntregas();
      const resumo = falhas.map((falha) => `Parada (${falha.action}): ${falha.mensagem}`).join('\n');
      if (window.Swal) await Swal.fire({ icon: 'warning', title: 'Ações pendentes não confirmadas', html: resumo.replace(/\n/g, '<br>'), confirmButtonText: 'OK' });
      else window.alert(resumo);
    }
  }
  async function abrirPendenciasSeExistirem() {
    const pendencias = safeParse(sessionStorage.getItem('cadfrota_pendencias_erp') || 'null', null); if (!pendencias) return;
    sessionStorage.removeItem('cadfrota_pendencias_erp');
    if (!(pendencias.motoristas?.length || pendencias.veiculos?.length)) return;
    await Swal.fire({ icon: 'info', title: 'Cadastro de Frota aberto', html: 'Os dados pendentes do ERP foram enviados para o módulo de Cadastro de Frota e estão pré-preenchidos.', confirmButtonText: 'OK' });
    window.open('/portal/modules/frota/cadastro-frota.php', '_blank');
  }

  document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-action], [data-order]'); if (!button) return;
    if (button.dataset.action === 'checkout') abrirCheckout(entregas.find((item) => Number(item.id) === Number(button.dataset.id))); else if (button.dataset.action) executarAcao(button.dataset.id, button.dataset.action);
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
      // Feedback imediato: fecha o modal e aplica o status local assim que os
      // dados (fotos/assinatura, já lidos localmente) estiverem prontos, sem
      // esperar a rede ou o GPS de alta precisão.
      $('checkout-modal').hidden = true;
      aplicarStatusLocal(id, 'checkout');
      const position = await getPositionFast();
      const body = { ...dados, ...position };
      request = { id, endpoint: `${apiBase}/entregas/${id}/checkout`, action: 'checkout', body, operation_id: operationId(id, 'checkout', body) }; request.body.operation_id = request.operation_id;
      if (!online()) { saveQueue([...getQueue(), request]); return; }
      const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(body) });
      if (response.status >= 400 && response.status < 500) {
        // Erro de validação do servidor (ex.: distância, autorização): não
        // enfileira para retry (voltaria a falhar sempre) e desfaz o status
        // aplicado localmente, avisando o motorista com o motivo real.
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
        // Falha de rede: modal ainda não fechou, avisa o motorista.
        const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]);
        window.alert('Não foi possível concluir o checkout online. Ele foi salvo e será sincronizado automaticamente.');
      } else {
        window.alert(error.message);
      }
    }
  });
  const signatureCanvas = $('signature-pad'); let drawing = false;
  signatureCanvas?.addEventListener('pointerdown', (event) => { drawing = true; signatureCanvas.setPointerCapture(event.pointerId); const rect = signatureCanvas.getBoundingClientRect(); const ctx = signatureCanvas.getContext('2d'); ctx.beginPath(); ctx.moveTo((event.clientX - rect.left) * signatureCanvas.width / rect.width, (event.clientY - rect.top) * signatureCanvas.height / rect.height); });
  signatureCanvas?.addEventListener('pointermove', (event) => { if (!drawing) return; const rect = signatureCanvas.getBoundingClientRect(); const ctx = signatureCanvas.getContext('2d'); ctx.lineWidth = 3; ctx.lineCap = 'round'; ctx.lineTo((event.clientX - rect.left) * signatureCanvas.width / rect.width, (event.clientY - rect.top) * signatureCanvas.height / rect.height); ctx.stroke(); });
  signatureCanvas?.addEventListener('pointerup', () => { drawing = false; });
  $('btn-refresh-route')?.addEventListener('click', carregarEntregas);
  $('route-conflict-refresh')?.addEventListener('click', async () => { await carregarEntregas(); $('route-conflict').hidden = true; });
  $('route-conflict-discard')?.addEventListener('click', async () => { await salvarFila(getQueue().filter((item) => !(item.type === 'reordenar' && item.conflict))); $('route-conflict').hidden = true; $('motorista-status').textContent = 'Ordenação local descartada; rota do gestor preservada.'; await carregarEntregas(); });
  
  $('btn-confirm-driver')?.addEventListener('click', async () => {
    if (motoristaVinculado > 0 && !podeTrocarMotorista) return; // motorista comum não pode trocar
    const val = Number($('driver-select-input')?.value || 0);
    if (!val) {
      if (typeof Swal !== 'undefined') Swal.fire({ icon: 'warning', title: 'Selecione um motorista', confirmButtonText: 'OK' });
      else alert('Selecione um motorista');
      return;
    }
    motoristaId = val;
    localStorage.setItem('motoristaId', val);
    app.dataset.motoristaId = val;
    cacheKey = 'frota.motorista.' + motoristaId + '.entregas';
    queueKey = 'frota.motorista.' + motoristaId + '.offline.queue';
    positionKey = 'frota.motorista.' + motoristaId + '.posicao';
    truckKey = 'frota.motorista.' + motoristaId + '.truck';
    await carregarEntregas();
    await carregarListaMotoristas();
  });

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

  $('driver-theme-toggle')?.addEventListener('click', alternarTema);
  window.addEventListener('online', () => { setConnectionState(); sincronizarFila(); carregarEntregas(); carregarNotificacoes(); iniciarPollingCobli(); });
  window.addEventListener('offline', () => { setConnectionState(); atualizarMapaRota(); atualizarCardCaminhao(); });
  navigator.serviceWorker?.addEventListener('message', (event) => { if (event.data?.type === 'frota-offline-sync') sincronizarFila(); });
  if ('serviceWorker' in navigator) { const appBase = window.location.pathname.split('/portal/')[0]; navigator.serviceWorker.register(`${appBase}/portal/modules/frota/service-worker.js`).catch(() => {}); }
  if (navigator.geolocation && online()) watchId = navigator.geolocation.watchPosition((position) => salvarPosicaoMotorista({ latitude: position.coords.latitude, longitude: position.coords.longitude, precisao: position.coords.accuracy }), () => {}, { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 });
  aplicarTema(); setConnectionState(); carregarListaMotoristas(); inicializarMapa(); carregarEntregas(); carregarNotificacoes(); sincronizarFila(); abrirPendenciasSeExistirem(); iniciarPollingCobli();
}());

// MARKER_UNIQUE_12345
