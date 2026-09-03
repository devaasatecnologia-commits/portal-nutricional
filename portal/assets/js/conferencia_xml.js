// ==========================================================================
// MÓDULO DE CONFERÊNCIA XML - VERSÃO FINAL
// ==========================================================================

// Estado da aplicação
const appState = {
    idoc: '',
    itensDaOC: [],
    nota: null,
    fornecedorNome: '',
    cnpjFornecedor: '',
    itensParaDeletar: [],
    itensParaAdicionar: [],
    itensXMLNaoMatch: [] // Itens do XML que não encontraram match na OC
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
        console.log('Carregando OCs para fornecedor:', s.value, 'filial:', idFilial);
        
        const resp = await fetchWithAuth(`/v1/xml/ordens-compra?idfornecedor=${s.value}&idfilial=${idFilial}`);
        
        if (!resp.ok) {
            const errorText = await resp.text();
            console.error('Erro na resposta:', errorText);
            throw new Error(`HTTP ${resp.status}: ${errorText.substring(0, 100)}`);
        }
        
        const ocs = await resp.json();
        console.log('OCs recebidas:', ocs);
        
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
    if (!opt || !opt.value) return;
    
    appState.idoc = s.value;
    appState.itensParaDeletar = [];
    appState.itensParaAdicionar = [];
    appState.itensXMLNaoMatch = [];
    
    document.getElementById('txt-oc-titulo').innerText = `Conferência OC #${appState.idoc}`;
    document.getElementById('txt-forn-subtitulo').innerText = appState.fornecedorNome;
    document.getElementById('val-total-oc').innerText = 'R$ ' + fMoeda(opt.dataset.valor);
    document.getElementById('containerNotas').style.display = 'block';
    document.getElementById('btnDesfazerExclusao').style.display = 'none';
    document.getElementById('countExcluidos').innerText = '0';

    try {
        const [itensResp, notasResp] = await Promise.all([
            fetchWithAuth(`/v1/xml/consulta-oc/${appState.idoc}`),
            fetchWithAuth(`/v1/xml/buscar-notas?cnpj=${appState.cnpjFornecedor}&data_oc=${opt.dataset.data}&valor_oc=${opt.dataset.valor}`)
        ]);
        
        appState.itensDaOC = await itensResp.json();
        const notas = await notasResp.json();

        // Verifica se já foi conferida
        if (appState.itensDaOC.length > 0 && appState.itensDaOC[0].ja_conferida) {
            const continuar = await Swal.fire({
                title: 'OC Já Conferida!',
                html: 'Esta Ordem de Compra já possui registros de conferência.<br><br><b>Deseja continuar para revisar?</b>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sim, desejo revisar',
                cancelButtonText: 'Não, cancelar',
                confirmButtonColor: '#274036',
                cancelButtonColor: '#ef4444'
            });

            if (!continuar.isConfirmed) {
                location.reload(); 
                return;
            }
        }

        let h = '';
        if (Array.isArray(notas) && notas.length > 0) {
            notas.forEach(n => {
                const notaStr = JSON.stringify(n).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
                h += `<div class="card-nota-mini" data-nota='${notaStr}' onclick="selecionarNota(this)">
                    <b>NF: ${n.numeronf}</b> - R$ ${fMoeda(n.valor)}<br><small>Emissão: ${n.emissao}</small>
                </div>`;
            });
        } else if (notas && notas.aviso) {
            h = `<div style="padding:10px; color:#856404; background:#fff3cd; border-radius:8px; font-size:12px;">${notas.aviso}</div>`;
        } else {
            h = `<div style="padding:10px; color:#64748b; background:#f8fafc; border-radius:8px; font-size:12px;">Nenhuma nota encontrada. Use a importação manual.</div>`;
        }

        document.getElementById('listaNotasCRM').innerHTML = h;

    } catch (e) {
        console.error('Erro ao buscar notas:', e);
        Swal.fire('Erro', 'Falha ao buscar dados da OC ou Notas Disponíveis.', 'error');
    }
}

