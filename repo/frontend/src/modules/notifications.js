/**
 * CampusOps Notifications Module
 * Notifications and preferences management.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var form = layui.form;
    var common = layui.common;

    var notifications = {
        initList: function () {
            this.init();
        },

        init: function () {
            this.load();
            this.bindEvents();
        },

        load: function (page) {
            page = page || 1;
            var that = this;
            common.request({
                url: '/notifications',
                data: { page: page, limit: 20 },
                success: function (res) {
                    if (res.success) {
                        that.render(res.data);
                    }
                }
            });
        },

        render: function (data) {
            var $list = $('#notifications-list');
            $list.empty();

            var unread = data.unread_count || 0;
            if (unread > 0) {
                $('#unread-badge').text(unread + ' unread').show();
            } else {
                $('#unread-badge').hide();
            }

            var list = data.list || [];
            if (list.length === 0) {
                $list.html('<div style="text-align:center;padding:30px;color:#999;">No notifications</div>');
                return;
            }

            var html = '<table class="layui-table" lay-skin="line"><thead><tr><th></th><th>Title</th><th>Date</th></tr></thead><tbody>';
            for (var i = 0; i < list.length; i++) {
                var n = list[i];
                var unreadClass = n.read_at ? '' : 'layui-bg-orange';
                var icon = n.read_at ? '&#10003;' : '&#9679;';
                var title = n.read_at ? n.title : '<strong>' + n.title + '</strong>';
                html += '<tr>' +
                    '<td><span style="color:' + (n.read_at ? '#999' : '#1E9FFF') + ';">' + icon + '</span></td>' +
                    '<td><a href="javascript:;" onclick="layui.notifications.view(' + n.id + ',\'' + n.entity_type + '\',' + n.entity_id + ')">' + title + '</a><br><small>' + n.body + '</small></td>' +
                    '<td>' + common.formatDateTime(n.created_at) + '</td></tr>';
            }
            html += '</tbody></table>';
            $list.html(html);
            this.renderPagination(data.total, data.page, data.limit);
        },

        renderPagination: function (total, page, limit) {
            var totalPages = Math.ceil(total / limit);
            var html = '<span>Total: ' + total + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page > 1 ? 'onclick="layui.notifications.load(' + (page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            html += '<span>Page ' + page + ' of ' + totalPages + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page < totalPages ? 'onclick="layui.notifications.load(' + (page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#notifications-pagination').html(html);
        },

        view: function (id, entityType, entityId) {
            common.request({
                url: '/notifications/' + id + '/read',
                method: 'PUT',
                success: function () {
                    if (entityType && entityId) {
                        var $container = layui.jquery('#app-content-inner');
                        $container.empty();
                        if (entityType === 'activity') {
                            layui.use('activities', function () {
                                layui.activities.showDetail(entityId);
                            });
                        } else if (entityType === 'order') {
                            layui.use('orders', function () {
                                layui.orders.showDetail(entityId);
                            });
                        }
                    }
                    notifications.load();
                }
            });
        },

        loadPreferences: function () {
            var that = this;
            common.request({
                url: '/preferences',
                success: function (res) {
                    if (res.success) {
                        that.renderPreferences(res.data);
                    }
                }
            });
        },

        renderPreferences: function (data) {
            form.val('preferences-form', {
                arrival_reminders: data.arrival_reminders,
                activity_alerts: data.activity_alerts,
                order_alerts: data.order_alerts,
                violation_alerts: data.violation_alerts
            });
        },

        savePreferences: function (data) {
            common.request({
                url: '/preferences',
                method: 'PUT',
                data: data,
                success: function (res) {
                    layer.msg('Preferences saved', { icon: 1 });
                }
            });
        },

        bindEvents: function () {
            var that = this;
            form.on('submit(preferences-form)', function (data) {
                that.savePreferences(data.field);
                return false;
            });
        }
    };

    window.layui = window.layui || {};
    window.layui.notifications = notifications;
    exports('notifications', notifications);
});