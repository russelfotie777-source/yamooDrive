<?php
/** @var array $r */
use Yamoo\Reservations;

$v = $r['v'];
$montant = ['montant' => fcfa($r['total'])];
$etats = [
    'confirmee' => ['succes', 'check', t('Réservation confirmée'), $r['paiement_statut'] === 'paye' ? t('Paiement de {montant} reçu. Votre véhicule vous attend.', $montant) : t('L’équipe a confirmé votre réservation. Vous payez à la remise des clés.')],
    'demande' => ['info', 'horloge', t('Demande envoyée'), t('Le véhicule vous est réservé. L’équipe confirme sur WhatsApp et vous payez à la remise des clés.')],
    'annulee' => ['neutre', 'fermer', t('Réservation annulée'), $r['paiement_statut'] === 'paye' ? t('Votre paiement de {montant} vous sera remboursé par l’équipe.', $montant) : t('Aucun montant ne vous sera demandé.')],
    'expiree' => $r['paiement_statut'] === 'paye'
        ? ['neutre', 'horloge', t('Paiement arrivé trop tard'), t('Un autre client a réservé le véhicule avant l’arrivée de votre paiement de {montant}. L’équipe vous le rembourse.', $montant)]
        : ['neutre', 'horloge', t('Paiement non reçu'), t('Le délai de paiement est dépassé et le véhicule a été libéré. Vous pouvez refaire une réservation.')],
    'terminee' => ['neutre', 'check', t('Location terminée'), t('Merci d’avoir roulé avec Yamoo Drive.')],
];
[$ton, $ic, $titreEtat, $texteEtat] = $etats[$r['statut']] ?? $etats['demande'];
$lien = url_absolue('/reservation/' . $r['code'], ['j' => $r['jeton']]);
$actif = in_array($r['statut'], ['demande', 'confirmee'], true);
$detail = $r['mission_detail'];
$limite = Reservations::limiteAnnulation($r);
// Une réservation à payer à la remise des clés n'a pas d'opérateur : quand l'équipe note
// le paiement, c'est elle qui l'a reçu.
$paiement = match (true) {
    $r['paiement_statut'] !== 'paye' => t('À payer à la remise des clés'),
    $r['paiement_mode'] === 'remise' => t('Paiement reçu par l’équipe'),
    default => t('Payé par {operateur}', ['operateur' => $r['paiement_operateur'] === 'orange' ? 'Orange Money' : 'MTN Mobile Money']),
};
?>
<div class="conteneur confirmation">
  <?php if ($flash): ?><p class="message message-info" role="status"><?= icone('info') ?><?= e($flash) ?></p><?php endif ?>

  <header class="confirmation-entete<?= $r['statut'] === 'confirmee' ? ' signature-confirmation' : '' ?>">
    <span class="etat-pastille etat-<?= $ton ?>" aria-hidden="true"><?= icone($ic) ?></span>
    <div>
      <h1 class="page-titre"><?= e($titreEtat) ?></h1>
      <p class="page-intro"><?= e($texteEtat) ?></p>
    </div>
  </header>

  <div class="confirmation-grille">
    <div>
      <div class="code-bloc">
        <div>
          <p class="code-libelle"><?= e(t('Code de réservation')) ?></p>
          <p class="code code-grand" dir="ltr"><?= e($r['code']) ?></p>
        </div>
        <button class="bouton bouton-contour" type="button" data-copier="<?= e($lien) ?>"><?= icone('copier') ?><span data-copier-libelle><?= e(t('Copier le lien')) ?></span></button>
      </div>
      <p class="champ-aide"><?= e(t('Gardez ce lien : il permet de retrouver votre réservation à tout moment.')) ?></p>

      <?php if ($actif): ?>
        <div class="actions actions-pile">
          <a class="bouton bouton-large <?= $r['statut'] === 'demande' ? 'bouton-plein' : 'bouton-contour' ?>" href="<?= e(lien_whatsapp(Reservations::messageWhatsapp($r) . "\n" . $lien)) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?><?= e(t('Envoyer à l’équipe sur WhatsApp')) ?></a>
          <a class="bouton bouton-large bouton-contour" href="<?= e(url('/reservation/' . $r['code'] . '/agenda.ics', ['j' => $r['jeton']])) ?>"><?= icone('agenda-plus') ?><?= e(t('Ajouter à mon agenda')) ?></a>
        </div>

        <section class="bloc" aria-labelledby="t-apporter">
          <h2 id="t-apporter" class="bloc-titre"><?= e(t('Le jour du départ')) ?></h2>
          <ul class="liste-coches">
            <?php if ($r['formule'] === 'sans'): ?>
              <li><?= icone('check') ?><?= e(t('Votre CNI ou passeport et votre permis de conduire, en original')) ?></li>
              <li><?= icone('check') ?><?= e(t('La caution remboursable de {montant}, rendue au retour après l’état des lieux', ['montant' => fcfa($r['caution'])])) ?></li>
              <li><?= icone('check') ?><?= e(t('Un état des lieux est fait avec vous : carburant, kilométrage, photos du véhicule')) ?></li>
            <?php else: ?>
              <li><?= icone('check') ?><?= e(t('Le chauffeur vous contacte avant l’heure de prise en charge')) ?></li>
              <?php if (!empty($detail['vol'])): ?><li><?= icone('check') ?><?= e(t('Il vous attend à la sortie avec une pancarte à votre nom (vol {vol})', ['vol' => $detail['vol']])) ?></li><?php endif ?>
              <li><?= icone('check') ?><?= e(t('Hors de la ville de départ, le carburant est à votre charge')) ?></li>
            <?php endif ?>
            <?php if ($r['paiement_statut'] !== 'paye'): ?><li><?= icone('check') ?><?= e(t('Le paiement de {montant}, en espèces ou par Mobile Money', $montant)) ?></li><?php endif ?>
          </ul>
        </section>
      <?php else: ?>
        <div class="actions">
          <a class="bouton bouton-large bouton-plein" href="<?= e(url('/vehicules/' . $v['slug'])) ?>"><?= e(t('Réserver de nouveau')) ?></a>
        </div>
      <?php endif ?>
    </div>

    <aside class="resume">
      <div class="resume-carte">
        <div class="resume-vehicule">
          <div class="resume-image"><?= image_vehicule($v['slug'], $v['nom'], '160px') ?></div>
          <div>
            <h2 class="resume-nom"><?= e($v['court']) ?></h2>
            <p><?= e($r['formule'] === 'sans' ? t('Sans chauffeur') : t('Avec chauffeur')) ?></p>
          </div>
        </div>
        <dl class="recap recap-compact">
          <div><dt><?= e(t('Départ')) ?></dt><dd><?= e(date_texte($r['debut'], 'EEEdMMM')) ?>, <?= heure($r['debut']) ?><span><?= e(lieu($r['lieu_depart'])) ?></span></dd></div>
          <div><dt><?= e(t('Retour')) ?></dt><dd><?= e(date_texte($r['fin'], 'EEEdMMM')) ?>, <?= heure($r['fin']) ?><span><?= e(lieu($r['lieu_retour'])) ?></span></dd></div>
          <?php if ($r['mission']): ?><div><dt><?= e(t('Occasion')) ?></dt><dd><?= e(t(config("missions.{$r['mission']}"))) ?><?php if (!empty($detail['destination'])): ?><span><?= e($detail['destination']) ?></span><?php endif ?></dd></div><?php endif ?>
          <div><dt><?= e(t('Au nom de')) ?></dt><dd><?= e($r['client_nom']) ?></dd></div>
        </dl>
        <dl class="lignes">
          <div><dt><?= fcfa($r['prix_jour']) ?> × <?= e(nb_jours((int) $r['jours'])) ?></dt><dd><?= fcfa($r['montant_location']) ?></dd></div>
          <?php if ($r['frais_rapatriement']): ?><div><dt><?= e(t('Frais de rapatriement')) ?></dt><dd><?= fcfa($r['frais_rapatriement']) ?></dd></div><?php endif ?>
        </dl>
        <p class="total"><span><?= e(t('Total')) ?></span><strong><?= fcfa($r['total']) ?></strong></p>
        <p class="etiquette <?= $r['paiement_statut'] === 'paye' ? 'etiquette-succes' : 'etiquette-neutre' ?>"><?= e($paiement) ?></p>
      </div>
      <?php if (Reservations::annulableParClient($r)): ?>
        <form class="annulation" method="post" action="<?= url('/reservation/' . $r['code'] . '/annuler') ?>" data-confirmer="<?= e(t('Annuler cette réservation ? Le véhicule sera libéré.')) ?>">
          <?= champ_csrf() ?>
          <input type="hidden" name="j" value="<?= e($r['jeton']) ?>">
          <button class="bouton-lien" type="submit"><?= e(t('Annuler la réservation')) ?></button>
          <p class="champ-aide"><?= e(t('Gratuit jusqu’au {date} à {heure}.', ['date' => date_texte($limite, 'dMMMM'), 'heure' => heure($limite)])) ?></p>
        </form>
      <?php endif ?>
    </aside>
  </div>
</div>
