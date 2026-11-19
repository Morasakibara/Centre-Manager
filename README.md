# PharaonAcademia — Centre Manager

Application locale de gestion d'un centre de formation : étudiants, formations,
années académiques, inscriptions, échéances, paiements, reçus et rapports PDF/Excel.

Ce guide décrit l'installation sur un autre ordinateur Windows avec MAMP.

## Prérequis

- Windows 10 ou 11.
- MAMP avec Apache, MySQL et PHP **8.2 ou supérieur**.
- Composer 2.
- Un navigateur récent.
- Accès à Internet pendant l'installation des dépendances Composer.

Extensions PHP requises par l'application et ses exports : `pdo`, `pdo_mysql`,
`mbstring`, `gd`, `zip`, `dom`, `xml`, `xmlreader`, `xmlwriter`, `simplexml`,
`fileinfo` et `zlib`. La liste effective peut varier selon les versions des
paquets ; `composer check-platform-reqs` vérifie les exigences installées.

## 1. Copier le projet sur le nouvel ordinateur

Copiez le dossier complet de l'application vers un emplacement simple, par
exemple :

```text
C:\MAMP\htdocs\pharaonacademia
```

Le dossier copié doit contenir au minimum `app`, `config`, `database`,
`public`, `resources`, `storage`, `composer.json` et `composer.lock`.

Ne copiez pas le `.env` d'un autre ordinateur. Il contient des paramètres
propres à cette installation et n'est normalement pas versionné. N'exposez pas
les répertoires `app`, `config`, `database` ou `storage` directement sur le Web.

## 2. Démarrer et configurer MAMP

1. Démarrez MAMP, puis lancez les serveurs Apache et MySQL.
2. Dans les préférences MAMP, vérifiez le port Apache et le port MySQL. Ils
   dépendent de la configuration de la machine ; ne présumez pas qu'ils sont
   identiques à ceux de l'ordinateur source.
3. Configurez le **Document Root Apache** sur le dossier `public` du projet :

   ```text
   C:\MAMP\htdocs\pharaonacademia\public
   ```

   Ainsi, les fichiers de l'application qui ne doivent pas être exposés restent
   en dehors de la racine Web.
4. Ouvrez l'adresse locale correspondant au port Apache affiché par MAMP. Par
   exemple, avec le port `80` : `http://localhost/` ; avec le port `8888` :
   `http://localhost:8888/`.

Si le Document Root MAMP ne peut pas être modifié, utilisez une configuration
de VirtualHost pointant sur le dossier `public`. Évitez de servir la racine du
projet ou de créer une URL publique vers `storage`.

## 3. Sélectionner le PHP de MAMP et installer Composer

Ouvrez PowerShell et repérez la version PHP incluse dans MAMP :

```powershell
Get-ChildItem C:\MAMP\bin\php -Directory
```

Ajoutez le dossier correspondant à la version retenue en tête du `PATH` de la
session PowerShell (remplacez le dossier d'exemple par celui présent sur le
poste) :

```powershell
$env:Path = "C:\MAMP\bin\php\php8.x.x;$env:Path"
php -v
```

