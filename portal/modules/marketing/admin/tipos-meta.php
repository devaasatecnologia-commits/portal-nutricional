<?php
$pageTitle = 'Tipos de Meta | Admin Marketing';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<style>
.tipo-card { transition: all 0.3s ease; cursor: pointer; }
.tipo-card:hover { transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.1); }
.campo-item { background: #f8fafc; border-radius: 10px; padding: 12px; margin-bottom: 8px; }
.dragging { opacity: 0.5; }
</style>
';
require_once __DIR__ . '/../../../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" x-data="tiposMeta()" x-init="init()">

    <!-- Header -->
    <div class="rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/modules/marketing/admin/index.php" class="btn-voltar sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-cubes text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">TIPOS DE META</h2>
                <span class="text-xs text-slate-400 font-medium">Configure os modelos de meta</span>
            </div>
        </div>
        <button onclick="abrirModalTipo()" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-bold hover:bg-purple-700 transition-all">
            <i class="fa-solid fa-plus mr-2"></i>Novo Tipo
        </button>
    </div>

    <!-- Lista de Tipos -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8" id="tiposContainer">
        <div class="text-center py-8 text-slate-400 col-span-full">Carregando tipos...</div>
    </div>

    <!-- Modal de Tipo -->
    <div id="modalTipo" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalTipo()"></div>
            <div class="relative bg-white rounded-3xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="bg-purple-600 px-6 py-4 flex items-center justify-between rounded-t-3xl sticky top-0">
                    <h3 class="text-lg font-bold text-white" id="modalTipoTitulo">
                        <i class="fa-solid fa-cube mr-2"></i>Novo Tipo de Meta
                    </h3>
                    <button onclick="fecharModalTipo()" class="text-white/70 hover:text-white">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="tipoId">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nome *</label>
                            <input type="text" id="tipoNome" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="Ex: Meta de Vendas">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Ícone</label>
                            <input type="text" id="tipoIcone" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="fa-chart-line" value="fa-chart-line">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Descrição</label>
                        <textarea id="tipoDescricao" rows="2" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="Descreva o propósito deste tipo de meta..."></textarea>
                    </div>
                    
                    <div class="border-t border-slate-100 pt-4">
                        <h4 class="font-bold text-slate-700 mb-3">Campos da Meta</h4>
                        <div id="camposLista" class="space-y-2 mb-3"></div>
                        <div class="flex gap-2">
                            <button onclick="adicionarCampo()" class="px-3 py-2 bg-slate-100 text-slate-600 rounded-xl text-sm font-bold hover:bg-slate-200">
                                <i class="fa-solid fa-plus mr-1"></i>Adicionar Campo
                            </button>
                        </div>
                    </div>
                    
                    <div class="pt-4 flex gap-3">
                        <button onclick="salvarTipo()" class="flex-1 px-4 py-3 bg-purple-600 text-white rounded-xl font-bold hover:bg-purple-700 transition-all">
                            <i class="fa-solid fa-save mr-2"></i>Salvar
                        </button>
                        <button onclick="fecharModalTipo()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Campo -->
    <div id="modalCampo" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalCampo()"></div>
            <div class="relative bg-white rounded-3xl max-w-md w-full shadow-2xl">
                <div class="bg-slate-700 px-6 py-4 flex items-center justify-between rounded-t-3xl">
                    <h3 class="text-lg font-bold text-white">Configurar Campo</h3>
                    <button onclick="fecharModalCampo()" class="text-white/70 hover:text-white">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="campoIndex">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nome do Campo *</label>
                        <input type="text" id="campoNome" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="meta_leads">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Rótulo *</label>
                        <input type="text" id="campoRotulo" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="Meta de Leads">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Tipo</label>
                            <select id="campoTipo" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                                <option value="number">Número</option>
                                <option value="text">Texto</option>
                                <option value="date">Data</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Unidade</label>
                            <input type="text" id="campoUnidade" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="unidades, R$, %">
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="campoObrigatorio" class="w-4 h-4">
                        <label class="text-sm text-slate-600">Campo obrigatório</label>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="campoEditavel" class="w-4 h-4" checked>
                        <label class="text-sm text-slate-600">Editável pelo usuário (aparece na alimentação)</label>
                    </div>
                    <div class="mt-2">
    <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Tipo para Cálculo</label>
    <select id="campoTipoComparacao" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
        <option value="">Informativo (não usado em cálculos)</option>
        <option value="taxa_inicial">Taxa Inicial (valor de partida)</option>
        <option value="valor_atual">Valor Atual (usuário alimenta)</option>
        <option value="meta_final">Meta Final (objetivo a atingir)</option>
    </select>
</div>
                    <div class="pt-4 flex gap-3">
                        <button onclick="salvarCampo()" class="flex-1 px-4 py-3 bg-slate-700 text-white rounded-xl font-bold hover:bg-slate-800 transition-all">
                            Salvar Campo
                        </button>
                        <button onclick="fecharModalCampo()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function tiposMeta() {
        return {
            tipos: [],
            camposTemp: [],
            editandoTipoId: null,
            
            async init() {
                await this.carregarTipos();
            },
            
            getToken() {
                return localStorage.getItem('authToken');
            },
            
            async fetchWithAuth(url, options = {}) {
                const token = this.getToken();
                if (!token) {
                    window.location.href = '/portal/login.php';
                    throw new Error('Token não encontrado');
                }
                
                const response = await fetch(url, {
                    ...options,
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json',
                        ...(options.headers || {})
                    }
                });
                
                if (response.status === 401) {
                    localStorage.removeItem('authToken');
                    localStorage.removeItem('userData');
                    window.location.href = '/portal/login.php';
                    throw new Error('Sessão expirada');
                }
                
                return response;
            },
            
            async carregarTipos() {
                try {
                    const resp = await this.fetchWithAuth('/v1/meta-builder/tipos');
                    const data = await resp.json();
                    
                    if (data.success) {
                        this.tipos = data.data;
                        this.renderizarTipos();
                    }
                } catch (e) {
                    console.error('Erro ao carregar tipos:', e);
                    document.getElementById('tiposContainer').innerHTML = '<div class="text-center py-8 text-rose-500 col-span-full">Erro ao carregar tipos</div>';
                }
            },
            
           renderizarTipos() {
    const container = document.getElementById('tiposContainer');
    
    if (!this.tipos.length) {
        container.innerHTML = '<div class="text-center py-8 text-slate-400 col-span-full">Nenhum tipo de meta cadastrado</div>';
        return;
    }
    
    const cores = {
        blue: 'bg-blue-100 text-blue-700',
        green: 'bg-green-100 text-green-700',
        purple: 'bg-purple-100 text-purple-700',
        amber: 'bg-amber-100 text-amber-700',
        emerald: 'bg-emerald-100 text-emerald-700',
        rose: 'bg-rose-100 text-rose-700'
    };
    
    container.innerHTML = this.tipos.map(t => `
        <div class="tipo-card bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-100 ${cores[t.cor] || 'bg-slate-100'}">
        <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/50">
        <i class="${t.icone || 'fa-solid fa-cube'} text-lg"></i>
        </div>
        <div>
        <h3 class="font-bold">${t.nome}</h3>
        <p class="text-xs opacity-75">${t.total_campos || 0} campo(s)</p>
        </div>
        </div>
        <div class="flex gap-1">
        <button onclick="editarTipo(${t.id})" class="p-1.5 rounded-lg hover:bg-white/20" title="Editar">
        <i class="fa-solid fa-pen text-xs"></i>
        </button>
        <button onclick="excluirTipo(${t.id}, '${t.nome.replace(/'/g, "\\'")}')" class="p-1.5 rounded-lg hover:bg-red-200 hover:text-red-600" title="Excluir">
        <i class="fa-solid fa-trash text-xs"></i>
        </button>
        </div>
        </div>
        </div>
        <div class="p-4">
        <p class="text-sm text-slate-500">${t.descricao || 'Sem descrição'}</p>
        <div class="mt-3 flex flex-wrap gap-1">
        <span class="text-[10px] px-2 py-0.5 bg-slate-100 rounded-full">ID: ${t.id}</span>
        <span class="text-[10px] px-2 py-0.5 ${t.ativo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'} rounded-full">
        ${t.ativo ? 'Ativo' : 'Inativo'}
        </span>
        </div>
        </div>
        </div>
        `).join('');
},
            
            limparModalTipo() {
                document.getElementById('tipoId').value = '';
                document.getElementById('tipoNome').value = '';
                document.getElementById('tipoIcone').value = 'fa-chart-line';
                document.getElementById('tipoDescricao').value = '';
                this.camposTemp = [];
                this.renderizarCamposLista();
            },
            
            renderizarCamposLista() {
                const container = document.getElementById('camposLista');
                if (!this.camposTemp.length) {
                    container.innerHTML = '<p class="text-center text-slate-400 text-sm py-4">Nenhum campo adicionado</p>';
                    return;
                }
                
                container.innerHTML = this.camposTemp.map((c, idx) => `
                    <div class="campo-item flex justify-between items-center">
                    <div>
                    <span class="font-bold text-sm">${c.rotulo}</span>
                    <span class="text-xs text-slate-400 ml-2">(${c.nome_campo})</span>
                    <div class="text-xs text-slate-400 mt-1">
                    ${c.tipo_campo} ${c.unidade ? `· ${c.unidade}` : ''} ${c.obrigatorio ? '· Obrigatório' : ''}
                    </div>
                    </div>
                    <div class="flex gap-2">
                    <button onclick="editarCampo(${idx})" class="text-slate-400 hover:text-slate-600">
                    <i class="fa-solid fa-pen text-xs"></i>
                    </button>
                    <button onclick="removerCampo(${idx})" class="text-slate-400 hover:text-rose-500">
                    <i class="fa-solid fa-trash text-xs"></i>
                    </button>
                    </div>
                    </div>
                    `).join('');
            }
        };
    }

    window.tiposMetaApp = null;

