# Tracabilit-des-quipements-par-QR-Code
Ce projet permet de scanner un QR code pour afficher l’état d’un équipement, changer son statut, l’attribuer à un utilisateur et consulter l’historique des mouvements.
## Fonctionnalités

- Connexion sécurisée.
- Scan de QR code.
- Changement automatique de statut.
- Attribution d’équipements à des utilisateurs.
- Historique des mouvements.
- Export CSV des données.
- Page d’analyse avec statistiques.
- Génération dynamique des QR codes.

## Technologies utilisées

- PHP
- MariaDB
- HTML / CSS
- Bootstrap
- JavaScript
- Chart.js

## Installation

1. Installer Apache, PHP et MariaDB.
2. Importer la base de données.
3. Modifier le fichier `db.php`.
4. Déposer le projet dans le dossier web.
5. Ouvrir l’interface dans le navigateur.

## Base de données

Tables principales :
- `equipements`
- `movements`
- `scan_pending`
- `utilisateurs`

## Utilisation

1. Se connecter avec les identifiants administrateur.
2. Scanner un QR code.
3. Visualiser l’équipement.
4. L’attribuer à un utilisateur si besoin.
5. Consulter les logs et les exports.

## Captures d’écran

Ajoute ici des images de l’interface, du tableau d’équipements et de la page d’analyse.

## Améliorations possibles

- Scan via caméra mobile.
- Notifications en temps réel.
- Tableau de bord plus avancé.
- Gestion multi-utilisateurs avec rôles.

## Auteur

Projet réalisé dans le cadre de mon BTS CIEL.
