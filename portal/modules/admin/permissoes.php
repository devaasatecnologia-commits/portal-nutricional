<?php
define('ADMIN_AREA', true);
$pageTitle = 'Permissões de Módulos | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- Header -->
<div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-lock text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">PERMISSÕES DE MÓDULOS</h2>
            <span class="text-xs text-slate-400 font-medium">Configure o acesso dos usuários</span>
        </div>
    </div>
</div>

<!-- Seletor de Usuário -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm mb-6">
    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
        <i class="fa-solid fa-user mr-2"></i>Selecione o Usuário
    </label>
    <div class="flex gap-3">
        <select id="selectUsuario" onchange="carregarPermissoes()" 
                class="flex-1 p-3 border-2 border-slate-200 rounded-xl text-sm font-semibold bg-white cursor-pointer hover:border-purple-300 transition-all">
            <option value="">Carregando usuários...</option>
        </select>
        <button onclick="salvarPermissoes()" class="px-6 py-3 bg-purple-600 text-white rounded-xl font-bold hover:bg-purple-700 transition-all flex items-center gap-2">
            <i class="fa-solid fa-save"></i> Salvar
        </button>
    </div>
</div>

<!-- Grid de Módulos -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="modulosGrid">
    <div class="col-span-full text-center py-12 text-slate-400">
        <i class="fa-solid fa-arrow-up text-4xl mb-3 block"></i>
        <p>Selecione um usuário para configurar suas permissões</p>
    </div>
</div>

<!-- Configurações Avançadas -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm mt-6" id="configAvancada" style="display:none;">
    <h5 class="text-sm font-bold text-slate-700 mb-4">
        <i class="fa-solid fa-building mr-2"></i>Configurações Avançadas
    </h5>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Filiais -->
        <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
                <i class="fa-solid fa-store mr-1"></i> Filiais Permitidas
            </label>
            <div class="space-y-2 max-h-48 overflow-y-auto border border-slate-200 rounded-xl p-3" id="checkFiliais">
                <p class="text-sm text-slate-400">Selecione um usuário</p>
            </div>
        </div>
        
        <!-- Gestores -->
        <div>
            <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
                <i class="fa-solid fa-user-tie mr-1"></i> Gestores Vinculados
            </label>
            <div id="checkGestores" class="space-y-2 max-h-48 overflow-y-auto border border-slate-200 rounded-xl p-3">
                <p class="text-sm text-slate-400">Selecione um usuário</p>
            </div>
        </div>
    </div>

    <!-- 🔥 NOVA SEÇÃO: USUÁRIOS QUE PODE VISUALIZAR -->
    <div class="mt-6 pt-6 border-t border-slate-200">
        <h5 class="text-sm font-bold text-slate-700 mb-4">
            <i class="fa-solid fa-users mr-2"></i>Usuários que pode visualizar
        </h5>
        
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="flex items-center gap-3 cursor-pointer mb-3">
                    <input type="checkbox" id="permiteVerTodos" 
                           class="w-5 h-5 text-purple-600 border-slate-300 rounded focus:ring-purple-500"
                           onchange="toggleSelecaoUsuariosPermissoes()">
                    <div>
                        <span class="text-sm font-bold text-slate-700">Pode ver todos os usuários</span>
                        <br>
                        <span class="text-[10px] text-slate-400">Libera acesso a todos os usuários do sistema</span>
                    </div>
                </label>
            </div>
            
            <div id="selecaoUsuariosPermissoes" style="display:none;">
                <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-user-plus mr-1"></i> Selecione os usuários permitidos
                </label>
                <div class="border border-slate-200 rounded-xl p-3 max-h-48 overflow-y-auto" id="checkUsuariosPermissoes">
                    <p class="text-sm text-slate-400">Carregando usuários...</p>
                </div>
                <div class="mt-2 text-xs text-slate-400">
                    <span id="contadorUsuariosSelecionadosPermissoes">0</span> usuários selecionados
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let usuarioSelecionado = null;
let permissoesAtuais = [];
let todosModulos = [];
let gestoresDisponiveis = [];
let todosUsuariosDisponiveis = []; // 🔥 NOVA VARIÁVEL
let usuariosVisualizarSelecionados = []; // 🔥 NOVA VARIÁVEL

// ✅ Carrega lista de gestores (com nomes)
async function carregarGestores() {
    try {
        const resp = await apiFetch('/admin/gestores', 'GET');
        gestoresDisponiveis = resp.gestores || [];
    } catch (e) {
        // Fallback
        gestoresDisponiveis = [
            { idcliforemp: 13878, nome: 'ADRIANO ROGERIO ELEODORO' },
            { idcliforemp: 15520, nome: 'MICHEL PLATINI DE SOUZA AGUIAR' },
            { idcliforemp: 5297, nome: 'ROBSON DE ALMEIDA BECKER' },
            { idcliforemp: 11371, nome: 'TALES FERNANDO DE JESUS BINDE' },
            { idcliforemp: 11258, nome: 'TIAGO GUSTAVO HERRMANN' }
        ];
    }
}

