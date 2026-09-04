</main><!-- Fim main-content -->

<script>
// Funções globais para o módulo admin
const API_BASE = `${window.location.origin}/index.php?api_route=`;

// Obter token JWT
function getAuthToken() {
    return localStorage.getItem('authToken');
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
            'Authorization': 'Bearer ' + token
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
</script>

</body>
</html>