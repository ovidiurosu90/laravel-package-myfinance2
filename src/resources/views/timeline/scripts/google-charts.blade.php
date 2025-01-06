<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
google.charts.load('current', {'packages':['timeline']});
google.charts.setOnLoadCallback(drawChart);
function drawChart()
{
@if(!empty($items))

    var container = document.getElementById('timeline');
    var chart = new google.visualization.Timeline(container);
    var dataTable = new google.visualization.DataTable();

    dataTable.addColumn({ type: 'string', id: 'RowLabel' });
    dataTable.addColumn({ type: 'string', id: 'BarLabel' });
    dataTable.addColumn({ type: 'string', role: 'tooltip',
                          id: 'Tooltip', 'p': {'html': true} });
    dataTable.addColumn({ type: 'date', id: 'Start' });
    dataTable.addColumn({ type: 'date', id: 'End' });
    dataTable.addRows([
    @foreach($items as $item)
        ['{{ $item['row_label'] }}',
         `{!! $item['bar_label'] !!}`,
         `{!! $item['tooltip'] !!}`,
         new Date('{{ $item['start'] }}'),
         new Date('{{ $item['end'] }}')],
    @endforeach
    ]);
    var options = {
        timeline: {
            groupByRowLabel: false,
            colorByRowLabel: true
        },
        focusTarget: 'category',
        tooltip: {isHtml: true},
        avoidOverlappingGridLines: false
    };

    chart.draw(dataTable, options);

@endif
}
</script>

