/**
 * Gerenciamento de autenticação JWT
 */

const TOKEN_KEY = 'authToken';
const USER_KEY = 'userData';

function getAuthToken() {
    return localStorage.getItem(TOKEN_KEY);
}

function getUserData() {
    const data = localStorage.getItem(USER_KEY);
    return data ? JSON.parse(data) : null;
}

function setAuthData(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
}



/**
 * Fetch wrapper que inclui automaticamente o token JWT
 */
async function fetchWithAuth(url, options = {}) {
    const token = getAuthToken();
    if (!token) {
        logout();
        throw new Error('Não autenticado');
    }

    const headers = {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        ...options.headers
    };

    const response = await fetch(url, { ...options, headers });

    if (response.status === 401) {
        logout();
        throw new Error('Sessão expirada');
    }

    return response;
}