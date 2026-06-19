(function () {
	'use strict';

	if (typeof window.vintricaConfig === 'undefined') {
		return;
	}

	var config = window.vintricaConfig.config;
	var strings = window.vintricaConfig.strings;
	var checkoutReady = !!window.vintricaConfig.checkoutReady;
	var woocommerceActive = !!window.vintricaConfig.woocommerceActive;
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

	function getVehicleLabel(code) {
		return getLabelFromList(config.vehicleTypes, code);
	}

	function getValidityLabel(country, validityCode) {
		var options = config.validities[country] || [];
		var i;

		for (i = 0; i < options.length; i += 1) {
			if (options[i].code === validityCode) {
				return options[i].label;
			}
		}

		return validityCode;
	}

	function getValidityPrice(country, validityCode) {
		var options = config.validities[country] || [];
		var i;

		for (i = 0; i < options.length; i += 1) {
			if (options[i].code === validityCode) {
				return options[i].price;
			}
		}

		return 0;
	}

	function VintricaBuilder(form) {
		this.form = form;
		this.vignettes = [];
		this.editingIndex = null;
		this.storageKey = storageKey;

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
		this.formError = form.querySelector('.vintrica-form-error');
		this.formSuccess = form.querySelector('.vintrica-form-success');
		this.continueNotice = form.querySelector('.vintrica-continue-notice');
		this.addButton = form.querySelector('.vintrica-add-vignette');
		this.cancelButton = form.querySelector('.vintrica-cancel-edit');
		this.submitButton = form.querySelector('.vintrica-submit');
		this.totalCount = form.querySelector('.vintrica-total-count');
		this.totalSubtotal = form.querySelector('.vintrica-total-subtotal');
		this.totalServiceFee = form.querySelector('.vintrica-total-service-fee');
		this.totalAmount = form.querySelector('.vintrica-total-amount');

		this.bindEvents();
		this.loadFromStorage();
		this.renderSummary();
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
			self.populateValidities(self.fields.country.value);
			self.fields.vignette_validity.value = '';
		});

		this.addButton.addEventListener('click', function () {
			self.handleAddOrUpdate();
		});

		this.cancelButton.addEventListener('click', function () {
			self.cancelEdit();
		});

		this.form.addEventListener('submit', function (event) {
			var validation = self.validateContinue();

			if (!validation.valid) {
				event.preventDefault();
				self.clearContinueNotice();
				self.showFormError(validation.message);
				return;
			}

			if (!checkoutReady) {
				event.preventDefault();
				self.clearFormError();
				self.persistState();
				self.showContinueNotice(
					woocommerceActive ? strings.woocommerceNotReady : strings.woocommerceMissing
				);
				return;
			}

			self.clearContinueNotice();
			self.clearFormError();
			self.persistState();
			self.clearStorage();

			if (!self.prepareSubmit()) {
				event.preventDefault();
				return;
			}

			self.submitButton.disabled = true;
			self.submitButton.setAttribute('aria-busy', 'true');
		});
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

	VintricaBuilder.prototype.populateValidities = function (countryCode) {
		var select = this.fields.vignette_validity;
		var options = config.validities[countryCode] || [];
		var i;
		var option;

		select.innerHTML = '';

		option = document.createElement('option');
		option.value = '';
		option.textContent = countryCode ? strings.selectValidity : strings.selectCountryFirst;
		select.appendChild(option);

		for (i = 0; i < options.length; i += 1) {
			option = document.createElement('option');
			option.value = options[i].code;
			option.textContent = options[i].label + ' (' + formatPrice(options[i].price) + ')';
			select.appendChild(option);
		}

		select.disabled = !countryCode || options.length === 0;
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
		this.fields.country.value = values.country || '';
		this.populateValidities(values.country || '');
		this.fields.vehicle_type.value = values.vehicle_type || '';
		this.fields.vignette_validity.value = values.vignette_validity || '';
		this.fields.start_date.value = values.start_date || '';
		this.fields.license_plate.value = values.license_plate || '';
		this.fields.registration_country.value = values.registration_country || '';
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
		this.populateValidities('');
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

	VintricaBuilder.prototype.showFormSuccess = function (message) {
		this.formSuccess.textContent = message;
		this.formSuccess.hidden = false;
		this.clearFormError();
	};

	VintricaBuilder.prototype.clearFormSuccess = function () {
		this.formSuccess.textContent = '';
		this.formSuccess.hidden = true;
	};

	VintricaBuilder.prototype.showContinueNotice = function (message) {
		this.continueNotice.textContent = message;
		this.continueNotice.hidden = false;
	};

	VintricaBuilder.prototype.clearContinueNotice = function () {
		this.continueNotice.textContent = '';
		this.continueNotice.hidden = true;
	};

	VintricaBuilder.prototype.validateBuilderFields = function (values) {
		var keys = Object.keys(values);
		var i;

		for (i = 0; i < keys.length; i += 1) {
			if (!values[keys[i]]) {
				return false;
			}
		}

		if (!config.validities[values.country]) {
			return false;
		}

		return getValidityPrice(values.country, values.vignette_validity) > 0;
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

	VintricaBuilder.prototype.handleAddOrUpdate = function () {
		var values = this.getFieldValues();
		var wasEditing = this.editingIndex !== null;

		this.clearFormError();
		this.clearContinueNotice();

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
		this.clearContinueNotice();
	};

	VintricaBuilder.prototype.cancelEdit = function () {
		this.editingIndex = null;
		this.clearBuilderFields();
		this.addButton.textContent = strings.addVignette;
		this.cancelButton.hidden = true;
		this.clearFormError();
	};

	VintricaBuilder.prototype.removeVignette = function (index) {
		if (this.editingIndex === index) {
			this.cancelEdit();
		} else if (this.editingIndex !== null && index < this.editingIndex) {
			this.editingIndex -= 1;
		}

		this.vignettes.splice(index, 1);
		this.showFormSuccess(strings.vignetteRemoved);
		this.renderSummary();
	};

	VintricaBuilder.prototype.calculateTotals = function () {
		var subtotal = 0;
		var i;
		var vignette;

		for (i = 0; i < this.vignettes.length; i += 1) {
			vignette = this.vignettes[i];
			subtotal += getValidityPrice(vignette.country, vignette.vignette_validity);
		}

		return {
			count: this.vignettes.length,
			subtotal: subtotal,
			serviceFee: this.vignettes.length > 0 ? config.serviceFee : 0,
			total: subtotal + (this.vignettes.length > 0 ? config.serviceFee : 0)
		};
	};

	VintricaBuilder.prototype.renderSummary = function () {
		var self = this;
		var totals = this.calculateTotals();
		var i;
		var vignette;
		var item;
		var title;
		var meta;
		var price;
		var actions;
		var editButton;
		var removeButton;
		var priceEl;

		this.summaryList.innerHTML = '';

		for (i = 0; i < this.vignettes.length; i += 1) {
			vignette = this.vignettes[i];
			price = getValidityPrice(vignette.country, vignette.vignette_validity);

			item = document.createElement('li');
			item.className = 'vintrica-summary-item';

			if (this.editingIndex === i) {
				item.classList.add('is-editing');
			}

			title = document.createElement('div');
			title.className = 'vintrica-summary-item__title';
			title.textContent = getCountryLabel(vignette.country) + ' · ' + getValidityLabel(vignette.country, vignette.vignette_validity);

			meta = document.createElement('div');
			meta.className = 'vintrica-summary-item__meta';
			meta.textContent = getVehicleLabel(vignette.vehicle_type) + ' · ' + strings.plateLabel + ': ' + vignette.license_plate + ' · ' + strings.startsLabel + ': ' + vignette.start_date;

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
					self.removeVignette(index);
				};
			}(i)));

			actions.appendChild(editButton);
			actions.appendChild(removeButton);

			priceEl = document.createElement('div');
			priceEl.className = 'vintrica-summary-item__price';
			priceEl.textContent = formatPrice(price);

			item.appendChild(title);
			item.appendChild(meta);
			item.appendChild(priceEl);
			item.appendChild(actions);

			this.summaryList.appendChild(item);
		}

		this.summaryEmpty.hidden = this.vignettes.length > 0;
		this.totalCount.textContent = String(totals.count);
		this.totalSubtotal.textContent = formatPrice(totals.subtotal);
		this.totalServiceFee.textContent = formatPrice(totals.serviceFee);
		this.totalAmount.textContent = formatPrice(totals.total);
		this.submitButton.disabled = this.vignettes.length === 0;

		this.persistState();
	};

	VintricaBuilder.prototype.prepareSubmit = function () {
		this.clearFormError();

		if (this.vignettes.length === 0) {
			this.showFormError(strings.validationOrderEmpty);
			return false;
		}

		this.persistState();
		return true;
	};

	document.addEventListener('DOMContentLoaded', function () {
		var forms = document.querySelectorAll('.vintrica-vignette-form');
		var i;

		for (i = 0; i < forms.length; i += 1) {
			new VintricaBuilder(forms[i]);
		}
	});
})();
