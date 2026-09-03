// ==========================================================================
// MÓDULO DE SEPARAÇÃO (COLETOR OFICIAL)
// ==========================================================================

// API_TOKEN legado (será usado apenas se não houver JWT)
var API_TOKEN = 'xoUM?va.JNG93v)@#i9FyH@B6n0}H4.yst%s8zV8M}xc+ZrFAz5:y6T07HxyYGE~';

// Estado específico da separação (usa AppState global)
const state = AppState;


// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
window.onload = async function() {
    try {
        const dados = await apiFetch('v1/separacao/embarques-pendentes');
        state.embarquesDisponiveis = Array.isArray(dados) ? dados : [];
        montarMenuInterno();

        const barcodeInput = document.getElementById('barcode-input');
        if (barcodeInput) {
            barcodeInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    processarLeitura(this.value);
                    this.value = '';
                }
            });
        }
    } catch (e) {
        console.error('Erro ao buscar embarques', e);
        showToast('Erro ao carregar embarques', 'error');
    }
};

// ==========================================================================
// FUNÇÕES DE UI (MENU, SELEÇÃO, ORDEM)
// ==========================================================================
function toggleMenuEmbarques() {
    const menu = document.getElementById('menuEmbarques');
    if (menu) {
        menu.style.display = (menu.style.display === 'none') ? 'block' : 'none';
    }
}

function montarMenuInterno() {
    const container = document.getElementById('menuEmbarques');
    if (!container) return;
    
    let h = '';
    state.embarquesDisponiveis.forEach(e => {
        const s = e.status_logistico;
        if (s === 'CARREGADO') return;

        let cor = '#1e293b', label = 'PENDENTE';
        if (s === 'SEPARACAO') { cor = '#f59e0b'; label = 'EM SEPARAÇÃO'; }
        else if (s === 'CONCLUIDO') { cor = '#10b981'; label = 'PRONTO'; }

        h += `<div onclick="selecionarEmbarqueManual('${e.idembarque}', '${label}', '${cor}')" style="padding: 15px; border-bottom: 1px solid #f1f5f9; color: ${cor}; font-weight: 800; cursor: pointer; background: white;">
                <span style="font-size: 0.65rem; display: block; opacity: 0.7;">${label}</span>
                #${e.idembarque} - ${e.rota}
              </div>`;
    });
    container.innerHTML = h || '<div style="padding:15px; color:#94a3b8;">Nenhum embarque.</div>';
}

async function selecionarEmbarqueManual(id, label, cor) {
    const jaPronto = (label === 'PRONTO' || label === 'CONCLUIDO');
    if (jaPronto) {
        const res = await Swal.fire({
            title: 'Embarque já separado!',
            text: 'Deseja abrir para estornar algum produto?',
            icon: 'question',
            position: 'top',
            showCancelButton: true,
            confirmButtonText: 'Sim, Abrir',
            cancelButtonText: 'Não, Sair',
            confirmButtonColor: '#274036',
            cancelButtonColor: '#ef4444'
        });
        if (!res.isConfirmed) return;
    }

    const sel = document.getElementById('selEmbarque');
    sel.innerHTML = `<option value="${id}">${id}</option>`;
    sel.value = id;
    document.getElementById('textoSelecao').innerHTML = `<b style="color:${cor}">[${label}] #${id}</b>`;
    document.getElementById('btnAbrirSelecao').style.borderColor = cor;
    document.getElementById('menuEmbarques').style.display = 'none';

    try {
       const dados = await apiFetch('v1/separacao/resumo/' + id, 'GET');
        state.resumo = dados;
        document.getElementById('resumo-peso').innerText = Math.floor(state.resumo.totalpesobruto || 0) + 'kg';
        document.getElementById('resumo-pedidos').innerText = state.resumo.qt_pedido || 0;
    } catch (e) {
        console.error('Erro resumo', e);
    }

    iniciarOperacao();
}

function iniciarOperacao() {
    state.embarque = document.getElementById('selEmbarque').value;
    if (!state.embarque) {
        document.getElementById('areaOperacional').style.display = 'none';
        return;
    }
    document.getElementById('areaOperacional').style.display = 'block';
    document.getElementById('label-embarque').innerText = 'CARGA #' + state.embarque;
    carregarLista();
}

