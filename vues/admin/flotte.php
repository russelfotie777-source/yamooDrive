<?php
use Yamoo\Flotte;
use Yamoo\Reservations;
?>
<div class="outil-entete">
  <h1 class="outil-titre">Flotte</h1>
</div>
<?php if ($erreur): ?><p class="message message-erreur" role="alert"><?= icone('info') ?><?= e($erreur) ?></p><?php endif ?>
<?php if ($flash): ?><p class="message message-succes" role="status"><?= icone('check') ?><?= e($flash) ?></p><?php endif ?>
<section class="panneau">
  <h2 class="panneau-titre">Bloquer des dates</h2>
  <p class="discret">Entretien, réparation ou location hors du site : le véhicule n’apparaît plus comme libre sur ces jours.</p>
  <form method="post" class="ligne-formulaire">
    <?= champ_csrf() ?>
    <div class="champ"><label for="b-vehicule" class="champ-libelle">Véhicule</label>
      <select id="b-vehicule" name="vehicule"><?php foreach (Flotte::tous() as $slug => $v): ?><option value="<?= e($slug) ?>"><?= e($v['nom']) ?></option><?php endforeach ?></select></div>
    <div class="champ"><label for="b-debut" class="champ-libelle">Du</label><input id="b-debut" name="debut" type="date" required></div>
    <div class="champ"><label for="b-fin" class="champ-libelle">Au</label><input id="b-fin" name="fin" type="date" required></div>
    <div class="champ"><label for="b-motif" class="champ-libelle">Motif</label><input id="b-motif" name="motif" type="text" placeholder="Entretien"></div>
    <button class="bouton bouton-petit bouton-plein" type="submit">Bloquer les dates</button>
  </form>
</section>
<div class="tableau-cadre">
  <table class="tableau tableau-dense">
    <thead><tr><th scope="col">Véhicule</th><th scope="col">Formules</th><th scope="col">Réservations à venir</th><th scope="col">Dates bloquées</th></tr></thead>
    <tbody>
      <?php foreach (Flotte::tous() as $slug => $v): ?>
        <tr>
          <td><strong><?= e($v['nom']) ?></strong><span class="discret"><?= e($v['type']) ?></span></td>
          <td><?php foreach ($v['tarifs'] as $f => $prix): ?><span class="discret-ligne"><?= $f === 'sans' ? 'Sans' : 'Avec' ?> chauffeur · <?= fcfa($prix) ?></span><?php endforeach ?></td>
          <td><?php if (!$etats[$slug]): ?><span class="discret">Aucune</span><?php endif ?>
            <?php foreach (array_slice($etats[$slug], 0, 3) as $o): ?>
              <span class="discret-ligne"><a class="code" href="<?= url('/equipe/reservations/' . $o['code']) ?>"><?= e($o['code']) ?></a> · <?= e(date_texte($o['debut'], 'dMMM')) ?> – <?= e(date_texte($o['retour'], 'dMMM')) ?><?php if ($o['statut'] !== 'confirmee'): ?> <span class="statut statut-<?= e($o['statut']) ?>"><?= e(Reservations::STATUTS[$o['statut']]) ?></span><?php endif ?></span>
            <?php endforeach ?>
            <?php if (count($etats[$slug]) > 3): ?><span class="discret"><?= count($etats[$slug]) - 3 === 1 ? 'et 1 autre' : 'et ' . (count($etats[$slug]) - 3) . ' autres' ?></span><?php endif ?></td>
          <td><?php if (empty($blocages[$slug])): ?><span class="discret">Aucune</span><?php endif ?>
            <?php foreach ($blocages[$slug] ?? [] as $b): ?>
              <form method="post" class="blocage"><?= champ_csrf() ?>
                <span><?= e(date_texte($b['debut'], 'dMMM')) ?> – <?= e(date_texte(date_locale($b['fin'])->modify('-1 day'), 'dMMM')) ?><?= $b['motif'] ? ' · ' . e($b['motif']) : '' ?></span>
                <button class="bouton-lien" name="supprimer" value="<?= (int) $b['id'] ?>">Retirer</button>
              </form>
            <?php endforeach ?></td>
        </tr>
      <?php endforeach ?>
    </tbody>
  </table>
</div>
