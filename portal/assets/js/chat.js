// ==========================================================================
// CHAT INTERNO - WHATSAPP STYLE (VERSÃO FINAL)
// ==========================================================================
let chatAberto = false;
let conversasAbertas = {};
let conversaAtivaId = null;
let contatosCache = [];
let pollingInterval = null;

// ======================================================================
// TOGGLE
// ======================================================================
function toggleChat() {
    chatAberto = !chatAberto;
    const win = document.getElementById('chatWindow');
    const btn = document.getElementById('chatToggle');
    if (win) win.classList.toggle('hidden', !chatAberto);
    if (btn) btn.classList.toggle('active', chatAberto);
    if (chatAberto) { carregarContatos(); iniciarPolling(); }
    else { pararPolling(); }
}

// ======================================================================
// CONTATOS
// ======================================================================
async function carregarContatos() {
    try {
        const resp = await fetch(`${API_URL}/chat/contatos`, {
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') }
        });
        contatosCache = await resp.json();
        renderizarContatos(contatosCache);
    } catch(e) {}
}

function renderizarContatos(lista) {
    const el = document.getElementById('contatosLista');
    if (!el) return;
    if (!lista.length) {
        el.innerHTML = '<p class="text-center text-slate-400 text-xs py-4">Nenhum usuário online</p>';
        return;
    }
    el.innerHTML = lista.map(c => {
        const iniciais = c.username.substring(0,2).toUpperCase();
        const avatarHTML = c.foto_url 
            ? `<img src="${c.foto_url}" style="width:44px; height:44px; border-radius:50%; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
            : '';
        const fallbackHTML = `<div class="contato-avatar" style="${c.foto_url ? 'display:none;' : 'display:flex;'}">${iniciais}</div>`;
        
        return `
        <div class="contato-item" onclick="abrirConversa(${c.idusuario}, '${c.username.replace(/'/g, "\\'")}')">
            <div style="position:relative; flex-shrink:0;">
                ${avatarHTML}
                ${fallbackHTML}
                ${c.nao_lidas > 0 ? `<span style="position:absolute; bottom:0; right:0; width:14px; height:14px; background:#25D366; border:2px solid white; border-radius:50%;"></span>` : ''}
            </div>
            <div class="contato-info">
                <div class="contato-nome">${c.username}</div>
                <div class="contato-status">${c.nao_lidas > 0 ? c.nao_lidas + ' novas mensagens' : 'Clique para conversar'}</div>
            </div>
            ${c.nao_lidas > 0 ? `<span class="contato-naolidas">${c.nao_lidas}</span>` : ''}
        </div>`;
    }).join('');
}

function filtrarContatos() {
    const busca = (document.getElementById('searchContato')?.value || '').toLowerCase();
    renderizarContatos(contatosCache.filter(c => c.username.toLowerCase().includes(busca)));
}

// ======================================================================
// CONVERSAS
// ======================================================================
async function abrirConversa(idusuario, nome) {
    if (conversasAbertas[idusuario]) {
        ativarConversa(idusuario);
        return;
    }
    conversasAbertas[idusuario] = { nome, mensagens: [], naoLidas: 0 };
    renderizarTabs();
    ativarConversa(idusuario);
    await marcarLida(idusuario);
    await carregarMensagens(idusuario);
}

function ativarConversa(idusuario) {
    conversaAtivaId = idusuario;
    renderizarTabs();
    renderizarConversaAtiva();
}

function fecharConversa(idusuario) {
    delete conversasAbertas[idusuario];
    if (conversaAtivaId === idusuario) {
        const ids = Object.keys(conversasAbertas);
        if (ids.length > 0) ativarConversa(parseInt(ids[ids.length - 1]));
        else mostrarContatos();
    }
    renderizarTabs();
}

function minimizarTodasConversas() { 
    conversaAtivaId = null;
    renderizarTabs();
    mostrarContatos(); 
}

// ======================================================================
// MOSTRAR / ESCONDER
// ======================================================================
function mostrarContatos() {
    conversaAtivaId = null;
    renderizarTabs();
    
    const conv = document.getElementById('chatConversaAtiva');
    const lista = document.getElementById('listaContatos');
    
    if (conv) {
        // Limpa tudo
        conv.innerHTML = '';
        conv.style.display = 'flex';
        
        // Recria a lista de contatos
        const novaLista = document.createElement('div');
        novaLista.id = 'listaContatos';
        novaLista.style.cssText = 'flex:1; overflow-y:auto; display:block;';
        novaLista.innerHTML = `
            <div class="chat-search">
                <i class="fa-solid fa-search"></i>
                <input type="text" id="searchContato" placeholder="Buscar usuário..." oninput="filtrarContatos()">
            </div>
            <div id="contatosLista" class="contatos-lista">
                <p class="text-center text-slate-400 text-xs py-4">Carregando...</p>
            </div>
        `;
        conv.appendChild(novaLista);
    }
    
    carregarContatos();
}

// ======================================================================
// TABS
// ======================================================================
function renderizarTabs() {
    const el = document.getElementById('chatTabs');
    if (!el) return;
    const ids = Object.keys(conversasAbertas);
    el.innerHTML = ids.map(id => {
        const conv = conversasAbertas[id];
        return `
            <div class="chat-tab ${conversaAtivaId == id ? 'active' : ''}" onclick="ativarConversa(${id})">
                💬 ${conv.nome}
                ${conv.naoLidas > 0 ? `<span class="tab-badge">${conv.naoLidas}</span>` : ''}
                <span class="tab-close" onclick="event.stopPropagation(); fecharConversa(${id})">✕</span>
            </div>
        `;
    }).join('');
}

// ======================================================================
// RENDERIZAR CONVERSA ATIVA
// ======================================================================
function renderizarConversaAtiva() {
    if (!conversaAtivaId) return;
    const conv = conversasAbertas[conversaAtivaId];
    const container = document.getElementById('chatConversaAtiva');
    if (!container) return;
    
    // Buscar foto do contato
    const contato = contatosCache.find(c => c.idusuario == conversaAtivaId);
    const fotoUrl = contato?.foto_url;
    const iniciais = conv.nome.substring(0,2).toUpperCase();
    
    container.innerHTML = `
        <div style="background:#075e54; color:white; padding:10px 14px; display:flex; align-items:center; gap:10px; flex-shrink:0;">
            <button onclick="mostrarContatos()" style="background:none; border:none; color:white; cursor:pointer; font-size:20px; padding:0 5px;">
                ←
            </button>
            <div style="position:relative; flex-shrink:0;">
                ${fotoUrl 
                    ? `<img src="${fotoUrl}" style="width:36px; height:36px; border-radius:50%; object-fit:cover;" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">`
                    : ''}
                <div style="width:36px; height:36px; border-radius:50%; background:rgba(255,255,255,0.2); display:${fotoUrl ? 'none' : 'flex'}; align-items:center; justify-content:center; font-weight:700; font-size:14px;">
                    ${iniciais}
                </div>
            </div>
            <span style="font-weight:600; font-size:14px; flex:1;">${conv.nome}</span>
        </div>
        
        <div class="mensagens-container" id="msgContainer_${conversaAtivaId}">
            ${conv.mensagens.length === 0 
                ? '<p class="text-center text-slate-400 text-xs py-8">Nenhuma mensagem ainda. Diga olá! 👋</p>'
                : conv.mensagens.map(m => renderizarMensagem(m)).join('')}
        </div>
        
        <div class="chat-input-area">
            <input type="text" id="chatInput_${conversaAtivaId}" placeholder="Digite sua mensagem..."
                   onkeypress="if(event.key==='Enter') enviarMensagem(${conversaAtivaId})">
            <button onclick="enviarMensagem(${conversaAtivaId})" class="chat-send-btn">
                <i class="fa-solid fa-paper-plane"></i>
            </button>
        </div>
    `;
}

function renderizarMensagem(m) {
    const userData = JSON.parse(localStorage.getItem('userData') || '{}');
    const meuId = userData.idusuario;
    const ehMinha = m.idusuario_remetente == meuId;
    const hora = new Date(m.datahora).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
    return `
        <div class="msg-bubble ${ehMinha ? 'msg-enviada' : 'msg-recebida'}">
            ${m.mensagem}
            <div class="msg-hora">${hora}</div>
        </div>
    `;
}

// ======================================================================
// MENSAGENS
// ======================================================================
async function carregarMensagens(idusuario) {
    try {
        const resp = await fetch(`${API_URL}/chat/mensagens/${idusuario}`, {
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') }
        });
        const mensagens = await resp.json();
        if (conversasAbertas[idusuario]) {
            conversasAbertas[idusuario].mensagens = mensagens;
            if (conversaAtivaId == idusuario) {
                const el = document.getElementById('msgContainer_' + idusuario);
                if (el) {
                    el.innerHTML = mensagens.length === 0 
                        ? '<p class="text-center text-slate-400 text-xs py-8">Nenhuma mensagem ainda. Diga olá! 👋</p>'
                        : mensagens.map(m => renderizarMensagem(m)).join('');
                    el.scrollTop = el.scrollHeight;
                }
            }
        }
    } catch(e) {}
}

async function enviarMensagem(idusuario) {
    const input = document.getElementById('chatInput_' + idusuario);
    if (!input) return;
    const msg = input.value.trim();
    if (!msg) return;
    
    try {
        await fetch(`${API_URL}/chat/enviar`, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + localStorage.getItem('authToken'),
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ destinatario: idusuario, mensagem: msg })
        });
        input.value = '';
        await carregarMensagens(idusuario);
    } catch(e) {}
}

async function marcarLida(remetenteId) {
    try {
        await fetch(`${API_URL}/chat/marcar-lida/${remetenteId}`, {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') }
        });
    } catch(e) {}
}

// ======================================================================
// POLLING
// ======================================================================
function iniciarPolling() {
    pararPolling();
    pollingInterval = setInterval(async () => {
        try {
            const resp = await fetch(`${API_URL}/chat/nao-lidas`, {
                headers: { 'Authorization': 'Bearer ' + localStorage.getItem('authToken') }
            });
            const data = await resp.json();
            const badge = document.getElementById('chatBadge');
            if (badge) {
                if (data.total > 0) {
                    badge.textContent = data.total > 99 ? '99+' : data.total;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }
            for (let id in conversasAbertas) {
                await carregarMensagens(parseInt(id));
            }
            if (!conversaAtivaId) await carregarContatos();
        } catch(e) {}
    }, 3000);
}

function pararPolling() {
    if (pollingInterval) clearInterval(pollingInterval);
}