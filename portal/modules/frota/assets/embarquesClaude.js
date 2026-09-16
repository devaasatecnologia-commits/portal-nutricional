var API_BASE = window.API_BASE || (window.location.pathname.startsWith('/API/') ? '/API' : '') + '/v1';
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
        const base = window.location.pathname.startsWith('/API/') ? '/API' : '';
        window.location.href = base + '/portal/login.php';
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

async function mapearErpIdsParaSistema(erpIds) {
    const token = getAuthToken();
    if (!token) return { sistemaIds: [], erros: ['Token não encontrado'] };
    
    const sistemaIds = [];
    const erros = [];
    const erpIdsArray = (Array.isArray(erpIds) ? erpIds : [erpIds])
        .map(id => parseInt(id))
        .filter(id => !isNaN(id) && id > 0);
    
    if (erpIdsArray.length === 0) {
        return { sistemaIds: [], erros: ['Nenhum ID válido fornecido'] };
    }
    
    let todosEmbarques = [];
    try {
        let pagina = 1;
        let totalPaginas = 1;

        do {
            const respLista = await fetch(API_BASE + `/frota/embarques?limite=100&pagina=${pagina}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (!respLista.ok) throw new Error(`HTTP ${respLista.status}`);

            const dados = await respLista.json();
            if (!dados.success || !Array.isArray(dados.data)) {
                throw new Error(dados.error || 'Resposta inválida ao listar embarques');
            }

            todosEmbarques.push(...dados.data);
            totalPaginas = Math.max(1, Number(dados.pagination?.total_paginas || 1));
            pagina++;
        } while (pagina <= totalPaginas);

        console.log('📋 Total de embarques no sistema:', todosEmbarques.length);
    } catch (e) {
        erros.push('Erro ao buscar lista: ' + e.message);
        return { sistemaIds: [], erros };
    }
    
    const mapaErpParaSistema = new Map();
    
    for (const emb of todosEmbarques) {
        const sistemaId = parseInt(emb.id);
        
        if (!mapaErpParaSistema.has(sistemaId)) {
            mapaErpParaSistema.set(sistemaId, sistemaId);
        }
        
        if (emb.erp_embarque_id) {
            const erpId = parseInt(emb.erp_embarque_id);
            if (!isNaN(erpId) && !mapaErpParaSistema.has(erpId)) {
                mapaErpParaSistema.set(erpId, sistemaId);
            }
        }
        
        if (emb.erp_ids_agrupados) {
            const ids = String(emb.erp_ids_agrupados)
                .split(',')
                .map(s => parseInt(s.trim()))
                .filter(n => !isNaN(n) && n > 0);
            
            for (const idAgrupado of ids) {
                if (!mapaErpParaSistema.has(idAgrupado)) {
                    mapaErpParaSistema.set(idAgrupado, sistemaId);
                }
            }
        }
    }
    
    console.log('🗺️ Mapa ERP→Sistema criado com', mapaErpParaSistema.size, 'entradas');
    
    for (const erpId of erpIdsArray) {
        if (mapaErpParaSistema.has(erpId)) {
            const sistemaId = mapaErpParaSistema.get(erpId);
            sistemaIds.push(sistemaId);
            console.log(`✅ ${erpId} → ${sistemaId}`);
        } else {
            erros.push(`ID ${erpId} não encontrado no sistema`);
            console.warn(`❌ ${erpId} não encontrado`);
        }
    }
    
    const sistemaIdsUnicos = [...new Set(sistemaIds)];
    
    console.log('📊 Resultado final:', {
        entrada: erpIdsArray,
        saida: sistemaIdsUnicos,
        erros: erros
    });
    
    return { sistemaIds: sistemaIdsUnicos, erros };
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
        const response = await fetch(API_BASE + '/frota/importar/embarques-erp', {
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
// RENDERIZAR ABAS DE STATUS (agora como pills)
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
                <button type="button" class="disponiveis-tab ${ativa}" data-aba="${key}" onclick="mudarAba('${key}')">
                    ${labels[key] || key}
                    <span class="disponiveis-tab-badge">${statusCount[key]}</span>
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
    document.querySelectorAll('.disponiveis-tab').forEach(btn => {
        btn.classList.toggle('ativa', btn.dataset.aba === aba);
    });
    renderizarDisponiveis(dadosEmbarquesERP, aba);
}
// ======================================================================
// RENDERIZAR LISTA DE DISPONÍVEIS (formato select/checkboxes)
// ======================================================================
function renderizarDisponiveis(embarques, aba) {
    const container = document.getElementById('lista-disponiveis');
    if (!container) return;

    const abaNormalizada = (aba || 'todos').toUpperCase();
    let filtrados = embarques;
    if (aba && aba !== 'todos') {
        filtrados = embarques.filter(emb => {
            const statusERP = (emb.status_logistico || 'PENDENTE').toUpperCase();
            return statusERP === abaNormalizada;
        });
    }

    // Filtro de busca
    const termoBusca = normalizarBusca(document.getElementById('busca-disponiveis')?.value || '');
    if (termoBusca) {
        filtrados = filtrados.filter(emb => {
            const texto = [
                emb.idembarque,
                emb.rota,
                emb.placa,
                emb.motorista_nome,
                emb.idfilial,
                emb.motorista_razao
            ].filter(Boolean).join(' ');
            return normalizarBusca(texto).includes(termoBusca);
        });
    }

    // Atualiza info do cabeçalho
    let totalPedidos = 0;
    let totalValor = 0;
    let filiais = new Set();
    filtrados.forEach(emb => {
        totalPedidos += emb.total_pedidos || 0;
        totalValor += emb.valor_total || 0;
        if (emb.idfilial) filiais.add(emb.idfilial);
    });

    const infoEl = document.getElementById('info-disponiveis');
    if (infoEl) {
        infoEl.textContent = `${filtrados.length} embarques • ${totalPedidos} pedidos • ${formatarMoeda(totalValor)} • ${filiais.size} filiais`;
    }

    if (filtrados.length === 0) {
        container.innerHTML = `
            <div class="text-center py-6 text-slate-400">
                <i class="fa-regular fa-inbox text-2xl block mb-2"></i>
                Nenhum embarque ${termoBusca ? 'corresponde à busca' : 'com este status'}
            </div>
        `;
        atualizarContadorSelecao();
       // atualizarFooterDisponiveis();
        return;
    }

    let html = '';
    filtrados.forEach(emb => {
        const selecionado = embarquesSelecionados.includes(emb.idembarque);
        const statusClass = {
            'PENDENTE': 'status-pendente',
            'SEPARADO': 'status-separado',
            'CARREGADO': 'status-carregado'
        }[emb.status_logistico] || 'status-pendente';

        const clientesNomes = (emb.clientes || []).slice(0, 2).map(c => c.nome || c.razao || 'Cliente');
        const temMais = (emb.clientes || []).length > 2;

        html += `
            <label class="disponivel-item ${selecionado ? 'selecionado' : ''}" data-id="${emb.idembarque}">
                <input type="checkbox" value="${emb.idembarque}" ${selecionado ? 'checked' : ''} onchange="toggleSelecionarDisponivel(${emb.idembarque})">
                <div class="disponivel-item-content">
                    <div class="disponivel-item-header">
                        <span class="disponivel-item-id">#${emb.idembarque}</span>
                        <span class="disponivel-item-status ${statusClass}">${emb.status_logistico || 'PENDENTE'}</span>
                        ${emb.gerou_nf === 'S' ? '<span class="disponivel-item-tag tag-nf">NF Gerada</span>' : ''}
                        ${emb.pex_conferido === 'S' ? '<span class="disponivel-item-tag tag-sep">Separado</span>' : ''}
                        ${emb.placa ? `<span class="disponivel-item-placa"><i class="fa-solid fa-truck"></i> ${escapeHtml(emb.placa)}</span>` : ''}
                        <span class="disponivel-item-filial"><i class="fa-solid fa-building"></i> ${emb.idfilial || '-'}</span>
                    </div>
                    <div class="disponivel-item-rota">${escapeHtml(emb.rota || 'Sem descrição')}</div>
                    <div class="disponivel-item-meta">
                        <span><i class="fa-solid fa-box"></i> ${emb.total_pedidos || 0} pedidos</span>
                        <span><i class="fa-solid fa-sack-dollar"></i> ${formatarMoeda(emb.valor_total || 0)}</span>
                        ${emb.motorista_nome ? `<span><i class="fa-solid fa-user"></i> ${escapeHtml(emb.motorista_nome)}</span>` : ''}
                        ${clientesNomes.length ? `<span><i class="fa-solid fa-users"></i> ${escapeHtml(clientesNomes.join(', '))}${temMais ? ` +${emb.clientes.length - 2}` : ''}</span>` : ''}
                    </div>
                </div>
            </label>
        `;
    });

    container.innerHTML = html;
    atualizarContadorSelecao();
    atualizarFooterDisponiveis();
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
    // Atualiza o item visualmente
    const item = document.querySelector(`.disponivel-item[data-id="${id}"]`);
    if (item) {
        const cb = item.querySelector('input[type="checkbox"]');
        if (embarquesSelecionados.includes(id)) {
            item.classList.add('selecionado');
            if (cb) cb.checked = true;
        } else {
            item.classList.remove('selecionado');
            if (cb) cb.checked = false;
        }
    }
    atualizarContadorSelecao();
    atualizarFooterDisponiveis();
}

// ======================================================================
// TOGGLE DA BARRA DE FILTROS AVANÇADOS
// ======================================================================
function toggleFiltrosAvancados() {
    const painel = document.getElementById('filtros-avancados');
    const btn = document.getElementById('btn-filtros-avancados');
    if (!painel) return;
    painel.classList.toggle('aberto');
    if (btn) btn.classList.toggle('aberto');
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
    const elTotal = document.getElementById('total-selecionados-disponiveis');
    if (elTotal) elTotal.textContent = total;
    const btn = document.getElementById('btn-criar-rotas-disponiveis');
    if (btn) btn.disabled = total === 0;
}

// ======================================================================
// RECARREGAR LISTA DE DISPONÍVEIS
// ======================================================================
function atualizarDisponiveis() {
    embarquesSelecionados = [];
    const busca = document.getElementById('busca-disponiveis');
    if (busca) busca.value = '';
    const buscaClear = document.getElementById('busca-disponiveis-clear');
    if (buscaClear) buscaClear.hidden = true;
    carregarDisponiveis();
}

// ======================================================================
// ======================================================================
// FILTRO RÁPIDO (PILLS DE STATUS)
// ======================================================================
function aplicarFiltroRapido(status, btnEl) {
    const select = document.getElementById('filtro-status');
    if (select) select.value = status;

    document.querySelectorAll('.quick-filter-pill').forEach(function(pill) {
        pill.classList.remove('active');
    });
    if (btnEl) btnEl.classList.add('active');

    paginaAtual = 1;
    cacheEmbarques.dados = null;
    cacheEmbarques.timestamp = null;
    carregarEmbarques();
}

// ======================================================================
// MUDAR LIMITE POR PÁGINA
// ======================================================================
function mudarLimite() {
    const select = document.getElementById('limite-por-pagina');
    if (select) {
        limitePorPagina = parseInt(select.value);
        paginaAtual = 1;  // Voltar para página 1 ao mudar o limite
        cacheEmbarques.dados = null;
        cacheEmbarques.timestamp = null;
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
    // EVENT LISTENERS DOS FILTROS DA TABELA DE ROTAS CRIADAS
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
    // EVENT LISTENERS DA BUSCA DE DISPONÍVEIS (NOVO)
    // ================================================================
    const buscaDisp = document.getElementById('busca-disponiveis');
    const buscaClear = document.getElementById('busca-disponiveis-clear');

    if (buscaDisp) {
        buscaDisp.addEventListener('input', debounce(function() {
            if (buscaClear) buscaClear.hidden = !buscaDisp.value;
            renderizarDisponiveis(dadosEmbarquesERP, abaAtual);
        }, 300));
    }

    if (buscaClear && buscaDisp) {
        buscaClear.addEventListener('click', function() {
            buscaDisp.value = '';
            buscaClear.hidden = true;
            renderizarDisponiveis(dadosEmbarquesERP, abaAtual);
            buscaDisp.focus();
        });
    }

    // ================================================================
    // ATUALIZAR INDICADOR DE CACHE A CADA 5 SEGUNDOS
    // ================================================================
    setInterval(atualizarIndicadorCache, 5000);
});



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
        const response = await fetch(`${API_BASE}/frota/entregas/rastreamento/${encodeURIComponent(codigo)}`, {
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

let url = API_BASE + '/frota/embarques?pagina=' + paginaAtual + '&limite=' + limitePorPagina;
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
            const base = window.location.pathname.startsWith('/API/') ? '/API' : '';
            window.location.href = base + '/portal/login.php';
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
// RENDERIZAR EMBARQUES (LISTA DE ROTAS CRIADAS)
// ======================================================================
function renderizarEmbarques(embarques, pagination) {
    const tbody = document.getElementById('lista-embarques');
    if (!tbody) return;
    renderizarVisaoOperacional(embarques || []);

    if (!embarques || embarques.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-slate-400">
            <i class="fa-regular fa-truck text-3xl block mb-2"></i>
            Nenhum embarque encontrado
        </td></tr>`;
        return;
    }

    let html = '';

    embarques.forEach((emb, index) => {
        const statusIcon = {
            'planejado': '📋',
            'em_andamento': '🚚',
            'finalizado': '✅',
            'cancelado': '🚫',
            'problema': '⚠️'
        }[emb.status] || '';

        const statusClass = {
            'planejado': 'planejado',
            'em_andamento': 'em_andamento',
            'finalizado': 'finalizado',
            'cancelado': 'cancelado',
            'problema': 'problema'
        }[emb.status] || 'planejado';

        const statusText = {
            'planejado': 'Planejado',
            'em_andamento': 'Em Andamento',
            'finalizado': 'Finalizado',
            'cancelado': 'Cancelado',
            'problema': 'Problema'
        }[emb.status] || emb.status;

        const total = parseInt(emb.total_entregas) || 0;
        const concluidas = parseInt(emb.entregas_concluidas) || 0;
        const progresso = total > 0 ? Math.round((concluidas / total) * 100) : 0;

        let barClass = 'em-andamento';
        if (emb.status === 'problema') {
            barClass = 'problema';
        } else if (progresso >= 100) {
            barClass = 'concluido';
        }

        const valorTotal = parseFloat(emb.valor_total_entregas) || 0;
        const pesoTotal = parseFloat(emb.peso_total_entregas) || 0;

        const placa = emb.veiculo_placa || '';
        const modelo = emb.veiculo_modelo || '';
        const temVeiculo = placa && placa.trim() !== '' && placa !== 'SEM VEÍCULO';

        const veiculoDisplay = temVeiculo
            ? `<span class="font-medium text-[#1a3c34] dark:text-white">${placa}</span>`
            : '<span class="text-slate-400 text-xs">Não definido</span>';

        const veiculoModelo = temVeiculo && modelo
            ? `<span class="text-xs text-slate-400 block">${modelo}</span>`
            : '';

        const nomeRota = emb.nome_embarque || emb.observacoes || emb.rota || '-';

        // 🔥 DETECTAR GRUPO
        const totalAgrupados = parseInt(emb.total_embarques_agrupados) || 1;
        const isGrupo = totalAgrupados > 1;
        const qtdEmbarques = isGrupo ? ` (${totalAgrupados} embarques)` : '';

        // 🔥 IDs do ERP (para ações em lote)
        const erpIdsAgrupados = emb.erp_ids_agrupados
            ? String(emb.erp_ids_agrupados).split(',').map(n => parseInt(n.trim())).filter(n => !isNaN(n) && n > 0)
            : [];

        // 🔥 SEMPRE o ID do SISTEMA para abrir detalhes
        const idSistema = parseInt(emb.id);

        // IDs para ações em lote
        const idsString = erpIdsAgrupados.length > 0
            ? erpIdsAgrupados.join(',')
            : String(idSistema);

        // ==============================================================
        // MONTAGEM DA COLUNA DE AÇÕES
        // ==============================================================
        const btnVer = `
            <button class="btn-icone azul" onclick="verDetalhes(${idSistema})" title="Ver detalhes${isGrupo ? ' do grupo' : ''}">
                <i class="fa-solid fa-eye"></i>
            </button>
        `;

        const btnEditar = `
            <button class="btn-icone azul" onclick="abrirModalEditarGrupo([${idsString}])" title="Editar">
                <i class="fa-solid fa-pen"></i>
            </button>
        `;

        // Slot dinâmico: Iniciar (se planejado) OU Finalizar (se em_andamento/problema)
        let btnStatusAcao = '';
        if (emb.status === 'planejado') {
            btnStatusAcao = `
                <button class="btn-icone verde" onclick="iniciarGrupo([${idsString}])" title="Iniciar${isGrupo ? ' todos' : ''}">
                    <i class="fa-solid fa-play"></i>
                </button>
            `;
        } else if (emb.status === 'em_andamento' || emb.status === 'problema') {
            btnStatusAcao = `
                <button class="btn-icone amber" onclick="finalizarGrupo([${idsString}])" title="Finalizar${isGrupo ? ' todos' : ''}">
                    <i class="fa-solid fa-flag-checkered"></i>
                </button>
            `;
        }

        // Itens do menu "mais ações"
        const podeCancelar = emb.status !== 'finalizado' && emb.status !== 'cancelado';
        const temProblema = emb.status === 'problema';

        const menuItens = [];
        menuItens.push(`
            <button type="button" onclick="excluirEmbarque(${idSistema}, event)" class="acoes-menu-item danger">
                <i class="fa-solid fa-trash-alt"></i> Excluir embarque
            </button>
        `);
        if (podeCancelar) {
            menuItens.push(`
                <button type="button" onclick="cancelarGrupo([${idsString}]); fecharTodosMenusAcoes();" class="acoes-menu-item danger">
                    <i class="fa-solid fa-ban"></i> Cancelar embarque
                </button>
            `);
        }
        if (temProblema) {
            menuItens.push(`
                <button type="button" onclick="verDetalhes(${idSistema}); fecharTodosMenusAcoes();" class="acoes-menu-item amber">
                    <i class="fa-solid fa-triangle-exclamation"></i> Ver problemas
                </button>
            `);
        }

        const btnMenu = `
            <div class="acoes-menu">
                <button class="btn-icone acoes-menu-trigger" 
                        type="button"
                        onclick="toggleMenuAcoes(event, ${idSistema})" 
                        title="Mais ações"
                        aria-haspopup="true"
                        aria-expanded="false">
                    <i class="fa-solid fa-ellipsis-vertical"></i>
                </button>
                <div class="acoes-menu-dropdown" id="acoes-menu-${idSistema}" role="menu" hidden>
                    ${menuItens.join('')}
                </div>
            </div>
        `;

        html += `
            <tr class="row-status-${statusClass}">
                <td class="text-center font-bold text-slate-400" data-label="#">${index + 1}</td>
                <td data-label="Embarque">
                    <div class="font-bold text-[#1a3c34] dark:text-white">
                        ${emb.numero_embarque || '#' + emb.id}
                        ${qtdEmbarques}
                    </div>
                    <div class="text-xs text-slate-400">${emb.nome_embarque || ''}</div>
                    ${emb.erp_embarque_id ? `<span class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-1.5 py-0.5 rounded-full">ERP: #${emb.erp_embarque_id}</span>` : ''}
                    ${isGrupo ? '<span class="text-xs bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 px-1.5 py-0.5 rounded-full">📦 Grupo</span>' : ''}
                </td>
                <td data-label="Rota">
                    <span class="text-sm">${nomeRota}</span>
                </td>
                <td data-label="Veículo">
                    ${veiculoDisplay}
                    ${veiculoModelo}
                </td>
                <td data-label="Motorista">${emb.motorista_nome || '-'}</td>
                <td class="text-center" data-label="Entregas">
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-sm font-bold">${concluidas}/${total}</span>
                        <div class="progress-thin w-16">
                            <div class="bar ${barClass}" style="width: ${progresso}%"></div>
                        </div>
                    </div>
                </td>
                <td class="text-center font-medium text-emerald-600 dark:text-emerald-400" data-label="Valor">${formatarMoeda(valorTotal)}</td>
                <td class="text-center font-medium text-slate-600 dark:text-slate-300" data-label="Peso">${formatarPeso(pesoTotal)}</td>
                <td class="text-center" data-label="Status">
                    <span class="status-badge ${statusClass}">
                        ${statusIcon} ${statusText}
                    </span>
                </td>
                <td class="text-center" data-label="Ações">
                    <div class="acoes-wrapper">
                        <div class="acoes-principais">
                            ${btnVer}
                            ${btnEditar}
                            ${btnStatusAcao}
                        </div>
                        ${btnMenu}
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

// ======================================================================
// MENU DE AÇÕES "⋮" (dropdown em cada linha da tabela)
// ======================================================================

/**
 * Abre/fecha o menu "⋮" da linha.
 * Como estamos dentro de uma tabela com overflow, o dropdown é
 * posicionado com position: fixed e coordenadas calculadas no clique.
 */
function toggleMenuAcoes(event, embarqueId) {
    event.stopPropagation();

    const trigger = event.currentTarget;
    const dropdown = document.getElementById(`acoes-menu-${embarqueId}`);
    if (!dropdown) return;

    const jaAberto = !dropdown.hidden;

    // Fecha todos os outros menus abertos
    fecharTodosMenusAcoes();

    if (jaAberto) return;

    // Abre
    dropdown.hidden = false;
    dropdown.style.visibility = 'hidden';

    // Calcula posição (fixed) logo abaixo do botão, alinhado à direita
    const rect = trigger.getBoundingClientRect();
    const dropdownRect = dropdown.getBoundingClientRect();
    const dropdownWidth = dropdownRect.width || 200;

    let top = rect.bottom + 6;
    let left = rect.right - dropdownWidth;

    // Se sair pela esquerda, encosta na borda
    if (left < 8) left = 8;

    // Se não caber abaixo, abre acima
    if (top + dropdownRect.height > window.innerHeight - 8) {
        top = rect.top - dropdownRect.height - 6;
    }

    dropdown.style.position = 'fixed';
    dropdown.style.top = top + 'px';
    dropdown.style.left = left + 'px';
    dropdown.style.visibility = 'visible';
    dropdown.style.zIndex = '1050';

    trigger.setAttribute('aria-expanded', 'true');
}

function fecharTodosMenusAcoes() {
    document.querySelectorAll('.acoes-menu-dropdown').forEach(el => {
        el.hidden = true;
        el.style.visibility = '';
        el.style.top = '';
        el.style.left = '';
    });
    document.querySelectorAll('.acoes-menu-trigger').forEach(el => {
        el.setAttribute('aria-expanded', 'false');
    });
}

// Fecha ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.closest('.acoes-menu')) {
        fecharTodosMenusAcoes();
    }
});

// Fecha ao rolar ou redimensionar (evita dropdown "solto" na tela)
window.addEventListener('scroll', fecharTodosMenusAcoes, true);
window.addEventListener('resize', fecharTodosMenusAcoes);

// Fecha com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharTodosMenusAcoes();
});

