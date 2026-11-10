/**
 * Shared v2 utility helpers, namespaced as window.PortalUtils so bare identifier
 * lookups can never be shadowed by /assets/scripts/utils.js (v1's global
 * utils, also loaded on every v2 page — see footer_scripts.php). Classic
 * <script> tags share one global lexical scope, so a plain top-level
 * `const hasRole` in one file silently wins over a later `window.hasRole = ...`
 * assignment in another; namespacing here avoids that collision entirely.
 *
 * v1's hasRole()/showAjaxLoader()/hideAjaxLoader() don't work under v2 URLs —
 * hasRole() derives the portal from location.pathname.split('/')[1], which is
 * "v2" here instead of "admin"; showAjaxLoader()/hideAjaxLoader() toggle
 * #modal_load, which doesn't exist in v2's layout (v2 uses #loadingOverlay).
 *
 * Depends on: jQuery, PortalAuth (portal-auth.js), toastr. Loaded in
 * shared/partials/head.php before any page-specific script.
 */
window.PortalUtils = (function ($) {
    function currentUser() {
        try { return (window.PortalAuth && PortalAuth.getUser()) || {}; } catch (e) { return {}; }
    }

    /** True if the current user has any of the given roles. */
    function hasRole(roles) {
        var user = currentUser();
        var userRoles = user.roles || (user.role ? [user.role] : []);
        return roles.some(function (r) { return userRoles.indexOf(r) !== -1; });
    }

    function showSuccess(message, title) {
        toastr.options.closeButton = true;
        toastr.success(message, title || 'Success');
    }

    function showError(message, title) {
        toastr.options.closeButton = true;
        toastr.error(message, title || 'Error');
    }

    function showWarning(message, title) {
        toastr.options.closeButton = true;
        toastr.warning(message, title || 'Warning');
    }

    /** SweetAlert error dialog (wraps global swalError from utils.js). */
    function showSwalError(message, title) {
        if (typeof swalError === 'function') {
            return swalError(message, title || 'Error');
        }
        window.alert(message);
    }

    /** SweetAlert info/notice dialog (wraps global swalAlert from utils.js). */
    function showSwalAlert(message, title, icon) {
        if (typeof swalAlert === 'function') {
            return swalAlert({
                title: title || '',
                text: message,
                icon: icon || 'info'
            });
        }
        window.alert(message);
    }

    function showValidationError(errors) {
        if (errors && typeof errors === 'object') {
            Object.keys(errors).forEach(function (field) {
                var messages = Array.isArray(errors[field]) ? errors[field] : [errors[field]];
                messages.forEach(function (message) { showError(message, 'Validation Error'); });
            });
        } else if (errors && typeof errors === 'string') {
            showError(errors, 'Validation Error');
        }
    }

    function showAjaxLoader() { $('#loadingOverlay').show(); }
    function hideAjaxLoader() { $('#loadingOverlay').hide(); }

    /**
     * Auto-show/hide #loadingOverlay for every $.ajax() call on the page, including
     * DataTables (which uses $.ajax internally for its `ajax: {...}` config) and
     * plain form-submit requests. jQuery's ajaxStart/ajaxStop fire once when the
     * count of in-flight requests goes 0->1 / 1->0, so concurrent requests don't
     * flicker the overlay on and off. A call that shouldn't trigger the overlay
     * (e.g. silent polling) can opt out with `$.ajax({..., global: false})`.
     */
    function attachGlobalLoader() {
        $(document).ajaxStart(showAjaxLoader).ajaxStop(hideAjaxLoader);
    }
    attachGlobalLoader();

    /**
     * Pace hooks XMLHttpRequest directly and still animates its top-of-page bar for
     * GET polls even when jQuery global:false suppresses #loadingOverlay. PortalApi
     * tags silent requests with ?_silent=1 — ignore those here once Pace has loaded.
     */
    $(function () {
        if (window.Pace && Pace.options && Pace.options.ajax) {
            Pace.options.ajax.ignoreURLs.push(/[?&]_silent=1(?:&|$)/);
        }
    });

    /**
     * Re-validates a Parsley-managed field after its value changes programmatically
     * (select2, datepicker, etc.) — but ONLY after the form has been submitted once
     * and this field already failed (`_failedOnce`). Matches Parsley's own contract:
     * errors appear on submit, then live-update on change to clear sticky messages.
     * Never triggers `input` (that can validate too early) and never validates an
     * untouched field.
     */
    function revalidateParsleyField($field) {
        if (typeof $field.parsley !== 'function') {
            return;
        }
        try {
            var fieldInstance = $field.parsley();
            if (!fieldInstance || !fieldInstance._failedOnce) {
                return;
            }
            fieldInstance.validate();
        } catch (e) { /* field not Parsley-managed */ }
    }

    /**
     * Re-validates a select2 field with Parsley the moment its value changes, so a
     * required dropdown's error message clears as soon as the user picks a value
     * instead of staying stuck until the next full form submit.
     *
     * Parsley only auto-attaches its own live-validation listener to a field AFTER
     * that field has failed validation once (e.g. on first submit attempt) — before
     * that, and unreliably even after depending on how Select2 dispatches its change
     * event, a manual re-check is the only consistent fix. Delegated on `document` so
     * it covers every select2 dropdown on the page, including ones added to a modal
     * after page load — no per-field wiring needed.
     *
     * Only re-validates after submit has already failed once (`_failedOnce`).
     */
    function attachSelect2ParsleySync() {
        $(document).on('change', 'select.select2-hidden-accessible', function () {
            revalidateParsleyField($(this));
        });
    }
    attachSelect2ParsleySync();

    /** File inputs fire `change`, not `input`, when a file is chosen — sync Parsley the same way. */
    function attachFileInputParsleySync() {
        $(document).on('change', 'input[type="file"]', function () {
            revalidateParsleyField($(this));
        });
    }
    attachFileInputParsleySync();

    /**
     * Clears stuck Parsley errors on jQuery UI datepicker fields after submit has
     * failed and the user then picks a date. jQuery UI sets the input value in
     * onSelect but does not fire a change/input event — this wraps datepicker init
     * globally so individual pages do not need per-field onSelect wiring.
     *
     * Deferred to DOM ready because datepicker loads in footer_scripts.php (after
     * portal-utils in head.php).
     */
    function attachDatepickerParsleySync() {
        $(function () {
            if (!$.fn.datepicker || !$.datepicker) {
                return;
            }

            var origDatepicker = $.fn.datepicker;
            $.fn.datepicker = function (option) {
                if (arguments.length === 1 && typeof option === 'object') {
                    var options = $.extend({}, option);
                    var userOnSelect = options.onSelect;
                    options.onSelect = function (dateText, inst) {
                        if ($.isFunction(userOnSelect)) {
                            userOnSelect.call(this, dateText, inst);
                        }
                        revalidateParsleyField($(this));
                    };
                    return origDatepicker.call(this, options);
                }
                return origDatepicker.apply(this, arguments);
            };
        });
    }
    attachDatepickerParsleySync();

    /**
     * Bootstrap 3 appends .modal-backdrop to body; modals inside .main-content sit
     * under the backdrop (screen dims, dialog invisible). Move each modal to body
     * on show and keep it above its backdrop — fixes Outreach Incomplete, Contact
     * Made, hub action modals, etc. without per-modal wiring.
     */
    function attachBootstrapModalStackingFix() {
        $(document).on('show.bs.modal', '.modal', function () {
            var $modal = $(this);
            if ($modal.parent()[0] !== document.body) {
                $modal.appendTo(document.body);
            }
        });
    }
    attachBootstrapModalStackingFix();

    /**
     * Show a Bootstrap 3 modal (selector or jQuery object). Stacking fix runs via
     * the global show.bs.modal / shown.bs.modal handlers above.
     */
    function showModal(modal) {
        var $modal = modal instanceof jQuery ? modal : $(modal);
        if (!$modal.length) {
            return;
        }
        $modal.modal('show');
    }

    /** Clear Parsley UI on one form element (never call .parsley() on a multi-element jQuery set). */
    function resetParsley($form) {
        if (!$form.length || typeof $form.parsley !== 'function') {
            return;
        }
        try {
            var parsley = $form.parsley();
            if (parsley && typeof parsley.reset === 'function') {
                parsley.reset();
            }
        } catch (e) { /* Parsley not initialized on this form yet */ }
    }

    /**
     * Reset all fields inside a form: native HTML reset, Select2 UI sync, Parsley errors.
     * Accepts one form or a jQuery collection of forms (e.g. $(modal).find('form')).
     * opts.after($form) — page-specific cleanup (dynamic rows, UI toggles, etc.).
     */
    function resetForm(form, opts) {
        opts = opts || {};
        var $form = form instanceof jQuery ? form : $(form);
        if (!$form.length) return;

        if ($form.length > 1) {
            $form.each(function () {
                resetForm($(this), opts);
            });
            return;
        }

        if (typeof opts.before === 'function') {
            opts.before($form);
        }

        var el = $form[0];
        if (el && typeof el.reset === 'function') {
            el.reset();
        }

        $form.find('select').each(function () {
            var $select = $(this);
            if ($select.data('select2') || $select.hasClass('select2-hidden-accessible')) {
                $select.trigger('change');
            }
        });

        resetParsley($form);

        $form.find('.errorMsg').hide();

        if (typeof opts.after === 'function') {
            opts.after($form);
        }
    }

    /** Reset form every time a Bootstrap 3 modal finishes closing. */
    function bindModalFormReset($modal, form, opts) {
        if (!$modal || !$modal.length) return;
        $modal.on('hidden.bs.modal', function () {
            resetForm(form, opts);
        });
    }

    /** Default display timezone — mirrors v1 GeneralClass::converToTz(..., 'America/Chicago', 'UTC'). */
    var DEFAULT_TIMEZONE = 'America/Chicago';

    /**
     * Parse API datetime strings. MySQL UTC values without a zone suffix are treated as UTC.
     * @returns {Date|null}
     */
    function parseUtcInstant(value) {
        if (value == null || value === '' || value === false) {
            return null;
        }
        if (value instanceof Date) {
            return isNaN(value.getTime()) ? null : value;
        }
        var s = String(value).trim();
        if (!s) {
            return null;
        }
        if (/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/.test(s) && !/[zZ]|[+-]\d{2}:?\d{2}$/.test(s)) {
            s = s.replace(' ', 'T') + 'Z';
        }
        var d = new Date(s);
        return isNaN(d.getTime()) ? null : d;
    }

    /**
     * Calendar date (DOB, stim start, Y-m-d fields) → m/d/Y without timezone shift.
     */
    function formatCalendarDate(value) {
        if (value == null || value === '' || value === false || value === '0000-00-00') {
            return '';
        }
        var s = String(value).trim();
        if (!s) {
            return '';
        }
        if (/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(s)) {
            return s;
        }
        var parts = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (parts) {
            return parts[2] + '/' + parts[3] + '/' + parts[1];
        }
        return s;
    }

    /** UTC timestamp → m/d/Y in the default display timezone (First RX Date, etc.). */
    function formatTzDate(value) {
        var d = parseUtcInstant(value);
        if (!d) {
            return '';
        }
        return new Intl.DateTimeFormat('en-US', {
            timeZone: DEFAULT_TIMEZONE,
            month: '2-digit',
            day: '2-digit',
            year: 'numeric'
        }).format(d);
    }

    /** UTC timestamp → m/d/Y h:mm AM/PM in the default display timezone (RX created, notes, etc.). */
    function formatTzDateTime(value) {
        var d = parseUtcInstant(value);
        if (!d) {
            return '';
        }
        return new Intl.DateTimeFormat('en-US', {
            timeZone: DEFAULT_TIMEZONE,
            month: '2-digit',
            day: '2-digit',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true
        }).format(d);
    }

    /**
     * Generic date formatter. kind: 'date' (default) | 'calendar' | 'dateTime'.
     */
    function formatDate(value, options) {
        var kind = (options && options.kind) || 'date';
        if (kind === 'calendar') {
            return formatCalendarDate(value);
        }
        if (kind === 'dateTime') {
            return formatTzDateTime(value);
        }
        return formatTzDate(value);
    }

    function formatDateTime(value) {
        return formatTzDateTime(value);
    }

    /**
     * Reads one value out of a page's persisted UI-state blob in localStorage.
     * A page keeps everything it wants to survive a reload (filters, panel
     * show/hide, dropdown choices, ...) as one JSON object under a single
     * `storageKey`; `path` walks into that object to the value you want, at
     * any depth: getPersistedState('pt_state', ['panels', 'woc_section']) is
     * the same idea as reading `state.panels.woc_section`. A plain string
     * also works for a one-level path: getPersistedState('pt_state', 'pod').
     * Returns undefined if any part of the path is missing or localStorage
     * is unavailable.
     */
    function getPersistedState(storageKey, path) {
        var state;
        try {
            state = JSON.parse(localStorage.getItem(storageKey)) || {};
        } catch (e) {
            return undefined;
        }
        var keys = Array.isArray(path) ? path : [path];
        var node = state;
        for (var i = 0; i < keys.length; i++) {
            if (node === null || typeof node !== 'object') {
                return undefined;
            }
            node = node[keys[i]];
        }
        return node;
    }

    /**
     * Writes one value into a page's persisted UI-state blob at `path` (see
     * getPersistedState) — creating any intermediate objects along the way.
     * No-ops silently if localStorage is unavailable (private browsing,
     * quota) — the value just won't persist.
     */
    function setPersistedState(storageKey, path, value) {
        var state;
        try {
            state = JSON.parse(localStorage.getItem(storageKey)) || {};
        } catch (e) {
            state = {};
        }
        var keys = Array.isArray(path) ? path : [path];
        var node = state;
        for (var i = 0; i < keys.length - 1; i++) {
            if (typeof node[keys[i]] !== 'object' || node[keys[i]] === null) {
                node[keys[i]] = {};
            }
            node = node[keys[i]];
        }
        node[keys[keys.length - 1]] = value;
        try {
            localStorage.setItem(storageKey, JSON.stringify(state));
        } catch (e) {
            // localStorage unavailable — value just won't persist
        }
    }

    /**
     * Returns a section id from the URL hash (#in_process_section) or, as a
     * fallback, the legacy v1 ?action= query param.
     */
    function getUrlHashSectionId() {
        var hash = (window.location.hash || '').replace(/^#/, '').trim();
        if (hash) {
            return hash;
        }
        try {
            var action = new URL(window.location.href).searchParams.get('action');
            return action ? String(action).trim() : '';
        } catch (e) {
            return '';
        }
    }

    /**
     * Scroll to a DOM section by id with a fixed header offset.
     * @returns {boolean} true if the element existed and scroll started
     */
    function scrollToSection(sectionId, offset) {
        offset = offset == null ? 80 : offset;
        if (!sectionId) {
            return false;
        }
        var $el = $('#' + sectionId);
        if (!$el.length) {
            return false;
        }
        $('html, body').animate({ scrollTop: $el.offset().top - offset }, 500);
        return true;
    }

    /**
     * In-memory TTL cache for frontend data (not persisted across reloads).
     *
     * Use PortalUtils.ttlCache for ad-hoc shared caching, or createTtlCache()
     * for an isolated store (e.g. per module / prefix).
     *
     * get() returns undefined on miss or expiry; any other value (including null)
     * is a cache hit.
     *
     * @param {{defaultTtlMs?: number, prefix?: string}} [options]
     * @returns {{get: Function, set: Function, has: Function, remove: Function, clear: Function}}
     */
    function createTtlCache(options) {
        options = options || {};
        var defaultTtlMs = options.defaultTtlMs != null ? options.defaultTtlMs : 30000;
        var prefix = options.prefix != null ? String(options.prefix) : '';
        var store = Object.create(null);

        function storageKey(key) {
            return prefix + String(key);
        }

        function get(key) {
            var k = storageKey(key);
            var entry = store[k];
            if (!entry) {
                return undefined;
            }
            if (Date.now() > entry.expiresAt) {
                delete store[k];
                return undefined;
            }
            return entry.value;
        }

        function set(key, value, ttlMs) {
            var ttl = ttlMs != null ? ttlMs : defaultTtlMs;
            store[storageKey(key)] = {
                value: value,
                expiresAt: Date.now() + Math.max(0, ttl)
            };
        }

        function has(key) {
            return get(key) !== undefined;
        }

        function remove(key) {
            delete store[storageKey(key)];
        }

        function clear() {
            store = Object.create(null);
        }

        return {
            get: get,
            set: set,
            has: has,
            remove: remove,
            clear: clear
        };
    }

    /** Shared in-memory TTL cache (default 30s). Prefer createTtlCache() for module-scoped stores. */
    var ttlCache = createTtlCache({ defaultTtlMs: 30000 });

    return {
        hasRole: hasRole,
        showSuccess: showSuccess,
        showError: showError,
        showWarning: showWarning,
        showSwalError: showSwalError,
        showSwalAlert: showSwalAlert,
        showValidationError: showValidationError,
        showAjaxLoader: showAjaxLoader,
        hideAjaxLoader: hideAjaxLoader,
        resetForm: resetForm,
        bindModalFormReset: bindModalFormReset,
        showModal: showModal,
        formatDate: formatDate,
        formatDateTime: formatDateTime,
        formatCalendarDate: formatCalendarDate,
        formatTzDate: formatTzDate,
        formatTzDateTime: formatTzDateTime,
        getPersistedState: getPersistedState,
        setPersistedState: setPersistedState,
        getUrlHashSectionId: getUrlHashSectionId,
        scrollToSection: scrollToSection,
        createTtlCache: createTtlCache,
        ttlCache: ttlCache
    };
})(jQuery);
