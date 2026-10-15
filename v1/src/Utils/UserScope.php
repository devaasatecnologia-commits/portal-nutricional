<?php

namespace Nutricional\Utils;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Escopo do usuário autenticado (filiais e gestores liberados em usuario.dash_filiais / dash_gestores).
 *
 * A identidade vem sempre do JWT (atributo "user" do request), nunca do corpo da requisição.
 * Lista vazia de filiais/gestores = sem restrição configurada para o usuário.
 */
final class UserScope
{
    public int $uid;
    public int $idusuario;
    public bool $isAdmin;
    /** @var int[] */
    public array $filiais = [];
    /** @var int[] */
    public array $gestores = [];

    private function __construct() {}

    public static function fromRequest(Request $request, PDO $pdo): self
    {
        $user = $request->getAttribute('user') ?? [];
        $user = is_object($user) ? (array)$user : (array)$user;

        $s = new self();
        $s->uid = (int)($user['uid'] ?? 0);
        $s->idusuario = (int)($user['idusuario'] ?? 0);
        $s->isAdmin = !empty($user['is_admin']) || in_array('admin', (array)($user['permissoes'] ?? []), true);

        if ($s->uid > 0) {
            $stmt = $pdo->prepare('SELECT dash_filiais, dash_gestores FROM usuario WHERE idcliforemp = :uid');
            $stmt->execute(['uid' => $s->uid]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $s->filiais = self::lista($row['dash_filiais'] ?? '');
            $s->gestores = self::lista($row['dash_gestores'] ?? '');
        }

        return $s;
    }

    /** @return int[] */
    public static function lista($valor): array
    {
        $itens = is_array($valor) ? $valor : explode(',', (string)$valor);
        $ids = [];
        foreach ($itens as $i) {
            $i = trim((string)$i);
            if ($i !== '' && ctype_digit($i) && (int)$i > 0) {
                $ids[(int)$i] = (int)$i;
            }
        }
        return array_values($ids);
    }

    public function filialRestrita(): bool
    {
        return !$this->isAdmin && !empty($this->filiais);
    }

    public function gestorRestrito(): bool
    {
        return !$this->isAdmin && !empty($this->gestores);
    }

    /**
     * Filiais efetivas da consulta: interseção do pedido com o liberado.
     * Retorna [] quando não há restrição nem pedido (todas as filiais).
     * Lança DomainException se o usuário pediu filial fora do seu escopo.
     *
     * @param mixed $pedidas id, lista ou 0/null/'' para "todas"
     * @return int[]
     */
    public function resolverFiliais($pedidas = null): array
    {
        return $this->interseccao(self::lista($pedidas), $this->filialRestrita() ? $this->filiais : [], 'filial');
    }

    /**
     * @param mixed $pedidos id, lista ou 0/null/'' para "todos"
     * @return int[]
     */
    public function resolverGestores($pedidos = null): array
    {
        return $this->interseccao(self::lista($pedidos), $this->gestorRestrito() ? $this->gestores : [], 'gestor');
    }

    private function interseccao(array $pedidos, array $permitidos, string $rotulo): array
    {
        if (empty($permitidos)) {
            return $pedidos;
        }
        if (empty($pedidos)) {
            return $permitidos;
        }
        $fora = array_diff($pedidos, $permitidos);
        if (!empty($fora)) {
            throw new \DomainException("Acesso negado: $rotulo fora do seu escopo.");
        }
        return $pedidos;
    }
}
