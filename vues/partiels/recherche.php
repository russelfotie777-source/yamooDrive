<?php
/** @var Yamoo\Demande $demande */
$erreurs = $erreurs ?? [];
$min = maintenant()->modify('+' . config('regles.delai_minimum_heures') . ' hours')->format('Y-m-d\TH:i');
$formuleChoisie = $formuleChoisie ?? null;
?>
<form class="recherche" action="<?= url('/vehicules') ?>" method="get" role="search" aria-label="<?= e(t('Rechercher un véhicule')) ?>" data-recherche>
  <div class="recherche-champ">
    <label for="r-lieu"><?= e(t('Prise en charge')) ?></label>
    <select id="r-lieu" name="lieu">
      <?php foreach (array_keys(config('lieux')) as $cle): ?>
        <option value="<?= e($cle) ?>"<?= $demande->lieuDepart === $cle ? ' selected' : '' ?>><?= e(lieu($cle, 'court')) ?></option>
      <?php endforeach ?>
    </select>
  </div>
  <div class="recherche-dates" data-plage data-min="<?= e($min) ?>">
    <div class="recherche-champ">
      <label for="r-debut"><?= e(t('Départ')) ?></label>
      <input id="r-debut" type="datetime-local" name="debut" min="<?= e($min) ?>" step="1800" value="<?= e($demande->debut?->format('Y-m-d\TH:i')) ?>" data-debut<?= isset($erreurs['debut']) ? ' aria-invalid="true" aria-describedby="r-erreur"' : '' ?>>
    </div>
    <div class="recherche-champ">
      <label for="r-fin"><?= e(t('Retour')) ?></label>
      <input id="r-fin" type="datetime-local" name="fin" min="<?= e($min) ?>" step="1800" value="<?= e($demande->fin?->format('Y-m-d\TH:i')) ?>" data-fin<?= isset($erreurs['fin']) ? ' aria-invalid="true" aria-describedby="r-erreur"' : '' ?>>
    </div>
  </div>
  <div class="recherche-champ">
    <label for="r-formule"><?= e(t('Formule')) ?></label>
    <select id="r-formule" name="formule">
      <option value=""><?= e(t('Avec ou sans chauffeur')) ?></option>
      <option value="sans"<?= $formuleChoisie === 'sans' ? ' selected' : '' ?>><?= e(t('Sans chauffeur')) ?></option>
      <option value="avec"<?= $formuleChoisie === 'avec' ? ' selected' : '' ?>><?= e(t('Avec chauffeur')) ?></option>
    </select>
  </div>
  <button class="recherche-bouton" type="submit"><?= icone('recherche') ?><span><?= e(t('Rechercher')) ?></span></button>
</form>
<?php if ($erreurs): ?>
  <p class="message message-erreur recherche-erreur" id="r-erreur" role="alert"><?= icone('info') ?><?= e(implode(' ', $erreurs)) ?></p>
<?php endif ?>
