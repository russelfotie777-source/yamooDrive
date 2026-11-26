<?php
/** @var Yamoo\Demande $demande */
use Yamoo\Flotte;

$r = config('regles');
$age = ['age' => $r['age_minimum'], 'permis' => $r['anciennete_permis_ans']];
$mots = preg_split('/\s+/u', t('Louez un SUV récent, avec ou sans chauffeur'));
$questions = [
    [t('Quelles pièces faut-il pour louer sans chauffeur ?'), t('Le conducteur doit avoir au moins {age} ans et son permis depuis {permis} ans. Préparez une CNI ou un passeport, le permis de conduire (le permis international est accepté pour les non-résidents) et un justificatif de domicile ou de séjour. Vous envoyez les photos dans le formulaire de réservation.', $age)],
    [t('Comment est comptée une journée ?'), t('Une journée correspond à 24 heures à partir de l’heure de prise en charge. Vous avez {minutes} minutes de tolérance au retour ; au-delà, une journée de plus est facturée. Le prix total s’affiche dès que vous choisissez vos dates.', ['minutes' => $r['tolerance_retour_minutes']])],
    [t('Puis-je annuler ma réservation ?'), t('Oui, l’annulation est gratuite jusqu’à {heures} heures avant l’heure de prise en charge. Vous annulez depuis la page de votre réservation, en un clic.', ['heures' => $r['annulation_gratuite_heures']])],
    [t('Comment fonctionne la caution ?'), t('Une caution remboursable est demandée pour la location sans chauffeur. Elle se règle à la remise des clés et vous est rendue au retour, après l’état des lieux. Avec chauffeur, il n’y a pas de caution.')],
    [t('Comment payer ?'), t('En ligne au moment de réserver, par MTN Mobile Money ou Orange Money : vous validez le paiement sur votre téléphone. Vous pouvez aussi payer à la remise des clés, en espèces ou par Mobile Money.')],
    [t('Puis-je rendre le véhicule dans une autre ville ?'), t('Oui. Prenez le véhicule à Douala et rendez-le à Yaoundé ou à Bafoussam, par exemple. Les frais de rapatriement s’ajoutent automatiquement au prix affiché.')],
    [t('Et le carburant ?'), t('Rendez le véhicule avec le même niveau de carburant qu’au départ, sinon la différence est facturée. Avec chauffeur, le carburant des trajets hors de la ville de départ est à votre charge.')],
    [t('Puis-je sortir du Cameroun avec le véhicule ?'), t('Seulement avec un accord écrit de Yamoo Drive. La sous-location, le transport rémunéré de personnes ou de marchandises et le tabac à bord sont interdits.')],
];
$services = [
    ['avion', 'Transfert aéroport', 'Votre chauffeur vous attend à l’arrivée, à Douala ou à Nsimalen, avec une pancarte à votre nom. Donnez-nous le numéro de vol et l’heure d’atterrissage.', 'Bonjour Yamoo Drive, je voudrais un transfert aéroport.'],
    ['alliances', 'Mariages et cérémonies', 'Cortèges de SUV assortis avec chauffeurs pour une dot, un mariage ou des obsèques, à l’horaire convenu.', 'Bonjour Yamoo Drive, je prépare une cérémonie et je voudrais un cortège.'],
    ['mallette', 'Entreprises et délégations', 'Véhicules avec chauffeur à l’heure, à la journée ou sur plusieurs jours pour vos équipes et vos invités.', 'Bonjour Yamoo Drive, je voudrais un devis pour mon entreprise.'],
    ['route', 'Voyages interurbains', 'Kribi, Limbé, Yaoundé, l’Ouest : partez sur la route en SUV récent, avec ou sans chauffeur.', 'Bonjour Yamoo Drive, je prépare un voyage hors de la ville.'],
];
?>
<?php // Après le lever de rideau, le titre se pose quand le rideau commence à s'ouvrir. ?>
<section class="hero conteneur<?= empty($entree) ? ' signature-en-cours' : '' ?>"<?= empty($entree) ? '' : ' data-signature-differee' ?> aria-labelledby="hero-titre">
  <div class="hero-texte">
    <h1 id="hero-titre" class="hero-titre" data-signature>
      <?php foreach ($mots as $mot): ?><span class="mot"><span><?= e($mot) ?></span></span> <?php endforeach ?>
    </h1>
    <p class="hero-sous-titre"><?= e(t('À Douala, Yaoundé et Bafoussam. Livraison aux aéroports de Douala et de Nsimalen, prix exact en ligne, paiement Mobile Money.')) ?></p>
  </div>
  <?php partiel('recherche', ['demande' => $demande]) ?>
  <figure class="hero-photo">
    <picture>
      <source type="image/avif" media="(min-width: 700px)" srcset="<?= asset('img/accueil/t2-milnerton-1280.avif') ?> 1280w, <?= asset('img/accueil/t2-milnerton-1920.avif') ?> 1920w, <?= asset('img/accueil/t2-milnerton-2560.avif') ?> 2560w" sizes="(min-width: 1328px) 1280px, 96vw">
      <source type="image/webp" media="(min-width: 700px)" srcset="<?= asset('img/accueil/t2-milnerton-1280.webp') ?> 1280w, <?= asset('img/accueil/t2-milnerton-1920.webp') ?> 1920w, <?= asset('img/accueil/t2-milnerton-2560.webp') ?> 2560w" sizes="(min-width: 1328px) 1280px, 96vw">
      <source type="image/avif" srcset="<?= asset('img/accueil/t2-milnerton-m640.avif') ?> 640w, <?= asset('img/accueil/t2-milnerton-m960.avif') ?> 960w, <?= asset('img/accueil/t2-milnerton-m1280.avif') ?> 1280w" sizes="92vw">
      <source type="image/webp" srcset="<?= asset('img/accueil/t2-milnerton-m640.webp') ?> 640w, <?= asset('img/accueil/t2-milnerton-m960.webp') ?> 960w, <?= asset('img/accueil/t2-milnerton-m1280.webp') ?> 1280w" sizes="92vw">
      <img src="<?= asset('img/accueil/t2-milnerton-1280.jpg') ?>" alt="<?= e(t('Jetour T2 noir garé sur un parking ensoleillé, palmiers et grand arbre en arrière-plan')) ?>" width="1280" height="640" fetchpriority="high">
    </picture>
    <figcaption><?= e(t('Jetour T2, le modèle phare. Photo Husskeyy,')) ?> <a href="https://commons.wikimedia.org/wiki/File:Jetour_T2_in_Milnerton,_Cape_Town.jpg" rel="noopener" target="_blank">CC BY-SA 4.0</a></figcaption>
  </figure>
  <dl class="reperes">
    <div><dt><?= e(t('Sans chauffeur')) ?></dt><dd><?= h('À partir de <strong>{prix}</strong> par jour', ['prix' => fcfa(Flotte::plancher('sans'))]) ?></dd></div>
    <div><dt><?= e(t('Avec chauffeur')) ?></dt><dd><?= h('À partir de <strong>{prix}</strong> par jour', ['prix' => fcfa(Flotte::plancher('avec'))]) ?></dd></div>
    <div><dt><?= e(t('Annulation gratuite')) ?></dt><dd><?= e(t('Jusqu’à {heures} heures avant le départ', ['heures' => $r['annulation_gratuite_heures']])) ?></dd></div>
    <div><dt><?= e(t('Paiement')) ?></dt><dd><?= e(t('MTN, Orange Money ou à la remise des clés')) ?></dd></div>
  </dl>
