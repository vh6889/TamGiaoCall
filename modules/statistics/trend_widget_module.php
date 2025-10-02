<?php
/**
 * Trend Widget Module
 * Display trend indicators and sparklines
 */

namespace Modules\Statistics\Widgets;

class TrendWidget {
    private $db;
    private $widgetId;
    private $title = '';
    private $data = [];
    private $type = 'line'; // line, bar, area
    private $color = '#667eea';
    private $height = 60;
    private $showValue = true;
    private $showChange = true;
    private $period = 'daily';
    
    public function __construct($db = null) {
        $this->db = $db;
        $this->widgetId = 'trend_' . uniqid();
    }
    
    /**
     * Set widget title
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set trend data
     */
    public function setData(array $data) {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Load trend from query
     */
    public function loadFromQuery($sql, $params = [], $valueField = 'value', $labelField = 'date') {
        if (!$this->db) {
            throw new \Exception("Database connection required");
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $this->data = [];
        foreach ($results as $row) {
            $this->data[$row[$labelField] ?? ''] = $row[$valueField] ?? 0;
        }
        
        return $this;
    }
    
    /**
     * Set visualization type
     */
    public function setType($type) {
        $this->type = $type;
        return $this;
    }
    
    /**
     * Set color
     */
    public function setColor($color) {
        $this->color = $color;
        return $this;
    }
    
    /**
     * Set height
     */
    public function setHeight($height) {
        $this->height = $height;
        return $this;
    }
    
    /**
     * Set period for comparison
     */
    public function setPeriod($period) {
        $this->period = $period;
        return $this;
    }
    
    /**
     * Calculate trend metrics
     */
    private function calculateMetrics() {
        if (empty($this->data)) {
            return [
                'current' => 0,
                'previous' => 0,
                'change' => 0,
                'change_percent' => 0,
                'trend' => 'flat',
                'min' => 0,
                'max' => 0,
                'avg' => 0
            ];
        }
        
        $values = array_values($this->data);
        $count = count($values);
        
        // Current and previous values
        $current = end($values);
        $previous = $count > 1 ? $values[$count - 2] : $current;
        
        // Change calculation
        $change = $current - $previous;
        $changePercent = $previous != 0 ? round(($change / $previous) * 100, 1) : 0;
        
        // Trend direction
        if ($changePercent > 5) {
            $trend = 'up';
        } elseif ($changePercent < -5) {
            $trend = 'down';
        } else {
            $trend = 'flat';
        }
        
        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $change,
            'change_percent' => $changePercent,
            'trend' => $trend,
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / $count
        ];
    }
    
    /**
     * Generate sparkline SVG
     */
    private function generateSparkline() {
        if (empty($this->data)) {
            return '';
        }
        
        $values = array_values($this->data);
        $count = count($values);
        
        if ($count < 2) {
            return '';
        }
        
        // Calculate dimensions
        $width = 200;
        $height = $this->height;
        $padding = 5;
        
        // Normalize values
        $min = min($values);
        $max = max($values);
        $range = $max - $min ?: 1;
        
        // Calculate points
        $points = [];
        $stepX = ($width - 2 * $padding) / ($count - 1);
        
        for ($i = 0; $i < $count; $i++) {
            $x = $padding + $i * $stepX;
            $y = $height - $padding - (($values[$i] - $min) / $range) * ($height - 2 * $padding);
            $points[] = "$x,$y";
        }
        
        $svg = '<svg width="' . $width . '" height="' . $height . '" class="trend-sparkline">';
        
        if ($this->type === 'area') {
            // Area chart
            $areaPoints = $points;
            $areaPoints[] = ($width - $padding) . ',' . ($height - $padding);
            $areaPoints[] = $padding . ',' . ($height - $padding);
            
            $svg .= '<polygon points="' . implode(' ', $areaPoints) . '" 
                     fill="' . $this->color . '" opacity="0.2"/>';
        }
        
        if ($this->type === 'bar') {
            // Bar chart
            $barWidth = $stepX * 0.6;
            for ($i = 0; $i < $count; $i++) {
                $x = $padding + $i * $stepX - $barWidth / 2;
                $y = $height - $padding - (($values[$i] - $min) / $range) * ($height - 2 * $padding);
                $barHeight = $height - $padding - $y;
                
                $svg .= '<rect x="' . $x . '" y="' . $y . '" 
                         width="' . $barWidth . '" height="' . $barHeight . '" 
                         fill="' . $this->color . '" opacity="0.7"/>';
            }
        } else {
            // Line chart
            $svg .= '<polyline points="' . implode(' ', $points) . '" 
                     stroke="' . $this->color . '" stroke-width="2" fill="none"/>';
            
            // Add dots for last few points
            $recentPoints = array_slice($points, -3);
            foreach ($recentPoints as $i => $point) {
                list($x, $y) = explode(',', $point);
                $radius = $i === count($recentPoints) - 1 ? 3 : 2;
                $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $radius . '" 
                         fill="' . $this->color . '"/>';
            }
        }
        
        $svg .= '</svg>';
        
        return $svg;
    }
    
    /**
     * Render the trend widget
     */
    public function render() {
        $metrics = $this->calculateMetrics();
        $sparkline = $this->generateSparkline();
        
        // Determine trend icon and color
        $trendIcon = 'fa-minus';
        $trendClass = 'text-muted';
        
        if ($metrics['trend'] === 'up') {
            $trendIcon = 'fa-arrow-up';
            $trendClass = 'text-success';
        } elseif ($metrics['trend'] === 'down') {
            $trendIcon = 'fa-arrow-down';
            $trendClass = 'text-danger';
        }
        
        $html = '
        <div class="trend-widget" id="' . $this->widgetId . '">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h6 class="text-muted mb-1">' . htmlspecialchars($this->title) . '</h6>';
        
        if ($this->showValue) {
            $html .= '
                    <h4 class="mb-0">' . number_format($metrics['current'], 0, ',', '.') . '</h4>';
        }
        
        if ($this->showChange) {
            $changeText = $metrics['change'] > 0 ? '+' . number_format($metrics['change'], 0, ',', '.') : number_format($metrics['change'], 0, ',', '.');
            $html .= '
                    <small class="' . $trendClass . '">
                        <i class="fas ' . $trendIcon . '"></i> 
                        ' . $changeText . ' (' . $metrics['change_percent'] . '%)
                    </small>';
        }
        
        $html .= '
                </div>
                <div class="trend-chart">
                    ' . $sparkline . '
                </div>
            </div>';
        
        // Add period selector if needed
        if (count($this->data) > 7) {
            $html .= '
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-outline-secondary" onclick="updateTrendPeriod(\'' . $this->widgetId . '\', \'7d\')">7D</button>
                <button type="button" class="btn btn-outline-secondary" onclick="updateTrendPeriod(\'' . $this->widgetId . '\', \'30d\')">30D</button>
                <button type="button" class="btn btn-outline-secondary" onclick="updateTrendPeriod(\'' . $this->widgetId . '\', \'90d\')">90D</button>
            </div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render multiple trends in grid
     */
    public function renderGrid(array $trends, $columns = 4) {
        $html = '<div class="row g-3">';
        
        foreach ($trends as $trend) {
            $colClass = 'col-md-' . (12 / $columns);
            
            $widget = new self($this->db);
            $widget->setTitle($trend['title'])
                   ->setData($trend['data'])
                   ->setColor($trend['color'] ?? $this->color);
            
            $html .= '<div class="' . $colClass . '">';
            $html .= '<div class="card h-100"><div class="card-body">';
            $html .= $widget->render();
            $html .= '</div></div>';
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Get data array for API
     */
    public function toArray() {
        $metrics = $this->calculateMetrics();
        
        return [
            'title' => $this->title,
            'data' => $this->data,
            'metrics' => $metrics,
            'type' => $this->type,
            'period' => $this->period
        ];
    }
}