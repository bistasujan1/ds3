/**
 * Shared AJAX helper for all v2 portals — thin wrapper over $.ajax so every
 * page stops hand-rolling the same url/contentType/JSON.stringify/error-toast
 * boilerplate. Auth headers are already attached globally by
 * PortalAuth.attachAjaxHeaders() ($.ajaxSetup) — this file does not touch auth.
 *
 * Depends on: jQuery, APIURL (shared/partials/head.php), toastr (optional,
 * loaded later in footer_scripts.php — fine, since calls fire after DOM ready).
 *
 * Loaded on every v2 page, right after portal-auth.js.
 */
(function ($) {
    'use strict';

    function isSilentRequest(opts) {
        return opts.silent === true || opts.global === false;
    }

    /** Pace tracks GET XHRs independently of jQuery's global flag — tag silent polls. */
    function withSilentParam(url, silent) {
        if (!silent) {
            return url;
        }
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + '_silent=1';
    }

    function errorHandler(opts) {
        return function (xhr) {
            var body = xhr.responseJSON || {};
            if (body.errors && typeof window.PortalUtils !== 'undefined' && PortalUtils.showValidationError) {
                PortalUtils.showValidationError(body.errors);
                if (opts.error) {
                    opts.error(xhr, body.message, body);
                }
                return;
            }
            var msg = body.message ? body.message : (opts.errorMessage || 'Request failed');
            if (opts.error) {
                opts.error(xhr, msg);
            } else if (typeof toastr !== 'undefined') {
                toastr.error(msg);
            }
        };
    }

    var PortalApi = {
        /**
         * GET request with query-string params.
         * @param {string} endpoint  Path relative to APIURL, e.g. 'admin/whitelist-phones'.
         * @param {object} [params]  Query params.
         * @param {object} [opts]    {success, error, complete, errorMessage, global}
         *   opts.global: false suppresses the page-wide ajaxStart/ajaxStop loading
         *   overlay (portal-utils.js) — use for silent polling so background refreshes
         *   don't flash a full-page loader on every tick.
         */
        get: function (endpoint, params, opts) {
            opts = opts || {};
            var silent = isSilentRequest(opts);
            return $.ajax({
                url: withSilentParam(APIURL + endpoint, silent),
                method: 'GET',
                data: params || {},
                global: !opts.silent && opts.global !== false,
                success: opts.success,
                error: errorHandler(opts),
                complete: opts.complete
            });
        },

        /**
         * JSON-body request. method is POST/PUT/PATCH/DELETE. data may be null (e.g. DELETE)
         * or a FormData instance (file upload — sent as-is, no JSON encoding).
         * opts.global: false suppresses the page-wide loading overlay (see get() above).
         */
        request: function (method, endpoint, data, opts) {
            opts = opts || {};
            var silent = isSilentRequest(opts);
            var settings = {
                url: withSilentParam(APIURL + endpoint, silent),
                method: method,
                global: !silent,
                success: opts.success,
                error: errorHandler(opts),
                complete: opts.complete
            };
            if (data instanceof FormData) {
                settings.data = data;
                settings.processData = false;
                settings.contentType = false;
            } else if (data !== undefined && data !== null) {
                settings.contentType = 'application/json';
                settings.data = JSON.stringify(data);
            }
            return $.ajax(settings);
        },

        post: function (endpoint, data, opts) { return this.request('POST', endpoint, data, opts); },
        put: function (endpoint, data, opts) { return this.request('PUT', endpoint, data, opts); },
        patch: function (endpoint, data, opts) { return this.request('PATCH', endpoint, data, opts); },
        del: function (endpoint, opts) { return this.request('DELETE', endpoint, null, opts); },

        /** True if response carries the expected code, e.g. PortalApi.ok(res, 201). */
        ok: function (res, code) {
            return !!res && res.code === code;
        },

        /** Toggle a submit button between idle text and a loading spinner. */
        setBtnLoading: function (btn, textEl, loaderEl, loading) {
            $(btn).prop('disabled', loading);
            $(textEl).toggle(!loading);
            $(loaderEl).toggle(loading);
        }
    };

    window.PortalApi = PortalApi;

}(jQuery));
