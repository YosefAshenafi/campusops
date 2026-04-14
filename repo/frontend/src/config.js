/**
 * CampusOps Global Configuration
 */
window.CampusOps = window.CampusOps || {};

CampusOps.config = {
    // API base URL
    apiBase: '/api/v1',

    // Application name
    appName: 'CampusOps',

    // Date/time format
    dateFormat: 'MM/DD/YYYY',
    timeFormat: 'hh:mm A',
    dateTimeFormat: 'MM/DD/YYYY hh:mm A',

    // Pagination defaults
    pageSize: 20,
    pageSizes: [10, 20, 50, 100],

    // File upload limits
    maxFileSize: 10 * 1024 * 1024, // 10 MB
    allowedFileTypes: ['jpg', 'jpeg', 'png', 'pdf'],
};

// Layui configuration
layui.config({
    base: '/src/modules/'
});
