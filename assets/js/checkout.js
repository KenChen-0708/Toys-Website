function toggleAddressForm(e) {
    e.preventDefault();
    const form = document.getElementById('addressForm');

    form.classList.toggle('active');

    // Don't automatically add required attributes - only validate when saving
    // This prevents blocking the checkout process if user just types but doesn't save
}

// Highlight selected address
document.querySelectorAll('.address-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.address-option').forEach(opt => {
            opt.style.borderColor = '#ddd';
            opt.style.boxShadow = 'none';
        });
        this.style.borderColor = '#4CAF50';
        this.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.2)';
    });
});

// Auto-hide alerts after 5 seconds
setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);

// Add progressive enhancement - if JavaScript fails, form still works
document.body.classList.add('js-enabled');

// Field validation function
function validateField(field) {
    const value = field.value.trim();
    const name = field.name;
    let isValid = true;
    let errorMessage = '';

    // Remove existing error message
    const existingError = field.parentNode.querySelector('.error-message');
    if (existingError) {
        existingError.remove();
    }

    // Validate based on field name
    switch (name) {
        case 'address_line1':
            if (value && (value.length < 5 || value.length > 100)) {
                isValid = false;
                errorMessage = 'Address Line 1 must be between 5-100 characters';
            }
            break;

        case 'address_line2':
            if (value && value.length > 100) {
                isValid = false;
                errorMessage = 'Address Line 2 must not exceed 100 characters';
            }
            break;

        case 'city':
            if (value && (value.length < 2 || value.length > 50)) {
                isValid = false;
                errorMessage = 'City must be between 2-50 characters';
            } else if (value && !/^[A-Za-z\s\-']+$/.test(value)) {
                isValid = false;
                errorMessage = 'City can only contain letters, spaces, hyphens, and apostrophes';
            } else if (value && /\d/.test(value)) {
                isValid = false;
                errorMessage = 'City cannot contain numbers';
            }
            break;

        case 'state':
            if (value && value.length > 50) {
                isValid = false;
                errorMessage = 'State must not exceed 50 characters';
            } else if (value && !/^[A-Za-z\s\-']+$/.test(value)) {
                isValid = false;
                errorMessage = 'State can only contain letters, spaces, hyphens, and apostrophes';
            } else if (value && /\d/.test(value)) {
                isValid = false;
                errorMessage = 'State cannot contain numbers';
            }
            break;

        case 'postal_code':
            if (value && !/^[0-9]{5}$/.test(value)) {
                isValid = false;
                errorMessage = 'Postal code must be exactly 5 digits';
            }
            break;

        case 'country':
            if (value && (value.length < 2 || value.length > 50)) {
                isValid = false;
                errorMessage = 'Country must be between 2-50 characters';
            } else if (value && !/^[A-Za-z\s\-']+$/.test(value)) {
                isValid = false;
                errorMessage = 'Country can only contain letters, spaces, hyphens, and apostrophes';
            }
            break;
    }

    // Show error if validation failed
    if (!isValid) {
        field.style.borderColor = '#ef4444';
        const errorDiv = document.createElement('div');
        errorDiv.className = 'error-message';
        errorDiv.style.color = '#ef4444';
        errorDiv.style.fontSize = '12px';
        errorDiv.style.marginTop = '4px';
        errorDiv.textContent = errorMessage;
        field.parentNode.appendChild(errorDiv);
    } else {
        field.style.borderColor = '#10b981';
    }

    return isValid;
}

// Show error message below a specific field
function showFieldError(field, message) {
    field.style.borderColor = '#ef4444';
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.style.color = '#ef4444';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '4px';
    errorDiv.textContent = message;
    field.parentNode.appendChild(errorDiv);
}

// Show multiple field errors (for save validation)
function showFieldErrors(errors) {
    // Clear all existing errors first
    document.querySelectorAll('.error-message').forEach(error => error.remove());

    // Map error messages to fields and show them
    errors.forEach(error => {
        if (error.includes('Address Line 1')) {
            const field = document.querySelector('input[name="address_line1"]');
            if (field) showFieldError(field, error);
        } else if (error.includes('Address Line 2')) {
            const field = document.querySelector('input[name="address_line2"]');
            if (field) showFieldError(field, error);
        } else if (error.includes('City')) {
            const field = document.querySelector('input[name="city"]');
            if (field) showFieldError(field, error);
        } else if (error.includes('State')) {
            const field = document.querySelector('input[name="state"]');
            if (field) showFieldError(field, error);
        } else if (error.includes('Postal code')) {
            const field = document.querySelector('input[name="postal_code"]');
            if (field) showFieldError(field, error);
        } else if (error.includes('Country')) {
            const field = document.querySelector('input[name="country"]');
            if (field) showFieldError(field, error);
        }
    });
}

// Prevent form submission conflicts and add validation
document.addEventListener('submit', function(e) {
    console.log('Form submission detected:', e.target);

    // Only prevent default for AJAX-handled forms when JS is working
    if (e.target.querySelector('button[name="add_address"]') || e.target.querySelector('button[name="apply_voucher"]')) {
        console.log('AJAX form detected, allowing default behavior');
        // These are handled by AJAX, don't prevent
        return;
    }

    // Validate main checkout form
    if (e.target.querySelector('button[name="place_order"]')) {
        console.log('Checkout form submission detected');

        // Clear ALL address form inputs to prevent any validation blocking
        const addressForm = document.getElementById('addressForm');
        if (addressForm) {
            const addressInputs = addressForm.querySelectorAll('input[type="text"]');
            addressInputs.forEach(input => {
                input.value = '';
                input.removeAttribute('required');
                input.style.borderColor = '';
                // Remove any error messages
                const errorMsg = input.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
            console.log('Cleared all address form inputs and errors');
        }

        // Check if user selected an existing address
        const selectedAddress = document.querySelector('input[name="shipping_address_id"]:checked');
        if (!selectedAddress) {
            console.log('No address selected, preventing submission');
            e.preventDefault();
            alert('Please select a shipping address before proceeding to payment.');
            return false;
        } else {
            console.log('Address selected, allowing submission');
        }
    }
});

// AJAX for voucher application
document.addEventListener('DOMContentLoaded', function() {
    // Add real-time validation for address form
    const addressForm = document.getElementById('addressForm');
    if (addressForm) {
        const inputs = addressForm.querySelectorAll('input[type="text"]');
        inputs.forEach(input => {
            // Strict input filtering for text-only fields (city, state, country)
            if (['city', 'state', 'country'].includes(input.name)) {
                // Prevent any numbers or invalid characters during typing
                input.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    // Only allow letters, spaces, hyphens, apostrophes, and control keys
                    if (!/[A-Za-z\s\-']/.test(char) && e.which !== 8 && e.which !== 0 && e.which !== 9) {
                        e.preventDefault();
                        return false;
                    }
                });

                // Handle paste events - strip out any numbers or invalid characters
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const cleaned = paste.replace(/[^A-Za-z\s\-']/g, '');
                    this.value = cleaned;
                    // Trigger input event to clear any error styling
                    this.dispatchEvent(new Event('input'));
                });

                // Additional sanitization on input change
                input.addEventListener('input', function(e) {
                    const originalValue = this.value;
                    const cleanedValue = originalValue.replace(/[^A-Za-z\s\-']/g, '');
                    if (originalValue !== cleanedValue) {
                        this.value = cleanedValue;
                    }
                });
            }

            // Strict number-only filtering for postal code
            if (input.name === 'postal_code') {
                // Prevent any non-numbers during typing
                input.addEventListener('keypress', function(e) {
                    const char = String.fromCharCode(e.which);
                    // Only allow numbers and control keys
                    if (!/[0-9]/.test(char) && e.which !== 8 && e.which !== 0 && e.which !== 9) {
                        e.preventDefault();
                        return false;
                    }
                });

                // Handle paste events - only keep numbers
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const paste = (e.clipboardData || window.clipboardData).getData('text');
                    const cleaned = paste.replace(/[^0-9]/g, '');
                    this.value = cleaned.substring(0, 5); // Limit to 5 digits
                    // Trigger input event to clear any error styling
                    this.dispatchEvent(new Event('input'));
                });

                // Additional sanitization on input change
                input.addEventListener('input', function(e) {
                    const originalValue = this.value;
                    const cleanedValue = originalValue.replace(/[^0-9]/g, '').substring(0, 5);
                    if (originalValue !== cleanedValue) {
                        this.value = cleanedValue;
                    }
                });
            }

            input.addEventListener('blur', function() {
                validateField(this);
            });

            input.addEventListener('input', function() {
                // Clear previous error styling when user starts typing
                this.style.borderColor = '';
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) {
                    errorMsg.remove();
                }
            });
        });

        // Add clear form functionality
        const clearBtn = document.getElementById('clearAddressBtn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function() {
                // Clear all form inputs
                inputs.forEach(input => {
                    input.value = '';
                    input.style.borderColor = '';
                    const errorMsg = input.parentNode.querySelector('.error-message');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                });
                // Reset country to default
                const countryInput = addressForm.querySelector('input[name="country"]');
                if (countryInput) {
                    countryInput.value = 'Malaysia';
                }
            });
        }
    }

    const voucherForm = document.querySelector('form[method="POST"]');
    const applyVoucherBtn = document.querySelector('button[name="apply_voucher"]');

    if (applyVoucherBtn) {
        applyVoucherBtn.addEventListener('click', function(e) {
            e.preventDefault();

            const voucherCode = document.querySelector('input[name="voucher_code"]').value;
            const formData = new FormData();
            formData.append('apply_voucher', '1');
            formData.append('voucher_code', voucherCode);

            // Show loading state
            applyVoucherBtn.disabled = true;
            applyVoucherBtn.innerHTML = 'Applying...';

            fetch('checkout_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    // Remove existing alerts
                    document.querySelectorAll('.alert').forEach(a => a.remove());

                    if (data.success) {
                        // Show success message
                        showAlert(data.message, 'success');

                        // Update price summary
                        updatePriceSummary(data.cart_total, data.voucher_discount, data.final_total, voucherCode);
                    } else {
                        // Show error message
                        showAlert(data.message, 'error');
                    }

                    // Reset button
                    applyVoucherBtn.disabled = false;
                    applyVoucherBtn.innerHTML = 'Apply';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred. Please try again.', 'error');
                    applyVoucherBtn.disabled = false;
                    applyVoucherBtn.innerHTML = 'Apply';
                });
        });
    }

    // AJAX for adding new address
    const addAddressBtn = document.querySelector('button[name="add_address"]');

    if (addAddressBtn) {
        addAddressBtn.addEventListener('click', function(e) {
            e.preventDefault();

            // Comprehensive client-side validation - only runs when saving
            const addressLine1 = document.querySelector('input[name="address_line1"]').value.trim();
            const city = document.querySelector('input[name="city"]').value.trim();
            const country = document.querySelector('input[name="country"]').value.trim();
            const state = document.querySelector('input[name="state"]').value.trim();
            const postalCode = document.querySelector('input[name="postal_code"]').value.trim();
            const addressLine2 = document.querySelector('input[name="address_line2"]').value.trim();

            // Validate all fields using the same logic as the validateField function
            let validationErrors = [];

            if (!addressLine1) {
                validationErrors.push('Address Line 1 is required');
            } else if (addressLine1.length < 5 || addressLine1.length > 100) {
                validationErrors.push('Address Line 1 must be between 5-100 characters');
            }

            if (!city) {
                validationErrors.push('City is required');
            } else if (city.length < 2 || city.length > 50) {
                validationErrors.push('City must be between 2-50 characters');
            } else if (!/^[A-Za-z\s\-']+$/.test(city) || /\d/.test(city)) {
                validationErrors.push('City cannot contain numbers or special characters');
            }

            if (!country) {
                validationErrors.push('Country is required');
            } else if (country.length < 2 || country.length > 50) {
                validationErrors.push('Country must be between 2-50 characters');
            } else if (!/^[A-Za-z\s\-']+$/.test(country) || /\d/.test(country)) {
                validationErrors.push('Country cannot contain numbers or special characters');
            }

            if (!state) {
                validationErrors.push('State is required');
            } else if (state.length > 50 || !/^[A-Za-z\s\-']+$/.test(state) || /\d/.test(state)) {
                validationErrors.push('State cannot contain numbers or special characters');
            }

            if (!postalCode) {
                validationErrors.push('Postal code is required');
            } else if (!/^[0-9]{5}$/.test(postalCode)) {
                validationErrors.push('Postal code must be exactly 5 digits');
            }

            if (addressLine2 && addressLine2.length > 100) {
                validationErrors.push('Address Line 2 must not exceed 100 characters');
            }

            if (validationErrors.length > 0) {
                // Show errors below each field instead of alert box
                showFieldErrors(validationErrors);
                return;
            }

            const formData = new FormData();
            formData.append('add_address', '1');
            formData.append('address_line1', addressLine1);
            formData.append('address_line2', addressLine2);
            formData.append('city', city);
            formData.append('state', state);
            formData.append('postal_code', postalCode);
            formData.append('country', country);

            // Show loading state
            addAddressBtn.disabled = true;
            addAddressBtn.innerHTML = 'Saving...';

            fetch('checkout_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Address addition response:', data); // Debug log

                    // Remove existing alerts
                    document.querySelectorAll('.alert').forEach(a => a.remove());

                    if (data.success) {
                        // Show success message
                        showAlert(data.message, 'success');

                        // Add new address to the list
                        addAddressToList(data.address);

                        // Clear form
                        document.querySelector('input[name="address_line1"]').value = '';
                        document.querySelector('input[name="address_line2"]').value = '';
                        document.querySelector('input[name="city"]').value = '';
                        document.querySelector('input[name="state"]').value = '';
                        document.querySelector('input[name="postal_code"]').value = '';

                        // Hide form
                        document.getElementById('addressForm').classList.remove('active');
                    } else {
                        // Show error message
                        showAlert(data.message, 'error');
                    }

                    // Reset button
                    addAddressBtn.disabled = false;
                    addAddressBtn.innerHTML = 'Save Address';
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('An error occurred while saving the address. Please try again.', 'error');
                    addAddressBtn.disabled = false;
                    addAddressBtn.innerHTML = 'Save Address';
                });
        });
    }
});

function showAlert(message, type) {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    document.body.appendChild(alert);

    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

function updatePriceSummary(cartTotal, voucherDiscount, finalTotal, voucherCode) {
    const priceSummary = document.querySelector('.price-summary');
    
    // Calculate shipping fee
    const freeShippingThreshold = 15.00;
    const defaultShippingFee = 5.00;
    const shippingFee = cartTotal >= freeShippingThreshold ? 0 : defaultShippingFee;

    let html = `
        <div class="price-row">
            <span>Subtotal</span>
            <span>RM${parseFloat(cartTotal).toFixed(2)}</span>
        </div>
        <div class="price-row">
            <span>Shipping</span>
            <span>${shippingFee > 0 ? 'RM' + shippingFee.toFixed(2) : '<span style="color: #28a745; font-weight: 600;">FREE</span>'}</span>
        </div>
    `;

    // Show free shipping message if applicable
    if (cartTotal < freeShippingThreshold && shippingFee > 0) {
        const amountNeeded = freeShippingThreshold - cartTotal;
        html += `
            <div class="price-row" style="font-size: 12px; color: #666; font-style: italic;">
                <span>Free shipping on orders over RM${freeShippingThreshold.toFixed(2)}</span>
                <span>Add RM${amountNeeded.toFixed(2)} more</span>
            </div>
        `;
    }

    // Ensure discount doesn't exceed cart total
    let adjustedVoucherDiscount = voucherDiscount;
    if (adjustedVoucherDiscount > cartTotal) {
        adjustedVoucherDiscount = cartTotal;
    }

    if (adjustedVoucherDiscount > 0) {
        html += `
            <div class="price-row discount">
                <span>Discount</span>
                <span>-RM${parseFloat(adjustedVoucherDiscount).toFixed(2)}</span>
            </div>
            <input type="hidden" name="voucher_code_hidden" value="${voucherCode}">
        `;
    }

    // Recalculate final total with shipping and prevent negative values
    let calculatedTotal = cartTotal + shippingFee - adjustedVoucherDiscount;
    
    // Ensure final total is never negative (minimum should be shipping fee if applicable)
    if (calculatedTotal < 0) {
        calculatedTotal = shippingFee;
    }

    html += `
        <div class="price-row total">
            <span>Total</span>
            <span>RM${calculatedTotal.toFixed(2)}</span>
        </div>
    `;

    priceSummary.innerHTML = html;
}

function addAddressToList(address) {
    const addressGrid = document.querySelector('#addressGrid');
    const noAddressesMessage = document.querySelector('#noAddressesMessage');

    // Remove "no addresses" message if it exists
    if (noAddressesMessage) {
        noAddressesMessage.remove();
    }

    const addressHTML = `
        <label class="address-option newly-added">
            <input type="radio" 
                   name="shipping_address_id" 
                   value="${address.shipping_address_id}" 
                   required
                   checked>
            <div class="address-content">
                <div class="address-line primary">
                    ${address.address_line1}
                </div>
                ${address.address_line2 ? `<div class="address-line">${address.address_line2}</div>` : ''}
                <div class="address-line">
                    ${address.city}, ${address.state} ${address.postal_code}
                </div>
                <div class="address-line">
                    ${address.country}
                </div>
            </div>
            <div class="check-icon">✓</div>
        </label>
    `;

    addressGrid.insertAdjacentHTML('afterbegin', addressHTML);

    // Re-attach event listeners
    document.querySelectorAll('.address-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.address-option').forEach(opt => {
                opt.style.borderColor = '#ddd';
                opt.style.boxShadow = 'none';
                opt.classList.remove('newly-added');
            });
            this.style.borderColor = '#4CAF50';
            this.style.boxShadow = '0 4px 12px rgba(76, 175, 80, 0.2)';
        });
    });

    // Remove the highlight class after animation completes
    setTimeout(() => {
        const newAddressOption = addressGrid.querySelector('.newly-added');
        if (newAddressOption) {
            newAddressOption.classList.remove('newly-added');
        }
    }, 2000);
}

// Prevent browser back button after accessing checkout
// This prevents users from going back after placing an order
if (window.history && window.history.pushState) {
    window.history.pushState('forward', null, window.location.href);
    window.addEventListener('popstate', function() {
        window.history.pushState('forward', null, window.location.href);
    });
}