<?php
/**
 * SimplePDF — Zero-dependency PDF generator for PHP
 * 
 * Generates valid PDF 1.4 files using raw PDF primitives.
 * Supports: text, lines, rectangles, colors, Helvetica font (built-in).
 * No TCPDF, FPDF, or Composer required.
 */
class SimplePDF {
    private $pages = [];
    private $currentPage = -1;
    private $objects = [];
    private $objectCount = 0;
    private $buffer = '';
    private $offsets = [];
    
    // Page dimensions (A4 in points: 595.28 x 841.89)
    private $pageW = 595.28;
    private $pageH = 841.89;
    
    // Current state
    private $fontSize = 12;
    private $fontStyle = ''; // '', 'B', 'I', 'BI'
    private $textColorR = 0;
    private $textColorG = 0;
    private $textColorB = 0;
    private $drawColorR = 0;
    private $drawColorG = 0;
    private $drawColorB = 0;
    private $fillColorR = 255;
    private $fillColorG = 255;
    private $fillColorB = 255;
    private $lineWidth = 0.567; // 0.2mm
    private $cursorY = 0;
    private $margins = ['l' => 56.69, 'r' => 56.69, 't' => 42.52, 'b' => 42.52]; // 20mm, 20mm, 15mm, 15mm
    
    // Font metrics (Helvetica character widths at 1pt, scaled by fontSize)
    private $charWidths = [];
    private $fontNames = [
        '' => 'Helvetica',
        'B' => 'Helvetica-Bold',
        'I' => 'Helvetica-Oblique',
        'BI' => 'Helvetica-BoldOblique'
    ];
    private $fontObjects = [];
    
    public function __construct() {
        // Helvetica average char width approximation (1/1000 of text space unit)
        $this->charWidths = array_fill(0, 256, 556);
        // Common character widths for Helvetica (approximate, good enough for layout)
        $cw = [32=>278,33=>278,34=>355,35=>556,36=>556,37=>889,38=>667,39=>191,40=>333,41=>333,42=>389,43=>584,44=>278,45=>333,46=>278,47=>278,
            48=>556,49=>556,50=>556,51=>556,52=>556,53=>556,54=>556,55=>556,56=>556,57=>556,58=>278,59=>278,60=>584,61=>584,62=>584,63=>556,
            64=>1015,65=>667,66=>667,67=>722,68=>722,69=>667,70=>611,71=>778,72=>722,73=>278,74=>500,75=>667,76=>556,77=>833,78=>722,79=>778,
            80=>667,81=>778,82=>722,83=>667,84=>611,85=>722,86=>667,87=>944,88=>667,89=>667,90=>611,91=>278,92=>278,93=>278,94=>469,95=>556,
            96=>333,97=>556,98=>556,99=>500,100=>556,101=>556,102=>278,103=>556,104=>556,105=>222,106=>222,107=>500,108=>222,109=>833,110=>556,
            111=>556,112=>556,113=>556,114=>333,115=>500,116=>278,117=>556,118=>500,119=>722,120=>500,121=>500,122=>500,123=>334,124=>260,125=>334,
            126=>584,127=>556];
        foreach ($cw as $k => $v) $this->charWidths[$k] = $v;
    }
    
    public function addPage() {
        $this->currentPage++;
        $this->pages[$this->currentPage] = '';
        $this->cursorY = $this->margins['t'];
    }
    
    public function setFont($style = '', $size = 12) {
        $this->fontStyle = $style;
        $this->fontSize = $size;
    }
    
    public function setTextColor($r, $g, $b) {
        $this->textColorR = $r; $this->textColorG = $g; $this->textColorB = $b;
    }
    
    public function setDrawColor($r, $g, $b) {
        $this->drawColorR = $r; $this->drawColorG = $g; $this->drawColorB = $b;
    }
    
    public function setFillColor($r, $g, $b) {
        $this->fillColorR = $r; $this->fillColorG = $g; $this->fillColorB = $b;
    }
    
    public function setLineWidth($w) {
        $this->lineWidth = $w;
    }
    
    public function getY() { return $this->cursorY; }
    public function setY($y) { $this->cursorY = $y; }
    public function getPageWidth() { return $this->pageW; }
    
    public function ln($h = 0) {
        if ($h == 0) $h = $this->fontSize * 1.2 / 72 * 72; // default line height
        $this->cursorY += $h;
    }
    
