// ==========================================================================
// MÓDULO DE CARREGAMENTO (COLETOR OFICIAL)
// ==========================================================================

var API_TOKEN = '';

const state = {
    embarque: '',
    ordem: 'ASC',
    itens: [],
    embarquesDisponiveis: [],
    resumo: {}
};

let docaSelecionada = null;
let fotoInputElement = null;
let scanner = null;
let isProcessing = false;

// ==========================================================================
// SELEÇÃO DE DOCA
// ==========================================================================
function selecionarDoca(doca) {
    docaSelecionada = doca;

    document.querySelectorAll('.doca-btn').forEach(btn => {
        btn.classList.remove('bg-blue-500', 'text-white');
        btn.classList.add('bg-slate-100', 'text-slate-600');
    });

    const btnMap = { 'DOCA 1': 'btnDoca1', 'DOCA 2': 'btnDoca2', 'DOCA 3': 'btnDoca3' };
    const btn = document.getElementById(btnMap[doca]);
    if (btn) {
        btn.classList.remove('bg-slate-100', 'text-slate-600');
        btn.classList.add('bg-blue-500', 'text-white');
    }

    document.getElementById('nomeDocaSelecionada').innerText = doca;
    document.getElementById('docaSelecionada').classList.remove('hidden');

    setTimeout(() => {
        const input = document.getElementById('barcode-input');
        if (input) input.focus();
    }, 300);
}

// ==========================================================================
// API
// ==========================================================================
async function apiFetch(endpoint, method = 'GET', body = null) {
    const token = localStorage.getItem('authToken') || localStorage.getItem('token');
    const normalizedEndpoint = endpoint.replace(/^\/+/, '').replace(/^v1\//, '');
    const apiRoot = window.API_URL || 'https://api.nutricionalbr.com/v1';
    let url = endpoint.startsWith('http') ? endpoint : `${apiRoot.replace(/\/$/, '')}/${normalizedEndpoint}`;

    const options = {
        method: method,
        headers: { 'Content-Type': 'application/json' }
    };

    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    } else if (API_TOKEN) {
        options.headers['X-API-Token'] = API_TOKEN;
    }

    if (method === 'GET' && body) {
        const query = new URLSearchParams(body).toString();
        if (query) url += (url.includes('?') ? '&' : '?') + query;
    } else if (body) {
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);

    if (response.status === 401) {
        const portalBase = location.pathname.includes('/API/') ? '/API/portal' : '/portal';
        window.location.href = `${portalBase}/login.php?redirect=carregamento`;
        throw new Error('Sessão expirada');
    }

    return response.json();
}

const semFotoUrl = 'https://placehold.co/150x150?text=S/F';

