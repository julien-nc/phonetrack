# Εφαρμογή Phonetrack για το Nextcloud

📱 Το PhoneTrack είναι μία Nextcloud εφαρμογή για την καταγραφή και αποθήκευση τοποθεσιών από φορητές συσκευές.

🗺 Λαμβάνει πληροφορίες από εφαρμογές καταγραφής σε κινητά τηλέφωνα και τα εμφανίζει δυναμικά σε χάρτη.

🌍 Βοηθήστε μας να μεταφράσουμε αυτή την εφαρμογή στο [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Δείτε και άλλους τρόπους για να βοηθήσετε στις [οδηγίες συνεισφοράς](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Πως να χρησιμοποιήσετε το PhoneTrack:

- Δημιουργήστε μία συνεδρία καταγραφής.
- Δώστε τον σύνδεσμο καταγραφής\* στις φορητές συσκευές. Choose the [logging method](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#logging-methods) you prefer.
- Παρακολουθήστε την τοποθεσία των συσκευών της συνεδρίας σε πραγματικό χρόνο (ή όχι) στο PhoneTrack ή κοινοποιήστε σε δημόσιες σελίδες.

(\*) Don't forget to set the device name in the link (rather than in the logging app settings). Replace "yourname" with the desired device name.
Setting the device name in logging app settings only works with Owntracks, Traccar and OpenGTS.

Στην κεντρική σελίδα του PhoneTrack, ενώ παρακολουθείτε μια συνεδρία, μπορείτε :

- 📍 Να εμφανίσετε το ιστορικό τοποθεσίας
- ⛛ Να φιλτράρετε σημεία
- ✎ Να κάνετε χειροκίνητη επεξεργασία/προσθήκη/διαγραφή σημείων
- ✎ Να επεξεργαστείτε τις συσκευές σας (μετονομασία, αλλαγή χρώματος/σχήματος, μετακίνηση σε άλλη συνεδρία)
- ⛶ Να ορίσετε γεωφρακτικές ζώνες για τις συσκευές
- ⚇ Να ορίσετε ειδοποιήσεις εγγύτητας για τις συνδεδεμένες συσκευές
- 🖧 Να κοινοποιήσετε μια συνεδρία σε άλλους χρήστες του Nextcloud ή με δημόσιο σύνδεσμο (μόνο για ανάγνωση)
- 🔗 Να δημιουργήσετε συνδέσμους δημόσιας κοινοποίησης με προαιρετικούς περιορισμούς (φίλτρα, όνομα συσκευής, τελευταίες θέσεις μόνο, απλοποίηση γεωφρακτικών ζωνών)
- 🖫 Να εισάγετε/εξάγετε συνεδρίες σε μορφή GPX (ένα αρχείο με μία καταγραφή ανά συσκευή ή ένα αρχείο ανά συσκευή)
- 🗠 Να προβάλετε στατιστικά της συνεδρίας
- 🔒 [Reserve a device name](https://github.com/julien-nc/phonetrack/blob/main/doc/user.md#device-name-reservation) to make sure only authorized user can log with this name
- 🗓 Να ορίσετε ή όχι, την αυτόματη εξαγωγή και αυτόματη εκκαθάριση συνεδρίας (καθημερινά/εβδομαδιαία/μηνιαία)
- ◔ Να επιλέξετε τι θα συμβαίνει όταν το μέγιστο όριο καταγραφής σημείων επιτυγχάνεται (αποκλεισμός καταγραφής ή διαγραφή παλαιότερου σημείου)

Η δημόσια σελίδα και η δημόσια φιλτραρισμένη σελίδα λειτουργεί σαν κύρια σελίδα, με εξαίρεση αν υπάρχει μόνο μία εμφανιζόμενη συνεδρία, τα πάντα είναι μόνο για ανάγνωση και δεν υπάρχει ανάγκη σύνδεσης.

Η εφαρμογή είναι υπό κατασκευή.

## Εγκατάσταση

Δείτε το [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) για λεπτομέρειες εγκατάστασης.

Ελέγξτε το αρχείο [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) για να δείτε τι νέο περιλαμβάνεται και τι θα συμπεριληφθεί στην επόμενη έκδοση.

Ελέγξτε το αρχείο [Συντάκτες](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) για να δείτε μία πλήρη λίστα των συντακτών.

## Γνωστά προβλήματα

- Το PhoneTrack λειτουργεί με ενεργοποιημένο τον περιορισμό ομάδων του Nextcloud. See [admindoc](https://github.com/julien-nc/phonetrack/blob/main/doc/admin.md#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Οποιοδήποτε σχόλιο για τη βελτίωση της εφαρμογής θα εκτιμηθεί.

