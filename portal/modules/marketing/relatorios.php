<?php
$pageTitle = 'Relatórios | Nutricional';
$version = time();
$extraCss = '
<link href="https://fonts.googleapis.com/css2?family=Chivo+Mono:wght@700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link rel="stylesheet" href="/portal/assets/css/module-base.css?v=' . $version . '">
';
require_once __DIR__ . '/../../estrutura/header.php';
?>

<div class="modulo-container max-w-full mx-auto px-4 lg:px-6 py-4">
    
    <!-- Header -->
    <div class="rounded-3xl p-5 mb-6 flex justify-between items-center shadow-sm bg-white">
        <div class="flex items-center gap-3">
            <a href="/portal/modules/marketing/dashboard.php" class="btn-voltar sm:flex w-10 h-10 rounded-xl items-center justify-center transition-colors mr-2 no-underline bg-slate-100 hover:bg-slate-200">
                <i class="fa-solid fa-arrow-left text-slate-600"></i>
            </a>
            <div class="w-12 h-12 bg-gradient-to-br from-rose-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-lg">
                <i class="fa-solid fa-file-pdf text-white text-xl"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-800 leading-none">CENTRAL DE RELATÓRIOS</h2>
                <span class="text-xs text-slate-400 font-medium">Exportação de Dados</span>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="/portal/modules/marketing/admin/index.php" class="px-4 py-2 bg-purple-600 text-white rounded-xl text-sm font-bold hover:bg-purple-700 transition-all">
                <i class="fa-solid fa-chart-simple mr-2"></i>Admin
            </a>
        </div>
    </div>

    <!-- Cards de Relatórios -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        
        <!-- Card: Dashboard PDF -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all">
            <div class="w-12 h-12 bg-rose-100 text-rose-600 rounded-xl flex items-center justify-center mb-4">
                <i class="fa-solid fa-chart-line text-xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 mb-2">Relatório de Performance</h3>
            <p class="text-sm text-slate-400 mb-4">KPIs, gráficos e resultados consolidados em PDF.</p>
            <div class="flex gap-2">
                <button onclick="gerarRelatorio('dashboard', 'pdf')" class="flex-1 px-4 py-2.5 bg-[#375a4b] text-white rounded-xl font-bold hover:bg-[#4a7a67] transition-all text-sm">
                    <i class="fa-solid fa-file-pdf mr-2"></i>PDF
                </button>
                <button onclick="gerarRelatorio('dashboard', 'email')" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all text-sm">
                    <i class="fa-solid fa-envelope"></i> Email
                </button>
            </div>
        </div>

        <!-- Card: Metas PDF -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-600 rounded-xl flex items-center justify-center mb-4">
                <i class="fa-solid fa-bullseye text-xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 mb-2">Relatório de Metas</h3>
            <p class="text-sm text-slate-400 mb-4">Progresso detalhado de todas as metas ativas.</p>
            <div class="flex gap-2">
                <button onclick="gerarRelatorio('metas', 'pdf')" class="flex-1 px-4 py-2.5 bg-emerald-600 text-white rounded-xl font-bold hover:bg-emerald-700 transition-all text-sm">
                    <i class="fa-solid fa-file-pdf mr-2"></i>PDF
                </button>
                <button onclick="gerarRelatorio('metas', 'email')" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all text-sm">
                    <i class="fa-solid fa-envelope"></i> Email
                </button>
            </div>
        </div>

        <!-- Card: Clientes -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all">
            <div class="w-12 h-12 bg-violet-100 text-violet-600 rounded-xl flex items-center justify-center mb-4">
                <i class="fa-solid fa-users text-xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 mb-2">Relatório de Clientes</h3>
            <p class="text-sm text-slate-400 mb-4">Lista completa com status e valores.</p>
            <div class="flex gap-2">
                <button onclick="gerarRelatorio('clientes', 'pdf')" class="flex-1 px-4 py-2.5 bg-violet-600 text-white rounded-xl font-bold hover:bg-violet-700 transition-all text-sm">
                    <i class="fa-solid fa-file-pdf mr-2"></i>PDF
                </button>
                <button onclick="exportarCSV()" class="px-4 py-2.5 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all text-sm">
                    <i class="fa-solid fa-file-csv"></i> CSV
                </button>
            </div>
        </div>

        <!-- Card: Comparativo -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all">
            <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center mb-4">
                <i class="fa-solid fa-code-compare text-xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 mb-2">Comparativo Mensal</h3>
            <p class="text-sm text-slate-400 mb-4">Mês atual vs mês anterior com variação %.</p>
            <button onclick="gerarComparativo()" class="w-full px-4 py-2.5 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all text-sm">
                <i class="fa-solid fa-file-pdf mr-2"></i>Gerar PDF
            </button>
        </div>

        <!-- Card: Pipeline -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all">
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center mb-4">
                <i class="fa-solid fa-funnel-dollar text-xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 mb-2">Relatório de Pipeline</h3>
            <p class="text-sm text-slate-400 mb-4">Funil de vendas completo por etapa.</p>
            <button onclick="gerarRelatorio('pipeline', 'pdf')" class="w-full px-4 py-2.5 bg-blue-500 text-white rounded-xl font-bold hover:bg-blue-600 transition-all text-sm">
                <i class="fa-solid fa-file-pdf mr-2"></i>Gerar PDF
            </button>
        </div>

        <!-- Card: Configurar Email -->
        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all">
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-xl flex items-center justify-center mb-4">
                <i class="fa-solid fa-gear text-xl"></i>
            </div>
            <h3 class="font-bold text-slate-800 mb-2">Email Automático</h3>
            <p class="text-sm text-slate-400 mb-4">Configurar envio semanal de relatórios.</p>
            <button onclick="configurarEmailAuto()" class="w-full px-4 py-2.5 bg-purple-500 text-white rounded-xl font-bold hover:bg-purple-600 transition-all text-sm">
                <i class="fa-solid fa-clock mr-2"></i>Configurar
            </button>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<script src="/portal/assets/js/marketing-utils.js?v=<?= $version ?>"></script>

