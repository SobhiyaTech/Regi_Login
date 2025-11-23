'use strict';

(function ($) {
  const $form = $('#registerForm');
  const $btn = $('#btnRegister');
  const $spinner = $('#regSpinner');
  const $alert = $('#registerAlert');
  const $username = $('#username');
  const $email = $('#email');
  const $password = $('#password');
  const $toggle = $('#toggleRegPassword');

  const USER_RE = /^[a-zA-Z0-9_.-]{3,32}$/;

  function showAlert(message, type) {
    $alert.removeClass('d-none alert-success alert-danger alert-warning alert-info')
      .addClass('alert-' + type)
      .text(message);
  }

  function setLoading(isLoading) {
    if (isLoading) { $btn.prop('disabled', true); $spinner.removeClass('d-none'); }
    else { $btn.prop('disabled', false); $spinner.addClass('d-none'); }
  }

  function validateUsername() {
    const v = $username.val().trim();
    const ok = USER_RE.test(v);
    $username.toggleClass('is-invalid', !ok).toggleClass('is-valid', ok);
    return ok;
  }
  function validateEmail() {
    const v = $email.val().trim();
    const ok = v.length > 3 && /@/.test(v);
    $email.toggleClass('is-invalid', !ok).toggleClass('is-valid', ok);
    return ok;
  }
  function validatePassword() {
    const v = $password.val();
    const ok = v.length >= 8;
    $password.toggleClass('is-invalid', !ok).toggleClass('is-valid', ok);
    return ok;
  }

  $username.on('blur input', validateUsername);
  $email.on('blur input', validateEmail);
  $password.on('blur input', validatePassword);

  $toggle.on('click', function(){
    const type = $password.attr('type') === 'password' ? 'text' : 'password';
    $password.attr('type', type);
    $toggle.attr('aria-label', type === 'password' ? 'Show password' : 'Hide password');
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    const okU = validateUsername();
    const okE = validateEmail();
    const okP = validatePassword();
    if (!(okU && okE && okP)) {
      showAlert('Please fix the highlighted fields.', 'warning');
      return;
    }

    const payload = {
      username: $username.val().trim(),
      email: $email.val().trim(),
      password: $password.val()
    };

    setLoading(true);
    showAlert('Creating your account…', 'info');

    $.ajax({
      url: '../php/register.php',
      method: 'POST',
      dataType: 'json',
      data: payload,
      success: function (res) {
        if (res && res.success) {
          showAlert('Registration successful. Redirecting to login…', 'success');
          setTimeout(function () { window.location.href = 'login.html'; }, 900);
        } else {
          showAlert(res && res.error ? res.error : 'Registration failed.', 'danger');
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred.';
        try { msg = (xhr.responseJSON && xhr.responseJSON.error) || msg; } catch (_) {}
        showAlert(msg, 'danger');
      },
      complete: function(){ setLoading(false); }
    });
  });
})(jQuery);