// ======================================================================
// EXCLUIR EMBARQUE (apaga o registro inteiro — soft/hard no backend)
// ======================================================================
async function excluirEmbarque(embarqueId, event) {
    if (event) event.stopPropagation();
    fecharTodosMenusAcoes();

    const token = getAuthToken();
    if (!token) return;

    const confirm = await Swal.fire({
        title: 'Excluir embarque?',
        html: `
            <div style="text-align:left;">
                <p>O embarque <strong>#${embarqueId}</strong> e <strong>todas as suas entregas</strong> serão removidos.</p>
                <p style="color:#dc2626;font-weight:600;margin-top:8px;">⚠️ Esta ação não pode ser desfeita.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        mostrarSpinner('Excluindo embarque...', `Processando #${embarqueId}...`, 0);

        const resp = await fetch(`${API_BASE}/frota/embarques/${embarqueId}`, {
            method: 'DELETE',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        });

        atualizarSpinner('Excluindo embarque...', 'Aguardando resposta...', 70);

        const rawText = await resp.text();
        let data;
        try { data = JSON.parse(rawText); } catch { data = { success: false, error: rawText.substring(0, 200) }; }

        setTimeout(() => fecharSpinner(), 300);

        if (resp.ok && data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Embarque excluído',
                text: data.message || 'O embarque foi removido com sucesso.',
                timer: 2500,
                showConfirmButton: false
            });
            cacheEmbarques.dados = null;
            cacheEmbarques.timestamp = null;
            carregarEmbarques();
        } else {
            Swal.fire('Erro', data.error || `HTTP ${resp.status}`, 'error');
        }
    } catch (err) {
        fecharSpinner();
        console.error('❌ Erro ao excluir embarque:', err);
        Swal.fire('Erro', err.message || 'Falha ao excluir embarque', 'error');
    }
}



function renderizarVisaoOperacional(embarques) {
    const container = document.getElementById('embarques-overview');
    if (!container) return;
    const status = embarques.reduce((acc, embarque) => {
        const chave = embarque.status || 'planejado';
        acc[chave] = (acc[chave] || 0) + 1;
        return acc;
    }, {});
    const entregas = embarques.reduce((total, embarque) => total + Number(embarque.total_entregas || 0), 0);
    const concluidas = embarques.reduce((total, embarque) => total + Number(embarque.entregas_concluidas || 0), 0);
    const progresso = entregas ? Math.round((concluidas / entregas) * 100) : 0;
    const cards = [
        ['fa-route', embarques.length, 'rotas na página', 'neutral'],
        ['fa-truck-fast', status.em_andamento || 0, 'em andamento', 'active'],
        ['fa-circle-check', `${progresso}%`, `${concluidas}/${entregas} entregas`, 'success'],
        ['fa-triangle-exclamation', status.problema || 0, 'com problema', status.problema ? 'danger' : 'neutral']
    ];
    container.innerHTML = `
        <div class="overview-heading">
            <div><span class="overview-eyebrow"><i class="fa-solid fa-signal"></i> Painel operacional</span><strong>Visão da página atual</strong></div>
            <span class="overview-caption">${entregas} entregas monitoradas</span>
        </div>
        <div class="overview-cards">
            ${cards.map(([icon, value, label, tone]) => `<div class="overview-card ${tone}"><i class="fa-solid ${icon}"></i><div><strong>${value}</strong><span>${label}</span></div></div>`).join('')}
        </div>
        <div class="overview-progress"><div><span>Conclusão das entregas</span><strong>${progresso}%</strong></div><div class="overview-progress-track"><span style="width:${progresso}%"></span></div></div>
    `;
}

function mudarPagina(direcao) {
    if (direcao === 'anterior' && paginaAtual > 1) paginaAtual--;
    else if (direcao === 'proximo' && paginaAtual < totalPaginas) paginaAtual++;
    cacheEmbarques.dados = null;
    cacheEmbarques.timestamp = null;
    carregarEmbarques();
}

