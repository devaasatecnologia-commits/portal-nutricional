(function () {
    'use strict';
    const app = document.querySelector('.motorista-app');
    if (!app) return;
    const motoristaId = Number(window.MOTORISTA_ID_INICIAL || app.dataset.motoristaId || localStorage.getItem('motoristaId') || 0);
    const cacheKey = `frota.motorista.${motoristaId}.entregas`;
    const queueKey = `frota.motorista.${motoristaId}.offline.queue`;
    const apiBase = `${window.API_URL || '/v1'}/frota`;
    let entregas = [];
    const $ = (id) => document.getElementById(id);
    const getQueue = () => { try { return JSON.parse(localStorage.getItem(queueKey) || '[]'); } catch (error) { return []; } };
    function saveQueue(queue) { localStorage.setItem(queueKey, JSON.stringify(queue)); $('fila-pendente').textContent = queue.length; }
    function operationId(id, action, body) { return `${motoristaId}:${id}:${action}:${body.data_hora}`; }
    function getPosition() { return new Promise((resolve) => { if (!navigator.geolocation) return resolve({}); $('gps-status').textContent = 'Obtendo GPS...'; navigator.geolocation.getCurrentPosition((position) => { $('gps-status').textContent = 'GPS confirmado'; resolve({ latitude: position.coords.latitude, longitude: position.coords.longitude, precisao: position.coords.accuracy }); }, () => { $('gps-status').textContent = 'GPS indisponível'; resolve({}); }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 30000 }); }); }
    function authHeaders() { const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken'); return token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' }; }
    function setConnectionState() { const online = navigator.onLine; const state = $('connection-state'); state.classList.toggle('is-offline', !online); state.querySelector('span:last-child').textContent = online ? 'Online' : 'Offline'; $('offline-notice').hidden = online; }
    function formatAddress(item) { return [item.endereco, item.numero, item.bairro, item.cidade, item.uf].filter(Boolean).join(', ') || 'Endereço não informado'; }
    function escapeHtml(value) { return String(value).replace(/[&<>'\"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '\"': '&quot;' }[character])); }
    function render() { const list = $('delivery-list'); if (!entregas.length) { list.innerHTML = '<div class="empty-state">Nenhuma entrega encontrada para hoje.</div>'; return; } list.innerHTML = entregas.map((item, index) => { const complete = ['entregue', 'entregue_com_problema'].includes(item.status); return `<article class="delivery-card${complete ? ' is-complete' : ''}"><div class="delivery-order"><span>Parada ${index + 1}</span><span class="order-actions"><button data-order="up" data-index="${index}" ${index === 0 ? 'disabled' : ''}>Subir</button><button data-order="down" data-index="${index}" ${index === entregas.length - 1 ? 'disabled' : ''}>Descer</button></span></div><h2>${escapeHtml(item.cliente_nome || `Entrega #${item.id}`)}</h2><p class="delivery-address">${escapeHtml(formatAddress(item))}</p><div class="delivery-meta"><span>${escapeHtml(item.status || 'pendente')}</span>${item.codigo_rastreamento ? `<span>${escapeHtml(item.codigo_rastreamento)}</span>` : ''}</div><div class="delivery-actions"><button class="checkin" data-action="checkin" data-id="${item.id}" ${complete ? 'disabled' : ''}>Cheguei</button><button class="checkout" data-action="checkout" data-id="${item.id}" ${complete ? 'disabled' : ''}>Entregue</button><button class="failure" data-action="falha" data-id="${item.id}" ${complete ? 'disabled' : ''}>Problema</button></div></article>`; }).join(''); }
    function updateSummary() { $('total-entregas').textContent = entregas.length; $('entregas-concluidas').textContent = entregas.filter((item) => ['entregue', 'entregue_com_problema'].includes(item.status)).length; $('fila-pendente').textContent = getQueue().length; }
    function persist() { localStorage.setItem(cacheKey, JSON.stringify(entregas)); render(); updateSummary(); }
    async function carregarEntregas() { if (!motoristaId) { $('motorista-status').textContent = 'Informe o motorista para carregar a rota'; $('delivery-list').innerHTML = '<div class="empty-state">A rota ainda não foi vinculada a um motorista.</div>'; return; } try { const response = await fetch(`${apiBase}/motoristas/${motoristaId}/entregas/hoje`, { headers: authHeaders(), credentials: 'include' }); if (!response.ok) throw new Error('Falha ao carregar rota'); const payload = await response.json(); entregas = payload.data?.entregas || payload.entregas || []; persist(); $('motorista-status').textContent = 'Rota atualizada agora'; } catch (error) { entregas = JSON.parse(localStorage.getItem(cacheKey) || '[]'); render(); updateSummary(); $('motorista-status').textContent = entregas.length ? 'Usando a última rota salva neste aparelho' : 'Não foi possível carregar a rota'; } }
    async function carregarNotificacoes() { if (!motoristaId || !navigator.onLine) return; try { const response = await fetch(`${apiBase}/motoristas/${motoristaId}/notificacoes?limite=5`, { headers: authHeaders(), credentials: 'include' }); if (!response.ok) return; const payload = await response.json(); const notification = (payload.data || []).find((item) => !item.lida); if (notification) { $('driver-alert').hidden = false; $('driver-alert').textContent = `${notification.titulo}: ${notification.mensagem}`; } } catch (error) { /* O app continua operacional offline. */ } }
    function aplicarStatusLocal(id, action) { const item = entregas.find((delivery) => Number(delivery.id) === Number(id)); if (!item) return; item.status = action === 'checkout' ? 'entregue' : action === 'falha' ? 'pendente' : 'em_entrega'; persist(); }
    function escolherArquivo() { return new Promise((resolve) => { const input = document.createElement('input'); input.type = 'file'; input.accept = 'image/*'; input.capture = 'environment'; input.onchange = () => { const file = input.files[0]; if (!file) return resolve(null); const reader = new FileReader(); reader.onload = () => resolve(reader.result); reader.onerror = () => resolve(null); reader.readAsDataURL(file); }; input.click(); }); }
    async function obterDadosDaAcao(action) {
        if (action === 'checkout') {
            const nomeRecebedor = window.prompt('Nome de quem recebeu a entrega:');
            if (!nomeRecebedor || !nomeRecebedor.trim()) return null;
            const fotoRomaneio = await escolherArquivo();
            if (!fotoRomaneio) { window.alert('A foto do romaneio assinado é obrigatória.'); return null; }
            return { motorista_id: motoristaId, desktop: false, nome_recebedor: nomeRecebedor.trim(), foto_romaneio: fotoRomaneio, data_hora: new Date().toISOString() };
        }
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
        if (!navigator.onLine) { const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]); aplicarStatusLocal(id, action); return; }
        try {
            const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(request.body) });
            if (!response.ok) throw new Error('Ação não aceita');
            aplicarStatusLocal(id, action);
        } catch (error) { const queue = getQueue(); if (!queue.some((item) => item.operation_id === request.operation_id)) saveQueue([...queue, request]); aplicarStatusLocal(id, action); }
    }
    async function salvarOrdem() { const embarqueId = entregas[0]?.embarque_id; if (!embarqueId) return; const body = { ordem: entregas.map((item) => item.id) }; const request = { endpoint: `${apiBase}/embarques/${embarqueId}/reordenar`, method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, body }; if (!navigator.onLine) { const queue = getQueue().filter((item) => item.type !== 'reordenar'); saveQueue([...queue, { ...request, type: 'reordenar', operation_id: `${motoristaId}:ordem:${embarqueId}` }]); return; } const response = await fetch(request.endpoint, request); if (!response.ok) throw new Error('Não foi possível salvar a ordem'); }
    async function mover(index, delta) { const target = index + delta; if (target < 0 || target >= entregas.length) return; [entregas[index], entregas[target]] = [entregas[target], entregas[index]]; persist(); try { await salvarOrdem(); } catch (error) { $('motorista-status').textContent = 'Ordem alterada localmente; será salva quando houver conexão'; } }
    async function sincronizarFila() { if (!navigator.onLine) return; const remaining = []; for (const request of getQueue()) { try { const response = await fetch(request.endpoint, { method: 'POST', headers: { ...authHeaders(), 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify(request.body) }); if (!response.ok) remaining.push(request); } catch (error) { remaining.push(request); } } saveQueue(remaining); }
    document.addEventListener('click', (event) => { const button = event.target.closest('[data-action], [data-order]'); if (!button) return; if (button.dataset.action) executarAcao(button.dataset.id, button.dataset.action); if (button.dataset.order) mover(Number(button.dataset.index), button.dataset.order === 'up' ? -1 : 1); });
    $('btn-refresh-route')?.addEventListener('click', carregarEntregas);
    window.addEventListener('online', () => { setConnectionState(); sincronizarFila(); carregarEntregas(); carregarNotificacoes(); });
    window.addEventListener('offline', setConnectionState);
    if ('serviceWorker' in navigator) {
        const appBase = window.location.pathname.split('/portal/')[0];
        navigator.serviceWorker.register(`${appBase}/portal/modules/frota/service-worker.js`).catch(() => {});
    }
    setConnectionState(); carregarEntregas(); carregarNotificacoes(); sincronizarFila();
}());
