<?php

namespace Yamoo;

use DateTimeImmutable;

// Un véhicule est pris par les réservations en attente de paiement (non expirées),
// les demandes à confirmer, les réservations confirmées et les blocages de l'équipe.
// L'heure de tolérance au retour reste réservée au client.
final class Disponibilite
{
    /** @return list<array{debut: string, fin: string, type: string}> */
    public static function occupations(string $vehicule, ?string $depuis = null, ?int $saufId = null): array
    {
        $tolerance = (int) config('regles.tolerance_retour_minutes');
        $maintenant = maintenant()->format('Y-m-d H:i');
        $depuis ??= $maintenant;
        $q = Base::pdo()->prepare(
            "SELECT id, debut, fin, statut FROM reservations
             WHERE vehicule = :v AND fin >= :depuis AND (:sauf IS NULL OR id != :sauf)
               AND (statut IN ('demande', 'confirmee') OR (statut = 'attente_paiement' AND expire_le > :maintenant))"
        );
        $q->execute(['v' => $vehicule, 'depuis' => $depuis, 'sauf' => $saufId, 'maintenant' => $maintenant]);
        $liste = [];
        foreach ($q as $r) {
            $liste[] = [
                'debut' => $r['debut'],
                'fin' => date_locale($r['fin'])->modify("+$tolerance minutes")->format('Y-m-d H:i'),
                'type' => 'reservation',
            ];
        }
        $q = Base::pdo()->prepare('SELECT debut, fin, motif FROM indisponibilites WHERE vehicule = ? AND fin >= ?');
        $q->execute([$vehicule, $depuis]);
        foreach ($q as $r) {
            $liste[] = ['debut' => $r['debut'], 'fin' => $r['fin'], 'type' => 'blocage'];
        }
        usort($liste, fn ($a, $b) => strcmp($a['debut'], $b['debut']));
        return $liste;
    }

    public static function estLibre(array $v, DateTimeImmutable $debut, DateTimeImmutable $fin, ?int $saufId = null): bool
    {
        $d = $debut->format('Y-m-d H:i');
        $f = $fin->format('Y-m-d H:i');
        $chevauchements = array_filter(
            self::occupations($v['slug'], null, $saufId),
            fn ($o) => $o['debut'] < $f && $o['fin'] > $d
        );
        return count($chevauchements) < $v['unites'];
    }

    // Fin de l'occupation qui gêne la période demandée : « réservé jusqu'au … ».
    public static function libreA(array $v, DateTimeImmutable $debut, DateTimeImmutable $fin): ?DateTimeImmutable
    {
        $d = $debut->format('Y-m-d H:i');
        $f = $fin->format('Y-m-d H:i');
        $fins = array_map(
            fn ($o) => $o['fin'],
            array_filter(self::occupations($v['slug']), fn ($o) => $o['debut'] < $f && $o['fin'] > $d)
        );
        return $fins ? date_locale(max($fins)) : null;
    }

    // Disponible maintenant ou prochaine date de retour, pour la liste sans dates.
    public static function etatActuel(array $v): ?DateTimeImmutable
    {
        $m = maintenant()->format('Y-m-d H:i');
        foreach (self::occupations($v['slug']) as $o) {
            if ($o['debut'] <= $m && $o['fin'] > $m) {
                return date_locale($o['fin']);
            }
        }
        return null;
    }

    /** @return list<string> jours (AAAA-MM-JJ) touchés par une occupation, pour le calendrier */
    public static function joursPris(array $v, int $horizon = 180): array
    {
        $jours = [];
        $limite = maintenant()->modify("+$horizon days")->format('Y-m-d');
        foreach (self::occupations($v['slug']) as $o) {
            $j = date_locale(substr($o['debut'], 0, 10));
            $fin = date_locale($o['fin']);
            while ($j < $fin && $j->format('Y-m-d') <= $limite) {
                $jours[$j->format('Y-m-d')] = true;
                $j = $j->modify('+1 day');
            }
        }
        return array_keys($jours);
    }
}
