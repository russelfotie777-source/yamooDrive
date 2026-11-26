<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Assistant\Simple;
use Yamoo\Base;
use Yamoo\Flotte;

// Réponses intégrées de l'assistant, utilisées sans clé d'API ou quand l'API ne répond pas.
#[RequiresPhpExtension('pdo_sqlite')]
final class AssistantSimpleTest extends TestCase
{
    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
    }

    public static function questions(): array
    {
        return [
            'annulation en français' => ['Comment annuler ma réservation ?', 'L’annulation est gratuite'],
            'annulation en arabe' => ['كيف يمكنني إلغاء الحجز؟', 'L’annulation est gratuite'],
            'caution en chinois' => ['押金是多少？', 'Une caution remboursable'],
            'permis en allemand' => ['Welchen Führerschein brauche ich?', 'avoir au moins'],
            'aéroport en anglais' => ['Can you pick me up at the airport?', 'Nous livrons le véhicule aux aéroports'],
            'mariage en espagnol' => ['Necesito coches para una boda', 'Pour un mariage'],
            'humain' => ['Je veux parler à un humain', 'l’assistant automatique'],
            'paiement' => ['Je peux payer avec Orange Money ?', 'Orange Money'],
            'agence' => ['Où êtes-vous situés ?', 'Logpom'],
            'modèle' => ['Quel est le prix du Prado ?', 'Toyota Land Cruiser Prado'],
            'salutation' => ['Bonjour', 'Bonjour. Je peux vous montrer'],
            'incompris' => ['xyz', 'Je n’ai pas compris'],
        ];
    }

    #[DataProvider('questions')]
    public function testReponseSelonLaQuestion(string $question, string $attendu): void
    {
        $this->assertStringContainsString($attendu, $this->reponse($question));
    }

    public function testDemainDonneLesVehiculesLibres(): void
    {
        $this->assertStringStartsWith('Pour demain (' . date_texte(maintenant()->modify('tomorrow'), 'EEEEdMMMM') . ')', $this->reponse('Quels véhicules sont libres demain ?'));
    }

    public function testCeWeekEnd(): void
    {
        $this->assertStringStartsWith('Pour ce week-end', $this->reponse('Une voiture pour ce week-end'));
    }

    public function testJourDeLaSemaine(): void
    {
        $samedi = date_texte(maintenant()->modify('next saturday'), 'EEEEdMMMM');

        $this->assertStringStartsWith("Pour $samedi", $this->reponse('Un SUV libre samedi ?'));
    }

    public function testGutenMorgenEstUneSalutation(): void
    {
        $this->assertStringStartsWith('Bonjour. Je peux vous montrer', $this->reponse('Guten Morgen!'));
    }

    public function testGutenMorgenNePorteSurAucuneDate(): void
    {
        $this->assertStringNotContainsString('demain', $this->reponse('Guten Morgen, welche Autos haben Sie?'));
        $this->assertStringStartsWith('Pour demain', $this->reponse('Guten Morgen, welche Autos sind morgen frei?'));
    }

    // Le premier prix annoncé est celui des véhicules trouvés, dans la formule demandée :
    // un 7 places avec chauffeur ne se loue pas au prix du plus petit SUV sans chauffeur.
    public static function formulesDemandees(): array
    {
        return ['avec chauffeur' => ['avec'], 'sans chauffeur' => ['sans']];
    }

    #[DataProvider('formulesDemandees')]
    public function testPremierPrixDesSeptPlacesDansLaFormuleDemandee(string $formule): void
    {
        $prix = Flotte::plancher($formule, Flotte::filtre(null, $formule, 7));

        $this->assertSame(
            "Voici les véhicules qui correspondent, $formule chauffeur à partir de **" . fcfa($prix) . '** par jour. Choisissez vos dates sur la fiche pour voir le prix total.',
            $this->reponse("Un 7 places $formule chauffeur")
        );
    }

    public function testPremierPrixDesSeptPlacesDansLesDeuxFormules(): void
    {
        $sept = Flotte::filtre(null, null, 7);

        $this->assertSame(
            'Voici les véhicules qui correspondent. Sans chauffeur à partir de **' . fcfa(Flotte::plancher('sans', $sept)) . '** par jour, avec chauffeur à partir de **' . fcfa(Flotte::plancher('avec', $sept)) . '**. Choisissez vos dates sur la fiche pour voir le prix total.',
            $this->reponse('Un 7 places')
        );
    }

    // Les minibus ne se louent qu'avec chauffeur : l'assistant ne cite pas de prix sans.
    public function testPremierPrixAvecChauffeurSeulQuandAucunVehiculeTrouveNeSeLoueSans(): void
    {
        $douze = Flotte::filtre(null, null, 12);
        if (Flotte::plancher('sans', $douze) !== null) {
            $this->markTestSkipped('La grille propose un 12 places sans chauffeur.');
        }

        $this->assertSame(
            'Voici les véhicules qui correspondent, avec chauffeur à partir de **' . fcfa(Flotte::plancher('avec', $douze)) . '** par jour. Choisissez vos dates sur la fiche pour voir le prix total.',
            $this->reponse('Un 12 places')
        );
    }

    private function reponse(string $question): string
    {
        return (new Simple())->reponds([['role' => 'user', 'texte' => $question]])['texte'];
    }
}
