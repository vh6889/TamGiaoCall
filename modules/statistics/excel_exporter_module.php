<?php
/**
 * Excel Exporter Module
 * Export data to Excel format using PHPSpreadsheet or simple CSV
 */

namespace Modules\Statistics\Exporters;

class ExcelExporter {
    private $data = [];
    private $headers = [];
    private $filename = 'export';
    private $title = '';
    private $metadata = [];
    private $format = 'xlsx'; // xlsx, csv, xls
    
    /**
     * Set data to export
     */
    public function setData(array $data) {
        $this->data = $data;
        
        // Auto-detect headers if not set
        if (empty($this->headers) && !empty($data)) {
            $this->headers = array_keys(reset($data));
        }
        
        return $this;
    }
    
    /**
     * Set column headers
     */
    public function setHeaders(array $headers) {
        $this->headers = $headers;
        return $this;
    }
    
    /**
     * Set filename
     */
    public function setFilename($filename) {
        $this->filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return $this;
    }
    
    /**
     * Set sheet title
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Add metadata
     */
    public function addMetadata($key, $value) {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * Set export format
     */
    public function setFormat($format) {
        $this->format = $format;
        return $this;
    }
    
    /**
     * Export to Excel using PHPSpreadsheet (if available)
     */
    public function exportExcel() {
        // Check if PHPSpreadsheet is available
        if (class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            return $this->exportWithPhpSpreadsheet();
        } else {
            // Fallback to simple HTML table export
            return $this->exportHtmlExcel();
        }
    }
    
    /**
     * Export using PHPSpreadsheet
     */
    private function exportWithPhpSpreadsheet() {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        if ($this->title) {
            $sheet->setTitle(substr($this->title, 0, 31)); // Excel limit
        }
        
        // Add metadata
        $row = 1;
        if (!empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                $sheet->setCellValue('A' . $row, $key . ':');
                $sheet->setCellValue('B' . $row, $value);
                $row++;
            }
            $row++; // Empty row
        }
        
        // Add headers
        $col = 'A';
        foreach ($this->headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->getFont()->setBold(true);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        $row++;
        
        // Add data
        foreach ($this->data as $dataRow) {
            $col = 'A';
            foreach ($this->headers as $key) {
                $value = $dataRow[$key] ?? '';
                $sheet->setCellValue($col . $row, $value);
                $col++;
            }
            $row++;
        }
        
        // Apply styles
        $lastColumn = $col;
        $lastRow = $row - 1;
        
        // Header style
        $headerRow = empty($this->metadata) ? 1 : count($this->metadata) + 2;
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)
              ->getFill()
              ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
              ->getStartColor()
              ->setARGB('FFE0E0E0');
        
        // Border
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $lastRow)
              ->getBorders()
              ->getAllBorders()
              ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
        
        // Create writer
        switch ($this->format) {
            case 'xls':
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
                $extension = 'xls';
                $contentType = 'application/vnd.ms-excel';
                break;
                
            case 'csv':
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
                $extension = 'csv';
                $contentType = 'text/csv';
                break;
                
            case 'xlsx':
            default:
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                $extension = 'xlsx';
                $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                break;
        }
        
        // Output
        $filename = $this->filename . '.' . $extension;
        
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer->save('php://output');
        exit;
    }
    
    /**
     * Export as HTML table (Excel will open it)
     */
    private function exportHtmlExcel() {
        $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" 
                       xmlns:x="urn:schemas-microsoft-com:office:excel" 
                       xmlns="http://www.w3.org/TR/REC-html40">
                <head>
                    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
                    <style>
                        table { border-collapse: collapse; }
                        th, td { border: 1px solid #ccc; padding: 8px; }
                        th { background-color: #f0f0f0; font-weight: bold; }
                    </style>
                </head>
                <body>';
        
        // Add title
        if ($this->title) {
            $html .= '<h2>' . htmlspecialchars($this->title) . '</h2>';
        }
        
        // Add metadata
        if (!empty($this->metadata)) {
            $html .= '<table style="margin-bottom: 20px;">';
            foreach ($this->metadata as $key => $value) {
                $html .= '<tr>
                    <td><strong>' . htmlspecialchars($key) . ':</strong></td>
                    <td>' . htmlspecialchars($value) . '</td>
                </tr>';
            }
            $html .= '</table>';
        }
        
        // Add data table
        $html .= '<table>';
        
        // Headers
        $html .= '<thead><tr>';
        foreach ($this->headers as $header) {
            $html .= '<th>' . htmlspecialchars($header) . '</th>';
        }
        $html .= '</tr></thead>';
        
        // Data
        $html .= '<tbody>';
        foreach ($this->data as $row) {
            $html .= '<tr>';
            foreach ($this->headers as $key) {
                $value = $row[$key] ?? '';
                
                // Format numbers
                if (is_numeric($value)) {
                    $html .= '<td style="mso-number-format:General;">' . $value . '</td>';
                } else {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        $html .= '</body></html>';
        
        // Output
        $filename = $this->filename . '.xls';
        
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        echo $html;
        exit;
    }
    
    /**
     * Export as CSV
     */
    public function exportCsv() {
        $filename = $this->filename . '.csv';
        
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        // Add BOM for UTF-8
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Add metadata if exists
        if (!empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                fputcsv($output, [$key, $value]);
            }
            fputcsv($output, []); // Empty row
        }
        
        // Add headers
        fputcsv($output, $this->headers);
        
        // Add data
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($this->headers as $key) {
                $rowData[] = $row[$key] ?? '';
            }
            fputcsv($output, $rowData);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Download file
     */
    public function download() {
        switch ($this->format) {
            case 'csv':
                return $this->exportCsv();
                
            case 'xlsx':
            case 'xls':
            default:
                return $this->exportExcel();
        }
    }
    
    /**
     * Save to file
     */
    public function save($path) {
        // Implementation for saving to file system
        // Similar to download but saves to file instead
        return true;
    }
}