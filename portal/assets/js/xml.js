// ==========================================================================
// MÓDULO DE CONFERÊNCIA XML - VERSÃO COMPLETA E CORRIGIDA
// ==========================================================================

// ==========================================================================
// CLASSE VALIDADOR DE QUANTIDADE - CORRIGIDA
// ==========================================================================

class ValidadorQuantidade {
    constructor(qtdOC, qtdXML, fator, unidadeOC = 'UN', unidadeXML = 'UN', tolerancia = 0.009) {
        this.qtdOC = parseFloat(qtdOC) || 0;
        this.qtdXML = parseFloat(qtdXML) || 0;
        this.fator = parseFloat(fator) || 1;
        this.unidadeOC = unidadeOC || 'UN';
        this.unidadeXML = unidadeXML || 'UN';
        this.tolerancia = tolerancia;
        if (this.fator <= 0) this.fator = 1;
        this.resultados = [];
        this.melhorMatch = null;
    }

    isIgual(v1, v2) {
        return Math.abs(v1 - v2) <= this.tolerancia;
    }

    formatarNumero(v) {
        return parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    }

    validar() {
        this.resultados = [];

        const unidadesDiferentes = this.unidadeOC !== this.unidadeXML;
        const xmlEmUnidadeMaior = unidadesDiferentes && this.fator > 1;

        if (xmlEmUnidadeMaior) {
            // XML está em unidade maior (ex: CX) -> converte para UN
            const qtdXMLConvertida = this.qtdXML * this.fator;
            if (this.isIgual(this.qtdOC, qtdXMLConvertida)) {
                this.resultados.push({
                    status: 'OK',
                    tipo: 'XML_CONVERTIDO_OK',
                    qtdOCExibida: this.qtdOC,
                    qtdXMLExibida: qtdXMLConvertida,
                    mensagem: `${this.formatarNumero(this.qtdXML)} ${this.unidadeXML} × ${this.fator} = ${this.formatarNumero(qtdXMLConvertida)} ${this.unidadeOC}`,
                    descricao: `OC: ${this.formatarNumero(this.qtdOC)} ${this.unidadeOC} | XML: ${this.formatarNumero(qtdXMLConvertida)} ${this.unidadeOC}`,
                    exibirConversao: true,
                    fator: this.fator,
                    prioridade: 1
                });
            } else {
                this.resultados.push({
                    status: 'DIVERGENTE',
                    tipo: 'XML_CONVERTIDO_DIVERGENTE',
                    qtdOCExibida: this.qtdOC,
                    qtdXMLExibida: qtdXMLConvertida,
                    mensagem: `❌ Qtd divergente: esperado ${this.formatarNumero(this.qtdOC)} ${this.unidadeOC}, recebido ${this.formatarNumero(this.qtdXML)} ${this.unidadeXML} (convertido = ${this.formatarNumero(qtdXMLConvertida)} ${this.unidadeOC})`,
                    descricao: `OC: ${this.formatarNumero(this.qtdOC)} ${this.unidadeOC} | XML: ${this.formatarNumero(qtdXMLConvertida)} ${this.unidadeOC} | Diferença: ${this.formatarNumero(Math.abs(this.qtdOC - qtdXMLConvertida))} ${this.unidadeOC}`,
                    exibirConversao: true,
                    fator: this.fator,
                    prioridade: 2
                });
            }
        } else {
            // XML já está na mesma unidade (UN) ou fator = 1
            if (this.isIgual(this.qtdOC, this.qtdXML)) {
                this.resultados.push({
                    status: 'OK',
                    tipo: 'MESMA_UNIDADE',
                    qtdOCExibida: this.qtdOC,
                    qtdXMLExibida: this.qtdXML,
                    mensagem: `${this.formatarNumero(this.qtdOC)} ${this.unidadeOC} = ${this.formatarNumero(this.qtdXML)} ${this.unidadeXML}`,
                    descricao: 'Quantidades iguais na mesma unidade',
                    exibirConversao: false,
                    prioridade: 3
                });
            } else {
                this.resultados.push({
                    status: 'DIVERGENTE',
                    tipo: 'MESMA_UNIDADE_DIVERGENTE',
                    qtdOCExibida: this.qtdOC,
                    qtdXMLExibida: this.qtdXML,
                    mensagem: `❌ Qtd divergente: esperado ${this.formatarNumero(this.qtdOC)} ${this.unidadeOC}, recebido ${this.formatarNumero(this.qtdXML)} ${this.unidadeXML}`,
                    descricao: `OC: ${this.formatarNumero(this.qtdOC)} ${this.unidadeOC} | XML: ${this.formatarNumero(this.qtdXML)} ${this.unidadeXML} | Diferença: ${this.formatarNumero(Math.abs(this.qtdOC - this.qtdXML))} ${this.unidadeOC}`,
                    exibirConversao: false,
                    prioridade: 4
                });
            }
        }

        this.resultados.sort((a, b) => a.prioridade - b.prioridade);
        this.melhorMatch = this.resultados[0];
        return this.melhorMatch;
    }

    getStatus() {
        if (!this.melhorMatch) this.validar();
        return this.melhorMatch.status;
    }

    isOK() {
        return this.getStatus() === 'OK';
    }

    isDivergente() {
        return this.getStatus() === 'DIVERGENTE';
    }

    getMensagem() {
        if (!this.melhorMatch) this.validar();
        return this.melhorMatch.mensagem;
    }

    getQtdExibida() {
        if (!this.melhorMatch) this.validar();
        return this.melhorMatch.qtdXMLExibida;
    }

    getQtdOCExibida() {
        if (!this.melhorMatch) this.validar();
        return this.melhorMatch.qtdOCExibida;
    }

    getHTML() {
        if (!this.melhorMatch) this.validar();
        const s = this.melhorMatch;
        const cor = s.status === 'OK' ? '#059669' : '#dc2626';
        const icone = s.status === 'OK' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        const bg = s.status === 'OK' ? '#f0fdf4' : '#fef2f2';
        const border = s.status === 'OK' ? '#bbf7d0' : '#fecaca';

        let html = `<div style="background:${bg};border:1px solid ${border};border-radius:6px;padding:4px 8px;margin-top:4px;">
            <div style="display:flex;align-items:center;gap:6px;color:${cor};font-size:10px;font-weight:600;">
                <i class="fa ${icone}" style="font-size:11px;"></i>
                <span>${s.mensagem}</span>
            </div>`;
        if (s.exibirConversao) {
            html += `<div style="color:#64748b;font-size:9px;margin-top:1px;">
                <i class="fa fa-arrow-right" style="font-size:8px;"></i> ${s.descricao}
            </div>`;
        }
        html += `</div>`;
        return html;
    }
}

// ==========================================================================
// FUNÇÃO DE VALIDAÇÃO RÁPIDA
// ==========================================================================

function validarQuantidade(qtdOC, qtdXML, fator, unidadeOC = 'UN', unidadeXML = 'UN') {
    const validador = new ValidadorQuantidade(qtdOC, qtdXML, fator, unidadeOC, unidadeXML);
    return validador.validar();
}

// ==========================================================================
// ESTADO DA APLICAÇÃO
// ==========================================================================

const appState = {
    idoc: '',
    itensDaOC: [],
    nota: null,
    fornecedorNome: '',
    cnpjFornecedor: '',
    itensParaDeletar: [],
    itensParaAdicionar: [],
    itensXMLNaoMatch: [],
    notasSelecionadas: [],      
    itensXMLCombinados: [] 
};

// Formatação
const fMoeda = (v) => parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fQtd = (v) => parseFloat(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });

// ==========================================================================
// INICIALIZAÇÃO
// ==========================================================================

window.addEventListener('DOMContentLoaded', async () => {
    try {
        const resp = await fetchWithAuth('/v1/xml/filiais');
        const filiais = await resp.json();
        let h = '<option value="">Selecione a Filial...</option>';
        if (Array.isArray(filiais)) {
            filiais.forEach(f => { h += `<option value="${f.idfilial}">${f.razao}</option>`; });
        }
        document.getElementById('selFilial').innerHTML = h;
    } catch (e) { 
        console.error("Erro ao carregar filiais", e); 
    }
});

// ==========================================================================
// CARREGAMENTO DE DADOS
// ==========================================================================

async function carregarFornecedores() {
    const idFilial = document.getElementById('selFilial').value;
    if (!idFilial) return;
    try {
        const resp = await fetchWithAuth(`/v1/xml/fornecedores/${idFilial}`);
        const data = await resp.json();
        let h = '<option value="">Escolha o Fornecedor...</option>';
        if (Array.isArray(data)) {
            data.forEach(f => { h += `<option value="${f.idfornecedor}" data-cnpj="${f.cnpj}">${f.razao}</option>`; });
        }
        document.getElementById('selForn').innerHTML = h;
    } catch (e) {
        console.error("Erro ao carregar fornecedores", e);
    }
}

async function carregarOCs() {
    const s = document.getElementById('selForn');
    const idFilial = document.getElementById('selFilial').value;
    if (!s.value || !idFilial) {
        console.log('Aguardando seleção de fornecedor e filial');
        return;
    }
    appState.fornecedorNome = s.options[s.selectedIndex].text;
    appState.cnpjFornecedor = s.options[s.selectedIndex].dataset.cnpj;

    try {
        const resp = await fetchWithAuth(`/v1/xml/ordens-compra?idfornecedor=${s.value}&idfilial=${idFilial}`);
        if (!resp.ok) {
            const errorText = await resp.text();
            throw new Error(`HTTP ${resp.status}: ${errorText.substring(0, 100)}`);
        }
        const ocs = await resp.json();
        let h = '<option value="">Selecione a OC...</option>';
        if (Array.isArray(ocs) && ocs.length > 0) {
            ocs.forEach(o => {
                const badgePortal = o.conferido_portal === 'S' ? ' 🟢 PORTAL' : '';
                h += `<option value="${o.idoc}" data-data="${o.data_iso}" data-valor="${o.valor_num}">
                    ${o.descricao_select}${badgePortal}
                </option>`;
            });
        } else {
            h += '<option disabled>Nenhuma OC disponível</option>';
        }
        document.getElementById('selOC').innerHTML = h;
    } catch (e) {
        console.error("Erro ao carregar OCs:", e);
        document.getElementById('selOC').innerHTML = '<option>Erro ao carregar OCs</option>';
        Swal.fire('Erro', 'Falha ao carregar Ordens de Compra: ' + e.message, 'error');
    }
}

