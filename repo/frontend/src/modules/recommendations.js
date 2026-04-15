/**
 * CampusOps Recommendations Module
 * Activity recommendations.
 */
layui.define(['jquery', 'common'], function (exports) {
    var $ = layui.jquery;
    var common = layui.common;

    var recommendations = {
        load: function (containerId, context, limit) {
            context = context || 'list';
            limit = limit || 10;
            
            common.request({
                url: '/recommendations?context=' + context + '&limit=' + limit,
                success: function (res) {
                    if (res.success) {
                        recommendations.render(res.data.list, containerId);
                    }
                }
            });
        },

        render: function (list, containerId) {
            var $container = $('#' + containerId);
            if (!list || list.length === 0) {
                $container.html('<div style="color:#999;padding:10px;">No recommendations</div>');
                return;
            }

            var html = '';
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                var tags = item.tags ? item.tags.slice(0, 3).join(', ') : '';
                html += '<div class="layui-card" style="margin-bottom:10px;cursor:pointer;" onclick="layui.recommendations.view(' + item.id + ')">' +
                    '<div class="layui-card-body">' +
                    '<div style="font-weight:600;">' + item.title + '</div>' +
                    (tags ? '<div style="color:#999;font-size:12px;margin-top:5px;">' + tags + '</div>' : '') +
                    '<div style="color:#1E9FFF;font-size:12px;margin-top:5px;">' + (item.signup_count || 0) + ' signed up</div>' +
                    '</div></div>';
            }
            $container.html(html);
        },

        view: function (id) {
            var $container = layui.jquery('#app-content-inner');
            $container.empty();
            layui.use('activities', function () {
                layui.activities.showDetail(id);
            });
        },

        loadPopular: function (containerId, limit) {
            limit = limit || 10;
            common.request({
                url: '/recommendations/popular?limit=' + limit,
                success: function (res) {
                    if (res.success) {
                        recommendations.render(res.data, containerId);
                    }
                }
            });
        }
    };

    window.layui = window.layui || {};
    window.layui.recommendations = recommendations;
    exports('recommendations', recommendations);
});