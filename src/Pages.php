<?php

namespace Yamoo;

use Yamoo\Assistant\Assistant;
use Yamoo\Paiement\NotchPay;
use Yamoo\Paiement\Paiement;

final class Pages
{
    public static function accueil(): void
    {
        $d = Demande::depuis($_GET);
        vue('accueil', [
            'titre' => t('Location de SUV et 4x4 au Cameroun'),
            'description' => t('Louez un SUV Jetour, un 4x4 ou un van avec ou sans chauffeur à Douala, Yaoundé et Bafoussam. Prix exact en ligne, paiement Mobile Money, livraison aux aéroports.'),
            'page' => 'accueil',
            // Lever de rideau seulement si l'accueil est la première page de la visite.
            'entree' => empty($_SESSION['logo_vu']),
            'demande' => $d,
            'cartes' => self::cartes(Flotte::tous(), null),
            'compte' => Flotte::compteParCategorie(),
        ]);
    }

    public static function catalogue(): void
    {
        $d = Demande::depuis($_GET);
        $categorie = isset(config('categories')[$_GET['categorie'] ?? '']) ? $_GET['categorie'] : null;
        $formule = in_array($_GET['formule'] ?? null, ['sans', 'avec'], true) ? $_GET['formule'] : null;
        $avecDates = $d->aDesDates() && $d->valideDates();
        $erreurs = $d->erreurs;
        $cartes = self::cartes(Flotte::filtre(null, $formule), $avecDates ? $d : null, $formule);
        $libres = count(array_filter($cartes, fn ($c) => $c['disponible']));
        vue('catalogue', [
            'titre' => $avecDates ? t('Véhicules du {debut} au {fin}', ['debut' => date_texte($d->debut, 'dMMM'), 'fin' => date_texte($d->fin, 'dMMM')]) : t('Tous nos véhicules'),
            'page' => 'catalogue',
            'demande' => $d,
            'formuleFiltre' => $formule,
            'categorie' => $categorie,
            'cartes' => $cartes,
            'avecDates' => $avecDates,
            'libres' => $libres,
            'erreursRecherche' => $erreurs,
            'compte' => Flotte::compteParCategorie(),
        ]);
    }

    /** Données des cartes de la grille, libres d'abord quand des dates sont choisies. */
    private static function cartes(array $vehicules, ?Demande $d, ?string $formule = null): array
    {
        $cartes = [];
        foreach ($vehicules as $v) {
            $f = Flotte::formuleParDefaut($v, $formule);
            $c = ['v' => $v, 'formule' => $f, 'prix' => $v['tarifs'][$f], 'disponible' => true, 'etat' => null, 'total' => null, 'jours' => null];
            if ($d) {
                $copie = clone $d;
                $copie->vehicule = $v;
                $copie->formule = $f;
                $devis = Devis::calcule($copie);
                $c['total'] = $devis['total'];
                $c['jours'] = $devis['jours'];
                $c['disponible'] = Disponibilite::estLibre($v, $d->debut, $d->fin);
                if (!$c['disponible']) {
                    $libre = Disponibilite::libreA($v, $d->debut, $d->fin);
                    $c['etat'] = $libre ? t('Réservé jusqu’au {date}, {heure}', ['date' => date_texte($libre, 'dMMM'), 'heure' => heure($libre)]) : t('Réservé à ces dates');
                }
                $c['lien'] = url('/vehicules/' . $v['slug'], $copie->versParams());
            } else {
                $retour = Disponibilite::etatActuel($v);
                $c['etat'] = $retour ? t('En location jusqu’au {date}', ['date' => date_texte($retour, 'dMMM')]) : null;
                $c['lien'] = url('/vehicules/' . $v['slug'], ['formule' => $formule]);
            }
            $cartes[] = $c;
        }
        if ($d) {
            usort($cartes, fn ($a, $b) => [!$a['disponible'], $a['total']] <=> [!$b['disponible'], $b['total']]);
        }
        return $cartes;
    }

