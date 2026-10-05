<?php
declare(strict_types=1);

/** Minimaler XLSX-Leser/-Schreiber ohne Abhängigkeit von PHP-ZipArchive. */
final class Xlsx
{
    public static function read(string $path): array
    {
        return self::readDetailed($path)['rows'];
    }

    /**
     * Liest das erste Tabellenblatt und erkennt zusätzlich durchgestrichene Zellen.
     * @return array{rows: array<int, array<int, string>>, strike: array<int, array<int, bool>>}
     */
    public static function readDetailed(string $path): array
    {
        $entries = self::readZip($path);
        $shared = [];
        $sharedStrike = [];
        if (isset($entries['xl/sharedStrings.xml'])) {
            $doc = simplexml_load_string($entries['xl/sharedStrings.xml']);
            if ($doc === false) throw new RuntimeException('Die Excel-Zeichenkette ist beschädigt.');
            foreach ($doc->si as $si) {
                $text = '';
                $struck = false;
                if (isset($si->t)) $text = (string)$si->t;
                else {
                    $runs = 0;
                    $struckRuns = 0;
                    foreach ($si->r as $run) {
                        $text .= (string)$run->t;
                        if (trim((string)$run->t) === '') continue;
                        $runs++;
                        if (isset($run->rPr->strike) && self::flagOn($run->rPr->strike)) $struckRuns++;
                    }
                    $struck = $runs > 0 && $runs === $struckRuns;
                }
                $shared[] = $text;
                $sharedStrike[] = $struck;
            }
        }
        $styleStrike = self::strikeStyles($entries['xl/styles.xml'] ?? null);
        $sheet = $entries['xl/worksheets/sheet1.xml'] ?? null;
        if ($sheet === null) throw new RuntimeException('Das erste Tabellenblatt fehlt.');
        $doc = simplexml_load_string($sheet);
        if ($doc === false) throw new RuntimeException('Das erste Tabellenblatt ist beschädigt.');
        $rows = [];
        $strike = [];
        foreach ($doc->sheetData->row as $row) {
            $values = [];
            $struckCells = [];
            foreach ($row->c as $cell) {
                $ref = (string)$cell['r'];
                preg_match('/^[A-Z]+/', $ref, $match);
                $index = self::columnIndex($match[0] ?? 'A');
                $type = (string)$cell['t'];
                $value = $type === 'inlineStr' ? (string)$cell->is->t : (string)$cell->v;
                $struck = !empty($styleStrike[(int)$cell['s']]);
                if ($type === 's') { $sharedIndex = (int)$value; $value = $shared[$sharedIndex] ?? ''; $struck = $struck || !empty($sharedStrike[$sharedIndex]); }
                if ($type === 'b') $value = $value === '1' ? 'Ja' : 'Nein';
                $values[$index] = $value;
                if ($struck && trim($value) !== '') $struckCells[$index] = true;
            }
            if ($values) {
                $max = max(array_keys($values));
                $rows[] = array_map(fn(int $i): string => (string)($values[$i] ?? ''), range(0, $max));
                $strike[count($rows) - 1] = $struckCells;
            }
        }
        return ['rows' => $rows, 'strike' => $strike];
    }

    private static function flagOn(\SimpleXMLElement $node): bool
    {
        $value = isset($node['val']) ? strtolower((string)$node['val']) : '1';
        return !in_array($value, ['0', 'false'], true);
    }

    /** Liefert je Zellformat-Index (cellXfs), ob dessen Schrift durchgestrichen ist. */
    private static function strikeStyles(?string $xml): array
    {
        if ($xml === null) return [];
        $doc = simplexml_load_string($xml);
        if ($doc === false) return [];
        $fonts = [];
        if (isset($doc->fonts->font)) foreach ($doc->fonts->font as $font) $fonts[] = isset($font->strike) && self::flagOn($font->strike);
        $styles = [];
        if (isset($doc->cellXfs->xf)) foreach ($doc->cellXfs->xf as $index => $xf) $styles[] = !empty($fonts[(int)$xf['fontId']]);
        return $styles;
    }

