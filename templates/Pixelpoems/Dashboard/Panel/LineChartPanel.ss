<% if $HasValues %>
	<div class="dashboard-linechart" data-dashboard-chart="$ChartData.ATT">
		<div class="dashboard-linechart__plot"></div>
		<div class="dashboard-linechart__tooltip" hidden>
			<strong class="dashboard-linechart__tooltip-value"></strong>
			<span class="dashboard-linechart__tooltip-series"></span>
			<span class="dashboard-linechart__tooltip-label"></span>
		</div>
	</div>
	<details class="dashboard-linechart__data">
		<summary><%t Pixelpoems\\Dashboard\\Panel\\LineChartPanel.SHOW_DATA 'Show data as table' %></summary>
		<table class="table dashboard-table">
			<thead>
				<tr>
					<th scope="col"><%t Pixelpoems\\Dashboard\\Panel\\LineChartPanel.PERIOD 'Period' %></th>
					<th scope="col">$SeriesTitle</th>
				</tr>
			</thead>
			<tbody>
				<% loop $Points %>
					<tr><td>$Label</td><td>$Value</td></tr>
				<% end_loop %>
			</tbody>
		</table>
	</details>
<% else %>
	<p class="dashboard-panel__empty"><%t Pixelpoems\\Dashboard\\Panel\\LineChartPanel.NO_DATA 'No data in the selected period.' %></p>
<% end_if %>
