// ==========================================================================
// CONFIGURAÇÃO GLOBAL DA API - VERSÃO FORTALECIDA
// ==========================================================================

// Detecta automaticamente o ambiente
const hostname = window.location.hostname;
const isLocal = hostname === 'localhost' || 
                hostname === '127.0.0.1' ||
                hostname === '::1' ||
                hostname.startsWith('192.168.') ||
                hostname === '192.168.1.99';

// URL da API (GLOBAL), respeitando a pasta de publicação local (/API).
const appBase = window.location.pathname.split('/portal/')[0];
window.API_URL = isLocal ? `${window.location.origin}${appBase}/index.php?api_route=` : 'https://api.nutricionalbr.com/v1';

// Versão simplificada
const API_URL = window.API_URL;

// ==========================================================================
// FUNÇÃO GLOBAL PARA REQUISIÇÕES (PADRÃO)
// ==========================================================================
window.apiFetch = async (endpoint, options = {}) => {
    const token = localStorage.getItem('authToken');
    
    // Remove /v1/ do início se existir (evita duplicação)
    let cleanEndpoint = endpoint;
    if (cleanEndpoint.startsWith('/v1/')) {
        cleanEndpoint = cleanEndpoint.substring(4);
    }
    if (cleanEndpoint.startsWith('v1/')) {
        cleanEndpoint = cleanEndpoint.substring(3);
    }
    
    const url = `${API_URL}/${cleanEndpoint}`;
    const defaultHeaders = {
        'Content-Type': 'application/json',
        ...(token && { 'Authorization': `Bearer ${token}` })
    };
    
    try {
        const response = await fetch(url, {
            ...options,
            headers: { ...defaultHeaders, ...options.headers }
        });
        
        // Se for 401, redireciona para login
        if (response.status === 401) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            sessionStorage.clear();
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada');
        }
        
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            if (!response.ok) throw new Error(text || `HTTP ${response.status}`);
            return text;
        }
    } catch (error) {
        console.error('Erro no apiFetch:', error);
        throw error;
    }
};

