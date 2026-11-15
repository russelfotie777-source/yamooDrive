<?php
/*
  Lever de rideau de l'accueil, sur la première page vue de la visite : le logo se trace sur le
  bleu nuit, le Land Cruiser entre et freine sous le logo, puis repart en tirant le rideau.
  Tout tient en CSS : sans JavaScript le rideau s'efface seul, le script sert à le passer.
*/
$src = fn (string $ext, array $largeurs) => implode(', ', array_map(
    fn ($w) => asset("img/flotte/toyota-land-cruiser-300-$w.$ext") . " {$w}w",
    $largeurs
));
?>
<div class="entree" data-entree>
  <div class="entree-rideau">
    <div class="entree-scene">
      <div class="entree-logo" aria-hidden="true">
        <?php partiel('logo', ['id' => 'logo-entree', 'anime' => true]) ?>
      </div>
    </div>
  </div>
  <div class="entree-route" aria-hidden="true">
    <div class="entree-voiture">
      <?php // Au téléphone, la voiture traverse vite : la 800 suffit et laisse la bande passante à la page. ?>
      <picture>
        <source type="image/avif" media="(min-width: 826px)" srcset="<?= $src('avif', [800, 1200, 1600]) ?>" sizes="760px">
        <source type="image/webp" media="(min-width: 826px)" srcset="<?= $src('webp', [800, 1200, 1600]) ?>" sizes="760px">
        <source type="image/avif" srcset="<?= $src('avif', [480, 800]) ?>" sizes="92vw">
        <source type="image/webp" srcset="<?= $src('webp', [480, 800]) ?>" sizes="92vw">
        <img src="<?= asset('img/flotte/toyota-land-cruiser-300-800.webp') ?>" alt="" width="800" height="600">
      </picture>
    </div>
  </div>
  <button class="entree-passer" type="button" data-entree-passer><?= e(t('Passer l’intro')) ?></button>
</div>
