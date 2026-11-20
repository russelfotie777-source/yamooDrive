<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yamoo\Agenda;
use Yamoo\Flotte;
use Yamoo\Langue;

// Fichier « Ajouter à mon agenda » de la page d'une réservation.
final class AgendaTest extends TestCase
{
    protected function tearDown(): void
    {
        Langue::choisit('fr');
    }

    public function testHeuresEnUtc(): void
    {
        $ics = Agenda::ics($this->reservation());

        // Douala est à UTC+1 toute l'année.
        $this->assertStringContainsString("\r\nDTSTART:20261205T080000Z\r\n", $ics);
        $this->assertStringContainsString("\r\nDTEND:20261207T080000Z\r\n", $ics);
        $this->assertMatchesRegularExpression('/\r\nDTSTAMP:\d{8}T\d{6}Z\r\n/', $ics);
    }

    public function testChaqueLigneFinitParCrlf(): void
    {
        $ics = Agenda::ics($this->reservation());

        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
        $this->assertSame(substr_count($ics, "\n"), substr_count($ics, "\r\n"));
    }

    public static function langues(): array
    {
        return array_map(fn ($code) => [$code], array_combine(array_keys(Langue::LANGUES), array_keys(Langue::LANGUES)));
    }

    #[DataProvider('langues')]
    public function testAucuneLigneNeDepasse75Octets(string $langue): void
    {
        Langue::choisit($langue);

        foreach (explode("\r\n", Agenda::ics($this->reservation())) as $ligne) {
            $this->assertLessThanOrEqual(75, strlen($ligne), $ligne);
            $this->assertTrue(mb_check_encoding($ligne, 'UTF-8'), "Caractère coupé : $ligne");
        }
    }

    public function testLeTexteDeplieResteEntier(): void
    {
        $r = $this->reservation();

        $deplie = str_replace("\r\n ", '', Agenda::ics($r));

        $this->assertStringContainsString(
            "\r\nDESCRIPTION:Réservation YD-ABC234. Total " . fcfa($r['total']) . '. WhatsApp ' . config('contact.telephone_affiche') . "\r\n",
            $deplie
        );
    }

    public function testVirgulesEtPointsVirgulesEchappes(): void
    {
        Langue::choisit('en');
        $r = $this->reservation();

        $deplie = str_replace("\r\n ", '', Agenda::ics($r));

        // En anglais, « 300,000 FCFA » : la virgule séparerait deux valeurs sans la barre oblique.
        $this->assertStringContainsString('Total ' . str_replace(',', '\,', fcfa($r['total'])) . '.', $deplie);
    }

    public function testPliageSurDesCaracteresDePlusieursOctets(): void
    {
        $ligne = 'SUMMARY:' . str_repeat('é', 40) . str_repeat('租', 30);

        $plie = Agenda::plie($ligne);

        $this->assertSame($ligne, str_replace("\r\n ", '', $plie));
        foreach (explode("\r\n", $plie) as $i => $morceau) {
            $this->assertLessThanOrEqual(75, strlen($morceau));
            $this->assertTrue(mb_check_encoding($morceau, 'UTF-8'));
            $this->assertSame($i > 0, str_starts_with($morceau, ' '));
        }
    }

    public function testLigneCourteInchangee(): void
    {
        $this->assertSame('BEGIN:VEVENT', Agenda::plie('BEGIN:VEVENT'));
        $this->assertSame(str_repeat('a', 75), Agenda::plie(str_repeat('a', 75)));
        $this->assertSame(str_repeat('a', 75) . "\r\n a", Agenda::plie(str_repeat('a', 76)));
    }

    private function reservation(): array
    {
        $v = Flotte::trouve('toyota-land-cruiser-300');
        return [
            'code' => 'YD-ABC234',
            'debut' => '2026-12-05 09:00',
            'fin' => '2026-12-07 09:00',
            'v' => $v,
            'lieu_depart' => 'yaounde-aeroport',
            'total' => 2 * $v['tarifs']['avec'],
        ];
    }
}
