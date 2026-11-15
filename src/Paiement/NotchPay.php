<?php

namespace Yamoo\Paiement;

use RuntimeException;

// Notch Pay encaisse MTN Mobile Money et Orange Money au Cameroun.
// Documentation : https://developer.notchpay.co/accept-payments/charge.md
final class NotchPay implements Fournisseur
{
    private const API = 'https://api.notchpay.co';
    private const CANAUX = ['mtn' => 'cm.mtn', 'orange' => 'cm.orange'];

    public function __construct(private string $cle)
    {
    }

    public function lance(array $reservation, string $operateur, string $numero): string
    {
        $v = $reservation['v'];
        $init = $this->appel('POST', '/payments', [
            'amount' => $reservation['total'],
            'currency' => 'XAF',
            'reference' => $reservation['code'],
            'description' => "Location {$v['nom']} {$reservation['code']}",
            'callback' => url_absolue('/paiement/' . $reservation['code'], ['j' => $reservation['jeton']]),
            'customer' => [
                'name' => $reservation['client_nom'],
                'phone' => $numero,
                'email' => $reservation['client_email'] ?: config('contact.email'),
            ],
        ]);
        $reference = $init['transaction']['reference'] ?? null;
        if (!$reference) {
            throw new RuntimeException('Notch Pay n’a pas renvoyé de référence de transaction.');
        }
        $this->appel('POST', '/payments/' . rawurlencode($reference), [
            'channel' => self::CANAUX[$operateur],
            'data' => ['phone' => $numero],
        ]);
        return $reference;
    }

    public function statut(array $reservation): string
    {
        $r = $this->appel('GET', '/payments/' . rawurlencode((string) $reservation['paiement_reference']));
        return match ($r['transaction']['status'] ?? '') {
            'complete' => 'paye',
            'failed', 'canceled', 'expired', 'rejected' => 'echec',
            default => 'en_cours',
        };
    }

    public function estDemo(): bool
    {
        return false;
    }

    public static function signatureValide(string $corps, ?string $signature): bool
    {
        $hash = config('paiement.notchpay_hash');
        return $hash && $signature && hash_equals(hash_hmac('sha256', $corps, $hash), $signature);
    }

    private function appel(string $methode, string $chemin, ?array $corps = null): array
    {
        $c = curl_init(self::API . $chemin);
        curl_setopt_array($c, [
            CURLOPT_CUSTOMREQUEST => $methode,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: ' . $this->cle, 'Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $corps !== null ? json_encode($corps, JSON_UNESCAPED_UNICODE) : null,
        ]);
        $reponse = curl_exec($c);
        $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $erreur = curl_error($c);
        curl_close($c);
        if ($reponse === false) {
            throw new RuntimeException("Notch Pay injoignable : $erreur");
        }
        $donnees = json_decode($reponse, true) ?: [];
        if ($code >= 400) {
            throw new RuntimeException('Notch Pay a refusé la demande : ' . ($donnees['message'] ?? "HTTP $code"));
        }
        return $donnees;
    }
}
