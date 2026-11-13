/**
 * Central de Treinamentos & Manuais — Nutricional Academy
 * Interface Premium & Especialista em Treinamento Operacional
 */

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || (window.location.origin + (window.location.pathname.includes('/API/') ? '/API/v1' : '/v1'))).replace(/\/$/, '');
    const endpoint = (acao || '').replace(/^\/+/, '').replace(/^treinamentos\/?/, '');
    let url = apiRoot + (apiRoot.includes('?') ? (endpoint ? '/' + endpoint : '') : (endpoint ? '/treinamentos/' + endpoint : '/treinamentos'));
    if (apiRoot.includes('?') && !url.includes('/treinamentos')) {
        url = apiRoot + '/treinamentos' + (endpoint ? '/' + endpoint : '');
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

    if (response.status === 403) {
        const err = new Error(data.mensagem || data.error || 'Acesso restrito ao treinamento deste módulo.');
        err.isForbidden = true;
        err.data = data;
        throw err;
    }

    if (!response.ok) {
        throw new Error(data.mensagem || data.error || `Erro HTTP ${response.status}`);
    }
    return data;
};

// Estado Global
const estadoTreinamentos = {
    manuais: [],
    setores: [],
    isAdmin: false,
    manualAtivo: null,
    slugAtivo: '',
    setorFiltroAtivo: '',
    termoBuscaAtivo: '',
    abaManualAtiva: 'objetivo'
};

const $ = (id) => document.getElementById(id);

