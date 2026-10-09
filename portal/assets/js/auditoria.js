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

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        })[char]);
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

    function timestampAuditoria(data) {
        const match = String(data || '').match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2}):(\d{2})$/);
        if (match) {
            return new Date(
                Number(match[3]), Number(match[2]) - 1, Number(match[1]),
                Number(match[4]), Number(match[5]), Number(match[6])
            ).getTime();
        }
        const timestamp = Date.parse(data);
        return Number.isNaN(timestamp) ? 0 : timestamp;
    }

    function getStatusBadge(status) {
        const badges = {
            'PENDENTE': '<span class="badge bg-warning"><i class="fa-solid fa-clock"></i> Pendente</span>',
            'SEPARACAO': '<span class="badge bg-info"><i class="fa-solid fa-box"></i> Separação</span>',
            'CONCLUIDO': '<span class="badge bg-success"><i class="fa-solid fa-check-circle"></i> Concluído</span>',
            'CARREGADO': '<span class="badge bg-primary"><i class="fa-solid fa-truck"></i> Carregado</span>'
        };
        return badges[status] || `<span class="badge bg-secondary">${escapeHtml(status || '---')}</span>`;
    }

    // ======================================================================
    // CHAMADAS À API (usando fetchWithAuth global)
    // ======================================================================

    const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    
    async function apiCall(endpoint, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = `${apiRoot}/auditoria${endpoint}${queryString ? (apiRoot.includes('?') ? '&' : '?') + queryString : ''}`;
        
        const resp = await fetchWithAuth(url);
        const data = await resp.json();
        if (!resp.ok || data?.error) {
            throw new Error(data?.error || `Falha na API de auditoria (HTTP ${resp.status})`);
        }
        return data;
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
        const inicio = document.getElementById('dataInicio')?.value || '';
        const fim = document.getElementById('dataFim')?.value || '';
        
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><i class="fa-solid fa-spinner fa-spin"></i> Carregando...</td></tr>';
        
        try {
            const dados = await apiCall('/carregamentos', { inicio, fim });
            if (!Array.isArray(dados)) {
                throw new Error('A API retornou uma lista de carregamentos inválida');
            }
            dadosCarregamento = dados;
            
            document.getElementById('countCarregamento').textContent = `(${dados.length})`;
            
            if (!dados.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-slate-400">Nenhum registro</td></tr>';
                return;
            }
            
            tbody.innerHTML = dados.map(item => `
              <tr>
        <td data-label="Emb."><strong>#${item.idembarque}</strong></td>
        <td data-label="Rota">${escapeHtml(item.rota || 'Interno')}</td>
        <td data-label="Placa">${escapeHtml(item.placa || '---')}</td>
        <td data-label="Motorista">${escapeHtml(item.motorista || '---')}</td>
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
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-danger">Erro ao carregar: ${escapeHtml(e.message)}</td></tr>`;
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
        const [timeline, itens, dossie] = await Promise.all([
            apiCall(`/timeline/${id}`),
            apiCall(`/itens/${id}/todos`),
            apiCall(`/dossie/${id}`)
        ]);
        
        // ========== FILTROS NO TOPO ==========
        window.dossieAuditoriaAtual = dossie;
        const totaisDossie = dossie.itens.reduce((totais, item) => {
            totais.previsto += Number(item.quantidade_prevista) || 0;
            totais.separado += Number(item.quantidade_separada) || 0;
            totais.carregado += Number(item.quantidade_carregada) || 0;
            if (Number(item.quantidade_carregada) > Number(item.quantidade_separada) + 0.0001) {
                totais.divergencias++;
            }
            return totais;
        }, { previsto: 0, separado: 0, carregado: 0, divergencias: 0 });
        let html = `
            <section class="mb-4 rounded-xl border border-slate-200 bg-slate-50 p-3 sm:p-4">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="m-0 text-base font-extrabold text-slate-800">Dossiê do embarque</h3>
                        <p class="m-0 text-xs text-slate-500">Previsto, separado, saldo carregado e evidências vinculadas às subidas.</p>
                    </div>
                    <button type="button" onclick="window.exportarDossieEmbarque()"
                            class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-bold text-slate-700 hover:bg-slate-100">
                        <i class="fa-solid fa-download"></i> Exportar dossiê CSV
                    </button>
                </div>
                <div class="mb-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <div class="rounded-lg bg-white p-2"><span class="block text-[10px] uppercase text-slate-500">Previsto</span><strong>${formatarQuantidade(totaisDossie.previsto)}</strong></div>
                    <div class="rounded-lg bg-white p-2"><span class="block text-[10px] uppercase text-slate-500">Separado</span><strong>${formatarQuantidade(totaisDossie.separado)}</strong></div>
                    <div class="rounded-lg bg-white p-2"><span class="block text-[10px] uppercase text-slate-500">Carregado atual</span><strong>${formatarQuantidade(totaisDossie.carregado)}</strong></div>
                    <div class="rounded-lg bg-white p-2"><span class="block text-[10px] uppercase text-slate-500">Acima do separado</span><strong>${totaisDossie.divergencias}</strong></div>
                </div>
                <div class="mb-3 flex flex-wrap gap-2 text-xs">
                    <button class="timeline-filter-btn active" data-dossie-filter="todos" onclick="window.filtrarDossie('todos')">Todos</button>
                    <button class="timeline-filter-btn" data-dossie-filter="divergencia" onclick="window.filtrarDossie('divergencia')">Acima do separado</button>
                    <button class="timeline-filter-btn" data-dossie-filter="parcial" onclick="window.filtrarDossie('parcial')">Parciais/pendentes</button>
                    <button class="timeline-filter-btn" data-dossie-filter="sem-foto" onclick="window.filtrarDossie('sem-foto')">Sem foto ativa</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[700px] text-left text-xs">
                        <thead><tr class="border-b border-slate-200 text-slate-500">
                            <th class="p-2">Item</th><th class="p-2">Previsto</th><th class="p-2">Separado</th>
                            <th class="p-2">Carregado</th><th class="p-2">Saldo</th><th class="p-2">Situação</th>
                        </tr></thead>
                        <tbody id="dossieItens">${dossie.itens.map(item => {
                            const previsto = Number(item.quantidade_prevista) || 0;
                            const separado = Number(item.quantidade_separada) || 0;
                            const carregado = Number(item.quantidade_carregada) || 0;
                            const saldo = Number(item.saldo_pendente) || 0;
                            const semFoto = item.sem_foto === true || item.sem_foto === 't';
                            const tags = [];
                            if (carregado > separado + 0.0001) tags.push('divergencia');
                            if (carregado > 0.0001 && carregado < separado - 0.0001) tags.push('parcial');
                            if (semFoto) tags.push('sem-foto');
                            const situacao = tags.length ? tags.map(tag => ({
                                divergencia: 'Acima do separado',
                                parcial: 'Parcial/pendente',
                                'sem-foto': 'Sem foto ativa'
                            })[tag]).join(' · ') : 'Conferido';
                            return `<tr class="dossie-item-row border-b border-slate-100" data-dossie-tags="${tags.join(' ')}">
                                <td class="p-2"><strong>${escapeHtml(item.produto || 'Item')}</strong><br><span class="text-slate-400">#${escapeHtml(item.iditem)} · ${escapeHtml(item.referencia || '---')}</span></td>
                                <td class="p-2">${formatarQuantidade(previsto)}</td>
                                <td class="p-2">${formatarQuantidade(separado)}</td>
                                <td class="p-2 font-bold">${formatarQuantidade(carregado)}</td>
                                <td class="p-2">${formatarQuantidade(saldo)}</td>
                                <td class="p-2">${escapeHtml(situacao)}</td>
                            </tr>`;
                        }).join('') || '<tr><td colspan="6" class="p-3 text-center text-slate-400">Nenhum item encontrado</td></tr>'}</tbody>
                    </table>
                </div>
                <h4 class="mb-2 mt-4 text-sm font-extrabold text-slate-800">Subidas, pedidos e evidências</h4>
                <div class="space-y-2">
                    ${dossie.subidas.map(subida => {
                        const fotos = Array.isArray(subida.fotos) && subida.fotos.length
                            ? subida.fotos
                            : (subida.foto ? [{ caminho_foto: subida.foto, data_hora: subida.data_hora, operador: subida.operador }] : []);
                        const allocations = Array.isArray(subida.alocacoes) ? subida.alocacoes : [];
                        const estado = Number(subida.quantidade_disponivel) <= 0.0001
                            ? 'Estornada'
                            : Number(subida.quantidade_estornada) > 0
                                ? 'Estorno parcial'
                                : 'Ativa';
                        const allocationsHtml = allocations.length
                            ? allocations.map(a => `Pedido #${escapeHtml(a.idpedido)} · item ${escapeHtml(a.iditempedido)}: ${formatarQuantidade(a.quantidade)} un`).join('<br>')
                            : '<span class="text-amber-700">Subida legada: alocação histórica por pedido não disponível</span>';
                        const fotosHtml = fotos.length
                            ? fotos.map((imagem, index) => {
                                const url = processarFotoUrl(imagem.caminho_foto);
                                return `<button type="button" onclick="window.abrirFotoDossie(${Number(subida.id_subida)}, ${index})" title="Foto ${index + 1} · ${escapeHtml(imagem.operador || subida.operador)} · ${escapeHtml(imagem.data_hora || subida.data_hora || '')}" class="h-14 w-14 overflow-hidden rounded border border-slate-200"><img src="${escapeHtml(url)}" alt="Foto ${index + 1} da subida" class="h-full w-full object-cover"></button>`;
                            }).join('')
                            : '<div class="flex h-14 w-14 items-center justify-center rounded bg-amber-50 text-center text-[10px] font-bold text-amber-700">SEM<br>FOTO</div>';
                        return `<article class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-xs">
                            <div class="flex flex-wrap gap-1">${fotosHtml}</div>
                            <div class="min-w-[180px] flex-1">
                                <strong>${escapeHtml(subida.produto)} · subida #${escapeHtml(subida.numero_subida)}</strong>
                                <div>Original: ${formatarQuantidade(subida.quantidade_original)} · saldo: ${formatarQuantidade(subida.quantidade_disponivel)} · ${escapeHtml(estado)}</div>
                                <div class="text-slate-500">${escapeHtml(subida.operador)} · ${escapeHtml(subida.data_hora || '')} · ${escapeHtml(subida.doca || 'sem doca')}</div>
                                <div class="mt-1 text-slate-600">${allocationsHtml}</div>
                            </div>
                        </article>`;
                    }).join('') || '<p class="text-xs text-slate-400">Sem registro individual de subida neste embarque.</p>'}
                </div>
                ${dossie.estornos.length ? `<h4 class="mb-2 mt-4 text-sm font-extrabold text-amber-800">Histórico de estornos</h4>
                    <div class="space-y-2">${dossie.estornos.map(estorno => {
                        const alocacoesEstornadas = Array.isArray(estorno.alocacoes_snapshot) ? estorno.alocacoes_snapshot : [];
                        const alocacoesTexto = alocacoesEstornadas.length
                            ? alocacoesEstornadas.map(a => `Pedido #${escapeHtml(a.idpedido)} · item ${escapeHtml(a.iditempedido)}: ${formatarQuantidade(a.quantidade)} un`).join('<br>')
                            : 'Alocação por pedido indisponível para este registro legado.';
                        return `<article class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs">
                            <strong>${escapeHtml(estorno.tipo)} · ${escapeHtml(estorno.produto || `Item #${estorno.iditem}`)} · ${formatarQuantidade(estorno.quantidade_estornada)} un</strong>
                            <div>${escapeHtml(estorno.operador)} · ${escapeHtml(estorno.criado_em || '')} · ${escapeHtml(estorno.motivo)}</div>
                            <div class="mt-1">${alocacoesTexto}</div>
                        </article>`;
                    }).join('')}</div>` : ''}
            </section>
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
            itens.sort((a, b) => timestampAuditoria(b.data_hora) - timestampAuditoria(a.data_hora));
            
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
                
                const fotoCarregamentoInformada = Boolean(item.foto_carregamento)
                    && !['.', '-', 'null', 'undefined'].includes(String(item.foto_carregamento).trim().toLowerCase());
                const temFotoCarregamento = item.tipo === 'carregamento' && fotoCarregamentoInformada;
                
                const codBarras = item.cod_barras || item.ean || '---';
                const fotoCatalogoIndex = window.timelineItens.indexOf(item);
                
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
                            <div style="width: 55px; height: 55px; cursor: pointer;" onclick="window.abrirFotoAuditoria(${fotoCatalogoIndex}, 'catalogo')">
                                <img src="${escapeHtml(fotoMaster)}" alt="Foto de catálogo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                                <div style="font-size: 0.55rem; text-align: center; color: #64748b; margin-top: 1px;">Catálogo</div>
                            </div>
                            
                            <!-- Foto Carregamento (só se existir) -->
                            ${temFotoCarregamento ? `
                            <div style="width: 55px; height: 55px; cursor: pointer; position: relative;" onclick="window.abrirFotoAuditoria(${fotoCatalogoIndex}, 'carregamento')">
                                <img src="${escapeHtml(fotoCarregamento)}" alt="Foto registrada na subida" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 2px solid #10b981; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                                <div style="font-size: 0.55rem; text-align: center; color: #10b981; margin-top: 1px; font-weight: 600;">
                                    <i class="fa-solid fa-camera"></i> Carga
                                </div>
                                <span style="position: absolute; top: -4px; right: -4px; background: #10b981; color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 0.5rem;">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                            </div>
                            ` : item.tipo === 'carregamento' ? `
                            <div class="flex items-center justify-center text-center text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md p-1" style="width: 76px; min-height: 55px;">
                                <span><i class="fa-solid fa-triangle-exclamation"></i><br>Sem foto</span>
                            </div>
                            ` : ''}
                        </div>
                        
                        <!-- Informações do produto -->
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem; line-height: 1.3; word-break: break-word;">
                                ${escapeHtml(item.produto)}
                            </div>
                            <div style="display: flex; flex-wrap: wrap; gap: 8px 15px; margin-top: 4px;">
                                <span style="font-size: 0.7rem; color: #64748b;">
                                    <i class="fa-solid fa-hashtag"></i> REF: ${escapeHtml(item.referencia || '---')}
                                </span>
                                <span style="font-size: 0.7rem; color: #64748b;">
                                    <i class="fa-solid fa-barcode"></i> ID: ${escapeHtml(item.iditem || '---')}
                                </span>
                                <span style="font-size: 0.7rem; color: #64748b;">
                                    <i class="fa-solid fa-upc-scan"></i> EAN: ${escapeHtml(codBarras)}
                                </span>
                            </div>
                            
                            <!-- 🔥 DETALHES DOS PEDIDOS (quando agrupado) -->
                            ${detalhesPedidos}
                            
                            <div style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px;">
                                <span class="badge ${badgeClass}" style="font-size: 0.7rem;">
                                    ${item.tipo === 'separacao' ? 'Separação' : 'Carregamento'}: ${qtdFormatada}
                                </span>
                                ${item.tipo === 'carregamento' && item.numero_subida ? `<span class="badge bg-primary" style="font-size: 0.7rem;">${escapeHtml(item.numero_subida)}ª subida</span>` : ''}
                                ${item.tipo === 'carregamento' && item.id_subida ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Registro #${escapeHtml(item.id_subida)}</span>` : ''}
                                ${item.tipo === 'carregamento' && item.doca ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">${escapeHtml(item.doca)}</span>` : ''}
                                ${item.tipo === 'carregamento' && item.quantidade_acumulada != null ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Acumulado: ${formatarQuantidade(item.quantidade_acumulada)}</span>` : ''}
                                ${item.tipo === 'carregamento' && item.quantidade_acumulada != null ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Acumulado: ${formatarQuantidade(item.quantidade_acumulada)}</span>` : ''}
                                ${qtdTotalFormatada ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Total: ${qtdTotalFormatada}</span>` : ''}
                                ${item.tipo === 'carregamento' && temFotoCarregamento ? '<span class="badge bg-success" style="font-size: 0.65rem;"><i class="fa-solid fa-camera"></i> Foto</span>' : ''}
                                ${temMultiplosPedidos ? `<span class="badge bg-info" style="font-size: 0.65rem;"><i class="fa-solid fa-receipt"></i> ${totalPedidos} pedidos</span>` : ''}
                            </div>
                        </div>
                        
                        <!-- Operador e data -->
                        <div style="text-align: right; min-width: 130px; flex-shrink: 0;">
                            <div style="font-weight: 600; color: #1e293b; font-size: 0.8rem;">
                                <i class="fa-solid fa-user"></i> ${escapeHtml(item.operador || 'Sistema')}
                            </div>
                            <div style="font-size: 0.7rem; color: #64748b;">
                                <i class="fa-regular fa-clock"></i> ${escapeHtml(item.data_hora || '---')}
                            </div>
                            <span class="badge bg-info" style="margin-top: 4px; font-size: 0.65rem;">${escapeHtml(item.status_desc)}</span>
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
                            <span style="font-weight: 600;">${escapeHtml(evento.status_desc)}</span>
                            <span style="color: #64748b;">${escapeHtml(evento.hora)}</span>
                            <span style="margin-left: auto;">${escapeHtml(evento.operador || 'Sistema')}</span>
                        </div>
                    `;
                });
                html += '</div>';
            }
        }
        
        content.innerHTML = html;
    } catch (e) {
        console.error('Erro timeline:', e);
        content.innerHTML = `
            <div class="mx-auto max-w-2xl rounded-xl border border-amber-200 bg-amber-50 p-4 text-center text-amber-900">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <strong class="ml-1">Não foi possível carregar o dossiê</strong>
                <p class="mb-0 mt-2 text-sm">${escapeHtml(e.message || 'Falha inesperada ao consultar a auditoria.')}</p>
            </div>`;
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
        itensFiltrados.sort((a, b) => timestampAuditoria(b.data_hora) - timestampAuditoria(a.data_hora));
        
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
            
            const fotoCarregamentoInformada = Boolean(item.foto_carregamento)
                && !['.', '-', 'null', 'undefined'].includes(String(item.foto_carregamento).trim().toLowerCase());
            const temFotoCarregamento = item.tipo === 'carregamento' && fotoCarregamentoInformada;
            
            const temMultiplosPedidos = item.pedidos && item.pedidos.length > 1;
            const totalPedidos = item.pedidos ? item.pedidos.length : 0;
            
            let detalhesPedidos = '';
            if (temMultiplosPedidos) {
                const pedidosStr = item.pedidos.map(p => 
                    `Pedido #${escapeHtml(p.idpedido)}: ${formatarQuantidade(p.qt_carregada)} un`
                ).join(' | ');
                detalhesPedidos = `
                    <div style="font-size:0.6rem; color:#64748b; margin-top:3px; background:#f1f5f9; padding:4px 8px; border-radius:4px;">
                        <i class="fa-solid fa-receipt"></i> ${pedidosStr}
                    </div>
                `;
            }
            
            const codBarras = item.cod_barras || item.ean || '---';
            const fotoCatalogoIndex = itens.indexOf(item);
            
            html += `
                <div class="timeline-item" data-tipo="${item.tipo}" style="display: flex; gap: 12px; padding: 12px; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 10px; background: white;">
                    <div style="width: 45px; height: 45px; background: ${bgColor}; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-size: 1.2rem; flex-shrink: 0;">
                        <i class="fa-solid fa-${icon}"></i>
                    </div>
                    <div style="display: flex; gap: 8px; flex-shrink: 0;">
                        <div style="width: 55px; height: 55px; cursor: pointer;" onclick="window.abrirFotoAuditoria(${fotoCatalogoIndex}, 'catalogo')">
                            <img src="${escapeHtml(fotoMaster)}" alt="Foto de catálogo" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 1px solid #e2e8f0; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                            <div style="font-size: 0.55rem; text-align: center; color: #64748b; margin-top: 1px;">Catálogo</div>
                        </div>
                        ${temFotoCarregamento ? `
                        <div style="width: 55px; height: 55px; cursor: pointer; position: relative;" onclick="window.abrirFotoAuditoria(${fotoCatalogoIndex}, 'carregamento')">
                            <img src="${escapeHtml(fotoCarregamento)}" alt="Foto registrada na subida" style="width: 100%; height: 100%; object-fit: contain; border-radius: 6px; border: 2px solid #10b981; background: #f8fafc;" onerror="this.src='${SEM_FOTO_URL}'">
                            <div style="font-size: 0.55rem; text-align: center; color: #10b981; margin-top: 1px; font-weight: 600;">
                                <i class="fa-solid fa-camera"></i> Carga
                            </div>
                            <span style="position: absolute; top: -4px; right: -4px; background: #10b981; color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 0.5rem;">
                                <i class="fa-solid fa-check"></i>
                            </span>
                        </div>
                        ` : item.tipo === 'carregamento' ? `
                        <div class="flex items-center justify-center text-center text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md p-1" style="width: 76px; min-height: 55px;">
                            <span><i class="fa-solid fa-triangle-exclamation"></i><br>Sem foto</span>
                        </div>
                        ` : ''}
                    </div>
                    <div style="flex: 1; min-width: 0;">
                        <div style="font-weight: 700; color: #1e293b; font-size: 0.9rem; line-height: 1.3; word-break: break-word;">${escapeHtml(item.produto)}</div>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px 15px; margin-top: 4px;">
                            <span style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-hashtag"></i> REF: ${escapeHtml(item.referencia || '---')}</span>
                            <span style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-barcode"></i> ID: ${escapeHtml(item.iditem || '---')}</span>
                            <span style="font-size: 0.7rem; color: #64748b;"><i class="fa-solid fa-upc-scan"></i> EAN: ${escapeHtml(codBarras)}</span>
                        </div>
                        ${detalhesPedidos}
                        <div style="margin-top: 6px; display: flex; flex-wrap: wrap; gap: 4px;">
                            <span class="badge ${badgeClass}" style="font-size: 0.7rem;">${item.tipo === 'separacao' ? 'Separação' : 'Carregamento'}: ${qtdFormatada}</span>
                            ${item.tipo === 'carregamento' && item.numero_subida ? `<span class="badge bg-primary" style="font-size: 0.7rem;">${escapeHtml(item.numero_subida)}ª subida</span>` : ''}
                            ${item.tipo === 'carregamento' && item.id_subida ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Registro #${escapeHtml(item.id_subida)}</span>` : ''}
                            ${item.tipo === 'carregamento' && item.doca ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">${escapeHtml(item.doca)}</span>` : ''}
                            ${item.tipo === 'carregamento' && item.quantidade_acumulada != null ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Acumulado: ${formatarQuantidade(item.quantidade_acumulada)}</span>` : ''}
                            ${qtdTotalFormatada ? `<span class="badge bg-light text-dark" style="font-size: 0.7rem;">Total: ${qtdTotalFormatada}</span>` : ''}
                            ${item.tipo === 'carregamento' && temFotoCarregamento ? '<span class="badge bg-success" style="font-size: 0.65rem;"><i class="fa-solid fa-camera"></i> Foto</span>' : ''}
                            ${temMultiplosPedidos ? `<span class="badge bg-info" style="font-size: 0.65rem;"><i class="fa-solid fa-receipt"></i> ${totalPedidos} pedidos</span>` : ''}
                        </div>
                    </div>
                    <div style="text-align: right; min-width: 130px; flex-shrink: 0;">
                        <div style="font-weight: 600; color: #1e293b; font-size: 0.8rem;"><i class="fa-solid fa-user"></i> ${escapeHtml(item.operador || 'Sistema')}</div>
                        <div style="font-size: 0.7rem; color: #64748b;"><i class="fa-regular fa-clock"></i> ${escapeHtml(item.data_hora || '---')}</div>
                        <span class="badge bg-info" style="margin-top: 4px; font-size: 0.65rem;">${escapeHtml(item.status_desc)}</span>
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
    if (!foto || ['.', '-', 'null', 'undefined'].includes(String(foto).trim().toLowerCase())) {
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
    if (/^https?:\/\//i.test(foto)) {
        return foto.replace(/ /g, '%20');
    }
    
    // Se for caminho do servidor de fotos (padrão Nutricional)
    const marker = 'Fotos para o Site/';
    const markerIndex = foto.toLowerCase().indexOf(marker.toLowerCase());
    if (markerIndex >= 0) {
        const imgPath = foto.substring(markerIndex + marker.length)
            .split('/').map(segment => encodeURIComponent(segment)).join('/');
        return imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath : SEM_FOTO_URL;
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
    const tituloEl = document.getElementById('fotoZoomTitulo');
    tituloEl.textContent = produto || 'Produto';
    document.getElementById('fotoZoomImagem').src = fotoUrl;
    abrirModalFoto();
}

function abrirFotoAuditoria(index, tipoFoto) {
    const item = window.timelineItens?.[index];
    if (!item) return;

    const foto = tipoFoto === 'carregamento'
        ? item.foto_carregamento
        : (item.foto_master || item.foto);
    const url = processarFotoUrl(foto);
    const etapa = tipoFoto === 'carregamento' ? 'Foto da subida' : 'Foto de catálogo';
    abrirFotoZoom(url, `${item.produto || 'Produto'} - ${etapa}`);
}

function abrirFotoDossie(idSubida, index = 0) {
    const subida = window.dossieAuditoriaAtual?.subidas?.find(item => Number(item.id_subida) === Number(idSubida));
    if (!subida) return;
    const fotos = Array.isArray(subida.fotos) && subida.fotos.length
        ? subida.fotos
        : (subida.foto ? [{ caminho_foto: subida.foto }] : []);
    const foto = fotos[index]?.caminho_foto;
    if (!foto) return;
    abrirFotoZoom(processarFotoUrl(foto), `${subida.produto || 'Produto'} - Subida #${subida.numero_subida} · Foto ${index + 1}`);
}

function filtrarDossie(filtro) {
    document.querySelectorAll('.dossie-item-row').forEach(row => {
        const tags = (row.dataset.dossieTags || '').split(' ').filter(Boolean);
        row.style.display = filtro === 'todos' || tags.includes(filtro) ? '' : 'none';
    });
    document.querySelectorAll('[data-dossie-filter]').forEach(button => {
        button.classList.toggle('active', button.dataset.dossieFilter === filtro);
    });
}

function exportarDossieEmbarque() {
    const dossie = window.dossieAuditoriaAtual;
    if (!dossie) return;

    const linhas = [[
        'tipo', 'embarque', 'item', 'subida', 'pedido', 'id_item_pedido',
        'previsto', 'separado', 'carregado', 'quantidade_evento', 'operador',
        'data_hora', 'status', 'motivo', 'foto'
    ]];
    const csvCell = value => {
        let text = String(value ?? '');
        if (/^[\t\r ]*[=+\-@]/.test(text)) text = `'${text}`;
        return `"${text.replace(/"/g, '""')}"`;
    };

    dossie.itens.forEach(item => linhas.push([
        'ITEM', dossie.idembarque, item.produto, '', '',
        item.iditem, item.quantidade_prevista, item.quantidade_separada,
        item.quantidade_carregada, '', '', '', item.status_conferencia, '', ''
    ]));
    dossie.subidas.forEach(subida => {
        const alocacoes = Array.isArray(subida.alocacoes) ? subida.alocacoes : [];
        const vinculos = alocacoes.length ? alocacoes : [{}];
        vinculos.forEach(alocacao => linhas.push([
            'SUBIDA', dossie.idembarque, subida.produto, subida.numero_subida,
            alocacao.idpedido || '', alocacao.iditempedido || '', '', '', '',
            alocacao.quantidade ?? subida.quantidade_original, subida.operador,
            subida.data_hora, Number(subida.quantidade_disponivel) > 0 ? 'ATIVA' : 'ESTORNADA',
            alocacoes.length ? '' : 'Legado sem alocação histórica',
            (Array.isArray(subida.fotos) ? subida.fotos.map(foto => foto.caminho_foto).join(' | ') : subida.foto) || 'SEM FOTO'
        ]));
    });
    dossie.estornos.forEach(estorno => {
        const alocacoes = Array.isArray(estorno.alocacoes_snapshot) ? estorno.alocacoes_snapshot : [];
        const vinculos = alocacoes.length ? alocacoes : [{}];
        vinculos.forEach(alocacao => linhas.push([
            'ESTORNO', dossie.idembarque, estorno.produto, estorno.numero_subida_original || '',
            alocacao.idpedido || '', alocacao.iditempedido || '', '', '', '',
            alocacao.quantidade ?? estorno.quantidade_estornada, estorno.operador,
            estorno.criado_em, estorno.tipo, estorno.motivo, estorno.caminho_foto_snapshot || ''
        ]));
    });

    const csv = '\uFEFF' + linhas.map(row => row.map(csvCell).join(';')).join('\r\n');
    const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    const link = document.createElement('a');
    link.href = url;
    link.download = `dossie-embarque-${dossie.idembarque}.csv`;
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
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
                (item.placa || '').toLowerCase().includes(search) ||
                (item.motorista || '').toLowerCase().includes(search) ||
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
            <td data-label="Rota">${escapeHtml(item.rota || 'Interno')}</td>
            <td data-label="Operador">${escapeHtml(item.operador_principal || '---')}</td>
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
        } else {
            tbody.innerHTML = filtrados.map(item => `
                <tr>
                    <td data-label="Emb."><strong>#${escapeHtml(item.idembarque)}</strong></td>
                    <td data-label="Rota">${escapeHtml(item.rota || 'Interno')}</td>
                    <td data-label="Placa">${escapeHtml(item.placa || '---')}</td>
                    <td data-label="Motorista">${escapeHtml(item.motorista || '---')}</td>
                    <td data-label="Peso">${formatarPeso(item.totalpesobruto)} kg</td>
                    <td data-label="Status">${getStatusBadge(item.status_logistico)}</td>
                    <td data-label="Ação">
                        <button class="px-3 py-1.5 border border-slate-300 text-slate-600 rounded-lg text-sm hover:bg-slate-700 hover:text-white hover:border-slate-700 transition-all" onclick="window.verTimeline(${Number(item.idembarque)})">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');
        }
    }

    async function exportarRelatorio() {
        const inicio = document.getElementById('dataInicio')?.value || '';
        const fim = document.getElementById('dataFim')?.value || '';
        const separator = apiRoot.includes('?') ? '&' : '?';
        const response = await fetchWithAuth(`${apiRoot}/auditoria/exportar${separator}inicio=${encodeURIComponent(inicio)}&fim=${encodeURIComponent(fim)}`);
        if (!response.ok) throw new Error('Falha ao exportar relatório');

        const blobUrl = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = blobUrl;
        link.download = `auditoria-logistica-${inicio}-${fim}.csv`;
        link.click();
        URL.revokeObjectURL(blobUrl);
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
    window.abrirFotoAuditoria = abrirFotoAuditoria;
    window.abrirFotoDossie = abrirFotoDossie;
    window.filtrarDossie = filtrarDossie;
    window.exportarDossieEmbarque = exportarDossieEmbarque;
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