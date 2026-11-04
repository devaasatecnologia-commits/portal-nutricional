// ==========================================================================
// MÓDULO DE CONSULTA SALDO DISPONÍVEL
// Nutricional Distribuidora • Análise de Estoque por Embarque
// ==========================================================================

const estadoConsulta = {
    embarqueSelecionado: null,
    dadosOriginais: [],
    dadosExibidos: [],
    apenasCriticos: true,
    termoBusca: '',
    itemModalAberto: null
};

// ======================================================================
// RELÓGIO & DATA DO CABEÇALHO
// ======================================================================
setInterval(() => {
    const agora = new Date();
    const horaFormatada = agora.toLocaleTimeString('pt-BR');
    const dataFormatada = agora.toLocaleDateString('pt-BR', { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' });
    
    const relogio = document.getElementById('relogio');
    const relogioMobile = document.getElementById('relogioMobile');
    const dataTopo = document.getElementById('data-topo');
    
    if (relogio) relogio.innerText = horaFormatada;
    if (relogioMobile) relogioMobile.innerText = horaFormatada;
    if (dataTopo) dataTopo.innerText = dataFormatada;
}, 1000);

// ======================================================================
// INICIALIZAÇÃO
// ======================================================================
window.addEventListener('DOMContentLoaded', async () => {
    initModernTooltipsSaldo();
    await carregarEmbarques();
});

// ======================================================================
// TOOLTIPS MODERNOS FLUTUANTES (RICH HOVER POPOVERS)
// ======================================================================
function initModernTooltipsSaldo() {
    const tooltipEl = document.getElementById('modernTooltipSaldo');
    const badgeEl = document.getElementById('tooltipSaldoBadge');
    const titleEl = document.getElementById('tooltipSaldoTitle');
    const descEl = document.getElementById('tooltipSaldoDesc');

    if (!tooltipEl || !titleEl || !descEl) return;

    let targetAtivo = null;

    document.addEventListener('mouseover', (e) => {
        const el = e.target.closest('[data-tooltip], [title]');
        if (!el) return;

        targetAtivo = el;
        const rawContent = el.getAttribute('data-tooltip') || el.getAttribute('title');
        if (!rawContent) return;

        // Limpa o title nativo para evitar tooltip duplo feio do navegador
        if (el.hasAttribute('title')) {
            el.setAttribute('data-tooltip-backup', el.getAttribute('title'));
            el.removeAttribute('title');
        }

        let titulo = 'Informação';
        let descricao = rawContent;
        let badge = 'AÇÃO';

        if (rawContent.includes('|')) {
            const partes = rawContent.split('|');
            titulo = partes[0].trim();
            descricao = partes.slice(1).join('|').trim();
        } else {
            titulo = el.innerText ? el.innerText.trim().slice(0, 30) : 'Detalhe';
        }

        if (descricao.toLowerCase().includes('seguran') || descricao.toLowerCase().includes('erp') || descricao.toLowerCase().includes('inativ')) {
            badge = 'ERP SEGURO';
            badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-emerald-400 bg-emerald-950/90 px-2 py-0.5 rounded-md border border-emerald-700/50';
        } else if (descricao.toLowerCase().includes('remover') || descricao.toLowerCase().includes('falta') || descricao.toLowerCase().includes('exclus')) {
            badge = 'IMPORTANTE';
            badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-rose-400 bg-rose-950/90 px-2 py-0.5 rounded-md border border-rose-700/50';
        } else if (descricao.toLowerCase().includes('editar') || descricao.toLowerCase().includes('ajust')) {
            badge = 'AJUSTE';
            badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-amber-400 bg-amber-950/90 px-2 py-0.5 rounded-md border border-amber-700/50';
        } else {
            badge = 'CONSULTA';
            badgeEl.className = 'text-[9px] font-black uppercase tracking-wider text-indigo-400 bg-indigo-950/90 px-2 py-0.5 rounded-md border border-indigo-700/50';
        }

        badgeEl.textContent = badge;
        titleEl.textContent = titulo;
        descEl.textContent = descricao;

        posicionarTooltip(e);
        tooltipEl.classList.remove('hidden');
    });

    document.addEventListener('mousemove', (e) => {
        if (!targetAtivo || tooltipEl.classList.contains('hidden')) return;
        posicionarTooltip(e);
    });

    document.addEventListener('mouseout', (e) => {
        if (targetAtivo && e.target.closest('[data-tooltip], [data-tooltip-backup]') === targetAtivo) {
            tooltipEl.classList.add('hidden');
            targetAtivo = null;
        }
    });

    function posicionarTooltip(e) {
        const offset = 14;
        let x = e.clientX + offset;
        let y = e.clientY + offset;

        const rect = tooltipEl.getBoundingClientRect();
        if (x + rect.width > window.innerWidth - 12) {
            x = e.clientX - rect.width - offset;
        }
        if (y + rect.height > window.innerHeight - 12) {
            y = e.clientY - rect.height - offset;
        }

        tooltipEl.style.left = `${Math.max(8, x)}px`;
        tooltipEl.style.top = `${Math.max(8, y)}px`;
    }
}

// ======================================================================
// HELPERS DE USUÁRIO & API
// ======================================================================
function getUserId() {
    const el = document.getElementById('user_id');
    if (el && el.value && parseInt(el.value) > 0) return parseInt(el.value);
    try {
        const u = JSON.parse(localStorage.getItem('usuario') || sessionStorage.getItem('usuario') || '{}');
        return parseInt(u.idusuario || u.idcliforemp || u.id || 0);
    } catch {
        return 0;
    }
}

function getUserNome() {
    const el = document.getElementById('user_nome');
    if (el && el.value && el.value.trim() !== '') return el.value.trim();
    try {
        const u = JSON.parse(localStorage.getItem('usuario') || sessionStorage.getItem('usuario') || '{}');
        return u.nome || u.username || 'Operador';
    } catch {
        return 'Operador';
    }
}

function getApiBaseUrl() {
    if (window.API_URL) return window.API_URL.replace(/\/$/, '');
    const origin = window.location.origin;
    const isLocalApi = window.location.pathname.includes('/API/');
    return isLocalApi ? `${origin}/API/v1` : `${origin}/v1`;
}

// ======================================================================
// CARREGAR EMBARQUES ATIVOS
// ======================================================================
async function carregarEmbarques() {
    const select = document.getElementById('selectEmbarque');
    if (!select) return;

    try {
        select.innerHTML = '<option value="">Buscando embarques ativos...</option>';
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${getApiBaseUrl()}/embarques-ativos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        const lista = Array.isArray(data) ? data : [];

        if (lista.length > 0) {
            select.innerHTML = '<option value="">Selecione um embarque ativo...</option>' +
                lista.map(e => {
                    const rota = e.rota || e.observacao || 'Itinerário Interno';
                    const entregador = e.entregador || 'S/ Entregador';
                    const placa = e.placa ? `Placa ${e.placa}` : 'S/P';
                    const qt = e.qt_pedido ? `${e.qt_pedido} peds` : '';
                    return `<option value="${e.idembarque}">
                        #${e.idembarque} • ${rota} • ${entregador} • ${placa} ${qt ? '• (' + qt + ')' : ''}
                    </option>`;
                }).join('');

            // Se já havia um embarque selecionado antes, preserva
            if (estadoConsulta.embarqueSelecionado) {
                select.value = estadoConsulta.embarqueSelecionado;
            }
        } else {
            select.innerHTML = '<option value="">Nenhum embarque ativo pendente de conferência</option>';
        }
    } catch (e) {
        console.error('Erro ao buscar embarques:', e);
        select.innerHTML = '<option value="">Erro ao carregar embarques</option>';
    }
}

// ======================================================================
// ALTERNAR MODO DE FILTRO (APENAS CRÍTICOS / TODOS)
// ======================================================================
function alternarFiltroModo(apenasCriticos) {
    estadoConsulta.apenasCriticos = apenasCriticos;

    const btnCriticos = document.getElementById('btnFiltroCriticos');
    const btnTodos = document.getElementById('btnFiltroTodos');

    if (apenasCriticos) {
        btnCriticos.className = 'flex-1 py-2 px-2 rounded-xl bg-white text-[#375a4b] shadow-xs transition text-center font-black';
        btnTodos.className = 'flex-1 py-2 px-2 rounded-xl text-slate-600 hover:text-slate-900 transition text-center font-bold';
    } else {
        btnTodos.className = 'flex-1 py-2 px-2 rounded-xl bg-white text-[#375a4b] shadow-xs transition text-center font-black';
        btnCriticos.className = 'flex-1 py-2 px-2 rounded-xl text-slate-600 hover:text-slate-900 transition text-center font-bold';
    }

    if (estadoConsulta.embarqueSelecionado) {
        carregarSaldos();
    }
}

// ======================================================================
// CARREGAR SALDOS DO EMBARQUE SELECIONADO
// ======================================================================
async function carregarSaldos() {
    const select = document.getElementById('selectEmbarque');
    const idembarque = select ? select.value : '';

    if (!idembarque) {
        estadoConsulta.embarqueSelecionado = null;
        document.getElementById('cardEmbarque').style.display = 'none';
        document.getElementById('cardsResumo').style.display = 'none';
        document.getElementById('tabelaContainer').style.display = 'none';
        return;
    }

    estadoConsulta.embarqueSelecionado = idembarque;
    atualizarCardEmbarque(idembarque);

    try {
        showLoading('Consultando saldos e reservas no ERP...');
        const token = localStorage.getItem('authToken');
        const urlParams = estadoConsulta.apenasCriticos ? '' : '?todos=1';
        
        const resp = await fetch(`${getApiBaseUrl()}/consulta/saldos/${idembarque}${urlParams}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        Swal.close();

        if (data && data.error) {
            throw new Error(data.error);
        }

        estadoConsulta.dadosOriginais = Array.isArray(data) ? data : [];
        filtrarTabelaLocal();

    } catch (e) {
        Swal.close();
        showError('Erro ao Consultar Saldos', e.message || 'Falha ao consultar estoque no banco do ERP.');
    }
}

// ======================================================================
// FILTRAGEM LOCAL EM TEMPO REAL (BUSCA POR REF OU PRODUTO)
// ======================================================================
function filtrarTabelaLocal() {
    const termo = (document.getElementById('filtroBuscaItem')?.value || '').toLowerCase().trim();
    estadoConsulta.termoBusca = termo;

    const btnLimpar = document.getElementById('btnLimparBuscaItem');
    if (btnLimpar) {
        if (termo) btnLimpar.classList.remove('hidden');
        else btnLimpar.classList.add('hidden');
    }

    const filtrados = estadoConsulta.dadosOriginais.filter(item => {
        if (!termo) return true;
        const ref = (item.referencia || '').toLowerCase();
        const desc = (item.descricao || '').toLowerCase();
        const id = String(item.iditem || '');
        return ref.includes(termo) || desc.includes(termo) || id.includes(termo);
    });

    estadoConsulta.dadosExibidos = filtrados;
    renderizarTabela(filtrados);
    atualizarCards(estadoConsulta.dadosOriginais);

    document.getElementById('cardsResumo').style.display = 'grid';
    document.getElementById('tabelaContainer').style.display = 'block';

    const contador = document.getElementById('badgeContadorTabela');
    if (contador) {
        contador.textContent = `${filtrados.length} de ${estadoConsulta.dadosOriginais.length} itens`;
    }
}

function limparBuscaItem() {
    const input = document.getElementById('filtroBuscaItem');
    if (input) {
        input.value = '';
        filtrarTabelaLocal();
        input.focus();
    }
}

// ======================================================================
// RENDERIZAR TABELA DE SALDOS
// ======================================================================
function renderizarTabela(dados) {
    const tbody = document.getElementById('tbodySaldos');
    if (!tbody) return;

    if (!dados.length) {
        const mensagem = estadoConsulta.apenasCriticos
            ? 'Nenhum item com falta de estoque neste embarque. Todos os produtos possuem estoque disponível!'
            : 'Nenhum item encontrado com os critérios de busca.';
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="text-center py-10 text-slate-400">
                    <i class="fa-solid fa-circle-check text-3xl mb-2 text-emerald-500 block"></i>
                    <span class="font-bold text-slate-700">${mensagem}</span>
                </td>
            </tr>`;
        return;
    }

    tbody.innerHTML = dados.map(item => {
        const estoqueFisico = parseFloat(item.estoque_fisico !== undefined ? item.estoque_fisico : (item.estoque || 0));
        const reserva = parseFloat(item.qtd_reservada || 0);
        const disponivel = parseFloat(item.estoque_disponivel !== undefined ? item.estoque_disponivel : (estoqueFisico - reserva));
        const qtdEmbarque = parseFloat(item.qtd_embarque || 0);
        const saldoFinal = parseFloat(item.saldo_final !== undefined ? item.saldo_final : (disponivel - qtdEmbarque));

        let statusClass, statusTexto, statusBadgeTooltip;
        if (saldoFinal >= 0) {
            statusClass = 'bg-emerald-100 text-emerald-800 border border-emerald-200';
            statusTexto = 'OK • DISPONÍVEL';
            statusBadgeTooltip = 'Estoque Suficiente|Atende 100% da demanda solicitada por este embarque';
        } else if (estoqueFisico <= 0.0001) {
            statusClass = 'bg-rose-100 text-rose-800 border border-rose-200';
            statusTexto = 'SEM ESTOQUE';
            statusBadgeTooltip = 'Estoque Zerado|Não há saldo físico no almoxarifado para atender o pedido';
        } else {
            statusClass = 'bg-amber-100 text-amber-800 border border-amber-200';
            statusTexto = `FALTA ${formatarNumero(Math.abs(saldoFinal))}`;
            statusBadgeTooltip = `Estoque Insuficiente|Há ${formatarNumero(disponivel)} un disponíveis, mas o embarque solicita ${formatarNumero(qtdEmbarque)} un`;
        }

        const saldoColor = saldoFinal >= 0 ? 'text-emerald-700 font-black' : 'text-rose-600 font-black';

        return `
            <tr class="border-b border-slate-100 hover:bg-slate-50/80 transition-all ${saldoFinal < 0 ? 'bg-rose-50/20' : ''}">
                <td data-label="REF" class="px-4 py-3 font-mono text-xs font-black text-[#2c493c]">
                    ${esc(item.referencia || '---')}
                </td>
                <td data-label="Produto" class="px-4 py-3 font-bold text-slate-800">
                    <div class="line-clamp-2" title="${esc(item.descricao)}">${esc(item.descricao)}</div>
                    <span class="text-[10px] text-slate-400 font-mono">ID Item: ${item.iditem}</span>
                </td>
                <td data-label="Estoque Físico" class="px-4 py-3 text-center font-bold text-slate-700" data-tooltip="Estoque Físico|Total de unidades físicas nas filiais: ${formatarNumero(estoqueFisico)} un">
                    ${formatarNumero(estoqueFisico)}
                </td>
                <td data-label="Reserva" class="px-4 py-3 text-center font-semibold text-slate-500" data-tooltip="Reserva Especial|Unidades reservadas para clientes prioritários: ${formatarNumero(reserva)} un">
                    ${reserva > 0 ? `<span class="px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 font-bold border border-amber-200">${formatarNumero(reserva)}</span>` : '<span class="text-slate-300">0</span>'}
                </td>
                <td data-label="Disponível" class="px-4 py-3 text-center font-bold text-slate-800" data-tooltip="Disponível para Faturamento|Estoque físico livre: ${formatarNumero(disponivel)} un">
                    ${formatarNumero(disponivel)}
                </td>
                <td data-label="Qtd Embarque" class="px-4 py-3 text-center font-black text-[#375a4b]" data-tooltip="Demanda do Embarque|Total pedido por este embarque: ${formatarNumero(qtdEmbarque)} un">
                    ${formatarNumero(qtdEmbarque)}
                </td>
                <td data-label="Saldo Projetado" class="px-4 py-3 text-center text-sm ${saldoColor}" data-tooltip="Saldo Projetado|Saldo final restante após atendimento deste embarque">
                    <span class="px-2 py-1 rounded-lg ${saldoFinal < 0 ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700'}">${formatarNumero(saldoFinal)}</span>
                </td>
                <td data-label="Status" class="px-4 py-3 text-center">
                    <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider inline-block ${statusClass}" data-tooltip="${statusBadgeTooltip}">
                        ${statusTexto}
                    </span>
                </td>
                <td data-label="Ações" class="px-4 py-3 text-center">
                    <button type="button" onclick="verPedidosItem('${item.iditem}', '${esc(item.descricao)}', '${esc(item.referencia)}')" 
                            class="px-3.5 py-2 bg-[#375a4b] hover:bg-[#2c493c] text-white rounded-xl text-xs font-black shadow-xs transition-all flex items-center gap-1.5 mx-auto cursor-pointer"
                            data-tooltip="Ver Pedidos do Item|Visualizar clientes e pedidos que contêm este produto para editar quantidades ou remover o item com histórico no ERP">
                        <i class="fa-solid fa-list-check"></i>
                        <span>Pedidos</span>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

// ======================================================================
// ATUALIZAR CARDS DE RESUMO
// ======================================================================
function atualizarCards(dados) {
    let ok = 0, alerta = 0, critico = 0;

    dados.forEach(item => {
        const estoqueFisico = parseFloat(item.estoque_fisico !== undefined ? item.estoque_fisico : (item.estoque || 0));
        const saldoFinal = parseFloat(item.saldo_final !== undefined ? item.saldo_final : (item.saldo || 0));

        if (saldoFinal >= 0) ok++;
        else if (estoqueFisico <= 0.0001) critico++;
        else alerta++;
    });

    document.getElementById('totalItens').textContent = dados.length;
    document.getElementById('itensOk').textContent = ok;
    document.getElementById('itensAlerta').textContent = alerta;
    document.getElementById('itensCritico').textContent = critico;
}

// ======================================================================
// ATUALIZAR CARD DO EMBARQUE
// ======================================================================
async function atualizarCardEmbarque(idembarque) {
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${getApiBaseUrl()}/embarques-ativos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const embarques = await resp.json();
        const lista = Array.isArray(embarques) ? embarques : [];
        const embarque = lista.find(e => String(e.idembarque) === String(idembarque));

        const card = document.getElementById('cardEmbarque');
        if (embarque && card) {
            document.getElementById('infoEmbarque').textContent = '#' + embarque.idembarque;
            document.getElementById('infoRota').textContent = embarque.rota || embarque.observacao || '---';
            document.getElementById('infoEntregador').textContent = embarque.entregador || '---';
            document.getElementById('infoPlaca').textContent = embarque.placa || 'S/P';
            document.getElementById('infoTransportadora').textContent = embarque.fantasia_transportadora || embarque.razao_transportadora || '---';
            document.getElementById('infoPesoBruto').textContent = (parseFloat(embarque.totalpesobruto) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' kg';
            document.getElementById('infoValorTotal').textContent = 'R$ ' + (parseFloat(embarque.valortotal) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            document.getElementById('infoQtPedidos').textContent = (embarque.qt_pedido || '0') + ' pedidos';
            document.getElementById('infoData').textContent = embarque.data ? new Date(embarque.data + 'T00:00:00').toLocaleDateString('pt-BR') : '---';

            card.style.display = 'block';
        }
    } catch (e) {
        console.error('Erro ao atualizar card do embarque:', e);
    }
}

// ======================================================================
// MODAL DE PEDIDOS DO ITEM
// ======================================================================
async function verPedidosItem(iditem, descricao, referencia) {
    if (!estadoConsulta.embarqueSelecionado) {
        showToast('Selecione um embarque primeiro', 'warning');
        return;
    }

    estadoConsulta.itemModalAberto = { iditem, descricao, referencia };

    try {
        showLoading('Buscando pedidos do item no ERP...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${getApiBaseUrl()}/consulta/pedidos-item/${estadoConsulta.embarqueSelecionado}/${iditem}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        Swal.close();

        if (data && data.error) throw new Error(data.error);

        const pedidos = Array.isArray(data) ? data : [];
        if (!pedidos.length) {
            showToast('Nenhum pedido ativo encontrado para este item', 'info');
            return;
        }

        const totalUnidades = pedidos.reduce((acc, p) => acc + parseFloat(p.qt || 0), 0);

        document.getElementById('modalPedidoTitulo').innerHTML = `
            <span>${referencia ? esc(referencia) + ' • ' : ''}${esc(descricao)}</span>
        `;
        document.getElementById('modalPedidoSubtitulo').innerHTML = `
            Código do Item: <b>${iditem}</b> • Embarque <b>#${estadoConsulta.embarqueSelecionado}</b>
        `;

        const conteudo = document.getElementById('modalPedidoConteudo');
        conteudo.innerHTML = `
            <!-- Resumo do Item no Embarque -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 p-4 rounded-2xl bg-gradient-to-r from-emerald-900 to-teal-800 text-white shadow-xs">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                        <i class="fa-solid fa-boxes-packing"></i>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-emerald-200 block">Total Solicitado</span>
                        <span class="text-2xl font-black">${formatarNumero(totalUnidades)} un</span>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:justify-end">
                    <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center text-white text-lg">
                        <i class="fa-solid fa-file-invoice"></i>
                    </div>
                    <div>
                        <span class="text-[10px] uppercase font-bold text-emerald-200 block">Pedidos Vinculados</span>
                        <span class="text-2xl font-black">${pedidos.length} pedidos</span>
                    </div>
                </div>
            </div>

            <!-- Alerta de Segurança e Auditoria -->
            <div class="p-3.5 rounded-2xl bg-slate-50 border border-slate-200 flex items-start gap-2.5 text-xs text-slate-600">
                <i class="fa-solid fa-shield-halved text-emerald-700 text-sm mt-0.5 flex-none"></i>
                <div class="leading-relaxed">
                    <b>Segurança de Dados no ERP:</b> Ao editar ou remover, o item é atualizado com segurança sem exclusão física do banco, registrando o log oficial de auditoria (<code>pedido_item_log</code>) e recalculando parcelas e peso do embarque automaticamente.
                </div>
            </div>

            <!-- Lista de Pedidos -->
            <div class="space-y-3 pt-1">
                ${pedidos.map((pedido, index) => {
                    const qt = parseFloat(pedido.qt || 0);
                    return `
                        <div class="bg-white border border-slate-200 hover:border-emerald-500 rounded-2xl p-4 sm:p-5 transition shadow-xs">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                                <div class="flex items-start gap-3">
                                    <span class="w-8 h-8 rounded-xl bg-emerald-100 text-[#375a4b] font-black text-xs flex items-center justify-center flex-none mt-0.5">
                                        #${index + 1}
                                    </span>
                                    <div>
                                        <h5 class="font-black text-slate-800 text-base flex items-center gap-2">
                                            <span>Pedido #${pedido.idpedido}</span>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">Linha #${pedido.iditempedido}</span>
                                        </h5>
                                        <p class="text-xs text-slate-500 font-semibold mt-0.5 flex items-center gap-1.5">
                                            <i class="fa-solid fa-store text-emerald-600 text-xs"></i>
                                            <span>${esc(pedido.cliente || 'Cliente não informado')}</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 self-start sm:self-auto">
                                    <span class="text-xs font-bold text-slate-400">Quantidade:</span>
                                    <span class="px-3 py-1.5 rounded-xl bg-emerald-50 text-[#375a4b] font-black text-base border border-emerald-200">
                                        ${formatarNumero(qt)} un
                                    </span>
                                </div>
                            </div>

                            <!-- Botões de Ação do Pedido -->
                            <div class="flex flex-wrap items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                <button type="button" onclick="editarQtdPedido(${pedido.idpedido}, ${pedido.iditempedido}, ${qt}, '${esc(descricao)}')"
                                        class="px-4 py-2 rounded-xl bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 font-black text-xs transition flex items-center gap-1.5 cursor-pointer"
                                        data-tooltip="Editar Quantidade do Pedido|Altera a quantidade deste produto no pedido, gravando log no ERP e recalculando as parcelas">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                    <span>Editar Quantidade</span>
                                </button>
                                <button type="button" onclick="removerItemPedido(${pedido.idpedido}, ${pedido.iditempedido}, '${esc(descricao)}')"
                                        class="px-4 py-2 rounded-xl bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-black text-xs transition flex items-center gap-1.5 cursor-pointer"
                                        data-tooltip="Remover Item do Pedido|Inativa este produto no pedido com log oficial de auditoria no ERP (sem DELETE físico) e recalcula parcelas e embarque">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <span>Remover Item</span>
                                </button>
                            </div>
                        </div>
                    `;
                }).join('')}
            </div>
        `;

        abrirModalPedido();

    } catch (e) {
        Swal.close();
        showError('Erro ao Buscar Pedidos', e.message || 'Falha ao buscar pedidos do item.');
    }
}

function abrirModalPedido() {
    const modal = document.getElementById('modalPedido');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function fecharModalPedido() {
    const modal = document.getElementById('modalPedido');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

// ======================================================================
// EDITAR QUANTIDADE DO PEDIDO
// ======================================================================
async function editarQtdPedido(idpedido, iditempedido, qtdAtual, descricao) {
    const { value: novaQtd } = await Swal.fire({
        title: 'Editar Quantidade no Pedido',
        html: `
            <div class="text-left text-xs text-slate-600 space-y-2">
                <p class="font-bold text-slate-800 text-sm">${descricao}</p>
                <p>Pedido: <b>#${idpedido}</b> • Linha: <b>#${iditempedido}</b></p>
                <p class="text-slate-500">Quantidade atual no pedido: <b class="text-slate-800">${formatarNumero(qtdAtual)} un</b></p>
                <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 text-[11px] text-slate-600">
                    <i class="fa-solid fa-circle-info text-emerald-600 mr-1"></i> Digite a nova quantidade atendida. Se digitar 0, o item será inativado e registrado no log do ERP.
                </div>
            </div>
        `,
        input: 'number',
        inputValue: qtdAtual,
        inputAttributes: { min: 0, step: 'any', class: 'swal2-input font-black text-lg' },
        showCancelButton: true,
        confirmButtonColor: '#375a4b',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fa-solid fa-check mr-1"></i> Salvar Quantidade',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'rounded-3xl border border-slate-100 shadow-2xl font-sans text-sm',
            confirmButton: 'rounded-xl font-black px-5 py-2.5 text-xs uppercase tracking-wider',
            cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs uppercase tracking-wider'
        }
    });

    if (novaQtd === undefined || novaQtd === null || novaQtd === '') return;
    const valorNumerico = parseFloat(novaQtd);
    if (isNaN(valorNumerico) || valorNumerico < 0) {
        showToast('Informe uma quantidade válida maior ou igual a zero', 'warning');
        return;
    }

    try {
        showLoading('Atualizando quantidade e recalculando ERP...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${getApiBaseUrl()}/consulta/editar-pedido`, {
            method: 'POST',
            headers: { 
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                idpedido,
                iditempedido,
                idembarque: estadoConsulta.embarqueSelecionado,
                nova_qtd: valorNumerico,
                idusuario: getUserId()
            })
        });
        const result = await resp.json();
        Swal.close();

        if (result && result.success) {
            showToast('Quantidade atualizada com sucesso no ERP!');
            if (estadoConsulta.itemModalAberto) {
                await verPedidosItem(estadoConsulta.itemModalAberto.iditem, estadoConsulta.itemModalAberto.descricao, estadoConsulta.itemModalAberto.referencia);
            }
            carregarSaldos();
        } else {
            showError('Erro ao Atualizar', result.error || 'Falha ao atualizar o pedido no ERP.');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message || 'Falha na requisição.');
    }
}

// ======================================================================
// REMOVER ITEM DO PEDIDO (COM LOG DE AUDITORIA NO ERP E SEM DELETE FÍSICO)
// ======================================================================
async function removerItemPedido(idpedido, iditempedido, descricao) {
    const confirmacao = await Swal.fire({
        icon: 'warning',
        title: 'Remover Item do Pedido?',
        html: `
            <div class="text-left text-xs text-slate-600 space-y-2.5">
                <p>Você confirma a remoção do item <b>${descricao}</b> do <b>Pedido #${idpedido}</b>?</p>
                <div class="p-3 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 text-xs leading-relaxed">
                    <i class="fa-solid fa-shield-halved text-amber-700 mr-1.5 text-sm"></i>
                    <b>Preservação do Histórico no ERP:</b> O item não será deletado fisicamente do banco. Ele será inativado e registrado no log oficial de exclusão (<code>pedido_item_log</code>), e as parcelas e totais do embarque serão recalculados automaticamente.
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#94a3b8',
        confirmButtonText: '<i class="fa-solid fa-trash-can mr-1.5"></i> Sim, Remover Item',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'rounded-3xl border border-slate-100 shadow-2xl font-sans text-sm',
            confirmButton: 'rounded-xl font-black px-5 py-2.5 text-xs uppercase tracking-wider',
            cancelButton: 'rounded-xl font-bold px-4 py-2.5 text-xs uppercase tracking-wider'
        }
    });

    if (!confirmacao.isConfirmed) return;

    try {
        showLoading('Inativando item e registrando auditoria no ERP...');
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${getApiBaseUrl()}/consulta/remover-item-pedido`, {
            method: 'POST',
            headers: { 
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                idpedido,
                iditempedido,
                idembarque: estadoConsulta.embarqueSelecionado,
                idusuario: getUserId()
            })
        });
        const data = await resp.json();
        Swal.close();

        if (data && data.success) {
            showToast('Item removido do pedido com sucesso!');
            // Recarrega o modal do item atual para ver os pedidos restantes
            if (estadoConsulta.itemModalAberto) {
                try {
                    await verPedidosItem(estadoConsulta.itemModalAberto.iditem, estadoConsulta.itemModalAberto.descricao, estadoConsulta.itemModalAberto.referencia);
                } catch {
                    fecharModalPedido();
                }
            }
            // Recarrega a tabela principal com os novos saldos
            carregarSaldos();
        } else {
            showError('Erro ao Remover', data.error || 'Falha ao remover o item no ERP.');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message || 'Falha na requisição.');
    }
}

// ======================================================================
// EXPORTAR RELATÓRIO EM PDF
// ======================================================================
async function exportarPDF() {
    if (!estadoConsulta.dadosOriginais.length) {
        showToast('Nenhum dado para exportar. Selecione um embarque primeiro.', 'warning');
        return;
    }

    showLoading('Gerando relatório completo em PDF...');

    try {
        const token = localStorage.getItem('authToken');
        const apiBase = getApiBaseUrl();

        // Dados do embarque
        const respEmbarque = await fetch(`${apiBase}/embarques-ativos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const embarques = await respEmbarque.json();
        const embarque = (Array.isArray(embarques) ? embarques : []).find(e => String(e.idembarque) === String(estadoConsulta.embarqueSelecionado));

        // Busca pedidos de cada item para enriquecer o relatório
        const itensComPedidos = await Promise.all(
            estadoConsulta.dadosOriginais.map(async (item) => {
                try {
                    const resp = await fetch(`${apiBase}/consulta/pedidos-item/${estadoConsulta.embarqueSelecionado}/${item.iditem}`, {
                        headers: { 'Authorization': 'Bearer ' + token }
                    });
                    const pedidos = await resp.json();
                    return { ...item, pedidos: Array.isArray(pedidos) ? pedidos : [] };
                } catch {
                    return { ...item, pedidos: [] };
                }
            })
        );

        Swal.close();

        const dataAtualFormatada = new Date().toLocaleString('pt-BR');
        const usuarioEmissor = getUserNome();

        let html = `
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Relatório de Saldo Disponível | Embarque #${estadoConsulta.embarqueSelecionado}</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding: 25px; color: #1e293b; font-size: 10px; line-height: 1.4; }
                
                .header { background: #2c493c; color: white; padding: 18px 22px; border-radius: 12px; margin-bottom: 18px; }
                .header h1 { font-size: 18px; margin-bottom: 4px; font-weight: 800; }
                .header p { font-size: 10px; opacity: 0.85; }
                
                .info-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
                .info-card { border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; background: #f8fafc; }
                .info-label { font-size: 8px; color: #64748b; text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; }
                .info-value { font-size: 12px; font-weight: 800; color: #1e293b; margin-top: 3px; }
                
                .section-title { font-size: 13px; font-weight: 800; color: #2c493c; margin: 20px 0 10px 0; padding-bottom: 6px; border-bottom: 2px solid #2c493c; }
                .section-title.danger { color: #dc2626; border-color: #dc2626; }
                
                .item-block { margin-bottom: 14px; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; page-break-inside: avoid; }
                .item-header { background: #f8fafc; padding: 10px 14px; border-bottom: 1px solid #e2e8f0; }
                .item-header .ref { font-size: 9px; color: #64748b; font-weight: 700; font-family: monospace; }
                .item-header .nome { font-size: 12px; font-weight: 800; color: #0f172a; margin: 2px 0; }
                .item-header .numeros { display: flex; gap: 16px; margin-top: 6px; font-size: 10px; }
                .item-header .numeros span { color: #475569; }
                .item-header .numeros .negativo { color: #dc2626; font-weight: 800; background: #fee2e2; padding: 1px 6px; border-radius: 4px; }
                .item-header .numeros .positivo { color: #15803d; font-weight: 800; background: #dcfce7; padding: 1px 6px; border-radius: 4px; }
                
                .pedido-lista { padding: 8px 14px; background: #ffffff; }
                .pedido-row { padding: 6px 0; border-bottom: 1px solid #f1f5f9; font-size: 10px; display: flex; justify-content: space-between; align-items: center; }
                .pedido-row:last-child { border-bottom: none; }
                .pedido-id { font-weight: 800; color: #2c493c; }
                .pedido-cliente { color: #475569; margin-left: 8px; flex: 1; }
                .pedido-qt { font-weight: 800; background: #f1f5f9; padding: 2px 8px; border-radius: 6px; color: #0f172a; }
                
                .sem-pedidos { color: #94a3b8; font-style: italic; padding: 8px 14px; font-size: 10px; }
                .footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 9px; color: #94a3b8; text-align: center; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Nutricional Distribuidora • Relatório de Saldo por Embarque</h1>
                <p>Gerado em: ${dataAtualFormatada} • Emissor: ${usuarioEmissor} • Embarque #${estadoConsulta.embarqueSelecionado}</p>
            </div>
            
            ${embarque ? `
            <div class="info-grid">
                <div class="info-card"><div class="info-label">Embarque</div><div class="info-value">#${embarque.idembarque}</div></div>
                <div class="info-card"><div class="info-label">Rota</div><div class="info-value">${embarque.rota || embarque.observacao || '---'}</div></div>
                <div class="info-card"><div class="info-label">Entregador</div><div class="info-value">${embarque.entregador || '---'}</div></div>
                <div class="info-card"><div class="info-label">Placa</div><div class="info-value">${embarque.placa || 'S/P'}</div></div>
                <div class="info-card"><div class="info-label">Transportadora</div><div class="info-value">${embarque.fantasia_transportadora || '---'}</div></div>
                <div class="info-card"><div class="info-label">Peso Total</div><div class="info-value">${parseFloat(embarque.totalpesobruto || 0).toLocaleString('pt-BR')} kg</div></div>
                <div class="info-card"><div class="info-label">Valor Total</div><div class="info-value">R$ ${parseFloat(embarque.valortotal || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2})}</div></div>
                <div class="info-card"><div class="info-label">Total Pedidos</div><div class="info-value">${embarque.qt_pedido || 0}</div></div>
            </div>
            ` : ''}

            <div class="section-title danger">⚠️ Itens com Falta de Saldo ou Pendências de Estoque (${itensComPedidos.length})</div>

            ${itensComPedidos.map(item => {
                const estoqueFisico = parseFloat(item.estoque_fisico !== undefined ? item.estoque_fisico : (item.estoque || 0));
                const reserva = parseFloat(item.qtd_reservada || 0);
                const disponivel = parseFloat(item.estoque_disponivel !== undefined ? item.estoque_disponivel : (estoqueFisico - reserva));
                const qtd = parseFloat(item.qtd_embarque || 0);
                const saldo = parseFloat(item.saldo_final !== undefined ? item.saldo_final : (disponivel - qtd));
                const temPedidos = item.pedidos && item.pedidos.length > 0;

                return `
                <div class="item-block">
                    <div class="item-header">
                        <div class="ref">REF: ${esc(item.referencia || '---')} | CÓDIGO ITEM: ${item.iditem}</div>
                        <div class="nome">${esc(item.descricao)}</div>
                        <div class="numeros">
                            <span>Físico: <b>${formatarNumero(estoqueFisico)}</b></span>
                            <span>Reserva: <b>${formatarNumero(reserva)}</b></span>
                            <span>Disponível: <b>${formatarNumero(disponivel)}</b></span>
                            <span>No Embarque: <b>${formatarNumero(qtd)}</b></span>
                            <span>Saldo Final: <b class="${saldo < 0 ? 'negativo' : 'positivo'}">${formatarNumero(saldo)} un</b></span>
                        </div>
                    </div>
                    ${temPedidos ? `
                    <div style="background:#f1f5f9; padding:4px 14px; font-size:9px; color:#475569; font-weight:800;">
                        PEDIDOS AFETADOS NESTE EMBARQUE (${item.pedidos.length}):
                    </div>
                    <div class="pedido-lista">
                        ${item.pedidos.map(p => `
                        <div class="pedido-row">
                            <span class="pedido-id">Pedido #${p.idpedido}</span>
                            <span class="pedido-cliente">🏪 ${esc(p.cliente || 'Cliente não informado')}</span>
                            <span class="pedido-qt">${formatarNumero(parseFloat(p.qt || 0))} un</span>
                        </div>
                        `).join('')}
                    </div>
                    ` : `
                    <div class="sem-pedidos">Nenhum pedido ativo encontrado para este item no embarque</div>
                    `}
                </div>`;
            }).join('')}

            <div class="footer">
                Portal Nutricional Distribuidora • Relatório Gerado para Gestão Logística e Conferência de Carga
            </div>
        </body>
        </html>`;

        const win = window.open('', '_blank', 'width=1000,height=850');
        win.document.write(html);
        win.document.close();
        setTimeout(() => win.print(), 600);

    } catch (e) {
        Swal.close();
        showError('Erro ao Gerar PDF', e.message || 'Falha ao processar relatório.');
    }
}

// ======================================================================
// UTILITÁRIOS DE FORMATO & SEGURANÇA
// ======================================================================
function formatarNumero(valor) {
    if (valor === undefined || valor === null || isNaN(valor)) return '0';
    const num = parseFloat(valor);
    if (Number.isInteger(num)) return num.toLocaleString('pt-BR');
    return parseFloat(num.toFixed(3)).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 3 });
}

function esc(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function showLoading(title) {
    Swal.fire({
        title,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading(),
        customClass: {
            popup: 'rounded-3xl border border-slate-100 shadow-2xl font-sans text-sm'
        }
    });
}

function showToast(message, icon = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon,
        title: message,
        showConfirmButton: false,
        timer: 3000
    });
}

function showError(title, text) {
    Swal.fire({
        icon: 'error',
        title,
        text,
        confirmButtonColor: '#ef4444',
        customClass: {
            popup: 'rounded-3xl border border-slate-100 shadow-2xl font-sans text-sm',
            confirmButton: 'rounded-xl font-black px-5 py-2.5 text-xs uppercase tracking-wider'
        }
    });
}
