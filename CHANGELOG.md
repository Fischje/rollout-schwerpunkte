# Änderungsverlauf

## 0.1.73

- Neu: „Stand der Rückmeldungen der Regionalverbände“ unter Import (Phase 3). Je Regionalverband Häkchen „übernommen“, Datum, „Erhalten von“, Weg und Bemerkung. Der Import einer RV-Rückmeldedatei setzt Häkchen, Datum und Weg automatisch; „Liste zurücksetzen“ leert die Liste für eine neue Abfragerunde
- Neu: Menüpunkt „RV-Sicht“. Je Regionalverband alle Rolloutleistungen, zu denen er gefragt wird, über alle Projekte in einer Tabelle: Bereitstellungsart (mit anderem RV bzw. externem Dienstleister), Termin, Bemerkung und Ansprechpartner je Projekt – für Antworten aus Terminen oder pauschale Antworten ohne Excel-Datei. Gespeichert wird wie beim Import der RV-Datei, mit Herkunftsvermerk „RV-Sicht <RV>“
- In der RV-Sicht lassen sich je Projekt weitere Leistungen ergänzen: eine passende Katalogleistung oder eine eigene, frei beschriebene Leistung, jeweils mit Auswahl der Rolloutobjekte (keins angehakt = alle passenden)
- RV-Sicht: Filter nach Projekt und „nur offene“, „Pauschale Antwort“ füllt alle angezeigten offenen Leistungen auf einmal; beim Speichern kann die Rückmeldung direkt im Stand der Rückmeldungen vermerkt werden
- Keine Änderung am Datenbankschema (der Stand der Rückmeldungen liegt in den Einstellungen der Anwendung)

## 0.1.72

- Die Pflege der strategischen Themenfelder ist von der Seite „Projektanpassung“ in die Einstellungen umgezogen (neuer Reiter „Strategische Themenfelder“). Je Themenfeld steht dort, wie viele Projekte ihm zugeordnet sind, darunter die Zahl der noch nicht zugeordneten Projekte
- Das Projektformular verlinkt beim Feld „Strategisches Themenfeld“ direkt auf den neuen Reiter

## 0.1.71

- Aufräumen: Altlasten in Code und Datenbank entfernt. Neues Datenbankschema 24; eine bestehende Datenbank wird einmalig mit `php tools/datenbank-bereinigen.php` umgestellt (mit Sicherung, Prüfung und Bericht). Bis dahin zeigt die Anwendung einen Wartungshinweis mit dem Befehl
- Entfallen: die frühere FI-Klassenspalte an Rolloutobjekten (die FI-Klasse steht nur noch im Klassenschema), das Kennzeichen „obligatorisch“ samt der nie mehr wirksamen „Verbindlich“-Darstellung, die Tabelle und Liste der alten Katalogvorschläge sowie die Seite „Mehrfach zugeordnete Leistungen aufräumen“
- Gesamtexport ohne die leere Spalte „Obligatorisch“; Katalogdateien verlangen die Spalten „Zentral erbracht“ und „Immer enthalten“
- Ältere Dateiformate (Sammelspalten der frühen TPL-Dateien, Zeilentyp „Projektleistung“, alte Spaltennamen) werden nicht mehr gelesen; einfache Schreibvarianten für selbst erstellte Strukturdateien bleiben erlaubt
- Beim Start laufen keine Migrationsroutinen mehr; die alten Update-Skripte 0.1.22 und 0.1.30 und die Datei `beispiel-import.csv` sind entfernt
- Hinweistext im Katalog-Editor korrigiert: Jede Leistung gehört genau einem Katalog

## 0.1.70

- E-Mail-Vorgabe nach Vorgabe des Fachbereichs neu formuliert: Abfrage der Unterstützungsleistungen des Regionalverbands, Bereitstellung nur für nicht zentral bereitgestellte Leistungen, Termin/Bemerkungen/Ansprechpartner „soweit möglich“. Ein unverändert gespeicherter Vorschlag aus 0.1.69 wird automatisch ersetzt

## 0.1.69

- E-Mail-Vorgabe ergänzt: Die Regionalverbände können zusätzlich zu den vorgegebenen Unterstützungsleistungen je Projekt eigene Leistungen ergänzen (Zeilen „Neue Rolloutleistung“, mit Angabe der Rolloutobjekte). Ein unverändert gespeicherter Vorschlag aus 0.1.68 wird automatisch ersetzt

## 0.1.68

- Korrektur E-Mail-Entwürfe: Der vorgeschlagene Text fragt jetzt die Unterstützungsleistungen und ihre Bereitstellung ab (nicht die Rolloutschwerpunkte) und beginnt mit „Sehr geehrte Kolleginnen und Kollegen“. Ein unverändert gespeicherter alter Vorschlag wird automatisch ersetzt
- Neuer Button „Auf Vorgabe zurücksetzen“ für Betreff und Text

## 0.1.67

- Neu: E-Mail-Entwürfe für Outlook zur RV-Abfrage (Export, Phase 3). Je Regionalverband eine .eml-Datei an alle Rollout-Ansprechpartner mit der RV-Datei im Anhang; Outlook öffnet sie per Doppelklick als ungesendeten Entwurf
- Betreff und Text sind vorher in einem Eingabefeld bearbeitbar und werden gespeichert; Platzhalter {Regionalverband}, {Ansprechpartner}, {Jahr} und {Datum}
- Download einzeln je Regionalverband oder alle als ZIP; die Zahl auf den Buttons zeigt die Empfänger, Regionalverbände ohne E-Mail-Adresse sind gelb markiert

## 0.1.66

- Rollout-Ansprechpartner: je Leistungserbringer jetzt beliebig viele Personen mit E-Mail-Adresse („+ weiterer Ansprechpartner“, × zum Entfernen). Ein in 0.1.65 eingetragener Ansprechpartner wird automatisch übernommen
- Neue Excel-Datei „Rollout-Ansprechpartner“ unter Export → Stammdaten (eine Zeile je Person, Leistungserbringer per Auswahlliste, freie Zeilen für Ergänzungen) und passender Upload unter Import → Stammdaten mit Änderungsvorschau. Für jeden Leistungserbringer in der Datei ersetzt sie die bisherige Liste; nicht enthaltene Leistungserbringer bleiben unverändert
- Ungültige E-Mail-Adressen, unbekannte Leistungserbringer und doppelte Einträge werden mit Zeilenangabe abgelehnt

## 0.1.65

- Neues Menü: Übersicht, Projektanpassung, Einstellungen, Import/Export. Die Übersicht ist jetzt die Startseite (auch beim Klick auf das Logo); die bisherige Seite „Projekte“ heißt „Projektanpassung“
- Einstellungen mit den Reitern Standard-Leistungskatalog, Leistungserbringer und Klassenschema. Die Klassenschemata von FI und DSV sowie die Kombinationsmöglichkeiten stehen jetzt auf dem eigenen Reiter „Klassenschema“
- Leistungserbringer: getrennte Abschnitte „Regionalverbände“ und „Dienstleister“ (FI, DSV); neu für alle ein Rollout-Ansprechpartner mit E-Mail-Adresse, unabhängig von den Ansprechpartnern je Projekt. Bestehende Datenbanken werden automatisch ergänzt
- Import/Export mit den Reitern Export und Import, beide nach Phasen gegliedert: Phase 1 TPL/PL, Phase 2 FI und DSV (als auslaufend gekennzeichnet), Phase 3 Regionalverbände, beim Export zusätzlich Phase 4 „Auswertung und Präsentation“ sowie die Stammdaten (Leistungskataloge)
- Export Phase 4 bündelt Gesamtbild (Excel/CSV), offene Punkte als Excel (einzelnes Projekt oder alle), Projektübersicht als PowerPoint und Projekttabelle als PowerPoint. Die Buttons „Als PowerPoint“ und „Offene Punkte als Excel“ auf der Übersicht entfallen
- Import: je Phase ein eigenes Upload-Feld, die Importart ergibt sich daraus. Katalogdateien und neue Strukturdaten stehen unter „Stammdaten“, die erwarteten Spalten als aufklappbare Hilfe

## 0.1.64

- RV-Abfragedatei: „Genutzt für Rolloutobjekte“ steht jetzt bei den Eingabespalten direkt hinter der Leistungsklasse. In den Zeilen „Neue Rolloutleistung“ ist das Feld auszufüllen (gelb) und bietet eine Auswahlliste mit den Rolloutobjekten des jeweiligen Projekts sowie „Alle Rolloutobjekte“; mehrere Objekte sind per Zeilenumbruch in der Zelle oder durch Kopieren der Zeile möglich
- Rückimport: Für neue Rolloutleistungen aus einer Rückmeldung werden die genannten Rolloutobjekte als Nutzung übernommen (bisher wurde das Feld bei Rückmeldungen nicht ausgewertet). Bei bestehenden Leistungen bleibt die Spalte reine Information
- Korrektur: Der Importassistent behielt die Kennzeichnung „Neue Rolloutleistung“ beim Prüfschritt nicht bei

