// ==========================================================================
// MÓDULO DE CARREGAMENTO (COLETOR OFICIAL)
// ==========================================================================

// API_TOKEN legado (será usado apenas se não houver JWT)
var API_TOKEN = 'xoUM?va.JNG93v)@#i9FyH@B6n0}H4.yst%s8zV8M}xc+ZrFAz5:y6T07HxyYGE~';

// Estado específico do carregamento
const state = {
    embarque: '',
    ordem: 'ASC',
    itens: [],
    embarquesDisponiveis: [],
    resumo: {}
};

// Variáveis globais (sem duplicação)
let docaSelecionada = null;

let fotoInputElement = null;

// ==========================================================================
// SELEÇÃO DE DOCA
// ==========================================================================
function selecionarDoca(doca) {
    docaSelecionada = doca;
    
    // Resetar todos os botões
    document.querySelectorAll('.doca-btn').forEach(btn => {
        btn.classList.remove('bg-blue-500', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-600');
    });
    
    // Destacar botão selecionado
    const btnMap = { 'DOCA 1': 'btnDoca1', 'DOCA 2': 'btnDoca2', 'DOCA 3': 'btnDoca3' };
    const btnId = btnMap[doca];
    const btn = document.getElementById(btnId);
    if (btn) {
        btn.classList.remove('bg-slate-100', 'text-slate-600');
        btn.classList.add('bg-blue-500', 'text-white');
    }
    
    // Mostrar confirmação
    document.getElementById('nomeDocaSelecionada').innerText = doca;
    document.getElementById('docaSelecionada').classList.remove('hidden');
    
    // Focar no input
    setTimeout(() => {
        const input = document.getElementById('barcode-input');
        if (input) input.focus();
    }, 300);
}

// ==========================================================================
// FUNÇÕES DE API (usando o mesmo padrão do separacao.js)
// ==========================================================================
async function apiFetch(endpoint, method = 'GET', body = null) {
    const token = localStorage.getItem('authToken') || localStorage.getItem('token');
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
        console.error('[Carregamento] Token inválido ou expirado');
        window.location.href = '/portal/login.php?redirect=carregamento';
        throw new Error('Sessão expirada');
    }
    
    return response.json();
}

