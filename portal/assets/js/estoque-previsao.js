// ==========================================================================
// MÓDULO ESTOQUE COM PREVISÃO - COMPLETO
// ==========================================================================

const estoquePrevisaoApp = {
    uid: (() => {
        try {
            const userData = JSON.parse(localStorage.getItem('userData') || '{}');
            return userData.idusuario || userData.uid || 5166;
        } catch {
            return 5166;
        }
    })(),
    
    currentFilial: null,
    currentPage: 1,
    currentOrder: 'produto',
    currentOrderDir: 'ASC',
    totalPages: 0,
    chart: null,
    
    // ==========================================================================
    // FUNÇÃO PARA FORMATAR URL DA IMAGEM (mesma lógica da separação)
    // ==========================================================================
    formatarUrlImagem(path_foto_master) {
        if (!path_foto_master) return null;
        
        // Extrai a parte após "Fotos para o Site\"
        const imgPath = path_foto_master.split('Fotos para o Site\\')[1];
        if (!imgPath) return null;
        
        // Substitui espaços por %20 e concatena com a URL base
        return 'https://acesso.nutricionalbr.com:2053/fotos/' + imgPath.replace(/ /g, '%20');
    },
    
    async init() {
        console.log('Inicializando módulo Estoque com Previsão...');
        await this.carregarFiliais();
        this.bindEvents();
        this.iniciarRelogio();
    },
    
    getToken() {
        return localStorage.getItem('authToken');
    },
    
    async fetchWithAuth(url, options = {}) {
        const token = this.getToken();
        if (!token) {
            window.location.href = '/portal/login.php';
            throw new Error('Token não encontrado');
        }
        
        const defaultOptions = {
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            }
        };
        
        const mergedOptions = {
            ...defaultOptions,
            ...options,
            headers: {
                ...defaultOptions.headers,
                ...(options.headers || {})
            }
        };
        
        const response = await fetch(url, mergedOptions);
        
        if (response.status === 401) {
            localStorage.removeItem('authToken');
            localStorage.removeItem('userData');
            window.location.href = '/portal/login.php';
            throw new Error('Sessão expirada');
        }
        
        return response;
    },
    
    async api(endpoint, body = null, method = 'POST') {
        const url = `${API_URL}/estoque-previsao/${endpoint}`;
        
        if (method === 'GET') {
            const resp = await this.fetchWithAuth(url, { method: 'GET' });
            return resp.json();
        } else {
            const resp = await this.fetchWithAuth(url, {
                method: 'POST',
                body: JSON.stringify(body)
            });
            return resp.json();
        }
    },
    
