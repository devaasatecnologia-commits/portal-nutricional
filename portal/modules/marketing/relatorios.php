<?php
$pageTitle = 'Relatórios de Marketing | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
<style>
    .rel-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 1.25rem;
        padding: 1.5rem;
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .rel-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08);
        transform: translateY(-2px);
    }
    .nav-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 0.85rem;
        font-size: 0.8rem;
        font-weight: 700;
        transition: all 0.15s ease;
        text-decoration: none;
    }
</style>
';
require_once __DIR__ . '/../../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- Hero / Header Integrado -->
    <div class="rounded-3xl p-5 sm:p-6 mb-6 flex flex-wrap justify-between items-center gap-4 shadow-sm bg-white border border-slate-100">
        <div class="flex items-center gap-3.5">
            <a href="/portal/" class="btn-voltar flex w-10 h-10 rounded-2xl items-center justify-center transition-colors no-underline bg-slate-100 hover:bg-slate-200" title="Voltar ao Portal">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-md shadow-rose-500/20 text-white text-xl">
                <i class="fa-solid fa-file-invoice"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-lg sm:text-xl font-extrabold text-slate-800 leading-tight">Central de Relatórios</h2>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-200">PDF & CSV</span>
                </div>
                <p class="text-xs text-slate-400 font-medium">Exportação executiva de dados do setor de Marketing & Vendas</p>
            </div>
        </div>
        
        <!-- Navegação Rápida entre Módulos de Marketing -->
        <div class="flex flex-wrap items-center gap-2">
            <a href="/portal/modules/marketing/dashboard.php" class="nav-pill bg-slate-100 text-slate-600 hover:bg-slate-200">
                <i class="fa-solid fa-chart-pie text-violet-600"></i>
                <span>Dashboard</span>
            </a>
            <a href="/portal/modules/marketing/metas.php" class="nav-pill bg-slate-100 text-slate-600 hover:bg-slate-200">
                <i class="fa-solid fa-bullseye text-emerald-600"></i>
                <span>Metas & Objetivos</span>
            </a>
            <a href="/portal/modules/marketing/clientes.php" class="nav-pill bg-slate-100 text-slate-600 hover:bg-slate-200">
                <i class="fa-solid fa-users text-blue-600"></i>
                <span>CRM Clientes</span>
            </a>
            <a href="/portal/modules/marketing/admin/index.php" class="nav-pill bg-purple-600 text-white hover:bg-purple-700 shadow-sm">
                <i class="fa-solid fa-sliders"></i>
                <span>Admin</span>
            </a>
        </div>
    </div>

    <!-- Cards de Relatórios -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        
        <!-- Card 1: Performance Geral (Dashboard Final) -->
        <div class="rel-card">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-rose-600 bg-rose-50 px-2.5 py-1 rounded-lg">Visão Geral</span>
                </div>
                <h3 class="font-black text-slate-800 text-base mb-1.5">Relatório de Performance</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-5">Consolidado 360° com KPIs, compradores do mês, faturamento ERP vs CRM e taxas de conversão.</p>
            </div>
            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button onclick="gerarRelatorio('dashboard', 'pdf')" class="flex-1 py-2.5 bg-gradient-to-r from-rose-600 to-pink-600 hover:from-rose-700 hover:to-pink-700 text-white rounded-xl font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Baixar PDF</span>
                </button>
                <button onclick="gerarRelatorio('dashboard', 'email')" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition cursor-pointer" title="Enviar por Email">
                    <i class="fa-solid fa-envelope"></i>
                </button>
            </div>
        </div>

        <!-- Card 2: Metas & Objetivos Concluídos/Ativos -->
        <div class="rel-card">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-bullseye"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg">Ciclos & Metas</span>
                </div>
                <h3 class="font-black text-slate-800 text-base mb-1.5">Relatório de Metas & Objetivos</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-5">Status completo de todos os ciclos: valores iniciais, metas finais alcançadas, percentual e prazos.</p>
            </div>
            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button onclick="gerarRelatorio('metas', 'pdf')" class="flex-1 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white rounded-xl font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Baixar PDF</span>
                </button>
                <button onclick="gerarRelatorio('metas', 'email')" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-bold text-xs transition cursor-pointer" title="Enviar por Email">
                    <i class="fa-solid fa-envelope"></i>
                </button>
            </div>
        </div>

        <!-- Card 3: Clientes & Base Unificada -->
        <div class="rel-card">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-violet-50 text-violet-600 rounded-2xl flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-violet-700 bg-violet-50 px-2.5 py-1 rounded-lg">Base de Clientes</span>
                </div>
                <h3 class="font-black text-slate-800 text-base mb-1.5">Relatório de Clientes</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-5">Listagem analítica com origem (CRM / ERP / AMBOS), localização, status e volume de negócios.</p>
            </div>
            <div class="flex gap-2 pt-2 border-t border-slate-100">
                <button onclick="gerarRelatorio('clientes', 'pdf')" class="flex-1 py-2.5 bg-gradient-to-r from-violet-600 to-purple-600 hover:from-violet-700 hover:to-purple-700 text-white rounded-xl font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Baixar PDF</span>
                </button>
                <button onclick="exportarCSV()" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl font-extrabold text-xs transition flex items-center gap-1 cursor-pointer" title="Exportar Planilha CSV">
                    <i class="fa-solid fa-file-csv text-emerald-600"></i>
                    <span>CSV</span>
                </button>
            </div>
        </div>

        <!-- Card 4: Comparativo Mensal -->
        <div class="rel-card">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-amber-50 text-amber-600 rounded-2xl flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-scale-balanced"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-amber-700 bg-amber-50 px-2.5 py-1 rounded-lg">Mês vs Anterior</span>
                </div>
                <h3 class="font-black text-slate-800 text-base mb-1.5">Comparativo Mensal</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-5">Análise comparativa mês atual vs mês anterior com variação percentual de leads, vendas e faturamento.</p>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <button onclick="gerarComparativo()" class="w-full py-2.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white rounded-xl font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Gerar PDF Comparativo</span>
                </button>
            </div>
        </div>

        <!-- Card 5: Pipeline & CRM -->
        <div class="rel-card">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-filter-circle-dollar"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg">Funil Comercial</span>
                </div>
                <h3 class="font-black text-slate-800 text-base mb-1.5">Relatório de Pipeline CRM</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-5">Distribuição de oportunidades em cada etapa do funil (Leads, Contato, Proposta, Fechados).</p>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <button onclick="gerarRelatorio('pipeline', 'pdf')" class="w-full py-2.5 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white rounded-xl font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-file-pdf"></i>
                    <span>Gerar PDF Pipeline</span>
                </button>
            </div>
        </div>

        <!-- Card 6: Automação por Email -->
        <div class="rel-card">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-xl shadow-xs">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-purple-700 bg-purple-50 px-2.5 py-1 rounded-lg">Agendamento</span>
                </div>
                <h3 class="font-black text-slate-800 text-base mb-1.5">Envio Automático Semanal</h3>
                <p class="text-xs text-slate-400 leading-relaxed mb-5">Configure dias da semana e destinatários para receber os relatórios consolidados por email.</p>
            </div>
            <div class="pt-2 border-t border-slate-100">
                <button onclick="configurarEmailAuto()" class="w-full py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl font-extrabold text-xs shadow-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i class="fa-solid fa-gear"></i>
                    <span>Configurar Agendamento</span>
                </button>
            </div>
        </div>

    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="/portal/assets/js/marketing-utils.js?v=<?= $version ?>"></script>

