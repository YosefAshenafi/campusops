/**
 * CampusOps Audit Module
 * Audit trail viewing.
 */
layui.define(['jquery', 'common'], function (exports) {
    var $ = layui.jquery;
    var common = layui.common;

    var audit = {
        initList: function () {
            this.load(1);
            this.bindEvents();
        },

        load: function (page) {
            page = page || 1;
            var params = {
                page: page,
                limit: 50,
                entity_type: $('#filter-entity-type').val() || '',
                date_from: $('#filter-date-from').val() || '',
                date_to: $('#filter-date-to').val() || ''
            };
            common.request({
                url: '/audit',
                data: params,
                success: function (res) {
                    if (res.success) audit.render(res.data);
                }
            });
        },

        render: function (data) {
            var $tbody = $('#audit-tbody');
            $tbody.empty();

            var list = data.list || [];
            if (list.length === 0) {
                $tbody.append('<tr><td colspan="5" style="text-align:center;color:#999;">No audit entries</td></tr>');
                return;
            }

            for (var i = 0; i < list.length; i++) {
                var a = list[i];
                var changes = '';
                if (a.old_state || a.new_state) {
                    changes = (a.old_state || '-') + ' → ' + (a.new_state || '-');
                }
                $tbody.append('<tr>' +
                    '<td>' + a.id + '</td>' +
                    '<td>' + a.entity_type + ' #' + a.entity_id + '</td>' +
                    '<td>' + a.action + '</td>' +
                    '<td>' + changes + '</td>' +
                    '<td>' + common.formatDateTime(a.created_at) + '</td></tr>');
            }

            var totalPages = Math.ceil(data.total / data.limit);
            var html = '<span>Total: ' + data.total + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (data.page > 1 ? 'onclick="layui.audit.load(' + (data.page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            html += '<span>Page ' + data.page + ' of ' + totalPages + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (data.page < totalPages ? 'onclick="layui.audit.load(' + (data.page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#audit-pagination').html(html);
        },

        bindEvents: function () {
            var that = this;
            $('#btn-search').on('click', function () { that.load(1); });
        }
    };

    window.layui = window.layui || {};
    window.layui.audit = audit;
    exports('audit', audit);
});