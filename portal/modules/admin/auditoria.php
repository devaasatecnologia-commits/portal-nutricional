<?php
define('ADMIN_AREA', true);
$pageTitle = 'Auditoria de Acessos | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- Header -->
<div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-slate-100 text-slate-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-clipboard-list text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">AUDITORIA DE ACESSOS</h2>
            <span class="text-xs text-slate-400 font-medium">Logs de login e atividades</span>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-4 flex gap-3 flex-wrap">
    <input type="text" id="searchLog" placeholder="Buscar por usuário..." 
           onkeyup="filtrarLogs()"
           class="flex-1 min-w-[200px] px-4 py-2 border border-slate-200 rounded-xl text-sm">
    <select id="filtroAcao" onchange="filtrarLogs()" 
            class="px-4 py-2 border border-slate-200 rounded-xl text-sm">
        <option value="">Todas as ações</option>
        <option value="LOGIN">Login</option>
        <option value="LOGOUT">Logout</option>
        <option value="API">API</option>
    </select>
    <input type="date" id="filtroData" onchange="filtrarLogs()" 
           class="px-4 py-2 border border-slate-200 rounded-xl text-sm">
</div>

<!-- Tabela de Logs -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">ID</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Usuário</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Ação</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">IP</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Data/Hora</th>
                </tr>
            </thead>
            <tbody id="logsTable">
                <tr><td colspan="5" class="text-center py-8 text-slate-400">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...
                </td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
let todosLogs = [];

async function carregarLogs() {
    try {
        const data = await apiFetch('/admin/logs', 'GET');
        todosLogs = data.logs || [];
        filtrarLogs();
    } catch (e) {
        document.getElementById('logsTable').innerHTML = 
            '<tr><td colspan="5" class="text-center py-8 text-slate-400">Erro ao carregar logs</td></tr>';
    }
}

function filtrarLogs() {
    const search = document.getElementById('searchLog').value.toLowerCase();
    const acao = document.getElementById('filtroAcao').value;
    const data = document.getElementById('filtroData').value;
    
    let filtrados = todosLogs;
    
    if (search) {
        filtrados = filtrados.filter(l => 
            (l.username || '').toLowerCase().includes(search)
        );
    }
    
    if (acao) {
        filtrados = filtrados.filter(l => l.acao === acao);
    }
    
    if (data) {
        filtrados = filtrados.filter(l => l.created_at && l.created_at.startsWith(data));
    }
    
    const tbody = document.getElementById('logsTable');
    
    if (filtrados.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-slate-400">Nenhum log encontrado</td></tr>';
        return;
    }
    
    tbody.innerHTML = filtrados.slice(0, 100).map(l => `
        <tr class="border-b border-slate-100 hover:bg-slate-50">
            <td class="px-4 py-3 text-sm font-mono">${l.id}</td>
            <td class="px-4 py-3 font-semibold">${l.username || 'N/A'}</td>
            <td class="px-4 py-3">
                <span class="px-2 py-1 rounded-full text-[10px] font-bold ${l.acao === 'LOGIN' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600'}">
                    ${l.acao}
                </span>
            </td>
            <td class="px-4 py-3 text-xs font-mono">${l.ip_origem || '-'}</td>
            <td class="px-4 py-3 text-xs text-slate-500">${formatarData(l.created_at)}</td>
        </tr>
    `).join('');
}

document.addEventListener('DOMContentLoaded', carregarLogs);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>