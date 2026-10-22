// Módulo Transferência de Flex — Nutricional API & Portal
// Integração em tempo real com a API Mercos e validação rigorosa de representantes ativos no ERP

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
const dataHora = (d) => {
    const m = String(d || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
    return m ? `${m[3]}/${m[2]}/${m[1]} ${m[4]}:${m[5]}` : esc(d);
};

const swalBase = { confirmButtonColor: '#7c3aed', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = {
    usuario: '',
    is_admin: false,
    filiais: [],
    filial: 1,
    reps: [],
    repDe: null,
    repPara: null,
    resumo: null,
    movs: [],
    enviando: false,
    abaAtiva: 'transferir',
};

// ==========================================
// FORMATAÇÃO E CÁLCULO DE VALOR
// ==========================================
function valorNumerico() {
    const digitos = ($('valor').value || '').replace(/\D/g, '');
    return digitos ? Number(digitos) / 100 : 0;
}

function formatarInputMoeda() {
    const val = valorNumerico();
    $('valor').value = val ? brl(val) : '';
    atualizarPrevisoes();
}

function adicionarValor(incremento) {
    const atual = valorNumerico();
    const novo = atual + incremento;
    $('valor').value = brl(novo);
    atualizarPrevisoes();
}

function usarSaldoTotal() {
    if (!estado.repDe || estado.repDe.saldo <= 0) {
        return aviso('info', 'Sem Saldo', 'O representante emissor selecionado não possui saldo disponível.');
    }
    $('valor').value = brl(estado.repDe.saldo);
    atualizarPrevisoes();
}

function zerarValor() {
    $('valor').value = '';
    atualizarPrevisoes();
}

// ==========================================
// CÁLCULO E PREVIEW TRANSPARENTE
// ==========================================
function atualizarPrevisoes() {
    const val = valorNumerico();
    const motivo = ($('motivo').value || '').trim();
    $('charCount').textContent = `${motivo.length} / 255`;

    // 1. Emissor DE
    if (estado.repDe) {
        const saldoPosDe = estado.repDe.saldo - val;
        $('saldoPosDe').textContent = brl(saldoPosDe);
        if (saldoPosDe < 0) {
            $('saldoPosDe').className = 'text-base font-black text-red-600';
            $('avisoSaldoInsuficiente').classList.remove('hidden');
        } else {
            $('saldoPosDe').className = 'text-base font-black text-slate-700';
            $('avisoSaldoInsuficiente').classList.add('hidden');
        }
    }

    // 2. Destinatário PARA
    if (estado.repPara) {
        const saldoPosPara = estado.repPara.saldo + val;
        $('saldoPosPara').textContent = brl(saldoPosPara);
        if (val > 0) {
            $('tagIncrementoPara').classList.remove('hidden');
            $('valorIncremento').textContent = `+ ${brl(val)}`;
        } else {
            $('tagIncrementoPara').classList.add('hidden');
        }
    }

    // 3. Preview Observação Mercos
    const previewBox = $('previewObsMercos');
    if (estado.repDe && estado.repPara && val > 0) {
        const motivoTxt = motivo || '(aguardando motivo...)';
        previewBox.textContent = `Transf: ${estado.repDe.nome} (#${estado.repDe.id}) -> ${estado.repPara.nome} (#${estado.repPara.id}) por ${estado.usuario} | Motivo: ${motivoTxt}`;
        previewBox.classList.remove('italic');
    } else {
        previewBox.textContent = 'Selecione os dois representantes, o valor e o motivo para visualizar o registro exato...';
        previewBox.classList.add('italic');
    }

    // 4. Validação do botão
    const valido = estado.repDe && estado.repPara && estado.repDe.id !== estado.repPara.id
        && val > 0 && val <= (estado.repDe.saldo || 0)
        && motivo.length >= 4;

    $('btnTransferir').disabled = !valido || estado.enviando;
}

// ==========================================
// CARREGAMENTO DE CONTEXTO E REPRESENTANTES
// ==========================================
async function carregarContexto(forcarRecarregar = false) {
    try {
        const filial = Number($('selFilial').value || estado.filial || 1);
        const data = await apiFetch('flex/contexto', 'GET', { filial });

        estado.usuario = data.usuario || 'GESTOR';
        estado.is_admin = !!data.is_admin;
        estado.filiais = data.filiais || [];
        estado.filial = Number(data.filial_atual || filial);
        estado.reps = data.representantes || [];
        estado.resumo = data.resumo || {};

        renderFiliais();
        renderKPIs();
        popularSelectsReps();
        renderTabelaEquipe();
        carregarMovimentacoes();
    } catch (e) {
        aviso('error', 'Erro ao carregar contexto', e.message);
    }
}

async function mudarFilial() {
    estado.filial = Number($('selFilial').value);
    resetarCampos();
    await carregarContexto();
}

function renderFiliais() {
    if (!estado.filiais.length) return;
    const sel = $('selFilial');
    sel.innerHTML = estado.filiais.map((f) => `
        <option value="${f.idfilial}" ${Number(f.idfilial) === estado.filial ? 'selected' : ''} class="text-slate-800 font-bold">
            ${esc(f.idfilial)} - ${esc(f.razao)}
        </option>
    `).join('');
}

function renderKPIs() {
    const res = estado.resumo || {};
    $('kpiTotalMes').textContent = brl(res.total_mes);
    $('kpiQtdMes').textContent = `${res.qtd_mes || 0} operações concluídas`;

    $('kpiSaldoEquipe').textContent = brl(res.saldo_equipe);
    $('kpiTotalReps').textContent = `${res.total_representantes || estado.reps.length} ativos (ERP + Mercos)`;
    $('badgeCountReps').textContent = String(estado.reps.length);

    if (res.saldo_gestor !== null && res.saldo_gestor !== undefined) {
        $('kpiSaldoGestor').textContent = brl(res.saldo_gestor);
        $('kpiNomeGestor').textContent = res.nome_gestor || 'Conta do Gestor';
    } else {
        $('kpiSaldoGestor').textContent = 'Perfil Gestão';
        $('kpiNomeGestor').textContent = `Usuário: ${esc(estado.usuario)}`;
    }

    if (res.ultima_transferencia) {
        const ult = res.ultima_transferencia;
        $('kpiUltimaValor').textContent = brl(ult.valor);
        $('kpiUltimaDesc').textContent = `${ult.nome_de.split(' ')[0]} ➔ ${ult.nome_para.split(' ')[0]}`;
        $('kpiUltimaDesc').title = `${ult.nome_de} -> ${ult.nome_para} por ${ult.usuario}`;
    } else {
        $('kpiUltimaValor').textContent = 'Nenhuma';
        $('kpiUltimaDesc').textContent = 'Sem histórico recente';
    }
}

// ==========================================
// POPULAR SELECTS DE E PARA
// ==========================================
function popularSelectsReps() {
    const selDe = $('selDe');
    const selPara = $('selPara');
    const fDe = $('fDe');
    const fPara = $('fPara');

    // Filtro DE: Apenas quem tem saldo > 0 pode transferir
    const repsComSaldo = estado.reps.filter((r) => r.saldo > 0);
    const repsZerados = estado.reps.filter((r) => r.saldo <= 0);

    let htmlDe = '<option value="">Selecione o emissor (quem envia)...</option>';
    if (repsComSaldo.length) {
        htmlDe += '<optgroup label="Com saldo disponível para transferência">';
        htmlDe += repsComSaldo.map((r) => `<option value="${r.id}">[${r.id}] ${esc(r.nome)} (Saldo: ${brl(r.saldo)})</option>`).join('');
        htmlDe += '</optgroup>';
    }
    if (repsZerados.length) {
        htmlDe += '<optgroup label="Representantes com saldo zerado (indisponível para envio)">';
        htmlDe += repsZerados.map((r) => `<option value="${r.id}" disabled>[${r.id}] ${esc(r.nome)} (Saldo: ${brl(r.saldo)})</option>`).join('');
        htmlDe += '</optgroup>';
    }
    selDe.innerHTML = htmlDe;

    // Filtro PARA: Todos os ativos da filial exceto o selecionado no DE
    atualizarSelectPara();

    // Filtros do Histórico
    const htmlFiltros = '<option value="">Todos</option>' + estado.reps.map((r) => `<option value="${r.id}">[${r.id}] ${esc(r.nome)}</option>`).join('');
    fDe.innerHTML = htmlFiltros;
    fPara.innerHTML = htmlFiltros;
}

function atualizarSelectPara() {
    const idDe = estado.repDe ? estado.repDe.id : 0;
    const selPara = $('selPara');

    if (!idDe) {
        selPara.innerHTML = '<option value="">Selecione o emissor (DE) primeiro...</option>';
        selPara.disabled = true;
        return;
    }

    selPara.disabled = false;
    const opcoes = estado.reps.filter((r) => r.id !== idDe);
    selPara.innerHTML = '<option value="">Selecione quem receberá o saldo...</option>' + opcoes.map((r) => `
        <option value="${r.id}">[${r.id}] ${esc(r.nome)} (Saldo atual: ${brl(r.saldo)})</option>
    `).join('');
}

// ==========================================
// SELEÇÃO INTERATIVA
// ==========================================
function aoSelecionarDe() {
    const id = Number($('selDe').value);
    estado.repDe = estado.reps.find((r) => r.id === id) || null;

    if (!estado.repDe) {
        $('cardDetalhesDe').classList.add('hidden');
        atualizarSelectPara();
        atualizarPrevisoes();
        return;
    }

    // Exibe card DE
    $('nomeDe').textContent = estado.repDe.nome;
    $('subDe').textContent = `ID ERP: ${estado.repDe.id} · Gestor: ${estado.repDe.gestor || 'N/A'}`;
    $('saldoAtualDe').textContent = brl(estado.repDe.saldo);
    $('avatarDe').textContent = estado.repDe.nome.slice(0, 2).toUpperCase();
    $('cardDetalhesDe').classList.remove('hidden');

    atualizarSelectPara();

    // Se o PARA atual for o mesmo do DE, reseta o PARA
    if (estado.repPara && estado.repPara.id === id) {
        $('selPara').value = '';
        aoSelecionarPara();
    }

    atualizarPrevisoes();
}

function aoSelecionarPara() {
    const id = Number($('selPara').value);
    estado.repPara = estado.reps.find((r) => r.id === id) || null;

    if (!estado.repPara) {
        $('cardDetalhesPara').classList.add('hidden');
        atualizarPrevisoes();
        return;
    }

    // Exibe card PARA
    $('nomePara').textContent = estado.repPara.nome;
    $('subPara').textContent = `ID ERP: ${estado.repPara.id} · Gestor: ${estado.repPara.gestor || 'N/A'}`;
    $('saldoAtualPara').textContent = brl(estado.repPara.saldo);
    $('avatarPara').textContent = estado.repPara.nome.slice(0, 2).toUpperCase();
    $('cardDetalhesPara').classList.remove('hidden');

    atualizarPrevisoes();
}

function resetarCampos() {
    estado.repDe = null;
    estado.repPara = null;
    $('selDe').value = '';
    $('selPara').value = '';
    $('valor').value = '';
    $('motivo').value = '';
    $('cardDetalhesDe').classList.add('hidden');
    $('cardDetalhesPara').classList.add('hidden');
    atualizarSelectPara();
    atualizarPrevisoes();
}

// ==========================================
// AÇÃO DE TRANSFERÊNCIA COM CONFIRMAÇÃO
// ==========================================
async function confirmarTransferencia() {
    const val = valorNumerico();
    const motivo = ($('motivo').value || '').trim();

    if (!estado.repDe || !estado.repPara) {
        return aviso('warning', 'Campos Incompletos', 'Selecione quem envia (DE) e quem recebe (PARA).');
    }
    if (val <= 0) {
        return aviso('warning', 'Valor Inválido', 'Informe um valor maior que zero.');
    }
    if (val > estado.repDe.saldo) {
        return aviso('error', 'Saldo Insuficiente', `${estado.repDe.nome} possui apenas ${brl(estado.repDe.saldo)}.`);
    }
    if (motivo.length < 4) {
        return aviso('warning', 'Motivo Obrigatório', 'Descreva o motivo da transferência com pelo menos 4 caracteres.');
    }

    const obsMercosExata = `Transf: ${estado.repDe.nome} (#${estado.repDe.id}) -> ${estado.repPara.nome} (#${estado.repPara.id}) por ${estado.usuario} | Motivo: ${motivo}`;

    const confirmacao = await Swal.fire({
        ...swalBase,
        icon: 'question',
        title: 'Confirmar Transferência?',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Sim, Transferir Agora',
        cancelButtonText: 'Cancelar',
        html: `
            <div class="text-left text-xs sm:text-sm space-y-3 p-1">
                <div class="p-3 rounded-2xl bg-violet-50 border border-violet-100">
                    <div class="text-[0.65rem] font-black uppercase text-violet-700 tracking-wider mb-1">Origem (DE)</div>
                    <div class="font-extrabold text-slate-800">${esc(estado.repDe.nome)} (#${estado.repDe.id})</div>
                    <div class="text-xs text-slate-600 font-semibold mt-0.5">Saldo: ${brl(estado.repDe.saldo)} ➔ <b class="text-violet-700">${brl(estado.repDe.saldo - val)}</b></div>
                </div>

                <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-100">
                    <div class="text-[0.65rem] font-black uppercase text-emerald-700 tracking-wider mb-1">Destino (PARA)</div>
                    <div class="font-extrabold text-slate-800">${esc(estado.repPara.nome)} (#${estado.repPara.id})</div>
                    <div class="text-xs text-slate-600 font-semibold mt-0.5">Saldo: ${brl(estado.repPara.saldo)} ➔ <b class="text-emerald-700">${brl(estado.repPara.saldo + val)}</b></div>
                </div>

                <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="flex justify-between items-center mb-1">
                        <span class="font-extrabold text-slate-700">Valor da Operação:</span>
                        <span class="text-base font-black text-violet-700">${brl(val)}</span>
                    </div>
                    <div class="text-xs text-slate-600"><b>Operador:</b> ${esc(estado.usuario)}</div>
                    <div class="text-xs text-slate-600 mt-0.5"><b>Motivo:</b> ${esc(motivo)}</div>
                </div>

                <div class="p-2.5 rounded-xl bg-purple-50 text-[0.72rem] text-purple-900 border border-purple-200/80">
                    <i class="fa-solid fa-cloud-arrow-up mr-1 text-purple-600"></i>
                    <b>Registro Mercos:</b> ${esc(obsMercosExata)}
                </div>
            </div>
        `,
    });

    if (!confirmacao.isConfirmed) return;

    estado.enviando = true;
    atualizarPrevisoes();
    Swal.fire({
        ...swalBase,
        title: 'Processando transferência...',
        text: 'Comunicando com a API Mercos e registrando logs...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
    });

    try {
        const payload = {
            filial: estado.filial,
            de: estado.repDe.id,
            para: estado.repPara.id,
            valor: val,
            motivo: motivo,
        };

        const res = await apiFetch('flex/transferir', 'POST', payload);

        await Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Transferência Concluída!',
            html: `
                <div class="text-left text-xs sm:text-sm space-y-2">
                    <div class="font-bold text-slate-800">${esc(res.message)}</div>
                    <div class="p-2.5 rounded-xl bg-slate-50 border text-xs text-slate-600 font-semibold">
                        <div><b>Novo Saldo ${esc(estado.repDe.nome)}:</b> ${brl(res.saldo_de)}</div>
                        <div><b>Novo Saldo ${esc(estado.repPara.nome)}:</b> ${brl(res.saldo_para)}</div>
                    </div>
                    ${res.aviso ? `<div class="text-xs text-amber-600 font-bold">${esc(res.aviso)}</div>` : ''}
                </div>
            `,
            confirmButtonText: 'Entendido',
        });

        resetarCampos();
        await carregarContexto();
    } catch (e) {
        aviso('error', 'Falha na Transferência', e.message);
        carregarMovimentacoes();
    } finally {
        estado.enviando = false;
        atualizarPrevisoes();
    }
}

