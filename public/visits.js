/**
 * Visits Analytics - Charts and data visualization
 */

// ========================================
// Initialization
// ========================================

document.addEventListener('DOMContentLoaded', function() {
    initTrendChart();
    initDeviceChart();
    initCountryBreakdownToggle();
});

// ========================================
// Line Chart - Traffic Trend
// ========================================

function initTrendChart() {
    const chartEl = document.querySelector('#trafficTrendChart');
    if (!chartEl) return;

    const dailyData = window.visitsConfig.dailyTotals;

    // Format data for chart
    const categories = dailyData.map(d => {
        const date = new Date(d.hit_date);
        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'short'
        });
    });

    const hitsData = dailyData.map(d => parseInt(d.daily_total));
    const uniqueData = dailyData.map(d => parseInt(d.daily_unique || 0));

    const chartConfig = {
        chart: {
            height: 250,
            type: 'area',
            toolbar: {
                show: false
            },
            parentHeightOffset: 0
        },
        series: [
            {
                name: 'Total Hits',
                data: hitsData
            },
            {
                name: 'Unique Visitors',
                data: uniqueData
            }
        ],
        colors: [
            config.colors.primary,
            config.colors.success
        ],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 100]
            }
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            width: 2,
            curve: 'smooth'
        },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            markers: {
                radius: 12
            }
        },
        grid: {
            borderColor: config.colors.borderColor,
            strokeDashArray: 4,
            padding: {
                top: 0,
                bottom: 0,
                left: 10,
                right: 10
            }
        },
        xaxis: {
            categories: categories,
            axisBorder: {
                show: false
            },
            axisTicks: {
                show: false
            },
            labels: {
                style: {
                    fontSize: '11px',
                    colors: config.colors.textMuted
                },
                rotate: -45,
                rotateAlways: categories.length > 15
            }
        },
        yaxis: {
            labels: {
                style: {
                    fontSize: '11px',
                    colors: config.colors.textMuted
                },
                formatter: val => Math.round(val)
            }
        },
        tooltip: {
            shared: true,
            intersect: false
        },
        markers: {
            size: 3,
            strokeWidth: 2,
            hover: {
                size: 5
            }
        }
    };

    new ApexCharts(chartEl, chartConfig).render();
}

// ========================================
// Donut Chart - Device Breakdown
// ========================================

function initDeviceChart() {
    const chartEl = document.querySelector('#deviceBreakdownChart');
    if (!chartEl) return;

    const breakdown = window.visitsConfig.deviceBreakdown || [];
    if (breakdown.length === 0) return;

    const labelMap = { desktop: 'Desktop', mobile: 'Mobile', tablet: 'Tablet', other: 'Other' };
    const labels = breakdown.map(d => labelMap[d.device_type] || d.device_type);
    const series = breakdown.map(d => parseInt(d.total_hits));

    const deviceConfig = {
        chart: {
            height: 220,
            type: 'donut'
        },
        series: series,
        labels: labels,
        colors: [config.colors.primary, config.colors.success, config.colors.warning, config.colors.secondary],
        stroke: {
            width: 3,
            colors: [config.colors.cardColor]
        },
        dataLabels: {
            enabled: false
        },
        legend: {
            show: true,
            position: 'bottom',
            fontSize: '12px'
        },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        value: {
                            fontSize: '18px',
                            fontWeight: 600,
                            formatter: val => val + ' hits'
                        },
                        total: {
                            show: true,
                            label: 'Total',
                            fontSize: '12px',
                            formatter: w => w.globals.seriesTotals.reduce((a, b) => a + b, 0)
                        }
                    }
                }
            }
        }
    };

    new ApexCharts(chartEl, deviceConfig).render();
}

// ========================================
// Country Breakdown - Show More Toggle
// ========================================

function initCountryBreakdownToggle() {
    const collapseEl = document.querySelector('#countryBreakdownRest');
    const labelEl = document.querySelector('#countryBreakdownRestLabel');
    const iconEl = document.querySelector('#countryBreakdownRestIcon');
    if (!collapseEl || !labelEl || !iconEl) return;

    collapseEl.addEventListener('show.bs.collapse', function() {
        labelEl.textContent = labelEl.dataset.hideLabel;
        iconEl.classList.replace('bx-chevron-down', 'bx-chevron-up');
    });
    collapseEl.addEventListener('hide.bs.collapse', function() {
        labelEl.textContent = labelEl.dataset.showLabel;
        iconEl.classList.replace('bx-chevron-up', 'bx-chevron-down');
    });
}