function getUserId() {
    const el = document.getElementById('user_id');
    if (el && el.value && el.value !== '0' && el.value !== '') {
        return parseInt(el.value);
    }
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    return userData.uid || 0;
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

async function comprimirImagemRapida(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
                
                // Dimensão máxima para celular (fotos de 12-48MP)
                const maxDim = 1280;
                
                if (width > maxDim) {
                    height = (height * maxDim) / width;
                    width = maxDim;
                }
                if (height > maxDim) {
                    width = (width * maxDim) / height;
                    height = maxDim;
                }
                
                canvas.width = width;
                canvas.height = height;
                
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // Qualidade 0.7 - bom equilíbrio para fotos de celular
                canvas.toBlob((blob) => {
                    const compressedFile = new File([blob], 'foto.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    
                    console.log(`📸 Compressão: ${(file.size / 1024 / 1024).toFixed(2)}MB → ${(compressedFile.size / 1024 / 1024).toFixed(2)}MB`);
                    resolve(compressedFile);
                }, 'image/jpeg', 0.7);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// ==========================================================================
// UPLOAD DE FOTO - RÁPIDO E CONFIÁVEL
// ==========================================================================
async function uploadFotoRapida(file, iditem, nomeItem, idCarregamento) {
    Swal.fire({
        title: '📤 Enviando foto...',
        text: nomeItem,
        allowOutsideClick: false,
        showConfirmButton: false,
        didOpen: () => Swal.showLoading(),
        position: 'top',
        toast: true,
        timer: 10000
    });
    
    const formData = new FormData();
    formData.append('foto', file);
    formData.append('idembarque', state.embarque);
    formData.append('iditem', iditem);
    formData.append('idusuario', getUserId());
    formData.append('doca', docaSelecionada);
    formData.append('id_carregamento', idCarregamento);  // ← NOVO
    
    try {
        const token = getAuthToken();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 20000);
        
        const resp = await fetch('/v1/carregamento/foto', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: formData,
            signal: controller.signal
        });
        
        clearTimeout(timeoutId);
        Swal.close();
        
        const result = await resp.json();
        
        if (result.success) {
            return true;
        } else {
            await Swal.fire({
                icon: 'error',
                title: '❌ Erro',
                text: result.error || 'Falha ao salvar foto',
                toast: true,
                position: 'top',
                timer: 2000,
                showConfirmButton: false
            });
            return false;
        }
    } catch (e) {
        Swal.close();
        console.error('Upload error:', e);
        
        let mensagem = 'Erro de conexão. Verifique sua internet.';
        if (e.name === 'AbortError') mensagem = 'Tempo limite excedido.';
        
        await Swal.fire({
            icon: 'error',
            title: '❌ Falha',
            text: mensagem,
            toast: true,
            position: 'top',
            timer: 2500,
            showConfirmButton: false
        });
        return false;
    }
}

// ==========================================================================
// CAPTURAR FOTO - OTIMIZADA PARA CELULAR
// ==========================================================================
async function capturarFoto(iditem, idCarregamento) {
    return new Promise((resolve) => {
        const item = state.itens.find(i => i.cod_item == iditem);
        const nomeItem = item ? (item.descricao || item.nome_item) : 'Item';
        
        if (!fotoInputElement) {
            fotoInputElement = document.createElement('input');
            fotoInputElement.type = 'file';
            fotoInputElement.accept = 'image/jpeg,image/jpg,image/png,image/webp';
            fotoInputElement.capture = 'environment';
        }
        
        const input = fotoInputElement;
        
        const timeoutId = setTimeout(() => {
            if (input.value) input.value = '';
            console.warn('Timeout ao capturar foto para item:', iditem);
            resolve(false);
        }, 60000);
        
        input.onchange = async () => {
            clearTimeout(timeoutId);
            
            if (!input.files || !input.files[0]) {
                await Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Foto obrigatória',
                    text: 'É necessário registrar uma foto do carregamento',
                    confirmButtonText: '📸 Tirar foto',
                    confirmButtonColor: '#274036'
                });
                const resultado = await capturarFoto(iditem, idCarregamento);
                resolve(resultado);
                return;
            }
            
            const file = input.files[0];
            input.value = '';
            
            if (file.size > 15 * 1024 * 1024) {
                await Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Foto de alta resolução',
                    html: `Sua foto tem <strong>${(file.size / 1024 / 1024).toFixed(1)}MB</strong><br>Vamos comprimir automaticamente para envio.`,
                    confirmButtonText: 'OK',
                    confirmButtonColor: '#f59e0b',
                    timer: 2000,
                    timerProgressBar: true
                });
            }
            
            Swal.fire({
                title: '📸 Processando foto...',
                text: 'Comprimindo imagem para envio',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
            
            let fileToUpload = await comprimirImagemRapida(file);
            Swal.close();
            
            const sucesso = await uploadFotoRapida(fileToUpload, iditem, nomeItem, idCarregamento);
            resolve(sucesso);
        };
        
        input.oncancel = async () => {
            clearTimeout(timeoutId);
            await Swal.fire({
                icon: 'warning',
                title: '⚠️ Foto obrigatória',
                text: 'Você precisa registrar uma foto para continuar',
                confirmButtonText: '📸 Abrir câmera',
                confirmButtonColor: '#f59e0b'
            });
            const resultado = await capturarFoto(iditem, idCarregamento);
            resolve(resultado);
        };
        
        input.click();
    });
}

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
window.onload = async function() {
    try {
        console.log('[Carregamento] Buscando embarques...');
        const dados = await apiFetch('v1/carregamento/embarques');
        console.log('[Carregamento] Embarques recebidos:', dados);
        
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
        if (e.status_logistico === 'CARREGADO') return;

        h += `<div onclick="selecionarEmbarqueManual('${e.idembarque}', 'PRONTO', '#10b981')" style="padding: 15px; border-bottom: 1px solid #f1f5f9; color: #10b981; font-weight: 800; cursor: pointer; background: white;">
                <span style="font-size: 0.65rem; display: block; opacity: 0.7;">NF GERADA</span>
                #${e.idembarque} - ${e.rota}
              </div>`;
    });
    container.innerHTML = h || '<div style="padding:15px; color:#94a3b8;">Nenhum embarque com NF pronta.</div>';
}

