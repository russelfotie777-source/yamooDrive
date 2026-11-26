<?php

namespace Yamoo\Assistant;

use Yamoo\Flotte;

// Réponses sans appel externe, quand aucune clé d'API n'est configurée ou que l'API
// ne répond pas : mots-clés dans les six langues du site, données de la flotte et
// conditions de location. Les réponses sont traduites comme le reste du site.
final class Simple
{
    private const MODELES = [
        'jetour-t2' => ['t2', 'traveller'],
        'jetour-g700' => ['g700', 'g 700'],
        'jetour-t1' => ['t1'],
        'jetour-x50' => ['x50', 'x 50'],
        'jetour-x70-plus' => ['x70', 'x 70'],
        'jetour-dashing' => ['dashing'],
        'jmc-vigus' => ['vigus', 'pick up', 'pickup', 'jmc', '皮卡'],
        'toyota-prado' => ['prado', '普拉多', 'برادو'],
        'toyota-land-cruiser-300' => ['land cruiser 300', 'lc300', 'lc 300', 'v8', '兰德酷路泽'],
        'mercedes-classe-e' => ['classe e', 'class e', 'clase e', 'e klasse', 'mercedes e'],
        'toyota-camry' => ['camry', '凯美瑞', 'كامري'],
        'mercedes-classe-v' => ['classe v', 'class v', 'clase v', 'v klasse', 'vito'],
        'toyota-hiace' => ['hiace', 'minibus', 'bus', '海狮', 'حافلة'],
    ];

