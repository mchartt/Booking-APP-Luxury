/**
 * login.js - Logica frontend per login/registrazione admin
 */

const AUTH_API = './api/auth.php';

// ========== INIZIALIZZAZIONE ==========
document.addEventListener('DOMContentLoaded', function() {
    initTabs();
    initForms();
    initPasswordToggles();
    initPasswordValidation();
    checkUrlParams();
    checkExistingSession();
});

// ========== VERIFICA SESSIONE ESISTENTE ==========
async function checkExistingSession() {
    try {
        const response = await fetch(`${AUTH_API}?action=check`, {
            credentials: 'include'
        });
        const data = await response.json();

        if (data.authenticated) {
            // Gia loggato, redirect a dashboard
            window.location.href = 'admin.html';
        }
    } catch (error) {
        // Sessione non attiva - comportamento normale per utenti non autenticati
    }
}

// ========== TABS ==========
function initTabs() {
    const tabs = document.querySelectorAll('.auth-tab');
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Aggiorna tabs
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            // Mostra form corretto
            if (tab.dataset.tab === 'login') {
                loginForm.classList.add('active');
                registerForm.classList.remove('active');
            } else {
                loginForm.classList.remove('active');
                registerForm.classList.add('active');
            }

            // Nascondi alert
            hideAlert();
        });
    });
}

// ========== FORMS ==========
function initForms() {
    // Login form
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleLogin();
    });

    // Register form
    document.getElementById('registerForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        await handleRegister();
    });
}

// ========== LOGIN ==========
async function handleLogin() {
    const form = document.getElementById('loginForm');
    const btn = form.querySelector('button[type="submit"]');

    const username = document.getElementById('loginUsername').value.trim();
    const password = document.getElementById('loginPassword').value;

    // Validazione base
    if (!username || !password) {
        showAlert('Inserisci username e password', 'error');
        return;
    }

    // Mostra loader
    btn.classList.add('loading');
    btn.disabled = true;

    try {
        const response = await fetch(`${AUTH_API}?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ username, password })
        });

        const data = await response.json();

        if (data.success) {
            showAlert('Login effettuato! Reindirizzamento...', 'success');
            setTimeout(() => {
                window.location.href = 'admin.html';
            }, 1000);
        } else {
            showAlert(data.message || 'Errore nel login', 'error');
        }
    } catch (error) {
        console.error('Login error:', error);
        showAlert('Errore di connessione. Riprova.', 'error');
    } finally {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

// ========== REGISTRAZIONE ==========
async function handleRegister() {
    const form = document.getElementById('registerForm');
    const btn = form.querySelector('button[type="submit"]');

    const username = document.getElementById('regUsername').value.trim();
    const email = document.getElementById('regEmail').value.trim();
    const password = document.getElementById('regPassword').value;
    const confirmPassword = document.getElementById('regConfirmPassword').value;

    // Validazione client-side
    const errors = validateRegistration(username, email, password, confirmPassword);
    if (errors.length > 0) {
        showAlert(errors.join('<br>'), 'error');
        return;
    }

    // Mostra loader
    btn.classList.add('loading');
    btn.disabled = true;

    try {
        const response = await fetch(`${AUTH_API}?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                username,
                email,
                password,
                confirm_password: confirmPassword
            })
        });

        const data = await response.json();

        if (data.success) {
            showAlert(data.message || 'Registrazione completata! Controlla la tua email.', 'success');
            form.reset();

            // In ambiente di sviluppo mostra il link di verifica nell'alert
            if (data.dev_verification_url) {
                showAlert(
                    `Registrazione completata!<br>
                     <small>DEV MODE: <a href="${data.dev_verification_url}" target="_blank">Clicca qui per verificare</a></small>`,
                    'info'
                );
            }

            // Resetta validazione password
            resetPasswordValidation();

        } else {
            const errorMsg = data.errors ? data.errors.join('<br>') : data.message;
            showAlert(errorMsg || 'Errore nella registrazione', 'error');
        }
    } catch (error) {
        console.error('Register error:', error);
        showAlert('Errore di connessione. Riprova.', 'error');
    } finally {
        btn.classList.remove('loading');
        btn.disabled = false;
    }
}

// ========== VALIDAZIONE REGISTRAZIONE ==========
function validateRegistration(username, email, password, confirmPassword) {
    const errors = [];

    // Username
    if (!username) {
        errors.push('Username obbligatorio');
    } else if (username.length < 3 || username.length > 50) {
        errors.push('Username deve essere tra 3 e 50 caratteri');
    } else if (!/^[a-zA-Z0-9_]+$/.test(username)) {
        errors.push('Username puo contenere solo lettere, numeri e underscore');
    }

    // Email
    if (!email) {
        errors.push('Email obbligatoria');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errors.push('Email non valida');
    }

    // Password
    if (!password) {
        errors.push('Password obbligatoria');
    } else {
        if (password.length < 8) {
            errors.push('Password deve avere almeno 8 caratteri');
        }
        if (!/[A-Z]/.test(password)) {
            errors.push('Password deve contenere almeno una maiuscola');
        }
        if (!/[a-z]/.test(password)) {
            errors.push('Password deve contenere almeno una minuscola');
        }
        if (!/[0-9]/.test(password)) {
            errors.push('Password deve contenere almeno un numero');
        }
        if (!/[!@#$%^&*()_+\-=\[\]{};\':\"\\|,.<>\/?~`]/.test(password)) {
            errors.push('Password deve contenere almeno un carattere speciale (!@#$%^&*...)');
        }
    }

    // Conferma password
    if (password !== confirmPassword) {
        errors.push('Le password non coincidono');
    }

    return errors;
}

// ========== PASSWORD TOGGLES ==========
function initPasswordToggles() {
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const targetId = btn.dataset.target;
            const input = document.getElementById(targetId);
            const icon = btn.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });
}

