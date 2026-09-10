// ======================================================================
// CADASTRO DE FROTA - VEÍCULOS + MOTORISTAS + MAPA AO VIVO (MapLibre)
// ======================================================================

const CONFIG = {
    API_BASE: '/v1/frota'
};

// ================================================================
// AUXILIARES
// ================================================================
function getAuthToken() {
    const token = localStorage.getItem('authToken');
    if (!token && !window.location.pathname.includes('login.php')) {
        window.location.href = '/portal/login.php';
    }
    return token;
}

function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    const icon = document.querySelector('.theme-toggle i');
    if (icon) icon.className = newTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

let debounceTimerVeiculo = null;
function debounceCarregarVeiculosCad() {
    clearTimeout(debounceTimerVeiculo);
    debounceTimerVeiculo = setTimeout(carregarVeiculosCad, 400);
}

let debounceTimerMotorista = null;
function debounceCarregarMotoristasCad() {
    clearTimeout(debounceTimerMotorista);
    debounceTimerMotorista = setTimeout(carregarMotoristasCad, 400);
}

// ================================================================
// ABAS
// ================================================================
function mudarAbaCadFrota(tab, el) {
    document.querySelectorAll('#cadfrota-tabs .cadfrota-tab').forEach(btn => {
        btn.classList.toggle('active', btn === el);
        btn.setAttribute('aria-selected', btn === el ? 'true' : 'false');
    });
    document.querySelectorAll('.cadfrota-tab-panel').forEach(panel => {
        panel.hidden = panel.id !== `cadfrota-tab-${tab}`;
    });

    if (tab === 'veiculos') carregarVeiculosCad();
    if (tab === 'motoristas') carregarMotoristasCad();
    if (tab === 'mapa') carregarMapaCadFrota();
}

function recarregarAbaAtualCadFrota() {
    const ativa = document.querySelector('#cadfrota-tabs .cadfrota-tab.active');
    const tab = ativa ? ativa.dataset.tab : 'veiculos';
    if (tab === 'veiculos') carregarVeiculosCad();
    else if (tab === 'motoristas') carregarMotoristasCad();
    else if (tab === 'mapa') carregarMapaCadFrota();
    carregarContadores();
}

// ================================================================
// CONTADORES DO HEADER
// ================================================================
async function carregarContadores() {
    const token = getAuthToken();
    try {
        const [rv, rm] = await Promise.all([
            fetch(`${CONFIG.API_BASE}/veiculos?limite=1`, { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch(`${CONFIG.API_BASE}/motoristas?limite=1`, { headers: { 'Authorization': 'Bearer ' + token } })
        ]);
        const dv = await rv.json();
        const dm = await rm.json();
        const totalV = document.getElementById('total-veiculos-cad');
        const totalM = document.getElementById('total-motoristas-cad');
        if (totalV) totalV.textContent = dv.pagination?.total ?? (dv.data || []).length;
        if (totalM) totalM.textContent = dm.pagination?.total ?? (dm.data || []).length;
    } catch (error) {
        console.error('Erro ao carregar contadores:', error);
    }
}

// ================================================================
// VEÍCULOS
// ================================================================
let vinculosCobliPorVeiculo = new Map();

async function carregarVinculosCobli() {
    const token = getAuthToken();
    try {
        const response = await fetch(`${CONFIG.API_BASE}/cobli/veiculos-vinculados`, { headers: { 'Authorization': 'Bearer ' + token } });
        const payload = await response.json();
        if (payload.success) {
            vinculosCobliPorVeiculo = new Map((payload.data || []).map(v => [v.veiculo_id, v]));
        }
    } catch (error) {
        console.error('Erro ao carregar vínculos Cobli:', error);
    }
}

async function carregarVeiculosCad() {
    const token = getAuthToken();
    const container = document.getElementById('cadfrota-lista-veiculos');
    const busca = document.getElementById('cadfrota-busca-veiculo')?.value || '';
    const status = document.getElementById('cadfrota-filtro-status-veiculo')?.value || '';
    if (container) container.innerHTML = '<div class="cadfrota-empty"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando veículos...</div>';

    await carregarVinculosCobli();

    try {
        let url = `${CONFIG.API_BASE}/veiculos?limite=100`;
        if (busca) url += `&busca=${encodeURIComponent(busca)}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;

        const response = await fetch(url, { headers: { 'Authorization': 'Bearer ' + token } });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao listar veículos');

        const veiculos = payload.data || [];
        const totalV = document.getElementById('total-veiculos-cad');
        if (totalV) totalV.textContent = payload.pagination?.total ?? veiculos.length;

        if (!veiculos.length) {
            if (container) container.innerHTML = '<div class="cadfrota-empty">Nenhum veículo encontrado.</div>';
            return;
        }

        if (container) {
            container.innerHTML = veiculos.map(v => {
                const vinculo = vinculosCobliPorVeiculo.get(v.id);
                return `
                <div class="cadfrota-card">
                    <div class="cadfrota-card-header">
                        <div>
                            <div class="cadfrota-card-title">${escapeHtml(v.placa)}</div>
                            <div class="cadfrota-card-sub">${escapeHtml(v.marca || '')} ${escapeHtml(v.modelo || '')} ${v.ano ? '· ' + escapeHtml(v.ano) : ''}</div>
                        </div>
                        <span class="hist-status-badge ${v.status === 'disponivel' ? 'finalizado' : v.status === 'manutencao' ? 'problema' : v.status === 'inativo' ? 'cancelado' : 'em_andamento'}">${escapeHtml(v.status || '-')}</span>
                    </div>
                    <div class="cadfrota-card-info">
                        ${vinculo ? '<span class="cadfrota-chip cobli-on"><i class="fa-solid fa-satellite-dish"></i> Cobli vinculado</span>' : '<span class="cadfrota-chip cobli-off"><i class="fa-solid fa-satellite-dish"></i> Sem Cobli</span>'}
                        ${v.capacidade_peso ? `<span class="cadfrota-chip">${escapeHtml(v.capacidade_peso)} kg</span>` : ''}
                        ${v.tipo ? `<span class="cadfrota-chip">${escapeHtml(v.tipo)}</span>` : ''}
                    </div>
                    <div class="cadfrota-card-actions">
                        <button type="button" class="cargas-clear-filter" onclick='abrirFormVeiculo(${JSON.stringify(v).replace(/'/g, "&#39;")})'>
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                        <button type="button" class="cargas-clear-filter" onclick="excluirVeiculoCad(${v.id})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar veículos:', error);
        if (container) container.innerHTML = '<div class="cadfrota-empty text-red-500">Erro ao carregar veículos.</div>';
    }
}

