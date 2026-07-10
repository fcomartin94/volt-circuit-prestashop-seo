<?php
/**
 * Attaches one placeholder image per product (already copied to /tmp/product_images
 * inside the container) using PrestaShop's own Image class, so thumbnails are
 * generated correctly in every configured size and the image is properly registered
 * in ps_image / ps_image_lang (including the alt text field), exactly as the back
 * office would do it.
 *
 * Usage:
 *   docker cp scripts/attach_images.php volt-circuit-prestashop:/var/www/html/attach_images.php
 *   docker exec volt-circuit-prestashop php /var/www/html/attach_images.php
 */

define('_PS_ADMIN_DIR_', '/var/www/html/admin');
require_once '/var/www/html/config/config.inc.php';
require_once '/var/www/html/init.php';

const ID_LANG = 1;

// reference => [product name for alt text]
$products = [
    'AWH-001' => 'Aether Wireless Headphones',
    'PSE-002' => 'Pulse Sport Earbuds',
    'NSH-003' => 'Nova Studio Headphones',
    'EMS-004' => 'Echo Mini Speaker',
    'VGC-005' => 'Voltix 65W GaN Charger',
    'FUC-006' => 'Flexi USB-C Cable 2m',
    'PWB-007' => 'Powerbank 10000mAh',
    'WCP-008' => 'Wireless Charging Pad',
    'GCC-009' => 'Guardian Clear Case',
    'TLC-010' => 'Terra Leather Case',
    'SRC-011' => 'Shield Rugged Case',
    'CFW-012' => 'Chrono Fit Watch',
    'PHT-013' => 'Pulse Health Tracker',
];

$imageDir = '/tmp/product_images/';

foreach ($products as $reference => $name) {
    $idProduct = (int) Db::getInstance()->getValue(
        'SELECT id_product FROM '._DB_PREFIX_.'product WHERE reference = "'.pSQL($reference).'"'
    );
    if (!$idProduct) {
        echo "SKIP  {$reference} — product not found\n";
        continue;
    }

    // Skip if this product already has an image (idempotency)
    $existingImage = (int) Db::getInstance()->getValue(
        'SELECT id_image FROM '._DB_PREFIX_.'image WHERE id_product = '.$idProduct
    );
    if ($existingImage) {
        echo "SKIP  {$reference} — already has an image (id {$existingImage})\n";
        continue;
    }

    $sourceFile = $imageDir.$reference.'.jpg';
    if (!file_exists($sourceFile)) {
        echo "FAIL  {$reference} — source file not found at {$sourceFile}\n";
        continue;
    }

    $image = new Image();
    $image->id_product = $idProduct;
    $image->position = 1;
    $image->cover = true;
    $image->legend = array_fill(1, 1, $name.' — product photo');
    $image->legend[ID_LANG] = $name.' — product photo';

    if (!$image->add()) {
        echo "FAIL  {$reference} — Image::add() failed\n";
        continue;
    }

    $targetPath = $image->getPathForCreation().'.jpg';
    if (!copy($sourceFile, $targetPath)) {
        echo "FAIL  {$reference} — could not copy source file to {$targetPath}\n";
        continue;
    }

    // Generate all configured thumbnail sizes from the source image
    $imagesTypes = ImageType::getImagesTypes('products');
    foreach ($imagesTypes as $imageType) {
        $sizedPath = $image->getPathForCreation().'-'.stripslashes($imageType['name']).'.jpg';
        ImageManager::resize($targetPath, $sizedPath, (int) $imageType['width'], (int) $imageType['height']);
    }
    // Also regenerate the base "large" default rendering PrestaShop expects
    ImageManager::resize($targetPath, $image->getPathForCreation().'.jpg');

    echo "OK    {$reference} — image attached (id_image {$image->id}), alt text set\n";
}

echo "Done.\n";
