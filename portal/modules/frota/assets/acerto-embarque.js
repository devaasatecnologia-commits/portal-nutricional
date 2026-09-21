var API_BASE = window.API_BASE || (window.location.pathname.startsWith('/API/') ? '/API' : '') + '/v1';
// ================================================================
// ACERTO DE EMBARQUE - JAVASCRIPT COMPLETO (CORRIGIDO)
// ================================================================

// ================================================================
// VARIÁVEIS GLOBAIS
// ================================================================
let embarcar = {
    dados: null,
    paginacao: {
        pagina: 1,
        limite: 20,
        total: 0
    },
    filtros: {
        status: '',
        busca: '',
        data_inicio: '',
        data_fim: ''
    }
};

let acertoAtual = {
    id: null,
    embarque_id: null,
    status: null
};


// ================================================================
// DETECÇÃO DE TEMA - FUNÇÃO AUXILIAR
// ================================================================
function isDarkTheme() {
    return document.documentElement.getAttribute('data-theme') === 'dark';
}

function getThemeClasses() {
    const isDark = isDarkTheme();
    return {
        isDark,
        bgCard: isDark ? 'bg-gray-800' : 'bg-white',
        borderCard: isDark ? 'border-gray-700' : 'border-gray-100',
        textTitle: isDark ? 'text-white' : 'text-gray-800',
        textSub: isDark ? 'text-gray-400' : 'text-gray-500',
        textValue: isDark ? 'text-gray-300' : 'text-gray-800',
        bgHover: isDark ? 'hover:bg-gray-700/30' : 'hover:bg-gray-50'
    };
}

// ================================================================
// 🔥 FUNÇÃO DE AUTENTICAÇÃO - CORRIGIDA
// ================================================================

function getToken() {
    const token = localStorage.getItem('authToken') || 
                  sessionStorage.getItem('authToken') ||
                  document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    
    console.log('🔐 Token:', token ? '✅ Presente (início: ' + token.substring(0, 20) + '...)' : '❌ Ausente');
    return token || '';
}

function getHeaders() {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
    };
    
    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }
    
    return headers;
}

function fetchAuth(url, options = {}) {
    const headers = getHeaders();
    
    console.log('📡 ' + (options.method || 'GET') + ' ' + url);
    console.log('🔐 Headers:', { ...headers, Authorization: headers.Authorization ? 'Bearer [HIDDEN]' : '❌' });
    
    return fetch(url, {
        ...options,
        headers: {
            ...headers,
            ...options.headers
        },
        credentials: 'include'
    })
    .then(async res => {
        console.log('📡 Resposta: ' + res.status);
        
        if (res.status === 401) {
            console.warn('⚠️ Token inválido ou expirado');
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            sessionStorage.removeItem('authToken');
            throw new Error('Sessão expirada. Faça login novamente.');
        }
        
        if (!res.ok) {
            throw new Error('Erro HTTP ' + res.status + ': ' + res.statusText);
        }
        
        return res.json();
    });
}

// ================================================================
// VERIFICAR AUTENTICAÇÃO NA INICIALIZAÇÃO
// ================================================================
function verificarAutenticacao() {
    const token = getToken();
    console.log('🔐 Verificando autenticação...');
    
    if (!token) {
        console.warn('⚠️ Nenhum token encontrado!');
        
        Swal.fire({
            icon: 'warning',
            title: 'Sessão não encontrada',
            text: 'Faça login para acessar o módulo de acerto.',
            confirmButtonText: 'Ir para Login',
            confirmButtonColor: '#1a3c34',
            allowOutsideClick: false
        }).then(() => {
            const base = window.location.pathname.startsWith('/API/') ? '/API' : '';
            window.location.href = base + '/portal/login.php?redirect=' + encodeURIComponent(window.location.pathname);
        });
        return false;
    }
    
    console.log('✅ Token encontrado, tamanho:', token.length);
    return true;
}

// ================================================================
// CARREGAR EMBARQUES PARA ACERTO
// ================================================================
function carregarEmbarquesParaAcerto(forcar = false) {
    const busca = document.getElementById('filtro-busca')?.value || '';
    const dataInicio = document.getElementById('filtro-data-inicio')?.value || '';
    const dataFim = document.getElementById('filtro-data-fim')?.value || '';
    
    const params = new URLSearchParams({
        pagina: embarcar.paginacao.pagina,
        limite: embarcar.paginacao.limite,
        busca: busca,
        data_inicio: dataInicio,
        data_fim: dataFim
    });
    
    if (embarcar.filtros.status) {
        params.append('status_acerto', embarcar.filtros.status);
    }
    
    showLoading('lista-embarques');
    
    const url = API_BASE + '/frota/acerto/embarques?' + params.toString();
    console.log('📡 Buscando: GET ' + url);
    
    fetchAuth(url)
    .then(data => {
        if (data.success) {
            embarcar.dados = data.data;
            embarcar.paginacao.total = data.pagination.total;
            renderizarEmbarques(data.data);
            atualizarPaginacao(data.pagination);
            atualizarContadores(data.data);
        } else {
            showError(data.error || 'Erro ao carregar embarques');
        }
    })
    .catch(err => {
        console.error('❌ Erro ao carregar:', err);
        
        if (err.message.includes('Sessão expirada') || err.message.includes('401')) {
            Swal.fire({
                icon: 'warning',
                title: 'Sessão Expirada',
                text: 'Sua sessão expirou. Faça login novamente.',
                confirmButtonText: 'Ir para Login',
                confirmButtonColor: '#1a3c34'
            }).then(() => {
                const base = window.location.pathname.startsWith('/API/') ? '/API' : '';
                window.location.href = base + '/portal/login.php?redirect=' + encodeURIComponent(window.location.pathname);
            });
        } else {
            showError('Erro ao carregar embarques: ' + err.message);
        }
    })
    .finally(() => {
        hideLoading('lista-embarques');
    });
}

// ================================================================
// RENDERIZAR EMBARQUES - VERSÃO COMPLETA CORRIGIDA
// ================================================================
function renderizarEmbarques(embarques) {
    const tbody = document.getElementById('lista-embarques');
    if (!tbody) return;
    renderizarResumoAcertos(embarques || []);
    
    if (!embarques || embarques.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="10" class="text-center py-8">
                    <div class="flex flex-col items-center gap-2">
                        <i class="fa-regular fa-inbox text-4xl text-slate-300 dark:text-slate-600"></i>
                        <p class="text-slate-400">Nenhum acerto encontrado</p>
                        <p class="text-xs text-slate-400">Os embarques aparecerão aqui quando um acerto for iniciado</p>
                        <button class="btn-primary-nutri text-sm py-1.5 px-4 mt-2" onclick="carregarEmbarquesParaAcerto(true)">
                            <i class="fa-solid fa-rotate-right"></i> Recarregar
                        </button>
                    </div>
                </td>
            </tr>
        `;
        return;
    }
    
    let html = '';
    embarques.forEach((emb, index) => {
        const statusClass = getStatusClass(emb.embarque_status);
        const statusLabel = getStatusLabel(emb.embarque_status);
        
        const statusIconMap = {
            'finalizado': '✅',
            'problema': '⚠️',
            'em_acerto': '🔄',
            'acertado': '📋'
        };
        const statusIcon = statusIconMap[emb.embarque_status] || '📦';
        
        const acertoStatusMap = {
            'pendente': { label: '⏳ Pendente', class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
            'em_andamento': { label: '🔄 Em Andamento', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
            'finalizado': { label: '✅ Finalizado', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
            'cancelado': { label: '🚫 Cancelado', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }
        };
        
        const totalEntregas = parseInt(emb.total_entregas) || 0;
        const entregasConcluidas = parseInt(emb.entregas_concluidas) || 0;
        const progresso = totalEntregas > 0 ? Math.round((entregasConcluidas / totalEntregas) * 100) : 0;
        const conferenciaInfo = getConferenciaResumo(emb.id, totalEntregas);
        const acertoInfo = conferenciaInfo || acertoStatusMap[emb.acerto_status] || {
            label: emb.acerto_status || 'N/A',
            class: 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400'
        };
        
        let barClass = 'em-andamento';
        if (emb.embarque_status === 'problema') {
            barClass = 'problema';
        } else if (progresso >= 100) {
            barClass = 'concluido';
        }
        
        const num = (embarcar.paginacao.pagina - 1) * embarcar.paginacao.limite + index + 1;
        const dataSaida = emb.data_saida ? formatDate(emb.data_saida) : '-';
        const valorTotal = parseFloat(emb.valor_total) || 0;
        const acertoFinalizado = emb.acerto_status === 'finalizado';
        const acertoEmAndamento = emb.acerto_status === 'em_andamento';
        const acaoAcerto = acertoFinalizado
            ? { classe: 'btn-acerto-view', icone: 'fa-eye', label: 'Visualizar' }
            : acertoEmAndamento
                ? { classe: 'btn-acerto-warning', icone: 'fa-pen-to-square', label: 'Continuar' }
                : { classe: 'btn-acerto-primary', icone: 'fa-file-signature', label: 'Acertar' };
        
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textTitle = isDark ? 'text-white' : 'text-gray-800';
        const textSub = isDark ? 'text-gray-400' : 'text-slate-400';
        const textValue = isDark ? 'text-gray-300' : 'text-gray-800';
        
        html += `
            <tr class="row-status-${emb.embarque_status || 'planejado'}">
                <td class="text-center font-bold ${textSub}" data-label="#">${num}</td>
                <td data-label="Embarque">
                    <div class="font-bold ${textTitle}">
                        ${escapeHtml(emb.numero_embarque || '#' + emb.id)}
                        ${emb.total_embarques_agrupados > 1 ? ` <span class="text-xs text-purple-600 dark:text-purple-400">(Grupo)</span>` : ''}
                    </div>
                    <div class="text-xs ${textSub}">${escapeHtml(emb.nome_embarque || '')}</div>
                    ${emb.erp_embarque_id ? `<span class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 px-1.5 py-0.5 rounded-full">ERP: #${escapeHtml(emb.erp_embarque_id)}</span>` : ''}
                    ${emb.total_embarques_agrupados > 1 ? `<span class="text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 px-1.5 py-0.5 rounded-full">📦 ${emb.total_embarques_agrupados} embarques</span>` : ''}
                </td>
                <td data-label="Veículo">
                    <div class="font-medium ${textTitle}">${escapeHtml(emb.placa || 'N/A')}</div>
                    <div class="text-xs ${textSub}">${escapeHtml(emb.modelo || '')}</div>
                </td>
                <td data-label="Motorista">
                    <div class="font-medium ${textTitle}">${escapeHtml(emb.motorista_nome || 'N/A')}</div>
                    <div class="text-xs ${textSub}">${escapeHtml(emb.motorista_telefone || '')}</div>
                </td>
                <td class="text-center" data-label="Entregas">
                    <div class="flex items-center justify-center gap-2">
                        <span class="text-sm font-bold ${textTitle}">${entregasConcluidas}/${totalEntregas}</span>
                        <div class="progress-thin w-16">
                            <div class="bar ${barClass}" style="width: ${progresso}%"></div>
                        </div>
                    </div>
                </td>
                <td class="text-center" data-label="Problemas">
                    ${emb.total_problemas > 0 ? 
                        `<span class="text-red-500 dark:text-red-400 font-bold text-lg">${emb.total_problemas}</span>` : 
                        `<span class="${textSub}">0</span>`
                    }
                </td>
                <td class="text-center font-semibold text-emerald-600 dark:text-emerald-400" data-label="Valor">${formatMoney(valorTotal)}</td>
                <td class="text-center" data-label="Status">
                    <span class="status-badge ${statusClass}">
                        ${statusIcon} ${escapeHtml(statusLabel)}
                    </span>
                </td>
                <td class="text-center" data-label="Acerto">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${acertoInfo.class}">
                        ${escapeHtml(acertoInfo.label)}
                    </span>
                    ${emb.data_fim_acerto ? `<div class="text-xs ${textSub} mt-1">${formatDate(emb.data_fim_acerto)}</div>` : ''}
                </td>
                <td class="text-center" data-label="Ações">
                    <button class="btn-acerto ${acaoAcerto.classe}" onclick="abrirAcerto(${emb.id})">
                        <i class="fa-solid ${acaoAcerto.icone}"></i>
                        <span class="hidden sm:inline">${acaoAcerto.label}</span>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function renderizarResumoAcertos(embarques) {
    const container = document.getElementById('acerto-overview');
    if (!container) return;
    const pendentes = embarques.filter(emb => ['pendente', 'em_andamento'].includes(emb.acerto_status)).length;
    const finalizados = embarques.filter(emb => emb.acerto_status === 'finalizado').length;
    const problemas = embarques.reduce((total, emb) => total + Number(emb.total_problemas || 0), 0);
    const valor = embarques.reduce((total, emb) => total + Number(emb.valor_total || 0), 0);
    const cards = [
        ['fa-file-signature', pendentes, 'acertos pendentes', 'pending'],
        ['fa-circle-check', finalizados, 'acertos finalizados', 'success'],
        ['fa-triangle-exclamation', problemas, 'problemas para revisar', problemas ? 'danger' : 'neutral'],
        ['fa-sack-dollar', formatMoney(valor), 'valor em conferência', 'money']
    ];
    container.innerHTML = `
        <div class="acerto-overview-heading">
            <div><span class="overview-eyebrow"><i class="fa-solid fa-clipboard-check"></i> Controle administrativo</span><strong>Resumo dos acertos exibidos</strong></div>
            <span class="overview-caption">${embarques.length} embarques na página</span>
        </div>
        <div class="acerto-overview-cards">
            ${cards.map(([icon, value, label, tone]) => `<div class="acerto-overview-card ${tone}"><i class="fa-solid ${icon}"></i><div><strong>${value}</strong><span>${label}</span></div></div>`).join('')}
        </div>
    `;
}

// ================================================================
// ABRIR ACERTO (MODAL)
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.2):
//   - Reset do botão "Conferido Total" para VISÍVEL ao abrir
//   - Reset do botão "Visualizar Comprovante" para ESCONDIDO
//   - Após carregar os detalhes, se o acerto já está finalizado,
//     `atualizarBotoesAcerto('finalizado')` alterna os botões:
//     esconde "Conferido Total" e mostra "Visualizar Comprovante"
// ================================================================
function abrirAcerto(embarqueId) {
    if (!embarqueId) {
        showError('ID do embarque não informado');
        return;
    }

    console.log('📌 abrirAcerto chamado com ID:', embarqueId);

    acertoAtual.embarque_id = embarqueId;
    acertoAtual.id = null;
    acertoAtual.status = null;

    const conteudo = document.getElementById('acerto-conteudo');
    const topoModal = document.getElementById('acerto-topo');
    if (topoModal) topoModal.innerHTML = '';
    if (conteudo) {
        conteudo.innerHTML = `
            <div class="text-center py-8">
                <i class="fa-solid fa-spinner fa-spin text-3xl text-emerald-500"></i>
                <p class="mt-3 text-slate-400">Carregando detalhes do embarque...</p>
            </div>
        `;
    }

    // ============================================================
    // RESET DOS BOTÕES DO FOOTER
    // 🔥 MUDANÇA (Bloco 4.2): inclui btnConferidoTotal e
    //    btnVisualizarComprovante no reset padrão
    // ============================================================
    const btnIniciar = document.getElementById('btn-iniciar-acerto');
    const btnFinalizar = document.getElementById('btn-finalizar-acerto');
    const btnCancelar = document.getElementById('btn-cancelar-acerto');
    const btnConferidoTotal = document.getElementById('btn-conferido-total');
    const btnVisualizarComprovante = document.getElementById('btn-visualizar-comprovante');

    // Estado inicial: só "Iniciar Acerto" fica visível
    if (btnIniciar) {
        btnIniciar.style.display = 'inline-flex';
        btnIniciar.style.visibility = 'visible';
        btnIniciar.style.opacity = '1';
    }
    if (btnFinalizar) {
        btnFinalizar.style.display = 'none';
        btnFinalizar.style.visibility = 'hidden';
        btnFinalizar.style.opacity = '0';
    }
    if (btnCancelar) {
        btnCancelar.style.display = 'none';
        btnCancelar.style.visibility = 'hidden';
        btnCancelar.style.opacity = '0';
    }
    // "Conferido Total" começa VISÍVEL (será escondido se o acerto
    // já estiver finalizado, no callback da API)
    if (btnConferidoTotal) {
        btnConferidoTotal.style.display = 'inline-flex';
    }
    // "Visualizar Comprovante" começa ESCONDIDO
    if (btnVisualizarComprovante) {
        btnVisualizarComprovante.style.display = 'none';
    }
    // ============================================================

    const modal = document.getElementById('modalAcerto');
    if (!modal) {
        showError('Modal de acerto não encontrado');
        return;
    }

    const oldBackdrops = document.querySelectorAll('.modal-backdrop');
    oldBackdrops.forEach(b => b.remove());

    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';

    modal.style.display = 'block';
    modal.style.visibility = 'visible';
    modal.style.opacity = '1';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.right = '0';
    modal.style.bottom = '0';
    modal.style.zIndex = '1050';
    modal.style.overflow = 'hidden';
    modal.style.outline = '0';

    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show';
    backdrop.style.position = 'fixed';
    backdrop.style.top = '0';
    backdrop.style.left = '0';
    backdrop.style.right = '0';
    backdrop.style.bottom = '0';
    backdrop.style.zIndex = '1040';
    backdrop.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
    document.body.appendChild(backdrop);

    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');

    modal.classList.add('show');

    const modalDialog = modal.querySelector('.modal-dialog');
    if (modalDialog) {
        modalDialog.style.width = '100vw';
        modalDialog.style.maxWidth = '100vw';
        modalDialog.style.height = '100vh';
        modalDialog.style.maxHeight = '100vh';
        modalDialog.style.margin = '0';
        modalDialog.style.display = 'flex';
        modalDialog.style.flexDirection = 'column';
    }

    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.height = '100vh';
        modalContent.style.maxHeight = '100vh';
        modalContent.style.borderRadius = '0';
        modalContent.style.display = 'flex';
        modalContent.style.flexDirection = 'column';
    }

    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.style.overflowY = 'auto';
        modalBody.style.flex = '1 1 auto';
        modalBody.style.maxHeight = 'none';
        modalBody.style.padding = '20px 28px';
    }

    const handleEsc = function (e) {
        if (e.key === 'Escape') {
            fecharModalAcerto();
        }
    };
    document.addEventListener('keydown', handleEsc);
    modal._handleEsc = handleEsc;

    backdrop.addEventListener('click', function (e) {
        if (e.target === backdrop) {
            fecharModalAcerto();
        }
    });

    const url = API_BASE + '/frota/acerto/' + embarqueId + '/detalhes';
    console.log('📡 Buscando: GET ' + url);

    fetchAuth(url)
    .then(data => {
        console.log('📦 Dados recebidos da API:', data);

        if (data.success) {
            console.log('✅ Renderizando detalhes do acerto...');
            renderizarDetalhesAcerto(data.data);

            // ============================================================
            // ATUALIZAR BOTÕES CONFORME STATUS DO ACERTO
            // 🔥 MUDANÇA (Bloco 4.2):
            //   - Se acerto já existe (em_andamento/pendente/finalizado)
            //     → `atualizarBotoesAcerto` cuida de alternar os botões
            //   - Se não existe acerto → ainda avalia se "Iniciar Acerto"
            //     pode ser habilitado com base no embarque_status
            //     (Opção A do Bloco 4)
            // ============================================================
            if (data.data.acerto_existente) {
                acertoAtual.id = data.data.acerto_existente.id;
                acertoAtual.status = data.data.acerto_existente.status;

                atualizarBotoesAcerto(
                    data.data.acerto_existente.status,
                    data.data.embarque_status
                );
            } else {
                atualizarBotoesAcerto(null, data.data.embarque_status);
            }

            const numeroEl = document.getElementById('acerto-numero');
            if (numeroEl) {
                numeroEl.textContent = data.data.numero_embarque || embarqueId;
            }

            const statusBadge = document.getElementById('acerto-status-badge');
            if (statusBadge && data.data.embarque_status) {
                const statusMap = {
                    'planejado': { label: '📋 Planejado', class: 'bg-blue-100 text-blue-700' },
                    'em_andamento': { label: '🚚 Em Andamento', class: 'bg-yellow-100 text-yellow-700' },
                    'finalizado': { label: '✅ Finalizado', class: 'bg-green-100 text-green-700' },
                    'cancelado': { label: '🚫 Cancelado', class: 'bg-red-100 text-red-700' },
                    'problema': { label: '⚠️ Problema', class: 'bg-orange-100 text-orange-700' }
                };
                const info = statusMap[data.data.embarque_status] || {
                    label: data.data.embarque_status,
                    class: 'bg-gray-100 text-gray-700'
                };
                statusBadge.textContent = info.label;
                statusBadge.className = 'ml-2 px-3 py-1 rounded-full text-xs font-bold ' + info.class;
                statusBadge.style.display = 'inline-block';
            }

            console.log('✅ Detalhes renderizados com sucesso!');
        } else {
            console.error('❌ Erro na resposta da API:', data.error);
            if (conteudo) {
                conteudo.innerHTML = `
                    <div class="text-center py-8 text-red-500">
                        <i class="fa-solid fa-triangle-exclamation text-3xl block mb-2"></i>
                        ${escapeHtml(data.error || 'Erro ao carregar detalhes')}
                    </div>
                `;
            }
        }
    })
    .catch(err => {
        console.error('❌ Erro na requisição:', err);
        if (conteudo) {
            conteudo.innerHTML = `
                <div class="text-center py-8 text-red-500">
                    <i class="fa-solid fa-circle-exclamation text-3xl block mb-2"></i>
                    ${escapeHtml(err.message)}
                </div>
            `;
        }
    });

    window.modalAcertoRef = {
        modal: modal,
        backdrop: backdrop,
        handleEsc: handleEsc
    };
}

