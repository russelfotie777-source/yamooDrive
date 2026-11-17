<?php

namespace Yamoo;

// robots.txt et sitemap.xml : les pages publiques dans les six langues, reliées entre
// elles. Les formulaires et les pages d'un client (réserver, paiement, réservation)
// restent ouverts aux robots mais portent « noindex » (vues/gabarit.php) : fermés
// dans robots.txt, les robots ne verraient pas ce « noindex » et pourraient garder
// l'adresse des liens « Réserver ». L'API et l'espace équipe, eux, sont fermés.
final class PlanDuSite
{
    public const FERMES = ['/api/', '/equipe'];

    /** @return list<string> chemins publics, sans préfixe de langue */
    public static function pages(): array
    {
        return [
            '/',
            '/vehicules',
            ...array_map(fn ($slug) => "/vehicules/$slug", array_keys(Flotte::tous())),
            '/conditions',
            '/credits',
        ];
    }

    // $racine : adresse du site sans barre finale, par exemple https://yamoodrive.com.
    public static function xml(string $racine): string
    {
        $x = new \XMLWriter();
        $x->openMemory();
        $x->setIndent(true);
        $x->startDocument('1.0', 'UTF-8');
        $x->startElement('urlset');
        $x->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $x->writeAttribute('xmlns:xhtml', 'http://www.w3.org/1999/xhtml');
        foreach (self::pages() as $chemin) {
            foreach (array_keys(Langue::LANGUES) as $code) {
                $x->startElement('url');
                $x->writeElement('loc', self::adresse($racine, $code, $chemin));
                foreach (Langue::LANGUES as $autre => $l) {
                    self::alternative($x, $l['html'], self::adresse($racine, $autre, $chemin));
                }
                self::alternative($x, 'x-default', self::adresse($racine, 'fr', $chemin));
                $x->endElement();
            }
        }
        $x->endElement();
        $x->endDocument();
        return $x->outputMemory();
    }

    public static function robots(string $racine, string $base = ''): string
    {
        $lignes = ['User-agent: *'];
        foreach (array_keys(Langue::LANGUES) as $code) {
            foreach (self::FERMES as $chemin) {
                $lignes[] = 'Disallow: ' . $base . Langue::prefixe($code) . $chemin;
            }
        }
        $lignes[] = '';
        $lignes[] = 'Sitemap: ' . $racine . '/sitemap.xml';
        return implode("\n", $lignes) . "\n";
    }

    // Même forme que les liens hreflang de l'en-tête des pages : /en/ pour l'accueil anglais.
    private static function adresse(string $racine, string $code, string $chemin): string
    {
        return $racine . Langue::prefixe($code) . $chemin;
    }

    private static function alternative(\XMLWriter $x, string $langue, string $href): void
    {
        $x->startElement('xhtml:link');
        $x->writeAttribute('rel', 'alternate');
        $x->writeAttribute('hreflang', $langue);
        $x->writeAttribute('href', $href);
        $x->endElement();
    }
}