// Funções globais
function abrirModalTipo() {
    if (window.tiposMetaApp) {
        window.tiposMetaApp.limparModalTipo();
        document.getElementById('modalTipoTitulo').innerHTML = '<i class="fa-solid fa-cube mr-2"></i>Novo Tipo de Meta';
        document.getElementById('modalTipo').classList.remove('hidden');
    }
}

function fecharModalTipo() {
    document.getElementById('modalTipo').classList.add('hidden');
}

function adicionarCampo() {
    document.getElementById('campoIndex').value = '';
    document.getElementById('campoNome').value = '';
    document.getElementById('campoRotulo').value = '';
    document.getElementById('campoTipo').value = 'number';
    document.getElementById('campoUnidade').value = '';
    document.getElementById('campoObrigatorio').checked = true;
    document.getElementById('campoEditavel').checked = true;
    document.getElementById('campoTipoComparacao').value = ''; 
    document.getElementById('modalCampo').classList.remove('hidden');
}

function editarCampo(index) {
    const campo = window.tiposMetaApp.camposTemp[index];
    document.getElementById('campoIndex').value = index;
    document.getElementById('campoNome').value = campo.nome_campo;
    document.getElementById('campoRotulo').value = campo.rotulo;
    document.getElementById('campoTipo').value = campo.tipo_campo;
    document.getElementById('campoUnidade').value = campo.unidade || '';
    document.getElementById('campoObrigatorio').checked = campo.obrigatorio;
    document.getElementById('campoEditavel').checked = campo.editavel !== false;
    document.getElementById('campoTipoComparacao').value = campo.tipo_comparacao || ''; 
    document.getElementById('modalCampo').classList.remove('hidden');
}

