<?php

namespace Yamoo\Assistant;

use Yamoo\Flotte;

// Réponses sans appel externe, quand aucune clé d'API n'est configurée ou que l'API
// ne répond pas : mots-clés, données de la flotte et conditions de location.
final class Simple
{
    private const MODELES = [
        'jetour-t2' => ['t2', 'traveller'],
        'jetour-g700' => ['g700', 'g 700'],
        'jetour-t1' => ['t1'],
        'jetour-x50' => ['x50', 'x 50'],
        'jetour-x70-plus' => ['x70', 'x 70'],
        'jetour-dashing' => ['dashing'],
        'jmc-vigus' => ['vigus', 'pick-up', 'pickup', 'pick up', 'jmc'],
        'toyota-prado' => ['prado'],
        'toyota-land-cruiser-300' => ['land cruiser 300', 'lc300', 'lc 300', 'v8'],
        'mercedes-classe-e' => ['classe e', 'class e', 'mercedes e'],
        'toyota-camry' => ['camry'],
        'mercedes-classe-v' => ['classe v', 'class v', 'vito'],
        'toyota-hiace' => ['hiace', 'minibus', 'bus'],
    ];

    public function reponds(array $historique): array
    {
        $question = end($historique)['texte'] ?? '';
        $q = self::normalise($question);
        $outils = new Outils();
        $tel = config('contact.telephone_affiche');
        $r = config('regles');

        $formule = str_contains($q, 'sans chauffeur') ? 'sans' : (str_contains($q, 'avec chauffeur') || str_contains($q, 'chauffeur') && !str_contains($q, 'sans') ? 'avec' : null);
        $places = preg_match('/(\d{1,2})\s*(places|personnes|passagers)/', $q, $m) ? (int) $m[1] : 0;
        $lieu = match (true) {
            str_contains($q, 'nsimalen') => 'yaounde-aeroport',
            str_contains($q, 'yaounde') => 'yaounde-agence',
            str_contains($q, 'bafoussam') => 'bafoussam-agence',
            default => null,
        };
        $modele = null;
        foreach (self::MODELES as $slug => $mots) {
            foreach ($mots as $mot) {
                if (preg_match('/\b' . preg_quote($mot, '/') . '\b/', $q)) {
                    $modele = $slug;
                    break 2;
                }
            }
        }

        $a = fn (string $t) => ['texte' => $t, 'cartes' => $outils->cartes, 'action' => $outils->action];
        [$debut, $fin, $quand] = self::periode($q);

        if (self::contient($q, ['humain', 'robot', ' ia', 'intelligence', 'personne reelle'])) {
            return $a("Je suis l’assistant automatique du site. Pour parler à l’équipe Yamoo Drive, écrivez sur WhatsApp au $tel.");
        }
        if ($modele && $debut) {
            $resultat = $outils->devis(['vehicule' => $modele, 'formule' => $formule, 'lieu_depart' => $lieu, 'debut' => $debut, 'fin' => $fin], true);
            if (!isset($resultat['erreur'])) {
                return $a($resultat['disponible']
                    ? "Le {$resultat['vehicule']} est libre $quand. Total : **{$resultat['total']}** pour " . pluriel($resultat['jours'], 'jour', 'jours') . ", {$resultat['formule']}. Touchez « Continuer la réservation » pour finir."
                    : "Le {$resultat['vehicule']} est déjà réservé $quand" . (isset($resultat['libre_a_partir_de']) ? ', il est libre à partir du ' . date_fr($resultat['libre_a_partir_de'], 'EEEE d MMMM') . ' à ' . heure($resultat['libre_a_partir_de']) : '') . '. Demandez-moi les véhicules libres à ces dates.');
            }
        }
        if ($modele) {
            $v = Flotte::trouve($modele);
            $outils->fiche(['vehicule' => $modele]);
            $prix = implode(' et ', array_map(
                fn ($f, $p) => '**' . fcfa($p) . '** par jour ' . ($f === 'sans' ? 'sans chauffeur' : 'avec chauffeur'),
                array_keys($v['tarifs']),
                $v['tarifs']
            ));
            return $a("{$v['nom']} : {$v['type']}, {$v['places_texte']}. {$v['resume']} Comptez $prix. Ouvrez la fiche pour voir les dates libres et réserver.");
        }
        if (self::contient($q, ['annul', 'rembours'])) {
            return $a("L’annulation est gratuite jusqu’à **{$r['annulation_gratuite_heures']} heures** avant l’heure de prise en charge. Vous pouvez annuler depuis la page de votre réservation.");
        }
        if (self::contient($q, ['caution', 'depot', 'garantie'])) {
            return $a('Une caution remboursable est demandée pour la location sans chauffeur. Elle se règle à la remise des clés et vous est rendue au retour, après l’état des lieux. Avec chauffeur, il n’y a pas de caution.');
        }
        if (self::contient($q, ['permis', 'age', 'piece', 'document', 'cni', 'passeport', 'papier', 'condition'])) {
            return $a("Pour louer sans chauffeur :\n- avoir au moins **{$r['age_minimum']} ans**\n- avoir le permis depuis **{$r['anciennete_permis_ans']} ans** (permis international accepté)\n- une CNI ou un passeport et un justificatif de domicile ou de séjour\nVous envoyez les photos dans le formulaire. Avec chauffeur, aucun permis n’est demandé.");
        }
        if (self::contient($q, ['aeroport', 'vol', 'atterri', 'avion'])) {
            return $a('Nous livrons le véhicule aux aéroports de Douala et de Yaoundé-Nsimalen. Pour un transfert avec chauffeur, il vous attend avec une pancarte à votre nom : indiquez le numéro de vol et l’heure d’atterrissage dans la réservation.');
        }
        if (self::contient($q, ['mariage', 'dot', 'ceremonie', 'cortege', 'obseque', 'deuil'])) {
            $outils->chercher(['categorie' => 'prestige']);
            return $a("Pour un mariage, une dot ou des obsèques, nous préparons un cortège de SUV assortis avec chauffeurs. Le prix dépend du nombre de véhicules et des horaires : envoyez la date et le lieu sur WhatsApp au $tel pour un devis.");
        }
        if (self::contient($q, ['entreprise', 'delegation', 'seminaire', 'societe', 'mission'])) {
            return $a("Pour une entreprise ou une délégation, nous mettons des véhicules avec chauffeur à disposition à l’heure, à la journée ou sur plusieurs jours. Écrivez-nous sur WhatsApp au $tel avec vos dates et le nombre de personnes.");
        }
        if (self::contient($q, ['paie', 'payer', 'paiement', 'mobile money', 'momo', 'orange', 'mtn', 'especes'])) {
            return $a('Vous pouvez payer en ligne par **MTN Mobile Money** ou **Orange Money** au moment de réserver : vous validez sur votre téléphone. Vous pouvez aussi payer à la remise des clés, en espèces ou par Mobile Money.');
        }
        if (self::contient($q, ['carburant', 'essence', 'gasoil', 'plein'])) {
            return $a('Rendez le véhicule avec le même niveau de carburant qu’au départ, sinon la différence est facturée. Avec chauffeur, le carburant des trajets hors de la ville de départ est à votre charge.');
        }
        if (self::contient($q, ['agence', 'adresse', 'ou etes', 'situe', 'localis', 'trouver'])) {
            return $a('Le siège est à **Douala, Logpom, carrefour Adem**. Nous sommes aussi à Yaoundé et à Bafoussam, et nous livrons aux aéroports de Douala et de Nsimalen. Vous pouvez rendre le véhicule dans une autre ville : des frais de rapatriement s’ajoutent au prix.');
        }
        if ($debut) {
            $libres = $outils->chercher(['formule' => $formule, 'places_min' => $places, 'lieu' => $lieu, 'debut' => $debut, 'fin' => $fin])['libres'];
            return $a($libres
                ? 'Pour ' . $quand . ', ' . pluriel($libres, 'véhicule est libre', 'véhicules sont libres') . ($places ? " avec $places places ou plus" : '') . '. Voici les moins chers, avec le prix total. Touchez un véhicule pour réserver.'
                : "Aucun véhicule n’est libre $quand. Essayez d’autres dates ou écrivez-nous sur WhatsApp au $tel.");
        }
        if (!$places && self::contient($q, ['kribi', 'limbe', 'voyage', 'interurbain', 'ouest', 'route'])) {
            $outils->chercher(['categorie' => 'suv', 'formule' => $formule]);
            return $a('Pour la route vers Kribi, Limbé, Yaoundé ou l’Ouest, nos SUV Jetour sont à l’aise, avec ou sans chauffeur. Voici les modèles et leurs prix par jour.');
        }
        if ($places || $formule || $lieu || self::contient($q, ['dispo', 'libre', 'vehicule', 'voiture', 'liste', 'quels', 'quel', 'louer', 'location', 'moins cher', 'prix', 'tarif', 'combien'])) {
            $outils->chercher(['formule' => $formule, 'places_min' => $places, 'lieu' => $lieu]);
            $n = count($outils->cartes);
            $texte = $n
                ? 'Voici ' . ($n > 1 ? "$n véhicules" : 'le véhicule') . ($formule === 'sans' ? ' à conduire vous-même' : ($formule === 'avec' ? ' avec chauffeur' : '')) . ($places ? " de $places places ou plus" : '') . '. Sans chauffeur à partir de **30 000 FCFA** par jour, avec chauffeur à partir de **40 000 FCFA**. Choisissez vos dates sur la fiche pour voir le prix total.'
                : 'Aucun véhicule ne correspond. Modifiez votre demande ou écrivez-nous sur WhatsApp.';
            return $a($texte);
        }
        if (self::contient($q, ['bonjour', 'bonsoir', 'salut', 'hello', 'merci'])) {
            return $a(str_contains($q, 'merci')
                ? 'Avec plaisir. Bonne route avec Yamoo Drive.'
                : 'Bonjour. Je peux vous montrer les véhicules libres, donner un prix ou expliquer les conditions. Que cherchez-vous ?');
        }
        return $a("Je n’ai pas compris. Essayez par exemple « un 7 places sans chauffeur » ou « prix du Prado », ou écrivez à l’équipe sur WhatsApp au $tel.");
    }

