<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\TestCase;
use Yamoo\Demande;

final class DemandeTest extends TestCase
{
    public function testLitUneDemandeComplete(): void
    {
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'lieu' => 'douala-aeroport',
            'lieu_retour' => 'yaounde-agence',
            'debut' => '2027-03-01T09:00',
            'fin' => '2027-03-04T18:30',
        ]);

        $this->assertSame('jetour-t2', $d->vehicule['slug']);
        $this->assertSame('avec', $d->formule);
        $this->assertSame('douala-aeroport', $d->lieuDepart);
        $this->assertSame('yaounde-agence', $d->lieuRetour);
        $this->assertSame('2027-03-01 09:00', $d->debut->format('Y-m-d H:i'));
        $this->assertSame('2027-03-04 18:30', $d->fin->format('Y-m-d H:i'));
        $this->assertSame(config('fuseau'), $d->debut->getTimezone()->getName());
    }

    public function testDatesEnDeuxChamps(): void
    {
        $d = Demande::depuis(['debut_date' => '2027-03-01', 'debut_heure' => '09:00', 'fin_date' => '2027-03-02', 'fin_heure' => '10:15']);

        $this->assertSame('2027-03-01 09:00', $d->debut->format('Y-m-d H:i'));
        $this->assertSame('2027-03-02 10:15', $d->fin->format('Y-m-d H:i'));
    }

    public function testIgnoreLesDatesMalFormees(): void
    {
        $d = Demande::depuis(['debut' => '01/03/2027 09:00', 'fin' => ['2027-03-02T09:00']]);

        $this->assertNull($d->debut);
        $this->assertNull($d->fin);
        $this->assertFalse($d->aDesDates());
    }

    public function testLieuInconnuRemplaceParLAgenceDeDouala(): void
    {
        $d = Demande::depuis(['lieu' => 'garoua-agence', 'lieu_retour' => 'nulle-part']);

        $this->assertSame('douala-agence', $d->lieuDepart);
        $this->assertSame('douala-agence', $d->lieuRetour);
    }

    public function testRetourAuLieuDeDepartParDefaut(): void
    {
        $d = Demande::depuis(['lieu' => 'bafoussam-agence']);

        $this->assertSame('bafoussam-agence', $d->lieuRetour);
        $this->assertNull($d->versParams()['lieu_retour']);
    }

    public function testFormuleAvecChauffeurImposeeAuxVehiculesQuiNeSeLouentPasSansChauffeur(): void
    {
        $d = Demande::depuis(['vehicule' => 'toyota-land-cruiser-300', 'formule' => 'sans']);

        $this->assertSame('avec', $d->formule);
    }

    public function testVehiculeInconnu(): void
    {
        $d = Demande::depuis(['vehicule' => 'tesla-model-s']);

        $this->assertNull($d->vehicule);
        $this->assertFalse($d->valide());
        $this->assertArrayHasKey('vehicule', $d->erreurs);
    }

    public function testFormuleNonProposeeRefusee(): void
    {
        $d = $this->demandeValide();
        $d->vehicule = Demande::depuis(['vehicule' => 'toyota-prado'])->vehicule;
        $d->formule = 'sans';

        $this->assertFalse($d->valide());
        $this->assertArrayHasKey('formule', $d->erreurs);
    }

    public function testDemandeValide(): void
    {
        $d = $this->demandeValide();

        $this->assertTrue($d->valide(), implode(' ', $d->erreurs));
        $this->assertSame([], $d->erreurs);
    }

    public function testDatesManquantes(): void
    {
        $d = Demande::depuis(['vehicule' => 'jetour-t2']);

        $this->assertFalse($d->valide());
        $this->assertArrayHasKey('debut', $d->erreurs);
        $this->assertArrayHasKey('fin', $d->erreurs);
    }

    public function testDepartTropProche(): void
    {
        $delai = config('regles.delai_minimum_heures');
        $debut = maintenant()->modify('+' . ($delai * 60 - 5) . ' minutes');
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+1 day')->format('Y-m-d\TH:i'),
        ]);

        $this->assertFalse($d->valideDates());
        $this->assertArrayHasKey('debut', $d->erreurs);
        $this->assertArrayNotHasKey('fin', $d->erreurs);
    }

    public function testRetourAvantLeDepart(): void
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        $d = Demande::depuis([
            'vehicule' => 'jetour-t2',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->format('Y-m-d\TH:i'),
        ]);

        $this->assertFalse($d->valideDates());
        $this->assertArrayHasKey('fin', $d->erreurs);
    }

    public function testDureeMaximaleEnLigne(): void
    {
        $max = config('regles.duree_max_jours');
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);

        $d = Demande::depuis(['vehicule' => 'jetour-t2', 'debut' => $debut->format('Y-m-d\TH:i'), 'fin' => $debut->modify("+$max days")->format('Y-m-d\TH:i')]);
        $this->assertTrue($d->valideDates());

        $d = Demande::depuis(['vehicule' => 'jetour-t2', 'debut' => $debut->format('Y-m-d\TH:i'), 'fin' => $debut->modify('+' . ($max + 1) . ' days')->format('Y-m-d\TH:i')]);
        $this->assertFalse($d->valideDates());
        $this->assertArrayHasKey('fin', $d->erreurs);
    }

    public function testVersParamsRedonneLaDemande(): void
    {
        $entree = [
            'vehicule' => 'jetour-t2',
            'formule' => 'avec',
            'lieu' => 'douala-aeroport',
            'lieu_retour' => 'yaounde-agence',
            'debut' => '2027-03-01T09:00',
            'fin' => '2027-03-04T18:30',
        ];
        $params = Demande::depuis($entree)->versParams();

        $this->assertSame(['formule' => 'avec', 'lieu' => 'douala-aeroport', 'lieu_retour' => 'yaounde-agence', 'debut' => '2027-03-01T09:00', 'fin' => '2027-03-04T18:30'], $params);
    }

    private function demandeValide(): Demande
    {
        $debut = maintenant()->modify('+3 days')->setTime(9, 0);
        return Demande::depuis([
            'vehicule' => 'jetour-t2',
            'formule' => 'sans',
            'debut' => $debut->format('Y-m-d\TH:i'),
            'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i'),
        ]);
    }
}
