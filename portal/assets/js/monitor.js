// ==========================================================================
// MÓDULO DE MONITORAMENTO LOGÍSTICO (IIFE - ESCOPO ISOLADO)
// ==========================================================================
(function() {
    'use strict';

    // ======================================================================
    // FUNÇÕES DE FALLBACK (caso não tenham sido carregadas globalmente)
    // ======================================================================
    
    // Fallback para showToast
    if (typeof window.showToast === 'undefined') {
        window.showToast = function(message, icon = 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: icon,
                    title: message,
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            } else {
                console.log('Toast:', message, icon);
            }
        };
    }
    const showToast = window.showToast;

    // Fallback para fetchWithAuth
    if (typeof window.fetchWithAuth === 'undefined') {
        window.fetchWithAuth = async function(url, options = {}) {
            const token = localStorage.getItem('authToken');
            if (!token) {
                window.location.href = '/portal/login.php';
                throw new Error('Não autenticado');
            }
            const headers = {
                'Authorization': `Bearer ${token}`,
                'Content-Type': 'application/json',
                ...options.headers
            };
            const response = await fetch(url, { ...options, headers });
            if (response.status === 401) {
                localStorage.clear();
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada');
            }
            return response;
        };
    }
    const fetchWithAuth = window.fetchWithAuth;

    // ======================================================================
    // VARIÁVEIS LOCAIS (NÃO POLUEM O ESCOPO GLOBAL)
    // ======================================================================
    let lastDataHash = "";
    let isFirstLoad = true;
    let carregadosConhecidos = new Set();

    // Áudio da buzina
    const somBuzina = new Audio('https://store.soundeffectgenerator.org/instants/fire-truck-siren-sound-effects/2ef9b362-truck-horn-pls.mp3');
    somBuzina.preload = 'auto';

    // Desbloqueio automático do áudio
    document.body.addEventListener('click', () => {
        somBuzina.play().then(() => {
            somBuzina.pause();
            somBuzina.currentTime = 0;
        }).catch(e => console.warn("Aguardando interação..."));
    }, { once: true });

    const playHorn = () => {
        somBuzina.currentTime = 0;
        somBuzina.volume = 0.7;
        somBuzina.play().catch(e => console.warn("Áudio travado."));
    };

    // ======================================================================
    // ATRAÇÃO COM VÍDEO (PET TRUCK)
    // ======================================================================
    function dispararAtracaoPET(dadosEmbarque) {
        const stage = document.getElementById('pet-truck-stage');
        const video = document.getElementById('video-caminhao');
        
        if (!stage || !video) return;

        document.getElementById('anim-id').innerText = `#${dadosEmbarque.idembarque}`;
        document.getElementById('anim-rota').innerText = dadosEmbarque.rota || "ROTA INTERNA";
        document.getElementById('anim-motorista').innerText = dadosEmbarque.motorista || "MOTORISTA";
        document.getElementById('anim-placa').innerText = dadosEmbarque.placa || "PLACA";

        stage.style.display = 'flex';
        video.currentTime = 0;
        video.play();
        playHorn();

        video.onended = () => { stage.style.display = 'none'; };
        setTimeout(() => { stage.style.display = 'none'; }, 8000);
    }

    // ======================================================================
    // ATUALIZAÇÃO PRINCIPAL
    // ======================================================================
    async function atualizarMonitor() {
        try {
            const resp = await fetchWithAuth('/v1/monitor/embarques');
            const dados = await resp.json();

            if (!dados || !Array.isArray(dados)) return;

            // Filtros para rodapé
            const todasSep = dados.filter(d => d.status_atual === 'CONCLUIDO' || d.status_atual === 'CARREGADO');
            const todasCar = dados.filter(d => d.status_atual === 'CARREGADO');

            // Gatilho do vídeo
            let novoCaminhaoParaAnimar = null;
            todasCar.forEach(d => {
                if (!carregadosConhecidos.has(d.idembarque)) {
                    carregadosConhecidos.add(d.idembarque);
                    if (!isFirstLoad) novoCaminhaoParaAnimar = d;
                }
            });

            if (novoCaminhaoParaAnimar) dispararAtracaoPET(novoCaminhaoParaAnimar);
            isFirstLoad = false;

            // Atualiza rodapé
            const formatarRota = (rota) => rota ? (rota.length > 20 ? rota.substring(0, 20) + '...' : rota) : 'Interno';
            
            const elSep = document.getElementById('lista-sep');
            if (elSep) {
                elSep.innerHTML = todasSep.slice(0, 8).map(d => 
                    `<div class="mini-badge">#${d.idembarque} - ${formatarRota(d.rota)}</div>`
                ).join('') || '<span class="text-muted small">Nenhuma separação recente</span>';
            }

            const elCar = document.getElementById('lista-car');
            if (elCar) {
                elCar.innerHTML = todasCar.slice(0, 8).map(d => 
                    `<div class="mini-badge" style="border-color: var(--success); color: var(--success);">#${d.idembarque} - ${formatarRota(d.rota)}</div>`
                ).join('') || '<span class="text-muted small">Nenhum carregamento recente</span>';
            }

            // Filtro do grid principal
            const dadosParaExibir = dados.filter(d => d.status_atual !== 'CARREGADO');

            if (dadosParaExibir.length === 0) {
                document.getElementById('monitor-grid').innerHTML = `
                    <div class="col-12 text-center mt-5">
                        <h2 class="text-muted opacity-50">SEM EMBARQUES ATIVOS PARA EXIBIÇÃO</h2>
                        <p class="text-muted">Todos os embarques recentes já foram finalizados.</p>
                    </div>`;
            } else {
                renderizarMonitor(dadosParaExibir);
            }

        } catch (e) {
            console.error("Erro crítico na atualização do Dashboard:", e);
            showToast('Erro ao carregar monitor', 'error');
        }
    }

    // ======================================================================
    // RENDERIZAÇÃO DOS CARDS
    // ======================================================================
function renderizarMonitor(dados) {
    const grid = document.getElementById('monitor-grid');
    if (!grid) return;

    const formatarData = (str) => {
        if (!str || str === '1900-01-01' || str.startsWith('1900')) return "Aguardando...";
        const limpo = str.split('.')[0];
        const partes = limpo.split(' ');
        const data = partes[0].split('-').reverse().join('/');
        const hora = partes[1] ? partes[1].substring(0, 5) : '00:00';
        return `${data} às ${hora}`;
    };

    const formatFoto = (p) => {
        if (!p || typeof p !== 'string' || p.indexOf('Fotos para o Site\\') === -1) {
            return 'https://placehold.co/80x80?text=SEM+FOTO';
        }
        try {
            return 'https://acesso.nutricionalbr.com:2053/fotos/' + p.split('Fotos para o Site\\')[1].replace(/ /g, '%20');
        } catch (e) {
            return 'https://placehold.co/80x80?text=ERRO+FOTO';
        }
    };

    let html = '';

    dados.forEach(row => {
        const t = parseInt(row.total_itens_unicos) || 0;
        const s = parseInt(row.itens_concluidos_sep) || 0;
        const c = parseInt(row.itens_concluidos_car) || 0;
        
        const pS = t > 0 ? Math.round((s / t) * 100) : 0;
        const pC = t > 0 ? Math.round((c / t) * 100) : 0;
        
        const sep = (row.last_sep_info && row.last_sep_info.includes('|')) ? row.last_sep_info.split('|') : null;
        const car = (row.last_car_info && row.last_car_info.includes('|')) ? row.last_car_info.split('|') : null;

        let statusExtraClass = "";
        if (row.status_atual === 'CARREGADO') statusExtraClass = "done-car";
        else if (row.status_atual === 'CONCLUIDO') statusExtraClass = "done-sep active-card";
        else if (row.status_atual !== 'PENDENTE') statusExtraClass = "active-card";

        html += `
        <div class="card-emb ${statusExtraClass}" style="background:#ffffff !important; color:#1e293b !important;">
            <div class="st-badge" style="background:#375a4b; color:#f7be2f;">#${row.idembarque} | ${row.status_atual}</div>
            <div class="route-title" style="color:#375a4b !important;">${row.rota || 'INTERNO'}</div>
            <div class="sub-details" style="color:#64748b !important;">
                <i class="fa-solid fa-truck mr-1" style="color:#f7be2f;"></i> <b>${row.placa || 'S/P'}</b> | 
                <b>${Math.floor(row.peso || 0)}kg</b> | 
                <i class="fa-solid fa-user-tie ml-2 mr-1"></i> ${row.motorista || 'NÃO INFORMADO'}
            </div>

            <div class="prog-label" style="color:#375a4b !important;">
                <span><i class="fa-solid fa-boxes-packing mr-1"></i> SEPARAÇÃO</span> 
                <span>${pS >= 100 ? '✅ CONCLUÍDO' : s + '/' + t + ' ITENS'}</span>
            </div>
            <div class="progress"><div class="progress-bar bar-sep" style="width:${pS}%; background:#f7be2f; color:#000;">${pS}%</div></div>
            
            <div class="item-box" style="background:#f8fafc !important; border:1px solid #e2e8f0 !important;">
                <img src="${formatFoto(sep ? sep[1] : null)}" style="width:50px; height:50px; object-fit:contain; background:white; border-radius:8px; margin-right:12px;">
                <div class="item-info" style="color:#1e293b !important;">
                    <div style="display:flex; align-items:center; margin-bottom:4px;">
                        <span class="badge-qtd" style="background:#375a4b; color:#f7be2f; padding:2px 6px; border-radius:5px; font-weight:800; font-size:0.7rem;">${sep ? Math.floor(sep[3])+'/'+Math.floor(sep[4]) : '0/0'}</span>
                        <small style="margin-left:8px; color:#64748b; font-weight:700;">ÚLTIMO BIP</small>
                    </div>
                    <b style="color:#1e293b !important; display:block; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${sep ? sep[0] : 'Aguardando...'}</b>
                    <div style="color:#64748b; font-size:0.85rem; margin-top:4px;">
                        <i class="fa-regular fa-clock mr-1"></i> ${sep ? sep[2] : '--:--'} | 
                        <i class="fa-solid fa-user-check ml-2 mr-1"></i> ${sep ? sep[5] : '---'}
                    </div>
                </div>
            </div>

            <div class="prog-label" style="color:#375a4b !important; margin-top:12px;">
                <span><i class="fa-solid fa-truck-ramp-box mr-1"></i> CARREGAMENTO</span> 
                <span>${pC >= 100 ? '✅ CONCLUÍDO' : c + '/' + t + ' ITENS'}</span>
            </div>
            <div class="progress"><div class="progress-bar bar-car" style="width:${pC}%; background:#10b981; color:#fff;">${pC}%</div></div>
            
            <div class="item-box" style="background:#f8fafc !important; border:1px solid #e2e8f0 !important;">
                <img src="${formatFoto(car ? car[1] : null)}" style="width:50px; height:50px; object-fit:contain; background:white; border-radius:8px; margin-right:12px;">
                <div class="item-info" style="color:#1e293b !important;">
                    <div style="display:flex; align-items:center; margin-bottom:4px;">
                        <span style="background:#10b981; color:white; padding:2px 6px; border-radius:5px; font-weight:800; font-size:0.7rem;">${car ? Math.floor(car[3])+'/'+Math.floor(car[4]) : '0/0'}</span>
                        <small style="margin-left:8px; color:#64748b; font-weight:700;">ÚLTIMO BIP</small>
                    </div>
                    <b style="color:#1e293b !important; display:block; max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">${car ? car[0] : 'Aguardando...'}</b>
                    <div style="color:#64748b; font-size:0.85rem; margin-top:4px;">
                        <i class="fa-regular fa-clock mr-1"></i> ${car ? car[2] : '--:--'} | 
                        <i class="fa-solid fa-user-check ml-2 mr-1"></i> ${car ? car[5] : '---'}
                    </div>
                </div>
            </div>

            <div style="margin-top:12px; padding-top:8px; display:flex; justify-content:space-between; align-items:center; border-top:1px solid #e2e8f0;">
                <span style="font-weight:700; color:#375a4b; font-size:0.85rem;"><i class="fa-solid fa-id-badge mr-1"></i> OP: ${row.operador}</span>
                <span style="color:#64748b; font-size:0.85rem; font-weight:700;">
                    <i class="fa-regular fa-calendar-check mr-1"></i> ${formatarData(row.ultima_atividade)}
                </span>
            </div>
        </div>`;
    });

    grid.innerHTML = html;
}

    // ======================================================================
    // RELÓGIO
    // ======================================================================
    setInterval(() => {
        const agora = new Date();
        const relogio = document.getElementById('relogio');
        const dataTopo = document.getElementById('data-topo');
        if (relogio) relogio.innerText = agora.toLocaleTimeString('pt-br');
        if (dataTopo) dataTopo.innerText = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
    }, 1000);

    // ======================================================================
    // INICIALIZAÇÃO
    // ======================================================================
    atualizarMonitor();
    setInterval(atualizarMonitor, 5000);

})();