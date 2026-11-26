<?php

namespace Yamoo\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Reservations;

// L'espace équipe servi par le vrai serveur, avec le mot de passe de tests/bootstrap.php.
#[RequiresPhpExtension('pdo_sqlite')]
final class EspaceEquipeTest extends TestCase
{
    use AvecServeur;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites; DELETE FROM essais_connexion;');
    }

    public function testConnexionOuvreLesPagesDeLEquipe(): void
    {
        $cookie = $this->connecteEquipe();

        [$code, $corps] = $this->demande('/equipe/flotte', null, $cookie);

        $this->assertSame(200, $code);
        $this->assertStringContainsString('<h1 class="outil-titre">Flotte</h1>', $corps);
    }

    public function testSansConnexionRetourALaPageDeConnexion(): void
    {
        [$code, , $entetes] = $this->demande('/equipe/flotte');

        $this->assertSame(303, $code);
        $this->assertContains('Location: /equipe', $entetes);
        $this->assertStringContainsString('Connexion de l’équipe', $this->demande('/equipe')[1]);
    }

    // Un retour prévu à 23 h 30 tombe le même jour, même si l'heure de tolérance déborde
    // sur le lendemain ; chaque réservation mène à sa page.
    public function testReservationsAVenirDeLaFlotte(): void
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        $confirmee = $this->reserve($debut, $debut->modify('+2 days')->setTime(23, 30), 'confirmee');
        $aConfirmer = $this->reserve($debut->modify('+5 days'), $debut->modify('+6 days'), 'demande');

        [, $corps] = $this->demande('/equipe/flotte', null, $this->connecteEquipe());

        $this->assertStringContainsString(
            '<a class="code" href="/equipe/reservations/' . $confirmee['code'] . '">' . $confirmee['code'] . '</a> · '
            . date_texte($debut, 'dMMM') . ' – ' . date_texte($debut->modify('+2 days'), 'dMMM') . '</span>',
            $corps
        );
        $this->assertStringContainsString(
            '>' . $aConfirmer['code'] . '</a> · ' . date_texte($debut->modify('+5 days'), 'dMMM') . ' – ' . date_texte($debut->modify('+6 days'), 'dMMM')
            . ' <span class="statut statut-demande">À confirmer</span>',
            $corps
        );
    }

    public function testAuDelaDeTroisReservationsLaFlotteDitCombienSuivent(): void
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        for ($i = 0; $i < 5; $i++) {
            $this->reserve($debut->modify('+' . (2 * $i) . ' days'), $debut->modify('+' . (2 * $i + 1) . ' days'), 'confirmee');
        }

        [, $corps] = $this->demande('/equipe/flotte', null, $this->connecteEquipe());

        $this->assertSame(3, substr_count($corps, 'href="/equipe/reservations/'));
        $this->assertStringContainsString('<span class="discret">et 2 autres</span>', $corps);
    }

    public function testBlocageDeDatesLibresConfirme(): void
    {
        $du = maintenant()->modify('+30 days');

        $page = $this->bloque($du, $du->modify('+1 day'));

        $this->assertStringContainsString('<p class="message message-succes" role="status">', $page);
        $this->assertStringContainsString('Jetour T2 bloqué du ' . date_texte($du, 'dMMM') . ' au ' . date_texte($du->modify('+1 day'), 'dMMM') . ' inclus.</p>', $page);
        $this->assertSame(1, $this->blocages());
    }

    // Une panne n'attend pas : le blocage est gardé, mais l'équipe doit prévenir les clients.
    public function testBlocageSurUneReservationPrevientLEquipe(): void
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        $r = $this->reserve($debut, $debut->modify('+2 days'), 'confirmee');
        $this->reserve($debut->modify('+10 days'), $debut->modify('+11 days'), 'confirmee');

        $page = $this->bloque($debut->modify('+1 day'), $debut->modify('+1 day'));

        $this->assertStringContainsString('<p class="message message-alerte" role="alert">', $page);
        $this->assertSame(1, preg_match('#<p class="message message-alerte".*?</p>#s', $page, $m));
        $this->assertStringContainsString('<a class="code" href="/equipe/reservations/' . $r['code'] . '">' . $r['code'] . '</a>', $m[0]);
        $this->assertStringContainsString('a déjà ce véhicule sur ces dates : prévenez le client', $m[0]);
        $this->assertSame(1, substr_count($m[0], '<a '));
        $this->assertStringNotContainsString('message-succes', $page);
        $this->assertSame(1, $this->blocages());
    }

    public static function blocagesRefuses(): array
    {
        return [
            'dates dans le désordre' => ['+5 days', '+3 days', 'Choisissez un véhicule et deux dates dans l’ordre.'],
            'dates passées' => ['-5 days', '-3 days', 'Ces dates sont passées. Choisissez des dates à venir.'],
        ];
    }

    #[DataProvider('blocagesRefuses')]
    public function testBlocageRefuseEnErreur(string $du, string $au, string $message): void
    {
        $page = $this->bloque(maintenant()->modify($du), maintenant()->modify($au));

        $this->assertStringContainsString('<p class="message message-erreur" role="alert">', $page);
        $this->assertStringContainsString($message . '</p>', $page);
        $this->assertStringNotContainsString('message-succes', $page);
        $this->assertSame(0, $this->blocages());
    }

    // L'équipe écrit en français, mais le premier message part dans la langue du client,
    // avec le lien de sa réservation dans cette langue.
    public function testMessageAuClientDansSaLangue(): void
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        $r = $this->reserve($debut, $debut->modify('+2 days'), 'confirmee');
        Reservations::maj($r['id'], ['langue' => 'en']);

        [$code, $page] = $this->demande('/equipe/reservations/' . $r['code'], null, $this->connecteEquipe());

        $this->assertSame(200, $code);
        $this->assertSame(1, preg_match('#href="https://wa\.me/237672894084\?text=([^"]+)"[^>]*>.*?Écrire au client</a>#s', $page, $m));
        $this->assertSame(
            "Hello Client de test, this is Yamoo Drive about your booking {$r['code']}.\n"
            . 'Your booking link: ' . self::$adresse . "/en/reservation/{$r['code']}?j={$r['jeton']}",
            rawurldecode(html_entity_decode($m[1]))
        );
    }

    // Sous /en/equipe, les libellés français côtoieraient des dates et des montants à
    // l'anglaise : l'espace équipe n'a qu'une adresse, à la racine.
    public static function adressesSousUneLangue(): array
    {
        return [
            'réservations' => ['/en/equipe', '/equipe'],
            'flotte' => ['/ar/equipe/flotte', '/equipe/flotte'],
            'recherche' => ['/de/equipe?q=672', '/equipe?q=672'],
            'une réservation' => ['/zh/equipe/reservations/YD-ABC234', '/equipe/reservations/YD-ABC234'],
        ];
    }

    #[DataProvider('adressesSousUneLangue')]
    public function testEspaceEquipeSeulementALaRacine(string $adresse, string $attendue): void
    {
        [$code, , $entetes] = $this->demande($adresse);

        $this->assertSame(308, $code);
        $this->assertContains("Location: $attendue", $entetes);
    }

    // Une page de connexion ouverte sous /en/equipe avant ce renvoi envoie son formulaire
    // là : le 308 le fait repartir tel quel vers la racine, et la connexion aboutit.
    public function testConnexionEnvoyeeSousUneLangueAboutit(): void
    {
        [, $corps, $entetes] = $this->demande('/equipe');
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $corps, $csrf);
        $cookie = self::cookieDeSession($entetes);
        $formulaire = ['_csrf' => $csrf[1], 'mot_de_passe' => getenv('ADMIN_MOT_DE_PASSE')];

        [$code, , $entetes] = $this->demande('/en/equipe/connexion', $formulaire, $cookie);
        $this->assertSame(308, $code);
        $this->assertContains('Location: /equipe/connexion', $entetes);

        [$code, , $entetes] = $this->demande('/equipe/connexion', $formulaire, $cookie);
        $this->assertSame(303, $code);
        $this->assertStringContainsString('Se déconnecter', $this->demande('/equipe', null, self::cookieDeSession($entetes) ?? $cookie)[1]);
    }

    public function testMauvaisMotDePasseRefuse(): void
    {
        [, $corps, $entetes] = $this->demande('/equipe');
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $corps, $csrf);
        $cookie = self::cookieDeSession($entetes);

        $this->demande('/equipe/connexion', ['_csrf' => $csrf[1], 'mot_de_passe' => 'pas-le-bon'], $cookie);

        [, $page] = $this->demande('/equipe', null, $cookie);
        $this->assertStringContainsString('Mot de passe incorrect', $page);
        $this->assertSame(303, $this->demande('/equipe/flotte', null, $cookie)[0]);
    }

    /** Bloque le Jetour T2 depuis la page Flotte et rend la page affichée ensuite. */
    private function bloque(DateTimeImmutable $du, DateTimeImmutable $au): string
    {
        $cookie = $this->connecteEquipe();
        [, $page] = $this->demande('/equipe/flotte', null, $cookie);
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page, $csrf);
        [$code, , $entetes] = $this->demande('/equipe/flotte', [
            '_csrf' => $csrf[1],
            'vehicule' => 'jetour-t2',
            'debut' => $du->format('Y-m-d'),
            'fin' => $au->format('Y-m-d'),
            'motif' => 'Vidange',
        ], $cookie);
        $this->assertSame(303, $code);
        $this->assertContains('Location: /equipe/flotte', $entetes);
        return $this->demande('/equipe/flotte', null, $cookie)[1];
    }

    private function blocages(): int
    {
        return (int) Base::pdo()->query('SELECT COUNT(*) FROM indisponibilites')->fetchColumn();
    }

    private function reserve(DateTimeImmutable $debut, DateTimeImmutable $fin, string $statut): array
    {
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $fin->format('Y-m-d\TH:i'),
        ]);
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => 'Client de test', 'telephone' => '+237672894084', 'email' => ''], ['paiement_mode' => 'remise']);
        Reservations::maj($r['id'], ['statut' => $statut]);
        return Reservations::parCode($r['code']);
    }
}
