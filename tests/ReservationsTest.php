<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Paiement\Paiement;
use Yamoo\Reservations;

// Cycle d'une réservation payée par Mobile Money, sur la base jetable des tests.
#[RequiresPhpExtension('pdo_sqlite')]
final class ReservationsTest extends TestCase
{
    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
    }

    public function testReservationMobileEnAttenteDuPaiement(): void
    {
        $r = $this->cree();

        $this->assertMatchesRegularExpression('/^YD-[A-Z0-9]{6}$/', $r['code']);
        $this->assertSame('attente_paiement', $r['statut']);
        $minutes = config('regles.attente_paiement_minutes');
        $this->assertSame(maintenant()->modify("+$minutes minutes")->format('Y-m-d H:i'), $r['expire_le']);
    }

    public function testPaiementALaRemiseDesClesSansDelai(): void
    {
        $r = $this->cree(['paiement_mode' => 'remise']);

        $this->assertSame('demande', $r['statut']);
        $this->assertNull($r['expire_le']);
    }

    public function testLeLienClientExigeLeJeton(): void
    {
        $r = $this->cree();

        $this->assertNull(Reservations::pourClient($r['code'], null));
        $this->assertNull(Reservations::pourClient($r['code'], 'mauvais-jeton'));
        $this->assertSame($r['id'], Reservations::pourClient(strtolower($r['code']), $r['jeton'])['id']);
    }

    public function testPaiementRecuConfirmeLaReservation(): void
    {
        $r = $this->enCours($this->cree(), time() - 60);

        $r = Paiement::verifie($r);

        $this->assertSame('confirmee', $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertNull($r['expire_le']);
        $this->assertStringStartsWith('Paiement reçu', Reservations::journal($r['id'])[0]['detail']);
    }

    public function testPaiementEnCoursNeChangeRien(): void
    {
        $r = $this->enCours($this->cree(), time());

        $this->assertSame('attente_paiement', Paiement::verifie($r)['statut']);
    }

    public function testPaiementRefuseGardeLeVehiculeJusquAuDelai(): void
    {
        $r = $this->enCours($this->cree(['numero' => '+237672894000']), time() - 60);

        $r = Paiement::verifie($r);

        $this->assertSame('attente_paiement', $r['statut']);
        $this->assertSame('echec', $r['paiement_statut']);
    }

    public function testDelaiDepasseLibereLeVehicule(): void
    {
        $r = $this->cree();
        $autre = $this->cree();
        Reservations::maj($r['id'], ['expire_le' => maintenant()->modify('-1 minute')->format('Y-m-d H:i')]);

        Reservations::expire();

        $this->assertSame('expiree', Reservations::parCode($r['code'])['statut']);
        $this->assertSame('echec', Reservations::parCode($r['code'])['paiement_statut']);
        $this->assertSame('attente_paiement', Reservations::parCode($autre['code'])['statut']);
    }

    public function testAnnulationGratuiteJusquA24HeuresAvantLeDepart(): void
    {
        $heures = config('regles.annulation_gratuite_heures');
        $loin = $this->cree(['paiement_mode' => 'remise']);
        $proche = $this->cree(['paiement_mode' => 'remise'], '+' . ($heures - 1) . ' hours');

        $this->assertTrue(Reservations::annulableParClient($loin));
        $this->assertFalse(Reservations::annulableParClient($proche));
    }

    public function testPasDAnnulationClientPendantLePaiement(): void
    {
        $this->assertFalse(Reservations::annulableParClient($this->cree()));
    }

    private function cree(array $extra = [], string $depart = '+3 days'): array
    {
        $debut = maintenant()->modify($depart);
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i'),
        ]);
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => 'Client de test', 'telephone' => '+237672894084', 'email' => ''], $extra + [
            'paiement_mode' => 'mobile',
            'operateur' => 'mtn',
            'numero' => '+237672894084',
        ]);
        return Reservations::parCode($r['code']);
    }

    // Paiement lancé auprès de la démonstration à l'instant donné.
    private function enCours(array $r, int $lance): array
    {
        Reservations::maj($r['id'], ['paiement_reference' => "DEMO-{$r['code']}-$lance", 'paiement_statut' => 'en_cours']);
        return Reservations::parCode($r['code']);
    }
}
