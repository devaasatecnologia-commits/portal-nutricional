<?php
define('ADMIN_AREA', true);
$pageTitle = 'Dashboard Admin | Nutricional';

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
            <span class="text-sm font-bold modulo-nome">ADMIN</span>
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
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-gauge-high text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">DASHBOARD ADMINISTRATIVO</h2>
                <span class="text-xs text-slate-400 font-medium">Visão geral do sistema</span>
            </div>
        </div>
        <div class="text-right hidden sm:block">
            <div class="clock font-mono text-xl lg:text-2xl font-black" id="relogio">00:00:00</div>
            <div class="data-topo text-[10px] lg:text-xs" id="data-topo">--/--/----</div>
        </div>
    </div>

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-3">
                <i class="fa-solid fa-users text-lg"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800" id="totalUsuarios">-</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">Total de Usuários</p>
        </div>
        
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center mb-3">
                <i class="fa-solid fa-check-circle text-lg"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800" id="usuariosAtivos">-</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">Usuários Ativos</p>
        </div>
        
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center mb-3">
                <i class="fa-solid fa-clock text-lg"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800" id="cronsAtivos">-</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">Crons Ativos</p>
        </div>
        
        <div class="stat-card bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center mb-3">
                <i class="fa-solid fa-key text-lg"></i>
            </div>
            <h3 class="text-2xl font-black text-slate-800" id="totalTokens">-</h3>
            <p class="text-xs text-slate-400 font-medium mt-1">API Tokens</p>
        </div>
    </div>

    <!-- Tabelas Rápidas -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <h5 class="text-sm font-bold text-slate-700 mb-4">
                <i class="fa-solid fa-users mr-2"></i>Últimos Usuários
            </h5>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-bold text-slate-400 uppercase">Usuário</th>
                            <th class="px-3 py-2 text-left text-xs font-bold text-slate-400 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-bold text-slate-400 uppercase">Módulos</th>
                        </tr>
                    </thead>
                    <tbody id="ultimosUsuarios">
                        <tr><td colspan="3" class="text-center py-4 text-slate-400">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
            <h5 class="text-sm font-bold text-slate-700 mb-4">
                <i class="fa-solid fa-clock mr-2"></i>Últimas Execuções Cron
            </h5>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-bold text-slate-400 uppercase">Job</th>
                            <th class="px-3 py-2 text-left text-xs font-bold text-slate-400 uppercase">Status</th>
                            <th class="px-3 py-2 text-left text-xs font-bold text-slate-400 uppercase">Data</th>
                        </tr>
                    </thead>
                    <tbody id="ultimasExecucoes">
                        <tr><td colspan="3" class="text-center py-4 text-slate-400">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
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

async function carregarDashboard() {
    try {
        showLoading('Carregando dashboard...');
        
        // ✅ Usa Promise.allSettled para não quebrar se um falhar
        const [usuarios, crons] = await Promise.allSettled([
            apiFetch('/admin/usuarios', 'GET'),
            apiFetch('/cron/dashboard', 'GET')
        ]);
        Swal.close();
        
        // ======================================================================
        // USUÁRIOS
        // ======================================================================
        if (usuarios.status === 'fulfilled') {
            const data = usuarios.value;
            document.getElementById('totalUsuarios').textContent = data.total || 0;
            document.getElementById('usuariosAtivos').textContent = data.ativos || 0;
            document.getElementById('totalTokens').textContent = data.tokens || 0;
            
            const tbodyUsers = document.getElementById('ultimosUsuarios');
            if (data.ultimos && data.ultimos.length > 0) {
                tbodyUsers.innerHTML = data.ultimos.map(u => `
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 font-semibold">${u.username}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold ${u.inativo === 'N' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500'}">
                                ${u.inativo === 'N' ? 'Ativo' : 'Inativo'}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-500">${u.permissoes || '-'}</td>
                    </tr>
                `).join('');
            } else {
                tbodyUsers.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-slate-400">Nenhum usuário</td></tr>';
            }
        } else {
            console.warn('⚠️ Usuários indisponíveis:', usuarios.reason);
            document.getElementById('totalUsuarios').textContent = '--';
            document.getElementById('usuariosAtivos').textContent = '--';
            document.getElementById('totalTokens').textContent = '--';
        }
        
        // ======================================================================
        // CRONS
        // ======================================================================
        if (crons.status === 'fulfilled') {
            const data = crons.value;
            document.getElementById('cronsAtivos').textContent = data.stats?.jobs_ativos || 0;
            
            const tbodyCron = document.getElementById('ultimasExecucoes');
            if (data.recentes && data.recentes.length > 0) {
                tbodyCron.innerHTML = data.recentes.map(e => `
                    <tr class="border-b border-slate-100">
                        <td class="px-3 py-2 font-semibold">${e.nome || 'Job ' + e.job_id}</td>
                        <td class="px-3 py-2">
                            <span class="px-2 py-1 rounded-full text-[10px] font-bold ${e.status === 'sucesso' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">
                                ${e.status}
                            </span>
                        </td>
                        <td class="px-3 py-2 text-xs text-slate-500">${formatarData(e.iniciado_em)}</td>
                    </tr>
                `).join('');
            } else {
                tbodyCron.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-slate-400">Nenhuma execução</td></tr>';
            }
        } else {
            console.warn('⚠️ Crons indisponíveis:', crons.reason);
            document.getElementById('cronsAtivos').textContent = '--';
            document.getElementById('ultimasExecucoes').innerHTML = '<tr><td colspan="3" class="text-center py-4 text-slate-400">Crons indisponíveis</td></tr>';
        }
        
    } catch (e) {
        Swal.close();
        console.error('❌ Erro no dashboard:', e);
        showError('Erro', 'Falha ao carregar dashboard: ' + e.message);
    }
}

document.addEventListener('DOMContentLoaded', carregarDashboard);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>