// ==========================================================================
// SELEÇÃO DE NOTA E PROCESSAMENTO DO XML
// ==========================================================================
async function selecionarNota(el) {
    const notaStr = el.dataset.nota
        .replace(/&quot;/g, '"')
        .replace(/&#39;/g, "'");
    const nota = JSON.parse(notaStr);
    appState.nota = nota;
    
    document.querySelectorAll('.card-nota-mini').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    
    Swal.fire({ 
        title: 'Processando XML...', 
        didOpen: () => Swal.showLoading(), 
        allowOutsideClick: false 
    });
    
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
// RENDERIZAÇÃO E MATCHING
// ==========================================================================
function renderizar(itensXML) {
    let poolXML = [...itensXML];
    
    appState.itensParaDeletar = [];
    appState.itensParaAdicionar = [];
    document.getElementById('btnDesfazerExclusao').style.display = 'none';
    document.getElementById('countExcluidos').innerText = '0';

    // Prepara itens da OC com fator de conversão
    let itensTrabalho = appState.itensDaOC.map(oc => ({
        oc: oc,
        fatorERP: Math.max(parseFloat(oc.fator_conversao) || 1, 1),
        xmlMatch: null,
        score: 0
    }));

    // RODADA 1: Match por EAN/Referência
    itensTrabalho.forEach(item => {
        const oc = item.oc;
        const oc_ean_un = String(oc.ean_unidade || '').replace(/\D/g, '').replace(/^0+/, '');
        const oc_ean_cx = String(oc.ean_caixa || '').replace(/\D/g, '').replace(/^0+/, '');
        const oc_ref = String(oc.cprod || '').toUpperCase().trim();

        let melhorIndex = -1;
        let maiorScore = 0;

        poolXML.forEach((x, idx) => {
            let scoreItem = 0;
            const xml_ean = String(x.ean || '').replace(/\D/g, '').replace(/^0+/, '');
            const xml_trib = String(x.eanTrib || '').replace(/\D/g, '').replace(/^0+/, '');
            const xml_ref = String(x.codigo || '').toUpperCase().trim();

            if (xml_ean && (xml_ean === oc_ean_un || xml_ean === oc_ean_cx)) scoreItem += 100;
            if (xml_trib && (xml_trib === oc_ean_un || xml_trib === oc_ean_cx)) scoreItem += 100;

            const refXML_limpa = xml_ref.replace(/[^A-Z0-9]/gi, '');
            const refOC_limpa = oc_ref.replace(/[^A-Z0-9]/gi, '');
            if (refXML_limpa && refOC_limpa && (refXML_limpa === refOC_limpa || refXML_limpa.includes(refOC_limpa) || refOC_limpa.includes(refXML_limpa))) {
                scoreItem += 90;
            }

            if (scoreItem > maiorScore) {
                maiorScore = scoreItem;
                melhorIndex = idx;
            }
        });

        if (maiorScore > 89) {
            item.xmlMatch = poolXML[melhorIndex];
            item.score = maiorScore;
            poolXML.splice(melhorIndex, 1);
        }
    });

    // RODADA 2: Match por NCM/Nome/Preço
    const strClean = (str) => (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();

    itensTrabalho.forEach(item => {
        if (item.xmlMatch) return;
        
        const oc = item.oc;
        const oc_nome = strClean(String(oc.xprod || ''));
        const oc_ncm = String(oc.ncm || '').replace(/\D/g, '');
        const qEsperadaUn = parseFloat(oc.qcom || 0);
        const precoOC = parseFloat(oc.cuncom || 0);

        let melhorIndex = -1;
        let maiorScore = 0;

        poolXML.forEach((x, idx) => {
            let scoreItem = 0;
            const xml_nome = strClean(String(x.xProd || ''));
            const xml_ncm = String(x.ncm || '').replace(/\D/g, '');
            const xml_qtd = parseFloat(x.qCom || 0);
            const xml_preco = parseFloat(x.vUnCom || 0);

            if (oc_ncm && xml_ncm === oc_ncm) scoreItem += 30;

            const palavrasOC = oc_nome.split(/[\s,.-]+/).filter(p => p.length > 2);
            const encontrados = palavrasOC.filter(p => xml_nome.includes(p)).length;
            const percentualAcerto = palavrasOC.length > 0 ? (encontrados / palavrasOC.length) : 0;
            
            if (percentualAcerto >= 0.5) scoreItem += 40;
            else if (percentualAcerto >= 0.25) scoreItem += 15;

            const qXmlCalculada = xml_qtd * item.fatorERP;
            const pXmlCalculado = xml_preco / item.fatorERP;

            if (Math.abs(qXmlCalculada - qEsperadaUn) < 0.01) scoreItem += 20;
            if (Math.abs(pXmlCalculado - precoOC) < 0.02) scoreItem += 20;

            if (scoreItem > maiorScore) {
                maiorScore = scoreItem;
                melhorIndex = idx;
            }
        });

        if (maiorScore >= 45) {
            item.xmlMatch = poolXML[melhorIndex];
            item.score = maiorScore;
            poolXML.splice(melhorIndex, 1);
        }
    });

    // Itens que sobraram no poolXML são itens do XML que não existem na OC
    appState.itensXMLNaoMatch = [...poolXML];

    // Geração do HTML para itens da OC
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
        const precoXMLAjustado = xmlMatch ? (parseFloat(xmlMatch.vUnCom) / fatorERP) : 0;
        
        const isDivPreco = xmlMatch && Math.abs(precoOC - precoXMLAjustado) > 0.02;
        const isDivQtd = xmlMatch && Math.abs(qEsperadaUn - qXmlCalculada) > 0.009; 
        const isDivGeral = isDivQtd || isDivPreco || !xmlMatch;

        const corBgScore = score > 89 ? '#dcfce7' : (score >= 45 ? '#fef9c3' : '#fee2e2');
        const corTxtScore = score > 89 ? '#166534' : (score >= 45 ? '#854d0e' : '#991b1b');

        // Escapar aspas simples e duplas nos data attributes
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
                    <div style="display: flex; align-items: center; gap: 6px; color: ${xmlMatch ? '#2563eb' : '#dc2626'};">
                        <i class="fa fa-barcode" style="color: ${xmlMatch ? '#94a3b8' : '#f87171'}; width: 12px;"></i>
                        <span><b>[XML]</b> ${xmlMatch ? `PROD: ${xmlMatch.xProd} | REF: ${xmlMatch.codigo}` : '<b>PRODUTO NÃO LOCALIZADO</b>'}</span>
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
                    <span style="font-weight: 800; color: #4338ca; font-size: 16px;" class="valor-xml-destaque">${fQtd(qXmlCalculada)}</span>
                    <div style="font-size: 10px; color: #6366f1; font-weight: 700; margin-top: 2px;">
                        ${xmlMatch ? `${fQtd(qXmlOriginal)} (${xmlMatch.uCom}) × ${fatorERP}` : '---'}
                    </div>
                </div>
            </div>

            <div style="font-size: 12px; line-height: 1.5; color: #475569; padding-left: 10px; border-left: 1px solid #f1f5f9;">
                <div><b>OC:</b> R$ ${precoOC.toFixed(2)}</div>
                <div style="color: ${isDivPreco ? '#dc2626' : '#059669'}; font-weight: 700;" class="info-precos-auditoria">
                    <b>NOTA:</b> ${xmlMatch ? `R$ ${precoXMLAjustado.toFixed(2)}` : '---'} ${isDivPreco ? '<i class="fa fa-exclamation-triangle" style="font-size: 10px;"></i>' : ''}
                </div>
            </div>

            <div style="text-align:center;">
                ${score === 0 ? 
                    `<button onclick="removerItemOC(${oc.iditem})" style="background: #fff; border: 1px solid #fecaca; color: #ef4444; padding: 6px 10px; border-radius: 6px; cursor: pointer;" title="Remover item da OC"><i class="fa fa-trash-alt"></i></button>` : 
                    (isDivQtd ? 
                        `<input type="number" class="qtd-input" value="${qXmlCalculada}" placeholder="0" data-id="${oc.iditem}" data-qtd-original="${qXmlCalculada}" oninput="validarLinha(this)" style="width: 65px; text-align: center; border: 2px solid #ef4444; background: #fff; border-radius: 6px; font-weight: 800; padding: 6px; color: #b91c1c;">` : 
                        `<input type="hidden" class="qtd-input" value="${qXmlCalculada}" data-id="${oc.iditem}"> <i class="fa fa-check-circle" style="color:#10b981; font-size: 20px;"></i>`
                    )}
            </div>

            <div style="text-align:center;">
                <span style="color: ${isDivGeral ? '#b91c1c' : '#15803d'}; font-size: 9px; font-weight: 900; border: 1px solid ${isDivGeral ? '#fecaca' : '#bbf7d0'}; padding: 6px; border-radius: 6px; background: ${isDivGeral ? '#fef2f2' : '#f0fdf4'}; display: block; text-align: center;">
                    ${!xmlMatch ? 'NÃO LOCALIZADO' : (isDivGeral ? 'DIVERGENTE' : 'CONFERIDO')}
                </span>
            </div>
        </div>`;
    });

    // Seção de itens do XML não encontrados na OC
    if (appState.itensXMLNaoMatch.length > 0) {
        html += `<div style="margin-top: 24px; padding: 16px; background: #fffbeb; border: 2px solid #f59e0b; border-radius: 16px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px;">
                <i class="fa fa-exclamation-triangle" style="color: #d97706; font-size: 20px;"></i>
                <strong style="color: #92400e;">ITENS NA NOTA QUE NÃO ESTÃO NA OC (${appState.itensXMLNaoMatch.length})</strong>
            </div>
            <div style="font-size: 12px; color: #78350f; margin-bottom: 12px;">Estes produtos constam no XML da nota fiscal mas não foram encontrados na Ordem de Compra.</div>`;
        
        appState.itensXMLNaoMatch.forEach((xmlItem, index) => {
            html += `<div style="background: white; border: 1px solid #fcd34d; border-radius: 10px; padding: 12px; margin-bottom: 8px;">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div style="flex: 1; min-width: 200px;">
                        <div style="font-weight: 700; color: #1e293b; font-size: 13px;">${xmlItem.xProd}</div>
                        <div style="font-size: 11px; color: #64748b;">
                            REF: ${xmlItem.codigo || 'N/A'} | EAN: ${xmlItem.ean || xmlItem.eanTrib || 'N/A'} | NCM: ${xmlItem.ncm || 'N/A'}
                        </div>
                        <div style="font-size: 11px; color: #4338ca; font-weight: 700;">
                            Qtd: ${fQtd(xmlItem.qCom)} ${xmlItem.uCom} | Unit: R$ ${fMoeda(xmlItem.vUnCom)}
                        </div>
                    </div>
                    <button onclick="buscarItemParaAdicionar(${index})" 
                        style="background: #375a4b; color: white; border: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-weight: 700; font-size: 12px; white-space: nowrap;">
                        <i class="fa fa-plus-circle"></i> ADICIONAR À OC
                    </button>
                </div>
            </div>`;
        });
        
        html += `</div>`;
    }
    
    document.getElementById('corpoItens').innerHTML = html;
    atualizarAuditoria();
}

// ==========================================================================
// BUSCAR ITEM PARA ADICIONAR À OC
// ==========================================================================
async function buscarItemParaAdicionar(index) {
    const xmlItem = appState.itensXMLNaoMatch[index];
    const termoBusca = xmlItem.codigo || xmlItem.ean || xmlItem.eanTrib || xmlItem.xProd.substring(0, 20);
    
    Swal.fire({
        title: 'Buscando item no sistema...',
        html: `Buscando por: <b>${termoBusca}</b><br>Produto: ${xmlItem.xProd}`,
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    
    try {
        const idFilial = document.getElementById('selFilial').value;
        const resp = await fetchWithAuth(`/v1/xml/buscar-item?termo=${encodeURIComponent(termoBusca)}&idfilial=${idFilial}`);
        const itens = await resp.json();
        
        Swal.close();
        
        if (!itens || itens.length === 0) {
            Swal.fire({
                title: 'Item não encontrado',
                html: `Não foi possível localizar "${xmlItem.xProd}" no cadastro de itens.<br><br>Deseja buscar manualmente?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Buscar Manualmente',
                cancelButtonText: 'Cancelar',
                input: 'text',
                inputPlaceholder: 'Digite a referência ou nome do item...'
            }).then(async (result) => {
                if (result.isConfirmed && result.value) {
                    const resp2 = await fetchWithAuth(`/v1/xml/buscar-item?termo=${encodeURIComponent(result.value)}&idfilial=${idFilial}`);
                    const itens2 = await resp2.json();
                    mostrarDialogoAdicionarItem(xmlItem, itens2, index);
                }
            });
            return;
        }
        
        mostrarDialogoAdicionarItem(xmlItem, itens, index);
        
    } catch (e) {
        Swal.close();
        console.error("Erro ao buscar item:", e);
        Swal.fire('Erro', 'Falha ao buscar item no sistema.', 'error');
    }
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
    
    // Proteção contra fatorConversao inválido (0, null, undefined, negativo)
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
                
                // Adicionar à lista de itens para sincronizar
                appState.itensParaAdicionar.push({
                    iditem: iditem,
                    quantidade: quantidade,
                    valor_unitario: valorUnitario
                });
                
                // Recarregar dados da OC
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
        const resp = await fetchWithAuth(`/v1/xml/consulta-oc/${appState.idoc}`);
        appState.itensDaOC = await resp.json();
        
        // Atualizar total OC
        const totalOC = appState.itensDaOC.reduce((sum, item) => sum + (parseFloat(item.qcom) * parseFloat(item.cuncom)), 0);
        document.getElementById('val-total-oc').innerText = 'R$ ' + fMoeda(totalOC);
        
        // Salvar listas antes de renderizar novamente
        const backupAdicionar = [...appState.itensParaAdicionar];
        const backupDeletar = [...appState.itensParaDeletar];
        
        if (appState.nota) {
            const resp2 = await fetchWithAuth(`/v1/xml/itens-xml?chave=${appState.nota.chave}`);
            const xmlText = await resp2.text();
            const itensXML = extrairItensXML(xmlText);
            renderizar(itensXML);
            
            // Restaurar listas após renderizar
            appState.itensParaAdicionar = backupAdicionar;
            appState.itensParaDeletar = backupDeletar;
            
            // Atualizar botão de desfazer se necessário
            if (appState.itensParaDeletar.length > 0) {
                document.getElementById('btnDesfazerExclusao').style.display = 'flex';
                document.getElementById('countExcluidos').innerText = appState.itensParaDeletar.length;
            }
        }
    } catch (e) {
        console.error("Erro ao recarregar OC:", e);
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
        
        // Enviar exclusão para o backend imediatamente
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
        
        // Atualizar total OC
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

// ==========================================================================
// IMPORTAÇÃO MANUAL DE XML
// ==========================================================================
window.importarXMLManual = function(input) {
    if (!input.files || !input.files[0]) return;
    
    const file = input.files[0];
    const reader = new FileReader();

    Swal.fire({
        title: 'Lendo Arquivo...',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });

    reader.onload = function(e) {
        try {
            const xmlText = e.target.result.trim();
            const parser = new DOMParser();
            const docXML = parser.parseFromString(xmlText, "text/xml");
            
            let valorTotalNota = 0;
            const tagValorNF = docXML.querySelector('vNF');
            if (tagValorNF) valorTotalNota = parseFloat(tagValorNF.textContent || 0);

            const itensXML = extrairItensXML(xmlText);
            
            if (!itensXML.length) {
                Swal.fire('Erro no XML', 'Não foi possível localizar produtos neste arquivo.', 'error');
                return;
            }

            document.getElementById('val-total-xml').innerText = 'R$ ' + fMoeda(valorTotalNota) + ' (Manual)';
            document.getElementById('txt-chave-nf').innerText = 'ARQUIVO: ' + file.name;
            document.getElementById('placeholder').style.display = 'none';
            document.getElementById('painelConferencia').style.display = 'block';

            renderizar(itensXML);
            
            Swal.close();
            showToast('XML Importado com sucesso!', 'success');

        } catch (err) {
            console.error("Erro no processamento manual:", err);
            Swal.fire('Erro', 'Falha ao processar a estrutura do XML.', 'error');
        }
    };

    reader.onerror = () => Swal.fire('Erro', 'Não foi possível ler o arquivo.', 'error');
    reader.readAsText(file);
};
// ==========================================================================
// FINALIZAR CONFERÊNCIA - 3 OPÇÕES SEPARADAS
// ==========================================================================
async function finalizarSincronizacao() {
    // Verificar campos vazios
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

    // Texto de divergências
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

    // ======================================================================
    // MODAL COM 3 OPÇÕES SEPARADAS
    // ======================================================================
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
            // Botão PDF
            document.getElementById('btnPdf').onclick = async () => {
                Swal.close();
                await acaoApenasPdf();
            };
            
            // Botão Email
            document.getElementById('btnEmail').onclick = async () => {
                Swal.close();
                await acaoApenasEmail();
            };
            
            // Botão Salvar
            document.getElementById('btnSalvar').onclick = async () => {
                Swal.close();
                await acaoApenasSalvar();
            };
        }
    });
}

