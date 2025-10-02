<?php
/**
 * Table Widget Module
 * Generate responsive data tables with sorting and actions
 */

namespace Modules\Statistics\Widgets;

class TableWidget {
    private $db;
    private $tableId;
    private $columns = [];
    private $data = [];
    private $actions = [];
    private $options = [];
    private $footer = false;
    private $sortable = true;
    private $searchable = false;
    private $pagination = false;
    
    public function __construct($db = null) {
        $this->db = $db;
        $this->tableId = 'table_' . uniqid();
    }
    
    /**
     * Set table columns
     */
    public function setColumns(array $columns) {
        $this->columns = $columns;
        return $this;
    }
    
    /**
     * Add single column
     */
    public function addColumn($key, $label, $options = []) {
        $this->columns[] = array_merge([
            'key' => $key,
            'label' => $label
        ], $options);
        return $this;
    }
    
    /**
     * Set table data
     */
    public function setData(array $data) {
        $this->data = $data;
        return $this;
    }
    
    /**
     * Load data from query
     */
    public function loadFromQuery($sql, $params = []) {
        if (!$this->db) {
            throw new \Exception("Database connection required");
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $this->data = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Auto-generate columns if not set
        if (empty($this->columns) && !empty($this->data)) {
            foreach (array_keys($this->data[0]) as $key) {
                $this->columns[] = [
                    'key' => $key,
                    'label' => ucfirst(str_replace('_', ' ', $key))
                ];
            }
        }
        
        return $this;
    }
    
    /**
     * Add row actions
     */
    public function addAction($label, $icon, $callback, $class = 'btn-sm btn-primary') {
        $this->actions[] = [
            'label' => $label,
            'icon' => $icon,
            'callback' => $callback,
            'class' => $class
        ];
        return $this;
    }
    
    /**
     * Set table options
     */
    public function setOptions(array $options) {
        $this->options = array_merge($this->options, $options);
        return $this;
    }
    
    /**
     * Enable features
     */
    public function enableSorting($enable = true) {
        $this->sortable = $enable;
        return $this;
    }
    
    public function enableSearch($enable = true) {
        $this->searchable = $enable;
        return $this;
    }
    
    public function enablePagination($enable = true, $perPage = 10) {
        $this->pagination = $enable ? $perPage : false;
        return $this;
    }
    
    public function showFooter($show = true) {
        $this->footer = $show;
        return $this;
    }
    
    /**
     * Render the table HTML
     */
    public function render() {
        $html = '<div class="table-widget-container">';
        
        // Add search box if enabled
        if ($this->searchable) {
            $html .= $this->renderSearch();
        }
        
        // Start table
        $html .= '<div class="table-responsive">';
        $html .= '<table id="' . $this->tableId . '" class="table table-hover ' . ($this->options['class'] ?? '') . '">';
        
        // Render header
        $html .= $this->renderHeader();
        
        // Render body
        $html .= $this->renderBody();
        
        // Render footer if enabled
        if ($this->footer) {
            $html .= $this->renderFooter();
        }
        
        $html .= '</table>';
        $html .= '</div>';
        
        // Add pagination if enabled
        if ($this->pagination) {
            $html .= $this->renderPagination();
        }
        
        $html .= '</div>';
        
        // Add JavaScript if features enabled
        if ($this->sortable || $this->searchable || $this->pagination) {
            $html .= $this->renderScript();
        }
        
        return $html;
    }
    
    /**
     * Render search box
     */
    private function renderSearch() {
        return '
        <div class="table-search mb-3">
            <input type="text" class="form-control" id="' . $this->tableId . '_search" 
                   placeholder="Tìm kiếm..." onkeyup="searchTable_' . $this->tableId . '(this.value)">
        </div>';
    }
    
    /**
     * Render table header
     */
    private function renderHeader() {
        $html = '<thead><tr>';
        
        foreach ($this->columns as $column) {
            $sortable = $this->sortable && !isset($column['sortable']) || $column['sortable'];
            $width = isset($column['width']) ? 'style="width: ' . $column['width'] . '"' : '';
            $align = isset($column['align']) ? 'text-' . $column['align'] : '';
            
            if ($sortable) {
                $html .= '<th class="sortable ' . $align . '" ' . $width . ' onclick="sortTable_' . $this->tableId . '(this)">';
                $html .= htmlspecialchars($column['label']);
                $html .= ' <i class="fas fa-sort"></i>';
            } else {
                $html .= '<th class="' . $align . '" ' . $width . '>';
                $html .= htmlspecialchars($column['label']);
            }
            
            $html .= '</th>';
        }
        
        if (!empty($this->actions)) {
            $html .= '<th style="width: 100px;">Thao tác</th>';
        }
        
        $html .= '</tr></thead>';
        
        return $html;
    }
    
    /**
     * Render table body
     */
    private function renderBody() {
        $html = '<tbody>';
        
        if (empty($this->data)) {
            $colspan = count($this->columns) + (!empty($this->actions) ? 1 : 0);
            $html .= '<tr><td colspan="' . $colspan . '" class="text-center text-muted">Không có dữ liệu</td></tr>';
        } else {
            foreach ($this->data as $rowIndex => $row) {
                $html .= $this->renderRow($row, $rowIndex);
            }
        }
        
        $html .= '</tbody>';
        
        return $html;
    }
    
    /**
     * Render single row
     */
    private function renderRow($row, $index) {
        $html = '<tr data-row-index="' . $index . '">';
        
        foreach ($this->columns as $column) {
            $value = $row[$column['key']] ?? '';
            $align = isset($column['align']) ? 'text-' . $column['align'] : '';
            
            // Apply formatter if exists
            if (isset($column['formatter'])) {
                if (is_callable($column['formatter'])) {
                    $value = call_user_func($column['formatter'], $value, $row);
                }
            } else {
                // Default formatting
                $value = $this->formatValue($value, $column);
            }
            
            $html .= '<td class="' . $align . '">' . $value . '</td>';
        }
        
        // Add actions
        if (!empty($this->actions)) {
            $html .= '<td>' . $this->renderActions($row, $index) . '</td>';
        }
        
        $html .= '</tr>';
        
        return $html;
    }
    
    /**
     * Format cell value
     */
    private function formatValue($value, $column) {
        $type = $column['type'] ?? 'text';
        
        switch ($type) {
            case 'number':
                return number_format($value, $column['decimals'] ?? 0, ',', '.');
                
            case 'money':
                return number_format($value, 0, ',', '.') . ' đ';
                
            case 'percent':
                return number_format($value, 1, ',', '.') . '%';
                
            case 'date':
                return $value ? date('d/m/Y', strtotime($value)) : '';
                
            case 'datetime':
                return $value ? date('d/m/Y H:i', strtotime($value)) : '';
                
            case 'badge':
                $color = $column['color'] ?? 'primary';
                if (isset($column['colors'][$value])) {
                    $color = $column['colors'][$value];
                }
                return '<span class="badge bg-' . $color . '">' . htmlspecialchars($value) . '</span>';
                
            case 'link':
                $href = $column['href'] ?? '#';
                $href = str_replace('{value}', $value, $href);
                return '<a href="' . $href . '">' . htmlspecialchars($value) . '</a>';
                
            case 'bool':
                return $value ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>';
                
            default:
                return htmlspecialchars($value);
        }
    }
    
    /**
     * Render row actions
     */
    private function renderActions($row, $index) {
        $html = '<div class="btn-group" role="group">';
        
        foreach ($this->actions as $action) {
            $callback = $action['callback'];
            $onclick = '';
            
            if (is_string($callback)) {
                // JavaScript function
                $onclick = $callback . '(' . json_encode($row) . ')';
            } elseif (is_array($callback)) {
                // URL with parameters
                $url = $callback[0];
                $params = $callback[1] ?? [];
                foreach ($params as $key => $field) {
                    $url = str_replace('{' . $key . '}', $row[$field] ?? '', $url);
                }
                $onclick = "window.location.href='" . $url . "'";
            }
            
            $html .= '<button type="button" class="btn ' . $action['class'] . '" 
                             onclick="' . htmlspecialchars($onclick) . '" 
                             title="' . htmlspecialchars($action['label']) . '">';
            
            if ($action['icon']) {
                $html .= '<i class="' . $action['icon'] . '"></i>';
            }
            
            if ($action['label'] && !strpos($action['class'], 'btn-sm')) {
                $html .= ' ' . htmlspecialchars($action['label']);
            }
            
            $html .= '</button>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render table footer
     */
    private function renderFooter() {
        $html = '<tfoot><tr>';
        
        foreach ($this->columns as $column) {
            $total = '';
            
            if (isset($column['footer'])) {
                if ($column['footer'] === 'sum') {
                    $total = array_sum(array_column($this->data, $column['key']));
                    $total = $this->formatValue($total, $column);
                } elseif ($column['footer'] === 'avg') {
                    $values = array_column($this->data, $column['key']);
                    $total = count($values) > 0 ? array_sum($values) / count($values) : 0;
                    $total = $this->formatValue($total, $column);
                } elseif ($column['footer'] === 'count') {
                    $total = count($this->data);
                } else {
                    $total = $column['footer'];
                }
            }
            
            $html .= '<th>' . $total . '</th>';
        }
        
        if (!empty($this->actions)) {
            $html .= '<th></th>';
        }
        
        $html .= '</tr></tfoot>';
        
        return $html;
    }
    
    /**
     * Render pagination
     */
    private function renderPagination() {
        $totalRows = count($this->data);
        $totalPages = ceil($totalRows / $this->pagination);
        
        $html = '<nav><ul class="pagination justify-content-center" id="' . $this->tableId . '_pagination">';
        
        for ($i = 1; $i <= $totalPages; $i++) {
            $active = $i === 1 ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '">
                        <a class="page-link" href="#" onclick="paginate_' . $this->tableId . '(' . $i . ')">' . $i . '</a>
                      </li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
    
    /**
     * Render JavaScript
     */
    private function renderScript() {
        $perPage = $this->pagination ?: 'null';
        
        return "
        <script>
        // Search function
        function searchTable_{$this->tableId}(value) {
            const table = document.getElementById('{$this->tableId}');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let row of rows) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value.toLowerCase()) ? '' : 'none';
            }
        }
        
        // Sort function
        function sortTable_{$this->tableId}(th) {
            const table = document.getElementById('{$this->tableId}');
            const tbody = table.getElementsByTagName('tbody')[0];
            const rows = Array.from(tbody.getElementsByTagName('tr'));
            const index = Array.from(th.parentNode.children).indexOf(th);
            const isAsc = th.classList.contains('asc');
            
            rows.sort((a, b) => {
                const aVal = a.cells[index].textContent;
                const bVal = b.cells[index].textContent;
                
                if (!isNaN(aVal) && !isNaN(bVal)) {
                    return isAsc ? bVal - aVal : aVal - bVal;
                }
                
                return isAsc ? bVal.localeCompare(aVal) : aVal.localeCompare(bVal);
            });
            
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
            
            // Update sort icons
            th.parentNode.querySelectorAll('th').forEach(t => {
                t.classList.remove('asc', 'desc');
                t.querySelector('i').className = 'fas fa-sort';
            });
            
            th.classList.add(isAsc ? 'desc' : 'asc');
            th.querySelector('i').className = isAsc ? 'fas fa-sort-down' : 'fas fa-sort-up';
        }
        
        // Pagination function
        function paginate_{$this->tableId}(page) {
            const perPage = {$perPage};
            if (!perPage) return;
            
            const table = document.getElementById('{$this->tableId}');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            const start = (page - 1) * perPage;
            const end = start + perPage;
            
            for (let i = 0; i < rows.length; i++) {
                rows[i].style.display = (i >= start && i < end) ? '' : 'none';
            }
            
            // Update pagination active
            document.querySelectorAll('#{$this->tableId}_pagination .page-item').forEach((item, i) => {
                item.classList.toggle('active', i === page - 1);
            });
        }
        
        // Initialize pagination
        if ({$perPage}) {
            paginate_{$this->tableId}(1);
        }
        </script>
        ";
    }
}