// ==========================================================================
// FUNÇÃO DE LOGOUT (com revogação de token na API)
// ==========================================================================
window.logout = async function() {
    const token = localStorage.getItem('authToken');
    
    try {
        if (token) {
            // Chamar API de logout para revogar o token
            const response = await fetch(`${API_URL}/auth/logout`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`,
                    'Content-Type': 'application/json'
                }
            });
            
            if (response.ok) {
                console.log('✅ Token revogado com sucesso na API');
            } else {
                console.warn('⚠️ Falha ao revogar token na API, mas continuando logout local');
            }
        }
    } catch (e) {
        console.error('Erro no logout:', e);
    } finally {
        // Limpar dados locais (sempre acontece)
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        sessionStorage.clear();
        
        // Redirecionar para login
        window.location.href = '/portal/login.php';
    }
};

// ==========================================================================
// LOGOUT DE TODOS OS DISPOSITIVOS
// ==========================================================================
window.logoutAll = async function() {
    if (!confirm('⚠️ Isso irá desconectar TODOS os seus dispositivos. Continuar?')) {
        return;
    }
    
    const token = localStorage.getItem('authToken');
    
    if (!token) {
        window.logout();
        return;
    }
    
    try {
        const response = await fetch(`${API_URL}/auth/logout-all`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json'
            }
        });
        
        const data = await response.json();
        
        if (data.success || data.error === undefined) {
            alert('✅ Todos os dispositivos foram desconectados');
            await window.logout();
        } else {
            alert('⚠️ Erro: ' + (data.error || 'Falha ao desconectar'));
            // Mesmo com erro, faz logout local
            window.logout();
        }
    } catch (e) {
        console.error('Erro no logoutAll:', e);
        alert('⚠️ Erro ao desconectar dispositivos. Fazendo logout local...');
        window.logout();
    }
};

// ==========================================================================
// FUNÇÃO PARA REQUISIÇÕES COM AUTENTICAÇÃO (estilo antigo)
// ==========================================================================
window.fetchWithAuth = async (url, options = {}) => {
    const token = localStorage.getItem('authToken');
    if (!token) {
        window.location.href = '/portal/login.php';
        throw new Error('Token não encontrado');
    }
    
    // Se a URL começar com /v1/, converte para usar API_URL
    let finalUrl = url;
    if (url.startsWith('/v1/')) {
        finalUrl = `${API_URL}${url.substring(4)}`;
    } else if (!url.startsWith('http')) {
        finalUrl = `${API_URL}/${url}`;
    }
    
    const headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    const response = await fetch(finalUrl, { ...options, headers });
    
    if (response.status === 401) {
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        sessionStorage.clear();
        window.location.href = '/portal/login.php';
        throw new Error('Sessão expirada');
    }
    
    return response;
};

// ==========================================================================
// CSRF TOKEN (enviar em todas as requisições POST/PUT/DELETE)
// ==========================================================================
function getCsrfToken() {
    try {
        const userData = JSON.parse(localStorage.getItem('userData') || '{}');
        const uid = userData.uid || 0;
        const today = new Date().toISOString().slice(0, 10);
        const str = uid + today;
        
        // Verificar se CryptoJS está disponível
        if (typeof CryptoJS !== 'undefined' && CryptoJS.MD5) {
            return CryptoJS.MD5(str).toString();
        } else {
            console.warn('CryptoJS não disponível, usando fallback');
            // Fallback simples (apenas para não quebrar)
            return btoa(str).substring(0, 32);
        }
    } catch (error) {
        console.error('Erro ao gerar CSRF token:', error);
        return 'fallback_token_' + Date.now();
    }
}

// Interceptar fetch para adicionar CSRF token (apenas para métodos que modificam dados)
const originalFetch = window.fetch;
window.fetch = function(url, options = {}) {
    // Verificar se é uma requisição que precisa de CSRF
    const method = options.method || 'GET';
    const needsCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase());
    
    // Verificar se não é uma rota pública que não precisa de CSRF
    const isPublicRoute = url.includes('/auth/login') || 
                          url.includes('/ping') || 
                          url.includes('/sistema/modulos-setores');
    
    if (needsCsrf && !isPublicRoute) {
        const csrfToken = getCsrfToken();
        options.headers = {
            ...options.headers,
            'X-CSRF-Token': csrfToken
        };
    }
    
    return originalFetch(url, options);
};

// ==========================================================================
// FUNÇÃO LEGADA apiFetch (para compatibilidade)
// ==========================================================================
window.legacyApiFetch = async (acao, metodo = 'GET', body = null) => {
    let url = `${API_URL}/${acao}`;
    
    if (metodo === 'GET' && body) {
        const params = new URLSearchParams(body).toString();
        url += '?' + params;
    }

    const options = {
        method: metodo,
        headers: { 'Content-Type': 'application/json' }
    };

    const token = localStorage.getItem('authToken');
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }

    if (metodo !== 'GET' && body) {
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, options);
        const text = await response.text();
        
        try {
            return JSON.parse(text);
        } catch (e) {
            if (!response.ok) throw new Error(text || 'Erro ' + response.status);
            return text;
        }
    } catch (error) {
        console.error('Erro no legacyApiFetch:', error);
        throw error;
    }
};

// ==========================================================================
// UTILITÁRIO PARA VERIFICAR SE TOKEN ESTÁ PRÓXIMO DE EXPIRAR
// ==========================================================================
window.isTokenExpiringSoon = function(minutesBefore = 5) {
    const token = localStorage.getItem('authToken');
    if (!token) return true;
    
    try {
        // Decodificar payload do JWT
        const payload = JSON.parse(atob(token.split('.')[1]));
        const exp = payload.exp * 1000;
        const now = Date.now();
        const timeToExpire = exp - now;
        const minutesToExpire = timeToExpire / (1000 * 60);
        
        return minutesToExpire <= minutesBefore;
    } catch (error) {
        console.error('Erro ao verificar expiração do token:', error);
        return true;
    }
};

// ==========================================================================
// INICIAR VERIFICAÇÃO PERIÓDICA DO TOKEN (a cada minuto)
// ==========================================================================
if (typeof window !== 'undefined') {
    setInterval(() => {
        if (window.isTokenExpiringSoon && window.isTokenExpiringSoon(5)) {
            console.warn('⚠️ Token próximo de expirar. Considere renovar ou fazer logout.');
            // Opcional: mostrar notificação para o usuário
            // Você pode implementar um refresh token aqui se tiver
        }
    }, 60000); // Verificar a cada minuto
}

console.log(`🌐 API_URL: ${API_URL} (Modo: ${isLocal ? 'desenvolvimento' : 'produção'})`);
console.log(`🔐 CSRF Protection: ${typeof CryptoJS !== 'undefined' ? '✅ Ativo' : '⚠️ CryptoJS não carregado'}`);