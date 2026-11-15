<?php use Yamoo\Reservations; ?>
<div class="outil-entete">
  <h1 class="outil-titre">Réservations</h1>
  <form class="outil-recherche" method="get" action="<?= url('/equipe') ?>" role="search">
    <?php if ($statut): ?><input type="hidden" name="statut" value="<?= e($statut) ?>"><?php endif ?>
    <label class="visuellement-cache" for="q">Rechercher</label>
    <input id="q" name="q" type="search" placeholder="Code, nom ou téléphone" value="<?= e($recherche) ?>">
    <button class="bouton bouton-petit bouton-contour" type="submit"><?= icone('recherche') ?>Rechercher</button>
  </form>
</div>
<?php if ($flash): ?><p class="message message-succes" role="status"><?= icone('check') ?><?= e($flash) ?></p><?php endif ?>
<nav class="onglets" aria-label="Filtrer par état">
  <a href="<?= url('/equipe', ['q' => $recherche]) ?>"<?= !$statut ? ' aria-current="page"' : '' ?>>Toutes <span><?= array_sum($compte) ?></span></a>
  <?php foreach (Reservations::STATUTS as $cle => $libelle): ?>
    <a href="<?= url('/equipe', ['statut' => $cle, 'q' => $recherche]) ?>"<?= $statut === $cle ? ' aria-current="page"' : '' ?>><?= e($libelle) ?> <span><?= $compte[$cle] ?? 0 ?></span></a>
  <?php endforeach ?>
</nav>
<?php if (!$liste): ?>
  <div class="vide vide-outil">
    <h2>Aucune réservation</h2>
    <p><?= $recherche !== '' ? 'Modifiez votre recherche et réessayez.' : 'Les réservations du site apparaîtront ici.' ?></p>
  </div>
<?php else: ?>
  <div class="tableau-cadre">
    <table class="tableau tableau-dense">
      <thead><tr><th scope="col">Code</th><th scope="col">Client</th><th scope="col">Véhicule</th><th scope="col">Départ</th><th scope="col">Retour</th><th scope="col">État</th><th scope="col" class="nombre">Total</th></tr></thead>
      <tbody>
        <?php foreach ($liste as $r): ?>
          <tr>
            <td><a class="code" href="<?= url('/equipe/reservations/' . $r['code']) ?>"><?= e($r['code']) ?></a></td>
            <td><?= e($r['client_nom']) ?><span class="discret"><?= e(telephone_lisible($r['client_telephone'])) ?></span></td>
            <td><?= e($r['v']['court'] ?? $r['vehicule']) ?><span class="discret"><?= $r['formule'] === 'sans' ? 'Sans chauffeur' : 'Avec chauffeur' ?></span></td>
            <td class="nombre-gauche"><?= e(date_fr($r['debut'], 'd MMM')) ?>, <?= heure($r['debut']) ?><span class="discret"><?= e(config("lieux.{$r['lieu_depart']}.court")) ?></span></td>
            <td class="nombre-gauche"><?= e(date_fr($r['fin'], 'd MMM')) ?>, <?= heure($r['fin']) ?><span class="discret"><?= e(config("lieux.{$r['lieu_retour']}.court")) ?></span></td>
            <td><span class="statut statut-<?= e($r['statut']) ?>"><?= e(Reservations::STATUTS[$r['statut']]) ?></span><?php if ($r['paiement_statut'] === 'paye'): ?><span class="discret">Payée</span><?php endif ?></td>
            <td class="nombre"><?= fcfa($r['total']) ?></td>
          </tr>
        <?php endforeach ?>
      </tbody>
    </table>
  </div>
<?php endif ?>
