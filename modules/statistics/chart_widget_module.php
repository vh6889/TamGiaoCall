<?php
/**
 * Chart Widget Module
 * Generate various chart types with Chart.js
 */

namespace Modules\Statistics\Widgets;

class ChartWidget {
    private $db;
    private $chartId;
    private $type = 'line'; // line, bar, pie, doughnut, area, radar
    private $title = '';
    private $data = [];
    private $options = [];
    private $height = 300;
    private $responsive = true;
    private $colors = [];
    
    // Default color palette
    private $defaultColors = [
        '#667eea', '#f56565', '#48bb78', '#ed8936', '#9f7aea',
        '#38b2ac', '#f6ad55', '#fc8181', '#63b3ed', '#b794f4'
    ];
    
    public function __construct($db = null) {
        $this->db = $db;
        $this->chartId = 'chart_' . uniqid();
    }
    
    /**
     * Set chart type
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }
    
    /**
     * Set chart title
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set chart data
     */
    public function setData($labels, $datasets) {
        $this->data = [
            'labels' => $labels,
            'datasets' => $this->formatDatasets($datasets)
        ];
        return $this;
    }
    
    /**
     * Load data from query
     */
    public function loadFromQuery($sql, $params = [], $labelField = 'label', $valueField = 'value') {
        if (!$this->db) {
            throw new \Exception("Database connection required");
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $labels = [];
        $values = [];
        
        foreach ($results as $row) {
            $labels[] = $row[$labelField];
            $values[] = $row[$valueField];
        }
        
        $this->setData($labels, [
            [
                'label' => $this->title,
                'data' => $values
            ]
        ]);
        
        return $this;
    }
    
    /**
     * Set chart options
     */
    public function setOptions(array $options) {
        $this->options = array_merge($this->options, $options);
        return $this;
    }
    
    /**
     * Set chart height
     */
    public function setHeight($height) {
        $this->height = $height;
        return $this;
    }
    
    /**
     * Set custom colors
     */
    public function setColors(array $colors) {
        $this->colors = $colors;
        return $this;
    }
    
    /**
     * Format datasets with colors and styling
     */
    private function formatDatasets($datasets) {
        $formatted = [];
        
        foreach ($datasets as $index => $dataset) {
            $color = $this->colors[$index] ?? $this->defaultColors[$index % count($this->defaultColors)];
            
            $formatted[] = array_merge([
                'backgroundColor' => $this->type === 'line' ? $this->hexToRgba($color, 0.1) : $color,
                'borderColor' => $color,
                'borderWidth' => 2,
                'pointBackgroundColor' => $color,
                'pointBorderColor' => '#fff',
                'pointHoverBackgroundColor' => '#fff',
                'pointHoverBorderColor' => $color,
                'tension' => 0.3,
                'fill' => $this->type === 'line'
            ], $dataset);
        }
        
        return $formatted;
    }
    
    /**
     * Convert hex to rgba
     */
    private function hexToRgba($hex, $alpha = 1) {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return "rgba($r, $g, $b, $alpha)";
    }
    
    /**
     * Get default options based on chart type
     */
    private function getDefaultOptions() {
        $options = [
            'responsive' => $this->responsive,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 15,
                        'font' => ['size' => 12]
                    ]
                ],
                'title' => [
                    'display' => !empty($this->title),
                    'text' => $this->title,
                    'font' => ['size' => 16]
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                    'callbacks' => []
                ]
            ]
        ];
        
        // Type-specific options
        switch ($this->type) {
            case 'line':
            case 'area':
                $options['scales'] = [
                    'y' => [
                        'beginAtZero' => true,
                        'grid' => ['display' => true, 'color' => 'rgba(0, 0, 0, 0.05)']
                    ],
                    'x' => [
                        'grid' => ['display' => false]
                    ]
                ];
                break;
                
            case 'bar':
                $options['scales'] = [
                    'y' => [
                        'beginAtZero' => true,
                        'grid' => ['display' => true, 'color' => 'rgba(0, 0, 0, 0.05)']
                    ],
                    'x' => [
                        'grid' => ['display' => false]
                    ]
                ];
                $options['plugins']['legend']['display'] = count($this->data['datasets'] ?? []) > 1;
                break;
                
            case 'pie':
            case 'doughnut':
                $options['plugins']['legend']['position'] = 'right';
                break;
                
            case 'radar':
                $options['scales'] = [
                    'r' => [
                        'beginAtZero' => true,
                        'grid' => ['circular' => true]
                    ]
                ];
                break;
        }
        
        return array_replace_recursive($options, $this->options);
    }
    
    /**
     * Render the chart HTML
     */
    public function render() {
        $options = $this->getDefaultOptions();
        $jsonData = json_encode($this->data);
        $jsonOptions = json_encode($options);
        
        $html = "
        <div class='chart-container' style='position: relative; height: {$this->height}px;'>
            <canvas id='{$this->chartId}'></canvas>
        </div>
        <script>
        (function() {
            const ctx = document.getElementById('{$this->chartId}').getContext('2d');
            new Chart(ctx, {
                type: '{$this->type}',
                data: {$jsonData},
                options: {$jsonOptions}
            });
        })();
        </script>
        ";
        
        return $html;
    }
    
    /**
     * Render comparison chart (multiple datasets)
     */
    public function renderComparison($periods) {
        $labels = [];
        $datasets = [];
        
        foreach ($periods as $period => $data) {
            if (empty($labels)) {
                $labels = array_keys($data);
            }
            
            $datasets[] = [
                'label' => $period,
                'data' => array_values($data)
            ];
        }
        
        $this->setData($labels, $datasets);
        return $this->render();
    }
    
    /**
     * Render stacked chart
     */
    public function renderStacked($data, $stacks) {
        $labels = array_keys($data);
        $datasets = [];
        
        foreach ($stacks as $stack) {
            $values = [];
            foreach ($data as $item) {
                $values[] = $item[$stack['field']] ?? 0;
            }
            
            $datasets[] = [
                'label' => $stack['label'],
                'data' => $values,
                'stack' => 'stack1'
            ];
        }
        
        $this->setData($labels, $datasets);
        $this->setOptions([
            'scales' => [
                'y' => ['stacked' => true],
                'x' => ['stacked' => true]
            ]
        ]);
        
        return $this->render();
    }
}