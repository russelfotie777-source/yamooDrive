<?php
/** @var array $r */
$scripts = ['paiement.js'];
$operateurs = ['mtn' => 'MTN Mobile Money', 'orange' => 'Orange Money'];
$echec = $r['paiement_statut'] === 'echec';
$numero = preg_replace('/^\+237 /', '', telephone_lisible($r['paiement_numero']));
?>
<div class="conteneur etat-page">
  <div class="etat-carte" data-paiement-attente
       data-api="<?= e(url('/api/paiement/' . $r['code'], ['j' => $r['jeton']])) ?>">
    <?php if ($demo): ?>
      <p class="message message-info"><?= icone('info') ?>Paiement de démonstration : aucun montant n’est débité.</p>
    <?php endif ?>
    <?php if ($flash): ?>
      <p class="message message-erreur" role="alert"><?= icone('info') ?><?= e($flash) ?></p>
    <?php endif ?>

    <div class="etat-attente" data-etat="attente"<?= $echec ? ' hidden' : '' ?>>
      <span class="indicateur" aria-hidden="true"></span>
      <h1 class="etat-titre">Validez le paiement sur votre téléphone</h1>
      <p class="etat-texte">Une demande de <strong><?= fcfa($r['total']) ?></strong> a été envoyée au <strong><?= e($numero) ?></strong> (<?= e($operateurs[$r['paiement_operateur']] ?? 'Mobile Money') ?>). Acceptez-la avec votre code secret. Si rien n’apparaît, ouvrez le menu Mobile Money de votre téléphone.</p>
      <p class="etat-statut" role="status" data-statut>En attente de votre validation…</p>
    </div>

    <div class="etat-echec" data-etat="echec"<?= $echec ? '' : ' hidden' ?>>
      <h1 class="etat-titre">Le paiement n’a pas abouti</h1>
      <p class="etat-texte">La demande a été refusée ou a expiré sur le téléphone. Réessayez avec le même numéro ou un autre, ou payez à la remise des clés.</p>
      <form class="formulaire-court" method="post" action="<?= url('/paiement/' . $r['code']) ?>">
        <?= champ_csrf() ?>
        <input type="hidden" name="j" value="<?= e($r['jeton']) ?>">
        <fieldset class="segments segments-operateurs">
          <legend class="champ-libelle">Opérateur</legend>
          <?php foreach ($operateurs as $cle => $libelle): ?>
            <label><input type="radio" name="operateur" value="<?= $cle ?>"<?= $r['paiement_operateur'] === $cle ? ' checked' : '' ?>><span><span class="pastille pastille-<?= $cle ?>"></span><?= e($libelle) ?></span></label>
          <?php endforeach ?>
        </fieldset>
        <div class="champ">
          <label for="numero" class="champ-libelle">Numéro Mobile Money</label>
          <input id="numero" name="numero" type="tel" inputmode="tel" value="<?= e($numero) ?>">
        </div>
        <div class="actions">
          <button class="bouton bouton-plein" type="submit" name="choix" value="relancer">Relancer le paiement</button>
          <button class="bouton bouton-contour" type="submit" name="choix" value="remise">Payer à la remise des clés</button>
        </div>
      </form>
    </div>

    <dl class="recap recap-compact">
      <div><dt>Réservation</dt><dd class="code"><?= e($r['code']) ?></dd></div>
      <div><dt>Véhicule</dt><dd><?= e($r['v']['nom']) ?></dd></div>
      <div><dt>Dates</dt><dd><?= e(periode_fr($r['debut'], $r['fin'])) ?></dd></div>
      <?php if ($r['expire_le']): ?><div><dt>Véhicule bloqué jusqu’à</dt><dd><?= heure($r['expire_le']) ?></dd></div><?php endif ?>
    </dl>
  </div>
</div>
