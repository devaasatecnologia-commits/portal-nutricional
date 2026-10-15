<?php
define('ADMIN_AREA', true);
$pageTitle = 'Segredos & Tokens | Admin Nutricional';

require_once __DIR__ . '/components/header.php';
require_once __DIR__ . '/components/sidebar.php';
?>

<div class="glass rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
    <div class="flex items-center gap-3">
        <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-2xl flex items-center justify-center">
            <i class="fa-solid fa-vault text-xl"></i>
        </div>
        <div>
            <h2 class="text-lg font-bold text-slate-800 leading-none">SEGREDOS & TOKENS</h2>
            <span class="text-xs text-slate-400 font-medium">Chaves de integração guardadas cifradas no banco (AES-256-GCM)</span>
        </div>
    </div>
</div>

<!-- 2FA -->
<div id="card2fa" class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 mb-6">
    <div class="flex justify-between items-center gap-4 flex-wrap">
        <div>
            <h5 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-shield-halved mr-2"></i>Verificação em duas etapas (app autenticador)</h5>
            <p class="text-xs text-slate-400 mt-1" id="texto2fa">Carregando...</p>
        </div>
        <div id="acoes2fa"></div>
    </div>
</div>

<!-- Segredos -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="p-5 border-b border-slate-100">
        <h5 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-key mr-2"></i>Chaves de integração</h5>
        <p class="text-xs text-slate-400 mt-1">O valor do banco tem prioridade sobre o <code>.env</code>. DB_*, CHAVE_SECRETA, API_TOKEN, CRON_TOKEN e JWT_SECRET continuam no <code>.env</code> (necessários antes de conectar ao banco).</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Chave</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Valor</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Origem</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Atualizado</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody id="segredosTable">
                <tr><td colspan="5" class="text-center py-8 text-slate-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Carregando...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Auditoria -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="p-5 border-b border-slate-100">
        <h5 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-clipboard-list mr-2"></i>Auditoria de acessos</h5>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Data/Hora</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Usuário</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Alvo</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Ação</th>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">IP</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Resultado</th>
                </tr>
            </thead>
            <tbody id="auditoriaTable"></tbody>
        </table>
    </div>
</div>

<script>
let estado2fa = { ativo: false, pendente: false };

async function carregar() {
    try {
        const d = await apiFetch('/admin/segredos', 'GET');
        if (d.error) throw new Error(d.error);
        estado2fa = d.doisfatores || estado2fa;
        renderizar2fa();
        renderizarSegredos(d.segredos || []);
        carregarAuditoria();
    } catch (e) {
        showError('Erro', e.message);
    }
}

function renderizar2fa() {
    const t = document.getElementById('texto2fa');
    const a = document.getElementById('acoes2fa');
    if (estado2fa.ativo) {
        t.innerHTML = '<span class="text-emerald-600 font-bold">Ativo.</span> Revelar tokens exige o código do aplicativo.';
        a.innerHTML = '<button onclick="desativar2fa()" class="px-4 py-2 border border-rose-300 text-rose-600 rounded-xl text-sm font-bold hover:bg-rose-500 hover:text-white">Desativar</button>';
    } else {
        t.innerHTML = '<span class="text-amber-600 font-bold">Não ativo.</span> Ative para poder revelar tokens e chaves.';
        a.innerHTML = '<button onclick="iniciar2fa()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">Ativar 2FA</button>';
    }
}