    public static function binary(array $rows, string $sheetName = 'Rolloutobjekte', array $options = []): string
    {
        $layout = (string)($options['layout'] ?? '');
        $tplLayout = $layout === 'tpl-planning';
        $providerLayout = $layout === 'provider-feedback';
        $styledLayout = $tplLayout || $providerLayout;
        $xmlRows = '';
        foreach ($rows as $rowNumber => $row) {
            $cells = '';
            foreach (array_values($row) as $columnNumber => $value) {
                $ref = self::columnName($columnNumber) . ($rowNumber + 1);
                $safe = htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $style = '';
                if ($styledLayout) {
                    $rowType = mb_strtolower(trim((string)($row[1] ?? '')));
                    $isProjectRow = $rowNumber > 0 && $rowType === 'projekt';
                    if ($tplLayout) {
                        $editableColumns = match ($rowType) {
                            'projekt' => [7,8,13],
                            'rolloutobjekt' => [2,3,4,5,6,13],
                            'projektleistung', 'neue projektleistung' => [9,10,11,12,13],
                            default => [],
                        };
                    } else {
                        $editableColumns = $isProjectRow ? [14,15] : [16,18,19,20,21];
                        if ($rowType === 'neue projektleistung') $editableColumns = array_values(array_unique(array_merge($editableColumns,[9,10,11])));
                        if (mb_strtolower(trim((string)($row[17] ?? ''))) === 'ja') $editableColumns = array_values(array_diff($editableColumns,[16,18,19]));
                        if ($columnNumber === 20 && mb_strtolower(trim((string)$value)) === 'nicht relevant') $editableColumns = array_values(array_diff($editableColumns,[20]));
                    }
                    $informationOnly = $providerLayout && mb_strtolower(trim((string)($row[24] ?? ''))) === 'ja';
                    $styleId = $rowNumber === 0 ? 1 : ($informationOnly || ($providerLayout && $columnNumber >= 22) ? 6 : (in_array($columnNumber, $editableColumns, true) ? ($isProjectRow ? 5 : 4) : ($isProjectRow ? 3 : 2)));
                    $style = ' s="' . $styleId . '"';
                }
                $cells .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . $safe . '</t></is></c>';
            }
            $rowAttributes = '';
            if ($styledLayout) {
                $lineCount = 1;
                foreach ($row as $value) $lineCount = max($lineCount, substr_count((string)$value, "\n") + 1);
                $height = $rowNumber === 0 ? 42 : min(105, max(30, 10 + $lineCount * 15));
                $rowAttributes = ' ht="' . $height . '" customHeight="1"';
            }
            $xmlRows .= '<row r="' . ($rowNumber + 1) . '"' . $rowAttributes . '>' . $cells . '</row>';
        }
        $sheetName = mb_substr(preg_replace('~[\\\\/?*\[\]:]~u', ' ', $sheetName) ?: 'Rolloutobjekte', 0, 31);
        $lastRow = max(1, count($rows));
        $lastColumn = self::columnName(max(0, count($rows[0] ?? []) - 1));
        $sheetBeforeData = '';
        $sheetAfterData = '';
        $styleContentType = '';
        $styleRelationship = '';
        $styles = null;
        if ($styledLayout) {
            $widths = $tplLayout ? [32,22,38,14,14,24,30,22,22,38,25,30,45,36] : [30,22,34,14,14,24,30,20,20,36,25,30,40,18,22,18,14,16,25,34,20,34];
            if ($providerLayout && ($options['provider_type'] ?? '') === 'RV') $widths = array_merge($widths, [40,40,4,46,26]);
            $columns = '';
            foreach ($widths as $index => $width) {
                $hidden = '';
                if ($providerLayout && $index === 13) $hidden = ' hidden="1"';
                if ($providerLayout && ($options['provider_type'] ?? '') === 'RV' && $index === 16) $hidden = ' hidden="1"';
                if ($providerLayout && ($options['provider_type'] ?? '') === 'RV' && $index === 24) $hidden = ' hidden="1"';
                if ($providerLayout && ($options['provider_type'] ?? '') !== 'RV' && in_array($index, [18,19], true)) $hidden = ' hidden="1"';
                $columns .= '<col min="' . ($index + 1) . '" max="' . ($index + 1) . '" width="' . $width . '" customWidth="1"' . $hidden . '/>';
            }
            $split = $tplLayout ? 2 : 3;
            $topLeft = $tplLayout ? 'C2' : 'D2';
            $sheetBeforeData = '<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="' . $split . '" ySplit="1" topLeftCell="' . $topLeft . '" activePane="bottomRight" state="frozen"/><selection pane="bottomRight" activeCell="' . $topLeft . '" sqref="' . $topLeft . '"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>' . $columns . '</cols>';
            if ($tplLayout) {
                $validations = '<dataValidations count="4"><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Klasse" error="Bitte A, B, C oder D auswählen." sqref="F2:F' . $lastRow . '"><formula1>&quot;A,B,C,D&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Klasse" error="Bitte eine FI-Klasse aus der Liste auswählen." sqref="G2:G' . $lastRow . '"><formula1>&quot;' . implode(',', fi_class_codes()) . '&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Leistungsklasse" error="Bitte A, B, C oder D auswählen." sqref="K2:K' . $lastRow . '"><formula1>&quot;A,B,C,D&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Leistungsklasse" error="Bitte eine FI-Klasse aus der Liste auswählen." sqref="L2:L' . $lastRow . '"><formula1>&quot;' . implode(',', fi_class_codes()) . '&quot;</formula1></dataValidation></dataValidations>';
                $conditional = '';
            } elseif (($options['provider_type'] ?? '') === 'RV') {
                $validations = '<dataValidations count="3"><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Leistungsklasse" error="Bitte A, B, C oder D auswählen." sqref="K2:K' . $lastRow . '"><formula1>&quot;A,B,C,D&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Leistungsklasse" error="Bitte eine FI-Klasse aus der Liste auswählen." sqref="L2:L' . $lastRow . '"><formula1>&quot;' . implode(',', fi_class_codes()) . '&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Bereitstellungsart" error="Bitte einen Eintrag aus der Liste auswählen." sqref="S2:S' . $lastRow . '"><formula1>&quot;Eigene Bereitstellung,Anderer Regionalverband,Externer Dienstleister,Keine Bereitstellung&quot;</formula1></dataValidation></dataValidations>';
                $conditional = '<conditionalFormatting sqref="S2:T' . $lastRow . '"><cfRule type="expression" dxfId="0" priority="1"><formula>$S2=&quot;Eigene Bereitstellung&quot;</formula></cfRule><cfRule type="expression" dxfId="1" priority="2"><formula>$S2=&quot;Anderer Regionalverband&quot;</formula></cfRule><cfRule type="expression" dxfId="2" priority="3"><formula>$S2=&quot;Externer Dienstleister&quot;</formula></cfRule><cfRule type="expression" dxfId="3" priority="4"><formula>$S2=&quot;Keine Bereitstellung&quot;</formula></cfRule></conditionalFormatting>';
            } else {
                $validations = '<dataValidations count="3"><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Leistungsklasse" error="Bitte A, B, C oder D auswählen." sqref="K2:K' . $lastRow . '"><formula1>&quot;A,B,C,D&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Leistungsklasse" error="Bitte eine FI-Klasse aus der Liste auswählen." sqref="L2:L' . $lastRow . '"><formula1>&quot;' . implode(',', fi_class_codes()) . '&quot;</formula1></dataValidation><dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Angabe" error="Bitte Ja oder Nein auswählen." sqref="Q2:Q' . $lastRow . '"><formula1>&quot;Ja,Nein&quot;</formula1></dataValidation></dataValidations>';
                $conditional = '';
            }
            $sheetAfterData = '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>' . $conditional . $validations . '<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
            $styleContentType = '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
            $styleRelationship = '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
            $styles = self::planningStyles();
        }
        $entries = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' . $styleContentType . '</Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . htmlspecialchars($sheetName, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' . $styleRelationship . '</Relationships>',
            'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:' . $lastColumn . $lastRow . '"/>' . $sheetBeforeData . '<sheetData>' . $xmlRows . '</sheetData>' . $sheetAfterData . '</worksheet>',
        ];
        if ($styles !== null) $entries['xl/styles.xml'] = $styles;
        if (isset($options['legend']) && is_array($options['legend']) && $styledLayout) self::appendLegendSheet($entries, $options['legend']);
        return self::buildZip($entries);
    }

