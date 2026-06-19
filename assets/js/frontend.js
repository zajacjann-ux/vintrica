(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('.vintrica-vignette-form');

		if (!forms.length) {
			return;
		}

		forms.forEach(function (form) {
			form.addEventListener('submit', function () {
				var submitButton = form.querySelector('.vintrica-submit');

				if (submitButton) {
					submitButton.disabled = true;
					submitButton.setAttribute('aria-busy', 'true');
				}
			});
		});
	});
})();
