# Aplicación PhoneTrack de Nextcloud

📱 PhoneTrack é unha aplicación de Nextcloud para trazar e almacenar localizacións de dispositivos móbiles.

🗺 Recibe a información desde as aplicacións de rexistro dos teléfonos móbiles e amósaa de xeito dinámico nun mapa.

🌍 Axúdanos a traducir esta app en [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Mira outros xeitos de axudar na [guía de colaboración](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Como utilizar PhoneTrack:

- Cree unha sesión de trazado.
- Pase a ligazón de rexistro\* aos dispositivos móbiles. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- Olle a localización dos dispositivos da sesión en tempo real (ou non) en PhoneTrack ou compártaa con páxinas públicas.

(\*) Non esqueza definir o nome do dispositivo na ligazón (no canto dos axustes da aplicación de rexistro). Substitúa «o seu nome» co nome de dispositivo que queira.
Definir o nome do dispositivo nos axustes da aplicación só funciona con Owntracks, Traccar e OpenGTS.

Na páxina principal de PhoneTrack, mentres ve unha sesión, pode:

- 📍Amosar o historial de localizacións
- ⛛ Filtrar puntos
- ✎ Editar/engadir/eliminar puntos manualmente
- ✎ Editar dispositivos (cambio de nome, cambio de forma/cor, pasar a outra sesión)
- ⛶ Definir zonas xeocercadas para os dispositivos
- ⚇ Definir alertas de proximidiade para parellas de dispositivos
- 🖧 Compartir a sesión con outros usuarios de Nextcloud ou cunha ligazón pública (só lectura)
- 🔗 Xerar ligazóns de compartición públicas con restricións opcionais (filtros, nome do dispositivo, só as últimas posicións, simplificación de xeocercas)
- 🖫 Importar/exportar unha sesión en formato GPX (un ficheiro cun trazado por dispositivo ou un ficheiro por dispositivo)
- 🗠 Amosar estatísticas da sesión
- 🔒 [Reservar no do dispositivo](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) para ter a certeza de que só a usuaria autorizada pode conectar con este nome
- 🗓 Alternar a exportación automática da sesión e a purga automática (diaria/semanal/mensual)
- ◔ Escolla o que facer cando se acade a cota máxima de puntos (bloquear o rexistro ou eliminar o punto máis antigo)

Tanto a páxina pública coma a páxina pública filtrada funcionan coma a páxina principal, agás que só se amosa unha sesión, todo é de só lectura e non é necesario acceder.

Esta aplicación foi probada en Nextcloud 17 con Firefox 57+ e con Chromium.

Esta aplicación é compatíbel coas cores temáticas e os temas de accesibilidade!

Esta aplicación está en desenvolvemento.

## Instalación

Le a [Documentación](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) para detalles da instalación.

Comproba o ficheiro de [REXISTRO de cambios](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) para coñecer as novidades e o que está por vir en próximas versións.

No ficheiro de persoas [AUTORAS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) podes ver quen se encarga do desenvolvemento.

## Incidencias coñecidas

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Calquera opinión será ben recibida.

