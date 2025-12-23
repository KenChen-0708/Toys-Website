// Theme preference (account page only)
(function () {
  const STORAGE_KEY = 'account_theme';
  const root = document.documentElement;
  const current = localStorage.getItem(STORAGE_KEY) || 'light';
  root.dataset.accountTheme = current;

  document.querySelectorAll('[name="account_theme"]').forEach((radio) => {
    if (radio.value === current) radio.checked = true;
    radio.addEventListener('change', () => {
      localStorage.setItem(STORAGE_KEY, radio.value);
      root.dataset.accountTheme = radio.value;
    });
  });
})();

// Password toggle functionality - reusable function (defined globally)
function initializePasswordToggles() {
  // Use event delegation on document to handle dynamically loaded elements
  // Handle clicks on both the button and the icon inside it
  $(document).off('click', '.password-toggle, .password-toggle i').on('click', '.password-toggle, .password-toggle i', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Find the button element (in case click was on icon)
    const $toggle = $(this).closest('.password-toggle');
    if (!$toggle.length) {
      return;
    }
    
    const targetId = $toggle.attr('data-target');
    
    if (!targetId) {
      console.error('Password toggle: data-target not found');
      return;
    }
    
    const $input = $('#' + targetId);
    if (!$input.length) {
      console.error('Password toggle: input not found for targetId:', targetId);
      return;
    }
    
    // Find or ensure icon exists - NEVER remove or change the icon class
    let $icon = $toggle.find('i');
    if (!$icon.length) {
      // Icon doesn't exist, create it with the standard eye icon
      $icon = $('<i class="fi fi-rs-eye"></i>');
      $toggle.append($icon);
    }
    
    // CRITICAL: Always ensure icon has the base classes and NEVER remove them
    if (!$icon.hasClass('fi')) {
      $icon.addClass('fi');
    }
    if (!$icon.hasClass('fi-rs-eye')) {
      $icon.addClass('fi-rs-eye');
    }
    
    // NEVER remove the fi-rs-eye class - keep it always
    // Just toggle password visibility and use data attribute for state
    if ($input.attr('type') === 'password') {
      $input.attr('type', 'text');
      $toggle.attr('title', 'Hide password');
      $toggle.attr('data-password-visible', 'true');
      // Add a class to the button for styling if needed, but keep icon unchanged
      $toggle.addClass('password-showing');
    } else {
      $input.attr('type', 'password');
      $toggle.attr('title', 'Show password');
      $toggle.attr('data-password-visible', 'false');
      $toggle.removeClass('password-showing');
    }
    
    // CRITICAL: Always force icon to be visible using inline styles - do this EVERY time
    $icon.css({
      'display': 'inline-block',
      'visibility': 'visible',
      'opacity': '1',
      'font-size': '1.125rem'
    });
    
    // CRITICAL: Always force button to be visible
    $toggle.css({
      'display': 'flex',
      'visibility': 'visible',
      'opacity': '1'
    });
    
    // Double-check icon is still there after a brief delay
    setTimeout(function() {
      const checkIcon = $toggle.find('i');
      if (!checkIcon.length || checkIcon.css('display') === 'none' || checkIcon.css('visibility') === 'hidden') {
        // Icon disappeared, recreate it
        $toggle.find('i').remove();
        const newIcon = $('<i class="fi fi-rs-eye"></i>');
        $toggle.append(newIcon);
        newIcon.css({
          'display': 'inline-block',
          'visibility': 'visible',
          'opacity': '1',
          'font-size': '1.125rem'
        });
      }
    }, 50);
  });
}

// Clear password form functionality - reusable function
function initializeClearPasswordButton() {
  $(document).off('click', '#clearPasswordForm').on('click', '#clearPasswordForm', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    // Get form elements fresh each time
    const form = document.getElementById('passwordChangeForm');
    const currentPwd = document.getElementById('current_password');
    const newPwd = document.getElementById('new_password');
    const confirmPwd = document.getElementById('new_password_confirm');
    const strengthFill = document.getElementById('passwordStrengthFill');
    const strengthText = document.getElementById('passwordStrengthText');
    const matchIndicator = document.getElementById('passwordMatch');
    const reqItems = document.querySelectorAll('.password-req-item');
    
    // Explicitly clear all password fields
    if (currentPwd) {
      currentPwd.value = '';
      currentPwd.type = 'password';
      currentPwd.setCustomValidity('');
    }
    if (newPwd) {
      newPwd.value = '';
      newPwd.type = 'password';
      newPwd.setCustomValidity('');
    }
    if (confirmPwd) {
      confirmPwd.value = '';
      confirmPwd.type = 'password';
      confirmPwd.setCustomValidity('');
    }
    
    // Reset form
    if (form) {
      form.reset();
    }
    
    // Reset password strength indicator
    if (strengthFill) {
      strengthFill.className = 'password-strength-fill';
    }
    if (strengthText) {
      strengthText.textContent = 'Password strength';
    }
    
    // Reset password requirements
    reqItems.forEach(item => {
      item.classList.remove('valid');
      const icon = item.querySelector('.req-icon');
      if (icon) {
        icon.classList.remove('fi-rs-check');
        icon.classList.add('fi-rs-cross-small');
      }
    });
    
    // Reset password match indicator
    if (matchIndicator) {
      matchIndicator.classList.remove('show');
    }
    
    // Reset password input types and toggle icons
    const toggles = document.querySelectorAll('.password-toggle');
    toggles.forEach(toggle => {
      const icon = toggle.querySelector('i');
      if (icon) {
        icon.classList.remove('fi-rs-eye-crossed');
        icon.classList.add('fi-rs-eye');
      }
      const targetId = toggle.getAttribute('data-target');
      if (targetId) {
        const input = document.getElementById(targetId);
        if (input) {
          input.type = 'password';
          input.value = '';
        }
      }
    });
  });
}

