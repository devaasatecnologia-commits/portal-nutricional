/**
 * Módulo Plano de Ação 5W2H & PDI (Integrado à Avaliação C.H.A.P.D)
 * Segue fielmente a estrutura da planilha corporativa PLANO DE AÇÃO
 */

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || (window.location.origin + (window.location.pathname.includes('/API/') ? '/API/v1' : '/v1'))).replace(/\/$/, '');
    const cleanAcao = acao.replace(/^\/+/, '').replace(/^avaliacao-chapd\//, '');
    const [acaoPath, acaoQuery] = cleanAcao.split('?');
    let url = apiRoot + '/avaliacao-chapd/' + acaoPath;
    if (acaoQuery) {
        url += (url.includes('?') ? '&' : '?') + acaoQuery;
    }
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

const $ = (id) => document.getElementById(id);

const esc = (str) => {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
};

function formatarUrlFoto(caminho) {
    if (!caminho) return '';
    if (caminho.startsWith('http://') || caminho.startsWith('https://') || caminho.startsWith('data:') || caminho.startsWith('blob:')) {
        if (caminho.includes('index.php?api_route=/uploads/') || caminho.includes('index.php?api_route=uploads/')) {
            caminho = caminho.replace(/\/index\.php\?api_route=\/?/, '/');
        }
        const isLocalHost = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
        if (isLocalHost && caminho.includes('api.nutricionalbr.com/uploads/')) {
            const pathAfterDomain = caminho.split('api.nutricionalbr.com/')[1];
            const basePrefix = window.location.pathname.includes('/API/') ? '/API' : '';
            return `${window.location.origin}${basePrefix}/${pathAfterDomain}`;
        }
        return caminho;
    }
    const cleanPath = caminho.replace(/^\/+/, '');
    const basePrefix = window.location.pathname.includes('/API/') ? '/API' : '';
    return `${window.location.origin}${basePrefix}/${cleanPath}`;
}

const swalBase = {
    confirmButtonColor: '#e11d48',
    cancelButtonColor: '#64748b',
    customClass: {
        popup: 'rounded-3xl border border-slate-100 shadow-2xl font-sans text-sm',
        confirmButton: 'rounded-xl font-black px-5 py-2.5 text-xs uppercase tracking-wider',
        cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs uppercase tracking-wider'
    }
};

const estadoPa = {
    abaAtiva: 'visao',
    colaboradores: [],
    pendentesPdi: [],
    planos: [],
    itensEditor: [],
    avaliacaoVinculada: null
};

document.addEventListener('DOMContentLoaded', async () => {
    await carregarContextoColab();
    await carregarTudoPlanoAcao();

    const params = new URLSearchParams(window.location.search);
    const idPlano = parseInt(params.get('idplano') || '0');
    const idAvaliacao = parseInt(params.get('idavaliacao') || '0');

    if (idPlano > 0) {
        await abrirPlanoExistente(idPlano);
    } else if (idAvaliacao > 0) {
        await iniciarPlanoAPartirDeAvaliacao(idAvaliacao);
    }
});

function alternarAbaPa(aba) {
    estadoPa.abaAtiva = aba;
    const btnVisao = $('tabPaVisao');
    const btnEditor = $('tabPaEditor');
    const painelVisao = $('painelVisaoGeral');
    const painelEditor = $('painelEditor5W2H');

    if (aba === 'editor') {
        btnVisao?.classList.remove('on');
        btnEditor?.classList.add('on');
        painelVisao?.classList.add('hidden');
        painelEditor?.classList.remove('hidden');
        if (estadoPa.itensEditor.length === 0) {
            adicionarLinha5W2H();
        }
    } else {
        btnEditor?.classList.remove('on');
        btnVisao?.classList.add('on');
        painelEditor?.classList.add('hidden');
        painelVisao?.classList.remove('hidden');
    }
}

async function carregarContextoColab() {
    try {
        const data = await apiFetch('contexto');
        if (data.sucesso) {
            estadoPa.colaboradores = data.colaboradores || [];
            const sel = $('paSelectColaborador');
            if (sel) {
                sel.innerHTML = '<option value="">Plano Corporativo / Geral (ou selecione o colaborador)...</option>';
                estadoPa.colaboradores.forEach(c => {
                    const opt = document.createElement('option');
                    opt.value = c.idcliforemp;
                    opt.textContent = `${c.nome} (${c.cargo} • Filial ${c.idfilial})`;
                    sel.appendChild(opt);
                });
            }
        }
    } catch (e) {
        console.error('Erro ao carregar colaboradores:', e);
    }
}

async function carregarTudoPlanoAcao() {
    await Promise.all([
        carregarPendentesPdi(),
        carregarPlanosAcao()
    ]);
}

async function carregarPendentesPdi() {
    const ano = $('filtroPdiAno')?.value || '';
    const filial = $('filtroPdiFilial')?.value || '';
    const grid = $('gridPendentesPdi');

    try {
        const data = await apiFetch(`planos-acao/pendentes?ano=${ano}&filial=${filial}`);
        if (!data.sucesso) throw new Error(data.mensagem);

        estadoPa.pendentesPdi = data.pendentes || [];
        if ($('kpiPaPendentesSemPlano')) {
            $('kpiPaPendentesSemPlano').textContent = data.sem_plano || 0;
        }
        renderizarGridPendentesPdi();
    } catch (e) {
        console.error('Erro ao carregar pendentes PDI:', e);
        if (grid) {
            grid.innerHTML = `<div class="col-span-full text-center py-6 text-rose-600 text-xs font-bold">${esc(e.message)}</div>`;
        }
    }
}

function renderizarGridPendentesPdi() {
    const grid = $('gridPendentesPdi');
    if (!grid) return;

    if (estadoPa.pendentesPdi.length === 0) {
        grid.innerHTML = `
            <div class="col-span-full p-6 rounded-2xl bg-emerald-50/70 border border-emerald-200 text-center">
                <i class="fa-solid fa-circle-check text-emerald-600 text-xl mb-1"></i>
                <div class="text-sm font-black text-emerald-900">Nenhum colaborador pendente de PDI neste filtro!</div>
                <p class="text-xs text-emerald-700 font-semibold mt-0.5">Quando uma avaliação C.H.A.P.D tiver pontuação menor que 85 ou for marcada pelo avaliador, ela aparecerá automaticamente aqui.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = estadoPa.pendentesPdi.map(item => {
        const urlFoto = item.foto_url ? formatarUrlFoto(item.foto_url) : '';
        const nomes = (item.nome_colaborador || 'C').trim().split(' ');
        const iniciais = (nomes[0][0] + (nomes.length > 1 ? nomes[nomes.length - 1][0] : '')).toUpperCase();
        const pts = parseFloat(item.pontuacao_total || 0);
        const isAbaixo85 = pts < 85;

        const badgeOrigem = isAbaixo85
            ? `<span class="px-2 py-0.5 rounded-full bg-rose-600 text-white text-[10px] font-black uppercase">Nota &lt; 85 (${pts} pts)</span>`
            : `<span class="px-2 py-0.5 rounded-full bg-amber-500 text-slate-900 text-[10px] font-black uppercase">Indicado pelo Avaliador (${pts} pts)</span>`;

        const temPlano = !!item.idplano;
        const statusPlanoBadge = temPlano
            ? obterBadgeStatusGeral(item.plano_status)
            : `<span class="px-2 py-0.5 rounded-md bg-rose-100 text-rose-800 border border-rose-200 text-[10px] font-black">AGUARDANDO PLANO 5W2H</span>`;

        const avatarHtml = urlFoto
            ? `<img src="${urlFoto}" class="w-12 h-12 rounded-2xl object-cover border-2 border-white shadow-sm flex-none" alt="Foto" onerror="this.style.display='none'">`
            : `<div class="w-12 h-12 rounded-2xl bg-rose-600 text-white font-black text-sm flex items-center justify-center flex-none shadow-sm">${iniciais}</div>`;

        return `
            <div class="p-4 rounded-2xl ${temPlano ? 'bg-slate-50 border border-slate-200' : 'bg-rose-50/60 border-2 border-rose-200'} flex flex-col justify-between gap-3 transition hover:shadow-md">
                <div>
                    <div class="flex items-start justify-between gap-2 mb-2.5">
                        <div class="flex items-center gap-2.5 overflow-hidden">
                            ${avatarHtml}
                            <div class="overflow-hidden">
                                <div class="font-black text-slate-900 text-sm truncate">${esc(item.nome_colaborador)}</div>
                                <div class="text-[11px] text-slate-500 font-bold truncate">${esc(item.cargo)} • Filial ${item.idfilial}</div>
                                <div class="text-[10px] text-slate-400 font-semibold">Ref: ${String(item.mes).padStart(2,'0')}/${item.ano} • Avaliador: ${esc(item.avaliador_nome)}</div>
                            </div>
                        </div>
                        <span class="w-8 h-8 rounded-xl bg-rose-100 text-rose-800 font-black text-xs flex items-center justify-center flex-none border border-rose-200" title="Conceito C.H.A.P.D">
                            ${esc(item.conceito_letra || 'E')}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-1.5 mb-2">
                        ${badgeOrigem}
                        ${statusPlanoBadge}
                    </div>

                    <div class="p-2.5 rounded-xl bg-white border border-slate-200/80 text-xs text-slate-600 space-y-1">
                        <div><b class="text-slate-800">Pontos a Desenvolver:</b> ${esc(item.pontos_desenvolver || item.plano_acao_resumo || 'Realizar levantamento 5W2H junto ao colaborador.')}</div>
                        <div class="text-[10px] text-slate-400 font-bold">
                            Pilares: C:${item.pontos_conhecimento} | H:${item.pontos_habilidade} | A:${item.pontos_atitude} | P:${item.pontos_postura} | D:${item.pontos_disciplina}
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-200/60">
                    <a href="avaliacao-chapd.php?ficha=${item.idavaliacao}" class="py-2 px-3 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 font-bold text-xs transition no-underline flex items-center gap-1">
                        <i class="fa-solid fa-file-lines"></i> Ficha
                    </a>
                    ${temPlano ? `
                        <button type="button" onclick="abrirPlanoExistente(${item.idplano})" class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-extrabold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-xs">
                            <i class="fa-solid fa-pen-to-square"></i> Abrir Plano 5W2H (${item.itens_concluidos}/${item.total_itens})
                        </button>
                    ` : `
                        <button type="button" onclick="iniciarPlanoAPartirDeAvaliacao(${item.idavaliacao})" class="flex-1 py-2 px-3 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-extrabold text-xs transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm">
                            <i class="fa-solid fa-plus-circle"></i> Estruturar Plano 5W2H
                        </button>
                    `}
                </div>
            </div>
        `;
    }).join('');
}

async function carregarPlanosAcao() {
    const ano = $('filtroPdiAno')?.value || '';
    const filial = $('filtroPdiFilial')?.value || '';
    const status = $('filtroPlanoStatus')?.value || '';
    const busca = $('filtroPlanoBusca')?.value || '';
    const tbody = $('tabelaPlanosCorpo');

    try {
        const data = await apiFetch(`planos-acao?ano=${ano}&filial=${filial}&status=${encodeURIComponent(status)}&busca=${encodeURIComponent(busca)}`);
        if (!data.sucesso) throw new Error(data.mensagem);

        estadoPa.planos = data.planos || [];
        const k = data.kpis || {};
        if ($('kpiPaDentroPrazo')) $('kpiPaDentroPrazo').textContent = k.dentro_prazo || 0;
        if ($('kpiPaConcluidas')) $('kpiPaConcluidas').textContent = k.concluidas || 0;
        if ($('kpiPaAtraso')) $('kpiPaAtraso').textContent = k.atraso_recuperavel || 0;
        if ($('kpiPaComprometido')) $('kpiPaComprometido').textContent = k.comprometido || 0;

        renderizarTabelaPlanos();
    } catch (e) {
        console.error('Erro ao carregar planos:', e);
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-rose-600 font-bold">${esc(e.message)}</td></tr>`;
        }
    }
}