async carregarFiliais() {
    try {
        const response = await this.api('resumo', { 
            idusuario: this.uid,
            filtro_id: 0,
            nivel: 'filial'
        });
        
        const select = document.getElementById('select-filial');
        if (select && response.config?.filiais && response.config.filiais.length > 0) {
            select.innerHTML = '';
            response.config.filiais.forEach(f => {
                const opt = document.createElement('option');
                opt.value = f.idfilial;
                opt.innerText = f.nome;
                select.appendChild(opt);
            });
            
            if (response.config.filial_padrao) {
                select.value = response.config.filial_padrao;
                this.currentFilial = response.config.filial_padrao;
            } else if (response.config.filiais[0]) {
                select.value = response.config.filiais[0].idfilial;
                this.currentFilial = response.config.filiais[0].idfilial;
            }
            
            const userDisplay = document.getElementById('user-display');
            if (userDisplay) {
                userDisplay.innerText = response.config?.usuario || 'USUÁRIO';
            }
        }
        
        // Se ainda não tem currentFilial, define um padrão
        if (!this.currentFilial) {
            this.currentFilial = 1;
        }
        
        // Agora carrega os dados que dependem da filial
        await this.carregarResumo();
        await this.carregarMarcas();
        await this.carregarProdutos();
        
    } catch (e) {
        console.error('Erro ao carregar filiais:', e);
        // Fallback: define filial padrão
        this.currentFilial = 1;
        await this.carregarResumo();
        await this.carregarMarcas();
        await this.carregarProdutos();
    }
},
    
    async carregarMarcas() {
        try {
            const url = `${API_URL}/estoque-previsao/marcas?idusuario=${this.uid}&idfilial=${this.currentFilial}`;
            const resp = await this.fetchWithAuth(url, { method: 'GET' });
            const data = await resp.json();
            
            if (data.success && data.data) {
                const select = document.getElementById('filter-marca');
                if (select) {
                    select.innerHTML = '<option value="">Todas as marcas</option>';
                    data.data.forEach(marca => {
                        const opt = document.createElement('option');
                        opt.value = marca.nome;
                        opt.innerText = marca.nome;
                        select.appendChild(opt);
                    });
                }
            }
        } catch (e) {
            console.error('Erro ao carregar marcas:', e);
        }
    },
    
    async carregarResumo() {
        if (!this.currentFilial) return;
        
        try {
            const resumo = await this.api('resumo', {
                idusuario: this.uid,
                filtro_id: parseInt(this.currentFilial),
                nivel: 'filial'
            });
            
            if (resumo.success && resumo.data) {
                const d = resumo.data;
                this.atualizarElemento('total-estoque', this.formatNumber(d.total_estoque));
                this.atualizarElemento('total-reservado', this.formatNumber(d.total_reservado));
                this.atualizarElemento('total-previsao', this.formatNumber(d.total_previsao));
                this.atualizarElemento('total-futuro', this.formatNumber(d.total_futuro));
                this.atualizarElemento('total-zerados', this.formatNumber(d.produtos_zerados));
                
                this.atualizarGraficoResumo(d);
            }
        } catch (e) {
            console.error('Erro ao carregar resumo:', e);
        }
    },
    
    atualizarGraficoResumo(dados) {
        const canvas = document.getElementById('chartResumo');
        if (!canvas) return;
        
        const ctx = canvas.getContext('2d');
        
        if (this.chart) {
            this.chart.destroy();
        }
        
        this.chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Estoque Disponível', 'Reservado', 'Previsão', 'Zerados'],
                datasets: [{
                    data: [
                        dados.total_estoque || 0,
                        dados.total_reservado || 0,
                        dados.total_previsao || 0,
                        dados.produtos_zerados || 0
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#ef4444'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                return `${label}: ${this.formatNumber(value)}`;
                            }
                        }
                    }
                }
            }
        });
    },
    
    async carregarProdutos() {
        if (!this.currentFilial) return;
        
        const shimmer = document.getElementById('shimmer');
        if (shimmer) shimmer.style.display = 'block';
        
        try {
            const search = document.getElementById('search-produto')?.value || '';
            const marca = document.getElementById('filter-marca')?.value || '';
            const status = document.getElementById('filter-status')?.value || '';
            
            const body = {
                idusuario: this.uid,
                filtro_id: parseInt(this.currentFilial),
                nivel: 'filial',
                page: this.currentPage,
                limit: 20,
                search: search,
                marca: marca,
                status: status,
                order_by: this.currentOrder,
                order_dir: this.currentOrderDir
            };
            
            const resp = await this.api('produtos', body);
            
            if (resp.success) {
                this.renderTable(resp.data || []);
                this.renderPagination(resp.pagination);
            } else {
                this.renderTable([]);
                this.mostrarErro(resp.error || 'Erro ao carregar produtos');
            }
        } catch (e) {
            console.error('Erro ao carregar produtos:', e);
            this.mostrarErro('Erro ao carregar produtos: ' + e.message);
        } finally {
            if (shimmer) shimmer.style.display = 'none';
        }
    },
    
    renderTable(produtos) {
        const tbody = document.getElementById('table-body');
        
        if (!tbody) return;
        
        if (!produtos || produtos.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-12 text-slate-400">Nenhum produto encontrado</td</tr>';
            return;
        }
        
        tbody.innerHTML = produtos.map(p => {
            const estoque = p['Estoque Disponível'] || 0;
            // Usa a mesma lógica de imagem da separação
            const fotoUrl = this.formatarUrlImagem(p['Foto']);
            
            let statusClass = '', statusText = '';
            
            if (estoque <= 0) {
                statusClass = 'bg-rose-100 text-rose-700';
                statusText = 'Zerado';
            } else if (estoque <= 10) {
                statusClass = 'bg-rose-100 text-rose-700';
                statusText = 'Crítico';
            } else if (estoque <= 50) {
                statusClass = 'bg-amber-100 text-amber-700';
                statusText = 'Baixo';
            } else {
                statusClass = 'bg-emerald-100 text-emerald-700';
                statusText = 'Ok';
            }
            
            return `
                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors cursor-pointer" onclick="estoquePrevisaoApp.abrirModalDetalhe(${p['ID Item']})">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            ${fotoUrl ? `<img src="${fotoUrl}" class="w-10 h-10 rounded-lg object-cover" onerror="this.style.display='none'">` : '<div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center"><i class="fa-solid fa-box text-slate-400"></i></div>'}
                            <div>
                                <div class="font-medium text-slate-800">${this.escapeHtml(p['Produto'] || '-')}</div>
                                <div class="text-xs text-slate-400">Ref: ${this.escapeHtml(p['Referência'] || '-')}</div>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-slate-600">${this.escapeHtml(p['Marca'] || '-')}</td>
                    <td class="px-4 py-3 text-right font-bold ${estoque <= 10 ? 'text-rose-600' : 'text-slate-700'}">
                        ${this.formatNumber(estoque)}
                    </td>
                    <td class="px-4 py-3 text-right text-amber-600">${this.formatNumber(p['Quantidade em Carteira'] || 0)}</td>
                    <td class="px-4 py-3 text-right font-semibold">${this.formatNumber(p['Estoque Líquido (s/ Previsão)'] || 0)}</td>
                    <td class="px-4 py-3 text-right text-blue-600">${this.formatNumber(p['Previsão de Compra Total'] || 0)}</td>
                    <td class="px-4 py-3 text-right font-bold text-purple-600">${this.formatNumber(p['Estoque Líquido (c/ Previsão)'] || 0)}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span>
                    </td>
                </tr>
            `;
        }).join('');
    },
    
    renderPagination(pagination) {
        const info = document.getElementById('pagination-info');
        const buttons = document.getElementById('pagination-buttons');
        
        if (!pagination || !info || !buttons) return;
        
        this.totalPages = pagination.pages || 0;
        
        const inicio = ((pagination.page - 1) * pagination.limit) + 1;
        const fim = Math.min(pagination.page * pagination.limit, pagination.total);
        
        info.innerText = `Mostrando ${inicio} a ${fim} de ${pagination.total} produtos`;
        
        let html = '';
        if (pagination.page > 1) {
            html += `<button onclick="estoquePrevisaoApp.goToPage(${pagination.page - 1})" class="px-3 py-1 border rounded-lg text-sm hover:bg-slate-50">Anterior</button>`;
        }
        html += `<span class="px-3 py-1 text-sm">Pág. ${pagination.page} de ${pagination.pages}</span>`;
        if (pagination.page < pagination.pages) {
            html += `<button onclick="estoquePrevisaoApp.goToPage(${pagination.page + 1})" class="px-3 py-1 border rounded-lg text-sm hover:bg-slate-50">Próxima</button>`;
        }
        buttons.innerHTML = html;
    },
    
    goToPage(page) {
        this.currentPage = page;
        this.carregarProdutos();
    },
    
    async abrirModalDetalhe(iditem) {
        Swal.fire({
            title: 'Carregando...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });
        
        try {
            const response = await this.fetchWithAuth(`${API_URL}/estoque-previsao/item/${iditem}?idusuario=${this.uid}&idfilial=${this.currentFilial}`, { method: 'GET' });
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Erro ao carregar detalhes');
            }
            
            const item = data.data.item;
            const previsoes = data.data.previsoes || [];
            const historico = data.data.historico || [];
            
            // Usa a mesma lógica de imagem da separação
            const fotoUrl = this.formatarUrlImagem(item.foto);
            
            // Montar tabela de previsões
            let htmlPrevisoes = '<div class="max-h-64 overflow-y-auto">';
            if (previsoes.length === 0) {
                htmlPrevisoes += '<p class="text-center text-slate-400 py-4">Nenhuma previsão de compra encontrada</p>';
            } else {
                htmlPrevisoes += `
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-2 py-1 text-left">OC</th>
                                <th class="px-2 py-1 text-left">Fornecedor</th>
                                <th class="px-2 py-1 text-right">Qtd</th>
                                <th class="px-2 py-1 text-right">Previsão</th>
                                <th class="px-2 py-1 text-center">Dias</th>
                            </tr>
                        </thead>
                        <tbody>
                `;
                previsoes.forEach(p => {
                    const dias = p['Dias para Chegada'] || 0;
                    const corDias = dias <= 7 ? 'text-rose-600 font-bold' : (dias <= 15 ? 'text-amber-600' : 'text-emerald-600');
                    htmlPrevisoes += `
                        <tr class="border-b">
                            <td class="px-2 py-2">${p['Número OC']}</td>
                            <td class="px-2 py-2 text-left">${this.escapeHtml(p['Fornecedor'])}</td>
                            <td class="px-2 py-2 text-right">${this.formatNumber(p['Quantidade Saldo'])}</td>
                            <td class="px-2 py-2 text-right">${p['Data Prevista'] || '-'}</td>
                            <td class="px-2 py-2 text-center ${corDias}">${dias}d</td>
                        </tr>
                    `;
                });
                htmlPrevisoes += '</tbody></table>';
            }
            htmlPrevisoes += '</div>';
            
            // Montar gráfico de histórico
            let labels = [];
            let entradas = [];
            let saidas = [];
            
            historico.forEach(h => {
                labels.push(h.data);
                entradas.push(h.entradas || 0);
                saidas.push(h.saidas || 0);
            });
            
            Swal.fire({
                title: `<div class="flex items-center gap-3"><div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center"><i class="fa-solid fa-box text-emerald-600 text-xl"></i></div><div class="text-left"><span class="text-sm text-slate-500">${item.referencia || '---'}</span><br><span class="text-base font-bold">${this.escapeHtml(item.nome)}</span></div></div>`,
                html: `
                    <div class="text-left space-y-4 max-h-[70vh] overflow-y-auto">
                        ${fotoUrl ? `<div class="flex justify-center"><img src="${fotoUrl}" class="max-w-full max-h-48 rounded-lg shadow" onerror="this.style.display='none'"></div>` : ''}
                        
                        <div class="bg-slate-50 rounded-xl p-4">
                            <div class="grid grid-cols-2 gap-3 text-sm">
                                <div><span class="font-bold text-slate-500">Marca:</span> ${item.marca || '-'}</div>
                                <div><span class="font-bold text-slate-500">Saldo Disponível:</span> <span class="text-emerald-600 font-bold">${this.formatNumber(item.saldo)}</span></div>
                                <div class="col-span-2"><span class="font-bold text-slate-500">Endereços:</span> ${item.enderecos || 'Nenhum'}</div>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-bold text-slate-700 mb-2">📊 Movimentação (últimos 30 dias)</h4>
                            <div style="height: 200px;">
                                <canvas id="graficoHistoricoModal"></canvas>
                            </div>
                        </div>
                        
                        <div>
                            <h4 class="font-bold text-slate-700 mb-2">📦 Previsões de Compra</h4>
                            ${htmlPrevisoes}
                        </div>
                    </div>
                `,
                width: '800px',
                showCloseButton: true,
                showConfirmButton: false,
                didOpen: () => {
                    if (labels.length > 0) {
                        const ctx = document.getElementById('graficoHistoricoModal').getContext('2d');
                        new Chart(ctx, {
                            type: 'line',
                            data: {
                                labels: labels,
                                datasets: [
                                    {
                                        label: 'Entradas',
                                        data: entradas,
                                        borderColor: '#10b981',
                                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    },
                                    {
                                        label: 'Saídas',
                                        data: saidas,
                                        borderColor: '#ef4444',
                                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                                        fill: true,
                                        tension: 0.4
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: true,
                                plugins: {
                                    legend: { position: 'top' },
                                    tooltip: {
                                        callbacks: {
                                            label: (context) => {
                                                return `${context.dataset.label}: ${this.formatNumber(context.raw)}`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    y: { beginAtZero: true, title: { display: true, text: 'Quantidade' } },
                                    x: { title: { display: true, text: 'Data' } }
                                }
                            }
                        });
                    }
                }
            });
            
        } catch (e) {
            console.error('Erro ao carregar detalhes:', e);
            Swal.fire('Erro', 'Falha ao carregar detalhes do item.', 'error');
        }
    },
    
    async exportarCSV() {
        const search = document.getElementById('search-produto')?.value || '';
        const marca = document.getElementById('filter-marca')?.value || '';
        
        Swal.fire({
            title: 'Exportando...',
            text: 'Aguarde enquanto preparamos seu arquivo',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });
        
        try {
            const body = {
                idusuario: this.uid,
                filtro_id: parseInt(this.currentFilial),
                search: search,
                marca: marca
            };
            
            const response = await this.fetchWithAuth(`${API_URL}/estoque-previsao/exportar`, {
                method: 'POST',
                body: JSON.stringify(body)
            });
            
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `estoque_${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            Swal.fire('Sucesso!', 'Arquivo exportado com sucesso.', 'success');
        } catch (e) {
            console.error('Erro ao exportar:', e);
            Swal.fire('Erro', 'Falha ao exportar o arquivo.', 'error');
        }
    },
    
    changeFilial() {
        const select = document.getElementById('select-filial');
        if (select && select.value) {
            this.currentFilial = select.value;
            this.currentPage = 1;
            this.carregarResumo();
            this.carregarMarcas();
            this.carregarProdutos();
        }
    },
    
    reset() {
        document.getElementById('search-produto').value = '';
        document.getElementById('filter-marca').value = '';
        document.getElementById('filter-status').value = '';
        this.currentPage = 1;
        this.currentOrder = 'produto';
        this.currentOrderDir = 'ASC';
        this.carregarProdutos();
    },
    
    bindEvents() {
        const selectFilial = document.getElementById('select-filial');
        if (selectFilial) {
            selectFilial.addEventListener('change', () => this.changeFilial());
        }
        
        const searchInput = document.getElementById('search-produto');
        if (searchInput) {
            searchInput.addEventListener('input', () => {
                this.currentPage = 1;
                this.carregarProdutos();
            });
        }
        
        const marcaSelect = document.getElementById('filter-marca');
        if (marcaSelect) {
            marcaSelect.addEventListener('change', () => {
                this.currentPage = 1;
                this.carregarProdutos();
            });
        }
        
        const statusSelect = document.getElementById('filter-status');
        if (statusSelect) {
            statusSelect.addEventListener('change', () => {
                this.currentPage = 1;
                this.carregarProdutos();
            });
        }
        
        const btnLimpar = document.getElementById('btn-limpar');
        if (btnLimpar) {
            btnLimpar.addEventListener('click', () => this.reset());
        }
        
        const btnExportar = document.getElementById('btn-exportar');
        if (btnExportar) {
            btnExportar.addEventListener('click', () => this.exportarCSV());
        }
        
        document.querySelectorAll('[data-order]').forEach(th => {
            th.addEventListener('click', () => {
                const order = th.getAttribute('data-order');
                if (this.currentOrder === order) {
                    this.currentOrderDir = this.currentOrderDir === 'ASC' ? 'DESC' : 'ASC';
                } else {
                    this.currentOrder = order;
                    this.currentOrderDir = 'ASC';
                }
                this.currentPage = 1;
                this.carregarProdutos();
            });
        });
    },
    
    atualizarElemento(id, valor) {
        const el = document.getElementById(id);
        if (el) el.innerText = valor;
    },
    
    formatNumber(value) {
        if (value === undefined || value === null) return '0';
        return Math.round(value).toLocaleString('pt-BR');
    },
    
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },
    
    mostrarErro(mensagem) {
        console.error(mensagem);
        const tbody = document.getElementById('table-body');
        if (tbody) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-12 text-rose-500">${mensagem}</td></tr>`;
        }
    },
    
    iniciarRelogio() {
        const atualizar = () => {
            const agora = new Date();
            const horaFormatada = agora.toLocaleTimeString('pt-br');
            const dataFormatada = agora.toLocaleDateString('pt-br', { weekday: 'long', day: '2-digit', month: 'long' });
            
            const relogio = document.getElementById('relogio');
            const dataTopo = document.getElementById('data-topo');
            
            if (relogio) relogio.innerText = horaFormatada;
            if (dataTopo) dataTopo.innerText = dataFormatada;
        };
        
        atualizar();
        setInterval(atualizar, 1000);
    }
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    estoquePrevisaoApp.init();
});

window.estoquePrevisaoApp = estoquePrevisaoApp;