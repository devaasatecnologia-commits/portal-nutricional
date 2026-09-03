// portal/assets/js/retorno.js

// ==========================================================================
// NOTA: Este arquivo depende do core.js (apiFetch, getUserId, getUserNome)
// ==========================================================================

// ==========================================================================
// VARIÁVEIS GLOBAIS
// ==========================================================================
let produtoAtual = null;
let destinoSelecionado = null;
let destinoNome = null;
let destinoCor = null;
let tipoMovimentacao = 'ENTRADA';
let produtoExisteNoPedido = false;
let dadosProdutoPedido = null;


// ==========================================================================
// FUNÇÕES AUXILIARES
// ==========================================================================
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
    if (!peso || peso === 0) return '0 kg';
    return parseFloat(peso).toLocaleString('pt-br', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    }) + ' kg';
}

function formatarQuantidade(qt) {
    if (!qt || qt === 0) return '0';
    return parseFloat(qt).toLocaleString('pt-br', { 
        minimumFractionDigits: 2, 
        maximumFractionDigits: 2 
    });
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

function limparTudo() {
    produtoAtual = null;
    destinoSelecionado = null;
    produtoExisteNoPedido = false;
    dadosProdutoPedido = null;
    tipoMovimentacao = 'ENTRADA';
    isProcessing = false;
    
    const elementos = ['produtoEncontrado', 'areaDestinos', 'areaOperacao', 'infoItemExistente'];
    elementos.forEach(id => {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
    });
    
    ['quantMov', 'pesoRealMov', 'loteMov', 'validadeMov', 'observacaoMov', 'fotoMov'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    
    const preview = document.getElementById('previewFoto');
    if (preview) preview.classList.add('hidden');
    
    const input = document.getElementById('barcode-input');
    if (input) {
        input.value = '';
        input.focus();
    }
    
    setTipoMovimentacao('ENTRADA');
}

// ==========================================================================
// CARREGAR MOVIMENTAÇÕES DO DIA
// ==========================================================================
async function carregarMovimentacoesHoje() {
    try {
        const movimentacoes = await apiFetch('v1/retorno/movimentacoes-hoje');
        
        const container = document.getElementById('movimentacoesHoje');
        const semMov = document.getElementById('semMovimentacoes');
        
        if (!container) return;
        
        if (!movimentacoes || movimentacoes.length === 0) {
            container.innerHTML = '';
            if (semMov) semMov.classList.remove('hidden');
            return;
        }
        
        if (semMov) semMov.classList.add('hidden');
        
        let html = '';
        movimentacoes.forEach(mov => {
            let tipoClass = '';
            let tipoIcone = '';
            let tipoTexto = '';
            let badgeClass = '';
            
            if (mov.tipo_movimentacao === 'ENTRADA') {
                tipoClass = 'border-l-green-500 bg-green-50';
                tipoIcone = 'fa-arrow-right-to-bracket';
                tipoTexto = 'ENTRADA';
                badgeClass = 'bg-green-100 text-green-700';
            } else if (mov.tipo_movimentacao === 'SAIDA') {
                tipoClass = 'border-l-red-500 bg-red-50';
                tipoIcone = 'fa-arrow-right-from-bracket';
                tipoTexto = 'SAÍDA';
                badgeClass = 'bg-red-100 text-red-700';
            } else {
                tipoClass = 'border-l-amber-500 bg-amber-50';
                tipoIcone = 'fa-pen';
                tipoTexto = 'AJUSTE';
                badgeClass = 'bg-amber-100 text-amber-700';
            }
            
            html += `
                <div class="movimentacao-card ${tipoClass} rounded-xl p-3 border-l-4 hover:shadow transition-all">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full ${badgeClass}">${tipoTexto}</span>
                                <span class="text-xs font-mono text-slate-500">Estoque: ${mov.destino_nome || 'N/A'}</span>
                            </div>
                            <div class="font-bold text-sm text-slate-700">${mov.nome_item || 'Produto'}</div>
                            <div class="text-xs text-slate-500 mt-1">
                                <i class="fa-solid fa-box"></i> ${formatarQuantidade(Math.abs(mov.quant))} uni
                                ${mov.peso_real ? `<span class="ml-2"><i class="fa-solid fa-weight-scale"></i> ${formatarPeso(mov.peso_real)}</span>` : ''}
                                ${mov.lote ? `<span class="ml-2"><i class="fa-solid fa-tag"></i> Lote: ${mov.lote}</span>` : ''}
                            </div>
                            <div class="text-xs text-slate-400 mt-1">
                                <i class="fa-solid fa-user"></i> ${mov.nome_usuario || 'Sistema'} | 
                                <i class="fa-solid fa-clock"></i> ${new Date(mov.data_hora).toLocaleTimeString('pt-br')}
                            </div>
                            ${mov.observacao ? `<div class="text-xs text-amber-600 mt-1"><i class="fa-solid fa-comment"></i> ${mov.observacao}</div>` : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
        
    } catch (e) {
        console.error('Erro ao carregar movimentações:', e);
    }
}

// ==========================================================================
// PROCESSAR LEITURA DO CÓDIGO DE BARRAS
// ==========================================================================
async function processarLeitura(codigo) {
    if (!codigo || isProcessing) return;
    isProcessing = true;
    
    Swal.fire({
        title: 'Buscando produto...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        position: 'top',
        timer: 10000
    });
    
    try {
        const produto = await apiFetch('v1/retorno/buscar-produto', 'POST', { busca: codigo });
        Swal.close();
        isProcessing = false;
        
        if (produto.error) {
            showToast(produto.error, 'error');
            return;
        }
        
        produtoAtual = produto;
        
        const nomeEl = document.getElementById('produtoNome');
        if (nomeEl) nomeEl.innerText = produto.descricao || 'Produto';
        
        const codigoEl = document.getElementById('produtoCodigo');
        if (codigoEl) codigoEl.innerHTML = `<i class="fa-solid fa-barcode"></i> ${produto.cod_barras || 'S/COD'}`;
        
        const refEl = document.getElementById('produtoReferencia');
        if (refEl) refEl.innerHTML = `<i class="fa-solid fa-tag"></i> Ref: ${produto.referencia || '---'}`;
        
        const pesoEl = document.getElementById('produtoPeso');
        if (pesoEl) pesoEl.innerText = formatarPeso(produto.pesoliquido);
        
        const fotoUrl = getImageUrl(produto.foto_url);
        const fotoEl = document.getElementById('produtoFoto');
        if (fotoEl) {
            fotoEl.src = fotoUrl;
            fotoEl.onerror = function() {
                this.src = 'https://placehold.co/100x100?text=S/F';
            };
        }
        
        document.getElementById('produtoEncontrado').style.display = 'block';
        document.getElementById('areaDestinos').style.display = 'block';
        document.getElementById('areaOperacao').style.display = 'none';
        document.getElementById('infoItemExistente').style.display = 'none';
        
        produtoExisteNoPedido = false;
        dadosProdutoPedido = null;
        tipoMovimentacao = 'ENTRADA';
        setTipoMovimentacao('ENTRADA');
        
        const input = document.getElementById('barcode-input');
        if (input) {
            input.value = '';
            input.focus();
        }
        
    } catch (e) {
        Swal.close();
        isProcessing = false;
        console.error('Erro ao buscar produto:', e);
        showToast('Erro ao buscar produto', 'error');
    }
}

// ==========================================================================
// SELECIONAR DESTINO
// ==========================================================================
async function selecionarDestino(id, nome, cor) {
    destinoSelecionado = id;
    destinoNome = nome;
    destinoCor = cor;
    
    document.querySelectorAll('.destino-card').forEach(card => {
        card.classList.remove('active', 'border-green-500', 'border-blue-500', 'border-amber-500');
        card.style.borderColor = 'transparent';
        card.style.backgroundColor = 'white';
    });
    
    const cards = document.querySelectorAll('.destino-card');
    const index = id === 10117 ? 0 : (id === 16595 ? 1 : 2);
    if (cards[index]) {
        cards[index].classList.add('active');
        cards[index].style.borderColor = cor;
        cards[index].style.backgroundColor = `${cor}10`;
    }
    
    Swal.fire({
        title: 'Verificando estoque...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        position: 'top',
        timer: 15000
    });
    
    try {
        const response = await apiFetch(`v1/auditoria-estoque/estoque-ativo/${id}?idusuario=${getUserId()}`);
        Swal.close();
        
        if (response && response.pedido) {
            const existeResponse = await apiFetch(`v1/retorno/produto-existe/${response.pedido.idpedido}/${produtoAtual.iditem}`);
            
            if (existeResponse.existe) {
                produtoExisteNoPedido = true;
                dadosProdutoPedido = existeResponse.dados;
                
                const quantEl = document.getElementById('infoQuantAtual');
                if (quantEl) quantEl.innerText = formatarQuantidade(dadosProdutoPedido.quantidade_atual);
                
                const pesoEl = document.getElementById('infoPesoAtual');
                if (pesoEl) pesoEl.innerText = formatarPeso(dadosProdutoPedido.peso_total);
                
                document.getElementById('infoItemExistente').style.display = 'block';
                
                ['btnEntrada', 'btnSaida', 'btnAjuste'].forEach(id => {
                    const btn = document.getElementById(id);
                    if (btn) {
                        btn.disabled = false;
                        btn.classList.remove('opacity-50', 'cursor-not-allowed');
                    }
                });
            } else {
                produtoExisteNoPedido = false;
                dadosProdutoPedido = null;
                document.getElementById('infoItemExistente').style.display = 'none';
                
                const btnEntrada = document.getElementById('btnEntrada');
                if (btnEntrada) {
                    btnEntrada.disabled = false;
                    btnEntrada.classList.remove('opacity-50', 'cursor-not-allowed');
                }
                
                ['btnSaida', 'btnAjuste'].forEach(id => {
                    const btn = document.getElementById(id);
                    if (btn) {
                        btn.disabled = true;
                        btn.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                });
                
                tipoMovimentacao = 'ENTRADA';
                setTipoMovimentacao('ENTRADA');
            }
        }
        
        document.getElementById('areaOperacao').style.display = 'block';
        const quantInput = document.getElementById('quantMov');
        if (quantInput) quantInput.focus();
        
    } catch (e) {
        Swal.close();
        console.error('Erro ao verificar estoque:', e);
        showToast('Erro ao verificar estoque', 'error');
    }
}

// ==========================================================================
// SET TIPO DE MOVIMENTAÇÃO
// ==========================================================================
function setTipoMovimentacao(tipo) {
    tipoMovimentacao = tipo;
    
    const btnEntrada = document.getElementById('btnEntrada');
    const btnSaida = document.getElementById('btnSaida');
    const btnAjuste = document.getElementById('btnAjuste');
    const quantInput = document.getElementById('quantMov');
    const quantAtual = dadosProdutoPedido ? parseFloat(dadosProdutoPedido.quantidade_atual) : 0;
    
    if (btnEntrada) {
        btnEntrada.className = 'flex-1 py-3 rounded-xl font-bold text-sm transition-all ' + 
            (tipo === 'ENTRADA' ? 'bg-green-500 text-white' : 'bg-slate-200 text-slate-600');
    }
    if (btnSaida) {
        btnSaida.className = 'flex-1 py-3 rounded-xl font-bold text-sm transition-all ' + 
            (tipo === 'SAIDA' ? 'bg-red-500 text-white' : 'bg-slate-200 text-slate-600');
    }
    if (btnAjuste) {
        btnAjuste.className = 'flex-1 py-3 rounded-xl font-bold text-sm transition-all ' + 
            (tipo === 'AJUSTE' ? 'bg-amber-500 text-white' : 'bg-slate-200 text-slate-600');
    }
    
    if (quantInput) {
        if (tipo === 'ENTRADA') {
            quantInput.placeholder = 'Quantidade a adicionar';
            quantInput.max = 999999;
            quantInput.value = '';
            quantInput.step = 'any';
        } else if (tipo === 'SAIDA') {
            quantInput.placeholder = `Quantidade a remover (máx: ${formatarQuantidade(quantAtual)})`;
            quantInput.max = quantAtual;
            quantInput.value = '';
            quantInput.step = 'any';
        } else {
            quantInput.placeholder = 'Nova quantidade total';
            quantInput.max = 999999;
            quantInput.value = quantAtual > 0 ? quantAtual : '';
            quantInput.step = 'any';
        }
        quantInput.focus();
        quantInput.select();
    }
}

// ==========================================================================
// CONFIRMAR MOVIMENTAÇÃO
// ==========================================================================
async function confirmarMovimentacao() {
    if (!produtoAtual) {
        showToast('Nenhum produto selecionado', 'error');
        return;
    }
    
    if (!destinoSelecionado) {
        showToast('Selecione um destino', 'error');
        return;
    }
    
    if (isProcessing) {
        showToast('Aguarde, processando...', 'warning');
        return;
    }
    isProcessing = true;
    
    const quantidade = parseFloat(document.getElementById('quantMov').value);
    const pesoReal = parseFloat(document.getElementById('pesoRealMov').value) || null;
    const lote = document.getElementById('loteMov').value;
    const validade = document.getElementById('validadeMov').value;
    const motivo = document.getElementById('motivoMov').value;
    const observacao = document.getElementById('observacaoMov').value;
    const fotoFile = document.getElementById('fotoMov').files[0];
    
    if (isNaN(quantidade) || quantidade <= 0) {
        showToast('Quantidade inválida', 'error');
        isProcessing = false;
        return;
    }
    
    const quantAtual = dadosProdutoPedido ? parseFloat(dadosProdutoPedido.quantidade_atual) : 0;
    
    if (tipoMovimentacao === 'SAIDA' && quantidade > quantAtual) {
        showToast(`Quantidade não pode exceder o saldo atual (${formatarQuantidade(quantAtual)})`, 'error');
        isProcessing = false;
        return;
    }
    
    if (tipoMovimentacao === 'AJUSTE' && quantidade === quantAtual) {
        showToast('A nova quantidade deve ser diferente da atual', 'warning');
        isProcessing = false;
        return;
    }
    
    Swal.fire({
        title: 'Processando...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        position: 'top',
        timer: 30000
    });
    
    try {
        const body = {
            idcliforemp_destino: destinoSelecionado,
            iditem: produtoAtual.iditem,
            tipo: tipoMovimentacao,
            quantidade: quantidade,
            peso_real: pesoReal,
            lote: lote || null,
            validade: validade || null,
            motivo: motivo,
            observacao: observacao || null,
            idusuario: parseInt(getUserId()),
            cod_barras: produtoAtual.cod_barras || null
        };
        
        if (tipoMovimentacao === 'AJUSTE') {
            body.nova_quantidade = quantidade;
        }
        
        console.log('[confirmarMovimentacao] Enviando:', body);
        const result = await apiFetch('v1/retorno/movimentar', 'POST', body);
        console.log('[confirmarMovimentacao] Resposta:', result);
        
        if (result.success) {
            if (fotoFile && result.id_movimentacao) {
                const formData = new FormData();
                formData.append('foto', fotoFile);
                formData.append('id_movimentacao', result.id_movimentacao);
                
                await apiFetch('v1/retorno/upload-foto', 'POST', formData);
            }
            
            Swal.close();
            showToast(result.message || 'Movimentação realizada com sucesso!', 'success');
            
            limparTudo();
            await carregarMovimentacoesHoje();
            
            const input = document.getElementById('barcode-input');
            if (input) input.focus();
        } else {
            Swal.close();
            showToast(result.error || 'Erro na movimentação', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error('Erro na movimentação:', e);
        showToast('Erro ao processar movimentação: ' + (e.message || 'Erro desconhecido'), 'error');
    } finally {
        isProcessing = false;
    }
}

// ==========================================================================
// PREVIEW DA FOTO
// ==========================================================================
document.addEventListener('DOMContentLoaded', function() {
    const fotoInput = document.getElementById('fotoMov');
    if (fotoInput) {
        fotoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('previewFoto');
            const previewImg = document.getElementById('previewImagem');
            
            if (file && preview && previewImg) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    previewImg.src = event.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            } else if (preview) {
                preview.classList.add('hidden');
            }
        });
    }
});

// ==========================================================================
// SCANNER DE CÓDIGO DE BARRAS
// ==========================================================================
function toggleCamera() {
    const div = document.getElementById('reader');
    if (!div) return;
    
    if (div.style.display === 'block') {
        if (scanner) {
            scanner.stop();
            scanner = null;
        }
        div.style.display = 'none';
    } else {
        window.scrollTo(0, 0);
        document.getElementById('barcode-input')?.blur();
        div.style.display = 'block';
        
        if (typeof Html5Qrcode === 'undefined') {
            showToast('Biblioteca de leitura não carregada', 'error');
            div.style.display = 'none';
            return;
        }
        
        scanner = new Html5Qrcode('reader');
        scanner.start(
            { facingMode: 'environment' }, 
            { fps: 20, qrbox: 260 }, 
            (txt) => {
                scanner.stop();
                scanner = null;
                div.style.display = 'none';
                processarLeitura(txt);
            }
        ).catch((err) => {
            console.error('Erro ao iniciar câmera:', err);
            div.style.display = 'none';
            showToast('Erro ao abrir câmera', 'error');
        });
    }
}

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Retorno] DOM carregado');
    console.log('[Retorno] API_URL:', typeof API_URL !== 'undefined' ? API_URL : 'N/A');
    console.log('[Retorno] getUserId:', getUserId());
    
    function atualizarRelogio() {
        const agora = new Date();
        const horaFormatada = agora.toLocaleTimeString('pt-br');
        const dataFormatada = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
        
        const relogio = document.getElementById('relogio');
        const relogioMobile = document.getElementById('relogioMobile');
        const dataTopo = document.getElementById('data-topo');
        
        if (relogio) relogio.innerText = horaFormatada;
        if (relogioMobile) relogioMobile.innerText = horaFormatada;
        if (dataTopo) dataTopo.innerText = dataFormatada;
    }
    
    atualizarRelogio();
    setInterval(atualizarRelogio, 1000);
    
    carregarMovimentacoesHoje();
    setInterval(carregarMovimentacoesHoje, 30000);
    
    const barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const value = this.value.trim();
                if (value) {
                    processarLeitura(value);
                    this.value = '';
                }
            }
        });
        
        document.addEventListener('click', function() {
            if (!document.querySelector('.swal2-container') && 
                !document.querySelector('.modal') &&
                !document.activeElement?.closest('input, button, textarea, select')) {
                barcodeInput.focus();
            }
        });
    }
});

// ==========================================================================
// EXPORTA FUNÇÕES GLOBAIS
// ==========================================================================
window.selecionarDestino = selecionarDestino;
window.setTipoMovimentacao = setTipoMovimentacao;
window.confirmarMovimentacao = confirmarMovimentacao;
window.limparTudo = limparTudo;
window.toggleCamera = toggleCamera;
window.carregarMovimentacoesHoje = carregarMovimentacoesHoje;
window.processarLeitura = processarLeitura;