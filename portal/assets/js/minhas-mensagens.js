// ==========================================================================
// MINHAS MENSAGENS
// ==========================================================================

setInterval(() => {
    const agora = new Date();
    document.getElementById('relogio').innerText = agora.toLocaleTimeString('pt-br');
    document.getElementById('relogioMobile').innerText = agora.toLocaleTimeString('pt-br');
    document.getElementById('data-topo').innerText = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
}, 1000);

window.addEventListener('DOMContentLoaded', carregarConversas);

async function carregarConversas() {
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_URL}/chat/minhas-conversas`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const conversas = await resp.json();
        
        document.getElementById('totalConversas').textContent = conversas.length;
        document.getElementById('totalEnviadas').textContent = conversas.reduce((s, c) => s + c.enviadas, 0);
        document.getElementById('totalRecebidas').textContent = conversas.reduce((s, c) => s + c.recebidas, 0);
        
        const container = document.getElementById('conversasLista');
        
        if (!conversas.length) {
            container.innerHTML = '<p class="text-center text-slate-400 text-sm py-8">Nenhuma conversa</p>';
            return;
        }
        
        container.innerHTML = conversas.map(c => `
            <div class="p-4 hover:bg-slate-50 transition-all cursor-pointer" onclick="abrirConversa(${c.idusuario}, '${c.username}')">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-[#375a4b] text-white rounded-xl flex items-center justify-center font-bold">
                            ${c.username.substring(0,2).toUpperCase()}
                        </div>
                        <div>
                            <h4 class="font-bold text-slate-800">${c.username}</h4>
                            <p class="text-xs text-slate-400 mt-0.5">${c.ultima_mensagem || 'Nenhuma mensagem'}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-xs text-slate-400">${c.ultima_data ? new Date(c.ultima_data).toLocaleString('pt-BR') : ''}</span>
                        <div class="flex gap-2 mt-1 text-[10px]">
                            <span class="text-emerald-600">📤 ${c.enviadas}</span>
                            <span class="text-blue-600">📥 ${c.recebidas}</span>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    } catch(e) {
        console.error('Erro:', e);
    }
}

async function abrirConversa(idusuario, nome) {
    const { value: mensagem } = await Swal.fire({
        title: `Conversa com ${nome}`,
        input: 'textarea',
        inputPlaceholder: 'Digite sua mensagem...',
        showCancelButton: true,
        confirmButtonText: 'Enviar',
        confirmButtonColor: '#375a4b',
        position: 'top'
    });
    
    if (mensagem) {
        try {
            const token = localStorage.getItem('authToken');
            await fetch(`${API_URL}/chat/enviar`, {
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ destinatario: idusuario, mensagem })
            });
            Swal.fire({ icon: 'success', title: 'Enviado!', timer: 1500, showConfirmButton: false });
            carregarConversas();
        } catch(e) {
            Swal.fire({ icon: 'error', title: 'Erro ao enviar' });
        }
    }
}