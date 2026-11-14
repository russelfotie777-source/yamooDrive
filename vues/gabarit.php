<?php
/** @var string $contenu */
$titreComplet = ($page ?? '') === 'accueil' ? 'Yamoo Drive – ' . $titre : $titre . ' – Yamoo Drive';
$description ??= 'Location de véhicules avec ou sans chauffeur à Douala, Yaoundé et Bafoussam. Réservation et paiement Mobile Money en ligne.';
$scripts ??= [];
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titreComplet) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0a0f24" media="(prefers-color-scheme: dark)">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Yamoo Drive">
<meta property="og:title" content="<?= e($titreComplet) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="<?= e(url_absolue('/assets/img/accueil/t2-milnerton-1280.jpg')) ?>">
<meta property="og:locale" content="fr_CM">
<link rel="icon" href="<?= asset('img/marque/icone-32.png') ?>" sizes="32x32">
<link rel="apple-touch-icon" href="<?= asset('img/marque/icone-180.png') ?>">
<link rel="preload" href="<?= base() ?>/assets/polices/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<script src="<?= asset('js/theme.js') ?>"></script>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'AutoRental',
    'name' => 'Yamoo Drive',
    'parentOrganization' => ['@type' => 'Organization', 'name' => 'Yamoo Group'],
    'telephone' => config('contact.telephone'),
    'email' => config('contact.email'),
    'url' => url_absolue('/'),
    'logo' => url_absolue('/assets/img/marque/icone-512.png'),
    'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Logpom, carrefour Adem', 'addressLocality' => 'Douala', 'addressRegion' => 'Littoral', 'addressCountry' => 'CM'],
    'areaServed' => ['Douala', 'Yaoundé', 'Bafoussam'],
    'priceRange' => 'À partir de 30 000 FCFA par jour',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body class="page-<?= e($page ?? 'autre') ?>" data-base="<?= e(base()) ?>" data-csrf="<?= e(csrf()) ?>">
<?php partiel('icones') ?>
<a class="evitement" href="#contenu">Aller au contenu</a>
<?php partiel('entete', ['page' => $page ?? '']) ?>
<main id="contenu">
<?= $contenu ?>
</main>
<?php partiel('pied') ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= asset('js/' . $s) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
