# Yamoo Drive

Site de réservation de Yamoo Drive en six langues : flotte, disponibilités, prix exact, réservation avec ou sans chauffeur, paiement MTN Mobile Money et Orange Money, assistant, espace équipe.

PHP 8.2, SQLite, sans framework. Aucune dépendance côté navigateur.

## Lancer

```sh
composer install
cp .env.exemple .env        # puis remplir
php -S 127.0.0.1:8080 index.php
```

Sous XAMPP, le dossier fonctionne tel quel à l’adresse `http://localhost/Yamoodrive/` (le fichier `.htaccess` envoie tout vers `index.php` et bloque `config/`, `src/`, `vues/`, `langues/`, `stockage/`, `.env`, `.git` et les autres fichiers cachés, sauf `.well-known`). La base `stockage/yamoo.sqlite` se crée au premier appel ; `stockage/` doit rester inscriptible par Apache.

En ligne, derrière un proxy ou un CDN qui chiffre à la place du serveur (Cloudflare, la plupart des hébergeurs), le site lit le HTTPS dans `X-Forwarded-Proto` : les adresses absolues (plan du site, retour de Notch Pay, lien de la réservation) restent en `https://` et le cookie de session, celui de l’espace équipe compris, ne part jamais en clair. En `http://localhost`, le cookie reste utilisable.

Le serveur de développement (`php -S`) ne sert lui-même que `assets/` ; toute autre adresse passe par les routes, si bien que `.env`, `.git` et la base ne sortent pas non plus. Ce serveur prend une adresse qui finit par une extension (`agenda.ics`, `/en/robots.txt`) pour le nom du script : `index.php` lui rend le sien, et ces adresses suivent les mêmes routes que sous Apache.

Un paramètre envoyé en liste (`?j[]=1`), qu’aucun formulaire du site ne produit, compte pour absent : la page répond comme sans lui (réservation introuvable, catalogue complet) au lieu de tomber en erreur 500. Seules les pièces jointes du conducteur arrivent en liste, par `$_FILES`.

## Tests

```sh
composer install
composer test
```

Les tests de `tests/` couvrent les réponses intégrées de l’assistant et les outils qu’il appelle avant de citer un prix ou une disponibilité, le devis, la lecture d’une demande, les contrôles du conducteur, les disponibilités, le paiement Mobile Money et le cycle d’une réservation, la relance d’un paiement refusé et le refus d’une seconde demande pendant que la première attend (par le vrai serveur, avec la session et le jeton du formulaire), le webhook de Notch Pay (par le vrai serveur, avec sa signature), le fichier agenda, les traductions, les formats de chaque langue, le plan du site, les adresses bricolées et les fichiers que le serveur de développement accepte de servir et ses adresses à extension, la page d’une réservation et l’espace équipe (connexion, flotte), avec un mot de passe de l’équipe propre aux tests. Ils travaillent sur une base SQLite jetable créée dans le dossier temporaire, jamais sur `stockage/yamoo.sqlite`, et le paiement y reste simulé même si le `.env` local choisit Notch Pay. Il faut les extensions `intl` et `pdo_sqlite` ; sans `pdo_sqlite`, les tests de disponibilité sont sautés. Sous XAMPP : `/opt/lampp/bin/php vendor/bin/phpunit`.

Les montants sont lus dans `config/` : les tests restent justes quand la vraie grille tarifaire remplace les prix provisoires.

Sur GitHub, chaque PR et chaque push sur `main` relancent la syntaxe PHP et JavaScript puis les tests, avec PHP 8.2 et 8.4 (`.github/workflows/tests.yml`). Une PR dont la vérification est rouge ne se fusionne pas.

## Langues

Six langues : français à la racine, anglais `/en/`, espagnol `/es/`, allemand `/de/`, chinois simplifié `/zh/`, arabe `/ar/` (mise en page de droite à gauche). Le choix se fait dans l’en-tête et garde la page et ses paramètres.

Les textes sont écrits en français dans le code (`t('…')`) et traduits dans `langues/textes.php`, une ligne par texte avec ses cinq traductions. Les nombres, les dates et les pluriels suivent les règles de chaque langue (ICU). `composer test` signale un texte du code ou de `config/` sans traduction, une traduction qui perd un paramètre (`{prix}`, `{n, plural, …}`), une forme ICU cassée ou une clé écrite deux fois. Pour les textes construits à la volée, lancer le serveur avec `RELEVE_TRADUCTIONS=/tmp/releve.jsonl` et parcourir le site : chaque texte sans traduction y est noté.

Les traductions chinoises et arabes gagneraient à être relues par une personne de langue maternelle avant la mise en ligne. L’espace équipe reste en français ; il indique la langue dans laquelle chaque client a réservé.

## Référencement

`/sitemap.xml` liste les pages publiques (accueil, catalogue, fiches, conditions, crédits) dans les six langues, chacune reliée à ses traductions. `/robots.txt` ferme l’API et l’espace équipe et indique le plan du site. Les pages de réservation, de paiement et de suivi d’une réservation portent `noindex`. À la mise en ligne, déclarer `https://<domaine>/sitemap.xml` dans Google Search Console et Bing Webmaster Tools.

## Réglages (`.env`)