async function buscarNotas() {
    const s = document.getElementById('selOC');
    const opt = s.options[s.selectedIndex];
    if (!opt || !opt.value) {
        console.warn('⚠️ Nenhuma OC selecionada');
        return;
    }
    
    // Verificar se tem CNPJ
    if (!appState.cnpjFornecedor) {
        console.warn('⚠️ CNPJ do fornecedor não disponível');
        Swal.fire({
            icon: 'warning',
            title: 'CNPJ não encontrado',
            text: 'Não foi possível obter o CNPJ do fornecedor. Verifique o cadastro ou use a importação manual.',
            confirmButtonColor: '#274036'
        });
        return;
    }
    
    appState.idoc = s.value;
    appState.itensParaDeletar = [];
    appState.itensParaAdicionar = [];
    appState.itensXMLNaoMatch = [];
    appState.notasSelecionadas = [];
    
    document.getElementById('txt-oc-titulo').innerText = `Conferência OC #${appState.idoc}`;
    document.getElementById('txt-forn-subtitulo').innerText = appState.fornecedorNome;
    document.getElementById('val-total-oc').innerText = 'R$ ' + fMoeda(opt.dataset.valor);
    document.getElementById('containerNotas').style.display = 'block';
    document.getElementById('btnDesfazerExclusao').style.display = 'none';
    document.getElementById('countExcluidos').innerText = '0';

    try {
        // 🔥 URL SEM valor_oc
        const url = `/v1/xml/buscar-notas?cnpj=${appState.cnpjFornecedor}&data_oc=${opt.dataset.data}`;
        console.log('🔍 Buscando notas:', url);
        
        const [itensResp, notasResp] = await Promise.all([
            fetchWithAuth(`/v1/xml/consulta-oc/${appState.idoc}`),
            fetchWithAuth(url)
        ]);
        
        if (!itensResp.ok) {
            throw new Error(`Erro ao buscar itens da OC: ${itensResp.status}`);
        }
        
        appState.itensDaOC = await itensResp.json();
        console.log('📦 Itens da OC:', appState.itensDaOC.length);
        
        // 🔥 VERIFICAR SE A RESPOSTA DAS NOTAS É OK
        if (!notasResp.ok) {
            // Tentar ler o erro do corpo
            let errorMsg = `Erro ${notasResp.status}`;
            try {
                const errorData = await notasResp.json();
                if (errorData && errorData.error) {
                    errorMsg = errorData.error;
                } else if (errorData && errorData.aviso) {
                    errorMsg = errorData.aviso;
                }
            } catch (e) {
                // Se não conseguir ler o JSON, usa o status
            }
            throw new Error(`Erro ao buscar notas: ${errorMsg}`);
        }
        
        // 🔥 PROCESSAR RESPOSTA DAS NOTAS
        const responseData = await notasResp.json();
        console.log('📄 Resposta das notas:', responseData);
        
        // 🔥 EXTRAIR NOTAS - SUPORTE A AMBOS OS FORMATOS
        let notas = [];
        let aviso = null;
        
        // Caso 1: É um array diretamente
        if (Array.isArray(responseData)) {
            notas = responseData;
            console.log('✅ Notas encontradas (array):', notas.length);
        } 
        // Caso 2: É um objeto com propriedade 'notas'
        else if (responseData && typeof responseData === 'object') {
            if (responseData.notas && Array.isArray(responseData.notas)) {
                notas = responseData.notas;
                console.log('✅ Notas encontradas (objeto.notas):', notas.length);
            }
            if (responseData.aviso) {
                aviso = responseData.aviso;
                console.log('ℹ️ Aviso:', aviso);
            }
        }
        
        // Verificar OC já conferida
        if (appState.itensDaOC.length > 0 && appState.itensDaOC[0].ja_conferida) {
            const continuar = await Swal.fire({
                title: 'OC Já Conferida!',
                html: 'Esta Ordem de Compra já possui registros de conferência.<br><br><b>Deseja continuar para revisar/adicionar notas?</b>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, revisar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#274036',
                cancelButtonColor: '#ef4444'
            });
            if (!continuar.isConfirmed) {
                location.reload(); 
                return;
            }
        }

        // 🔥 MONTAR HTML
        let h = '';
        
        // Mostrar aviso se houver
        if (aviso) {
            h += `<div style="padding:12px; color:#856404; background:#fff3cd; border-radius:8px; font-size:12px; margin-bottom:12px; border:1px solid #fcd34d;">
                <i class="fa fa-info-circle"></i> ${aviso}
            </div>`;
        }
        
        // Mostrar notas
        if (notas && notas.length > 0) {
            console.log('📋 Renderizando', notas.length, 'notas...');
            
            notas.forEach((n, index) => {
                const notaStr = JSON.stringify(n).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                h += `<div class="card-nota-mini" data-nota='${notaStr}' 
                           onclick="toggleNotaSelecionada(this)" 
                           style="cursor:pointer; padding:16px; border:2px solid #e2e8f0; border-radius:14px; margin-bottom:8px; background:white; transition:all 0.2s;">
                    <b>NF: ${n.numeronf || 'N/A'}</b> - R$ ${fMoeda(n.valor || 0)}
                    <br><small>Emissão: ${n.emissao || 'N/A'}</small>
                    <br><small style="color:#64748b; font-size:10px;">Clique para selecionar</small>
                </div>`;
            });
            
            // Botão para conferir múltiplas notas
            h += `
                <div id="btnConferirMultiplas" style="display:none; margin-top:12px;">
                    <button onclick="window.conferirNotasMultiplas()" 
                            style="width:100%; background:#375a4b; color:white; border:none; padding:14px; border-radius:14px; font-weight:800; cursor:pointer; transition:all 0.2s; font-size:14px; display:flex; align-items:center; justify-content:center; gap:8px;">
                        <i class="fa fa-file-invoice"></i> 
                        CONFERIR NOTAS SELECIONADAS
                    </button>
                </div>
            `;
            
        } else if (!aviso) {
            // Tentar busca final sem filtro de data
            try {
                console.log('⚠️ Tentando busca sem filtro de data...');
                const respFinal = await fetchWithAuth(`/v1/xml/buscar-notas?cnpj=${appState.cnpjFornecedor}`);
                
                if (respFinal.ok) {
                    const dataFinal = await respFinal.json();
                    
                    let notasFinal = [];
                    if (Array.isArray(dataFinal)) {
                        notasFinal = dataFinal;
                    } else if (dataFinal && dataFinal.notas && Array.isArray(dataFinal.notas)) {
                        notasFinal = dataFinal.notas;
                    }
                    
                    if (notasFinal.length > 0) {
                        h = `<div style="padding:12px; color:#856404; background:#fff3cd; border-radius:8px; font-size:12px; margin-bottom:12px; border:1px solid #fcd34d;">
                            <i class="fa fa-info-circle"></i> Encontradas ${notasFinal.length} notas, mas fora do período de 30 dias da OC.
                        </div>`;
                        
                        notasFinal.forEach(n => {
                            const notaStr = JSON.stringify(n).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                            h += `<div class="card-nota-mini" data-nota='${notaStr}' 
                                       onclick="toggleNotaSelecionada(this)" 
                                       style="cursor:pointer; padding:16px; border:2px solid #e2e8f0; border-radius:14px; margin-bottom:8px; background:white; transition:all 0.2s;">
                                <b>NF: ${n.numeronf}</b> - R$ ${fMoeda(n.valor)}
                                <br><small>Emissão: ${n.emissao}</small>
                                <br><small style="color:#64748b; font-size:10px;">Clique para selecionar</small>
                            </div>`;
                        });
                        
                        h += `
                            <div id="btnConferirMultiplas" style="display:none; margin-top:12px;">
                                <button onclick="window.conferirNotasMultiplas()" 
                                        style="width:100%; background:#375a4b; color:white; border:none; padding:14px; border-radius:14px; font-weight:800; cursor:pointer; transition:all 0.2s; font-size:14px; display:flex; align-items:center; justify-content:center; gap:8px;">
                                    <i class="fa fa-file-invoice"></i> CONFERIR NOTAS SELECIONADAS
                                </button>
                            </div>
                        `;
                    }
                }
                
                if (!h) {
                    h = `<div style="padding:10px; color:#64748b; background:#f8fafc; border-radius:8px; font-size:12px;">
                        Nenhuma nota fiscal encontrada para este fornecedor.
                        <br><small>💡 Verifique o CNPJ (${appState.cnpjFornecedor}) ou use a importação manual.</small>
                    </div>`;
                }
            } catch (e) {
                console.error('❌ Erro na busca final:', e);
                h = `<div style="padding:10px; color:#64748b; background:#f8fafc; border-radius:8px; font-size:12px;">
                    Nenhuma nota fiscal encontrada para este fornecedor.
                    <br><small>💡 Verifique o CNPJ (${appState.cnpjFornecedor}) ou use a importação manual.</small>
                </div>`;
            }
        }
        
        document.getElementById('listaNotasCRM').innerHTML = h;
        console.log('✅ HTML das notas gerado com sucesso');
        
    } catch (e) {
        console.error('❌ Erro ao buscar notas:', e);
        
        // 🔥 MOSTRAR MENSAGEM DE ERRO NA TELA
        let errorHtml = `
            <div style="padding:16px; color:#991b1b; background:#fef2f2; border-radius:12px; border:1px solid #fecaca; text-align:center;">
                <i class="fa-solid fa-triangle-exclamation text-2xl mb-2 block" style="color:#dc2626;"></i>
                <p style="font-weight:600; font-size:14px;">Erro ao buscar notas</p>
                <p style="font-size:12px; margin-top:4px;">${e.message || 'Falha ao buscar dados da OC ou Notas Disponíveis.'}</p>
                <button onclick="buscarNotas()" style="margin-top:12px; background:#dc2626; color:white; border:none; padding:8px 20px; border-radius:8px; cursor:pointer; font-weight:600;">
                    <i class="fa-solid fa-rotate"></i> Tentar novamente
                </button>
            </div>
        `;
        document.getElementById('listaNotasCRM').innerHTML = errorHtml;
        
        Swal.fire({
            icon: 'error',
            title: 'Erro ao buscar notas',
            text: e.message || 'Falha ao buscar dados da OC ou Notas Disponíveis.',
            confirmButtonColor: '#ef4444'
        });
    }
}
// ==========================================================================
// SELEÇÃO DE NOTA E PROCESSAMENTO DO XML
// ==========================================================================

