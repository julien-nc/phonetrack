# Aplikacja PhoneTrack Nextcloud

PhoneTrack to aplikacja Nextcloud do śledzenia i przechowywania lokalizacji urządzeń mobilnych.

🗺 Otrzymuje informacje z telefonów komórkowych rejestrując w aplikacji, które wyświetla je dynamicznie na mapie.

🌍 Pomóż nam przetłumaczyć tę aplikację w [projekcie PhoneTrack Crowdin](https://crowdin.com/project/phonetrack).

⚒ Sprawdź inne sposoby, aby pomóc w [wytycznych dotyczących wkładu](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CONTRIBUTING.md).

Jak korzystać z PhoneTrack:

* Utwórz sesję śledzenia.
* Podaj link rejestracyjny\* na urządzenia mobilne. Wybierz preferowaną [metodę logowania](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#logging-methods).
* Oglądaj lokalizację urządzeń sesji w czasie rzeczywistym (lub nie) w aplikacji PhoneTrack lub udostępniaj ją na publicznych stronach.

(\*) Nie zapomnij ustawić nazwy urządzenia w łączu (zamiast w ustawieniach aplikacji rejestrującej). Zastąp "yourname" żądaną nazwą urządzenia. Ustawienie nazwy urządzenia w ustawieniach aplikacji rejestrującej działa tylko z Owntracks, Traccar i OpenGTS.

Na stronie głównej PhoneTrack podczas oglądania sesji możesz:

* 📍 Wyświetlić historię lokalizacji
* ⛛ Filtrować punkty
* ✎ Ręcznie edytować/dodawać/usuwać punkty
* ✎ Edytować urządzenia (zmieniać nazwę, zmieniać kolor/kształt, przechodzić do kolejnej sesji)
* ⛶ Zdefiniować wyznaczone strefy dla urządzeń
* ⚇ Zdefiniować alarmy zbliżeniowe dla pary urządzeń
* 🖧 Udostępniać sesję innym użytkownikom Nextcloud lub poprzez publiczny link (tylko do odczytu)
* 🔗 Generować publiczne łącza do akcji z opcjonalnymi ograniczeniami (filtry, nazwa urządzenia, tylko ostatnie pozycje, uproszczenie wyznaczonej strefy)
* 🖫 Importować/eksportować sesję w formacie GPX (jeden plik z jedną ścieżką na urządzenie lub jeden plik na urządzenie)
* 🗠 Wyświetlać statystyki sesji
* 🔒 [Zarezerwować nazwę urządzenia](https://gitlab.com/eneiluj/phonetrack-oc/wikis/userdoc#device-name-reservation), aby upewnić się, że tylko autoryzowany użytkownik może logować się z tą nazwą
* 🗓 Przełączać automatyczne eksportowanie sesji i automatyczne oczyszczanie (codziennie/co tydzień/co miesiąc)
* ◔ Wybrać, co zrobić, gdy zostanie osiągnięty limit liczby punktów (zablokować rejestrowanie lub usuwać najstarszy punkt)

Strona publiczna i strona publiczna filtrowana działają jak strona główna, z wyjątkiem wyświetlania tylko jednej sesji, wszystko jest tylko do odczytu i nie trzeba się logować.

Ta aplikacja jest testowana na Nextcloud 15 z Firefoxem 57+ i Chromium.

Ta aplikacja jest kompatybilna z tematycznymi kolorami i dostępnymi motywami!

Ta aplikacja jest w trakcie opracowywania.

## Instalacja

Zobacz szczegóły instalacji w [AdminDoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc).

Sprawdź plik [CHANGELOG](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/CHANGELOG.md#change-log), aby zobaczyć, co nowego i co nadchodzi w następnym wydaniu.

Sprawdź plik [AUTHORS](https://gitlab.com/eneiluj/phonetrack-oc/blob/master/AUTHORS.md#authors), aby wyświetlić pełną listę autorów.

## Znane problemy

* PhoneTrack **działa teraz** z aktywacją ograniczenia grupy Nextcloud. Zobacz [admindoc](https://gitlab.com/eneiluj/phonetrack-oc/wikis/admindoc#issue-with-phonetrack-restricted-to-some-groups-in-nextcloud).

Wszelkie opinie będą doceniane.