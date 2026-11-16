// Módulo Movimentação de Flex — Nutricional API & Portal
// Controle manual de saldo Flex de representantes com cobertura de Gestor

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    let url = apiRoot + '/' + acao.replace(/^\/+/, '').replace(/^v1\//, '');
    const options = { method: metodo, headers: {}, credentials: 'include' };
    const token = localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) options.headers.Authorization = 'Bearer ' + token;

    if (metodo === 'GET' && body) {
        url += (url.includes('?') ? '&' : '?') + new URLSearchParams(body).toString();
    } else if (body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    const response = await fetch(url, options);
    const text = await response.text();
    let data = {};
    try {
        data = text ? JSON.parse(text) : {};
    } catch {
        data = { error: text || 'Resposta inválida do servidor' };
    }
    if (!response.ok) {
        throw new Error(data.error || `Erro ${response.status}`);
    }
    return data;
};

const $ = (id) => document.getElementById(id);
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const brl = (n) => Number(n || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const swalBase = { confirmButtonColor: '#059669', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = {
    usuario: '',
    is_admin: false,
    filiais: [1, 6],
    filial: 1,
    reps: [],
    repSelecionado: null,
    gestores: {},
    gestoresEquipe: [],
    gestoresHistorico: [],
    gestorAtual: null,
    metaCache: null,
    tipo: 'DEBITO',
    valor: 0,
    observacao: '',
    usarGestor: true,
    historico: [],
    resumoAuditoria: {},
    enviando: false,
    abaAtiva: 'movimentar'
};

// ==========================================
// INICIALIZAÇÃO DO MÓDULO
// ==========================================
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const userData = JSON.parse(localStorage.getItem('userData') || '{}');
        const permissoes = userData.permissoes || [];
        const isAdmin = !!(userData.is_admin || permissoes.includes('admin') || userData.nivel === 'admin');
        if (!isAdmin && !permissoes.includes('movimentacao-flex')) {
            const wrap = document.querySelector('.mf-wrap') || document.body;
            wrap.innerHTML = `
                <div class="max-w-xl mx-auto my-16 bg-white rounded-3xl p-8 sm:p-10 border border-slate-200 shadow-xl text-center">
                    <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center mx-auto mb-4 text-2xl font-black shadow-xs">
                        <i class="fa-solid fa-lock"></i>
                    </div>
                    <h2 class="text-xl font-black text-slate-800 mb-2">Acesso Restrito ao Módulo</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mb-6 leading-relaxed">
                        Seu perfil de usuário não possui permissão para executar ou gerenciar a <b>Movimentação Manual de Flex</b>. Esta funcionalidade é exclusiva para gestores e administradores autorizados.
                    </p>
                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <a href="/portal/" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white font-bold text-xs hover:bg-slate-900 transition no-underline">
                            <i class="fa-solid fa-arrow-left mr-1.5"></i> Dashboard Principal
                        </a>
                        <a href="/portal/modules/verbas-flex.php" class="px-5 py-2.5 rounded-xl bg-emerald-600 text-white font-bold text-xs hover:bg-emerald-700 transition no-underline">
                            <i class="fa-solid fa-chart-pie mr-1.5"></i> Gestão de Verbas Flex
                        </a>
                    </div>
                </div>
            `;
            return;
        }

        inicializarDatasHistorico();
        await carregarContexto();
        $('selFilial').addEventListener('change', async (e) => {
            estado.filial = Number(e.target.value);
            if ($('filtroGestorEquipe')) $('filtroGestorEquipe').value = '';
            if ($('filtroHistGestor')) $('filtroHistGestor').value = '';
            if ($('filtroHistColab')) $('filtroHistColab').value = '';
            await carregarRepresentantes();
            await carregarHistorico();
        });

        $('selRep').addEventListener('change', (e) => {
            const id = Number(e.target.value);
            selecionarRepresentante(id);
        });

        await carregarRepresentantes();
        await carregarHistorico();
    } catch (err) {
        console.error('Falha na inicialização:', err);
    }
});

// ==========================================
// CARREGAMENTO DE CONTEXTO E REPRESENTANTES
// ==========================================
async function carregarContexto() {
    try {
        const res = await apiFetch('flex-movimentacao/contexto');
        if (res.success) {
            estado.usuario = res.usuario?.username || '';
            estado.is_admin = res.usuario?.is_admin || false;
            estado.filiais = res.filiais_disponiveis || [1, 6];
            estado.gestores = res.gestores || {};

            // Atualiza select de filiais
            const selF = $('selFilial');
            selF.innerHTML = '';
            estado.filiais.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f;
                opt.textContent = f === 1 ? 'Matriz 1 (SC)' : 'Filial 6 (PR)';
                opt.className = 'text-slate-800 font-bold';
                selF.appendChild(opt);
            });
            selF.value = estado.filial;
        }
    } catch (e) {
        console.warn('Não foi possível carregar contexto inicial:', e.getMessage);
    }
}

