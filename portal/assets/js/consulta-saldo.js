// ==========================================================================
// MÓDULO DE CONSULTA SALDO DISPONÍVEL
// ==========================================================================

let embarqueConsulta = null;
let dadosSaldos = [];

// ======================================================================
// RELÓGIO
// ======================================================================
setInterval(() => {
    const agora = new Date();
    const horaFormatada = agora.toLocaleTimeString('pt-br');
    const dataFormatada = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
    
    const relogio = document.getElementById('relogio');
    const relogioMobile = document.getElementById('relogioMobile');
    const dataTopo = document.getElementById('data-topo');
    
    if (relogio) relogio.innerText = horaFormatada;
    if (relogioMobile) relogioMobile.innerText = horaFormatada;
    if (dataTopo) dataTopo.innerText = dataFormatada;
}, 1000);

// ======================================================================
// INICIALIZAÇÃO
// ======================================================================
window.addEventListener('DOMContentLoaded', async () => {
    await carregarEmbarques();
});

// ======================================================================
// CARREGAR EMBARQUES
// ======================================================================
async function carregarEmbarques() {
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/embarques-ativos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        
        const lista = Array.isArray(data) ? data : [];
        
        const select = document.getElementById('selectEmbarque');
        if (!select) return;
        
        if (lista.length > 0) {
            select.innerHTML = '<option value="">Selecione um embarque...</option>' +
                lista.map(e => {
                    return `<option value="${e.idembarque}">
                        #${e.idembarque} - ${e.rota || e.observacao || 'Interno'} | ${e.entregador || '---'} | ${e.placa || 'S/P'}
                    </option>`;
                }).join('');
        } else {
            select.innerHTML = '<option value="">Nenhum embarque disponível</option>';
        }
    } catch (e) {
        const select = document.getElementById('selectEmbarque');
        if (select) select.innerHTML = '<option value="">Erro ao carregar</option>';
    }
}

// ======================================================================
// CARREGAR SALDOS
// ======================================================================
async function carregarSaldos() {
    const select = document.getElementById('selectEmbarque');
    const idembarque = select.value;
    const alerta =  0;
    
    if (!idembarque) {
        showToast('Selecione um embarque', 'warning');
        return;
    }
    
    embarqueConsulta = idembarque;
    
    // Atualizar card do embarque
    atualizarCardEmbarque(idembarque);
    
    try {
        showLoading('Consultando saldos...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/consulta/saldos/${idembarque}?alerta=${alerta}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        Swal.close();
        
        dadosSaldos = Array.isArray(data) ? data : [];
        
        if (dadosSaldos.length > 0) {
            renderizarTabela(dadosSaldos);
            atualizarCards(dadosSaldos);
            document.getElementById('cardsResumo').style.display = 'grid';
            document.getElementById('tabelaContainer').style.display = 'block';
        } else {
            document.getElementById('tbodySaldos').innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">Nenhum item encontrado</td></tr>';
            document.getElementById('cardsResumo').style.display = 'none';
        }
    } catch (e) {
        Swal.close();
        showError('Erro', 'Falha ao consultar saldos: ' + e.message);
    }
}

// ======================================================================
// ATUALIZAR CARD DO EMBARQUE
// ======================================================================
async function atualizarCardEmbarque(idembarque) {
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/embarques-ativos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const embarques = await resp.json();
        const lista = Array.isArray(embarques) ? embarques : [];
        const embarque = lista.find(e => e.idembarque == idembarque);
        
        if (embarque) {
            document.getElementById('infoEmbarque').textContent = '#' + embarque.idembarque;
            document.getElementById('infoRota').textContent = embarque.rota || embarque.observacao || '---';
            document.getElementById('infoEntregador').textContent = embarque.entregador || '---';
            document.getElementById('infoPlaca').textContent = embarque.placa || '---';
            document.getElementById('infoTransportadora').textContent = embarque.fantasia_transportadora || '---';
            document.getElementById('infoPesoBruto').textContent = (parseFloat(embarque.totalpesobruto) || 0).toLocaleString('pt-BR') + ' kg';
            document.getElementById('infoValorTotal').textContent = 'R$ ' + (parseFloat(embarque.valortotal) || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2});
            document.getElementById('infoQtPedidos').textContent = embarque.qt_pedido || '0';
            document.getElementById('infoData').textContent = embarque.data ? new Date(embarque.data + 'T00:00:00').toLocaleDateString('pt-BR') : '---';
            
            document.getElementById('cardEmbarque').style.display = 'block';
        }
    } catch (e) {
        console.error('Erro ao carregar informações do embarque:', e);
    }
}