    // « ce week-end », « samedi », « demain » : période par défaut de 9 h à 9 h ou 18 h.
    private static function periode(string $q): array
    {
        $m = maintenant();
        $jours = ['lundi' => 1, 'mardi' => 2, 'mercredi' => 3, 'jeudi' => 4, 'vendredi' => 5, 'samedi' => 6, 'dimanche' => 7];
        if (str_contains($q, 'week-end') || str_contains($q, 'weekend') || str_contains($q, 'week end')) {
            $samedi = $m->modify((int) $m->format('N') === 6 && (int) $m->format('H') < 7 ? 'today' : 'next saturday')->setTime(9, 0);
            return [$samedi->format('Y-m-d\TH:i'), $samedi->modify('+1 day')->setTime(18, 0)->format('Y-m-d\TH:i'), 'ce week-end (' . date_fr($samedi, 'd') . '–' . date_fr($samedi->modify('+1 day'), 'd MMMM') . ')'];
        }
        if (str_contains($q, 'demain')) {
            $d = $m->modify('tomorrow')->setTime(9, 0);
            return [$d->format('Y-m-d\TH:i'), $d->modify('+1 day')->format('Y-m-d\TH:i'), 'demain (' . date_fr($d, 'EEEE d MMMM') . ')'];
        }
        foreach ($jours as $nom => $n) {
            if (preg_match('/\b' . $nom . '\b/', $q)) {
                $d = $m->modify('next ' . ['', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'][$n])->setTime(9, 0);
                return [$d->format('Y-m-d\TH:i'), $d->modify('+1 day')->format('Y-m-d\TH:i'), date_fr($d, 'EEEE d MMMM')];
            }
        }
        return [null, null, null];
    }

    private static function contient(string $q, array $mots): bool
    {
        foreach ($mots as $mot) {
            if (str_contains($q, $mot)) {
                return true;
            }
        }
        return false;
    }

    private static function normalise(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');
        $t = strtr($t, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ù' => 'u', 'û' => 'u', 'ç' => 'c', '’' => "'"]);
        return ' ' . preg_replace('/\s+/', ' ', $t) . ' ';
    }
}