const esc = (str) => {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

// Inicialização
document.addEventListener('DOMContentLoaded', async () => {
    await carregarListaManuais();

    // Checa se veio parâmetro ?modulo=slug na URL
    const params = new URLSearchParams(window.location.search);
    const moduloParam = params.get('modulo');

    if (moduloParam) {
        selecionarManual(moduloParam);
    } else if (estadoTreinamentos.manuais.length > 0) {
        selecionarManual(estadoTreinamentos.manuais[0].modulo_slug);
    }
});

/**
 * Carrega a lista de manuais disponíveis para o usuário
 */
async function carregarListaManuais() {
    try {
        const data = await apiFetch('');
        if (!data.sucesso) throw new Error(data.mensagem || 'Falha ao carregar manuais');

        estadoTreinamentos.manuais = data.manuais || [];
        estadoTreinamentos.setores = data.setores || [];
        estadoTreinamentos.isAdmin = !!data.is_admin;

        // Atualiza badges do header
        if (!estadoTreinamentos.isAdmin) {
            const b = $('badgeControleAcesso');
            if (b) b.classList.remove('hidden');
        }

        $('totalManuaisBadge').textContent = `${estadoTreinamentos.manuais.length} ${estadoTreinamentos.manuais.length === 1 ? 'módulo' : 'módulos'}`;

        renderizarPillsSetores();
        renderizarSidebar(estadoTreinamentos.manuais);

    } catch (err) {
        console.error('Erro ao carregar lista de manuais:', err);
        $('listaManuaisSidebar').innerHTML = `
            <div class="p-3 bg-rose-50 text-rose-700 rounded-xl text-xs font-bold border border-rose-200">
                <i class="fa-solid fa-triangle-exclamation mr-1"></i> ${esc(err.message)}
            </div>
        `;
    }
}

/**
 * Renderiza os botões/pílulas de setores no topo da sidebar
 */
function renderizarPillsSetores() {
    const box = $('pillsSetoresFiltro');
    if (!box) return;

    let html = `
        <button type="button" onclick="filtrarPorSetor('')" class="tr-filter-pill ${estadoTreinamentos.setorFiltroAtivo === '' ? 'active' : ''}" data-setor="">
            Todos (${estadoTreinamentos.manuais.length})
        </button>
    `;

    estadoTreinamentos.setores.forEach(setor => {
        const qtd = estadoTreinamentos.manuais.filter(m => m.setor === setor).length;
        const isAtivo = (estadoTreinamentos.setorFiltroAtivo === setor);
        html += `
            <button type="button" onclick="filtrarPorSetor('${esc(setor)}')" class="tr-filter-pill ${isAtivo ? 'active' : ''}" data-setor="${esc(setor)}">
                ${esc(setor)} <span class="opacity-75 font-normal text-[0.65rem]">(${qtd})</span>
            </button>
        `;
    });

    box.innerHTML = html;
}

/**
 * Filtra a barra lateral por setor
 */
function filtrarPorSetor(setor) {
    estadoTreinamentos.setorFiltroAtivo = setor;
    renderizarPillsSetores();
    aplicarFiltrosSidebar();
}

/**
 * Filtra a barra lateral por termo de busca
 */
function aoBuscarManual() {
    const input = $('inputBuscaManual');
    estadoTreinamentos.termoBuscaAtivo = (input ? input.value.trim().toLowerCase() : '');
    aplicarFiltrosSidebar();
}

function aplicarFiltrosSidebar() {
    const termo = estadoTreinamentos.termoBuscaAtivo;
    const setor = estadoTreinamentos.setorFiltroAtivo;

    const filtrados = estadoTreinamentos.manuais.filter(m => {
        const casaSetor = (!setor || m.setor === setor);
        const casaTermo = (!termo
            || (m.titulo && m.titulo.toLowerCase().includes(termo))
            || (m.subtitulo && m.subtitulo.toLowerCase().includes(termo))
            || (m.modulo_slug && m.modulo_slug.toLowerCase().includes(termo))
            || (m.objetivo_geral && m.objetivo_geral.toLowerCase().includes(termo))
        );
        return casaSetor && casaTermo;
    });

    renderizarSidebar(filtrados);
}

/**
 * Renderiza os itens da barra lateral
 */
function renderizarSidebar(lista) {
    const box = $('listaManuaisSidebar');
    if (!box) return;

    if (lista.length === 0) {
        box.innerHTML = `
            <div class="text-center py-8 text-slate-400 text-xs font-semibold">
                <i class="fa-solid fa-folder-open text-slate-300 text-2xl block mb-2"></i>
                Nenhum manual encontrado para os filtros selecionados.
            </div>
        `;
        return;
    }

    // Cores temáticas por setor
    const coresSetor = {
        'Logística': { bg: 'bg-blue-100', text: 'text-blue-800' },
        'Frota': { bg: 'bg-amber-100', text: 'text-amber-800' },
        'Financeiro': { bg: 'bg-emerald-100', text: 'text-emerald-800' },
        'Faturamento': { bg: 'bg-teal-100', text: 'text-teal-800' },
        'Análise de Vendas': { bg: 'bg-indigo-100', text: 'text-indigo-800' },
        'Comercial': { bg: 'bg-purple-100', text: 'text-purple-800' },
        'Recursos Humanos': { bg: 'bg-emerald-100', text: 'text-emerald-800' },
        'Marketing': { bg: 'bg-rose-100', text: 'text-rose-800' },
        'TI': { bg: 'bg-slate-100', text: 'text-slate-800' }
    };

    // Agrupa por setor
    const setores = {};
    lista.forEach(m => {
        const s = m.setor || 'Geral';
        if (!setores[s]) setores[s] = [];
        setores[s].push(m);
    });

    let html = '';
    Object.keys(setores).forEach(nomeSetor => {
        html += `
            <div class="pt-2.5 pb-1">
                <span class="text-[0.66rem] uppercase font-black tracking-wider text-slate-400 px-2 block">
                    ${esc(nomeSetor)}
                </span>
            </div>
        `;

        setores[nomeSetor].forEach(m => {
            const isAtivo = (m.modulo_slug === estadoTreinamentos.slugAtivo);
            const estilo = coresSetor[m.setor] || { bg: 'bg-emerald-100', text: 'text-emerald-800' };

            html += `
                <div id="sideItem_${m.modulo_slug}" onclick="selecionarManual('${m.modulo_slug}')" class="tr-sidebar-item ${isAtivo ? 'ativo' : ''}">
                    <div class="w-8 h-8 rounded-xl ${estilo.bg} ${estilo.text} flex items-center justify-center flex-shrink-0 text-xs font-black shadow-xs">
                        <i class="fa-solid ${m.icone || 'fa-book'}"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="font-extrabold text-xs text-slate-800 truncate">${esc(m.titulo)}</div>
                        <div class="text-[0.65rem] text-slate-400 font-semibold truncate flex items-center gap-1.5 mt-0.5">
                            <span>v${m.versao || '2.0'}</span>
                            <span>•</span>
                            <span>${m.data_atualizacao || 'Atualizado'}</span>
                        </div>
                    </div>
                </div>
            `;
        });
    });

    box.innerHTML = html;
}

/**
 * Seleciona e carrega os detalhes completos do manual
 */
async function selecionarManual(slug) {
    estadoTreinamentos.slugAtivo = slug;

    // Atualiza seleção visual na barra lateral
    document.querySelectorAll('.tr-sidebar-item').forEach(el => el.classList.remove('ativo'));
    const itemAtivo = $(`sideItem_${slug}`);
    if (itemAtivo) itemAtivo.classList.add('ativo');

    const areaRestrita = $('telaAcessoRestrito');
    const areaValida = $('conteudoManualValido');

    try {
        const data = await apiFetch(`${slug}`);
        if (!data.sucesso) throw new Error(data.mensagem);

        const m = data.manual;
        estadoTreinamentos.manualAtivo = m;

        // Oculta tela de bloqueio e exibe conteúdo
        if (areaRestrita) areaRestrita.classList.add('hidden');
        if (areaValida) areaValida.classList.remove('hidden');

        // Cabeçalho
        $('manualSetorBadge').textContent = m.setor || 'Geral';
        $('manualVersaoBadge').textContent = `Versão ${m.versao || '2.0'} • ${m.autor || 'Tech Lead'}`;
        $('manualTitulo').textContent = m.titulo;
        $('manualSubtitulo').textContent = m.subtitulo || m.objetivo_geral;

        // Estimativa de tempo de leitura (aprox. 150 palavras por minuto)
        const totalPalavras = (m.objetivo_geral || '').split(/\s+/).length + 300;
        const minutosLeitura = Math.max(3, Math.ceil(totalPalavras / 120));
        $('manualTempoLeituraBadge').innerHTML = `<i class="fa-regular fa-clock"></i> ${minutosLeitura} min de leitura`;

        // Link do botão "Acessar Módulo"
        const urlsModulos = {
            'avaliacao-chapd': '/portal/modules/avaliacao-chapd.php',
            'movimentacao-flex': '/portal/modules/movimentacao-flex.php',
            'transferencia-compra': '/portal/modules/transferencia-compra.php',
            'transferencia-matriz-filial': '/portal/modules/transferencia-compra.php?tipo=matriz-filial',
            'transferencia-filial-matriz': '/portal/modules/transferencia-compra.php?tipo=filial-matriz',
            'verbas-flex': '/portal/modules/verbas-flex.php',
            'separacao': '/portal/modules/separacao.php',
            'carregamento': '/portal/modules/carregamento.php',
            'monitor': '/portal/modules/monitor.php',
            'desembarque': '/portal/modules/desembarque.php',
            'gestao-deposito': '/portal/modules/gestao-deposito.php',
            'auditoria': '/portal/modules/auditoria.php',
            'estoque-previsao': '/portal/modules/estoque-previsao.php',
            'inventario': '/portal/modules/inventario.php',
            'frota': '/portal/modules/frota.php',
            'frota-embarques': '/portal/modules/frota-embarques.php',
            'retorno-carga': '/portal/modules/retorno-carga.php',
            'acerto-embarque': '/portal/modules/acerto-embarque.php',
            'motorista-offline': '/portal/modules/motorista-offline.php',
            'frota-cadastro': '/portal/modules/frota-cadastro.php',
            'financeiro': '/portal/modules/financeiro.php',
            'analise_carteira': '/portal/modules/analise_carteira.php',
            'consulta-saldo': '/portal/modules/consulta-saldo.php',
            'xml': '/portal/modules/xml.php',
            'cubo_vendas': '/portal/modules/cubo-vendas.php',
            'cubo-vendas': '/portal/modules/cubo-vendas.php',
            'dashboard_vendas': '/portal/modules/dashboard-vendas.php',
            'dashboard-vendas': '/portal/modules/dashboard-vendas.php',
            'recompra_clientes': '/portal/modules/recompra-clientes.php',
            'recompra-clientes': '/portal/modules/recompra-clientes.php',
            'crons': '/portal/modules/admin/crons.php',
            'admin': '/portal/modules/admin/index.php'
        };
        const urlBase = (window.location.pathname.includes('/API/') ? '/API' : '');
        const destino = urlsModulos[m.modulo_slug] || `/portal/modules/${m.modulo_slug}.php`;
        $('btnIrParaModulo').href = urlBase + destino;

        // 1. Aba Objetivo
        $('manualObjetivoTexto').textContent = m.objetivo_geral;

        // 2. Aba KPIs
        renderizarAbaKpis(m.cards_kpis || []);

        // 3. Aba Ações
        renderizarAbaAcoes(m.acoes_botoes || []);

        // 4. Aba Relatórios
        renderizarAbaRelatorios(m.relatorios_tabelas || []);

        // 5. Aba Dúvidas
        renderizarAbaDuvidas(m.duvidas_frequentes || []);

        // Mantém na aba corrente
        trocarAbaManual(estadoTreinamentos.abaManualAtiva || 'objetivo');

        // Atualiza a URL sem recarregar a página
        const novaUrl = window.location.pathname + `?modulo=${slug}`;
        window.history.replaceState({ path: novaUrl }, '', novaUrl);

    } catch (err) {
        console.error('Erro ao abrir manual:', err);

        // Se foi bloqueado por falta de permissão (403)
        if (err.isForbidden) {
            if (areaValida) areaValida.classList.add('hidden');
            if (areaRestrita) {
                areaRestrita.classList.remove('hidden');
                $('msgAcessoRestrito').textContent = err.message || 'Seu usuário não possui permissão concedida para operar este módulo.';
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Erro ao carregar manual',
                text: err.message || 'Não foi possível carregar os dados deste treinamento.'
            });
        }
    }
}

