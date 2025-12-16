<?php
echo "Available PDO drivers:\n";
print_r(PDO::getAvailableDrivers());

echo "\nPHP Version: " . phpversion() . "\n";
echo "Loaded Extensions:\n";
print_r(get_loaded_extensions());
?>