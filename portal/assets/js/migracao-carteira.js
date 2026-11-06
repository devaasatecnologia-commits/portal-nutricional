/**
 * Módulo Migração de Carteira de Clientes (ERP & Mercos -> Portal Nutricional)
 * Transferência atômica de carteiras com reflexo em:
 *  - palmtop & palmtop_vendrepre
 *  - cliforemp, cliente, cliente_vendedor_mov
 *  - receber (títulos em aberto)
 *  - log_provider (sincronização Mercos)
 *  - Notificação por e-mail com CC configurável
 */

const apiFetch = async function (acao, metodo = 'GET', body = null) {
    const apiRoot = (window.API_URL || (window.location.origin + (window.location.pathname.includes('/API/') ? '/API/v1' : '/v1'))).replace(/\/$/, '');
    const cleanAcao = acao.replace(/^\/+/, '').replace(/^v1\//, '');
    const [acaoPath, acaoQuery] = cleanAcao.split('?');

    let url = apiRoot + '/' + acaoPath;
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

    const response = await fetch(url, options);
    const text = await response.text();
    let data = {};
    try { data = text ? JSON.parse(text) : {}; } catch { data = { error: text || 'Resposta inválida do servidor' }; }
    if (!response.ok) throw new Error(data.mensagem || data.error || `Erro ${response.status}`);
    return data;
};

const $ = (id) => document.getElementById(id);
const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const formatMoeda = (n) => Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const formatQtd = (n) => Number(n || 0).toLocaleString('pt-BR', { maximumFractionDigits: 0 });

const swalBase = { confirmButtonColor: '#0d9488', cancelButtonColor: '#64748b', customClass: { popup: 'rounded-3xl' } };
const aviso = (icon, title, text) => Swal.fire({ ...swalBase, icon, title, text, confirmButtonText: 'OK' });

const estado = {
    abaAtiva: 'migracao',
    representantes: [],
    origemId: 0,
    destinoId: 0,
    mercosStatus: null, // { sucesso, no_mercos, filial, mensagem }
    clientes: [],
    clientesFiltrados: [],
    selecionados: new Set(),
    historico: []
};

document.addEventListener('DOMContentLoaded', async () => {
    await carregarRepresentantes();
});

function trocarAba(aba) {
    estado.abaAtiva = aba;
    if (aba === 'migracao') {
        $('abaMigracao').classList.remove('hidden');
        $('abaHistorico').classList.add('hidden');
        $('tabBtnNovaMigracao').classList.add('active');
        $('tabBtnHistorico').classList.remove('active');
    } else {
        $('abaMigracao').classList.add('hidden');
        $('abaHistorico').classList.remove('hidden');
        $('tabBtnNovaMigracao').classList.remove('active');
        $('tabBtnHistorico').classList.add('active');
        carregarHistorico();
    }
}

/**
 * Carrega a lista de representantes comerciais ativos
 */
async function carregarRepresentantes() {
    try {
        const res = await apiFetch('migracao-carteira/representantes');
        if (!res.sucesso) throw new Error(res.mensagem || 'Falha ao carregar representantes');

        estado.representantes = res.representantes || [];
        popularSelectsRepresentantes();
    } catch (err) {
        aviso('error', 'Erro ao Carregar', err.message);
    }
}

function popularSelectsRepresentantes() {
    const selOrigem = $('selRepreOrigem');
    const selDestino = $('selRepreDestino');

    if (!selOrigem || !selDestino) return;

    let optsOrigem = '<option value="">-- Selecione o Representante de Origem --</option>';
    let optsDestino = '<option value="">-- Selecione o Novo Representante --</option>';

    estado.representantes.forEach(r => {
        const totalCli = parseInt(r.total_clientes || 0);
        const fantasia = esc(r.fantasia);
        const id = parseInt(r.idcliforemp);
        const cliInfo = totalCli > 0 ? `(${totalCli} cliente${totalCli > 1 ? 's' : ''})` : '(Sem clientes)';

        optsOrigem += `<option value="${id}">${fantasia} ${cliInfo} • Filial ${r.idfilial}</option>`;
        optsDestino += `<option value="${id}">${fantasia} • Filial ${r.idfilial}</option>`;
    });

    selOrigem.innerHTML = optsOrigem;
    selDestino.innerHTML = optsDestino;
}

/**
 * Evento ao alterar o representante de origem (DE)
 */
async function aoMudarRepreOrigem() {
    const selOrigem = $('selRepreOrigem');
    const id = parseInt(selOrigem.value || 0);
    estado.origemId = id;

    if (id <= 0) {
        $('detalhesRepreOrigem').classList.add('hidden');
        $('badgeQtdClientesOrigem').classList.add('hidden');
        $('secaoClientes').classList.add('hidden');
        estado.clientes = [];
        estado.clientesFiltrados = [];
        estado.selecionados.clear();
        return;
    }

    const repre = estado.representantes.find(r => parseInt(r.idcliforemp) === id);
    if (repre) {
        $('detalhesRepreOrigem').classList.remove('hidden');
        $('infoOrigemRazao').textContent = repre.razaosocial || repre.fantasia;
        $('infoOrigemEmail').textContent = repre.email || 'Não informado';
        $('infoOrigemFilial').textContent = `Filial ${repre.idfilial}`;
        $('infoOrigemTotalCli').textContent = `${repre.total_clientes || 0} cliente(s) ativo(s)`;

        const badgeCli = $('badgeQtdClientesOrigem');
        badgeCli.textContent = `${repre.total_clientes || 0} clientes`;
        badgeCli.classList.remove('hidden');
    }

    // Se origem for igual a destino, limpa destino
    if (estado.origemId === estado.destinoId && estado.destinoId > 0) {
        $('selRepreDestino').value = '';
        estado.destinoId = 0;
        $('detalhesRepreDestino').classList.add('hidden');
        $('badgeMercosStatus').classList.add('hidden');
        aviso('warning', 'Representantes Iguais', 'O representante de origem e destino não podem ser os mesmos.');
    }

    // Carrega clientes da carteira
    await carregarClientesCarteira(id);
}

/**
 * Evento ao alterar o representante de destino (PARA)
 */
async function aoMudarRepreDestino() {
    const selDestino = $('selRepreDestino');
    const id = parseInt(selDestino.value || 0);
    estado.destinoId = id;

    const badgeMercos = $('badgeMercosStatus');
    const detalhesDestino = $('detalhesRepreDestino');

    if (id <= 0) {
        detalhesDestino.classList.add('hidden');
        badgeMercos.classList.add('hidden');
        estado.mercosStatus = null;
        atualizarResumoAcao();
        return;
    }

    if (id === estado.origemId) {
        selDestino.value = '';
        estado.destinoId = 0;
        detalhesDestino.classList.add('hidden');
        badgeMercos.classList.add('hidden');
        aviso('warning', 'Atenção', 'Selecione um representante diferente da origem.');
        return;
    }

    const repre = estado.representantes.find(r => parseInt(r.idcliforemp) === id);
    if (repre) {
        detalhesDestino.classList.remove('hidden');
        $('infoDestinoRazao').textContent = repre.razaosocial || repre.fantasia;
        $('infoDestinoEmail').textContent = repre.email || 'Não informado';
        $('infoDestinoSupervisor').textContent = repre.supervisor_nome ? `${repre.supervisor_nome}` : 'Não vinculado';
    }

    // Feedback visual enquanto checa no Mercos
    badgeMercos.className = 'mc-badge bg-slate-100 text-slate-600 border border-slate-300';
    badgeMercos.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Verificando Mercos...';
    badgeMercos.classList.remove('hidden');
    $('infoDestinoMercosTxt').innerHTML = '<span class="text-slate-500 font-bold"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Consultando API Mercos...</span>';

    try {
        const res = await apiFetch(`migracao-carteira/mercos-status?idrepresentante=${id}`);
        estado.mercosStatus = res;

        if (res.no_mercos === true) {
            badgeMercos.className = 'mc-badge bg-emerald-100 text-emerald-800 border border-emerald-300';
            badgeMercos.innerHTML = `<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i>Ativo no Mercos (F${res.filial})`;
            $('infoDestinoMercosTxt').innerHTML = `<span class="text-emerald-700 font-extrabold"><i class="fa-solid fa-circle-check mr-1"></i>Usuário ativo no Mercos (Filial ${res.filial})</span>`;
        } else if (res.no_mercos === false) {
            badgeMercos.className = 'mc-badge bg-amber-100 text-amber-900 border border-amber-300';
            badgeMercos.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i>Não criado no Mercos`;
            $('infoDestinoMercosTxt').innerHTML = `<span class="text-amber-800 font-extrabold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Usuário NÃO criado no Mercos (Filial ${res.filial})</span>`;

            // Toast de aviso conforme regra legada do ERP
            Swal.fire({
                ...swalBase,
                icon: 'warning',
                title: 'Atenção: Mercos',
                text: 'Usuário não criado no MERCOS para a filial ' + res.filial + '. O vínculo no ERP será realizado normalmente, mas certifique-se de cadastrá-lo na plataforma de vendas.',
                confirmButtonText: 'Entendi'
            });
        } else {
            badgeMercos.className = 'mc-badge bg-slate-100 text-slate-700 border border-slate-300';
            badgeMercos.innerHTML = `<i class="fa-solid fa-circle-question text-slate-500 mr-1"></i>Mercos Indisponível`;
            $('infoDestinoMercosTxt').innerHTML = `<span class="text-slate-600 font-bold">${esc(res.aviso || 'Não foi possível validar no momento')}</span>`;
        }
    } catch (err) {
        badgeMercos.className = 'mc-badge bg-rose-100 text-rose-800 border border-rose-300';
        badgeMercos.innerHTML = `<i class="fa-solid fa-circle-exclamation mr-1"></i>Erro Mercos`;
        $('infoDestinoMercosTxt').innerHTML = `<span class="text-rose-700 font-bold">Falha ao consultar API Mercos</span>`;
    }

    atualizarResumoAcao();
}

/**
 * Carrega a carteira de clientes do representante de origem
 */
async function carregarClientesCarteira(idrepre) {
    const secao = $('secaoClientes');
    const tbody = $('tbodyClientes');

    secao.classList.remove('hidden');
    tbody.innerHTML = `
        <tr>
            <td colspan="7" class="p-8 text-center text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando carteira de clientes...
            </td>
        </tr>
    `;

    try {
        const res = await apiFetch(`migracao-carteira/clientes?idrepresentante=${idrepre}`);
        if (!res.sucesso) throw new Error(res.mensagem || 'Falha ao buscar clientes da carteira');

        estado.clientes = res.clientes || [];
        estado.clientesFiltrados = [...estado.clientes];
        estado.selecionados.clear();

        renderizarGradeClientes();
    } catch (err) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="p-6 text-center text-rose-600 font-bold">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>${esc(err.message)}
                </td>
            </tr>
        `;
    }
}

/**
 * Renderiza a grade de clientes aplicando filtro textual
 */
function renderizarGradeClientes() {
    const tbody = $('tbodyClientes');
    const lista = estado.clientesFiltrados;

    if (lista.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="p-8 text-center text-slate-400">
                    Nenhum cliente encontrado para este representante ou termo de busca.
                </td>
            </tr>
        `;
        atualizarContadoresSelecao();
        return;
    }

    let html = '';
    lista.forEach(cli => {
        const isSel = estado.selecionados.has(cli.idcliente);
        const rowClass = isSel ? 'mc-row-selected' : 'hover:bg-slate-50/80';
        const titulos = parseInt(cli.titulos_abertos || 0);
        const valorAberto = parseFloat(cli.valor_aberto || 0);

        let badgeTitulos = '<span class="text-slate-400 font-normal">Nenhum</span>';
        if (titulos > 0) {
            badgeTitulos = `
                <div class="inline-flex flex-col items-end">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-100 text-amber-900 font-extrabold text-[11px] border border-amber-300">
                        <i class="fa-solid fa-receipt text-[10px] text-amber-700"></i> ${titulos} título${titulos > 1 ? 's' : ''}
                    </span>
                    <span class="text-[10px] font-bold text-slate-500 mt-0.5">R$ ${formatMoeda(valorAberto)}</span>
                </div>
            `;
        }

        html += `
            <tr id="trCli_${cli.idcliente}" class="transition-colors ${rowClass}">
                <td class="p-3 text-center">
                    <input type="checkbox" class="custom-checkbox chk-cliente" value="${cli.idcliente}" ${isSel ? 'checked' : ''} onchange="alternarSelecaoCliente(${cli.idcliente}, this.checked)">
                </td>
                <td class="p-3 font-bold text-slate-800">${cli.idcliente}</td>
                <td class="p-3">
                    <div class="font-extrabold text-slate-900">${esc(cli.cliente)}</div>
                </td>
                <td class="p-3 text-slate-600 text-[11px] truncate max-w-[220px]" title="${esc(cli.razaosocial)}">
                    ${esc(cli.razaosocial || '-')}
                </td>
                <td class="p-3 text-slate-500 font-mono text-[11px]">${esc(cli.cnpjcpf || '-')}</td>
                <td class="p-3 text-slate-600">
                    ${esc([cli.cidade, cli.uf].filter(Boolean).join(' / ') || '-')}
                </td>
                <td class="p-3 text-right">
                    ${badgeTitulos}
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    atualizarContadoresSelecao();
}

/**
 * Filtra a grade de clientes pelo termo digitado
 */
function filtrarGradeClientes() {
    const termo = ($('filtroTextoClientes')?.value || '').toLowerCase().trim();
    if (!termo) {
        estado.clientesFiltrados = [...estado.clientes];
    } else {
        estado.clientesFiltrados = estado.clientes.filter(c => {
            const fantasia = (c.cliente || '').toLowerCase();
            const razao = (c.razaosocial || '').toLowerCase();
            const id = String(c.idcliente);
            const cnpj = (c.cnpjcpf || '').replace(/\D/g, '');
            const cidade = (c.cidade || '').toLowerCase();
            return fantasia.includes(termo) || razao.includes(termo) || id.includes(termo) || cnpj.includes(termo) || cidade.includes(termo);
        });
    }
    renderizarGradeClientes();
}

/**
 * Alterna a seleção de um cliente específico
 */
function alternarSelecaoCliente(idcliente, marcado) {
    if (marcado) {
        estado.selecionados.add(idcliente);
    } else {
        estado.selecionados.delete(idcliente);
    }

    const tr = $(`trCli_${idcliente}`);
    if (tr) {
        if (marcado) tr.classList.add('mc-row-selected');
        else tr.classList.remove('mc-row-selected');
    }

    atualizarContadoresSelecao();
}

/**
 * Seleciona ou desmarca todos os clientes atualmente visíveis na grade
 */
function selecionarTodosClientes(marcar = true) {
    estado.clientesFiltrados.forEach(c => {
        if (marcar) estado.selecionados.add(c.idcliente);
        else estado.selecionados.delete(c.idcliente);
    });

    const chkCab = $('chkTodosCabecalho');
    if (chkCab) chkCab.checked = marcar;

    renderizarGradeClientes();
}

function alternarTodosCabecalho(marcado) {
    selecionarTodosClientes(marcado);
}

/**
 * Atualiza badges e textos informativos de contagem de seleção
 */
function atualizarContadoresSelecao() {
    const qtdTotal = estado.clientes.length;
    const qtdSel = estado.selecionados.size;
    const chkCab = $('chkTodosCabecalho');

    if (chkCab) {
        chkCab.checked = qtdTotal > 0 && qtdSel === qtdTotal;
    }

    const lblContador = $('lblContadorSelecao');
    const alertaGeral = $('alertaSelecaoGeral');

    if (qtdSel === 0) {
        if (lblContador) lblContador.innerHTML = `<span class="text-amber-700 font-bold">Nenhum cliente marcado</span> • Total na carteira: <b>${qtdTotal}</b>`;
        if (alertaGeral) alertaGeral.classList.remove('hidden');
    } else {
        if (lblContador) lblContador.innerHTML = `<span class="text-teal-700 font-black">${qtdSel}</span> de <b>${qtdTotal}</b> cliente(s) selecionado(s)`;
        if (alertaGeral) alertaGeral.classList.add('hidden');
    }

    atualizarResumoAcao();
}

function atualizarResumoAcao() {
    const lblResumo = $('lblResumoTransferencia');
    if (!lblResumo) return;

    const qtdTotal = estado.clientes.length;
    const qtdSel = estado.selecionados.size;
    const qtdFinal = qtdSel > 0 ? qtdSel : qtdTotal;

    lblResumo.textContent = `${qtdFinal} cliente(s) a transferir`;
}

/**
 * Confirmação detalhada e execução da migração
 */
async function confirmarExecutarMigracao() {
    if (!estado.origemId || !estado.destinoId) {
        aviso('warning', 'Atenção', 'Selecione o Representante de Origem e o Representante de Destino.');
        return;
    }

    if (estado.origemId === estado.destinoId) {
        aviso('warning', 'Atenção', 'Os representantes de origem e destino são iguais.');
        return;
    }

    if (estado.clientes.length === 0) {
        aviso('warning', 'Carteira Vazia', 'O representante de origem não possui clientes ativos para transferência.');
        return;
    }

    const repreOrigem = estado.representantes.find(r => parseInt(r.idcliforemp) === estado.origemId);
    const repreDestino = estado.representantes.find(r => parseInt(r.idcliforemp) === estado.destinoId);

    const qtdSel = estado.selecionados.size;
    const ehTotalCarteira = qtdSel === 0 || qtdSel === estado.clientes.length;
    const qtdTransferir = ehTotalCarteira ? estado.clientes.length : qtdSel;

    const idsParaEnviar = ehTotalCarteira ? [] : Array.from(estado.selecionados);
    const motivo = ($('txtMotivoMigracao')?.value || '').trim();

    // Monta texto de confirmação com aviso do Mercos se aplicável
    let mercosAvisoHtml = '';
    if (estado.mercosStatus && estado.mercosStatus.no_mercos === false) {
        mercosAvisoHtml = `
            <div class="p-3 my-2 rounded-2xl bg-amber-50 border border-amber-300 text-amber-900 text-xs text-left">
                <i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i>
                <strong>Atenção:</strong> O representante de destino <u>não possui usuário ativo no Mercos</u> para a Filial ${estado.mercosStatus.filial}.
            </div>
        `;
    }

    const htmlConfirmacao = `
        <div class="text-left text-xs space-y-2 mt-2">
            <p class="text-sm font-semibold text-slate-700">Confirma a transferência definitiva de carteira?</p>
            
            <div class="p-3 rounded-2xl bg-slate-50 border border-slate-200 space-y-1.5 font-medium">
                <div><span class="text-slate-500 font-bold">Origem (DE):</span> <strong class="text-rose-700">${esc(repreOrigem?.fantasia)}</strong></div>
                <div><span class="text-slate-500 font-bold">Destino (PARA):</span> <strong class="text-teal-700">${esc(repreDestino?.fantasia)}</strong></div>
                <div><span class="text-slate-500 font-bold">Quantidade:</span> <strong class="text-slate-900">${qtdTransferir} cliente(s)</strong> ${ehTotalCarteira ? '(Toda a carteira)' : '(Seleção parcial)'}</div>
                ${motivo ? `<div><span class="text-slate-500 font-bold">Motivo:</span> <em>${esc(motivo)}</em></div>` : ''}
            </div>

            ${mercosAvisoHtml}

            <p class="text-[11px] text-slate-500 pt-1">
                <i class="fa-solid fa-bell text-teal-600 mr-1"></i>
                E-mails de notificação serão disparados para o novo representante, antigo, supervisor e cópias configuradas.
            </p>
        </div>
    `;

    const confirm = await Swal.fire({
        ...swalBase,
        title: 'Confirmar Migração?',
        html: htmlConfirmacao,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-bolt mr-1"></i> Sim, Transferir Carteira',
        cancelButtonText: 'Cancelar'
    });

    if (!confirm.isConfirmed) return;

    Swal.fire({
        ...swalBase,
        title: 'Processando Migração...',
        html: `
            <div class="p-4 text-center">
                <i class="fa-solid fa-spinner fa-spin text-teal-600 text-4xl mb-3"></i>
                <p class="text-xs text-slate-600 font-semibold">Atualizando ERP, carteira, títulos do contas a receber, gerando logs Mercos e disparando e-mails de notificação...</p>
            </div>
        `,
        allowOutsideClick: false,
        showConfirmButton: false
    });

    try {
        const payload = {
            idrepresentante_antigo: estado.origemId,
            idrepresentante_novo: estado.destinoId,
            clientes_ids: idsParaEnviar,
            motivo: motivo
        };

        const res = await apiFetch('migracao-carteira/executar', 'POST', payload);

        if (!res.sucesso) throw new Error(res.mensagem || 'Falha ao processar migração');

        await Swal.fire({
            ...swalBase,
            icon: 'success',
            title: 'Migração Concluída!',
            html: `
                <div class="text-center text-xs space-y-2">
                    <p class="text-sm font-bold text-slate-800">${esc(res.mensagem)}</p>
                    <div class="p-3 bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-900 text-left space-y-1">
                        <div><i class="fa-solid fa-check text-emerald-600 mr-1"></i> <strong>${res.total_clientes}</strong> clientes transferidos com sucesso.</div>
                        <div><i class="fa-solid fa-check text-emerald-600 mr-1"></i> <strong>${res.titulos_atualizados}</strong> títulos a receber atualizados.</div>
                        <div><i class="fa-solid fa-envelope text-emerald-600 mr-1"></i> Notificação por e-mail: <strong>${res.email_enviado ? 'Enviada com sucesso' : 'Registrada no histórico'}</strong>.</div>
                    </div>
                </div>
            `,
            confirmButtonText: 'Concluir'
        });

        // Limpa formulário e recarrega representantes e carteira
        $('txtMotivoMigracao').value = '';
        await carregarRepresentantes();
        $('selRepreOrigem').value = estado.origemId;
        await aoMudarRepreOrigem();

    } catch (err) {
        aviso('error', 'Falha na Operação', err.message);
    }
}

/**
 * Carrega a aba de histórico de migrações
 */
async function carregarHistorico() {
    const tbody = $('tbodyHistorico');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="p-8 text-center text-slate-400">
                <i class="fa-solid fa-spinner fa-spin mr-1"></i>Carregando histórico...
            </td>
        </tr>
    `;

    try {
        const res = await apiFetch('migracao-carteira/historico');
        if (!res.sucesso) throw new Error(res.mensagem || 'Falha ao buscar histórico');

        estado.historico = res.historico || [];
        renderizarHistorico();
    } catch (err) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="p-6 text-center text-rose-600 font-bold">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>${esc(err.message)}
                </td>
            </tr>
        `;
    }
}

function renderizarHistorico() {
    const tbody = $('tbodyHistorico');
    const lista = estado.historico;

    if (lista.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="p-8 text-center text-slate-400">
                    Nenhuma migração de carteira realizada até o momento.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    lista.forEach((h, index) => {
        const emailBadge = h.email_status === 'ENVIADO' 
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-100 text-emerald-800 border border-emerald-200"><i class="fa-solid fa-check"></i> Enviado</span>'
            : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-slate-100 text-slate-600 border border-slate-200">Pendente</span>';

        html += `
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="p-3 font-bold text-slate-800 whitespace-nowrap">${h.datahora_fmt || '-'}</td>
                <td class="p-3 font-semibold text-slate-700">${esc(h.usuario)}</td>
                <td class="p-3 text-rose-700 font-bold">${esc(h.nome_repre_antigo)}</td>
                <td class="p-3 text-teal-700 font-bold">${esc(h.nome_repre_novo)}</td>
                <td class="p-3 text-center font-black text-slate-900">${h.total_clientes}</td>
                <td class="p-3 text-center text-slate-600 font-semibold">${h.titulos_atualizados || 0}</td>
                <td class="p-3 text-center">${emailBadge}</td>
                <td class="p-3 text-right">
                    <button type="button" onclick="verClientesHistorico(${index})" class="px-2.5 py-1 rounded-xl bg-slate-100 hover:bg-teal-50 text-slate-700 hover:text-teal-700 font-bold text-[11px] transition cursor-pointer">
                        <i class="fa-solid fa-list-check mr-1 text-teal-600"></i>Ver Clientes
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function verClientesHistorico(index) {
    const item = estado.historico[index];
    if (!item) return;

    $('modalHistTitulo').textContent = `Transferência #${item.id} · ${item.datahora_fmt}`;
    $('modalHistSubtitulo').textContent = `De: ${item.nome_repre_antigo} ➔ Para: ${item.nome_repre_novo} (${item.total_clientes} clientes)`;

    const tbody = $('modalHistTbody');
    const clientes = item.clientes || [];

    if (clientes.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="p-4 text-center text-slate-400">Nenhum detalhe salvo.</td></tr>';
    } else {
        tbody.innerHTML = clientes.map(c => `
            <tr class="hover:bg-slate-50">
                <td class="p-2.5 font-bold text-slate-800">${c.idcliforemp}</td>
                <td class="p-2.5 font-extrabold text-slate-900">${esc(c.fantasia)}</td>
                <td class="p-2.5 text-slate-600 truncate max-w-[240px]">${esc(c.razaosocial || '-')}</td>
            </tr>
        `).join('');
    }

    $('modalClientesHistorico').classList.remove('hidden');
}

function fecharModalHistorico() {
    $('modalClientesHistorico').classList.add('hidden');
}