function voltarParaPrimeiroManual() {
    if (estadoTreinamentos.manuais.length > 0) {
        selecionarManual(estadoTreinamentos.manuais[0].modulo_slug);
    }
}

/**
 * Renderiza a Aba de KPIs
 */
function renderizarAbaKpis(kpis) {
    const box = $('manualKpisLista');
    if (!box) return;

    if (!kpis || kpis.length === 0) {
        box.innerHTML = '<div class="p-4 bg-slate-50 text-slate-400 rounded-2xl text-xs font-semibold">Nenhum card de KPI cadastrado neste manual.</div>';
        return;
    }

    box.innerHTML = kpis.map((k, idx) => {
        const nome = k.nome || k.card || `Indicador #${idx + 1}`;
        const descricao = k.descricao || k.significado || '';
        const formula = k.formula || k.meta_ideal || '';

        return `
            <div class="p-4 rounded-2xl bg-slate-50/70 border border-slate-200">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="w-6 h-6 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center font-black text-xs flex-shrink-0">
                        ${idx + 1}
                    </span>
                    <h4 class="font-black text-slate-800 text-xs sm:text-sm">${esc(nome)}</h4>
                </div>
                <p class="text-xs text-slate-600 leading-relaxed pl-8">
                    ${esc(descricao)}
                </p>
                ${formula ? `
                    <div class="mt-2 pl-8 flex flex-wrap items-center gap-2">
                        <span class="text-[0.68rem] font-bold text-slate-500 bg-white px-2.5 py-1 rounded-lg border border-slate-200 inline-flex items-center gap-1.5 font-mono-code">
                            <i class="fa-solid fa-calculator text-emerald-600"></i> ${esc(formula)}
                        </span>
                    </div>
                ` : ''}
            </div>
        `;
    }).join('');
}

