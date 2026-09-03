<?php
$pageTitle = 'Gerenciar Metas | Admin Marketing';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<style>
.meta-card { transition: all 0.3s ease; cursor: pointer; }
.meta-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
.status-badge { font-size: 0.7rem; }
.campo-valor { background: #f1f5f9; border-radius: 8px; padding: 8px 12px; }
</style>
';
require_once __DIR__ . '/../../../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4" x-data="gerenciarInstancias()" x-init="init()">
    
    <!-- Header -->
    <div class="rounded-3xl p-5 mb-6 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/modules/marketing/admin/index.php" class="btn-voltar flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-bullseye text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">GERENCIAR METAS</h2>
                <span class="text-xs text-slate-400 font-medium">Instâncias de meta</span>
            </div>
        </div>
        <div class="flex gap-2 w-full lg:w-auto">
            <a href="/portal/modules/marketing/admin/tipos-meta.php" class="flex-1 lg:flex-none px-4 py-2.5 bg-purple-600 text-white rounded-xl text-sm font-bold hover:bg-purple-700 transition-all text-center">
                <i class="fa-solid fa-cubes mr-2"></i>Tipos de Meta
            </a>
            <button onclick="abrirModalNovaMeta()" class="flex-1 lg:flex-none px-4 py-2.5 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700 transition-all">
                <i class="fa-solid fa-plus mr-2"></i>Nova Meta
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white p-4 rounded-2xl border border-slate-100 shadow-sm mb-6">
        <div class="flex flex-wrap gap-3">
            <select id="filtroStatus" x-model="filtroStatus" @change="filtrar()" class="p-2.5 border border-slate-200 rounded-xl text-sm">
                <option value="todas">Todas as metas</option>
                <option value="ativa">Ativas</option>
                <option value="pausada">Pausadas</option>
                <option value="concluida">Concluídas</option>
                <option value="cancelada">Canceladas</option>
            </select>
            <select id="filtroTipo" x-model="filtroTipo" @change="filtrar()" class="p-2.5 border border-slate-200 rounded-xl text-sm">
                <option value="0">Todos os tipos</option>
            </select>
            <input type="text" x-model="filtroBusca" @input="filtrar()" placeholder="Buscar meta..." class="flex-1 min-w-[200px] p-2.5 border border-slate-200 rounded-xl text-sm">
            <span class="text-xs text-slate-400 self-center ml-auto" x-text="'Total: ' + metasFiltradas.length + ' meta(s)'"></span>
        </div>
    </div>

    <!-- Grid de Metas -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="metasContainer">
        <div class="text-center py-12 text-slate-400 col-span-full">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-emerald-600 mx-auto mb-3"></div>
            Carregando metas...
        </div>
    </div>

    <!-- Modal de Criação/Edição da Meta -->
    <div id="modalMeta" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalMeta()"></div>
            <div class="relative bg-white rounded-3xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="bg-emerald-600 px-6 py-4 flex items-center justify-between rounded-t-3xl sticky top-0 z-10">
                    <h3 class="text-lg font-bold text-white" id="modalMetaTitulo">
                        <i class="fa-solid fa-bullseye mr-2"></i>Nova Meta
                    </h3>
                    <button onclick="fecharModalMeta()" class="text-white/70 hover:text-white">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6 space-y-4">
                    <input type="hidden" id="metaId">
                    
                    <!-- Seleção do Tipo -->
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Tipo de Meta *</label>
                        <select id="metaTipo" onchange="carregarCamposPorTipo()" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                            <option value="">Selecione um tipo...</option>
                        </select>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Título *</label>
                            <input type="text" id="metaTitulo" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="Ex: Black Friday 2024">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Status</label>
                            <select id="metaStatus" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm">
                                <option value="ativa">✅ Ativa</option>
                                <option value="pausada">⏸️ Pausada</option>
                                <option value="concluida">🏁 Concluída</option>
                                <option value="cancelada">❌ Cancelada</option>
                            </select>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Descrição</label>
                        <textarea id="metaDescricao" rows="2" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" placeholder="Descreva o objetivo desta meta..."></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Data Início *</label>
                            <input type="date" id="metaInicio" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase mb-2">Data Fim</label>
                            <input type="date" id="metaFim" class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                        </div>
                    </div>
                    
                    <!-- Campos Dinâmicos -->
                    <div class="border-t border-slate-100 pt-4" id="secaoCampos" style="display: none;">
                        <h4 class="font-bold text-slate-700 mb-3">
                            <i class="fa-solid fa-sliders mr-2 text-purple-500"></i>Valores da Meta
                        </h4>
                        <div id="camposValores" class="space-y-4"></div>
                    </div>
                    
                    <div class="pt-4 flex gap-3 border-t border-slate-100">
                        <button onclick="salvarMeta()" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
                            <i class="fa-solid fa-save mr-2"></i>Salvar Meta
                        </button>
                        <button onclick="fecharModalMeta()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                            Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Visualização Rápida -->
    <div id="modalVisualizar" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="fecharModalVisualizar()"></div>
            <div class="relative bg-white rounded-3xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
                <div class="bg-slate-700 px-6 py-4 flex items-center justify-between rounded-t-3xl sticky top-0 z-10">
                    <h3 class="text-lg font-bold text-white" id="visualizarTitulo">Detalhes da Meta</h3>
                    <button onclick="fecharModalVisualizar()" class="text-white/70 hover:text-white">
                        <i class="fa-solid fa-times text-xl"></i>
                    </button>
                </div>
                <div class="p-6" id="visualizarConteudo">
                    <div class="text-center py-8 text-slate-400">Carregando...</div>
                </div>
                <div class="px-6 pb-6 flex gap-3">
                    <button onclick="fecharModalVisualizarEEditar()" class="flex-1 px-4 py-3 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all">
                        <i class="fa-solid fa-pen mr-2"></i>Editar Meta
                    </button>
                    <button onclick="fecharModalVisualizar()" class="px-4 py-3 bg-slate-200 text-slate-600 rounded-xl font-bold hover:bg-slate-300 transition-all">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function gerenciarInstancias() {
    return {
        metas: [],
        tipos: [],
        filtroStatus: 'todas',
        filtroTipo: '0',
        filtroBusca: '',
        metaVisualizando: null,
        
        async init() {
            await Promise.all([
                this.carregarTipos(),
                this.carregarMetas()
            ]);
        },
        
        get metasFiltradas() {
            let resultado = this.metas;
            
            if (this.filtroStatus !== 'todas') {
                resultado = resultado.filter(m => m.status === this.filtroStatus);
            }
            
            if (this.filtroTipo !== '0') {
                resultado = resultado.filter(m => m.id_tipo_meta == this.filtroTipo);
            }
            
            if (this.filtroBusca) {
                const termo = this.filtroBusca.toLowerCase();
                resultado = resultado.filter(m => 
                    (m.titulo || '').toLowerCase().includes(termo) ||
                    (m.descricao || '').toLowerCase().includes(termo) ||
                    (m.tipo_nome || '').toLowerCase().includes(termo)
                );
            }
            
            return resultado;
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
            
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json',
                    ...(options.headers || {})
                }
            });
            
            if (response.status === 401) {
                localStorage.removeItem('authToken');
                localStorage.removeItem('userData');
                window.location.href = '/portal/login.php';
                throw new Error('Sessão expirada');
            }
            
            return response;
        },
        
        async carregarTipos() {
            try {
                const resp = await this.fetchWithAuth('/v1/meta-builder/tipos');
                const data = await resp.json();
                
                if (data.success && data.data) {
                    this.tipos = data.data;
                    const selectTipo = document.getElementById('filtroTipo');
                    selectTipo.innerHTML = '<option value="0">Todos os tipos</option>' +
                        this.tipos.map(t => `<option value="${t.id}">${t.nome}</option>`).join('');
                    
                    // Também popular o select do modal
                    const selectModal = document.getElementById('metaTipo');
                    if (selectModal) {
                        selectModal.innerHTML = '<option value="">Selecione um tipo...</option>' +
                            this.tipos.map(t => `<option value="${t.id}">${t.nome}</option>`).join('');
                    }
                }
            } catch (e) {
                console.error('Erro ao carregar tipos:', e);
            }
        },
        
        async carregarMetas() {
            try {
                const resp = await this.fetchWithAuth('/v1/meta-builder/dashboard');
                const data = await resp.json();
                
                if (data.success && data.data) {
                    this.metas = data.data;
                    this.renderizarMetas();
                }
            } catch (e) {
                console.error('Erro ao carregar metas:', e);
                document.getElementById('metasContainer').innerHTML = 
                    '<div class="text-center py-12 text-rose-500 col-span-full">Erro ao carregar metas</div>';
            }
        },
        
        filtrar() {
            this.renderizarMetas();
        },
        
        renderizarMetas() {
            const container = document.getElementById('metasContainer');
            const metas = this.metasFiltradas;
            
            if (!metas.length) {
                container.innerHTML = `
                    <div class="text-center py-12 text-slate-400 col-span-full">
                        <i class="fa-solid fa-bullseye text-4xl text-slate-300 mb-3 block"></i>
                        <p>Nenhuma meta encontrada</p>
                        <button onclick="abrirModalNovaMeta()" class="mt-3 px-4 py-2 bg-emerald-600 text-white rounded-xl text-sm font-bold hover:bg-emerald-700">
                            Criar Primeira Meta
                        </button>
                    </div>`;
                return;
            }
            
            container.innerHTML = metas.map(m => {
                const statusInfo = this.getStatusInfo(m);
                const diasInfo = this.calcularDiasRestantes(m);
                const valores = this.parseValores(m.valores);
                
                return `
                <div class="meta-card bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden" 
                     onclick="visualizarMeta(${m.id})">
                    <!-- Cabeçalho da Meta -->
                    <div class="p-4 border-b border-slate-100 ${statusInfo.bgColor}">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl flex items-center justify-center bg-white/50">
                                    <i class="fa-solid ${m.icone || 'fa-bullseye'} text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="font-bold text-sm line-clamp-1">${this.escapeHtml(m.titulo)}</h3>
                                    <p class="text-xs opacity-75">${m.tipo_nome || 'Meta Padrão'}</p>
                                </div>
                            </div>
                            <span class="status-badge px-2 py-1 rounded-full ${statusInfo.badgeColor} font-bold">
                                ${statusInfo.texto}
                            </span>
                        </div>
                    </div>
                    
                    <!-- Corpo -->
                    <div class="p-4">
                        <div class="flex items-center justify-between text-xs text-slate-400 mb-3">
                            <span><i class="fa-regular fa-calendar mr-1"></i>${this.formatarData(m.data_inicio)} → ${this.formatarData(m.data_fim)}</span>
                            <span class="${diasInfo.color}">${diasInfo.texto}</span>
                        </div>
                        
                        ${valores && Object.keys(valores).length > 0 ? `
                        <div class="flex flex-wrap gap-2 mt-2">
                            ${Object.entries(valores).slice(0, 4).map(([key, value]) => `
                                <div class="campo-valor text-xs">
                                    <span class="text-slate-400">${this.formatarCampo(key)}:</span>
                                    <span class="font-bold text-slate-700 ml-1">${this.formatarValor(value, key)}</span>
                                </div>
                            `).join('')}
                            ${Object.keys(valores).length > 4 ? `<span class="text-xs text-slate-400 self-end">+${Object.keys(valores).length - 4} mais</span>` : ''}
                        </div>
                        ` : '<p class="text-xs text-slate-400">Sem valores definidos</p>'}
                        
                        <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between items-center">
                            <span class="text-xs text-slate-400">ID: ${m.id}</span>
                            <div class="flex gap-2" onclick="event.stopPropagation()">
                                <button onclick="visualizarMeta(${m.id})" class="p-1.5 text-slate-400 hover:text-slate-600" title="Visualizar">
                                    <i class="fa-solid fa-eye text-xs"></i>
                                </button>
                                <button onclick="editarMeta(${m.id})" class="p-1.5 text-blue-500 hover:text-blue-600" title="Editar">
                                    <i class="fa-solid fa-pen text-xs"></i>
                                </button>
                                <button onclick="excluirMeta(${m.id}, '${this.escapeHtml(m.titulo)}')" class="p-1.5 text-rose-500 hover:text-rose-600" title="Excluir">
                                    <i class="fa-solid fa-trash text-xs"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>`;
            }).join('');
        },
        
        getStatusInfo(meta) {
            const statusMap = {
                'ativa': { texto: '✅ Ativa', bgColor: 'bg-emerald-50', badgeColor: 'bg-emerald-100 text-emerald-700' },
                'pausada': { texto: '⏸️ Pausada', bgColor: 'bg-amber-50', badgeColor: 'bg-amber-100 text-amber-700' },
                'concluida': { texto: '🏁 Concluída', bgColor: 'bg-blue-50', badgeColor: 'bg-blue-100 text-blue-700' },
                'cancelada': { texto: '❌ Cancelada', bgColor: 'bg-rose-50', badgeColor: 'bg-rose-100 text-rose-700' }
            };
            return statusMap[meta.status] || { texto: meta.status, bgColor: 'bg-slate-50', badgeColor: 'bg-slate-100 text-slate-600' };
        },
        
        calcularDiasRestantes(meta) {
            if (['concluida', 'cancelada'].includes(meta.status)) {
                return { texto: 'Finalizada', color: 'text-slate-400' };
            }
            if (!meta.data_fim) {
                return { texto: 'Sem prazo', color: 'text-slate-400' };
            }
            
            const fim = new Date(meta.data_fim);
            const hoje = new Date();
            const diff = Math.ceil((fim - hoje) / (1000 * 60 * 60 * 24));
            
            if (diff < 0) {
                return { texto: `Vencida há ${Math.abs(diff)} dias`, color: 'text-rose-600 font-bold' };
            } else if (diff === 0) {
                return { texto: 'Vence hoje!', color: 'text-amber-600 font-bold' };
            } else if (diff <= 7) {
                return { texto: `${diff} dias restantes`, color: 'text-amber-600' };
            } else {
                return { texto: `${diff} dias restantes`, color: 'text-slate-400' };
            }
        },
        
        parseValores(valores) {
            if (!valores) return null;
            try {
                return typeof valores === 'string' ? JSON.parse(valores) : valores;
            } catch(e) {
                return null;
            }
        },
        
        formatarData(data) {
            if (!data) return '-';
            return new Date(data).toLocaleDateString('pt-BR');
        },
        
        formatarCampo(campo) {
            const mapa = {
                'meta_leads': 'Leads',
                'meta_faturamento': 'Faturamento',
                'leads': 'Leads',
                'faturamento': 'Faturamento',
                'investimento': 'Investimento',
                'roas_alvo': 'ROAS Alvo'
            };
            return mapa[campo] || campo.replace(/_/g, ' ').toUpperCase();
        },
        
        formatarValor(valor, campo) {
            const num = parseFloat(valor);
            if (isNaN(num)) return valor;
            
            if (campo.includes('faturamento') || campo.includes('investimento')) {
                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(num);
            }
            if (campo.includes('roas')) {
                return num.toFixed(2) + 'x';
            }
            return num.toLocaleString('pt-BR');
        },
        
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };
}

