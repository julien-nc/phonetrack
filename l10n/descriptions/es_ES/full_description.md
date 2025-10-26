# Aplicación Nextcloud PhoneTrack

📱 PhoneTrack es una aplicación Nextcloud para el rastreo y almacenamiento de localización de dispositivos.

🗺 PhoneTrack recibe la información de aplicaciones móviles de log y la muestra dinámicamete en un mapa.

🌍 Ayúdanos a traducir esta aplicacion en [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Chequeá otras formas de ayudar en [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Cómo usar PhoneTrack:

* Crea una sesión de rastreo.
* Da el enlace de registro\* a los dispositivos móviles. Elija el [método de seguimiento](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) que prefiera.
* Vea la ubicación de los dispositivos de la sesión en tiempo real (o no) en PhoneTrack o compártela con páginas públicas.

(\*) No olvide establecer el nombre del dispositivo en el enlace (en lugar de en la configuración de la aplicación de seguimiento). Sustituye "tunombre" con el nombre que desees del dispositivo. Configurar el nombre del dispositivo en la app de registro solo funciona con Owntracks, Traccar y OpenGTS.

En la página principal de PhoneTrack, mientras vigilas una sesión, puedes:

* 📍 Mostrar el historial de localizaciones
* ⛛ Filtrar puntos
* ► Editar/añadir/borrar puntos manualmente
* ✓ Editar dispositivos (cambiar nombre, cambiar color/forma, ir a otra sesión)
* Definir zonas de geovallado para dispositivos
* ► Definir alertas de proximidad para dispositivos emparejados
* ✓ Compartir una sesión con otros usuarios de Nextcloud o con un enlace público (sólo lectura)
* 🔗 Generar enlaces públicos con restricciones opcionales (filtros, nombre de dispositivo, sólo última posición, simplificación de geovallado)
* . Importar/exportar una sesión en formato GPX (un archivo con un track por dispositivo o un archivo por dispositivo)
* ► Mostrar estadísticas de sesiones
* 🔒 [Reserva un nombre de dispositivo](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) para asegurarse de que sólo el usuario autorizado puede entrar con este nombre
* 🗓 Activar la exportación automática de sesión y la purga automática (diaria/semanal/mensual)
* . Elija qué hacer cuando se alcanza el máxomo número de puntos (bloquear el registro o eliminar el punto más antiguo)

La página pública y la página pública filtrada funcionan como la página principal, excepto que sólo se muestra una sesión, todo es de sólo lectura y no hay necesidad de iniciar sesión.

Esta aplicación está probada en Nextcloud 17 con Firefox 57+ y Chromium.

¡Esta aplicación es compatible con colores temáticos y temas de accesibilidad!

Esta aplicación está en desarrollo.

## Instalar

Ver el [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) para los detalles de instalación.

Mira el archivo [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) para ver lo nuevo y lo que vendrá en la próxima versión.

Mira [AUTORES](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) para ver la lista completa de autores.

## Problemas conocidos

* PhoneTrack **ahora funciona** con la restricción de grupos de Nextcloud activada. Mira [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Se agradece cualquier comentario.