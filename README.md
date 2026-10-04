# Rollout-Schwerpunkte 0.1.47

Geschützte PHP-/SQLite-Webanwendung zur Planung von Schwerpunkt-Projekten,
Rolloutobjekten und Unterstützungsleistungen durch FI, DSV und zwölf
Regionalverbände.

## Fachliches Zielmodell

- Unterstützungsleistungen werden ausschließlich Projekten zugeordnet.
- Im Projekt wird separat angehakt, bei welchen Rolloutobjekten eine
  Projektleistung genutzt wird.
- Im zentralen Standardkatalog gibt es keine Projekt- oder
  Rolloutobjekt-Ebene mehr.
- Zusätzliche, nicht standardisierte Leistungen gehören genau zu einem Projekt.
- A–D bezeichnet die Rolloutklasse Bankfachlich und steuert Leistungen der
  Regionalverbände kumulativ: C umfasst A, B und C.
- FI verwendet die Klassen 1–3 kumulativ: 2 umfasst 1 und 2. Die feste
  Klasse 0 kennzeichnet Rolloutobjekte, bei denen keine FI-Unterstützungsleistung
  erforderlich ist.
- Der DSV verwendet ein eigenes, unter `Leistungserbringer` konfigurierbares
  Klassenschema. Möglich sind 1–3, A–D oder zwei bis acht frei benannte,
  kumulativ geordnete Klassen. Auch beim DSV ist die vorgelagerte Klasse 0 fest
  für Rolloutobjekte ohne erforderliche DSV-Unterstützungsleistung reserviert.
- Eine Leistung kann Regionalverbänden, FI und DSV gleichzeitig zugeordnet
  sein; FI und DSV werden getrennt ausgewählt.
- „Obligatorisch“ bedeutet: Die Leistungserbringer werden nicht gefragt, ob sie
  die Leistung bereitstellen, sie ist verbindlich. Das gilt automatisch für die
  Basisklassen (Bankfachlich A, FI 1, erste DSV-Klasse) und zusätzlich für
  Leistungen mit dem Flag „obligatorisch“.
- „Immer enthalten“ ist ein eigenes Flag: Die Leistung wird jedem Rolloutobjekt
  zugeordnet, dessen Klasse passt (kumulativ), auch neuen Objekten, und kann
  weder in der Projektmatrix noch per Import abgewählt werden (z. B. ROLF;
  praktisch darf ein ROLF für das ganze Projekt geschrieben werden).
- Je Leistung wird festgelegt, ob sie obligatorisch ist und ob ein
  Termin/Zeitraum relevant ist.
- Je Projekt und Leistungserbringer wird genau ein Ansprechpartner gepflegt.
- Regionalverbände erfassen bei B–D die Bereitstellungsart: selbst, anderer
  Regionalverband, externer Dienstleister oder keine Bereitstellung.

## Dreiphasiger Excel-Ablauf

1. Für jedes Projekt wird eine TPL-/PL-Datei erzeugt. Jedes Rolloutobjekt steht
   genau einmal als Stammdatenzeile mit Zeitraum und bankfachlicher Klasse in
   der Datei. Vor dem Export kann gewählt werden, ob zusätzlich die
   Verbunddienstleisterklasse und die dazugehörigen Katalogleistungen enthalten
   sein sollen. Ohne Auswahl fehlen diese beiden Spalten vollständig und ein
   Rückimport verändert vorhandene Verbunddienstleisterangaben nicht. Darunter
   wählen TPL/PL die vorgesehenen Katalogleistungen aus: Bankfachlich kumulativ
   nach A–D und bei zugeschalteten Verbunddienstleisterangaben kumulativ nach
   1–3. Zusätzlich
   steht ein Freitextfeld für Leistungen außerhalb des Katalogs zur Verfügung.
   In dieser Phase werden Angebot und Bereitstellung noch nicht abgefragt.
