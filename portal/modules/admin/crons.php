<?php
define('ADMIN_AREA', true);
$pageTitle = 'Motor de Crons | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<!-- Header -->
<div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-clock text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">MOTOR DE CRONS</h2>
            <span class="text-xs text-slate-400 font-medium">Gerenciamento de automações</span>
        </div>
    </div>
    <div class="flex gap-2">
         <button onclick="novoJob()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all flex items-center gap-2 text-sm">
            <i class="fa-solid fa-plus"></i> Novo Job
        </button>
        <button onclick="atualizarDashboard()" class="px-4 py-2 bg-slate-700 text-white rounded-xl font-bold hover:bg-slate-800 transition-all flex items-center gap-2">
            <i class="fa-solid fa-rotate"></i> Atualizar
        </button>
    </div>
</div>
<!-- Cards de Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm text-center">
        <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-list text-lg"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800" id="totalJobs">-</h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Total de Jobs</p>
    </div>
    
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm text-center">
        <div class="w-10 h-10 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-play text-lg"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800" id="jobsAtivos">-</h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Jobs Ativos</p>
    </div>
    
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm text-center">
        <div class="w-10 h-10 bg-sky-100 text-sky-600 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-calendar-day text-lg"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800" id="execucoesHoje">-</h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Execuções Hoje</p>
    </div>
    
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm text-center">
        <div class="w-10 h-10 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center mx-auto mb-3">
            <i class="fa-solid fa-exclamation-triangle text-lg"></i>
        </div>
        <h3 class="text-2xl font-black text-slate-800" id="falhasHoje">-</h3>
        <p class="text-xs text-slate-400 font-medium mt-1">Falhas Hoje</p>
    </div>
</div>

<!-- Execução Manual -->
<div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm mb-6">
    <h5 class="text-sm font-bold text-slate-700 mb-4">
        <i class="fa-solid fa-play mr-2"></i>Execução Manual
    </h5>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
        <button onclick="executarCronManual('representantes')" class="p-4 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all text-sm">
            <i class="fa-solid fa-users mr-2"></i>Executar Representantes
        </button>
        <button onclick="executarCronManual('gestores')" class="p-4 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all text-sm">
            <i class="fa-solid fa-chart-pie mr-2"></i>Executar Gestores
        </button>
        <button onclick="executarCronManual('historico_kpi')" class="p-4 bg-sky-500 text-white rounded-xl font-bold hover:bg-sky-600 transition-all text-sm">
            <i class="fa-solid fa-chart-line mr-2"></i>Executar Histórico KPI
        </button>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <button onclick="executarCronManual('notas_nutrire')" class="p-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all text-sm">
            <i class="fa-solid fa-file-invoice mr-2"></i>Notas Nutrire
        </button>
        <button onclick="executarCronManual('flex_minimo_gestor')" class="p-3 bg-purple-600 text-white rounded-xl font-bold hover:bg-purple-700 transition-all text-sm">
            <i class="fa-solid fa-coins mr-2"></i>Flex Mínimo Gestor
        </button>
        <button onclick="executarCronManual('bonificacoes_flex')" class="p-3 bg-rose-600 text-white rounded-xl font-bold hover:bg-rose-700 transition-all text-sm">
            <i class="fa-solid fa-gift mr-2"></i>Bonificações Flex
        </button>
    </div>
</div>

<!-- Gráfico + Lista de Jobs -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Gráfico -->
    <div class="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <div class="flex justify-between items-center mb-4">
            <h5 class="text-sm font-bold text-slate-700">
                <i class="fa-solid fa-chart-line mr-2"></i>Execuções nos últimos dias
            </h5>
            <select id="periodoGrafico" onchange="atualizarDashboard()" class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-bold">
                <option value="7">7 dias</option>
                <option value="15">15 dias</option>
                <option value="30">30 dias</option>
            </select>
        </div>
        <div style="height: 300px;">
            <canvas id="graficoExecucoes"></canvas>
        </div>
    </div>
    
    <!-- Próximas Execuções -->
    <div class="bg-white p-5 rounded-2xl border border-slate-100 shadow-sm">
        <h5 class="text-sm font-bold text-slate-700 mb-4">
            <i class="fa-solid fa-clock mr-2"></i>Próximas Execuções
        </h5>
        <div id="proximasExecucoes" class="max-h-[300px] overflow-y-auto">
            <p class="text-center text-slate-400 py-4">Carregando...</p>
        </div>
    </div>
</div>