## 0.1.63

- Neu: Strategische Themenfelder. Jedes Projekt kann einem der neun Themenfelder zugeordnet werden (Projektformular „Strategisches Themenfeld“); die Projektliste zeigt das Themenfeld unter dem Projektnamen
- Die neun Namen sind unten auf der Seite „Projekte“ unter „Strategische Themenfelder bearbeiten“ pflegbar; vorbelegt sind 1. Beratung, Produkte und Services im Multikanal (PK/FK), 2. Immobilien / Wohnen, 3. Payment, 4. Personal und 6. Effizienz und Standardisierung
- Projekttabelle als PowerPoint wieder vollständig wie die Vorlage: dunkle Themenfeld-Spalte links (senkrechter Text, zusammengefasst über alle Projekte des Feldes), Zeile „nach strategischen Themenfeldern“ und die neun Themenfeld-Bilder. Sortierung zuerst nach Themenfeld, dann nach Projektname; Projekte ohne Themenfeld stehen am Ende („Ohne Themenfeld“)
- Die gleichmäßige Verteilung auf die Folien berücksichtigt die Mindesthöhe des senkrechten Themenfeld-Textes; die Tabelle endet weiterhin spätestens 2 cm über dem unteren Rand

## 0.1.62

- Projekttabelle als PowerPoint: Die Tabelle endet spätestens 2 cm über dem unteren Folienrand. Zeilenhöhen werden großzügiger berechnet, weil die Zellen „Projektname“ und „Rollout-Klasse“ wie in der Vorlage Innenabstand haben – bisher konnten die Tabellen in PowerPoint weiter nach unten wachsen als geplant
- Die Projekte werden gleichmäßig auf die Folien verteilt: zuerst wird die kleinste nötige Folienzahl ermittelt, dann werden die Projekte in ihrer Reihenfolge so aufgeteilt, dass alle Folien möglichst gleich voll sind. Ein Projekt bleibt weiterhin zusammen, sofern es allein auf eine Folie passt

## 0.1.61

- Anmeldung hält länger: abgemeldet wird erst nach 12 Stunden ohne Aktivität (bisher PHP-Vorgabe: 24 Minuten) und nicht mehr beim Schließen des Browsers. Jeder Seitenaufruf verlängert die Sitzung
- Dauer einstellbar über `session_lifetime_hours` in `config.php` (mindestens 15 Minuten)
- Sitzungen liegen jetzt im Ordner `data/sessions` der Anwendung, damit der Aufräumjob des Servers sie nicht vorzeitig löscht (der PHP-Benutzer braucht dort Schreibrechte – wie schon für die Datenbank in `data/`)

## 0.1.60

- Neu unter „Import / Export“: „Projekttabelle als PowerPoint“ – alle Projekte mit ihren Rolloutobjekten, Rollout-Klasse, Rollout-Beginn und Rollout-Ende im Format der Tabelle „Rolloutschwerpunkte“ (Vorlage `resources/powerpoint/rolloutschwerpunkte-tabelle-vorlage.pptx`): gleiche Folie, Spalten, Schriftgrößen, Farben und Rahmen; ohne Zuordnung zu strategischen Themenfeldern
- Titel (Vorgabe „Rolloutschwerpunkte <nächstes Jahr>“) und Fußzeile lassen sich vor dem Export anpassen. Projekte bleiben auf einer Folie zusammen; reicht der Platz nicht, folgt eine weitere Folie mit gleichem Aufbau
- PowerPoint-Export allgemein: Nicht mehr verwendete Bilder der Vorlage werden aus der erzeugten Datei entfernt

## 0.1.59

- Projektmatrix, Bereich Bankfachlich: Wird die Erbringung einer Leistung auf „zentral“ bzw. „regional“ umgestellt, wandert sie sofort in den Block „Projekt (zentral)“ bzw. „Regionalverbände (regional)“ – in der Nutzungstabelle und in der Matrix der Leistungserbringer, einsortiert nach Klasse und Name. Bei „Alle Rolloutobjekte“ gilt: regional, sobald die Leistung an mindestens einem genutzten Objekt regional erbracht wird
- Angaben der Regionalverbände zu einer Leistung, die vor dem Speichern auf zentral umgestellt wird, werden nicht mitgespeichert (zentral wird nicht abgefragt)

## 0.1.58

- Projektmatrix: Wird oben eine Standardleistung angehakt, erscheint sie sofort unten in „Nutzung je Rolloutobjekt“ und in der Matrix der Leistungserbringer; wird sie abgewählt, verschwindet sie dort sofort. Übernommen wird wie bisher mit „Angezeigten Ausschnitt speichern“
- Ist ein einzelnes Rolloutobjekt gewählt, erscheint eine Leistung in der Matrix der Leistungserbringer, sobald sie an diesem Objekt angehakt ist, und verschwindet beim Abhaken wieder

## 0.1.57

- Begriff: Aus „Projektleistung“ wird überall „Rolloutleistung“ (Oberfläche, Exporte, Datensatz-Spalte). Ältere Dateien mit „Projektleistung“ lassen sich weiterhin importieren
- RV-Abfragedatei neu sortiert: Projekt, Datensatz, Rolloutleistung, Leistungsklasse, Erbringung, dann die Eingaben Bereitstellungsart, anderer Regionalverband / externer Dienstleister, Termin / Zeitraum, Bemerkung, Ansprechpartner, Telefonnummer; Informationsspalten (genutzt für Rolloutobjekte, erbracht durch, Block, FI-/DSV-Planung, Projektleitung usw.) stehen dahinter
- RV-Abfragedatei: Eine Spalte „Leistungsklasse“ zeigt bei jeder Leistung die Klasse im eigenen Katalog (A–D, „FI 1“, „DSV …“); bisher fehlte z. B. die DSV-Klasse. Nicht mehr benötigte Spalten (Rolloutobjekt, Beginn/Ende, „Angeboten“, „Obligatorisch“) entfallen
- Projektliste aufgeräumt: Die Rolloutobjekte stehen kompakt neben dem Projekt (Zeitraum und Klassen Bankfachlich/FI/DSV, Link direkt in die Matrix des Objekts); ab sieben Objekten klappt der Rest unter „+ n weitere“ ein. Auf dem Handy werden Projekt und Objekte untereinander gezeigt

## 0.1.56

- PowerPoint-Export: Die Unterzeile der FI-/DSV-Folie nennt nur noch die Zahl der Leistungen je Bereich im Projekt (ohne die aufaddierten Planungen)
- Schlussfolie der Vorlage: Satz korrigiert zu „Die Unterlage ist eine interne Arbeitsunterlage zur Verwendung zwischen Regionalverbänden, den Verbunddienstleistern und dem DSGV – daher vertraulich zu behandeln.“

## 0.1.55

- Neu: Übersicht eines Projekts als PowerPoint („Als PowerPoint“ auf der Übersichtsseite, sobald ein Projekt gewählt ist). Grundlage ist die Vorlage „Synchronisation Rollout“ (`resources/powerpoint/`) mit Master, Schriften, Titel- und Schlussfolie
- Aufbau: Titelfolie mit Projekt und Datum, „Status und Überblick“ (Kennzahl-Kacheln, Balken der RV-Angaben, Rolloutobjekte mit Klassen und offenen Punkten), „Bankfachliche Leistungen“ (Projekt (zentral) und Farbmatrix Leistung × Regionalverband), „Leistungen von FI und DSV“ (Leistung × Rolloutobjekt), Schlussfolie
- Reicht der Platz nicht, entstehen Fortsetzungsfolien mit gleichem Titel („(1/2)“) und wiederholter Kopfzeile; der Inhalt wird gleichmäßig verteilt. Ab neun Rolloutobjekten wird die FI-/DSV-Matrix spaltenweise aufgeteilt
- Fußzeile „Synchronisation Rollout | Projekt | Datum“ und Seitenzahl auf allen Inhaltsfolien; Tabellen und Kacheln bleiben in PowerPoint bearbeitbar

## 0.1.54

- Projektmatrix: Die Rolloutklasse des gewählten Bereichs (Bankfachlich, FI oder DSV) lässt sich jetzt direkt oben auf der Seite einstellen – für das gewählte Rolloutobjekt oder, bei „Alle Rolloutobjekte“, für jedes Objekt des Projekts. Sie wird mit „Angezeigten Ausschnitt speichern“ übernommen; die Leistungsauswahl darunter richtet sich danach. Ein Wechsel zum Rolloutobjekt-Formular ist dafür nicht mehr nötig
- Wird eine Klasse gesenkt, entfallen die Nutzungen von Leistungen, die für die neue Klasse nicht mehr passen (wie beim Bearbeiten des Rolloutobjekts)

## 0.1.53