async function selecionarNota(el) {
    const notaStr = el.dataset.nota.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
    const nota = JSON.parse(notaStr);
    appState.nota = nota;
    document.querySelectorAll('.card-nota-mini').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    Swal.fire({ title: 'Processando XML...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
    try {
        const resp = await fetchWithAuth(`/v1/xml/itens-xml?chave=${nota.chave}`);
        const xmlText = await resp.text();
        const itensXML = extrairItensXML(xmlText);
        document.getElementById('val-total-xml').innerText = 'R$ ' + fMoeda(nota.valor);
        document.getElementById('txt-chave-nf').innerText = 'CHAVE: ' + nota.chave;
        document.getElementById('placeholder').style.display = 'none';
        document.getElementById('painelConferencia').style.display = 'block';
        renderizar(itensXML);
        Swal.close();
    } catch(e) { 
        console.error('Erro ao ler XML:', e);
        Swal.fire('Erro', 'Não foi possível ler os itens do XML.', 'error'); 
    }
}

// ==========================================================================
// CONFERÊNCIA COM MÚLTIPLAS NOTAS
// ==========================================================================

/**
 * Alterna a seleção de uma nota para conferência múltipla
 */
function toggleNotaSelecionada(el) {
    const notaStr = el.dataset.nota.replace(/&quot;/g, '"').replace(/&#39;/g, "'");
    const nota = JSON.parse(notaStr);
    
    const chave = nota.chave;
    const idx = appState.notasSelecionadas.findIndex(n => n.chave === chave);
    
    if (idx >= 0) {
        appState.notasSelecionadas.splice(idx, 1);
        el.classList.remove('active');
        el.style.borderColor = '#e2e8f0';
        el.style.background = 'white';
    } else {
        appState.notasSelecionadas.push(nota);
        el.classList.add('active');
        el.style.borderColor = '#375a4b';
        el.style.background = '#f0f4f2';
    }
    
    const btnConferir = document.getElementById('btnConferirMultiplas');
    if (btnConferir) {
        if (appState.notasSelecionadas.length > 0) {
            let total = 0;
            appState.notasSelecionadas.forEach(n => {
                total += parseFloat(n.valor) || 0;
            });
            
            btnConferir.style.display = 'block';
            const btn = btnConferir.querySelector('button');
            if (btn) {
                const qtd = appState.notasSelecionadas.length;
                btn.innerHTML = `
                    <i class="fa fa-file-invoice"></i> 
                    CONFERIR ${qtd} NOTA${qtd > 1 ? 'S' : ''}
                    <span style="background:rgba(255,255,255,0.2); padding:2px 12px; border-radius:12px; font-size:11px;">
                        R$ ${fMoeda(total)}
                    </span>
                `;
            }
        } else {
            btnConferir.style.display = 'none';
        }
    }
}

/**
 * Processa múltiplas notas selecionadas
 */
async function conferirNotasMultiplas() {
    if (appState.notasSelecionadas.length === 0) {
        Swal.fire('Aviso', 'Selecione pelo menos uma nota para conferir.', 'warning');
        return;
    }

    // 🔥 CALCULAR TOTAIS PARA EXIBIR NA CONFIRMAÇÃO
    let totalSelecionado = 0;
    let listaNotas = '';
    appState.notasSelecionadas.forEach((n, i) => {
        totalSelecionado += parseFloat(n.valor) || 0;
        listaNotas += `
            <div style="display:flex; justify-content:space-between; padding:4px 0; border-bottom:1px solid #f1f5f9;">
                <span><b>NF ${i+1}:</b> ${n.numeronf}</span>
                <span style="font-weight:700;">R$ ${fMoeda(n.valor)}</span>
            </div>
        `;
    });

    // 🔥 MOSTRAR CONFIRMAÇÃO
    const confirmacao = await Swal.fire({
        title: '📋 Confirmar Conferência Múltipla',
        html: `
            <div style="text-align:left;">
                <div style="background:#f8fafc; padding:16px; border-radius:12px; margin-bottom:16px;">
                    <div style="font-weight:700; color:#0f172a; font-size:14px; margin-bottom:8px;">
                        <i class="fa fa-file-invoice" style="color:#375a4b;"></i> Notas Selecionadas (${appState.notasSelecionadas.length})
                    </div>
                    ${listaNotas}
                    <div style="display:flex; justify-content:space-between; padding:8px 0; margin-top:8px; border-top:2px solid #375a4b; font-weight:800; color:#0f172a;">
                        <span>TOTAL</span>
                        <span style="color:#375a4b;">R$ ${fMoeda(totalSelecionado)}</span>
                    </div>
                </div>
                <div style="background:#fef3c7; padding:12px; border-radius:8px; border-left:4px solid #f59e0b; font-size:13px; color:#92400e;">
                    <i class="fa fa-info-circle"></i> 
                    Os itens de <b>todas as notas</b> serão combinados e conferidos contra a OC #${appState.idoc}.
                </div>
                <div style="margin-top:12px; font-size:12px; color:#64748b;">
                    <i class="fa fa-clock"></i> Este processo pode levar alguns segundos.
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: '✅ Sim, Conferir Agora',
        cancelButtonText: '❌ Cancelar',
        confirmButtonColor: '#375a4b',
        cancelButtonColor: '#ef4444',
        width: '520px'
    });

    if (!confirmacao.isConfirmed) {
        return; // Cancelou
    }

    // 🔥 PROCESSAR
    Swal.fire({
        title: '⏳ Processando Notas...',
        html: `Carregando ${appState.notasSelecionadas.length} nota(s)...`,
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false,
        showConfirmButton: false
    });
    
    try {
        const chaves = appState.notasSelecionadas.map(n => n.chave);
        
        const resp = await fetchWithAuth('/v1/xml/buscar-notas-multiplas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ chaves: chaves })
        });
        
        if (!resp.ok) {
            throw new Error('Erro ao buscar notas: ' + resp.status);
        }
        
        const notasComXml = await resp.json();
        
        if (!notasComXml || notasComXml.length === 0) {
            throw new Error('Nenhuma nota encontrada');
        }
        
        let todosItensXML = [];
        let totalNotas = 0;
        
        notasComXml.forEach(nota => {
            if (nota.xml_conteudo) {
                const itens = extrairItensXML(nota.xml_conteudo);
                todosItensXML = todosItensXML.concat(itens);
                totalNotas += parseFloat(nota.valor) || 0;
            }
        });
        
        if (todosItensXML.length === 0) {
            throw new Error('Nenhum item encontrado nas notas selecionadas.');
        }
        
        const itensAgrupados = agruparItensXML(todosItensXML);
        
        const totalNotasFormatado = 'R$ ' + fMoeda(totalNotas);
        document.getElementById('val-total-xml').innerText = totalNotasFormatado + ' (Múltiplas)';
        
        const chavesResumidas = chaves.map(c => c.substring(0, 10) + '...').join(', ');
        document.getElementById('txt-chave-nf').innerText = 'NOTAS: ' + chavesResumidas;
        
        document.getElementById('placeholder').style.display = 'none';
        document.getElementById('painelConferencia').style.display = 'block';
        
        renderizar(itensAgrupados);
        
        appState.notasSelecionadas = [];
        document.querySelectorAll('.card-nota-mini.active').forEach(el => {
            el.classList.remove('active');
            el.style.borderColor = '#e2e8f0';
            el.style.background = 'white';
        });
        document.getElementById('btnConferirMultiplas').style.display = 'none';
        
        Swal.close();
        
        Swal.fire({
            icon: 'success',
            title: '✅ Conferência Múltipla Iniciada!',
            html: `
                <div style="text-align: left;">
                    <p><b>📄 Notas processadas:</b> ${notasComXml.length}</p>
                    <p><b>📦 Itens totais (XML):</b> ${todosItensXML.length}</p>
                    <p><b>📊 Itens agrupados:</b> ${itensAgrupados.length}</p>
                    <p><b>💰 Total NF:</b> ${totalNotasFormatado}</p>
                    <p style="margin-top:8px; font-size:12px; color:#64748b;">
                        <i class="fa fa-check-circle" style="color:#10b981;"></i> 
                        ${itensAgrupados.length} itens foram carregados para conferência.
                    </p>
                </div>
            `,
            timer: 4000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
        
    } catch (error) {
        Swal.close();
        console.error('Erro ao conferir múltiplas notas:', error);
        Swal.fire({
            icon: 'error',
            title: '❌ Erro ao Processar',
            text: error.message || 'Falha ao processar as notas selecionadas.',
            confirmButtonColor: '#ef4444'
        });
    }
}

function extrairItensXML(xmlString) {
    const parser = new DOMParser();
    const xmlDoc = parser.parseFromString(xmlString, "text/xml");
    const lista = [];
    xmlDoc.querySelectorAll('det').forEach(det => {
        const prod = det.querySelector('prod');
        if (!prod) return;
        const getV = (t) => prod.querySelector(t)?.textContent.trim() || "";
        lista.push({ 
            codigo: getV('cProd'), 
            ean: getV('cEAN'), 
            eanTrib: getV('cEANTrib'), 
            xProd: getV('xProd'), 
            ncm: getV('NCM'),
            qCom: parseFloat(getV('qCom') || 0), 
            uCom: getV('uCom'),
            vUnCom: parseFloat(getV('vUnCom') || 0) 
        });
    });
    return lista;
}

// ==========================================================================
// AGRUPAR ITENS DUPLICADOS DO XML
// ==========================================================================

function agruparItensXML(itensXML) {
    const mapa = new Map();
    for (const item of itensXML) {
        const codigoLimpo = (item.codigo || '').trim().toUpperCase();
        const eanLimpo = (item.ean || '').replace(/\D/g, '');
        const eanTribLimpo = (item.eanTrib || '').replace(/\D/g, '');
        const ncmLimpo = (item.ncm || '').replace(/\D/g, '');
        let chave = '';
        if (codigoLimpo) chave = `COD:${codigoLimpo}`;
        else if (eanLimpo) chave = `EAN:${eanLimpo}`;
        else if (eanTribLimpo) chave = `EAN_TRIB:${eanTribLimpo}`;
        else if (ncmLimpo) chave = `NCM:${ncmLimpo}`;
        else {
            const nomeNormalizado = (item.xProd || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase().trim();
            chave = `NOME:${nomeNormalizado}`;
        }
        if (mapa.has(chave)) {
            const existente = mapa.get(chave);
            const qtdOriginal = existente.qCom;
            const novaQtd = item.qCom;
            const qtdTotal = qtdOriginal + novaQtd;
            const valorTotalOriginal = qtdOriginal * existente.vUnCom;
            const valorTotalNovo = novaQtd * item.vUnCom;
            const precoMedio = (valorTotalOriginal + valorTotalNovo) / qtdTotal;
            existente.qCom = qtdTotal;
            existente.vUnCom = precoMedio;
            if (item.xProd && item.xProd.length > (existente.xProd || '').length) {
                existente.xProd = item.xProd;
            }
        } else {
            mapa.set(chave, { ...item });
        }
    }
    return Array.from(mapa.values());
}

// ==========================================================================
// RENDERIZAÇÃO E MATCHING - VERSÃO DEFINITIVA
// ==========================================================================
function renderizar(itensXML) {
    // 1. Agrupa itens do XML
    const itensXMLAgrupados = agruparItensXML(itensXML);
    let poolXML = [...itensXMLAgrupados];
    
    console.log('📊 Itens XML antes do agrupamento:', itensXML.length);
    console.log('📊 Itens XML após agrupamento:', itensXMLAgrupados.length);
    
    // 2. Reseta estado
    appState.itensParaDeletar = [];
    appState.itensParaAdicionar = [];
    document.getElementById('btnDesfazerExclusao').style.display = 'none';
    document.getElementById('countExcluidos').innerText = '0';

    // 3. Prepara itens da OC
    let itensTrabalho = appState.itensDaOC.map(oc => ({
        oc: oc,
        fatorERP: Math.max(parseFloat(oc.fator_conversao) || 1, 1),
        xmlMatch: null,
        score: 0
    }));

    // =============================================================
    // FUNÇÃO DE SIMILARIDADE JACCARD (PURA, SEM LISTAS FIXAS)
    // =============================================================
    function jaccard(a, b) {
        const limparPalavra = (p) => {
            p = p.replace(/[0-9,.]/g, '');
            p = p.replace(/[^A-Z]/g, '');
            return p;
        };
        
        const setA = new Set(
            a.split(/[\s,.-]+/)
                .map(limparPalavra)
                .filter(w => w.length > 1)
        );
        
        const setB = new Set(
            b.split(/[\s,.-]+/)
                .map(limparPalavra)
                .filter(w => w.length > 1)
        );
        
        if (setA.size === 0 || setB.size === 0) return 0;
        
        const intersection = new Set([...setA].filter(x => setB.has(x)));
        const union = new Set([...setA, ...setB]);
        return intersection.size / union.size;
    }

    // =============================================================
    // EXTRAIR PESO DO NOME
    // =============================================================
    function extrairPeso(nome) {
        const match = nome.match(/(\d+[,.]?\d*)\s*(KG|G|ML|L|MG)/i);
        if (match) {
            const valor = parseFloat(match[1].replace(',', '.'));
            const unidade = match[2].toUpperCase();
            return { valor: valor, unidade: unidade, texto: match[0] };
        }
        return null;
    }

    // =============================================================
    // ORDENAÇÃO
    // =============================================================
    itensTrabalho.sort((a, b) => {
        const nomeA = a.oc.xprod || '';
        const nomeB = b.oc.xprod || '';
        const palavrasA = nomeA.split(/\s+/).length;
        const palavrasB = nomeB.split(/\s+/).length;
        if (palavrasA !== palavrasB) return palavrasB - palavrasA;
        return nomeB.length - nomeA.length;
    });

    // ======================================================================
    // RODADA 1: Match por EAN/Referência (exato) - PRIORIDADE MÁXIMA
    // ======================================================================
    itensTrabalho.forEach(item => {
        const oc = item.oc;
        const oc_ean_un = String(oc.ean_unidade || '').replace(/\D/g, '').replace(/^0+/, '');
        const oc_ean_cx = String(oc.ean_caixa || '').replace(/\D/g, '').replace(/^0+/, '');
        const oc_ref = String(oc.cprod || '').toUpperCase().trim();

        let melhorIndex = -1;
        let maiorScore = 0;
        let matchPorEAN = false;
        let matchPorRef = false;

        poolXML.forEach((x, idx) => {
            let scoreItem = 0;
            const xml_ean = String(x.ean || '').replace(/\D/g, '').replace(/^0+/, '');
            const xml_trib = String(x.eanTrib || '').replace(/\D/g, '').replace(/^0+/, '');
            const xml_ref = String(x.codigo || '').toUpperCase().trim();

            const hasValidEAN = xml_ean && xml_ean !== '';
            const hasValidTrib = xml_trib && xml_trib !== '';

            if (hasValidEAN && (xml_ean === oc_ean_un || xml_ean === oc_ean_cx)) {
                scoreItem = 100;
                matchPorEAN = true;
            }
            if (hasValidTrib && (xml_trib === oc_ean_un || xml_trib === oc_ean_cx)) {
                scoreItem = 100;
                matchPorEAN = true;
            }

            if (xml_ref && oc_ref && xml_ref === oc_ref) {
                scoreItem = 100;
                matchPorRef = true;
            }

            if (scoreItem > maiorScore) {
                maiorScore = scoreItem;
                melhorIndex = idx;
            }
        });

        if (maiorScore >= 90 && melhorIndex !== -1) {
            item.xmlMatch = poolXML[melhorIndex];
            item.score = maiorScore;
            poolXML.splice(melhorIndex, 1);
            
            if (matchPorEAN) {
                console.log(`✅ Match por EAN: ${oc.cprod} ↔ ${item.xmlMatch.codigo} (100%)`);
            } else if (matchPorRef) {
                console.log(`✅ Match por Referência: ${oc.cprod} ↔ ${item.xmlMatch.codigo} (100%)`);
            }
        }
    });

    // ======================================================================
    // RODADA 2: Match por NOME (Jaccard) - ÚNICA RODADA DE MATCH
    // ======================================================================
    const strClean = (str) => (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();

    itensTrabalho.forEach(item => {
        if (item.xmlMatch) return;
        
        const oc = item.oc;
        const oc_nome = strClean(String(oc.xprod || ''));
        const oc_ncm = String(oc.ncm || '').replace(/\D/g, '');
        const qEsperadaUn = parseFloat(oc.qcom || 0);
        const precoOC = parseFloat(oc.cuncom || 0);
        const ocPeso = extrairPeso(oc.xprod || '');

        const candidatos = [];

        poolXML.forEach((x, idx) => {
            let scoreItem = 0;
            const xml_nome = strClean(String(x.xProd || ''));
            const xml_ncm = String(x.ncm || '').replace(/\D/g, '');
            const xml_qtd = parseFloat(x.qCom || 0);
            const xml_preco = parseFloat(x.vUnCom || 0);
            const xmlPeso = extrairPeso(x.xProd || '');

            // 🔥 SIMILARIDADE DO NOME - CRITÉRIO OBRIGATÓRIO
            const similaridadeJaccard = jaccard(oc_nome, xml_nome);
            
            // 🔥 EXIGÊNCIA MÍNIMA DE 50% DE SIMILARIDADE
            if (similaridadeJaccard < 0.50) {
                return; // ignora este XML - NÃO FAZ MATCH!
            }

            // Pontos baseados na similaridade (90pts máximo)
            if (similaridadeJaccard >= 0.8) {
                scoreItem += 90;
            } else if (similaridadeJaccard >= 0.6) {
                scoreItem += 75;
            } else if (similaridadeJaccard >= 0.5) {
                scoreItem += 60;
            }

            // Bônus pequenos
            if (oc_ncm && xml_ncm === oc_ncm) scoreItem += 5;
            
            if (ocPeso && xmlPeso && ocPeso.valor === xmlPeso.valor && ocPeso.unidade === xmlPeso.unidade) {
                scoreItem += 3;
            }

            const qXmlCalculada = xml_qtd * item.fatorERP;
            if (Math.abs(qXmlCalculada - qEsperadaUn) < 0.01) scoreItem += 2;

            if (scoreItem >= 50) {
                candidatos.push({
                    index: idx,
                    score: scoreItem,
                    jaccard: similaridadeJaccard,
                    codigo: x.codigo,
                    xProd: x.xProd,
                    scoreOriginal: scoreItem
                });
            }
        });

        if (candidatos.length > 0) {
            candidatos.sort((a, b) => {
                if (b.score !== a.score) return b.score - a.score;
                return b.jaccard - a.jaccard;
            });

            const melhor = candidatos[0];
            item.xmlMatch = poolXML[melhor.index];
            item.score = Math.min(Math.round(melhor.scoreOriginal * 1.05), 100);
            poolXML.splice(melhor.index, 1);
        }
    });

       // ======================================================================
    // RODADA 3: Fallback por nome parcial (apenas para itens sem match)
    // EXIGE SIMILARIDADE MÍNIMA DE 35% E PESO IGUAL
    // ======================================================================
    itensTrabalho.forEach(item => {
        if (item.xmlMatch) return;
        const oc = item.oc;
        const oc_nome = strClean(String(oc.xprod || ''));
        const qEsperadaUn = parseFloat(oc.qcom || 0);
        const ocPeso = extrairPeso(oc.xprod || '');
        const oc_ncm = String(oc.ncm || '').replace(/\D/g, '');
        
        let melhorIndex = -1;
        let maiorScore = 0;
        
        poolXML.forEach((x, idx) => {
            let scoreItem = 0;
            const xml_nome = strClean(String(x.xProd || ''));
            const xml_qtd = parseFloat(x.qCom || 0);
            const xmlPeso = extrairPeso(x.xProd || '');
            const xml_ncm = String(x.ncm || '').replace(/\D/g, '');
            
            const similaridade = jaccard(oc_nome, xml_nome);
            
            // 🔥 EXIGÊNCIA: similaridade >= 35% E (peso igual OU NCM igual)
            if (similaridade < 0.35) return;
            
            // Peso da similaridade
            scoreItem = similaridade * 50;
            
            // Bônus se peso igual
            if (ocPeso && xmlPeso && ocPeso.valor === xmlPeso.valor && ocPeso.unidade === xmlPeso.unidade) {
                scoreItem += 20;
            }
            
            // Bônus se NCM igual
            if (oc_ncm && xml_ncm && oc_ncm === xml_ncm) {
                scoreItem += 10;
            }
            
            // Bônus se quantidade parecida
            const qXmlCalculada = xml_qtd * item.fatorERP;
            if (Math.abs(qXmlCalculada - qEsperadaUn) / qEsperadaUn < 0.3) {
                scoreItem += 10;
            }
            
            // 🔥 SÓ ACEITA SE SCORE >= 50
            if (scoreItem > maiorScore && scoreItem >= 50) {
                maiorScore = scoreItem;
                melhorIndex = idx;
            }
        });
        
        if (maiorScore >= 50 && melhorIndex !== -1) {
            item.xmlMatch = poolXML[melhorIndex];
            item.score = Math.round(maiorScore);
            poolXML.splice(melhorIndex, 1);
            console.log(`🔄 Match fallback: ${oc.cprod} → ${item.xmlMatch.codigo} (score: ${item.score}%)`);
        }
    });

    // ======================================================================
    // RODADA 4: Match forçado (apenas se NCM + Qtd + Preço baterem PERFEITAMENTE)
    // E EXIGE PESO IGUAL
    // ======================================================================
    itensTrabalho.forEach(item => {
        if (item.xmlMatch) return;
        const oc = item.oc;
        const oc_ncm = String(oc.ncm || '').replace(/\D/g, '');
        const qEsperadaUn = parseFloat(oc.qcom || 0);
        const precoOC = parseFloat(oc.cuncom || 0);
        const ocPeso = extrairPeso(oc.xprod || '');
        const oc_nome = strClean(String(oc.xprod || ''));
        
        let melhorIndex = -1;
        let maiorScore = 0;
        
        poolXML.forEach((x, idx) => {
            let scoreItem = 0;
            const xml_ncm = String(x.ncm || '').replace(/\D/g, '');
            const xml_qtd = parseFloat(x.qCom || 0);
            const xml_preco = parseFloat(x.vUnCom || 0);
            const xmlPeso = extrairPeso(x.xProd || '');
            const xml_nome = strClean(String(x.xProd || ''));
            
            // 🔥 EXIGÊNCIA: PESO DEVE SER IGUAL
            if (!ocPeso || !xmlPeso || ocPeso.valor !== xmlPeso.valor || ocPeso.unidade !== xmlPeso.unidade) {
                return; // peso diferente - NÃO FAZ MATCH
            }
            
            // NCM
            if (oc_ncm && xml_ncm === oc_ncm) scoreItem += 30;
            
            // Similaridade (pelo menos 20%)
            const similaridade = jaccard(oc_nome, xml_nome);
            if (similaridade >= 0.2) {
                scoreItem += 20;
            } else {
                return; // similaridade muito baixa
            }
            
            // Quantidade
            const qXmlCalculada = xml_qtd * item.fatorERP;
            if (Math.abs(qXmlCalculada - qEsperadaUn) < 0.01) scoreItem += 20;
            
            // Preço
            const pXmlCalculado = xml_preco / item.fatorERP;
            if (Math.abs(pXmlCalculado - precoOC) < 0.02) scoreItem += 20;
            
            // 🔥 SÓ ACEITA SE SCORE >= 70
            if (scoreItem > maiorScore && scoreItem >= 70) {
                maiorScore = scoreItem;
                melhorIndex = idx;
            }
        });
        
        if (maiorScore >= 70 && melhorIndex !== -1) {
            item.xmlMatch = poolXML[melhorIndex];
            item.score = Math.min(Math.round(maiorScore), 100);
            poolXML.splice(melhorIndex, 1);
            console.log(`✅ Match forçado: ${oc.cprod} → ${item.xmlMatch.codigo} (score: ${item.score}%)`);
        }
    });
    // ======================================================================
    // Itens do XML que não tiveram match
    // ======================================================================
    appState.itensXMLNaoMatch = [...poolXML];

    // ======================================================================
    // GERAÇÃO DO HTML
    // ======================================================================
    let html = '';
    
    itensTrabalho.forEach(item => {
        const oc = item.oc;
        const xmlMatch = item.xmlMatch;
        const score = item.score;
        const fatorERP = item.fatorERP;

        const qEsperadaUn = parseFloat(oc.qcom || 0);
        const precoOC = parseFloat(oc.cuncom || 0);
        const qXmlOriginal = xmlMatch ? parseFloat(xmlMatch.qCom) : 0;
        const qXmlCalculada = qXmlOriginal * fatorERP;
        const ucomFormatado = oc.ucom ? String(oc.ucom).split(',')[0] : 'UN';
        const unidadeXML = xmlMatch ? (xmlMatch.uCom || 'UN') : 'UN';
        const precoXMLAjustado = xmlMatch ? (parseFloat(xmlMatch.vUnCom) / fatorERP) : 0;

        let isDivQtd = true;
        let qtdExibida = qXmlCalculada;
        let htmlValidacao = '';

        if (xmlMatch && typeof ValidadorQuantidade !== 'undefined') {
            const unidadeOCComparacao = 'UN';
            const validador = new ValidadorQuantidade(qEsperadaUn, qXmlOriginal, fatorERP, unidadeOCComparacao, unidadeXML);
            const resultado = validador.validar();
            isDivQtd = resultado.status === 'DIVERGENTE';
            qtdExibida = resultado.qtdXMLExibida;
            htmlValidacao = validador.getHTML();
        } else if (xmlMatch) {
            isDivQtd = Math.abs(qEsperadaUn - qXmlCalculada) > 0.009;
        }

        const isDivPreco = xmlMatch && Math.abs(precoOC - precoXMLAjustado) > 0.02;
        const isDivGeral = isDivQtd || isDivPreco || !xmlMatch;

        // 🔥 LIMITE MÍNIMO PARA MATCH = 50%
        const LIMITE_MINIMO_MATCH = 50;
        const mostrarBotaoDelete = !xmlMatch || score < LIMITE_MINIMO_MATCH;

        // 🔥 STATUS
        let statusTexto = 'NÃO LOCALIZADO';
        let statusCor = '#b91c1c';
        let statusBg = '#fef2f2';
        let statusBorder = '#fecaca';

        if (xmlMatch && score >= LIMITE_MINIMO_MATCH) {
            if (isDivGeral) {
                statusTexto = 'DIVERGENTE';
                statusCor = '#b91c1c';
                statusBg = '#fef2f2';
                statusBorder = '#fecaca';
            } else {
                statusTexto = 'CONFERIDO';
                statusCor = '#15803d';
                statusBg = '#f0fdf4';
                statusBorder = '#bbf7d0';
            }
        }

        const corBgScore = score >= 65 ? '#dcfce7' : (score >= 45 ? '#fef9c3' : (score >= 35 ? '#fed7aa' : '#fee2e2'));
        const corTxtScore = score >= 65 ? '#166534' : (score >= 45 ? '#854d0e' : (score >= 35 ? '#9a3412' : '#991b1b'));

        const refEscapada = (oc.cprod || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        const nomeEscapado = (oc.xprod || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        html += `<div class="item-card ${isDivGeral ? 'divergente' : 'ok'}" id="row-${oc.iditem}" 
            data-id="${oc.iditem}" data-referencia="${refEscapada}" 
            data-nome="${nomeEscapado}" data-qtd="${qEsperadaUn}" data-valor-unit="${precoOC}"
            style="border-left-color: ${isDivGeral ? '#e53e3e' : '#38a169'}">
            
            <div style="overflow: hidden;">
                <div style="display: flex; gap: 6px; margin-bottom: 6px; align-items: center; flex-wrap: wrap;">
                    <span style="background: #1e293b; color: white; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800;">ID: ${oc.iditem}</span>
                    <span style="background: ${corBgScore}; color: ${corTxtScore}; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800;">MATCH: ${score}%</span>
                    ${fatorERP > 1 ? `<span style="background: #eff6ff; color: #1e40af; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 800;">FATOR: ${fatorERP}x</span>` : ''}
                </div>
                
                <div style="font-weight: 700; font-size: 13px; color: #0f172a; margin-bottom: 6px; word-break: break-word;">${oc.xprod}</div>
                
                <div style="background: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #f1f5f9; font-size: 11px;">
                    <div style="display: flex; align-items: center; gap: 6px; color: #475569; margin-bottom: 4px;">
                        <i class="fa fa-file-invoice" style="color: #94a3b8; width: 12px;"></i>
                        <span><b>[OC]</b> REF: ${oc.cprod} | EAN: ${oc.ean_unidade || '---'}</span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 6px; color: ${xmlMatch && score >= LIMITE_MINIMO_MATCH ? '#2563eb' : '#dc2626'};">
                        <i class="fa fa-barcode" style="color: ${xmlMatch && score >= LIMITE_MINIMO_MATCH ? '#94a3b8' : '#f87171'}; width: 12px;"></i>
                        <span><b>[XML]</b> ${xmlMatch && score >= LIMITE_MINIMO_MATCH ? `PROD: ${xmlMatch.xProd} | REF: ${xmlMatch.codigo}` : '<b>PRODUTO NÃO LOCALIZADO</b>'}</span>
                    </div>
                </div>
            </div>

            <div style="text-align:center;">
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px;">
                    <span style="font-weight: 700; color: #334155; font-size: 15px;" class="val-pedido">${fQtd(qEsperadaUn)}</span>
                    <small style="display:block; font-size: 10px; color: #94a3b8; font-weight: 700;">${ucomFormatado}</small>
                </div>
            </div>

            <div style="text-align:center;">
                <div style="background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 6px; padding: 6px;">
                    <span style="font-weight: 800; color: #4338ca; font-size: 16px;" class="valor-xml-destaque">${xmlMatch && score >= LIMITE_MINIMO_MATCH ? fQtd(qtdExibida) : '---'}</span>
                    <div style="font-size: 10px; color: #6366f1; font-weight: 700; margin-top: 2px;">
                        ${xmlMatch && score >= LIMITE_MINIMO_MATCH ? `${fQtd(qXmlOriginal)} (${unidadeXML}) × ${fatorERP}` : '---'}
                    </div>
                </div>
            </div>

            <div style="font-size: 12px; line-height: 1.5; color: #475569; padding-left: 10px; border-left: 1px solid #f1f5f9;">
                <div><b>OC:</b> R$ ${precoOC.toFixed(2)}</div>
                <div style="color: ${isDivPreco && score >= LIMITE_MINIMO_MATCH ? '#dc2626' : '#059669'}; font-weight: 700;" class="info-precos-auditoria">
                    <b>NOTA:</b> ${xmlMatch && score >= LIMITE_MINIMO_MATCH ? `R$ ${precoXMLAjustado.toFixed(2)}` : '---'} ${isDivPreco && score >= LIMITE_MINIMO_MATCH ? '<i class="fa fa-exclamation-triangle" style="font-size: 10px;"></i>' : ''}
                </div>
                ${xmlMatch && score >= LIMITE_MINIMO_MATCH ? htmlValidacao : ''}
            </div>

            <div style="text-align:center;">
                ${mostrarBotaoDelete ? 
                    `<button onclick="removerItemOC(${oc.iditem})" style="background: #fff; border: 1px solid #fecaca; color: #ef4444; padding: 6px 10px; border-radius: 6px; cursor: pointer;" title="Remover item da OC"><i class="fa fa-trash-alt"></i></button>` : 
                    (isDivQtd ? 
                        `<input type="number" class="qtd-input" value="${qtdExibida}" placeholder="0" data-id="${oc.iditem}" data-qtd-original="${qtdExibida}" oninput="validarLinha(this)" style="width: 80px; text-align: center; border: 2px solid #ef4444; background: #fff; border-radius: 6px; font-weight: 800; padding: 6px; color: #b91c1c;">` : 
                        `<input type="hidden" class="qtd-input" value="${qtdExibida}" data-id="${oc.iditem}"> <i class="fa fa-check-circle" style="color:#10b981; font-size: 20px;"></i>`
                    )}
            </div>

            <div style="text-align:center;">
                <span style="color: ${statusCor}; font-size: 9px; font-weight: 900; border: 1px solid ${statusBorder}; padding: 6px; border-radius: 6px; background: ${statusBg}; display: block; text-align: center;">
                    ${statusTexto}
                </span>
            </div>
        </div>`;
    });

    // ======================================================================
    // ITENS DO XML SEM MATCH
    // ======================================================================
    if (appState.itensXMLNaoMatch.length > 0) {
        html += `<div style="margin-top: 24px; padding: 16px; background: #fffbeb; border: 2px solid #f59e0b; border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <i class="fa fa-exclamation-triangle" style="color: #d97706; font-size: 20px;"></i>
                <strong style="color: #92400e;">⚠️ ITENS NA NOTA QUE NÃO ESTÃO NA OC (${appState.itensXMLNaoMatch.length})</strong>
            </div>
            <div style="font-size: 12px; color: #78350f; margin-bottom: 12px;">Estes produtos constam no XML da nota fiscal mas <b>não foram encontrados</b> na Ordem de Compra.</div>`;
        
        appState.itensXMLNaoMatch.forEach((xmlItem, index) => {
            const valorTotal = (xmlItem.qCom * xmlItem.vUnCom);
            
            html += `<div style="background: white; border: 1px solid #fcd34d; border-radius: 10px; padding: 12px; margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <span style="background: #dc2626; color: white; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: 800;">XML</span>
                            <div style="font-weight: 700; color: #1e293b; font-size: 13px;">${xmlItem.xProd}</div>
                        </div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                            <b>REF:</b> ${xmlItem.codigo || 'N/A'} | 
                            <b>EAN:</b> ${xmlItem.ean || xmlItem.eanTrib || 'N/A'} | 
                            <b>NCM:</b> ${xmlItem.ncm || 'N/A'}
                        </div>
                        <div style="font-size: 11px; color: #4338ca; font-weight: 700; margin-top: 2px;">
                            <b>Qtd:</b> ${fQtd(xmlItem.qCom)} ${xmlItem.uCom} | 
                            <b>Unit:</b> R$ ${fMoeda(xmlItem.vUnCom)} | 
                            <b>Total:</b> R$ ${fMoeda(valorTotal)}
                        </div>
                    </div>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <button onclick="buscarItemParaAdicionar(${index})" 
                            style="background: #375a4b; color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 12px; white-space: nowrap;">
                            <i class="fa fa-plus-circle"></i> ADICIONAR À OC
                        </button>
                        <button onclick="ignorarItemXml(${index})" 
                            style="background: #ef4444; color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 12px; white-space: nowrap;">
                            <i class="fa fa-times-circle"></i> IGNORAR
                        </button>
                    </div>
                </div>
            </div>`;
        });
        
        html += `</div>`;
    }
    
    document.getElementById('corpoItens').innerHTML = html;
    atualizarAuditoria();
    
    const totalMatched = itensTrabalho.filter(i => i.xmlMatch).length;
    const totalNaoMatched = itensTrabalho.filter(i => !i.xmlMatch).length;
    console.log(`📊 Resumo do Matching: ${totalMatched} itens encontrados, ${totalNaoMatched} não encontrados, ${appState.itensXMLNaoMatch.length} itens extras na NF`);
}

// ==========================================================================
// BUSCAR ITEM PARA ADICIONAR À OC
// ==========================================================================


async function buscarItemParaAdicionar(index) {
    const xmlItem = appState.itensXMLNaoMatch[index];
    if (!xmlItem) {
        Swal.fire('Erro', 'Item não encontrado na lista.', 'error');
        return;
    }

    const idFilial = document.getElementById('selFilial').value;
    if (!idFilial) {
        Swal.fire('Erro', 'Selecione uma filial primeiro.', 'warning');
        return;
    }

    // 1. PRIORIDADE 1: Buscar por REFERÊNCIA (cProd)
    const termoRef = xmlItem.codigo ? xmlItem.codigo.trim() : '';
    
    // 2. PRIORIDADE 2: Buscar por EAN (cEAN ou cEANTrib)
    const termoEAN = xmlItem.ean || xmlItem.eanTrib || '';
    const termoLimpoEAN = termoEAN.replace(/\D/g, '');
    
    // 3. PRIORIDADE 3: Buscar por parte do nome (primeiras palavras)
    const nomeBusca = xmlItem.xProd ? xmlItem.xProd.substring(0, 30).trim() : '';

    // Mostrar loading
    Swal.fire({
        title: 'Buscando item...',
        html: `
            <div style="text-align: left; font-size: 13px;">
                <b>Produto:</b> ${xmlItem.xProd || 'N/A'}<br>
                <b>REF:</b> ${xmlItem.codigo || 'N/A'}<br>
                <b>EAN:</b> ${termoEAN || 'N/A'}
            </div>
            <div style="margin-top: 12px; color: #64748b; font-size: 12px;">
                <i class="fa fa-spinner fa-spin"></i> Buscando por referência e EAN...
            </div>
        `,
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });

    try {
        let itensEncontrados = [];
        let termosBuscados = [];

        // =============================================================
        // TENTATIVA 1: Busca por REFERÊNCIA EXATA
        // =============================================================
        if (termoRef) {
            termosBuscados.push(`REF: ${termoRef}`);
            const resp = await fetchWithAuth(`/v1/xml/buscar-item?termo=${encodeURIComponent(termoRef)}&idfilial=${idFilial}`);
            const data = await resp.json();
            if (data && data.length > 0) {
                itensEncontrados = data;
            }
        }

        // =============================================================
        // TENTATIVA 2: Busca por EAN (se não encontrou por REF)
        // =============================================================
        if (itensEncontrados.length === 0 && termoLimpoEAN && termoLimpoEAN.length > 3) {
            termosBuscados.push(`EAN: ${termoLimpoEAN}`);
            const resp = await fetchWithAuth(`/v1/xml/buscar-item?termo=${encodeURIComponent(termoLimpoEAN)}&idfilial=${idFilial}`);
            const data = await resp.json();
            if (data && data.length > 0) {
                itensEncontrados = data;
            }
        }

        // =============================================================
        // TENTATIVA 3: Busca por NOME (se não encontrou por REF ou EAN)
        // =============================================================
        if (itensEncontrados.length === 0 && nomeBusca && nomeBusca.length > 3) {
            termosBuscados.push(`Nome: ${nomeBusca}`);
            const resp = await fetchWithAuth(`/v1/xml/buscar-item?termo=${encodeURIComponent(nomeBusca)}&idfilial=${idFilial}`);
            const data = await resp.json();
            if (data && data.length > 0) {
                itensEncontrados = data;
            }
        }

        Swal.close();

        // =============================================================
        // SE ENCONTROU ITENS -> MOSTRA PARA SELECIONAR
        // =============================================================
        if (itensEncontrados.length > 0) {
            mostrarDialogoSelecionarItem(xmlItem, itensEncontrados, index);
            return;
        }

        // =============================================================
        // NÃO ENCONTROU -> ABRE BUSCA MANUAL COMPLETA
        // =============================================================
        console.log('🔍 Item não encontrado automaticamente. Abrindo busca manual.');
        abrirBuscaManual(xmlItem, index, termosBuscados);

    } catch (e) {
        Swal.close();
        console.error("Erro ao buscar item:", e);
        // Fallback: abrir busca manual
        abrirBuscaManual(xmlItem, index, ['Erro na busca automática']);
    }
}

function abrirBuscaManual(xmlItem, index, termosBuscados = []) {
    const idFilial = document.getElementById('selFilial').value;
    
    // HTML do modal de busca manual
    const htmlModal = `
        <div style="text-align: left;">
            <div style="background: #fef3c7; padding: 12px; border-radius: 8px; margin-bottom: 16px; border-left: 4px solid #f59e0b;">
                <div style="font-weight: 700; color: #92400e; font-size: 13px;">
                    <i class="fa fa-search"></i> Busca Manual
                </div>
                <div style="font-size: 12px; color: #78350f; margin-top: 4px;">
                    ${termosBuscados.length > 0 ? `Buscas realizadas: ${termosBuscados.join(' | ')}` : 'Nenhum resultado encontrado'}
                </div>
            </div>
            
            <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                <div style="font-weight: 600; color: #0f172a; font-size: 13px;">Produto do XML:</div>
                <div style="font-size: 12px; color: #475569; margin-top: 4px;">
                    <b>Nome:</b> ${xmlItem.xProd || 'N/A'}<br>
                    <b>REF:</b> ${xmlItem.codigo || 'N/A'} | 
                    <b>EAN:</b> ${xmlItem.ean || xmlItem.eanTrib || 'N/A'}<br>
                    <b>NCM:</b> ${xmlItem.ncm || 'N/A'} | 
                    <b>Qtd:</b> ${fQtd(xmlItem.qCom)} ${xmlItem.uCom || 'UN'}
                </div>
            </div>
            
            <div style="margin-top: 12px;">
                <label style="font-size: 12px; font-weight: 600; color: #334155; display: block; margin-bottom: 4px;">
                    <i class="fa fa-barcode"></i> Digite a referência, EAN ou descrição:
                </label>
                <div style="display: flex; gap: 8px;">
                    <input id="buscaManualInput" class="swal2-input" 
                           placeholder="Ex: 12345 ou NUTRILIFE ou 789..." 
                           style="flex: 1; padding: 10px; border: 2px solid #d1d5db; border-radius: 8px; font-size: 14px;">
                    <button id="btnBuscarManual" 
                            style="background: #274036; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 700; white-space: nowrap;">
                        <i class="fa fa-search"></i> Buscar
                    </button>
                </div>
            </div>
            
            <div id="resultadoBuscaManual" style="margin-top: 12px; max-height: 250px; overflow-y: auto;">
                <div style="color: #94a3b8; font-size: 12px; text-align: center; padding: 20px 0;">
                    <i class="fa fa-info-circle"></i> Digite um termo e clique em "Buscar"
                </div>
            </div>
        </div>
    `;

    Swal.fire({
        title: '🔍 Buscar Item no Cadastro',
        html: htmlModal,
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Adicionar Selecionado',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        cancelButtonColor: '#ef4444',
        width: '580px',
        preConfirm: () => {
            // Verifica se algum item foi selecionado
            const selected = document.querySelector('.item-busca-selecionado');
            if (!selected) {
                Swal.showValidationMessage('Selecione um item da lista clicando nele.');
                return false;
            }
            const iditem = parseInt(selected.dataset.iditem);
            const referencia = selected.dataset.referencia;
            const descricao = selected.dataset.descricao;
            const fator = parseFloat(selected.dataset.fator) || 1;
            return { iditem, referencia, descricao, fator };
        },
        didOpen: () => {
            // Foco no input
            const input = document.getElementById('buscaManualInput');
            if (input) {
                setTimeout(() => input.focus(), 300);
            }
            
            // Buscar ao clicar no botão
            document.getElementById('btnBuscarManual').onclick = async () => {
                const termo = document.getElementById('buscaManualInput').value.trim();
                if (!termo) {
                    Swal.showValidationMessage('Digite um termo para buscar');
                    return;
                }
                
                try {
                    const resultadoDiv = document.getElementById('resultadoBuscaManual');
                    resultadoDiv.innerHTML = `
                        <div style="text-align: center; padding: 20px; color: #64748b;">
                            <i class="fa fa-spinner fa-spin"></i> Buscando...
                        </div>
                    `;
                    
                    const resp = await fetchWithAuth(`/v1/xml/buscar-item?termo=${encodeURIComponent(termo)}&idfilial=${idFilial}`);
                    const itens = await resp.json();
                    
                    if (!itens || itens.length === 0) {
                        resultadoDiv.innerHTML = `
                            <div style="background: #fef2f2; padding: 12px; border-radius: 8px; color: #991b1b; font-size: 13px; border-left: 4px solid #ef4444;">
                                <i class="fa fa-times-circle"></i> Nenhum item encontrado para "<b>${termo}</b>"
                            </div>
                            <div style="margin-top: 8px; font-size: 11px; color: #64748b;">
                                <i class="fa fa-lightbulb"></i> Dica: Tente buscar pela referência, EAN ou parte do nome.
                            </div>
                        `;
                        return;
                    }
                    
                    let html = `
                        <div style="font-size: 11px; color: #64748b; margin-bottom: 8px;">
                            <b>${itens.length}</b> item(ns) encontrado(s) para "<b>${termo}</b>":
                        </div>
                    `;
                    
                    itens.forEach((item) => {
                        const id = item.iditem;
                        const ref = item.referencia || 'N/A';
                        const desc = item.descricao || 'Sem descrição';
                        const fator = item.fator_conversao || 1;
                        const ean = item.ean_unidade || 'N/A';
                        const ncm = item.ncm || 'N/A';
                        const preco = item.preco_compra || 0;
                        
                        html += `
                            <div class="item-busca-resultado" 
                                 data-iditem="${id}"
                                 data-referencia="${ref.replace(/"/g, '&quot;')}"
                                 data-descricao="${desc.replace(/"/g, '&quot;')}"
                                 data-fator="${fator}"
                                 onclick="selecionarItemBuscaManual(this)"
                                 style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 6px; cursor: pointer; transition: all 0.2s;"
                                 onmouseover="this.style.borderColor='#274036'; this.style.background='#f0fdf4';"
                                 onmouseout="if(!this.classList.contains('item-busca-selecionado')){this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc';}">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">${ref} - ${desc}</div>
                                    <span style="font-size: 10px; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 12px;">ID: ${id}</span>
                                </div>
                                <div style="font-size: 11px; color: #64748b; margin-top: 4px; display: flex; flex-wrap: wrap; gap: 12px;">
                                    <span><b>Fator:</b> ${fator}x</span>
                                    <span><b>EAN:</b> ${ean}</span>
                                    <span><b>NCM:</b> ${ncm}</span>
                                    ${preco > 0 ? `<span style="color: #059669; font-weight: 700;">R$ ${fMoeda(preco)}</span>` : ''}
                                </div>
                            </div>
                        `;
                    });
                    
                    resultadoDiv.innerHTML = html;
                    
                } catch (e) {
                    console.error('Erro na busca manual:', e);
                    document.getElementById('resultadoBuscaManual').innerHTML = `
                        <div style="background: #fef2f2; padding: 12px; border-radius: 8px; color: #991b1b; font-size: 13px; border-left: 4px solid #ef4444;">
                            <i class="fa fa-exclamation-triangle"></i> Erro ao buscar: ${e.message}
                        </div>
                    `;
                }
            };
            
            // Buscar ao pressionar Enter
            document.getElementById('buscaManualInput').addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    document.getElementById('btnBuscarManual').click();
                }
            });
        }
    }).then(async (result) => {
        if (result.isConfirmed && result.value) {
            const { iditem, referencia, descricao, fator } = result.value;
            await confirmarAdicaoItem(index, iditem, referencia, descricao, fator);
        }
    });
}

function selecionarItemBuscaManual(el) {
    // Remove seleção anterior
    document.querySelectorAll('.item-busca-selecionado').forEach(item => {
        item.classList.remove('item-busca-selecionado');
        item.style.borderColor = '#e2e8f0';
        item.style.background = '#f8fafc';
    });
    
    // Seleciona o atual
    el.classList.add('item-busca-selecionado');
    el.style.borderColor = '#274036';
    el.style.background = '#dcfce7';
    
    // Atualiza o botão de confirmação
    const confirmBtn = document.querySelector('.swal2-confirm');
    if (confirmBtn) {
        const ref = el.dataset.referencia || '';
        const desc = el.dataset.descricao || '';
        confirmBtn.innerHTML = `<i class="fa fa-check-circle"></i> Adicionar ${ref} - ${desc.substring(0, 20)}...`;
        confirmBtn.disabled = false;
    }
}


function mostrarDialogoSelecionarItem(xmlItem, itensSistema, index) {
    if (!itensSistema || itensSistema.length === 0) {
        Swal.fire('Item não encontrado', 'Nenhum item correspondente localizado.', 'warning');
        return;
    }
    
    let opcoesHtml = '';
    itensSistema.forEach((item) => {
        const id = item.iditem;
        const ref = item.referencia || 'N/A';
        const desc = item.descricao || 'Sem descrição';
        const fator = item.fator_conversao || 1;
        const ean = item.ean_unidade || 'N/A';
        const ncm = item.ncm || 'N/A';
        const preco = item.preco_compra || 0;
        
        opcoesHtml += `
            <div class="item-busca-resultado" 
                 data-iditem="${id}"
                 data-referencia="${ref.replace(/"/g, '&quot;')}"
                 data-descricao="${desc.replace(/"/g, '&quot;')}"
                 data-fator="${fator}"
                 onclick="selecionarItemBuscaManual(this)"
                 style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;"
                 onmouseover="this.style.borderColor='#274036'; this.style.background='#f0fdf4';"
                 onmouseout="if(!this.classList.contains('item-busca-selecionado')){this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc';}">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 4px;">
                    <div style="font-weight: 700; color: #0f172a; font-size: 13px;">${ref} - ${desc}</div>
                    <span style="font-size: 10px; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 12px;">ID: ${id}</span>
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px; display: flex; flex-wrap: wrap; gap: 12px;">
                    <span><b>Fator:</b> ${fator}x</span>
                    <span><b>EAN:</b> ${ean}</span>
                    <span><b>NCM:</b> ${ncm}</span>
                    ${preco > 0 ? `<span style="color: #059669; font-weight: 700;">R$ ${fMoeda(preco)}</span>` : ''}
                </div>
            </div>
        `;
    });
    
    Swal.fire({
        title: 'Selecione o Item para Adicionar',
        html: `
            <div style="text-align: left;">
                <div style="background: #f8fafc; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                    <div style="font-weight: 600; color: #0f172a; font-size: 13px;">Produto do XML:</div>
                    <div style="font-size: 12px; color: #475569; margin-top: 4px;">
                        <b>Nome:</b> ${xmlItem.xProd || 'N/A'}<br>
                        <b>Qtd:</b> ${fQtd(xmlItem.qCom)} ${xmlItem.uCom || 'UN'} | 
                        <b>Valor Unit:</b> R$ ${fMoeda(xmlItem.vUnCom)}
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto;">${opcoesHtml}</div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 8px; text-align: center;">
                    <i class="fa fa-hand-pointer"></i> Clique em um item para selecionar
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Adicionar Selecionado',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#274036',
        cancelButtonColor: '#ef4444',
        width: '600px',
        preConfirm: () => {
            const selected = document.querySelector('.item-busca-selecionado');
            if (!selected) {
                Swal.showValidationMessage('Selecione um item da lista clicando nele.');
                return false;
            }
            const iditem = parseInt(selected.dataset.iditem);
            const referencia = selected.dataset.referencia;
            const descricao = selected.dataset.descricao;
            const fator = parseFloat(selected.dataset.fator) || 1;
            return { iditem, referencia, descricao, fator };
        }
    }).then(async (result) => {
        if (result.isConfirmed && result.value) {
            const { iditem, referencia, descricao, fator } = result.value;
            await confirmarAdicaoItem(index, iditem, referencia, descricao, fator);
        }
    });
}

