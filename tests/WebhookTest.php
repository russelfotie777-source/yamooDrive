<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Reservations;

// Notch Pay prévient le site par /api/notchpay quand un paiement change d'état. Passe par
// le vrai serveur, qui lit le hash des tests et reste sur le paiement de démonstration.
#[RequiresPhpExtension('pdo_sqlite')]
final class WebhookTest extends TestCase
{
    use AvecServeur;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
    }

    public function testSignatureInvalideRefusee(): void
    {
        $r = $this->enCours();

        [$code] = $this->signale($r['paiement_reference'], 'autre-hash');

        $this->assertSame(403, $code);
        $this->assertSame('en_cours', Reservations::parCode($r['code'])['paiement_statut']);
    }

    public function testPaiementSignaleConfirmeLaReservation(): void
    {
        $r = $this->enCours();

        [$code, $corps] = $this->signale($r['paiement_reference']);

        $this->assertSame(200, $code);
        $this->assertSame(['ok' => true], json_decode($corps, true));
        $relue = Reservations::parCode($r['code']);
        $this->assertSame('confirmee', $relue['statut']);
        $this->assertSame('paye', $relue['paiement_statut']);
    }

    // L'équipe a confirmé au téléphone avant que le client valide sur le sien.
    public function testPaiementSignaleApresLaConfirmationDeLEquipe(): void
    {
        $r = $this->enCours();
        Reservations::maj($r['id'], ['statut' => 'confirmee', 'expire_le' => null], 'Réservation confirmée par l’équipe.');

        [$code] = $this->signale($r['paiement_reference']);

        $this->assertSame(200, $code);
        $this->assertSame('paye', Reservations::parCode($r['code'])['paiement_statut']);
        $this->assertStringStartsWith('Paiement reçu', Reservations::journal($r['id'])[0]['detail']);
    }

    public function testReferenceInconnueSansEffet(): void
    {
        $r = $this->enCours();

        [$code] = $this->signale('trx.inconnue');

        $this->assertSame(200, $code);
        $this->assertSame('en_cours', Reservations::parCode($r['code'])['paiement_statut']);
    }

    /** @return array{int, string} code et corps de la réponse */
    private function signale(string $reference, string $hash = 'hash-des-tests'): array
    {
        $corps = json_encode(['event' => 'payment.complete', 'data' => ['reference' => $reference]]);
        $contexte = stream_context_create(['http' => [
            'method' => 'POST',
            'ignore_errors' => true,
            'timeout' => 5,
            'header' => ['Content-Type: application/json', 'X-Notch-Signature: ' . hash_hmac('sha256', $corps, $hash)],
            'content' => $corps,
        ]]);
        $reponse = file_get_contents(self::$adresse . '/api/notchpay', false, $contexte);
        preg_match('#^HTTP/\S+ (\d{3})#', $http_response_header[0] ?? '', $m);
        return [(int) ($m[1] ?? 0), (string) $reponse];
    }

    // Réservation dont la demande de paiement de démonstration est partie il y a une
    // minute : le fournisseur la donne désormais pour payée.
    private function enCours(): array
    {
        $debut = maintenant()->modify('+3 days');
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i'),
        ]);
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => 'Client de test', 'telephone' => '+237672894084', 'email' => ''], [
            'paiement_mode' => 'mobile',
            'operateur' => 'mtn',
            'numero' => '+237672894084',
        ]);
        Reservations::maj($r['id'], ['paiement_reference' => "DEMO-{$r['code']}-" . (time() - 60), 'paiement_statut' => 'en_cours']);
        return Reservations::parCode($r['code']);
    }
}
