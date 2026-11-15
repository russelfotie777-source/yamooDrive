<div class="conteneur etat-page">
  <div class="etat-carte etat-centre">
    <h1 class="etat-titre"><?= e($titre) ?></h1>
    <p class="etat-texte"><?= e($message) ?></p>
    <div class="actions">
      <a class="bouton bouton-plein" href="<?= url('/') ?>"><?= e(t('Revenir à l’accueil')) ?></a>
      <a class="bouton bouton-contour" href="<?= e(lien_whatsapp()) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?><?= e(t('Écrire sur WhatsApp')) ?></a>
    </div>
  </div>
</div>
