<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Vérifie que le vrai serveur de développement ne sert que assets/ : les fichiers
// privés reçoivent la page introuvable du site.
final class ServeurTest extends TestCase
{
    use AvecServeur;

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

    public function testCookieDeSessionChiffreDerriereUnProxyHttps(): void
    {
        $cookie = $this->cookieDeSession(['X-Forwarded-Proto: https']);

        $this->assertMatchesRegularExpression('/;\s*secure(;|$)/i', $cookie);
        $this->assertMatchesRegularExpression('/;\s*httponly(;|$)/i', $cookie);
    }

    // Sans HTTPS, un cookie « secure » ne reviendrait jamais : l'espace équipe et les
    // formulaires ne marcheraient plus sur http://localhost.
    public function testCookieDeSessionEnHttpSimple(): void
    {
        $cookie = $this->cookieDeSession();

        $this->assertDoesNotMatchRegularExpression('/;\s*secure(;|$)/i', $cookie);
        $this->assertMatchesRegularExpression('/;\s*httponly(;|$)/i', $cookie);
    }

    private function cookieDeSession(array $entetes = []): string
    {
        [$code, , $recus] = $this->demande('/vehicules', null, null, $entetes);
        $this->assertSame(200, $code);
        $cookies = preg_grep('/^Set-Cookie: yamoo=/i', $recus);
        $this->assertCount(1, $cookies);
        return reset($cookies);
    }
}
