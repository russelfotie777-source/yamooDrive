<?php $r = config('regles'); ?>
<article class="conteneur texte-long">
  <h1 class="page-titre">Conditions de location</h1>
  <p class="page-intro">Résumé des conditions générales de Yamoo Drive. Le contrat signé à la remise des clés fait foi.</p>

  <h2>Le conducteur</h2>
  <ul>
    <li>Âge minimum : <?= $r['age_minimum'] ?> ans.</li>
    <li>Permis de conduire valide depuis au moins <?= $r['anciennete_permis_ans'] ?> ans. Le permis international est accepté pour les non-résidents.</li>
    <li>Pièces à présenter : CNI ou passeport, et justificatif de domicile ou de séjour.</li>
    <li>Seuls les conducteurs déclarés peuvent conduire le véhicule.</li>
    <li>Avec chauffeur, aucun permis ni caution n’est demandé au client.</li>
  </ul>

  <h2>Réservation</h2>
  <ul>
    <li>Une réservation payée en ligne est confirmée dès la réception du paiement. Une demande avec paiement à la remise des clés est confirmée par l’équipe Yamoo Drive.</li>
    <li>Un véhicule de catégorie égale ou supérieure peut être proposé à la place du modèle réservé.</li>
  </ul>

  <h2>Durée et retour</h2>
  <ul>
    <li>Une journée de location correspond à 24 heures à partir de l’heure de prise en charge.</li>
    <li>Une tolérance de <?= $r['tolerance_retour_minutes'] ?> minutes est accordée au retour. Au-delà, une journée supplémentaire peut être facturée.</li>
    <li>Le véhicule est rendu à l’heure et à l’agence prévues. Une prolongation se demande à l’avance.</li>
    <li>Aller simple : le véhicule peut être rendu dans une autre ville, avec des frais de rapatriement ajoutés au prix.</li>
  </ul>

  <h2>Paiement et caution</h2>
  <ul>
    <li>Paiement en ligne par MTN Mobile Money ou Orange Money, ou à la remise des clés en espèces ou par Mobile Money.</li>
    <li>Une caution est demandée pour la location sans chauffeur. Elle est rendue au retour, après l’état des lieux.</li>
  </ul>

  <h2>Carburant et kilométrage</h2>
  <ul>
    <li>Le véhicule est rendu avec le même niveau de carburant qu’au départ. La différence est facturée sinon.</li>
    <li>Les limites de kilométrage et le prix du kilomètre supplémentaire figurent au contrat.</li>
    <li>Avec chauffeur, le carburant des trajets hors de la ville de prise en charge est à la charge du client, sauf accord contraire.</li>
  </ul>

  <h2>Utilisation du véhicule</h2>
  <ul>
    <li>Sortie du territoire camerounais interdite sans accord écrit de Yamoo Drive.</li>
    <li>Interdits : sous-location, transport rémunéré de personnes ou de marchandises, courses, remorquage, conduite sous l’effet de l’alcool ou de drogues.</li>
    <li>Il est interdit de fumer à bord. Un forfait de nettoyage peut être facturé.</li>
    <li>Les amendes et contraventions sont à la charge du client.</li>
  </ul>

  <h2>Assurance et incidents</h2>
  <ul>
    <li>Les véhicules sont assurés selon la réglementation camerounaise.</li>
    <li>Tout accident, vol ou dommage est signalé immédiatement à Yamoo Drive et déclaré aux autorités.</li>
    <li>Une franchise s’applique lorsque le client est responsable. Aucune réparation sans l’accord de Yamoo Drive.</li>
  </ul>

  <h2>Annulation</h2>
  <ul>
    <li>Annulation gratuite jusqu’à <?= $r['annulation_gratuite_heures'] ?> heures avant l’heure de prise en charge, depuis la page de la réservation. Un paiement en ligne est alors remboursé.</li>
    <li>Des absences répétées sans prévenir peuvent conduire à refuser de futures réservations.</li>
  </ul>

  <p class="note"><?= icone('info') ?>Ces conditions peuvent évoluer. Une question ? Écrivez-nous sur WhatsApp au <?= e(config('contact.telephone_affiche')) ?>.</p>
</article>
