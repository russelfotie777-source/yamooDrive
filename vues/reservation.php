<?php
/** @var array $r */
use Yamoo\Reservations;

$v = $r['v'];
$etats = [
    'confirmee' => ['succes', 'check', 'Réservation confirmée', $r['paiement_statut'] === 'paye' ? 'Paiement de ' . fcfa($r['total']) . ' reçu. Votre véhicule vous attend.' : 'L’équipe a confirmé votre réservation. Vous payez à la remise des clés.'],
    'demande' => ['info', 'horloge', 'Demande envoyée', 'Le véhicule vous est réservé. L’équipe confirme sur WhatsApp et vous payez à la remise des clés.'],
    'annulee' => ['neutre', 'fermer', 'Réservation annulée', $r['paiement_statut'] === 'paye' ? 'Votre paiement de ' . fcfa($r['total']) . ' vous sera remboursé par l’équipe.' : 'Aucun montant ne vous sera demandé.'],
    'expiree' => ['neutre', 'horloge', 'Paiement non reçu', 'Le délai de paiement est dépassé et le véhicule a été libéré. Vous pouvez refaire une réservation.'],
    'terminee' => ['neutre', 'check', 'Location terminée', 'Merci d’avoir roulé avec Yamoo Drive.'],
];
[$ton, $ic, $titreEtat, $texteEtat] = $etats[$r['statut']] ?? $etats['demande'];
$lien = url_absolue('/reservation/' . $r['code'], ['j' => $r['jeton']]);
$actif = in_array($r['statut'], ['demande', 'confirmee'], true);
$detail = $r['mission_detail'];
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
          <p class="code-libelle">Code de réservation</p>
          <p class="code code-grand"><?= e($r['code']) ?></p>
        </div>
        <button class="bouton bouton-contour" type="button" data-copier="<?= e($lien) ?>"><?= icone('copier') ?><span data-copier-libelle>Copier le lien</span></button>
      </div>
      <p class="champ-aide">Gardez ce lien : il permet de retrouver votre réservation à tout moment.</p>

      <?php if ($actif): ?>
        <div class="actions actions-pile">
          <a class="bouton bouton-large <?= $r['statut'] === 'demande' ? 'bouton-plein' : 'bouton-contour' ?>" href="<?= e(lien_whatsapp(Reservations::messageWhatsapp($r) . "\n" . $lien)) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?>Envoyer à l’équipe sur WhatsApp</a>
          <a class="bouton bouton-large bouton-contour" href="<?= e(url('/reservation/' . $r['code'] . '/agenda.ics', ['j' => $r['jeton']])) ?>"><?= icone('agenda-plus') ?>Ajouter à mon agenda</a>
        </div>

        <section class="bloc" aria-labelledby="t-apporter">
          <h2 id="t-apporter" class="bloc-titre">Le jour du départ</h2>
          <ul class="liste-coches">
            <?php if ($r['formule'] === 'sans'): ?>
              <li><?= icone('check') ?>Votre CNI ou passeport et votre permis de conduire, en original</li>
              <li><?= icone('check') ?>La caution remboursable de <?= fcfa($r['caution']) ?>, rendue au retour après l’état des lieux</li>
              <li><?= icone('check') ?>Un état des lieux est fait avec vous : carburant, kilométrage, photos du véhicule</li>
            <?php else: ?>
              <li><?= icone('check') ?>Le chauffeur vous contacte avant l’heure de prise en charge</li>
              <?php if (!empty($detail['vol'])): ?><li><?= icone('check') ?>Il vous attend à la sortie avec une pancarte à votre nom (vol <?= e($detail['vol']) ?>)</li><?php endif ?>
              <li><?= icone('check') ?>Hors de la ville de départ, le carburant est à votre charge</li>
            <?php endif ?>
            <?php if ($r['paiement_statut'] !== 'paye'): ?><li><?= icone('check') ?>Le paiement de <?= fcfa($r['total']) ?>, en espèces ou par Mobile Money</li><?php endif ?>
          </ul>
        </section>
      <?php else: ?>
        <div class="actions">
          <a class="bouton bouton-large bouton-plein" href="<?= e(url('/vehicules/' . $v['slug'])) ?>">Réserver de nouveau</a>
        </div>
      <?php endif ?>
    </div>

    <aside class="resume">
      <div class="resume-carte">
        <div class="resume-vehicule">
          <div class="resume-image"><?= image_vehicule($v['slug'], $v['nom'], '160px') ?></div>
          <div>
            <h2 class="resume-nom"><?= e($v['court']) ?></h2>
            <p><?= $r['formule'] === 'sans' ? 'Sans chauffeur' : 'Avec chauffeur' ?></p>
          </div>
        </div>
        <dl class="recap recap-compact">
          <div><dt>Départ</dt><dd><?= e(date_fr($r['debut'], 'EEE d MMM')) ?>, <?= heure($r['debut']) ?><span><?= e(config("lieux.{$r['lieu_depart']}.nom")) ?></span></dd></div>
          <div><dt>Retour</dt><dd><?= e(date_fr($r['fin'], 'EEE d MMM')) ?>, <?= heure($r['fin']) ?><span><?= e(config("lieux.{$r['lieu_retour']}.nom")) ?></span></dd></div>
          <?php if ($r['mission']): ?><div><dt>Occasion</dt><dd><?= e(config("missions.{$r['mission']}")) ?><?php if (!empty($detail['destination'])): ?><span><?= e($detail['destination']) ?></span><?php endif ?></dd></div><?php endif ?>
          <div><dt>Au nom de</dt><dd><?= e($r['client_nom']) ?></dd></div>
        </dl>
        <dl class="lignes">
          <div><dt><?= fcfa($r['prix_jour']) ?> × <?= pluriel((int) $r['jours'], 'jour', 'jours') ?></dt><dd><?= fcfa($r['montant_location']) ?></dd></div>
          <?php if ($r['frais_rapatriement']): ?><div><dt>Frais de rapatriement</dt><dd><?= fcfa($r['frais_rapatriement']) ?></dd></div><?php endif ?>
        </dl>
        <p class="total"><span>Total</span><strong><?= fcfa($r['total']) ?></strong></p>
        <p class="etiquette <?= $r['paiement_statut'] === 'paye' ? 'etiquette-succes' : 'etiquette-neutre' ?>"><?= $r['paiement_statut'] === 'paye' ? 'Payé par ' . ($r['paiement_operateur'] === 'orange' ? 'Orange Money' : 'MTN Mobile Money') : 'À payer à la remise des clés' ?></p>
      </div>
      <?php if (Reservations::annulableParClient($r)): ?>
        <form class="annulation" method="post" action="<?= url('/reservation/' . $r['code'] . '/annuler') ?>" data-confirmer="Annuler cette réservation ? Le véhicule sera libéré.">
          <?= champ_csrf() ?>
          <input type="hidden" name="j" value="<?= e($r['jeton']) ?>">
          <button class="bouton-lien" type="submit">Annuler la réservation</button>
          <p class="champ-aide">Gratuit jusqu’au <?= e(date_fr(Reservations::limiteAnnulation($r), 'd MMMM')) ?> à <?= heure(Reservations::limiteAnnulation($r)) ?>.</p>
        </form>
      <?php endif ?>
    </aside>
  </div>
</div>
