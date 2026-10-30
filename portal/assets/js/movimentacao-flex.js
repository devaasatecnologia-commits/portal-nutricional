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
    gestorAtual: null,
    tipo: 'DEBITO',
    valor: 0,
    observacao: '',
    usarGestor: true,
    historico: [],
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

        await carregarContexto();
        $('selFilial').addEventListener('change', async (e) => {
            estado.filial = Number(e.target.value);
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

        const res = await apiFetch(`flex-movimentacao/representantes?filial=${estado.filial}`);
        if (!res.success) throw new Error(res.error || 'Erro ao carregar dados');

        estado.reps = res.representantes || [];
        estado.gestorAtual = res.gestor_filial || null;

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

        // Renderiza Tabela da Equipe
        renderizarTabelaEquipe(estado.reps);

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
function renderizarTabelaEquipe(lista) {
    const tbody = $('tabelaEquipeCorpo');
    if (!lista || lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400">Nenhum representante encontrado nesta filial.</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(r => {
        const saldo = Number(r.saldo);
        const corSaldo = saldo > 0 ? 'text-emerald-700' : (saldo < 0 ? 'text-rose-600' : 'text-slate-400');
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
                    <button type="button" onclick="iniciarMovimentacaoPara(${r.id})" class="px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-extrabold text-xs transition border border-emerald-200">
                        <i class="fa-solid fa-sliders mr-1"></i> Movimentar
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function filtrarEquipe() {
    const termo = ($('filtroEquipe').value || '').trim().toLowerCase();
    if (!termo) {
        renderizarTabelaEquipe(estado.reps);
        return;
    }
    const filtrados = estado.reps.filter(r => 
        String(r.id).includes(termo) ||
        (r.nome && r.nome.toLowerCase().includes(termo)) ||
        (r.razao && r.razao.toLowerCase().includes(termo)) ||
        (r.fantasia && r.fantasia.toLowerCase().includes(termo))
    );
    renderizarTabelaEquipe(filtrados);
}

function iniciarMovimentacaoPara(id) {
    alternarAba('movimentar');
    selecionarRepresentante(id);
    $('inpValor').focus();
}

// ==========================================
// HISTÓRICO & AUDITORIA
// ==========================================
async function carregarHistorico() {
    const tbody = $('tabelaHistoricoCorpo');
    try {
        const res = await apiFetch(`flex-movimentacao/historico?filial=${estado.filial}&limite=50`);
        if (!res.success) throw new Error(res.error || 'Erro ao carregar histórico');

        estado.historico = res.historico || [];

        if (estado.historico.length > 0) {
            const ult = estado.historico[0];
            $('kpiUltimaValor').textContent = brl(ult.valor_solicitado);
            $('kpiUltimaDesc').textContent = `${ult.tipo_movimentacao} · ${ult.nome_colaborador}`;
        } else {
            $('kpiUltimaValor').textContent = '--';
            $('kpiUltimaDesc').textContent = 'Nenhuma recente';
        }

        if (estado.historico.length === 0) {
            tbody.innerHTML = '<tr><td colspan="9" class="text-center py-8 text-slate-400">Nenhuma movimentação manual registrada para esta filial.</td></tr>';
            return;
        }

        tbody.innerHTML = estado.historico.map(h => {
            const ehCred = h.tipo_movimentacao === 'CREDITO';
            const badgeTipo = ehCred 
                ? '<span class="px-2 py-0.5 rounded-full text-[0.65rem] font-extrabold bg-emerald-100 text-emerald-800">CRÉDITO (+)</span>' 
                : '<span class="px-2 py-0.5 rounded-full text-[0.65rem] font-extrabold bg-rose-100 text-rose-800">DÉBITO (-)</span>';

            const badgeGestor = h.usou_gestor 
                ? `<span class="px-2 py-0.5 rounded-full text-[0.65rem] font-extrabold bg-purple-100 text-purple-800" title="Cobriu ${brl(h.valor_gestor)} via ${esc(h.nome_gestor)}">SIM (${brl(h.valor_gestor)})</span>` 
                : '<span class="text-slate-400 text-xs">Não</span>';

            return `
                <tr class="hover:bg-slate-50 transition">
                    <td class="py-3 px-3 text-slate-500 font-mono text-[0.7rem]">${h.data_formatada || '--'}</td>
                    <td class="py-3 px-3 font-bold text-slate-800">${esc(h.usuario)}</td>
                    <td class="py-3 px-3 text-slate-600">Filial ${h.filial}</td>
                    <td class="py-3 px-3 font-bold text-slate-800">${esc(h.nome_colaborador)}</td>
                    <td class="py-3 px-3">${badgeTipo}</td>
                    <td class="py-3 px-3 text-right font-black ${ehCred ? 'text-emerald-700' : 'text-rose-600'}">${brl(h.valor_solicitado)}</td>
                    <td class="py-3 px-3 text-center">${badgeGestor}</td>
                    <td class="py-3 px-3 text-slate-600 text-[0.75rem] max-w-[240px] truncate" title="${esc(h.observacao)}">${esc(h.observacao)}</td>
                    <td class="py-3 px-3 text-center">
                        <button type="button" onclick="verDetalhesHistorico(${h.id})" class="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs transition" title="Ver Detalhes">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-6 text-rose-500">Erro ao carregar histórico: ${esc(e.message)}</td></tr>`;
    }
}

function verDetalhesHistorico(id) {
    const item = estado.historico.find(h => h.id === id);
    if (!item) return;

    let etapasHtml = '<p class="text-slate-400 italic">Nenhum detalhe adicional de etapas registrado.</p>';
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
        }
    } catch (e) {}

    Swal.fire({
        ...swalBase,
        title: `Detalhes da Movimentação #${item.id}`,
        html: `
            <div class="text-left text-xs space-y-3">
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-2xl grid grid-cols-2 gap-2 text-slate-700">
                    <div><b>Data:</b> ${item.data_formatada}</div>
                    <div><b>Operador:</b> ${esc(item.usuario)}</div>
                    <div><b>Representante:</b> ${esc(item.nome_colaborador)}</div>
                    <div><b>Filial:</b> ${item.filial}</div>
                    <div><b>Tipo:</b> ${item.tipo_movimentacao}</div>
                    <div><b>Valor:</b> ${brl(item.valor_solicitado)}</div>
                    <div><b>Saldo Rep Antes:</b> ${brl(item.saldo_anterior_rep)}</div>
                    <div><b>Saldo Rep Depois:</b> ${brl(item.saldo_novo_rep)}</div>
                    ${item.usou_gestor ? `
                        <div class="col-span-2 pt-1 border-t border-slate-200 text-purple-900">
                            <b>Gestor:</b> ${esc(item.nome_gestor)} · Cobertura: <b>${brl(item.valor_gestor)}</b><br>
                            Saldo Gestor: ${brl(item.saldo_anterior_gestor)} ➔ <b>${brl(item.saldo_novo_gestor)}</b>
                        </div>
                    ` : ''}
                </div>
                <div>
                    <b class="text-slate-700 mb-1 block">Observação Registrada:</b>
                    <div class="p-2.5 bg-white border border-slate-200 rounded-xl text-slate-800">"${esc(item.observacao)}"</div>
                </div>
                <div>
                    <b class="text-slate-700 mb-1 block">Etapas Executadas no Mercos:</b>
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