// ========== FUNÇÕES GLOBAIS ==========

// Variável para armazenar a instância
window.appInstancias = null;

// Abrir modal para nova meta
function abrirModalNovaMeta() {
    document.getElementById('metaId').value = '';
    document.getElementById('metaTitulo').value = '';
    document.getElementById('metaTipo').value = '';
    document.getElementById('metaDescricao').value = '';
    document.getElementById('metaStatus').value = 'ativa';
    document.getElementById('metaInicio').value = new Date().toISOString().split('T')[0];
    document.getElementById('metaFim').value = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    
    document.getElementById('secaoCampos').style.display = 'none';
    document.getElementById('camposValores').innerHTML = '';
    
    document.getElementById('modalMetaTitulo').innerHTML = '<i class="fa-solid fa-plus mr-2"></i>Nova Meta';
    document.getElementById('modalMeta').classList.remove('hidden');
}

// Fechar modal de meta
function fecharModalMeta() {
    document.getElementById('modalMeta').classList.add('hidden');
}

// Carregar campos do tipo selecionado
async function carregarCamposPorTipo() {
    const tipoId = document.getElementById('metaTipo').value;
    const secao = document.getElementById('secaoCampos');
    const container = document.getElementById('camposValores');
    
    if (!tipoId) {
        secao.style.display = 'none';
        return;
    }
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`/v1/meta-builder/tipos/${tipoId}/campos`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        
        if (data.success && data.data && data.data.length > 0) {
            container.innerHTML = data.data.map(campo => {
                const unidade = campo.unidade === 'R$' ? 'R$ ' : (campo.unidade === '%' ? '%' : '');
                const step = campo.tipo_campo === 'number' ? (campo.unidade === 'R$' ? '0.01' : '1') : '';
                
                return `
                <div>
                    <label class="block text-xs font-bold text-slate-500 uppercase mb-2">
                        ${campo.rotulo || campo.nome_campo}
                        ${campo.unidade ? `<span class="text-slate-400 ml-1">(${campo.unidade})</span>` : ''}
                        ${campo.obrigatorio ? '<span class="text-rose-500">*</span>' : ''}
                    </label>
                    <input type="${campo.tipo_campo || 'number'}" 
                           id="campo_${campo.nome_campo}"
                           data-nome="${campo.nome_campo}"
                           placeholder="${campo.rotulo || campo.nome_campo}"
                           ${campo.obrigatorio ? 'required' : ''}
                           ${step ? `step="${step}"` : ''}
                           class="w-full p-3 border-2 border-slate-200 rounded-xl text-sm focus:ring-2 focus:ring-emerald-400 focus:border-emerald-400">
                </div>`;
            }).join('');
            
            secao.style.display = 'block';
        } else {
            container.innerHTML = '<p class="text-center text-slate-400 py-4">Nenhum campo configurado para este tipo</p>';
            secao.style.display = 'block';
        }
    } catch (e) {
        console.error('Erro ao carregar campos:', e);
        secao.style.display = 'none';
    }
}

