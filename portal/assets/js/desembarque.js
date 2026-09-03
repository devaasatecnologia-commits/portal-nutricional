// ==========================================================================
// MÓDULO DE DESEMBARQUE (CONFERÊNCIA DE RECEBIMENTO)
// ==========================================================================

const state = AppState;
let ocAtual = null;

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
window.addEventListener('DOMContentLoaded', async function() {
    const barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
        barcodeInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                processarLeituraDesembarque(this.value);
                this.value = '';
            }
        });
    }
    
    // Fechar menu ao clicar fora
    document.addEventListener('click', function(e) {
        const menu = document.getElementById('menuOCs');
        const btn = document.getElementById('btnAbrirSelecao');
        if (menu && btn && !menu.contains(e.target) && !btn.contains(e.target)) {
            menu.style.display = 'none';
        }
    });
});

// ==========================================================================
// MENU DE ORDENS DE COMPRA
// ==========================================================================
function toggleMenuOCs() {
    const menu = document.getElementById('menuOCs');
    if (menu) {
        if (menu.style.display === 'none' || !menu.style.display) {
            menu.style.display = 'block';
            carregarListaOCs();
        } else {
            menu.style.display = 'none';
        }
    }
}

async function carregarListaOCs() {
    const container = document.getElementById('menuOCs');
    if (!container) return;
    
    try {
        const dados = await apiFetch('v1/desembarque/ordens-compra', 'GET');
        const ocs = Array.isArray(dados) ? dados : [];
        
        if (ocs.length === 0) {
            container.innerHTML = '<div class="p-4 text-center text-slate-400 text-sm">Nenhuma OC disponível.</div>';
            return;
        }
        
        let h = '';
        ocs.forEach(oc => {
            const corStatus = oc.status_conferencia == 2 ? '#f59e0b' : '#10b981';
            const statusTexto = oc.status_conferencia == 2 ? 'EM ANDAMENTO' : 'ABERTO';
            
            h += `<div onclick="selecionarOC('${oc.idoc}', '${(oc.fornecedor || '').replace(/'/g, "\\'")}')" 
                      style="padding: 15px; border-bottom: 1px solid #f1f5f9; cursor: pointer; background: white; transition: all 0.2s; border-left: 4px solid transparent;"
                      onmouseover="this.style.background='#f0fdf4'; this.style.borderLeft='4px solid #10b981';"
                      onmouseout="this.style.background='white'; this.style.borderLeft='4px solid transparent';">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-weight:800; color:#1e293b;">OC #${oc.idoc}</span>
                    <span style="font-size:0.65rem; font-weight:700; color:${corStatus}; background:${corStatus}15; padding:2px 8px; border-radius:4px;">${statusTexto}</span>
                </div>
                <span style="font-size:0.75rem; color:#64748b; display:block; margin-top:3px;">${oc.fornecedor || ''}</span>
                <span style="font-size:0.65rem; color:#94a3b8;">${oc.data_oc || ''} | R$ ${parseFloat(oc.valortotal || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</span>
            </div>`;
        });
        
        container.innerHTML = h;
    } catch (e) {
        container.innerHTML = '<div class="p-4 text-center text-rose-500 text-sm">Erro ao carregar OCs.</div>';
        console.error('Erro ao carregar OCs:', e);
    }
}

