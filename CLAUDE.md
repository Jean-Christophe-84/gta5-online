# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Environnement et commandes

Application Symfony 7.4 / PHP 8.5 / MySQL 8, exécutée dans **DDEV** (`gta5-online.local`). Toutes les commandes passent par le conteneur :

```bash
ddev exec php bin/phpunit                                          # toute la suite
ddev exec php bin/phpunit tests/Functional/BountyTargetTest.php    # un fichier
ddev exec php bin/phpunit --filter testNomDuTest                   # un test
make cc                                                            # vider le cache (ddev php bin/console c:c)
make rector-list                                                   # Rector en dry-run (sets PHP 8.5, deadCode, codeQuality, Symfony, Doctrine)
make rector                                                        # appliquer Rector
make reset                                                         # recréer la base depuis backup/gta5_online.sql (FILE=backup/history/… pour une sauvegarde plus ancienne)
make backup                                                        # sauvegarder la base maintenant (ddev php bin/console app:backup)
```

`phpunit.dist.xml` échoue sur deprecation, notice et warning.

## Base de données : `backup/gta5_online.sql` est la source de vérité

- Pas de migrations Doctrine. Le schéma et les données de référence vivent dans `backup/gta5_online.sql` (dossier synchronisé à la main sur le NAS, jamais sous `public/`).
- Sauvegarde automatique : `Doctrine/BackupMiddleware` (middleware DBAL) voit toute écriture sur la connexion Doctrine, par l'ORM comme en SQL brut (`INSERT`/`UPDATE`/`DELETE`/`REPLACE`/`TRUNCATE` exécuté sans erreur et modifiant au moins une ligne ; pas le DDL, pour que le `CREATE DATABASE` de `make reset` ne sauvegarde jamais une base vide) et prévient `EventListener/BackupListener`, qui appelle `BackupHelper::backup()` sur `kernel.terminate` / `console.terminate` (après la réponse, priorité -2048 : après `AfterResponse`). Les contrôleurs n'appellent jamais la sauvegarde ; une nouvelle action d'écriture est couverte d'office. Le middleware n'est chargé qu'en `dev` et `test` (`#[When]`) : jamais de sauvegarde en production, où `BackupListener` reste inerte. Les écritures de suivi passent par `BackupListener::ignore(fn)` (dernière activité dans `ActivityListener`, journal des connexions dans `LoginListener`) et les commandes de `BackupListener::SKIPPED_COMMANDS` (`RemoveOldLoginsCommand`, `UserStatisticCommand`) ne sauvegardent pas : elles ne justifient pas à elles seules une sauvegarde.
- Le dump (`Ifsnop\Mysqldump` sur la connexion Doctrine, utf8mb4, sans date pour que deux états identiques donnent le même fichier) est écrit dans un fichier temporaire puis renommé en `backup/gta5_online.sql` ; s'il diffère du précédent, une copie gzip va dans `backup/history/gta5_online_Y-m-d_H-i-s.sql.gz`, puis `prune()` applique la rétention (`BackupHelper::RETENTION` : tout sur 48 h, le dernier de chaque jour sur 30 jours, puis le dernier de chaque mois). Un échec est seulement journalisé (`critical`, `var/log`). Paramètres : `backup_directory` (`var/backup_test` en test, `backup/` n'est jamais touché par les tests), `backup_name`.
- L'historique garde les données des comptes anonymisés ou supprimés : à purger avant toute mise en ligne avec de vrais utilisateurs.
- Un changement de schéma doit donc aussi être reporté dans le dump.
- Les tests (`tests/bootstrap.php`) suppriment et recréent `<db>_test` depuis `backup/gta5_online.sql` à chaque lancement. Chaque test doit poser explicitement l'état dont il a besoin (voir les helpers `fetchOne`, `loginAs`, `post`, `setOwned`, `submitLogin` de `tests/Functional/AppWebTestCase.php`).

## Tests

**À maintenir à chaque évolution** : toute nouvelle route, règle métier, liste, filtre, colonne triable, formulaire ou commande reçoit ses tests dans la même modification, et un test existant suit le changement de comportement (jamais supprimé pour faire passer la suite). Relancer toute la suite après chaque modification.

