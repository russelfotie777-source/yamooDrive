# Yamoo Drive

Site de réservation de Yamoo Drive en six langues : flotte, disponibilités, prix exact, réservation avec ou sans chauffeur, paiement MTN Mobile Money et Orange Money, assistant, espace équipe.

PHP 8.2, SQLite, sans framework. Aucune dépendance côté navigateur.

## Lancer

```sh
composer install
cp .env.exemple .env        # puis remplir
php -S 127.0.0.1:8080 index.php
```

Sous XAMPP, le dossier fonctionne tel quel à l’adresse `http://localhost/Yamoodrive/` (le fichier `.htaccess` envoie tout vers `index.php` et bloque `config/`, `src/`, `vues/`, `stockage/` et `.env`). La base `stockage/yamoo.sqlite` se crée au premier appel ; `stockage/` doit rester inscriptible par Apache.

## Tests

```sh
composer install
composer test
```

Les tests de `tests/` couvrent le devis, la lecture d’une demande, les contrôles du conducteur et les disponibilités. Ils travaillent sur une base SQLite jetable créée dans le dossier temporaire, jamais sur `stockage/yamoo.sqlite`. Il faut les extensions `intl` et `pdo_sqlite` ; sans `pdo_sqlite`, les tests de disponibilité sont sautés. Sous XAMPP : `/opt/lampp/bin/php vendor/bin/phpunit`.

Les montants sont lus dans `config/` : les tests restent justes quand la vraie grille tarifaire remplace les prix provisoires.

## Langues

Six langues : français à la racine, anglais `/en/`, espagnol `/es/`, allemand `/de/`, chinois simplifié `/zh/`, arabe `/ar/` (mise en page de droite à gauche). Le choix se fait dans l’en-tête et garde la page et ses paramètres.

Les textes sont écrits en français dans le code (`t('…')`) et traduits dans `langues/textes.php`, une ligne par texte avec ses cinq traductions. Les nombres, les dates et les pluriels suivent les règles de chaque langue (ICU). Pour repérer un texte oublié, lancer le serveur avec `RELEVE_TRADUCTIONS=/tmp/releve.jsonl` et parcourir le site : chaque texte sans traduction y est noté.

Les traductions chinoises et arabes gagneraient à être relues par une personne de langue maternelle avant la mise en ligne. L’espace équipe reste en français ; il indique la langue dans laquelle chaque client a réservé.

## Réglages (`.env`)

| Clé | Rôle |
|---|---|
| `ANTHROPIC_API_KEY` | Assistant conversationnel (modèle `claude-opus-5-5`). Sans clé, l’assistant répond avec ses réponses intégrées : véhicules libres, prix, conditions, dates simples (« ce week-end », « demain », « samedi »). |
| `PAIEMENT_FOURNISSEUR` | `demo` simule MTN et Orange Money (aucun débit ; un numéro qui finit par `000` simule un refus). `notchpay` encaisse pour de vrai. |
| `NOTCHPAY_CLE_PUBLIQUE`, `NOTCHPAY_HASH_WEBHOOK` | Compte Notch Pay. Déclarer le webhook `https://<domaine>/api/notchpay`. |
| `ADMIN_MOT_DE_PASSE` | Ouvre l’espace équipe `/equipe`. Vide : espace fermé. |
| `BASE_SQLITE` | Autre fichier de base que `stockage/yamoo.sqlite`. Les tests s’en servent. |

## À valider par Yamoo Drive avant la mise en ligne

Seuls les tarifs planchers sont publics (30 000 FCFA par jour sans chauffeur, 40 000 FCFA avec chauffeur). Ces valeurs sont **provisoires** :

- prix par jour de chaque modèle : `config/flotte.php` ;
- formules proposées par modèle (le Prado, le Land Cruiser 300, les berlines et les vans sont en « avec chauffeur » seulement) ;
- caution (200 000 FCFA) et frais de rapatriement entre villes : `config/app.php` ;
- délai minimum avant un départ (2 heures) et durée maximale en ligne (60 jours) ;
- adresses précises des agences de Yaoundé et de Bafoussam (inconnues à ce jour).

## Règles appliquées

Tirées des conditions générales de yamoodrive.com : journée de 24 heures à partir de la prise en charge, une heure de tolérance au retour, aller simple avec frais de rapatriement, conducteur de 23 ans et permis de 2 ans pour la formule sans chauffeur (vérifiés au jour du départ), pièces obligatoires (CNI ou passeport, permis, justificatif de domicile), annulation gratuite jusqu’à 24 heures avant le départ. Un paiement Mobile Money non validé en 30 minutes libère le véhicule.

## Photos

Les photos des modèles viennent de Wikimedia Commons (licences CC0, CC BY-SA), détourées et avec une plaque Yamoo Drive à la place de la plaque d’origine. Les auteurs sont crédités sur `/credits`. Les photos d’intérieur du T2 et du X50 sont celles de yamoodrive.com. À remplacer par les photos de la vraie flotte dès qu’elles existent : `assets/img/flotte/<modèle>-<largeur>.avif|webp`, cadrage 4:3, fond transparent.

## Organisation

```
index.php            routes
config/              réglages, flotte
src/                 Demande, Devis, Disponibilite, Reservations, Conducteur, Paiement/, Assistant/, Pages, Admin
vues/                gabarits PHP
langues/             traductions (anglais, espagnol, allemand, chinois, arabe)
assets/              CSS, JS, images, polices Figtree et Noto Sans Arabic (OFL)
stockage/            base SQLite et pièces des conducteurs (hors du web)
tests/               tests PHPUnit
```
