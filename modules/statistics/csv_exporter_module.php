<?php
/**
 * CSV Exporter Module
 * Export data to CSV format
 */

namespace Modules\Statistics\Exporters;

class CSVExporter {
    private $data = [];
    private $headers = [];
    private $filename = 'export';
    private $delimiter = ',';
    private $enclosure = '"';
    private $escape = '\\';
    private $encoding = 'UTF-8';
    
    /**
     * Set data to export
     */
    public function setData(array $data) {
        $this->data = $data;
        
        // Auto-detect headers
        if (empty($this->headers) && !empty($data)) {
            $this->headers = array_keys(reset($data));
        }
        
        return $this;
    }
    
    /**
     * Set headers
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
     * Set delimiter
     */
    public function setDelimiter($delimiter) {
        $this->delimiter = $delimiter;
        return $this;
    }
    
    /**
     * Set encoding
     */
    public function setEncoding($encoding) {
        $this->encoding = $encoding;
        return $this;
    }
    
    /**
     * Export to CSV
     */
    public function export() {
        $filename = $this->filename . '.csv';
        
        // Set headers
        header('Content-Type: text/csv; charset=' . $this->encoding);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Open output stream
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8 (helps Excel recognize UTF-8)
        if ($this->encoding === 'UTF-8') {
            fprintf($output, "\xEF\xBB\xBF");
        }
        
        // Write headers
        fputcsv($output, $this->headers, $this->delimiter, $this->enclosure, $this->escape);
        
        // Write data
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($this->headers as $key) {
                $rowData[] = $row[$key] ?? '';
            }
            fputcsv($output, $rowData, $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Get content
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        
        return $content;
    }
    
    /**
     * Download CSV
     */
    public function download() {
        return $this->export();
    }
}
            foreach ($this->headers as $key) {
                $value = $row[$key] ?? '';
                
                // Format value
                if (is_array($value)) {
                    $value = json_encode($value);
                } elseif (is_bool($value)) {
                    $value = $value ? 'Yes' : 'No';
                } elseif (is_numeric($value) && strlen($value) > 15) {
                    // Prevent Excel from converting large numbers to scientific notation
                    $value = '="' . $value . '"';
                }
                
                $rowData[] = $value;
            }
            
            fputcsv($output, $rowData, $this->delimiter, $this->enclosure, $this->escape);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Save to file
     */
    public function save($filepath) {
        $file = fopen($filepath, 'w');
        
        // Add BOM for UTF-8
        if ($this->encoding === 'UTF-8') {
            fprintf($file, "\xEF\xBB\xBF");
        }
        
        // Write headers
        fputcsv($file, $this->headers, $this->delimiter, $this->enclosure, $this->escape);
        
        // Write data
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($this->headers as $key) {
                $rowData[] = $row[$key] ?? '';
            }
            fputcsv($file, $rowData, $this->delimiter, $this->enclosure, $this->escape);
        }
        
        fclose($file);
        
        return true;
    }
    
    /**
     * Get CSV content as string
     */
    public function getContent() {
        $output = fopen('php://memory', 'w+');
        
        // Add BOM for UTF-8
        if ($this->encoding === 'UTF-8') {
            fprintf($output, "\xEF\xBB\xBF");
        }
        
        // Write headers
        fputcsv($output, $this->headers, $this->delimiter, $this->enclosure, $this->escape);
        
        // Write data
        foreach ($this->data as $row) {
            $rowData = [];
            