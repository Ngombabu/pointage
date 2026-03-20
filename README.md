# 📱 MobileApp - Système de Pointage par QR Code

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/your-repo)
[![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1.svg)](https://mysql.com)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

> Une application complète de gestion des présences avec QR code dynamique, système de retenues et administration avancée.

## 📋 Table des matières

- [Aperçu](#-aperçu)
- [Fonctionnalités](#-fonctionnalités)
- [Architecture Technique](#-architecture-technique)
- [Installation](#-installation)
- [Structure du Projet](#-structure-du-projet)
- [Guide d'Utilisation](#-guide-dutilisation)
- [API Documentation](#-api-documentation)
- [Base de Données](#-base-de-données)
- [Sécurité](#-sécurité)
- [Dépannage](#-dépannage)
- [Contributions](#-contributions)
- [Licence](#-licence)

## 🚀 Aperçu

**MobileApp** est une solution complète de gestion des présences pour entreprises. Elle permet aux agents de pointer via un QR code dynamique (renouvelé toutes les 15 secondes) et aux superviseurs de gérer les présences, les retards et les retenues salariales.

### ✨ Captures d'écran

| Interface Agent | Interface Superviseur | Scanner QR |
|-----------------|----------------------|------------|
| ![Agent](https://via.placeholder.com/300x200?text=Interface+Agent) | ![Admin](https://via.placeholder.com/300x200?text=Interface+Admin) | ![Scan](https://via.placeholder.com/300x200?text=Scanner+QR) |

## 🎯 Fonctionnalités

### 👤 Espace Agent
- 🔐 **Connexion sécurisée** avec horaires autorisés (18h-5h)
- 📱 **QR Code dynamique** renouvelé toutes les 15 secondes
- 🔄 **Rafraîchissement automatique** avec timer visuel
- 📊 **Tableau de bord personnel** avec statistiques
- 💰 **Suivi des retenues** par mois
- 📅 **Historique des présences** et retards
- ✏️ **Modification du profil** (email, mot de passe)

### 👑 Espace Superviseur
- 📈 **Dashboard** avec statistiques globales
- 👥 **Gestion des agents** (CRUD complet)
- 🏪 **Gestion des shops** (CRUD complet)
- 👤 **Gestion des superviseurs** (admin uniquement)
- ⚙️ **Configuration** (heure de pointage, pénalité)
- 🔌 **Contrôle des connexions** (ON/OFF)
- 📊 **Rapports détaillés** par mois et par agent
- 💸 **Gestion manuelle des retenues**

### 📷 Scanner QR Code
- 🎥 **Scan en temps réel** avec caméra
- ⏱️ **Validation token** (15 secondes)
- 📝 **Enregistrement automatique** des présences
- ⚠️ **Détection des retards** avec calcul de pénalité
- 📋 **Historique des scans** en direct
- 🔄 **Interface responsive** tablet/PC

## 🏗 Architecture Technique

### Technologies Utilisées

| Technologie | Version | Utilisation |
|------------|---------|-------------|
| PHP | 8.0+ | Backend API |
| MySQL | 5.7+ | Base de données |
| HTML5/CSS3 | - | Interface utilisateur |
| JavaScript | ES6+ | Logique frontend |
| jsQR | 1.4.0 | Détection QR code |
| phpqrcode | - | Génération QR code |
| FontAwesome | - | Icônes |

### Structure API