- Herkunftsvermerk („Letzter gilt“): Nutzungen am Rolloutobjekt, Projektangaben der Leistungserbringer und Angaben am Rolloutobjekt merken sich, wer sie zuletzt inhaltlich geändert hat – TPL-/PL-Import, Rückmeldung FI/DSV/Regionalverband oder Projektmatrix, mit Name und Zeitpunkt. Unverändert erneut gespeicherte oder importierte Angaben behalten ihren Vermerk
- Anzeige des Vermerks in Projektmatrix und Übersicht; die Importbestätigung zeigt beim bisherigen Wert, von wem er stammt
- Dopplungshinweis: Gleichnamige Leistungen aus verschiedenen Katalogen am selben Rolloutobjekt werden in Projektmatrix und Übersicht markiert; entsteht eine solche Dopplung durch einen Import, weist die Importbestätigung darauf hin
- RV-Abfragedatei: Der Block „Regionalverbände (regional)“ mit den abzufragenden Leistungen steht jetzt oben
- Korrektur: Die TPL-/PL-Datei führte DSV-Leistungen (und ohne Anbieterdaten auch FI-Leistungen) in der Spalte „außerhalb des Katalogs“, was beim Rückimport zu nicht zuordenbaren Zeilen führte. Sie enthält jetzt nur noch bankfachliche und – mit Anbieterdaten – FI-Leistungen; „Datei ist maßgeblich“ entfernt keine DSV-Leistungen mehr

## 0.1.52

- Projektmatrix und Übersicht werden oben schrittweise eingegrenzt: 1. Projekt, 2. Rolloutobjekt (oder alle), 3. Bereich (Bankfachlich, FI, DSV; in der Übersicht zusätzlich „Alle Bereiche“) – dadurch steht deutlich weniger auf einer Seite
- Darstellung in vier Blöcken: Projekt (zentral), Regionalverbände (regional), FI und DSV; innerhalb der Blöcke nach Klasse und Name sortiert
- Die Projektmatrix speichert nur den angezeigten Ausschnitt (Bereich und ggf. Rolloutobjekt); andere Bereiche und Objekte bleiben unverändert. Beim Wechsel der Auswahl mit ungespeicherten Änderungen fragt die Seite nach
- Die Übersicht hat einen eigenen Block „Projekt (zentral)“ mit den zentral erbrachten bankfachlichen Leistungen; Kennzahlen und offene Punkte beziehen sich auf die Auswahl
- Gesamtexport und RV-Abfragedatei: neue Spalte „Block“, Sortierung nach Block, Klasse und Name; zentral erbrachte bankfachliche Leistungen erscheinen im Gesamtexport einmal mit „Projekt“ als Erbringer statt zwölfmal je Regionalverband
- Der Link „Matrix“ in der Projektansicht öffnet die Matrix direkt für das jeweilige Rolloutobjekt
- Korrektur: FI-Leistungsauswahl in der Projektmatrix brach bei numerischen Klassen mit einem Fehler ab

## 0.1.51

- Drei getrennte Kataloge: Jede Leistung gehört genau einem Anbieter (Bankfachlich, FI oder DSV). Gleiche Namen sind in verschiedenen Katalogen erlaubt (z. B. „Kickoff“ bankfachlich und bei der FI), innerhalb eines Katalogs nicht
- Katalog-Editor, Leistungseditor und Importassistent wählen den Katalog per Auswahlknopf (genau einer); angezeigt werden nur die Klassen des gewählten Katalogs
- Neue Seite „Mehrfach zugeordnete Leistungen aufräumen“ (Hinweis im Katalog): je Leistung aufteilen (je Anbieter ein eigener Eintrag mit dessen Klasse, Rückmeldungen und Nutzung wandern mit) oder nur einen Anbieter behalten; Vorschau der Änderungen vor dem Speichern
- Importe ordnen Leistungen über die neue Spalte „Leistung-ID“ bzw. den Namen im passenden Katalog zu; FI- und DSV-Rückmeldungen finden die Leistung im Katalog des erkannten Anbieters
- Korrektur: Die Bereinigung ungültiger Objektzuordnungen berücksichtigte die FI- und DSV-Klassen der Rolloutobjekte nicht (PHP-Warnung, FI-Zuordnungen gingen verloren)

## 0.1.50

- FI-Klassenschema ist wie beim DSV unter „Leistungserbringer“ einstellbar (zwei bis acht Stufen mit eigenen Bezeichnungen, Klasse 0 fest); Auswahllisten, Excel-Dateien und Prüfungen verwenden das gepflegte Schema
- Neue Grafik „Kombinationsmöglichkeiten der Klassen“ unter den Klassenschemata: Bankfachlich A–D × FI- bzw. DSV-Klassen mit der Zahl der Rolloutobjekte je Kombination
- „Obligatorisch“ entfällt und wird durch die Erbringung ersetzt: Bankfachliche Leistungen werden entweder regional (durch die Regionalverbände) oder zentral (durch das Projekt) erbracht; FI- und DSV-Leistungen sind immer zentral
- Die Erbringung hat eine Vorgabe im Katalog (Katalog-Editor, Leistungseditor, Katalog-Excel „Zentral erbracht“) und lässt sich je Rolloutobjekt ändern: in der Projektmatrix unter jedem Haken sowie über die neue Spalte „Erbringung“ der TPL-/PL-Datei; der Importassistent zeigt die Änderung im Vergleich „Bisher → neu“
- Die automatische Regel „Klasse A bzw. 1 ist obligatorisch“ ist entfernt; bei der Umstellung werden bisher als obligatorisch markierte Leistungen zu „zentral“, alle anderen zu „regional“
- RV-Abfragedatei, Projektmatrix, Übersicht und Gesamtexport fragen bzw. werten nur noch regional zu erbringende Leistungen aus; zentrale Leistungen erscheinen als Information („zentral durch das Projekt erbracht“), die Rolloutobjekte sind nach regional/zentral gekennzeichnet
- Katalog und Projektmatrix kennzeichnen Leistungen mit „Zentral“ bzw. „Regional (RV)“ statt „Obligatorisch“
- Korrektur: Tabellenblattnamen in Excel-Exporten werden wieder korrekt bereinigt (bisher PHP-Warnung)

## 0.1.49

- Neue Seite „Übersicht“ (Hauptmenü): Alle Projekte mit Stand der Zusagen der Regionalverbände, Zahl der offenen Punkte sowie geplanten FI- und DSV-Leistungen
- Projektansicht mit Kennzahlen und farbiger Matrix der bankfachlichen Leistungen der TPL × zwölf Regionalverbände (selbst, über anderen Regionalverband, externer Dienstleister, verbindlich, offen, keine Bereitstellung); Details wie übernehmender Verband oder Dienstleister per Mauszeiger
- „Offene Punkte je Rolloutobjekt“: welche Leistungen an welchem Objekt noch ohne Zusage, Übergabe an einen anderen Verband oder externe Lösung sind und bei welchen Verbänden
- Eigene Abschnitte für die Leistungen der FI und des DSV je Rolloutobjekt (geplant, vorgesehen ohne Planung, nicht vorgesehen, für die Klasse nicht relevant)
- „Offene Punkte als Excel“ für ein Projekt oder alle Projekte, mit Ansprechpartnern der Regionalverbände
- RV-Abfragedatei: neue Spalte „Erbracht durch“ zeigt je Leistung, wer sie erbringt (Regionalverbände bankfachlich, FI, DSV) und ob die Angabe des Verbands erforderlich, verbindlich oder nur Information ist; die Legende erklärt die Spalte
- Gesamtexport: neue Spalten „Art des Leistungserbringers“, „Zuständig laut Katalog“, „Stand der Leistungserbringung“ und „Leistung erbracht durch“ (bei Übergabe der übernehmende Verband bzw. externe Dienstleister)

## 0.1.48

- Kein Vorschlagswesen mehr im Import: Neue Leistungen werden ausschließlich über „Als neue Leistung anlegen“ festgelegt und beim Bestätigen direkt angelegt, auf Wunsch als Standardleistung im zentralen Katalog; gleichnamige Zeilen ergeben genau eine Leistung
- In DSV-Dateien als Katalogleistung markierte Einträge sind im Prüfschritt mit „Als neue Leistung anlegen“ und Haken für den Standardkatalog vorbelegt
- Offene Katalogvorschläge aus früheren Importen sind im Standardkatalog nach Name und Zielkatalog zusammengefasst; eine Freigabe legt die Leistung einmal an oder verwendet die bereits vorhandene gleichnamige Standardleistung und ordnet sie allen Fundstellen zu; „Ablehnen“ gilt ebenfalls für die ganze Gruppe
- Die Meldung „Eine Leistung mit diesem Namen existiert bereits“ bei der Freigabe entfällt dadurch

## 0.1.47

