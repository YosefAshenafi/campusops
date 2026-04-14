/**
 * CampusOps Tasks Module
 * Task and checklist management.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var tasks = {
        currentActivityId: null,

        initList: function () {
            var params = new URLSearchParams(window.location.search);
            var activityId = params.get('activity_id');
            if (activityId) {
                this.loadTasks(parseInt(activityId, 10));
            }
            this.bindEvents();
        },

        loadTasks: function (activityId) {
            var that = this;
            this.currentActivityId = activityId;
            common.request({
                url: '/activities/' + activityId + '/tasks',
                success: function (res) {
                    if (res.success) {
                        that.renderTasks(res.data);
                    }
                }
            });
        },

        renderTasks: function (list) {
            var that = this;
            var $tbody = $('#tasks-tbody');
            $tbody.empty();
            for (var i = 0; i < list.length; i++) {
                var t = list[i];
                var statusBadge = that.getStatusBadge(t.status);
                $tbody.append('<tr>' +
                    '<td>' + t.id + '</td>' +
                    '<td>' + t.title + '</td>' +
                    '<td>' + (t.assignee_name || 'Unassigned') + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + (t.due_date ? common.formatDate(t.due_date) : '-') + '</td>' +
                    '<td><button class="layui-btn layui-btn-xs" data-action="edit" data-id="' + t.id + '">Edit</button> ' +
                    '<button class="layui-btn layui-btn-xs layui-btn-warm" data-action="status" data-id="' + t.id + '">Status</button> ' +
                    '<button class="layui-btn layui-btn-xs layui-btn-danger" data-action="delete" data-id="' + t.id + '">Delete</button></td>' +
                    '</tr>');
            }
            if (list.length === 0) {
                $tbody.append('<tr><td colspan="6" style="text-align:center;color:#999;">No tasks</td></tr>');
            }
        },

        getStatusBadge: function (status) {
            var colors = { pending: 'layui-bg-orange', in_progress: 'layui-bg-blue', completed: 'layui-bg-green' };
            var labels = { pending: 'Pending', in_progress: 'In Progress', completed: 'Completed' };
            return '<span class="layui-badge ' + (colors[status] || '') + '">' + (labels[status] || status) + '</span>';
        },

        bindEvents: function () {
            var that = this;

            $('#btn-add-task').on('click', function () {
                that.showTaskForm();
            });

            $('#tasks-tbody').on('click', '[data-action]', function () {
                var action = $(this).attr('data-action');
                var id = $(this).attr('data-id');
                if (action === 'edit') that.showTaskForm(id);
                else if (action === 'status') that.showStatusChange(id);
                else if (action === 'delete') that.deleteTask(id);
            });
        },

        showTaskForm: function (taskId) {
            var content = '<form class="layui-form layui-form-pane">' +
                '<div class="layui-form-item"><label class="layui-form-label">Title</label><div class="layui-input-block"><input type="text" name="title" class="layui-input" required></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Description</label><div class="layui-input-block"><textarea name="description" class="layui-textarea"></textarea></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Assignee ID</label><div class="layui-input-block"><input type="number" name="assigned_to" class="layui-input"></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Due Date</label><div class="layui-input-block"><input type="text" name="due_date" class="layui-input" placeholder="YYYY-MM-DD"></div></div>' +
                '<div class="layui-form-item"><button class="layui-btn" lay-submit>Save</button></div></form>';
            layer.open({ type: 1, title: taskId ? 'Edit Task' : 'Add Task', content: content, area: ['400px', '400px'] });
        },

        showStatusChange: function (id) {
            layer.prompt({ title: 'New status (pending/in_progress/completed)' }, function (value, index) {
                common.request({
                    url: '/tasks/' + id + '/status',
                    method: 'PUT',
                    data: { status: value },
                    success: function () {
                        layer.msg('Status updated', { icon: 1 });
                    }
                });
                layer.close(index);
            });
        },

        deleteTask: function (id) {
            var that = this;
            layer.confirm('Delete this task?', { icon: 3 }, function (idx) {
                common.request({
                    url: '/tasks/' + id,
                    method: 'DELETE',
                    success: function () {
                        layer.msg('Task deleted', { icon: 1 });
                        that.loadTasks(that.currentActivityId);
                    }
                });
                layer.close(idx);
            });
        }
    };

    window.layui = window.layui || {};
    window.layui.tasks = tasks;

    exports('tasks', tasks);
});