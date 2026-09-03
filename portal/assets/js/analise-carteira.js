// ==========================================================================
// ANÁLISE DE CARTEIRA - CARDS RASTREÁVEIS + GRÁFICOS INTERATIVOS
// ==========================================================================

const analiseCarteiraApp = {
    gestorSelecionado: null,
    representanteSelecionado: null,
    nomeRepresentante: '',
    nomeGestor: '',
    dadosRepresentantes: [],
    dadosTitulos: [],
    chartPizza: null,
    chartRanking: null,

    async init() {
        const token = this.getToken();
        if (!token) { window.location.href = '/portal/login.php'; return; }
        await this.carregarGestores();
        this.bindEvents();
    },

    getToken() {
        return localStorage.getItem('authToken') || sessionStorage.getItem('authToken') || '';
    },

    getUserId() {
        try {
            const ud = JSON.parse(localStorage.getItem('userData') || '{}');
            return ud.uid || ud.idusuario || ud.idcliforemp || 0;
        } catch { return 0; }
    },

    fmtMoeda(v) {
        return 'R$ ' + (parseFloat(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    },

    async fetchWithAuth(url, options = {}) {
        const token = this.getToken();
        if (!token) { window.location.href = '/portal/login.php'; throw new Error('Sem token'); }
        const resp = await fetch(url, {
            ...options,
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', ...(options.headers || {}) }
        });
        if (resp.status === 401) {
            localStorage.clear(); sessionStorage.clear();
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada');
        }
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        return resp;
    },

    bindEvents() {
        const selGestor = document.getElementById('select-gestor');
        const selRep = document.getElementById('select-representante');

        selGestor.addEventListener('change', async (e) => {
            this.gestorSelecionado = e.target.value ? parseInt(e.target.value) : null;
            this.representanteSelecionado = null;
            this.nomeRepresentante = '';
            this.nomeGestor = e.target.selectedOptions[0]?.text || '';
            selRep.value = '';
            document.getElementById('secao-titulos').style.display = 'none';
            this.gestorSelecionado ? await this.carregarDadosRepresentantes() : this.limparTudo();
        });

        selRep.addEventListener('change', async (e) => {
            this.representanteSelecionado = e.target.value ? parseInt(e.target.value) : null;
            if (this.representanteSelecionado) {
                this.nomeRepresentante = e.target.selectedOptions[0]?.text || '';
                await this.carregarTitulos();
                document.getElementById('secao-titulos').style.display = 'block';
                document.getElementById('titulos-nome-rep').innerText = `Representante: ${this.nomeRepresentante} | Gestor: ${this.nomeGestor}`;
                setTimeout(() => document.getElementById('secao-titulos').scrollIntoView({ behavior: 'smooth' }), 300);
            } else {
                document.getElementById('secao-titulos').style.display = 'none';
            }
        });
    },

    async carregarGestores() {
        try {
            const uid = this.getUserId();
            const url = uid === 4 ? '/v1/analise-carteira/admin/gestores' : '/v1/analise-carteira/gestores';
            const method = uid === 4 ? 'GET' : 'POST';
            const body = uid === 4 ? undefined : JSON.stringify({ idusuario: uid });
            const resp = await this.fetchWithAuth(url, { method, body });
            const gestores = await resp.json();
            const lista = Array.isArray(gestores) ? gestores : (gestores?.data || []);
            const select = document.getElementById('select-gestor');
            if (!lista.length) { select.innerHTML = '<option value="">Nenhum gestor disponível</option>'; return; }
            select.innerHTML = '<option value="">Selecione um gestor...</option>' + lista.map(g => `<option value="${g.id}">${g.nome}</option>`).join('');
            if (lista.length === 1) {
                select.value = lista[0].id;
                this.gestorSelecionado = parseInt(lista[0].id);
                this.nomeGestor = lista[0].nome;
                await this.carregarDadosRepresentantes();
            }
        } catch (e) { console.error('Erro gestores:', e); }
    },

    async carregarDadosRepresentantes() {
        if (!this.gestorSelecionado) return;
        this.showShimmer();
        try {
            const resp = await this.fetchWithAuth('/v1/analise-carteira/tabela-gestor', {
                method: 'POST',
                body: JSON.stringify({ idusuario: this.getUserId(), id_gestor: this.gestorSelecionado })
            });
            const dados = await resp.json();
            this.dadosRepresentantes = Array.isArray(dados) ? dados : (dados?.data || []);
            this.popularSelectRepresentantes();
            this.atualizarKPIs();
            this.renderizarResumoGestor();
            this.renderizarTabelaRepresentantes();
            this.renderizarGraficoPizza();
            this.renderizarGraficoRanking();
            document.getElementById('subtitulo-header').innerText = `Gestor: ${this.nomeGestor} | ${this.dadosRepresentantes.length} representantes`;
        } catch (e) {
            console.error('Erro dados:', e);
            if (e.message !== 'Sessão expirada') Swal.fire('Erro', e.message, 'error');
        } finally { this.hideShimmer(); }
    },

    popularSelectRepresentantes() {
        const select = document.getElementById('select-representante');
        const ids = new Set();
        const reps = [];
        this.dadosRepresentantes.forEach(r => {
            if (r.id_representante && !ids.has(r.id_representante)) {
                ids.add(r.id_representante);
                reps.push({ id: r.id_representante, nome: r['Nome do Representante'] || 'Sem nome' });
            }
        });
        reps.sort((a, b) => a.nome.localeCompare(b.nome));
        select.innerHTML = '<option value="">Todos os representantes</option>' + reps.map(r => `<option value="${r.id}">${r.nome}</option>`).join('');
        select.value = this.representanteSelecionado || '';
    },

    async carregarTitulos() {
        if (!this.representanteSelecionado) return;
        this.showShimmer();
        try {
            const resp = await this.fetchWithAuth('/v1/analise-carteira/titulos-representante', {
                method: 'POST',
                body: JSON.stringify({ idusuario: this.getUserId(), id_representante: this.representanteSelecionado })
            });
            const dados = await resp.json();
            this.dadosTitulos = Array.isArray(dados) ? dados : (dados?.data || []);
            let totalTitulos = 0;
            this.dadosTitulos.forEach(t => totalTitulos += parseFloat(t['Valor Saldo'] || 0));
            document.getElementById('tabela-titulos-info').innerText = `${this.dadosTitulos.length} título(s) | Total: ${this.fmtMoeda(totalTitulos)}`;
            this.renderizarTabelaTitulos(this.dadosTitulos);
        } catch (e) { console.error('Erro títulos:', e); }
        finally { this.hideShimmer(); }
    },

    // ======================================================================
    // KPIs - CARDS RASTREÁVEIS
    // ======================================================================
    atualizarKPIs() {
        const d = this.dadosRepresentantes;
        if (!d.length) { this.zerarKPIs(); return; }

        let vencidos = 0, carteiraTotal = 0, titulosVencidos = 0, clientesAfetados = 0;
        let d30 = 0, d60 = 0, d90 = 0, aVencer = 0, prox30 = 0;

        d.forEach(r => {
            vencidos += parseFloat(r['Vencidos'] || 0);
            carteiraTotal += parseFloat(r['Total Receber'] || 0);
            titulosVencidos += parseInt(r['Total Titulos'] || 0);
            clientesAfetados += parseInt(r['Total Clientes'] || 0);
            d30 += parseFloat(r['30 Dias'] || 0);
            d60 += parseFloat(r['60 Dias'] || 0);
            d90 += parseFloat(r['M60 Dias'] || 0);
            aVencer += parseFloat(r['À Vencer'] || 0);
            prox30 += parseFloat(r['Próx. 30 Dias'] || 0);
        });

        const iag = carteiraTotal > 0 ? (vencidos / carteiraTotal * 100).toFixed(2) : '0.00';
        const percVencidos = carteiraTotal > 0 ? (vencidos / carteiraTotal * 100).toFixed(1) : '0.0';
        const totalVencido = d30 + d60 + d90;

        // Cards com data-attribute para rastreamento
        this.setText('kpi-vencidos', this.fmtMoeda(vencidos));
        this.setText('kpi-vencidos-perc', percVencidos + '% da carteira');
        this.setText('kpi-iag', iag + '%');
        this.setText('kpi-carteira', this.fmtMoeda(carteiraTotal));
        this.setText('kpi-carteira-info', titulosVencidos + ' títulos | ' + clientesAfetados + ' clientes');
        this.setText('kpi-titulos', titulosVencidos);
        this.setText('kpi-titulos-clientes', clientesAfetados + ' clientes afetados');
        this.setText('kpi-a-vencer', this.fmtMoeda(aVencer));
        this.setText('kpi-a-vencer-sub', prox30 > 0 ? `Próx. 30 dias: ${this.fmtMoeda(prox30)}` : 'Próximos 30 dias');

        // Legendas pizza
        this.setText('pizza-30d', this.fmtMoeda(d30));
        this.setText('pizza-60d', this.fmtMoeda(d60));
        this.setText('pizza-90d', this.fmtMoeda(d90));
        this.setText('pizza-30d-perc', totalVencido > 0 ? (d30/totalVencido*100).toFixed(1) + '%' : '0%');
        this.setText('pizza-60d-perc', totalVencido > 0 ? (d60/totalVencido*100).toFixed(1) + '%' : '0%');
        this.setText('pizza-90d-perc', totalVencido > 0 ? (d90/totalVencido*100).toFixed(1) + '%' : '0%');
    },

    zerarKPIs() {
        ['kpi-vencidos','kpi-vencidos-perc','kpi-iag','kpi-carteira','kpi-carteira-info',
         'kpi-titulos','kpi-titulos-clientes','kpi-a-vencer','kpi-a-vencer-sub',
         'pizza-30d','pizza-60d','pizza-90d','pizza-30d-perc','pizza-60d-perc','pizza-90d-perc']
        .forEach(id => {
            const el = document.getElementById(id);
            if (el) el.innerText = id.includes('R$') || id.includes('pizza') ? 'R$ 0' : (id.includes('%') ? '0%' : '0');
        });
    },

    // ======================================================================
    // RESUMO GESTOR
    // ======================================================================
    renderizarResumoGestor() {
        const d = this.dadosRepresentantes;
        const container = document.getElementById('secao-resumo-gestor');
        if (!d.length) { if (container) container.style.display = 'none'; return; }

        let venc = 0, d30 = 0, d60 = 0, d90 = 0, cart = 0, avencer = 0;
        d.forEach(r => {
            venc += parseFloat(r['Vencidos'] || 0);
            d30 += parseFloat(r['30 Dias'] || 0);
            d60 += parseFloat(r['60 Dias'] || 0);
            d90 += parseFloat(r['M60 Dias'] || 0);
            cart += parseFloat(r['Total Receber'] || 0);
            avencer += parseFloat(r['À Vencer'] || 0);
        });

        const iag = cart > 0 ? (venc / cart * 100) : 0;
        const pc = iag > 15 ? 'badge-critico' : (iag >= 5 ? 'badge-atencao' : 'badge-saudavel');
        const pt = iag > 15 ? 'CRÍTICO' : (iag >= 5 ? 'ATENÇÃO' : 'SAUDÁVEL');

        if (container) container.style.display = 'block';
        document.getElementById('tabela-resumo-gestor-body').innerHTML = `
        <tr class="bg-gradient-to-r from-amber-50 to-white font-bold">
            <td class="px-4 py-3"><i class="fa-solid fa-user-tie mr-2 text-amber-600"></i>${this.nomeGestor}</td>
            <td class="px-4 py-3 text-right text-rose-600">${this.fmtMoeda(venc)}</td>
            <td class="px-4 py-3 text-right">${this.fmtMoeda(d30)}</td>
            <td class="px-4 py-3 text-center text-blue-600">${venc>0?(d30/venc*100).toFixed(1):0}%</td>
            <td class="px-4 py-3 text-right">${this.fmtMoeda(d60)}</td>
            <td class="px-4 py-3 text-center text-amber-600">${venc>0?(d60/venc*100).toFixed(1):0}%</td>
            <td class="px-4 py-3 text-right">${this.fmtMoeda(d90)}</td>
            <td class="px-4 py-3 text-center text-rose-600">${venc>0?(d90/venc*100).toFixed(1):0}%</td>
            <td class="px-4 py-3 text-center"><span class="${pc}">${pt} (${iag.toFixed(1)}%)</span></td>
            <td class="px-4 py-3 text-right text-emerald-600">${this.fmtMoeda(avencer)}</td>
        </tr>`;
    },

    // ======================================================================
    // TABELA REPRESENTANTES
    // ======================================================================
    renderizarTabelaRepresentantes() {
        const d = this.dadosRepresentantes;
        document.getElementById('tabela-rep-info').innerText = `${d.length} representante(s) | Gestor: ${this.nomeGestor}`;
        const tbody = document.getElementById('tabela-rep-body');
        if (!d.length) { tbody.innerHTML = '<tr><td colspan="13" class="text-center py-8 text-slate-400">Nenhum representante</td></tr>'; return; }

        tbody.innerHTML = d.map(r => {
            const iag = parseFloat(r['IAG Rep (%)'] || 0);
            const pc = iag > 15 ? 'badge-critico' : (iag >= 5 ? 'badge-atencao' : 'badge-saudavel');
            const pt = r['Performance_Com_IAG'] || (iag > 15 ? 'CRÍTICO' : (iag >= 5 ? 'ATENÇÃO' : 'SAUDÁVEL'));
            return `<tr class="border-b border-slate-100 hover:bg-blue-50 cursor-pointer transition-all"
                        onclick="selecionarRepresentante(${r.id_representante}, '${(r['Nome do Representante']||'').replace(/'/g,"\\'")}')"
                        title="Clique para ver títulos">
                <td class="px-3 py-3 font-bold text-slate-800">${r['Nome do Representante']}</td>
                <td class="px-3 py-3 text-right font-bold text-rose-600">${this.fmtMoeda(r['Vencidos'])}</td>
                <td class="px-3 py-3 text-right">${this.fmtMoeda(r['30 Dias'])}</td>
                <td class="px-3 py-3 text-center text-blue-600 font-bold">${r['Perc. 30 Dias']||0}%</td>
                <td class="px-3 py-3 text-right">${this.fmtMoeda(r['60 Dias'])}</td>
                <td class="px-3 py-3 text-center text-amber-600 font-bold">${r['Perc. 60 Dias']||0}%</td>
                <td class="px-3 py-3 text-right">${this.fmtMoeda(r['M60 Dias'])}</td>
                <td class="px-3 py-3 text-center text-rose-600 font-bold">${r['Perc. M60 Dias']||0}%</td>
                <td class="px-3 py-3 text-center font-bold text-purple-600">${r['Percentual']||0}%</td>
                <td class="px-3 py-3 text-center font-bold">${r['Total Titulos']||0}</td>
                <td class="px-3 py-3 text-center">${r['Total Clientes']||0}</td>
                <td class="px-3 py-3 text-center"><span class="${pc}">${pt}</span></td>
                <td class="px-3 py-3 text-right text-emerald-600 font-medium">${this.fmtMoeda(r['À Vencer']||0)}</td>
            </tr>`;
        }).join('');
    },

    // ======================================================================
    // TABELA TÍTULOS
    // ======================================================================
    renderizarTabelaTitulos(dados) {
        const tbody = document.getElementById('tabela-titulos-body');
        if (!dados.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Nenhum título em aberto</td></tr>'; return; }
        tbody.innerHTML = dados.map(t => {
            const da = parseInt(t['Dias em Atrasos']||0);
            const de = parseInt(t['Dias do Último Evento']||0);
            const ba = da > 60 ? 'badge-critico' : (da > 30 ? 'badge-atencao' : 'badge-dias');
            return `<tr class="border-b border-slate-100 hover:bg-slate-50 transition-all">
                <td class="px-3 py-2.5 font-medium">${t['Nome Fantasia']||'-'}</td>
                <td class="px-3 py-2.5 font-mono text-xs">${t['Documento']||'-'}</td>
                <td class="px-3 py-2.5 text-center text-xs">${t['Vencimento']||'-'}</td>
                <td class="px-3 py-2.5 text-right font-bold text-rose-600">${this.fmtMoeda(t['Valor Saldo'])}</td>
                <td class="px-3 py-2.5 text-center"><span class="${ba}">${da} d</span></td>
                <td class="px-3 py-2.5 text-center">${de>0?`<span class="badge-evento">${de} d</span>`:'<span class="badge-sem-evento">Sem evento</span>'}</td>
                <td class="px-3 py-2.5 text-xs">${t['Usuário']||'-'}</td>
                <td class="px-3 py-2.5 text-xs">${t['Registro do Evento']?`<span class="font-medium text-emerald-700">${t['Registro do Evento']}</span>`:'<span class="text-rose-500 font-bold">Sem apontamento</span>'}</td>
            </tr>`;
        }).join('');
    },

    // ======================================================================
    // GRÁFICO PIZZA - RASTREÁVEL (clique na fatia filtra representante)
    // ======================================================================
    renderizarGraficoPizza() {
        if (this.chartPizza) { this.chartPizza.destroy(); this.chartPizza = null; }
        const d = this.dadosRepresentantes;
        let d30=0, d60=0, d90=0;
        d.forEach(r => { d30+=parseFloat(r['30 Dias']||0); d60+=parseFloat(r['60 Dias']||0); d90+=parseFloat(r['M60 Dias']||0); });
        const el = document.querySelector('#chart-pizza');
        if (!el) return;
        if (d30+d60+d90===0) { el.innerHTML='<div class="flex items-center justify-center h-full text-slate-400">Sem vencidos</div>'; return; }
        el.innerHTML='';
        this.chartPizza = new ApexCharts(el, {
            series: [d30, d60, d90],
            chart: { type: 'donut', height: 280, toolbar: { show: false },
                events: {
                    // ✅ RASTREÁVEL: clicar na fatia abre modal com representantes daquela faixa
                    dataPointSelection: (event, chartContext, config) => {
                        const faixas = ['30 Dias', '60 Dias', '+60 Dias'];
                        const faixa = faixas[config.dataPointIndex];
                        this.mostrarRepresentantesPorFaixa(faixa);
                    }
                }
            },
            labels: ['30 Dias', '60 Dias', '+60 Dias'],
            colors: ['#3b82f6', '#f59e0b', '#ef4444'],
            plotOptions: { pie: { donut: { size: '55%',
                labels: { show: true, name: { fontSize: '10px' },
                    value: { fontSize: '12px', fontWeight: 'bold', formatter: (v) => 'R$ ' + parseFloat(v).toLocaleString('pt-BR', { minimumFractionDigits: 0 }) },
                    total: { show: true, label: 'Total Vencido', fontSize: '12px', fontWeight: 'bold',
                        formatter: (w) => 'R$ ' + w.globals.seriesTotals.reduce((a,b)=>a+b,0).toLocaleString('pt-BR', { minimumFractionDigits: 0 }) }
                } } } },
            legend: { position: 'bottom', fontSize: '11px' },
            tooltip: { y: { formatter: (v) => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) } }
        });
        this.chartPizza.render();
    },

    // Mostrar modal com representantes por faixa de atraso
    mostrarRepresentantesPorFaixa(faixa) {
        const campo = faixa === '30 Dias' ? '30 Dias' : (faixa === '60 Dias' ? '60 Dias' : 'M60 Dias');
        const reps = this.dadosRepresentantes
            .filter(r => parseFloat(r[campo] || 0) > 0)
            .sort((a, b) => parseFloat(b[campo] || 0) - parseFloat(a[campo] || 0));

        if (!reps.length) {
            Swal.fire('Informação', `Nenhum representante com vencidos em ${faixa}`, 'info');
            return;
        }

        let html = `<div class="text-left text-xs" style="max-height: 400px; overflow-y: auto;">
            <table class="w-full">
                <thead><tr class="bg-slate-100">
                    <th class="p-2 text-left">Representante</th>
                    <th class="p-2 text-right">Valor</th>
                    <th class="p-2 text-center">Ação</th>
                </tr></thead><tbody>`;

        reps.forEach(r => {
            html += `<tr class="border-b hover:bg-blue-50">
                <td class="p-2 font-medium">${r['Nome do Representante']}</td>
                <td class="p-2 text-right font-bold text-rose-600">${this.fmtMoeda(r[campo])}</td>
                <td class="p-2 text-center">
                    <button onclick="Swal.close();selecionarRepresentante(${r.id_representante},'${(r['Nome do Representante']||'').replace(/'/g,"\\'")}')" 
                            class="px-2 py-1 bg-blue-500 text-white rounded text-xs font-bold hover:bg-blue-600">
                        Ver Títulos
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table></div>';

        Swal.fire({
            title: `Representantes com vencidos em ${faixa}`,
            html: html,
            width: '650px',
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#375a4b'
        });
    },

    // ======================================================================
    // GRÁFICO RANKING - RASTREÁVEL (clique na barra seleciona representante)
    // ======================================================================
    renderizarGraficoRanking() {
        if (this.chartRanking) { this.chartRanking.destroy(); this.chartRanking = null; }
        const top10 = [...this.dadosRepresentantes]
            .sort((a,b) => parseFloat(b['Vencidos']||0) - parseFloat(a['Vencidos']||0))
            .slice(0, 10);
        const el = document.querySelector('#chart-ranking');
        if (!el) return;
        if (!top10.length) { el.innerHTML='<div class="flex items-center justify-center h-full text-slate-400">Sem dados</div>'; return; }
        el.innerHTML='';

        this.chartRanking = new ApexCharts(el, {
            series: [
                { name: 'Vencidos (R$)', type: 'bar', data: top10.map(r => parseFloat(r['Vencidos']||0)) },
                { name: 'IAG (%)', type: 'line', data: top10.map(r => parseFloat(r['IAG Rep (%)']||0)) }
            ],
            chart: { height: 300, toolbar: { show: false },
                events: {
                    // ✅ RASTREÁVEL: clicar na barra seleciona o representante
                    dataPointSelection: (event, chartContext, config) => {
                        const rep = top10[config.dataPointIndex];
                        if (rep) {
                            selecionarRepresentante(rep.id_representante, rep['Nome do Representante']);
                        }
                    },
                    // Também funciona com clique normal
                    click: (event, chartContext, config) => {
                        if (config.dataPointIndex >= 0) {
                            const rep = top10[config.dataPointIndex];
                            if (rep) {
                                selecionarRepresentante(rep.id_representante, rep['Nome do Representante']);
                            }
                        }
                    }
                }
            },
            colors: ['#ef4444', '#f59e0b'],
            plotOptions: { bar: { borderRadius: 6, horizontal: true, barHeight: '60%' } },
            stroke: { width: [0, 3], curve: 'smooth' },
            markers: { size: 5, colors: ['#f59e0b'] },
            xaxis: { categories: top10.map(r => (r['Nome do Representante']||'').split(' ').slice(0,2).join(' ')), labels: { style: { fontSize: '10px' } } },
            yaxis: [
                { title: { text: 'R$' }, labels: { formatter: (v) => 'R$ ' + (v/1000).toFixed(0) + 'k' } },
                { opposite: true, title: { text: 'IAG %' }, labels: { formatter: (v) => v + '%' } }
            ],
            tooltip: { shared: true,
                y: [
                    { formatter: (v) => 'R$ ' + v.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) },
                    { formatter: (v) => v.toFixed(2) + '%' }
                ]
            },
            legend: { position: 'bottom', fontSize: '11px' },
            grid: { borderColor: '#f1f5f9' }
        });
        this.chartRanking.render();
    },

    setText(id, text) { const el = document.getElementById(id); if (el) el.innerText = text; },

    limparTudo() {
        document.getElementById('secao-titulos').style.display = 'none';
        document.getElementById('secao-resumo-gestor').style.display = 'none';
        document.getElementById('select-representante').innerHTML = '<option value="">Todos os representantes</option>';
        this.zerarKPIs();
        document.getElementById('tabela-rep-body').innerHTML = '<tr><td colspan="13" class="text-center py-12 text-slate-400">Selecione um gestor</td></tr>';
        document.getElementById('tabela-resumo-gestor-body').innerHTML = '';
        document.getElementById('tabela-titulos-body').innerHTML = '<tr><td colspan="8" class="text-center py-8 text-slate-400">Selecione um representante</td></tr>';
        document.getElementById('subtitulo-header').innerText = 'Inadimplência por Gestor e Representante';
        if (this.chartPizza) { this.chartPizza.destroy(); this.chartPizza = null; }
        if (this.chartRanking) { this.chartRanking.destroy(); this.chartRanking = null; }
        const ep = document.querySelector('#chart-pizza');
        const er = document.querySelector('#chart-ranking');
        if (ep) ep.innerHTML = '<div class="flex items-center justify-center h-full text-slate-400">Aguardando gestor</div>';
        if (er) er.innerHTML = '<div class="flex items-center justify-center h-full text-slate-400">Aguardando gestor</div>';
    },

    showShimmer() { const b = document.getElementById('shimmer-bar'); if (b) b.style.display = 'block'; },
    hideShimmer() { const b = document.getElementById('shimmer-bar'); if (b) b.style.display = 'none'; }
};

// ===== FUNÇÕES GLOBAIS =====
function selecionarRepresentante(id, nome) {
    document.getElementById('select-representante').value = id;
    analiseCarteiraApp.representanteSelecionado = id;
    analiseCarteiraApp.nomeRepresentante = nome;
    analiseCarteiraApp.carregarTitulos().then(() => {
        document.getElementById('secao-titulos').style.display = 'block';
        document.getElementById('titulos-nome-rep').innerText = `Representante: ${nome} | Gestor: ${analiseCarteiraApp.nomeGestor}`;
        document.getElementById('secao-titulos').scrollIntoView({ behavior: 'smooth' });
    });
}

function scrollToTable(id) { document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' }); }

async function exportarExcelCompleto() {
    if (!analiseCarteiraApp.dadosRepresentantes.length) { Swal.fire('Atenção','Nenhum dado','warning'); return; }
    const ws = XLSX.utils.json_to_sheet(analiseCarteiraApp.dadosRepresentantes);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Representantes');
    XLSX.writeFile(wb, `analise-carteira-${analiseCarteiraApp.nomeGestor.replace(/\s+/g,'_')}-${new Date().toISOString().split('T')[0]}.xlsx`);
}

async function exportarExcelTitulos() {
    if (!analiseCarteiraApp.dadosTitulos.length) { Swal.fire('Atenção','Nenhum título','warning'); return; }
    const ws = XLSX.utils.json_to_sheet(analiseCarteiraApp.dadosTitulos);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Titulos');
    XLSX.writeFile(wb, `titulos-${analiseCarteiraApp.nomeRepresentante.replace(/\s+/g,'_')}-${new Date().toISOString().split('T')[0]}.xlsx`);
}

document.addEventListener('DOMContentLoaded', () => {
    window.analiseCarteiraApp = analiseCarteiraApp;
    analiseCarteiraApp.init();
});