    /**
     * Erstellt die TPL-/PL-Planungsdatei mit abhängigen Leistungsauswahllisten,
     * sichtbarer Legende und einem ausgeblendeten Listenblatt.
     */
    public static function tplPlanningBinary(array $workbook): string
    {
        $rows = (array)($workbook['rows'] ?? []);
        $legend = (array)($workbook['legend'] ?? []);
        $lists = (array)($workbook['lists'] ?? []);
        $validationRows = (array)($workbook['validation_rows'] ?? []);
        if (!$rows) throw new RuntimeException('Die TPL-Planungsdatei enthält keine Daten.');

        $headers=array_values((array)$rows[0]);
        $find=static function(string $header)use($headers):?int{$index=array_search($header,$headers,true);return $index===false?null:(int)$index;};
        $functionalClassColumn=$find('Rolloutklasse Bankfachlich');
        $providerClassColumn=$find('Rolloutklasse Verbunddienstleister');
        $functionalCatalogColumn=$find('Katalogleistung Bankfachlich');
        $providerCatalogColumn=$find('Katalogleistung Verbunddienstleister');
        $outsideColumn=$find('Unterstützungsleistung außerhalb des Katalogs');
        $noteColumn=$find('Anmerkung zum Rolloutobjekt');
        $deliveryColumn=$find('Erbringung');
        if($functionalClassColumn===null||$functionalCatalogColumn===null||$outsideColumn===null||$noteColumn===null)throw new RuntimeException('Die TPL-Planungsdatei hat eine ungültige Spaltenstruktur.');
        if(($providerClassColumn===null)!==($providerCatalogColumn===null))throw new RuntimeException('Die Verbunddienstleister-Spalten der TPL-Planungsdatei sind unvollständig.');

        $mainRows = '';$objectRows=[];$validations=[];
        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;$rowType = mb_strtolower(trim((string)($row[1] ?? '')));$cells='';
            foreach (array_values($row) as $columnIndex => $value) {
                if ($rowIndex === 0) $styleId=1;
                elseif ($rowType === 'rolloutobjekt') {$editable=array_filter([2,3,4,$functionalClassColumn,$providerClassColumn,$noteColumn],static fn($item):bool=>$item!==null);$selectionColumns=array_filter([$functionalCatalogColumn,$providerCatalogColumn,$outsideColumn,$deliveryColumn],static fn($item):bool=>$item!==null);$styleId=in_array($columnIndex,$editable,true)?5:(in_array($columnIndex,$selectionColumns,true)?6:3);}
                else {$editable=array_filter([$functionalCatalogColumn,$providerCatalogColumn,$outsideColumn,$deliveryColumn],static fn($item):bool=>$item!==null);$styleId=in_array($columnIndex,$editable,true)?4:6;}
                $cells .= self::inlineCell($columnIndex,$excelRow,(string)$value,$styleId);
            }
            $outline=$rowType==='leistungsauswahl'?' outlineLevel="1"':'';
            $height=$rowIndex===0?48:($rowType==='rolloutobjekt'?36:30);
            $mainRows.='<row r="'.$excelRow.'" ht="'.$height.'" customHeight="1"'.$outline.'>'.$cells.'</row>';
            if($rowType==='rolloutobjekt')$objectRows[]=$excelRow;
        }
        $functionalClassLetter=self::columnName($functionalClassColumn);$functionalCatalogLetter=self::columnName($functionalCatalogColumn);
        if($objectRows){$objectRanges=[];foreach($objectRows as $row)$objectRanges[]=$functionalClassLetter.$row;$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Klasse" error="Bitte A, B, C oder D auswählen." sqref="'.implode(' ',$objectRanges).'"><formula1>&quot;A,B,C,D&quot;</formula1></dataValidation>';if($providerClassColumn!==null){$providerClassLetter=self::columnName($providerClassColumn);$objectRanges=[];foreach($objectRows as $row)$objectRanges[]=$providerClassLetter.$row;$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Klasse" error="Bitte eine FI-Klasse aus der Liste auswählen." sqref="'.implode(' ',$objectRanges).'"><formula1>&quot;' . implode(',', array_merge(['0'], fi_class_codes())) . '&quot;</formula1></dataValidation>';}}
        if($deliveryColumn!==null&&$validationRows){$deliveryLetter=self::columnName($deliveryColumn);$deliveryRanges=[];foreach(array_keys($validationRows) as $row)$deliveryRanges[]=$deliveryLetter.(int)$row;$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Erbringung" error="Bitte regional oder zentral auswählen. Leer = Vorgabe aus dem Katalog." sqref="'.implode(' ',$deliveryRanges).'"><formula1>&quot;regional,zentral&quot;</formula1></dataValidation>';}
        foreach($validationRows as $row=>$rule){$master=(int)($rule['master_row']??0);if($master<2)continue;$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Nicht passende Leistung" error="Bitte eine zur Rolloutklasse passende Katalogleistung auswählen." sqref="'.$functionalCatalogLetter.(int)$row.'"><formula1>INDIRECT(&quot;RV_&quot;&amp;IF($'.$functionalClassLetter.'$'.$master.'=&quot;&quot;,&quot;EMPTY&quot;,$'.$functionalClassLetter.'$'.$master.'))</formula1></dataValidation>';if($providerClassColumn!==null&&$providerCatalogColumn!==null){$providerClassLetter=self::columnName($providerClassColumn);$providerCatalogLetter=self::columnName($providerCatalogColumn);$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Nicht passende Leistung" error="Bitte eine zur Rolloutklasse passende Katalogleistung auswählen." sqref="'.$providerCatalogLetter.(int)$row.'"><formula1>INDIRECT(&quot;VD_&quot;&amp;IF($'.$providerClassLetter.'$'.$master.'=&quot;&quot;,&quot;EMPTY&quot;,$'.$providerClassLetter.'$'.$master.'))</formula1></dataValidation>';}}
        $widthByHeader=['Projekt'=>30,'Datensatz'=>21,'Rolloutobjekt'=>36,'Rolloutbeginn'=>14,'Rolloutende'=>14,'Rolloutklasse Bankfachlich'=>24,'Rolloutklasse Verbunddienstleister'=>30,'Katalogleistung Bankfachlich'=>42,'Katalogleistung Verbunddienstleister'=>44,'Unterstützungsleistung außerhalb des Katalogs'=>44,'Erbringung'=>16,'Anmerkung zum Rolloutobjekt'=>36];$mainColumns='';foreach($headers as $index=>$header){$width=$widthByHeader[(string)$header]??24;$mainColumns.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.$width.'" customWidth="1"/>';}
        $mainLast=max(1,count($rows));$validationXml=$validations?'<dataValidations count="'.count($validations).'">'.implode('',$validations).'</dataValidations>':'';
        $lastColumn=self::columnName(max(0,count($headers)-1));$mainSheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$lastColumn.$mainLast.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="2" ySplit="1" topLeftCell="C2" activePane="bottomRight" state="frozen"/><selection pane="bottomRight" activeCell="C2" sqref="C2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>'.$mainColumns.'</cols><sheetData>'.$mainRows.'</sheetData>'.$validationXml.'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';

        $legendSheet=self::legendSheetXml($legend);

        $listNames=array_keys($lists);$listRows='';$maxListRows=1;foreach($lists as $values)$maxListRows=max($maxListRows,count((array)$values)+1);for($row=1;$row<=$maxListRows;$row++){$cells='';foreach($listNames as $column=>$name){$value=$row===1?$name:(string)(((array)$lists[$name])[$row-2]??'');$cells.=self::inlineCell($column,$row,$value,$row===1?1:2);}$listRows.='<row r="'.$row.'">'.$cells.'</row>';}$listLastColumn=self::columnName(max(0,count($listNames)-1));$listSheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$listLastColumn.$maxListRows.'"/><sheetData>'.$listRows.'</sheetData></worksheet>';
        $defined=[];foreach($listNames as $column=>$name){$count=max(1,count((array)$lists[$name]));$letter=self::columnName($column);$defined[]='<definedName name="'.htmlspecialchars($name,ENT_XML1|ENT_QUOTES,'UTF-8').'">Listen!$'.$letter.'$2:$'.$letter.'$'.($count+1).'</definedName>';}

        $entries=[
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="TPL-Planung" sheetId="1" r:id="rId1"/><sheet name="Legende" sheetId="2" r:id="rId2"/><sheet name="Listen" sheetId="3" state="hidden" r:id="rId3"/></sheets><definedNames>'.implode('',$defined).'</definedNames><calcPr calcId="191029" calcMode="auto"/></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'=>$mainSheet,'xl/worksheets/sheet2.xml'=>$legendSheet,'xl/worksheets/sheet3.xml'=>$listSheet,'xl/styles.xml'=>self::planningStyles(),
        ];
        return self::buildZip($entries);
    }

    /**
     * Erstellt die Phase-2-Arbeitsdatei für FI oder DSV. Die Auswahlliste der
     * Katalogleistungen richtet sich nach der am jeweiligen Rolloutobjekt
     * ausgewählten Klasse des Leistungserbringers.
     */
    public static function centralProviderPlanningBinary(array $workbook): string
    {
        $rows=(array)($workbook['rows']??[]);$legend=(array)($workbook['legend']??[]);$lists=(array)($workbook['lists']??[]);$validationRows=(array)($workbook['validation_rows']??[]);$classRows=array_map('intval',(array)($workbook['class_rows']??[]));$providerType=(string)($workbook['provider_type']??'FI');
        if(!$rows)throw new RuntimeException('Die Phase-2-Datei enthält keine Daten.');
        $headers=array_values((array)$rows[0]);$find=static function(string $header)use($headers):?int{$index=array_search($header,$headers,true);return $index===false?null:(int)$index;};
        $classColumn=$find('Rolloutklasse '.$providerType);$catalogColumn=$find('Katalogleistung '.$providerType);$outsideColumn=$find('Unterstützungsleistung außerhalb des Katalogs');$suggestionColumn=$find('Als neue Katalogleistung vorschlagen');$scheduleColumn=$find('Termin / Zeitraum');$noteColumn=$find('Bemerkung');$contactNameColumn=$find('Ansprechpartner');$contactPhoneColumn=$find('Telefonnummer');
        if($classColumn===null||$catalogColumn===null||$outsideColumn===null||$scheduleColumn===null||$noteColumn===null)throw new RuntimeException('Die Phase-2-Datei hat eine ungültige Spaltenstruktur.');
        $mainRows='';$validations=[];
        foreach($rows as $rowIndex=>$row){$excelRow=$rowIndex+1;$rowType=mb_strtolower(trim((string)($row[1]??'')));$cells='';foreach(array_values($row) as $columnIndex=>$value){if($rowIndex===0)$styleId=1;elseif($rowType==='rolloutobjekt'){$editable=array_filter([$classColumn,$contactNameColumn,$contactPhoneColumn],static fn($value):bool=>$value!==null);$styleId=in_array($columnIndex,$editable,true)?($columnIndex===$classColumn?4:5):6;}else{$editable=array_filter([$catalogColumn,$outsideColumn,$suggestionColumn,$scheduleColumn,$noteColumn],static fn($value):bool=>$value!==null);if($columnIndex===$scheduleColumn&&mb_strtolower(trim((string)$value))==='nicht relevant')$editable=array_values(array_diff($editable,[$scheduleColumn]));$styleId=in_array($columnIndex,$editable,true)?4:2;}$cells.=self::inlineCell($columnIndex,$excelRow,(string)$value,$styleId);}$height=$rowIndex===0?46:($rowType==='rolloutobjekt'?34:30);$mainRows.='<row r="'.$excelRow.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';}
        $classLetter=self::columnName($classColumn);$catalogLetter=self::columnName($catalogColumn);
        $classRows=array_values(array_filter($classRows,static fn(int $row):bool=>$row>=2));if($classRows){$ranges=[];foreach($classRows as $row)$ranges[]=$classLetter.$row;$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Klasse" error="Bitte eine Klasse aus dem hinterlegten '.$providerType.'-Schema auswählen." sqref="'.implode(' ',$ranges).'"><formula1>'.$providerType.'_CLASS</formula1></dataValidation>';}
        foreach($validationRows as $row=>$rule){$master=(int)($rule['master_row']??0);if($master<2)continue;$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Nicht passende Leistung" error="Bitte eine zur Klasse dieses Rolloutobjekts passende Katalogleistung auswählen." sqref="'.$catalogLetter.(int)$row.'"><formula1>INDIRECT(IF($'.$classLetter.'$'.$master.'=&quot;&quot;,&quot;'.$providerType.'_EMPTY&quot;,&quot;'.$providerType.'_L&quot;&amp;MATCH($'.$classLetter.'$'.$master.','.$providerType.'_CLASS,0)))</formula1></dataValidation>';if($suggestionColumn!==null)$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Angabe" error="Bitte Ja oder Nein auswählen." sqref="'.self::columnName($suggestionColumn).(int)$row.'"><formula1>&quot;Ja,Nein&quot;</formula1></dataValidation>';}
        $widthByHeader=['Projekt'=>32,'Datensatz'=>21,'Rolloutobjekt'=>32,'Rolloutbeginn'=>14,'Rolloutende'=>14,'Rolloutklasse FI'=>28,'Rolloutklasse DSV'=>28,'Katalogleistung FI'=>44,'Katalogleistung DSV'=>44,'Unterstützungsleistung außerhalb des Katalogs'=>44,'Als neue Katalogleistung vorschlagen'=>26,'Termin / Zeitraum'=>28,'Bemerkung'=>42,'Ansprechpartner'=>22,'Telefonnummer'=>18];$columns='';foreach($headers as $index=>$header){$width=$widthByHeader[(string)$header]??22;$columns.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.$width.'" customWidth="1"/>';}
        $last=max(1,count($rows));$validationXml=$validations?'<dataValidations count="'.count($validations).'">'.implode('',$validations).'</dataValidations>':'';
        $lastColumn=self::columnName(max(0,count($headers)-1));$mainSheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$lastColumn.$last.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="2" ySplit="1" topLeftCell="C2" activePane="bottomRight" state="frozen"/><selection pane="bottomRight" activeCell="C2" sqref="C2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>'.$columns.'</cols><sheetData>'.$mainRows.'</sheetData>'.$validationXml.'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
        $legendSheet=self::legendSheetXml($legend);
        $listNames=array_keys($lists);$listRows='';$maxListRows=1;foreach($lists as $values)$maxListRows=max($maxListRows,count((array)$values)+1);for($row=1;$row<=$maxListRows;$row++){$cells='';foreach($listNames as $column=>$name){$value=$row===1?$name:(string)(((array)$lists[$name])[$row-2]??'');$cells.=self::inlineCell($column,$row,$value,$row===1?1:2);}$listRows.='<row r="'.$row.'">'.$cells.'</row>';}$listLastColumn=self::columnName(max(0,count($listNames)-1));$listSheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$listLastColumn.$maxListRows.'"/><sheetData>'.$listRows.'</sheetData></worksheet>';
        $defined=[];foreach($listNames as $column=>$name){$count=max(1,count((array)$lists[$name]));$letter=self::columnName($column);$defined[]='<definedName name="'.htmlspecialchars($name,ENT_XML1|ENT_QUOTES,'UTF-8').'">Listen!$'.$letter.'$2:$'.$letter.'$'.($count+1).'</definedName>';}
        $entries=['[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet3.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>','_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>','xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Planung" sheetId="1" r:id="rId1"/><sheet name="Legende" sheetId="2" r:id="rId2"/><sheet name="Listen" sheetId="3" state="hidden" r:id="rId3"/></sheets><definedNames>'.implode('',$defined).'</definedNames><calcPr calcId="191029" calcMode="auto"/></workbook>','xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet3.xml"/><Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>','xl/worksheets/sheet1.xml'=>$mainSheet,'xl/worksheets/sheet2.xml'=>$legendSheet,'xl/worksheets/sheet3.xml'=>$listSheet,'xl/styles.xml'=>self::planningStyles()];
        return self::buildZip($entries);
    }

    /** Erstellt eine bearbeitbare Katalogdatei mit einer Ja/Nein-Spalte je Klasse. */
    public static function catalogMaintenanceBinary(array $workbook): string
    {
        $rows=(array)($workbook['rows']??[]);$legend=(array)($workbook['legend']??[]);$classColumns=array_map('intval',(array)($workbook['class_columns']??[]));$flagColumns=array_map('intval',(array)($workbook['flag_columns']??[]));
        if(!$rows)throw new RuntimeException('Die Katalogdatei enthält keine Daten.');
        $headers=array_values((array)$rows[0]);$lastRow=max(1,count($rows));$lastColumn=self::columnName(max(0,count($headers)-1));$xmlRows='';
        foreach($rows as $rowIndex=>$row){$cells='';foreach(array_values($row) as $columnIndex=>$value){$styleId=$rowIndex===0?1:($columnIndex===0?6:4);$cells.=self::inlineCell($columnIndex,$rowIndex+1,(string)$value,$styleId);}$xmlRows.='<row r="'.($rowIndex+1).'" ht="'.($rowIndex===0?46:30).'" customHeight="1">'.$cells.'</row>';}
        $widths=[];foreach($headers as $header)$widths[]=match((string)$header){'Leistung-ID'=>13,'Unterstützungsleistung'=>42,'Beschreibung'=>55,'Obligatorisch','Termin / Zeitraum relevant','Aktiv'=>24,default=>18};$columns='';foreach($widths as $index=>$width)$columns.='<col min="'.($index+1).'" max="'.($index+1).'" width="'.$width.'" customWidth="1"/>';
        $validations=[];foreach(array_values(array_unique(array_merge($classColumns,$flagColumns))) as $column){$letter=self::columnName($column);$validations[]='<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorTitle="Ungültige Angabe" error="Bitte Ja oder Nein auswählen." sqref="'.$letter.'2:'.$letter.$lastRow.'"><formula1>&quot;Ja,Nein&quot;</formula1></dataValidation>';}$validationXml=$validations?'<dataValidations count="'.count($validations).'">'.implode('',$validations).'</dataValidations>':'';
        $mainSheet='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:'.$lastColumn.$lastRow.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane xSplit="1" ySplit="1" topLeftCell="B2" activePane="bottomRight" state="frozen"/><selection pane="bottomRight" activeCell="B2" sqref="B2"/></sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols>'.$columns.'</cols><sheetData>'.$xmlRows.'</sheetData><autoFilter ref="A1:'.$lastColumn.$lastRow.'"/>'.$validationXml.'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
        $entries=['[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>','_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>','xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Leistungskatalog" sheetId="1" r:id="rId1"/><sheet name="Legende" sheetId="2" r:id="rId2"/></sheets></workbook>','xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>','xl/worksheets/sheet1.xml'=>$mainSheet,'xl/worksheets/sheet2.xml'=>self::legendSheetXml($legend),'xl/styles.xml'=>self::planningStyles()];
        return self::buildZip($entries);
    }

    private static function inlineCell(int $column,int $row,string $value,int $styleId=0): string
    {
        return '<c r="'.self::columnName($column).$row.'" s="'.$styleId.'" t="inlineStr"><is><t xml:space="preserve">'.htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
    }

    /** Fügt zu einer formatierten Einblattdatei das einheitliche Legendenblatt hinzu. */
    private static function appendLegendSheet(array &$entries,array $legend): void
    {
        $entries['[Content_Types].xml']=str_replace('</Types>','<Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>',$entries['[Content_Types].xml']);
        $entries['xl/workbook.xml']=str_replace('</sheets>','<sheet name="Legende" sheetId="2" r:id="rId3"/></sheets>',$entries['xl/workbook.xml']);
        $entries['xl/_rels/workbook.xml.rels']=str_replace('</Relationships>','<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/></Relationships>',$entries['xl/_rels/workbook.xml.rels']);
        $entries['xl/worksheets/sheet2.xml']=self::legendSheetXml($legend);
    }

    /** Rendert die gemeinsame Legende als vier Spalten breite, farbcodierte Übersicht. */
    private static function legendSheetXml(array $legend): string
    {
        $rows=(array)($legend['rows']??$legend);$xmlRows='';$merges=[];$firstCatalogHeader=0;
        foreach($rows as $rowIndex=>$entry){
            $excelRow=$rowIndex+1;$type=is_array($entry)&&isset($entry['type'])?(string)$entry['type']:'catalog';$values=is_array($entry)&&isset($entry['values'])?(array)$entry['values']:(array)$entry;
            $values=array_pad(array_values($values),4,'');$cells='';
            foreach($values as $columnIndex=>$value){
                $styleId=2;
                if($type==='title')$styleId=3;
                elseif($type==='section')$styleId=3;
                elseif($type==='header'){$styleId=1;if($firstCatalogHeader===0)$firstCatalogHeader=$excelRow;}
                elseif($type==='catalog'&&$columnIndex===0)$styleId=self::legendClassStyle((string)($entry['dimension']??''),(string)($entry['class']??$value));
                $cells.=self::inlineCell($columnIndex,$excelRow,(string)$value,$styleId);
            }
            if($type==='title'||$type==='section')$merges[]='A'.$excelRow.':D'.$excelRow;
            if($type==='note')$merges[]='B'.$excelRow.':D'.$excelRow;
            $height=$type==='title'?32:($type==='note'?48:($type==='section'?26:24));
            $xmlRows.='<row r="'.$excelRow.'" ht="'.$height.'" customHeight="1">'.$cells.'</row>';
        }
        $last=max(1,count($rows));$mergeXml=$merges?'<mergeCells count="'.count($merges).'">'.implode('',array_map(static fn(string $ref):string=>'<mergeCell ref="'.$ref.'"/>',$merges)).'</mergeCells>':'';
        $pane=$firstCatalogHeader?'<pane ySplit="'.($firstCatalogHeader-1).'" topLeftCell="A'.$firstCatalogHeader.'" activePane="bottomLeft" state="frozen"/>':'';
        return '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><dimension ref="A1:D'.$last.'"/><sheetViews><sheetView showGridLines="0" workbookViewId="0">'.$pane.'</sheetView></sheetViews><sheetFormatPr defaultRowHeight="18"/><cols><col min="1" max="1" width="27" customWidth="1"/><col min="2" max="2" width="16" customWidth="1"/><col min="3" max="3" width="48" customWidth="1"/><col min="4" max="4" width="26" customWidth="1"/></cols><sheetData>'.$xmlRows.'</sheetData>'.$mergeXml.'<pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    }

    private static function legendClassStyle(string $dimension,string $class): int
    {
        if($dimension==='functional')return match($class){'A'=>7,'B'=>8,'C'=>9,'D'=>10,default=>2};
        if($dimension==='technical')return match($class){'1'=>11,'2'=>12,'3'=>13,default=>2};
        if($dimension==='dsv'&&preg_match('/^L([1-8])$/',$class,$match))return match(min(3,(int)$match[1])){1=>11,2=>12,3=>13};
        return 2;
    }

    public static function downloadBinary(string $binary,string $filename): never
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Length: '.strlen($binary));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $binary;exit;
    }

    public static function download(array $rows, string $filename, string $sheetName = 'Rolloutobjekte', array $options = []): never
    {
        $binary = self::binary($rows, $sheetName, $options);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $binary;
        exit;
    }

    private static function planningStyles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="3"><font><sz val="11"/><color theme="1"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font><font><b/><sz val="11"/><color rgb="FF1F2937"/><name val="Calibri"/><family val="2"/></font></fonts>'
            . '<fills count="13"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE30613"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFDE9EA"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFF2CC"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE9ECEF"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFF4E8A"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFD1D7"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFFA8B3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF06A78"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDCEBFA"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFAFCFEB"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF78AEE0"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right><top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="14"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1" applyProtection="1"><alignment vertical="top" wrapText="1"/><protection locked="0"/></xf><xf numFmtId="0" fontId="2" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1" applyProtection="1"><alignment vertical="top" wrapText="1"/><protection locked="0"/></xf><xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="6" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="7" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="8" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="9" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="10" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="11" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="2" fillId="12" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles><dxfs count="4"><dxf><fill><patternFill patternType="solid"><fgColor rgb="FFEAF6EE"/></patternFill></fill></dxf><dxf><fill><patternFill patternType="solid"><fgColor rgb="FFEEF4FB"/></patternFill></fill></dxf><dxf><fill><patternFill patternType="solid"><fgColor rgb="FFFFF4DF"/></patternFill></fill></dxf><dxf><fill><patternFill patternType="solid"><fgColor rgb="FFF2F2F2"/></patternFill></fill></dxf></dxfs><tableStyles count="0" defaultTableStyle="TableStyleMedium2" defaultPivotStyle="PivotStyleLight16"/></styleSheet>';
    }

    /** Lädt mehrere bereits erzeugte Dateien als ZIP herunter, ebenfalls ohne ZipArchive. */
    public static function downloadArchive(array $files, string $filename): never
    {
        $binary = self::buildZip($files);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($binary));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $binary;
        exit;
    }

    /** Erstellt ein valides ZIP mit unkomprimierten Einträgen vollständig in PHP. */
    private static function buildZip(array $entries): string
    {
        $local = '';
        $central = '';
        $offset = 0;
        [$dosTime, $dosDate] = self::dosDateTime();
        foreach ($entries as $name => $content) {
            $name = str_replace('\\', '/', (string)$name);
            $content = (string)$content;
            $crc = crc32($content);
            $size = strlen($content);
            $nameLength = strlen($name);
            $localHeader = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0);
            $local .= $localHeader . $name . $content;
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $dosTime, $dosDate, $crc, $size, $size, $nameLength, 0, 0, 0, 0, 0, $offset) . $name;
            $offset += strlen($localHeader) + $nameLength + $size;
        }
        $count = count($entries);
        $end = pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($local), 0);
        return $local . $central . $end;
    }

