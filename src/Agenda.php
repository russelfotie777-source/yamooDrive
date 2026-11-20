<?php

namespace Yamoo;

use DateTimeZone;

// Fichier .ics d'une réservation, à ouvrir dans l'agenda du téléphone.
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
        return implode("\r\n", $lignes) . "\r\n";
    }

    private static function echappe(string $texte): string
    {
        return addcslashes($texte, ",;\\");
    }
}
