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
$facultatif = '<span class="facultatif">' . e(t('facultatif')) . '</span>';
?>
<div class="conteneur reserver">
  <a class="retour" href="<?= e(url('/vehicules/' . $v['slug'], $demande->versParams())) ?>"><?= icone('fleche-gauche', 'icone icone-sens') ?><?= e(t('Retour au véhicule')) ?></a>
  <h1 class="page-titre"><?= e(t('Réserver le {nom}', ['nom' => $v['court']])) ?></h1>

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
            <p><strong><?= e(t('{n, plural, one {Un point à corriger} other {# points à corriger}}', ['n' => count($erreurs)])) ?></strong></p>
            <ul><?php foreach ($erreurs as $cle => $m): ?><li><a href="#c-<?= e($cle) ?>"><?= e($m) ?></a></li><?php endforeach ?></ul>
          </div>
        </div>
      <?php endif ?>

      <section class="etape" aria-labelledby="e1">
        <h2 id="e1" class="etape-titre"><span class="etape-numero">1</span><?= e(t('Votre location')) ?></h2>
        <dl class="recap">
          <div><dt><?= e(t('Départ')) ?></dt><dd><?= e(t('{date} à {heure}', ['date' => date_texte($demande->debut, 'EEEEdMMMM'), 'heure' => heure($demande->debut)])) ?><span><?= e(lieu($demande->lieuDepart)) ?></span></dd></div>
          <div><dt><?= e(t('Retour')) ?></dt><dd><?= e(t('{date} à {heure}', ['date' => date_texte($demande->fin, 'EEEEdMMMM'), 'heure' => heure($demande->fin)])) ?><span><?= e(lieu($demande->lieuRetour)) ?></span></dd></div>
          <div><dt><?= e(t('Formule')) ?></dt><dd><?= e($avec ? t('Avec chauffeur') : t('Sans chauffeur, vous conduisez')) ?></dd></div>
        </dl>
        <a class="lien-fort" href="<?= e(url('/vehicules/' . $v['slug'], $demande->versParams())) ?>#reserver"><?= e(t('Modifier les dates ou le lieu')) ?></a>
      </section>

      <?php if ($avec): ?>
        <section class="etape" aria-labelledby="e2">
          <h2 id="e2" class="etape-titre"><span class="etape-numero">2</span><?= e(t('Votre trajet')) ?></h2>
          <fieldset class="choix-grille" id="c-mission">
            <legend class="champ-libelle"><?= e(t('Pour quelle occasion ?')) ?></legend>
            <?php foreach (['ville' => 'lieu', 'aeroport' => 'avion', 'mariage' => 'alliances', 'entreprise' => 'mallette', 'voyage' => 'route'] as $cle => $ic): ?>
              <label class="choix">
                <input type="radio" name="mission" value="<?= $cle ?>"<?= $mission === $cle ? ' checked' : '' ?> data-mission>
                <span class="choix-carte"><?= icone($ic) ?><?= e(t(config("missions.$cle"))) ?></span>
              </label>
            <?php endforeach ?>
          </fieldset>
          <div class="champs-deux" data-si-mission="aeroport"<?= $mission === 'aeroport' ? '' : ' hidden' ?>>
            <div class="champ" id="c-vol">
              <label for="vol" class="champ-libelle"><?= e(t('Numéro de vol')) ?></label>
              <input id="vol" name="vol" type="text" autocomplete="off" placeholder="<?= e(t('Par exemple AF 902')) ?>" value="<?= $a('vol') ?>"<?= $invalide('vol') ?>>
              <?= $err('vol') ?>
            </div>
            <div class="champ" id="c-atterrissage">
              <label for="atterrissage" class="champ-libelle"><?= e(t('Heure d’atterrissage')) ?></label>
              <input id="atterrissage" name="atterrissage" type="time" value="<?= $a('atterrissage') ?>"<?= $invalide('atterrissage') ?>>
              <?= $err('atterrissage') ?>
            </div>
            <p class="champ-aide champs-deux-aide"><?= e(t('Le chauffeur vous attend à la sortie avec une pancarte à votre nom.')) ?></p>
          </div>
          <div class="champ" id="c-destination" data-si-mission="voyage"<?= $mission === 'voyage' ? '' : ' hidden' ?>>
            <label for="destination" class="champ-libelle"><?= e(t('Destination')) ?></label>
            <input id="destination" name="destination" type="text" placeholder="<?= e(t('Kribi, Limbé, Dschang…')) ?>" value="<?= $a('destination') ?>"<?= $invalide('destination') ?>>
            <?= $err('destination') ?>
          </div>
          <div class="champ">
            <label for="adresse" class="champ-libelle"><?= e(t('Adresse de prise en charge')) ?> <?= $facultatif ?></label>
            <input id="adresse" name="adresse" type="text" placeholder="<?= e(t('Hôtel, quartier, repère')) ?>" value="<?= $a('adresse') ?>">
          </div>
        </section>
      <?php else: ?>
        <section class="etape" aria-labelledby="e2">
          <h2 id="e2" class="etape-titre"><span class="etape-numero">2</span><?= e(t('Le conducteur')) ?></h2>
          <p class="etape-intro"><?= e(t('Pour louer sans chauffeur, le conducteur doit avoir {age} ans et son permis depuis {permis} ans le jour du départ.', ['age' => $r['age_minimum'], 'permis' => $r['anciennete_permis_ans']])) ?></p>
          <div class="champs-deux">
            <div class="champ" id="c-naissance">
              <label for="naissance" class="champ-libelle"><?= e(t('Date de naissance')) ?></label>
              <input id="naissance" name="naissance" type="date" max="<?= e(maintenant()->format('Y-m-d')) ?>" value="<?= $a('naissance') ?>" autocomplete="bday" data-controle="naissance"<?= $invalide('naissance') ?>>
              <p class="champ-aide" data-controle-message="naissance" hidden></p>
              <?= $err('naissance') ?>
            </div>
            <div class="champ" id="c-permis">
              <label for="permis" class="champ-libelle"><?= e(t('Permis obtenu le')) ?></label>
              <input id="permis" name="permis" type="date" max="<?= e(maintenant()->format('Y-m-d')) ?>" value="<?= $a('permis') ?>" data-controle="permis"<?= $invalide('permis') ?>>
              <p class="champ-aide" data-controle-message="permis" hidden></p>
              <?= $err('permis') ?>
            </div>
          </div>
          <div class="pieces" id="c-pieces">
            <p class="champ-libelle"><?= e(t('Photos des pièces')) ?></p>
            <p class="champ-aide"><?= e(t('Prenez-les en photo avec votre téléphone, bien à plat et lisibles. Elles ne sont vues que par l’équipe Yamoo Drive.')) ?></p>
            <?php if (isset($erreurs['pieces'])): ?><?= $err('pieces') ?><?php endif ?>
            <?php foreach (['identite' => ['carte-id', 'CNI ou passeport, recto et verso'], 'permis' => ['volant', 'Recto et verso'], 'domicile' => ['lieu', 'Facture, attestation ou réservation d’hôtel']] as $cle => [$ic, $aide]): ?>
              <div class="piece" id="c-piece_<?= $cle ?>">
                <input class="piece-input" id="piece-<?= $cle ?>" name="<?= $cle ?>[]" type="file" accept="image/*,application/pdf" multiple data-piece<?= $invalide('piece_' . $cle) ?>>
                <label class="piece-zone" for="piece-<?= $cle ?>">
                  <span class="piece-icone"><?= icone($ic) ?></span>
                  <span class="piece-texte"><strong><?= e(t(Conducteur::PIECES[$cle])) ?></strong><span data-piece-etat><?= e(t($aide)) ?></span></span>
                  <span class="piece-action"><?= icone('ajout') ?><?= e(t('Ajouter')) ?></span>
                </label>
                <?= $err('piece_' . $cle) ?>
              </div>
            <?php endforeach ?>
          </div>
        </section>
      <?php endif ?>

      <section class="etape" aria-labelledby="e3">
        <h2 id="e3" class="etape-titre"><span class="etape-numero">3</span><?= e(t('Vos coordonnées')) ?></h2>
        <div class="champ" id="c-nom">
          <label for="nom" class="champ-libelle"><?= e(t('Nom et prénom')) ?></label>
          <input id="nom" name="nom" type="text" autocomplete="name" value="<?= $a('nom') ?>" required<?= $invalide('nom') ?>>
          <?= $err('nom') ?>
        </div>
        <div class="champs-deux">
          <div class="champ" id="c-telephone">
            <label for="telephone" class="champ-libelle"><?= e(t('Téléphone WhatsApp')) ?></label>
            <input id="telephone" name="telephone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="6 72 89 40 84" value="<?= $a('telephone') ?>" required<?= $invalide('telephone') ?> data-telephone>
            <?= $err('telephone') ?>
          </div>
          <div class="champ" id="c-email">
            <label for="email" class="champ-libelle"><?= e(t('E-mail')) ?> <?= $facultatif ?></label>
            <input id="email" name="email" type="email" autocomplete="email" dir="ltr" value="<?= $a('email') ?>"<?= $invalide('email') ?>>
            <?= $err('email') ?>
          </div>
        </div>
      </section>

      <section class="etape" aria-labelledby="e4">
        <h2 id="e4" class="etape-titre"><span class="etape-numero">4</span><?= e(t('Paiement')) ?></h2>
        <?php if ($demo): ?>
          <p class="message message-info"><?= icone('info') ?><?= e(t('Paiement de démonstration : aucun montant n’est débité.')) ?></p>
        <?php endif ?>
        <fieldset class="paiements">
          <legend class="visuellement-cache"><?= e(t('Mode de paiement')) ?></legend>
          <label class="paiement-mode">
            <input type="radio" name="paiement" value="mobile"<?= $paiement === 'mobile' ? ' checked' : '' ?> data-paiement>
            <span class="paiement-carte">
              <span class="paiement-titre"><strong><?= e(t('Payer maintenant')) ?></strong><span><?= e(t('MTN Mobile Money ou Orange Money')) ?></span></span>
              <span class="operateurs" aria-hidden="true"><span class="operateur operateur-mtn">MTN</span><span class="operateur operateur-orange">orange</span></span>
            </span>
          </label>
          <div class="paiement-detail" data-si-paiement="mobile"<?= $paiement === 'mobile' ? '' : ' hidden' ?>>
            <fieldset class="segments segments-operateurs" id="c-operateur">
              <legend class="champ-libelle"><?= e(t('Votre opérateur')) ?></legend>
              <label><input type="radio" name="operateur" value="mtn"<?= $operateur === 'mtn' ? ' checked' : '' ?>><span><span class="pastille pastille-mtn"></span>MTN Mobile Money</span></label>
              <label><input type="radio" name="operateur" value="orange"<?= $operateur === 'orange' ? ' checked' : '' ?>><span><span class="pastille pastille-orange"></span>Orange Money</span></label>
            </fieldset>
            <?= $err('operateur') ?>
            <div class="champ" id="c-numero">
              <label for="numero" class="champ-libelle"><?= e(t('Numéro Mobile Money')) ?></label>
              <input id="numero" name="numero" type="tel" inputmode="tel" dir="ltr" placeholder="6 72 89 40 84" value="<?= $a('numero') ?>"<?= $invalide('numero') ?> data-numero>
              <p class="champ-aide"><?= e(t('Vous recevrez une demande de validation sur ce téléphone.')) ?></p>
              <?= $err('numero') ?>
            </div>
          </div>
          <label class="paiement-mode">
            <input type="radio" name="paiement" value="remise"<?= $paiement === 'remise' ? ' checked' : '' ?> data-paiement>
            <span class="paiement-carte">
              <span class="paiement-titre"><strong><?= e(t('Payer à la remise des clés')) ?></strong><span><?= e(t('En espèces ou par Mobile Money. L’équipe confirme sur WhatsApp.')) ?></span></span>
            </span>
          </label>
        </fieldset>
      </section>

      <div class="champ champ-case" id="c-conditions">
        <label class="case">
          <input type="checkbox" name="conditions" value="1"<?= !empty($ancien['conditions']) ? ' checked' : '' ?><?= $invalide('conditions') ?>>
          <span><?= h('J’accepte les <a href="{lien}" target="_blank">conditions de location</a> de Yamoo Drive.', ['lien' => url('/conditions')]) ?></span>
        </label>
        <?= $err('conditions') ?>
      </div>

      <button class="bouton bouton-large bouton-plein bouton-envoyer" type="submit" data-envoyer>
        <span data-envoyer-libelle><?= e($paiement === 'remise' ? t('Envoyer ma demande') : t('Payer {total}', ['total' => fcfa($devis['total'])])) ?></span>
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
          <div><dt><?= fcfa($devis['prix_jour']) ?> × <?= e(nb_jours($devis['jours'])) ?></dt><dd><?= fcfa($devis['montant_location']) ?></dd></div>
          <?php if ($devis['frais_rapatriement']): ?><div><dt><?= e(t('Frais de rapatriement')) ?></dt><dd><?= fcfa($devis['frais_rapatriement']) ?></dd></div><?php endif ?>
        </dl>
        <p class="total"><span><?= e(t('Total')) ?></span><strong><?= fcfa($devis['total']) ?></strong></p>
        <?php if ($devis['annulation_jusqua']): ?>
          <p class="resa-note"><?= icone('bouclier') ?><span><?= e(t('Annulation gratuite jusqu’au {date} à {heure}', ['date' => date_texte($devis['annulation_jusqua'], 'dMMMM'), 'heure' => heure($devis['annulation_jusqua'])])) ?></span></p>
        <?php endif ?>
        <?php if ($devis['caution']): ?>
          <p class="resa-note"><?= icone('info') ?><span><?= e(t('Caution remboursable de {montant} à la remise des clés, non incluse', ['montant' => fcfa($devis['caution'])])) ?></span></p>
        <?php endif ?>
        <p class="resa-note"><?= icone('horloge') ?><span><?= e(t('Retour au plus tard le {date} à {heure}, tolérance comprise', ['date' => date_texte($devis['retour_tolere'], 'dMMMM'), 'heure' => heure($devis['retour_tolere'])])) ?></span></p>
      </div>
    </aside>
  </div>
</div>
