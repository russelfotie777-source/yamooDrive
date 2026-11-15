<button class="assistant-lanceur" type="button" aria-expanded="false" aria-controls="assistant" data-assistant-ouvrir>
  <img src="<?= asset('img/marque/y-blanc.png') ?>" alt="" width="20" height="19">
  <span><?= e(t('Une question ?')) ?></span>
</button>
<section class="assistant" id="assistant" role="dialog" aria-modal="false" aria-labelledby="assistant-titre" hidden data-assistant data-whatsapp="<?= e(lien_whatsapp()) ?>">
  <header class="assistant-entete">
    <span class="assistant-avatar" aria-hidden="true"><img src="<?= asset('img/marque/y-blanc.png') ?>" alt="" width="18" height="17"></span>
    <div>
      <h2 id="assistant-titre"><?= e(t('Assistant Yamoo Drive')) ?></h2>
      <p><?= e(t('Véhicules libres, prix, conditions, réservation')) ?></p>
    </div>
    <button class="bouton-icone" type="button" aria-label="<?= e(t('Fermer l’assistant')) ?>" data-assistant-fermer><?= icone('fermer') ?></button>
  </header>
  <div class="assistant-fil" data-assistant-fil aria-live="polite">
    <div class="assistant-accueil" data-assistant-accueil>
      <p><?= e(t('Bonjour. Dites-moi ce qu’il vous faut : je vous montre les véhicules libres et le prix exact.')) ?></p>
      <ul class="assistant-suggestions" aria-label="<?= e(t('Exemples de questions')) ?>">
        <li><button type="button" data-suggestion><?= e(t('Quels véhicules sont libres ce week-end ?')) ?></button></li>
        <li><button type="button" data-suggestion><?= e(t('Combien coûte un Prado avec chauffeur ?')) ?></button></li>
        <li><button type="button" data-suggestion><?= e(t('Quelles pièces pour louer sans chauffeur ?')) ?></button></li>
        <li><button type="button" data-suggestion><?= e(t('Un 7 places pour aller à Kribi')) ?></button></li>
      </ul>
    </div>
  </div>
  <form class="assistant-saisie" data-assistant-form>
    <label class="visuellement-cache" for="assistant-question"><?= e(t('Votre question')) ?></label>
    <textarea id="assistant-question" name="question" rows="1" maxlength="1200" placeholder="<?= e(t('Écrivez votre question…')) ?>" required></textarea>
    <button class="assistant-envoyer" type="submit" aria-label="<?= e(t('Envoyer la question')) ?>"><?= icone('envoyer') ?></button>
  </form>
  <a class="assistant-whatsapp" href="<?= e(lien_whatsapp(t('Bonjour Yamoo Drive, j’ai une question.'))) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?><?= e(t('Parler à l’équipe sur WhatsApp')) ?></a>
</section>
