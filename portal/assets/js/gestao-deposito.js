// ==========================================================================
// MÓDULO DE GESTÃO DE DEPÓSITO (ENDEREÇOS E LOCALIZAÇÃO)
// ==========================================================================

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================
window.addEventListener('DOMContentLoaded', async function() {
    await carregarResumo();
    await carregarSecoes();
});

// ==========================================================================
// RESUMO
// ==========================================================================
async function carregarResumo() {
    try {
        const resp = await apiFetch('v1/deposito/resumo', 'GET');
        document.getElementById('resumoTotalSecoes').innerText = resp.total_secoes || '0';
        document.getElementById('resumoTotalEnderecos').innerText = resp.total_enderecos || '0';
        document.getElementById('resumoOcupados').innerText = resp.ocupados || '0';
        document.getElementById('resumoLotes').innerText = resp.total_lotes || '0';
    } catch (e) {
        console.error('Erro ao carregar resumo:', e);
    }
}

// ==========================================================================
// LISTAGEM DE SEÇÕES
// ==========================================================================
async function carregarSecoes() {
    const container = document.getElementById('listaSecoes');
    container.innerHTML = '<p class="text-center text-slate-400 py-8">Carregando...</p>';
    
    try {
        const resp = await apiFetch('v1/deposito/secoes', 'GET');
        const secoes = Array.isArray(resp) ? resp : [];
        
        if (secoes.length === 0) {
            container.innerHTML = '<div class="bg-white rounded-2xl p-8 text-center"><i class="fa-solid fa-warehouse text-4xl text-slate-300 mb-3 block"></i><p class="text-slate-400">Nenhuma seção encontrada.</p></div>';
            return;
        }
        
        let h = '';
        secoes.forEach(s => {
            const corBg = s.total_enderecos > 0 ? 'bg-white' : 'bg-amber-50';
            h += `
            <div class="${corBg} rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
                <!-- Cabeçalho da Seção -->
                <div class="p-4 flex justify-between items-center cursor-pointer hover:bg-slate-50 transition-colors" onclick="toggleSecao(${s.idsecao})">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center font-bold">
                            ${s.sigla || s.idsecao}
                        </div>
                        <div>
                            <h3 class="font-bold text-slate-800">${s.descricao}</h3>
                            <span class="text-xs text-slate-400">
                                ${s.total_enderecos || 0} endereços | ${s.enderecos_ocupados || 0} ocupados | ${s.total_lotes || 0} lotes
                            </span>
                        </div>
                    </div>
                    <i class="fa-solid fa-chevron-down text-slate-400 transition-transform" id="icone-${s.idsecao}"></i>
                </div>
                
                <!-- Lista de Endereços (oculta) -->
                <div id="enderecos-${s.idsecao}" class="hidden border-t border-slate-100 bg-slate-50">
                    <div class="p-3 text-center text-slate-400 text-sm">Carregando endereços...</div>
                </div>
            </div>`;
        });
        
        container.innerHTML = h;
    } catch (e) {
        container.innerHTML = '<p class="text-center text-rose-500 py-8">Erro ao carregar seções.</p>';
        console.error('Erro:', e);
    }
}

// ==========================================================================
// EXPANDIR SEÇÃO (CARREGAR ENDEREÇOS)
// ==========================================================================
async function toggleSecao(idsecao) {
    const divEnderecos = document.getElementById('enderecos-' + idsecao);
    const icone = document.getElementById('icone-' + idsecao);
    
    if (divEnderecos.classList.contains('hidden')) {
        // Abrir
        divEnderecos.classList.remove('hidden');
        if (icone) icone.style.transform = 'rotate(180deg)';
        await carregarEnderecos(idsecao);
    } else {
        // Fechar
        divEnderecos.classList.add('hidden');
        if (icone) icone.style.transform = 'rotate(0deg)';
    }
}