- „Für den Standardkatalog vorschlagen“ gibt es jetzt bei allen Importen mit Leistungen: TPL-/PL-Planung, Rückmeldungen der FI, des DSV und der Regionalverbände (bisher nur DSV)
- Vorschläge landen im Standardkatalog unter „Offene Katalogvorschläge“ mit Zielkatalog (RV, FI oder DSV), Herkunft und Projekt/Rolloutobjekt; bei der Freigabe wird die Ab-Klasse gewählt (vorbelegt mit der Klasse des Rolloutobjekts)
- Nach der Freigabe ist die Leistung Standardleistung des jeweiligen Katalogs und wird dem Projekt und, falls passend, dem Rolloutobjekt zugeordnet; Angaben des vorschlagenden Leistungserbringers werden übernommen
- Alternativ bleibt der direkte Weg „Als neue Leistung anlegen“ mit Haken „In den zentralen Standardkatalog aufnehmen“ für alle Quellen erhalten
- Bestehende Datenbanken werden beim ersten Start automatisch umgestellt; vorhandene DSV-Vorschläge bleiben erhalten

## 0.1.46

- Rückmeldeimport: „Dem zentralen Katalog vorschlagen“ erscheint nur noch bei DSV-Dateien; bei FI und Regionalverbänden führte die Auswahl bisher zur Fehlermeldung „Zentrale Katalogvorschläge sind … dem DSV vorbehalten“
- „Als neue Leistung anlegen“ hat jetzt auch bei Rückmeldungen das Feld „Neue Leistung festlegen“: Aufnahme in den zentralen Standardkatalog per Haken und Ab-Klasse des jeweiligen Leistungserbringers (vorbelegt mit der Klasse des Rolloutobjekts); zuständig ist automatisch der oben gewählte Leistungserbringer
- Die im Feld gewählte Klasse hat Vorrang vor der Klasse des Rolloutobjekts (auch beim DSV)
- Jede Zeile im Prüfschritt zeigt ihre Zeilennummer, damit Meldungen wie „Zeile 6: …“ zuzuordnen sind; die Meldung beim Katalogvorschlag nennt jetzt den richtigen Weg

## 0.1.45

- Rückmeldeimport: Die Auswahl „Zuordnung Leistung“ zeigt nur noch die Leistungen des oben gewählten Leistungserbringers (FI, DSV oder Regionalverbände), sortiert nach dessen Ab-Klasse und mit Klassenangabe (z. B. „FI ab Klasse 2“); bei Wechsel des Leistungserbringers passt sich die Liste sofort an
- Eine bereits gewählte, aber nicht passende Leistung bleibt sichtbar und ist mit „nicht für … vorgesehen“ gekennzeichnet
- Die Auswahl des Leistungserbringers oben hat jetzt Vorrang vor der automatisch erkannten Zuordnung
- Neue Leistungen, die in mehreren Zeilen unter demselben Namen angelegt werden, entstehen nur einmal; Folgezeilen übernehmen die Festlegung der ersten Zeile automatisch (Katalogaufnahme, Zuständigkeit, Klassen) und zeigen einen Hinweis statt eines eigenen Eingabefelds
- Abweichende Festlegungen für denselben Namen werden vor dem Import mit Zeilenangabe abgelehnt; Schreibvarianten (Groß-/Kleinschreibung, Leerzeichen) führen nicht mehr zu doppelten Leistungen

## 0.1.44

- Rückmeldungen von FI und DSV: Der Prüfschritt zeigt statt „Klasse Bankfachlich“ die Spalte „Rolloutklasse FI / DSV“ mit einer Auswahl aus dem Klassenschema des zugeordneten Leistungserbringers (FI 1–3, DSV-Schema, jeweils inklusive Klasse 0)
- Die Klasse wird an der Objektzeile gewählt und an den Leistungszeilen darunter angezeigt; eine Änderung wirkt sofort auch auf diese Zeilen
- „Bisher → neu“ zeigt die Änderung der FI-/DSV-Klasse; weicht der Zeitraum in der Datei ab, erscheint ein Hinweis, dass er in dieser Phase nicht übernommen wird (Beginn und Ende zeigen den Stand der Datenbank)
- Spalten nur für Regionalverbände (Angeboten, Bereitstellungsart, anderer RV / externer DL) sind ausgeblendet, solange keine Datei einem Regionalverband zugeordnet ist; FI-/DSV-Leistungen gelten im Vergleich korrekt als „Geplant: Ja“
- Korrektur: Bei gleichlautenden Klassen von FI und DSV (z. B. „2“) wird jetzt die Klasse des richtigen Leistungserbringers vorbelegt

## 0.1.43

- Neues Flag „Immer enthalten“ an Standard- und zusätzlichen Projektleistungen (Katalog-Editor, Leistungseditor, Katalog-Excel mit neuer Spalte; ältere Katalogdateien ohne Spalte bleiben importierbar)
- Leistungen mit „Immer enthalten“ werden automatisch jedem Rolloutobjekt zugeordnet, dessen Klasse passt (kumulativ), auch neu angelegten oder umgestuften Objekten
- In der Projektmatrix sind sie angehakt und gesperrt; „Streichen“ und „Datei ist maßgeblich“ im Import entfernen sie nicht
- „Obligatorisch“ schützt nicht mehr vor dem Streichen: Es regelt nur, dass die Leistungserbringer nicht nach der Bereitstellung gefragt werden (Basisklassen A/1/erste DSV-Klasse automatisch oder per Flag)
- Katalogliste und Projektmatrix kennzeichnen „Immer enthalten“, „Obligatorisch“ und „Obligatorisch (Basisklasse …)“ einheitlich
- Korrektur: Der TPL-/PL-Import entfernte bisher die Zuordnungen einer genannten Leistung an allen anderen Rolloutobjekten des Projekts; jetzt bleiben nicht genannte Zuordnungen unverändert und entfernt wird nur per „Streichen“ oder „Datei ist maßgeblich“ (die Zählung entfernter Zuordnungen stimmt wieder)
- Bestehende Datenbanken erhalten die neue Spalte beim ersten Start automatisch; das Schema bleibt Version 23

## 0.1.42

- Neue Leistungen werden im Importassistenten über ein eigenes Feld „Neue Leistung festlegen“ angelegt: Haken für den zentralen Standardkatalog, Auswahl der Erbringer (Regionalverbände, FI, DSV) sowie je Erbringer die Ab-Klasse (bankfachlich A–D, FI 1–3, DSV-Schema)
- Die Spalte „Zuständig bei neuer Leistung“ entfällt; Klasse und Zuständigkeit werden nur bei den gewählten Erbringern gespeichert, auch die DSV-Klasse im TPL-/PL-Import
- „Vorhandenes Rolloutobjekt“ zeigt nur noch den Namen des Rolloutobjekts ohne Projektnamen
- In der Planungsansicht stehen Beginn, Ende und Klasse des Rolloutobjekts auch an jeder Leistungszeile darunter (schreibgeschützt, aktualisieren sich live bei Änderungen an der Objektzeile); Objektzeilen sind farbig hervorgehoben
- Verständlichere Fehlermeldung, wenn eine Leistung nicht zur Klasse des Rolloutobjekts passt

## 0.1.41

- Excel-Import erkennt durchgestrichene Zellen (Zellformat und Textabschnitte); in TPL-/PL-Dateien gelten durchgestrichene Leistungen als von der TPL gestrichen
- Solche Zeilen sind im Prüfschritt mit „In Excel durchgestrichen“ gekennzeichnet und mit der neuen Aktion „Streichen (am Objekt entfernen)“ vorbelegt; die Aktion lässt sich je Zeile auf „Übernehmen“ oder „Nicht übernehmen“ ändern
- „Bisher → neu“ zeigt vorab, ob die Leistung am Objekt tatsächlich entfernt wird, nicht eingetragen ist (nichts zu tun) oder als obligatorische Leistung (Klasse A) erhalten bleibt
- Die Aktion „Streichen“ wirkt je Zeile und unabhängig vom Schalter „Datei ist maßgeblich“; nutzt danach kein Rolloutobjekt des Projekts die Leistung mehr, entfällt auch die Projektleistung; alles erscheint vorab als „Wird entfernt“
- „Alle übernehmen“ belässt durchgestrichene Zeilen auf „Streichen“; außerhalb des TPL-/PL-Imports sind sie standardmäßig abgewählt

## 0.1.40

- Import-Prüftabelle ist nicht mehr unbegrenzt breit: Spaltenköpfe und Vergleichsspalte brechen um, Eingabefelder passen sich der Spaltenbreite an
- Standardmäßig zeigt die Tabelle nur die für die Entscheidung nötigen Spalten; Projekt, Bemerkung und Detailspalten lassen sich mit „Alle Spalten anzeigen“ einblenden und bleiben auch ausgeblendet bearbeitbar und Teil des Imports

## 0.1.39

- Die Versionsnummer oben links ist anklickbar und öffnet den Änderungsverlauf als kleines Fenster; die aktuelle Version ist aufgeklappt
- Die Programmversion wird jetzt aus dem obersten Eintrag der Datei `CHANGELOG.md` gelesen und nicht mehr aus der serverseitigen `config.php`; mit jeder Auslieferung genügt ein neuer Changelog-Eintrag

## 0.1.38

