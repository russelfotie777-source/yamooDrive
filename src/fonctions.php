<?php
// Petites fonctions partagées par les contrôleurs et les vues.

function env(string $cle, ?string $defaut = null): ?string
{
    static $fichier = null;
    if ($fichier === null) {
        $fichier = [];
        $chemin = dirname(__DIR__) . '/.env';
        if (is_readable($chemin)) {
            foreach (file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ligne) {
                if ($ligne[0] === '#' || !str_contains($ligne, '=')) {
                    continue;
                }
                [$k, $v] = array_map('trim', explode('=', $ligne, 2));
                $fichier[$k] = trim($v, "\"'");
            }
        }
    }
    $valeur = getenv($cle);
    if ($valeur !== false && $valeur !== '') {
        return $valeur;
    }
    return ($fichier[$cle] ?? '') !== '' ? $fichier[$cle] : $defaut;
}

function config(string $chemin, mixed $defaut = null): mixed
{
    static $c = null;
    $c ??= require dirname(__DIR__) . '/config/app.php';
    $valeur = $c;
    foreach (explode('.', $chemin) as $cle) {
        if (!is_array($valeur) || !array_key_exists($cle, $valeur)) {
            return $defaut;
        }
        $valeur = $valeur[$cle];
    }
    return $valeur;
}

function e(?string $texte): string
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Le site peut vivre à la racine (php -S) ou dans un sous-dossier (XAMPP : /Yamoodrive).
function base(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    }
    return $b;
}

function url(string $chemin = '/', array $params = []): string
{
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
    return base() . Yamoo\Langue::prefixe() . $chemin . ($params ? '?' . http_build_query($params) : '');
}

// La page courante dans une autre langue, avec les mêmes paramètres.
function url_langue(string $code, bool $absolue = false): string
{
    $requete = $_SERVER['QUERY_STRING'] ?? '';
    $chemin = base() . Yamoo\Langue::prefixe($code) . Yamoo\Langue::$chemin . ($requete !== '' ? '?' . $requete : '');
    if (!$absolue) {
        return $chemin;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $chemin;
}

// Texte traduit. Les paramètres suivent le format des messages ICU :
// t('{n, plural, one {# jour} other {# jours}}', ['n' => 3]).
function t(string $texte, array $params = []): string
{
    $modele = Yamoo\Langue::traduit($texte);
    if (!$params) {
        return $modele;
    }
    $resultat = MessageFormatter::formatMessage(Yamoo\Langue::info('locale'), $modele, $params);
    return $resultat === false ? $modele : $resultat;
}

// Variante pour les textes qui portent leur propre balisage (<strong>) : les valeurs sont échappées.
function h(string $texte, array $params = []): string
{
    return t($texte, array_map(fn ($v) => is_string($v) ? e($v) : $v, $params));
}

// Libellé traduit d'un lieu de config/app.php : 'nom', 'court' ou 'detail'.
function lieu(string $cle, string $champ = 'nom'): string
{
    return t((string) config("lieux.$cle.$champ"));
}

function nb_jours(int $n): string
{
    return t('{n, plural, one {# jour} other {# jours}}', ['n' => $n]);
}

function url_absolue(string $chemin = '/', array $params = []): string
{
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $hote = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $hote . url($chemin, $params);
}

function asset(string $chemin): string
{
    $fichier = dirname(__DIR__) . '/assets/' . $chemin;
    $v = is_file($fichier) ? substr(base_convert((string) filemtime($fichier), 10, 36), -6) : '0';
    return base() . '/assets/' . $chemin . '?v=' . $v;
}

function fcfa(int|float $montant): string
{
    $texte = nombre(round($montant)) . "\u{00A0}FCFA";
    // En arabe, le montant reste un bloc lu de gauche à droite : « 60,000 FCFA ».
    return Yamoo\Langue::info('sens') === 'rtl' ? "\u{2066}$texte\u{2069}" : $texte;
}

function nombre(int|float $n): string
{
    static $formats = [];
    $locale = Yamoo\Langue::info('locale');
    $formats[$locale] ??= new NumberFormatter($locale, NumberFormatter::DECIMAL);
    return $formats[$locale]->format($n);
}

function maintenant(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone(config('fuseau')));
}

function date_locale(string|DateTimeInterface $date): DateTimeImmutable
{
    if ($date instanceof DateTimeInterface) {
        return DateTimeImmutable::createFromInterface($date);
    }
    return new DateTimeImmutable($date, new DateTimeZone(config('fuseau')));
}