function abrirFormVeiculo(veiculo) {
    document.getElementById('veiculo-cad-id').value = veiculo?.id || '';
    document.getElementById('veiculo-cad-placa').value = veiculo?.placa || '';
    document.getElementById('veiculo-cad-tipo').value = veiculo?.tipo || 'bau';
    document.getElementById('veiculo-cad-marca').value = veiculo?.marca || '';
    document.getElementById('veiculo-cad-modelo').value = veiculo?.modelo || '';
    document.getElementById('veiculo-cad-ano').value = veiculo?.ano || '';
    document.getElementById('veiculo-cad-cor').value = veiculo?.cor || '';
    document.getElementById('veiculo-cad-capacidade').value = veiculo?.capacidade_peso || '';
    document.getElementById('veiculo-cad-status').value = veiculo?.status || 'disponivel';
    document.getElementById('modal-veiculo-titulo').textContent = veiculo?.id ? 'Editar veículo' : 'Novo veículo';

    const modal = new bootstrap.Modal(document.getElementById('modalVeiculoCad'));
    modal.show();
}

async function salvarVeiculoCad() {
    const token = getAuthToken();
    const id = document.getElementById('veiculo-cad-id').value;
    const placa = document.getElementById('veiculo-cad-placa').value.trim().toUpperCase();

    if (!placa) {
        alert('Informe a placa do veículo.');
        return;
    }

    const dados = {
        placa,
        tipo: document.getElementById('veiculo-cad-tipo').value,
        marca: document.getElementById('veiculo-cad-marca').value.trim(),
        modelo: document.getElementById('veiculo-cad-modelo').value.trim(),
        ano: document.getElementById('veiculo-cad-ano').value || null,
        cor: document.getElementById('veiculo-cad-cor').value.trim(),
        capacidade_peso: document.getElementById('veiculo-cad-capacidade').value || null,
        status: document.getElementById('veiculo-cad-status').value
    };

    try {
        const url = id ? `${CONFIG.API_BASE}/veiculos/${id}` : `${CONFIG.API_BASE}/veiculos`;
        const method = id ? 'PUT' : 'POST';
        const response = await fetch(url, {
            method,
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao salvar veículo');

        bootstrap.Modal.getInstance(document.getElementById('modalVeiculoCad'))?.hide();
        await carregarVeiculosCad();
    } catch (error) {
        console.error('Erro ao salvar veículo:', error);
        alert('Erro ao salvar veículo: ' + error.message);
    }
}

async function excluirVeiculoCad(id) {
    if (!confirm('Excluir este veículo? Esta ação não pode ser desfeita.')) return;
    const token = getAuthToken();
    try {
        const response = await fetch(`${CONFIG.API_BASE}/veiculos/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao excluir veículo');
        await carregarVeiculosCad();
    } catch (error) {
        console.error('Erro ao excluir veículo:', error);
        alert('Erro ao excluir veículo: ' + error.message);
    }
}

// ================================================================
// MOTORISTAS
// ================================================================
async function carregarMotoristasCad() {
    const token = getAuthToken();
    const container = document.getElementById('cadfrota-lista-motoristas');
    const busca = document.getElementById('cadfrota-busca-motorista')?.value || '';
    const status = document.getElementById('cadfrota-filtro-status-motorista')?.value || '';
    if (container) container.innerHTML = '<div class="cadfrota-empty"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando motoristas...</div>';

    try {
        let url = `${CONFIG.API_BASE}/motoristas?limite=100`;
        if (busca) url += `&busca=${encodeURIComponent(busca)}`;
        if (status) url += `&status=${encodeURIComponent(status)}`;

        const response = await fetch(url, { headers: { 'Authorization': 'Bearer ' + token } });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao listar motoristas');

        const motoristas = payload.data || [];
        const totalM = document.getElementById('total-motoristas-cad');
        if (totalM) totalM.textContent = payload.pagination?.total ?? motoristas.length;

        if (!motoristas.length) {
            if (container) container.innerHTML = '<div class="cadfrota-empty">Nenhum motorista encontrado.</div>';
            return;
        }

        if (container) {
            container.innerHTML = motoristas.map(m => `
                <div class="cadfrota-card">
                    <div class="cadfrota-card-header">
                        <div>
                            <div class="cadfrota-card-title">${escapeHtml(m.nome)}</div>
                            <div class="cadfrota-card-sub">${escapeHtml(m.cpf || 'CPF não informado')}</div>
                        </div>
                        <span class="hist-status-badge ${m.status === 'ativo' ? 'finalizado' : m.status === 'inativo' ? 'cancelado' : 'em_andamento'}">${escapeHtml(m.status || '-')}</span>
                    </div>
                    <div class="cadfrota-card-info">
                        ${m.erp_id ? '<span class="cadfrota-chip erp"><i class="fa-solid fa-plug-circle-bolt"></i> Vinculado ao ERP</span>' : ''}
                        ${m.veiculo_placa ? `<span class="cadfrota-chip"><i class="fa-solid fa-truck"></i> ${escapeHtml(m.veiculo_placa)}</span>` : ''}
                        ${m.telefone ? `<span class="cadfrota-chip">${escapeHtml(m.telefone)}</span>` : ''}
                    </div>
                    <div class="cadfrota-card-actions">
                        <button type="button" class="cargas-clear-filter" onclick='abrirFormMotorista(${JSON.stringify(m).replace(/'/g, "&#39;")})'>
                            <i class="fa-solid fa-pen"></i> Editar
                        </button>
                        <button type="button" class="cargas-clear-filter" onclick="excluirMotoristaCad(${m.id})">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }
    } catch (error) {
        console.error('Erro ao carregar motoristas:', error);
        if (container) container.innerHTML = '<div class="cadfrota-empty text-red-500">Erro ao carregar motoristas.</div>';
    }
}

