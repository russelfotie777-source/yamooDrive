<?php
// Point d'entrée unique. Sous Apache, .htaccess y renvoie toutes les adresses ;
// en développement : php -S 127.0.0.1:8080 index.php

// Le serveur de développement ne sert lui-même que les fichiers de assets/ : sans ce
// filtre, il enverrait .env, .git ou la base SQLite à qui les demande.
if (PHP_SAPI === 'cli-server') {
    $public = realpath($_SERVER['DOCUMENT_ROOT'] . '/assets') . '/';
    $fichier = realpath($_SERVER['DOCUMENT_ROOT'] . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    if ($fichier && str_starts_with($fichier, $public) && is_file($fichier)) {
        return false;
    }
    // Pour une adresse qui finit par une extension (agenda.ics, /en/robots.txt), ce serveur
    // met l'adresse demandée dans SCRIPT_NAME : base() y verrait le sous-dossier du site et
    // la route ne serait plus reconnue. Ici, le site est toujours servi depuis la racine.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require __DIR__ . '/vendor/autoload.php';

use Yamoo\Admin;
use Yamoo\Langue;
use Yamoo\Pages;

date_default_timezone_set(config('fuseau'));
ini_set('display_errors', env('APP_DEBUG') ? '1' : '0');

// En HTTPS, même quand c'est un proxy qui chiffre, le cookie de session (celui de l'espace
// équipe compris) ne repart jamais en clair.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => en_https()]);
session_name('yamoo');
session_start();

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; connect-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// Aucun formulaire du site n'envoie de liste hors des pièces jointes, qui passent par
// $_FILES. Une adresse bricolée comme ?j[]=1 donnerait un tableau aux contrôleurs, qui
// attendent du texte, et la page tomberait en erreur 500 : la valeur compte pour absente.
$_GET = array_filter($_GET, 'is_string');
$_POST = array_filter($_POST, 'is_string');

$methode = $_SERVER['REQUEST_METHOD'];
$chemin = '/' . trim(substr(rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)), strlen(base())), '/');
// Le français vit à la racine : /fr/… renvoie vers la même page sans préfixe.
if (preg_match('#^/fr(/.*)?$#', $chemin, $m)) {
    redirige(base() . ($m[1] ?? '/') . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), 301);
}
$chemin = Langue::depuisChemin($chemin);

$routes = [
    ['GET', '#^/$#', [Pages::class, 'accueil']],
    ['GET', '#^/vehicules$#', [Pages::class, 'catalogue']],
    ['GET', '#^/vehicules/([a-z0-9-]+)$#', [Pages::class, 'vehicule']],
    ['GET', '#^/reserver/([a-z0-9-]+)$#', [Pages::class, 'reserver']],
    ['POST', '#^/reserver/([a-z0-9-]+)$#', [Pages::class, 'enregistrer']],
    ['GET', '#^/paiement/(YD-[A-Z0-9]{6})$#', [Pages::class, 'paiement']],
    ['POST', '#^/paiement/(YD-[A-Z0-9]{6})$#', [Pages::class, 'relancerPaiement']],
    ['GET', '#^/reservation/(YD-[A-Z0-9]{6})$#', [Pages::class, 'reservation']],
    ['GET', '#^/reservation/(YD-[A-Z0-9]{6})/agenda\.ics$#', [Pages::class, 'agenda']],
    ['POST', '#^/reservation/(YD-[A-Z0-9]{6})/annuler$#', [Pages::class, 'annuler']],
    ['GET', '#^/conditions$#', [Pages::class, 'conditions']],
    ['GET', '#^/credits$#', [Pages::class, 'credits']],
    ['GET', '#^/robots\.txt$#', [Pages::class, 'robots']],
    ['GET', '#^/sitemap\.xml$#', [Pages::class, 'planDuSite']],
    ['POST', '#^/api/assistant$#', [Pages::class, 'apiAssistant']],
    ['GET', '#^/api/devis$#', [Pages::class, 'apiDevis']],
    ['GET', '#^/api/paiement/(YD-[A-Z0-9]{6})$#', [Pages::class, 'apiPaiement']],
    ['POST', '#^/api/notchpay$#', [Pages::class, 'webhookNotchPay']],
    ['GET', '#^/equipe$#', [Admin::class, 'tableau']],
    ['POST', '#^/equipe/connexion$#', [Admin::class, 'connexion']],
    ['POST', '#^/equipe/deconnexion$#', [Admin::class, 'deconnexion']],
    ['GET', '#^/equipe/reservations/(YD-[A-Z0-9]{6})$#', [Admin::class, 'reservation']],
    ['POST', '#^/equipe/reservations/(YD-[A-Z0-9]{6})$#', [Admin::class, 'action']],
    ['GET', '#^/equipe/pieces/(YD-[A-Z0-9]{6})/([a-z0-9.-]+)$#', [Admin::class, 'piece']],
    ['GET', '#^/equipe/flotte$#', [Admin::class, 'flotte']],
    ['POST', '#^/equipe/flotte$#', [Admin::class, 'bloquer']],
];

$trouvee = false;
foreach ($routes as [$verbe, $motif, $action]) {
    if (preg_match($motif, $chemin, $m)) {
        $trouvee = true;
        if ($verbe === $methode || ($verbe === 'GET' && $methode === 'HEAD')) {
            array_shift($m);
            $action(...$m);
            exit;
        }
    }
}

http_response_code($trouvee ? 405 : 404);
vue('erreur', [
    'titre' => t('Page introuvable'),
    'message' => t('Cette adresse ne mène nulle part. Revenez à l’accueil pour choisir un véhicule.'),
]);