- Importassistent: neue Spalte „Bisher → neu“ je Zeile mit Gegenüberstellung der aktuellen Datenbankwerte und der importierten Werte; geänderte Felder sind hervorgehoben und aktualisieren sich live, wenn Zuordnung oder Werte in der Zeile bearbeitet werden
- Je Zeile steht jetzt die Aktion „Übernehmen“ oder „Nicht übernehmen“ zur Wahl; alle Felder bleiben vor dem Import bearbeitbar
- Sammelaktionen: alle übernehmen, alle abwählen, Zeilen ohne Änderung abwählen, Zeilen mit Hinweis abwählen; Zähler zeigt übernommene Zeilen und Zeilen mit Änderung
- Bei einer neuen Leistung kann „Als Standardleistung in den zentralen Katalog aufnehmen“ angehakt werden (TPL-/PL-Import); Klasse und Zuständigkeit stammen aus der Zeile, Klasse A ist wie im Katalog obligatorisch
- Neue Option „Datei ist maßgeblich“ für den TPL-/PL-Import: Leistungen, die am Rolloutobjekt eingetragen waren und in der Datei nicht mehr stehen, werden entfernt; fehlt danach jede Nutzung im Projekt, entfällt auch die Projektleistung samt Angaben der Leistungserbringer
- Obligatorische Leistungen (Klasse A, Basisklassen) und in der Datei genannte, aber abgewählte Zeilen bleiben erhalten; Verbunddienstleisterleistungen werden nur berücksichtigt, wenn die Datei diese Spalten enthält
- Entfernungen erscheinen vorab in der Änderungsvorschau als „Wird entfernt“; das Ergebnis nennt die Zahl der entfernten Zuordnungen

## 0.1.37

- Importassistent: Zeilen, deren Leistungswert ausdrücklich nicht übernommen wird, entfallen im Planungs- und Strukturimport vollständig; zuvor überschrieben sie Beginn, Ende und Notiz des Rolloutobjekts
- Zeitraum und Klasse in Leistungsauswahl-Zeilen der TPL-/PL-Datei werden nicht mehr an die Leistung weitergereicht
- Objektangaben (Name, Zeitraum, Klasse) in einer Leistungsauswahl-Zeile werden nicht mehr still verworfen, sondern rot als Hinweis angezeigt; eine Zeile ohne Leistung erscheint dann nicht ausgewählt
- Leistungen in der Zeile des Rolloutobjekts (Spalten Katalogleistung oder außerhalb des Katalogs) werden als Leistungszeile erkannt und müssen zugeordnet werden, statt zu entfallen
- Hinweise bei Freitext außerhalb des Katalogs, der in derselben Zeile wie eine Katalogleistung steht oder Kommas, Doppelpunkte beziehungsweise sehr viel Text enthält und deshalb wahrscheinlich ein Kommentar ist
- Sammelhinweis oberhalb der Zuordnungstabelle mit der Zahl der auffälligen Zeilen
- Der Planungsimport prüft jetzt auch ohne Leistungserbringer, ob eine Standardleistung zur bankfachlichen Klasse des Rolloutobjekts passt, und bricht andernfalls mit einer verständlichen Meldung ab

## 0.1.36

- neuer Schalter beim TPL-/PL-Export für Verbunddienstleisterklassen und zugehörige Leistungen
- mit aktiviertem Schalter werden die bisherigen Verbunddienstleister-Spalten einschließlich Dropdowns exportiert und wieder importiert
- ohne Schalter fehlen die beiden Spalten vollständig aus Arbeitsblatt, Dropdownlogik und TPL-Legende
- Rückimporte ohne Verbunddienstleister-Spalten lassen vorhandene Verbunddienstleisterklassen und -leistungen unverändert
- die Option gilt sowohl für den Einzelexport eines Projekts als auch für das ZIP aller Projektdateien

## 0.1.35

- feste Rolloutklasse 0 für FI und DSV: keine Verbundpartnerleistung erforderlich
- Klasse 0 ist an jedem Rolloutobjekt auswählbar und wird eindeutig von einer noch nicht festgelegten Klasse unterschieden
- Phase-2-Dateien für FI und DSV enthalten Klasse 0 in ihren Klassen-Dropdowns
- die abhängige Katalogleistungsauswahl bleibt bei Klasse 0 leer
- Rückimporte übernehmen Klasse 0, weisen aber Leistungen in derselben Klasse als widersprüchlich zurück
- bestehende Datenbanken erhalten die beiden festen Klassen beim ersten Start automatisch; das Schema bleibt Version 23
- Katalogpflegedateien bleiben frei von Klasse-0-Spalten, da Klasse 0 keine Leistungen enthalten kann

## 0.1.34

- kumulative Klassenauswahl jetzt auch direkt in der Anwendungsoberfläche
- Wahl von A markiert B, C und D automatisch; Wahl von 1 markiert 2 und 3 automatisch
- abgeleitete höhere Klassen werden grau dargestellt und können nicht einzeln abgewählt werden
- dieselbe Logik gilt für das frei konfigurierte DSV-Klassenschema und für zusätzliche Projektleistungen
- serverseitige Normalisierung speichert je Bereich nur die niedrigste Ausgangsklasse

## 0.1.33

- Katalogexporte für Regionalverbände, FI und DSV zeigen die kumulative Klassenvererbung vollständig als `Ja`
- eine Leistung ab A steht damit sichtbar auch in B, C und D auf `Ja`; ab 1 entsprechend auch in 2 und 3
- beim Rückimport wird die niedrigste markierte Ausgangsklasse kanonisch gespeichert und die höhere Gültigkeit daraus abgeleitet
- das Legendenblatt erläutert die kumulative Darstellung ausdrücklich

## 0.1.32

- verbindliche Änderungsvorschau vor jedem Struktur-, TPL-/PL-, FI-/DSV-, Regionalverbands- und Katalogimport
- vollständige Simulation des Imports ohne dauerhafte Datenbankänderung
- getrennte Anzeige neuer Datensätze, geänderter Felder, geleerter Werte und entfernter Datensätze oder Zuordnungen
- bisheriger und geplanter Wert werden je Feld unmittelbar gegenübergestellt
- tatsächliches Schreiben erfolgt erst über einen gesonderten roten Bestätigungsbutton
- Schutz vor veralteten Vorschauen: Wurde die Datenbank zwischen Vorschau und Bestätigung geändert, wird der Import abgebrochen
- Rückkehr zur bearbeitbaren Zuordnung beziehungsweise vollständiges Verwerfen bleiben vor der Bestätigung möglich

## 0.1.31

- Phase-2-Datei der FI ohne die Spalten `Katalogvorschlag (nur DSV)`, `Angeboten` und `Leistungserbringer`
- Phase-2-Datei des DSV ohne die Spalten `Angeboten` und `Leistungserbringer`; der DSV-Katalogvorschlag bleibt erhalten
- die Auswahl oder freie Eingabe einer FI-/DSV-Leistung gilt beim Rückimport automatisch als geplante Leistung
- drei getrennte Excel-Dateien zur Pflege der Standardkataloge für Regionalverbände, FI und DSV
- Katalogdateien mit einer Ja-/Nein-Spalte je Klasse sowie Feldern für Obligatorik, Terminrelevanz und Aktivstatus
- bearbeitete Katalogdateien können transaktional zurückimportiert werden; die Klassen anderer Katalogbereiche bleiben unverändert

## 0.1.30

- Rolloutklassen werden weiterhin ausschließlich an Rolloutobjekten gespeichert; FI- und DSV-Klassen sind jetzt fachlich und technisch getrennte Zuordnungen
- FI behält das kumulative Schema 1–3; der DSV erhält ein eigenes konfigurierbares Schema mit 1–3, A–D oder zwei bis acht frei benannten Klassen
- Phase-2-Dateien enthalten je Rolloutobjekt ein eigenes Klassen-Dropdown und davon abhängig ein kumulatives Leistungs-Dropdown ausschließlich für FI beziehungsweise DSV
- vorhandene Projekt-, Rolloutobjekt- und Leistungszuordnungen werden mit dem einmaligen Datenbankupdate auf die objektbezogene Struktur übernommen
- der DSV kann Leistungen außerhalb des Katalogs als neue zentrale Katalogleistung vorschlagen; Vorschläge werden im DSV-Register geprüft, angenommen oder abgelehnt
- angenommene Vorschläge behalten Projekt, Rolloutobjekt, Angebot, Termin und Bemerkung und erscheinen anschließend in den Gesamtansichten
- Legenden, Katalogregister, Matrix und Klassenkennzeichnungen unterscheiden Bankfachlich, FI und DSV eindeutig
- neues bereinigtes Datenbankschema 23; der normale Anwendungsstart enthält weiterhin keine historischen Migrationsroutinen

## 0.1.29

