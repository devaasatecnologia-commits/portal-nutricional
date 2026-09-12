// ==========================================================================
// CONFIGURAÇÃO GLOBAL DA API - VERSÃO UNIFICADA (LOCAL + PRODUÇÃO)
// ==========================================================================

// Detecta automaticamente o ambiente
(function() {
    'use strict';
    
    var hostname = window.location.hostname;
    var isLocal = hostname === 'localhost' || 
                  hostname === '127.0.0.1' ||
                  hostname === '::1' ||
                  hostname.startsWith('192.168.') ||
                  hostname === '192.168.1.99';
    
    // URL da API (GLOBAL), respeitando a pasta de publicação local (/API)
    var appBase = window.location.pathname.split('/portal/')[0];
    window.API_URL = isLocal 
        ? window.location.origin + appBase + '/index.php?api_route=' 
        : 'https://api.nutricionalbr.com/v1';
    
    window.isLocal = isLocal;
    
    // Não declara `const API_URL` — usa window.API_URL em todo lugar
    console.log('🌐 API_URL: ' + window.API_URL + ' (Modo: ' + (isLocal ? 'desenvolvimento' : 'produção') + ')');
})();

// ==========================================================================
// FUNÇÃO GLOBAL PARA REQUISIÇÕES (PADRÃO)
// ==========================================================================
window.apiFetch = async function(endpoint, options) {
    options = options || {};
    var token = localStorage.getItem('authToken');
    
    // Remove /v1/ do início se existir (evita duplicação)
    var cleanEndpoint = endpoint;
    if (cleanEndpoint.startsWith('/v1/')) {
        cleanEndpoint = cleanEndpoint.substring(4);
    }
    if (cleanEndpoint.startsWith('v1/')) {
        cleanEndpoint = cleanEndpoint.substring(3);
    }
    
    var url = window.API_URL + '/' + cleanEndpoint;
    var defaultHeaders = {
        'Content-Type': 'application/json'
    };
    if (token) {
        defaultHeaders['Authorization'] = 'Bearer ' + token;
    }
    
    try {
        var response = await fetch(url, Object.assign({}, options, {
            headers: Object.assign({}, defaultHeaders, options.headers || {})
        }));
        
        if (response.status === 401) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            sessionStorage.clear();
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada');
        }
        
        var text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            if (!response.ok) throw new Error(text || 'HTTP ' + response.status);
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
    var token = localStorage.getItem('authToken');
    
    try {
        if (token) {
            var response = await fetch(window.API_URL + '/auth/logout', {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
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
        localStorage.removeItem('authToken');
        localStorage.removeItem('userData');
        sessionStorage.clear();
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
    
    var token = localStorage.getItem('authToken');
    
    if (!token) {
        window.logout();
        return;
    }
    
    try {
        var response = await fetch(window.API_URL + '/auth/logout-all', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            }
        });
        
        var data = await response.json();
        
        if (data.success || data.error === undefined) {
            alert('✅ Todos os dispositivos foram desconectados');
            await window.logout();
        } else {
            alert('⚠️ Erro: ' + (data.error || 'Falha ao desconectar'));
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
window.fetchWithAuth = async function(url, options) {
    options = options || {};
    var token = localStorage.getItem('authToken');
    if (!token) {
        window.location.href = '/portal/login.php';
        throw new Error('Token não encontrado');
    }
    
    var finalUrl = url;
    if (url.startsWith('/v1/')) {
        finalUrl = window.API_URL + url.substring(4);
    } else if (!url.startsWith('http')) {
        finalUrl = window.API_URL + '/' + url;
    }
    
    var headers = Object.assign({
        'Authorization': 'Bearer ' + token,
        'Content-Type': 'application/json'
    }, options.headers || {});
    
    var response = await fetch(finalUrl, Object.assign({}, options, { headers: headers }));
    
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
// CSRF TOKEN
// ==========================================================================
function getCsrfToken() {
    try {
        var userData = JSON.parse(localStorage.getItem('userData') || '{}');
        var uid = userData.uid || 0;
        var today = new Date().toISOString().slice(0, 10);
        var str = uid + today;
        
        if (typeof CryptoJS !== 'undefined' && CryptoJS.MD5) {
            return CryptoJS.MD5(str).toString();
        } else {
            console.warn('CryptoJS não disponível, usando fallback');
            return btoa(str).substring(0, 32);
        }
    } catch (error) {
        console.error('Erro ao gerar CSRF token:', error);
        return 'fallback_token_' + Date.now();
    }
}

// Interceptar fetch para adicionar CSRF token
(function() {
    var originalFetch = window.fetch;
    window.fetch = function(url, options) {
        options = options || {};
        var urlStr = (typeof url === 'string') ? url : (url && url.url) ? url.url : String(url);
        var method = options.method || 'GET';
        var needsCsrf = ['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(method.toUpperCase()) !== -1;
        var isPublicRoute = urlStr.indexOf('/auth/login') !== -1 || 
                            urlStr.indexOf('/ping') !== -1 || 
                            urlStr.indexOf('/sistema/modulos-setores') !== -1;
        
        if (needsCsrf && !isPublicRoute) {
            var csrfToken = getCsrfToken();
            options.headers = Object.assign({}, options.headers || {}, {
                'X-CSRF-Token': csrfToken
            });
        }
        
        return originalFetch(url, options);
    };
})();

// ==========================================================================
// FUNÇÃO LEGADA apiFetch (para compatibilidade)
// ==========================================================================
window.legacyApiFetch = async function(acao, metodo, body) {
    metodo = metodo || 'GET';
    body = body || null;
    
    var url = window.API_URL + '/' + acao;
    
    if (metodo === 'GET' && body) {
        var params = new URLSearchParams(body).toString();
        url += '?' + params;
    }

    var options = {
        method: metodo,
        headers: { 'Content-Type': 'application/json' }
    };

    var token = localStorage.getItem('authToken');
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }

    if (metodo !== 'GET' && body) {
        options.body = JSON.stringify(body);
    }

    try {
        var response = await fetch(url, options);
        var text = await response.text();
        
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
window.isTokenExpiringSoon = function(minutesBefore) {
    minutesBefore = minutesBefore || 5;
    var token = localStorage.getItem('authToken');
    if (!token) return true;
    
    try {
        var payload = JSON.parse(atob(token.split('.')[1]));
        var exp = payload.exp * 1000;
        var now = Date.now();
        var timeToExpire = exp - now;
        var minutesToExpire = timeToExpire / (1000 * 60);
        
        return minutesToExpire <= minutesBefore;
    } catch (error) {
        console.error('Erro ao verificar expiração do token:', error);
        return true;
    }
};

// ==========================================================================
// VERIFICAÇÃO PERIÓDICA DO TOKEN (a cada minuto)
// ==========================================================================
if (typeof window !== 'undefined') {
    setInterval(function() {
        if (window.isTokenExpiringSoon && window.isTokenExpiringSoon(5)) {
            console.warn('⚠️ Token próximo de expirar. Considere renovar ou fazer logout.');
        }
    }, 60000);
}

console.log('🔐 CSRF Protection: ' + (typeof CryptoJS !== 'undefined' ? '✅ Ativo' : '⚠️ CryptoJS não carregado'));