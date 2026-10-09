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

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[char]);
}

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
          focarBipe();
    }, 300);
}

let currentAbortController = null;

async function apiFetch(endpoint, method = 'GET', body = null) {
    // Cancela requisição anterior se ainda estiver pendente
    if (currentAbortController) {
        try { currentAbortController.abort(); } catch (e) {}
    }
    currentAbortController = new AbortController();
    const signal = currentAbortController.signal;

    const token = localStorage.getItem('authToken') || localStorage.getItem('token');
    const normalizedEndpoint = endpoint.replace(/^\/+/, '').replace(/^v1\//, '');
    const apiRoot = window.API_URL || 'https://api.nutricionalbr.com/v1';
    let url = endpoint.startsWith('http') ? endpoint : `${apiRoot.replace(/\/$/, '')}/${normalizedEndpoint}`;

    const options = {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        signal: signal
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

    // Timeout de 30s
    const timeoutId = setTimeout(() => {
        try { currentAbortController.abort(); } catch (e) {}
    }, 30000);

    // ✅ LOG DE SAÍDA
    console.log(`[API →] ${method} ${url}`, body || '');

    try {
        const response = await fetch(url, options);
        clearTimeout(timeoutId);

        if (response.status === 401) {
            const portalBase = location.pathname.includes('/API/') ? '/API/portal' : '/portal';
            window.location.href = `${portalBase}/login.php?redirect=carregamento`;
            throw new Error('Sessão expirada');
        }

        // ✅ LOG DE RESPOSTA
        const text = await response.text();
        console.log(`[API ←] ${response.status} ${url}`, text.substring(0, 500));

        let json;
        try {
            json = JSON.parse(text);
        } catch (parseErr) {
            console.error('[API ❌] Resposta não é JSON. Recebido:', text.substring(0, 500));
            throw new Error(`Resposta inválida do servidor (${response.status})`);
        }

        return json;
    } catch (e) {
        clearTimeout(timeoutId);
        if (e.name === 'AbortError') {
            throw new Error('Tempo limite excedido. Tente novamente.');
        }
        throw e;
    } finally {
        currentAbortController = null;
    }
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

// ==========================================================================
// FOCO AUTOMÁTICO NO BIPE (após cada operação)
// ==========================================================================
function focarBipe() {
    const input = document.getElementById('barcode-input');
    if (!input) return;

    // Limpa resíduo do bipe anterior
    input.value = '';

    // Garante modo "bipe pronto" (readonly evita teclado fantasma no mobile)
    input.setAttribute('readonly', 'true');

    // Foca e coloca cursor no fim
    input.focus();
    try {
        const len = input.value.length;
        input.setSelectionRange(len, len);
    } catch (e) { /* alguns navegadores não suportam em readonly */ }
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
async function uploadFotoRapida(file, iditem, nomeItem, idCarregamento, idSubida = null) {
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
    if (idSubida) formData.append('id_subida', idSubida);

    try {
        const token = getAuthToken();
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 20000);

        const apiRoot = window.API_URL || 'https://api.nutricionalbr.com/v1';
        const urlFoto = `${apiRoot.replace(/\/$/, '')}/carregamento/foto`;
        console.log('[Upload] POST para:', urlFoto, 'subida:', idSubida);

        const resp = await fetch(urlFoto, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: formData,
            signal: controller.signal
        });

        clearTimeout(timeoutId);
        Swal.close();

        const result = await resp.json();

        if (result.success) return true;

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
async function capturarFoto(iditem, idCarregamento, idSubida = null) {
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
                resolve(await capturarFoto(iditem, idCarregamento, idSubida));
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

            const sucesso = await uploadFotoRapida(fileToUpload, iditem, nomeItem, idCarregamento, idSubida);
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
            resolve(await capturarFoto(iditem, idCarregamento, idSubida));
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
        // ✅ Cache-bust para garantir dados frescos
        const dados = await apiFetch(
            `v1/carregamento/itens/${state.embarque}`,
            'GET',
            { ordem: state.ordem, _: Date.now() }
        );
        state.itens = Array.isArray(dados) ? dados : [];
        render();
        isProcessing = false;
        focarBipe();
    } catch (e) {
        console.error('Erro na lista:', e);
    }
}

// ==========================================================================
// RENDERIZAR LISTA
// ==========================================================================
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
        const subidas = parseInt(i.subidas) || 0;
        const ultimaSubidaHora = i.ultima_subida_hora
            ? new Date(i.ultima_subida_hora).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : null;

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
                    ${subidas > 0 ? `
                        <div class="qty-tag" style="background:#dbeafe; color:#1e40af; font-weight:800;">
                            📦 ${subidas} subida${subidas > 1 ? 's' : ''}
                            ${ja_car > 0 ? ` · ${Number(ja_car.toFixed(2))}` : ''}
                        </div>
                    ` : ''}
                </div>
                <div style="font-size:0.55rem; color:#94a3b8; margin-top:5px; display:flex; justify-content:space-between;">
                    <span>EAN: ${i.cod_barras || 'S/ COD'}</span>
                    <span>ID: ${i.cod_item}${ultimaSubidaHora ? ` · Últ: ${ultimaSubidaHora}` : ''}</span>
                </div>
            </div>
            ${subidas > 0 ? `
                <button onclick="event.stopPropagation(); verHistoricoSubidas(${i.cod_item})"
                        style="position:absolute; top:8px; right:8px; background:#dbeafe; color:#1e40af;
                               border:none; border-radius:6px; padding:4px 8px; font-size:0.6rem;
                               font-weight:800; cursor:pointer;">
                    📦 Histórico
                </button>
            ` : ''}
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

    // ❌ TRAVA 1: não pertence ao embarque
    if (!item) {
        new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
        await Swal.fire({
            title: 'Não encontrado',
            text: 'Item não pertence a este embarque ou código inválido.',
            icon: 'error', timer: 2000, position: 'top', toast: true, showConfirmButton: false
        });
        isProcessing = false;
        focarBipe();
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
        focarBipe();
        return;
    }

    const ja_car = parseFloat(item.ja_carregado) || 0;
    const ja_sep = parseFloat(item.ja_separado) || 0;
    const total = parseFloat(item.quant_embarque) || 0;
    const disponivel = parseFloat(item.quantidade_disponivel) || 0;
    const subidas = parseInt(item.subidas) || 0;

    // ══════════════════════════════════════════════════════════════
    // ✅ SE O ITEM JÁ TEM SUBIDAS, PERGUNTAR O QUE FAZER
    // ══════════════════════════════════════════════════════════════
    if (subidas > 0) {
        isProcessing = false;
        focarBipe();

        const aindaTemDisponivel = disponivel > 0.0001;

        if (aindaTemDisponivel) {
            // Modal com 3 opções: adicionar mais / estornar / cancelar
            const escolha = await Swal.fire({
                title: '📦 Item com subidas',
                html: `<div style="text-align:center;">
                    <div style="font-weight:800; color:#1e293b; margin-bottom:8px;">${item.descricao || item.nome_item}</div>
                    <div style="font-size:0.85rem; color:#475569;">
                        Carregado: <b>${Number(ja_car.toFixed(3))}</b> de <b>${Number(total.toFixed(3))}</b>
                    </div>
                    <div style="font-size:0.85rem; color:#f59e0b; margin-top:6px;">
                        Disponível para carregar: <b>${Number(disponivel.toFixed(3))}</b>
                    </div>
                    <div style="font-size:0.85rem; color:#64748b; margin-top:10px;">
                        <b>${subidas}</b> subida${subidas > 1 ? 's' : ''} registrada${subidas > 1 ? 's' : ''}
                    </div>
                    <div style="font-size:0.8rem; color:#94a3b8; margin-top:12px;">
                        O que deseja fazer?
                    </div>
                </div>`,
                icon: 'question',
                showDenyButton: true,
                showCancelButton: true,
                confirmButtonText: '➕ Adicionar mais',
                denyButtonText: '🗑️ Estornar subida',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#10b981',
                denyButtonColor: '#dc2626',
                cancelButtonColor: '#94a3b8',
                position: 'top',
                width: '95%'
            });

            if (escolha.isConfirmed) {
                // Adicionar mais → continua no fluxo normal abaixo
                isProcessing = true;
            } else if (escolha.isDenied) {
                // Abre o histórico de subidas
                await verHistoricoSubidas(item.cod_item);
                return;
            } else {
                // Cancelou
                return;
            }
        } else {
            // Item com subidas e sem disponível → só permite estornar
            const escolha = await Swal.fire({
                title: '📦 Item com subidas',
                html: `<div style="text-align:center;">
                    <div style="font-weight:800; color:#1e293b; margin-bottom:8px;">${item.descricao || item.nome_item}</div>
                    <div style="font-size:0.85rem; color:#dc2626; font-weight:700;">
                        ⚠️ Item já carregado totalmente
                    </div>
                    <div style="font-size:0.85rem; color:#475569; margin-top:8px;">
                        Carregado: <b>${Number(ja_car.toFixed(3))}</b> de <b>${Number(total.toFixed(3))}</b>
                    </div>
                    <div style="font-size:0.85rem; color:#64748b; margin-top:10px;">
                        <b>${subidas}</b> subida${subidas > 1 ? 's' : ''} registrada${subidas > 1 ? 's' : ''}
                    </div>
                    <div style="font-size:0.8rem; color:#94a3b8; margin-top:12px;">
                        Deseja estornar alguma subida?
                    </div>
                </div>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '🗑️ Ver subidas',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#94a3b8',
                position: 'top',
                width: '95%'
            });

            if (escolha.isConfirmed) {
                await verHistoricoSubidas(item.cod_item);
            }
            return;
        }
    }

    // ❌ TRAVA 3: nada disponível (item sem subidas)
    if (disponivel <= 0.0001) {
        isProcessing = false;

        if (ja_car >= total - 0.0001 && total > 0) {
            confirmarEstornoCarregamento(item.cod_item, item.descricao || item.nome_item);
            return;
        }

        if (ja_car > 0.0001) {
            new Audio('https://actions.google.com/sounds/v1/alarms/beep_short.ogg').play();
            await Swal.fire({
                title: '📦 Subida parcial',
                html: `<div style="text-align:center;">
                    <div style="font-weight:800;">${item.descricao || item.nome_item}</div>
                    <div style="margin-top:10px;">
                        Carregado: <b style="color:#10b981;">${Number(ja_car.toFixed(3))}</b>
                        de <b>${Number(total.toFixed(3))}</b>
                    </div>
                    <div style="color:#f59e0b; margin-top:10px; font-size:0.85rem;">
                        ⏳ Aguardando mais separação para nova subida
                    </div>
                </div>`,
                icon: 'info',
                position: 'top',
                confirmButtonColor: '#274036'
            });
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
        focarBipe();
        return;
    }

    // ======================================================================
    // ✅ LIBERADO: confirma quantidade da SUBIDA
    // ======================================================================
    const falta = Number(disponivel.toFixed(4));
    window.scrollTo(0, 0);
    const el = document.getElementById('item-' + item.cod_item);
    if (el) el.classList.add('active');

    const fotoUrl = getProductImageUrl(item.path_foto_master);
    const numeroProximaSubida = subidas + 1;

    // Aviso de subida (a partir da 2ª)
    let avisoSubida = '';
    if (numeroProximaSubida > 1) {
        avisoSubida = `<div style="background:#dbeafe; padding:8px; border-radius:6px; margin-top:10px; font-size:0.75rem; color:#1e40af;">
            📦 <b>${numeroProximaSubida}ª SUBIDA</b><br>
            Já carregado: ${Number(ja_car.toFixed(3))} de ${Number(total.toFixed(3))}
        </div>`;
    }

    // Aviso de separação parcial
    let avisoParcial = '';
    if (item.status_item === 'PARCIAL' && ja_sep < total - 0.0001) {
        avisoParcial = `<div style="background:#fef3c7; padding:8px; border-radius:6px; margin-top:10px; font-size:0.75rem; color:#92400e;">
             ⚡ <b>SEPARAÇÃO PARCIAL</b><br>
             Separado: ${Number(ja_sep.toFixed(3))} de ${Number(total.toFixed(3))}
           </div>`;
    }

    const res = await Swal.fire({
        title: numeroProximaSubida > 1 ? `📦 ${numeroProximaSubida}ª Subida` : 'Confirmar Carga',
        position: 'top',
        html: `<div style="display:flex; flex-direction:column; align-items:center;">
            <img src="${fotoUrl}" style="width:100px; height:100px; object-fit:contain; border-radius:10px; margin-bottom:10px; border:1px solid #eee;">
            <div style="font-weight:800; font-size:0.9rem; text-align:center; max-width:250px; color:#1e293b;">${item.descricao || item.nome_item}</div>
            <div style="color:var(--danger); font-weight:800; font-size:1rem; margin-top:8px;">DISPONÍVEL: ${Number(falta.toFixed(3))}</div>
            ${avisoSubida}
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
                const idSubida = r.id_subida;
                const numeroSubida = r.numero_subida || 1;
                const qtAcumulada = r.quantidade_acumulada || res.value;

                await Swal.fire({
                    title: `📸 Foto da ${numeroSubida}ª subida`,
                    html: `<div style="text-align:center;">
                        <div style="font-weight:800; font-size:1rem; color:#1e293b;">${item.descricao || item.nome_item}</div>
                        <div style="font-size:1.5rem; font-weight:800; color:var(--success); margin:10px 0;">+${res.value} unidades</div>
                        <div style="font-size:0.8rem; color:#64748b; margin-bottom:8px;">
                            Subida ${numeroSubida} — Total acumulado: ${qtAcumulada}
                        </div>
                        <p style="font-size:0.85rem; color:#64748b;">Registre uma foto deste palete/carga</p>
                        <div style="background:#fef3c7; padding:6px; border-radius:6px; margin-top:8px; font-size:0.75rem;">
                            ⚠️ Foto obrigatória para cada subida
                        </div>
                    </div>`,
                    icon: 'info',
                    confirmButtonText: '📸 Tirar foto agora',
                    confirmButtonColor: '#274036',
                    showCancelButton: false,
                    allowOutsideClick: false,
                    position: 'top'
                });

                const fotoOk = await capturarFoto(item.cod_item, idCarregamento, idSubida);

                if (!fotoOk) {
                    try {
                        await apiFetch('v1/carregamento/desfazer', 'POST', {
                            idembarque: String(state.embarque),
                            iditem: String(item.cod_item),
                            qtd: res.value,
                            motivo: 'Foto não registrada após confirmação da subida'
                        });
                        await Swal.fire({
                            title: '⚠️ Subida desfeita',
                            text: 'Sem foto, a quantidade foi revertida. Tente novamente.',
                            icon: 'warning',
                            timer: 3000,
                            position: 'top',
                            showConfirmButton: false
                        });
                    } catch (e) {
                        console.error('Erro ao desfazer:', e);
                        await Swal.fire({
                            title: '⚠️ Atenção',
                            text: 'O carregamento ficou salvo sem foto. Estorne manualmente.',
                            icon: 'error',
                            timer: 4000,
                            position: 'top'
                        });
                    }
                } else {
                    await Swal.fire({
                        icon: 'success',
                        title: `✅ ${numeroSubida}ª subida registrada!`,
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
            Swal.fire({ title: 'Erro na API', text: e.message, icon: 'error', position: 'top' });
        } finally {
            isProcessing = false;
            if (el) el.classList.remove('active');
            focarBipe();
        }
    } else {
        isProcessing = false;
        if (el) el.classList.remove('active');
        focarBipe();
    }
}
// ==========================================================================
// ESTORNO
// ==========================================================================
async function confirmarEstornoCarregamento(id, nome) {
    if (isProcessing) return;      // evita duplo clique / bipe acidental
    isProcessing = true;

    document.getElementById('barcode-input').blur();

    const itemEstorno = state.itens.find(i => i.cod_item == id);
    const fotoUrl = getProductImageUrl(itemEstorno?.path_foto_master);

    try {
        const res = await Swal.fire({
            title: 'Estornar?',
            position: 'top',
            html: `<div style="display:flex; flex-direction:column; align-items:center;">
                <img src="${fotoUrl}" style="width:80px; height:80px; object-fit:contain; border-radius:10px; margin-bottom:10px;">
                <div style="font-weight:800; color:var(--primary); font-size:0.9rem; text-align:center;">${nome}</div>
                <div style="font-size:0.85rem; color:#64748b; text-align:center;">Deseja retirar este item do caminhão?</div>
            </div>`,
            input: 'textarea',
            inputLabel: 'Motivo obrigatório do estorno',
            inputPlaceholder: 'Descreva por que este item será estornado',
            inputAttributes: { maxlength: 500, 'aria-label': 'Motivo do estorno' },
            inputValidator: value => {
                const motivo = String(value || '').trim();
                if (motivo.length < 3) return 'Informe um motivo com pelo menos 3 caracteres.';
                if (motivo.length > 500) return 'O motivo pode ter no máximo 500 caracteres.';
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sim, Estornar',
            width: '95%'
        });

        if (res.isConfirmed) {
            Swal.showLoading();
            try {
                const r = await apiFetch(`v1/carregamento/estornar/${id}/${state.embarque}`, 'DELETE', {
                    motivo: String(res.value).trim()
                });
                if (r.success) {
                    await carregarLista();
                    Swal.fire({ icon: 'success', title: 'Estornado; evidências preservadas', timer: 1500, position: 'top' });
                } else {
                    throw new Error(r.error || 'Não foi possível estornar o item.');
                }
            } catch (e) {
                Swal.fire({ title: 'Erro', text: e.message, icon: 'error' });
            }
        }
    } finally {
        isProcessing = false;
        focarBipe();
    }
}

// ==========================================================================
// FINALIZAR
// ==========================================================================
async function finalizarCargaOficial() {
    if (isProcessing) return;      // evita duplo clique / bipe acidental
    isProcessing = true;

    try {
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
                const r = await apiFetch(
                    `v1/carregamento/finalizar/${state.embarque}`,
                    'POST',
                    { idusuario: getUserId() }
                );

                if (r.success) {
                    await Swal.fire({
                        title: 'FINALIZADO',
                        icon: 'success',
                        timer: 2000,
                        position: 'top'
                    });
                    location.reload();
                    return; // reload mata a página — não precisa destravar nem focar
                } else {
                    throw new Error(r.error);
                }
            } catch (e) {
                await Swal.fire({ title: 'Erro', text: e.message, icon: 'error' });
            }
        }
    } finally {
        // Se location.reload() foi chamado, isso nem executa — o navegador já está saindo.
        // Se cancelou ou deu erro, isso destrava o bipe e devolve o foco.
        isProcessing = false;
        focarBipe();
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
     focarBipe();
    }
});

// ==========================================================================
// HISTÓRICO DE SUBIDAS DE UM ITEM
// Permite estornar UMA subida OU TODAS
// (Event listeners anexados via JS — sem onclick inline)
// ==========================================================================
async function verHistoricoSubidas(iditem) {
    const item = state.itens.find(i => i.cod_item == iditem);
    const nomeItem = item ? (item.descricao || item.nome_item) : 'Item';

    if (isProcessing) return;
    isProcessing = true;

    try {
        Swal.fire({
            title: 'Carregando histórico...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            position: 'top'
        });

        const subidas = await apiFetch(
            `v1/carregamento/subidas/${state.embarque}/${iditem}`,
            'GET'
        );

        Swal.close();

        if (!Array.isArray(subidas) || subidas.length === 0) {
            await Swal.fire({
                title: 'Sem subidas',
                text: 'Nenhuma subida registrada para este item.',
                icon: 'info',
                position: 'top'
            });
            isProcessing = false;
            focarBipe();
            return;
        }

        // =============================================================
        // MONTAR HTML DO MODAL (SEM onclick inline)
        // =============================================================
        let html = `<div style="text-align:left; max-height:65vh; overflow-y:auto;">
            <div style="font-weight:800; margin-bottom:12px; font-size:1rem; color:#1e293b;">${nomeItem}</div>
            <div style="font-size:0.75rem; color:#64748b; margin-bottom:12px;">
                ${subidas.length} subida${subidas.length > 1 ? 's' : ''} registrada${subidas.length > 1 ? 's' : ''}
            </div>`;

        subidas.forEach(s => {
            const data = new Date(s.data_hora).toLocaleString('pt-BR');
            const temFoto = s.path_foto_auditoria ? '📸' : '⚠️ sem foto';
            const qtSubida = Number((parseFloat(s.qt_subida) || 0).toFixed(3));
            const qtAcumulada = Number((parseFloat(s.qt_acumulada) || 0).toFixed(3));
            const qtDisponivel = Number((parseFloat(s.quantidade_disponivel) || 0).toFixed(3));
            const estornada = ['ESTORNADA', 'PARCIALMENTE_ESTORNADA'].includes(s.status_estorno);

            html += `
                <div style="border-left:3px solid ${estornada ? '#f59e0b' : '#3b82f6'}; padding:10px 12px; margin-bottom:10px; background:#f8fafc; border-radius:8px; position:relative;">
                    <div style="font-weight:800; color:#1e40af; font-size:0.9rem; padding-right:70px;">
                        ${s.numero_subida}ª SUBIDA ${temFoto}
                        ${estornada ? `<span style="color:#b45309;">· ${s.status_estorno === 'ESTORNADA' ? 'ESTORNADA' : 'ESTORNO PARCIAL'}</span>` : ''}
                    </div>
                    <div style="font-size:0.85rem; color:#475569; margin-top:6px;">
                        Original: <b>${qtSubida}</b> · Disponível: <b>${qtDisponivel}</b> · Acum. no registro: <b>${qtAcumulada}</b>
                    </div>
                    <div style="font-size:0.7rem; color:#94a3b8; margin-top:4px;">
                        ${data} · ${s.doca || 'sem doca'} · ${s.nome_usuario || 'usuário'}
                    </div>
                    ${s.ultimo_motivo ? `<div style="font-size:0.72rem;color:#92400e;margin-top:5px;">Motivo do último estorno: ${escapeHtml(s.ultimo_motivo)}</div>` : ''}
                    ${qtDisponivel > 0.0001 ? `<button type="button"
                            class="btn-estornar-subida"
                            data-id-subida="${s.id}"
                            data-numero-subida="${s.numero_subida}"
                            style="position:absolute; top:8px; right:8px; background:#fee2e2; color:#dc2626;
                                   border:none; border-radius:6px; padding:6px 10px; font-size:0.7rem;
                                   font-weight:800; cursor:pointer;">
                        🗑️ Estornar
                    </button>` : ''}
                </div>
            `;
        });

        html += '</div>';

        const quantidadeAtiva = subidas.filter(s => Number(s.quantidade_disponivel) > 0.0001).length;
        if (quantidadeAtiva > 0) html += `
            <div style="margin-top:16px; padding-top:12px; border-top:2px dashed #e2e8f0;">
                <button type="button"
                        class="btn-estornar-todas"
                        style="width:100%; background:#dc2626; color:white; border:none;
                               border-radius:8px; padding:12px; font-weight:800; font-size:0.85rem; cursor:pointer;">
                    Estornar saldo restante de ${quantidadeAtiva} subida(s)
                </button>
            </div>
        `;

        // ✅ Abre o modal e anexa os event listeners no `didOpen`
        await Swal.fire({
            title: '📦 Histórico de Subidas',
            html,
            position: 'top',
            width: '95%',
            showConfirmButton: true,
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#274036',
            showCloseButton: true,
            didOpen: () => {
                const popup = Swal.getPopup();
                if (!popup) return;

                // ✅ Anexa listener em cada botão de estornar subida
                popup.querySelectorAll('.btn-estornar-subida').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        const idSubida = parseInt(btn.getAttribute('data-id-subida'), 10);
                        const numeroSubida = parseInt(btn.getAttribute('data-numero-subida'), 10);

                        // Fecha o modal de histórico
                        Swal.close();

                        // Aguarda 200ms (fechar o modal) e chama o estorno
                        setTimeout(() => {
                            estornarSubidaEspecifica(idSubida, numeroSubida, nomeItem, iditem);
                        }, 200);
                    });
                });

                // ✅ Anexa listener no botão "Estornar TODAS"
                const btnTodas = popup.querySelector('.btn-estornar-todas');
                if (btnTodas) {
                    btnTodas.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();

                        Swal.close();
                        setTimeout(() => {
                            estornarTodasSubidas(iditem, nomeItem);
                        }, 200);
                    });
                }
            }
        });

    } catch (e) {
        Swal.close();
        await Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Não foi possível carregar o histórico.',
            position: 'top'
        });
    } finally {
        isProcessing = false;
        focarBipe();
    }
}