- Phase-2-Dateien für FI und DSV haben wieder ein deutlich sichtbares Dropdown für die Rolloutklasse Verbunddienstleister am Projekt
- die Katalogleistung bleibt als abhängiges Dropdown verfügbar: FI beziehungsweise DSV sehen nur ihre eigenen Katalogleistungen der gewählten Klasse und der darunterliegenden Klassen
- die auswählbare Rolloutklasse ist gelb als Eingabefeld markiert; die übrige stabile Phase-2-Logik aus 0.1.28 bleibt unverändert

## 0.1.28

- Hotfix: die Phase-2-Exportlogik ist wieder auf den stabilen Stand 0.1.25 zurückgesetzt
- die zusätzlichen Exportänderungen aus 0.1.26 und 0.1.27, die auf dem Server zum Fehler 500 führten, sind entfernt
- Datenbankschema und vorhandene Daten bleiben unverändert

## 0.1.27

- Phase-2-Dateien für FI und DSV enthalten wieder eine Dropdown-Auswahl für die Rolloutklasse Verbunddienstleister an jedem Rolloutobjekt
- die im Projekt angezeigte höchste Verbunddienstleisterklasse wird in Excel aus den Rolloutobjektklassen abgeleitet und aktualisiert die zulässige FI-/DSV-Leistungsauswahl
- die klassenabhängigen Katalogleistungs-Dropdowns für FI und DSV wurden korrigiert
- beim Rückimport einer Phase-2-Datei werden geänderte Verbunddienstleisterklassen der Rolloutobjekte übernommen

## 0.1.26

- Phase-2-Dateien für FI und DSV zeigen nun je Projekt alle zugehörigen Rolloutobjekte mit Beginn, Ende und individueller Rolloutklasse Verbunddienstleister
- die bankfachliche Rolloutklasse ist aus Phase 2 vollständig entfernt
- die Dropdown-Auswahl für FI-/DSV-Katalogleistungen richtet sich weiterhin nach der höchsten Verbunddienstleisterklasse des Projekts
- Rückimport unterscheidet objektbezogene Zeilen der Übersicht sicher von den projektweiten FI-/DSV-Auswahlzeilen
- additive Klassenlogik bestätigt und durchgängig beibehalten: A ⊂ B ⊂ C ⊂ D sowie 1 ⊂ 2 ⊂ 3

## 0.1.25

- Phase-2-Arbeitsdateien für FI und DSV als klassenabhängige Auswahlvorlagen neu aufgebaut
- je Projekt wird die höchste an seinen Rolloutobjekten gepflegte Rolloutklasse Verbunddienstleister angezeigt
- FI- beziehungsweise DSV-Katalogleistungen der Klassen 1–3 stehen kumulativ passend zu dieser Klasse als Dropdown bereit
- freie Spalte „Unterstützungsleistung außerhalb des Katalogs“ für weitere geplante Leistungen ergänzt
- Angebot, Termin oder Zeitraum und Bemerkung werden direkt in derselben Auswahlzeile gepflegt
- bestehende FI-/DSV-Planungen werden vorausgefüllt; der Rückimport ordnet Auswahl und Freitext weiterhin dem jeweiligen Leistungserbringer zu
- RV-Arbeitsdateien und Datenbankschema bleiben unverändert

## 0.1.24

- einheitliches Tabellenblatt „Legende und Anleitung“ in allen Arbeitsdateien der drei Phasen ergänzt
- Legende erklärt je Empfänger die konkrete Aufgabe sowie den vollständigen Ablauf von TPL/PL, FI/DSV und Regionalverbänden
- aktiver Standardkatalog übersichtlich in den Bereichen Regionalverbände, FI und DSV aufgeführt
- innerhalb jedes Bereichs nach Klasse und danach alphabetisch sortiert; mehrfach klassifizierte Leistungen erscheinen in jeder passenden Klasse
- Klassen A–D rot und Klassen 1–3 blau abgestuft hervorgehoben
- Katalogübersicht bewusst ohne Leistungsbeschreibungen gehalten; Begriffe und Klassenlogik stehen kompakt oberhalb der Übersicht
- TPL-, FI-, DSV- und alle RV-Einzeldownloads sowie ihre ZIP-Sammlungen enthalten die Legende
- Datenbankschema bleibt unverändert auf Stand 22; kein Datenbank-Patch erforderlich

## 0.1.23

- Phase 1 konsequent als Auswahl der vorgesehenen Leistungen durch TPL/PL gestaltet; keine Ja-/Nein-, Angebots- oder Bereitstellungsabfrage
- TPL-/PL-Datei in klare Rolloutobjektblöcke mit jeweils acht frei erweiterbaren Auswahlzeilen gegliedert
- Katalogleistungen Bankfachlich werden je Rolloutobjekt kumulativ passend zu A–D als Dropdown angeboten
- Katalogleistungen von FI/DSV werden je Rolloutobjekt kumulativ passend zu 1–3 als getrenntes Dropdown angeboten
- freie Spalte „Unterstützungsleistung außerhalb des Katalogs“ ergänzt
- Projektleitung, TPL Rollout und eine vermeintliche Projekt-Rolloutklasse aus der Phase-1-Datei entfernt
- zusätzliches Tabellenblatt „Legende“ mit Prozesshinweisen, kumulativer Klassenlogik und vollständigem Leistungskatalog ergänzt
- dieselbe Projektleistung kann bei mehreren Rolloutobjekten ausgewählt und beim Rückimport zu einer gemeinsamen Projektleistung mit mehreren Nutzungszuordnungen zusammengeführt werden
- FI-/DSV- und RV-Rückmeldefragen bleiben unverändert den Phasen 2 und 3 vorbehalten
- Datenbankschema bleibt unverändert auf Stand 22; kein Datenbank-Patch erforderlich

## 0.1.22

- Datenmodell vollständig auf Projektleistungen vereinheitlicht
- direkte Zuordnung und Rückmeldung von Leistungen an einzelnen Rolloutobjekten entfernt
- neue Zuordnungstabelle für die Nutzung einer Projektleistung je Rolloutobjekt ergänzt
- Rolloutobjekt-Nutzung in Projektmatrix und TPL-Rollout-Datei bearbeitbar
- TPL-Dateien enthalten Projekt, Rolloutobjekte und Projektleistungen als getrennte, jeweils einmalige Datensätze
- FI-, DSV- und RV-Dateien enthalten ausschließlich Projektleistungen
- alle Excel-Arbeitsblätter entsperrt; Eingabefelder und zusätzliche Zeilen sind frei bearbeitbar
- acht vorbereitete Ergänzungszeilen je Projekt; weitere Zeilen dürfen beliebig eingefügt oder kopiert werden
- neue Leistungen aus allen drei Phasen werden im Importassistenten einzeln zugeordnet, angelegt oder ausgelassen
- RV-Dateien zeigen FI-/DSV-Angaben passend zur jeweiligen Projektleistung zur Information
- Gesamt-Export um die Rolloutobjekt-Nutzung der Projektleistungen ergänzt
- konsolidiertes SQLite-Zielschema 22 ohne alte Ebenenfelder, `project_standard_services` und `support_matrix`
- einmaliges Datenbank-Patch für Schema 16 und 21 mit Sicherung, Zusammenführung alter Rückmeldungen und Integritätsprüfung
- objektbezogene Zusatzleistungen aus Schema 16 werden dem Projekt zugeordnet und behalten ihr bisheriges Rolloutobjekt als Nutzung
- normale Anwendung enthält weiterhin keine historischen Laufzeitmigrationen

## 0.1.21

- Datenbankschema auf einen konsolidierten Zielstand 21 angehoben
- historische Upgrade-Routinen aus dem normalen Anwendungsstart entfernt
- nicht mehr verwendete Spalte für individuell einem Rolloutobjekt zugeordnete Leistungen entfernt
- zusätzliche, nicht standardisierte Leistungen im Schema verbindlich auf Projektebene begrenzt
- aktuelle Suchindizes für Katalog, Klassen, Anbieterziele und Matrixzugriffe ergänzt
- redundante Zielauflösung im Importassistenten entfernt
- separates Kommandozeilen-Patchskript für bestehende Datenbanken aus Version 0.1.20 ergänzt
- Patch erstellt vor jeder Änderung eine SQLite-Sicherung und prüft Struktur, Fremdschlüssel sowie Integrität
- bei unerwarteter oder alter Datenbankstruktur erfolgt ein sicherer Abbruch statt einer automatischen Kettenmigration
- bestehende Projekte, Rolloutobjekte, Leistungen, Ansprechpartner und gültige Matrixeinträge bleiben erhalten

## 0.1.20

