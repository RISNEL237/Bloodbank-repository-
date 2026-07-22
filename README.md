# 🩸 SangGestion — Système de Gestion de Banque de Sang

Application web complète (PHP + MySQL + HTML/CSS/JS natifs) pour la gestion
d'une banque de sang : donneurs, dons, poches, stocks, hôpitaux,
distributions, utilisateurs et historique.

---

## 📁 Structure du projet

```
gestion_sang/
├── config/
│   ├── config.php          → connexion PDO, constantes (seuils, délais)
│   └── schema.sql           → script SQL de création de la base
├── includes/
│   ├── header.php           → sidebar + topbar (inclus dans chaque page)
│   ├── footer.php
│   ├── menu.php              (fusionné dans header.php)
│   └── auth_check.php       → protection des pages + logAction()
├── auth/
│   ├── auth.php             → connexion + inscription (un seul fichier, onglets)
│   └── logout.php
├── pages/
│   ├── dashboard.php
│   ├── donneur.php          → CRUD donneurs + historique dons
│   ├── poche.php            → CRUD poches + suivi stock
│   ├── hopital.php          → CRUD hôpitaux
│   ├── vente.php             → distributions + mise à jour auto du stock
│   ├── recherche.php         → recherche globale
│   ├── historique.php        → journal des opérations
│   └── utilisateurs.php      → gestion des comptes (admin uniquement)
├── actions/
│   ├── save_don.php          → formulaire + traitement don (règle des 90 jours)
│   ├── delete.php             → suppression logique générique
│   └── update.php             → mise à jour rapide (AJAX)
├── css/
│   └── style.css
├── js/
│   └── script.js
└── index.php                 → page d'accueil publique
```

---

## ⚙️ Installation

### 1. Prérequis
- PHP ≥ 8.0 (avec extension **PDO MySQL**)
- MySQL ou MariaDB
- Un serveur local : XAMPP, WAMP, Laragon ou `php -S`

### 2. Créer la base de données

```bash
mysql -u root -p < config/schema.sql
```

Ou via phpMyAdmin : créez une base `gestion_sang`, puis importez `config/schema.sql`.

### 3. Configurer la connexion

Modifiez `config/config.php` si nécessaire :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'gestion_sang');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 4. Créer le compte administrateur

Le script SQL insère un administrateur par défaut, mais avec un hash
factice. Générez un vrai hash avec ce mini-script PHP (à exécuter une
seule fois puis à supprimer) :

```php
<?php
echo password_hash('VotreMotDePasse123!', PASSWORD_DEFAULT);
```

Copiez le résultat dans la colonne `mot_de_passe` de la table
`utilisateurs` pour l'administrateur, ou simplement créez votre compte
via la page **Inscription** (`auth/auth.php?mode=inscription`) en
choisissant le rôle "Administrateur".

### 5. Lancer le serveur

```bash
php -S localhost:8000
```

Puis ouvrez `http://localhost:8000/index.php`.

---

## 🔑 Règles métier importantes implémentées

- **Délai de 90 jours entre deux dons** : vérifié côté serveur dans
  `actions/save_don.php` (jamais uniquement côté JS). Si le délai n'est
  pas respecté, le formulaire est désactivé et un message explicite
  affiche le nombre de jours restants.
- **Alerte de stock critique** : si le stock d'un groupe sanguin tombe
  sous `SEUIL_STOCK_CRITIQUE` (5 par défaut, modifiable dans
  `config.php`), une bannière rouge apparaît sur toutes les pages et un
  badge clignote dans la sidebar.
- **Mise à jour automatique du stock** : lors d'une distribution
  (`pages/vente.php`), la poche est automatiquement marquée comme
  `distribue` ou sa quantité est décrémentée.
- **Expiration automatique** : les poches dont la date d'expiration est
  dépassée passent automatiquement à l'état `expire` à chaque chargement
  de `pages/poche.php`.
- **Historique complet** : chaque action (ajout, modification,
  suppression, connexion, distribution) est enregistrée via la fonction
  `logAction()` dans la table `historique`, avec date, heure, utilisateur
  et IP.
- **Mots de passe chiffrés** avec `password_hash()` / `password_verify()`.
- **Suppression logique** : les donneurs et hôpitaux ne sont jamais
  supprimés physiquement (champ `actif`), afin de préserver l'intégrité
  de l'historique et des statistiques.

---

## 👥 Rôles utilisateurs

| Rôle           | Droits                                                        |
|----------------|----------------------------------------------------------------|
| **Administrateur** | Accès total, gestion des utilisateurs, suppression définitive |
| **Agent**          | Gestion donneurs, dons, poches, hôpitaux, distributions        |
| **Médecin**        | Consultation + enregistrement de distributions                |

---

## 🎨 Design

- Palette médicale : rouge sang `#C0152A`, blanc clinique, gris ardoise
- Police **Inter** (Google Fonts)
- Sidebar sombre avec badges de rôle colorés
- Jauges de stock animées par groupe sanguin
- Graphiques Chart.js (dons par mois, répartition du stock)
- 100% CSS pur (aucun framework), responsive (sidebar repliable en mobile)

---

## 📝 Notes pour la soutenance

- Le code suit une architecture MVC simplifiée adaptée à un projet
  académique : séparation claire config / includes / pages / actions.
- Toutes les requêtes SQL utilisent des **requêtes préparées PDO**
  (protection contre les injections SQL).
- Toutes les sorties HTML utilisateur passent par `htmlspecialchars()`
  (protection contre les failles XSS).
- Les sessions sont configurées avec `httponly` et `samesite=Strict`.
