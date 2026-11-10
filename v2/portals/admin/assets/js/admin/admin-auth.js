/**
 * DS3 Admin — login page JS.
 *
 * Requires: jQuery, PortalAuth (portal-auth.js), toastr, Parsley
 * All loaded inline in login.php before this file.
 */
$(function () {
    // Redirect to dashboard if already logged in
    PortalAuth.redirectIfAuthenticated();

    if (!$('#loginBtn').length) return;

    var API_URL = APIURL + 'admin/user/login';

    $('#loginForm').parsley();

    $('#loginBtn').on('click', function (e) {
        e.preventDefault();
        if (!$('#loginForm').parsley().validate()) return;

        var $btn = $(this).prop('disabled', true).text('Signing in...');

        $.ajax({
            url: API_URL,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                portal:   'admin',
                email:    $('#email').val().trim(),
                password: $('#password').val()
            }),
            success: function (res) {
                if (res && res.code === 200 && res.data && res.data.token) {
                    PortalAuth.setSession({
                        token:     res.data.token,
                        user_data: res.data.user_data || '',
                        user:      res.data.user || {}
                    });
                    window.location.href = BASEURL + '/dashboard';
                } else {
                    toastr.error(res.message || 'Login failed. Please check your credentials.');
                    $btn.prop('disabled', false).text('Sign In');
                }
            },
            error: function () {
                toastr.error('Unable to connect. Please try again.');
                $btn.prop('disabled', false).text('Sign In');
            }
        });
    });

    $('#password').on('keypress', function (e) {
        if (e.which === 13) $('#loginBtn').trigger('click');
    });
});
