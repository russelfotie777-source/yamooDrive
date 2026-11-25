<?php

namespace Yamoo;

// Langue de la page. Le français vit à la racine du site, les autres langues sous un
// préfixe (/en/, /es/…). Les textes sont écrits en français dans le code et traduits
// par langues/textes.php : une ligne par texte français, ses cinq traductions à côté.
final class Langue
{
    public const LANGUES = [
        'fr' => ['nom' => 'Français', 'locale' => 'fr_FR', 'html' => 'fr', 'intl' => 'fr-FR', 'og' => 'fr_CM', 'sens' => 'ltr'],
        'en' => ['nom' => 'English', 'locale' => 'en_GB', 'html' => 'en', 'intl' => 'en-GB', 'og' => 'en_GB', 'sens' => 'ltr'],
        'es' => ['nom' => 'Español', 'locale' => 'es_ES', 'html' => 'es', 'intl' => 'es-ES', 'og' => 'es_ES', 'sens' => 'ltr'],
        'de' => ['nom' => 'Deutsch', 'locale' => 'de_DE', 'html' => 'de', 'intl' => 'de-DE', 'og' => 'de_DE', 'sens' => 'ltr'],
        'zh' => ['nom' => '中文', 'locale' => 'zh_Hans_CN', 'html' => 'zh-Hans', 'intl' => 'zh-Hans-CN', 'og' => 'zh_CN', 'sens' => 'ltr'],
        // Chiffres latins en arabe : ce sont ceux des plaques, des prix et des téléphones au Tchad et au Cameroun.
        'ar' => ['nom' => 'العربية', 'locale' => 'ar@numbers=latn', 'html' => 'ar', 'intl' => 'ar-u-nu-latn', 'og' => 'ar_TD', 'sens' => 'rtl'],
    ];

    // Textes utilisés par les scripts du navigateur, envoyés traduits avec chaque page.
    public const TEXTES_JS = [
        'Passer en mode clair', 'Passer en mode sombre', 'Lien copié', 'Copiez ce lien :',
        'Ajouter une date', 'Choisir les dates', 'Mois précédent', 'Mois suivant', 'Heure de départ', 'Heure de retour',
        'Effacer les dates', 'Valider', 'Du {debut} au {fin}', 'Touchez le jour du retour', 'Touchez le jour du départ',
        'déjà réservé', 'départ', 'retour',
        'Choisir mes dates', 'Réserver', 'par jour', 'Vérifiez les dates.',
        'Le prix n’a pas pu être calculé. Vérifiez votre connexion puis réessayez.',
        'Envoyer ma demande', 'Payer {total}', 'Âge vérifié pour la date de départ.', 'Ancienneté du permis vérifiée.',
        'Le conducteur doit avoir au moins 23 ans le jour du départ.', 'Le permis doit avoir au moins 2 ans le jour du départ.',
        'Fichiers ajoutés : {n}', 'Envoi en cours…',
        'Paiement reçu. Ouverture de votre réservation…', 'Connexion perdue. Nouvel essai dans quelques secondes…',
        'Toujours en attente. Vérifiez votre téléphone.',
        'Je regarde…', 'La réponse n’est pas arrivée. Vérifiez votre connexion puis réessayez, ou écrivez-nous sur WhatsApp.',
    ];

    // Ordre des traductions dans langues/textes.php.
    private const COLONNES = ['en' => 0, 'es' => 1, 'de' => 2, 'zh' => 3, 'ar' => 4];

    public static string $code = 'fr';
    /** Chemin de la page sans le préfixe de langue, pour construire les liens vers les autres langues. */
    public static string $chemin = '/';
    private static ?array $textes = null;

    // Retire le préfixe de langue du chemin demandé et retient la langue.
    public static function depuisChemin(string $chemin): string
    {
        if (preg_match('#^/(' . implode('|', array_keys(self::LANGUES)) . ')(/.*)?$#', $chemin, $m) && $m[1] !== 'fr') {
            self::$code = $m[1];
            $chemin = $m[2] ?? '/';
        }
        self::$chemin = $chemin === '' ? '/' : $chemin;
        return self::$chemin;
    }

    public static function choisit(string $code): void
    {
        if (isset(self::LANGUES[$code])) {
            self::$code = $code;
        }
    }

    /**
     * Exécute $faire dans une autre langue, puis revient à celle de la page : un texte
     * destiné au client part dans la langue où il a réservé, même depuis l'espace équipe.
     * @template T
     * @param callable(): T $faire
     * @return T
     */
    public static function dans(string $code, callable $faire): mixed
    {
        $avant = self::$code;
        self::choisit($code);
        try {
            return $faire();
        } finally {
            self::$code = $avant;
        }
    }

    public static function prefixe(?string $code = null): string
    {
        $code ??= self::$code;
        return $code === 'fr' ? '' : '/' . $code;
    }

    public static function info(string $cle): string
    {
        return self::LANGUES[self::$code][$cle];
    }

    public static function textesJs(): array
    {
        return array_combine(self::TEXTES_JS, array_map([self::class, 'traduit'], self::TEXTES_JS));
    }

    public static function traduit(string $texte): string
    {
        if (self::$code === 'fr') {
            return $texte;
        }
        self::$textes ??= require dirname(__DIR__) . '/langues/textes.php';
        $traduction = self::$textes[$texte][self::COLONNES[self::$code]] ?? null;
        if ($traduction === null) {
            // En développement, RELEVE_TRADUCTIONS=<fichier> note les textes encore sans traduction.
            if ($releve = getenv('RELEVE_TRADUCTIONS')) {
                file_put_contents($releve, json_encode([self::$code, $texte], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
            }
            return $texte;
        }
        return $traduction;
    }
}