| Clé | Rôle |
|---|---|
| `ANTHROPIC_API_KEY` | Assistant conversationnel (modèle `claude-opus-5-5`). Il ne donne une disponibilité que pour des dates qu’on peut réserver : un départ passé ou trop proche, un retour manquant ou avant le départ lui reviennent comme une erreur à corriger avec le client. Sans clé, l’assistant répond avec ses réponses intégrées : véhicules libres, prix, conditions, dates simples (« ce week-end », « demain », « samedi »). |
| `PAIEMENT_FOURNISSEUR` | `demo` simule MTN et Orange Money (aucun débit ; un numéro qui finit par `000` simule un refus). `notchpay` encaisse pour de vrai. |
| `NOTCHPAY_CLE_PUBLIQUE`, `NOTCHPAY_HASH_WEBHOOK` | Compte Notch Pay. Déclarer le webhook `https://<domaine>/api/notchpay`. |
| `ADMIN_MOT_DE_PASSE` | Ouvre l’espace équipe `/equipe`. Vide : espace fermé. La recherche trouve un client par son code, son nom ou son numéro écrit de n’importe quelle façon (« +237 6 72 89 40 84 », « 672 89 40 84 », « 00237… », la fin du numéro). Chaque action (confirmer, encaisser, annuler, terminer) n’est acceptée que dans l’état où la page la propose : un onglet resté ouvert ne peut pas confirmer une réservation expirée. La page Flotte relie chaque réservation à venir à sa page, avec son retour prévu et son état quand elle n’est pas encore confirmée. Après 5 mots de passe faux en 15 minutes, l’adresse IP attend ; derrière un proxy qui masque les adresses, toute l’équipe attend avec elle. |
| `BASE_SQLITE` | Autre fichier de base que `stockage/yamoo.sqlite`. Les tests s’en servent. |

## À valider par Yamoo Drive avant la mise en ligne

Seuls les tarifs planchers sont publics (30 000 FCFA par jour sans chauffeur, 40 000 FCFA avec chauffeur). Ces valeurs sont **provisoires** :

- prix par jour de chaque modèle : `config/flotte.php` ;
- formules proposées par modèle (le Prado, le Land Cruiser 300, les berlines et les vans sont en « avec chauffeur » seulement) ;
- caution (200 000 FCFA) et frais de rapatriement entre villes : `config/app.php` ;
- délai minimum avant un départ (2 heures) et durée maximale en ligne (60 jours) ;
- adresses précises des agences de Yaoundé et de Bafoussam (inconnues à ce jour).

## Règles appliquées

Tirées des conditions générales de yamoodrive.com : journée de 24 heures à partir de la prise en charge, une heure de tolérance au retour, aller simple avec frais de rapatriement, conducteur de 23 ans et permis de 2 ans pour la formule sans chauffeur (vérifiés au jour du départ), pièces obligatoires (CNI ou passeport, permis, justificatif de domicile), annulation gratuite jusqu’à 24 heures avant le départ. Pendant l’heure de tolérance, le véhicule reste au client, même une fois l’heure de retour passée : le site ne l’annonce pas libre et n’accepte aucun départ avant la fin de cette heure. Un paiement Mobile Money non validé en 30 minutes libère le véhicule ; une page de paiement restée ouverte plus longtemps ne peut plus relancer le paiement ni passer à la remise des clés, le client voit « Paiement non reçu » et refait une réservation. Tant qu’une demande attend sur le téléphone, le site n’en envoie pas d’autre et ne passe pas à la remise des clés, même après un double clic ou depuis un second onglet : la référence de la demande en attente reste celle de la réservation, et le paiement validé sur le téléphone y arrive. S’il arrive quand même après ce délai, la réservation reprend si le véhicule est encore libre ; sinon elle reste expirée et le journal de l’équipe indique le remboursement à faire. La règle vaut aussi quand Notch Pay prévient le site avant qu’une page ait constaté la fin du délai : le véhicule libéré n’est jamais rendu à un client s’il a été réservé entre-temps. Quand l’équipe confirme ou annule la réservation pendant que la demande attend encore sur le téléphone du client, le paiement qui arrive ensuite est noté sans revenir sur sa décision : le client ne paie pas une seconde fois à la remise des clés et, après une annulation, le journal indique le remboursement à faire.

## Agenda

La page d’une réservation propose « Ajouter à mon agenda » : un fichier `.ics` avec le véhicule, les heures de départ et de retour, le lieu de prise en charge, le code, le total et le numéro WhatsApp. Il suit la norme iCalendar (RFC 5545) : heures en UTC, lignes de 75 octets au plus, pliées entre deux caractères pour que l’arabe, le chinois et les accents restent entiers.

## Photos

Les photos des modèles viennent de Wikimedia Commons (licences CC0, CC BY-SA), détourées et avec une plaque Yamoo Drive à la place de la plaque d’origine. Les auteurs sont crédités sur `/credits`. Les photos d’intérieur du T2 et du X50 sont celles de yamoodrive.com. À remplacer par les photos de la vraie flotte dès qu’elles existent : `assets/img/flotte/<modèle>-<largeur>.avif|webp`, cadrage 4:3, fond transparent.

## Organisation

```
index.php            routes
config/              réglages, flotte
src/                 Demande, Devis, Disponibilite, Reservations, Conducteur, Agenda, Paiement/, Assistant/, Pages, Admin
vues/                gabarits PHP
langues/             traductions (anglais, espagnol, allemand, chinois, arabe)
assets/              CSS, JS, images, polices Figtree et Noto Sans Arabic (OFL)
stockage/            base SQLite et pièces des conducteurs (hors du web)
tests/               tests PHPUnit
```