<script>
// ============================================================================
// AUXILIARES
// ============================================================================
const formatarMoeda = (valor) => {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(valor || 0));
};
const formatarNumero = (valor) => {
    return new Intl.NumberFormat('pt-BR').format(Number(valor || 0));
};

function formatarDataBR(dataStr) {
    if (!dataStr) return '—';
    const partes = String(dataStr).split('T')[0].split('-');
    if (partes.length === 3) {
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }
    return dataStr;
}

// ============================================================================
// GERAR RELATÓRIO PDF / EMAIL
// ============================================================================
async function gerarRelatorio(tipo, destino) {
    Swal.fire({ title: 'Gerando relatório...', text: 'Aguarde um instante', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const { jsPDF } = window.jspdf;
        const token = localStorage.getItem('authToken');
        const doc = new jsPDF('p', 'mm', 'a4');
        const hoje = new Date().toLocaleDateString('pt-BR');
        
        // Cabeçalho Executivo Nutricional
        doc.setFillColor(55, 90, 75);
        doc.rect(0, 0, 210, 28, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(18);
        doc.setFont('helvetica', 'bold');
        doc.text('Nutricional Distribuidora', 15, 14);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text('Módulo de Marketing & Comercial • Emissão: ' + hoje, 15, 22);
        
        let yPos = 38;
        
        // -------------------------------------------------------------
        // 1. RELATÓRIO DE PERFORMANCE (DASHBOARD FINAL CONSOLIDADO)
        // -------------------------------------------------------------
        if (tipo === 'dashboard') {
            const resp = await fetch('/v1/marketing/dashboard-final', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const data = await resp.json();
            const kpis = data.kpis || {};
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text('Relatório Consolidado de Performance', 15, yPos);
            yPos += 8;
            
            const kpiData = [
                ['Base de Clientes (Total Geral)', formatarNumero(kpis.total_clientes?.geral || 0)],
                ['Clientes Ativos ERP', formatarNumero(kpis.total_clientes?.erp || 0)],
                ['Leads / Clientes CRM', formatarNumero(kpis.total_clientes?.crm || 0)],
                ['Clientes Integrados (AMBOS)', formatarNumero(kpis.total_clientes?.ambos || 0)],
                ['Compradores do Mês (ERP)', formatarNumero(kpis.compradores_mes?.erp || 0)],
                ['Compradores do Mês (CRM)', formatarNumero(kpis.compradores_mes?.crm || 0)],
                ['Taxa de Conversão ERP', (kpis.taxas?.erp || 0) + '%'],
                ['Taxa de Conversão CRM', (kpis.taxas?.crm || 0) + '%'],
                ['Faturamento Mensal ERP', formatarMoeda(kpis.faturamento?.erp || 0)],
                ['Faturamento Mensal CRM', formatarMoeda(kpis.faturamento?.crm || 0)],
                ['Metas Ativas', String(kpis.metas?.ativas || 0)],
                ['Progresso Médio das Metas', (kpis.metas?.progresso_medio || 0) + '%']
            ];
            
            doc.autoTable({ 
                startY: yPos, 
                head: [['Indicador Estratégico', 'Valor Consolidado']], 
                body: kpiData, 
                theme: 'grid', 
                headStyles: { fillColor: [55, 90, 75], textColor: [255, 255, 255], fontSize: 9, fontStyle: 'bold' }, 
                bodyStyles: { fontSize: 9, textColor: [30, 41, 59] },
                columnStyles: { 0: { cellWidth: 120 }, 1: { cellWidth: 60, halign: 'right', fontStyle: 'bold' } }
            });

        // -------------------------------------------------------------
        // 2. RELATÓRIO DE METAS & OBJETIVOS (METABUILDER ATUALIZADO)
        // -------------------------------------------------------------
        } else if (tipo === 'metas') {
            const resp = await fetch('/v1/meta-builder/metas-otimizadas?status=todas', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const data = await resp.json();
            const metas = (data.success && data.data) ? data.data : (Array.isArray(data) ? data : []);
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text('Relatório Analítico de Metas & Objetivos', 15, yPos);
            yPos += 8;
            
            if (metas && metas.length) {
                const metasData = metas.map(m => {
                    const prog = Math.min(100, Math.max(0, Number(m.progresso || 0))).toFixed(1);
                    const dtIni = formatarDataBR(m.data_inicio);
                    const dtFim = formatarDataBR(m.data_fim);
                    const statusTxt = m.status === 'concluida' ? 'Concluída' : 'Ativa';
                    return [
                        m.titulo || 'Meta #' + m.id,
                        m.tipo_nome || 'Geral',
                        `${dtIni} a ${dtFim}`,
                        `${prog}%`,
                        statusTxt
                    ];
                });

                doc.autoTable({ 
                    startY: yPos, 
                    head: [['Meta / Objetivo', 'Tipo', 'Vigência', 'Progresso', 'Status']], 
                    body: metasData, 
                    theme: 'grid', 
                    headStyles: { fillColor: [16, 185, 129], textColor: [255, 255, 255], fontSize: 9, fontStyle: 'bold' }, 
                    bodyStyles: { fontSize: 8.5 },
                    columnStyles: { 
                        0: { cellWidth: 70, fontStyle: 'bold' }, 
                        1: { cellWidth: 35 }, 
                        2: { cellWidth: 40, halign: 'center' },
                        3: { cellWidth: 20, halign: 'right', fontStyle: 'bold' },
                        4: { cellWidth: 20, halign: 'center' }
                    }
                });
            } else {
                doc.setFontSize(10);
                doc.text('Nenhuma meta cadastrada no período.', 15, yPos);
            }

        // -------------------------------------------------------------
        // 3. RELATÓRIO DE CLIENTES (IMPLEMENTAÇÃO COMPLETA)
        // -------------------------------------------------------------
        } else if (tipo === 'clientes') {
            const resp = await fetch('/v1/marketing/clientes-compradores', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const data = await resp.json();
            const clientes = (data.success && data.crm_ambos_compradores) ? data.crm_ambos_compradores : [];
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text('Relatório de Clientes e Compradores Ativos', 15, yPos);
            yPos += 8;
            
            if (clientes && clientes.length) {
                const cliData = clientes.slice(0, 50).map(c => [
                    c.nome || '—',
                    c.empresa || '—',
                    (c.cidade || '') + (c.uf ? '/' + c.uf : ''),
                    c.origem || 'CRM',
                    formatarMoeda(c.total_compras || 0),
                    formatarDataBR(c.ultima_compra)
                ]);

                doc.autoTable({ 
                    startY: yPos, 
                    head: [['Cliente / Razão Social', 'Empresa / Fantasia', 'Cidade/UF', 'Origem', 'Total Compras', 'Última Compra']], 
                    body: cliData, 
                    theme: 'grid', 
                    headStyles: { fillColor: [124, 58, 237], textColor: [255, 255, 255], fontSize: 8.5, fontStyle: 'bold' }, 
                    bodyStyles: { fontSize: 8 },
                    columnStyles: { 
                        0: { cellWidth: 48, fontStyle: 'bold' }, 
                        1: { cellWidth: 40 }, 
                        2: { cellWidth: 28 },
                        3: { cellWidth: 18, halign: 'center' },
                        4: { cellWidth: 26, halign: 'right', fontStyle: 'bold' },
                        5: { cellWidth: 22, halign: 'center' }
                    }
                });
            } else {
                doc.setFontSize(10);
                doc.text('Nenhum cliente registrado na base de compradores.', 15, yPos);
            }

        // -------------------------------------------------------------
        // 4. RELATÓRIO DE PIPELINE CRM
        // -------------------------------------------------------------
        } else if (tipo === 'pipeline') {
            const resp = await fetch('/v1/marketing/crm-dashboard', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const data = await resp.json();
            const pipeline = data.pipeline || [];
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(14);
            doc.setFont('helvetica', 'bold');
            doc.text('Relatório do Funil Comercial / Pipeline', 15, yPos);
            yPos += 8;
            
            const pipeData = pipeline.map(p => [
                p.status || 'Etapa', 
                formatarNumero(p.total || 0), 
                formatarMoeda(p.valor || 0)
            ]);

            doc.autoTable({ 
                startY: yPos, 
                head: [['Etapa do Funil', 'Oportunidades (Qtd)', 'Volume Estimado (R$)']], 
                body: pipeData, 
                theme: 'grid', 
                headStyles: { fillColor: [37, 99, 235], textColor: [255, 255, 255], fontSize: 9, fontStyle: 'bold' }, 
                bodyStyles: { fontSize: 9 },
                columnStyles: { 
                    0: { cellWidth: 90, fontStyle: 'bold' }, 
                    1: { cellWidth: 40, halign: 'center' }, 
                    2: { cellWidth: 50, halign: 'right', fontStyle: 'bold' } 
                }
            });
        }
        
        // Rodapé em todas as páginas
        const totalPaginas = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPaginas; i++) { 
            doc.setPage(i); 
            doc.setFontSize(7.5); 
            doc.setTextColor(148, 163, 184); 
            doc.text(`Nutricional Distribuidora — Central de Relatórios Marketing`, 15, 290); 
            doc.text(`Página ${i} de ${totalPaginas}`, 180, 290); 
        }
        
        Swal.close();
        
        if (destino === 'email') {
            const pdfBlob = doc.output('blob');
            const fd = new FormData();
            fd.append('pdf', pdfBlob, `Relatorio_${tipo}_${hoje.replace(/\//g, '-')}.pdf`);
            fd.append('tipo', tipo);
            
            const respEmail = await fetch('/v1/marketing/enviar-relatorio-email', { 
                method: 'POST', 
                headers: { 'Authorization': 'Bearer ' + token }, 
                body: fd 
            });
            const resultEmail = await respEmail.json();
            
            if (resultEmail.success) {
                Swal.fire('Sucesso!', 'Relatório enviado por email com sucesso.', 'success');
            } else {
                window.open(doc.output('bloburl'), '_blank');
                Swal.fire('Atenção', 'Falha no envio do email. O PDF foi aberto no navegador.', 'warning');
            }
        } else {
            window.open(doc.output('bloburl'), '_blank');
        }
    } catch(e) {
        Swal.close();
        console.error('Erro ao gerar relatório:', e);
        Swal.fire('Erro', 'Falha ao gerar relatório: ' + e.message, 'error');
    }
}

// ============================================================================
// GERAR COMPARATIVO MENSAL
// ============================================================================
async function gerarComparativo() {
    Swal.fire({ title: 'Gerando Comparativo...', text: 'Consolidando períodos', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const { jsPDF } = window.jspdf;
        const token = localStorage.getItem('authToken');
        const resp = await fetch('/v1/marketing/comparativo-mensal', { 
            headers: { 'Authorization': 'Bearer ' + token } 
        });
        const data = await resp.json();
        
        const doc = new jsPDF('p', 'mm', 'a4');
        const hoje = new Date().toLocaleDateString('pt-BR');
        
        doc.setFillColor(55, 90, 75);
        doc.rect(0, 0, 210, 28, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(18);
        doc.setFont('helvetica', 'bold');
        doc.text('Nutricional Distribuidora', 15, 14);
        doc.setFontSize(9);
        doc.setFont('helvetica', 'normal');
        doc.text('Comparativo de Fechamento Mensal • ' + hoje, 15, 22);
        
        doc.setTextColor(30, 41, 59);
        doc.setFontSize(13);
        doc.setFont('helvetica', 'bold');
        doc.text(`Evolução Mensal (${data.mes_atual || 'Mês Atual'} vs ${data.mes_anterior || 'Mês Anterior'})`, 15, 38);
        
        const compData = [
            ['Leads Recebidos', formatarNumero(data.leads_atual || 0), formatarNumero(data.leads_anterior || 0), (data.variacao_leads || 0).toFixed(1) + '%'],
            ['Vendas Fechadas', formatarNumero(data.vendas_atual || 0), formatarNumero(data.vendas_anterior || 0), (data.variacao_vendas || 0).toFixed(1) + '%'],
            ['Faturamento Gerado', formatarMoeda(data.fat_atual || 0), formatarMoeda(data.fat_anterior || 0), (data.variacao_fat || 0).toFixed(1) + '%'],
            ['Investimento em Mídia', formatarMoeda(data.inv_atual || 0), formatarMoeda(data.inv_anterior || 0), (data.variacao_inv || 0).toFixed(1) + '%']
        ];
        
        doc.autoTable({ 
            startY: 45, 
            head: [['Indicador de Vendas', 'Mês Vigente', 'Mês Anterior', 'Variação (%)']], 
            body: compData, 
            theme: 'grid', 
            headStyles: { fillColor: [245, 158, 11], textColor: [255, 255, 255], fontSize: 9, fontStyle: 'bold' }, 
            bodyStyles: { fontSize: 9 },
            columnStyles: { 
                0: { cellWidth: 70, fontStyle: 'bold' },
                1: { cellWidth: 38, halign: 'right' },
                2: { cellWidth: 38, halign: 'right' },
                3: { cellWidth: 34, halign: 'right', fontStyle: 'bold' }
            }
        });
        
        Swal.close();
        window.open(doc.output('bloburl'), '_blank');
    } catch(e) {
        Swal.close();
        console.error('Erro ao gerar comparativo:', e);
        Swal.fire('Erro', 'Falha ao gerar comparativo.', 'error');
    }
}

// ============================================================================
// EXPORTAR CLIENTES CSV
// ============================================================================
function exportarCSV() { 
    const token = localStorage.getItem('authToken') || '';
    window.open('/v1/marketing/clientes/exportar/csv?token=' + encodeURIComponent(token), '_blank'); 
}

// ============================================================================
// CONFIGURAR EMAIL AUTOMÁTICO
// ============================================================================
function configurarEmailAuto() {
    Swal.fire({
        title: 'Agendamento de Relatórios',
        html: `
            <div class="text-left space-y-3">
                <p class="text-xs text-slate-500">Selecione o dia e o e-mail para envio semanal automático do consolidado executivo:</p>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Dia da Semana</label>
                    <select id="emailDia" class="w-full p-2.5 border rounded-xl text-xs font-semibold">
                        <option value="1">Toda Segunda-feira (08:00)</option>
                        <option value="3">Toda Quarta-feira (08:00)</option>
                        <option value="5" selected>Toda Sexta-feira (17:00)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Email Destinatário</label>
                    <input type="email" id="emailDestino" class="w-full p-2.5 border rounded-xl text-xs font-semibold" 
                           placeholder="email@nutricionalbr.com" value="marketing@nutricionalbr.com">
                </div>
            </div>
        `,
        showCancelButton: true, 
        confirmButtonText: 'Salvar Agendamento', 
        confirmButtonColor: '#7c3aed',
        cancelButtonText: 'Cancelar',
        preConfirm: async () => {
            const token = localStorage.getItem('authToken');
            const resp = await fetch('/v1/marketing/configurar-email-auto', { 
                method: 'POST', 
                headers: { 
                    'Authorization': 'Bearer ' + token, 
                    'Content-Type': 'application/json' 
                }, 
                body: JSON.stringify({ 
                    dia_semana: parseInt(document.getElementById('emailDia').value), 
                    email: document.getElementById('emailDestino').value 
                }) 
            });
            const result = await resp.json();
            if (!result.success) throw new Error(result.error);
            return result;
        }
    }).then(r => { 
        if (r.isConfirmed) Swal.fire('Configurado!', 'Envio automático semanal atualizado com sucesso.', 'success'); 
    });
}
</script>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>
