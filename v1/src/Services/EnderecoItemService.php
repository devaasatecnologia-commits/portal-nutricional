<?php

namespace Nutricional\Services;

use PDO;

/**
 * Consulta onde cada item está endereçado no Depósito (lote_endereco).
 * Usado pela Separação (mostrar o endereço), pelo Desembarque (sugerir o destino)
 * e pelo Depósito (localizar item).
 */
class EnderecoItemService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    private const SQL_ENDERECO = "
        SELECT le.iditem, le.idfilial, le.idsecao, le.idendereco, le.lote,
               le.quantidade::float AS quantidade, le.data_entrada,
               s.descricao AS secao,
               TRIM(split_part(s.descricao, ' - ', 1)) || '-' || se.linhasigla || se.colunasigla AS endereco,
               'LOTE_FIFO'::text AS origem,
               'LOTE'::text AS tipo
        FROM lote_endereco le
        JOIN secao_enderecos se ON se.idendereco = le.idendereco AND se.idsecao = le.idsecao
        JOIN secao s ON s.idsecao = le.idsecao
        WHERE le.quantidade > 0";

    private const SQL_FIXO = "
        SELECT ie.iditem, NULL::integer AS idfilial, ie.idsecao, ie.idendereco, NULL::text AS lote,
               0::float AS quantidade, NULL::date AS data_entrada,
               s.descricao AS secao,
               TRIM(split_part(s.descricao, ' - ', 1)) || '-' || se.linhasigla || se.colunasigla AS endereco,
               'FIXO'::text AS origem,
               ie.tipo
        FROM item_endereco ie
        JOIN secao_enderecos se ON se.idendereco = ie.idendereco AND se.idsecao = ie.idsecao
        JOIN secao s ON s.idsecao = ie.idsecao
        WHERE 1=1";

    /**
     * Endereços ativos de vários itens, do lote mais antigo ao mais novo (FIFO),
     * complementados pelos endereços fixos cadastrados (item_endereco).
     *
     * @param int[] $iditens
     * @return array<int, array<int, array<string, mixed>>> iditem => endereços
     */
    public function porItens(array $iditens, ?int $idfilial = null): array
    {
        $iditens = array_values(array_unique(array_filter(array_map('intval', $iditens))));
        if (!$iditens) {
            return [];
        }

        $in = implode(',', array_fill(0, count($iditens), '?'));
        $sql = self::SQL_ENDERECO . " AND le.iditem IN ($in)";
        $params = $iditens;
        if ($idfilial) {
            $sql .= " AND le.idfilial = ?";
            $params[] = $idfilial;
        }
        $sql .= " ORDER BY le.data_entrada ASC NULLS LAST, le.lote ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $mapa = [];
        $vistosFixos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $iditem = (int)$row['iditem'];
            $mapa[$iditem][] = $row;
            $vistosFixos[$iditem][(int)$row['idendereco']] = true;
        }

        // Complementa com endereços fixos cadastrados (Giro / Pulmão) sem duplicar se já tem lote no mesmo slot
        try {
            $sqlFixo = self::SQL_FIXO . " AND ie.iditem IN ($in) ORDER BY ie.prioridade ASC, ie.id ASC";
            $stmtFixo = $this->pdo->prepare($sqlFixo);
            $stmtFixo->execute($iditens);
            foreach ($stmtFixo->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $iditem = (int)$row['iditem'];
                if (!empty($vistosFixos[$iditem][(int)$row['idendereco']])) {
                    continue;
                }
                $mapa[$iditem][] = $row;
            }
        } catch (\Throwable $e) {
            // Ignora se a tabela item_endereco ainda não existir em algum ambiente
        }

        return $mapa;
    }

    /** Endereços de um item (para sugerir onde guardar mais unidades). */
    public function porItem(int $iditem, ?int $idfilial = null): array
    {
        return $this->porItens([$iditem], $idfilial)[$iditem] ?? [];
    }

    /** Seção cadastrada para o item no ERP (destino padrão quando ainda não há endereço). */
    public function secaoPadrao(int $iditem): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT s.idsecao, s.descricao
            FROM item i
            JOIN secao s ON s.idsecao = i.idsecao
            WHERE i.iditem = ? AND s.inativo = 'N'");
        $stmt->execute([$iditem]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** Resumo curto para exibição: "A-1A, B-2C". */
    public static function resumo(array $enderecos, int $max = 3): string
    {
        $labels = [];
        foreach ($enderecos as $e) {
            $labels[$e['endereco']] = true;
        }
        $labels = array_keys($labels);
        $txt = implode(', ', array_slice($labels, 0, $max));
        if (count($labels) > $max) {
            $txt .= ' +' . (count($labels) - $max);
        }
        return $txt;
    }
}
