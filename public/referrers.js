document.addEventListener('DOMContentLoaded', function() {
    // Pie Chart - Top Sources
    const pieChartEl = document.querySelector('#referralPieChart');
    if (pieChartEl) {
        const topReferrers = window.topReferrersData.top5;
        const totalReferralHits = window.topReferrersData.totalHits;

        const labels = topReferrers.map(r => r.referrer_domain);
        const series = topReferrers.map(r => parseInt(r.total_hits));

        // Calculate "Other" if there are more than 5 referrers
        const top5Total = series.reduce((a, b) => a + b, 0);
        const otherTotal = totalReferralHits - top5Total;
        if (otherTotal > 0) {
            labels.push('Other');
            series.push(otherTotal);
        }

        // Gold, Silver (darker base so hover lightens nicely), Bronze, then theme colors, then gray for Other
        const colors = ['#FFD700', '#A9A9A9', '#CD7F32', config.colors.info, config.colors.secondary, '#6c757d'];

        const pieConfig = {
            chart: {
                height: 200,
                type: 'donut'
            },
            series: series,
            labels: labels,
            colors: colors.slice(0, series.length),
            stroke: {
                width: 3,
                colors: [config.colors.cardColor]
            },
            dataLabels: { enabled: false },
            legend: { show: false },
            states: {
                hover: {
                    filter: {
                        type: 'lighten',
                        value: 0.05
                    }
                }
            },
            plotOptions: {
                pie: {
                    donut: {
                        size: '70%',
                        labels: {
                            show: true,
                            name: { fontSize: '12px' },
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

        new ApexCharts(pieChartEl, pieConfig).render();
    }

    // Line Chart - Referral Trend
    const chartEl = document.querySelector('#referralTrendChart');
    if (!chartEl) return;

    const dailyData = window.topReferrersData.daily;

    // Format data for chart
    const categories = dailyData.map(d => {
        const date = new Date(d.hit_date);
        return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
    });
    const hitsData = dailyData.map(d => parseInt(d.daily_total));
    const uniqueData = dailyData.map(d => parseInt(d.daily_unique || 0));

    const chartConfig = {
        chart: {
            height: 250,
            type: 'area',
            toolbar: { show: false },
            parentHeightOffset: 0
        },
        series: [
            { name: 'Referral Hits', data: hitsData },
            { name: 'Unique Visitors', data: uniqueData }
        ],
        colors: [config.colors.primary, config.colors.success],
        fill: {
            type: 'gradient',
            gradient: {
                shadeIntensity: 1,
                opacityFrom: 0.4,
                opacityTo: 0.1,
                stops: [0, 100]
            }
        },
        dataLabels: { enabled: false },
        stroke: {
            width: 2,
            curve: 'smooth'
        },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'right',
            fontSize: '12px',
            markers: { radius: 12 }
        },
        grid: {
            borderColor: config.colors.borderColor,
            strokeDashArray: 4,
            padding: { top: 0, bottom: 0, left: 10, right: 10 }
        },
        xaxis: {
            categories: categories,
            axisBorder: { show: false },
            axisTicks: { show: false },
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
            hover: { size: 5 }
        }
    };

    new ApexCharts(chartEl, chartConfig).render();
});