// ==========================================================================
// AÇÃO 1: APENAS PDF (NÃO SALVA)
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
// GERAR RELATÓRIO PDF CORRIGIDO - LAYOUT LIMPO E LEGÍVEL
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
    let y = 20;

    // ---- CABEÇALHO ----
    doc.setFont("helvetica", "bold");
    doc.setFontSize(16);
    doc.text(`Relatório de Divergências - OC #${appState.idoc}`, margin, y);
    y += 8;

    doc.setFontSize(9);
    doc.setTextColor(71, 85, 105);
    doc.text(`Fornecedor: ${appState.fornecedorNome}`, margin, y);
    y += 5;
    doc.text(`Data: ${new Date().toLocaleString('pt-BR')}`, margin, y);
    y += 8;

    // Totais lado a lado
    doc.setFillColor(241, 245, 249);
    doc.roundedRect(margin, y, 85, 20, 2, 2, 'F');
    doc.roundedRect(margin + 95, y, 85, 20, 2, 2, 'F');

    doc.setFontSize(9);
    doc.setFont("helvetica", "bold");
    doc.text("Total OC:", margin + 5, y + 8);
    doc.text("Total NF:", margin + 5, y + 14);
    doc.text(document.getElementById('val-total-oc')?.innerText || "R$ 0,00", margin + 35, y + 8);
    doc.text(`R$ ${fMoeda(appState.nota?.valor || 0)}`, margin + 35, y + 14);

    doc.text("Itens OC:", margin + 100, y + 8);
    doc.text("Itens NF:", margin + 100, y + 14);
    doc.text(`${appState.itensDaOC.length}`, margin + 145, y + 8);
    doc.text(`${appState.itensXMLNaoMatch ? (appState.itensDaOC.length + appState.itensXMLNaoMatch.length) : appState.itensDaOC.length}`, margin + 145, y + 14);

    y += 28;

    // ---- TABELA DE ITENS DIVERGENTES ----
    const todosCards = Array.from(document.querySelectorAll('.item-card'));
    const itensDivergentes = todosCards.filter(card => 
        card.classList.contains('divergente') && card.style.display !== 'none'
    );

    if (itensDivergentes.length === 0 && (!appState.itensXMLNaoMatch || appState.itensXMLNaoMatch.length === 0)) {
        doc.setFontSize(12);
        doc.setTextColor(34, 197, 94);
        doc.text("✓ Nenhuma divergência encontrada.", margin, y);
        y += 15;
    } else {
        doc.setFontSize(11);
        doc.setFont("helvetica", "bold");
        doc.setTextColor(220, 38, 38);
        doc.text(`📋 ITENS DIVERGENTES (${itensDivergentes.length})`, margin, y);
        y += 8;

        // Cabeçalho da tabela
        doc.setFontSize(8);
        doc.setFont("helvetica", "bold");
        doc.setTextColor(100, 116, 139);
        doc.setFillColor(241, 245, 249);
        doc.rect(margin, y, pageWidth - (margin * 2), 8, 'F');
        
        const colX = {
            produto: margin + 2,
            ref: margin + 62,
            qtdOc: margin + 92,
            qtdNf: margin + 112,
            precoOc: margin + 132,
            precoNf: margin + 152,
            status: margin + 172
        };
        
        doc.text("Produto", colX.produto, y + 5);
        doc.text("Ref", colX.ref, y + 5);
        doc.text("Qtd OC", colX.qtdOc, y + 5, { align: 'right' });
        doc.text("Qtd NF", colX.qtdNf, y + 5, { align: 'right' });
        doc.text("R$ OC", colX.precoOc, y + 5, { align: 'right' });
        doc.text("R$ NF", colX.precoNf, y + 5, { align: 'right' });
        doc.text("Status", colX.status, y + 5, { align: 'center' });
        
        y += 10;
        let rowCount = 0;

        for (const card of itensDivergentes) {
            if (y > 270) {
                doc.addPage();
                y = 20;
                rowCount = 0;
            }
            
            const nome = (card.dataset.nome || "Produto").substring(0, 28);
            const ref = (card.dataset.referencia || "N/A").substring(0, 10);
            const qtdOC = parseFloat(card.dataset.qtd || 0);
            
            const valorXmlSpan = card.querySelector('.valor-xml-destaque');
            let qtdNF = 0;
            if (valorXmlSpan) {
                const qtdTexto = valorXmlSpan.innerText.replace(/\./g, '').replace(',', '.');
                qtdNF = parseFloat(qtdTexto) || 0;
            }
            
            const precoOC = parseFloat(card.dataset.valorUnit || 0);
            const precoDiv = card.querySelector('.info-precos-auditoria');
            let precoNF = 0;
            if (precoDiv) {
                const precoTexto = precoDiv.innerText;
                const match = precoTexto.match(/R\$ ([\d.,]+)/);
                if (match) precoNF = parseFloat(match[1].replace(/\./g, '').replace(',', '.'));
            }
            
            doc.setFontSize(7);
            doc.setFont("helvetica", "normal");
            doc.setTextColor(30, 41, 59);
            
            doc.text(nome, colX.produto, y + 3);
            doc.text(ref, colX.ref, y + 3);
            doc.text(fQtd(qtdOC), colX.qtdOc, y + 3, { align: 'right' });
            doc.text(fQtd(qtdNF), colX.qtdNf, y + 3, { align: 'right' });
            doc.text("R$ " + precoOC.toFixed(2).replace('.', ','), colX.precoOc, y + 3, { align: 'right' });
            doc.text(precoNF > 0 ? "R$ " + precoNF.toFixed(2).replace('.', ',') : "---", colX.precoNf, y + 3, { align: 'right' });
            
            const statusColor = card.classList.contains('ok') ? [34, 197, 94] : [220, 38, 38];
            doc.setTextColor(statusColor[0], statusColor[1], statusColor[2]);
            doc.text(card.classList.contains('ok') ? "OK" : "DIVERGE", colX.status, y + 3, { align: 'center' });
            
            y += 6;
            rowCount++;
            
            if (rowCount % 2 === 0) {
                doc.setDrawColor(226, 232, 240);
                doc.line(margin, y - 1, pageWidth - margin, y - 1);
            }
        }
        
        y += 5;
    }

    // ---- ITENS DO XML NÃO LOCALIZADOS ----
    if (appState.itensXMLNaoMatch && appState.itensXMLNaoMatch.length > 0 && y < 260) {
        y += 5;
        doc.setFontSize(11);
        doc.setFont("helvetica", "bold");
        doc.setTextColor(245, 158, 11);
        doc.text(`⚠️ ITENS NA NF NÃO LOCALIZADOS NA OC (${appState.itensXMLNaoMatch.length})`, margin, y);
        y += 6;
        
        doc.setFontSize(7);
        doc.setFont("helvetica", "normal");
        doc.setTextColor(100, 116, 139);
        
        const itensLimitados = appState.itensXMLNaoMatch.slice(0, 10);
        for (const xmlItem of itensLimitados) {
            if (y > 275) {
                doc.addPage();
                y = 20;
            }
            const texto = `${xmlItem.xProd.substring(0, 45)} | Qtd: ${fQtd(xmlItem.qCom)} ${xmlItem.uCom} | REF: ${xmlItem.codigo || 'N/A'}`;
            doc.text("• " + texto, margin, y);
            y += 4;
        }
        
        if (appState.itensXMLNaoMatch.length > 10) {
            doc.text(`... e mais ${appState.itensXMLNaoMatch.length - 10} itens não listados`, margin, y);
            y += 5;
        }
    }

    // ---- RODAPÉ ----
    const totalPaginas = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPaginas; i++) {
        doc.setPage(i);
        doc.setFontSize(7);
        doc.setTextColor(148, 163, 184);
        doc.text(`Nutricional Distribuidora - Auditoria de Carga - ${new Date().toLocaleString()}`, margin, 290);
        doc.text(`Página ${i} de ${totalPaginas}`, pageWidth - margin - 20, 290);
    }

    // ---- AÇÕES FINAIS ----
    if (destino === 'abrir' || destino === 'impressao') {
        const blob = doc.output('blob');
        const url = URL.createObjectURL(blob);
        window.open(url, '_blank');
        setTimeout(() => URL.revokeObjectURL(url), 5000);
        return true;
    } else if (destino === 'email') {
        return doc.output('blob');
    }
    return true;
}

