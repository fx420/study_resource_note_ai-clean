document.addEventListener('DOMContentLoaded', function () {
    function validateField(input, regex, errorMessage) {
        const value = input.value;
        let errorElement = input.parentElement.querySelector('.validation-error');

        if (!errorElement) {
            errorElement = document.createElement('small');
            errorElement.classList.add('validation-error', 'form-text');
            input.parentElement.appendChild(errorElement);
        }

        if (value === '') {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            return true;
        }

        if (!regex.test(value)) {
            errorElement.textContent = errorMessage;
            input.classList.add('is-invalid');
            return false;
        } else {
            errorElement.textContent = '';
            input.classList.remove('is-invalid');
            return true;
        }
    }

    const username = document.getElementById('username');
    if (username) {
        username.addEventListener('input', function () {
            validateField(username, /^[^\s]+$/, 'Username must not contain spaces.');
        });
    }

    const email = document.getElementById('email');
    if (email) {
        email.addEventListener('input', function () {
            validateField(email, /^[a-zA-Z0-9._%+-]+@(gmail\.com|mail\.com|email\.com)$/, 'Email must end with @gmail.com, @mail.com, or @email.com.');
        });
    }

    const password = document.getElementById('password');
    if (password) {
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{7,}$/;
        password.addEventListener('input', function () {
            validateField(password, passwordRegex, 'Password must be more than 6 Characters and include at least 1 Uppercase letter, 1 Lowercase letter, 1 Digit, and 1 Special character.');
        });
    }

    const passwordConfirmation = document.getElementById('password_confirmation');
    if (passwordConfirmation && password) {
        passwordConfirmation.addEventListener('input', function () {
            let errorElement = passwordConfirmation.parentElement.querySelector('.validation-error');
            if (!errorElement) {
                errorElement = document.createElement('small');
                errorElement.classList.add('validation-error', 'form-text');
                passwordConfirmation.parentElement.appendChild(errorElement);
            }
            if (password.value !== passwordConfirmation.value) {
                errorElement.textContent = 'Passwords do not match.';
                passwordConfirmation.classList.add('is-invalid');
            } else {
                errorElement.textContent = '';
                passwordConfirmation.classList.remove('is-invalid');
            }
        });
    }

    const togglePassword = document.getElementById('togglePassword');
    if (togglePassword && password) {
        togglePassword.addEventListener('click', function () {
            if (password.type === 'password') {
                password.type = 'text';
                togglePassword.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                password.type = 'password';
                togglePassword.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    }

    const togglePasswordConfirm = document.getElementById('togglePasswordConfirm');
    if (togglePasswordConfirm && passwordConfirmation) {
        togglePasswordConfirm.addEventListener('click', function () {
            if (passwordConfirmation.type === 'password') {
                passwordConfirmation.type = 'text';
                togglePasswordConfirm.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                passwordConfirmation.type = 'password';
                togglePasswordConfirm.innerHTML = '<i class="fas fa-eye"></i>';
            }
        });
    }
});