// ==========================================================================
// CONFIRMAR ADIÇÃO DE ITEM - VERSÃO CORRIGIDA
// ==========================================================================

async function confirmarAdicaoItem(index, iditem, referencia, descricao, fatorConversao) {
    const xmlItem = appState.itensXMLNaoMatch[index];
    if (!xmlItem) {
        Swal.fire('Erro', 'Item do XML não encontrado.', 'error');
        return;
    }

    const fatorSeguro = (fatorConversao && fatorConversao > 0) ? fatorConversao : 1;
    
    // 🔥 CORREÇÃO: Usar a quantidade do XML multiplicada pelo fator
    const quantidade = xmlItem.qCom * fatorSeguro;
    const valorUnitario = xmlItem.vUnCom / fatorSeguro;
    
    // 🔥 CORREÇÃO: Garantir que os valores são números válidos
    if (isNaN(quantidade) || quantidade <= 0) {
        Swal.fire('Erro', 'Quantidade inválida para adicionar.', 'error');
        return;
    }
    
    if (isNaN(valorUnitario) || valorUnitario <= 0) {
        Swal.fire('Erro', 'Valor unitário inválido para adicionar.', 'error');
        return;
    }

    // Mostrar confirmação
    const result = await Swal.fire({
        title: 'Confirmar Adição',
        html: `
            <div style="text-align: left; background: #f8fafc; padding: 16px; border-radius: 12px;">
                <div style="font-weight: 700; color: #0f172a; font-size: 14px; margin-bottom: 8px;">
                    <i class="fa fa-plus-circle" style="color: #274036;"></i> Adicionar à OC #${appState.idoc}
                </div>
                <hr style="margin: 8px 0; border-color: #e2e8f0;">
                <div><b>Item:</b> ${referencia} - ${descricao}</div>
                <div><b>Quantidade:</b> ${fQtd(quantidade)}</div>
                <div><b>Valor Unitário:</b> R$ ${fMoeda(valorUnitario)}</div>
                <div style="font-weight: 700; color: #059669;">
                    <b>Valor Total:</b> R$ ${fMoeda(quantidade * valorUnitario)}
                </div>
                <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                    <b>Fator Conversão:</b> ${fatorSeguro}x
                </div>
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Adicionar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#375a4b',
        cancelButtonColor: '#ef4444'
    });

    if (!result.isConfirmed) return;

    // Mostrar loading
    Swal.fire({
        title: 'Adicionando item...',
        text: 'Aguarde enquanto o item é adicionado à OC.',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });

    try {
        const resp = await fetchWithAuth('/v1/xml/adicionar-item', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                idoc: appState.idoc,
                iditem: iditem,
                quantidade: quantidade,
                valor_unitario: valorUnitario
            })
        });

        const data = await resp.json();

        Swal.close();

        if (data.success) {
            // Remover o item da lista de não-match
            appState.itensXMLNaoMatch.splice(index, 1);
            
            // Adicionar ao estado de itens adicionados
            appState.itensParaAdicionar.push({
                iditem: iditem,
                quantidade: quantidade,
                valor_unitario: valorUnitario
            });

            // Recarregar a OC para mostrar o novo item
            await recarregarDadosOC();

            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: '✅ Item adicionado com sucesso!',
                showConfirmButton: false,
                timer: 2000
            });
        } else {
            Swal.fire('Erro', data.error || 'Falha ao adicionar item.', 'error');
        }
    } catch (e) {
        Swal.close();
        console.error("Erro ao adicionar item:", e);
        Swal.fire('Erro', 'Falha ao adicionar item à OC. Tente novamente.', 'error');
    }
}

// ==========================================================================
// FUNÇÃO PARA SELECIONAR ITEM DA BUSCA MANUAL
// ==========================================================================

function selecionarItemDaBusca(index, iditem, referencia, descricao, fatorConversao) {
    const xmlItem = appState.itensXMLNaoMatch[index];
    
    // Fechar o Swal atual
    Swal.close();
    
    // Calcular quantidade e valor
    const fatorSeguro = (fatorConversao && fatorConversao > 0) ? fatorConversao : 1;
    const quantidade = xmlItem.qCom * fatorSeguro;
    const valorUnitario = xmlItem.vUnCom / fatorSeguro;
    
    // Confirmar adição
    Swal.fire({
        title: 'Confirmar Adição',
        html: `
            <div style="text-align: left; background: #f8fafc; padding: 16px; border-radius: 12px;">
                <b>Item selecionado:</b> ${referencia} - ${descricao}<br>
                <b>Quantidade:</b> ${fQtd(quantidade)}<br>
                <b>Valor Unitário:</b> R$ ${fMoeda(valorUnitario)}<br>
                <b>Valor Total:</b> R$ ${fMoeda(quantidade * valorUnitario)}<br>
                <b>Fator Conversão:</b> ${fatorSeguro}x
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Adicionar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#375a4b'
    }).then(async (result) => {
        if (result.isConfirmed) {
            await confirmarAdicaoItem(index, iditem, referencia, descricao, fatorConversao);
        }
    });
}




