<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Lance le vrai serveur de développement (php -S index.php) et vérifie qu'il ne sert
// que assets/ : les fichiers privés reçoivent la page introuvable du site.
final class ServeurTest extends TestCase
{
    private static $processus;
    private static string $adresse;

    public static function setUpBeforeClass(): void
    {
        $prise = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($prise, false), ':'), 1);
        fclose($prise);
        self::$adresse = "http://127.0.0.1:$port";

        $racine = dirname(__DIR__);
        self::$processus = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $racine, "$racine/index.php"],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $tuyaux,
            $racine,
            getenv() + ['APP_DEBUG' => '0']
        );
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
            usleep(20000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        proc_terminate(self::$processus);
        proc_close(self::$processus);
    }

    public static function fichiersPrives(): array
    {
        return [
            'exemple de .env' => ['/.env.exemple'],
            'dépôt git' => ['/.git/HEAD'],
            'réglages' => ['/config/app.php'],
            'traductions' => ['/langues/textes.php'],
            'stockage' => ['/stockage/.htaccess'],
            'dépendances' => ['/composer.lock'],
            'remontée depuis assets' => ['/assets/../composer.json'],
        ];
    }

    #[DataProvider('fichiersPrives')]
    public function testFichierPriveIntrouvable(string $chemin): void
    {
        [$code, $corps] = $this->demande($chemin);

        $this->assertSame(404, $code);
        $this->assertStringContainsString('<html', $corps);
    }

    public function testFeuilleDeStyleServie(): void
    {
        [$code, $corps] = $this->demande('/assets/css/site.css');

        $this->assertSame(200, $code);
        $this->assertSame(file_get_contents(dirname(__DIR__) . '/assets/css/site.css'), $corps);
    }

    public function testPagesDuSiteServies(): void
    {
        $this->assertSame(200, $this->demande('/vehicules')[0]);
        $this->assertSame(200, $this->demande('/robots.txt')[0]);
    }

    /** @return array{int, string} */
    private function demande(string $chemin): array
    {
        $corps = file_get_contents(self::$adresse . $chemin, false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]));
        preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m);
        return [(int) ($m[1] ?? 0), (string) $corps];
    }
}
