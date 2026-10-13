<% if $HasValues %>
	<div class="dashboard-linechart" data-dashboard-chart="$ChartData.ATT">
		<% if $IsMulti %>
			<ul class="dashboard-linechart__legend">
				<% loop $SeriesList %>
					<li class="dashboard-linechart__legend-item dashboard-linechart__series--$SeriesIndex">$Title</li>
				<% end_loop %>
			</ul>
		<% end_if %>
		<div class="dashboard-linechart__plot"></div>
		<div class="dashboard-linechart__tooltip" hidden>
			<span class="dashboard-linechart__tooltip-label"></span>
			<div class="dashboard-linechart__tooltip-values"></div>
		</div>
	</div>
	<details class="dashboard-linechart__data">
		<summary><%t Pixelpoems\\Dashboard\\Panel\\LineChartPanel.SHOW_DATA 'Show data as table' %></summary>
		<table class="table dashboard-table">
			<thead>
				<tr>
					<th scope="col"><%t Pixelpoems\\Dashboard\\Panel\\LineChartPanel.PERIOD 'Period' %></th>
					<% loop $SeriesList %>
						<th scope="col">$Title</th>
					<% end_loop %>
				</tr>
			</thead>
			<tbody>
				<% loop $Rows %>
					<tr>
						<td>$Label</td>
						<% loop $Values %><td>$Value</td><% end_loop %>
					</tr>
				<% end_loop %>
			</tbody>
		</table>
	</details>
<% else %>
	<p class="dashboard-panel__empty"><%t Pixelpoems\\Dashboard\\Panel\\LineChartPanel.NO_DATA 'No data in the selected period.' %></p>
<% end_if %>
