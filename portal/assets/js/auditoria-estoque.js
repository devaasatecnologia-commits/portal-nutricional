// portal/assets/js/auditoria-estoque.js

// ==========================================================================
// VARIÁVEIS GLOBAIS
// ==========================================================================
let estoqueAtual = null;
let pedidoAtual = null;
let produtosAtuais = [];
let produtoSelecionado = null;
let moverDestinoId = null;
let moverDestinoNome = null;

// ==========================================================================
// FUNÇÕES AUXILIARES
// ==========================================================================
async function apiFetch(endpoint, method = 'GET', body = null) {
    const token = localStorage.getItem('authToken') || localStorage.getItem('token');
    const url = endpoint.startsWith('http') ? endpoint : `/${endpoint}`;
    
    const options = {
        method: method,
        headers: { 'Content-Type': 'application/json' }
    };
    
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }
    
    if (body && (method === 'POST' || method === 'PUT' || method === 'DELETE')) {
        options.body = JSON.stringify(body);
    }
    
    const response = await fetch(url, options);
    
    if (response.status === 401) {
        window.location.href = '/portal/login.php?redirect=auditoria-estoque';
        throw new Error('Sessão expirada');
    }
    
    return response.json();
}

function getUserId() {
    const el = document.getElementById('user_id');
    if (el && el.value && el.value !== '0') {
        return parseInt(el.value);
    }
    return 0;
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
    }
}

