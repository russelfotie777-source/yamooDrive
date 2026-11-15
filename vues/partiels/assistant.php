<button class="assistant-lanceur" type="button" aria-expanded="false" aria-controls="assistant" data-assistant-ouvrir>
  <img src="<?= asset('img/marque/y-blanc.png') ?>" alt="" width="20" height="19">
  <span>Une question ?</span>
</button>
<section class="assistant" id="assistant" role="dialog" aria-modal="false" aria-labelledby="assistant-titre" hidden data-assistant data-whatsapp="<?= e(lien_whatsapp()) ?>">
  <header class="assistant-entete">
    <span class="assistant-avatar" aria-hidden="true"><img src="<?= asset('img/marque/y-blanc.png') ?>" alt="" width="18" height="17"></span>
    <div>
      <h2 id="assistant-titre">Assistant Yamoo Drive</h2>
      <p>Véhicules libres, prix, conditions, réservation</p>
    </div>
    <button class="bouton-icone" type="button" aria-label="Fermer l’assistant" data-assistant-fermer><?= icone('fermer') ?></button>
  </header>
  <div class="assistant-fil" data-assistant-fil aria-live="polite">
    <div class="assistant-accueil" data-assistant-accueil>
      <p>Bonjour. Dites-moi ce qu’il vous faut : je vous montre les véhicules libres et le prix exact.</p>
      <ul class="assistant-suggestions" aria-label="Exemples de questions">
        <li><button type="button" data-suggestion>Quels véhicules sont libres ce week-end ?</button></li>
        <li><button type="button" data-suggestion>Combien coûte un Prado avec chauffeur ?</button></li>
        <li><button type="button" data-suggestion>Quelles pièces pour louer sans chauffeur ?</button></li>
        <li><button type="button" data-suggestion>Un 7 places pour aller à Kribi</button></li>
      </ul>
    </div>
  </div>
  <form class="assistant-saisie" data-assistant-form>
    <label class="visuellement-cache" for="assistant-question">Votre question</label>
    <textarea id="assistant-question" name="question" rows="1" maxlength="1200" placeholder="Écrivez votre question…" required></textarea>
    <button class="assistant-envoyer" type="submit" aria-label="Envoyer la question"><?= icone('envoyer') ?></button>
  </form>
  <a class="assistant-whatsapp" href="<?= e(lien_whatsapp('Bonjour Yamoo Drive, j’ai une question.')) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?>Parler à l’équipe sur WhatsApp</a>
</section>
