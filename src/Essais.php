<?php

namespace Yamoo;

use DateTimeImmutable;

// Freine les essais de mot de passe de l'espace équipe : après 5 échecs en 15 minutes,
// une adresse IP attend que le plus ancien sorte de la fenêtre.
final class Essais
{
    public const MAX = 5;
    public const FENETRE_MINUTES = 15;

    /** Heure à partir de laquelle l'adresse peut réessayer, ou null si elle le peut déjà. */
    public static function bloqueJusqua(string $ip): ?DateTimeImmutable
    {
        $q = Base::pdo()->prepare('SELECT cree_le FROM essais_connexion WHERE ip = ? AND cree_le > ? ORDER BY cree_le DESC LIMIT ' . self::MAX);
        $q->execute([$ip, self::debutFenetre()]);
        $echecs = $q->fetchAll(\PDO::FETCH_COLUMN);
        if (count($echecs) < self::MAX) {
            return null;
        }
        return date_locale(end($echecs))->modify('+' . self::FENETRE_MINUTES . ' minutes');
    }

    public static function echec(string $ip): void
    {
        $pdo = Base::pdo();
        $pdo->prepare('DELETE FROM essais_connexion WHERE cree_le <= ?')->execute([self::debutFenetre()]);
        $pdo->prepare('INSERT INTO essais_connexion (ip, cree_le) VALUES (?, ?)')->execute([$ip, maintenant()->format('Y-m-d H:i:s')]);
    }

    public static function reussite(string $ip): void
    {
        Base::pdo()->prepare('DELETE FROM essais_connexion WHERE ip = ?')->execute([$ip]);
    }

    private static function debutFenetre(): string
    {
        return maintenant()->modify('-' . self::FENETRE_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    }
}
