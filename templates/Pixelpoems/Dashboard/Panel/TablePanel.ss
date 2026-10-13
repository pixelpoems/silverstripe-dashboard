<% if $Rows %>
	<table class="table dashboard-table">
		<thead>
			<tr>
				<% loop $Columns %><th scope="col">$Title</th><% end_loop %>
			</tr>
		</thead>
		<tbody>
			<% loop $Rows %>
				<tr>
					<% loop $Cells %><td><% if $Link %><a href="$Link">$Value</a><% else %>$Value<% end_if %></td><% end_loop %>
				</tr>
			<% end_loop %>
		</tbody>
	</table>
<% else %>
	<p class="dashboard-panel__empty"><%t Pixelpoems\\Dashboard\\Panel\\TablePanel.NO_DATA 'No data available.' %></p>
<% end_if %>
<% if $Summary || $MoreLink %>
	<div class="dashboard-table__footer">
		<% if $Summary %><span class="dashboard-table__summary">$Summary</span><% end_if %>
		<% if $MoreLink %><a href="$MoreLink" class="btn btn-outline-secondary btn-sm dashboard-table__more">$MoreTitle</a><% end_if %>
	</div>
<% end_if %>
