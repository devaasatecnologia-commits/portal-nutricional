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
let isProcessing = false;
let scanner = null;

// O config.js define apiFetch(endpoint, options); aqui o padrão é (acao, metodo, body)
const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const endpoint = String(acao).replace(/^\/+/, '').replace(/^v1\//, '');
    const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    const options = { method: metodo, headers: {} };
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) options.headers.Authorization = 'Bearer ' + token;

    let url = apiRoot + '/' + endpoint;
    if (body instanceof FormData) {
        options.body = body;
    } else if (body && metodo === 'GET') {
        url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();
    } else if (body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    if (response.status === 401) {
        window.location.href = '/portal/login.php';
        throw new Error('Sessão expirada');
    }
    const text = await response.text();
    try {
        return text ? JSON.parse(text) : {};
    } catch (e) {
        return { error: response.ok ? 'Resposta inválida do servidor' : `Erro ${response.status}` };
    }
};

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
    const sem = 'https://placehold.co/100x100?text=S/F';
    if (!foto) return sem;
    const n = String(foto).trim().replace(/\\/g, '/');
    if (!n || ['.', '-', 'null', 'undefined'].includes(n.toLowerCase())) return sem;
    if (/^https?:\/\//i.test(n)) return n.replace(/ /g, '%20');
    const marker = 'fotos para o site/';
    const idx = n.toLowerCase().indexOf(marker);
    const rel = idx >= 0 ? n.substring(idx + marker.length) : n.replace(/^\/+/, '').replace(/^fotos\//i, '');
    if (!rel) return sem;
    return 'https://acesso.nutricionalbr.com:2053/fotos/' + rel.split('/').map(encodeURIComponent).join('/');
}

function limparTudo() {
    produtoAtual = null;
    embarqueContexto = null;
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
async function processarLeitura(codigo, doEmbarque = false) {
    if (!codigo || isProcessing) return;
    if (!doEmbarque) embarqueContexto = null;
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
        if (pesoEl) pesoEl.innerText = formatarPeso(produto.pesoliquido) + ' kg';

        const badgeItem = document.getElementById('produtoBadgeItem');
        if (badgeItem) badgeItem.innerText = '#' + produto.iditem;

        const pesoBrutoEl = document.getElementById('produtoPesoBruto');
        if (pesoBrutoEl) pesoBrutoEl.innerText = formatarPeso(produto.pesobruto) + ' kg';

        const totalQtdEl = document.getElementById('produtoTotalEstoqueQtd');
        if (totalQtdEl) totalQtdEl.innerText = formatarQuantidade(produto.estoque_total_qtd || 0) + ' un';

        const totalPesoEl = document.getElementById('produtoTotalEstoquePeso');
        if (totalPesoEl) totalPesoEl.innerText = formatarPeso(produto.estoque_total_peso || 0) + ' kg';
        
        const fotoUrl = getImageUrl(produto.foto || produto.foto_url);
        const fotoEl = document.getElementById('produtoFoto');
        if (fotoEl) {
            fotoEl.src = fotoUrl;
            fotoEl.onerror = function() {
                this.src = 'https://placehold.co/100x100?text=S/F';
            };
        }

        // Atualiza os 4 cards de destinos com a situação em tempo real
        if (Array.isArray(produto.destinos_status)) {
            produto.destinos_status.forEach(st => {
                const qtdEl = document.getElementById(`destQtd_${st.idcliforemp}`);
                const pesoDestEl = document.getElementById(`destPeso_${st.idcliforemp}`);
                const pedEl = document.getElementById(`destPed_${st.idcliforemp}`);
                const cardEl = document.querySelector(`.destino-card[data-destino="${st.idcliforemp}"]`);

                if (qtdEl) {
                    if (st.quantidade > 0) {
                        qtdEl.innerText = `${formatarQuantidade(st.quantidade)} UN no Estoque`;
                        qtdEl.className = 'dest-qtd-badge px-2.5 py-1 rounded-xl text-xs font-black bg-emerald-100 text-emerald-800 border border-emerald-300 shadow-sm';
                    } else {
                        qtdEl.innerText = 'Sem saldo neste pedido';
                        qtdEl.className = 'dest-qtd-badge px-2.5 py-1 rounded-xl text-xs font-semibold bg-slate-100 text-slate-400';
                    }
                }

                if (pesoDestEl) {
                    pesoDestEl.innerText = st.quantidade > 0 ? `Peso: ${formatarPeso(st.peso_total)} kg` : 'Peso: 0 kg';
                }

                if (pedEl) {
                    pedEl.innerText = st.idpedido ? `Pedido #${st.idpedido}` : 'Sem pedido aberto';
                }

                if (cardEl) {
                    if (st.quantidade > 0) {
                        cardEl.classList.add('ring-2', 'ring-emerald-400/40');
                    } else {
                        cardEl.classList.remove('ring-2', 'ring-emerald-400/40');
                    }
                }
            });
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
    
    const card = document.querySelector(`.destino-card[data-destino="${id}"]`);
    if (card) {
        card.classList.add('active');
        card.style.borderColor = cor;
        card.style.backgroundColor = `${cor}10`;
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
        
        if (response && response.error) {
            document.getElementById('areaOperacao').style.display = 'none';
            if (response.codigo === 'SEM_PEDIDO_ABERTO') {
                const conf = await Swal.fire({
                    icon: 'question',
                    title: 'Sem pedido aberto',
                    html: `${response.error}<br><br><b>Deseja criar um novo "Estoque" para ${nome}?</b><br><small>Será gerado no ERP uma cópia do último pedido deste destino, com o item X-ITEM.</small>`,
                    showCancelButton: true,
                    confirmButtonText: 'Sim, criar estoque',
                    cancelButtonText: 'Não'
                });
                if (conf.isConfirmed) {
                    Swal.fire({ title: 'Criando estoque...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                    const criado = await apiFetch('v1/retorno/criar-estoque', 'POST', { idcliforemp: id });
                    Swal.close();
                    if (criado && criado.success) {
                        showToast(criado.message || 'Estoque criado!', 'success');
                        return selecionarDestino(id, nome, cor);
                    }
                    Swal.fire({ icon: 'error', title: 'Não foi possível criar', text: (criado && criado.error) || 'Erro desconhecido' });
                }
            } else {
                Swal.fire({ icon: 'warning', title: 'Atenção', text: response.error, confirmButtonText: 'OK' });
            }
            return;
        }
        
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
        // Vindo do embarque: só ENTRADA (destinar). No bipe avulso: todas as operações
        const botoesOp = document.getElementById('botoesOperacao');
        if (botoesOp) botoesOp.style.display = embarqueContexto ? 'none' : '';
        const tituloOp = document.getElementById('tituloOperacao');
        if (tituloOp) tituloOp.innerText = embarqueContexto ? `DESTINAR DO EMBARQUE #${embarqueContexto.id}` : 'ESCOLHA A OPERAÇÃO';
        if (embarqueContexto) { tipoMovimentacao = 'ENTRADA'; setTipoMovimentacao('ENTRADA'); }
        const quantInput = document.getElementById('quantMov');
        if (quantInput) {
            quantInput.focus();
        }
        
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
            observacao: embarqueContexto ? `DESTINAR DO EMBARQUE #${embarqueContexto.id}${observacao ? ' - ' + observacao : ''}` : (observacao || null),
            idusuario: parseInt(getUserId()),
            idembarque: embarqueContexto ? embarqueContexto.id : null,
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
            
            const embRecarregar = embarqueContexto ? embarqueContexto.id : null;
            limparTudo();
            await carregarMovimentacoesHoje();
            if (embRecarregar) await buscarEmbarqueRetorno(embRecarregar);
            
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
// RETORNO POR EMBARQUE
// ==========================================================================
let embarqueContexto = null;

if (typeof window.getUserId !== 'function') {
    window.getUserId = function () {
        const el = document.getElementById('user_id');
        return (el && el.value) ? el.value : '0';
    };
}

function escHtmlRet(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

let embarqueMostrarTodos = false;
function alternarTodosEmbarque(id) {
    embarqueMostrarTodos = !embarqueMostrarTodos;
    buscarEmbarqueRetorno(id);
}

async function buscarEmbarqueRetorno(idForcado) {
    const id = parseInt(idForcado || document.getElementById('embarqueBusca').value, 10);
    const box = document.getElementById('embarqueResultado');
    if (!id) { showToast('Informe o número do embarque', 'warning'); return; }
    box.classList.remove('hidden');
    box.innerHTML = '<div class="text-center text-slate-400 py-4">Carregando...</div>';
    try {
        const r = await apiFetch(`v1/retorno-embarque/${id}${embarqueMostrarTodos ? ((window.API_URL || '').includes('?') ? '&todos=1' : '?todos=1') : ''}`);
        if (!r || !r.success) throw new Error((r && r.error) || 'Falha');
        const n = formatarQuantidade;
        const toggle = `<button onclick="alternarTodosEmbarque(${id})" class="text-xs font-bold text-blue-600 underline">${embarqueMostrarTodos ? 'Mostrar só extras/devoluções' : `Ver todos os ${r.total_itens_embarque} itens do embarque`}</button>`;
        if (!r.itens.length) {
            box.innerHTML = `<div class="text-center text-slate-500 py-4">Nenhum item extra ou devolvido no acerto deste embarque.<div class="mt-2">${toggle}</div></div>`;
            return;
        }
        box.innerHTML = `
            <div class="flex items-center justify-between mb-2">
                <div class="text-xs font-bold text-slate-500">EMBARQUE #${id} - ${r.itens.length} ${embarqueMostrarTodos ? 'itens' : 'item(ns) extra/devolvido(s)'}</div>${toggle}
            </div>
            <div class="space-y-2 max-h-[55vh] overflow-y-auto pr-1">${r.itens.map(i => `
                <div class="border rounded-xl p-3 ${i.pendente_destinar > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200'}">
                    <div class="flex items-center gap-3">
                        <img src="${getImageUrl(i.foto)}" class="w-14 h-14 object-contain rounded-lg bg-slate-50 border" loading="lazy" onerror="this.onerror=null;this.src='https://placehold.co/100x100?text=S/F'">
                        <div class="font-bold text-sm text-slate-800">${escHtmlRet(i.descricao)} <span class="text-slate-400 font-normal">${escHtmlRet(i.referencia || '')}</span></div>
                    </div>
                    <div class="grid grid-cols-4 gap-2 text-center text-xs mt-2">
                        <div><div class="text-slate-400">Carregado</div><div class="font-black">${n(i.carregado)}${i.extra_subida > 0 ? `<span class="text-violet-600"> (+${n(i.extra_subida)})</span>` : ''}</div></div>
                        <div><div class="text-slate-400">Devolvido (acerto)</div><div class="font-black">${n(i.devolvido_acerto)}</div></div>
                        <div><div class="text-slate-400">Destinado</div><div class="font-black text-green-600">${n(i.destinado)}</div></div>
                        <div><div class="text-slate-400">Pendente</div><div class="font-black text-amber-600">${n(i.pendente_destinar)}</div></div>
                    </div>
                    <button onclick="destinarDoEmbarque(${id}, ${i.iditem}, ${i.pendente_destinar})"
                        class="mt-2 w-full py-2 rounded-lg text-xs font-bold ${i.pendente_destinar > 0 ? 'bg-amber-500 text-white' : 'bg-slate-100 text-slate-600'}">
                        <i class="fa-solid fa-arrow-right-arrow-left"></i> DESTINAR
                    </button>
                </div>`).join('')}
            </div>`;
    } catch (e) {
        box.innerHTML = '<div class="text-center text-red-500 py-4">Erro ao buscar embarque.</div>';
    }
}

async function destinarDoEmbarque(idembarque, iditem, pendente) {
    embarqueContexto = { id: idembarque, iditem, pendente: pendente || 0 };
    ['quantMov', 'pesoRealMov', 'loteMov', 'validadeMov', 'observacaoMov', 'fotoMov'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    await processarLeitura(String(iditem), true);
    const alvo = document.getElementById('produtoEncontrado');
    if (alvo && alvo.style.display !== 'none') alvo.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

window.buscarEmbarqueRetorno = buscarEmbarqueRetorno;
window.destinarDoEmbarque = destinarDoEmbarque;
window.alternarTodosEmbarque = alternarTodosEmbarque;

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