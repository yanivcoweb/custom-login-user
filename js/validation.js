console.log('validation.js 2.4.01');

document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('.form-validation'); // Select all forms with the shared class
	//console.log('forms',forms);
	
	function validatePassword(password) {
		var rules    = (typeof ajaxData !== 'undefined' && ajaxData.passwordRules)    ? ajaxData.passwordRules    : {};
		var messages = (typeof ajaxData !== 'undefined' && ajaxData.passwordMessages) ? ajaxData.passwordMessages : {};
		var minLength = parseInt(rules.min_length, 10) || 6;

		var errors = [];

		if (password.length < minLength) {
			errors.push(messages.min_length || 'Password must be at least ' + minLength + ' characters long.');
		}
		if (rules.require_uppercase == 1 && !/[A-Z]/.test(password)) {
			errors.push(messages.uppercase || 'Password must contain at least one uppercase letter (A-Z).');
		}
		if (rules.require_lowercase == 1 && !/[a-z]/.test(password)) {
			errors.push(messages.lowercase || 'Password must contain at least one lowercase letter (a-z).');
		}
		if (rules.require_number == 1 && !/[0-9]/.test(password)) {
			errors.push(messages.number || 'Password must contain at least one number (0-9).');
		}
		if (rules.require_special == 1 && !/[!@#$%^&*()]/.test(password)) {
			errors.push(messages.special_char || 'Password must contain at least one special character (!@#$%^&*()).');
		}
		return errors;
	}

	
    // Email validation helper function
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(String(email).toLowerCase());
    }

    // Form validation function
    function validateForm(form) {
        //console.log(`Validating form: ${form.id}`);
        let isValid = true;

        // Show/hide error helper functions
        const showError = (input) => {
            const errorMessage = form.querySelector(`.error-message[data-inputid="${input.id}"]`);
            if (errorMessage) {
                errorMessage.style.display = 'block';
            }
        };

        const hideError = (input) => {
            const errorMessage = form.querySelector(`.error-message[data-inputid="${input.id}"]`);
            if (errorMessage) {
                errorMessage.style.display = 'none';
            }
        };

        const showEmailError = (inputId, errorType) => {
            const errorMessage = form.querySelector(`.error-message[data-inputid="${inputId}"]`);
            if (errorMessage) {
                // Reset both spans first
                errorMessage.querySelector('.email-empty').style.display = 'none';
                errorMessage.querySelector('.email-incurrected').style.display = 'none';

                // Show the specific error type
                if (errorType === 'empty') {
                    errorMessage.querySelector('.email-empty').style.display = 'block';
                } else if (errorType === 'incorrect') {
                    errorMessage.querySelector('.email-incurrected').style.display = 'block';
                }

                errorMessage.style.display = 'block'; // Ensure the error message container is visible
            }
        };

        const hideEmailError = (inputId) => {
            const errorMessage = form.querySelector(`.error-message[data-inputid="${inputId}"]`);
            if (errorMessage) {
                // Reset both spans and hide the container
                errorMessage.querySelector('.email-empty').style.display = 'none';
                errorMessage.querySelector('.email-incurrected').style.display = 'none';
                errorMessage.style.display = 'none';
            }
        };

        // Validate text inputs
        const textInputs = form.querySelectorAll('input[type="text"][custom-required]');
        textInputs.forEach((input) => {
            input.addEventListener('focus', () => hideError(input)); // Hide error on focus
            if (!input.value.trim()) {
                isValid = false;
                showError(input); // Show error if empty
            }
        });

        // Validate email input
        const emailInput = form.querySelector('input[type="email"][custom-required]');
        if (emailInput) {
            const emailValue = emailInput.value.trim();

            emailInput.addEventListener('focus', () => hideEmailError(emailInput.id)); // Hide error on focus

            if (!emailValue) {
                isValid = false;
                showEmailError(emailInput.id, 'empty'); // Show empty field error
            } else if (!validateEmail(emailValue)) {
                isValid = false;
                showEmailError(emailInput.id, 'incorrect'); // Show incorrect format error
            } else {
                hideEmailError(emailInput.id); // Hide error if valid
            }
        }
		
        // Validate password inputs
        // const passwordInputs = form.querySelectorAll('input[type="password"][custom-required]');
        // passwordInputs.forEach((input) => {
            // input.addEventListener('focus', () => hideError(input)); // Hide error on focus
            // if (!input.value.trim()) {
                // isValid = false;
                // showError(input); // Show error if empty
            // }
        // });
		// Inside validateForm function (modify password validation)
		const passwordInput = form.querySelector('input[type="password"][custom-required]');
		if (passwordInput) {
			const passwordValue = passwordInput.value.trim();
			const errors = validatePassword(passwordValue);

			const errorMessageContainer = form.querySelector(`.error-message[data-inputid="${passwordInput.id}"]`);
			if (errors.length > 0) {
				isValid = false;
				if (errorMessageContainer) {
					errorMessageContainer.innerHTML = errors.map(error => `<p>${error}</p>`).join("");
					errorMessageContainer.style.display = "block";
				}
			} else {
				errorMessageContainer.style.display = "none";
			}
		}

        // Validate other required fields (e.g., selects, textareas)
        const requiredFields = form.querySelectorAll('select[custom-required], textarea[custom-required]');
        requiredFields.forEach((field) => {
            field.addEventListener('focus', () => hideError(field)); // Hide error on focus
            if (!field.value.trim()) {
                isValid = false;
                showError(field); // Show error if empty
            }
        });

		// Validate all required checkboxes
        const checkboxes = form.querySelectorAll('input[type="checkbox"][custom-required]');
        checkboxes.forEach((checkbox) => {
            // Add a 'change' event listener to hide error on interaction
            checkbox.addEventListener('change', () => {
                if (checkbox.checked) {
                    hideError(checkbox);
                }
            });

            const errorMessage = form.querySelector(`.error-message[data-inputid="${checkbox.id}"]`);
            if (!checkbox.checked) {
                isValid = false;
                if (errorMessage) {
                    errorMessage.style.display = 'block'; // Show error if not checked
                }
            } else {
                if (errorMessage) {
                    errorMessage.style.display = 'none'; // Hide error if checked
                }
            }
        });

        return isValid;
    }

    // Form submission handler
    forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault(); // Prevent default form submission
            //console.log('Submitting form:', form);

            const isFormValid = validateForm(form);
            if (isFormValid) {
                console.log(`Form ${form.id} is valid. Submitting...`);

				const submitBtn = form.querySelector('[type="submit"]');
				if (submitBtn) submitBtn.disabled = true;

				try {
					switch (form.id) {
						case 'loginform':
							await login_user(form, event);
							break;
						case 'passwordlostform':
							await lost_password(form, event);
							break;
						case 'signupform':
							await signup(form, event);
							break;
						case 'setpassform':
							await set_password(form, event);
							break;
						default:
							console.error(`Unknown form ID: ${form.id}`);
					}
				} finally {
					if (submitBtn) submitBtn.disabled = false;
				}

            } else {
                console.log(`Form ${form.id} is invalid. Please correct errors.`);
            }
        });
    });

});