async function carregarRepresentantes(forcarToast = false) {
    try {
        $('selRep').innerHTML = '<option value="">Carregando representantes da filial...</option>';
        $('tabelaEquipeCorpo').innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Consultando API Mercos...</td></tr>';

        const res = await apiFetch(`flex-movimentacao/representantes?filial=${estado.filial}${forcarToast ? '&forcar=1' : ''}`);
        if (!res.success) throw new Error(res.error || 'Erro ao carregar dados');

        estado.reps = res.representantes || [];
        estado.gestoresEquipe = res.gestores_equipe || [];
        estado.gestorAtual = res.gestor_filial || null;
        estado.metaCache = res.meta_cache || null;

        atualizarBadgeStatusCache(estado.metaCache);
        popularSelectoresEquipeEHistorico();

        // Atualiza KPIs
        if (estado.gestorAtual) {
            $('kpiSaldoGestor').textContent = brl(estado.gestorAtual.saldo);
            $('kpiNomeGestor').textContent = estado.gestorAtual.nome;
            $('txtNomeGestorCard').textContent = estado.gestorAtual.nome;
            $('txtCargoGestorCard').textContent = estado.gestorAtual.cargo || `Gestor Filial ${estado.filial}`;
            $('txtSaldoGestorCard').textContent = brl(estado.gestorAtual.saldo);
        }

        $('kpiTotalReps').textContent = estado.reps.length;
        $('badgeCountReps').textContent = estado.reps.length;

        const saldoTotalEquipe = estado.reps.reduce((acc, r) => acc + (Number(r.saldo) || 0), 0);
        $('kpiSaldoEquipe').textContent = brl(saldoTotalEquipe);

        // Preenche dropdown de seleção
        const sel = $('selRep');
        sel.innerHTML = '<option value="">-- Escolha um Representante --</option>';
        estado.reps.forEach(r => {
            const opt = document.createElement('option');
            opt.value = r.id;
            const sinal = r.saldo >= 0 ? '+' : '';
            opt.textContent = `${r.nome} · Saldo: ${sinal}${brl(r.saldo)}`;
            sel.appendChild(opt);
        });

        // Renderiza Tabela da Equipe aplicando filtros ativos
        filtrarEquipe();

        // Se havia representante selecionado, revalida
        if (estado.repSelecionado) {
            const encontrado = estado.reps.find(r => r.id === estado.repSelecionado.id);
            if (encontrado) {
                selecionarRepresentante(encontrado.id);
            } else {
                limparSelecaoRep();
            }
        } else {
            limparSelecaoRep();
        }

        if (forcarToast) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: 'Saldos atualizados com o Mercos!',
                showConfirmButton: false,
                timer: 2000
            });
        }
    } catch (e) {
        aviso('error', 'Falha ao Conectar com a Mercos', e.message);
    }
}

function selecionarRepresentante(id) {
    const rep = estado.reps.find(r => r.id === id);
    if (!rep) {
        limparSelecaoRep();
        return;
    }

    estado.repSelecionado = rep;
    $('selRep').value = rep.id;

    const box = $('boxSaldoRep');
    const txt = $('txtSaldoRep');
    const badge = $('badgeStatusSaldoRep');
    const detalhe = $('txtDetalheRep');

    txt.textContent = brl(rep.saldo);
    detalhe.textContent = `${rep.nome} (Cód: ${rep.id}) · Gestor: ${rep.gestor || 'Não informado'}`;

    if (rep.saldo > 0) {
        box.className = 'p-4 rounded-2xl border transition-all bg-emerald-50/80 border-emerald-200 text-emerald-950';
        txt.className = 'text-2xl font-black text-emerald-700 mt-1';
        badge.className = 'text-[0.7rem] font-extrabold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
        badge.textContent = 'Saldo Positivo';
    } else if (rep.saldo === 0) {
        box.className = 'p-4 rounded-2xl border transition-all bg-amber-50/80 border-amber-200 text-amber-950';
        txt.className = 'text-2xl font-black text-amber-700 mt-1';
        badge.className = 'text-[0.7rem] font-extrabold px-2 py-0.5 rounded-full bg-amber-100 text-amber-800';
        badge.textContent = 'Saldo Zerado (R$ 0,00)';
    } else {
        box.className = 'p-4 rounded-2xl border transition-all bg-rose-50/80 border-rose-200 text-rose-950';
        txt.className = 'text-2xl font-black text-rose-700 mt-1';
        badge.className = 'text-[0.7rem] font-extrabold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800';
        badge.textContent = 'Saldo Negativo';
    }

    atualizarPrevisoes();
}

function limparSelecaoRep() {
    estado.repSelecionado = null;
    $('selRep').value = '';
    const box = $('boxSaldoRep');
    box.className = 'p-4 rounded-2xl border transition-all bg-slate-50 border-slate-200';
    $('txtSaldoRep').textContent = 'R$ 0,00';
    $('txtSaldoRep').className = 'text-2xl font-black text-slate-700 mt-1';
    $('badgeStatusSaldoRep').className = 'text-[0.7rem] font-extrabold px-2 py-0.5 rounded-full bg-slate-200 text-slate-700';
    $('badgeStatusSaldoRep').textContent = 'Selecione';
    $('txtDetalheRep').textContent = 'Nenhum colaborador selecionado';
    atualizarPrevisoes();
}

// ==========================================
// TIPO DE OPERAÇÃO & ENTRADA DE DADOS
// ==========================================
function definirTipo(tipo) {
    estado.tipo = tipo;
    const btnC = $('btnTipoCredito');
    const btnD = $('btnTipoDebito');
    const boxGestor = $('boxOpcaoGestor');

    if (tipo === 'CREDITO') {
        btnC.className = 'type-btn active-credito';
        btnD.className = 'type-btn inactive';
        boxGestor.classList.add('hidden');
    } else {
        btnC.className = 'type-btn inactive';
        btnD.className = 'type-btn active-debito';
        boxGestor.classList.remove('hidden');
    }

    atualizarPrevisoes();
}

function valorNumerico() {
    const digitos = ($('inpValor').value || '').replace(/\D/g, '');
    return digitos ? Number(digitos) / 100 : 0;
}

function formatarInputMoeda() {
    const val = valorNumerico();
    $('inpValor').value = val ? brl(val) : '';
    atualizarPrevisoes();
}

function adicionarValor(inc) {
    const atual = valorNumerico();
    const novo = atual + inc;
    $('inpValor').value = brl(novo);
    atualizarPrevisoes();
}

function zerarValor() {
    $('inpValor').value = '';
    atualizarPrevisoes();
}

