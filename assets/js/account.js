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

// Tab navigation for account page
(function () {
  const tabs = document.querySelectorAll('.account__tab');
  const tabContents = document.querySelectorAll('.tab__content');

  function switchTab(targetId) {
    // Remove active class from all tabs
    tabs.forEach(tab => {
      tab.classList.remove('active-tab');
    });

    // Remove active class from all content sections
    tabContents.forEach(content => {
      content.classList.remove('active-tab');
    });

    // Add active class to clicked tab
    const activeTab = document.querySelector(`[data-target="${targetId}"]`);
    if (activeTab) {
      activeTab.classList.add('active-tab');
    }

    // Add active class to target content section
    const targetContent = document.querySelector(targetId);
    if (targetContent) {
      targetContent.classList.add('active-tab');
    }
  }

  // Add click event listeners to all tabs
  tabs.forEach(tab => {
    tab.addEventListener('click', function() {
      const targetId = this.getAttribute('data-target');
      if (targetId) {
        switchTab(targetId);
      }
    });
  });

  // Handle hash navigation (e.g., #update-profile from address section)
  if (window.location.hash) {
    const hash = window.location.hash;
    const targetTab = document.querySelector(`[data-target="${hash}"]`);
    if (targetTab) {
      switchTab(hash);
    }
  }
})();

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

  // Handle city dropdown filtering based on state selection
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
    const passwordToggles = document.querySelectorAll('.password-toggle');
    const passwordStrengthFill = document.getElementById('passwordStrengthFill');
    const passwordStrengthText = document.getElementById('passwordStrengthText');
    const passwordRequirements = document.querySelectorAll('.password-req-item');
    const passwordMatch = document.getElementById('passwordMatch');
    const clearBtn = document.getElementById('clearPasswordForm');

    // Password toggle functionality
    passwordToggles.forEach(toggle => {
      toggle.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fi-rs-eye');
          icon.classList.add('fi-rs-eye-crossed');
        } else {
          input.type = 'password';
          icon.classList.remove('fi-rs-eye-crossed');
          icon.classList.add('fi-rs-eye');
        }
      });
    });

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

    // Clear form
    if (clearBtn) {
      clearBtn.addEventListener('click', function() {
        passwordForm.reset();
        passwordStrengthFill.className = 'password-strength-fill';
        passwordStrengthText.textContent = 'Password strength';
        passwordRequirements.forEach(item => {
          item.classList.remove('valid');
          const icon = item.querySelector('.req-icon');
          if (icon) {
            icon.classList.remove('fi-rs-check');
            icon.classList.add('fi-rs-cross-small');
          }
        });
        passwordMatch.classList.remove('show');
        passwordToggles.forEach(toggle => {
          const icon = toggle.querySelector('i');
          icon.classList.remove('fi-rs-eye-crossed');
          icon.classList.add('fi-rs-eye');
          const targetId = toggle.getAttribute('data-target');
          document.getElementById(targetId).type = 'password';
        });
      });
    }

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