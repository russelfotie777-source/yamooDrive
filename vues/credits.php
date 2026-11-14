<?php use Yamoo\Flotte; ?>
<article class="conteneur texte-long">
  <h1 class="page-titre">Crédits photos</h1>
  <p class="page-intro">Les photos des modèles viennent de Wikimedia Commons. Elles ont été détourées et leur plaque remplacée par Yamoo Drive ; ces versions modifiées sont diffusées sous la même licence que l’original.</p>
  <table class="tableau">
    <thead><tr><th scope="col">Véhicule</th><th scope="col">Auteur</th><th scope="col">Licence</th></tr></thead>
    <tbody>
      <?php foreach (Flotte::tous() as $v): ?>
        <tr>
          <td><?= e($v['nom']) ?></td>
          <td><?= e($v['photo']['auteur']) ?></td>
          <td><a href="<?= e($v['photo']['lien']) ?>" rel="noopener" target="_blank"><?= e($v['photo']['licence']) ?></a></td>
        </tr>
      <?php endforeach ?>
      <tr>
        <td>Jetour T2, page d’accueil</td>
        <td>Husskeyy</td>
        <td><a href="https://commons.wikimedia.org/wiki/File:Jetour_T2_in_Milnerton,_Cape_Town.jpg" rel="noopener" target="_blank">CC BY-SA 4.0</a></td>
      </tr>
    </tbody>
  </table>
  <p>Les photos d’intérieur du Jetour T2 et du Jetour X50 sont celles du site yamoodrive.com. La police Figtree est sous licence SIL Open Font License 1.1.</p>
</article>
