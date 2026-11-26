<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Assistant\Consignes;
use Yamoo\Flotte;

// Les prix « à partir de » de l'accueil, des moteurs de recherche et de l'assistant sont
// lus dans la grille de config/flotte.php : ils restent justes quand les vrais tarifs
// remplacent les provisoires.
#[RequiresPhpExtension('pdo_sqlite')]
final class FlotteTest extends TestCase
{
    use AvecServeur;

    public static function formules(): array
    {
        return ['sans chauffeur' => ['sans'], 'avec chauffeur' => ['avec']];
    }

    #[DataProvider('formules')]
    public function testPlancherDUneFormule(string $formule): void
    {
        $grille = array_filter(array_column(require dirname(__DIR__) . '/config/flotte.php', 'tarifs'), fn ($t) => isset($t[$formule]));

        $this->assertSame(min(array_column($grille, $formule)), Flotte::plancher($formule));
    }

    public function testPlancherDeTouteLaFlotte(): void
    {
        $this->assertSame(min(Flotte::plancher('sans'), Flotte::plancher('avec')), Flotte::plancher());
    }

    public function testPlancherDUneListe(): void
    {
        $vans = [['slug' => 'a', 'tarifs' => ['avec' => 120000]], ['slug' => 'b', 'tarifs' => ['avec' => 80000]]];

        $this->assertSame(80000, Flotte::plancher('avec', $vans));
        $this->assertSame(80000, Flotte::plancher(null, $vans));
        $this->assertNull(Flotte::plancher('sans', $vans));
    }

    #[DataProvider('formules')]
    public function testAccueilAnnonceLePlancherDeLaGrille(string $formule): void
    {
        [$code, $corps] = $this->demande('/');

        $this->assertSame(200, $code);
        // Une fois dans les repères sous la photo, une fois dans le bloc de la formule.
        $this->assertSame(2, substr_count($corps, 'À partir de <strong>' . fcfa(Flotte::plancher($formule)) . '</strong> par jour'));
    }

    public function testDonneesStructureesAvecLePlancherDeLaGrille(): void
    {
        [, $corps] = $this->demande('/');

        $this->assertStringContainsString('"priceRange":"À partir de ' . fcfa(Flotte::plancher()) . ' par jour"', $corps);
    }

    #[DataProvider('formules')]
    public function testConsignesDeLAssistantAvecLePlancherDeLaGrille(string $formule): void
    {
        $libelle = $formule === 'sans' ? 'Sans chauffeur' : 'Avec chauffeur';

        $this->assertStringContainsString("- $libelle : à partir de " . fcfa(Flotte::plancher($formule)) . ' par jour.', Consignes::texte());
    }
}
