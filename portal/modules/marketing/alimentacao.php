<?php
/**
 * Redirecionador para Metas & Objetivos (Alimentação Integrada)
 * Evita erro 404 em acessos legados
 */
header('Location: /portal/modules/marketing/metas.php', true, 301);
exit;

