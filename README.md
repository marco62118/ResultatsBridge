# ResultatsBridge
**La gestion moderne de votre club de bridge**

Un simple smartphone Android comme boîtier de marquage aux tables de tournoi de Bridge. Un smartphone par table suffit.

Application complète pour organiser et gérer des tournois de bridge : constitution des équipes, suivi des mouvements, saisie des scores, calcul automatique du classement, consultation en ligne.

## Télécharger

[⬇️ Télécharger l'APK v1.0](https://github.com/marco62118/ResultatsBridge/releases/download/v1.0/resultatsbridge.apk)

> Compatible Android 6 et supérieur. Autoriser les sources inconnues dans les paramètres de votre téléphone.

---

## Présentation générale

ResultatsBridge est une solution complète sur smartphone Android permettant l'organisation du club et de gérer intégralement un tournoi de bridge.

- Gestion des membres du club et leur accès aux résultats des tournois
- Organisation complète de tournois de bridge de tout type avec constitution des équipes, suivi des mouvements des joueurs et des donnes
- Enregistrement automatique de chaque donne
- Calcul automatique du classement
- Consultation des résultats en local et en ligne

## Fonctionnalités principales

- Calcul automatique du classement en fin de tournoi
- Saisie possible des enchères et des mains donne par donne
- Mode local (réseau Wi-Fi interne) ou mode en ligne (Internet)
- Export du tournoi vers un serveur en ligne pour consultation par les adhérents
- Espace web pour les clubs et leurs adhérents

## Types de tournois supportés

- Tournois par 4 équipes sur 2 tables
- Tournois Howell (avec ou sans table relais)
- Tournois Mitchell (avec skip, guéridon, table relais)

Tous les mouvements sont préétablis et indiqués sur les smartphones.

---

## Modes de fonctionnement

### Mode local — sans internet
L'organisateur crée un point d'accès Wi-Fi mobile sur son smartphone. Les joueurs se connectent à ce réseau et saisissent l'adresse IP communiquée par l'organisateur. En fin de tournoi, l'organisateur peut exporter les résultats vers le serveur en ligne.

### Mode en ligne — internet obligatoire
L'organisateur crée le tournoi directement sur le serveur en ligne. Les joueurs saisissent leurs résultats en temps réel via le réseau mobile ou Wi-Fi.

---

## Accès au serveur en ligne

| Qui | Lien |
|-----|------|
| Inscription d'un club | https://resultats-bridge.alwaysdata.net/asso/inscription.html |
| Gestion du compte club | https://resultats-bridge.alwaysdata.net/asso/mon_compte.html |
| Consultation des résultats | https://resultats-bridge.alwaysdata.net/asso/resultats.html |

- L'inscription d'un club nécessite un **code d'invitation** fourni par l'administrateur de la plateforme
- Chaque adhérent s'inscrit avec le **code adhérent** fourni par son club
- Les joueurs externes s'inscrivent avec le **code externe** et ne consultent que les tournois auxquels ils ont participé

---

## Déroulement d'un tournoi

### Constitution du tournoi (Organisateur)
Configuration du nombre d'équipes, de tables et de donnes par table. Constitution des paires avec la liste des adhérents du club.

### À chaque table
Un seul joueur par table — "le marqueur" — manipule le smartphone. Il saisit :
1. Les enchères joueur par joueur (ou directement le contrat final)
2. La carte d'entame
3. Le résultat de la donne (les points sont calculés automatiquement)
4. Les mains des 4 joueurs (pour vérification et analyse post-tournoi)

### Changement de mouvement
En fin de donne, l'application indique à chaque équipe la table suivante et l'adversaire. Le mouvement ne commence qu'après le feu vert de l'organisateur.

### Fin de tournoi
Dès que toutes les équipes ont enregistré leurs donnes, le classement est calculé et affiché automatiquement sur tous les smartphones. L'organisateur exporte le tournoi sur le site en ligne en un clic.

---

## Technique

- Application Android développée en Kotlin
- Serveur PHP / MySQL
- Compatible Android 6 et supérieur
