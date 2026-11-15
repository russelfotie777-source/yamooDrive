<div class="outil-connexion">
  <h1 class="outil-titre">Connexion de l’équipe</h1>
  <?php if (!$actif): ?>
    <p class="message message-info"><?= icone('info') ?>L’espace équipe est fermé. Ajoutez ADMIN_MOT_DE_PASSE dans le fichier .env pour l’ouvrir.</p>
  <?php else: ?>
    <?php if ($erreur): ?><p class="message message-erreur" role="alert"><?= icone('info') ?><?= e($erreur) ?></p><?php endif ?>
    <form method="post" action="<?= url('/equipe/connexion') ?>" class="formulaire-court">
      <?= champ_csrf() ?>
      <div class="champ">
        <label for="mdp" class="champ-libelle">Mot de passe</label>
        <input id="mdp" name="mot_de_passe" type="password" autocomplete="current-password" required autofocus>
      </div>
      <button class="bouton bouton-plein" type="submit">Se connecter</button>
    </form>
  <?php endif ?>
</div>
