<?php

namespace Yamoo\Tests;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;
use Yamoo\Flotte;
use Yamoo\Langue;
use Yamoo\PlanDuSite;

final class PlanDuSiteTest extends TestCase
{
    private const RACINE = 'https://yamoodrive.com';

    public function testChaqueVehiculeALaPage(): void
    {
        foreach (array_keys(Flotte::tous()) as $slug) {
            $this->assertContains("/vehicules/$slug", PlanDuSite::pages());
        }
    }

    public function testChaquePageDansLesSixLangues(): void
    {
        $xpath = $this->plan();
        $nbLangues = count(Langue::LANGUES);

        $this->assertSame(count(PlanDuSite::pages()) * $nbLangues, $xpath->query('//s:url')->length);
        foreach ($xpath->query('//s:url') as $url) {
            // Six langues plus x-default, comme dans l'en-tête des pages.
            $this->assertSame($nbLangues + 1, $xpath->query('x:link[@rel="alternate"]', $url)->length);
        }
    }

    public function testAdressesDeLaFicheDuT2(): void
    {
        $xpath = $this->plan();
        $url = $xpath->query('//s:url[s:loc="https://yamoodrive.com/en/vehicules/jetour-t2"]')->item(0);

        $this->assertNotNull($url);
        $this->assertSame('https://yamoodrive.com/vehicules/jetour-t2', $xpath->evaluate('string(x:link[@hreflang="fr"]/@href)', $url));
        $this->assertSame('https://yamoodrive.com/zh/vehicules/jetour-t2', $xpath->evaluate('string(x:link[@hreflang="zh-Hans"]/@href)', $url));
        $this->assertSame('https://yamoodrive.com/vehicules/jetour-t2', $xpath->evaluate('string(x:link[@hreflang="x-default"]/@href)', $url));
    }

    public function testAccueilsDeChaqueLangue(): void
    {
        $locs = array_map(fn ($n) => $n->textContent, iterator_to_array($this->plan()->query('//s:loc')));

        $this->assertContains('https://yamoodrive.com/', $locs);
        $this->assertContains('https://yamoodrive.com/ar/', $locs);
    }

    public function testAucunePagePriveeDansLePlan(): void
    {
        $xml = PlanDuSite::xml(self::RACINE);

        foreach (['/reserver/', '/paiement/', '/reservation/', '/api/', '/equipe'] as $chemin) {
            $this->assertStringNotContainsString($chemin, $xml);
        }
    }

    public function testRobotsFermeLApiEtLEspaceEquipeDansChaqueLangue(): void
    {
        $robots = PlanDuSite::robots(self::RACINE);

        $this->assertStringStartsWith("User-agent: *\n", $robots);
        $this->assertStringContainsString("Disallow: /equipe\n", $robots);
        $this->assertStringContainsString("Disallow: /api/\n", $robots);
        $this->assertStringContainsString("Disallow: /ar/equipe\n", $robots);
        $this->assertStringContainsString("Disallow: /de/api/\n", $robots);
        $this->assertSame(count(PlanDuSite::FERMES) * count(Langue::LANGUES), substr_count($robots, 'Disallow: '));
        $this->assertStringEndsWith("\nSitemap: https://yamoodrive.com/sitemap.xml\n", $robots);
    }

    public function testRobotsDansUnSousDossier(): void
    {
        $robots = PlanDuSite::robots('http://localhost/Yamoodrive', '/Yamoodrive');

        $this->assertStringContainsString("Disallow: /Yamoodrive/en/api/\n", $robots);
        $this->assertStringContainsString('Sitemap: http://localhost/Yamoodrive/sitemap.xml', $robots);
    }

    public function testLesPagesPubliquesNeSontPasFermees(): void
    {
        foreach (PlanDuSite::pages() as $page) {
            foreach (PlanDuSite::FERMES as $prive) {
                $this->assertStringStartsNotWith($prive, $page);
            }
        }
    }

    private function plan(): DOMXPath
    {
        $doc = new DOMDocument();
        $this->assertTrue($doc->loadXML(PlanDuSite::xml(self::RACINE)), 'sitemap.xml mal formé');
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('x', 'http://www.w3.org/1999/xhtml');
        return $xpath;
    }
}
