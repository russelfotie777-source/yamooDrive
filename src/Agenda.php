<?php

namespace Yamoo;

use DateTimeZone;

// Fichier .ics d'une réservation, à ouvrir dans l'agenda du téléphone.
// Norme iCalendar (RFC 5545) : heures en UTC, lignes finies par CRLF et pliées à 75 octets.
final class Agenda
{
    public static function ics(array $r): string
    {
        $utc = new DateTimeZone('UTC');
        $f = fn (string $d) => date_locale($d)->setTimezone($utc)->format('Ymd\THis\Z');
        $lignes = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Yamoo Drive//Reservation//FR', 'CALSCALE:GREGORIAN',
            'BEGIN:VEVENT',
            'UID:' . $r['code'] . '@yamoodrive.com',
            'DTSTAMP:' . gmdate('Ymd\THis\Z'),
            'DTSTART:' . $f($r['debut']),
            'DTEND:' . $f($r['fin']),
            'SUMMARY:' . self::echappe(t('Location {nom}', ['nom' => $r['v']['nom']]) . ' – Yamoo Drive'),
            'LOCATION:' . self::echappe(lieu($r['lieu_depart'])),
            'DESCRIPTION:' . self::echappe(t('Réservation {code}', ['code' => $r['code']]) . '. ' . t('Total') . ' ' . fcfa($r['total']) . '. WhatsApp ' . config('contact.telephone_affiche')),
            'END:VEVENT', 'END:VCALENDAR',
        ];
        return implode("\r\n", array_map([self::class, 'plie'], $lignes)) . "\r\n";
    }

    // Au-delà de 75 octets, la ligne continue sur la suivante, qui commence par une espace.
    // La coupure tombe entre deux caractères : une lettre accentuée, chinoise ou arabe
    // n'est jamais coupée en deux.
    public static function plie(string $ligne): string
    {
        $morceaux = [];
        $courant = '';
        foreach (mb_str_split($ligne, 1, 'UTF-8') as $caractere) {
            if (strlen($courant) + strlen($caractere) > 75) {
                $morceaux[] = $courant;
                $courant = ' ';
            }
            $courant .= $caractere;
        }
        $morceaux[] = $courant;
        return implode("\r\n", $morceaux);
    }

    private static function echappe(string $texte): string
    {
        return addcslashes($texte, ",;\\");
    }
}
