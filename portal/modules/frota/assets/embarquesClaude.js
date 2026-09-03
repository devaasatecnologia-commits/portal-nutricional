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
let mapaRota = null;
let rotaMarkers = [];
let rotaPolyline = null;
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
        window.location.href = '/portal/login.php';
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

// ======================================================================
// MAPEAR IDS DO ERP PARA IDS DO SISTEMA - FUNÇÃO AUXILIAR
// ======================================================================
async function mapearErpIdsParaSistema(erpIds) {
    const token = getAuthToken();
    if (!token) return { sistemaIds: [], erros: ['Token não encontrado'] };
    
    const sistemaIds = [];
    const erros = [];
    const erpIdsArray = Array.isArray(erpIds) ? erpIds : [erpIds];
    
    // Buscar todos os embarques do sistema uma única vez
    let todosEmbarques = [];
    try {
        const respLista = await fetch('/v1/frota/embarques?limite=10000', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (respLista.ok) {
            const dados = await respLista.json();
            if (dados.success && dados.data) {
                todosEmbarques = dados.data;
            }
        }
    } catch (e) {
        erros.push('Erro ao buscar lista de embarques: ' + e.message);
    }
    
    for (const erpId of erpIdsArray) {
        try {
            // 1. Tentar encontrar pelo ID do sistema diretamente
            const encontradoDireto = todosEmbarques.find(e => e.id == erpId);
            if (encontradoDireto) {
                sistemaIds.push(encontradoDireto.id);
                continue;
            }
            
            // 2. Tentar encontrar pelo erp_embarque_id
            const encontradoErp = todosEmbarques.find(e => e.erp_embarque_id == erpId);
            if (encontradoErp) {
                sistemaIds.push(encontradoErp.id);
                continue;
            }
            
            // 3. Tentar encontrar pelo numero_embarque
            const encontradoNumero = todosEmbarques.find(e => e.numero_embarque == erpId || e.numero_embarque == '#' + erpId);
            if (encontradoNumero) {
                sistemaIds.push(encontradoNumero.id);
                continue;
            }
            
            // 4. Tentar encontrar dentro de erp_ids_agrupados
            const encontradoAgrupado = todosEmbarques.find(e => 
                e.erp_ids_agrupados && e.erp_ids_agrupados.split(',').map(Number).includes(erpId)
            );
            if (encontradoAgrupado) {
                sistemaIds.push(encontradoAgrupado.id);
                continue;
            }
            
            // 5. Tentar buscar diretamente pela API (fallback)
            const respSistema = await fetch(`/v1/frota/embarques/${erpId}`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            
            if (respSistema.ok) {
                const data = await respSistema.json();
                if (data.success && data.data) {
                    sistemaIds.push(data.data.id);
                    continue;
                }
            }
            
            // Se chegou aqui, não encontrou
            erros.push(`ID ${erpId} não encontrado no sistema`);
            
        } catch (e) {
            erros.push(`Erro ao buscar ID ${erpId}: ${e.message}`);
        }
    }
    
    // Remover duplicatas
    const sistemaIdsUnicos = [...new Set(sistemaIds)];
    
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
        const response = await fetch('/v1/frota/importar/embarques-erp', {
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
// RENDERIZAR ABAS DINAMICAMENTE
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
                <button class="aba-disponivel ${ativa}" data-aba="${key}" onclick="mudarAba('${key}')">
                    ${labels[key] || key}
                    <span class="badge-aba">${statusCount[key]}</span>
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
    document.querySelectorAll('.aba-disponivel').forEach(btn => {
        btn.classList.toggle('ativa', btn.dataset.aba === aba);
    });
    renderizarDisponiveis(dadosEmbarquesERP, aba);
    embarquesSelecionados = [];
    atualizarContadorSelecao();
}

// ======================================================================
// RENDERIZAR LISTA DE DISPONÍVEIS
// ======================================================================
function renderizarDisponiveis(embarques, aba) {
    const container = document.getElementById('lista-disponiveis');

    const abaNormalizada = aba.toUpperCase();
    let filtrados = embarques;
    if (aba !== 'todos') {
        filtrados = embarques.filter(emb => {
            const statusERP = (emb.status_logistico || 'PENDENTE').toUpperCase();
            return statusERP === abaNormalizada;
        });
    }

    let totalPedidos = 0;
    let totalValor = 0;
    let filiais = new Set();
    filtrados.forEach(emb => {
        totalPedidos += emb.total_pedidos || 0;
        totalValor += emb.valor_total || 0;
        if (emb.idfilial) filiais.add(emb.idfilial);
    });

    document.getElementById('info-disponiveis').textContent =
`${filtrados.length} embarques • ${totalPedidos} pedidos • R$ ${totalValor.toFixed(2)} • ${filiais.size} filiais`;

if (filtrados.length === 0) {
    container.innerHTML = `
            <div class="text-center py-4 text-slate-400">
                <i class="fa-regular fa-inbox text-2xl block mb-2"></i>
                Nenhum embarque com este status
            </div>
    `;
    return;
}

let html = '';
filtrados.forEach(emb => {
    const statusClass = {
        'PENDENTE': 'bg-yellow-100 text-yellow-700',
        'SEPARADO': 'bg-blue-100 text-blue-700',
        'CARREGADO': 'bg-green-100 text-green-700'
    } [emb.status_logistico] || 'bg-slate-100 text-slate-700';

    const clientesNomes = (emb.clientes || []).slice(0, 3).map(c => c.nome || c.razao || 'Cliente');
    const temMais = (emb.clientes || []).length > 3;

    html += `
            <div class="embarque-disponivel-item flex items-start gap-4 p-4 border border-slate-200 rounded-xl hover:shadow-md transition-all cursor-pointer ${embarquesSelecionados.includes(emb.idembarque) ? 'selecionado' : ''}"
                 data-id="${emb.idembarque}" onclick="toggleSelecionarDisponivel(${emb.idembarque})">
                <div class="checkbox flex-shrink-0 mt-1">
                    <i class="fa-solid fa-check ${embarquesSelecionados.includes(emb.idembarque) ? 'text-white' : 'text-transparent'}"></i>
                </div>
                <div class="flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-bold text-[#1a3c34]">#${emb.idembarque}</span>
                        <span class="px-2 py-0.5 rounded-full text-xs font-bold ${statusClass}">${emb.status_logistico || 'PENDENTE'}</span>
                        ${emb.gerou_nf === 'S' ? '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700">NF Gerada</span>' : ''}
                        ${emb.pex_conferido === 'S' ? '<span class="px-2 py-0.5 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Separado</span>' : ''}
                    </div>
                    <p class="text-sm text-slate-500 mt-1">${emb.rota || 'Sem descrição'}</p>
                    <div class="flex flex-wrap gap-3 mt-2 text-xs">
                        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">📦 ${emb.total_pedidos || 0} pedidos</span>
                        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">💰 ${formatarMoeda(emb.valor_total || 0)}</span>
                        <span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">🏢 Filial ${emb.idfilial || '-'}</span>
        ${emb.placa ? `<span class="px-2 py-1 bg-slate-100 rounded-full border border-slate-200">🚛 ${emb.placa}</span>` : ''}
                    </div>
        ${clientesNomes.length ? `
                        <div class="flex flex-wrap gap-1 mt-2">
            ${clientesNomes.map(n => `<span class="text-xs bg-slate-200 px-2 py-0.5 rounded-full">${n}</span>`).join('')}
            ${temMais ? `<span class="text-xs text-slate-400">+${emb.clientes.length - 3}</span>` : ''}
                        </div>
            ` : ''}
                </div>
            </div>
        `;
    });

container.innerHTML = html;
atualizarContadorSelecao();
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
    document.querySelectorAll('.embarque-disponivel-item').forEach(el => {
        const itemId = parseInt(el.dataset.id);
        if (embarquesSelecionados.includes(itemId)) {
            el.classList.add('selecionado');
            el.querySelector('.checkbox i').className = 'fa-solid fa-check text-white';
        } else {
            el.classList.remove('selecionado');
            el.querySelector('.checkbox i').className = 'fa-solid fa-check text-transparent';
        }
    });
    atualizarContadorSelecao();
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
    document.getElementById('total-selecionados-disponiveis').textContent = total;
    const btn = document.getElementById('btn-criar-rotas-disponiveis');
    if (btn) btn.disabled = total === 0;
}

// ======================================================================
// RECARREGAR LISTA DE DISPONÍVEIS
// ======================================================================
function atualizarDisponiveis() {
    embarquesSelecionados = [];
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
    // 🔥 EVENT LISTENERS DOS FILTROS - LIMPAR CACHE AO MUDAR
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
    // 🔥 ATUALIZAR INDICADOR DE CACHE A CADA 5 SEGUNDOS
    // ================================================================
    setInterval(atualizarIndicadorCache, 5000);
});

// ======================================================================
// SELECIONAR ENTREGA NO MAPA
// ======================================================================
function selecionarEntregaNoMapa(entregaId) {
    entregaSelecionadaId = entregaId;
    document.querySelectorAll('.entrega-item').forEach(function(item) {
        item.classList.toggle('ativa', parseInt(item.dataset.id) === entregaId);
    });
    centralizarNoMapa(entregaId);
}

function centralizarNoMapa(entregaId) {
    const entrega = entregasAtuais.find(function(e) { return e.id === entregaId; });
    if (!entrega || !entrega.latitude || !entrega.longitude) return;
    if (mapaRota) {
        mapaRota.setView([entrega.latitude, entrega.longitude], 15);
    }
}

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
        const response = await fetch(`/v1/frota/entregas/rastreamento/${encodeURIComponent(codigo)}`, {
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

let url = '/v1/frota/embarques?pagina=' + paginaAtual + '&limite=' + limitePorPagina;
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
            window.location.href = '/portal/login.php';
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
// RENDERIZAR EMBARQUES NA TABELA - VERSÃO COMPLETA
// ======================================================================
function renderizarEmbarques(embarques, pagination) {
    const tbody = document.getElementById('lista-embarques');

    if (!embarques || embarques.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-8 text-slate-400">
            <i class="fa-regular fa-truck text-3xl block mb-2"></i>
            Nenhum embarque encontrado
    </td></tr>`;
    return;
}

let html = '';
embarques.forEach((emb, index) => {
        // Status com ícones
    const statusIcon = {
        'planejado': '📋',
        'em_andamento': '🚚',
        'finalizado': '✅',
        'cancelado': '🚫',
        'problema': '⚠️'
    } [emb.status] || '';

    const statusClass = {
        'planejado': 'planejado',
        'em_andamento': 'em_andamento',
        'finalizado': 'finalizado',
        'cancelado': 'cancelado',
        'problema': 'problema'
    } [emb.status] || 'planejado';

    const statusText = {
        'planejado': 'Planejado',
        'em_andamento': 'Em Andamento',
        'finalizado': 'Finalizado',
        'cancelado': 'Cancelado',
        'problema': 'Problema'
    } [emb.status] || emb.status;

    const total = emb.total_entregas || 0;
    const concluidas = emb.entregas_concluidas || 0;
    const progresso = total > 0 ? Math.round((concluidas / total) * 100) : 0;

    let barClass = 'em-andamento';
    if (emb.status === 'problema') {
        barClass = 'problema';
    } else if (progresso >= 100) {
        barClass = 'concluido';
    }

    const valorTotal = emb.valor_total_entregas || 0;
    const pesoTotal = emb.peso_total_entregas || emb.peso_total || 0;

    const placa = emb.veiculo_placa || '';
    const modelo = emb.veiculo_modelo || '';
    const temVeiculo = placa && placa.trim() !== '' && placa !== 'SEM VEÍCULO';

    const veiculoDisplay = temVeiculo ?
`<span class="font-medium text-[#1a3c34] dark:text-white">${placa}</span>` :
'<span class="text-slate-400 text-xs">Não definido</span>';

const veiculoModelo = temVeiculo && modelo ?
`<span class="text-xs text-slate-400 block">${modelo}</span>` :
'';

const nomeRota = emb.nome_embarque || emb.observacoes || emb.rota || '-';

const isGrupo = (emb.total_embarques_agrupados && emb.total_embarques_agrupados > 1) || false;
const qtdEmbarques = isGrupo ? ` (${emb.total_embarques_agrupados} embarques)` : '';

let idsParaAcao = [emb.id];
if (isGrupo && emb.erp_ids_agrupados) {
    idsParaAcao = emb.erp_ids_agrupados.split(',').map(Number);
}
const idsString = idsParaAcao.join(',');

        // 🔥 Botão específico para status problema
const btnProblema = emb.status === 'problema' ? `
            <button class="btn-icone amber" onclick="verDetalhes(${emb.id})" title="Ver problemas">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </button>
` : '';

html += `
            <tr class="row-status-${statusClass}">
                <td class="text-center font-bold text-slate-400" data-label="#">${index + 1}</td>
                <td data-label="Embarque">
                    <div class="font-bold text-[#1a3c34] dark:text-white">
                        ${emb.numero_embarque || '#' + emb.id}
                        ${qtdEmbarques}
                    </div>
                    <div class="text-xs text-slate-400">${emb.nome_embarque || ''}</div>
                    ${emb.erp_embarque_id ? '<span class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 px-1.5 py-0.5 rounded-full">ERP: #' + emb.erp_embarque_id + '</span>' : ''}
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
                    <div class="flex items-center justify-center gap-1 flex-wrap">
    ${isGrupo ? `
                            <button class="btn-icone azul" onclick="verDetalhesGrupo([${idsString}])" title="Ver detalhes do grupo">
                                <i class="fa-solid fa-layer-group"></i>
                            </button>
        ` : `
                            <button class="btn-icone azul" onclick="verDetalhes(${emb.id})" title="Ver detalhes">
                                <i class="fa-solid fa-eye"></i>
                            </button>
        `}
                        <button class="btn-icone azul" onclick="abrirModalEditarGrupo([${idsString}])" title="Editar grupo">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="btn-icone vermelho" onclick="removerEntregaGrupo([${idsString}])" title="Remover entrega">
                            <i class="fa-solid fa-trash-alt"></i>
                        </button>
        ${emb.status === 'planejado' ? `
                            <button class="btn-icone verde" onclick="iniciarGrupo([${idsString}])" title="Iniciar todos">
                                <i class="fa-solid fa-play"></i>
                            </button>
            ` : ''}
            ${emb.status === 'em_andamento' || emb.status === 'problema' ? `
                            <button class="btn-icone amber" onclick="finalizarGrupo([${idsString}])" title="Finalizar todos">
                                <i class="fa-solid fa-flag-checkered"></i>
                            </button>
                ` : ''}
                ${emb.status !== 'finalizado' && emb.status !== 'cancelado' ? `
                            <button class="btn-icone vermelho" onclick="cancelarGrupo([${idsString}])" title="Cancelar todos">
                                <i class="fa-solid fa-ban"></i>
                            </button>
                    ` : ''}
                        ${btnProblema}
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
            const response = await fetch('/v1/frota/embarques/' + id + '/cancelar', {
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
            const response = await fetch('/v1/frota/embarques/' + id, {
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
            const response = await fetch(`/v1/frota/embarques/${id}/iniciar`, {
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
            const resp = await fetch(`/v1/frota/embarques/${id}`, {
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
                const response = await fetch(`/v1/frota/embarques/${id}/finalizar`, {
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
        const response = await fetch(`/v1/frota/entregas/${entregaId}/checkin`, {
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
        const resp = await fetch(`/v1/frota/entregas/${entregaId}`, {
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
                const resp = await fetch('/v1/frota/importar/itens-pedidos', {
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
    const response = await fetch(`/v1/frota/entregas/${entregaId}/checkout`, {
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
        const response = await fetch(`/v1/frota/entregas/${entregaId}/falha`, {
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
        const response = await fetch('/v1/frota/embarques/' + id + '/iniciar', {
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
        const response = await fetch('/v1/frota/embarques/' + id + '/finalizar', {
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
        const response = await fetch('/v1/frota/embarques/' + id + '/cancelar', {
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
        'cancelada': 'cancelada',
        'entregue_com_problema': 'entregue_com_problema'
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
    return status === 'entregue_com_problema' || status === 'entregue_com_problema';
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
// VER DETALHES DO EMBARQUE - VERSÃO PREMIUM COMPLETA
// ======================================================================
async function verDetalhes(id) {
    embarqueIdDetalhes = id;
    const token = getAuthToken();
    if (!token) return;

    try {
        const response = await fetch('/v1/frota/embarques/' + id, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('HTTP ' + response.status);

        const dados = await response.json();
        if (!dados.success) throw new Error(dados.error || 'Erro desconhecido');

        const emb = dados.data;
        entregasAtuais = emb.entregas || [];

        // ================================================================
        // PROCESSAR DADOS DAS ENTREGAS
        // ================================================================
        entregasAtuais = entregasAtuais.map(function(e, index) {
            e.id = e.id || index + 1;
            e.cliente_nome = e.cliente_nome || 'Cliente';
            e.endereco = e.endereco || '';
            e.numero = e.numero || '';
            e.bairro = e.bairro || '';
            e.cidade = e.cidade || '';
            e.uf = e.uf || '';
            e.pedido_id = e.pedido_id || '';
            e.valor_total = e.valor_total || 0;
            e.peso_total = e.peso_total || 0;
            e.status = e.status || 'PENDENTE';
            e.origem_geolocalizacao = e.origem_geolocalizacao || '';
            e.total_pedidos_agrupados = e.total_pedidos_agrupados || 1;
            e.pedidos_ids = e.pedidos_ids || '';
            e.erp_embarques_ids = e.erp_embarques_ids || '';
            e.foto_romaneio_url = e.foto_romaneio_url || null;
            e.foto_item_url = e.foto_item_url || null;

            e.distancia_distribuidora = (e.latitude && e.longitude) ?
            calcularDistancia(DISTRIBUIDORA_LAT, DISTRIBUIDORA_LNG, e.latitude, e.longitude) :
            null;
            e.ordem_original = index + 1;
            return e;
        });

        entregasAtuais.sort(function(a, b) {
            if (a.distancia_distribuidora === null) return 1;
            if (b.distancia_distribuidora === null) return -1;
            return a.distancia_distribuidora - b.distancia_distribuidora;
        });

        entregasAtuais.forEach(function(e, i) { e.ordem_entrega = i + 1; });

        document.getElementById('detalhes-numero').textContent = '#' + (emb.numero_embarque || emb.id);

        // ================================================================
        // CALCULAR STATS
        // ================================================================
        const statusIcon = {
            'planejado': '📋',
            'em_andamento': '🚚',
            'finalizado': '✅',
            'cancelado': '🚫',
            'problema': '⚠️'
        } [emb.status] || '';

        const statusClass = {
            'planejado': 'planejado',
            'em_andamento': 'em_andamento',
            'finalizado': 'finalizado',
            'cancelado': 'cancelado',
            'problema': 'problema'
        } [emb.status] || 'planejado';

        const statusText = {
            'planejado': 'Planejado',
            'em_andamento': 'Em Andamento',
            'finalizado': 'Finalizado',
            'cancelado': 'Cancelado',
            'problema': 'Problema'
        } [emb.status] || emb.status;

        const totalEntregas = parseInt(emb.total_entregas) || 0;
        const entregasConcluidas = parseInt(emb.entregas_concluidas) || 0;
        const progresso = totalEntregas > 0 ? Math.round((entregasConcluidas / totalEntregas) * 100) : 0;

        let distanciaTotal = 0;
        let ultimoPonto = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };
        entregasAtuais.forEach(function(e) {
            if (e.latitude && e.longitude) {
                const d = calcularDistancia(ultimoPonto.lat, ultimoPonto.lng, e.latitude, e.longitude);
                if (d !== null) { distanciaTotal += d; }
                ultimoPonto.lat = e.latitude;
                ultimoPonto.lng = e.longitude;
            }
        });

        // ================================================================
        // GERAR HTML DOS CARDS DE INFORMAÇÃO - PREMIUM
        // ================================================================
        const infoCardsHtml = `
            <div class="detalhes-grid">
                <div class="detalhes-card">
                    <div class="label"><i class="fa-solid fa-hashtag"></i> Número</div>
                    <div class="value">${emb.numero_embarque || '#' + emb.id}</div>
            ${emb.erp_embarque_id ? `<div class="value" style="font-size:0.75rem;color:var(--nutri-text-secondary);"><i class="fa-solid fa-link"></i> ERP: #${emb.erp_embarque_id}</div>` : ''}
                </div>
                <div class="detalhes-card status-card">
                    <div class="label"><i class="fa-solid fa-circle"></i> Status</div>
                    <div class="value">
                        <span class="badge-status ${statusClass}">${statusIcon} ${statusText}</span>
                    </div>
                </div>
                <div class="detalhes-card">
                    <div class="label"><i class="fa-solid fa-truck"></i> Veículo</div>
                    <div class="value">${emb.veiculo_placa || '-'}</div>
                    <div class="value sub">${emb.veiculo_modelo || ''}</div>
                </div>
                <div class="detalhes-card">
                    <div class="label"><i class="fa-solid fa-user"></i> Motorista</div>
                    <div class="value">${emb.motorista_nome || '-'}</div>
            ${emb.motorista_telefone ? `<div class="value sub"><i class="fa-solid fa-phone"></i> ${emb.motorista_telefone}</div>` : ''}
                </div>
                <div class="detalhes-card">
                    <div class="label"><i class="fa-regular fa-calendar"></i> Data Saída</div>
                    <div class="value">${formatarDataHora(emb.data_saida)}</div>
                </div>
                <div class="detalhes-card">
                    <div class="label"><i class="fa-regular fa-calendar"></i> Data Retorno</div>
                    <div class="value">${formatarDataHora(emb.data_retorno) || '-'}</div>
                </div>
            ${emb.observacoes ? `
                    <div class="detalhes-card" style="grid-column: 1 / -1;">
                        <div class="label"><i class="fa-regular fa-message"></i> Observações</div>
                        <div class="value" style="font-size:0.85rem;font-weight:400;">${emb.observacoes}</div>
                    </div>
                ` : ''}
                ${emb.erp_ids_agrupados ? `
                    <div class="detalhes-card" style="grid-column: 1 / -1;background: linear-gradient(135deg, #ede9fe, #f5f3ff);">
                        <div class="label"><i class="fa-solid fa-layer-group"></i> Embarques Agrupados</div>
                        <div class="value" style="display:flex;flex-wrap:wrap;gap:6px;font-size:0.8rem;">
                            ${emb.erp_ids_agrupados.split(',').map(id => 
                                `<span style="background:rgba(139,92,246,0.15);padding:2px 12px;border-radius:999px;color:#6b21a8;">#${id.trim()}</span>`
                                ).join('')}
                        </div>
                    </div>
                                ` : ''}
            </div>
                            `;

        // ================================================================
        // PROGRESSO EM DESTAQUE
        // ================================================================
                            let barClass = 'em-andamento';
                            if (emb.status === 'problema') {
                                barClass = 'problema';
                            } else if (progresso >= 100) {
                                barClass = 'concluido';
                            }

                            const progressHtml = `
            <div class="progress-destaque">
                <div class="progress-info">
                    <span class="progress-label"><i class="fa-solid fa-chart-simple"></i> Progresso</span>
                    <div class="progress-track">
                        <div class="progress-fill ${barClass}" style="width: ${progresso}%"></div>
                    </div>
                </div>
                <span class="progress-percent">${progresso}%</span>
            </div>
                            `;

        // ================================================================
        // GERAR HTML DAS ENTREGAS - VERSÃO PREMIUM
        // ================================================================
                            let entregasHtml = '';
                            if (entregasAtuais.length > 0) {
                                entregasHtml = `
                <div class="entregas-section">
                    <div class="entregas-header">
                        <h6 class="font-bold text-[#1a3c34] text-sm">
                            <i class="fa-solid fa-list mr-2" style="color:var(--nutri-accent);"></i> 
                            Entregas (${entregasAtuais.length})
                            <span class="text-xs font-normal text-slate-400">(arraste para reordenar)</span>
                        </h6>
                        <span class="text-xs text-slate-500">
                            <i class="fa-solid fa-route mr-1" style="color:#f59e0b;"></i> 
                            Total: ${distanciaTotal.toFixed(2)} km
                        </span>
                    </div>
                    <div id="lista-entregas-container">
                        ${entregasAtuais.map(function(e, index) {
                         const statusClasse = getStatusClass(e.status);
                         const statusIcon = getStatusIcon(e.status);
                         const statusLabel = getStatusLabel(e.status);
                         const temCheckout = e.status === 'entregue' || e.status === 'entregue com problema' || e.status === 'falha';
                         const temFotos = e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url));

                         const checkoutBadge = temCheckout ? `<span class="badge-entrega checkout"><i class="fa-solid fa-check-double"></i> Checkout</span>` : '';
                         const fotosBadge = temFotos ? `<span class="badge-entrega fotos"><i class="fa-regular fa-images"></i> 📸</span>` : '';
                         const recebedorInfo = e.nome_recebedor ? `<span class="badge-entrega recebedor"><i class="fa-solid fa-user"></i> ${e.nome_recebedor}</span>` : '';

                         let checklistInfo = '';
                         if (e.checklist && e.checklist.length > 0) {
                            const totalItens = e.checklist.length;
                            const entregues = e.checklist.filter(item => item.status === 'entregue').length;
                            const faltantes = e.checklist.filter(item => item.status === 'faltante').length;
                            const devolvidos = e.checklist.filter(item => item.status === 'devolvido').length;
                            const comFoto = e.checklist.filter(item => item.foto_url).length;

                            let statusText = '';
                            let statusClass = 'success';
                            let statusIcon = '✅';
                            if (faltantes > 0 || devolvidos > 0) {
                                statusText = `${faltantes} faltante${faltantes > 1 ? 's' : ''}${devolvidos > 0 ? `, ${devolvidos} devolvido${devolvidos > 1 ? 's' : ''}` : ''}`;
                                statusClass = 'danger';
                                statusIcon = '⚠️';
                                } else {
                                    statusText = `${entregues}/${totalItens} itens`;
                                    statusClass = 'success';
                                    statusIcon = '✅';
                                }

                                checklistInfo = `
                                    <div class="checklist-info">
                                        <span class="tag ${statusClass}"><i class="fa-solid fa-clipboard-list"></i> ${statusIcon} ${statusText}</span>
                                    ${comFoto > 0 ? `<span class="tag success"><i class="fa-regular fa-images"></i> ${comFoto} foto${comFoto > 1 ? 's' : ''}</span>` : ''}
                                        <button class="btn-ver-itens" onclick="event.stopPropagation(); event.preventDefault(); verItensCheckout(${e.id})">
                                            <i class="fa-solid fa-eye"></i> Ver Itens
                                        </button>
                                    </div>
                                    `;
                                }

                                let geoBadge = '';
                                if (e.origem_geolocalizacao) {
                                    const cls = e.origem_geolocalizacao === 'frota_cliente' ? 'frota_cliente' : 
                                    e.origem_geolocalizacao === 'google_maps' ? 'google_maps' : 'sem_geo';
                                    const label = e.origem_geolocalizacao === 'frota_cliente' ? 'GPS' : 
                                    e.origem_geolocalizacao === 'google_maps' ? 'Maps' : e.origem_geolocalizacao;
                                    geoBadge = '<span class="geo-badge ' + cls + '"><i class="fa-solid fa-location-dot"></i> ' + label + '</span>';
                                }

                                const distanciaStr = e.distancia_distribuidora !== null ? 
                                e.distancia_distribuidora.toFixed(1) + ' km' : '--';

                                const totalPedidos = parseInt(e.total_pedidos_agrupados) || 1;
                                const pedidosLabel = totalPedidos > 1 ? 
                                `<span class="pedido"><i class="fa-regular fa-file-lines"></i> ${totalPedidos} pedidos</span>` : 
                                (e.pedido_id ? `<span class="pedido"><i class="fa-regular fa-file-lines"></i> Pedido #${e.pedido_id}</span>` : '');

                                const codigoRastreamento = e.codigo_rastreamento ? `<span class="text-xs text-slate-400 ml-1">🔍 ${e.codigo_rastreamento}</span>` : '';

                                const btnCheckin = (e.status === 'pendente' || e.status === 'em_entrega') ? 
                                `<button class="btn-acao verde" onclick="event.stopPropagation(); registrarCheckin(${e.id})" title="Check-in"><i class="fa-solid fa-check"></i></button>` : '';

                                const btnCheckout = (e.status === 'pendente' || e.status === 'em_entrega') ? 
                                `<button class="btn-acao azul" onclick="event.stopPropagation(); registrarCheckout(${e.id})" title="Checkout"><i class="fa-solid fa-flag-checkered"></i></button>` : '';

                                const btnFalha = (e.status === 'pendente' || e.status === 'em_entrega') ? 
                                `<button class="btn-acao vermelho" onclick="event.stopPropagation(); registrarFalha(${e.id})" title="Falha"><i class="fa-solid fa-times"></i></button>` : '';

                                const btnFotos = (e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url))) ? 
                                `<button class="btn-acao azul" onclick="event.stopPropagation(); abrirGaleriaFotos(${e.id})" title="Ver fotos">
                                    <i class="fa-regular fa-images"></i>
                                    ${temCheckout ? '<i class="fa-solid fa-circle-check" style="color:#10b981;font-size:0.5rem;margin-left:-4px;"></i>' : ''}
                                </button>` : '';

                                return `
                                <div class="entrega-item ${entregaSelecionadaId === e.id ? 'ativa' : ''}" 
                                     data-id="${e.id}" data-lat="${e.latitude || ''}" data-lng="${e.longitude || ''}"
                                     onclick="selecionarEntregaNoMapa(${e.id})">
                                    <div class="drag-handle"><i class="fa-solid fa-grip-lines"></i></div>
                                    <span class="ordem">${index + 1}</span>
                                    <div class="info">
                                        <div class="cliente">
                                            ${e.cliente_nome || 'Cliente'} 
                                            ${codigoRastreamento}
                                            ${checkoutBadge}
                                            ${fotosBadge}
                                            ${recebedorInfo}
                                        </div>
                                        <div class="endereco">
                                            <i class="fa-solid fa-location-dot"></i>
                                            ${e.endereco || ''}${e.numero ? ', ' + e.numero : ''}${e.bairro ? ', ' + e.bairro : ''}${e.cidade ? ', ' + e.cidade : ''}${e.uf ? ', ' + e.uf : ''}
                                        </div>
                                        ${checklistInfo}
                                        <div class="detalhes">
                                            ${pedidosLabel}
                                    ${e.valor_total ? `<span class="valor"><i class="fa-regular fa-money-bill-1"></i> ${formatarMoeda(e.valor_total)}</span>` : ''}
                                    ${e.peso_total ? `<span class="peso"><i class="fa-regular fa-weight-scale"></i> ${formatarPeso(e.peso_total)}</span>` : ''}
                                            ${geoBadge}
                                        </div>
                                    </div>
                                    <div class="distancia"><i class="fa-solid fa-location-arrow"></i> ${distanciaStr}</div>
                                    <span class="status-mini ${statusClasse}">${statusIcon} ${statusLabel}</span>
                                    <button class="btn-mapa" onclick="event.stopPropagation(); centralizarNoMapa(${e.id})" title="Centralizar no mapa">
                                        <i class="fa-solid fa-crosshairs"></i>
                                    </button>
                                    <div class="actions">
                                        ${btnCheckin}
                                        ${btnCheckout}
                                        ${btnFalha}
                                        ${btnFotos}
                                    </div>
                                </div>
                                    `;
                                    }).join('')}
                    </div>
                    <div class="resumo-rota">
                        <div class="item"><i class="fa-solid fa-flag-checkered"></i> <span><span class="numero">${entregasAtuais.length}</span> entregas</span></div>
                        <div class="item"><i class="fa-solid fa-route"></i> <span>Distância total: <span class="distancia-total">${distanciaTotal.toFixed(2)} km</span></span></div>
                        <div class="item"><i class="fa-solid fa-clock"></i> <span>Previsto: ${distanciaTotal > 0 ? Math.round(distanciaTotal / 40 * 60) : 0} min</span></div>
                                    ${emb.total_embarques_agrupados > 1 ? `
                            <div class="item"><i class="fa-solid fa-layer-group"></i> <span><span class="numero">${emb.total_embarques_agrupados}</span> embarques agrupados</span></div>
                                        ` : ''}
                    </div>
                </div>
                                    `;
                                } else {
                                    entregasHtml = `<div class="text-center text-slate-400 py-8"><i class="fa-regular fa-box text-3xl block mb-3"></i>Nenhuma entrega vinculada</div>`;
                                }

        // ================================================================
        // HISTÓRICO DE AÇÕES - VERSÃO PREMIUM
        // ================================================================
                                let historicoHtml = '';
                                if (emb.historico && emb.historico.length > 0) {
                                    const acaoConfig = {
                                        'iniciar': { icone: 'fa-solid fa-play', cor: '#3b82f6', label: 'Embarque iniciado', bg: '#dbeafe', dotClass: 'iniciar' },
                                        'finalizar': { icone: 'fa-solid fa-flag-checkered', cor: '#10b981', label: 'Embarque finalizado', bg: '#d1fae5', dotClass: 'finalizar' },
                                        'cancelar': { icone: 'fa-solid fa-ban', cor: '#ef4444', label: 'Embarque cancelado', bg: '#fee2e2', dotClass: 'cancelar' },
                                        'checkin': { icone: 'fa-solid fa-location-dot', cor: '#f59e0b', label: 'Check-in realizado', bg: '#fef3c7', dotClass: 'checkin' },
                                        'checkout': { icone: 'fa-solid fa-check-double', cor: '#10b981', label: 'Checkout finalizado', bg: '#d1fae5', dotClass: 'checkout' },
                                        'falha': { icone: 'fa-solid fa-times-circle', cor: '#ef4444', label: 'Falha na entrega', bg: '#fee2e2', dotClass: 'falha' },
                                        'reagendar': { icone: 'fa-solid fa-calendar-plus', cor: '#f59e0b', label: 'Entrega reagendada', bg: '#fef3c7', dotClass: 'iniciar' },
                                        'corrigir_endereco': { icone: 'fa-solid fa-map-pin', cor: '#3b82f6', label: 'Endereço corrigido', bg: '#dbeafe', dotClass: 'checkin' },
                                        'remover_entrega': { icone: 'fa-solid fa-trash-alt', cor: '#ef4444', label: 'Entrega removida', bg: '#fee2e2', dotClass: 'cancelar' },
                                        'editar': { icone: 'fa-solid fa-pen', cor: '#8b5cf6', label: 'Embarque editado', bg: '#ede9fe', dotClass: 'iniciar' },
                                        'problema': { icone: 'fa-solid fa-triangle-exclamation', cor: '#f59e0b', label: 'Problema identificado', bg: '#fef3c7', dotClass: 'falha' },
                                        'resolver_problema': { icone: 'fa-solid fa-check-circle', cor: '#10b981', label: 'Problema resolvido', bg: '#d1fae5', dotClass: 'checkout' }
                                    };

                                    historicoHtml = `
                <div class="historico-section">
                    <div class="historico-header">
                        <h6 class="font-bold text-[#1a3c34] text-sm">
                            <i class="fa-solid fa-clock-rotate-left mr-2" style="color:var(--nutri-accent);"></i>
                            Histórico de Ações
                            <span class="text-xs font-normal text-slate-400">(${emb.historico.length} eventos)</span>
                        </h6>
                    </div>
                    <div class="historico-timeline">
                        ${emb.historico.map(function(log, index) {
                            const config = acaoConfig[log.acao] || { 
                                icone: 'fa-solid fa-circle', 
                                cor: '#94a3b8', 
                                label: log.acao || 'Ação',
                                bg: 'var(--nutri-border)',
                                dotClass: ''
                            };
                            const isUltimo = index === emb.historico.length - 1;
                            const isCheckout = log.acao === 'checkout';
                            
                            return `
                                <div class="timeline-item ${!isUltimo ? '' : 'last'}">
                                    <div class="dot ${config.dotClass}">
                                        <i class="${config.icone}"></i>
                                    </div>
                                    <div class="header">
                                        <span class="title">${config.label}</span>
                                        <span class="time"><i class="fa-regular fa-clock"></i> ${formatarDataHora(log.data_hora)}</span>
                                        <span class="user"><i class="fa-regular fa-user"></i> ${log.usuario_nome || 'Sistema'}</span>
                                    </div>
                                ${log.descricao && log.descricao !== log.acao ? `
                                        <div class="descricao">${log.descricao}</div>
                                    ` : ''}
                                    ${isCheckout ? `
                                        <div class="descricao" style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;">
                                            <span class="tag success"><i class="fa-solid fa-check"></i> Entrega concluída</span>
                                        ${log.descricao && log.descricao.includes('faltante') ? `<span class="tag warning"><i class="fa-solid fa-triangle-exclamation"></i> Itens faltantes</span>` : ''}
                                        ${log.descricao && log.descricao.includes('devolução') ? `<span class="tag danger"><i class="fa-solid fa-rotate-left"></i> Devoluções</span>` : ''}
                                        </div>
                                        ` : ''}
                                </div>
                                        `;
                                        }).join('')}
                    </div>
                </div>
                                    `;
                                }

        // ================================================================
        // MONTAR HTML COMPLETO DO MODAL - VERSÃO PREMIUM
        // ================================================================
                                const container = document.getElementById('detalhes-conteudo');
                                container.innerHTML = `
            ${infoCardsHtml}
            ${progressHtml}
            <hr style="margin:20px 0;border:0;border-top:2px solid var(--nutri-border);">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="left-column">
                    ${entregasHtml}
                    ${historicoHtml}
                </div>
                <div class="right-column">
                    <div class="mapa-header">
                        <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                            <i class="fa-solid fa-map mr-2" style="color:var(--nutri-accent);"></i> 
                            Mapa da Rota
                            <span class="text-xs font-normal text-slate-400">(clique na entrega para ver detalhes)</span>
                        </h6>
                    </div>
                    <div id="mapa-rota"></div>
                </div>
            </div>
                                `;

        // ================================================================
        // INICIALIZAR MAPA E SORTABLE
        // ================================================================
                                setTimeout(function() { inicializarMapaRota(entregasAtuais); }, 300);
                                setTimeout(function() { initSortable(); }, 500);

        // ================================================================
        // ABRIR MODAL
        // ================================================================
                                if (typeof $ !== 'undefined' && $.fn.modal) {
                                    $('#modalDetalhes').modal('show');
                                } else {
                                    const el = document.getElementById('modalDetalhes');
                                    el.style.display = 'block';
                                    el.classList.add('show');
                                    document.body.classList.add('modal-open');
                                    if (!document.querySelector('.modal-backdrop')) {
                                        const b = document.createElement('div');
                                        b.className = 'modal-backdrop fade show';
                                        document.body.appendChild(b);
                                    }
                                }
                            } catch (error) {
                                mostrarNotificacao('Erro ao carregar detalhes do embarque: ' + error.message, 'error');
                            }
                        }

// ======================================================================
// ABRIR GALERIA DE FOTOS
// ======================================================================
                        async function abrirGaleriaFotos(entregaId) {
                            const token = getAuthToken();
                            try {
                                const resp = await fetch(`/v1/frota/entregas/${entregaId}`, {
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

                                Swal.fire({
                                    title: '📸 Fotos da Entrega',
                                    html: html,
                                    width: '800px',
                                    showConfirmButton: true,
                                    confirmButtonText: 'Fechar',
                                    confirmButtonColor: '#10b981',
                                    customClass: {
                                        popup: 'galeria-fotos-modal'
                                    }
                                });

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
// MAPA
// ======================================================================
                        function inicializarMapaRota(entregas) {
                            const container = document.getElementById('mapa-rota');
                            if (!container) return;
                            if (mapaRota) { mapaRota.remove();
                            mapaRota = null;
                            rotaMarkers = [];
                            rotaPolyline = null; }

                            const pontos = [{ lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG, tipo: 'distribuidora' }];
                            entregas.forEach(function(e) {
                                if (e.latitude && e.longitude) pontos.push({ lat: e.latitude, lng: e.longitude, tipo: 'entrega', entrega: e });
                            });

                            if (pontos.length === 1) {
                                container.innerHTML = `<div class="flex items-center justify-center h-[450px] text-slate-400">
            <div class="text-center"><i class="fa-regular fa-map text-3xl block mb-2"></i><p>Sem coordenadas para exibir</p></div>
                            </div>`;
                            return;
                        }

                        mapaRota = L.map(container, { center: [DISTRIBUIDORA_LAT, DISTRIBUIDORA_LNG], zoom: 12, zoomControl: false });
                        L.control.zoom({ position: 'topright' }).addTo(mapaRota);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
                            maxZoom: 19
                        }).addTo(mapaRota);

                        const latLngs = pontos.map(function(p) { return [p.lat, p.lng]; });
                        rotaPolyline = L.polyline(latLngs, { color: '#1a3c34', weight: 4, opacity: 0.7, dashArray: '8, 6' }).addTo(mapaRota);

                        const distribuidoraIcon = L.divIcon({
                            className: 'marker-distribuidora',
                            html: '<i class="fa-solid fa-building" style="color:#1a3c34; font-size:22px;"></i>',
                            iconSize: [44, 44],
                            iconAnchor: [22, 22]
                        });
                        L.marker([DISTRIBUIDORA_LAT, DISTRIBUIDORA_LNG], { icon: distribuidoraIcon })
                        .addTo(mapaRota)
                        .bindPopup(`<div style="font-family:Inter;padding:6px;"><p class="font-bold text-[#1a3c34]">🏢 Nutricional Distribuidora</p><p class="text-sm">${DISTRIBUIDORA_ENDERECO}</p><p class="text-xs text-slate-400">Ponto de partida</p></div>`);

                        entregas.forEach(function(e, index) {
                            if (!e.latitude || !e.longitude) return;
                            const isAtiva = entregaSelecionadaId === e.id;
                            const entregaIcon = L.divIcon({
                                className: 'marker-entrega ' + (isAtiva ? 'ativa' : '') + ' ' + (e.status || 'pendente'),
                                html: '' + (index + 1),
                                iconSize: [34, 34],
                                iconAnchor: [17, 17]
                            });
                            const marker = L.marker([e.latitude, e.longitude], { icon: entregaIcon })
                            .addTo(mapaRota)
                            .bindPopup(`
                <div style="font-family:Inter;padding:6px;min-width:200px;">
                    <p class="font-bold">${index + 1}. ${e.cliente_nome || 'Cliente'}</p>
                    <p class="text-sm">${e.endereco || ''}${e.numero ? ', ' + e.numero : ''}</p>
                    <p class="text-sm text-slate-500">${e.bairro ? e.bairro + ', ' : ''}${e.cidade || ''}${e.uf ? ', ' + e.uf : ''}</p>
                    <hr style="margin:4px 0;">
                    ${e.pedido_id ? '<p class="text-xs"><strong>Pedido:</strong> #' + e.pedido_id + '</p>' : ''}
                    ${e.valor_total ? '<p class="text-xs"><strong>Valor:</strong> ' + formatarMoeda(e.valor_total) + '</p>' : ''}
                    ${e.peso_total ? '<p class="text-xs"><strong>Peso:</strong> ' + formatarPeso(e.peso_total) + '</p>' : ''}
                                ${e.origem_geolocalizacao ? `<p class="text-xs mt-1"><span class="geo-badge ${e.origem_geolocalizacao === 'frota_cliente' ? 'frota_cliente' : 'google_maps'}"><i class="fa-solid fa-location-dot"></i> ${e.origem_geolocalizacao}</span></p>` : ''}
                    <p class="text-xs text-slate-400 mt-1">Status: ${e.status || 'pendente'}</p>
                </div>
                            `);
                            marker.on('click', function() { selecionarEntregaNoMapa(e.id); });
                            rotaMarkers.push(marker);
                        });

                        const allPoints = pontos.map(function(p) { return [p.lat, p.lng]; });
                        mapaRota.fitBounds(L.latLngBounds(allPoints), { padding: [50, 50] });
                    }

// ======================================================================
// AGRUPAR EMBARQUES
// ======================================================================
                    function agruparEmbarques(embarques) {
                        if (!embarques || embarques.length === 0) return [];

                        const grupos = {};

                        embarques.forEach(emb => {
                            if (emb.total_embarques_agrupados && emb.total_embarques_agrupados > 1) {
                                const chave = 'grupo_' + emb.id;
                                if (!grupos[chave]) {
                                    grupos[chave] = {
                                        veiculo_id: emb.veiculo_id,
                                        veiculo_placa: emb.veiculo_placa || 'Não definido',
                                        veiculo_modelo: emb.veiculo_modelo || '',
                                        motorista_id: emb.motorista_id,
                                        motorista_nome: emb.motorista_nome || 'Não definido',
                                        embarques: [emb],
                                        total_entregas: emb.total_entregas || 0,
                                        entregas_concluidas: emb.entregas_concluidas || 0,
                                        valor_total: emb.valor_total_entregas || 0,
                                        peso_total: emb.peso_total_entregas || 0,
                                        total_embarques: emb.total_embarques_agrupados,
                                        erp_ids: emb.erp_ids_agrupados ? emb.erp_ids_agrupados.split(',') : [],
                                        status: emb.status
                                    };
                                }
                                return;
                            }

                            const chave = `${emb.veiculo_id || 'sem_veiculo'}_${emb.motorista_id || 'sem_motorista'}`;
                            if (!grupos[chave]) {
                                grupos[chave] = {
                                    veiculo_id: emb.veiculo_id,
                                    veiculo_placa: emb.veiculo_placa || 'Não definido',
                                    veiculo_modelo: emb.veiculo_modelo || '',
                                    motorista_id: emb.motorista_id,
                                    motorista_nome: emb.motorista_nome || 'Não definido',
                                    embarques: [],
                                    total_entregas: 0,
                                    entregas_concluidas: 0,
                                    valor_total: 0,
                                    peso_total: 0,
                                    total_embarques: 0,
                                    erp_ids: [],
                                    status: 'planejado'
                                };
                            }
                            grupos[chave].embarques.push(emb);
                            grupos[chave].total_entregas += parseInt(emb.total_entregas) || 0;
                            grupos[chave].entregas_concluidas += parseInt(emb.entregas_concluidas) || 0;
                            grupos[chave].valor_total += parseFloat(emb.valor_total_entregas) || 0;
                            grupos[chave].peso_total += parseFloat(emb.peso_total_entregas) || 0;
                            grupos[chave].total_embarques++;
                            if (emb.erp_embarque_id) {
                                grupos[chave].erp_ids.push(emb.erp_embarque_id);
                            }
                            if (emb.status !== 'planejado') {
                                grupos[chave].status = 'em_andamento';
                            }
                        });

                        return Object.values(grupos);
                    }

// ======================================================================
// SORTABLE
// ======================================================================
                    function initSortable() {
                        const container = document.getElementById('lista-entregas-container');
                        if (!container) return;
                        new Sortable(container, {
                            animation: 150,
                            handle: '.drag-handle',
                            onStart: function(e) { e.item.classList.add('dragging'); },
                            onEnd: function(e) {
                                e.item.classList.remove('dragging');
                                atualizarOrdemAposDrag();
                            }
                        });
                    }

                    function atualizarOrdemAposDrag() {
                        const items = document.querySelectorAll('.entrega-item');
                        const novaOrdem = [];
                        items.forEach(function(item, index) {
                            const id = parseInt(item.dataset.id);
                            novaOrdem.push(id);
                            const ordemSpan = item.querySelector('.ordem');
                            if (ordemSpan) ordemSpan.textContent = index + 1;
                        });
                        const novaLista = [];
                        novaOrdem.forEach(function(id) {
                            const entrega = entregasAtuais.find(function(e) { return e.id === id; });
                            if (entrega) novaLista.push(entrega);
                        });
                        entregasAtuais = novaLista;
                        recalcularDistanciaTotal();
                        atualizarPolilinhaRota();
                        salvarOrdemEntregas(embarqueIdDetalhes, novaOrdem);
                    }

                    function recalcularDistanciaTotal() {
                        let total = 0;
                        let ultimo = { lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG };
                        entregasAtuais.forEach(function(e) {
                            if (e.latitude && e.longitude) {
                                const d = calcularDistancia(ultimo.lat, ultimo.lng, e.latitude, e.longitude);
                                if (d !== null) total += d;
                                ultimo.lat = e.latitude;
                                ultimo.lng = e.longitude;
                            }
                        });
                        const el = document.querySelector('.distancia-total');
                        if (el) el.textContent = total.toFixed(2) + ' km';
                    }

                    function atualizarPolilinhaRota() {
                        if (!mapaRota) return;
                        if (rotaPolyline) mapaRota.removeLayer(rotaPolyline);
                        const pontos = [{ lat: DISTRIBUIDORA_LAT, lng: DISTRIBUIDORA_LNG }];
                        entregasAtuais.forEach(function(e) {
                            if (e.latitude && e.longitude) pontos.push({ lat: e.latitude, lng: e.longitude });
                        });
                        const latLngs = pontos.map(function(p) { return [p.lat, p.lng]; });
                        rotaPolyline = L.polyline(latLngs, { color: '#1a3c34', weight: 4, opacity: 0.7, dashArray: '8, 6' }).addTo(mapaRota);
                    }

                    async function salvarOrdemEntregas(embarqueId, novaOrdem) {
                        const token = getAuthToken();
                        if (!token) return;
                        try {
                            await fetch('/v1/frota/embarques/' + embarqueId + '/reordenar', {
                                method: 'POST',
                                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                                body: JSON.stringify({ ordem: novaOrdem })
                            });
                        } catch (e) {}
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
                            const response = await fetch('/v1/frota/embarques/' + id + '/otimizar-rota', {
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
                                const resp = await fetch(`/v1/frota/embarques/${embarqueId}`, {
                                    headers: { 'Authorization': 'Bearer ' + token }
                                });
                                const data = await resp.json();
                                if (data.success && data.data.entregas) {
                                    entrega = data.data.entregas.find(e => e.id === entregaId);
                                }
                            }

                            if (!entrega) {
                                const resp = await fetch(`/v1/frota/entregas/${entregaId}`, {
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
// CRIAR ROTAS SELECIONADAS - CORRIGIDO
// ======================================================================
async function criarRotasSelecionadas() {
    if (embarquesSelecionados.length === 0) {
        Swal.fire('Atenção', 'Selecione pelo menos um embarque', 'warning');
        return;
    }

    const token = getAuthToken();
    if (!token) {
        window.location.href = '/portal/login.php';
        return;
    }

    const totalSelecionados = embarquesSelecionados.length;
    const isMultiplo = totalSelecionados > 1;

    console.log('📌 Embarques selecionados:', embarquesSelecionados);
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

        for (const id of embarquesSelecionados) {
            const response = await fetch(`/v1/frota/importar/embarque-detalhes/${id}`, {
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
            fetch('/v1/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch('/v1/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
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
                fetch('/v1/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
                fetch('/v1/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
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
        payload.ids_agrupados = embarquesSelecionados;
        // 🔥 IMPORTANTE: NÃO enviar id_embarque_erp quando for múltiplo
    } else {
        // Para um único embarque, usar id_embarque_erp
        payload.id_embarque_erp = embarquesSelecionados[0];
    }

    console.log('📤 Payload enviado:', payload);

    // 🔥 SPINNER MELHORADO
    mostrarSpinner(
        isMultiplo ? 'Criando grupo de embarques...' : 'Criando rota...',
        isMultiplo ? `Consolidando ${totalSelecionados} embarques...` : 'Processando...',
        0
    );

    try {
        const response = await fetch('/v1/frota/importar/criar-embarque', {
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
                                            const r = await fetch('/v1/frota/motoristas', {
                                                method: 'POST',
                                                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                                                body: JSON.stringify(m)
                                            });
                                            resultados.push(await r.json());
                                        }
                                        for (const v of veiculosParaCadastrar) {
                                            const r = await fetch('/v1/frota/veiculos', {
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
        const resp = await fetch(`/v1/frota/embarques/${primeiroId}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        if (!data.success) {
            Swal.fire('Erro', data.error || 'Falha ao carregar dados', 'error');
            return;
        }
        const emb = data.data;

        const [respVeiculos, respMotoristas] = await Promise.all([
            fetch('/v1/frota/veiculos?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } }),
            fetch('/v1/frota/motoristas?limite=1000', { headers: { 'Authorization': 'Bearer ' + token } })
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

        let todasEntregas = [];
        for (const id of sistemaIds) {
            const respEntrega = await fetch(`/v1/frota/embarques/${id}`, {
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

        const entregasHtml = todasEntregas.length > 0 ? todasEntregas.map(e => {
            const statusLabel = e.status || 'pendente';
            const statusColor = statusLabel === 'entregue' ? 'bg-green-100 text-green-700' :
            statusLabel === 'falha' ? 'bg-red-100 text-red-700' :
            statusLabel === 'em_entrega' ? 'bg-yellow-100 text-yellow-700' :
            'bg-blue-100 text-blue-700';

            const temFotos = e.foto_romaneio_url || (e.checklist && e.checklist.length > 0 && e.checklist.some(item => item.foto_url));
            const fotosBadge = temFotos ? '<span class="fotos-badge"><i class="fa-regular fa-images"></i> 📸</span>' : '';

            const temCheckout = statusLabel === 'entregue' || statusLabel === 'entregue_com_problema' || statusLabel === 'falha';
            const checkoutBadge = temCheckout ? '<span class="checkout-badge"><i class="fa-solid fa-check-double"></i> ✅</span>' : '';

            return `
                <div class="entrega-edit-item" data-entrega-id="${e.id}" data-embarque-id="${e.embarque_id}">
                    <div class="entrega-edit-info">
                        <div class="cliente">
                            ${e.cliente_nome || 'Cliente'}
                            ${fotosBadge}
                            ${checkoutBadge}
                        </div>
                        <div class="detalhes">
                            <span class="pedido">💰 ${formatarMoeda(e.valor || 0)}</span>
                            <span class="peso">⚖️ ${formatarPeso(e.peso_total || 0)}</span>
                            <span class="status ${statusColor}">${statusLabel}</span>
                            <span class="embarque-ref">📦 Embarque #${e.embarque_id}</span>
                        </div>
                    </div>
                    <button class="btn-remover-entrega-edit" data-entrega-id="${e.id}" data-embarque-id="${e.embarque_id}" title="Remover esta entrega">
                        <i class="fa-solid fa-trash-can"></i>
                    </button>
                </div>
            `;
        }).join('') : '<p class="text-muted">Nenhuma entrega</p>';

        const modalHtml = `
            <div class="edit-modal-container">
                <div class="edit-modal-header">
                    <h3><i class="fa-solid fa-pen-to-square"></i> Editar Grupo (${sistemaIds.length} embarque${sistemaIds.length > 1 ? 's' : ''})</h3>
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
                            <label>Data/Hora Saída</label>
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
                            <h5><i class="fa-solid fa-list"></i> Entregas do Grupo (${todasEntregas.length})</h5>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                <button class="btn-add-embarque" id="btn-adicionar-embarque-erp" style="background: #3b82f6; color: white; border: none; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; cursor: pointer;">
                                    <i class="fa-solid fa-plus"></i> Adicionar Embarque ERP
                                </button>
                                <button class="btn-add-pedidos" id="btn-adicionar-pedidos" style="background: #10b981; color: white; border: none; padding: 4px 12px; border-radius: 8px; font-size: 0.75rem; cursor: pointer;">
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
                    <button class="btn-secondary" id="btn-cancelar-editar">Cancelar</button>
                    <button class="btn-primary" id="btn-salvar-editar">💾 Salvar</button>
                </div>
            </div>
        `;

        // Estilos já estão definidos no código original

        await Swal.fire({
            title: '',
            html: modalHtml,
            showConfirmButton: false,
            showCancelButton: false,
            allowOutsideClick: false,
            width: '800px',
            customClass: {
                popup: 'edit-modal-popup'
            },
            didOpen: () => {
                document.getElementById('btn-cancelar-editar').addEventListener('click', () => {
                    Swal.close();
                });

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
                    
                    const payload = { nome_embarque: nome, veiculo_id: veiculo, motorista_id: motorista, data_saida: data, status };
                    let sucessos = 0;
                    
                    for (const id of sistemaIds) {
                        const respUpdate = await fetch(`/v1/frota/embarques/${id}`, {
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
                            const respDel = await fetch(`/v1/frota/embarques/${embId}/entregas/${entregaId}`, {
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

                document.getElementById('btn-adicionar-embarque-erp')?.addEventListener('click', async () => {
                    // ... código para adicionar embarque ERP (mantido do original)
                    try {
                        Swal.fire({
                            title: 'Carregando embarques ERP...',
                            didOpen: () => Swal.showLoading(),
                            allowOutsideClick: false
                        });

                        const respErp = await fetch('/v1/frota/importar/embarques-erp', {
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

                        const respAdd = await fetch(`/v1/frota/embarques/${sistemaIds[0]}/adicionar-embarque-erp`, {
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

                document.getElementById('btn-adicionar-pedidos')?.addEventListener('click', async () => {
                    // ... código para adicionar pedidos (mantido do original)
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
                                    const resp = await fetch(`/v1/frota/importar/buscar-pedidos?q=${encodeURIComponent(termo)}`, {
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
                            const respAdd = await fetch(`/v1/frota/embarques/${sistemaIds[0]}/adicionar-pedidos`, {
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
            const resp = await fetch(`/v1/frota/embarques/${id}`, {
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

        const respDel = await fetch(`/v1/frota/embarques/${embId}/entregas/${entregaId}`, {
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
// EXPORTAÇÕES GLOBAIS
// ======================================================================
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
window.selecionarEntregaNoMapa = selecionarEntregaNoMapa;
window.centralizarNoMapa = centralizarNoMapa;
window.toggleTheme = toggleTheme;
window.mostrarNotificacao = mostrarNotificacao;
window.exportarRota = exportarRota;
window.abrirGaleriaFotos = abrirGaleriaFotos;
window.abrirZoomFoto = abrirZoomFoto;
window.fecharZoom = fecharZoom;