# Aplikasi PhoneTrack di Nextcloud

PhoneTrack adalah aplikasi di nextcloud yang digunakan untuk melacak serta menyimpan posisi lokasi dari perangkat mobile.

PhoneTrack menerima informasi log dari perangkat mobile dan menampilkan-nya secara dinamis di peta.

🌍 Bantu kami untuk alih bahasa di [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Silahkan kunjungi alternatif lain untuk membantu kami di [contribution guidelines](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Bagaimana cara menggunakan PhoneTrack :

- Buatlah sesi pelacakan.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
- PhoneTrack dapat memantau lokasi sesi perangkat baik secara langsung maupun tidak, atau bahkan dapat di bagikan kepada pihak lain.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

Pada halaman utama PhoneTrack, saat memantau sebuah sesi, anda dapat melakukan beberapa hal, antara lain :

- 📍 Menampilkan lokasi lampau
- ⛛ Titik penyaring
- ✎ Rubah/tambah/hapus titik secara manual
- ✎ Rubah perangkat (ganti nama, merubah bentuk/warna, pindahkan ke sesi lain)
- ⛶ Membuat area "geofencing" untuk perangkat
- ⚇ Membuat peringatan atas sebuah perangkat jika saling berdekatan
- 🖧 Berbagi sesi ke pengguna Nextcloud lainnya atau ke pihak lain (hanya lihat)
- 🔗 Membuat alamat untuk di bagi secara umum dengan beberapa batasan (saring, nama perangkat, posisi terakhir dan "geofencing" sederhana)
- 🖫 Impor/ekspor sebuah sesi dalam format GPX (satu berkas dengan satu pelacakan untuk setiap perangkat atau satu berkas untuk satu perangkat)
- 🗠 Menampilkan statistik sesi
- 🔒 [Pesan nama perangkat](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) sehingga hanya yang di perbolehkan saja menggunakan nama tersebut
- 🗓 Perpindahan sesi ekspor otomatis dan penghapusan otomatis (harian/mingguan/bulanan)
- ◔ Memilih untuk menentukan ketika jumlah kuota titik telah habis (hapus titik sebelumnya atau membatasi log)

Halaman untuk umum dan yang ter-saring bekerja seperti halaman utama hanya saja tersedia untuk satu sesi, semuanya tidak perlu akses khusus.

Aplikasi ini telah di ujicoba pada seri Nextcloud 17 dengan menggunakan Firefox 57+ dan Chromium.

Aplikasi ini juga dapat merubah warna tema dan tema aksesibilitas !

Fitur ini dalam pengembangan.

## Memasang

Silahkan mengunjungi [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) untuk lebih detail tentang pemasangan.

Silahkan periksa berkas [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) untuk melihat daftar perubahan dan rencana pengembangan.

Silahkan periksa berkas [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) untuk melihat daftar para pengarang.

## Kendala yang Diketahui

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Saran dari anda dapat membantu kami lebih baik.

