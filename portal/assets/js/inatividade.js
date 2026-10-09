// Bloqueio por inatividade: após 10 minutos sem uso, exige o código de parada para continuar.
(function () {
    'use strict';

    const LIMITE_MS = 10 * 60 * 1000;

    // Tabela de códigos de parada logística (código = grupo.número)
    const CODIGOS = [
        ['1. Equipamentos e movimentação', [
            'Aguardando empilhadeira', 'Aguardando paleteira ou equipamento de movimentação',
            'Equipamento indisponível ou com defeito', 'Aguardando recarga ou troca de bateria do equipamento',
            'Corredor bloqueado ou obstruído', 'Aguardando liberação de doca ou área de movimentação',
            'Palete ou carga inadequada para movimentação']],
        ['2. Separação e localização de produtos', [
            'Produto não encontrado no endereço indicado', 'Divergência entre estoque físico e sistema',
            'Endereço de armazenagem incorreto', 'Produto armazenado em local inadequado',
            'Falta de produto para concluir o pedido', 'Produto bloqueado ou aguardando liberação',
            'Etiqueta ou código de barras ilegível', 'Necessidade de recontagem ou conferência de estoque']],
        ['3. Interrupções e apoio operacional', [
            'Parada para auxiliar em outra função', 'Deslocamento para buscar materiais ou ferramentas',
            'Aguardando orientação ou definição da liderança', 'Aguardando outro colaborador para concluir a atividade',
            'Interrupção para reunião ou comunicação operacional', 'Realização de atividade fora da função programada',
            'Falta de mão de obra disponível no setor']],
        ['4. Conferência, avarias e retrabalho', [
            'Divergência na conferência do pedido', 'Necessidade de refazer a separação',
            'Produto avariado durante a movimentação', 'Embalagem danificada ou inadequada',
            'Pedido separado incorretamente', 'Aguardando correção de pedido ou documentação',
            'Retrabalho por falha operacional', 'Aguardando análise de divergência de estoque']],
        ['5. Expedição e carregamento', [
            'Aguardando veículo para carregamento', 'Aguardando liberação da expedição',
            'Aguardando conferência final', 'Aguardando documentação fiscal',
            'Carga bloqueada por divergência ou avaria', 'Doca indisponível',
            'Reorganização de carga já separada', 'Aguardando motorista ou transportadora',
            'Falta de espaço na área de expedição']],
        ['6. Infraestrutura, sistemas e condições operacionais', [
            'Sistema WMS ou ERP indisponível', 'Falha de coletor, terminal ou impressora',
            'Falta de energia elétrica', 'Limpeza ou organização emergencial da área',
            'Área de trabalho sem condições adequadas', 'Aguardando manutenção da infraestrutura',
            'Parada por segurança operacional', 'Pausa regulamentar ou intervalo programado',
            'Falta de insumos operacionais', 'Outro motivo não classificado — exige justificativa']]
    ];
    const CODIGO_OUTRO = '6.10';
    const CHECK_MS = 15 * 1000;
    const K_ATIVIDADE = 'portal_ultima_atividade';
    const K_BLOQUEIO = 'portal_bloqueio_inatividade';
    const OVERLAY_ID = 'inatividade-overlay';

    // Motorista em rota usa a tela offline; o bloqueio não se aplica a ela.
    const path = location.pathname;
    if (path.includes('login.php') || path.includes('motorista-offline')) return;

    const getToken = () => localStorage.getItem('authToken') || localStorage.getItem('token');
    if (!getToken()) return;

    const agora = () => Date.now();
    const ultimaAtividade = () => parseInt(localStorage.getItem(K_ATIVIDADE) || '0', 10) || agora();
    const bloqueado = () => !!localStorage.getItem(K_BLOQUEIO);

    let ultimoRegistro = 0;
    function registrarAtividade() {
        if (bloqueado()) return;
        const t = agora();
        if (t - ultimoRegistro < 1000) return;
        ultimoRegistro = t;
        localStorage.setItem(K_ATIVIDADE, String(t));
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'wheel', 'scroll', 'click'].forEach(ev =>
        window.addEventListener(ev, registrarAtividade, { passive: true, capture: true })
    );

    function apiBase() {
        return (window.API_URL || 'https://api.nutricionalbr.com/v1').replace(/\/$/, '');
    }

    async function enviarMotivo(motivo, minutos, codigo) {
        const resp = await fetch(apiBase() + '/auth/inatividade', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Authorization: 'Bearer ' + getToken() },
            body: JSON.stringify({ motivo, codigo_parada: codigo, minutos_inativo: minutos, pagina: location.pathname })
        });
        if (resp.status === 401) {
            localStorage.removeItem('authToken');
            localStorage.removeItem(K_BLOQUEIO);
            location.href = (location.pathname.includes('/API/') ? '/API' : '') + '/portal/login.php?expired=1';
            return false;
        }
        return true;
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function opcoesCodigos() {
        return CODIGOS.map(([grupo, itens]) =>
            `<optgroup label="${esc(grupo)}">` +
            itens.map((d, i) => {
                const cod = grupo.charAt(0) + '.' + (i + 1);
                return `<option value="${cod}">${cod} - ${esc(d)}</option>`;
            }).join('') + '</optgroup>'
        ).join('');
    }

    function descricaoCodigo(cod) {
        const [g, n] = cod.split('.').map(Number);
        const grupo = CODIGOS[g - 1];
        return grupo && grupo[1][n - 1] ? grupo[1][n - 1] : '';
    }

    function mostrarOverlay() {
        if (document.getElementById(OVERLAY_ID)) return;
        const inicio = parseInt(localStorage.getItem(K_BLOQUEIO), 10) || agora();
        const minutos = Math.max(Math.round(LIMITE_MS / 60000), Math.round((inicio - ultimaAtividade()) / 60000));

        const ov = document.createElement('div');
        ov.id = OVERLAY_ID;
        ov.style.cssText = 'position:fixed;inset:0;z-index:2147483000;background:rgba(15,23,42,.92);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:16px;font-family:inherit;';
        ov.innerHTML = `
            <div style="background:#fff;border-radius:18px;max-width:440px;width:100%;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.4);">
                <div style="font-size:2rem;text-align:center;">⏸️</div>
                <h2 style="text-align:center;font-weight:800;font-size:1.2rem;color:#1e293b;margin:6px 0;">Sessão pausada por inatividade</h2>
                <p style="text-align:center;font-size:.85rem;color:#64748b;margin:0 0 14px;">
                    Você ficou ${esc(minutos)} min sem usar o sistema. Selecione o código da parada para continuar.
                </p>
                <select id="inatividade-codigo"
                    style="width:100%;border:2px solid #e2e8f0;border-radius:12px;padding:10px;font-size:.9rem;outline:none;color:#1e293b;margin-bottom:8px;background:#fff;">
                    <option value="">Selecione o código da parada...</option>${opcoesCodigos()}
                </select>
                <textarea id="inatividade-motivo" rows="2" maxlength="250"
                    placeholder="Justificativa (obrigatória para o código 6.10)"
                    style="width:100%;border:2px solid #e2e8f0;border-radius:12px;padding:10px;font-size:.95rem;resize:none;outline:none;color:#1e293b;"></textarea>
                <div id="inatividade-erro" style="color:#dc2626;font-size:.8rem;min-height:18px;margin-top:4px;"></div>
                <button id="inatividade-ok" type="button"
                    style="width:100%;margin-top:8px;background:#274036;color:#fff;border:0;border-radius:12px;padding:12px;font-weight:800;font-size:.95rem;cursor:pointer;">
                    Retomar trabalho
                </button>
            </div>`;

        // Impede que atalhos e leitores de código da página recebam as teclas digitadas aqui
        ['keydown', 'keyup', 'keypress', 'click', 'input'].forEach(ev =>
            ov.addEventListener(ev, e => e.stopPropagation())
        );
        document.body.appendChild(ov);

        const campo = ov.querySelector('#inatividade-motivo');
        const erro = ov.querySelector('#inatividade-erro');
        const btn = ov.querySelector('#inatividade-ok');
        setTimeout(() => campo.focus(), 50);

        async function confirmar() {
            const codigo = ov.querySelector('#inatividade-codigo').value;
            const just = campo.value.trim().replace(/\s+/g, ' ');
            if (!codigo) {
                erro.textContent = 'Selecione o código da parada.';
                return;
            }
            if (codigo === CODIGO_OUTRO && just.length < 5) {
                erro.textContent = 'O código 6.10 exige justificativa (mínimo 5 caracteres).';
                campo.focus();
                return;
            }
            const motivo = (codigo + ' - ' + descricaoCodigo(codigo) + (just ? ': ' + just : '')).slice(0, 300);
            btn.disabled = true;
            btn.textContent = 'Registrando...';
            try {
                const ok = await enviarMotivo(motivo, minutos, codigo);
                if (!ok) return;
            } catch (e) {
                // Sem rede: libera para não travar a operação e guarda o motivo localmente
                const fila = JSON.parse(localStorage.getItem('portal_inatividade_pendente') || '[]');
                fila.push({ motivo, codigo_parada: codigo, minutos, em: new Date().toISOString() });
                localStorage.setItem('portal_inatividade_pendente', JSON.stringify(fila.slice(-20)));
            }
            localStorage.removeItem(K_BLOQUEIO);
            localStorage.setItem(K_ATIVIDADE, String(agora()));
            removerOverlay();
        }

        btn.addEventListener('click', confirmar);
        campo.addEventListener('keydown', e => {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); confirmar(); }
        });
    }

    function removerOverlay() {
        const ov = document.getElementById(OVERLAY_ID);
        if (ov) ov.remove();
    }

    function bloquear() {
        if (!bloqueado()) localStorage.setItem(K_BLOQUEIO, String(agora()));
        mostrarOverlay();
    }

    function verificar() {
        if (!getToken()) return;
        if (bloqueado()) {
            mostrarOverlay();
            return;
        }
        if (agora() - ultimaAtividade() >= LIMITE_MS) bloquear();
    }

    // Sincroniza várias abas: liberar em uma libera todas; bloquear em uma bloqueia todas
    window.addEventListener('storage', e => {
        if (e.key !== K_BLOQUEIO) return;
        if (e.newValue) mostrarOverlay(); else removerOverlay();
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) verificar();
    });

    if (!localStorage.getItem(K_ATIVIDADE)) localStorage.setItem(K_ATIVIDADE, String(agora()));
    verificar();
    setInterval(verificar, CHECK_MS);

    // Aviso com contagem regressiva nos últimos segundos; qualquer atividade o remove.
    const AVISO_ID = 'inatividade-aviso';
    const AVISO_SEG = 60;

    function atualizarAviso() {
        let el = document.getElementById(AVISO_ID);
        if (!getToken() || bloqueado()) { if (el) el.remove(); return; }
        const restante = Math.ceil((LIMITE_MS - (agora() - ultimaAtividade())) / 1000);
        if (restante > AVISO_SEG || restante <= 0) { if (el) el.remove(); return; }
        if (!el) {
            el = document.createElement('div');
            el.id = AVISO_ID;
            el.style.cssText = 'position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:2147482000;background:#92400e;color:#fff;padding:10px 18px;border-radius:999px;font-weight:700;font-size:.85rem;box-shadow:0 8px 24px rgba(0,0,0,.35);pointer-events:none;';
            document.body.appendChild(el);
        }
        el.textContent = '⏳ Sem atividade: a sessão será pausada em ' + restante + 's';
    }
    setInterval(atualizarAviso, 1000);
    // O logout encerra o controle para a próxima sessão começar limpa
    window.addEventListener('beforeunload', () => {
        if (!getToken()) {
            localStorage.removeItem(K_BLOQUEIO);
            localStorage.removeItem(K_ATIVIDADE);
        }
    });
})();
