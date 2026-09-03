<?php
define('ADMIN_AREA', true);
$pageTitle = 'Permissões por Setor | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-layer-group text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">PERMISSÕES POR SETOR</h2>
            <span class="text-xs text-slate-400 font-medium">Libere módulos por setor de atuação</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    
    <!-- Coluna 1: Usuário -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
            <i class="fa-solid fa-user mr-2"></i>Selecione o Usuário
        </label>
        
        <input type="text" id="searchUser" placeholder="Buscar usuário..." 
               onkeyup="filtrarUsuarios()"
               class="w-full p-3 border border-slate-200 rounded-xl text-sm mb-3">
        
        <div id="listaUsuarios" class="space-y-1 max-h-[500px] overflow-y-auto">
            <p class="text-sm text-slate-400 text-center py-4">Carregando...</p>
        </div>
    </div>
    
    <!-- Coluna 2: Setores -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
            <i class="fa-solid fa-layer-group mr-2"></i>Setores Disponíveis
        </label>
        
        <div id="listaSetores" class="space-y-2">
            <p class="text-sm text-slate-400 text-center py-4">Selecione um usuário primeiro</p>
        </div>
        
        <button onclick="salvarPorSetor()" id="btnSalvarSetor" 
                class="w-full mt-4 px-4 py-3 bg-purple-600 text-white rounded-xl font-bold hover:bg-purple-700 transition-all disabled:opacity-50 disabled:cursor-not-allowed" disabled>
            <i class="fa-solid fa-save mr-2"></i>Salvar Permissões
        </button>
    </div>
    
    <!-- Coluna 3: Módulos do Setor -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">
            <i class="fa-solid fa-cubes mr-2"></i>Módulos do Setor
        </label>
        
        <div id="listaModulosSetor" class="space-y-2">
            <p class="text-sm text-slate-400 text-center py-4">Clique em um setor para ver os módulos</p>
        </div>
    </div>
</div>

<script>
let usuarioSelecionado = null;
let todosUsuarios = [];
let todosSetores = [];
let setoresSelecionados = [];

async function carregarDados() {
    try {
        const [respUsuarios, respSetores] = await Promise.all([
            apiFetch('/admin/usuarios', 'GET'),
            apiFetch('/admin/setores', 'GET')
        ]);
        
        todosUsuarios = (respUsuarios.usuarios || []).filter(u => u.inativo === 'N');
        todosSetores = respSetores.setores || [];
        
        filtrarUsuarios();
    } catch (e) {
        showError('Erro', 'Falha ao carregar dados');
    }
}

function filtrarUsuarios() {
    const search = document.getElementById('searchUser').value.toLowerCase();
    let filtrados = todosUsuarios;
    
    if (search) {
        filtrados = filtrados.filter(u => u.username.toLowerCase().includes(search));
    }
    
    const div = document.getElementById('listaUsuarios');
    
    if (filtrados.length === 0) {
        div.innerHTML = '<p class="text-sm text-slate-400 text-center py-4">Nenhum usuário encontrado</p>';
        return;
    }
    
    div.innerHTML = filtrados.map(u => {
        const isSelected = usuarioSelecionado && usuarioSelecionado.idcliforemp === u.idcliforemp;
        const adminBadge = u.nivel_admin ? ` <span class="text-[10px] text-amber-600">[${u.nivel_admin}]</span>` : '';
        
        return `
            <div onclick="selecionarUsuario(${u.idcliforemp})" 
                 class="p-3 rounded-xl cursor-pointer transition-all border-2 ${isSelected ? 'border-purple-500 bg-purple-50' : 'border-transparent hover:bg-slate-50'}">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="font-semibold text-sm">${u.username}${adminBadge}</span>
                        <br><small class="text-slate-400">ID: ${u.idcliforemp}</small>
                    </div>
                    ${isSelected ? '<i class="fa-solid fa-check-circle text-purple-500"></i>' : ''}
                </div>
            </div>
        `;
    }).join('');
}