    /** Liest die für XLSX benötigten ZIP-Einträge; unterstützt Store und Deflate. */
    private static function readZip(string $path): array
    {
        $binary = file_get_contents($path);
        if ($binary === false || strlen($binary) < 22) throw new RuntimeException('Die XLSX-Datei konnte nicht gelesen werden.');
        $endPos = strrpos($binary, "PK\x05\x06");
        if ($endPos === false) throw new RuntimeException('Die XLSX-Datei ist kein gültiges ZIP-Archiv.');
        $end = unpack('vdisk/vdiskStart/ventriesDisk/ventries/Vsize/Voffset/vcomment', substr($binary, $endPos + 4, 18));
        if (!$end) throw new RuntimeException('Das XLSX-Verzeichnis ist beschädigt.');
        $position = (int)$end['offset'];
        $entries = [];
        for ($i = 0; $i < (int)$end['entries']; $i++) {
            if (substr($binary, $position, 4) !== "PK\x01\x02") throw new RuntimeException('Ein XLSX-Verzeichniseintrag ist beschädigt.');
            $header = unpack('vversionMade/vversionNeed/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vnameLength/vextraLength/vcommentLength/vdisk/vinternal/Vexternal/VlocalOffset', substr($binary, $position + 4, 42));
            if (!$header) throw new RuntimeException('Ein XLSX-Verzeichniseintrag konnte nicht gelesen werden.');
            $name = substr($binary, $position + 46, (int)$header['nameLength']);
            $localOffset = (int)$header['localOffset'];
            $localLengths = unpack('vnameLength/vextraLength', substr($binary, $localOffset + 26, 4));
            if (!$localLengths) throw new RuntimeException('Ein XLSX-Dateieintrag ist beschädigt.');
            $dataOffset = $localOffset + 30 + (int)$localLengths['nameLength'] + (int)$localLengths['extraLength'];
            $data = substr($binary, $dataOffset, (int)$header['compressed']);
            if ((int)$header['method'] === 8) {
                $inflated = gzinflate($data);
                if ($inflated === false) throw new RuntimeException('Ein komprimierter XLSX-Eintrag konnte nicht entpackt werden.');
                $data = $inflated;
            } elseif ((int)$header['method'] !== 0) {
                throw new RuntimeException('Die XLSX-Datei verwendet eine nicht unterstützte Komprimierung.');
            }
            $entries[$name] = $data;
            $position += 46 + (int)$header['nameLength'] + (int)$header['extraLength'] + (int)$header['commentLength'];
        }
        return $entries;
    }

    private static function dosDateTime(): array
    {
        $now = getdate();
        $year = max(1980, (int)$now['year']);
        $time = ((int)$now['hours'] << 11) | ((int)$now['minutes'] << 5) | intdiv((int)$now['seconds'], 2);
        $date = (($year - 1980) << 9) | ((int)$now['mon'] << 5) | (int)$now['mday'];
        return [$time, $date];
    }

    private static function columnIndex(string $letters): int
    {
        $number = 0;
        foreach (str_split($letters) as $letter) $number = $number * 26 + ord($letter) - 64;
        return $number - 1;
    }

    private static function columnName(int $index): string
    {
        $name = '';
        for ($number = $index + 1; $number > 0; $number = intdiv($number - 1, 26)) {
            $name = chr(($number - 1) % 26 + 65) . $name;
        }
        return $name;
    }
}