async function carregarUsuarios() {
    try {
        const data = await apiFetch('/admin/usuarios', 'GET');
        const select = document.getElementById('selectUsuario');
        
        // ✅ Guarda o valor selecionado antes de recarregar
        const currentValue = select.value;
        
        select.innerHTML = '<option value="">Selecione um usuário...</option>';
        
        if (data.usuarios) {
            const ativos = data.usuarios.filter(u => u.inativo === 'N');
            
            ativos.forEach(u => {
                const adminTag = u.nivel_admin ? ` [${u.nivel_admin}]` : '';
                select.innerHTML += `<option value="${u.idcliforemp}" 
                    data-filiais="${u.dash_filiais || ''}" 
                    data-gestores="${u.dash_gestores || ''}"
                    data-permissoes="${u.permissoes || ''}">
                    ${u.username}${adminTag} (ID: ${u.idcliforemp})
                </option>`;
            });
        }
        
        // ✅ Restaura o valor selecionado (se ainda existir)
        if (currentValue) {
            const option = Array.from(select.options).find(opt => opt.value === currentValue);
            if (option) {
                select.value = currentValue;
            }
        }
    } catch (e) {
        showError('Erro', 'Falha ao carregar usuários');
    }
}
// ======================================================================
// USUÁRIOS DISPONÍVEIS PARA VISUALIZAÇÃO
// ======================================================================

async function carregarUsuariosDisponiveis() {
    try {
        const resp = await apiFetch('/admin/usuarios/lista-completa', 'GET');
        todosUsuariosDisponiveis = resp.usuarios || [];
        console.log('✅ Usuários disponíveis carregados:', todosUsuariosDisponiveis.length);
    } catch (e) {
        console.error('❌ Erro ao carregar usuários disponíveis:', e);
        todosUsuariosDisponiveis = [];
    }
}

function toggleSelecaoUsuariosPermissoes() {
    const permiteTodos = document.getElementById('permiteVerTodos').checked;
    const divSelecao = document.getElementById('selecaoUsuariosPermissoes');
    
    if (permiteTodos) {
        divSelecao.style.display = 'none';
        usuariosVisualizarSelecionados = [];
        document.getElementById('contadorUsuariosSelecionadosPermissoes').textContent = '0';
    } else {
        divSelecao.style.display = 'block';
        renderizarUsuariosVisualizarPermissoes();
    }
}

function renderizarUsuariosVisualizarPermissoes() {
    const div = document.getElementById('checkUsuariosPermissoes');
    const idAtual = usuarioSelecionado?.idcliforemp;
    
    if (!todosUsuariosDisponiveis || todosUsuariosDisponiveis.length === 0) {
        div.innerHTML = '<p class="text-sm text-slate-400">Nenhum usuário disponível</p>';
        return;
    }
    
    // Filtra para não mostrar o próprio usuário
    const usuariosFiltrados = todosUsuariosDisponiveis.filter(u => 
        u.idcliforemp != idAtual
    );
    
    if (usuariosFiltrados.length === 0) {
        div.innerHTML = '<p class="text-sm text-slate-400">Nenhum outro usuário disponível</p>';
        return;
    }
    
    div.innerHTML = usuariosFiltrados.map(u => {
        const checked = usuariosVisualizarSelecionados.includes(u.idusuario);
        const status = u.inativo === 'S' ? ' (Inativo)' : '';
        return `
            <label class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                <input type="checkbox" value="${u.idusuario}" ${checked ? 'checked' : ''} 
                       class="w-4 h-4 text-purple-600 border-slate-300 rounded focus:ring-purple-500"
                       onchange="toggleUsuarioVisualizarPermissoes(${u.idusuario}, this.checked)">
                <span class="text-sm font-medium text-slate-700">${u.username}${status}</span>
                <span class="text-xs text-slate-400 ml-auto">ID: ${u.idcliforemp}</span>
            </label>
        `;
    }).join('');
    
    document.getElementById('contadorUsuariosSelecionadosPermissoes').textContent = usuariosVisualizarSelecionados.length;
}