function mostrarDialogoAdicionarItem(xmlItem, itensSistema, index) {
    if (!itensSistema || itensSistema.length === 0) {
        Swal.fire('Item não encontrado', 'Nenhum item correspondente localizado.', 'warning');
        return;
    }
    let opcoesHtml = '';
    itensSistema.forEach((item, i) => {
        opcoesHtml += `
        <div style="background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px; margin-bottom: 8px; cursor: pointer; transition: all 0.2s;"
             onclick="confirmarAdicaoItem(${index}, ${item.iditem}, '${item.referencia?.replace(/'/g, "\\'") || ''}', '${item.descricao?.replace(/'/g, "\\'")?.substring(0, 30) || ''}', ${item.fator_conversao || 1})"
             onmouseover="this.style.borderColor='#375a4b'; this.style.background='#f0fdf4';"
             onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f8fafc';">
            <div style="font-weight: 700; color: #1e293b;">${item.referencia} - ${item.descricao}</div>
            <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                EAN: ${item.ean_unidade || 'N/A'} | NCM: ${item.ncm || 'N/A'} | Fator: ${item.fator_conversao || 1}x
            </div>
            ${item.preco_compra > 0 ? `<div style="font-size: 11px; color: #059669; font-weight: 700;">Preço Compra: R$ ${fMoeda(item.preco_compra)}</div>` : ''}
        </div>`;
    });
    Swal.fire({
        title: 'Selecione o Item para Adicionar',
        html: `
            <div style="text-align: left; margin-bottom: 12px;">
                <b>Produto no XML:</b> ${xmlItem.xProd}<br>
                <b>Qtd no XML:</b> ${fQtd(xmlItem.qCom)} ${xmlItem.uCom}<br>
                <b>Valor Unit:</b> R$ ${fMoeda(xmlItem.vUnCom)}
            </div>
            <div style="max-height: 300px; overflow-y: auto;">${opcoesHtml}</div>
        `,
        showCancelButton: true,
        cancelButtonText: 'Cancelar',
        showConfirmButton: false,
        width: '600px'
    });
}

