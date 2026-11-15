<?php

namespace Yamoo;

use DateTimeImmutable;
use DateTimeZone;

// Contrôles de la location sans chauffeur : âge, ancienneté du permis, pièces justificatives.
final class Conducteur
{
    public const PIECES = [
        'identite' => 'Pièce d’identité',
        'permis' => 'Permis de conduire',
        'domicile' => 'Justificatif de domicile ou de séjour',
    ];
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif', 'application/pdf' => 'pdf'];
    private const TAILLE_MAX = 10 * 1024 * 1024;

    public static function date(?string $saisie): ?DateTimeImmutable
    {
        if (!$saisie || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $saisie)) {
            return null;
        }
        return DateTimeImmutable::createFromFormat('!Y-m-d', $saisie, new DateTimeZone(config('fuseau'))) ?: null;
    }

    /** @return array<string, string> erreurs par champ */
    public static function verifie(?DateTimeImmutable $naissance, ?DateTimeImmutable $permis, DateTimeImmutable $depart): array
    {
        $erreurs = [];
        $age = config('regles.age_minimum');
        $anciennete = config('regles.anciennete_permis_ans');
        if (!$naissance) {
            $erreurs['naissance'] = 'Indiquez la date de naissance du conducteur.';
        } elseif ($naissance->modify("+$age years") > $depart) {
            $erreurs['naissance'] = "Le conducteur doit avoir au moins $age ans le jour du départ. Choisissez la formule avec chauffeur.";
        }
        if (!$permis) {
            $erreurs['permis'] = 'Indiquez la date d’obtention du permis.';
        } elseif ($permis > maintenant()) {
            $erreurs['permis'] = 'La date d’obtention du permis est dans le futur. Vérifiez-la.';
        } elseif ($permis->modify("+$anciennete years") > $depart) {
            $erreurs['permis'] = "Le permis doit avoir au moins $anciennete ans le jour du départ. Choisissez la formule avec chauffeur.";
        }
        return $erreurs;
    }

    // Vérifie les fichiers reçus sans les enregistrer.
    public static function verifiePieces(array $fichiers): array
    {
        $erreurs = [];
        foreach (array_keys(self::PIECES) as $piece) {
            $liste = self::normalise($fichiers[$piece] ?? null);
            $cle = 'piece_' . $piece;
            if (!$liste) {
                $erreurs[$cle] = self::PIECES[$piece] . ' : ajoutez une photo ou un PDF.';
                continue;
            }
            foreach ($liste as $f) {
                if ($f['error'] !== UPLOAD_ERR_OK) {
                    $erreurs[$cle] = self::PIECES[$piece] . ' : le fichier n’a pas été reçu. Réessayez avec une photo plus légère.';
                } elseif ($f['size'] > self::TAILLE_MAX) {
                    $erreurs[$cle] = self::PIECES[$piece] . ' : chaque fichier doit faire moins de 10 Mo.';
                } elseif (!isset(self::TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])])) {
                    $erreurs[$cle] = self::PIECES[$piece] . ' : envoyez une photo (JPG, PNG, WebP, HEIC) ou un PDF.';
                }
            }
        }
        return $erreurs;
    }

    // Les pièces restent hors du dossier public, sous un nom aléatoire.
    public static function enregistrePieces(array $fichiers, string $code): array
    {
        $dossier = dirname(__DIR__) . '/stockage/pieces/' . $code;
        if (!is_dir($dossier)) {
            mkdir($dossier, 0750, true);
        }
        $enregistres = [];
        foreach (array_keys(self::PIECES) as $piece) {
            foreach (self::normalise($fichiers[$piece] ?? null) as $i => $f) {
                $ext = self::TYPES[(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name'])];
                $nom = $piece . '-' . ($i + 1) . '-' . bin2hex(random_bytes(6)) . '.' . $ext;
                move_uploaded_file($f['tmp_name'], "$dossier/$nom");
                $enregistres[$piece][] = $nom;
            }
        }
        return $enregistres;
    }

    public static function chemin(string $code, string $nom): ?string
    {
        if (!preg_match('/^[a-z]+-\d+-[a-f0-9]{12}\.[a-z]+$/', $nom) || !preg_match('/^YD-[A-Z0-9]{6}$/', $code)) {
            return null;
        }
        $chemin = dirname(__DIR__) . "/stockage/pieces/$code/$nom";
        return is_file($chemin) ? $chemin : null;
    }

    private static function normalise(?array $champ): array
    {
        if (!$champ || !isset($champ['name'])) {
            return [];
        }
        if (!is_array($champ['name'])) {
            return $champ['error'] === UPLOAD_ERR_NO_FILE ? [] : [$champ];
        }
        $liste = [];
        foreach ($champ['name'] as $i => $nom) {
            if ($champ['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $liste[] = ['name' => $nom, 'tmp_name' => $champ['tmp_name'][$i], 'size' => $champ['size'][$i], 'error' => $champ['error'][$i]];
        }
        return array_slice($liste, 0, 4);
    }
}