</section>

<section class="section conteneur" id="vehicules" aria-labelledby="vehicules-titre">
  <div class="section-entete">
    <div>
      <h2 id="vehicules-titre" class="section-titre"><?= e(t('Nos véhicules')) ?></h2>
      <p class="section-intro"><?= e(t('{n} modèles, du SUV compact au minibus de 15 places. Choisissez vos dates pour voir le prix total.', ['n' => count($cartes)])) ?></p>
    </div>
  </div>
  <div class="filtres" role="group" aria-label="<?= e(t('Filtrer par catégorie')) ?>" data-filtres>
    <a class="filtre" href="<?= url('/vehicules') ?>" aria-pressed="true" data-filtre=""><?= e(t('Tous')) ?> <span><?= nombre(count($cartes)) ?></span></a>
    <?php foreach (config('categories') as $cle => $libelle): ?>
      <a class="filtre" href="<?= url('/vehicules', ['categorie' => $cle]) ?>" aria-pressed="false" data-filtre="<?= e($cle) ?>"><?= e(t($libelle)) ?> <span><?= nombre($compte[$cle] ?? 0) ?></span></a>
    <?php endforeach ?>
  </div>
  <div class="grille-vehicules" data-grille>
    <?php foreach ($cartes as $c) partiel('carte-vehicule', ['c' => $c]) ?>
  </div>
</section>

