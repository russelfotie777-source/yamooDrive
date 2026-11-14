<?php
/** @var array $v @var Yamoo\Demande $demande @var array $devis @var array $erreurs @var array $ancien */
use Yamoo\Conducteur;

$scripts = ['reserver.js'];
$a = fn (string $cle, string $defaut = '') => e(is_string($ancien[$cle] ?? null) ? $ancien[$cle] : $defaut);
$err = function (string $cle) use ($erreurs): string {
    return isset($erreurs[$cle]) ? '<p class="champ-erreur" id="err-' . e($cle) . '">' . icone('info') . e($erreurs[$cle]) . '</p>' : '';
};
$invalide = fn (string $cle) => isset($erreurs[$cle]) ? ' aria-invalid="true" aria-describedby="err-' . e($cle) . '"' : '';
$avec = $demande->formule === 'avec';
$mission = $ancien['mission'] ?? 'ville';
$paiement = $ancien['paiement'] ?? 'mobile';
$operateur = $ancien['operateur'] ?? '';
$r = config('regles');
$lieuDepart = config("lieux.{$demande->lieuDepart}");
$lieuRetour = config("lieux.{$demande->lieuRetour}");
?>
<div class="conteneur reserver">
  <a class="retour" href="<?= e(url('/vehicules/' . $v['slug'], $demande->versParams())) ?>"><?= icone('fleche-gauche') ?>Retour au véhicule</a>
  <h1 class="page-titre">Réserver le <?= e($v['court']) ?></h1>

  <div class="reserver-grille">
    <form class="formulaire" method="post" action="<?= url('/reserver/' . $v['slug']) ?>" enctype="multipart/form-data" novalidate data-formulaire
          data-total="<?= e(fcfa($devis['total'])) ?>" data-naissance-max="<?= e($demande->debut->modify('-' . $r['age_minimum'] . ' years')->format('Y-m-d')) ?>" data-permis-max="<?= e($demande->debut->modify('-' . $r['anciennete_permis_ans'] . ' years')->format('Y-m-d')) ?>">
      <?= champ_csrf() ?>
      <?php foreach ($demande->versParams() as $cle => $valeur): if ($valeur === null) continue; ?>
        <input type="hidden" name="<?= e($cle) ?>" value="<?= e($valeur) ?>">
      <?php endforeach ?>
      <?php if ($demande->lieuRetour === $demande->lieuDepart): ?><input type="hidden" name="lieu_retour" value="<?= e($demande->lieuRetour) ?>"><?php endif ?>

      <?php if ($erreurs): ?>
        <div class="message message-erreur message-bloc" role="alert" tabindex="-1" data-erreurs>
          <?= icone('info') ?>
          <div>
            <p><strong><?= count($erreurs) > 1 ? count($erreurs) . ' points à corriger' : 'Un point à corriger' ?></strong></p>
            <ul><?php foreach ($erreurs as $cle => $m): ?><li><a href="#c-<?= e($cle) ?>"><?= e($m) ?></a></li><?php endforeach ?></ul>
          </div>
        </div>
      <?php endif ?>

      <section class="etape" aria-labelledby="e1">
        <h2 id="e1" class="etape-titre"><span class="etape-numero">1</span>Votre location</h2>
        <dl class="recap">
          <div><dt>Départ</dt><dd><?= e(date_fr($demande->debut, 'EEEE d MMMM')) ?> à <?= heure($demande->debut) ?><span><?= e($lieuDepart['nom']) ?></span></dd></div>
          <div><dt>Retour</dt><dd><?= e(date_fr($demande->fin, 'EEEE d MMMM')) ?> à <?= heure($demande->fin) ?><span><?= e($lieuRetour['nom']) ?></span></dd></div>
          <div><dt>Formule</dt><dd><?= $avec ? 'Avec chauffeur' : 'Sans chauffeur, vous conduisez' ?></dd></div>
        </dl>
        <a class="lien-fort" href="<?= e(url('/vehicules/' . $v['slug'], $demande->versParams())) ?>#reserver">Modifier les dates ou le lieu</a>
      </section>

      <?php if ($avec): ?>
        <section class="etape" aria-labelledby="e2">
          <h2 id="e2" class="etape-titre"><span class="etape-numero">2</span>Votre trajet</h2>
          <fieldset class="choix-grille" id="c-mission">
            <legend class="champ-libelle">Pour quelle occasion ?</legend>
            <?php foreach (['ville' => 'lieu', 'aeroport' => 'avion', 'mariage' => 'alliances', 'entreprise' => 'mallette', 'voyage' => 'route'] as $cle => $ic): ?>
              <label class="choix">
                <input type="radio" name="mission" value="<?= $cle ?>"<?= $mission === $cle ? ' checked' : '' ?> data-mission>
                <span class="choix-carte"><?= icone($ic) ?><?= e(config("missions.$cle")) ?></span>
              </label>
            <?php endforeach ?>
          </fieldset>
          <div class="champs-deux" data-si-mission="aeroport"<?= $mission === 'aeroport' ? '' : ' hidden' ?>>
            <div class="champ" id="c-vol">
              <label for="vol" class="champ-libelle">Numéro de vol</label>
              <input id="vol" name="vol" type="text" autocomplete="off" placeholder="Par exemple AF 902" value="<?= $a('vol') ?>"<?= $invalide('vol') ?>>
              <?= $err('vol') ?>
            </div>
            <div class="champ" id="c-atterrissage">
              <label for="atterrissage" class="champ-libelle">Heure d’atterrissage</label>
              <input id="atterrissage" name="atterrissage" type="time" value="<?= $a('atterrissage') ?>"<?= $invalide('atterrissage') ?>>
              <?= $err('atterrissage') ?>
            </div>
            <p class="champ-aide champs-deux-aide">Le chauffeur vous attend à la sortie avec une pancarte à votre nom.</p>
          </div>
          <div class="champ" id="c-destination" data-si-mission="voyage"<?= $mission === 'voyage' ? '' : ' hidden' ?>>
            <label for="destination" class="champ-libelle">Destination</label>
            <input id="destination" name="destination" type="text" placeholder="Kribi, Limbé, Dschang…" value="<?= $a('destination') ?>"<?= $invalide('destination') ?>>
            <?= $err('destination') ?>
          </div>
          <div class="champ">
            <label for="adresse" class="champ-libelle">Adresse de prise en charge <span class="facultatif">facultatif</span></label>
            <input id="adresse" name="adresse" type="text" placeholder="Hôtel, quartier, repère" value="<?= $a('adresse') ?>">
          </div>
        </section>
      <?php else: ?>
        <section class="etape" aria-labelledby="e2">
          <h2 id="e2" class="etape-titre"><span class="etape-numero">2</span>Le conducteur</h2>
          <p class="etape-intro">Pour louer sans chauffeur, le conducteur doit avoir <?= $r['age_minimum'] ?> ans et son permis depuis <?= $r['anciennete_permis_ans'] ?> ans le jour du départ.</p>
          <div class="champs-deux">
            <div class="champ" id="c-naissance">
              <label for="naissance" class="champ-libelle">Date de naissance</label>
              <input id="naissance" name="naissance" type="date" max="<?= e(maintenant()->format('Y-m-d')) ?>" value="<?= $a('naissance') ?>" autocomplete="bday" data-controle="naissance"<?= $invalide('naissance') ?>>
              <p class="champ-aide" data-controle-message="naissance" hidden></p>
              <?= $err('naissance') ?>
            </div>
            <div class="champ" id="c-permis">
              <label for="permis" class="champ-libelle">Permis obtenu le</label>
              <input id="permis" name="permis" type="date" max="<?= e(maintenant()->format('Y-m-d')) ?>" value="<?= $a('permis') ?>" data-controle="permis"<?= $invalide('permis') ?>>
              <p class="champ-aide" data-controle-message="permis" hidden></p>
              <?= $err('permis') ?>
            </div>
          </div>
          <div class="pieces" id="c-pieces">
            <p class="champ-libelle">Photos des pièces</p>
            <p class="champ-aide">Prenez-les en photo avec votre téléphone, bien à plat et lisibles. Elles ne sont vues que par l’équipe Yamoo Drive.</p>
            <?php if (isset($erreurs['pieces'])): ?><?= $err('pieces') ?><?php endif ?>
            <?php foreach (['identite' => ['carte-id', 'CNI ou passeport, recto et verso'], 'permis' => ['volant', 'Recto et verso'], 'domicile' => ['lieu', 'Facture, attestation ou réservation d’hôtel']] as $cle => [$ic, $aide]): ?>
              <div class="piece" id="c-piece_<?= $cle ?>">
                <input class="piece-input" id="piece-<?= $cle ?>" name="<?= $cle ?>[]" type="file" accept="image/*,application/pdf" multiple data-piece<?= $invalide('piece_' . $cle) ?>>
                <label class="piece-zone" for="piece-<?= $cle ?>">
                  <span class="piece-icone"><?= icone($ic) ?></span>
                  <span class="piece-texte"><strong><?= e(Conducteur::PIECES[$cle]) ?></strong><span data-piece-etat><?= e($aide) ?></span></span>
                  <span class="piece-action"><?= icone('ajout') ?>Ajouter</span>
                </label>
                <?= $err('piece_' . $cle) ?>
              </div>
            <?php endforeach ?>
          </div>
        </section>
      <?php endif ?>

      <section class="etape" aria-labelledby="e3">
        <h2 id="e3" class="etape-titre"><span class="etape-numero">3</span>Vos coordonnées</h2>
        <div class="champ" id="c-nom">
          <label for="nom" class="champ-libelle">Nom et prénom</label>
          <input id="nom" name="nom" type="text" autocomplete="name" value="<?= $a('nom') ?>" required<?= $invalide('nom') ?>>
          <?= $err('nom') ?>
        </div>
        <div class="champs-deux">
          <div class="champ" id="c-telephone">
            <label for="telephone" class="champ-libelle">Téléphone WhatsApp</label>
            <input id="telephone" name="telephone" type="tel" inputmode="tel" autocomplete="tel" placeholder="6 72 89 40 84" value="<?= $a('telephone') ?>" required<?= $invalide('telephone') ?> data-telephone>
            <?= $err('telephone') ?>
          </div>
          <div class="champ" id="c-email">
            <label for="email" class="champ-libelle">E-mail <span class="facultatif">facultatif</span></label>
            <input id="email" name="email" type="email" autocomplete="email" value="<?= $a('email') ?>"<?= $invalide('email') ?>>
            <?= $err('email') ?>
          </div>
        </div>
      </section>

      <section class="etape" aria-labelledby="e4">
        <h2 id="e4" class="etape-titre"><span class="etape-numero">4</span>Paiement</h2>
        <?php if ($demo): ?>
          <p class="message message-info"><?= icone('info') ?>Paiement de démonstration : aucun montant n’est débité.</p>
        <?php endif ?>
        <fieldset class="paiements">
          <legend class="visuellement-cache">Mode de paiement</legend>
          <label class="paiement-mode">
            <input type="radio" name="paiement" value="mobile"<?= $paiement === 'mobile' ? ' checked' : '' ?> data-paiement>
            <span class="paiement-carte">
              <span class="paiement-titre"><strong>Payer maintenant</strong><span>MTN Mobile Money ou Orange Money</span></span>
              <span class="operateurs" aria-hidden="true"><span class="operateur operateur-mtn">MTN</span><span class="operateur operateur-orange">orange</span></span>
            </span>
          </label>
          <div class="paiement-detail" data-si-paiement="mobile"<?= $paiement === 'mobile' ? '' : ' hidden' ?>>
            <fieldset class="segments segments-operateurs" id="c-operateur">
              <legend class="champ-libelle">Votre opérateur</legend>
              <label><input type="radio" name="operateur" value="mtn"<?= $operateur === 'mtn' ? ' checked' : '' ?>><span><span class="pastille pastille-mtn"></span>MTN Mobile Money</span></label>
              <label><input type="radio" name="operateur" value="orange"<?= $operateur === 'orange' ? ' checked' : '' ?>><span><span class="pastille pastille-orange"></span>Orange Money</span></label>
            </fieldset>
            <?= $err('operateur') ?>
            <div class="champ" id="c-numero">
              <label for="numero" class="champ-libelle">Numéro Mobile Money</label>
              <input id="numero" name="numero" type="tel" inputmode="tel" placeholder="6 72 89 40 84" value="<?= $a('numero') ?>"<?= $invalide('numero') ?> data-numero>
              <p class="champ-aide">Vous recevrez une demande de validation sur ce téléphone.</p>
              <?= $err('numero') ?>
            </div>
          </div>
          <label class="paiement-mode">
            <input type="radio" name="paiement" value="remise"<?= $paiement === 'remise' ? ' checked' : '' ?> data-paiement>
            <span class="paiement-carte">
              <span class="paiement-titre"><strong>Payer à la remise des clés</strong><span>En espèces ou par Mobile Money. L’équipe confirme sur WhatsApp.</span></span>
            </span>
          </label>
        </fieldset>
      </section>

      <div class="champ champ-case" id="c-conditions">
        <label class="case">
          <input type="checkbox" name="conditions" value="1"<?= !empty($ancien['conditions']) ? ' checked' : '' ?><?= $invalide('conditions') ?>>
          <span>J’accepte les <a href="<?= url('/conditions') ?>" target="_blank">conditions de location</a> de Yamoo Drive.</span>
        </label>
        <?= $err('conditions') ?>
      </div>

      <button class="bouton bouton-large bouton-plein bouton-envoyer" type="submit" data-envoyer>
        <span data-envoyer-libelle><?= $paiement === 'remise' ? 'Envoyer ma demande' : 'Payer ' . e(fcfa($devis['total'])) ?></span>
      </button>
    </form>

    <aside class="resume" aria-labelledby="t-resume">
      <div class="resume-carte">
        <div class="resume-vehicule">
          <div class="resume-image"><?= image_vehicule($v['slug'], $v['nom'], '160px') ?></div>
          <div>
            <h2 id="t-resume" class="resume-nom"><?= e($v['court']) ?></h2>
            <p><?= e($v['type']) ?> · <?= e($v['places_texte']) ?></p>
          </div>
        </div>
        <dl class="lignes">
          <div><dt><?= fcfa($devis['prix_jour']) ?> × <?= pluriel($devis['jours'], 'jour', 'jours') ?></dt><dd><?= fcfa($devis['montant_location']) ?></dd></div>
          <?php if ($devis['frais_rapatriement']): ?><div><dt>Frais de rapatriement</dt><dd><?= fcfa($devis['frais_rapatriement']) ?></dd></div><?php endif ?>
        </dl>
        <p class="total"><span>Total</span><strong><?= fcfa($devis['total']) ?></strong></p>
        <?php if ($devis['annulation_jusqua']): ?>
          <p class="resa-note"><?= icone('bouclier') ?><span>Annulation gratuite jusqu’au <?= e(date_fr($devis['annulation_jusqua'], 'd MMMM')) ?> à <?= heure($devis['annulation_jusqua']) ?></span></p>
        <?php endif ?>
        <?php if ($devis['caution']): ?>
          <p class="resa-note"><?= icone('info') ?><span>Caution remboursable de <?= fcfa($devis['caution']) ?> à la remise des clés, non incluse</span></p>
        <?php endif ?>
        <p class="resa-note"><?= icone('horloge') ?><span>Retour au plus tard le <?= e(date_fr($devis['retour_tolere'], 'd MMMM')) ?> à <?= heure($devis['retour_tolere']) ?>, tolérance comprise</span></p>
      </div>
    </aside>
  </div>
</div>