async function confirmarAdicaoItem(index, iditem, referencia, descricao, fatorConversao) {
    const xmlItem = appState.itensXMLNaoMatch[index];
    const fatorSeguro = (fatorConversao && fatorConversao > 0) ? fatorConversao : 1;
    const quantidade = xmlItem.qCom * fatorSeguro;
    const valorUnitario = xmlItem.vUnCom / fatorSeguro;
    Swal.close();
    const result = await Swal.fire({
        title: 'Confirmar Adição',
        html: `
            <div style="text-align: left; background: #f8fafc; padding: 16px; border-radius: 12px;">
                <b>Item:</b> ${referencia} - ${descricao}<br>
                <b>Quantidade:</b> ${fQtd(quantidade)}<br>
                <b>Valor Unitário:</b> R$ ${fMoeda(valorUnitario)}<br>
                <b>Valor Total:</b> R$ ${fMoeda(quantidade * valorUnitario)}<br>
                <b>Fator Conversão:</b> ${fatorSeguro}x
            </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Adicionar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#375a4b'
    });
    if (result.isConfirmed) {
        try {
            const resp = await fetchWithAuth('/v1/xml/adicionar-item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    idoc: appState.idoc,
                    iditem: iditem,
                    quantidade: quantidade,
                    valor_unitario: valorUnitario
                })
            });
            const data = await resp.json();
            if (data.success) {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Item adicionado à OC!',
                    showConfirmButton: false,
                    timer: 2000
                });
                appState.itensParaAdicionar.push({
                    iditem: iditem,
                    quantidade: quantidade,
                    valor_unitario: valorUnitario
                });
                await recarregarDadosOC();
            } else {
                Swal.fire('Erro', data.error || 'Falha ao adicionar item.', 'error');
            }
        } catch (e) {
            console.error("Erro ao adicionar item:", e);
            Swal.fire('Erro', 'Falha ao adicionar item à OC.', 'error');
        }
    }
}

async function recarregarDadosOC() {
    try {
        // Buscar dados atualizados da OC
        const resp = await fetchWithAuth(`/v1/xml/consulta-oc/${appState.idoc}`);
        if (!resp.ok) throw new Error('Erro ao buscar dados da OC');
        
        const itensAtualizados = await resp.json();
        appState.itensDaOC = itensAtualizados;
        
        // Atualizar total da OC
        const totalOC = itensAtualizados.reduce((sum, item) => {
            return sum + (parseFloat(item.qcom || 0) * parseFloat(item.cuncom || 0));
        }, 0);
        document.getElementById('val-total-oc').innerText = 'R$ ' + fMoeda(totalOC);
        
        // Backup dos estados
        const backupAdicionar = [...appState.itensParaAdicionar];
        const backupDeletar = [...appState.itensParaDeletar];
        
        // Re-renderizar com os dados atuais
        if (appState.nota) {
            const resp2 = await fetchWithAuth(`/v1/xml/itens-xml?chave=${appState.nota.chave}`);
            const xmlText = await resp2.text();
            const itensXML = extrairItensXML(xmlText);
            renderizar(itensXML);
            
            // Restaurar estados
            appState.itensParaAdicionar = backupAdicionar;
            appState.itensParaDeletar = backupDeletar;
            
            if (appState.itensParaDeletar.length > 0) {
                document.getElementById('btnDesfazerExclusao').style.display = 'flex';
                document.getElementById('countExcluidos').innerText = appState.itensParaDeletar.length;
            }
        }
        
        console.log('✅ OC recarregada com sucesso');
    } catch (e) {
        console.error("Erro ao recarregar OC:", e);
        Swal.fire('Aviso', 'Falha ao atualizar a lista de itens. Recarregue a página.', 'warning');
    }
}
// ==========================================================================
// FUNÇÕES AUXILIARES
// ==========================================================================

function atualizarAuditoria() {
    const cards = document.querySelectorAll('.item-card');
    const divergentes = document.querySelectorAll('.item-card.divergente');
    if (cards.length === 0 && appState.itensXMLNaoMatch.length === 0) return;
    const total = cards.length;
    const qtdErros = divergentes.length + appState.itensXMLNaoMatch.length;
    const percentualErro = total > 0 ? (qtdErros / (total + appState.itensXMLNaoMatch.length)) * 100 : 0;
    const alertDiv = document.getElementById('auditAlert');
    if (alertDiv) {
        alertDiv.style.display = percentualErro >= 52.0 ? 'flex' : 'none';
    }
    const btnFinalizar = document.getElementById('btnFinalizar');
    if (btnFinalizar) {
        if (qtdErros > 0) {
            btnFinalizar.style.background = '#f59e0b';
            btnFinalizar.style.color = '#000';
            btnFinalizar.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> VERIFICAR DIVERGÊNCIAS (${qtdErros})`;
        } else {
            btnFinalizar.style.background = '#274036';
            btnFinalizar.style.color = '#fff';
            btnFinalizar.innerHTML = '<i class="fa-solid fa-circle-check"></i> FINALIZAR CONFERÊNCIA';
        }
    }
    appState.percentualErro = percentualErro;
    appState.totalDivergentes = qtdErros;
}

function validarLinha(input) {
    const id = input.dataset.id;
    const card = document.getElementById(`row-${id}`);
    if (!card) return;
    const qEsperada = parseFloat(card.dataset.qtd || 0);
    const qDigitada = parseFloat(input.value || 0);
    if (Math.abs(qDigitada - qEsperada) < 0.001) {
        card.classList.remove('divergente');
        card.classList.add('ok');
        card.style.borderLeftColor = '#38a169';
        input.style.borderColor = '#38a169';
        input.style.color = '#166534';
        const badge = card.querySelector('div[style*="text-align:center"]:last-child span');
        if (badge) {
            badge.style.color = '#15803d';
            badge.style.borderColor = '#bbf7d0';
            badge.style.background = '#f0fdf4';
            badge.innerText = 'CONFERIDO';
        }
    } else {
        card.classList.add('divergente');
        card.classList.remove('ok');
        card.style.borderLeftColor = '#e53e3e';
        input.style.borderColor = '#ef4444';
        input.style.color = '#b91c1c';
    }
    atualizarAuditoria();
}

async function removerItemOC(id) {
    const card = document.getElementById(`row-${id}`);
    if (!card) return;
    const nome = card.dataset.nome || "Produto";
    const qtd = parseFloat(card.dataset.qtd || 0);
    const valorUnit = parseFloat(card.dataset.valorUnit || 0);
    const totalItem = qtd * valorUnit;
    const result = await Swal.fire({
        title: 'Retirar item da OC?',
        html: `<div style="text-align: left;">
            <b>REF:</b> ${card.dataset.referencia || 'N/A'}<br>
            <b>Produto:</b> ${nome}<br>
            <b>Qtd:</b> ${qtd.toLocaleString('pt-BR')}<br>
            <b>Total:</b> R$ ${fMoeda(totalItem)}
        </div>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Sim, retirar',
        cancelButtonText: 'Manter'
    });
    if (result.isConfirmed) {
        appState.itensParaDeletar.push({ id: id, nome: nome });
        try {
            await fetchWithAuth('/v1/xml/deletar-item', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ idoc: appState.idoc, iditem: id })
            });
        } catch (e) {
            console.error("Erro ao deletar item:", e);
        }
        card.style.display = 'none';
        document.getElementById('btnDesfazerExclusao').style.display = 'flex';
        document.getElementById('countExcluidos').innerText = appState.itensParaDeletar.length;
        const elTotalOC = document.getElementById('val-total-oc');
        const valorAtualOC = parseFloat(elTotalOC?.innerText.replace(/[^\d,-]/g, '').replace(',', '.') || 0);
        elTotalOC.innerText = 'R$ ' + fMoeda(valorAtualOC - totalItem);
        atualizarAuditoria();
        Swal.fire({ toast: true, position: 'top-end', icon: 'info', title: 'Item removido', showConfirmButton: false, timer: 1500 });
    }
}

function desfazerExclusoes() {
    if (!appState.itensParaDeletar.length) return;
    appState.itensParaDeletar.forEach(item => {
        const card = document.getElementById(`row-${item.id}`);
        if (card) {
            card.style.display = '';
            card.classList.add('divergente');
        }
    });
    appState.itensParaDeletar = [];
    document.getElementById('btnDesfazerExclusao').style.display = 'none';
    document.getElementById('countExcluidos').innerText = '0';
    atualizarAuditoria();
    Swal.fire('Restaurados', 'Os itens voltaram para a lista. Nota: as exclusões já foram enviadas ao banco.', 'info');
}

function copiarChaveAcesso() {
    const chave = document.getElementById('txt-chave-nf').innerText.replace('CHAVE: ', '').replace('ARQUIVO: ', '');
    navigator.clipboard?.writeText(chave);
    showToast('Chave copiada!', 'info');
}

function ignorarItemXml(index) {
    appState.itensXMLNaoMatch.splice(index, 1);
    renderizar(appState.itensXMLNaoMatch);
    showToast('Item ignorado!', 'info');
}

// ==========================================================================
// IMPORTAÇÃO MANUAL DE XML - SUPORTE A MÚLTIPLOS ARQUIVOS
// ==========================================================================

// Lista de arquivos XML selecionados
let arquivosXMLManual = [];

/**
 * Importa múltiplos arquivos XML manualmente
 */
window.importarXMLManual = function(input) {
    if (!input.files || !input.files.length) {
        Swal.fire('Atenção', 'Selecione pelo menos um arquivo XML.', 'warning');
        return;
    }
    
    // Adicionar arquivos à lista
    for (let file of input.files) {
        // Verificar extensão
        if (!file.name.toLowerCase().endsWith('.xml')) {
            Swal.fire('Aviso', "O arquivo '${file.name}' não é um XML válido. Ignorado.", 'warning');
            continue;
        }
        arquivosXMLManual.push(file);
    }
    
    if (arquivosXMLManual.length === 0) {
        Swal.fire('Atenção', 'Nenhum arquivo XML válido selecionado.', 'warning');
        return;
    }
    
    // Mostrar modal com lista de arquivos
    Swal.fire({
        title: `📄 ${arquivosXMLManual.length} arquivo(s) selecionado(s)`,
        html: `
            <div style="text-align:left; max-height:200px; overflow-y:auto; margin-bottom:16px;">
                ${arquivosXMLManual.map((f, i) => `<div style="padding:6px 0; border-bottom:1px solid #f1f5f9; display:flex; justify-content:space-between; align-items:center;">
                    <span><i class="fa-regular fa-file-code" style="color:#f59e0b;"></i> ${f.name}</span>
                    <span style="font-size:11px; color:#94a3b8;">${(f.size / 1024).toFixed(1)} KB</span>
                </div>`).join('')}
            </div>
            <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                <button id="btnProcessarXMLs" class="swal2-confirm swal2-styled" style="background:#375a4b; padding:12px 24px;">
                    <i class="fa fa-file-invoice"></i> PROCESSAR TODOS
                </button>
                <button id="btnLimparXMLs" class="swal2-cancel swal2-styled" style="background:#ef4444; padding:12px 24px;">
                    <i class="fa fa-times"></i> LIMPAR LISTA
                </button>
                <button id="btnAdicionarMaisXMLs" class="swal2-styled" style="background:#3b82f6; padding:12px 24px;">
                    <i class="fa fa-plus"></i> ADICIONAR MAIS
                </button>
            </div>
            <div style="margin-top:12px; font-size:11px; color:#94a3b8; text-align:center;">
                <i class="fa-regular fa-circle-info"></i> Os itens de todas as notas serão combinados automaticamente
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Fechar',
        width: '500px',
        didOpen: () => {
            // Botão PROCESSAR
            document.getElementById('btnProcessarXMLs').onclick = () => {
                Swal.close();
                processarXMLsManual();
            };
            
            // Botão LIMPAR
            document.getElementById('btnLimparXMLs').onclick = () => {
                arquivosXMLManual = [];
                document.getElementById('fileXml').value = '';
                Swal.close();
                showToast('Arquivos removidos', 'info');
            };
            
            // Botão ADICIONAR MAIS
            document.getElementById('btnAdicionarMaisXMLs').onclick = () => {
                Swal.close();
                // Abrir seletor de arquivos novamente
                setTimeout(() => {
                    document.getElementById('fileXml').click();
                }, 300);
            };
        }
    });
};

