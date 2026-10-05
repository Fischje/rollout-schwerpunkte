<?php
declare(strict_types=1);

/**
 * PowerPoint-Export auf Basis der Vorlage „Synchronisation Rollout“.
 *
 * Aus der Vorlage bleiben Master, Layouts, Design, eingebettete Schriften, die Titelfolie (Folie 1)
 * und die Schlussfolie (letzte Folie) erhalten. Die Inhaltsfolien dazwischen werden neu auf dem
 * Layout „Text-Folie #1“ erzeugt: Platzhalter für Topline, Überschrift, Subline und Quelle,
 * darauf Kacheln, Balken und Tabellen als bearbeitbare PowerPoint-Formen.
 */
final class Pptx
{
    public const EMU_CM = 360000;
    /** Inhaltsbereich des Layouts „Text-Folie #1“ (EMU). */
    public const AREA_X = 1152000;
    public const AREA_Y = 1567298;
    public const AREA_W = 10490400;
    public const AREA_BOTTOM = 6120000;
    private const CONTENT_LAYOUT = 'slideLayout18.xml';
    private const NS = 'xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main"';
    private const SLIDE_TYPE = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide';
    private const SLIDE_CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.presentationml.slide+xml';

    /** @var array<string,string> */
    private array $files = [];
    /** @var list<string> Inhaltsfolien als Formen-XML */
    private array $slides = [];
    private int $shapeId = 10;

