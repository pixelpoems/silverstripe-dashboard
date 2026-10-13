/**
 * Lazy loads dashboard panels and handles the date range filter. Uses entwine as CMS sections are loaded via PJAX.
 */
(function ($) {
    $.entwine('ss', function ($) {
        $('.dashboard-panel[data-dashboard-url]').entwine({
            onmatch: function () {
                this._super();
                this.loadPanel();
            },

            loadPanel: function () {
                var panel = this;
                var body = panel.find('.dashboard-panel__body');

                fetch(panel.data('dashboard-url'), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error(response.status + ' ' + response.statusText);
                        }
                        return response.text();
                    })
                    .then(function (html) {
                        body.html(html);
                    })
                    .catch(function (error) {
                        body.empty().append(
                            $('<p class="dashboard-panel__error"></p>').text(
                                ss.i18n._t('Pixelpoems\\Dashboard.LOAD_ERROR', 'Panel could not be loaded.') + ' (' + error.message + ')'
                            )
                        );
                    });
            }
        });

        // Line chart - single series as SVG with crosshair tooltip, re-rendered on resize
        $('.dashboard-linechart[data-dashboard-chart]').entwine({
            onmatch: function () {
                this._super();

                var chart = this;
                var timeout;
                this.data('resize-handler', function () {
                    clearTimeout(timeout);
                    timeout = setTimeout(function () { chart.renderChart(); }, 150);
                });
                $(window).on('resize', this.data('resize-handler'));

                this.renderChart();
            },

            onunmatch: function () {
                $(window).off('resize', this.data('resize-handler'));
                this._super();
            },

            renderChart: function () {
                var data = this.data('dashboard-chart');
                var plot = this.find('.dashboard-linechart__plot');
                var width = plot.width();
                if (!data || !data.values || !data.values.length || !width) {
                    return;
                }

                var NS = 'http://www.w3.org/2000/svg';
                var height = 240;
                var pad = { top: 16, right: 48, bottom: 28, left: 40 };
                var innerW = width - pad.left - pad.right;
                var innerH = height - pad.top - pad.bottom;
                var values = data.values;
                var count = values.length;
                var max = Math.max.apply(null, values);

                // Clean tick step (1, 2, 5 * 10^n) for ~4 gridlines
                var raw = Math.max(max, 1) / 4;
                var magnitude = Math.pow(10, Math.floor(Math.log10(raw)));
                var step = [1, 2, 5, 10].map(function (f) { return f * magnitude; }).find(function (s) { return s >= raw; });
                step = Math.max(1, step);
                var top = Math.ceil(Math.max(max, 1) / step) * step;

                var x = function (i) { return pad.left + (count === 1 ? innerW / 2 : i * innerW / (count - 1)); };
                var y = function (v) { return pad.top + innerH - v / top * innerH; };
                var format = function (v) { return v.toLocaleString(document.documentElement.lang || undefined); };

                var el = function (name, attrs, parent) {
                    var node = document.createElementNS(NS, name);
                    Object.keys(attrs).forEach(function (key) { node.setAttribute(key, attrs[key]); });
                    if (parent) {
                        parent.appendChild(node);
                    }
                    return node;
                };

                var svg = el('svg', {
                    width: width,
                    height: height,
                    viewBox: '0 0 ' + width + ' ' + height,
                    class: 'dashboard-linechart__svg',
                    role: 'img',
                    tabindex: 0,
                    'aria-label': data.series
                });

                // Gridlines + y ticks
                for (var t = 0; t <= top; t += step) {
                    el('line', { x1: pad.left, x2: width - pad.right, y1: y(t), y2: y(t), class: 'dashboard-linechart__grid' }, svg);
                    el('text', { x: pad.left - 8, y: y(t), class: 'dashboard-linechart__tick', 'text-anchor': 'end', 'dominant-baseline': 'middle' }, svg)
                        .textContent = format(t);
                }

                // X labels - as many as fit without overlapping
                var every = Math.max(1, Math.ceil(count / Math.max(1, Math.floor(innerW / 110))));
                for (var i = 0; i < count; i += every) {
                    el('text', { x: x(i), y: height - 8, class: 'dashboard-linechart__tick', 'text-anchor': count === 1 ? 'middle' : (i === 0 ? 'start' : 'middle') }, svg)
                        .textContent = data.labels[i];
                }

                // Area wash + line
                var line = values.map(function (v, i) { return (i ? 'L' : 'M') + x(i) + ' ' + y(v); }).join(' ');
                el('path', { d: line + ' L' + x(count - 1) + ' ' + y(0) + ' L' + x(0) + ' ' + y(0) + ' Z', class: 'dashboard-linechart__area' }, svg);
                el('path', { d: line, class: 'dashboard-linechart__line' }, svg);

                // End dot + value label
                el('circle', { cx: x(count - 1), cy: y(values[count - 1]), r: 4, class: 'dashboard-linechart__dot' }, svg);
                el('text', { x: x(count - 1) + 10, y: y(values[count - 1]), class: 'dashboard-linechart__end-label', 'dominant-baseline': 'middle' }, svg)
                    .textContent = format(values[count - 1]);

                // Crosshair + hover dot
                var crosshair = el('line', { y1: pad.top, y2: pad.top + innerH, class: 'dashboard-linechart__crosshair', visibility: 'hidden' }, svg);
                var hoverDot = el('circle', { r: 4, class: 'dashboard-linechart__dot', visibility: 'hidden' }, svg);
                var hit = el('rect', { x: pad.left - 12, y: 0, width: innerW + 24, height: height, fill: 'transparent' }, svg);

                plot.empty().append(svg);

                var tooltip = this.find('.dashboard-linechart__tooltip');
                tooltip.find('.dashboard-linechart__tooltip-series').text(data.series);
                var active = count - 1;

                var show = function (index) {
                    active = Math.max(0, Math.min(count - 1, index));
                    var cx = x(active);
                    var cy = y(values[active]);
                    crosshair.setAttribute('x1', cx);
                    crosshair.setAttribute('x2', cx);
                    crosshair.setAttribute('visibility', 'visible');
                    hoverDot.setAttribute('cx', cx);
                    hoverDot.setAttribute('cy', cy);
                    hoverDot.setAttribute('visibility', 'visible');

                    tooltip.find('.dashboard-linechart__tooltip-value').text(format(values[active]));
                    tooltip.find('.dashboard-linechart__tooltip-label').text(data.labels[active]);
                    tooltip.prop('hidden', false);

                    var left = cx + 12;
                    if (left + tooltip.outerWidth() > width) {
                        left = cx - 12 - tooltip.outerWidth();
                    }
                    tooltip.css({ left: left, top: Math.max(0, cy - tooltip.outerHeight() / 2) });
                };

                var hide = function () {
                    crosshair.setAttribute('visibility', 'hidden');
                    hoverDot.setAttribute('visibility', 'hidden');
                    tooltip.prop('hidden', true);
                };

                hit.addEventListener('pointermove', function (e) {
                    var bounds = svg.getBoundingClientRect();
                    var position = e.clientX - bounds.left - pad.left;
                    show(count === 1 ? 0 : Math.round(position / innerW * (count - 1)));
                });
                hit.addEventListener('pointerleave', hide);
                svg.addEventListener('focus', function () { show(active); });
                svg.addEventListener('blur', hide);
                svg.addEventListener('keydown', function (e) {
                    if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
                        e.preventDefault();
                        show(active + (e.key === 'ArrowLeft' ? -1 : 1));
                    }
                });
            }
        });

        // Date range filter - reload the dashboard via PJAX instead of a full page load
        $('.dashboard-filter').entwine({
            onsubmit: function (e) {
                e.preventDefault();

                var action = this.attr('action');
                var separator = action.indexOf('?') === -1 ? '?' : '&';
                $('.cms-container').loadPanel(action + separator + this.serialize());

                return false;
            }
        });

        $('.dashboard-filter__preset').entwine({
            onclick: function (e) {
                e.preventDefault();
                $('.cms-container').loadPanel(this.attr('href'));

                return false;
            }
        });
    });
}(jQuery));
