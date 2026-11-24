<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Reservations;

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

    // Le serveur de développement traite à part les adresses qui finissent par une extension.
    #[RequiresPhpExtension('pdo_sqlite')]
    public function testFichierAgendaTelecharge(): void
    {
        $debut = maintenant()->modify('+3 days');
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i'),
        ]);
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => 'Client de test', 'telephone' => '+237672894084', 'email' => ''], ['paiement_mode' => 'remise']);

        [$code, $corps, $entetes] = $this->demande('/reservation/' . $r['code'] . '/agenda.ics?j=' . $r['jeton']);

        $this->assertSame(200, $code);
        $this->assertNotEmpty(preg_grep('#^Content-Type: text/calendar#i', $entetes));
        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $corps);
        $this->assertStringContainsString($r['code'], $corps);
    }

    public static function fichiersDeLaRacineSeulement(): array
    {
        return [
            'robots.txt en anglais' => ['/en/robots.txt'],
            'plan du site en arabe' => ['/ar/sitemap.xml'],
        ];
    }

    // Les moteurs ne lisent ces fichiers qu'à la racine : une copie par langue ne sert à rien.
    #[DataProvider('fichiersDeLaRacineSeulement')]
    public function testFichierDeLaRacineIntrouvableSousUneLangue(string $chemin): void
    {
        $this->assertSame(404, $this->demande($chemin)[0]);
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
