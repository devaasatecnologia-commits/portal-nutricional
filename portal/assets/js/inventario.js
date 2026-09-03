// ==========================================================================
// MÓDULO DE INVENTÁRIO - CONSULTA DE ESTOQUE
// ==========================================================================

// API_TOKEN legado (fallback)
var API_TOKEN = 'xoUM?va.JNG93v)@#i9FyH@B6n0}H4.yst%s8zV8M}xc+ZrFAz5:y6T07HxyYGE~';

// Estado do módulo
const state = {
    itens: [],
    filiais: [],
    marcas: [],
    grupos: []
};

// ==========================================================================
// FUNÇÕES DE AUTENTICAÇÃO (mesmo padrão do carregamento.js)
// ==========================================================================

function getAuthToken() {
    // Tentar diferentes fontes de token
    let token = localStorage.getItem('authToken');
    if (token) return token;
    
    token = localStorage.getItem('token');
    if (token) return token;
    
    token = sessionStorage.getItem('authToken');
    if (token) return token;
    
    return null;
}

function getUserId() {
    const el = document.getElementById('user_id');
    if (el && el.value && el.value !== '0' && el.value !== '') {
        return parseInt(el.value);
    }
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    return userData.uid || 0;
}

// ==========================================================================
// FUNÇÃO DE API (exatamente igual ao carregamento.js)
// ==========================================================================
async function apiFetch(endpoint, method = 'GET', body = null) {
    const token = getAuthToken();
    const url = endpoint.startsWith('http') ? endpoint : `/${endpoint}`;
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    } else if (API_TOKEN) {
        options.headers['X-API-Token'] = API_TOKEN;
    }
    
    if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
        options.body = JSON.stringify(body);
    }
    
    const response = await fetch(url, options);
    
    if (response.status === 401) {
        console.error('[Inventario] Token inválido ou expirado');
        Swal.fire({
            title: 'Sessão Expirada',
            text: 'Faça login novamente para continuar.',
            icon: 'warning',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = '/portal/login.php';
        });
        throw new Error('Sessão expirada');
    }
    
    return response.json();
}

function showToast(message, type = 'success') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: type,
            title: message,
            showConfirmButton: false,
            timer: 3000
        });
    } else {
        console.log(message);
    }
}

// ==========================================================================
// CARREGAMENTO DOS FILTROS
// ==========================================================================
async function carregarFiliais() {
    try {
        const data = await apiFetch('v1/inventario/filiais');
        state.filiais = data;
        const sel = document.getElementById('selFiliais');
        if (sel) {
            sel.innerHTML = data.map(f => `<option value="${f.idfilial}">${f.nome}</option>`).join('');
        }
    } catch (e) {
        console.error('Erro ao carregar filiais:', e);
        showToast('Erro ao carregar filiais', 'error');
    }
}

async function carregarMarcas() {
    try {
        const data = await apiFetch('v1/inventario/marcas');
        state.marcas = data;
        const sel = document.getElementById('selMarcas');
        if (sel) {
            sel.innerHTML = data.map(m => `<option value="${m.idmarca}">${m.descricao}</option>`).join('');
        }
    } catch (e) {
        console.error('Erro ao carregar marcas:', e);
        showToast('Erro ao carregar marcas', 'error');
    }
}

async function carregarGrupos() {
    try {
        const data = await apiFetch('v1/inventario/grupos');
        state.grupos = data;
        const sel = document.getElementById('selGrupos');
        if (sel) {
            sel.innerHTML = data.map(g => `<option value="${g.idgrupo}">${g.descricao}</option>`).join('');
        }
    } catch (e) {
        console.error('Erro ao carregar grupos:', e);
        showToast('Erro ao carregar grupos', 'error');
    }
}

async function buscarItens() {
    const termo = document.getElementById('buscaItem').value.trim();
    if (!termo) {
        showToast('Digite um termo para buscar', 'warning');
        return;
    }
    
    Swal.fire({
        title: 'Buscando...',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    
    try {
        const result = await apiFetch(`v1/inventario/buscar-itens?termo=${encodeURIComponent(termo)}`);
        Swal.close();
        
        const sel = document.getElementById('selItens');
        if (result.data && result.data.length > 0) {
            sel.innerHTML = result.data.map(item => 
                `<option value="${item.iditem}">${item.referencia} - ${item.descricao.substring(0, 50)}</option>`
            ).join('');
            showToast(`${result.data.length} itens encontrados`, 'success');
        } else {
            sel.innerHTML = '<option value="">Nenhum item encontrado</option>';
            showToast('Nenhum item encontrado', 'warning');
        }
    } catch (e) {
        Swal.close();
        console.error('Erro ao buscar itens:', e);
        showToast('Erro ao buscar itens', 'error');
    }
}

function getSelectedValues(selectId) {
    const select = document.getElementById(selectId);
    if (!select) return [];
    return Array.from(select.selectedOptions).map(opt => opt.value).filter(v => v && v !== '');
}

function limparFiltros() {
    const selects = ['selFiliais', 'selMarcas', 'selGrupos'];
    selects.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.selectedIndex = -1;
    });
    document.getElementById('selItens').innerHTML = '<option value="">Selecione após busca</option>';
    document.getElementById('buscaItem').value = '';
    consultarInventario();
}

