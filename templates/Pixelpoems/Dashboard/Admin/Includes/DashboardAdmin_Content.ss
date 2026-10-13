<div id="dashboard-admin-cms-content" class="flexbox-area-grow fill-height cms-content $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content">

	<div class="cms-content-header vertical-align-items">
		<div class="cms-content-header-info flexbox-area-grow vertical-align-items">
			<% include SilverStripe\Admin\CMSBreadcrumbs %>
		</div>
	</div>

	<div class="flexbox-area-grow cms-content-fields ui-widget-content panel--padded">
		<form class="dashboard-filter" action="$Link" method="get">
			<div class="dashboard-filter__fields">
				<label class="dashboard-filter__field">
					<span><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.FROM 'From' %></span>
					<input type="date" name="from" value="$FilterFrom" class="form-control" required>
				</label>
				<label class="dashboard-filter__field">
					<span><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.TO 'To' %></span>
					<input type="date" name="to" value="$FilterTo" class="form-control" required>
				</label>
				<button type="submit" class="btn btn-primary"><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.APPLY 'Apply' %></button>
			</div>
			<% if $DateRangePresets %>
				<div class="dashboard-filter__presets btn-group" role="group">
					<% loop $DateRangePresets %>
						<a href="$Link" class="btn btn-outline-secondary dashboard-filter__preset<% if $IsCurrent %> active<% end_if %>">$Title</a>
					<% end_loop %>
				</div>
			<% end_if %>
			<% if $ShowLegend %>
				<ul class="dashboard-legend">
					<li class="dashboard-legend__item dashboard-legend__item--range"><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.LEGEND_RANGE 'Follows the selected period' %></li>
					<li class="dashboard-legend__item dashboard-legend__item--total"><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.LEGEND_TOTAL 'Total, independent of the period' %></li>
				</ul>
			<% end_if %>
		</form>

		<% if $Panels %>
			<div class="dashboard-grid">
				<% loop $Panels %>
					<section class="dashboard-panel dashboard-panel--$Key <% if $usesDateRange %>dashboard-panel--range<% else %>dashboard-panel--total<% end_if %>" style="--dashboard-panel-width: $Width"
						<% if $isLazy %>data-dashboard-url="$LazyLink"<% end_if %>>
						<header class="dashboard-panel__header">
							<h2 class="dashboard-panel__title">$Title</h2>
							<span class="dashboard-panel__range">
								<% if $usesDateRange %>$DateRangeNice<% else %><%t Pixelpoems\Dashboard\Panel\DashboardPanel.TOTAL 'Total' %><% end_if %>
							</span>
							<% if $Description %><p class="dashboard-panel__description">$Description</p><% end_if %>
						</header>
						<div class="dashboard-panel__body">
							<% if $isLazy %>
								<div class="dashboard-panel__loading">
									<span class="dashboard-panel__spinner" aria-hidden="true"></span>
									<span class="visually-hidden"><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.LOADING 'Loading...' %></span>
								</div>
							<% else %>
								$renderContent
							<% end_if %>
						</div>
					</section>
				<% end_loop %>
			</div>
		<% else %>
			<p class="message notice"><%t Pixelpoems\Dashboard\Admin\DashboardAdmin.NO_PANELS 'No panels configured.' %></p>
		<% end_if %>
	</div>

</div>