// ======================================================================
// CANCELAR GRUPO - CORRIGIDO (COM MAPEAMENTO DE IDs)
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

    const token = getAuthToken();
    if (!token) return;

    // ============================================================
    // 🔥 MAPEAR IDs DO ERP PARA IDs DO SISTEMA
    // ============================================================
    const { sistemaIds, erros } = await mapearErpIdsParaSistema(listaIds);

    if (sistemaIds.length === 0) {
        Swal.fire({
            icon: 'error',
            title: '❌ Nenhum embarque encontrado',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi encontrado no sistema.</p>
                    ${erros.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${erros.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    const result = await Swal.fire({
        title: `Cancelar ${sistemaIds.length} embarque(s)?`,
        text: `${sistemaIds.length} embarques serão cancelados. Esta ação não pode ser desfeita.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sim, cancelar todos',
        cancelButtonText: 'Voltar'
    });

    if (!result.isConfirmed) return;

    let sucessos = 0;
    let errosOperacao = 0;
    let errosDetalhes = [];

    for (const id of sistemaIds) {
        try {
            const response = await fetch(API_BASE + '/frota/embarques/' + id + '/cancelar', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                sucessos++;
            } else {
                errosOperacao++;
                errosDetalhes.push(`Embarque #${id}: ${data.error || 'Erro desconhecido'}`);
            }
        } catch (e) {
            errosOperacao++;
            errosDetalhes.push(`Embarque #${id}: ${e.message}`);
        }
    }

    if (errosOperacao === 0) {
        Swal.fire({
            icon: 'success',
            title: '✅ Todos cancelados!',
            text: `${sucessos} embarque${sucessos > 1 ? 's' : ''} cancelado${sucessos > 1 ? 's' : ''} com sucesso.`,
            timer: 3000,
            showConfirmButton: false
        });
    } else if (sucessos > 0) {
        Swal.fire({
            icon: 'warning',
            title: '⚠️ Cancelamento parcial',
            html: `
                <div style="text-align: left;">
                    <p>✅ <strong>${sucessos}</strong> embarque${sucessos > 1 ? 's' : ''} cancelado${sucessos > 1 ? 's' : ''}</p>
                    <p>❌ <strong>${errosOperacao}</strong> embarque${errosOperacao > 1 ? 's' : ''} com erro</p>
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
            title: '❌ Falha ao cancelar',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi cancelado.</p>
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
            const response = await fetch(API_BASE + '/frota/embarques/' + id, {
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
// INICIAR GRUPO - CORRIGIDO (USA ID DO SISTEMA)
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

    const token = getAuthToken();
    if (!token) return;

    // ============================================================
    // 🔥 CORREÇÃO: Buscar os IDs do sistema para cada ID do ERP
    // ============================================================
    const { sistemaIds, erros } = await mapearErpIdsParaSistema(listaIds);

    if (sistemaIds.length === 0) {
        Swal.fire({
            icon: 'error',
            title: '❌ Nenhum embarque encontrado',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi encontrado no sistema.</p>
                    ${erros.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${erros.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    const result = await Swal.fire({
        title: `Iniciar ${sistemaIds.length} embarque(s)?`,
        text: `${sistemaIds.length} embarque${sistemaIds.length > 1 ? 's' : ''} serão iniciados.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sim, iniciar todos',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    // ============================================================
    // 🔥 INICIAR OS EMBARQUES USANDO OS IDs DO SISTEMA
    // ============================================================
    mostrarSpinner(
        'Iniciando embarques...',
        `Processando ${sistemaIds.length} embarques...`,
        0
    );

    let sucessos = 0;
    let errosOperacao = 0;
    let errosDetalhes = [];

    for (let i = 0; i < sistemaIds.length; i++) {
        const id = sistemaIds[i];
        try {
            const response = await fetch(`${API_BASE}/frota/embarques/${id}/iniciar`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await response.json();
            if (data.success) {
                sucessos++;
            } else {
                errosOperacao++;
                errosDetalhes.push(`Embarque #${id}: ${data.error || 'Erro desconhecido'}`);
            }
        } catch (e) {
            errosOperacao++;
            errosDetalhes.push(`Embarque #${id}: ${e.message}`);
        }

        const progresso = Math.round(((i + 1) / sistemaIds.length) * 100);
        atualizarSpinner(
            'Iniciando embarques...',
            `${sucessos} iniciados, ${errosOperacao} falhas`,
            progresso
        );
    }

    setTimeout(() => fecharSpinner(), 300);

    if (errosOperacao === 0) {
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
                    <p>❌ <strong>${errosOperacao}</strong> embarque${errosOperacao > 1 ? 's' : ''} com erro</p>
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
// FINALIZAR GRUPO - CORRIGIDO (USA ID DO SISTEMA)
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

    // ============================================================
    // 🔥 CORREÇÃO: Buscar os IDs do sistema para cada ID do ERP
    // ============================================================
    const { sistemaIds, erros } = await mapearErpIdsParaSistema(listaIds);

    if (sistemaIds.length === 0) {
        Swal.fire({
            icon: 'error',
            title: '❌ Nenhum embarque encontrado',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi encontrado no sistema.</p>
                    ${erros.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${erros.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    try {
        let todasEntregas = [];
        for (const id of sistemaIds) {
            const resp = await fetch(`${API_BASE}/frota/embarques/${id}`, {
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
            title: `Finalizar ${sistemaIds.length} embarque(s)?`,
            html: `
                <div style="text-align: left;">
                    <p><strong>${sistemaIds.length}</strong> embarque${sistemaIds.length > 1 ? 's' : ''} serão finalizados.</p>
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

        mostrarSpinner(
            'Finalizando embarques...',
            `Processando ${sistemaIds.length} embarques...`,
            0
        );

        let sucessos = 0;
        let errosOperacao = 0;
        let errosDetalhes = [];

        for (let i = 0; i < sistemaIds.length; i++) {
            const id = sistemaIds[i];
            try {
                const response = await fetch(`${API_BASE}/frota/embarques/${id}/finalizar`, {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token }
                });

                const data = await response.json();

                if (data.success) {
                    sucessos++;
                } else {
                    errosOperacao++;
                    errosDetalhes.push(`Embarque #${id}: ${data.error || 'Erro desconhecido'}`);
                }
            } catch (e) {
                errosOperacao++;
                errosDetalhes.push(`Embarque #${id}: ${e.message}`);
            }

            const progresso = Math.round(((i + 1) / sistemaIds.length) * 100);
            atualizarSpinner(
                'Finalizando embarques...',
                `${sucessos} concluídos, ${errosOperacao} falhas`,
                progresso
            );
        }

        setTimeout(() => fecharSpinner(), 300);

        if (errosOperacao === 0) {
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
                        <p>❌ <strong>${errosOperacao}</strong> embarque${errosOperacao > 1 ? 's' : ''} com erro</p>
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
        const response = await fetch(`${API_BASE}/frota/entregas/${entregaId}/checkin`, {
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
        const resp = await fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
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
                const resp = await fetch(API_BASE + '/frota/importar/itens-pedidos', {
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
    const response = await fetch(`${API_BASE}/frota/entregas/${entregaId}/checkout`, {
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
        const response = await fetch(`${API_BASE}/frota/entregas/${entregaId}/falha`, {
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
        const response = await fetch(API_BASE + '/frota/embarques/' + id + '/iniciar', {
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
        const response = await fetch(API_BASE + '/frota/embarques/' + id + '/finalizar', {
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
        const response = await fetch(API_BASE + '/frota/embarques/' + id + '/cancelar', {
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
// [MELHORIA] FUNÇÕES PARA STATUS "ENTREGUE_COM_PROBLEMA"
// ======================================================================

/**
 * Retorna a classe CSS correta para o status "entregue_com_problema"
 * Substitui a classe padrão "entregue" quando houver problemas
 */
function getStatusClass(status) {
    const classes = {
        'entregue': 'entregue',
        'entregue_com_problema': 'entregue_com_problema',
        'pendente': 'pendente',
        'em_entrega': 'em_entrega',
        'falha': 'falha',
        'cancelada': 'cancelada'
    };
    return classes[status] || 'pendente';
}

/**
 * Retorna o ícone para o status
 */
function getStatusIcon(status) {
    const icons = {
        'entregue': '✅',
        'entregue_com_problema': '⚠️',
        'pendente': '⏳',
        'em_entrega': '🚚',
        'falha': '❌',
        'cancelada': '🚫'
    };
    return icons[status] || '📦';
}

/**
 * Retorna o label formatado para o status
 */
function getStatusLabel(status) {
    const labels = {
        'entregue': 'Entregue',
        'entregue_com_problema': 'Entregue c/ Problema',
        'pendente': 'Pendente',
        'em_entrega': 'Em Rota',
        'falha': 'Falha',
        'cancelada': 'Cancelada'
    };
    return labels[status] || status;
}

/**
 * Verifica se o status é "entregue_com_problema" e adiciona classes especiais
 */
function isEntregueComProblema(status) {
   return status === 'entregue_com_problema';
}

/**
 * Aplica classe especial ao elemento da entrega se for "entregue_com_problema"
 */
function aplicarClasseProblema(element, status) {
    if (isEntregueComProblema(status)) {
        element.classList.add('entregue-com-problema');
        const statusBadge = element.querySelector('.status-mini');
        if (statusBadge) {
            statusBadge.classList.add('entregue_com_problema');
        }
        // Adicionar ícone de problema no nome do cliente
        const clienteDiv = element.querySelector('.cliente');
        if (clienteDiv && !clienteDiv.querySelector('.problema-icon')) {
            const icon = document.createElement('span');
            icon.className = 'problema-icon';
            icon.innerHTML = '⚠️';
            icon.title = 'Entrega com problemas (faltantes/devoluções)';
            clienteDiv.appendChild(icon);
        }
    }
}


// ======================================================================
// VER DETALHES DO EMBARQUE — MODAL SWAL COM 3 ABAS + MAPLIBRE
// ======================================================================
async function verDetalhes(id) {
    embarqueIdDetalhes = id;
    const token = getAuthToken();
    if (!token) return;

    // Spinner inicial
    Swal.fire({
        title: 'Carregando embarque...',
        html: '<div style="padding:20px 0;"><i class="fa-solid fa-spinner fa-spin" style="font-size:32px;color:#10b981;"></i></div>',
        showConfirmButton: false,
        allowOutsideClick: false,
        width: '95vw',
        customClass: { popup: 'swal-embarque-modal' }
    });

    try {
        const response = await fetch(`${API_BASE}/frota/embarques/${id}`, {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const dados = await response.json();
        if (!dados.success) throw new Error(dados.error || 'Erro ao carregar embarque');

        const emb = dados.data;
        entregasAtuais = emb.entregas || [];

        // Normalizar entregas
        entregasAtuais = entregasAtuais.map((e, i) => {
            e.id = e.id || i + 1;
            e.cliente_nome = e.cliente_nome || 'Cliente';
            e.endereco = e.endereco || '';
            e.numero = e.numero || '';
            e.bairro = e.bairro || '';
            e.cidade = e.cidade || '';
            e.uf = e.uf || '';
            e.valor_total = e.valor_total || 0;
            e.peso_total = e.peso_total || 0;
            e.status = e.status || 'pendente';
            e.ordem_entrega = i + 1;
            e.pedidos_ids = e.pedidos_ids || '';
            return e;
        });

        // Estado do modal
        window.__embarqueEstado = {
            id: id,
            status: emb.status,
            isEditavel: emb.status === 'planejado',
            snapshotHistorico: [], // para "Desfazer"
            abaAtiva: 'rota',
            pollingTimer: null
        };

        // Abre o modal principal
        await abrirModalEmbarqueSwal(emb);

    } catch (error) {
        Swal.close();
        mostrarNotificacao('Erro ao carregar embarque: ' + error.message, 'error');
    }
}

// ======================================================================
// ABRIR GALERIA DE FOTOS
// Usa o mesmo container filho (#swal-filho-container) para não derrubar
// a modal do embarque quando chamada a partir dela.
// Ao fechar: reabre a modal pai se ela tiver sumido (independente de
// _pendenteRedesenho) e aplica redesenho pendente se houver.
// ======================================================================
async function abrirGaleriaFotos(entregaId) {
    const token = getAuthToken();
    try {
        const resp = await fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
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

        // ================================================================
        // USA O CONTAINER FILHO ISOLADO — impede que esta Swal feche a modal pai
        // ================================================================
        let filhoContainer = document.getElementById('swal-filho-container');
        if (!filhoContainer) {
            filhoContainer = document.createElement('div');
            filhoContainer.id = 'swal-filho-container';
            filhoContainer.style.cssText = 'position:fixed;inset:0;z-index:10001;pointer-events:none;';
            document.body.appendChild(filhoContainer);
        }
        filhoContainer.style.pointerEvents = 'auto';

        const SwalFilho = Swal.mixin({ target: filhoContainer });

        await SwalFilho.fire({
            title: '📸 Fotos da Entrega',
            html: html,
            width: '800px',
            showConfirmButton: true,
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#10b981',
            customClass: {
                popup: 'galeria-fotos-modal'
            },
            allowOutsideClick: false
        });

        // ================================================================
        // PÓS-FECHAMENTO
        // ================================================================

        // 1) Libera o container filho
        filhoContainer.style.pointerEvents = 'none';

        // 2) Decide o que fazer ao fechar:
        //    - Se a modal pai sumiu → reabre (independente de _pendenteRedesenho)
        //    - Se a pai continua aberta e há redesenho pendente → aplica
        const embBackup = window.__embarqueBackup;
        const paiSumiu = embBackup && !document.querySelector('.swal-embarque-modal-fullscreen');

        if (paiSumiu) {
            // Limpa flag de redesenho pendente (vai ser recriada ao reabrir)
            if (window.__embarqueEstado) {
                window.__embarqueEstado._pendenteRedesenho = false;
            }
            setTimeout(() => {
                window.__embarqueReabrindo = true;
                abrirModalEmbarqueSwal(embBackup);
            }, 50);
        } else if (window.__embarqueEstado?._pendenteRedesenho && embBackup) {
            window.__embarqueEstado._pendenteRedesenho = false;
            setTimeout(() => redesenharModalEmbarqueCompleto(embBackup, []), 100);
        }

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
// MAPA — MAPLIBRE GL (OpenFreeMap)
// ======================================================================
let embarqueMapa = null;
let embarqueMapMarkers = [];
let embarqueMapRotaSource = 'rota-embarque';

function inicializarMapaEmbarque() {
    const container = document.getElementById('embarque-mapa');
    if (!container) return;

    if (embarqueMapa) {
        embarqueMapa.remove();
        embarqueMapa = null;
    }
    embarqueMapMarkers = [];
// Silencia erro conhecido do MapLibre 4.x com tiles do OpenFreeMap
// (bug: "Expected value to be of type number, but found null instead")
if (!window.__mapErrorFilterInstalled) {
    window.__mapErrorFilterInstalled = true;
    const _origError = console.error;
    console.error = function(...args) {
        const first = args[0];
        if (first && typeof first === 'string' &&
            (first.includes('Expected value to be of type number') ||
             first.includes('but found null instead'))) {
            return; // ignora ruído do MapLibre
        }
        _origError.apply(console, args);
    };
    // Também captura erros assíncronos do worker do MapLibre
    window.addEventListener('error', (e) => {
        if (e.message && e.message.includes('Expected value to be of type number')) {
            e.preventDefault();
            return true;
        }
    });
}
    embarqueMapa = new maplibregl.Map({
        container: 'embarque-mapa',
         style: 'https://tiles.openfreemap.org/styles/bright', 
        center: [DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT],
        zoom: 10,
        attributionControl: true
    });

    embarqueMapa.addControl(new maplibregl.NavigationControl(), 'top-right');
    embarqueMapa.addControl(new maplibregl.ScaleControl(), 'bottom-left');

    embarqueMapa.on('load', () => {
        // Source + Layer para a rota
        if (!embarqueMapa.getSource(embarqueMapRotaSource)) {
            embarqueMapa.addSource(embarqueMapRotaSource, {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] }
            });

            // Linha externa (sombra)
            embarqueMapa.addLayer({
                id: 'rota-embarque-outline',
                type: 'line',
                source: embarqueMapRotaSource,
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#0b1f18',
                    'line-width': 7,
                    'line-opacity': 0.35
                }
            });

            // Linha principal
            embarqueMapa.addLayer({
                id: 'rota-embarque-line',
                type: 'line',
                source: embarqueMapRotaSource,
                layout: { 'line-cap': 'round', 'line-join': 'round' },
                paint: {
                    'line-color': '#10b981',
                    'line-width': 4,
                    'line-opacity': 0.95,
                    'line-dasharray': [2, 1.5]
                }
            });
        }
        desenharMapaEmbarque();
    });
}

// ----------------------------------------------------------------------
// Desenha marcadores + linha (sempre a partir de entregasAtuais)
// ----------------------------------------------------------------------
function desenharMapaEmbarque() {
    if (!embarqueMapa) return;

    // Limpa marcadores antigos
    embarqueMapMarkers.forEach(m => m.remove());
    embarqueMapMarkers = [];

    // Monta pontos na ordem atual (Nutricional primeiro)
    const pontos = [[DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT]];
    const bounds = new maplibregl.LngLatBounds();
    bounds.extend([DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT]);

    // Marcador da Nutricional
    const elNutri = document.createElement('div');
    elNutri.className = 'embarque-marker embarque-marker-origem';
    elNutri.innerHTML = '<i class="fa-solid fa-warehouse"></i>';
    const markerNutri = new maplibregl.Marker({ element: elNutri })
        .setLngLat([DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT])
        .setPopup(new maplibregl.Popup({ offset: 24 }).setHTML(
            `<div class="popup-embarque"><strong>🏢 Nutricional</strong><br><small>${DISTRIBUIDORA_ENDERECO}</small></div>`
        ))
        .addTo(embarqueMapa);
    embarqueMapMarkers.push(markerNutri);

    // Marcadores de cada entrega na ordem
    entregasAtuais.forEach((entrega, index) => {
        if (!entrega.latitude || !entrega.longitude) return;

        const lngLat = [parseFloat(entrega.longitude), parseFloat(entrega.latitude)];
        pontos.push(lngLat);
        bounds.extend(lngLat);

        const statusCls = (entrega.status || 'pendente').replace(/_/g, '-');
        const isProxima = index === 0 && (entrega.status === 'pendente' || entrega.status === 'em_entrega');

        const el = document.createElement('div');
        el.className = `embarque-marker embarque-marker-${statusCls}${isProxima ? ' embarque-marker-proxima' : ''}`;
        el.innerHTML = `<span class="embarque-marker-num">${index + 1}</span>`;

        const popupHtml = montarPopupEntrega(entrega, index);

        const marker = new maplibregl.Marker({ element: el, anchor: 'center' })
            .setLngLat(lngLat)
            .setPopup(new maplibregl.Popup({ offset: 22, maxWidth: '320px' }).setHTML(popupHtml))
            .addTo(embarqueMapa);

        el.addEventListener('click', () => {
            destacarEntregaNaLista(entrega.id);
        });

        embarqueMapMarkers.push(marker);
    });

    // Atualiza a linha (rota)
    atualizarLinhaRotaEmbarque(pontos);

    // Ajusta o zoom
    if (pontos.length > 1) {
        embarqueMapa.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 500 });
    }
}

function montarPopupEntrega(entrega, index) {
    const statusLabel = getStatusLabel(entrega.status);
    const statusIcon = getStatusIcon(entrega.status);
    const valor = formatarMoeda(entrega.valor_total || 0);
    const peso = formatarPeso(entrega.peso_total || 0);

    // 🔥 Distância acumulada até esta parada
    const distancias = calcularDistanciasAcumuladas();
    const infoDist = distancias.get(entrega.id);
    const kmLinha = (infoDist && infoDist.acumulada > 0)
        ? `<div class="popup-linha">
              <i class="fa-solid fa-route"></i>
              <span><strong>${infoDist.acumulada.toFixed(1)} km</strong> desde a distribuidora${
                  infoDist.trecho !== null ? ` · <small style="opacity:.8;">+${infoDist.trecho.toFixed(1)} km da parada anterior</small>` : ''
              }</span>
           </div>`
        : '';

    const checkin = entrega.horario_checkin ? `<div class="popup-linha"><i class="fa-solid fa-right-to-bracket"></i> Check-in: ${formatarDataHora(entrega.horario_checkin)}</div>` : '';
    const checkout = entrega.horario_entrega ? `<div class="popup-linha"><i class="fa-solid fa-check-double"></i> Entrega: ${formatarDataHora(entrega.horario_entrega)}</div>` : '';
    const recebedor = entrega.nome_recebedor ? `<div class="popup-linha"><i class="fa-solid fa-user"></i> ${entrega.nome_recebedor}</div>` : '';

    return `
        <div class="popup-embarque">
            <div class="popup-header">
                <span class="popup-num">${index + 1}</span>
                <strong>${entrega.cliente_nome || 'Cliente'}</strong>
            </div>
            <div class="popup-status status-${(entrega.status || 'pendente').replace(/_/g, '-')}">
                ${statusIcon} ${statusLabel}
            </div>
            <div class="popup-body">
                ${kmLinha}
                <div class="popup-linha"><i class="fa-solid fa-location-dot"></i> ${entrega.endereco || ''}${entrega.numero ? ', ' + entrega.numero : ''}</div>
                <div class="popup-linha"><i class="fa-solid fa-city"></i> ${entrega.cidade || ''}${entrega.uf ? '/' + entrega.uf : ''}</div>
                ${recebedor}
                ${checkin}
                ${checkout}
                <div class="popup-linha"><i class="fa-solid fa-sack-dollar"></i> ${valor} · <i class="fa-solid fa-weight-hanging"></i> ${peso}</div>
            </div>
            <div class="popup-actions">
                ${entrega.latitude && entrega.longitude ? `
                    <a href="https://www.google.com/maps/dir/?api=1&destination=${entrega.latitude},${entrega.longitude}" target="_blank" class="popup-btn">
                        <i class="fa-solid fa-diamond-turn-right"></i> Navegar
                    </a>
                ` : ''}
                <button type="button" class="popup-btn" onclick="destacarEntregaNaLista(${entrega.id})">
                    <i class="fa-solid fa-list"></i> Ver na lista
                </button>
            </div>
        </div>
    `;
}

// ----------------------------------------------------------------------
// Atualiza a linha da rota no mapa (com animação suave)
// ----------------------------------------------------------------------
function atualizarLinhaRotaEmbarque(pontosLngLat) {
    if (!embarqueMapa) return;
    const source = embarqueMapa.getSource(embarqueMapRotaSource);
    if (!source) return;

    if (pontosLngLat.length < 2) {
        source.setData({ type: 'FeatureCollection', features: [] });
        return;
    }

    source.setData({
        type: 'FeatureCollection',
        features: [{
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: pontosLngLat },
            properties: {}
        }]
    });
}

// ----------------------------------------------------------------------
// Recalcula tudo (distância, tempo, linha, contadores) após reordenar
// ----------------------------------------------------------------------
function recalcularRotaEmbarque() {
    // Distância total
    let distancia = 0;
    let anterior = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };
    const pontosLngLat = [[DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT]];

    entregasAtuais.forEach(e => {
        if (!e.latitude || !e.longitude) return;
        const lat = parseFloat(e.latitude);
        const lng = parseFloat(e.longitude);
        const d = calcularDistancia(anterior.lat, anterior.lng, lat, lng);
        if (d !== null) distancia += d;
        anterior = { lat, lng };
        pontosLngLat.push([lng, lat]);
    });

    // Atualiza a linha no mapa (com animação)
    atualizarLinhaRotaEmbarque(pontosLngLat);

    // Atualiza os textos no DOM
    const elDist = document.querySelector('.swal-embarque-distancia');
    const elTempo = document.querySelector('.swal-embarque-tempo');
    if (elDist) elDist.textContent = distancia.toFixed(1) + ' km';
    if (elTempo) elTempo.textContent = Math.round(distancia / 40 * 60) + ' min';

    // Renumera os itens da lista
    document.querySelectorAll('.swal-entrega-item').forEach((el, i) => {
        const num = el.querySelector('.swal-entrega-num');
        if (num) num.textContent = i + 1;
        el.dataset.ordem = i + 1;
    });

    // Persiste a nova ordem (debounce manual)
    salvarOrdemEmbarque();
}

// ----------------------------------------------------------------------
// Destaque visual da entrega na lista quando clica no marcador
// ----------------------------------------------------------------------
function destacarEntregaNaLista(entregaId) {
    document.querySelectorAll('.swal-entrega-item').forEach(el => {
        el.classList.remove('is-destacado');
    });
    const el = document.querySelector(`.swal-entrega-item[data-id="${entregaId}"]`);
    if (el) {
        el.classList.add('is-destacado');
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => el.classList.remove('is-destacado'), 2000);
    }
}

// ----------------------------------------------------------------------
// Salva ordem no backend (com debounce)
// ----------------------------------------------------------------------
let __salvarOrdemTimer = null;
function salvarOrdemEmbarque() {
    if (__salvarOrdemTimer) clearTimeout(__salvarOrdemTimer);
    __salvarOrdemTimer = setTimeout(async () => {
        const token = getAuthToken();
        if (!token) return;
        const ordem = entregasAtuais.map(e => e.id);
        try {
            await fetch(`${API_BASE}/frota/embarques/${embarqueIdDetalhes}/reordenar`, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ ordem })
            });
        } catch (e) {
            console.warn('Erro ao salvar ordem:', e);
        }
    }, 500);
}

// ======================================================================
// SORTABLE — só ativa quando o embarque está planejado
// ======================================================================
let __sortableInstance = null;

function initSortableEmbarque() {
    const container = document.getElementById('swal-lista-entregas');
    if (!container) return;

    if (__sortableInstance) {
        __sortableInstance.destroy();
        __sortableInstance = null;
    }

    const editavel = window.__embarqueEstado?.isEditavel;
    if (!editavel) return;

    __sortableInstance = new Sortable(container, {
        animation: 150,
        handle: '.swal-entrega-handle',
        ghostClass: 'is-arrastando',
        onEnd: function() {
            // Reconstrói entregasAtuais na nova ordem
            const novaOrdemIds = Array.from(container.querySelectorAll('.swal-entrega-item'))
                .map(el => parseInt(el.dataset.id));

            const reordenado = [];
            novaOrdemIds.forEach(id => {
                const e = entregasAtuais.find(x => x.id === id);
                if (e) reordenado.push(e);
            });
            entregasAtuais = reordenado;

            // Registra no histórico local (para "Desfazer")
            window.__embarqueEstado.snapshotHistorico.push(novaOrdemIds);

            // Recalcula distância + redesenha mapa
            recalcularRotaEmbarque();

            // 🔥 Se o polling deixou um redesenho pendente, executa agora
            if (window.__embarqueEstado?._pendenteRedesenho) {
                window.__embarqueEstado._pendenteRedesenho = false;
                const embBackup = window.__embarqueBackup;
                if (embBackup) {
                    setTimeout(() => redesenharModalEmbarqueCompleto(embBackup, []), 100);
                }
            }
        }
    });
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
                            const response = await fetch(API_BASE + '/frota/embarques/' + id + '/otimizar-rota', {
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
                                const resp = await fetch(`${API_BASE}/frota/embarques/${embarqueId}`, {
                                    headers: { 'Authorization': 'Bearer ' + token }
                                });
                                const data = await resp.json();
                                if (data.success && data.data.entregas) {
                                    entrega = data.data.entregas.find(e => e.id === entregaId);
                                }
                            }

                            if (!entrega) {
                                const resp = await fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
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
// VER DETALHES DE UMA ENTREGA (aberto pelo menu ⋮ do item)
// Abre a modal filha SEM fechar a modal pai (embarque).
// O polling detecta o container filho ativo e pausa o redesenho.
// Se a entrega tiver fotos, oferece "Ver fotos" como botão cancelar.
// ======================================================================
async function verDetalhesEntrega(entregaId) {
    const token = getAuthToken();
    if (!token) return;

    // Fecha o menu antes de abrir o modal
    if (typeof fecharMenusEntrega === 'function') fecharMenusEntrega();

    // Guarda a referência do embarque atual ANTES de qualquer coisa
    const embBackup = window.__embarqueBackup;

    // Tenta usar o objeto que já está em memória (mais rápido)
    let entrega = (entregasAtuais || []).find(e => e.id === entregaId);

    if (!entrega) {
        try {
            const resp = await fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const data = await resp.json();
            if (data.success && data.data) {
                entrega = data.data;
            }
        } catch (e) {
            mostrarNotificacao('Erro ao carregar entrega', 'error');
            return;
        }
    }

    if (!entrega) {
        mostrarNotificacao('Entrega não encontrada', 'error');
        return;
    }

    const statusIcon  = getStatusIcon(entrega.status);
    const statusLabel = getStatusLabel(entrega.status);
    const statusCls   = (entrega.status || 'pendente').replace(/_/g, '-');

    const temFotos = entrega.foto_romaneio_url ||
        (entrega.checklist && entrega.checklist.some(i => i.foto_url));

    const checklistHtml = (entrega.checklist && entrega.checklist.length > 0)
        ? `
            <div style="margin-top:14px;">
                <p style="font-weight:700;font-size:0.85rem;margin-bottom:8px;">
                    📋 Itens do Checklist (${entrega.checklist.length})
                </p>
                <div style="max-height:220px;overflow-y:auto;border:1px solid var(--nutri-border);border-radius:8px;">
                    ${entrega.checklist.map(item => {
                        const st = item.status || 'entregue';
                        const cor = st === 'entregue' ? '#10b981'
                                  : st === 'faltante' ? '#f59e0b'
                                  : '#dc2626';
                        const label = st === 'entregue' ? '✅ Entregue'
                                    : st === 'faltante' ? '⚠️ Faltante'
                                    : '🔄 Devolvido';
                        return `
                            <div style="display:flex;justify-content:space-between;gap:8px;padding:8px 12px;border-bottom:1px solid var(--nutri-border);font-size:0.8rem;">
                                <span style="flex:1;">
                                    <strong>${escapeHtml(item.referencia || '—')}</strong><br>
                                    <small style="color:var(--nutri-text-secondary);">${escapeHtml(item.descricao || '')}</small>
                                </span>
                                <span style="text-align:right;white-space:nowrap;">
                                    <span style="color:${cor};font-weight:700;">${label}</span><br>
                                    <small>${item.quantidade_entregue || 0} / ${item.quantidade_prevista || 0}</small>
                                </span>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `
        : '';

    // ================================================================
    // ABRE A MODAL FILHA — usando container isolado
    // ================================================================
    let filhoContainer = document.getElementById('swal-filho-container');
    if (!filhoContainer) {
        filhoContainer = document.createElement('div');
        filhoContainer.id = 'swal-filho-container';
        filhoContainer.style.cssText = 'position:fixed;inset:0;z-index:10001;pointer-events:none;';
        document.body.appendChild(filhoContainer);
    }
    filhoContainer.style.pointerEvents = 'auto';

    const SwalFilho = Swal.mixin({ target: filhoContainer });

    const result = await SwalFilho.fire({
        title: '📦 Detalhes da Entrega',
        html: `
            <div style="text-align:left;font-family:'Inter',sans-serif;">
                <div style="background:linear-gradient(135deg,#0d241c,#1f5445);color:#fff;border-radius:12px;padding:16px 18px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:1.05rem;font-weight:800;">${escapeHtml(entrega.cliente_nome || 'Cliente')}</div>
                            <div style="font-size:0.8rem;opacity:0.9;margin-top:2px;">
                                <i class="fa-solid fa-location-dot"></i>
                                ${escapeHtml(entrega.endereco || '')}${entrega.numero ? ', ' + escapeHtml(entrega.numero) : ''}
                                ${entrega.cidade ? ' — ' + escapeHtml(entrega.cidade) : ''}${entrega.uf ? '/' + escapeHtml(entrega.uf) : ''}
                            </div>
                        </div>
                        <span class="swal-entrega-status status-${statusCls}" style="flex-shrink:0;">
                            ${statusIcon} ${statusLabel}
                        </span>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-top:14px;">
                    <div style="border:1px solid var(--nutri-border);border-radius:10px;padding:10px 12px;">
                        <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;color:var(--nutri-text-secondary);">Valor</div>
                        <div style="font-size:0.95rem;font-weight:700;">${formatarMoeda(entrega.valor_total || entrega.valor || 0)}</div>
                    </div>
                    <div style="border:1px solid var(--nutri-border);border-radius:10px;padding:10px 12px;">
                        <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;color:var(--nutri-text-secondary);">Peso</div>
                        <div style="font-size:0.95rem;font-weight:700;">${formatarPeso(entrega.peso_total || 0)}</div>
                    </div>
                    ${entrega.codigo_rastreamento ? `
                        <div style="border:1px solid var(--nutri-border);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;color:var(--nutri-text-secondary);">Rastreio</div>
                            <div style="font-size:0.8rem;font-weight:700;font-family:monospace;">${escapeHtml(entrega.codigo_rastreamento)}</div>
                        </div>
                    ` : ''}
                    ${entrega.pedidos_ids ? `
                        <div style="border:1px solid var(--nutri-border);border-radius:10px;padding:10px 12px;">
                            <div style="font-size:0.65rem;font-weight:800;text-transform:uppercase;color:var(--nutri-text-secondary);">Pedidos</div>
                            <div style="font-size:0.8rem;font-weight:700;">${escapeHtml(entrega.pedidos_ids)}</div>
                        </div>
                    ` : ''}
                </div>

                ${entrega.nome_recebedor ? `
                    <div style="margin-top:12px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;font-size:0.85rem;">
                        <i class="fa-solid fa-user" style="color:#10b981;"></i>
                        <strong>Recebedor:</strong> ${escapeHtml(entrega.nome_recebedor)}
                    </div>
                ` : ''}

                ${entrega.horario_checkin ? `
                    <div style="margin-top:8px;font-size:0.8rem;color:var(--nutri-text-secondary);">
                        <i class="fa-solid fa-right-to-bracket"></i> Check-in: ${formatarDataHora(entrega.horario_checkin)}
                    </div>
                ` : ''}
                ${entrega.horario_entrega ? `
                    <div style="margin-top:4px;font-size:0.8rem;color:var(--nutri-text-secondary);">
                        <i class="fa-solid fa-check-double"></i> Entrega: ${formatarDataHora(entrega.horario_entrega)}
                    </div>
                ` : ''}

                ${checklistHtml}
            </div>
        `,
        width: '640px',
        showCancelButton: temFotos,
        confirmButtonText: 'Fechar',
        cancelButtonText: '<i class="fa-solid fa-images"></i> Ver fotos',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#3b82f6',
        customClass: { popup: 'swal-entrega-detalhes' },
        showCloseButton: true,
        allowOutsideClick: false,
        allowEscapeKey: true
    });

    // ================================================================
    // TRATAMENTO PÓS-FECHAMENTO
    // ================================================================

    // 1) Libera o container filho (o polling detecta pelo pointer-events)
    filhoContainer.style.pointerEvents = 'none';

    // 2) Aplica redesenho pendente do polling (se houve)
    if (window.__embarqueEstado?._pendenteRedesenho) {
        window.__embarqueEstado._pendenteRedesenho = false;
        const backupAtual = window.__embarqueBackup || embBackup;
        if (backupAtual && document.querySelector('.swal-embarque-modal-fullscreen')) {
            setTimeout(() => redesenharModalEmbarqueCompleto(backupAtual, []), 100);
        }
    }

    // 3) Se o usuário clicou em "Ver fotos", abre a galeria (que usa o
    //    mesmo container filho isolado) e NÃO reabre a pai aqui. A galeria
    //    se encarrega de reabrir a pai ao fechar.
    if (result.dismiss === Swal.DismissReason.cancel && temFotos) {
        abrirGaleriaFotos(entregaId);
        return;
    }

    // 4) Se a modal pai foi derrubada pelo Swal, reabre
    if (embBackup && !document.querySelector('.swal-embarque-modal-fullscreen')) {
        setTimeout(() => {
            window.__embarqueReabrindo = true;
            abrirModalEmbarqueSwal(embBackup);
        }, 50);
    }
}
// ======================================================================
// CRIAR ROTAS SELECIONADAS - CORRIGIDO
// ======================================================================
async function criarRotasSelecionadas() {
    if (embarquesSelecionados.length === 0) {
        Swal.fire('Atenção', 'Selecione pelo menos um embarque', 'warning');
        return;
    }

    const token = getAuthToken();
    if (!token) {
        const base = window.location.pathname.startsWith('/API/') ? '/API' : '';
        window.location.href = base + '/portal/login.php';
        return;
    }

    // 🔥 CORREÇÃO: capturar um snapshot imutável dos IDs selecionados no início.
    // Isso evita que a lista global `embarquesSelecionados` seja esvaziada ou
    // alterada (por exemplo, ao trocar de aba ou recarregar a lista) enquanto
    // esta função assíncrona ainda está buscando dados do ERP, causando o erro
    // "Informe pelo menos um ID de embarque" ao montar o payload final.
    const idsSelecionados = [...embarquesSelecionados];

    const totalSelecionados = idsSelecionados.length;
    const isMultiplo = totalSelecionados > 1;

    console.log('📌 Embarques selecionados:', idsSelecionados);
    console.log('📌 É múltiplo?', isMultiplo);

    let motoristasERP = [];
    let veiculosERP = [];
    let dadosEmbarques = [];

    try {
        Swal.fire({
            title: 'Buscando dados do ERP...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        for (const id of idsSelecionados) {
            const response = await fetch(`${API_BASE}/frota/importar/embarque-detalhes/${id}`, {
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
            fetch(API_BASE + '/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch(API_BASE + '/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
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
                fetch(API_BASE + '/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
                fetch(API_BASE + '/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
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
                if (e) { m.existe = true; m.id_sistema = e.id; }
            });
            veiculosERP.forEach(v => {
                const e = veiculosSistema.find(vs => vs.placa.toUpperCase() === v.placa.toUpperCase());
                if (e) { v.existe = true; v.id_sistema = e.id; v.modelo = e.modelo || ''; }
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

    // 🔥 CORREÇÃO: Construir o payload corretamente
    const payload = {
        veiculo_id: veiculoId,
        motorista_id: motoristaId,
        data_saida: dataSaida,
        usuario_id: getUserId(),
        nome_embarque: nomeEmbarque
    };

    // 🔥 CORREÇÃO: Usar o campo correto para a API
    if (isMultiplo) {
        // Para múltiplos embarques, usar ids_agrupados
        payload.ids_agrupados = idsSelecionados;
        // 🔥 IMPORTANTE: NÃO enviar id_embarque_erp quando for múltiplo
    } else {
        // Para um único embarque, usar id_embarque_erp
        payload.id_embarque_erp = idsSelecionados[0];
    }

    console.log('📤 Payload enviado:', payload);

    // 🔥 SPINNER MELHORADO
    mostrarSpinner(
        isMultiplo ? 'Criando grupo de embarques...' : 'Criando rota...',
        isMultiplo ? `Consolidando ${totalSelecionados} embarques...` : 'Processando...',
        0
    );

    try {
        const response = await fetch(API_BASE + '/frota/importar/criar-embarque', {
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
            embarquesSelecionados = [];
            carregarDisponiveis();
            carregarEmbarques();

        } else {
            Swal.fire('Erro', dados.error || 'Falha ao criar rota', 'error');
        }
    } catch (error) {
        fecharSpinner();
        console.error('❌ Erro:', error);
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
                                            const r = await fetch(API_BASE + '/frota/motoristas', {
                                                method: 'POST',
                                                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                                                body: JSON.stringify(m)
                                            });
                                            resultados.push(await r.json());
                                        }
                                        for (const v of veiculosParaCadastrar) {
                                            const r = await fetch(API_BASE + '/frota/veiculos', {
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
// EDITAR GRUPO - CORRIGIDO (COM MAPEAMENTO DE IDs)
// Layout refinado: cabeçalho limpo, formulário vertical,
// lista compacta (1 linha por entrega) e rodapé fixo.
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

    // ============================================================
    // 🔥 MAPEAR IDs DO ERP PARA IDs DO SISTEMA
    // ============================================================
    const { sistemaIds, erros } = await mapearErpIdsParaSistema(listaIds);

    if (sistemaIds.length === 0) {
        Swal.fire({
            icon: 'error',
            title: '❌ Nenhum embarque encontrado',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi encontrado no sistema.</p>
                    ${erros.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${erros.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    try {
        const primeiroId = sistemaIds[0];
        const resp = await fetch(`${API_BASE}/frota/embarques/${primeiroId}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        if (!data.success) {
            Swal.fire('Erro', data.error || 'Falha ao carregar dados', 'error');
            return;
        }
        const emb = data.data;

        const [respVeiculos, respMotoristas] = await Promise.all([
            fetch(API_BASE + '/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch(API_BASE + '/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
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

        // Carrega todas as entregas do grupo
        let todasEntregas = [];
        for (const id of sistemaIds) {
            const respEntrega = await fetch(`${API_BASE}/frota/embarques/${id}`, {
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

        // ============================================================
        // LISTA DE ENTREGAS — layout compacto (1 linha por item)
        // ============================================================
        const entregasHtml = todasEntregas.length > 0 ? todasEntregas.map(e => {
            const statusLabel = e.status || 'pendente';
            const statusClass = {
                'entregue': 'entregue',
                'falha': 'falha',
                'em_entrega': 'em_entrega'
            }[statusLabel] || 'pendente';

            const temFotos = e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url));
            const fotosBadge = temFotos ? '<span class="fotos-badge"><i class="fa-regular fa-images"></i> Fotos</span>' : '';

            const temCheckout = statusLabel === 'entregue' || statusLabel === 'entregue_com_problema' || statusLabel === 'falha';
            const checkoutBadge = temCheckout ? '<span class="checkout-badge"><i class="fa-solid fa-check-double"></i> Checkout</span>' : '';

            return `
                <div class="entrega-edit-item" data-entrega-id="${e.id}" data-embarque-id="${e.embarque_id}">
                    <div class="entrega-edit-info">
                        <div class="cliente">
                            ${e.cliente_nome || 'Cliente'}
                            ${fotosBadge}
                            ${checkoutBadge}
                        </div>
                        <div class="detalhes">
                            <span><i class="fa-solid fa-sack-dollar"></i> ${formatarMoeda(e.valor || 0)}</span>
                            <span><i class="fa-solid fa-weight-hanging"></i> ${formatarPeso(e.peso_total || 0)}</span>
                            <span class="status ${statusClass}"><i class="fa-solid fa-circle"></i> ${statusLabel}</span>
                            <span class="embarque-ref"><i class="fa-solid fa-box"></i> Embarque #${e.embarque_id}</span>
                        </div>
                    </div>
                    <button class="btn-remover-entrega-edit" data-entrega-id="${e.id}" data-embarque-id="${e.embarque_id}" title="Remover esta entrega">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            `;
        }).join('') : '<div style="padding:24px;text-align:center;color:var(--nutri-text-secondary);font-size:0.85rem;">Nenhuma entrega</div>';

        // ============================================================
        // HTML DO MODAL — layout refinado
        // ============================================================
        const modalHtml = `
            <div class="edit-modal-container">
                <div class="edit-modal-header">
                    <h3>
                        <i class="fa-solid fa-pen-to-square"></i>
                        Editar Grupo
                        <span style="font-weight:600;font-size:0.8rem;opacity:0.7;">
                            (${sistemaIds.length} embarque${sistemaIds.length > 1 ? 's' : ''})
                        </span>
                    </h3>
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
                            <label>Data / Hora Saída</label>
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
                            <h5>
                                <i class="fa-solid fa-list"></i>
                                Entregas do Grupo (${todasEntregas.length})
                            </h5>
                            <div class="entregas-header-actions">
                                <button class="btn-add-embarque" id="btn-adicionar-embarque-erp">
                                    <i class="fa-solid fa-plus"></i> Adicionar Embarque ERP
                                </button>
                                <button class="btn-add-pedidos" id="btn-adicionar-pedidos">
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
                    <button class="btn-secondary" id="btn-cancelar-editar">
                        <i class="fa-solid fa-xmark"></i> Cancelar
                    </button>
                    <button class="btn-primary" id="btn-salvar-editar">
                        <i class="fa-solid fa-floppy-disk"></i> Salvar
                    </button>
                </div>
            </div>
        `;

        // ============================================================
        // ABRE O MODAL
        // ============================================================
        await Swal.fire({
            html: modalHtml,
            width: '720px',
            heightAuto: false,
            padding: 0,
            showConfirmButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            customClass: {
                popup: 'edit-modal-popup'
            },
            didOpen: () => {
                // ----------------------------------------------------
                // Botão CANCELAR
                // ----------------------------------------------------
                document.getElementById('btn-cancelar-editar').addEventListener('click', () => {
                    Swal.close();
                });

                // ----------------------------------------------------
                // Botão SALVAR
                // ----------------------------------------------------
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

                    const payload = {
                        nome_embarque: nome,
                        veiculo_id: veiculo,
                        motorista_id: motorista,
                        data_saida: data,
                        status
                    };
                    let sucessos = 0;

                    for (const id of sistemaIds) {
                        const respUpdate = await fetch(`${API_BASE}/frota/embarques/${id}`, {
                            method: 'PUT',
                            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const resultUpdate = await respUpdate.json();
                        if (resultUpdate.success) sucessos++;
                    }

                    if (sucessos === sistemaIds.length) {
                        Swal.fire('Sucesso', `Grupo atualizado (${sucessos} embarques)`, 'success');
                        carregarEmbarques();
                        Swal.close();
                    } else {
                        Swal.fire('Aviso', `Atualizados ${sucessos} de ${sistemaIds.length} embarques`, 'warning');
                        carregarEmbarques();
                    }
                });

                // ----------------------------------------------------
                // Botões REMOVER ENTREGA
                // ----------------------------------------------------
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
                            const respDel = await fetch(`${API_BASE}/frota/embarques/${embId}/entregas/${entregaId}`, {
                                method: 'DELETE',
                                headers: { 'Authorization': 'Bearer ' + token }
                            });
                            const resultDel = await respDel.json();
                            if (resultDel.success) {
                                Swal.fire('Removido', 'Entrega removida com sucesso', 'success');
                                Swal.close();
                                abrirModalEditarGrupo(sistemaIds);
                                carregarEmbarques();
                            } else {
                                Swal.fire('Erro', resultDel.error || 'Falha ao remover', 'error');
                            }
                        } catch (error) {
                            Swal.fire('Erro', 'Falha ao remover entrega', 'error');
                        }
                    });
                });

                // ----------------------------------------------------
                // Botão ADICIONAR EMBARQUE ERP
                // ----------------------------------------------------
                document.getElementById('btn-adicionar-embarque-erp')?.addEventListener('click', async () => {
                    try {
                        Swal.fire({
                            title: 'Carregando embarques ERP...',
                            didOpen: () => Swal.showLoading(),
                            allowOutsideClick: false
                        });

                        const respErp = await fetch(API_BASE + '/frota/importar/embarques-erp', {
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

                        const respAdd = await fetch(`${API_BASE}/frota/embarques/${sistemaIds[0]}/adicionar-embarque-erp`, {
                            method: 'POST',
                            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                            body: JSON.stringify({ erp_embarque_id: erpId })
                        });
                        const resultAdd = await respAdd.json();

                        if (resultAdd.success) {
                            Swal.fire('Sucesso', resultAdd.message, 'success');
                            Swal.close();
                            await verDetalhes(sistemaIds[0]);
                            carregarEmbarques();
                        } else {
                            Swal.fire('Erro', resultAdd.error || 'Falha ao adicionar', 'error');
                        }
                    } catch (error) {
                        Swal.fire('Erro', 'Falha ao adicionar embarque', 'error');
                    }
                });

                // ----------------------------------------------------
                // Botão ADICIONAR PEDIDOS
                // ----------------------------------------------------
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
                                    const resp = await fetch(`${API_BASE}/frota/importar/buscar-pedidos?q=${encodeURIComponent(termo)}`, {
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
                            const respAdd = await fetch(`${API_BASE}/frota/embarques/${sistemaIds[0]}/adicionar-pedidos`, {
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
                                    abrirModalEditarGrupo(sistemaIds);
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
// REMOVER ENTREGA DE UM GRUPO - CORRIGIDO (COM MAPEAMENTO)
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

    // ============================================================
    // 🔥 MAPEAR IDs DO ERP PARA IDs DO SISTEMA
    // ============================================================
    const { sistemaIds, erros } = await mapearErpIdsParaSistema(listaIds);

    if (sistemaIds.length === 0) {
        Swal.fire({
            icon: 'error',
            title: '❌ Nenhum embarque encontrado',
            html: `
                <div style="text-align: left;">
                    <p>Nenhum embarque foi encontrado no sistema.</p>
                    ${erros.length > 0 ? `
                        <div style="margin-top: 8px; max-height: 100px; overflow-y: auto; background: #fef2f2; padding: 8px; border-radius: 8px; font-size: 0.8rem; color: #dc2626;">
                            ${erros.join('<br>')}
                        </div>
                    ` : ''}
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#dc2626'
        });
        return;
    }

    try {
        let todasEntregas = [];
        for (const id of sistemaIds) {
            const resp = await fetch(`${API_BASE}/frota/embarques/${id}`, {
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

        const respDel = await fetch(`${API_BASE}/frota/embarques/${embId}/entregas/${entregaId}`, {
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
// LIMPAR SELEÇÃO DE DISPONÍVEIS
// ======================================================================
function limparSelecionados() {
    embarquesSelecionados = [];
    document.querySelectorAll('.disponivel-item').forEach(function(el) {
        el.classList.remove('selecionado');
        const cb = el.querySelector('input[type="checkbox"]');
        if (cb) cb.checked = false;
    });
    atualizarContadorSelecao();
    atualizarFooterDisponiveis();
}

// ======================================================================
// ATUALIZAR FOOTER DE DISPONÍVEIS
// ======================================================================
function atualizarFooterDisponiveis() {
    const footer = document.getElementById('disponiveis-footer');
    const info = document.getElementById('disponiveis-info-selecao');
    const totais = document.getElementById('disponiveis-footer-totais');
    if (!footer) return;

    const totalSel = embarquesSelecionados.length;
    if (totalSel === 0) {
        footer.hidden = true;
        return;
    }

    footer.hidden = false;
    if (info) {
        info.textContent = `${totalSel} embarque${totalSel > 1 ? 's' : ''} selecionado${totalSel > 1 ? 's' : ''}`;
    }

    let pedidos = 0;
    let valor = 0;
    dadosEmbarquesERP.forEach(function(emb) {
        if (embarquesSelecionados.includes(emb.idembarque)) {
            pedidos += emb.total_pedidos || 0;
            valor += emb.valor_total || 0;
        }
    });

    if (totais) {
        totais.innerHTML = `
            <span><i class="fa-solid fa-box"></i> ${pedidos} pedidos</span>
            <span><i class="fa-solid fa-sack-dollar"></i> ${formatarMoeda(valor)}</span>
        `;
    }
}
// ======================================================================
// ESCAPAR HTML (evita XSS e quebra de layout por caracteres especiais)
// ======================================================================
function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
// ======================================================================
// NORMALIZAR BUSCA (remover acentos e caixa)
// ======================================================================
function normalizarBusca(value) {
    return String(value || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}
// ======================================================================
// MODAL SWAL DO EMBARQUE (3 ABAS + MAPA + DRAG + AÇÕES + POLLING)
// ======================================================================
async function abrirModalEmbarqueSwal(emb) {
    const id = emb.id;
    const statusInfo = mapStatusInfo(emb.status);
    const totalEntregas = parseInt(emb.total_entregas) || entregasAtuais.length;
    const concluidas = parseInt(emb.entregas_concluidas) || 0;
    const progresso = totalEntregas > 0 ? Math.round((concluidas / totalEntregas) * 100) : 0;

    // 🔥 Guarda o emb para reabrir caso a modal filha derrube esta
    window.__embarqueBackup = emb;

    // Distância e tempo previstos
    let distancia = 0;
    let anterior = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };
    entregasAtuais.forEach(e => {
        if (!e.latitude || !e.longitude) return;
        const d = calcularDistancia(anterior.lat, anterior.lng, parseFloat(e.latitude), parseFloat(e.longitude));
        if (d !== null) distancia += d;
        anterior = { lat: parseFloat(e.latitude), lng: parseFloat(e.longitude) };
    });

    const isEditavel = emb.status === 'planejado';

    const headerHtml = `
        <div class="swal-embarque-header">
            <div class="swal-embarque-header-top">
                <div class="swal-embarque-titulo">
                    <i class="fa-solid fa-truck"></i>
                    <span>Embarque <strong>${emb.numero_embarque || '#' + id}</strong></span>
                    <span class="swal-embarque-status status-${statusInfo.cls}">${statusInfo.icon} ${statusInfo.label}</span>
                </div>
                <button type="button" class="swal-embarque-fechar" onclick="Swal.close()" title="Fechar">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="swal-embarque-meta">
                <span><i class="fa-solid fa-truck-fast"></i> ${emb.veiculo_placa || 'Sem veículo'}</span>
                <span><i class="fa-solid fa-user"></i> ${emb.motorista_nome || 'Sem motorista'}</span>
                <span><i class="fa-solid fa-box"></i> ${concluidas}/${totalEntregas} entregas</span>
                <span><i class="fa-solid fa-route"></i> <strong class="swal-embarque-distancia">${distancia.toFixed(1)} km</strong></span>
                <span><i class="fa-solid fa-clock"></i> <strong class="swal-embarque-tempo">${Math.round(distancia / 40 * 60)} min</strong></span>
                <span><i class="fa-solid fa-percent"></i> ${progresso}% concluído</span>
            </div>
        </div>
    `;

    const abasHtml = `
        <div class="swal-embarque-abas">
            <button type="button" class="swal-embarque-aba ativa" data-aba="rota" onclick="mudarAbaEmbarque('rota', this)">
                <i class="fa-solid fa-route"></i> Rota
            </button>
            <button type="button" class="swal-embarque-aba" data-aba="historico" onclick="mudarAbaEmbarque('historico', this)">
                <i class="fa-solid fa-clock-rotate-left"></i> Histórico
            </button>
            <button type="button" class="swal-embarque-aba" data-aba="acompanhamento" onclick="mudarAbaEmbarque('acompanhamento', this)">
                <i class="fa-solid fa-satellite-dish"></i> Acompanhamento
            </button>
        </div>
    `;

    const acoesHtml = isEditavel ? `
        <div class="swal-embarque-acoes">
            <button type="button" class="swal-acao-btn" onclick="otimizarRotaEmbarque()" title="Ordenar por proximidade">
                <i class="fa-solid fa-wand-magic-sparkles"></i> Otimizar
            </button>
            <button type="button" class="swal-acao-btn" onclick="inverterRotaEmbarque()" title="Inverter ordem">
                <i class="fa-solid fa-arrows-rotate"></i> Inverter
            </button>
            <button type="button" class="swal-acao-btn" onclick="desfazerOrdemEmbarque()" title="Desfazer última alteração">
                <i class="fa-solid fa-rotate-left"></i> Desfazer
            </button>
            <span class="swal-acao-hint"><i class="fa-solid fa-circle-info"></i> Arraste os itens para reordenar</span>
        </div>
    ` : `
        <div class="swal-embarque-acoes swal-embarque-acoes-bloqueadas">
            <span class="swal-acao-hint"><i class="fa-solid fa-lock"></i> Edição bloqueada — embarque já iniciado</span>
        </div>
    `;

    const corpoHtml = `
        <div class="swal-embarque-body">
            <div class="swal-embarque-tab-panel" data-panel="rota">
                ${acoesHtml}
                <div class="swal-embarque-grid">
                    <div class="swal-embarque-col-esq">
                        <div class="swal-embarque-col-header">
                            <i class="fa-solid fa-list-ol"></i> Entregas (${entregasAtuais.length})
                        </div>
                        <div id="swal-lista-entregas" class="swal-lista-entregas">
                            ${entregasAtuais.map((e, i) => renderItemEntrega(e, i, isEditavel)).join('')}
                        </div>
                    </div>
                    <div class="swal-embarque-col-dir">
                        <div id="embarque-mapa" class="swal-embarque-mapa"></div>
                    </div>
                </div>
            </div>
            <div class="swal-embarque-tab-panel" data-panel="historico" hidden>
                ${renderAbaHistorico(emb)}
            </div>
            <div class="swal-embarque-tab-panel" data-panel="acompanhamento" hidden>
                ${renderAbaAcompanhamento(emb)}
            </div>
        </div>
    `;

    const footerHtml = `
        <div class="swal-embarque-footer">
            <button type="button" class="swal-embarque-btn-sec" onclick="exportarRota()">
                <i class="fa-solid fa-file-csv"></i> Exportar CSV
            </button>
            <button type="button" class="swal-embarque-btn-pri" onclick="Swal.close()">
                Fechar
            </button>
        </div>
    `;

    // ================================================================
    // Silencia o erro conhecido do MapLibre + OpenFreeMap
    // ("Expected value to be of type number, but found null instead")
    // ================================================================
    if (!window.__mapErrorFilterInstalled) {
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

    await Swal.fire({
        html: `
            <div class="swal-embarque-modal-inner">
                ${headerHtml}
                ${abasHtml}
                ${corpoHtml}
                ${footerHtml}
            </div>
        `,
        width: '100vw',
        heightAuto: false,
        padding: 0,
        showConfirmButton: false,
        showCloseButton: false,
        allowOutsideClick: false,
        customClass: { popup: 'swal-embarque-modal-fullscreen' },
        didOpen: () => {
            // Limpa a flag de reabertura
            window.__embarqueReabrindo = false;

            setTimeout(() => {
                inicializarMapaEmbarque();
                initSortableEmbarque();

                if (embarqueMapa) {
                    setTimeout(() => {
                        try { embarqueMapa.resize(); } catch (e) {}
                    }, 250);
                }

                // 🔥 Inicia o polling (só roda se em_andamento ou problema)
                iniciarPollingEmbarque();
            }, 100);
        },
        willClose: () => {
            // 🔥 Para o polling sempre (mesmo se for reabertura, será reiniciado)
            pararPollingEmbarque();

            // 🔥 Reset defensivo da flag: garante que ela NUNCA fica presa em true.
            // Se o didOpen do próximo modal não rodar por algum motivo, ela ainda
            // volta pro estado correto aqui.
            const eraReabertura = !!window.__embarqueReabrindo;
            window.__embarqueReabrindo = false;

            // Se estamos reabrindo (troca de modal), NÃO destroi o mapa
            if (eraReabertura) return;

            if (embarqueMapa) {
                embarqueMapa.remove();
                embarqueMapa = null;
            }
        }
    });
}

// ======================================================================
// DISTÂNCIA ACUMULADA (distribuidora → parada 1 → ... → parada N)
// Retorna Map<entregaId, { acumulada, trecho }>
// ======================================================================
function calcularDistanciasAcumuladas() {
    const resultado = new Map();
    let latAnt = DISTRIBUIDORA_LAT;
    let lngAnt = DISTRIBUIDORA_LNG;
    let acumulada = 0;

    entregasAtuais.forEach((e) => {
        if (!e.latitude || !e.longitude) {
            resultado.set(e.id, { acumulada: acumulada, trecho: null });
            return;
        }
        const lat = parseFloat(e.latitude);
        const lng = parseFloat(e.longitude);
        const trecho = calcularDistancia(latAnt, lngAnt, lat, lng);
        if (trecho !== null) acumulada += trecho;
        resultado.set(e.id, { acumulada: acumulada, trecho: trecho });
        latAnt = lat;
        lngAnt = lng;
    });

    return resultado;
}
function renderItemEntrega(e, index, isEditavel) {
    const statusCls = (e.status || 'pendente').replace(/_/g, '-');
    const statusLabel = getStatusLabel(e.status);
    const statusIcon = getStatusIcon(e.status);
    const podeAcao = e.status === 'pendente' || e.status === 'em_entrega';

    // 🔥 Distância acumulada desde a distribuidora até esta parada
 const distancias = window.__embarqueEstado?.distanciasAcumuladas || calcularDistanciasAcumuladas();
const infoDist = distancias.get(e.id);
    const kmTag = (infoDist && infoDist.acumulada > 0)
        ? `<span class="swal-entrega-tag km" title="Distância acumulada desde a distribuidora">
              <i class="fa-solid fa-route"></i> ${infoDist.acumulada.toFixed(1)} km
           </span>`
        : '';

    return `
        <div class="swal-entrega-item status-${statusCls}" data-id="${e.id}" data-ordem="${index + 1}">
            ${isEditavel ? '<span class="swal-entrega-handle"><i class="fa-solid fa-grip-vertical"></i></span>' : ''}
            <span class="swal-entrega-num">${index + 1}</span>
            <div class="swal-entrega-info">
                <div class="swal-entrega-nome">${e.cliente_nome || 'Cliente'}</div>
                <div class="swal-entrega-end"><i class="fa-solid fa-location-dot"></i> ${e.endereco || ''}${e.numero ? ', ' + e.numero : ''}${e.cidade ? ' — ' + e.cidade : ''}</div>
                <div class="swal-entrega-tags">
                    <span class="swal-entrega-status status-${statusCls}">${statusIcon} ${statusLabel}</span>
                    ${kmTag}
                    ${e.nome_recebedor ? `<span class="swal-entrega-tag"><i class="fa-solid fa-user"></i> ${e.nome_recebedor}</span>` : ''}
                    ${e.horario_entrega ? `<span class="swal-entrega-tag"><i class="fa-solid fa-clock"></i> ${formatarDataHora(e.horario_entrega)}</span>` : ''}
                    ${e.valor_total ? `<span class="swal-entrega-tag"><i class="fa-solid fa-sack-dollar"></i> ${formatarMoeda(e.valor_total)}</span>` : ''}
                </div>
            </div>
            <div class="swal-entrega-acoes">
                ${podeAcao ? `
                    <button type="button" class="swal-entrega-btn azul" title="Check-in" onclick="registrarCheckin(${e.id})">
                        <i class="fa-solid fa-right-to-bracket"></i>
                    </button>
                    <button type="button" class="swal-entrega-btn verde" title="Checkout" onclick="registrarCheckout(${e.id})">
                        <i class="fa-solid fa-check-double"></i>
                    </button>
                ` : ''}
                <div class="swal-entrega-menu">
                    <button type="button" class="swal-entrega-btn menu" onclick="toggleMenuEntrega(event, ${e.id})" title="Mais ações">
                        <i class="fa-solid fa-ellipsis-vertical"></i>
                    </button>
                    <div class="swal-entrega-menu-dropdown" id="entrega-menu-${e.id}" hidden>
                        <button type="button" onclick="verDetalhesEntrega(${e.id}); fecharMenusEntrega();">
                            <i class="fa-solid fa-eye"></i> Ver detalhes
                        </button>
                        ${podeAcao ? `
                            <button type="button" class="danger" onclick="registrarFalha(${e.id}); fecharMenusEntrega();">
                                <i class="fa-solid fa-triangle-exclamation"></i> Registrar falha
                            </button>
                        ` : ''}
                        ${(e.foto_romaneio_url || (e.checklist && e.checklist.some(i => i.foto_url))) ? `
                            <button type="button" onclick="abrirGaleriaFotos(${e.id}); fecharMenusEntrega();">
                                <i class="fa-solid fa-images"></i> Ver fotos
                            </button>
                        ` : ''}
                        ${(e.checklist && e.checklist.length) ? `
                            <button type="button" onclick="verItensCheckout(${e.id}); fecharMenusEntrega();">
                                <i class="fa-solid fa-clipboard-check"></i> Itens do checkout
                            </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

// ----------------------------------------------------------------------
// Aba Histórico
// ----------------------------------------------------------------------
function renderAbaHistorico(emb) {
    const historico = emb.historico || [];
    if (!historico.length) {
        return `<div class="swal-embarque-empty"><i class="fa-solid fa-clock-rotate-left"></i> Nenhum evento registrado.</div>`;
    }
    return `
        <div class="swal-historico-lista">
            ${historico.map(h => `
                <div class="swal-historico-item">
                    <div class="swal-historico-icon">
                        <i class="fa-solid fa-circle"></i>
                    </div>
                    <div class="swal-historico-info">
                        <div class="swal-historico-title">${h.acao || 'Ação'}</div>
                        <div class="swal-historico-desc">${h.descricao || ''}</div>
                    </div>
                    <div class="swal-historico-meta">
                        <span><i class="fa-regular fa-clock"></i> ${formatarDataHora(h.data_hora)}</span>
                        ${h.usuario_nome ? `<span><i class="fa-regular fa-user"></i> ${h.usuario_nome}</span>` : ''}
                    </div>
                </div>
            `).join('')}
        </div>
    `;
}

// ----------------------------------------------------------------------
// Aba Acompanhamento
// ----------------------------------------------------------------------
function renderAbaAcompanhamento(emb) {
    const total = entregasAtuais.length;
    const entregues = entregasAtuais.filter(e => e.status === 'entregue' || e.status === 'entregue_com_problema').length;
    const pendentes = entregasAtuais.filter(e => e.status === 'pendente').length;
    const emRota = entregasAtuais.filter(e => e.status === 'em_entrega').length;
    const comProblema = entregasAtuais.filter(e => e.status === 'problema' || e.status === 'falha').length;

    return `
        <div class="swal-acompanhamento">
            <div class="swal-acomp-cards">
                <div class="swal-acomp-card"><i class="fa-solid fa-box"></i><strong>${total}</strong><span>Total</span></div>
                <div class="swal-acomp-card sucesso"><i class="fa-solid fa-check-circle"></i><strong>${entregues}</strong><span>Entregues</span></div>
                <div class="swal-acomp-card amarelo"><i class="fa-solid fa-truck-fast"></i><strong>${emRota}</strong><span>Em rota</span></div>
                <div class="swal-acomp-card"><i class="fa-solid fa-hourglass-half"></i><strong>${pendentes}</strong><span>Pendentes</span></div>
                <div class="swal-acomp-card perigo"><i class="fa-solid fa-triangle-exclamation"></i><strong>${comProblema}</strong><span>Problemas</span></div>
            </div>
            <div class="swal-acomp-info">
                <p><i class="fa-solid fa-circle-info"></i> Esta aba mostrará em tempo real o progresso do motorista e o trajeto percorrido.</p>
                <p class="text-sm">Atualização automática a cada 30s enquanto o modal estiver aberto.</p>
            </div>
        </div>
    `;
}

// ----------------------------------------------------------------------
// Helpers de UI
// ----------------------------------------------------------------------
function mapStatusInfo(status) {
    const map = {
        'planejado':    { label: 'Planejado',    cls: 'planejado',    icon: '📋' },
        'em_andamento': { label: 'Em andamento', cls: 'em-andamento', icon: '🚚' },
        'finalizado':   { label: 'Finalizado',   cls: 'finalizado',   icon: '✅' },
        'cancelado':    { label: 'Cancelado',    cls: 'cancelado',    icon: '🚫' },
        'problema':     { label: 'Com problema', cls: 'problema',     icon: '⚠️' }
    };
    return map[status] || { label: status || '-', cls: 'planejado', icon: '📋' };
}
// ======================================================================
// NAVEGAÇÃO ENTRE ABAS DO MODAL DO EMBARQUE
// ======================================================================
function mudarAbaEmbarque(aba, btn) {
    window.__embarqueEstado.abaAtiva = aba;

    document.querySelectorAll('.swal-embarque-aba').forEach(b => b.classList.remove('ativa'));
    btn.classList.add('ativa');

    document.querySelectorAll('.swal-embarque-tab-panel').forEach(p => {
        p.hidden = p.dataset.panel !== aba;
    });

    // Quando volta para a aba Rota, redesenha o mapa E reinicia o sortable
    if (aba === 'rota' && embarqueMapa) {
        setTimeout(() => {
            embarqueMapa.resize();
            desenharMapaEmbarque();
            initSortableEmbarque();
        }, 50);
    }

    // 🔥 Se o polling deixou um redesenho pendente, aplica agora
    if (window.__embarqueEstado?._pendenteRedesenho) {
        window.__embarqueEstado._pendenteRedesenho = false;
        const embBackup = window.__embarqueBackup;
        if (embBackup) {
            setTimeout(() => redesenharModalEmbarqueCompleto(embBackup, []), 100);
        }
    }
}

function otimizarRotaEmbarque() {
    if (!window.__embarqueEstado?.isEditavel) return;

    // Guarda estado atual para desfazer
    window.__embarqueEstado.snapshotHistorico.push(entregasAtuais.map(e => e.id));

    // Algoritmo do vizinho mais próximo partindo da distribuidora
    const naoVisitados = [...entregasAtuais];
    const rota = [];
    let atual = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };

    while (naoVisitados.length) {
        let melhorIdx = 0;
        let melhorDist = Infinity;
        naoVisitados.forEach((e, i) => {
            if (!e.latitude || !e.longitude) return;
            const d = calcularDistancia(atual.lat, atual.lng, parseFloat(e.latitude), parseFloat(e.longitude));
            if (d !== null && d < melhorDist) {
                melhorDist = d;
                melhorIdx = i;
            }
        });
        const escolhida = naoVisitados.splice(melhorIdx, 1)[0];
        rota.push(escolhida);
        if (escolhida.latitude && escolhida.longitude) {
            atual = { lat: parseFloat(escolhida.latitude), lng: parseFloat(escolhida.longitude) };
        }
    }

    entregasAtuais = rota;
    redesenharListaEmbarque();
    desenharMapaEmbarque();       // 🔥 NOVO
    recalcularRotaEmbarque();

    // Enquadra a nova rota no mapa
    if (embarqueMapa && entregasAtuais.length > 0) {
        const bounds = new maplibregl.LngLatBounds();
        bounds.extend([DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT]);
        entregasAtuais.forEach(e => {
            if (e.latitude && e.longitude) {
                bounds.extend([parseFloat(e.longitude), parseFloat(e.latitude)]);
            }
        });
        try { embarqueMapa.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 500 }); } catch (err) {}
    }

    mostrarNotificacao('Rota otimizada por proximidade', 'success');
}
function inverterRotaEmbarque() {
    if (!window.__embarqueEstado?.isEditavel) return;

    // Guarda estado atual para "Desfazer"
    window.__embarqueEstado.snapshotHistorico.push(entregasAtuais.map(e => e.id));

    // Inverte a ordem: o último vira primeiro
    entregasAtuais.reverse();

    // Redesenha a lista (com nova numeração)
    redesenharListaEmbarque();

    // 🔥 Redesenha o mapa: os marcadores recebem novos números e a linha é refeita
    desenharMapaEmbarque();

    // Recalcula distância total, tempo, header
    recalcularRotaEmbarque();

    // Ajusta o zoom para enquadrar a nova ordem
    if (embarqueMapa && entregasAtuais.length > 0) {
        const bounds = new maplibregl.LngLatBounds();
        bounds.extend([DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT]);
        entregasAtuais.forEach(e => {
            if (e.latitude && e.longitude) {
                bounds.extend([parseFloat(e.longitude), parseFloat(e.latitude)]);
            }
        });
        try {
            embarqueMapa.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 500 });
        } catch (err) {}
    }

    mostrarNotificacao('Rota invertida — agora começa pelo último cliente', 'info');
}
function desfazerOrdemEmbarque() {
    const hist = window.__embarqueEstado?.snapshotHistorico;
    if (!hist || !hist.length) {
        mostrarNotificacao('Nada para desfazer', 'warning');
        return;
    }
    const ordemAnterior = hist.pop();
    const reordenado = [];
    ordemAnterior.forEach(id => {
        const e = entregasAtuais.find(x => x.id === id);
        if (e) reordenado.push(e);
    });
    entregasAtuais = reordenado;
    redesenharListaEmbarque();
    desenharMapaEmbarque();       // 🔥 NOVO
    recalcularRotaEmbarque();

    if (embarqueMapa && entregasAtuais.length > 0) {
        const bounds = new maplibregl.LngLatBounds();
        bounds.extend([DISTRIBUIDORA_LNG, DISTRIBUIDORA_LAT]);
        entregasAtuais.forEach(e => {
            if (e.latitude && e.longitude) {
                bounds.extend([parseFloat(e.longitude), parseFloat(e.latitude)]);
            }
        });
        try { embarqueMapa.fitBounds(bounds, { padding: 60, maxZoom: 14, duration: 500 }); } catch (err) {}
    }

    mostrarNotificacao('Ordem desfeita', 'info');
}

function redesenharListaEmbarque() {
    const container = document.getElementById('swal-lista-entregas');
    if (!container) return;
    const editavel = window.__embarqueEstado?.isEditavel;

    // Cache do cálculo: 1 vez por renderização
    window.__embarqueEstado.distanciasAcumuladas = calcularDistanciasAcumuladas();

    container.innerHTML = entregasAtuais.map((e, i) => renderItemEntrega(e, i, editavel)).join('');
    initSortableEmbarque();
}

// ----------------------------------------------------------------------
// Menu "⋮" dos itens de entrega
// ----------------------------------------------------------------------
function toggleMenuEntrega(event, entregaId) {
    event.stopPropagation();
    const dropdown = document.getElementById(`entrega-menu-${entregaId}`);
    if (!dropdown) return;
    const jaAberto = !dropdown.hidden;
    fecharMenusEntrega();
    if (jaAberto) return;

    dropdown.hidden = false;

    const trigger = event.currentTarget;
    const rect = trigger.getBoundingClientRect();
    dropdown.style.position = 'fixed';
    dropdown.style.top = (rect.bottom + 4) + 'px';
    dropdown.style.left = Math.min(rect.right - 180, window.innerWidth - 190) + 'px';
    dropdown.style.zIndex = '2000';
}

function fecharMenusEntrega() {
    document.querySelectorAll('.swal-entrega-menu-dropdown').forEach(d => d.hidden = true);
}

document.addEventListener('click', (e) => {
    if (!e.target.closest('.swal-entrega-menu')) fecharMenusEntrega();
});

// ======================================================================
// POLLING DE 30s — atualiza o modal do embarque enquanto está aberto
// Roda sempre que o embarque está em_andamento ou problema.
// Evita redesenhar DOM durante drag ou quando a modal filha está aberta.
// ======================================================================

const POLLING_INTERVALO_MS = 30000;

/**
 * Inicia o polling do embarque atual (usa window.__embarqueEstado.id).
 * Idempotente: se já houver timer ativo, não cria outro.
 */
function iniciarPollingEmbarque() {
    const estado = window.__embarqueEstado;
    if (!estado) return;
    if (estado.pollingTimer) return; // já ativo

    // Só roda em embarques "vivos"
    if (estado.status !== 'em_andamento' && estado.status !== 'problema') {
        console.log('[polling] Embarque não está em_andamento/problema. Polling não será iniciado.');
        return;
    }

    estado.pollingTimer = setInterval(() => {
        atualizarDadosEmbarque().catch(err => {
            console.warn('[polling] Falha ao atualizar embarque:', err);
        });
    }, POLLING_INTERVALO_MS);

    console.log('[polling] Iniciado. Intervalo:', POLLING_INTERVALO_MS, 'ms');
}

/**
 * Para o polling do embarque atual.
 */
function pararPollingEmbarque() {
    const estado = window.__embarqueEstado;
    if (!estado) return;
    if (estado.pollingTimer) {
        clearInterval(estado.pollingTimer);
        estado.pollingTimer = null;
        console.log('[polling] Parado.');
    }
}

/**
 * Verifica se o DOM pode ser redesenhado agora.
 * Retorna false se o usuário está arrastando ou a modal filha está aberta.
 */
function podeRedesenharDom() {
    // 1. Modal filha aberta?
    if (document.getElementById('swal-filho-container')) {
        const container = document.getElementById('swal-filho-container');
        if (container && container.style.pointerEvents === 'auto') {
            return false;
        }
    }

    // 2. Usuário arrastando item?
    if (document.querySelector('.swal-entrega-item.is-arrastando')) {
        return false;
    }

    // 3. Sortable em modo drag? (fallback defensivo)
    if (window.__sortableInstance && window.__sortableInstance._dragEl) {
        return false;
    }

    return true;
}

// ======================================================================
// POLLING DE 30s — atualizarDadosEmbarque
// Busca o estado atual do embarque no backend e sincroniza com o modal.
// Atualiza sempre o estado interno. Só redesenha DOM se permitido.
// Para o polling em 401/404 (token expirado ou embarque deletado).
// ======================================================================
async function atualizarDadosEmbarque() {
    const estado = window.__embarqueEstado;
    if (!estado || !estado.id) return;

    const token = getAuthToken();
    if (!token) {
        pararPollingEmbarque();
        return;
    }

    // Se a modal pai já foi fechada, aborta
    if (!document.querySelector('.swal-embarque-modal-fullscreen')) {
        pararPollingEmbarque();
        return;
    }

    let resp;
    try {
        resp = await fetch(`${API_BASE}/frota/embarques/${estado.id}`, {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
        });
    } catch (e) {
        // Falha de rede — só loga, não trava nada e mantém o polling
        return;
    }

    // 🔥 Para o polling em 401 (token expirado) ou 404 (embarque deletado)
    if (resp.status === 401 || resp.status === 404) {
        pararPollingEmbarque();
        console.warn('[polling] Parado por HTTP', resp.status, '— embarque não está mais disponível.');
        return;
    }

    if (!resp.ok) return;

    let dados;
    try { dados = await resp.json(); } catch { return; }
    if (!dados.success || !dados.data) return;

    const emb = dados.data;

    // 1. Atualiza status do embarque (pode ter mudado pra finalizado, etc.)
    const statusMudou = emb.status !== estado.status;
    estado.status = emb.status;
    estado.isEditavel = (emb.status === 'planejado');

    // 2. Atualiza entregas em memória
    const novasEntregas = (emb.entregas || []).map((e, i) => ({
        ...e,
        id: e.id || i + 1,
        cliente_nome: e.cliente_nome || 'Cliente',
        endereco: e.endereco || '',
        numero: e.numero || '',
        bairro: e.bairro || '',
        cidade: e.cidade || '',
        uf: e.uf || '',
        valor_total: e.valor_total || 0,
        peso_total: e.peso_total || 0,
        status: e.status || 'pendente',
        ordem_entrega: i + 1,
        pedidos_ids: e.pedidos_ids || ''
    }));

    // Detecta mudanças visíveis pro usuário (para toast discreto no futuro)
    const mudancas = detectarMudancasEntregas(entregasAtuais, novasEntregas);

    entregasAtuais = novasEntregas;

    // Atualiza o backup (para reabrir a modal caso precise)
    window.__embarqueBackup = emb;

    // 3. Recalcula cache de distâncias
    estado.distanciasAcumuladas = calcularDistanciasAcumuladas();

    // 4. Só redesenha DOM se permitido
    if (!podeRedesenharDom()) {
        // Guarda a flag pra saber que precisa redesenhar no próximo tick
        estado._pendenteRedesenho = true;
        return;
    }

    redesenharModalEmbarqueCompleto(emb, mudancas);

    // Se o status mudou (ex.: finalizou), para o polling
    if (statusMudou && emb.status !== 'em_andamento' && emb.status !== 'problema') {
        pararPollingEmbarque();
    }
}

/**
 * Compara duas listas de entregas e retorna as mudanças relevantes.
 * Usado para log futuro e para eventual toast discreto.
 */
function detectarMudancasEntregas(antes, depois) {
    const mudancas = [];
    const mapaAntes = new Map((antes || []).map(e => [e.id, e]));

    (depois || []).forEach(nova => {
        const velha = mapaAntes.get(nova.id);
        if (!velha) return;
        if (velha.status !== nova.status) {
            mudancas.push({
                tipo: 'status',
                entrega_id: nova.id,
                cliente: nova.cliente_nome,
                de: velha.status,
                para: nova.status
            });
        }
        if (!velha.horario_checkin && nova.horario_checkin) {
            mudancas.push({
                tipo: 'checkin',
                entrega_id: nova.id,
                cliente: nova.cliente_nome
            });
        }
        if (!velha.horario_entrega && nova.horario_entrega) {
            mudancas.push({
                tipo: 'checkout',
                entrega_id: nova.id,
                cliente: nova.cliente_nome
            });
        }
    });

    return mudancas;
}

/**
 * Redesenha o modal completo: header, lista, cards e mapa.
 * Mantém a posição do scroll da lista, quando possível.
 */
function redesenharModalEmbarqueCompleto(emb, mudancas) {
    // 1. Header — meta e contadores
    const statusInfo = mapStatusInfo(emb.status);
    const totalEntregas = parseInt(emb.total_entregas) || entregasAtuais.length;
    const concluidas = parseInt(emb.entregas_concluidas) || 0;
    const progresso = totalEntregas > 0 ? Math.round((concluidas / totalEntregas) * 100) : 0;

    const tituloEl = document.querySelector('.swal-embarque-titulo');
    if (tituloEl) {
        const statusBadge = tituloEl.querySelector('.swal-embarque-status');
        if (statusBadge) {
            statusBadge.className = `swal-embarque-status status-${statusInfo.cls}`;
            statusBadge.textContent = `${statusInfo.icon} ${statusInfo.label}`;
        }
    }

    const metaEl = document.querySelector('.swal-embarque-meta');
    if (metaEl) {
        // Atualiza contadores dentro do meta (entregas, %)
        metaEl.querySelectorAll('span').forEach(span => {
            const txt = span.textContent;
            if (txt.includes('/') && txt.includes('entregas')) {
                span.innerHTML = `<i class="fa-solid fa-box"></i> ${concluidas}/${totalEntregas} entregas`;
            }
            if (txt.includes('% concluído')) {
                span.innerHTML = `<i class="fa-solid fa-percent"></i> ${progresso}% concluído`;
            }
        });
    }

    // 2. Lista de entregas — preserva scroll
    const listaEl = document.getElementById('swal-lista-entregas');
    if (listaEl) {
        const scrollTop = listaEl.scrollTop;
        const isEditavel = window.__embarqueEstado?.isEditavel;
        listaEl.innerHTML = entregasAtuais.map((e, i) => renderItemEntrega(e, i, isEditavel)).join('');
        listaEl.scrollTop = scrollTop;
        // Reinicia sortable (só age se isEditavel)
        initSortableEmbarque();
    }

    // 3. Cards da aba Acompanhamento (se visível)
    const acPanel = document.querySelector('.swal-embarque-tab-panel[data-panel="acompanhamento"]');
    if (acPanel && !acPanel.hidden) {
        acPanel.innerHTML = renderAbaAcompanhamento(emb);
    }

    // 4. Mapa — redesenha marcadores (não recria o mapa)
    if (embarqueMapa) {
        desenharMapaEmbarque();
    }

    // 5. Distância/tempo do header (recém-calculados)
    recalcularRotaEmbarque();
}
// ----------------------------------------------------------------------
// Exportações globais (novas)
// ----------------------------------------------------------------------
window.iniciarPollingEmbarque = iniciarPollingEmbarque;
window.pararPollingEmbarque = pararPollingEmbarque;
window.atualizarDadosEmbarque = atualizarDadosEmbarque;
window.redesenharModalEmbarqueCompleto = redesenharModalEmbarqueCompleto;
window.podeRedesenharDom = podeRedesenharDom;
window.verDetalhesEntrega = verDetalhesEntrega;
window.abrirModalEmbarqueSwal = abrirModalEmbarqueSwal;
window.mudarAbaEmbarque = mudarAbaEmbarque;
window.otimizarRotaEmbarque = otimizarRotaEmbarque;
window.inverterRotaEmbarque = inverterRotaEmbarque;
window.desfazerOrdemEmbarque = desfazerOrdemEmbarque;
window.toggleMenuEntrega = toggleMenuEntrega;
window.fecharMenusEntrega = fecharMenusEntrega;
window.destacarEntregaNaLista = destacarEntregaNaLista;
window.recalcularRotaEmbarque = recalcularRotaEmbarque;
window.toggleMenuAcoes = toggleMenuAcoes;
window.fecharTodosMenusAcoes = fecharTodosMenusAcoes;
window.excluirEmbarque = excluirEmbarque;
window.escapeHtml = escapeHtml;
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
window.toggleTheme = toggleTheme;
window.mostrarNotificacao = mostrarNotificacao;
window.exportarRota = exportarRota;
window.abrirGaleriaFotos = abrirGaleriaFotos;
window.abrirZoomFoto = abrirZoomFoto;
window.fecharZoom = fecharZoom;
window.limparSelecionados = limparSelecionados;
window.atualizarFooterDisponiveis = atualizarFooterDisponiveis;
window.normalizarBusca = normalizarBusca;