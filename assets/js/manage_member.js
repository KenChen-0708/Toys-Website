// Manage Member behaviors (requires jQuery)

(function ($) {
	$(function () {
		// Search debounce → reload with q param
		let searchTimer = null;
		$('#mm-search').on('input', function () {
			clearTimeout(searchTimer);
			const q = $(this).val().trim();
			searchTimer = setTimeout(() => {
				const url = new URL(window.location.href);
				if (q) { url.searchParams.set('q', q); } else { url.searchParams.delete('q'); }
				url.searchParams.delete('page');
				window.location.href = url.toString();
			}, 300);
		});

		// Per-page change
		$('#mm-per-page').on('change', function () {
			const per = $(this).val();
			const url = new URL(window.location.href);
			url.searchParams.set('per_page', per);
			url.searchParams.delete('page');
			window.location.href = url.toString();
		});

		// Modal helpers
		function openEditModal(data) {
			$('#edit_member_id').val(data.id);
			$('#edit_name').val(data.name);
			$('#edit_email').val(data.email);
			$('#edit_phone').val(data.phone);
			$('#edit_address').val(data.address);
			$('#edit_password').val('');
			$('#editModal').css('display', 'flex');
		}
		function closeEditModal() {
			$('#editModal').hide();
		}

		// Expose to window for inline onclick fallback
		window.mmOpenEdit = openEditModal;
		window.mmCloseEdit = closeEditModal;

		// Close modal on backdrop click
		$('#editModal').on('click', function (e) {
			if (e.target === this) closeEditModal();
		});

		// Enhance delete confirmation
		$('form[data-confirm]').on('submit', function () {
			const msg = $(this).attr('data-confirm') || 'Are you sure?';
			return window.confirm(msg);
		});

		// Block/Unblock member functionality
		window.toggleBlockMember = function (memberId, currentStatus, memberName) {
			const action = currentStatus == 1 ? 'unblock' : 'block';
			const actionText = action === 'block' ? 'block' : 'unblock';
			const confirmMsg = `Are you sure you want to ${actionText} the member "${memberName}"?`;

			if (!confirm(confirmMsg)) {
				return;
			}

			// Send AJAX request
			$.ajax({
				url: 'block_member.php',
				method: 'POST',
				contentType: 'application/json',
				dataType: 'json',
				data: JSON.stringify({
					member_id: memberId,
					action: action
				}),
				success: function (response) {
					if (response.success) {
						window.location.reload();
					} else {
						alert('Error: ' + response.message);
					}
				},
				error: function (xhr) {
					let errorMsg = 'An error occurred while trying to ' + actionText + ' the member.';
					try {
						const response = JSON.parse(xhr.responseText);
						if (response.message) {
							errorMsg = response.message;
						}
					} catch (e) {
						// Use default error message
					}
					alert(errorMsg);
				}
			});
		};
	});
})(jQuery);
