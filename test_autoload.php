<?php
// Test autoload pour vérifier les classes Resources
echo "Démarrage du test autoload...\n";

try {
    require 'vendor/autoload.php';
    echo "✓ Autoload chargé\n";
    
    if (class_exists('App\Http\Resources\UserResource')) {
        echo "✓ UserResource trouvée\n";
    } else {
        echo "✗ UserResource NOT trouvée\n";
    }
    
    if (class_exists('App\Http\Resources\CountryResource')) {
        echo "✓ CountryResource trouvée\n";
    } else {
        echo "✗ CountryResource NOT trouvée\n";
    }
    
    echo "Test terminé avec succès!\n";
} catch (\Exception $e) {
    echo "✗ ERREUR: " . $e->getMessage() . "\n";
}