    public static function vehicule(string $slug): void
    {
        $v = Flotte::trouve($slug) ?? self::introuvable();
        $d = Demande::depuis($_GET, $v);
        $devis = null;
        $libre = null;
        $libreA = null;
        if ($d->aDesDates() && $d->valide()) {
            $devis = Devis::calcule($d);
            $libre = Disponibilite::estLibre($v, $d->debut, $d->fin);
            $libreA = $libre ? null : Disponibilite::libreA($v, $d->debut, $d->fin);
        }
        $semblables = array_slice(array_filter(Flotte::tous(), fn ($x) => $x['categorie'] === $v['categorie'] && $x['slug'] !== $slug), 0, 4);
        if (count($semblables) < 2) {
            $semblables += array_slice(array_filter(Flotte::tous(), fn ($x) => $x['slug'] !== $slug && !isset($semblables[$x['slug']])), 0, 4 - count($semblables));
        }
        vue('vehicule', [
            'titre' => t('{nom} à louer', ['nom' => $v['nom']]),
            'description' => $v['resume'] . ' ' . $v['places_texte'] . ', ' . Flotte::formulesTexte($v) . '. ' . t('À partir de {prix} par jour', ['prix' => fcfa(Flotte::prixDepart($v))]) . '.',
            'page' => 'vehicule',
            'v' => $v,
            'demande' => $d,
            'devis' => $devis,
            'libre' => $libre,
            'libreA' => $libreA,
            'joursPris' => Disponibilite::joursPris($v),
            'semblables' => self::cartes($semblables, null),
            'flash' => self::flash(),
        ]);
    }

    public static function reserver(string $slug): void
    {
        $v = Flotte::trouve($slug) ?? self::introuvable();
        $d = Demande::depuis($_GET, $v);
        if (!$d->valide()) {
            self::flash(reset($d->erreurs) ?: t('Choisissez vos dates pour réserver.'));
            redirige(url('/vehicules/' . $slug, $d->versParams()) . '#reserver');
        }
        if (!Disponibilite::estLibre($v, $d->debut, $d->fin)) {
            self::flash(t('Ce véhicule vient d’être réservé à ces dates. Choisissez d’autres dates ou un autre véhicule.'));
            redirige(url('/vehicules/' . $slug, $d->versParams()) . '#reserver');
        }
        self::formulaire($d, [], []);
    }

    private static function formulaire(Demande $d, array $erreurs, array $ancien): void
    {
        vue('reserver', [
            'titre' => t('Réserver le {nom}', ['nom' => $d->vehicule['court']]),
            'page' => 'reserver',
            'v' => $d->vehicule,
            'demande' => $d,
            'devis' => Devis::calcule($d),
            'erreurs' => $erreurs,
            'ancien' => $ancien,
            'demo' => Paiement::fournisseur()->estDemo(),
        ]);
    }

