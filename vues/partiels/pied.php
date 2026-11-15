<footer class="pied">
  <div class="conteneur pied-grille">
    <div class="pied-marque">
      <img src="<?= asset('img/marque/logo-sombre.png') ?>" alt="Yamoo Drive" width="148" height="48" loading="lazy">
      <p>Location de véhicules avec ou sans chauffeur au Cameroun. Yamoo Drive est la filiale mobilité de Yamoo Group, avec Yamoo Travel et Yamoo Media.</p>
    </div>
    <div>
      <h2 class="pied-titre">Contact</h2>
      <ul class="pied-liste">
        <li><a href="<?= e(lien_whatsapp()) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?>WhatsApp <?= e(config('contact.telephone_affiche')) ?></a></li>
        <li><a href="tel:<?= e(config('contact.telephone')) ?>"><?= icone('telephone') ?>Appeler</a></li>
        <li><a href="mailto:<?= e(config('contact.email')) ?>"><?= icone('message') ?><?= e(config('contact.email')) ?></a></li>
      </ul>
    </div>
    <div>
      <h2 class="pied-titre">Agences</h2>
      <ul class="pied-liste pied-liste-texte">
        <li>Douala, Logpom, carrefour Adem</li>
        <li>Yaoundé</li>
        <li>Bafoussam</li>
        <li>Aéroports de Douala et de Nsimalen</li>
      </ul>
    </div>
    <div>
      <h2 class="pied-titre">Informations</h2>
      <ul class="pied-liste">
        <li><a href="<?= url('/vehicules') ?>">Tous les véhicules</a></li>
        <li><a href="<?= url('/conditions') ?>">Conditions de location</a></li>
        <li><a href="<?= url('/credits') ?>">Crédits photos</a></li>
        <li><a href="<?= url('/equipe') ?>">Espace équipe</a></li>
      </ul>
    </div>
  </div>
  <div class="conteneur pied-bas">
    <p>© <?= maintenant()->format('Y') ?> Yamoo Drive · Yamoo Group</p>
    <p>Partenaire SKY MOTORS, distributeur Jetour et JMC</p>
  </div>
</footer>
