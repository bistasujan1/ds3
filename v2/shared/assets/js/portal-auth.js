/**
 * Shared client-side auth for all v2 portals — token lives in localStorage only
 * (matches v1's storage pattern). No PHP session anywhere.
 *
 * Depends on: jQuery, PORTAL, BASEURL, APIURL, PORTAL_API_SCOPE
 * (set in shared/partials/head.php and shared/layouts/auth.php).
 *
 * Loaded on every v2 page (both layouts) — provides window.PortalAuth.
 */
(function ($) {
    'use strict';

    function storageKey(name) {
        return PORTAL + '_' + name;
    }

    function apiScope(override) {
        return override || window.PORTAL_API_SCOPE || PORTAL;
    }

    function sessionExpiredKey() {
        return storageKey('session_expired');
    }

    /**
     * Resolves the post-login landing URL for the current portal. Defaults to
     * '/dashboard'. A portal without a dashboard page (e.g. pharmacy) can
     * override by setting window.PORTAL_HOME_URL_RESOLVER = function (user) {
     * return '/some/page'; } before this script runs its login/redirect flows.
     */
    function homeUrl(user) {
        if (typeof window.PORTAL_HOME_URL_RESOLVER === 'function') {
            return BASEURL + window.PORTAL_HOME_URL_RESOLVER(user || null);
        }
        return BASEURL + '/dashboard';
    }

    // v1 issues a Bearer token under its own portal-branded localStorage keys
    // (same SHA256 scheme + user.token DB column v2 verifies against — see
    // api/v2/utils/Auth.php). Fall back to it so a v1 session works on v2
    // without forcing re-login. Patient excluded: v1 patient uses a separate
    // JWT scheme and v2 has no patient auth backend yet.
    var V1_KEY_PREFIX = { admin: 'assist', pharmacy: 'pha', clinic: 'nurse', partner: 'par' };

    function v1Fallback(suffix) {
        var prefix = V1_KEY_PREFIX[PORTAL];
        return prefix ? localStorage.getItem(prefix + '_' + suffix) : null;
    }

    var PortalAuth = {
        getToken: function () {
            return localStorage.getItem(storageKey('token')) || v1Fallback('auth_token') || '';
        },

        getUserData: function () {
            var raw = localStorage.getItem(storageKey('user_data'));
            if (raw) {
                return raw;
            }
            // v1 stores this via JSON.stringify(userData) even though userData is
            // already a plain string — unwrap the extra quoting before use.
            var v1Raw = v1Fallback('user_data');
            if (!v1Raw) {
                return '';
            }
            try {
                return JSON.parse(v1Raw);
            } catch (e) {
                return v1Raw;
            }
        },

        getUser: function () {
            return JSON.parse(localStorage.getItem(storageKey('user')) || v1Fallback('user_info') || 'null');
        },

        setSession: function (data) {
            data = data || {};
            localStorage.setItem(storageKey('token'), data.token || '');
            localStorage.setItem(storageKey('user_data'), data.user_data || '');
            localStorage.setItem(storageKey('user'), JSON.stringify(data.user || {}));
            localStorage.removeItem(sessionExpiredKey());

            // Also mirror the v1 portal-branded keys (same shape v1's own login.js
            // writes) so a v2 login is recognized by v1 pages too -- v1 has no
            // knowledge of the v2 keys above and only ever reads its own pha_/
            // assist_/nurse_/par_-prefixed keys. Without this, logging in on v2
            // and then following a not-yet-migrated sidebar link to v1 gets the
            // user bounced straight to v1's login page.
            var prefix = V1_KEY_PREFIX[PORTAL];
            if (prefix) {
                localStorage.setItem(prefix + '_auth_token', data.token || '');
                localStorage.setItem(prefix + '_user_data', JSON.stringify(data.user_data || ''));
                localStorage.setItem(prefix + '_user_info', JSON.stringify(data.user || {}));
                localStorage.setItem(prefix + '_login_time', new Date());
            }
        },

        /** Clear only this portal's v2 session keys -- leaves a v1-originated session intact. */
        clearOwnSession: function () {
            localStorage.removeItem(storageKey('token'));
            localStorage.removeItem(storageKey('user_data'));
            localStorage.removeItem(storageKey('user'));
        },

        clearSession: function () {
            this.clearOwnSession();
            localStorage.removeItem(sessionExpiredKey());

            // getToken()/getUser() fall back to the v1 portal-branded keys, so a
            // logout that only clears the v2 keys leaves the v1 token behind and
            // the login page's redirectIfAuthenticated() bounces back to the
            // dashboard. Clear the v1 fallback keys too. Reserved for an explicit
            // user-initiated logout (wireLogout) -- NOT for the ajaxError session
            // guard below, which must not kill a valid v1 session over a single
            // v2-side 401/403.
            var prefix = V1_KEY_PREFIX[PORTAL];
            if (prefix) {
                localStorage.removeItem(prefix + '_auth_token');
                localStorage.removeItem(prefix + '_user_data');
                localStorage.removeItem(prefix + '_user_info');
                localStorage.removeItem(prefix + '_login_time');
            }
        },

        /**
         * Call on authenticated pages. Redirects to login if no token is stored.
         * @return {boolean} true if a token is present.
         */
        requireAuth: function () {
            if (!this.getToken()) {
                window.location.href = BASEURL + '/login';
                return false;
            }
            return true;
        },

        /**
         * Call on login pages. Redirects to dashboard if a token already exists.
         * @return {boolean} true if it redirected.
         */
        redirectIfAuthenticated: function () {
            // A previous authenticated page already got a middleware-level
            // auth failure and redirected here. Clear v1 fallback keys once so
            // stale bridge tokens cannot bounce login <-> dashboard forever.
            if (localStorage.getItem(sessionExpiredKey()) === '1') {
                this.clearSession();
                return false;
            }

            if (this.getToken()) {
                window.location.href = homeUrl(this.getUser());
                return true;
            }
            return false;
        },

        /** Attach Authorization + User-Data headers (required by Auth::protect()) to every AJAX call. */
        attachAjaxHeaders: function () {
            var token = this.getToken();
            var userDataStr = this.getUserData();
            $.ajaxSetup({
                beforeSend: function (xhr) {
                    if (token) {
                        xhr.setRequestHeader('Authorization', 'Bearer ' + token);
                    }
                    if (userDataStr) {
                        xhr.setRequestHeader('User-Data', userDataStr);
                    }
                }
            });
            this.attachSessionGuard();
            this.renderUserName();

            // #first_load is a full-screen white overlay (z-index 9999, main.css)
            // shown while the page's JS initializes. Every authenticated portal
            // page calls attachAjaxHeaders() right after requireAuth(), so this is
            // the one place to remove it -- forgetting it per-portal leaves the
            // whole page (sidebar/topbar/content) looking blank.
            $('#first_load').fadeOut();
        },

        /**
         * Replace the navbar's portal-name placeholder with the logged-in user's
         * name + role, matching v1's main.js (pha_user_info.first_name + ' ' +
         * pha_user_info.last_name + ' (' + roleNames + ')'). Leaves the server-
         * rendered placeholder untouched if no stored user data is available.
         */
        renderUserName: function () {
            var user = this.getUser();
            if (!user || !user.first_name) {
                return;
            }
            var roleNames = user.roleNames || [];
            $('#login_user_name').html(
                user.first_name + ' ' + user.last_name +
                ' (<small>' + roleNames.join(', ') + '</small>)'
            );
        },

        /**
         * Global handler for auth failures on any AJAX call, so every page gets the
         * same "your session expired, please log in again" behavior instead of each
         * page's own .fail() callback silently doing nothing or showing a generic error.
         *
         * v2's API collapses two very different failures onto the SAME HTTP status:
         *   - AuthMiddleware / PortalScopeMiddleware failure (missing, invalid, or
         *     expired token; wrong portal scope) -> api/v2/system/Engine.php's
         *     generic halt(403, 'Forbidden') -> a bare "Forbidden" TEXT body, not JSON.
         *   - A role check inside a controller (e.g. assertSuperAdmin()) -> a normal
         *     JSON body {code: 403, message: "Access is denied."} -- the token is
         *     fine, the user is just not allowed to do that specific action.
         * Only the first case is a session problem; only that case should clear the
         * session and redirect to login. The second case should just show its message.
         */
        attachSessionGuard: function () {
            var self = this;
            if (this._sessionGuardAttached) {
                return;
            }
            this._sessionGuardAttached = true;

            $(document).ajaxError(function (event, xhr, settings) {
                if (!xhr || (xhr.status !== 401 && xhr.status !== 403)) {
                    return;
                }
                // The login request itself handles its own errors (bad credentials, etc).
                if (settings && /\/user\/login(?:\?|$)/.test(settings.url || '')) {
                    return;
                }

                var body = xhr.responseJSON;
                if (!body) {
                    try { body = JSON.parse(xhr.responseText); } catch (e) { body = null; }
                }

                // A parsed JSON body with a message means a real (non-session) permission
                // error from a controller -- surface it and stop, no redirect.
                if (xhr.status === 403 && body && body.message) {
                    if (window.PortalUtils) {
                        PortalUtils.showError(body.message, 'Access Denied');
                    } else {
                        alert(body.message);
                    }
                    return;
                }

                // Otherwise: missing/invalid/expired token or portal-scope mismatch.
                // Only this portal's v2 keys are cleared -- a v1-originated session
                // (pha_*/assist_*/etc.) must survive a v2-side auth failure so the
                // user isn't logged out of v1 too by an unrelated v2 API call.
                self.clearOwnSession();
                localStorage.setItem(sessionExpiredKey(), '1');
                var message = 'Your session has expired. Please log in again.';
                var alreadyOnLogin = /\/login\/?$/.test(window.location.pathname);
                if (alreadyOnLogin) {
                    return;
                }
                if (window.PortalUtils) {
                    PortalUtils.showError(message, 'Session Expired');
                    setTimeout(function () { window.location.href = BASEURL + '/login'; }, 2500);
                } else {
                    alert(message);
                    window.location.href = BASEURL + '/login';
                }
            });
        },

        /**
         * Wire a logout control to call the API logout endpoint, clear localStorage,
         * and redirect to login.
         * @param {string} selector  Click target, e.g. '.logout-btn'.
         * @param {string} [scope]   API scope override (defaults to PORTAL_API_SCOPE / PORTAL).
         */
        wireLogout: function (selector, scope) {
            var self = this;
            $(document).on('click', selector, function (e) {
                e.preventDefault();
                $.ajax({
                    url: APIURL + apiScope(scope) + '/user/logout',
                    method: 'POST',
                    complete: function () {
                        self.clearSession();
                        window.location.href = BASEURL + '/login';
                    }
                });
            });
        },

        /**
         * Wire a standard login form: #loginform, #signin-email, #signin-password,
         * #unmask-password, #btn-login, #login-alert. Handles email validation, the
         * password-visibility toggle, submit, and Enter-to-submit.
         *
         * @param {object}   options
         * @param {string}   [options.apiScope]     API scope override for the login endpoint.
         * @param {object}   [options.extraPayload]  Extra fields merged into the login POST body.
         * @param {function} [options.onSuccess]     Called with the API response before redirecting.
         */
        wireLoginForm: function (options) {
            var self = this;
            options = options || {};

            var emailElm = $("#loginform input[name='email']");
            emailElm.on('keyup', function (e) {
                e.preventDefault();
                var val = emailElm.val();
                if (!validateEmail(val) || val.length === 0) {
                    emailElm.parsley().removeError('invalid_email_format', {updateClass: true});
                    emailElm.parsley().addError('invalid_email_format', {message: 'Invalid email format.'});
                    return false;
                }
                emailElm.parsley().removeError('invalid_email_format', {updateClass: true});
            });

            $('#unmask-password').on('click', function () {
                var pw = $('#signin-password');
                pw.attr('type', pw.attr('type') === 'password' ? 'text' : 'password');
                $(this).toggleClass('glyphicon-eye-open glyphicon-eye-close');
            });

            $('#btn-login').on('click', function () {
                var email    = $('#signin-email').val().trim();
                var password = $('#signin-password').val();
                var $terms   = $("input[name='terms_and_condition']");
                var $alert   = $('#login-alert');

                $alert.addClass('hide').text('');

                if (!email || !password) {
                    $alert.text('Email and password are required.').removeClass('hide');
                    return;
                }
                if ($terms.length && !$terms.is(':checked')) {
                    $alert.text('Please accept the Terms of Service.').removeClass('hide');
                    return;
                }

                var $btn = $(this).prop('disabled', true).text('Logging in...');
                var payload = $.extend({ email: email, password: password }, options.extraPayload || {});

                $.ajax({
                    url: APIURL + apiScope(options.apiScope) + '/user/login',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify(payload),
                    success: function (res) {
                        if (res && res.code === 200 && res.data) {
                            self.setSession(res.data);
                            if (options.onSuccess) {
                                options.onSuccess(res);
                            }
                            window.location.href = homeUrl(res.data.user);
                        } else if (res && res.code === 200 && res.email) {
                            // Not whitelisted — hand off to the verify-identity page via
                            // the shared #verify_form (see shared/layouts/auth.php).
                            var $verifyForm = $('#verify_form');
                            if ($verifyForm.length) {
                                $verifyForm.find('#email').val(res.email);
                                $verifyForm.find('#hide_email').val(res.hide_email || '');
                                $verifyForm.find('#ph').val(res.ph || '');
                                $verifyForm[0].submit();
                            }
                        } else {
                            $alert.text(res.message || 'Login failed.').removeClass('hide');
                            $btn.prop('disabled', false).text('Login');
                        }
                    },
                    error: function (xhr) {
                        var msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : 'Server error. Please try again.';
                        $alert.text(msg).removeClass('hide');
                        $btn.prop('disabled', false).text('Login');
                    }
                });
            });

            $('#signin-password').on('keypress', function (e) {
                if (e.which === 13) {
                    $('#btn-login').trigger('click');
                }
            });
        }
    };

    window.PortalAuth = PortalAuth;

}(jQuery));