function getProductImageUrl(path) {
    if (!path) return semFotoUrl;
    const normalized = String(path).trim().replace(/\\/g, '/');
    if (!normalized || ['.', '-', 'null', 'undefined'].includes(normalized.toLowerCase())) return semFotoUrl;
    if (/^https?:\/\//i.test(normalized)) return normalized.replace(/ /g, '%20');

    const marker = 'Fotos para o Site/';
    const markerIndex = normalized.toLowerCase().indexOf(marker.toLowerCase());
    let relativePath = markerIndex >= 0
        ? normalized.substring(markerIndex + marker.length)
        : normalized.replace(/^\/+/, '').replace(/^fotos\//i, '');

    if (!relativePath) return semFotoUrl;
    relativePath = relativePath.split('/').map(segment => encodeURIComponent(segment)).join('/');
    return 'https://acesso.nutricionalbr.com:2053/fotos/' + relativePath;
}

function getUserId() {
    const el = document.getElementById('user_id');
    if (el && el.value && el.value !== '0' && el.value !== '') {
        return parseInt(el.value);
    }
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    return userData.uid || 0;
}

function getAuthToken() {
    return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
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
// COMPRESSÃO DE IMAGEM
// ==========================================================================
async function comprimirImagemRapida(file) {
    return new Promise((resolve) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const canvas = document.createElement('canvas');
                let width = img.width;
                let height = img.height;
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

                canvas.toBlob((blob) => {
                    const compressedFile = new File([blob], 'foto.jpg', {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    resolve(compressedFile);
                }, 'image/jpeg', 0.7);
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    });
}

// ==========================================================================
// UPLOAD DE FOTO
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
    formData.append('id_carregamento', idCarregamento);

    try {
        const token = getAuthToken();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 20000);

        const apiRoot = window.API_URL || 'https://api.nutricionalbr.com/v1';
        const resp = await fetch(`${apiRoot.replace(/\/$/, '')}/carregamento/foto`, {
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
        }

        await Swal.fire({
            icon: 'error',
            title: '❌ Erro',
            text: result.error || 'Falha ao salvar foto',
            toast: true, position: 'top', timer: 2000, showConfirmButton: false
        });
        return false;
    } catch (e) {
        Swal.close();
        let mensagem = 'Erro de conexão. Verifique sua internet.';
        if (e.name === 'AbortError') mensagem = 'Tempo limite excedido.';

        await Swal.fire({
            icon: 'error',
            title: '❌ Falha',
            text: mensagem,
            toast: true, position: 'top', timer: 2500, showConfirmButton: false
        });
        return false;
    }
}

// ==========================================================================
// CAPTURA DE FOTO
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
                resolve(await capturarFoto(iditem, idCarregamento));
                return;
            }

            const file = input.files[0];
            input.value = '';

            if (file.size > 15 * 1024 * 1024) {
                await Swal.fire({
                    icon: 'warning',
                    title: '⚠️ Foto de alta resolução',
                    html: `Sua foto tem <strong>${(file.size / 1024 / 1024).toFixed(1)}MB</strong><br>Vamos comprimir automaticamente.`,
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

            const fileToUpload = await comprimirImagemRapida(file);
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
            resolve(await capturarFoto(iditem, idCarregamento));
        };

        input.click();
    });
}

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
window.onload = async function() {
    try {
        const dados = await apiFetch('v1/carregamento/embarques');
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
// MENU DE EMBARQUES
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

        const prontos = parseInt(e.itens_prontos) || 0;
        const total = parseInt(e.total_itens) || 0;
        const carregados = parseInt(e.itens_carregados) || 0;
        const progresso = parseInt(e.progresso_separacao) || 0;

        let cor = '#3b82f6', label = 'EM SEPARAÇÃO';
        if (carregados > 0 && carregados < total) {
            cor = '#f59e0b'; label = 'EM CARREGAMENTO';
        } else if (prontos === total && total > 0) {
            cor = '#10b981'; label = 'PRONTO';
        } else if (prontos > 0) {
            cor = '#f59e0b'; label = 'SEPARAÇÃO PARCIAL';
        }

        h += `<div onclick="selecionarEmbarqueManual('${e.idembarque}', '${label}', '${cor}')" 
                  style="padding: 15px; border-bottom: 1px solid #f1f5f9; color: ${cor}; font-weight: 800; cursor: pointer; background: white;">
                <span style="font-size: 0.65rem; display: block; opacity: 0.7;">
                    ${label} — ${prontos}/${total} prontos (${progresso}%)
                </span>
                #${e.idembarque} - ${e.rota}
              </div>`;
    });
    container.innerHTML = h || '<div style="padding:15px; color:#94a3b8;">Nenhum embarque disponível.</div>';
}

