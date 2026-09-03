<?php
define('ADMIN_AREA', true);
$pageTitle = 'Gerenciar Usuários | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- HEADER MOBILE FIXO -->
<div class="mobile-toolbar block lg:hidden fixed top-0 left-0 right-0 z-50 shadow-lg">
    <div class="flex items-center justify-between px-4 py-3">
        <a href="/portal/" class="flex items-center gap-2 no-underline">
            <i class="fa-solid fa-arrow-left text-lg"></i>
            <span class="text-sm font-bold">VOLTAR</span>
        </a>
        <div class="text-center">
            <span class="text-sm font-bold modulo-nome">USUÁRIOS</span>
        </div>
        <div class="clock font-mono text-sm font-bold" id="relogioMobile">00:00</div>
    </div>
</div>
<div class="mobile-spacer block lg:hidden h-14"></div>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">

    <!-- Header Desktop -->
    <div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/" class="btn-voltar hidden sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-users text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">GERENCIAR USUÁRIOS</h2>
                <span class="text-xs text-slate-400 font-medium">Controle de acesso ao sistema</span>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block mr-4">
                <div class="clock font-mono text-base lg:text-xl font-black" id="relogio">00:00:00</div>
                <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
            </div>
            <button onclick="toggleInativos()" id="btnToggleInativos" class="px-4 py-2 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all flex items-center gap-2 text-sm">
                <i class="fa-solid fa-eye"></i> <span id="textoToggle">Ver Inativos</span>
            </button>
            <button onclick="novoUsuario()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all flex items-center gap-2">
                <i class="fa-solid fa-plus"></i> Novo Usuário
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-4 flex gap-3 flex-wrap items-center">
        <input type="text" id="searchUsuario" placeholder="Buscar por nome ou username..." 
               onkeyup="filtrarUsuarios()"
               class="flex-1 min-w-[200px] px-4 py-2 border border-slate-200 rounded-xl text-sm">
        <span class="text-xs text-slate-400 font-medium">
            <i class="fa-solid fa-users mr-1"></i> <span id="contadorUsuarios">0</span> usuários
        </span>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Foto</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">ID</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Usuário</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Filiais</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Gestores</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Módulos</th>
                        <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody id="usuariosTable">
                    <tr><td colspan="8" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal de Edição -->
<div id="modalEditarUsuario" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModal()"></div>
        
        <div class="relative bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="bg-slate-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-white" id="modalTitulo">
                    <i class="fa-solid fa-user-edit mr-2"></i>Editar Usuário
                </h3>
                <button onclick="fecharModal()" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <form id="formEditarUsuario" class="space-y-4">
                    <input type="hidden" id="editIdCliforemp">
                    
                    <!-- Foto de Perfil -->
                    <div class="text-center mb-4">
                        <div class="relative inline-block">
                            <div class="w-24 h-24 rounded-2xl bg-slate-100 flex items-center justify-center overflow-hidden border-2 border-slate-200 mx-auto" id="fotoPreviewContainer">
                         <img id="fotoPreview" src="" class="w-full h-full object-cover" style="display:none;" onerror="this.style.display='none'; document.getElementById('fotoPlaceholder').style.display='flex';">
                                <div id="fotoPlaceholder" class="flex flex-col items-center justify-center">
                                    <i class="fa-solid fa-user text-3xl text-slate-400"></i>
                                </div>
                            </div>
                            <label for="inputFotoPerfil" class="absolute -bottom-2 -right-2 w-8 h-8 bg-[#375a4b] text-white rounded-lg flex items-center justify-center cursor-pointer hover:bg-[#4a7a67] transition-colors shadow-lg">
                                <i class="fa-solid fa-camera text-xs"></i>
                            </label>
                            <input type="file" id="inputFotoPerfil" accept="image/*" onchange="previewFotoPerfil(event)" class="hidden">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">Clique na câmera para alterar a foto</p>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Username</label>
                        <input type="text" id="editUsername" autocomplete="off" class="w-full p-3 border border-slate-200 rounded-xl text-sm bg-slate-50" readonly>
                    </div>
                    
                 <div class="grid grid-cols-2 gap-4">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nova Senha (deixe em branco para manter)</label>
        <input type="password" id="editSenha" autocomplete="new-password" class="w-full p-3 border border-slate-200 rounded-xl text-sm" placeholder="••••••••">
    </div>
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Confirmar Senha</label>
        <input type="password" id="editSenhaConfirm" autocomplete="new-password" class="w-full p-3 border border-slate-200 rounded-xl text-sm" placeholder="••••••••">
    </div>
