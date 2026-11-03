/**
 * Módulo: Avaliação de Desempenho C.H.A.P.D
 * Nutricional Distribuidora
 * Mobile-First • People Analytics • Matriz Anual
 */

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || (window.location.origin + (window.location.pathname.includes('/API/') ? '/API/v1' : '/v1'))).replace(/\/$/, '');
    let url = apiRoot + '/avaliacao-chapd/' + acao.replace(/^\/+/, '').replace(/^avaliacao-chapd\//, '');
    const options = { method: metodo, headers: {}, credentials: 'include' };
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) options.headers.Authorization = 'Bearer ' + token;

    if (metodo === 'GET' && body) {
        url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();
    } else if (body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, options);
    } catch (netErr) {
        throw new Error('Falha de conexão com a API. Verifique sua rede ou servidor.');
    }

    if (response.status === 401) {
        localStorage.removeItem('authToken');
        sessionStorage.clear();
        const portalBase = window.location.pathname.startsWith('/API/') ? '/API' : '';
        window.location.href = portalBase + '/portal/login.php?expired=1';
        throw new Error('Sessão expirada. Redirecionando para login...');
    }

    const text = await response.text();
    let data = {};
    try {
        data = text ? JSON.parse(text) : {};
    } catch {
        data = { mensagem: text || 'Resposta inválida do servidor' };
    }
    if (!response.ok) {
        throw new Error(data.mensagem || data.error || `Erro HTTP ${response.status}`);
    }
    return data;
};

// ==========================================
// ESTADO GLOBAL DA APLICAÇÃO
// ==========================================
const estado = {
    abaAtiva: 'avaliar',
    anoAtual: new Date().getFullYear(),
    mesAtual: new Date().getMonth() + 1,
    usuario: null,
    colaboradores: [],
    perguntas: [],
    escalaPontos: [],
    conceitosLetras: [],
    conjuntosLetras: [],
    faixasClassificacao: [],
    
    // Estado do Wizard de Avaliação
    colaboradorSelecionado: null,
    anoSelecionado: new Date().getFullYear(),
    mesSelecionado: new Date().getMonth() + 1,
    indicePerguntaAtual: 0,
    respostas: {}, // { idpergunta: { pontuacao: 10, observacao_item: '' } }
    conceitoLetraSelecionada: '',
    
    // Estado do Módulo de Fotos
    fotoStream: null,
    fotoBlob: null,
    fotoArquivo: null,
    modoFoto: 'webcam',
    
    // Cache de Dados
    matrizAnual: [],
    historico: [],
    perguntasGestao: []
};

// Atalho DOM
const $ = (id) => document.getElementById(id);

// SweetAlert2 Base
const swalBase = {
    confirmButtonColor: '#059669',
    cancelButtonColor: '#94a3b8',
    customClass: {
        popup: 'rounded-3xl border border-slate-100 shadow-2xl font-sans text-sm',
        confirmButton: 'rounded-xl font-black px-5 py-2.5 text-xs uppercase tracking-wider',
        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs uppercase tracking-wider'
    }
};

// ==========================================
// INICIALIZAÇÃO
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
    popularSelectMeses();
    initModernTooltipsChapd();
    await carregarContexto();
});

/**
 * Preenche os selects de meses (1..12)
 */
function popularSelectMeses() {
    const meses = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
    ];

    const selMes = $('selMes');
    const filtroHistMes = $('filtroHistMes');
    const filtroDashMes = $('filtroDashMes');

    if (selMes) selMes.innerHTML = '';
    
    meses.forEach((nome, idx) => {
        const num = idx + 1;
        // Select de Avaliação
        if (selMes) {
            const opt = document.createElement('option');
            opt.value = num;
            opt.textContent = `${num.toString().padStart(2, '0')} - ${nome}`;
            if (num === estado.mesAtual) opt.selected = true;
            selMes.appendChild(opt);
        }
        // Filtro Histórico
        if (filtroHistMes) {
            const optH = document.createElement('option');
            optH.value = num;
            optH.textContent = nome;
            filtroHistMes.appendChild(optH);
        }
        // Filtro Dashboard
        if (filtroDashMes) {
            const optD = document.createElement('option');
            optD.value = num;
            optD.textContent = nome;
            filtroDashMes.appendChild(optD);
        }
    });
}

/**
 * Carrega contexto do backend (Colaboradores ativos ERP, Perguntas, Letras, Escala)
 */
async function carregarContexto() {
    try {
        const data = await apiFetch('contexto');

        if (!data.sucesso) {
            throw new Error(data.mensagem || 'Falha ao obter contexto da avaliação.');
        }

        estado.usuario = data.usuario;
        estado.colaboradores = data.colaboradores || [];
        estado.perguntas = data.perguntas || [];
        estado.escalaPontos = data.escala_pontos || [];
        estado.conceitosLetras = data.conceitos_letras || [];
        estado.conjuntosLetras = data.conjuntos_letras || [];
        estado.faixasClassificacao = data.faixas_classificacao || [];

        // Preenche o Select de Colaboradores
        popularSelectColaboradores();

        // Renderiza as Letras no Fechamento
        renderizarGradeConceitosLetras();

    } catch (err) {
        console.error('Erro ao carregar contexto:', err);
        const isPermissao = (err.message && (err.message.includes('não autorizado') || err.message.includes('403')));
        Swal.fire({
            ...swalBase,
            icon: isPermissao ? 'warning' : 'error',
            title: isPermissao ? 'Acesso Restrito' : 'Erro de Conexão',
            text: isPermissao ? 'Você não possui permissão para acessar a Avaliação de Desempenho C.H.A.P.D. Solicite liberação ao administrador.' : err.message
        });
    }
}

/**
 * Inicializa Tooltips Modernos e Ricos no Hover
 */
function initModernTooltipsChapd() {
    const tooltip = $('modernTooltipChapd');
    const badgeEl = $('tooltipChapdBadge');
    const titleEl = $('tooltipChapdTitle');
    const descEl = $('tooltipChapdDesc');
    if (!tooltip) return;

    let activeTarget = null;

    document.addEventListener('mouseover', (e) => {
        const el = e.target.closest('[data-tip-title], [data-tip-desc]');
        if (!el) return;

        activeTarget = el;
        const title = el.getAttribute('data-tip-title') || '';
        const desc = el.getAttribute('data-tip-desc') || '';
        const badge = el.getAttribute('data-tip-badge') || 'INFORMAÇÃO';

        if (titleEl) titleEl.textContent = title;
        if (descEl) descEl.textContent = desc;
        if (badgeEl) badgeEl.textContent = badge;

        tooltip.classList.remove('hidden');
        posicionarTooltip(e);
    });

    document.addEventListener('mousemove', (e) => {
        if (!activeTarget) return;
        posicionarTooltip(e);
    });

    document.addEventListener('mouseout', (e) => {
        const el = e.target.closest('[data-tip-title], [data-tip-desc]');
        if (el && el === activeTarget) {
            activeTarget = null;
            tooltip.classList.add('hidden');
        }
    });

    function posicionarTooltip(e) {
        const x = e.clientX;
        const y = e.clientY;
        const gap = 16;
        const tipRect = tooltip.getBoundingClientRect();
        const winW = window.innerWidth;
        const winH = window.innerHeight;

        let left = x + gap;
        let top = y + gap;

        if (left + tipRect.width > winW - 12) {
            left = Math.max(12, x - tipRect.width - gap);
        }
        if (top + tipRect.height > winH - 12) {
            top = Math.max(12, y - tipRect.height - gap);
        }

        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;
    }
}

/**
 * Preenche o select de colaboradores com os 50 funcionários ativos do ERP
 */
function popularSelectColaboradores() {
    const sel = $('selColaborador');
    if (!sel) return;

    sel.innerHTML = '<option value="">Selecione um colaborador ativo no ERP...</option>';

    estado.colaboradores.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.idcliforemp;
        opt.textContent = `${c.nome} (${c.cargo} • Filial ${c.idfilial})`;
        sel.appendChild(opt);
    });
}

/**
 * Converte caminhos relativos de foto (ex: uploads/chapd/colab_1.jpg)
 * na URL absoluta correta baseada na origem e prefixo da API/Portal
 */
function formatarUrlFoto(caminho) {
    if (!caminho) return '';
    if (caminho.startsWith('http://') || caminho.startsWith('https://') || caminho.startsWith('data:') || caminho.startsWith('blob:')) {
        return caminho;
    }
    const cleanPath = caminho.replace(/^\/+/, '');
    if (window.API_URL) {
        const apiBase = window.API_URL.replace(/\/v1\/?$/, '').replace(/\/$/, '');
        return `${apiBase}/${cleanPath}`;
    }
    const basePrefix = window.location.pathname.includes('/API/') ? '/API' : '';
    return `${window.location.origin}${basePrefix}/${cleanPath}`;
}