// ================================================================
// FECHAR MODAL DE ACERTO - CORRIGIDO
// ================================================================
function fecharModalAcerto() {
    const modal = document.getElementById('modalAcerto');
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (backdrop) {
        backdrop.remove();
    }
    
    document.body.style.overflow = '';
    document.body.classList.remove('modal-open');
    
    if (modal) {
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
        modal.classList.remove('show');
    }
    
    if (modal && modal._handleEsc) {
        document.removeEventListener('keydown', modal._handleEsc);
        delete modal._handleEsc;
    }
    
    if (window.modalAcertoRef) {
        window.modalAcertoRef = null;
    }
}

// ================================================================
// BOTÃO FECHAR DO MODAL - EVENT LISTENER
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    const closeBtn = document.querySelector('#modalAcerto .btn-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            fecharModalAcerto();
        });
    }
    
    const closeFooterBtn = document.querySelector('#modalAcerto .btn-secondary-nutri[data-bs-dismiss="modal"]');
    if (closeFooterBtn) {
        closeFooterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fecharModalAcerto();
        });
    }
});

// ================================================================
function alternarDetalhesEntrega(button) {
    const card = button.closest('.acerto-delivery-card');
    const body = card ? card.querySelector('.acerto-delivery-body') : null;
    if (!body) return;
    const expanded = body.classList.toggle('is-expanded');
    button.setAttribute('aria-expanded', String(expanded));
    button.innerHTML = expanded
        ? '<i class="fa-solid fa-chevron-up"></i> Ocultar detalhes'
        : '<i class="fa-solid fa-list-check"></i> Conferir itens';
}