function removerCampo(index) {
    window.tiposMetaApp.camposTemp.splice(index, 1);
    window.tiposMetaApp.renderizarCamposLista();
}

function fecharModalCampo() {
    document.getElementById('modalCampo').classList.add('hidden');
}

function salvarCampo() {
    const campo = {
        nome_campo: document.getElementById('campoNome').value.trim(),
        rotulo: document.getElementById('campoRotulo').value.trim(),
        tipo_campo: document.getElementById('campoTipo').value,
        unidade: document.getElementById('campoUnidade').value.trim(),
        obrigatorio: document.getElementById('campoObrigatorio').checked,
        editavel: document.getElementById('campoEditavel').checked,
        tipo_comparacao: document.getElementById('campoTipoComparacao').value,
        ordem: window.tiposMetaApp.camposTemp.length
    };
    
    if (!campo.nome_campo || !campo.rotulo) {
        Swal.fire('Atenção', 'Preencha nome e rótulo do campo', 'warning');
        return;
    }
    
    const index = document.getElementById('campoIndex').value;
    if (index !== '') {
        window.tiposMetaApp.camposTemp[parseInt(index)] = campo;
    } else {
        window.tiposMetaApp.camposTemp.push(campo);
    }
    
    window.tiposMetaApp.renderizarCamposLista();
    fecharModalCampo();
}
async function excluirTipo(id, nome) {
    const result = await Swal.fire({
        title: 'Excluir Tipo?',
        html: `<p>Deseja realmente excluir o tipo:</p><p class="font-bold text-rose-600 mt-2">"${nome}"</p><p class="text-xs text-slate-400 mt-3">Isso também removerá todos os campos vinculados.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    });
    
    if (!result.isConfirmed) return;
    
    Swal.fire({ title: 'Excluindo...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`/v1/meta-builder/tipos/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        Swal.close();
        
        if (resp.ok) {
            Swal.fire({ icon: 'success', title: 'Excluído!', timer: 1500, showConfirmButton: false });
            if (window.tiposMetaApp) {
                window.tiposMetaApp.carregarTipos();
            }
        } else {
            const data = await resp.json();
            Swal.fire('Erro', data.error || 'Falha ao excluir', 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Erro', 'Falha ao excluir: ' + e.message, 'error');
    }
}

async function salvarTipo() {
    const id = document.getElementById('tipoId').value;
    const dados = {
        nome: document.getElementById('tipoNome').value.trim(),
        descricao: document.getElementById('tipoDescricao').value.trim(),
        icone: document.getElementById('tipoIcone').value.trim(),
        cor: 'purple',
        campos: window.tiposMetaApp ? window.tiposMetaApp.camposTemp : []
    };
    
    if (!dados.nome) {
        Swal.fire('Atenção', 'Nome do tipo é obrigatório', 'warning');
        return;
    }
    
    Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const token = localStorage.getItem('authToken');
        let url, method;
        
        if (id && id !== '') {
            // Editar tipo existente
            url = `/v1/meta-builder/tipos/${id}`;
            method = 'PUT';
        } else {
            // Criar novo tipo
            url = '/v1/meta-builder/tipos';
            method = 'POST';
        }
        
        const resp = await fetch(url, {
            method: method,
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
            body: JSON.stringify(dados)
        });
        
        const result = await resp.json();
        Swal.close();
        
        if (result.success) {
            Swal.fire({ icon: 'success', title: 'Salvo!', timer: 1500, showConfirmButton: false });
            fecharModalTipo();
            
            // Recarregar lista
            if (window.tiposMetaApp) {
                window.tiposMetaApp.carregarTipos();
            } else {
                location.reload();
            }
        } else {
            Swal.fire('Erro', result.error || 'Falha ao salvar', 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Erro', e.message, 'error');
    }
}

async function editarTipo(id) {
    Swal.fire({ title: 'Carregando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`/v1/meta-builder/tipos/${id}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        
        Swal.close();
        
        if (data.success && data.data) {
            const tipo = data.data;
            
            // Preencher o formulário do modal
            document.getElementById('tipoId').value = tipo.id;
            document.getElementById('tipoNome').value = tipo.nome;
            document.getElementById('tipoIcone').value = tipo.icone || 'fa-chart-line';
            document.getElementById('tipoDescricao').value = tipo.descricao || '';
            
            // Carregar campos existentes
            if (window.tiposMetaApp) {
                window.tiposMetaApp.camposTemp = tipo.campos || [];
                window.tiposMetaApp.renderizarCamposLista();
            }
            
            document.getElementById('modalTipoTitulo').innerHTML = '<i class="fa-solid fa-pen mr-2"></i>Editar Tipo de Meta';
            document.getElementById('modalTipo').classList.remove('hidden');
        } else {
            Swal.fire('Erro', data.error || 'Não foi possível carregar o tipo', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error(e);
        Swal.fire('Erro', 'Falha ao carregar dados', 'error');
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    window.tiposMetaApp = new tiposMeta();
    window.tiposMetaApp.init();
});
</script>

<?php require_once __DIR__ . '/../../../estrutura/footer.php'; ?>