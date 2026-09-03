<?php
define('ADMIN_AREA', true);
$pageTitle = 'API Tokens | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- Header -->
<div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-key text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">API TOKENS</h2>
            <span class="text-xs text-slate-400 font-medium">Gerencie tokens de acesso e clientes</span>
        </div>
    </div>
    <button onclick="novoToken()" class="px-4 py-2 bg-amber-600 text-white rounded-xl font-bold hover:bg-amber-700 transition-all flex items-center gap-2">
        <i class="fa-solid fa-plus"></i> Novo Token
    </button>
</div>

<!-- Tokens Existentes -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="p-5 border-b border-slate-100">
        <h5 class="text-sm font-bold text-slate-700">
            <i class="fa-solid fa-list mr-2"></i>Tokens Ativos
        </h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Token</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Permissões</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Criado</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Expira</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody id="tokensTable">
                <tr><td colspan="7" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Logs de Uso -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h5 class="text-sm font-bold text-slate-700">
            <i class="fa-solid fa-chart-bar mr-2"></i>Últimas Requisições
        </h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Endpoint</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Método</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Data/Hora</th>
                </tr>
            </thead>
            <tbody id="logsTable">
                <tr><td colspan="5" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
async function carregarTokens() {
    try {
        const data = await apiFetch('/admin/api-tokens', 'GET');
        
        // Tokens
        const tbody = document.getElementById('tokensTable');
        if (data.tokens && data.tokens.length > 0) {
            tbody.innerHTML = data.tokens.map(t => `
                <tr class="border-b border-slate-100 hover:bg-slate-50">
                    <td class="px-4 py-3 font-semibold">${t.nome_cliente || 'N/A'}</td>
                    <td class="px-4 py-3">
                        <code class="text-xs bg-slate-100 px-2 py-1 rounded">${t.token_prefixo}...</code>
                        <button onclick="navigator.clipboard.writeText('${t.token}'); showToast('Token copiado!', 'info')" class="ml-2 text-slate-400 hover:text-amber-500">
                            <i class="fa-regular fa-copy"></i>
                        </button>
                    </td>
                    <td class="px-4 py-3 text-xs">
                        <div class="flex flex-wrap gap-1">
                            ${(t.permissoes_escopo || '[]').replace(/[\[\]"]/g, '').split(',').map(p => 
                                `<span class="px-2 py-0.5 bg-slate-100 text-slate-600 rounded-full text-[10px] font-bold">${p.trim() || 'Nenhum'}</span>`
                            ).join('')}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-xs text-slate-500">${formatarData(t.created_at)}</td>
                    <td class="px-4 py-3 text-xs">${t.expira_em ? formatarData(t.expira_em) : '<span class="text-emerald-600 font-bold">Nunca</span>'}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-1 rounded-full text-[10px] font-bold ${t.ativo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                            ${t.ativo ? 'Ativo' : 'Inativo'}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button onclick="revogarToken(${t.id})" class="px-3 py-1.5 border border-rose-300 text-rose-600 rounded-lg text-sm hover:bg-rose-500 hover:text-white transition-all">
                            <i class="fa-solid fa-ban"></i> Revogar
                        </button>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-8 text-slate-400">Nenhum token cadastrado</td></tr>';
        }
        
        // Logs
        const tbodyLogs = document.getElementById('logsTable');
        if (data.logs && data.logs.length > 0) {
            tbodyLogs.innerHTML = data.logs.map(l => `
                <tr class="border-b border-slate-100 text-sm">
                    <td class="px-4 py-2 font-medium">${l.cliente || '-'}</td>
                    <td class="px-4 py-2 font-mono text-xs">${l.endpoint}</td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold ${l.metodo === 'GET' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700'}">${l.metodo}</span>
                    </td>
                    <td class="px-4 py-2">
                        <span class="px-2 py-1 rounded-full text-[10px] font-bold ${l.status_http >= 400 ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700'}">${l.status_http}</span>
                    </td>
                    <td class="px-4 py-2 text-xs text-slate-500">${formatarData(l.created_at)}</td>
                </tr>
            `).join('');
        } else {
            tbodyLogs.innerHTML = '<tr><td colspan="5" class="text-center py-8 text-slate-400">Nenhum log recente</td></tr>';
        }
        
    } catch (e) {
        showError('Erro', 'Falha ao carregar tokens: ' + e.message);
    }
}

function novoToken() {
    Swal.fire({
        title: 'Novo Token API',
        html: `
            <div class="text-left space-y-4">
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-1">Nome do Cliente *</label>
                    <input type="text" id="tokenCliente" class="w-full p-3 border border-slate-300 rounded-xl" placeholder="Ex: Sistema ERP">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-1">Escopos (separados por vírgula)</label>
                    <input type="text" id="tokenEscopos" class="w-full p-3 border border-slate-300 rounded-xl" 
                           value="consulta_pedidos,rastreio,boleto,nf" placeholder="Ex: consulta_pedidos,boleto">
                    <small class="text-slate-400">Disponíveis: consulta_pedidos, rastreio, boleto, nf</small>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-600 mb-1">Dias até expirar (0 = nunca)</label>
                    <input type="number" id="tokenExpira" class="w-full p-3 border border-slate-300 rounded-xl" value="365" min="0">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-key mr-2"></i>Gerar Token',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#f59e0b',
        preConfirm: async () => {
            const cliente = document.getElementById('tokenCliente').value;
            const escopos = document.getElementById('tokenEscopos').value;
            const expira = document.getElementById('tokenExpira').value;
            
            if (!cliente) {
                Swal.showValidationMessage('Nome do cliente é obrigatório');
                return false;
            }
            
            try {
                const result = await apiFetch('/admin/api-tokens', 'POST', {
                    nome_cliente: cliente,
                    permissoes: escopos.split(',').map(s => s.trim()).filter(s => s),
                    dias_expirar: parseInt(expira)
                });
                
                if (result.success) return result;
                throw new Error(result.error || 'Erro ao gerar token');
            } catch (e) {
                Swal.showValidationMessage(e.message);
                return false;
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            Swal.fire({
                title: 'Token Gerado!',
                html: `
                    <p class="mb-3">Copie o token abaixo. <b class="text-rose-600">Ele não será mostrado novamente!</b></p>
                    <div class="bg-slate-100 p-4 rounded-xl font-mono text-sm break-all select-all cursor-pointer" 
                         onclick="navigator.clipboard.writeText('${result.value.token}'); showToast('Token copiado!')">
                        ${result.value.token}
                    </div>
                    <p class="text-xs text-slate-400 mt-2">Clique no token para copiar</p>
                `,
                icon: 'success',
                confirmButtonColor: '#274036'
            });
            carregarTokens();
        }
    });
}

async function revogarToken(id) {
    const confirm = await confirmar(
        'Revogar Token?',
        'O acesso via este token será imediatamente bloqueado.',
        'Sim, revogar'
    );
    
    if (!confirm.isConfirmed) return;
    
    try {
        const result = await apiFetch(`/admin/api-tokens/${id}/revogar`, 'POST');
        if (result.success) {
            showToast('Token revogado com sucesso!');
            carregarTokens();
        }
    } catch (e) {
        showError('Erro', e.message);
    }
}

document.addEventListener('DOMContentLoaded', carregarTokens);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>