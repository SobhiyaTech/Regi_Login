'use strict';

(function ($) {
  const token = localStorage.getItem('sessionToken');
  if (!token) {
    window.location.replace('login.html');
  }

  function setUserCore(user) {
    if (!user) return;
    const username = user.username || 'User';
    const email = user.email || 'user@example.com';
    
    // Set display fields
    $('#displayUsername').text(username);
    $('#displayEmail').text(email);
    
    // Set avatar initials (first letter of username)
    const initials = username.charAt(0).toUpperCase();
    $('#avatarInitials').text(initials);
  }

  function updateProfileDisplay(profile) {
    if (!profile) {
      $('#displayDob').text('Not set').removeClass('text-primary');
      $('#displayAge').text('Not set').removeClass('text-primary');
      $('#displayContact').text('Not set').removeClass('text-primary');
      $('#displayAddress').text('Not set').removeClass('text-primary');
      $('#ageDisplay').hide();
      $('#contactDisplay').hide();
      return;
    }

    // Update DOB display
    if (profile.dob) {
      const dobDate = new Date(profile.dob);
      const formattedDob = dobDate.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
      $('#displayDob').text(formattedDob).addClass('text-primary fw-bold');
    } else {
      $('#displayDob').text('Not set').removeClass('text-primary fw-bold');
    }

    // Update age display
    const age = profile.dob ? calculateAge(profile.dob) : profile.age;
    if (age) {
      $('#displayAge').text(age + ' years old').addClass('text-primary fw-bold');
      $('#ageDisplay').show();
      $('#ageBadgeText').text('Age: ' + age);
    } else {
      $('#displayAge').text('Not set').removeClass('text-primary fw-bold');
      $('#ageDisplay').hide();
    }

    // Update contact display
    if (profile.contact && profile.contact.trim()) {
      $('#displayContact').text(profile.contact).addClass('text-primary fw-bold');
      $('#contactDisplay').show();
      const shortContact = profile.contact.length > 20 ? 
        profile.contact.substring(0, 20) + '...' : profile.contact;
      $('#contactBadgeText').text(shortContact);
    } else {
      $('#displayContact').text('Not set').removeClass('text-primary fw-bold');
      $('#contactDisplay').hide();
    }

    // Update address display
    if (profile.address && profile.address.trim()) {
      $('#displayAddress').html(profile.address.replace(/\n/g, '<br>')).addClass('text-primary fw-bold');
    } else {
      $('#displayAddress').text('Not set').removeClass('text-primary fw-bold');
    }
  }

  function showAlert(message, type) {
    const el = $('#profileAlert');
    el.removeClass('d-none alert-success alert-danger alert-warning alert-info').addClass('alert-' + type).text(message);
  }

  // Prefill core user details from localStorage if available
  try {
    const cached = JSON.parse(localStorage.getItem('userCore') || 'null');
    if (cached) setUserCore(cached);
  } catch (_) {}

  // Fetch profile data from backend
  function fetchProfile() {
    showAlert('Loading profile…', 'info');
    $.ajax({
      url: '../php/profile.php',
      method: 'GET',
      dataType: 'json',
      headers: { 'X-Session-Token': token },
      success: function (res) {
        if (res && res.success) {
          if (res.user) {
            localStorage.setItem('userCore', JSON.stringify(res.user));
            setUserCore(res.user);
          }
          if (res.profile) {
            $('#dob').val(res.profile.dob || '');
            $('#contact').val(res.profile.contact || '');
            $('#address').val(res.profile.address || '');
            
            // Auto-calculate age from DOB if available
            if (res.profile.dob) {
              const age = calculateAge(res.profile.dob);
              $('#age').val(age !== null ? age : (res.profile.age || ''));
            } else {
              $('#age').val(res.profile.age || '');
            }
            
            // Update profile display
            updateProfileDisplay(res.profile);
          } else {
            updateProfileDisplay(null);
          }
          $('#profileAlert').addClass('d-none');
        } else {
          // Invalid token or other error – force logout
          doLogout();
        }
      },
      error: function () {
        // On error, don't reveal details – just require re-login
        doLogout();
      }
    });
  }

  function doLogout() {
    localStorage.removeItem('sessionToken');
    localStorage.removeItem('userCore');
    window.location.replace('login.html');
  }

  $('#logoutBtn').on('click', function () { doLogout(); });

  // Calculate age from date of birth
  function calculateAge(dob) {
    if (!dob) return null;
    const birthDate = new Date(dob);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    // Adjust if birthday hasn't occurred this year yet
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
      age--;
    }
    
    return age >= 0 ? age : null;
  }

  // Auto-calculate age when DOB changes
  $('#dob').on('change', function() {
    const dob = $(this).val();
    if (dob) {
      const age = calculateAge(dob);
      if (age !== null) {
        $('#age').val(age);
      }
    }
  });

  $('#btnReset').on('click', function(){
    $('#age').val('');
    $('#dob').val('');
    $('#contact').val('');
    $('#address').val('');
  });

  $('#profileForm').on('submit', function (e) {
    e.preventDefault();
    
    const dobValue = $('#dob').val() || null;
    const ageValue = dobValue ? calculateAge(dobValue) : ($('#age').val() || null);
    
    const payload = {
      age: ageValue,
      dob: dobValue,
      contact: $('#contact').val().trim() || null,
      address: $('#address').val().trim() || null
    };

    const $btn = $('#btnSave');
    const $spin = $('#saveSpinner');
    $btn.prop('disabled', true); $spin.removeClass('d-none');
    showAlert('Saving…', 'info');

    $.ajax({
      url: '../php/profile.php',
      method: 'POST',
      dataType: 'json',
      headers: { 'X-Session-Token': token },
      data: JSON.stringify({ action: 'update', profile: payload }),
      processData: false,
      contentType: 'application/json; charset=UTF-8',
      success: function (res) {
        if (res && res.success) {
          showAlert('Profile updated successfully.', 'success');
          // Update display with new values
          const displayProfile = {
            dob: payload.dob,
            age: payload.age,
            contact: payload.contact,
            address: payload.address
          };
          updateProfileDisplay(displayProfile);
        } else {
          showAlert(res && res.error ? res.error : 'Update failed.', 'danger');
        }
      },
      error: function (xhr) {
        let msg = 'An error occurred.';
        try { msg = (xhr.responseJSON && xhr.responseJSON.error) || msg; } catch (_) {}
        showAlert(msg, 'danger');
      },
      complete: function(){ $btn.prop('disabled', false); $spin.addClass('d-none'); }
    });
  });

  // Initial fetch
  fetchProfile();
})(jQuery);
