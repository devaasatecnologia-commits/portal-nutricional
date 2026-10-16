<?php
$pageTitle = 'Verificacao em duas etapas | Portal Nutricional';
require_once __DIR__ . '/admin/components/header.php';
// sem sidebar de proposito: tela enxuta so para ativar o 2FA
?>

<div class="max-w-2xl mx-auto py-8">
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-6 mb-6">
        <div class="flex items-start gap-3 mb-4">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-2xl flex items-center justify-center">
                <i class="fa-solid fa-shield-halved text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800">Verificacao em duas etapas</h2>
                <p class="text-xs text-slate-400">Proteja sua conta com um app autenticador (Google, Microsoft, Authy).</p>
            </div>
        </div>

        <div id="statusBox" class="text-sm text-slate-500">Carregando...</div>
        <div id="acoesBox" class="mt-4"></div>
    </div>
</div>

<script>
async function carregar() {
    const box = document.getElementById('statusBox');
    const acoes = document.getElementById('acoesBox');
    try {
        const d = await apiFetch('/2fa/status', 'GET');
        if (d.error) throw new Error(d.error);
        if (d.ativo) {
            box.innerHTML = '<span class="text-emerald-600 font-bold">Ativo.</span> Sua conta ja pede o codigo do app no login.';
            acoes.innerHTML = '<button onclick="desativar()" class="px-4 py-2 border border-rose-300 text-rose-600 rounded-xl text-sm font-bold hover:bg-rose-500 hover:text-white">Desativar</button>';
        } else if (d.pendente) {
            box.innerHTML = '<span class="text-amber-600 font-bold">Pendente.</span> Voce ja iniciou o cadastro; reinicie para gerar um novo segredo.';
            acoes.innerHTML = '<button onclick="iniciar()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">Reiniciar cadastro</button>';
        } else {
            box.innerHTML = '<span class="text-slate-600 font-bold">Nao ativado.</span> Ative para aumentar a seguranca da sua conta.';
            acoes.innerHTML = '<button onclick="iniciar()" class="px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">Ativar 2FA</button>';
        }
    } catch (e) {
        box.textContent = 'Erro ao carregar status: ' + e.message;
    }
}

async function iniciar() {
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

    let d;
    try {
        d = await apiFetch('/2fa/iniciar', 'POST', { senha: s.value });
    } catch (e) {
        return showError('Erro', e.message);
    }
    if (d.error) return showError('Erro', d.error);

    const r = await Swal.fire({
        title: 'Configure o autenticador',
        html: `<p class="text-sm mb-2">No app, escolha <b>Adicionar conta &gt; Inserir chave de configuracao</b> e informe:</p>
               <div class="bg-slate-100 p-3 rounded-xl font-mono text-sm break-all select-all">${escapeHtml(d.segredo)}</div>
               <p class="text-xs text-slate-400 mt-2">Tipo: baseado em tempo. Depois digite abaixo o codigo de 6 digitos gerado.</p>`,
        input: 'text',
        inputAttributes: { inputmode: 'numeric', maxlength: 6, autocomplete: 'one-time-code' },
        showCancelButton: true,
        confirmButtonText: 'Ativar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        inputValidator: v => /^\d{6}$/.test((v || '').trim()) ? null : 'Informe os 6 digitos'
    });
    if (!r.isConfirmed) return;

    let c;
    try {
        c = await apiFetch('/2fa/confirmar', 'POST', { codigo: r.value.trim() });
    } catch (e) {
        return showError('Erro', e.message);
    }
    if (c.error) return showError('Erro', c.error);

    showToast('2FA ativado');
    carregar();
}

async function desativar() {
    const s = await Swal.fire({
        title: 'Confirme sua senha',
        input: 'password',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        inputValidator: v => v ? null : 'Informe a senha'
    });
    if (!s.isConfirmed) return;

    let codigo;
    if (typeof pedirCodigo2fa === 'function') {
        codigo = await pedirCodigo2fa('Codigo para desativar');
    } else {
        const c = await Swal.fire({
            title: 'Codigo do autenticador',
            input: 'text',
            inputAttributes: { inputmode: 'numeric', maxlength: 6, autocomplete: 'one-time-code' },
            showCancelButton: true,
            confirmButtonColor: '#274036',
            inputValidator: v => /^\d{6}$/.test((v || '').trim()) ? null : 'Informe os 6 digitos'
        });
        if (!c.isConfirmed) return;
        codigo = c.value.trim();
    }
    if (!codigo) return;

    let d;
    try {
        d = await apiFetch('/2fa/desativar', 'POST', { senha: s.value, codigo });
    } catch (e) {
        return showError('Erro', e.message);
    }
    if (d.error) return showError('Erro', d.error);

    showToast('2FA desativado');
    carregar();
}

document.addEventListener('DOMContentLoaded', carregar);
</script>

<?php require_once __DIR__ . '/admin/components/footer.php'; ?>