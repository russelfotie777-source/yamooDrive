<?php
$liens = [
    'vehicules' => [url('/vehicules'), 'Véhicules'],
    'services' => [url('/') . '#services', 'Services'],
    'agences' => [url('/') . '#agences', 'Agences'],
    'questions' => [url('/') . '#questions', 'Questions'],
];
$numero = config('contact.telephone_affiche');
// Le tracé du logo se joue sur la première page vue de la visite, pas à chaque clic.
$logoAnime = empty($_SESSION['logo_vu']);
$_SESSION['logo_vu'] = true;
?>
<header class="entete">
  <div class="conteneur entete-contenu">
    <a class="logo" href="<?= url('/') ?>" aria-label="Yamoo Drive, accueil">
      <?php partiel('logo', ['id' => 'logo-entete', 'anime' => $logoAnime]) ?>
    </a>
    <nav class="nav-principale" aria-label="Navigation principale">
      <?php foreach ($liens as $cle => [$href, $libelle]): ?>
        <a href="<?= e($href) ?>"<?= ($page === 'catalogue' && $cle === 'vehicules') ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
      <?php endforeach ?>
    </nav>
    <div class="entete-actions">
      <a class="bouton bouton-contour bouton-whatsapp" href="<?= e(lien_whatsapp('Bonjour Yamoo Drive, je voudrais louer un véhicule.')) ?>" rel="noopener" target="_blank">
        <?= icone('whatsapp') ?><span class="entete-numero"><?= e(substr($numero, 5)) ?></span><span class="visuellement-cache"> sur WhatsApp</span>
      </a>
      <button class="bouton-icone" type="button" data-bascule-theme aria-label="Passer en mode sombre" title="Changer de thème">
        <?= icone('lune', 'icone icone-lune') ?><?= icone('soleil', 'icone icone-soleil') ?>
      </button>
      <button class="bouton-icone menu-bouton" type="button" aria-expanded="false" aria-controls="menu-mobile" aria-label="Ouvrir le menu" data-menu>
        <?= icone('menu') ?>
      </button>
    </div>
  </div>
  <div class="menu-mobile" id="menu-mobile" hidden>
    <nav class="conteneur" aria-label="Menu">
      <?php foreach ($liens as [$href, $libelle]): ?>
        <a href="<?= e($href) ?>"><?= e($libelle) ?></a>
      <?php endforeach ?>
      <a href="tel:<?= e(config('contact.telephone')) ?>"><?= icone('telephone') ?>Appeler le <?= e($numero) ?></a>
    </nav>
  </div>
</header>
