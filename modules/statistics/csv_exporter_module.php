<?php
/**
 * CSV Exporter Module - FIXED VERSION
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
    private $metadata = [];
    private $title = '';
    
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
     * Set title
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
     * Format value for CSV
     */
    private function formatValue($value) {
        // Handle arrays
        if (is_array($value)) {
            return json_encode($value);
        }
        
        // Handle booleans
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        
        // Handle large numbers to prevent Excel scientific notation
        if (is_numeric($value) && strlen((string)$value) > 15) {
            return '="' . $value . '"';
        }
        
        // Handle null
        if ($value === null) {
            return '';
        }
        
        return $value;
    }
    
    /**
     * Export to CSV and download
     */
    public function export() {
        $filename = $this->filename . '.csv';
        
        // Set headers for download
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
        
        // Write title if set
        if (!empty($this->title)) {
            fputcsv($output, [$this->title], $this->delimiter, $this->enclosure, $this->escape);
            fputcsv($output, [], $this->delimiter, $this->enclosure, $this->escape); // Empty row
        }
        
        // Write metadata if exists
        if (!empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                fputcsv($output, [$key, $value], $this->delimiter, $this->enclosure, $this->escape);
            }
            fputcsv($output, [], $this->delimiter, $this->enclosure, $this->escape); // Empty row
        }
        
        // Write headers
        fputcsv($output, $this->headers, $this->delimiter, $this->enclosure, $this->escape);
        
        // Write data
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($this->headers as $key) {
                $value = $row[$key] ?? '';
                $rowData[] = $this->formatValue($value);
            }
            fputcsv($output, $rowData, $this->delimiter, $this->enclosure, $this->escape);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Download CSV (alias for export)
     */
    public function download() {
        return $this->export();
    }
    
    /**
     * Save to file
     */
    public function save($filepath) {
        $file = fopen($filepath, 'w');
        
        if (!$file) {
            throw new \Exception("Cannot open file for writing: $filepath");
        }
        
        // Add BOM for UTF-8
        if ($this->encoding === 'UTF-8') {
            fprintf($file, "\xEF\xBB\xBF");
        }
        
        // Write title if set
        if (!empty($this->title)) {
            fputcsv($file, [$this->title], $this->delimiter, $this->enclosure, $this->escape);
            fputcsv($file, [], $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Write metadata if exists
        if (!empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                fputcsv($file, [$key, $value], $this->delimiter, $this->enclosure, $this->escape);
            }
            fputcsv($file, [], $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Write headers
        fputcsv($file, $this->headers, $this->delimiter, $this->enclosure, $this->escape);
        
        // Write data
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($this->headers as $key) {
                $value = $row[$key] ?? '';
                $rowData[] = $this->formatValue($value);
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
        
        if (!$output) {
            throw new \Exception("Cannot create memory stream");
        }
        
        // Add BOM for UTF-8
        if ($this->encoding === 'UTF-8') {
            fprintf($output, "\xEF\xBB\xBF");
        }
        
        // Write title if set
        if (!empty($this->title)) {
            fputcsv($output, [$this->title], $this->delimiter, $this->enclosure, $this->escape);
            fputcsv($output, [], $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Write metadata if exists
        if (!empty($this->metadata)) {
            foreach ($this->metadata as $key => $value) {
                fputcsv($output, [$key, $value], $this->delimiter, $this->enclosure, $this->escape);
            }
            fputcsv($output, [], $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Write headers
        fputcsv($output, $this->headers, $this->delimiter, $this->enclosure, $this->escape);
        
        // Write data
        foreach ($this->data as $row) {
            $rowData = [];
            foreach ($this->headers as $key) {
                $value = $row[$key] ?? '';
                $rowData[] = $this->formatValue($value);
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
     * Set format (for compatibility with other exporters)
     */
    public function setFormat($format) {
        // CSV only has one format, so this is just for interface compatibility
        return $this;
    }
}