// ==========================================================================
// ENVIAR EMAIL CORRIGIDO - INCLUI DADOS NO CORPO
// ==========================================================================
async function enviarEmailDivergencia(pdfBlob) {
    const divergentes = document.querySelectorAll('.item-card.divergente:not([style*="display: none"])');
    
    // Construir tabela HTML para o corpo do email
    let tabelaHtml = `
        <table style="width:100%; border-collapse: collapse; font-family: Arial, sans-serif; font-size: 12px;">
            <thead>
                <tr style="background: #274036; color: white;">
                    <th style="padding: 8px; text-align: left;">Produto</th>
                    <th style="padding: 8px; text-align: center;">Qtd OC</th>
                    <th style="padding: 8px; text-align: center;">Qtd NF</th>
                    <th style="padding: 8px; text-align: right;">Preço OC</th>
                    <th style="padding: 8px; text-align: right;">Preço NF</th>
                </tr>
            </thead>
            <tbody>
    `;
    
    for (const card of divergentes) {
        const nome = card.dataset.nome || "Produto";
        const qtdOC = parseFloat(card.dataset.qtd || 0);
        const precoOC = parseFloat(card.dataset.valorUnit || 0);
        
        const valorXmlSpan = card.querySelector('.valor-xml-destaque');
        let qtdNF = 0;
        if (valorXmlSpan) {
            const qtdTexto = valorXmlSpan.innerText.replace(/\./g, '').replace(',', '.');
            qtdNF = parseFloat(qtdTexto) || 0;
        }
        
        const precoDiv = card.querySelector('.info-precos-auditoria');
        let precoNF = 0;
        if (precoDiv) {
            const precoTexto = precoDiv.innerText;
            const match = precoTexto.match(/R\$ ([\d.,]+)/);
            if (match) precoNF = parseFloat(match[1].replace(/\./g, '').replace(',', '.'));
        }
        
        tabelaHtml += `
            <tr style="border-bottom: 1px solid #e2e8f0;">
                <td style="padding: 8px;">${nome.substring(0, 50)}</td>
                <td style="padding: 8px; text-align: center;">${fQtd(qtdOC)}</td>
                <td style="padding: 8px; text-align: center;">${fQtd(qtdNF)}</td>
                <td style="padding: 8px; text-align: right;">R$ ${precoOC.toFixed(2).replace('.', ',')}</td>
                <td style="padding: 8px; text-align: right;">${precoNF > 0 ? 'R$ ' + precoNF.toFixed(2).replace('.', ',') : '---'}</td>
            </tr>
        `;
    }
    
    tabelaHtml += `
            </tbody>
        </table>
    `;
    
    const formData = new FormData();
    formData.append('idoc', appState.idoc);
    formData.append('fornecedor', appState.fornecedorNome);
    formData.append('total_oc', document.getElementById('val-total-oc')?.innerText || 'R$ 0,00');
    formData.append('total_nf', `R$ ${fMoeda(appState.nota?.valor || 0)}`);
    formData.append('qtd_divergencias', divergentes.length);
    formData.append('tabela_divergencias', tabelaHtml);
    formData.append('pdf', pdfBlob, `Divergencia_OC_${appState.idoc}.pdf`);

    try {
        const resp = await fetchWithAuth('/v1/xml/enviar-email', {
            method: 'POST',
            body: formData
        });
        const result = await resp.json();
        return result.success === true;
    } catch (error) {
        console.error('Erro ao enviar e-mail:', error);
        return false;
    }
}