    // Mots-clés normalisés (minuscules, sans accents). Entourés d'espaces : mot entier.
    private const MOTS = [
        'humain' => [' humain', 'robot', ' ia ', 'intelligence artificielle', 'personne reelle', 'human', ' ai ', ' bot ', 'real person', 'humano', 'persona real', 'mensch', 'roboter', ' ki ', 'echte person', '人工', '机器人', '真人', 'روبوت', 'انسان', 'بشري'],
        'sans' => ['sans chauffeur', 'without driver', 'without a driver', 'self drive', 'sin conductor', 'sin chofer', 'ohne fahrer', 'selbst fahren', '不带司机', '自驾', 'بدون سائق'],
        'avec' => ['chauffeur', 'driver', 'conductor', 'chofer', 'fahrer', '司机', 'سائق'],
        'annulation' => ['annul', 'rembours', 'cancel', 'refund', 'reembols', 'storn', 'erstatt', '取消', '退款', 'الغاء', 'إلغاء', 'استرداد'],
        'caution' => ['caution', ' depot', 'garantie', 'deposit', 'deposito', 'fianza', 'kaution', '押金', 'تامين', 'تأمين', 'عربون'],
        'pieces' => ['permis', ' age ', 'piece', 'document', ' cni ', 'passeport', 'papier', 'condition', 'licence', 'license', 'passport', 'requirement', ' id ', 'licencia', 'carnet', 'pasaporte', 'requisito', ' edad', 'fuhrerschein', 'ausweis', 'reisepass', 'voraussetzung', ' alter ', '驾照', '证件', '护照', '身份证', '年龄', '条件', 'رخصة', 'وثائق', 'مستندات', 'جواز', 'هوية', 'شروط'],
        'aeroport' => ['aeroport', ' vol ', 'atterri', 'avion', 'airport', 'flight', 'landing', 'aeropuerto', 'vuelo', 'flughafen', ' flug', 'landung', '机场', '航班', '飞机', 'مطار', 'طائرة'],
        'ceremonie' => ['mariage', ' dot ', 'ceremonie', 'cortege', 'obseque', 'deuil', 'wedding', 'ceremony', 'funeral', 'convoy', 'boda', 'ceremonia', 'cortejo', 'hochzeit', 'zeremonie', 'beerdigung', 'trauerfeier', '婚礼', '仪式', '葬礼', '车队', 'زفاف', 'عرس', 'حفل', 'جنازة', 'موكب'],
        'entreprise' => ['entreprise', 'delegation', 'seminaire', 'societe', ' mission', 'company', 'business', 'corporate', 'seminar', 'empresa', 'delegacion', 'seminario', ' firma', 'unternehmen', '公司', '企业', '代表团', 'شركة', 'وفد', 'ندوة'],
        'paiement' => [' paie', 'payer', 'paiement', 'mobile money', 'momo', 'orange', ' mtn', 'especes', ' pay', 'cash', ' pago', 'pagar', 'efectivo', 'zahl', 'bezahl', '付款', '支付', '现金', 'دفع', 'نقد'],
        'carburant' => ['carburant', 'essence', 'gasoil', ' plein', 'fuel', 'petrol', 'diesel', 'combustible', 'gasolina', 'kraftstoff', 'benzin', ' tank', '油', 'وقود', 'بنزين'],
        'agence' => ['agence', 'adresse', 'ou etes', 'situe', 'localis', 'agency', 'office', 'address', 'where are', 'agencia', 'oficina', 'direccion', 'donde', 'filiale', 'standort', ' wo sind', '门店', '地址', '在哪', 'وكالة', 'مكتب', 'عنوان', 'اين', 'أين'],
        'voyage' => ['kribi', 'limbe', 'voyage', 'interurbain', ' ouest', ' route', ' trip', 'travel', ' road', 'viaje', 'carretera', ' reise', '旅行', '长途', 'سفر'],
        'liste' => ['dispo', ' libre', 'vehicule', 'voiture', 'liste', ' quel', 'louer', 'location', 'moins cher', ' prix', 'tarif', 'combien', 'available', ' free', ' car', 'vehicle', ' list', 'which', ' rent', 'cheap', 'price', ' cost', 'how much', 'disponible', ' coche', 'alquil', 'barato', 'precio', 'cuanto', 'verfugbar', ' frei', ' auto', 'fahrzeug', ' miet', 'gunstig', 'preis', 'kostet', 'wie viel', '车', '可用', '租', '便宜', '价格', '多少', 'سيارة', 'متاح', 'متوفر', 'ايجار', 'إيجار', 'استئجار', 'سعر', ' كم'],
        'merci' => ['merci', 'thank', 'gracias', 'danke', '谢谢', 'شكرا'],
        'bonjour' => ['bonjour', 'bonsoir', 'salut', 'hello', ' hi ', 'hola', 'hallo', 'guten tag', 'guten morgen', '你好', 'مرحبا', 'السلام'],
        'weekend' => ['week end', 'weekend', 'fin de semana', 'wochenende', '周末', 'نهاية الاسبوع', 'نهاية الأسبوع'],
        'demain' => ['demain', 'tomorrow', 'manana', 'morgen', '明天', 'غدا'],
    ];

    private const JOURS = [
        1 => ['lundi', 'monday', 'lunes', 'montag', '周一', '星期一', 'الاثنين'],
        2 => ['mardi', 'tuesday', 'martes', 'dienstag', '周二', '星期二', 'الثلاثاء'],
        3 => ['mercredi', 'wednesday', 'miercoles', 'mittwoch', '周三', '星期三', 'الاربعاء', 'الأربعاء'],
        4 => ['jeudi', 'thursday', 'jueves', 'donnerstag', '周四', '星期四', 'الخميس'],
        5 => ['vendredi', 'friday', 'viernes', 'freitag', '周五', '星期五', 'الجمعة'],
        6 => ['samedi', 'saturday', 'sabado', 'samstag', '周六', '星期六', 'السبت'],
        7 => ['dimanche', 'sunday', 'domingo', 'sonntag', '周日', '星期日', '星期天', 'الاحد', 'الأحد'],
    ];

