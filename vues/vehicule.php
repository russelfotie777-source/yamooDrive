<?php
/** @var array $v @var Yamoo\Demande $demande @var ?array $devis */
use Yamoo\Flotte;

$scripts = ['calendrier.js', 'vehicule.js'];
$deuxFormules = isset($v['tarifs']['sans'], $v['tarifs']['avec']);
$min = maintenant()->modify('+' . config('regles.delai_minimum_heures') . ' hours')->format('Y-m-d\TH:i');
$galerie = $v['galerie'];
$r = config('regles');
$categories = config('categories');
?>
<div class="conteneur fiche">
  <nav class="fil" aria-label="Fil d’Ariane">
    <a href="<?= url('/vehicules') ?>">Véhicules</a>
    <?= icone('chevron-droite') ?>
    <a href="<?= url('/vehicules', ['categorie' => $v['categorie']]) ?>"><?= e($categories[$v['categorie']]) ?></a>
  </nav>

  <header class="fiche-entete">
    <h1 class="page-titre"><?= e($v['nom']) ?></h1>
    <p class="fiche-meta">
      <span><?= e($v['type']) ?></span>
      <span><?= icone('places') ?><?= e($v['places_texte']) ?></span>
      <span><?= icone($deuxFormules || isset($v['tarifs']['sans']) ? 'cle' : 'volant') ?><?= e(Flotte::formulesTexte($v)) ?></span>
      <?php if (!empty($v['phare'])): ?><span class="etiquette etiquette-or">Modèle phare</span><?php endif ?>
    </p>
  </header>

  <div class="galerie<?= $galerie ? ' galerie-mosaique' : '' ?>">
    <div class="galerie-principale">
      <?= image_vehicule($v['slug'], $v['nom'] . ', photo du modèle', $galerie ? '(min-width: 900px) 640px, 92vw' : '(min-width: 1328px) 1280px, 92vw', true) ?>
    </div>
    <?php foreach (array_slice($galerie, 0, 4) as $i => [$nom, $alt, $l, $h]): ?>
      <button class="galerie-vignette" type="button" data-galerie-ouvrir="<?= $i ?>" aria-label="Agrandir : <?= e($alt) ?>">
        <?= image_galerie($nom, $alt, $l, $h, '(min-width: 900px) 320px, 46vw') ?>
      </button>
    <?php endforeach ?>
    <?php if (count($galerie) > 4): ?>
      <button class="bouton bouton-contour galerie-tout" type="button" data-galerie-ouvrir="0"><?= icone('grille') ?>Voir les <?= count($galerie) ?> photos</button>
    <?php endif ?>
  </div>

  <div class="fiche-grille">
    <div class="fiche-detail">
      <p class="fiche-resume"><?= e($v['resume']) ?></p>

      <section class="bloc" aria-labelledby="t-formules">
        <h2 id="t-formules" class="bloc-titre">Formules et prix</h2>
        <ul class="tarifs">
          <?php foreach (['sans' => ['cle', 'Sans chauffeur', 'Vous conduisez. Caution remboursable à la remise des clés.'], 'avec' => ['volant', 'Avec chauffeur', 'Un chauffeur Yamoo Drive vous conduit. Ni permis ni caution.']] as $f => [$ic, $libelle, $texte]): ?>
            <?php if (isset($v['tarifs'][$f])): ?>
              <li>
                <span class="tarif-icone"><?= icone($ic) ?></span>
                <div><h3><?= e($libelle) ?></h3><p><?= e($texte) ?></p></div>
                <p class="tarif-prix"><strong><?= fcfa($v['tarifs'][$f]) ?></strong><span>par jour</span></p>
              </li>
            <?php endif ?>
          <?php endforeach ?>
        </ul>
        <?php if (!isset($v['tarifs']['sans'])): ?>
          <p class="note"><?= icone('info') ?>Ce véhicule se loue uniquement avec chauffeur.</p>
        <?php endif ?>
      </section>

      <section class="bloc" aria-labelledby="t-equipements">
        <h2 id="t-equipements" class="bloc-titre">Équipements</h2>
        <ul class="liste-coches liste-colonnes">
          <?php foreach ($v['equipements'] as $eq): ?><li><?= icone('check') ?><?= e($eq) ?></li><?php endforeach ?>
        </ul>
        <h3 class="bloc-sous-titre">Idéal pour</h3>
        <ul class="puces">
          <?php foreach ($v['ideal'] as $i): ?><li><?= e($i) ?></li><?php endforeach ?>
        </ul>
      </section>

      <section class="bloc" aria-labelledby="t-dispo">
        <h2 id="t-dispo" class="bloc-titre">Disponibilités</h2>
        <p class="bloc-intro">Touchez le jour du départ puis celui du retour. Les jours barrés sont déjà réservés.</p>
        <div class="calendrier-en-ligne" data-calendrier-en-ligne></div>
      </section>

      <section class="bloc" aria-labelledby="t-savoir">
        <h2 id="t-savoir" class="bloc-titre">Bon à savoir</h2>
        <ul class="savoir">
          <li><?= icone('horloge') ?><div><h3>Journée de 24 heures</h3><p>Comptée à partir de la prise en charge, avec <?= $r['tolerance_retour_minutes'] ?> minutes de tolérance au retour.</p></div></li>
          <li><?= icone('carburant') ?><div><h3>Carburant</h3><p>Rendu au même niveau qu’au départ, sinon la différence est facturée.</p></div></li>
          <li><?= icone('bouclier') ?><div><h3>Annulation gratuite</h3><p>Jusqu’à <?= $r['annulation_gratuite_heures'] ?> heures avant la prise en charge.</p></div></li>
          <li><?= icone('carte-id') ?><div><h3>Sans chauffeur</h3><p><?= $r['age_minimum'] ?> ans minimum, permis depuis <?= $r['anciennete_permis_ans'] ?> ans, CNI ou passeport, justificatif de domicile.</p></div></li>
        </ul>
        <p class="credit">Photo du modèle : la couleur et la finition du véhicule remis peuvent varier. Photo <?= e($v['photo']['auteur']) ?>, <a href="<?= e($v['photo']['lien']) ?>" rel="noopener" target="_blank"><?= e($v['photo']['licence']) ?></a>, image retouchée par Yamoo Drive.</p>
      </section>
    </div>

    <aside class="fiche-reservation" id="reserver" aria-labelledby="t-reserver">
      <form class="carte-resa" action="<?= url('/reserver/' . $v['slug']) ?>" method="get" data-resa
            data-vehicule="<?= e($v['slug']) ?>" data-pris="<?= e(json_encode($joursPris)) ?>" data-min="<?= e($min) ?>"
            data-tarifs="<?= e(json_encode(array_map('fcfa', $v['tarifs']), JSON_UNESCAPED_UNICODE)) ?>">
        <h2 id="t-reserver" class="visuellement-cache">Réserver le <?= e($v['court']) ?></h2>
        <p class="resa-prix"><strong data-resa-prix-jour><?= fcfa($v['tarifs'][$demande->formule]) ?></strong> par jour</p>

        <?php if ($deuxFormules): ?>
          <fieldset class="segments">
            <legend class="visuellement-cache">Formule</legend>
            <?php foreach (['sans' => 'Sans chauffeur', 'avec' => 'Avec chauffeur'] as $f => $libelle): ?>
              <label><input type="radio" name="formule" value="<?= $f ?>"<?= $demande->formule === $f ? ' checked' : '' ?>><span><?= e($libelle) ?></span></label>
            <?php endforeach ?>
          </fieldset>
        <?php else: ?>
          <input type="hidden" name="formule" value="<?= e($demande->formule) ?>">
        <?php endif ?>

        <div class="resa-champs" data-plage data-min="<?= e($min) ?>">
          <div class="resa-champ">
            <label for="f-debut">Départ</label>
            <input id="f-debut" type="datetime-local" name="debut" min="<?= e($min) ?>" step="1800" value="<?= e($demande->debut?->format('Y-m-d\TH:i')) ?>" data-debut>
          </div>
          <div class="resa-champ">
            <label for="f-fin">Retour</label>
            <input id="f-fin" type="datetime-local" name="fin" min="<?= e($min) ?>" step="1800" value="<?= e($demande->fin?->format('Y-m-d\TH:i')) ?>" data-fin>
          </div>
          <div class="resa-champ">
            <label for="f-lieu">Prise en charge</label>
            <select id="f-lieu" name="lieu">
              <?php foreach (config('lieux') as $cle => $l): ?><option value="<?= e($cle) ?>"<?= $demande->lieuDepart === $cle ? ' selected' : '' ?>><?= e($l['court']) ?></option><?php endforeach ?>
            </select>
          </div>
          <div class="resa-champ">
            <label for="f-retour">Retour à</label>
            <select id="f-retour" name="lieu_retour">
              <?php foreach (config('lieux') as $cle => $l): ?><option value="<?= e($cle) ?>"<?= $demande->lieuRetour === $cle ? ' selected' : '' ?>><?= e($l['court']) ?></option><?php endforeach ?>
            </select>
          </div>
        </div>

        <p class="message message-erreur" data-resa-message role="status"<?= ($flash || $libre === false) ? '' : ' hidden' ?>>
          <?= icone('info') ?><span><?= e($flash ?? ($libre === false ? ($libreA ? 'Réservé jusqu’au ' . date_fr($libreA, 'EEEE d MMMM') . ' à ' . heure($libreA) . '. Choisissez d’autres dates.' : 'Déjà réservé à ces dates. Choisissez d’autres dates.') : '')) ?></span>
        </p>

        <button class="bouton bouton-large bouton-plein" type="submit" data-resa-bouton<?= $libre === false ? ' disabled' : '' ?>>
          <?= $devis ? 'Réserver' : 'Choisir mes dates' ?>
        </button>
        <p class="resa-rassure" data-resa-rassure<?= $devis ? '' : ' hidden' ?>>Rien n’est débité à cette étape</p>

        <div class="resa-devis" data-resa-devis<?= $devis ? '' : ' hidden' ?>>
          <dl class="lignes" data-resa-lignes>
            <?php if ($devis): ?>
              <div><dt><?= fcfa($devis['prix_jour']) ?> × <?= pluriel($devis['jours'], 'jour', 'jours') ?></dt><dd><?= fcfa($devis['montant_location']) ?></dd></div>
              <?php if ($devis['frais_rapatriement']): ?><div><dt>Frais de rapatriement</dt><dd><?= fcfa($devis['frais_rapatriement']) ?></dd></div><?php endif ?>
            <?php endif ?>
          </dl>
          <p class="total"><span>Total</span><strong data-resa-total><?= $devis ? fcfa($devis['total']) : '' ?></strong></p>
          <p class="resa-note" data-resa-annulation<?= ($devis['annulation_jusqua'] ?? null) ? '' : ' hidden' ?>><?= icone('bouclier') ?><span><?= $devis && $devis['annulation_jusqua'] ? 'Annulation gratuite jusqu’au ' . e(date_fr($devis['annulation_jusqua'], 'd MMMM')) . ' à ' . heure($devis['annulation_jusqua']) : '' ?></span></p>
          <p class="resa-note" data-resa-caution<?= ($devis['caution'] ?? 0) ? '' : ' hidden' ?>><?= icone('info') ?><span><?= $devis && $devis['caution'] ? 'Caution remboursable de ' . fcfa($devis['caution']) . ' à la remise des clés' : '' ?></span></p>
        </div>
      </form>
      <a class="resa-whatsapp" href="<?= e(lien_whatsapp("Bonjour Yamoo Drive, j’ai une question sur le {$v['nom']}.")) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?>Une question sur ce véhicule ?</a>
    </aside>
  </div>

  <section class="section section-serree" aria-labelledby="t-semblables">
    <h2 id="t-semblables" class="section-titre section-titre-petit">Vous aimerez aussi</h2>
    <div class="grille-vehicules">
      <?php foreach ($semblables as $c) partiel('carte-vehicule', ['c' => $c]) ?>
    </div>
  </section>
