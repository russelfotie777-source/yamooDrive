<?php
use Yamoo\Conducteur;
use Yamoo\Reservations;

$detail = $r['mission_detail'];
$conducteur = $r['conducteur'];
$age = !empty($conducteur['naissance']) ? date_locale($conducteur['naissance'])->diff(date_locale($r['debut']))->y : null;
$anciennete = !empty($conducteur['permis']) ? date_locale($conducteur['permis'])->diff(date_locale($r['debut']))->y : null;
?>
<a class="retour" href="<?= url('/equipe') ?>"><?= icone('fleche-gauche') ?>Réservations</a>
<div class="outil-entete">
  <h1 class="outil-titre"><span class="code"><?= e($r['code']) ?></span> <span class="statut statut-<?= e($r['statut']) ?>"><?= e(Reservations::STATUTS[$r['statut']]) ?></span></h1>
  <div class="actions">
    <?php if (in_array($r['statut'], ['demande', 'attente_paiement'], true)): ?>
      <form method="post"><?= champ_csrf() ?><button class="bouton bouton-petit bouton-plein" name="action" value="confirmer">Confirmer</button></form>
    <?php endif ?>
    <?php if ($r['paiement_statut'] !== 'paye' && !in_array($r['statut'], ['annulee', 'expiree'], true)): ?>
      <form method="post"><?= champ_csrf() ?><button class="bouton bouton-petit bouton-contour" name="action" value="payee">Marquer comme payée</button></form>
    <?php endif ?>
    <?php if (in_array($r['statut'], ['demande', 'confirmee', 'attente_paiement'], true)): ?>
      <form method="post" data-confirmer="Annuler la réservation <?= e($r['code']) ?> ?"><?= champ_csrf() ?><button class="bouton bouton-petit bouton-danger" name="action" value="annuler">Annuler</button></form>
    <?php endif ?>
  </div>
</div>
<?php if ($flash): ?><p class="message message-succes" role="status"><?= icone('check') ?><?= e($flash) ?></p><?php endif ?>

