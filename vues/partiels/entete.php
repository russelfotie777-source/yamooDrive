<?php
use Yamoo\Langue;

$liens = [
    'vehicules' => [url('/vehicules'), t('Véhicules')],
    'services' => [url('/') . '#services', t('Services')],
    'agences' => [url('/') . '#agences', t('Agences')],
    'questions' => [url('/') . '#questions', t('Questions')],
];
$numero = config('contact.telephone_affiche');
// Le tracé du logo se joue sur la première page vue de la visite, pas à chaque clic.
// Sur l'accueil, le lever de rideau le joue déjà en grand.
$logoAnime = empty($_SESSION['logo_vu']) && empty($entree);
$_SESSION['logo_vu'] = true;
?>
<header class="entete">
  <div class="conteneur entete-contenu">
    <a class="logo" href="<?= url('/') ?>" aria-label="<?= e(t('Yamoo Drive, accueil')) ?>">
      <?php partiel('logo', ['id' => 'logo-entete', 'anime' => $logoAnime]) ?>
    </a>
    <nav class="nav-principale" aria-label="<?= e(t('Navigation principale')) ?>">
      <?php foreach ($liens as $cle => [$href, $libelle]): ?>
        <a href="<?= e($href) ?>"<?= ($page === 'catalogue' && $cle === 'vehicules') ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
      <?php endforeach ?>
    </nav>
    <div class="entete-actions">
      <a class="bouton bouton-contour bouton-whatsapp" href="<?= e(lien_whatsapp(t('Bonjour Yamoo Drive, je voudrais louer un véhicule.'))) ?>" rel="noopener" target="_blank">
        <?= icone('whatsapp') ?><span class="entete-numero" dir="ltr"><?= e(substr($numero, 5)) ?></span><span class="visuellement-cache"> <?= e(t('sur WhatsApp')) ?></span>
      </a>
      <div class="langues" data-langues>
        <button class="bouton-langue" type="button" aria-haspopup="true" aria-expanded="false" aria-controls="menu-langues" aria-label="<?= e(t('Langue : {nom}', ['nom' => Langue::info('nom')])) ?>" data-langues-bouton>
          <?= icone('langue') ?><span><?= e(strtoupper(Langue::$code)) ?></span>
        </button>
        <ul class="menu-langues" id="menu-langues" role="menu" data-langues-menu>
          <?php foreach (Langue::LANGUES as $code => $l): ?>
            <li role="none"><a role="menuitem" href="<?= e(url_langue($code)) ?>" hreflang="<?= e($l['html']) ?>" lang="<?= e($l['html']) ?>"<?= $code === Langue::$code ? ' aria-current="true"' : '' ?>><?= e($l['nom']) ?><?= $code === Langue::$code ? icone('check') : '' ?></a></li>
          <?php endforeach ?>
        </ul>
      </div>
      <button class="bouton-icone" type="button" data-bascule-theme aria-label="<?= e(t('Passer en mode sombre')) ?>" title="<?= e(t('Changer de thème')) ?>">
        <?= icone('lune', 'icone icone-lune') ?><?= icone('soleil', 'icone icone-soleil') ?>
      </button>
      <button class="bouton-icone menu-bouton" type="button" aria-expanded="false" aria-controls="menu-mobile" aria-label="<?= e(t('Ouvrir le menu')) ?>" data-menu>
        <?= icone('menu') ?>
      </button>
    </div>
  </div>
  <div class="menu-mobile" id="menu-mobile" hidden>
    <nav class="conteneur" aria-label="<?= e(t('Menu')) ?>">
      <?php foreach ($liens as [$href, $libelle]): ?>
        <a href="<?= e($href) ?>"><?= e($libelle) ?></a>
      <?php endforeach ?>
      <a href="tel:<?= e(config('contact.telephone')) ?>"><?= icone('telephone') ?><?= e(t('Appeler le {numero}', ['numero' => $numero])) ?></a>
    </nav>
  </div>
</header>
