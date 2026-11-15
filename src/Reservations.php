<?php

namespace Yamoo;

use DateTimeImmutable;

final class Reservations
{
    public const STATUTS = [
        'attente_paiement' => 'En attente du paiement',
        'demande' => 'À confirmer',
        'confirmee' => 'Confirmée',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
        'expiree' => 'Paiement non reçu',
    ];

    public static function cree(Demande $d, array $devis, array $client, array $extra): array
    {
        $pdo = Base::pdo();
        $maintenant = maintenant()->format('Y-m-d H:i:s');
        $mobile = $extra['paiement_mode'] === 'mobile';
        $donnees = [
            'code' => self::nouveauCode(),
            'jeton' => bin2hex(random_bytes(16)),
            'vehicule' => $d->vehicule['slug'],
            'formule' => $d->formule,
            'lieu_depart' => $d->lieuDepart,
            'lieu_retour' => $d->lieuRetour,
            'debut' => $d->debut->format('Y-m-d H:i'),
            'fin' => $d->fin->format('Y-m-d H:i'),
            'jours' => $devis['jours'],
            'prix_jour' => $devis['prix_jour'],
            'montant_location' => $devis['montant_location'],
            'frais_rapatriement' => $devis['frais_rapatriement'],
            'total' => $devis['total'],
            'caution' => $devis['caution'],
            'mission' => $extra['mission'] ?? null,
            'mission_detail' => isset($extra['mission_detail']) ? json_encode($extra['mission_detail'], JSON_UNESCAPED_UNICODE) : null,
            'client_nom' => $client['nom'],
            'client_telephone' => $client['telephone'],
            'client_email' => $client['email'] ?: null,
            'conducteur' => isset($extra['conducteur']) ? json_encode($extra['conducteur'], JSON_UNESCAPED_UNICODE) : null,
            'paiement_mode' => $extra['paiement_mode'],
            'paiement_operateur' => $extra['operateur'] ?? null,
            'paiement_numero' => $extra['numero'] ?? null,
            'statut' => $mobile ? 'attente_paiement' : 'demande',
            'langue' => Langue::$code,
            'expire_le' => $mobile ? maintenant()->modify('+' . config('regles.attente_paiement_minutes') . ' minutes')->format('Y-m-d H:i') : null,
            'cree_le' => $maintenant,
            'maj_le' => $maintenant,
        ];
        $cols = array_keys($donnees);
        $pdo->prepare('INSERT INTO reservations (' . implode(', ', $cols) . ') VALUES (:' . implode(', :', $cols) . ')')->execute($donnees);
        $donnees['id'] = (int) $pdo->lastInsertId();
        self::note($donnees['id'], 'creation', $mobile ? 'Réservation créée, paiement Mobile Money lancé.' : 'Demande créée, paiement à la remise des clés.');
        return $donnees;
    }

