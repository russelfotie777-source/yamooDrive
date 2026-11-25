<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;

// L'espace équipe servi par le vrai serveur, avec le mot de passe de tests/bootstrap.php.
#[RequiresPhpExtension('pdo_sqlite')]
final class EspaceEquipeTest extends TestCase
{
    use AvecServeur;

    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM journal; DELETE FROM reservations; DELETE FROM indisponibilites; DELETE FROM essais_connexion;');
    }

    public function testConnexionOuvreLesPagesDeLEquipe(): void
    {
        $cookie = $this->connecteEquipe();

        [$code, $corps] = $this->demande('/equipe/flotte', null, $cookie);

        $this->assertSame(200, $code);
        $this->assertStringContainsString('<h1 class="outil-titre">Flotte</h1>', $corps);
    }

    public function testSansConnexionRetourALaPageDeConnexion(): void
    {
        [$code, , $entetes] = $this->demande('/equipe/flotte');

        $this->assertSame(303, $code);
        $this->assertContains('Location: /equipe', $entetes);
        $this->assertStringContainsString('Connexion de l’équipe', $this->demande('/equipe')[1]);
    }

    public function testMauvaisMotDePasseRefuse(): void
    {
        [, $corps, $entetes] = $this->demande('/equipe');
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $corps, $csrf);
        $cookie = self::cookieDeSession($entetes);

        $this->demande('/equipe/connexion', ['_csrf' => $csrf[1], 'mot_de_passe' => 'pas-le-bon'], $cookie);

        [, $page] = $this->demande('/equipe', null, $cookie);
        $this->assertStringContainsString('Mot de passe incorrect', $page);
        $this->assertSame(303, $this->demande('/equipe/flotte', null, $cookie)[0]);
    }
}
