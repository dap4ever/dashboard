<?php
/**
 * SimpleXLSX - Simple XLSX parser for PHP
 * Simplified version for reading XLSX files
 */

class SimpleXLSX {
    private $workbook;
    private $sheets;
    private $sharedStrings;
    private $error;
    
    public function __construct() {
        $this->sheets = array();
        $this->sharedStrings = array();
    }
    
    public static function parse($filename) {
        $xlsx = new self();
        if ($xlsx->_parse($filename)) {
            return $xlsx;
        }
        return false;
    }
    
    public static function parseError() {
        return 'Erro ao processar arquivo XLSX';
    }
    
    private function _parse($filename) {
        if (!file_exists($filename)) {
            $this->error = 'Arquivo não encontrado';
            return false;
        }
        
        // Verificar se é um arquivo ZIP (XLSX é um ZIP)
        $zip = new ZipArchive();
        if ($zip->open($filename) !== TRUE) {
            $this->error = 'Não foi possível abrir o arquivo XLSX';
            return false;
        }
        
        // Ler strings compartilhadas
        $this->_parseSharedStrings($zip);
        
        // Ler a primeira planilha
        $this->_parseWorksheet($zip, 'xl/worksheets/sheet1.xml');
        
        $zip->close();
        return true;
    }
    
    private function _parseSharedStrings($zip) {
        $sharedStringsXML = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXML === false) {
            return;
        }
        
        $xml = simplexml_load_string($sharedStringsXML);
        if ($xml === false) {
            return;
        }
        
        foreach ($xml->si as $si) {
            if (isset($si->t)) {
                $this->sharedStrings[] = (string)$si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $r) {
                    if (isset($r->t)) {
                        $text .= (string)$r->t;
                    }
                }
                $this->sharedStrings[] = $text;
            } else {
                $this->sharedStrings[] = '';
            }
        }
    }
    
    private function _parseWorksheet($zip, $sheetPath) {
        $worksheetXML = $zip->getFromName($sheetPath);
        if ($worksheetXML === false) {
            return;
        }
        
        $xml = simplexml_load_string($worksheetXML);
        if ($xml === false) {
            return;
        }
        
        $rows = array();
        $currentRow = 0;
        
        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rowIndex = (int)$row['r'] - 1;
                $rows[$rowIndex] = array();
                
                if (isset($row->c)) {
                    foreach ($row->c as $cell) {
                        $cellRef = (string)$cell['r'];
                        $colIndex = $this->_getColumnIndex($cellRef);
                        
                        $value = '';
                        if (isset($cell->v)) {
                            $cellValue = (string)$cell->v;
                            
                            // Verificar tipo da célula
                            if (isset($cell['t']) && (string)$cell['t'] === 's') {
                                // String compartilhada
                                $stringIndex = (int)$cellValue;
                                if (isset($this->sharedStrings[$stringIndex])) {
                                    $value = $this->sharedStrings[$stringIndex];
                                }
                            } else {
                                // Valor direto
                                $value = $cellValue;
                            }
                        } elseif (isset($cell->is->t)) {
                            // Inline string
                            $value = (string)$cell->is->t;
                        }
                        
                        $rows[$rowIndex][$colIndex] = $value;
                    }
                }
                
                // Preencher células vazias
                if (!empty($rows[$rowIndex])) {
                    $maxCol = max(array_keys($rows[$rowIndex]));
                    for ($i = 0; $i <= $maxCol; $i++) {
                        if (!isset($rows[$rowIndex][$i])) {
                            $rows[$rowIndex][$i] = '';
                        }
                    }
                    ksort($rows[$rowIndex]);
                }
            }
        }
        
        // Remover índices de linha e reorganizar
        ksort($rows);
        $this->sheets[0] = array_values($rows);
    }
    
    private function _getColumnIndex($cellRef) {
        // Extrair a parte da coluna (letras) da referência da célula
        preg_match('/([A-Z]+)/', $cellRef, $matches);
        if (empty($matches)) {
            return 0;
        }
        
        $column = $matches[1];
        $index = 0;
        $length = strlen($column);
        
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        
        return $index - 1;
    }
    
    public function rows($sheetIndex = 0) {
        if (isset($this->sheets[$sheetIndex])) {
            return $this->sheets[$sheetIndex];
        }
        return array();
    }
    
    public function getError() {
        return $this->error;
    }
}

?>