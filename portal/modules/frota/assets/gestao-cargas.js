// ======================================================================
// GESTÃO DE CARGAS - SCRIPT COMPLETO
// ======================================================================

// ================================================================
// CONFIGURAÇÕES
// ================================================================
// Respeita window.API_URL definido em /portal/assets/js/config.js,
// que já trata corretamente ambiente local (pasta /API) vs produção.
const apiUrl = window.API_URL || '/';
const CONFIG = {
    API_BASE: apiUrl + (apiUrl.endsWith('/') || apiUrl.endsWith('=') ? '' : '/') + 'frota',
    CACHE_VALIDADE: 60000,
    LIMITE_PADRAO: 25,
    DEBOUNCE_DELAY: 400
};

// ================================================================
// ESTADO GLOBAL
// ================================================================
let state = {
    paginaAtual: 1,
    totalPaginas: 1,
    totalRegistros: 0,
    limitePorPagina: CONFIG.LIMITE_PADRAO,
    filtroStatus: 'todos',
    filtroPrioridade: 'todas',
    filtroBusca: '',
    dadosProblemas: [],
    entregaSelecionada: null,
    chartInstance: null,
    modalInstance: null
};

// ================================================================
// CACHE
// ================================================================
let cache = {
    dados: null,
    timestamp: null,
    validade: CONFIG.CACHE_VALIDADE
};

// ================================================================
// FUNÇÕES AUXILIARES
// ================================================================
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
    }).format(valor || 0);
}

function getPriorityLabel(prioridade) {
    const labels = {
        'baixa': '🟢 Baixa',
        'media': '🟡 Média',
        'alta': '🟠 Alta',
        'critica': '🔴 Crítica'
    };
    return labels[prioridade] || prioridade;
}

function getStatusLabel(status) {
    const labels = {
        'pendente': '⏳ Pendente',
        'em_analise': '🔍 Em Análise',
        'resolvido': '✅ Resolvido',
        'cancelado': '🚫 Cancelado'
    };
    return labels[status] || status;
}

function getTipoLabel(tipo) {
    const labels = {
        'faltante': '⚠️ Faltante',
        'devolucao': '🔄 Devolução',
        'avaria': '💥 Avaria',
        'extraviado': '❓ Extraviado',
        'outro': '📌 Outro'
    };
    return labels[tipo] || tipo;
}

