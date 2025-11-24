'use strict';

(function ($) {
  const $form = $('#loginForm');
  const $btn = $('#btnLogin');
  const $spinner = $('#btnSpinner');
  const $alert = $('#loginAlert');
  const $username = $('#username');
  const $password = $('#password');
  const $togglePassword = $('#togglePassword');

  function showAlert(message, type) {
    $alert.removeClass('d-none alert-success alert-danger alert-warning alert-info')
      .addClass('alert-' + type)
      .text(message);
  }

  function setLoading(isLoading) {
    if (isLoading) {
      $btn.prop('disabled', true);
      $spinner.removeClass('d-none');
    } else {
      $btn.prop('disabled', false);
      $spinner.addClass('d-none');
    }
  }

  function validateField($el) {
    const v = $el.val().trim();
    if (!v) {
      $el.addClass('is-invalid').removeClass('is-valid');
      return false;
    }
    $el.addClass('is-valid').removeClass('is-invalid');
    return true;
  }

  $username.on('blur input', function(){ validateField($username); });
  $password.on('blur input', function(){ validateField($password); });

  $togglePassword.on('click', function(){
    const type = $password.attr('type') === 'password' ? 'text' : 'password';
    $password.attr('type', type);
    $togglePassword.attr('aria-label', type === 'password' ? 'Show password' : 'Hide password');
    // brief blink feedback on the eye icon
    const $eye = $togglePassword.find('.pw-eye');
    if ($eye.length) {
      $eye.addClass('blink');
      // remove after animation completes (slightly longer than animation)
      setTimeout(function(){ $eye.removeClass('blink'); }, 350);
    }
  });

  $form.on('submit', function (e) {
    e.preventDefault();
    const okUser = validateField($username);
    const okPass = validateField($password);
    if (!okUser || !okPass) {
      showAlert('Please fill in required fields.', 'warning');
      return;
    }

    const payload = {
      username: $username.val().trim(),
      password: $password.val()
    };

    setLoading(true);
    showAlert('Signing in…', 'info');

    $.ajax({
      url: '../php/login.php',
      method: 'POST',
      dataType: 'json',
      data: payload,
      success: function (res) {
        if (res && res.success && res.token) {
          localStorage.setItem('sessionToken', res.token);
          if (res.user) localStorage.setItem('userCore', JSON.stringify(res.user));
          window.location.href = 'profile.html';
        } else {
          showAlert(res && res.error ? res.error : 'Login failed.', 'danger');
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
