# Rollout-Schwerpunkte 0.1.55

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
- FI und DSV verwenden je ein eigenes, unter `Leistungserbringer`
  konfigurierbares, kumulatives Klassenschema (zwei bis acht Stufen, Standard
  1–3). Die feste Klasse 0 kennzeichnet Rolloutobjekte, bei denen keine
  Unterstützungsleistung des jeweiligen Verbunddienstleisters erforderlich ist.
- Der DSV verwendet ein eigenes, unter `Leistungserbringer` konfigurierbares
  Klassenschema. Möglich sind 1–3, A–D oder zwei bis acht frei benannte,
  kumulativ geordnete Klassen. Auch beim DSV ist die vorgelagerte Klasse 0 fest
  für Rolloutobjekte ohne erforderliche DSV-Unterstützungsleistung reserviert.
- Es gibt drei getrennte Kataloge: Bankfachlich (Regionalverbände bzw.
  Projekt), FI und DSV. Jede Leistung gehört genau einem Katalog; derselbe Name
  darf in verschiedenen Katalogen vorkommen (z. B. „Kickoff“ bankfachlich und
  bei der FI), innerhalb eines Katalogs nicht. Ältere, mehreren Anbietern
  zugeordnete Leistungen werden auf der Seite „Mehrfach zugeordnete Leistungen
  aufräumen“ (Hinweis im Katalog) je Eintrag aufgeteilt oder auf einen Anbieter
  festgelegt; der Importassistent zeigt die Änderungen vorher an.
- Erbringung: Bankfachliche Leistungen werden regional (durch die
  Regionalverbände, die dazu befragt werden) oder zentral (durch das Projekt)
  erbracht. Der Katalog gibt die Erbringung vor; je Rolloutobjekt kann sie in
  der Projektmatrix oder über die TPL-/PL-Datei geändert werden. FI- und
  DSV-Leistungen sind immer zentral.
- „Immer enthalten“ ist ein eigenes Flag: Die Leistung wird jedem Rolloutobjekt
  zugeordnet, dessen Klasse passt (kumulativ), auch neuen Objekten, und kann
  weder in der Projektmatrix noch per Import abgewählt werden (z. B. ROLF;
  praktisch darf ein ROLF für das ganze Projekt geschrieben werden).
- Je Leistung wird festgelegt, ob ein
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
   werden beim Import über „Als neue Leistung anlegen“ direkt angelegt, auf
   Wunsch als Standardleistung im zentralen Katalog. Die Auswahl
   oder freie Erfassung einer Leistung gilt unmittelbar als Planung; deshalb
   enthalten diese Dateien keine zusätzliche Spalte `Angeboten`. Die Zuordnung
   zu FI beziehungsweise DSV erfolgt über die Datei und ist nicht als sichtbare
   Spalte erforderlich.
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

## Übersicht Leistungserbringung

Die Seite „Übersicht“ wird oben in drei Schritten eingegrenzt: Projekt,
Rolloutobjekt (oder alle) und Bereich (alle, Bankfachlich, FI oder DSV). Die
Darstellung ist in vier Blöcke gegliedert: **Projekt (zentral)** – bankfachliche
Leistungen, die zentral durch das Projekt erbracht werden; **Regionalverbände
(regional)** – welcher Regionalverband selbst, über einen anderen Verband oder
einen externen Dienstleister bereitstellt; **FI** und **DSV** – die geplanten
Leistungen je Rolloutobjekt. Offene Punkte (keine Angabe oder keine
Bereitstellung) erscheinen zusätzlich je Rolloutobjekt und lassen sich als Excel
exportieren. RV-Abfragedateien und Gesamtexport enthalten die Spalte „Block“
und sind nach Block, Klasse und Name sortiert (in der RV-Abfragedatei steht der
Block „Regionalverbände (regional)“ mit den abzufragenden Leistungen oben); zentral erbrachte bankfachliche
Leistungen stehen im Gesamtexport einmal mit „Projekt“ als Erbringer statt je
Regionalverband.

## PowerPoint-Export

Auf der Seite „Übersicht“ erzeugt „Als PowerPoint“ für das gewählte Projekt eine
Präsentation auf Basis der Vorlage `resources/powerpoint/synchronisation-rollout-vorlage.pptx`
(Master, Schriften, Titel- und Schlussfolie bleiben erhalten). Inhaltsfolien:
„Status und Überblick“, „Bankfachliche Leistungen“ und „Leistungen von FI und DSV“,
alle auf dem Layout „Text-Folie #1“. Passt eine Tabelle nicht auf eine Folie,
folgen Fortsetzungsfolien. Wird die Vorlage ausgetauscht, müssen die Platzhaltertexte
„Projektname, Datum“ (Titelfolie) und „Titel der Präsentation | Name | Ort, Datum“
(Schlussfolie) sowie das Layout „Text-Folie #1“ (`slideLayout18.xml`) erhalten bleiben.

## Wer hat zuletzt geändert?

Es gilt: Wer zuletzt etwas gesagt hat, überschreibt die Angabe des anderen –
egal ob TPL-/PL-Import, Rückmeldung von FI, DSV oder Regionalverband oder
Speichern in der Projektmatrix. Jede Nutzung am Rolloutobjekt, jede Projektangabe
eines Leistungserbringers und jede Angabe am Rolloutobjekt trägt dazu einen
Herkunftsvermerk („zuletzt: Rückmeldung FI · Name, Datum“). Der Vermerk ändert
sich nur, wenn sich der Inhalt tatsächlich ändert; unverändert erneut
gespeicherte oder importierte Angaben behalten ihn. Projektmatrix und Übersicht
zeigen ihn an, die Importbestätigung nennt ihn beim bisherigen Wert.

Gleichnamige Leistungen aus verschiedenen Katalogen am selben Rolloutobjekt
(z. B. „Kickoff“ bankfachlich und bei der FI) sind erlaubt, werden aber in
Projektmatrix und Übersicht mit „⚠ Gleicher Name auch bei …“ markiert. Entsteht
eine solche Dopplung durch einen Import, weist die Importbestätigung darauf hin.

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

Die Projektmatrix nutzt die vollständige verfügbare Browserbreite. Wie die
Übersicht wird sie oben über Projekt, Rolloutobjekt und Bereich (Bankfachlich,
FI oder DSV) eingegrenzt; im Bereich Bankfachlich ist sie in die Blöcke
„Projekt (zentral)“ und „Regionalverbände (regional)“ geteilt. Gespeichert wird
nur der angezeigte Ausschnitt – andere Bereiche und Rolloutobjekte bleiben
unverändert. Oben auf der Seite lässt sich außerdem die Rolloutklasse des
gewählten Bereichs je Rolloutobjekt einstellen (ohne Umweg über das
Rolloutobjekt-Formular); beim Wechsel mit ungespeicherten Änderungen fragt die Seite nach.
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

Aktueller Stand: **0.1.55**. Die Version steht ausschließlich im obersten Eintrag
von `CHANGELOG.md` und wird oben links angezeigt; ein Klick darauf öffnet den
Änderungsverlauf. Die dritte Stelle wird bei jeder Auslieferung automatisch
erhöht; die erste und zweite Stelle nur auf ausdrückliche Anweisung.

## Sicherheit

- ausschließlich per HTTPS veröffentlichen
- regelmäßige Sicherungen der SQLite-Datei einrichten
- Zugriff nach Möglichkeit auf internes Netz oder VPN begrenzen
- vor produktiver Mehrbenutzernutzung Rollen, Passwortwechsel und
  Protokollierung ergänzen