function toggleUsuarioVisualizarPermissoes(id, checked) {
    if (checked) {
        if (!usuariosVisualizarSelecionados.includes(id)) {
            usuariosVisualizarSelecionados.push(id);
        }
    } else {
        usuariosVisualizarSelecionados = usuariosVisualizarSelecionados.filter(i => i !== id);
    }
    document.getElementById('contadorUsuariosSelecionadosPermissoes').textContent = usuariosVisualizarSelecionados.length;
}
async function carregarPermissoes() {
    const select = document.getElementById('selectUsuario');
    const opt = select.options[select.selectedIndex];
    
    if (!opt || !opt.value) {
        usuarioSelecionado = null;
        permissoesAtuais = [];
        document.getElementById('modulosGrid').innerHTML = `
            <div class="col-span-full text-center py-12 text-slate-400">
                <i class="fa-solid fa-arrow-up text-4xl mb-3 block"></i>
                <p>Selecione um usuário para configurar suas permissões</p>
            </div>
        `;
        document.getElementById('configAvancada').style.display = 'none';
        return;
    }
    
    try {
        showLoading('Carregando permissões...');
        
        // Carrega permissões normais
        const data = await apiFetch(`/admin/usuarios/${opt.value}/permissoes`, 'GET');
        
        // 🔥 Carrega visualizações de usuários
        const visData = await apiFetch(`/admin/usuarios/${opt.value}/visualizacao`, 'GET');
        
        Swal.close();
        
        usuarioSelecionado = {
            idcliforemp: data.usuario.idcliforemp,
            username: data.usuario.username,
            filiais: data.usuario.dash_filiais || '',
            gestores: data.usuario.dash_gestores || ''
        };
        
        permissoesAtuais = data.permissoes_atuais || [];
        todosModulos = data.todos_modulos || [];
        
        // 🔥 Carrega as visualizações
        const permiteTodos = visData.permite_ver_todos === true;
        document.getElementById('permiteVerTodos').checked = permiteTodos;
        
        usuariosVisualizarSelecionados = (visData.usuarios_visualizar || []).map(u => u.id);
        
        // Atualiza a interface de seleção
        toggleSelecaoUsuariosPermissoes();
        
        renderizarModulos();
        renderizarFiliaisGestores();
        document.getElementById('configAvancada').style.display = 'block';
        
    } catch (e) {
        Swal.close();
        showError('Erro', 'Falha ao carregar permissões: ' + e.message);
    }
}