// ==========================================
// WIZARD ETAPA 1: SELEÇÃO DO COLABORADOR
// ==========================================
function aoSelecionarColaborador() {
    const id = parseInt($('selColaborador').value);
    const colab = estado.colaboradores.find(c => c.idcliforemp === id);

    const cardResumo = $('cardResumoColab');
    if (!colab) {
        cardResumo?.classList.add('hidden');
        estado.colaboradorSelecionado = null;
        return;
    }

    estado.colaboradorSelecionado = colab;

    // Preenche card de resumo
    $('colabNome').textContent = colab.nome;
    $('colabCargo').innerHTML = `<i class="fa-solid fa-briefcase text-emerald-600"></i> ${colab.cargo || 'Não informado'}`;
    $('colabFilial').innerHTML = `<i class="fa-solid fa-building text-emerald-600"></i> Filial ${colab.idfilial}`;
    $('colabAdmissao').innerHTML = `<i class="fa-solid fa-calendar text-emerald-600"></i> Adm: ${colab.data_admissao ? formatarDataBR(colab.data_admissao) : '--'}`;

    // Badge de Perfil Customizado na Área Separada
    const badgePerfil = $('badgePerfilCustom');
    if (badgePerfil) {
        if (colab.perfil_editado) {
            badgePerfil.classList.remove('hidden');
        } else {
            badgePerfil.classList.add('hidden');
        }
    }

    // Avatar com Foto ou Iniciais
    const nomes = colab.nome.trim().split(' ');
    const iniciais = (nomes[0][0] + (nomes.length > 1 ? nomes[nomes.length - 1][0] : '')).toUpperCase();
    const colabIniciais = $('colabIniciais');
    const colabFotoImg = $('colabFotoImg');
    const colabFotoBtnTexto = $('colabFotoBtnTexto');

    if (colab.foto_url) {
        if (colabFotoImg) {
            colabFotoImg.src = formatarUrlFoto(colab.foto_url);
            colabFotoImg.classList.remove('hidden');
            colabFotoImg.onerror = () => {
                colabFotoImg.classList.add('hidden');
                if (colabIniciais) colabIniciais.classList.remove('hidden');
            };
        }
        if (colabIniciais) colabIniciais.classList.add('hidden');
        if (colabFotoBtnTexto) colabFotoBtnTexto.textContent = 'Alterar Foto';
    } else {
        if (colabFotoImg) {
            colabFotoImg.src = '';
            colabFotoImg.classList.add('hidden');
        }
        if (colabIniciais) {
            colabIniciais.textContent = iniciais;
            colabIniciais.classList.remove('hidden');
        }
        if (colabFotoBtnTexto) colabFotoBtnTexto.textContent = 'Foto';
    }

    // Sugere Setor (prioriza personalização se houver)
    const inputSetor = $('inputSetor');
    if (inputSetor) {
        if (colab.setor_custom) {
            inputSetor.value = colab.setor_custom;
        } else if (!inputSetor.value) {
            const cargoUpper = (colab.cargo || '').toUpperCase();
            if (cargoUpper.includes('COMERCIAL') || cargoUpper.includes('VEND')) inputSetor.value = 'Comercial';
            else if (cargoUpper.includes('CARGA') || cargoUpper.includes('MOTOR') || cargoUpper.includes('EXPED')) inputSetor.value = 'Logística';
            else if (cargoUpper.includes('TI') || cargoUpper.includes('INFOR')) inputSetor.value = 'TI';
            else if (cargoUpper.includes('FINANC') || cargoUpper.includes('CONT')) inputSetor.value = 'Financeiro';
            else inputSetor.value = 'Operacional';
        }
    }

    // Sugere Célula (prioriza personalização se houver)
    const inputCelula = $('inputCelula');
    if (inputCelula) {
        if (colab.celula_custom) {
            inputCelula.value = colab.celula_custom;
        } else if (!inputCelula.value) {
            inputCelula.value = colab.idfilial === 1 ? 'Matriz SC' : (colab.idfilial === 6 ? 'Filial PR' : `Filial ${colab.idfilial}`);
        }
    }

    cardResumo?.classList.remove('hidden');
}

// ==========================================
// MÓDULO DE EDIÇÃO DO PERFIL (ÁREA SEPARADA ERP)
// ==========================================
function abrirModalEditarPerfilColab(idcliforemp = null) {
    let c = null;
    if (idcliforemp) {
        c = estado.colaboradores.find(x => x.idcliforemp === parseInt(idcliforemp));
    }
    if (!c) {
        c = estado.colaboradorSelecionado;
    }

    if (!c) {
        Swal.fire({
            ...swalBase,
            icon: 'info',
            title: 'Selecione o Colaborador',
            text: 'Primeiro selecione um colaborador na lista para editar os dados.'
        });
        return;
    }

    $('editPerfilIdcliforemp').value = c.idcliforemp;
    $('modalPerfilSubtitulo').textContent = `${c.nome} (Cód. ${c.idcliforemp}) • Área C.H.A.P.D`;
    $('editPerfilNomeErpLabel').textContent = `ERP: ${c.nome_erp || c.nome}`;
    $('editPerfilCargoErpLabel').textContent = `ERP: ${c.cargo_erp || c.cargo}`;

    $('editPerfilNome').value = (c.nome !== c.nome_erp) ? c.nome : '';
    $('editPerfilCargo').value = (c.cargo !== c.cargo_erp) ? c.cargo : '';
    $('editPerfilSetor').value = c.setor_custom || (estado.colaboradorSelecionado?.idcliforemp === c.idcliforemp ? $('inputSetor')?.value || '' : '');
    $('editPerfilCelula').value = c.celula_custom || (estado.colaboradorSelecionado?.idcliforemp === c.idcliforemp ? $('inputCelula')?.value || '' : '');
    $('editPerfilFilial').value = c.idfilial || 1;
    $('editPerfilAdmissao').value = c.data_admissao || '';
    $('editPerfilObservacoes').value = c.perfil_observacoes || '';

    $('modalEditarPerfilColab')?.classList.remove('hidden');
}

function fecharModalEditarPerfilColab() {
    $('modalEditarPerfilColab')?.classList.add('hidden');
}

async function salvarPerfilColaborador(event) {
    event.preventDefault();

    const id = parseInt($('editPerfilIdcliforemp').value);
    if (!id) return;

    const btn = $('btnSalvarPerfilColab');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gravando...';

    const payload = {
        idcliforemp: id,
        nome_custom: $('editPerfilNome').value.trim(),
        cargo_custom: $('editPerfilCargo').value.trim(),
        setor_custom: $('editPerfilSetor').value.trim(),
        celula_custom: $('editPerfilCelula').value.trim(),
        idfilial_custom: parseInt($('editPerfilFilial').value) || null,
        data_admissao_custom: $('editPerfilAdmissao').value || null,
        observacoes: $('editPerfilObservacoes').value.trim()
    };

    try {
        const data = await apiFetch('colaborador/salvar-perfil', 'POST', payload);

        if (!data.sucesso) {
            throw new Error(data.mensagem || 'Falha ao salvar dados do colaborador.');
        }

        const colabAtualizado = data.colaborador;

        // Atualiza item no array global
        const idx = estado.colaboradores.findIndex(x => x.idcliforemp === id);
        if (idx !== -1) {
            estado.colaboradores[idx] = { ...estado.colaboradores[idx], ...colabAtualizado };
        }

        // Atualiza colaborador selecionado
        if (estado.colaboradorSelecionado && estado.colaboradorSelecionado.idcliforemp === id) {
            estado.colaboradorSelecionado = { ...estado.colaboradorSelecionado, ...colabAtualizado };
            aoSelecionarColaborador();
        }

        // Atualiza texto da option do select
        const sel = $('selColaborador');
        if (sel) {
            const opt = sel.querySelector(`option[value="${id}"]`);
            if (opt) {
                opt.textContent = `${colabAtualizado.nome} (${colabAtualizado.cargo} • Filial ${colabAtualizado.idfilial})`;
            }
        }

        // Atualiza grid de colaboradores
        if (typeof renderizarGridColaboradores === 'function') {
            renderizarGridColaboradores();
        }

        fecharModalEditarPerfilColab();

        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Perfil Atualizado!',
            text: 'Os dados foram salvos na área exclusiva C.H.A.P.D e vinculados ao ERP com sucesso.',
            timer: 2000,
            showConfirmButton: false
        });

    } catch (err) {
        console.error('Erro ao salvar perfil:', err);
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Erro ao Salvar',
            text: err.message
        });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Salvar Alterações';
    }
}

async function restaurarPadraoErpColaborador() {
    const id = parseInt($('editPerfilIdcliforemp').value);
    if (!id) return;

    const confirm = await Swal.fire({
        ...swalBase,
        title: 'Restaurar Dados Originais?',
        text: 'Deseja limpar as edições customizadas e voltar a exibir o Nome, Cargo e Lotação nativos do ERP?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Restaurar ERP',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        const data = await apiFetch('colaborador/salvar-perfil', 'POST', {
            idcliforemp: id,
            restaurar_padrao: true
        });

        if (!data.sucesso) throw new Error(data.mensagem);

        const colabAtualizado = data.colaborador;

        const idx = estado.colaboradores.findIndex(x => x.idcliforemp === id);
        if (idx !== -1) {
            estado.colaboradores[idx] = { ...estado.colaboradores[idx], ...colabAtualizado };
        }

        if (estado.colaboradorSelecionado && estado.colaboradorSelecionado.idcliforemp === id) {
            estado.colaboradorSelecionado = { ...estado.colaboradorSelecionado, ...colabAtualizado };
            aoSelecionarColaborador();
        }

        // Atualiza option do select
        const sel = $('selColaborador');
        if (sel) {
            const opt = sel.querySelector(`option[value="${id}"]`);
            if (opt) {
                opt.textContent = `${colabAtualizado.nome} (${colabAtualizado.cargo} • Filial ${colabAtualizado.idfilial})`;
            }
        }

        // Atualiza grid de colaboradores
        if (typeof renderizarGridColaboradores === 'function') {
            renderizarGridColaboradores();
        }

        fecharModalEditarPerfilColab();

        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Restaurado!',
            text: 'Os dados do colaborador voltaram ao padrão original do ERP.',
            timer: 2000,
            showConfirmButton: false
        });

    } catch (err) {
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Erro',
            text: err.message
        });
    }
}

// ==========================================
// MÓDULO DE FOTO DO COLABORADOR (CÂMERA / ARQUIVO)
// ==========================================
function abrirModalCapturaFoto(idcliforemp = null) {
    let colab = null;
    if (idcliforemp) {
        colab = estado.colaboradores.find(c => c.idcliforemp === parseInt(idcliforemp));
        if (colab) {
            estado.colaboradorSelecionado = colab;
            const sel = $('selColaborador');
            if (sel && sel.value !== String(idcliforemp)) {
                sel.value = String(idcliforemp);
                aoSelecionarColaborador();
            }
        }
    } else {
        colab = estado.colaboradorSelecionado;
    }

    if (!colab) {
        Swal.fire({
            ...swalBase,
            icon: 'info',
            title: 'Selecione o Colaborador',
            text: 'Primeiro selecione um colaborador na lista para vincular a foto.'
        });
        return;
    }

    $('modalFotoNomeColab').textContent = `${colab.nome} (Cód. ${colab.idcliforemp})`;
    estado.fotoBlob = null;
    estado.fotoArquivo = null;
    $('btnSalvarFotoUpload').disabled = true;

    $('modalFotoColab')?.classList.remove('hidden');
    alternarModoFoto('webcam');
}

function fecharModalCapturaFoto() {
    pararWebcam();
    $('modalFotoColab')?.classList.add('hidden');
}