/**
 * Processa todos os XMLs da lista manual
 */
function processarXMLsManual() {
    if (arquivosXMLManual.length === 0) {
        Swal.fire('Atenção', 'Nenhum arquivo para processar.', 'warning');
        return;
    }
    
    Swal.fire({
        title: '⏳ Processando XMLs...',
        html: `Carregando ${arquivosXMLManual.length} arquivo(s)...`,
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false,
        showConfirmButton: false
    });
    
    let todosItens = [];
    let totalNotas = 0;
    let arquivosProcessados = 0;
    let arquivosComErro = [];
    
    arquivosXMLManual.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const xmlText = e.target.result.trim();
                const parser = new DOMParser();
                const docXML = parser.parseFromString(xmlText, "text/xml");
                
                // Verificar se o XML é válido
                const errorNodes = docXML.getElementsByTagName('parsererror');
                if (errorNodes.length > 0) {
                    throw new Error('XML inválido');
                }
                
                // Extrair valor total da nota
                const tagValorNF = docXML.querySelector('vNF');
                if (tagValorNF) {
                    totalNotas += parseFloat(tagValorNF.textContent || 0);
                }
                
                // Extrair itens
                const itens = extrairItensXML(xmlText);
                if (itens.length > 0) {
                    todosItens = todosItens.concat(itens);
                } else {
                    arquivosComErro.push(file.name);
                }
                
                arquivosProcessados++;
                
                // Atualizar progresso
                if (arquivosProcessados < arquivosXMLManual.length) {
                    Swal.update({
                        html: `Processando ${arquivosProcessados} de ${arquivosXMLManual.length}...<br>
                               <span style="font-size:12px; color:#64748b;">${file.name}</span>`
                    });
                }
                
                // Finalizar quando todos forem processados
                if (arquivosProcessados === arquivosXMLManual.length) {
                    finalizarImportacaoManual(todosItens, totalNotas, arquivosComErro);
                }
                
            } catch (err) {
                console.error("Erro no arquivo:", file.name, err);
                arquivosComErro.push(file.name);
                arquivosProcessados++;
                
                if (arquivosProcessados === arquivosXMLManual.length) {
                    finalizarImportacaoManual(todosItens, totalNotas, arquivosComErro);
                }
            }
        };
        reader.onerror = function() {
            arquivosComErro.push(file.name);
            arquivosProcessados++;
            if (arquivosProcessados === arquivosXMLManual.length) {
                finalizarImportacaoManual(todosItens, totalNotas, arquivosComErro);
            }
        };
        reader.readAsText(file);
    });
}

/**
 * Finaliza a importação manual com os itens extraídos
 */
function finalizarImportacaoManual(itens, totalNotas, arquivosComErro = []) {
    Swal.close();
    
    // Verificar erros
    if (arquivosComErro.length > 0) {
        console.warn('⚠️ Arquivos com erro:', arquivosComErro);
    }
    
    // Verificar se encontrou itens
    if (!itens.length) {
        Swal.fire({
            icon: 'error',
            title: 'Nenhum item encontrado',
            html: `
                <div style="text-align:left;">
                    <p>Não foi possível extrair produtos dos arquivos.</p>
                    ${arquivosComErro.length > 0 ? `<p style="font-size:12px; color:#991b1b;">Arquivos com erro: ${arquivosComErro.join(', ')}</p>` : ''}
                    <p style="font-size:12px; color:#64748b; margin-top:8px;">Verifique se os XMLs são válidos.</p>
                </div>
            `,
            confirmButtonColor: '#ef4444'
        });
        return;
    }
    
    // Agrupar itens
    const itensAgrupados = agruparItensXML(itens);
    
    // Atualizar interface
    const totalNotasFormatado = 'R$ ' + fMoeda(totalNotas);
    document.getElementById('val-total-xml').innerText = totalNotasFormatado + ' (Manual - ' + arquivosXMLManual.length + ' arquivos)';
    document.getElementById('txt-chave-nf').innerText = 'ARQUIVOS: ' + arquivosXMLManual.map(f => f.name).join(', ');
    document.getElementById('placeholder').style.display = 'none';
    document.getElementById('painelConferencia').style.display = 'block';
    
    // Renderizar itens
    renderizar(itensAgrupados);
    
    // Mostrar resumo
    let msgHtml = `
        <div style="text-align:left;">
            <p><b>📄 Arquivos processados:</b> ${arquivosXMLManual.length}</p>
            <p><b>📦 Itens totais (XML):</b> ${itens.length}</p>
            <p><b>📊 Itens agrupados:</b> ${itensAgrupados.length}</p>
            <p><b>💰 Total NF:</b> ${totalNotasFormatado}</p>
        </div>
    `;
    
    if (arquivosComErro.length > 0) {
        msgHtml += `
            <div style="margin-top:12px; padding:8px; background:#fef2f2; border-radius:8px; border-left:3px solid #ef4444;">
                <p style="font-size:11px; color:#991b1b;">
                    <i class="fa-solid fa-triangle-exclamation"></i> 
                    Arquivos com erro: ${arquivosComErro.join(', ')}
                </p>
            </div>
        `;
    }
    
    Swal.fire({
        icon: 'success',
        title: '✅ XMLs Importados!',
        html: msgHtml,
        timer: 4000,
        showConfirmButton: true,
        confirmButtonText: 'OK',
        confirmButtonColor: '#375a4b',
        toast: true,
        position: 'top-end'
    });
    
    // Limpar lista de arquivos
    arquivosXMLManual = [];
    document.getElementById('fileXml').value = '';
}

/**
 * Limpa a lista de XMLs manuais
 */
window.limparXMLsManual = function() {
    arquivosXMLManual = [];
    document.getElementById('fileXml').value = '';
    showToast('Arquivos limpos', 'info');
};
// ==========================================================================
// FINALIZAR CONFERÊNCIA
// ==========================================================================

async function finalizarSincronizacao() {
    const divergentes = document.querySelectorAll('.item-card.divergente:not([style*="display: none"])');
    const inputsVazios = Array.from(divergentes).some(d => {
        const input = d.querySelector('.qtd-input');
        return input && input.type !== 'hidden' && input.value === '';
    });
    if (inputsVazios) {
        Swal.fire({
            title: 'Campos Vazios',
            text: 'Por favor, preencha as quantidades conferidas em todos os itens divergentes.',
            icon: 'warning',
            confirmButtonColor: '#274036'
        });
        return;
    }
    const totalDivergencias = divergentes.length + appState.itensXMLNaoMatch.length;
    let divergenciaTexto = '';
    if (totalDivergencias > 0) {
        divergenciaTexto = `
            <div style="background: #fef2f2; padding: 12px; border-radius: 8px; margin-bottom: 16px;">
                <i class="fa-solid fa-triangle-exclamation" style="color: #dc2626;"></i>
                <span style="color: #991b1b; font-weight: 600;">Divergências Detectadas!</span>
                <p style="font-size: 12px; color: #7f1d1d; margin-top: 4px;">
                    ${divergentes.length} itens com diferenças | 
                    ${appState.itensXMLNaoMatch.length} itens do XML não adicionados
                </p>
            </div>
        `;
    }
    const result = await Swal.fire({
        title: 'Conferência de Carga',
        html: `
            <div style="text-align: left;">
                ${divergenciaTexto}
                <p class="text-sm text-slate-600 mb-4">Escolha a ação desejada:</p>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <button id="btnPdf" class="action-btn" style="background: #3b82f6; color: white; padding: 12px; border: none; border-radius: 12px; cursor: pointer; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-regular fa-file-pdf"></i> GERAR RELATÓRIO PDF
                    </button>
                    <button id="btnEmail" class="action-btn" style="background: #f59e0b; color: white; padding: 12px; border: none; border-radius: 12px; cursor: pointer; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-regular fa-envelope"></i> ENVIAR POR EMAIL
                    </button>
                    <button id="btnSalvar" class="action-btn" style="background: #10b981; color: white; padding: 12px; border: none; border-radius: 12px; cursor: pointer; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa-solid fa-save"></i> SALVAR NO BANCO
                    </button>
                </div>
                <p class="text-xs text-slate-400 mt-4 text-center">
                    <i class="fa-regular fa-clock"></i> As ações são independentes. Nada é salvo automaticamente.
                </p>
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Fechar',
        cancelButtonColor: '#64748b',
        width: '450px',
        didOpen: () => {
            document.getElementById('btnPdf').onclick = async () => {
                Swal.close();
                await acaoApenasPdf();
            };
            document.getElementById('btnEmail').onclick = async () => {
                Swal.close();
                await acaoApenasEmail();
            };
            document.getElementById('btnSalvar').onclick = async () => {
                Swal.close();
                await acaoApenasSalvar();
            };
        }
    });
}

// ==========================================================================
// AÇÃO 1: APENAS PDF
// ==========================================================================

async function acaoApenasPdf() {
    Swal.fire({
        title: 'Gerando PDF...',
        text: 'Aguarde enquanto o relatório é gerado.',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    try {
        await gerarRelatorioPDF('abrir');
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'PDF Gerado!',
            text: 'O relatório foi aberto em uma nova aba.',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    } catch (error) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Falha ao gerar o PDF: ' + error.message,
            confirmButtonColor: '#ef4444'
        });
    }
}

// ==========================================================================
// GERAR RELATÓRIO PDF - VERSÃO CORRIGIDA COM EXTRAÇÃO ROBUSTA
// ==========================================================================

async function gerarRelatorioPDF(destino) {
    const { jsPDF } = window.jspdf;
    if (!jsPDF) {
        Swal.fire('Erro', 'Biblioteca jsPDF não encontrada.', 'error');
        return;
    }

    const doc = new jsPDF('p', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    const margin = 15;
    let yPos = 20;

    // --- CABEÇALHO ---
    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.text(`Relatório de Divergências OC #${appState.idoc || '---'}`, margin, yPos);
    yPos += 8;

    doc.setFontSize(9);
    doc.setTextColor(71, 85, 105);
    doc.setFont("helvetica", "normal");
    const nomeForn = appState.fornecedorNome || "Não identificado";
    doc.text(`Fornecedor: ${nomeForn}`, margin, yPos);
    yPos += 5;

    const selOC = document.getElementById('selOC');
    const dataOC = selOC?.options[selOC.selectedIndex]?.dataset.data || "---";
    const dataNF = appState.nota?.emissao || "---";
    doc.text(`Emissão OC: ${dataOC}  |  Emissão NF: ${dataNF}`, margin, yPos);
    yPos += 7;

    // Totais
    doc.setFillColor(241, 245, 249);
    doc.roundedRect(pageWidth - 55, 15, 45, 20, 2, 2, 'F');
    doc.setFontSize(8);
    doc.text("TOTAL OC:", pageWidth - 50, 22);
    doc.text("TOTAL NF:", pageWidth - 50, 30);
    doc.setFont("helvetica", "bold");
    doc.text(document.getElementById('val-total-oc')?.innerText || "R$ 0,00", pageWidth - 30, 22);
    doc.text(`R$ ${fMoeda(appState.nota?.valor || 0)}`, pageWidth - 30, 30);
    yPos += 5;

    // --- ITENS DIVERGENTES ---
    const itensDivergentes = Array.from(document.querySelectorAll('.item-card')).filter(card => 
        card.classList.contains('divergente') && card.style.display !== 'none'
    );

    if (itensDivergentes.length === 0) {
        doc.text("Nenhum item divergente encontrado.", margin, yPos);
    } else {
        for (const card of itensDivergentes) {
            // Verifica quebra de página
            if (yPos > 260) {
                doc.addPage();
                yPos = 20;
            }

            // Box do item
            doc.setDrawColor(226, 232, 240);
            doc.setFillColor(255, 255, 255);
            doc.roundedRect(margin, yPos, pageWidth - 2 * margin, 50, 2, 2, 'FD');
            doc.setFillColor(239, 68, 68);
            doc.rect(margin, yPos, 2, 50, 'F');

            // ID e Referência
            doc.setTextColor(100, 116, 139);
            doc.setFontSize(7);
            doc.setFont("helvetica", "normal");
            const idItem = card.dataset.id || "";
            const refTexto = card.dataset.referencia || "REF N/A";
            doc.text(`ID: ${idItem} | ${refTexto}`, margin + 5, yPos + 6);

            // Nome do Produto
            doc.setTextColor(30, 41, 59);
            doc.setFontSize(9);
            doc.setFont("helvetica", "bold");
            const nomeProd = card.dataset.nome || "Produto";
            const nomeLines = doc.splitTextToSize(nomeProd, pageWidth - 2 * margin - 20);
            doc.text(nomeLines, margin + 5, yPos + 12);

            // --- Extrair dados do card ---
            // Valor unitário OC
            const vUnitOC = parseFloat(card.dataset.valorUnit || 0);
            // Quantidade OC
            const qtdOC = parseFloat(card.dataset.qtd || 0);

            // --- Extrair valor unitário NOTA e unidade ---
            let valorNota = 'R$ ---';
            let unidadeNota = 'UN';
            const infoPreco = card.querySelector('.info-precos-auditoria');
            if (infoPreco) {
                let texto = infoPreco.innerText.trim();
                // Remove "NOTA:" e ícones
                texto = texto.replace(/NOTA:/i, '').trim();
                // Remove ícones do Font Awesome (se houver)
                texto = texto.replace(/[^\w\s.,$%()]/g, '').trim();
                // Extrai valor
                const matchValor = texto.match(/R\$\s*([\d.,]+)/);
                if (matchValor) valorNota = 'R$ ' + matchValor[1];
                // Extrai unidade entre parênteses
                const matchUnidade = texto.match(/\(([^)]+)\)/);
                if (matchUnidade) unidadeNota = matchUnidade[1];
            }

            // --- Quantidades do XML ---
            let qtdXMLConvertida = 0;
            let qtdXMLOriginal = 0;
            let unidadeXML = 'UN';
            let fator = 1;

            const xmlSpan = card.querySelector('.valor-xml-destaque');
            if (xmlSpan) {
                const qtdTexto = xmlSpan.innerText.trim();
                const matchQtd = qtdTexto.match(/[\d.,]+/);
                if (matchQtd) {
                    qtdXMLConvertida = parseFloat(matchQtd[0].replace(/\./g, '').replace(',', '.'));
                }
                const convDiv = xmlSpan.parentElement.querySelector('div[style*="font-size: 10px"]');
                if (convDiv) {
                    const convText = convDiv.innerText;
                    const matchOriginal = convText.match(/^([\d.,]+)\s*\(([^)]+)\)/);
                    if (matchOriginal) {
                        qtdXMLOriginal = parseFloat(matchOriginal[1].replace(/\./g, '').replace(',', '.'));
                        unidadeXML = matchOriginal[2];
                    }
                    const matchFator = convText.match(/×\s*([\d.,]+)/);
                    if (matchFator) {
                        fator = parseFloat(matchFator[1].replace(/\./g, '').replace(',', '.'));
                    }
                }
            }
            if (qtdXMLOriginal === 0) {
                qtdXMLOriginal = qtdXMLConvertida;
                unidadeXML = 'UN';
            }

            // Quantidade conferida
            let qtdConferida = qtdXMLConvertida;
            const qtdInput = card.querySelector('.qtd-input');
            if (qtdInput && qtdInput.type !== 'hidden' && qtdInput.value) {
                qtdConferida = parseFloat(qtdInput.value) || qtdXMLConvertida;
            }

            // --- Posicionamento dos dados ---
            let innerY = yPos + 20;

            // Valor Unit. OC
            doc.setFontSize(8);
            doc.setFont("helvetica", "normal");
            doc.setTextColor(71, 85, 105);
            doc.text(`Valor Unit. OC: R$ ${vUnitOC.toFixed(2).replace('.', ',')} (UN)`, margin + 5, innerY);

            // Valor Unit. NOTA
            doc.setTextColor(220, 38, 38);
            doc.setFont("helvetica", "bold");
            doc.text(`Valor Unit. NOTA: ${valorNota} (${unidadeNota})`, margin + 80, innerY);
            innerY += 6;

            // Conversão de quantidade
            if (fator > 1 && unidadeXML !== 'UN') {
                doc.setFontSize(7);
                doc.setTextColor(100, 116, 139);
                doc.setFont("helvetica", "normal");
                const conversaoText = `${fQtd(qtdXMLOriginal)} ${unidadeXML} × ${fator} = ${fQtd(qtdXMLOriginal * fator)} UN`;
                doc.text(`Conversão: ${conversaoText}`, margin + 5, innerY);
                innerY += 5;
                const diff = Math.abs(qtdOC - qtdXMLOriginal * fator);
                if (diff > 0.009) {
                    doc.setTextColor(220, 38, 38);
                    doc.text(`Diferença: ${fQtd(diff)} UN`, margin + 5, innerY);
                    innerY += 5;
                }
            } else {
                doc.setFontSize(7);
                doc.setTextColor(100, 116, 139);
                doc.setFont("helvetica", "normal");
                doc.text(`OC: ${fQtd(qtdOC)} UN | XML: ${fQtd(qtdXMLConvertida)} ${unidadeXML}`, margin + 5, innerY);
                innerY += 5;
            }

            // Tabela de quantidades
            const tableY = innerY + 2;
            doc.setFontSize(6);
            doc.setTextColor(100, 116, 139);
            doc.setFont("helvetica", "normal");
            doc.text("QTD PEDIDO", margin + 5, tableY);
            doc.text("QTD NOTA (XML)", margin + 50, tableY);
            doc.text("QTD CONFERIDA", margin + 95, tableY);

            doc.setFontSize(10);
            doc.setTextColor(30, 41, 59);
            doc.text(fQtd(qtdOC), margin + 5, tableY + 6);
            const isDivQtd = Math.abs(qtdOC - qtdXMLConvertida) > 0.009;
            doc.setTextColor(isDivQtd ? 220 : 30, isDivQtd ? 38 : 41, isDivQtd ? 38 : 59);
            doc.text(fQtd(qtdXMLConvertida), margin + 50, tableY + 6);
            doc.setTextColor(34, 197, 94);
            doc.text(fQtd(qtdConferida), margin + 95, tableY + 6);

            // Status DIVERGENTE
            doc.setFontSize(7);
            doc.setTextColor(239, 68, 68);
            doc.setFont("helvetica", "bold");
            doc.text("DIVERGENTE", pageWidth - margin - 25, tableY + 6);

            // Avança yPos para o próximo item
            yPos += 52;
        }
    }

    // --- RODAPÉ ---
    const totalPaginas = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPaginas; i++) {
        doc.setPage(i);
        doc.setFontSize(7);
        doc.setTextColor(148, 163, 184);
        doc.setFont("helvetica", "normal");
        doc.text(`Nutricional Distribuidora - Auditoria de Carga - ${new Date().toLocaleString()}`, margin, 290);
        doc.text(`Página ${i} de ${totalPaginas}`, pageWidth - margin - 20, 290);
    }

    if (destino === 'abrir') {
        window.open(doc.output('bloburl'), '_blank');
        return true;
    } else if (destino === 'email') {
        return doc.output('blob');
    } else if (destino === 'download') {
        doc.save(`Auditoria_OC_${appState.idoc}.pdf`);
        return true;
    }
    return true;
}
// ==========================================================================
// ENVIAR EMAIL COM DIVERGÊNCIAS - CORRIGIDO (envia apenas itensHtml)
// ==========================================================================