function obterBadgeStatusGeral(status) {
    const st = (status || 'DENTRO_DO_PRAZO').toUpperCase();
    if (st === 'ACAO_CONCLUIDA' || st === 'CONCLUIDO') {
        return `<span class="px-2.5 py-1 rounded-lg st-concluida text-[10px] font-black uppercase">Ação Concluída</span>`;
    }
    if (st === 'CRONOGRAMA_COMPROMETIDO') {
        return `<span class="px-2.5 py-1 rounded-lg st-comprometido text-[10px] font-black uppercase">Cronograma Comprometido</span>`;
    }
    if (st === 'ATRASO_RECUPERAVEL') {
        return `<span class="px-2.5 py-1 rounded-lg st-atraso text-[10px] font-black uppercase">Atraso Recuperável</span>`;
    }
    return `<span class="px-2.5 py-1 rounded-lg st-dentro-prazo text-[10px] font-black uppercase">Dentro do Prazo</span>`;
}

function renderizarTabelaPlanos() {
    const tbody = $('tabelaPlanosCorpo');
    if (!tbody) return;

    if (estadoPa.planos.length === 0) {
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-8 text-slate-400">Nenhum Plano de Ação 5W2H registrado ainda. Clique em "Estruturar Plano 5W2H" em um colaborador acima ou em "Novo Plano de Ação".</td></tr>`;
        return;
    }

    tbody.innerHTML = estadoPa.planos.map(p => {
        const total = parseInt(p.total_itens || 0);
        const concl = parseInt(p.concluidas || 0);
        const perc = total > 0 ? Math.round((concl / total) * 100) : 0;

        return `
            <tr class="hover:bg-slate-50 transition">
                <td class="py-3 px-3">
                    <div class="font-black text-slate-900">#${p.id} • ${esc(p.nome_colaborador || 'Plano Corporativo / Setorial')}</div>
                    <div class="text-[11px] text-slate-400 font-semibold">
                        ${esc(p.cargo || p.setor || 'Geral')} • Filial ${p.idfilial || 1}
                        ${p.pontuacao_origem ? ` • Origem: <b class="text-rose-600">${p.pontuacao_origem} pts (${p.conceito_origem || '-'})</b>` : ''}
                    </div>
                </td>
                <td class="py-3 px-3 max-w-xs">
                    <div class="font-bold text-rose-800 line-clamp-1" title="${esc(p.problema)}"><b>Problema:</b> ${esc(p.problema)}</div>
                    <div class="text-[11px] text-emerald-800 line-clamp-1 mt-0.5" title="${esc(p.objetivo)}"><b>Objetivo:</b> ${esc(p.objetivo)}</div>
                </td>
                <td class="py-3 px-3">
                    <div class="font-bold text-slate-800">${esc(p.responsavel)}</div>
                    <div class="text-[10px] text-slate-400">Rev: ${esc(p.revisao || p.criado_em_fmt)}</div>
                </td>
                <td class="py-3 px-3 text-center">
                    <div class="font-black text-slate-800">${concl} / ${total} concluídas (${perc}%)</div>
                    <div class="w-28 bg-slate-100 h-1.5 rounded-full mx-auto mt-1 overflow-hidden">
                        <div class="bg-emerald-500 h-full rounded-full" style="width: ${perc}%"></div>
                    </div>
                </td>
                <td class="py-3 px-3 text-center font-bold text-slate-600">
                    ${esc(p.prox_vencimento_fmt || 'Concluído')}
                </td>
                <td class="py-3 px-3 text-center">
                    ${obterBadgeStatusGeral(p.status_geral)}
                </td>
                <td class="py-3 px-3 text-center">
                    <div class="flex items-center justify-center gap-1.5">
                        <button type="button" onclick="abrirPlanoExistente(${p.id})" class="p-1.5 px-3 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-extrabold text-xs transition flex items-center gap-1 cursor-pointer">
                            <i class="fa-solid fa-table-list"></i> Abrir 5W2H
                        </button>
                        <button type="button" onclick="excluirPlano5W2H(${p.id})" class="p-1.5 px-2.5 rounded-xl bg-slate-100 hover:bg-rose-100 text-slate-500 hover:text-rose-700 transition cursor-pointer" title="Excluir Plano">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

// =========================================================================
// EDITOR 5W2H (CRIAR NOVO, CRIAR A PARTIR DE AVALIAÇÃO OU EDITAR EXISTENTE)
// =========================================================================

function abrirNovoPlanoAvulso() {
    limparFormularioEditor();
    $('paOrigemTipo').value = 'CORPORATIVO';
    $('tituloEditorPlano').textContent = 'NOVO PLANO DE AÇÃO 5W2H';
    $('badgeIdPlanoEditor').textContent = '#Novo';
    $('badgeIdPlanoEditor').classList.remove('hidden');
    estadoPa.itensEditor = [];
    adicionarLinha5W2H();
    alternarAbaPa('editor');
}

function limparFormularioEditor() {
    $('paId').value = '';
    $('paIdAvaliacao').value = '';
    $('paOrigemTipo').value = 'PDI_ABAIXO_85';
    $('paSelectColaborador').value = '';
    $('paCargo').value = '';
    $('paSetor').value = '';
    $('paFilial').value = '1';
    $('paProblema').value = '';
    $('paObjetivo').value = '';
    $('paResponsavel').value = 'Sandro Rafo';
    $('paAprovador').value = 'Robson Becker';
    $('paRevisao').value = `${new Date().toLocaleDateString('pt-BR')} - Rev. 01`;
    if ($('paMetaPontuacao')) $('paMetaPontuacao').value = '90';
    if ($('paPrazoReavaliacao')) $('paPrazoReavaliacao').value = '30';
    if ($('paPilaresCriticos')) $('paPilaresCriticos').value = '';
    if ($('paFeedbackColaborador')) $('paFeedbackColaborador').value = '';
    if ($('badgeCienciaPlano')) $('badgeCienciaPlano').textContent = 'Registro de ciência e percepção do colaborador sobre o PDI';
    $('bannerOrigemAvaliacao')?.classList.add('hidden');
    $('painelRaioXPilaresPdi')?.classList.add('hidden');
    estadoPa.avaliacaoVinculada = null;
    estadoPa.itensEditor = [];
}

function aoSelecionarColabNoPlano() {
    const id = parseInt($('paSelectColaborador')?.value || '0');
    const colab = estadoPa.colaboradores.find(c => parseInt(c.idcliforemp) === id);
    if (!colab) return;

    $('paCargo').value = colab.cargo || '';
    $('paSetor').value = colab.setor_custom || '';
    $('paFilial').value = String(colab.idfilial || 1);
}

function renderizarRaioXPilaresPdi(a) {
    const box = $('painelRaioXPilaresPdi');
    if (!box) return;

    const pilares = [
        { sigla: 'C', nome: 'Conhecimento', pts: parseFloat(a.pontos_conhecimento ?? a.pilar_c_origem ?? 0) },
        { sigla: 'H', nome: 'Habilidade', pts: parseFloat(a.pontos_habilidade ?? a.pilar_h_origem ?? 0) },
        { sigla: 'A', nome: 'Atitude', pts: parseFloat(a.pontos_atitude ?? a.pilar_a_origem ?? 0) },
        { sigla: 'P', nome: 'Postura', pts: parseFloat(a.pontos_postura ?? a.pilar_p_origem ?? 0) },
        { sigla: 'D', nome: 'Disciplina', pts: parseFloat(a.pontos_disciplina ?? a.pilar_d_origem ?? 0) }
    ];

    const somaPilares = pilares.reduce((s, p) => s + p.pts, 0);
    if (somaPilares <= 0) {
        box.classList.add('hidden');
        return;
    }

    box.classList.remove('hidden');
    const criticos = [];

    box.innerHTML = pilares.map(p => {
        const perc = Math.min(100, Math.round((p.pts / 30) * 100));
        let statusTag = '';
        let barColor = 'bg-emerald-500';
        let cardCls = 'bg-white border-slate-200';

        if (p.pts < 18) {
            criticos.push(`${p.sigla} (${p.nome})`);
            statusTag = '<span class="px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 text-[9px] font-black">GAP CRÍTICO</span>';
            barColor = 'bg-rose-600';
            cardCls = 'bg-rose-50/70 border-rose-300';
        } else if (p.pts < 24) {
            criticos.push(`${p.sigla} (${p.nome})`);
            statusTag = '<span class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 text-[9px] font-black">DESENVOLVER</span>';
            barColor = 'bg-amber-500';
            cardCls = 'bg-amber-50/50 border-amber-200';
        } else {
            statusTag = '<span class="px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[9px] font-black">ADEQUADO</span>';
        }

        return `
            <div class="p-2.5 rounded-xl border ${cardCls}">
                <div class="flex items-center justify-between gap-1 mb-1">
                    <span class="text-xs font-black text-slate-800">${p.sigla} • ${p.nome}</span>
                    ${statusTag}
                </div>
                <div class="flex items-baseline justify-between text-[11px] font-bold text-slate-600 mb-1">
                    <span>${p.pts} / 30 pts</span>
                    <span>${perc}%</span>
                </div>
                <div class="w-full h-1.5 bg-slate-200 rounded-full overflow-hidden">
                    <div class="${barColor} h-full rounded-full" style="width:${perc}%"></div>
                </div>
            </div>
        `;
    }).join('');

    if ($('paPilaresCriticos') && !$('paPilaresCriticos').value) {
        $('paPilaresCriticos').value = criticos.length > 0 ? criticos.join(', ') : 'Todos os Pilares C.H.A.P.D';
    }
}

async function iniciarPlanoAPartirDeAvaliacao(idavaliacao) {
    try {
        const data = await apiFetch(`detalhes/${idavaliacao}`);
        if (!data.sucesso) throw new Error(data.mensagem);

        const a = data.avaliacao;
        if (a.idplano) {
            await abrirPlanoExistente(a.idplano);
            return;
        }

        limparFormularioEditor();
        estadoPa.avaliacaoVinculada = a;

        $('paIdAvaliacao').value = a.id;
        $('paSelectColaborador').value = a.idcliforemp;
        $('paCargo').value = a.cargo || '';
        $('paSetor').value = a.setor || '';
        $('paFilial').value = String(a.idfilial || 1);
        $('paResponsavel').value = a.avaliador_nome || 'Sandro Rafo';
        $('paAprovador').value = a.aprovador_nome || 'Robson Becker';

        const pts = parseFloat(a.pontuacao_total || 0);
        $('paOrigemTipo').value = pts < 85 ? 'PDI_ABAIXO_85' : 'PDI_INDICACAO_AVALIADOR';
        if ($('paMetaPontuacao')) $('paMetaPontuacao').value = pts < 85 ? '90' : String(Math.min(150, Math.round(pts + 15)));
        if ($('paPrazoReavaliacao')) $('paPrazoReavaliacao').value = pts < 60 ? '30' : '60';
        if ($('paFeedbackColaborador')) $('paFeedbackColaborador').value = a.feedback_colaborador || '';

        $('paProblema').value = a.pontos_desenvolver || `Pontuação C.H.A.P.D (${pts} pts - Conceito ${a.conceito_letra}) requer desenvolvimento estruturado nos pilares identificados na avaliação.`;
        $('paObjetivo').value = a.plano_acao || `Desenvolver as competências técnicas e comportamentais de ${a.nome_colaborador} aplicando a metodologia 70-20-10 para atingir desempenho igual ou superior a 90 pontos no próximo ciclo C.H.A.P.D.`;

        $('tituloEditorPlano').textContent = `PLANO DE AÇÃO & PDI • ${a.nome_colaborador}`;
        $('badgeIdPlanoEditor').textContent = 'Novo PDI';
        $('badgeIdPlanoEditor').classList.remove('hidden');

        exibirBannerAvaliacaoOrigem(a);
        renderizarRaioXPilaresPdi(a);

        // Cria etapas sugeridas automaticamente com base nas respostas de menor nota + metodologia 70-20-10
        const respostasBaixas = (data.respostas || []).filter(r => parseFloat(r.pontuacao) <= 6);
        estadoPa.itensEditor = [];

        const hoje = new Date().toISOString().slice(0, 10);
        const mais15 = new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 10);
        const mais30 = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
        const localPadrao = a.idfilial == 6 ? 'Filial PR' : (a.idfilial == 2 ? 'Filial 2 SC' : 'NTD / Matriz');

        // 1ª Etapa Obrigatória: Alinhamento 1:1 (20% Mentoria)
        estadoPa.itensEditor.push({
            ordem: 1,
            pilar_chapd: 'GERAL',
            tipo_702010: '20_MENTORIA',
            o_que: 'Realizar Reunião 1:1 de Devolutiva C.H.A.P.D e Pactuação do PDI',
            como: 'Apresentar o diagnóstico por pilar, ouvir a percepção do colaborador e formalizar o compromisso de evolução',
            onde: localPadrao,
            quem: a.avaliador_nome || 'Sandro Rafo',
            data_inicio: hoje,
            data_termino: mais15,
            status: 'Dentro do Prazo',
            observacao: `Nota de origem: ${pts} pts (Conceito ${a.conceito_letra})`,
            evidencia_status: 'P',
            evidencia_auditoria: 'Termo de ciência / Feedback registrado no sistema'
        });

        if (respostasBaixas.length > 0) {
            respostasBaixas.slice(0, 4).forEach((r, idx) => {
                const trilha = (r.pilar === 'C') ? '10_TREINAMENTO' : '70_PRATICA';
                estadoPa.itensEditor.push({
                    ordem: idx + 2,
                    pilar_chapd: r.pilar || 'GERAL',
                    tipo_702010: trilha,
                    o_que: `Desenvolver e corrigir rotina no critério [${r.pilar}]: ${r.pergunta}`,
                    como: r.pilar === 'C'
                        ? 'Realizar reciclagem normativa/procedimental com estudo dirigido e validação de aprendizado'
                        : 'Executar acompanhamento prático on-the-job com checklist diário e correção imediata de desvios',
                    onde: localPadrao,
                    quem: a.avaliador_nome || 'Sandro Rafo',
                    data_inicio: hoje,
                    data_termino: mais30,
                    status: 'Dentro do Prazo',
                    observacao: r.observacao_item || `Nota atribuída na avaliação: ${r.pontuacao} pts`,
                    evidencia_status: 'P',
                    evidencia_auditoria: 'Checklist prático / Registro de acompanhamento'
                });
            });
        } else {
            inserirEtapasSugeridasPdi(false);
        }

        renderizarLinhas5W2H();
        alternarAbaPa('editor');

    } catch (e) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro ao carregar avaliação', text: e.message });
    }
}

function exibirBannerAvaliacaoOrigem(a) {
    const banner = $('bannerOrigemAvaliacao');
    if (!banner) return;
    banner.classList.remove('hidden');

    const pts = parseFloat(a.pontuacao_total || a.pontuacao_origem || 0);
    const letra = a.conceito_letra || a.conceito_origem || '-';
    $('bannerTextoColab').textContent = `Colaborador: ${a.nome_colaborador} (${a.cargo || 'Colaborador'})`;
    $('bannerSubtextoColab').textContent = `Avaliação C.H.A.P.D #${a.idavaliacao || a.id} • Ref: ${String(a.mes).padStart(2,'0')}/${a.ano} • Nota: ${pts} pts (Conceito ${letra})`;

    const btnFicha = $('btnVerFichaOrigem');
    if (btnFicha) {
        const idAv = a.idavaliacao || a.id;
        btnFicha.onclick = () => {
            window.location.href = `avaliacao-chapd.php?ficha=${idAv}`;
        };
    }
}

async function abrirPlanoExistente(idPlano) {
    try {
        const data = await apiFetch(`planos-acao/${idPlano}`);
        if (!data.sucesso) throw new Error(data.mensagem);

        limparFormularioEditor();
        const p = data.plano;
        estadoPa.avaliacaoVinculada = p;

        $('paId').value = p.id;
        $('paIdAvaliacao').value = p.idavaliacao || '';
        $('paOrigemTipo').value = p.origem_tipo || 'PDI_ABAIXO_85';
        $('paSelectColaborador').value = p.idcliforemp || '';
        $('paCargo').value = p.cargo || '';
        $('paSetor').value = p.setor || '';
        $('paFilial').value = String(p.idfilial || 1);
        $('paProblema').value = p.problema || '';
        $('paObjetivo').value = p.objetivo || '';
        $('paResponsavel').value = p.responsavel || 'Sandro Rafo';
        $('paAprovador').value = p.aprovador || 'Robson Becker';
        $('paRevisao').value = p.revisao || '';
        if ($('paMetaPontuacao')) $('paMetaPontuacao').value = p.meta_pontuacao || 90;
        if ($('paPrazoReavaliacao')) $('paPrazoReavaliacao').value = String(p.prazo_dias_reavaliacao || 30);
        if ($('paPilaresCriticos')) $('paPilaresCriticos').value = p.pilares_criticos || '';
        if ($('paFeedbackColaborador')) $('paFeedbackColaborador').value = p.feedback_colaborador || '';
        if ($('badgeCienciaPlano')) {
            $('badgeCienciaPlano').textContent = p.data_ciencia_fmt
                ? `Ciência registrada em ${p.data_ciencia_fmt}`
                : 'Registro de ciência e percepção do colaborador sobre o PDI';
        }

        $('tituloEditorPlano').textContent = `PLANO DE AÇÃO #${p.id} • ${p.nome_colaborador || 'Corporativo'}`;
        $('badgeIdPlanoEditor').textContent = `#${p.id}`;
        $('badgeIdPlanoEditor').classList.remove('hidden');

        if (p.idavaliacao) {
            exibirBannerAvaliacaoOrigem(p);
            renderizarRaioXPilaresPdi(p);
        }

        estadoPa.itensEditor = (data.itens || []).map((it, idx) => ({
            ordem: idx + 1,
            pilar_chapd: it.pilar_chapd || 'GERAL',
            tipo_702010: it.tipo_702010 || '70_PRATICA',
            o_que: it.o_que || '',
            como: it.como || '',
            onde: it.onde || 'NTD',
            quem: it.quem || p.responsavel || '',
            data_inicio: it.data_inicio || '',
            data_termino: it.data_termino || '',
            status: it.status || 'Dentro do Prazo',
            observacao: it.observacao || '',
            evidencia_status: it.evidencia_status || 'P',
            evidencia_auditoria: it.evidencia_auditoria || ''
        }));

        renderizarLinhas5W2H();
        alternarAbaPa('editor');

    } catch (e) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro ao abrir Plano de Ação', text: e.message });
    }
}

function sincronizarInputsDaTabela() {
    const trs = document.querySelectorAll('#corpoTabela5W2H tr[data-idx]');
    trs.forEach(tr => {
        const idx = parseInt(tr.getAttribute('data-idx'));
        if (!estadoPa.itensEditor[idx]) return;
        estadoPa.itensEditor[idx].pilar_chapd = tr.querySelector('.in-pilar')?.value || 'GERAL';
        estadoPa.itensEditor[idx].tipo_702010 = tr.querySelector('.in-702010')?.value || '70_PRATICA';
        estadoPa.itensEditor[idx].o_que = tr.querySelector('.in-oque')?.value || '';
        estadoPa.itensEditor[idx].como = tr.querySelector('.in-como')?.value || '';
        estadoPa.itensEditor[idx].onde = tr.querySelector('.in-onde')?.value || 'NTD';
        estadoPa.itensEditor[idx].quem = tr.querySelector('.in-quem')?.value || '';
        estadoPa.itensEditor[idx].data_inicio = tr.querySelector('.in-inicio')?.value || '';
        estadoPa.itensEditor[idx].data_termino = tr.querySelector('.in-termino')?.value || '';
        estadoPa.itensEditor[idx].status = tr.querySelector('.in-status')?.value || 'Dentro do Prazo';
        estadoPa.itensEditor[idx].observacao = tr.querySelector('.in-obs')?.value || '';
        estadoPa.itensEditor[idx].evidencia_status = tr.querySelector('.in-evstatus')?.value || 'P';
        estadoPa.itensEditor[idx].evidencia_auditoria = tr.querySelector('.in-evaud')?.value || '';
    });
    atualizarResumo702010();
}

function atualizarResumo702010() {
    const badge = $('resumo702010Badge');
    if (!badge) return;
    const c70 = estadoPa.itensEditor.filter(i => (i.tipo_702010 || '70_PRATICA') === '70_PRATICA').length;
    const c20 = estadoPa.itensEditor.filter(i => i.tipo_702010 === '20_MENTORIA').length;
    const c10 = estadoPa.itensEditor.filter(i => i.tipo_702010 === '10_TREINAMENTO').length;

    badge.innerHTML = `
        <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-200">70% Prática (${c70})</span>
        <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 border border-amber-200">20% Mentoria (${c20})</span>
        <span class="px-2.5 py-1 rounded-lg bg-sky-100 text-sky-800 border border-sky-200">10% Treinamento (${c10})</span>
    `;
}

function adicionarLinha5W2H() {
    sincronizarInputsDaTabela();
    const hoje = new Date().toISOString().slice(0, 10);
    const mais15 = new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 10);
    const respPadrao = $('paResponsavel')?.value || 'Sandro Rafo';

    estadoPa.itensEditor.push({
        ordem: estadoPa.itensEditor.length + 1,
        pilar_chapd: 'GERAL',
        tipo_702010: '70_PRATICA',
        o_que: '',
        como: '',
        onde: 'NTD',
        quem: respPadrao,
        data_inicio: hoje,
        data_termino: mais15,
        status: 'Dentro do Prazo',
        observacao: '',
        evidencia_status: 'P',
        evidencia_auditoria: ''
    });

    renderizarLinhas5W2H();
}