function abrirFormMotorista(motorista) {
    document.getElementById('motorista-cad-id').value = motorista?.id || '';
    document.getElementById('motorista-cad-nome').value = motorista?.nome || '';
    document.getElementById('motorista-cad-cpf').value = motorista?.cpf || '';
    document.getElementById('motorista-cad-cnh').value = motorista?.cnh || '';
    document.getElementById('motorista-cad-telefone').value = motorista?.telefone || '';
    document.getElementById('motorista-cad-email').value = motorista?.email || '';
    document.getElementById('motorista-cad-endereco').value = motorista?.endereco || '';
    document.getElementById('motorista-cad-status').value = motorista?.status || 'ativo';
    document.getElementById('modal-motorista-titulo').textContent = motorista?.id ? 'Editar motorista' : 'Novo motorista';

    const modal = new bootstrap.Modal(document.getElementById('modalMotoristaCad'));
    modal.show();
}

async function salvarMotoristaCad() {
    const token = getAuthToken();
    const id = document.getElementById('motorista-cad-id').value;
    const nome = document.getElementById('motorista-cad-nome').value.trim();

    if (!nome) {
        alert('Informe o nome do motorista.');
        return;
    }

    const dados = {
        nome,
        cpf: document.getElementById('motorista-cad-cpf').value.trim(),
        cnh: document.getElementById('motorista-cad-cnh').value.trim(),
        telefone: document.getElementById('motorista-cad-telefone').value.trim(),
        email: document.getElementById('motorista-cad-email').value.trim(),
        endereco: document.getElementById('motorista-cad-endereco').value.trim(),
        status: document.getElementById('motorista-cad-status').value
    };

    try {
        const url = id ? `${CONFIG.API_BASE}/motoristas/${id}` : `${CONFIG.API_BASE}/motoristas`;
        const method = id ? 'PUT' : 'POST';
        const response = await fetch(url, {
            method,
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao salvar motorista');

        bootstrap.Modal.getInstance(document.getElementById('modalMotoristaCad'))?.hide();
        await carregarMotoristasCad();
    } catch (error) {
        console.error('Erro ao salvar motorista:', error);
        alert('Erro ao salvar motorista: ' + error.message);
    }
}

async function excluirMotoristaCad(id) {
    if (!confirm('Excluir/inativar este motorista?')) return;
    const token = getAuthToken();
    try {
        const response = await fetch(`${CONFIG.API_BASE}/motoristas/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao excluir motorista');
        await carregarMotoristasCad();
    } catch (error) {
        console.error('Erro ao excluir motorista:', error);
        alert('Erro ao excluir motorista: ' + error.message);
    }
}

// ================================================================
// MAPA AO VIVO (MapLibre GL + OpenFreeMap)
// ================================================================
let cadFrotaMapa = null;
let cadFrotaMarcadores = [];

function inicializarMapaCadFrota() {
    if (cadFrotaMapa) return;
    cadFrotaMapa = new maplibregl.Map({
        container: 'cadfrota-mapa',
        style: 'https://tiles.openfreemap.org/styles/liberty',
        center: [-49.53561648427039, -28.979438954992666],
        zoom: 11,
        attributionControl: true
    });
    cadFrotaMapa.addControl(new maplibregl.NavigationControl(), 'top-right');
}

function limparMarcadoresCadFrota() {
    cadFrotaMarcadores.forEach(m => m.remove());
    cadFrotaMarcadores = [];
}

async function carregarMapaCadFrota() {
    inicializarMapaCadFrota();

    const token = getAuthToken();
    const vazio = document.getElementById('cadfrota-mapa-vazio');

    try {
        const response = await fetch(`${CONFIG.API_BASE}/cobli/frota/posicoes`, { headers: { 'Authorization': 'Bearer ' + token } });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao buscar posições');

        const veiculos = payload.data || [];
        limparMarcadoresCadFrota();

        if (!veiculos.length) {
            if (vazio) vazio.style.display = 'block';
            return;
        }
        if (vazio) vazio.style.display = 'none';

        const bounds = new maplibregl.LngLatBounds();

        veiculos.forEach(v => {
            const el = document.createElement('div');
            el.className = 'cadfrota-mapa-marcador';
            const emMovimento = (v.velocidade || 0) > 0;
            el.innerHTML = `
                <div class="cadfrota-mapa-placa">${escapeHtml(v.placa)}</div>
                <div class="cadfrota-mapa-icone ${emMovimento ? '' : 'parado'}"><i class="fa-solid fa-truck"></i></div>
            `;

            const popupHtml = `
                <div style="font-size:13px; line-height:1.5;">
                    <strong>${escapeHtml(v.placa)}</strong> — ${escapeHtml(v.modelo || '')}<br>
                    ${v.motorista ? `Motorista: ${escapeHtml(v.motorista)}<br>` : ''}
                    Velocidade: ${v.velocidade ?? 0} km/h<br>
                    Ignição: ${v.ignicao_ligada ? 'Ligada' : 'Desligada'}<br>
                    ${v.atualizado_em ? `Atualizado: ${new Date(v.atualizado_em).toLocaleString('pt-BR')}` : ''}
                </div>
            `;

            const marker = new maplibregl.Marker({ element: el })
                .setLngLat([v.longitude, v.latitude])
                .setPopup(new maplibregl.Popup({ offset: 30 }).setHTML(popupHtml))
                .addTo(cadFrotaMapa);

            cadFrotaMarcadores.push(marker);
            bounds.extend([v.longitude, v.latitude]);
        });

        if (veiculos.length > 1) {
            cadFrotaMapa.fitBounds(bounds, { padding: 60, maxZoom: 14 });
        } else if (veiculos.length === 1) {
            cadFrotaMapa.flyTo({ center: [veiculos[0].longitude, veiculos[0].latitude], zoom: 14 });
        }
    } catch (error) {
        console.error('Erro ao carregar mapa da frota:', error);
        if (vazio) { vazio.style.display = 'block'; vazio.textContent = 'Erro ao carregar posições da frota.'; }
    }
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================
document.addEventListener('DOMContentLoaded', () => {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        const icon = document.querySelector('.theme-toggle i');
        if (icon) icon.className = 'fa-solid fa-sun';
    }

    carregarContadores();
    carregarVeiculosCad();
});