// ================================================================
// RENDERIZAR DETALHES DO ACERTO — v5 (Bloco 4 + 4.1)
//
// Mudanças v4 (2026-09-21):
//   - DEVOLUÇÃO SEM TRATAMENTO agora tem botão "Tratar devolução"
//   - Card de pedido de acerto exibe badge "Transação 19 / 20"
//   - Fallback visual mais claro quando o backend não devolve
//     `tipo_tratamento`
//
// 🔥 MUDANÇA v5 (2026-09-21, Bloco 4.1):
//   - Substitui "Gerar Pedido" (laranja) por badge informativo azul
//     quando já existe pedido de faltante ativo para a entrega.
//   - O badge é CLICÁVEL e rola até o card do pedido existente.
//   - Reduz frustração de "clicar e receber aviso" → o usuário já vê
//     de antemão que o pedido foi criado.
// ================================================================
function renderizarDetalhesAcerto(dados) {
    console.log('📌 renderizarDetalhesAcerto chamado com dados:', dados);
    window.acertoDadosAtual = dados;

    const embarquesVinculados = dados.embarques || dados.embarques_vinculados || [];
    const embarquesErp = embarquesVinculados.length > 0
        ? embarquesVinculados
        : (dados.erp_ids_agrupados || dados.erp_embarque_id || '')
            .toString()
            .split(',')
            .map(id => id.trim())
            .filter(id => id && id !== '0' && id !== 'null')
            .map(id => ({ erp_embarque_id: id }));

    const numeroEl = document.getElementById('acerto-numero');
    if (numeroEl) {
        numeroEl.textContent = dados.numero_embarque || 'N/A';
    }

    const conteudo = document.getElementById('acerto-conteudo');
    if (!conteudo) {
        console.error('❌ Elemento acerto-conteudo não encontrado');
        return;
    }
    const topoModal = document.getElementById('acerto-topo');

    try {
        const inputBusca = document.getElementById('acerto-busca-pedido');
        if (inputBusca) inputBusca.value = '';
        const resultadoBusca = document.getElementById('acerto-pedido-resultado');
        if (resultadoBusca) {
            resultadoBusca.hidden = true;
            resultadoBusca.innerHTML = '';
        }

        const totalProblemas = dados.resumo_problemas ?
            dados.resumo_problemas.reduce((acc, p) => acc + parseInt(p.total), 0) : 0;

        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const bgCard = isDark ? 'bg-gray-800' : 'bg-white';
        const borderCard = isDark ? 'border-gray-700' : 'border-gray-100';
        const textTitle = isDark ? 'text-white' : 'text-gray-800';
        const textSub = isDark ? 'text-gray-400' : 'text-gray-500';
        const textValue = isDark ? 'text-gray-300' : 'text-gray-800';

        const embarquesVinculadosHtml = embarquesErp.length > 0
            ? `
                <div class="acerto-linked-inline">
                    <span><i class="fa-solid fa-link"></i> Vinculados</span>
                    ${embarquesErp.map(embarque => {
                        const numero = embarque.numero_embarque || embarque.erp_embarque_id || embarque.id;
                        return `<b><i class="fa-solid fa-truck"></i> #${escapeHtml(numero)}</b>`;
                    }).join('')}
                </div>
            `
            : '';

        const headerMotorista = document.getElementById('acerto-header-motorista');
        const headerVeiculo = document.getElementById('acerto-header-veiculo');
        const headerVinculados = document.getElementById('acerto-header-vinculados');
        const headerMetrics = document.getElementById('acerto-header-metrics');
        const statusBadge = document.getElementById('acerto-status-badge');

        if (headerMotorista) headerMotorista.textContent = dados.motorista_nome || 'Motorista não identificado';
        if (headerVeiculo) {
            headerVeiculo.innerHTML = `<i class="fa-solid fa-truck"></i> ${escapeHtml(dados.placa || 'Sem veículo')}${dados.modelo ? ' · ' + escapeHtml(dados.modelo) : ''}${dados.motorista_telefone ? ' · ' + escapeHtml(dados.motorista_telefone) : ''}`;
        }
        if (headerVinculados) headerVinculados.innerHTML = embarquesVinculadosHtml;
        if (headerMetrics) {
            headerMetrics.innerHTML = `
                <div><strong>${dados.total_entregas || 0}</strong><span>entregas</span></div>
                <div class="${totalProblemas ? 'has-alert' : ''}"><strong>${totalProblemas}</strong><span>problemas</span></div>
                <div><strong>${escapeHtml(dados.embarque_status || 'N/A')}</strong><span>status</span></div>
            `;
        }
        if (statusBadge) {
            statusBadge.style.display = 'inline-flex';
            statusBadge.className = 'acerto-header-status';
            statusBadge.innerHTML = `<i class="fa-solid fa-check-square"></i> ${escapeHtml(dados.embarque_status || 'Status')}`;
        }

        let html = '';

        if (dados.resumo_problemas && dados.resumo_problemas.length > 0) {
            html += `
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-1 h-6 bg-red-500 rounded-full"></div>
                    <h6 class="font-bold ${textTitle}">Problemas por Tipo</h6>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    ${dados.resumo_problemas.map(p => `
                        <div class="${bgCard} rounded-xl p-4 text-center border ${borderCard} shadow-sm">
                            <div class="text-2xl font-bold text-red-600 dark:text-red-400">${p.total}</div>
                            <div class="text-xs font-medium ${textSub} uppercase tracking-wider">${escapeHtml(p.tipo_problema)}</div>
                            <div class="text-sm ${textSub}">${formatMoney(p.total_valor)}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
            `;
        }

        const ultimoEvento = dados.timeline && dados.timeline.length > 0 ? dados.timeline[0] : null;
        html += `
            <details class="acerto-timeline-collapsible ${bgCard} border ${borderCard}">
                <summary>
                    <span><i class="fa-solid fa-clock-rotate-left"></i> Timeline</span>
                    <small>${dados.timeline?.length || 0} eventos${ultimoEvento ? ` · último: ${escapeHtml(ultimoEvento.acao || 'ação')}` : ''}</small>
                    <i class="fa-solid fa-chevron-down"></i>
                </summary>
                <div class="acerto-timeline-body max-h-64 overflow-y-auto">
        `;

        if (dados.timeline && dados.timeline.length > 0) {
            dados.timeline.forEach((item, index) => {
                const isLast = index === dados.timeline.length - 1;
                const iconClass = getTimelineIconClass(item.acao);
                const icon = getTimelineIcon(item.acao);
                const borderClass = isDark ? 'border-gray-700' : 'border-gray-100';
                html += `
                    <div class="flex gap-3 ${!isLast ? 'pb-3 border-b ' + borderClass : ''} mb-3 last:mb-0">
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center ${iconClass} text-white text-xs flex-shrink-0">
                                <i class="fa-solid ${icon}"></i>
                            </div>
                            ${!isLast ? `<div class="w-0.5 flex-1 bg-gray-200 dark:bg-gray-700 mt-1"></div>` : ''}
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center justify-between gap-1">
                                <span class="font-semibold ${textTitle} text-sm">${escapeHtml(item.acao || 'Ação')}</span>
                                <span class="text-xs ${textSub} whitespace-nowrap">${formatDateTime(item.data_hora)}</span>
                            </div>
                            <div class="text-sm ${textSub}">${escapeHtml(item.descricao || '')}</div>
                            ${item.usuario_nome ? `<div class="text-xs ${textSub}">👤 ${escapeHtml(item.usuario_nome)}</div>` : ''}
                        </div>
                    </div>
                `;
            });
        } else {
            html += `<div class="${textSub} text-sm text-center py-4">Nenhuma atividade registrada</div>`;
        }

        html += `
                </div>
            </details>
        `;
        if (topoModal) {
            topoModal.innerHTML = html;
            html = '';
        }

        html += `
            <div class="acerto-delivery-list">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-1 h-6 bg-emerald-500 rounded-full"></div>
                    <h6 class="font-bold ${textTitle}">Fila de conferência na ordem da rota</h6>
                    <span class="text-xs ${textSub}">(${dados.entregas?.length || 0})</span>
                </div>
        `;

        if (dados.entregas && dados.entregas.length > 0) {
            dados.entregas.forEach((entrega, index) => {
                const temProblemas = entrega.problemas && entrega.problemas.length > 0;
                const temChecklist = entrega.checklist && entrega.checklist.length > 0;
                const temFotos = entrega.fotos && entrega.fotos.length > 0;
                const temRomaneio = entrega.foto_romaneio_url;

                const statusMap = {
                    'pendente': { label: 'Pendente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
                    'em_entrega': { label: 'Em Rota', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
                    'entregue': { label: 'Entregue', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
                    'entregue_com_problema': { label: 'Com Problema', class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
                    'falha': { label: 'Falha', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
                    'cancelada': { label: 'Cancelada', class: 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400' }
                };
                const statusInfo = statusMap[entrega.status] || { label: entrega.status || 'N/A', class: 'bg-gray-100 text-gray-700' };

                let pedidosNumeros = [];
                if (entrega.pedidos_ids) {
                    const ids = entrega.pedidos_ids.split(',').map(id => id.trim());
                    pedidosNumeros = ids.filter(id => id && id !== '0' && id !== 'null');
                }
                if (pedidosNumeros.length === 0 && entrega.pedido_id) {
                    pedidosNumeros = [String(entrega.pedido_id)];
                }
                if (pedidosNumeros.length === 0) {
                    pedidosNumeros = [String(entrega.id)];
                }

                let erpEmbarquesNumeros = [];
                if (entrega.erp_embarques_ids) {
                    const ids = entrega.erp_embarques_ids.split(',').map(id => id.trim());
                    erpEmbarquesNumeros = ids.filter(id => id && id !== '0' && id !== 'null');
                }
                if (erpEmbarquesNumeros.length === 0 && dados.erp_embarque_id) {
                    erpEmbarquesNumeros = [String(dados.erp_embarque_id)];
                }

                const pedidosDisplay = pedidosNumeros.map(num =>
                    `<span class="pedido-tag inline-flex items-center bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-bold px-2.5 py-0.5 rounded-lg text-sm font-mono border border-blue-200 dark:border-blue-800">
                        #${escapeHtml(num)}
                    </span>`
                ).join(' ');

                const erpEmbarquesDisplay = erpEmbarquesNumeros.map(num =>
                    `<span class="erp-embarque-tag inline-flex items-center bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-bold px-2.5 py-0.5 rounded-lg text-sm font-mono border border-purple-200 dark:border-purple-800">
                        <i class="fa-solid fa-truck mr-1"></i> #${escapeHtml(num)}
                    </span>`
                ).join(' ');

                const qtdPedidos = pedidosNumeros.length;
                const labelPedidos = qtdPedidos > 1 ? `${qtdPedidos} pedidos` : '1 pedido';
                const termosBusca = [
                    entrega.id,
                    entrega.cliente_nome,
                    entrega.endereco,
                    entrega.numero,
                    entrega.cidade,
                    entrega.uf,
                    entrega.codigo_rastreamento,
                    entrega.nome_recebedor,
                    ...pedidosNumeros,
                    ...erpEmbarquesNumeros
                ].filter(Boolean).join(' ');

                let itensHtml = '';
                if (temChecklist) {
                    itensHtml = `
                        <div class="mt-3 pt-3 border-t ${isDark ? 'border-gray-700' : 'border-gray-100'}">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fa-solid fa-box text-gray-400 text-xs"></i>
                                <span class="text-xs font-medium ${textSub}">Itens (${entrega.checklist.length})</span>
                            </div>
                            <div class="space-y-1">
                                ${entrega.checklist.map(item => {
                                    const qtdPrev = parseFloat(item.quantidade_prevista || 0);
                                    const qtdEnt = parseFloat(item.quantidade_entregue || 0);
                                    const isOk = item.status === 'entregue';
                                    const nomeProduto = item.descricao || item.nome_produto || item.produto_nome || item.referencia || 'Item';

                                    return `
                                        <div class="acerto-item-grid ${isOk ? 'is-ok' : 'has-divergence'}">
                                            <button type="button" class="acerto-item-photo" ${item.foto_url ? `data-foto-url="${escapeHtml(item.foto_url)}" data-foto-label="${escapeHtml(nomeProduto)}" onclick="abrirZoomFoto(this.dataset.fotoUrl, this.dataset.fotoLabel)"` : ''}>
                                                ${item.foto_url ? `<img src="${escapeHtml(item.foto_url)}" alt="${escapeHtml(nomeProduto)}">` : '<i class="fa-regular fa-image"></i><span>Sem foto</span>'}
                                            </button>
                                            <div class="acerto-item-info">
                                                <span class="acerto-item-ref">${escapeHtml(item.referencia || 'Sem ref.')}</span>
                                                <strong>${escapeHtml(nomeProduto)}</strong>
                                                <small>ID item: ${escapeHtml(item.item_id || '-')} ${item.motivo ? ' · Motivo: ' + escapeHtml(item.motivo) : ''}</small>
                                            </div>
                                            <div class="acerto-item-qty">
                                                <span>Previsto <strong>${qtdPrev}</strong></span>
                                                <span>Entregue <strong>${qtdEnt}</strong></span>
                                                <b>${isOk ? 'OK' : 'Divergência'}</b>
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        </div>
                    `;
                }

                let fotosHtml = '';
                if (temFotos) {
                    fotosHtml = `
                        <div class="mt-3 flex gap-2 flex-wrap">
                            ${entrega.fotos.slice(0, 4).map(foto => `
                                <div data-foto-url="${escapeHtml(foto.url_foto)}" data-foto-label="${escapeHtml(foto.descricao || 'Foto')}" onclick="abrirZoomFoto(this.dataset.fotoUrl, this.dataset.fotoLabel)"
                                     class="w-12 h-12 rounded-lg overflow-hidden cursor-pointer border-2 ${isDark ? 'border-gray-700 hover:border-emerald-400' : 'border-gray-200 hover:border-emerald-500'} transition-all hover:scale-105"
                                     title="${escapeHtml(foto.descricao || 'Foto')}">
                                    <img src="${escapeHtml(foto.url_foto)}" class="w-full h-full object-cover"
                                         onerror="this.style.display='none';this.parentElement.innerHTML='<div class=\\'w-12 h-12 flex items-center justify-center bg-gray-100 dark:bg-gray-800 text-gray-400\\'><i class=\\'fa-regular fa-image\\'></i></div>'">
                                </div>
                            `).join('')}
                            ${entrega.fotos.length > 4 ? `<div class="text-xs ${textSub} flex items-center">+${entrega.fotos.length - 4}</div>` : ''}
                        </div>
                    `;
                }

                let romaneioHtml = '';
                if (temRomaneio) {
                    romaneioHtml = `
                        <button data-foto-url="${escapeHtml(entrega.foto_romaneio_url)}" data-foto-label="Romaneio Assinado" onclick="abrirZoomFoto(this.dataset.fotoUrl, this.dataset.fotoLabel)"
                                class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-lg border border-blue-200 dark:border-blue-800 transition-colors">
                            <i class="fa-regular fa-file-pdf"></i> Romaneio
                        </button>
                    `;
                }

                const temItensProblema = entrega.checklist && entrega.checklist.some(item =>
                    item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista
                );
                const totalItens = temChecklist ? entrega.checklist.length : 0;
                const itensOk = temChecklist ? entrega.checklist.filter(item => item.status === 'entregue').length : 0;
                const divergencias = temChecklist ? entrega.checklist.filter(item => item.status && item.status !== 'entregue').length : 0;
                const totalEvidencias = (temFotos ? entrega.fotos.length : 0) + (temRomaneio ? 1 : 0);
                const conferenciaStatus = (temProblemas || divergencias > 0) ? 'divergencia' : (entrega.status === 'entregue' ? 'entregue' : 'pendente');
                const clienteNomeArg = jsStringArg(entrega.cliente_nome || '');
                const entregaConferida = isEntregaConferida(entrega.id);

                // ============================================================
                // 🔥 DEVOLUÇÃO (comprovante) — v4
                //   4 casos:
                //     A) comprovante emitido → badge verde + reimprimir
                //     B) tratamento existe, sem comprovante → botão gerar
                //     C) devolução SEM tratamento → botão "Tratar devolução"
                //     D) sem problema de devolução → nada
                // ============================================================
                const problemasArr = Array.isArray(entrega.problemas) ? entrega.problemas : [];
                const problemasDevolucao = problemasArr.filter(p =>
                    String(p.tipo_problema || '').toLowerCase() === 'devolucao'
                );
                const problemasFaltante = problemasArr.filter(p =>
                    String(p.tipo_problema || '').toLowerCase() === 'faltante'
                );

                let botaoDevolucaoHtml = '';
                let devolucaoSemTratamento = false;

                if (problemasDevolucao.length > 0) {
                    problemasDevolucao.forEach(p => {
                        const tipoTratamento = String(p.tratamento_tipo || '').toLowerCase();
                        const ehTratamentoComprovante = (tipoTratamento === 'devolucao_comprovante' || tipoTratamento === '');
                        if (!ehTratamentoComprovante) return;

                        const numeroComprovante = p.tratamento_numero_comprovante;
                        const tratamentoId = p.tratamento_id;

                        if (numeroComprovante && tratamentoId) {
                            // CASO A) Comprovante já emitido → badge + reimprimir
                            const emitidoEm = p.tratamento_comprovante_emitido_em || '';
                            botaoDevolucaoHtml += `
                                <span class="inline-flex items-center gap-1 text-xs font-semibold
                                             bg-emerald-100 text-emerald-700
                                             dark:bg-emerald-900/30 dark:text-emerald-300
                                             pl-3 pr-1 py-1 rounded-lg
                                             border border-emerald-200 dark:border-emerald-800">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <span class="pr-1">${escapeHtml(numeroComprovante)}</span>
                                    <button type="button"
                                            class="inline-flex items-center justify-center
                                                   w-6 h-6 rounded-md
                                                   text-emerald-700 hover:text-white
                                                   hover:bg-emerald-600
                                                   dark:text-emerald-300 dark:hover:text-emerald-900 dark:hover:bg-emerald-400
                                                   transition-colors"
                                            title="Reimprimir comprovante ${escapeHtml(numeroComprovante)}${emitidoEm ? ' (emitido em ' + escapeHtml(emitidoEm) + ')' : ''}"
                                            onclick="reimprimirComprovanteDevolucao(${tratamentoId}, this)">
                                        <i class="fa-solid fa-print" style="font-size:0.7rem;"></i>
                                    </button>
                                </span>
                            `;
                        } else if (tratamentoId) {
                            // CASO B) Tratamento existe, sem comprovante → botão gerar
                            botaoDevolucaoHtml += `
                                <button type="button"
                                        class="inline-flex items-center gap-1.5 text-xs font-medium
                                               bg-blue-600 hover:bg-blue-700 text-white
                                               px-3 py-1.5 rounded-lg transition-colors"
                                        title="Gerar comprovante de devolução para faturamento"
                                        onclick="gerarComprovanteDevolucao(${tratamentoId}, this)">
                                    <i class="fa-solid fa-file-invoice"></i> Gerar Comprovante
                                </button>
                            `;
                        } else {
                            // CASO C) Devolução SEM tratamento → botão para tratar
                            devolucaoSemTratamento = true;
                        }
                    });

                    // Se caiu no caso C, mostra botão "Tratar devolução"
                    if (devolucaoSemTratamento && botaoDevolucaoHtml === '') {
                        const primeiroProblema = problemasDevolucao[0];
                        const problemaIdArg = Number(primeiroProblema.id || 0);
                        botaoDevolucaoHtml = `
                            <button type="button"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium
                                           bg-amber-500 hover:bg-amber-600 text-white
                                           px-3 py-1.5 rounded-lg transition-colors"
                                    title="Registrar tratamento de devolução (gera comprovante para faturamento)"
                                    onclick="tratarDevolucao(${entrega.id}, ${problemaIdArg}, ${clienteNomeArg}, this)">
                                <i class="fa-solid fa-file-circle-plus"></i> Tratar devolução
                            </button>
                        `;
                    }
                }

                // ============================================================
                // 🔥 REGRA DE EXCLUSIVIDADE MÚTUA (faltante x devolução)
                //
                // 🔥 MUDANÇA v5 (Bloco 4.1):
                //   Se já existe pedido de faltante ATIVO para esta entrega,
                //   substitui o botão laranja "Gerar Pedido" por um badge
                //   informativo azul CLICÁVEL (rola até o card do pedido).
                // ============================================================
                const temDevolucaoNaEntrega = problemasDevolucao.length > 0;
                const temFaltanteNaEntrega = problemasFaltante.length > 0 || temItensProblema;

                // 🔥 Verifica se já existe pedido de faltante para esta entrega
                const pedidosAcertoExistentes = dados.pedidos_acerto || [];
                const pedidoFaltanteExistente = pedidosAcertoExistentes.find(p =>
                    Number(p.entrega_id) === Number(entrega.id)
                    && p.tipo_problema === 'faltante'
                    && ['pendente', 'processando', 'criado_erp'].includes(p.status)
                ) || null;

                let botaoFaltanteHtml = '';

                if (!temDevolucaoNaEntrega && temFaltanteNaEntrega) {
                    if (pedidoFaltanteExistente) {
                        // 🔥 JÁ EXISTE PEDIDO → badge informativo clicável
                        const statusEmoji = {
                            'pendente': '⏳',
                            'processando': '🔄',
                            'criado_erp': '✅'
                        }[pedidoFaltanteExistente.status] || 'ⓘ';

                        const statusTexto = {
                            'pendente': 'pendente',
                            'processando': 'processando',
                            'criado_erp': 'criado no ERP'
                        }[pedidoFaltanteExistente.status] || pedidoFaltanteExistente.status;

                        botaoFaltanteHtml = `
                            <button type="button"
                                    class="inline-flex items-center gap-1.5 text-xs font-medium
                                           bg-blue-600 hover:bg-blue-700 text-white
                                           px-3 py-1.5 rounded-lg transition-colors"
                                    title="Ver pedido #${pedidoFaltanteExistente.id} (${escapeHtml(statusTexto)})"
                                    onclick="scrollParaPedido(${pedidoFaltanteExistente.id})">
                                ${statusEmoji} Pedido #${pedidoFaltanteExistente.id}
                                <i class="fa-solid fa-arrow-down" style="font-size:0.7rem;"></i>
                            </button>
                        `;
                    } else if (temItensProblema) {
                        botaoFaltanteHtml = `
                            <button class="inline-flex items-center gap-1.5 text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors"
                                    onclick="criarPedidoParaItensProblema(${entrega.id}, ${clienteNomeArg})">
                                <i class="fa-solid fa-plus"></i> Gerar Pedido
                            </button>
                        `;
                    } else if (temProblemas) {
                        botaoFaltanteHtml = `
                            <button class="inline-flex items-center gap-1.5 text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors"
                                    onclick="abrirPedidoProblema(${acertoAtual.id || 'null'}, ${entrega.id}, ${clienteNomeArg})">
                                <i class="fa-solid fa-plus"></i> Gerar Pedido
                            </button>
                        `;
                    }
                }

                // Se a entrega tem AMBOS, oferece link discreto para faltante
                let linkFaltanteSecundarioHtml = '';
                if (temDevolucaoNaEntrega && temFaltanteNaEntrega) {
                    if (pedidoFaltanteExistente) {
                        // Já existe → link azul pro pedido
                        linkFaltanteSecundarioHtml = `
                            <button class="inline-flex items-center gap-1.5 text-[11px] font-medium
                                           text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300
                                           underline-offset-2 hover:underline"
                                    onclick="scrollParaPedido(${pedidoFaltanteExistente.id})">
                                <i class="fa-solid fa-arrow-down"></i> Ver pedido #${pedidoFaltanteExistente.id}
                            </button>
                        `;
                    } else {
                        linkFaltanteSecundarioHtml = `
                            <button class="inline-flex items-center gap-1.5 text-[11px] font-medium
                                           text-orange-600 hover:text-orange-700 dark:text-orange-400 dark:hover:text-orange-300
                                           underline-offset-2 hover:underline"
                                    onclick="criarPedidoParaItensProblema(${entrega.id}, ${clienteNomeArg})">
                                <i class="fa-solid fa-plus"></i> Gerar faltante também
                            </button>
                        `;
                    }
                }

                const detalhesAdministrativosHtml = `
                    <details class="acerto-entry-more">
                        <summary>
                            <span><i class="fa-solid fa-layer-group"></i> Ver tudo</span>
                            <small>${labelPedidos}${erpEmbarquesNumeros.length ? ` · ${erpEmbarquesNumeros.length} ERP` : ''}</small>
                            <i class="fa-solid fa-chevron-down"></i>
                        </summary>
                        <div class="acerto-entry-more-body">
                            ${pedidosDisplay ? `
                                <div class="acerto-entry-info-row is-blue">
                                    <span><i class="fa-solid fa-file-invoice"></i> Pedidos</span>
                                    <div>${pedidosDisplay}</div>
                                </div>
                            ` : ''}
                            ${erpEmbarquesDisplay ? `
                                <div class="acerto-entry-info-row is-purple">
                                    <span><i class="fa-solid fa-truck"></i> Embarques ERP</span>
                                    <div>${erpEmbarquesDisplay}</div>
                                </div>
                            ` : ''}
                            <div class="acerto-entry-meta">
                                ${entrega.nome_recebedor ? `<span><i class="fa-solid fa-user"></i> ${escapeHtml(entrega.nome_recebedor)}</span>` : ''}
                                ${entrega.horario_entrega ? `<span><i class="fa-regular fa-clock"></i> ${formatDateTime(entrega.horario_entrega)}</span>` : ''}
                                ${entrega.codigo_rastreamento ? `<span><i class="fa-solid fa-qrcode"></i> ${escapeHtml(entrega.codigo_rastreamento)}</span>` : ''}
                            </div>
                        </div>
                    </details>
                `;

                const cardBorderColor = temProblemas ? 'border-orange-200 dark:border-orange-800' : (isDark ? 'border-gray-700' : 'border-gray-200');
                const cardBg = temProblemas ? (isDark ? 'bg-orange-900/5' : 'bg-orange-50/30') : (isDark ? 'bg-gray-800' : 'bg-white');

                html += `
                    <div class="acerto-delivery-card ${entregaConferida ? 'is-conferido' : ''} rounded-xl border ${cardBorderColor} ${cardBg} shadow-sm hover:shadow-md transition-all mb-4 overflow-hidden" data-entrega-card data-entrega-id="${entrega.id}" data-conferencia="${conferenciaStatus}" data-conferido="${entregaConferida ? '1' : '0'}" data-order="${index + 1}" data-search="${escapeHtml(termosBusca)}" data-divergencia="${(temProblemas || temItensProblema) ? '1' : '0'}" data-cliente="${escapeHtml(entrega.cliente_nome || '')}" data-pedidos="${escapeHtml(pedidosNumeros.join(', '))}" style="--delivery-order:${index + 1};">
                        <div class="p-4 ${temProblemas ? 'border-b border-orange-200 dark:border-orange-800' : 'border-b ' + (isDark ? 'border-gray-700' : 'border-gray-200')}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="acerto-client-heading flex flex-wrap items-center gap-2">
                                        <span class="acerto-stop-badge">#${index + 1}</span>
                                        <span class="font-bold ${textTitle} text-base">
                                            ${escapeHtml(entrega.cliente_nome || 'Cliente não identificado')}
                                        </span>
                                        <span class="text-xs ${isDark ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-500'} px-2 py-0.5 rounded-full">
                                            ${labelPedidos}
                                        </span>
                                        <span class="text-xs px-2.5 py-0.5 rounded-full font-medium ${statusInfo.class}">
                                            ${escapeHtml(statusInfo.label)}
                                        </span>
                                        ${entregaConferida ? getConferidoTagHtml() : ''}
                                    </div>
                                    <div class="text-sm ${textSub} mt-1">
                                        <i class="fa-solid fa-location-dot text-gray-400 text-xs"></i>
                                        ${escapeHtml(entrega.endereco || '')} ${escapeHtml(entrega.numero || '')} - ${escapeHtml(entrega.cidade || '')}/${escapeHtml(entrega.uf || '')}
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                                    ${romaneioHtml}
                                    ${botaoDevolucaoHtml}
                                    ${botaoFaltanteHtml}
                                    ${linkFaltanteSecundarioHtml}
                                    ${temChecklist ? `<button type="button" class="btn-detalhes-entrega inline-flex items-center gap-1.5 text-xs font-medium ${isDark ? 'bg-emerald-900/30 text-emerald-300 hover:bg-emerald-900/50' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100'} px-3 py-1.5 rounded-lg transition-colors" onclick="alternarDetalhesEntrega(this)" aria-expanded="false"><i class="fa-solid fa-list-check"></i> Conferir itens</button>` : ''}
                                </div>
                            </div>
                            <div class="acerto-conferencia-strip">
                                <span><i class="fa-solid fa-clipboard-check"></i> ${itensOk}/${totalItens} itens OK</span>
                                <span class="${divergencias ? 'is-danger' : ''}"><i class="fa-solid fa-triangle-exclamation"></i> ${divergencias} divergência(s)</span>
                                <span><i class="fa-regular fa-images"></i> ${totalEvidencias} evidência(s)</span>
                                ${detalhesAdministrativosHtml}
                            </div>
                        </div>

                        <div class="acerto-delivery-body p-4">
                            ${temProblemas ? `
                                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 mb-3 border border-orange-200 dark:border-orange-800">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fa-solid fa-triangle-exclamation text-orange-500 text-xs"></i>
                                        <span class="text-xs font-semibold text-orange-600 dark:text-orange-400 uppercase tracking-wider">Problemas</span>
                                    </div>
                                    ${entrega.problemas.map(p => `
                                        <div class="flex flex-wrap items-start gap-2 text-sm py-1.5 border-b border-orange-100 dark:border-orange-800 last:border-0">
                                            <span class="font-medium text-orange-600 dark:text-orange-400 capitalize text-xs">${escapeHtml(p.tipo_problema)}:</span>
                                            <span class="${textTitle} flex-1">${escapeHtml(p.descricao_problema || 'Sem descrição')}</span>
                                            <span class="text-xs ${textSub} whitespace-nowrap">${formatDateTime(p.created_at)}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}

                            ${itensHtml}
                            ${fotosHtml}
                        <div class="acerto-client-footer">
                            ${(!temDevolucaoNaEntrega && temItensProblema) ? `<button type="button" class="danger" onclick="criarPedidoParaItensProblema(${entrega.id}, ${clienteNomeArg})"><i class="fa-solid fa-file-circle-plus"></i> Gerar pedido de divergência</button>` : ''}
                            <button type="button" class="success" onclick="marcarEntregaConferida(${entrega.id}, this)"><i class="fa-solid fa-check-double"></i> Pedido conferido</button>
                        </div>
                        </div>
                    </div>
                `;
            });
        } else {
            html += `
                <div class="text-center py-12 ${bgCard} rounded-xl border ${borderCard}">
                    <i class="fa-regular fa-box text-4xl text-gray-300 dark:text-gray-600 block mb-3"></i>
                    <p class="${textSub}">Nenhuma entrega encontrada</p>
                </div>
            `;
        }

        html += '</div>';

        // ============================================================
        // 8. PEDIDOS DE ACERTO
        // 🔥 Exibe "Transação 19/20" derivada de tipo_tratamento
        // ============================================================
        if (dados.pedidos_acerto && dados.pedidos_acerto.length > 0) {
            html += `
                <div class="mt-6">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-6 bg-purple-500 rounded-full"></div>
                        <h6 class="font-bold ${textTitle}">Pedidos de Acerto</h6>
                        <span class="text-xs ${textSub}">(${dados.pedidos_acerto.length})</span>
                    </div>
                    <div class="space-y-2">
                        ${dados.pedidos_acerto.map(pedido => {
                            const pedidoItens = pedido.itens_afetados || [];
                            const totalItensPedido = pedidoItens.length;
                            const isCriadoERP = pedido.status === 'criado_erp';
                            const isPendente = pedido.status === 'pendente';

                            // 🔥 Badge de transação derivado de tipo_tratamento
                            const mapTransacaoPorTratamento = {
                                'faltante_com_estoque': 19,
                                'faltante_sem_estoque': 20
                            };
                            const transacao = mapTransacaoPorTratamento[pedido.tipo_tratamento] || null;
                            const transacaoBadge = transacao
                                ? `<span class="text-xs px-2 py-0.5 rounded-full ${transacao === 19 ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'} ml-2">
                                       <i class="fa-solid fa-right-left"></i> Transação ${transacao}
                                   </span>`
                                : '';

                            const tipoLabel = pedido.tipo_tratamento === 'faltante_com_estoque'
                                ? '⚠️ Faltante c/ estoque'
                                : pedido.tipo_tratamento === 'faltante_sem_estoque'
                                    ? '⚠️ Faltante s/ estoque'
                                    : pedido.tipo_problema === 'faltante'
                                        ? '⚠️ Faltante'
                                        : '🔄 Devolução';

                            return `
                                <div class="${bgCard} rounded-xl p-4 border ${borderCard} shadow-sm hover:shadow-md transition-all" data-pedido-id="${pedido.id}">
                                    <div class="flex flex-wrap justify-between items-center gap-2">
                                        <div>
                                            <span class="font-bold ${textTitle}">#${pedido.id}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full ${pedido.tipo_problema === 'faltante' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'} ml-2">
                                                ${tipoLabel}
                                            </span>
                                            ${transacaoBadge}
                                            <span class="text-xs ${textSub} ml-2">${formatDateTime(pedido.created_at)}</span>
                                        </div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="text-sm font-medium ${textTitle}">${formatMoney(pedido.valor_total || 0)}</span>
                                            ${isPendente ? `
                                                <button class="inline-flex items-center gap-1.5 text-xs font-medium bg-purple-600 hover:bg-purple-700 text-white px-3 py-1.5 rounded-lg transition-colors"
                                                        onclick="gerarPedidoERP(${pedido.id})">
                                                    <i class="fa-solid fa-cloud-upload"></i> Gerar ERP
                                                </button>
                                            ` : (
                                                isCriadoERP ? `
                                                    <span class="text-xs bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 px-2 py-1 rounded-full">
                                                        ✅ Criado no ERP
                                                        ${pedido.numero_pedido_criado ? ` (${escapeHtml(pedido.numero_pedido_criado)})` : ''}
                                                    </span>
                                                ` : `
                                                    <span class="text-xs bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-400 px-2 py-1 rounded-full">
                                                        ${escapeHtml(pedido.status || 'Pendente')}
                                                    </span>
                                                `
                                            )}
                                        </div>
                                    </div>
                                    ${totalItensPedido > 0 ? `
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            ${pedidoItens.slice(0, 5).map(item => `
                                                <span class="text-xs ${isDark ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-600'} px-2 py-0.5 rounded-full">
                                                    ${escapeHtml(item.referencia || 'Item')} (${escapeHtml(item.quantidade || 0)})
                                                </span>
                                            `).join('')}
                                            ${totalItensPedido > 5 ? `<span class="text-xs ${textSub}">+${totalItensPedido - 5} itens</span>` : ''}
                                        </div>
                                    ` : ''}
                                    ${pedido.observacoes ? `
                                        <div class="mt-2 text-xs ${textSub}">
                                            <i class="fa-regular fa-message"></i> ${escapeHtml(pedido.observacoes)}
                                        </div>
                                    ` : ''}
                                    ${pedido.motivo ? `
                                        <div class="mt-1 text-xs ${textSub}">
                                            <i class="fa-solid fa-info-circle"></i> Motivo: ${escapeHtml(pedido.motivo)}
                                        </div>
                                    ` : ''}
                                    ${pedido.pedido_erp_criado_id ? `
                                        <div class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                            <i class="fa-solid fa-link"></i> ERP ID: ${escapeHtml(pedido.pedido_erp_criado_id)}
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        if (dados.acerto_existente && dados.acerto_existente.status === 'finalizado') {
            html += `
                <div class="mt-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-check-circle text-emerald-600 dark:text-emerald-400 text-xl"></i>
                        <div>
                            <p class="font-bold ${textTitle}">Acerto Finalizado</p>
                            <p class="text-sm ${textSub}">
                                Finalizado por ${escapeHtml(dados.acerto_existente.gestor_nome || 'Gestor')} em ${formatDateTime(dados.acerto_existente.data_acerto)}
                            </p>
                            ${dados.acerto_existente.total_pedidos_faltantes > 0 || dados.acerto_existente.total_pedidos_devolvidos > 0 ? `
                                <div class="flex gap-4 mt-2 text-sm">
                                    ${dados.acerto_existente.total_pedidos_faltantes > 0 ? `<span class="text-red-600 dark:text-red-400">⚠️ ${dados.acerto_existente.total_pedidos_faltantes} pedidos faltantes</span>` : ''}
                                    ${dados.acerto_existente.total_pedidos_devolvidos > 0 ? `<span class="text-orange-600 dark:text-orange-400">🔄 ${dados.acerto_existente.total_pedidos_devolvidos} devoluções</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }

        conteudo.innerHTML = html;
        filtrarEntregasAcerto();
        console.log('✅ Conteúdo renderizado com sucesso!');

    } catch (error) {
        console.error('❌ Erro ao renderizar detalhes:', error);
        conteudo.innerHTML = `
            <div class="text-center py-8 text-red-500">
                <i class="fa-solid fa-circle-exclamation text-3xl block mb-2"></i>
                Erro ao renderizar detalhes: ${escapeHtml(error.message)}
            </div>
        `;
    }
}
// ================================================================
// ROLAR ATÉ O CARD DE UM PEDIDO DE ACERTO
//
// 🔥 NOVO 2026-09-21 (Bloco 4.1 v2):
//   Usado pelos badges "Pedido #X" nos cards de entrega para
//   rolar até o card correspondente na seção "Pedidos de Acerto".
//
//   Reaproveita a mesma estratégia de busca de `mostrarAvisoPedidoExistente`:
//     1. data-pedido-id específico
//     2. Fallback restrito: seção "Pedidos de Acerto"
//     3. Fallback final: qualquer card fora de entrega
// ================================================================
function scrollParaPedido(pedidoId) {
    if (!pedidoId) return;

    // 1. Tenta pelo data-pedido-id (mais preciso)
    let card = document.querySelector(`#acerto-conteudo [data-pedido-id="${pedidoId}"]`);

    // 2. Fallback: procura na seção "Pedidos de Acerto"
    if (!card) {
        const headings = document.querySelectorAll('#acerto-conteudo h6');
        for (const h of headings) {
            if (h.textContent.includes('Pedidos de Acerto')) {
                const container = h.closest('.mt-6');
                if (container) {
                    const cards = container.querySelectorAll('.rounded-xl');
                    for (const c of cards) {
                        const headingSpan = c.querySelector('span');
                        if (headingSpan && headingSpan.textContent.includes(`#${pedidoId}`)) {
                            card = c;
                            break;
                        }
                    }
                }
                break;
            }
        }
    }

    // 3. Fallback final: qualquer card que NÃO seja de entrega
    if (!card) {
        const cards = document.querySelectorAll('#acerto-conteudo .rounded-xl');
        for (const c of cards) {
            if (!c.hasAttribute('data-entrega-card') && c.textContent.includes(`#${pedidoId}`)) {
                card = c;
                break;
            }
        }
    }

    if (!card) {
        // Se não achou, avisa via toast
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        Toast.fire({
            icon: 'info',
            title: `Pedido #${pedidoId} está em "Pedidos de Acerto"`
        });
        return;
    }

    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.style.transition = 'box-shadow 0.4s ease';
    card.style.boxShadow = '0 0 0 4px rgba(124, 58, 237, 0.35)';
    setTimeout(() => {
        card.style.boxShadow = '';
    }, 2000);
}
// ================================================================
// TRATAR DEVOLUÇÃO (registrar tratamento + preparar p/ comprovante)
//
// 🔥 NOVO 2026-09-21 (Bloco 4 - Etapa 5 v4):
//   Quando o motorista registra uma devolução no checkout, ela só
//   existe como FATO (frota_entrega_problema). O TRATAMENTO (Camada 2)
//   precisa ser criado pelo gestor para liberar o botão "Gerar Comprovante".
//
//   Esta função chama POST /frota/acerto/pedido-problema com
//   tipo_problema='devolucao' e tipo_tratamento='devolucao_comprovante'.
//   O backend (criarPedidoProblema) reconhece esse combo e:
//     - Cria o registro em frota_problema_tratamento com status='aguardando_fat'
//     - NÃO cria pedido de acerto (devolução não gera ERP)
//     - Retorna `proximo_passo: 'gerar_comprovante'`
// ================================================================
async function tratarDevolucao(entregaId, problemaId, clienteNome, botaoOrigem) {
    if (!entregaId) {
        showError('ID da entrega não informado');
        return;
    }

    const acertoId = acertoAtual.id || document.getElementById('pp-acerto-id')?.value || 0;
    if (!acertoId) {
        Swal.fire('Erro', 'ID do acerto não encontrado. Inicie o acerto primeiro.', 'error');
        return;
    }

    const token = getToken();
    if (!token) {
        showError('Token não encontrado');
        return;
    }

    const confirmacao = await Swal.fire({
        title: 'Tratar devolução',
        html: `
            <div style="text-align:left; font-size:0.9rem;">
                <p>Esta devolução será registrada como <b>tratamento para faturamento</b>.</p>
                <p style="color:#64748b; margin-top:6px;">
                    Próximo passo: gerar o comprovante (DEV-AAAA-NNNNNN).
                </p>
                <p style="color:#dc2626; margin-top:10px; font-size:0.85rem;">
                    <i class="fa-solid fa-info-circle"></i>
                    Devolução <b>NÃO gera pedido ERP</b> — só comprovante.
                </p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, tratar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#64748b'
    });

    if (!confirmacao.isConfirmed) return;

    const textoOriginal = botaoOrigem ? botaoOrigem.innerHTML : null;
    if (botaoOrigem) {
        botaoOrigem.disabled = true;
        botaoOrigem.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Tratando...';
    }

    try {
        // Buscar os itens devolvidos da entrega para enviar no payload
        const entregaResp = await fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        });
        const entregaData = await entregaResp.json();

        if (!entregaData.success) {
            throw new Error(entregaData.error || 'Não foi possível carregar a entrega');
        }

        // Filtra itens devolvidos do checklist
        const itensDevolvidos = (entregaData.data.checklist || []).filter(item =>
            String(item.status || '').toLowerCase() === 'devolvido'
        );

        if (itensDevolvidos.length === 0) {
            throw new Error('Nenhum item devolvido encontrado no checklist desta entrega.');
        }

        const itens = itensDevolvidos.map(item => ({
            iditem: item.item_id || 0,
            referencia: item.referencia || '',
            descricao: item.descricao || '',
            quantidade: parseFloat(item.quantidade_prevista || 0) - parseFloat(item.quantidade_entregue || 0),
            unidade: item.unidade || 'UN'
        }));

        const payload = {
            acerto_id: parseInt(acertoId),
            entrega_id: parseInt(entregaId),
            problema_id: problemaId ? parseInt(problemaId) : 0,
            tipo_problema: 'devolucao',
            tipo_tratamento: 'devolucao_comprovante',
            motivo: 'Devolução registrada no checkout',
            observacoes: 'Tratamento criado pelo gestor no acerto',
            itens: itens
        };

        console.log('📤 Enviando tratamento de devolução:', payload);

        const response = await fetch(API_BASE + '/frota/acerto/pedido-problema', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const contentType = response.headers.get('content-type') || '';
        let data;
        if (contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const texto = await response.text();
            throw new Error(`Resposta inesperada (HTTP ${response.status}): ${texto.substring(0, 200)}`);
        }

        if (!response.ok || !data.success) {
            throw new Error(data.error || `Erro HTTP ${response.status}`);
        }

        const tratamentoId = data.data?.tratamento_id;

        Swal.fire({
            icon: 'success',
            title: '✅ Devolução tratada!',
            html: `
                <div style="text-align:left; padding:8px;">
                    <p>Tratamento #${tratamentoId} registrado com sucesso.</p>
                    <p style="font-size:0.85rem; color:#64748b; margin-top:6px;">
                        O botão <b>"Gerar Comprovante"</b> já está disponível no card.
                    </p>
                </div>
            `,
            confirmButtonText: 'OK',
            confirmButtonColor: '#10b981'
        });

        // Recarrega o modal para o botão "Gerar Comprovante" aparecer
        if (acertoAtual.embarque_id) {
            setTimeout(() => abrirAcerto(acertoAtual.embarque_id), 500);
        }

    } catch (error) {
        console.error('❌ Erro ao tratar devolução:', error);
        Swal.fire({
            icon: 'error',
            title: 'Falha ao tratar devolução',
            text: error.message || 'Erro desconhecido',
            confirmButtonColor: '#dc2626'
        });
    } finally {
        if (botaoOrigem && document.body.contains(botaoOrigem)) {
            botaoOrigem.disabled = false;
            if (textoOriginal) botaoOrigem.innerHTML = textoOriginal;
        }
    }
}



// ================================================================
// INICIAR ACERTO
// ================================================================
function iniciarAcerto() {
    if (!acertoAtual.embarque_id) {
        showError('Nenhum embarque selecionado');
        return;
    }
    
    Swal.fire({
        title: 'Iniciar Acerto',
        text: 'Você está prestes a iniciar o acerto deste embarque. Deseja continuar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1a3c34',
        confirmButtonText: 'Sim, iniciar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const url = API_BASE + '/frota/acerto/iniciar';
            const data = { embarque_id: acertoAtual.embarque_id };
            
            console.log('📡 Enviando: POST ' + url, data);
            
            fetchAuth(url, {
                method: 'POST',
                body: JSON.stringify(data)
            })
            .then(data => {
              if (data.success) {
                    acertoAtual.id = data.data.acerto_id;
                    acertoAtual.status = 'em_andamento';
                    atualizarBotoesAcerto(
                        'em_andamento',
                        data.data.embarque_status || window.acertoDadosAtual?.embarque_status
                    );
                    Swal.fire({
                        icon: 'success',
                        title: 'Acerto iniciado!',
                        text: data.message,
                        timer: 2000
                    });
                    abrirAcerto(acertoAtual.embarque_id);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.error || 'Não foi possível iniciar o acerto'
                    });
                }
            })
            .catch(err => {
                console.error('❌ Erro:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: err.message
                });
            });
        }
    });
}

// ================================================================
// FINALIZAR ACERTO
// ================================================================
function finalizarAcerto() {
    if (!acertoAtual.id) {
        showError('Nenhum acerto em andamento');
        return;
    }
    
    Swal.fire({
        title: 'Finalizar Acerto',
        text: 'Deseja finalizar este acerto? Esta ação não poderá ser desfeita.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Sim, finalizar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const url = API_BASE + '/frota/acerto/' + acertoAtual.id + '/finalizar';
            
            console.log('📡 Enviando: POST ' + url);
            
            fetchAuth(url, {
                method: 'POST',
                body: JSON.stringify({ assinatura_gestor: null })
            })
            .then(data => {
                if (data.success) {
                    acertoAtual.status = 'finalizado';
                    atualizarBotoesAcerto('finalizado');
                    Swal.fire({
                        icon: 'success',
                        title: 'Acerto finalizado!',
                        text: data.message,
                        timer: 2000
                    });
                    carregarEmbarquesParaAcerto();
                    setTimeout(() => {
                        fecharModalAcerto();
                    }, 2000);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.error || 'Não foi possível finalizar o acerto'
                    });
                }
            })
            .catch(err => {
                console.error('❌ Erro:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: err.message
                });
            });
        }
    });
}

// ================================================================
// CANCELAR ACERTO
// ================================================================
function cancelarAcerto() {
    if (!acertoAtual.id) {
        showError('Nenhum acerto em andamento');
        return;
    }
    
    Swal.fire({
        title: 'Cancelar Acerto',
        text: 'Tem certeza que deseja cancelar este acerto?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Voltar'
    }).then(result => {
        if (result.isConfirmed) {
            const url = API_BASE + '/frota/acerto/' + acertoAtual.id + '/cancelar';
            
            console.log('📡 Enviando: POST ' + url);
            
            fetchAuth(url, {
                method: 'POST'
            })
            .then(data => {
                if (data.success) {
                    acertoAtual.status = 'cancelado';
                       atualizarBotoesAcerto(
                        'cancelado',
                        window.acertoDadosAtual?.embarque_status
                    );
                    Swal.fire({
                        icon: 'info',
                        title: 'Acerto cancelado',
                        text: data.message,
                        timer: 2000
                    });
                    carregarEmbarquesParaAcerto();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.error || 'Não foi possível cancelar'
                    });
                }
            })
            .catch(err => {
                console.error('❌ Erro:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: err.message
                });
            });
        }
    });
}

// ================================================================
// VERIFICAR SE JÁ EXISTE PEDIDO DE FALTANTE PARA A ENTREGA
//
// 🔥 NOVO 2026-09-21 (Bloco 4.1):
//   Evita que o usuário crie pedido duplicado para a mesma entrega.
//   Retorna o objeto do pedido existente OU null.
//
//   Uso:
//     const existente = verificarPedidoFaltanteExistente(entregaId);
//     if (existente) { mostrarAvisoPedidoExistente(existente); return; }
// ================================================================
function verificarPedidoFaltanteExistente(entregaId) {
    if (!entregaId) return null;

    const pedidos = window.acertoDadosAtual?.pedidos_acerto || [];
    return pedidos.find(p =>
        Number(p.entrega_id) === Number(entregaId)
        && p.tipo_problema === 'faltante'
        && ['pendente', 'processando', 'criado_erp'].includes(p.status)
    ) || null;
}
// ================================================================
// MOSTRAR AVISO DE PEDIDO JÁ EXISTENTE
//
// 🔥 NOVO 2026-09-21 (Bloco 4.1):
//   SweetAlert informativo com os dados do pedido existente e botão
//   "Ver no card" que rola a tela até o card do pedido.
//
// 🔥 MELHORIA 2026-09-21 (Bloco 4.1 v2):
//   - Fallback de scroll mais restrito: busca APENAS dentro da seção
//     "Pedidos de Acerto", evitando falsos positivos (cards de entrega
//     que por acaso contenham "#N" no texto)
//   - Cobre caso do `#acerto-conteudo` ainda não estar no DOM
// ================================================================
function mostrarAvisoPedidoExistente(pedido) {
    if (!pedido) return;

    const statusLabel = {
        'pendente':    '⏳ Pendente (ainda não enviado ao ERP)',
        'processando': '🔄 Em processamento no ERP',
        'criado_erp':  '✅ Já criado no ERP'
    }[pedido.status] || pedido.status;

    const tipoLabel = pedido.tipo_tratamento === 'faltante_com_estoque'
        ? '⚠️ Faltante com estoque (Transação 19)'
        : pedido.tipo_tratamento === 'faltante_sem_estoque'
            ? '⚠️ Faltante sem estoque (Transação 20)'
            : '⚠️ Faltante';

    const erpInfo = pedido.pedido_erp_criado_id
        ? `<p style="font-size:0.85rem;color:#059669;margin:4px 0 0;">
              <i class="fa-solid fa-link"></i> ERP: <b>${escapeHtml(pedido.pedido_erp_criado_id)}</b>
              ${pedido.numero_pedido_criado ? ` (${escapeHtml(pedido.numero_pedido_criado)})` : ''}
           </p>`
        : '';

    Swal.fire({
        icon: 'info',
        title: `Pedido #${pedido.id} já existe`,
        html: `
            <div style="text-align:left; font-size:0.9rem;">
                <p>Esta entrega <b>já possui um pedido de faltante</b> criado neste acerto.</p>

                <div style="background:#f1f5f9;border-radius:10px;padding:12px;margin:12px 0;">
                    <p style="margin:0 0 6px;"><b>Tipo:</b> ${escapeHtml(tipoLabel)}</p>
                    <p style="margin:0 0 6px;"><b>Status:</b> ${escapeHtml(statusLabel)}</p>
                    <p style="margin:0 0 6px;"><b>Valor:</b> ${formatMoney(pedido.valor_total || 0)}</p>
                    <p style="margin:0 0 6px;"><b>Criado em:</b> ${formatDateTime(pedido.created_at)}</p>
                    ${erpInfo}
                </div>

                <p style="color:#64748b; font-size:0.85rem; margin-top:8px;">
                    <i class="fa-solid fa-info-circle"></i>
                    Para ver o pedido completo, consulte o card
                    <b>"Pedidos de Acerto"</b> abaixo na tela.
                </p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-arrow-down"></i> Ver pedido',
        cancelButtonText: 'Fechar',
        confirmButtonColor: '#7c3aed',
        cancelButtonColor: '#64748b'
    }).then(result => {
        if (!result.isConfirmed) return;

        // ============================================================
        // Estratégia de busca do card (3 níveis de fallback):
        //   1. data-pedido-id específico (mais preciso)
        //   2. Fallback restrito: procura na seção "Pedidos de Acerto"
        //   3. Fallback final: qualquer card que contenha "#<id>"
        // ============================================================
        let card = document.querySelector(`#acerto-conteudo [data-pedido-id="${pedido.id}"]`);

        // Fallback 1: procura na seção "Pedidos de Acerto"
        if (!card) {
            const headings = document.querySelectorAll('#acerto-conteudo h6');
            for (const h of headings) {
                if (h.textContent.includes('Pedidos de Acerto')) {
                    const container = h.closest('.mt-6');
                    if (container) {
                        const cards = container.querySelectorAll('.rounded-xl');
                        for (const c of cards) {
                            // Verifica se tem "Pedido #<id>" ou "#<id>" no heading
                            const headingSpan = c.querySelector('span');
                            if (headingSpan && headingSpan.textContent.includes(`#${pedido.id}`)) {
                                card = c;
                                break;
                            }
                        }
                    }
                    break;
                }
            }
        }

        // Fallback 2 (último recurso): qualquer card que contenha "#<id>"
        if (!card) {
            const cards = document.querySelectorAll('#acerto-conteudo .rounded-xl');
            for (const c of cards) {
                // Só considera se o card NÃO tem data-entrega-id (evita pegar cards de entrega)
                if (!c.hasAttribute('data-entrega-card') && c.textContent.includes(`#${pedido.id}`)) {
                    card = c;
                    break;
                }
            }
        }

        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.style.transition = 'box-shadow 0.4s ease';
            card.style.boxShadow = '0 0 0 4px rgba(124, 58, 237, 0.35)';
            setTimeout(() => {
                card.style.boxShadow = '';
            }, 2000);
        } else {
            // Aviso final se por algum motivo não achou o card
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000,
                timerProgressBar: true
            });
            Toast.fire({
                icon: 'info',
                title: 'Card do pedido está na seção "Pedidos de Acerto"'
            });
        }
    });
}
// ================================================================
// ABRIR MODAL DE PEDIDO DE PROBLEMA
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4 - Etapa 5):
//   - Reseta o select `#pp-tipo-tratamento` para o padrão
//     (`faltante_com_estoque`) toda vez que o modal abre.
//   - Garante que o select esteja sempre visível (modal só serve
//     para faltante — o hidden `pp-tipo-problema` é sempre 'faltante').
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.1):
//   - Antes de abrir o modal, verifica se já existe pedido de faltante
//     para a entrega. Se sim, mostra aviso e NÃO abre o modal.
// ================================================================
function abrirPedidoProblema(acertoId, entregaId, clienteNome) {
    if (!acertoId || isNaN(parseInt(acertoId)) || parseInt(acertoId) <= 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Acerto nao iniciado',
            text: 'Voce precisa iniciar o acerto deste embarque antes de gerar pedidos de problema.',
            confirmButtonText: 'OK',
            confirmButtonColor: '#f59e0b'
        });
        return;
    }

    if (!entregaId) {
        showError('Dados incompletos para criar pedido');
        return;
    }

    // ============================================================
    // 🔥 NOVO (Bloco 4.1): Verificar se já existe pedido de faltante
    // ============================================================
    const pedidoExistente = verificarPedidoFaltanteExistente(entregaId);
    if (pedidoExistente) {
        mostrarAvisoPedidoExistente(pedidoExistente);
        return;   // não abre o modal
    }
    // ============================================================

    const ppAcertoId = document.getElementById('pp-acerto-id');
    const ppEntregaId = document.getElementById('pp-entrega-id');
    const ppClienteNome = document.getElementById('pp-cliente-nome');
    const ppItensBody = document.getElementById('pp-itens-body');
    const ppTotalValor = document.getElementById('pp-total-valor');

    // Reset do select tipo_tratamento
    const ppTipoTratamento = document.getElementById('pp-tipo-tratamento');
    if (ppTipoTratamento) {
        ppTipoTratamento.value = 'faltante_com_estoque';
    }

    // Sempre visível (modal só serve pra faltante)
    const ppTipoTratamentoWrapper = document.getElementById('pp-tipo-tratamento-wrapper');
    if (ppTipoTratamentoWrapper) {
        ppTipoTratamentoWrapper.style.display = 'block';
    }

    // Reset de outros campos
    if (ppAcertoId) ppAcertoId.value = acertoId;
    if (ppEntregaId) ppEntregaId.value = entregaId;
    if (ppClienteNome) ppClienteNome.value = clienteNome || 'Cliente não identificado';
    if (ppItensBody) ppItensBody.innerHTML = '';
    if (ppTotalValor) ppTotalValor.textContent = '0,00';

    const modal = document.getElementById('modalPedidoProblema');
    if (!modal) {
        showError('Modal de pedido não encontrado');
        return;
    }

    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        } else {
            modal.style.display = 'block';
            modal.classList.add('show');
            document.body.classList.add('modal-open');
            let backdrop = document.querySelector('.modal-backdrop');
            if (!backdrop) {
                backdrop = document.createElement('div');
                backdrop.className = 'modal-backdrop fade show';
                document.body.appendChild(backdrop);
            }
        }
    } catch (e) {
        console.warn('⚠️ Erro ao usar Bootstrap Modal, usando fallback manual:', e);
        modal.style.display = 'block';
        modal.classList.add('show');
        document.body.classList.add('modal-open');
        let backdrop = document.querySelector('.modal-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            document.body.appendChild(backdrop);
        }
    }
}

// ================================================================
// ADICIONAR ITEM AO PEDIDO DE PROBLEMA
// ================================================================
function adicionarItemProblema() {
    Swal.fire({
        title: 'Buscar Item',
        html: `
            <div class="mb-3">
                <label class="form-label">Digite a referência ou descrição do item</label>
                <input type="text" id="swal-item-busca" class="form-control" placeholder="Ex: PROD-001 ou Arroz...">
            </div>
            <div id="swal-resultados" class="mt-2"></div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Buscar',
        cancelButtonText: 'Cancelar',
        preConfirm: () => {
            const value = document.getElementById('swal-item-busca').value;
            if (!value) {
                Swal.showValidationMessage('Digite um termo para buscar');
                return;
            }
            return value;
        }
    }).then(result => {
        if (result.isConfirmed && result.value) {
            const busca = result.value;
            const url = API_BASE + '/frota/acerto/itens/buscar?q=' + encodeURIComponent(busca) + '&limite=10';
            
            Swal.fire({
                title: 'Buscando...',
                text: 'Aguarde',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            fetchAuth(url)
            .then(data => {
                if (!data.success) {
                    throw new Error(data.error || 'Erro ao buscar itens');
                }
                
                const itens = data.data || [];
                
                if (itens.length === 0) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Nenhum item encontrado',
                        text: 'Tente buscar com outro termo'
                    });
                    return;
                }
                
                let html = `
                    <div class="max-h-64 overflow-y-auto">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Referência</th>
                                    <th>Descrição</th>
                                    <th>Saldo</th>
                                    <th>Valor</th>
                                    <th>Ação</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                itens.forEach(item => {
                    html += `
                        <tr>
                            <td><strong>${escapeHtml(item.referencia || 'N/A')}</strong></td>
                            <td>${escapeHtml(item.descricao || '')}</td>
                            <td class="text-center">${escapeHtml(item.saldo_estoque || 0)}</td>
                            <td class="text-end">${formatMoney(item.valor_unitario)}</td>
                            <td class="text-center">
                                <button class="btn btn-sm btn-primary" onclick="selecionarItemProblema(${JSON.stringify(item).replace(/"/g, '&quot;')}); Swal.close();">
                                    <i class="fa-solid fa-plus"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                
                Swal.fire({
                    title: 'Selecione um Item',
                    html: html,
                    confirmButtonText: 'Fechar',
                    confirmButtonColor: '#6b7280'
                });
            })
            .catch(err => {
                console.error('❌ Erro:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: err.message
                });
            });
        }
    });
}

// ================================================================
// SELECIONAR ITEM PARA O PEDIDO
// ================================================================
function selecionarItemProblema(item) {
    if (!item || !item.iditem) {
        showError('Item inválido');
        return;
    }
    
    const existing = document.querySelector('#pp-itens-body tr[data-item-id="' + item.iditem + '"]');
    if (existing) {
        Swal.fire('Aviso', 'Este item já foi adicionado', 'warning');
        return;
    }
    
    const row = document.createElement('tr');
    row.dataset.itemId = item.iditem;
    row.dataset.valorUnitario = item.valor_unitario || 0;
    row.innerHTML = `
        <td>${escapeHtml(item.descricao || 'Sem descrição')}</td>
        <td><strong>${escapeHtml(item.referencia || 'N/A')}</strong></td>
        <td>
            <input type="number" class="form-control form-control-sm" value="1" min="0.001" step="0.001" 
                   onchange="calcularTotalItem(this)">
        </td>
        <td class="text-end">${formatMoney(item.valor_unitario || 0)}</td>
        <td class="text-end item-total">${formatMoney(item.valor_unitario || 0)}</td>
        <td class="text-center">
            <button class="btn btn-sm btn-danger" onclick="removerItemProblema(this)">
                <i class="fa-solid fa-trash"></i>
            </button>
        </td>
    `;
    
    document.getElementById('pp-itens-body').appendChild(row);
    recalcularTotalPedido();
}

// ================================================================
// CALCULAR TOTAL DO ITEM
// ================================================================
function calcularTotalItem(input) {
    const row = input.closest('tr');
    if (!row) return;
    
    const qtd = parseFloat(input.value) || 0;
    const valorUnitario = parseFloat(row.dataset.valorUnitario) || 0;
    const total = qtd * valorUnitario;
    
    const totalEl = row.querySelector('.item-total');
    if (totalEl) {
        totalEl.textContent = formatMoney(total);
    }
    
    recalcularTotalPedido();
}

// ================================================================
// REMOVER ITEM DO PEDIDO
// ================================================================
function removerItemProblema(btn) {
    const row = btn.closest('tr');
    if (row) {
        row.remove();
        recalcularTotalPedido();
    }
}

// ================================================================
// RECALCULAR TOTAL DO PEDIDO
// ================================================================
function recalcularTotalPedido() {
    let total = 0;
    document.querySelectorAll('#pp-itens-body tr').forEach(row => {
        const totalEl = row.querySelector('.item-total');
        if (totalEl) {
            const valor = parseFloat(totalEl.textContent.replace(/[^0-9,.-]/g, '').replace(',', '.')) || 0;
            total += valor;
        }
    });
    
    const totalEl = document.getElementById('pp-total-valor');
    if (totalEl) {
        totalEl.textContent = formatMoney(total);
    }
}

// ================================================================
// SALVAR PEDIDO DE PROBLEMA
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4 - Etapa 5):
//   - Lê o select `#pp-tipo-tratamento` e envia no payload como
//     `tipo_tratamento`.
//   - Validação: se o select não existir ou estiver vazio, assume
//     `faltante_com_estoque`.
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.1):
//   - Rede de segurança: revalida se já existe pedido de faltante
//     antes de enviar pro backend.
// ================================================================
function salvarPedidoProblema() {
    const acertoId = document.getElementById('pp-acerto-id')?.value;
    const entregaId = document.getElementById('pp-entrega-id')?.value;
    const tipo = document.getElementById('pp-tipo-problema')?.value || 'faltante';
    const motivo = document.getElementById('pp-motivo')?.value || '';
    const observacoes = document.getElementById('pp-observacoes')?.value || '';

    // ============================================================
    // 🔥 NOVO (Bloco 4.1): Rede de segurança antes de enviar
    // ============================================================
    const pedidoExistente = verificarPedidoFaltanteExistente(entregaId);
    if (pedidoExistente) {
        // Fecha o modal (se estiver aberto)
        const modal = document.getElementById('modalPedidoProblema');
        if (modal && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(modal);
            if (inst) inst.hide();
        }
        mostrarAvisoPedidoExistente(pedidoExistente);
        return;
    }
    // ============================================================

    // Ler tipo_tratamento do select
    let tipoTratamento = document.getElementById('pp-tipo-tratamento')?.value || '';

    if (!tipoTratamento) {
        tipoTratamento = 'faltante_com_estoque';
    }

    const tiposValidos = ['faltante_com_estoque', 'faltante_sem_estoque'];
    if (!tiposValidos.includes(tipoTratamento)) {
        Swal.fire('Erro', 'Tipo de tratamento inválido: ' + tipoTratamento, 'error');
        return;
    }

    if (!acertoId || !entregaId) {
        showError('Dados do acerto ou entrega não encontrados');
        return;
    }

    const itens = [];
    document.querySelectorAll('#pp-itens-body tr').forEach(row => {
        const qtdInput = row.querySelector('input[type="number"]');
        const itemId = parseInt(row.dataset.itemId);
        const quantidade = parseFloat(qtdInput?.value) || 0;
        const valorUnitario = parseFloat(row.dataset.valorUnitario) || 0;

        if (itemId && quantidade > 0) {
            itens.push({
                iditem: itemId,
                quantidade: quantidade,
                valor_unitario: valorUnitario
            });
        }
    });

    if (itens.length === 0) {
        Swal.fire('Aviso', 'Adicione pelo menos um item com quantidade válida', 'warning');
        return;
    }

    const tipoTratamentoLabel = tipoTratamento === 'faltante_sem_estoque'
        ? 'Faltante SEM estoque (ERP 20)'
        : 'Faltante COM estoque (ERP 19)';

    Swal.fire({
        title: 'Confirmar pedido de faltante',
        html: `
            <div style="text-align:left; font-size:0.9rem;">
                <p><b>Tipo:</b> ${escapeHtml(tipoTratamentoLabel)}</p>
                <p><b>Itens:</b> ${itens.length}</p>
                <p><b>Motivo:</b> ${escapeHtml(motivo || '-')}</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#1a3c34',
        confirmButtonText: 'Sim, criar',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (result.isConfirmed) {
            const data = {
                acerto_id: parseInt(acertoId),
                entrega_id: parseInt(entregaId),
                tipo_problema: tipo,
                tipo_tratamento: tipoTratamento,
                motivo: motivo,
                observacoes: observacoes,
                itens: itens
            };

            const url = API_BASE + '/frota/acerto/pedido-problema';

            console.log('📡 Enviando: POST ' + url, data);

            fetchAuth(url, {
                method: 'POST',
                body: JSON.stringify(data)
            })
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Pedido criado!',
                        text: data.message,
                        timer: 2000
                    });
                    const modal = document.getElementById('modalPedidoProblema');
                    const modalInstance = bootstrap.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                    abrirAcerto(acertoAtual.embarque_id);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro',
                        text: data.error || 'Não foi possível criar o pedido'
                    });
                }
            })
            .catch(err => {
                console.error('❌ Erro:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Erro',
                    text: err.message
                });
            });
        }
    });
}

// ================================================================
// FUNÇÕES AUXILIARES
// ================================================================

function getStatusClass(status) {
    const map = {
        'finalizado': 'status-badge-finalizado',
        'problema': 'status-badge-problema',
        'em_acerto': 'status-badge-em_acerto',
        'acertado': 'status-badge-acertado'
    };
    return map[status] || 'status-badge-finalizado';
}

function getStatusLabel(status) {
    const map = {
        'finalizado': 'Finalizado',
        'problema': 'Com Problema',
        'em_acerto': 'Em Acerto',
        'acertado': 'Acertado'
    };
    return map[status] || status;
}

function getTimelineIcon(acao) {
    const map = {
        'checkin': 'fa-arrow-right-to-bracket',
        'checkout': 'fa-arrow-right-from-bracket',
        'problema': 'fa-triangle-exclamation',
        'iniciar': 'fa-play',
        'finalizar': 'fa-check-double',
        'cancelar': 'fa-ban',
        'acerto_iniciado': 'fa-play',
        'acerto_finalizado': 'fa-check-double',
        'pedido_problema_criado': 'fa-plus-circle',
        'pedido_erp_criado': 'fa-file-invoice'
    };
    return map[acao] || 'fa-circle';
}

function getTimelineIconClass(acao) {
    const map = {
        'checkin': 'timeline-icon-checkin',
        'checkout': 'timeline-icon-checkout',
        'problema': 'timeline-icon-problema'
    };
    return map[acao] || 'timeline-icon-sistema';
}

function formatDate(date) {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleDateString('pt-BR');
}

function formatDateTime(date) {
    if (!date) return '';
    const d = new Date(date);
    return d.toLocaleString('pt-BR');
}

function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value || 0);
}

function formatPeso(peso) {
    const valor = parseFloat(peso);
    if (isNaN(valor) || valor === 0) return '0 kg';
    if (valor >= 1000) {
        return (valor / 1000).toFixed(1) + ' t';
    }
    return valor.toFixed(1) + ' kg';
}

// ================================================================
// VER DETALHES DA ENTREGA
// ================================================================
function verDetalhesEntrega(entregaId) {
    const token = getToken();
    if (!token) {
        showError('Token não encontrado');
        return;
    }
    
    Swal.fire({
        title: 'Carregando...',
        text: 'Buscando detalhes da entrega',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        Swal.close();
        
        if (!data.success) {
            Swal.fire('Erro', data.error || 'Erro ao carregar detalhes', 'error');
            return;
        }
        
        const entrega = data.data;
        
        const statusMap = {
            'pendente': { label: '⏳ Pendente', class: 'bg-yellow-100 text-yellow-700' },
            'em_entrega': { label: '🚚 Em Rota', class: 'bg-blue-100 text-blue-700' },
            'entregue': { label: '✅ Entregue', class: 'bg-green-100 text-green-700' },
            'entregue_com_problema': { label: '⚠️ Com Problema', class: 'bg-orange-100 text-orange-700' },
            'falha': { label: '❌ Falha', class: 'bg-red-100 text-red-700' },
            'cancelada': { label: '🚫 Cancelada', class: 'bg-gray-100 text-gray-700' }
        };
        const statusInfo = statusMap[entrega.status] || { label: entrega.status || 'N/A', class: 'bg-gray-100 text-gray-700' };
        
        const temProblemas = entrega.checklist && entrega.checklist.some(item => 
            item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista
        );
        
        let html = `
            <div style="text-align:left;max-height:500px;overflow-y:auto;padding:4px;">
                <div style="background: linear-gradient(135deg, #1a3c34, #2d5a4e); color: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px;">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700;">${escapeHtml(entrega.cliente_nome || 'Cliente')}</div>
                            <div style="font-size: 0.85rem; opacity: 0.9;">${escapeHtml(entrega.endereco || '')} ${escapeHtml(entrega.numero || '')} - ${escapeHtml(entrega.cidade || '')}/${escapeHtml(entrega.uf || '')}</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: ${statusInfo.class.replace('text-', '')}; padding: 4px 12px; border-radius: 100px; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                                ${escapeHtml(statusInfo.label)}
                            </span>
                            ${entrega.codigo_rastreamento ? `<div style="font-size: 0.7rem; opacity: 0.7; margin-top: 4px;">🔍 ${escapeHtml(entrega.codigo_rastreamento)}</div>` : ''}
                        </div>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 16px;">
                    <div style="background: #f0fdf4; border-radius: 8px; padding: 8px 12px; border: 1px solid #bbf7d0;">
                        <div style="font-size: 0.6rem; text-transform: uppercase; color: #64748b;">Recebedor</div>
                        <div style="font-weight: 600; font-size: 0.85rem;">${escapeHtml(entrega.nome_recebedor || 'Não informado')}</div>
                    </div>
                    <div style="background: #dbeafe; border-radius: 8px; padding: 8px 12px; border: 1px solid #93c5fd;">
                        <div style="font-size: 0.6rem; text-transform: uppercase; color: #64748b;">Data Entrega</div>
                        <div style="font-weight: 600; font-size: 0.85rem;">${formatDateTime(entrega.horario_entrega)}</div>
                    </div>
                    <div style="background: #fef3c7; border-radius: 8px; padding: 8px 12px; border: 1px solid #fcd34d;">
                        <div style="font-size: 0.6rem; text-transform: uppercase; color: #64748b;">Valor</div>
                        <div style="font-weight: 600; font-size: 0.85rem; color: #059669;">${formatMoney(entrega.valor_total)}</div>
                    </div>
                    <div style="background: #f3e8ff; border-radius: 8px; padding: 8px 12px; border: 1px solid #d8b4fe;">
                        <div style="font-size: 0.6rem; text-transform: uppercase; color: #64748b;">Peso</div>
                        <div style="font-weight: 600; font-size: 0.85rem;">${formatPeso(entrega.peso_total)}</div>
                    </div>
                </div>
        `;
        
        if (entrega.checklist && entrega.checklist.length > 0) {
            const itensProblema = entrega.checklist.filter(item => 
                item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista
            );
            const temProblema = itensProblema.length > 0;
            const clienteNomeArg = jsStringArg(entrega.cliente_nome || '');
            
            html += `
                <hr style="border: 0; border-top: 2px solid #e5e7eb; margin: 12px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h6 style="font-weight: 700; font-size: 0.95rem; margin: 0;">
                        <i class="fa-solid fa-clipboard-list"></i> Itens (${entrega.checklist.length})
                        ${temProblema ? `<span style="background: #fee2e2; color: #dc2626; padding: 2px 10px; border-radius: 100px; font-size: 0.65rem; margin-left: 8px;">⚠️ ${itensProblema.length} com problema</span>` : ''}
                    </h6>
                    ${temProblema ? `
                        <button onclick="criarPedidoParaItensProblema(${entrega.id}, ${clienteNomeArg})"
                                style="background: #f59e0b; color: white; border: none; padding: 6px 14px; border-radius: 8px; cursor: pointer; font-size: 0.75rem; font-weight: 600; display: flex; align-items: center; gap: 6px;">
                            <i class="fa-solid fa-plus"></i> Gerar Pedido
                        </button>
                    ` : ''}
                </div>
                <div style="overflow-x: auto; max-height: 250px; overflow-y: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.75rem; min-width: 600px;">
                        <thead style="background: #f1f5f9; position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th style="padding: 6px 10px; text-align: left; font-weight: 600;">Ref.</th>
                                <th style="padding: 6px 10px; text-align: left; font-weight: 600;">Produto</th>
                                <th style="padding: 6px 10px; text-align: center; font-weight: 600;">Prev.</th>
                                <th style="padding: 6px 10px; text-align: center; font-weight: 600;">Ent.</th>
                                <th style="padding: 6px 10px; text-align: center; font-weight: 600;">Faltante</th>
                                <th style="padding: 6px 10px; text-align: center; font-weight: 600;">Status</th>
                                <th style="padding: 6px 10px; text-align: left; font-weight: 600;">Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${entrega.checklist.map(item => {
                                const isProblema = item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista;
                                const bgColor = isProblema ? '#fef2f2' : 'transparent';
                                const qtdPrev = parseFloat(item.quantidade_prevista || 0);
                                const qtdEnt = parseFloat(item.quantidade_entregue || 0);
                                const qtdFaltante = qtdPrev - qtdEnt;
                                
                                const statusColor = item.status === 'entregue' ? '#10b981' : 
                                                   item.status === 'faltante' ? '#f59e0b' : '#ef4444';
                                const statusLabel = item.status === 'entregue' ? '✅ Entregue' : 
                                                   item.status === 'faltante' ? '⚠️ Faltante' : '🔄 Devolvido';
                                
                                return `
                                    <tr style="border-bottom: 1px solid #e5e7eb; background: ${bgColor};">
                                        <td style="padding: 6px 10px; font-weight: 500;">${escapeHtml(item.referencia || '-')}</td>
                                        <td style="padding: 6px 10px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${escapeHtml(item.descricao || '-')}</td>
                                        <td style="padding: 6px 10px; text-align: center;">${qtdPrev}</td>
                                        <td style="padding: 6px 10px; text-align: center; font-weight: ${isProblema ? '700' : '400'}; color: ${isProblema ? '#dc2626' : 'inherit'};">${qtdEnt}</td>
                                        <td style="padding: 6px 10px; text-align: center; font-weight: 700; color: #dc2626;">
                                            ${isProblema ? qtdFaltante : '-'}
                                        </td>
                                        <td style="padding: 6px 10px; text-align: center;">
                                            <span style="background: ${statusColor}20; color: ${statusColor}; padding: 2px 8px; border-radius: 100px; font-size: 0.65rem; font-weight: 600; white-space: nowrap;">
                                                ${statusLabel}
                                            </span>
                                        </td>
                                        <td style="padding: 6px 10px; font-size: 0.7rem; color: ${item.motivo ? '#dc2626' : '#94a3b8'};">${escapeHtml(item.motivo || '—')}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        if (entrega.fotos && entrega.fotos.length > 0) {
            html += `
                <hr style="border: 0; border-top: 2px solid #e5e7eb; margin: 12px 0;">
                <h6 style="font-weight: 700; font-size: 0.95rem; margin-bottom: 8px;">
                    <i class="fa-regular fa-images"></i> Fotos (${entrega.fotos.length})
                </h6>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    ${entrega.fotos.map(foto => `
                        <div data-foto-url="${escapeHtml(foto.url_foto)}" data-foto-label="${escapeHtml(foto.descricao || 'Foto')}" onclick="abrirZoomFoto(this.dataset.fotoUrl, this.dataset.fotoLabel)" 
                             style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; cursor: pointer; border: 2px solid #e5e7eb; transition: transform 0.2s;"
                             onmouseover="this.style.transform='scale(1.05)'" 
                             onmouseout="this.style.transform='scale(1)'">
                            <img src="${escapeHtml(foto.url_foto)}" style="width:100%;height:100%;object-fit:cover;" 
                                 onerror="this.style.display='none';this.parentElement.innerHTML='<div style=\\'width:80px;height:80px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;font-size:1.5rem;color:#9ca3af;\\'><i class=\\'fa-regular fa-image\\'></i></div>'">
                            <div style="font-size:0.5rem;text-align:center;background:rgba(0,0,0,0.5);color:white;padding:1px 4px;">${escapeHtml(foto.descricao || '')}</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        html += '</div>';
        
        Swal.fire({
            title: `📦 Detalhes da Entrega #${entregaId}`,
            html: html,
            width: window.innerWidth < 768 ? '95%' : '950px', maxWidth: '95vw',
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#1a3c34',
            customClass: {
                popup: 'modal-detalhes-entrega'
            }
        });
    })
    .catch(err => {
        Swal.close();
        console.error('❌ Erro:', err);
        Swal.fire('Erro', err.message, 'error');
    });
}

// ================================================================
// CRIAR PEDIDO PARA ITENS COM PROBLEMA
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4 - Etapa 5):
//   - Adiciona um segundo Swal para perguntar o tipo_tratamento
//     antes de enviar o pedido.
//   - Envia `tipo_tratamento` no payload.
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.1):
//   - Verifica se já existe pedido de faltante para a entrega antes
//     de iniciar o fluxo. Se sim, mostra aviso e não prossegue.
// ================================================================
function criarPedidoParaItensProblema(entregaId, clienteNome) {
    if (!entregaId) {
        showError('ID da entrega não informado');
        return;
    }

    // ============================================================
    // 🔥 NOVO (Bloco 4.1): Verificar se já existe pedido de faltante
    // ============================================================
    const pedidoExistente = verificarPedidoFaltanteExistente(entregaId);
    if (pedidoExistente) {
        mostrarAvisoPedidoExistente(pedidoExistente);
        return;
    }
    // ============================================================

    const token = getToken();
    if (!token) {
        showError('Token não encontrado');
        return;
    }

    Swal.fire({
        title: 'Carregando...',
        text: 'Buscando itens com problema',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch(`${API_BASE}/frota/entregas/${entregaId}`, {
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        Swal.close();

        if (!data.success) {
            Swal.fire('Erro', data.error || 'Erro ao carregar itens', 'error');
            return;
        }

        const entrega = data.data;

        const itensProblema = entrega.checklist.filter(item =>
            item.status !== 'entregue' &&
            item.quantidade_entregue < item.quantidade_prevista
        );

        if (itensProblema.length === 0) {
            Swal.fire('Aviso', 'Nenhum item com problema encontrado nesta entrega.', 'info');
            return;
        }

        const itensFaltantes = itensProblema.map(item => {
            const qtdPrev = parseFloat(item.quantidade_prevista || 0);
            const qtdEnt = parseFloat(item.quantidade_entregue || 0);
            return {
                ...item,
                quantidade_faltante: qtdPrev - qtdEnt,
                quantidade_original: qtdPrev,
                quantidade_entregue_original: qtdEnt
            };
        });

        let itensHtml = itensFaltantes.map((item, index) => `
            <div style="background: #fef2f2; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; border: 1px solid #fca5a5;">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px;">
                    <div style="flex: 1;">
                        <span style="font-weight: 600; font-size: 0.9rem;">${escapeHtml(item.referencia || 'Item')}</span>
                        <span style="font-size: 0.8rem; color: #64748b; display: block;">${escapeHtml(item.descricao || 'Sem descrição')}</span>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                        <div style="text-align: center;">
                            <div style="font-size: 0.6rem; color: #64748b;">Previsto</div>
                            <div style="font-weight: 600;">${item.quantidade_original}</div>
                        </div>
                        <div style="text-align: center;">
                            <div style="font-size: 0.6rem; color: #64748b;">Entregue</div>
                            <div style="font-weight: 600; color: #10b981;">${item.quantidade_entregue_original}</div>
                        </div>
                        <div style="text-align: center; background: #dc2626; color: white; padding: 2px 12px; border-radius: 6px;">
                            <div style="font-size: 0.6rem;">Faltante</div>
                            <div style="font-weight: 700; font-size: 1.1rem;">${item.quantidade_faltante}</div>
                        </div>
                        <input type="hidden" class="item-faltante-qtd" data-idx="${index}" value="${item.quantidade_faltante}">
                        <input type="hidden" class="item-faltante-id" value="${escapeHtml(item.item_id || 0)}">
                        <input type="hidden" class="item-faltante-ref" value="${escapeHtml(item.referencia || '')}">
                        <input type="hidden" class="item-faltante-desc" value="${escapeHtml(item.descricao || '')}">
                    </div>
                </div>
                ${item.motivo ? `<div style="font-size: 0.7rem; color: #dc2626; margin-top: 4px;">Motivo: ${escapeHtml(item.motivo)}</div>` : ''}
            </div>
        `).join('');

        const totalFaltante = itensFaltantes.reduce((sum, item) => sum + item.quantidade_faltante, 0);
        const totalItens = itensFaltantes.length;

        Swal.fire({
            title: '📝 Criar Pedido de Faltante',
            html: `
                <div style="text-align: left; max-width: 100%;">
                    <div style="background: #f0fdf4; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #bbf7d0;">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                            <span style="font-weight: 600;">👤 Cliente: ${escapeHtml(clienteNome || 'Cliente')}</span>
                            <span style="color: #dc2626; font-weight: 700;">⚠️ ${totalItens} itens faltantes</span>
                            <span style="color: #dc2626; font-weight: 700;">📦 ${totalFaltante} unidades</span>
                        </div>
                    </div>

                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px;">
                        <i class="fa-solid fa-info-circle"></i>
                        Será criado um pedido de <strong>faltante</strong> com as quantidades não entregues.
                        Clique em <strong>"Confirmar"</strong> para criar o pedido no sistema.
                    </p>

                    <div style="max-height: 300px; overflow-y: auto; padding-right: 4px;">
                        ${itensHtml}
                    </div>

                    <div style="margin-top: 12px; padding: 10px 14px; background: #fef3c7; border-radius: 8px; border: 1px solid #fcd34d;">
                        <span style="font-weight: 600; color: #92400e;">
                            ⚠️ Total: ${totalItens} itens | ${totalFaltante} unidades faltantes
                        </span>
                    </div>
                </div>
            `,
            width: window.innerWidth < 768 ? '95%' : '750px', maxWidth: '95vw',
            showCancelButton: true,
            confirmButtonText: '✅ Confirmar Pedido',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#dc2626',
            preConfirm: () => {
                const itensParaEnviar = itensFaltantes.map((item, index) => ({
                    iditem: item.item_id || 0,
                    referencia: item.referencia || '',
                    descricao: item.descricao || '',
                    quantidade: item.quantidade_faltante,
                    valor_unitario: 0,
                    motivo: item.motivo || 'Faltante no checkout',
                    unidade: item.unidade || 'UN'
                }));

                return {
                    itens: itensParaEnviar,
                    tipoTratamento: 'faltante_com_estoque'
                };
            }
        }).then(async result => {
            if (result.isConfirmed && result.value) {
                const itens = result.value.itens || result.value;
                if (!itens || itens.length === 0) {
                    Swal.fire('Erro', 'Nenhum item válido para criar pedido', 'error');
                    return;
                }

                const acertoId = acertoAtual.id || document.getElementById('pp-acerto-id')?.value || 0;
                if (!acertoId) {
                    Swal.fire('Erro', 'ID do acerto não encontrado. Inicie o acerto primeiro.', 'error');
                    return;
                }

                // Perguntar tipo de faltante
                const escolha = await Swal.fire({
                    title: 'Tipo de faltante',
                    html: `
                        <div style="text-align:left; font-size:0.9rem;">
                            <p>Este pedido será criado como <b>faltante</b>. Escolha a transação:</p>
                            <div style="margin-top:8px;">
                                <label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer;">
                                    <input type="radio" name="swal-tipo-trat" value="faltante_com_estoque" checked>
                                    <span><b>Com estoque</b> (transação ERP 19)</span>
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;padding:8px;border:1px solid #e5e7eb;border-radius:8px;cursor:pointer;margin-top:6px;">
                                    <input type="radio" name="swal-tipo-trat" value="faltante_sem_estoque">
                                    <span><b>Sem estoque</b> (transação ERP 20)</span>
                                </label>
                            </div>
                        </div>
                    `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#1a3c34',
                    preConfirm: () => {
                        const sel = document.querySelector('input[name="swal-tipo-trat"]:checked');
                        return sel ? sel.value : 'faltante_com_estoque';
                    }
                });

                if (!escolha.isConfirmed) return;
                const tipoTratamento = escolha.value || 'faltante_com_estoque';

                const payload = {
                    acerto_id: parseInt(acertoId),
                    entrega_id: parseInt(entregaId),
                    tipo_problema: 'faltante',
                    tipo_tratamento: tipoTratamento,
                    motivo: 'Faltante no checkout',
                    observacoes: 'Pedido gerado automaticamente a partir dos itens faltantes',
                    itens: itens.map(item => ({
                        iditem: item.iditem,
                        referencia: item.referencia,
                        descricao: item.descricao,
                        quantidade: item.quantidade,
                        unidade: item.unidade || 'UN'
                    }))
                };

                console.log('📤 Enviando pedido de faltante:', payload);

                Swal.fire({
                    title: 'Criando pedido...',
                    text: 'Aguarde',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch(API_BASE + '/frota/acerto/pedido-problema', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                })
                .then(async res => {
                    const contentType = res.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return res.json();
                    }
                    const text = await res.text();
                    console.error('❌ Resposta não é JSON:', text);
                    throw new Error('Erro no servidor: ' + res.status);
                })
                .then(data => {
                    Swal.close();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '✅ Pedido criado com sucesso!',
                            html: `
                                <div style="text-align: left; padding: 8px;">
                                    <p>Pedido de <strong>faltante</strong> criado para a entrega #${escapeHtml(entregaId)}</p>
                                    <p style="font-size: 0.85rem; color: #64748b;">
                                        Total: <strong>${itens.length}</strong> itens |
                                        <strong>${totalFaltante}</strong> unidades
                                    </p>
                                    <div style="margin-top: 8px; background: #f0fdf4; padding: 8px 12px; border-radius: 8px;">
                                        <span style="font-size: 0.8rem; color: #065f46;">
                                            <i class="fa-solid fa-check-circle"></i>
                                            Agora você pode gerar o pedido no ERP clicando em "Gerar Pedido ERP"
                                        </span>
                                    </div>
                                </div>
                            `,
                            confirmButtonText: 'OK',
                            confirmButtonColor: '#10b981'
                        });

                        if (acertoAtual.embarque_id) {
                            setTimeout(() => {
                                abrirAcerto(acertoAtual.embarque_id);
                            }, 500);
                        }
                    } else {
                        // 🔥 NOVO (Bloco 4.1): Trata o 409 do backend amigavelmente
                        if (data.code === 'PEDIDO_JA_EXISTE' && data.pedido_id) {
                            mostrarAvisoPedidoExistente({
                                id: data.pedido_id,
                                status: data.pedido_status,
                                tipo_tratamento: data.pedido_tipo_tratamento,
                                valor_total: data.pedido_valor_total,
                                created_at: data.pedido_criado_em,
                                pedido_erp_criado_id: data.pedido_erp_id,
                                numero_pedido_criado: data.pedido_numero_erp
                            });
                        } else {
                            Swal.fire('Erro', data.error || 'Falha ao criar pedido', 'error');
                        }
                    }
                })
                .catch(err => {
                    Swal.close();
                    console.error('❌ Erro na requisição:', err);
                    Swal.fire('Erro', err.message || 'Falha ao criar pedido', 'error');
                });
            }
        });
    })
    .catch(err => {
        Swal.close();
        console.error('❌ Erro:', err);
        Swal.fire('Erro', err.message, 'error');
    });
}
// ================================================================
// GERAR COMPROVANTE DE DEVOLUÇÃO (Bloco 4, Etapa 3) — v2 CORRIGIDA
// Chama POST /v1/frota/acerto/tratamento/{id}/gerar-comprovante
//
// ⚠️ O ID recebido é SEMPRE o frota_problema_tratamento.id (não o problema.id)
//
// 🔥 Correção v2 (2026-09-18):
//   - Substituído `mostrarNotificacao()` (não existe neste arquivo)
//     por `Swal.mixin({ toast: true })`, seguindo o padrão do módulo
//   - Aumentado o delay antes de recarregar a modal (600ms → 1500ms)
//     para dar tempo da janela de impressão abrir e o usuário clicar em
//     imprimir. Assim o badge verde aparece depois, sem "matar" a impressão.
// ================================================================
async function gerarComprovanteDevolucao(tratamentoId, botaoOrigem) {
    if (!tratamentoId || isNaN(parseInt(tratamentoId))) {
        showError('ID de tratamento inválido.');
        return;
    }

    const token = getToken();
    if (!token) {
        showError('Token não encontrado. Faça login novamente.');
        return;
    }

    // ================================================================
    // 1. Confirmação
    // ================================================================
    const confirmacao = await Swal.fire({
        title: 'Gerar comprovante de devolução?',
        html: `
            <div style="text-align:left; font-size:0.9rem;">
                <p>O comprovante será numerado sequencialmente (formato <code>DEV-AAAA-NNNNNN</code>) e ficará disponível para faturamento.</p>
                <p style="color:#64748b; margin-top:8px;">Esta ação <b>não pode ser desfeita</b>.</p>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, gerar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#64748b'
    });

    if (!confirmacao.isConfirmed) return;

    // ================================================================
    // 2. Desabilita o botão enquanto processa
    // ================================================================
    const textoOriginal = botaoOrigem ? botaoOrigem.innerHTML : null;
    if (botaoOrigem) {
        botaoOrigem.disabled = true;
        botaoOrigem.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gerando...';
    }

    try {
        // ================================================================
        // 3. Chamada à API
        // ================================================================
        const url = `${API_BASE}/frota/acerto/tratamento/${encodeURIComponent(tratamentoId)}/gerar-comprovante`;
        console.log('📡 POST', url);

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        });

        // Trata respostas não-JSON com segurança
        const contentType = response.headers.get('content-type') || '';
        let dados;
        if (contentType.includes('application/json')) {
            dados = await response.json();
        } else {
            const texto = await response.text();
            throw new Error(`Resposta inesperada do servidor (HTTP ${response.status}): ${texto.substring(0, 200)}`);
        }

        if (!response.ok || !dados.success) {
            throw new Error(dados.error || dados.message || `Erro HTTP ${response.status}`);
        }

        // ================================================================
        // 4. Sucesso — toast + impressão (Etapa 4)
        // ================================================================
        const payload = dados.data || dados.dados || {};
        console.log('✅ Comprovante gerado:', payload);

        const numero = payload.numero_comprovante || '';

        // 🔥 Toast via SweetAlert (padrão do módulo — mostrarNotificacao não existe aqui)
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            didOpen: (toast) => {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });

        Toast.fire({
            icon: 'success',
            title: `Comprovante ${numero || ''} gerado com sucesso!`
        });

        // 🔥 Etapa 4 — abre janela de impressão
        imprimirComprovanteDevolucao(payload);

        // 🔥 Recarrega os detalhes (só depois de um tempo suficiente para
        // o usuário interagir com a janela de impressão — assim o badge verde
        // aparece quando ele fechar)
        if (acertoAtual.embarque_id) {
            setTimeout(() => {
                abrirAcerto(acertoAtual.embarque_id);
            }, 1500);
        }

    } catch (error) {
        console.error('❌ Erro ao gerar comprovante:', error);

        Swal.fire({
            icon: 'error',
            title: 'Falha ao gerar comprovante',
            html: `
                <div style="text-align:left;">
                    <p style="color:#dc2626; font-size:0.9rem; background:#fef2f2; padding:8px 12px; border-radius:8px;">
                        ${escapeHtml(error.message || 'Erro desconhecido')}
                    </p>
                    <p style="color:#64748b; font-size:0.85rem; margin-top:12px;">
                        Se o tratamento estiver em <code>pendente</code>, ele precisa primeiro ser movido para
                        <code>aguardando_fat</code> no backend antes de gerar o comprovante.
                    </p>
                </div>
            `,
            confirmButtonColor: '#dc2626'
        });

    } finally {
        // ================================================================
        // 5. Restaura o botão (se ainda estiver no DOM)
        // ================================================================
        if (botaoOrigem && document.body.contains(botaoOrigem)) {
            botaoOrigem.disabled = false;
            if (textoOriginal) botaoOrigem.innerHTML = textoOriginal;
        }
    }
}
// ================================================================
// REIMPRIMIR COMPROVANTE DE DEVOLUÇÃO
// Busca os dados do comprovante já emitido e reabre a janela de impressão.
// Não gera um novo comprovante, só reimprime o existente.
// ================================================================
async function reimprimirComprovanteDevolucao(tratamentoId, botaoOrigem) {
    if (!tratamentoId || isNaN(parseInt(tratamentoId))) {
        showError('ID de tratamento inválido.');
        return;
    }

    const token = getToken();
    if (!token) {
        showError('Token não encontrado. Faça login novamente.');
        return;
    }

    // Desabilita o botão enquanto processa
    const textoOriginal = botaoOrigem ? botaoOrigem.innerHTML : null;
    if (botaoOrigem) {
        botaoOrigem.disabled = true;
        botaoOrigem.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    }

    try {
        const url = `${API_BASE}/frota/acerto/tratamento/${encodeURIComponent(tratamentoId)}/comprovante`;
        console.log('📡 GET', url);

        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Accept': 'application/json'
            }
        });

        const contentType = response.headers.get('content-type') || '';
        let dados;
        if (contentType.includes('application/json')) {
            dados = await response.json();
        } else {
            const texto = await response.text();
            throw new Error(`Resposta inesperada (HTTP ${response.status}): ${texto.substring(0, 200)}`);
        }

        if (!response.ok || !dados.success) {
            throw new Error(dados.error || dados.message || `Erro HTTP ${response.status}`);
        }

        const payload = dados.data || {};
        console.log('✅ Comprovante para reimpressão:', payload);

        // Reabre a janela de impressão com os dados existentes
        imprimirComprovanteDevolucao(payload);

    } catch (error) {
        console.error('❌ Erro ao reimprimir comprovante:', error);
        Swal.fire({
            icon: 'error',
            title: 'Falha ao reimprimir',
            text: error.message || 'Erro desconhecido',
            confirmButtonColor: '#dc2626'
        });
    } finally {
        if (botaoOrigem && document.body.contains(botaoOrigem)) {
            botaoOrigem.disabled = false;
            if (textoOriginal) botaoOrigem.innerHTML = textoOriginal;
        }
    }
}
// ================================================================
// GERAR PEDIDO ERP DIRETO
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4 - Etapa 6):
//   - Deriva `id_transacao` de `tipo_tratamento` (mesma lógica do backend),
//     não mais de `tipo_problema`.
//   - Mapa: faltante_com_estoque → 19, faltante_sem_estoque → 20.
//   - Fallback retrocompatível: se `tipo_tratamento` não vier, mantém
//     o comportamento antigo (faltante → 19, devolucao → 20).
//   - Bloqueia envio se o tipo for desconhecido (evita mandar transação
//     errada pro ERP).
// ================================================================
function gerarPedidoERP(pedidoAcertoId) {
    if (!pedidoAcertoId) {
        showError('ID do pedido de acerto não informado');
        return;
    }

    const token = getToken();
    if (!token) {
        showError('Token não encontrado');
        return;
    }

    Swal.fire({
        title: 'Carregando...',
        text: 'Buscando dados do pedido',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });

    fetch(`${API_BASE}/frota/acerto/pedido/${pedidoAcertoId}`, {
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        }
    })
    .then(res => res.json())
    .then(pedidoData => {
        Swal.close();

        if (!pedidoData.success) {
            Swal.fire('Erro', pedidoData.error || 'Pedido não encontrado', 'error');
            return;
        }

        const pedido = pedidoData.data;
        const tipoProblema = pedido.tipo_problema || 'faltante';
        const tipoTratamento = pedido.tipo_tratamento || '';

        // ============================================================
        // 🔥 MUDANÇA 2026-09-21 (Bloco 4 - Etapa 6):
        //   Deriva a transação do `tipo_tratamento`
        // ============================================================
        const mapTransacaoPorTratamento = {
            'faltante_com_estoque': 19,
            'faltante_sem_estoque': 20,
            // devolucao_comprovante NÃO gera pedido ERP (não tem transação)
        };

        let transacaoAutomatica = null;
        let tipoLabel = '';
        let tipoEmoji = '';

        if (tipoTratamento && mapTransacaoPorTratamento[tipoTratamento] != null) {
            transacaoAutomatica = mapTransacaoPorTratamento[tipoTratamento];
            if (tipoTratamento === 'faltante_com_estoque') {
                tipoLabel = 'Faltante com estoque (Transação 19)';
                tipoEmoji = '⚠️';
            } else if (tipoTratamento === 'faltante_sem_estoque') {
                tipoLabel = 'Faltante sem estoque (Transação 20)';
                tipoEmoji = '⚠️';
            }
        } else {
            // Fallback retrocompatível (sem tipo_tratamento no pedido)
            if (tipoProblema === 'faltante') {
                transacaoAutomatica = 19;
                tipoLabel = 'Faltante (Transação 19 - padrão)';
                tipoEmoji = '⚠️';
            } else if (tipoProblema === 'devolucao') {
                transacaoAutomatica = 20;
                tipoLabel = 'Devolução (Transação 20 - legado)';
                tipoEmoji = '🔄';
            } else {
                // Tipo desconhecido: bloqueia
                Swal.fire({
                    icon: 'error',
                    title: 'Tipo de tratamento desconhecido',
                    html: `Não foi possível determinar a transação ERP.<br>
                           <code>tipo_problema: ${escapeHtml(tipoProblema)}</code><br>
                           <code>tipo_tratamento: ${escapeHtml(tipoTratamento || '(vazio)')}</code>`,
                    confirmButtonColor: '#dc2626'
                });
                return;
            }
        }

        const filialPadrao = 1;
        const sandbox = false;

        let itensResumo = '';
        const itens = pedido.itens_afetados || [];
        if (itens.length > 0) {
            itensResumo = itens.map(item => `
                <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #e5e7eb; font-size: 0.85rem;">
                    <span>${escapeHtml(item.referencia || 'Item')} - ${escapeHtml(item.descricao || '')}</span>
                    <span style="font-weight: 600; color: #dc2626;">${escapeHtml(item.quantidade)} un</span>
                </div>
            `).join('');
        }

        Swal.fire({
            title: '🚀 Gerar Pedido no ERP',
            html: `
                <div style="text-align: left; max-width: 100%;">
                    <div style="background: #f0fdf4; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #bbf7d0;">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <span style="font-weight: 600;">📋 Pedido #${escapeHtml(pedidoAcertoId)}</span>
                            <span style="color: #dc2626; font-weight: 700;">${tipoEmoji} ${escapeHtml(tipoLabel)}</span>
                            <span style="font-weight: 600;">💰 ${formatMoney(pedido.valor_total || 0)}</span>
                        </div>
                    </div>

                    <div style="background: #dbeafe; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; border: 1px solid #93c5fd;">
                        <div style="font-size: 0.8rem; color: #1e40af;">
                            <strong>🔧 Configuração Automática:</strong>
                            <ul style="margin: 6px 0 0 20px; padding: 0;">
                                <li>Transação: <strong>${transacaoAutomatica}</strong> (${escapeHtml(tipoTratamento || tipoProblema)})</li>
                                <li>Filial: <strong>${filialPadrao}</strong></li>
                                <li>Modo: <strong>🚀 Produção</strong></li>
                            </ul>
                        </div>
                    </div>

                    ${itensResumo ? `
                        <div style="max-height: 200px; overflow-y: auto; background: #f8fafc; border-radius: 8px; padding: 8px 12px; margin-bottom: 12px;">
                            <p style="font-weight: 600; font-size: 0.8rem; margin-bottom: 4px;">📦 Itens (${itens.length}):</p>
                            ${itensResumo}
                        </div>
                    ` : ''}

                    <p style="font-size: 0.8rem; color: #dc2626; margin-top: 8px;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        O pedido será inserido diretamente no ERP. Confirme para continuar.
                    </p>
                </div>
            `,
            width: window.innerWidth < 768 ? '95%' : '650px', maxWidth: '95vw',
            showCancelButton: true,
            confirmButtonText: '🚀 Confirmar e Gerar no ERP',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#dc2626'
        }).then(result => {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: '🚀 Gerando pedido no ERP...',
                text: 'Aguarde enquanto o pedido é processado',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            const payload = {
                id_transacao: transacaoAutomatica,
                id_filial: filialPadrao,
                sandbox: sandbox
            };

            fetch(`${API_BASE}/frota/acerto/pedido/${pedidoAcertoId}/criar-erp`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async res => {
                const contentType = res.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return res.json();
                }
                const text = await res.text();
                console.error('❌ Resposta não é JSON:', text);
                throw new Error('Erro no servidor: ' + res.status);
            })
            .then(data => {
                Swal.close();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '✅ Pedido gerado com sucesso!',
                        html: `
                            <div style="text-align: left; padding: 8px;">
                                <p><strong>Pedido criado no ERP</strong></p>
                                <div style="background: #f0fdf4; border-radius: 8px; padding: 12px; margin-top: 8px;">
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.85rem;">
                                        <span><strong>ID Pedido PDA:</strong> ${escapeHtml(data.data?.idpedidopda || 'N/A')}</span>
                                        <span><strong>Sequencial:</strong> ${escapeHtml(data.data?.sequencial_portal || 'N/A')}</span>
                                        <span><strong>Transação:</strong> ${escapeHtml(data.data?.idtransacao || 'N/A')}</span>
                                        <span><strong>Total:</strong> ${formatMoney(data.data?.valortotalpedido || 0)}</span>
                                    </div>
                                </div>
                            </div>
                        `,
                        confirmButtonText: 'OK',
                        confirmButtonColor: '#10b981'
                    });

                    if (acertoAtual.embarque_id) {
                        setTimeout(() => abrirAcerto(acertoAtual.embarque_id), 500);
                    }
                } else {
                    Swal.fire('Erro', data.error || 'Falha ao criar pedido no ERP', 'error');
                }
            })
            .catch(err => {
                Swal.close();
                console.error('❌ Erro:', err);
                Swal.fire('Erro', err.message || 'Falha ao criar pedido no ERP', 'error');
            });
        });
    })
    .catch(err => {
        Swal.close();
        console.error('❌ Erro:', err);
        Swal.fire('Erro', err.message, 'error');
    });
}

// ================================================================
// ATUALIZAR BOTÕES DE ACERTO
//
// 🔥 MUDANÇA 2026-09-18 (Bloco 4 - Opção A):
//   - Aceita 2 status de embarque que permitem iniciar acerto:
//     'finalizado' e 'problema'
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.2):
//   - Quando o acerto está FINALIZADO (embarque conferido):
//     • Esconde o botão "Conferido Total"
//     • Mostra o botão "Visualizar Comprovante"
// ================================================================
function atualizarBotoesAcerto(status, embarqueStatus) {
    const btnIniciar   = document.getElementById('btn-iniciar-acerto');
    const btnFinalizar = document.getElementById('btn-finalizar-acerto');
    const btnCancelar  = document.getElementById('btn-cancelar-acerto');
    const btnConferidoTotal = document.getElementById('btn-conferido-total');
    const btnVisualizarComprovante = document.getElementById('btn-visualizar-comprovante');

    if (btnIniciar)   btnIniciar.style.display   = 'none';
    if (btnFinalizar) btnFinalizar.style.display = 'none';
    if (btnCancelar)  btnCancelar.style.display  = 'none';
    if (btnConferidoTotal) btnConferidoTotal.style.display = 'none';
    if (btnVisualizarComprovante) btnVisualizarComprovante.style.display = 'none';

    // ============================================================
    // Estado: acerto FINALIZADO → só "Visualizar Comprovante" + "Fechar"
    // ============================================================
    if (status === 'finalizado') {
        if (btnVisualizarComprovante) btnVisualizarComprovante.style.display = 'inline-flex';
        return;
    }

    // ============================================================
    // Estado: acerto em andamento ou pendente
    //   → "Finalizar" + "Cancelar" + "Conferido Total" + "Fechar"
    // ============================================================
    if (status === 'em_andamento' || status === 'pendente') {
        if (btnFinalizar) btnFinalizar.style.display = 'inline-flex';
        if (btnCancelar)  btnCancelar.style.display  = 'inline-flex';
        if (btnConferidoTotal) btnConferidoTotal.style.display = 'inline-flex';
        return;
    }

    // ============================================================
    // Estado: nenhum acerto → mostra "Iniciar Acerto"
    // ============================================================
    if (!status) {
        if (!btnIniciar) return;

        const statusPermitidos = ['finalizado', 'problema', null, undefined, ''];
        const podeIniciar = statusPermitidos.includes(embarqueStatus);

        const labelPadrao = '<i class="fa-solid fa-play"></i> Iniciar Acerto';

        if (podeIniciar) {
            btnIniciar.style.display = 'inline-flex';
            btnIniciar.disabled = false;
            btnIniciar.title = embarqueStatus === 'problema'
                ? 'Embarque com divergências — o acerto tratará faltantes/devoluções'
                : 'Iniciar acerto do embarque';
            btnIniciar.innerHTML = labelPadrao;
        } else {
            const msgPorStatus = {
                'planejado':    'Aguardando o motorista iniciar a rota',
                'em_andamento': 'Aguardando conclusão das entregas pelo motorista',
                'cancelado':    'Embarque cancelado — não pode ser acertado'
            };
            const motivo = msgPorStatus[embarqueStatus] || 'Embarque não está pronto para acerto';

            btnIniciar.style.display = 'inline-flex';
            btnIniciar.disabled = true;
            btnIniciar.title = motivo;
            btnIniciar.innerHTML = '<i class="fa-solid fa-lock"></i> Acerto bloqueado';
        }

        // Mesmo sem acerto, se já foi conferido localmente, mostra "Visualizar"
        if (btnConferidoTotal && !isEmbarqueFinalizadoTotal(acertoAtual.embarque_id)) {
            btnConferidoTotal.style.display = 'inline-flex';
        } else if (btnVisualizarComprovante && isEmbarqueFinalizadoTotal(acertoAtual.embarque_id)) {
            btnVisualizarComprovante.style.display = 'inline-flex';
            if (btnConferidoTotal) btnConferidoTotal.style.display = 'none';
        }
    }
}
function atualizarContadores(embarques) {
    const total = document.getElementById('total-acertos');
    if (total && embarques) {
        const pendentes = embarques.filter(e => e.embarque_status === 'finalizado' || e.embarque_status === 'problema').length;
        total.textContent = pendentes;
    }
}

function aplicarFiltro(status, btn) {
    document.querySelectorAll('.quick-filter-pill').forEach(p => p.classList.remove('active'));
    if (btn) btn.classList.add('active');
    
    embarcar.filtros.status = status;
    embarcar.paginacao.pagina = 1;
    carregarEmbarquesParaAcerto();
}

function buscarEmbarques() {
    embarcar.paginacao.pagina = 1;
    carregarEmbarquesParaAcerto();
}

function mudarLimite() {
    const select = document.getElementById('limite-por-pagina');
    if (select) {
        embarcar.paginacao.limite = parseInt(select.value);
        embarcar.paginacao.pagina = 1;
        carregarEmbarquesParaAcerto();
    }
}

function mudarPagina(direcao) {
    if (direcao === 'anterior' && embarcar.paginacao.pagina > 1) {
        embarcar.paginacao.pagina--;
    } else if (direcao === 'proximo') {
        const totalPaginas = Math.ceil(embarcar.paginacao.total / embarcar.paginacao.limite);
        if (embarcar.paginacao.pagina < totalPaginas) {
            embarcar.paginacao.pagina++;
        }
    }
    carregarEmbarquesParaAcerto();
}

// ================================================================
// ABRIR FOTO COM ZOOM
// ================================================================
function abrirZoomFoto(url, label) {
    if (!url) {
        showError('URL da foto não disponível');
        return;
    }
    
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
            <br><small style="font-size: 0.8rem; opacity: 0.7;">${escapeHtml(label || 'Foto')}</small>
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
        <span>${escapeHtml(label || 'Foto')}</span>
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

// ================================================================
// FECHAR ZOOM
// ================================================================
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
function atualizarPaginacao(pagination) {
    const paginaAtual = document.getElementById('pagina-atual');
    const infoPaginacao = document.getElementById('info-paginacao');
    const btnAnterior = document.getElementById('btn-anterior');
    const btnProximo = document.getElementById('btn-proximo');
    
    if (paginaAtual) paginaAtual.textContent = pagination.pagina;
    if (infoPaginacao) {
        infoPaginacao.textContent = pagination.total + ' registros • Página ' + pagination.pagina + ' de ' + pagination.total_paginas;
    }
    if (btnAnterior) btnAnterior.disabled = pagination.pagina <= 1;
    if (btnProximo) btnProximo.disabled = pagination.pagina >= pagination.total_paginas;
}

function showLoading(elementId) {
    const el = document.getElementById(elementId);
    if (el) {
        el.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-8">
                    <div class="flex flex-col items-center gap-2">
                        <div class="animate-spin rounded-full h-8 w-8 border-4 border-emerald-500 border-t-transparent"></div>
                        <div class="text-slate-400 text-sm">Carregando...</div>
                    </div>
                </td>
            </tr>
        `;
    }
}

function hideLoading(elementId) {
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: message
    });
}

// ================================================================
// TEMA CLARO/ESCURO
// ================================================================
function toggleTheme() {
    const html = document.documentElement;
    const currentTheme = html.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    const btn = document.querySelector('.theme-toggle-inline i');
    if (btn) {
        btn.className = newTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
    
    const acertoContent = document.getElementById('acerto-conteudo');
    if (acertoContent && acertoContent.innerHTML.trim() !== '' && !acertoContent.innerHTML.includes('Carregando')) {
        const acertoNumero = document.getElementById('acerto-numero');
        if (acertoNumero && acertoNumero.textContent !== 'N/A') {
            const embarqueId = window.acertoAtual?.embarque_id;
            if (embarqueId) {
                abrirAcerto(embarqueId);
            }
        }
    }
}

function initTheme() {
    const savedTheme = localStorage.getItem('theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    const btn = document.querySelector('.theme-toggle-inline i');
    if (btn) {
        btn.className = savedTheme === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    }
}

// ================================================================
// INICIALIZAÇÃO
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Inicializando Acerto de Embarque');
    
    const autenticado = verificarAutenticacao();
    if (!autenticado) return;
    
    initTheme();
    carregarEmbarquesParaAcerto();
    
    setInterval(() => {
        carregarEmbarquesParaAcerto();
    }, 60000);
});

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character]));
}

function jsStringArg(value) {
    return escapeHtml(JSON.stringify(String(value ?? '')));
}

function normalizarBusca(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim();
}

function getConferenciaKey() {
    return `frota:acerto:conferencia:${acertoAtual.embarque_id || 'sem-embarque'}`;
}

function getConferencias() {
    try {
        return JSON.parse(localStorage.getItem(getConferenciaKey()) || '[]');
    } catch (error) {
        return [];
    }
}

function salvarConferencias(ids) {
    localStorage.setItem(getConferenciaKey(), JSON.stringify([...new Set(ids.map(Number).filter(Boolean))]));
}

function isEntregaConferida(entregaId) {
    return getConferencias().includes(Number(entregaId));
}

function getConferenciaResumo(embarqueId, totalEntregas) {
    try {
        const finalizadoTotal = localStorage.getItem(`frota:acerto:finalizado:${embarqueId}`) === '1';
        const ids = JSON.parse(localStorage.getItem(`frota:acerto:conferencia:${embarqueId}`) || '[]');
        if (finalizadoTotal) {
            return { label: '✅ Conferido', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' };
        }
        if (!ids.length) return null;
        return { label: `🟦 Conferido parcial ${ids.length}/${totalEntregas || '?'}`, class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' };
    } catch (error) {
        return null;
    }
}

function isEmbarqueFinalizadoTotal(embarqueId) {
    return localStorage.getItem(`frota:acerto:finalizado:${embarqueId || (acertoAtual.embarque_id || '')}`) === '1';
}

function atualizarResumoConferenciaModal() {
    const total = document.querySelectorAll('#acerto-conteudo [data-entrega-card]').length;
    const conferidos = document.querySelectorAll('#acerto-conteudo [data-entrega-card].is-conferido').length;
    const badge = document.getElementById('acerto-status-badge');
    if (badge && total) {
        badge.style.display = 'inline-flex';
        badge.className = 'acerto-header-status';
        if (isEmbarqueFinalizadoTotal()) {
            badge.textContent = '✅ Conferido';
        } else {
            badge.textContent = `Conferido parcial ${conferidos}/${total}`;
        }
    }
}

function marcarEntregaConferida(entregaId, button) {
    const card = document.querySelector(`#acerto-conteudo [data-entrega-card][data-entrega-id="${entregaId}"]`);
    const ids = getConferencias();
    if (!ids.includes(Number(entregaId))) {
        ids.push(Number(entregaId));
        salvarConferencias(ids);
    }
    if (card) {
        card.classList.add('is-conferido');
        card.dataset.conferido = '1';
        const heading = card.querySelector('.acerto-client-heading');
        if (heading && !heading.querySelector('.acerto-conferido-tag')) {
            heading.insertAdjacentHTML('beforeend', getConferidoTagHtml());
        }
    }
    if (button) button.innerHTML = '<i class="fa-solid fa-check"></i> Pedido conferido';
    atualizarResumoConferenciaModal();
    filtrarEntregasAcerto();
}

// ================================================================
// MARCAR EMBARQUE COMO CONFERIDO (Conferido Total)
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.2):
//   - Regra ajustada: uma entrega com divergência é considerada
//     "tratada" se tiver **UMA** destas condições:
//       (a) Pedido de FALTANTE criado (frota_acerto_pedido) → ERP
//       (b) Comprovante de DEVOLUÇÃO emitido (número DEV-AAAA-NNNNNN)
//   - Antes, só checava (a), o que bloqueava indevidamente entregas
//     que só tinham devolução (que NÃO gera pedido ERP).
// ================================================================
function marcarEmbarqueConferido() {
    const cards = Array.from(document.querySelectorAll('#acerto-conteudo [data-entrega-card]'));
    if (!cards.length) {
        showError('Nenhuma entrega encontrada para conferir.');
        return;
    }

    const naoConferidas = cards.filter(card => card.dataset.conferido !== '1' && !card.classList.contains('is-conferido'));

    const dados = window.acertoDadosAtual || {};
    const pedidosAcerto = dados.pedidos_acerto || [];

    // ============================================================
    // 🔥 AJUSTE Bloco 4.2: considera "tratada" se tiver
    //    PEDIDO DE FALTANTE **ou** COMPROVANTE DE DEVOLUÇÃO
    // ============================================================
    const cardsComDivergenciaSemPedido = cards.filter(card => {
        if (card.dataset.divergencia !== '1') return false;

        const entregaId = Number(card.dataset.entregaId);
        const entrega = (dados.entregas || []).find(e => Number(e.id) === entregaId);
        if (!entrega) return false;

        // (a) Tem pedido de faltante criado?
        const temPedidoFaltante = pedidosAcerto.some(p =>
            Number(p.entrega_id) === entregaId
        );

        if (temPedidoFaltante) return false; // tratada, ok

        // (b) Tem comprovante de devolução emitido?
        const problemas = Array.isArray(entrega.problemas) ? entrega.problemas : [];
        const temComprovanteDevolucao = problemas.some(p => {
            const tipo = String(p.tipo_problema || '').toLowerCase();
            if (tipo !== 'devolucao') return false;
            // Considera tratado SOMENTE se o comprovante foi emitido
            return Boolean(p.tratamento_numero_comprovante && p.tratamento_id);
        });

        if (temComprovanteDevolucao) return false; // tratada, ok

        // Chegou aqui: tem divergência e nenhum tratamento finalizado
        return true;
    });
    // ============================================================

    if (naoConferidas.length > 0) {
        const nomes = naoConferidas.slice(0, 5).map(c => c.dataset.cliente || '#' + c.dataset.entregaId).join(', ');
        Swal.fire({
            icon: 'warning',
            title: 'Entregas pendentes de conferência',
            html: `Ainda há <b>${naoConferidas.length}</b> entrega(s) não conferida(s):<br><span style="font-size:13px;">${escapeHtml(nomes)}${naoConferidas.length > 5 ? '…' : ''}</span><br><br>Confira todos os clientes antes de finalizar o embarque.`,
            confirmButtonText: 'Entendi'
        });
        return;
    }

    if (cardsComDivergenciaSemPedido.length > 0) {
        const nomes = cardsComDivergenciaSemPedido.slice(0, 5).map(c => c.dataset.cliente || '#' + c.dataset.entregaId).join(', ');
        Swal.fire({
            icon: 'warning',
            title: 'Divergências sem tratamento finalizado',
            html: `
                Existe(m) <b>${cardsComDivergenciaSemPedido.length}</b> entrega(s) com divergência sem pedido de faltante <b>ou</b> comprovante de devolução:
                <br><span style="font-size:13px;">${escapeHtml(nomes)}${cardsComDivergenciaSemPedido.length > 5 ? '…' : ''}</span>
                <br><br>
                Gere o <b>pedido de faltante</b> ou o <b>comprovante de devolução</b> antes de concluir o "Conferido Total".
            `,
            confirmButtonText: 'Entendi'
        });
        return;
    }

    Swal.fire({
        icon: 'warning',
        title: 'Confirmar Conferido Total?',
        html: 'Todos os clientes deste embarque serão marcados como <b>conferidos</b>.<br><br>Esta ação <b>não possui estorno</b>. Deseja continuar?',
        showCancelButton: true,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'Sim, concluir conferência',
        cancelButtonText: 'Cancelar'
    }).then(result => {
        if (!result.isConfirmed) return;

        const ids = cards.map(card => Number(card.dataset.entregaId)).filter(Boolean);
        salvarConferencias(ids);
        cards.forEach(card => {
            card.classList.add('is-conferido');
            card.dataset.conferido = '1';
            const heading = card.querySelector('.acerto-client-heading');
            if (heading && !heading.querySelector('.acerto-conferido-tag')) {
                heading.insertAdjacentHTML('beforeend', getConferidoTagHtml());
            }
        });
        atualizarResumoConferenciaModal();
        filtrarEntregasAcerto();

        gerarComprovanteConferenciaTotal(dados, cards);
    });
}

// ================================================================
// COMPROVANTE DE CONFERÊNCIA TOTAL
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.2):
//   - Aceita parâmetro `opcoes = { modoVisualizacao: false }`.
//   - Quando `modoVisualizacao = true`:
//     • NÃO altera o status do acerto no servidor ao imprimir
//     • Botão "Imprimir" chama window.print() direto
//   - Quando `false` (fluxo normal):
//     • Após imprimir, chama finalizarAposComprovanteImpresso()
// ================================================================
function gerarComprovanteConferenciaTotal(dados, cards, opcoes = {}) {
    const modoVisualizacao = opcoes.modoVisualizacao === true;

    const dataAtual = new Date();
    const dataFormatada = dataAtual.toLocaleDateString('pt-BR') + ' ' + dataAtual.toLocaleTimeString('pt-BR');
    const motorista = dados.motorista_nome || dados.motorista || document.getElementById('acerto-header-motorista')?.textContent?.replace(/^[^A-Za-zÀ-ÿ0-9]*/, '') || 'N/A';
    const veiculo = dados.veiculo_placa || dados.veiculo || document.getElementById('acerto-header-veiculo')?.textContent?.replace(/^[^A-Za-zÀ-ÿ0-9]*/, '') || 'N/A';
    const numeroEmbarque = dados.numero_embarque || acertoAtual.embarque_id || 'N/A';

    const linhas = cards.map(card => {
        const cliente = escapeHtml(card.dataset.cliente || 'Cliente');
        const pedidos = escapeHtml(card.dataset.pedidos || '');
        const teveDivergencia = card.dataset.divergencia === '1';
        return `
            <tr>
                <td>${cliente}</td>
                <td>${pedidos}</td>
                <td style="text-align:center;">${teveDivergencia ? '⚠️ Divergência' : '✅ OK'}</td>
            </tr>
        `;
    }).join('');

    const divergentes = cards.filter(c => c.dataset.divergencia === '1');
    const observacoesHtml = divergentes.length > 0 ? `
        <div class="comprovante-obs">
            <strong>Observações de divergência:</strong>
            <ul>
                ${divergentes.map(c => `<li>${escapeHtml(c.dataset.cliente || '')} — pedido(s) ${escapeHtml(c.dataset.pedidos || '')} com divergência registrada e tratada via pedido de acerto.</li>`).join('')}
            </ul>
        </div>
    ` : '';

    const html = `
        <div class="comprovante-conferencia">
            <div class="comprovante-header">
                <h2>Comprovante de Conferência de Embarque</h2>
                <p>Embarque #${escapeHtml(String(numeroEmbarque))} — Emitido em ${dataFormatada}</p>
            </div>
            <div class="comprovante-dados">
                <div><strong>Motorista:</strong> ${escapeHtml(motorista)}</div>
                <div><strong>Veículo:</strong> ${escapeHtml(veiculo)}</div>
                <div><strong>Total de entregas conferidas:</strong> ${cards.length}</div>
                <div><strong>Divergências:</strong> ${divergentes.length}</div>
            </div>
            <table class="comprovante-tabela">
                <thead>
                    <tr><th>Cliente</th><th>Pedido(s)</th><th>Status</th></tr>
                </thead>
                <tbody>${linhas}</tbody>
            </table>
            ${observacoesHtml}
            <p class="comprovante-declaracao">
                Declaro que finalizei a entrega de todos os pedidos deste embarque, estando ciente das divergências
                apontadas acima (quando houver), as quais foram tratadas por meio de pedido de acerto (faltante/devolução).
            </p>
            <div class="comprovante-assinatura">
                <div class="linha-assinatura"></div>
                <span>Assinatura do Motorista</span>
            </div>
        </div>
    `;

    const modalEl = document.getElementById('modalComprovanteConferencia');
    const corpo = document.getElementById('comprovante-conferencia-corpo');

    if (modalEl && corpo) {
        corpo.innerHTML = html;

        // Ajusta o botão "Imprimir" conforme o modo
        const footer = modalEl.querySelector('.modal-footer');
        if (footer) {
            const btnImprimir = footer.querySelector('button[onclick="imprimirComprovanteConferencia()"]');
            if (btnImprimir) {
                btnImprimir.setAttribute('onclick', modoVisualizacao
                    ? 'imprimirComprovanteConferenciaVisualizacao()'
                    : 'imprimirComprovanteConferencia()');
            }
        }

        // Se não é modo visualização, guarda o flag pra o afterprint saber
        if (!modoVisualizacao) {
            modalEl.dataset.aguardandoFinalizacao = '1';
        } else {
            modalEl.dataset.aguardandoFinalizacao = '0';
        }

        const modalInstance = new bootstrap.Modal(modalEl);
        modalInstance.show();
    } else {
        // Fallback: abre em nova janela
        const win = window.open('', '_blank');
        win.document.write(`<html><head><title>Comprovante</title></head><body>${html}</body></html>`);
        win.document.close();
        win.print();
    }
}

// ================================================================
// IMPRIMIR COMPROVANTE DE CONFERÊNCIA
//
// 🔥 MUDANÇA 2026-09-21 (Bloco 4.2):
//   - Verifica `modalEl.dataset.aguardandoFinalizacao`:
//     • '1' → depois de imprimir, chama finalizarAposComprovanteImpresso()
//     • '0' → apenas imprime (modo visualização)
// ================================================================
function imprimirComprovanteConferencia() {
    const modalEl = document.getElementById('modalComprovanteConferencia');
    const aguardandoFinalizacao = modalEl?.dataset.aguardandoFinalizacao === '1';

    if (!aguardandoFinalizacao) {
        // Modo visualização: só imprime
        window.print();
        return;
    }

    const onAfterPrint = () => {
        window.removeEventListener('afterprint', onAfterPrint);
        finalizarAposComprovanteImpresso();
    };
    window.addEventListener('afterprint', onAfterPrint);
    window.print();
}
// ================================================================
// IMPRIMIR COMPROVANTE DE CONFERÊNCIA EM MODO VISUALIZAÇÃO
//
// 🔥 NOVO 2026-09-21 (Bloco 4.2):
//   Chamada quando o botão "Imprimir" é clicado dentro do modal
//   aberto via "Visualizar Comprovante" (já finalizado).
//   NÃO dispara `finalizarAposComprovanteImpresso()` —
//   apenas imprime.
// ================================================================
function imprimirComprovanteConferenciaVisualizacao() {
    window.print();
}

// ================================================================
// VISUALIZAR COMPROVANTE DE CONFERÊNCIA (já emitido)
//
// 🔥 NOVO 2026-09-21 (Bloco 4.2):
//   Chamada pelo botão "Visualizar Comprovante" no footer do modal
//   quando o embarque já foi conferido. Reconstrói o HTML do
//   comprovante a partir dos dados atuais + cards marcados como
//   conferidos, e abre o modal sem disparar "finalizar" novamente.
// ================================================================
function visualizarComprovanteConferencia() {
    const dados = window.acertoDadosAtual || {};
    const cards = Array.from(document.querySelectorAll('#acerto-conteudo [data-entrega-card]'));

    if (!cards.length) {
        showError('Nenhuma entrega encontrada para gerar o comprovante.');
        return;
    }

    // Reconstrói o comprovante a partir dos dados atuais
    // (mesma lógica de `gerarComprovanteConferenciaTotal`, mas sem
    // disparar finalização automática no afterprint)
    gerarComprovanteConferenciaTotal(dados, cards, { modoVisualizacao: true });
}

// ================================================================
// IMPRIMIR COMPROVANTE DE DEVOLUÇÃO (Bloco 4, Etapa 4) — v3 CORRIGIDA
//
// Monta HTML estruturado e dispara window.print() em nova janela.
// O CSS @media print está embutido inline para não depender do
// acerto-embarque.css (que é carregado só na página principal).
//
// 🔥 CORRIGIDO 2026-09-21 (v3):
//   - Aceita payload ACHATADO (novo backend: cliente_nome, motorista_nome,
//     veiculo_placa no top-level) E ANINHADO (compatibilidade)
//   - Prioridade: top-level > aninhado
//   - `itens` e `itens_devolvidos` são equivalentes
//   - `endereco_completo` já formatado tem prioridade
//   - Adiciona CPF do motorista e código de rastreamento na saída
// ================================================================
function imprimirComprovanteDevolucao(dados) {
    if (!dados || typeof dados !== 'object') {
        showError('Dados do comprovante inválidos.');
        return;
    }

    // ================================================================
    // Helpers locais
    // ================================================================
    const esc = (v) => String(v ?? '').replace(/[&<>'"]/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
    }[c]));

    const fmtData = (s) => {
        if (!s) return '—';
        try {
            const d = new Date(s);
            if (isNaN(d.getTime())) return String(s);
            return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch { return String(s); }
    };

    const fmtMoeda = (v) => {
        const n = parseFloat(v);
        if (isNaN(n)) return 'R$ 0,00';
        return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    };

    const fmtQtd = (v) => {
        const n = parseFloat(v);
        if (isNaN(n)) return '0';
        return Number.isInteger(n) ? String(n) : n.toFixed(3).replace(/\.?0+$/, '');
    };

    // ================================================================
    // 🔥 CORRIGIDO 2026-09-21:
    //   Aceita AMBAS as estruturas: achatada (novo backend) e aninhada
    //   (compatibilidade com chamadas antigas).
    //   Prioridade: top-level > aninhado.
    // ================================================================
    const entrega  = dados.entrega  || {};
    const embarque = dados.embarque || {};

    // Número e emitente
    const numero        = dados.numero_comprovante || dados.numero || dados.comprovante_numero || '—';
    const emitidoEm     = dados.comprovante_emitido_em || dados.emitido_em || dados.created_at || new Date().toISOString();
    const emitenteNome  = (dados.emitido_por && dados.emitido_por.nome)
                            || dados.emitente_nome
                            || dados.gestor_nome
                            || 'Gestor';

    // Embarque
    const embarqueId    = dados.embarque_id || embarque.id || (acertoAtual && acertoAtual.embarque_id) || '—';
    const numeroEmb     = dados.numero_embarque || embarque.numero_embarque || `#${embarqueId}`;

    // Cliente / entrega
    const clienteNome   = dados.cliente_nome || entrega.cliente_nome || '—';
    const clienteEnd    = dados.endereco_completo
                            || entrega.endereco_completo
                            || [
                                (dados.endereco || entrega.endereco || '') + ' ' + (dados.numero_end || entrega.numero_end || ''),
                                dados.bairro || entrega.bairro || '',
                                (dados.cidade || entrega.cidade || '') + '/' + (dados.uf || entrega.uf || '')
                              ].filter(Boolean).join(', ').trim();

    const codigoRastreio = dados.codigo_rastreamento || entrega.codigo_rastreamento || '';
    const entregaId      = dados.entrega_id || entrega.id || '—';

    // Motorista / veículo
    const motoristaNome  = dados.motorista_nome || embarque.motorista_nome || '—';
    const motoristaCpf   = dados.motorista_cpf  || embarque.motorista_cpf  || '';
    const veiculoPlaca   = dados.veiculo_placa  || embarque.veiculo_placa  || '—';
    const veiculoModelo  = dados.veiculo_modelo || embarque.veiculo_modelo || '';

    // Itens (aceita 'itens' e 'itens_devolvidos')
    const itens = Array.isArray(dados.itens)
        ? dados.itens
        : (Array.isArray(dados.itens_devolvidos) ? dados.itens_devolvidos : []);

    // ================================================================
    // Linhas da tabela de itens
    // ================================================================
    const linhasItens = itens.length > 0
        ? itens.map((it, idx) => {
            const ref     = it.referencia || it.ref || '—';
            const desc    = it.descricao || it.desc || '—';
            const qtdPrev = fmtQtd(it.quantidade_prevista ?? it.qtd_prevista ?? 0);
            const qtdEnt  = fmtQtd(it.quantidade_entregue ?? it.qtd_entregue ?? 0);
            const qtdDev  = fmtQtd(it.quantidade_devolvida ?? it.qtd_devolvida ?? it.quantidade ?? 0);
            const motivo  = it.motivo || it.observacao || '—';
            const valor   = parseFloat(it.valor_total ?? it.valor ?? 0);
            const valorFmt = valor > 0 ? fmtMoeda(valor) : '—';

            return `
                <tr>
                    <td style="text-align:center;">${idx + 1}</td>
                    <td>${esc(ref)}</td>
                    <td>${esc(desc)}</td>
                    <td style="text-align:right;">${qtdPrev}</td>
                    <td style="text-align:right;">${qtdEnt}</td>
                    <td style="text-align:right; font-weight:700; color:#b45309;">${qtdDev}</td>
                    <td>${esc(motivo)}</td>
                    <td style="text-align:right;">${valorFmt}</td>
                </tr>
            `;
        }).join('')
        : '<tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:20px;">Nenhum item informado</td></tr>';

    // ================================================================
    // Totais
    // ================================================================
    const totalItens     = itens.length;
    const totalUnidades  = itens.reduce((acc, it) => acc + parseFloat(it.quantidade_devolvida ?? it.qtd_devolvida ?? it.quantidade ?? 0), 0);
    const totalValor     = itens.reduce((acc, it) => acc + parseFloat(it.valor_total ?? it.valor ?? 0), 0);

    // ================================================================
    // HTML completo
    // ================================================================
    const html = `
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Devolução — ${esc(numero)}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #1f2937;
            margin: 0;
            padding: 24px;
            background: #fff;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #166534;
            padding-bottom: 14px;
            margin-bottom: 18px;
        }
        .header h1 {
            margin: 0 0 4px;
            font-size: 1.4rem;
            color: #14532d;
        }
        .header .sub {
            font-size: 0.85rem;
            color: #6b7280;
            margin: 0;
        }
        .header .numero {
            text-align: right;
        }
        .header .numero strong {
            display: block;
            font-size: 1.15rem;
            font-family: 'Courier New', monospace;
            color: #166534;
            letter-spacing: 1px;
        }
        .header .numero span {
            font-size: 0.72rem;
            color: #6b7280;
        }
        .secao {
            margin-bottom: 16px;
        }
        .secao h2 {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #166534;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 4px;
            margin: 0 0 8px;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 24px;
            font-size: 0.86rem;
        }
        .grid-2 div strong { color: #374151; }
        .grid-2 div span { color: #1f2937; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8rem;
            margin-top: 4px;
        }
        thead th {
            background: #f0fdf4;
            color: #166534;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 7px 8px;
            border: 1px solid #d1d5db;
            text-align: left;
        }
        tbody td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            vertical-align: top;
        }
        tbody tr:nth-child(even) { background: #fafafa; }
        .totais {
            display: flex;
            justify-content: flex-end;
            gap: 32px;
            margin-top: 10px;
            font-size: 0.88rem;
        }
        .totais div { text-align: right; }
        .totais div strong {
            display: block;
            color: #166534;
            font-size: 1.05rem;
        }
        .totais div span {
            font-size: 0.7rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .declaracao {
            margin-top: 26px;
            font-size: 0.85rem;
            line-height: 1.55;
            color: #374151;
            background: #f9fafb;
            border-left: 4px solid #16a34a;
            padding: 12px 14px;
            border-radius: 4px;
        }
        .assinaturas {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-top: 60px;
        }
        .assinaturas .linha {
            border-top: 1px solid #1f2937;
            padding-top: 6px;
            text-align: center;
            font-size: 0.78rem;
            color: #6b7280;
        }
        .rodape {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            font-size: 0.7rem;
            color: #9ca3af;
            text-align: center;
        }
        @media print {
            body { padding: 12px; }
            .header { page-break-after: avoid; }
            table { page-break-inside: auto; }
            tr { page-break-inside: avoid; page-break-after: auto; }
            thead { display: table-header-group; }
            .assinaturas { page-break-inside: avoid; }
        }
        @page { margin: 12mm; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>Comprovante de Devolução</h1>
            <p class="sub">Documento para faturamento — não gera pedido no ERP</p>
        </div>
        <div class="numero">
            <strong>${esc(numero)}</strong>
            <span>Emitido em ${fmtData(emitidoEm)}</span>
        </div>
    </div>

    <div class="secao">
        <h2>Emitente</h2>
        <div class="grid-2">
            <div><strong>Gestor responsável:</strong> <span>${esc(emitenteNome)}</span></div>
            <div><strong>Embarque:</strong> <span>${esc(numeroEmb)}</span></div>
        </div>
    </div>

    <div class="secao">
        <h2>Entrega / Cliente</h2>
        <div class="grid-2">
            <div><strong>Cliente:</strong> <span>${esc(clienteNome)}</span></div>
            <div><strong>Entrega #:</strong> <span>${esc(entregaId)}</span></div>
            <div style="grid-column: 1 / -1;"><strong>Endereço:</strong> <span>${esc(clienteEnd || '—')}</span></div>
            ${codigoRastreio ? `<div><strong>Código de rastreio:</strong> <span>${esc(codigoRastreio)}</span></div>` : ''}
        </div>
    </div>

    <div class="secao">
        <h2>Embarque</h2>
        <div class="grid-2">
            <div><strong>Motorista:</strong> <span>${esc(motoristaNome)}</span></div>
            <div><strong>CPF:</strong> <span>${esc(motoristaCpf || '—')}</span></div>
            <div><strong>Veículo:</strong> <span>${esc(veiculoPlaca)}</span></div>
            <div><strong>Modelo:</strong> <span>${esc(veiculoModelo || '—')}</span></div>
        </div>
    </div>

    <div class="secao">
        <h2>Itens Devolvidos</h2>
        <table>
            <thead>
                <tr>
                    <th style="width:32px; text-align:center;">#</th>
                    <th style="width:90px;">Referência</th>
                    <th>Descrição</th>
                    <th style="width:70px; text-align:right;">Prev.</th>
                    <th style="width:70px; text-align:right;">Entregue</th>
                    <th style="width:80px; text-align:right;">Devolvido</th>
                    <th style="width:130px;">Motivo</th>
                    <th style="width:90px; text-align:right;">Valor</th>
                </tr>
            </thead>
            <tbody>
                ${linhasItens}
            </tbody>
        </table>

        <div class="totais">
            <div>
                <strong>${totalItens}</strong>
                <span>Itens</span>
            </div>
            <div>
                <strong>${fmtQtd(totalUnidades)}</strong>
                <span>Unidades</span>
            </div>
            <div>
                <strong>${fmtMoeda(totalValor)}</strong>
                <span>Valor total</span>
            </div>
        </div>
    </div>

    <div class="declaracao">
        Declaro, para fins de faturamento, que os itens acima relacionados foram <strong>devolvidos</strong>
        pelo cliente no ato da entrega do embarque <strong>${esc(numeroEmb)}</strong>, e que os mesmos serão
        tratados pelo setor de faturamento conforme política comercial vigente.
    </div>

    <div class="assinaturas">
        <div class="linha">Assinatura do Motorista</div>
        <div class="linha">Assinatura do Gestor / Conferente</div>
    </div>

    <div class="rodape">
        Documento gerado eletronicamente pelo Portal Nutricional • ${fmtData(new Date().toISOString())}
    </div>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                try { window.print(); } catch (e) { console.warn('Print bloqueado:', e); }
            }, 250);
        });
    </script>
</body>
</html>
    `.trim();

    // ================================================================
    // Abre em nova janela e escreve o HTML
    // ================================================================
    const win = window.open('', '_blank', 'width=1000,height=800,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes,resizable=yes');

    if (!win) {
        // Popup bloqueado — oferece download como fallback
        Swal.fire({
            icon: 'warning',
            title: 'Popup bloqueado',
            html: `
                <p>O navegador bloqueou a janela de impressão.</p>
                <p style="font-size:0.85rem;color:#64748b;">Permita popups para este site ou use o botão abaixo para baixar o HTML e abrir manualmente.</p>
            `,
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-download"></i> Baixar HTML',
            cancelButtonText: 'Fechar',
            confirmButtonColor: '#2563eb'
        }).then((r) => {
            if (r.isConfirmed) {
                const blob = new Blob([html], { type: 'text/html;charset=utf-8' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `comprovante-devolucao-${numero}.html`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                setTimeout(() => URL.revokeObjectURL(url), 2000);
            }
        });
        return;
    }

    win.document.open();
    win.document.write(html);
    win.document.close();
    win.focus();
}

// ================================================================
// FINALIZA O EMBARQUE AUTOMATICAMENTE APÓS A IMPRESSÃO
// ================================================================
function finalizarAposComprovanteImpresso() {
    const embarqueId = acertoAtual.embarque_id;
    if (embarqueId) {
        localStorage.setItem(`frota:acerto:finalizado:${embarqueId}`, '1');
    }

    const finalizarNoServidor = acertoAtual.id
        ? fetchAuth(API_BASE + '/frota/acerto/' + acertoAtual.id + '/finalizar', {
            method: 'POST',
            body: JSON.stringify({ assinatura_gestor: null })
        }).catch(err => {
            console.warn('⚠️ Erro de rede ao finalizar acerto:', err);
            return { success: false, error: err.message };
        })
        : Promise.resolve({ success: true });

    finalizarNoServidor.then((response) => {
        if (!response || response.success === false) {
            const erroMsg = (response && response.error) || 'Não foi possível finalizar o acerto no servidor.';

            if (embarqueId) {
                localStorage.removeItem(`frota:acerto:finalizado:${embarqueId}`);
            }

            const modalComprovanteEl = document.getElementById('modalComprovanteConferencia');
            if (modalComprovanteEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                const inst = bootstrap.Modal.getInstance(modalComprovanteEl) || new bootstrap.Modal(modalComprovanteEl);
                inst.hide();
            }

            Swal.fire({
                icon: 'error',
                title: 'Falha ao finalizar no servidor',
                html: `
                    <div style="text-align:left;">
                        <p>O comprovante foi impresso, mas o acerto <b>não foi finalizado</b> no sistema.</p>
                        <p style="font-size:0.85rem;color:#dc2626;background:#fef2f2;padding:8px;border-radius:8px;margin-top:8px;">
                            ${escapeHtml(erroMsg)}
                        </p>
                        <p style="font-size:0.85rem;color:#64748b;margin-top:8px;">
                            Resolva a pendência e clique em <b>Finalizar Acerto</b> novamente.
                        </p>
                    </div>
                `,
                confirmButtonText: 'OK',
                confirmButtonColor: '#dc2626'
            });

            atualizarResumoConferenciaModal();
            return;
        }

        acertoAtual.status = 'finalizado';
        atualizarBotoesAcerto('finalizado');
        atualizarResumoConferenciaModal();

        const badge = document.getElementById('acerto-status-badge');
        if (badge) {
            badge.style.display = 'inline-flex';
            badge.className = 'acerto-header-status';
            badge.textContent = '✅ Conferido';
        }

        const modalComprovanteEl = document.getElementById('modalComprovanteConferencia');
        if (modalComprovanteEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const inst = bootstrap.Modal.getInstance(modalComprovanteEl) || new bootstrap.Modal(modalComprovanteEl);
            inst.hide();
        }

        Swal.fire({
            icon: 'success',
            title: 'Embarque conferido e finalizado!',
            text: 'Comprovante impresso e status atualizado com sucesso.',
            timer: 2500
        });

        fecharModalAcerto();
        carregarEmbarquesParaAcerto(true);
    });
}

function getConferidoTagHtml() {
    return '<span class="acerto-conferido-tag"><i class="fa-solid fa-circle-check"></i> Conferido</span>';
}

function getCardOrder(card, termo, bateBusca) {
    const baseOrder = Number(card.dataset.order || 0);
    const conferido = card.dataset.conferido === '1' || card.classList.contains('is-conferido');
    if (termo && bateBusca) {
        return String((conferido ? 20000 : 0) + baseOrder);
    }
    return String((conferido ? 10000 : 0) + baseOrder);
}

function filtrarEntregasAcerto() {
    const input = document.getElementById('acerto-busca-pedido');
    const result = document.getElementById('acerto-pedido-resultado');
    const termo = normalizarBusca(input?.value || '');
    const filtroAtivo = document.querySelector('.acerto-conferencia-filters button.active')?.dataset.conferencia || 'todos';
    const cards = Array.from(document.querySelectorAll('#acerto-conteudo [data-entrega-card]'));
    if (!termo && filtroAtivo === 'todos') {
        cards.forEach(card => {
            card.hidden = false;
            card.classList.remove('is-search-match');
            card.classList.remove('is-search-dim');
            card.style.order = getCardOrder(card, '', false);
        });
        if (result) {
            result.hidden = true;
            result.innerHTML = '';
        }
        return;
    }
    let encontrados = 0;
    cards.forEach(card => {
        const bateBusca = !termo || normalizarBusca(card.dataset.search || '').includes(termo);
        const bateFiltro = filtroAtivo === 'todos' || card.dataset.conferencia === filtroAtivo;
        card.hidden = !bateFiltro;
        card.classList.toggle('is-search-match', Boolean(termo && bateBusca && bateFiltro));
        card.classList.toggle('is-search-dim', Boolean(termo && !bateBusca && bateFiltro));
        card.style.order = getCardOrder(card, termo, bateBusca);
        if (bateBusca && bateFiltro) encontrados++;
    });
    if (result) {
        result.hidden = false;
        result.innerHTML = encontrados
            ? `<div class="acerto-pedido-result-summary">${encontrados} entrega(s) localizada(s) nos dados carregados</div>`
            : '<div class="acerto-pedido-result-summary">Nenhuma entrega carregada corresponde à busca.</div>';
    }
}

function limparBuscaAcerto() {
    const input = document.getElementById('acerto-busca-pedido');
    if (input) input.value = '';
    filtrarEntregasAcerto();
    input?.focus();
}

function aplicarFiltroConferencia(tipo, button) {
    document.querySelectorAll('.acerto-conferencia-filters button').forEach(item => item.classList.toggle('active', item === button));
    filtrarEntregasAcerto();
}

// ================================================================
// EXPORTAÇÕES GLOBAIS
// ================================================================
window.verDetalhesEntrega = verDetalhesEntrega;
window.formatPeso = formatPeso;
window.abrirZoomFoto = abrirZoomFoto;
window.fecharZoom = fecharZoom;
window.carregarEmbarquesParaAcerto = carregarEmbarquesParaAcerto;
window.abrirAcerto = abrirAcerto;
window.iniciarAcerto = iniciarAcerto;
window.finalizarAcerto = finalizarAcerto;
window.cancelarAcerto = cancelarAcerto;
window.abrirPedidoProblema = abrirPedidoProblema;
window.adicionarItemProblema = adicionarItemProblema;
window.selecionarItemProblema = selecionarItemProblema;
window.calcularTotalItem = calcularTotalItem;
window.removerItemProblema = removerItemProblema;
window.salvarPedidoProblema = salvarPedidoProblema;
window.criarPedidoParaItensProblema = criarPedidoParaItensProblema;
window.aplicarFiltro = aplicarFiltro;
window.buscarEmbarques = buscarEmbarques;
window.mudarLimite = mudarLimite;
window.mudarPagina = mudarPagina;
window.toggleTheme = toggleTheme;
window.formatMoney = formatMoney;
window.formatDate = formatDate;
window.formatDateTime = formatDateTime;
window.getStatusClass = getStatusClass;
window.getStatusLabel = getStatusLabel;
window.getTimelineIcon = getTimelineIcon;
window.getTimelineIconClass = getTimelineIconClass;
window.atualizarBotoesAcerto = atualizarBotoesAcerto;
window.showError = showError;
window.fecharModalAcerto = fecharModalAcerto;
window.filtrarEntregasAcerto = filtrarEntregasAcerto;
window.limparBuscaAcerto = limparBuscaAcerto;
window.aplicarFiltroConferencia = aplicarFiltroConferencia;
window.marcarEntregaConferida = marcarEntregaConferida;
window.marcarEmbarqueConferido = marcarEmbarqueConferido;
window.imprimirComprovanteConferencia = imprimirComprovanteConferencia;
window.gerarComprovanteDevolucao = gerarComprovanteDevolucao;
window.imprimirComprovanteDevolucao = imprimirComprovanteDevolucao;
window.renderizarDetalhesAcerto = renderizarDetalhesAcerto;
window.reimprimirComprovanteDevolucao = reimprimirComprovanteDevolucao;
window.tratarDevolucao = tratarDevolucao;
window.verificarPedidoFaltanteExistente = verificarPedidoFaltanteExistente;
window.mostrarAvisoPedidoExistente = mostrarAvisoPedidoExistente;
window.scrollParaPedido = scrollParaPedido;
window.visualizarComprovanteConferencia = visualizarComprovanteConferencia;
window.imprimirComprovanteConferenciaVisualizacao = imprimirComprovanteConferenciaVisualizacao;