</div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">
                            <i class="fa-solid fa-store mr-1"></i> Filiais Permitidas
                        </label>
                        <div class="flex gap-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" value="1" id="editFilial1" class="w-4 h-4 text-blue-600 rounded">
                                <span class="text-sm">Filial 1 - Matriz</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" value="6" id="editFilial6" class="w-4 h-4 text-blue-600 rounded">
                                <span class="text-sm">Filial 6 - Filial</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">
                            <i class="fa-solid fa-user-tie mr-1"></i> Gestores Vinculados
                        </label>
                        <div id="checkGestoresEdit" class="space-y-2 max-h-48 overflow-y-auto border border-slate-200 rounded-xl p-3">
                            <p class="text-sm text-slate-400">Carregando gestores...</p>
                        </div>
                    </div>
                    <!-- Dentro do modal, após os gestores -->
<div class="pt-4 border-t border-slate-100">
    <label class="flex items-center gap-3 cursor-pointer mb-3">
        <input type="checkbox" id="editPermiteVerTodos" 
               class="w-5 h-5 text-purple-600 border-slate-300 rounded focus:ring-purple-500"
               onchange="toggleSelecaoUsuarios()">
        <div>
            <span class="text-sm font-bold text-slate-700">Pode ver todos os usuários</span>
            <br>
            <span class="text-[10px] text-slate-400">Libera acesso a todos os usuários do sistema</span>
        </div>
    </label>
</div>

<div id="selecaoUsuariosEdit" style="display:none;">
    <div class="pt-2 border-t border-slate-100">
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">
            <i class="fa-solid fa-user-plus mr-1"></i> Selecione os usuários que pode visualizar
        </label>
        <div class="border border-slate-200 rounded-xl p-3 max-h-48 overflow-y-auto" id="checkUsuariosEdit">
            <p class="text-sm text-slate-400">Carregando usuários...</p>
        </div>
        <div class="mt-2 text-xs text-slate-400">
            <span id="contadorUsuariosSelecionados">0</span> usuários selecionados
        </div>
    </div>
</div>
                    <div class="pt-4 border-t border-slate-100 flex gap-3">
                        <button type="button" onclick="salvarEdicao()" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
                            <i class="fa-solid fa-save mr-2"></i>Salvar Alterações
                        </button>
                        <button type="button" onclick="fecharModal()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ======================================================================
// RELÓGIO
// ======================================================================
setInterval(() => {
    const agora = new Date();
    const horaFormatada = agora.toLocaleTimeString('pt-br');
    const dataFormatada = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
    const relogio = document.getElementById('relogio');
    const relogioMobile = document.getElementById('relogioMobile');
    const dataTopo = document.getElementById('data-topo');
    if (relogio) relogio.innerText = horaFormatada;
    if (relogioMobile) relogioMobile.innerText = horaFormatada;
    if (dataTopo) dataTopo.innerText = dataFormatada;
}, 1000);

// ======================================================================
// VARIÁVEIS GLOBAIS
// ======================================================================

let todosUsuarios = [];
let gestoresDisponiveis = [];
let mostrarInativos = false;
let fotoFile = null;
let editandoIdCliforemp = null;
let editandoIdUsuario = null;
let todosUsuariosDisponiveis = []; // 🔥 NOVA VARIÁVEL
let usuariosVisualizarSelecionados = []; // 🔥 NOVA VARIÁVEL

// ======================================================================
// FUNÇÕES AUXILIARES
// ======================================================================

function showLoading(title = 'Carregando...') {
    Swal.fire({
        title: title,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
}

function showToast(message, icon = 'success') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: message,
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true
    });
}

