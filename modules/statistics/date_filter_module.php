<?php
/**
 * Date Filter Module
 * Handle date range filtering and date-related queries
 */

namespace Modules\Statistics\Filters;

class DateFilter {
    private $dateField = 'created_at';
    private $startDate = null;
    private $endDate = null;
    private $presetRange = null;
    private $timezone = 'Asia/Ho_Chi_Minh';
    
    /**
     * Set date field to filter on
     */
    public function setDateField($field) {
        $this->dateField = $field;
        return $this;
    }
    
    /**
     * Set custom date range
     */
    public function setRange($start, $end) {
        $this->startDate = $this->normalizeDate($start);
        $this->endDate = $this->normalizeDate($end, true);
        $this->presetRange = null;
        return $this;
    }
    
    /**
     * Use preset range
     */
    public function setPreset($preset) {
        $this->presetRange = $preset;
        $this->applyPreset();
        return $this;
    }
    
    /**
     * Apply preset date range
     */
    private function applyPreset() {
        $now = new \DateTime('now', new \DateTimeZone($this->timezone));
        $start = clone $now;
        $end = clone $now;
        
        switch ($this->presetRange) {
            case 'today':
                $start->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
                
            case 'yesterday':
                $start->modify('-1 day')->setTime(0, 0, 0);
                $end->modify('-1 day')->setTime(23, 59, 59);
                break;
                
            case 'this_week':
                $start->modify('monday this week')->setTime(0, 0, 0);
                $end->modify('sunday this week')->setTime(23, 59, 59);
                break;
                
            case 'last_week':
                $start->modify('monday last week')->setTime(0, 0, 0);
                $end->modify('sunday last week')->setTime(23, 59, 59);
                break;
                
            case 'this_month':
                $start->modify('first day of this month')->setTime(0, 0, 0);
                $end->modify('last day of this month')->setTime(23, 59, 59);
                break;
                
            case 'last_month':
                $start->modify('first day of last month')->setTime(0, 0, 0);
                $end->modify('last day of last month')->setTime(23, 59, 59);
                break;
                
            case 'this_quarter':
                $quarter = ceil($now->format('n') / 3);
                $start->setDate($now->format('Y'), ($quarter - 1) * 3 + 1, 1)->setTime(0, 0, 0);
                $end->setDate($now->format('Y'), $quarter * 3, 1)->modify('last day of this month')->setTime(23, 59, 59);
                break;
                
            case 'last_quarter':
                $quarter = ceil($now->format('n') / 3) - 1;
                if ($quarter < 1) {
                    $quarter = 4;
                    $year = $now->format('Y') - 1;
                } else {
                    $year = $now->format('Y');
                }
                $start->setDate($year, ($quarter - 1) * 3 + 1, 1)->setTime(0, 0, 0);
                $end->setDate($year, $quarter * 3, 1)->modify('last day of this month')->setTime(23, 59, 59);
                break;
                
            case 'this_year':
                $start->modify('first day of January')->setTime(0, 0, 0);
                $end->modify('last day of December')->setTime(23, 59, 59);
                break;
                
            case 'last_year':
                $start->modify('-1 year')->modify('first day of January')->setTime(0, 0, 0);
                $end->modify('-1 year')->modify('last day of December')->setTime(23, 59, 59);
                break;
                
            case 'last_7_days':
                $start->modify('-6 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
                
            case 'last_30_days':
                $start->modify('-29 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
                
            case 'last_90_days':
                $start->modify('-89 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
                
            case 'last_365_days':
                $start->modify('-364 days')->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
                
            default:
                // Default to today
                $start->setTime(0, 0, 0);
                $end->setTime(23, 59, 59);
                break;
        }
        
        $this->startDate = $start->format('Y-m-d H:i:s');
        $this->endDate = $end->format('Y-m-d H:i:s');
    }
    
    /**
     * Normalize date format
     */
    private function normalizeDate($date, $endOfDay = false) {
        if ($date instanceof \DateTime) {
            return $date->format('Y-m-d H:i:s');
        }
        
        // If date only (no time), add time
        if (strlen($date) == 10) {
            $date .= $endOfDay ? ' 23:59:59' : ' 00:00:00';
        }
        
        return $date;
    }
    
    /**
     * Get SQL WHERE clause
     */
    public function getWhereClause(&$params = []) {
        if (!$this->startDate || !$this->endDate) {
            return '1=1';
        }
        
        $params[] = $this->startDate;
        $params[] = $this->endDate;
        
        return "{$this->dateField} BETWEEN ? AND ?";
    }
    
    /**
     * Get date range as array
     */
    public function getRange() {
        return [
            'start' => $this->startDate,
            'end' => $this->endDate,
            'preset' => $this->presetRange
        ];
    }
    
    /**
     * Get comparison range (previous period)
     */
    public function getComparisonRange() {
        if (!$this->startDate || !$this->endDate) {
            return null;
        }
        
        $start = new \DateTime($this->startDate);
        $end = new \DateTime($this->endDate);
        $diff = $start->diff($end);
        
        $compareStart = clone $start;
        $compareEnd = clone $end;
        
        // Move back by the same period
        $compareStart->sub($diff);
        $compareEnd->sub($diff);
        
        return [
            'start' => $compareStart->format('Y-m-d H:i:s'),
            'end' => $compareEnd->format('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get preset options
     */
    public static function getPresetOptions() {
        return [
            'today' => 'Hôm nay',
            'yesterday' => 'Hôm qua',
            'this_week' => 'Tuần này',
            'last_week' => 'Tuần trước',
            'this_month' => 'Tháng này',
            'last_month' => 'Tháng trước',
            'this_quarter' => 'Quý này',
            'last_quarter' => 'Quý trước',
            'this_year' => 'Năm nay',
            'last_year' => 'Năm trước',
            'last_7_days' => '7 ngày qua',
            'last_30_days' => '30 ngày qua',
            'last_90_days' => '90 ngày qua',
            'last_365_days' => '365 ngày qua'
        ];
    }
    
    /**
     * Parse date string to components
     */
    public function parseToComponents($format = 'Y-m-d') {
        if (!$this->startDate || !$this->endDate) {
            return [];
        }
        
        $start = new \DateTime($this->startDate);
        $end = new \DateTime($this->endDate);
        $components = [];
        
        switch ($format) {
            case 'Y-m-d':
                // Daily components
                $period = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));
                foreach ($period as $date) {
                    $components[] = $date->format('Y-m-d');
                }
                break;
                
            case 'Y-W':
                // Weekly components
                $period = new \DatePeriod($start, new \DateInterval('P1W'), $end);
                foreach ($period as $date) {
                    $components[] = $date->format('Y-W');
                }
                break;
                
            case 'Y-m':
                // Monthly components
                $period = new \DatePeriod($start, new \DateInterval('P1M'), $end);
                foreach ($period as $date) {
                    $components[] = $date->format('Y-m');
                }
                break;
        }
        
        return $components;
    }
    
    /**
     * Check if date is within range
     */
    public function isWithinRange($date) {
        if (!$this->startDate || !$this->endDate) {
            return true;
        }
        
        $checkDate = new \DateTime($date);
        $start = new \DateTime($this->startDate);
        $end = new \DateTime($this->endDate);
        
        return $checkDate >= $start && $checkDate <= $end;
    }
}