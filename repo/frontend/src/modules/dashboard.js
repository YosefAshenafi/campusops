/**
 * CampusOps Dashboard Module
 * Dashboard widgets and data.
 */
layui.define(['jquery', 'layer', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var dashboard = {
        dragSrcWidget: null,

        init: function () {
            this.loadLayout();
            this.load();
            this.initDragDrop();
            this.bindFavoriteButtons();
            this.bindLayoutButtons();
        },

        load: function () {
            var that = this;
            common.request({
                url: '/dashboard',
                success: function (res) {
                    if (res.success) {
                        that.render(res.data);
                    }
                }
            });
        },

        render: function (data) {
            this.renderOrdersChart(data.widgets.orders_by_state || []);
            this.renderActivitiesChart(data.widgets.activities_by_state || []);
            this.renderRecentOrders(data.widgets.recent_orders || []);
        },

        renderOrdersChart: function (data) {
            var $container = $('#orders-chart');
            if (!data || data.length === 0) {
                $container.html('<div style="color:#999;padding:20px;text-align:center;">No data</div>');
                return;
            }
            var html = '<table class="layui-table"><thead><tr><th>State</th><th>Count</th></tr></thead><tbody>';
            for (var i = 0; i < data.length; i++) {
                var item = data[i];
                html += '<tr><td>' + item.state + '</td><td>' + item.count + '</td></tr>';
            }
            html += '</tbody></table>';
            $container.html(html);
        },

        renderActivitiesChart: function (data) {
            var $container = $('#activities-chart');
            if (!data || data.length === 0) {
                $container.html('<div style="color:#999;padding:20px;text-align:center;">No data</div>');
                return;
            }
            var html = '<table class="layui-table"><thead><tr><th>State</th><th>Count</th></tr></thead><tbody>';
            for (var i = 0; i < data.length; i++) {
                var item = data[i];
                html += '<tr><td>' + item.state + '</td><td>' + item.count + '</td></tr>';
            }
            html += '</tbody></table>';
            $container.html(html);
        },

        renderRecentOrders: function (data) {
            var $container = $('#recent-orders');
            if (!data || data.length === 0) {
                $container.html('<div style="color:#999;padding:20px;text-align:center;">No orders</div>');
                return;
            }
            var html = '<table class="layui-table"><thead><tr><th>ID</th><th>State</th><th>Amount</th></tr></thead><tbody>';
            for (var i = 0; i < data.length; i++) {
                var o = data[i];
                html += '<tr><td>' + o.id + '</td><td>' + o.state + '</td><td>$' + o.amount + '</td></tr>';
            }
            html += '</tbody></table>';
            $container.html(html);
        },

        favoriteWidget: function (widgetId) {
            common.request({
                url: '/dashboard/favorites',
                method: 'POST',
                data: { widget_id: widgetId },
                success: function (res) {
                    if (res.success) {
                        layer.msg('Widget "' + widgetId + '" added to favorites');
                        // Update button state if rendered
                        var $btn = $('[data-widget-id="' + widgetId + '"] .fav-btn');
                        if ($btn.length) $btn.text('★ Favorited').addClass('favorited');
                    }
                }
            });
        },

        unfavoriteWidget: function (widgetId) {
            common.request({
                url: '/dashboard/favorites/' + encodeURIComponent(widgetId),
                method: 'DELETE',
                success: function (res) {
                    if (res.success) {
                        layer.msg('Widget "' + widgetId + '" removed from favorites');
                        var $btn = $('[data-widget-id="' + widgetId + '"] .fav-btn');
                        if ($btn.length) $btn.text('☆ Favorite').removeClass('favorited');
                    }
                }
            });
        },

        initDragDrop: function () {
            var that = this;
            var container = document.getElementById('dashboard-widgets');
            if (!container) return;

            var widgets = container.querySelectorAll('.dashboard-widget');
            widgets.forEach(function (widget) {
                widget.addEventListener('dragstart', function (e) {
                    that.dragSrcWidget = widget;
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', widget.getAttribute('data-widget-id'));
                });

                widget.addEventListener('dragover', function (e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    widget.classList.add('drag-over');
                    return false;
                });

                widget.addEventListener('dragleave', function () {
                    widget.classList.remove('drag-over');
                });

                widget.addEventListener('drop', function (e) {
                    e.stopPropagation();
                    widget.classList.remove('drag-over');
                    if (that.dragSrcWidget !== widget) {
                        // Swap positions in the DOM
                        var srcNext = that.dragSrcWidget.nextSibling;
                        var tgtNext = widget.nextSibling;
                        if (tgtNext === that.dragSrcWidget) {
                            container.insertBefore(that.dragSrcWidget, widget);
                        } else {
                            container.insertBefore(widget, srcNext);
                            container.insertBefore(that.dragSrcWidget, tgtNext);
                        }
                    }
                    return false;
                });

                widget.addEventListener('dragend', function () {
                    widgets.forEach(function (w) { w.classList.remove('drag-over'); });
                });
            });
        },

        saveLayout: function () {
            var container = document.getElementById('dashboard-widgets');
            if (!container) return;
            var order = [];
            container.querySelectorAll('.dashboard-widget').forEach(function (w) {
                order.push(w.getAttribute('data-widget-id'));
            });
            common.request({
                url: '/dashboard/custom',
                method: 'POST',
                data: { layout: order },
                success: function (res) {
                    if (res.success) {
                        layer.msg('Layout saved', { icon: 1 });
                    }
                }
            });
        },

        loadLayout: function () {
            var container = document.getElementById('dashboard-widgets');
            if (!container) return;
            common.request({
                url: '/dashboard/custom',
                success: function (res) {
                    if (res.success && res.data && Array.isArray(res.data.layout)) {
                        var order = res.data.layout;
                        order.forEach(function (widgetId) {
                            var el = container.querySelector('[data-widget-id="' + widgetId + '"]');
                            if (el) container.appendChild(el);
                        });
                    }
                }
            });
        },

        bindLayoutButtons: function () {
            var that = this;
            $('#btn-save-layout').on('click', function () { that.saveLayout(); });
            $('#btn-reset-layout').on('click', function () {
                common.request({
                    url: '/dashboard/custom',
                    method: 'DELETE',
                    success: function (res) {
                        if (res.success) {
                            layer.msg('Layout reset', { icon: 1 });
                            that.load();
                        }
                    }
                });
            });
        },

        bindFavoriteButtons: function () {
            var that = this;
            // Load current favorites to set initial state
            common.request({
                url: '/dashboard/favorites',
                success: function (res) {
                    if (res.success && res.data) {
                        var favIds = res.data.map(function (f) { return f.widget_id; });
                        $('[data-widget-id]').each(function () {
                            var wid = $(this).data('widget-id');
                            var $btn = $(this).find('.fav-btn');
                            if ($btn.length === 0) {
                                $btn = $('<button class="fav-btn layui-btn layui-btn-xs" style="margin-left:8px;">☆ Favorite</button>');
                                $(this).find('.layui-card-header').append($btn);
                            }
                            if (favIds.indexOf(wid) !== -1) {
                                $btn.text('★ Favorited').addClass('favorited');
                            }
                            $btn.off('click').on('click', function (e) {
                                e.stopPropagation();
                                if ($btn.hasClass('favorited')) {
                                    that.unfavoriteWidget(wid);
                                } else {
                                    that.favoriteWidget(wid);
                                }
                            });
                        });
                    }
                }
            });
        }
    };

    window.layui = window.layui || {};
    window.layui.dashboard = dashboard;
    exports('dashboard', dashboard);
});