// Date dans la langue de la page, à partir d'un squelette ICU : « dMMM » donne « 18 nov. »
// en français, « 18 Nov » en anglais, « 11月18日 » en chinois.
function date_texte(string|DateTimeInterface $date, string $squelette = 'dMMM'): string
{
    static $formats = [];
    $locale = Yamoo\Langue::info('locale');
    if (!isset($formats[$locale][$squelette])) {
        $motif = (new IntlDatePatternGenerator($locale))->getBestPattern($squelette);
        $formats[$locale][$squelette] = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, config('fuseau'), null, $motif);
    }
    return $formats[$locale][$squelette]->format(date_locale($date));
}

function heure(string|DateTimeInterface $date): string
{
    return date_locale($date)->format('H:i');
}

function periode_texte(string $debut, string $fin): string
{
    $d = date_locale($debut);
    $f = date_locale($fin);
    $squelette = $d->format('Y') === $f->format('Y') ? 'EEEdMMM' : 'yMMMEEEd';
    return date_texte($d, $squelette) . ', ' . heure($d) . ' – ' . date_texte($f, $squelette) . ', ' . heure($f);
}

// « +237677123456 » devient « +237 6 77 12 34 56 ».
function telephone_lisible(?string $numero): string
{
    $n = (string) $numero;
    if (preg_match('/^\+237(\d)(\d{2})(\d{2})(\d{2})(\d{2})$/', $n, $m)) {
        return "+237 $m[1] $m[2] $m[3] $m[4] $m[5]";
    }
    return $n;
}

function lien_whatsapp(string $message = ''): string
{
    return 'https://wa.me/' . config('contact.whatsapp') . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function champ_csrf(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf()) . '">';
}

function verifie_csrf(): void
{
    $jeton = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF'] ?? '');
    if (!is_string($jeton) || !hash_equals(csrf(), $jeton)) {
        http_response_code(419);
        vue('erreur', ['titre' => t('Page expirée'), 'message' => t('La page est restée ouverte trop longtemps. Rechargez-la puis recommencez.')]);
        exit;
    }
}

function vue(string $nom, array $donnees = [], ?string $gabarit = 'gabarit'): void
{
    extract($donnees, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . "/vues/$nom.php";
    $contenu = ob_get_clean();
    if ($gabarit === null) {
        echo $contenu;
        return;
    }
    require dirname(__DIR__) . "/vues/$gabarit.php";
}

function partiel(string $nom, array $donnees = []): void
{
    extract($donnees, EXTR_SKIP);
    require dirname(__DIR__) . "/vues/partiels/$nom.php";
}

function json(mixed $donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function redirige(string $vers, int $code = 303): never
{
    header('Location: ' . $vers, true, $code);
    exit;
}

function icone(string $nom, string $classe = 'icone'): string
{
    return '<svg class="' . e($classe) . '" aria-hidden="true" focusable="false"><use href="#i-' . e($nom) . '"></use></svg>';
}

// Image d'un véhicule détouré : AVIF puis WebP, plusieurs largeurs.
function image_vehicule(string $slug, string $alt, string $tailles, bool $prioritaire = false, string $classe = ''): string
{
    $src = fn (string $ext) => implode(', ', array_map(
        fn ($w) => asset("img/flotte/$slug-$w.$ext") . " {$w}w",
        [480, 800, 1200, 1600]
    ));
    $chargement = $prioritaire ? 'fetchpriority="high"' : 'loading="lazy" decoding="async"';
    return '<picture>'
        . '<source type="image/avif" srcset="' . $src('avif') . '" sizes="' . e($tailles) . '">'
        . '<source type="image/webp" srcset="' . $src('webp') . '" sizes="' . e($tailles) . '">'
        . '<img src="' . asset("img/flotte/$slug-800.webp") . '" alt="' . e($alt) . '" width="1600" height="1200" '
        . $chargement . ($classe ? ' class="' . e($classe) . '"' : '') . '>'
        . '</picture>';
}

function image_galerie(string $nom, string $alt, int $largeur, int $hauteur, string $tailles, string $classe = ''): string
{
    $petite = min(480, $largeur);
    $src = fn (string $ext) => asset("img/galerie/$nom-$petite.$ext") . " {$petite}w"
        . ($petite !== $largeur ? ', ' . asset("img/galerie/$nom-$largeur.$ext") . " {$largeur}w" : '');
    return '<picture>'
        . '<source type="image/avif" srcset="' . $src('avif') . '" sizes="' . e($tailles) . '">'
        . '<source type="image/webp" srcset="' . $src('webp') . '" sizes="' . e($tailles) . '">'
        . '<img src="' . asset("img/galerie/$nom-$largeur.webp") . '" alt="' . e($alt) . '" width="' . $largeur . '" height="' . $hauteur . '" loading="lazy" decoding="async"'
        . ($classe ? ' class="' . e($classe) . '"' : '') . '>'
        . '</picture>';
}