    public static function enregistrer(string $slug): void
    {
        verifie_csrf();
        $v = Flotte::trouve($slug) ?? self::introuvable();
        $p = $_POST;
        $d = Demande::depuis($p, $v);
        $ancien = array_map(fn ($x) => is_string($x) ? trim($x) : $x, $p);
        $erreurs = [];
        $erreursPieces = [];

        if (!$d->valide()) {
            $erreurs += $d->erreurs;
            self::formulaire($d, $erreurs, $ancien);
            return;
        }

        $nom = trim((string) ($p['nom'] ?? ''));
        if (mb_strlen($nom) < 3) {
            $erreurs['nom'] = t('Écrivez votre nom et votre prénom.');
        }
        $telephone = self::telephone((string) ($p['telephone'] ?? ''));
        if (!$telephone) {
            $erreurs['telephone'] = t('Écrivez un numéro joignable, par exemple 6 72 89 40 84.');
        }
        $email = trim((string) ($p['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = t('Cette adresse e-mail n’est pas valide. Corrigez-la ou laissez le champ vide.');
        }

        $extra = [];
        if ($d->formule === 'avec') {
            $mission = isset(config('missions')[$p['mission'] ?? '']) ? $p['mission'] : 'ville';
            $detail = ['adresse' => mb_substr(trim((string) ($p['adresse'] ?? '')), 0, 200)];
            if ($mission === 'aeroport') {
                $detail['vol'] = strtoupper(mb_substr(trim((string) ($p['vol'] ?? '')), 0, 12));
                $detail['atterrissage'] = preg_match('/^\d{2}:\d{2}$/', $p['atterrissage'] ?? '') ? $p['atterrissage'] : '';
                if ($detail['vol'] === '') {
                    $erreurs['vol'] = t('Indiquez le numéro de vol pour que le chauffeur vous attende.');
                }
                if ($detail['atterrissage'] === '') {
                    $erreurs['atterrissage'] = t('Indiquez l’heure d’atterrissage.');
                }
            }
            if ($mission === 'voyage') {
                $detail['destination'] = mb_substr(trim((string) ($p['destination'] ?? '')), 0, 120);
                if ($detail['destination'] === '') {
                    $erreurs['destination'] = t('Indiquez la destination du voyage.');
                }
            }
            $extra['mission'] = $mission;
            $extra['mission_detail'] = array_filter($detail);
        } else {
            $naissance = Conducteur::date($p['naissance'] ?? null);
            $permis = Conducteur::date($p['permis'] ?? null);
            $erreurs += Conducteur::verifie($naissance, $permis, $d->debut);
            $erreursPieces = Conducteur::verifiePieces($_FILES);
            $erreurs += $erreursPieces;
            $extra['conducteur'] = ['naissance' => $naissance?->format('Y-m-d'), 'permis' => $permis?->format('Y-m-d')];
        }

        $mode = ($p['paiement'] ?? '') === 'remise' ? 'remise' : 'mobile';
        $extra['paiement_mode'] = $mode;
        if ($mode === 'mobile') {
            $operateur = in_array($p['operateur'] ?? '', ['mtn', 'orange'], true) ? $p['operateur'] : null;
            $numero = Paiement::numero((string) ($p['numero'] ?? ''));
            if (!$operateur) {
                $erreurs['operateur'] = t('Choisissez MTN Mobile Money ou Orange Money.');
            }
            if (!$numero) {
                $erreurs['numero'] = t('Écrivez le numéro Mobile Money à 9 chiffres, par exemple 6 72 89 40 84.');
            }
            $extra['operateur'] = $operateur;
            $extra['numero'] = $numero;
        }
        if (empty($p['conditions'])) {
            $erreurs['conditions'] = t('Acceptez les conditions de location pour continuer.');
        }

        if ($erreurs) {
            if ($d->formule === 'sans' && !$erreursPieces) {
                $erreurs['pieces'] = t('Pour votre sécurité, les photos ne sont pas gardées après une erreur : ajoutez-les de nouveau.');
            }
            http_response_code(422);
            self::formulaire($d, $erreurs, $ancien);
            return;
        }

        // Vérification et création dans la même transaction : deux clients ne peuvent pas
        // réserver le même véhicule sur la même période.
        $pdo = Base::pdo();
        $pdo->exec('BEGIN IMMEDIATE');
        if (!Disponibilite::estLibre($v, $d->debut, $d->fin)) {
            $pdo->exec('ROLLBACK');
            self::flash(t('Ce véhicule vient d’être réservé à ces dates. Choisissez d’autres dates ou un autre véhicule.'));
            redirige(url('/vehicules/' . $slug, $d->versParams()) . '#reserver');
        }
        $r = Reservations::cree($d, Devis::calcule($d), ['nom' => $nom, 'telephone' => $telephone, 'email' => $email], $extra);
        $pdo->exec('COMMIT');

        if ($d->formule === 'sans') {
            $pieces = Conducteur::enregistrePieces($_FILES, $r['code']);
            $conducteur = $extra['conducteur'] + ['pieces' => $pieces];
            Reservations::maj($r['id'], ['conducteur' => json_encode($conducteur, JSON_UNESCAPED_UNICODE)], count($pieces, COUNT_RECURSIVE) - count($pieces) . ' pièces reçues.', 'pieces');
        }

        $r = Reservations::parCode($r['code']);
        if ($mode === 'mobile') {
            $erreur = Paiement::lance($r);
            if ($erreur) {
                self::flash($erreur);
            }
            redirige(url('/paiement/' . $r['code'], ['j' => $r['jeton']]));
        }
        redirige(url('/reservation/' . $r['code'], ['j' => $r['jeton']]));
    }

    private static function telephone(string $saisie): ?string
    {
        if ($cm = Paiement::numero($saisie)) {
            return $cm;
        }
        $chiffres = preg_replace('/\D+/', '', $saisie);
        // Numéro étranger (diaspora) : on garde l'indicatif saisi.
        if (str_starts_with(trim($saisie), '+') || str_starts_with($chiffres, '00')) {
            $chiffres = ltrim($chiffres, '0');
            return strlen($chiffres) >= 8 && strlen($chiffres) <= 15 ? '+' . $chiffres : null;
        }
        return null;
    }

    public static function paiement(string $code): void
    {
        $r = Reservations::pourClient($code, $_GET['j'] ?? null) ?? self::introuvable();
        Reservations::expire();
        $r = Paiement::verifie(Reservations::parCode($code));
        if ($r['statut'] !== 'attente_paiement') {
            redirige(url('/reservation/' . $code, ['j' => $r['jeton']]));
        }
        vue('paiement', [
            'titre' => t('Validez le paiement'),
            'page' => 'paiement',
            'r' => $r,
            'demo' => Paiement::fournisseur()->estDemo(),
            'flash' => self::flash(),
        ]);
    }

    public static function relancerPaiement(string $code): void
    {
        verifie_csrf();
        $r = Reservations::pourClient($code, $_POST['j'] ?? null) ?? self::introuvable();
        if ($r['statut'] !== 'attente_paiement') {
            redirige(url('/reservation/' . $code, ['j' => $r['jeton']]));
        }
        if (($_POST['choix'] ?? '') === 'remise') {
            Reservations::maj($r['id'], ['paiement_mode' => 'remise', 'paiement_statut' => 'aucun', 'statut' => 'demande', 'expire_le' => null], 'Le client paiera à la remise des clés.');
            redirige(url('/reservation/' . $code, ['j' => $r['jeton']]));
        }
        $operateur = in_array($_POST['operateur'] ?? '', ['mtn', 'orange'], true) ? $_POST['operateur'] : $r['paiement_operateur'];
        $numero = Paiement::numero((string) ($_POST['numero'] ?? '')) ?? $r['paiement_numero'];
        Reservations::maj($r['id'], [
            'paiement_operateur' => $operateur,
            'paiement_numero' => $numero,
            'expire_le' => maintenant()->modify('+' . config('regles.attente_paiement_minutes') . ' minutes')->format('Y-m-d H:i'),
        ]);
        $erreur = Paiement::lance(Reservations::parCode($code));
        if ($erreur) {
            self::flash($erreur);
        }
        redirige(url('/paiement/' . $code, ['j' => $r['jeton']]));
    }

    public static function reservation(string $code): void
    {
        $r = Reservations::pourClient($code, $_GET['j'] ?? null) ?? self::introuvable();
        Reservations::expire();
        $r = Paiement::verifie(Reservations::parCode($code));
        if ($r['statut'] === 'attente_paiement') {
            redirige(url('/paiement/' . $code, ['j' => $r['jeton']]));
        }
        vue('reservation', [
            'titre' => t('Réservation {code}', ['code' => $r['code']]),
            'page' => 'reservation',
            'r' => $r,
            'flash' => self::flash(),
        ]);
    }

    public static function agenda(string $code): void
    {
        $r = Reservations::pourClient($code, $_GET['j'] ?? null) ?? self::introuvable();
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="yamoo-drive-' . $r['code'] . '.ics"');
        echo Agenda::ics($r);
    }

    public static function annuler(string $code): void
    {
        verifie_csrf();
        $r = Reservations::pourClient($code, $_POST['j'] ?? null) ?? self::introuvable();
        if (!Reservations::annulableParClient($r)) {
            self::flash(t('L’annulation gratuite n’est plus possible pour cette réservation. Écrivez-nous sur WhatsApp.'));
        } else {
            $note = 'Annulée par le client.' . ($r['paiement_statut'] === 'paye' ? ' Remboursement de ' . fcfa($r['total']) . ' à faire.' : '');
            Reservations::maj($r['id'], ['statut' => 'annulee'], $note);
            self::flash(t('Réservation annulée.'));
        }
        redirige(url('/reservation/' . $code, ['j' => $r['jeton']]));
    }

    public static function conditions(): void
    {
        vue('conditions', ['titre' => t('Conditions de location'), 'page' => 'conditions']);
    }

    public static function credits(): void
    {
        vue('credits', ['titre' => t('Crédits photos'), 'page' => 'credits']);
    }

    // Les moteurs de recherche ne lisent robots.txt et sitemap.xml qu'à la racine du site.
    public static function robots(): void
    {
        if (Langue::$code !== 'fr') {
            self::introuvable();
        }
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo PlanDuSite::robots(self::racine(), base());
    }

    public static function planDuSite(): void
    {
        if (Langue::$code !== 'fr') {
            self::introuvable();
        }
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=86400');
        echo PlanDuSite::xml(self::racine());
    }

    private static function racine(): string
    {
        return rtrim(url_absolue('/'), '/');
    }

    public static function apiAssistant(): void
    {
        if (!hash_equals(csrf(), (string) ($_SERVER['HTTP_X_CSRF'] ?? ''))) {
            json(['texte' => t('La page a expiré. Rechargez-la puis reposez votre question.'), 'cartes' => [], 'action' => null], 419);
        }
        $corps = json_decode(file_get_contents('php://input') ?: '[]', true);
        json(Assistant::reponds($corps['messages'] ?? null));
    }

    public static function apiDevis(): void
    {
        $v = Flotte::trouve($_GET['vehicule'] ?? null);
        if (!$v) {
            json(['ok' => false, 'erreurs' => ['vehicule' => t('Véhicule inconnu.')]], 404);
        }
        $d = Demande::depuis($_GET, $v);
        if (!$d->valide()) {
            json(['ok' => false, 'erreurs' => $d->erreurs, 'prix_jour' => fcfa($v['tarifs'][$d->formule] ?? Flotte::prixDepart($v))]);
        }
        $devis = Devis::calcule($d);
        $libre = Disponibilite::estLibre($v, $d->debut, $d->fin);
        $libreA = $libre ? null : Disponibilite::libreA($v, $d->debut, $d->fin);
        json([
            'ok' => true,
            'disponible' => $libre,
            'message' => $libre ? null : ($libreA ? t('Réservé jusqu’au {date} à {heure}. Choisissez d’autres dates.', ['date' => date_texte($libreA, 'EEEEdMMMM'), 'heure' => heure($libreA)]) : t('Déjà réservé à ces dates. Choisissez d’autres dates.')),
            'jours' => $devis['jours'],
            'lignes' => array_values(array_filter([
                [fcfa($devis['prix_jour']) . ' × ' . nb_jours($devis['jours']), fcfa($devis['montant_location'])],
                $devis['frais_rapatriement'] ? [t('Frais de rapatriement'), fcfa($devis['frais_rapatriement'])] : null,
            ])),
            'total' => fcfa($devis['total']),
            'prix_jour' => fcfa($devis['prix_jour']),
            'unite' => t('pour {duree}', ['duree' => nb_jours($devis['jours'])]),
            'caution' => $devis['caution'] ? t('Caution remboursable de {montant} à la remise des clés', ['montant' => fcfa($devis['caution'])]) : null,
            'annulation' => $devis['annulation_jusqua'] ? t('Annulation gratuite jusqu’au {date} à {heure}', ['date' => date_texte($devis['annulation_jusqua'], 'dMMMM'), 'heure' => heure($devis['annulation_jusqua'])]) : null,
            'lien' => url('/reserver/' . $v['slug'], $d->versParams()),
        ]);
    }

    public static function apiPaiement(string $code): void
    {
        $r = Reservations::pourClient($code, $_GET['j'] ?? null);
        if (!$r) {
            json(['erreur' => t('Réservation introuvable.')], 404);
        }
        Reservations::expire();
        $r = Paiement::verifie(Reservations::parCode($code));
        json([
            'statut' => $r['statut'],
            'paiement' => $r['paiement_statut'],
            'suite' => $r['statut'] !== 'attente_paiement' ? url('/reservation/' . $code, ['j' => $r['jeton']]) : null,
        ]);
    }

    public static function webhookNotchPay(): void
    {
        $corps = file_get_contents('php://input') ?: '';
        if (!NotchPay::signatureValide($corps, $_SERVER['HTTP_X_NOTCH_SIGNATURE'] ?? null)) {
            json(['erreur' => 'Signature invalide.'], 403);
        }
        $donnees = json_decode($corps, true) ?: [];
        $reference = $donnees['data']['reference'] ?? null;
        $r = $reference ? Reservations::parReference($reference) : null;
        if ($r) {
            Paiement::verifie($r);
        }
        json(['ok' => true]);
    }

    public static function flash(?string $message = null): ?string
    {
        if ($message !== null) {
            $_SESSION['flash'] = $message;
            return null;
        }
        $m = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $m;
    }

    private static function introuvable(): never
    {
        http_response_code(404);
        vue('erreur', ['titre' => t('Page introuvable'), 'message' => t('Ce véhicule ou cette réservation n’existe pas. Revenez à l’accueil pour choisir un véhicule.')]);
        exit;
    }
}
