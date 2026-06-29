(function () {
	'use strict';

	if (typeof window.vintricaConfig === 'undefined') {
		return;
	}

	var config = window.vintricaConfig.config;
	var strings = window.vintricaConfig.strings;
	var storageKey = window.vintricaConfig.storageKey || 'vintrica_vignettes_default';

	function formatPrice(amount) {
		return config.currency + ' ' + amount.toFixed(2);
	}

	function getLabelFromList(list, code) {
		var item;
		var i;

		for (i = 0; i < list.length; i += 1) {
			item = list[i];
			if (item.code === code) {
				return item.label;
			}
		}

		return code;
	}

	function getCountryLabel(code) {
		return getLabelFromList(config.countries, code);
	}

	function getRegistrationCountryLabel(code) {
		return getLabelFromList(config.registrationCountries || [], code);
	}

	function getVehicleLabel(code) {
		return getLabelFromList(config.vehicleTypes, code);
	}

	function getCatalogOptions(country, vehicleType) {
		var byCountry = config.validities[country] || {};
		return byCountry[vehicleType] || [];
	}

	function getValidityLabel(country, vehicleType, validityCode) {
		var options = getCatalogOptions(country, vehicleType);
		var i;

		for (i = 0; i < options.length; i += 1) {
			if (options[i].code === validityCode) {
				return options[i].label;
			}
		}

		return validityCode;
	}

	function getValidityPrice(country, vehicleType, validityCode) {
		var options = getCatalogOptions(country, vehicleType);
		var i;

		for (i = 0; i < options.length; i += 1) {
			if (options[i].code === validityCode) {
				return options[i].price;
			}
		}

		return 0;
	}

	function isValidEmail(value) {
		return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
	}

	var uiIcons = {
		highway: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19V5l4 2 4-2 4 2 4-2v14"/><path d="M8 11v8M12 9v10M16 11v8"/></svg>',
		vehicle: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 17h-.5a2.5 2.5 0 0 1 0-5h.9l1.2-3.6A2 2 0 0 1 10.5 7h3a2 2 0 0 1 1.9 1.4l1.2 3.6h.9a2.5 2.5 0 0 1 0 5H17"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>',
		plate: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h4M18 10h.01"/></svg>',
		calendar: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>',
		globe: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
		edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>',
		remove: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6M14 11v6"/></svg>'
	};

	function createReviewDetail(label, value, iconKey) {
		var detail = document.createElement('div');
		detail.className = 'vintrica-review-vignette-card__detail';

		detail.innerHTML =
			'<span class="vintrica-review-vignette-card__detail-icon">' + uiIcons[iconKey] + '</span>' +
			'<span class="vintrica-review-vignette-card__detail-label">' + label + '</span>' +
			'<span class="vintrica-review-vignette-card__detail-value"></span>';

		detail.querySelector('.vintrica-review-vignette-card__detail-value').textContent = value;

		return detail;
	}

	function VintricaBuilder(form) {
		this.form = form;
		this.vignettes = [];
		this.editingIndex = null;
		this.storageKey = storageKey;
		this.currentStep = 1;

		this.fields = {
			country: form.querySelector('[data-vintrica-field="country"]'),
			vehicle_type: form.querySelector('[data-vintrica-field="vehicle_type"]'),
			vignette_validity: form.querySelector('[data-vintrica-field="vignette_validity"]'),
			start_date: form.querySelector('[data-vintrica-field="start_date"]'),
			license_plate: form.querySelector('[data-vintrica-field="license_plate"]'),
			registration_country: form.querySelector('[data-vintrica-field="registration_country"]')
		};

		this.hiddenInput = form.querySelector('#vintrica-vignettes-data');
		this.summaryList = form.querySelector('.vintrica-summary-list');
		this.summaryEmpty = form.querySelector('.vintrica-summary-empty');
		this.formError = form.querySelector('.vintrica-step--builder .vintrica-form-error');
		this.formSuccess = form.querySelector('.vintrica-form-success');
		this.addButton = form.querySelector('.vintrica-add-vignette');
		this.cancelButton = form.querySelector('.vintrica-cancel-edit');
		this.continueButton = form.querySelector('.vintrica-continue-billing');
		this.continueReviewButton = form.querySelector('.vintrica-continue-review');
		this.backBuilderButton = form.querySelector('.vintrica-back-builder');
		this.backBillingButton = form.querySelector('.vintrica-back-billing');
		this.editVignettesButton = form.querySelector('.vintrica-edit-vignettes');
		this.payButton = form.querySelector('#vintrica-pay-submit') || form.querySelector('.vintrica-pay-submit');
		this.billingError = form.querySelector('.vintrica-billing-error');
		this.reviewError = form.querySelector('.vintrica-review-error');
		this.stepBuilder = form.querySelector('.vintrica-step--builder');
		this.stepBilling = form.querySelector('.vintrica-step--billing');
		this.stepReview = form.querySelector('.vintrica-step--review');
		this.stepIndicators = form.querySelectorAll('[data-vintrica-step-indicator]');
		this.stepNavButtons = form.querySelectorAll('[data-vintrica-step-nav]');
		this.reviewVignettes = form.querySelector('.vintrica-review-vignettes');
		this.reviewBilling = form.querySelector('.vintrica-review-billing');
		this.reviewSubtotal = form.querySelector('.vintrica-review-subtotal');
		this.reviewTotal = form.querySelector('.vintrica-review-total');
		this.totalCount = form.querySelector('.vintrica-total-count');
		this.totalSubtotal = form.querySelector('.vintrica-total-subtotal');
		this.totalAmount = form.querySelector('.vintrica-total-amount');

		this.billingFields = {
			first_name: form.querySelector('[name="vintrica_billing_first_name"]'),
			last_name: form.querySelector('[name="vintrica_billing_last_name"]'),
			email: form.querySelector('[name="vintrica_billing_email"]'),
			phone: form.querySelector('[name="vintrica_billing_phone"]'),
			company: form.querySelector('[name="vintrica_billing_company"]'),
			ico: form.querySelector('[name="vintrica_billing_ico"]'),
			dic: form.querySelector('[name="vintrica_billing_dic"]'),
			ic_dph: form.querySelector('[name="vintrica_billing_ic_dph"]'),
			street: form.querySelector('[name="vintrica_billing_street"]'),
			city: form.querySelector('[name="vintrica_billing_city"]'),
			zip: form.querySelector('[name="vintrica_billing_zip"]'),
			country: form.querySelector('[name="vintrica_billing_country"]')
		};

		this.consentFields = form.querySelectorAll('[name^="vintrica_consent_"]');

		this.bindEvents();
		this.initAllChoices();
		this.populateVehicleTypes('');
		this.populateValidities('', '');
		this.loadFromStorage();
		this.renderSummary();
		this.updateStepIndicators();
	}

	VintricaBuilder.prototype.clearStorage = function () {
		if (!window.localStorage) {
			return;
		}

		try {
			window.localStorage.removeItem(this.storageKey);
		} catch (error) {
			// Ignore storage errors.
		}
	};

	VintricaBuilder.prototype.bindEvents = function () {
		var self = this;

		this.fields.country.addEventListener('change', function () {
			self.populateVehicleTypes(self.fields.country.value);
			self.populateValidities(self.fields.country.value, '');
			self.setSelectValue(self.fields.vehicle_type, '');
			self.setSelectValue(self.fields.vignette_validity, '');
		});

		this.fields.vehicle_type.addEventListener('change', function () {
			self.populateValidities(self.fields.country.value, self.fields.vehicle_type.value);
			self.setSelectValue(self.fields.vignette_validity, '');
		});

		this.addButton.addEventListener('click', function () {
			self.handleAddOrUpdate();
		});

		this.cancelButton.addEventListener('click', function () {
			self.cancelEdit();
		});

		this.continueButton.addEventListener('click', function () {
			self.goToStep(2);
		});

		this.continueReviewButton.addEventListener('click', function () {
			self.goToStep(3);
		});

		this.backBuilderButton.addEventListener('click', function () {
			self.goToStep(1);
		});

		this.backBillingButton.addEventListener('click', function () {
			self.goToStep(2);
		});

		this.editVignettesButton.addEventListener('click', function () {
			self.goToStep(1);
		});

		if (this.payButton) {
			this.payButton.addEventListener('click', function (event) {
				event.preventDefault();
				self.handlePayment(event);
			});
		}

		this.form.addEventListener('submit', function (event) {
			event.preventDefault();
		});

		this.stepNavButtons.forEach(function (button) {
			button.addEventListener('click', function () {
				var step = parseInt(button.getAttribute('data-vintrica-step-nav'), 10);

				if (!step || button.disabled) {
					return;
				}

				self.goToStep(step);
			});
		});

		this.form.addEventListener('change', function () {
			self.updateStepIndicators();
		});

		this.form.addEventListener('input', function () {
			self.updateStepIndicators();
		});
	};

	VintricaBuilder.prototype.canNavigateToStep = function (step) {
		if (step === 1) {
			return true;
		}

		if (step === 2) {
			return this.validateContinue().valid;
		}

		if (step === 3) {
			return this.vignettes.length > 0 && this.validateBilling().valid;
		}

		return false;
	};

	VintricaBuilder.prototype.updateStepIndicators = function () {
		var self = this;

		this.stepIndicators.forEach(function (indicator) {
			var step = parseInt(indicator.getAttribute('data-vintrica-step-indicator'), 10);
			var button = indicator.querySelector('[data-vintrica-step-nav]');
			var clickable = step <= 3 && self.canNavigateToStep(step);

			indicator.classList.toggle('is-clickable', clickable);
			indicator.classList.toggle('is-disabled', !clickable);

			if (button) {
				button.disabled = !clickable;
			}
		});
	};

	VintricaBuilder.prototype.debugLog = function () {
		if (!window.vintricaConfig.debug || typeof console === 'undefined' || !console.log) {
			return;
		}

		console.log.apply(console, ['[VINTRICA]'].concat(Array.prototype.slice.call(arguments)));
	};

	VintricaBuilder.prototype.handlePayment = function (event) {
		var self = this;
		var validation;
		var formData;
		var originalText;
		var checkout;

		if (event && typeof event.preventDefault === 'function') {
			event.preventDefault();
		}

		if (this.currentStep !== 3) {
			return;
		}

		if (!this.payButton) {
			this.debugLog('payment button not found');
			return;
		}

		validation = this.validateCheckout();

		if (!validation.valid) {
			this.showReviewError(validation.message);
			return;
		}

		checkout = window.VintricaCheckout;

		if (!checkout || !checkout.ajax_url || !checkout.nonce || !checkout.action) {
			this.debugLog('missing checkout config', checkout);
			this.showReviewError(strings.paymentFailed);
			return;
		}

		this.clearReviewError();
		this.persistState();

		originalText = this.payButton.textContent;
		this.payButton.disabled = true;
		this.payButton.setAttribute('aria-busy', 'true');
		this.payButton.textContent = strings.paymentProcessing;

		formData = new FormData(this.form);
		formData.set('action', checkout.action);
		formData.set('nonce', checkout.nonce);

		this.debugLog('creating checkout session');

		fetch(checkout.ajax_url, {
			method: 'POST',
			credentials: 'same-origin',
			body: formData
		})
			.then(function (response) {
				return response.json().then(function (data) {
					return {
						ok: response.ok,
						data: data
					};
				}).catch(function () {
					return {
						ok: false,
						data: null
					};
				});
			})
			.then(function (result) {
				var payload = result.data;
				var message;

				self.debugLog('checkout response', payload);

				if (!payload || !payload.success) {
					message = payload && payload.data && payload.data.message
						? payload.data.message
						: strings.paymentFailed;
					throw new Error(message);
				}

				if (!payload.data || !payload.data.checkout_url) {
					throw new Error(strings.paymentFailed);
				}

				self.clearStorage();
				window.location.href = payload.data.checkout_url;
			})
			.catch(function (error) {
				self.payButton.disabled = false;
				self.payButton.removeAttribute('aria-busy');
				self.payButton.textContent = originalText;
				self.showReviewError(error.message || strings.paymentFailed);
				self.debugLog('checkout failed', error.message || error);
			});
	};

	VintricaBuilder.prototype.goToStep = function (step) {
		var validation;
		var isBackward = step < this.currentStep;

		if (step === 4) {
			return;
		}

		if (step === 1) {
			this.clearFormError();
			this.clearBillingError();
			this.clearReviewError();
		}

		if (step === 2) {
			if (!this.canNavigateToStep(2)) {
				if (!isBackward) {
					this.showFormError(this.validateContinue().message || strings.validationOrderEmpty);
				}
				return;
			}

			if (!isBackward) {
				validation = this.validateContinue();

				if (!validation.valid) {
					this.showFormError(validation.message);
					return;
				}

				this.clearFormError();
				this.persistState();
			}
		}

		if (step === 3) {
			if (!this.canNavigateToStep(3)) {
				if (!isBackward) {
					this.showBillingError(this.validateBilling().message || strings.validationBillingRequired);
				}
				return;
			}

			if (!isBackward) {
				validation = this.validateCheckout();

				if (!validation.valid) {
					this.showBillingError(validation.message);
					return;
				}
			}

			this.clearBillingError();
			this.clearReviewError();
			this.renderReview();
		}

		this.currentStep = step;
		this.stepBuilder.hidden = step !== 1;
		this.stepBuilder.classList.toggle('is-active', step === 1);
		this.stepBilling.hidden = step !== 2;
		this.stepBilling.classList.toggle('is-active', step === 2);
		this.stepReview.hidden = step !== 3 && step !== 4;
		this.stepReview.classList.toggle('is-active', step === 3 || step === 4);

		this.stepIndicators.forEach(function (indicator) {
			var indicatorStep = parseInt(indicator.getAttribute('data-vintrica-step-indicator'), 10);
			indicator.classList.toggle('is-active', indicatorStep === step);
			indicator.classList.toggle('is-complete', indicatorStep < step);
		});

		this.updateStepIndicators();

		if (step === 1) {
			this.stepBuilder.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		if (step === 2) {
			this.clearBillingError();
			this.stepBilling.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}

		if (step === 3) {
			this.clearReviewError();
			this.stepReview.scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	};

	VintricaBuilder.prototype.getChoicesConfig = function () {
		return {
			searchEnabled: true,
			itemSelectText: '',
			shouldSort: false,
			allowHTML: false,
			searchPlaceholderValue: strings.searchPlaceholder || 'Hľadať…',
			noResultsText: strings.noResults || 'Nenašli sa žiadne výsledky',
			noChoicesText: strings.noChoices || 'Žiadne možnosti',
			classNames: {
				containerOuter: 'choices vintrica-choices',
				containerInner: 'choices__inner vintrica-choices__inner',
				input: 'choices__input vintrica-choices__input',
				listDropdown: 'choices__list--dropdown vintrica-choices__dropdown',
				itemSelectable: 'choices__item--selectable vintrica-choices__option'
			}
		};
	};

	VintricaBuilder.prototype.initChoicesOnSelect = function (select, extraConfig) {
		var choicesConfig;

		if (!select || typeof Choices === 'undefined') {
			return null;
		}

		if (select._vintricaChoices) {
			select._vintricaChoices.destroy();
			delete select._vintricaChoices;
		}

		choicesConfig = Object.assign({}, this.getChoicesConfig(), extraConfig || {});
		select._vintricaChoices = new Choices(select, choicesConfig);

		return select._vintricaChoices;
	};

	VintricaBuilder.prototype.initAllChoices = function () {
		this.initChoicesOnSelect(this.fields.country);
		this.initChoicesOnSelect(this.fields.vehicle_type);
		this.initChoicesOnSelect(this.fields.vignette_validity);
		this.initChoicesOnSelect(this.fields.registration_country);
		this.initChoicesOnSelect(this.billingFields.country);
	};

	VintricaBuilder.prototype.setSelectValue = function (select, value) {
		var normalized = value || '';

		if (!select) {
			return;
		}

		if (select._vintricaChoices) {
			select._vintricaChoices.removeActiveItems();
			select._vintricaChoices.setChoiceByValue(String(normalized));
			return;
		}

		select.value = normalized;
	};

	VintricaBuilder.prototype.setSelectDisabled = function (select, disabled) {
		if (!select) {
			return;
		}

		select.disabled = disabled;

		if (select._vintricaChoices) {
			if (disabled) {
				select._vintricaChoices.disable();
			} else {
				select._vintricaChoices.enable();
			}
		}
	};

	VintricaBuilder.prototype.loadFromStorage = function () {
		var stored;
		var parsed;

		if (!window.localStorage) {
			return;
		}

		try {
			stored = window.localStorage.getItem(this.storageKey);

			if (!stored) {
				return;
			}

			parsed = JSON.parse(stored);

			if (Array.isArray(parsed)) {
				this.vignettes = parsed;
			}
		} catch (error) {
			this.vignettes = [];
		}
	};

	VintricaBuilder.prototype.persistState = function () {
		var payload = JSON.stringify(this.vignettes);

		this.hiddenInput.value = payload;

		if (!window.localStorage) {
			return;
		}

		try {
			window.localStorage.setItem(this.storageKey, payload);
		} catch (error) {
			// Ignore storage quota or privacy mode errors.
		}
	};

	VintricaBuilder.prototype.populateVehicleTypes = function (countryCode) {
		var select = this.fields.vehicle_type;
		var codes = config.vehicleTypesByCountry[countryCode] || [];
		var choices = [
			{
				value: '',
				label: countryCode ? strings.selectVehicleType : strings.selectCountryFirst
			}
		];
		var disabled = !countryCode || codes.length === 0;
		var i;
		var option;

		for (i = 0; i < codes.length; i += 1) {
			choices.push({
				value: codes[i],
				label: getVehicleLabel(codes[i])
			});
		}

		if (select._vintricaChoices) {
			select._vintricaChoices.clearStore();
			select._vintricaChoices.setChoices(choices, 'value', 'label', true);
			this.setSelectDisabled(select, disabled);
			return;
		}

		select.innerHTML = '';

		option = document.createElement('option');
		option.value = '';
		option.textContent = countryCode ? strings.selectVehicleType : strings.selectCountryFirst;
		select.appendChild(option);

		for (i = 0; i < codes.length; i += 1) {
			option = document.createElement('option');
			option.value = codes[i];
			option.textContent = getVehicleLabel(codes[i]);
			select.appendChild(option);
		}

		this.setSelectDisabled(select, disabled);
	};

	VintricaBuilder.prototype.populateValidities = function (countryCode, vehicleType) {
		var select = this.fields.vignette_validity;
		var options = getCatalogOptions(countryCode, vehicleType);
		var choices = [
			{
				value: '',
				label: !countryCode ? strings.selectCountryFirst : (vehicleType ? strings.selectValidity : strings.selectVehicleFirst)
			}
		];
		var disabled = !countryCode || !vehicleType || options.length === 0;
		var i;
		var option;

		for (i = 0; i < options.length; i += 1) {
			choices.push({
				value: options[i].code,
				label: options[i].label + ' (' + formatPrice(options[i].price) + ')'
			});
		}

		if (select._vintricaChoices) {
			select._vintricaChoices.clearStore();
			select._vintricaChoices.setChoices(choices, 'value', 'label', true);
			this.setSelectDisabled(select, disabled);
			return;
		}

		select.innerHTML = '';

		option = document.createElement('option');
		option.value = '';
		option.textContent = !countryCode ? strings.selectCountryFirst : (vehicleType ? strings.selectValidity : strings.selectVehicleFirst);
		select.appendChild(option);

		for (i = 0; i < options.length; i += 1) {
			option = document.createElement('option');
			option.value = options[i].code;
			option.textContent = options[i].label + ' (' + formatPrice(options[i].price) + ')';
			select.appendChild(option);
		}

		this.setSelectDisabled(select, disabled);
	};

	VintricaBuilder.prototype.getFieldValues = function () {
		return {
			country: this.fields.country.value,
			vehicle_type: this.fields.vehicle_type.value,
			vignette_validity: this.fields.vignette_validity.value,
			start_date: this.fields.start_date.value,
			license_plate: this.fields.license_plate.value.trim().toUpperCase(),
			registration_country: this.fields.registration_country.value
		};
	};

	VintricaBuilder.prototype.setFieldValues = function (values) {
		this.setSelectValue(this.fields.country, values.country || '');
		this.populateVehicleTypes(values.country || '');
		this.setSelectValue(this.fields.vehicle_type, values.vehicle_type || '');
		this.populateValidities(values.country || '', values.vehicle_type || '');
		this.setSelectValue(this.fields.vignette_validity, values.vignette_validity || '');
		this.fields.start_date.value = values.start_date || '';
		this.fields.license_plate.value = values.license_plate || '';
		this.setSelectValue(this.fields.registration_country, values.registration_country || '');
	};

	VintricaBuilder.prototype.clearBuilderFields = function () {
		this.setFieldValues({
			country: '',
			vehicle_type: '',
			vignette_validity: '',
			start_date: '',
			license_plate: '',
			registration_country: ''
		});
		this.populateVehicleTypes('');
		this.populateValidities('', '');
	};

	VintricaBuilder.prototype.showFormError = function (message) {
		this.formError.textContent = message;
		this.formError.hidden = false;
		this.clearFormSuccess();
	};

	VintricaBuilder.prototype.clearFormError = function () {
		this.formError.textContent = '';
		this.formError.hidden = true;
	};

	VintricaBuilder.prototype.showBillingError = function (message) {
		this.billingError.textContent = message;
		this.billingError.hidden = false;
	};

	VintricaBuilder.prototype.clearBillingError = function () {
		this.billingError.textContent = '';
		this.billingError.hidden = true;
	};

	VintricaBuilder.prototype.showReviewError = function (message) {
		this.reviewError.textContent = message;
		this.reviewError.hidden = false;
	};

	VintricaBuilder.prototype.clearReviewError = function () {
		this.reviewError.textContent = '';
		this.reviewError.hidden = true;
	};

	VintricaBuilder.prototype.showFormSuccess = function (message) {
		this.formSuccess.textContent = message;
		this.formSuccess.hidden = false;
		this.clearFormError();
	};

	VintricaBuilder.prototype.clearFormSuccess = function () {
		this.formSuccess.textContent = '';
		this.formSuccess.hidden = true;
	};

	VintricaBuilder.prototype.validateBuilderFields = function (values) {
		var keys = Object.keys(values);
		var i;

		for (i = 0; i < keys.length; i += 1) {
			if (!values[keys[i]]) {
				return false;
			}
		}

		if (!getCatalogOptions(values.country, values.vehicle_type).length) {
			return false;
		}

		return getValidityPrice(values.country, values.vehicle_type, values.vignette_validity) > 0;
	};

	VintricaBuilder.prototype.validateContinue = function () {
		var i;
		var vignette;

		if (this.vignettes.length === 0) {
			return {
				valid: false,
				message: strings.validationOrderEmpty
			};
		}

		for (i = 0; i < this.vignettes.length; i += 1) {
			vignette = this.vignettes[i];

			if (!vignette.license_plate || !String(vignette.license_plate).trim()) {
				return {
					valid: false,
					message: strings.validationInvalidData
				};
			}

			if (!vignette.start_date || !String(vignette.start_date).trim()) {
				return {
					valid: false,
					message: strings.validationInvalidData
				};
			}
		}

		return {
			valid: true,
			message: ''
		};
	};

	VintricaBuilder.prototype.validateBilling = function () {
		var fieldKey;
		var field;
		var i;

		for (fieldKey in this.billingFields) {
			if (!Object.prototype.hasOwnProperty.call(this.billingFields, fieldKey)) {
				continue;
			}

			field = this.billingFields[fieldKey];

			if (fieldKey === 'company' || fieldKey === 'ico' || fieldKey === 'dic' || fieldKey === 'ic_dph') {
				continue;
			}

			if (!field || !String(field.value).trim()) {
				return {
					valid: false,
					message: strings.validationBillingRequired
				};
			}
		}

		if (!isValidEmail(this.billingFields.email.value.trim())) {
			return {
				valid: false,
				message: strings.validationEmailInvalid
			};
		}

		for (i = 0; i < this.consentFields.length; i += 1) {
			if (!this.consentFields[i].checked) {
				return {
					valid: false,
					message: strings.validationConsentRequired
				};
			}
		}

		return {
			valid: true,
			message: ''
		};
	};

	VintricaBuilder.prototype.validateCheckout = function () {
		var continueValidation = this.validateContinue();
		var billingValidation;

		if (!continueValidation.valid) {
			return continueValidation;
		}

		billingValidation = this.validateBilling();

		if (!billingValidation.valid) {
			return billingValidation;
		}

		return {
			valid: true,
			message: ''
		};
	};

	VintricaBuilder.prototype.handleAddOrUpdate = function () {
		var values = this.getFieldValues();
		var wasEditing = this.editingIndex !== null;

		this.clearFormError();

		if (!this.validateBuilderFields(values)) {
			this.showFormError(strings.validationRequired);
			return;
		}

		if (wasEditing) {
			this.vignettes[this.editingIndex] = values;
			this.cancelEdit();
			this.showFormSuccess(strings.vignetteUpdated);
		} else {
			this.vignettes.push(values);
			this.clearBuilderFields();
			this.showFormSuccess(strings.vignetteAdded);
		}

		this.renderSummary();
	};

	VintricaBuilder.prototype.startEdit = function (index) {
		this.editingIndex = index;
		this.setFieldValues(this.vignettes[index]);
		this.addButton.textContent = strings.updateVignette;
		this.cancelButton.hidden = false;
		this.clearFormError();
	};

	VintricaBuilder.prototype.cancelEdit = function () {
		this.editingIndex = null;
		this.clearBuilderFields();
		this.addButton.textContent = strings.addVignette;
		this.cancelButton.hidden = true;
		this.clearFormError();
	};

	VintricaBuilder.prototype.removeVignette = function (index, fromReview) {
		if (this.editingIndex === index) {
			this.cancelEdit();
		} else if (this.editingIndex !== null && index < this.editingIndex) {
			this.editingIndex -= 1;
		}

		this.vignettes.splice(index, 1);
		this.renderSummary();

		if (fromReview) {
			if (this.vignettes.length === 0) {
				this.goToStep(1);
				this.showFormError(strings.validationOrderEmpty);
				return;
			}

			this.renderReview();
		} else {
			this.showFormSuccess(strings.vignetteRemoved);
		}
	};

	VintricaBuilder.prototype.calculateTotals = function () {
		var subtotal = 0;
		var i;
		var vignette;

		for (i = 0; i < this.vignettes.length; i += 1) {
			vignette = this.vignettes[i];
			subtotal += getValidityPrice(vignette.country, vignette.vehicle_type, vignette.vignette_validity);
		}

		return {
			count: this.vignettes.length,
			subtotal: subtotal,
			total: subtotal
		};
	};

	VintricaBuilder.prototype.renderSummary = function () {
		var self = this;
		var totals = this.calculateTotals();
		var i;
		var vignette;
		var item;
		var header;
		var badge;
		var body;
		var title;
		var meta;
		var price;
		var actions;
		var editButton;
		var removeButton;
		var priceEl;
		var footer;

		this.summaryList.innerHTML = '';

		for (i = 0; i < this.vignettes.length; i += 1) {
			vignette = this.vignettes[i];
			price = getValidityPrice(vignette.country, vignette.vehicle_type, vignette.vignette_validity);

			item = document.createElement('li');
			item.className = 'vintrica-summary-item';

			if (this.editingIndex === i) {
				item.classList.add('is-editing');
			}

			header = document.createElement('div');
			header.className = 'vintrica-summary-item__header';

			badge = document.createElement('span');
			badge.className = 'vintrica-summary-item__badge';
			badge.textContent = String(vignette.country || '').toUpperCase();

			title = document.createElement('div');
			title.className = 'vintrica-summary-item__title';
			title.textContent = getCountryLabel(vignette.country) + ' · ' + getValidityLabel(vignette.country, vignette.vehicle_type, vignette.vignette_validity);

			priceEl = document.createElement('div');
			priceEl.className = 'vintrica-summary-item__price';
			priceEl.textContent = formatPrice(price);

			header.appendChild(badge);
			header.appendChild(title);
			header.appendChild(priceEl);

			body = document.createElement('div');
			body.className = 'vintrica-summary-item__body';

			meta = document.createElement('div');
			meta.className = 'vintrica-summary-item__meta';
			meta.textContent = getVehicleLabel(vignette.vehicle_type) + ' · ' + strings.plateLabel + ': ' + vignette.license_plate + ' · ' + strings.startsLabel + ': ' + vignette.start_date;

			body.appendChild(meta);

			footer = document.createElement('div');
			footer.className = 'vintrica-summary-item__footer';

			actions = document.createElement('div');
			actions.className = 'vintrica-summary-item__actions';

			editButton = document.createElement('button');
			editButton.type = 'button';
			editButton.className = 'vintrica-button vintrica-button--link';
			editButton.textContent = strings.edit;
			editButton.addEventListener('click', (function (index) {
				return function () {
					self.startEdit(index);
				};
			}(i)));

			removeButton = document.createElement('button');
			removeButton.type = 'button';
			removeButton.className = 'vintrica-button vintrica-button--link vintrica-button--danger';
			removeButton.textContent = strings.remove;
			removeButton.addEventListener('click', (function (index) {
				return function () {
					self.removeVignette(index, false);
				};
			}(i)));

			actions.appendChild(editButton);
			actions.appendChild(removeButton);
			footer.appendChild(actions);

			item.appendChild(header);
			item.appendChild(body);
			item.appendChild(footer);

			this.summaryList.appendChild(item);
		}

		this.summaryEmpty.hidden = this.vignettes.length > 0;
		this.totalCount.textContent = String(totals.count);
		this.totalSubtotal.textContent = formatPrice(totals.subtotal);
		this.totalAmount.textContent = formatPrice(totals.total);
		this.continueButton.disabled = this.vignettes.length === 0;

		this.persistState();
	};

	VintricaBuilder.prototype.renderReview = function () {
		var self = this;
		var totals = this.calculateTotals();
		var i;
		var vignette;
		var card;
		var header;
		var headerIcon;
		var headerMain;
		var badge;
		var title;
		var priceEl;
		var body;
		var footer;
		var editButton;
		var removeButton;
		var addressParts;
		var billingCountry;

		this.reviewVignettes.innerHTML = '';

		for (i = 0; i < this.vignettes.length; i += 1) {
			vignette = this.vignettes[i];

			card = document.createElement('article');
			card.className = 'vintrica-review-vignette-card';

			header = document.createElement('header');
			header.className = 'vintrica-review-vignette-card__header';

			headerIcon = document.createElement('div');
			headerIcon.className = 'vintrica-review-vignette-card__header-icon';
			headerIcon.innerHTML = uiIcons.highway;

			headerMain = document.createElement('div');
			headerMain.className = 'vintrica-review-vignette-card__header-main';

			badge = document.createElement('span');
			badge.className = 'vintrica-review-vignette-card__badge';
			badge.textContent = String(vignette.country || '').toUpperCase();

			title = document.createElement('h4');
			title.className = 'vintrica-review-vignette-card__title';
			title.textContent = getCountryLabel(vignette.country) + ' · ' + getValidityLabel(vignette.country, vignette.vehicle_type, vignette.vignette_validity);

			headerMain.appendChild(badge);
			headerMain.appendChild(title);

			priceEl = document.createElement('div');
			priceEl.className = 'vintrica-review-vignette-card__price';
			priceEl.textContent = formatPrice(getValidityPrice(vignette.country, vignette.vehicle_type, vignette.vignette_validity));

			header.appendChild(headerIcon);
			header.appendChild(headerMain);
			header.appendChild(priceEl);

			body = document.createElement('div');
			body.className = 'vintrica-review-vignette-card__body';
			body.appendChild(createReviewDetail(strings.labelVehicleType, getVehicleLabel(vignette.vehicle_type), 'vehicle'));
			body.appendChild(createReviewDetail(strings.plateLabel, vignette.license_plate, 'plate'));
			body.appendChild(createReviewDetail(strings.labelStartDate, vignette.start_date, 'calendar'));
			body.appendChild(createReviewDetail(strings.labelRegistrationCountry, getRegistrationCountryLabel(vignette.registration_country), 'globe'));

			footer = document.createElement('footer');
			footer.className = 'vintrica-review-vignette-card__footer';

			editButton = document.createElement('button');
			editButton.type = 'button';
			editButton.className = 'vintrica-review-vignette-card__action vintrica-review-vignette-card__action--edit';
			editButton.innerHTML = uiIcons.edit + '<span>' + strings.edit + '</span>';
			editButton.addEventListener('click', (function (index) {
				return function () {
					self.startEdit(index);
					self.goToStep(1);
				};
			}(i)));

			removeButton = document.createElement('button');
			removeButton.type = 'button';
			removeButton.className = 'vintrica-review-vignette-card__action vintrica-review-vignette-card__action--remove';
			removeButton.innerHTML = uiIcons.remove + '<span>' + strings.remove + '</span>';
			removeButton.addEventListener('click', (function (index) {
				return function () {
					self.removeVignette(index, true);
				};
			}(i)));

			footer.appendChild(editButton);
			footer.appendChild(removeButton);

			card.appendChild(header);
			card.appendChild(body);
			card.appendChild(footer);
			this.reviewVignettes.appendChild(card);
		}

		this.reviewSubtotal.textContent = formatPrice(totals.subtotal);
		this.reviewTotal.textContent = formatPrice(totals.total);

		billingCountry = getRegistrationCountryLabel(this.billingFields.country.value);
		addressParts = [
			this.billingFields.street.value.trim(),
			(this.billingFields.zip.value.trim() + ' ' + this.billingFields.city.value.trim()).trim(),
			billingCountry
		].filter(function (part) {
			return part;
		});

		this.reviewBilling.innerHTML =
			'<dl class="vintrica-review-card__grid">' +
			'<div><dt>' + strings.labelFirstName + '</dt><dd>' + this.escapeHtml(this.billingFields.first_name.value.trim()) + '</dd></div>' +
			'<div><dt>' + strings.labelLastName + '</dt><dd>' + this.escapeHtml(this.billingFields.last_name.value.trim()) + '</dd></div>' +
			'<div><dt>' + strings.labelEmail + '</dt><dd>' + this.escapeHtml(this.billingFields.email.value.trim()) + '</dd></div>' +
			'<div><dt>' + strings.labelPhone + '</dt><dd>' + this.escapeHtml(this.billingFields.phone.value.trim()) + '</dd></div>' +
			'<div><dt>' + strings.labelCompany + '</dt><dd>' + this.escapeHtml(this.billingFields.company.value.trim() || '—') + '</dd></div>' +
			'<div><dt>' + strings.labelIco + '</dt><dd>' + this.escapeHtml(this.billingFields.ico.value.trim() || '—') + '</dd></div>' +
			'<div><dt>' + strings.labelDic + '</dt><dd>' + this.escapeHtml(this.billingFields.dic.value.trim()) + '</dd></div>' +
			'<div><dt>' + strings.labelIcDph + '</dt><dd>' + this.escapeHtml(this.billingFields.ic_dph.value.trim() || '—') + '</dd></div>' +
			'<div><dt>' + strings.labelAddress + '</dt><dd>' + this.escapeHtml(addressParts.join(', ')) + '</dd></div>' +
			'</dl>';
	};

	VintricaBuilder.prototype.escapeHtml = function (value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	};

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('.vintrica-vignette-form');
		var i;

		for (i = 0; i < forms.length; i += 1) {
			new VintricaBuilder(forms[i]);
		}
	});
})();