function adicionarRecomendacaoPorPilar(pilar) {
    sincronizarInputsDaTabela();
    const hoje = new Date().toISOString().slice(0, 10);
    const d20 = new Date(Date.now() + 20 * 86400000).toISOString().slice(0, 10);
    const resp = $('paResponsavel')?.value || 'Sandro Rafo';
    const filial = $('paFilial')?.value || '1';
    const onde = filial === '6' ? 'Filial PR' : (filial === '2' ? 'Filial 2 SC' : 'NTD');

    const catalogo = {
        C: {
            pilar_chapd: 'C',
            tipo_702010: '10_TREINAMENTO',
            o_que: 'Realizar reciclagem técnica sobre normas, processos operacionais e manuais da função',
            como: 'Estudo dirigido dos procedimentos padrão do setor + sabatina prática com o gestor imediato',
            observacao: 'Foco no Pilar C (Conhecimento Técnico)',
            evidencia_auditoria: 'Avaliação de eficácia / Lista de treinamento assinada'
        },
        H: {
            pilar_chapd: 'H',
            tipo_702010: '70_PRATICA',
            o_que: 'Executar prática assistida (On-the-Job) para ganho de produtividade, agilidade e zero retrabalho',
            como: 'Acompanhamento de rotina com padrinho técnico durante 2 semanas corrigindo gargalos de execução',
            observacao: 'Foco no Pilar H (Habilidade Prática)',
            evidencia_auditoria: 'Indicador de produtividade / Checklist sem erros'
        },
        A: {
            pilar_chapd: 'A',
            tipo_702010: '70_PRATICA',
            o_que: 'Assumir protagonismo na resolução de problemas e melhoria proativa das rotinas da célula',
            como: 'Apresentar semanalmente ao gestor 1 melhoria ou solução antecipada de gargalo do setor',
            observacao: 'Foco no Pilar A (Atitude e Proatividade)',
            evidencia_auditoria: 'Registro das iniciativas aplicadas no período'
        },
        P: {
            pilar_chapd: 'P',
            tipo_702010: '20_MENTORIA',
            o_que: 'Alinhar comunicação interpessoal, trabalho em equipe e inteligência emocional sob pressão',
            como: 'Sessões quinzenais de mentoria 1:1 com feedback comportamental e observação direta no setor',
            observacao: 'Foco no Pilar P (Postura Profissional)',
            evidencia_auditoria: 'Ata de mentoria 1:1 e avaliação de clima'
        },
        D: {
            pilar_chapd: 'D',
            tipo_702010: '70_PRATICA',
            o_que: 'Cumprir rigorosamente horários, uso de EPIs, organização (5S) e prazos estabelecidos',
            como: 'Monitoramento diário de assiduidade, conformidade de segurança e entrega de tarefas no prazo',
            observacao: 'Foco no Pilar D (Disciplina e Conformidade)',
            evidencia_auditoria: 'Espelho de ponto / Auditoria 5S sem não-conformidades'
        }
    };

    const rec = catalogo[pilar] || catalogo.C;
    const novoItem = {
        ordem: estadoPa.itensEditor.length + 1,
        pilar_chapd: rec.pilar_chapd,
        tipo_702010: rec.tipo_702010,
        o_que: rec.o_que,
        como: rec.como,
        onde,
        quem: resp,
        data_inicio: hoje,
        data_termino: d20,
        status: 'Dentro do Prazo',
        observacao: rec.observacao,
        evidencia_status: 'P',
        evidencia_auditoria: rec.evidencia_auditoria
    };

    if (estadoPa.itensEditor.length === 1 && !estadoPa.itensEditor[0].o_que) {
        estadoPa.itensEditor[0] = novoItem;
    } else {
        estadoPa.itensEditor.push(novoItem);
    }

    renderizarLinhas5W2H();
}

