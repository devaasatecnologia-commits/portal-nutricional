// ==========================================================================
// MÓDULO FINANCEIRO
// A identidade do usuário vem sempre do JWT no backend; o seletor de
// "Usuário (Visão)" só envia um alvo que o backend valida (403 se não permitido).
// ==========================================================================

const NIVEL_NOME = { filial: 'FILIAL', gestor: 'GESTOR', representante: 'REPRESENTANTE', cliente: 'CLIENTE' };

function esc(valor) {
    return String(valor ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function fmtBRL(v) {
    return (parseFloat(v) || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function fmtPct(v) {
    return (parseFloat(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
}

function fmtNum(v) {
    return (parseFloat(v) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function fmtData(d) {
    if (!d || d === 'Sem Data') return 'S/DATA';
    if (typeof d === 'string') {
        if (d.includes('/')) return d.substring(0, 10);
        const p = d.substring(0, 10).split('-');
        if (p.length === 3) return `${p[2]}/${p[1]}/${p[0]}`;
    }
    return 'S/DATA';
}

const app = {
    nivel: 'filial',
    f_id: null,
    path: [{ nivel: 'filial', f_id: null, nome: 'TODAS AS FILIAIS' }],
    chart: null,
    initialized: false,
    loadSeq: 0,

    fire(opts) {
        return Swal.fire({ position: 'top', ...opts });
    },

    msg(title, text, icon) {
        return this.fire({ title, text, icon });
    },

    async init() {
        document.getElementById('hist-day').value = new Date().getDay();
        this.updateBreadcrumb();
        await this.loadUsersList();
        await this.load();
        this.loadHistory();
    },

    async api(endpoint, body) {
        const resp = await fetchWithAuth(`/v1/financeiro/${endpoint}`, {
            method: 'POST',
            body: JSON.stringify(body)
        });
        let data = null;
        try {
            data = await resp.json();
        } catch (e) {
            data = null;
        }
        if (!resp.ok) {
            throw new Error((data && (data.error || data.erro)) || `Erro ${resp.status}`);
        }
        return data;
    },

    // ---------------- parâmetros ----------------
    lerInteiro(id, padrao) {
        const el = document.getElementById(id);
        let v = parseInt(el ? el.value : '', 10);
        if (isNaN(v) || v < 1) v = padrao;
        if (v > 365) v = 365;
        if (el) el.value = v;
        return v;
    },

    alvoUsuario() {
        const sel = document.getElementById('global-user-filter');
        return sel && sel.value ? sel.value : 0;
    },

    filialSelecionada() {
        const sel = document.getElementById('select-filial');
        return sel && sel.value ? parseInt(sel.value, 10) || 0 : 0;
    },

    params() {
        return {
            idusuario: this.alvoUsuario(),
            idfilial: this.filialSelecionada(),
            dias_recup: this.lerInteiro('filtro-dias-recup', 120),
            corte_atraso: this.lerInteiro('filtro-corte-atraso', 8)
        };
    },

    paramsNivel() {
        const p = this.params();
        // No nível filial o filtro_id é a própria filial
        const filtro = this.nivel === 'filial' ? p.idfilial : (this.f_id || 0);
        return { ...p, nivel: this.nivel, filtro_id: filtro };
    },

    onParamChange() {
        this.load();
    },

    // ---------------- navegação ----------------
    rotuloBase() {
        const selUser = document.getElementById('global-user-filter');
        const selFil = document.getElementById('select-filial');
        const filial = selFil && selFil.value ? selFil.options[selFil.selectedIndex].text : 'TODAS AS FILIAIS';
        const outro = selUser && selUser.style.display !== 'none' && selUser.value
            ? selUser.options[selUser.selectedIndex].text + ' - ' : '';
        return outro + filial;
    },

    updateBreadcrumb() {
        const container = document.getElementById('b-path');
        if (!container) return;
        container.innerHTML = '';
        this.path.forEach((step, index) => {
            const span = document.createElement('span');
            span.innerText = step.nome;
            if (index === this.path.length - 1) {
                span.className = 'text-slate-700 cursor-default font-bold';
            } else {
                span.className = 'cursor-pointer underline hover:text-amber-500 transition-colors';
                span.addEventListener('click', () => this.goToBreadcrumb(index));
            }
            container.appendChild(span);
            if (index < this.path.length - 1) {
                const sep = document.createElement('span');
                sep.className = 'mx-2 text-slate-400';
                sep.innerText = '›';
                container.appendChild(sep);
            }
        });
    },

    goToBreadcrumb(index) {
        this.path = this.path.slice(0, index + 1);
        const alvo = this.path[index];
        this.nivel = alvo.nivel;
        this.f_id = alvo.f_id;
        this.updateBreadcrumb();
        this.load();
    },

    async loadUsersList() {
        const select = document.getElementById('global-user-filter');
        if (!select) return;
        try {
            const usuarios = await this.api('lista-usuarios', {});
            select.innerHTML = '';
            const meu = (usuarios && usuarios[0]) ? usuarios[0].id : null;
            const outros = (usuarios || []).filter(u => u.id !== meu);
            if (outros.length > 0) {
                // Valor vazio = o próprio usuário logado
                const opt0 = document.createElement('option');
                opt0.value = '';
                opt0.innerText = 'MINHA VISÃO';
                select.appendChild(opt0);
                outros.forEach(u => {
                    const opt = document.createElement('option');
                    opt.value = u.id;
                    opt.innerText = String(u.nome || '').toUpperCase();
                    select.appendChild(opt);
                });
                select.style.display = 'block';
            } else {
                select.style.display = 'none';
            }
        } catch (e) {
            console.error('Erro ao carregar usuários:', e);
            select.style.display = 'none';
        }
    },

    changeUser() {
        this.nivel = 'filial';
        this.f_id = null;
        this.initialized = false;
        const selFil = document.getElementById('select-filial');
        if (selFil) selFil.value = '';
        this.path = [{ nivel: 'filial', f_id: null, nome: 'TODAS AS FILIAIS' }];
        this.updateBreadcrumb();
        this.load().then(() => this.loadHistory());
    },

    changeFilial() {
        this.nivel = 'filial';
        this.f_id = null;
        this.path = [{ nivel: 'filial', f_id: null, nome: this.rotuloBase() }];
        this.updateBreadcrumb();
        this.load();
        this.loadHistory();
    },

    reset() {
        const selUser = document.getElementById('global-user-filter');
        if (selUser) selUser.value = '';
        document.getElementById('filtro-dias-recup').value = 120;
        document.getElementById('filtro-corte-atraso').value = 8;
        document.getElementById('hist-type').value = 'iag_calculado';
        document.getElementById('hist-day').value = new Date().getDay();
        this.changeUser();
    },

    drill(id, nome) {
        if (this.nivel === 'filial') {
            this.nivel = 'gestor';
        } else if (this.nivel === 'gestor') {
            this.nivel = 'representante';
        } else {
            this.showClientTitles(id, nome);
            return;
        }
        this.f_id = id;
        this.path.push({ nivel: this.nivel, f_id: id, nome });
        this.updateBreadcrumb();
        this.load();
    },

    // ---------------- dashboard ----------------
    setText(id, valor) {
        const el = document.getElementById(id);
        if (el) el.innerText = valor;
    },

    mostrarErroCards(msg) {
        const el = document.getElementById('erro-cards');
        if (!el) return;
        el.style.display = msg ? 'block' : 'none';
        el.innerText = msg || '';
    },

    estadoTabela(msg, erro) {
        const el = document.getElementById('t-estado');
        if (!el) return;
        el.style.display = msg ? 'block' : 'none';
        el.className = 'fin-estado' + (erro ? ' erro' : '');
        el.innerText = msg || '';
    },

    popularFiliais(config) {
        const select = document.getElementById('select-filial');
        if (!select) return;
        const filiais = config.filiais || [];
        select.innerHTML = '';
        select.style.display = filiais.length <= 1 ? 'none' : 'block';
        if (filiais.length > 1) {
            const optTodas = document.createElement('option');
            optTodas.value = '';
            optTodas.innerText = 'TODAS AS FILIAIS';
            select.appendChild(optTodas);
        }
        filiais.forEach(f => {
            const opt = document.createElement('option');
            opt.value = f.idfilial;
            opt.innerText = f.nome;
            select.appendChild(opt);
        });
        if (config.filial_padrao) select.value = config.filial_padrao;
        else if (filiais.length === 1) select.value = filiais[0].idfilial;
    },

    async load() {
        const seq = ++this.loadSeq;
        const shimmer = document.getElementById('shimmer');
        if (shimmer) shimmer.style.display = 'block';
        this.mostrarErroCards('');
        this.estadoTabela('');
        try {
            const res = await this.api('dashboard', this.paramsNivel());
            if (seq !== this.loadSeq) return;

            if (!this.initialized) {
                this.setText('user-display', res.config?.usuario || 'USUÁRIO');
                this.popularFiliais(res.config || {});
                if (this.path.length === 1) {
                    this.path[0].nome = this.rotuloBase();
                    this.updateBreadcrumb();
                }
                this.initialized = true;
            }

            const r = res.resumo_filial || {};
            const total = parseFloat(r.total) || 0;
            const venc = parseFloat(r.vencidos) || 0;
            const iap = parseFloat(r.valor_iap) || 0;
            this.setText('f-total', fmtBRL(total));
            this.setText('f-avencer', fmtBRL(r.a_vencer));
            this.setText('f-30', fmtBRL(r.d30));
            this.setText('f-60', fmtBRL(r.d60));
            this.setText('f-90', fmtBRL(r.d90));
            this.setText('kpi-iag', fmtPct(total > 0 ? venc * 100 / total : 0));
            this.setText('kpi-iap', fmtPct(total > 0 ? iap * 100 / total : 0));
            this.setText('kpi-iag-valor', `${fmtBRL(venc)} vencido`);
            this.setText('kpi-iap-valor', `${fmtBRL(iap)} vencido há mais de 30 dias`);
            this.setText('kpi-recup', fmtPct(res.taxa_recup));
            const d = res.recup_detalhe || {};
            this.setText('kpi-recup-quant', `${d.pagos || 0} pagos de ${d.total || 0} títulos trabalhados`);
            this.renderTable(res.tabela || []);
        } catch (e) {
            if (seq !== this.loadSeq) return;
            console.error('Erro no load:', e);
            this.mostrarErroCards(`Não foi possível carregar os indicadores: ${e.message}`);
            ['f-total', 'f-avencer', 'f-30', 'f-60', 'f-90', 'kpi-iag', 'kpi-iap', 'kpi-recup'].forEach(id => this.setText(id, '—'));
            this.setText('kpi-iag-valor', '');
            this.setText('kpi-iap-valor', '');
            this.setText('kpi-recup-quant', '');
            this.renderTable([], true);
            this.estadoTabela(`Erro ao carregar a tabela: ${e.message}`, true);
        } finally {
            if (seq === this.loadSeq && shimmer) shimmer.style.display = 'none';
        }
    },

    renderTable(dados, erro) {
        const head = document.getElementById('t-head');
        const body = document.getElementById('t-body');
        if (!head || !body) return;
        body.innerHTML = '';
        const titulos = { filial: 'Gestor', gestor: 'Representante', representante: 'Cliente' };
        head.innerHTML = `<tr>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">${esc(titulos[this.nivel] || 'Item')}</th>
            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Total Vencido</th>
            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase" title="IAG = vencido ÷ carteira. Crítico > 10%, Atenção ≥ 5%">IAG</th>
            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase" title="Crítico > 10% · Atenção ≥ 5% · Saudável < 5%">Status</th>
        </tr>`;

        if (!dados.length) {
            if (!erro) this.estadoTabela('Nenhum registro com saldo para este nível/filtro.');
            return;
        }

        dados.forEach(i => {
            let cor = '#10b981';
            if (i.performance === 'CRÍTICO') cor = '#ef4444';
            else if (i.performance === 'ATENÇÃO') cor = '#f59e0b';
            const tr = document.createElement('tr');
            tr.className = 'border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-all';
            tr.dataset.id = i.id;
            tr.dataset.nome = i.nome;
            tr.addEventListener('click', () => this.drill(i.id, i.nome));
            tr.innerHTML = `
                <td class="px-4 py-3"><b>${esc(i.nome)}</b><br><small class="text-slate-400">ID: ${esc(i.id)}</small></td>
                <td class="px-4 py-3 text-right font-semibold">${fmtBRL(i.vencidos)}</td>
                <td class="px-4 py-3 text-center"><b>${fmtPct(i.iag)}</b></td>
                <td class="px-4 py-3 text-center"><span style="color:${cor}; border:1px solid ${cor}; padding:4px 8px; border-radius:6px; font-weight:700;">${esc(i.performance)}</span></td>`;
            body.appendChild(tr);
        });
    },

    // ---------------- títulos do cliente ----------------
    async showClientTitles(id_cliente, nome_cliente) {
        this.fire({ title: 'Buscando títulos...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        try {
            const res = await this.api('relatorio-detalhado', {
                ...this.params(),
                nivel: 'cliente',
                filtro_id: id_cliente
            });
            if (!res || res.length === 0) {
                this.msg('Aviso', 'Este cliente não possui títulos pendentes com saldo.', 'info');
                return;
            }

            let html = `<div style="max-height:450px; overflow-y:auto; text-align:left;">
                <table style="width:100%; font-size:0.8rem; border-collapse: collapse;">
                <thead><tr style="background:#f1f5f9; position:sticky; top:0;">
                    <th style="padding:8px; text-align:left;">Título / Venc.</th>
                    <th style="padding:8px; text-align:right;">Saldo</th>
                    <th style="padding:8px; text-align:left;">Último apontamento</th>
                </tr></thead><tbody>`;
            for (const t of res) {
                const atraso = parseInt(t.dias_atraso, 10) || 0;
                const atrasoHtml = atraso > 0
                    ? `<span style="color:#ef4444;">+${atraso}d</span>` : `<span style="color:#10b981;">${atraso}d</span>`;
                let evento = '<i style="color:#94a3b8;">Sem registro</i>';
                if (t.evento && t.evento !== 'Sem Registro') {
                    evento = `<b style="color:#274036;">${esc(t.evento)}</b>`;
                    if (t.ultimo_evento && t.ultimo_evento !== 'Sem Data') {
                        evento += `<br><small style="color:#64748b;">${esc(t.ultimo_evento)}</small>`;
                    }
                    if (t.descricao && t.descricao !== 'Sem Registro') {
                        const d = String(t.descricao);
                        evento += `<br><span style="color:#475569; font-size:0.7rem;">"${esc(d.substring(0, 50))}${d.length > 50 ? '...' : ''}"</span>`;
                    }
                }
                html += `<tr style="border-bottom:1px solid #e2e8f0;">
                    <td style="padding:8px;"><b>${esc(t.documento)}</b><br>
                        <small style="color:#64748b;">Venc: ${esc(fmtData(t.vencimento))} (${atrasoHtml})</small></td>
                    <td style="padding:8px; text-align:right; font-weight:bold; color:#dc2626;">${fmtBRL(t.valorsaldo)}</td>
                    <td style="padding:8px;">${evento}</td></tr>`;
            }
            html += '</tbody></table></div>';
            const aviso = res[0].total_linhas > res.length
                ? `<p style="font-size:0.75rem;color:#b45309;margin-top:8px;">Exibindo ${res.length} de ${res[0].total_linhas} títulos.</p>` : '';

            this.fire({
                title: `Títulos do cliente: ${esc(nome_cliente)}`,
                html: html + aviso,
                width: '850px',
                confirmButtonColor: '#274036',
                confirmButtonText: 'Fechar'
            });
        } catch (e) {
            console.error(e);
            this.msg('Erro', `Falha ao carregar títulos: ${e.message}`, 'error');
        }
    },

    // ---------------- histórico ----------------
    formatCurrency(v) {
        return fmtBRL(v);
    },

    estadoGrafico(msg, erro) {
        const el = document.getElementById('chart-estado');
        const canvas = document.getElementById('chartHistory');
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
        if (canvas) canvas.style.display = msg ? 'none' : 'block';
        if (!el) return;
        el.style.display = msg ? 'flex' : 'none';
        el.className = 'fin-estado' + (erro ? ' erro' : '');
        el.innerText = msg || '';
    },

    async loadHistory() {
        try {
            const type = document.getElementById('hist-type')?.value || 'iag_calculado';
            const day = document.getElementById('hist-day')?.value || new Date().getDay();
            const res = await this.api('historico-kpi', {
                idusuario: this.alvoUsuario(),
                tipo: type,
                filtro_id: this.filialSelecionada(),
                dia_semana: day
            });
            if (!res || res.length === 0) {
                this.estadoGrafico('Sem registros no histórico para este filtro e dia da semana.');
                return;
            }
            this.estadoGrafico('');
            const canvas = document.getElementById('chartHistory');
            if (!canvas) return;
            const labels = res.map(h => h.data);
            const values = res.map(h => parseFloat(h.valor) || 0);
            const labelMap = { iag_calculado: 'IAG %', iap_calculado: 'IAP %', taxa_recuperacao: 'Recuperação %' };
            const self = this;
            this.chart = new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: labelMap[type] || type,
                        data: values,
                        backgroundColor: (c) => {
                            const area = c.chart.chartArea;
                            if (!area) return '#274036';
                            const g = c.chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                            g.addColorStop(0, '#274036');
                            g.addColorStop(1, '#4a7a67');
                            return g;
                        },
                        borderRadius: 8,
                        hoverBackgroundColor: '#f7be2f'
                    }]
                },
                plugins: [{
                    id: 'numerosNoTopo',
                    afterDatasetsDraw(chart) {
                        const ctx = chart.ctx;
                        chart.data.datasets.forEach((dataset, i) => {
                            chart.getDatasetMeta(i).data.forEach((ponto, idx) => {
                                ctx.save();
                                ctx.font = 'bold 11px "Plus Jakarta Sans"';
                                ctx.fillStyle = '#274036';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'bottom';
                                ctx.fillText(fmtPct(dataset.data[idx]), ponto.x, ponto.y - 8);
                                ctx.restore();
                            });
                        });
                    }
                }],
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    layout: { padding: { top: 25 } },
                    onClick: (_, els) => {
                        if (els.length > 0) self.showHistoryDetails(res[els[0].index], type);
                    },
                    onHover: (event, els) => {
                        event.native.target.style.cursor = els.length > 0 ? 'pointer' : 'default';
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(30,41,59,0.95)',
                            callbacks: {
                                label: (c) => `Percentual: ${fmtPct(c.raw)}`,
                                afterLabel: (c) => {
                                    const item = res[c.dataIndex];
                                    if (type === 'taxa_recuperacao') {
                                        return `Recuperados: ${item.abs_recup_pagos} de ${item.abs_recup_total}`;
                                    }
                                    const v = type === 'iap_calculado' ? item.abs_iap : item.abs_iag;
                                    return `Valor-base: ${fmtBRL(v)}`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { callback: (v) => v + '%' } },
                        x: { grid: { display: false } }
                    }
                }
            });
        } catch (e) {
            console.error('Erro no gráfico:', e);
            this.estadoGrafico(`Erro ao carregar o histórico: ${e.message}`, true);
        }
    },

    showHistoryDetails(item, type) {
        const nomes = {
            iag_calculado: 'Inadimplência Geral (IAG)',
            iap_calculado: 'Atraso Crítico (IAP)',
            taxa_recuperacao: 'Taxa de Recuperação'
        };
        const data = item.data_registro ? item.data_registro.split('-').reverse().join('/') : item.data;
        let componentes;
        if (type === 'taxa_recuperacao') {
            componentes = `
                <div><span>Recuperados</span><strong>${esc(item.abs_recup_pagos)}</strong></div>
                <div><span>Base trabalhada</span><strong>${esc(item.abs_recup_total)}</strong></div>`;
        } else {
            const v = type === 'iap_calculado' ? item.abs_iap : item.abs_iag;
            componentes = `
                <div><span>Valor do indicador</span><strong>${fmtBRL(v)}</strong></div>
                <div><span>Carteira total</span><strong>${fmtBRL(item.abs_total)}</strong></div>`;
        }
        this.fire({
            title: nomes[type] || 'Histórico financeiro',
            html: `
                <div style="display:grid; gap:10px; text-align:left; font-size:0.85rem;">
                    <div style="padding:12px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px;">
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;"><span>Data registrada</span><strong>${esc(data)}</strong></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;"><span>Resultado</span><strong>${fmtPct(item.valor)}</strong></div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:8px;"><span>Filiais</span><strong>${esc(item.filiais || '-')}</strong></div>
                        <div style="display:flex; justify-content:space-between;"><span>Registros consolidados</span><strong>${esc(item.registros_origem || 0)}</strong></div>
                    </div>
                    <div style="display:grid; gap:8px;">${componentes}</div>
                    <div style="font-size:0.7rem; color:#94a3b8;">Histórico consolidado por filial – não considera filtro de gestor/representante.</div>
                </div>`,
            confirmButtonText: 'Fechar',
            confirmButtonColor: '#274036'
        });
    },

    // ---------------- auditoria ----------------
    async showAudit(tipo) {
        this.fire({ title: 'Aguarde...', didOpen: () => Swal.showLoading() });
        try {
            const p = this.paramsNivel();
            const res = await this.api('detalhes-kpi', { ...p, tipo });
            if (!res || res.length === 0) {
                this.msg('Info', 'Nenhum registro encontrado.', 'info');
                return;
            }
            const titulos = { iag: 'IAG – Top 10 clientes por saldo vencido', iap: 'IAP – Top 10 clientes por saldo vencido há mais de 30 dias', recup: 'Taxa de Recuperação – cenários' };
            const nota = tipo === 'recup'
                ? `<p style="font-size:0.75rem;color:#64748b;text-align:left;margin-bottom:8px;">Janela: títulos vencidos nos últimos <b>${p.dias_recup}</b> dias · Cenário 1: atraso ≥ <b>${p.corte_atraso}</b> dias · status 5 (renegociado/cancelado) excluído. Taxa = cenário 3 ÷ (cenário 2 + cenário 3).</p>`
                : '';
            let html = `${nota}<div style="max-height:400px; overflow:auto;"><table style="width:100%; border-collapse: collapse;">
                <thead><tr style="background:#f1f5f9; position:sticky; top:0;">
                    <th style="padding:8px; text-align:left;">${tipo === 'recup' ? 'Cenário' : 'Cliente'}</th>
                    <th style="padding:8px; text-align:right;">${tipo === 'recup' ? 'Qtd títulos' : 'Valor'}</th>
                </tr></thead><tbody>`;
            res.forEach(d => {
                const valor = d.is_qtd === true ? esc(d.valor) : fmtBRL(d.valor);
                const clic = d.cenario_id ? ` data-cenario="${esc(d.cenario_id)}" data-label="${esc(d.label)}" style="cursor:pointer;border-bottom:1px solid #e2e8f0;"` : ' style="border-bottom:1px solid #e2e8f0;"';
                const lupa = d.cenario_id ? '<i class="fa-solid fa-magnifying-glass" style="margin-left:8px; color:#274036;"></i>' : '';
                html += `<tr${clic}><td style="padding:8px;"><b>${esc(d.label)}</b></td>
                    <td style="padding:8px; text-align:right;"><b>${valor}</b> ${lupa}</td></tr>`;
            });
            html += '</tbody></table></div>';
            this.fire({
                title: titulos[tipo] || tipo.toUpperCase(),
                html,
                width: '650px',
                confirmButtonColor: '#274036',
                customClass: { popup: 'audit-modal' },
                didOpen: (popup) => {
                    popup.querySelectorAll('tr[data-cenario]').forEach(tr => {
                        tr.addEventListener('click', () => this.showRecupDetails(parseInt(tr.dataset.cenario, 10), tr.dataset.label));
                    });
                }
            });
        } catch (e) {
            console.error('Erro detalhado:', e);
            this.msg('Erro', `Falha ao buscar detalhes: ${e.message}`, 'error');
        }
    },

    async showRecupDetails(cenario_id, titulo) {
        this.fire({ title: 'Buscando títulos...', didOpen: () => Swal.showLoading() });
        try {
            const res = await this.api('detalhes-kpi', { ...this.paramsNivel(), tipo: 'recup', cenario: cenario_id });
            if (!res || res.length === 0) {
                this.msg('Info', 'Nenhum título encontrado.', 'info');
                return;
            }
            const colValor = cenario_id === 3 ? 'Recebido' : 'Saldo';
            let html = `<div style="max-height:450px; overflow:auto;"><table style="width:100%; font-size:0.8rem;">
                <thead><tr><th style="text-align:left;">Cliente</th><th>Doc / ${colValor}</th><th style="text-align:left;">Últ. evento</th></tr></thead><tbody>`;
            res.forEach(d => {
                const evento = d.desc_evento && d.desc_evento !== 'Sem Registro'
                    ? `${esc(d.desc_evento)}<br><small>(${esc(d.data_evento || '')})</small>`
                    : '<span style="color:#dc2626;">Sem apontamento</span>';
                html += `<tr><td style="padding:4px;">${esc(d.cliente)}</td>
                    <td style="text-align:center;"><b>${esc(d.documento)}</b><br>Venc: ${esc(d.data_vencimento)}<br><span style="color:#64748b;">${fmtBRL(d.valorsaldo)}</span></td>
                    <td style="padding:4px;">${evento}</td></tr>`;
            });
            const total = parseInt(res[0].total_cenario, 10) || res.length;
            const aviso = total > res.length
                ? `<p style="font-size:0.75rem;color:#b45309;margin-top:8px;">Exibindo os ${res.length} maiores de ${total} títulos.</p>` : '';
            this.fire({
                title: esc(titulo),
                html: html + '</tbody></table></div>' + aviso,
                width: '850px',
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-arrow-left"></i> Voltar',
                cancelButtonText: 'Fechar'
            }).then((result) => {
                if (result.isConfirmed) this.showAudit('recup');
            });
        } catch (e) {
            this.msg('Erro', `Falha ao buscar detalhes: ${e.message}`, 'error');
        }
    },

    // ---------------- relatório PDF ----------------
    async gerarRelatorio() {
        const p = this.params();
        const nivel = this.nivel;
        const filtroId = nivel === 'filial' ? 0 : (this.f_id || 0);

        const jsPDFCtor = window.jspdf?.jsPDF;
        if (!jsPDFCtor) {
            this.msg('Erro', 'A biblioteca de PDF (jsPDF) não foi carregada. Verifique a conexão e recarregue a página.', 'error');
            return;
        }

        this.fire({ title: 'Gerando relatório...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        try {
            const data = await this.api('relatorio-detalhado', { ...p, nivel, filtro_id: filtroId });
            Swal.close();
            if (!data || data.length === 0) {
                this.msg('Aviso', 'Nenhum título encontrado.', 'info');
                return;
            }

            const selFil = document.getElementById('select-filial');
            const nomeFilial = selFil && selFil.value ? selFil.options[selFil.selectedIndex].text : 'Todas as filiais';
            const nomeNivel = nivel === 'filial'
                ? nomeFilial
                : (this.path.find(x => x.nivel === nivel)?.nome || '');
            const titulo = `RELATÓRIO FINANCEIRO - ${NIVEL_NOME[nivel] || 'GERAL'}: ${nomeNivel}`;
            const nomeUsuario = (typeof getUserNome === 'function' ? getUserNome() : '') || 'Sistema';

            const doc = gerarPDFFinanceiro(jsPDFCtor, data, nomeUsuario, nomeFilial, titulo);
            doc.save(`relatorio_financeiro_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`);

            const totalLinhas = parseInt(data[0].total_linhas, 10) || data.length;
            const truncado = totalLinhas > data.length;
            this.fire({
                icon: truncado ? 'warning' : 'success',
                title: truncado ? 'PDF gerado com limite' : 'PDF gerado!',
                text: truncado
                    ? `Exibindo ${data.length} de ${totalLinhas} títulos (limite do relatório). Filtre por gestor/representante para ver o restante.`
                    : `${data.length} títulos`,
                timer: truncado ? 8000 : 3000,
                showConfirmButton: truncado
            });
        } catch (error) {
            Swal.close();
            this.msg('Erro', error.message, 'error');
        }
    }
};

// ==========================================================================
// PDF (A4 paisagem, 277mm úteis, fontes >= 6pt)
// ==========================================================================
function gerarPDFFinanceiro(jsPDF, dados, usuario, nomeFilial, titulo) {
    const doc = new jsPDF('l', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 10;
    const usable = pageWidth - margin * 2;
    const FONTE = 6;
    const ALTURA = 5.6;

    const colunas = [
        { label: 'EMISSÃO', width: 16, align: 'center' },
        { label: 'CLIENTE', width: 44, align: 'left' },
        { label: 'DOCUMENTO', width: 26, align: 'left' },
        { label: 'VENC.', width: 16, align: 'center' },
        { label: 'RECEBIDO', width: 20, align: 'right' },
        { label: 'SALDO', width: 20, align: 'right' },
        { label: 'ATRASO', width: 12, align: 'center' },
        { label: 'REPRESENTANTE', width: 30, align: 'left' },
        { label: 'DT ÚLT. EVENTO', width: 18, align: 'center' },
        { label: 'ÚLTIMO EVENTO', width: 50, align: 'left' },
        { label: 'DIAS S/ COB.', width: 13, align: 'center' },
        { label: 'USUÁRIO', width: 12, align: 'left' }
    ];
    const soma = colunas.reduce((s, c) => s + c.width, 0);
    colunas[9].width += usable - soma;

    const semReg = (v) => !v || v === 'Sem Registro' || v === 'Sem Data';
    const ajustar = (texto, largura) => {
        let t = String(texto ?? '');
        if (doc.getTextWidth(t) <= largura) return t;
        while (t.length > 1 && doc.getTextWidth(t + '..') > largura) t = t.slice(0, -1);
        return t + '..';
    };

    const totalLinhas = parseInt(dados[0]?.total_linhas, 10) || dados.length;
    const totalValor = parseFloat(dados[0]?.total_geral_valor) || 0;
    const totalRecebido = parseFloat(dados[0]?.total_geral_recebido) || 0;
    const totalSaldo = parseFloat(dados[0]?.total_geral_saldo) || 0;
    const gestores = [...new Set(dados.map(i => i.gestor).filter(Boolean))];

    function cabecalho(pagina) {
        let y = 12;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(11);
        doc.setTextColor(30, 41, 59);
        doc.text(ajustar(titulo, usable - 60), margin, y);
        doc.setFontSize(FONTE + 1);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(100, 116, 139);
        doc.text(`Gerado em: ${new Date().toLocaleString('pt-BR')}`, pageWidth - margin, y, { align: 'right' });
        y += 6;

        doc.setFontSize(FONTE + 1);
        doc.setTextColor(30, 41, 59);
        doc.setFont('helvetica', 'bold');
        doc.text('Usuário:', margin, y);
        doc.setFont('helvetica', 'normal');
        doc.text(ajustar(usuario, 60), margin + 14, y);
        doc.setFont('helvetica', 'bold');
        doc.text('Filial:', margin + 90, y);
        doc.setFont('helvetica', 'normal');
        doc.text(ajustar(nomeFilial, 90), margin + 100, y);
        const reg = totalLinhas > dados.length
            ? `${dados.length} de ${totalLinhas} (limite)` : `${dados.length}`;
        doc.setFont('helvetica', 'bold');
        doc.text('Registros:', pageWidth - margin - 45, y);
        doc.setFont('helvetica', 'normal');
        doc.text(reg, pageWidth - margin, y, { align: 'right' });
        y += 5;

        doc.setFont('helvetica', 'bold');
        doc.text('Gestor(es):', margin, y);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(71, 85, 105);
        doc.text(ajustar(gestores.join(', ') || 'Não informado', usable - 45), margin + 17, y);
        doc.setTextColor(100, 116, 139);
        doc.text(`Pág. ${pagina}`, pageWidth - margin, y, { align: 'right' });
        y += 5;

        const cw = (usable - 4) / 3;
        const cards = [
            ['TOTAL GERAL', totalValor, [30, 41, 59]],
            ['TOTAL RECEBIDO', totalRecebido, [5, 150, 105]],
            ['SALDO DEVEDOR', totalSaldo, [185, 28, 28]]
        ];
        cards.forEach((c, i) => {
            const x = margin + i * (cw + 2);
            doc.setFillColor(...c[2]);
            doc.roundedRect(x, y, cw, 9, 1.5, 1.5, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(FONTE);
            doc.text(c[0], x + 2, y + 3.6);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(FONTE + 2);
            doc.text(`R$ ${fmtNum(c[1])}`, x + 2, y + 7.6);
        });
        y += 12;

        // cabeçalho da tabela
        doc.setFillColor(30, 41, 59);
        doc.rect(margin, y, usable, 6, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(FONTE);
        let x = margin;
        colunas.forEach(col => {
            if (col.align === 'center') doc.text(col.label, x + col.width / 2, y + 4, { align: 'center' });
            else if (col.align === 'right') doc.text(col.label, x + col.width - 1.5, y + 4, { align: 'right' });
            else doc.text(col.label, x + 1.5, y + 4);
            x += col.width;
        });
        return y + 6.5;
    }

    let pagina = 1;
    let y = cabecalho(pagina);

    dados.forEach((item, idx) => {
        if (y + ALTURA > pageHeight - 12) {
            doc.addPage();
            pagina++;
            y = cabecalho(pagina);
        }
        doc.setFillColor(idx % 2 === 0 ? 248 : 255, idx % 2 === 0 ? 250 : 255, idx % 2 === 0 ? 252 : 255);
        doc.rect(margin, y, usable, ALTURA, 'F');
        doc.setDrawColor(226, 232, 240);
        doc.setLineWidth(0.1);
        doc.line(margin, y + ALTURA, pageWidth - margin, y + ALTURA);

        let rep = item.nome_vendedor || '';
        if (rep.includes(' / ')) rep = rep.split(' / ')[1] || rep;

        const saldo = parseFloat(item.valorsaldo) || 0;
        const recebido = parseFloat(item.totalrecebido) || 0;
        const atraso = parseInt(item.dias_atraso, 10) || 0;
        const dsc = semReg(item.ult_evento_dias) ? 'S/REG' : String(item.ult_evento_dias);
        const evento = semReg(item.descricao) ? 'S/REG' : item.descricao;

        const celulas = [
            { t: fmtData(item.dataemissao) },
            { t: `${item.idcliforemp || ''} ${String(item.nomefantasia || '').toUpperCase()}` },
            { t: `${String(item.documento || '-').toUpperCase()}${item.metodopagto ? ' ' + String(item.metodopagto).toUpperCase() : ''}` },
            { t: fmtData(item.vencimento) },
            { t: fmtNum(recebido), cor: recebido > 0 ? [5, 150, 105] : null },
            { t: fmtNum(saldo), cor: saldo > 0 ? [185, 28, 28] : null, bold: saldo > 0 },
            { t: String(atraso), cor: atraso > 30 ? [185, 28, 28] : (atraso > 0 ? [245, 158, 11] : [5, 150, 105]), bold: atraso > 30 },
            { t: (rep || 'S/REPRES').toUpperCase() },
            { t: semReg(item.ultimo_evento) ? 'S/DATA' : item.ultimo_evento, mudo: semReg(item.ultimo_evento) },
            { t: evento, mudo: evento === 'S/REG' },
            { t: dsc, mudo: dsc === 'S/REG', cor: parseInt(dsc, 10) > 30 ? [185, 28, 28] : null, bold: parseInt(dsc, 10) > 30 },
            { t: semReg(item.usuario) ? 'S/REG' : String(item.usuario).toUpperCase(), mudo: semReg(item.usuario) }
        ];

        let x = margin;
        celulas.forEach((c, i) => {
            const col = colunas[i];
            doc.setFont('helvetica', c.bold ? 'bold' : (c.mudo ? 'italic' : 'normal'));
            doc.setFontSize(FONTE);
            if (c.mudo) doc.setTextColor(148, 163, 184);
            else if (c.cor) doc.setTextColor(...c.cor);
            else doc.setTextColor(30, 41, 59);
            const t = ajustar(c.t, col.width - 2);
            const yb = y + 3.9;
            if (col.align === 'center') doc.text(t, x + col.width / 2, yb, { align: 'center' });
            else if (col.align === 'right') doc.text(t, x + col.width - 1, yb, { align: 'right' });
            else doc.text(t, x + 1, yb);
            x += col.width;
        });
        y += ALTURA;
    });

    const paginas = doc.internal.getNumberOfPages();
    for (let i = 1; i <= paginas; i++) {
        doc.setPage(i);
        doc.setDrawColor(39, 64, 54);
        doc.setLineWidth(0.3);
        doc.line(margin, pageHeight - 9, pageWidth - margin, pageHeight - 9);
        doc.setFontSize(FONTE);
        doc.setTextColor(148, 163, 184);
        doc.setFont('helvetica', 'normal');
        const aviso = totalLinhas > dados.length ? ` · Exibindo ${dados.length} de ${totalLinhas} títulos` : '';
        doc.text(`Nutricional - ${new Date().toLocaleDateString('pt-BR')}${aviso}`, margin, pageHeight - 5.5);
        doc.text(`Pág. ${i}/${paginas}`, pageWidth - margin, pageHeight - 5.5, { align: 'right' });
    }
    return doc;
}

window.addEventListener('DOMContentLoaded', () => {
    app.init();
});
window.app = app;
