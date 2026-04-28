document.addEventListener('DOMContentLoaded', function () {
    const webauthnBox = document.querySelector('[data-webauthn-box]');
    if (!webauthnBox) {
        return;
    }

    const registerButton = webauthnBox.querySelector('[data-passkey-register]');
    const loginButton = webauthnBox.querySelector('[data-passkey-login]');
    const messageBox = webauthnBox.querySelector('[data-passkey-message]');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const rememberMeInput = document.getElementById('remember_me');
    const csrfInput = document.querySelector('input[name="csrf_token"]');
    const apiUrl = webauthnBox.dataset.apiUrl || '';

    const supportsPasskeys = (
        window.isSecureContext === true &&
        'PublicKeyCredential' in window &&
        !!navigator.credentials &&
        typeof navigator.credentials.create === 'function' &&
        typeof navigator.credentials.get === 'function'
    );

    const setMessage = function (message, type) {
        if (!messageBox) {
            return;
        }

        messageBox.textContent = message;
        messageBox.classList.remove('alert-success', 'alert-error', 'alert-warning');
        messageBox.classList.add(type === 'success' ? 'alert-success' : (type === 'warning' ? 'alert-warning' : 'alert-error'));
    };

    const setLoading = function (button, isLoading) {
        if (!button) {
            return;
        }

        if (isLoading) {
            button.dataset.originalText = button.dataset.originalText || button.textContent;
            button.textContent = button.dataset.loadingText || 'Please wait...';
            button.disabled = true;
            return;
        }

        button.textContent = button.dataset.originalText || button.textContent;
        button.disabled = false;
    };

    const utf8ToArrayBuffer = function (text) {
        return new TextEncoder().encode(String(text || '')).buffer;
    };

    const base64UrlToArrayBuffer = function (value) {
        if (value instanceof ArrayBuffer) {
            return value;
        }

        if (ArrayBuffer.isView(value)) {
            return value.buffer.slice(value.byteOffset, value.byteOffset + value.byteLength);
        }

        if (Array.isArray(value)) {
            return new Uint8Array(value).buffer;
        }

        const normalized = String(value || '').trim();
        if (normalized === '') {
            return new ArrayBuffer(0);
        }

        const base64Candidate = normalized.replace(/-/g, '+').replace(/_/g, '/').replace(/\s+/g, '');
        const padded = base64Candidate + '='.repeat((4 - (base64Candidate.length % 4)) % 4);

        try {
            const binary = window.atob(padded);
            const bytes = new Uint8Array(binary.length);

            for (let index = 0; index < binary.length; index += 1) {
                bytes[index] = binary.charCodeAt(index);
            }

            return bytes.buffer;
        } catch (error) {
            // Fallback for non-base64 challenge formats returned by some backends.
            return utf8ToArrayBuffer(normalized);
        }
    };

    const arrayBufferToBase64Url = function (buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';

        for (let index = 0; index < bytes.byteLength; index += 1) {
            binary += String.fromCharCode(bytes[index]);
        }

        return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    };

    const postForm = async function (payload) {
        const formData = new FormData();
        Object.keys(payload).forEach(function (key) {
            formData.append(key, payload[key]);
        });

        const response = await fetch(apiUrl, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'fetch',
            },
            body: formData,
        });

        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.message || 'Passkey request failed.');
        }

        return data;
    };

    const friendlyPasskeyError = function (error, context) {
        const errorName = error && error.name ? String(error.name) : '';
        const defaultMessage = context === 'register'
            ? 'Could not register passkey.'
            : 'Could not log in with passkey. You can still use password login.';

        if (errorName === 'InvalidStateError') {
            return 'This device already has a passkey for this account. Use "Login with Face ID / Fingerprint".';
        }

        if (errorName === 'NotAllowedError') {
            return 'Face ID / fingerprint was cancelled or timed out. Please try again.';
        }

        return (error && error.message) ? error.message : defaultMessage;
    };

    const mapCreateOptions = function (publicKey) {
        const mapped = Object.assign({}, publicKey);
        mapped.challenge = base64UrlToArrayBuffer(publicKey.challenge);
        mapped.user = Object.assign({}, publicKey.user || {});
        mapped.user.id = base64UrlToArrayBuffer(publicKey.user.id);

        if (Array.isArray(publicKey.excludeCredentials)) {
            mapped.excludeCredentials = publicKey.excludeCredentials.map(function (item) {
                return Object.assign({}, item, {
                    id: base64UrlToArrayBuffer(item.id),
                });
            });
        }

        return mapped;
    };

    const mapGetOptions = function (publicKey) {
        const mapped = Object.assign({}, publicKey);
        mapped.challenge = base64UrlToArrayBuffer(publicKey.challenge);

        if (Array.isArray(publicKey.allowCredentials)) {
            mapped.allowCredentials = publicKey.allowCredentials.map(function (item) {
                return Object.assign({}, item, {
                    id: base64UrlToArrayBuffer(item.id),
                });
            });
        }

        return mapped;
    };

    if (!supportsPasskeys) {
        if (registerButton) {
            registerButton.disabled = true;
        }
        if (loginButton) {
            loginButton.disabled = true;
        }
        if (window.isSecureContext !== true) {
            setMessage('Passkeys require HTTPS on mobile browsers. Open the secure HTTPS URL, then try again.', 'warning');
        } else {
            setMessage('Passkeys are not supported in this browser. Use email/password login.', 'warning');
        }
        return;
    }

    if (registerButton) {
        registerButton.addEventListener('click', async function () {
            const email = (emailInput ? emailInput.value : '').trim();
            const password = passwordInput ? passwordInput.value : '';
            const csrfToken = csrfInput ? csrfInput.value : '';

            if (!email || !password) {
                setMessage('Enter email and password first, then register your passkey.', 'error');
                return;
            }

            setLoading(registerButton, true);
            setMessage('Starting passkey registration...', 'warning');

            try {
                const beginPayload = await postForm({
                    csrf_token: csrfToken,
                    action: 'register_begin',
                    email: email,
                    password: password,
                });

                const credential = await navigator.credentials.create({
                    publicKey: mapCreateOptions(beginPayload.data.publicKey),
                });

                if (!credential || !credential.response) {
                    throw new Error('Passkey creation was cancelled.');
                }

                const finishPayload = await postForm({
                    csrf_token: csrfToken,
                    action: 'register_finish',
                    clientDataJSON: arrayBufferToBase64Url(credential.response.clientDataJSON),
                    attestationObject: arrayBufferToBase64Url(credential.response.attestationObject),
                    transports: JSON.stringify(
                        typeof credential.response.getTransports === 'function'
                            ? credential.response.getTransports()
                            : []
                    ),
                });

                setMessage(finishPayload.message || 'Passkey registered successfully.', 'success');
            } catch (error) {
                setMessage(friendlyPasskeyError(error, 'register'), 'error');
            } finally {
                setLoading(registerButton, false);
            }
        });
    }

    if (loginButton) {
        loginButton.addEventListener('click', async function () {
            const email = (emailInput ? emailInput.value : '').trim();
            const csrfToken = csrfInput ? csrfInput.value : '';

            if (!email) {
                setMessage('Enter your email first, then use passkey login.', 'error');
                return;
            }

            setLoading(loginButton, true);
            setMessage('Waiting for Face ID / Fingerprint...', 'warning');

            try {
                const beginPayload = await postForm({
                    csrf_token: csrfToken,
                    action: 'login_begin',
                    email: email,
                });

                const assertion = await navigator.credentials.get({
                    publicKey: mapGetOptions(beginPayload.data.publicKey),
                });

                if (!assertion || !assertion.response) {
                    throw new Error('Passkey login was cancelled.');
                }

                const finishPayload = await postForm({
                    csrf_token: csrfToken,
                    action: 'login_finish',
                    credentialId: arrayBufferToBase64Url(assertion.rawId),
                    clientDataJSON: arrayBufferToBase64Url(assertion.response.clientDataJSON),
                    authenticatorData: arrayBufferToBase64Url(assertion.response.authenticatorData),
                    signature: arrayBufferToBase64Url(assertion.response.signature),
                    remember_me: rememberMeInput && rememberMeInput.checked ? '1' : '0',
                });

                setMessage(finishPayload.message || 'Passkey login successful.', 'success');
                window.location.href = (finishPayload.data && finishPayload.data.redirect) ? finishPayload.data.redirect : '/';
            } catch (error) {
                setMessage(friendlyPasskeyError(error, 'login'), 'error');
            } finally {
                setLoading(loginButton, false);
            }
        });
    }
});