// ==========================================
// PREVIEW DINÂMICO & SIMULAÇÃO DAS ETAPAS
// ==========================================
function atualizarPrevisoes() {
    const val = valorNumerico();
    const obs = ($('inpObservacao').value || '').trim();
    estado.observacao = obs;
    estado.usarGestor = $('chkUsarGestor').checked;

    $('charCount').textContent = `${obs.length} / 255`;

    const rep = estado.repSelecionado;
    const gestor = estado.gestorAtual;
    const listaPreview = $('listaEtapasPreview');
    const badgeStatus = $('badgePreviewStatus');
    const btnExecutar = $('btnExecutar');

    // Validação mínima para simular
    if (!rep) {
        badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600';
        badgeStatus.textContent = 'Aguardando representante';
        listaPreview.innerHTML = '<p class="text-slate-400 italic">Selecione um representante para simular a operação.</p>';
        btnExecutar.disabled = true;
        return;
    }

    if (val <= 0.009) {
        badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-slate-200 text-slate-600';
        badgeStatus.textContent = 'Informe o valor';
        listaPreview.innerHTML = '<p class="text-slate-400 italic">Digite um valor maior que zero para visualizar as etapas.</p>';
        btnExecutar.disabled = true;
        return;
    }

    const obsValida = obs.length >= 3;

    // CENÁRIO 1: ADICIONAR SALDO (CRÉDITO)
    if (estado.tipo === 'CREDITO') {
        const saldoFinalRep = rep.saldo + val;
        badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
        badgeStatus.textContent = 'Crédito Direto';

        listaPreview.innerHTML = `
            <div class="p-3 bg-white rounded-xl border border-emerald-100 space-y-1.5">
                <div class="flex items-center justify-between font-bold text-slate-700">
                    <span><i class="fa-solid fa-circle-arrow-up text-emerald-600 mr-1.5"></i>Adição Direta de Saldo:</span>
                    <span class="text-emerald-700 font-extrabold">+ ${brl(val)}</span>
                </div>
                <div class="text-[0.75rem] text-slate-500">
                    O saldo de <b>${esc(rep.nome)}</b> irá de <b>${brl(rep.saldo)}</b> para <b class="text-emerald-700 font-extrabold">${brl(saldoFinalRep)}</b>.
                </div>
                <div class="text-[0.7rem] text-slate-400 bg-slate-50 p-2 rounded-lg border border-slate-100 mt-1">
                    <b>Observação no Mercos:</b> "${esc(obs) || '<i class="text-rose-500">Digite a observação obrigatória</i>'}"
                </div>
            </div>
        `;

        btnExecutar.disabled = !obsValida;
        return;
    }

    // CENÁRIO 2: REMOVER SALDO (DÉBITO)
    const saldoRep = Number(rep.saldo);
    const temSaldoSuficiente = (saldoRep >= val);

    // Subcenário 2.1: Representante tem saldo suficiente
    if (temSaldoSuficiente) {
        const saldoFinalRep = saldoRep - val;
        badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800';
        badgeStatus.textContent = 'Saldo Suficiente (Débito Direto)';

        listaPreview.innerHTML = `
            <div class="p-3 bg-white rounded-xl border border-emerald-100 space-y-1.5">
                <div class="flex items-center justify-between font-bold text-slate-700">
                    <span><i class="fa-solid fa-circle-arrow-down text-rose-600 mr-1.5"></i>Remoção Direta de Saldo:</span>
                    <span class="text-rose-700 font-extrabold">- ${brl(val)}</span>
                </div>
                <div class="text-[0.75rem] text-slate-500">
                    O representante possui saldo suficiente. O saldo irá de <b>${brl(saldoRep)}</b> para <b class="text-slate-800 font-extrabold">${brl(saldoFinalRep)}</b> sem envolver o Gestor.
                </div>
                <div class="text-[0.7rem] text-slate-400 bg-slate-50 p-2 rounded-lg border border-slate-100 mt-1">
                    <b>Observação no Mercos:</b> "${esc(obs) || '<i class="text-rose-500">Digite a observação obrigatória</i>'}"
                </div>
            </div>
        `;

        btnExecutar.disabled = !obsValida;
        return;
    }

    // Subcenário 2.2: Representante NÃO tem saldo suficiente E Checkbox NÃO MARCADO
    if (!estado.usarGestor) {
        const faltam = val - Math.max(0, saldoRep);
        badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800';
        badgeStatus.textContent = 'Saldo Insuficiente';

        listaPreview.innerHTML = `
            <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-rose-900 space-y-1">
                <div class="font-extrabold flex items-center gap-1.5">
                    <i class="fa-solid fa-triangle-exclamation text-rose-600"></i> Bloqueio: Saldo Insuficiente no Representante
                </div>
                <p class="text-[0.75rem] text-rose-700 leading-relaxed">
                    Saldo atual: <b>${brl(saldoRep)}</b>. O valor solicitado é <b>${brl(val)}</b> (faltam <b>${brl(faltam)}</b>).
                    <br>Marque a opção <b>"Tirar saldo do gestor"</b> para cobrir automaticamente a diferença via empréstimo.
                </p>
            </div>
        `;

        btnExecutar.disabled = true;
        return;
    }

    // Subcenário 2.3: Representante NÃO tem saldo suficiente E Checkbox MARCADO -> COBERTURA VIA GESTOR
    const diferenca = saldoRep > 0 ? (val - saldoRep) : val;
    const saldoGestor = gestor ? Number(gestor.saldo) : 0;
    const gestorTemSaldo = saldoGestor >= diferenca;

    if (!gestorTemSaldo) {
        badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-rose-100 text-rose-800';
        badgeStatus.textContent = 'Gestor sem Saldo Suficiente';

        listaPreview.innerHTML = `
            <div class="p-3 bg-rose-50 rounded-xl border border-rose-200 text-rose-900 space-y-1">
                <div class="font-extrabold flex items-center gap-1.5">
                    <i class="fa-solid fa-circle-xmark text-rose-600"></i> Cobertura Indisponível no Gestor
                </div>
                <p class="text-[0.75rem] text-rose-700 leading-relaxed">
                    O Gestor <b>${esc(gestor?.nome || 'da filial')}</b> possui saldo de apenas <b>${brl(saldoGestor)}</b>, insuficiente para cobrir o empréstimo necessário de <b>${brl(diferenca)}</b>.
                </p>
            </div>
        `;

        btnExecutar.disabled = true;
        return;
    }

    // Gestor tem saldo! Renderiza as 3 etapas exatamente como o usuário solicitou!
    const saldoFinalGestor = saldoGestor - diferenca;
    const saldoIntermediarioRep = saldoRep + diferenca;
    const saldoFinalRep = saldoIntermediarioRep - val;

    badgeStatus.className = 'text-[0.7rem] font-bold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800';
    badgeStatus.textContent = 'Cobertura de Gestor (3 Etapas)';

    const obsTexto = esc(obs) || '<i class="text-rose-500 font-normal">Observação obrigatória...</i>';

    listaPreview.innerHTML = `
        <div class="space-y-2">
            <!-- ETAPA 1 -->
            <div class="p-2.5 bg-white rounded-xl border border-purple-100 flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 font-extrabold text-[0.65rem] flex items-center justify-center flex-shrink-0 mt-0.5">1</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between font-bold text-slate-800">
                        <span>Débito no Gestor (${esc(gestor?.nome)}):</span>
                        <span class="text-rose-600 font-extrabold">- ${brl(diferenca)}</span>
                    </div>
                    <div class="text-[0.7rem] text-slate-500 mt-0.5">
                        Saldo Gestor: <b>${brl(saldoGestor)}</b> ➔ <b class="text-purple-700">${brl(saldoFinalGestor)}</b>
                    </div>
                    <div class="text-[0.68rem] text-slate-400 bg-slate-50 p-1.5 rounded mt-1 border border-slate-100">
                        <b>Obs Mercos:</b> "Emprestimo para o representante ${esc(rep.nome)} para movimentação : ${obsTexto}"
                    </div>
                </div>
            </div>

            <!-- ETAPA 2 -->
            <div class="p-2.5 bg-white rounded-xl border border-emerald-100 flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 font-extrabold text-[0.65rem] flex items-center justify-center flex-shrink-0 mt-0.5">2</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between font-bold text-slate-800">
                        <span>Empréstimo no Representante (${esc(rep.nome)}):</span>
                        <span class="text-emerald-600 font-extrabold">+ ${brl(diferenca)}</span>
                    </div>
                    <div class="text-[0.7rem] text-slate-500 mt-0.5">
                        Saldo Representante: <b>${brl(saldoRep)}</b> ➔ <b class="text-emerald-700">${brl(saldoIntermediarioRep)}</b>
                    </div>
                    <div class="text-[0.68rem] text-slate-400 bg-slate-50 p-1.5 rounded mt-1 border border-slate-100">
                        <b>Obs Mercos:</b> "Emprestimo para movimentação : ${obsTexto}"
                    </div>
                </div>
            </div>

            <!-- ETAPA 3 -->
            <div class="p-2.5 bg-white rounded-xl border border-slate-200 flex items-start gap-2.5">
                <span class="w-5 h-5 rounded-full bg-slate-200 text-slate-700 font-extrabold text-[0.65rem] flex items-center justify-center flex-shrink-0 mt-0.5">3</span>
                <div class="flex-1">
                    <div class="flex items-center justify-between font-bold text-slate-800">
                        <span>Retirada Solicitada do Representante:</span>
                        <span class="text-rose-600 font-extrabold">- ${brl(val)}</span>
                    </div>
                    <div class="text-[0.7rem] text-slate-500 mt-0.5">
                        Saldo Final do Representante: <b class="text-slate-900">${brl(saldoFinalRep)}</b>
                    </div>
                    <div class="text-[0.68rem] text-slate-400 bg-slate-50 p-1.5 rounded mt-1 border border-slate-100">
                        <b>Obs Mercos:</b> "${obsTexto}"
                    </div>
                </div>
            </div>
        </div>
    `;

    btnExecutar.disabled = !obsValida;
}

