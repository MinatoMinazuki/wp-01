/* dashboard.js - ダッシュボード画面用スクリプト (jQuery版) */

/**
 * Chart.jsを使用して支出グラフを作成する
 * HTML側から渡されるデータを使用して初期化する
 */
function initExpenseChart(labels, dataVals, bgColors) {
    const $ctx = $('#expenseChart');
    if (!$ctx.length) return;

    const ctx = $ctx[0].getContext('2d');

    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: dataVals,
                backgroundColor: bgColors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    callbacks: {
                        label: function (context) {
                            let label = context.label || '';
                            if (label) { label += ': '; }
                            if (context.parsed !== null) {
                                label += '¥ ' + new Intl.NumberFormat().format(context.parsed);
                            }
                            return label;
                        }
                    }
                }
            }
        }
    });
}