// ======================================================================
// RENDERIZAR TABELA
// ======================================================================
function renderizarTabela(dados) {
    const tbody = document.getElementById('tbodySaldos');
    
    if (!dados.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">Nenhum item encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = dados.map(item => {
        const estoque = formatarNumero(parseFloat(item.estoque) || 0);
        const qtdEmbarque = formatarNumero(parseFloat(item.qtd_embarque) || 0);
        const saldo = formatarNumero(estoque - qtdEmbarque);
        
        let statusClass, statusTexto;
        if (saldo >= 0) {
            statusClass = 'bg-emerald-100 text-emerald-700';
            statusTexto = 'OK';
        } else if (estoque === 0) {
            statusClass = 'bg-rose-100 text-rose-700';
            statusTexto = 'SEM ESTOQUE';
        } else {
            statusClass = 'bg-amber-100 text-amber-700';
            statusTexto = `FALTA ${Math.abs(saldo)}`;
        }
        
        return `
        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all ${saldo < 0 ? 'bg-red-50/30' : ''}">
            <td data-label="REF" class="px-4 py-3 font-mono text-sm font-bold text-[#375a4b]">${item.referencia || '---'}</td>
            <td data-label="Produto" class="px-4 py-3 font-semibold text-slate-700">${item.descricao}</td>
            <td data-label="Estoque" class="px-4 py-3 text-center font-bold text-slate-600">${estoque}</td>
            <td data-label="Qtd Embarque" class="px-4 py-3 text-center font-bold text-slate-600">${qtdEmbarque}</td>
            <td data-label="Saldo" class="px-4 py-3 text-center">
                <span class="font-black text-lg ${saldo >= 0 ? 'text-emerald-600' : 'text-rose-600'}">${saldo}</span>
            </td>
            <td data-label="Status" class="px-4 py-3 text-center">
                <span class="px-3 py-1 rounded-full text-[10px] font-bold ${statusClass}">${statusTexto}</span>
            </td>
            <td data-label="Ações" class="px-4 py-3 text-center">
                <button onclick="verPedidosItem('${item.iditem}', '${item.descricao.replace(/'/g, "\\'")}', '${item.referencia}')" 
                        class="px-3 py-1.5 bg-[#375a4b] text-white rounded-lg text-sm hover:bg-[#4a7a67] transition-all">
                    <i class="fa-solid fa-list-check mr-1"></i> Pedidos
                </button>
            </td>
        </tr>`;
    }).join('');
}

function formatarNumero(valor) {
    if (isNaN(valor)) return '0';
    const num = parseFloat(valor);
    if (Number.isInteger(num)) return num.toString();
    return parseFloat(num.toFixed(3)).toString();
}

// ======================================================================
// ATUALIZAR CARDS DE RESUMO
// ======================================================================
function atualizarCards(dados) {
    let ok = 0, alerta = 0, critico = 0;
    
    dados.forEach(item => {
        const saldo = (parseFloat(item.estoque) || 0) - (parseFloat(item.qtd_embarque) || 0);
        if (saldo >= 0) ok++;
        else if (item.estoque == 0) critico++;
        else alerta++;
    });
    
    document.getElementById('totalItens').textContent = dados.length;
    document.getElementById('itensOk').textContent = ok;
    document.getElementById('itensAlerta').textContent = alerta;
    document.getElementById('itensCritico').textContent = critico;
}