function removerLinha5W2H(idx) {
    sincronizarInputsDaTabela();
    if (estadoPa.itensEditor.length <= 1) {
        Swal.fire({ ...swalBase, icon: 'info', title: 'Mínimo 1 Etapa', text: 'O Plano de Ação deve possuir pelo menos uma etapa 5W2H.' });
        return;
    }
    estadoPa.itensEditor.splice(idx, 1);
    renderizarLinhas5W2H();
}

function inserirEtapasSugeridasPdi(render = true) {
    if (render) sincronizarInputsDaTabela();
    const hoje = new Date().toISOString().slice(0, 10);
    const d10 = new Date(Date.now() + 10 * 86400000).toISOString().slice(0, 10);
    const d20 = new Date(Date.now() + 20 * 86400000).toISOString().slice(0, 10);
    const d30 = new Date(Date.now() + 30 * 86400000).toISOString().slice(0, 10);
    const resp = $('paResponsavel')?.value || 'Sandro Rafo';

    const sugestoes = [
        {
            pilar_chapd: 'GERAL',
            tipo_702010: '20_MENTORIA',
            o_que: 'Realizar sessão de Feedback Estruturado 1:1 e alinhamento de expectativas C.H.A.P.D',
            como: 'Apresentar os pontos de atenção da avaliação, ouvir o colaborador e pactuar metas claras de melhoria',
            onde: 'NTD',
            quem: resp,
            data_inicio: hoje,
            data_termino: d10,
            status: 'Dentro do Prazo',
            observacao: 'Etapa inicial obrigatória do PDI (20% Mentoria)',
            evidencia_status: 'P',
            evidencia_auditoria: 'Ata de feedback assinada / Ciência no sistema'
        },
        {
            pilar_chapd: 'C',
            tipo_702010: '10_TREINAMENTO',
            o_que: 'Promover reciclagem conceitual e revisão dos procedimentos operacionais padrão (POP)',
            como: 'Orientação técnica focada nas normas do setor e esclarecimento de dúvidas operacionais',
            onde: 'NTD',
            quem: resp,
            data_inicio: hoje,
            data_termino: d20,
            status: 'Dentro do Prazo',
            observacao: 'Base teórica (10% Treinamento Formal)',
            evidencia_status: 'P',
            evidencia_auditoria: 'Lista de presença / Registro de instrução'
        },
        {
            pilar_chapd: 'H',
            tipo_702010: '70_PRATICA',
            o_que: 'Executar acompanhamento prático (On-the-Job) na rotina diária de trabalho',
            como: 'Prática assistida com gestor/padrinho validando qualidade de entrega, postura e cumprimento de prazos',
            onde: 'NTD',
            quem: resp,
            data_inicio: d10,
            data_termino: d30,
            status: 'Dentro do Prazo',
            observacao: 'Consolidação prática (70% On-the-Job)',
            evidencia_status: 'P',
            evidencia_auditoria: 'Checklist de acompanhamento concluído'
        },
        {
            pilar_chapd: 'GERAL',
            tipo_702010: '20_MENTORIA',
            o_que: 'Realizar Reavaliação C.H.A.P.D de fechamento de ciclo PDI (30 dias)',
            como: 'Aplicar nova rodada de verificação nos pilares críticos para validar evolução à meta (>= 90 pontos)',
            onde: 'NTD',
            quem: resp,
            data_inicio: d20,
            data_termino: d30,
            status: 'Dentro do Prazo',
            observacao: 'Fechamento do ciclo PDI',
            evidencia_status: 'P',
            evidencia_auditoria: 'Nova ficha C.H.A.P.D registrada'
        }
    ];

    if (estadoPa.itensEditor.length === 1 && !estadoPa.itensEditor[0].o_que) {
        estadoPa.itensEditor = sugestoes;
    } else {
        estadoPa.itensEditor.push(...sugestoes);
    }

    if (render) renderizarLinhas5W2H();
}

