<?php
define('_PS_ADMIN_DIR_', '/var/www/html/admin');
require_once '/var/www/html/config/config.inc.php';
require_once '/var/www/html/init.php';

$reflection = new ReflectionClass('ProductAttribute');
echo "Class file: " . $reflection->getFileName() . "\n\n";

echo "Public properties:\n";
foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
    echo "  - {$prop->getName()}\n";
}

echo "\nMethods matching add|Attributes:\n";
foreach ($reflection->getMethods() as $method) {
    if (stripos($method->getName(), 'add') !== false) {
        echo "  - {$method->getName()}\n";
    }
}

// Try creating a real attribute and inspect it after add()
$group = new AttributeGroup();
$existing = (int) Db::getInstance()->getValue(
    'SELECT ag.id_attribute_group FROM '._DB_PREFIX_.'attribute_group ag
     JOIN '._DB_PREFIX_.'attribute_group_lang agl ON agl.id_attribute_group = ag.id_attribute_group
     WHERE agl.name = "DebugGroup" AND agl.id_lang = 1'
);
if ($existing) {
    $group = new AttributeGroup($existing);
} else {
    $group->is_color_group = false;
    $group->group_type = 'select';
    $group->name = [1 => 'DebugGroup'];
    $group->public_name = [1 => 'DebugGroup'];
    $group->add();
}
echo "\nGroup id: {$group->id}\n";

$attr = new ProductAttribute();
$attr->id_attribute_group = $group->id;
$attr->name = [1 => 'DebugValue'];
$result = $attr->add();
echo "add() result: " . var_export($result, true) . "\n";
echo "Attribute object dump:\n";
var_dump($attr);
