# Applicazione PhoneTrack per Nextcloud

📱 PhoneTrack è un'applicazione per Nextcloud per monitorare e memorizzare le posizioni dei dispositivi mobili.

🗺 riceve informazioni dalle app di registrazione dei telefoni cellulari e la visualizza dinamicamente su una mappa.

〓 Aiutaci a tradurre questa app su [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Scopri altri modi per aiutare nelle linee guida [contributivo](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Come utilizzare PhoneTrack:

- Crea una sessione di tracciamento.
- Fornire il collegamento di registrazione\* ai dispositivi mobili. Scegli il [ metodo di registrazione ](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) che preferisci.
- Guarda la posizione dei dispositivi della sessione in tempo reale (o no) a PhoneTrack o condividilo con pagine pubbliche.

(\*) Non dimenticare di impostare il nome del dispositivo nel link (piuttosto che nelle impostazioni di registrazione dell'app). Sostituisci "yourname" con il nome del dispositivo desiderato.
L'impostazione del nome del dispositivo nelle impostazioni di registrazione delle app funziona solo con le tracce proprie, Traccar e OpenGTS.

Nella pagina principale di PhoneTrack, mentre segui una sessione, è possibile:

- 📍 Visualizza la cronologia della posizione
- ⛛ Filtra punti
- ✎ Modifica/aggiungi/elimina punti manualmente
- ✎ Modifica dispositivi (rinomina, cambia colore/forma, sposta in un'altra sessione)
- ⛶ Definisci zone di Geo-perimetro per i dispositivi\\
- ⚇ Definisci gli avvisi di prossimità per gli accoppiamenti del dispositivo
- 🖧 Condividi una sessione ad altri utenti Nextcloud o con un link pubblico (sola lettura)
- 🔗 Genera collegamenti di condivisione pubblica con restrizioni opzionali (filtri, nome del dispositivo, solo posizioni finali, semplificazione Geo-perimetro)
- 🖫 Importa/esporta una sessione in formato GPX (un file con una traccia per dispositivo o un file per dispositivo)
- 🗠 Visualizza le statistiche delle sessioni
- 🔒 [Riserva un nome del dispositivo](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) per assicurarsi che solo l'utente autorizzato possa accedere con questo nome
- 🗓 Toggle esportazione automatica della sessione e cancellazione automatica (giornaliera/settimanale/mensile)
- 𗩌 Scegli cosa fare quando si raggiunge la quota del numero di punti (blocca la registrazione o elimina il punto più vecchio)

Pagina pubblica e pagina filtrata pubblica come pagina principale, ad eccezione di una sola sessione visualizzata, tutto è in sola lettura e non c'è bisogno di accedere.

Questa app è testata su Nextcloud 17 con Firefox 57+ e Chromium.

Questa app è compatibile con i colori tematici e i temi di accessibilità!

Questa app è in fase di sviluppo.

## Installa

Vedi [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) per i dettagli di installazione.

Controlla [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) il file per vedere cosa è nuovo e cosa sta arrivando con la prossima versione.

Seleziona il file [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) per vedere l'elenco completo degli autori.

## Problemi noti

- PhoneTrack **ora funziona** con la restrizione del gruppo Nextcloud attivata. Vedi [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Qualsiasi feedback sarà apprezzato.