// ==========================================================================
// CONSULTA PRINCIPAL
// ==========================================================================
async function consultarInventario() {
    const loading = document.getElementById('loading');
    const tabelaDiv = document.getElementById('tabelaResultados');
    const resumoDiv = document.getElementById('resumoContainer');
    
    loading.style.display = 'block';
    tabelaDiv.style.display = 'none';
    resumoDiv.style.display = 'none';
    
    const payload = {
        filiais: getSelectedValues('selFiliais'),
        marcas: getSelectedValues('selMarcas'),
        grupos: getSelectedValues('selGrupos'),
        itens: getSelectedValues('selItens')
    };
    
    try {
        const result = await apiFetch('v1/inventario/consultar', 'POST', payload);
        
        if (result.error) throw new Error(result.error);
        
        const dados = result.data || [];
        renderizarTabela(dados);
        
        // Resumo
        const totalItens = new Set(dados.map(i => i.iditem)).size;
        const totalLotes = dados.length;
        const saldoTotal = dados.reduce((sum, i) => sum + (parseFloat(i.quant_lote) || 0), 0);
        const lotesVencendo = dados.filter(i => i.status_validade === 'Ruim').length;
        
        document.getElementById('totalItens').innerText = totalItens;
        document.getElementById('totalLotes').innerText = totalLotes;
        document.getElementById('saldoTotal').innerText = saldoTotal.toLocaleString('pt-BR');
        document.getElementById('lotesVencendo').innerText = lotesVencendo;
        
        loading.style.display = 'none';
        tabelaDiv.style.display = 'block';
        resumoDiv.style.display = dados.length > 0 ? 'grid' : 'none';
        
        if (dados.length === 0) {
            showToast('Nenhum registro encontrado', 'info');
        }
    } catch (e) {
        console.error('Erro na consulta:', e);
        loading.style.display = 'none';
        showToast('Erro ao consultar inventário: ' + e.message, 'error');
    }
}