<!-- Lista de Jobs (Tabela CRUD) -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="p-5 border-b border-slate-100 flex justify-between items-center">
        <h5 class="text-sm font-bold text-slate-700">
            <i class="fa-solid fa-list-check mr-2"></i>Todos os Jobs
        </h5>
        <div class="flex gap-2">
            <select id="filtroCategoria" onchange="filtrarJobs()" class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-bold">
                <option value="">Todas categorias</option>
                <option value="1">📧 Email</option>
                <option value="2">⚙️ Processamento</option>
                <option value="3">🔗 Integração</option>
                <option value="4">💰 Financeiro</option>
            </select>
            <select id="filtroStatus" onchange="filtrarJobs()" class="px-3 py-1.5 border border-slate-200 rounded-lg text-xs font-bold">
                <option value="">Todos</option>
                <option value="true">Ativos</option>
                <option value="false">Inativos</option>
            </select>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Job</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Comando</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Schedule</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Última Exec.</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody id="jobsTable">
                <tr><td colspan="6" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Execuções Recentes -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h5 class="text-sm font-bold text-slate-700">
            <i class="fa-solid fa-history mr-2"></i>Execuções Recentes
        </h5>
    </div>
    <div id="execucoesRecentes" class="divide-y divide-slate-100 max-h-[400px] overflow-y-auto">
        <p class="text-center text-slate-400 py-4">Carregando...</p>
    </div>
</div>

<!-- Modal de Job (Criar/Editar) -->
<div id="modalJob" class="fixed inset-0 z-50 hidden overflow-y-auto" role="dialog">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalJob()"></div>
        
        <div class="relative bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-hidden shadow-2xl">
            <div class="bg-slate-700 px-6 py-4 flex items-center justify-between">
                <h3 class="text-lg font-bold text-white" id="modalJobTitulo">
                    <i class="fa-solid fa-plus-circle mr-2"></i>Novo Job
                </h3>
                <button onclick="fecharModalJob()" class="text-white/70 hover:text-white transition-colors">
                    <i class="fa-solid fa-times text-xl"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-80px)]">
                <form id="formJob" class="space-y-4">
                    <input type="hidden" id="jobId">
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Nome do Job *</label>
                            <input type="text" id="jobNome" required class="w-full p-3 border border-slate-200 rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Comando *</label>
                            <input type="text" id="jobComando" required class="w-full p-3 border border-slate-200 rounded-xl text-sm font-mono" placeholder="representantes">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Descrição</label>
                        <textarea id="jobDescricao" rows="2" class="w-full p-3 border border-slate-200 rounded-xl text-sm"></textarea>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Categoria</label>
                            <select id="jobCategoria" class="w-full p-3 border border-slate-200 rounded-xl text-sm">
                                <option value="">Selecione...</option>
                                <option value="1">📧 Email</option>
                                <option value="2">⚙️ Processamento</option>
                                <option value="3">🔗 Integração</option>
                                <option value="4">💰 Financeiro</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Schedule (Cron)</label>
                            <input type="text" id="jobSchedule" class="w-full p-3 border border-slate-200 rounded-xl text-sm font-mono" placeholder="0 9 * * *">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Status</label>
                            <div class="flex items-center gap-3 mt-3">
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="jobAtivo" checked class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-emerald-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
                                </label>
                                <span class="text-sm font-medium text-slate-600" id="textoStatus">Ativo</span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Emails para Notificação</label>
                        <input type="text" id="jobNotificarEmail" class="w-full p-3 border border-slate-200 rounded-xl text-sm" placeholder="email1@exemplo.com, email2@exemplo.com">
                    </div>
                    
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="jobNotificarSucesso" class="w-4 h-4 text-emerald-600 rounded">
                            <span class="text-sm">Notificar SUCESSO</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="jobNotificarFalha" checked class="w-4 h-4 text-rose-600 rounded">
                            <span class="text-sm">Notificar FALHA</span>
                        </label>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Parâmetros (JSON)</label>
                        <textarea id="jobParametros" rows="4" class="w-full p-3 border border-slate-200 rounded-xl text-sm font-mono">{}</textarea>
                    </div>
                    
                    <div class="pt-4 border-t border-slate-100 flex gap-3">
                        <button type="button" onclick="salvarJob()" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
                            <i class="fa-solid fa-save mr-2"></i>Salvar Job
                        </button>
                        <button type="button" onclick="fecharModalJob()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let grafico = null;
let todosJobs = [];