async function signup(form, event) {
	console.log('function signup');
    event.preventDefault();

    const formData = new FormData(form);
	// console.log('formData',formData);
	
    const responseElement = document.getElementById('signupform-register-response');
    // responseElement.textContent = 'Processing...';
	responseElement.textContent = 'Processing...';
	responseElement.style.color = 'black';
	
	formData.append('nonce', ajaxData.registerNonce);

    try {

		const response = await fetch(ajaxData.ajaxurl, {
            method: 'POST',
            body: formData,
        });
		// console.log('response:', response);


        const result = await response.json();
		// console.log('Parsed JSON:', result);

		if (result.success) {
            responseElement.innerHTML = result.data.message;
            responseElement.style.color = 'green';
			form.reset(); // Clear the form on success
            // Redirect to the provided URL
			console.log('result.data.redirect:', result.data.redirect);
            if (result.data.redirect) {
                window.location.href = result.data.redirect;
            }
        } else {
            responseElement.innerHTML = result.data.message;
            responseElement.style.color = 'red';
        }
    } catch (error) {
        // responseElement.textContent = 'An error occurred. Please try again.';
		responseElement.textContent = 'An error occurred. Please try again.';
        responseElement.style.color = 'red';
    }
}


async function set_password(form, event) {
	console.log('function set_password');
    event.preventDefault();

    const formData = new FormData(form);
    formData.append('action', 'ajax_password_set'); // Add action for PHP handler

    const responseElement = document.getElementById('password-set-response');
    // responseElement.textContent = 'Processing...';
	responseElement.textContent = 'Processing...';
	responseElement.style.color = 'black';

	formData.append('nonce', ajaxData.setPasswordNonce);

	try {
		const response = await fetch(ajaxData.ajaxurl, {
			method: 'POST',
			body: formData,
		});

		const text = await response.text(); // Get the raw response as text
		//console.log('Raw response:', text);

		const result = JSON.parse(text); // Parse it as JSON
		//console.log('Parsed JSON:', result);

		if (result.success) {
			responseElement.innerHTML = result.data.message;
			responseElement.style.color = 'green';
			form.reset(); // Clear the form on success
			console.log('result.data.redirect:', result.data.redirect);
            if (result.data.redirect) {
                window.location.href = result.data.redirect;
            }
		} else {
			responseElement.innerHTML = result.data.message;
			responseElement.style.color = 'red';
		}
	} catch (error) {
		console.error('Fetch error:', error);
		// responseElement.textContent = 'An error occurred. Please try again.';
		responseElement.textContent = 'An error occurred. Please try again.';
		responseElement.style.color = 'red';
	}
}


