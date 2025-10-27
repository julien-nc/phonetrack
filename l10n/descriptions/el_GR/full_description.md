# Εφαρμογή Phonetrack για το Nextcloud

📱 Το PhoneTrack είναι μία Nextcloud εφαρμογή για την καταγραφή και αποθήκευση τοποθεσιών από φορητές συσκευές.

🗺 Λαμβάνει πληροφορίες από εφαρμογές καταγραφής σε κινητά τηλέφωνα και τα εμφανίζει δυναμικά σε χάρτη.

🌍 Βοηθήστε μας να μεταφράσουμε αυτή την εφαρμογή στο [PhoneTrack Crowdin project](https://crowdin.com/project/phonetrack).

⚒ Δείτε και άλλους τρόπους για να βοηθήσετε στις [οδηγίες συνεισφοράς](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Πως να χρησιμοποιήσετε το PhoneTrack:

- Δημιουργήστε μία συνεδρία καταγραφής.
- Give the logging link\* to the mobile devices. Choose the [logging method](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods) you prefer.
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
- 🔒 [Να δεσμεύσετε ένα όνομα συσκευής](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation) για να βεβαιωθείτε ότι μόνο ένας εξουσιοδοτημένος χρήστης μπορεί να συνδεθεί με αυτό το όνομα
- 🗓 Να ορίσετε ή όχι, την αυτόματη εξαγωγή και αυτόματη εκκαθάριση συνεδρίας (καθημερινά/εβδομαδιαία/μηνιαία)
- ◔ Να επιλέξετε τι θα συμβαίνει όταν το μέγιστο όριο καταγραφής σημείων επιτυγχάνεται (αποκλεισμός καταγραφής ή διαγραφή παλαιότερου σημείου)

Η δημόσια σελίδα και η δημόσια φιλτραρισμένη σελίδα λειτουργεί σαν κύρια σελίδα, με εξαίρεση αν υπάρχει μόνο μία εμφανιζόμενη συνεδρία, τα πάντα είναι μόνο για ανάγνωση και δεν υπάρχει ανάγκη σύνδεσης.

Αυτή η εφαρμογή ελέγχθηκε στο Nextcloud 17 με το Firefox 57+ και το Chromium.

Η εφαρμογή είναι συμβατή με θέματα χρωμάτων και προσβασιμότητας!

Η εφαρμογή είναι υπό κατασκευή.

## Εγκατάσταση

Δείτε το [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc) για λεπτομέρειες εγκατάστασης.

Ελέγξτε το αρχείο [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log) για να δείτε τι νέο περιλαμβάνεται και τι θα συμπεριληφθεί στην επόμενη έκδοση.

Ελέγξτε το αρχείο [Συντάκτες](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors) για να δείτε μία πλήρη λίστα των συντακτών.

## Γνωστά προβλήματα

- PhoneTrack **now works** with Nextcloud group restriction activated. See [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Οποιοδήποτε σχόλιο για τη βελτίωση της εφαρμογής θα εκτιμηθεί.

