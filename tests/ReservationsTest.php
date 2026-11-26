<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Langue;
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

    public function testPaiementRecuApresLeDelaiReprendLaReservation(): void
    {
        $r = $this->expiree($this->enCours($this->cree(), time() - 60));

        $r = Paiement::verifie($r);

        $this->assertSame('confirmee', $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertNull($r['expire_le']);
        $this->assertStringContainsString('réservation confirmée', Reservations::journal($r['id'])[0]['detail']);
    }

    public function testPaiementRecuApresLeDelaiQuandLeVehiculeEstRepris(): void
    {
        $r = $this->expiree($this->enCours($this->cree(), time() - 60));
        $suivant = $this->cree(['paiement_mode' => 'remise']);

        $r = Paiement::verifie($r);

        $this->assertSame('expiree', $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertStringContainsString('remboursement à faire', Reservations::journal($r['id'])[0]['detail']);
        $this->assertSame('demande', Reservations::parCode($suivant['code'])['statut']);
    }

    // Notch Pay prévient le site (webhook) alors que le délai est passé mais qu'aucune page
    // ne l'a encore constaté : un autre client a pu réserver le véhicule libéré.
    public function testPaiementSignaleAvantLeConstatDuDelaiQuandLeVehiculeEstRepris(): void
    {
        $r = $this->depasse($this->enCours($this->cree(), time() - 60));
        $suivant = $this->cree(['paiement_mode' => 'remise']);

        $r = Paiement::verifie($r);

        $this->assertSame('expiree', $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertStringContainsString('remboursement à faire', Reservations::journal($r['id'])[0]['detail']);
        $this->assertSame('demande', Reservations::parCode($suivant['code'])['statut']);
    }

    public function testPaiementSignaleAvantLeConstatDuDelaiQuandLeVehiculeEstLibre(): void
    {
        $r = $this->depasse($this->enCours($this->cree(), time() - 60));

        $r = Paiement::verifie($r);

        $this->assertSame('confirmee', $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertStringContainsString('après le délai', Reservations::journal($r['id'])[0]['detail']);
    }

    public function testDelaiDepasseSansPaiementResteExpire(): void
    {
        $r = $this->expiree($this->enCours($this->cree(), time()));

        $r = Paiement::verifie($r);

        $this->assertSame('expiree', $r['statut']);
        $this->assertSame('echec', $r['paiement_statut']);
    }

    public function testUnPaiementTardifNEstEnregistreQuUneFois(): void
    {
        $r = $this->expiree($this->enCours($this->cree(), time() - 60));
        $this->cree(['paiement_mode' => 'remise']);

        Paiement::verifie($r);
        Paiement::verifie(Reservations::parCode($r['code']));

        $notes = array_filter(Reservations::journal($r['id']), fn ($j) => str_contains($j['detail'], 'après le délai'));
        $this->assertCount(1, $notes);
    }

    public static function decisionsQuiGardentLeVehicule(): array
    {
        return [
            'confirmée par l’équipe' => ['confirmee'],
            'rendue avant le paiement' => ['terminee'],
        ];
    }

    // L'équipe confirme pendant que la demande attend sur le téléphone du client, qui la
    // valide ensuite : sans le paiement noté, on le lui redemanderait à la remise des clés.
    #[DataProvider('decisionsQuiGardentLeVehicule')]
    public function testPaiementRecuApresUneDecisionDeLEquipe(string $statut): void
    {
        $r = $this->parLEquipe($this->enCours($this->cree(), time() - 60), $statut);

        $r = Paiement::verifie($r);

        $this->assertSame($statut, $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertStringStartsWith('Paiement reçu', Reservations::journal($r['id'])[0]['detail']);
    }

    public function testPaiementRecuApresLAnnulationDeLEquipe(): void
    {
        $r = $this->parLEquipe($this->enCours($this->cree(), time() - 60), 'annulee');

        $r = Paiement::verifie($r);

        $this->assertSame('annulee', $r['statut']);
        $this->assertSame('paye', $r['paiement_statut']);
        $this->assertStringContainsString('Remboursement à faire', Reservations::journal($r['id'])[0]['detail']);
    }

    public static function languesDesPages(): array
    {
        return ['anglais' => ['en'], 'espagnol' => ['es'], 'allemand' => ['de'], 'chinois' => ['zh'], 'arabe' => ['ar']];
    }

    // Le client attend son paiement sur une page en allemand ou en arabe : la note du
    // journal reste écrite comme le lit l'équipe, sans « 110.000 » ni marques de sens.
    #[DataProvider('languesDesPages')]
    public function testLeJournalGardeLeMontantEnFrancais(string $langue): void
    {
        $r = $this->enCours($this->cree(), time() - 60);

        Langue::dans($langue, fn () => Paiement::verifie($r));

        $this->assertSame('Paiement reçu : ' . fcfa($r['total']) . '.', Reservations::journal($r['id'])[0]['detail']);
    }

    public function testPaiementRefuseApresLaConfirmationDeLEquipe(): void
    {
        $r = $this->parLEquipe($this->enCours($this->cree(['numero' => '+237672894000']), time() - 60), 'confirmee');

        $r = Paiement::verifie($r);

        $this->assertSame('confirmee', $r['statut']);
        $this->assertSame('echec', $r['paiement_statut']);
    }

    public function testPaiementEnCoursApresLaConfirmationDeLEquipeNeChangeRien(): void
    {
        $r = $this->parLEquipe($this->enCours($this->cree(), time()), 'confirmee');
        $notes = count(Reservations::journal($r['id']));

        $r = Paiement::verifie($r);

        $this->assertSame('en_cours', $r['paiement_statut']);
        $this->assertCount($notes, Reservations::journal($r['id']));
    }

    public function testUnPaiementApresLEquipeNEstEnregistreQuUneFois(): void
    {
        $r = $this->parLEquipe($this->enCours($this->cree(), time() - 60), 'confirmee');

        Paiement::verifie($r);
        Paiement::verifie(Reservations::parCode($r['code']));

        $notes = array_filter(Reservations::journal($r['id']), fn ($j) => str_starts_with($j['detail'], 'Paiement reçu'));
        $this->assertCount(1, $notes);
    }

    // Après un refus, le client a choisi de payer à la remise des clés : l'ancienne demande
    // n'est plus interrogée, même si le fournisseur finit par la donner pour payée.
    public function testPaiementALaRemiseApresUnRefusNEstPlusInterroge(): void
    {
        $r = $this->enCours($this->cree(), time() - 60);
        Reservations::maj($r['id'], ['paiement_mode' => 'remise', 'paiement_statut' => 'aucun', 'statut' => 'demande', 'expire_le' => null]);

        $r = Paiement::verifie(Reservations::parCode($r['code']));

        $this->assertSame('demande', $r['statut']);
        $this->assertSame('aucun', $r['paiement_statut']);
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

    public static function recherchesQuiTrouvent(): array
    {
        return [
            'numéro tel qu’affiché dans la liste' => ['+237 6 72 89 40 84'],
            'numéro tel qu’écrit au Cameroun' => ['672 89 40 84'],
            'numéro avec 00237' => ['00237 672 89 40 84'],
            'numéro avec des points' => ['6.72.89.40.84'],
            'fin du numéro' => ['89 40 84'],
            'numéro collé' => ['+237672894084'],
            'nom' => ['client de test'],
        ];
    }

    #[DataProvider('recherchesQuiTrouvent')]
    public function testRechercheDeLEquipe(string $recherche): void
    {
        $r = $this->cree();

        $this->assertSame([$r['code']], array_column(Reservations::liste(null, $recherche), 'code'));
    }

    public function testRechercheParCode(): void
    {
        $r = $this->cree();

        $this->assertSame([$r['code']], array_column(Reservations::liste(null, strtolower($r['code'])), 'code'));
    }

    public function testRechercheDUnAutreNumero(): void
    {
        $this->cree();

        $this->assertSame([], Reservations::liste(null, '6 99 00 00 00'));
        $this->assertSame([], Reservations::liste(null, '+33 6 72 89 40 84'));
    }

    public static function languesDuClient(): array
    {
        return [
            'français, à la racine' => ['fr', 'Bonjour Client de test, Yamoo Drive au sujet de votre réservation', 'http://localhost/reservation/'],
            'arabe' => ['ar', 'مرحبًا Client de test، معك Yamoo Drive بخصوص حجزك', 'http://localhost/ar/reservation/'],
            'langue retirée du site' => ['xx', 'Bonjour Client de test, Yamoo Drive au sujet de votre réservation', 'http://localhost/reservation/'],
        ];
    }

    #[DataProvider('languesDuClient')]
    public function testMessageAuClientDansLaLangueDeSaReservation(string $langue, string $salut, string $lien): void
    {
        $r = $this->cree(['paiement_mode' => 'remise']);
        Reservations::maj($r['id'], ['langue' => $langue]);

        [$premiere, $seconde] = explode("\n", Reservations::messageAuClient(Reservations::parCode($r['code'])));

        $this->assertStringStartsWith($salut, $premiere);
        $this->assertStringContainsString($r['code'], $premiere);
        $this->assertStringEndsWith($lien . $r['code'] . '?j=' . $r['jeton'], $seconde);
        $this->assertSame('fr', \Yamoo\Langue::$code);
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

    public static function actionsParStatut(): array
    {
        return [
            'à confirmer' => ['demande', 'aucun', ['confirmer', 'payee', 'annuler', 'note']],
            'en attente du paiement' => ['attente_paiement', 'en_cours', ['confirmer', 'payee', 'annuler', 'note']],
            'confirmée et payée' => ['confirmee', 'paye', ['annuler', 'terminer', 'note']],
            'confirmée, à payer' => ['confirmee', 'aucun', ['payee', 'annuler', 'terminer', 'note']],
            'terminée, à payer' => ['terminee', 'aucun', ['payee', 'note']],
            'expirée' => ['expiree', 'echec', ['note']],
            'payée trop tard' => ['expiree', 'paye', ['note']],
            'annulée' => ['annulee', 'aucun', ['note']],
        ];
    }

    #[DataProvider('actionsParStatut')]
    public function testActionsDeLEquipeSelonLEtat(string $statut, string $paiement, array $attendues): void
    {
        $this->assertSame($attendues, Reservations::actionsEquipe(['statut' => $statut, 'paiement_statut' => $paiement]));
    }

    // Délai de paiement dépassé et constaté, comme au passage suivant sur le site.
    private function expiree(array $r): array
    {
        Reservations::maj($r['id'], ['expire_le' => maintenant()->modify('-1 minute')->format('Y-m-d H:i')]);
        Reservations::expire();
        return Reservations::parCode($r['code']);
    }

    // Délai de paiement dépassé, mais aucune page n'est passée depuis pour le constater.
    private function depasse(array $r): array
    {
        Reservations::maj($r['id'], ['expire_le' => maintenant()->modify('-1 minute')->format('Y-m-d H:i')]);
        return Reservations::parCode($r['code']);
    }

    // Statut posé dans l'espace équipe, comme le fait Admin::action, sans toucher au paiement.
    private function parLEquipe(array $r, string $statut): array
    {
        Reservations::maj($r['id'], ['statut' => $statut, 'expire_le' => null]);
        return Reservations::parCode($r['code']);
    }

    // Paiement lancé auprès de la démonstration à l'instant donné.
    private function enCours(array $r, int $lance): array
    {
        Reservations::maj($r['id'], ['paiement_reference' => "DEMO-{$r['code']}-$lance", 'paiement_statut' => 'en_cours']);
        return Reservations::parCode($r['code']);
    }
}
