// ==========================================================================
// CONFIGURAÇÕES DA API
// ==========================================================================
const API_URL = 'https://api.nutricionalbr.com';

// ==========================================================================
// FUNÇÕES PARA CAPTURAR USUÁRIO (INJETADO VIA PHP OU LOCALSTORAGE)
// ==========================================================================
function getUserId() {
    const el = document.getElementById('user_id');
    if (el && el.value && el.value !== '0' && el.value !== '') {
        return el.value;
    }
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    if (userData.uid && userData.uid !== '0') {
        return userData.uid;
    }
    return '0';
}

function getUserNome() {
    const el = document.getElementById('user_nome');
    if (el && el.value) return el.value;
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    return userData.username || 'SISTEMA';
}

// ==========================================================================
// FUNÇÃO MESTRE PARA CHAMADAS À API (CORRIGIDA)
// ==========================================================================
async function apiFetch(acao, metodo = 'GET', body = null) {
    let url = `${API_URL}/${acao}`;
    
    if (metodo === 'GET' && body && typeof body === 'object') {
        const params = new URLSearchParams(body).toString();
        if (params) {
            url += '?' + params;
        }
        body = null;
    }

    const options = {
        method: metodo,
        headers: {}
    };

    const token = localStorage.getItem('authToken') || localStorage.getItem('token');
    if (token) {
        options.headers['Authorization'] = 'Bearer ' + token;
    }

    if (body instanceof FormData) {
        options.body = body;
    } else if (metodo !== 'GET' && body) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    try {
        const response = await fetch(url, options);
        
        // Se não for OK, tenta extrair mensagem de erro
        if (!response.ok) {
            const text = await response.text();
            let errorMsg = `Erro ${response.status}: ${response.statusText}`;
            
            // Tenta extrair mensagem do HTML
            if (text && text.length < 500) {
                const cleanText = text.replace(/<[^>]*>/g, '').trim();
                if (cleanText) {
                    errorMsg = cleanText;
                }
            }
            
            throw new Error(errorMsg);
        }
        
        const text = await response.text();
        
        // Se não houver conteúdo, retorna sucesso
        if (!text || !text.trim()) {
            return { success: true };
        }
        
        // Tenta parsear JSON
        try {
            return JSON.parse(text);
        } catch (e) {
            console.warn('[apiFetch] Resposta não é JSON:', text.substring(0, 100));
            return { 
                error: 'Resposta inválida do servidor', 
                details: text.substring(0, 200) 
            };
        }
    } catch (error) {
        console.error('[apiFetch] Erro:', error.message);
        throw error;
    }
}

// ==========================================================================
// ESTADO GLOBAL COMPARTILHADO
// ==========================================================================
const AppState = {
    embarque: '',
    ordem: 'ASC',
    itens: [],
    embarquesDisponiveis: [],
    resumo: {}
};

let scanner = null;
let isProcessing = false;