</div>

<div class="barre-resa" data-barre-resa>
  <p><strong data-barre-prix><?= $devis ? fcfa($devis['total']) : fcfa($v['tarifs'][$demande->formule]) ?></strong><span data-barre-unite><?= $devis ? 'pour ' . pluriel($devis['jours'], 'jour', 'jours') : 'par jour' ?></span></p>
  <a class="bouton bouton-plein" href="#reserver">Réserver</a>
</div>

<?php if ($galerie): ?>
  <dialog class="visionneuse" data-galerie aria-label="Photos du <?= e($v['court']) ?>">
    <div class="visionneuse-entete">
      <p><?= e($v['nom']) ?> · <?= count($galerie) ?> photos fournies par Yamoo Drive</p>
      <button class="bouton-icone" type="button" aria-label="Fermer les photos" data-galerie-fermer><?= icone('fermer') ?></button>
    </div>
    <ul class="visionneuse-liste">
      <?php foreach ($galerie as [$nom, $alt, $l, $h]): ?>
        <li><figure><?= image_galerie($nom, $alt, $l, $h, '(min-width: 900px) 860px, 96vw') ?><figcaption><?= e($alt) ?></figcaption></figure></li>
      <?php endforeach ?>
    </ul>
  </dialog>
<?php endif ?>