// ==========================================================================
// ESTORNAR UMA SUBIDA ESPECÍFICA
// ==========================================================================
async function estornarSubidaEspecifica(idSubida, numeroSubida, nomeItem, iditem) {
    if (isProcessing) return;

    // Fecha o modal de histórico primeiro
    Swal.close();

    isProcessing = true;
    try {
        const res = await Swal.fire({
            title: `Estornar ${numeroSubida}ª subida?`,
            html: `<div style="text-align:center;">
                <div style="font-weight:800; color:#1e293b;">${nomeItem}</div>
                <div style="font-size:0.85rem; color:#64748b; margin-top:8px;">
                    Esta ação retira a quantidade ainda disponível desta subida.
                    O registro e as fotos originais serão preservados para auditoria.
                </div>
            </div>`,
            input: 'textarea',
            inputLabel: 'Motivo obrigatório do estorno',
            inputPlaceholder: 'Descreva o motivo',
            inputAttributes: { maxlength: 500, 'aria-label': 'Motivo do estorno' },
            inputValidator: value => {
                const motivo = String(value || '').trim();
                if (motivo.length < 3) return 'Informe um motivo com pelo menos 3 caracteres.';
                if (motivo.length > 500) return 'O motivo pode ter no máximo 500 caracteres.';
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Sim, estornar',
            cancelButtonText: 'Cancelar',
            position: 'top',
            width: '95%'
        });

        if (!res.isConfirmed) {
            isProcessing = false;
            focarBipe();
            return;
        }

        Swal.fire({
            title: 'Estornando...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            position: 'top'
        });

        const r = await apiFetch(`v1/carregamento/subida/estornar`, 'POST', {
            id_subida: idSubida,
            motivo: String(res.value).trim()
        });

        if (r.success) {
            await Swal.fire({
                icon: 'success',
                title: '✅ Subida estornada!',
                html: `<div style="text-align:center; font-size:0.85rem;">
                    ${numeroSubida}ª subida estornada; fotos e registro preservados.<br>
                    <span style="color:#64748b;">Quantidade: ${r.qt_estornada}</span>
                </div>`,
                timer: 2500,
                showConfirmButton: false,
                position: 'top'
            });

            await carregarLista();
        } else {
            throw new Error(r.error || 'Erro ao estornar');
        }

    } catch (e) {
        await Swal.fire({
            title: 'Erro',
            text: e.message,
            icon: 'error',
            position: 'top'
        });
    } finally {
        isProcessing = false;
        focarBipe();
    }
}

// ==========================================================================
// ESTORNAR TODAS AS SUBIDAS DE UM ITEM
// ==========================================================================
async function estornarTodasSubidas(iditem, nomeItem) {
    if (isProcessing) return;

    Swal.close();
    isProcessing = true;

    try {
        const res = await Swal.fire({
            title: 'Estornar TUDO?',
            html: `<div style="text-align:center;">
                <div style="font-weight:800; color:#1e293b;">${nomeItem}</div>
                <div style="font-size:0.85rem; color:#dc2626; margin-top:10px; font-weight:700;">
                    ⚠️ Esta ação estorna todo o saldo carregado deste item.
                </div>
                <div style="font-size:0.8rem; color:#64748b; margin-top:6px;">
                    O histórico e as fotos serão preservados para auditoria.
                </div>
            </div>`,
            input: 'textarea',
            inputLabel: 'Motivo obrigatório do estorno',
            inputPlaceholder: 'Descreva o motivo',
            inputAttributes: { maxlength: 500, 'aria-label': 'Motivo do estorno' },
            inputValidator: value => {
                const motivo = String(value || '').trim();
                if (motivo.length < 3) return 'Informe um motivo com pelo menos 3 caracteres.';
                if (motivo.length > 500) return 'O motivo pode ter no máximo 500 caracteres.';
            },
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Sim, estornar tudo',
            cancelButtonText: 'Cancelar',
            position: 'top',
            width: '95%'
        });

        if (!res.isConfirmed) {
            isProcessing = false;
            focarBipe();
            return;
        }

        Swal.fire({
            title: 'Estornando tudo...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading(),
            position: 'top'
        });

    const r = await apiFetch(`v1/carregamento/subidas-todas/estornar`, 'POST', {
    idembarque: String(state.embarque),
    iditem: String(iditem),
    motivo: String(res.value).trim()
});

        if (r.success) {
            await Swal.fire({
                icon: 'success',
                title: '✅ Tudo estornado!',
                html: `<div style="text-align:center; font-size:0.85rem;">
                    Quantidade estornada: ${r.quantidade_estornada}<br>
                    <span style="color:#64748b;">Histórico e fotos preservados.</span>
                </div>`,
                timer: 2500,
                showConfirmButton: false,
                position: 'top'
            });

            await carregarLista();
        } else {
            throw new Error(r.error || 'Erro ao estornar');
        }

    } catch (e) {
        await Swal.fire({
            title: 'Erro',
            text: e.message,
            icon: 'error',
            position: 'top'
        });
    } finally {
        isProcessing = false;
        focarBipe();
    }
}
// ==========================================================================
// EXPORTAÇÃO GLOBAL
// ==========================================================================
window.toggleMenuEmbarques = toggleMenuEmbarques;
window.selecionarEmbarqueManual = selecionarEmbarqueManual;
window.alterarOrdem = alterarOrdem;
window.toggleCamera = toggleCamera;
window.finalizarCargaOficial = finalizarCargaOficial;
window.selecionarDoca = selecionarDoca;
window.verHistoricoSubidas = verHistoricoSubidas;
window.focarBipe = focarBipe;
window.estornarSubidaEspecifica = estornarSubidaEspecifica;
window.estornarTodasSubidas = estornarTodasSubidas;
// Devolve o foco ao campo do bipe sempre que o último modal/toast fecha (coletor Bluetooth/acoplado)
(function () {
    let timer = null;
    new MutationObserver((mutations) => {
        const fechou = mutations.some(m => Array.from(m.removedNodes).some(n => n.classList && n.classList.contains('swal2-container')));
        if (!fechou) return;
        clearTimeout(timer);
        timer = setTimeout(() => {
            if (!document.querySelector('.swal2-container')) focarBipe();
        }, 80);
    }).observe(document.body, { childList: true });
})();