function alternarModoFoto(modo) {
    estado.modoFoto = modo;
    const btnWebcam = $('abaFotoModoWebcam');
    const btnArquivo = $('abaFotoModoArquivo');
    const painelWebcam = $('painelFotoWebcam');
    const painelArquivo = $('painelFotoArquivo');

    if (modo === 'webcam') {
        btnWebcam.className = 'flex-1 py-2 rounded-lg bg-white text-emerald-800 shadow-xs flex items-center justify-center gap-1.5 transition font-bold';
        btnArquivo.className = 'flex-1 py-2 rounded-lg text-slate-600 flex items-center justify-center gap-1.5 transition font-bold';
        painelWebcam.classList.remove('hidden');
        painelArquivo.classList.add('hidden');
        if (!estado.fotoBlob) {
            iniciarWebcam();
        }
    } else {
        pararWebcam();
        btnArquivo.className = 'flex-1 py-2 rounded-lg bg-white text-emerald-800 shadow-xs flex items-center justify-center gap-1.5 transition font-bold';
        btnWebcam.className = 'flex-1 py-2 rounded-lg text-slate-600 flex items-center justify-center gap-1.5 transition font-bold';
        painelArquivo.classList.remove('hidden');
        painelWebcam.classList.add('hidden');
    }
}

async function iniciarWebcam() {
    try {
        pararWebcam();
        const constraints = {
            video: {
                facingMode: 'user',
                width: { ideal: 640 },
                height: { ideal: 480 }
            },
            audio: false
        };

        const stream = await navigator.mediaDevices.getUserMedia(constraints);
        estado.fotoStream = stream;

        const video = $('videoWebcam');
        if (video) {
            video.srcObject = stream;
            video.classList.remove('hidden');
        }

        $('overlayCameraInativa')?.classList.add('hidden');
        $('previewFotoCapturada')?.classList.add('hidden');
        $('btnDispararFoto')?.classList.remove('hidden');
        $('btnRepetirFoto')?.classList.add('hidden');

    } catch (err) {
        console.warn('Erro ao acessar webcam:', err);
        Swal.fire({
            ...swalBase,
            icon: 'warning',
            title: 'Câmera Não Disponível',
            text: 'Não foi possível acessar a câmera do dispositivo. Verifique as permissões do navegador ou utilize a opção "Enviar Arquivo".'
        });
        alternarModoFoto('arquivo');
    }
}

function pararWebcam() {
    if (estado.fotoStream) {
        estado.fotoStream.getTracks().forEach(track => track.stop());
        estado.fotoStream = null;
    }
    const video = $('videoWebcam');
    if (video) video.srcObject = null;
}

function capturarFotoWebcam() {
    const video = $('videoWebcam');
    const canvas = $('canvasWebcam');
    const preview = $('previewFotoCapturada');

    if (!video || !canvas || !video.videoWidth) {
        Swal.fire({ ...swalBase, icon: 'info', title: 'Aguarde a câmera', text: 'Aguarde o carregamento do vídeo da câmera.' });
        return;
    }

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

    canvas.toBlob((blob) => {
        if (!blob) return;
        estado.fotoBlob = blob;
        estado.fotoArquivo = null;

        const url = URL.createObjectURL(blob);
        preview.src = url;
        preview.classList.remove('hidden');
        video.classList.add('hidden');

        $('btnDispararFoto').classList.add('hidden');
        $('btnRepetirFoto').classList.remove('hidden');
        $('btnSalvarFotoUpload').disabled = false;

        pararWebcam();
    }, 'image/jpeg', 0.92);
}

function reiniciarCapturaWebcam() {
    estado.fotoBlob = null;
    $('btnSalvarFotoUpload').disabled = true;
    iniciarWebcam();
}

function aoSelecionarArquivoFoto(event) {
    const file = event.target.files && event.target.files[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Arquivo Inválido', text: 'Selecione apenas arquivos de imagem (JPEG, PNG ou WEBP).' });
        return;
    }

    if (file.size > 10 * 1024 * 1024) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Arquivo Muito Grande', text: 'O tamanho da foto deve ser no máximo 10MB.' });
        return;
    }

    estado.fotoArquivo = file;
    estado.fotoBlob = null;

    const reader = new FileReader();
    reader.onload = (e) => {
        $('previewFotoArquivo').src = e.target.result;
        $('previewArquivoNome').textContent = file.name;
        $('previewArquivoTamanho').textContent = `${(file.size / 1024).toFixed(1)} KB`;
        $('previewArquivoContainer').classList.remove('hidden');
        $('btnSalvarFotoUpload').disabled = false;
    };
    reader.readAsDataURL(file);
}

async function salvarFotoColaborador() {
    if (!estado.colaboradorSelecionado) return;

    const fileToUpload = estado.fotoArquivo || estado.fotoBlob;
    if (!fileToUpload) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Nenhuma foto', text: 'Capture ou envie uma foto antes de salvar.' });
        return;
    }

    const btn = $('btnSalvarFotoUpload');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

    const formData = new FormData();
    formData.append('idcliforemp', estado.colaboradorSelecionado.idcliforemp);
    if (estado.fotoArquivo) {
        formData.append('foto', estado.fotoArquivo);
    } else {
        formData.append('foto', estado.fotoBlob, `colab_${estado.colaboradorSelecionado.idcliforemp}.jpg`);
    }

    try {
        const apiRoot = (window.API_URL || (window.location.origin + (window.location.pathname.includes('/API/') ? '/API/v1' : '/v1'))).replace(/\/$/, '');
        const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
        const headers = {};
        if (token) headers.Authorization = 'Bearer ' + token;

        const resp = await fetch(`${apiRoot}/avaliacao-chapd/upload-foto`, {
            method: 'POST',
            headers,
            body: formData,
            credentials: 'include'
        });

        const resJson = await resp.json();
        if (!resp.ok || !resJson.sucesso) {
            throw new Error(resJson.mensagem || 'Falha ao salvar foto do colaborador.');
        }

        const novaFotoUrl = resJson.foto_url;
        estado.colaboradorSelecionado.foto_url = novaFotoUrl;

        // Atualiza no cache de colaboradores
        const colabItem = estado.colaboradores.find(c => c.idcliforemp === estado.colaboradorSelecionado.idcliforemp);
        if (colabItem) colabItem.foto_url = novaFotoUrl;

        // Atualiza Avatar do Card com URL absoluta e Cache-Bust para refletir na hora!
        const urlFinal = formatarUrlFoto(resJson.url_completa || novaFotoUrl) + '?t=' + Date.now();
        const imgEl = $('colabFotoImg');
        const iniciaisEl = $('colabIniciais');
        if (imgEl && iniciaisEl) {
            imgEl.src = urlFinal;
            imgEl.classList.remove('hidden');
            imgEl.onerror = () => {
                imgEl.classList.add('hidden');
                if (iniciaisEl) iniciaisEl.classList.remove('hidden');
            };
            iniciaisEl.classList.add('hidden');
        }
        $('colabFotoBtnTexto').textContent = 'Alterar Foto';

        // Atualiza a Grid de Colaboradores
        if (typeof renderizarGridColaboradores === 'function') {
            renderizarGridColaboradores();
        }

        fecharModalCapturaFoto();

        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Foto Salva!',
            text: 'Foto vinculada com sucesso ao prontuário do colaborador no C.H.A.P.D.',
            timer: 2000,
            showConfirmButton: false
        });

    } catch (err) {
        console.error('Erro upload foto:', err);
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro no Upload', text: err.message });
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-check"></i> Salvar Foto no Prontuário';
    }
}

/**
 * Valida a etapa 1 e inicia o questionário pergunta por pergunta
 */
function iniciarQuestionario() {
    if (!estado.colaboradorSelecionado) {
        Swal.fire({
            ...swalBase,
            icon: 'warning',
            title: 'Colaborador Obrigatório',
            text: 'Por favor, selecione um colaborador antes de iniciar a avaliação.'
        });
        return;
    }

    if (estado.perguntas.length === 0) {
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Nenhuma Pergunta Cadastrada',
            text: 'Não há perguntas ativas cadastradas para avaliação.'
        });
        return;
    }

    estado.anoSelecionado = parseInt($('selAno').value);
    estado.mesSelecionado = parseInt($('selMes').value);
    estado.indicePerguntaAtual = 0;
    estado.respostas = {}; // Reinicia respostas

    // Transição de Telas
    $('stepSelecao').classList.add('hidden');
    $('stepQuestionario').classList.remove('hidden');
    $('stepSintese').classList.add('hidden');

    renderizarPerguntaAtual();
}

// ==========================================
// WIZARD ETAPA 2: QUESTIONÁRIO INTERATIVO
// ==========================================
function renderizarPerguntaAtual() {
    const total = estado.perguntas.length;
    const idx = estado.indicePerguntaAtual;
    const p = estado.perguntas[idx];

    if (!p) return;

    // Atualiza Barra e Textos de Progresso
    const numAtual = idx + 1;
    const perc = Math.round((numAtual / total) * 100);
    $('progressoTexto').textContent = `Pergunta ${numAtual} de ${total}`;
    $('progressoPercentual').textContent = `${perc}% Concluído`;
    $('barraProgresso').style.width = `${perc}%`;

    // Atualiza Cabeçalho da Pergunta
    $('badgeCodigo').textContent = `#${p.codigo || (p.pilar + numAtual)}`;
    $('textoPergunta').textContent = p.pergunta;
    $('descricaoAuxiliar').textContent = p.descricao_auxiliar || 'Avalie o comportamento habitual e consistente do colaborador neste critério.';

    // Estilo do Pilar C.H.A.P.D
    const nomesPilares = {
        'C': 'Conhecimento Técnico & Processos',
        'H': 'Habilidade & Qualidade de Execução',
        'A': 'Atitude & Proatividade',
        'P': 'Postura & Relacionamento',
        'D': 'Disciplina & Cumprimento de Normas'
    };
    const iconesPilares = {
        'C': 'fa-brain',
        'H': 'fa-screwdriver-wrench',
        'A': 'fa-bolt',
        'P': 'fa-handshake',
        'D': 'fa-shield-halved'
    };

    const pilar = (p.pilar || 'C').toUpperCase();
    const badgePilar = $('badgePilar');
    badgePilar.className = `pilar-badge pilar-${pilar}`;
    badgePilar.innerHTML = `<i class="fa-solid ${iconesPilares[pilar] || 'fa-tag'}"></i> <span>${nomesPilares[pilar] || pilar}</span>`;

    // Limpa / Restaura Seleção dos 5 Botões de Nota
    [10, 8, 6, 4, 2].forEach(v => {
        const btn = $(`btnNota${v}`);
        if (btn) {
            btn.classList.remove('sel-10', 'sel-8', 'sel-6', 'sel-4', 'sel-2');
        }
    });

    const respostaSalva = estado.respostas[p.id];
    if (respostaSalva) {
        const btnSel = $(`btnNota${respostaSalva.pontuacao}`);
        if (btnSel) {
            btnSel.classList.add(`sel-${respostaSalva.pontuacao}`);
        }
        $('obsPerguntaAtual').value = respostaSalva.observacao_item || '';
    } else {
        $('obsPerguntaAtual').value = '';
    }

    // Botões de Navegação
    $('btnPerguntaAnterior').disabled = (idx === 0);
    $('btnPerguntaProxima').textContent = (idx === total - 1) ? 'Revisar & Concluir' : 'Próxima';

    // Pontos Parciais
    calcularPontosParciais();
}

