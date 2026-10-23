-- ============================================================================
-- FUNCTION: public.consultar_clientes_otimizado_v2
-- Atualizada em: 23/10/2026
-- Objetivo: Consulta unificada de clientes CRM + ERP com suporte a filtros
--           de Filial, Gestor e Representante/Vendedor Comercial (UserScope).
-- ============================================================================

DROP FUNCTION IF EXISTS public.consultar_clientes_otimizado_v2(text, integer, text, text, text, text, text, text, date, date, integer, integer, text);

CREATE OR REPLACE FUNCTION public.consultar_clientes_otimizado_v2(
    p_busca text DEFAULT NULL::text, 
    p_busca_id integer DEFAULT NULL::integer, 
    p_origem text DEFAULT NULL::text, 
    p_status text DEFAULT NULL::text, 
    p_termometro text DEFAULT NULL::text, 
    p_periodo text DEFAULT NULL::text, 
    p_ja_comprou text DEFAULT NULL::text, 
    p_tipo_periodo text DEFAULT 'compra'::text, 
    p_data_inicio date DEFAULT NULL::date, 
    p_data_fim date DEFAULT NULL::date, 
    p_limite integer DEFAULT 50, 
    p_offset integer DEFAULT 0, 
    p_origem_cliente text DEFAULT NULL::text,
    p_filiais text DEFAULT NULL::text,
    p_gestores text DEFAULT NULL::text,
    p_vendedor integer DEFAULT NULL::integer
)
 RETURNS jsonb
 LANGUAGE plpgsql
AS $function$
DECLARE
    v_sql TEXT;
    v_result JSONB;
    v_total INTEGER;
    v_where TEXT := ' WHERE 1=1 ';
    v_data_inicio TEXT;
    v_data_fim TEXT;
    v_tem_periodo BOOLEAN := FALSE;
    v_campos_extra TEXT := '';