- höchste Rolloutklasse Bankfachlich und Verbunddienstleister wird aus allen Rolloutobjekten eines Projekts ermittelt
- Projektauswahl zeigt Leistungen nur bis zu den ermittelten Klassen und berücksichtigt die kumulative Logik
- Beispiel C/2: sichtbar sind die Klassen A, B, C sowie 1 und 2; D und 3 bleiben ausgeblendet
- sämtliche globalen Standardleistungen werden unabhängig von ihrer bisherigen Ebenenkennzeichnung in der Projektauswahl angeboten
- mehrfach klassifizierte Leistungen erscheinen in beiden passenden Bereichen
- doppelte Auswahlfelder derselben Leistung werden unmittelbar synchronisiert und nur einmal gespeichert
- serverseitige Prüfung verhindert die Auswahl einer für das Projekt nicht passenden Klasse
- klassenbezogene Standardleistungen für Rolloutobjekte werden erst nach ihrer Auswahl im Projekt aktiviert
- Anbieterdateien berücksichtigen auch bei projektweiten Leistungen die im Projekt vorhandenen Klassen
- bestehende Datenbank kann unverändert weiterverwendet werden

## 0.1.19

- Auswahl projektweiter Unterstützungsleistungen zuerst nach Bankfachlichem Rollout und Rollout Verbunddienstleister gegliedert
- innerhalb der beiden Rolloutbereiche nach Klasse A–D beziehungsweise 1–3 gruppiert
- Unterstützungsleistungen innerhalb jeder Klasse deutsch-alphabetisch sortiert
- mehrfach klassifizierte Leistungen erscheinen einmal im vorrangigen bankfachlichen Bereich; alle Klassenkennzeichen bleiben sichtbar
- bisherige Bezeichnung „technische Rolloutklasse“ durch „Rolloutklasse Verbunddienstleister“ ersetzt
- neue Excel- und CSV-Dateien verwenden „Rolloutklasse Bankfachlich“ und „Rolloutklasse Verbunddienstleister“ als Spaltenüberschriften
- Import bleibt mit bisherigen Dateien kompatibel und erkennt alte sowie neue Klassenüberschriften
- bestehende Datenbank kann unverändert weiterverwendet werden

## 0.1.18

- Auswahl der projektweiten Standardleistungen und Ansprechpartnerbereich nutzen responsiv die gesamte verfügbare Monitorbreite
- Leistungsauswahl passt sich mit ein bis vier Spalten an die Bildschirmbreite an
- fachliche Klassen A–D werden direkt an der Leistung in zunehmend kräftigen Rottönen gekennzeichnet
- FI-/DSV-Zuordnungen 1–3 werden direkt an der Leistung in zunehmend kräftigen Blautönen gekennzeichnet
- bei mehrfach klassifizierten Leistungen bleiben fachliche und FI-/DSV-Kennzeichen gleichzeitig sichtbar
- einheitliche Farblogik in Standardkatalog, Projektleistungsauswahl und Matrix sowie ergänzende Farblegende

## 0.1.17

- leere Matrixansicht aus Version 0.1.16 behoben
- Parameterübergabe der klassenbezogenen Standardleistungsabfrage korrigiert
- bestehende Datenbank kann unverändert weiterverwendet werden

## 0.1.16

- Unterstützungsleistungen in Auswahl- und Bearbeitungsansichten alphabetisch sortiert.
- Standardkatalog weiterhin nach Rolloutklassen gegliedert; Gesamtbild und Informationsansichten innerhalb der Klassen sortiert.
- Nicht standardisierte Leistungen können nur noch projektweit angelegt werden.
- Bestehende Einzelobjekt-Leistungen werden beim Update mit ihren Rückmeldungen in die jeweilige Projektleistung überführt.
- TPL- und Anbieterdateien erlauben keine zusätzlichen Leistungen mehr je Rolloutobjekt.

## 0.1.15

- Auswahl „obligatorisch“ für Standard- und Zusatzleistungen ergänzt
- obligatorische Leistungen werden bei allen jeweils zuständigen Leistungserbringern automatisch gesetzt
- Angebots- und Bereitstellungsabfrage entfällt bei obligatorischen Leistungen
- Auswahl ergänzt, ob Termin oder Zeitraum für eine Leistung relevant ist
- nicht relevante Terminfelder werden in Matrix und Import ignoriert sowie in Excel geschützt dargestellt
- Bemerkungsfelder bleiben unabhängig von der Terminrelevanz verfügbar
- bestehende Leistungen werden beim einmaligen Datenbank-Upgrade mit relevantem Terminfeld übernommen

## 0.1.14

- fehlerhafte Verklebung von Klassenkennzeichen und Gruppenüberschrift im Standardkatalog behoben
- Gruppenüberschriften für Regionalverbände als „Klasse A–D“ und für FI/DSV als „Zuordnung 1–3“ eindeutig bezeichnet
- Trefferanzahl je Gruppe mit „Leistung“ beziehungsweise „Leistungen“ ausgeschrieben
- Versionsparameter für das Anwendungsstylesheet ergänzt, damit Browser die korrigierte Darstellung sofort laden

## 0.1.13

- Anbieterzuordnung einer Unterstützungsleistung von der Klassenzuordnung getrennt
- Standardleistungen separat für Regionalverbände, FI und DSV auswählbar; Mehrfachzuordnungen bleiben möglich
- Klassen 1–3 ausdrücklich als Zuordnung zur technischen Rolloutklasse und nicht als Art der Leistung bezeichnet
- Termin-/Zeitraumfeld für jede projektweite und rolloutobjektbezogene Rückmeldung ergänzt
- dreiphasiger Excel-Ablauf: einzelne TPL-Projektdateien, FI-/DSV-Planung, anschließend zwölf RV-Abfragen
- FI und DSV erfassen geplante Unterstützungsleistungen getrennt mit Termin/Zeitraum und Bemerkung
- RV-Arbeitsdateien zeigen die importierten FI-/DSV-Angaben grau und geschützt nur zur Information
- Informationsspalten der RV-Dateien werden beim Rückimport bewusst ignoriert
- bestehende Datenbanken werden einmalig um Anbieterzuordnungen und Termin-/Zeitraumfelder erweitert

## 0.1.12

- Standardkatalog in drei Register für Regionalverbände, FI und DSV gegliedert
- fachliche Leistungen A–D erscheinen im Register der Regionalverbände
- technische Leistungen 1–3 erscheinen getrennt in den Registern FI und DSV
- Leistungen mit fachlicher und technischer Klassifizierung bleiben ein Katalogeintrag und erscheinen in allen passenden Registern
- Leistungen innerhalb jedes Registers nach ihrer niedrigsten zugeordneten Klasse gruppiert
- Suche sowie Filter nach Ebene und Aktivstatus ergänzt
- Anlage- und Bearbeitungsformular einklappbar gestaltet, damit der Katalog die volle Seitenbreite nutzen kann
- Trefferzahlen je Register und Klassengruppe ergänzt
- Zuständigkeit auch in Matrix, Pflichtlogik, Import und Anbieterdateien getrennt: A–D nur Regionalverbände, 1–3 nur FI und DSV
- automatische Basisklasse A nur für Regionalverbände und Basisklasse 1 nur für FI und DSV gesetzt
- nicht zuständige Zellen in der Matrix eindeutig als nicht anwendbar dargestellt

## 0.1.11

- Bereitstellungsart für fachliche Unterstützungsleistungen der Klassen B, C und D ergänzt
- Regionalverbände wählen zwischen eigener Bereitstellung, anderem Regionalverband, externem Dienstleister und keiner Bereitstellung
- bei anderem Regionalverband steht eine Auswahlliste der übrigen Regionalverbände zur Verfügung
- Import prüft, dass ein angegebener anderer Regionalverband aktiv und nicht mit dem rückmeldenden Verband identisch ist
- bei externer Bereitstellung wird der konkrete Dienstleister erfasst
- Matrixzellen werden nach Bereitstellungsart farblich gekennzeichnet; Detailfelder erscheinen nur bei Bedarf
- Leistungsklasse wird unmittelbar an der Unterstützungsleistung angezeigt
- RV-Arbeitsdateien um Bereitstellungsart und konkreten Partner ergänzt und übersichtlich formatiert
- Importassistent sowie CSV-/XLSX-Gesamtexport um die neuen Angaben erweitert
- bestehende RV-Haken werden beim einmaligen Datenbank-Upgrade als eigene Bereitstellung übernommen

## 0.1.10

- TPL-Rollout-Arbeitsdatei auf genau eine Projektzeile und eine Zeile je Rolloutobjekt konsolidiert
- mehrfache Stammdaten- und pauschale Leerzeilen aus der TPL-Datei entfernt
- vorgesehene Leistungen als übersichtliche Liste innerhalb der jeweiligen Projekt- oder Rolloutobjektzeile zusammengefasst
- separate Eingabespalte für weitere Unterstützungsleistungen ergänzt; mehrere Einträge werden zeilenweise erfasst
- Rückimport zerlegt die konsolidierten Leistungsfelder automatisch wieder in einzelne prüfbare Datensätze
- Excel-Datei mit breiten Spalten, Zeilenumbruch, fixierter Kopfzeile, Filter, Klassen-Auswahllisten und farblich hervorgehobenen Eingabefeldern gestaltet

## 0.1.9