2. FI und DSV erhalten getrennte Dateien. Jedes Rolloutobjekt hat dort ein
   eigenes Klassen-Dropdown. Die darunterliegenden Leistungs-Dropdowns zeigen
   kumulativ nur die zum Leistungserbringer und zur Klasse dieses konkreten
   Rolloutobjekts passenden Katalogleistungen. Klasse 0 steht in beiden
   Klassen-Dropdowns zur Verfügung und liefert konsequent eine leere
   Leistungsauswahl. Freie Ergänzungen (FI, DSV, Regionalverbände und TPL/PL)
   lassen sich beim Import entweder direkt als Standardleistung anlegen oder
   für den Standardkatalog vorschlagen. Die Auswahl
   oder freie Erfassung einer Leistung gilt unmittelbar als Planung; deshalb
   enthalten diese Dateien keine zusätzliche Spalte `Angeboten`. Die Zuordnung
   zu FI beziehungsweise DSV erfolgt über die Datei und ist nicht als sichtbare
   Spalte erforderlich. Vorschläge erscheinen unter „Offene
   Katalogvorschläge“ im Standardkatalog und werden erst nach Freigabe (mit
   Wahl der Ab-Klasse) verbindlich.
3. Die Regionalverbände erhalten danach getrennte Dateien. Die zuvor
   importierten FI-/DSV-Angaben werden je Projektleistung zur Information
   angezeigt.

Alle Arbeitsblätter sind ungeschützt. Gelbe Zellen kennzeichnen die vorgesehenen
Eingaben. Je Rolloutobjekt sind acht vorbereitete Auswahlzeilen enthalten;
zusätzliche Zeilen dürfen beliebig eingefügt oder kopiert werden.
Alle Arbeitsdateien enthalten außerdem ein Blatt `Legende` mit einer passenden
Arbeitsanleitung, Klassenlogik und dem nach Regionalverbänden, FI und DSV
strukturierten Leistungskatalog. Projektleitung und TPL Rollout werden nicht exportiert, da sie
in diesem Ablauf nicht verändert werden.
Neue oder unklare Leistungen werden nicht ungeprüft angelegt: Beim Rückimport
fragt der Importassistent, ob eine bestehende Leistung zugeordnet, eine neue
nicht standardisierte Projektleistung angelegt oder der Wert ausgelassen werden
soll.

Im Prüfschritt zeigt die Spalte „Bisher → neu“ je Zeile den aktuellen Datenbankwert
neben dem neuen Wert. Jede Zeile kann einzeln übernommen oder nicht übernommen
und vorher bearbeitet werden. Neue Leistungen lassen sich dort auch als
Standardleistung in den zentralen Katalog aufnehmen. Beim TPL-/PL-Import kann
zusätzlich „Datei ist maßgeblich“ gewählt werden: Nicht mehr genannte Leistungen
werden dann am Rolloutobjekt entfernt, Leistungen mit „Immer enthalten“
bleiben aber erhalten.
Durchgestrichene Leistungen in TPL-/PL-Excel-Dateien werden als Streichung
vorbelegt (Aktion „Streichen“ je Zeile); Leistungen mit „Immer enthalten“
bleiben auch dann erhalten.

Der Gesamtimport ist transaktional. Bei einem Fehler wird die gesamte Datei
zurückgerollt. Rückmeldungen eines Leistungserbringers verändern keine Angaben
anderer Leistungserbringer.

Jeder Import besitzt vor dem Schreiben eine verbindliche Änderungsvorschau.
Der Assistent simuliert den vollständigen Import und zeigt neue Datensätze,
geänderte Felder mit Alt-/Neuwert, geleerte Felder sowie entfernte Datensätze
oder Zuordnungen. Erst eine gesonderte Bestätigung übernimmt die angezeigten
Änderungen. Ändert sich die Datenbank zwischen Vorschau und Bestätigung, wird
der Import aus Sicherheitsgründen abgebrochen und muss neu geprüft werden.

Zusätzlich können die drei Standardkataloge für Regionalverbände, FI und DSV
als getrennte Excel-Dateien exportiert, bearbeitet und wieder importiert werden.
Jede Klasse besitzt eine eigene Ja-/Nein-Spalte. Die wirksame kumulative
Verfügbarkeit wird dabei vollständig ausgeschrieben: Eine Leistung ab A steht
auch in B, C und D auf `Ja`, eine Leistung ab 1 auch in 2 und 3. Beim Rückimport
wird die niedrigste mit `Ja` markierte Ausgangsklasse gespeichert. Weitere Spalten steuern Obligatorik,
Terminrelevanz und Aktivstatus; freie Zeilen dienen zum Anlegen neuer
Katalogleistungen. Klasse 0 erscheint absichtlich nicht in den
Katalogpflegedateien, weil ihr keine Leistung zugeordnet werden darf.

## Installation

