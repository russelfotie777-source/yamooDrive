<?php

namespace Yamoo\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Flotte;

// Les montants viennent de config/ : les tests restent justes quand Yamoo Drive
// remplacera les prix provisoires par sa vraie grille.
final class DevisTest extends TestCase
{
    public static function durees(): array
    {
        return [
            'une demi-heure compte une journée' => ['2027-03-01 08:00', '2027-03-01 08:30', 1],
            'vingt-quatre heures pile' => ['2027-03-01 08:00', '2027-03-02 08:00', 1],
            'retour dans l’heure de tolérance' => ['2027-03-01 08:00', '2027-03-02 09:00', 1],
            'une minute après la tolérance' => ['2027-03-01 08:00', '2027-03-02 09:01', 2],
            'trois jours' => ['2027-03-01 08:00', '2027-03-04 08:00', 3],
            'trois jours et une heure' => ['2027-03-01 08:00', '2027-03-04 09:00', 3],
            'trois jours et une heure et une minute' => ['2027-03-01 08:00', '2027-03-04 09:01', 4],
            'passage d’un mois à l’autre' => ['2027-02-27 10:00', '2027-03-02 10:00', 3],
        ];
    }

    #[DataProvider('durees')]
    public function testCompteLesJoursAvecUneHeureDeTolerance(string $debut, string $fin, int $jours): void
    {
        $this->assertSame($jours, Devis::jours(date_locale($debut), date_locale($fin)));
    }

    public function testPasDeRapatriementDansLaMemeVille(): void
    {
        $this->assertSame(0, Devis::rapatriement('douala-agence', 'douala-aeroport'));
        $this->assertSame(0, Devis::rapatriement('yaounde-aeroport', 'yaounde-agence'));
    }

    public function testRapatriementEntreDeuxVillesDansLesDeuxSens(): void
    {
        $prix = config('montants.rapatriement.douala|yaounde');

        $this->assertGreaterThan(0, $prix);
        $this->assertSame($prix, Devis::rapatriement('douala-agence', 'yaounde-aeroport'));
        $this->assertSame($prix, Devis::rapatriement('yaounde-agence', 'douala-aeroport'));
    }

    public function testChaqueTrajetEntreVillesAUnPrix(): void
    {
        $villes = array_keys(config('villes'));
        foreach ($villes as $a) {
            foreach ($villes as $b) {
                if ($a !== $b) {
                    $lieuA = $this->premierLieu($a);
                    $lieuB = $this->premierLieu($b);
                    $this->assertGreaterThan(0, Devis::rapatriement($lieuA, $lieuB), "$a → $b");
                }
            }
        }
    }

    public function testLieuInconnuSansRapatriement(): void
    {
        $this->assertSame(0, Devis::rapatriement('douala-agence', 'garoua-agence'));
    }

    public function testDevisSansChauffeurAvecCaution(): void
    {
        $v = Flotte::trouve('jetour-t2');
        $debut = maintenant()->modify('+10 days')->setTime(9, 0);
        $d = $this->demande($v, 'sans', $debut, $debut->modify('+3 days'));

        $devis = Devis::calcule($d);

        $this->assertSame(3, $devis['jours']);
        $this->assertSame($v['tarifs']['sans'], $devis['prix_jour']);
        $this->assertSame(3 * $v['tarifs']['sans'], $devis['montant_location']);
        $this->assertSame(0, $devis['frais_rapatriement']);
        $this->assertSame($devis['montant_location'], $devis['total']);
        $this->assertSame((int) config('montants.caution_sans_chauffeur'), $devis['caution']);
    }

    public function testDevisAvecChauffeurSansCaution(): void
    {
        $v = Flotte::trouve('jetour-t2');
        $debut = maintenant()->modify('+10 days')->setTime(9, 0);
        $d = $this->demande($v, 'avec', $debut, $debut->modify('+2 days'));

        $devis = Devis::calcule($d);

        $this->assertSame(2 * $v['tarifs']['avec'], $devis['montant_location']);
        $this->assertSame(0, $devis['caution']);
    }

    public function testAllerSimpleAjouteLeRapatriementAuTotal(): void
    {
        $v = Flotte::trouve('toyota-hiace');
        $debut = maintenant()->modify('+10 days')->setTime(9, 0);
        $d = $this->demande($v, 'avec', $debut, $debut->modify('+1 day'));
        $d->lieuDepart = 'douala-agence';
        $d->lieuRetour = 'yaounde-agence';

        $devis = Devis::calcule($d);

        $frais = config('montants.rapatriement.douala|yaounde');
        $this->assertSame($frais, $devis['frais_rapatriement']);
        $this->assertSame($v['tarifs']['avec'] + $frais, $devis['total']);
    }

    public function testHeureDeRetourToleree(): void
    {
        $v = Flotte::trouve('jetour-x50');
        $debut = maintenant()->modify('+10 days')->setTime(9, 0);
        $d = $this->demande($v, 'sans', $debut, $debut->modify('+2 days'));

        $devis = Devis::calcule($d);

        $this->assertSame($debut->modify('+2 days')->setTime(10, 0)->format('Y-m-d H:i'), $devis['retour_tolere']);
    }

    public function testAnnulationGratuiteJusquaVingtQuatreHeuresAvant(): void
    {
        $v = Flotte::trouve('jetour-x50');
        $debut = maintenant()->modify('+10 days')->setTime(9, 0);
        $d = $this->demande($v, 'sans', $debut, $debut->modify('+1 day'));

        $this->assertSame($debut->modify('-1 day')->format('Y-m-d H:i'), Devis::calcule($d)['annulation_jusqua']);
    }

    public function testPlusDAnnulationGratuiteAMoinsDeVingtQuatreHeures(): void
    {
        $v = Flotte::trouve('jetour-x50');
        $debut = maintenant()->modify('+5 hours');
        $d = $this->demande($v, 'sans', $debut, $debut->modify('+1 day'));

        $this->assertNull(Devis::calcule($d)['annulation_jusqua']);
    }

    private function demande(array $v, string $formule, DateTimeImmutable $debut, DateTimeImmutable $fin): Demande
    {
        $d = new Demande();
        $d->vehicule = $v;
        $d->formule = $formule;
        $d->debut = $debut;
        $d->fin = $fin;
        return $d;
    }

    private function premierLieu(string $ville): string
    {
        foreach (config('lieux') as $cle => $lieu) {
            if ($lieu['ville'] === $ville) {
                return $cle;
            }
        }
        $this->fail("Aucun lieu à $ville");
    }
}
