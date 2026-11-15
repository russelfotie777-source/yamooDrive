<?php
$r = config('regles');
$sections = [
    'Le conducteur' => [
        t('Âge minimum : {age} ans.', ['age' => $r['age_minimum']]),
        t('Permis de conduire valide depuis au moins {ans} ans. Le permis international est accepté pour les non-résidents.', ['ans' => $r['anciennete_permis_ans']]),
        t('Pièces à présenter : CNI ou passeport, et justificatif de domicile ou de séjour.'),
        t('Seuls les conducteurs déclarés peuvent conduire le véhicule.'),
        t('Avec chauffeur, aucun permis ni caution n’est demandé au client.'),
    ],
    'Réservation' => [
        t('Une réservation payée en ligne est confirmée dès la réception du paiement. Une demande avec paiement à la remise des clés est confirmée par l’équipe Yamoo Drive.'),
        t('Un véhicule de catégorie égale ou supérieure peut être proposé à la place du modèle réservé.'),
    ],
    'Durée et retour' => [
        t('Une journée de location correspond à 24 heures à partir de l’heure de prise en charge.'),
        t('Une tolérance de {minutes} minutes est accordée au retour. Au-delà, une journée supplémentaire peut être facturée.', ['minutes' => $r['tolerance_retour_minutes']]),
        t('Le véhicule est rendu à l’heure et à l’agence prévues. Une prolongation se demande à l’avance.'),
        t('Aller simple : le véhicule peut être rendu dans une autre ville, avec des frais de rapatriement ajoutés au prix.'),
    ],
    'Paiement et caution' => [
        t('Paiement en ligne par MTN Mobile Money ou Orange Money, ou à la remise des clés en espèces ou par Mobile Money.'),
        t('Une caution est demandée pour la location sans chauffeur. Elle est rendue au retour, après l’état des lieux.'),
    ],
    'Carburant et kilométrage' => [
        t('Le véhicule est rendu avec le même niveau de carburant qu’au départ. La différence est facturée sinon.'),
        t('Les limites de kilométrage et le prix du kilomètre supplémentaire figurent au contrat.'),
        t('Avec chauffeur, le carburant des trajets hors de la ville de prise en charge est à la charge du client, sauf accord contraire.'),
    ],
    'Utilisation du véhicule' => [
        t('Sortie du territoire camerounais interdite sans accord écrit de Yamoo Drive.'),
        t('Interdits : sous-location, transport rémunéré de personnes ou de marchandises, courses, remorquage, conduite sous l’effet de l’alcool ou de drogues.'),
        t('Il est interdit de fumer à bord. Un forfait de nettoyage peut être facturé.'),
        t('Les amendes et contraventions sont à la charge du client.'),
    ],
    'Assurance et incidents' => [
        t('Les véhicules sont assurés selon la réglementation camerounaise.'),
        t('Tout accident, vol ou dommage est signalé immédiatement à Yamoo Drive et déclaré aux autorités.'),
        t('Une franchise s’applique lorsque le client est responsable. Aucune réparation sans l’accord de Yamoo Drive.'),
    ],
    'Annulation' => [
        t('Annulation gratuite jusqu’à {heures} heures avant l’heure de prise en charge, depuis la page de la réservation. Un paiement en ligne est alors remboursé.', ['heures' => $r['annulation_gratuite_heures']]),
        t('Des absences répétées sans prévenir peuvent conduire à refuser de futures réservations.'),
    ],
];
?>
<article class="conteneur texte-long">
  <h1 class="page-titre"><?= e(t('Conditions de location')) ?></h1>
  <p class="page-intro"><?= e(t('Résumé des conditions générales de Yamoo Drive. Le contrat signé à la remise des clés fait foi.')) ?></p>

  <?php foreach ($sections as $titreSection => $points): ?>
    <h2><?= e(t($titreSection)) ?></h2>
    <ul>
      <?php foreach ($points as $point): ?><li><?= e($point) ?></li><?php endforeach ?>
    </ul>
  <?php endforeach ?>

  <p class="note"><?= icone('info') ?><?= e(t('Ces conditions peuvent évoluer. Une question ? Écrivez-nous sur WhatsApp au {numero}.', ['numero' => config('contact.telephone_affiche')])) ?></p>
</article>