// Dynamic content switching using jQuery
$(document).ready(function() {
  // Initialize password toggles on page load
  initializePasswordToggles();
  
  // Initialize clear password button on page load
  initializeClearPasswordButton();
  
  // Handle menu item clicks
  $('.profile-menu__item').on('click', function() {
    const contentId = $(this).data('content');
    
    if (!contentId) {
      return;
    }

    // Remove active class from all menu items
    $('.profile-menu__item').removeClass('active');
    
    // Add active class to clicked menu item
    $(this).addClass('active');
    
    // Hide all content panels
    $('.content-panel').removeClass('active');
    
    // Show the selected content panel
    $('#' + contentId).addClass('active');
    
    // Initialize password toggles and clear button when password panel is shown
    if (contentId === 'change-password') {
      initializePasswordToggles();
      initializeClearPasswordButton();
    }
    
    // Update URL hash without scrolling
    if (history.pushState) {
      history.pushState(null, null, '#' + contentId);
    } else {
      window.location.hash = '#' + contentId;
    }
  });

  // Handle hash navigation on page load
  if (window.location.hash) {
    const hash = window.location.hash.substring(1); // Remove #
    const menuItem = $('.profile-menu__item[data-content="' + hash + '"]');
    if (menuItem.length) {
      menuItem.trigger('click');
    }
  } else {
    // Default: show dashboard
    $('.profile-menu__item[data-content="dashboard"]').trigger('click');
  }

  // Handle link buttons that switch content (e.g., "update profile" link in dashboard)
  $(document).on('click', '.profile-link-btn', function() {
    const contentId = $(this).data('content');
    if (contentId) {
      const menuItem = $('.profile-menu__item[data-content="' + contentId + '"]');
      if (menuItem.length) {
        menuItem.trigger('click');
      } else {
        // If no menu item exists, directly show the content panel
        $('.profile-menu__item').removeClass('active');
        $('.content-panel').removeClass('active');
        $('#' + contentId).addClass('active');
      }
    }
  });

  // Address edit modal functionality
  $(document).on('click', '#editAddressBtn', function() {
    $('#addressEditModal').addClass('active');
  });

  $(document).on('click', '#closeAddressModal, #cancelAddressModalBtn', function() {
    $('#addressEditModal').removeClass('active');
  });

  // Close modal when clicking outside
  $(document).on('click', '#addressEditModal', function(e) {
    if ($(e.target).is('#addressEditModal')) {
      $(this).removeClass('active');
    }
  });

  // Handle checklist item clicks
  $(document).on('click', '.profile-check-item', function(e) {
    e.preventDefault();
    const action = $(this).data('checklist-action');
    const field = $(this).data('checklist-field');
    
    if (action === 'photo') {
      // Open profile photo modal
      $('#profilePhotoTrigger').trigger('click');
    } else if (action === 'update-profile') {
      // Navigate to update profile page
      const menuItem = $('.profile-menu__item[data-content="update-profile"]');
      if (menuItem.length) {
        menuItem.trigger('click');
        
        // Scroll to the specific field if needed
        setTimeout(function() {
          const fieldId = 'phone_number';
          if (field === 'phone') {
            const phoneInput = document.getElementById(fieldId);
            if (phoneInput) {
              phoneInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
              phoneInput.focus();
            }
          }
        }, 300);
      }
    }
  });

  // Handle stat card clicks
  $(document).on('click', '.account-stat-card', function(e) {
    e.preventDefault();
    const url = $(this).data('stat-url');
    const panel = $(this).data('stat-panel');
    
    if (panel) {
      // Navigate to a panel within the same page
      const menuItem = $('.profile-menu__item[data-content="' + panel + '"]');
      if (menuItem.length) {
        menuItem.trigger('click');
      } else {
        // If no menu item exists, directly show the content panel
        $('.profile-menu__item').removeClass('active');
        $('.content-panel').removeClass('active');
        $('#' + panel).addClass('active');
      }
    } else if (url) {
      // Navigate to external page
      window.location.href = url;
    }
  });

  // Handle profile photo click to open modal and auto-start webcam
  $('#profilePhotoTrigger').on('click', function() {
    $('#profilePhotoModal').addClass('active');
    // Auto-start webcam when modal opens
    setTimeout(function() {
      startModalWebcam();
    }, 100);
  });

  // Close modal handlers
  $('#closePhotoModal, #cancelPhotoModal').on('click', function() {
    stopModalWebcam();
    $('#profilePhotoModal').removeClass('active');
  });

  // Close modal when clicking outside
  $('#profilePhotoModal').on('click', function(e) {
    if ($(e.target).is('#profilePhotoModal')) {
      stopModalWebcam();
      $(this).removeClass('active');
    }
  });

  // Modal webcam functions
  let modalStream = null;
  
  function startModalWebcam() {
    const video = document.getElementById('modalWebcamVideo');
    const currentImg = document.getElementById('modalCurrentProfileImg');
    const defaultAvatar = document.getElementById('modalDefaultProfileAvatar');
    const webcamControls = document.getElementById('modalWebcamControls');
    const dropZone = document.getElementById('modalPhotoDropZone');
    
    if (!video) return;
    
    navigator.mediaDevices.getUserMedia({ video: true })
      .then(function(stream) {
        modalStream = stream;
        video.srcObject = stream;
        video.style.display = 'block';
        
        // Hide current profile image
        if (currentImg) {
          currentImg.style.display = 'none';
        }
        if (defaultAvatar) {
          defaultAvatar.style.display = 'none';
        }
        
        // Show webcam controls
        if (webcamControls) {
          webcamControls.style.display = 'flex';
        }
        
        // Make dropzone less prominent but still functional
        if (dropZone) {
          dropZone.style.opacity = '0.7';
        }
      })
      .catch(function(err) {
        console.error('Webcam access error:', err);
        // If webcam fails, show upload option prominently
        if (dropZone) {
          dropZone.style.opacity = '1';
        }
      });
  }
  
  function stopModalWebcam() {
    if (modalStream) {
      modalStream.getTracks().forEach(function(track) {
        track.stop();
      });
      modalStream = null;
    }
    
    const video = document.getElementById('modalWebcamVideo');
    const currentImg = document.getElementById('modalCurrentProfileImg');
    const defaultAvatar = document.getElementById('modalDefaultProfileAvatar');
    const webcamControls = document.getElementById('modalWebcamControls');
    const dropZone = document.getElementById('modalPhotoDropZone');
    
    if (video) {
      video.style.display = 'none';
      video.srcObject = null;
    }
    if (webcamControls) {
      webcamControls.style.display = 'none';
    }
    if (dropZone) {
      dropZone.style.opacity = '1';
    }
  }
  
  // Switch from webcam to file upload
  $('#modalSwitchToUploadBtn').on('click', function() {
    stopModalWebcam();
    const dropZone = document.getElementById('modalPhotoDropZone');
    if (dropZone) {
      dropZone.style.opacity = '1';
      dropZone.style.transform = 'scale(1.05)';
      setTimeout(function() {
        dropZone.style.transform = 'scale(1)';
      }, 200);
    }
  });
  
  // Modal webcam capture
  $('#modalCaptureWebcamBtn').on('click', function() {
    const video = document.getElementById('modalWebcamVideo');
    const canvas = document.getElementById('modalWebcamCanvas');
    const previewImg = document.getElementById('modalPhotoPreview');
    const currentImg = document.getElementById('modalCurrentProfileImg');
    const defaultAvatar = document.getElementById('modalDefaultProfileAvatar');
    const webcamControls = document.getElementById('modalWebcamControls');
    const captureField = document.getElementById('modalCaptureData');
    const editControls = document.getElementById('modalPhotoEditControls');
    
    if (!video || !modalStream) return;
    
    const width = video.videoWidth || 320;
    const height = video.videoHeight || 240;
    
    if (!canvas) {
      const newCanvas = document.createElement('canvas');
      newCanvas.id = 'modalWebcamCanvas';
      newCanvas.style.display = 'none';
      document.body.appendChild(newCanvas);
      canvas = newCanvas;
    }
    
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(video, 0, 0, width, height);
    const dataUrl = canvas.toDataURL('image/png');
    
    // Stop webcam
    stopModalWebcam();
    
    // Show preview
    if (previewImg) {
      previewImg.src = dataUrl;
      previewImg.style.display = 'block';
      previewImg.style.visibility = 'visible';
      previewImg.style.opacity = '1';
      // Store original for reset functionality
      if (typeof modalOriginalImageSrc === 'undefined') {
        window.modalOriginalImageSrc = dataUrl;
      } else {
        window.modalOriginalImageSrc = dataUrl;
      }
      if (typeof modalCurrentRotation === 'undefined') {
        window.modalCurrentRotation = 0;
        window.modalCurrentFlipH = 1;
        window.modalCurrentFlipV = 1;
      } else {
        window.modalCurrentRotation = 0;
        window.modalCurrentFlipH = 1;
        window.modalCurrentFlipV = 1;
      }
    }
    
    // Hide current image
    if (currentImg) {
      currentImg.style.display = 'none';
    }
    if (defaultAvatar) {
      defaultAvatar.style.display = 'none';
    }
    
    // Set capture data
    if (captureField) {
      captureField.value = dataUrl;
    }
    
    // Show edit controls
    if (editControls) {
      editControls.style.display = 'block';
    }
    
    // Show remove button
    const removeBtn = document.getElementById('modalRemovePreviewBtn');
    if (removeBtn) {
      removeBtn.style.display = 'flex';
    }
    
    // Make dropzone prominent again
    const dropZone = document.getElementById('modalPhotoDropZone');
    if (dropZone) {
      dropZone.style.opacity = '1';
    }
  });
  
  // Modal file upload handlers
  const modalDropZone = document.getElementById('modalPhotoDropZone');
  const modalFileInput = document.getElementById('modalProfilePhotoInput');
  const modalPreviewImg = document.getElementById('modalPhotoPreview');
  const modalCurrentImg = document.getElementById('modalCurrentProfileImg');
  const modalDefaultAvatar = document.getElementById('modalDefaultProfileAvatar');
  const modalCaptureField = document.getElementById('modalCaptureData');
  const modalRemoveBtn = document.getElementById('modalRemovePreviewBtn');
  const modalEditControls = document.getElementById('modalPhotoEditControls');
  
  if (modalDropZone && modalFileInput) {
    // Click to browse
    modalDropZone.addEventListener('click', function() {
      modalFileInput.click();
    });
    
    // Drag and drop
    modalDropZone.addEventListener('dragover', function(e) {
      e.preventDefault();
      modalDropZone.classList.add('photo-dropzone--hover');
    });
    
    modalDropZone.addEventListener('dragleave', function() {
      modalDropZone.classList.remove('photo-dropzone--hover');
    });
    
    modalDropZone.addEventListener('drop', function(e) {
      e.preventDefault();
      modalDropZone.classList.remove('photo-dropzone--hover');
      const file = e.dataTransfer.files[0];
      if (file && file.type.startsWith('image/')) {
        modalFileInput.files = e.dataTransfer.files;
        handleModalFileUpload(file);
      }
    });
    
    // File input change
    modalFileInput.addEventListener('change', function() {
      const file = modalFileInput.files[0];
      if (file && file.type.startsWith('image/')) {
        handleModalFileUpload(file);
      }
    });
  }
  
  function handleModalFileUpload(file) {
    // Stop webcam if running
    stopModalWebcam();
    
    const reader = new FileReader();
    reader.onload = function(e) {
      const dataUrl = e.target.result;
      
      // Show preview
      if (modalPreviewImg) {
        modalPreviewImg.src = dataUrl;
        modalPreviewImg.style.display = 'block';
        modalPreviewImg.style.visibility = 'visible';
        modalPreviewImg.style.opacity = '1';
        // Store original for reset functionality
        if (typeof modalOriginalImageSrc === 'undefined') {
          window.modalOriginalImageSrc = dataUrl;
        } else {
          window.modalOriginalImageSrc = dataUrl;
        }
        if (typeof modalCurrentRotation === 'undefined') {
          window.modalCurrentRotation = 0;
          window.modalCurrentFlipH = 1;
          window.modalCurrentFlipV = 1;
        } else {
          window.modalCurrentRotation = 0;
          window.modalCurrentFlipH = 1;
          window.modalCurrentFlipV = 1;
        }
      }
      
      // Hide current image
      if (modalCurrentImg) {
        modalCurrentImg.style.display = 'none';
      }
      if (modalDefaultAvatar) {
        modalDefaultAvatar.style.display = 'none';
      }
      
      // Set capture data
      if (modalCaptureField) {
        modalCaptureField.value = dataUrl;
      }
      
      // Clear file input so server uses capture data
      if (modalFileInput) {
        modalFileInput.value = '';
      }
      
      // Show edit controls
      if (modalEditControls) {
        modalEditControls.style.display = 'block';
      }
      
      // Show remove button
      if (modalRemoveBtn) {
        modalRemoveBtn.style.display = 'flex';
      }
    };
    reader.readAsDataURL(file);
  }
  
  // Modal remove preview
  if (modalRemoveBtn) {
    modalRemoveBtn.addEventListener('click', function() {
      if (modalPreviewImg) {
        modalPreviewImg.src = '';
        modalPreviewImg.style.display = 'none';
      }
      if (modalCurrentImg) {
        modalCurrentImg.style.display = 'block';
      }
      if (modalDefaultAvatar) {
        modalDefaultAvatar.style.display = 'flex';
      }
      if (modalRemoveBtn) {
        modalRemoveBtn.style.display = 'none';
      }
      if (modalEditControls) {
        modalEditControls.style.display = 'none';
      }
      if (modalCaptureField) {
        modalCaptureField.value = '';
      }
      // Restart webcam
      startModalWebcam();
    });
  }

  // Modal image processing controls
  window.modalCurrentRotation = window.modalCurrentRotation || 0;
  window.modalCurrentFlipH = window.modalCurrentFlipH || 1;
  window.modalCurrentFlipV = window.modalCurrentFlipV || 1;
  window.modalOriginalImageSrc = window.modalOriginalImageSrc || null;

  function applyModalTransform() {
    const previewImg = document.getElementById('modalPhotoPreview');
    if (!previewImg) return;
    
    const transform = `rotate(${window.modalCurrentRotation}deg) scaleX(${window.modalCurrentFlipH}) scaleY(${window.modalCurrentFlipV})`;
    previewImg.style.transform = transform;
  }

  function applyModalTransformToData() {
    const previewImg = document.getElementById('modalPhotoPreview');
    const captureField = document.getElementById('modalCaptureData');
    if (!previewImg || !captureField) return;
    
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = new Image();
    
    img.onload = function() {
      canvas.width = img.width;
      canvas.height = img.height;
      
      ctx.save();
      ctx.translate(canvas.width / 2, canvas.height / 2);
      ctx.rotate((window.modalCurrentRotation * Math.PI) / 180);
      ctx.scale(window.modalCurrentFlipH, window.modalCurrentFlipV);
      ctx.drawImage(img, -img.width / 2, -img.height / 2);
      ctx.restore();
      
      const dataUrl = canvas.toDataURL('image/png');
      previewImg.src = dataUrl;
      captureField.value = dataUrl;
    };
    
    img.src = previewImg.src;
  }

  const modalRotateLeftBtn = document.getElementById('modalRotateLeftBtn');
  const modalRotateRightBtn = document.getElementById('modalRotateRightBtn');
  const modalFlipHorizontalBtn = document.getElementById('modalFlipHorizontalBtn');
  const modalFlipVerticalBtn = document.getElementById('modalFlipVerticalBtn');
  const modalResetImageBtn = document.getElementById('modalResetImageBtn');

  if (modalRotateLeftBtn) {
    modalRotateLeftBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      window.modalCurrentRotation -= 90;
      applyModalTransform();
      applyModalTransformToData();
    });
  }

  if (modalRotateRightBtn) {
    modalRotateRightBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      window.modalCurrentRotation += 90;
      applyModalTransform();
      applyModalTransformToData();
    });
  }

  if (modalFlipHorizontalBtn) {
    modalFlipHorizontalBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      window.modalCurrentFlipH *= -1;
      applyModalTransform();
      applyModalTransformToData();
    });
  }

  if (modalFlipVerticalBtn) {
    modalFlipVerticalBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      window.modalCurrentFlipV *= -1;
      applyModalTransform();
      applyModalTransformToData();
    });
  }

  if (modalResetImageBtn) {
    modalResetImageBtn.addEventListener('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      window.modalCurrentRotation = 0;
      window.modalCurrentFlipH = 1;
      window.modalCurrentFlipV = 1;
      const previewImg = document.getElementById('modalPhotoPreview');
      const captureField = document.getElementById('modalCaptureData');
      if (window.modalOriginalImageSrc && previewImg) {
        previewImg.src = window.modalOriginalImageSrc;
        previewImg.style.transform = '';
        if (captureField) {
          captureField.value = window.modalOriginalImageSrc;
        }
      }
    });
  }
});

