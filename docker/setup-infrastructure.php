<?php
require dirname(__DIR__).'/backend/app/InfrastructureEnvironment.php';
try {
    App\InfrastructureEnvironment::prepare(dirname(__DIR__));
    echo "Local infrastructure secrets are ready. Existing values were preserved.\n";
} catch (RuntimeException $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}