function alterarOrdem(o) {
    state.ordem = o;
    document.getElementById('btnASC').classList.toggle('active', o === 'ASC');
    document.getElementById('btnDESC').classList.toggle('active', o === 'DESC');
    carregarLista();
}

// ==========================================================================
// CARREGAMENTO E RENDERIZAÇÃO DOS ITENS
// ==========================================================================
async function carregarLista() {
    try {
        const dados = await apiFetch(`v1/separacao/itens/${state.embarque}`, 'GET', { ordem: state.ordem });
        state.itens = Array.isArray(dados) ? dados : [];
        render();
        isProcessing = false;

        const input = document.getElementById('barcode-input');
        if (input) {
            input.setAttribute('readonly', 'true');
            input.focus();
        }
    } catch (e) {
        Swal.fire({ title: 'Erro', text: 'Falha ao sincronizar lista.', icon: 'error', position: 'top' });
    }
}

function render() {
    const listaAlvo = document.getElementById('listaItens');
    const btnFinalizar = document.getElementById('container-finalizar');
    
    const itensOrdenados = [...state.itens].sort((a, b) => {
        const salA = parseFloat(a.saldo_restante);
        const salB = parseFloat(b.saldo_restante);
        return (salA >= 0.0001 ? 0 : 1) - (salB >= 0.0001 ? 0 : 1);
    });

    let h = '';
    let pendentesCount = 0;
    let concluidosCount = 0;
    let ultimaSecao = null;

    itensOrdenados.forEach(i => {
        const saldo = parseFloat(i.saldo_restante) || 0;
        const saldoItem = parseFloat(i.saldoitem) || 0;
        const concluido = !(saldo >= 0.0001);
        
        if (!concluido) pendentesCount++; else concluidosCount++;

        if (i.idsecao !== ultimaSecao) {
            if (ultimaSecao !== null) h += '<div class="section-divider"></div>';
            ultimaSecao = i.idsecao;
        }

        // CORREÇÃO: usa path_foto_master em vez de foto
        const imgPath = i.path_foto_master ? i.path_foto_master.split('Fotos para o Site\\')[1] : null;
        const img = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/100x100?text=S/F';
        
        // CORREÇÃO: usa descricao (fallback para nome_item)
        const nomeProduto = i.descricao || i.nome_item || 'Produto';

        h += `<div class="item-card ${concluido ? 'concluido' : ''}" id="item-${i.cod_item}">
            <img src="${img}" class="prod-img" loading="lazy" onerror="this.src='https://placehold.co/100x100?text=S/F'">
            <div class="item-info">
                <div class="item-name">${nomeProduto}</div>
                <div class="qty-row">
                    <div class="qty-tag">TOTAL: ${Number(parseFloat(i.quant_embarque).toFixed(3))}</div>
                    <div class="qty-tag" style="color:${concluido ? 'var(--success)' : 'var(--danger)'}">
                        ${concluido ? '<i class="fa-solid fa-check"></i> OK' : 'FALTA: ' + Number(saldo.toFixed(3))}
                    </div>
                    <div class="qty-tag">Saldo Estoque: ${Number(parseFloat(saldoItem).toFixed(3))}</div>
                </div>
                <div style="font-size:0.55rem; color:#94a3b8; margin-top:5px; display:flex; justify-content:space-between;">
                    <span>EAN: ${i.cod_barras || 'S/ COD'}</span>
                    <span>ID: ${i.cod_item}</span>
                </div>
            </div>
        </div>`;
    });

    const total = state.itens.length;
    document.getElementById('contagem-itens-header').innerText = concluidosCount + '/' + total + ' ITENS';
    document.getElementById('resumo-total-itens').innerText = concluidosCount + '/' + total;

    const tudoPronto = !(pendentesCount >= 1);
    btnFinalizar.style.display = (total >= 1 && tudoPronto) ? 'block' : 'none';

    if (total >= 1 && tudoPronto) {
        h = '<div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; text-align:center; font-weight:800; border:2px solid #10b981; margin-bottom:10px;">🎉 CONFERÊNCIA FINALIZADA!</div>' + h;
    }

    listaAlvo.innerHTML = h || '<div style="text-align:center;padding:40px; color:#94a3b8;">Nenhum item pendente.</div>';
}