// ==========================================================================
// RENDERIZAÇÃO DA TABELA
// ==========================================================================
function renderizarTabela(dados) {
    const tbody = document.getElementById('tbodyEstoque');
    const totalRegistros = document.getElementById('totalRegistros');
    
    if (!dados || dados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">Nenhum registro encontrado</td></tr>';
        if (totalRegistros) totalRegistros.innerText = 'Total: 0 registros';
        return;
    }
    
    tbody.innerHTML = dados.map(item => {
        let badgeClass = '';
        if (item.status_validade === 'Ótimo') badgeClass = 'badge-otimo';
        else if (item.status_validade === 'Regular') badgeClass = 'badge-regular';
        else if (item.status_validade === 'Ruim') badgeClass = 'badge-ruim';
        
        const devolucaoHtml = item.status_devolucao_lote === 'D' 
            ? `<span class="badge-devolucao"><i class="fa-solid fa-rotate-left"></i> ${item.devolucao_lote}x</span>`
            : '<span style="color:#94a3b8;">Sem devolução</span>';
        
        const descricao = item.descricao && item.descricao.length > 35 
            ? item.descricao.substring(0, 35) + '...' 
            : (item.descricao || '-');
        
        const loteSeguro = (item.lote || '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
        
        return `
            <tr>
                <td title="${item.descricao || ''}">
                    <strong>${descricao}</strong><br>
                    <small class="text-muted">${item.grupo || ''} | ${item.marca || ''}</small>
                </td>
                <td>${item.referencia || '-'}<br><small class="text-muted">${item.idbarra || ''}</small></td>
                <td><code class="small">${item.lote || '-'}</code></td>
                <td>${item.validade || '-'}</td>
                <td><span class="badge-validade ${badgeClass}">${item.status_validade || '-'}</span></td>
                <td><strong class="text-primary">${parseFloat(item.quant_lote || 0).toLocaleString('pt-BR')}</strong><br><small>Total: ${parseFloat(item.saldo_total || 0).toLocaleString('pt-BR')}</small></td>
                <td>${item.unidade || 'UN'}</td>
                <td>${item.filial || '-'}</td>
                <td>${devolucaoHtml}</td>
                <td><button class="btn-detalhes" onclick="verDetalhesLote(${item.iditem}, '${loteSeguro}')"><i class="fa-solid fa-chart-line"></i> Histórico</button></td>
            </tr>
        `;
    }).join('');
    
    if (totalRegistros) {
        totalRegistros.innerText = `Total: ${dados.length} registros (${new Set(dados.map(i => i.iditem)).size} itens distintos)`;
    }
}

// ==========================================================================
// DETALHES DO LOTE
// ==========================================================================
async function verDetalhesLote(iditem, lote) {
    Swal.fire({
        title: 'Carregando...',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    
    try {
        const result = await apiFetch(`v1/inventario/detalhes-lote/${iditem}/${encodeURIComponent(lote)}`);
        if (result.error) throw new Error(result.error);
        
        const movimentos = result.data || [];
        
        let html = `
            <div style="max-height: 400px; overflow-y: auto;">
                <table class="table table-sm" style="font-size: 12px;">
                    <thead class="table-light">
                        <tr><th>Data/Hora</th><th>Tipo</th><th>Quantidade</th><th>Origem</th><th>Usuário</th></tr>
                    </thead>
                    <tbody>
        `;
        
        if (movimentos.length === 0) {
            html += '<tr><td colspan="5" class="text-center">Nenhum movimento encontrado</td></tr>';
        } else {
            movimentos.forEach(m => {
                const corTipo = m.tipo_movimento === 'ENTRADA' ? 'success' : 'danger';
                html += `
                    <tr>
                        <td>${m.data_movimento_formatada || '-'}</td>
                        <td><span class="badge bg-${corTipo}">${m.tipo_movimento || '-'}</span></td>
                        <td class="text-end">${Math.abs(m.quantidade || 0).toLocaleString('pt-BR')}</td>
                        <td>${m.descricao_origem || '-'}</td>
                        <td>${m.usuario || '-'}</td>
                    </tr>
                `;
            });
        }
        
        html += `</tbody></table></div>`;
        
        Swal.fire({
            title: `Histórico do Lote: ${lote}`,
            html: html,
            width: '700px',
            confirmButtonColor: '#274036'
        });
    } catch (e) {
        Swal.close();
        showToast('Erro ao carregar histórico', 'error');
    }
}

// ==========================================================================
// EXPORTAÇÃO
// ==========================================================================
async function exportarInventario() {
    Swal.fire({
        title: 'Exportando...',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    
    try {
        const params = new URLSearchParams();
        if (getSelectedValues('selFiliais').length) params.append('filiais', getSelectedValues('selFiliais').join(','));
        if (getSelectedValues('selMarcas').length) params.append('marcas', getSelectedValues('selMarcas').join(','));
        if (getSelectedValues('selGrupos').length) params.append('grupos', getSelectedValues('selGrupos').join(','));
        if (getSelectedValues('selItens').length) params.append('itens', getSelectedValues('selItens').join(','));
        
        const token = getAuthToken();
        const url = `/v1/inventario/exportar-excel?${params.toString()}`;
        
        // Abrir em nova aba com token no header
        const response = await fetch(url, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        if (response.status === 401) throw new Error('Não autorizado');
        
        const blob = await response.blob();
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `inventario_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.xlsx`;
        link.click();
        URL.revokeObjectURL(link.href);
        
        Swal.close();
        showToast('Exportação concluída!', 'success');
    } catch (e) {
        Swal.close();
        console.error('Erro ao exportar:', e);
        showToast('Erro ao exportar', 'error');
    }
}

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
window.onload = async function() {
    console.log('[Inventario] Inicializando...');
    
    // Verificar token
    const token = getAuthToken();
    if (!token) {
        console.warn('[Inventario] Token não encontrado');
        Swal.fire({
            title: 'Sessão Expirada',
            text: 'Faça login para acessar o módulo.',
            icon: 'warning',
            confirmButtonText: 'OK'
        }).then(() => {
            window.location.href = '/portal/login.php';
        });
        return;
    }
    
    // Carregar dados
    await carregarFiliais();
    await carregarMarcas();
    await carregarGrupos();
    
    // Eventos
    document.getElementById('btnPesquisar')?.addEventListener('click', consultarInventario);
    document.getElementById('btnLimpar')?.addEventListener('click', limparFiltros);
    document.getElementById('btnExportar')?.addEventListener('click', exportarInventario);
    document.getElementById('btnBuscarItem')?.addEventListener('click', buscarItens);
    document.getElementById('buscaItem')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') buscarItens();
    });
    
    // Consulta inicial
    consultarInventario();
    
    console.log('[Inventario] Pronto!');
};

// ==========================================================================
// EXPORTAÇÃO DE FUNÇÕES GLOBAIS
// ==========================================================================
window.verDetalhesLote = verDetalhesLote;