async function enviarEmailDivergencia(arquivoPDF) {
    const divergentes = Array.from(document.querySelectorAll('.item-card')).filter(card => 
        card.classList.contains('divergente') && card.style.display !== 'none'
    );
    if (divergentes.length === 0) return false;
    
    let totalOC = 'R$ 0,00';
    const totalOCElement = document.getElementById('val-total-oc');
    if (totalOCElement && totalOCElement.innerText) totalOC = totalOCElement.innerText;
    else if (appState.nota && appState.nota.valor) totalOC = 'R$ ' + fMoeda(appState.nota.valor);
    
    let totalNF = 'R$ 0,00';
    const totalNFElement = document.getElementById('val-total-xml');
    if (totalNFElement && totalNFElement.innerText) totalNF = totalNFElement.innerText;
    else if (appState.nota && appState.nota.valor) totalNF = 'R$ ' + fMoeda(appState.nota.valor);
    
    let itensHtml = '';
    for (const card of divergentes) {
        const idItem = card.dataset.id || '';
        const ref = card.dataset.referencia || "N/A";
        const nome = card.dataset.nome || "Produto";
        const qtdOC = parseFloat(card.dataset.qtd || 0);
        const precoOC = parseFloat(card.dataset.valorUnit || 0);
        const infoPrecoHTML = card.querySelector('.info-precos-auditoria')?.innerText || "";
        const valorNota = infoPrecoHTML.split('NOTA:')[1] || 'R$ ---';
        let qtdNF = 0;
        const xmlSpan = card.querySelector('.valor-xml-destaque');
        if (xmlSpan) {
            const qtdTexto = xmlSpan.innerText.trim();
            const matchQtd = qtdTexto.match(/[\d.,]+/);
            if (matchQtd) qtdNF = parseFloat(matchQtd[0].replace(/\./g, '').replace(',', '.'));
        }
        let qtdConferida = qtdNF;
        const qtdInput = card.querySelector('.qtd-input');
        if (qtdInput && qtdInput.type !== 'hidden' && qtdInput.value) {
            qtdConferida = parseFloat(qtdInput.value) || qtdNF;
        }
        const isDivPreco = Math.abs(precoOC - parseFloat(valorNota?.replace('R$', '').replace(',', '.') || 0)) > 0.02;
        const isDivQtd = Math.abs(qtdOC - qtdNF) > 0.009;
        
        // Monta apenas o card do item (sem cabeçalho/rodapé)
        itensHtml += `
            <div style="background: #f8fafc; border-left: 4px solid #dc2626; border-radius: 8px; padding: 12px; margin-bottom: 12px;">
                <div style="color: #274036; font-size: 12px; font-weight: bold;">ID: ${idItem} | REF: ${ref}</div>
                <div style="font-weight: bold; font-size: 14px; margin: 8px 0;">${nome}</div>
                <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-top: 8px;">
                    <div><div style="color: #64748b; font-size: 11px;">Valor Unit. OC:</div><div style="font-weight: bold; color: ${isDivPreco ? '#dc2626' : '#059669'};">R$ ${precoOC.toFixed(2).replace('.', ',')}</div></div>
                    <div><div style="color: #64748b; font-size: 11px;">Valor Unit. NOTA:</div><div style="font-weight: bold; color: ${isDivPreco ? '#dc2626' : '#059669'};">${valorNota}</div></div>
                    <div><div style="color: #64748b; font-size: 11px;">QTD PEDIDO:</div><div style="font-weight: bold; color: ${isDivQtd ? '#dc2626' : '#059669'};">${fQtd(qtdOC)}</div></div>
                    <div><div style="color: #64748b; font-size: 11px;">QTD NOTA (XML):</div><div style="font-weight: bold; color: ${isDivQtd ? '#dc2626' : '#059669'};">${fQtd(qtdNF)}</div></div>
                    <div><div style="color: #64748b; font-size: 11px;">QTD CONFERIDA:</div><div style="font-weight: bold; color: #10b981;">${fQtd(qtdConferida)}</div></div>
                </div>
            </div>
        `;
    }
    
    // Envia apenas os itens (sem cabeçalho/rodapé) - o PHP vai montar o HTML completo
    return itensHtml;  // Retorna o HTML dos itens para ser usado no envio
}

// A função acaoApenasEmail precisa ser ajustada para usar o novo retorno
async function acaoApenasEmail() {
    const divergentes = document.querySelectorAll('.item-card.divergente:not([style*="display: none"])');
    if (divergentes.length === 0) {
        Swal.fire({ icon: 'info', title: 'Nenhuma divergência', text: 'Não há itens divergentes para enviar no relatório.', confirmButtonColor: '#274036' });
        return;
    }
    Swal.fire({
        title: 'Preparando e-mail...',
        text: 'Aguarde enquanto o relatório é gerado e enviado.',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    try {
        const pdfBlob = await gerarRelatorioPDF('email');
        if (!pdfBlob) throw new Error('Não foi possível gerar o PDF');
        const arquivoPDF = new File([pdfBlob], `Divergencia_OC_${appState.idoc}.pdf`, { type: 'application/pdf' });
        
        // Gera o HTML dos itens
        const itensHtml = await enviarEmailDivergencia(arquivoPDF);
        if (!itensHtml) {
            Swal.close();
            Swal.fire({ icon: 'warning', title: 'Nenhum item divergente', text: 'Não há itens para enviar.' });
            return;
        }
        
        // Monta o payload com os itens e o PDF
        const formData = new FormData();
        formData.append('idoc', appState.idoc);
        formData.append('fornecedor', appState.fornecedorNome);
        formData.append('total_oc', document.getElementById('val-total-oc')?.innerText || 'R$ 0,00');
        formData.append('total_nf', document.getElementById('val-total-xml')?.innerText || 'R$ 0,00');
        formData.append('qtd_divergencias', divergentes.length);
        formData.append('tabela_divergencias', itensHtml);
        formData.append('pdf', arquivoPDF, `Divergencia_OC_${appState.idoc}.pdf`);
        
        const token = localStorage.getItem('authToken');
        const response = await fetch('/v1/xml/enviar-email', {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${token}` },
            body: formData
        });
        const result = await response.json();
        Swal.close();
        if (result.success) {
            Swal.fire({
                icon: 'success',
                title: 'E-mail Enviado!',
                html: `O relatório foi enviado para:<br><strong>alan@nutricionalbr.com</strong><br><strong>robson@nutricionalbr.com</strong><br><strong>faturamento@nutricionalbr.com</strong>`,
                timer: 4000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            Swal.fire({ icon: 'warning', title: 'Falha no envio', text: 'Não foi possível enviar o e-mail.', confirmButtonColor: '#f59e0b' });
        }
    } catch (error) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Erro', text: 'Falha ao processar: ' + error.message, confirmButtonColor: '#ef4444' });
    }
}

// ==========================================================================
// AÇÃO 3: APENAS SALVAR
// ==========================================================================

async function acaoApenasSalvar() {
    const confirmacao = await Swal.fire({
        title: 'Salvar Alterações?',
        html: `<div style="text-align:left;"><p>Deseja salvar as alterações no banco de dados?</p><p class="text-sm text-amber-600 mt-2"><i class="fa-solid fa-triangle-exclamation"></i> Esta ação não pode ser desfeita.</p></div>`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, Salvar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#10b981',
        cancelButtonColor: '#64748b'
    });
    if (!confirmacao.isConfirmed) return;
    Swal.fire({
        title: 'Salvando...',
        text: 'Aguarde enquanto os dados são salvos no banco.',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    try {
        await dispararSincronizacaoFinal();
        Swal.close();
        Swal.fire({
            icon: 'success',
            title: 'Salvo com Sucesso!',
            text: 'As alterações foram gravadas no banco de dados.',
            timer: 2000,
            showConfirmButton: false
        }).then(() => location.reload());
    } catch (error) {
        Swal.close();
        Swal.fire({ icon: 'error', title: 'Erro ao Salvar', text: error.message, confirmButtonColor: '#ef4444' });
    }
}
async function dispararSincronizacaoFinal() {
    // Coletar dados dos itens para salvar
    const itensParaSalvar = [];
    document.querySelectorAll('.item-card:not([style*="display: none"])').forEach(card => {
        const id = parseInt(card.dataset.id);
        const input = card.querySelector('.qtd-input');
        let quantidade = parseFloat(card.dataset.qtd || 0);
        if (input && input.type !== 'hidden' && input.value) {
            const val = parseFloat(input.value);
            if (!isNaN(val)) quantidade = val;
        }
        itensParaSalvar.push({ iditem: id, quantidade: quantidade });
    });

    const payload = {
        idoc: appState.idoc,
        itens: itensParaSalvar.map(i => ({
            iditem: parseInt(i.iditem) || 0,
            quantidade: parseFloat(i.quantidade) || 0
        })),
        itens_deletar: (appState.itensParaDeletar || []).map(i => parseInt(i.id) || 0),
        itens_adicionar: (appState.itensParaAdicionar || []).map(i => ({
            iditem: parseInt(i.iditem) || 0,
            quantidade: parseFloat(i.quantidade) || 0,
            valor_unitario: parseFloat(i.valor_unitario) || 0
        }))
    };

    try {
        const resp = await fetchWithAuth('/v1/xml/atualizar-conferencia', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        if (!resp.ok) {
            const errorText = await resp.text();
            throw new Error(`Erro ${resp.status}: ${errorText.substring(0, 200)}`);
        }

        const result = await resp.json();
        if (!result.success) {
            throw new Error(result.error || 'Erro ao salvar conferência');
        }

        return result;

    } catch (error) {
        console.error('Erro ao sincronizar:', error);
        throw error;
    }
}
// ==========================================================================
// FUNÇÃO TOAST AUXILIAR
// ==========================================================================

function showToast(msg, icon = 'info') {
    Swal.fire({ toast: true, position: 'top-end', icon: icon, title: msg, showConfirmButton: false, timer: 2000 });
}

// ==========================================================================
// EXPORTAR FUNÇÕES GLOBAIS
// ==========================================================================

window.carregarFornecedores = carregarFornecedores;
window.carregarOCs = carregarOCs;
window.buscarNotas = buscarNotas;
window.selecionarNota = selecionarNota;
window.removerItemOC = removerItemOC;
window.desfazerExclusoes = desfazerExclusoes;
window.validarLinha = validarLinha;
window.copiarChaveAcesso = copiarChaveAcesso;
window.finalizarSincronizacao = finalizarSincronizacao;
window.buscarItemParaAdicionar = buscarItemParaAdicionar;
window.confirmarAdicaoItem = confirmarAdicaoItem;
window.ignorarItemXml = ignorarItemXml;
window.importarXMLManual = window.importarXMLManual;
window.toggleNotaSelecionada = toggleNotaSelecionada;
window.conferirNotasMultiplas = conferirNotasMultiplas;