async function selecionarEmbarqueManual(id, label, cor) {
    const menu = document.getElementById('menuEmbarques');
    if (menu) menu.style.display = 'none';

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
// CARREGAR E RENDERIZAR
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

    // Ordenação: LIBERADO → PARCIAL → CARREGADO → BLOQUEADO
    const ordemStatus = { 'LIBERADO': 0, 'PARCIAL': 1, 'CARREGADO': 2, 'BLOQUEADO': 3 };
    const itensOrdenados = [...state.itens].sort((a, b) => {
        const oa = ordemStatus[a.status_item] ?? 9;
        const ob = ordemStatus[b.status_item] ?? 9;
        return oa - ob;
    });

    let h = '';
    let concluidosCarga = 0;
    let bloqueados = 0;
    let parciais = 0;
    let ultimaSecao = null;

    itensOrdenados.forEach(i => {
        const ja_car = parseFloat(i.ja_carregado) || 0;
        const ja_sep = parseFloat(i.ja_separado) || 0;
        const total = parseFloat(i.quant_embarque) || 0;
        const disponivel = parseFloat(i.quantidade_disponivel) || 0;
        const status = i.status_item || 'BLOQUEADO';

        if (status === 'CARREGADO') concluidosCarga++;
        if (status === 'BLOQUEADO') bloqueados++;
        if (status === 'PARCIAL') parciais++;

        if (i.idsecao !== ultimaSecao) {
            if (ultimaSecao !== null) h += '<div class="section-divider"></div>';
            ultimaSecao = i.idsecao;
        }

        const img = getProductImageUrl(i.path_foto_master);

        let cardClass = '';
        let statusHTML = '';
        let statusColor = 'var(--success)';

        switch (status) {
            case 'CARREGADO':
                cardClass = 'concluido';
                statusHTML = '<i class="fa-solid fa-check-circle"></i> CARREGADO';
                break;
            case 'LIBERADO':
                cardClass = 'liberado';
                statusHTML = `<i class="fa-solid fa-truck-fast"></i> PRONTO (${Number(disponivel.toFixed(3))})`;
                break;
            case 'PARCIAL':
                cardClass = 'parcial';
                statusHTML = `<i class="fa-solid fa-hourglass-half"></i> PARCIAL (${Number(disponivel.toFixed(3))})`;
                statusColor = '#f59e0b';
                break;
            case 'BLOQUEADO':
            default:
                cardClass = 'bloqueado';
                statusHTML = '<i class="fa-solid fa-lock"></i> AGUARDANDO SEPARAÇÃO';
                statusColor = '#94a3b8';
                break;
        }

        h += `<div class="item-card ${cardClass}" id="item-${i.cod_item}">
            <img src="${img}" class="prod-img" onerror="this.src='https://placehold.co/100x100?text=S/F'">
            <div class="item-info">
                <div class="item-name">
                    ${status === 'BLOQUEADO' ? '<i class="fa-solid fa-lock"></i> ' : ''}
                    ${i.descricao || i.nome_item}
                </div>
                <div class="qty-row">
                    <div class="qty-tag">SEP: ${Number(ja_sep.toFixed(3))}/${Number(total.toFixed(3))}</div>
                    <div class="qty-tag" style="color:${statusColor}; font-weight:800;">
                        ${statusHTML}
                    </div>
                </div>
                <div style="font-size:0.55rem; color:#94a3b8; margin-top:5px; display:flex; justify-content:space-between;">
                    <span>EAN: ${i.cod_barras || 'S/ COD'}</span>
                    <span>ID: ${i.cod_item}</span>
                </div>
            </div>
        </div>`;
    });

    const total = state.itens.length;
    const tudoCarregado = concluidosCarga === total && total > 0;

    document.getElementById('contagem-itens-header').innerText = concluidosCarga + '/' + total + ' ITENS';
    document.getElementById('resumo-total-itens').innerText = concluidosCarga + '/' + total;

    let avisoTopo = '';
    if (bloqueados > 0) {
        avisoTopo += `<div style="background:#f1f5f9; color:#64748b; padding:10px; border-radius:10px; text-align:center; font-weight:700; margin-bottom:8px; font-size:0.8rem;">
            <i class="fa-solid fa-hourglass-half"></i> ${bloqueados} item(s) aguardando separação
        </div>`;
    }
    if (parciais > 0) {
        avisoTopo += `<div style="background:#fef3c7; color:#92400e; padding:10px; border-radius:10px; text-align:center; font-weight:700; margin-bottom:8px; font-size:0.8rem;">
            <i class="fa-solid fa-triangle-exclamation"></i> ${parciais} item(s) com separação parcial
        </div>`;
    }

    if (tudoCarregado) {
        btnFinalizar.style.display = 'block';
        avisoTopo = '<div style="background:#dcfce7; color:#166534; padding:15px; border-radius:12px; text-align:center; font-weight:800; border:2px solid #10b981; margin-bottom:10px;"><i class="fa-solid fa-circle-check"></i> TUDO CARREGADO!</div>';
    } else {
        btnFinalizar.style.display = 'none';
    }

    listaAlvo.innerHTML = avisoTopo + (h || '<div style="padding:40px; color:#94a3b8; text-align:center;">Nenhum item.</div>');
}

