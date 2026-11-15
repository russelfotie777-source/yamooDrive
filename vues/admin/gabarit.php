<?php /** @var string $contenu */ ?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= e($titre) ?> – Équipe Yamoo Drive</title>
<link rel="icon" href="<?= asset('img/marque/icone-32.png') ?>" sizes="32x32">
<link rel="preload" href="<?= base() ?>/assets/polices/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset('css/site.css') ?>">
<script src="<?= asset('js/theme.js') ?>"></script>
</head>
<body class="outil" data-base="<?= e(base()) ?>">
<?php partiel('icones') ?>
<header class="outil-barre">
  <div class="outil-contenu outil-barre-contenu">
    <a class="outil-marque" href="<?= url('/equipe') ?>"><img src="<?= asset('img/marque/icone-32.png') ?>" alt="" width="24" height="24">Équipe Yamoo Drive</a>
    <?php if (!empty($_SESSION['equipe'])): ?>
      <nav class="outil-nav" aria-label="Espace équipe">
        <a href="<?= url('/equipe') ?>"<?= $titre === 'Réservations' ? ' aria-current="page"' : '' ?>>Réservations</a>
        <a href="<?= url('/equipe/flotte') ?>"<?= $titre === 'Flotte' ? ' aria-current="page"' : '' ?>>Flotte</a>
      </nav>
      <div class="outil-barre-fin">
        <a href="<?= url('/') ?>" target="_blank">Voir le site<?= icone('lien-externe') ?></a>
        <form method="post" action="<?= url('/equipe/deconnexion') ?>"><?= champ_csrf() ?><button class="bouton bouton-petit bouton-contour" type="submit"><?= icone('sortie') ?>Se déconnecter</button></form>
      </div>
    <?php endif ?>
  </div>
</header>
<main class="outil-contenu outil-page">
<?= $contenu ?>
</main>
<script src="<?= asset('js/site.js') ?>" defer></script>
</body>
</html>