// ==========================================================================
// PROCESSAMENTO DE LEITURA E CONFIRMAÇÃO
// ==========================================================================
async function processarLeitura(codigo) {
    if (!codigo || isProcessing) return;
    isProcessing = true;

    const busca = codigo.toString().trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
    const item = state.itens.find(i => {
        if (i.cod_item && i.cod_item.toString() === busca) return true;
        if (i.todos_codigos && i.todos_codigos !== 'SEM_BARRA') {
            const listaCods = i.todos_codigos.split(',').map(c => c.trim().toUpperCase().replace(/[^A-Z0-9]/g, ''));
            return listaCods.includes(busca);
        }
        return false;
    });

    if (!item) {
        new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
        await Swal.fire({ title: 'Não encontrado', text: 'Código não pertence a nenhum item.', icon: 'error', timer: 1500, position: 'top', toast: true, showConfirmButton: false });
        isProcessing = false;
        return;
    }

    const saldoNum = parseFloat(item.saldo_restante);
    if (!(saldoNum >= 0.0001)) {
        isProcessing = false;
        if (parseFloat(item.ja_carregado || 0) > 0) {
            Swal.fire({ title: 'Bloqueado', text: 'Item já carregado!', icon: 'error', position: 'top' });
            return;
        }
        confirmarEstorno(item.cod_item, item.nome_item);
        return;
    }

    window.scrollTo(0, 0);
    const el = document.getElementById('item-' + item.cod_item);
    if (el) el.classList.add('active');

const imgPath = item.path_foto_master ? item.path_foto_master.split('Fotos para o Site\\')[1] : null;
const fotoUrl = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/150x150?text=S/F';
 const saldoFormatado = Number(saldoNum.toFixed(3));

    const res = await Swal.fire({
        title: 'Conferir Separação',
        position: 'top',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
            <img src="${fotoUrl}" style="width:80px; height:80px; object-fit:contain; border-radius:10px; margin-bottom:5px;">
            <div style="font-weight:800; font-size:0.85rem; color:#274036; text-align:center;">${item.nome_item}</div>
            <div style="color:#ef4444; font-weight:800; font-size:1rem; margin-top:8px;">FALTA SEPARAR: ${saldoFormatado}</div>
        </div>`,
        input: 'text',
        inputValue: saldoFormatado,
        inputAttributes: { inputmode: 'decimal' },
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Sair',
        confirmButtonColor: '#10b981',
        width: '90%',
        didOpen: () => {
            const inputSwal = Swal.getInput();
            inputSwal.style.width = '65%';
            inputSwal.style.margin = '10px auto';
            inputSwal.style.textAlign = 'center';
            inputSwal.style.fontWeight = '900';
            inputSwal.style.fontSize = '1.8rem';
            document.getElementById('barcode-input').blur();
        },
        preConfirm: (value) => {
            const parsed = parseFloat(String(value).replace(',', '.'));
            if (isNaN(parsed) || parsed <= 0) {
                Swal.showValidationMessage('Quantidade inválida');
                return false;
            }
            if (parsed > (saldoFormatado + 0.001)) {
                Swal.showValidationMessage('Quantidade maior que o faltante!');
                return false;
            }
            return parsed;
        }
    });

    if (res.isConfirmed && res.value) {
        await enviarConfirmacao(item.cod_item, res.value);
    } else {
        isProcessing = false;
        if (el) el.classList.remove('active');
    }
}

async function enviarConfirmacao(iditem, qtd) {
    const dados = {
        iditem: String(iditem),
        idembarque: String(state.embarque),
        qtd: parseFloat(qtd),
        idusuario: getUserId()
    };

    window.scrollTo({ top: 0, behavior: 'instant' });
    Swal.fire({ title: 'Gravando...', position: 'top', allowOutsideClick: false, didOpen: () => Swal.showLoading() });

    try {
        const resultado = await apiFetch('v1/separacao/confirmar', 'POST', dados);
        if (resultado.success) {
            Swal.close();
            await carregarLista();
            isProcessing = false;
            Swal.fire({ icon: 'success', title: 'Gravado!', position: 'top', timer: 1000, showConfirmButton: false });
        } else {
            throw new Error(resultado.error || 'Erro ao processar');
        }
    } catch (e) {
        isProcessing = false;
        Swal.fire({ title: 'Atenção', text: e.message, icon: 'warning', position: 'top', width: '95%' });
        await carregarLista();
    }
}

async function confirmarEstorno(iditem, nome) {
    window.scrollTo(0, 0);
    document.getElementById('barcode-input').blur();
    const res = await Swal.fire({
        title: 'Item já conferido!',
        text: `Deseja estornar ${nome}?`,
        icon: 'warning',
        position: 'top',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, Estornar',
        width: '95%'
    });
    if (res.isConfirmed) {
        Swal.fire({ title: 'Estornando...', position: 'top', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        try {
            const resultado = await apiFetch(`v1/separacao/estornar/${iditem}/${state.embarque}`, 'DELETE');
            if (resultado.success) {
                await carregarLista();
                Swal.fire({ title: 'Sucesso', icon: 'success', timer: 1500, position: 'top', showConfirmButton: false });
            } else {
                throw new Error(resultado.error);
            }
        } catch (e) {
            Swal.fire({ title: 'Erro', text: e.message, icon: 'error', position: 'top' });
        }
    }
    isProcessing = false;
}

async function finalizarSeparacao() {
    const res = await Swal.fire({
        title: 'Finalizar Operação?',
        text: 'Marcar este embarque como SEPARADO?',
        icon: 'question',
        position: 'top',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Sim, Finalizar'
    });

    if (res.isConfirmed) {
        try {
            const resultado = await apiFetch(`v1/separacao/finalizar/${state.embarque}`, 'POST', { idusuario: getUserId() });
            if (resultado.success) {
                await Swal.fire({ title: 'EMBARQUE SEPARADO', text: 'Aguardando carregamento.', icon: 'success', position: 'top', timer: 2000, showConfirmButton: false });
                location.reload();
            } else {
                throw new Error(resultado.error || 'Erro ao finalizar');
            }
        } catch (e) {
            Swal.fire({ title: 'Erro', text: e.message, icon: 'error', position: 'top' });
        }
    }
}

// ==========================================================================
// CÂMERA E SINCRONISMO
// ==========================================================================
function toggleCamera() {
    const div = document.getElementById('reader');
    if (div.style.display === 'block') {
        fecharCamera();
    } else {
        window.scrollTo(0, 0);
        document.getElementById('barcode-input').blur();
        div.style.display = 'block';
        scanner = new Html5Qrcode('reader');
        scanner.start({ facingMode: 'environment' }, { fps: 20, qrbox: 260 }, (txt) => {
            fecharCamera();
            processarLeitura(txt);
        }).catch(() => fecharCamera());
    }
}

function fecharCamera() {
    const div = document.getElementById('reader');
    if (scanner) {
        scanner.stop().then(() => scanner = null).catch(() => {});
    }
    div.style.display = 'none';
}

document.addEventListener('click', (e) => {
    const input = document.getElementById('barcode-input');
    if (!['BUTTON', 'SELECT', 'INPUT'].includes(e.target.tagName) && !document.querySelector('.swal2-container')) {
        if (input) {
            input.setAttribute('readonly', 'true');
            input.focus();
        }
    }
});

// Sincronismo automático
setInterval(async () => {
    if (!state.embarque || isProcessing || (typeof Swal !== 'undefined' && Swal.isVisible())) return;
    try {
        const resposta = await apiFetch(`v1/separacao/itens/${state.embarque}`, 'GET', { ordem: state.ordem, v: Date.now() });
        if (Array.isArray(resposta) && JSON.stringify(resposta) !== JSON.stringify(state.itens)) {
            state.itens = resposta;
            render();
        }
    } catch (e) {}
}, 5000);

// ==========================================================================
// EXPORTAÇÃO DE FUNÇÕES GLOBAIS (necessárias para onclick no HTML)
// ==========================================================================
window.toggleMenuEmbarques = toggleMenuEmbarques;
window.selecionarEmbarqueManual = selecionarEmbarqueManual;
window.alterarOrdem = alterarOrdem;
window.toggleCamera = toggleCamera;
window.finalizarSeparacao = finalizarSeparacao;