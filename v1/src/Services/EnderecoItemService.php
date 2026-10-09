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
               TRIM(split_part(s.descricao, ' - ', 1)) || '-' || se.linhasigla || se.colunasigla AS endereco
        FROM lote_endereco le
        JOIN secao_enderecos se ON se.idendereco = le.idendereco AND se.idsecao = le.idsecao
        JOIN secao s ON s.idsecao = le.idsecao
        WHERE le.quantidade > 0";

    /**
     * Endereços ativos de vários itens, do lote mais antigo ao mais novo (FIFO).
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
        $sql .= " ORDER BY le.data_entrada ASC, le.lote ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $mapa = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[(int)$row['iditem']][] = $row;
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