    public function __construct(string $templatePath)
    {
        if (!is_file($templatePath)) throw new RuntimeException('Die PowerPoint-Vorlage fehlt: ' . basename($templatePath));
        $zip = new ZipArchive();
        if ($zip->open($templatePath) !== true) throw new RuntimeException('Die PowerPoint-Vorlage lässt sich nicht öffnen.');
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            $this->files[$name] = (string)$zip->getFromIndex($i);
        }
        $zip->close();
        if (!isset($this->files['ppt/slideLayouts/' . self::CONTENT_LAYOUT])) throw new RuntimeException('In der Vorlage fehlt das Layout „Text-Folie #1“.');
    }

    public static function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Fügt eine Inhaltsfolie hinzu. $shapes ist das XML der Formen (Kacheln, Tabellen …). */
    public function addSlide(string $topline, string $title, string $subline, string $source, string $shapes): void
    {
        $placeholder = function (string $ph, string $name, string $text): string {
            if ($text === '') return '';
            return '<p:sp><p:nvSpPr><p:cNvPr id="' . $this->nextId() . '" name="' . self::esc($name) . '"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr>' . $ph . '</p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="de-DE" dirty="0"/><a:t>' . self::esc($text) . '</a:t></a:r></a:p></p:txBody></p:sp>';
        };
        $this->slides[] = $placeholder('<p:ph type="title"/>', 'Titel', $title)
            . $placeholder('<p:ph type="body" idx="13"/>', 'Topline', $topline)
            . $placeholder('<p:ph type="body" idx="14"/>', 'Subline', $subline)
            . $placeholder('<p:ph type="body" idx="15"/>', 'Quelle', $source)
            . "\n" . $shapes;
    }

    public function nextId(): int
    {
        return ++$this->shapeId;
    }

    /** Rechteck (optional abgerundet) mit Text. Farben als Hex ohne #. */
    public function box(int $x, int $y, int $w, int $h, ?string $fill, array $paragraphs, array $opt = []): string
    {
        $geom = !empty($opt['round']) ? 'roundRect' : 'rect';
        $fillXml = $fill === null ? '<a:noFill/>' : '<a:solidFill><a:srgbClr val="' . $fill . '"/></a:solidFill>';
        $line = isset($opt['line']) ? '<a:ln w="9525"><a:solidFill><a:srgbClr val="' . $opt['line'] . '"/></a:solidFill></a:ln>' : '<a:ln><a:noFill/></a:ln>';
        $anchor = $opt['anchor'] ?? 'ctr';
        $inset = (int)($opt['inset'] ?? 72000);
        $name = self::esc((string)($opt['name'] ?? 'Form'));
        $adj = !empty($opt['round']) ? '<a:avLst><a:gd name="adj" fmla="val 8000"/></a:avLst>' : '<a:avLst/>';
        return '<p:sp><p:nvSpPr><p:cNvPr id="' . $this->nextId() . '" name="' . $name . '"/><p:cNvSpPr/><p:nvPr/></p:nvSpPr><p:spPr><a:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . max(1, $w) . '" cy="' . max(1, $h) . '"/></a:xfrm><a:prstGeom prst="' . $geom . '">' . $adj . '</a:prstGeom>' . $fillXml . $line . '</p:spPr>'
            . '<p:txBody><a:bodyPr wrap="square" lIns="' . $inset . '" tIns="36000" rIns="' . $inset . '" bIns="36000" anchor="' . $anchor . '"><a:normAutofit/></a:bodyPr><a:lstStyle/>' . $this->paragraphs($paragraphs) . '</p:txBody></p:sp>';
    }

    /**
     * Absätze: je Eintrag ['text'=>…, 'size'=>pt, 'color'=>hex, 'bold'=>bool, 'align'=>'l'|'ctr'|'r'].
     * 'bold' nutzt die Hervorhebungsschrift der Vorlage (Sparkasse Medium).
     */
    public function paragraphs(array $paragraphs): string
    {
        $xml = '';
        foreach ($paragraphs as $p) {
            $size = (int)round(((float)($p['size'] ?? 12)) * 100);
            $color = $p['color'] ?? '000000';
            $font = !empty($p['bold']) ? '<a:latin typeface="Sparkasse Medium"/>' : '';
            $algn = $p['align'] ?? 'l';
            $xml .= '<a:p><a:pPr algn="' . $algn . '"><a:lnSpc><a:spcPct val="100000"/></a:lnSpc><a:spcBef><a:spcPts val="0"/></a:spcBef><a:buNone/></a:pPr>';
            $text = (string)($p['text'] ?? '');
            if ($text === '') {
                $xml .= '<a:endParaRPr lang="de-DE" sz="' . $size . '"/></a:p>';
                continue;
            }
            $xml .= '<a:r><a:rPr lang="de-DE" sz="' . $size . '" dirty="0"><a:solidFill><a:srgbClr val="' . $color . '"/></a:solidFill>' . $font . '</a:rPr><a:t>' . self::esc($text) . '</a:t></a:r></a:p>';
        }
        return $xml === '' ? '<a:p><a:endParaRPr lang="de-DE"/></a:p>' : $xml;
    }

    /**
     * Tabelle. $rows: Liste von Zeilen, jede Zeile Liste von Zellen
     * ['text'=>…, 'fill'=>hex|null, 'color'=>hex, 'bold'=>bool, 'align'=>…, 'span'=>n, 'size'=>pt].
     */
    public function table(int $x, int $y, array $colWidths, array $rows, int $rowHeight, float $fontSize = 9.0): string
    {
        $grid = '';
        foreach ($colWidths as $w) $grid .= '<a:gridCol w="' . (int)$w . '"/>';
        $border = static fn(string $side): string => '<a:' . $side . ' w="6350"><a:solidFill><a:srgbClr val="D9D9D9"/></a:solidFill></a:' . $side . '>';
        $rowsXml = '';
        foreach ($rows as $row) {
            $height = (int)($row['_height'] ?? $rowHeight);
            unset($row['_height']);
            $rowsXml .= '<a:tr h="' . $height . '">';
            $column = 0;
            foreach ($row as $cell) {
                $span = max(1, (int)($cell['span'] ?? 1));
                $size = (int)round(((float)($cell['size'] ?? $fontSize)) * 100);
                $font = !empty($cell['bold']) ? '<a:latin typeface="Sparkasse Medium"/>' : '';
                $text = (string)($cell['text'] ?? '');
                $run = $text === '' ? '<a:endParaRPr lang="de-DE" sz="' . $size . '"/>' : '<a:r><a:rPr lang="de-DE" sz="' . $size . '" dirty="0"><a:solidFill><a:srgbClr val="' . ($cell['color'] ?? '000000') . '"/></a:solidFill>' . $font . '</a:rPr><a:t>' . self::esc($text) . '</a:t></a:r>';
                $fill = isset($cell['fill']) && $cell['fill'] !== null ? '<a:solidFill><a:srgbClr val="' . $cell['fill'] . '"/></a:solidFill>' : '<a:noFill/>';
                $rowsXml .= '<a:tc' . ($span > 1 ? ' gridSpan="' . $span . '"' : '') . '><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:pPr algn="' . ($cell['align'] ?? 'l') . '"><a:lnSpc><a:spcPct val="100000"/></a:lnSpc><a:spcBef><a:spcPts val="0"/></a:spcBef><a:buNone/></a:pPr>' . $run . '</a:p></a:txBody>'
                    . '<a:tcPr marL="54000" marR="54000" marT="18000" marB="18000" anchor="ctr">' . $border('lnL') . $border('lnR') . $border('lnT') . $border('lnB') . $fill . '</a:tcPr></a:tc>';
                for ($i = 1; $i < $span; $i++) $rowsXml .= '<a:tc hMerge="1"><a:txBody><a:bodyPr/><a:lstStyle/><a:p><a:endParaRPr lang="de-DE"/></a:p></a:txBody><a:tcPr/></a:tc>';
                $column += $span;
            }
            $rowsXml .= '</a:tr>';
        }
        $width = array_sum(array_map('intval', $colWidths));
        $height = 0;
        foreach ($rows as $row) $height += (int)($row['_height'] ?? $rowHeight);
        return '<p:graphicFrame><p:nvGraphicFramePr><p:cNvPr id="' . $this->nextId() . '" name="Tabelle"/><p:cNvGraphicFramePr><a:graphicFrameLocks noGrp="1"/></p:cNvGraphicFramePr><p:nvPr/></p:nvGraphicFramePr><p:xfrm><a:off x="' . $x . '" y="' . $y . '"/><a:ext cx="' . $width . '" cy="' . $height . '"/></p:xfrm>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/table"><a:tbl><a:tblPr firstRow="1" bandRow="0"/><a:tblGrid>' . $grid . '</a:tblGrid>' . $rowsXml . '</a:tbl></a:graphicData></a:graphic></p:graphicFrame>';
    }

    /** Erzeugt die fertige Präsentation als Binärstring. */
    public function build(string $titleSubtitle, string $footer): string
    {
        $files = $this->files;
        $presentation = $files['ppt/presentation.xml'];
        $presRels = $files['ppt/_rels/presentation.xml.rels'];

        // Bisherige Folien in Reihenfolge ermitteln: erste = Titel, letzte = Schlussfolie
        preg_match_all('~<p:sldId [^>]*r:id="([^"]+)"[^>]*/>~', $presentation, $ids);
        $targets = [];
        foreach ($ids[1] as $rid) {
            if (!preg_match('~<Relationship [^>]*Id="' . preg_quote($rid, '~') . '"[^>]*Target="([^"]+)"~', $presRels, $m)
                && !preg_match('~<Relationship [^>]*Target="([^"]+)"[^>]*Id="' . preg_quote($rid, '~') . '"~', $presRels, $m)) {
                throw new RuntimeException('Die Folienreihenfolge der Vorlage ist nicht lesbar.');
            }
            $targets[] = 'ppt/' . ltrim($m[1], '/');
        }
        if (count($targets) < 2) throw new RuntimeException('Die Vorlage braucht mindestens eine Titel- und eine Schlussfolie.');
        $titleSlide = $targets[0];
        $closingSlide = $targets[count($targets) - 1];
        $titleXml = $files[$titleSlide];
        $titleRels = $files[str_replace('slides/', 'slides/_rels/', $titleSlide) . '.rels'];
        $closingXml = $files[$closingSlide];
        $closingRels = $files[str_replace('slides/', 'slides/_rels/', $closingSlide) . '.rels'];

        // Alle alten Folien entfernen
        foreach (array_keys($files) as $name) {
            if (preg_match('~^ppt/slides/(_rels/)?slide\d+\.xml(\.rels)?$~', $name)) unset($files[$name]);
        }

        $titleXml = str_replace('>Projektname, Datum<', '>' . self::esc($titleSubtitle) . '<', $titleXml);
        $closingXml = str_replace('>Titel der Präsentation | Name | Ort, Datum<', '>' . self::esc($footer) . '<', $closingXml);

        $newSlides = [[$titleXml, $titleRels]];
        $layoutRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/' . self::CONTENT_LAYOUT . '"/></Relationships>';
        foreach ($this->slides as $index => $shapes) {
            $number = $index + 2;
            $footerXml = '<p:sp><p:nvSpPr><p:cNvPr id="' . $this->nextId() . '" name="Fußzeile"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="ftr" sz="quarter" idx="16"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:r><a:rPr lang="de-DE"/><a:t>' . self::esc($footer) . '</a:t></a:r></a:p></p:txBody></p:sp>'
                . '<p:sp><p:nvSpPr><p:cNvPr id="' . $this->nextId() . '" name="Foliennummer"/><p:cNvSpPr><a:spLocks noGrp="1"/></p:cNvSpPr><p:nvPr><p:ph type="sldNum" sz="quarter" idx="12"/></p:nvPr></p:nvSpPr><p:spPr/><p:txBody><a:bodyPr/><a:lstStyle/><a:p><a:fld id="{6D3B2C1A-5E4F-4A7B-9C8D-0E1F2A3B4C5D}" type="slidenum"><a:rPr lang="de-DE"/><a:t>' . $number . '</a:t></a:fld><a:endParaRPr lang="de-DE"/></a:p></p:txBody></p:sp>';
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n" . '<p:sld ' . self::NS . '><p:cSld><p:spTree><p:nvGrpSpPr><p:cNvPr id="1" name=""/><p:cNvGrpSpPr/><p:nvPr/></p:nvGrpSpPr><p:grpSpPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="0" cy="0"/><a:chOff x="0" y="0"/><a:chExt cx="0" cy="0"/></a:xfrm></p:grpSpPr>'
                . $shapes . $footerXml . '</p:spTree></p:cSld><p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr></p:sld>';
            $newSlides[] = [$xml, $layoutRels];
        }
        $newSlides[] = [$closingXml, $closingRels];

        // Folien, Beziehungen, Folienliste und Inhaltstypen neu schreiben
        $presRels = preg_replace('~<Relationship [^>]*Type="' . preg_quote(self::SLIDE_TYPE, '~') . '"[^>]*/>~', '', $presRels);
        $sldIds = '';
        $relXml = '';
        $types = preg_replace('~<Override PartName="/ppt/slides/slide\d+\.xml"[^>]*/>~', '', $files['[Content_Types].xml']);
        $overrides = '';
        foreach ($newSlides as $index => [$xml, $rels]) {
            $number = $index + 1;
            $files['ppt/slides/slide' . $number . '.xml'] = $xml;
            $files['ppt/slides/_rels/slide' . $number . '.xml.rels'] = $rels;
            $relXml .= '<Relationship Id="rIdSlide' . $number . '" Type="' . self::SLIDE_TYPE . '" Target="slides/slide' . $number . '.xml"/>';
            $sldIds .= '<p:sldId id="' . (255 + $number) . '" r:id="rIdSlide' . $number . '"/>';
            $overrides .= '<Override PartName="/ppt/slides/slide' . $number . '.xml" ContentType="' . self::SLIDE_CONTENT_TYPE . '"/>';
        }
        $files['ppt/_rels/presentation.xml.rels'] = str_replace('</Relationships>', $relXml . '</Relationships>', $presRels);
        $files['ppt/presentation.xml'] = preg_replace('~<p:sldIdLst>.*?</p:sldIdLst>~s', '<p:sldIdLst>' . $sldIds . '</p:sldIdLst>', $presentation);
        $files['[Content_Types].xml'] = str_replace('</Types>', $overrides . '</Types>', $types);
        if (isset($files['docProps/app.xml'])) $files['docProps/app.xml'] = preg_replace('~<Slides>\d+</Slides>~', '<Slides>' . count($newSlides) . '</Slides>', $files['docProps/app.xml']);

        $path = tempnam(sys_get_temp_dir(), 'pptx');
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Die PowerPoint-Datei konnte nicht erzeugt werden.');
        $zip->addFromString('[Content_Types].xml', $files['[Content_Types].xml']);
        unset($files['[Content_Types].xml']);
        foreach ($files as $name => $content) $zip->addFromString($name, $content);
        $zip->close();
        $binary = (string)file_get_contents($path);
        @unlink($path);
        return $binary;
    }

    public static function download(string $binary, string $filename): never
    {
        header('Content-Type: application/vnd.openxmlformats-officedocument.presentationml.presentation');
        header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
        header('Content-Length: ' . strlen($binary));
        echo $binary;
        exit;
    }
}