/**
 * Ao clicar em uma das notas (10, 8, 6, 4 ou 2)
 */
function responderPergunta(nota) {
    const p = estado.perguntas[estado.indicePerguntaAtual];
    if (!p) return;

    // Remove seleção anterior
    [10, 8, 6, 4, 2].forEach(v => {
        const btn = $(`btnNota${v}`);
        if (btn) btn.classList.remove('sel-10', 'sel-8', 'sel-6', 'sel-4', 'sel-2');
    });

    // Aplica seleção visual imediata
    const btnAtivo = $(`btnNota${nota}`);
    if (btnAtivo) btnAtivo.classList.add(`sel-${nota}`);

    // Salva no estado
    const obs = $('obsPerguntaAtual').value.trim();
    estado.respostas[p.id] = {
        idpergunta: p.id,
        pilar: p.pilar,
        pontuacao: nota,
        observacao_item: obs
    };

    calcularPontosParciais();

    // Feedback sonoro/tátil leve e avança suavemente após 220ms
    setTimeout(() => {
        perguntaProxima();
    }, 220);
}

function perguntaAnterior() {
    salvarObsPerguntaAtual();
    if (estado.indicePerguntaAtual > 0) {
        estado.indicePerguntaAtual--;
        renderizarPerguntaAtual();
    }
}

function perguntaProxima() {
    salvarObsPerguntaAtual();
    const total = estado.perguntas.length;

    // Verifica se a pergunta atual foi respondida
    const pAtual = estado.perguntas[estado.indicePerguntaAtual];
    if (!estado.respostas[pAtual.id]) {
        Swal.fire({
            ...swalBase,
            icon: 'info',
            title: 'Pontuação Necessária',
            text: 'Por favor, selecione uma das opções de nota (10, 8, 6, 4 ou 2) para continuar.'
        });
        return;
    }

    if (estado.indicePerguntaAtual < total - 1) {
        estado.indicePerguntaAtual++;
        renderizarPerguntaAtual();
    } else {
        // Chegou ao fim do questionário! Avança para o Step 3 de Síntese
        exibirStepSintese();
    }
}

function salvarObsPerguntaAtual() {
    const p = estado.perguntas[estado.indicePerguntaAtual];
    if (p && estado.respostas[p.id]) {
        estado.respostas[p.id].observacao_item = $('obsPerguntaAtual').value.trim();
    }
}

function calcularPontosParciais() {
    let soma = 0;
    Object.values(estado.respostas).forEach(r => {
        soma += (r.pontuacao || 0);
    });
    $('pontosParciais').textContent = soma;
    return soma;
}

// ==========================================
// WIZARD ETAPA 3: SÍNTESE, CONCEITO E FECHAMENTO
// ==========================================
/**
 * Calcula o Conjunto Duplo de Letras conforme a metodologia Nutricional C.H.A.P.D:
 * - Soma Máxima: Conjunto A & B (>= 115 pontos / Alta Performance)
 * - Meio dos Pontos: Conjunto C & D (85 a 114.9 pontos / Médio Potencial)
 * - Últimos Abaixo: Conjunto E & F (< 85 pontos / Atenção Crítica / Necessita PDI)
 */
function calcularConjuntoDuplo(pontos) {
    if (pontos >= 115) {
        return {
            codigo: 'AB',
            letras: 'A & B',
            titulo: 'Alta Performance / Destaque',
            descricao: 'Soma Máxima (≥ 115 pts). Domínio dos processos, postura exemplar e entrega com diferencial.',
            badge: 'CONJUNTO A & B (≥ 115 PTS)',
            classBadge: 'bg-emerald-100 text-emerald-900 border border-emerald-300',
            sugestaoLetra: pontos >= 130 ? 'A' : 'B'
        };
    } else if (pontos >= 85) {
        return {
            codigo: 'CD',
            letras: 'C & D',
            titulo: 'Médio Potencial / Em Desenvolvimento',
            descricao: 'Meio dos Pontos (85 a 114 pts). Cumpre rotinas satisfatoriamente, com potencial para ampliar proatividade.',
            badge: 'CONJUNTO C & D (85 A 114 PTS)',
            classBadge: 'bg-indigo-100 text-indigo-900 border border-indigo-300',
            sugestaoLetra: pontos >= 100 ? 'C' : 'D'
        };
    } else {
        return {
            codigo: 'EF',
            letras: 'E & F',
            titulo: 'Atenção Crítica / Necessita PDI',
            descricao: 'Últimos Abaixo (< 85 pts). Desempenho irregular ou lacunas críticas que exigem plano individual imediato.',
            badge: 'CONJUNTO E & F (< 85 PTS)',
            classBadge: 'bg-rose-100 text-rose-900 border border-rose-300',
            sugestaoLetra: pontos >= 60 ? 'E' : 'F'
        };
    }
}

function exibirStepSintese() {
    $('stepSelecao').classList.add('hidden');
    $('stepQuestionario').classList.add('hidden');
    $('stepSintese').classList.remove('hidden');

    // 1. Apura pontuação total e por pilares
    let totalPontos = 0;
    const pilares = { 'C': 0, 'H': 0, 'A': 0, 'P': 0, 'D': 0 };

    Object.values(estado.respostas).forEach(r => {
        totalPontos += r.pontuacao;
        const pil = (r.pilar || 'C').toUpperCase();
        if (pilares[pil] !== undefined) pilares[pil] += r.pontuacao;
    });

    const maxPossivel = estado.perguntas.length * 10;
    const perc = maxPossivel > 0 ? ((totalPontos / maxPossivel) * 100).toFixed(1) : 0;

    // Placar
    $('resumoPontosTotal').textContent = `${totalPontos} / ${maxPossivel}`;
    $('resumoPercentual').textContent = `${perc}% de aproveitamento geral`;

    // Classificação por Faixa de Pontos da Planilha
    let classificacao = 'Baixo potencial';
    let badgeClass = 'bg-rose-100 text-rose-800 border border-rose-200';

    if (totalPontos >= 125) {
        classificacao = 'Excelente';
        badgeClass = 'bg-emerald-100 text-emerald-800 border border-emerald-300';
    } else if (totalPontos >= 100) {
        classificacao = 'Aceitável e com potencial';
        badgeClass = 'bg-blue-100 text-blue-800 border border-blue-200';
    } else if (totalPontos >= 85) {
        classificacao = 'Médio potencial';
        badgeClass = 'bg-amber-100 text-amber-800 border border-amber-200';
    }

    $('resumoClassificacao').textContent = classificacao;
    const badgeFaixa = $('resumoBadgeFaixa');
    badgeFaixa.className = `mt-2 text-xs font-black inline-flex items-center gap-1.5 px-3 py-1 rounded-xl ${badgeClass}`;
    badgeFaixa.innerHTML = `<i class="fa-solid fa-certificate"></i> Faixa: ${classificacao}`;

    // Conjunto Duplo conforme a regra de ouro do usuário (A&B, C&D, E&F)
    const conjunto = calcularConjuntoDuplo(totalPontos);
    const elConjunto = $('resumoConjuntoDuplo');
    if (elConjunto) elConjunto.textContent = conjunto.letras;
    const badgeConjunto = $('resumoConjuntoBadge');
    if (badgeConjunto) {
        badgeConjunto.className = `mt-2 text-[11px] font-black inline-flex items-center gap-1 px-2.5 py-1 rounded-xl ${conjunto.classBadge}`;
        badgeConjunto.innerHTML = `<i class="fa-solid fa-layer-group"></i> ${conjunto.badge}`;
    }

    // Barras dos 5 Pilares
    $('pilarTotalC').textContent = `${pilares.C} pts`;
    $('pilarBarraC').style.width = `${Math.min(100, (pilares.C / 40) * 100)}%`;

    $('pilarTotalH').textContent = `${pilares.H} pts`;
    $('pilarBarraH').style.width = `${Math.min(100, (pilares.H / 20) * 100)}%`;

    $('pilarTotalA').textContent = `${pilares.A} pts`;
    $('pilarBarraA').style.width = `${Math.min(100, (pilares.A / 30) * 100)}%`;

    $('pilarTotalP').textContent = `${pilares.P} pts`;
    $('pilarBarraP').style.width = `${Math.min(100, (pilares.P / 30) * 100)}%`;

    $('pilarTotalD').textContent = `${pilares.D} pts`;
    $('pilarBarraD').style.width = `${Math.min(100, (pilares.D / 20) * 100)}%`;

    // Sugestão Automática de Letra Conceito com base na dupla calculada
    if (!estado.conceitoLetraSelecionada) {
        selecionarLetra(conjunto.sugestaoLetra);
    }
}

function voltarAoQuestionario() {
    $('stepSintese').classList.add('hidden');
    $('stepQuestionario').classList.remove('hidden');
    renderizarPerguntaAtual();
}

/**
 * Renderiza os 3 blocos de Conjuntos Duplos de Letras (A & B, C & D, E & F)
 */
