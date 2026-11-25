<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Reservations;

// Le paiement a été refusé (solde insuffisant, code oublié) : le client garde la page
// ouverte, recharge son compte et revient relancer le paiement ou choisir de payer à la
// remise des clés. Passe par le vrai serveur, avec la session et le jeton du formulaire.
#[RequiresPhpExtension('pdo_sqlite')]
final class RelancePaiementTest extends TestCase
{
    use AvecServeur;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
    }

    public static function choix(): array
    {
        return [
            'relancer le paiement' => ['relancer', 'attente_paiement', '/paiement/'],
            'payer à la remise des clés' => ['remise', 'demande', '/reservation/'],
        ];
    }

    #[DataProvider('choix')]
    public function testChoixPrisDansLeDelai(string $choix, string $statut, string $page): void
    {
        $r = $this->refusee();
        $formulaire = $this->ouvre($r);

        [$code, , $entetes] = $this->envoie($r, $choix, $formulaire);

        $this->assertSame(303, $code);
        $this->assertContains('Location: ' . $page . $r['code'] . '?j=' . $r['jeton'], $entetes);
        $this->assertSame($statut, Reservations::parCode($r['code'])['statut']);
    }

    // Pendant que la page restait ouverte, le délai est passé et un autre client a pris
    // le véhicule libéré : ni la relance ni la remise des clés ne doivent le reprendre.
    #[DataProvider('choix')]
    public function testChoixRefuseApresLeDelaiQuandLeVehiculeEstRepris(string $choix): void
    {
        $r = $this->refusee();
        $formulaire = $this->ouvre($r);
        Reservations::maj($r['id'], ['expire_le' => maintenant()->modify('-1 minute')->format('Y-m-d H:i')]);
        $suivant = $this->cree(['paiement_mode' => 'remise']);

        [$code, , $entetes] = $this->envoie($r, $choix, $formulaire);

        $this->assertSame(303, $code);
        $this->assertContains('Location: /reservation/' . $r['code'] . '?j=' . $r['jeton'], $entetes);
        $relue = Reservations::parCode($r['code']);
        $this->assertSame('expiree', $relue['statut']);
        $this->assertSame('mobile', $relue['paiement_mode']);
        $this->assertSame('demande', Reservations::parCode($suivant['code'])['statut']);
    }

    // Double clic sur « Relancer », ou second onglet resté sur l'écran d'échec : la
    // première demande attend sur le téléphone et doit garder sa référence, sinon le
    // paiement que le client y valide n'arriverait sur aucune réservation.
    #[DataProvider('choix')]
    public function testChoixRefusePendantUneDemandeEnCours(string $choix): void
    {
        $r = $this->refusee();
        $formulaire = $this->ouvre($r);
        $this->envoie($r, 'relancer', $formulaire, '6 72 89 40 84');
        $premiere = Reservations::parCode($r['code']);
        $this->assertSame('en_cours', $premiere['paiement_statut']);

        [$code, , $entetes] = $this->envoie($r, $choix, $formulaire, '6 99 11 22 33');

        $this->assertSame(303, $code);
        $this->assertContains('Location: /paiement/' . $r['code'] . '?j=' . $r['jeton'], $entetes);
        $relue = Reservations::parCode($r['code']);
        $this->assertSame('attente_paiement', $relue['statut']);
        $this->assertSame('mobile', $relue['paiement_mode']);
        $this->assertSame($premiere['paiement_reference'], $relue['paiement_reference']);
        $this->assertSame('+237672894084', $relue['paiement_numero']);
        $this->assertSame($premiere['expire_le'], $relue['expire_le']);
        [, $page] = $this->demande('/paiement/' . $r['code'] . '?j=' . $r['jeton'], null, $formulaire['cookie']);
        $this->assertStringContainsString('Une demande attend déjà sur votre téléphone', $page);
    }

    // La demande relancée a été refusée à son tour : le client peut réessayer.
    public function testRelanceAutoriseeQuandLaDemandeEnCoursAEchoue(): void
    {
        $r = $this->refusee();
        $formulaire = $this->ouvre($r);
        $this->envoie($r, 'relancer', $formulaire);
        $this->assertSame('en_cours', Reservations::parCode($r['code'])['paiement_statut']);

        $this->envoie($r, 'relancer', $formulaire, '6 72 89 40 84');

        $relue = Reservations::parCode($r['code']);
        $this->assertSame('+237672894084', $relue['paiement_numero']);
        $this->assertSame('en_cours', $relue['paiement_statut']);
    }

    /** Ouvre la page de paiement comme le client : cookie de session et jeton du formulaire. */
    private function ouvre(array $r): array
    {
        [$code, $corps, $entetes] = $this->demande('/paiement/' . $r['code'] . '?j=' . $r['jeton']);
        $this->assertSame(200, $code);
        $this->assertStringContainsString('name="choix" value="remise"', $corps);
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $corps, $csrf);
        $cookie = self::cookieDeSession($entetes);
        $this->assertNotEmpty($csrf[1] ?? null);
        $this->assertNotNull($cookie);
        return ['cookie' => $cookie, 'csrf' => $csrf[1]];
    }

    private function envoie(array $r, string $choix, array $formulaire, string $numero = '6 72 89 40 00'): array
    {
        return $this->demande('/paiement/' . $r['code'], [
            '_csrf' => $formulaire['csrf'],
            'j' => $r['jeton'],
            'operateur' => 'mtn',
            'numero' => $numero,
            'choix' => $choix,
        ], $formulaire['cookie']);
    }

    // Le paiement de démonstration refuse les numéros qui finissent par 000.
    private function refusee(): array
    {
        $r = $this->cree(['numero' => '+237672894000']);
        Reservations::maj($r['id'], ['paiement_reference' => "DEMO-{$r['code']}-" . (time() - 60), 'paiement_statut' => 'echec']);
        return Reservations::parCode($r['code']);
    }

    private function cree(array $extra = []): array
    {
        $debut = maintenant()->modify('+3 days');
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
}
