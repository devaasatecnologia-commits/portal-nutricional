<?php

namespace Nutricional\Utils;

use PDO;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Escopo do usuário autenticado (filiais e gestores liberados em usuario.dash_filiais / dash_gestores).
 *
 * A identidade vem sempre do JWT (atributo "user" do request), nunca do corpo da requisição.
 * Se o usuário não for administrador e não tiver escopo explícito, restringe ao seu próprio ID ou fecha o escopo.
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

        if ($s->uid > 0 || $s->idusuario > 0) {
            $stmt = $pdo->prepare('SELECT idcliforemp, idusuario, dash_filiais, dash_gestores, permite_ver_usuarios FROM usuario WHERE idcliforemp = :uid OR idusuario = :idusuario LIMIT 1');
            $stmt->execute(['uid' => $s->uid, 'idusuario' => $s->idusuario]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $s->filiais = self::lista($row['dash_filiais'] ?? '');
            $s->gestores = self::lista($row['dash_gestores'] ?? '');

            // SE NÃO É ADMINISTRADOR: APLICA HIERARQUIA E RESTRIÇÃO ESTRITA
            if (!$s->isAdmin) {
                // 1. Filial: se não configurada no dash_filiais, tenta a filial padrão do cliforemp
                if (empty($s->filiais) && $s->uid > 0) {
                    $stF = $pdo->prepare('SELECT idfilial FROM cliforemp WHERE idcliforemp = :uid LIMIT 1');
                    $stF->execute(['uid' => $s->uid]);
                    $filialPadrao = (int)($stF->fetchColumn() ?: 0);
                    $s->filiais = $filialPadrao > 0 ? [$filialPadrao] : [-1];
                } elseif (empty($s->filiais)) {
                    $s->filiais = [-1]; // Sem permissão de filial -> bloqueia acesso geral
                }

                // 2. Gestores/Supervisores: se não configurado no dash_gestores
                if (empty($s->gestores) && $s->uid > 0) {
                    // Verifica se o usuário é gestor ou representante no ERP
                    $stG = $pdo->prepare("SELECT 1 FROM vw_gestor_repre WHERE idgestor = :uid OR idrepre = :uid LIMIT 1");
                    $stG->execute(['uid' => $s->uid]);
                    if ($stG->fetchColumn()) {
                        $s->gestores = [$s->uid]; // Escopo restrito a ele mesmo
                    } else {
                        $s->gestores = [-1]; // Usuário comum sem permissão de gestor -> não pode ver outros
                    }
                } elseif (empty($s->gestores)) {
                    $s->gestores = [-1];
                }
            }
        } elseif (!$s->isAdmin) {
            // Não autenticado com identificador válido ou sem perfil admin -> bloqueia
            $s->filiais = [-1];
            $s->gestores = [-1];
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
            if ($i !== '' && (ctype_digit($i) || (is_numeric($i) && (int)$i === -1))) {
                $num = (int)$i;
                if ($num > 0 || $num === -1) {
                    $ids[$num] = $num;
                }
            }
        }
        return array_values($ids);
    }

    public function filialRestrita(): bool
    {
        return !$this->isAdmin;
    }

    public function gestorRestrito(): bool
    {
        return !$this->isAdmin;
    }

    /**
     * Filiais efetivas da consulta: interseção do pedido com o liberado.
     * Retorna [] quando não há restrição (admin sem pedido).
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
            return $this->isAdmin ? $pedidos : [-1];
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
