// ==========================================================================
// MÓDULO FINANCEIRO - VERSÃO FINAL (SEM DEBUG)
// ==========================================================================

const app = {
    nivel: "filial",
    f_id: null,
    path: [{ nivel: "filial", f_id: null, nome: "GLOBAL" }],

    uid: (() => {
        try {
            const userData = JSON.parse(localStorage.getItem('userData') || '{}');
            return userData.uid || 5166;
        } catch {
            return 5166;
        }
    })(),

    chart: null,
    initialized: false,

    async init() {
        document.getElementById("hist-day").value = new Date().getDay();
        this.updateBreadcrumb();
        await this.load();
        await this.loadUsersList();
        this.loadHistory();
    },

    async api(endpoint, body) {
        const url = `/v1/financeiro/${endpoint}`;
        const resp = await fetchWithAuth(url, {
            method: 'POST',
            body: JSON.stringify(body)
        });
        return resp.json();
    },

    updateBreadcrumb() {
        const container = document.getElementById("b-path");
        if (!container) return;
        const niveisUnicos = [];
        const niveisVistos = new Set();
        for (let i = this.path.length - 1; i >= 0; i--) {
            const step = this.path[i];
            if (!niveisVistos.has(step.nivel)) {
                niveisVistos.add(step.nivel);
                niveisUnicos.unshift(step);
            }
        }
        this.path = niveisUnicos;
        container.innerHTML = "";
        this.path.forEach((step, index) => {
            const span = document.createElement("span");
            span.innerText = step.nome;
            if (index === this.path.length - 1) {
                span.className = "text-slate-700 cursor-default font-bold";
            } else {
                span.className = "cursor-pointer underline hover:text-amber-500 transition-colors";
                span.onclick = () => this.goToBreadcrumb(index);
            }
            container.appendChild(span);
            if (index < this.path.length - 1) {
                const sep = document.createElement("span");
                sep.className = "mx-2 text-slate-400";
                sep.innerText = "›";
                container.appendChild(sep);
            }
        });
    },

    goToBreadcrumb(index) {
        this.path = this.path.slice(0, index + 1);
        const target = this.path[index];
        this.nivel = target.nivel;
        this.f_id = target.f_id;
        this.updateBreadcrumb();
        this.load();
    },

    async loadUsersList() {
        const select = document.getElementById("global-user-filter");
        if (!select) return;
        try {
            const usuarios = await this.api("lista-usuarios", {
                idusuario: this.uid,
                idfilial: this.f_id
            });
            select.innerHTML = "";
            if (usuarios && usuarios.length > 1) {
                select.innerHTML = '<option value="">VISÃO GLOBAL (FILIAL)</option>';
                usuarios.forEach(u => {
                    const opt = document.createElement("option");
                    opt.value = u.id;
                    opt.innerText = u.nome.toUpperCase();
                    if (u.id == this.uid) opt.selected = true;
                    select.appendChild(opt);
                });
                select.style.display = "block";
            } else {
                select.style.display = "none";
            }
        } catch (e) {
            console.error("Erro ao carregar usuários:", e);
            select.style.display = "none";
        }
    },

    changeUser(val) {
        this.nivel = "filial";
        const selectUser = document.getElementById("global-user-filter");
        const selectFilial = document.getElementById("select-filial");
        const nomeUser = selectUser.options[selectUser.selectedIndex].text;
        const nomeFilial = selectFilial && selectFilial.selectedIndex >= 0 ? selectFilial.options[selectFilial.selectedIndex].text : "GLOBAL";
        const isMaster = selectUser.selectedIndex > 0;
        const label = isMaster ? `${nomeUser} - ${nomeFilial}` : nomeFilial;
        this.path = [{ nivel: "filial", f_id: this.f_id, nome: label }];
        this.updateBreadcrumb();
        this.load();
        this.loadHistory();
    },

    async load() {
        const shimmer = document.getElementById("shimmer");
        if (shimmer) shimmer.style.display = "block";
        try {
            const globalUserSelect = document.getElementById("global-user-filter");
            const currentUid = (globalUserSelect && globalUserSelect.value) ? globalUserSelect.value : this.uid;
            const diasRecupInput = document.getElementById("filtro-dias-recup");
            const diasRecup = (diasRecupInput && diasRecupInput.value) ? diasRecupInput.value : 120;
            const selectFilial = document.getElementById("select-filial");
            const idfilial = selectFilial && selectFilial.value ? selectFilial.value : 1;

            // NÍVEL CLIENTE – busca clientes via dashboard
            if (this.nivel === 'cliente') {
                const res = await this.api("dashboard", {
                    idusuario: currentUid,
                    nivel: this.nivel,
                    filtro_id: this.f_id,
                    dias_recup: diasRecup
                });
                this.renderTable(res.tabela || []);
                const resumoContainer = document.querySelector('.resumo-container');
                if (resumoContainer) resumoContainer.style.display = "none";
                if (shimmer) shimmer.style.display = "none";
                return;
            }

            // DEMAIS NÍVEIS (filial, gestor)
            const res = await this.api("dashboard", {
                idusuario: currentUid,
                nivel: this.nivel,
                filtro_id: this.f_id,
                dias_recup: diasRecup
            });

            if (!this.initialized) {
                const userDisplay = document.getElementById("user-display");
                if (userDisplay) userDisplay.innerText = res.config?.usuario || "USUÁRIO";
                const select = document.getElementById("select-filial");
                if (select) {
                    select.innerHTML = "";
                    if (res.config?.filiais) {
                        select.style.display = res.config.filiais.length <= 1 ? "none" : "block";
                        res.config.filiais.forEach(f => {
                            const opt = document.createElement("option");
                            opt.value = f.idfilial;
                            opt.innerText = f.nome;
                            select.appendChild(opt);
                        });
                    }
                }
                this.f_id = this.f_id || res.config?.filial_padrao;
                if (this.f_id && select) select.value = this.f_id;
                if (this.path.length === 1 && this.f_id) {
                    const selectedOpt = select ? select.options[select.selectedIndex] : null;
                    const nomeFilial = selectedOpt ? selectedOpt.text : "FILIAL";
                    const isMaster = globalUserSelect && globalUserSelect.style.display !== "none" && globalUserSelect.selectedIndex > 0;
                    const nomeUser = isMaster ? globalUserSelect.options[globalUserSelect.selectedIndex].text : "";
                    const label = isMaster ? `${nomeUser} - ${nomeFilial}` : nomeFilial;
                    this.path[0] = { nivel: "filial", f_id: this.f_id, nome: label };
                    this.updateBreadcrumb();
                }
                this.initialized = true;
            }

            const resu = res.resumo_filial || { total: 0, vencidos: 0, valor_iap: 0, d30: 0, d60: 0, d90: 0 };
            const fmt = { style: "currency", currency: "BRL" };
            const setText = (id, value) => {
                const el = document.getElementById(id);
                if (el) el.innerText = value;
            };
            setText('f-total', (parseFloat(resu.total) || 0).toLocaleString("pt-br", fmt));
            setText('f-30', (parseFloat(resu.d30) || 0).toLocaleString("pt-br", fmt));
            setText('f-60', (parseFloat(resu.d60) || 0).toLocaleString("pt-br", fmt));
            setText('f-90', (parseFloat(resu.d90) || 0).toLocaleString("pt-br", fmt));
            const iagVal = resu.total > 0 ? (resu.vencidos / resu.total * 100).toFixed(2) : "0.00";
            setText('kpi-iag', iagVal + "%");
            setText('kpi-iag-quick', iagVal + "%");
            setText('kpi-iap', (resu.total > 0 ? (resu.valor_iap / resu.total * 100).toFixed(2) : "0.00") + "%");
            setText('kpi-recup', (res.taxa_recup || 0) + "%");
            setText('kpi-iag-valor', (parseFloat(resu.vencidos) || 0).toLocaleString("pt-br", fmt));
            setText('kpi-iap-valor', (parseFloat(resu.valor_iap) || 0).toLocaleString("pt-br", fmt));
            if (res.recup_detalhe) {
                setText('kpi-recup-quant', `${res.recup_detalhe.pagos} de ${res.recup_detalhe.total} títulos`);
            }
            this.renderTable(res.tabela || []);
        } catch (e) {
            console.error("Erro no load:", e);
        } finally {
            if (shimmer) shimmer.style.display = "none";
        }
    },

renderTable(dados) {
    const head = document.getElementById("t-head");
    const body = document.getElementById("t-body");
    if (!head || !body) return;
    body.innerHTML = "";

    if (this.nivel === "cliente") {
        head.innerHTML = `<tr>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">Cliente</th>
            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Total Vencido</th>
            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">IAG</th>
            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
        </table>`;
        dados.forEach(i => {
            let perfColor = "#10b981";
            if (i.performance === 'CRÍTICO') perfColor = "#ef4444";
            else if (i.performance === 'ATENÇÃO') perfColor = "#f59e0b";
            const tr = document.createElement("tr");
            tr.className = "border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-all";
            tr.onclick = () => this.showClientTitles(i.id, i.nome);
            tr.innerHTML = `
                <td class="px-4 py-3"><b>${i.nome}</b><br><small class="text-slate-400">ID: ${i.id}</small></td>
                <td class="px-4 py-3 text-right font-semibold">R$ ${parseFloat(i.vencidos).toLocaleString("pt-br", { minimumFractionDigits: 2 })}</td>
                <td class="px-4 py-3 text-center"><b>${i.iag}%</b></td>
                <td class="px-4 py-3 text-center"><span style="color:${perfColor}; border:1px solid ${perfColor}; padding:4px 8px; border-radius:6px; font-weight:700;">${i.performance}</span></td>
            `;
            body.appendChild(tr);
        });
    } else {
               let titulo = "";
        if (this.nivel === "filial") {
            titulo = "Unidade";
        } else if (this.nivel === "gestor") {
            if (this.f_id === 1 || this.f_id === null) {
                titulo = "Gestor";       
            } else {
                titulo = "Representante"; 
            }
        } else {
            titulo = "Item";
        }
        head.innerHTML = `<tr>
            <th class="px-4 py-3 text-left text-xs font-bold text-slate-400 uppercase">${titulo}</th>
            <th class="px-4 py-3 text-right text-xs font-bold text-slate-400 uppercase">Total Vencido</th>
            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">IAG</th>
            <th class="px-4 py-3 text-center text-xs font-bold text-slate-400 uppercase">Status</th>
        </tr>`;

        dados.forEach(i => {
            let perfColor = "#10b981";
            if (i.performance === 'CRÍTICO') perfColor = "#ef4444";
            else if (i.performance === 'ATENÇÃO') perfColor = "#f59e0b";
            const tr = document.createElement("tr");
            tr.className = "border-b border-slate-100 hover:bg-slate-50 cursor-pointer transition-all";
            const idItem = i.id;
            const nomeItem = i.nome;
            tr.onclick = (function(id, nome) {
                return function() { app.drill(id, nome); };
            })(idItem, nomeItem);
            tr.innerHTML = `
                <td class="px-4 py-3"><b>${i.nome}</b><br><small class="text-slate-400">ID: ${i.id}</small></td>
                <td class="px-4 py-3 text-right font-semibold">R$ ${parseFloat(i.vencidos).toLocaleString("pt-br", { minimumFractionDigits: 2 })}</td>
                <td class="px-4 py-3 text-center"><b>${i.iag}%</b></td>
                <td class="px-4 py-3 text-center"><span style="color:${perfColor}; border:1px solid ${perfColor}; padding:4px 8px; border-radius:6px; font-weight:700;">${i.performance}</span></td>
            `;
            body.appendChild(tr);
        });
    }
},

  async showClientTitles(id_cliente, nome_cliente) {
    Swal.fire({ title: "Buscando títulos...", didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    try {
        const globalUserSelect = document.getElementById("global-user-filter");
        const currentUid = (globalUserSelect && globalUserSelect.value) ? globalUserSelect.value : this.uid;
        const idfilial = document.getElementById("select-filial")?.value || 1;
        const diasRecup = document.getElementById("filtro-dias-recup")?.value || 999;
        const payload = {
            idusuario: currentUid,
            idfilial: idfilial,
            dias_recup: diasRecup,
            nivel: "cliente",
            filtro_id: id_cliente
        };
        const res = await this.api("relatorio-detalhado", payload);
        
        if (!res || res.length === 0) {
            Swal.fire("Aviso", "Este cliente não possui títulos pendentes com saldo.", "info");
            return;
        }
        
        // 🔧 CORREÇÃO: Filtrar apenas títulos com saldo POSITIVO
        const titulosValidos = res.filter(t => parseFloat(t.valorsaldo) > 0.01);
        
        if (titulosValidos.length === 0) {
            Swal.fire("Aviso", "Este cliente não possui títulos pendentes com saldo.", "info");
            return;
        }
        
        let html = `<div style="max-height:450px; overflow-y:auto; text-align:left;">
            <table style="width:100%; font-size:0.8rem; border-collapse: collapse;">
                <thead>
                    <tr style="background:#f1f5f9; position:sticky; top:0;">
                        <th style="padding:8px; text-align:left;">Título / Venc.</th>
                        <th style="padding:8px; text-align:right;">Valor</th>
                        <th style="padding:8px; text-align:left;">Último Apontamento</th>
                    </tr>
                </thead>
                <tbody>`;
        
        for (const t of titulosValidos) {
            // 🔧 CORREÇÃO: Usar vencimento_formatado já vindo do backend
            let dataVencimento = t.vencimento_formatado || 'S/DATA';
            let atraso = parseInt(t.dias_atraso) || 0;
            let atrasoFormatado = atraso > 0 ? `<span style="color:#ef4444;">+${atraso}d</span>` : `<span style="color:#10b981;">${atraso}d</span>`;
            
            // Tratar evento
            let eventoHtml = '<i style="color:#94a3b8;">Sem registro</i>';
            if (t.evento && t.evento !== 'Sem Registro' && t.evento !== 'Sem registro') {
                eventoHtml = `<b style="color:#274036;">${t.evento}</b>`;
                if (t.ultimo_evento_formatado && t.ultimo_evento_formatado !== 'Sem Data') {
                    eventoHtml += `<br><small style="color:#64748b;">${t.ultimo_evento_formatado}</small>`;
                }
                if (t.descricao && t.descricao !== 'Sem Registro' && t.descricao !== 'Sem registro') {
                    eventoHtml += `<br><span style="color:#475569; font-size:0.7rem;">"${t.descricao.substring(0, 50)}${t.descricao.length > 50 ? '...' : ''}"</span>`;
                }
            }
            
            let valorSaldo = parseFloat(t.valorsaldo) || 0;
            
            html += `<tr style="border-bottom:1px solid #e2e8f0;">
                <td style="padding:8px;">
                    <b>${t.documento}</b><br>
                    <small style="color:#64748b;">Venc: ${dataVencimento} (${atrasoFormatado})</small>
                </td>
                <td style="padding:8px; text-align:right; font-weight:bold; color:#dc2626;">
                    ${valorSaldo.toLocaleString("pt-br", { style: 'currency', currency: 'BRL' })}
                </td>
                <td style="padding:8px;">
                    ${eventoHtml}
                 </td>
             </tr>`;
        }
        
        html += `</tbody></table></div>`;
        
        Swal.fire({
            title: `Títulos do cliente: ${nome_cliente}`,
            html: html,
            width: '850px',
            confirmButtonColor: "#274036",
            confirmButtonText: 'Fechar'
        });
    } catch (e) {
        console.error(e);
        Swal.fire("Erro", "Falha ao carregar títulos.", "error");
    }
},

    async loadHistory() {
        try {
            const type = document.getElementById("hist-type")?.value || "iag_calculado";
            const day = document.getElementById("hist-day")?.value || new Date().getDay();
            const globalUserSelect = document.getElementById("global-user-filter");
            const currentUid = (globalUserSelect && globalUserSelect.value) ? globalUserSelect.value : this.uid;
            const selectFilialGlobal = document.getElementById("select-filial");
            const alvoVisualizado = (selectFilialGlobal && selectFilialGlobal.value) ? selectFilialGlobal.value : (this.f_id || 1);
            const res = await this.api("historico-kpi", {
                idusuario: currentUid,
                tipo: type,
                filtro_id: alvoVisualizado,
                dia_semana: day
            });
            if (!res || res.length === 0) return;
            const labels = res.map(h => h.data);
            const values = res.map(h => parseFloat(h.valor) || 0);
            const labelMap = { iag_calculado: "IAG %", iap_calculado: "IAP %", taxa_recuperacao: "Recuperação %" };
            if (this.chart) this.chart.destroy();
            const canvas = document.getElementById("chartHistory");
            if (!canvas) return;
            const ctx = canvas.getContext("2d");
            const gradient = ctx.createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, "#274036");
            gradient.addColorStop(1, "#4a7a67");
            this.chart = new Chart(ctx, {
                type: "bar",
                data: { labels, datasets: [{ label: labelMap[type] || type, data: values, backgroundColor: gradient, borderRadius: 8, hoverBackgroundColor: "#f7be2f" }] },
                plugins: [{
                    id: 'numerosNoTopo',
                    afterDatasetsDraw(chart) {
                        chart.data.datasets.forEach((dataset, i) => {
                            chart.getDatasetMeta(i).data.forEach((point, idx) => {
                                const valor = dataset.data[idx];
                                ctx.font = 'bold 11px "Plus Jakarta Sans"';
                                ctx.fillStyle = '#274036';
                                ctx.textAlign = 'center';
                                ctx.textBaseline = 'bottom';
                                ctx.fillText(parseFloat(valor).toFixed(2) + '%', point.x, point.y - 8);
                            });
                        });
                    }
                }],
                options: {
                    responsive: true, maintainAspectRatio: false, layout: { padding: { top: 25 } },
                    plugins: { legend: { display: false }, tooltip: { backgroundColor: 'rgba(30,41,59,0.95)', callbacks: { label: (ctx) => `Percentual: ${ctx.raw}%` } } },
                    scales: { y: { beginAtZero: true, grid: { color: "#f1f5f9" }, ticks: { callback: (v) => v + "%" } }, x: { grid: { display: false } } }
                }
            });
        } catch (e) {
            console.error("Erro no gráfico:", e);
        }
    },

    drill(id, nome) {
        if (this.nivel === 'filial') {
            if (id === this.f_id) {
                this.nivel = 'gestor';
                this.updateBreadcrumb();
                this.load();
                return;
            }
            this.path.push({ nivel: 'gestor', f_id: id, nome: nome });
            this.nivel = 'gestor';
            this.f_id = id;
            this.updateBreadcrumb();
            this.load();
            return;
        }
        if (this.nivel === 'gestor') {
            if (this.f_id === 1 || this.f_id === null) {
                this.f_id = id;
                const idx = this.path.findIndex(p => p.nivel === 'gestor');
                if (idx !== -1) {
                    this.path[idx] = { nivel: 'gestor', f_id: id, nome: nome };
                } else {
                    this.path.push({ nivel: 'gestor', f_id: id, nome: nome });
                }
                this.updateBreadcrumb();
                this.load();
                return;
            } else {
                this.path.push({ nivel: 'cliente', f_id: id, nome: nome });
                this.nivel = 'cliente';
                this.f_id = id;
                this.updateBreadcrumb();
                this.load();
                return;
            }
        }
    },

    changeFilial(v) {
        this.f_id = v;
        this.nivel = "filial";
        const selectFilial = document.getElementById("select-filial");
        const nomeFilial = selectFilial.options[selectFilial.selectedIndex].text;
        const selectUser = document.getElementById("global-user-filter");
        const isMaster = selectUser && selectUser.style.display !== "none" && selectUser.selectedIndex > 0;
        const nomeUser = isMaster ? selectUser.options[selectUser.selectedIndex].text : "";
        const label = isMaster ? `${nomeUser} - ${nomeFilial}` : nomeFilial;
        this.path = [{ nivel: "filial", f_id: v, nome: label }];
        this.updateBreadcrumb();
        this.loadUsersList().then(() => {
            this.load();
            this.loadHistory();
        });
    },

    reset() {
        this.nivel = "filial";
        this.f_id = null;
        const selectUser = document.getElementById("global-user-filter");
        if (selectUser) selectUser.value = "";
        const selectFilial = document.getElementById("select-filial");
        if (selectFilial) selectFilial.value = "";
        this.initialized = false;
        this.path = [{ nivel: "filial", f_id: null, nome: "GLOBAL" }];
        this.updateBreadcrumb();
        this.loadUsersList().then(() => {
            this.load();
            this.loadHistory();
        });
    },

    async showAudit(tipo) {
    Swal.fire({ title: "Aguarde...", didOpen: () => Swal.showLoading() });
    try {
        const globalUserSelect = document.getElementById("global-user-filter");
        const currentUid = (globalUserSelect && globalUserSelect.value) ? globalUserSelect.value : this.uid;
        const diasRecup = document.getElementById("filtro-dias-recup")?.value || 120;
        
        const res = await this.api("detalhes-kpi", {
            tipo,
            nivel: this.nivel,
            filtro_id: this.f_id,
            idusuario: currentUid,
            dias_recup: diasRecup
        });
        
        if (!res || res.length === 0) {
            Swal.fire("Info", "Nenhum registro encontrado.", "info");
            return;
        }
        
        let titulo = tipo.toUpperCase();
        let html = `<div style="max-height:400px; overflow:auto;">
                    <table style="width:100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background:#f1f5f9; position:sticky; top:0;">
                            <th style="padding:8px; text-align:left;">Cliente / Origem</th>
                            <th style="padding:8px; text-align:right;">Valor / Qtd</th>
                        </tr>
                    </thead>
                    <tbody>`;
        
        res.forEach(d => {
            // 🔧 CORREÇÃO: Verifica se é quantidade ou valor
            let valor;
            let isQuantidade = false;
            
            if (d.is_qtd === true) {
                // Caso 1: É quantidade (cenários de recuperação)
                valor = d.valor;
                isQuantidade = true;
            } else {
                // Caso 2: É valor monetário (IAG/IAP)
                valor = `R$ ${parseFloat(d.valor).toLocaleString("pt-br", { minimumFractionDigits: 2 })}`;
            }
            
            let clickAttr = '';
            let lupaIcon = '';
            
            if (d.cenario_id) {
                clickAttr = `onclick="app.showRecupDetails(${d.cenario_id}, '${d.label.replace(/'/g, "\\'")}')" style="cursor:pointer;"`;
                lupaIcon = `<i class="fa-solid fa-magnifying-glass" style="margin-left:8px; color:#274036;"></i>`;
            }
            
            html += `<tr ${clickAttr} style="border-bottom:1px solid #e2e8f0; hover:bg-slate-50;">
                        <td style="padding:8px;">
                            <b>${d.label}</b>
                        </td>
                        <td style="padding:8px; text-align:right;">
                            <b>${valor}</b> ${lupaIcon}
                        </td>
                     </tr>`;
        });
        
        html += `</tbody></table></div>`;
        
        Swal.fire({ 
            title: `Auditoria: ${titulo}`, 
            html: html, 
            width: "600px", 
            confirmButtonColor: "#274036",
            customClass: {
                popup: 'audit-modal'
            }
        });
    } catch (e) {
        console.error("Erro detalhado:", e);
        Swal.fire("Erro", "Falha ao buscar detalhes. Verifique o console para mais informações.", "error");
    }
},

    async showRecupDetails(cenario_id, titulo) {
        Swal.fire({ title: "Buscando Títulos...", didOpen: () => Swal.showLoading() });
        try {
            const globalUserSelect = document.getElementById("global-user-filter");
            const currentUid = (globalUserSelect && globalUserSelect.value) ? globalUserSelect.value : this.uid;
            const diasRecup = document.getElementById("filtro-dias-recup")?.value || 120;
            const res = await this.api("detalhes-kpi", {
                tipo: 'recup',
                cenario: cenario_id,
                nivel: this.nivel,
                filtro_id: this.f_id,
                idusuario: currentUid,
                dias_recup
            });
            if (!res || res.length === 0) {
                Swal.fire("Info", "Nenhum título encontrado.", "info");
                return;
            }
            let html = `<div style="max-height:450px; overflow:auto;"><table style="width:100%;"><thead><tr><th>Cliente</th><th>Doc / R$</th><th>Últ. Evento</th></tr></thead><tbody>`;
            res.forEach(d => {
                let evento = d.desc_evento ? `${d.desc_evento}<br><small>(${d.data_evento})</small>` : '<span class="text-danger">Sem Apontamento</span>';
                html += `<tr><td>${d.cliente}</td><td style="text-align:center;"><b>${d.documento}</b><br>Venc: ${d.data_vencimento}<br><span class="text-muted">${(parseFloat(d.valorsaldo) || 0).toLocaleString("pt-br", { style: 'currency', currency: 'BRL' })}</span></td><td>${evento}</td></tr>`;
            });
            Swal.fire({
                title: titulo,
                html: html + `</tbody></table></div>`,
                width: "850px",
                showCancelButton: true,
                confirmButtonText: '<i class="fa-solid fa-arrow-left"></i> Voltar',
                cancelButtonText: 'Fechar'
            }).then((result) => {
                if (result.isConfirmed) this.showAudit('recup');
            });
        } catch (e) {
            Swal.fire('Erro', 'Falha ao buscar detalhes.', 'error');
        }
    },

 async gerarRelatorio() {
    const globalUserSelect = document.getElementById("global-user-filter");
    const currentUid = (globalUserSelect && globalUserSelect.value) ? globalUserSelect.value : this.uid;
    const selectFilial = document.getElementById("select-filial");
    const idfilial = selectFilial && selectFilial.value ? selectFilial.value : 1;
    const diasRecup = document.getElementById("filtro-dias-recup")?.value || 999;

    let nivel = this.nivel;
    let filtroId = this.f_id;

    if (nivel === 'filial') {
        filtroId = 0;
    }
    if (nivel === 'gestor' && (filtroId === 1 || filtroId === null)) {
        filtroId = 0;
    }

    Swal.fire({ title: 'Gerando Relatório...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    try {
        const data = await this.api("relatorio-detalhado", {
            idusuario: currentUid,
            idfilial: idfilial,
            dias_recup: diasRecup,
            nivel: nivel,
            filtro_id: filtroId || 0
        });

        Swal.close();

        if (data.error) throw new Error(data.error);
        if (!data || data.length === 0) {
            Swal.fire('Aviso', 'Nenhum título encontrado.', 'info');
            return;
        }

        let tituloRelatorio = "RELATÓRIO FINANCEIRO - TÍTULOS";
        if (nivel === 'filial') {
            const filialNome = selectFilial?.options[selectFilial.selectedIndex]?.text || 'Filial';
            tituloRelatorio = `RELATÓRIO FINANCEIRO - FILIAL: ${filialNome}`;
        } else if (nivel === 'gestor' && filtroId > 0) {
            const gestorNome = this.path.find(p => p.nivel === 'gestor')?.nome || 'Gestor';
            tituloRelatorio = `RELATÓRIO FINANCEIRO - GESTOR: ${gestorNome}`;
        } else if (nivel === 'cliente' && filtroId > 0) {
            const clienteNome = this.path.find(p => p.nivel === 'cliente')?.nome || 'Cliente';
            tituloRelatorio = `RELATÓRIO FINANCEIRO - CLIENTE: ${clienteNome}`;
        }

        const nomeUsuario = document.getElementById("user_nome")?.value || 'Sistema';
        const nomeFilial = selectFilial?.options[selectFilial.selectedIndex]?.text || 'Todas';

        // ✅ Verifique se a função gerarPDFFinanceiro está definida
        if (typeof gerarPDFFinanceiro !== 'function') {
            console.error("Função gerarPDFFinanceiro não encontrada!");
            Swal.fire('Erro', 'Função de geração de PDF não disponível.', 'error');
            return;
        }

        const doc = gerarPDFFinanceiro(data, nomeUsuario, nomeFilial, diasRecup, tituloRelatorio);
        if (doc) {
            doc.save(`relatorio_financeiro_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.pdf`);
            Swal.fire({ icon: 'success', title: 'PDF Gerado!', text: `${data.length} títulos`, timer: 3000, showConfirmButton: false, toast: true });
        } else {
            Swal.fire('Erro', 'Falha ao gerar o PDF.', 'error');
        }
    } catch (error) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Erro', text: error.message });
    }
}
};
// ==========================================================================
// FUNÇÃO PARA GERAR PDF PROFISSIONAL - VERSÃO COM LARGURAS OTIMIZADAS
// ==========================================================================
function gerarPDFFinanceiro(dados, usuario, nomeFilial, diasRecup, tituloPersonalizado = null) {
    const { jsPDF } = window.jspdf;
    if (!jsPDF) {
        Swal.fire('Erro', 'Biblioteca jsPDF não encontrada.', 'error');
        return null;
    }

    const doc = new jsPDF('l', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth(); // 297mm
    const pageHeight = doc.internal.pageSize.getHeight(); // 210mm
    const margin = 10;
    const usableWidth = pageWidth - (margin * 2); // 277mm
    
    let y = 12;
    let paginaAtual = 1;

    // ============================================================
    // FUNÇÃO PARA TEXTO EM CAIXA ALTA
    // ============================================================
    function toUpperCaseText(str) {
        if (!str || str === 'Sem Registro' || str === 'Sem Data') return 'S/REG';
        if (str === 'Sem Registro') return 'S/REG';
        return String(str).toUpperCase();
    }

    // ============================================================
    // FUNÇÃO PARA PRIMEIRA LETRA MAIÚSCULA
    // ============================================================
    function capitalizeFirstLetter(str) {
        if (!str || str === 'Sem Registro') return 'S/REG';
        if (str === 'S/REG') return 'S/REG';
        return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
    }

    // ============================================================
    // FUNÇÃO PARA TRUNCAR TEXTO
    // ============================================================
    function truncarTexto(texto, maxLen = 50) {
        if (!texto || texto === 'Sem Registro') return 'S/REG';
        if (texto === 'S/REG') return 'S/REG';
        if (texto.length <= maxLen) return texto;
        return texto.substring(0, maxLen) + '...';
    }

    // ============================================================
    // EXTRAI GESTORES ÚNICOS
    // ============================================================
    const gestoresUnicos = [...new Set(dados.map(item => item.gestor).filter(g => g))];
    const gestoresTexto = gestoresUnicos.length > 0 
        ? gestoresUnicos.join(', ')
        : 'Não informado';

    // ============================================================
    // CALCULA TOTAIS
    // ============================================================
    const totalValor = dados[0]?.total_geral_valor || dados.reduce((sum, i) => sum + (parseFloat(i.valor) || 0), 0);
    const totalRecebido = dados[0]?.total_geral_recebido || dados.reduce((sum, i) => sum + (parseFloat(i.totalrecebido) || 0), 0);
    const totalSaldo = dados[0]?.total_geral_saldo || dados.reduce((sum, i) => sum + (parseFloat(i.valorsaldo) || 0), 0);

    // ============================================================
    // DEFINIÇÃO DAS COLUNAS - LARGURAS OTIMIZADAS
    // ============================================================
    const colunas = [
        { label: "EMISSÃO", width: 16, align: "center" },        // Reduzido
        { label: "CLIENTE", width: 35, align: "left" },          // Reduzido
        { label: "DOCUMENTO", width: 22, align: "left" },        // Reduzido
        { label: "VENC", width: 14, align: "center" },           // Abreviado
        { label: "RECEBIDO", width: 18, align: "right" },        // Ajustado
        { label: "SALDO", width: 18, align: "right" },           // Ajustado
        { label: "ATR", width: 8, align: "center" },             // Abreviado
        { label: "REPRES", width: 22, align: "left" },           // Reduzido significativamente
        { label: "DT ULT", width: 14, align: "center" },         // Abreviado
        { label: "ULTIMO EVENTO", width: 50, align: "left" },    // Aumentado para dar mais espaço
        { label: "DSC", width: 10, align: "center" },            // Abreviado (Dias Sem Cobrança)
        { label: "USU", width: 14, align: "left" }               // Abreviado
    ];
    
    // Verifica se a soma das larguras não ultrapassa a largura utilizável
    const somaLarguras = colunas.reduce((sum, col) => sum + col.width, 0);
    if (somaLarguras > usableWidth) {
        console.warn(`Soma das colunas (${somaLarguras}mm) excede largura útil (${usableWidth}mm)`);
    }

    // ============================================================
    // CABEÇALHO COMPLETO
    // ============================================================
    function desenharCabecalhoCompleto(doc, pagina) {
        let yCab = 12;
        
        // LINHA 1: TÍTULO E DATA/HORA
        doc.setFont("helvetica", "bold");
        doc.setFontSize(11);
        doc.setTextColor(30, 41, 59);
        const titulo = tituloPersonalizado || "RELATÓRIO FINANCEIRO";
        doc.text(titulo, margin, yCab);
        
        doc.setFontSize(6.5);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(100, 116, 139);
        doc.text(`Gerado em: ${new Date().toLocaleString('pt-BR')}`, pageWidth - margin, yCab, { align: 'right' });
        yCab += 6.5;
        
        // LINHA 2: INFORMAÇÕES
        doc.setFontSize(7);
        doc.setTextColor(30, 41, 59);
        
        doc.setFont("helvetica", "bold");
        doc.text("Usuário:", margin, yCab);
        doc.setFont("helvetica", "normal");
        doc.text(` ${usuario}`, margin + 15, yCab);
        
        doc.setFont("helvetica", "bold");
        doc.text("Filial:", margin + 65, yCab);
        doc.setFont("helvetica", "normal");
        doc.text(` ${nomeFilial}`, margin + 75, yCab);
        
        doc.setFont("helvetica", "bold");
        doc.text("Registros:", pageWidth - margin - 30, yCab);
        doc.setFont("helvetica", "normal");
        doc.text(` ${dados.length}`, pageWidth - margin - 10, yCab, { align: 'right' });
        
        yCab += 5.5;
        
        // LINHA 3: GESTORES
        doc.setFont("helvetica", "bold");
        doc.setFontSize(6.5);
        doc.text("Gestor(es):", margin, yCab);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(71, 85, 105);
        
        const maxLarguraGestores = usableWidth - 30;
        let gestoresDisplay = gestoresTexto;
        
        if (doc.getTextWidth(gestoresDisplay) > maxLarguraGestores) {
            let textoTruncado = '';
            const gestoresArray = gestoresUnicos;
            
            for (let i = 0; i < gestoresArray.length; i++) {
                const teste = textoTruncado ? `${textoTruncado}, ${gestoresArray[i]}` : gestoresArray[i];
                if (doc.getTextWidth(teste + '...') <= maxLarguraGestores) {
                    textoTruncado = teste;
                } else {
                    const restantes = gestoresArray.length - i;
                    textoTruncado += ` +${restantes} mais`;
                    break;
                }
            }
            gestoresDisplay = textoTruncado || gestoresArray[0] + '...';
        }
        
        doc.text(` ${gestoresDisplay}`, margin + 22, yCab);
        
        doc.setTextColor(100, 116, 139);
        doc.text(`Pág. ${pagina}`, pageWidth - margin, yCab, { align: 'right' });
        
        yCab += 7;
        
        // CARDS DE TOTAIS (mais compactos)
        const cardY = yCab;
        const cardHeight = 7.5;
        const espacoCards = 2;
        const cardWidth = (usableWidth - (espacoCards * 2)) / 3;
        
        doc.setFillColor(30, 41, 59);
        doc.roundedRect(margin, cardY, cardWidth, cardHeight, 1.5, 1.5, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(5);
        doc.setFont("helvetica", "normal");
        doc.text("TOTAL GERAL", margin + 2, cardY + 3);
        doc.setFontSize(6);
        doc.setFont("helvetica", "bold");
        doc.text(`R$ ${parseFloat(totalValor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`, margin + 2, cardY + 6.5);
        
        doc.setFillColor(5, 150, 105);
        doc.roundedRect(margin + cardWidth + espacoCards, cardY, cardWidth, cardHeight, 1.5, 1.5, 'F');
        doc.setFontSize(5);
        doc.setFont("helvetica", "normal");
        doc.text("TOTAL RECEBIDO", margin + cardWidth + espacoCards + 2, cardY + 3);
        doc.setFontSize(6);
        doc.setFont("helvetica", "bold");
        doc.text(`R$ ${parseFloat(totalRecebido).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`, margin + cardWidth + espacoCards + 2, cardY + 6.5);
        
        doc.setFillColor(185, 28, 28);
        doc.roundedRect(margin + (cardWidth + espacoCards) * 2, cardY, cardWidth, cardHeight, 1.5, 1.5, 'F');
        doc.setFontSize(5);
        doc.setFont("helvetica", "normal");
        doc.text("SALDO DEVEDOR", margin + (cardWidth + espacoCards) * 2 + 2, cardY + 3);
        doc.setFontSize(6);
        doc.setFont("helvetica", "bold");
        doc.text(`R$ ${parseFloat(totalSaldo).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}`, margin + (cardWidth + espacoCards) * 2 + 2, cardY + 6.5);
        
        yCab += cardHeight + 5;
        
        return yCab;
    }

    // ============================================================
    // CABEÇALHO DA TABELA
    // ============================================================
    function desenharCabecalhoTabela(doc, y) {
        doc.setFillColor(30, 41, 59);
        doc.rect(margin, y, usableWidth, 6.5, 'F');
        
        doc.setDrawColor(51, 65, 85);
        doc.setLineWidth(0.2);
        let x = margin;
        colunas.forEach(col => {
            doc.line(x, y, x, y + 6.5);
            x += col.width;
        });
        doc.line(pageWidth - margin, y, pageWidth - margin, y + 6.5);
        
        doc.setTextColor(255, 255, 255);
        doc.setFont("helvetica", "bold");
        doc.setFontSize(4.8);
        
        x = margin;
        colunas.forEach(col => {
            if (col.align === "center") {
                doc.text(col.label, x + col.width / 2, y + 4.2, { align: 'center' });
            } else if (col.align === "right") {
                doc.text(col.label, x + col.width - 1.5, y + 4.2, { align: 'right' });
            } else {
                doc.text(col.label, x + 1.5, y + 4.2);
            }
            x += col.width;
        });
        
        doc.setDrawColor(51, 65, 85);
        doc.setLineWidth(0.3);
        doc.line(margin, y + 6.5, pageWidth - margin, y + 6.5);
        
        return y + 7;
    }

    // ============================================================
    // FUNÇÕES AUXILIARES
    // ============================================================
    function formatarData(data) {
        if (!data || data === 'Sem Data') return 'S/DATA';
        try {
            if (typeof data === 'string') {
                if (data.includes('/')) return data.substring(0, 10);
                const partes = data.split('-');
                if (partes.length === 3) return `${partes[2]}/${partes[1]}/${partes[0]}`;
            }
            const date = new Date(data);
            if (!isNaN(date.getTime())) {
                return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
            }
        } catch (e) {
            return 'S/DATA';
        }
        return 'S/DATA';
    }

    // ============================================================
    // DESENHA PRIMEIRA PÁGINA
    // ============================================================
    y = desenharCabecalhoCompleto(doc, paginaAtual);
    y = desenharCabecalhoTabela(doc, y);
    doc.setTextColor(0, 0, 0);
    doc.setFont("helvetica", "normal");

    // ============================================================
    // ALTURA DA LINHA
    // ============================================================
    const alturaLinhaPadrao = 5.2;
    
    // ============================================================
    // LOOP PRINCIPAL
    // ============================================================
    for (let idx = 0; idx < dados.length; idx++) {
        const item = dados[idx];
        
        // EXTRAI APENAS O REPRESENTANTE (sem o gestor)
        let representante = item.nome_vendedor || '';
        if (representante && representante.includes(' / ')) {
            representante = representante.split(' / ')[1] || representante;
        }
        // Limita o tamanho do nome do representante
        if (representante.length > 20) {
            representante = representante.substring(0, 18) + '..';
        }
        representante = toUpperCaseText(representante);
        
        // TRATA O ÚLTIMO EVENTO
        let ultimoEvento = item.descricao || 'Sem Registro';
        ultimoEvento = truncarTexto(ultimoEvento, 55);
        ultimoEvento = capitalizeFirstLetter(ultimoEvento);
        
        // DEMAIS CAMPOS
        let clienteNome = toUpperCaseText(item.nomefantasia || '');
        // Limita nome do cliente
        if (clienteNome.length > 30) {
            clienteNome = clienteNome.substring(0, 28) + '..';
        }
        
        let documento = toUpperCaseText(item.documento || '-');
        let metodoPagto = item.metodopagto ? ` ${toUpperCaseText(item.metodopagto)}` : '';
        let usuarioTexto = toUpperCaseText(item.usuario || 'Sem Registro');
        if (usuarioTexto === 'SEM REGISTRO') usuarioTexto = 'S/REG';
        
        let ultEventoDias = item.ult_evento_dias || 'Sem Registro';
        if (ultEventoDias === 'Sem Registro') ultEventoDias = 'S/REG';
        
        // Prepara os textos
        const textos = [
            formatarData(item.dataemissao),
            `${item.idcliforemp || ''} ${clienteNome}`,
            `${documento}${metodoPagto}`,
            formatarData(item.vencimento),
            (parseFloat(item.totalrecebido) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            (parseFloat(item.valorsaldo) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            `${parseInt(item.dias_atraso) || 0}`,
            representante || 'S/REPRES',
            formatarData(item.ultimo_evento),
            ultimoEvento,
            ultEventoDias,
            usuarioTexto
        ];
        
        // VERIFICA QUEBRA DE PÁGINA
        if (alturaLinhaPadrao > pageHeight - 18 - y) {
            doc.addPage();
            paginaAtual++;
            y = 12;
            y = desenharCabecalhoCompleto(doc, paginaAtual);
            y = desenharCabecalhoTabela(doc, y);
            doc.setTextColor(0, 0, 0);
            doc.setFont("helvetica", "normal");
        }
        
        // FUNDO ZEBRADO
        if (idx % 2 === 0) {
            doc.setFillColor(248, 250, 252);
        } else {
            doc.setFillColor(255, 255, 255);
        }
        doc.rect(margin, y, usableWidth, alturaLinhaPadrao, 'F');
        
        // LINHAS DE GRADE
        doc.setDrawColor(226, 232, 240);
        doc.setLineWidth(0.1);
        doc.line(margin, y, pageWidth - margin, y);
        doc.line(margin, y + alturaLinhaPadrao, pageWidth - margin, y + alturaLinhaPadrao);
        
        let xGrid = margin;
        colunas.forEach(col => {
            doc.line(xGrid, y, xGrid, y + alturaLinhaPadrao);
            xGrid += col.width;
        });
        doc.line(pageWidth - margin, y, pageWidth - margin, y + alturaLinhaPadrao);
        
        // RENDERIZA COLUNAS
        let x = margin;
        const yBase = y + 3.3;
        
        // Fonte padrão
        doc.setFontSize(4.8);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(30, 41, 59);
        
        // 1. EMISSÃO
        doc.text(textos[0], x + colunas[0].width / 2, yBase, { align: 'center' });
        x += colunas[0].width;
        
        // 2. CLIENTE
        let clienteTexto = textos[1];
        if (doc.getTextWidth(clienteTexto) > colunas[1].width - 2) {
            doc.setFontSize(4.5);
        }
        doc.text(clienteTexto, x + 1, yBase);
        doc.setFontSize(4.8);
        x += colunas[1].width;
        
        // 3. DOCUMENTO
        doc.text(textos[2], x + 1, yBase);
        x += colunas[2].width;
        
        // 4. VENCIMENTO
        doc.text(textos[3], x + colunas[3].width / 2, yBase, { align: 'center' });
        x += colunas[3].width;
        
        // 5. RECEBIDO
        const recebido = parseFloat(item.totalrecebido) || 0;
        if (recebido > 0) doc.setTextColor(5, 150, 105);
        doc.text(textos[4], x + colunas[4].width - 1, yBase, { align: 'right' });
        doc.setTextColor(30, 41, 59);
        x += colunas[4].width;
        
        // 6. SALDO
        const saldo = parseFloat(item.valorsaldo) || 0;
        if (saldo > 0) {
            doc.setTextColor(185, 28, 28);
            doc.setFont("helvetica", "bold");
        }
        doc.text(textos[5], x + colunas[5].width - 1, yBase, { align: 'right' });
        doc.setTextColor(30, 41, 59);
        doc.setFont("helvetica", "normal");
        x += colunas[5].width;
        
        // 7. ATRASO
        const atraso = parseInt(item.dias_atraso) || 0;
        if (atraso > 30) {
            doc.setTextColor(185, 28, 28);
            doc.setFont("helvetica", "bold");
        } else if (atraso > 0) {
            doc.setTextColor(245, 158, 11);
        } else if (atraso === 0) {
            doc.setTextColor(5, 150, 105);
        }
        doc.text(textos[6], x + colunas[6].width / 2, yBase, { align: 'center' });
        doc.setTextColor(30, 41, 59);
        doc.setFont("helvetica", "normal");
        x += colunas[6].width;
        
        // 8. REPRESENTANTE (agora com largura adequada)
        let repTexto = textos[7];
        if (doc.getTextWidth(repTexto) > colunas[7].width - 2) {
            doc.setFontSize(4.2);
        }
        doc.text(repTexto, x + 1, yBase);
        doc.setFontSize(4.8);
        x += colunas[7].width;
        
        // 9. DATA ÚLTIMO EVENTO
        if (textos[8] === 'S/DATA') {
            doc.setTextColor(148, 163, 184);
            doc.setFont("helvetica", "italic");
        }
        doc.text(textos[8], x + colunas[8].width / 2, yBase, { align: 'center' });
        doc.setTextColor(30, 41, 59);
        doc.setFont("helvetica", "normal");
        x += colunas[8].width;
        
        // 10. ÚLTIMO EVENTO (fonte menor)
        doc.setFontSize(4.2);
        if (textos[9] === 'S/REG') {
            doc.setTextColor(148, 163, 184);
            doc.setFont("helvetica", "italic");
        }
        
        let eventoTexto = textos[9];
        const larguraEvento = colunas[9].width - 2;
        if (doc.getTextWidth(eventoTexto) > larguraEvento) {
            doc.setFontSize(3.8);
            if (doc.getTextWidth(eventoTexto) > larguraEvento) {
                doc.setFontSize(3.5);
            }
        }
        doc.text(eventoTexto, x + 1, yBase);
        
        // Restaura fonte
        doc.setFontSize(4.8);
        doc.setTextColor(30, 41, 59);
        doc.setFont("helvetica", "normal");
        x += colunas[9].width;
        
        // 11. DIAS SEM COBRANÇA
        if (textos[10] === 'S/REG') {
            doc.setTextColor(148, 163, 184);
            doc.setFont("helvetica", "italic");
        } else {
            const diasNum = parseInt(textos[10]);
            if (!isNaN(diasNum) && diasNum > 30) {
                doc.setTextColor(185, 28, 28);
                doc.setFont("helvetica", "bold");
            }
        }
        doc.text(textos[10], x + colunas[10].width / 2, yBase, { align: 'center' });
        doc.setTextColor(30, 41, 59);
        doc.setFont("helvetica", "normal");
        x += colunas[10].width;
        
        // 12. USUÁRIO
        if (textos[11] === 'S/REG') {
            doc.setTextColor(148, 163, 184);
            doc.setFont("helvetica", "italic");
        }
        doc.text(textos[11], x + 1, yBase);
        doc.setTextColor(30, 41, 59);
        doc.setFont("helvetica", "normal");
        
        y += alturaLinhaPadrao;
    }

    // ============================================================
    // RODAPÉ
    // ============================================================
    const totalPaginas = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPaginas; i++) {
        doc.setPage(i);
        
        doc.setDrawColor(39, 64, 54);
        doc.setLineWidth(0.3);
        doc.line(margin, pageHeight - 9, pageWidth - margin, pageHeight - 9);
        
        doc.setFontSize(5);
        doc.setTextColor(148, 163, 184);
        doc.setFont("helvetica", "normal");
        doc.text(`Nutricional - ${new Date().toLocaleDateString('pt-BR')}`, margin, pageHeight - 5.5);
        doc.text(`Pág. ${i}/${totalPaginas}`, pageWidth - margin, pageHeight - 5.5, { align: 'right' });
    }
    
    return doc;
}

// ==========================================================================
// CONFIGURAÇÃO SWEETALERT2
// ==========================================================================
const originalFire = Swal.fire;
Swal.fire = function (...args) {
    if (typeof args[0] === 'object' && args[0] !== null) {
        if (args[0].width === '800px') args[0].position = 'center';
        else args[0].position = 'top';
    } else {
        return originalFire.call(this, { title: args[0], text: args[1], icon: args[2], position: 'top' });
    }
    return originalFire.apply(this, args);
};

window.addEventListener('DOMContentLoaded', () => {
    app.init();
});
window.app = app;