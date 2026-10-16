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

<!-- Segurança por usuário -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="p-5 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap">
        <div>
            <h5 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-user-lock mr-2"></i>Segurança de acesso por usuário</h5>
            <p class="text-xs text-slate-400 mt-1">2FA no login, tempo de inatividade e duração do token. Vale no próximo login do usuário. O usuário precisa ter ativado o 2FA (Admin &gt; Segredos &amp; 2FA); se ainda não ativou, entra normalmente e fica sinalizado.</p>
        </div>
        <input id="filtroSeg" oninput="renderizarSeguranca()" placeholder="Filtrar usuário..." class="px-3 py-2 border border-slate-200 rounded-xl text-sm">
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Usuário</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Autenticador</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Exigir 2FA no login</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Inatividade (min)</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Token (min)</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase"></th>
                </tr>
            </thead>
            <tbody id="segUsuariosTable"></tbody>
        </table>
    </div>
</div>

<!-- Cofre de senhas -->
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="p-5 border-b border-slate-100 flex justify-between items-center gap-3 flex-wrap">
        <div>
            <h5 class="text-sm font-bold text-slate-700"><i class="fa-solid fa-lock mr-2"></i>Meu cofre de senhas</h5>
            <p class="text-xs text-slate-400 mt-1">Cifrado com uma <b>senha mestra que não fica salva em lugar nenhum</b> (nem no banco, nem no .env). Abrir exige senha mestra + código do autenticador. Se perder a senha mestra, os itens não podem ser recuperados.</p>
        </div>
        <div id="acoesCofre"></div>
    </div>
    <div id="corpoCofre" class="p-5 text-sm text-slate-400">Carregando...</div>
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

// ======================================================================
// Segurança por usuário
// ======================================================================
let segUsuarios = [];

async function carregarSeguranca() {
    try {
        const d = await apiFetch('/admin/seguranca-usuarios', 'GET');
        if (d.error) throw new Error(d.error);
        segUsuarios = d.usuarios || [];
        renderizarSeguranca();
    } catch (e) { showError('Erro', e.message); }
}

function renderizarSeguranca() {
    const f = (document.getElementById('filtroSeg').value || '').toLowerCase();
    const lista = segUsuarios.filter(u => !f || u.username.toLowerCase().includes(f));
    document.getElementById('segUsuariosTable').innerHTML = lista.map(u => `
        <tr class="border-b border-slate-100 text-sm">
            <td class="px-4 py-2 font-semibold">${escapeHtml(u.username)}</td>
            <td class="px-4 py-2 text-center">${u.tem_2fa
                ? '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">Ativo</span>'
                : '<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-slate-100 text-slate-500">Não ativado</span>'}</td>
            <td class="px-4 py-2 text-center"><input type="checkbox" id="seg2fa_${u.idusuario}" ${u.exige_2fa_login ? 'checked' : ''} class="w-4 h-4">
                ${u.exige_2fa_login && !u.tem_2fa ? '<div class="text-[10px] text-amber-600">aguardando cadastro</div>' : ''}</td>
            <td class="px-4 py-2 text-center"><input type="number" min="1" max="480" id="segInat_${u.idusuario}" value="${u.inatividade_min}" class="w-20 px-2 py-1 border border-slate-200 rounded-lg text-center"></td>
            <td class="px-4 py-2 text-center"><input type="number" min="15" max="1440" id="segTok_${u.idusuario}" value="${u.token_min}" class="w-24 px-2 py-1 border border-slate-200 rounded-lg text-center"></td>
            <td class="px-4 py-2 text-center"><button onclick="salvarSeguranca(${u.idusuario})" class="px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-bold hover:bg-emerald-700">Salvar</button></td>
        </tr>`).join('') || '<tr><td colspan="6" class="text-center py-6 text-slate-400">Nenhum usuário</td></tr>';
}

async function salvarSeguranca(id) {
    try {
        const d = await apiFetch('/admin/seguranca-usuarios/' + id, 'POST', {
            exige_2fa_login: document.getElementById('seg2fa_' + id).checked,
            inatividade_min: parseInt(document.getElementById('segInat_' + id).value, 10),
            token_min: parseInt(document.getElementById('segTok_' + id).value, 10)
        });
        if (d.error) throw new Error(d.error);
        showToast('Política salva');
        carregarSeguranca();
    } catch (e) { showError('Erro', e.message); }
}

// ======================================================================
// Cofre de senhas (senha mestra só em memória; ticket vale 5 min)
// ======================================================================
let cofre = { configurado: false, tem2fa: false, ticket: null, mestra: null, timer: null, itens: [] };

function bloquearCofre() {
    clearTimeout(cofre.timer);
    cofre.ticket = null; cofre.mestra = null; cofre.itens = [];
    renderizarCofre();
}

async function carregarCofre() {
    try {
        const s = await apiFetch('/admin/cofre/status', 'GET');
        if (s.error) throw new Error(s.error);
        cofre.configurado = !!s.configurado; cofre.tem2fa = !!s.tem_2fa;
        renderizarCofre();
    } catch (e) { document.getElementById('corpoCofre').textContent = e.message; }
}

