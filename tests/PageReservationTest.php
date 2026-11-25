<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Reservations;

// La page qu'un client ouvre depuis le lien de sa réservation, servie par le vrai serveur.
#[RequiresPhpExtension('pdo_sqlite')]
final class PageReservationTest extends TestCase
{
    use AvecServeur;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
    }

    public static function paiements(): array
    {
        return [
            'à la remise des clés, noté par l’équipe' => [['paiement_mode' => 'remise'], 'Paiement reçu par l’équipe'],
            'MTN Mobile Money' => [['paiement_mode' => 'mobile', 'operateur' => 'mtn', 'numero' => '+237672894084'], 'Payé par MTN Mobile Money'],
            'Orange Money' => [['paiement_mode' => 'mobile', 'operateur' => 'orange', 'numero' => '+237699112233'], 'Payé par Orange Money'],
        ];
    }

    #[DataProvider('paiements')]
    public function testEtiquetteDuPaiementRecu(array $paiement, string $etiquette): void
    {
        $r = $this->cree($paiement);
        Reservations::maj($r['id'], ['statut' => 'confirmee', 'paiement_statut' => 'paye', 'expire_le' => null]);

        [$code, $corps] = $this->demande('/reservation/' . $r['code'] . '?j=' . $r['jeton']);

        $this->assertSame(200, $code);
        $this->assertStringContainsString('>' . $etiquette . '</p>', $corps);
        $this->assertSame(1, substr_count($corps, 'Payé par') + substr_count($corps, 'Paiement reçu par'));
    }

    public function testEtiquetteDuPaiementAttendu(): void
    {
        $r = $this->cree(['paiement_mode' => 'remise']);

        [, $corps] = $this->demande('/reservation/' . $r['code'] . '?j=' . $r['jeton']);

        $this->assertStringContainsString('>À payer à la remise des clés</p>', $corps);
        $this->assertStringNotContainsString('Payé par', $corps);
    }

    private function cree(array $paiement): array
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i'),
        ]);
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => 'Client de test', 'telephone' => '+237672894084', 'email' => ''], $paiement);
        return Reservations::parCode($r['code']);
    }
}