// ======================================================================
// VER PEDIDOS DO ITEM
// ======================================================================
async function verPedidosItem(iditem, descricao, referencia) {
    try {
        showLoading('Buscando pedidos...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/consulta/pedidos-item/${embarqueConsulta}/${iditem}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        Swal.close();
        
        if (!data || !data.length) {
            showToast('Nenhum pedido encontrado para este item', 'info');
            return;
        }
        
        const totalUnidades = data.reduce((s, p) => s + parseFloat(p.qt || 0), 0);
        
        document.getElementById('modalPedidoTitulo').innerHTML = 
            `<i class="fa-solid fa-box mr-2"></i>${referencia} - ${descricao}`;
        
        document.getElementById('modalPedidoConteudo').innerHTML = `
        <div class="space-y-4">
            <div class="bg-gradient-to-r from-[#375a4b] to-[#4a7a67] p-5 rounded-2xl text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-white/70 text-xs uppercase tracking-wider font-bold">Total no Embarque</p>
                        <h3 class="text-3xl font-black">${formatarNumero(totalUnidades)} un</h3>
                    </div>
                    <div class="text-right">
                        <p class="text-white/70 text-xs uppercase tracking-wider font-bold">Pedidos</p>
                        <h3 class="text-3xl font-black">${data.length}</h3>
                    </div>
                </div>
            </div>
            <div class="space-y-3">
                ${data.map((pedido, index) => {
                    const qt = formatarNumero(parseFloat(pedido.qt || 0));
                    return `
                    <div class="bg-white border border-slate-200 rounded-2xl p-5 hover:border-[#375a4b] hover:shadow-md transition-all">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="w-8 h-8 bg-[#375a4b]/10 text-[#375a4b] rounded-lg flex items-center justify-center font-bold text-sm">#${index + 1}</span>
                                    <h5 class="font-bold text-slate-800 text-lg">Pedido #${pedido.idpedido}</h5>
                                </div>
                                <p class="text-sm text-slate-500 ml-10">
                                    <i class="fa-solid fa-store mr-1"></i> ${pedido.cliente || 'Cliente não informado'}
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="px-4 py-2 bg-[#375a4b]/10 text-[#375a4b] rounded-xl text-lg font-black">${qt} un</span>
                            </div>
                        </div>
                        <div class="flex gap-3 ml-10">
                            <button onclick="editarQtdPedido(${pedido.idpedido}, ${pedido.iditempedido}, ${pedido.qt}, '${descricao.replace(/'/g, "\\'")}')" 
                                    class="flex-1 px-4 py-2.5 bg-amber-50 text-amber-700 rounded-xl text-sm font-bold hover:bg-amber-100 border border-amber-200 transition-all flex items-center justify-center gap-2">
                                <i class="fa-solid fa-pen"></i> Editar Quantidade
                            </button>
                            <button onclick="removerItemPedido(${pedido.idpedido}, ${pedido.iditempedido}, '${descricao.replace(/'/g, "\\'")}')" 
                                    class="px-4 py-2.5 bg-rose-50 text-rose-700 rounded-xl text-sm font-bold hover:bg-rose-100 border border-rose-200 transition-all flex items-center gap-2">
                                <i class="fa-solid fa-trash"></i> Remover
                            </button>
                        </div>
                    </div>`;
                }).join('')}
            </div>
        </div>`;
        
        abrirModalPedido();
    } catch (e) {
        Swal.close();
        showError('Erro', 'Falha ao buscar pedidos: ' + e.message);
    }
}

