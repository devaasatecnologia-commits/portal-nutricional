(() => {
    const state = { charts: {}, map: null, heat: null };
    const endpoints = {
        kpis: '/v1/frota/dashboard/kpis',
        problemas: '/v1/frota/dashboard/kpis-problemas',
        graficos: '/v1/frota/dashboard/graficos',
        mapa: '/v1/frota/dashboard/mapa',
        acertos: '/v1/frota/acerto/embarques?pagina=1&limite=1000'
    };

    function token() { return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || ''; }
    async function get(url) {
        const headers = { Accept: 'application/json' };
        if (token()) headers.Authorization = `Bearer ${token()}`;
        const response = await fetch(url, { headers, credentials: 'include' });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        return response.json();
    }
    const number = value => new Intl.NumberFormat('pt-BR').format(Number(value || 0));
    const percent = value => `${Number(value || 0).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;

    function fillKpis(kpis, problemas, acertos) {
        document.getElementById('kpi-entregas').textContent = number(kpis.entregas_hoje);
        document.getElementById('kpi-entregas-sub').textContent = `${number(kpis.entregas_concluidas_hoje)} concluídas`;
        document.getElementById('kpi-motoristas').textContent = number(kpis.motoristas_em_rota);
        document.getElementById('kpi-motoristas-sub').textContent = `${number(kpis.motoristas_ativos)} ativos`;
        document.getElementById('kpi-problemas').textContent = number(problemas.pendentes);
        document.getElementById('kpi-problemas-sub').textContent = `${number(problemas.em_analise)} em análise`;
        const total = acertos.length;
        const finalizados = acertos.filter(item => ['finalizado', 'concluido', 'concluído'].includes(String(item.acerto_status || '').toLowerCase())).length;
        document.getElementById('kpi-acerto').textContent = total ? percent((finalizados / total) * 100) : '0%';
    }

    function chart(id, config) {
        if (state.charts[id]) state.charts[id].destroy();
        state.charts[id] = new Chart(document.getElementById(id), config);
    }
    function renderCharts(data) {
        const styles = getComputedStyle(document.documentElement);
        const ink = styles.getPropertyValue('--fleet-ink').trim() || '#18332d';
        chart('grafico-entregas', { type: 'line', data: { labels: data.dias || [], datasets: [
            { label: 'Concluídas', data: data.concluidas || [], borderColor: '#2e8b68', backgroundColor: 'rgba(46,139,104,.12)', fill: true, tension: .35 },
            { label: 'Pendentes', data: data.pendentes || [], borderColor: '#d27b32', backgroundColor: 'transparent', tension: .35 }
        ] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { color: ink, usePointStyle: true } } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
        const status = data.status_distribution || {};
        chart('grafico-status', { type: 'doughnut', data: { labels: ['Concluídas', 'Pendentes', 'Em andamento', 'Falhas', 'Canceladas'], datasets: [{ data: [status.concluidas || 0, status.pendentes || 0, status.em_andamento || 0, status.falha || 0, status.canceladas || 0], backgroundColor: ['#2e8b68', '#d27b32', '#3979a8', '#b84b4b', '#9aa8a2'], borderWidth: 0 }] }, options: { responsive: true, maintainAspectRatio: false, cutout: '66%', plugins: { legend: { position: 'bottom', labels: { color: ink, usePointStyle: true, padding: 12 } } } } });
    }
    function renderRanking(items) {
        const target = document.getElementById('ranking-motoristas');
        if (!items || !items.length) { target.innerHTML = '<div class="empty-state">Nenhum dado de performance disponível.</div>'; return; }
        target.innerHTML = items.slice(0, 5).map((item, index) => `<div class="ranking-row"><span class="rank-number">${String(index + 1).padStart(2, '0')}</span><div><div class="ranking-name">${item.nome || 'Motorista'}</div><div class="ranking-meta">${number(item.total_faturado)} em entregas</div></div><strong class="ranking-value">${number(item.total_entregas)}</strong></div>`).join('');
    }
    function renderMap(items) {
        if (!state.map) state.map = L.map('mapa-frota', { zoomControl: true }).setView([-15.78, -47.93], 4);
        if (!state.map._baseLayer) { state.map._baseLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap' }).addTo(state.map); }
        const points = (items || []).filter(item => Number(item.latitude) && Number(item.longitude)).map(item => [Number(item.latitude), Number(item.longitude), item.status === 'em_rota' ? 1 : .55]);
        if (state.heat) state.map.removeLayer(state.heat);
        state.heat = L.heatLayer(points, { radius: 30, blur: 22, maxZoom: 12, gradient: { .35: '#3979a8', .6: '#e2b957', .85: '#d27b32', 1: '#b84b4b' } }).addTo(state.map);
        if (points.length) state.map.fitBounds(points.map(point => [point[0], point[1]]), { padding: [20, 20], maxZoom: 12 });
    }
    async function load() {
        const button = document.getElementById('btn-atualizar');
        button.disabled = true; button.querySelector('i').classList.add('fa-spin');
        try {
            const [kpis, problemas, graficos, mapa, acertos] = await Promise.all(Object.values(endpoints).map(get));
            fillKpis(kpis.data || {}, problemas.data || {}, acertos.data || []);
            renderCharts(graficos.data || {});
            renderRanking((graficos.data || {}).top_motoristas || []);
            renderMap(mapa.data || []);
            document.getElementById('ultima-atualizacao').textContent = `Atualizado às ${new Date().toLocaleTimeString('pt-BR')}`;
        } catch (error) {
            console.error('Erro ao carregar dashboard executivo:', error);
            document.getElementById('ultima-atualizacao').textContent = 'Falha ao atualizar';
        } finally { button.disabled = false; button.querySelector('i').classList.remove('fa-spin'); }
    }
    document.addEventListener('DOMContentLoaded', () => { document.getElementById('btn-atualizar').addEventListener('click', load); load(); setInterval(load, 60000); });
})();
