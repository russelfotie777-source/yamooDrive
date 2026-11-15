<?php
/** @var Yamoo\Demande $demande */
$params = $demande->versParams();
$params['formule'] = $formuleFiltre;
?>
<section class="conteneur page-entete">
  <h1 class="page-titre"><?= e($titre) ?></h1>
  <p class="page-intro">
    <?php if ($avecDates): ?>
      <?= e(t('{libres, plural, one {# véhicule libre} other {# véhicules libres}} sur {total}', ['libres' => $libres, 'total' => count($cartes)])) ?> · <?= e(lieu($demande->lieuDepart)) ?> · <?= e(periode_texte($demande->debut->format('Y-m-d H:i'), $demande->fin->format('Y-m-d H:i'))) ?>
    <?php else: ?>
      <?= e(t('Choisissez vos dates pour voir les véhicules libres et le prix total.')) ?>
    <?php endif ?>
  </p>
  <?php partiel('recherche', ['demande' => $demande, 'erreurs' => $erreursRecherche, 'formuleChoisie' => $formuleFiltre]) ?>
</section>

<section class="section section-serree conteneur" aria-label="<?= e(t('Résultats')) ?>">
  <div class="filtres" role="group" aria-label="<?= e(t('Filtrer par catégorie')) ?>" data-filtres<?= $categorie ? ' data-initial="' . e($categorie) . '"' : '' ?>>
    <a class="filtre" href="<?= e(url('/vehicules', $params)) ?>" aria-pressed="<?= $categorie ? 'false' : 'true' ?>" data-filtre=""><?= e(t('Tous')) ?> <span><?= nombre(count($cartes)) ?></span></a>
    <?php foreach (config('categories') as $cle => $libelle):
        $n = count(array_filter($cartes, fn ($c) => $c['v']['categorie'] === $cle));
        if (!$n) continue; ?>
      <a class="filtre" href="<?= e(url('/vehicules', $params + ['categorie' => $cle])) ?>" aria-pressed="<?= $categorie === $cle ? 'true' : 'false' ?>" data-filtre="<?= e($cle) ?>"><?= e(t($libelle)) ?> <span><?= nombre($n) ?></span></a>
    <?php endforeach ?>
  </div>
  <?php if ($cartes): ?>
    <div class="grille-vehicules" data-grille>
      <?php foreach ($cartes as $c) {
          partiel('carte-vehicule', ['c' => $c, 'cache' => $categorie && $c['v']['categorie'] !== $categorie]);
      } ?>
    </div>
  <?php else: ?>
    <div class="vide">
      <h2><?= e(t('Aucun véhicule')) ?></h2>
      <p><?= e(t('Modifiez la formule ou les dates, puis relancez la recherche.')) ?></p>
      <a class="bouton" href="<?= url('/vehicules') ?>"><?= e(t('Voir tous les véhicules')) ?></a>
    </div>
  <?php endif ?>
</section>
