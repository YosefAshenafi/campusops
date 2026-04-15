/**
 * CampusOps Activities Module
 * Activity CRUD, lifecycle management, and signups.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var form = layui.form;
    var common = layui.common;

    var activities = {
        currentPage: 1,
        pageSize: 20,
        currentActivity: null,
        userInfo: null,

        /**
         * Initialize the activities list view.
         */
        initList: function () {
            this.loadActivities();
            this.bindListEvents();
        },

        /**
         * Load activities with current filters.
         */
        loadActivities: function (page) {
            page = page || 1;
            var that = this;
            var params = {
                page: page,
                limit: that.pageSize,
                state: $('#filter-state').val() || '',
                keyword: $('#filter-keyword').val() || ''
            };

            common.request({
                url: '/activities',
                data: params,
                success: function (res) {
                    if (res.success) {
                        that.renderTable(res.data.list);
                        that.renderPagination(res.data.total, res.data.page, res.data.limit);
                    }
                }
            });
        },

        /**
         * Render the activities table.
         */
        renderTable: function (list) {
            var $tbody = $('#activities-tbody');
            $tbody.empty();
            if (!list || list.length === 0) {
                $tbody.append('<tr><td colspan="8" style="text-align:center;color:#999;">No activities</td></tr>');
                return;
            }
            var that = this;
            for (var i = 0; i < list.length; i++) {
                var v = list[i];
                var signup_start = v.signup_start ? common.formatDateTime(v.signup_start) : '-';
                var signup_end = v.signup_end ? common.formatDateTime(v.signup_end) : '-';

                var row = '<tr>' +
                    '<td>' + v.id + '</td>' +
                    '<td>' + that.escapeHtml(v.title) + '</td>' +
                    '<td>' + that.getStateBadge(v.state) + '</td>' +
                    '<td>' + v.version_number + '</td>' +
                    '<td>' + v.current_signups + '</td>' +
                    '<td>' + (v.max_headcount > 0 ? v.max_headcount : 'Unlimited') + '</td>' +
                    '<td>' + signup_start + ' - ' + signup_end + '</td>' +
                    '<td>' +
                    '<button class="layui-btn layui-btn-xs" data-action="view" data-id="' + v.id + '">View</button>' +
                    '<button class="layui-btn layui-btn-xs layui-btn-normal" data-action="edit" data-id="' + v.id + '">Edit</button>' +
                    '</td>' +
                    '</tr>';
                $tbody.append(row);
            }
        },

        /**
         * Render pagination.
         */
        renderPagination: function (total, page, limit) {
            var totalPages = Math.ceil(total / limit);
            var paginationHtml = '<span>Total: ' + total + '</span> ';
            paginationHtml += '<button class="layui-btn layui-xs" ' + (page > 1 ? 'onclick="layui.activities.loadActivities(' + (page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            paginationHtml += '<span>Page ' + page + ' of ' + totalPages + '</span> ';
            paginationHtml += '<button class="layui-btn layui-xs" ' + (page < totalPages ? 'onclick="layui.activities.loadActivities(' + (page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#activities-pagination').html(paginationHtml);
        },

        /**
         * Bind list view events.
         */
        bindListEvents: function () {
            var that = this;

            $('#btn-search').on('click', function () {
                that.loadActivities(1);
            });

            $('#filter-state').on('change', function () {
                that.loadActivities(1);
            });

            $('#btn-add-activity').on('click', function () {
                that.showForm();
            });

            $('#activities-tbody').on('click', '[data-action]', function () {
                var action = $(this).attr('data-action');
                var id = $(this).attr('data-id');
                if (action === 'view') {
                    that.showDetail(id);
                } else if (action === 'edit') {
                    that.showForm(id);
                }
            });

            form.render('select', 'activity-filters');
        },

        /**
         * Show activity detail.
         */
        showDetail: function (activityId) {
            var that = this;
            that.userInfo = common.getUser();

            var $container = $('#app-content-inner');
            $container.find('.activities-list-view').hide();
            $container.find('.activity-form-view').hide();

            function onDetailReady() {
                $container.find('.activity-detail-view').show();
                that.bindDetailEvents();
                common.request({
                    url: '/activities/' + activityId,
                    success: function (res) {
                        if (res.success) {
                            that.currentActivity = res.data;
                            that.renderDetail(res.data);
                        }
                    }
                });
            }

            if (!$container.find('.activity-detail-view').length) {
                $('<div>').appendTo($container).load('/src/views/activities/detail.html', onDetailReady);
            } else {
                onDetailReady();
            }
        },

        /**
         * Render activity detail.
         */
        renderDetail: function (activity) {
            var that = this;
            $('#activity-id').val(activity.id);
            $('#activity-title').text(activity.title);
            $('#detail-title').text(activity.title);
            $('#detail-body').text(activity.body || '-');
            $('#detail-tags').html(this.renderTags(activity.tags));
            $('#detail-max-headcount').text(activity.max_headcount > 0 ? activity.max_headcount : 'Unlimited');
            $('#detail-supplies').html(this.renderTags(activity.required_supplies));

            var signupWindow = '-';
            if (activity.signup_start || activity.signup_end) {
                signupWindow = (activity.signup_start ? common.formatDateTime(activity.signup_start) : 'Open') + 
                    ' - ' + 
                    (activity.signup_end ? common.formatDateTime(activity.signup_end) : 'Open');
            }
            $('#detail-signup-window').text(signupWindow);

            $('#activity-state-badge').html(that.getStateBadge(activity.state));

            // Eligibility tags
            var eligibilityTags = activity.eligibility_tags || [];
            if (eligibilityTags.length > 0) {
                $('#detail-eligibility-tags').html(this.renderTags(eligibilityTags));
            } else {
                $('#detail-eligibility-tags').html('<span style="color:#999;">No eligibility restrictions</span>');
            }

            // Lifecycle transition timestamps
            $('#ts-published').text(activity.published_at ? common.formatDateTime(activity.published_at) : '-');
            $('#ts-started').text(activity.started_at ? common.formatDateTime(activity.started_at) : '-');
            $('#ts-completed').text(activity.completed_at ? common.formatDateTime(activity.completed_at) : '-');
            $('#ts-archived').text(activity.archived_at ? common.formatDateTime(activity.archived_at) : '-');

            this.loadSignups(activity.id);
            this.renderActions(activity.state, activity.current_signups, activity.max_headcount);
        },

        /**
         * Load and render signups.
         */
        loadSignups: function (activityId) {
            var that = this;
            common.request({
                url: '/activities/' + activityId + '/signups',
                success: function (res) {
                    if (res.success) {
                        that.renderSignups(res.data);
                    }
                }
            });
        },

        /**
         * Render signups list.
         */
        renderSignups: function (signups) {
            var html = '<table class="layui-table"><thead><tr><th>ID</th><th>User</th><th>Status</th><th>Signed Up</th></tr></thead><tbody>';
            if (!signups || signups.length === 0) {
                html += '<tr><td colspan="4" style="text-align:center;color:#999;">No signups yet</td></tr>';
            } else {
                for (var i = 0; i < signups.length; i++) {
                    var s = signups[i];
                    var statusBadge = s.status === 'confirmed' 
                        ? '<span class="layui-badge layui-bg-green">Confirmed</span>'
                        : '<span class="layui-badge layui-bg-orange">Pending Ack</span>';
                    html += '<tr><td>' + s.id + '</td><td>' + s.username + '</td><td>' + statusBadge + '</td><td>' + common.formatDateTime(s.created_at) + '</td></tr>';
                }
            }
            html += '</tbody></table>';
            $('#detail-signups').html(html);
        },

        /**
         * Render action buttons based on state and user role.
         */
        renderActions: function (state, currentSignups, maxHeadcount) {
            var $actions = $('#activity-actions');
            $actions.hide();

            var isAdmin = this.userInfo.role === 'administrator' || this.userInfo.role === 'operations_staff';
            var isFull = maxHeadcount > 0 && currentSignups >= maxHeadcount;

            if (!isAdmin) {
                if (state === 'published' && !isFull) {
                    $('#btn-signup').show();
                    $('#btn-cancel-signup').hide();
                    $actions.show();
                }
                return;
            }

            $actions.show();

            $('#btn-publish').hide();
            $('#btn-start').hide();
            $('#btn-complete').hide();
            $('#btn-archive').hide();
            $('#btn-signup').hide();
            $('#btn-cancel-signup').hide();

            if (state === 'draft') {
                $('#btn-publish').show();
            } else if (state === 'published') {
                $('#btn-start').show();
            } else if (state === 'in_progress') {
                $('#btn-complete').show();
            } else if (state === 'completed') {
            }
            $('#btn-archive').show();
        },

        /**
         * Bind detail view events.
         */
        bindDetailEvents: function () {
            var that = this;

            $('#btn-edit').off('click').on('click', function () {
                that.showForm(that.currentActivity.id);
            });

            $('#btn-versions').off('click').on('click', function () {
                that.showVersions(that.currentActivity.id);
            });

            $('#btn-changelog').off('click').on('click', function () {
                that.showChangeLog(that.currentActivity.id);
            });

            $('#btn-publish').off('click').on('click', function () {
                that.transitionActivity(that.currentActivity.id, 'publish');
            });

            $('#btn-start').off('click').on('click', function () {
                that.transitionActivity(that.currentActivity.id, 'start');
            });

            $('#btn-complete').off('click').on('click', function () {
                that.transitionActivity(that.currentActivity.id, 'complete');
            });

            $('#btn-archive').off('click').on('click', function () {
                that.transitionActivity(that.currentActivity.id, 'archive');
            });

            $('#btn-signup').off('click').on('click', function () {
                that.signupActivity(that.currentActivity.id);
            });

            $('#btn-cancel-signup').off('click').on('click', function () {
                that.cancelSignup(that.currentActivity.id);
            });
        },

        /**
         * Transition activity state.
         */
        transitionActivity: function (id, action) {
            var that = this;
            layer.confirm('Are you sure you want to ' + action + ' this activity?', { icon: 3, btn: ['OK', 'Cancel'] }, function (idx) {
                common.request({
                    url: '/activities/' + id + '/' + action,
                    method: 'POST',
                    success: function (res) {
                        layer.msg('Activity ' + action + 'd', { icon: 1 });
                        that.showDetail(id);
                    }
                });
                layer.close(idx);
            });
        },

        /**
         * Sign up for activity.
         */
        signupActivity: function (id) {
            var that = this;
            common.request({
                url: '/activities/' + id + '/signups',
                method: 'POST',
                success: function (res) {
                    layer.msg('Signed up successfully', { icon: 1 });
                    that.showDetail(id);
                }
            });
        },

        /**
         * Cancel signup - finds the user's signup_id first, then sends DELETE with it.
         */
        cancelSignup: function (id) {
            var that = this;
            var userInfo = common.getUser();
            // First fetch signups to find the user's own signup_id
            common.request({
                url: '/activities/' + id + '/signups',
                success: function (res) {
                    if (res.success && res.data) {
                        var mySignup = null;
                        for (var i = 0; i < res.data.length; i++) {
                            if (res.data[i].user_id === userInfo.id) {
                                mySignup = res.data[i];
                                break;
                            }
                        }
                        if (mySignup) {
                            common.request({
                                url: '/activities/' + id + '/signups/' + mySignup.id,
                                method: 'DELETE',
                                success: function () {
                                    layer.msg('Signup cancelled', { icon: 1 });
                                    that.showDetail(id);
                                }
                            });
                        } else {
                            layer.msg('No active signup found', { icon: 2 });
                        }
                    }
                }
            });
        },

        /**
         * Show versions modal.
         */
        showVersions: function (id) {
            common.request({
                url: '/activities/' + id + '/versions',
                success: function (res) {
                    var html = '<table class="layui-table"><thead><tr><th>Version</th><th>Title</th><th>State</th><th>Created</th></tr></thead><tbody>';
                    for (var i = 0; i < res.data.length; i++) {
                        var v = res.data[i];
                        html += '<tr><td>' + v.version_number + '</td><td>' + v.title + '</td><td>' + v.state + '</td><td>' + common.formatDateTime(v.created_at) + '</td></tr>';
                    }
                    html += '</tbody></table>';
                    layer.open({
                        type: 1,
                        title: 'Version History',
                        content: '<div style="padding:20px;">' + html + '</div>',
                        area: ['600px', '400px']
                    });
                }
            });
        },

        /**
         * Show change log modal.
         */
        showChangeLog: function (id) {
            common.request({
                url: '/activities/' + id + '/change-log',
                success: function (res) {
                    var html = '';
                    for (var i = 0; i < res.data.length; i++) {
                        var log = res.data[i];
                        html += '<p><strong>v' + log.from_version + ' → v' + log.to_version + '</strong> (' + common.formatDateTime(log.created_at) + ')</p>';
                        var changes = log.changes;
                        for (var key in changes) {
                            var oldVal = typeof changes[key].old === 'object' ? JSON.stringify(changes[key].old) : changes[key].old;
                            var newVal = typeof changes[key].new === 'object' ? JSON.stringify(changes[key].new) : changes[key].new;
                            html += '<div class="changelog-field">';
                            html += '<span class="field-name">' + key + ':</span>';
                            html += '<span class="changelog-old">' + oldVal + '</span>';
                            html += '<span class="changelog-arrow">&rarr;</span>';
                            html += '<span class="changelog-new">' + newVal + '</span>';
                            html += '</div>';
                        }
                    }
                    if (!html) html = 'No changes yet';
                    layer.open({
                        type: 1,
                        title: 'Change Log',
                        content: '<div style="padding:20px;">' + html + '</div>',
                        area: ['500px', '400px']
                    });
                }
            });
        },

        /**
         * Show activity form (create or edit).
         */
        showForm: function (activityId) {
            var that = this;
            var isEdit = !!activityId;

            var $container = $('#app-content-inner');
            $container.find('.activities-list-view').hide();
            $container.find('.activity-detail-view').hide();

            function onFormReady() {
                $('#form-title').text(isEdit ? 'Edit Activity' : 'Create Activity');
                $('#btn-submit').text(isEdit ? 'Update' : 'Create');

                if (isEdit) {
                    common.request({
                        url: '/activities/' + activityId,
                        success: function (res) {
                            if (res.success) {
                                that.fillForm(res.data);
                            }
                        }
                    });
                } else {
                    that.resetForm();
                }

                $container.find('.activity-form-view').show();
                form.render();
                that.bindFormEvents();
            }

            if (!$container.find('.activity-form-view').length) {
                $('<div>').appendTo($container).load('/src/views/activities/form.html', onFormReady);
            } else {
                onFormReady();
            }
        },

        /**
         * Initialize form view (called by form.html's embedded script on load).
         */
        initForm: function () {
            form.render();
        },

        /**
         * Fill form with activity data.
         */
        fillForm: function (activity) {
            $('#activity-id').val(activity.id);
            $('#input-title').val(activity.title);
            $('#input-body').val(activity.body);
            $('#input-tags').val((activity.tags || []).join(', '));
            $('#input-max-headcount').val(activity.max_headcount);
            $('#input-signup-start').val(activity.signup_start || '');
            $('#input-signup-end').val(activity.signup_end || '');
            $('#input-eligibility-tags').val((activity.eligibility_tags || []).join(', '));
            $('#input-supplies').val((activity.required_supplies || []).join(', '));
        },

        /**
         * Reset form.
         */
        resetForm: function () {
            $('#activity-id').val('');
            $('#activity-form')[0].reset();
        },

        /**
         * Bind form events.
         */
        bindFormEvents: function () {
            var that = this;

            form.on('submit(submit-activity)', function (data) {
                that.saveActivity(data.field);
                return false;
            });

            $('#btn-cancel').off('click').on('click', function () {
                $('#app-content-inner').find('.activity-form-view').hide();
                $('#app-content-inner').find('.activities-list-view').show();
            });
        },

        /**
         * Save activity (create or update).
         */
        saveActivity: function (data) {
            var that = this;
            var isEdit = !!data.id;
            var url = '/activities' + (isEdit ? '/' + data.id : '');
            var method = isEdit ? 'PUT' : 'POST';

            if (data.tags) {
                data.tags = data.tags.split(',').map(function(t) { return t.trim(); }).filter(Boolean);
            }
            if (data.eligibility_tags) {
                data.eligibility_tags = data.eligibility_tags.split(',').map(function(t) { return t.trim(); }).filter(Boolean);
            }
            if (data.required_supplies) {
                data.required_supplies = data.required_supplies.split(',').map(function(t) { return t.trim(); }).filter(Boolean);
            }

            common.request({
                url: url,
                method: method,
                data: data,
                success: function (res) {
                    layer.msg('Activity saved', { icon: 1 });
                    $('#app-content-inner').find('.activity-form-view').hide();
                    $('#app-content-inner').find('.activities-list-view').show();
                    that.loadActivities(1);
                }
            });
        },

        /**
         * Get state badge HTML.
         */
        getStateBadge: function (state) {
            var colors = {
                draft: 'layui-bg-gray',
                published: 'layui-bg-blue',
                in_progress: 'layui-bg-green',
                completed: 'layui-bg-orange',
                archived: 'layui-bg-black'
            };
            var labels = {
                draft: 'Draft',
                published: 'Published',
                in_progress: 'In Progress',
                completed: 'Completed',
                archived: 'Archived'
            };
            var color = colors[state] || 'layui-bg-gray';
            var label = labels[state] || state;
            return '<span class="layui-badge ' + color + '">' + label + '</span>';
        },

        /**
         * Render tags.
         */
        renderTags: function (tags) {
            if (!tags || !tags.length) return '-';
            var html = '';
            for (var i = 0; i < tags.length; i++) {
                html += '<span class="layui-badge layui-bg-gray" style="margin-right:5px;">' + tags[i] + '</span>';
            }
            return html;
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    window.layui = window.layui || {};
    window.layui.activities = activities;

    exports('activities', activities);
});