Vérifiez que la version affichée est PHP 8.2 ou supérieure. Installez Composer
depuis [getcomposer.org](https://getcomposer.org/) en choisissant le `php.exe`
de MAMP si l'installateur le demande. Rouvrez PowerShell après installation,
réappliquez le `PATH` ci-dessus, puis vérifiez :

```powershell
composer --version
```

Dans PowerShell, placez-vous dans le dossier du projet et installez les
dépendances verrouillées par `composer.lock` :

```powershell
Set-Location C:\MAMP\htdocs\pharaonacademia
composer install
```

`composer install` installe également PHPStan, utile pour le contrôle du code.
Pour une installation destinée uniquement à l'utilisation de l'application,
les dépendances de développement peuvent être omises avec
`composer install --no-dev --optimize-autoloader`.

Vérifiez les extensions PHP depuis le même terminal :

```powershell
php -m
composer check-platform-reqs
```

Si Composer indique une extension manquante, activez-la dans le `php.ini` de
la version PHP MAMP utilisée par le site et par la ligne de commande. Pour
repérer le fichier chargé en ligne de commande :

```powershell
php --ini
```

Après toute modification, redémarrez les serveurs MAMP et relancez
`composer check-platform-reqs`.

## 4. Créer une base de données neuve

1. Ouvrez phpMyAdmin depuis la page d'accueil MAMP.
2. Créez une base vide nommée `pharaonacademia`, avec le jeu de caractères
   `utf8mb4` et la collation `utf8mb4_unicode_ci`.
3. Sélectionnez cette base et importez `database/schema.sql`.
4. **Pour créer le compte administrateur**, importez ensuite
   `database/seeds.sql`. Ce fichier ajoute également des étudiants, des
   formations, des inscriptions et des paiements de démonstration.

> **Attention :** `schema.sql` supprime puis recrée les tables de la base
> sélectionnée. `seeds.sql` efface et remplace plusieurs jeux de données.
> N'importez jamais ces fichiers dans une base contenant des données à garder.
> Ils sont destinés à une première installation vierge.

Le compte administrateur initial fourni par les graines est :

```text
Nom d'utilisateur : admin
Mot de passe       : Admin1234
```

Connectez-vous une première fois et changez immédiatement ce mot de passe
depuis le menu du compte. Les graines sont prévues pour un démarrage et des
essais locaux ; supprimez ou remplacez les données de démonstration avant
d'utiliser l'application avec les données réelles du centre.

## 5. Configurer l'environnement local

À la racine du projet, copiez le modèle :

```powershell
Copy-Item .env.example .env
```

Modifiez `.env` avec les ports et les identifiants MySQL de **ce** MAMP.
Exemple pour une installation MAMP locale utilisant le port MySQL `3306` et
les identifiants par défaut de développement :

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL="http://localhost"

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pharaonacademia
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

CENTRE_NOM="PharaonAcademia"
CENTRE_ADRESSE="Adresse du centre"
CENTRE_TELEPHONE="+237 ..."
CENTRE_EMAIL="contact@pharaonacademia.local"
APP_TIMEZONE=Africa/Douala
APP_DEVISE=XAF
```

Adaptez `APP_URL` au port Apache, par exemple `http://localhost:8888`. Adaptez
`DB_PORT`, `DB_USERNAME` et `DB_PASSWORD` aux valeurs réellement configurées
dans MAMP. Les identifiants `root` sans mot de passe ne conviennent qu'à une
installation de développement locale isolée ; définissez un compte MySQL dédié
et un mot de passe si le serveur est accessible à d'autres ordinateurs.

Les paramètres du centre enregistrés dans la table `parametres` priment sur les
valeurs `CENTRE_*` de `.env`. Ajustez aussi le nom, l'adresse, le téléphone et
l'e-mail dans la page **Paramètres** de l'application après le chargement des
graines.

## 6. Ouvrir l'application et vérifier le déploiement

1. Vérifiez que MAMP indique Apache et MySQL démarrés.
2. Naviguez vers l'adresse locale correspondant au port Apache.
3. Connectez-vous avec le compte initial, puis changez son mot de passe.
4. Vérifiez les pages étudiants, inscriptions, paiements et rapports.
5. Vérifiez que les exports PDF et Excel fonctionnent. Ils nécessitent les
   extensions PHP de traitement d'images, XML et ZIP.

Contrôles facultatifs depuis PowerShell, à la racine du projet :

```powershell
composer check-platform-reqs
composer analyse
```

La suite `composer test` utilise une base MySQL nommée
`centre_manager_test`, qu'elle supprime puis recrée. **Ne la lancez pas si cette
base contient des données à conserver.**

## Données, sauvegardes et accès

- La base MySQL contient les données métier. Sauvegardez-la régulièrement
  depuis phpMyAdmin ou avec la fonction de sauvegarde de l'application.
- Les reçus et rapports générés sont stockés sous `storage/exports`.
- Les journaux de l'application sont écrits sous `storage/logs`.
- Les sauvegardes générées par l'application sont placées sous
  `storage/backups`.
- PHP doit pouvoir écrire dans `storage/exports`, `storage/logs` et
  `storage/backups`. Si ces dossiers manquent, créez-les sans modifier les
  permissions au-delà de ce qui est nécessaire pour le compte exécutant
  Apache.
- L'application est destinée à un usage local. Ne publiez pas MAMP ou
  phpMyAdmin sur Internet et ne transférez pas `.env` à des tiers.

## Dépannage

### Erreur de connexion à MySQL

- Confirmez que le service MySQL MAMP est démarré.
- Vérifiez le port dans MAMP et reportez-le dans `DB_PORT`.
- Vérifiez `DB_DATABASE`, `DB_USERNAME` et `DB_PASSWORD` dans `.env`.
- Confirmez que `pharaonacademia` a été créée et que `schema.sql`, puis
  `seeds.sql`, ont été importés dans cette base.

### Erreur Composer « platform requirements »

- Exécutez `php -v` et `php --ini` pour confirmer que PowerShell utilise le PHP
  de MAMP attendu.
- Activez les extensions signalées dans le `php.ini` de MAMP, redémarrez
  Apache et relancez `composer check-platform-reqs`.

### Page introuvable, URL ou CSS incorrects

- Vérifiez que le Document Root Apache pointe bien vers
  `C:\MAMP\htdocs\pharaonacademia\public`.
- Vérifiez le port Apache et `APP_URL`, puis redémarrez Apache.

### Le nom ou les coordonnées du centre sont incorrects

- Modifiez-les dans **Paramètres**. Les valeurs en base de données remplacent
  celles du fichier `.env`.

### Les PDF/Excel ne peuvent pas être enregistrés

- Vérifiez la présence et les droits d'écriture sur `storage/exports`.
- Vérifiez les extensions `gd`, `zip`, `xml` et `mbstring` avec
  `composer check-platform-reqs`.
