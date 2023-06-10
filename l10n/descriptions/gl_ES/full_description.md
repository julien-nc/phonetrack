# Aplicación PhoneTrack Nextcloud

📱 PhoneTrack é unha aplicación de Nextcloud para seguir e gardar a localización de dispositivos móbiles.

🗺 Recibe a información desde as aplicacións de rexistro dos teléfonos móbiles e móstraa de xeito dinámico nun mapa.

🌍 Axúdanos a traducir esta app en [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Mira outros xeitos de axudar na [guía de colaboración](https://github.com/julien-nc/phonetrack/blob/main/CONTRIBUTING.md).

Como utilizar PhoneTrack:

* Crea unha sesión de seguimento.
* Utiliza a ligazón\* de conexión no dispositivos móbiles. Elixe a [forma de conexión](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) que prefiras.
* Olla a localización do dispositivo na sesión en tempo real (ou non) en PhoneTrack ou compartea en páxinas públicas.

(\*) Non esquezas establecer o nome do dispositivo na ligazón (mellor que nos axustes da app de rexistro). Muda o teu "nome de usuaria" co nome que queiras darlle ao dispositivo. Establecer o nome do dispositivo nos axustes da app só funciona con Owntracks, Traccar e OpenGTS.

Na páxina principal de PhoneTrack, ao ver unha sesión, podes:

* 📍Mostrar o historial de localizacións
* ⛛ Filtrar puntos
* ✎ Editar/engadir/eliminar puntos manualmente
* ✎ Editar dispsitivos (cambio de nome, cambio de forma/cor, ir a outra sesión)
* ⛶ Definir zonas privadas para os dispositivos
* ⚇ Definir alertas de proximidiade para parellas de dispositivos
* 🖧 Compartir a sesión con outras usuarias de Nextcloud ou cunha ligazón pública (só lectura)
* 🔗 Crear ligazóns públicas con restricións optativas (filtros, nome do dispositivo, só últimas posicións, protección simplificada da posición)
* 🖫 Importar/exportar unha sesión en formato GPX (un ficheiro cunha pista por dispositivo ou un ficheiro por dispositivo)
* 🗠 Mostar estatísticas da sesión
* 🔒 [Reservar no do dispositivo](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) para ter a certeza de que só a usuaria autorizada pode conectar con este nome
* 🗓 Activar a exportación automática da sesión e autoeliminación (diaria/semanal/mensual)
* ◔ Elixe que queres que aconteza cando acadas un determinado número de puntos (deixar de gravar ou eliminar os máis antigos)

Páxina pública e páxina pública filtrada funcionan como páxina principal excepto se só hai unha sesión que mostrar, todo está en modo só-lectura e non precisas ter sesión iniciada.

Esta app esta probada en Nextcloud 17 e Firefox 57+ e Chromium.

Esta app é compatible coas cores e accesibilidade dos decorados da instancia!

App en desenvolvemento.

## Instalación

Le a [Documentación](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) para detalles da instalación.

Comproba o ficheiro de [REXISTRO de cambios](https://github.com/julien-nc/phonetrack/blob/main/CHANGELOG.md#change-log) para coñecer as novidades e o que está por vir en próximas versións.

No ficheiro de persoas [AUTORAS](https://github.com/julien-nc/phonetrack/blob/main/AUTHORS.md#authors) podes ver quen se encarga do desenvolvemento.

## Problemas coñecidos

* Agora PhoneTrack **funciona correctamente** aínda estando activada a restrición de grupo en Nextcloud. Le a [documentación](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud) para admins.

Calquera opinión e revisión é ben recibida.