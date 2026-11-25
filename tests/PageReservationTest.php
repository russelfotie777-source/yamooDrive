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

    // Le paiement est arrivé trop tard ou le client a annulé : il refait la même location.
    public static function reservationsAFaireDeNouveau(): array
    {
        return [
            'paiement non reçu' => ['expiree'],
            'annulée' => ['annulee'],
        ];
    }

    #[DataProvider('reservationsAFaireDeNouveau')]
    public function testReserverDeNouveauGardeLesChoix(string $statut): void
    {
        $r = $this->cree(['paiement_mode' => 'mobile', 'operateur' => 'mtn', 'numero' => '+237672894084'], [
            'vehicule' => 'jetour-x50', 'formule' => 'sans', 'lieu' => 'douala-aeroport', 'lieu_retour' => 'yaounde-agence',
        ]);
        Reservations::maj($r['id'], ['statut' => $statut, 'expire_le' => null]);

        [$chemin, $params, $ancre] = $this->lienReserverDeNouveau($r);

        $this->assertSame('/vehicules/jetour-x50', $chemin);
        $this->assertSame([
            'formule' => 'sans',
            'lieu' => 'douala-aeroport',
            'lieu_retour' => 'yaounde-agence',
            'debut' => str_replace(' ', 'T', $r['debut']),
            'fin' => str_replace(' ', 'T', $r['fin']),
        ], $params);
        $this->assertSame('reserver', $ancre);
    }

    // Une location finie a des dates passées : le lien garde le reste et laisse choisir les dates.
    public function testReserverDeNouveauSansDatesPassees(): void
    {
        $r = $this->cree(['paiement_mode' => 'remise'], ['debut' => maintenant()->modify('-5 days')->setTime(9, 0)]);
        Reservations::maj($r['id'], ['statut' => 'terminee']);

        [$chemin, $params, $ancre] = $this->lienReserverDeNouveau($r);

        $this->assertSame('/vehicules/jetour-t2', $chemin);
        $this->assertSame(['formule' => 'avec', 'lieu' => 'douala-agence'], $params);
        $this->assertNull($ancre);
    }

    /** @return array{string, array, ?string} chemin, paramètres et ancre du bouton « Réserver de nouveau » */
    private function lienReserverDeNouveau(array $r): array
    {
        [$code, $corps] = $this->demande('/reservation/' . $r['code'] . '?j=' . $r['jeton']);
        $this->assertSame(200, $code);
        $this->assertSame(1, preg_match('#<a [^>]*href="([^"]+)"[^>]*>Réserver de nouveau</a>#', $corps, $m));
        $lien = parse_url(html_entity_decode($m[1]));
        parse_str($lien['query'] ?? '', $params);
        return [$lien['path'], $params, $lien['fragment'] ?? null];
    }

    private function cree(array $paiement, array $location = []): array
    {
        $debut = $location['debut'] ?? maintenant()->modify('+3 days')->setTime(9, 0);
        $d = Demande::depuis([
            'vehicule' => $location['vehicule'] ?? 'jetour-t2',
            'formule' => $location['formule'] ?? 'avec',
            'lieu' => $location['lieu'] ?? null,
            'lieu_retour' => $location['lieu_retour'] ?? null,
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i'),
        ]);
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => 'Client de test', 'telephone' => '+237672894084', 'email' => ''], $paiement);
        return Reservations::parCode($r['code']);
    }
}