function mostrarNotificacao(mensagem, tipo = 'info') {
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

// ================================================================
// SPINNER
// ================================================================
function mostrarSpinner(texto, subtexto, progresso = 0) {
    fecharSpinner();

    const overlay = document.createElement('div');
    overlay.id = 'spinner-overlay';
    overlay.className = 'spinner-overlay';
    overlay.innerHTML = `
        <div class="spinner-container">
            <div class="spinner"></div>
            <div class="spinner-text">${texto || 'Carregando...'}</div>
            ${subtexto ? `<div class="spinner-subtext">${subtexto}</div>` : ''}
            <div class="progress-bar-container">
                <div class="progress-fill" style="width: ${Math.min(100, Math.max(0, progresso))}%"></div>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
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
        subtextEl.textContent = subtexto || '';
        subtextEl.style.display = subtexto ? 'block' : 'none';
    }
    if (progressEl && progresso !== undefined) {
        progressEl.style.width = Math.min(100, Math.max(0, progresso)) + '%';
    }
}

function fecharSpinner() {
    const overlay = document.getElementById('spinner-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        overlay.style.transition = 'opacity 0.3s ease';
        setTimeout(() => overlay.remove(), 300);
    }
    document.body.style.overflow = '';
}

// ================================================================
// TEMA
// ================================================================
function toggleTheme() {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const newTheme = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    const icon = document.querySelector('.theme-toggle i');
    if (icon) icon.className = newTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
}

// ================================================================
// CARREGAR DADOS
// ================================================================
async function carregarDados(forceRefresh = false) {
    const token = getAuthToken();
    if (!token) return;

    const agora = Date.now();
    if (!forceRefresh && cache.dados && (agora - cache.timestamp) < cache.validade) {
        renderizarDados(cache.dados);
        return;
    }

    const status = state.filtroStatus;
    const busca = state.filtroBusca;
    const prioridade = state.filtroPrioridade;

  let url = `${CONFIG.API_BASE}/gestao-cargas/problemas?pagina=${state.paginaAtual}&limite=${state.limitePorPagina}`;
   if (status && status !== 'todos') url += `&status=${status}`;
    if (busca) url += `&busca=${encodeURIComponent(busca)}`;
    if (prioridade && prioridade !== 'todas') url += `&prioridade=${prioridade}`;

    try {
        const response = await fetch(url, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (response.status === 401) {
            const base = window.location.pathname.startsWith('/API/') ? '/API' : '';
            window.location.href = base + '/portal/login.php';
            return;
        }

        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                cache.dados = dados;
                cache.timestamp = Date.now();
                renderizarDados(dados);
                carregarKPIs();
                carregarKPIsOperacionais();
            }
        }
    } catch (error) {
        console.error('Erro ao carregar dados:', error);
        mostrarNotificacao('Erro ao carregar dados', 'error');
    }
}

async function carregarKPIsOperacionais() {
    const token = getAuthToken();
    if (!token) return;
    try {
        const response = await fetch(`${CONFIG.API_BASE}/dashboard/kpis`, { headers: { 'Authorization': 'Bearer ' + token } });
        if (!response.ok) return;
        const payload = await response.json();
        if (payload.success) renderizarKPIsOperacionais(payload.data || {});
    } catch (error) {
        const container = document.getElementById('operational-kpis');
        if (container) container.innerHTML = '<div class="operational-loading">Indicadores operacionais indisponíveis.</div>';
    }
}

function renderizarKPIsOperacionais(kpis) {
    const container = document.getElementById('operational-kpis');
    if (!container) return;
    const cards = [
        ['embarques_ativos', 'Embarques ativos'],
        ['entregas_hoje', 'Entregas hoje'],
        ['taxa_entrega_hoje', 'Taxa de entrega', '%'],
        ['motoristas_em_rota', 'Motoristas em rota'],
        ['veiculos_em_rota', 'Veículos em rota'],
        ['faturamento_mes', 'Faturamento do mês', 'money']
    ];
    container.innerHTML = cards.map(([key, label, format]) => {
        const value = format === 'money' ? formatarMoeda(kpis[key]) : `${kpis[key] || 0}${format || ''}`;
        return `<div class="operational-kpi"><strong>${value}</strong><span>${label}</span></div>`;
    }).join('');
}

// ================================================================
// RENDERIZAR DADOS
// ================================================================
function renderizarDados(dados) {
    state.dadosProblemas = Array.isArray(dados.data) ? dados.data : [];
    renderizarTabela(state.dadosProblemas);
    renderizarPaginacao(dados.pagination);
}

function renderizarTabela(problemas) {
    const tbody = document.getElementById('lista-problemas');

    if (!problemas || problemas.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="empty-state-cargas">
                    <i class="fa-regular fa-circle-check text-3xl block mb-2"></i>
                    Nenhum problema encontrado
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    problemas.forEach((p, index) => {
        const prioridadeClass = p.prioridade || 'media';
        const statusClass = p.status_problema || 'pendente';
        const tipoClass = p.tipo_problema || 'outro';
        const bgRow = p.status_problema === 'resolvido' ? 'row-status-resolvido' : 'row-status-pendente';

        html += `
            <tr class="${bgRow}">
                <td class="text-center font-bold text-slate-400" data-label="#">${index + 1}</td>
                <td data-label="Entrega">
                    <div class="font-bold text-[#1a3c34]">#${p.entrega_id || '-'}</div>
                    <div class="text-xs text-slate-400">${formatarDataHora(p.data_problema)}</div>
                </td>
                <td data-label="Cliente">
                    <div class="font-medium">${p.cliente_nome || '-'}</div>
                    <div class="text-xs text-slate-400">${p.cidade || ''}${p.uf ? ', ' + p.uf : ''}</div>
                </td>
                <td data-label="Motorista">
                    <div class="font-medium">${p.motorista_nome || '-'}</div>
                    <div class="text-xs text-slate-400">${p.veiculo_placa || ''}</div>
                </td>
                <td data-label="Problema">
                    <div class="flex flex-col gap-1">
                        <span class="tipo-problema-badge ${tipoClass}">${getTipoLabel(p.tipo_problema)}</span>
                        <span class="text-xs text-slate-500">${p.referencia || ''}</span>
                    </div>
                </td>
                <td class="text-center" data-label="Qtd">
                    <span class="font-bold">${p.quantidade_afetada || 0}</span>
                </td>
                <td class="text-center" data-label="Valor">
                    <span class="font-medium text-emerald-600">${formatarMoeda(p.valor_afetado)}</span>
                </td>
                <td class="text-center" data-label="Prioridade">
                    <span class="priority-badge ${prioridadeClass}">${getPriorityLabel(p.prioridade)}</span>
                </td>
                <td class="text-center" data-label="Status">
                    <span class="status-problema ${statusClass}">${getStatusLabel(p.status_problema)}</span>
                </td>
                <td class="text-center" data-label="Ações">
                    <div class="flex items-center justify-center gap-1">
                        <button class="btn-icone azul" onclick="verAnalise(${p.entrega_id})" title="Ver análise">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        ${p.status_problema !== 'resolvido' ? `
                            <button class="btn-icone verde" onclick="resolverProblema(${p.id})" title="Resolver">
                                <i class="fa-solid fa-check"></i>
                            </button>
                        ` : ''}
                        ${p.status_problema === 'pendente' ? `
                            <button class="btn-icone amber" onclick="iniciarAnalise(${p.id})" title="Iniciar análise">
                                <i class="fa-solid fa-play"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function renderizarPaginacao(pagination) {
    if (!pagination) return;
    state.totalPaginas = pagination.total_paginas || 1;
    state.totalRegistros = pagination.total || 0;

    document.getElementById('info-registros').textContent =
        `${state.totalRegistros} registros • Página ${pagination.pagina || 1} de ${state.totalPaginas}`;
    document.getElementById('info-paginacao').textContent =
        `Mostrando ${(pagination.pagina - 1) * state.limitePorPagina + 1} - ${Math.min(pagination.pagina * state.limitePorPagina, state.totalRegistros)} de ${state.totalRegistros}`;
    document.getElementById('pagina-atual').textContent = pagination.pagina || 1;
    state.paginaAtual = pagination.pagina || 1;
}

// ================================================================
// KPI CARDS
// ================================================================
async function carregarKPIs() {
    const token = getAuthToken();
    if (!token) return;

    try {
        // 🔥 ROTA CORRETA: /gestao-cargas/kpis-problemas
        const response = await fetch(`${CONFIG.API_BASE}/gestao-cargas/kpis-problemas`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (response.ok) {
            const dados = await response.json();
            if (dados.success) {
                renderizarKPIs(dados.data);
            }
        } else {
            // Fallback: tentar a rota alternativa
            const responseFallback = await fetch(`${CONFIG.API_BASE}/dashboard/kpis-problemas`, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            if (responseFallback.ok) {
                const dados = await responseFallback.json();
                if (dados.success) {
                    renderizarKPIs(dados.data);
                }
            }
        }
    } catch (error) {
        console.error('Erro ao carregar KPIs:', error);
        mostrarNotificacao('Erro ao carregar indicadores', 'warning');
    }
}

function renderizarKPIs(kpis) {
    const container = document.getElementById('kpi-cards');
    if (!container) return;

    const cards = [
        { id: 'total', label: 'Total Problemas', value: kpis.total || 0, icon: 'fa-triangle-exclamation', cor: 'primary' },
        { id: 'pendentes', label: 'Pendentes', value: kpis.pendentes || 0, icon: 'fa-clock', cor: 'warning' },
        { id: 'em_analise', label: 'Em Análise', value: kpis.em_analise || 0, icon: 'fa-magnifying-glass', cor: 'info' },
        { id: 'resolvidos', label: 'Resolvidos', value: kpis.resolvidos || 0, icon: 'fa-check-circle', cor: 'success' },
        { id: 'faltantes', label: 'Faltantes', value: kpis.faltantes || 0, icon: 'fa-box-open', cor: 'danger' },
        { id: 'devolucoes', label: 'Devoluções', value: kpis.devolucoes || 0, icon: 'fa-rotate-left', cor: 'purple' }
    ];

    let html = '';
    cards.forEach(card => {
        html += `
            <div class="kpi-card ${card.cor}">
                <div class="flex items-center gap-4">
                    <div class="kpi-icon">
                        <i class="fa-solid ${card.icon}"></i>
                    </div>
                    <div>
                        <div class="kpi-value" id="kpi-${card.id}">${card.value}</div>
                        <div class="kpi-label">${card.label}</div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// ================================================================
// FILTROS
// ================================================================
function aplicarFiltro(status, btnEl) {
    state.filtroStatus = status;
    state.paginaAtual = 1;
    cache.dados = null;
    cache.timestamp = null;

    document.querySelectorAll('.quick-filter-pill').forEach(pill => {
        pill.classList.remove('active');
    });
    if (btnEl) btnEl.classList.add('active');

    carregarDados();
}

function aplicarPrioridade(prioridade) {
    state.filtroPrioridade = prioridade;
    state.paginaAtual = 1;
    cache.dados = null;
    cache.timestamp = null;
    atualizarAcaoLimparFiltros();
    carregarDados();
}

function atualizarAcaoLimparFiltros() {
    const button = document.getElementById('limpar-filtros');
    if (button) button.hidden = state.filtroStatus === 'todos' && state.filtroPrioridade === 'todas' && !state.filtroBusca;
}

function limparFiltros() {
    state.filtroStatus = 'todos';
    state.filtroPrioridade = 'todas';
    state.filtroBusca = '';
    state.paginaAtual = 1;
    cache.dados = null;
    cache.timestamp = null;
    const input = document.getElementById('filtro-busca');
    const select = document.getElementById('filtro-prioridade');
    if (input) input.value = '';
    if (select) select.value = 'todas';
    document.querySelectorAll('.quick-filter-pill').forEach(pill => pill.classList.toggle('active', pill.dataset.filtro === 'todos'));
    atualizarAcaoLimparFiltros();
    carregarDados();
}

function mudarPagina(direcao) {
    if (direcao === 'anterior' && state.paginaAtual > 1) state.paginaAtual--;
    else if (direcao === 'proximo' && state.paginaAtual < state.totalPaginas) state.paginaAtual++;
    cache.dados = null;
    cache.timestamp = null;
    carregarDados();
}

// ================================================================
// VER ANÁLISE DA ENTREGA
// ================================================================
async function verAnalise(entregaId) {
    state.entregaSelecionada = entregaId;
    const token = getAuthToken();
    if (!token) return;

    mostrarSpinner('Carregando análise...', 'Buscando dados da entrega', 30);

    try {
        const response = await fetch(`${CONFIG.API_BASE}/entregas/${entregaId}/analise`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });

        if (!response.ok) throw new Error('HTTP ' + response.status);

        const dados = await response.json();
        if (!dados.success) throw new Error(dados.error || 'Erro desconhecido');

        atualizarSpinner('Processando dados...', 'Montando análise', 60);

        const entrega = dados.data;

        // Montar HTML do modal
        const container = document.getElementById('analise-conteudo');
        container.innerHTML = montarHtmlAnalise(entrega);

        document.getElementById('analise-numero').textContent = '# ' + (entrega.id || entregaId);

        atualizarSpinner('Finalizando...', '', 90);

        setTimeout(() => {
            fecharSpinner();
            abrirModalAnalise();
        }, 300);

    } catch (error) {
        fecharSpinner();
        mostrarNotificacao('Erro ao carregar análise: ' + error.message, 'error');
    }
}

function montarHtmlAnalise(entrega) {
    const checklist = Array.isArray(entrega.checklist) ? entrega.checklist : [];
    const problemas = Array.isArray(entrega.problemas) ? entrega.problemas : [];
    const fotos = Array.isArray(entrega.fotos) ? entrega.fotos : [];
    const itensComProblema = checklist.filter(item => item.status && item.status !== 'entregue').length;
    const valorAfetado = problemas.reduce((total, problema) => total + Number(problema.valor_afetado || 0), 0);
    const resumoHtml = `
        <div class="analise-summary">
            <div class="analise-summary-main">
                <span class="analise-eyebrow"><i class="fa-solid fa-route"></i> Ficha operacional</span>
                <strong>${entrega.cliente_nome || 'Entrega sem cliente identificado'}</strong>
                <span>${entrega.cidade || ''}${entrega.uf ? ', ' + entrega.uf : ''}${entrega.veiculo_placa ? ' · ' + entrega.veiculo_placa : ''}</span>
            </div>
            <div class="analise-summary-stats">
                <div><strong>${checklist.length}</strong><span>itens</span></div>
                <div class="${itensComProblema ? 'is-alert' : ''}"><strong>${itensComProblema}</strong><span>com divergência</span></div>
                <div class="${problemas.length ? 'is-alert' : ''}"><strong>${problemas.length}</strong><span>ocorrências</span></div>
                <div><strong>${fotos.length}</strong><span>evidências</span></div>
            </div>
        </div>
        ${problemas.length ? `<div class="analise-impact"><i class="fa-solid fa-chart-line"></i><span>Impacto registrado</span><strong>${formatarMoeda(valorAfetado)}</strong><small>valor afetado</small></div>` : ''}
    `;

    // Info da entrega
    const infoHtml = `
        ${resumoHtml}
        <div class="detalhes-grid">
            <div class="detalhes-card">
                <div class="label"><i class="fa-solid fa-hashtag"></i> ID Entrega</div>
                <div class="value">#${entrega.id}</div>
            </div>
            <div class="detalhes-card ${entrega.status === 'entregue_com_problema' ? 'status-card' : ''}">
                <div class="label"><i class="fa-solid fa-circle"></i> Status</div>
                <div class="value">
                    <span class="badge-status ${entrega.status === 'entregue_com_problema' ? 'problema' : 'finalizado'}">
                        ${entrega.status === 'entregue_com_problema' ? '⚠️ Entregue c/ Problema' : entrega.status || 'Pendente'}
                    </span>
                </div>
            </div>
            <div class="detalhes-card">
                <div class="label"><i class="fa-solid fa-user"></i> Cliente</div>
                <div class="value">${entrega.cliente_nome || '-'}</div>
                <div class="value sub">${entrega.endereco || ''}${entrega.numero ? ', ' + entrega.numero : ''}</div>
            </div>
            <div class="detalhes-card">
                <div class="label"><i class="fa-solid fa-truck"></i> Motorista / Veículo</div>
                <div class="value">${entrega.motorista_nome || '-'}</div>
                <div class="value sub">${entrega.veiculo_placa || ''}</div>
            </div>
            <div class="detalhes-card">
                <div class="label"><i class="fa-regular fa-calendar"></i> Data Entrega</div>
                <div class="value">${formatarDataHora(entrega.horario_entrega) || formatarDataHora(entrega.created_at)}</div>
            </div>
            <div class="detalhes-card">
                <div class="label"><i class="fa-solid fa-qrcode"></i> Código Rastreamento</div>
                <div class="value" style="font-family: monospace; font-size: 0.85rem;">${entrega.codigo_rastreamento || '-'}</div>
            </div>
        </div>
    `;

    // Checklist de itens
    let checklistHtml = '';
    if (checklist.length > 0) {
        checklistHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-solid fa-clipboard-list mr-2" style="color:var(--nutri-accent);"></i>
                    Checklist de Itens (${checklist.length})
                </h6>
                <div class="analise-checklist">
                    ${checklist.map(item => {
                        const isProblema = item.status !== 'entregue';
                        const statusClass = item.status || 'entregue';
                        return `
                            <div class="checklist-item">
                                <div class="info">
                                    <div class="ref">${item.referencia || '-'}</div>
                                    <div class="desc">${item.descricao || 'Sem descrição'}</div>
                                </div>
                                <div class="quantidades">
                                    <span class="previsto">Prev: ${item.quantidade_prevista || 0}</span>
                                    <span class="entregue ${isProblema ? 'problema' : ''}">Ent: ${item.quantidade_entregue || 0}</span>
                                </div>
                                <span class="status-item ${statusClass}">${item.status || 'entregue'}</span>
                                ${item.motivo ? `<span class="text-xs text-red-500">${item.motivo}</span>` : ''}
                                ${item.foto_item ? `
                                    <button class="foto-btn" onclick="verFotoItem('${item.foto_item}')" title="Ver foto">
                                        <i class="fa-regular fa-image"></i>
                                    </button>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    // Problemas registrados
    let problemasHtml = '';
    if (problemas.length > 0) {
        problemasHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-solid fa-triangle-exclamation mr-2" style="color:#f59e0b;"></i>
                    Problemas Registrados (${problemas.length})
                </h6>
                <div class="analise-checklist">
                    ${problemas.map(p => `
                        <div class="checklist-item" style="border-left: 3px solid ${p.prioridade === 'critica' ? '#dc2626' : p.prioridade === 'alta' ? '#f59e0b' : '#3b82f6'};">
                            <div class="info">
                                <div class="ref">${getTipoLabel(p.tipo_problema)}</div>
                                <div class="desc">${p.descricao_problema || 'Sem descrição'}</div>
                                ${p.solucao ? `<div class="desc" style="color:var(--nutri-accent);">✅ Solução: ${p.solucao}</div>` : ''}
                            </div>
                            <div class="quantidades">
                                <span>Qtd: <strong>${p.quantidade_afetada || 0}</strong></span>
                                <span>Valor: <strong>${formatarMoeda(p.valor_afetado)}</strong></span>
                            </div>
                            <span class="priority-badge ${p.prioridade || 'media'}">${getPriorityLabel(p.prioridade)}</span>
                            <span class="status-problema ${p.status_problema || 'pendente'}">${getStatusLabel(p.status_problema)}</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    // Timeline
    let timelineHtml = '';
    if (entrega.timeline && entrega.timeline.length > 0) {
        const acaoDot = {
            'checkin': 'checkin',
            'checkout': 'checkout',
            'problema': 'problema',
            'resolvido': 'resolvido',
            'falha': 'falha'
        };
        const acaoIcon = {
            'checkin': 'fa-solid fa-location-dot',
            'checkout': 'fa-solid fa-check-double',
            'problema': 'fa-solid fa-triangle-exclamation',
            'resolvido': 'fa-solid fa-check-circle',
            'falha': 'fa-solid fa-times-circle'
        };

        timelineHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-solid fa-clock-rotate-left mr-2" style="color:var(--nutri-accent);"></i>
                    Timeline (${entrega.timeline.length} eventos)
                </h6>
                <div class="analise-timeline">
                    ${entrega.timeline.map(event => {
                        const dotClass = acaoDot[event.acao] || '';
                        const icon = acaoIcon[event.acao] || 'fa-solid fa-circle';
                        return `
                            <div class="timeline-item">
                                <div class="dot ${dotClass}">
                                    <i class="${icon}"></i>
                                </div>
                                <div class="header">
                                    <span class="title">${event.descricao || event.acao || 'Evento'}</span>
                                    <span class="time">${formatarDataHora(event.created_at)}</span>
                                    <span class="user">${event.usuario_nome || 'Sistema'}</span>
                                </div>
                                ${event.dados_anteriores ? `
                                    <div class="descricao">
                                        <strong>Antes:</strong> ${JSON.stringify(event.dados_anteriores)}
                                    </div>
                                ` : ''}
                                ${event.dados_novos ? `
                                    <div class="descricao">
                                        <strong>Depois:</strong> ${JSON.stringify(event.dados_novos)}
                                    </div>
                                ` : ''}
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    // Fotos
    let fotosHtml = '';
    if (fotos.length > 0) {
        fotosHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-regular fa-images mr-2" style="color:var(--nutri-accent);"></i>
                    Fotos (${fotos.length})
                </h6>
                <div class="flex flex-wrap gap-3">
                    ${fotos.map(foto => `
                        <div class="foto-thumbnail" style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; cursor: pointer; border: 2px solid var(--nutri-border);" 
                             onclick="abrirZoomFoto('${foto.url_foto}', '${foto.tipo_foto || 'Foto'}')">
                            <img src="${foto.url_foto}" style="width: 100%; height: 100%; object-fit: cover;" 
                                 onerror="this.style.display='none'; this.parentElement.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;background:#f1f5f9;color:#94a3b8;\\'><i class=\\'fa-regular fa-image\\'></i></div>'">
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    }

    return `
        ${infoHtml}
        <hr style="margin: 20px 0; border: 0; border-top: 2px solid var(--nutri-border);">
        ${checklistHtml}
        ${problemasHtml}
        ${timelineHtml}
        ${fotosHtml}
    `;
}

// ================================================================
// MODAL DE ANÁLISE
// ================================================================
function abrirModalAnalise() {
    const el = document.getElementById('modalAnalise');
    if (!el) return;

    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        if (!state.modalInstance) {
            state.modalInstance = new bootstrap.Modal(el, {
                backdrop: 'static',
                keyboard: true
            });
        }
        state.modalInstance.show();
    } else if (typeof $ !== 'undefined' && $.fn.modal) {
        $(el).modal('show');
    } else {
        el.style.display = 'block';
        el.classList.add('show');
        document.body.classList.add('modal-open');
        if (!document.querySelector('.modal-backdrop')) {
            const b = document.createElement('div');
            b.className = 'modal-backdrop fade show';
            document.body.appendChild(b);
        }
    }
}

function fecharModalAnalise() {
    const el = document.getElementById('modalAnalise');
    if (!el) return;

    if (state.modalInstance) {
        state.modalInstance.hide();
        state.modalInstance.dispose();
        state.modalInstance = null;
    } else if (typeof $ !== 'undefined' && $.fn.modal) {
        $(el).modal('hide');
    } else {
        el.style.display = 'none';
        el.classList.remove('show');
        document.body.classList.remove('modal-open');
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) backdrop.remove();
    }
}

// ================================================================
// AÇÕES DO GESTOR
// ================================================================
async function resolverProblema(problemaId) {
    const result = await Swal.fire({
        title: 'Resolver Problema',
        text: 'Confirma a resolução deste problema?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sim, resolver',
        cancelButtonText: 'Cancelar'
    });

    if (!result.isConfirmed) return;

    const token = getAuthToken();
    if (!token) return;

    try {
        mostrarSpinner('Resolvendo problema...', 'Atualizando status', 50);

        const response = await fetch(`${CONFIG.API_BASE}/problemas/${problemaId}/resolver`, {
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ solucao: 'Resolvido pelo gestor' })
        });

        atualizarSpinner('Finalizando...', '', 90);

        const dados = await response.json();

        setTimeout(() => {
            fecharSpinner();
            if (dados.success) {
                mostrarNotificacao('✅ Problema resolvido com sucesso!', 'success');
                carregarDados(true);
                fecharModalAnalise();
            } else {
                mostrarNotificacao(dados.error || 'Erro ao resolver problema', 'error');
            }
        }, 300);

    } catch (error) {
        fecharSpinner();
        mostrarNotificacao('Erro ao resolver problema: ' + error.message, 'error');
    }
}

async function iniciarAnalise(problemaId) {
    const token = getAuthToken();
    if (!token) return;

    try {
        const response = await fetch(`${CONFIG.API_BASE}/problemas/${problemaId}/iniciar-analise`, {
            method: 'PUT',
            headers: { 'Authorization': 'Bearer ' + token }
        });

        const dados = await response.json();
        if (dados.success) {
            mostrarNotificacao('🔍 Análise iniciada!', 'info');
            carregarDados(true);
        } else {
            mostrarNotificacao(dados.error || 'Erro ao iniciar análise', 'error');
        }
    } catch (error) {
        mostrarNotificacao('Erro ao iniciar análise', 'error');
    }
}

function adicionarAnalise() {
    Swal.fire({
        title: 'Adicionar Análise',
        html: `
            <div class="text-left">
                <div class="mb-3">
                    <label class="form-label">Título</label>
                    <input type="text" id="analise-titulo" class="form-control" placeholder="Título da análise">
                </div>
                <div class="mb-3">
                    <label class="form-label">Descrição</label>
                    <textarea id="analise-descricao" class="form-control" rows="4" placeholder="Descreva sua análise..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nota (0-10)</label>
                    <input type="number" id="analise-nota" class="form-control" min="0" max="10" value="7">
                </div>
                <div class="mb-3">
                    <label class="form-label">Recomendações</label>
                    <textarea id="analise-recomendacoes" class="form-control" rows="3" placeholder="Sugestões para melhorias..."></textarea>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Salvar Análise',
        confirmButtonColor: '#10b981',
        preConfirm: () => {
            const titulo = document.getElementById('analise-titulo').value.trim();
            const descricao = document.getElementById('analise-descricao').value.trim();
            const nota = parseInt(document.getElementById('analise-nota').value) || 0;
            const recomendacoes = document.getElementById('analise-recomendacoes').value.trim();

            if (!titulo) {
                Swal.showValidationMessage('O título é obrigatório');
                return false;
            }
            if (!descricao) {
                Swal.showValidationMessage('A descrição é obrigatória');
                return false;
            }

            return { titulo, descricao, nota, recomendacoes };
        }
    }).then(async (result) => {
        if (!result.isConfirmed) return;

        const token = getAuthToken();
        if (!token) return;

        try {
            const response = await fetch(`${CONFIG.API_BASE}/entregas/${state.entregaSelecionada}/analise`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(result.value)
            });

            const dados = await response.json();
            if (dados.success) {
                mostrarNotificacao('✅ Análise adicionada com sucesso!', 'success');
                carregarDados(true);
                verAnalise(state.entregaSelecionada);
            } else {
                mostrarNotificacao(dados.error || 'Erro ao adicionar análise', 'error');
            }
        } catch (error) {
            mostrarNotificacao('Erro ao adicionar análise', 'error');
        }
    });
}

// ================================================================
// FOTO - ZOOM
// ================================================================
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
                style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 16px; border-radius: 8px; cursor: pointer; font-size: 0.85rem;">
            ✕ Fechar
        </button>
    `;

    container.appendChild(img);
    container.appendChild(caption);
    backdrop.appendChild(container);
    document.body.appendChild(backdrop);

    backdrop.onclick = (e) => {
        if (e.target === backdrop) fecharZoom();
    };

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') fecharZoom();
    });

    document.body.style.overflow = 'hidden';
}

function fecharZoom() {
    const backdrop = document.getElementById('zoom-backdrop');
    if (backdrop) {
        backdrop.style.animation = 'fadeOutZoom 0.2s ease';
        setTimeout(() => {
            backdrop.remove();
            document.body.style.overflow = '';
        }, 200);
    }
}

function verFotoItem(fotoUrl) {
    abrirZoomFoto(fotoUrl, 'Foto do Item');
}

// ================================================================
// EXPORTAR CSV
// ================================================================
function exportarCSV() {
    if (!state.dadosProblemas || state.dadosProblemas.length === 0) {
        mostrarNotificacao('Nenhum dado para exportar', 'warning');
        return;
    }

    const headers = ['ID', 'Entrega', 'Cliente', 'Motorista', 'Tipo', 'Referência', 'Quantidade', 'Valor', 'Prioridade', 'Status', 'Data'];
    const rows = state.dadosProblemas.map(p => [
        p.id,
        p.entrega_id,
        p.cliente_nome || '',
        p.motorista_nome || '',
        p.tipo_problema || '',
        p.referencia || '',
        p.quantidade_afetada || 0,
        p.valor_afetado || 0,
        p.prioridade || '',
        p.status_problema || '',
        formatarDataHora(p.created_at)
    ]);

    let csv = headers.join(',') + '\n';
    rows.forEach(row => {
        csv += row.join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `problemas_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);

    mostrarNotificacao('📊 CSV exportado com sucesso!', 'success');
}

// ================================================================
// ABAS PRINCIPAIS (Visão Geral / Motoristas / Histórico)
// ================================================================
let abaCargasAtiva = 'visao-geral';
let motoristasCarregados = false;
let veiculosCarregados = false;
let graficosCarregados = false;
let cobliCarregado = false;
let rankingMotoristasData = [];
let rankingVeiculosData = [];

// ================================================================
// Filtros de eficiência (persistidos em localStorage)
// true = ocultar itens com amostra_insuficiente
// ================================================================
let filtroEficienciaMotoristas = false;
let filtroEficienciaVeiculos   = false;
let chartInstances = {};
let historicoState = {
    pagina: 1,
    totalPaginas: 1,
    busca: '',
    status: 'todos',
    dataInicio: '',
    dataFim: ''
};

function mudarAbaCargas(aba, btn) {
    abaCargasAtiva = aba;

    document.querySelectorAll('.cargas-tab').forEach(t => {
        t.classList.toggle('active', t === btn);
        t.setAttribute('aria-selected', t === btn ? 'true' : 'false');
    });
    document.querySelectorAll('.cargas-tab-panel').forEach(p => {
        p.hidden = p.id !== `tab-${aba}`;
    });

    // ------------------------------------------------------------
    // Carregamento sob demanda por aba
    // ------------------------------------------------------------

    // 🆕 Dashboard (Bloco 5.B.2)
    if (aba === 'dashboard') {
        carregarAbaDashboard();
    }

    // ⏳ Eficiência (será implementada no Bloco 5.C)
    if (aba === 'eficiencia') {
        // TODO: carregarAbaEficiencia() no Bloco 5.C
    }

    // ⏳ Análises (será ajustado no Bloco 5.E)
    if (aba === 'analises' && !graficosCarregados) {
        carregarGraficosCargas();
    }

    // ⏳ Mapa (será implementado no Bloco 5.D)
    if (aba === 'mapa') {
        // TODO: carregarAbaMapa() no Bloco 5.D
    }
}

// ================================================================
// ABA: DESEMPENHO DE MOTORISTAS
// ================================================================
async function carregarRankingMotoristas() {
    const token = getAuthToken();
    if (!token) return;

    const dias = document.getElementById('filtro-motoristas-dias')?.value || 30;
    const infoPeriodo = document.getElementById('info-motoristas-periodo');
    if (infoPeriodo) {
        const labels = { '7': 'Últimos 7 dias', '30': 'Últimos 30 dias', '90': 'Últimos 90 dias', '365': 'Últimos 12 meses' };
        infoPeriodo.textContent = labels[dias] || `Últimos ${dias} dias`;
    }

    const tbody = document.getElementById('lista-motoristas');
    if (tbody) tbody.innerHTML = '<tr><td colspan="10" class="text-center py-8">Carregando...</td></tr>';

    try {
        const response = await fetch(`${CONFIG.API_BASE}/gestao-cargas/ranking-motoristas?dias=${dias}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('Falha ao carregar ranking');
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro desconhecido');

        motoristasCarregados = true;
        rankingMotoristasData = payload.data || [];
        renderizarDestaquesMotoristas(rankingMotoristasData);
        renderizarTabelaMotoristas(rankingMotoristasData);
    } catch (error) {
        console.error('Erro ao carregar ranking de motoristas:', error);
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-red-500">Erro ao carregar ranking de motoristas</td></tr>';
    }
}

function renderizarDestaquesMotoristas(dados) {
    const container = document.getElementById('motoristas-destaques');
    if (!container) return;

    if (!dados.length) {
        container.innerHTML = '<div class="empty-state-cargas">Nenhum dado de motorista no período selecionado.</div>';
        return;
    }

    const maisDivergencia = [...dados].sort((a, b) => b.taxa_divergencia - a.taxa_divergencia)[0];
    const maisAtrasos = [...dados].sort((a, b) => (b.entregas_atrasadas || 0) - (a.entregas_atrasadas || 0))[0];
    const melhorDesempenho = [...dados].sort((a, b) => a.indice_ineficiencia - b.indice_ineficiencia)[0];

    container.innerHTML = `
        <div class="motoristas-destaques-grid">
            <div class="motorista-destaque-card critico">
                <div class="destaque-label"><i class="fa-solid fa-triangle-exclamation"></i> Maior taxa de divergência</div>
                <div class="destaque-nome">${escapeHtml(maisDivergencia?.motorista_nome || '-')}</div>
                <div class="destaque-valor">${(maisDivergencia?.taxa_divergencia ?? 0).toFixed(1)}%</div>
            </div>
            <div class="motorista-destaque-card critico">
                <div class="destaque-label"><i class="fa-regular fa-clock"></i> Mais entregas atrasadas</div>
                <div class="destaque-nome">${escapeHtml(maisAtrasos?.motorista_nome || '-')}</div>
                <div class="destaque-valor">${maisAtrasos?.entregas_atrasadas ?? 0}</div>
            </div>
            <div class="motorista-destaque-card sucesso">
                <div class="destaque-label"><i class="fa-solid fa-medal"></i> Melhor desempenho</div>
                <div class="destaque-nome">${escapeHtml(melhorDesempenho?.motorista_nome || '-')}</div>
                <div class="destaque-valor">${(melhorDesempenho?.indice_ineficiencia ?? 0).toFixed(1)} pts</div>
            </div>
        </div>
    `;
}

function renderizarTabelaMotoristas(dados) {
    const tbody = document.getElementById('lista-motoristas');
    if (!tbody) return;

    // ================================================================
    // Aplica filtro "Ocultar sem eficiência" (se ativo)
    // ================================================================
    const dadosFiltrados = aplicarFiltroEficiencia(dados, filtroEficienciaMotoristas);

    if (!dadosFiltrados.length) {
        const msgVazia = (filtroEficienciaMotoristas && dados.length > 0)
            ? 'Nenhum motorista com eficiência calculada no período (filtro ativo).'
            : 'Nenhum motorista com embarques no período.';
        tbody.innerHTML = `<tr><td colspan="12" class="text-center py-8">${msgVazia}</td></tr>`;
        return;
    }

    tbody.innerHTML = dadosFiltrados.map((m, idx) => {
        const indice = Number(m.indice_ineficiencia || 0);
        const nivel = indice >= 60 ? 'alto' : (indice >= 30 ? 'medio' : 'baixo');
        const taxaDivClass = m.taxa_divergencia >= 15 ? 'critico' : (m.taxa_divergencia >= 5 ? 'alerta' : 'ok');
        const taxaPrazoClass = m.taxa_no_prazo >= 90 ? 'ok' : (m.taxa_no_prazo >= 70 ? 'alerta' : 'critico');
        const score = Number(m.score_desempenho || 0);
        const scoreNivel = score >= 80 ? 'alto' : (score >= 50 ? 'medio' : 'baixo');

        // ================================================================
        // KPIs de eficiência de trajeto (com flags)
        // ================================================================
        const trajetos = Number(m.trajetos_analisados || 0);
        const amostraInsuf = !!m.amostra_insuficiente;
        const amostraPequena = !!m.amostra_pequena;

        // ----- Tempo Médio de Deslocamento -----
        let tempoDeslHtml = '';
        if (amostraInsuf || m.tempo_medio_deslocamento_min === null || m.tempo_medio_deslocamento_min === undefined) {
            tempoDeslHtml = '<span class="text-slate-400" title="Dados insuficientes (0 trajetos válidos)">—</span>';
        } else {
            const badgeAviso = amostraPequena
                ? `<span class="amostra-badge" title="Amostra pequena — use com cautela"><i class="fa-solid fa-triangle-exclamation"></i> ${trajetos}</span>`
                : `<span class="text-xs text-slate-400" title="Baseado em ${trajetos} trajetos">(${trajetos})</span>`;
            tempoDeslHtml = `${Math.round(m.tempo_medio_deslocamento_min)} min ${badgeAviso}`;
        }

        // ----- Índice de Eficiência -----
        let eficienciaHtml = '';
        if (amostraInsuf || m.indice_eficiencia_trajeto === null || m.indice_eficiencia_trajeto === undefined) {
            eficienciaHtml = '<span class="text-slate-400" title="Dados insuficientes (0 trajetos válidos)">—</span>';
        } else {
            const ef = Number(m.indice_eficiencia_trajeto);
            const efClass = ef >= 85 ? 'ok' : (ef >= 60 ? 'alerta' : 'critico');
            const efIcon = ef >= 85 ? 'fa-arrow-trend-up'
                          : (ef >= 60 ? 'fa-arrow-right' : 'fa-arrow-trend-down');
            const titulo = amostraPequena
                ? `Amostra pequena (${trajetos} trajeto${trajetos === 1 ? '' : 's'}) — use com cautela`
                : `Tempo ideal / tempo real (baseado em ${trajetos} trajetos)`;

            const badgeAviso = amostraPequena
                ? `<span class="amostra-badge" title="${titulo}"><i class="fa-solid fa-triangle-exclamation"></i></span>`
                : '';

            eficienciaHtml = `<span class="badge-taxa ${efClass}" title="${titulo}">
                <i class="fa-solid ${efIcon}"></i> ${ef.toFixed(1)}%
            </span>${badgeAviso}`;
        }

        return `
        <tr class="tabela-motoristas-linha" onclick="abrirDetalheMotorista(${m.id})">
            <td class="text-center">${idx + 1}</td>
            <td>
                <div class="motorista-nome-cell">
                    <strong>${escapeHtml(m.motorista_nome || '-')}</strong>
                    <span>${escapeHtml(m.motorista_telefone || '')}</span>
                </div>
            </td>
            <td class="text-center">${m.total_embarques ?? 0}</td>
            <td class="text-center">${m.total_entregas ?? 0}</td>
            <td class="text-center"><span class="badge-taxa ${taxaDivClass}">${(m.taxa_divergencia ?? 0).toFixed(1)}%</span></td>
            <td class="text-center"><span class="badge-taxa ${taxaPrazoClass}">${(m.taxa_no_prazo ?? 0).toFixed(1)}%</span></td>
            <td class="text-center">${Math.round(m.tempo_medio_entrega_min ?? 0)} min</td>
            <td class="text-center">${tempoDeslHtml}</td>
            <td class="text-center">${eficienciaHtml}</td>
            <td class="text-center">${m.total_problemas ?? 0}</td>
            <td class="text-center"><span class="score-mini-badge nivel-${scoreNivel}">${score.toFixed(1)}</span></td>
            <td>
                <div class="indice-ineficiencia-bar">
                    <div class="indice-ineficiencia-bar-fill ${nivel}" style="width:${Math.min(indice, 100)}%"></div>
                    <div class="indice-ineficiencia-bar-label">${indice.toFixed(1)}</div>
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function abrirDetalheMotorista(id) {
    const m = rankingMotoristasData.find(x => String(x.id) === String(id));
    if (!m) return;

    const titulo = document.getElementById('detalhe-ranking-titulo');
    const conteudo = document.getElementById('detalhe-ranking-conteudo');
    if (titulo) titulo.innerHTML = `<i class="fa-solid fa-id-badge mr-2"></i> ${escapeHtml(m.motorista_nome || '-')}`;
    if (conteudo) conteudo.innerHTML = '<div class="text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando perfil completo...</div>';

    const modal = new bootstrap.Modal(document.getElementById('modalDetalheRanking'));
    modal.show();

    const token = getAuthToken();
    try {
        const response = await fetch(`${CONFIG.API_BASE}/gestao-cargas/motorista/${id}/perfil?dias=90`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('Falha ao buscar perfil do motorista');
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro desconhecido');

        if (conteudo) conteudo.innerHTML = montarHtmlPerfilMotorista(payload.data || {});
    } catch (error) {
        console.error('Erro ao abrir perfil do motorista:', error);
        if (conteudo) conteudo.innerHTML = '<div class="text-center py-8 text-red-500">Erro ao carregar perfil do motorista</div>';
    }
}

function montarHtmlPerfilMotorista(data) {
    const mot = data.motorista || {};
    const met = data.metricas || {};
    const veiculos = data.veiculos_utilizados || [];
    const embarques = data.embarques || [];
    const positivos = data.pontos_positivos || [];
    const negativos = data.pontos_negativos || [];

    const indice = Number(met.indice_ineficiencia || 0);
    const nivel = indice >= 60 ? 'alto' : (indice >= 30 ? 'medio' : 'baixo');
    const score = Number(met.score_desempenho || 0);
    const scoreNivel = score >= 80 ? 'alto' : (score >= 50 ? 'medio' : 'baixo');

    // ================================================================
    // KPIs de eficiência de trajeto (com flags)
    // ================================================================
    const trajetos = Number(met.trajetos_analisados || 0);
    const amostraInsuf = !!met.amostra_insuficiente;
    const amostraPequena = !!met.amostra_pequena;
    const temEficiencia = !amostraInsuf
        && met.indice_eficiencia_trajeto !== null
        && met.indice_eficiencia_trajeto !== undefined;

    // ----- Card Eficiência Trajeto -----
    let eficienciaCardHtml = '';
    if (temEficiencia) {
        const ef = Number(met.indice_eficiencia_trajeto);
        const efClass = ef >= 85 ? 'ok' : (ef >= 60 ? 'alerta' : 'critico');
        const efIcon = ef >= 85 ? 'fa-arrow-trend-up'
                      : (ef >= 60 ? 'fa-arrow-right' : 'fa-arrow-trend-down');
        const efLabel = ef >= 85 ? 'Trajeto Eficiente'
                       : (ef >= 60 ? 'Trajeto Aceitável' : 'Trajeto Ineficiente');

        const subtitulo = amostraPequena
            ? `⚠ Amostra pequena (${trajetos} trajeto${trajetos === 1 ? '' : 's'}) — use com cautela`
            : `${efLabel} • ${trajetos} trajetos`;

        eficienciaCardHtml = `
            <div class="stat eficiencia-card ${efClass}${amostraPequena ? ' com-aviso' : ''}">
                <strong>
                    <i class="fa-solid ${efIcon}"></i>
                    ${ef.toFixed(1)}%
                </strong>
                <span>Eficiência Trajeto</span>
                <small class="text-xs text-slate-400">${subtitulo}</small>
            </div>
        `;
    } else {
        eficienciaCardHtml = `
            <div class="stat eficiencia-card neutro">
                <strong>
                    <i class="fa-solid fa-circle-info"></i>
                    —
                </strong>
                <span>Eficiência Trajeto</span>
                <small class="text-xs text-slate-400">Dados insuficientes (${trajetos} trajeto${trajetos === 1 ? '' : 's'})</small>
            </div>
        `;
    }

    // ----- Cards de Tempo de Deslocamento -----
    let tempoDeslHtml = '';
    if (temEficiencia && met.tempo_medio_deslocamento_min !== null) {
        tempoDeslHtml = `
            <div class="stat">
                <strong>${Math.round(met.tempo_medio_deslocamento_min)} min</strong>
                <span>Tempo Médio Desl.</span>
                <small class="text-xs text-slate-400">média real entre paradas</small>
            </div>
            <div class="stat">
                <strong>${Math.round(met.tempo_ideal_medio_min ?? 0)} min</strong>
                <span>Tempo Ideal Médio</span>
                <small class="text-xs text-slate-400">a ${data.velocidade_referencia_kmh ?? 40} km/h</small>
            </div>
            <div class="stat">
                <strong>${met.distancia_media_trajeto_km != null ? met.distancia_media_trajeto_km.toFixed(1) : '—'} km</strong>
                <span>Distância Média</span>
                <small class="text-xs text-slate-400">entre paradas</small>
            </div>
        `;
    } else {
        tempoDeslHtml = `
            <div class="stat">
                <strong>—</strong>
                <span>Tempo Médio Desl.</span>
                <small class="text-xs text-slate-400">sem trajetos válidos</small>
            </div>
        `;
    }

    const header = `
        <div class="detalhe-ranking-header">
            <div>
                <strong style="font-size:1.1rem;">${escapeHtml(mot.nome || '-')}</strong>
                <div class="text-sm text-slate-500">${escapeHtml(mot.telefone || 'Sem telefone')} • Status: ${escapeHtml(mot.status || '-')}</div>
            </div>
            <div class="perfil-score-badges">
                <div class="score-motorista-badge nivel-${scoreNivel}">
                    <span class="score-valor">${score.toFixed(1)}</span>
                    <span class="score-label">Score de Desempenho</span>
                </div>
                <div class="indice-ineficiencia-bar" style="max-width:220px;">
                    <div class="indice-ineficiencia-bar-fill ${nivel}" style="width:${Math.min(indice, 100)}%"></div>
                    <div class="indice-ineficiencia-bar-label">Índice de Ineficiência: ${indice.toFixed(1)}</div>
                </div>
            </div>

            <!-- KPIs PRINCIPAIS -->
            <div class="detalhe-ranking-stats">
                <div class="stat"><strong>${met.total_embarques ?? 0}</strong><span>Embarques</span></div>
                <div class="stat"><strong>${met.total_entregas ?? 0}</strong><span>Entregas</span></div>
                <div class="stat"><strong>${met.entregas_concluidas ?? 0}</strong><span>Concluídas</span></div>
                <div class="stat"><strong>${met.entregas_atrasadas ?? 0}</strong><span>Atrasadas</span></div>
                <div class="stat"><strong>${(met.taxa_divergencia ?? 0).toFixed(1)}%</strong><span>Divergência</span></div>
                <div class="stat"><strong>${(met.taxa_no_prazo ?? 0).toFixed(1)}%</strong><span>No prazo</span></div>
                <div class="stat"><strong>${Math.round(met.tempo_medio_entrega_min ?? 0)} min</strong><span>Tempo no cliente</span></div>
                <div class="stat"><strong>${met.total_problemas ?? 0}</strong><span>Problemas</span></div>
                <div class="stat"><strong>${formatarMoeda(met.valor_total_afetado ?? 0)}</strong><span>Valor Afetado</span></div>
            </div>

            <!-- KPIs NOVOS: EFICIÊNCIA DE TRAJETO -->
            <div class="detalhe-ranking-stats detalhe-ranking-stats-eficiencia">
                ${eficienciaCardHtml}
                ${tempoDeslHtml}
            </div>
        </div>
    `;

    const pontosHtml = `
        <div class="perfil-pontos-grid">
            <div class="perfil-pontos-coluna positivos">
                <h4><i class="fa-solid fa-circle-check mr-1"></i> Pontos Positivos</h4>
                ${positivos.length ? '<ul>' + positivos.map(p => `<li>${escapeHtml(p)}</li>`).join('') + '</ul>' : '<p class="text-slate-500 text-sm">Nenhum destaque no período.</p>'}
            </div>
            <div class="perfil-pontos-coluna negativos">
                <h4><i class="fa-solid fa-circle-exclamation mr-1"></i> Pontos de Atenção</h4>
                ${negativos.length ? '<ul>' + negativos.map(p => `<li>${escapeHtml(p)}</li>`).join('') + '</ul>' : '<p class="text-slate-500 text-sm">Nenhum ponto de atenção identificado.</p>'}
            </div>
        </div>
    `;

    const veiculosHtml = veiculos.length ? `
        <div class="perfil-secao">
            <h4><i class="fa-solid fa-truck mr-1"></i> Veículos Utilizados</h4>
            <div class="perfil-veiculos-lista">
                ${veiculos.map(v => `
                    <div class="perfil-veiculo-card">
                        <strong>${escapeHtml(v.placa || '-')}</strong>
                        <span>${escapeHtml(v.marca || '')} ${escapeHtml(v.modelo || '')}</span>
                        <span class="text-xs text-slate-500">${v.total_embarques} embarques • Último uso: ${formatarData(v.ultimo_uso)}</span>
                    </div>
                `).join('')}
            </div>
        </div>
    ` : '';

    const embarquesHtml = embarques.length ? `
        <div class="perfil-secao">
            <h4><i class="fa-solid fa-clock-rotate-left mr-1"></i> Histórico de Embarques (rastreável)</h4>
            <div class="perfil-embarques-lista">
                ${embarques.map(e => `
                    <div class="perfil-embarque-item" onclick="fecharModalRankingEAbrirEmbarque(${e.id})">
                        <div>
                            <strong>${escapeHtml(e.numero_embarque || ('#' + e.id))}</strong>
                            <span class="text-xs text-slate-500">${escapeHtml(e.veiculo_placa || '-')} • ${formatarData(e.data_saida)}</span>
                        </div>
                        <div class="perfil-embarque-badges">
                            <span class="hist-status-badge ${e.embarque_status || ''}">${escapeHtml(e.embarque_status || '-')}</span>
                            ${e.acerto_status ? `<span class="hist-status-badge ${e.acerto_status}">${escapeHtml(e.acerto_status)}</span>` : ''}
                            <span class="text-xs">${e.entregas_concluidas ?? 0}/${e.total_entregas ?? 0} entregas</span>
                            ${(e.total_problemas ?? 0) > 0 ? `<span class="text-xs text-red-500">${e.total_problemas} problema(s)</span>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    ` : '';

    return header + pontosHtml + veiculosHtml + embarquesHtml;
}

function fecharModalRankingEAbrirEmbarque(embarqueId) {
    const rankingModalEl = document.getElementById('modalDetalheRanking');
    const rankingModal = bootstrap.Modal.getInstance(rankingModalEl);
    if (rankingModal) rankingModal.hide();
    setTimeout(() => abrirDetalheEmbarque(embarqueId), 300);
}


// ================================================================
// ABA: POR CAMINHÃO (VEÍCULOS)
// ================================================================
async function carregarRankingVeiculos() {
    const token = getAuthToken();
    if (!token) return;

    const dias = document.getElementById('filtro-veiculos-dias')?.value || 30;
    const infoPeriodo = document.getElementById('info-veiculos-periodo');
    if (infoPeriodo) {
        const labels = { '7': 'Últimos 7 dias', '30': 'Últimos 30 dias', '90': 'Últimos 90 dias', '365': 'Últimos 12 meses' };
        infoPeriodo.textContent = labels[dias] || `Últimos ${dias} dias`;
    }

    const tbody = document.getElementById('lista-veiculos');
    if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8">Carregando...</td></tr>';

    try {
        const response = await fetch(`${CONFIG.API_BASE}/gestao-cargas/ranking-veiculos?dias=${dias}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('Falha ao carregar ranking de veículos');
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro desconhecido');

        veiculosCarregados = true;
        rankingVeiculosData = payload.data || [];
        renderizarDestaquesVeiculos(rankingVeiculosData);
        renderizarTabelaVeiculos(rankingVeiculosData);
    } catch (error) {
        console.error('Erro ao carregar ranking de veículos:', error);
        if (tbody) tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-red-500">Erro ao carregar ranking de veículos</td></tr>';
    }
}

function renderizarDestaquesVeiculos(dados) {
    const container = document.getElementById('veiculos-destaques');
    if (!container) return;

    if (!dados.length) {
        container.innerHTML = '<div class="empty-state-cargas">Nenhum dado de veículo no período selecionado.</div>';
        return;
    }

    const maisDivergencia = [...dados].sort((a, b) => b.taxa_divergencia - a.taxa_divergencia)[0];
    const maisAtrasos = [...dados].sort((a, b) => (b.entregas_atrasadas || 0) - (a.entregas_atrasadas || 0))[0];
    const melhorDesempenho = [...dados].sort((a, b) => a.indice_ineficiencia - b.indice_ineficiencia)[0];

    container.innerHTML = `
        <div class="motoristas-destaques-grid">
            <div class="motorista-destaque-card critico">
                <div class="destaque-label"><i class="fa-solid fa-triangle-exclamation"></i> Maior taxa de divergência</div>
                <div class="destaque-nome">${escapeHtml(maisDivergencia?.placa || '-')}</div>
                <div class="destaque-valor">${(maisDivergencia?.taxa_divergencia ?? 0).toFixed(1)}%</div>
            </div>
            <div class="motorista-destaque-card critico">
                <div class="destaque-label"><i class="fa-regular fa-clock"></i> Mais entregas atrasadas</div>
                <div class="destaque-nome">${escapeHtml(maisAtrasos?.placa || '-')}</div>
                <div class="destaque-valor">${maisAtrasos?.entregas_atrasadas ?? 0}</div>
            </div>
            <div class="motorista-destaque-card sucesso">
                <div class="destaque-label"><i class="fa-solid fa-medal"></i> Melhor desempenho</div>
                <div class="destaque-nome">${escapeHtml(melhorDesempenho?.placa || '-')}</div>
                <div class="destaque-valor">${(melhorDesempenho?.indice_ineficiencia ?? 0).toFixed(1)} pts</div>
            </div>
        </div>
    `;
}

function renderizarTabelaVeiculos(dados) {
    const tbody = document.getElementById('lista-veiculos');
    if (!tbody) return;

    // ================================================================
    // Aplica filtro "Ocultar sem eficiência" (se ativo)
    // ================================================================
    const dadosFiltrados = aplicarFiltroEficiencia(dados, filtroEficienciaVeiculos);

    if (!dadosFiltrados.length) {
        const msgVazia = (filtroEficienciaVeiculos && dados.length > 0)
            ? 'Nenhum veículo com eficiência calculada no período (filtro ativo).'
            : 'Nenhum veículo com embarques no período.';
        tbody.innerHTML = `<tr><td colspan="11" class="text-center py-8">${msgVazia}</td></tr>`;
        return;
    }

    tbody.innerHTML = dadosFiltrados.map((v, idx) => {
        const indice = Number(v.indice_ineficiencia || 0);
        const nivel = indice >= 60 ? 'alto' : (indice >= 30 ? 'medio' : 'baixo');
        const taxaDivClass = v.taxa_divergencia >= 15 ? 'critico' : (v.taxa_divergencia >= 5 ? 'alerta' : 'ok');
        const taxaPrazoClass = v.taxa_no_prazo >= 90 ? 'ok' : (v.taxa_no_prazo >= 70 ? 'alerta' : 'critico');

        // ================================================================
        // KPIs de eficiência de trajeto (com flags)
        // ================================================================
        const trajetos = Number(v.trajetos_analisados || 0);
        const amostraInsuf = !!v.amostra_insuficiente;
        const amostraPequena = !!v.amostra_pequena;

        // ----- Tempo Médio de Deslocamento -----
        let tempoDeslHtml = '';
        if (amostraInsuf || v.tempo_medio_deslocamento_min === null || v.tempo_medio_deslocamento_min === undefined) {
            tempoDeslHtml = '<span class="text-slate-400" title="Dados insuficientes (0 trajetos válidos)">—</span>';
        } else {
            const badgeAviso = amostraPequena
                ? `<span class="amostra-badge" title="Amostra pequena — use com cautela"><i class="fa-solid fa-triangle-exclamation"></i> ${trajetos}</span>`
                : `<span class="text-xs text-slate-400" title="Baseado em ${trajetos} trajetos">(${trajetos})</span>`;
            tempoDeslHtml = `${Math.round(v.tempo_medio_deslocamento_min)} min ${badgeAviso}`;
        }

        // ----- Índice de Eficiência -----
        let eficienciaHtml = '';
        if (amostraInsuf || v.indice_eficiencia_trajeto === null || v.indice_eficiencia_trajeto === undefined) {
            eficienciaHtml = '<span class="text-slate-400" title="Dados insuficientes (0 trajetos válidos)">—</span>';
        } else {
            const ef = Number(v.indice_eficiencia_trajeto);
            const efClass = ef >= 85 ? 'ok' : (ef >= 60 ? 'alerta' : 'critico');
            const efIcon = ef >= 85 ? 'fa-arrow-trend-up'
                          : (ef >= 60 ? 'fa-arrow-right' : 'fa-arrow-trend-down');
            const titulo = amostraPequena
                ? `Amostra pequena (${trajetos} trajeto${trajetos === 1 ? '' : 's'}) — use com cautela`
                : `Tempo ideal / tempo real (baseado em ${trajetos} trajetos)`;

            const badgeAviso = amostraPequena
                ? `<span class="amostra-badge" title="${titulo}"><i class="fa-solid fa-triangle-exclamation"></i></span>`
                : '';

            eficienciaHtml = `<span class="badge-taxa ${efClass}" title="${titulo}">
                <i class="fa-solid ${efIcon}"></i> ${ef.toFixed(1)}%
            </span>${badgeAviso}`;
        }

        return `
        <tr class="tabela-veiculos-linha" onclick="abrirDetalheVeiculo(${v.id})">
            <td class="text-center">${idx + 1}</td>
            <td>
                <div class="motorista-nome-cell">
                    <strong>${escapeHtml(v.placa || '-')}</strong>
                    <span>${escapeHtml(v.marca || '')} ${escapeHtml(v.modelo || '')}</span>
                </div>
            </td>
            <td class="text-center">${v.total_embarques ?? 0}</td>
            <td class="text-center">${v.total_entregas ?? 0}</td>
            <td class="text-center"><span class="badge-taxa ${taxaDivClass}">${(v.taxa_divergencia ?? 0).toFixed(1)}%</span></td>
            <td class="text-center"><span class="badge-taxa ${taxaPrazoClass}">${(v.taxa_no_prazo ?? 0).toFixed(1)}%</span></td>
            <td class="text-center">${Math.round(v.tempo_medio_entrega_min ?? 0)} min</td>
            <td class="text-center">${tempoDeslHtml}</td>
            <td class="text-center">${eficienciaHtml}</td>
            <td class="text-center">${v.total_problemas ?? 0}</td>
            <td>
                <div class="indice-ineficiencia-bar">
                    <div class="indice-ineficiencia-bar-fill ${nivel}" style="width:${Math.min(indice, 100)}%"></div>
                    <div class="indice-ineficiencia-bar-label">${indice.toFixed(1)}</div>
                </div>
            </td>
        </tr>`;
    }).join('');
}
// =====================================================================
// FILTRO "Ocultar sem eficiência" — toggle rápido no cabeçalho
// =====================================================================

/**
 * Alterna o filtro de eficiência para uma das abas (motoristas/veiculos).
 * Persiste em localStorage e re-renderiza a tabela.
 */
function alternarFiltroEficiencia(aba, ativo) {
    if (aba === 'motoristas') {
        filtroEficienciaMotoristas = !!ativo;
        try { localStorage.setItem('frota_filtro_eficiencia_motoristas', ativo ? '1' : '0'); } catch (e) {}
        renderizarTabelaMotoristas(rankingMotoristasData);
    } else if (aba === 'veiculos') {
        filtroEficienciaVeiculos = !!ativo;
        try { localStorage.setItem('frota_filtro_eficiencia_veiculos', ativo ? '1' : '0'); } catch (e) {}
        renderizarTabelaVeiculos(rankingVeiculosData);
    }
}

/**
 * Aplica o filtro de eficiência sobre um array de dados.
 * Remove itens com amostra_insuficiente quando o filtro está ativo.
 */
function aplicarFiltroEficiencia(dados, ativo) {
    if (!ativo) return dados;
    return dados.filter(item => !item.amostra_insuficiente);
}

/**
 * Restaura o estado dos toggles a partir do localStorage ao carregar a página.
 * Deve ser chamada ANTES de carregar os rankings, para que os toggles
 * apareçam já marcados.
 */
function restaurarFiltrosEficiencia() {
    try {
        const m = localStorage.getItem('frota_filtro_eficiencia_motoristas') === '1';
        const v = localStorage.getItem('frota_filtro_eficiencia_veiculos') === '1';

        filtroEficienciaMotoristas = m;
        filtroEficienciaVeiculos = v;

        const toggleM = document.getElementById('toggle-eficiencia-motoristas');
        const toggleV = document.getElementById('toggle-eficiencia-veiculos');
        if (toggleM) toggleM.checked = m;
        if (toggleV) toggleV.checked = v;
    } catch (e) {
        console.warn('Não foi possível restaurar filtros de eficiência:', e);
    }
}

function abrirDetalheVeiculo(id) {
    const v = rankingVeiculosData.find(x => String(x.id) === String(id));
    if (!v) return;

    const titulo = document.getElementById('detalhe-ranking-titulo');
    const conteudo = document.getElementById('detalhe-ranking-conteudo');
    if (titulo) titulo.innerHTML = `<i class="fa-solid fa-truck mr-2"></i> ${escapeHtml(v.placa || '-')}`;

    const indice = Number(v.indice_ineficiencia || 0);
    const nivel = indice >= 60 ? 'alto' : (indice >= 30 ? 'medio' : 'baixo');

    // ================================================================
    // KPIs de eficiência de trajeto (com flags)
    // ================================================================
    const trajetos = Number(v.trajetos_analisados || 0);
    const amostraInsuf = !!v.amostra_insuficiente;
    const amostraPequena = !!v.amostra_pequena;
    const temEficiencia = !amostraInsuf
        && v.indice_eficiencia_trajeto !== null
        && v.indice_eficiencia_trajeto !== undefined;

    // ----- Card Eficiência Trajeto -----
    let eficienciaCardHtml = '';
    if (temEficiencia) {
        const ef = Number(v.indice_eficiencia_trajeto);
        const efClass = ef >= 85 ? 'ok' : (ef >= 60 ? 'alerta' : 'critico');
        const efIcon = ef >= 85 ? 'fa-arrow-trend-up'
                      : (ef >= 60 ? 'fa-arrow-right' : 'fa-arrow-trend-down');
        const efLabel = ef >= 85 ? 'Trajeto Eficiente'
                       : (ef >= 60 ? 'Trajeto Aceitável' : 'Trajeto Ineficiente');

        const subtitulo = amostraPequena
            ? `⚠ Amostra pequena (${trajetos} trajeto${trajetos === 1 ? '' : 's'}) — use com cautela`
            : `${efLabel} • ${trajetos} trajetos`;

        eficienciaCardHtml = `
            <div class="stat eficiencia-card ${efClass}${amostraPequena ? ' com-aviso' : ''}">
                <strong>
                    <i class="fa-solid ${efIcon}"></i>
                    ${ef.toFixed(1)}%
                </strong>
                <span>Eficiência Trajeto</span>
                <small class="text-xs text-slate-400">${subtitulo}</small>
            </div>
        `;
    } else {
        eficienciaCardHtml = `
            <div class="stat eficiencia-card neutro">
                <strong>
                    <i class="fa-solid fa-circle-info"></i>
                    —
                </strong>
                <span>Eficiência Trajeto</span>
                <small class="text-xs text-slate-400">Dados insuficientes (${trajetos} trajeto${trajetos === 1 ? '' : 's'})</small>
            </div>
        `;
    }

    // ----- Cards de Tempo de Deslocamento -----
    let tempoDeslHtml = '';
    if (temEficiencia && v.tempo_medio_deslocamento_min !== null) {
        tempoDeslHtml = `
            <div class="stat">
                <strong>${Math.round(v.tempo_medio_deslocamento_min)} min</strong>
                <span>Tempo Médio Desl.</span>
                <small class="text-xs text-slate-400">média real entre paradas</small>
            </div>
            <div class="stat">
                <strong>${Math.round(v.tempo_ideal_medio_min ?? 0)} min</strong>
                <span>Tempo Ideal Médio</span>
                <small class="text-xs text-slate-400">a 40 km/h</small>
            </div>
            <div class="stat">
                <strong>${v.distancia_media_trajeto_km != null ? v.distancia_media_trajeto_km.toFixed(1) : '—'} km</strong>
                <span>Distância Média</span>
                <small class="text-xs text-slate-400">entre paradas</small>
            </div>
        `;
    } else {
        tempoDeslHtml = `
            <div class="stat">
                <strong>—</strong>
                <span>Tempo Médio Desl.</span>
                <small class="text-xs text-slate-400">sem trajetos válidos</small>
            </div>
        `;
    }

    if (conteudo) {
        conteudo.innerHTML = `
            <div class="detalhe-ranking-header">
                <div>
                    <strong style="font-size:1.1rem;">${escapeHtml(v.placa || '-')}</strong>
                    <div class="text-sm text-slate-500">${escapeHtml(v.marca || '')} ${escapeHtml(v.modelo || '')} • ${escapeHtml(v.tipo || '-')} • Status: ${escapeHtml(v.veiculo_status || '-')}</div>
                </div>
                <div class="indice-ineficiencia-bar" style="max-width:220px;">
                    <div class="indice-ineficiencia-bar-fill ${nivel}" style="width:${Math.min(indice, 100)}%"></div>
                    <div class="indice-ineficiencia-bar-label">Índice: ${indice.toFixed(1)}</div>
                </div>

                <!-- KPIs PRINCIPAIS -->
                <div class="detalhe-ranking-stats">
                    <div class="stat"><strong>${v.total_embarques ?? 0}</strong><span>Embarques</span></div>
                    <div class="stat"><strong>${v.total_entregas ?? 0}</strong><span>Entregas</span></div>
                    <div class="stat"><strong>${v.entregas_concluidas ?? 0}</strong><span>Concluídas</span></div>
                    <div class="stat"><strong>${v.entregas_atrasadas ?? 0}</strong><span>Atrasadas</span></div>
                    <div class="stat"><strong>${(v.taxa_divergencia ?? 0).toFixed(1)}%</strong><span>Divergência</span></div>
                    <div class="stat"><strong>${(v.taxa_no_prazo ?? 0).toFixed(1)}%</strong><span>No prazo</span></div>
                    <div class="stat"><strong>${Math.round(v.tempo_medio_entrega_min ?? 0)} min</strong><span>Tempo no cliente</span></div>
                    <div class="stat"><strong>${v.total_problemas ?? 0}</strong><span>Problemas</span></div>
                    <div class="stat"><strong>${v.faltantes ?? 0}</strong><span>Faltantes</span></div>
                    <div class="stat"><strong>${v.devolucoes ?? 0}</strong><span>Devoluções</span></div>
                    <div class="stat"><strong>${Math.round(v.peso_total_transportado ?? 0)} kg</strong><span>Peso Transportado</span></div>
                    <div class="stat"><strong>${formatarMoeda(v.valor_total_afetado ?? 0)}</strong><span>Valor Afetado</span></div>
                </div>

                <!-- KPIs NOVOS: EFICIÊNCIA DE TRAJETO -->
                <div class="detalhe-ranking-stats detalhe-ranking-stats-eficiencia">
                    ${eficienciaCardHtml}
                    ${tempoDeslHtml}
                </div>
            </div>
        `;
    }

    const modal = new bootstrap.Modal(document.getElementById('modalDetalheRanking'));
    modal.show();
}

// ================================================================
// ABA: GRÁFICOS PREMIUM (Chart.js)
// ================================================================
const CORES_GRAFICO = {
    primaria: '#1a3c34',
    dourado: '#c9a227',
    sucesso: '#10b981',
    alerta: '#f59e0b',
    perigo: '#dc2626',
    info: '#3b82f6',
    roxo: '#8b5cf6',
    palette: ['#1a3c34', '#c9a227', '#3b82f6', '#dc2626', '#8b5cf6', '#10b981', '#f59e0b']
};

async function carregarGraficosCargas() {
    const token = getAuthToken();
    if (!token) return;

    const dias = document.getElementById('filtro-graficos-dias')?.value || 14;

    try {
        const response = await fetch(`${CONFIG.API_BASE}/gestao-cargas/graficos?dias=${dias}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('Falha ao carregar gráficos');
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro desconhecido');

        graficosCarregados = true;
        renderizarGraficosCargas(payload.data || {});
    } catch (error) {
        console.error('Erro ao carregar gráficos de gestão de cargas:', error);
        mostrarNotificacao('Erro ao carregar gráficos', 'error');
    }
}

function destruirChart(id) {
    if (chartInstances[id]) {
        chartInstances[id].destroy();
        delete chartInstances[id];
    }
}

function renderizarGraficosCargas(data) {
    if (typeof Chart === 'undefined') return;

    // 1. Evolução diária (linha)
    destruirChart('evolucao');
    const ctxEvolucao = document.getElementById('chart-evolucao');
    if (ctxEvolucao) {
        const evolucao = data.evolucao_diaria || [];
        chartInstances.evolucao = new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: evolucao.map(e => e.label),
                datasets: [
                    {
                        label: 'Problemas Criados',
                        data: evolucao.map(e => e.criados),
                        borderColor: CORES_GRAFICO.perigo,
                        backgroundColor: 'rgba(220, 38, 38, .08)',
                        tension: .35,
                        fill: true,
                        pointRadius: 3
                    },
                    {
                        label: 'Problemas Resolvidos',
                        data: evolucao.map(e => e.resolvidos),
                        borderColor: CORES_GRAFICO.sucesso,
                        backgroundColor: 'rgba(16, 185, 129, .08)',
                        tension: .35,
                        fill: true,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // 2. Distribuição por tipo (doughnut)
    destruirChart('tipo');
    const ctxTipo = document.getElementById('chart-tipo');
    if (ctxTipo) {
        const porTipo = data.por_tipo || [];
        chartInstances.tipo = new Chart(ctxTipo, {
            type: 'doughnut',
            data: {
                labels: porTipo.map(t => (t.tipo_problema || '-').replace(/_/g, ' ')),
                datasets: [{
                    data: porTipo.map(t => t.total),
                    backgroundColor: CORES_GRAFICO.palette,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom' } }, cutout: '62%' }
        });
    }

    // 3. Distribuição por prioridade (barra horizontal)
    destruirChart('prioridade');
    const ctxPrioridade = document.getElementById('chart-prioridade');
    if (ctxPrioridade) {
        const porPrioridade = data.por_prioridade || [];
        const ordem = ['critica', 'alta', 'media', 'baixa'];
        const ordenado = [...porPrioridade].sort((a, b) => ordem.indexOf(a.prioridade) - ordem.indexOf(b.prioridade));
        const coresPrioridade = { critica: CORES_GRAFICO.perigo, alta: CORES_GRAFICO.alerta, media: CORES_GRAFICO.info, baixa: CORES_GRAFICO.sucesso };
        chartInstances.prioridade = new Chart(ctxPrioridade, {
            type: 'bar',
            data: {
                labels: ordenado.map(p => (p.prioridade || '-').toUpperCase()),
                datasets: [{
                    label: 'Problemas ativos',
                    data: ordenado.map(p => p.total),
                    backgroundColor: ordenado.map(p => coresPrioridade[p.prioridade] || CORES_GRAFICO.primaria),
                    borderRadius: 8
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // 4. Top motoristas com mais problemas (barra)
    destruirChart('topMotoristas');
    const ctxTopMotoristas = document.getElementById('chart-top-motoristas');
    if (ctxTopMotoristas) {
        const topM = data.top_motoristas_problemas || [];
        chartInstances.topMotoristas = new Chart(ctxTopMotoristas, {
            type: 'bar',
            data: {
                labels: topM.map(m => m.motorista_nome),
                datasets: [{
                    label: 'Problemas',
                    data: topM.map(m => m.total_problemas),
                    backgroundColor: CORES_GRAFICO.dourado,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }

    // 5. Top veículos com mais problemas (barra)
    destruirChart('topVeiculos');
    const ctxTopVeiculos = document.getElementById('chart-top-veiculos');
    if (ctxTopVeiculos) {
        const topV = data.top_veiculos_problemas || [];
        chartInstances.topVeiculos = new Chart(ctxTopVeiculos, {
            type: 'bar',
            data: {
                labels: topV.map(v => v.placa),
                datasets: [{
                    label: 'Problemas',
                    data: topV.map(v => v.total_problemas),
                    backgroundColor: CORES_GRAFICO.primaria,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
            }
        });
    }
}


async function carregarHistoricoEmbarques() {
    const token = getAuthToken();
    if (!token) return;

    const lista = document.getElementById('hist-lista-embarques');
    if (lista) lista.innerHTML = '<div class="text-center py-8">Carregando...</div>';

    let url = `${CONFIG.API_BASE}/gestao-cargas/historico-embarques?pagina=${historicoState.pagina}&limite=15`;
    if (historicoState.busca) url += `&busca=${encodeURIComponent(historicoState.busca)}`;
    if (historicoState.status && historicoState.status !== 'todos') url += `&status=${historicoState.status}`;
    if (historicoState.dataInicio) url += `&data_inicio=${historicoState.dataInicio}`;
    if (historicoState.dataFim) url += `&data_fim=${historicoState.dataFim}`;

    try {
        const response = await fetch(url, { headers: { 'Authorization': 'Bearer ' + token } });
        if (!response.ok) throw new Error('Falha ao carregar histórico');
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro desconhecido');

        renderizarHistoricoEmbarques(payload.data || [], payload.pagination || {});
    } catch (error) {
        console.error('Erro ao carregar histórico de embarques:', error);
        if (lista) lista.innerHTML = '<div class="text-center py-8 text-red-500">Erro ao carregar histórico de embarques</div>';
    }
}

function renderizarHistoricoEmbarques(dados, pagination) {
    const lista = document.getElementById('hist-lista-embarques');
    const info = document.getElementById('hist-info-registros');
    const infoPag = document.getElementById('hist-info-paginacao');

    historicoState.totalPaginas = pagination.total_paginas || 1;
    if (info) info.textContent = `${pagination.total ?? 0} embarque(s) encontrado(s)`;
    if (infoPag) infoPag.textContent = `Página ${historicoState.pagina} de ${historicoState.totalPaginas}`;

    document.getElementById('hist-pagina-atual').textContent = historicoState.pagina;
    document.getElementById('hist-btn-anterior').disabled = historicoState.pagina <= 1;
    document.getElementById('hist-btn-proximo').disabled = historicoState.pagina >= historicoState.totalPaginas;

    if (!lista) return;

    if (!dados.length) {
        lista.innerHTML = '<div class="empty-state-cargas">Nenhum embarque encontrado com os filtros atuais.</div>';
        return;
    }

    const statusLabels = {
        planejado: 'Planejado', em_andamento: 'Em andamento',
        finalizado: 'Finalizado', cancelado: 'Cancelado', problema: 'Com problema'
    };

    lista.innerHTML = dados.map(e => {
        const status = e.embarque_status || 'planejado';
        let acertoLabel = 'Sem acerto';
        let acertoClass = 'pendente';
        if (e.acerto_status === 'finalizado') { acertoLabel = 'Conferido total'; acertoClass = ''; }
        else if (e.acerto_id) { acertoLabel = 'Conferido parcial'; acertoClass = 'parcial'; }

        return `
        <div class="hist-embarque-card" onclick="abrirDetalheEmbarque(${e.id})">
            <div class="hist-embarque-main">
                <div class="hist-embarque-icon"><i class="fa-solid fa-truck-fast"></i></div>
                <div class="hist-embarque-info">
                    <strong>${escapeHtml(e.numero_embarque || ('#' + e.id))}</strong>
                    <span>${escapeHtml(e.motorista_nome || 'Sem motorista')} • ${escapeHtml(e.veiculo_placa || '-')} • ${formatarData(e.data_saida)}</span>
                </div>
            </div>
            <div class="hist-embarque-meta">
                <div class="meta-item"><strong>${e.entregas_concluidas ?? 0}/${e.total_entregas ?? 0}</strong><span>Entregas</span></div>
                <div class="meta-item"><strong>${e.total_problemas ?? 0}</strong><span>Problemas</span></div>
                <span class="hist-acerto-badge ${acertoClass}">${acertoLabel}</span>
                <span class="hist-status-badge ${status}">${statusLabels[status] || status}</span>
            </div>
        </div>`;
    }).join('');
}

function mudarPaginaHistorico(direcao) {
    if (direcao === 'anterior' && historicoState.pagina > 1) historicoState.pagina--;
    if (direcao === 'proximo' && historicoState.pagina < historicoState.totalPaginas) historicoState.pagina++;
    carregarHistoricoEmbarques();
}

// Instância persistente do modal de detalhe de embarque (evita memory leak)
let modalDetalheEmbarqueInstance = null;

async function abrirDetalheEmbarque(embarqueId) {
    const modalEl = document.getElementById('modalDetalheEmbarque');
    const conteudo = document.getElementById('detalhe-embarque-conteudo');
    const numeroEl = document.getElementById('detalhe-embarque-numero');
    if (conteudo) conteudo.innerHTML = '<div class="text-center py-8"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando...</div>';

    if (!modalDetalheEmbarqueInstance) {
        modalDetalheEmbarqueInstance = new bootstrap.Modal(modalEl);
    }
    modalDetalheEmbarqueInstance.show();


    const token = getAuthToken();
    try {
        const response = await fetch(`${CONFIG.API_BASE}/gestao-cargas/embarque/${embarqueId}/detalhes-completos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        if (!response.ok) throw new Error('Falha ao buscar embarque');
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro desconhecido');

        const dados = payload.data || {};
        if (numeroEl) numeroEl.textContent = dados.numero_embarque || ('#' + embarqueId);
        if (conteudo) conteudo.innerHTML = montarHtmlDetalheEmbarque(dados);
    } catch (error) {
        console.error('Erro ao abrir detalhe do embarque:', error);
        if (conteudo) conteudo.innerHTML = '<div class="text-center py-8 text-red-500">Erro ao carregar detalhes do embarque</div>';
    }
}

function montarHtmlDetalheEmbarque(dados) {
    const entregas = dados.entregas || [];
    const resumo = dados.resumo || {};
    const totalEntregas = resumo.total_entregas ?? entregas.length;
    const concluidas = resumo.entregas_concluidas ?? entregas.filter(e => e.status === 'entregue' || e.status === 'entregue_com_problema').length;
    const totalProblemas = resumo.total_problemas ?? 0;

    const header = `
        <div class="detalhe-embarque-header">
            <div>
                <strong>${escapeHtml(dados.motorista_nome || 'Sem motorista')}</strong>
                <div class="text-sm text-slate-500">${escapeHtml(dados.veiculo_placa || '-')} • ${formatarData(dados.data_saida)}${dados.acerto ? ' • Conferência: ' + escapeHtml(dados.acerto.status || '-') : ''}</div>
            </div>
            <div class="detalhe-embarque-stats">
                <div class="stat"><strong>${totalEntregas}</strong><span>Entregas</span></div>
                <div class="stat"><strong>${concluidas}</strong><span>Concluídas</span></div>
                <div class="stat"><strong>${totalProblemas}</strong><span>Problemas</span></div>
                <div class="stat"><strong>${resumo.percentual_concluido ?? 0}%</strong><span>Progresso</span></div>
            </div>
        </div>
    `;

    // Timeline geral do embarque (logs)
    const logs = dados.timeline_embarque || [];
    const timelineHtml = logs.length ? `
        <div class="detalhe-embarque-timeline">
            <h4><i class="fa-solid fa-timeline mr-1"></i> Timeline do Embarque</h4>
            <div class="timeline-embarque-lista">
                ${logs.map(l => `
                    <div class="timeline-embarque-item">
                        <div class="timeline-embarque-ponto"></div>
                        <div class="timeline-embarque-conteudo">
                            <strong>${escapeHtml(l.acao || '-')}</strong>
                            <span class="text-xs text-slate-500">${formatarDataHora(l.created_at)} • ${escapeHtml(l.usuario_nome || 'Sistema')}</span>
                            ${l.descricao ? `<div class="text-sm">${escapeHtml(l.descricao)}</div>` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        </div>
    ` : '';

    if (!entregas.length) {
        return header + timelineHtml + '<div class="empty-state-cargas">Nenhuma entrega registrada neste embarque.</div>';
    }

    const lista = entregas.map(e => {
        const comProb = e.status === 'entregue_com_problema' || e.status === 'falha' || (e.problemas && e.problemas.length);
        const fotos = (e.checklist || []).filter(c => c.foto_url);
        const clienteNome = e.cliente_nome || e.cliente_nome_cadastro || 'Cliente não identificado';

        const itensHtml = (e.checklist || []).length ? `
            <div class="entrega-item-itens-grid">
                ${e.checklist.map(item => {
                    const divergente = item.status && item.status !== 'ok' && item.status !== 'conforme';
                    return `
                    <div class="entrega-item-card ${divergente ? 'item-divergente' : ''}">
                        ${item.foto_url ? `<img src="${escapeHtml(item.foto_url)}" onclick="abrirZoomFoto('${escapeHtml(item.foto_url)}', '${escapeHtml(clienteNome)}')" alt="Foto item">` : '<div class="item-sem-foto"><i class="fa-solid fa-image"></i></div>'}
                        <div class="entrega-item-card-info">
                            <strong>${escapeHtml(item.descricao || item.referencia || '-')}</strong>
                            <span>Prev: ${item.quantidade_prevista ?? '-'} • Entregue: ${item.quantidade_entregue ?? '-'}</span>
                            ${item.motivo ? `<span class="text-red-500">${escapeHtml(item.motivo)}</span>` : ''}
                        </div>
                    </div>`;
                }).join('')}
            </div>
        ` : '';

        const problemasHtml = (e.problemas || []).length ? `
            <div class="entrega-item-problemas">
                ${e.problemas.map(p => `
                    <span class="problema-badge ${p.status_problema || ''}">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        ${escapeHtml(p.tipo_problema || '-')}: ${escapeHtml(p.descricao_problema || '-')} (${escapeHtml(p.status_problema || '-')})
                    </span>
                `).join('')}
            </div>
        ` : '';

        return `
        <div class="detalhe-entrega-item ${comProb ? 'com-problema' : ''}">
            <div class="entrega-item-head">
                <strong>${escapeHtml(clienteNome)}</strong>
                <span class="hist-status-badge ${e.status || ''}">${escapeHtml(e.status || '-')}</span>
            </div>
            <div class="text-xs text-slate-500 mt-1">${escapeHtml(e.cliente_cidade || '')}/${escapeHtml(e.cliente_uf || '')} • Pedido(s): ${escapeHtml(e.pedidos_ids || '-')}</div>
            ${itensHtml}
            ${problemasHtml}
        </div>`;
    }).join('');

    return header + timelineHtml + `<div class="detalhe-entregas-lista">${lista}</div>`;
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

// ================================================================
// ABA: RASTREIO COBLI (INTEGRAÇÃO DE RASTREAMENTO VEICULAR REAL)
// ================================================================
async function carregarStatusCobli() {
    const token = getAuthToken();
    const badge = document.getElementById('cobli-status-badge');
    const detalhe = document.getElementById('cobli-status-detalhe');
    if (badge) badge.textContent = 'Verificando...';

    try {
        const response = await fetch(`${CONFIG.API_BASE}/cobli/status`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const payload = await response.json();
        const dados = payload.data || {};

        if (badge) {
            badge.textContent = dados.conexao_ok ? 'Conectado' : (dados.configurado ? 'Falha na conexão' : 'Não configurado');
            badge.className = 'hist-status-badge ' + (dados.conexao_ok ? 'finalizado' : (dados.configurado ? 'cancelado' : 'planejado'));
        }
        if (detalhe) {
            detalhe.innerHTML = `<i class="fa-solid ${dados.conexao_ok ? 'fa-circle-check text-green-600' : 'fa-circle-exclamation text-amber-500'} mr-1"></i> ${escapeHtml(dados.detalhe || '')}`;
        }

        if (dados.conexao_ok) {
            carregarMapaCobli();
        }
    } catch (error) {
        console.error('Erro ao verificar status da Cobli:', error);
        if (badge) { badge.textContent = 'Erro'; badge.className = 'hist-status-badge cancelado'; }
        if (detalhe) detalhe.textContent = 'Não foi possível verificar o status da integração.';
    }
}


// ----------------------------------------------------------------
// Mapa ao vivo (MapLibre GL + OpenFreeMap) com a posição dos veículos vinculados
// ----------------------------------------------------------------
let cobliMapa = null;
let cobliMapaMarcadores = {};

function inicializarMapaCobli() {
    if (cobliMapa) return cobliMapa;
    const el = document.getElementById('cobli-mapa');
    if (!el || typeof maplibregl === 'undefined') return null;

    cobliMapa = new maplibregl.Map({
        container: el,
        style: 'https://tiles.openfreemap.org/styles/liberty',
        center: [-51.925, -14.235], // centro do Brasil por padrão
        zoom: 4,
        attributionControl: true
    });
    cobliMapa.addControl(new maplibregl.NavigationControl(), 'top-right');

    return cobliMapa;
}

async function carregarMapaCobli() {
    const token = getAuthToken();
    const vazio = document.getElementById('cobli-mapa-vazio');
    const mapaEl = document.getElementById('cobli-mapa');
    const atualizadoEl = document.getElementById('cobli-mapa-atualizado');

    try {
        const response = await fetch(`${CONFIG.API_BASE}/cobli/frota/posicoes`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const payload = await response.json();
        if (!payload.success) throw new Error(payload.error || 'Erro ao buscar posições');

        const veiculos = payload.data || [];

        if (!veiculos.length) {
            if (vazio) vazio.style.display = 'block';
            if (mapaEl) mapaEl.style.display = 'none';
            return;
        }

        if (vazio) vazio.style.display = 'none';
        if (mapaEl) mapaEl.style.display = 'block';

        const mapa = inicializarMapaCobli();
        if (!mapa) return;

        // Remove marcadores antigos que não existem mais
        Object.keys(cobliMapaMarcadores).forEach(id => {
            if (!veiculos.some(v => String(v.veiculo_id) === id)) {
                cobliMapaMarcadores[id].remove();
                delete cobliMapaMarcadores[id];
            }
        });

        const bounds = new maplibregl.LngLatBounds();
        veiculos.forEach(v => {
            const id = String(v.veiculo_id);
            const lngLat = [v.longitude, v.latitude];
            bounds.extend(lngLat);

            const popupHtml = `
                <strong>${escapeHtml(v.placa || 'Veículo')} ${v.modelo ? '- ' + escapeHtml(v.modelo) : ''}</strong><br>
                ${v.motorista ? 'Motorista: ' + escapeHtml(v.motorista) + '<br>' : ''}
                Velocidade: ${v.velocidade ?? '-'} km/h<br>
                Ignição: ${v.ignicao_ligada ? 'Ligada' : 'Desligada'}<br>
                <span class="text-xs text-slate-400">Atualizado: ${v.atualizado_em ? new Date(v.atualizado_em).toLocaleString('pt-BR') : '-'}</span>
            `;

            if (cobliMapaMarcadores[id]) {
                cobliMapaMarcadores[id].setLngLat(lngLat);
                cobliMapaMarcadores[id].getPopup().setHTML(popupHtml);
            } else {
                const el = document.createElement('div');
                el.className = 'cadfrota-mapa-marcador';
                const emMovimento = (v.velocidade || 0) > 0;
                el.innerHTML = `
                    <div class="cadfrota-mapa-placa">${escapeHtml(v.placa || '-')}</div>
                    <div class="cadfrota-mapa-icone ${emMovimento ? '' : 'parado'}"><i class="fa-solid fa-truck"></i></div>
                `;

                cobliMapaMarcadores[id] = new maplibregl.Marker({ element: el })
                    .setLngLat(lngLat)
                    .setPopup(new maplibregl.Popup({ offset: 30 }).setHTML(popupHtml))
                    .addTo(mapa);
            }
        });

        if (veiculos.length > 1) {
            mapa.fitBounds(bounds, { padding: 40, maxZoom: 14 });
        } else if (veiculos.length === 1) {
            mapa.flyTo({ center: [veiculos[0].longitude, veiculos[0].latitude], zoom: 14 });
        }

        if (atualizadoEl) {
            atualizadoEl.textContent = 'Atualizado às ' + new Date().toLocaleTimeString('pt-BR');
        }
    } catch (error) {
        console.error('Erro ao carregar mapa da Cobli:', error);
        if (vazio) {
            vazio.style.display = 'block';
            vazio.textContent = 'Erro ao carregar posições dos veículos.';
        }
        if (mapaEl) mapaEl.style.display = 'none';
    }
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Tema
    const saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.querySelector('.theme-toggle i');
    if (icon) icon.className = saved === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';

    // ================================================================
    // Restaura filtros de eficiência ANTES de carregar dados
    // ================================================================
    restaurarFiltrosEficiencia();

    // Carregar dados
    carregarDados();

    // Atualização automática a cada 60 segundos
    setInterval(() => carregarDados(), 60000);

    // Filtro de busca com debounce
    const buscaInput = document.getElementById('filtro-busca');
    if (buscaInput) {
        buscaInput.addEventListener('input', debounce(function() {
            state.filtroBusca = this.value;
            state.paginaAtual = 1;
            cache.dados = null;
            cache.timestamp = null;
            atualizarAcaoLimparFiltros();
            carregarDados();
        }, 400));
    }

    const prioridadeSelect = document.getElementById('filtro-prioridade');
    if (prioridadeSelect) prioridadeSelect.addEventListener('change', function() { aplicarPrioridade(this.value); });
    const limparButton = document.getElementById('limpar-filtros');
    if (limparButton) limparButton.addEventListener('click', limparFiltros);

    // Filtros da aba Desempenho de Motoristas
    const motoristasDias = document.getElementById('filtro-motoristas-dias');
    if (motoristasDias) motoristasDias.addEventListener('change', carregarRankingMotoristas);

    // Filtros da aba Por Caminhão
    const veiculosDias = document.getElementById('filtro-veiculos-dias');
    if (veiculosDias) veiculosDias.addEventListener('change', carregarRankingVeiculos);

    // Filtro da aba Gráficos
    const graficosDias = document.getElementById('filtro-graficos-dias');
    if (graficosDias) graficosDias.addEventListener('change', carregarGraficosCargas);

    // Filtros da aba Histórico de Embarques
    const histBusca = document.getElementById('hist-busca');
    if (histBusca) histBusca.addEventListener('input', debounce(function() {
        historicoState.busca = this.value;
        historicoState.pagina = 1;
        carregarHistoricoEmbarques();
    }, 400));

    const histStatus = document.getElementById('hist-status');
    if (histStatus) histStatus.addEventListener('change', function() {
        historicoState.status = this.value;
        historicoState.pagina = 1;
        carregarHistoricoEmbarques();
    });

    const histDataInicio = document.getElementById('hist-data-inicio');
    if (histDataInicio) histDataInicio.addEventListener('change', function() {
        historicoState.dataInicio = this.value;
        historicoState.pagina = 1;
        carregarHistoricoEmbarques();
    });

    const histDataFim = document.getElementById('hist-data-fim');
    if (histDataFim) histDataFim.addEventListener('change', function() {
        historicoState.dataFim = this.value;
        historicoState.pagina = 1;
        carregarHistoricoEmbarques();
    });

    const histLimpar = document.getElementById('hist-limpar-filtros');
    if (histLimpar) histLimpar.addEventListener('click', function() {
        historicoState = { pagina: 1, totalPaginas: 1, busca: '', status: 'todos', dataInicio: '', dataFim: '' };
        if (histBusca) histBusca.value = '';
        if (histStatus) histStatus.value = 'todos';
        if (histDataInicio) histDataInicio.value = '';
        if (histDataFim) histDataFim.value = '';
        carregarHistoricoEmbarques();
    });

    // Aba Rastreio (Cobli)
    const cobliAtualizarMapa = document.getElementById('cobli-atualizar-mapa');
    if (cobliAtualizarMapa) cobliAtualizarMapa.addEventListener('click', carregarMapaCobli);

    // Limpar cache ao mudar página
    window.addEventListener('beforeunload', function() {
        cache.dados = null;
        cache.timestamp = null;
    });
});

// ================================================================
// DEBOUNCE
// ================================================================
function debounce(fn, delay) {
    let timer;
    return function(...args) {
        clearTimeout(timer);
        timer = setTimeout(() => fn.apply(this, args), delay);
    };
}

// ================================================================
// EXPORTAÇÕES GLOBAIS
// ================================================================
window.carregarDados = carregarDados;
window.aplicarFiltro = aplicarFiltro;
window.mudarPagina = mudarPagina;
window.verAnalise = verAnalise;
window.resolverProblema = resolverProblema;
window.iniciarAnalise = iniciarAnalise;
window.adicionarAnalise = adicionarAnalise;
window.exportarCSV = exportarCSV;
window.abrirZoomFoto = abrirZoomFoto;
window.fecharZoom = fecharZoom;
window.verFotoItem = verFotoItem;
window.toggleTheme = toggleTheme;
window.mostrarNotificacao = mostrarNotificacao;
window.fecharModalAnalise = fecharModalAnalise;
window.mudarAbaCargas = mudarAbaCargas;
window.carregarRankingMotoristas = carregarRankingMotoristas;
window.carregarRankingVeiculos = carregarRankingVeiculos;
window.carregarGraficosCargas = carregarGraficosCargas;
window.abrirDetalheMotorista = abrirDetalheMotorista;
window.abrirDetalheVeiculo = abrirDetalheVeiculo;
window.mudarPaginaHistorico = mudarPaginaHistorico;
window.abrirDetalheEmbarque = abrirDetalheEmbarque;
window.alternarFiltroEficiencia = alternarFiltroEficiencia;
window.restaurarFiltrosEficiencia = restaurarFiltrosEficiencia;

// ================================================================
// 🔥 NOVO 2026-09-22 (Bloco 5.B.2):
// DASHBOARD EXECUTIVO — Aba "Dashboard"
// Carrega KPIs, gráficos e destaques com rastreabilidade
// ================================================================

// Cache de controle — evita recarregar a aba toda vez que o gestor clica
let dashCarregada = false;
let dashCharts = {};

// ---------------------------------------------------------------
// Orquestrador: carrega todos os dados da aba Dashboard em paralelo
// ---------------------------------------------------------------
async function carregarAbaDashboard(forcar = false) {
    if (dashCarregada && !forcar) return;

    const token = getAuthToken();
    if (!token) return;

    // Esqueleto visual (placeholders)
    preencherPlaceholdersDashboard();

    try {
        // ============================================================
        // 1. Buscar todos os endpoints em paralelo
        // ============================================================
        const [
            kpisResp,
            problemasResp,
            acertoResp,
            graficosResp,
            embarquesResp
        ] = await Promise.all([
            fetch(`${CONFIG.API_BASE}/dashboard/kpis`, {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => ({})),

            fetch(`${CONFIG.API_BASE}/dashboard/kpis-problemas`, {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => ({})),

            fetch(`${CONFIG.API_BASE}/dashboard/acerto-kpis`, {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => ({})),

            fetch(`${CONFIG.API_BASE}/dashboard/graficos`, {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => ({})),

            fetch(`${CONFIG.API_BASE}/gestao-cargas/historico-embarques?pagina=1&limite=5`, {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(r => r.json()).catch(() => ({}))
        ]);

        const kpis       = kpisResp.data       || {};
        const problemas  = problemasResp.data  || {};
        const acerto     = acertoResp.data     || {};
        const graficos   = graficosResp.data   || {};
        const embarques  = embarquesResp.data  || [];

        // ============================================================
        // 2. Preencher os 6 KPIs
        // ============================================================
        preencherKpisDashboard(kpis, problemas, acerto);

        // ============================================================
        // 3. Renderizar os 3 gráficos
        // ============================================================
        renderizarGraficosDashboard(graficos);

        // ============================================================
        // 4. Ranking top 5 motoristas
        // ============================================================
        renderizarTopMotoristasDashboard(graficos.top_motoristas || []);

        // ============================================================
        // 5. Últimos 5 embarques
        // ============================================================
        renderizarUltimosEmbarques(embarques);

        dashCarregada = true;

    } catch (error) {
        console.error('Erro ao carregar Dashboard:', error);
        mostrarNotificacao('Erro ao carregar o dashboard executivo', 'error');
    }
}

// ---------------------------------------------------------------
// Placeholders antes de carregar (UX)
// ---------------------------------------------------------------
function preencherPlaceholdersDashboard() {
    const ids = [
        'dash-kpi-entregas', 'dash-kpi-motoristas', 'dash-kpi-problemas',
        'dash-kpi-acerto', 'dash-kpi-taxa', 'dash-kpi-faturamento'
    ];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = '...';
    });

    const subs = [
        'dash-kpi-entregas-sub', 'dash-kpi-motoristas-sub', 'dash-kpi-problemas-sub',
        'dash-kpi-acerto-sub', 'dash-kpi-taxa-sub', 'dash-kpi-faturamento-sub'
    ];
    subs.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = 'carregando';
    });
}

// ---------------------------------------------------------------
// Preencher os 6 KPIs com dados dos endpoints
// ---------------------------------------------------------------
function preencherKpisDashboard(kpis, problemas, acerto) {
    const num = (v) => new Intl.NumberFormat('pt-BR').format(Number(v || 0));
    const pct = (v) => `${Number(v || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
    const money = (v) => new Intl.NumberFormat('pt-BR', {
        style: 'currency', currency: 'BRL', minimumFractionDigits: 0, maximumFractionDigits: 0
    }).format(Number(v || 0));

    // KPI 1 — Entregas hoje
    const entregasHoje = kpis.entregas_hoje || 0;
    const entregasConcluidas = kpis.entregas_concluidas_hoje || 0;
    setTexto('dash-kpi-entregas', num(entregasHoje));
    setTexto('dash-kpi-entregas-sub', `${num(entregasConcluidas)} concluídas`);

    // KPI 2 — Motoristas em rota
    const emRota = kpis.motoristas_em_rota || 0;
    const ativos = kpis.motoristas_ativos || 0;
    setTexto('dash-kpi-motoristas', num(emRota));
    setTexto('dash-kpi-motoristas-sub', `${num(ativos)} ativos`);

    // KPI 3 — Problemas pendentes
    const problemasPend = problemas.pendentes || 0;
    const emAnalise = problemas.em_analise || 0;
    setTexto('dash-kpi-problemas', num(problemasPend));
    setTexto('dash-kpi-problemas-sub', `${num(emAnalise)} em análise`);

    // KPI 4 — Faltantes / Devoluções
    const faltantes = acerto.faltantes || {};
    const devolucoes = acerto.devolucoes || {};
    const totalAcerto = (faltantes.total || 0) + (devolucoes.total || 0);
    const faltPend = faltantes.pendentes || 0;
    const compEmit = devolucoes.comprovantes_emitidos || 0;
    setTexto('dash-kpi-acerto', num(totalAcerto));
    setTexto('dash-kpi-acerto-sub', `${num(faltPend)} falt. pendentes · ${num(compEmit)} comprovantes`);

    // KPI 5 — Taxa de acerto (entregas concluídas / entregas hoje)
    const taxa = entregasHoje > 0
        ? (entregasConcluidas / entregasHoje) * 100
        : 0;
    setTexto('dash-kpi-taxa', pct(taxa));
    setTexto('dash-kpi-taxa-sub', `${num(entregasConcluidas)}/${num(entregasHoje)} entregas`);

    // KPI 6 — Faturamento do mês
    const fat = kpis.faturamento_mes || 0;
    setTexto('dash-kpi-faturamento', money(fat));
    setTexto('dash-kpi-faturamento-sub', `${num(kpis.total_entregas_mes || 0)} entregas no mês`);
}

function setTexto(id, valor) {
    const el = document.getElementById(id);
    if (el) el.textContent = valor;
}

// ---------------------------------------------------------------
// Renderizar os 3 gráficos do Dashboard
// ---------------------------------------------------------------
function renderizarGraficosDashboard(data) {
    if (typeof Chart === 'undefined') return;

    // Destruir anteriores
    Object.keys(dashCharts).forEach(id => {
        if (dashCharts[id]) dashCharts[id].destroy();
    });
    dashCharts = {};

    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    const ink = isDark ? '#eaf2ee' : '#18332d';

    // ---------- Gráfico 1: Ritmo de entregas 7 dias ----------
    const ctx1 = document.getElementById('dash-chart-entregas');
    if (ctx1) {
        dashCharts.entregas = new Chart(ctx1, {
            type: 'line',
            data: {
                labels: data.dias || [],
                datasets: [
                    {
                        label: 'Concluídas',
                        data: data.concluidas || [],
                        borderColor: '#2e8b68',
                        backgroundColor: 'rgba(46,139,104,.12)',
                        fill: true,
                        tension: .35,
                        pointRadius: 3
                    },
                    {
                        label: 'Pendentes',
                        data: data.pendentes || [],
                        borderColor: '#d27b32',
                        backgroundColor: 'transparent',
                        tension: .35,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: ink, usePointStyle: true }
                    }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: ink } },
                    x: { ticks: { color: ink } }
                }
            }
        });
    }

    // ---------- Gráfico 2: Status das entregas (doughnut) ----------
    const ctx2 = document.getElementById('dash-chart-status');
    if (ctx2) {
        const status = data.status_distribution || {};
        dashCharts.status = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Concluídas', 'Pendentes', 'Em andamento', 'Falhas', 'Canceladas'],
                datasets: [{
                    data: [
                        status.concluidas   || 0,
                        status.pendentes    || 0,
                        status.em_andamento || 0,
                        status.falha        || 0,
                        status.canceladas   || 0
                    ],
                    backgroundColor: ['#2e8b68', '#d27b32', '#3979a8', '#b84b4b', '#9aa8a2'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '66%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: ink, usePointStyle: true, padding: 12 }
                    }
                }
            }
        });
    }

    // ---------- Gráfico 3: Ritmo do mês (barras) ----------
    const ctx3 = document.getElementById('dash-chart-mes');
    if (ctx3) {
        // Por enquanto usa dados mock — vai ser ajustado quando tiver endpoint mensal
        const hoje = new Date();
        const diasMes = new Date(hoje.getFullYear(), hoje.getMonth() + 1, 0).getDate();
        const labels = [];
        const valoresMock = [];
        for (let i = 1; i <= Math.min(diasMes, 30); i++) {
            labels.push(String(i));
            // Mock: pico entre 10-50 entregas
            valoresMock.push(Math.floor(Math.random() * 40) + 10);
        }

        dashCharts.mes = new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Entregas',
                    data: valoresMock,
                    backgroundColor: 'rgba(46,139,104,.7)',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0, color: ink } },
                    x: { ticks: { color: ink, maxTicksLimit: 10 } }
                }
            }
        });
    }
}

// ---------------------------------------------------------------
// Ranking top 5 motoristas (clicável)
// ---------------------------------------------------------------
function renderizarTopMotoristasDashboard(items) {
    const target = document.getElementById('dash-top-motoristas');
    if (!target) return;

    if (!items || !items.length) {
        target.innerHTML = '<div class="cargas-em-construcao-mini">Nenhum dado de performance disponível.</div>';
        return;
    }

    target.innerHTML = items.slice(0, 5).map((item, index) => `
        <div class="cargas-ranking-row" onclick="abrirDetalheMotorista(${item.id || 0})">
            <span class="cargas-ranking-pos">${String(index + 1).padStart(2, '0')}</span>
            <div class="cargas-ranking-info">
                <strong>${escapeHtml(item.nome || 'Motorista')}</strong>
                <small>${new Intl.NumberFormat('pt-BR').format(Number(item.total_faturado || 0))} em entregas</small>
            </div>
            <span class="cargas-ranking-value">${new Intl.NumberFormat('pt-BR').format(Number(item.total_entregas || 0))}</span>
        </div>
    `).join('');
}

// ---------------------------------------------------------------
// Últimos 5 embarques (clicável)
// ---------------------------------------------------------------
function renderizarUltimosEmbarques(embarques) {
    const target = document.getElementById('dash-ultimos-embarques');
    if (!target) return;

    if (!embarques || !embarques.length) {
        target.innerHTML = '<div class="cargas-em-construcao-mini">Nenhum embarque encontrado.</div>';
        return;
    }

    const statusLabels = {
        planejado: 'Planejado',
        em_andamento: 'Em andamento',
        finalizado: 'Finalizado',
        cancelado: 'Cancelado',
        problema: 'Com problema'
    };

    target.innerHTML = embarques.slice(0, 5).map(e => {
        const status = e.embarque_status || 'planejado';
        const numero = e.numero_embarque || ('#' + e.id);
        const motorista = e.motorista_nome || 'Sem motorista';
        const placa = e.veiculo_placa || '-';
        const entregas = `${e.entregas_concluidas || 0}/${e.total_entregas || 0}`;
        const data = e.data_saida ? formatarData(e.data_saida) : '-';

        return `
            <div class="cargas-ultimo-row" onclick="dashIrParaMapaComEmbarque(${e.id})">
                <div class="cargas-ultimo-info">
                    <strong>${escapeHtml(numero)}</strong>
                    <small>${escapeHtml(motorista)} · ${escapeHtml(placa)} · ${escapeHtml(data)}</small>
                </div>
                <div class="cargas-ultimo-meta">
                    <span>${entregas}</span>
                    <span class="hist-status-badge ${status}">${statusLabels[status] || status}</span>
                </div>
            </div>
        `;
    }).join('');
}

// ---------------------------------------------------------------
// Navegação rápida entre abas (rastreabilidade dos cards)
// ---------------------------------------------------------------
function dashIrPara(aba) {
    const btn = document.querySelector(`.cargas-tab[data-tab="${aba}"]`);
    if (btn) {
        mudarAbaCargas(aba, btn);
        // Scroll para o topo da seção
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ---------------------------------------------------------------
// Abrir aba Mapa já com um embarque selecionado
// ---------------------------------------------------------------
function dashIrParaMapaComEmbarque(embarqueId) {
    dashIrPara('mapa');
    // Chama a sub-aba Histórico (que será implementada no 5.D)
    setTimeout(() => {
        const btnHist = document.querySelector('.cargas-subtab[data-subtab="historico"]');
        if (btnHist) mudarSubAbaCargas('historico', btnHist);
        // Se a função abrirDetalheEmbarque já existir, abre o embarque
        if (typeof abrirDetalheEmbarque === 'function') {
            setTimeout(() => abrirDetalheEmbarque(embarqueId), 300);
        }
    }, 200);
}

// ---------------------------------------------------------------
// Modal de detalhamento de faltantes/devoluções
// ---------------------------------------------------------------
async function dashAbrirModalAcerto() {
    const token = getAuthToken();
    if (!token) return;

    Swal.fire({
        title: 'Carregando...',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });

    try {
        const resp = await fetch(`${CONFIG.API_BASE}/dashboard/acerto-detalhado?limite=10`, {
            headers: { 'Authorization': 'Bearer ' + token }
        }).then(r => r.json());

        const itens = (resp.data && resp.data.itens) || [];

        if (!itens.length) {
            Swal.fire({
                icon: 'info',
                title: 'Sem faltantes pendentes',
                text: 'Não há faltantes/devoluções registrados no momento.',
                confirmButtonColor: '#10b981'
            });
            return;
        }

        const money = (v) => new Intl.NumberFormat('pt-BR', {
            style: 'currency', currency: 'BRL'
        }).format(Number(v || 0));

        const linhas = itens.map(item => {
            const tipo = item.tipo_tratamento === 'faltante_com_estoque'
                ? '⚠️ Faltante c/ estoque'
                : item.tipo_tratamento === 'faltante_sem_estoque'
                    ? '⚠️ Faltante s/ estoque'
                    : '🔄 Devolução';
            const status = item.status || '-';
            const cor = status === 'criado_erp' ? '#059669'
                : status === 'pendente' ? '#d97706'
                : '#2563eb';
            return `
                <div style="display:flex; justify-content:space-between; align-items:center;
                            padding:10px 12px; border-bottom:1px solid #e5e7eb; gap:10px; text-align:left;">
                    <div style="flex:1; min-width:0;">
                        <div style="font-weight:700; font-size:0.85rem; color:#1f2937;">
                            ${escapeHtml(item.cliente_nome || 'Cliente')}
                        </div>
                        <div style="font-size:0.72rem; color:#64748b;">
                            Entrega #${item.entrega_id} · ${tipo}
                        </div>
                    </div>
                    <div style="text-align:right; white-space:nowrap;">
                        <div style="font-weight:700; color:${cor}; font-size:0.8rem;">${status}</div>
                        <div style="font-size:0.72rem; color:#64748b;">${money(item.valor_total)}</div>
                    </div>
                </div>
            `;
        }).join('');

        Swal.fire({
            title: 'Faltantes e Devoluções',
            html: `
                <div style="max-height:420px; overflow-y:auto; border-radius:12px;
                            border:1px solid #e5e7eb; background:#fff;">
                    ${linhas}
                </div>
                <p style="margin-top:12px; font-size:0.75rem; color:#64748b; text-align:left;">
                    <i class="fa-solid fa-info-circle"></i>
                    Para ver todos os itens, abra o módulo <b>Acerto de Embarque</b>.
                </p>
            `,
            width: '600px',
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#10b981'
        });

    } catch (error) {
        console.error('Erro ao abrir detalhamento:', error);
        Swal.fire('Erro', 'Não foi possível carregar os detalhes.', 'error');
    }
}

// ---------------------------------------------------------------
// Sub-abas do Mapa (preparando pro Bloco 5.D)
// ---------------------------------------------------------------
function mudarSubAbaCargas(subaba, btn) {
    // Ativa o botão
    document.querySelectorAll('.cargas-subtab').forEach(b => {
        b.classList.toggle('active', b === btn);
        b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
    });

    // Mostra o painel correspondente
    document.querySelectorAll('.cargas-subpanel').forEach(p => {
        p.hidden = p.id !== `subtab-${subaba}`;
    });
}

// ---------------------------------------------------------------
// Expor funções globalmente (usadas por onclick no HTML)
// ---------------------------------------------------------------
window.carregarAbaDashboard      = carregarAbaDashboard;
window.dashIrPara                = dashIrPara;
window.dashIrParaMapaComEmbarque = dashIrParaMapaComEmbarque;
window.dashAbrirModalAcerto      = dashAbrirModalAcerto;
window.mudarSubAbaCargas         = mudarSubAbaCargas;
