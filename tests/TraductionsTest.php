<?php

namespace Yamoo\Tests;

use MessageFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yamoo\Conducteur;
use Yamoo\Langue;

// langues/textes.php doit suivre le code : un texte ajouté sans traduction, une
// traduction qui perd un {paramètre} ou une forme ICU cassée font échouer ces tests.
final class TraductionsTest extends TestCase
{
    // Ordre des colonnes de langues/textes.php.
    private const LOCALES = ['en' => 'en_GB', 'es' => 'es_ES', 'de' => 'de_DE', 'zh' => 'zh_Hans_CN', 'ar' => 'ar@numbers=latn'];

    private static array $textes;

    public static function setUpBeforeClass(): void
    {
        self::$textes = require dirname(__DIR__) . '/langues/textes.php';
    }

    public function testChaqueTexteDuCodeEstTraduit(): void
    {
        $manquants = [];
        foreach ($this->textesDuCode() as $texte => $fichier) {
            if (!isset(self::$textes[$texte])) {
                $manquants[] = "$fichier : $texte";
            }
        }

        $this->assertSame([], $manquants, 'Textes à ajouter dans langues/textes.php');
    }

    public function testTextesDeLaFlotteEtDesReglagesTraduits(): void
    {
        $manquants = array_values(array_filter(
            array_unique($this->textesDesReglages()),
            fn ($t) => !isset(self::$textes[$t])
        ));

        $this->assertSame([], $manquants, 'Textes de config/ à ajouter dans langues/textes.php');
    }

    public function testChaqueTexteASesCinqTraductions(): void
    {
        $incomplets = [];
        foreach (self::$textes as $fr => $traductions) {
            if (!is_array($traductions) || count($traductions) !== 5 || in_array('', array_map('trim', $traductions), true)) {
                $incomplets[] = $fr;
            }
        }

        $this->assertSame([], $incomplets);
    }

    public function testLesTraductionsGardentLesParametres(): void
    {
        $ecarts = [];
        foreach (self::$textes as $fr => $traductions) {
            $attendus = $this->parametres($fr);
            foreach (array_keys(self::LOCALES) as $i => $langue) {
                if ($this->parametres($traductions[$i]) !== $attendus) {
                    $ecarts[] = "[$langue] $fr";
                }
            }
        }

        $this->assertSame([], $ecarts);
    }

    public function testLesFormesIcuSontValides(): void
    {
        $invalides = [];
        foreach (self::$textes as $fr => $traductions) {
            if (str_contains($fr, '{') && MessageFormatter::create('fr_FR', $fr) === null) {
                $invalides[] = "[fr] $fr";
            }
            foreach (array_values(self::LOCALES) as $i => $locale) {
                if (str_contains($traductions[$i], '{') && MessageFormatter::create($locale, $traductions[$i]) === null) {
                    $invalides[] = "[$locale] {$traductions[$i]}";
                }
            }
        }

        $this->assertSame([], $invalides);
    }

    public static function nombresEnArabe(): array
    {
        $permis = 'Le permis doit avoir au moins {ans} ans le jour du départ. Choisissez la formule avec chauffeur.';
        $delai = 'Le départ doit être au moins {heures} heures après maintenant. Choisissez une heure plus tardive.';
        return [
            'permis de 2 ans' => [$permis, ['ans' => 2], 'عمر الرخصة عامين على الأقل'],
            'permis de 3 ans' => [$permis, ['ans' => 3], 'عمر الرخصة 3 أعوام على الأقل'],
            'permis de 11 ans' => [$permis, ['ans' => 11], 'عمر الرخصة 11 عامًا على الأقل'],
            'conducteur de 23 ans' => ['Le conducteur doit avoir au moins {age} ans le jour du départ. Choisissez la formule avec chauffeur.', ['age' => 23], 'عن 23 عامًا يوم'],
            'départ dans 2 heures' => [$delai, ['heures' => 2], 'بعد ساعتين على الأقل'],
            'départ dans 3 heures' => [$delai, ['heures' => 3], 'بعد 3 ساعات على الأقل'],
            'tolérance de 60 minutes' => ['Une tolérance de {minutes} minutes est accordée au retour. Au-delà, une journée supplémentaire peut être facturée.', ['minutes' => 60], 'مدتها 60 دقيقة عند'],
            '7 places' => ['{n} places', ['n' => 7], '7 مقاعد'],
        ];
    }