async function carregarEnderecos(idsecao) {
    const container = document.getElementById('enderecos-' + idsecao);
    container.innerHTML = '<div class="p-3 text-center text-slate-400 text-sm">Carregando...</div>';
    
    try {
        const resp = await apiFetch(`v1/deposito/enderecos/${idsecao}`, 'GET');
        const enderecos = Array.isArray(resp) ? resp : [];
        
        if (enderecos.length === 0) {
            container.innerHTML = `
                <div class="p-6 text-center">
                    <i class="fa-solid fa-map-pin text-2xl text-slate-300 mb-2 block"></i>
                    <p class="text-sm text-slate-400">Nenhum endereço cadastrado nesta seção.</p>
                    <button onclick="abrirModalEnderecoSecao(${idsecao})" class="mt-3 px-4 py-2 bg-amber-500 text-white rounded-xl text-sm font-bold hover:bg-amber-600">
                        <i class="fa-solid fa-plus mr-2"></i>Cadastrar Endereço
                    </button>
                </div>`;
            return;
        }
        
        let h = '<div class="divide-y divide-slate-200">';
        enderecos.forEach(e => {
            const pctOcupado = e.capacidade > 0 ? Math.round((e.ocupado / e.capacidade) * 100) : 0;
            const corBarra = pctOcupado > 80 ? 'bg-rose-500' : (pctOcupado > 50 ? 'bg-amber-500' : 'bg-emerald-500');
            
            h += `
            <div class="p-3 hover:bg-white transition-colors cursor-pointer" onclick="verLotesEndereco(${e.idendereco}, '${e.linhasigla}${e.colunasigla}')">
                <div class="flex justify-between items-center mb-2">
                    <div class="flex items-center gap-3">
                        <span class="w-16 h-10 bg-slate-200 rounded-lg flex items-center justify-center font-bold text-slate-700 text-sm">
                            ${e.linhasigla}${e.colunasigla}
                        </span>
                        <div>
                            <span class="text-sm font-bold text-slate-700">Rua ${e.linhasigla} - Posição ${e.colunasigla}</span>
                            <span class="text-xs text-slate-400 block">
                                ${e.lotes_armazenados || 0} lote(s) armazenado(s)
                                ${e.lotes_lista ? ' | Lotes: ' + e.lotes_lista : ''}
                            </span>
                        </div>
                    </div>
                    <button onclick="event.stopPropagation(); deletarEndereco(${idsecao}, ${e.idendereco}, '${e.linhasigla}${e.colunasigla}')" 
                            class="text-slate-400 hover:text-rose-500 p-2" title="Excluir endereço">
                        <i class="fa-solid fa-trash text-sm"></i>
                    </button>
                </div>
                
                <!-- Barra de ocupação -->
                <div class="flex items-center gap-2">
                    <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                        <div class="h-full ${corBarra} rounded-full" style="width:${pctOcupado}%"></div>
                    </div>
                    <span class="text-xs font-bold text-slate-500">${e.ocupado}/${e.capacidade}</span>
                </div>
            </div>`;
        });
        h += '</div>';
        
        container.innerHTML = h;
    } catch (e) {
        container.innerHTML = '<div class="p-3 text-center text-rose-500 text-sm">Erro ao carregar endereços.</div>';
        console.error('Erro:', e);
    }
}

// ==========================================================================
// VER LOTES DE UM ENDEREÇO
// ==========================================================================
async function verLotesEndereco(idendereco, sigla) {
    Swal.fire({
        title: 'Endereço ' + sigla,
        html: '<p class="text-center text-slate-400">Carregando lotes...</p>',
        showCloseButton: true,
        showConfirmButton: false,
        didOpen: async () => {
            try {
                const resp = await apiFetch(`v1/deposito/lotes-endereco/${idendereco}`, 'GET');
                const lotes = Array.isArray(resp) ? resp : [];
                
                if (lotes.length === 0) {
                    Swal.update({
                        html: '<div class="text-center py-4"><i class="fa-solid fa-box-open text-3xl text-slate-300 mb-2 block"></i><p class="text-slate-400">Nenhum lote neste endereço.</p></div>'
                    });
                    return;
                }
                
                let h = '<div class="divide-y divide-slate-100 max-h-80 overflow-y-auto">';
                lotes.forEach(l => {
                    h += `
                    <div class="py-3">
                        <p class="font-bold text-sm text-slate-800">${l.nome_item || 'Item ' + l.iditem}</p>
                        <p class="text-xs text-slate-500">REF: ${l.referencia || 'N/A'} | Lote: <b>${l.lote}</b></p>
                        <p class="text-xs text-slate-400">Qtd: ${l.quantidade} | Entrada: ${l.data_entrada || 'N/A'} | OC: ${l.idoc || 'N/A'}</p>
                    </div>`;
                });
                h += '</div>';
                
                Swal.update({ html: h });
            } catch (e) {
                Swal.update({ html: '<p class="text-center text-rose-500">Erro ao carregar lotes.</p>' });
            }
        }
    });
}

