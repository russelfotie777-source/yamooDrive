<?php

namespace Yamoo;

// Espace équipe : suivi des réservations, pièces des conducteurs, blocage de dates.
final class Admin
{
    private static function connecte(): bool
    {
        return !empty($_SESSION['equipe']);
    }

    private static function exigeConnexion(): void
    {
        if (!self::connecte()) {
            redirige(url('/equipe'));
        }
    }

    public static function tableau(): void
    {
        if (!self::connecte()) {
            vue('admin/connexion', [
                'titre' => 'Espace équipe',
                'page' => 'equipe',
                'actif' => (bool) config('admin.mot_de_passe'),
                'erreur' => Pages::flash(),
            ], 'admin/gabarit');
            return;
        }
        $statut = isset(Reservations::STATUTS[$_GET['statut'] ?? '']) ? $_GET['statut'] : null;
        $recherche = trim((string) ($_GET['q'] ?? ''));
        vue('admin/reservations', [
            'titre' => 'Réservations',
            'page' => 'equipe',
            'liste' => Reservations::liste($statut, $recherche),
            'compte' => Reservations::compteParStatut(),
            'statut' => $statut,
            'recherche' => $recherche,
            'flash' => Pages::flash(),
        ], 'admin/gabarit');
    }

    public static function connexion(): void
    {
        verifie_csrf();
        $attendu = (string) config('admin.mot_de_passe');
        $saisi = (string) ($_POST['mot_de_passe'] ?? '');
        if ($attendu !== '' && hash_equals($attendu, $saisi)) {
            session_regenerate_id(true);
            $_SESSION['equipe'] = true;
        } else {
            sleep(1);
            Pages::flash('Mot de passe incorrect. Vérifiez-le puis réessayez.');
        }
        redirige(url('/equipe'));
    }

    public static function deconnexion(): void
    {
        verifie_csrf();
        unset($_SESSION['equipe']);
        session_regenerate_id(true);
        redirige(url('/equipe'));
    }

    public static function reservation(string $code): void
    {
        self::exigeConnexion();
        $r = Reservations::parCode($code);
        if (!$r) {
            redirige(url('/equipe'));
        }
        vue('admin/reservation', [
            'titre' => $r['code'],
            'page' => 'equipe',
            'r' => $r,
            'journal' => Reservations::journal($r['id']),
            'flash' => Pages::flash(),
        ], 'admin/gabarit');
    }

    public static function action(string $code): void
    {
        self::exigeConnexion();
        verifie_csrf();
        $r = Reservations::parCode($code);
        if (!$r) {
            redirige(url('/equipe'));
        }
        $action = $_POST['action'] ?? '';
        switch ($action) {
            case 'confirmer':
                Reservations::maj($r['id'], ['statut' => 'confirmee', 'expire_le' => null], 'Réservation confirmée par l’équipe.');
                Pages::flash('Réservation confirmée.');
                break;
            case 'payee':
                Reservations::maj($r['id'], ['paiement_statut' => 'paye', 'statut' => in_array($r['statut'], ['demande', 'attente_paiement'], true) ? 'confirmee' : $r['statut'], 'expire_le' => null], 'Paiement encaissé à l’agence : ' . fcfa($r['total']) . '.', 'paiement');
                Pages::flash('Paiement enregistré.');
                break;
            case 'terminer':
                $releve = trim((string) ($_POST['releve'] ?? ''));
                Reservations::maj($r['id'], ['statut' => 'terminee'], 'Véhicule rendu.' . ($releve !== '' ? ' État des lieux : ' . mb_substr($releve, 0, 500) : ''));
                Pages::flash('Location terminée.');
                break;
            case 'annuler':
                Reservations::maj($r['id'], ['statut' => 'annulee'], 'Annulée par l’équipe.' . ($r['paiement_statut'] === 'paye' ? ' Remboursement de ' . fcfa($r['total']) . ' à faire.' : ''));
                Pages::flash('Réservation annulée.');
                break;
            case 'note':
                $note = trim((string) ($_POST['note'] ?? ''));
                if ($note !== '') {
                    Reservations::note($r['id'], 'note', mb_substr($note, 0, 1000));
                    Pages::flash('Note ajoutée.');
                }
                break;
        }
        redirige(url('/equipe/reservations/' . $code));
    }

    public static function piece(string $code, string $nom): void
    {
        self::exigeConnexion();
        $chemin = Conducteur::chemin($code, $nom);
        if (!$chemin) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . (new \finfo(FILEINFO_MIME_TYPE))->file($chemin));
        header('Content-Disposition: inline; filename="' . $nom . '"');
        header('Cache-Control: private, no-store');
        readfile($chemin);
    }

    public static function flotte(): void
    {
        self::exigeConnexion();
        $q = Base::pdo()->prepare('SELECT * FROM indisponibilites WHERE fin >= ? ORDER BY debut');
        $q->execute([maintenant()->format('Y-m-d H:i')]);
        $blocages = [];
        foreach ($q as $b) {
            $blocages[$b['vehicule']][] = $b;
        }
        $etats = [];
        foreach (Flotte::tous() as $slug => $v) {
            $etats[$slug] = array_values(array_filter(Disponibilite::occupations($slug), fn ($o) => $o['type'] === 'reservation'));
        }
        vue('admin/flotte', [
            'titre' => 'Flotte',
            'page' => 'equipe',
            'blocages' => $blocages,
            'etats' => $etats,
            'flash' => Pages::flash(),
        ], 'admin/gabarit');
    }

    public static function bloquer(): void
    {
        self::exigeConnexion();
        verifie_csrf();
        if (!empty($_POST['supprimer'])) {
            Base::pdo()->prepare('DELETE FROM indisponibilites WHERE id = ?')->execute([(int) $_POST['supprimer']]);
            Pages::flash('Blocage retiré.');
            redirige(url('/equipe/flotte'));
        }
        $v = Flotte::trouve($_POST['vehicule'] ?? null);
        $debut = Conducteur::date($_POST['debut'] ?? null);
        $fin = Conducteur::date($_POST['fin'] ?? null);
        if (!$v || !$debut || !$fin || $fin < $debut) {
            Pages::flash('Choisissez un véhicule et deux dates dans l’ordre.');
            redirige(url('/equipe/flotte'));
        }
        Base::pdo()->prepare('INSERT INTO indisponibilites (vehicule, debut, fin, motif, cree_le) VALUES (?, ?, ?, ?, ?)')->execute([
            $v['slug'],
            $debut->format('Y-m-d 00:00'),
            $fin->modify('+1 day')->format('Y-m-d 00:00'),
            mb_substr(trim((string) ($_POST['motif'] ?? '')), 0, 120) ?: null,
            maintenant()->format('Y-m-d H:i:s'),
        ]);
        Pages::flash($v['court'] . ' bloqué du ' . date_texte($debut, 'dMMM') . ' au ' . date_texte($fin, 'dMMM') . '.');
        redirige(url('/equipe/flotte'));
    }
}