// ========== PASSWORD VALIDATION ==========
function initPasswordValidation() {
    const passwordInput = document.getElementById('regPassword');
    const confirmInput = document.getElementById('regConfirmPassword');
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');

    // Validazione forza password
    passwordInput.addEventListener('input', () => {
        const password = passwordInput.value;
        const strength = calculatePasswordStrength(password);

        // Aggiorna barra
        strengthBar.style.setProperty('--strength', strength.percent + '%');
        strengthBar.style.setProperty('--strength-color', strength.color);
        strengthText.textContent = strength.label;
        strengthText.style.color = strength.color;

        // Aggiorna requisiti
        updateRequirements(password);

        // Verifica match con conferma
        if (confirmInput.value) {
            checkPasswordMatch();
        }
    });

    // Verifica match password
    confirmInput.addEventListener('input', checkPasswordMatch);
}

function calculatePasswordStrength(password) {
    let score = 0;

    if (password.length >= 8) score += 25;
    if (password.length >= 12) score += 15;
    if (/[A-Z]/.test(password)) score += 20;
    if (/[a-z]/.test(password)) score += 10;
    if (/[0-9]/.test(password)) score += 20;
    if (/[^A-Za-z0-9]/.test(password)) score += 10;

    let label, color;
    if (score < 30) {
        label = 'Debole';
        color = '#ef4444';
    } else if (score < 60) {
        label = 'Media';
        color = '#f59e0b';
    } else if (score < 80) {
        label = 'Buona';
        color = '#22c55e';
    } else {
        label = 'Ottima';
        color = '#10b981';
    }

    return { percent: Math.min(score, 100), label, color };
}

function updateRequirements(password) {
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqNumber = document.getElementById('req-number');

    reqLength.classList.toggle('valid', password.length >= 8);
    reqUpper.classList.toggle('valid', /[A-Z]/.test(password));
    reqNumber.classList.toggle('valid', /[0-9]/.test(password));
}

function checkPasswordMatch() {
    const password = document.getElementById('regPassword').value;
    const confirm = document.getElementById('regConfirmPassword').value;
    const hint = document.getElementById('passwordMatch');
    const confirmInput = document.getElementById('regConfirmPassword');

    if (!confirm) {
        hint.textContent = '';
        hint.className = 'input-hint';
        confirmInput.classList.remove('error', 'success');
        return;
    }

    if (password === confirm) {
        hint.textContent = 'Le password coincidono';
        hint.className = 'input-hint success';
        confirmInput.classList.remove('error');
        confirmInput.classList.add('success');
    } else {
        hint.textContent = 'Le password non coincidono';
        hint.className = 'input-hint error';
        confirmInput.classList.remove('success');
        confirmInput.classList.add('error');
    }
}

function resetPasswordValidation() {
    const strengthBar = document.querySelector('.strength-bar');
    const strengthText = document.querySelector('.strength-text');

    strengthBar.style.setProperty('--strength', '0%');
    strengthText.textContent = '';

    document.querySelectorAll('.password-requirements li').forEach(li => {
        li.classList.remove('valid');
    });

    document.getElementById('passwordMatch').textContent = '';
    document.getElementById('regConfirmPassword').classList.remove('error', 'success');
}

// ========== ALERT BOX ==========
function showAlert(message, type = 'info') {
    const alertBox = document.getElementById('alertBox');
    const alertIcon = alertBox.querySelector('.alert-icon');
    const alertMessage = alertBox.querySelector('.alert-message');
    const alertClose = alertBox.querySelector('.alert-close');

    // Imposta icona
    const icons = {
        success: 'fas fa-check-circle',
        error: 'fas fa-exclamation-circle',
        warning: 'fas fa-exclamation-triangle',
        info: 'fas fa-info-circle'
    };

    alertIcon.className = 'alert-icon ' + (icons[type] || icons.info);
    alertMessage.innerHTML = message;
    alertBox.className = 'alert-box ' + type;
    alertBox.style.display = 'flex';

    // Close button
    alertClose.onclick = hideAlert;

    // Auto-hide dopo 10 secondi per messaggi non error
    if (type !== 'error') {
        setTimeout(hideAlert, 10000);
    }
}

function hideAlert() {
    const alertBox = document.getElementById('alertBox');
    alertBox.style.display = 'none';
}

// ========== URL PARAMS ==========
function checkUrlParams() {
    const params = new URLSearchParams(window.location.search);

    // Errori da verifica email
    if (params.get('error') === 'invalid_token') {
        showAlert('Token di verifica non valido o scaduto.', 'error');
    } else if (params.get('error') === 'token_expired') {
        showAlert('Il link di verifica e scaduto. Registrati nuovamente.', 'error');
    }

    // Successo verifica
    if (params.get('verified') === '1') {
        if (params.get('first_admin') === '1') {
            showAlert('Email verificata! Sei il primo admin, il tuo account e gia attivo. Puoi accedere.', 'success');
        } else if (params.get('pending') === '1') {
            showAlert('Email verificata! Il tuo account e in attesa di approvazione da parte di un amministratore.', 'info');
        } else {
            showAlert('Email verificata con successo!', 'success');
        }
    }

    // Pulisci URL
    if (params.toString()) {
        window.history.replaceState({}, document.title, window.location.pathname);
    }
}