// ==========================================================================
// MODAL DE ENDEREÇO
// ==========================================================================
function abrirModalEndereco() {
    document.getElementById('modalEnderecoTitulo').innerHTML = '<i class="fa-solid fa-map-pin mr-2"></i>Novo Endereço';
    document.getElementById('enderecoId').value = '';
    document.getElementById('enderecoLinha').value = '';
    document.getElementById('enderecoColuna').value = '';
    document.getElementById('enderecoSigla').value = '';
    document.getElementById('enderecoCapacidade').value = '100';
    document.getElementById('enderecoNumLinha').value = '1';
    
    carregarSecoesSelect();
    
    document.getElementById('modalEndereco').classList.remove('hidden');
    
    // Gerar sigla automaticamente
    document.getElementById('enderecoLinha').addEventListener('input', gerarSigla);
    document.getElementById('enderecoColuna').addEventListener('input', gerarSigla);
}

function abrirModalEnderecoSecao(idsecao) {
    abrirModalEndereco();
    document.getElementById('enderecoSecao').value = idsecao;
}

function fecharModalEndereco() {
    document.getElementById('modalEndereco').classList.add('hidden');
}

function gerarSigla() {
    const linha = document.getElementById('enderecoLinha').value.toUpperCase();
    const coluna = document.getElementById('enderecoColuna').value.toUpperCase();
    document.getElementById('enderecoSigla').value = linha + coluna;
}

async function carregarSecoesSelect() {
    try {
        const resp = await apiFetch('v1/deposito/secoes', 'GET');
        const secoes = Array.isArray(resp) ? resp : [];
        
        const select = document.getElementById('enderecoSecao');
        select.innerHTML = '<option value="">Selecione a seção...</option>' +
            secoes.map(s => `<option value="${s.idsecao}">${s.descricao} (${s.sigla || s.idsecao})</option>`).join('');
    } catch (e) {
        console.error('Erro:', e);
    }
}

async function salvarEndereco() {
    const dados = {
        idsecao: parseInt(document.getElementById('enderecoSecao').value || 0),
        linha: document.getElementById('enderecoLinha').value.toUpperCase().trim(),
        coluna: document.getElementById('enderecoColuna').value.toUpperCase().trim(),
        num_linha: parseInt(document.getElementById('enderecoNumLinha').value || 1),
        num_coluna: 1,
        capacidade: parseInt(document.getElementById('enderecoCapacidade').value || 100)
    };
    
    if (!dados.idsecao || !dados.linha || !dados.coluna) {
        Swal.fire({ icon: 'warning', title: 'Preencha todos os campos', position: 'top' });
        return;
    }
    
    try {
        Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        
        const resp = await apiFetch('v1/deposito/endereco', 'POST', dados);
        Swal.close();
        
        if (resp.success) {
            Swal.fire({ icon: 'success', title: 'Endereço cadastrado!', text: `Sigla: ${resp.sigla}`, timer: 2000, showConfirmButton: false, position: 'top' });
            fecharModalEndereco();
            await carregarSecoes();
            await carregarResumo();
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: resp.error || 'Falha ao salvar', position: 'top' });
        }
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, position: 'top' });
    }
}


