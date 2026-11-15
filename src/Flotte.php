<?php

namespace Yamoo;

final class Flotte
{
    private static ?array $vehicules = null;

    /** @return array<string, array> */
    public static function tous(): array
    {
        if (self::$vehicules === null) {
            self::$vehicules = [];
            foreach (require dirname(__DIR__) . '/config/flotte.php' as $slug => $v) {
                $v['slug'] = $slug;
                $v['court'] ??= $v['nom'];
                $v['places_texte'] ??= $v['places'] . ' places';
                $v['unites'] ??= 1;
                $v['galerie'] ??= [];
                self::$vehicules[$slug] = $v;
            }
        }
        return self::$vehicules;
    }

    public static function trouve(?string $slug): ?array
    {
        return $slug !== null ? (self::tous()[$slug] ?? null) : null;
    }

    public static function propose(array $v, string $formule): bool
    {
        return isset($v['tarifs'][$formule]);
    }

    public static function prixDepart(array $v, ?string $formule = null): int
    {
        if ($formule && isset($v['tarifs'][$formule])) {
            return $v['tarifs'][$formule];
        }
        return min($v['tarifs']);
    }

    // Formule par défaut : celle demandée si le véhicule la propose, sinon la moins chère.
    public static function formuleParDefaut(array $v, ?string $voulue = null): string
    {
        if ($voulue && isset($v['tarifs'][$voulue])) {
            return $voulue;
        }
        return isset($v['tarifs']['sans']) ? 'sans' : 'avec';
    }

    public static function formulesTexte(array $v): string
    {
        return match (true) {
            isset($v['tarifs']['sans'], $v['tarifs']['avec']) => 'Avec ou sans chauffeur',
            isset($v['tarifs']['avec']) => 'Avec chauffeur',
            default => 'Sans chauffeur',
        };
    }

    /** @return array<string, int> nombre de véhicules par catégorie */
    public static function compteParCategorie(): array
    {
        $compte = [];
        foreach (self::tous() as $v) {
            $compte[$v['categorie']] = ($compte[$v['categorie']] ?? 0) + 1;
        }
        return $compte;
    }

    public static function filtre(?string $categorie = null, ?string $formule = null, int $places = 0): array
    {
        return array_filter(self::tous(), function (array $v) use ($categorie, $formule, $places) {
            return (!$categorie || $v['categorie'] === $categorie)
                && (!$formule || isset($v['tarifs'][$formule]))
                && $v['places'] >= $places;
        });
    }
}