    // En arabe, le nom compté change de forme avec le nombre : عامين (2), 3 أعوام, 11 عامًا.
    #[DataProvider('nombresEnArabe')]
    public function testLeNomCompteSAccordeEnArabe(string $fr, array $valeurs, string $attendu): void
    {
        $texte = MessageFormatter::formatMessage(self::LOCALES['ar'], self::$textes[$fr][4], $valeurs);

        $this->assertStringContainsString($attendu, $texte);
    }

    // Une clé écrite deux fois écrase la première sans prévenir.
    public function testAucunTexteEnDouble(): void
    {
        $cles = [];
        $profondeur = 0;
        $precedent = null;
        foreach (token_get_all(file_get_contents(dirname(__DIR__) . '/langues/textes.php')) as $jeton) {
            if (is_array($jeton) && in_array($jeton[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }
            $profondeur += match ($jeton) { '[' => 1, ']' => -1, default => 0 };
            if (is_array($jeton) && $jeton[0] === T_DOUBLE_ARROW && $profondeur === 1 && is_array($precedent)) {
                $cles[] = $this->litteral($precedent[1]);
            }
            $precedent = $jeton;
        }

        $doublons = array_keys(array_filter(array_count_values($cles), fn ($n) => $n > 1));
        $this->assertSame([], $doublons);
        $this->assertCount(count(self::$textes), $cles);
    }

    /** @return array<string, string> texte => premier fichier où il apparaît */
    private function textesDuCode(): array
    {
        $racine = dirname(__DIR__);
        $fichiers = array_merge(
            glob("$racine/src/*.php"),
            glob("$racine/src/*/*.php"),
            glob("$racine/vues/*.php"),
            glob("$racine/vues/partiels/*.php"),
        );
        $textes = [];
        foreach ($fichiers as $fichier) {
            $jetons = array_values(array_filter(token_get_all(file_get_contents($fichier)), fn ($j) => !is_array($j) || $j[0] !== T_WHITESPACE));
            foreach ($jetons as $i => $j) {
                $appel = is_array($j) && $j[0] === T_STRING && in_array($j[1], ['t', 'h'], true)
                    && ($jetons[$i + 1] ?? null) === '('
                    && is_array($jetons[$i + 2] ?? null) && $jetons[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
                    && !(is_array($jetons[$i - 1] ?? null) && in_array($jetons[$i - 1][0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true));
                if ($appel) {
                    $textes[$this->litteral($jetons[$i + 2][1])] ??= substr($fichier, strlen($racine) + 1);
                }
            }
        }
        $this->assertGreaterThan(300, count($textes), 'La recherche des textes du code ne trouve presque rien.');
        return $textes;
    }

    /** @return list<string> textes écrits dans config/ et dans le code, traduits à l'affichage */
    private function textesDesReglages(): array
    {
        $textes = array_merge(
            array_column(config('lieux'), 'nom'),
            array_column(config('lieux'), 'court'),
            array_values(config('categories')),
            array_values(config('missions')),
            array_values(Conducteur::PIECES),
            Langue::TEXTES_JS,
        );
        foreach (require dirname(__DIR__) . '/config/flotte.php' as $v) {
            array_push($textes, $v['type'], $v['resume'], ...$v['equipements'], ...$v['ideal']);
            if (isset($v['places_texte'])) {
                $textes[] = $v['places_texte'];
            }
            foreach ($v['galerie'] ?? [] as $photo) {
                $textes[] = $photo[1];
            }
        }
        return $textes;
    }

    // Noms des paramètres ICU : {prix}, {n, plural, …}. Les branches comme « one {# jour} » n'en sont pas.
    private function parametres(string $texte): array
    {
        preg_match_all('/\{\s*([a-z_]\w*)\s*(?:\}|,\s*(?:plural|select|selectordinal|number|date|time)\b)/i', $texte, $m);
        $noms = array_values(array_unique($m[1]));
        sort($noms);
        return $noms;
    }

    private function litteral(string $source): string
    {
        $corps = substr($source, 1, -1);
        return $source[0] === '"' ? stripcslashes($corps) : str_replace(["\\'", '\\\\'], ["'", '\\'], $corps);
    }
}