function formatarPeso(peso) {
    if (!peso) return '0 kg';
    return parseFloat(peso).toLocaleString('pt-br', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
}

function formatarQuantidade(qt) {
    if (!qt) return '0';
    return parseFloat(qt).toLocaleString('pt-br', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function getImageUrl(foto) {
    if (!foto) return 'https://placehold.co/100x100?text=S/F';
    if (foto.startsWith('http://') || foto.startsWith('https://')) return foto;
    
    let imgPath = foto;
    if (imgPath.includes('Fotos para o Site\\')) {
        imgPath = imgPath.split('Fotos para o Site\\')[1];
    }
    imgPath = imgPath.replace(/\\/g, '/');
    
    if (imgPath) {
        return 'https://acesso.nutricionalbr.com:2053/fotos/' + encodeURIComponent(imgPath).replace(/%2F/g, '/');
    }
    return 'https://placehold.co/100x100?text=S/F';
}

// ==========================================================================
// SELECIONAR ESTOQUE
// ==========================================================================
async function selecionarEstoque(id, nome, cor) {
    // Marca o card selecionado
    document.querySelectorAll('.estoque-card').forEach(card => {
        card.classList.remove('active', 'border-green-500', 'border-blue-500', 'border-amber-500');
        card.style.borderColor = 'transparent';
    });
    
    const cards = document.querySelectorAll('.estoque-card');
    const index = id === 10117 ? 0 : (id === 16595 ? 1 : 2);
    if (cards[index]) {
        cards[index].classList.add('active');
        cards[index].style.borderColor = cor;
    }
    
    Swal.fire({
        title: 'Carregando estoque...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        position: 'top'
    });
    
    try {
        const response = await apiFetch(`v1/auditoria-estoque/estoque-ativo/${id}?idusuario=${getUserId()}`);
        Swal.close();
        
        if (response.error) {
            showToast(response.error, 'error');
            return;
        }
        
        estoqueAtual = response.estoque;
        pedidoAtual = response.pedido;
        produtosAtuais = response.produtos;
        
        // Atualiza UI
        document.getElementById('infoEstoque').style.display = 'block';
        document.getElementById('estoqueNome').innerHTML = `${response.estoque.nome} <span style="color:${response.estoque.cor}">●</span>`;
        document.getElementById('loteNumero').innerText = response.pedido.idpedido;
        document.getElementById('totalProdutos').innerText = response.produtos.length;
        
        document.getElementById('areaBusca').style.display = 'block';
        document.getElementById('areaConteudo').style.display = 'grid';
        
        // Renderiza produtos e movimentações
        renderizarProdutos(response.produtos);
        renderizarMovimentacoes(response.movimentacoes);
        
        // Atualiza badges nos cards
        atualizarBadges();
        
    } catch (e) {
        Swal.close();
        console.error('Erro ao carregar estoque:', e);
        showToast('Erro ao carregar estoque', 'error');
    }
}

// ==========================================================================
// ATUALIZAR BADGES DOS CARDS
// ==========================================================================
async function atualizarBadges() {
    const estoques = [10117, 16595, 16596];
    
    for (const id of estoques) {
        try {
            const response = await apiFetch(`v1/auditoria-estoque/estoque-ativo/${id}?idusuario=${getUserId()}`);
            if (response && response.pedido) {
                const badgeContainer = document.getElementById(`badge-container-${id}`);
                if (badgeContainer) {
                    const totalProdutos = response.produtos.length;
                    const totalMovimentacoes = response.movimentacoes.length;
                    badgeContainer.innerHTML = `
                        <span class="inline-flex items-center gap-1 text-xs bg-slate-100 px-2 py-1 rounded-full">
                            <i class="fa-solid fa-boxes"></i> ${totalProdutos}
                        </span>
                        <span class="inline-flex items-center gap-1 text-xs bg-slate-100 px-2 py-1 rounded-full ml-1">
                            <i class="fa-solid fa-clock"></i> ${totalMovimentacoes}
                        </span>
                    `;
                }
            }
        } catch (e) {
            console.error(`Erro ao carregar badge para ${id}:`, e);
        }
    }
}

// ==========================================================================
// RENDERIZAR PRODUTOS
// ==========================================================================
function renderizarProdutos(produtos) {
    const container = document.getElementById('listaProdutos');
    
    if (!produtos || produtos.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-slate-400">
                <i class="fa-solid fa-box-open text-4xl mb-2 block"></i>
                Nenhum produto neste estoque
            </div>
        `;
        return;
    }
    
    let html = '';
    produtos.forEach(produto => {
        const fotoUrl = getImageUrl(produto.foto_url);
        const quantidade = parseFloat(produto.quantidade_atual);
        
        html += `
            <div class="produto-item p-4 hover:bg-slate-50 transition-colors" data-iditem="${produto.iditem}">
                <div class="flex gap-4">
                    <img src="${fotoUrl}" class="w-12 h-12 object-contain rounded-lg bg-slate-100 p-1" onerror="this.src='https://placehold.co/100x100?text=S/F'">
                    <div class="flex-1">
                        <div class="font-bold text-slate-800">${produto.nome_item}</div>
                        <div class="text-xs text-slate-500 flex flex-wrap gap-3 mt-1">
                            <span><i class="fa-solid fa-barcode"></i> ${produto.cod_barras}</span>
                            <span><i class="fa-solid fa-tag"></i> Ref: ${produto.referencia || '---'}</span>
                        </div>
                        <div class="flex flex-wrap gap-3 mt-2 text-sm">
                            <span class="bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">
                                <i class="fa-solid fa-box"></i> ${formatarQuantidade(quantidade)} uni
                            </span>
                            <span class="bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                <i class="fa-solid fa-weight-scale"></i> ${formatarPeso(produto.peso_total)}
                            </span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="abrirModalHistorico(${produto.iditem}, '${produto.nome_item.replace(/'/g, "\\'")}')" 
                                class="text-purple-500 hover:text-purple-700 p-2 rounded-lg hover:bg-purple-50 transition-colors" title="Histórico">
                            <i class="fa-solid fa-timeline"></i>
                        </button>
                        <button onclick="abrirModalAjustar(${produto.iditem}, '${produto.nome_item.replace(/'/g, "\\'")}', '${produto.cod_barras}', ${quantidade}, ${produto.peso_total})" 
                                class="text-amber-500 hover:text-amber-700 p-2 rounded-lg hover:bg-amber-50 transition-colors" title="Ajustar">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button onclick="abrirModalMover(${produto.iditem}, '${produto.nome_item.replace(/'/g, "\\'")}', '${produto.cod_barras}', ${quantidade})" 
                                class="text-blue-500 hover:text-blue-700 p-2 rounded-lg hover:bg-blue-50 transition-colors" title="Mover">
                            <i class="fa-solid fa-right-left"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ==========================================================================
// RENDERIZAR MOVIMENTAÇÕES
// ==========================================================================
function renderizarMovimentacoes(movimentacoes) {
    const container = document.getElementById('listaMovimentacoes');
    
    if (!movimentacoes || movimentacoes.length === 0) {
        container.innerHTML = `
            <div class="text-center py-8 text-slate-400">
                <i class="fa-solid fa-inbox text-4xl mb-2 block"></i>
                Nenhuma movimentação registrada
            </div>
        `;
        return;
    }
    
    let html = '';
    movimentacoes.forEach(mov => {
        let bgClass = '';
        let icon = '';
        let textClass = '';
        
        if (mov.tipo_movimentacao === 'ENTRADA') {
            bgClass = 'bg-green-50';
            icon = 'fa-arrow-right-to-bracket';
            textClass = 'text-green-700';
        } else if (mov.tipo_movimentacao === 'SAIDA') {
            bgClass = 'bg-red-50';
            icon = 'fa-arrow-right-from-bracket';
            textClass = 'text-red-700';
        } else {
            bgClass = 'bg-amber-50';
            icon = 'fa-pen';
            textClass = 'text-amber-700';
        }
        
        html += `
            <div class="mov-item p-4 ${bgClass} transition-colors">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fa-solid ${icon} ${textClass} text-sm"></i>
                            <span class="font-bold text-sm ${textClass}">${mov.tipo_movimentacao}</span>
                            <span class="text-xs text-slate-400">${new Date(mov.data_hora).toLocaleString('pt-br')}</span>
                        </div>
                        <div class="font-bold text-slate-800">${mov.nome_item}</div>
                        <div class="text-sm text-slate-600 mt-1">
                            ${formatarQuantidade(mov.quant)} uni
                            ${mov.peso_real ? ` | Peso: ${formatarPeso(mov.peso_real)}` : ''}
                        </div>
                        <div class="text-xs text-slate-500 mt-1">
                            <i class="fa-solid fa-user"></i> ${mov.nome_usuario || 'Sistema'}
                            ${mov.lote ? ` | Lote: ${mov.lote}` : ''}
                        </div>
                        ${mov.observacao ? `<div class="text-xs text-amber-600 mt-1"><i class="fa-solid fa-comment"></i> ${mov.observacao}</div>` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// ==========================================================================
// ATUALIZAR ESTOQUE
// ==========================================================================
async function atualizarEstoque() {
    if (!estoqueAtual) return;
    await selecionarEstoque(estoqueAtual.id, estoqueAtual.nome, estoqueAtual.cor);
}

// ==========================================================================
// BUSCAR PRODUTOS
// ==========================================================================
document.getElementById('buscaProduto')?.addEventListener('input', function(e) {
    const termo = e.target.value.toLowerCase();
    const produtos = document.querySelectorAll('.produto-item');
    
    produtos.forEach(produto => {
        const texto = produto.innerText.toLowerCase();
        if (texto.includes(termo)) {
            produto.style.display = 'block';
        } else {
            produto.style.display = 'none';
        }
    });
});

// ==========================================================================
// MODAL AJUSTAR PRODUTO
// ==========================================================================
let ajustarProdutoId = null;
let ajustarProdutoNome = null;
let ajustarQuantAtual = 0;

function abrirModalAjustar(iditem, nome, codigo, quantidade, pesoTotal) {
    ajustarProdutoId = iditem;
    ajustarProdutoNome = nome;
    ajustarQuantAtual = quantidade;
    
    document.getElementById('ajustarProdutoNome').innerText = nome;
    document.getElementById('ajustarProdutoCodigo').innerHTML = `<i class="fa-solid fa-barcode"></i> ${codigo}`;
    document.getElementById('ajustarQuantAtual').value = formatarQuantidade(quantidade);
    document.getElementById('ajustarNovaQuant').value = quantidade;
    document.getElementById('ajustarPesoReal').value = '';
    document.getElementById('ajustarLote').value = '';
    document.getElementById('ajustarValidade').value = '';
    document.getElementById('ajustarObservacao').value = '';
    
    document.getElementById('modalAjustar').style.display = 'flex';
    document.getElementById('modalAjustar').classList.add('flex');
    document.getElementById('modalAjustar').classList.remove('hidden');
    
    setTimeout(() => {
        document.getElementById('ajustarNovaQuant').focus();
    }, 100);
}

function fecharModalAjustar() {
    document.getElementById('modalAjustar').style.display = 'none';
    document.getElementById('modalAjustar').classList.remove('flex');
    document.getElementById('modalAjustar').classList.add('hidden');
}

async function confirmarAjuste() {
    const novaQuantidade = parseFloat(document.getElementById('ajustarNovaQuant').value);
    const pesoReal = parseFloat(document.getElementById('ajustarPesoReal').value) || null;
    const lote = document.getElementById('ajustarLote').value;
    const validade = document.getElementById('ajustarValidade').value;
    const motivo = document.getElementById('ajustarMotivo').value;
    const observacao = document.getElementById('ajustarObservacao').value;
    
    if (isNaN(novaQuantidade) || novaQuantidade < 0) {
        showToast('Nova quantidade inválida', 'error');
        return;
    }
    
    Swal.fire({
        title: 'Ajustando produto...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        position: 'top'
    });
    
    try {
        const result = await apiFetch('v1/auditoria-estoque/ajustar-produto', 'POST', {
            idpedido: pedidoAtual.idpedido,
            iditem: ajustarProdutoId,
            nova_quantidade: novaQuantidade,
            peso_real: pesoReal,
            lote: lote || null,
            validade: validade || null,
            motivo: motivo,
            observacao: observacao || null,
            idusuario: getUserId()
        });
        
        Swal.close();
        
        if (result.success) {
            showToast(result.message, 'success');
            fecharModalAjustar();
            await selecionarEstoque(estoqueAtual.id, estoqueAtual.nome, estoqueAtual.cor);
        } else {
            showToast(result.error || 'Erro ao ajustar produto', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error('Erro ao ajustar:', e);
        showToast('Erro ao ajustar produto', 'error');
    }
}

// ==========================================================================
// MODAL MOVER PRODUTO
// ==========================================================================
let moverProdutoId = null;
let moverProdutoNome = null;
let moverQuantidadeDisponivel = 0;

function abrirModalMover(iditem, nome, codigo, quantidade) {
    moverProdutoId = iditem;
    moverProdutoNome = nome;
    moverQuantidadeDisponivel = quantidade;
    moverDestinoId = null;
    moverDestinoNome = null;
    
    document.getElementById('moverProdutoNome').innerText = nome;
    document.getElementById('moverProdutoCodigo').innerHTML = `<i class="fa-solid fa-barcode"></i> ${codigo}`;
    document.getElementById('moverQuantidade').value = '';
    document.getElementById('moverQuantidade').max = quantidade;
    document.getElementById('moverDisponivel').innerText = formatarQuantidade(quantidade);
    document.getElementById('moverDestinoSelecionado').innerHTML = '';
    document.getElementById('moverObservacao').value = '';
    
    // Reseta botões de destino
    document.querySelectorAll('.mover-destino-btn').forEach(btn => {
        btn.classList.remove('bg-green-500', 'bg-blue-500', 'bg-amber-500', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-600');
    });
    
    document.getElementById('modalMover').style.display = 'flex';
    document.getElementById('modalMover').classList.add('flex');
    document.getElementById('modalMover').classList.remove('hidden');
    
    setTimeout(() => {
        document.getElementById('moverQuantidade').focus();
    }, 100);
}

function fecharModalMover() {
    document.getElementById('modalMover').style.display = 'none';
    document.getElementById('modalMover').classList.remove('flex');
    document.getElementById('modalMover').classList.add('hidden');
}

function setMoverDestino(id, nome, cor) {
    moverDestinoId = id;
    moverDestinoNome = nome;
    
    document.querySelectorAll('.mover-destino-btn').forEach(btn => {
        btn.classList.remove('bg-green-500', 'bg-blue-500', 'bg-amber-500', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-600');
    });
    
    const btnMap = {
        10117: 0,
        16595: 1,
        16596: 2
    };
    const btns = document.querySelectorAll('.mover-destino-btn');
    if (btns[btnMap[id]]) {
        btns[btnMap[id]].classList.remove('bg-slate-100', 'text-slate-600');
        btns[btnMap[id]].classList.add(`bg-${cor === '#10b981' ? 'green' : (cor === '#3b82f6' ? 'blue' : 'amber')}-500`, 'text-white');
    }
    
    document.getElementById('moverDestinoSelecionado').innerHTML = `Destino: <strong>${nome}</strong>`;
}

async function confirmarMover() {
    if (!moverDestinoId) {
        showToast('Selecione um destino', 'warning');
        return;
    }
    
    const quantidade = parseFloat(document.getElementById('moverQuantidade').value);
    const motivo = document.getElementById('moverMotivo').value;
    const observacao = document.getElementById('moverObservacao').value;
    
    if (isNaN(quantidade) || quantidade <= 0) {
        showToast('Quantidade inválida', 'error');
        return;
    }
    
    if (quantidade > moverQuantidadeDisponivel) {
        showToast(`Quantidade não pode exceder ${formatarQuantidade(moverQuantidadeDisponivel)}`, 'error');
        return;
    }
    
    Swal.fire({
        title: 'Movendo produto...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        position: 'top'
    });
    
    try {
        const result = await apiFetch('v1/auditoria-estoque/mover-produto', 'POST', {
            idpedido_origem: pedidoAtual.idpedido,
            idcliforemp_destino: moverDestinoId,
            iditem: moverProdutoId,
            quantidade: quantidade,
            idusuario: getUserId(),
            motivo: motivo,
            observacao: observacao || null
        });
        
        Swal.close();
        
        if (result.success) {
            showToast(result.message, 'success');
            fecharModalMover();
            await selecionarEstoque(estoqueAtual.id, estoqueAtual.nome, estoqueAtual.cor);
        } else {
            showToast(result.error || 'Erro ao mover produto', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error('Erro ao mover:', e);
        showToast('Erro ao mover produto', 'error');
    }
}

// ==========================================================================
// MODAL HISTÓRICO DO PRODUTO
// ==========================================================================
async function abrirModalHistorico(iditem, nome) {
    document.getElementById('modalHistorico').style.display = 'flex';
    document.getElementById('modalHistorico').classList.add('flex');
    document.getElementById('modalHistorico').classList.remove('hidden');
    
    document.getElementById('historicoConteudo').innerHTML = `
        <div class="text-center py-8 text-slate-400">
            <i class="fa-solid fa-spinner fa-spin text-2xl mb-2 block"></i>
            Carregando histórico...
        </div>
    `;
    
    try {
        const movimentacoes = await apiFetch(`v1/auditoria-estoque/movimentacoes-produto/${pedidoAtual.idpedido}/${iditem}`);
        
        if (!movimentacoes || movimentacoes.length === 0) {
            document.getElementById('historicoConteudo').innerHTML = `
                <div class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-inbox text-4xl mb-2 block"></i>
                    Nenhuma movimentação encontrada para este produto
                </div>
            `;
            return;
        }
        
        let html = '';
        movimentacoes.forEach(mov => {
            let bgClass = '';
            let icon = '';
            
            if (mov.tipo_movimentacao === 'ENTRADA') {
                bgClass = 'bg-green-50 border-l-green-500';
                icon = 'fa-arrow-right-to-bracket text-green-600';
            } else if (mov.tipo_movimentacao === 'SAIDA') {
                bgClass = 'bg-red-50 border-l-red-500';
                icon = 'fa-arrow-right-from-bracket text-red-600';
            } else {
                bgClass = 'bg-amber-50 border-l-amber-500';
                icon = 'fa-pen text-amber-600';
            }
            
            html += `
                <div class="${bgClass} border-l-4 rounded-xl p-3">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fa-solid ${icon} text-sm"></i>
                                <span class="font-bold text-sm">${mov.tipo_movimentacao}</span>
                                <span class="text-xs text-slate-400">${new Date(mov.data_hora).toLocaleString('pt-br')}</span>
                            </div>
                            <div class="text-sm text-slate-600">
                                Quantidade: ${formatarQuantidade(mov.quant)} uni
                                ${mov.peso_real ? ` | Peso Real: ${formatarPeso(mov.peso_real)}` : ''}
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                Antes: ${formatarQuantidade(mov.quantidade_antes)} uni | 
                                Depois: ${formatarQuantidade(mov.quantidade_depois)} uni
                            </div>
                            <div class="text-xs text-slate-500 mt-1">
                                <i class="fa-solid fa-user"></i> ${mov.nome_usuario || 'Sistema'}
                                ${mov.lote ? ` | Lote: ${mov.lote}` : ''}
                                ${mov.validade ? ` | Validade: ${new Date(mov.validade).toLocaleDateString('pt-br')}` : ''}
                            </div>
                            <div class="text-xs text-amber-600 mt-1">
                                <i class="fa-solid fa-tag"></i> Motivo: ${mov.motivo}
                            </div>
                            ${mov.observacao ? `<div class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-comment"></i> ${mov.observacao}</div>` : ''}
                            ${mov.path_foto ? `<div class="mt-2"><a href="/${mov.path_foto}" target="_blank" class="text-xs text-blue-500 hover:underline"><i class="fa-solid fa-image"></i> Ver foto</a></div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        document.getElementById('historicoConteudo').innerHTML = html;
        
    } catch (e) {
        console.error('Erro ao carregar histórico:', e);
        document.getElementById('historicoConteudo').innerHTML = `
            <div class="text-center py-8 text-red-500">
                <i class="fa-solid fa-circle-exclamation text-2xl mb-2 block"></i>
                Erro ao carregar histórico
            </div>
        `;
    }
}

function fecharModalHistorico() {
    document.getElementById('modalHistorico').style.display = 'none';
    document.getElementById('modalHistorico').classList.remove('flex');
    document.getElementById('modalHistorico').classList.add('hidden');
}

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
document.addEventListener('DOMContentLoaded', function() {
    atualizarBadges();
});