// ==========================================
// ABA 2: SALDOS DA EQUIPE (TABELA INTERATIVA)
// ==========================================
function renderTabelaEquipe(termo = '') {
    const tbody = $('tabelaEquipe');
    const filtrados = estado.reps.filter((r) => {
        if (!termo) return true;
        const t = termo.toLowerCase();
        return r.nome.toLowerCase().includes(t) || String(r.id).includes(t) || (r.gestor && r.gestor.toLowerCase().includes(t));
    });

    if (!filtrados.length) {
        tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-slate-400">Nenhum representante encontrado com este termo.</td></tr>';
        return;
    }

    tbody.innerHTML = filtrados.map((r) => `
        <tr class="hover:bg-slate-50/80 transition">
            <td class="py-3 px-3">
                <div class="font-extrabold text-slate-800 flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-violet-100 text-violet-700 font-black text-[0.65rem] flex items-center justify-center">${r.id}</span>
                    <span>${esc(r.nome)}</span>
                </div>
                ${r.razao && r.razao !== r.nome ? `<div class="text-[0.7rem] text-slate-400 font-semibold truncate max-w-xs">${esc(r.razao)}</div>` : ''}
            </td>
            <td class="py-3 px-3">
                <span class="tf-chip bg-slate-100 text-slate-600">${esc(r.gestor || 'N/A')}</span>
            </td>
            <td class="py-3 px-3 text-right">
                <span class="font-black ${r.saldo > 0 ? 'text-violet-700' : 'text-slate-400'}">${brl(r.saldo)}</span>
            </td>
            <td class="py-3 px-3 text-center">
                <span class="tf-chip ${r.saldo > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500'}">
                    ${r.saldo > 0 ? 'Ativo com Saldo' : 'Saldo Zerado'}
                </span>
            </td>
            <td class="py-3 px-3 text-center">
                <div class="flex items-center justify-center gap-1.5">
                    <button class="py-1 px-2.5 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 font-bold text-xs transition" title="Enviar saldo para este representante" onclick="acaoRapidaEquipe(${r.id}, 'para')">
                        <i class="fa-solid fa-arrow-down mr-1"></i> Receber
                    </button>
                    <button class="py-1 px-2.5 rounded-lg ${r.saldo > 0 ? 'bg-violet-50 hover:bg-violet-100 text-violet-700' : 'bg-slate-100 text-slate-400 cursor-not-allowed'} font-bold text-xs transition" title="Tirar saldo deste representante" ${r.saldo > 0 ? '' : 'disabled'} onclick="acaoRapidaEquipe(${r.id}, 'de')">
                        <i class="fa-solid fa-arrow-up mr-1"></i> Ceder
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function filtrarEquipe() {
    renderTabelaEquipe($('buscaEquipe').value.trim());
}

function acaoRapidaEquipe(id, direcao) {
    alternarAba('transferir');
    if (direcao === 'de') {
        $('selDe').value = String(id);
        aoSelecionarDe();
    } else {
        $('selPara').value = String(id);
        aoSelecionarPara();
    }
}

// ==========================================
// ABA 3: HISTÓRICO DE MOVIMENTAÇÕES
// ==========================================
async function carregarMovimentacoes() {
    const box = $('listaMovimentacoes');
    box.innerHTML = '<div class="text-center py-8 text-slate-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Carregando movimentações...</div>';

    const p = {
        filial: estado.filial,
        de: $('fDe').value || '',
        para: $('fPara').value || '',
        inicio: $('dtIni').value || '',
        fim: $('dtFim').value || '',
        limite: 100,
    };

    try {
        const res = await apiFetch('flex/movimentacoes', 'GET', p);
        estado.movs = res.movimentacoes || [];
        renderMovimentacoes();
    } catch (e) {
        box.innerHTML = `<div class="text-center py-6 text-red-500 text-xs font-bold">${esc(e.message)}</div>`;
    }
}

function renderMovimentacoes() {
    const box = $('listaMovimentacoes');
    if (!estado.movs.length) {
        box.innerHTML = '<div class="text-center py-10 text-slate-400 text-sm">Nenhuma transferência encontrada para os filtros selecionados.</div>';
        return;
    }

    box.innerHTML = estado.movs.map((m) => {
        const ok = m.sucesso === true || m.sucesso === 1 || m.sucesso === 't';
        return `
            <div class="tf-mov-card ${ok ? '' : 'falha'}">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="tf-chip ${ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200'}">
                            <i class="fa-solid ${ok ? 'fa-check' : 'fa-xmark'}"></i> ${ok ? 'Sucesso' : 'Falha'}
                        </span>
                        <span class="text-xs text-slate-400 font-semibold">${dataHora(m.data_transferencia)}</span>
                        <span class="tf-chip bg-slate-100 text-slate-600 font-bold"><i class="fa-solid fa-user text-[0.65rem] mr-1"></i> ${esc(m.usuario)}</span>
                    </div>
                    <div class="text-base sm:text-lg font-black ${ok ? 'text-violet-700' : 'text-red-600'}">
                        ${brl(m.valor)}
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2.5 pt-2 border-t border-slate-100">
                    <div class="text-xs">
                        <span class="text-slate-400 font-bold">DE (Quem enviou):</span>
                        <div class="font-extrabold text-slate-800">${esc(m.nome_de)}</div>
                        ${ok ? `<div class="text-[0.7rem] text-slate-500 font-semibold">Saldos: ${brl(m.valor_saldo_anterior_de)} ➔ ${brl(m.valor_novo_saldo_de)}</div>` : ''}
                    </div>
                    <div class="text-xs">
                        <span class="text-slate-400 font-bold">PARA (Quem recebeu):</span>
                        <div class="font-extrabold text-slate-800">${esc(m.nome_para)}</div>
                        ${ok ? `<div class="text-[0.7rem] text-slate-500 font-semibold">Saldos: ${brl(m.valor_saldo_anterior_para)} ➔ ${brl(m.valor_novo_saldo_para)}</div>` : ''}
                    </div>
                </div>

                <div class="mt-2 text-xs text-slate-600 bg-slate-50 rounded-xl p-2 border border-slate-100">
                    <b>Motivo:</b> ${esc(m.motivo)}
                    ${!ok && m.mensagem ? `<div class="text-red-600 font-bold mt-1">Erro: ${esc(m.mensagem)}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function filtroDataRapido(modo) {
    const hoje = new Date();
    const fmt = (d) => d.toISOString().slice(0, 10);

    if (modo === 'hoje') {
        $('dtIni').value = fmt(hoje);
        $('dtFim').value = fmt(hoje);
    } else if (modo === '7d') {
        const d = new Date(hoje.getTime() - 7 * 86400000);
        $('dtIni').value = fmt(d);
        $('dtFim').value = fmt(hoje);
    } else if (modo === 'mes') {
        const d = new Date(hoje.getFullYear(), hoje.getMonth(), 1);
        $('dtIni').value = fmt(d);
        $('dtFim').value = fmt(hoje);
    }
    carregarMovimentacoes();
}

function limparFiltros() {
    $('fDe').value = '';
    $('fPara').value = '';
    $('dtIni').value = '';
    $('dtFim').value = '';
    carregarMovimentacoes();
}

// ==========================================
// EXPORTAÇÃO EXCEL (CSV UTF-8 COM BOM)
// ==========================================
async function baixarExcel() {
    let lista = [];
    try {
        const p = {
            filial: estado.filial,
            de: $('fDe').value || '',
            para: $('fPara').value || '',
            inicio: $('dtIni').value || '',
            fim: $('dtFim').value || '',
            limite: 2000,
        };
        const res = await apiFetch('flex/movimentacoes', 'GET', p);
        lista = res.movimentacoes || [];
    } catch (e) {
        return aviso('error', 'Erro ao exportar', e.message);
    }

    if (!lista.length) {
        return aviso('info', 'Sem Dados', 'Não há transferências para exportar com os filtros atuais.');
    }

    const cab = ['Data', 'Quem Enviou (DE)', 'Quem Recebeu (PARA)', 'Valor (R$)', 'Saldo Ant DE', 'Novo Saldo DE', 'Saldo Ant PARA', 'Novo Saldo PARA', 'Motivo', 'Operador', 'Situação'];
    const cel = (v) => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const num = (v) => Number(v ?? 0).toFixed(2).replace('.', ',');

    const linhas = lista.map((m) => [
        dataHora(m.data_transferencia),
        m.nome_de,
        m.nome_para,
        num(m.valor),
        num(m.valor_saldo_anterior_de),
        num(m.valor_novo_saldo_de),
        num(m.valor_saldo_anterior_para),
        num(m.valor_novo_saldo_para),
        m.motivo,
        m.usuario,
        (m.sucesso === true || m.sucesso === 1 || m.sucesso === 't') ? 'Sucesso' : 'Falhou',
    ].map(cel).join(';'));

    const blob = new Blob(['\ufeff' + [cab.map(cel).join(';'), ...linhas].join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `transferencias_flex_filial${estado.filial}_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(a.href);
}

// ==========================================
// CONTROLE DE ABAS
// ==========================================
function alternarAba(aba) {
    estado.abaAtiva = aba;
    ['transferir', 'equipe', 'historico'].forEach((a) => {
        const btn = $(`tabBtn${a.charAt(0).toUpperCase() + a.slice(1)}`);
        const painel = $(`painel${a.charAt(0).toUpperCase() + a.slice(1)}`);
        if (btn) btn.classList.toggle('on', a === aba);
        if (painel) painel.classList.toggle('hidden', a !== aba);
    });
}

// ==========================================
// INICIALIZAÇÃO
// ==========================================
window.addEventListener('DOMContentLoaded', () => {
    $('selFilial').addEventListener('change', mudarFilial);
    $('selDe').addEventListener('change', aoSelecionarDe);
    $('selPara').addEventListener('change', aoSelecionarPara);
    $('valor').addEventListener('input', formatarInputMoeda);
    $('motivo').addEventListener('input', atualizarPrevisoes);

    carregarContexto();
});
