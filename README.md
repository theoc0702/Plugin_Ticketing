# Plugin_Ticketing# Plugin Ticketing

## 📌 Description
Plugin Ticketing est un plugin WordPress permettant de gérer des réservations en ligne. Il ajoute un formulaire de réservation via un shortcode et enregistre les demandes dans un custom post type "Réservations".

## ⚙️ Installation
1. Télécharger le dossier `plugin-ticketing`.
2. Placer le dossier dans `wp-content/plugins/`.
3. Activer le plugin depuis l'administration WordPress (`Extensions > Plugins installés`).

## 🚀 Utilisation
- **Affichage du formulaire** : Ajouter le shortcode `[reservation_form]` dans une page ou un article.
- **Accès aux réservations** : Depuis l'administration (`Réservations` dans le menu).
- **Soumission dynamique** : Le formulaire utilise AJAX pour envoyer les réservations sans recharger la page.

## 📂 Structure du projet
```
plugin-ticketing/
├── assets/
│   ├── style.css  # Styles du formulaire
│   ├── script.js  # Gestion AJAX
├── plugin-ticketing.php  # Fichier principal du plugin
└── README.md  # Documentation
```

## 🛠️ Développement
### Ajout d'une nouvelle fonctionnalité
- Modifier `plugin-ticketing.php` pour ajouter des hooks WordPress.
- Ajouter du CSS ou du JavaScript dans `assets/`.

### Enregistrement d'une réservation
Les réservations sont stockées dans un custom post type `reservation`. Chaque réservation contient :
- **Nom du client** (titre du post)
- **Email du client** (contenu du post)
- **Date de réservation** (métadonnée `date_reservation`)

## 📌 À venir
- Gestion des créneaux horaires
- Affichage des réservations sur un calendrier
- Notifications par email

## ✉️ Contact
Auteur : [Théo Chelly](https://www.linkedin.com/in/theo-chelly/)