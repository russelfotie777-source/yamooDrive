<?php

namespace Yamoo\Paiement;

use Throwable;
use Yamoo\Base;
use Yamoo\Disponibilite;
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
            return t('Le paiement n’a pas pu être lancé. Vérifiez le numéro puis réessayez, ou choisissez le paiement à la remise des clés.');
        }
    }

    // Interroge le fournisseur et met la réservation à jour. Retourne la réservation relue.
    public static function verifie(array $r): array
    {
        // Le webhook de Notch Pay peut arriver après la fin du délai sans qu'aucune page l'ait
        // encore constatée : la réservation est toujours « en attente » alors que le véhicule
        // est libre pour les autres clients depuis. On constate d'abord, pour ne confirmer
        // qu'après avoir vérifié que personne ne l'a pris.
        if ($r['statut'] === 'attente_paiement' && $r['expire_le'] && $r['expire_le'] <= maintenant()->format('Y-m-d H:i')) {
            Reservations::expire();
            $r = Reservations::parCode($r['code']);
        }
        // Un client qui valide sur son téléphone juste avant la fin du délai peut n'être
        // confirmé par le fournisseur qu'après l'expiration : on interroge encore.
        $tardif = $r['statut'] === 'expiree' && $r['paiement_mode'] === 'mobile' && $r['paiement_statut'] !== 'paye';
        if (($r['statut'] !== 'attente_paiement' && !$tardif) || !$r['paiement_reference']) {
            return $r;
        }
        try {
            $etat = self::fournisseur()->statut($r);
        } catch (Throwable) {
            return $r;
        }
        if ($tardif) {
            return $etat === 'paye' ? self::recuApresDelai($r) : $r;
        }
        if ($etat === 'paye') {
            Reservations::maj($r['id'], ['paiement_statut' => 'paye', 'statut' => 'confirmee', 'expire_le' => null], 'Paiement reçu : ' . fcfa($r['total']) . '.', 'paiement');
        } elseif ($etat === 'echec') {
            Reservations::maj($r['id'], ['paiement_statut' => 'echec'], 'Paiement refusé ou annulé sur le téléphone.', 'paiement');
        }
        return Reservations::parCode($r['code']);
    }

    // L'argent est arrivé alors que le véhicule était libéré : la réservation reprend si
    // personne ne l'a pris entre-temps, sinon l'équipe doit rembourser.
    private static function recuApresDelai(array $r): array
    {
        $pdo = Base::pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        if ($r['v'] && Disponibilite::estLibre($r['v'], date_locale($r['debut']), date_locale($r['fin']), $r['id'])) {
            Reservations::maj($r['id'], ['paiement_statut' => 'paye', 'statut' => 'confirmee', 'expire_le' => null], 'Paiement reçu après le délai : ' . fcfa($r['total']) . '. Véhicule encore libre, réservation confirmée.', 'paiement');
        } else {
            Reservations::maj($r['id'], ['paiement_statut' => 'paye'], 'Paiement reçu après le délai : ' . fcfa($r['total']) . '. Le véhicule a été réservé entre-temps : remboursement à faire.', 'paiement');
        }
        $pdo->exec('COMMIT');
        return Reservations::parCode($r['code']);
    }
}
