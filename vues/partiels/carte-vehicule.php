<?php
/** @var array $c donnée de carte construite par Pages::cartes() */
$v = $c['v'];
$tailles = $tailles ?? '(min-width: 1200px) 300px, (min-width: 900px) 30vw, (min-width: 600px) 46vw, 92vw';
?>
<article class="carte<?= $c['disponible'] ? '' : ' carte-prise' ?>" data-categorie="<?= e($v['categorie']) ?>"<?= !empty($cache) ? ' hidden' : '' ?>>
  <a class="carte-lien" href="<?= e($c['lien']) ?>">
    <div class="carte-image">
      <?= image_vehicule($v['slug'], $v['nom'], $tailles, $prioritaire ?? false) ?>
      <?php if (!empty($v['phare'])): ?><span class="etiquette etiquette-or"><?= e(t('Modèle phare')) ?></span><?php endif ?>
    </div>
    <div class="carte-texte">
      <h3 class="carte-nom"><?= e($v['court']) ?></h3>
      <p class="carte-detail"><?= e($v['type']) ?> · <?= e($v['places_texte']) ?></p>
      <p class="carte-detail"><?= e(Yamoo\Flotte::formulesTexte($v)) ?></p>
      <p class="carte-prix">
        <?php if ($c['total'] !== null): ?>
          <?= h('<strong>{prix}</strong> pour {duree}', ['prix' => fcfa($c['total']), 'duree' => nb_jours($c['jours'])]) ?>
        <?php else: ?>
          <?= h('<strong>{prix}</strong> par jour', ['prix' => fcfa($c['prix'])]) ?>
        <?php endif ?>
      </p>
      <?php if ($c['etat']): ?>
        <p class="carte-etat"><?= icone('horloge') ?><?= e($c['etat']) ?></p>
      <?php elseif ($c['total'] !== null): ?>
        <p class="carte-etat carte-etat-libre"><?= icone('check') ?><?= e(t('Libre à ces dates')) ?></p>
      <?php endif ?>
    </div>
  </a>
</article>