function renderizarSegredos(lista) {
    const cores = { banco: 'bg-emerald-100 text-emerald-700', env: 'bg-amber-100 text-amber-700', vazio: 'bg-slate-100 text-slate-500' };
    const nomes = { banco: 'Banco (cifrado)', env: '.env', vazio: 'Não configurado' };
    document.getElementById('segredosTable').innerHTML = lista.map(s => `
        <tr class="border-b border-slate-100 hover:bg-slate-50">
            <td class="px-4 py-3">
                <div class="font-semibold text-sm">${escapeHtml(s.rotulo)}</div>
                <code class="text-[10px] text-slate-400">${escapeHtml(s.chave)}</code>
                <div class="text-xs text-slate-400">${escapeHtml(s.descricao)}</div>
            </td>
            <td class="px-4 py-3"><code class="text-xs bg-slate-100 px-2 py-1 rounded">${s.configurado ? escapeHtml(s.mascara) : '—'}</code></td>
            <td class="px-4 py-3"><span class="px-2 py-1 rounded-full text-[10px] font-bold ${cores[s.origem]}">${nomes[s.origem]}</span></td>
            <td class="px-4 py-3 text-xs text-slate-500">${s.atualizado ? escapeHtml(s.atualizado.por || '') + '<br>' + formatarData(s.atualizado.em) : '-'}</td>
            <td class="px-4 py-3 text-center whitespace-nowrap">
                <button ${s.configurado ? '' : 'disabled'} onclick="revelarSegredo('/admin/segredos/${s.chave}/revelar', 'valor', '${escapeHtml(s.rotulo)}')" title="Revelar (exige 2FA)" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm hover:bg-slate-700 hover:text-white disabled:opacity-30"><i class="fa-solid fa-eye"></i></button>
                <button onclick="editarSegredo('${s.chave}', '${escapeHtml(s.rotulo)}')" title="Definir/alterar" class="px-3 py-1.5 border border-amber-300 text-amber-600 rounded-lg text-sm hover:bg-amber-500 hover:text-white"><i class="fa-solid fa-pen"></i></button>
                <button ${s.origem === 'banco' ? '' : 'disabled'} onclick="removerSegredo('${s.chave}')" title="Remover do banco" class="px-3 py-1.5 border border-rose-300 text-rose-600 rounded-lg text-sm hover:bg-rose-500 hover:text-white disabled:opacity-30"><i class="fa-solid fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

async function editarSegredo(chave, rotulo) {
    const r = await Swal.fire({
        title: rotulo,
        input: 'password',
        inputLabel: 'Novo valor (será salvo cifrado e mascarado na tela)',
        inputAttributes: { autocomplete: 'new-password' },
        showCancelButton: true,
        confirmButtonText: 'Salvar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        inputValidator: v => (v || '').trim() ? null : 'Informe o valor'
    });
    if (!r.isConfirmed) return;
    try {
        const res = await apiFetch('/admin/segredos', 'POST', { chave, valor: r.value });
        if (res.error) throw new Error(res.error);
        showToast('Segredo salvo');
        carregar();
    } catch (e) { showError('Erro', e.message); }
}

async function removerSegredo(chave) {
    const c = await confirmar('Remover do banco?', 'Passa a valer o valor do .env (se houver).', 'Sim, remover');
    if (!c.isConfirmed) return;
    try {
        const res = await apiFetch(`/admin/segredos/${chave}`, 'DELETE');
        if (res.error) throw new Error(res.error);
        showToast('Removido');
        carregar();
    } catch (e) { showError('Erro', e.message); }
}

async function iniciar2fa() {
    const s = await Swal.fire({
        title: 'Confirme sua senha',
        input: 'password',
        showCancelButton: true,
        confirmButtonText: 'Continuar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        inputValidator: v => v ? null : 'Informe a senha'
    });
    if (!s.isConfirmed) return;
    try {
        const d = await apiFetch('/admin/2fa/iniciar', 'POST', { senha: s.value });
        if (d.error) throw new Error(d.error);
        const r = await Swal.fire({
            title: 'Configure o autenticador',
            html: `<p class="text-sm mb-2">No Google/Microsoft Authenticator escolha <b>Adicionar conta &gt; Inserir chave de configuração</b> e informe:</p>
                   <div class="bg-slate-100 p-3 rounded-xl font-mono text-sm break-all select-all">${escapeHtml(d.segredo)}</div>
                   <p class="text-xs text-slate-400 mt-2">Tipo: baseado em tempo. Depois digite abaixo o código de 6 dígitos gerado.</p>`,
            input: 'text',
            inputAttributes: { inputmode: 'numeric', maxlength: 6 },
            showCancelButton: true,
            confirmButtonText: 'Ativar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#274036',
            inputValidator: v => /^\d{6}$/.test((v || '').trim()) ? null : 'Informe os 6 dígitos'
        });
        if (!r.isConfirmed) return;
        const c = await apiFetch('/admin/2fa/confirmar', 'POST', { codigo: r.value.trim() });
        if (c.error) throw new Error(c.error);
        showToast('2FA ativado');
        carregar();
    } catch (e) { showError('Erro', e.message); }
}

async function desativar2fa() {
    const s = await Swal.fire({ title: 'Confirme sua senha', input: 'password', showCancelButton: true, confirmButtonColor: '#ef4444',
        inputValidator: v => v ? null : 'Informe a senha' });
    if (!s.isConfirmed) return;
    const codigo = await pedirCodigo2fa('Código para desativar');
    if (!codigo) return;
    try {
        const d = await apiFetch('/admin/2fa/desativar', 'POST', { senha: s.value, codigo });
        if (d.error) throw new Error(d.error);
        showToast('2FA desativado');
        carregar();
    } catch (e) { showError('Erro', e.message); }
}

async function carregarAuditoria() {
    try {
        const d = await apiFetch('/admin/segredos/auditoria', 'GET');
        document.getElementById('auditoriaTable').innerHTML = (d.logs || []).map(l => `
            <tr class="border-b border-slate-100 text-sm">
                <td class="px-4 py-2 text-xs text-slate-500">${formatarData(l.created_at)}</td>
                <td class="px-4 py-2">${escapeHtml(l.username)}</td>
                <td class="px-4 py-2 font-mono text-xs">${escapeHtml(l.chave)}</td>
                <td class="px-4 py-2">${escapeHtml(l.acao)}</td>
                <td class="px-4 py-2 text-xs">${escapeHtml(l.ip)}</td>
                <td class="px-4 py-2 text-center"><span class="px-2 py-1 rounded-full text-[10px] font-bold ${l.sucesso ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}">${l.sucesso ? 'OK' : 'FALHA'}</span></td>
            </tr>`).join('') || '<tr><td colspan="6" class="text-center py-6 text-slate-400">Sem registros</td></tr>';
    } catch (e) { /* auditoria e informativa */ }
}

document.addEventListener('DOMContentLoaded', carregar);
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