- `tests/Functional/AppWebTestCase.php` : base commune (`loginAs`, `post` avec le jeton CSRF, `getModalFields` qui reproduit l'envoi d'une modale par le JS, `setOwned`, `submitLogin`, `fetchOne`). Le groupe 1 a toutes les permissions.
- `RouteTest` : parcourt **toutes** les routes du routeur (une route ajoutée est couverte sans rien écrire) : visiteur (redirection vers `/login` hors `PUBLIC_ROUTES`), administrateur en GET et en AJAX (`get_*` aussi avec un identifiant, en édition et en suppression), POST vide avec jeton (hors `EXCLUDED_POST_ROUTES`). Échoue sur un statut ≥ 500 ou sur un détail technique (`error_technical_details`) renvoyé avec un succès.
- `ListFilterTest` : chaque filtre des listes principales (`FILTERS`), chaque colonne triable (`th[data-sort-key][onclick]`) dans les deux sens, rang des colonnes triées (chaque `<th>` triable passe sa clé aux flèches, `include('_partial/arrows.html.twig', {sortKey: '…'})`, qui affichent son rang dans `filter.order` en `span.rank` quand plusieurs colonnes sont triées), tri / page / recherche invalides, recherche gardée en session, filtres Groupe / Actif / Rôle de la liste des utilisateurs (appliqués quand la recherche est vide, `UserHelper::getUsers()`). Une nouvelle liste s'ajoute dans `LISTS`, un nouveau filtre dans `FILTERS`.
- `ReferenceFormTest` : cycle des listes de référence (champ obligatoire, ajout, modale d'édition, modification, recherche, permission `*_ADD`). Une nouvelle liste de référence s'ajoute dans `referenceProvider`.
- `OwnershipTest`, `BusinessRuleTest`, `BountyTargetTest`, `HeistCasinoTest`, `HeistCayoPericoTest`, `HeistKortzCenterTest`, `DeletionTest` : règles métier ; `AccountTest`, `LoginLogTest`, `SecurityTest`, `PermissionTest`, `LocaleTest`, `ErrorPageTest` : comptes, sécurité, langues ; `CommandTest`, `UserStatisticTest` : commandes (jamais les imports gtabase.com) ; `tests/Integration/ConfigurationHelperTest` : mises en forme (nombres, pourcentages, vitesses, tri des tuiles, `getOrderBy`).
- `BackupTest` (une écriture sauvegarde, la dernière activité, le journal des connexions, une requête sans changement et les commandes de suivi non ; écriture d'`AfterResponse` incluse, `app:backup`), `tests/Integration/BackupHelperTest` (dump, historique, rétention), `tests/Unit/BackupMiddlewareTest` (`hasChanged()`).
- Un test qui modifie un état partagé (permissions, langue de l'utilisateur 1, braquage) le restaure (`finally`, `setUp`) : la base n'est recréée qu'une fois par lancement.

**Tests navigateur** (groupe `js`, exclus par défaut, environ 4 min, à lancer après toute modification de JS, de Twig ou de CSS) : `ddev exec php bin/phpunit --group js`. Symfony Panther pilote Chromium (paquets `chromium` et `chromium-driver` de `webimage_extra_packages` dans `.ddev/config.yaml`) sur le serveur PHP intégré, en env `test` sur `db_test` ; variables `PANTHER_*` dans `.env.test`, captures des échecs dans `var/error-screenshots`.
- `tests/Browser/BrowserTestCase` : connexion de l'utilisateur 1 (administrateur du groupe 1) par le vrai formulaire avec la clé Turnstile de test, tailles ordinateur 1200 / tablette 800 / mobile 375, `waitForAjax` (compte les `fetch` et attend la fin du loader du layout), erreurs graves de la console.
- `JavascriptErrorTest` : chaque page HTML du routeur, en visiteur et en administrateur, sans erreur JS (plus un garde-fou qui vérifie que la détection marche).
- `PageInteractionTest` : sur chaque liste de `ListFilterTest::LISTS` et à chaque taille, chaque filtre, retrait des filtres, recherche, chaque tri, ouverture puis fermeture de chaque modale (ajout, édition, copie, suppression) sans erreur affichée ; rien n'est enregistré.
- `ScenarioTest` : enregistrements réels par vrais clics et frappes, à chaque taille (ajout par modale et erreur de champ obligatoire, case « possédé », choix Tom Select et champs des braquages Casino / Cayo Perico / Kortz Center, prix d'un butin secondaire).

## Architecture

**Un contrôleur par action.** `src/Controller/<Domaine>/` contient des classes à une seule action (`IndexController`, `FilterController`, `FormController`, `GetController`, `UpdateController`, `DeleteController`, `LaunchCommandController`/`CheckCommandController`/`StopCommandController`...). Les routes sont déclarées en attributs.

**Helpers = couche métier.** `src/Helper/<Domaine>Helper.php` (services `readonly`) contiennent requêtes, filtres et calculs (`getFilter()`, `getParameters()`, `getDeletion()`, `delete()`...). Les contrôleurs restent fins. L'accès aux données passe surtout par du **SQL DBAL brut** (`$entityManager->getConnection()`), les entités Doctrine servant surtout de mapping.

**AJAX.** Les actions POST renvoient une `JsonResponse` `{success, message, ...}` contenant des fragments Twig rendus (`templates/<Domaine>/_partial/*.html.twig`, avec `filter`, `template`, `summary`...). Les erreurs passent par `App\Service\ErrorResponder`, les suppressions par `App\Service\DeletionResponder`. Le JS est inline dans les templates Twig. Les librairies front (highcharts, tippy, tom-select) sont vendorisées dans `public/assets/`, et le CSS est dans `public/css/style.css`.

**Sécurité.**
- Chaque action a `#[IsGranted(new Expression('is_granted("ROLE_USER")'))]` (`ROLE_ADMIN` pour la configuration et les commandes), puis vérifie une permission métier via `$this->isGranted(Entity::PERMISSION_...)`.
- `Security/Voter/PermissionVoter` résout ces attributs depuis les tables `permission` / `permission_group_gta`, par groupe (`GroupGta`) de l'utilisateur. Les permissions d'un groupe s'éditent dans la modale des groupes (`GroupGtaHelper::getPermissions()` / `savePermissions()`, enregistrées seulement si le formulaire envoie `permissionIds` ; le JS envoie toujours un `permissionIds[]=` vide pour qu'on puisse tout décocher).
- `EventListener/AjaxCsrfListener` exige l'en-tête `X-CSRF-Token` (token `ajax`, exposé dans `<meta name="csrf-token">`) sur tout POST, sauf `login` et `switch_locale`.
- La connexion a du throttling, Turnstile (`EventListener/TurnstileListener` sur `CheckPassportEvent`, via `ConfigurationHelper::checkTurnstile` ; clés `TURNSTILE_*`, clés de test Cloudflare en dev et test) et un `UserChecker`. Les connexions sont journalisées dans `LoginSuccess`/`LoginError`.

**Données par utilisateur.** Les tables `<entité>_user` (`vehicle_user`, `property_user`, `bounty_target_user`...) stockent l'état propre à chaque joueur (`owned`...). Les pages d'index insèrent d'abord les lignes manquantes pour l'utilisateur courant (`INSERT ... WHERE NOT EXISTS`).

**Traductions.**
- Les libellés d'interface sont dans `translations/messages+intl-icu.{fr,en}.yml` (ICU), via `ConfigurationHelper::getTranslateLocale()` et les filtres Twig de `src/Twig/`.
- Les données de référence sont traduites par des entités `*Locale` (`VehicleLocale`, `PropertyLocale`...) liées à `Locale`. `ConfigurationHelper::translate()` utilise DeepL.

**Audit.** `Entity/Traits/InfoUpdateTrait` (`createdBy/At`, `updatedBy/At`) est rempli par `EventListener/InfoUpdateListener`.

**Imports externes.** `src/Command/Get{Vehicles,Properties,Weapons}Command` scrapent gtabase.com. Ils sont lancés en arrière-plan depuis l'interface (`LaunchCommandController` via `Symfony\Process`, avec un PID dans `var/run/` et un log dans `var/log/*_get.log`). Les images sont dans `public/uploads/` et passent par LiipImagine (`public/media/cache`).

**Braquages.** `src/Controller/Heist/<Braquage>/` contient un dossier par braquage. Les calculs de butin sont dans `HeistCasinoHelper`, `HeistCayoPericoHelper`, `HeistKortzCenterHelper`, et les énumérations (difficulté, approche...) dans `src/Enum/`.

## Conventions du projet

- Commentaires, docblocks et messages métier en français.
- Garder triés par ordre alphabétique les `use`, méthodes, paramètres injectés et clés de tableaux / YAML de traduction. Les tuiles de l'accueil sont triées à l'affichage par libellé traduit (filtre Twig `sortTiles`).
- Le sens métier d'une ligne de référence se porte par une colonne booléenne éditable, jamais par un identifiant codé en dur en PHP.
- Hébergement : o2switch (mentions légales, `gdpr_recipient_host`).
- RGPD : compte anonymisé plutôt que supprimé (`UserHelper::anonymize`), car `created_by` et `updated_by` y font référence. Toute nouvelle table avec `user_id` va dans `UserHelper::USER_TABLES`. `users:purge` (quotidien) et `logins:remove-old` sont à planifier en production.
- `@media` uniquement en fin de `style.css`, un bloc par requête, par ordre décroissant, seulement en `max-width` : 1199, 991, 767, 575, 375 (présents même vides), `hover` et `prefers-reduced-motion` en tête. Une règle n'est écrite qu'au plus grand seuil où elle s'applique.