BEGIN
    -- ================================================================
    -- DETERMINAR DATAS DO PERIODO
    -- ================================================================
    IF p_periodo IS NOT NULL AND p_periodo != '' THEN
        
        -- CASO 1: PERIODO POR COMPRA
        IF p_tipo_periodo = 'compra' THEN
            v_tem_periodo := TRUE;
            
            IF p_periodo = 'hoje' THEN
                v_data_inicio := CURRENT_DATE::text;
                v_data_fim := CURRENT_DATE::text;
            ELSIF p_periodo = '7dias' THEN
                v_data_inicio := (CURRENT_DATE - INTERVAL '7 days')::text;
                v_data_fim := CURRENT_DATE::text;
            ELSIF p_periodo = '15dias' THEN
                v_data_inicio := (CURRENT_DATE - INTERVAL '15 days')::text;
                v_data_fim := CURRENT_DATE::text;
            ELSIF p_periodo = '30dias' THEN
                v_data_inicio := (CURRENT_DATE - INTERVAL '30 days')::text;
                v_data_fim := CURRENT_DATE::text;
            ELSIF p_periodo = '60dias' THEN
                v_data_inicio := (CURRENT_DATE - INTERVAL '60 days')::text;
                v_data_fim := CURRENT_DATE::text;
            ELSIF p_periodo = '90dias' THEN
                v_data_inicio := (CURRENT_DATE - INTERVAL '90 days')::text;
                v_data_fim := CURRENT_DATE::text;
            ELSIF p_periodo = 'personalizado' AND p_data_inicio IS NOT NULL THEN
                v_data_inicio := p_data_inicio::text;
                v_data_fim := COALESCE(p_data_fim, CURRENT_DATE)::text;
            END IF;
        END IF;
        
        -- CASO 2: PERIODO POR CADASTRO
        IF p_tipo_periodo = 'cadastro' THEN
            v_tem_periodo := FALSE;
            
            IF p_periodo = 'personalizado' THEN
                IF p_data_inicio IS NOT NULL THEN
                    v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= ''' || p_data_inicio || '''';
                END IF;
                
                IF p_data_fim IS NOT NULL THEN
                    v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) <= ''' || p_data_fim || '''';
                END IF;
                
            ELSIF p_periodo = 'hoje' THEN
                v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= CURRENT_DATE';
                
            ELSIF p_periodo = '7dias' THEN
                v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= CURRENT_DATE - INTERVAL ''7 days''';
                
            ELSIF p_periodo = '15dias' THEN
                v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= CURRENT_DATE - INTERVAL ''15 days''';
                
            ELSIF p_periodo = '30dias' THEN
                v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= CURRENT_DATE - INTERVAL ''30 days''';
                
            ELSIF p_periodo = '60dias' THEN
                v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= CURRENT_DATE - INTERVAL ''60 days''';
                
            ELSIF p_periodo = '90dias' THEN
                v_where := v_where || ' AND COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) >= CURRENT_DATE - INTERVAL ''90 days''';
                
            END IF;
        END IF;
    END IF;

    -- ================================================================
    -- CONSTRUIR WHERE - SUPORTE A MULTIPLOS VALORES
    -- ================================================================
    
    -- Busca por ID
    IF p_busca_id IS NOT NULL AND p_busca_id > 0 THEN
        v_where := v_where || ' AND (v.id_crm = ' || p_busca_id || ' OR v.id_erp = ' || p_busca_id || ')';
    END IF;
    
    -- Busca por texto
    IF p_busca IS NOT NULL AND LENGTH(TRIM(p_busca)) > 0 THEN
        IF p_busca ~ '^[0-9]+$' THEN
            v_where := v_where || ' AND (v.nome ILIKE ''%' || p_busca || '%'' 
                OR v.empresa ILIKE ''%' || p_busca || '%'' 
                OR v.telefone ILIKE ''%' || p_busca || '%'' 
                OR v.email ILIKE ''%' || p_busca || '%'' 
                OR v.cnpj_cpf ILIKE ''%' || p_busca || '%'')';
        ELSE
            v_where := v_where || ' AND (v.nome ILIKE ''%' || p_busca || '%'' 
                OR v.empresa ILIKE ''%' || p_busca || '%'' 
                OR v.email ILIKE ''%' || p_busca || '%'' 
                OR v.cidade ILIKE ''%' || p_busca || '%'' 
                OR v.observacoes ILIKE ''%' || p_busca || '%'')';
        END IF;
    END IF;
    
    -- Origem dos dados (CRM, ERP, AMBOS)
    IF p_origem IS NOT NULL AND p_origem != '' THEN
        IF p_origem LIKE '%,%' THEN
            v_where := v_where || ' AND v.origem_dados IN (';
            DECLARE
                r TEXT;
                primeiro BOOLEAN := TRUE;
            BEGIN
                FOR r IN SELECT unnest(string_to_array(p_origem, ','))
                LOOP
                    IF NOT primeiro THEN
                        v_where := v_where || ',';
                    END IF;
                    v_where := v_where || '''' || TRIM(r) || '''';
                    primeiro := FALSE;
                END LOOP;
            END;
            v_where := v_where || ')';
        ELSE
            v_where := v_where || ' AND v.origem_dados = ''' || p_origem || '''';
        END IF;
    END IF;
    
    -- Status do CRM
    IF p_status IS NOT NULL AND p_status != '' THEN
        IF p_status LIKE '%,%' THEN
            v_where := v_where || ' AND v.status_crm IN (';
            DECLARE
                r TEXT;
                primeiro BOOLEAN := TRUE;
            BEGIN
                FOR r IN SELECT unnest(string_to_array(p_status, ','))
                LOOP
                    IF NOT primeiro THEN
                        v_where := v_where || ',';
                    END IF;
                    v_where := v_where || '''' || TRIM(r) || '''';
                    primeiro := FALSE;
                END LOOP;
            END;
            v_where := v_where || ')';
        ELSE
            v_where := v_where || ' AND v.status_crm = ''' || p_status || '''';
        END IF;
    END IF;
    
    -- Termômetro
    IF p_termometro IS NOT NULL AND p_termometro != '' THEN
        IF p_termometro LIKE '%,%' THEN
            v_where := v_where || ' AND v.termometro IN (';
            DECLARE
                r TEXT;
                primeiro BOOLEAN := TRUE;
            BEGIN
                FOR r IN SELECT unnest(string_to_array(p_termometro, ','))
                LOOP
                    IF NOT primeiro THEN
                        v_where := v_where || ',';
                    END IF;
                    v_where := v_where || '''' || TRIM(r) || '''';
                    primeiro := FALSE;
                END LOOP;
            END;
            v_where := v_where || ')';
        ELSE
            v_where := v_where || ' AND v.termometro = ''' || p_termometro || '''';
        END IF;
    END IF;
    
    -- Origem do cliente (Site, WhatsApp, Instagram, etc)
    IF p_origem_cliente IS NOT NULL AND p_origem_cliente != '' THEN
        IF p_origem_cliente LIKE '%,%' THEN
            v_where := v_where || ' AND v.origem_crm IN (';
            DECLARE
                r TEXT;
                primeiro BOOLEAN := TRUE;
            BEGIN
                FOR r IN SELECT unnest(string_to_array(p_origem_cliente, ','))
                LOOP
                    IF NOT primeiro THEN
                        v_where := v_where || ',';
                    END IF;
                    v_where := v_where || '''' || TRIM(r) || '''';
                    primeiro := FALSE;
                END LOOP;
            END;
            v_where := v_where || ')';
        ELSE
            v_where := v_where || ' AND v.origem_crm = ''' || p_origem_cliente || '''';
        END IF;
    END IF;
    
    -- ================================================================
    -- FILTRO POR COMPRA - SUPORTE A MULTIPLOS
    -- ================================================================
    IF p_ja_comprou IS NOT NULL AND p_ja_comprou != '' THEN
        IF p_ja_comprou LIKE '%,%' THEN
            IF p_ja_comprou LIKE '%sim%' AND p_ja_comprou LIKE '%nao%' THEN
                NULL;
            ELSIF p_ja_comprou LIKE '%sim%' THEN
                v_where := v_where || ' AND COALESCE(v.total_pedidos, 0) > 0';
            ELSIF p_ja_comprou LIKE '%nao%' THEN
                v_where := v_where || ' AND COALESCE(v.total_pedidos, 0) = 0';
            END IF;
        ELSIF p_ja_comprou = 'sim' THEN
            v_where := v_where || ' AND COALESCE(v.total_pedidos, 0) > 0';
        ELSIF p_ja_comprou = 'nao' THEN
            v_where := v_where || ' AND COALESCE(v.total_pedidos, 0) = 0';
        END IF;
    END IF;

    -- ================================================================
    -- FILTRO POR FILIAL (p_filiais: '1' ou '6' ou '1,6')
    -- ================================================================
    IF p_filiais IS NOT NULL AND TRIM(p_filiais) != '' THEN
        v_where := v_where || ' AND COALESCE(NULLIF(e.idfilial, 0), um.filial, 1) IN (' || p_filiais || ')';
    END IF;

    -- ================================================================
    -- FILTRO POR GESTOR (p_gestores: '13878' ou '13878,15520')
    -- ================================================================
    IF p_gestores IS NOT NULL AND TRIM(p_gestores) != '' THEN
        v_where := v_where || ' AND COALESCE(c.id_gestor, um.cdgestor, rc.idgestor, 0) IN (' || p_gestores || ')';
    END IF;

    -- ================================================================
    -- FILTRO POR VENDEDOR/REPRESENTANTE
    -- ================================================================
    IF p_vendedor IS NOT NULL AND p_vendedor > 0 THEN
        v_where := v_where || ' AND (v.idvendedor = ' || p_vendedor || ' OR c.id_vendedor = ' || p_vendedor || ')';
    END IF;

    -- ================================================================
    -- PERIODO POR COMPRA - FILTRA CLIENTES QUE COMPRARAM NO PERIODO
    -- ================================================================
    IF v_tem_periodo THEN
        v_where := v_where || ' AND EXISTS (
            SELECT 1 FROM pedido p 
            WHERE p.idcliforemp = v.id_erp 
            AND p.data >= ''' || v_data_inicio || '''
            AND p.data <= ''' || v_data_fim || '''
            AND p.status IN (1,4,5)
        )';
    END IF;
    
    -- ================================================================
    -- CAMPOS EXTRA DO PERIODO
    -- ================================================================
    IF v_tem_periodo THEN
        v_campos_extra := COALESCE(',
            COALESCE((
                SELECT COALESCE(SUM(p.valortotalpedido), 0)
                FROM pedido p
                WHERE p.idcliforemp = v.id_erp
                AND p.status IN (1,4,5)
                AND p.data >= ''' || v_data_inicio || '''
                AND p.data <= ''' || v_data_fim || '''
            ), 0) as total_compras_periodo,
            COALESCE((
                SELECT COUNT(DISTINCT p.idpedido)
                FROM pedido p
                WHERE p.idcliforemp = v.id_erp
                AND p.status IN (1,4,5)
                AND p.data >= ''' || v_data_inicio || '''
                AND p.data <= ''' || v_data_fim || '''
            ), 0) as total_pedidos_periodo', '');
    ELSE
        v_campos_extra := ',
            0 as total_compras_periodo,
            0 as total_pedidos_periodo';
    END IF;
    
    -- ================================================================
    -- GARANTIR QUE V_WHERE NAO SEJA NULL
    -- ================================================================
    IF v_where IS NULL THEN
        v_where := ' WHERE 1=1 ';
    END IF;
    
    -- ================================================================
    -- CONTAR TOTAL
    -- ================================================================
    EXECUTE 'SELECT COUNT(*) 
             FROM view_clientes_unificado v
             LEFT JOIN cliforemp e ON e.idcliforemp = v.id_erp
             LEFT JOIN mkt_clientes c ON c.id = v.id_crm
             LEFT JOIN usuarios_mercos um ON um.idcolaborador = v.idvendedor
             LEFT JOIN representante_cliente rc ON rc.idcliforemp = v.idvendedor
             LEFT JOIN cliforemp g ON g.idcliforemp = COALESCE(c.id_gestor, um.cdgestor, rc.idgestor)' || v_where INTO v_total;
    
    -- ================================================================
    -- BUSCAR CLIENTES COM PAGINACAO
    -- ================================================================
    v_sql := COALESCE('
        SELECT jsonb_build_object(
            ''success'', true,
            ''clientes'', COALESCE(
                (SELECT jsonb_agg(row_to_json(t)) FROM (
                    SELECT 
                        v.id_crm,
                        v.id_erp,
                        v.nome,
                        v.empresa,
                        v.telefone,
                        v.email,
                        v.cidade,
                        v.uf,
                        v.endereco,
                        v.numero,
                        v.bairro,
                        v.cep,
                        v.complemento,
                        v.cnpj_cpf,
                        v.ie,
                        v.status_crm,
                        v.termometro,
                        v.valor_negocio,
                        v.origem_crm,
                        v.observacoes,
                        v.data_cadastro_crm,
                        v.data_cadastro_erp,
                        v.ultima_interacao,
                        v.origem_dados,
                        v.data_ultima_compra,
                        COALESCE(v.total_pedidos, 0) as total_pedidos,
                        COALESCE(v.total_compras, 0) as total_compras,
                        COALESCE(NULLIF(e.idfilial, 0), um.filial, 1) as idfilial,
                        CASE WHEN COALESCE(NULLIF(e.idfilial, 0), um.filial, 1) = 6 THEN ''NUTRICIONAL (FILIAL 6 - PR)'' ELSE ''NUTRICIONAL (FILIAL 1 - SC)'' END as nome_filial,
                        v.idvendedor,
                        COALESCE(v.nome_vendedor, c.nome_vendedor, '''') as nome_vendedor,
                        COALESCE(c.id_gestor, um.cdgestor, rc.idgestor, 0) as idgestor,
                        COALESCE(c.nome_gestor, um.gestor, g.fantasia, '''') as nome_gestor'
                        || COALESCE(v_campos_extra, '') ||
                    ' FROM view_clientes_unificado v
                      LEFT JOIN cliforemp e ON e.idcliforemp = v.id_erp
                      LEFT JOIN mkt_clientes c ON c.id = v.id_crm
                      LEFT JOIN usuarios_mercos um ON um.idcolaborador = v.idvendedor
                      LEFT JOIN representante_cliente rc ON rc.idcliforemp = v.idvendedor
                      LEFT JOIN cliforemp g ON g.idcliforemp = COALESCE(c.id_gestor, um.cdgestor, rc.idgestor)
                    ' || COALESCE(v_where, ' WHERE 1=1 ') || '
                    ORDER BY COALESCE(v.data_cadastro_crm, v.data_cadastro_erp) DESC NULLS LAST
                    LIMIT ' || COALESCE(p_limite::text, '50') || ' OFFSET ' || COALESCE(p_offset::text, '0') || '
                ) t),
                ''[]''::jsonb
            ),
            ''total'', ' || COALESCE(v_total::text, '0') || ',
            ''pagina'', ' || COALESCE((p_offset / p_limite + 1)::text, '1') || ',
            ''total_paginas'', CEIL(' || COALESCE(v_total::text, '0') || '::float / ' || COALESCE(p_limite::text, '50') || ')
        )
    ', '');
    
    -- ================================================================
    -- EXECUTAR
    -- ================================================================
    EXECUTE v_sql INTO v_result;
    
    RETURN v_result;
END;
$function$;
