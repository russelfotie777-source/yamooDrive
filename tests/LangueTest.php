<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Yamoo\Langue;

final class LangueTest extends TestCase
{
    protected function tearDown(): void
    {
        Langue::$code = 'fr';
        Langue::$chemin = '/';
    }

    public function testFrancaisALaRacine(): void
    {
        $this->assertSame('/vehicules/jetour-t2', Langue::depuisChemin('/vehicules/jetour-t2'));
        $this->assertSame('fr', Langue::$code);
        $this->assertSame('', Langue::prefixe());
    }

    public function testPrefixeDeLangueRetireDuChemin(): void
    {
        $this->assertSame('/vehicules', Langue::depuisChemin('/ar/vehicules'));
        $this->assertSame('ar', Langue::$code);
        $this->assertSame('/ar', Langue::prefixe());
        $this->assertSame('rtl', Langue::info('sens'));
    }

    public function testAccueilDUneLangue(): void
    {
        $this->assertSame('/', Langue::depuisChemin('/en'));
        $this->assertSame('en', Langue::$code);
    }

    public function testUnMotQuiCommenceCommeUneLangueNEnEstPasUne(): void
    {
        $this->assertSame('/english', Langue::depuisChemin('/english'));
        $this->assertSame('fr', Langue::$code);
    }

    public function testLiensDansLaLangueDeLaPage(): void
    {
        $this->assertSame('/vehicules?formule=avec', url('/vehicules', ['formule' => 'avec', 'lieu' => null]));

        Langue::choisit('de');
        $this->assertSame('/de/vehicules?formule=avec', url('/vehicules', ['formule' => 'avec', 'lieu' => '']));
    }

    public function testLangueInconnueIgnoree(): void
    {
        Langue::choisit('pt');

        $this->assertSame('fr', Langue::$code);
    }

    public function testTexteSansTraductionResteEnFrancais(): void
    {
        Langue::choisit('en');

        $this->assertSame('Un texte qui n’existe nulle part', t('Un texte qui n’existe nulle part'));
        $this->assertSame('Choose a vehicle.', t('Choisissez un véhicule.'));
    }

    public static function montants(): array
    {
        return [
            'français' => ['fr', "60\u{202F}000\u{00A0}FCFA"],
            'anglais' => ['en', "60,000\u{00A0}FCFA"],
            'allemand' => ['de', "60.000\u{00A0}FCFA"],
            // En arabe, le montant reste un bloc lu de gauche à droite, en chiffres latins.
            'arabe' => ['ar', "\u{2066}60,000\u{00A0}FCFA\u{2069}"],
        ];
    }

    #[DataProvider('montants')]
    public function testMontantsEnFcfa(string $langue, string $attendu): void
    {
        Langue::choisit($langue);

        $this->assertSame($attendu, fcfa(60000));
    }

    public function testPlurielsSelonLaLangue(): void
    {
        $this->assertSame(['1 jour', '2 jours'], [nb_jours(1), nb_jours(2)]);

        Langue::choisit('de');
        $this->assertSame(['1 Tag', '2 Tage'], [nb_jours(1), nb_jours(2)]);

        // L'arabe a une forme pour un, pour deux, pour quelques-uns et pour beaucoup.
        Langue::choisit('ar');
        $this->assertSame(['يوم واحد', 'يومين', '3 أيام', '11 يومًا'], [nb_jours(1), nb_jours(2), nb_jours(3), nb_jours(11)]);
    }

    public function testDatesDansLaLangueDeLaPage(): void
    {
        $this->assertSame('1 mars', date_texte('2027-03-01 09:00'));

        Langue::choisit('de');
        $this->assertSame('1. März', date_texte('2027-03-01 09:00'));

        Langue::choisit('zh');
        $this->assertSame('3月1日', date_texte('2027-03-01 09:00'));
    }

    public function testTelephoneLisible(): void
    {
        $this->assertSame('+237 6 72 89 40 84', telephone_lisible('+237672894084'));
        $this->assertSame('0612', telephone_lisible('0612'));
        $this->assertSame('', telephone_lisible(null));
    }

    public function testLienWhatsapp(): void
    {
        $numero = config('contact.whatsapp');

        $this->assertSame("https://wa.me/$numero", lien_whatsapp());
        $this->assertSame("https://wa.me/$numero?text=Bonjour%20Yamoo%20Drive", lien_whatsapp('Bonjour Yamoo Drive'));
    }

    public function testEchappeLeHtml(): void
    {
        $this->assertSame('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', e("<script>alert('x')</script>"));
        $this->assertSame('', e(null));
    }
}
