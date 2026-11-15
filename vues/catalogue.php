<?php
/** @var Yamoo\Demande $demande */
$params = $demande->versParams();
$params['formule'] = $formuleFiltre;
?>
<section class="conteneur page-entete">
  <h1 class="page-titre"><?= e($titre) ?></h1>
  <p class="page-intro">
    <?php if ($avecDates): ?>
      <?= pluriel($libres, 'véhicule libre', 'véhicules libres') ?> sur <?= count($cartes) ?> · <?= e(config("lieux.{$demande->lieuDepart}.nom")) ?> · <?= e(periode_fr($demande->debut->format('Y-m-d H:i'), $demande->fin->format('Y-m-d H:i'))) ?>
    <?php else: ?>
      Choisissez vos dates pour voir les véhicules libres et le prix total.
    <?php endif ?>
  </p>
  <?php partiel('recherche', ['demande' => $demande, 'erreurs' => $erreursRecherche, 'formuleChoisie' => $formuleFiltre]) ?>
</section>

<section class="section section-serree conteneur" aria-label="Résultats">
  <div class="filtres" role="group" aria-label="Filtrer par catégorie" data-filtres<?= $categorie ? ' data-initial="' . e($categorie) . '"' : '' ?>>
    <a class="filtre" href="<?= e(url('/vehicules', $params)) ?>" aria-pressed="<?= $categorie ? 'false' : 'true' ?>" data-filtre="">Tous <span><?= count($cartes) ?></span></a>
    <?php foreach (config('categories') as $cle => $libelle):
        $n = count(array_filter($cartes, fn ($c) => $c['v']['categorie'] === $cle));
        if (!$n) continue; ?>
      <a class="filtre" href="<?= e(url('/vehicules', $params + ['categorie' => $cle])) ?>" aria-pressed="<?= $categorie === $cle ? 'true' : 'false' ?>" data-filtre="<?= e($cle) ?>"><?= e($libelle) ?> <span><?= $n ?></span></a>
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
      <h2>Aucun véhicule</h2>
      <p>Modifiez la formule ou les dates, puis relancez la recherche.</p>
      <a class="bouton" href="<?= url('/vehicules') ?>">Voir tous les véhicules</a>
    </div>
  <?php endif ?>
</section>