function renderizarGradeConceitosLetras() {
    const container = $('gradeConceitosLetras');
    if (!container) return;

    container.innerHTML = '';

    // Agrupamento explícito em duplas de letras
    const duplasConfig = [
        {
            dupla: 'AB',
            letrasNome: 'A e B',
            titulo: 'Soma Máxima (≥ 115 pts)',
            subtitulo: 'Alta Performance • Diferencial',
            corBadge: 'bg-emerald-100 text-emerald-800 border-emerald-300'
        },
        {
            dupla: 'CD',
            letrasNome: 'C e D',
            titulo: 'Meio dos Pontos (85 a 114 pts)',
            subtitulo: 'Médio Potencial • Desenvolvimento',
            corBadge: 'bg-indigo-100 text-indigo-800 border-indigo-300'
        },
        {
            dupla: 'EF',
            letrasNome: 'E e F',
            titulo: 'Últimos Abaixo (< 85 pts)',
            subtitulo: 'Atenção Crítica • Exige PDI',
            corBadge: 'bg-rose-100 text-rose-800 border-rose-300'
        }
    ];

    duplasConfig.forEach(conf => {
        const col = document.createElement('div');
        col.className = 'space-y-2.5 p-3 rounded-2xl bg-slate-50/80 border border-slate-200/70';

        const cabecalho = document.createElement('div');
        cabecalho.className = 'flex items-center justify-between pb-1.5 border-b border-slate-200';
        cabecalho.innerHTML = `
            <div>
                <span class="text-xs font-black text-slate-800">Conjunto ${conf.letrasNome}</span>
                <div class="text-[10px] text-slate-500 font-semibold">${conf.subtitulo}</div>
            </div>
            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md border ${conf.corBadge}">${conf.titulo}</span>
        `;
        col.appendChild(cabecalho);

        // Busca itens do grupo
        estado.conceitosLetras.forEach(grupo => {
            grupo.itens.forEach(item => {
                if (conf.letrasNome.includes(item.letra)) {
                    const card = document.createElement('div');
                    card.id = `cardLetra_${item.letra}`;
                    card.className = 'letra-card cursor-pointer transition hover:border-emerald-300 hover:shadow-xs';
                    card.onclick = () => selecionarLetra(item.letra);

                    card.innerHTML = `
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-800 font-black text-base flex items-center justify-center flex-shrink-0" id="badgeLetra_${item.letra}">
                                ${item.letra}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-extrabold text-slate-800 text-xs sm:text-sm truncate">${item.titulo}</div>
                                <p class="text-[0.68rem] text-slate-500 font-semibold leading-tight mt-0.5 line-clamp-2">${item.descricao}</p>
                            </div>
                        </div>
                    `;
                    col.appendChild(card);
                }
            });
        });

        container.appendChild(col);
    });
}

/**
 * Seleciona a Letra Conceito
 */
function selecionarLetra(letra) {
    estado.conceitoLetraSelecionada = letra;

    // Desmarca todos os cards
    document.querySelectorAll('.letra-card').forEach(c => c.classList.remove('selecionada'));
    document.querySelectorAll('[id^="badgeLetra_"]').forEach(b => {
        b.className = 'w-9 h-9 rounded-xl bg-slate-100 text-slate-800 font-black text-base flex items-center justify-center flex-shrink-0';
    });

    // Marca o card selecionado
    const cardSel = $(`cardLetra_${letra}`);
    if (cardSel) cardSel.classList.add('selecionada');

    const badgeSel = $(`badgeLetra_${letra}`);
    if (badgeSel) {
        badgeSel.className = 'w-9 h-9 rounded-xl bg-emerald-600 text-white font-black text-base flex items-center justify-center flex-shrink-0 shadow-sm';
    }

    // Atualiza o resumo
    $('resumoLetraConceito').textContent = `Conceito ${letra}`;
    $('resumoLetraBox').textContent = letra;
    $('resumoLetraBox').className = 'w-12 h-12 rounded-2xl bg-emerald-600 text-white flex items-center justify-center text-xl font-black shadow-md';

    // Descrição
    let desc = '';
    estado.conceitosLetras.forEach(g => {
        const achou = g.itens.find(i => i.letra === letra);
        if (achou) desc = achou.descricao;
    });
    $('resumoLetraTexto').textContent = desc;
}

/**
 * Salva a avaliação final no banco de dados
 */
