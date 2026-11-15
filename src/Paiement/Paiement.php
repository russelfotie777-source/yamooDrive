<?php

namespace Yamoo\Paiement;

use Throwable;
use Yamoo\Reservations;

final class Paiement
{
    public static function fournisseur(): Fournisseur
    {
        $cle = config('paiement.notchpay_cle');
        return config('paiement.fournisseur') === 'notchpay' && $cle ? new NotchPay($cle) : new Demo();
    }

    // Numéro camerounais à 9 chiffres commençant par 6, avec ou sans indicatif.
    public static function numero(string $saisie): ?string
    {
        $chiffres = preg_replace('/\D+/', '', $saisie);
        $chiffres = preg_replace('/^(00)?237/', '', $chiffres);
        return preg_match('/^6\d{8}$/', $chiffres) ? '+237' . $chiffres : null;
    }

    public static function lance(array $r): ?string
    {
        try {
            $reference = self::fournisseur()->lance($r, $r['paiement_operateur'], $r['paiement_numero']);
            Reservations::maj($r['id'], ['paiement_reference' => $reference, 'paiement_statut' => 'en_cours'], 'Demande de paiement envoyée au ' . $r['paiement_numero'] . '.', 'paiement');
            return null;
        } catch (Throwable $e) {
            Reservations::maj($r['id'], ['paiement_statut' => 'echec'], 'Échec du lancement : ' . $e->getMessage(), 'paiement');
            return 'Le paiement n’a pas pu être lancé. Vérifiez le numéro puis réessayez, ou choisissez le paiement à la remise des clés.';
        }
    }

    // Interroge le fournisseur et met la réservation à jour. Retourne la réservation relue.
    public static function verifie(array $r): array
    {
        if ($r['statut'] !== 'attente_paiement' || !$r['paiement_reference']) {
            return $r;
        }
        try {
            $etat = self::fournisseur()->statut($r);
        } catch (Throwable) {
            return $r;
        }
        if ($etat === 'paye') {
            Reservations::maj($r['id'], ['paiement_statut' => 'paye', 'statut' => 'confirmee', 'expire_le' => null], 'Paiement reçu : ' . fcfa($r['total']) . '.', 'paiement');
        } elseif ($etat === 'echec') {
            Reservations::maj($r['id'], ['paiement_statut' => 'echec'], 'Paiement refusé ou annulé sur le téléphone.', 'paiement');
        }
        return Reservations::parCode($r['code']);
    }
}