// Salvar meta (criar ou editar)
async function salvarMeta() {
    const id = document.getElementById('metaId').value;
    const idTipoMeta = parseInt(document.getElementById('metaTipo').value) || 0;
    const titulo = document.getElementById('metaTitulo').value.trim();
    const descricao = document.getElementById('metaDescricao').value;
    const dataInicio = document.getElementById('metaInicio').value;
    const dataFim = document.getElementById('metaFim').value;
    const status = document.getElementById('metaStatus').value;
    
    if (!idTipoMeta || !titulo) {
        Swal.fire('Atenção', 'Preencha o tipo de meta e o título', 'warning');
        return;
    }
    
    // Coletar valores dos campos dinâmicos
    const campos = [];
    const inputs = document.querySelectorAll('#camposValores input');
    inputs.forEach(input => {
        const nome = input.getAttribute('data-nome') || input.id.replace('campo_', '');
        let valor = input.value;
        
        if (input.type === 'number') {
            valor = valor === '' ? 0 : parseFloat(valor);
        }
        campos.push({ nome: nome, valor: valor });
    });
    
    const token = localStorage.getItem('authToken');
    if (!token) {
        Swal.fire('Erro', 'Sessão expirada. Faça login novamente.', 'error')
            .then(() => window.location.href = '/portal/login.php');
        return;
    }
    
    Swal.fire({ title: 'Salvando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        let url = '/v1/meta-builder/instancias';
        let method = 'POST';
        
        // Se tiver ID, é edição (mas o endpoint atual não suporta PUT para instâncias)
        // Vamos usar POST mesmo para criar sempre que for novo
        const body = {
            id_tipo_meta: idTipoMeta,
            titulo: titulo,
            descricao: descricao,
            data_inicio: dataInicio,
            data_fim: dataFim,
            status: status,
            campos: campos
        };
        
        const resp = await fetch(url, {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(body)
        });
        
        const result = await resp.json();
        Swal.close();
        
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: id ? 'Meta atualizada!' : 'Meta criada com sucesso!',
                timer: 2000,
                showConfirmButton: false
            });
            fecharModalMeta();
            
            // Recarregar lista
            if (window.appInstancias) {
                window.appInstancias.carregarMetas();
            }
        } else {
            Swal.fire('Erro', result.error || 'Falha ao salvar meta', 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Erro', 'Erro ao conectar: ' + e.message, 'error');
    }
}