function showError(title, text) {
    Swal.fire({
        icon: 'error',
        title: title,
        text: text,
        position: 'top',
        confirmButtonColor: '#ef4444'
    });
}

async function confirmar(titulo, texto, botaoConfirmar) {
    return Swal.fire({
        title: titulo,
        text: texto,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: botaoConfirmar || 'Sim',
        cancelButtonText: 'Cancelar',
        position: 'top'
    });
}

function formatarData(data) {
    if (!data) return '---';
    try {
        const d = new Date(data);
        return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    } catch {
        return data;
    }
}

// ======================================================================
// FOTO DE PERFIL
// ======================================================================

function previewFotoPerfil(event) {
    const file = event.target.files[0];
    if (!file) return;
    
    // Valida tamanho (máx 5MB)
    if (file.size > 5 * 1024 * 1024) {
        showError('Erro', 'A foto deve ter no máximo 5MB.');
        event.target.value = '';
        return;
    }
    
    // Valida tipo
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        showError('Erro', 'Tipo não permitido. Use JPEG, PNG ou WEBP.');
        event.target.value = '';
        return;
    }
    
    fotoFile = file;
    const reader = new FileReader();
    reader.onload = (e) => {
        const preview = document.getElementById('fotoPreview');
        const placeholder = document.getElementById('fotoPlaceholder');
        if (preview) {
            preview.src = e.target.result;
            preview.style.display = 'block';  // ✅ Mostra a imagem
        }
        if (placeholder) placeholder.style.display = 'none';  // ✅ Esconde placeholder
    };
    reader.readAsDataURL(file);
}

async function uploadFoto(idcliforemp, idusuario) {
    if (!fotoFile) return null;
    
    const formData = new FormData();
    formData.append('foto', fotoFile);
    formData.append('idusuario', idusuario);
    formData.append('idcliforemp', idcliforemp);
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch('https://api.nutricionalbr.com/v1/admin/upload-foto', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token },
            body: formData
        });
        
        const data = await resp.json();
     
        
        if (data.success) {
            return data.foto_url;
        } else {
            showError('Erro', data.error || 'Falha ao enviar foto');
            return null;
        }
    } catch (e) {
        console.error('Erro upload foto:', e);
        showError('Erro', 'Falha de conexão ao enviar foto');
        return null;
    }
}

function getFotoUrl(fotoPerfil) {
    if (!fotoPerfil) return null;
    if (fotoPerfil.startsWith('http')) return fotoPerfil;
    return 'https://api.nutricionalbr.com/' + fotoPerfil;
}

// ======================================================================
// GESTORES
// ======================================================================

async function carregarGestores() {
    try {
        const resp = await apiFetch('/admin/gestores', 'GET');
     
        gestoresDisponiveis = resp.gestores || [];
       
    } catch (e) {
        console.warn('⚠️ Erro ao carregar gestores, usando fallback:', e.message);
        gestoresDisponiveis = [
            { idcliforemp: 13878, nome: 'ADRIANO ROGERIO ELEODORO' },
            { idcliforemp: 15520, nome: 'MICHEL PLATINI DE SOUZA AGUIAR' },
            { idcliforemp: 5297, nome: 'ROBSON DE ALMEIDA BECKER' },
            { idcliforemp: 11371, nome: 'TALES FERNANDO DE JESUS BINDE' },
            { idcliforemp: 11258, nome: 'TIAGO GUSTAVO HERRMANN' }
        ];
    }
}

// ======================================================================
// USUÁRIOS
// ======================================================================

async function carregarUsuarios() {
    try {
        showLoading('Carregando usuários...');
        const data = await apiFetch('/admin/usuarios', 'GET');
        todosUsuarios = data.usuarios || [];
        Swal.close();
        filtrarUsuarios();
    } catch (e) {
        Swal.close();
        showError('Erro', 'Falha ao carregar usuários: ' + e.message);
    }
}