async function salvarAvaliacaoFinal() {
    if (!estado.colaboradorSelecionado) return;

    if (!estado.conceitoLetraSelecionada) {
        Swal.fire({
            ...swalBase,
            icon: 'warning',
            title: 'Conceito Obrigatório',
            text: 'Por favor, selecione uma das Letras de Conceito (A a F).'
        });
        return;
    }

    const confirm = await Swal.fire({
        ...swalBase,
        title: 'Confirmar Avaliação?',
        html: `
            <div class="text-left text-xs space-y-2">
                <p>Confirma o registro da avaliação de <b>${estado.colaboradorSelecionado.nome}</b>?</p>
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                    <div><b>Período:</b> ${estado.mesSelecionado}/${estado.anoSelecionado}</div>
                    <div><b>Pontuação Total:</b> ${$('resumoPontosTotal').textContent}</div>
                    <div><b>Classificação:</b> ${$('resumoClassificacao').textContent}</div>
                    <div><b>Conceito:</b> Letra ${estado.conceitoLetraSelecionada}</div>
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Gravar Avaliação',
        cancelButtonText: 'Revisar'
    });

    if (!confirm.isConfirmed) return;

    // Prepara payload
    const respostasArray = Object.values(estado.respostas);

    const payload = {
        idcliforemp: estado.colaboradorSelecionado.idcliforemp,
        ano: estado.anoSelecionado,
        mes: estado.mesSelecionado,
        setor: $('inputSetor').value.trim(),
        celula_trabalho: $('inputCelula').value.trim(),
        avaliador_nome: $('inputAvaliador').value.trim() || 'Sandro Rafo',
        aprovador_nome: $('inputAprovador').value.trim() || 'Robson Becker',
        conceito_letra: estado.conceitoLetraSelecionada,
        foto_colaborador: estado.colaboradorSelecionado.foto_url || '',
        pontos_fortes: $('inputPontosFortes').value.trim(),
        pontos_desenvolver: $('inputPontosDesenvolver').value.trim(),
        plano_acao: $('inputPlanoAcao').value.trim(),
        observacoes_gerais: '',
        respostas: respostasArray
    };

    const btnSalvar = $('btnSalvarAvaliacao');
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gravando...';

    try {
        const data = await apiFetch('salvar', 'POST', payload);

        if (!data.sucesso) {
            throw new Error(data.mensagem || 'Erro ao registrar avaliação.');
        }

        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Avaliação Registrada!',
            html: `
                <div class="text-xs space-y-2">
                    <p>A avaliação de <b>${estado.colaboradorSelecionado.nome}</b> foi gravada com sucesso.</p>
                    <div class="p-2.5 bg-emerald-50 text-emerald-800 rounded-xl font-bold">
                        Pontos: ${data.pontuacao_total} • Conceito: ${data.conceito_letra} (${data.classificacao})
                    </div>
                </div>
            `,
            showDenyButton: true,
            confirmButtonText: 'Ver Ficha Completa',
            denyButtonText: 'Ir para Matriz Anual'
        }).then((result) => {
            if (result.isConfirmed) {
                abrirModalFicha(data.id);
            } else {
                alternarAba('matriz');
            }
        });

        // Limpa formulário
        iniciarNovaAvaliacao();

    } catch (err) {
        console.error('Erro ao salvar avaliação:', err);
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Falha na Gravação',
            text: err.message
        });
    } finally {
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = '<i class="fa-solid fa-cloud-arrow-up"></i> Concluir e Salvar Avaliação';
    }
}

function cancelarAvaliacao() {
    Swal.fire({
        ...swalBase,
        title: 'Descartar Avaliação?',
        text: 'Todas as respostas desta avaliação serão perdidas.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, Descartar',
        cancelButtonText: 'Continuar Avaliando'
    }).then(res => {
        if (res.isConfirmed) {
            iniciarNovaAvaliacao();
        }
    });
}

function iniciarNovaAvaliacao() {
    estado.colaboradorSelecionado = null;
    estado.respostas = {};
    estado.indicePerguntaAtual = 0;
    estado.conceitoLetraSelecionada = '';

    $('selColaborador').value = '';
    $('cardResumoColab')?.classList.add('hidden');
    $('inputPontosFortes').value = '';
    $('inputPontosDesenvolver').value = '';
    $('inputPlanoAcao').value = '';

    alternarAba('avaliar');
    $('stepSelecao').classList.remove('hidden');
    $('stepQuestionario').classList.add('hidden');
    $('stepSintese').classList.add('hidden');
}

// ==========================================
// ABA 2: DASHBOARD & PEOPLE ANALYTICS
// ==========================================
async function carregarDashboard() {
    const ano = $('filtroDashAno')?.value || estado.anoAtual;
    const mes = $('filtroDashMes')?.value || '';
    const filial = $('filtroDashFilial')?.value || '';

    try {
        const data = await apiFetch(`dashboard?ano=${ano}&mes=${mes}&filial=${filial}`);

        if (!data.sucesso) throw new Error(data.mensagem);

        const m = data.metricas || {};

        $('kpiDashTotal').textContent = m.total_avaliacoes || 0;
        $('kpiDashMedia').textContent = `${m.media_pontos || 0} pts`;
        $('kpiDashPercentual').textContent = `${m.media_percentual || 0}% de aproveitamento`;

        // Contagens
        let excelentes = 0;
        let atencao = 0;
        (data.distribuicao_faixas || []).forEach(f => {
            if (f.faixa.includes('Excelente')) excelentes += parseInt(f.quantidade);
            if (f.faixa.includes('Baixo')) atencao += parseInt(f.quantidade);
        });

        $('kpiDashExcelentes').textContent = excelentes;
        $('kpiDashAtencao').textContent = atencao;

        // Renderiza Top Colaboradores
        renderizarTopColaboradores(data.top_colaboradores || []);

        // Renderiza Colaboradores em Atenção
        renderizarAtencaoColaboradores(data.atencao_colaboradores || []);

    } catch (err) {
        console.error('Erro ao carregar dashboard:', err);
    }
}

function renderizarTopColaboradores(lista) {
    const box = $('listaTopColaboradores');
    if (!box) return;

    if (lista.length === 0) {
        box.innerHTML = '<div class="text-center py-6 text-slate-400 text-xs font-semibold">Nenhuma avaliação encontrada no período.</div>';
        return;
    }

    box.innerHTML = lista.map((c, idx) => `
        <div class="p-3 bg-slate-50 hover:bg-emerald-50/50 rounded-2xl border border-slate-100 flex items-center justify-between transition cursor-pointer" onclick="abrirModalFicha(${c.id})">
            <div class="flex items-center gap-3">
                <span class="w-7 h-7 rounded-full ${idx === 0 ? 'bg-amber-400 text-slate-900' : 'bg-slate-200 text-slate-600'} flex items-center justify-center font-black text-xs">
                    ${idx + 1}
                </span>
                <div>
                    <div class="font-black text-slate-800 text-xs sm:text-sm">${esc(c.nome_colaborador)}</div>
                    <div class="text-[0.68rem] text-slate-500 font-semibold">${esc(c.cargo)}</div>
                </div>
            </div>
            <div class="text-right">
                <span class="font-black text-emerald-700 text-sm">${c.pontuacao_total} pts</span>
                <span class="block text-[0.68rem] font-bold text-slate-400">Conceito ${c.conceito_letra}</span>
            </div>
        </div>
    `).join('');
}

function renderizarAtencaoColaboradores(lista) {
    const box = $('listaAtencaoColaboradores');
    if (!box) return;

    if (lista.length === 0) {
        box.innerHTML = '<div class="text-center py-6 text-emerald-600 text-xs font-bold"><i class="fa-solid fa-check-circle"></i> Parabéns! Nenhum colaborador em nível crítico no período.</div>';
        return;
    }

    box.innerHTML = lista.map(c => `
        <div class="p-3 bg-rose-50/60 rounded-2xl border border-rose-100 flex items-center justify-between transition cursor-pointer" onclick="abrirModalFicha(${c.id})">
            <div>
                <div class="font-black text-slate-800 text-xs sm:text-sm">${esc(c.nome_colaborador)}</div>
                <div class="text-[0.68rem] text-rose-700 font-semibold">${esc(c.cargo)} • Pontos: <b>${c.pontuacao_total}</b></div>
                <div class="text-[0.65rem] text-slate-500 mt-0.5 line-clamp-1 italic">${esc(c.plano_acao || c.pontos_desenvolver || 'Sem PDI cadastrado')}</div>
            </div>
            <span class="p-1 px-2.5 rounded-xl bg-rose-200 text-rose-900 font-black text-xs">
                ${c.conceito_letra || 'E'}
            </span>
        </div>
    `).join('');
}

// ==========================================
// ABA 3: MATRIZ ANUAL (JAN A DEZ) - IDÊNTICA À PLANILHA
// ==========================================
async function carregarMatrizAnual() {
    const ano = $('filtroMatrizAno')?.value || estado.anoAtual;
    const filial = $('filtroMatrizFilial')?.value || '';

    const corpo = $('tabelaMatrizCorpo');
    corpo.innerHTML = '<tr><td colspan="15" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando matriz anual...</td></tr>';

    try {
        const data = await apiFetch(`matriz-anual?ano=${ano}&filial=${filial}`);

        if (!data.sucesso) throw new Error(data.mensagem);

        estado.matrizAnual = data.matriz || [];
        renderizarTabelaMatriz();

    } catch (err) {
        console.error('Erro na matriz:', err);
        corpo.innerHTML = `<tr><td colspan="15" class="text-center py-8 text-rose-500 font-bold">${err.message}</td></tr>`;
    }
}

function renderizarTabelaMatriz() {
    const corpo = $('tabelaMatrizCorpo');
    if (!corpo) return;

    if (estado.matrizAnual.length === 0) {
        corpo.innerHTML = '<tr><td colspan="15" class="text-center py-8 text-slate-400">Nenhum colaborador encontrado.</td></tr>';
        return;
    }

    corpo.innerHTML = estado.matrizAnual.map(colab => {
        let colsMeses = '';

        for (let m = 1; m <= 12; m++) {
            const item = colab.meses[m];
            if (item) {
                // Define cor do chip
                let badgeClass = 'bg-slate-100 text-slate-700';
                if (item.pontos >= 125) badgeClass = 'bg-emerald-100 text-emerald-800 font-extrabold';
                else if (item.pontos >= 100) badgeClass = 'bg-blue-100 text-blue-800 font-extrabold';
                else if (item.pontos >= 85) badgeClass = 'bg-amber-100 text-amber-800 font-extrabold';
                else badgeClass = 'bg-rose-100 text-rose-800 font-extrabold';

                colsMeses += `
                    <td class="py-2.5 px-1.5 text-center">
                        <button type="button" onclick="abrirModalFicha(${item.id})" class="p-1 px-1.5 rounded-lg text-[0.68rem] ${badgeClass} hover:opacity-80 transition shadow-xs" title="Ver avaliação">
                            ${item.pontos} <span class="text-[0.6rem] opacity-75">[${item.letra || ''}]</span>
                        </button>
                    </td>
                `;
            } else {
                colsMeses += `
                    <td class="py-2.5 px-1.5 text-center text-slate-300 text-[0.7rem]">-</td>
                `;
            }
        }

        // Média Anual
        const media = colab.media_anual !== null ? `<b>${colab.media_anual}</b>` : '<span class="text-slate-300">-</span>';

        const fotoUrlFormatada = colab.foto_url ? formatarUrlFoto(colab.foto_url) : '';
        const colabFotoHtml = fotoUrlFormatada 
            ? `<img src="${fotoUrlFormatada}" class="w-8 h-8 rounded-xl object-cover flex-none border border-slate-200 shadow-2xs" alt="Foto" onerror="this.onerror=null; this.src=''; this.classList.add('hidden');">`
            : `<div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 font-black text-xs flex items-center justify-center flex-none">${(colab.nome || 'C')[0]}</div>`;

        return `
            <tr class="hover:bg-slate-50/80 transition">
                <td class="py-3 px-3">
                    <div class="flex items-center gap-2.5">
                        ${colabFotoHtml}
                        <div class="overflow-hidden">
                            <div class="font-black text-slate-800 text-xs truncate">${esc(colab.nome)}</div>
                            <div class="text-[0.68rem] text-slate-400 font-semibold truncate">${esc(colab.cargo)} • Filial ${colab.filial}</div>
                        </div>
                    </div>
                </td>
                ${colsMeses}
                <td class="py-3 px-3 text-center bg-slate-50 text-slate-800 font-black">${media}</td>
                <td class="py-3 px-3 text-center">
                    <button type="button" onclick="iniciarAvaliacaoDireta(${colab.idcliforemp})" class="p-1.5 px-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-extrabold text-[0.68rem] transition flex items-center gap-1 mx-auto" title="Avaliar este colaborador">
                        <i class="fa-solid fa-plus"></i> Avaliar
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

/**
 * Atalho para iniciar avaliação direto de um colaborador clicado na Matriz
 */
function iniciarAvaliacaoDireta(idcliforemp) {
    iniciarNovaAvaliacao();
    $('selColaborador').value = idcliforemp;
    aoSelecionarColaborador();
}

// ==========================================
// ABA 4: HISTÓRICO & RELATÓRIOS
// ==========================================
async function carregarHistorico() {
    const ano = $('filtroHistAno')?.value || '';
    const mes = $('filtroHistMes')?.value || '';

    const corpo = $('tabelaHistoricoCorpo');
    corpo.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando histórico...</td></tr>';

    try {
        const data = await apiFetch(`historico?ano=${ano}&mes=${mes}`);

        if (!data.sucesso) throw new Error(data.mensagem);

        estado.historico = data.avaliacoes || [];
        renderizarTabelaHistorico();

    } catch (err) {
        console.error('Erro no histórico:', err);
        corpo.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-rose-500 font-bold">${err.message}</td></tr>`;
    }
}

function renderizarTabelaHistorico() {
    const corpo = $('tabelaHistoricoCorpo');
    if (!corpo) return;

    if (estado.historico.length === 0) {
        corpo.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Nenhuma avaliação encontrada.</td></tr>';
        return;
    }

    corpo.innerHTML = estado.historico.map(a => {
        const rawFoto = a.foto_url || a.foto_colaborador;
        const fotoUrlFormatada = rawFoto ? formatarUrlFoto(rawFoto) : '';
        const fotoHtml = fotoUrlFormatada
            ? `<img src="${fotoUrlFormatada}" class="w-8 h-8 rounded-xl object-cover flex-none border border-slate-200 shadow-2xs" alt="Foto" onerror="this.onerror=null; this.src=''; this.classList.add('hidden');">`
            : `<div class="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-800 font-black text-xs flex items-center justify-center flex-none">${(a.nome_colaborador || 'C')[0]}</div>`;

        return `
            <tr class="hover:bg-slate-50 transition">
                <td class="py-3 px-3 font-bold text-slate-700">${a.mes.toString().padStart(2, '0')}/${a.ano}</td>
                <td class="py-3 px-3">
                    <div class="flex items-center gap-2.5">
                        ${fotoHtml}
                        <div>
                            <div class="font-black text-slate-800">${esc(a.nome_colaborador)}</div>
                            <div class="text-[0.68rem] text-slate-400 font-semibold">${esc(a.cargo)}</div>
                        </div>
                    </div>
                </td>
                <td class="py-3 px-3 text-slate-500">${esc(a.cargo)} • ${esc(a.setor || 'Geral')}</td>
                <td class="py-3 px-3 text-center font-black text-slate-900">${a.pontuacao_total} / ${a.pontuacao_maxima}</td>
                <td class="py-3 px-3 text-center">
                    <span class="p-1 px-2.5 rounded-xl bg-slate-100 text-slate-800 font-black text-xs">
                        ${a.conceito_letra || '-'}
                    </span>
                </td>
                <td class="py-3 px-3 font-bold text-slate-700">${esc(a.classificacao_pontos)}</td>
                <td class="py-3 px-3 text-slate-500">${esc(a.avaliador_nome)}</td>
                <td class="py-3 px-3 text-center">
                    <button type="button" onclick="abrirModalFicha(${a.id})" class="p-1.5 px-3 rounded-xl bg-slate-100 hover:bg-emerald-50 hover:text-emerald-700 font-bold text-xs transition flex items-center gap-1 mx-auto">
                        <i class="fa-solid fa-file-lines"></i> Ficha
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// ==========================================
// MODAL FICHA FORMAL / IMPRESSÃO PDF
// ==========================================
async function abrirModalFicha(id) {
    try {
        const data = await apiFetch(`detalhes/${id}`);

        if (!data.sucesso) throw new Error(data.mensagem);

        const a = data.avaliacao;
        const respostas = data.respostas || [];

        const mesesNomes = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];

        $('fichaModalTitulo').textContent = `Ficha de Avaliação #${a.id} • ${a.nome_colaborador}`;
        $('fichaModalSubtitulo').textContent = `Período: ${mesesNomes[a.mes]} / ${a.ano} • Realizada em ${formatarDataBR(a.data_avaliacao)}`;

        $('fichaPeriodo').textContent = `${mesesNomes[a.mes]} / ${a.ano}`;
        $('fichaColaborador').textContent = a.nome_colaborador;
        $('fichaCargo').textContent = a.cargo || 'Não informado';
        $('fichaSetor').textContent = a.setor || 'Geral';
        $('fichaCelula').textContent = a.celula_trabalho || `Filial ${a.idfilial}`;

        // Foto do Colaborador na Ficha Oficial
        const fotoUrl = a.foto_colaborador || a.foto_url;
        const imgFicha = $('fichaColabFoto');
        const iniciaisFicha = $('fichaColabIniciais');
        if (fotoUrl) {
            if (imgFicha) {
                imgFicha.src = formatarUrlFoto(fotoUrl);
                imgFicha.classList.remove('hidden');
                imgFicha.onerror = () => {
                    imgFicha.classList.add('hidden');
                    if (iniciaisFicha) iniciaisFicha.classList.remove('hidden');
                };
            }
            if (iniciaisFicha) iniciaisFicha.classList.add('hidden');
        } else {
            if (imgFicha) imgFicha.classList.add('hidden');
            if (iniciaisFicha) {
                const nomes = (a.nome_colaborador || '').trim().split(' ');
                iniciaisFicha.textContent = (nomes[0][0] + (nomes.length > 1 ? nomes[nomes.length - 1][0] : '')).toUpperCase();
                iniciaisFicha.classList.remove('hidden');
            }
        }
        if (fotoUrl) {
            if (imgFicha) {
                imgFicha.src = fotoUrl;
                imgFicha.classList.remove('hidden');
            }
            if (iniciaisFicha) iniciaisFicha.classList.add('hidden');
        } else {
            if (imgFicha) imgFicha.classList.add('hidden');
            if (iniciaisFicha) {
                const nomes = (a.nome_colaborador || '').trim().split(' ');
                iniciaisFicha.textContent = (nomes[0][0] + (nomes.length > 1 ? nomes[nomes.length - 1][0] : '')).toUpperCase();
                iniciaisFicha.classList.remove('hidden');
            }
        }

        $('fichaPontos').textContent = `${a.pontuacao_total} / ${a.pontuacao_maxima} pts`;
        $('fichaPercentual').textContent = `${a.percentual_atingido}% de aproveitamento`;
        $('fichaClassificacao').textContent = a.classificacao_pontos;
        
        // Conjunto Duplo na Ficha
        const elConjunto = $('fichaConjuntoDuplo');
        if (elConjunto) {
            const conj = a.conjunto || calcularConjuntoDuplo(a.pontuacao_total).letras;
            elConjunto.textContent = conj;
        }

        $('fichaLetra').textContent = `Letra ${a.conceito_letra || '-'}`;

        $('fichaPontosFortes').textContent = a.pontos_fortes || 'Nenhum ponto forte registrado.';
        $('fichaPontosDesenvolver').textContent = a.pontos_desenvolver || 'Nenhum ponto registrado.';
        $('fichaPlanoAcao').textContent = a.plano_acao || 'Nenhum plano de ação formalizado.';

        $('fichaAssinaturaAvaliador').textContent = a.avaliador_nome || 'Sandro Rafo';
        $('fichaAssinaturaAprovador').textContent = a.aprovador_nome || 'Robson Becker';

        // Tabela de Respostas
        const tbody = $('fichaTabelaRespostas');
        tbody.innerHTML = respostas.map(r => `
            <tr>
                <td class="p-2 text-center font-black text-slate-700 bg-slate-50">${r.pilar}</td>
                <td class="p-2">
                    <b class="text-slate-800">${esc(r.pergunta)}</b>
                    ${r.descricao_auxiliar ? `<div class="text-[0.65rem] text-slate-400 mt-0.5">${esc(r.descricao_auxiliar)}</div>` : ''}
                </td>
                <td class="p-2 text-center font-bold">
                    <span class="p-1 px-2 rounded-lg bg-slate-100 text-slate-800">${r.pontuacao} pts</span>
                    <div class="text-[0.65rem] text-slate-400 mt-0.5">${esc(r.texto_opcao)}</div>
                </td>
                <td class="p-2 text-[0.7rem] text-slate-500 italic">${esc(r.observacao_item || '-')}</td>
            </tr>
        `).join('');

        $('modalFicha').classList.remove('hidden');

    } catch (err) {
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Erro ao abrir ficha',
            text: err.message
        });
    }
}

