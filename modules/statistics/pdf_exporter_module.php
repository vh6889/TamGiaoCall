<?php
/**
 * PDF Exporter Module
 * Export reports to PDF format
 */

namespace Modules\Statistics\Exporters;

class PDFExporter {
    private $data = [];
    private $headers = [];
    private $title = '';
    private $metadata = [];
    private $orientation = 'P'; // P=Portrait, L=Landscape
    private $pageSize = 'A4';
    private $filename = 'export';
    
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
     * Set title
     */
    public function setTitle($title) {
        $this->title = $title;
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
     * Add metadata
     */
    public function addMetadata($key, $value) {
        $this->metadata[$key] = $value;
        return $this;
    }
    
    /**
     * Set orientation
     */
    public function setOrientation($orientation) {
        $this->orientation = $orientation;
        return $this;
    }
    
    /**
     * Set page size
     */
    public function setPageSize($size) {
        $this->pageSize = $size;
        return $this;
    }
    
    /**
     * Export using TCPDF if available
     */
    public function exportPDF() {
        // Check if TCPDF is available
        if (class_exists('TCPDF')) {
            return $this->exportWithTCPDF();
        } else {
            // Fallback to HTML->PDF conversion
            return $this->exportHtmlPdf();
        }
    }
    
    /**
     * Export with TCPDF library
     */
    private function exportWithTCPDF() {
        $pdf = new \TCPDF($this->orientation, 'mm', $this->pageSize, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('Telesale Manager');
        $pdf->SetAuthor($_SESSION['user']['full_name'] ?? 'System');
        $pdf->SetTitle($this->title);
        
        // Set margins
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, 15);
        
        // Set font
        $pdf->SetFont('dejavusans', '', 10);
        
        // Add page
        $pdf->AddPage();
        
        // Title
        if ($this->title) {
            $pdf->SetFont('dejavusans', 'B', 16);
            $pdf->Cell(0, 10, $this->title, 0, 1, 'C');
            $pdf->Ln(5);
        }
        
        // Metadata
        if (!empty($this->metadata)) {
            $pdf->SetFont('dejavusans', '', 9);
            foreach ($this->metadata as $key => $value) {
                $pdf->Cell(40, 6, $key . ':', 0, 0, 'R');
                $pdf->Cell(0, 6, $value, 0, 1);
            }
            $pdf->Ln(5);
        }
        
        // Table
        $this->renderTable($pdf);
        
        // Output
        $filename = $this->filename . '.pdf';
        $pdf->Output($filename, 'D');
        exit;
    }
    
    /**
     * Render table in PDF
     */
    private function renderTable($pdf) {
        $pdf->SetFont('dejavusans', 'B', 9);
        
        // Calculate column widths
        $pageWidth = $pdf->GetPageWidth() - 30; // Minus margins
        $colCount = count($this->headers);
        $colWidth = $pageWidth / $colCount;
        
        // Header
        $pdf->SetFillColor(230, 230, 230);
        foreach ($this->headers as $header) {
            $pdf->Cell($colWidth, 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        // Data rows
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetFillColor(245, 245, 245);
        
        $fill = false;
        foreach ($this->data as $row) {
            foreach ($this->headers as $key) {
                $value = $row[$key] ?? '';
                $pdf->Cell($colWidth, 7, $value, 1, 0, 'L', $fill);
            }
            $pdf->Ln();
            $fill = !$fill;
        }
    }
    
    /**
     * Fallback HTML to PDF
     */
    private function exportHtmlPdf() {
        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>' . htmlspecialchars($this->title) . '</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; }
                h1 { text-align: center; color: #333; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th { background: #f0f0f0; padding: 8px; text-align: left; border: 1px solid #ddd; }
                td { padding: 6px; border: 1px solid #ddd; }
                tr:nth-child(even) { background: #f9f9f9; }
                .metadata { margin: 10px 0; }
                .metadata strong { display: inline-block; width: 150px; }
                @page { margin: 20mm; }
            </style>
        </head>
        <body>';
        
        // Title
        if ($this->title) {
            $html .= '<h1>' . htmlspecialchars($this->title) . '</h1>';
        }
        
        // Metadata
        if (!empty($this->metadata)) {
            $html .= '<div class="metadata">';
            foreach ($this->metadata as $key => $value) {
                $html .= '<div><strong>' . htmlspecialchars($key) . ':</strong> ' . htmlspecialchars($value) . '</div>';
            }
            $html .= '</div>';
        }
        
        // Table
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
                $html .= '<td>' . htmlspecialchars($value) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';
        
        $html .= '</body></html>';
        
        // Use wkhtmltopdf if available
        if ($this->hasWkhtmltopdf()) {
            $this->generateWithWkhtmltopdf($html);
        } else {
            // Output as HTML with PDF headers
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $this->filename . '.pdf"');
            echo $html;
        }
        
        exit;
    }
    
    /**
     * Check if wkhtmltopdf is available
     */
    private function hasWkhtmltopdf() {
        $output = shell_exec('which wkhtmltopdf 2>/dev/null');
        return !empty($output);
    }
    
    /**
     * Generate PDF using wkhtmltopdf
     */
    private function generateWithWkhtmltopdf($html) {
        $tempHtml = tempnam(sys_get_temp_dir(), 'pdf_') . '.html';
        $tempPdf = tempnam(sys_get_temp_dir(), 'pdf_') . '.pdf';
        
        file_put_contents($tempHtml, $html);
        
        $command = "wkhtmltopdf --orientation {$this->orientation} --page-size {$this->pageSize} {$tempHtml} {$tempPdf} 2>&1";
        exec($command);
        
        if (file_exists($tempPdf)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $this->filename . '.pdf"');
            readfile($tempPdf);
            
            unlink($tempHtml);
            unlink($tempPdf);
        }
    }
    
    /**
     * Download PDF
     */
    public function download() {
        return $this->exportPDF();
    }
}