// ==========================================================================
// SALVAR NO BANCO CORRIGIDO - INCLUI ADIÇÃO E EXCLUSÃO
// ==========================================================================
async function dispararSincronizacaoFinal() {
    const idoc = document.getElementById('selOC').value;
    if (!idoc) throw new Error('ID da OC não encontrado');
    
    // Coletar itens modificados (inputs com alteração)
    const dadosParaEnviar = [];
    document.querySelectorAll('.item-card .qtd-input').forEach(input => {
        const card = input.closest('.item-card');
        if (card && card.style.display !== 'none') {
            const valor = input.type === 'hidden' ? input.value : (input.value || 0);
            dadosParaEnviar.push({
                iditem: card.dataset.id,
                quantidade: parseFloat(valor) || 0
            });
        }
    });
    
    // Itens deletados (marcados para exclusão)
    const itensDeletar = appState.itensParaDeletar.map(i => i.id);
    
    // Itens adicionados (do XML que não tinha match)
    const itensAdicionar = appState.itensParaAdicionar || [];
    
    const fd = new FormData();
    fd.append('idoc', idoc);
    fd.append('itens', JSON.stringify(dadosParaEnviar));
    fd.append('itens_deletar', JSON.stringify(itensDeletar));
    fd.append('itens_adicionar', JSON.stringify(itensAdicionar));
    
    const resp = await fetchWithAuth('/v1/xml/atualizar-conferencia', {
        method: 'POST',
        body: fd
    });
    
    const result = await resp.json();
    if (!result.success) throw new Error(result.error || 'Erro ao salvar');
    
    return result;
}