function renderizarModulos() {
    const grid = document.getElementById('modulosGrid');
    
    if (todosModulos.length === 0) {
        grid.innerHTML = '<div class="col-span-full text-center py-8 text-slate-400">Nenhum módulo disponível</div>';
        return;
    }
    
    const coresMap = {
        'bg-emerald-50': { bg: 'bg-emerald-50', text: 'text-emerald-600', border: 'border-emerald-500', icon: 'text-emerald-600' },
        'bg-blue-50': { bg: 'bg-blue-50', text: 'text-blue-600', border: 'border-blue-500', icon: 'text-blue-600' },
        'bg-indigo-50': { bg: 'bg-indigo-50', text: 'text-indigo-600', border: 'border-indigo-500', icon: 'text-indigo-600' },
        'bg-sky-50': { bg: 'bg-sky-50', text: 'text-sky-600', border: 'border-sky-500', icon: 'text-sky-600' },
        'bg-slate-100': { bg: 'bg-slate-100', text: 'text-slate-600', border: 'border-slate-500', icon: 'text-slate-600' },
        'bg-amber-50': { bg: 'bg-amber-50', text: 'text-amber-600', border: 'border-amber-500', icon: 'text-amber-600' },
        'bg-rose-50': { bg: 'bg-rose-50', text: 'text-rose-600', border: 'border-rose-500', icon: 'text-rose-600' },
        'bg-purple-50': { bg: 'bg-purple-50', text: 'text-purple-600', border: 'border-purple-500', icon: 'text-purple-600' }
    };
    
    grid.innerHTML = todosModulos.map(mod => {
        const temPermissao = permissoesAtuais.includes(mod.slug);
        const cores = coresMap[mod.cor_bg] || coresMap['bg-slate-100'];
        
        return `
            <div class="bg-white p-5 rounded-2xl border-2 transition-all cursor-pointer shadow-sm hover:shadow-lg ${temPermissao ? cores.border : 'border-slate-200'}"
                 onclick="toggleModulo('${mod.slug}')">
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 ${cores.bg} ${cores.icon} rounded-xl flex items-center justify-center">
                        <i class="fa-solid ${mod.icon || 'fa-cube'}"></i>
                    </div>
                    <div class="flex-1">
                        <h5 class="font-bold text-slate-800 text-sm">${mod.nome}</h5>
                        <p class="text-xs text-slate-400 mt-1">${mod.descricao || ''}</p>
                    </div>
                    <div class="${temPermissao ? cores.icon : 'text-slate-300'}">
                        <i class="fa-solid ${temPermissao ? 'fa-check-circle' : 'fa-circle'} text-xl"></i>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

function toggleModulo(moduloSlug) {
    if (permissoesAtuais.includes(moduloSlug)) {
        permissoesAtuais = permissoesAtuais.filter(p => p !== moduloSlug);
    } else {
        permissoesAtuais.push(moduloSlug);
    }
    renderizarModulos();
}

function renderizarFiliaisGestores() {
    // Filiais
    const divFiliais = document.getElementById('checkFiliais');
    const filiaisDoUsuario = usuarioSelecionado.filiais ? usuarioSelecionado.filiais.split(',') : [];
    
    const todasFiliais = [
        { id: '1', nome: 'Filial 1 - Matriz' },
        { id: '6', nome: 'Filial 6 - Filial' }
    ];
    
    divFiliais.innerHTML = todasFiliais.map(f => {
        const checked = filiaisDoUsuario.includes(f.id);
        return `
            <label class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                <input type="checkbox" value="${f.id}" ${checked ? 'checked' : ''} 
                       class="w-4 h-4 text-purple-600 border-slate-300 rounded focus:ring-purple-500">
                <span class="text-sm font-medium text-slate-700">${f.nome}</span>
            </label>
        `;
    }).join('');
    
    // ✅ Gestores - Checkboxes com NOMES (não IDs)
    const divGestores = document.getElementById('checkGestores');
    const gestoresDoUsuario = usuarioSelecionado.gestores ? usuarioSelecionado.gestores.split(',').map(id => parseInt(id.trim())) : [];
    
    divGestores.innerHTML = gestoresDisponiveis.map(g => {
        const checked = gestoresDoUsuario.includes(g.idcliforemp);
        return `
            <label class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                <input type="checkbox" value="${g.idcliforemp}" ${checked ? 'checked' : ''} 
                       class="w-4 h-4 text-purple-600 border-slate-300 rounded focus:ring-purple-500">
                <span class="text-sm font-medium text-slate-700">${g.nome}</span>
            </label>
        `;
    }).join('');
}

async function salvarPermissoes() {
    if (!usuarioSelecionado) {
        showError('Erro', 'Selecione um usuário primeiro');
        return;
    }
    
    // Coletar filiais
    const checkboxesFiliais = document.querySelectorAll('#checkFiliais input[type="checkbox"]:checked');
    const filiais = Array.from(checkboxesFiliais).map(cb => cb.value).join(',');
    
    // Coletar gestores
    const checkboxesGestores = document.querySelectorAll('#checkGestores input[type="checkbox"]:checked');
    const gestores = Array.from(checkboxesGestores).map(cb => cb.value).join(',');
    
    // 🔥 Dados de visualização
    const permiteTodos = document.getElementById('permiteVerTodos')?.checked || false;
    const usuariosVisualizar = permiteTodos ? [] : usuariosVisualizarSelecionados;
    
    // Dados para permissões
    const data = {
        idcliforemp: usuarioSelecionado.idcliforemp,
        modulos: permissoesAtuais,
        dash_filiais: filiais,
        dash_gestores: gestores,
        permite_ver_usuarios: permiteTodos ? 'S' : 'N'
    };
    
    try {
        showLoading('Salvando permissões...');
        
        // Salva permissões normais
        const result = await apiFetch('/admin/permissoes', 'POST', data);
        
        // 🔥 Salva visualizações de usuários
        const visData = {
            idcliforemp: usuarioSelecionado.idcliforemp,
            permite_ver_todos: permiteTodos ? 'S' : 'N',
            usuarios_visualizar: usuariosVisualizar
        };
        const visResult = await apiFetch('/admin/usuarios/visualizacao', 'POST', visData);
        
        Swal.close();
        
        if (result.success && visResult.success) {
            showSuccess('Sucesso!', `Permissões de ${usuarioSelecionado.username} atualizadas`);
            carregarUsuarios();
        } else {
            showError('Erro', result.error || visResult.error || 'Falha ao salvar');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

// ✅ Verifica se veio um usuário pela URL (?user=15750)
function checkUrlUser() {
    const params = new URLSearchParams(window.location.search);
    const userId = params.get('user');
    
    if (userId) {
        // Aguarda a lista de usuários carregar e então seleciona
        const select = document.getElementById('selectUsuario');
        
        // Tenta selecionar imediatamente
        const option = Array.from(select.options).find(opt => opt.value === userId);
        if (option) {
            select.value = userId;
            carregarPermissoes();
        } else {
            // Se ainda não carregou, tenta de novo em 500ms
            setTimeout(() => {
                const opt = Array.from(select.options).find(o => o.value === userId);
                if (opt) {
                    select.value = userId;
                    carregarPermissoes();
                }
            }, 500);
        }
    }
}

// Modifica a inicialização para verificar URL
document.addEventListener('DOMContentLoaded', async () => {
    await carregarGestores();
    await carregarUsuarios();
	await carregarUsuariosDisponiveis(); 
    checkUrlUser();
});


</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>