async function selecionarUsuario(idcliforemp) {
    usuarioSelecionado = todosUsuarios.find(u => u.idcliforemp == idcliforemp);
    if (!usuarioSelecionado) return;
    
    // Destaca na lista
    filtrarUsuarios();
    
    // Carrega permissões atuais do usuário
    try {
        const data = await apiFetch(`/admin/usuarios/${idcliforemp}/permissoes`, 'GET');
        const permissoesAtuais = data.permissoes_atuais || [];
        
        // Marca os setores que o usuário já tem
        renderizarSetores(permissoesAtuais);
        document.getElementById('btnSalvarSetor').disabled = false;
    } catch (e) {
        showError('Erro', 'Falha ao carregar permissões do usuário');
    }
}

function renderizarSetores(permissoesAtuais) {
    const div = document.getElementById('listaSetores');
    setoresSelecionados = [];
    
    // Determina quais setores o usuário já tem
    todosSetores.forEach(setor => {
        const modulosDoSetor = (setor.modulos_slugs || '').split(',').filter(s => s);
        const todosTem = modulosDoSetor.length > 0 && modulosDoSetor.every(slug => permissoesAtuais.includes(slug));
        
        if (todosTem) {
            setoresSelecionados.push(setor.slug);
        }
    });
    
    div.innerHTML = todosSetores.map(setor => {
        const isSelected = setoresSelecionados.includes(setor.slug);
        const modulosDoSetor = (setor.modulos_slugs || '').split(',').filter(s => s).length;
        
        return `
            <div onclick="toggleSetor('${setor.slug}', this)" 
                 class="p-4 rounded-xl cursor-pointer transition-all border-2 ${isSelected ? 'border-purple-500 bg-purple-50' : 'border-slate-200 hover:border-slate-300'}">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <div class="w-8 h-8 ${setor.cor_bg} ${setor.cor_texto} rounded-lg flex items-center justify-center">
                            <i class="fas ${setor.icone} text-sm"></i>
                        </div>
                        <span class="font-bold text-sm">${setor.nome}</span>
                    </div>
                    <i class="fa-solid ${isSelected ? 'fa-check-circle text-purple-500' : 'fa-circle text-slate-300'} text-lg"></i>
                </div>
                <p class="text-xs text-slate-400">${modulosDoSetor} módulo(s)</p>
                
                <button onclick="event.stopPropagation(); verModulosSetor(${setor.id}, '${setor.nome}')" 
                        class="mt-2 text-[10px] text-purple-600 hover:text-purple-800 font-bold uppercase">
                    <i class="fa-solid fa-eye mr-1"></i>Ver módulos
                </button>
            </div>
        `;
    }).join('');
}

function toggleSetor(slug, el) {
    if (setoresSelecionados.includes(slug)) {
        setoresSelecionados = setoresSelecionados.filter(s => s !== slug);
    } else {
        setoresSelecionados.push(slug);
    }
    
    // Re-renderiza
    renderizarSetores([]);
}

async function verModulosSetor(setorId, nomeSetor) {
    try {
        const data = await apiFetch(`/admin/setores/${setorId}/modulos`, 'GET');
        const modulos = data.modulos || [];
        
        const div = document.getElementById('listaModulosSetor');
        div.innerHTML = `
            <h5 class="font-bold text-sm text-slate-700 mb-3">${nomeSetor} (${modulos.length})</h5>
            ${modulos.map(m => `
                <div class="flex items-center gap-2 p-2 rounded-lg bg-slate-50">
                    <div class="w-6 h-6 ${m.cor_bg} ${m.cor_text} rounded flex items-center justify-center">
                        <i class="fas ${m.icon} text-[10px]"></i>
                    </div>
                    <span class="text-xs font-medium">${m.nome}</span>
                </div>
            `).join('')}
        `;
    } catch (e) {
        console.error(e);
    }
}

async function salvarPorSetor() {
    if (!usuarioSelecionado) return;
    
    try {
        showLoading('Salvando...');
        const result = await apiFetch('/admin/permissoes-por-setor', 'POST', {
            idcliforemp: usuarioSelecionado.idcliforemp,
            setores: setoresSelecionados
        });
        Swal.close();
        
        if (result.success) {
            showSuccess('Sucesso!', `Permissões de ${usuarioSelecionado.username} atualizadas por setor`);
        } else {
            showError('Erro', result.error || 'Falha ao salvar');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

document.addEventListener('DOMContentLoaded', carregarDados);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>