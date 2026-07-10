<?php
/**
 * Seed script for Volt & Circuit catalog.
 *
 * Runs INSIDE the PrestaShop container using PrestaShop's own bootstrap and ORM
 * classes (Product, Combination, AttributeGroup, Attribute), so every record is
 * created exactly as if it had been entered through the back office — same
 * validation, same category/lang tables, same fields available to the SEO tab.
 *
 * Usage (from host):
 *   docker cp scripts/seed_products.php volt-circuit-prestashop:/var/www/html/seed_products.php
 *   docker exec volt-circuit-prestashop php /var/www/html/seed_products.php
 *
 * Idempotency: each product is looked up by reference before creating it, so the
 * script can be re-run safely without creating duplicates.
 */

define('_PS_ADMIN_DIR_', '/var/www/html/admin');
require_once '/var/www/html/config/config.inc.php';
require_once '/var/www/html/init.php';

const ID_LANG = 1; // English
const ID_SHOP = 1;

// Category IDs, as created earlier via the back office
const CAT_HEADPHONES = 3;
const CAT_CHARGERS   = 4;
const CAT_CASES       = 5;
const CAT_WATCHES     = 6;

function getOrCreateAttributeGroup(string $name): AttributeGroup
{
    $idGroup = (int) Db::getInstance()->getValue(
        'SELECT ag.id_attribute_group FROM '._DB_PREFIX_.'attribute_group ag
         JOIN '._DB_PREFIX_.'attribute_group_lang agl ON agl.id_attribute_group = ag.id_attribute_group
         WHERE agl.name = "'.pSQL($name).'" AND agl.id_lang = '.ID_LANG
    );
    if ($idGroup) {
        return new AttributeGroup($idGroup, ID_LANG);
    }

    $group = new AttributeGroup();
    $group->is_color_group = ($name === 'Color');
    $group->group_type = $name === 'Color' ? 'color' : 'select';
    $group->name = array_fill(1, 1, $name);
    $group->name[ID_LANG] = $name;
    $group->public_name = array_fill(1, 1, $name);
    $group->public_name[ID_LANG] = $name;
    $group->add();

    return $group;
}

function getOrCreateAttribute(AttributeGroup $group, string $value, string $color = ''): ProductAttribute
{
    $idAttribute = (int) Db::getInstance()->getValue(
        'SELECT a.id_attribute FROM '._DB_PREFIX_.'attribute a
         JOIN '._DB_PREFIX_.'attribute_lang al ON al.id_attribute = a.id_attribute
         WHERE a.id_attribute_group = '.(int) $group->id.'
         AND al.name = "'.pSQL($value).'" AND al.id_lang = '.ID_LANG
    );
    if ($idAttribute) {
        return new ProductAttribute($idAttribute);
    }

    $attribute = new ProductAttribute();
    $attribute->id_attribute_group = $group->id;
    $attribute->name = array_fill(1, 1, $value);
    $attribute->name[ID_LANG] = $value;
    if ($color !== '') {
        $attribute->color = $color;
    }
    $attribute->add();

    return $attribute;
}

function findProductIdByReference(string $reference): int
{
    return (int) Db::getInstance()->getValue(
        'SELECT id_product FROM '._DB_PREFIX_.'product WHERE reference = "'.pSQL($reference).'"'
    );
}

/**
 * @param array{
 *   name: string, reference: string, category: int, summary: string,
 *   description: string, price: float, seo_title: string, seo_description: string,
 *   combinations: array<array{group: string, value: string, color?: string, ref_suffix: string, qty: int}>
 * } $data
 */
