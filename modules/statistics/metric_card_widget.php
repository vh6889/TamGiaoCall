<?php
/**
 * Metric Card Widget
 * Display key metrics with comparison and trends
 */

namespace Modules\Statistics\Widgets;

class MetricCard {
    private $db;
    private $title = '';
    private $value = 0;
    private $previousValue = null;
    private $format = 'number';
    private $icon = 'fa-chart-line';
    private $color = 'primary';
    private $trend = [];
    private $drilldown = null;
    private $tooltip = '';
    private $suffix = '';
    private $prefix = '';
    
    public function __construct($db = null) {
        $this->db = $db;
    }
    
    /**
     * Set card title
     */
    public function setTitle($title) {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Set main value
     */
    public function setValue($value) {
        $this->value = $value;
        return $this;
    }
    
    /**
     * Set comparison value
     */
    public function setCompare($previousValue) {
        $this->previousValue = $previousValue;
        return $this;
    }
    
    /**
     * Set value format (number, money, percent, time)
     */
    public function setFormat($format) {
        $this->format = $format;
        return $this;
    }
    
    /**
     * Set card icon
     */
    public function setIcon($icon) {
        $this->icon = $icon;
        return $this;
    }
    
    /**
     * Set card color theme
     */
    public function setColor($color) {
        $this->color = $color;
        return $this;
    }
    
    /**
     * Set trend data for sparkline
     */
    public function setTrend(array $trend) {
        $this->trend = $trend;
        return $this;
    }
    
    /**
     * Set drilldown parameters
     */
    public function setDrilldown($type, $id, $params = []) {
        $this->drilldown = [
            'type' => $type,
            'id' => $id,
            'params' => $params
        ];
        return $this;
    }
    
    /**
     * Set tooltip text
     */
    public function setTooltip($tooltip) {
        $this->tooltip = $tooltip;
        return $this;
    }
    
    /**
     * Set value prefix/suffix
     */
    public function setPrefix($prefix) {
        $this->prefix = $prefix;
        return $this;
    }
    
    public function setSuffix($suffix) {
        $this->suffix = $suffix;
        return $this;
    }
    
    /**
     * Load data from database
     */
    public function loadFromQuery($sql, $params = []) {
        if (!$this->db) {
            throw new \Exception("Database connection required for loadFromQuery");
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($result) {
            $this->value = $result['value'] ?? 0;
            if (isset($result['previous_value'])) {
                $this->previousValue = $result['previous_value'];
            }
            if (isset($result['trend'])) {
                $this->trend = json_decode($result['trend'], true) ?? [];
            }
        }
        
        return $this;
    }
    
    /**
     * Calculate change percentage
     */
    private function getChangePercent() {
        if ($this->previousValue === null || $this->previousValue == 0) {
            return null;
        }
        
        return round(($this->value - $this->previousValue) * 100 / $this->previousValue, 1);
    }
    
    /**
     * Format value based on type
     */
    private function formatValue($value) {
        switch ($this->format) {
            case 'money':
                return number_format($value, 0, ',', '.') . ' đ';
                
            case 'percent':
                return number_format($value, 1, ',', '.') . '%';
                
            case 'time':
                if ($value < 60) {
                    return $value . ' giây';
                } elseif ($value < 3600) {
                    return round($value / 60, 1) . ' phút';
                } elseif ($value < 86400) {
                    return round($value / 3600, 1) . ' giờ';
                } else {
                    return round($value / 86400, 1) . ' ngày';
                }
                
            case 'decimal':
                return number_format($value, 2, ',', '.');
                
            case 'number':
            default:
                return number_format($value, 0, ',', '.');
        }
    }
    
    /**
     * Get sparkline HTML
     */
    private function getSparklineHtml() {
        if (empty($this->trend)) {
            return '';
        }
        
        $trendData = json_encode(array_values($this->trend));
        $trendId = 'trend_' . uniqid();
        
        return "
        <canvas id='{$trendId}' width='100' height='30' style='width: 100px; height: 30px;'></canvas>
        <script>
        (function() {
            const canvas = document.getElementById('{$trendId}');
            const ctx = canvas.getContext('2d');
            const data = {$trendData};
            const max = Math.max(...data);
            const min = Math.min(...data);
            const range = max - min || 1;
            const width = canvas.width;
            const height = canvas.height;
            const stepX = width / (data.length - 1);
            
            ctx.strokeStyle = '#667eea';
            ctx.lineWidth = 2;
            ctx.beginPath();
            
            data.forEach((value, index) => {
                const x = index * stepX;
                const y = height - ((value - min) / range) * height;
                if (index === 0) {
                    ctx.moveTo(x, y);
                } else {
                    ctx.lineTo(x, y);
                }
            });
            
            ctx.stroke();
        })();
        </script>
        ";
    }
    
    /**
     * Render the metric card HTML
     */
    public function render() {
        $changePercent = $this->getChangePercent();
        $changeClass = '';
        $changeIcon = '';
        $changeText = '';
        
        if ($changePercent !== null) {
            if ($changePercent > 0) {
                $changeClass = 'text-success';
                $changeIcon = 'fa-arrow-up';
                $changeText = '+' . $changePercent . '%';
            } elseif ($changePercent < 0) {
                $changeClass = 'text-danger';
                $changeIcon = 'fa-arrow-down';
                $changeText = $changePercent . '%';
            } else {
                $changeClass = 'text-muted';
                $changeIcon = 'fa-minus';
                $changeText = '0%';
            }
        }
        
        $drilldownAttrs = '';
        if ($this->drilldown) {
            $drilldownAttrs = sprintf(
                'data-drilldown-type="%s" data-drilldown-id="%s" data-drilldown-params="%s" style="cursor: pointer;"',
                htmlspecialchars($this->drilldown['type']),
                htmlspecialchars($this->drilldown['id']),
                htmlspecialchars(json_encode($this->drilldown['params']))
            );
        }
        
        $tooltipAttr = $this->tooltip ? 'data-bs-toggle="tooltip" title="' . htmlspecialchars($this->tooltip) . '"' : '';
        
        $html = "
        <div class='metric-card card border-0 shadow-sm h-100' {$drilldownAttrs} {$tooltipAttr}>
            <div class='card-body'>
                <div class='d-flex justify-content-between align-items-start mb-2'>
                    <div class='metric-icon bg-{$this->color} bg-opacity-10 p-2 rounded'>
                        <i class='fas {$this->icon} text-{$this->color}'></i>
                    </div>
                    ";
        
        if (!empty($this->trend)) {
            $html .= "<div class='metric-trend'>" . $this->getSparklineHtml() . "</div>";
        }
        
        $html .= "
                </div>
                <h6 class='text-muted mb-2'>{$this->title}</h6>
                <div class='d-flex align-items-baseline'>
                    <h3 class='mb-0'>
                        {$this->prefix}
                        <span class='metric-value'>" . $this->formatValue($this->value) . "</span>
                        {$this->suffix}
                    </h3>
                    ";
        
        if ($changeText) {
            $html .= "
                    <span class='ms-2 {$changeClass} small'>
                        <i class='fas {$changeIcon}'></i> {$changeText}
                    </span>
            ";
        }
        
        $html .= "
                </div>
                ";
        
        if ($this->previousValue !== null) {
            $html .= "
                <small class='text-muted'>
                    So với kỳ trước: " . $this->formatValue($this->previousValue) . "
                </small>
            ";
        }
        
        $html .= "
            </div>
        </div>
        ";
        
        return $html;
    }
    
    /**
     * Get data array for API response
     */
    public function toArray() {
        return [
            'title' => $this->title,
            'value' => $this->value,
            'formatted_value' => $this->formatValue($this->value),
            'previous_value' => $this->previousValue,
            'change_percent' => $this->getChangePercent(),
            'format' => $this->format,
            'icon' => $this->icon,
            'color' => $this->color,
            'trend' => $this->trend,
            'drilldown' => $this->drilldown
        ];
    }
}