// Drag-and-drop + webcam for profile photo
(function () {
  const dropZone = document.getElementById('photoDropZone');
  const fileInput = document.getElementById('profilePhotoInput');
  const previewImg = document.getElementById('photoPreview');
  const profilePhotoCurrent = document.getElementById('profilePhotoCurrent');
  const currentProfileImg = document.getElementById('currentProfileImg');
  const defaultProfileAvatar = document.getElementById('defaultProfileAvatar');
  const captureField = document.getElementById('captureData');
  const removePreviewBtn = document.getElementById('removePreviewBtn');

  if (!dropZone || !fileInput) return;

  let originalImageSrc = null;
  let currentRotation = 0;
  let currentFlipH = 1;
  let currentFlipV = 1;
  const editControls = document.getElementById('photoEditControls');
  const editCanvas = document.createElement('canvas');
  const editCtx = editCanvas.getContext('2d');

  function showPreview(file) {
    const reader = new FileReader();
    reader.onload = function (e) {
      originalImageSrc = e.target.result;
      previewImg.src = e.target.result;
      previewImg.style.display = 'block';
      previewImg.style.visibility = 'visible';
      previewImg.style.opacity = '1';
      // Hide current profile image and default avatar
      if (currentProfileImg) {
        currentProfileImg.style.display = 'none';
        currentProfileImg.style.visibility = 'hidden';
      }
      if (defaultProfileAvatar) {
        defaultProfileAvatar.style.display = 'none';
        defaultProfileAvatar.style.visibility = 'hidden';
      }
      // Show remove button
      if (removePreviewBtn) removePreviewBtn.style.display = 'flex';
      // Keep dropzone visible - don't hide it
      // Reset transformations
      currentRotation = 0;
      currentFlipH = 1;
      currentFlipV = 1;
      applyTransform();
      // Show edit controls
      if (editControls) editControls.style.display = 'block';
      // Convert file to data URL and set in capture field for consistent handling
      // This ensures both drag-and-drop and webcam work the same way
      if (captureField) {
        captureField.value = e.target.result;
      }
      // Clear file input so server uses capture data (consistent with webcam)
      if (fileInput) fileInput.value = '';
    };
    reader.readAsDataURL(file);
  }

  function removePreview() {
    if (editControls) editControls.style.display = 'none';
    previewImg.src = '';
    previewImg.style.display = 'none';
    // Show original profile image or default avatar
    if (currentProfileImg) currentProfileImg.style.display = 'block';
    if (defaultProfileAvatar) defaultProfileAvatar.style.display = 'flex';
    // Hide remove button
    if (removePreviewBtn) removePreviewBtn.style.display = 'none';
    originalImageSrc = null;
    currentRotation = 0;
    currentFlipH = 1;
    currentFlipV = 1;
    if (fileInput) fileInput.value = '';
    if (captureField) captureField.value = '';
  }

  function applyTransform() {
    if (!previewImg || !originalImageSrc) return;
    const transform = `rotate(${currentRotation}deg) scaleX(${currentFlipH}) scaleY(${currentFlipV})`;
    previewImg.style.transform = transform;
  }

  function applyTransformToData() {
    if (!originalImageSrc) return;
    const img = new Image();
    img.onload = function() {
      editCanvas.width = img.width;
      editCanvas.height = img.height;
      editCtx.save();
      
      // Move to center
      editCtx.translate(editCanvas.width / 2, editCanvas.height / 2);
      
      // Apply rotation
      editCtx.rotate((currentRotation * Math.PI) / 180);
      
      // Apply flips
      editCtx.scale(currentFlipH, currentFlipV);
      
      // Draw image
      editCtx.drawImage(img, -img.width / 2, -img.height / 2);
      editCtx.restore();
      
      // Update preview and form data
      const dataUrl = editCanvas.toDataURL('image/png');
      previewImg.src = dataUrl;
      if (captureField) captureField.value = dataUrl;
      if (fileInput) fileInput.value = '';
    };
    img.src = originalImageSrc;
  }

  dropZone.addEventListener('click', () => fileInput.click());
  dropZone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropZone.classList.add('photo-dropzone--hover');
  });
  dropZone.addEventListener('dragleave', () => {
    dropZone.classList.remove('photo-dropzone--hover');
  });
  dropZone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropZone.classList.remove('photo-dropzone--hover');
    const file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      fileInput.files = e.dataTransfer.files;
      showPreview(file);
    }
  });

  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file && file.type.startsWith('image/')) {
      showPreview(file);
    }
  });

  // Webcam logic
  const openBtn = document.getElementById('openWebcamBtn');
  const closeBtn = document.getElementById('closeWebcamBtn');
  const captureBtn = document.getElementById('captureWebcamBtn');
  const video = document.getElementById('webcamVideo');
  const canvas = document.getElementById('webcamCanvas');
  const webcamControls = document.getElementById('webcamControls');
  let stream = null;

  function stopStream() {
    if (stream) {
      stream.getTracks().forEach((t) => t.stop());
      stream = null;
    }
  }

  if (openBtn && closeBtn && captureBtn && video && canvas) {
    openBtn.addEventListener('click', async () => {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
        video.style.display = 'block';
        // Hide current profile image and default avatar
        if (currentProfileImg) {
          currentProfileImg.style.display = 'none';
          currentProfileImg.style.visibility = 'hidden';
        }
        if (defaultProfileAvatar) {
          defaultProfileAvatar.style.display = 'none';
          defaultProfileAvatar.style.visibility = 'hidden';
        }
        // Hide preview if showing
        previewImg.style.display = 'none';
        // Show webcam controls
        if (webcamControls) webcamControls.style.display = 'flex';
      } catch (err) {
        alert('Unable to access webcam.');
      }
    });

    closeBtn.addEventListener('click', () => {
      video.style.display = 'none';
      if (webcamControls) webcamControls.style.display = 'none';
      stopStream();
      // Show original profile image or default avatar
      if (currentProfileImg) {
        currentProfileImg.style.display = 'block';
        currentProfileImg.style.visibility = 'visible';
      }
      if (defaultProfileAvatar) {
        defaultProfileAvatar.style.display = 'flex';
        defaultProfileAvatar.style.visibility = 'visible';
      }
    });

    captureBtn.addEventListener('click', () => {
      if (!stream) return;
      const width = video.videoWidth || 320;
      const height = video.videoHeight || 240;
      canvas.width = width;
      canvas.height = height;
      const ctx = canvas.getContext('2d');
      ctx.drawImage(video, 0, 0, width, height);
      const dataUrl = canvas.toDataURL('image/png');
      originalImageSrc = dataUrl;
      previewImg.src = dataUrl;
      previewImg.style.display = 'block';
      previewImg.style.visibility = 'visible';
      previewImg.style.opacity = '1';
      // Hide video
      video.style.display = 'none';
      // Hide webcam controls
      if (webcamControls) webcamControls.style.display = 'none';
      // Stop stream
      stopStream();
      // Hide current profile image and default avatar
      if (currentProfileImg) {
        currentProfileImg.style.display = 'none';
        currentProfileImg.style.visibility = 'hidden';
      }
      if (defaultProfileAvatar) {
        defaultProfileAvatar.style.display = 'none';
        defaultProfileAvatar.style.visibility = 'hidden';
      }
      // Show remove button
      if (removePreviewBtn) removePreviewBtn.style.display = 'flex';
      // Reset transformations
      currentRotation = 0;
      currentFlipH = 1;
      currentFlipV = 1;
      applyTransform();
      // Show edit controls
      if (editControls) editControls.style.display = 'block';
      if (captureField) captureField.value = dataUrl;
      // Clear file input so server knows to use capture
      fileInput.value = '';
    });
  }

  // Remove preview button
  if (removePreviewBtn) {
    removePreviewBtn.addEventListener('click', removePreview);
  }

  // Image processing controls
  const rotateLeftBtn = document.getElementById('rotateLeftBtn');
  const rotateRightBtn = document.getElementById('rotateRightBtn');
  const flipHorizontalBtn = document.getElementById('flipHorizontalBtn');
  const flipVerticalBtn = document.getElementById('flipVerticalBtn');
  const resetImageBtn = document.getElementById('resetImageBtn');

  if (rotateLeftBtn) {
    rotateLeftBtn.addEventListener('click', () => {
      currentRotation -= 90;
      applyTransform();
      applyTransformToData();
    });
  }

  if (rotateRightBtn) {
    rotateRightBtn.addEventListener('click', () => {
      currentRotation += 90;
      applyTransform();
      applyTransformToData();
    });
  }

  if (flipHorizontalBtn) {
    flipHorizontalBtn.addEventListener('click', () => {
      currentFlipH *= -1;
      applyTransform();
      applyTransformToData();
    });
  }

  if (flipVerticalBtn) {
    flipVerticalBtn.addEventListener('click', () => {
      currentFlipV *= -1;
      applyTransform();
      applyTransformToData();
    });
  }

  if (resetImageBtn) {
    resetImageBtn.addEventListener('click', () => {
      currentRotation = 0;
      currentFlipH = 1;
      currentFlipV = 1;
      if (originalImageSrc) {
        previewImg.src = originalImageSrc;
        if (captureField) {
          // If original was from file input, clear capture field
          if (fileInput && fileInput.files.length > 0) {
            captureField.value = '';
          } else {
            captureField.value = originalImageSrc;
          }
        }
      }
      applyTransform();
    });
  }

  // Address state/city dropdown functionality - removed since address fields are no longer in the form
  // The code below is safe because it checks if elements exist before attaching listeners
  // If address fields are re-added in the future, this code will automatically work again
  const stateSelect = document.getElementById('address_state');
  const citySelect = document.getElementById('address_city');
  
  if (stateSelect && citySelect && typeof citiesByState !== 'undefined') {
    // Update city options when state changes
    stateSelect.addEventListener('change', function() {
      const selectedState = this.value;
      
      // Clear current city selection
      citySelect.innerHTML = '<option value="">Select City</option>';
      
      // Get cities for selected state
      if (selectedState && citiesByState[selectedState]) {
        const cities = citiesByState[selectedState];
        cities.forEach(function(city) {
          const option = document.createElement('option');
          option.value = city;
          option.textContent = city;
          citySelect.appendChild(option);
        });
      } else {
        // If no state selected, show all cities
        Object.values(citiesByState).flat().sort().forEach(function(city) {
          const option = document.createElement('option');
          option.value = city;
          option.textContent = city;
          citySelect.appendChild(option);
        });
      }
    });
  }

  // Phone number validation and formatting
  const phoneInput = document.querySelector('input[name="phone"]');
  if (phoneInput) {
    phoneInput.addEventListener('input', function(e) {
      let value = e.target.value.replace(/[^\d\-]/g, ''); // Remove non-digits and non-dashes
      
      // Auto-format: Add dash after area code (3 digits)
      if (value.length > 3 && value[3] !== '-') {
        value = value.slice(0, 3) + '-' + value.slice(3);
      }
      
      e.target.value = value;
    });

    phoneInput.addEventListener('blur', function(e) {
      const value = e.target.value.replace(/[\s\-]/g, '');
      if (value !== '' && (!/^0\d{9,10}$/.test(value))) {
        e.target.setCustomValidity('Please enter a valid Malaysian phone number (10-11 digits starting with 0)');
        e.target.reportValidity();
      } else {
        e.target.setCustomValidity('');
      }
    });
  }

  // Street address validation
  const streetInput = document.querySelector('input[name="address_street"]');
  if (streetInput) {
    streetInput.addEventListener('blur', function(e) {
      const value = e.target.value.trim();
      if (value !== '') {
        if (value.length < 5) {
          e.target.setCustomValidity('Street address must be at least 5 characters long');
          e.target.reportValidity();
        } else if (value.length > 200) {
          e.target.setCustomValidity('Street address must be less than 200 characters');
          e.target.reportValidity();
        } else if (!/[a-zA-Z]/.test(value)) {
          e.target.setCustomValidity('Street address must contain at least one letter');
          e.target.reportValidity();
        } else {
          e.target.setCustomValidity('');
        }
      } else {
        e.target.setCustomValidity('');
      }
    });
  }

  // Copy address to clipboard functionality
  const copyAddressBtn = document.getElementById('copyAddressBtn');
  if (copyAddressBtn) {
    copyAddressBtn.addEventListener('click', function() {
      const addressLines = document.querySelectorAll('.address-line');
      let addressText = '';
      addressLines.forEach((line, index) => {
        addressText += line.textContent.trim();
        if (index < addressLines.length - 1) {
          addressText += ', ';
        }
      });
      
      // Copy to clipboard
      navigator.clipboard.writeText(addressText).then(() => {
        // Show feedback
        const originalText = this.innerHTML;
        this.innerHTML = '<i class="fi fi-rs-check"></i> Copied!';
        this.style.background = '#dcfce7';
        this.style.borderColor = '#16a34a';
        this.style.color = '#166534';
        
        setTimeout(() => {
          this.innerHTML = originalText;
          this.style.background = '';
          this.style.borderColor = '';
          this.style.color = '';
        }, 2000);
      }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy address. Please try again.');
      });
    });
  }

  // Password change form enhancements
  const passwordForm = document.getElementById('passwordChangeForm');
  if (passwordForm) {
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('new_password_confirm');
    const passwordStrengthFill = document.getElementById('passwordStrengthFill');
    const passwordStrengthText = document.getElementById('passwordStrengthText');
    const passwordRequirements = document.querySelectorAll('.password-req-item');
    const passwordMatch = document.getElementById('passwordMatch');
    const clearBtn = document.getElementById('clearPasswordForm');

    // Initialize password toggles on page load
    initializePasswordToggles();

    // Password strength checker
    function checkPasswordStrength(password) {
      let strength = 0;
      const checks = {
        length: password.length >= 8,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /\d/.test(password),
        special: /[@$!%*#?&^_\-]/.test(password)
      };

      // Update requirement indicators
      passwordRequirements.forEach(item => {
        const req = item.getAttribute('data-requirement');
        const icon = item.querySelector('.req-icon');
        if (checks[req]) {
          item.classList.add('valid');
          if (icon) {
            icon.classList.remove('fi-rs-cross-small');
            icon.classList.add('fi-rs-check');
          }
          strength++;
        } else {
          item.classList.remove('valid');
          if (icon) {
            icon.classList.remove('fi-rs-check');
            icon.classList.add('fi-rs-cross-small');
          }
        }
      });

      // Update strength bar
      if (strength === 0) {
        passwordStrengthFill.className = 'password-strength-fill';
        passwordStrengthText.textContent = 'Password strength';
      } else if (strength <= 2) {
        passwordStrengthFill.className = 'password-strength-fill weak';
        passwordStrengthText.textContent = 'Weak password';
      } else if (strength <= 4) {
        passwordStrengthFill.className = 'password-strength-fill medium';
        passwordStrengthText.textContent = 'Medium strength';
      } else {
        passwordStrengthFill.className = 'password-strength-fill strong';
        passwordStrengthText.textContent = 'Strong password';
      }
    }

    // Password match checker
    function checkPasswordMatch() {
      if (confirmPassword.value === '' && newPassword.value === '') {
        passwordMatch.classList.remove('show');
        return;
      }
      
      if (confirmPassword.value === newPassword.value && newPassword.value !== '') {
        passwordMatch.classList.add('show');
        confirmPassword.setCustomValidity('');
      } else {
        passwordMatch.classList.remove('show');
        if (confirmPassword.value !== '') {
          confirmPassword.setCustomValidity('Passwords do not match');
        }
      }
    }

    // Event listeners
    newPassword.addEventListener('input', function() {
      checkPasswordStrength(this.value);
      checkPasswordMatch();
    });

    confirmPassword.addEventListener('input', function() {
      checkPasswordMatch();
    });

    confirmPassword.addEventListener('blur', function() {
      if (this.value !== '' && this.value !== newPassword.value) {
        this.reportValidity();
      }
    });


    // Form validation
    passwordForm.addEventListener('submit', function(e) {
      if (newPassword.value !== confirmPassword.value) {
        e.preventDefault();
        confirmPassword.setCustomValidity('Passwords do not match');
        confirmPassword.reportValidity();
        return false;
      }
      
      // Check if password meets all requirements
      const checks = {
        length: newPassword.value.length >= 8,
        uppercase: /[A-Z]/.test(newPassword.value),
        lowercase: /[a-z]/.test(newPassword.value),
        number: /\d/.test(newPassword.value),
        special: /[@$!%*#?&^_\-]/.test(newPassword.value)
      };
      
      if (!Object.values(checks).every(v => v === true)) {
        e.preventDefault();
        newPassword.setCustomValidity('Password must meet all requirements');
        newPassword.reportValidity();
        return false;
      }
    });
  }

  // Flag to prevent multiple simultaneous updates
  let isUpdatingPhotos = false;

  // Update all profile photos (header, dashboard, and update profile section) after successful profile update
  function updateAllProfilePhotos() {
    // Prevent multiple simultaneous updates
    if (isUpdatingPhotos) {
      return;
    }
    isUpdatingPhotos = true;
    
    const dashboardImg = document.getElementById('dashboardProfileImg');
    const dashboardIcon = document.getElementById('dashboardProfileIcon');
    const currentProfileImg = document.getElementById('currentProfileImg');
    const defaultProfileAvatar = document.getElementById('defaultProfileAvatar');
    const headerAvatar = document.getElementById('headerAvatar');
    
    // Determine the photo source - check currentProfileImg first, then dashboardImg
    let photoSrc = null;
    if (currentProfileImg && currentProfileImg.src && currentProfileImg.src !== '') {
      photoSrc = currentProfileImg.src.split('?')[0];
    } else if (dashboardImg && dashboardImg.src && dashboardImg.src !== '') {
      photoSrc = dashboardImg.src.split('?')[0];
    }
    
    // Use a single cache-busting parameter for all images in this update cycle
    const cacheBuster = Date.now();
    const newSrc = photoSrc ? photoSrc + '?v=' + cacheBuster : null;
    
    if (newSrc) {
      // Photo exists - update all three locations
      
      // 1. Update Dashboard
      if (dashboardImg) {
        dashboardImg.src = newSrc;
        dashboardImg.style.display = 'block';
        dashboardImg.style.visibility = 'visible';
        dashboardImg.style.opacity = '1';
      }
      if (dashboardIcon) {
        dashboardIcon.style.display = 'none';
      }
      
      // 2. Update Header
      if (headerAvatar) {
        let headerImg = headerAvatar.querySelector('img');
        if (headerImg) {
          headerImg.src = newSrc;
        } else {
          // Create img element if it doesn't exist
          headerImg = document.createElement('img');
          headerImg.src = newSrc;
          headerImg.alt = 'Profile photo';
          headerAvatar.innerHTML = ''; // Clear initials
          headerAvatar.appendChild(headerImg);
        }
      }
      
      // 3. Update Current Profile Photo (in update profile section)
      if (currentProfileImg) {
        currentProfileImg.src = newSrc;
        currentProfileImg.style.display = 'block';
        currentProfileImg.style.visibility = 'visible';
      }
      if (defaultProfileAvatar) {
        defaultProfileAvatar.style.display = 'none';
      }
    } else {
      // No photo - show initials in all locations
      
      // 1. Update Dashboard
      if (dashboardIcon) {
        dashboardIcon.style.display = 'flex';
      }
      if (dashboardImg) {
        dashboardImg.style.display = 'none';
      }
      
      // 2. Update Header
      if (headerAvatar) {
        const headerImg = headerAvatar.querySelector('img');
        if (headerImg) {
          headerImg.remove();
        }
        // Get username from page to show initials
        const username = document.querySelector('.account-summary-name')?.textContent || 
                        document.querySelector('.profile-menu__header strong')?.textContent || '';
        const parts = username.trim().split(/\s+/);
        const first = parts[0] ? parts[0][0] : '';
        const second = parts[1] ? parts[1][0] : '';
        const initials = (first + second).toUpperCase() || 'U';
        headerAvatar.textContent = initials;
      }
      
      // 3. Update Current Profile Photo (in update profile section)
      if (defaultProfileAvatar) {
        defaultProfileAvatar.style.display = 'flex';
      }
      if (currentProfileImg) {
        currentProfileImg.style.display = 'none';
      }
    }
    
    // Reset flag after a short delay to allow images to load
    setTimeout(() => {
      isUpdatingPhotos = false;
    }, 1000);
  }

  // Check for profile update success message and update all profile photos
  const profileSuccess = document.querySelector('.alert--success');
  if (profileSuccess && profileSuccess.textContent.includes('Profile updated successfully')) {
    // Wait for the page to fully load, then update all photos once
    setTimeout(() => {
      updateAllProfilePhotos();
    }, 300);
  }
})();