Benötigt werden PHP 8.1 oder neuer, PHP-FPM sowie `pdo_sqlite`, `mbstring`,
`simplexml` und `zlib`. Der Caddy-Dokumentenstamm ist der Ordner `public/`.
Der PHP-FPM-Benutzer benötigt Schreibrecht auf `data/`. Für den gemeinsamen
Rückimport sollten `max_file_uploads`, `upload_max_filesize` und
`post_max_size` ausreichend groß konfiguriert sein.

Das ZIP enthält keine Datenbank. Eine Neuinstallation erzeugt direkt das
konsolidierte Schema 23 und fragt anschließend nach dem Administrationskonto.
Das Passwort benötigt mindestens neun beliebige Zeichen.

Beispiel für Caddy:

```caddyfile
rollout.example.de {
    root * /var/www/rollout-schwerpunkte/public
    encode zstd gzip
    php_fastcgi unix//run/php/php8.3-fpm.sock
    file_server
}
```

## Update einer bestehenden Datenbank

Vor dem ersten Aufruf von Version 0.1.30 muss eine vorhandene Datenbank aus
Version 0.1.22 bis 0.1.29 einmalig aktualisiert werden:

```bash
cd /var/www/rollout-schwerpunkte
php tools/update-database-0.1.30.php data/rollout.sqlite
```

Währenddessen darf die Webanwendung nicht verwendet werden. Das Skript legt
automatisch eine Sicherung `rollout.sqlite.backup-before-0.1.30-...` an,
trennt die FI- und DSV-Klassen, überführt vorhandene Rückmeldungen in die
objektbezogene Struktur und prüft anschließend Fremdschlüssel und Integrität.
Details stehen in `tools/ANLEITUNG-DATENBANK-UPDATE-0.1.30.txt`.

Eine Datenbank aus Version 0.1.20 oder 0.1.21 wird zuerst mit dem weiterhin
beiliegenden Skript 0.1.22 auf Schema 22 und danach mit dem Skript 0.1.30 auf
Schema 23 aktualisiert.

Der normale Anwendungsstart enthält keine historischen Migrationsroutinen. Eine
Datenbank mit einer anderen Schema-Version wird mit einem klaren Hinweis auf das
Wartungsskript abgewiesen.

## Importspalten

Die Anwendung erkennt insbesondere:

- Projekt und Datensatz (`Projekt`, `Rolloutobjekt` oder `Projektleistung`)
- Rolloutobjekt, Beginn, Ende sowie Bankfachlich-, FI- und DSV-Klasse
- Unterstützungsleistung sowie ihre Leistungsklassen
- `Genutzt für Rolloutobjekte` mit einer Bezeichnung je Zeile
- Leistungserbringer, Ansprechpartner und Telefonnummer
- Angebot, obligatorische Kennzeichnung, Bereitstellungsart, Partner,
  Termin/Zeitraum und Bemerkung

Aus Gründen der Abwärtskompatibilität erkennt der Importassistent weiterhin die
alten Spaltenbezeichnungen `Ebene` und `technische Rolloutklasse`. Neu erzeugte
Dateien verwenden ausschließlich die heutigen Begriffe.

## Darstellung und Sortierung

Die Projektmatrix nutzt die vollständige verfügbare Browserbreite.
Leistungsauswahlen sind zuerst nach Bankfachlichem Rollout, FI und DSV, danach
nach Klasse und innerhalb der Klasse alphabetisch sortiert. Fachliche Klassen
werden in abgestuften Rottönen, FI- und DSV-Klassen in abgestuften Blautönen
dargestellt. Allgemeine
Auswahllisten bleiben alphabetisch.

Bei der Klassenpflege einer Unterstützungsleistung werden höhere Klassen
kumulativ automatisch angehakt und grau gesperrt. Wird beispielsweise A
gewählt, sind B, C und D sichtbar enthalten; bei FI und DSV gilt dieselbe
Regel entlang der jeweiligen Klassenreihenfolge.

## Versionierung

Aktueller Stand: **0.1.47**. Die Version steht ausschließlich im obersten Eintrag
von `CHANGELOG.md` und wird oben links angezeigt; ein Klick darauf öffnet den
Änderungsverlauf. Die dritte Stelle wird bei jeder Auslieferung automatisch
erhöht; die erste und zweite Stelle nur auf ausdrückliche Anweisung.

## Sicherheit

- ausschließlich per HTTPS veröffentlichen
- regelmäßige Sicherungen der SQLite-Datei einrichten
- Zugriff nach Möglichkeit auf internes Netz oder VPN begrenzen
- vor produktiver Mehrbenutzernutzung Rollen, Passwortwechsel und
  Protokollierung ergänzen
