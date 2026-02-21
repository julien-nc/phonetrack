# Aplicación PhoneTrack Nextcloud

📱 PhoneTrack é unha aplicación de Nextcloud para seguir e gardar a localización de dispositivos móbiles.

🗺 Recibe a información desde as aplicacións de rexistro dos teléfonos móbiles e móstraa de xeito dinámico nun mapa.

🌍 Axúdanos a traducir esta app en [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Mira outros xeitos de axudar na [guía de colaboración](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Como utilizar PhoneTrack:

- Crea unha sesión de seguimento.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods) you prefer.
- Olla a localización do dispositivo na sesión en tempo real (ou non) en PhoneTrack ou compartea en páxinas públicas.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

Na páxina principal de PhoneTrack, ao ver unha sesión, podes:

- 📍Mostrar o historial de localizacións
- ⛛ Filtrar puntos
- ✎ Editar/engadir/eliminar puntos manualmente
- ✎ Editar dispsitivos (cambio de nome, cambio de forma/cor, ir a outra sesión)
- ⛶ Definir zonas privadas para os dispositivos
- ⚇ Definir alertas de proximidiade para parellas de dispositivos
- 🖧 Compartir a sesión con outras usuarias de Nextcloud ou cunha ligazón pública (só lectura)
- 🔗 Crear ligazóns públicas con restricións optativas (filtros, nome do dispositivo, só últimas posicións, protección simplificada da posición)
- 🖫 Importar/exportar unha sesión en formato GPX (un ficheiro cunha pista por dispositivo ou un ficheiro por dispositivo)
- 🗠 Mostar estatísticas da sesión
- 🔒 [Reserve a device name](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Activar a exportación automática da sesión e autoeliminación (diaria/semanal/mensual)
- ◔ Elixe que queres que aconteza cando acadas un determinado número de puntos (deixar de gravar ou eliminar os máis antigos)

Páxina pública e páxina pública filtrada funcionan como páxina principal excepto se só hai unha sesión que mostrar, todo está en modo só-lectura e non precisas ter sesión iniciada.

App en desenvolvemento.

## Instalación

Le a [Documentación](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) para detalles da instalación.

Comproba o ficheiro de [REXISTRO de cambios](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) para coñecer as novidades e o que está por vir en próximas versións.

No ficheiro de persoas [AUTORAS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) podes ver quen se encarga do desenvolvemento.

## Problemas coñecidos

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Calquera opinión e revisión é ben recibida.