async function selecionarEmbarqueManual(id, label, cor) {
    const menu = document.getElementById('menuEmbarques');
    if (menu) menu.style.display = 'none';

    const jaCarregado = (label === 'CARREGADO' || label === 'FINALIZADO');
    if (jaCarregado) {
        const res = await Swal.fire({
            title: 'Embarque já carregado!',
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
    if (sel) {
        sel.innerHTML = `<option value="${id}">${id}</option>`;
        sel.value = id;
    }
    
    document.getElementById('textoSelecao').innerHTML = `<b style="color:${cor}">[${label}] #${id}</b>`;
    document.getElementById('btnAbrirSelecao').style.borderColor = cor;
    
    try {
       const dados = await apiFetch('v1/carregamento/resumo/' + id, 'GET');
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
    
    const divDoca = document.getElementById('selecaoDoca');
    if (divDoca) divDoca.style.display = 'block';
    
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
    if (!state.embarque) return;
    try {
        const dados = await apiFetch(`v1/carregamento/itens/${state.embarque}`, 'GET', { ordem: state.ordem });
        state.itens = Array.isArray(dados) ? dados : [];
        render();
        isProcessing = false;

        const input = document.getElementById('barcode-input');
        if (input) {
            input.setAttribute('readonly', 'true');
            input.focus();
        }
    } catch (e) {
        console.error('Erro na lista:', e);
    }
}

function render() {
    const listaAlvo = document.getElementById('listaItens');
    const btnFinalizar = document.getElementById('container-finalizar');
    if (!listaAlvo) return;

    const itensOrdenados = [...state.itens].sort((a, b) => {
        const libA = parseInt(a.pode_carregar) || 0;
        const libB = parseInt(b.pode_carregar) || 0;
        const ja_carA = parseFloat(a.ja_carregado) || 0;
        const totalA = parseFloat(a.quant_embarque) || 0;
        const concA = (ja_carA >= (totalA - 0.01));
        const ja_carB = parseFloat(b.ja_carregado) || 0;
        const totalB = parseFloat(b.quant_embarque) || 0;
        const concB = (ja_carB >= (totalB - 0.01));

        if (libA !== libB) return libB - libA;
        return (concA ? 1 : 0) - (concB ? 1 : 0);
    });

    let h = '';
    let concluidosCarga = 0;
    let ultimaSecao = null;

    itensOrdenados.forEach(i => {
        const ja_car = parseFloat(i.ja_carregado) || 0;
        const ja_sep = parseFloat(i.ja_separado) || 0;
        const total = parseFloat(i.quant_embarque) || 0;
        const liberado = (parseInt(i.pode_carregar) === 1);
        const concluido = (ja_car >= total - 0.01);
        
        if (concluido) concluidosCarga++;
        
        if (i.idsecao !== ultimaSecao) {
            if (ultimaSecao !== null) h += '<div class="section-divider"></div>';
            ultimaSecao = i.idsecao;
        }

        const imgPath = i.path_foto_master ? i.path_foto_master.split('Fotos para o Site\\')[1] : null;
        const img = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/100x100?text=S/F';

        h += `<div class="item-card ${concluido ? 'concluido' : ''} ${!liberado ? 'bloqueado' : ''}" id="item-${i.cod_item}">
            <img src="${img}" class="prod-img" onerror="this.src='https://placehold.co/100x100?text=S/F'">
            <div class="item-info">
                <div class="item-name">${!liberado ? '<i class="fa-solid fa-lock"></i> ' : ''}${i.descricao || i.nome_item}</div>
                <div class="qty-row">
                    <div class="qty-tag">SEP: ${Math.floor(ja_sep)}</div>
                    <div class="qty-tag" style="color:${concluido ? 'var(--success)' : 'var(--danger)'}">
                        ${concluido ? '<i class="fa-solid fa-check"></i> OK' : 'FALTA: ' + Math.floor(ja_sep - ja_car)}
                    </div>
                </div>
                <div style="font-size:0.55rem; color:#94a3b8; margin-top:5px; display:flex; justify-content:space-between;">
                    <span>EAN: ${i.cod_barras || 'S/ COD'}</span>
                    <span>ID: ${i.cod_item}</span>
                    ${!liberado ? '<span style="color:var(--danger); font-weight:800;">AGUARDANDO SEPARAÇÃO</span>' : ''}
                </div>
            </div>
        </div>`;
    });

    const total = state.itens.length;
    document.getElementById('contagem-itens-header').innerText = concluidosCarga + '/' + total + ' ITENS';
    document.getElementById('resumo-total-itens').innerText = concluidosCarga + '/' + total;

    if (total >= 1 && concluidosCarga === total) {
        btnFinalizar.style.display = 'block';
        h = '<div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; text-align:center; font-weight:800; border:2px solid #10b981; margin-bottom:10px;"><i class="fa-solid fa-circle-check"></i> TUDO PRONTO!</div>' + h;
    } else {
        btnFinalizar.style.display = 'none';
    }
    
    listaAlvo.innerHTML = h || '<div style="padding:40px; color:#94a3b8; text-align:center;">Nenhum item pendente.</div>';
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
        await Swal.fire({ 
            title: 'Não encontrado', 
            text: 'Item não pertence a este embarque ou código inválido.', 
            icon: 'error', 
            timer: 2000, 
            position: 'top', 
            toast: true, 
            showConfirmButton: false 
        });
        isProcessing = false;
        return;
    }

    if (parseInt(item.pode_carregar) !== 1) {
        new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
        await Swal.fire({ 
            title: 'Aguarde!', 
            text: 'Este item ainda não foi totalmente separado pela logística.', 
            icon: 'warning', 
            position: 'top' 
        });
        isProcessing = false;
        return;
    }

    const ja_car = parseFloat(item.ja_carregado) || 0;
    const ja_sep = parseFloat(item.ja_separado) || 0;
    const falta = Number((ja_sep - ja_car).toFixed(4));
    
    if (falta <= 0.0001) {
        isProcessing = false;
        confirmarEstornoCarregamento(item.cod_item, item.descricao || item.nome_item);
        return;
    }

    window.scrollTo(0, 0);
    const el = document.getElementById('item-' + item.cod_item);
    if (el) el.classList.add('active');

    const imgPath = item.path_foto_master ? item.path_foto_master.split('Fotos para o Site\\')[1] : null;
    const fotoUrl = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/150x150?text=S/F';

    const res = await Swal.fire({
        title: 'Confirmar Carga',
        position: 'top',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
            <img src="${fotoUrl}" style="width:100px; height:100px; object-fit:contain; border-radius:10px; margin-bottom:10px; border:1px solid #eee;">
            <div style="font-weight:800; font-size:0.9rem; text-align:center; max-width:250px; color:#1e293b;">${item.descricao || item.nome_item}</div>
            <div style="color:var(--danger); font-weight:800; font-size:1rem; margin-top:8px;">SALDO DISPONÍVEL: ${Number(falta.toFixed(3))}</div>
        </div>`,
        input: 'text',
        inputValue: Number(falta.toFixed(3)),
        inputAttributes: { inputmode: 'decimal' },
        showCancelButton: true,
        confirmButtonText: 'Confirmar Carga',
        cancelButtonText: 'Sair',
        confirmButtonColor: '#10b981',
        width: '90%',
        didOpen: () => {
            const inputSwal = Swal.getInput();
            inputSwal.style.width = '70%';
            inputSwal.style.margin = '15px auto';
            inputSwal.style.textAlign = 'center';
            inputSwal.style.fontWeight = '900';
            inputSwal.style.fontSize = '1.8rem';
            inputSwal.style.color = '#1e293b';
            document.getElementById('barcode-input').blur();
        },
        preConfirm: (value) => {
            const parsed = parseFloat(String(value).replace(',', '.'));
            if (isNaN(parsed) || parsed <= 0) {
                Swal.showValidationMessage('Quantidade inválida');
                return false;
            }
            if (parsed > (falta + 0.001)) {
                Swal.showValidationMessage('Não pode carregar mais que o separado: ' + Number(falta.toFixed(3)));
                return false;
            }
            return parsed;
        }
    });

   if (res.isConfirmed && res.value) {
    Swal.fire({ 
        title: 'Gravando...', 
        position: 'top', 
        allowOutsideClick: false, 
        didOpen: () => Swal.showLoading() 
    });
    
    try {
        const r = await apiFetch('v1/carregamento/confirmar', 'POST', {
            iditem: String(item.cod_item),
            idembarque: String(state.embarque),
            qtd: res.value,
            idusuario: getUserId(),
            doca: docaSelecionada
        });
        
        if (r.success) {
            Swal.close();
            
            // 🔥 PEGAR O ID DO CARREGAMENTO RETORNADO PELA API
            const idCarregamento = r.id_carregamento;
            
            // Sempre pedir foto para CADA carregamento
            await Swal.fire({
                title: '📸 Foto do carregamento',
                html: `<div style="text-align:center;">
                    <div style="font-weight:800; font-size:1rem; color:#1e293b;">${item.descricao || item.nome_item}</div>
                    <div style="font-size:1.5rem; font-weight:800; color:var(--success); margin:10px 0;">+${res.value} unidades</div>
                    <p style="font-size:0.85rem; color:#64748b;">Registre uma foto deste palete/carga</p>
                    <div style="background:#fef3c7; padding:6px; border-radius:6px; margin-top:8px; font-size:0.75rem;">
                        ⚠️ Foto obrigatória para cada carregamento
                    </div>
                </div>`,
                icon: 'info',
                confirmButtonText: '📸 Tirar foto agora',
                confirmButtonColor: '#274036',
                showCancelButton: false,
                allowOutsideClick: false,
                position: 'top'
            });
            
            // 🔥 Passar o ID do carregamento específico para a foto
            const fotoOk = await capturarFoto(item.cod_item, idCarregamento);
            
            if (!fotoOk) {
                await Swal.fire({
                    title: '⚠️ Atenção',
                    text: 'O carregamento foi registrado, mas a foto não foi salva!',
                    icon: 'warning',
                    timer: 2500,
                    position: 'top'
                });
            } else {
                await Swal.fire({ 
                    icon: 'success', 
                    title: '✅ Carregado com sucesso!', 
                    timer: 1500, 
                    showConfirmButton: false, 
                    position: 'top' 
                });
            }
            
            await carregarLista();
            
        } else {
            throw new Error(r.error || 'Erro ao gravar no banco');
        }
    } catch (e) {
        Swal.fire({ 
            title: 'Erro na API', 
            text: e.message, 
            icon: 'error', 
            position: 'top' 
        });
    } finally {
        isProcessing = false;
        if (el) el.classList.remove('active');
    }
} else {
    isProcessing = false;
    if (el) el.classList.remove('active');
}
}

async function confirmarEstornoCarregamento(id, nome) {
    document.getElementById('barcode-input').blur();
    
    const itemEstorno = state.itens.find(i => i.cod_item == id);
    const imgPath = itemEstorno?.path_foto_master ? itemEstorno.path_foto_master.split('Fotos para o Site\\')[1] : null;
    const fotoUrl = imgPath ? 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20') : 'https://placehold.co/150x150?text=S/F';

    const res = await Swal.fire({
        title: 'Estornar?',
        position: 'top',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
            <img src="${fotoUrl}" style="width:80px; height:80px; object-fit:contain; border-radius:10px; margin-bottom:10px;">
            <div style="font-weight:800; color:var(--primary); font-size:0.9rem; text-align:center;">${nome}</div>
            <div style="font-size:0.85rem; color:#64748b; text-align:center;">Deseja retirar este item do caminhão?</div>
        </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, Estornar',
        width: '95%'
    });

    if (res.isConfirmed) {
        Swal.showLoading();
        try {
            const r = await apiFetch(`v1/carregamento/estornar/${id}/${state.embarque}`, 'DELETE');
            if (r.success) {
                await carregarLista();
                Swal.fire({ icon: 'success', title: 'Estornado!', timer: 1000, position: 'top' });
            }
        } catch (e) {
            Swal.fire({ title: 'Erro', text: e.message, icon: 'error' });
        }
    }
    isProcessing = false;
}

// ==========================================================================
// FINALIZAÇÃO E CÂMERA
// ==========================================================================
async function finalizarCargaOficial() {
    const res = await Swal.fire({
        title: 'Finalizar?',
        text: 'Caminhão liberado?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        position: 'top'
    });

    if (res.isConfirmed) {
        try {
            const r = await apiFetch(`v1/carregamento/finalizar/${state.embarque}`, 'POST', { idusuario: getUserId() });
            if (r.success) {
                await Swal.fire({ title: 'FINALIZADO', icon: 'success', timer: 2000, position: 'top' });
                location.reload();
            }
        } catch (e) {
            Swal.fire({ title: 'Erro', text: e.message, icon: 'error' });
        }
    }
}

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
            processarLeitura(txt);
        }).catch(() => {
            div.style.display = 'none';
        });
    }
}

// ==========================================================================
// SINCRONISMO AUTOMÁTICO
// ==========================================================================
setInterval(async () => {
    if (!state.embarque || isProcessing || (typeof Swal !== 'undefined' && Swal.isVisible())) return;
    try {
        const resposta = await apiFetch(`v1/carregamento/itens/${state.embarque}`, 'GET', { ordem: state.ordem, v: Date.now() });
        if (Array.isArray(resposta) && JSON.stringify(resposta) !== JSON.stringify(state.itens)) {
            if (state.itens.length > 0) detectarAlteracaoRemota(state.itens, resposta);
            state.itens = resposta;
            render();
        }
    } catch (e) {}
}, 5000);

function detectarAlteracaoRemota(antigos, novos) {
    novos.forEach(itemNovo => {
        const itemAntigo = antigos.find(a => a.cod_item === itemNovo.cod_item);
        if (itemAntigo) {
            const sepAntiga = parseFloat(itemAntigo.ja_separado) || 0;
            const sepNova = parseFloat(itemNovo.ja_separado) || 0;
            const libAntigo = parseInt(itemAntigo.pode_carregar);
            const libNovo = parseInt(itemNovo.pode_carregar);

            if (sepNova < sepAntiga || (libAntigo === 1 && libNovo === 0)) {
                try { new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3').play(); } catch (e) {}
                Swal.fire({
                    title: 'Mudança na Logística!',
                    html: `O item <b>${itemNovo.descricao || itemNovo.nome_item}</b> foi alterado pela separação.<br>Verifique o caminhão!`,
                    icon: 'warning',
                    toast: true,
                    position: 'top-end',
                    timer: 6000,
                    showConfirmButton: false
                });
            }
        }
    });
}

document.addEventListener('click', (e) => {
    if (!['BUTTON', 'SELECT', 'INPUT'].includes(e.target.tagName) && !document.querySelector('.swal2-container')) {
        const input = document.getElementById('barcode-input');
        if (input) {
            input.setAttribute('readonly', 'true');
            input.focus();
        }
    }
});

// ==========================================================================
// EXPORTAÇÃO DE FUNÇÕES GLOBAIS (necessárias para onclick no HTML)
// ==========================================================================
window.toggleMenuEmbarques = toggleMenuEmbarques;
window.selecionarEmbarqueManual = selecionarEmbarqueManual;
window.alterarOrdem = alterarOrdem;
window.toggleCamera = toggleCamera;
window.finalizarCargaOficial = finalizarCargaOficial;
window.selecionarDoca = selecionarDoca;