async function selecionarOC(id, fornecedor) {
    const menu = document.getElementById('menuOCs');
    if (menu) menu.style.display = 'none';
    
    document.getElementById('textoSelecao').innerHTML = `<b style="color:#10b981;">OC #${id}</b> - ${fornecedor || ''}`;
    document.getElementById('btnAbrirSelecao').style.borderColor = '#10b981';
    document.getElementById('selOC').value = id;
    
    Swal.fire({ title: 'Buscando itens...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        const resp = await apiFetch(`v1/desembarque/itens/${id}`, 'GET');
        const itens = Array.isArray(resp) ? resp : [];
        
        Swal.close();

        if (itens.length === 0) {
            Swal.fire({ icon: 'info', title: 'OC sem itens pendentes', text: 'Todos os itens já foram conferidos.', position: 'top' });
            return;
        }

        ocAtual = id;
        state.itens = itens;
        
        document.getElementById('areaOperacional').style.display = 'block';
        document.getElementById('label-oc').innerText = 'OC #' + id;
        
        renderDesembarque();
        
        const input = document.getElementById('barcode-input');
        if (input) {
            input.setAttribute('readonly', 'true');
            setTimeout(() => input.focus(), 500);
        }
    } catch (e) {
        Swal.close();
        console.error('Erro ao buscar OC:', e);
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao carregar itens da OC.', position: 'top' });
    }
}

// ==========================================================================
// RENDERIZAÇÃO DOS ITENS
// ==========================================================================
function renderDesembarque() {
    const listaAlvo = document.getElementById('listaItens');
    const btnFinalizar = document.getElementById('container-finalizar');
    if (!listaAlvo) return;

    const itens = state.itens || [];
    let concluidos = 0;
    let h = '';

    itens.forEach(i => {
        const saldo = parseFloat(i.saldo) || 0;
        const total = parseFloat(i.quant_oc) || 0;
        const concluido = saldo <= 0.01;
        
        if (concluido) concluidos++;

        const imgPath = i.path_foto_master ? i.path_foto_master.split('Fotos para o Site\\')[1] : null;
        const img = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/100x100?text=S/F';

        h += `<div class="item-card ${concluido ? 'concluido' : ''}" id="item-${i.cod_item}">
            <img src="${img}" class="prod-img" onerror="this.src='https://placehold.co/100x100?text=S/F'">
            <div class="item-info">
                <div class="item-name">${i.nome_item || 'Item ' + i.cod_item}</div>
                <div class="qty-row">
                    <div class="qty-tag">OC: ${parseFloat(total).toFixed(0)}</div>
                    <div class="qty-tag" style="color:${concluido ? 'var(--success)' : 'var(--danger)'}">
                        ${concluido ? '<i class="fa-solid fa-check"></i> OK' : 'FALTA: ' + parseFloat(saldo).toFixed(0)}
                    </div>
                </div>
                <div style="font-size:0.55rem; color:#94a3b8; margin-top:5px; display:flex; justify-content:space-between;">
                    <span>EAN: ${i.cod_barras || 'S/ COD'}</span>
                    <span>ID: ${i.cod_item}</span>
                    <span>REF: ${i.referencia || 'N/A'}</span>
                </div>
            </div>
        </div>`;
    });

    const total = itens.length;
    document.getElementById('contagem-itens-header').innerText = concluidos + '/' + total + ' ITENS';
    document.getElementById('resumo-conferidos').innerText = concluidos + '/' + total;
    document.getElementById('resumo-pendentes').innerText = (total - concluidos);

    if (total >= 1 && concluidos === total) {
        btnFinalizar.style.display = 'block';
        h = '<div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; text-align:center; font-weight:800; border:2px solid #10b981; margin-bottom:10px;"><i class="fa-solid fa-circle-check"></i> TODOS ITENS CONFERIDOS!</div>' + h;
    } else {
        btnFinalizar.style.display = 'none';
    }
    
    listaAlvo.innerHTML = h || '<div style="padding:40px; color:#94a3b8; text-align:center;">Nenhum item pendente.</div>';
}

// ==========================================================================
// PROCESSAMENTO DE LEITURA
// ==========================================================================
async function processarLeituraDesembarque(codigo) {
    if (!codigo || isProcessing) return;
    if (!ocAtual) {
        Swal.fire({ icon: 'warning', title: 'Selecione uma OC primeiro', position: 'top' });
        return;
    }
    
    isProcessing = true;
    const busca = codigo.toString().trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
    
    try {
        const resp = await apiFetch(`v1/desembarque/buscar-item/${ocAtual}?codigo=${busca}`, 'GET');
        
        if (resp.error) {
            new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
            await Swal.fire({ title: 'Não encontrado', text: resp.error || 'Item não pertence a esta OC ou sem saldo.', icon: 'error', timer: 2000, position: 'top', toast: true, showConfirmButton: false });
            isProcessing = false;
            return;
        }
        
        await exibirModalConferencia(resp);
        await carregarItens();
        
    } catch (e) {
        new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
        await Swal.fire({ title: 'Erro', text: 'Item não encontrado.', icon: 'error', timer: 2000, position: 'top', toast: true, showConfirmButton: false });
    } finally {
        isProcessing = false;
    }
}

/// ==========================================================================
// MODAL DE CONFERÊNCIA (COM FOTO + ENDEREÇAMENTO)
// ==========================================================================
async function exibirModalConferencia(item) {
    const saldo = parseFloat(item.saldo) || 0;
    const nomeItem = item.nome_item || 'Item';
    const imgPath = item.path_foto_master ? item.path_foto_master.split('Fotos para o Site\\')[1] : null;
    const fotoUrl = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/150x150?text=S/F';
    
    const dataPadrao = new Date();
    dataPadrao.setFullYear(dataPadrao.getFullYear() + 1);
    const validadePadrao = dataPadrao.toISOString().split('T')[0];

    window.scrollTo(0, 0);
    const el = document.getElementById('item-' + item.cod_item);
    if (el) el.classList.add('active');

    const res = await Swal.fire({
        title: 'Conferir Item',
        position: 'top',
        html: `
            <div style="display:flex; flex-direction:column; align-items:center;">
                <img src="${fotoUrl}" style="width:80px; height:80px; object-fit:contain; border-radius:10px; margin-bottom:10px; border:1px solid #eee;">
                <div style="font-weight:800; font-size:0.9rem; text-align:center; max-width:280px; color:#1e293b; margin-bottom:10px;">${nomeItem}</div>
                <div style="color:var(--danger); font-weight:800; font-size:1rem; margin-bottom:15px;">SALDO: ${saldo.toFixed(0)}</div>
                
                <div style="width:100%; margin-bottom:10px;">
                    <label style="display:block; text-align:left; font-size:0.7rem; font-weight:700; color:#64748b; margin-bottom:4px;">QUANTIDADE</label>
                    <input id="swalQuantidade" type="number" class="swal2-input" value="${saldo.toFixed(0)}" min="1" max="${saldo.toFixed(0)}"
                           style="margin:0; width:100%; height:50px; font-size:1.5rem;">
                </div>
                
                <div style="width:100%; margin-bottom:10px;">
                    <label style="display:block; text-align:left; font-size:0.7rem; font-weight:700; color:#64748b; margin-bottom:4px;">LOTE *</label>
                    <input id="swalLote" type="text" class="swal2-input" placeholder="Digite o número do lote"
                           style="margin:0; width:100%; height:50px; font-size:1.2rem;">
                </div>
                
                <div style="width:100%; margin-bottom:10px;">
                    <label style="display:block; text-align:left; font-size:0.7rem; font-weight:700; color:#64748b; margin-bottom:4px;">VALIDADE</label>
                    <input id="swalValidade" type="date" class="swal2-input" value="${validadePadrao}"
                           style="margin:0; width:100%; height:50px; font-size:1.2rem;">
                </div>
                
                <div style="width:100%; margin-bottom:10px; background:#f0fdf4; padding:10px; border-radius:10px; border:1px solid #bbf7d0;">
                    <label style="display:block; text-align:left; font-size:0.7rem; font-weight:700; color:#166534; margin-bottom:4px;">
                        <i class="fa-solid fa-map-pin"></i> ENDEREÇO (Seção)
                    </label>
              <select id="swalSecao" class="swal2-input" style="margin:0; width:100%; height:45px; font-size:1rem; background:white;">
    <option value="">Carregando seções...</option>
</select>
<select id="swalEndereco" class="swal2-input" style="margin-top:8px; width:100%; height:45px; font-size:1rem; background:white;">
    <option value="">Selecione a seção primeiro</option>
</select> </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '📸 Tirar Foto e Confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
        width: '95%',
  didOpen: async () => {
    document.getElementById('barcode-input').blur();
    
    // Carregar seções
    try {
        const secoes = await apiFetch('v1/desembarque/secoes', 'GET');
        const select = document.getElementById('swalSecao');
        if (Array.isArray(secoes) && secoes.length > 0) {
            select.innerHTML = '<option value="">Selecione a seção...</option>' +
                secoes.map(s => `<option value="${s.idsecao}">${s.descricao} (${s.sigla || s.idsecao})</option>`).join('');
        } else {
            select.innerHTML = '<option value="">Nenhuma seção disponível</option>';
        }
        
        // ✅ Evento para carregar endereços ao mudar a seção
        select.addEventListener('change', async function() {
            const idsecao = this.value;
            const enderecoSelect = document.getElementById('swalEndereco');
            
            if (!idsecao) {
                enderecoSelect.innerHTML = '<option value="">Selecione a seção primeiro</option>';
                return;
            }
            
            try {
                const enderecos = await apiFetch(`v1/desembarque/enderecos/${idsecao}`, 'GET');
                
                if (Array.isArray(enderecos) && enderecos.length > 0) {
                    enderecoSelect.innerHTML = '<option value="">Selecione o endereço...</option>' +
                        enderecos.map(e => `<option value="${e.idendereco}" data-sigla="${e.sigla}">
                            ${e.sigla} | Disp: ${e.disponivel} | Cap: ${e.capacidade}
                        </option>`).join('');
                } else {
                    enderecoSelect.innerHTML = '<option value="">Nenhum endereço disponível</option>';
                }
            } catch (e) {
                enderecoSelect.innerHTML = '<option value="">Erro ao carregar endereços</option>';
            }
        });
        
    } catch (e) {
        document.getElementById('swalSecao').innerHTML = '<option value="">Erro ao carregar</option>';
    }
    
    setTimeout(() => document.getElementById('swalQuantidade').focus(), 300);
},
        preConfirm: () => {
    const qtd = parseFloat(document.getElementById('swalQuantidade').value);
    const lote = document.getElementById('swalLote').value.trim();
    const validade = document.getElementById('swalValidade').value;
    const idsecao = document.getElementById('swalSecao').value;
    const idendereco = document.getElementById('swalEndereco').value;
    const enderecoSelect = document.getElementById('swalEndereco');
    const siglaEndereco = enderecoSelect.options[enderecoSelect.selectedIndex]?.dataset?.sigla || '';
    
    if (!qtd || qtd <= 0) { Swal.showValidationMessage('Quantidade inválida'); return false; }
    if (qtd > saldo) { Swal.showValidationMessage('Excede o saldo: ' + saldo.toFixed(0)); return false; }
    if (!lote) { Swal.showValidationMessage('O lote é obrigatório'); return false; }
    if (!idsecao) { Swal.showValidationMessage('Selecione uma seção'); return false; }
    if (!idendereco) { Swal.showValidationMessage('Selecione um endereço'); return false; }
    
    return { 
        quantidade: qtd, 
        lote: lote, 
        validade: validade, 
        idsecao: idsecao, 
        idendereco: idendereco,
        endereco: siglaEndereco 
    };
}
    });

    if (el) el.classList.remove('active');

    if (res.isConfirmed && res.value) {
        // 📸 TIRAR FOTO
        const fotoOK = await capturarFotoDesembarque(item.cod_item);
        
        if (!fotoOK) {
            Swal.fire({ icon: 'error', title: 'Foto obrigatória!', text: 'É necessário registrar uma foto do produto.', position: 'top' });
            return;
        }
        
        // Gravar no banco
        Swal.fire({ title: 'Gravando...', position: 'top', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
        
        try {
       const r = await apiFetch('v1/desembarque/confirmar', 'POST', {
    idoc: ocAtual,
    iditem: String(item.cod_item),
    quantidade: res.value.quantidade,
    lote: res.value.lote,
    validade: res.value.validade,
    idsecao: res.value.idsecao,
    idendereco: res.value.idendereco,   
    endereco: res.value.endereco,
    usuario: document.getElementById('user_nome')?.value || 'SISTEMA'
});
            
            Swal.close();
            
            if (r.success) {
                await Swal.fire({ icon: 'success', title: '✅ Recebido!', text: `Lote: ${res.value.lote} | Qtd: ${res.value.quantidade} | Local: ${res.value.endereco}`, timer: 2500, showConfirmButton: false, position: 'top' });
            } else {
                await Swal.fire({ icon: 'error', title: 'Erro', text: r.error || 'Erro ao gravar', position: 'top' });
            }
        } catch (e) {
            Swal.close();
            await Swal.fire({ icon: 'error', title: 'Erro na API', text: e.message, position: 'top' });
        }
    }
}

// ==========================================================================
// CAPTURA DE FOTO (DESEMBARQUE)
// ==========================================================================
async function capturarFotoDesembarque(iditem) {
    return new Promise((resolve) => {
        const item = state.itens.find(i => i.cod_item == iditem);
        const nomeItem = item ? (item.nome_item || item.descricao) : 'Item';
        
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.capture = 'environment';
        
        input.onchange = async () => {
            if (input.files && input.files[0]) {
                const file = input.files[0];
                
                if (file.size > 5 * 1024 * 1024) {
                    await Swal.fire({ icon: 'error', title: 'Foto muito grande!', text: 'Máximo 5MB.', position: 'top' });
                    resolve(false);
                    return;
                }
                
                // Preview
                const reader = new FileReader();
                reader.onload = async (e) => {
                    const preview = await Swal.fire({
                        title: 'Confirmar foto?',
                        html: `<img src="${e.target.result}" style="max-width:100%; max-height:250px; border-radius:10px;"><p style="font-weight:700; margin-top:8px;">${nomeItem}</p>`,
                        showCancelButton: true,
                        confirmButtonText: '✅ Sim, enviar',
                        cancelButtonText: '📷 Tirar outra',
                        confirmButtonColor: '#10b981',
                        position: 'top'
                    });
                    
                    if (preview.isConfirmed) {
                        // Upload
                        const formData = new FormData();
                        formData.append('foto', file);
                        formData.append('iditem', iditem);
                        formData.append('idusuario', getUserId());
                        
                        try {
                            const token = getAuthToken();
                            const resp = await fetch('/v1/desembarque/foto', {
                                method: 'POST',
                                headers: { 'Authorization': 'Bearer ' + token },
                                body: formData
                            });
                            const result = await resp.json();
                            
                            if (result.success) {
                                await Swal.fire({ icon: 'success', title: '✅ Foto registrada!', timer: 1500, showConfirmButton: false, position: 'top' });
                                resolve(true);
                            } else {
                                await Swal.fire({ icon: 'error', title: 'Erro ao salvar foto', position: 'top' });
                                resolve(false);
                            }
                        } catch (e) {
                            resolve(false);
                        }
                    } else if (preview.dismiss === Swal.DismissReason.cancel) {
                        const resultado = await capturarFotoDesembarque(iditem);
                        resolve(resultado);
                    } else {
                        resolve(false);
                    }
                };
                reader.readAsDataURL(file);
            } else {
                await Swal.fire({ icon: 'warning', title: '⚠️ Foto obrigatória!', text: 'É necessário registrar uma foto.', position: 'top', confirmButtonText: '📸 Tirar foto' });
                const resultado = await capturarFotoDesembarque(iditem);
                resolve(resultado);
            }
        };
        
        input.oncancel = async () => {
            await Swal.fire({ icon: 'warning', title: '⚠️ Foto obrigatória!', text: 'Você precisa registrar uma foto.', position: 'top', confirmButtonText: '📸 Abrir câmera' });
            const resultado = await capturarFotoDesembarque(iditem);
            resolve(resultado);
        };
        
        input.click();
        
        setTimeout(() => resolve(false), 120000);
    });
}

// ==========================================================================
// CARREGAR ITENS ATUALIZADOS
// ==========================================================================
async function carregarItens() {
    if (!ocAtual) return;
    try {
        const resp = await apiFetch(`v1/desembarque/itens/${ocAtual}`, 'GET');
        state.itens = Array.isArray(resp) ? resp : [];
        renderDesembarque();
        
        if (state.itens.length === 0) {
            document.getElementById('container-finalizar').style.display = 'block';
        }
    } catch (e) {
        console.error('Erro ao recarregar itens:', e);
    }
}

// ==========================================================================
// FINALIZAR CONFERÊNCIA
// ==========================================================================
async function finalizarConferencia() {
    const res = await Swal.fire({
        title: 'Finalizar Conferência?',
        text: `Todos os itens da OC #${ocAtual} foram conferidos?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Sim, Finalizar',
        position: 'top'
    });

    if (res.isConfirmed) {
        try {
            const r = await apiFetch(`v1/desembarque/finalizar/${ocAtual}`, 'POST', {
                usuario: document.getElementById('user_nome')?.value || 'SISTEMA'
            });
            
            if (r.success) {
                await Swal.fire({ title: 'FINALIZADO!', text: 'Conferência concluída.', icon: 'success', timer: 2500, position: 'top' });
                location.reload();
            }
        } catch (e) {
            Swal.fire({ title: 'Erro', text: e.message, icon: 'error', position: 'top' });
        }
    }
}

// ==========================================================================
// CÂMERA
// ==========================================================================
function toggleCamera() {
    const div = document.getElementById('reader');
    if (div.style.display === 'block') {
        if (scanner) scanner.stop();
        div.style.display = 'none';
    } else {
        window.scrollTo(0, 0);
        document.getElementById('barcode-input').blur();
        div.style.display = 'block';
        scanner = new Html5Qrcode('reader');
        scanner.start({ facingMode: 'environment' }, { fps: 20, qrbox: 260 }, (txt) => {
            scanner.stop();
            div.style.display = 'none';
            processarLeituraDesembarque(txt);
        }).catch(() => { div.style.display = 'none'; });
    }
}

// Refocar input ao clicar fora
document.addEventListener('click', (e) => {
    if (!['BUTTON', 'SELECT', 'INPUT'].includes(e.target.tagName) && !document.querySelector('.swal2-container')) {
        const input = document.getElementById('barcode-input');
        if (input && ocAtual) {
            input.setAttribute('readonly', 'true');
            input.focus();
        }
    }
});

// ==========================================================================
// EXPORTAÇÃO GLOBAL
// ==========================================================================
window.toggleMenuOCs = toggleMenuOCs;
window.selecionarOC = selecionarOC;
window.toggleCamera = toggleCamera;
window.finalizarConferencia = finalizarConferencia;