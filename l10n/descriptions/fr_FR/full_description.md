# Application Nextcloud PhoneTrack

📱 PhoneTrack est une application Nextcloud pour suivre et stocker la position d'appareils mobiles.

🗺 Elle reçoit des informations provenant d'applications de logging des téléphones mobiles et les affiche en direct sur une carte.

🌍 Aidez-nous à traduire cette application sur [le projet Crowdin de PhoneTrack Nextcloud](https://crowdin.com/project/phonetrack).

⚒ Découvrez d'autres façons d'aider dans les [indications de contribution](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Comment utiliser PhoneTrack :

- Créez une session de tracking.
- Donnez le lien de logging\* aux appareils mobiles. Choose the [logging method](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods) you prefer.
- Regardez l'emplacement des appareils de la session en temps réel (ou non) dans PhoneTrack ou partagez-le avec des liens publics.

(\*) N'oubliez pas de définir le nom de l'appareil dans le lien (plutôt que dans les paramètres de l'application de logging). Remplacez 'yourname' par le nom d'appareil désiré.
Définir le nom de l'appareil dans les paramètres de l'application de journalisation ne fonctionne qu'avec Owntracks, Traccar et OpenGTS.

Sur la page principale de PhoneTrack, quand vous regardez une session, vous pouvez :

- 📍 Afficher l'historique de position
- ⛛ Filtrer les points
- ✎ Modifier/ajouter/supprimer manuellement les points
- ✎ Éditer les appareils (renommer, changer la couleur/forme, déplacer vers une autre session)
- ⛶ Définir des zones de geofencing pour les appareils
- ⚇ Définir des alertes de proximité pour des paires d'appareils
- 🖧 Partager une session à d'autres utilisateurs Nextcloud ou avec un lien public (lecture seule)
- 🔗 Générer des liens de partage public avec des restrictions optionnelles (filtres, nom d'appareil, dernières positions seulement, simplification de geofence)
- 🖫 Importer/exporter une session au format GPX (un fichier avec une piste par périphérique ou un fichier par périphérique)
- 🗠 Afficher les statistiques des sessions
- 🔒 [Reserve a device name](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Activer l'export automatique de session et la purge automatique (quotidien/hebdomadaire/mensuel)
- ◔ Choisir ce qui se passe lorsque le quota de nombre de point est atteint (bloquer le logging ou supprimer le point le plus ancien)

Les pages publiques et les pages publiques filtrées fonctionnent comme la page principale, sauf qu'il n'y a qu'une session affichée, tout est en lecture seule et il n'y a pas besoin d'être connecté.

Cette appli est en développement.

## Installation

Voir l' [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) pour les détails de l'installation.

Faites un tour vers le fichier [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) pour voir ce qui est nouveau et ce qui arrive dans la prochaine version.

Lisez le fichier [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) pour voir la liste complète des auteurs.

## Problèmes connus

- PhoneTrack **fonctionne maintenant** avec une restriction de groupe Nextcloud activée. See [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Tout retour sera apprécié.