function renderizarCofre() {
    const corpo = document.getElementById('corpoCofre');
    const acoes = document.getElementById('acoesCofre');
    if (!cofre.tem2fa) {
        acoes.innerHTML = '';
        corpo.innerHTML = '<span class="text-amber-600 font-bold">Ative o 2FA acima</span> para criar e abrir o cofre.';
        return;
    }
    if (!cofre.configurado) {
        acoes.innerHTML = '<button onclick="criarCofre()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">Criar cofre</button>';
        corpo.textContent = 'Você ainda não criou o seu cofre.';
        return;
    }
    if (!cofre.ticket) {
        acoes.innerHTML = '<button onclick="desbloquearCofre()" class="px-4 py-2 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-700"><i class="fa-solid fa-lock-open mr-2"></i>Abrir cofre</button>';
        corpo.innerHTML = '<i class="fa-solid fa-lock mr-2"></i>Cofre bloqueado.';
        return;
    }
    acoes.innerHTML = `<button onclick="itemCofre()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700 mr-2"><i class="fa-solid fa-plus mr-1"></i>Novo item</button>
        <button onclick="bloquearCofre()" class="px-4 py-2 border border-slate-300 rounded-xl text-sm font-bold hover:bg-slate-100">Bloquear</button>`;
    corpo.innerHTML = cofre.itens.length ? `<table class="w-full">${cofre.itens.map(i => `
        <tr class="border-b border-slate-100 text-sm text-slate-700">
            <td class="py-2 font-semibold">${escapeHtml(i.titulo)}</td>
            <td class="py-2 text-xs text-slate-400">${formatarData(i.updated_at)}</td>
            <td class="py-2 text-right whitespace-nowrap">
                <button onclick="verItemCofre(${i.id})" title="Ver" class="px-3 py-1.5 border border-slate-300 rounded-lg hover:bg-slate-700 hover:text-white"><i class="fa-solid fa-eye"></i></button>
                <button onclick="itemCofre(${i.id})" title="Editar" class="px-3 py-1.5 border border-amber-300 text-amber-600 rounded-lg hover:bg-amber-500 hover:text-white"><i class="fa-solid fa-pen"></i></button>
                <button onclick="removerItemCofre(${i.id})" title="Excluir" class="px-3 py-1.5 border border-rose-300 text-rose-600 rounded-lg hover:bg-rose-500 hover:text-white"><i class="fa-solid fa-trash"></i></button>
            </td></tr>`).join('')}</table>` : 'Cofre vazio. Clique em "Novo item".';
}

async function criarCofre() {
    const f = await Swal.fire({
        title: 'Criar cofre',
        html: '<p style="font-size:.8rem;margin-bottom:8px">A senha mestra (mín. 12 caracteres) <b>não pode ser recuperada</b>. Anote-a em local seguro.</p>' +
              '<input id="cv_mestra" type="password" class="swal2-input" placeholder="Senha mestra" autocomplete="new-password">' +
              '<input id="cv_mestra2" type="password" class="swal2-input" placeholder="Repita a senha mestra" autocomplete="new-password">' +
              '<input id="cv_login" type="password" class="swal2-input" placeholder="Sua senha de login" autocomplete="current-password">',
        showCancelButton: true, confirmButtonText: 'Continuar', confirmButtonColor: '#274036',
        preConfirm: () => {
            const m = document.getElementById('cv_mestra').value, m2 = document.getElementById('cv_mestra2').value, l = document.getElementById('cv_login').value;
            if (m.length < 12) return Swal.showValidationMessage('Mínimo de 12 caracteres');
            if (m !== m2) return Swal.showValidationMessage('As senhas não conferem');
            if (!l) return Swal.showValidationMessage('Informe sua senha de login');
            return { m, l };
        }
    });
    if (!f.isConfirmed) return;
    const codigo = await pedirCodigo2fa('Código para criar o cofre');
    if (!codigo) return;
    try {
        const d = await apiFetch('/admin/cofre/criar', 'POST', { senha_mestra: f.value.m, senha: f.value.l, codigo });
        if (d.error) throw new Error(d.error);
        showToast('Cofre criado');
        carregarCofre();
    } catch (e) { showError('Erro', e.message); }
}

async function desbloquearCofre() {
    const m = await Swal.fire({ title: 'Senha mestra', input: 'password', inputAttributes: { autocomplete: 'off' }, showCancelButton: true,
        confirmButtonColor: '#274036', inputValidator: v => v ? null : 'Informe a senha mestra' });
    if (!m.isConfirmed) return;
    const codigo = await pedirCodigo2fa('Código para abrir o cofre');
    if (!codigo) return;
    try {
        const d = await apiFetch('/admin/cofre/desbloquear', 'POST', { senha_mestra: m.value, codigo });
        if (d.error) throw new Error(d.error);
        cofre.ticket = d.ticket; cofre.mestra = m.value;
        clearTimeout(cofre.timer);
        cofre.timer = setTimeout(() => { bloquearCofre(); showToast('Cofre bloqueado por tempo', 'info'); }, (d.expira_em || 300) * 1000);
        await listarItensCofre();
    } catch (e) { showError('Erro', e.message); }
}