// ==========================================
// CONFIRMAÇÃO E EXECUÇÃO
// ==========================================
async function confirmarExecucao() {
    const rep = estado.repSelecionado;
    const gestor = estado.gestorAtual;
    const val = valorNumerico();
    const obs = ($('inpObservacao').value || '').trim();

    if (!rep) return aviso('warning', 'Atenção', 'Selecione um representante.');
    if (val <= 0.009) return aviso('warning', 'Atenção', 'Informe um valor válido maior que zero.');
    if (obs.length < 3) return aviso('warning', 'Observação Obrigatória', 'O campo de observação é OBRIGATÓRIO (mínimo de 3 caracteres).');

    const saldoRep = Number(rep.saldo);
    const tipo = estado.tipo;
    const usarGestor = estado.usarGestor;
    const precisaGestor = (tipo === 'DEBITO' && saldoRep < val && usarGestor);
    const diferenca = saldoRep > 0 ? (val - saldoRep) : val;

    let htmlConfirm = '';

    if (tipo === 'CREDITO') {
        htmlConfirm = `
            <div class="text-left text-xs space-y-3">
                <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-900">
                    <b class="text-sm block mb-1">🟢 Adicionar Saldo Flex</b>
                    Representante: <b>${esc(rep.nome)}</b> (Filial ${estado.filial})<br>
                    Valor a ser creditado: <b class="text-emerald-700 text-sm">${brl(val)}</b><br>
                    Saldo atual: <b>${brl(saldoRep)}</b> ➔ Novo saldo previsto: <b>${brl(saldoRep + val)}</b>
                </div>
                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-600">
                    <b>Observação no Mercos:</b><br>"${esc(obs)}"
                </div>
            </div>
        `;
    } else if (!precisaGestor) {
        htmlConfirm = `
            <div class="text-left text-xs space-y-3">
                <div class="p-3 bg-rose-50 border border-rose-200 rounded-2xl text-rose-900">
                    <b class="text-sm block mb-1">🔴 Remover Saldo Flex (Débito Direto)</b>
                    Representante: <b>${esc(rep.nome)}</b> (Filial ${estado.filial})<br>
                    Valor a ser debitado: <b class="text-rose-700 text-sm">- ${brl(val)}</b><br>
                    Saldo atual: <b>${brl(saldoRep)}</b> ➔ Novo saldo previsto: <b>${brl(saldoRep - val)}</b>
                </div>
                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-600">
                    <b>Observação no Mercos:</b><br>"${esc(obs)}"
                </div>
            </div>
        `;
    } else {
        htmlConfirm = `
            <div class="text-left text-xs space-y-3">
                <div class="p-3 bg-purple-50 border border-purple-200 rounded-2xl text-purple-950">
                    <b class="text-sm block mb-1">🟣 Remoção de Saldo com Cobertura do Gestor</b>
                    Representante: <b>${esc(rep.nome)}</b> · Saldo: <b>${brl(saldoRep)}</b><br>
                    Valor solicitado para retirada: <b class="text-rose-700">${brl(val)}</b><br>
                    Empréstimo do Gestor (<b>${esc(gestor?.nome)}</b>): <b class="text-purple-700">${brl(diferenca)}</b>
                </div>
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl space-y-1.5 text-slate-700">
                    <div class="font-extrabold text-slate-800">Etapas que serão executadas na API Mercos:</div>
                    <div>1. Gestor ➔ <span class="text-rose-600 font-bold">- ${brl(diferenca)}</span> ("Emprestimo para o representante...")</div>
                    <div>2. Representante ➔ <span class="text-emerald-600 font-bold">+ ${brl(diferenca)}</span> ("Emprestimo para movimentação...")</div>
                    <div>3. Representante ➔ <span class="text-rose-600 font-bold">- ${brl(val)}</span> ("${esc(obs)}")</div>
                    <div class="font-bold text-slate-900 pt-1 border-t border-slate-200">
                        Saldo Final Previsto: Representante <b>${brl(saldoRep + diferenca - val)}</b> | Gestor <b>${brl(Number(gestor?.saldo || 0) - diferenca)}</b>
                    </div>
                </div>
            </div>
        `;
    }

    const { isConfirmed } = await Swal.fire({
        ...swalBase,
        title: 'Confirmar Movimentação?',
        html: htmlConfirm,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Executar no Mercos',
        cancelButtonText: 'Cancelar'
    });

    if (!isConfirmed) return;

    // Execução
    const btn = $('btnExecutar');
    try {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Processando no Mercos...';

        const payload = {
            filial: estado.filial,
            colaborador_id: rep.id,
            tipo: tipo,
            valor: val,
            observacao: obs,
            tirar_saldo_gestor: estado.usarGestor
        };

        const res = await apiFetch('flex-movimentacao/movimentar', 'POST', payload);

        if (!res.success) throw new Error(res.error || 'Erro na movimentação');

        // Sucesso
        let msgSucesso = `Operação concluída com sucesso no Mercos! Novo saldo de ${esc(rep.nome)}: ${brl(res.saldo_novo || res.representante?.saldo_novo)}`;
        if (res.usou_gestor) {
            msgSucesso += `<br><small class="text-slate-500">Empréstimo de ${brl(res.gestor?.valor_retirado)} coberto pelo gestor ${esc(res.gestor?.nome)}.</small>`;
        }

        await Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Movimentação Concluída!',
            html: `<div class="text-left text-xs p-3 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-950">${msgSucesso}</div>`,
            confirmButtonText: 'OK'
        });

        // Limpa campos
        zerarValor();
        $('inpObservacao').value = '';

        // Recarrega saldos e histórico
        await carregarRepresentantes();
        await carregarHistorico();

        if (estado.repSelecionado) {
            selecionarRepresentante(estado.repSelecionado.id);
        }
    } catch (e) {
        aviso('error', 'Falha na Operação', e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fa-solid fa-bolt"></i> Executar Movimentação no Mercos';
        atualizarPrevisoes();
    }
}

