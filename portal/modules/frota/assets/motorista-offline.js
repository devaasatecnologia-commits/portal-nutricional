(function () {
    'use strict';
    const app = document.querySelector('.motorista-app');
    if (!app) return;
    const motoristaId = Number(window.MOTORISTA_ID_INICIAL || app.dataset.motoristaId || localStorage.getItem('motoristaId') || 0);
    const cacheKey = `frota.motorista.${motoristaId}.entregas`;
    const queueKey = `frota.motorista.${motoristaId}.offline.queue`;
    const apiBase = `${window.API_URL || '/v1'}/frota`;
    let entregas = [];
    let rotaVersion = null;
    let offlineQueue = [];
    let queueReady;
    const $ = (id) => document.getElementById(id);
    function aplicarTema() { const saved = localStorage.getItem('frota.motorista.theme'); const dark = saved ? saved === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches; document.documentElement.dataset.driverTheme = dark ? 'dark' : 'light'; const button = $('driver-theme-toggle'); if (button) button.textContent = dark ? 'Tema claro' : 'Tema escuro'; }
    function alternarTema() { const dark = document.documentElement.dataset.driverTheme !== 'dark'; localStorage.setItem('frota.motorista.theme', dark ? 'dark' : 'light'); aplicarTema(); }
    function abrirBancoOffline() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(`frota-motorista-${motoristaId}`, 1);
            request.onupgradeneeded = () => request.result.createObjectStore('fila', { keyPath: 'operation_id' });
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
            const legacy = JSON.parse(localStorage.getItem(queueKey) || '[]');
            if (legacy.length) {
                offlineQueue = [...offlineQueue, ...legacy.filter((item) => !offlineQueue.some((saved) => saved.operation_id === item.operation_id))];
                await salvarFila(offlineQueue);
                localStorage.removeItem(queueKey);
            }
        } catch (error) {
            try { offlineQueue = JSON.parse(localStorage.getItem(queueKey) || '[]'); } catch (fallbackError) { offlineQueue = []; }
        }
        $('fila-pendente').textContent = offlineQueue.length;
        return offlineQueue;
    }
    function getQueue() { return offlineQueue; }
    async function salvarFila(queue) {
        offlineQueue = queue;
        $('fila-pendente').textContent = queue.length;
        try {
            const db = await abrirBancoOffline();
            const transaction = db.transaction('fila', 'readwrite');
            const store = transaction.objectStore('fila');
            store.clear();
            queue.forEach((item) => store.put(item));
        } catch (error) {
            localStorage.setItem(queueKey, JSON.stringify(queue));
        }
    }
    function saveQueue(queue) { void salvarFila(queue); atualizarConflitoRota(); void navigator.serviceWorker?.ready.then((registration) => registration.sync?.register('frota-offline-sync')); }
    queueReady = carregarFila();
    function operationId(id, action, body) { return `${motoristaId}:${id}:${action}:${body.data_hora}`; }
    function getPosition() { return new Promise((resolve) => { if (!navigator.geolocation) return resolve({}); $('gps-status').textContent = 'Obtendo GPS...'; navigator.geolocation.getCurrentPosition((position) => { $('gps-status').textContent = 'GPS confirmado'; resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude, precisao: position.coords.accuracy }); }, () => { $('gps-status').textContent = 'GPS indisponível'; resolve({}); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }); }); }
    function authHeaders() { const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken'); return token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' }; }
    function setConnectionState() { const online = navigator.onLine; const state = $('connection-state'); state.classList.toggle('is-offline', !online); state.querySelector('span:last-child').textContent = online ? 'Online' : 'Offline'; $('offline-notice').hidden = online; }
    function atualizarConflitoRota() { $('route-conflict').hidden = !getQueue().some((item) => item.type === 'reordenar' && item.conflict); }
    function formatAddress(item) { return [item.endereco, item.numero, item.bairro, item.cidade, item.uf].filter(Boolean).join(', ') || 'Endereço não informado'; }
    function escapeHtml(value) { return String(value).replace(/[&<>'\"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '\"': '&quot;' }[character])); }
    function render() { const list = $('delivery-list'); if (!entregas.length) { list.innerHTML = '<div class="empty-state">Nenhuma entrega encontrada para hoje.</div>'; return; } list.innerHTML = entregas.map((item, index) => { const complete = ['entregue', 'entregue_com_problema'].includes(item.status); return `<article class="delivery-card${complete ? ' is-complete' : ''}"><div class="delivery-order"><span>Parada ${index + 1}</span><span class="order-actions"><button data-order="up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>Subir</button><button data-order="down" data-index="${index}" ${index === entregas.length - 1 ? 'disabled' : ''}>Descer</button></span></div><h2>${escapeHtml(item.cliente_nome || `Entrega #${item.id}`)}</h2><p class="delivery-address">${escapeHtml(formatAddress(item))}</p><div class="delivery-meta"><span>${escapeHtml(item.status || 'pendente')}</span>${item.codigo_rastreamento ? `<span>${escapeHtml(item.codigo_rastreamento)}</span>` : ''}</div><div class="delivery-actions"><button class="checkin" data-action="checkin" data-id="${item.id}" ${complete ? 'disabled' : ''}>Cheguei</button><button class="checkout" data-action="checkout" data-id="${item.id}" ${complete ? 'disabled' : ''}>Entregue</button><button class="failure" data-action="falha" data-id="${item.id}" ${complete ? 'disabled' : ''}>Problema</button></div></article>`; }).join(''); }
    function updateSummary() { $('total-entregas').textContent = entregas.length; $('entregas-concluidas').textContent = entregas.filter((item) => ['entregue', 'entregue_com_problema'].includes(item.status)).length; $('fila-pendente').textContent = getQueue().length; }
    function persist() { localStorage.setItem(cacheKey, JSON.stringify(entregas)); render(); updateSummary(); }
    async function carregarEntregas() { if (!motoristaId) { $('motorista-status').textContent = 'Informe o motorista para carregar a rota'; $('delivery-list').innerHTML = '<div class="empty-state">A rota ainda não foi vinculada a um motorista.</div>'; return; } try { const response = await fetch(`${apiBase}/motoristas/${motoristaId}/entregas/hoje`, { headers: authHeaders(), credentials: 'include' }); if (!response.ok) throw new Error('Falha ao carregar rota'); const payload = await response.json(); entregas = payload.data?.entregas || payload.entregas || []; rotaVersion = payload.data?.rota_ativa?.updated_at || entregas[0]?.embarque_updated_at || null; persist(); $('motorista-status').textContent = 'Rota atualizada agora'; } catch (error) { entregas = JSON.parse(localStorage.getItem(cacheKey) || '[]'); rotaVersion = entregas[0]?.embarque_updated_at || null; render(); updateSummary(); $('motorista-status').textContent = entregas.length ? 'Usando a última rota salva neste aparelho' : 'Não foi possível carregar a rota'; } }
    async function carregarNotificacoes() { if (!motoristaId || !navigator.onLine) return; try { const response = await fetch(`${apiBase}/motoristas/${motoristaId}/notificacoes?limite=5`, { headers: authHeaders(), credentials: 'include' }); if (!response.ok) return; const payload = await response.json(); const notification = (payload.data || []).find((item) => !item.lida); if (notification) { $('driver-alert').hidden = false; $('driver-alert').textContent = `${notification.titulo}: ${notification.mensagem}`; } } catch (error) { /* O app continua operacional offline. */ } }
    function aplicarStatusLocal(id, action) { const item = entregas.find((delivery) => Number(delivery.id) === Number(id)); if (!item) return; item.status = action === 'checkout' ? 'entregue' : action === 'falha' ? 'pendente' : 'em_entrega'; persist(); }
    function lerArquivo(file) { return new Promise((resolve) => { if (!file) return resolve(null); const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = () => resolve(null); reader.readAsDataURL(file); }); }
    function abrirCheckout(item) {
        $('checkout-modal').hidden = false;
        $('checkout-form').dataset.deliveryId = item.id;
        $('receiver-name').value = '';
        $('romaneio-photo').value = '';
        const fields = $('checklist-fields');
        fields.innerHTML = item.checklist?.length ? '<h3>Conferência dos itens</h3>' + item.checklist.map((entry, index) => `<div class="checklist-item"><div><strong>${escapeHtml(entry.referencia || entry.descricao || `Item ${entry.item_id}`)}</strong><small>Previsto: ${escapeHtml(entry.quantidade_prevista || 0)}</small></div><label>Qtd.<input type="number" min="0" step="any" data-check-index="${index}" value="${escapeHtml(entry.quantidade_prevista || 0)}"></label><input type="file" accept="image/*" capture="environment" data-photo-index="${index}"></div>`).join('') : '<p class="gps-status">Nenhum item de checklist cadastrado.</p>';
        limparAssinatura();
    }
    function limparAssinatura() { const canvas = $('signature-pad'); canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height); }
    function capturarAssinatura() { const canvas = $('signature-pad'); return canvas.toDataURL('image/png'); }
    async function dadosCheckout() {
        const item = entregas.find((delivery) => Number(delivery.id) === Number($('checkout-form').dataset.deliveryId));
        const nomeRecebedor = $('receiver-name').value.trim();
        const romaneio = await lerArquivo($('romaneio-photo').files[0]);
        if (!item || !nomeRecebedor || !romaneio || capturarAssinatura() === 'data:image/png;base64,') throw new Error('Preencha recebedor, foto e assinatura.');
        const checklist = [];
        for (const entry of item.checklist || []) {
            const index = checklist.length;
            const foto = await lerArquivo(document.querySelector(`[data-photo-index="${index}"]`)?.files[0]);
            if (!foto) throw new Error('Adicione uma foto para cada item do checklist.');
            const quantidade = Number(document.querySelector(`[data-check-index="${index}"]`)?.value || 0);
            checklist.push({ item_id: entry.item_id, referencia: entry.referencia, descricao: entry.descricao, quantidade_prevista: Number(entry.quantidade_prevista || 0), quantidade_entregue: quantidade, status: quantidade >= Number(entry.quantidade_prevista || 0) ? 'entregue' : 'faltante', motivo: quantidade >= Number(entry.quantidade_prevista || 0) ? null : 'Quantidade divergente', foto_item: foto });
        }
        return { motorista_id: motoristaId, desktop: false, nome_recebedor: nomeRecebedor, foto_romaneio: romaneio, assinatura_base64: capturarAssinatura(), checklist, tem_faltante: checklist.some((entry) => entry.status === 'faltante'), data_hora: new Date().toISOString() };
    }
    async function obterDadosDaAcao(action) {
        if (action === 'checkout') return dadosCheckout();
        if (action === 'falha') {
            const motivo = window.prompt('Motivo: cliente_ausente, endereco_incorreto, recusado, nao_localizado ou outro');
            const motivosValidos = ['cliente_ausente', 'endereco_incorreto', 'recusado', 'nao_localizado', 'outro'];
            if (!motivosValidos.includes(motivo)) return null;
            return { motorista_id: motoristaId, motivo, observacao: motivo, data_hora: new Date().toISOString() };
        }
        return { motorista_id: motoristaId, desktop: false, data_hora: new Date().toISOString() };
    }
    async function executarAcao(id, action) {
        const position = await getPosition();
        const body = { ...(await obterDadosDaAcao(action)), ...position };
        if (!body) return;
        const request = { id, endpoint: `${apiBase}/entregas/${id}/${action}`, action, body, operation_id: operationId(id, action, body) };
        request.body.operation_id = request.operation_id;
        if (!navigator.onLine) { const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]); aplicarStatusLocal(id, action); return; }
        try {
            const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(request.body) });
            if (!response.ok) throw new Error('Ação não aceita');
            aplicarStatusLocal(id, action);
        } catch (error) { const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]); aplicarStatusLocal(id, action); }
    }
    async function salvarOrdem() { const embarqueId = entregas[0]?.embarque_id; if (!embarqueId) return; const operationIdValue = `${motoristaId}:ordem:${embarqueId}:${entregas.map((item) => item.id).join('-')}`; const body = { ordem: entregas.map((item) => item.id), operation_id: operationIdValue, expected_updated_at: rotaVersion }; const request = { endpoint: `${apiBase}/embarques/${embarqueId}/reordenar`, method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body }; if (!navigator.onLine) { const queue = getQueue().filter((item) => item.type !== 'reordenar'); saveQueue([...queue, { ...request, type: 'reordenar', operation_id: operationIdValue }]); return; } const response = await fetch(request.endpoint, request); if (response.status === 409) { $('motorista-status').textContent = 'Conflito: o gestor alterou a rota. Atualize antes de salvar novamente.'; throw new Error('A rota foi alterada pelo gestor'); } if (!response.ok) throw new Error('Não foi possível salvar a ordem'); }
    async function mover(index, delta) { const target = index + delta; if (target < 0 || target >= entregas.length) return; [entregas[index], entregas[target]] = [entregas[target], entregas[index]]; persist(); try { await salvarOrdem(); } catch (error) { $('motorista-status').textContent = 'Ordem alterada localmente; será salva quando houver conexão'; } }
    async function sincronizarFila() { if (!navigator.onLine) return; await queueReady; const remaining = []; for (const request of [...getQueue()]) { try { const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(request.body) }); if (response.status === 409) { request.conflict = true; remaining.push(request); $('motorista-status').textContent = 'Há uma alteração de rota pendente de revisão.'; } else if (!response.ok) remaining.push(request); } catch (error) { remaining.push(request); } } await salvarFila(remaining); atualizarConflitoRota(); }
    document.addEventListener('click', (event) => { const button = event.target.closest('[data-action], [data-order]'); if (!button) return; if (button.dataset.action === 'checkout') abrirCheckout(entregas.find((item) => Number(item.id) === Number(button.dataset.id))); else if (button.dataset.action) executarAcao(button.dataset.id, button.dataset.action); if (button.dataset.order) mover(Number(button.dataset.index), button.dataset.order === 'up' ? -1 : 1); });
    $('checkout-cancel')?.addEventListener('click', () => { $('checkout-modal').hidden = true; });
    $('signature-clear')?.addEventListener('click', limparAssinatura);
    $('checkout-form')?.addEventListener('submit', async (event) => { event.preventDefault(); try { const id = $('checkout-form').dataset.deliveryId; $('checkout-modal').hidden = true; const position = await getPosition(); const body = { ...(await obterDadosDaAcao('checkout')), ...position }; const request = { id, endpoint: `${apiBase}/entregas/${id}/checkout`, action: 'checkout', body, operation_id: operationId(id, 'checkout', body) }; request.body.operation_id = request.operation_id; if (!navigator.onLine) { saveQueue([...getQueue(), request]); aplicarStatusLocal(id, 'checkout'); return; } const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(body) }); if (!response.ok) throw new Error('Checkout não aceito'); aplicarStatusLocal(id, 'checkout'); } catch (error) { window.alert(error.message); $('checkout-modal').hidden = false; } });
    const signatureCanvas = $('signature-pad'); let drawing = false; signatureCanvas?.addEventListener('pointerdown', (event) => { drawing = true; signatureCanvas.setPointerCapture(event.pointerId); const rect = signatureCanvas.getBoundingClientRect(); const context = signatureCanvas.getContext('2d'); context.beginPath(); context.moveTo((event.clientX - rect.left) * signatureCanvas.width / rect.width, (event.clientY - rect.top) * signatureCanvas.height / rect.height); }); signatureCanvas?.addEventListener('pointermove', (event) => { if (!drawing) return; const rect = signatureCanvas.getBoundingClientRect(); const context = signatureCanvas.getContext('2d'); context.lineWidth = 3; context.lineCap = 'round'; context.lineTo((event.clientX - rect.left) * signatureCanvas.width / rect.width, (event.clientY - rect.top) * signatureCanvas.height / rect.height); context.stroke(); }); signatureCanvas?.addEventListener('pointerup', () => { drawing = false; });
    $('btn-refresh-route')?.addEventListener('click', carregarEntregas);
    $('route-conflict-refresh')?.addEventListener('click', async () => { await carregarEntregas(); $('route-conflict').hidden = true; });
    $('route-conflict-discard')?.addEventListener('click', async () => { await salvarFila(getQueue().filter((item) => !(item.type === 'reordenar' && item.conflict))); $('route-conflict').hidden = true; $('motorista-status').textContent = 'Ordenação local descartada; rota do gestor preservada.'; await carregarEntregas(); });
    $('driver-theme-toggle')?.addEventListener('click', alternarTema);
    window.addEventListener('online', () => { setConnectionState(); sincronizarFila(); carregarEntregas(); carregarNotificacoes(); });
    navigator.serviceWorker?.addEventListener('message', (event) => { if (event.data?.type === 'frota-offline-sync') sincronizarFila(); });
    window.addEventListener('offline', setConnectionState);
    if ('serviceWorker' in navigator) {
        const appBase = window.location.pathname.split('/portal/')[0];
        navigator.serviceWorker.register(`${appBase}/portal/modules/frota/service-worker.js`).catch(() => {});
    }
    aplicarTema(); setConnectionState(); carregarEntregas(); carregarNotificacoes(); sincronizarFila();
}());
