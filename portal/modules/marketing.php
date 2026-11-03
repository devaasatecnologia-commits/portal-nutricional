<?php
/**
 * Redirecionador para o Dashboard Oficial de Marketing
 * Evita erro 404 em acessos legados ou URLs diretas
 */
header('Location: /portal/modules/marketing/dashboard.php', true, 301);
exit;

