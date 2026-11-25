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
window.apiFetch = async function(endpoint, options, maybeBody) {
    if (typeof options === 'string') {
        var methodStr = options.toUpperCase();
        options = { method: methodStr };
        if (maybeBody !== undefined && maybeBody !== null) {
            if (methodStr === 'GET') {
                var qs = new URLSearchParams(maybeBody).toString();
                if (qs) endpoint += (endpoint.indexOf('?') === -1 ? '?' : '&') + qs;
            } else {
                options.body = (typeof maybeBody === 'string' || (typeof FormData !== 'undefined' && maybeBody instanceof FormData))
                    ? maybeBody
                    : JSON.stringify(maybeBody);
            }
        }
    } else {
        options = options || {};
    }
    var token = localStorage.getItem('authToken');
    
    // Remove /v1/ e barras redundantes do início
    var cleanEndpoint = endpoint || '';
    if (cleanEndpoint.startsWith('/v1/')) {
        cleanEndpoint = cleanEndpoint.substring(4);
    }
    if (cleanEndpoint.startsWith('v1/')) {
        cleanEndpoint = cleanEndpoint.substring(3);
    }
    cleanEndpoint = cleanEndpoint.replace(/^\/+/, '');
    
    var baseUrl = window.API_URL || '/v1';
    var url;
    if (baseUrl.endsWith('=')) {
        url = baseUrl + '/' + cleanEndpoint;
    } else {
        url = baseUrl.replace(/\/+$/, '') + '/' + cleanEndpoint;
    }
    var isFormData = (typeof FormData !== 'undefined') && (options.body instanceof FormData);
    var defaultHeaders = {};
    if (!isFormData) {
        defaultHeaders['Content-Type'] = 'application/json';
    }
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
    
    var isFormData = (typeof FormData !== 'undefined') && (options.body instanceof FormData);
    var defaultHeaders = {
        'Authorization': 'Bearer ' + token
    };
    if (!isFormData) {
        defaultHeaders['Content-Type'] = 'application/json';
    }
    var headers = Object.assign(defaultHeaders, options.headers || {});
    
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
function getCsrfDateSaoPaulo() {
    var now = new Date();
    try {
        var formatted = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'America/Sao_Paulo',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit'
        }).format(now);
        if (/^\d{4}-\d{2}-\d{2}$/.test(formatted)) {
            return formatted;
        }
    } catch (e) {
        // Fallback para data local abaixo
    }
    return now.getFullYear() + '-' +
        String(now.getMonth() + 1).padStart(2, '0') + '-' +
        String(now.getDate()).padStart(2, '0');
}

function getCsrfUid() {
    try {
        var token = localStorage.getItem('authToken') ||
                    sessionStorage.getItem('authToken') ||
                    localStorage.getItem('token');
        if (token && token.indexOf('.') !== -1) {
            var base64 = token.split('.')[1].replace(/-/g, '+').replace(/_/g, '/');
            while (base64.length % 4) base64 += '=';
            var payload = JSON.parse(atob(base64));
            if (payload && payload.uid) {
                return payload.uid;
            }
        }
    } catch (e) {
        // Fallback para userData abaixo
    }
    var userData = JSON.parse(localStorage.getItem('userData') || '{}');
    return userData.uid || 0;
}