function fecharModalFicha() {
    $('modalFicha').classList.add('hidden');
}

function imprimirFicha() {
    window.print();
}

// ==========================================
// ABA 5: GESTÃO DE PERGUNTAS & CRITÉRIOS
// ==========================================
async function carregarPerguntasGestao() {
    const corpo = $('tabelaPerguntasCorpo');
    corpo.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando perguntas...</td></tr>';

    try {
        const data = await apiFetch('perguntas');

        if (!data.sucesso) throw new Error(data.mensagem);

        estado.perguntasGestao = data.perguntas || [];
        renderizarTabelaPerguntasGestao();

    } catch (err) {
        corpo.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-rose-500 font-bold">${err.message}</td></tr>`;
    }
}

function renderizarTabelaPerguntasGestao() {
    const corpo = $('tabelaPerguntasCorpo');
    if (!corpo) return;

    corpo.innerHTML = estado.perguntasGestao.map(p => `
        <tr class="hover:bg-slate-50 transition">
            <td class="py-3 px-3 text-center font-black text-slate-500">${p.ordem}</td>
            <td class="py-3 px-3 text-center">
                <span class="pilar-badge pilar-${p.pilar}">${p.pilar}</span>
            </td>
            <td class="py-3 px-3 font-extrabold text-slate-700">${esc(p.codigo)}</td>
            <td class="py-3 px-3 font-bold text-slate-800">${esc(p.pergunta)}</td>
            <td class="py-3 px-3 text-slate-500 text-[0.7rem]">${esc(p.descricao_auxiliar || '-')}</td>
            <td class="py-3 px-3 text-center">
                <button type="button" onclick="togglePerguntaStatus(${p.id})" class="p-1 px-2.5 rounded-full text-[0.68rem] font-bold ${p.ativo ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'}">
                    ${p.ativo ? 'Ativa' : 'Inativa'}
                </button>
            </td>
            <td class="py-3 px-3 text-center">
                <button type="button" onclick="editarPergunta(${p.id})" class="p-1.5 px-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs transition" title="Editar">
                    <i class="fa-solid fa-pen"></i>
                </button>
            </td>
        </tr>
    `).join('');
}

function abrirModalNovaPergunta() {
    $('modalPerguntaTitulo').textContent = 'Nova Pergunta C.H.A.P.D';
    $('perguntaId').value = '';
    $('perguntaCodigo').value = '';
    $('perguntaTexto').value = '';
    $('perguntaDescAux').value = '';
    $('perguntaOrdem').value = estado.perguntasGestao.length + 1;
    $('perguntaAtivo').value = '1';
    $('modalPergunta').classList.remove('hidden');
}

function editarPergunta(id) {
    const p = estado.perguntasGestao.find(x => x.id === id);
    if (!p) return;

    $('modalPerguntaTitulo').textContent = `Editar Pergunta #${p.codigo}`;
    $('perguntaId').value = p.id;
    $('perguntaPilar').value = p.pilar;
    $('perguntaCodigo').value = p.codigo;
    $('perguntaTexto').value = p.pergunta;
    $('perguntaDescAux').value = p.descricao_auxiliar || '';
    $('perguntaOrdem').value = p.ordem;
    $('perguntaAtivo').value = p.ativo ? '1' : '0';
    $('modalPergunta').classList.remove('hidden');
}

function fecharModalPergunta() {
    $('modalPergunta').classList.add('hidden');
}

async function salvarPergunta(e) {
    e.preventDefault();

    const payload = {
        id: $('perguntaId').value || null,
        pilar: $('perguntaPilar').value,
        codigo: $('perguntaCodigo').value.trim(),
        pergunta: $('perguntaTexto').value.trim(),
        descricao_auxiliar: $('perguntaDescAux').value.trim(),
        ordem: parseInt($('perguntaOrdem').value) || 1,
        ativo: $('perguntaAtivo').value === '1'
    };

    try {
        const data = await apiFetch('perguntas/salvar', 'POST', payload);
        if (!data.sucesso) throw new Error(data.mensagem);

        fecharModalPergunta();
        await carregarPerguntasGestao();
        await carregarContexto(); // Recarrega perguntas ativas

        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Salvo!',
            text: 'Critério avaliativo configurado com sucesso.'
        });

    } catch (err) {
        Swal.fire({
            ...swalBase,
            icon: 'error',
            title: 'Erro ao salvar pergunta',
            text: err.message
        });
    }
}

async function togglePerguntaStatus(id) {
    try {
        const data = await apiFetch('perguntas/toggle', 'POST', { id });
        if (!data.sucesso) throw new Error(data.mensagem);

        await carregarPerguntasGestao();
        await carregarContexto();

    } catch (err) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro', text: err.message });
    }
}