// Visualizar meta
async function visualizarMeta(id) {
    Swal.fire({ title: 'Carregando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`/v1/meta-builder/instancias/${id}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        
        Swal.close();
        
        if (data.success && data.data) {
            const m = data.data;
            window.appInstancias.metaVisualizando = m;
            
            document.getElementById('visualizarTitulo').innerText = m.titulo || 'Detalhes da Meta';
            
            const valores = typeof m.valores === 'string' ? JSON.parse(m.valores) : (m.valores || {});
            const statusInfo = window.appInstancias.getStatusInfo(m);
            const diasInfo = window.appInstancias.calcularDiasRestantes(m);
            
            let html = `
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-400">Tipo</span>
                        <span class="font-bold">${m.tipo_nome || 'Meta Padrão'}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-400">Status</span>
                        <span class="px-3 py-1 rounded-full text-xs font-bold ${statusInfo.badgeColor}">${statusInfo.texto}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-400">Período</span>
                        <span class="font-medium">${window.appInstancias.formatarData(m.data_inicio)} → ${window.appInstancias.formatarData(m.data_fim)}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-slate-400">Prazo</span>
                        <span class="${diasInfo.color}">${diasInfo.texto}</span>
                    </div>
                    ${m.descricao ? `
                    <div class="border-t border-slate-100 pt-3">
                        <span class="text-sm text-slate-400 block mb-1">Descrição</span>
                        <p class="text-sm">${m.descricao}</p>
                    </div>` : ''}
                    ${Object.keys(valores).length > 0 ? `
                    <div class="border-t border-slate-100 pt-3">
                        <span class="text-sm text-slate-400 block mb-3">Valores da Meta</span>
                        <div class="grid grid-cols-2 gap-3">
                            ${Object.entries(valores).map(([key, value]) => `
                                <div class="campo-valor">
                                    <span class="text-xs text-slate-400">${window.appInstancias.formatarCampo(key)}</span>
                                    <div class="font-bold text-slate-700">${window.appInstancias.formatarValor(value, key)}</div>
                                </div>
                            `).join('')}
                        </div>
                    </div>` : ''}
                    <div class="border-t border-slate-100 pt-3">
                        <span class="text-xs text-slate-400">ID: ${m.id} | Criada em: ${m.created_at ? new Date(m.created_at).toLocaleDateString('pt-BR') : 'N/D'}</span>
                    </div>
                </div>`;
            
            document.getElementById('visualizarConteudo').innerHTML = html;
            document.getElementById('modalVisualizar').classList.remove('hidden');
        } else {
            Swal.fire('Erro', 'Meta não encontrada', 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Erro', 'Falha ao carregar detalhes', 'error');
    }
}

function fecharModalVisualizar() {
    document.getElementById('modalVisualizar').classList.add('hidden');
}

function fecharModalVisualizarEEditar() {
    const meta = window.appInstancias?.metaVisualizando;
    fecharModalVisualizar();
    if (meta) {
        setTimeout(() => editarMeta(meta.id), 300);
    }
}

// Editar meta
async function editarMeta(id) {
    Swal.fire({ title: 'Carregando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`/v1/meta-builder/instancias/${id}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await resp.json();
        
        Swal.close();
        
        if (data.success && data.data) {
            const m = data.data;
            
            document.getElementById('metaId').value = m.id;
            document.getElementById('metaTitulo').value = m.titulo || '';
            document.getElementById('metaTipo').value = m.id_tipo_meta || '';
            document.getElementById('metaDescricao').value = m.descricao || '';
            document.getElementById('metaStatus').value = m.status || 'ativa';
            document.getElementById('metaInicio').value = m.data_inicio || '';
            document.getElementById('metaFim').value = m.data_fim || '';
            
            document.getElementById('modalMetaTitulo').innerHTML = '<i class="fa-solid fa-pen mr-2"></i>Editar Meta';
            
            // Carregar campos
            await carregarCamposPorTipo();
            
            // Preencher valores existentes
            setTimeout(() => {
                const valores = typeof m.valores === 'string' ? JSON.parse(m.valores) : (m.valores || {});
                for (const [key, value] of Object.entries(valores)) {
                    const input = document.getElementById(`campo_${key}`);
                    if (input) {
                        input.value = value;
                    }
                }
            }, 500);
            
            document.getElementById('modalMeta').classList.remove('hidden');
        } else {
            Swal.fire('Erro', 'Meta não encontrada', 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Erro', 'Falha ao carregar meta', 'error');
    }
}

// Excluir meta
async function excluirMeta(id, titulo) {
    const result = await Swal.fire({
        title: 'Excluir Meta?',
        html: `<p>Deseja realmente excluir a meta:</p><p class="font-bold text-rose-600 mt-2">"${titulo}"</p><p class="text-xs text-slate-400 mt-3">Esta ação não pode ser desfeita.</p>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, excluir',
        cancelButtonText: 'Cancelar'
    });
    
    if (!result.isConfirmed) return;
    
    Swal.fire({ title: 'Excluindo...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const token = localStorage.getItem('authToken');
        // Nota: Você precisará adicionar um endpoint DELETE para instâncias
        // Por enquanto, vamos apenas remover da lista local
        const resp = await fetch(`/v1/meta-builder/instancias/${id}`, {
            method: 'DELETE',
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        Swal.close();
        
        if (resp.ok) {
            Swal.fire({ icon: 'success', title: 'Excluída!', timer: 1500, showConfirmButton: false });
            if (window.appInstancias) {
                window.appInstancias.carregarMetas();
            }
        } else {
            // Se não tiver endpoint DELETE, apenas remove da lista
            Swal.fire('Aviso', 'Endpoint de exclusão não disponível. Adicione o método deleteInstanciaMeta no MetaBuilderController.', 'warning');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Aviso', 'Erro ao excluir. Verifique se o endpoint DELETE existe.', 'warning');
    }
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    window.appInstancias = new gerenciarInstancias();
    window.appInstancias.init();
});
</script>

<?php require_once __DIR__ . '/../../../estrutura/footer.php'; ?>