// ==========================================================================
// PROCESSAR LEITURA
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

    // ❌ TRAVA 1: não pertence
    if (!item) {
        new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
        await Swal.fire({
            title: 'Não encontrado',
            text: 'Item não pertence a este embarque ou código inválido.',
            icon: 'error', timer: 2000, position: 'top', toast: true, showConfirmButton: false
        });
        isProcessing = false;
        return;
    }

    // ❌ TRAVA 2: bloqueado (nada separado)
    if (item.status_item === 'BLOQUEADO') {
        new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
        await Swal.fire({
            title: '🔒 Aguarde!',
            text: 'Este item ainda não foi separado pela logística.',
            icon: 'warning',
            position: 'top',
            confirmButtonColor: '#f59e0b'
        });
        isProcessing = false;
        return;
    }

    const ja_car = parseFloat(item.ja_carregado) || 0;
    const ja_sep = parseFloat(item.ja_separado) || 0;
    const disponivel = parseFloat(item.quantidade_disponivel) || 0;

    // ❌ TRAVA 3: nada disponível
    if (disponivel <= 0.0001) {
        isProcessing = false;
        if (ja_car > 0.0001) {
            confirmarEstornoCarregamento(item.cod_item, item.descricao || item.nome_item);
        } else {
            await Swal.fire({
                title: '⏳ Aguardando separação',
                html: `<div style="text-align:center;">
                    <div style="font-weight:800;">${item.descricao || item.nome_item}</div>
                    <div style="margin-top:10px;">Separado: <b>${Number(ja_sep.toFixed(3))}</b></div>
                    <div>Carregado: <b>${Number(ja_car.toFixed(3))}</b></div>
                    <div style="color:#f59e0b; margin-top:10px;">Aguardando mais separação...</div>
                </div>`,
                icon: 'info', position: 'top'
            });
        }
        return;
    }

    // ✅ Liberado: confirma quantidade
    const falta = Number(disponivel.toFixed(4));
    window.scrollTo(0, 0);
    const el = document.getElementById('item-' + item.cod_item);
    if (el) el.classList.add('active');

    const fotoUrl = getProductImageUrl(item.path_foto_master);
    const avisoParcial = item.status_item === 'PARCIAL'
        ? `<div style="background:#fef3c7; padding:8px; border-radius:6px; margin-top:10px; font-size:0.75rem; color:#92400e;">
             ⚡ <b>SEPARAÇÃO PARCIAL</b><br>
             Separado: ${Number(ja_sep.toFixed(3))} de ${Number(parseFloat(item.quant_embarque).toFixed(3))}
           </div>`
        : '';

    const res = await Swal.fire({
        title: 'Confirmar Carga',
        position: 'top',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
            <img src="${fotoUrl}" style="width:100px; height:100px; object-fit:contain; border-radius:10px; margin-bottom:10px; border:1px solid #eee;">
            <div style="font-weight:800; font-size:0.9rem; text-align:center; max-width:250px; color:#1e293b;">${item.descricao || item.nome_item}</div>
            <div style="color:var(--danger); font-weight:800; font-size:1rem; margin-top:8px;">DISPONÍVEL: ${Number(falta.toFixed(3))}</div>
            ${avisoParcial}
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
            document.getElementById('barcode-input').blur();
        },
        preConfirm: (value) => {
            const parsed = parseFloat(String(value).replace(',', '.'));
            if (isNaN(parsed) || parsed <= 0) {
                Swal.showValidationMessage('Quantidade inválida');
                return false;
            }
            if (parsed > (falta + 0.001)) {
                Swal.showValidationMessage('Máximo disponível: ' + Number(falta.toFixed(3)));
                return false;
            }
            return parsed;
        }
    });

    if (res.isConfirmed && res.value) {
        Swal.fire({
            title: 'Gravando...', position: 'top',
            allowOutsideClick: false, didOpen: () => Swal.showLoading()
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
                const idCarregamento = r.id_carregamento;

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

                const fotoOk = await capturarFoto(item.cod_item, idCarregamento);

                if (!fotoOk) {
                    await Swal.fire({
                        title: '⚠️ Atenção',
                        text: 'O carregamento foi registrado, mas a foto não foi salva!',
                        icon: 'warning', timer: 2500, position: 'top'
                    });
                } else {
                    await Swal.fire({
                        icon: 'success', title: '✅ Carregado com sucesso!',
                        timer: 1500, showConfirmButton: false, position: 'top'
                    });
                }

                await carregarLista();
            } else {
                throw new Error(r.error || 'Erro ao gravar no banco');
            }
        } catch (e) {
            Swal.fire({ title: 'Erro na API', text: e.message, icon: 'error', position: 'top' });
        } finally {
            isProcessing = false;
            if (el) el.classList.remove('active');
        }
    } else {
        isProcessing = false;
        if (el) el.classList.remove('active');
    }
}

