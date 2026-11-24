<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Les robots et les curieux essaient des paramètres en liste (?j[]=1) : la page répond
// comme si le paramètre manquait, jamais par une erreur 500. Passe par le vrai serveur.
final class AdressesBricoleesTest extends TestCase
{
    use AvecServeur;

    public static function adresses(): array
    {
        return [
            'catégorie du catalogue' => ['/vehicules?categorie[]=suv', 200],
            'véhicule du devis' => ['/api/devis?vehicule[]=jetour-t2', 404],
            'jeton de la réservation' => ['/reservation/YD-ABC234?j[]=1', 404],
            'jeton du paiement' => ['/paiement/YD-ABC234?j[]=1', 404],
            'jeton du suivi du paiement' => ['/api/paiement/YD-ABC234?j[]=1', 404],
        ];
    }

    #[DataProvider('adresses')]
    public function testParametreEnListeIgnore(string $chemin, int $attendu): void
    {
        $this->assertSame($attendu, $this->demande($chemin)[0]);
    }

    public function testFormulaireAvecUnChampEnListe(): void
    {
        [, $corps, $entetes] = $this->demande('/vehicules');
        preg_match('/data-csrf="([a-f0-9]+)"/', $corps, $csrf);
        $cookie = null;
        foreach ($entetes as $ligne) {
            if (preg_match('/^Set-Cookie: (yamoo=[^;]+)/i', $ligne, $m)) {
                $cookie = $m[1];
            }
        }
        $this->assertNotEmpty($csrf[1] ?? null);
        $this->assertNotNull($cookie);

        [$code] = $this->demande('/reservation/YD-ABC234/annuler', ['_csrf' => $csrf[1] ?? '', 'j' => ['1']], $cookie);

        $this->assertSame(404, $code);
    }

    public function testJetonDuFormulaireEnListeRefuse(): void
    {
        [$code] = $this->demande('/reservation/YD-ABC234/annuler', ['_csrf' => ['1'], 'j' => '1']);

        $this->assertSame(419, $code);
    }
}
