<?php

namespace Yamoo\Tests;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Yamoo\Conducteur;

final class ConducteurTest extends TestCase
{
    /** @var list<string> */
    private array $fichiers = [];

    protected function tearDown(): void
    {
        array_map('unlink', array_filter($this->fichiers, 'is_file'));
    }

    public function testLitUneDateDeNaissance(): void
    {
        $date = Conducteur::date('1998-07-14');

        $this->assertSame('1998-07-14 00:00', $date->format('Y-m-d H:i'));
    }

    public function testRefuseUneSaisieMalFormee(): void
    {
        $this->assertNull(Conducteur::date(null));
        $this->assertNull(Conducteur::date(''));
        $this->assertNull(Conducteur::date('14/07/1998'));
        $this->assertNull(Conducteur::date('1998-7-14'));
    }

    public function testConducteurQuiRemplitLesConditions(): void
    {
        $depart = $this->depart();
        $erreurs = Conducteur::verifie($depart->modify('-30 years'), $depart->modify('-5 years'), $depart);

        $this->assertSame([], $erreurs);
    }

    public function testAgeMinimumAtteintLeJourDuDepart(): void
    {
        $age = config('regles.age_minimum');
        $depart = $this->depart();
        $naissance = $depart->setTime(0, 0)->modify("-$age years");

        $this->assertArrayNotHasKey('naissance', Conducteur::verifie($naissance, $depart->modify('-5 years'), $depart));
        $this->assertArrayHasKey('naissance', Conducteur::verifie($naissance->modify('+1 day'), $depart->modify('-5 years'), $depart));
    }

    public function testAncienneteDuPermisAtteinteLeJourDuDepart(): void
    {
        $ans = config('regles.anciennete_permis_ans');
        $depart = $this->depart();
        $permis = $depart->setTime(0, 0)->modify("-$ans years");

        $this->assertArrayNotHasKey('permis', Conducteur::verifie($depart->modify('-30 years'), $permis, $depart));
        $this->assertArrayHasKey('permis', Conducteur::verifie($depart->modify('-30 years'), $permis->modify('+1 day'), $depart));
    }

    public function testPermisDateDuFutur(): void
    {
        $depart = $this->depart();
        $erreurs = Conducteur::verifie($depart->modify('-30 years'), maintenant()->modify('+1 day'), $depart);

        $this->assertStringContainsString('futur', $erreurs['permis']);
    }

    public function testDatesManquantes(): void
    {
        $erreurs = Conducteur::verifie(null, null, $this->depart());

        $this->assertArrayHasKey('naissance', $erreurs);
        $this->assertArrayHasKey('permis', $erreurs);
    }

    public function testPiecesAcceptees(): void
    {
        $fichiers = [
            'identite' => $this->envoi($this->png()),
            'permis' => $this->envoiMultiple([$this->png(), $this->pdf()]),
            'domicile' => $this->envoi($this->pdf()),
        ];

        $this->assertSame([], Conducteur::verifiePieces($fichiers));
    }

    public function testPieceManquante(): void
    {
        $fichiers = [
            'identite' => $this->envoi($this->png()),
            'permis' => ['name' => '', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_NO_FILE],
        ];

        $erreurs = Conducteur::verifiePieces($fichiers);

        $this->assertSame(['piece_permis', 'piece_domicile'], array_keys($erreurs));
    }

    public function testRefuseUnFichierQuiNEstNiUnePhotoNiUnPdf(): void
    {
        $fichiers = [
            'identite' => $this->envoi($this->fichier("<?php echo 'bonjour';")),
            'permis' => $this->envoi($this->png()),
            'domicile' => $this->envoi($this->pdf()),
        ];

        $this->assertSame(['piece_identite'], array_keys(Conducteur::verifiePieces($fichiers)));
    }

    public function testRefuseUnFichierTropLourd(): void
    {
        $lourd = $this->envoi($this->png());
        $lourd['size'] = 10 * 1024 * 1024 + 1;
        $fichiers = ['identite' => $lourd, 'permis' => $this->envoi($this->png()), 'domicile' => $this->envoi($this->pdf())];

        $erreurs = Conducteur::verifiePieces($fichiers);

        $this->assertStringContainsString('10', $erreurs['piece_identite']);
    }

    public function testEnvoiInterrompu(): void
    {
        $fichiers = [
            'identite' => ['name' => 'cni.jpg', 'tmp_name' => '', 'size' => 0, 'error' => UPLOAD_ERR_PARTIAL],
            'permis' => $this->envoi($this->png()),
            'domicile' => $this->envoi($this->pdf()),
        ];

        $this->assertArrayHasKey('piece_identite', Conducteur::verifiePieces($fichiers));
    }

    public function testCheminDesPiecesFermeAuxNomsInventes(): void
    {
        $this->assertNull(Conducteur::chemin('YD-ABC234', '../../.env'));
        $this->assertNull(Conducteur::chemin('../YD-ABC234', 'permis-1-0123456789ab.pdf'));
        $this->assertNull(Conducteur::chemin('YD-ABC234', 'permis-1-0123456789ab.pdf'));
    }

    private function depart(): DateTimeImmutable
    {
        return maintenant()->modify('+10 days')->setTime(9, 0);
    }

    private function png(): string
    {
        // Image PNG de 1 pixel.
        return $this->fichier(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    private function pdf(): string
    {
        return $this->fichier("%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    private function fichier(string $contenu): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'yamoo-piece-');
        file_put_contents($chemin, $contenu);
        $this->fichiers[] = $chemin;
        return $chemin;
    }

    private function envoi(string $chemin): array
    {
        return ['name' => basename($chemin), 'tmp_name' => $chemin, 'size' => filesize($chemin), 'error' => UPLOAD_ERR_OK];
    }

    /** Forme de $_FILES pour un champ multiple (name="permis[]"). */
    private function envoiMultiple(array $chemins): array
    {
        $champ = ['name' => [], 'tmp_name' => [], 'size' => [], 'error' => []];
        foreach ($chemins as $chemin) {
            foreach ($this->envoi($chemin) as $cle => $valeur) {
                $champ[$cle][] = $valeur;
            }
        }
        return $champ;
    }
}
