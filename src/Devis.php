<?php

namespace Yamoo;

use DateTimeImmutable;

// Règles de prix des conditions générales : une journée = 24 heures à partir de la prise
// en charge, une heure de tolérance au retour, frais de rapatriement pour un aller simple.
final class Devis
{
    public static function jours(DateTimeImmutable $debut, DateTimeImmutable $fin): int
    {
        $minutes = intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60);
        $tolerance = config('regles.tolerance_retour_minutes');
        return max(1, (int) ceil(($minutes - $tolerance) / 1440));
    }

    public static function rapatriement(string $lieuDepart, string $lieuRetour): int
    {
        $a = config("lieux.$lieuDepart.ville");
        $b = config("lieux.$lieuRetour.ville");
        if (!$a || !$b || $a === $b) {
            return 0;
        }
        $cle = implode('|', [min($a, $b), max($a, $b)]);
        return (int) config("montants.rapatriement.$cle", 0);
    }

    public static function calcule(Demande $d): array
    {
        $v = $d->vehicule;
        $jours = self::jours($d->debut, $d->fin);
        $prixJour = $v['tarifs'][$d->formule];
        $location = $jours * $prixJour;
        $rapatriement = self::rapatriement($d->lieuDepart, $d->lieuRetour);
        $limite = $d->debut->modify('-' . config('regles.annulation_gratuite_heures') . ' hours');
        return [
            'jours' => $jours,
            'prix_jour' => $prixJour,
            'montant_location' => $location,
            'frais_rapatriement' => $rapatriement,
            'total' => $location + $rapatriement,
            'caution' => $d->formule === 'sans' ? (int) config('montants.caution_sans_chauffeur') : 0,
            'annulation_jusqua' => $limite > maintenant() ? $limite->format('Y-m-d H:i') : null,
            'retour_tolere' => $d->debut->modify('+' . ($jours * 24) . ' hours')->modify('+' . config('regles.tolerance_retour_minutes') . ' minutes')->format('Y-m-d H:i'),
        ];
    }
}
