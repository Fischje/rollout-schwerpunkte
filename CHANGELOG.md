# Änderungsverlauf

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
