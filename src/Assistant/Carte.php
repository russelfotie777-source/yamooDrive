<?php

namespace Yamoo\Assistant;

use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Disponibilite;
use Yamoo\Flotte;

// Petite fiche de véhicule affichée dans la conversation.
final class Carte
{
    public static function depuis(array $v, ?string $formule = null, ?Demande $d = null): array
    {
        $formule = Flotte::formuleParDefaut($v, $formule);
        $prix = $v['tarifs'][$formule];
        $carte = [
            'slug' => $v['slug'],
            'nom' => $v['court'],
            'detail' => $v['type'] . ' · ' . $v['places_texte'],
            'prix' => fcfa($prix),
            'prix_brut' => $prix,
            'unite' => t('par jour') . ', ' . ($formule === 'sans' ? t('sans chauffeur') : t('avec chauffeur')),
            'disponible' => true,
            'etat' => null,
            'lien' => url('/vehicules/' . $v['slug'], ['formule' => $formule]),
            'image' => asset('img/flotte/' . $v['slug'] . '-480.webp'),
        ];
        if ($d && $d->aDesDates() && $d->fin > $d->debut) {
            $copie = clone $d;
            $copie->vehicule = $v;
            $copie->formule = $formule;
            $devis = Devis::calcule($copie);
            $carte['prix'] = fcfa($devis['total']);
            $carte['unite'] = t('pour {duree}', ['duree' => nb_jours($devis['jours'])]);
            $carte['disponible'] = Disponibilite::estLibre($v, $d->debut, $d->fin);
            if (!$carte['disponible']) {
                $libre = Disponibilite::libreA($v, $d->debut, $d->fin);
                $carte['etat'] = $libre ? t('Réservé jusqu’au {date}, {heure}', ['date' => date_texte($libre, 'dMMM'), 'heure' => heure($libre)]) : t('Réservé à ces dates');
            }
            $carte['lien'] = url('/vehicules/' . $v['slug'], $copie->versParams());
        }
        return $carte;
    }
}
