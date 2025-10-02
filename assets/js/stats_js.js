document.addEventListener('DOMContentLoaded', function() {
    // Auto submit form when report type changes
    const reportTypeSelect = document.getElementById('reportTypeSelect');
    if (reportTypeSelect) {
        reportTypeSelect.addEventListener('change', function() {
            document.getElementById('filterForm').submit();
        });
    }

    // Show loading on form submit
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function() {
            document.getElementById('loadingOverlay').classList.add('show');
        });
    });

    // Handle metric card clicks
    document.querySelectorAll('.metric-card.clickable').forEach(card => {
        card.addEventListener('click', function() {
            const drillType = this.dataset.drillType;
            const drillId = this.dataset.drillId;
            const currentParams = new URLSearchParams(window.location.search);
            currentParams.set('drill', drillType);
            currentParams.set('drill_id', drillId);
            window.location.href = '?' + currentParams.toString();
        });
    });

    // Initialize charts if on overview page and data is available
    if (typeof statsChartData !== 'undefined') {
        
        // Trend Chart
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            const trendChart = new Chart(trendCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: statsChartData.trendLabels,
                    datasets: [{
                        label: 'Doanh thu',
                        data: statsChartData.trendRevenue,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    if (context.dataset.label === 'Đơn hàng') {
                                        return 'Đơn hàng: ' + new Intl.NumberFormat('vi-VN').format(context.parsed.y || 0);
                                    }
                                    return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND'
                                    }).format(context.parsed.y || 0);
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return new Intl.NumberFormat('vi-VN', {
                                        notation: 'compact',
                                        compactDisplay: 'short'
                                    }).format(value);
                                }
                            }
                        }
                    }
                }
            });

            // Handle chart type switch
            document.querySelectorAll('[data-chart]').forEach(button => {
                button.addEventListener('click', function() {
                    document.querySelectorAll('[data-chart]').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const chartType = this.dataset.chart;
                    if (chartType === 'revenue') {
                        trendChart.data.datasets[0].data = statsChartData.trendRevenue;
                        trendChart.data.datasets[0].label = 'Doanh thu';
                    } else {
                        trendChart.data.datasets[0].data = statsChartData.trendOrders;
                        trendChart.data.datasets[0].label = 'Đơn hàng';
                    }
                    trendChart.update();
                });
            });
        }

        // Distribution Chart
        const distributionCtx = document.getElementById('distributionChart');
        if (distributionCtx) {
            new Chart(distributionCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: statsChartData.distributionLabels,
                    datasets: [{
                        data: statsChartData.distributionData,
                        backgroundColor: statsChartData.distributionColors
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? (context.parsed / total * 100).toFixed(1) : 0;
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});