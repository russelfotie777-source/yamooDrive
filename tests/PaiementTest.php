<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yamoo\Paiement\Demo;
use Yamoo\Paiement\NotchPay;
use Yamoo\Paiement\Paiement;

final class PaiementTest extends TestCase
{
    public static function numerosAcceptes(): array
    {
        return [
            'avec espaces' => ['6 72 89 40 84'],
            'avec indicatif' => ['+237 672 89 40 84'],
            'indicatif en 00' => ['00237672894084'],
            'indicatif sans +' => ['237672894084'],
            'avec tirets et points' => ['672-89.40.84'],
        ];
    }

    #[DataProvider('numerosAcceptes')]
    public function testNumeroMobileMoneyMisAuFormatInternational(string $saisie): void
    {
        $this->assertSame('+237672894084', Paiement::numero($saisie));
    }

    public static function numerosRefuses(): array
    {
        return [
            'vide' => [''],
            'huit chiffres' => ['72894084'],
            'fixe en 2' => ['233421234'],
            'dix chiffres' => ['6728940840'],
            'numéro français' => ['+33 6 12 34 56 78'],
        ];
    }

    #[DataProvider('numerosRefuses')]
    public function testNumeroQuiNEstPasUnMobileCamerounais(string $saisie): void
    {
        $this->assertNull(Paiement::numero($saisie));
    }

    public function testFournisseurDeDemonstrationPendantLesTests(): void
    {
        $this->assertInstanceOf(Demo::class, Paiement::fournisseur());
        $this->assertTrue(Paiement::fournisseur()->estDemo());
    }

    public function testDemoValideLePaiementApresQuelquesSecondes(): void
    {
        $demo = new Demo();
        $r = ['code' => 'YD-ABC234', 'paiement_numero' => '+237672894084'];

        $reference = $demo->lance($r, 'mtn', '+237672894084');
        $this->assertStringStartsWith('DEMO-YD-ABC234-', $reference);
        $this->assertSame('en_cours', $demo->statut($r + ['paiement_reference' => $reference]));
        $this->assertSame('paye', $demo->statut($r + ['paiement_reference' => 'DEMO-YD-ABC234-' . (time() - 60)]));
    }

    public function testDemoRefuseUnNumeroQuiFinitPar000(): void
    {
        $r = ['code' => 'YD-ABC234', 'paiement_numero' => '+237672894000', 'paiement_reference' => 'DEMO-YD-ABC234-' . (time() - 60)];

        $this->assertSame('echec', (new Demo())->statut($r));
    }

    public function testSignatureDuWebhookNotchPay(): void
    {
        $corps = '{"event":"payment.complete","data":{"reference":"trx.abc"}}';
        $signature = hash_hmac('sha256', $corps, 'hash-des-tests');

        $this->assertTrue(NotchPay::signatureValide($corps, $signature));
        $this->assertFalse(NotchPay::signatureValide($corps . ' ', $signature));
        $this->assertFalse(NotchPay::signatureValide($corps, hash_hmac('sha256', $corps, 'autre-hash')));
        $this->assertFalse(NotchPay::signatureValide($corps, null));
        $this->assertFalse(NotchPay::signatureValide($corps, ''));
    }
}
