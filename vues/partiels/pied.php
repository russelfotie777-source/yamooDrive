<footer class="pied">
  <div class="conteneur pied-grille">
    <div class="pied-marque">
      <?php partiel('logo', ['id' => 'logo-pied']) ?>
      <p><?= e(t('Location de véhicules avec ou sans chauffeur au Cameroun. Yamoo Drive est la filiale mobilité de Yamoo Group, avec Yamoo Travel et Yamoo Media.')) ?></p>
    </div>
    <div>
      <h2 class="pied-titre"><?= e(t('Contact')) ?></h2>
      <ul class="pied-liste">
        <li><a href="<?= e(lien_whatsapp()) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?>WhatsApp <span dir="ltr"><?= e(config('contact.telephone_affiche')) ?></span></a></li>
        <li><a href="tel:<?= e(config('contact.telephone')) ?>"><?= icone('telephone') ?><?= e(t('Appeler')) ?></a></li>
        <li><a href="mailto:<?= e(config('contact.email')) ?>"><?= icone('message') ?><?= e(config('contact.email')) ?></a></li>
      </ul>
    </div>
    <div>
      <h2 class="pied-titre"><?= e(t('Agences')) ?></h2>
      <ul class="pied-liste pied-liste-texte">
        <li><?= e(t('Douala, Logpom, carrefour Adem')) ?></li>
        <li><?= e(t('Yaoundé')) ?></li>
        <li><?= e(t('Bafoussam')) ?></li>
        <li><?= e(t('Aéroports de Douala et de Nsimalen')) ?></li>
      </ul>
    </div>
    <div>
      <h2 class="pied-titre"><?= e(t('Informations')) ?></h2>
      <ul class="pied-liste">
        <li><a href="<?= url('/vehicules') ?>"><?= e(t('Tous les véhicules')) ?></a></li>
        <li><a href="<?= url('/conditions') ?>"><?= e(t('Conditions de location')) ?></a></li>
        <li><a href="<?= url('/credits') ?>"><?= e(t('Crédits photos')) ?></a></li>
        <li><a href="<?= base() ?>/equipe"><?= e(t('Espace équipe')) ?></a></li>
      </ul>
    </div>
  </div>
  <div class="conteneur pied-bas">
    <p>© <?= maintenant()->format('Y') ?> Yamoo Drive · Yamoo Group</p>
    <p><?= e(t('Partenaire SKY MOTORS, distributeur Jetour et JMC')) ?></p>
  </div>
</footer>