// ======================================================================
// MODAL
// ======================================================================
function abrirModalJob() { document.getElementById('modalJob').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
function fecharModalJob() { document.getElementById('modalJob').classList.add('hidden'); document.body.style.overflow = ''; }

function novoJob() {
    document.getElementById('modalJobTitulo').innerHTML = '<i class="fa-solid fa-plus-circle mr-2"></i>Novo Job';
    document.getElementById('jobId').value = '';
    document.getElementById('formJob').reset();
    document.getElementById('jobAtivo').checked = true;
    document.getElementById('jobNotificarFalha').checked = true;
    document.getElementById('jobParametros').value = '{}';
    abrirModalJob();
}

async function editarJob(id) {
    const job = todosJobs.find(j => j.id == id);
    if (!job) return;

    document.getElementById('modalJobTitulo').innerHTML = '<i class="fa-solid fa-edit mr-2"></i>Editar: ' + job.nome;
    document.getElementById('jobId').value = job.id;
    document.getElementById('jobNome').value = job.nome;
    document.getElementById('jobComando').value = job.comando;
    document.getElementById('jobDescricao').value = job.descricao || '';
    document.getElementById('jobCategoria').value = job.categoria_id || '';
    document.getElementById('jobSchedule').value = job.schedule || '';
    document.getElementById('jobAtivo').checked = job.ativo;
    document.getElementById('jobNotificarEmail').value = job.notificar_email || '';
    document.getElementById('jobNotificarSucesso').checked = job.notificar_sucesso;
    document.getElementById('jobNotificarFalha').checked = job.notificar_falha;
    document.getElementById('jobParametros').value = JSON.stringify(job.parametros || {}, null, 2);
    abrirModalJob();
}

async function salvarJob() {
    const id = document.getElementById('jobId').value;
    const data = {
        id: id || null,
        nome: document.getElementById('jobNome').value,
        comando: document.getElementById('jobComando').value,
        descricao: document.getElementById('jobDescricao').value,
        categoria_id: document.getElementById('jobCategoria').value || null,
        schedule: document.getElementById('jobSchedule').value || null,
        ativo: document.getElementById('jobAtivo').checked,
        notificar_email: document.getElementById('jobNotificarEmail').value || null,
        notificar_sucesso: document.getElementById('jobNotificarSucesso').checked,
        notificar_falha: document.getElementById('jobNotificarFalha').checked,
        parametros: JSON.parse(document.getElementById('jobParametros').value || '{}')
    };

    if (!data.nome || !data.comando) {
        showError('Erro', 'Nome e Comando são obrigatórios');
        return;
    }

    try {
        showLoading(id ? 'Atualizando...' : 'Criando...');
        const result = await apiFetch('/cron/jobs', 'POST', data);
        Swal.close();

        if (result.success) {
            fecharModalJob();
            showToast(result.message);
            carregarJobs();
            atualizarDashboard();
        } else {
            showError('Erro', result.message);
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

async function excluirJob(id) {
    const job = todosJobs.find(j => j.id == id);
    if (!job) return;

    const confirm = await confirmar('Excluir Job?', `Tem certeza que deseja excluir "${job.nome}"?`, 'Sim, excluir');
    if (!confirm.isConfirmed) return;

    try {
        showLoading('Excluindo...');
        const result = await apiFetch(`/cron/jobs/${id}`, 'DELETE');
        Swal.close();

        if (result.success) {
            showToast('Job removido!');
            carregarJobs();
            atualizarDashboard();
        }
    } catch (e) {
        Swal.close();
        showError('Erro', e.message);
    }
}

// ======================================================================
// DADOS
// ======================================================================
async function carregarJobs() {
    try {
        const data = await apiFetch('/cron/jobs', 'GET');
        todosJobs = data.jobs || [];
        filtrarJobs();
    } catch (e) {
        console.error('Erro ao carregar jobs:', e);
    }
}

function filtrarJobs() {
    const categoria = document.getElementById('filtroCategoria')?.value || '';
    const status = document.getElementById('filtroStatus')?.value || '';

    let filtrados = todosJobs;
    if (categoria) filtrados = filtrados.filter(j => j.categoria_id == categoria);
    if (status !== '') filtrados = filtrados.filter(j => j.ativo === (status === 'true'));

    renderizarJobs(filtrados);
}

function renderizarJobs(lista) {
    const tbody = document.getElementById('jobsTable');
    if (!tbody) return;

    if (lista.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-8 text-slate-400">Nenhum job encontrado</td></tr>';
        return;
    }

    tbody.innerHTML = lista.map(job => {
        const statusBg = job.ativo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
        const ultimaExec = job.ultima_execucao ? formatarData(job.ultima_execucao) : 'Nunca';
        const corCategoria = job.cor || '#6c757d';
        const iconeCategoria = job.icone || 'fa-cog';

        return `
            <tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                <td class="px-4 py-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid ${iconeCategoria}" style="color: ${corCategoria};"></i>
                        <div>
                            <b class="text-sm">${job.nome}</b>
                            <br><small class="text-slate-400">${job.descricao || 'Sem descrição'}</small>
                        </div>
                    </div>
                </td>
                <td class="px-4 py-3"><code class="text-xs bg-slate-100 px-2 py-1 rounded">${job.comando}</code></td>
                <td class="px-4 py-3 text-xs font-mono">${job.schedule || '-'}</td>
                <td class="px-4 py-3 text-xs text-slate-500">${ultimaExec}</td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-[10px] font-bold ${statusBg}">${job.ativo ? 'Ativo' : 'Inativo'}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <button onclick="executarCronManual('${job.comando}')" class="px-3 py-1.5 border border-emerald-300 text-emerald-600 rounded-lg text-sm hover:bg-emerald-500 hover:text-white transition-all mr-1" title="Executar">
                        <i class="fa-solid fa-play"></i>
                    </button>
                    <button onclick="editarJob(${job.id})" class="px-3 py-1.5 border border-slate-300 text-slate-600 rounded-lg text-sm hover:bg-slate-700 hover:text-white transition-all mr-1" title="Editar">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <button onclick="excluirJob(${job.id})" class="px-3 py-1.5 border border-rose-300 text-rose-600 rounded-lg text-sm hover:bg-rose-500 hover:text-white transition-all" title="Excluir">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

async function atualizarDashboard() {
    try {
        showLoading('Carregando dashboard...');
        const periodo = document.getElementById('periodoGrafico')?.value || '7';
        const data = await apiFetch(`/cron/dashboard?dias=${periodo}`, 'GET');
        Swal.close();

        document.getElementById('totalJobs').textContent = data.stats?.total_jobs || 0;
        document.getElementById('jobsAtivos').textContent = data.stats?.jobs_ativos || 0;
        document.getElementById('execucoesHoje').textContent = data.stats?.execucoes_hoje || 0;
        document.getElementById('falhasHoje').textContent = data.stats?.falhas_hoje || 0;

        if (data.grafico && data.grafico.length > 0) atualizarGrafico(data.grafico);
        if (data.proximas) atualizarProximas(data.proximas);
        if (data.recentes) atualizarRecentes(data.recentes);
    } catch (e) {
        Swal.close();
        showError('Erro', 'Falha ao carregar dashboard: ' + e.message);
    }
}

function atualizarGrafico(dados) {
    const canvas = document.getElementById('graficoExecucoes');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    const labels = dados.map(d => {
        const data = new Date(d.data + 'T12:00:00');
        return data.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
    });
    
    const sucessos = dados.map(d => parseInt(d.sucessos) || 0);
    const falhas = dados.map(d => parseInt(d.falhas) || 0);
    
    if (grafico) {
        grafico.destroy();
    }
    
    grafico = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Sucessos',
                    data: sucessos,
                    backgroundColor: '#10b981',
                    borderRadius: 8,
                    borderSkipped: false
                },
                {
                    label: 'Falhas',
                    data: falhas,
                    backgroundColor: '#ef4444',
                    borderRadius: 8,
                    borderSkipped: false
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 15,
                        font: { 
                            family: "'Plus Jakarta Sans', sans-serif", 
                            size: 12, 
                            weight: 'bold' 
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(30, 41, 59, 0.95)',
                    titleFont: { size: 12, family: "'Plus Jakarta Sans', sans-serif" },
                    bodyFont: { size: 13, weight: 'bold', family: "'Plus Jakarta Sans', sans-serif" },
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { 
                        stepSize: 1,
                        font: { size: 11 }
                    },
                    grid: { color: '#f1f5f9' }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 11 }
                    }
                }
            }
        }
    });
}

function atualizarProximas(jobs) {
    const container = document.getElementById('proximasExecucoes');
    
    if (!jobs || jobs.length === 0) {
        container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum job agendado</p>';
        return;
    }
    
    container.innerHTML = jobs.map(job => {
        const proxima = job.proxima_execucao ? formatarData(job.proxima_execucao) : 'Não agendado';
        const statusBg = job.ativo ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500';
        const statusTexto = job.ativo ? 'Ativo' : 'Inativo';
        const corCategoria = job.cor || job.cor_categoria || '#6c757d';
        
        return `
            <div class="flex justify-between items-center p-3 border-b border-slate-100 last:border-0 hover:bg-slate-50 transition-all">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: ${corCategoria};"></div>
                        <span class="font-semibold text-sm truncate">${job.nome}</span>
                    </div>
                    <small class="text-slate-400 ml-4 block truncate">${job.schedule || 'Sem agendamento'}</small>
                </div>
                <div class="text-right flex-shrink-0 ml-3">
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${statusBg}">${statusTexto}</span>
                    <br>
                    <small class="text-slate-400">${proxima}</small>
                </div>
            </div>
        `;
    }).join('');
}

function atualizarRecentes(execucoes) {
    const container = document.getElementById('execucoesRecentes');
    
    if (!execucoes || execucoes.length === 0) {
        container.innerHTML = '<p class="text-center text-slate-400 py-6">Nenhuma execução recente</p>';
        return;
    }
    
    container.innerHTML = execucoes.map(exec => {
        let statusBg, statusIcon, statusText, borderColor;
        
        if (exec.status === 'sucesso') {
            statusBg = 'bg-emerald-100 text-emerald-700';
            statusIcon = 'fa-check-circle';
            statusText = 'Sucesso';
            borderColor = 'border-l-emerald-500';
        } else if (exec.status === 'falha') {
            statusBg = 'bg-rose-100 text-rose-700';
            statusIcon = 'fa-times-circle';
            statusText = 'Falha';
            borderColor = 'border-l-rose-500';
        } else {
            statusBg = 'bg-amber-100 text-amber-700';
            statusIcon = 'fa-spinner fa-spin';
            statusText = 'Executando';
            borderColor = 'border-l-amber-500';
        }
        
        // ✅ Garante que é número (usando parseFloat)
        const duracao = formatarDuracao(parseFloat(exec.duracao_segundos));
        const dataHora = formatarData(exec.iniciado_em);
        const usuario = exec.usuario || 'Sistema';
        const origem = exec.origem || 'Manual';
        
        return `
            <div class="p-4 border-l-4 ${borderColor} hover:bg-slate-50 transition-all cursor-pointer" 
                 onclick="verDetalhesExecucao(${exec.id})" 
                 title="Clique para ver detalhes">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-3">
                        <i class="fa-solid ${statusIcon} text-lg ${exec.status === 'executando' ? 'text-amber-500' : ''}"></i>
                        <div>
                            <span class="font-semibold text-sm">${exec.nome || 'Job #' + exec.job_id}</span>
                            <div class="flex items-center gap-3 mt-1">
                                <small class="text-slate-400">
                                    <i class="fa-regular fa-clock mr-1"></i>${dataHora}
                                </small>
                                <small class="text-slate-400">
                                    <i class="fa-regular fa-user mr-1"></i>${usuario}
                                </small>
                                <small class="text-slate-400">
                                    <i class="fa-regular fa-sync mr-1"></i>${origem}
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <span class="px-2 py-1 rounded-full text-[10px] font-bold ${statusBg}">${statusText}</span>
                        <br>
                        <small class="text-slate-400">${duracao}</small>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function executarCronManual(comando) {
    const nomes = {
        'representantes': 'Representantes',
        'gestores': 'Gestores',
        'historico_kpi': 'Histórico KPI',
        'notas_nutrire': 'Notas Nutrire',
        'flex_minimo_gestor': 'Flex Mínimo Gestor',
        'bonificacoes_flex': 'Bonificações Flex'
    };
    
    const nomeExibicao = nomes[comando] || comando;
    
    const confirm = await Swal.fire({
        title: 'Executar Job?',
        html: `
            <div class="text-left">
                <p class="mb-3">Deseja executar o job <b>"${nomeExibicao}"</b> agora?</p>
                <div class="bg-amber-50 border border-amber-200 rounded-xl p-3 text-sm text-amber-700">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    Esta ação irá disparar o processo imediatamente e pode afetar o sistema.
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-play mr-2"></i>Sim, executar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#6c757d'
    });
    
    if (!confirm.isConfirmed) return;
    
    try {
        Swal.fire({
            title: 'Executando...',
            html: `<p>Processando <b>${nomeExibicao}</b></p>
                   <div class="mt-4 flex justify-center">
                       <i class="fa-solid fa-spinner fa-spin fa-2x text-emerald-500"></i>
                   </div>`,
            allowOutsideClick: false,
            showConfirmButton: false
        });
        
        const result = await apiFetch('/cron/executar', 'POST', { 
            comando: comando,
            origem: 'MANUAL'
        });
        
        Swal.close();
        
        if (result.success) {
            // Atualiza os dados
            carregarJobs();
            atualizarDashboard();
            
            // Verifica se tem detalhes para mostrar
            const temDetalhes = result.detalhes && Object.keys(result.detalhes).length > 0;
            
            if (temDetalhes) {
                const detalhesFormatados = JSON.stringify(result.detalhes, null, 2);
                
                Swal.fire({
                    title: '✅ Job Executado!',
                    html: `
                        <div class="text-left">
                            <p class="mb-3 text-emerald-600 font-bold">${result.message}</p>
                            <div class="bg-slate-800 text-emerald-400 p-4 rounded-xl text-xs font-mono max-h-[300px] overflow-auto text-left">
                                <pre style="margin: 0; white-space: pre-wrap;">${detalhesFormatados}</pre>
                            </div>
                            ${result.duracao ? `<p class="mt-3 text-xs text-slate-400">Duração: ${result.duracao}s</p>` : ''}
                        </div>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#274036',
                    width: '700px'
                });
            } else {
                await Swal.fire({
                    title: '✅ Sucesso!',
                    text: result.message,
                    icon: 'success',
                    confirmButtonColor: '#274036'
                });
            }
        } else {
            Swal.fire({
                title: '❌ Erro',
                html: `
                    <div class="text-left">
                        <p class="text-rose-600 font-bold">${result.message || 'Falha na execução'}</p>
                    </div>
                `,
                icon: 'error',
                confirmButtonColor: '#274036'
            });
        }
    } catch (e) {
        Swal.close();
        Swal.fire({
            title: '❌ Erro de Conexão',
            text: e.message || 'Falha ao comunicar com o servidor',
            icon: 'error',
            confirmButtonColor: '#274036'
        });
    }
}

async function verDetalhesExecucao(id) {
    try {
        Swal.fire({
            title: 'Carregando detalhes...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        const data = await apiFetch(`/cron/auditoria/${id}`, 'GET');
        Swal.close();
        
        if (!data.execucao) {
            Swal.fire('Info', 'Detalhes não encontrados', 'info');
            return;
        }
        
        const exec = data.execucao;
        const statusBg = exec.status === 'sucesso' ? 'bg-emerald-100 text-emerald-700' : 
                         exec.status === 'falha' ? 'bg-rose-100 text-rose-700' : 
                         'bg-amber-100 text-amber-700';
        
        let resultadoHtml = '';
        if (exec.resultado && typeof exec.resultado === 'object') {
            resultadoHtml = `<pre class="text-xs bg-slate-100 p-3 rounded-xl max-h-[300px] overflow-auto">${JSON.stringify(exec.resultado, null, 2)}</pre>`;
        } else if (exec.resultado) {
            resultadoHtml = `<p class="text-sm text-slate-600">${exec.resultado}</p>`;
        } else {
            resultadoHtml = '<p class="text-sm text-slate-400">Sem resultado detalhado</p>';
        }
        
        Swal.fire({
            title: `Execução #${exec.id}`,
            html: `
                <div class="text-left space-y-3">
                    <div class="flex justify-between">
                        <span class="text-slate-500">Job:</span>
                        <b>${exec.nome || 'N/A'}</b>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Comando:</span>
                        <code class="text-xs bg-slate-100 px-2 py-1 rounded">${exec.comando || 'N/A'}</code>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Status:</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold ${statusBg}">${exec.status}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Usuário:</span>
                        <span>${exec.usuario || '-'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Origem:</span>
                        <span>${exec.origem || '-'}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Início:</span>
                        <span>${formatarData(exec.iniciado_em)}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-500">Duração:</span>
                        <span>${formatarDuracao(exec.duracao_segundos)}</span>
                    </div>
                    <hr class="border-slate-200">
                    <p class="text-xs font-bold text-slate-500 uppercase">Resultado:</p>
                    ${resultadoHtml}
                </div>
            `,
            icon: 'info',
            confirmButtonColor: '#274036',
            width: '600px'
        });
        
    } catch (e) {
        Swal.close();
        Swal.fire('Erro', 'Falha ao carregar detalhes', 'error');
    }
}

document.addEventListener('DOMContentLoaded', () => {
    carregarJobs();
    atualizarDashboard();
});
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>