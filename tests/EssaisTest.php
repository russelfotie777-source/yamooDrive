<?php

namespace Yamoo\Tests;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Yamoo\Base;
use Yamoo\Essais;

#[RequiresPhpExtension('pdo_sqlite')]
final class EssaisTest extends TestCase
{
    protected function setUp(): void
    {
        Base::pdo()->exec('DELETE FROM essais_connexion');
    }

    public function testQuatreEchecsLaissentReessayer(): void
    {
        $this->echecs('203.0.113.7', Essais::MAX - 1);

        $this->assertNull(Essais::bloqueJusqua('203.0.113.7'));
    }

    public function testCinqEchecsBloquentQuinzeMinutesApresLePlusAncien(): void
    {
        $premier = maintenant()->modify('-4 minutes');
        $this->insere('203.0.113.7', $premier);
        $this->echecs('203.0.113.7', Essais::MAX - 1);

        $jusqua = Essais::bloqueJusqua('203.0.113.7');

        $this->assertSame($premier->modify('+' . Essais::FENETRE_MINUTES . ' minutes')->format('Y-m-d H:i:s'), $jusqua->format('Y-m-d H:i:s'));
    }

    public function testUneAutreAdresseNEstPasBloquee(): void
    {
        $this->echecs('203.0.113.7', Essais::MAX);

        $this->assertNull(Essais::bloqueJusqua('198.51.100.20'));
    }

    public function testLesEchecsSortentDeLaFenetre(): void
    {
        foreach (range(1, Essais::MAX) as $i) {
            $this->insere('203.0.113.7', maintenant()->modify('-' . (Essais::FENETRE_MINUTES + $i) . ' minutes'));
        }

        $this->assertNull(Essais::bloqueJusqua('203.0.113.7'));
        Essais::echec('203.0.113.7');
        $this->assertSame(1, (int) Base::pdo()->query('SELECT COUNT(*) FROM essais_connexion')->fetchColumn());
    }

    public function testUneConnexionReussieEffaceLesEchecs(): void
    {
        $this->echecs('203.0.113.7', Essais::MAX - 1);

        Essais::reussite('203.0.113.7');

        $this->echecs('203.0.113.7', Essais::MAX - 1);
        $this->assertNull(Essais::bloqueJusqua('203.0.113.7'));
    }

    private function echecs(string $ip, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            Essais::echec($ip);
        }
    }

    private function insere(string $ip, \DateTimeImmutable $quand): void
    {
        Base::pdo()->prepare('INSERT INTO essais_connexion (ip, cree_le) VALUES (?, ?)')->execute([$ip, $quand->format('Y-m-d H:i:s')]);
    }
}
