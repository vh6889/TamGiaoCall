<?php
/**
 * Calculator Module
 * Calculate various metrics and statistics
 */

namespace Modules\Statistics\Core;

class Calculator {
    
    /**
     * Calculate basic statistics
     */
    public function calculateBasicStats($values) {
        if (empty($values)) {
            return [
                'count' => 0,
                'sum' => 0,
                'avg' => 0,
                'min' => 0,
                'max' => 0,
                'median' => 0,
                'stddev' => 0
            ];
        }
        
        $count = count($values);
        $sum = array_sum($values);
        $avg = $sum / $count;
        
        return [
            'count' => $count,
            'sum' => $sum,
            'avg' => $avg,
            'min' => min($values),
            'max' => max($values),
            'median' => $this->calculateMedian($values),
            'stddev' => $this->calculateStdDev($values, $avg)
        ];
    }
    
    /**
     * Calculate median
     */
    public function calculateMedian($values) {
        if (empty($values)) return 0;
        
        sort($values);
        $count = count($values);
        $middle = floor($count / 2);
        
        if ($count % 2 == 0) {
            return ($values[$middle - 1] + $values[$middle]) / 2;
        } else {
            return $values[$middle];
        }
    }
    
    /**
     * Calculate standard deviation
     */
    public function calculateStdDev($values, $mean = null) {
        if (empty($values)) return 0;
        
        if ($mean === null) {
            $mean = array_sum($values) / count($values);
        }
        
        $variance = 0;
        foreach ($values as $value) {
            $variance += pow($value - $mean, 2);
        }
        
        $variance /= count($values);
        return sqrt($variance);
    }
    
    /**
     * Calculate percentage change
     */
    public function calculatePercentageChange($oldValue, $newValue) {
        if ($oldValue == 0) {
            return $newValue == 0 ? 0 : 100;
        }
        
        return round(($newValue - $oldValue) * 100 / $oldValue, 2);
    }
    
    /**
     * Calculate growth rate
     */
    public function calculateGrowthRate($values) {
        if (count($values) < 2) return 0;
        
        $first = reset($values);
        $last = end($values);
        $periods = count($values) - 1;
        
        if ($first == 0) return 0;
        
        return round((pow($last / $first, 1 / $periods) - 1) * 100, 2);
    }
    
    /**
     * Calculate moving average
     */
    public function calculateMovingAverage($values, $period = 7) {
        $result = [];
        $count = count($values);
        
        for ($i = 0; $i < $count; $i++) {
            if ($i < $period - 1) {
                // Not enough data points yet
                $result[] = null;
            } else {
                $sum = 0;
                for ($j = 0; $j < $period; $j++) {
                    $sum += $values[$i - $j];
                }
                $result[] = $sum / $period;
            }
        }
        
        return $result;
    }
    
    /**
     * Calculate conversion rate
     */
    public function calculateConversionRate($conversions, $total) {
        if ($total == 0) return 0;
        return round($conversions * 100 / $total, 2);
    }
    
    /**
     * Calculate retention rate
     */
    public function calculateRetentionRate($retained, $initial) {
        if ($initial == 0) return 0;
        return round($retained * 100 / $initial, 2);
    }
    
    /**
     * Calculate churn rate
     */
    public function calculateChurnRate($lost, $initial) {
        if ($initial == 0) return 0;
        return round($lost * 100 / $initial, 2);
    }
    
    /**
     * Calculate percentiles
     */
    public function calculatePercentiles($values, $percentiles = [25, 50, 75, 90, 95]) {
        if (empty($values)) {
            return array_fill_keys($percentiles, 0);
        }
        
        sort($values);
        $count = count($values);
        $result = [];
        
        foreach ($percentiles as $p) {
            $index = ($p / 100) * ($count - 1);
            $lower = floor($index);
            $upper = ceil($index);
            $weight = $index - $lower;
            
            if ($upper >= $count) {
                $result[$p] = $values[$count - 1];
            } else {
                $result[$p] = $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
            }
        }
        
        return $result;
    }
    
    /**
     * Calculate forecast using simple linear regression
     */
    public function calculateForecast($values, $periods = 7) {
        if (count($values) < 2) {
            return array_fill(0, $periods, end($values) ?: 0);
        }
        
        // Prepare data points
        $x = range(0, count($values) - 1);
        $y = array_values($values);
        $n = count($values);
        
        // Calculate regression coefficients
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        // Generate forecast
        $forecast = [];
        for ($i = 0; $i < $periods; $i++) {
            $forecast[] = $intercept + $slope * ($n + $i);
        }
        
        return $forecast;
    }
    
    /**
     * Calculate seasonal index
     */
    public function calculateSeasonalIndex($values, $seasonLength = 7) {
        if (count($values) < $seasonLength) {
            return array_fill(0, $seasonLength, 1);
        }
        
        $seasonTotals = array_fill(0, $seasonLength, 0);
        $seasonCounts = array_fill(0, $seasonLength, 0);
        
        foreach ($values as $i => $value) {
            $seasonIndex = $i % $seasonLength;
            $seasonTotals[$seasonIndex] += $value;
            $seasonCounts[$seasonIndex]++;
        }
        
        $overallAvg = array_sum($values) / count($values);
        $seasonalIndex = [];
        
        for ($i = 0; $i < $seasonLength; $i++) {
            if ($seasonCounts[$i] > 0 && $overallAvg > 0) {
                $seasonAvg = $seasonTotals[$i] / $seasonCounts[$i];
                $seasonalIndex[] = $seasonAvg / $overallAvg;
            } else {
                $seasonalIndex[] = 1;
            }
        }
        
        return $seasonalIndex;
    }
    
