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
    faixasClassificacao: [],
    
    // Estado do Wizard de Avaliação
    colaboradorSelecionado: null,
    anoSelecionado: new Date().getFullYear(),
    mesSelecionado: new Date().getMonth() + 1,
    indicePerguntaAtual: 0,
    respostas: {}, // { idpergunta: { pontuacao: 10, observacao_item: '' } }
    conceitoLetraSelecionada: '',
    
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

    // Avatar com Iniciais
    const nomes = colab.nome.trim().split(' ');
    const iniciais = (nomes[0][0] + (nomes.length > 1 ? nomes[nomes.length - 1][0] : '')).toUpperCase();
    $('colabAvatar').textContent = iniciais;

    // Sugere Setor baseado no Cargo se estiver vazio
    const inputSetor = $('inputSetor');
    if (inputSetor && !inputSetor.value) {
        const cargoUpper = (colab.cargo || '').toUpperCase();
        if (cargoUpper.includes('COMERCIAL') || cargoUpper.includes('VEND')) inputSetor.value = 'Comercial';
        else if (cargoUpper.includes('CARGA') || cargoUpper.includes('MOTOR') || cargoUpper.includes('EXPED')) inputSetor.value = 'Logística';
        else if (cargoUpper.includes('TI') || cargoUpper.includes('INFOR')) inputSetor.value = 'TI';
        else if (cargoUpper.includes('FINANC') || cargoUpper.includes('CONT')) inputSetor.value = 'Financeiro';
        else inputSetor.value = 'Operacional';
    }

    // Sugere Célula baseado na Filial se estiver vazia
    const inputCelula = $('inputCelula');
    if (inputCelula && !inputCelula.value) {
        inputCelula.value = colab.idfilial === 1 ? 'Matriz SC' : (colab.idfilial === 6 ? 'Filial PR' : `Filial ${colab.idfilial}`);
    }

    cardResumo?.classList.remove('hidden');
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

    // Sugestão Automática de Letra Conceito com base na pontuação
    if (!estado.conceitoLetraSelecionada) {
        if (totalPontos >= 130) selecionarLetra('A');
        else if (totalPontos >= 115) selecionarLetra('B');
        else if (totalPontos >= 100) selecionarLetra('C');
        else if (totalPontos >= 85)  selecionarLetra('D');
        else if (totalPontos >= 65)  selecionarLetra('E');
        else selecionarLetra('F');
    }
}

function voltarAoQuestionario() {
    $('stepSintese').classList.add('hidden');
    $('stepQuestionario').classList.remove('hidden');
    renderizarPerguntaAtual();
}

/**
 * Renderiza os 3 blocos de Letras Conceito (A, B, C, D, E, F)
 */
function renderizarGradeConceitosLetras() {
    const container = $('gradeConceitosLetras');
    if (!container) return;

    container.innerHTML = '';

    estado.conceitosLetras.forEach(grupo => {
        const col = document.createElement('div');
        col.className = 'space-y-2.5';

        const tituloGrupo = document.createElement('div');
        tituloGrupo.className = 'text-[0.68rem] uppercase font-black tracking-wider text-slate-400';
        tituloGrupo.textContent = grupo.grupo;
        col.appendChild(tituloGrupo);

        grupo.itens.forEach(item => {
            const card = document.createElement('div');
            card.id = `cardLetra_${item.letra}`;
            card.className = 'letra-card';
            card.onclick = () => selecionarLetra(item.letra);

            card.innerHTML = `
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-800 font-black text-base flex items-center justify-center flex-shrink-0" id="badgeLetra_${item.letra}">
                        ${item.letra}
                    </div>
                    <div>
                        <div class="font-extrabold text-slate-800 text-xs sm:text-sm">${item.titulo}</div>
                        <p class="text-[0.7rem] text-slate-500 font-semibold leading-tight mt-0.5">${item.descricao}</p>
                    </div>
                </div>
            `;
            col.appendChild(card);
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

        return `
            <tr class="hover:bg-slate-50/80 transition">
                <td class="py-3 px-3">
                    <div class="font-black text-slate-800 text-xs">${esc(colab.nome)}</div>
                    <div class="text-[0.68rem] text-slate-400 font-semibold">${esc(colab.cargo)} • Filial ${colab.filial}</div>
                </td>
                ${colsMeses}
                <td class="py-3 px-3 text-center bg-slate-50 text-slate-800 font-black">${media}</td>
                <td class="py-3 px-3 text-center">
                    <button type="button" onclick="iniciarAvaliacaoDireta(${colab.idcliforemp})" class="p-1.5 px-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-extrabold text-[0.68rem] transition" title="Avaliar este colaborador">
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

    corpo.innerHTML = estado.historico.map(a => `
        <tr class="hover:bg-slate-50 transition">
            <td class="py-3 px-3 font-bold text-slate-700">${a.mes.toString().padStart(2, '0')}/${a.ano}</td>
            <td class="py-3 px-3 font-black text-slate-800">${esc(a.nome_colaborador)}</td>
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
    `).join('');
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

        $('fichaPontos').textContent = `${a.pontuacao_total} / ${a.pontuacao_maxima} pts`;
        $('fichaPercentual').textContent = `${a.percentual_atingido}% de aproveitamento`;
        $('fichaClassificacao').textContent = a.classificacao_pontos;
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
    const abas = ['avaliar', 'dashboard', 'matriz', 'historico', 'perguntas'];

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
    else if (aba === 'matriz') carregarMatrizAnual();
    else if (aba === 'historico') carregarHistorico();
    else if (aba === 'perguntas') carregarPerguntasGestao();
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