// ==========================================
// RENDERIZAÇÃO DA TABELA DA EQUIPE
// ==========================================
function inicializarDatasHistorico() {
    const inpIni = $('filtroHistInicio');
    const inpFim = $('filtroHistFim');
    const pad = (n) => String(n).padStart(2, '0');
    const fmt = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    const hoje = new Date();
    const d60 = new Date();
    d60.setDate(hoje.getDate() - 60);
    if (inpIni && !inpIni.value) inpIni.value = fmt(d60);
    if (inpFim && !inpFim.value) inpFim.value = fmt(hoje);
}

function atualizarBadgeStatusCache(meta) {
    const el = $('badgeCacheHistorico');
    const badgeTop = $('badgeApi');
    if (meta && meta.stale) {
        if (el) {
            el.className = 'mf-chip bg-amber-100 text-amber-800 border border-amber-200';
            el.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> Cache Offline (${esc(meta.atualizado_em || '--')})`;
        }
        if (badgeTop) {
            badgeTop.className = 'mf-chip bg-amber-500/25 text-amber-200 border border-amber-400/30';
            badgeTop.innerHTML = '<span class="pulse-dot"></span> Mercos (Cache)';
        }
        return;
    }
    if (meta && meta.cache_hit) {
        if (el) {
            el.className = 'mf-chip bg-blue-50 text-blue-700 border border-blue-200';
            el.innerHTML = `<i class="fa-solid fa-bolt"></i> Cache Live (${meta.idade_segundos || 0}s)`;
        }
    } else if (meta) {
        if (el) {
            el.className = 'mf-chip bg-emerald-50 text-emerald-700 border border-emerald-200';
            el.innerHTML = `<i class="fa-solid fa-circle-check"></i> Mercos Live (${esc(meta.atualizado_em || 'agora')})`;
        }
    }
    if (badgeTop && (!meta || !meta.stale)) {
        badgeTop.className = 'mf-chip bg-emerald-500/25 text-emerald-200 border border-emerald-400/30';
        badgeTop.innerHTML = '<span class="pulse-dot"></span> Mercos Online';
    }
}

function popularSelectoresEquipeEHistorico() {
    const selGEq = $('filtroGestorEquipe');
    if (selGEq) {
        const valAtual = selGEq.value;
        selGEq.innerHTML = '<option value="">Todos os Gestores</option>';
        (estado.gestoresEquipe || []).forEach(g => {
            const opt = document.createElement('option');
            opt.value = String(g.cdgestor);
            opt.textContent = `${g.nome} (${g.qtd_representantes})`;
            selGEq.appendChild(opt);
        });
        if (valAtual && [...selGEq.options].some(o => o.value === valAtual)) {
            selGEq.value = valAtual;
        }
    }

    const selColabHist = $('filtroHistColab');
    if (selColabHist) {
        const valAtual = selColabHist.value;
        selColabHist.innerHTML = '<option value="">Todos os Colaboradores</option>';
        if (estado.gestorAtual) {
            const optG = document.createElement('option');
            optG.value = String(estado.gestorAtual.id);
            optG.textContent = `★ ${estado.gestorAtual.nome} (Gestor Cobertura)`;
            selColabHist.appendChild(optG);
        }
        (estado.reps || []).forEach(r => {
            const opt = document.createElement('option');
            opt.value = String(r.id);
            opt.textContent = `${r.nome} (Cód. ${r.id})`;
            selColabHist.appendChild(opt);
        });
        if (valAtual && [...selColabHist.options].some(o => o.value === valAtual)) {
            selColabHist.value = valAtual;
        }
    }
}

function renderizarTabelaEquipe(lista) {
    const tbody = $('tabelaEquipeCorpo');
    const resumoEl = $('resumoEquipeFiltrada');
    if (resumoEl) {
        const somaFiltrada = (lista || []).reduce((acc, r) => acc + (Number(r.saldo) || 0), 0);
        resumoEl.textContent = `${(lista || []).length} representante(s) exibido(s) · Carteira combinada: ${brl(somaFiltrada)}`;
    }

    if (!lista || lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400">Nenhum representante encontrado com os filtros aplicados.</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(r => {
        const saldo = Number(r.saldo);
        const badgeSaldo = saldo > 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : (saldo < 0 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-slate-100 text-slate-600 border-slate-200');

        return `
            <tr class="hover:bg-slate-50 transition">
                <td class="py-3 px-3 text-slate-400 font-mono text-[0.7rem]">${r.id}</td>
                <td class="py-3 px-3 font-bold text-slate-800">${esc(r.nome)}</td>
                <td class="py-3 px-3 text-slate-500 text-[0.75rem] truncate max-w-[200px]" title="${esc(r.razao)}">${esc(r.razao || r.fantasia || '--')}</td>
                <td class="py-3 px-3 text-slate-600 text-[0.75rem]">${esc(r.gestor || '--')}</td>
                <td class="py-3 px-3 text-right">
                    <span class="inline-block px-2.5 py-1 rounded-xl font-extrabold text-xs border ${badgeSaldo}">
                        ${brl(saldo)}
                    </span>
                </td>
                <td class="py-3 px-3 text-center">
                    <div class="inline-flex items-center gap-1.5">
                        <button type="button" onclick="iniciarMovimentacaoPara(${r.id})" class="px-2.5 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-extrabold text-xs transition border border-emerald-200">
                            <i class="fa-solid fa-sliders mr-1"></i> Movimentar
                        </button>
                        <button type="button" onclick="verHistoricoDoRep(${r.id})" class="px-2.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-extrabold text-xs transition border border-indigo-200" title="Auditar extrato deste representante">
                            <i class="fa-solid fa-magnifying-glass-dollar mr-1"></i> Extrato
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function filtrarEquipe() {
    const termo = ($('filtroEquipe')?.value || '').trim().toLowerCase();
    const gestorFiltro = Number($('filtroGestorEquipe')?.value || '0');
    const filtrados = estado.reps.filter(r => {
        if (gestorFiltro > 0 && Number(r.cdgestor) !== gestorFiltro) return false;
        if (!termo) return true;
        return String(r.id).includes(termo) ||
            (r.nome && r.nome.toLowerCase().includes(termo)) ||
            (r.razao && r.razao.toLowerCase().includes(termo)) ||
            (r.fantasia && r.fantasia.toLowerCase().includes(termo)) ||
            (r.gestor && r.gestor.toLowerCase().includes(termo));
    });
    renderizarTabelaEquipe(filtrados);
}

function verHistoricoDoRep(id) {
    if ($('filtroHistColab')) {
        $('filtroHistColab').value = String(id);
    }
    alternarAba('historico');
}

function limparFiltrosHistorico() {
    if ($('filtroHistGestor')) $('filtroHistGestor').value = '';
    if ($('filtroHistColab')) $('filtroHistColab').value = '';
    if ($('filtroHistTipo')) $('filtroHistTipo').value = 'TODOS';
    if ($('filtroHistOrigem')) $('filtroHistOrigem').value = 'TODAS';
    if ($('filtroHistBusca')) $('filtroHistBusca').value = '';
    if ($('chkHistApenasGestor')) $('chkHistApenasGestor').checked = false;
    if ($('filtroHistInicio')) $('filtroHistInicio').value = '';
    if ($('filtroHistFim')) $('filtroHistFim').value = '';
    inicializarDatasHistorico();
    carregarHistorico(false);
}

function iniciarMovimentacaoPara(id) {
    alternarAba('movimentar');
    selecionarRepresentante(id);
    $('inpValor').focus();
}

// ==========================================
// HISTÓRICO & AUDITORIA UNIFICADA
// ==========================================
function badgeOrigemRotulo(h) {
    const orig = String(h.origem || 'MANUAL_PORTAL').toUpperCase();
    if (orig === 'TRANSFERENCIA') {
        return '<span class="px-2 py-0.5 rounded-md text-[0.65rem] font-extrabold bg-blue-50 text-blue-700 border border-blue-200"><i class="fa-solid fa-right-left mr-1"></i>' + esc(h.origem_rotulo || 'Transferência') + '</span>';
    }
    if (orig === 'BONIFICACAO_CRON') {
        return '<span class="px-2 py-0.5 rounded-md text-[0.65rem] font-extrabold bg-amber-50 text-amber-800 border border-amber-200"><i class="fa-solid fa-file-invoice-dollar mr-1"></i>Bonif. NF</span>';
    }
    if (orig === 'BONIFICACAO_GESTOR') {
        return '<span class="px-2 py-0.5 rounded-md text-[0.65rem] font-extrabold bg-purple-50 text-purple-800 border border-purple-200"><i class="fa-solid fa-shield-halved mr-1"></i>Cobert. Bonif.</span>';
    }
    if (orig === 'FLEX_MINIMO') {
        return '<span class="px-2 py-0.5 rounded-md text-[0.65rem] font-extrabold bg-teal-50 text-teal-800 border border-teal-200"><i class="fa-solid fa-coins mr-1"></i>Flex Mínimo</span>';
    }
    return '<span class="px-2 py-0.5 rounded-md text-[0.65rem] font-extrabold bg-emerald-50 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-sliders mr-1"></i>Manual Portal</span>';
}

async function carregarHistorico(forcarSync = false) {
    const tbody = $('tabelaHistoricoCorpo');
    try {
        const params = {
            filial: estado.filial,
            limite: 200
        };
        const cdGestor = $('filtroHistGestor')?.value || '';
        const colabId = $('filtroHistColab')?.value || '';
        const tipo = $('filtroHistTipo')?.value || 'TODOS';
        const origem = $('filtroHistOrigem')?.value || 'TODAS';
        const inicio = $('filtroHistInicio')?.value || '';
        const fim = $('filtroHistFim')?.value || '';
        const busca = ($('filtroHistBusca')?.value || '').trim();
        const apenasGestor = $('chkHistApenasGestor')?.checked ? '1' : '0';

        if (cdGestor) params.cdgestor = cdGestor;
        if (colabId) params.colaborador_id = colabId;
        if (tipo && tipo !== 'TODOS') params.tipo = tipo;
        if (origem && origem !== 'TODAS') params.origem = origem;
        if (inicio) params.data_inicio = inicio;
        if (fim) params.data_fim = fim;
        if (busca) params.busca = busca;
        if (apenasGestor === '1') params.apenas_gestor = '1';
        if (forcarSync) params.forcar = '1';

        const res = await apiFetch('flex-movimentacao/historico', 'GET', params);
        if (!res.success) throw new Error(res.error || 'Erro ao carregar histórico');

        estado.historico = res.historico || [];
        estado.resumoAuditoria = res.resumo_auditoria || {};
        estado.gestoresHistorico = res.gestores_disponiveis || [];
        if (res.meta_cache) {
            estado.metaCache = res.meta_cache;
            atualizarBadgeStatusCache(res.meta_cache);
        }

        // Atualiza select de gestores da auditoria preservando filtro ativo
        const selGHist = $('filtroHistGestor');
        if (selGHist) {
            const valSel = selGHist.value;
            selGHist.innerHTML = '<option value="">Todos os Gestores</option>';
            estado.gestoresHistorico.forEach(g => {
                const opt = document.createElement('option');
                opt.value = String(g.id);
                opt.textContent = g.nome;
                selGHist.appendChild(opt);
            });
            if (valSel && [...selGHist.options].some(o => o.value === valSel)) {
                selGHist.value = valSel;
            }
        }

        // Atualiza 4 KPIs da aba Auditoria
        const rAud = estado.resumoAuditoria;
        if ($('audKpiCreditos')) $('audKpiCreditos').textContent = brl(rAud.total_creditos || 0);
        if ($('audQtdCreditos')) $('audQtdCreditos').textContent = `${rAud.qtd_creditos || 0} lançamento(s) (+)`;
        if ($('audKpiDebitos')) $('audKpiDebitos').textContent = brl(rAud.total_debitos || 0);
        if ($('audQtdDebitos')) $('audQtdDebitos').textContent = `${rAud.qtd_debitos || 0} lançamento(s) (-)`;
        if ($('audKpiSaldoLiquido')) {
            const liq = Number(rAud.saldo_liquido || 0);
            $('audKpiSaldoLiquido').textContent = brl(liq);
            $('audKpiSaldoLiquido').className = `text-lg sm:text-xl font-black ${liq >= 0 ? 'text-emerald-700' : 'text-rose-600'}`;
        }
        if ($('audQtdRegistros')) $('audQtdRegistros').textContent = `${rAud.total_registros || 0} evento(s) auditado(s)`;
        if ($('audKpiCoberturaGestor')) $('audKpiCoberturaGestor').textContent = brl(rAud.total_coberto_gestor || 0);
        if ($('audQtdCobertura')) $('audQtdCobertura').textContent = `${rAud.qtd_cobertura_gestor || 0} operação(ões) c/ gestor`;

        // Atualiza KPI de topo "Última Movimentação"
        if (estado.historico.length > 0) {
            const ult = estado.historico[0];
            $('kpiUltimaValor').textContent = brl(ult.valor_solicitado);
            $('kpiUltimaDesc').textContent = `${ult.tipo_movimentacao} · ${ult.nome_colaborador}`;
        } else {
            $('kpiUltimaValor').textContent = '--';
            $('kpiUltimaDesc').textContent = 'Nenhuma no filtro';
        }

        if (estado.historico.length === 0) {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center py-8 text-slate-400">Nenhuma movimentação encontrada para os filtros selecionados.</td></tr>';
            return;
        }

        tbody.innerHTML = estado.historico.map((h, idx) => {
            const ehCred = h.tipo_movimentacao === 'CREDITO';
            const badgeTipo = ehCred
                ? '<span class="px-2 py-0.5 rounded-full text-[0.65rem] font-extrabold bg-emerald-100 text-emerald-800">CRÉDITO (+)</span>'
                : '<span class="px-2 py-0.5 rounded-full text-[0.65rem] font-extrabold bg-rose-100 text-rose-800">DÉBITO (-)</span>';

            const gestorNomeExib = h.nome_gestor_carteira || h.nome_gestor || '--';
            const coberturaBadge = h.usou_gestor
                ? `<div class="mt-0.5"><span class="px-2 py-0.5 rounded-full text-[0.62rem] font-extrabold bg-purple-100 text-purple-800 border border-purple-200" title="Cobertura pelo gestor ${esc(h.nome_gestor)}">Coberto: ${brl(h.valor_gestor)}</span></div>`
                : '';

            const temProgressao = h.saldo_anterior_rep !== null && h.saldo_novo_rep !== null;
            const progressaoHtml = temProgressao
                ? `<span class="text-slate-400 text-[0.7rem]">${brl(h.saldo_anterior_rep)}</span> <i class="fa-solid fa-arrow-right text-[0.6rem] text-slate-300 mx-0.5"></i> <b class="text-slate-800">${brl(h.saldo_novo_rep)}</b>`
                : '<span class="text-slate-300 text-[0.7rem]">Via Cron/ERP</span>';

            const saldoLiveHtml = h.saldo_mercos_atual !== null && h.saldo_mercos_atual !== undefined
                ? `<span class="px-2 py-0.5 rounded-lg text-[0.72rem] font-extrabold border ${Number(h.saldo_mercos_atual) >= 0 ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'}">${brl(h.saldo_mercos_atual)}</span>`
                : '<span class="text-slate-300 text-xs">--</span>';

            const docBadge = h.documento
                ? `<span class="inline-block mr-1 px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 font-mono text-[0.65rem]">NF ${esc(h.documento)}</span>`
                : '';

            return `
                <tr class="hover:bg-slate-50 transition">
                    <td class="py-3 px-2.5 text-slate-500 font-mono text-[0.68rem] whitespace-nowrap">${esc(h.data_formatada || '--')}</td>
                    <td class="py-3 px-2.5">
                        ${badgeOrigemRotulo(h)}
                        <div class="text-[0.68rem] text-slate-500 font-bold mt-0.5">${esc(h.usuario || '--')}</div>
                    </td>
                    <td class="py-3 px-2.5">
                        <div class="font-bold text-slate-800">${esc(h.nome_colaborador)}</div>
                        <div class="text-[0.65rem] text-slate-400 font-mono">Cód. ${h.idcolaborador}</div>
                    </td>
                    <td class="py-3 px-2.5">
                        <div class="text-[0.72rem] font-bold text-slate-700 truncate max-w-[160px]" title="${esc(gestorNomeExib)}">${esc(gestorNomeExib)}</div>
                        ${coberturaBadge}
                    </td>
                    <td class="py-3 px-2.5 text-center">${badgeTipo}</td>
                    <td class="py-3 px-2.5 text-right font-black whitespace-nowrap ${ehCred ? 'text-emerald-700' : 'text-rose-600'}">${ehCred ? '+' : '-'} ${brl(h.valor_solicitado)}</td>
                    <td class="py-3 px-2.5 text-right whitespace-nowrap">${progressaoHtml}</td>
                    <td class="py-3 px-2.5 text-right whitespace-nowrap">${saldoLiveHtml}</td>
                    <td class="py-3 px-2.5 text-slate-600 text-[0.72rem] max-w-[230px]">
                        <div class="line-clamp-2" title="${esc(h.observacao)}">${docBadge}${esc(h.observacao)}</div>
                    </td>
                    <td class="py-3 px-2.5 text-center">
                        <button type="button" onclick="verDetalhesHistorico(${idx})" class="p-1.5 rounded-lg bg-slate-100 hover:bg-indigo-100 hover:text-indigo-700 text-slate-600 text-xs transition" title="Ver Auditoria Completa">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-6 text-rose-500">Erro ao carregar histórico: ${esc(e.message)}</td></tr>`;
    }
}

function verDetalhesHistorico(index) {
    const item = estado.historico[Number(index)];
    if (!item) return;

    let etapasHtml = '';
    try {
        const json = typeof item.detalhes_json === 'string' ? JSON.parse(item.detalhes_json) : item.detalhes_json;
        if (json?.etapas && json.etapas.length > 0) {
            etapasHtml = json.etapas.map((et, i) => `
                <div class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl mb-1.5 text-xs text-slate-700">
                    <div class="font-extrabold text-slate-900 mb-0.5">Etapa ${et.etapa || (i + 1)}: ${esc(et.acao)}</div>
                    <div>Valor: <b>${brl(et.valor)}</b> · Saldo: ${brl(et.saldo_anterior)} ➔ <b>${brl(et.saldo_novo)}</b></div>
                    ${et.observacao ? `<div class="text-[0.7rem] text-slate-400 mt-0.5">Obs: "${esc(et.observacao)}"</div>` : ''}
                </div>
            `).join('');
        } else if (json && typeof json === 'object') {
            etapasHtml = `<pre class="p-2.5 bg-slate-50 border border-slate-200 rounded-xl text-[0.7rem] font-mono text-slate-600 overflow-x-auto">${esc(JSON.stringify(json, null, 2))}</pre>`;
        }
    } catch (e) {}

    if (!etapasHtml) {
        etapasHtml = '<p class="text-slate-400 italic">Nenhum detalhe técnico adicional registrado para esta origem.</p>';
    }

    Swal.fire({
        ...swalBase,
        title: `Auditoria #${item.id} • ${item.origem_rotulo || item.origem || 'FLEX'}`,
        html: `
            <div class="text-left text-xs space-y-3">
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl grid grid-cols-2 gap-2 text-slate-700">
                    <div><b>Data:</b> ${esc(item.data_formatada)}</div>
                    <div><b>Origem / Operador:</b> ${esc(item.origem_rotulo || item.origem)} (${esc(item.usuario)})</div>
                    <div><b>Colaborador:</b> ${esc(item.nome_colaborador)} (#${item.idcolaborador})</div>
                    <div><b>Filial:</b> ${item.filial}</div>
                    <div><b>Natureza:</b> ${item.tipo_movimentacao}</div>
                    <div><b>Valor:</b> <b>${brl(item.valor_solicitado)}</b></div>
                    <div><b>Gestor Carteira:</b> ${esc(item.nome_gestor_carteira || item.nome_gestor || '--')}</div>
                    <div><b>Saldo Mercos Live:</b> <b class="text-emerald-700">${item.saldo_mercos_atual !== null ? brl(item.saldo_mercos_atual) : '--'}</b></div>
                    ${item.saldo_anterior_rep !== null ? `<div><b>Saldo Antes:</b> ${brl(item.saldo_anterior_rep)}</div>` : ''}
                    ${item.saldo_novo_rep !== null ? `<div><b>Saldo Depois:</b> ${brl(item.saldo_novo_rep)}</div>` : ''}
                    ${item.usou_gestor ? `
                        <div class="col-span-2 pt-1.5 mt-1 border-t border-slate-200 text-purple-900">
                            <b>Cobertura Gestor:</b> ${esc(item.nome_gestor)} · Valor: <b>${brl(item.valor_gestor)}</b>
                            ${item.saldo_anterior_gestor !== null ? `<br>Saldo Gestor: ${brl(item.saldo_anterior_gestor)} ➔ <b>${brl(item.saldo_novo_gestor)}</b>` : ''}
                        </div>
                    ` : ''}
                </div>
                <div>
                    <b class="text-slate-700 mb-1 block">Observação / Documento Registrado:</b>
                    <div class="p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800">${item.documento ? `<b>NF ${esc(item.documento)}</b> — ` : ''}"${esc(item.observacao)}"</div>
                </div>
                <div>
                    <b class="text-slate-700 mb-1 block">Rastreamento Técnico / Etapas:</b>
                    ${etapasHtml}
                </div>
            </div>
        `,
        confirmButtonText: 'Fechar'
    });
}

// ==========================================
// CONTROLE DE ABAS
// ==========================================
function alternarAba(aba) {
    estado.abaAtiva = aba;
    const abas = ['movimentar', 'equipe', 'historico'];
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

    if (aba === 'historico') {
        carregarHistorico();
    }
}

// ==========================================
// MODAL MANUAL & REGRAS
// ==========================================
function abrirModalManual() {
    const modal = $('modalManual');
    if (modal) modal.classList.remove('hidden');
}

function fecharModalManual() {
    const modal = $('modalManual');
    if (modal) modal.classList.add('hidden');
}