    /**
     * Draw a line
     */
    public function line($x1, $y1, $x2, $y2) {
        $s = sprintf('%.2f w ', $this->lineWidth);
        $s .= sprintf('%.3f %.3f %.3f RG ', $this->drawColorR/255, $this->drawColorG/255, $this->drawColorB/255);
        $s .= sprintf('%.2f %.2f m %.2f %.2f l S ', $x1, $this->pageH - $y1, $x2, $this->pageH - $y2);
        $this->pages[$this->currentPage] .= $s;
    }
    
    /**
     * Draw a filled rectangle
     */
    public function rect($x, $y, $w, $h, $style = 'F') {
        $op = ($style === 'F') ? 'f' : (($style === 'FD' || $style === 'DF') ? 'B' : 'S');
        $s = sprintf('%.3f %.3f %.3f RG ', $this->drawColorR/255, $this->drawColorG/255, $this->drawColorB/255);
        $s .= sprintf('%.3f %.3f %.3f rg ', $this->fillColorR/255, $this->fillColorG/255, $this->fillColorB/255);
        $s .= sprintf('%.2f %.2f %.2f %.2f re %s ', $x, $this->pageH - $y - $h, $w, $h, $op);
        $this->pages[$this->currentPage] .= $s;
    }
    
    /**
     * Get text width in points
     */
    public function getTextWidth($text) {
        $w = 0;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($text[$i]);
            $w += ($this->charWidths[$c] ?? 556);
        }
        return $w * $this->fontSize / 1000;
    }
    
    /**
     * Write a cell (text in a box)
     * @param float $w Width (0 = remaining page width)
     * @param float $h Height
     * @param string $text Text content
     * @param string $align L, C, R
     * @param bool $filled Fill background
     * @param bool $newLine Move to next line after
     */
    public function cell($w, $h, $text = '', $align = 'L', $filled = false, $newLine = false) {
        $x = $this->margins['l'];
        $contentW = $this->pageW - $this->margins['l'] - $this->margins['r'];
        if ($w == 0) $w = $contentW;
        
        // Check page break
        if ($this->cursorY + $h > $this->pageH - $this->margins['b']) {
            $this->addPage();
        }
        
        // Fill background
        if ($filled) {
            $s = sprintf('%.3f %.3f %.3f rg ', $this->fillColorR/255, $this->fillColorG/255, $this->fillColorB/255);
            $s .= sprintf('%.2f %.2f %.2f %.2f re f ', $x, $this->pageH - $this->cursorY - $h, $w, $h);
            $this->pages[$this->currentPage] .= $s;
        }
        
        if ($text !== '') {
            $textW = $this->getTextWidth($text);
            if ($align === 'R') {
                $dx = $w - $textW - 2;
            } elseif ($align === 'C') {
                $dx = ($w - $textW) / 2;
            } else {
                $dx = 2;
            }
            
            $textY = $this->cursorY + ($h - $this->fontSize * 0.75) / 2 + $this->fontSize * 0.75;
            $this->putText($x + $dx, $textY, $text);
        }
        
        if ($newLine) {
            $this->cursorY += $h;
        }
    }
    
    /**
     * Write text in two columns (left + right aligned)  
     */
    public function cellLR($leftText, $rightText, $h = 14) {
        $contentW = $this->pageW - $this->margins['l'] - $this->margins['r'];
        $halfW = $contentW / 2;
        
        // Check page break
        if ($this->cursorY + $h > $this->pageH - $this->margins['b']) {
            $this->addPage();
        }
        
        // Left text
        if ($leftText !== '') {
            $textY = $this->cursorY + ($h - $this->fontSize * 0.75) / 2 + $this->fontSize * 0.75;
            $this->putText($this->margins['l'] + 2, $textY, $leftText);
        }
        
        // Right text
        if ($rightText !== '') {
            $tw = $this->getTextWidth($rightText);
            $textY = $this->cursorY + ($h - $this->fontSize * 0.75) / 2 + $this->fontSize * 0.75;
            $this->putText($this->pageW - $this->margins['r'] - $tw - 2, $textY, $rightText);
        }
        
        $this->cursorY += $h;
    }
    
    /**
     * Write raw text at position
     */
    private function putText($x, $y, $text) {
        $fontKey = $this->fontStyle ?: '';
        $fontName = $this->fontNames[$fontKey] ?? 'Helvetica';
        
        // Escape special PDF characters
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        
        $s = 'BT ';
        $s .= sprintf('%.3f %.3f %.3f rg ', $this->textColorR/255, $this->textColorG/255, $this->textColorB/255);
        $s .= sprintf('/F%s %.2f Tf ', $this->getFontIndex($fontKey), $this->fontSize);
        $s .= sprintf('%.2f %.2f Td ', $x, $this->pageH - $y);
        $s .= sprintf('(%s) Tj ', $text);
        $s .= 'ET ';
        $this->pages[$this->currentPage] .= $s;
    }
    
    private function getFontIndex($style) {
        $map = ['' => 1, 'B' => 2, 'I' => 3, 'BI' => 4];
        return $map[$style] ?? 1;
    }
    
    /**
     * Output PDF as string
     */
    public function output() {
        $this->buffer = '';
        
        // Header
        $this->buffer .= "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        
        // Font objects (Helvetica family - built-in, no embedding needed)
        $fonts = [
            1 => 'Helvetica',
            2 => 'Helvetica-Bold', 
            3 => 'Helvetica-Oblique',
            4 => 'Helvetica-BoldOblique'
        ];
        
        $fontObjIds = [];
        foreach ($fonts as $idx => $name) {
            $objId = $this->newObj();
            $fontObjIds[$idx] = $objId;
            $this->putObj("<< /Type /Font /Subtype /Type1 /BaseFont /$name /Encoding /WinAnsiEncoding >>");
        }
        
        // Page contents
        $pageObjIds = [];
        $contentObjIds = [];
        
        foreach ($this->pages as $i => $content) {
            // Content stream
            $contentId = $this->newObj();
            $contentObjIds[$i] = $contentId;
            $stream = $content;
            $this->putObj("<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");
        }
        
        // Pages
        $pagesId = $this->newObj();
        $kids = '';
        
        // Create page objects
        $pageIds = [];
        foreach ($this->pages as $i => $content) {
            $pageId = $this->newObj();
            $pageIds[] = $pageId;
            
            $fontResources = '';
            foreach ($fontObjIds as $idx => $objId) {
                $fontResources .= "/F$idx $objId 0 R ";
            }
            
            $this->putObj("<< /Type /Page /Parent $pagesId 0 R /MediaBox [0 0 {$this->pageW} {$this->pageH}] " .
                "/Contents {$contentObjIds[$i]} 0 R " .
                "/Resources << /Font << $fontResources >> >> >>");
        }
        
        // Update pages object
        $kidsStr = implode(' 0 R ', $pageIds) . ' 0 R';
        $count = count($this->pages);
        // We need to rewrite the pages object
        $this->offsets[$pagesId] = strlen($this->buffer);
        $this->buffer .= "$pagesId 0 obj\n<< /Type /Pages /Kids [$kidsStr] /Count $count >>\nendobj\n";
        
        // Catalog
        $catalogId = $this->newObj();
        $this->putObj("<< /Type /Catalog /Pages $pagesId 0 R >>");
        
        // Info
        $infoId = $this->newObj();
        $date = date('YmdHis');
        $this->putObj("<< /Producer (MFA Tools Invoice Generator) /CreationDate (D:$date) >>");
        
        // Cross-reference table
        $xrefPos = strlen($this->buffer);
        $this->buffer .= "xref\n";
        $this->buffer .= "0 " . ($this->objectCount + 1) . "\n";
        $this->buffer .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $this->objectCount; $i++) {
            $this->buffer .= sprintf("%010d 00000 n \n", $this->offsets[$i] ?? 0);
        }
        
        // Trailer
        $this->buffer .= "trailer\n";
        $this->buffer .= "<< /Size " . ($this->objectCount + 1) . " /Root $catalogId 0 R /Info $infoId 0 R >>\n";
        $this->buffer .= "startxref\n$xrefPos\n%%EOF\n";
        
        return $this->buffer;
    }
    
    private function newObj() {
        $this->objectCount++;
        $this->offsets[$this->objectCount] = strlen($this->buffer);
        return $this->objectCount;
    }
    
    private function putObj($content) {
        $id = $this->objectCount;
        $this->buffer .= "$id 0 obj\n$content\nendobj\n";
    }
}
