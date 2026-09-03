// ==========================================================================
// MÓDULO DE CRONS - VERSÃO COMPLETA COM SWEETALERT2
// ==========================================================================
(function() {
    'use strict';

   const API_BASE = `${API_URL}/cron`;
    
    // ======================================================================
    // FUNÇÕES DE UI (SWEETALERT2)
    // ======================================================================
    
    function showLoading(title = 'Carregando...', text = '') {
        Swal.fire({
            title: title,
            text: text,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    }
    
    function showSuccess(title, text) {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'success',
            confirmButtonColor: '#274036'
        });
    }
    
    function showError(title, text) {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'error',
            confirmButtonColor: '#274036'
        });
    }
    
    function showWarning(title, text) {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'warning',
            confirmButtonColor: '#274036'
        });
    }
    
    function showInfo(title, text) {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'info',
            confirmButtonColor: '#274036'
        });
    }
    
    async function confirmar(title, text, confirmText = 'Sim', cancelText = 'Cancelar') {
        return Swal.fire({
            title: title,
            text: text,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: confirmText,
            cancelButtonText: cancelText,
            confirmButtonColor: '#274036',
            cancelButtonColor: '#6c757d'
        });
    }
    
    // ======================================================================
    // FUNÇÕES PRINCIPAIS
    // ======================================================================
    
    async function carregarAuditoria() {
        const tbody = document.getElementById('auditoriaTable');
        const tipo = document.getElementById('filtroTipo')?.value || '';
        
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4"><i class="fa-solid fa-spinner fa-spin me-2"></i>Carregando...</td></tr>';
        
        try {
            const url = tipo ? `${API_BASE}/auditoria?tipo=${tipo}&limite=100` : `${API_BASE}/auditoria?limite=100`;
            
            const token = localStorage.getItem('authToken');
            const resp = await fetch(url, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            const text = await resp.text();
            const data = JSON.parse(text);
            
            if (!data.auditoria || data.auditoria.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-regular fa-folder-open me-2"></i>Nenhuma execução registrada</td></tr>';
                atualizarEstatisticas(data.estatisticas || []);
                return;
            }
            
            const tipos = {
                'representantes': '👥 Representantes',
                'gestores': '📊 Gestores',
                'historico_kpi': '📈 Histórico KPI',
                'limpar_links': '🔗 Limpeza de Links'
            };
            
            let html = '';
            data.auditoria.forEach(item => {
                const dataHora = new Date(item.data_execucao).toLocaleString('pt-BR');
                const statusClass = item.status === 'sucesso' ? 'success' : (item.status === 'erro' ? 'danger' : 'warning');
                const statusIcon = item.status === 'sucesso' ? 'check-circle' : (item.status === 'erro' ? 'times-circle' : 'clock');
                const origemIcon = item.origem === 'PAINEL' ? '🖥️' : '🤖';
                const origemNome = item.origem === 'PAINEL' ? 'Painel' : 'Automático';
                
                let resultadoPreview = '';
                try {
                    const res = JSON.parse(item.resultado || '{}');
                    if (res.enviados !== undefined) resultadoPreview = `📧 Enviados: ${res.enviados}`;
                    else if (res.processados !== undefined) resultadoPreview = `📊 Processados: ${res.processados}`;
                    else if (res.message) resultadoPreview = res.message;
                    else resultadoPreview = 'Ver detalhes';
                } catch {
                    resultadoPreview = (item.resultado || 'Sem detalhes').substring(0, 50);
                }
                
                html += `
                    <tr>
                        <td>${dataHora}</td>
                        <td>${tipos[item.tipo] || item.tipo}</td>
                        <td><span class="badge bg-${statusClass}"><i class="fa-regular fa-${statusIcon} me-1"></i>${item.status}</span></td>
                        <td>${item.usuario || '-'}</td>
                        <td>${origemIcon} ${origemNome}</td>
                        <td>${item.duracao_segundos ? item.duracao_segundos + 's' : '-'}</td>
                        <td>${resultadoPreview}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(${item.id})" title="Ver detalhes">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            
            tbody.innerHTML = html;
            atualizarEstatisticas(data.estatisticas || []);
            
        } catch (e) {
            console.error('Erro:', e);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-danger"><i class="fa-regular fa-circle-exclamation me-2"></i>Erro ao carregar dados</td></tr>';
        }
    }
    
    function atualizarEstatisticas(estatisticas) {
        const container = document.getElementById('statsContainer');
        if (!container) return;
        
        if (!estatisticas || estatisticas.length === 0) {
            container.innerHTML = '<div class="col-12"><div class="alert alert-info"><i class="fa-regular fa-info-circle me-2"></i>Nenhuma estatística disponível</div></div>';
            return;
        }
        
        const nomes = {
            'representantes': 'Representantes',
            'gestores': 'Gestores',
            'historico_kpi': 'Histórico KPI',
            'limpar_links': 'Limpeza de Links'
        };
        
        const icones = {
            'representantes': 'fa-users',
            'gestores': 'fa-chart-pie',
            'historico_kpi': 'fa-chart-line',
            'limpar_links': 'fa-broom'
        };
        
        let html = '';
        estatisticas.forEach(stat => {
            const ultima = stat.ultima_execucao ? new Date(stat.ultima_execucao).toLocaleString('pt-BR') : 'Nunca';
            const taxaSucesso = stat.total > 0 ? ((stat.sucessos / stat.total) * 100).toFixed(1) : 0;
            
            html += `
                <div class="col-md-4">
                    <div class="card stat-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-3">
                                <i class="fa-solid ${icones[stat.tipo] || 'fa-cog'} fs-3 me-2" style="color: var(--primary);"></i>
                                <h5 class="card-title mb-0">${nomes[stat.tipo] || stat.tipo}</h5>
                            </div>
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted">Total</small>
                                    <h4>${stat.total}</h4>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Sucesso</small>
                                    <h4 class="text-success">${taxaSucesso}%</h4>
                                </div>
                            </div>
                            <div class="progress mb-2" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: ${(stat.sucessos/stat.total)*100}%" title="Sucessos: ${stat.sucessos}">${stat.sucessos}</div>
                                <div class="progress-bar bg-danger" style="width: ${(stat.erros/stat.total)*100}%" title="Erros: ${stat.erros}">${stat.erros}</div>
                            </div>
                            <small class="text-muted">
                                <i class="fa-regular fa-clock me-1"></i>Última: ${ultima}
                            </small>
                        </div>
                    </div>
                </div>
            `;
        });
        
        container.innerHTML = html;
    }
    
    async function executarCron(tipo) {
        const nomes = {
            'representantes': 'Relatório de Representantes',
            'gestores': 'Relatório de Gestores',
            'historico_kpi': 'Histórico KPI'
        };
        
        const descricoes = {
            'representantes': 'Envia relatório de alterações em pedidos para os representantes',
            'gestores': 'Envia relatório de inadimplência para os gestores (apenas último dia útil)',
            'historico_kpi': 'Processa o histórico de KPIs financeiros para todos os usuários'
        };
        
        const confirm = await Swal.fire({
            title: 'Confirmar execução?',
            html: `
                <p class="mb-2"><strong>${nomes[tipo]}</strong></p>
                <p class="text-muted small">${descricoes[tipo]}</p>
                <p class="mb-0">Deseja executar este cron manualmente agora?</p>
            `,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-play me-2"></i>Sim, executar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#274036',
            cancelButtonColor: '#6c757d'
        });
        
        if (!confirm.isConfirmed) return;
        
        Swal.fire({
            title: '<i class="fa-solid fa-spinner fa-spin me-2"></i>Executando...',
            html: 'Isso pode levar alguns segundos.<br><small class="text-muted">Aguardando resposta da API...</small>',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => Swal.showLoading()
        });
        
        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(API_BASE + '/executar', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': 'Bearer ' + token
                },
                body: JSON.stringify({ tipo: tipo })
            });
            
            const text = await resp.text();
            const data = JSON.parse(text);
            
            Swal.close();
            
            if (data.success) {
                let detalhesHtml = '';
                
                if (data.detalhes) {
                    if (data.detalhes.enviados !== undefined) {
                        detalhesHtml = `<br><small class="text-muted">📧 Enviados: ${data.detalhes.enviados} | ❌ Falhas: ${data.detalhes.falhas || 0}</small>`;
                    } else if (data.detalhes.processados !== undefined) {
                        detalhesHtml = `<br><small class="text-muted">📊 Processados: ${data.detalhes.processados}</small>`;
                    }
                }
                
                await Swal.fire({
                    title: '<i class="fa-regular fa-circle-check text-success me-2"></i>Sucesso!',
                    html: `${data.message}${detalhesHtml}<br><small class="text-muted">⏱️ Duração: ${data.duracao}s</small>`,
                    icon: 'success',
                    confirmButtonColor: '#274036'
                });
                
                await carregarAuditoria();
            } else {
                await Swal.fire({
                    title: '<i class="fa-regular fa-circle-xmark text-danger me-2"></i>Erro!',
                    html: data.message || 'Falha na execução',
                    icon: 'error',
                    confirmButtonColor: '#274036'
                });
            }
            
        } catch (e) {
            Swal.close();
            console.error('Erro:', e);
            
            Swal.fire({
                title: '<i class="fa-regular fa-circle-xmark text-danger me-2"></i>Erro de Conexão',
                text: 'Falha na requisição. Tente novamente.',
                icon: 'error',
                confirmButtonColor: '#274036'
            });
        }
    }
    
    async function limparLinks() {
        const confirm = await Swal.fire({
            title: 'Limpar links?',
            html: `
                <p class="mb-2">Isso permitirá reutilizar tokens que já foram usados.</p>
                <p class="text-muted small">Use esta opção apenas se estiver enfrentando erros de "Link já foi utilizado".</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-broom me-2"></i>Sim, limpar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#f59e0b',
            cancelButtonColor: '#6c757d'
        });
        
        if (!confirm.isConfirmed) return;
        
        Swal.fire({
            title: '<i class="fa-solid fa-spinner fa-spin me-2"></i>Limpando...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
        
        try {
            const token = localStorage.getItem('authToken');
            const resp = await fetch(API_BASE + '/limpar-links', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token }
            });
            
            const text = await resp.text();
            const data = JSON.parse(text);
            
            Swal.close();
            
            if (data.success) {
                await Swal.fire({
                    title: '<i class="fa-regular fa-circle-check text-success me-2"></i>Sucesso!',
                    text: data.message,
                    icon: 'success',
                    confirmButtonColor: '#274036'
                });
                await carregarAuditoria();
            } else {
                await Swal.fire({
                    title: '<i class="fa-regular fa-circle-xmark text-danger me-2"></i>Erro!',
                    text: data.message,
                    icon: 'error',
                    confirmButtonColor: '#274036'
                });
            }
            
        } catch (e) {
            Swal.close();
            Swal.fire({
                title: 'Erro de Conexão',
                text: 'Falha na requisição',
                icon: 'error',
                confirmButtonColor: '#274036'
            });
        }
    }
    
async function verDetalhes(id) {
    Swal.fire({
        title: '<i class="fa-solid fa-spinner fa-spin me-2"></i>Carregando...',
        text: 'Buscando detalhes da execução...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    
    try {
        const token = localStorage.getItem('authToken');
        const resp = await fetch(`${API_BASE}/auditoria/${id}`, {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        
        const text = await resp.text();
        const data = JSON.parse(text);
        
        Swal.close();
        
        if (data.error) {
            Swal.fire({
                title: 'Erro',
                text: data.error,
                icon: 'error',
                confirmButtonColor: '#274036'
            });
            return;
        }
        
        // Formatar dados
        const dataHora = new Date(data.data_execucao).toLocaleString('pt-BR');
        const statusClass = data.status === 'sucesso' ? 'success' : (data.status === 'erro' ? 'danger' : 'warning');
        const statusIcon = data.status === 'sucesso' ? '✅' : (data.status === 'erro' ? '❌' : '⚠️');
        const origemIcon = data.origem === 'PAINEL' ? '🖥️' : '🤖';
        const origemNome = data.origem === 'PAINEL' ? 'Painel (Manual)' : 'Automático (cPanel)';
        
        const tipos = {
            'representantes': '👥 Relatório de Representantes',
            'gestores': '📊 Relatório de Gestores',
            'historico_kpi': '📈 Histórico KPI',
            'limpar_links': '🔗 Limpeza de Links'
        };
        
        const tipoNome = tipos[data.tipo] || data.tipo;
        
        // Processar resultado para exibição bonita
        let resultadoHtml = '';
        
        if (data.resultado_decodificado) {
            const res = data.resultado_decodificado;
            
            if (data.tipo === 'representantes') {
                resultadoHtml = `
                    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 20px; margin-top: 15px;">
                        <h5 style="margin-bottom: 15px; color: #274036;">
                            <i class="fa-solid fa-chart-simple me-2"></i>Resumo da Execução
                        </h5>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 15px;">
                            <div style="text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <div style="font-size: 24px; color: #0d6efd; margin-bottom: 5px;">
                                    <i class="fa-solid fa-users"></i>
                                </div>
                                <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Representantes</div>
                                <div style="font-size: 28px; font-weight: bold; color: #274036;">${res.total_representantes || 0}</div>
                            </div>
                            <div style="text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <div style="font-size: 24px; color: #10b981; margin-bottom: 5px;">
                                    <i class="fa-solid fa-check-circle"></i>
                                </div>
                                <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Enviados</div>
                                <div style="font-size: 28px; font-weight: bold; color: #10b981;">${res.enviados || 0}</div>
                            </div>
                            <div style="text-align: center; padding: 15px; background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                                <div style="font-size: 24px; color: #ef4444; margin-bottom: 5px;">
                                    <i class="fa-solid fa-times-circle"></i>
                                </div>
                                <div style="font-size: 12px; color: #6c757d; text-transform: uppercase;">Falhas</div>
                                <div style="font-size: 28px; font-weight: bold; color: #ef4444;">${res.falhas || 0}</div>
                            </div>
                        </div>`;
                
                if (res.detalhes && Array.isArray(res.detalhes) && res.detalhes.length > 0) {
                    resultadoHtml += `
                        <div style="background: white; border-radius: 8px; padding: 15px; margin-top: 10px;">
                            <h6 style="margin-bottom: 10px; color: #274036;">
                                <i class="fa-solid fa-list me-2"></i>Detalhes dos Envios
                            </h6>
                            <div style="max-height: 200px; overflow-y: auto;">`;
                    
                    res.detalhes.forEach(d => {
                        const isSuccess = d.includes('✅');
                        const isError = d.includes('❌');
                        const icon = isSuccess ? '✅' : (isError ? '❌' : '⚠️');
                        const color = isSuccess ? '#10b981' : (isError ? '#ef4444' : '#f59e0b');
                        resultadoHtml += `<div style="padding: 5px 0; color: ${color};">${icon} ${d}</div>`;
                    });
                    
                    resultadoHtml += `</div></div>`;
                }
                
                resultadoHtml += `</div>`;
                
            } else if (data.tipo === 'gestores') {
                resultadoHtml = `
                    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 20px; margin-top: 15px;">
                        <h5 style="margin-bottom: 15px; color: #274036;">
                            <i class="fa-solid fa-chart-pie me-2"></i>Resumo da Execução
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                                <div style="font-size: 32px; color: #10b981; margin-bottom: 10px;">
                                    <i class="fa-solid fa-envelope"></i>
                                </div>
                                <div style="font-size: 14px; color: #6c757d; text-transform: uppercase;">Emails Enviados</div>
                                <div style="font-size: 36px; font-weight: bold; color: #10b981;">${res.enviados || 0}</div>
                            </div>
                        </div>
                    </div>
                `;
                
            } else if (data.tipo === 'historico_kpi') {
                resultadoHtml = `
                    <div style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 12px; padding: 20px; margin-top: 15px;">
                        <h5 style="margin-bottom: 15px; color: #274036;">
                            <i class="fa-solid fa-database me-2"></i>Resumo da Execução
                        </h5>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <div style="text-align: center; padding: 20px; background: white; border-radius: 8px;">
                                <div style="font-size: 32px; color: #0d6efd; margin-bottom: 10px;">
                                    <i class="fa-solid fa-user-gear"></i>
                                </div>
                                <div style="font-size: 14px; color: #6c757d; text-transform: uppercase;">Usuários Processados</div>
                                <div style="font-size: 36px; font-weight: bold; color: #0d6efd;">${res.processados || 0}</div>
                            </div>
                        </div>
                    </div>
                `;
            } else {
                // Fallback: JSON formatado
                resultadoHtml = `
                    <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-top: 15px;">
                        <h6 style="margin-bottom: 10px; color: #274036;">
                            <i class="fa-solid fa-code me-2"></i>Resultado
                        </h6>
                        <pre style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; max-height: 300px; overflow: auto; font-size: 12px; font-family: 'Courier New', monospace;">${JSON.stringify(res, null, 2)}</pre>
                    </div>
                `;
            }
        } else {
            resultadoHtml = `
                <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; margin-top: 15px;">
                    <h6 style="margin-bottom: 10px; color: #274036;">
                        <i class="fa-solid fa-file-lines me-2"></i>Resultado Bruto
                    </h6>
                    <div style="background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 8px; max-height: 300px; overflow: auto; font-family: 'Courier New', monospace; white-space: pre-wrap; word-break: break-all;">${data.resultado || 'Sem detalhes'}</div>
                </div>
            `;
        }
        
        // Construir o modal completo
        const html = `
            <div style="font-family: 'Segoe UI', Arial, sans-serif;">
                <!-- Cabeçalho -->
                <div style="background: linear-gradient(135deg, #274036 0%, #1a2a24 100%); margin: -16px -16px 20px -16px; padding: 20px; border-radius: 16px 16px 0 0; color: white;">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <h4 style="margin: 0; font-weight: 600;">
                            <i class="fa-solid fa-circle-info me-2"></i>Detalhes da Execução #${id}
                        </h4>
                        <span class="badge bg-${statusClass}" style="font-size: 14px; padding: 8px 16px;">
                            ${statusIcon} ${data.status.toUpperCase()}
                        </span>
                    </div>
                </div>
                
                <!-- Informações principais -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 15px;">
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-calendar me-1"></i>Data/Hora
                        </div>
                        <div style="font-weight: 600; color: #274036;">${dataHora}</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-clock me-1"></i>Duração
                        </div>
                        <div style="font-weight: 600; color: #274036;">${data.duracao_segundos || 0}s</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-tag me-1"></i>Tipo
                        </div>
                        <div style="font-weight: 600; color: #274036;">${tipoNome}</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-user me-1"></i>Usuário
                        </div>
                        <div style="font-weight: 600; color: #274036;">${data.usuario || 'SISTEMA'}</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-globe me-1"></i>Origem
                        </div>
                        <div style="font-weight: 600; color: #274036;">${origemIcon} ${origemNome}</div>
                    </div>
                    <div style="background: #f8f9fa; padding: 12px; border-radius: 8px;">
                        <div style="font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px;">
                            <i class="fa-regular fa-network-wired me-1"></i>IP
                        </div>
                        <div style="font-weight: 600; color: #274036;">${data.ip || '-'}</div>
                    </div>
                </div>
                
                <!-- Resultado formatado -->
                ${resultadoHtml}
            </div>
        `;
        
        Swal.fire({
            title: '',
            html: html,
            width: '700px',
            showConfirmButton: true,
            confirmButtonText: '<i class="fa-solid fa-check me-2"></i>Fechar',
            confirmButtonColor: '#274036',
            customClass: {
                popup: 'swal-wide'
            }
        });
        
    } catch (e) {
        Swal.close();
        Swal.fire({
            title: 'Erro',
            text: 'Não foi possível carregar os detalhes',
            icon: 'error',
            confirmButtonColor: '#274036'
        });
    }
}
    
    // ======================================================================
    // EXPORTAÇÃO GLOBAL
    // ======================================================================
    window.carregarAuditoria = carregarAuditoria;
    window.executarCron = executarCron;
    window.limparLinks = limparLinks;
    window.verDetalhes = verDetalhes;
    
    // ======================================================================
    // INICIALIZAÇÃO
    // ======================================================================
    document.addEventListener('DOMContentLoaded', carregarAuditoria);
    
})();