/**
 * Renderiza a Aba de Ações e Botões
 */
function renderizarAbaAcoes(acoes) {
    const box = $('manualAcoesLista');
    if (!box) return;

    if (!acoes || acoes.length === 0) {
        box.innerHTML = '<div class="p-4 bg-slate-50 text-slate-400 rounded-2xl text-xs font-semibold">Nenhum botão ou ação cadastrada.</div>';
        return;
    }

    const coresBtn = {
        emerald: 'bg-emerald-600 text-white',
        blue: 'bg-blue-600 text-white',
        amber: 'bg-amber-500 text-white',
        rose: 'bg-rose-600 text-white',
        slate: 'bg-slate-700 text-white'
    };

    box.innerHTML = acoes.map((a, idx) => {
        const elemento = a.botao || a.elemento || a.nome || a.titulo || `Ação #${idx + 1}`;
        const fluxo = a.funcao || a.fluxo || a.descricao || a.acao || '';
        const cor = coresBtn[a.cor] || 'bg-slate-800 text-white';

        return `
            <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-xs flex flex-col sm:flex-row sm:items-start gap-3">
                <div class="flex-shrink-0">
                    <span class="px-3 py-1.5 rounded-xl font-black text-[0.7rem] uppercase tracking-wider inline-flex items-center gap-1.5 shadow-xs ${cor}">
                        <i class="fa-solid fa-hand-pointer text-[0.65rem]"></i> ${esc(elemento)}
                    </span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-xs text-slate-700 leading-relaxed font-medium">
                        ${esc(fluxo)}
                    </p>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Renderiza a Aba de Relatórios e Tabelas
 */
function renderizarAbaRelatorios(relatorios) {
    const box = $('manualRelatoriosLista');
    if (!box) return;

    if (!relatorios || relatorios.length === 0) {
        box.innerHTML = '<div class="p-4 bg-slate-50 text-slate-400 rounded-2xl text-xs font-semibold">Nenhum relatório cadastrado.</div>';
        return;
    }

    box.innerHTML = relatorios.map(r => {
        const titulo = r.nome || r.tabela || 'Tabela do Módulo';
        const descricao = r.descricao || r.utilidade || '';

        return `
            <div class="p-4 rounded-2xl bg-slate-50/70 border border-slate-200">
                <h4 class="font-black text-slate-800 text-xs sm:text-sm flex items-center gap-2 mb-1.5">
                    <i class="fa-solid fa-table-list text-emerald-600"></i> ${esc(titulo)}
                </h4>
                <p class="text-xs text-slate-600 leading-relaxed pl-6">
                    ${esc(descricao)}
                </p>
            </div>
        `;
    }).join('');
}

/**
 * Renderiza a Aba de Dúvidas e FAQ
 */
function renderizarAbaDuvidas(duvidas) {
    const box = $('manualDuvidasLista');
    if (!box) return;

    if (!duvidas || duvidas.length === 0) {
        box.innerHTML = '<div class="p-4 bg-slate-50 text-slate-400 rounded-2xl text-xs font-semibold">Nenhuma dúvida frequente cadastrada.</div>';
        return;
    }

    box.innerHTML = duvidas.map(d => {
        const pergunta = d.pergunta || d.duvida || '';
        const resposta = d.resposta || '';

        return `
            <div class="p-4 rounded-2xl bg-emerald-50/50 border border-emerald-100">
                <h4 class="font-black text-emerald-950 text-xs sm:text-sm flex items-start gap-2 mb-1.5">
                    <i class="fa-solid fa-circle-question text-emerald-600 mt-0.5 flex-shrink-0"></i>
                    <span>${esc(pergunta)}</span>
                </h4>
                <p class="text-xs text-slate-700 leading-relaxed pl-6 font-medium">
                    ${esc(resposta)}
                </p>
            </div>
        `;
    }).join('');
}

/**
 * Alterna entre as 5 abas do manual
 */
function trocarAbaManual(aba) {
    estadoTreinamentos.abaManualAtiva = aba;

    const abas = ['objetivo', 'kpis', 'acoes', 'relatorios', 'duvidas'];
    abas.forEach(a => {
        const tabEl = $(`tabManual${a.charAt(0).toUpperCase() + a.slice(1)}`);
        const painelEl = $(`painelManual${a.charAt(0).toUpperCase() + a.slice(1)}`);

        if (tabEl) {
            if (a === aba) {
                tabEl.classList.add('on');
            } else {
                tabEl.classList.remove('on');
            }
        }

        if (painelEl) {
            if (a === aba) {
                painelEl.classList.remove('hidden');
            } else {
                painelEl.classList.add('hidden');
            }
        }
    });
}