<div class="outil-grille">
  <div class="outil-principal">
    <section class="panneau">
      <h2 class="panneau-titre">Location</h2>
      <dl class="definitions">
        <div><dt>Véhicule</dt><dd><?= e($r['v']['nom'] ?? $r['vehicule']) ?>, <?= $r['formule'] === 'sans' ? 'sans chauffeur' : 'avec chauffeur' ?></dd></div>
        <div><dt>Départ</dt><dd><?= e(date_fr($r['debut'], 'EEEE d MMMM y')) ?> à <?= heure($r['debut']) ?> · <?= e(config("lieux.{$r['lieu_depart']}.nom")) ?></dd></div>
        <div><dt>Retour</dt><dd><?= e(date_fr($r['fin'], 'EEEE d MMMM y')) ?> à <?= heure($r['fin']) ?> · <?= e(config("lieux.{$r['lieu_retour']}.nom")) ?></dd></div>
        <div><dt>Durée</dt><dd><?= pluriel((int) $r['jours'], 'jour', 'jours') ?> à <?= fcfa($r['prix_jour']) ?></dd></div>
        <?php if ($r['frais_rapatriement']): ?><div><dt>Rapatriement</dt><dd><?= fcfa($r['frais_rapatriement']) ?></dd></div><?php endif ?>
        <div><dt>Total</dt><dd><strong><?= fcfa($r['total']) ?></strong></dd></div>
        <?php if ($r['caution']): ?><div><dt>Caution</dt><dd><?= fcfa($r['caution']) ?> à encaisser à la remise des clés</dd></div><?php endif ?>
        <div><dt>Paiement</dt><dd><?= $r['paiement_mode'] === 'mobile' ? e(($r['paiement_operateur'] === 'orange' ? 'Orange Money' : 'MTN Mobile Money') . ' ' . telephone_lisible($r['paiement_numero'])) : 'À la remise des clés' ?> · <?= e(['aucun' => 'non payé', 'en_cours' => 'en cours', 'paye' => 'payé', 'echec' => 'échoué'][$r['paiement_statut']] ?? $r['paiement_statut']) ?><?php if ($r['paiement_reference']): ?> · <span class="code"><?= e($r['paiement_reference']) ?></span><?php endif ?></dd></div>
      </dl>
    </section>

    <?php if ($r['mission']): ?>
      <section class="panneau">
        <h2 class="panneau-titre">Trajet</h2>
        <dl class="definitions">
          <div><dt>Occasion</dt><dd><?= e(config("missions.{$r['mission']}")) ?></dd></div>
          <?php if (!empty($detail['vol'])): ?><div><dt>Vol</dt><dd><?= e($detail['vol']) ?>, atterrissage à <?= e($detail['atterrissage'] ?? '') ?></dd></div><?php endif ?>
          <?php if (!empty($detail['destination'])): ?><div><dt>Destination</dt><dd><?= e($detail['destination']) ?></dd></div><?php endif ?>
          <?php if (!empty($detail['adresse'])): ?><div><dt>Adresse</dt><dd><?= e($detail['adresse']) ?></dd></div><?php endif ?>
        </dl>
      </section>
    <?php endif ?>

    <?php if ($r['formule'] === 'sans'): ?>
      <section class="panneau">
        <h2 class="panneau-titre">Conducteur</h2>
        <dl class="definitions">
          <div><dt>Né le</dt><dd><?= !empty($conducteur['naissance']) ? e(date_fr($conducteur['naissance'], 'd MMMM y')) . " · $age ans au départ" : '–' ?></dd></div>
          <div><dt>Permis depuis</dt><dd><?= !empty($conducteur['permis']) ? e(date_fr($conducteur['permis'], 'd MMMM y')) . " · $anciennete ans au départ" : '–' ?></dd></div>
        </dl>
        <ul class="pieces-liste">
          <?php foreach (Conducteur::PIECES as $cle => $libelle): ?>
            <li>
              <span><?= e($libelle) ?></span>
              <?php foreach ($conducteur['pieces'][$cle] ?? [] as $i => $fichier): ?>
                <a href="<?= url('/equipe/pieces/' . $r['code'] . '/' . $fichier) ?>" target="_blank">Fichier <?= $i + 1 ?></a>
              <?php endforeach ?>
              <?php if (empty($conducteur['pieces'][$cle])): ?><span class="discret">non reçu</span><?php endif ?>
            </li>
          <?php endforeach ?>
        </ul>
      </section>
    <?php endif ?>

    <?php if ($r['statut'] === 'confirmee'): ?>
      <section class="panneau">
        <h2 class="panneau-titre">Retour du véhicule</h2>
        <form method="post" class="formulaire-court">
          <?= champ_csrf() ?>
          <div class="champ">
            <label for="releve" class="champ-libelle">État des lieux au retour</label>
            <textarea id="releve" name="releve" rows="3" placeholder="Kilométrage, niveau de carburant, remarques, caution rendue"></textarea>
          </div>
          <button class="bouton bouton-petit bouton-plein" name="action" value="terminer">Terminer la location</button>
        </form>
      </section>
    <?php endif ?>
  </div>

  <aside class="outil-cote">
    <section class="panneau">
      <h2 class="panneau-titre">Client</h2>
      <p><strong><?= e($r['client_nom']) ?></strong></p>
      <p><a href="tel:<?= e($r['client_telephone']) ?>"><?= e(telephone_lisible($r['client_telephone'])) ?></a></p>
      <?php if ($r['client_email']): ?><p><a href="mailto:<?= e($r['client_email']) ?>"><?= e($r['client_email']) ?></a></p><?php endif ?>
      <a class="bouton bouton-petit bouton-contour" href="https://wa.me/<?= e(ltrim($r['client_telephone'], '+')) ?>?text=<?= rawurlencode('Bonjour ' . $r['client_nom'] . ', Yamoo Drive au sujet de votre réservation ' . $r['code'] . '.') ?>" target="_blank" rel="noopener"><?= icone('whatsapp') ?>Écrire au client</a>
    </section>
    <section class="panneau">
      <h2 class="panneau-titre">Journal</h2>
      <form method="post" class="note-form">
        <?= champ_csrf() ?>
        <label class="visuellement-cache" for="note">Ajouter une note</label>
        <textarea id="note" name="note" rows="2" placeholder="Ajouter une note interne"></textarea>
        <button class="bouton bouton-petit bouton-contour" name="action" value="note">Ajouter la note</button>
      </form>
      <ol class="journal">
        <?php foreach ($journal as $j): ?>
          <li><p><?= e($j['detail']) ?></p><time datetime="<?= e($j['cree_le']) ?>" title="<?= e($j['cree_le']) ?>"><?= e(date_fr($j['cree_le'], 'd MMM')) ?>, <?= heure($j['cree_le']) ?></time></li>
        <?php endforeach ?>
      </ol>
    </section>
  </aside>
</div>