- zwei dauerhaft sichtbare Schnellaktionen in der oberen Navigation ergänzt
- Projekt kann in einem kompakten Dialog einschließlich Projektleitung, TPL Rollout und Beschreibung angelegt werden
- Rolloutobjekt kann in einem Dialog mit Projektzuordnung, Zeitraum, Klassen und Anmerkungen angelegt werden
- Projektauswahl im Rolloutobjekt-Dialog wird alphabetisch aus den vorhandenen Projekten befüllt
- bei noch fehlenden Projekten erklärt der Dialog den erforderlichen ersten Schritt und verhindert das Speichern
- Projektexistenz wird beim Speichern eines Rolloutobjekts zusätzlich serverseitig geprüft
- Schnellaktionsfarben an das Sparkassen-Farbschema angepasst

## 0.1.8

- TPL-Rollout-Planungsdatei als Excel-Export ergänzt
- Planungsdatei enthält vorhandene Projekte und Rolloutobjekte mit getrennten Stammdaten- und Leistungszeilen
- TPL-Rückimport aktualisiert Zeiträume, Rolloutklassen, TPL Rollout und vorgesehene Leistungen, ohne neue Projekte oder Rolloutobjekte anzulegen
- anbieterspezifische Excel-Arbeitsdateien für FI, DSV und alle zwölf Regionalverbände ergänzt
- alle 14 Arbeitsdateien können gemeinsam als ZIP oder einzeln heruntergeladen werden
- Dateien enthalten vorhandene Leistungen, Ansprechpartner sowie freie Zeilen für ergänzende nicht standardisierte Leistungen
- FI und DSV erhalten mehr freie Ergänzungszeilen als die Regionalverbände
- Rückmeldungsimport auf FI und DSV erweitert
- Dateiname und Leistungserbringerspalte werden zur automatischen Zuordnung genutzt
- Rückimport verändert nur Matrixeinträge und Projektkontakte des jeweils zugeordneten Leistungserbringers
- XLSX- und ZIP-Erzeugung benötigt weiterhin keine PHP-Erweiterung `ZipArchive`

## 0.1.7

- Mehrdatei-Upload für CSV- und XLSX-Rückmeldungen ergänzt
- jede Rückmeldedatei wird vor der Übernahme genau einem Regionalverband zugeordnet
- vorhandene Projekte und Rolloutobjekte werden eindeutig gematcht oder in der Vorschau manuell ausgewählt
- Rückmeldemodus verhindert das unbeabsichtigte Anlegen doppelter Projekte und Rolloutobjekte
- Import aktualisiert ausschließlich die Matrixzellen und Projektkontakte des zur Datei gewählten Regionalverbands
- bereits importierte Rückmeldungen anderer Regionalverbände bleiben beim Zusammenführen unverändert
- bisheriger Strukturimport zum Anlegen neuer Projekte und Rolloutobjekte bleibt als eigener Modus erhalten
- vollständiger Import wird bei Fehlern weiterhin transaktional zurückgerollt

## 0.1.6

- zweistufigen Importassistenten für CSV- und XLSX-Dateien ergänzt
- Datenbank wird erst nach ausdrücklicher Bestätigung der Vorschau verändert
- alle erkannten Importwerte können vor der Übernahme bearbeitet werden
- einzelne Importzeilen können abgewählt werden
- eindeutige Leistungsnamen werden automatisch zugeordnet
- unklare oder fehlende Treffer können manuell aus Standardleistungen und bereits vorhandenen nicht standardisierten Leistungen gewählt werden
- gefundene Leistungen können direkt als neue nicht standardisierte Projekt- oder Rolloutobjektleistung angelegt werden
- Leistungserbringer können vor dem Import ebenfalls manuell zugeordnet werden
- serverseitige Prüfung verhindert unpassende Leistungszuordnungen zwischen Projekt- und Rolloutobjektebene

## 0.1.5

- Standardkatalog vollständig von einzelnen Projekten entkoppelt
- projektweite Standardleistungen sind zentral für alle Projekte verfügbar
- Auswahl der benötigten projektweiten Standardleistungen je Projekt in der Projektmatrix ergänzt
- projektbezogene Katalogoption für Standardleistungen entfernt
- automatische A-/1-Logik greift bei projektweiten Standards erst nach deren Auswahl im Projekt
- CSV-/XLSX-Export und -Import sichern auch ausgewählte Standards ohne Matrixhaken

## 0.1.4

- Auslieferung auf eine vollständig leere, beim ersten Aufruf neu erzeugte SQLite-Datenbank umgestellt
- historisch gewachsene Migrationsroutinen vollständig entfernt
- Schemaaufbau und Stammdatenanlage laufen nur noch einmal bei einer neuen Datenbank
- veraltete Ansprechpartnerfelder aus den Leistungsmatrizen entfernt
- benötigte Datenbankindizes direkt in das saubere Zielschema aufgenommen
- einzige Passwortanforderung: mindestens 9 beliebige Zeichen

## 0.1.3

- Ansprechpartner je Projekt auf FI und DSV erweitert
- zentrale Ansprechpartnerliste zeigt FI, DSV und alle Regionalverbände in Matrixreihenfolge
- Migration, CSV-Import und CSV-/XLSX-Export übernehmen nun Kontakte aller Leistungserbringer

## 0.1.2

- Ansprechpartner und Telefonnummer auf eine Zuordnung je Projekt und Regionalverband vereinfacht
- zentrale Ansprechpartnerliste oberhalb der Projektmatrix ergänzt
- Ansprechpartnerfelder aus den einzelnen Leistungszellen entfernt
- leistungsbezogene Anmerkungen bleiben je Matrixzelle erhalten
- vorhandene Kontaktdaten werden bei der Migration projektbezogen zusammengeführt
- CSV-/XLSX-Import und -Export auf projektbezogene Regionalverbandskontakte angepasst

## 0.1.1

- Matrix auf Projektebene zusammengeführt
- projektweite Unterstützungsleistungen als eigene Ebene ergänzt
- alle Rolloutobjekte eines Projekts werden mit ihren Leistungen untereinander angezeigt
- Leistungen können global, einem Projekt, klassenbezogen oder einem konkreten Rolloutobjekt zugeordnet werden
- horizontaler Bildlauf erfolgt über die gesamte Seite statt innerhalb des Matrixcontainers
- CSV-/XLSX-Import und -Export um die Spalte `Ebene` und projektweite Matrixeinträge erweitert
- deutsches Datumsformat `TT.MM.JJJJ` für Anzeige, Eingabe, CSV- und XLSX-Export vereinheitlicht
- fachliche Rolloutklassen kumulativ aufgebaut: B enthält A+B, C enthält A+B+C und D enthält A+B+C+D
- Klasse-A-Leistungen für alle zutreffenden Rolloutobjekte und sämtliche aktiven Leistungserbringer automatisch aktiviert und gesperrt
- technische Klassen ebenfalls kumulativ aufgebaut: Klasse 2 enthält 1+2 und Klasse 3 enthält 1+2+3
- technische Klasse-1-Leistungen wie fachliche Klasse-A-Leistungen automatisch aktiviert und gesperrt
- Finanz Informatik und Deutscher Sparkassenverlag in der Anwendung auf `FI` und `DSV` verkürzt
- Standardkatalog auf zentral gepflegte Standardleistungen begrenzt
- zusätzliche Leistungen direkt am Projekt oder einem konkreten Rolloutobjekt anlegbar und bearbeitbar
- Kennzeichnung „vom Projektteam für alle vorgegeben“ mit automatisch gesperrten Haken ergänzt
- einzelne Regionalverbände, FI oder DSV können nicht verbindliche Zusatzleistungen unabhängig voneinander anbieten
- automatische Kennzeichnung auch für projektweite Leistungen ergänzt

## 0.1.0

- erster Prototyp mit Login, Projekten, Rolloutobjekten und Unterstützungsmatrix
- fachliche Rolloutklassen A–D und technische Rolloutklassen 1–3
- CSV-/XLSX-Import und -Export
- Unterscheidung zwischen vorgegebenen Standardleistungen und zusätzlichen, nicht standardisierten Leistungen
- zusätzliche Leistungen können frei einer oder mehreren Rolloutklassen zugeordnet werden
- Auslieferung als reine PHP-Webanwendung für einen vorhandenen Caddy-/PHP-FPM-Webserver
- XLSX-Export und -Import funktionieren ohne die optionale PHP-Erweiterung ZipArchive
- Exportfehler werden sichtbar in der Anwendung angezeigt
- Korrektur: Ansprechpartner und Telefonnummer werden je Rolloutobjekt, Unterstützungsleistung und Leistungserbringer gepflegt, nicht pauschal je Regionalverband
- automatische, nicht abwählbare Leistungserbringung der Klasse-A-Standardleistungen durch Regionalverbände
- Ansprechpartner und automatische Klasse-A-Kennzeichnung im CSV-/XLSX-Export
- FI und DSV stehen in Matrix, Verwaltung und Exporten vor den Regionalverbänden
- neutrale Regionalverbandseinträge durch die 12 offiziellen Abkürzungen in alphabetischer Reihenfolge ersetzt