<script>
// ============================================================================
// VERIFICAR DEPENDÊNCIAS
// ============================================================================
if (typeof MarketingUtils === 'undefined') {
    console.error('❌ MarketingUtils não carregado!');
    Swal.fire({
        title: 'Erro de carregamento',
        text: 'Recursos do marketing não carregaram corretamente. Recarregue a página.',
        icon: 'error',
        confirmButtonText: 'Recarregar'
    }).then(() => location.reload());
}

// ============================================================================
// FUNÇÕES AUXILIARES
// ============================================================================
const formatarMoeda = (valor) => MarketingUtils.formatarValor(valor, 'moeda');
const formatarPercentual = (valor) => MarketingUtils.formatarValor(valor, 'percentual');

// ============================================================================
// GERAR RELATÓRIO
// ============================================================================
async function gerarRelatorio(tipo, destino) {
    Swal.fire({ title: 'Gerando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const { jsPDF } = window.jspdf;
        const token = localStorage.getItem('authToken');
        const doc = new jsPDF('p', 'mm', 'a4');
        const hoje = new Date().toLocaleDateString('pt-BR');
        
        // Header do PDF
        doc.setFillColor(55, 90, 75);
        doc.rect(0, 0, 210, 30, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(20);
        doc.text('Nutricional Pet Food', 15, 18);
        doc.setFontSize(10);
        doc.text('Relatório - ' + hoje, 15, 26);
        
        let yPos = 45;
        
        if (tipo === 'dashboard') {
            const resp = await fetch('/v1/marketing/dashboard', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const data = await resp.json();
            const kpis = data.kpis || {};
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(16);
            doc.text('Relatório de Performance', 15, yPos);
            yPos += 10;
            
            const kpiData = [
                ['Total Leads', String(kpis.total_leads || 0)],
                ['Taxa de Conversão', formatarPercentual(kpis.taxa_conversao || 0)],
                ['CPL', formatarMoeda(kpis.cpl || 0)],
                ['ROAS', (kpis.roas || 0).toFixed(2) + 'x'],
                ['Faturamento', formatarMoeda(kpis.faturamento || 0)]
            ];
            
            doc.autoTable({ 
                startY: yPos, 
                head: [['Indicador', 'Valor']], 
                body: kpiData, 
                theme: 'grid', 
                headStyles: { fillColor: [55, 90, 75], textColor: [255, 255, 255], fontSize: 10 }, 
                bodyStyles: { fontSize: 10 } 
            });
            
        } else if (tipo === 'metas') {
            const resp = await fetch('/v1/marketing/metas-progresso', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const metas = await resp.json();
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(16);
            doc.text('Relatório de Metas', 15, yPos);
            yPos += 8;
            
            if (metas && metas.length) {
                const metasData = metas.map(m => [
                    m.titulo, 
                    `${m.total_leads_realizados || 0}/${m.meta_leads || 0} (${m.pct_leads || 0}%)`, 
                    `${formatarMoeda(m.total_faturamento || 0)} (${m.pct_faturamento || 0}%)`, 
                    m.dias_restantes > 0 ? `${m.dias_restantes} dias` : 'Vencida'
                ]);
                doc.autoTable({ 
                    startY: yPos, 
                    head: [['Meta', 'Leads', 'Faturamento', 'Prazo']], 
                    body: metasData, 
                    theme: 'grid', 
                    headStyles: { fillColor: [247, 190, 47], textColor: [30, 41, 59], fontSize: 9 }, 
                    bodyStyles: { fontSize: 9 } 
                });
            } else {
                doc.text('Nenhuma meta ativa', 15, yPos);
            }
            
        } else if (tipo === 'pipeline') {
            const resp = await fetch('/v1/marketing/crm-dashboard', { 
                headers: { 'Authorization': 'Bearer ' + token } 
            });
            const data = await resp.json();
            const pipeline = data.pipeline || [];
            
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(16);
            doc.text('Relatório de Pipeline', 15, yPos);
            yPos += 8;
            
            const pipeData = pipeline.map(p => [
                p.status, 
                String(p.total), 
                formatarMoeda(p.valor || 0)
            ]);
            doc.autoTable({ 
                startY: yPos, 
                head: [['Etapa', 'Quantidade', 'Valor Total']], 
                body: pipeData, 
                theme: 'grid', 
                headStyles: { fillColor: [59, 130, 246], textColor: [255, 255, 255], fontSize: 10 }, 
                bodyStyles: { fontSize: 10 } 
            });
        }
        
        // Adicionar numeração de páginas
        const totalPaginas = doc.internal.getNumberOfPages();
        for (let i = 1; i <= totalPaginas; i++) { 
            doc.setPage(i); 
            doc.setFontSize(7); 
            doc.setTextColor(148, 163, 184); 
            doc.text(`Nutricional - ${hoje}`, 10, 290); 
            doc.text(`Página ${i} de ${totalPaginas}`, 185, 290); 
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
                Swal.fire('Sucesso!', 'Relatório enviado por email.', 'success');
            } else {
                window.open(doc.output('bloburl'), '_blank');
                Swal.fire('Atenção', 'Email falhou. PDF aberto.', 'warning');
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
    Swal.fire({ title: 'Gerando...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    
    try {
        const { jsPDF } = window.jspdf;
        const token = localStorage.getItem('authToken');
        const resp = await fetch('/v1/marketing/comparativo-mensal', { 
            headers: { 'Authorization': 'Bearer ' + token } 
        });
        const data = await resp.json();
        
        const doc = new jsPDF('p', 'mm', 'a4');
        const hoje = new Date().toLocaleDateString('pt-BR');
        
        // Header
        doc.setFillColor(55, 90, 75);
        doc.rect(0, 0, 210, 30, 'F');
        doc.setTextColor(255, 255, 255);
        doc.setFontSize(20);
        doc.text('Nutricional Pet Food', 15, 18);
        doc.setFontSize(10);
        doc.text('Comparativo Mensal - ' + hoje, 15, 26);
        
        doc.setTextColor(30, 41, 59);
        doc.setFontSize(14);
        doc.text(`${data.mes_atual || '--'} vs ${data.mes_anterior || '--'}`, 15, 45);
        
        const compData = [
            ['Leads', String(data.leads_atual || 0), String(data.leads_anterior || 0), (data.variacao_leads || 0).toFixed(1) + '%'],
            ['Vendas', String(data.vendas_atual || 0), String(data.vendas_anterior || 0), (data.variacao_vendas || 0).toFixed(1) + '%'],
            ['Faturamento', formatarMoeda(data.fat_atual || 0), formatarMoeda(data.fat_anterior || 0), (data.variacao_fat || 0).toFixed(1) + '%'],
            ['Investimento', formatarMoeda(data.inv_atual || 0), formatarMoeda(data.inv_anterior || 0), (data.variacao_inv || 0).toFixed(1) + '%']
        ];
        
        doc.autoTable({ 
            startY: 55, 
            head: [['Indicador', 'Mês Atual', 'Mês Anterior', 'Variação']], 
            body: compData, 
            theme: 'grid', 
            headStyles: { fillColor: [245, 158, 11], textColor: [255, 255, 255], fontSize: 10 }, 
            bodyStyles: { fontSize: 10 } 
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
// EXPORTAR CLIENTES CSV (APENAS SE NECESSÁRIO)
// ============================================================================
function exportarCSV() { 
    window.open('/v1/marketing/clientes/exportar/csv', '_blank'); 
}

// ============================================================================
// CONFIGURAR EMAIL AUTOMÁTICO
// ============================================================================
function configurarEmailAuto() {
    Swal.fire({
        title: 'Email Automático',
        html: `
            <select id="emailDia" class="w-full p-2.5 border rounded-xl mb-3">
                <option value="1">Segunda</option>
                <option value="3">Quarta</option>
                <option value="5" selected>Sexta</option>
            </select>
            <input type="email" id="emailDestino" class="w-full p-2.5 border rounded-xl" 
                   placeholder="email@nutricionalbr.com" value="marketing@nutricionalbr.com">
        `,
        showCancelButton: true, 
        confirmButtonText: 'Salvar', 
        confirmButtonColor: '#059669',
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
        if (r.isConfirmed) Swal.fire('Configurado!', 'Relatórios automáticos ativados.', 'success'); 
    });
}
</script>

<?php require_once __DIR__ . '/../../estrutura/footer.php'; ?>