// ==========================================================================
// AÇÃO 3: APENAS SALVAR (NÃO GERA PDF NEM EMAIL)
// ==========================================================================
async function acaoApenasSalvar() {
    // Confirmar com o usuário
    const confirmacao = await Swal.fire({
        title: 'Salvar Alterações?',
        html: `
            <div style="text-align: left;">
                <p>Deseja salvar as alterações no banco de dados?</p>
                <p class="text-sm text-amber-600 mt-2">
                    <i class="fa-solid fa-triangle-exclamation"></i> 
                    Esta ação não pode ser desfeita.
                </p>
            </div>
        `,
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
        }).then(() => {
            location.reload();
        });
    } catch (error) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Erro ao Salvar',
            text: error.message,
            confirmButtonColor: '#ef4444'
        });
    }
}


// ==========================================================================
// AÇÃO 2: APENAS EMAIL (NÃO SALVA)
// ==========================================================================
async function acaoApenasEmail() {
    Swal.fire({
        title: 'Preparando e-mail...',
        text: 'Aguarde enquanto o relatório é gerado e enviado.',
        didOpen: () => Swal.showLoading(),
        allowOutsideClick: false
    });
    
    try {
        const pdfBlob = await gerarRelatorioPDF('email');
        
        if (!pdfBlob) {
            throw new Error('Não foi possível gerar o PDF');
        }
        
        const emailEnviado = await enviarEmailDivergencia(pdfBlob);
        
        Swal.close();
        
        if (emailEnviado) {
            Swal.fire({
                icon: 'success',
                title: 'E-mail Enviado!',
                text: 'O relatório foi enviado para a gerência.',
                timer: 3000,
                showConfirmButton: false,
                toast: true,
                position: 'top-end'
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Falha no envio',
                text: 'Não foi possível enviar o e-mail. Verifique sua conexão.',
                confirmButtonColor: '#f59e0b'
            });
        }
    } catch (error) {
        Swal.close();
        Swal.fire({
            icon: 'error',
            title: 'Erro',
            text: 'Falha ao processar: ' + error.message,
            confirmButtonColor: '#ef4444'
        });
    }
}
// ==========================================================================
// FUNÇÃO: APENAS PDF/EMAIL (NÃO SALVA NO BANCO)
// ==========================================================================
async function apenasPdfEmail() {
    const result = await Swal.fire({
        title: 'Opções de Relatório',
        html: `
            <div style="text-align: left;">
                <p>Gerar relatório <b>sem salvar</b> as alterações no banco.</p>
                <p class="text-sm text-amber-600 mt-2">⚠️ Nenhuma alteração será gravada!</p>
            </div>
        `,
        icon: 'info',
        showCancelButton: true,
        showDenyButton: true,
        confirmButtonText: '<i class="fa fa-print"></i> Apenas PDF',
        denyButtonText: '<i class="fa fa-envelope"></i> Apenas Email',
        cancelButtonText: 'Voltar',
        confirmButtonColor: '#3b82f6',
        denyButtonColor: '#f59e0b',
        cancelButtonColor: '#64748b'
    });

    if (result.isConfirmed) {
        // Apenas PDF (não salva)
        await gerarRelatorioPDF('abrir');
        Swal.fire('PDF Gerado!', 'O relatório foi aberto em nova aba.', 'success');
    } else if (result.isDenied) {
        // Apenas Email (não salva)
        const pdfBlob = await gerarRelatorioPDF('email');
        if (pdfBlob) {
            const emailEnviado = await enviarEmailDivergencia(pdfBlob);
            if (emailEnviado) {
                Swal.fire('E-mail Enviado!', 'Relatório enviado por e-mail.', 'success');
            } else {
                Swal.fire('Erro', 'Falha ao enviar e-mail.', 'error');
            }
        }
    }
}


// Função toast auxiliar
function showToast(msg, icon = 'info') {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: icon,
        title: msg,
        showConfirmButton: false,
        timer: 2000
    });
}

// Exportar funções globais
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