function seedProduct(array $data): void
{
    $existingId = findProductIdByReference($data['reference']);
    if ($existingId) {
        echo "SKIP  {$data['reference']} — already exists (id {$existingId})\n";
        return;
    }

    $product = new Product();
    $product->name = array_fill(1, 1, $data['name']);
    $product->name[ID_LANG] = $data['name'];
    $product->link_rewrite = array_fill(1, 1, Tools::str2url($data['name']));
    $product->link_rewrite[ID_LANG] = Tools::str2url($data['name']);
    $product->description_short = array_fill(1, 1, $data['summary']);
    $product->description_short[ID_LANG] = '<p>'.$data['summary'].'</p>';
    $product->description = array_fill(1, 1, $data['description']);
    $product->description[ID_LANG] = '<p>'.$data['description'].'</p>';
    $product->reference = $data['reference'];
    $product->price = $data['price'];
    $product->id_tax_rules_group = 1; // ES Standard rate (21%) — assumes rule group id 1; verified separately
    $product->active = 1;
    $product->state = Product::STATE_SAVED;
    $product->condition = 'new';

    if ($data['seo_title'] !== '') {
        $product->meta_title = array_fill(1, 1, $data['seo_title']);
        $product->meta_title[ID_LANG] = $data['seo_title'];
    }
    if ($data['seo_description'] !== '') {
        $product->meta_description = array_fill(1, 1, $data['seo_description']);
        $product->meta_description[ID_LANG] = $data['seo_description'];
    }

    $product->add();
    $product->updateCategories([$data['category'], 2]); // 2 = Home root category

    if (!empty($data['combinations'])) {
        // Group combination rows by attribute group name so we can generate the
        // cartesian product when a product varies on 2 axes (e.g. color + model).
        $byGroup = [];
        foreach ($data['combinations'] as $row) {
            $byGroup[$row['group']][] = $row;
        }

        // Simple case: single attribute axis (most products here).
        if (count($byGroup) === 1) {
            $groupName = array_key_first($byGroup);
            $group = getOrCreateAttributeGroup($groupName);
            foreach ($byGroup[$groupName] as $row) {
                $attribute = getOrCreateAttribute($group, $row['value'], $row['color'] ?? '');
                $combination = new Combination();
                $combination->id_product = $product->id;
                $combination->reference = $data['reference'].'-'.$row['ref_suffix'];
                $combination->quantity = $row['qty'];
                $combination->price = 0; // no price impact
                $combination->add();
                $combination->setAttributes([$attribute->id]);
                StockAvailable::setQuantity($product->id, $combination->id, $row['qty']);
            }
        } else {
            // Two axes (e.g. Terra Leather Case: Color x Model) — build attribute ids
            // per group, then create one combination per row using both ids matched
            // by ref_suffix pairing already expressed explicitly in the seed data.
            foreach ($data['combinations'] as $row) {
                $group = getOrCreateAttributeGroup($row['group']);
                $attributeIds = [];
                $attribute = getOrCreateAttribute($group, $row['value'], $row['color'] ?? '');
                $attributeIds[] = $attribute->id;
                if (isset($row['pair_group'], $row['pair_value'])) {
                    $pairGroup = getOrCreateAttributeGroup($row['pair_group']);
                    $pairAttribute = getOrCreateAttribute($pairGroup, $row['pair_value']);
                    $attributeIds[] = $pairAttribute->id;
                }
                $combination = new Combination();
                $combination->id_product = $product->id;
                $combination->reference = $data['reference'].'-'.$row['ref_suffix'];
                $combination->quantity = $row['qty'];
                $combination->price = 0;
                $combination->add();
                $combination->setAttributes($attributeIds);
                StockAvailable::setQuantity($product->id, $combination->id, $row['qty']);
            }
        }
    } else {
        StockAvailable::setQuantity($product->id, 0, 100);
    }

    echo "OK    {$data['reference']} — created (id {$product->id})\n";
}

