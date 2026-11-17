<?php
// Les tests ne touchent jamais stockage/yamoo.sqlite : chaque lancement crée sa base
// dans le dossier temporaire et l'efface à la fin.

$base = tempnam(sys_get_temp_dir(), 'yamoo-test-');
unlink($base);
putenv("BASE_SQLITE=$base");
register_shutdown_function(function () use ($base) {
    foreach ([$base, "$base-wal", "$base-shm"] as $f) {
        if (is_file($f)) {
            unlink($f);
        }
    }
});

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['HTTP_HOST'] = 'localhost';

require dirname(__DIR__) . '/vendor/autoload.php';

date_default_timezone_set(config('fuseau'));
