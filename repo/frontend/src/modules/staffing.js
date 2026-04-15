/**
 * CampusOps Staffing Module
 * Staffing plan management.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var staffing = {
        currentActivityId: null,

        initList: function () {
            var params = new URLSearchParams(window.location.search);
            var activityId = params.get('activity_id');
            if (activityId) {
                this.load(parseInt(activityId, 10));
            }
            this.bindEvents();
        },

        load: function (activityId) {
            this.currentActivityId = activityId;
            common.request({
                url: '/activities/' + activityId + '/staffing',
                success: function (res) {
                    if (res.success) staffing.render(res.data);
                }
            });
        },

        render: function (list) {
            var $tbody = $('#staffing-tbody');
            $tbody.empty();
            for (var i = 0; i < list.length; i++) {
                var s = list[i];
                var assigned = s.assigned_users ? s.assigned_users.length : 0;
                $tbody.append('<tr>' +
                    '<td>' + s.id + '</td>' +
                    '<td>' + s.role + '</td>' +
                    '<td>' + s.required_count + '</td>' +
                    '<td>' + assigned + '</td>' +
                    '<td>' + (s.notes || '-') + '</td>' +
                    '<td><button class="layui-btn layui-btn-xs" data-action="edit" data-id="' + s.id + '">Edit</button> ' +
                    '<button class="layui-btn layui-btn-xs layui-btn-danger" data-action="delete" data-id="' + s.id + '">Delete</button></td></tr>');
            }
            if (list.length === 0) $tbody.append('<tr><td colspan="6" style="text-align:center;color:#999;">No staffing</td></tr>');
        },

        bindEvents: function () {
            var that = this;
            $('#btn-add-staffing').on('click', function () { that.showForm(); });
            $('#staffing-tbody').on('click', '[data-action]', function () {
                var action = $(this).attr('data-action');
                var id = $(this).attr('data-id');
                if (action === 'edit') that.showForm(id);
                else if (action === 'delete') that.delete(id);
            });
        },

        showForm: function (id) {
            var that = this;
            var content = '<form class="layui-form layui-form-pane" style="padding:15px;">' +
                '<div class="layui-form-item"><label class="layui-form-label">Role</label><div class="layui-input-block"><input type="text" name="role" class="layui-input" required></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Required</label><div class="layui-input-block"><input type="number" name="required_count" class="layui-input" value="1"></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Notes</label><div class="layui-input-block"><textarea name="notes" class="layui-textarea"></textarea></div></div>' +
                '<div class="layui-form-item"><button class="layui-btn" lay-submit lay-filter="staffing-form">Save</button></div></form>';
            layer.open({
                type: 1,
                title: id ? 'Edit Staffing' : 'Add Staffing',
                content: content,
                area: ['400px', '350px'],
                success: function () {
                    layui.form.render();
                    layui.form.on('submit(staffing-form)', function (data) {
                        var url = id ? '/staffing/' + id : '/activities/' + that.currentActivityId + '/staffing';
                        var method = id ? 'PUT' : 'POST';
                        common.request({
                            url: url,
                            method: method,
                            data: data.field,
                            success: function () {
                                layer.closeAll();
                                layer.msg('Saved', { icon: 1 });
                                that.load(that.currentActivityId);
                            }
                        });
                        return false;
                    });
                }
            });
        },

        delete: function (id) {
            var that = this;
            layer.confirm('Delete?', { icon: 3 }, function (idx) {
                common.request({
                    url: '/staffing/' + id, method: 'DELETE',
                    success: function () { that.load(that.currentActivityId); }
                });
                layer.close(idx);
            });
        }
    };

    window.layui = window.layui || {};
    window.layui.staffing = staffing;
    exports('staffing', staffing);
});