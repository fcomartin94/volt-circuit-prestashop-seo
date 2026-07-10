<?php
/**
 * One-off fix: the original seed_products.php set Combination->quantity directly,
 * which does not propagate to ps_stock_available (the table PrestaShop actually
 * reads for availability). This script re-applies the intended quantities via
 * StockAvailable::setQuantity(), matching combinations by their reference suffix.
 *
 * Usage:
 *   docker cp scripts/fix_stock.php volt-circuit-prestashop:/var/www/html/fix_stock.php
 *   docker exec volt-circuit-prestashop php /var/www/html/fix_stock.php
 */

define('_PS_ADMIN_DIR_', '/var/www/html/admin');
require_once '/var/www/html/config/config.inc.php';
require_once '/var/www/html/init.php';

// reference => quantity, taken directly from the original seed data in seed_products.php
$quantities = [
    'PSE-002-BLK' => 50, 'PSE-002-BLU' => 50,
    'NSH-003-BLK' => 40, 'NSH-003-SLV' => 40,
    'EMS-004-BLK' => 30, 'EMS-004-RED' => 30, 'EMS-004-WHT' => 30,
    'FUC-006-BLK' => 100, 'FUC-006-WHT' => 100,
    'PWB-007-10K' => 60, 'PWB-007-20K' => 60,
    'WCP-008-BLK' => 70, 'WCP-008-WHT' => 70,
    'GCC-009-I15' => 80, 'GCC-009-I15P' => 80,
    'TLC-010-BRN-I15' => 30, 'TLC-010-BLK-I15' => 30,
    'TLC-010-BRN-I15P' => 30, 'TLC-010-BLK-I15P' => 30,
    'SRC-011-BLK' => 45, 'SRC-011-GRN' => 45,
    'CFW-012-BLK' => 25, 'CFW-012-SLV' => 25, 'CFW-012-RSG' => 25,
    'PHT-013-BLK' => 35, 'PHT-013-WHT' => 35,
];

$rows = Db::getInstance()->executeS(
    'SELECT id_product, id_product_attribute, reference FROM '._DB_PREFIX_.'product_attribute'
);

$fixed = 0;
$skipped = 0;
foreach ($rows as $row) {
    $ref = $row['reference'];
    if (!isset($quantities[$ref])) {
        echo "SKIP  {$ref} — not in fix list (not part of this seed batch)\n";
        $skipped++;
        continue;
    }
    StockAvailable::setQuantity((int) $row['id_product'], (int) $row['id_product_attribute'], $quantities[$ref]);
    echo "OK    {$ref} — quantity set to {$quantities[$ref]}\n";
    $fixed++;
}

echo "Done. Fixed: {$fixed}, skipped: {$skipped}\n";
