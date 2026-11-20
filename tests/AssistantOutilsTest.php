<?php

namespace Yamoo\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Assistant\Outils;
use Yamoo\Base;
use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Flotte;

// Outils que l'assistant appelle avant de citer un prix ou une disponibilité.
// Les montants attendus sont recalculés depuis config/, jamais écrits en dur.
#[RequiresPhpExtension('pdo_sqlite')]
final class AssistantOutilsTest extends TestCase
{
    private DateTimeImmutable $lundi;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
        $this->lundi = maintenant()->modify('next monday')->modify('+7 days')->setTime(9, 0);
    }

    public function testChaqueOutilDecritEstExecute(): void
    {
        foreach (Outils::definitions() as $outil) {
            $resultat = (new Outils())->execute($outil['name'], []);

            $this->assertStringNotContainsString('Outil inconnu', $resultat['erreur'] ?? '', $outil['name']);
        }
    }

    public function testOutilInconnu(): void
    {
        $this->assertSame(['erreur' => 'Outil inconnu : supprimer'], (new Outils())->execute('supprimer', []));
    }

    public function testDevisReprendLaGrilleEtLesFraisDeRapatriement(): void
    {
        $entree = $this->entree('jetour-t2', '+0 hours', '+2 days') + ['formule' => 'sans', 'lieu_retour' => 'yaounde-agence'];
        $attendu = Devis::calcule(Demande::depuis($entree));

        $r = (new Outils())->execute('calculer_devis', $entree);

        $this->assertSame(2, $r['jours']);
        $this->assertSame(fcfa(Flotte::trouve('jetour-t2')['tarifs']['sans']), $r['prix_par_jour']);
        $this->assertSame(fcfa(Devis::rapatriement('douala-agence', 'yaounde-agence')), $r['frais_rapatriement']);
        $this->assertSame(fcfa($attendu['total']), $r['total']);
        $this->assertSame(fcfa($attendu['caution']), $r['caution_remboursable_a_la_remise']);
        $this->assertTrue($r['disponible']);
    }

    public function testDevisAvecChauffeurSansCaution(): void
    {
        $r = (new Outils())->execute('calculer_devis', $this->entree('toyota-prado', '+0 hours', '+1 day'));

        $this->assertSame(fcfa(Flotte::trouve('toyota-prado')['tarifs']['avec']), $r['total']);
        $this->assertSame('aucune', $r['caution_remboursable_a_la_remise']);
    }

    public function testDevisRefuseUnDepartDejaPasse(): void
    {
        $hier = maintenant()->modify('-1 day');
        $outils = new Outils();

        $r = $outils->execute('preparer_reservation', ['vehicule' => 'jetour-t2', 'debut' => $hier->format('Y-m-d\TH:i'), 'fin' => $hier->modify('+2 days')->format('Y-m-d\TH:i')]);

        $this->assertStringContainsString('au moins', $r['erreur']);
        $this->assertNull($outils->action);
    }

    public function testPreparerLaReservationAfficheLeBouton(): void
    {
        $outils = new Outils();

        $r = $outils->execute('preparer_reservation', $this->entree('jetour-t2', '+0 hours', '+2 days'));

        $this->assertTrue($r['bouton_affiche']);
        $this->assertSame('Continuer la réservation', $outils->action['libelle']);
        $this->assertStringStartsWith(url('/reserver/jetour-t2') . '?', $outils->action['lien']);
        $this->assertStringContainsString('debut=' . rawurlencode($this->lundi->format('Y-m-d\TH:i')), $outils->action['lien']);
    }

    public function testPasDeBoutonQuandLeVehiculeEstPris(): void
    {
        $this->bloque('jetour-t2', '-1 day', '+1 day');
        $outils = new Outils();

        $r = $outils->execute('preparer_reservation', $this->entree('jetour-t2', '+0 hours', '+2 days'));

        $this->assertFalse($r['disponible']);
        $this->assertSame($this->a('+1 day'), $r['libre_a_partir_de']);
        $this->assertArrayNotHasKey('bouton_affiche', $r);
        $this->assertNull($outils->action);
        $this->assertFalse($outils->cartes[0]['disponible']);
    }

    public function testRechercheMetLesVehiculesLibresDAbordPuisLesMoinsChers(): void
    {
        $sansChauffeur = Flotte::filtre(null, 'sans');
        $prix = array_map(fn ($v) => $v['tarifs']['sans'], $sansChauffeur);
        $moinsCher = array_keys($prix, min($prix))[0];
        $this->bloque($moinsCher, '-1 day', '+1 day');
        $outils = new Outils();

        $r = $outils->execute('chercher_vehicules', ['formule' => 'sans'] + $this->entree(null, '+0 hours', '+2 days'));

        $this->assertSame(count($sansChauffeur), $r['nombre']);
        $this->assertSame(count($sansChauffeur) - 1, $r['libres']);
        $pris = array_values(array_filter($r['vehicules'], fn ($l) => $l['vehicule'] === $moinsCher))[0];
        $this->assertFalse($pris['disponible']);
        $this->assertSame($this->a('+1 day'), $pris['libre_a_partir_de']);

        $this->assertLessThanOrEqual(6, count($outils->cartes));
        $this->assertNotSame($moinsCher, $outils->cartes[0]['slug']);
        $ordre = array_map(fn ($c) => [!$c['disponible'], $c['prix_brut']], $outils->cartes);
        $trie = $ordre;
        sort($trie);
        $this->assertSame($trie, $ordre);
    }

    public function testRechercheFiltreLesPlacesEtLaFormule(): void
    {
        $r = (new Outils())->execute('chercher_vehicules', ['formule' => 'avec', 'places_min' => 7]);

        $this->assertSame(count(Flotte::filtre(null, 'avec', 7)), $r['nombre']);
        foreach ($r['vehicules'] as $l) {
            $v = Flotte::trouve($l['vehicule']);
            $this->assertGreaterThanOrEqual(7, $v['places']);
            $this->assertArrayHasKey('prix_par_jour_avec_chauffeur', $l);
        }
    }

    public function testFicheDonneLesPeriodesDejaReservees(): void
    {
        $this->bloque('toyota-prado', '+0 hours', '+3 days');

        $r = (new Outils())->execute('fiche_vehicule', ['vehicule' => 'toyota-prado']);

        $this->assertSame([['du' => $this->a('+0 hours'), 'au' => $this->a('+3 days')]], $r['deja_reserve']);
        $this->assertSame(['avec' => fcfa(Flotte::trouve('toyota-prado')['tarifs']['avec'])], $r['formules']);
    }

    public function testFicheVehiculeInconnu(): void
    {
        $this->assertSame(['erreur' => 'Véhicule inconnu.'], (new Outils())->execute('fiche_vehicule', ['vehicule' => 'tesla']));
    }

    private function entree(?string $vehicule, string $debut, string $fin): array
    {
        return array_filter([
            'vehicule' => $vehicule,
            'lieu_depart' => 'douala-agence',
            'lieu' => 'douala-agence',
            'debut' => $this->lundi->modify($debut)->format('Y-m-d\TH:i'),
            'fin' => $this->lundi->modify($fin)->format('Y-m-d\TH:i'),
        ]);
    }

    private function a(string $decalage): string
    {
        return $this->lundi->modify($decalage)->format('Y-m-d H:i');
    }

    private function bloque(string $vehicule, string $debut, string $fin): void
    {
        Base::pdo()->prepare('INSERT INTO indisponibilites (vehicule, debut, fin, motif, cree_le) VALUES (?, ?, ?, ?, ?)')
            ->execute([$vehicule, $this->a($debut), $this->a($fin), 'Entretien', maintenant()->format('Y-m-d H:i:s')]);
    }
}