async function lost_password(form, event) {
	console.log('function lost_password');
    event.preventDefault();

    const formData = new FormData(form);
    formData.append('action', 'ajax_password_lost'); // Add action for PHP handler

    const responseElement = document.getElementById('password-lost-response');
    // responseElement.textContent = 'Processing...';
	responseElement.textContent = 'Processing...';
	responseElement.style.color = 'black';

	formData.append('nonce', ajaxData.lostPasswordNonce);

	try {
		const response = await fetch(ajaxData.ajaxurl, {
			method: 'POST',
			body: formData,
		});

		const text = await response.text(); // Get the raw response as text
		//console.log('Raw response:', text);

		const result = JSON.parse(text); // Parse it as JSON
		//console.log('Parsed JSON:', result);

		if (result.success) {
			responseElement.innerHTML = result.data.message;
			responseElement.style.color = 'green';
			form.reset(); // Clear the form on success
		} else {
			responseElement.innerHTML = result.data.message;
			responseElement.style.color = 'red';
		}
	} catch (error) {
		console.error('Fetch error:', error);
		// responseElement.textContent = 'An error occurred. Please try again.';
		responseElement.textContent = 'An error occurred. Please try again.';		
		responseElement.style.color = 'red';
	}
}


async function login_user(form, event) {
	console.log('function login_user');
    event.preventDefault();

    const formData = new FormData(form);
    formData.append('action', 'ajax_login_user'); // Add action for PHP handler

    const responseElement = document.getElementById('login-response');
    // responseElement.textContent = 'Processing...';
	responseElement.textContent = 'Processing...';
	responseElement.style.color = 'black';

	formData.append('login_nonce', ajaxData.setLoginNonce);

	try {
		
		const response = await fetch(ajaxData.ajaxurl, {
			method: 'POST',
			mode: 'no-cors',  // Prevent CORS error
			body: formData,
		});
		
		const text = await response.text(); // Get the raw response as text
		console.log(text);
      
        if (!text.trim()) {
			console.log('aa');
			window.location.reload();
          
        }else{
			console.log('bb');

			const result = JSON.parse(text); // Parse it as JSON
			console.log('Parsed JSON:', result);

			if (result.success) {
				responseElement.innerHTML = result.data.message;
				responseElement.style.color = 'green';
				form.reset(); // Clear the form on success
				 
				 // Redirect to the provided URL
				if (result.data.redirect) {
					console.log('result.data.redirect:', result.data.redirect);
					window.location.href = result.data.redirect;
				}
				
			} else {
				responseElement.innerHTML = result.data.message;
				responseElement.style.color = 'red';
			}
			
		}

	} catch (error) {
		console.log('cc');
		console.error('Fetch error:', error);
		// responseElement.textContent = 'An error occurred. Please try again.';
		responseElement.textContent = 'An error occurred. Please try again.';		
		responseElement.style.color = 'red';
	}
}



