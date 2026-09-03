// ======================================================================
// MODULO FROTA - FUNCOES COMPARTILHADAS
// ======================================================================

/**
 * Obtem o token de autenticacao do localStorage
 */
function getAuthToken() {
    return localStorage.getItem('authToken');
}

/**
 * Verifica se o usuario esta autenticado
 */
function isAuthenticated() {
    return getAuthToken() !== null;
}

/**
 * Formata data e hora
 */
function formatarDataHora(dataString) {
    if (!dataString) return '-';
    try {
        const data = new Date(dataString);
        return data.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dataString;
    }
}

/**
 * Formata apenas data
 */
function formatarData(dataString) {
    if (!dataString) return '-';
    try {
        const data = new Date(dataString);
        return data.toLocaleDateString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    } catch (e) {
        return dataString;
    }
}

/**
 * Formata apenas hora
 */
function formatarHora(dataString) {
    if (!dataString) return '-';
    try {
        const data = new Date(dataString);
        return data.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dataString;
    }
}

/**
 * Obtem a classe CSS para status
 */
function getStatusClass(status) {
    const classes = {
        'entregue': 'success',
        'pendente': 'warning',
        'em_andamento': 'info',
        'falha': 'danger',
        'cancelada': 'secondary',
        'planejado': 'primary',
        'finalizado': 'success',
        'disponivel': 'success',
        'em_rota': 'warning',
        'manutencao': 'danger',
        'inativo': 'secondary'
    };
    return classes[status] || 'secondary';
}

/**
 * Obtem o texto do status
 */
function getStatusText(status) {
    const texts = {
        'entregue': 'Entregue',
        'pendente': 'Pendente',
        'em_andamento': 'Em Andamento',
        'falha': 'Falha',
        'cancelada': 'Cancelada',
        'planejado': 'Planejado',
        'finalizado': 'Finalizado',
        'disponivel': 'Disponivel',
        'em_rota': 'Em Rota',
        'manutencao': 'Manutencao',
        'inativo': 'Inativo'
    };
    return texts[status] || status;
}

// Exportar funcoes para uso global
window.getAuthToken = getAuthToken;
window.isAuthenticated = isAuthenticated;
window.formatarDataHora = formatarDataHora;
window.formatarData = formatarData;
window.formatarHora = formatarHora;
window.getStatusClass = getStatusClass;
window.getStatusText = getStatusText;