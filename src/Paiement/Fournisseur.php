<?php

namespace Yamoo\Paiement;

interface Fournisseur
{
    /** Envoie la demande de paiement au téléphone du client. Retourne la référence du fournisseur. */
    public function lance(array $reservation, string $operateur, string $numero): string;

    /** « en_cours », « paye » ou « echec ». */
    public function statut(array $reservation): string;

    public function estDemo(): bool;
}
