# 🗺️ Roadmap Commerciale & Technique — WP Agent Bridge (Spécial Agences & Black Friday)

> **Objectif Stratégique** : Positionner et packager **WP Agent Bridge** comme l'outil d'inspection et de diagnostic IA incontournable pour les agences web et les développeurs WordPress / WooCommerce, avec un lancement sous forme de **Licence Lifetime (LTD)** pour le Black Friday.

---

## 🎯 Vision & Proposition de Valeur "Agence"

### Le Pitch
> *"Fini les demandes d'accès SSH/FTP au client qui prennent 3 jours pour analyser un incident. Auditez, diagnostiquez et résolvez les bugs WordPress & WooCommerce en 30 secondes avec Claude, Cursor et Antigravity, sans aucun risque de casser le site en production."*

### Les 3 Piliers de Confiance Inébranlables
1. **100% Lecture Seule (`GET` uniquement)** : Zéro risque de corruption de base de données ou de régression sur les sites clients (responsabilité civile de l'agence protégée).
2. **Conformité RGPD & Caviardage Automatique** : PII (emails, adresses clients) et secrets API (Stripe, SMTP, salts) masqués avant transmission aux LLMs.
3. **Protection Mémoire `fseek` & Anti-Brute Force** : Zéro impact sur les performances et zéro risque de crash serveur, même sur des boutiques à fort trafic.

---

## 🚀 Phase 1 : Les Incontournables Agences (Must-Have "Dealbreakers")

Ces fonctionnalités sont indispensables pour pouvoir vendre à des agences sans essuyer de refus immédiat.

### 1.1 Infrastructure Commerciale & Gestionnaire de Licences (LTD)
- [ ] **Système de Clés de Licence** : Intégration d'un moteur de licensing (ex. *Lemon Squeezy*, *Freemius*, *Appsero* ou *License Manager for WooCommerce*).
- [ ] **Validation & Activation API** : Endpoint d'activation / désactivation de site lié à la clé d'agence.
- [ ] **Distribution Sécurisée des Mises à Jour** :
  - Adapter `plugin-update-checker` (PUC) pour conditionner la réception des mises à jour automatiques à la validité de la licence.
  - Basculer les releases de code de production vers un flux d'artefacts d'update protégé (API / repo privé) tout en gardant la documentation publique.
- [ ] **Gestion des Quotas d'Activation** : Support des paliers de licences (5 sites, 25 sites, Illimité).
- [ ] **Révocation à Distance** : Pouvoir détacher un site d'une licence si le client quitte l'agence.

### 1.2 Marque Blanche Complète (White-Label & Stealth Mode)
- [ ] **Personnalisation de l'Identité** :
  - Renommage du plugin dans la liste des extensions (ex: *"MonAgence Diagnostic Core"*).
  - Personnalisation de l'auteur, de l'URL du site agence et de la description.
  - Remplacement ou masquage de l'icône de menu.
- [ ] **Mode Furtif (Stealth Mode)** :
  - Masquer l'onglet de menu `Settings > Agent Bridge` pour les utilisateurs non super-administrateurs.
  - Protection d'accès au panneau via constante `wp-config.php` (ex: `define('WP_AGENT_BRIDGE_STEALTH', true);`) ou paramètre d'URL secret.
  - Option de verrouillage pour empêcher la désactivation accidentelle du plugin par le client.
- [ ] **Nettoyage des Références Externes** : Masquage conditionnel des liens GitHub et contacts de support dans l'interface lorsque le mode Marque Blanche est activé.

### 1.3 Gestion Multi-Développeurs & Jetons d'Accès Scopés
- [ ] **Jetons Multiples Nommés** :
  - Remplacement du jeton unique global par un tableau de jetons étiquetés (ex: *"Julien - Lead Dev"*, *"Audit Freelance Frontend"*, *"Bot Monitoring"*).
- [ ] **Dates d'Expiration** : Possibilité de créer des jetons temporaires avec expiration automatique (ex: 7 jours, 14 jours, 30 jours) pour les prestataires externes.
- [ ] **Permissions / Scopes par Jeton** :
  - Possibilité de restreindre un token à un sous-ensemble de modules (ex: lecture seule sur `theme` et `content`, mais interdiction stricte de `woocommerce/orders` et `logs`).
- [ ] **Révocation Granulaire** : Révoquer un collaborateur ou un prestataire en 1 clic sans invalider les accès des autres développeurs.
- [ ] **Traçabilité des Requêtes** : Attribution de chaque requête dans l'onglet des logs au jeton spécifique utilisé.

### 1.4 Support des Formulaires d'Agence (Gravity Forms, Fluent Forms, WPForms)
- [ ] **Nouveau Contrôleur REST `/forms`** :
  - Prise en charge des 3 extensions de formulaires les plus populaires en agence :
    - **Gravity Forms**
    - **Fluent Forms**
    - **WPForms**
    - *(En complément d'Elementor Forms déjà supporté)*.
- [ ] **Cartographie des Intégrations** :
  - Liste des formulaires actifs avec champs et IDs.
  - Détection des flux de notifications (adresses emails de routage).
  - Détection des webhooks et intégrations CRM (HubSpot, ActiveCampaign, Mailchimp, Zapier).

---

## ⚡ Phase 2 : Les "Game Changers" Spécifiques à l'IA

Ces fonctionnalités créent un effet "Whaou" immédiat chez les développeurs utilisant Cursor, Claude Code ou Antigravity.

### 2.1 Connecteur Natif MCP (Model Context Protocol)
- [ ] **Serveur MCP Dédié (`@wp-agent-bridge/mcp`)** :
  - Package npm léger exécutable via `npx` (ex: `npx wp-agent-bridge-mcp --url=https://client.com --token=...`).
  - Configuration en 1 clic dans `claude_desktop_config.json` et Cursor MCP Settings.
- [ ] **Outils MCP Prêts à l'Emploi** :
  - `wp_health_check` : diagnostic global instantané.
  - `wp_get_recent_errors` : extraction sans surcharge des dernières erreurs fatales.
  - `wp_get_theme_overrides` : détection des conflits de templates WooCommerce.
  - `wp_get_order_diagnosis` : audit complet d'une commande avec notes de passerelle de paiement.

### 2.2 Générateur de Packs de Démarrage IA & Règles Cursor
- [ ] **Export One-Click `.cursorrules` & `.windsurfrules`** :
  - Téléchargement d'un fichier de configuration prêt à déposer à la racine d'un workspace local.
- [ ] **Skill Antigravity / Claude Projects Generator** :
  - Déjà initié avec `/capabilities?format=skill`, à packager sous forme de fiche projet copiable en 1 clic.

### 2.3 Rapports de Pré-Audit Marque Blanche (PDF / Markdown)
- [ ] **Générateur de Rapport Client** :
  - Compilation des données `/system/database`, `/system/security`, `/system/mail`, `/content/seo-audit` et `/woocommerce/analytics/sales`.
  - Export sous forme de document exécutif propre (Markdown et HTML imprimable en PDF A4) avec le logo de l'agence.
  - Cas d'usage : Permet à l'agence de livrer un pré-audit complet à un prospect dès la première prise de contact.

### 2.4 Indicateur d'Environnement (Staging / Production / Local)
- [ ] **Détection `WP_ENVIRONMENT_TYPE`** :
  - Détection automatique et exposition dans `/ping` et `/system`.
  - Badge visuel dans l'admin WordPress (Vert: Production, Orange: Staging, Bleu: Local).
  - Instruction claire à destination des agents IA pour adapter leur niveau de prudence.

---

## 🔮 Phase 3 : Évolutions & Écosystème Avancé

### 3.1 Nouveaux Page Builders d'Agence
- [ ] **Bricks Builder** : Inspection des templates Bricks, des conditions d'affichage et des classes globales (très populaire chez les agences orientées performance).
- [ ] **Divi** : Détection des layouts Divi Library et des modules utilisés.

### 3.2 Gestion de Flotte (Fleet Management)
- [ ] **CLI Fleet Profile (`cli/sync.js`)** :
  - Fichier de configuration multi-sites `sites.json` permettant de synchroniser l'ensemble du parc de clients d'une agence en une seule commande (ex: `node sync.js --site=client-a`).
- [ ] **Compatibilité MainWP / WP Umbrella** :
  - Possibilité de distribuer la configuration ou de centraliser les clés de licence via les plateformes de maintenance multi-sites.

---

## 💰 Modèle Économique & Packaging Black Friday (LTD)

### Proposition de Grille Tarifaire Lifetime Deal (Paiement Unique)

| Palier | Cible | Quota Sites | Prix Conseillé (LTD) | Fonctionnalités Clés |
| :--- | :--- | :--- | :--- | :--- |
| **Tier 1 : Freelance** | Développeur solo | **5 sites** | **59 $ – 79 $** | Tous les modules d'inspection, Playbooks, CLI sync, Redaction RGPD |
| **Tier 2 : Studio** | Petite agence | **25 sites** | **129 $ – 149 $** | + Gestion Multi-Jetons avec scopes & dates d'expiration |
| **Tier 3 : Agency Pro** | Agence Web | **Sites Illimités** | **249 $ – 299 $** | **+ Marque Blanche complète (White-Label)**, Serveur MCP natif, Générateur de rapports clients |

---

## 📅 Rétroplanning Indicatif d'Exécution (D'ici Black Friday)

```mermaid
gantt
    title Préparation Lancement Black Friday
    dateFormat  YYYY-MM-DD
    section Licensing & Monétisation
    Choix et intégration License Manager (Lemon Squeezy / Freemius) :2026-09-10, 10d
    Protection du flux de mises à jour PUC                            :2026-09-20, 5d
    section Fonctionnalités Agence
    Onglet Marque Blanche (White-Label & Stealth)                    :2026-09-25, 7d
    Gestion Multi-Jetons & Scopes temporaires                       :2026-10-02, 7d
    Contrôleur Formulaires (Gravity Forms, Fluent Forms)             :2026-10-09, 6d
    section Écosystème IA
    Serveur MCP léger (npx @wp-agent-bridge/mcp)                     :2026-10-15, 8d
    Export Rapports Pré-Audit PDF/HTML Marque Blanche                :2026-10-23, 6d
    section Marketing & Lancement
    Création Landing Page & Vidéo Démo (Cursor/Claude en live)       :2026-10-29, 10d
    Campagne de Teasing & Offre Black Friday                         :2026-11-10, 15d
```
