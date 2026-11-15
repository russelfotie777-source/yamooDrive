<?php

namespace Yamoo\Paiement;

// Simule MTN Mobile Money et Orange Money pour présenter le parcours sans compte marchand :
// le paiement passe au bout de quelques secondes. Un numéro qui finit par 000 est refusé,
// pour montrer aussi le cas d'échec.
final class Demo implements Fournisseur
{
    private const DELAI_SECONDES = 8;

    public function lance(array $reservation, string $operateur, string $numero): string
    {
        return 'DEMO-' . $reservation['code'] . '-' . time();
    }

    public function statut(array $reservation): string
    {
        if (str_ends_with((string) $reservation['paiement_numero'], '000')) {
            return 'echec';
        }
        $depart = (int) substr(strrchr((string) $reservation['paiement_reference'], '-'), 1);
        return time() - $depart >= self::DELAI_SECONDES ? 'paye' : 'en_cours';
    }

    public function estDemo(): bool
    {
        return true;
    }
}