<section class="section section-formules" id="formules" aria-labelledby="formules-titre">
  <div class="conteneur">
    <h2 id="formules-titre" class="section-titre"><?= e(t('Avec ou sans chauffeur')) ?></h2>
    <div class="formules">
      <article class="formule">
        <h3><?= icone('cle') ?><?= e(t('Sans chauffeur')) ?></h3>
        <p class="formule-prix"><?= h('À partir de <strong>{prix}</strong> par jour', ['prix' => fcfa(Flotte::plancher('sans'))]) ?></p>
        <p><?= e(t('Vous prenez le volant. Idéal pour circuler librement en ville ou partir en week-end.')) ?></p>
        <ul class="liste-coches">
          <li><?= icone('check') ?><?= e(t('Conducteur de {age} ans minimum, permis depuis {permis} ans', $age)) ?></li>
          <li><?= icone('check') ?><?= e(t('CNI ou passeport, justificatif de domicile ou de séjour')) ?></li>
          <li><?= icone('check') ?><?= e(t('Caution remboursable à la remise des clés')) ?></li>
          <li><?= icone('check') ?><?= e(t('Rendu avec le même niveau de carburant')) ?></li>
        </ul>
        <a class="lien-fort" href="<?= url('/vehicules', ['formule' => 'sans']) ?>"><?= e(t('Voir les véhicules sans chauffeur')) ?></a>
      </article>
      <article class="formule">
        <h3><?= icone('volant') ?><?= e(t('Avec chauffeur')) ?></h3>
        <p class="formule-prix"><?= h('À partir de <strong>{prix}</strong> par jour', ['prix' => fcfa(Flotte::plancher('avec'))]) ?></p>
        <p><?= e(t('Un chauffeur Yamoo Drive vous conduit. Pour les rendez-vous, les événements et les transferts.')) ?></p>
        <ul class="liste-coches">
          <li><?= icone('check') ?><?= e(t('Ni permis ni caution à fournir')) ?></li>
          <li><?= icone('check') ?><?= e(t('Chauffeur professionnel qui connaît la ville')) ?></li>
          <li><?= icone('check') ?><?= e(t('Accueil à l’aéroport avec une pancarte à votre nom')) ?></li>
          <li><?= icone('check') ?><?= e(t('Hors de la ville de départ, carburant à votre charge')) ?></li>
        </ul>
        <a class="lien-fort" href="<?= url('/vehicules', ['formule' => 'avec']) ?>"><?= e(t('Voir les véhicules avec chauffeur')) ?></a>
      </article>
    </div>
  </div>
</section>

<section class="section conteneur section-colonnes" id="services" aria-labelledby="services-titre">
  <div class="section-colonne-titre">
    <h2 id="services-titre" class="section-titre"><?= e(t('Nos services')) ?></h2>
    <p class="section-intro"><?= e(t('Pour ces prestations, le prix dépend du programme. Envoyez-nous la date et le lieu : nous répondons avec un devis sur WhatsApp.')) ?></p>
  </div>
  <ul class="services">
    <?php foreach ($services as [$ic, $titreService, $texte, $message]): ?>
      <li class="service">
        <span class="service-icone"><?= icone($ic) ?></span>
        <div>
          <h3><?= e(t($titreService)) ?></h3>
          <p><?= e(t($texte)) ?></p>
        </div>
        <a class="bouton bouton-contour" href="<?= e(lien_whatsapp(t($message))) ?>" rel="noopener" target="_blank"><?= icone('whatsapp') ?><?= e(t('Demander un devis')) ?></a>
      </li>
    <?php endforeach ?>
  </ul>
</section>

<section class="section conteneur" id="agences" aria-labelledby="agences-titre">
  <h2 id="agences-titre" class="section-titre"><?= e(t('Trois villes, deux aéroports')) ?></h2>
  <div class="agences">
    <article class="agence">
      <h3><?= e(t('Douala')) ?></h3>
      <p class="agence-role"><?= e(t('Siège')) ?></p>
      <p><?= icone('lieu') ?><?= e(t('Logpom, carrefour Adem')) ?></p>
      <p><?= icone('avion') ?><?= e(t('Livraison à l’aéroport international de Douala')) ?></p>
    </article>
    <article class="agence">
      <h3><?= e(t('Yaoundé')) ?></h3>
      <p class="agence-role"><?= e(t('Agence')) ?></p>
      <p><?= icone('lieu') ?><?= e(t('Prise en charge à l’agence')) ?></p>
      <p><?= icone('avion') ?><?= e(t('Livraison à l’aéroport de Yaoundé-Nsimalen')) ?></p>
    </article>
    <article class="agence">
      <h3><?= e(t('Bafoussam')) ?></h3>
      <p class="agence-role"><?= e(t('Agence')) ?></p>
      <p><?= icone('lieu') ?><?= e(t('Prise en charge et retour à l’agence')) ?></p>
    </article>
  </div>
  <p class="note"><?= icone('route') ?><?= e(t('Aller simple possible : prenez le véhicule dans une ville et rendez-le dans une autre. Les frais de rapatriement s’ajoutent au prix.')) ?></p>
</section>

<section class="section conteneur section-colonnes" id="questions" aria-labelledby="questions-titre">
  <div class="section-colonne-titre">
    <h2 id="questions-titre" class="section-titre"><?= e(t('Questions fréquentes')) ?></h2>
    <p class="section-intro"><?= e(t('Une autre question ? L’assistant répond tout de suite, l’équipe sur WhatsApp.')) ?></p>
    <div class="actions">
      <button class="bouton bouton-contour" type="button" data-assistant-ouvrir><?= icone('message') ?><?= e(t('Poser une question')) ?></button>
    </div>
  </div>
  <div class="faq">
    <?php foreach ($questions as [$q, $reponse]): ?>
      <details>
        <summary><?= e($q) ?><?= icone('chevron-bas') ?></summary>
        <p><?= e($reponse) ?></p>
      </details>
    <?php endforeach ?>
    <p class="faq-lien"><a href="<?= url('/conditions') ?>"><?= e(t('Lire toutes les conditions de location')) ?></a></p>
  </div>
</section>
