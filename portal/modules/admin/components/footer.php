</main><!-- Fim main-content -->

<script>
// Funções globais para o módulo admin
const API_BASE = `${window.location.origin}/index.php?api_route=`;

// Obter token JWT
function getAuthToken() {
    return localStorage.getItem('authToken') || sessionStorage.getItem('authToken');
}

function getCsrfToken(token = getAuthToken()) {
    try {
        if (!token) return '';
        const payload = JSON.parse(atob(token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/')));
        const uid = Number(payload.uid || 0);
        const today = new Date();
        const date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        const value = uid ? `${uid}${date}` : '';
        return value && window.CryptoJS ? CryptoJS.MD5(value).toString() : (value ? btoa(value).substring(0, 32) : '');
    } catch (error) {
        return '';
    }
}

// Fetch autenticado
async function apiFetch(endpoint, method = 'GET', body = null) {
    const token = getAuthToken();
    if (!token) {
        window.location.href = '/portal/login.php';
        throw new Error('Não autenticado');
    }
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + token,
            'X-CSRF-Token': getCsrfToken(token)
        }
    };
    
    if (body) {
        if (window.__waCodigo && method !== 'GET' && typeof body === 'object' && !body.codigo_whatsapp) {
            body.codigo_whatsapp = window.__waCodigo;
            window.__waCodigo = null;
        }
        options.body = JSON.stringify(body);
    }
    
    const cleanEndpoint = endpoint.startsWith('/v1/') ? endpoint.substring(4) : endpoint;
    const response = await fetch(`${API_BASE}${cleanEndpoint}`, options);
    const text = await response.text();
    
    try {
        return JSON.parse(text);
    } catch (e) {
        throw new Error(text || 'Erro na requisição');
    }
}

// Formatar data
function formatarData(data) {
    if (!data) return '-';
    return new Date(data).toLocaleString('pt-BR');
}

// ✅ Formatar duração (CORRIGIDO)
function formatarDuracao(segundos) {
    if (!segundos) return '-';
    
    const seg = parseFloat(segundos);
    
    if (isNaN(seg)) return '-';
    if (seg < 60) return seg.toFixed(2) + 's';
    
    const minutos = Math.floor(seg / 60);
    const segs = (seg % 60).toFixed(0);
    return `${minutos}m ${segs}s`;
}

// SweetAlert helpers
function showLoading(title = 'Processando...') {
    Swal.fire({
        title: title,
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
}

function showSuccess(title, text = '') {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'success',
        confirmButtonColor: '#274036'
    });
}

function showError(title, text = '') {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'error',
        confirmButtonColor: '#274036'
    });
}

async function confirmar(title, text, confirmText = 'Sim') {
    return Swal.fire({
        title: title,
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d'
    });
}

// Toast
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

function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}

// Pede o codigo do app autenticador (TOTP) e, se o usuario tiver WhatsApp cadastrado, o codigo de 5 min
// enviado por WhatsApp. Retorna o TOTP (ou '' quando totp=false) e deixa o codigo do WhatsApp em
// window.__waCodigo, que o proximo apiFetch POST envia como codigo_whatsapp. Retorna null se cancelado.
async function pedirCodigo2fa(titulo = 'Verificação em duas etapas', totp = true) {
    let exigeWhats = false, destino = '';
    try {
        const st = await apiFetch('/admin/whatsapp/status');
        exigeWhats = !!st.exigido; destino = st.destino || '';
    } catch (e) { /* segue so com TOTP */ }
    window.__waCodigo = null;

    const campo = (id, rotulo) => `<label class="block text-left text-xs font-semibold text-slate-600 mt-3 mb-1">${rotulo}</label>
        <input id="${id}" class="swal2-input !m-0 !w-full" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000">`;
    const html = (totp ? campo('vTotp', 'Código do aplicativo autenticador') : '')
        + (exigeWhats ? campo('vWhats', `Código enviado por WhatsApp (${escapeHtml(destino)}) - vale 5 min`)
            + '<button type="button" id="vEnviar" class="mt-2 text-sm text-emerald-700 font-semibold underline">Enviar código por WhatsApp</button>' : '');
    if (!totp && !exigeWhats) return '';

    const r = await Swal.fire({
        title: titulo,
        html,
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        didOpen: () => {
            const b = document.getElementById('vEnviar');
            if (!b) return;
            b.onclick = async () => {
                b.disabled = true; b.textContent = 'Enviando...';
                try {
                    const e = await apiFetch('/admin/whatsapp/enviar', 'POST', {});
                    if (e.error) throw new Error(e.error);
                    b.textContent = 'Código enviado. Reenviar em 60s';
                    setTimeout(() => { b.disabled = false; b.textContent = 'Reenviar código'; }, 60000);
                } catch (err) {
                    b.disabled = false; b.textContent = 'Enviar código por WhatsApp';
                    Swal.showValidationMessage(err.message);
                }
            };
        },
        preConfirm: () => {
            const t = (document.getElementById('vTotp')?.value || '').trim();
            const w = (document.getElementById('vWhats')?.value || '').trim();
            if (totp && !/^\d{6}$/.test(t)) return Swal.showValidationMessage('Informe os 6 dígitos do autenticador');
            if (exigeWhats && !/^\d{6}$/.test(w)) return Swal.showValidationMessage('Informe os 6 dígitos do WhatsApp');
            return { t, w };
        }
    });
    if (!r.isConfirmed) return null;
    window.__waCodigo = r.value.w || null;
    return r.value.t;
}

// Revela um valor sensivel (exige 2FA). Mostra por alguns segundos e permite copiar.
async function revelarSegredo(endpoint, campo, titulo) {
    const codigo = await pedirCodigo2fa();
    if (!codigo) return;
    try {
        const r = await apiFetch(endpoint, 'POST', { codigo });
        if (r.requer_2fa_setup) {
            return showError('2FA não configurado', 'Ative a verificação em duas etapas em Admin > Segredos & Tokens.');
        }
        if (r.error) throw new Error(r.error);
        await Swal.fire({
            title: titulo,
            html: `<div class="bg-slate-100 p-4 rounded-xl font-mono text-sm break-all select-all">${escapeHtml(r[campo])}</div>
                   <p class="text-xs text-slate-400 mt-2">Esta janela mostra o valor apenas agora. O acesso foi registrado na auditoria.</p>`,
            showCancelButton: true,
            confirmButtonText: 'Copiar',
            cancelButtonText: 'Fechar',
            confirmButtonColor: '#274036'
        }).then(x => { if (x.isConfirmed) { navigator.clipboard.writeText(r[campo]); showToast('Copiado!'); } });
    } catch (e) {
        showError('Erro', e.message);
    }
}
</script>

</body>
</html>