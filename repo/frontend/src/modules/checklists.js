/**
 * CampusOps Checklists Module
 * Checklist and checklist item management.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var checklists = {
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
            var that = this;
            common.request({
                url: '/activities/' + activityId + '/checklists',
                success: function (res) {
                    if (res.success) {
                        that.render(res.data);
                    }
                }
            });
        },

        render: function (list) {
            var that = this;
            var $container = $('#checklists-container');
            $container.empty();

            if (!list || list.length === 0) {
                $container.html('<div style="text-align:center;padding:30px;color:#999;">No checklists</div>');
                return;
            }

            for (var i = 0; i < list.length; i++) {
                var cl = list[i];
                var itemsHtml = '';
                var items = cl.items || [];
                for (var j = 0; j < items.length; j++) {
                    var item = items[j];
                    var checked = item.completed ? 'checked' : '';
                    itemsHtml += '<div class="checklist-item" style="padding:4px 0;">' +
                        '<input type="checkbox" ' + checked + ' data-checklist-id="' + cl.id + '" data-item-id="' + item.id + '"> ' +
                        '<span style="' + (item.completed ? 'text-decoration:line-through;color:#999;' : '') + '">' + item.label + '</span>' +
                        (item.completed && item.completed_at ? ' <small style="color:#999;">(' + common.formatDateTime(item.completed_at) + ')</small>' : '') +
                        '</div>';
                }

                var html = '<div class="layui-card" style="margin-bottom:12px;" data-checklist-id="' + cl.id + '">' +
                    '<div class="layui-card-header">' +
                    '<strong>' + cl.title + '</strong>' +
                    '<span style="float:right;">' +
                    '<button class="layui-btn layui-btn-xs" data-action="edit" data-id="' + cl.id + '">Edit</button> ' +
                    '<button class="layui-btn layui-btn-xs layui-btn-danger" data-action="delete" data-id="' + cl.id + '">Delete</button>' +
                    '</span>' +
                    '</div>' +
                    '<div class="layui-card-body">' +
                    (itemsHtml || '<span style="color:#999;">No items</span>') +
                    '</div></div>';

                $container.append(html);
            }
        },

        bindEvents: function () {
            var that = this;

            $('#btn-add-checklist').on('click', function () {
                that.showForm();
            });

            $('#checklists-container').on('change', 'input[type="checkbox"]', function () {
                var checklistId = $(this).data('checklist-id');
                var itemId = $(this).data('item-id');
                that.toggleItem(checklistId, itemId);
            });

            $('#checklists-container').on('click', '[data-action]', function () {
                var action = $(this).attr('data-action');
                var id = $(this).attr('data-id');
                if (action === 'edit') {
                    that.showForm(parseInt(id, 10));
                } else if (action === 'delete') {
                    that.deleteChecklist(parseInt(id, 10));
                }
            });
        },

        showForm: function (checklistId) {
            var title = checklistId ? 'Edit Checklist' : 'Add Checklist';
            var content = '<form class="layui-form layui-form-pane" style="padding:20px;">' +
                '<div class="layui-form-item">' +
                '<label class="layui-form-label">Title</label>' +
                '<div class="layui-input-block"><input type="text" name="title" class="layui-input" required></div>' +
                '</div>' +
                '<div class="layui-form-item">' +
                '<label class="layui-form-label">Items</label>' +
                '<div class="layui-input-block"><textarea name="items" class="layui-textarea" placeholder="One item per line"></textarea></div>' +
                '</div>' +
                '<div class="layui-form-item">' +
                '<button class="layui-btn" lay-submit lay-filter="checklist-form">Save</button>' +
                '</div></form>';

            var that = this;
            layer.open({
                type: 1,
                title: title,
                content: content,
                area: ['450px', '380px'],
                success: function (layerElem) {
                    layui.form.render();
                    layui.form.on('submit(checklist-form)', function (data) {
                        var items = data.field.items
                            ? data.field.items.split('\n').map(function (s) { return s.trim(); }).filter(Boolean)
                            : [];
                        var payload = { title: data.field.title, items: items };
                        var url = checklistId
                            ? '/checklists/' + checklistId
                            : '/activities/' + that.currentActivityId + '/checklists';
                        var method = checklistId ? 'PUT' : 'POST';
                        common.request({
                            url: url,
                            method: method,
                            data: payload,
                            success: function (res) {
                                if (res.success) {
                                    layer.closeAll();
                                    layer.msg('Saved', { icon: 1 });
                                    that.load(that.currentActivityId);
                                }
                            }
                        });
                        return false;
                    });
                }
            });
        },

        toggleItem: function (checklistId, itemId) {
            var that = this;
            common.request({
                url: '/checklists/' + checklistId + '/items/' + itemId + '/complete',
                method: 'PUT',
                success: function (res) {
                    if (res.success) {
                        that.load(that.currentActivityId);
                    }
                }
            });
        },

        deleteChecklist: function (id) {
            var that = this;
            layer.confirm('Delete this checklist and all its items?', { icon: 3 }, function (idx) {
                common.request({
                    url: '/checklists/' + id,
                    method: 'DELETE',
                    success: function (res) {
                        if (res.success) {
                            layer.msg('Deleted', { icon: 1 });
                            that.load(that.currentActivityId);
                        }
                    }
                });
                layer.close(idx);
            });
        }
    };

    window.layui = window.layui || {};
    window.layui.checklists = checklists;
    exports('checklists', checklists);
});