    private static function nouveauCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = 'YD-';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (self::parCode($code));
        return $code;
    }

    public static function parCode(string $code): ?array
    {
        $q = Base::pdo()->prepare('SELECT * FROM reservations WHERE code = ?');
        $q->execute([strtoupper($code)]);
        $r = $q->fetch();
        return $r ? self::complete($r) : null;
    }

    // Accès client : le code seul ne suffit pas, il faut aussi le jeton du lien reçu.
    public static function pourClient(string $code, ?string $jeton): ?array
    {
        $r = self::parCode($code);
        return $r && is_string($jeton) && hash_equals($r['jeton'], $jeton) ? $r : null;
    }

    public static function parReference(string $reference): ?array
    {
        $q = Base::pdo()->prepare('SELECT * FROM reservations WHERE paiement_reference = ?');
        $q->execute([$reference]);
        $r = $q->fetch();
        return $r ? self::complete($r) : null;
    }

    private static function complete(array $r): array
    {
        $r['mission_detail'] = $r['mission_detail'] ? json_decode($r['mission_detail'], true) : [];
        $r['conducteur'] = $r['conducteur'] ? json_decode($r['conducteur'], true) : [];
        $r['v'] = Flotte::trouve($r['vehicule']);
        return $r;
    }

    public static function liste(?string $statut = null, string $recherche = ''): array
    {
        self::expire();
        $sql = 'SELECT * FROM reservations WHERE 1';
        $p = [];
        if ($statut) {
            $sql .= ' AND statut = :statut';
            $p['statut'] = $statut;
        }
        if ($recherche !== '') {
            $sql .= ' AND (code LIKE :r OR client_nom LIKE :r OR client_telephone LIKE :r)';
            $p['r'] = '%' . $recherche . '%';
        }
        $q = Base::pdo()->prepare($sql . ' ORDER BY debut DESC LIMIT 200');
        $q->execute($p);
        return array_map([self::class, 'complete'], $q->fetchAll());
    }

    public static function compteParStatut(): array
    {
        self::expire();
        return Base::pdo()->query('SELECT statut, COUNT(*) FROM reservations GROUP BY statut')->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public static function maj(int $id, array $champs, ?string $note = null, string $type = 'statut'): void
    {
        $champs['maj_le'] = maintenant()->format('Y-m-d H:i:s');
        $set = implode(', ', array_map(fn ($c) => "$c = :$c", array_keys($champs)));
        Base::pdo()->prepare("UPDATE reservations SET $set WHERE id = :id")->execute($champs + ['id' => $id]);
        if ($note) {
            self::note($id, $type, $note);
        }
    }

    public static function note(int $id, string $type, string $detail): void
    {
        Base::pdo()->prepare('INSERT INTO journal (reservation_id, type, detail, cree_le) VALUES (?, ?, ?, ?)')
            ->execute([$id, $type, $detail, maintenant()->format('Y-m-d H:i:s')]);
    }

    public static function journal(int $id): array
    {
        $q = Base::pdo()->prepare('SELECT * FROM journal WHERE reservation_id = ? ORDER BY id DESC');
        $q->execute([$id]);
        return $q->fetchAll();
    }

    // Un paiement Mobile Money non validé à temps libère le véhicule.
    public static function expire(): void
    {
        $m = maintenant()->format('Y-m-d H:i');
        $q = Base::pdo()->prepare("SELECT id FROM reservations WHERE statut = 'attente_paiement' AND expire_le <= ?");
        $q->execute([$m]);
        foreach ($q->fetchAll(\PDO::FETCH_COLUMN) as $id) {
            self::maj((int) $id, ['statut' => 'expiree', 'paiement_statut' => 'echec'], 'Délai de paiement dépassé, véhicule libéré.');
        }
    }

    public static function annulableParClient(array $r): bool
    {
        $limite = date_locale($r['debut'])->modify('-' . config('regles.annulation_gratuite_heures') . ' hours');
        return in_array($r['statut'], ['demande', 'confirmee'], true) && maintenant() < $limite;
    }

    public static function limiteAnnulation(array $r): DateTimeImmutable
    {
        return date_locale($r['debut'])->modify('-' . config('regles.annulation_gratuite_heures') . ' hours');
    }

    public static function messageWhatsapp(array $r): string
    {
        $v = $r['v'];
        $quand = fn (string $d) => t('{date} à {heure}', ['date' => date_texte($d, 'EEEEdMMMM'), 'heure' => heure($d)]);
        $lignes = [
            t('Bonjour Yamoo Drive, voici ma réservation {code}.', ['code' => $r['code']]),
            t('Véhicule : {nom} ({formule})', ['nom' => $v['nom'], 'formule' => $r['formule'] === 'sans' ? t('sans chauffeur') : t('avec chauffeur')]),
            t('Départ : {quand}, {lieu}', ['quand' => $quand($r['debut']), 'lieu' => lieu($r['lieu_depart'])]),
            t('Retour : {quand}, {lieu}', ['quand' => $quand($r['fin']), 'lieu' => lieu($r['lieu_retour'])]),
            t('Total : {montant}', ['montant' => fcfa($r['total'])]) . ' (' . ($r['paiement_statut'] === 'paye' ? t('payé') : t('à payer à la remise des clés')) . ')',
            t('Nom : {nom}', ['nom' => $r['client_nom']]),
        ];
        return implode("\n", $lignes);
    }
}
