<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yamoo\Langue;

// Le site en ligne passera sans doute derrière un proxy ou un CDN qui chiffre à sa place :
// PHP n'y voit que du HTTP, et seul X-Forwarded-Proto dit que le visiteur est en HTTPS.
final class HttpsTest extends TestCase
{
    private array $serveur;

    protected function setUp(): void
    {
        $this->serveur = $_SERVER;
        unset($_SERVER['HTTPS'], $_SERVER['HTTP_X_FORWARDED_PROTO']);
        Langue::$code = 'fr';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serveur;
    }

    public static function requetes(): array
    {
        return [
            'Apache en HTTPS' => [['HTTPS' => 'on'], true],
            'serveur qui met HTTPS à 1' => [['HTTPS' => '1'], true],
            'IIS en HTTP simple' => [['HTTPS' => 'off'], false],
            'proxy HTTPS' => [['HTTP_X_FORWARDED_PROTO' => 'https'], true],
            'proxy qui écrit en majuscules' => [['HTTP_X_FORWARDED_PROTO' => 'HTTPS'], true],
            'deux proxys, le visiteur en HTTPS' => [['HTTP_X_FORWARDED_PROTO' => 'https, http'], true],
            'deux proxys, le visiteur en HTTP' => [['HTTP_X_FORWARDED_PROTO' => 'http, https'], false],
            'proxy HTTP' => [['HTTP_X_FORWARDED_PROTO' => 'http'], false],
            'IIS derrière un proxy HTTPS' => [['HTTPS' => 'off', 'HTTP_X_FORWARDED_PROTO' => 'https'], true],
            'HTTP simple' => [[], false],
        ];
    }

    #[DataProvider('requetes')]
    public function testDetectionDuHttps(array $serveur, bool $attendu): void
    {
        $_SERVER = $serveur + $_SERVER;

        $this->assertSame($attendu, en_https());
    }

    public function testAdresseAbsolueDerriereUnProxyHttps(): void
    {
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $_SERVER['HTTP_HOST'] = 'yamoodrive.com';

        $this->assertSame('https://yamoodrive.com/vehicules', url_absolue('/vehicules'));
    }

    public function testAdresseAbsolueEnHttpSimple(): void
    {
        $_SERVER['HTTP_HOST'] = 'localhost';

        $this->assertSame('http://localhost/vehicules', url_absolue('/vehicules'));
    }
}