function getCsrfToken() {
    try {
        var uid = getCsrfUid();
        var today = getCsrfDateSaoPaulo();
        var str = String(uid) + today;
        
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

// ==========================================================================
// LOADING GLOBAL DO PORTAL (APENAS EM AÇÕES DO USUÁRIO E ABERTURA DE TELA)
// ==========================================================================
(function() {
    var activeFetches = 0;
    var showTimer = null;
    var elapsedTimer = null;
    var startTime = 0;
    var domReady = false;
    var pageLoadUntil = Date.now() + 2600;
    var lastUserActionAt = 0;

    function markUserAction() {
        lastUserActionAt = Date.now();
    }

    // Registra apenas interações intencionais do usuário (ignora mousemove/scroll passivos)
    ['pointerdown', 'mousedown', 'touchstart', 'change', 'submit'].forEach(function(evName) {
        window.addEventListener(evName, markUserAction, { capture: true, passive: true });
    });
    window.addEventListener('keydown', function(ev) {
        var k = ev && ev.key;
        if (k === 'Enter' || k === ' ' || k === 'Tab' || (ev.target && /^(INPUT|SELECT|TEXTAREA)$/.test(ev.target.tagName))) {
            markUserAction();
        }
    }, { capture: true, passive: true });

    function ensureLoaderDom() {
        if (document.getElementById('nutri-global-loader-style')) return true;
        if (!document.head || !document.body) return false;

        var st = document.createElement('style');
        st.id = 'nutri-global-loader-style';
        st.textContent =
            '#nutri-top-progress{position:fixed;top:0;left:0;right:0;height:4px;z-index:99999;pointer-events:none;opacity:0;transition:opacity .2s ease;overflow:hidden;background:rgba(15,23,42,.08)}' +
            '#nutri-top-progress.is-active{opacity:1}' +
            '#nutri-top-progress::after{content:"";display:block;height:100%;width:42%;background:linear-gradient(90deg,#059669,#E8C87E,#10b981);border-radius:0 4px 4px 0;animation:nutriBarSlide 1.15s infinite ease-in-out}' +
            '@keyframes nutriBarSlide{0%{transform:translateX(-110%)}100%{transform:translateX(260%)}}' +
            '#nutri-global-loader{position:fixed;inset:0;z-index:99998;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.22);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .18s ease}' +
            '#nutri-global-loader.is-active{opacity:1;pointer-events:auto}' +
            '.nutri-loader-card{background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;padding:16px 22px;box-shadow:0 20px 45px rgba(15,23,42,.18);display:flex;align-items:center;gap:14px;max-width:90vw}' +
            '.nutri-loader-spin{width:34px;height:34px;border-radius:50%;border:3.5px solid #d1fae5;border-top-color:#059669;border-right-color:#E8C87E;animation:nutriSpin .75s linear infinite;flex-shrink:0}' +
            '@keyframes nutriSpin{to{transform:rotate(360deg)}}' +
            '.nutri-loader-title{font-family:"Plus Jakarta Sans",system-ui,sans-serif;font-size:13.5px;font-weight:800;color:#0f172a;line-height:1.25}' +
            '.nutri-loader-sub{font-family:"Plus Jakarta Sans",system-ui,sans-serif;font-size:11.5px;font-weight:600;color:#64748b;margin-top:2px}';
        document.head.appendChild(st);

        var bar = document.createElement('div');
        bar.id = 'nutri-top-progress';
        document.body.appendChild(bar);

        var box = document.createElement('div');
        box.id = 'nutri-global-loader';
        box.setAttribute('aria-live', 'polite');
        box.innerHTML =
            '<div class="nutri-loader-card">' +
                '<div class="nutri-loader-spin"></div>' +
                '<div>' +
                    '<div class="nutri-loader-title" id="nutri-loader-msg">Carregando dados, aguarde...</div>' +
                    '<div class="nutri-loader-sub" id="nutri-loader-sub">Consultando servidor Nutricional</div>' +
                '</div>' +
            '</div>';
        document.body.appendChild(box);
        domReady = true;
        return true;
    }

    function renderVisible(msg) {
        if (!ensureLoaderDom()) return;
        var bar = document.getElementById('nutri-top-progress');
        var box = document.getElementById('nutri-global-loader');
        var mEl = document.getElementById('nutri-loader-msg');
        var sEl = document.getElementById('nutri-loader-sub');
        if (mEl && msg) mEl.textContent = msg;
        if (sEl) sEl.textContent = 'Consultando servidor Nutricional...';
        if (bar) bar.classList.add('is-active');
        if (box) box.classList.add('is-active');

        clearInterval(elapsedTimer);
        startTime = Date.now();
        elapsedTimer = setInterval(function() {
            var sec = Math.floor((Date.now() - startTime) / 1000);
            if (sEl && sec >= 2) {
                sEl.textContent = 'Processando informações no servidor... (' + sec + 's)';
            }
        }, 1000);
    }

    function hideVisible() {
        clearTimeout(showTimer);
        showTimer = null;
        clearInterval(elapsedTimer);
        elapsedTimer = null;
        if (!domReady) return;
        var bar = document.getElementById('nutri-top-progress');
        var box = document.getElementById('nutri-global-loader');
        if (bar) bar.classList.remove('is-active');
        if (box) box.classList.remove('is-active');
    }

    function isSilentUrl(urlStr) {
        var u = String(urlStr || '').toLowerCase();
        return u.indexOf('/notificacoes') !== -1 ||
               u.indexOf('/resumo-geral') !== -1 ||
               u.indexOf('/chat/') !== -1 ||
               u.indexOf('/mensagens') !== -1 ||
               u.indexOf('/nao-lidas') !== -1 ||
               u.indexOf('/inatividade') !== -1 ||
               u.indexOf('/ping') !== -1 ||
               u.indexOf('/heartbeat') !== -1 ||
               u.indexOf('/online') !== -1 ||
               u.indexOf('/unread') !== -1 ||
               u.indexOf('/badge') !== -1 ||
               u.indexOf('silent=1') !== -1;
    }

    function shouldShowLoaderForRequest(urlStr, options, needsCsrf) {
        if (options && options.silent) return false;
        if (isSilentUrl(urlStr)) return false;
        if (options && options.showLoader === true) return true;
        var now = Date.now();
        // Abertura inicial da tela/módulo
        if (now <= pageLoadUntil) return true;
        // Ação direta do usuário nos últimos 1.6s (clique, filtro, busca, submit, encadeamento imediato)
        if ((now - lastUserActionAt) <= 1600) return true;
        // Já existe um carregamento ativo iniciado por ação do usuário (ex.: segunda chamada encadeada após carregarContexto)
        if (activeFetches > 0) return true;
        // Mutações explícitas (POST/PUT/DELETE/PATCH) não silenciosas
        if (needsCsrf) return true;
        // Qualquer timer/setInterval em background sem ação do usuário fica 100% silencioso!
        return false;
    }

    window.PortalLoader = {
        show: function(msg) { markUserAction(); renderVisible(msg || 'Carregando dados, aguarde...'); },
        hide: function() { activeFetches = 0; hideVisible(); }
    };

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
            if (typeof Headers !== 'undefined' && options.headers instanceof Headers) {
                options.headers.set('X-CSRF-Token', csrfToken);
            } else {
                options.headers = Object.assign({}, options.headers || {}, {
                    'X-CSRF-Token': csrfToken
                });
            }
        }

        var trackLoader = shouldShowLoaderForRequest(urlStr, options, needsCsrf);
        if (trackLoader) {
            activeFetches++;
            if (ensureLoaderDom()) {
                var bar = document.getElementById('nutri-top-progress');
                if (bar) bar.classList.add('is-active');
            }
            if (!showTimer) {
                var label = needsCsrf ? 'Salvando e processando, aguarde...' : 'Carregando dados, aguarde...';
                showTimer = setTimeout(function() {
                    if (activeFetches > 0) renderVisible(label);
                }, 320);
            }
        }

        return originalFetch(url, options).finally(function() {
            if (trackLoader) {
                activeFetches = Math.max(0, activeFetches - 1);
                if (activeFetches === 0) {
                    hideVisible();
                }
            }
        });
    };

    document.addEventListener('DOMContentLoaded', function() {
        ensureLoaderDom();
        document.addEventListener('click', function(ev) {
            var a = ev.target && ev.target.closest ? ev.target.closest('a[href]') : null;
            if (!a) return;
            var href = a.getAttribute('href') || '';
            if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) return;
            if (a.target === '_blank' || a.hasAttribute('download') || ev.ctrlKey || ev.metaKey || ev.shiftKey) return;
            if (href.indexOf('/portal/') !== -1 || href.indexOf('.php') !== -1) {
                renderVisible('Abrindo módulo, aguarde...');
            }
        });
    });

    window.addEventListener('pageshow', function() {
        if (activeFetches === 0) hideVisible();
    });
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


// ==========================================================================
// FOTOS DE PRODUTOS (HFS via Cloudflare Tunnel - fotos.nutricionalbr.com)
// A porta 2053 do servidor nao e mais exposta; todos os modulos usam esta base.
// ==========================================================================
window.FOTOS_BASE_URL = window.FOTOS_BASE_URL || 'https://fotos.nutricionalbr.com/fotos/';

// Fallback global: se qualquer foto do HFS falhar, troca por um icone neutro (sem ficar "carregando")
(function() {
    var PLACEHOLDER = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="10" fill="#f1f5f9"/>' +
        '<path d="M14 44l12-14 9 10 6-7 9 11z" fill="#cbd5e1"/><circle cx="23" cy="21" r="5" fill="#cbd5e1"/></svg>'
    );
    window.addEventListener('error', function(ev) {
        var el = ev && ev.target;
        if (!el || el.tagName !== 'IMG') return;
        var src = String(el.currentSrc || el.src || '');
        if (src.indexOf('fotos.nutricionalbr.com') === -1 && src.indexOf(':2053/fotos/') === -1) return;
        if (el.dataset && el.dataset.fotoFallback === '1') return;
        if (el.dataset) el.dataset.fotoFallback = '1';
        el.src = PLACEHOLDER;
    }, true);
})();
