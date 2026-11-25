<?php

namespace Yamoo\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Disponibilite;
use Yamoo\Flotte;

// Base jetable créée par tests/bootstrap.php (BASE_SQLITE), vidée avant chaque test.
#[RequiresPhpExtension('pdo_sqlite')]
final class DisponibiliteTest extends TestCase
{
    private array $v;
    private DateTimeImmutable $lundi;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
        $this->v = Flotte::trouve('jetour-t2');
        $this->lundi = maintenant()->modify('next monday')->modify('+7 days')->setTime(9, 0);
    }

    public function testLibreSansReservation(): void
    {
        $this->assertTrue($this->libre('+0 hours', '+2 days'));
        $this->assertSame([], Disponibilite::occupations('jetour-t2'));
    }

    public function testReservationConfirmeeOccupeLeVehicule(): void
    {
        $this->reserve('+0 hours', '+2 days', 'confirmee');

        $this->assertFalse($this->libre('+1 day', '+3 days'));
        $this->assertFalse($this->libre('-1 day', '+1 hour'));
        $this->assertTrue($this->libre('+3 days', '+4 days'));
    }

    public function testLHeureDeToleranceResteAuClient(): void
    {
        $this->reserve('+0 hours', '+2 days', 'confirmee');
        $tolerance = config('regles.tolerance_retour_minutes');

        $this->assertFalse($this->libre('+2 days', '+3 days'));
        $this->assertFalse($this->libre('+2 days +' . ($tolerance - 1) . ' minutes', '+3 days'));
        $this->assertTrue($this->libre("+2 days +$tolerance minutes", '+3 days'));
    }

    public function testDemandeAConfirmerOccupeAussi(): void
    {
        $this->reserve('+0 hours', '+1 day', 'demande');

        $this->assertFalse($this->libre('+0 hours', '+1 day'));
    }

    public function testPaiementEnAttenteBloqueJusquaSonExpiration(): void
    {
        $this->reserve('+0 hours', '+1 day', 'attente_paiement', maintenant()->modify('+20 minutes'));
        $this->assertFalse($this->libre('+0 hours', '+1 day'));

        Base::pdo()->exec('DELETE FROM reservations');
        $this->reserve('+0 hours', '+1 day', 'attente_paiement', maintenant()->modify('-1 minute'));
        $this->assertTrue($this->libre('+0 hours', '+1 day'));
    }

    public function testReservationsTermineesOuAnnuleesNeComptentPas(): void
    {
        foreach (['annulee', 'terminee', 'expiree'] as $statut) {
            $this->reserve('+0 hours', '+1 day', $statut);
        }

        $this->assertTrue($this->libre('+0 hours', '+1 day'));
    }

    public function testBlocageDeLEquipe(): void
    {
        Base::pdo()->prepare('INSERT INTO indisponibilites (vehicule, debut, fin, motif, cree_le) VALUES (?, ?, ?, ?, ?)')
            ->execute(['jetour-t2', $this->a('+1 day'), $this->a('+2 days'), 'Vidange', $this->a('-7 days')]);

        $this->assertFalse($this->libre('+0 hours', '+1 day +1 hour'));
        $this->assertTrue($this->libre('+0 hours', '+1 day'));
        $this->assertSame('blocage', Disponibilite::occupations('jetour-t2')[0]['type']);
    }

    public function testAutreVehiculeNonConcerne(): void
    {
        $this->reserve('+0 hours', '+2 days', 'confirmee', null, 'jetour-x50');

        $this->assertTrue($this->libre('+0 hours', '+2 days'));
    }

    public function testUneReservationNeSeGenePasElleMeme(): void
    {
        $id = $this->reserve('+0 hours', '+2 days', 'confirmee');

        $this->assertTrue(Disponibilite::estLibre($this->v, $this->lundi, $this->lundi->modify('+2 days'), $id));
    }

    public function testLibreAPartirDeLaFinPlusLaTolerance(): void
    {
        $this->reserve('+0 hours', '+2 days', 'confirmee');
        $this->reserve('+3 days', '+5 days', 'confirmee');
        $tolerance = config('regles.tolerance_retour_minutes');

        $libre = Disponibilite::libreA($this->v, $this->lundi->modify('+1 day'), $this->lundi->modify('+4 days'));

        $this->assertSame($this->a("+5 days +$tolerance minutes"), $libre->format('Y-m-d H:i'));
        $this->assertNull(Disponibilite::libreA($this->v, $this->lundi->modify('+6 days'), $this->lundi->modify('+7 days')));
    }

    public function testEtatActuel(): void
    {
        $this->assertNull(Disponibilite::etatActuel($this->v));

        $fin = maintenant()->modify('+1 day')->setTime(18, 0);
        $this->insere(maintenant()->modify('-1 day')->format('Y-m-d H:i'), $fin->format('Y-m-d H:i'), 'confirmee');

        $tolerance = config('regles.tolerance_retour_minutes');
        $this->assertSame($fin->modify("+$tolerance minutes")->format('Y-m-d H:i'), Disponibilite::etatActuel($this->v)->format('Y-m-d H:i'));
    }

    // Retour prévu il y a une demi-heure : le client a encore son heure de tolérance, le
    // véhicule reste en location jusqu'à sa fin.
    public function testLocationQuiVientDeFinirGardeSonHeureDeTolerance(): void
    {
        $tolerance = config('regles.tolerance_retour_minutes');
        $fin = maintenant()->modify('-' . intdiv($tolerance, 2) . ' minutes');
        $this->insere(maintenant()->modify('-2 days')->format('Y-m-d H:i'), $fin->format('Y-m-d H:i'), 'confirmee');
        $rendu = $fin->modify("+$tolerance minutes");

        $this->assertSame($rendu->format('Y-m-d H:i'), Disponibilite::etatActuel($this->v)?->format('Y-m-d H:i'));
        $this->assertFalse(Disponibilite::estLibre($this->v, maintenant(), maintenant()->modify('+1 day')));
        $this->assertTrue(Disponibilite::estLibre($this->v, $rendu, $rendu->modify('+1 day')));
        $this->assertContains(maintenant()->format('Y-m-d'), Disponibilite::joursPris($this->v));
    }

    public function testLocationDontLaToleranceEstPasseeNeComptePlus(): void
    {
        $tolerance = config('regles.tolerance_retour_minutes');
        $fin = maintenant()->modify('-' . ($tolerance + 1) . ' minutes');
        $this->insere(maintenant()->modify('-2 days')->format('Y-m-d H:i'), $fin->format('Y-m-d H:i'), 'confirmee');

        $this->assertNull(Disponibilite::etatActuel($this->v));
        $this->assertSame([], Disponibilite::occupations('jetour-t2'));
    }

    public function testJoursPrisPourLeCalendrier(): void
    {
        // Du lundi 9 h au mercredi 9 h : lundi, mardi et mercredi (heure de tolérance comprise).
        $this->reserve('+0 hours', '+2 days', 'confirmee');

        $this->assertSame(
            [$this->lundi->format('Y-m-d'), $this->lundi->modify('+1 day')->format('Y-m-d'), $this->lundi->modify('+2 days')->format('Y-m-d')],
            Disponibilite::joursPris($this->v)
        );
    }

    private function libre(string $debut, string $fin): bool
    {
        return Disponibilite::estLibre($this->v, $this->lundi->modify($debut), $this->lundi->modify($fin));
    }

    private function a(string $decalage): string
    {
        return $this->lundi->modify($decalage)->format('Y-m-d H:i');
    }

    private function reserve(string $debut, string $fin, string $statut, ?DateTimeImmutable $expire = null, string $vehicule = 'jetour-t2'): int
    {
        return $this->insere($this->a($debut), $this->a($fin), $statut, $expire, $vehicule);
    }

    private function insere(string $debut, string $fin, string $statut, ?DateTimeImmutable $expire = null, string $vehicule = 'jetour-t2'): int
    {
        static $n = 0;
        $n++;
        $pdo = Base::pdo();
        $pdo->prepare(
            "INSERT INTO reservations (code, jeton, vehicule, formule, lieu_depart, lieu_retour, debut, fin, jours, prix_jour,
                montant_location, total, client_nom, client_telephone, paiement_mode, statut, expire_le, cree_le, maj_le)
             VALUES (?, 'jeton', ?, 'sans', 'douala-agence', 'douala-agence', ?, ?, 1, 1, 1, 1, 'Client de test', '+237600000000', ?, ?, ?, ?, ?)"
        )->execute([
            sprintf('YD-TEST%02d', $n), $vehicule, $debut, $fin,
            $statut === 'attente_paiement' ? 'mobile' : 'remise', $statut, $expire?->format('Y-m-d H:i'),
            maintenant()->format('Y-m-d H:i:s'), maintenant()->format('Y-m-d H:i:s'),
        ]);
        return (int) $pdo->lastInsertId();
    }
}