    /**
     * Calculate correlation coefficient
     */
    public function calculateCorrelation($x, $y) {
        if (count($x) !== count($y) || count($x) < 2) {
            return 0;
        }
        
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumX2 = 0;
        $sumY2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumX2 += $x[$i] * $x[$i];
            $sumY2 += $y[$i] * $y[$i];
        }
        
        $numerator = $n * $sumXY - $sumX * $sumY;
        $denominator = sqrt(($n * $sumX2 - $sumX * $sumX) * ($n * $sumY2 - $sumY * $sumY));
        
        if ($denominator == 0) return 0;
        
        return $numerator / $denominator;
    }
    
    /**
     * Calculate compound annual growth rate (CAGR)
     */
    public function calculateCAGR($beginValue, $endValue, $years) {
        if ($beginValue <= 0 || $years <= 0) return 0;
        
        return round((pow($endValue / $beginValue, 1 / $years) - 1) * 100, 2);
    }
    
    /**
     * Calculate weighted average
     */
    public function calculateWeightedAverage($values, $weights) {
        if (count($values) !== count($weights) || empty($values)) {
            return 0;
        }
        
        $weightedSum = 0;
        $totalWeight = 0;
        
        for ($i = 0; $i < count($values); $i++) {
            $weightedSum += $values[$i] * $weights[$i];
            $totalWeight += $weights[$i];
        }
        
        if ($totalWeight == 0) return 0;
        
        return $weightedSum / $totalWeight;
    }
    
    /**
     * Calculate quartiles
     */
    public function calculateQuartiles($values) {
        return $this->calculatePercentiles($values, [25, 50, 75]);
    }
    
    /**
     * Calculate mode (most frequent value)
     */
    public function calculateMode($values) {
        if (empty($values)) return null;
        
        $valueCounts = array_count_values($values);
        $maxCount = max($valueCounts);
        
        $modes = [];
        foreach ($valueCounts as $value => $count) {
            if ($count == $maxCount) {
                $modes[] = $value;
            }
        }
        
        return count($modes) == 1 ? $modes[0] : $modes;
    }
    
    /**
     * Calculate range
     */
    public function calculateRange($values) {
        if (empty($values)) return 0;
        return max($values) - min($values);
    }
    
    /**
     * Calculate interquartile range (IQR)
     */
    public function calculateIQR($values) {
        $quartiles = $this->calculateQuartiles($values);
        return $quartiles[75] - $quartiles[25];
    }
    
    /**
     * Detect outliers using IQR method
     */
    public function detectOutliers($values) {
        if (count($values) < 4) return [];
        
        $quartiles = $this->calculateQuartiles($values);
        $iqr = $quartiles[75] - $quartiles[25];
        
        $lowerBound = $quartiles[25] - 1.5 * $iqr;
        $upperBound = $quartiles[75] + 1.5 * $iqr;
        
        $outliers = [];
        foreach ($values as $value) {
            if ($value < $lowerBound || $value > $upperBound) {
                $outliers[] = $value;
            }
        }
        
        return $outliers;
    }
    
    /**
     * Calculate z-scores
     */
    public function calculateZScores($values) {
        if (empty($values)) return [];
        
        $mean = array_sum($values) / count($values);
        $stddev = $this->calculateStdDev($values, $mean);
        
        if ($stddev == 0) return array_fill(0, count($values), 0);
        
        $zScores = [];
        foreach ($values as $value) {
            $zScores[] = ($value - $mean) / $stddev;
        }
        
        return $zScores;
    }
    
    /**
     * Calculate confidence interval
     */
    public function calculateConfidenceInterval($values, $confidence = 0.95) {
        if (empty($values)) return ['lower' => 0, 'upper' => 0];
        
        $mean = array_sum($values) / count($values);
        $stddev = $this->calculateStdDev($values, $mean);
        $n = count($values);
        
        // Z-scores for common confidence levels
        $zScores = [
            0.90 => 1.645,
            0.95 => 1.96,
            0.99 => 2.576
        ];
        
        $z = $zScores[$confidence] ?? 1.96;
        $margin = $z * ($stddev / sqrt($n));
        
        return [
            'lower' => $mean - $margin,
            'upper' => $mean + $margin,
            'mean' => $mean,
            'margin' => $margin
        ];
    }
    
    /**
     * Calculate exponential moving average
     */
    public function calculateEMA($values, $period = 10) {
        if (empty($values)) return [];
        
        $multiplier = 2 / ($period + 1);
        $ema = [];
        
        // Start with simple average for first period
        if (count($values) >= $period) {
            $ema[0] = array_sum(array_slice($values, 0, $period)) / $period;
            
            // Calculate EMA for rest
            for ($i = $period; $i < count($values); $i++) {
                $ema[$i - $period + 1] = ($values[$i] * $multiplier) + ($ema[$i - $period] * (1 - $multiplier));
            }
        } else {
            $ema[0] = array_sum($values) / count($values);
        }
        
        return $ema;
    }
}