function filtrarUsuarios() {
    const search = document.getElementById('searchUsuario')?.value?.toLowerCase() || '';
    let filtrados = todosUsuarios;
    
    // Filtro de ativos/inativos
    if (!mostrarInativos) {
        filtrados = filtrados.filter(u => u.inativo === 'N');
    }
    
    // Busca textual
    if (search) {
        filtrados = filtrados.filter(u => 
            (u.username || '').toLowerCase().includes(search)
        );
    }
    
    const contador = document.getElementById('contadorUsuarios');
    if (contador) contador.textContent = filtrados.length;
    
    renderizarUsuarios(filtrados);
}

function toggleInativos() {
    mostrarInativos = !mostrarInativos;
    const btn = document.getElementById('btnToggleInativos');
    const texto = document.getElementById('textoToggle');
    
    if (mostrarInativos) {
        btn.classList.remove('bg-slate-200', 'text-slate-600');
        btn.classList.add('bg-amber-100', 'text-amber-700');
        texto.textContent = 'Mostrar Ativos';
    } else {
        btn.classList.add('bg-slate-200', 'text-slate-600');
        btn.classList.remove('bg-amber-100', 'text-amber-700');
        texto.textContent = 'Ver Inativos';
    }
    
    filtrarUsuarios();
}

function getNomesGestores(idsString) {
    if (!idsString) return '-';
    const ids = idsString.split(',').map(id => parseInt(id.trim()));
    const nomes = ids.map(id => {
        const gestor = gestoresDisponiveis.find(g => g.idcliforemp == id);
        return gestor ? gestor.nome.split(' ')[0] : `ID:${id}`;
    });
    return nomes.join(', ');
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

function toggleSelecaoUsuarios() {
    const permiteTodos = document.getElementById('editPermiteVerTodos').checked;
    const divSelecao = document.getElementById('selecaoUsuariosEdit');
    
    if (permiteTodos) {
        divSelecao.style.display = 'none';
        usuariosVisualizarSelecionados = [];
        document.getElementById('contadorUsuariosSelecionados').textContent = '0';
    } else {
        divSelecao.style.display = 'block';
        renderizarUsuariosVisualizarEdit();
    }
}

function renderizarUsuariosVisualizarEdit() {
    const div = document.getElementById('checkUsuariosEdit');
    const idAtual = editandoIdCliforemp;
    
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
                       onchange="toggleUsuarioVisualizarEdit(${u.idusuario}, this.checked)">
                <span class="text-sm font-medium text-slate-700">${u.username}${status}</span>
                <span class="text-xs text-slate-400 ml-auto">ID: ${u.idcliforemp}</span>
            </label>
        `;
    }).join('');
    
    document.getElementById('contadorUsuariosSelecionados').textContent = usuariosVisualizarSelecionados.length;
}

function toggleUsuarioVisualizarEdit(id, checked) {
    if (checked) {
        if (!usuariosVisualizarSelecionados.includes(id)) {
            usuariosVisualizarSelecionados.push(id);
        }
    } else {
        usuariosVisualizarSelecionados = usuariosVisualizarSelecionados.filter(i => i !== id);
    }
    document.getElementById('contadorUsuariosSelecionados').textContent = usuariosVisualizarSelecionados.length;
}

// ======================================================================
// RENDERIZAÇÃO
// ======================================================================

function renderizarUsuarios(lista) {
    const tbody = document.getElementById('usuariosTable');
    if (!tbody) return;
    
    if (lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Nenhum usuário encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = lista.map(u => {
        const adminBadge = u.nivel_admin 
            ? `<span class="px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-[10px] font-bold ml-1">${u.nivel_admin}</span>` 
            : '';
        
        const gestoresNomes = getNomesGestores(u.dash_gestores);
        const fotoUrl = getFotoUrl(u.foto_perfil);
        
        return `
        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
            <td class="px-4 py-3">
                <div class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center overflow-hidden">
                    ${fotoUrl 
                        ? `<img src="${fotoUrl}" class="w-full h-full object-cover" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                        : ''}
                    <span class="text-slate-500 font-bold text-sm" style="${fotoUrl ? 'display:none' : 'display:flex'}">${(u.username || 'U').substring(0, 2).toUpperCase()}</span>
                </div>
            </td>
            <td class="px-4 py-3 font-mono text-sm">${u.idcliforemp}</td>
            <td class="px-4 py-3">
                <b>${u.username}</b>${adminBadge}
            </td>
            <td class="px-4 py-3 text-xs">${u.dash_filiais || '-'}</td>
            <td class="px-4 py-3 text-xs" title="${u.dash_gestores || ''}">${gestoresNomes}</td>
            <td class="px-4 py-3 text-xs">
                <div class="flex flex-wrap gap-1">
                    ${(u.permissoes || '').split(',').filter(p => p.trim()).slice(0, 3).map(p => 
                        `<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold">${p.trim()}</span>`
                    ).join('')}
                    ${(u.permissoes || '').split(',').filter(p => p.trim()).length > 3 ? 
                        `<span class="px-2 py-0.5 bg-slate-100 text-slate-400 rounded-full text-[10px] font-bold">+${(u.permissoes || '').split(',').filter(p => p.trim()).length - 3}</span>` 
                        : ''}
                </div>
            </td>
            <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold ${u.inativo === 'N' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                    ${u.inativo === 'N' ? 'Ativo' : 'Inativo'}
                </span>
            </td>
            <td class="px-4 py-3 text-center">
                <button onclick="window.location.href='permissoes.php?user=${u.idcliforemp}'" 
                        class="px-3 py-1.5 border border-purple-300 text-purple-600 rounded-lg text-sm hover:bg-purple-500 hover:text-white transition-all mr-1"
                        title="Permissões">
                    <i class="fa-solid fa-lock"></i>
                </button>
                <button onclick="editarUsuario('${u.idcliforemp}')" 
                        class="px-3 py-1.5 border border-slate-300 text-slate-600 rounded-lg text-sm hover:bg-slate-700 hover:text-white transition-all mr-1"
                        title="Editar">
                    <i class="fa-solid fa-edit"></i>
                </button>
                <button onclick="toggleUsuario('${u.idcliforemp}', '${u.inativo}', '${u.nivel_admin || ''}')" 
                        class="px-3 py-1.5 border rounded-lg text-sm transition-all ${u.inativo === 'N' ? 'border-rose-300 text-rose-600 hover:bg-rose-500 hover:text-white' : 'border-emerald-300 text-emerald-600 hover:bg-emerald-500 hover:text-white'}"
                        title="${u.inativo === 'N' ? 'Desativar' : 'Ativar'}">
                    <i class="fa-solid ${u.inativo === 'N' ? 'fa-ban' : 'fa-check'}"></i>
                </button>
            </td>
        </tr>`;
    }).join('');
}

// ======================================================================
// MODAL DE EDIÇÃO
// ======================================================================

function abrirModal() {
    const modal = document.getElementById('modalEditarUsuario');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function fecharModal() {
    const modal = document.getElementById('modalEditarUsuario');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    // Limpar estado
    fotoFile = null;
    editandoIdCliforemp = null;
    editandoIdUsuario = null;
    const inputFoto = document.getElementById('inputFotoPerfil');
    if (inputFoto) inputFoto.value = '';
    
    // Resetar preview da foto
    const fotoPreview = document.getElementById('fotoPreview');
    const fotoPlaceholder = document.getElementById('fotoPlaceholder');
    if (fotoPreview) {
        fotoPreview.src = '';
        fotoPreview.style.display = 'none';
    }
    if (fotoPlaceholder) {
        fotoPlaceholder.style.display = 'flex';
    }
}

async function editarUsuario(idcliforemp) {
    try {
        // 1. Busca dados do usuário
        const data = await apiFetch('/admin/usuarios', 'GET');
        const usuario = (data.usuarios || []).find(u => u.idcliforemp == idcliforemp);
        
        if (!usuario) {
            showError('Erro', 'Usuário não encontrado');
            return;
        }
        
        // 2. Busca visualizações
        const visData = await apiFetch(`/admin/usuarios/${idcliforemp}/visualizacao`, 'GET');
        
        // 3. Guarda IDs
        editandoIdCliforemp = usuario.idcliforemp;
        editandoIdUsuario = usuario.idusuario;
        
        // 4. Resetar foto
        fotoFile = null;
        const inputFoto = document.getElementById('inputFotoPerfil');
        if (inputFoto) inputFoto.value = '';
        
        // 5. Preencher campos básicos
        document.getElementById('editIdCliforemp').value = usuario.idcliforemp || '';
        document.getElementById('editUsername').value = usuario.username || '';
        document.getElementById('editSenha').value = '';
        document.getElementById('editSenhaConfirm').value = '';
        
        // 6. Carregar visualizações
        const permiteTodos = visData.permite_ver_todos === true;
        document.getElementById('editPermiteVerTodos').checked = permiteTodos;
        usuariosVisualizarSelecionados = (visData.usuarios_visualizar || []).map(u => u.id);
        toggleSelecaoUsuarios();
        
        // 7. Carregar foto
        const fotoPreview = document.getElementById('fotoPreview');
        const fotoPlaceholder = document.getElementById('fotoPlaceholder');
        if (usuario.foto_perfil) {
            const fotoUrl = getFotoUrl(usuario.foto_perfil);
            if (fotoPreview) {
                fotoPreview.src = fotoUrl;
                fotoPreview.style.display = 'block';
                fotoPreview.onerror = function() {
                    fotoPreview.style.display = 'none';
                    if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
                };
            }
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'none';
        } else {
            if (fotoPreview) fotoPreview.style.display = 'none';
            if (fotoPlaceholder) fotoPlaceholder.style.display = 'flex';
        }
        
        // 8. Carregar filiais
        const filiais = (usuario.dash_filiais || '').split(',');
        document.getElementById('editFilial1').checked = filiais.includes('1');
        document.getElementById('editFilial6').checked = filiais.includes('6');
        
        // 9. 🔥 CARREGAR GESTORES
        if (gestoresDisponiveis.length === 0) {
            await carregarGestores();
        }
        
        const gestoresUsuario = (usuario.dash_gestores || '').split(',').map(id => parseInt(id.trim())).filter(id => !isNaN(id));
        const divGestores = document.getElementById('checkGestoresEdit');
        
        if (divGestores && gestoresDisponiveis.length > 0) {
            divGestores.innerHTML = gestoresDisponiveis.map(g => {
                const checked = gestoresUsuario.includes(g.idcliforemp);
                return `
                    <label class="flex items-center gap-3 p-2 hover:bg-slate-50 rounded-lg cursor-pointer">
                        <input type="checkbox" value="${g.idcliforemp}" ${checked ? 'checked' : ''} 
                               class="w-4 h-4 text-purple-600 border-slate-300 rounded focus:ring-purple-500">
                        <span class="text-sm font-medium text-slate-700">${g.nome}</span>
                    </label>
                `;
            }).join('');
        } else if (divGestores) {
            divGestores.innerHTML = '<p class="text-sm text-slate-400">Nenhum gestor disponível</p>';
        }
        
        // 10. Título do modal
        const titulo = document.getElementById('modalTitulo');
        if (titulo) {
            titulo.innerHTML = '<i class="fa-solid fa-user-edit mr-2"></i>Editar: ' + usuario.username;
        }
        
        // 11. Abrir modal
        abrirModal();
        
    } catch (e) {
        console.error('❌ Erro ao editar usuário:', e);
        showError('Erro', 'Falha ao carregar dados do usuário');
    }
}
// ======================================================================
// SALVAR EDIÇÃO
// ======================================================================

async function salvarEdicao() {
    const idcliforemp = parseInt(document.getElementById('editIdCliforemp')?.value) || editandoIdCliforemp;
    const idusuario = editandoIdUsuario;
    const senha = document.getElementById('editSenha')?.value || '';
    const senhaConfirm = document.getElementById('editSenhaConfirm')?.value || '';
    
    // Validar senha
    if (senha && senha !== senhaConfirm) {
        showError('Erro', 'As senhas não conferem');
        return;
    }
    
    if (senha && senha.length < 3) {
        showError('Erro', 'A senha deve ter pelo menos 3 caracteres');
        return;
    }
    
    // Upload da foto primeiro (se houver)
    if (fotoFile) {
        showLoading('Enviando foto...');
        const fotoUrl = await uploadFoto(idcliforemp, idusuario);
        Swal.close();
        
        if (!fotoUrl) {
            const continuar = await confirmar(
                'Continuar sem foto?',
                'A foto não foi enviada. Deseja salvar as outras alterações mesmo assim?',
                'Sim, continuar'
            );
            if (!continuar.isConfirmed) return;
        }
    }
    
    // Coletar dados
    const filiais = [];
    if (document.getElementById('editFilial1')?.checked) filiais.push('1');
    if (document.getElementById('editFilial6')?.checked) filiais.push('6');
    
    const checkboxesGestores = document.querySelectorAll('#checkGestoresEdit input[type="checkbox"]:checked');
    const gestores = Array.from(checkboxesGestores).map(cb => cb.value).join(',');
    
    // 🔥 Dados de visualização
    const permiteTodos = document.getElementById('editPermiteVerTodos')?.checked || false;
    const usuariosVisualizar = permiteTodos ? [] : usuariosVisualizarSelecionados;
    
    // Dados para editar usuário
    const data = {
        idcliforemp: idcliforemp,
        dash_filiais: filiais.join(','),
        dash_gestores: gestores,
        permite_ver_usuarios: permiteTodos ? 'S' : 'N'
    };
    
    if (senha) {
        data.senha = senha;
    }
    
    try {
        showLoading('Salvando...');
        
        // 🔥 Salva edição do usuário
        const result = await apiFetch('/admin/usuarios/editar', 'POST', data);
        
        // 🔥 Salva visualizações
        const visData = {
            idcliforemp: idcliforemp,
            permite_ver_todos: permiteTodos ? 'S' : 'N',
            usuarios_visualizar: usuariosVisualizar
        };
        const visResult = await apiFetch('/admin/usuarios/visualizacao', 'POST', visData);
        
        Swal.close();
        
        if (result.success && visResult.success) {
            showToast('Usuário atualizado com sucesso!');
            fecharModal();
            await carregarUsuarios();
        } else {
            showError('Erro', result.error || visResult.error || 'Falha ao salvar');
        }
    } catch (e) {
        Swal.close();
        console.error('❌ Erro ao salvar:', e);
        showError('Erro', e.message || 'Falha de conexão');
    }
}

// ======================================================================
// ATIVAR/DESATIVAR USUÁRIO
// ======================================================================

async function toggleUsuario(id, statusAtual, nivelAdmin) {
    if (nivelAdmin) {
        showError('Ação Bloqueada', `Não é possível desativar um administrador (${nivelAdmin}). Remova o acesso admin primeiro.`);
        return;
    }
    
    const acao = statusAtual === 'N' ? 'desativar' : 'ativar';
    const result = await confirmar(
        `${acao.charAt(0).toUpperCase() + acao.slice(1)} Usuário?`,
        `Tem certeza que deseja ${acao} este usuário?`,
        `Sim, ${acao}`
    );
    
    if (!result.isConfirmed) return;
    
    try {
        showLoading('Processando...');
        const resp = await apiFetch(`/admin/usuarios/${id}/toggle`, 'POST');
        Swal.close();
        
        if (resp.success) {
            showToast(`Usuário ${acao}do com sucesso!`);
            await carregarUsuarios();
        } else {
            showError('Erro', resp.error || 'Falha ao processar');
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

// ======================================================================
// NOVO USUÁRIO
// ======================================================================

function novoUsuario() {
    showToast('Funcionalidade em desenvolvimento', 'info');
}

// ======================================================================
// INICIALIZAÇÃO
// ======================================================================

document.addEventListener('DOMContentLoaded', async () => {
  
    await carregarGestores();
    await carregarUsuarios();
    await carregarUsuariosDisponiveis();
    // Fechar modal com ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            fecharModal();
        }
    });
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>