function obterClasseSelectStatus(status) {
    if (status === 'Ação Concluída') return 'st-concluida';
    if (status === 'Cronograma Comprometido') return 'st-comprometido';
    if (status === 'Atraso Recuperável') return 'st-atraso';
    return 'st-dentro-prazo';
}

function renderizarLinhas5W2H() {
    const tbody = $('corpoTabela5W2H');
    if (!tbody) return;

    tbody.innerHTML = estadoPa.itensEditor.map((it, idx) => {
        const clsStatus = obterClasseSelectStatus(it.status);
        const clsEv = `ev-${it.evidencia_status || 'P'}`;
        const pilar = it.pilar_chapd || 'GERAL';
        const trilha = it.tipo_702010 || '70_PRATICA';

        return `
            <tr data-idx="${idx}" class="align-top hover:bg-slate-50/70 transition">
                <td class="py-3 px-2 text-center font-black text-slate-400">${idx + 1}</td>
                <td class="py-2.5 px-2 space-y-1">
                    <select class="in-pilar pa-input mt-0 py-1 px-2 text-[11px] font-black text-slate-800 bg-slate-50">
                        <option value="GERAL" ${pilar === 'GERAL' ? 'selected' : ''}>Pilar: Geral</option>
                        <option value="C" ${pilar === 'C' ? 'selected' : ''}>[C] Conhecimento</option>
                        <option value="H" ${pilar === 'H' ? 'selected' : ''}>[H] Habilidade</option>
                        <option value="A" ${pilar === 'A' ? 'selected' : ''}>[A] Atitude</option>
                        <option value="P" ${pilar === 'P' ? 'selected' : ''}>[P] Postura</option>
                        <option value="D" ${pilar === 'D' ? 'selected' : ''}>[D] Disciplina</option>
                    </select>
                    <select onchange="sincronizarInputsDaTabela()" class="in-702010 pa-input mt-0 py-1 px-2 text-[10px] font-extrabold text-indigo-800 bg-indigo-50/60 border-indigo-200">
                        <option value="70_PRATICA" ${trilha === '70_PRATICA' ? 'selected' : ''}>70% Prática Rotina</option>
                        <option value="20_MENTORIA" ${trilha === '20_MENTORIA' ? 'selected' : ''}>20% Mentoria / 1:1</option>
                        <option value="10_TREINAMENTO" ${trilha === '10_TREINAMENTO' ? 'selected' : ''}>10% Treinamento</option>
                    </select>
                </td>
                <td class="py-2.5 px-2">
                    <textarea rows="2" class="in-oque pa-input mt-0 text-xs" placeholder="Ex: Realizar treinamento de...">${esc(it.o_que)}</textarea>
                </td>
                <td class="py-2.5 px-2">
                    <textarea rows="2" class="in-como pa-input mt-0 text-xs" placeholder="Descreva as etapas...">${esc(it.como)}</textarea>
                </td>
                <td class="py-2.5 px-2">
                    <input type="text" class="in-onde pa-input mt-0 text-xs" value="${esc(it.onde || 'NTD')}" placeholder="NTD / Filial">
                </td>
                <td class="py-2.5 px-2">
                    <input type="text" class="in-quem pa-input mt-0 text-xs" value="${esc(it.quem)}" placeholder="Responsável">
                </td>
                <td class="py-2.5 px-2">
                    <input type="date" class="in-inicio pa-input mt-0 text-xs px-2" value="${esc(it.data_inicio)}">
                </td>
                <td class="py-2.5 px-2">
                    <input type="date" class="in-termino pa-input mt-0 text-xs px-2" value="${esc(it.data_termino)}">
                </td>
                <td class="py-2.5 px-2">
                    <select onchange="this.className='in-status w-full p-2 rounded-xl text-xs font-black uppercase cursor-pointer ' + obterClasseSelectStatus(this.value)" class="in-status w-full p-2 rounded-xl text-xs font-black uppercase cursor-pointer ${clsStatus}">
                        <option value="Dentro do Prazo" ${it.status === 'Dentro do Prazo' ? 'selected' : ''}>Dentro do Prazo</option>
                        <option value="Ação Concluída" ${it.status === 'Ação Concluída' ? 'selected' : ''}>Ação Concluída</option>
                        <option value="Atraso Recuperável" ${it.status === 'Atraso Recuperável' ? 'selected' : ''}>Atraso Recuperável</option>
                        <option value="Cronograma Comprometido" ${it.status === 'Cronograma Comprometido' ? 'selected' : ''}>Cronograma Comprometido</option>
                    </select>
                </td>
                <td class="py-2.5 px-2">
                    <textarea rows="2" class="in-obs pa-input mt-0 text-xs" placeholder="Observações...">${esc(it.observacao)}</textarea>
                </td>
                <td class="py-2.5 px-2 space-y-1">
                    <select onchange="this.className='in-evstatus w-full p-1.5 rounded-lg text-[11px] font-black text-center cursor-pointer ev-' + this.value" class="in-evstatus w-full p-1.5 rounded-lg text-[11px] font-black text-center cursor-pointer ${clsEv}">
                        <option value="P" ${it.evidencia_status === 'P' ? 'selected' : ''}>P (Pendente)</option>
                        <option value="OK" ${it.evidencia_status === 'OK' ? 'selected' : ''}>OK (Verificado)</option>
                        <option value="NOK" ${it.evidencia_status === 'NOK' ? 'selected' : ''}>NOK (Não Conforme)</option>
                    </select>
                    <input type="text" class="in-evaud pa-input mt-0 py-1 px-2 text-[11px]" value="${esc(it.evidencia_auditoria)}" placeholder="Evidência / Doc...">
                </td>
                <td class="py-3 px-2 text-center no-print">
                    <button type="button" onclick="removerLinha5W2H(${idx})" class="w-7 h-7 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center transition cursor-pointer" title="Remover etapa">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');

    atualizarResumo702010();
}

async function salvarPlano5W2H() {
    sincronizarInputsDaTabela();

    const idColab = parseInt($('paSelectColaborador')?.value || '0');
    const colabObj = estadoPa.colaboradores.find(c => parseInt(c.idcliforemp) === idColab);
    const nomeColab = colabObj ? colabObj.nome : (estadoPa.avaliacaoVinculada?.nome_colaborador || '');

    const problema = $('paProblema')?.value.trim() || '';
    const objetivo = $('paObjetivo')?.value.trim() || '';

    if (!problema) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Problema Obrigatório', text: 'Preencha o campo PROBLEMA / GAPS DE COMPETÊNCIA no cabeçalho do plano.' });
        return;
    }
    if (!objetivo) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Objetivo Obrigatório', text: 'Preencha o campo OBJETIVO ESPERADO no cabeçalho do plano.' });
        return;
    }

    const itensPreenchidos = estadoPa.itensEditor.filter(i => i.o_que.trim() !== '');
    if (itensPreenchidos.length === 0) {
        Swal.fire({ ...swalBase, icon: 'warning', title: 'Adicione Etapas 5W2H', text: 'Preencha pelo menos uma linha com a coluna "O QUE" na tabela 5W2H.' });
        return;
    }

    const payload = {
        id: $('paId')?.value ? parseInt($('paId').value) : null,
        idavaliacao: $('paIdAvaliacao')?.value ? parseInt($('paIdAvaliacao').value) : null,
        idcliforemp: idColab > 0 ? idColab : null,
        nome_colaborador: nomeColab,
        cargo: $('paCargo')?.value.trim() || '',
        setor: $('paSetor')?.value.trim() || '',
        idfilial: parseInt($('paFilial')?.value || '1'),
        ano: estadoPa.avaliacaoVinculada?.ano || new Date().getFullYear(),
        mes: estadoPa.avaliacaoVinculada?.mes || (new Date().getMonth() + 1),
        pontuacao_origem: estadoPa.avaliacaoVinculada?.pontuacao_total ?? estadoPa.avaliacaoVinculada?.pontuacao_origem ?? null,
        conceito_origem: estadoPa.avaliacaoVinculada?.conceito_letra ?? estadoPa.avaliacaoVinculada?.conceito_origem ?? null,
        origem_tipo: $('paOrigemTipo')?.value || 'PDI_ABAIXO_85',
        problema,
        objetivo,
        responsavel: $('paResponsavel')?.value.trim() || 'Sandro Rafo',
        aprovador: $('paAprovador')?.value.trim() || 'Robson Becker',
        revisao: $('paRevisao')?.value.trim() || '',
        meta_pontuacao: parseFloat($('paMetaPontuacao')?.value || '90'),
        prazo_dias_reavaliacao: parseInt($('paPrazoReavaliacao')?.value || '30'),
        pilares_criticos: $('paPilaresCriticos')?.value.trim() || '',
        feedback_colaborador: $('paFeedbackColaborador')?.value.trim() || null,
        itens: itensPreenchidos
    };

    const btn = $('btnSalvarPlano5W2H');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Salvando...';
    }

    try {
        const res = await apiFetch('planos-acao/salvar', 'POST', payload);
        if (!res.sucesso) throw new Error(res.mensagem);

        $('paId').value = res.id;
        $('badgeIdPlanoEditor').textContent = `#${res.id}`;

        await carregarTudoPlanoAcao();

        Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Plano de Ação 5W2H Salvo!',
            text: 'O Plano de Ação e todas as etapas do cronograma foram gravados com sucesso.',
            showCancelButton: true,
            confirmButtonText: 'Voltar para Fila PDI',
            cancelButtonText: 'Continuar na Planilha 5W2H'
        }).then(r => {
            if (r.isConfirmed) {
                alternarAbaPa('visao');
            }
        });

    } catch (e) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro ao Salvar', text: e.message });
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Salvar Plano de Ação 5W2H';
        }
    }
}

async function excluirPlano5W2H(id) {
    const confirm = await Swal.fire({
        ...swalBase,
        icon: 'warning',
        title: `Excluir Plano #${id}?`,
        text: 'Esta ação removerá o Plano de Ação e suas etapas 5W2H.',
        showCancelButton: true,
        confirmButtonText: 'Sim, Excluir',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    try {
        const res = await apiFetch(`planos-acao/${id}`, 'DELETE');
        if (!res.sucesso) throw new Error(res.mensagem);
        await carregarTudoPlanoAcao();
        Swal.fire({ ...swalBase, icon: 'success', title: 'Excluído!', text: res.mensagem, timer: 1800, showConfirmButton: false });
    } catch (e) {
        Swal.fire({ ...swalBase, icon: 'error', title: 'Erro ao excluir', text: e.message });
    }
}

