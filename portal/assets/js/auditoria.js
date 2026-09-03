// ==========================================================================
// MÓDULO DE AUDITORIA LOGÍSTICA (IIFE - ESCOPO ISOLADO)
// ==========================================================================
(function() {
    'use strict';

    // ======================================================================
    // VARIÁVEIS LOCAIS
    // ======================================================================
    let modalTimeline;
    let modalFoto;
    let dadosSeparacao = [];
    let dadosCarregamento = [];

    // ======================================================================
    // FUNÇÕES AUXILIARES
    // ======================================================================
    
    // ======================================================================
// MODAIS NATIVOS (Substituindo Bootstrap)
// ======================================================================

function abrirModalTimeline() {
    document.getElementById('modalTimeline').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function fecharModalTimeline() {
    document.getElementById('modalTimeline').classList.add('hidden');
    document.body.style.overflow = '';
}

function abrirModalFoto() {
    document.getElementById('modalFoto').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function fecharModalFoto() {
    document.getElementById('modalFoto').classList.add('hidden');
    document.body.style.overflow = '';
}

// Substituir no código existente:
// Onde estiver "modalTimeline.show()" -> "abrirModalTimeline()"
// Onde estiver "modalFoto.show()" -> "abrirModalFoto()"

    function formatarPeso(peso) {
        return parseFloat(peso || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });
    }

    function formatarData(data) {
        if (!data) return '--:--';
        try {
            const d = new Date(data);
            return d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        } catch {
            return data.split(' ')[1] || data;
        }
    }

    function getStatusBadge(status) {
        const badges = {
            'PENDENTE': '<span class="badge bg-warning"><i class="fa-solid fa-clock"></i> Pendente</span>',
            'SEPARACAO': '<span class="badge bg-info"><i class="fa-solid fa-box"></i> Separação</span>',
            'CONCLUIDO': '<span class="badge bg-success"><i class="fa-solid fa-check-circle"></i> Concluído</span>',
            'CARREGADO': '<span class="badge bg-primary"><i class="fa-solid fa-truck"></i> Carregado</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${status || '---'}</span>`;
    }

    // ======================================================================
    // CHAMADAS À API (usando fetchWithAuth global)
    // ======================================================================
    
    async function apiCall(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = `/v1/auditoria${endpoint}${queryString ? '?' + queryString : ''}`;
        
        const resp = await fetchWithAuth(url);
        return resp.json();
    }

    // ======================================================================
    // CARREGAMENTO DE DADOS
    // ======================================================================

    async function carregarResumo() {
        const inicio = document.getElementById('dataInicio')?.value || '';
        const fim = document.getElementById('dataFim')?.value || '';
        
        try {
            const data = await apiCall('/resumo', { inicio, fim });
            
            document.getElementById('totalEmbarques').textContent = data.total_embarques || 0;
            document.getElementById('embarquesFinalizados').textContent = data.finalizados || 0;
            document.getElementById('embarquesAndamento').textContent = data.em_andamento || 0;
            
            const bipsTotal = (parseInt(data.total_bips_separacao) || 0) + (parseInt(data.total_bips_carregamento) || 0);
            document.getElementById('totalBips').textContent = bipsTotal;
            document.getElementById('totalPeso').textContent = formatarPeso(data.total_peso);
            
            // Atualizar footer
            document.getElementById('lista-auditoria-sep').innerHTML = 
                `<div class="mini-badge">${data.total_bips_separacao || 0} bips separação</div>` +
                `<div class="mini-badge">${data.finalizados || 0} finalizados</div>`;
            
            document.getElementById('lista-auditoria-car').innerHTML = 
                `<div class="mini-badge">${data.total_bips_carregamento || 0} bips carregamento</div>` +
                `<div class="mini-badge">${formatarPeso(data.total_peso)} kg</div>`;
                
        } catch (e) {
            console.error('Erro resumo:', e);
        }
    }

    async function carregarSeparacao() {
        const inicio = document.getElementById('dataInicio')?.value || '';
        const fim = document.getElementById('dataFim')?.value || '';
        const tbody = document.getElementById('tbodySeparacao');
        
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><i class="fa-solid fa-spinner fa-spin"></i> Carregando...</td></tr>';
        
        try {
            const dados = await apiCall('/historico', { inicio, fim });
            dadosSeparacao = dados;
            
            document.getElementById('countSeparacao').textContent = `(${dados.length})`;
            
            if (!dados.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-slate-400">Nenhum registro</td></tr>';
                return;
            }
            
            tbody.innerHTML = dados.map(item => `
                <tr>
        <td data-label="Emb."><strong>#${item.idembarque}</strong></td>
        <td data-label="Rota">${item.rota || 'Interno'}</td>
        <td data-label="Operador">${item.operador_principal || '---'}</td>
        <td data-label="Início/Fim"><small>${formatarData(item.inicio_op)} / ${formatarData(item.fim_op)}</small></td>
        <td data-label="Bips"><span class="badge bg-primary">${item.total_bips || 0}</span></td>
        <td data-label="Status">${getStatusBadge(item.status_atual)}</td>
        <td data-label="Ação">
            <button class="px-3 py-1.5 border border-slate-300 text-slate-600 rounded-lg text-sm hover:bg-slate-700 hover:text-white hover:border-slate-700 transition-all" onclick="window.verTimeline(${item.idembarque})">
                <i class="fa-solid fa-eye"></i>
            </button>
        </td>
    </tr>
            `).join('');
        } catch (e) {
            console.error('Erro separação:', e);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">Erro ao carregar</td></tr>';
        }
    }

    async function carregarCarregamento() {
        const tbody = document.getElementById('tbodyCarregamento');
        
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><i class="fa-solid fa-spinner fa-spin"></i> Carregando...</td></tr>';
        
        try {
            const resp = await fetchWithAuth('/v1/carregamento/embarques');
            const dados = await resp.json();
            dadosCarregamento = dados;
            
            document.getElementById('countCarregamento').textContent = `(${dados.length})`;
            
            if (!dados.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-slate-400">Nenhum registro</td></tr>';
                return;
            }
            
            const detalhes = await Promise.all(dados.map(async (emb) => {
                try {
                    const resp = await fetchWithAuth(`/v1/carregamento/resumo/${emb.idembarque}`);
                    const resumo = await resp.json();
                    return { ...emb, ...resumo };
                } catch { return emb; }
            }));
            
            dadosCarregamento = detalhes;
            
            tbody.innerHTML = detalhes.map(item => `
              <tr>
        <td data-label="Emb."><strong>#${item.idembarque}</strong></td>
        <td data-label="Rota">${item.rota || 'Interno'}</td>
        <td data-label="Placa">${item.placa || '---'}</td>
        <td data-label="Motorista">${item.motorista || '---'}</td>
        <td data-label="Peso">${formatarPeso(item.totalpesobruto)} kg</td>
        <td data-label="Status">${getStatusBadge(item.status_logistico)}</td>
        <td data-label="Ação">
            <button class="px-3 py-1.5 border border-slate-300 text-slate-600 rounded-lg text-sm hover:bg-slate-700 hover:text-white hover:border-slate-700 transition-all" onclick="window.verTimeline(${item.idembarque})">
                <i class="fa-solid fa-eye"></i>
            </button>
        </td>
    </tr>
            `).join('');
        } catch (e) {
            console.error('Erro carregamento:', e);
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-danger">Erro ao carregar</td></tr>';
        }
    }

    async function carregarRanking() {
        const inicio = document.getElementById('dataInicio')?.value || '';
        const fim = document.getElementById('dataFim')?.value || '';
        const tbody = document.getElementById('tbodyRanking');
        
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5"><i class="fa-solid fa-spinner fa-spin"></i> Carregando...</td></tr>';
        
        try {
            const dados = await apiCall('/ranking', { inicio, fim });
            
            if (!dados.length) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted">Nenhum registro</td></tr>';
                return;
            }
            
            tbody.innerHTML = dados.map((item, i) => {
                let rankStyle = '';
                if (i === 0) rankStyle = 'style="color: #f7be2f; font-weight: 800;"';
                else if (i === 1) rankStyle = 'style="color: #c0c0c0; font-weight: 800;"';
                else if (i === 2) rankStyle = 'style="color: #cd7f32; font-weight: 800;"';
                
                return `
                     <tr>
            <td data-label="#" ${rankStyle}>#${i + 1}</td>
            <td data-label="Operador"><strong>${item.operador}</strong></td>
            <td data-label="Emb.">${item.embarques_trabalhados}</td>
            <td data-label="Bips"><span class="badge bg-primary">${item.total_bips}</span></td>
            <td data-label="Total">${formatarPeso(item.total_separado)}</td>
            <td data-label="Última Ativ."><small>${item.ultima_atividade ? new Date(item.ultima_atividade).toLocaleString('pt-BR') : '---'}</small></td>
        </tr>
                `;
            }).join('');
        } catch (e) {
            console.error('Erro ranking:', e);
            tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-danger">Erro ao carregar</td></tr>';
        }
    }
async function verTimeline(id) {
    document.getElementById('modalEmbarqueId').textContent = `#${id}`;
    const content = document.getElementById('timelineContent');
    content.innerHTML = '<div class="text-center py-5"><i class="fa-solid fa-spinner fa-spin fa-2x"></i><p class="mt-3">Carregando timeline...</p></div>';
    
    abrirModalTimeline();
    
    try {
        const [timeline, itens] = await Promise.all([
            apiCall(`/timeline/${id}`),
            apiCall(`/itens/${id}/todos`)
        ]);
        
        // ========== FILTROS NO TOPO ==========
        let html = `
            <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <div style="padding: 8px 16px; background: #f8fafc; border-radius: 10px;">
                    <i class="fa-solid fa-box"></i> <strong>${itens.length}</strong> itens processados
                </div>
                <div style="display: flex; gap: 5px; margin-left: auto;">
                    <button class="timeline-filter-btn active" data-filter="todos" onclick="window.filtrarTimeline('todos')">
                        <i class="fa-solid fa-list"></i> Todos (${itens.length})
                    </button>
                    <button class="timeline-filter-btn" data-filter="separacao" onclick="window.filtrarTimeline('separacao')">
                        <i class="fa-solid fa-box"></i> Separação (<span id="countSep">${itens.filter(i => i.tipo === 'separacao').length}</span>)
                    </button>
                    <button class="timeline-filter-btn" data-filter="carregamento" onclick="window.filtrarTimeline('carregamento')">
                        <i class="fa-solid fa-truck"></i> Carregamento (<span id="countCar">${itens.filter(i => i.tipo === 'carregamento').length}</span>)
                    </button>
                </div>
            </div>
            <div id="timelineItemsContainer">
        `;
        
        // Armazenar itens globalmente para o filtro
        window.timelineItens = itens;
        window.timelineStatus = timeline;
        
        // URL da imagem padrão "Sem Foto"
        const SEM_FOTO_URL = '/portal/assets/img/no-image.png';
        
        if (!itens.length) {
            html += '<p class="text-center text-muted py-4">Nenhum item registrado</p>';
        } else {
            itens.sort((a, b) => {
                const dateA = new Date(a.data_hora.split('/').reverse().join('/'));
                const dateB = new Date(b.data_hora.split('/').reverse().join('/'));
                return dateB - dateA;
            });
            
            itens.forEach(item => {
                const bgColor = item.tipo === 'separacao' ? '#f59e0b' : '#10b981';
                const icon = item.tipo === 'separacao' ? 'box' : 'truck';
                const badgeClass = item.tipo === 'separacao' ? 'bg-warning' : 'bg-success';
                
                const qtdFormatada = formatarQuantidade(item.quantidade);
                const qtdTotalFormatada = item.quantidade_total ? formatarQuantidade(item.quantidade_total) : null;
                
                // ========== PROCESSAR FOTOS ==========
                let fotoMaster = processarFotoUrl(item.foto_master || item.foto);
                let fotoCarregamento = processarFotoUrl(item.foto_carregamento);
                
                if (!fotoMaster) {
                    fotoMaster = SEM_FOTO_URL;
                }
                
                const temFotoCarregamento = item.tipo === 'carregamento' && 
                                            fotoCarregamento && 
                                            fotoCarregamento !== fotoMaster;
                
                const produtoEscapado = item.produto.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                const codBarras = item.cod_barras || item.ean || '---';
                
                // 🔥 VERIFICAR SE TEM MÚLTIPLOS PEDIDOS (AGRUPADO)
                const temMultiplosPedidos = item.pedidos && item.pedidos.length > 1;
                const totalPedidos = item.pedidos ? item.pedidos.length : 0;
                
                // 🔥 GERAR DETALHES DOS PEDIDOS
                let detalhesPedidos = '';
                if (temMultiplosPedidos) {
                    const pedidosStr = item.pedidos.map(p => 
                        `Pedido #${p.idpedido}: ${formatarQuantidade(p.qt_carregada)} un`
                    ).join(' | ');
                    detalhesPedidos = `
                        <div style="font-size:0.6rem; color:#64748b; margin-top:3px; background:#f1f5f9; padding:4px 8px; border-radius:4px;">
                            <i class="fa-solid fa-receipt"></i> 
                            ${pedidosStr}
                        </div>
                    `;
                }
                
                html += `
                    <div class="timeline-item" data-tipo="${item.tipo}" style="display: flex; gap: 12px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; background: white;">
                        <!-- Ícone do tipo -->
                        <div style="width: 45px; height: 45px; background: ${bgColor}; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; flex-shrink: 0;">
                            <i class="fa-solid fa-${icon}"></i>
                        </div>
                        
                        <!-- Área de fotos -->
                        <div style="display: flex; gap: 8px; flex-shrink: 0;">
                            <!-- Foto Catálogo -->
                            <div style="width: 55px; height: 55px; cursor: pointer;" onclick="window.abrirFotoZoom('${fotoMaster}', '${produtoEscapado} - Catálogo')">
                                <img src="${fotoMaster}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                                <div style="font-size: 0.55rem; text-align: center; color: #64748b; margin-top: 1px;">Catálogo</div>
                            </div>
                            
                            <!-- Foto Carregamento (só se existir) -->
                            ${temFotoCarregamento ? `
                            <div style="width: 55px; height: 55px; cursor: pointer; position: relative;" onclick="window.abrirFotoZoom('${fotoCarregamento}', '${produtoEscapado} - Carregamento')">
                                <img src="${fotoCarregamento}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 2px solid #10b981; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                                <div style="font-size: 0.55rem; text-align: center; color: #10b981; margin-top: 1px; font-weight: 600;">
                                    <i class="fa-solid fa-camera"></i> Carga
                                </div>
                                <span style="position: absolute; top: -4px; right: -4px; background: #10b981; color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 0.5rem;">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <!-- Informações do produto -->
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem; line-height: 1.3; word-break: break-word;">
                                ${item.produto}
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px 15px; margin-top: 4px;">
                                <span style="font-size: 0.7rem; color: #64748b;">
                                    <i class="fa-solid fa-hashtag"></i> REF: ${item.referencia || '---'}
                                </span>
                                <span style="font-size: 0.7rem; color: #64748b;">
                                    <i class="fa-solid fa-barcode"></i> ID: ${item.iditem || '---'}
                                </span>
                                <span style="font-size: 0.7rem; color: #64748b;">
                                    <i class="fa-solid fa-upc-scan"></i> EAN: ${codBarras}
                                </span>
                            </div>
                            
                            <!-- 🔥 DETALHES DOS PEDIDOS (quando agrupado) -->
                            ${detalhesPedidos}
                            
                            <div style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px;">
                                <span class="badge ${badgeClass}" style="font-size: 0.7rem;">
                                    ${item.tipo === 'separacao' ? 'Separação' : 'Carregamento'}: ${qtdFormatada}
                                </span>
                                ${qtdTotalFormatada ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Total: ${qtdTotalFormatada}</span>` : ''}
                                ${item.tipo === 'carregamento' && temFotoCarregamento ? '<span class="badge bg-success" style="font-size: 0.65rem;"><i class="fa-solid fa-camera"></i> Foto</span>' : ''}
                                ${temMultiplosPedidos ? `<span class="badge bg-info" style="font-size: 0.65rem;"><i class="fa-solid fa-receipt"></i> ${totalPedidos} pedidos</span>` : ''}
                            </div>
                        </div>
                        
                        <!-- Operador e data -->
                        <div style="text-align: right; min-width: 130px; flex-shrink: 0;">
                            <div style="font-weight: 600; color: #1e293b; font-size: 0.8rem;">
                                <i class="fa-solid fa-user"></i> ${item.operador || 'Sistema'}
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b;">
                                <i class="fa-regular fa-clock"></i> ${item.data_hora || '---'}
                            </div>
                            <span class="badge bg-info" style="margin-top: 4px; font-size: 0.65rem;">${item.status_desc}</span>
                        </div>
                    </div>
                `;
            });
        }
        
        html += '</div>'; // Fecha timelineItemsContainer
        
        // Eventos de status
        if (timeline && timeline.length) {
            const eventosStatus = timeline.filter(t => t.tipo === 'status');
            if (eventosStatus.length) {
                html += '<div style="margin-top: 20px; padding: 15px; background: #f1f5f9; border-radius: 10px;"><h6 style="margin-bottom: 10px; font-size: 0.9rem;"><i class="fa-solid fa-arrow-rotate-right"></i> Mudanças de Status</h6>';
                eventosStatus.forEach(evento => {
                    html += `
                        <div style="display: flex; gap: 15px; padding: 6px 0; border-bottom: 1px dashed #cbd5e1; font-size: 0.8rem;">
                            <span style="font-weight: 600;">${evento.status_desc}</span>
                            <span style="color: #64748b;">${evento.hora}</span>
                            <span style="margin-left: auto;">${evento.operador || 'Sistema'}</span>
                        </div>
                    `;
                });
                html += '</div>';
            }
        }
        
        content.innerHTML = html;
    } catch (e) {
        console.error('Erro timeline:', e);
        content.innerHTML = '<p class="text-center text-danger py-5"><i class="fa-solid fa-triangle-exclamation"></i> Erro ao carregar timeline</p>';
    }
}
function filtrarTimeline(tipo) {
    const itens = window.timelineItens || [];
    const container = document.getElementById('timelineItemsContainer');
    
    if (!container) return;
    
    document.querySelectorAll('.timeline-filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === tipo) {
            btn.classList.add('active');
        }
    });
    
    const itensFiltrados = tipo === 'todos' 
        ? itens 
        : itens.filter(i => i.tipo === tipo);
    
    document.getElementById('countSep').textContent = itens.filter(i => i.tipo === 'separacao').length;
    document.getElementById('countCar').textContent = itens.filter(i => i.tipo === 'carregamento').length;
    
    const SEM_FOTO_URL = 'https://placehold.co/200x200/E2E8F0/64748B?text=S/F';
    let html = '';
    
    if (!itensFiltrados.length) {
        html = '<p class="text-center text-muted py-4">Nenhum item neste filtro</p>';
    } else {
        itensFiltrados.sort((a, b) => {
            const dateA = new Date(a.data_hora.split('/').reverse().join('/'));
            const dateB = new Date(b.data_hora.split('/').reverse().join('/'));
            return dateB - dateA;
        });
        
        itensFiltrados.forEach(item => {
            const bgColor = item.tipo === 'separacao' ? '#f59e0b' : '#10b981';
            const icon = item.tipo === 'separacao' ? 'box' : 'truck';
            const badgeClass = item.tipo === 'separacao' ? 'bg-warning' : 'bg-success';
            
            const qtdFormatada = formatarQuantidade(item.quantidade);
            const qtdTotalFormatada = item.quantidade_total ? formatarQuantidade(item.quantidade_total) : null;
            
            let fotoMaster = processarFotoUrl(item.foto_master || item.foto);
            let fotoCarregamento = processarFotoUrl(item.foto_carregamento);
            
            if (!fotoMaster) {
                fotoMaster = SEM_FOTO_URL;
            }
            
            const temFotoCarregamento = item.tipo === 'carregamento' && 
                                        fotoCarregamento && 
                                        fotoCarregamento !== fotoMaster;
            
            const temMultiplosPedidos = item.pedidos && item.pedidos.length > 1;
            const totalPedidos = item.pedidos ? item.pedidos.length : 0;
            
            let detalhesPedidos = '';
            if (temMultiplosPedidos) {
                const pedidosStr = item.pedidos.map(p => 
                    `Pedido #${p.idpedido}: ${formatarQuantidade(p.qt_carregada)} un`
                ).join(' | ');
                detalhesPedidos = `
                    <div style="font-size:0.6rem; color:#64748b; margin-top:3px; background:#f1f5f9; padding:4px 8px; border-radius:4px;">
                        <i class="fa-solid fa-receipt"></i> ${pedidosStr}
                    </div>
                `;
            }
            
            const produtoEscapado = item.produto.replace(/'/g, "\\'").replace(/"/g, '&quot;');
            const codBarras = item.cod_barras || item.ean || '---';
            
            html += `
                <div class="timeline-item" data-tipo="${item.tipo}" style="display: flex; gap: 12px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; background: white;">
                    <div style="width: 45px; height: 45px; background: ${bgColor}; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; flex-shrink: 0;">
                        <i class="fa-solid fa-${icon}"></i>
                    </div>
                    <div style="display: flex; gap: 8px; flex-shrink: 0;">
                        <div style="width: 55px; height: 55px; cursor: pointer;" onclick="window.abrirFotoZoom('${fotoMaster}', '${produtoEscapado} - Catálogo')">
                            <img src="${fotoMaster}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                            <div style="font-size: 0.55rem; text-align: center; color: #64748b; margin-top: 1px;">Catálogo</div>
                        </div>
                        ${temFotoCarregamento ? `
                        <div style="width: 55px; height: 55px; cursor: pointer; position: relative;" onclick="window.abrirFotoZoom('${fotoCarregamento}', '${produtoEscapado} - Carregamento')">
                            <img src="${fotoCarregamento}" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 2px solid #10b981; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                            <div style="font-size: 0.55rem; text-align: center; color: #10b981; margin-top: 1px; font-weight: 600;">
                                <i class="fa-solid fa-camera"></i> Carga
                            </div>
                            <span style="position: absolute; top: -4px; right: -4px; background: #10b981; color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 0.5rem;">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </div>
                        ` : ''}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem; line-height: 1.3; word-break: break-word;">${item.produto}</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px 15px; margin-top: 4px;">
                            <span style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-hashtag"></i> REF: ${item.referencia || '---'}</span>
                            <span style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-barcode"></i> ID: ${item.iditem || '---'}</span>
                            <span style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-upc-scan"></i> EAN: ${codBarras}</span>
                        </div>
                        ${detalhesPedidos}
                        <div style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px;">
                            <span class="badge ${badgeClass}" style="font-size: 0.7rem;">${item.tipo === 'separacao' ? 'Separação' : 'Carregamento'}: ${qtdFormatada}</span>
                            ${qtdTotalFormatada ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Total: ${qtdTotalFormatada}</span>` : ''}
                            ${item.tipo === 'carregamento' && temFotoCarregamento ? '<span class="badge bg-success" style="font-size: 0.65rem;"><i class="fa-solid fa-camera"></i> Foto</span>' : ''}
                            ${temMultiplosPedidos ? `<span class="badge bg-info" style="font-size: 0.65rem;"><i class="fa-solid fa-receipt"></i> ${totalPedidos} pedidos</span>` : ''}
                        </div>
                    </div>
                    <div style="text-align: right; min-width: 130px; flex-shrink: 0;">
                        <div style="font-weight: 600; color: #1e293b; font-size: 0.8rem;"><i class="fa-solid fa-user"></i> ${item.operador || 'Sistema'}</div>
                        <div style="font-size: 0.7rem; color: #64748b;"><i class="fa-regular fa-clock"></i> ${item.data_hora || '---'}</div>
                        <span class="badge bg-info" style="margin-top: 4px; font-size: 0.65rem;">${item.status_desc}</span>
                    </div>
                </div>
            `;
        });
    }
    
    container.innerHTML = html;
}

// ======================================================================
// FUNÇÕES AUXILIARES (ADICIONAR NO INÍCIO DO ARQUIVO)
// ======================================================================

function formatarQuantidade(qtd) {
    if (!qtd && qtd !== 0) return '0';
    const num = parseFloat(qtd);
    // Se for inteiro (ex: 70.000), retorna sem decimais
    if (Number.isInteger(num)) return num.toString();
    // Remove zeros desnecessários
    return num.toFixed(3).replace(/\.?0+$/, '');
}

function processarFotoUrl(foto) {
    // URL da imagem padrão "Sem Foto"
    const SEM_FOTO_URL = 'https://placehold.co/200x200/E2E8F0/64748B?text=S/F';
    
    // Se não tiver foto, retornar imagem padrão
    if (!foto || foto === 'null' || foto === 'undefined' || foto === '') {
        return SEM_FOTO_URL;
    }
    
    // Substituir barras invertidas por barras normais
    foto = foto.replace(/\\/g, '/');
    
    // ========== CORREÇÃO PRINCIPAL: Sempre remover /portal/assets/produtos ==========
    // Isso corrige URLs completas e relativas
    if (foto.includes('/portal/assets/produtos/')) {
        foto = foto.replace('/portal/assets/produtos', '');
    }
    
    // Se for URL completa (http/https)
    if (foto.startsWith('http')) {
        return foto;
    }
    
    // Se for caminho do servidor de fotos (padrão Nutricional)
    if (foto.includes('Fotos para o Site/')) {
        let imgPath = foto.split('Fotos para o Site/')[1];
        return 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20');
    }
    
    // Tratar /portal/assets/ (caso ainda tenha sem produtos)
    if (foto.includes('/portal/assets/')) {
        return 'https://api.nutricionalbr.com' + foto;
    }
    
    // Tratar uploads/carregamento
    if (foto.includes('/uploads/carregamento/')) {
        return 'https://api.nutricionalbr.com' + foto;
    }
    
    // Se for caminho relativo começando com /
    if (foto.startsWith('/')) {
        return 'https://api.nutricionalbr.com' + foto;
    }
    
    // Fallback
    return SEM_FOTO_URL;
}

function abrirFotoZoom(fotoUrl, produto) {
    document.getElementById('fotoZoomTitulo').textContent = produto || 'Produto';
    document.getElementById('fotoZoomImagem').src = fotoUrl;
    
    const tituloEl = document.getElementById('fotoZoomTitulo');
    const isCarregamento = produto.includes('Carregamento');
    
    if (isCarregamento) {
        tituloEl.innerHTML = `<i class="fa-solid fa-camera"></i> ${produto} <span class="badge bg-success ml-3">Foto Real</span>`;
    } else {
        tituloEl.innerHTML = `<i class="fa-solid fa-box"></i> ${produto} <span class="badge bg-info ml-3">Catálogo</span>`;
    }
    
    abrirModalFoto(); // <- ALTERADO
}
    // ======================================================================
    // TABS E FILTROS
    // ======================================================================

    function switchTab(tab) {
        document.getElementById('panelSeparacao').style.display = tab === 'separacao' ? 'block' : 'none';
        document.getElementById('panelCarregamento').style.display = tab === 'carregamento' ? 'block' : 'none';
        document.getElementById('panelRanking').style.display = tab === 'ranking' ? 'block' : 'none';
        
        document.getElementById('btnTabSeparacao').classList.toggle('active', tab === 'separacao');
        document.getElementById('btnTabCarregamento').classList.toggle('active', tab === 'carregamento');
        document.getElementById('btnTabRanking').classList.toggle('active', tab === 'ranking');
        
        if (tab === 'carregamento' && !dadosCarregamento.length) carregarCarregamento();
        if (tab === 'ranking') carregarRanking();
    }

    function filtrarDados() {
        carregarResumo();
        carregarSeparacao();
        
        if (document.getElementById('panelCarregamento').style.display === 'block') carregarCarregamento();
        if (document.getElementById('panelRanking').style.display === 'block') carregarRanking();
    }

    function filtrarTabela(tipo) {
        const search = document.getElementById(`search${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`)?.value.toLowerCase() || '';
        const status = document.getElementById(`statusFilter${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`)?.value || '';
        
        const dados = tipo === 'separacao' ? dadosSeparacao : dadosCarregamento;
        if (!dados?.length) return;
        
        const filtrados = dados.filter(item => {
            const matchSearch = !search || 
                String(item.idembarque).includes(search) ||
                (item.rota || '').toLowerCase().includes(search) ||
                (item.operador_principal || '').toLowerCase().includes(search);
            
            const itemStatus = item.status_atual || item.status_logistico;
            const matchStatus = !status || itemStatus === status;
            
            return matchSearch && matchStatus;
        });
        
        const tbody = document.getElementById(`tbody${tipo.charAt(0).toUpperCase() + tipo.slice(1)}`);
        
        if (!filtrados.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">Nenhum registro</td></tr>`;
            return;
        }
        
        if (tipo === 'separacao') {
            tbody.innerHTML = filtrados.map(item => `
                <tr>
            <td data-label="Emb."><strong>#${item.idembarque}</strong></td>
            <td data-label="Rota">${item.rota || 'Interno'}</td>
            <td data-label="Operador">${item.operador_principal || '---'}</td>
            <td data-label="Início/Fim"><small>${formatarData(item.inicio_op)} / ${formatarData(item.fim_op)}</small></td>
            <td data-label="Bips"><span class="badge bg-primary">${item.total_bips || 0}</span></td>
            <td data-label="Status">${getStatusBadge(item.status_atual)}</td>
            <td data-label="Ação">
                <button class="px-3 py-1.5 border border-slate-300 text-slate-600 rounded-lg text-sm hover:bg-slate-700 hover:text-white hover:border-slate-700 transition-all" onclick="window.verTimeline(${item.idembarque})">
                    <i class="fa-solid fa-eye"></i>
                </button>
            </td>
        </tr>
            `).join('');
        }
    }

    function exportarRelatorio() {
        const inicio = document.getElementById('dataInicio')?.value || '';
        const fim = document.getElementById('dataFim')?.value || '';
        window.open(`/v1/auditoria/exportar?inicio=${inicio}&fim=${fim}`, '_blank');
    }

    // ======================================================================
    // RELÓGIO
    // ======================================================================
   // ======================================================================
// RELÓGIO (Desktop + Mobile)
// ======================================================================
setInterval(() => {
    const agora = new Date();
    const horaFormatada = agora.toLocaleTimeString('pt-br');
    
    const relogioEl = document.getElementById('relogio');
    const relogioMobileEl = document.getElementById('relogioMobile');
    const dataEl = document.getElementById('data-topo');
    
    if (relogioEl) relogioEl.innerText = horaFormatada;
    if (relogioMobileEl) relogioMobileEl.innerText = horaFormatada;
    if (dataEl) dataEl.innerText = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
}, 1000);

    // ======================================================================
    // INICIALIZAÇÃO
    // ======================================================================
    window.addEventListener('DOMContentLoaded', () => {
       // modalTimeline = new bootstrap.Modal(document.getElementById('modalTimeline'));
      //  modalFoto = new bootstrap.Modal(document.getElementById('modalFoto'));
        
         //   window.modalFoto = modalFoto;

        carregarResumo();
        carregarSeparacao();
    });

    // ======================================================================
    // EXPORTAÇÃO GLOBAL
    // ======================================================================
    window.switchTab = switchTab;
    window.filtrarDados = filtrarDados;
    window.filtrarTabela = filtrarTabela;
    window.exportarRelatorio = exportarRelatorio;
    window.verTimeline = verTimeline;
    window.abrirFotoZoom = abrirFotoZoom; 
    window.filtrarTimeline = filtrarTimeline; 
    window.fecharModalTimeline = fecharModalTimeline;
window.fecharModalFoto = fecharModalFoto;

})();
// ======================================================================
// ALPINE.JS HANDLER - FORA da IIFE, no escopo global
// ======================================================================
document.addEventListener('alpine:init', () => {
    Alpine.data('auditoriaHandler', () => ({
        init() {
            // Inicialização se necessário
        }
    }));
});