/**
 * DS3 Admin Portal — main JS loaded on every authenticated page.
 *
 * Requires: jQuery, PortalAuth (portal-auth.js), PortalApi (portal-api.js)
 * All loaded globally via shared/partials/head.php before this file runs.
 */
(function ($) {
    'use strict';

    // Guard auth — redirects to BASEURL + '/login' if no token stored
    if (!PortalAuth.requireAuth()) return;

    // Wire Authorization + User-Data headers to all AJAX
    PortalAuth.attachAjaxHeaders();

    $(document).ready(function () {
        // Sidebar navigation — navigate on any button with a data-href
        $('#sidebar').on('click', 'button[data-href]', function () {
            window.location.href = $(this).data('href');
        });

        // Sidebar collapsible groups — toggle open/closed
        $('#sidebar').on('click', '.modhead', function () {
            $(this).closest('.mod').toggleClass('open');
        });
    });

}(jQuery));
