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

let modalAcertoInstance = null;


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
    // 🔥 CORRIGIDO: usar a mesma chave que o resto do sistema (authToken)
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
            
            // Tentar renovar token via refresh
            try {
                const refreshResult = await tentarRenovarToken();
                if (refreshResult) {
                    console.log('🔄 Token renovado. Tentando novamente...');
                    return fetchAuth(url, options);
                }
            } catch (e) {
                console.error('❌ Erro ao renovar token:', e);
            }
            
            throw new Error('Sessão expirada. Faça login novamente.');
        }
        
        if (!res.ok) {
            throw new Error('Erro HTTP ' + res.status + ': ' + res.statusText);
        }
        
        return res.json();
    });
}

async function tentarRenovarToken() {
    try {
        const response = await fetch('/v1/auth/refresh', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        if (response.ok) {
            const data = await response.json();
            if (data.token) {
                localStorage.setItem('authToken', data.token);
                console.log('✅ Token renovado com sucesso');
                return true;
            }
        }
        return false;
    } catch (e) {
        console.error('❌ Erro ao renovar token:', e);
        return false;
    }
}

// ================================================================
// VERIFICAR AUTENTICAÇÃO NA INICIALIZAÇÃO
// ================================================================
function verificarAutenticacao() {
    const token = getToken();
    console.log('🔐 Verificando autenticação...');
    
    if (!token) {
        console.warn('⚠️ Nenhum token encontrado!');
        
        // Tenta redirecionar para login
        Swal.fire({
            icon: 'warning',
            title: 'Sessão não encontrada',
            text: 'Faça login para acessar o módulo de acerto.',
            confirmButtonText: 'Ir para Login',
            confirmButtonColor: '#1a3c34',
            allowOutsideClick: false
        }).then(() => {
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
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
    
    const url = '/v1/frota/acerto/embarques?' + params.toString();
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
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
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
        // ============================================================
        // 1. STATUS DO EMBARQUE
        // ============================================================
        const statusClass = getStatusClass(emb.embarque_status);
        const statusLabel = getStatusLabel(emb.embarque_status);
        
        const statusIconMap = {
            'finalizado': '✅',
            'problema': '⚠️',
            'em_acerto': '🔄',
            'acertado': '📋'
        };
        const statusIcon = statusIconMap[emb.embarque_status] || '📦';
        
        // ============================================================
        // 2. STATUS DO ACERTO
        // ============================================================
        const acertoStatusMap = {
            'pendente': { label: '⏳ Pendente', class: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' },
            'em_andamento': { label: '🔄 Em Andamento', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
            'finalizado': { label: '✅ Finalizado', class: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' },
            'cancelado': { label: '🚫 Cancelado', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' }
        };
        const acertoInfo = acertoStatusMap[emb.acerto_status] || { 
            label: emb.acerto_status || 'N/A', 
            class: 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400' 
        };
        
        // ============================================================
        // 3. PROGRESSO
        // ============================================================
        const totalEntregas = parseInt(emb.total_entregas) || 0;
        const entregasConcluidas = parseInt(emb.entregas_concluidas) || 0;
        const progresso = totalEntregas > 0 ? Math.round((entregasConcluidas / totalEntregas) * 100) : 0;
        
        let barClass = 'em-andamento';
        if (emb.embarque_status === 'problema') {
            barClass = 'problema';
        } else if (progresso >= 100) {
            barClass = 'concluido';
        }
        
        // ============================================================
        // 4. NÚMERO DA LINHA
        // ============================================================
        const num = (embarcar.paginacao.pagina - 1) * embarcar.paginacao.limite + index + 1;
        
        // ============================================================
        // 5. DATA DE SAÍDA FORMATADA
        // ============================================================
        const dataSaida = emb.data_saida ? formatDate(emb.data_saida) : '-';
        
        // ============================================================
        // 6. VALOR TOTAL
        // ============================================================
        const valorTotal = parseFloat(emb.valor_total) || 0;
        
        // ============================================================
        // 7. DETECTAR TEMA PARA CORES ADAPTATIVAS
        // ============================================================
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const textTitle = isDark ? 'text-white' : 'text-gray-800';
        const textSub = isDark ? 'text-gray-400' : 'text-slate-400';
        const textValue = isDark ? 'text-gray-300' : 'text-gray-800';
        
        html += `
            <tr class="row-status-${emb.embarque_status || 'planejado'}">
                <td class="text-center font-bold ${textSub}" data-label="#">${num}</td>
                <td data-label="Embarque">
                    <div class="font-bold ${textTitle}">
                        ${emb.numero_embarque || '#' + emb.id}
                        ${emb.total_embarques_agrupados > 1 ? ` <span class="text-xs text-purple-600 dark:text-purple-400">(Grupo)</span>` : ''}
                    </div>
                    <div class="text-xs ${textSub}">${emb.nome_embarque || ''}</div>
                    ${emb.erp_embarque_id ? `<span class="text-xs bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 px-1.5 py-0.5 rounded-full">ERP: #${emb.erp_embarque_id}</span>` : ''}
                    ${emb.total_embarques_agrupados > 1 ? `<span class="text-xs bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 px-1.5 py-0.5 rounded-full">📦 ${emb.total_embarques_agrupados} embarques</span>` : ''}
                </td>
                <td data-label="Veículo">
                    <div class="font-medium ${textTitle}">${emb.placa || 'N/A'}</div>
                    <div class="text-xs ${textSub}">${emb.modelo || ''}</div>
                </td>
                <td data-label="Motorista">
                    <div class="font-medium ${textTitle}">${emb.motorista_nome || 'N/A'}</div>
                    <div class="text-xs ${textSub}">${emb.motorista_telefone || ''}</div>
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
                        ${statusIcon} ${statusLabel}
                    </span>
                </td>
                <td class="text-center" data-label="Acerto">
                    <span class="px-2 py-1 rounded-full text-xs font-medium ${acertoInfo.class}">
                        ${acertoInfo.label}
                    </span>
                    ${emb.data_fim_acerto ? `<div class="text-xs ${textSub} mt-1">${formatDate(emb.data_fim_acerto)}</div>` : ''}
                </td>
                <td class="text-center" data-label="Ações">
                    <button class="btn-acerto btn-acerto-primary" onclick="abrirAcerto(${emb.id})">
                        <i class="fa-solid fa-file-signature"></i> 
                        <span class="hidden sm:inline">Acertar</span>
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

// ================================================================
// ABRIR ACERTO (MODAL) - VERSÃO CORRIGIDA
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
    if (conteudo) {
        conteudo.innerHTML = `
            <div class="text-center py-8">
                <i class="fa-solid fa-spinner fa-spin text-3xl text-emerald-500"></i>
                <p class="mt-3 text-slate-400">Carregando detalhes do embarque...</p>
            </div>
        `;
    }
    
    // Resetar botões
    const btnIniciar = document.getElementById('btn-iniciar-acerto');
    const btnFinalizar = document.getElementById('btn-finalizar-acerto');
    const btnCancelar = document.getElementById('btn-cancelar-acerto');
    
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
    
    const modal = document.getElementById('modalAcerto');
    if (!modal) {
        showError('Modal de acerto não encontrado');
        return;
    }
    
    // ============================================================
    // 🔥 CORREÇÃO: REMOVER BACKDROPS ANTERIORES
    // ============================================================
    const oldBackdrops = document.querySelectorAll('.modal-backdrop');
    oldBackdrops.forEach(b => b.remove());
    
    // Remover classe modal-open do body
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    
    // ============================================================
    // 🔥 CORREÇÃO: CONFIGURAR O MODAL CORRETAMENTE
    // ============================================================
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
    
    // Forçar o backdrop
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
    
    // Bloquear scroll da página
    document.body.style.overflow = 'hidden';
    document.body.classList.add('modal-open');
    
    // ============================================================
    // 🔥 CORREÇÃO: GARANTIR QUE O MODAL TENHA SCROLL
    // ============================================================
    modal.classList.add('show');
    
    // Forçar que o modal-dialog tenha scroll
    const modalDialog = modal.querySelector('.modal-dialog');
    if (modalDialog) {
        modalDialog.style.maxHeight = '90vh';
        modalDialog.style.display = 'flex';
        modalDialog.style.flexDirection = 'column';
    }
    
    const modalContent = modal.querySelector('.modal-content');
    if (modalContent) {
        modalContent.style.maxHeight = '90vh';
        modalContent.style.display = 'flex';
        modalContent.style.flexDirection = 'column';
    }
    
    // ============================================================
    // 🔥 CORREÇÃO: BODY DO MODAL COM SCROLL
    // ============================================================
    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.style.overflowY = 'auto';
        modalBody.style.flex = '1 1 auto';
        modalBody.style.maxHeight = 'calc(90vh - 130px)';
        modalBody.style.padding = '24px 28px';
    }
    
    // ============================================================
    // 🔥 EVENTO PARA FECHAR COM ESC
    // ============================================================
    const handleEsc = function(e) {
        if (e.key === 'Escape') {
            fecharModalAcerto();
        }
    };
    document.addEventListener('keydown', handleEsc);
    modal._handleEsc = handleEsc;
    
    // ============================================================
    // 🔥 EVENTO PARA FECHAR AO CLICAR NO BACKDROP
    // ============================================================
    backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) {
            fecharModalAcerto();
        }
    });
    
    // ============================================================
    // 🔥 CARREGAR DADOS
    // ============================================================
    const url = '/v1/frota/acerto/' + embarqueId + '/detalhes';
    console.log('📡 Buscando: GET ' + url);
    
    fetchAuth(url)
    .then(data => {
        console.log('📦 Dados recebidos da API:', data);
        
        if (data.success) {
            console.log('✅ Renderizando detalhes do acerto...');
            renderizarDetalhesAcerto(data.data);
            
            if (data.data.acerto_existente) {
                acertoAtual.id = data.data.acerto_existente.id;
                acertoAtual.status = data.data.acerto_existente.status;
                atualizarBotoesAcerto(data.data.acerto_existente.status);
            }
            
            const numeroEl = document.getElementById('acerto-numero');
            if (numeroEl) {
                numeroEl.textContent = data.data.numero_embarque || embarqueId;
            }
            
            // 🔥 ATUALIZAR STATUS BADGE NO HEADER
            const statusBadge = document.getElementById('acerto-status-badge');
            if (statusBadge && data.data.embarque_status) {
                const statusMap = {
                    'planejado': { label: '📋 Planejado', class: 'bg-blue-100 text-blue-700' },
                    'em_andamento': { label: '🚚 Em Andamento', class: 'bg-yellow-100 text-yellow-700' },
                    'finalizado': { label: '✅ Finalizado', class: 'bg-green-100 text-green-700' },
                    'cancelado': { label: '🚫 Cancelado', class: 'bg-red-100 text-red-700' },
                    'problema': { label: '⚠️ Problema', class: 'bg-orange-100 text-orange-700' }
                };
                const info = statusMap[data.data.embarque_status] || { label: data.data.embarque_status, class: 'bg-gray-100 text-gray-700' };
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
                        ${data.error || 'Erro ao carregar detalhes'}
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
                    ${err.message}
                </div>
            `;
        }
    });
    
    // ============================================================
    // 🔥 SALVAR REFERÊNCIA PARA FECHAR
    // ============================================================
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
    
    // Remover backdrop
    if (backdrop) {
        backdrop.remove();
    }
    
    // Restaurar scroll
    document.body.style.overflow = '';
    document.body.classList.remove('modal-open');
    
    // Ocultar modal
    if (modal) {
        modal.style.display = 'none';
        modal.style.visibility = 'hidden';
        modal.style.opacity = '0';
        modal.classList.remove('show');
    }
    
    // Remover evento ESC
    if (modal && modal._handleEsc) {
        document.removeEventListener('keydown', modal._handleEsc);
        delete modal._handleEsc;
    }
    
    // Limpar referência
    if (window.modalAcertoRef) {
        window.modalAcertoRef = null;
    }
}

// ================================================================
// BOTÃO FECHAR DO MODAL - EVENT LISTENER
// ================================================================
document.addEventListener('DOMContentLoaded', function() {
    // Botão fechar do modal
    const closeBtn = document.querySelector('#modalAcerto .btn-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            fecharModalAcerto();
        });
    }
    
    // Botão Fechar do footer
    const closeFooterBtn = document.querySelector('#modalAcerto .btn-secondary-nutri[data-bs-dismiss="modal"]');
    if (closeFooterBtn) {
        closeFooterBtn.addEventListener('click', function(e) {
            e.preventDefault();
            fecharModalAcerto();
        });
    }
});

// ================================================================
// RENDERIZAR DETALHES DO ACERTO - VERSÃO COMPLETA
// ================================================================
function renderizarDetalhesAcerto(dados) {
    console.log('📌 renderizarDetalhesAcerto chamado com dados:', dados);

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
    
    console.log('✅ Preparando HTML...');
    
    try {
        // ============================================================
        // 1. RESUMO DO EMBARQUE - LAYOUT MODERNO
        // ============================================================
        const totalProblemas = dados.resumo_problemas ? 
            dados.resumo_problemas.reduce((acc, p) => acc + parseInt(p.total), 0) : 0;
        
        // ============================================================
        // 🔥 DETECTAR TEMA ATUAL PARA ADAPTAR CLASSES
        // ============================================================
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const bgCard = isDark ? 'bg-gray-800' : 'bg-white';
        const borderCard = isDark ? 'border-gray-700' : 'border-gray-100';
        const textTitle = isDark ? 'text-white' : 'text-gray-800';
        const textSub = isDark ? 'text-gray-400' : 'text-gray-500';
        const textValue = isDark ? 'text-gray-300' : 'text-gray-800';
        const bgHover = isDark ? 'hover:bg-gray-700/30' : 'hover:bg-gray-50';
        
        let html = `
            <!-- Stats Cards - Layout Moderno -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="${bgCard} rounded-2xl p-5 shadow-sm border ${borderCard} hover:shadow-md transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                            <i class="fa-solid fa-user text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs font-medium ${textSub} uppercase tracking-wider">Motorista</div>
                            <div class="font-bold ${textTitle} text-base truncate max-w-[140px]">${dados.motorista_nome || 'N/A'}</div>
                            <div class="text-xs ${textSub}">${dados.motorista_telefone || ''}</div>
                        </div>
                    </div>
                </div>
                <div class="${bgCard} rounded-2xl p-5 shadow-sm border ${borderCard} hover:shadow-md transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                            <i class="fa-solid fa-truck text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs font-medium ${textSub} uppercase tracking-wider">Veículo</div>
                            <div class="font-bold ${textTitle} text-base">${dados.placa || 'N/A'}</div>
                            <div class="text-xs ${textSub}">${dados.modelo || ''}</div>
                        </div>
                    </div>
                </div>
                <div class="${bgCard} rounded-2xl p-5 shadow-sm border ${borderCard} hover:shadow-md transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl bg-purple-50 dark:bg-purple-900/30 flex items-center justify-center text-purple-600 dark:text-purple-400">
                            <i class="fa-solid fa-boxes text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs font-medium ${textSub} uppercase tracking-wider">Entregas</div>
                            <div class="font-bold ${textTitle} text-base">${dados.total_entregas || 0}</div>
                            <div class="text-xs ${textSub}">${dados.embarque_status || 'N/A'}</div>
                        </div>
                    </div>
                </div>
                <div class="${bgCard} rounded-2xl p-5 shadow-sm border ${totalProblemas > 0 ? 'border-red-200 dark:border-red-800' : borderCard} hover:shadow-md transition-all">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-xl ${totalProblemas > 0 ? 'bg-red-50 dark:bg-red-900/30' : 'bg-gray-50 dark:bg-gray-700/30'} flex items-center justify-center ${totalProblemas > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'}">
                            <i class="fa-solid fa-triangle-exclamation text-lg"></i>
                        </div>
                        <div>
                            <div class="text-xs font-medium ${textSub} uppercase tracking-wider">Problemas</div>
                            <div class="font-bold ${totalProblemas > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400 dark:text-gray-500'} text-base">${totalProblemas}</div>
                            <div class="text-xs ${textSub}">pendentes</div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        if (embarquesErp.length > 0) {
            html += `
                <div class="mb-6 ${bgCard} rounded-xl p-4 border ${borderCard} shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <div class="w-1 h-6 bg-purple-500 rounded-full"></div>
                        <h6 class="font-bold ${textTitle}">Embarques vinculados</h6>
                        <span class="text-xs ${textSub}">(${embarquesErp.length})</span>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        ${embarquesErp.map(embarque => {
                            const numero = embarque.numero_embarque || embarque.erp_embarque_id || embarque.id;
                            return `<span class="inline-flex items-center gap-1.5 bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-bold px-3 py-1.5 rounded-lg text-sm font-mono border border-purple-200 dark:border-purple-800">
                                <i class="fa-solid fa-truck"></i> #${numero}
                            </span>`;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        // ============================================================
        // 2. RESUMO DE PROBLEMAS (se houver)
        // ============================================================
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
                            <div class="text-xs font-medium ${textSub} uppercase tracking-wider">${p.tipo_problema}</div>
                            <div class="text-sm ${textSub}">${formatMoney(p.total_valor)}</div>
                        </div>
                    `).join('')}
                </div>
            </div>
            `;
        }

        // ============================================================
        // 3. TIMELINE - Moderna
        // ============================================================
        html += `
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-1 h-6 bg-blue-500 rounded-full"></div>
                    <h6 class="font-bold ${textTitle}">Timeline</h6>
                    <span class="text-xs ${textSub}">(${dados.timeline?.length || 0} eventos)</span>
                </div>
                <div class="${bgCard} rounded-xl p-4 border ${borderCard} shadow-sm max-h-64 overflow-y-auto">
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
                                <span class="font-semibold ${textTitle} text-sm">${item.acao || 'Ação'}</span>
                                <span class="text-xs ${textSub} whitespace-nowrap">${formatDateTime(item.data_hora)}</span>
                            </div>
                            <div class="text-sm ${textSub}">${item.descricao || ''}</div>
                            ${item.usuario_nome ? `<div class="text-xs ${textSub}">👤 ${item.usuario_nome}</div>` : ''}
                        </div>
                    </div>
                `;
            });
        } else {
            html += `<div class="${textSub} text-sm text-center py-4">Nenhuma atividade registrada</div>`;
        }
        
        html += `
                </div>
            </div>
        `;

        // ============================================================
        // 4. ENTREGAS - LAYOUT MODERNO COM PEDIDOS E EMBARQUES EM DESTAQUE
        // ============================================================
        html += `
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-1 h-6 bg-emerald-500 rounded-full"></div>
                    <h6 class="font-bold ${textTitle}">Entregas</h6>
                    <span class="text-xs ${textSub}">(${dados.entregas?.length || 0})</span>
                </div>
        `;
        
        if (dados.entregas && dados.entregas.length > 0) {
            dados.entregas.forEach((entrega, index) => {
                const temProblemas = entrega.problemas && entrega.problemas.length > 0;
                const temChecklist = entrega.checklist && entrega.checklist.length > 0;
                const temFotos = entrega.fotos && entrega.fotos.length > 0;
                const temRomaneio = entrega.foto_romaneio_url;
                
                // Status
                const statusMap = {
                    'pendente': { label: 'Pendente', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
                    'em_entrega': { label: 'Em Rota', class: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' },
                    'entregue': { label: 'Entregue', class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
                    'entregue_com_problema': { label: 'Com Problema', class: 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' },
                    'falha': { label: 'Falha', class: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' },
                    'cancelada': { label: 'Cancelada', class: 'bg-gray-100 text-gray-700 dark:bg-gray-700/30 dark:text-gray-400' }
                };
                const statusInfo = statusMap[entrega.status] || { label: entrega.status || 'N/A', class: 'bg-gray-100 text-gray-700' };
                
                // ============================================================
                // 🔥 EXTRAIR NÚMEROS DOS PEDIDOS (pedido_id ou pedidos_ids)
                // ============================================================
                let pedidosNumeros = [];
                
                // 1. Tentar extrair do campo pedidos_ids (principal)
                if (entrega.pedidos_ids) {
                    const ids = entrega.pedidos_ids.split(',').map(id => id.trim());
                    pedidosNumeros = ids.filter(id => id && id !== '0' && id !== 'null');
                }
                
                // 2. Se não tiver, tentar pegar do pedido_id
                if (pedidosNumeros.length === 0 && entrega.pedido_id) {
                    pedidosNumeros = [String(entrega.pedido_id)];
                }
                
                // 3. Se ainda não tiver, usar fallback
                if (pedidosNumeros.length === 0) {
                    pedidosNumeros = [String(entrega.id)];
                }
                
                // ============================================================
                // 🔥 EXTRAIR NÚMEROS DOS EMBARQUES ERP (erp_embarques_ids)
                // ============================================================
                let erpEmbarquesNumeros = [];
                
                // Tentar extrair do campo erp_embarques_ids
                if (entrega.erp_embarques_ids) {
                    const ids = entrega.erp_embarques_ids.split(',').map(id => id.trim());
                    erpEmbarquesNumeros = ids.filter(id => id && id !== '0' && id !== 'null');
                }
                
                // Se não tiver erp_embarques_ids, tentar usar o erp_embarque_id do pai
                if (erpEmbarquesNumeros.length === 0 && dados.erp_embarque_id) {
                    erpEmbarquesNumeros = [String(dados.erp_embarque_id)];
                }
                
                // Montar display dos pedidos
                const pedidosDisplay = pedidosNumeros.map(num => 
                    `<span class="pedido-tag inline-flex items-center bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 font-bold px-2.5 py-0.5 rounded-lg text-sm font-mono border border-blue-200 dark:border-blue-800">
                        #${num}
                    </span>`
                ).join(' ');
                
                // Montar display dos embarques ERP
                const erpEmbarquesDisplay = erpEmbarquesNumeros.map(num => 
                    `<span class="erp-embarque-tag inline-flex items-center bg-purple-50 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 font-bold px-2.5 py-0.5 rounded-lg text-sm font-mono border border-purple-200 dark:border-purple-800">
                        <i class="fa-solid fa-truck mr-1"></i> #${num}
                    </span>`
                ).join(' ');
                
                const qtdPedidos = pedidosNumeros.length;
                const labelPedidos = qtdPedidos > 1 ? `${qtdPedidos} pedidos` : '1 pedido';
                
                // ============================================================
                // 🔥 ITENS DO CHECKLIST
                // ============================================================
                let itensHtml = '';
                if (temChecklist) {
                    const itensExibir = entrega.checklist.slice(0, 5);
                    const temMais = entrega.checklist.length > 5;
                    
                    itensHtml = `
                        <div class="mt-3 pt-3 border-t ${isDark ? 'border-gray-700' : 'border-gray-100'}">
                            <div class="flex items-center gap-2 mb-2">
                                <i class="fa-solid fa-box text-gray-400 text-xs"></i>
                                <span class="text-xs font-medium ${textSub}">Itens (${entrega.checklist.length})</span>
                            </div>
                            <div class="space-y-1">
                                ${itensExibir.map(item => {
                                    const qtdPrev = parseFloat(item.quantidade_prevista || 0);
                                    const qtdEnt = parseFloat(item.quantidade_entregue || 0);
                                    const isOk = item.status === 'entregue';
                                    const nomeProduto = item.descricao || item.nome_produto || item.produto_nome || item.referencia || 'Item';
                                    const textColor = isOk ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400';
                                    
                                    return `
                                        <div class="flex items-center justify-between text-sm py-1.5 px-3 rounded-lg ${bgHover} transition-colors">
                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                <span class="${textColor}">${isOk ? '✓' : '✗'}</span>
                                                <span class="font-mono text-xs ${textSub} flex-shrink-0">${item.referencia || ''}</span>
                                                <span class="${textTitle} truncate">${nomeProduto}</span>
                                            </div>
                                            <div class="flex items-center gap-2 flex-shrink-0">
                                                <span class="text-xs font-medium ${textColor}">
                                                    ${qtdEnt}/${qtdPrev}
                                                </span>
                                                ${!isOk ? `<span class="text-xs ${textSub}">${item.motivo || ''}</span>` : ''}
                                            </div>
                                        </div>
                                    `;
                                }).join('')}
                                ${temMais ? `<div class="text-xs ${textSub} text-center py-1">+ ${entrega.checklist.length - 5} itens</div>` : ''}
                            </div>
                        </div>
                    `;
                }

                // ============================================================
                // 🔥 FOTOS
                // ============================================================
                let fotosHtml = '';
                if (temFotos) {
                    fotosHtml = `
                        <div class="mt-3 flex gap-2 flex-wrap">
                            ${entrega.fotos.slice(0, 4).map(foto => `
                                <div onclick="abrirZoomFoto('${foto.url_foto}', '${foto.descricao || 'Foto'}')" 
                                     class="w-12 h-12 rounded-lg overflow-hidden cursor-pointer border-2 ${isDark ? 'border-gray-700 hover:border-emerald-400' : 'border-gray-200 hover:border-emerald-500'} transition-all hover:scale-105"
                                     title="${foto.descricao || 'Foto'}">
                                    <img src="${foto.url_foto}" class="w-full h-full object-cover" 
                                         onerror="this.style.display='none';this.parentElement.innerHTML='<div class=\\'w-12 h-12 flex items-center justify-center bg-gray-100 dark:bg-gray-800 text-gray-400\\'><i class=\\'fa-regular fa-image\\'></i></div>'">
                                </div>
                            `).join('')}
                            ${entrega.fotos.length > 4 ? `<div class="text-xs ${textSub} flex items-center">+${entrega.fotos.length - 4}</div>` : ''}
                        </div>
                    `;
                }

                // ============================================================
                // 🔥 ROMANEIO
                // ============================================================
                let romaneioHtml = '';
                if (temRomaneio) {
                    romaneioHtml = `
                        <button onclick="abrirZoomFoto('${entrega.foto_romaneio_url}', 'Romaneio Assinado')" 
                                class="inline-flex items-center gap-1.5 text-xs text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium bg-blue-50 dark:bg-blue-900/20 px-3 py-1 rounded-lg border border-blue-200 dark:border-blue-800 transition-colors">
                            <i class="fa-regular fa-file-pdf"></i> Romaneio
                        </button>
                    `;
                }

                // ============================================================
                // 🔥 VERIFICAR SE TEM PROBLEMAS PARA GERAR PEDIDO
                // ============================================================
                const temItensProblema = entrega.checklist && entrega.checklist.some(item => 
                    item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista
                );

                // ============================================================
                // 🔥 CONSTRUIR CARD MODERNO
                // ============================================================
                const cardBorderColor = temProblemas ? 'border-orange-200 dark:border-orange-800' : (isDark ? 'border-gray-700' : 'border-gray-200');
                const cardBg = temProblemas ? (isDark ? 'bg-orange-900/5' : 'bg-orange-50/30') : (isDark ? 'bg-gray-800' : 'bg-white');
                
                html += `
                    <div class="rounded-xl border ${cardBorderColor} ${cardBg} shadow-sm hover:shadow-md transition-all mb-4 overflow-hidden">
                        <!-- Cabeçalho -->
                        <div class="p-4 ${temProblemas ? 'border-b border-orange-200 dark:border-orange-800' : 'border-b ' + (isDark ? 'border-gray-700' : 'border-gray-200')}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <!-- Cliente -->
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-bold ${textTitle} text-base">
                                            ${entrega.cliente_nome || 'Cliente não identificado'}
                                        </span>
                                        <span class="text-xs ${isDark ? 'bg-gray-700 text-gray-400' : 'bg-gray-100 text-gray-500'} px-2 py-0.5 rounded-full">
                                            ${labelPedidos}
                                        </span>
                                        <span class="text-xs px-2.5 py-0.5 rounded-full font-medium ${statusInfo.class}">
                                            ${statusInfo.label}
                                        </span>
                                    </div>
                                    <!-- Endereço -->
                                    <div class="text-sm ${textSub} mt-1">
                                        <i class="fa-solid fa-location-dot text-gray-400 text-xs"></i> 
                                        ${entrega.endereco || ''} ${entrega.numero || ''} - ${entrega.cidade || ''}/${entrega.uf || ''}
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 flex-shrink-0">
                                    ${romaneioHtml}
                                    ${temItensProblema ? `
                                        <button class="inline-flex items-center gap-1.5 text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors" 
                                                onclick="criarPedidoParaItensProblema(${entrega.id}, '${entrega.cliente_nome || ''}')">
                                            <i class="fa-solid fa-plus"></i> Gerar Pedido
                                        </button>
                                    ` : ''}
                                    ${temProblemas ? `
                                        <button class="inline-flex items-center gap-1.5 text-xs font-medium bg-orange-500 hover:bg-orange-600 text-white px-3 py-1.5 rounded-lg transition-colors" 
                                                onclick="abrirPedidoProblema(${dados.id || dados.acerto_id}, ${entrega.id}, '${entrega.cliente_nome || ''}')">
                                            <i class="fa-solid fa-plus"></i> Criar Pedido
                                        </button>
                                    ` : ''}
                                    ${(temChecklist || temFotos || temRomaneio) ? `
                                        <button class="inline-flex items-center gap-1.5 text-xs font-medium ${isDark ? 'bg-gray-700 text-gray-300 hover:bg-gray-600' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'} px-3 py-1.5 rounded-lg transition-colors" 
                                                onclick="verDetalhesEntrega(${entrega.id})">
                                            <i class="fa-solid fa-eye"></i> Detalhes
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <!-- 🔥 PEDIDOS EM DESTAQUE -->
                            ${pedidosDisplay ? `
                            <div class="mt-2 flex flex-wrap items-center gap-2 p-2.5 bg-blue-50/60 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800/50">
                                <span class="text-xs font-medium ${textSub} flex items-center gap-1">
                                    <i class="fa-solid fa-file-invoice text-blue-400"></i> Pedidos:
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    ${pedidosDisplay}
                                </div>
                            </div>
                            ` : ''}
                            
                            <!-- 🔥 EMBARQUES ERP EM DESTAQUE -->
                            ${erpEmbarquesDisplay ? `
                            <div class="mt-2 flex flex-wrap items-center gap-2 p-2.5 bg-purple-50/60 dark:bg-purple-900/20 rounded-lg border border-purple-100 dark:border-purple-800/50">
                                <span class="text-xs font-medium ${textSub} flex items-center gap-1">
                                    <i class="fa-solid fa-truck text-purple-400"></i> Embarques ERP:
                                </span>
                                <div class="flex flex-wrap gap-1.5">
                                    ${erpEmbarquesDisplay}
                                </div>
                            </div>
                            ` : ''}
                            
                            <!-- Recebedor e Data -->
                            <div class="flex flex-wrap items-center gap-4 mt-2 text-sm ${textSub}">
                                ${entrega.nome_recebedor ? `<span><i class="fa-solid fa-user text-gray-400"></i> <span class="${textTitle} font-medium">${entrega.nome_recebedor}</span></span>` : ''}
                                ${entrega.horario_entrega ? `<span><i class="fa-regular fa-clock text-gray-400"></i> <span class="${textTitle}">${formatDateTime(entrega.horario_entrega)}</span></span>` : ''}
                                ${entrega.codigo_rastreamento ? `<span class="text-xs"><i class="fa-solid fa-qrcode text-gray-400"></i> ${entrega.codigo_rastreamento}</span>` : ''}
                            </div>
                        </div>
                        
                        <!-- Corpo -->
                        <div class="p-4">
                            <!-- Problemas -->
                            ${temProblemas ? `
                                <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-3 mb-3 border border-orange-200 dark:border-orange-800">
                                    <div class="flex items-center gap-2 mb-1">
                                        <i class="fa-solid fa-triangle-exclamation text-orange-500 text-xs"></i>
                                        <span class="text-xs font-semibold text-orange-600 dark:text-orange-400 uppercase tracking-wider">Problemas</span>
                                    </div>
                                    ${entrega.problemas.map(p => `
                                        <div class="flex flex-wrap items-start gap-2 text-sm py-1.5 border-b border-orange-100 dark:border-orange-800 last:border-0">
                                            <span class="font-medium text-orange-600 dark:text-orange-400 capitalize text-xs">${p.tipo_problema}:</span>
                                            <span class="${textTitle} flex-1">${p.descricao_problema || 'Sem descrição'}</span>
                                            <span class="text-xs ${textSub} whitespace-nowrap">${formatDateTime(p.created_at)}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            ` : ''}
                            
                            <!-- Itens -->
                            ${itensHtml}
                            
                            <!-- Fotos -->
                            ${fotosHtml}
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
        // 5. PEDIDOS DE ACERTO CRIADOS
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
                            const totalItens = pedidoItens.length;
                            const isCriadoERP = pedido.status === 'criado_erp';
                            const isPendente = pedido.status === 'pendente';
                            
                            return `
                                <div class="${bgCard} rounded-xl p-4 border ${borderCard} shadow-sm hover:shadow-md transition-all">
                                    <div class="flex flex-wrap justify-between items-center gap-2">
                                        <div>
                                            <span class="font-bold ${textTitle}">#${pedido.id}</span>
                                            <span class="text-xs px-2 py-0.5 rounded-full ${pedido.tipo_problema === 'faltante' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'} ml-2">
                                                ${pedido.tipo_problema === 'faltante' ? '⚠️ Faltante' : '🔄 Devolução'}
                                            </span>
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
                                                        ${pedido.numero_pedido_criado ? ` (${pedido.numero_pedido_criado})` : ''}
                                                    </span>
                                                ` : `
                                                    <span class="text-xs bg-gray-100 text-gray-600 dark:bg-gray-700/30 dark:text-gray-400 px-2 py-1 rounded-full">
                                                        ${pedido.status || 'Pendente'}
                                                    </span>
                                                `
                                            )}
                                        </div>
                                    </div>
                                    ${pedidoItens.length > 0 ? `
                                        <div class="mt-2 flex flex-wrap gap-1">
                                            ${pedidoItens.slice(0, 5).map(item => `
                                                <span class="text-xs ${isDark ? 'bg-gray-700 text-gray-300' : 'bg-gray-100 text-gray-600'} px-2 py-0.5 rounded-full">
                                                    ${item.referencia || 'Item'} (${item.quantidade || 0})
                                                </span>
                                            `).join('')}
                                            ${pedidoItens.length > 5 ? `<span class="text-xs ${textSub}">+${pedidoItens.length - 5} itens</span>` : ''}
                                        </div>
                                    ` : ''}
                                    ${pedido.observacoes ? `
                                        <div class="mt-2 text-xs ${textSub}">
                                            <i class="fa-regular fa-message"></i> ${pedido.observacoes}
                                        </div>
                                    ` : ''}
                                    ${pedido.motivo ? `
                                        <div class="mt-1 text-xs ${textSub}">
                                            <i class="fa-solid fa-info-circle"></i> Motivo: ${pedido.motivo}
                                        </div>
                                    ` : ''}
                                    ${pedido.pedido_erp_criado_id ? `
                                        <div class="mt-1 text-xs text-blue-600 dark:text-blue-400">
                                            <i class="fa-solid fa-link"></i> ERP ID: ${pedido.pedido_erp_criado_id}
                                        </div>
                                    ` : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>
                </div>
            `;
        }

        // ============================================================
        // 6. RESUMO FINAL (se houver acerto finalizado)
        // ============================================================
        if (dados.acerto_existente && dados.acerto_existente.status === 'finalizado') {
            html += `
                <div class="mt-6 p-4 bg-emerald-50 dark:bg-emerald-900/20 rounded-xl border border-emerald-200 dark:border-emerald-800">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-check-circle text-emerald-600 dark:text-emerald-400 text-xl"></i>
                        <div>
                            <p class="font-bold ${textTitle}">Acerto Finalizado</p>
                            <p class="text-sm ${textSub}">
                                Finalizado por ${dados.acerto_existente.gestor_nome || 'Gestor'} em ${formatDateTime(dados.acerto_existente.data_acerto)}
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
        
        console.log('✅ HTML gerado com sucesso, inserindo no DOM...');
        conteudo.innerHTML = html;
        console.log('✅ Conteúdo renderizado com sucesso!');
        
    } catch (error) {
        console.error('❌ Erro ao renderizar detalhes:', error);
        conteudo.innerHTML = `
            <div class="text-center py-8 text-red-500">
                <i class="fa-solid fa-circle-exclamation text-3xl block mb-2"></i>
                Erro ao renderizar detalhes: ${error.message}
            </div>
        `;
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
            const url = '/v1/frota/acerto/iniciar';
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
                    atualizarBotoesAcerto('em_andamento');
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
            const url = '/v1/frota/acerto/' + acertoAtual.id + '/finalizar';
            
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
                        if (modalAcertoInstance) {
                            modalAcertoInstance.hide();
                        }
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
            const url = '/v1/frota/acerto/' + acertoAtual.id + '/cancelar';
            
            console.log('📡 Enviando: POST ' + url);
            
            fetchAuth(url, {
                method: 'POST'
            })
            .then(data => {
                if (data.success) {
                    acertoAtual.status = 'cancelado';
                    atualizarBotoesAcerto('cancelado');
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
// ABRIR MODAL DE PEDIDO DE PROBLEMA - CORRIGIDO
// ================================================================
function abrirPedidoProblema(acertoId, entregaId, clienteNome) {
    if (!acertoId || !entregaId) {
        showError('Dados incompletos para criar pedido');
        return;
    }
    
    const ppAcertoId = document.getElementById('pp-acerto-id');
    const ppEntregaId = document.getElementById('pp-entrega-id');
    const ppClienteNome = document.getElementById('pp-cliente-nome');
    const ppItensBody = document.getElementById('pp-itens-body');
    const ppTotalValor = document.getElementById('pp-total-valor');
    
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
    
    // 🔥 CORRIGIDO: Usar bootstrap.Modal de forma segura
    try {
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            const modalInstance = new bootstrap.Modal(modal);
            modalInstance.show();
        } else {
            // Fallback manual
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
            const url = '/v1/frota/acerto/itens/buscar?q=' + encodeURIComponent(busca) + '&limite=10';
            
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
                            <td><strong>${item.referencia || 'N/A'}</strong></td>
                            <td>${item.descricao || ''}</td>
                            <td class="text-center">${item.saldo_estoque || 0}</td>
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
        <td>${item.descricao || 'Sem descrição'}</td>
        <td><strong>${item.referencia || 'N/A'}</strong></td>
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
// ================================================================
function salvarPedidoProblema() {
    const acertoId = document.getElementById('pp-acerto-id')?.value;
    const entregaId = document.getElementById('pp-entrega-id')?.value;
    const tipo = document.getElementById('pp-tipo-problema')?.value || 'faltante';
    const motivo = document.getElementById('pp-motivo')?.value || '';
    const observacoes = document.getElementById('pp-observacoes')?.value || '';
    
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
    
    Swal.fire({
        title: 'Confirmar',
        text: 'Deseja criar este pedido de ' + tipo + ' com ' + itens.length + ' item(ns)?',
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
                motivo: motivo,
                observacoes: observacoes,
                itens: itens
            };
            
            const url = '/v1/frota/acerto/pedido-problema';
            
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
// ================================================================
// FUNÇÃO AUXILIAR - FORMATAR PESO
// ================================================================
function formatPeso(peso) {
    const valor = parseFloat(peso);
    if (isNaN(valor) || valor === 0) return '0 kg';
    if (valor >= 1000) {
        return (valor / 1000).toFixed(1) + ' t';
    }
    return valor.toFixed(1) + ' kg';
}

// ================================================================
// VER DETALHES DA ENTREGA - COM DESTAQUE PARA ITENS PROBLEMA
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
    
    fetch(`/v1/frota/entregas/${entregaId}`, {
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
        
        // Status da entrega
        const statusMap = {
            'pendente': { label: '⏳ Pendente', class: 'bg-yellow-100 text-yellow-700' },
            'em_entrega': { label: '🚚 Em Rota', class: 'bg-blue-100 text-blue-700' },
            'entregue': { label: '✅ Entregue', class: 'bg-green-100 text-green-700' },
            'entregue_com_problema': { label: '⚠️ Com Problema', class: 'bg-orange-100 text-orange-700' },
            'falha': { label: '❌ Falha', class: 'bg-red-100 text-red-700' },
            'cancelada': { label: '🚫 Cancelada', class: 'bg-gray-100 text-gray-700' }
        };
        const statusInfo = statusMap[entrega.status] || { label: entrega.status || 'N/A', class: 'bg-gray-100 text-gray-700' };
        
        // 🔥 VERIFICAR SE TEM PROBLEMAS PARA GERAR PEDIDO
        const temProblemas = entrega.checklist && entrega.checklist.some(item => 
            item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista
        );
        
        let html = `
            <div style="text-align:left;max-height:500px;overflow-y:auto;padding:4px;">
                <!-- Cabeçalho -->
                <div style="background: linear-gradient(135deg, #1a3c34, #2d5a4e); color: white; border-radius: 12px; padding: 16px 20px; margin-bottom: 16px;">
                    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700;">${entrega.cliente_nome || 'Cliente'}</div>
                            <div style="font-size: 0.85rem; opacity: 0.9;">${entrega.endereco || ''} ${entrega.numero || ''} - ${entrega.cidade || ''}/${entrega.uf || ''}</div>
                        </div>
                        <div style="text-align: right;">
                            <span style="background: ${statusInfo.class.replace('text-', '')}; padding: 4px 12px; border-radius: 100px; font-size: 0.75rem; font-weight: 600; display: inline-block;">
                                ${statusInfo.label}
                            </span>
                            ${entrega.codigo_rastreamento ? `<div style="font-size: 0.7rem; opacity: 0.7; margin-top: 4px;">🔍 ${entrega.codigo_rastreamento}</div>` : ''}
                        </div>
                    </div>
                </div>
                
                <!-- Info Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 8px; margin-bottom: 16px;">
                    <div style="background: #f0fdf4; border-radius: 8px; padding: 8px 12px; border: 1px solid #bbf7d0;">
                        <div style="font-size: 0.6rem; text-transform: uppercase; color: #64748b;">Recebedor</div>
                        <div style="font-weight: 600; font-size: 0.85rem;">${entrega.nome_recebedor || 'Não informado'}</div>
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
        
        // Checklist - Itens com destaque para problemas
        if (entrega.checklist && entrega.checklist.length > 0) {
            const itensProblema = entrega.checklist.filter(item => 
                item.status !== 'entregue' && item.quantidade_entregue < item.quantidade_prevista
            );
            const temProblema = itensProblema.length > 0;
            
            html += `
                <hr style="border: 0; border-top: 2px solid #e5e7eb; margin: 12px 0;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <h6 style="font-weight: 700; font-size: 0.95rem; margin: 0;">
                        <i class="fa-solid fa-clipboard-list"></i> Itens (${entrega.checklist.length})
                        ${temProblema ? `<span style="background: #fee2e2; color: #dc2626; padding: 2px 10px; border-radius: 100px; font-size: 0.65rem; margin-left: 8px;">⚠️ ${itensProblema.length} com problema</span>` : ''}
                    </h6>
                    ${temProblema ? `
                        <button onclick="criarPedidoParaItensProblema(${entrega.id}, '${entrega.cliente_nome || ''}')" 
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
                                        <td style="padding: 6px 10px; font-weight: 500;">${item.referencia || '-'}</td>
                                        <td style="padding: 6px 10px; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${item.descricao || '-'}</td>
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
                                        <td style="padding: 6px 10px; font-size: 0.7rem; color: ${item.motivo ? '#dc2626' : '#94a3b8'};">${item.motivo || '—'}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        // Fotos
        if (entrega.fotos && entrega.fotos.length > 0) {
            html += `
                <hr style="border: 0; border-top: 2px solid #e5e7eb; margin: 12px 0;">
                <h6 style="font-weight: 700; font-size: 0.95rem; margin-bottom: 8px;">
                    <i class="fa-regular fa-images"></i> Fotos (${entrega.fotos.length})
                </h6>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    ${entrega.fotos.map(foto => `
                        <div onclick="abrirZoomFoto('${foto.url_foto}', '${foto.descricao || 'Foto'}')" 
                             style="width: 80px; height: 80px; border-radius: 8px; overflow: hidden; cursor: pointer; border: 2px solid #e5e7eb; transition: transform 0.2s;"
                             onmouseover="this.style.transform='scale(1.05)'" 
                             onmouseout="this.style.transform='scale(1)'">
                            <img src="${foto.url_foto}" style="width:100%;height:100%;object-fit:cover;" 
                                 onerror="this.style.display='none';this.parentElement.innerHTML='<div style=\\'width:80px;height:80px;display:flex;align-items:center;justify-content:center;background:#f3f4f6;font-size:1.5rem;color:#9ca3af;\\'><i class=\\'fa-regular fa-image\\'></i></div>'">
                            <div style="font-size:0.5rem;text-align:center;background:rgba(0,0,0,0.5);color:white;padding:1px 4px;">${foto.descricao || ''}</div>
                        </div>
                    `).join('')}
                </div>
            `;
        }
        
        html += '</div>';
        
        Swal.fire({
            title: `📦 Detalhes da Entrega #${entregaId}`,
            html: html,
            width: '950px',
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
// CRIAR PEDIDO APENAS PARA ITENS COM PROBLEMA - VERSÃO COMPLETA CORRIGIDA
// ================================================================
function criarPedidoParaItensProblema(entregaId, clienteNome) {
    if (!entregaId) {
        showError('ID da entrega não informado');
        return;
    }
    
    const token = getToken();
    if (!token) {
        showError('Token não encontrado');
        return;
    }
    
    // Buscar os itens com problema
    Swal.fire({
        title: 'Carregando...',
        text: 'Buscando itens com problema',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch(`/v1/frota/entregas/${entregaId}`, {
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
        
        // 🔥 FILTRAR APENAS ITENS COM PROBLEMA (quantidade_entregue < quantidade_prevista)
        const itensProblema = entrega.checklist.filter(item => 
            item.status !== 'entregue' && 
            item.quantidade_entregue < item.quantidade_prevista
        );
        
        if (itensProblema.length === 0) {
            Swal.fire('Aviso', 'Nenhum item com problema encontrado nesta entrega.', 'info');
            return;
        }
        
        // 🔥 CALCULAR A QUANTIDADE FALTANTE PARA CADA ITEM
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
        
        // ============================================================
        // MONTAR HTML PARA O MODAL DE CONFIRMAÇÃO
        // ============================================================
        let itensHtml = itensFaltantes.map((item, index) => `
            <div style="background: #fef2f2; border-radius: 8px; padding: 10px 14px; margin-bottom: 8px; border: 1px solid #fca5a5;">
                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 8px;">
                    <div style="flex: 1;">
                        <span style="font-weight: 600; font-size: 0.9rem;">${item.referencia || 'Item'}</span>
                        <span style="font-size: 0.8rem; color: #64748b; display: block;">${item.descricao || 'Sem descrição'}</span>
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
                        <input type="hidden" class="item-faltante-id" value="${item.item_id || 0}">
                        <input type="hidden" class="item-faltante-ref" value="${item.referencia || ''}">
                        <input type="hidden" class="item-faltante-desc" value="${item.descricao || ''}">
                    </div>
                </div>
                ${item.motivo ? `<div style="font-size: 0.7rem; color: #dc2626; margin-top: 4px;">Motivo: ${item.motivo}</div>` : ''}
            </div>
        `).join('');
        
        const totalFaltante = itensFaltantes.reduce((sum, item) => sum + item.quantidade_faltante, 0);
        const totalItens = itensFaltantes.length;
        
        // ============================================================
        // MODAL DE CONFIRMAÇÃO
        // ============================================================
        Swal.fire({
            title: '📝 Criar Pedido de Faltante',
            html: `
                <div style="text-align: left; max-width: 100%;">
                    <div style="background: #f0fdf4; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #bbf7d0;">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                            <span style="font-weight: 600;">👤 Cliente: ${clienteNome || 'Cliente'}</span>
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
            width: '750px',
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
                
                return itensParaEnviar;
            }
        }).then(result => {
            if (result.isConfirmed && result.value) {
                const itens = result.value;
                if (itens.length === 0) {
                    Swal.fire('Erro', 'Nenhum item válido para criar pedido', 'error');
                    return;
                }
                
                // 🔥 ENVIAR PARA O CONTROLLER
                const acertoId = acertoAtual.id || document.getElementById('pp-acerto-id')?.value || 0;
                if (!acertoId) {
                    Swal.fire('Erro', 'ID do acerto não encontrado. Inicie o acerto primeiro.', 'error');
                    return;
                }
                
                const payload = {
                    acerto_id: parseInt(acertoId),
                    entrega_id: parseInt(entregaId),
                    tipo_problema: 'faltante',
                    motivo: 'Faltante no checkout',
                    observacoes: 'Pedido gerado automaticamente a partir dos itens faltantes',
                    itens: itens.map(item => ({
                        iditem: item.iditem,
                        referencia: item.referencia,
                        descricao: item.descricao,
                        quantidade: item.quantidade,
                        valor_unitario: 0,
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
                
                fetch('/v1/frota/acerto/pedido-problema', {
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
                                    <p>Pedido de <strong>faltante</strong> criado para a entrega #${entregaId}</p>
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
                        Swal.fire('Erro', data.error || 'Falha ao criar pedido', 'error');
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

/// ================================================================
// GERAR PEDIDO ERP A PARTIR DO PEDIDO DE ACERTO - VERSÃO COMPLETA
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
    
    // Primeiro, buscar detalhes do pedido para mostrar resumo
    Swal.fire({
        title: 'Carregando...',
        text: 'Buscando dados do pedido',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
    
    fetch(`/v1/frota/acerto/pedido/${pedidoAcertoId}`, {
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
        
        // Montar resumo dos itens
        let itensResumo = '';
        if (pedido.itens_afetados && pedido.itens_afetados.length > 0) {
            itensResumo = pedido.itens_afetados.map(item => `
                <div style="display: flex; justify-content: space-between; padding: 4px 0; border-bottom: 1px solid #e5e7eb; font-size: 0.85rem;">
                    <span>${item.referencia || 'Item'} - ${item.descricao || ''}</span>
                    <span style="font-weight: 600; color: #dc2626;">${item.quantidade} un</span>
                </div>
            `).join('');
        }
        
        // Modal para selecionar transação
        Swal.fire({
            title: '🚀 Gerar Pedido no ERP',
            html: `
                <div style="text-align: left; max-width: 100%;">
                    <div style="background: #f0fdf4; border-radius: 12px; padding: 12px 16px; margin-bottom: 16px; border: 1px solid #bbf7d0;">
                        <div style="display: flex; justify-content: space-between; flex-wrap: wrap;">
                            <span style="font-weight: 600;">📋 Pedido #${pedidoAcertoId}</span>
                            <span style="color: #dc2626; font-weight: 700;">${pedido.tipo_problema || 'Faltante'}</span>
                            <span style="font-weight: 600;">💰 ${formatMoney(pedido.valor_total || 0)}</span>
                        </div>
                    </div>
                    
                    ${itensResumo ? `
                        <div style="max-height: 150px; overflow-y: auto; margin-bottom: 12px; background: #f8fafc; border-radius: 8px; padding: 8px 12px;">
                            <p style="font-weight: 600; font-size: 0.8rem; margin-bottom: 4px;">📦 Itens do Pedido:</p>
                            ${itensResumo}
                        </div>
                    ` : ''}
                    
                    <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 12px;">
                        <i class="fa-solid fa-info-circle"></i> 
                        Selecione a transação e o modo para criar o pedido no ERP.
                    </p>
                    
                    <div id="transacoes-loading" style="text-align: center; padding: 20px;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Carregando transações...
                    </div>
                    <div id="transacoes-container" style="display: none;">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Transação *</label>
                            <select id="select-transacao" class="form-select">
                                <option value="">Selecione uma transação...</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Filial</label>
                            <input type="number" id="filial-id" class="form-control" value="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Modo</label>
                            <select id="modo-erp" class="form-select">
                                <option value="sandbox" selected>🧪 Sandbox (Teste - apenas visualiza SQL)</option>
                                <option value="producao">🚀 Produção (Insere no ERP)</option>
                            </select>
                        </div>
                        <div style="background: #fef3c7; border-radius: 8px; padding: 8px 12px; border: 1px solid #fcd34d;">
                            <span style="font-size: 0.75rem; color: #92400e;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                                Modo <strong>Produção</strong> irá inserir o pedido diretamente no ERP.
                                Use com cuidado!
                            </span>
                        </div>
                    </div>
                </div>
            `,
            width: '650px',
            showCancelButton: true,
            confirmButtonText: '🚀 Gerar Pedido',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#10b981',
            cancelButtonColor: '#dc2626',
            didOpen: async () => {
                try {
                    const response = await fetch('/v1/frota/acerto/transacoes', {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        }
                    });
                    const data = await response.json();
                    
                    const loading = document.getElementById('transacoes-loading');
                    const container = document.getElementById('transacoes-container');
                    const select = document.getElementById('select-transacao');
                    
                    if (data.success && data.data && data.data.length > 0) {
                        data.data.forEach(transacao => {
                            const option = document.createElement('option');
                            option.value = transacao.idtransacao;
                            option.textContent = `${transacao.idtransacao} - ${transacao.idtransacao === 19 ? 'Faltante' : 'Devolução'} - ${transacao.descricao || 'Sem descrição'}`;
                            select.appendChild(option);
                        });
                        loading.style.display = 'none';
                        container.style.display = 'block';
                    } else {
                        loading.innerHTML = `
                            <span style="color: #dc2626;">
                                <i class="fa-solid fa-exclamation-circle"></i> 
                                Nenhuma transação disponível
                            </span>
                        `;
                    }
                } catch (err) {
                    document.getElementById('transacoes-loading').innerHTML = 
                        '<span style="color: #dc2626;">⚠️ Erro ao carregar transações</span>';
                }
            },
            preConfirm: () => {
                const transacaoId = document.getElementById('select-transacao')?.value;
                const filialId = document.getElementById('filial-id')?.value || 1;
                const modo = document.getElementById('modo-erp')?.value || 'sandbox';
                
                if (!transacaoId) {
                    Swal.showValidationMessage('Selecione uma transação');
                    return false;
                }
                
                return {
                    transacao_id: parseInt(transacaoId),
                    filial_id: parseInt(filialId),
                    sandbox: modo === 'sandbox'
                };
            }
        }).then(result => {
            if (result.isConfirmed && result.value) {
                const { transacao_id, filial_id, sandbox } = result.value;
                
                const payload = {
                    id_transacao: transacao_id,
                    id_filial: filial_id,
                    sandbox: sandbox
                };
                
                Swal.fire({
                    title: sandbox ? '🧪 Gerando pedido em modo SANDBOX...' : '🚀 Gerando pedido em PRODUÇÃO...',
                    text: 'Aguarde enquanto o pedido é processado',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });
                
                fetch(`/v1/frota/acerto/pedido/${pedidoAcertoId}/criar-erp`, {
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
                        let mensagem = '✅ Pedido criado no ERP com sucesso!';
                        let icon = 'success';
                        let cor = '#10b981';
                        
                        if (data.sandbox) {
                            mensagem = '🧪 MODO SANDBOX: Pedido validado. Nenhuma inserção foi feita.';
                            icon = 'info';
                            cor = '#3b82f6';
                        }
                        
                        // Montar HTML dos SQLs (se sandbox)
                        let sqlHtml = '';
                        if (data.sandbox && data.sql) {
                            sqlHtml = `
                                <div style="margin-top: 12px; background: #1e293b; color: #e2e8f0; padding: 12px; border-radius: 8px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.7rem; white-space: pre-wrap;">
                                    <strong style="color: #60a5fa;">📋 SQL gerado (NÃO EXECUTADO - Modo Sandbox):</strong>
                                    <hr style="border-color: #334155; margin: 4px 0;">
                                    ${data.sql.pedido ? `<div style="margin-top: 8px;"><span style="color: #34d399;">📄 INSERT pedido:</span>\n${data.sql.pedido}</div>` : ''}
                                    ${data.sql.itens ? `<div style="margin-top: 8px;"><span style="color: #34d399;">📦 INSERT itens:</span>\n${data.sql.itens}</div>` : ''}
                                    ${data.sql.update ? `<div style="margin-top: 8px;"><span style="color: #fbbf24;">🔄 UPDATE totais:</span>\n${data.sql.update}</div>` : ''}
                                </div>
                            `;
                        }
                        
                        // Montar resumo dos dados
                        let dadosHtml = '';
                        if (data.dados_completos) {
                            const d = data.dados_completos;
                            dadosHtml = `
                                <div style="margin-top: 12px; background: ${isDarkTheme() ? '#1e293b' : '#f0fdf4'}; padding: 12px; border-radius: 8px; border: 1px solid ${isDarkTheme() ? '#334155' : '#bbf7d0'};">
                                    <p><strong>📋 Resumo do Pedido:</strong></p>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 4px; font-size: 0.8rem;">
                                        <span><strong>ID Pedido PDA:</strong> ${d.idpedidopda || 'N/A'}</span>
                                        <span><strong>Sequencial:</strong> ${d.sequencial_portal || 'N/A'}</span>
                                        <span><strong>Cliente:</strong> ${d.idcliente || 'N/A'}</span>
                                        <span><strong>Transação:</strong> ${d.idtransacao || 'N/A'}</span>
                                        <span><strong>Filial:</strong> ${d.idfilial || 'N/A'}</span>
                                        <span><strong>Total:</strong> ${formatMoney(d.valortotalpedido || 0)}</span>
                                        <span><strong>Itens:</strong> ${d.itens ? d.itens.length : 0}</span>
                                        <span><strong>Peso Bruto:</strong> ${(d.pesobruto || 0).toFixed(2)} kg</span>
                                    </div>
                                </div>
                            `;
                        }
                        
                        Swal.fire({
                            icon: icon,
                            title: data.sandbox ? '🧪 Modo Sandbox' : '✅ Sucesso!',
                            html: `
                                <div style="text-align: left; padding: 8px; max-height: 600px; overflow-y: auto;">
                                    <div style="background: ${data.sandbox ? '#fef3c7' : '#d1fae5'}; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; border: 1px solid ${data.sandbox ? '#fcd34d' : '#6ee7b7'};">
                                        <span style="color: ${data.sandbox ? '#92400e' : '#065f46'}; font-weight: 600;">
                                            <i class="fa-solid ${data.sandbox ? 'fa-flask' : 'fa-check-circle'}"></i> 
                                            ${mensagem}
                                        </span>
                                    </div>
                                    ${dadosHtml}
                                    ${sqlHtml}
                                    ${data.sandbox ? `
                                        <div style="margin-top: 12px; background: #dbeafe; padding: 8px 12px; border-radius: 8px;">
                                            <span style="font-size: 0.8rem; color: #1e40af;">
                                                <i class="fa-solid fa-info-circle"></i> 
                                                Para ativar a produção, altere o modo para "Produção" no modal.
                                            </span>
                                        </div>
                                    ` : ''}
                                </div>
                            `,
                            width: '850px',
                            confirmButtonText: 'OK',
                            confirmButtonColor: cor
                        });
                        
                        // Recarregar o acerto
                        if (acertoAtual.embarque_id) {
                            setTimeout(() => {
                                abrirAcerto(acertoAtual.embarque_id);
                            }, 1000);
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
            }
        });
    })
    .catch(err => {
        Swal.close();
        console.error('❌ Erro:', err);
        Swal.fire('Erro', err.message, 'error');
    });
}
function atualizarBotoesAcerto(status) {
    const btnIniciar = document.getElementById('btn-iniciar-acerto');
    const btnFinalizar = document.getElementById('btn-finalizar-acerto');
    const btnCancelar = document.getElementById('btn-cancelar-acerto');
    
    if (btnIniciar) btnIniciar.style.display = 'none';
    if (btnFinalizar) btnFinalizar.style.display = 'none';
    if (btnCancelar) btnCancelar.style.display = 'none';
    
    if (status === 'em_andamento' || status === 'pendente') {
        if (btnFinalizar) btnFinalizar.style.display = 'inline-flex';
        if (btnCancelar) btnCancelar.style.display = 'inline-flex';
    } else if (!status) {
        if (btnIniciar) btnIniciar.style.display = 'inline-flex';
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
// ABRIR FOTO COM ZOOM - VERSÃO COMPLETA
// ================================================================
function abrirZoomFoto(url, label) {
    if (!url) {
        showError('URL da foto não disponível');
        return;
    }
    
    // Criar backdrop
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
                style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 6px 16px; border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: background 0.2s;"
                onmouseover="this.style.background='rgba(255,255,255,0.3)'" 
                onmouseout="this.style.background='rgba(255,255,255,0.2)'">
            ✕ Fechar
        </button>
    `;
    
    // Controles de zoom
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
    
    // Fechar ao clicar no backdrop
    backdrop.onclick = (e) => {
        if (e.target === backdrop) {
            fecharZoom();
        }
    };
    
    // Fechar com ESC
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
    // O loading será substituído pelo render
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: message
    });
}

// ================================================================
// TEMA CLARO/ESCURO - COM ATUALIZAÇÃO DA UI
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
    
    // 🔥 ATUALIZAR A UI DO ACERTO SE ESTIVER ABERTO
    const acertoContent = document.getElementById('acerto-conteudo');
    if (acertoContent && acertoContent.innerHTML.trim() !== '' && !acertoContent.innerHTML.includes('Carregando')) {
        // Se o acerto estiver aberto, recarregar os dados
        const acertoNumero = document.getElementById('acerto-numero');
        if (acertoNumero && acertoNumero.textContent !== 'N/A') {
            const embarqueId = window.acertoAtual?.embarque_id;
            if (embarqueId) {
                // Recarregar o acerto com o novo tema
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
    
    // Verificar autenticação
    const autenticado = verificarAutenticacao();
    if (!autenticado) return;
    
    initTheme();
    carregarEmbarquesParaAcerto();
    
    setInterval(() => {
        carregarEmbarquesParaAcerto();
    }, 60000);
});

// ================================================================
// EXPORTAÇÕES GLOBAIS (para uso inline no HTML)
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