async function listarItensCofre() {
    const d = await apiFetch('/admin/cofre/itens', 'GET');
    if (d.error) throw new Error(d.error);
    cofre.itens = d.itens || [];
    renderizarCofre();
}

function credCofre() { return { ticket: cofre.ticket, senha_mestra: cofre.mestra }; }

function tratarErroCofre(d) {
    if (d.bloqueado) bloquearCofre();
    throw new Error(d.error);
}

async function lerItemCofre(id) {
    const d = await apiFetch('/admin/cofre/itens/' + id + '/revelar', 'POST', credCofre());
    if (d.error) tratarErroCofre(d);
    return d;
}

async function verItemCofre(id) {
    try {
        const d = await lerItemCofre(id);
        const r = await Swal.fire({
            title: escapeHtml(d.titulo),
            html: `<div style="text-align:left;font-size:.85rem">
                <div><b>Login:</b> <span class="select-all">${escapeHtml(d.login)}</span></div>
                <div><b>URL:</b> <span class="select-all">${escapeHtml(d.url)}</span></div>
                <div style="margin-top:6px"><b>Senha:</b> <code class="select-all" style="background:#f1f5f9;padding:2px 6px;border-radius:6px">${escapeHtml(d.senha)}</code></div>
                ${d.notas ? `<div style="margin-top:6px;white-space:pre-wrap"><b>Notas:</b> ${escapeHtml(d.notas)}</div>` : ''}
                <p style="font-size:.7rem;color:#94a3b8;margin-top:8px">Fecha sozinho em 30s; a área de transferência é limpa 30s após copiar.</p></div>`,
            showCancelButton: true, confirmButtonText: 'Copiar senha', cancelButtonText: 'Fechar',
            confirmButtonColor: '#274036', timer: 30000, timerProgressBar: true
        });
        if (r.isConfirmed) {
            await navigator.clipboard.writeText(d.senha);
            showToast('Senha copiada (limpa em 30s)');
            setTimeout(() => navigator.clipboard.writeText(' ').catch(() => {}), 30000);
        }
    } catch (e) { showError('Erro', e.message); }
}

async function itemCofre(id) {
    let atual = { titulo: '', login: '', url: '', senha: '', notas: '' };
    try { if (id) atual = await lerItemCofre(id); } catch (e) { return showError('Erro', e.message); }
    const f = await Swal.fire({
        title: id ? 'Editar item' : 'Novo item',
        html: `<input id="ci_titulo" class="swal2-input" placeholder="Título (ex.: Registro.br)" maxlength="120" value="${escapeHtml(atual.titulo)}">
               <input id="ci_login" class="swal2-input" placeholder="Login / usuário" autocomplete="off" value="${escapeHtml(atual.login)}">
               <input id="ci_url" class="swal2-input" placeholder="URL" autocomplete="off" value="${escapeHtml(atual.url)}">
               <input id="ci_senha" type="text" class="swal2-input" placeholder="Senha" autocomplete="off" value="${escapeHtml(atual.senha)}">
               <textarea id="ci_notas" class="swal2-textarea" placeholder="Notas">${escapeHtml(atual.notas)}</textarea>`,
        showCancelButton: true, confirmButtonText: 'Salvar', confirmButtonColor: '#274036',
        preConfirm: () => {
            const v = { titulo: document.getElementById('ci_titulo').value.trim(), login: document.getElementById('ci_login').value,
                url: document.getElementById('ci_url').value, senha: document.getElementById('ci_senha').value, notas: document.getElementById('ci_notas').value };
            if (!v.titulo) return Swal.showValidationMessage('Informe o título');
            return v;
        }
    });
    if (!f.isConfirmed) return;
    try {
        const d = await apiFetch('/admin/cofre/itens', 'POST', { ...credCofre(), id: id || 0, ...f.value });
        if (d.error) tratarErroCofre(d);
        showToast('Item salvo');
        await listarItensCofre();
    } catch (e) { showError('Erro', e.message); }
}

async function removerItemCofre(id) {
    const c = await confirmar('Excluir item?', 'Esta ação não pode ser desfeita.', 'Excluir');
    if (!c.isConfirmed) return;
    try {
        const d = await apiFetch('/admin/cofre/itens/' + id + '/remover', 'POST', credCofre());
        if (d.error) tratarErroCofre(d);
        await listarItensCofre();
    } catch (e) { showError('Erro', e.message); }
}

document.addEventListener('DOMContentLoaded', () => { carregar(); carregarSeguranca(); carregarCofre(); });
</script>

<?php require_once __DIR__ . '/components/footer.php'; ?>