// ==========================================================================
// ESTORNO
// ==========================================================================
async function confirmarEstornoCarregamento(id, nome) {
    document.getElementById('barcode-input').blur();

    const itemEstorno = state.itens.find(i => i.cod_item == id);
    const fotoUrl = getProductImageUrl(itemEstorno?.path_foto_master);

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
// FINALIZAR
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
            } else {
                throw new Error(r.error);
            }
        } catch (e) {
            Swal.fire({ title: 'Erro', text: e.message, icon: 'error' });
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
        if (!itemAntigo) return;

        const dispAntigo = parseFloat(itemAntigo.quantidade_disponivel) || 0;
        const dispNovo = parseFloat(itemNovo.quantidade_disponivel) || 0;
        const statusAntigo = itemAntigo.status_item;
        const statusNovo = itemNovo.status_item;

        // Separação liberou MAIS quantidade
        if (dispNovo > dispAntigo + 0.0001) {
            try { new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3').play(); } catch (e) {}
            Swal.fire({
                title: '📦 Mais itens liberados!',
                html: `<b>${itemNovo.descricao || itemNovo.nome_item}</b><br>
                       Disponível agora: <b style="color:#10b981">${Number(dispNovo.toFixed(3))}</b>`,
                icon: 'success',
                toast: true, position: 'top-end',
                timer: 5000, showConfirmButton: false
            });
        }

        // Estorno remoto (separador estornou)
        const sepAntiga = parseFloat(itemAntigo.ja_separado) || 0;
        const sepNova = parseFloat(itemNovo.ja_separado) || 0;
        if (sepNova < sepAntiga - 0.0001) {
            try { new Audio('https://assets.mixkit.co/active_storage/sfx/2568/2568-preview.mp3').play(); } catch (e) {}
            Swal.fire({
                title: '⚠️ Item alterado!',
                html: `O item <b>${itemNovo.descricao || itemNovo.nome_item}</b> teve separação reduzida.<br>Verifique o caminhão!`,
                icon: 'warning',
                toast: true, position: 'top-end',
                timer: 6000, showConfirmButton: false
            });
        }

        // Item bloqueado remotamente
        if (statusAntigo !== 'BLOQUEADO' && statusNovo === 'BLOQUEADO') {
            Swal.fire({
                title: '🔒 Item bloqueado!',
                html: `<b>${itemNovo.descricao || itemNovo.nome_item}</b> voltou a aguardar separação.`,
                icon: 'warning',
                toast: true, position: 'top-end',
                timer: 6000, showConfirmButton: false
            });
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
// EXPORTAÇÃO GLOBAL
// ==========================================================================
window.toggleMenuEmbarques = toggleMenuEmbarques;
window.selecionarEmbarqueManual = selecionarEmbarqueManual;
window.alterarOrdem = alterarOrdem;
window.toggleCamera = toggleCamera;
window.finalizarCargaOficial = finalizarCargaOficial;
window.selecionarDoca = selecionarDoca;