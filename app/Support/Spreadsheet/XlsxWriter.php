<?php

namespace App\Support\Spreadsheet;

use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Minimal .xlsx writer: inline strings, numbers, bold rows, column widths and merged cells.
 */
class XlsxWriter
{
    /** Excel's paper size code for A4 */
    public const PAPER_A4 = 9;

    /** Excel's paper size code for A5 */
    public const PAPER_A5 = 11;

    /**
     * @var list<array{name: string, rows: list<list<string|int|float|null>>, widths: list<int>, bold: list<int>, merges: list<string>, print: ?array{paper: int, orientation: string}}>
     */
    private array $sheets = [];

    /**
     * @param  list<list<string|int|float|null>>  $rows
     * @param  list<int>  $widths  column widths in characters
     * @param  list<int>  $boldRows  zero-based row indexes to render in bold
     * @param  list<string>  $merges  ranges such as "A1:F1"
     * @param  array{paper: int, orientation: string}|null  $print  paper size and orientation; the sheet is then fitted to one page
     */
    public function addSheet(string $name, array $rows, array $widths = [], array $boldRows = [], array $merges = [], ?array $print = null): static
    {
        $name = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', '', $name)) ?: 'Sheet', 0, 31);
        $taken = array_column($this->sheets, 'name');
        $unique = $name;

        for ($suffix = 2; in_array($unique, $taken, true); $suffix++) {
            $unique = mb_substr($name, 0, 28).' '.$suffix;
        }

        $this->sheets[] = ['name' => $unique, 'rows' => $rows, 'widths' => $widths, 'bold' => $boldRows, 'merges' => $merges, 'print' => $print];

        return $this;
    }

    public function save(string $path): void
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1', []);
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create spreadsheet at {$path}.");
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->addFromString('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheet($sheet));
        }

        $zip->close();
    }

    public function download(string $filename): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        $this->save($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public static function columnLetter(int $index): string
    {
        $letter = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letter = chr(65 + ($index - 1) % 26).$letter;
        }

        return $letter;
    }

    private function contentTypes(): string
    {
        $overrides = '';

        foreach (array_keys($this->sheets) as $index) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';

        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.$this->escape($sheet['name']).'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';

        foreach (array_keys($this->sheets) as $index) {
            $rels .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }

        $rels .= '<Relationship Id="rId'.(count($this->sheets) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="4">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="164" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/>'
            .'</cellXfs></styleSheet>';
    }

    /**
     * @param  array{name: string, rows: list<list<string|int|float|null>>, widths: list<int>, bold: list<int>, merges: list<string>, print: ?array{paper: int, orientation: string}}  $sheet
     */
    private function sheet(array $sheet): string
    {
        $cols = '';

        foreach ($sheet['widths'] as $index => $width) {
            $cols .= '<col min="'.($index + 1).'" max="'.($index + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $rows = '';

        foreach ($sheet['rows'] as $rowIndex => $row) {
            $bold = in_array($rowIndex, $sheet['bold'], true);
            $cells = '';

            foreach (array_values($row) as $colIndex => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $ref = self::columnLetter($colIndex).($rowIndex + 1);

                if (is_int($value) || is_float($value)) {
                    $cells .= '<c r="'.$ref.'" s="'.($bold ? 3 : 2).'"><v>'.$value.'</v></c>';
                } else {
                    $cells .= '<c r="'.$ref.'" t="inlineStr"'.($bold ? ' s="1"' : '').'><is><t xml:space="preserve">'.$this->escape((string) $value).'</t></is></c>';
                }
            }

            $rows .= '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }

        $merges = $sheet['merges'] === [] ? '' : '<mergeCells count="'.count($sheet['merges']).'">'
            .implode('', array_map(fn (string $range) => '<mergeCell ref="'.$range.'"/>', $sheet['merges']))
            .'</mergeCells>';

        $print = $sheet['print'];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .($print ? '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>' : '')
            .($cols === '' ? '' : '<cols>'.$cols.'</cols>')
            .'<sheetData>'.$rows.'</sheetData>'
            .$merges
            .($print ? '<pageMargins left="0.3" right="0.3" top="0.3" bottom="0.3" header="0" footer="0"/>'
                .'<pageSetup paperSize="'.$print['paper'].'" orientation="'.$this->escape($print['orientation']).'" fitToWidth="1" fitToHeight="1"/>' : '')
            .'</worksheet>';
    }

    private function escape(string $value): string
    {
        $value = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value);

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
