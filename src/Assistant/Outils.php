<?php

namespace Yamoo\Assistant;

use Yamoo\Demande;
use Yamoo\Devis;
use Yamoo\Disponibilite;
use Yamoo\Flotte;

// Outils de l'assistant : ils lisent la flotte, les disponibilités et les règles de prix,
// pour que les réponses ne citent jamais un prix ou une date inventés.
final class Outils
{
    /** Cartes de véhicules et bouton d'action à afficher sous la réponse. */
    public array $cartes = [];
    public ?array $action = null;

    public static function definitions(): array
    {
        $slugs = array_keys(Flotte::tous());
        $lieux = array_keys(config('lieux'));
        $date = ['type' => 'string', 'description' => 'Date et heure locales au format AAAA-MM-JJTHH:MM, par exemple 2026-11-21T09:00.'];
        return [
            [
                'name' => 'chercher_vehicules',
                'description' => 'Liste les véhicules de la flotte avec leur prix par jour. Avec des dates, indique aussi la disponibilité et le total ; si les dates ne conviennent pas, erreur_dates dit quoi corriger et aucune disponibilité n’est donnée. À utiliser pour « quels véhicules sont libres », « un 7 places », « le moins cher », etc.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'lieu' => ['type' => 'string', 'enum' => $lieux, 'description' => 'Lieu de prise en charge.'],
                        'debut' => $date,
                        'fin' => $date,
                        'formule' => ['type' => 'string', 'enum' => ['sans', 'avec'], 'description' => 'sans = le client conduit, avec = chauffeur Yamoo Drive.'],
                        'places_min' => ['type' => 'integer', 'description' => 'Nombre minimum de places.'],
                        'categorie' => ['type' => 'string', 'enum' => array_keys(config('categories'))],
                    ],
                ],
            ],
            [
                'name' => 'fiche_vehicule',
                'description' => 'Détails d’un véhicule : type, places, équipements, formules, prix par jour et prochaines périodes déjà réservées.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => ['vehicule' => ['type' => 'string', 'enum' => $slugs]],
                    'required' => ['vehicule'],
                ],
            ],
            [
                'name' => 'calculer_devis',
                'description' => 'Calcule le prix exact d’une location (journées de 24 h, une heure de tolérance, frais de rapatriement si la ville de retour est différente) et vérifie la disponibilité.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vehicule' => ['type' => 'string', 'enum' => $slugs],
                        'formule' => ['type' => 'string', 'enum' => ['sans', 'avec']],
                        'lieu_depart' => ['type' => 'string', 'enum' => $lieux],
                        'lieu_retour' => ['type' => 'string', 'enum' => $lieux],
                        'debut' => $date,
                        'fin' => $date,
                    ],
                    'required' => ['vehicule', 'debut', 'fin'],
                ],
            ],
            [
                'name' => 'preparer_reservation',
                'description' => 'Prépare le formulaire de réservation déjà rempli. L’interface affiche un bouton « Continuer la réservation » sous ta réponse. À appeler dès que le client veut réserver et que véhicule, dates et lieu sont connus.',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'vehicule' => ['type' => 'string', 'enum' => $slugs],
                        'formule' => ['type' => 'string', 'enum' => ['sans', 'avec']],
                        'lieu_depart' => ['type' => 'string', 'enum' => $lieux],
                        'lieu_retour' => ['type' => 'string', 'enum' => $lieux],
                        'debut' => $date,
                        'fin' => $date,
                    ],
                    'required' => ['vehicule', 'debut', 'fin'],
                ],
            ],
        ];
    }

    public function execute(string $nom, array $entree): array
    {
        return match ($nom) {
            'chercher_vehicules' => $this->chercher($entree),
            'fiche_vehicule' => $this->fiche($entree),
            'calculer_devis' => $this->devis($entree, false),
            'preparer_reservation' => $this->devis($entree, true),
            default => ['erreur' => "Outil inconnu : $nom"],
        };
    }

    public function chercher(array $e): array
    {
        $formule = in_array($e['formule'] ?? null, ['sans', 'avec'], true) ? $e['formule'] : null;
        $categorie = isset(config('categories')[$e['categorie'] ?? '']) ? $e['categorie'] : null;
        $liste = Flotte::filtre($categorie, $formule, (int) ($e['places_min'] ?? 0));
        $d = Demande::depuis(['lieu' => $e['lieu'] ?? null, 'debut' => $e['debut'] ?? null, 'fin' => $e['fin'] ?? null, 'formule' => $formule]);
        // Une date passée, trop proche ou sans retour ne donne aucune disponibilité :
        // le modèle reçoit la raison au lieu d'une liste de véhicules « libres ».
        $erreurDates = null;
        $avecDates = false;
        if (!empty($e['debut']) || !empty($e['fin'])) {
            $avecDates = $d->valideDates();
            $erreurDates = $avecDates ? null : implode(' ', $d->erreurs);
        }
        $resultats = [];
        $this->cartes = [];
        foreach ($liste as $v) {
            $ligne = ['vehicule' => $v['slug'], 'nom' => $v['nom'], 'type' => $v['type'], 'places' => $v['places_texte'], 'formules' => Flotte::formulesTexte($v)];
            foreach ($v['tarifs'] as $f => $prix) {
                $ligne['prix_par_jour_' . $f . '_chauffeur'] = fcfa($prix);
            }
            $carte = Carte::depuis($v, $formule);
            if ($avecDates) {
                $libre = Disponibilite::estLibre($v, $d->debut, $d->fin);
                $ligne['disponible'] = $libre;
                if (!$libre && ($a = Disponibilite::libreA($v, $d->debut, $d->fin))) {
                    $ligne['libre_a_partir_de'] = $a->format('Y-m-d H:i');
                }
                $carte = Carte::depuis($v, $formule, $d);
            }
            $resultats[] = $ligne;
            $this->cartes[] = $carte;
        }
        // Les véhicules libres d'abord, puis du moins cher au plus cher.
        usort($this->cartes, fn ($a, $b) => [!$a['disponible'], $a['prix_brut']] <=> [!$b['disponible'], $b['prix_brut']]);
        $this->cartes = array_slice($this->cartes, 0, 6);
        $reponse = ['nombre' => count($resultats)];
        if ($avecDates) {
            $reponse['libres'] = count(array_filter($resultats, fn ($l) => $l['disponible']));
        }
        if ($erreurDates) {
            $reponse['erreur_dates'] = $erreurDates;
        }
        return $reponse + ['vehicules' => $resultats];
    }

    public function fiche(array $e): array
    {
        $v = Flotte::trouve($e['vehicule'] ?? null);
        if (!$v) {
            return ['erreur' => 'Véhicule inconnu.'];
        }
        $this->cartes = [Carte::depuis($v)];
        $prises = array_map(
            fn ($o) => ['du' => $o['debut'], 'au' => $o['fin']],
            array_slice(Disponibilite::occupations($v['slug']), 0, 5)
        );
        return [
            'nom' => $v['nom'], 'type' => $v['type'], 'places' => $v['places_texte'], 'resume' => $v['resume'],
            'equipements' => $v['equipements'], 'ideal_pour' => $v['ideal'],
            'formules' => array_map('fcfa', $v['tarifs']),
            'deja_reserve' => $prises ?: 'Aucune réservation à venir.',
            'page' => url('/vehicules/' . $v['slug']),
        ];
    }

    public function devis(array $e, bool $preparer): array
    {
        $d = Demande::depuis([
            'vehicule' => $e['vehicule'] ?? null,
            'formule' => $e['formule'] ?? null,
            'lieu' => $e['lieu_depart'] ?? null,
            'lieu_retour' => $e['lieu_retour'] ?? null,
            'debut' => $e['debut'] ?? null,
            'fin' => $e['fin'] ?? null,
        ]);
        if (!$d->valide()) {
            return ['erreur' => implode(' ', $d->erreurs)];
        }
        $devis = Devis::calcule($d);
        $libre = Disponibilite::estLibre($d->vehicule, $d->debut, $d->fin);
        $this->cartes = [Carte::depuis($d->vehicule, $d->formule, $d)];
        $resultat = [
            'vehicule' => $d->vehicule['nom'],
            'formule' => $d->formule === 'sans' ? t('sans chauffeur') : t('avec chauffeur'),
            'depart' => lieu($d->lieuDepart) . ', ' . $d->debut->format('Y-m-d H:i'),
            'retour' => lieu($d->lieuRetour) . ', ' . $d->fin->format('Y-m-d H:i'),
            'jours' => $devis['jours'],
            'prix_par_jour' => fcfa($devis['prix_jour']),
            'frais_rapatriement' => fcfa($devis['frais_rapatriement']),
            'total' => fcfa($devis['total']),
            'caution_remboursable_a_la_remise' => $devis['caution'] ? fcfa($devis['caution']) : 'aucune',
            'annulation_gratuite_jusqua' => $devis['annulation_jusqua'],
            'disponible' => $libre,
        ];
        if (!$libre && ($a = Disponibilite::libreA($d->vehicule, $d->debut, $d->fin))) {
            $resultat['libre_a_partir_de'] = $a->format('Y-m-d H:i');
        }
        if ($preparer && $libre) {
            $this->action = ['libelle' => t('Continuer la réservation'), 'lien' => url('/reserver/' . $d->vehicule['slug'], $d->versParams())];
            $resultat['bouton_affiche'] = true;
        }
        return $resultat;
    }
}