// ==========================================
// CONTROLE DE ABAS E MODAIS
// ==========================================
function alternarAba(aba) {
    estado.abaAtiva = aba;
    const abas = ['avaliar', 'dashboard', 'colaboradores', 'matriz', 'historico', 'perguntas'];

    abas.forEach(a => {
        const btn = $(`tabBtn${a.charAt(0).toUpperCase() + a.slice(1)}`);
        const painel = $(`painel${a.charAt(0).toUpperCase() + a.slice(1)}`);

        if (a === aba) {
            btn?.classList.add('on');
            painel?.classList.remove('hidden');
        } else {
            btn?.classList.remove('on');
            painel?.classList.add('hidden');
        }
    });

    if (aba === 'dashboard') carregarDashboard();
    else if (aba === 'colaboradores') renderizarGridColaboradores();
    else if (aba === 'matriz') carregarMatrizAnual();
    else if (aba === 'historico') carregarHistorico();
    else if (aba === 'perguntas') carregarPerguntasGestao();
}

// ==========================================
// PAINEL DE GESTÃO DE COLABORADORES & EQUIPE
// ==========================================
function atualizarMetricasColaboradores() {
    const total = estado.colaboradores.length;
    const comFoto = estado.colaboradores.filter(c => !!c.foto_url).length;
    const custom = estado.colaboradores.filter(c => !!c.perfil_editado).length;
    const usuarios = estado.colaboradores.filter(c => !!c.usuario_portal).length;

    const elTotal = $('colabMetricaTotal');
    const elFoto = $('colabMetricaComFoto');
    const elCustom = $('colabMetricaCustom');
    const elUsers = $('colabMetricaUsuarios');

    if (elTotal) elTotal.textContent = total;
    if (elFoto) elFoto.textContent = comFoto;
    if (elCustom) elCustom.textContent = custom;
    if (elUsers) elUsers.textContent = usuarios;
}

function filtrarColaboradoresGrid() {
    const termo = ($('filtroColabBusca')?.value || '').toLowerCase().trim();
    const filial = $('filtroColabFilial')?.value || '';
    const foto = $('filtroColabFoto')?.value || '';

    const btnLimpar = $('btnLimparBuscaColab');
    if (btnLimpar) {
        if (termo) btnLimpar.classList.remove('hidden');
        else btnLimpar.classList.add('hidden');
    }

    const filtrados = estado.colaboradores.filter(c => {
        // Filtro Filial
        if (filial && String(c.idfilial) !== String(filial)) {
            return false;
        }

        // Filtro Status / Foto
        if (foto === 'com_foto' && !c.foto_url) return false;
        if (foto === 'sem_foto' && !!c.foto_url) return false;
        if (foto === 'customizado' && !c.perfil_editado) return false;
        if (foto === 'com_usuario' && !c.usuario_portal) return false;

        // Filtro de Texto
        if (termo) {
            const matchNome = (c.nome || '').toLowerCase().includes(termo);
            const matchNomeErp = (c.nome_erp || '').toLowerCase().includes(termo);
            const matchCargo = (c.cargo || '').toLowerCase().includes(termo);
            const matchSetor = (c.setor_custom || '').toLowerCase().includes(termo);
            const matchCelula = (c.celula_custom || '').toLowerCase().includes(termo);
            const matchUser = (c.usuario_portal || '').toLowerCase().includes(termo);
            const matchId = String(c.idcliforemp).includes(termo);

            if (!matchNome && !matchNomeErp && !matchCargo && !matchSetor && !matchCelula && !matchUser && !matchId) {
                return false;
            }
        }

        return true;
    });

    renderizarGridColaboradores(filtrados);
}

function limparFiltroBuscaColab() {
    const input = $('filtroColabBusca');
    if (input) {
        input.value = '';
        filtrarColaboradoresGrid();
        input.focus();
    }
}

function renderizarGridColaboradores(lista = null) {
    atualizarMetricasColaboradores();

    const grid = $('gridColaboradores');
    const empty = $('emptyColaboradores');
    const contador = $('colabContadorExibidos');

    if (!grid) return;

    const itens = lista !== null ? lista : estado.colaboradores;

    if (contador) {
        if (itens.length === estado.colaboradores.length) {
            contador.textContent = `Exibindo todos os ${itens.length} colaboradores ativos`;
        } else {
            contador.textContent = `Exibindo ${itens.length} de ${estado.colaboradores.length} colaboradores`;
        }
    }

    if (itens.length === 0) {
        grid.innerHTML = '';
        empty?.classList.remove('hidden');
        return;
    }

    empty?.classList.add('hidden');

    grid.innerHTML = itens.map(c => {
        const nomes = (c.nome || 'Colaborador').trim().split(' ');
        const iniciais = (nomes[0][0] + (nomes.length > 1 ? nomes[nomes.length - 1][0] : '')).toUpperCase();
        const urlFoto = formatarUrlFoto(c.foto_url);

        const badgeFilial = `Filial ${c.idfilial}`;
        const temCustom = !!c.perfil_editado;
        const temUser = !!c.usuario_portal;

        return `
            <div class="ch-card p-4 flex flex-col justify-between hover:shadow-md transition group border border-slate-200/80 hover:border-emerald-300">
                <div>
                    <!-- TOPO DO CARD: AVATAR + BADGES -->
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="relative flex-none">
                            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-600 to-teal-500 text-white font-black text-base flex items-center justify-center overflow-hidden shadow-xs border-2 border-white ring-2 ring-slate-100">
                                ${urlFoto ? `
                                    <img src="${urlFoto}?t=${Date.now()}" alt="${esc(c.nome)}" class="w-full h-full object-cover" onerror="this.classList.add('hidden'); this.nextElementSibling.classList.remove('hidden');">
                                    <span class="hidden">${iniciais}</span>
                                ` : `
                                    <span>${iniciais}</span>
                                `}
                            </div>
                            <!-- Botão de Câmera Rápida sobreposto -->
                            <button type="button" onclick="abrirModalCapturaFoto(${c.idcliforemp})" title="Alterar ou Bater Foto" class="absolute -bottom-1 -right-1 w-6 h-6 rounded-full bg-slate-900 hover:bg-emerald-600 text-white text-[10px] flex items-center justify-center transition shadow-md cursor-pointer border border-white">
                                <i class="fa-solid fa-camera"></i>
                            </button>
                        </div>

                        <div class="flex flex-col items-end gap-1 overflow-hidden">
                            <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-slate-100 text-slate-700">
                                ${badgeFilial}
                            </span>
                            ${temUser ? `
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200" title="Usuário vinculado ao portal - fotos e dados sincronizados">
                                    <i class="fa-solid fa-circle-check text-emerald-600 text-[9px]"></i> @${esc(c.usuario_portal)}
                                </span>
                            ` : `
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-medium bg-slate-100 text-slate-400" title="Sem usuário cadastrado no portal">
                                    <i class="fa-regular fa-user text-[8px]"></i> Sem login
                                </span>
                            `}
                            ${temCustom ? `
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-extrabold bg-indigo-50 text-indigo-700 border border-indigo-200" title="Perfil customizado na área separada C.H.A.P.D">
                                    <i class="fa-solid fa-user-gear text-[8px]"></i> Custom
                                </span>
                            ` : ''}
                        </div>
                    </div>

                    <!-- DADOS DO COLABORADOR -->
                    <div class="space-y-1">
                        <div class="font-black text-slate-800 text-sm leading-snug line-clamp-2" title="${esc(c.nome)}">
                            ${esc(c.nome)}
                        </div>
                        ${(c.nome_erp && c.nome_erp !== c.nome) ? `
                            <div class="text-[10px] text-slate-400 font-semibold truncate" title="Nome original no ERP: ${esc(c.nome_erp)}">
                                ERP: ${esc(c.nome_erp)}
                            </div>
                        ` : ''}
                        <div class="text-xs text-slate-600 font-medium flex items-center gap-1.5 pt-0.5 truncate" title="${esc(c.cargo)}">
                            <i class="fa-solid fa-briefcase text-emerald-600 text-[11px] flex-none"></i>
                            <span class="truncate">${esc(c.cargo)}</span>
                        </div>
                        ${(c.setor_custom || c.celula_custom) ? `
                            <div class="text-[11px] text-slate-500 font-medium flex items-center gap-1.5 truncate">
                                <i class="fa-solid fa-layer-group text-indigo-500 text-[10px] flex-none"></i>
                                <span class="truncate">${esc([c.setor_custom, c.celula_custom].filter(Boolean).join(' • '))}</span>
                            </div>
                        ` : ''}
                        ${c.data_admissao ? `
                            <div class="text-[10px] text-slate-400 font-medium flex items-center gap-1.5">
                                <i class="fa-solid fa-calendar text-slate-400 text-[10px] flex-none"></i>
                                <span>Admissão: ${formatarDataBR(c.data_admissao)}</span>
                            </div>
                        ` : ''}
                    </div>
                </div>

                <!-- AÇÕES DO CARD -->
                <div class="pt-3 mt-3 border-t border-slate-100 flex items-center justify-between gap-1.5">
                    <button type="button" onclick="abrirModalEditarPerfilColab(${c.idcliforemp})" class="flex-1 py-1.5 px-2 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-extrabold text-[11px] transition flex items-center justify-center gap-1 cursor-pointer" title="Editar dados e perfil na área separada">
                        <i class="fa-solid fa-user-pen text-[10px]"></i> Editar
                    </button>
                    <button type="button" onclick="abrirModalCapturaFoto(${c.idcliforemp})" class="py-1.5 px-2.5 rounded-xl bg-slate-100 hover:bg-emerald-50 text-slate-700 hover:text-emerald-700 font-extrabold text-[11px] transition flex items-center justify-center gap-1 cursor-pointer" title="Tirar foto ou enviar arquivo">
                        <i class="fa-solid fa-camera text-[10px]"></i> Foto
                    </button>
                    <button type="button" onclick="iniciarAvaliacaoDireta(${c.idcliforemp})" class="py-1.5 px-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-[11px] transition flex items-center justify-center gap-1 shadow-xs cursor-pointer" title="Iniciar Avaliação C.H.A.P.D deste colaborador">
                        <i class="fa-solid fa-clipboard-check text-[10px]"></i> Avaliar
                    </button>
                </div>
            </div>
        `;
    }).join('');
}

function abrirModalManual() {
    $('modalManual')?.classList.remove('hidden');
}

function fecharModalManual() {
    $('modalManual')?.classList.add('hidden');
}

// Helpers
function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function formatarDataBR(dataStr) {
    if (!dataStr) return '--';
    const partes = dataStr.split('-');
    if (partes.length === 3) return `${partes[2]}/${partes[1]}/${partes[0]}`;
    return dataStr;
}
