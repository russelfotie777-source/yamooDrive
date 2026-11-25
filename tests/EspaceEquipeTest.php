<?php

namespace Yamoo\Tests;

use DateTimeImmutable;
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
