<?php
/** @var string $contenu */
use Yamoo\Langue;

$titreComplet = ($page ?? '') === 'accueil' ? 'Yamoo Drive – ' . $titre : $titre . ' – Yamoo Drive';
$description ??= t('Location de véhicules avec ou sans chauffeur à Douala, Yaoundé et Bafoussam. Réservation et paiement Mobile Money en ligne.');
$scripts ??= [];
?><!doctype html>
<html lang="<?= e(Langue::info('html')) ?>" dir="<?= e(Langue::info('sens')) ?>" data-intl="<?= e(Langue::info('intl')) ?>">
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
<meta property="og:locale" content="<?= e(Langue::info('og')) ?>">
<?php foreach (Langue::LANGUES as $code => $l): ?>
<link rel="alternate" hreflang="<?= e($l['html']) ?>" href="<?= e(url_langue($code, true)) ?>">
<?php endforeach ?>
<link rel="alternate" hreflang="x-default" href="<?= e(url_langue('fr', true)) ?>">
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
    'priceRange' => t('À partir de {prix} par jour', ['prix' => fcfa(30000)]),
    'availableLanguage' => array_column(Langue::LANGUES, 'nom'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<script type="application/json" id="textes-js"><?= json_encode(Langue::textesJs(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
</head>
<body class="page-<?= e($page ?? 'autre') ?>" data-base="<?= e(base()) ?>" data-racine="<?= e(base() . Langue::prefixe()) ?>" data-csrf="<?= e(csrf()) ?>">
<?php partiel('icones') ?>
<a class="evitement" href="#contenu"><?= e(t('Aller au contenu')) ?></a>
<?php partiel('entete', ['page' => $page ?? '']) ?>
<main id="contenu">
<?= $contenu ?>
</main>
<?php partiel('pied') ?>
<?php partiel('assistant') ?>
<script src="<?= asset('js/site.js') ?>" defer></script>
<?php foreach ($scripts as $s): ?>
<script src="<?= asset('js/' . $s) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
