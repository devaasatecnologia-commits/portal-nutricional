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

// Pede o codigo de 6 digitos do app autenticador. Retorna o codigo ou null se cancelado.
async function pedirCodigo2fa(titulo = 'Verificação em duas etapas') {
    const r = await Swal.fire({
        title: titulo,
        text: 'Digite o código de 6 dígitos do seu aplicativo autenticador.',
        input: 'text',
        inputAttributes: { inputmode: 'numeric', maxlength: 6, autocomplete: 'one-time-code' },
        showCancelButton: true,
        confirmButtonText: 'Confirmar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        inputValidator: v => /^\d{6}$/.test((v || '').trim()) ? null : 'Informe os 6 dígitos'
    });
    return r.isConfirmed ? r.value.trim() : null;
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