// ======================================================================
// EDITAR QUANTIDADE DO PEDIDO
// ======================================================================
async function editarQtdPedido(idpedido, iditempedido, qtdAtual, descricao) {
    const { value: novaQtd } = await Swal.fire({
        title: 'Editar Quantidade',
        html: `<p class="text-sm text-slate-600 mb-3">${descricao}</p><p class="text-xs text-slate-400">Quantidade atual: <b>${qtdAtual}</b></p>`,
        input: 'number',
        inputValue: qtdAtual,
        inputAttributes: { min: 0, step: 'any' },
        showCancelButton: true,
        confirmButtonColor: '#7c3aed',
        confirmButtonText: 'Salvar',
        cancelButtonText: 'Cancelar',
        position: 'top'
    });
    
    if (!novaQtd && novaQtd !== 0) return;
    
    try {
        showLoading('Atualizando...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/consulta/editar-pedido`, {
            method: 'POST',
            headers: { 
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                idpedido,
                iditempedido,
                idembarque: embarqueConsulta,
                nova_qtd: parseFloat(novaQtd),
                idusuario: getUserId()
            })
        });
        const result = await resp.json();
        Swal.close();
        
        if (result.success) {
            showToast('Quantidade atualizada!');
            verPedidosItem(result.iditem, descricao, '');
            carregarSaldos();
        } else {
            showError('Erro', result.error || 'Falha ao atualizar');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

// ======================================================================
// REMOVER ITEM DO PEDIDO
// ======================================================================
async function removerItemPedido(idpedido, iditempedido, descricao) {
    const result = await Swal.fire({
        title: 'Remover Item?',
        html: `<p class="text-sm">Deseja remover <b>${descricao}</b> do pedido #${idpedido}?</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, Remover',
        cancelButtonText: 'Cancelar',
        position: 'top'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        showLoading('Removendo...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/consulta/remover-item-pedido`, {
            method: 'POST',
            headers: { 
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                idpedido,
                iditempedido,
                idembarque: embarqueConsulta,
                idusuario: getUserId()
            })
        });
        const data = await resp.json();
        Swal.close();
        
        if (data.success) {
            showToast('Item removido do pedido!');
            carregarSaldos();
            fecharModalPedido();
        } else {
            showError('Erro', data.error || 'Falha ao remover');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

// ======================================================================
// MODAL
// ======================================================================
function abrirModalPedido() {
    document.getElementById('modalPedido').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function fecharModalPedido() {
    document.getElementById('modalPedido').classList.add('hidden');
    document.body.style.overflow = '';
}

// ======================================================================
// EXPORTAR PDF (SUBSTITUI O CSV)
// ======================================================================
// ======================================================================
// EXPORTAR PDF COM PEDIDOS
// ======================================================================
async function exportarPDF() {
    if (!dadosSaldos.length) {
        showToast('Nenhum dado para exportar', 'warning');
        return;
    }
    
    showLoading('Buscando dados dos pedidos...');
    
    try {
        const token = localStorage.getItem('authToken');
        
        // Buscar dados do embarque
        const respEmbarque = await fetch(`${API_URL}/embarques-ativos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const embarques = await respEmbarque.json();
        const embarque = (Array.isArray(embarques) ? embarques : []).find(e => e.idembarque == embarqueConsulta);
        
        // Buscar pedidos de CADA item
        const itensComPedidos = await Promise.all(
            dadosSaldos.map(async (item) => {
                const resp = await fetch(`${API_URL}/consulta/pedidos-item/${embarqueConsulta}/${item.iditem}`, {
                    headers: { 'Authorization': 'Bearer ' + token }
                });
                const pedidos = await resp.json();
                return { ...item, pedidos: Array.isArray(pedidos) ? pedidos : [] };
            })
        );
        
        Swal.close();
        
        // Gerar HTML
        let html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório | Embarque #${embarqueConsulta}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Helvetica', sans-serif; padding: 25px; color: #1e293b; font-size: 10px; }
                
                .header { background: #375a4b; color: white; padding: 18px 20px; border-radius: 10px; margin-bottom: 18px; }
                .header h1 { font-size: 18px; margin-bottom: 3px; }
                .header p { font-size: 10px; opacity: 0.8; }
                
                .info-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 18px; }
                .info-card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; }
                .info-label { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: bold; }
                .info-value { font-size: 13px; font-weight: bold; color: #1e293b; margin-top: 3px; }
                
                .section-title { font-size: 14px; font-weight: bold; color: #375a4b; margin: 20px 0 10px 0; 
                                padding-bottom: 5px; border-bottom: 2px solid #375a4b; }
                .section-title.danger { color: #ef4444; border-color: #ef4444; }
                
                .item-block { margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; }
                .item-header { background: #f8fafc; padding: 10px 12px; border-bottom: 1px solid #e2e8f0; }
                .item-header .ref { font-size: 9px; color: #64748b; }
                .item-header .nome { font-size: 12px; font-weight: bold; }
                .item-header .numeros { display: flex; gap: 15px; margin-top: 5px; font-size: 10px; }
                .item-header .numeros span { color: #64748b; }
                .item-header .numeros .negativo { color: #ef4444; font-weight: bold; }
                
                .pedido-lista { padding: 8px 12px; }
                .pedido-row { padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 10px; }
                .pedido-row:last-child { border-bottom: none; }
                .pedido-row .pedido-id { font-weight: bold; color: #375a4b; }
                .pedido-row .pedido-cliente { color: #64748b; margin-left: 8px; }
                .pedido-row .pedido-qt { float: right; font-weight: bold; background: #f1f5f9; 
                                        padding: 2px 8px; border-radius: 4px; }
                
                .sem-pedidos { color: #94a3b8; font-style: italic; padding: 8px 12px; font-size: 10px; }
                
                .footer { margin-top: 25px; padding-top: 12px; border-top: 1px solid #e2e8f0; 
                         font-size: 9px; color: #94a3b8; text-align: center; }
                
                .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 8px; font-weight: bold; }
                .badge-danger { background: #fee2e2; color: #ef4444; }
                .badge-warning { background: #fef3c7; color: #d97706; }
            </style>
        </head>
        <body>
            <!-- Cabeçalho -->
            <div class="header">
                <h1>📋 Relatório de Saldo Disponível</h1>
                <p>Gerado em: ${new Date().toLocaleString('pt-BR')} | Usuário: ${getUserNome() || 'Sistema'}</p>
            </div>
            
            ${embarque ? `
            <h3 style="font-size:13px; color:#375a4b; margin-bottom:8px;">🚛 Dados do Embarque</h3>
            <div class="info-grid">
                <div class="info-card"><div class="info-label">Embarque</div><div class="info-value">#${embarque.idembarque}</div></div>
                <div class="info-card"><div class="info-label">Rota</div><div class="info-value">${embarque.rota || embarque.observacao || '---'}</div></div>
                <div class="info-card"><div class="info-label">Entregador</div><div class="info-value">${embarque.entregador || '---'}</div></div>
                <div class="info-card"><div class="info-label">Placa</div><div class="info-value">${embarque.placa || '---'}</div></div>
                <div class="info-card"><div class="info-label">Transportadora</div><div class="info-value">${embarque.fantasia_transportadora || '---'}</div></div>
                <div class="info-card"><div class="info-label">Peso Total</div><div class="info-value">${parseFloat(embarque.totalpesobruto || 0).toLocaleString('pt-BR')} kg</div></div>
                <div class="info-card"><div class="info-label">Valor Total</div><div class="info-value">R$ ${parseFloat(embarque.valortotal || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div></div>
                <div class="info-card"><div class="info-label">Qt Pedidos</div><div class="info-value">${embarque.qt_pedido || 0}</div></div>
                <div class="info-card"><div class="info-label">Data</div><div class="info-value">${embarque.data ? new Date(embarque.data + 'T00:00:00').toLocaleDateString('pt-BR') : '---'}</div></div>
            </div>
            ` : ''}
            
            <!-- ITENS COM PEDIDOS -->
            <div class="section-title danger">⚠️ Itens com Estoque Insuficiente (${dadosSaldos.length})</div>
            
            ${itensComPedidos.map(item => {
                const estoque = formatarNumero(parseFloat(item.estoque) || 0);
                const qtd = formatarNumero(parseFloat(item.qtd_embarque) || 0);
                const saldo = formatarNumero(estoque - qtd);
                const temPedidos = item.pedidos && item.pedidos.length > 0;
                
                return `
                <div class="item-block">
                    <div class="item-header">
                        <div class="ref">REF: ${item.referencia || '---'} | ID: ${item.iditem}</div>
                        <div class="nome">${item.descricao}</div>
                        <div class="numeros">
                            <span>📦 Estoque: <b>${estoque}</b></span>
                            <span>🚛 No embarque: <b>${qtd}</b></span>
                            <span>Saldo: <b class="negativo">${saldo}</b></span>
                        </div>
                    </div>
                    ${temPedidos ? `
                    <div style="background:#fafafa; padding:4px 12px; font-size:9px; color:#64748b; font-weight:bold;">
                        📋 PEDIDOS QUE CONTÉM ESTE ITEM (${item.pedidos.length}):
                    </div>
                    <div class="pedido-lista">
                        ${item.pedidos.map(p => `
                        <div class="pedido-row">
                            <span class="pedido-id">📄 Pedido #${p.idpedido}</span>
                            <span class="pedido-cliente">🏪 ${p.cliente || 'Cliente não informado'}</span>
                            <span class="pedido-qt">${formatarNumero(parseFloat(p.qt || 0))} un</span>
                        </div>
                        `).join('')}
                    </div>
                    ` : `
                    <div class="sem-pedidos">Nenhum pedido encontrado para este item no embarque</div>
                    `}
                </div>`;
            }).join('')}
            
            <div class="footer">
                © ${new Date().getFullYear()} Nutricional Distribuidora | By Alan Marcon | Relatório gerado automaticamente
            </div>
        </body>
        </html>`;
        
        // Abrir em nova janela
        const win = window.open('', '_blank', 'width=1000,height=800');
        win.document.write(html);
        win.document.close();
        
        setTimeout(() => win.print(), 600);
        
    } catch (e) {
        Swal.close();
        showError('Erro', 'Falha ao gerar PDF: ' + e.message);
    }
}

// ======================================================================
// FUNÇÕES AUXILIARES
// ======================================================================
function showLoading(title) {
    Swal.fire({ title, allowOutsideClick: false, didOpen: () => Swal.showLoading() });
}
function showToast(message, icon = 'success') {
    Swal.fire({ toast: true, position: 'top-end', icon, title: message, showConfirmButton: false, timer: 3000 });
}
function showError(title, text) {
    Swal.fire({ icon: 'error', title, text, position: 'top', confirmButtonColor: '#ef4444' });
}