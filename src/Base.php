<?php

namespace Yamoo;

use PDO;

final class Base
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $chemin = dirname(__DIR__) . '/stockage/yamoo.sqlite';
            $nouvelle = !is_file($chemin);
            self::$pdo = new PDO('sqlite:' . $chemin, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 3000;');
            if ($nouvelle || !self::table('reservations')) {
                self::cree();
            }
        }
        return self::$pdo;
    }

    private static function table(string $nom): bool
    {
        $q = self::$pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
        $q->execute([$nom]);
        return (bool) $q->fetchColumn();
    }

    private static function cree(): void
    {
        self::$pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS reservations (
                id INTEGER PRIMARY KEY,
                code TEXT NOT NULL UNIQUE,
                jeton TEXT NOT NULL,
                vehicule TEXT NOT NULL,
                formule TEXT NOT NULL CHECK (formule IN ('sans', 'avec')),
                lieu_depart TEXT NOT NULL,
                lieu_retour TEXT NOT NULL,
                debut TEXT NOT NULL,
                fin TEXT NOT NULL,
                jours INTEGER NOT NULL,
                prix_jour INTEGER NOT NULL,
                montant_location INTEGER NOT NULL,
                frais_rapatriement INTEGER NOT NULL DEFAULT 0,
                total INTEGER NOT NULL,
                caution INTEGER NOT NULL DEFAULT 0,
                mission TEXT,
                mission_detail TEXT,
                client_nom TEXT NOT NULL,
                client_telephone TEXT NOT NULL,
                client_email TEXT,
                conducteur TEXT,
                paiement_mode TEXT NOT NULL CHECK (paiement_mode IN ('mobile', 'remise')),
                paiement_operateur TEXT,
                paiement_numero TEXT,
                paiement_reference TEXT,
                paiement_statut TEXT NOT NULL DEFAULT 'aucun',
                statut TEXT NOT NULL,
                note_interne TEXT,
                expire_le TEXT,
                cree_le TEXT NOT NULL,
                maj_le TEXT NOT NULL
            );
            CREATE INDEX IF NOT EXISTS reservations_vehicule ON reservations (vehicule, debut, fin);

            CREATE TABLE IF NOT EXISTS indisponibilites (
                id INTEGER PRIMARY KEY,
                vehicule TEXT NOT NULL,
                debut TEXT NOT NULL,
                fin TEXT NOT NULL,
                motif TEXT,
                cree_le TEXT NOT NULL
            );

            CREATE TABLE IF NOT EXISTS journal (
                id INTEGER PRIMARY KEY,
                reservation_id INTEGER NOT NULL REFERENCES reservations (id) ON DELETE CASCADE,
                type TEXT NOT NULL,
                detail TEXT,
                cree_le TEXT NOT NULL
            );
            SQL);
    }
}