$products = [
    // --- Headphones & Audio ---
    [
        'name' => 'Pulse Sport Earbuds', 'reference' => 'PSE-002', 'category' => CAT_HEADPHONES,
        'summary' => 'True wireless sport earbuds with IPX7 water resistance and secure ear-hook fit.',
        'description' => 'The Pulse Sport Earbuds are built for workouts: IPX7 water and sweat resistance, a secure ear-hook fit that won\'t budge, and touch controls for calls and music. Up to 8 hours of playback per charge, 24 hours total with the charging case.',
        'price' => 59.99,
        'seo_title' => 'Pulse Sport Earbuds | Volt & Circuit',
        'seo_description' => 'Shop the Pulse Sport Earbuds: IPX7 waterproof true wireless earbuds with secure fit, touch controls, and 24-hour battery life.',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 50],
            ['group' => 'Color', 'value' => 'Blue', 'color' => '#1E5FA8', 'ref_suffix' => 'BLU', 'qty' => 50],
        ],
    ],
    [
        'name' => 'Nova Studio Headphones', 'reference' => 'NSH-003', 'category' => CAT_HEADPHONES,
        'summary' => 'Professional-grade studio headphones with flat frequency response for accurate monitoring.',
        'description' => 'The Nova Studio Headphones deliver a flat, accurate frequency response trusted by audio professionals for mixing and mastering. Over-ear closed-back design isolates ambient noise, with a detachable coiled cable and a rugged metal headband.',
        'price' => 129.99,
        'seo_title' => 'Nova Studio Headphones | Volt & Circuit',
        'seo_description' => 'The Nova Studio Headphones offer flat frequency response and closed-back isolation, built for accurate studio monitoring.',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 40],
            ['group' => 'Color', 'value' => 'Silver', 'color' => '#C0C0C0', 'ref_suffix' => 'SLV', 'qty' => 40],
        ],
    ],
    [
        'name' => 'Echo Mini Speaker', 'reference' => 'EMS-004', 'category' => CAT_HEADPHONES,
        'summary' => 'Compact portable Bluetooth speaker with 12-hour battery and rich bass.',
        'description' => 'The Echo Mini Speaker packs surprisingly rich bass into a pocket-sized shell. Bluetooth 5.0, IPX5 splash resistance, and a 12-hour battery make it a reliable companion for outdoor listening.',
        'price' => 39.99,
        'seo_title' => '', // seeded issue: missing meta tags
        'seo_description' => '',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 30],
            ['group' => 'Color', 'value' => 'Red', 'color' => '#D0312D', 'ref_suffix' => 'RED', 'qty' => 30],
            ['group' => 'Color', 'value' => 'White', 'color' => '#FFFFFF', 'ref_suffix' => 'WHT', 'qty' => 30],
        ],
    ],

    // --- Chargers & Cables ---
    [
        'name' => 'Voltix 65W GaN Charger', 'reference' => 'VGC-005', 'category' => CAT_CHARGERS,
        'summary' => 'Compact 65W GaN charger with USB-C PD, fast-charges laptops and phones alike.',
        'description' => 'The Voltix 65W GaN Charger uses gallium nitride technology to pack desktop-charger power into a compact shell smaller than your palm. USB-C Power Delivery fast-charges laptops, tablets, and phones from a single port.',
        'price' => 34.99,
        'seo_title' => 'Voltix 65W GaN Charger | Volt & Circuit',
        'seo_description' => 'The Voltix 65W GaN Charger fast-charges laptops and phones from a compact, travel-friendly USB-C PD charger.',
        'combinations' => [],
    ],
    [
        'name' => 'Flexi USB-C Cable 2m', 'reference' => 'FUC-006', 'category' => CAT_CHARGERS,
        'summary' => 'Braided 2-meter USB-C cable rated for 100W fast charging and 480Mbps data transfer.',
        'description' => 'The Flexi USB-C Cable is a durable braided nylon cable rated for 100W fast charging and 480Mbps data transfer, at a generous 2-meter length so you can charge from across the room.',
        'price' => 12.99,
        'seo_title' => '', // seeded issue: missing meta tags
        'seo_description' => '',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 100],
            ['group' => 'Color', 'value' => 'White', 'color' => '#FFFFFF', 'ref_suffix' => 'WHT', 'qty' => 100],
        ],
    ],
    [
        'name' => 'Powerbank 10000mAh', 'reference' => 'PWB-007', 'category' => CAT_CHARGERS,
        'summary' => 'Slim portable power bank with USB-C PD fast charging.',
        'description' => 'The Powerbank 10000mAh is slim enough to slip into any pocket while still delivering enough charge for a full day away from an outlet. USB-C Power Delivery in and out for fast, versatile charging.',
        'price' => 44.99,
        'seo_title' => 'Powerbank 10000mAh | Volt & Circuit',
        'seo_description' => 'A slim 10000mAh power bank with USB-C PD fast charging, available in 10000mAh and 20000mAh capacities.',
        'combinations' => [
            ['group' => 'Capacity', 'value' => '10000mAh', 'ref_suffix' => '10K', 'qty' => 60],
            ['group' => 'Capacity', 'value' => '20000mAh', 'ref_suffix' => '20K', 'qty' => 60],
        ],
    ],
    [
        'name' => 'Wireless Charging Pad', 'reference' => 'WCP-008', 'category' => CAT_CHARGERS,
        'summary' => '15W Qi wireless charging pad with anti-slip surface and LED indicator.',
        'description' => 'The Wireless Charging Pad delivers up to 15W of Qi wireless charging with an anti-slip silicone surface and a subtle LED indicator that dims automatically once charging completes.',
        'price' => 24.99,
        'seo_title' => 'Wireless Charging Pad | Volt & Circuit',
        'seo_description' => '15W Qi wireless charging pad with anti-slip surface, available in black and white.',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 70],
            ['group' => 'Color', 'value' => 'White', 'color' => '#FFFFFF', 'ref_suffix' => 'WHT', 'qty' => 70],
        ],
    ],

    // --- Phone Cases ---
    [
        'name' => 'Guardian Clear Case', 'reference' => 'GCC-009', 'category' => CAT_CASES,
        'summary' => 'Slim clear case with reinforced corners for drop protection.',
        'description' => 'The Guardian Clear Case stays slim and transparent while reinforced corners absorb shock from drops up to 2 meters. A raised bezel protects the screen and camera from scratches.',
        'price' => 19.99,
        'seo_title' => '', // seeded issue: missing meta tags
        'seo_description' => '',
        'combinations' => [
            ['group' => 'Model', 'value' => 'iPhone 15', 'ref_suffix' => 'I15', 'qty' => 80],
            ['group' => 'Model', 'value' => 'iPhone 15 Pro', 'ref_suffix' => 'I15P', 'qty' => 80],
        ],
    ],
    [
        'name' => 'Terra Leather Case', 'reference' => 'TLC-010', 'category' => CAT_CASES,
        'summary' => 'Genuine leather case with card slot and magnetic closure.',
        'description' => 'The Terra Leather Case is crafted from genuine full-grain leather that develops a rich patina over time. A built-in card slot and magnetic closure add everyday practicality.',
        'price' => 29.99,
        'seo_title' => 'Terra Leather Case | Volt & Circuit',
        'seo_description' => 'Genuine leather phone case with card slot and magnetic closure, in brown and black for iPhone 15 and 15 Pro.',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Brown', 'color' => '#5C3A21', 'ref_suffix' => 'BRN-I15', 'qty' => 30, 'pair_group' => 'Model', 'pair_value' => 'iPhone 15'],
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK-I15', 'qty' => 30, 'pair_group' => 'Model', 'pair_value' => 'iPhone 15'],
            ['group' => 'Color', 'value' => 'Brown', 'color' => '#5C3A21', 'ref_suffix' => 'BRN-I15P', 'qty' => 30, 'pair_group' => 'Model', 'pair_value' => 'iPhone 15 Pro'],
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK-I15P', 'qty' => 30, 'pair_group' => 'Model', 'pair_value' => 'iPhone 15 Pro'],
        ],
    ],
    [
        'name' => 'Shield Rugged Case', 'reference' => 'SRC-011', 'category' => CAT_CASES,
        'summary' => 'Military-grade rugged case with built-in kickstand.',
        'description' => 'The Shield Rugged Case is tested to military drop-protection standards, with a built-in kickstand for hands-free viewing and raised edges that guard the screen and camera.',
        'price' => 22.99,
        'seo_title' => 'Shield Rugged Case | Volt & Circuit',
        'seo_description' => 'Military-grade rugged phone case with built-in kickstand and raised screen protection, in black and green.',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 45],
            ['group' => 'Color', 'value' => 'Green', 'color' => '#2E5E3E', 'ref_suffix' => 'GRN', 'qty' => 45],
        ],
    ],

    // --- Smartwatches ---
    [
        'name' => 'Chrono Fit Watch', 'reference' => 'CFW-012', 'category' => CAT_WATCHES,
        'summary' => 'Fitness smartwatch with heart-rate monitoring, GPS, and 7-day battery life.',
        'description' => 'The Chrono Fit Watch tracks heart rate, built-in GPS routes, sleep, and over 20 sport modes, all while lasting up to 7 days on a single charge. The always-on AMOLED display stays crisp in direct sunlight.',
        'price' => 149.99,
        'seo_title' => '', // seeded issue: missing meta tags
        'seo_description' => '',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 25],
            ['group' => 'Color', 'value' => 'Silver', 'color' => '#C0C0C0', 'ref_suffix' => 'SLV', 'qty' => 25],
            ['group' => 'Color', 'value' => 'Rose Gold', 'color' => '#B76E79', 'ref_suffix' => 'RSG', 'qty' => 25],
        ],
    ],
    [
        'name' => 'Pulse Health Tracker', 'reference' => 'PHT-013', 'category' => CAT_WATCHES,
        'summary' => 'Health-focused smartwatch with SpO2, sleep tracking, and AMOLED display.',
        'description' => 'The Pulse Health Tracker focuses on wellness: continuous SpO2 monitoring, detailed sleep-stage tracking, and stress alerts, shown on a vivid AMOLED display in a lightweight aluminum case.',
        'price' => 89.99,
        'seo_title' => 'Pulse Health Tracker | Volt & Circuit',
        'seo_description' => 'The Pulse Health Tracker monitors SpO2, sleep, and stress on a vivid AMOLED display, in black and white.',
        'combinations' => [
            ['group' => 'Color', 'value' => 'Black', 'color' => '#000000', 'ref_suffix' => 'BLK', 'qty' => 35],
            ['group' => 'Color', 'value' => 'White', 'color' => '#FFFFFF', 'ref_suffix' => 'WHT', 'qty' => 35],
        ],
    ],
];

foreach ($products as $data) {
    try {
        seedProduct($data);
    } catch (Throwable $e) {
        echo "FAIL  {$data['reference']} — ".$e->getMessage()."\n";
    }
}

echo "Done.\n";
