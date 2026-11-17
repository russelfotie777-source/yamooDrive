<?php

namespace Yamoo;

use DateTimeImmutable;
use DateTimeZone;

// Une demande de location lue depuis l'URL ou un formulaire, avec ses erreurs.
final class Demande
{
    public ?array $vehicule = null;
    public string $formule = 'sans';
    public string $lieuDepart = 'douala-agence';
    public string $lieuRetour = 'douala-agence';
    public ?DateTimeImmutable $debut = null;
    public ?DateTimeImmutable $fin = null;
    /** @var array<string, string> */
    public array $erreurs = [];

    public static function depuis(array $entree, ?array $vehicule = null): self
    {
        $d = new self();
        $d->vehicule = $vehicule ?? Flotte::trouve($entree['vehicule'] ?? null);

        $lieux = config('lieux');
        $lieu = $entree['lieu'] ?? $entree['lieu_depart'] ?? null;
        if (is_string($lieu) && isset($lieux[$lieu])) {
            $d->lieuDepart = $lieu;
        }
        $retour = $entree['lieu_retour'] ?? null;
        $d->lieuRetour = is_string($retour) && isset($lieux[$retour]) ? $retour : $d->lieuDepart;

        $formule = $entree['formule'] ?? null;
        if ($d->vehicule) {
            $d->formule = Flotte::formuleParDefaut($d->vehicule, is_string($formule) ? $formule : null);
        } elseif (in_array($formule, ['sans', 'avec'], true)) {
            $d->formule = $formule;
        }

        $d->debut = self::date($entree, 'debut');
        $d->fin = self::date($entree, 'fin');
        return $d;
    }

    private static function date(array $entree, string $nom): ?DateTimeImmutable
    {
        $brut = $entree[$nom] ?? null;
        if ((!is_string($brut) || $brut === '') && isset($entree["{$nom}_date"], $entree["{$nom}_heure"])) {
            $brut = $entree["{$nom}_date"] . 'T' . $entree["{$nom}_heure"];
        }
        if (!is_string($brut) || !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}$/', $brut)) {
            return null;
        }
        $brut = str_replace('T', ' ', $brut);
        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i', $brut, new DateTimeZone(config('fuseau')));
        // PHP décale une date impossible (30 février, 25 h 70) au lieu de la refuser.
        return $date && $date->format('Y-m-d H:i') === $brut ? $date : null;
    }

    public function aDesDates(): bool
    {
        return $this->debut !== null && $this->fin !== null;
    }

    public function valide(): bool
    {
        $this->erreurs = [];
        if (!$this->vehicule) {
            $this->erreurs['vehicule'] = t('Choisissez un véhicule.');
            return false;
        }
        if (!Flotte::propose($this->vehicule, $this->formule)) {
            $this->erreurs['formule'] = t('Ce véhicule se loue uniquement avec chauffeur.');
        }
        return $this->valideDates() && !$this->erreurs;
    }

    public function valideDates(): bool
    {
        unset($this->erreurs['debut'], $this->erreurs['fin']);
        if (!$this->debut) {
            $this->erreurs['debut'] = t('Indiquez le jour et l’heure de départ.');
        }
        if (!$this->fin) {
            $this->erreurs['fin'] = t('Indiquez le jour et l’heure de retour.');
        }
        if (!$this->debut || !$this->fin) {
            return false;
        }
        $delai = config('regles.delai_minimum_heures');
        if ($this->debut < maintenant()->modify("+$delai hours")) {
            $this->erreurs['debut'] = t('Le départ doit être au moins {heures} heures après maintenant. Choisissez une heure plus tardive.', ['heures' => $delai]);
        }
        if ($this->fin <= $this->debut) {
            $this->erreurs['fin'] = t('Le retour doit être après le départ.');
        } elseif (Devis::jours($this->debut, $this->fin) > config('regles.duree_max_jours')) {
            $this->erreurs['fin'] = t('Pour plus de {jours} jours, écrivez-nous sur WhatsApp : nous préparons un devis.', ['jours' => config('regles.duree_max_jours')]);
        }
        return !isset($this->erreurs['debut']) && !isset($this->erreurs['fin']);
    }

    public function versParams(): array
    {
        return [
            'formule' => $this->formule,
            'lieu' => $this->lieuDepart,
            'lieu_retour' => $this->lieuRetour !== $this->lieuDepart ? $this->lieuRetour : null,
            'debut' => $this->debut?->format('Y-m-d\TH:i'),
            'fin' => $this->fin?->format('Y-m-d\TH:i'),
        ];
    }
}
