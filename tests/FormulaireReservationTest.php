<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Conducteur;
use Yamoo\Langue;

// Le formulaire de réservation sans chauffeur contrôle l'âge et le permis pendant la
// saisie : ses limites et ses messages doivent être ceux du serveur, tirés des réglages.
#[RequiresPhpExtension('pdo_sqlite')]
final class FormulaireReservationTest extends TestCase
{
    use AvecServeur;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites;');
    }

    public static function langues(): array
    {
        return ['français' => ['fr'], 'anglais' => ['en'], 'arabe' => ['ar']];
    }

    #[DataProvider('langues')]
    public function testLeFormulaireReprendLesMessagesDuServeur(string $langue): void
    {
        $debut = maintenant()->modify('+5 days')->setTime(9, 0);
        $params = http_build_query(['formule' => 'sans', 'debut' => $debut->format('Y-m-d\TH:i'), 'fin' => $debut->modify('+2 days')->format('Y-m-d\TH:i')]);

        [$code, $page] = $this->demande(Langue::prefixe($langue) . '/reserver/jetour-x70-plus?' . $params);

        $this->assertSame(200, $code);
        $this->assertSame(1, preg_match('/<form [^>]*data-formulaire[^>]*>/', $page, $form));
        $attendus = Langue::dans($langue, fn () => [Conducteur::messageAge(), Conducteur::messagePermis()]);
        $this->assertStringContainsString('data-naissance-erreur="' . e($attendus[0]) . '"', $form[0]);
        $this->assertStringContainsString('data-permis-erreur="' . e($attendus[1]) . '"', $form[0]);
        $this->assertStringContainsString('data-naissance-max="' . $debut->modify('-' . config('regles.age_minimum') . ' years')->format('Y-m-d') . '"', $form[0]);
        $this->assertStringContainsString('data-permis-max="' . $debut->modify('-' . config('regles.anciennete_permis_ans') . ' years')->format('Y-m-d') . '"', $form[0]);
    }
}
