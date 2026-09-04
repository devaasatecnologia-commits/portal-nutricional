// ======================================================================
// GESTÃO DE CARGAS - SCRIPT COMPLETO
// ======================================================================

// ================================================================
// CONFIGURAÇÕES
// ================================================================
const CONFIG = {
    API_BASE: '/v1/frota',
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
            window.location.href = '/portal/login.php';
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
    renderizarTabela(dados.data);
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
    // Info da entrega
    const infoHtml = `
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
    if (entrega.checklist && entrega.checklist.length > 0) {
        checklistHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-solid fa-clipboard-list mr-2" style="color:var(--nutri-accent);"></i>
                    Checklist de Itens (${entrega.checklist.length})
                </h6>
                <div class="analise-checklist">
                    ${entrega.checklist.map(item => {
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
    if (entrega.problemas && entrega.problemas.length > 0) {
        problemasHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-solid fa-triangle-exclamation mr-2" style="color:#f59e0b;"></i>
                    Problemas Registrados (${entrega.problemas.length})
                </h6>
                <div class="analise-checklist">
                    ${entrega.problemas.map(p => `
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
    if (entrega.fotos && entrega.fotos.length > 0) {
        fotosHtml = `
            <div class="mt-4">
                <h6 class="font-bold text-[#1a3c34] text-sm mb-3">
                    <i class="fa-regular fa-images mr-2" style="color:var(--nutri-accent);"></i>
                    Fotos (${entrega.fotos.length})
                </h6>
                <div class="flex flex-wrap gap-3">
                    ${entrega.fotos.map(foto => `
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
// INICIALIZAÇÃO
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Tema
    const saved = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', saved);
    const icon = document.querySelector('.theme-toggle i');
    if (icon) icon.className = saved === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';

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
            carregarDados();
        }, 400));
    }

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