async function deletarEndereco(idsecao, idendereco, sigla) {
    const result = await Swal.fire({
        title: 'Excluir endereço?',
        html: `Deseja realmente excluir o endereço <b>${sigla}</b>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, excluir',
        position: 'top'
    });
    
    if (!result.isConfirmed) return;
    
    try {
        const resp = await apiFetch(`v1/deposito/endereco/${idsecao}/${idendereco}`, 'DELETE');
        
        if (resp.success) {
            Swal.fire({ icon: 'success', title: 'Excluído!', timer: 1500, showConfirmButton: false, position: 'top' });
            await carregarEnderecos(idsecao);
            await carregarResumo();
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: resp.error || 'Falha ao excluir', position: 'top' });
        }
    } catch (e) {
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, position: 'top' });
    }
}
// ==========================================================================
// MODAL DE SEÇÃO (CRIAR/EDITAR)
// ==========================================================================
function abrirModalSecao() {
    document.getElementById('modalSecaoTitulo').innerHTML = '<i class="fa-solid fa-warehouse mr-2"></i>Nova Seção';
    document.getElementById('secaoId').value = '';
    document.getElementById('secaoDescricao').value = '';
    document.getElementById('secaoSigla').value = '';
    document.getElementById('modalSecao').classList.remove('hidden');
}

function fecharModalSecao() {
    document.getElementById('modalSecao').classList.add('hidden');
}

async function editarSecao(idsecao) {
    Swal.fire({ title: 'Carregando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const resp = await apiFetch(`v1/deposito/secao/${idsecao}`, 'GET');
        Swal.close();
        
        if (resp.error) {
            Swal.fire({ icon: 'error', title: 'Erro', text: resp.error, position: 'top' });
            return;
        }
        
        document.getElementById('modalSecaoTitulo').innerHTML = '<i class="fa-solid fa-pen mr-2"></i>Editar Seção';
        document.getElementById('secaoId').value = resp.idsecao;
        document.getElementById('secaoDescricao').value = resp.descricao || '';
        document.getElementById('secaoSigla').value = resp.sigla || '';
        document.getElementById('modalSecao').classList.remove('hidden');
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao carregar seção.', position: 'top' });
    }
}

async function salvarSecao() {
    const idsecao = parseInt(document.getElementById('secaoId').value || 0);
    const dados = {
        idsecao: idsecao,
        descricao: document.getElementById('secaoDescricao').value.trim(),
        sigla: document.getElementById('secaoSigla').value.trim().toUpperCase(),
        usuario: document.getElementById('user_nome')?.value || 'SISTEMA'
    };
    
    if (!dados.descricao) {
        Swal.fire({ icon: 'warning', title: 'Descrição é obrigatória', position: 'top' });
        return;
    }
    
    try {
        Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        
        const resp = await apiFetch('v1/deposito/secao', 'POST', dados);
        Swal.close();
        
        if (resp.success) {
            Swal.fire({ 
                icon: 'success', 
                title: resp.acao === 'criado' ? 'Seção criada!' : 'Seção atualizada!', 
                timer: 2000, showConfirmButton: false, position: 'top' 
            });
            fecharModalSecao();
            await carregarSecoes();
            await carregarResumo();
        } else {
            Swal.fire({ icon: 'error', title: 'Erro', text: resp.error || 'Falha ao salvar', position: 'top' });
        }
    } catch (e) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Erro', text: e.message, position: 'top' });
    }
}

// ==========================================================================
// EXPORTAÇÃO GLOBAL (adicionar)
// ==========================================================================
window.abrirModalSecao = abrirModalSecao;
window.fecharModalSecao = fecharModalSecao;
window.editarSecao = editarSecao;
window.salvarSecao = salvarSecao;
window.toggleSecao = toggleSecao;
window.abrirModalEndereco = abrirModalEndereco;
window.abrirModalEnderecoSecao = abrirModalEnderecoSecao;
window.fecharModalEndereco = fecharModalEndereco;
window.salvarEndereco = salvarEndereco;
window.deletarEndereco = deletarEndereco;
window.verLotesEndereco = verLotesEndereco;
window.carregarSecoes = carregarSecoes;