    public function reponds(array $historique): array
    {
        $q = self::normalise(end($historique)['texte'] ?? '');
        $outils = new Outils();
        $tel = config('contact.telephone_affiche');
        $r = config('regles');
        $a = fn (string $texte) => ['texte' => $texte, 'cartes' => $outils->cartes, 'action' => $outils->action];

        $formule = self::contient($q, 'sans') ? 'sans' : (self::contient($q, 'avec') ? 'avec' : null);
        $places = preg_match('/(\d{1,2})\s*(places|personnes|passagers|seats?|seater|people|passengers|plazas|personas|pasajeros|sitze|sitzer|personen|座|人|مقاعد|ركاب|اشخاص)/u', $q, $m) ? (int) $m[1] : 0;
        $lieu = match (true) {
            str_contains($q, 'nsimalen') => 'yaounde-aeroport',
            str_contains($q, 'yaounde') || str_contains($q, '雅温得') || str_contains($q, 'ياوندي') => 'yaounde-agence',
            str_contains($q, 'bafoussam') || str_contains($q, 'بافوسام') => 'bafoussam-agence',
            default => null,
        };
        $modele = null;
        foreach (self::MODELES as $slug => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($q, preg_match('/^[a-z0-9 ]+$/', $mot) ? " $mot " : $mot)) {
                    $modele = $slug;
                    break 2;
                }
            }
        }
        [$debut, $fin, $quand] = self::periode($q);

        if (self::contient($q, 'humain')) {
            return $a(t('Je suis l’assistant automatique du site. Pour parler à l’équipe Yamoo Drive, écrivez sur WhatsApp au {numero}.', ['numero' => $tel]));
        }
        if ($modele && $debut) {
            $res = $outils->devis(['vehicule' => $modele, 'formule' => $formule, 'lieu_depart' => $lieu, 'debut' => $debut, 'fin' => $fin], true);
            if (!isset($res['erreur'])) {
                if ($res['disponible']) {
                    return $a(t('Le {vehicule} est libre {quand}. Total : **{total}** pour {duree}, {formule}. Touchez le bouton ci-dessous pour finir la réservation.', [
                        'vehicule' => $res['vehicule'], 'quand' => $quand, 'total' => $res['total'], 'duree' => nb_jours($res['jours']), 'formule' => $res['formule'],
                    ]));
                }
                $texte = t('Le {vehicule} est déjà réservé {quand}.', ['vehicule' => $res['vehicule'], 'quand' => $quand]);
                if (isset($res['libre_a_partir_de'])) {
                    $texte .= ' ' . t('Il est libre à partir du {date} à {heure}.', ['date' => date_texte($res['libre_a_partir_de'], 'EEEEdMMMM'), 'heure' => heure($res['libre_a_partir_de'])]);
                }
                return $a($texte . ' ' . t('Demandez-moi les véhicules libres à ces dates.'));
            }
        }
        if ($modele) {
            $v = Flotte::trouve($modele);
            $outils->fiche(['vehicule' => $modele]);
            $prix = implode(' ' . t('et') . ' ', array_map(
                fn ($f, $p) => $f === 'sans'
                    ? t('**{montant}** par jour sans chauffeur', ['montant' => fcfa($p)])
                    : t('**{montant}** par jour avec chauffeur', ['montant' => fcfa($p)]),
                array_keys($v['tarifs']),
                $v['tarifs']
            ));
            return $a("{$v['nom']} : {$v['type']}, {$v['places_texte']}. {$v['resume']} " . t('Comptez {prix}.', ['prix' => $prix]) . ' ' . t('Ouvrez la fiche pour voir les dates libres et réserver.'));
        }
        if (self::contient($q, 'annulation')) {
            return $a(t('L’annulation est gratuite jusqu’à **{heures} heures** avant l’heure de prise en charge. Vous pouvez annuler depuis la page de votre réservation.', ['heures' => $r['annulation_gratuite_heures']]));
        }
        if (self::contient($q, 'caution')) {
            return $a(t('Une caution remboursable est demandée pour la location sans chauffeur. Elle se règle à la remise des clés et vous est rendue au retour, après l’état des lieux. Avec chauffeur, il n’y a pas de caution.'));
        }
        if (self::contient($q, 'pieces')) {
            return $a(t("Pour louer sans chauffeur :\n- avoir au moins **{age} ans**\n- avoir le permis depuis **{permis} ans** (permis international accepté)\n- une CNI ou un passeport et un justificatif de domicile ou de séjour\nVous envoyez les photos dans le formulaire. Avec chauffeur, aucun permis n’est demandé.", ['age' => $r['age_minimum'], 'permis' => $r['anciennete_permis_ans']]));
        }
        if (self::contient($q, 'aeroport')) {
            return $a(t('Nous livrons le véhicule aux aéroports de Douala et de Yaoundé-Nsimalen. Pour un transfert avec chauffeur, il vous attend avec une pancarte à votre nom : indiquez le numéro de vol et l’heure d’atterrissage dans la réservation.'));
        }
        if (self::contient($q, 'ceremonie')) {
            $outils->chercher(['categorie' => 'prestige']);
            return $a(t('Pour un mariage, une dot ou des obsèques, nous préparons un cortège de SUV assortis avec chauffeurs. Le prix dépend du nombre de véhicules et des horaires : envoyez la date et le lieu sur WhatsApp au {numero} pour un devis.', ['numero' => $tel]));
        }
        if (self::contient($q, 'entreprise')) {
            return $a(t('Pour une entreprise ou une délégation, nous mettons des véhicules avec chauffeur à disposition à l’heure, à la journée ou sur plusieurs jours. Écrivez-nous sur WhatsApp au {numero} avec vos dates et le nombre de personnes.', ['numero' => $tel]));
        }
        if (self::contient($q, 'paiement')) {
            return $a(t('Vous pouvez payer en ligne par **MTN Mobile Money** ou **Orange Money** au moment de réserver : vous validez sur votre téléphone. Vous pouvez aussi payer à la remise des clés, en espèces ou par Mobile Money.'));
        }
        if (self::contient($q, 'carburant')) {
            return $a(t('Rendez le véhicule avec le même niveau de carburant qu’au départ, sinon la différence est facturée. Avec chauffeur, le carburant des trajets hors de la ville de départ est à votre charge.'));
        }
        if (self::contient($q, 'agence')) {
            return $a(t('Le siège est à **Douala, Logpom, carrefour Adem**. Nous sommes aussi à Yaoundé et à Bafoussam, et nous livrons aux aéroports de Douala et de Nsimalen. Vous pouvez rendre le véhicule dans une autre ville : des frais de rapatriement s’ajoutent au prix.'));
        }
        if ($debut) {
            $res = $outils->chercher(['formule' => $formule, 'places_min' => $places, 'lieu' => $lieu, 'debut' => $debut, 'fin' => $fin]);
            if (isset($res['erreur_dates'])) {
                return $a($res['erreur_dates']);
            }
            $libres = $res['libres'];
            if (!$libres) {
                return $a(t('Aucun véhicule n’est libre {quand}. Essayez d’autres dates ou écrivez-nous sur WhatsApp au {numero}.', ['quand' => $quand, 'numero' => $tel]));
            }
            return $a(t('Pour {quand}, {libres, plural, one {# véhicule est libre} other {# véhicules sont libres}}.', ['quand' => $quand, 'libres' => $libres])
                . ($places ? ' ' . t('Avec {places} places ou plus.', ['places' => $places]) : '')
                . ' ' . t('Voici les moins chers, avec le prix total. Touchez un véhicule pour réserver.'));
        }
        if (!$places && self::contient($q, 'voyage')) {
            $outils->chercher(['categorie' => 'suv', 'formule' => $formule]);
            return $a(t('Pour la route vers Kribi, Limbé, Yaoundé ou l’Ouest, nos SUV Jetour sont à l’aise, avec ou sans chauffeur. Voici les modèles et leurs prix par jour.'));
        }
        if ($places || $formule || $lieu || self::contient($q, 'liste')) {
            $res = $outils->chercher(['formule' => $formule, 'places_min' => $places, 'lieu' => $lieu]);
            if (!$outils->cartes) {
                return $a(t('Aucun véhicule ne correspond. Modifiez votre demande ou écrivez-nous sur WhatsApp.'));
            }
            // Le premier prix est celui des véhicules trouvés, dans la formule demandée : celui
            // de toute la flotte tromperait le client qui cherche un 7 places avec chauffeur.
            $trouves = array_map(fn ($l) => Flotte::trouve($l['vehicule']), $res['vehicules']);
            $sans = $formule === 'avec' ? null : Flotte::plancher('sans', $trouves);
            $avec = $formule === 'sans' ? null : Flotte::plancher('avec', $trouves);
            return $a(match (true) {
                $sans !== null && $avec !== null => t('Voici les véhicules qui correspondent. Sans chauffeur à partir de **{sans}** par jour, avec chauffeur à partir de **{avec}**. Choisissez vos dates sur la fiche pour voir le prix total.', ['sans' => fcfa($sans), 'avec' => fcfa($avec)]),
                $sans !== null => t('Voici les véhicules qui correspondent, sans chauffeur à partir de **{prix}** par jour. Choisissez vos dates sur la fiche pour voir le prix total.', ['prix' => fcfa($sans)]),
                default => t('Voici les véhicules qui correspondent, avec chauffeur à partir de **{prix}** par jour. Choisissez vos dates sur la fiche pour voir le prix total.', ['prix' => fcfa($avec)]),
            });
        }
        if (self::contient($q, 'merci')) {
            return $a(t('Avec plaisir. Bonne route avec Yamoo Drive.'));
        }
        if (self::contient($q, 'bonjour')) {
            return $a(t('Bonjour. Je peux vous montrer les véhicules libres, donner un prix ou expliquer les conditions. Que cherchez-vous ?'));
        }
        return $a(t('Je n’ai pas compris. Essayez par exemple « un 7 places sans chauffeur » ou « prix du Prado », ou écrivez à l’équipe sur WhatsApp au {numero}.', ['numero' => $tel]));
    }

    // « ce week-end », « samedi », « demain » dans les six langues : de 9 h à 9 h, ou à 18 h le dimanche.
    private static function periode(string $q): array
    {
        $m = maintenant();
        // « Guten Morgen » salue, il ne demande pas un véhicule pour demain (« morgen »).
        $q = str_replace(' guten morgen ', ' ', $q);
        if (self::contient($q, 'weekend')) {
            $samedi = $m->modify((int) $m->format('N') === 6 && (int) $m->format('H') < 7 ? 'today' : 'next saturday')->setTime(9, 0);
            $dimanche = $samedi->modify('+1 day')->setTime(18, 0);
            return [$samedi->format('Y-m-d\TH:i'), $dimanche->format('Y-m-d\TH:i'), t('ce week-end ({debut}–{fin})', ['debut' => date_texte($samedi, 'd'), 'fin' => date_texte($dimanche, 'dMMMM')])];
        }
        if (self::contient($q, 'demain')) {
            $d = $m->modify('tomorrow')->setTime(9, 0);
            return [$d->format('Y-m-d\TH:i'), $d->modify('+1 day')->format('Y-m-d\TH:i'), t('demain ({date})', ['date' => date_texte($d, 'EEEEdMMMM')])];
        }
        $anglais = ['', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach (self::JOURS as $n => $noms) {
            foreach ($noms as $nom) {
                if (str_contains($q, preg_match('/^[a-z]+$/', $nom) ? " $nom" : $nom)) {
                    $d = $m->modify('next ' . $anglais[$n])->setTime(9, 0);
                    return [$d->format('Y-m-d\TH:i'), $d->modify('+1 day')->format('Y-m-d\TH:i'), date_texte($d, 'EEEEdMMMM')];
                }
            }
        }
        return [null, null, null];
    }

    private static function contient(string $q, string $groupe): bool
    {
        foreach (self::MOTS[$groupe] as $mot) {
            if (str_contains($q, $mot)) {
                return true;
            }
        }
        return false;
    }

    // Minuscules, accents latins retirés, ponctuation remplacée par des espaces.
    private static function normalise(string $t): string
    {
        $t = mb_strtolower($t, 'UTF-8');
        $t = \Normalizer::normalize($t, \Normalizer::FORM_D);
        $t = preg_replace('/(?<=[a-z])\p{Mn}+/u', '', $t);
        $t = \Normalizer::normalize($t, \Normalizer::FORM_C);
        $t = strtr($t, ['ß' => 'ss', '-' => ' ']);
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t);
        return ' ' . trim($t) . ' ';
    }
}
