document.addEventListener('DOMContentLoaded', function () {
    const chartCanvases = document.querySelectorAll('[data-chart-type]');
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const toastStack = document.getElementById('toastStack');
    const toastTrigger = document.querySelector('.toast-trigger');
    const expensesPage = document.querySelector('[data-expenses-page]');
    const categoriesPage = document.querySelector('[data-categories-page]');
    const editModal = document.getElementById('categoryEditModal');
    const deleteModal = document.getElementById('categoryDeleteModal');
    const editButtons = document.querySelectorAll('[data-category-edit]');
    const deleteButtons = document.querySelectorAll('[data-category-delete]');
    const confirmDeleteButton = document.getElementById('confirmCategoryDelete');
    const editCategoryId = document.getElementById('editCategoryId');
    const editCategoryName = document.getElementById('editCategoryName');
    const editOriginalName = document.getElementById('editOriginalName');
    const deleteCategoryName = document.getElementById('deleteCategoryName');
    const settingsForms = document.querySelectorAll('[data-settings-form]');
    const themeOptions = document.querySelectorAll('[data-theme-option]');
    const avatarChoiceButtons = document.querySelectorAll('[data-avatar-choice]');
    const avatarModeInput = document.getElementById('avatarMode');
    const avatarUploadPanel = document.getElementById('avatarUploadPanel');
    const openDeleteAccountModalButton = document.getElementById('openDeleteAccountModal');
    const confirmDeleteAccountButton = document.getElementById('confirmDeleteAccount');
    const deleteAccountModal = document.getElementById('deleteAccountModal');
    const deleteAccountForm = document.getElementById('deleteAccountForm');
    let pendingDeleteForm = null;

    const closeSidebar = function () {
        if (!sidebar) {
            return;
        }

        sidebar.classList.remove('open');
        if (sidebarOverlay) {
            sidebarOverlay.classList.remove('open');
        }
        document.body.style.overflow = '';
    };

    const openSidebar = function () {
        if (!sidebar) {
            return;
        }

        sidebar.classList.add('open');
        if (sidebarOverlay) {
            sidebarOverlay.classList.add('open');
        }
        document.body.style.overflow = 'hidden';
    };

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function () {
            if (sidebar.classList.contains('open')) {
                closeSidebar();
            } else {
                openSidebar();
            }
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            closeSidebar();
        });
    }

    if (sidebar) {
        sidebar.querySelectorAll('a.nav-item, a.sidebar-logout').forEach(function (link) {
            link.addEventListener('click', function () {
                if (window.innerWidth <= 900) {
                    closeSidebar();
                }
            });
        });
    }

    const showToast = function (message, type) {
        if (!toastStack || !message) {
            return;
        }

        const toast = document.createElement('div');
        toast.className = 'toast toast-' + (type || 'success');
        toast.textContent = message;
        toastStack.appendChild(toast);

        requestAnimationFrame(function () {
            toast.classList.add('is-visible');
        });

        window.setTimeout(function () {
            toast.classList.remove('is-visible');
            window.setTimeout(function () {
                toast.remove();
            }, 240);
        }, 3200);
    };

    if (toastTrigger) {
        showToast(toastTrigger.dataset.toastMessage || '', toastTrigger.dataset.toastType || 'success');
    }

    let deferredInstallPrompt = null;
    const userAgent = window.navigator.userAgent || '';
    const platform = String(window.navigator.userAgentData?.platform || window.navigator.platform || '').toLowerCase();
    const isAndroid = /android/i.test(userAgent) || platform.includes('android');
    const isIos = !isAndroid && (
        /iphone|ipad|ipod/i.test(userAgent) ||
        (platform.includes('mac') && window.navigator.maxTouchPoints > 1)
    );

    const showInstallHelpModal = function (message) {
        let modal = document.querySelector('[data-install-help-modal]');

        if (!modal) {
            modal = document.createElement('div');
            modal.className = 'install-help-modal';
            modal.setAttribute('data-install-help-modal', 'true');
            modal.innerHTML = ''
                + '<div class="install-help-card" role="dialog" aria-modal="true" aria-label="Install App Help">'
                + '  <h3>Install Expense Tracker</h3>'
                + '  <p class="install-help-message"></p>'
                + '  <div class="install-help-actions">'
                + '    <button type="button" class="button" data-install-help-close>OK</button>'
                + '  </div>'
                + '</div>';
            document.body.appendChild(modal);

            const closeButton = modal.querySelector('[data-install-help-close]');
            if (closeButton) {
                closeButton.addEventListener('click', function () {
                    modal.classList.remove('is-visible');
                });
            }

            modal.addEventListener('click', function (event) {
                if (event.target === modal) {
                    modal.classList.remove('is-visible');
                }
            });
        }

        const messageElement = modal.querySelector('.install-help-message');
        if (messageElement) {
            messageElement.textContent = message;
        }

        modal.classList.add('is-visible');
    };

    const showInstallBanner = function () {
        if (window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true) {
            return;
        }

        if (document.querySelector('[data-install-banner]')) {
            return;
        }

        const bannerMessage = isIos
            ? 'Install Expense Tracker. On iPhone: tap Share in Safari, then tap "Add to Home Screen".'
            : isAndroid
                ? 'Install Expense Tracker. On Android: open browser menu (⋮), then tap "Install app" or "Add to Home screen".'
                : 'Install Expense Tracker for faster access.';

        const banner = document.createElement('div');
        banner.className = 'install-banner';
        banner.setAttribute('data-install-banner', 'true');
        banner.innerHTML = ''
            + '<p class="install-banner-text">' + bannerMessage + '</p>'
            + '<div class="install-banner-actions">'
            + '  <button type="button" class="button" data-install-app-button>Install App</button>'
            + '  <button type="button" class="button secondary" data-install-dismiss-button>Later</button>'
            + '</div>';

        document.body.appendChild(banner);

        const installButton = banner.querySelector('[data-install-app-button]');
        const dismissButton = banner.querySelector('[data-install-dismiss-button]');

        if (installButton) {
            installButton.addEventListener('click', async function () {
                if (deferredInstallPrompt) {
                    deferredInstallPrompt.prompt();
                    await deferredInstallPrompt.userChoice;
                    deferredInstallPrompt = null;
                    banner.remove();
                    return;
                }

                if (isIos) {
                    showInstallHelpModal('On iPhone: tap Share in Safari, then tap "Add to Home Screen".');
                    return;
                }

                if (isAndroid) {
                    showInstallHelpModal('On Android: open browser menu (⋮) and tap "Install app" or "Add to Home screen".');
                    return;
                }

                showInstallHelpModal('Use your browser menu and choose "Install app" or "Add to Home Screen".');
            });
        }

        if (dismissButton) {
            dismissButton.addEventListener('click', function () {
                banner.remove();
            });
        }
    };

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredInstallPrompt = event;
        showInstallBanner();
    });

    window.addEventListener('appinstalled', function () {
        deferredInstallPrompt = null;
        const banner = document.querySelector('[data-install-banner]');
        if (banner) {
            banner.remove();
        }
    });

    window.setTimeout(showInstallBanner, 1200);

    const getBasePath = function () {
        const path = window.location.pathname;
        const markers = ['/pages/', '/auth/', '/api/'];

        for (const marker of markers) {
            const index = path.indexOf(marker);
            if (index !== -1) {
                return path.slice(0, index);
            }
        }

        return path.replace(/\/+$/, '');
    };

    const basePath = getBasePath();

    const createFormData = function (form) {
        return new FormData(form);
    };

    const setLoadingState = function (button, isLoading) {
        if (!button) {
            return;
        }

        if (isLoading) {
            button.dataset.originalText = button.dataset.originalText || button.textContent;
            button.textContent = button.dataset.loadingText || 'Saving...';
            button.disabled = true;
            button.classList.add('is-loading');
            return;
        }

        button.textContent = button.dataset.originalText || button.textContent;
        button.disabled = false;
        button.classList.remove('is-loading');
    };

    const submitApiForm = async function (url, form) {
        const response = await fetch(url, {
            method: 'POST',
            body: createFormData(form),
            headers: {
                'X-Requested-With': 'fetch',
            },
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Request failed.');
        }

        return payload;
    };

    const fetchJson = async function (url) {
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'fetch',
            },
        });
        const payload = await response.json();

        if (!response.ok || !payload.success) {
            throw new Error(payload.message || 'Request failed.');
        }

        return payload;
    };

    const escapeHtml = function (value) {
        return String(value).replace(/[&<>"']/g, function (character) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
            })[character];
        });
    };

    const getCurrencySymbol = function () {
        const currencySelect = document.getElementById('settings-currency');
        const currency = currencySelect
            ? currencySelect.value
            : (document.body.dataset.userCurrency || 'USD');
        const symbols = {
            USD: '$',
            SAR: 'SAR ',
            PHP: 'PHP ',
            EUR: 'EUR ',
            GBP: 'GBP ',
        };

        return symbols[currency] || (currency + ' ');
    };

    const formatCurrency = function (amount) {
        const value = Number(amount || 0);
        return getCurrencySymbol() + value.toFixed(2);
    };

    const isDuplicateCategoryName = function (form) {
        const nameInput = form.querySelector('input[name="name"]');
        const originalNameInput = form.querySelector('input[name="original_name"]');

        if (!nameInput) {
            return false;
        }

        const normalizedName = nameInput.value.trim().replace(/\s+/g, ' ').toLowerCase();
        const originalName = originalNameInput ? originalNameInput.value.trim().toLowerCase() : '';
        const existingNames = JSON.parse(form.dataset.existingNames || '[]');

        if (!normalizedName) {
            showToast('Category name is required.', 'error');
            nameInput.focus();
            return true;
        }

        if (existingNames.includes(normalizedName) && normalizedName !== originalName) {
            showToast('You already have a category with this name.', 'error');
            nameInput.focus();
            return true;
        }

        return false;
    };

    const bindLoadingSubmitButtons = function (form) {
        form.querySelectorAll('[data-loading-button]').forEach(function (button) {
            if (button.type === 'submit') {
                setLoadingState(button, true);
            }
        });
    };

    const clearLoadingSubmitButtons = function (form) {
        form.querySelectorAll('[data-loading-button]').forEach(function (button) {
            setLoadingState(button, false);
        });
    };

    const closeModal = function (modal) {
        if (!modal) {
            return;
        }

        modal.hidden = true;
        document.body.style.overflow = '';
    };

    const openModal = function (modal) {
        if (!modal) {
            return;
        }

        modal.hidden = false;
        document.body.style.overflow = 'hidden';
    };

    document.querySelectorAll('[data-modal-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            closeModal(button.closest('.modal-backdrop'));
        });
    });

    [editModal, deleteModal, deleteAccountModal].forEach(function (modal) {
        if (!modal) {
            return;
        }

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                closeModal(modal);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeSidebar();
            closeModal(editModal);
            closeModal(deleteModal);
            closeModal(deleteAccountModal);
        }
    });

    editButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            if (!editModal || !editCategoryId || !editCategoryName || !editOriginalName) {
                return;
            }

            editCategoryId.value = button.dataset.categoryId || '';
            editCategoryName.value = button.dataset.categoryName || '';
            editOriginalName.value = (button.dataset.categoryName || '').toLowerCase();
            openModal(editModal);
            editCategoryName.focus();
            editCategoryName.select();
        });
    });

    deleteButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            pendingDeleteForm = button.closest('form');

            if (!deleteModal || !deleteCategoryName) {
                if (pendingDeleteForm) {
                    pendingDeleteForm.submit();
                }

                return;
            }

            deleteCategoryName.textContent = button.dataset.categoryName || '';
            openModal(deleteModal);
        });
    });

    if (confirmDeleteButton) {
        confirmDeleteButton.addEventListener('click', function () {
            if (pendingDeleteForm) {
                pendingDeleteForm.submit();
            }
        });
    }

    document.querySelectorAll('[data-category-form]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const nameInput = form.querySelector('input[name="name"]');
            const originalNameInput = form.querySelector('input[name="original_name"]');

            if (!nameInput) {
                return;
            }

            const normalizedName = nameInput.value.trim().replace(/\s+/g, ' ').toLowerCase();
            const originalName = originalNameInput ? originalNameInput.value.trim().toLowerCase() : '';
            const existingNames = JSON.parse(form.dataset.existingNames || '[]');

            if (!normalizedName) {
                event.preventDefault();
                showToast('Category name is required.', 'error');
                nameInput.focus();
                return;
            }

            if (existingNames.includes(normalizedName) && normalizedName !== originalName) {
                event.preventDefault();
                showToast('You already have a category with this name.', 'error');
                nameInput.focus();
            }
        });
    });

    const categoryIcon = function (name) {
        const key = String(name || '').trim().toLowerCase();

        if (key.includes('food')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M7 3v7M10 3v7M7 7h3M16 3v18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M16 3c2.2 1.4 3 3.3 3 5.4V11h-3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('bill')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M7 3h8l4 4v14H7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 3v4h4M10 12h4M10 16h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('transport') || key.includes('taxi') || key.includes('bus')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M6 16V8a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M5 16h14M8 19h.01M16 19h.01M8 16v3M16 16v3M9 10h6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('shop')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M6 8h12l-1.2 10H7.2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M9 8a3 3 0 1 1 6 0" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('health') || key.includes('doctor') || key.includes('pharma')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><rect x="4" y="4" width="16" height="16" rx="4" stroke="currentColor" stroke-width="1.8"></rect></svg>';
        }

        if (key.includes('entertain')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M5 8.5 12 5l7 3.5v7L12 19l-7-3.5z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="m10 10 4 2-4 2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('education') || key.includes('course')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="m4 8 8-4 8 4-8 4z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M8 10v4c0 1.2 1.8 2 4 2s4-.8 4-2v-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M20 9v5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('coffee')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M6 10h9v4a4 4 0 0 1-4 4H9a3 3 0 0 1-3-3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 11h1a2 2 0 1 1 0 4h-1M8 6v2M11 5v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        if (key.includes('pet')) {
            return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M8.5 10.5c1.4-1 5.6-1 7 0 1.7 1.3 2.5 3.4 2 5.3-.4 1.7-1.7 2.7-3.3 2.7-.8 0-1.5-.3-2.2-.8-.7.5-1.4.8-2.2.8-1.6 0-2.9-1-3.3-2.7-.5-1.9.3-4 2-5.3Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M8 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM13.5 6a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0ZM19 7.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
        }

        return '<svg viewBox="0 0 24 24" aria-hidden="true" fill="none"><path d="M11 4H6a2 2 0 0 0-2 2v5l8.5 8.5a2.1 2.1 0 0 0 3 0l4-4a2.1 2.1 0 0 0 0-3z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="M7.5 8.5h.01" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
    };

    const bindCategoryActionButtons = function () {
        document.querySelectorAll('[data-category-edit]').forEach(function (button) {
            button.onclick = function () {
                if (!editModal || !editCategoryId || !editCategoryName || !editOriginalName) {
                    return;
                }

                editCategoryId.value = button.dataset.categoryId || '';
                editCategoryName.value = button.dataset.categoryName || '';
                editOriginalName.value = (button.dataset.categoryName || '').toLowerCase();
                openModal(editModal);
                editCategoryName.focus();
                editCategoryName.select();
            };
        });

        document.querySelectorAll('[data-category-delete]').forEach(function (button) {
            button.onclick = function () {
                pendingDeleteForm = button.closest('form');

                if (!deleteModal || !deleteCategoryName) {
                    return;
                }

                deleteCategoryName.textContent = button.dataset.categoryName || '';
                openModal(deleteModal);
            };
        });
    };

    bindCategoryActionButtons();

    const renderCategories = function (categories) {
        const tableBody = document.querySelector('.categories-table tbody');
        if (!tableBody) {
            return;
        }

        tableBody.innerHTML = categories.map(function (category) {
            const isDefault = Number(category.is_default) === 1;
            const safeName = escapeHtml(category.name);

            return '<tr class="category-row">'
                + '<td class="category-icon-cell"><span class="category-icon">' + categoryIcon(category.name) + '</span></td>'
                + '<td><strong>' + safeName + '</strong></td>'
                + '<td>' + (isDefault
                    ? '<span class="badge badge-soft-green">Default</span>'
                    : '<span class="badge badge-soft">Custom</span>') + '</td>'
                + '<td class="category-actions">' + (isDefault
                    ? '<span class="muted">Protected</span>'
                    : '<button class="icon-outline-button" type="button" data-category-edit data-category-id="' + escapeHtml(category.id) + '" data-category-name="' + safeName + '" aria-label="Edit ' + safeName + '" title="Edit"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 20h4l9.5-9.5-4-4L4.5 16V20Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path><path d="m12.5 7.5 4 4 1.5-1.5a1.414 1.414 0 0 0 0-2l-2-2a1.414 1.414 0 0 0-2 0L12.5 7.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></button>'
                        + '<form method="post" class="inline-form" data-category-delete-form><input type="hidden" name="csrf_token" value="' + escapeHtml(document.querySelector('input[name="csrf_token"]').value) + '"><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="' + escapeHtml(category.id) + '"><button class="icon-outline-button icon-outline-danger" type="button" data-category-delete data-category-id="' + escapeHtml(category.id) + '" data-category-name="' + safeName + '" aria-label="Delete ' + safeName + '" title="Delete"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7h14M10 11v5M14 11v5M8 7l1-2h6l1 2M8 7v11a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"></path></svg></button></form>') + '</td>'
                + '</tr>';
        }).join('');

        const existingNames = JSON.stringify(categories.map(function (category) {
            return String(category.name).toLowerCase();
        }));

        document.querySelectorAll('[data-category-form]').forEach(function (form) {
            form.dataset.existingNames = existingNames;
        });

        bindCategoryActionButtons();
    };

    if (categoriesPage) {
        const categoriesApiUrl = categoriesPage.dataset.apiUrl;
        const createCategoryForm = document.querySelector('[data-category-create-form]');
        const editCategoryForm = document.querySelector('[data-category-edit-form]');

        const refreshCategories = async function () {
            const payload = await fetchJson(categoriesApiUrl);
            renderCategories(payload.data.categories || []);
        };

        if (createCategoryForm) {
            createCategoryForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (isDuplicateCategoryName(createCategoryForm)) {
                    return;
                }
                const submitButton = createCategoryForm.querySelector('[type="submit"]');
                setLoadingState(submitButton, true);

                try {
                    const payload = await submitApiForm(categoriesApiUrl, createCategoryForm);
                    createCategoryForm.reset();
                    showToast(payload.message, 'success');
                    await refreshCategories();
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    setLoadingState(submitButton, false);
                }
            });
        }

        if (editCategoryForm) {
            editCategoryForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (isDuplicateCategoryName(editCategoryForm)) {
                    return;
                }
                const submitButton = editCategoryForm.querySelector('[type="submit"]');
                setLoadingState(submitButton, true);

                try {
                    const payload = await submitApiForm(categoriesApiUrl, editCategoryForm);
                    showToast(payload.message, 'success');
                    closeModal(editModal);
                    editCategoryForm.reset();
                    await refreshCategories();
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    setLoadingState(submitButton, false);
                }
            });
        }

        if (confirmDeleteButton) {
            confirmDeleteButton.addEventListener('click', async function () {
                if (!pendingDeleteForm) {
                    return;
                }

                setLoadingState(confirmDeleteButton, true);

                try {
                    const payload = await submitApiForm(categoriesApiUrl, pendingDeleteForm);
                    showToast(payload.message, 'success');
                    closeModal(deleteModal);
                    await refreshCategories();
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    setLoadingState(confirmDeleteButton, false);
                    pendingDeleteForm = null;
                }
            });
        }
    }

    const renderExpenses = function (expenses, summary) {
        const tbody = document.querySelector('[data-expenses-tbody]');
        const totalSummary = document.querySelector('[data-expense-summary="total"]');
        const monthlySummary = document.querySelector('[data-expense-summary="monthly"]');
        const upcomingSummary = document.querySelector('[data-expense-summary="upcoming"]');
        const editBaseUrl = expensesPage ? expensesPage.dataset.baseEditUrl || '' : '';

        if (totalSummary) {
            totalSummary.textContent = formatCurrency(summary.total_expenses || 0);
        }

        if (monthlySummary) {
            monthlySummary.textContent = formatCurrency(summary.monthly_expenses || 0);
        }

        if (upcomingSummary) {
            upcomingSummary.textContent = formatCurrency(summary.upcoming_deductions || 0);
        }

        if (!tbody) {
            return;
        }

        if (!expenses.length) {
            tbody.innerHTML = '<tr><td colspan="4" class="muted">No expenses found for the selected filters.</td></tr>';
            return;
        }

        tbody.innerHTML = expenses.map(function (expense) {
            const isPaid = String(expense.payment_status) === 'paid';
            const safeTitle = escapeHtml(expense.title);
            const safeCategory = escapeHtml(expense.category_name || 'Uncategorized');
            const safeDate = escapeHtml(expense.expense_date);
            const safeId = escapeHtml(expense.id);

            return '<tr>'
                + '<td><strong>' + safeTitle + '</strong><div class="table-meta">' + (isPaid
                    ? '<span class="badge badge-success">Paid</span>'
                    : '<span class="badge badge-warning">Not Paid</span>') + '</div></td>'
                + '<td>' + safeCategory + '</td>'
                + '<td>' + safeDate + '</td>'
                + '<td class="' + (isPaid ? 'amount-positive' : 'amount-negative') + '">'
                + formatCurrency(expense.amount || 0)
                + '<div class="table-actions-inline">'
                + '<a class="button secondary" href="' + editBaseUrl + safeId + '">Edit</a>'
                + '<form method="post" class="inline-form" data-expense-delete-form><input type="hidden" name="csrf_token" value="' + escapeHtml(document.querySelector('input[name="csrf_token"]').value) + '"><input type="hidden" name="action" value="delete"><input type="hidden" name="expense_id" value="' + safeId + '"><button class="danger" type="submit" data-loading-button data-loading-text="Deleting...">Delete</button></form>'
                + '</div></td></tr>';
        }).join('');
    };

    if (expensesPage) {
        const expensesApiUrl = expensesPage.dataset.apiUrl;
        const expenseForm = document.querySelector('[data-expense-form]');
        const expenseFilterForm = document.querySelector('[data-expense-filter-form]');

        const refreshExpenses = async function (queryString) {
            const payload = await fetchJson(expensesApiUrl + (queryString ? '?' + queryString : ''));
            renderExpenses(payload.data.expenses || [], payload.data.summary || {});
        };

        if (expenseForm) {
            expenseForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                bindLoadingSubmitButtons(expenseForm);

                try {
                    const payload = await submitApiForm(expensesApiUrl, expenseForm);
                    showToast(payload.message, 'success');
                    expenseForm.reset();
                    const expenseIdField = expenseForm.querySelector('[data-expense-id]');
                    if (expenseIdField) {
                        expenseIdField.value = '0';
                    }
                    const pendingOption = expenseForm.querySelector('input[name="payment_status"][value="pending"]');
                    if (pendingOption) {
                        pendingOption.checked = true;
                    }
                    await refreshExpenses(expenseFilterForm ? new URLSearchParams(new FormData(expenseFilterForm)).toString() : '');
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    clearLoadingSubmitButtons(expenseForm);
                }
            });
        }

        if (expenseFilterForm) {
            expenseFilterForm.addEventListener('submit', async function (event) {
                event.preventDefault();
                bindLoadingSubmitButtons(expenseFilterForm);

                try {
                    const queryString = new URLSearchParams(new FormData(expenseFilterForm)).toString();
                    await refreshExpenses(queryString);
                    showToast('Expenses filtered successfully.', 'success');
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    clearLoadingSubmitButtons(expenseFilterForm);
                }
            });
        }

        document.addEventListener('submit', async function (event) {
            const form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            if (!form.matches('[data-expense-delete-form]')) {
                return;
            }

            event.preventDefault();
            bindLoadingSubmitButtons(form);

            try {
                const payload = await submitApiForm(expensesApiUrl, form);
                showToast(payload.message, 'success');
                await refreshExpenses(expenseFilterForm ? new URLSearchParams(new FormData(expenseFilterForm)).toString() : '');
            } catch (error) {
                showToast(error.message, 'error');
            } finally {
                clearLoadingSubmitButtons(form);
            }
        });
    }

    const setTheme = function (theme) {
        document.body.classList.remove('theme-light', 'theme-dark');
        document.body.classList.add('theme-' + theme);
    };

    themeOptions.forEach(function (option) {
        option.addEventListener('change', function () {
            setTheme(option.value === 'dark' ? 'dark' : 'light');
            showToast('Theme preview updated', 'success');
        });
    });

    avatarChoiceButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const nextMode = button.dataset.avatarChoice || 'generated';

            if (avatarModeInput) {
                avatarModeInput.value = nextMode;
            }

            avatarChoiceButtons.forEach(function (choiceButton) {
                choiceButton.classList.toggle('is-active', choiceButton === button);
            });

            if (avatarUploadPanel) {
                avatarUploadPanel.classList.toggle('is-visible', nextMode === 'uploaded');
            }
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = button.parentElement ? button.parentElement.querySelector('[data-password-input]') : null;

            if (!input) {
                return;
            }

            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            button.textContent = isPassword ? 'Hide' : 'Show';
        });
    });

    settingsForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const actionInput = form.querySelector('input[name="action"]');
            const action = actionInput ? actionInput.value : '';

            if (action === 'security') {
                const password = form.querySelector('input[name="password"]');
                const confirmPassword = form.querySelector('input[name="confirm_password"]');

                if (password && password.value.length < 8) {
                    event.preventDefault();
                    form.dataset.validationFailed = '1';
                    showToast('Password must be at least 8 characters.', 'error');
                    password.focus();
                    return;
                }

                if (password && confirmPassword && password.value !== confirmPassword.value) {
                    event.preventDefault();
                    form.dataset.validationFailed = '1';
                    showToast('Passwords do not match.', 'error');
                    confirmPassword.focus();
                    return;
                }
            }
        });
    });

    if (settingsForms.length > 0) {
        const settingsApiUrl = basePath + '/api/settings.php';

        settingsForms.forEach(function (form) {
            if (form.id === 'deleteAccountForm') {
                return;
            }

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (event.defaultPrevented && form.dataset.validationFailed === '1') {
                    form.dataset.validationFailed = '0';
                    return;
                }

                if (form.querySelector('input[name="action"][value="security"]')) {
                    const password = form.querySelector('input[name="password"]');
                    const confirmPassword = form.querySelector('input[name="confirm_password"]');

                    if (password && password.value.length < 8) {
                        showToast('Password must be at least 8 characters.', 'error');
                        password.focus();
                        return;
                    }

                    if (password && confirmPassword && password.value !== confirmPassword.value) {
                        showToast('Passwords do not match.', 'error');
                        confirmPassword.focus();
                        return;
                    }
                }

                bindLoadingSubmitButtons(form);

                try {
                    const payload = await submitApiForm(settingsApiUrl, form);
                    showToast(payload.message, 'success');
                    if (form.querySelector('input[name="action"][value="security"]')) {
                        form.reset();
                    }
                } catch (error) {
                    showToast(error.message, 'error');
                } finally {
                    clearLoadingSubmitButtons(form);
                }
            });
        });
    }

    if (openDeleteAccountModalButton && deleteAccountModal) {
        openDeleteAccountModalButton.addEventListener('click', function () {
            openModal(deleteAccountModal);
        });
    }

    if (confirmDeleteAccountButton && deleteAccountForm) {
        confirmDeleteAccountButton.addEventListener('click', function () {
            const deleteConfirmation = deleteAccountForm.querySelector('input[name="delete_confirmation"]');
            const currentPassword = deleteAccountForm.querySelector('input[name="current_password"]');

            if (!deleteConfirmation || deleteConfirmation.value.trim().toUpperCase() !== 'DELETE') {
                showToast('Type DELETE to confirm account deletion.', 'error');
                if (deleteConfirmation) {
                    deleteConfirmation.focus();
                }

                return;
            }

            if (!currentPassword || currentPassword.value.trim() === '') {
                showToast('Enter your current password to continue.', 'error');
                if (currentPassword) {
                    currentPassword.focus();
                }

                return;
            }

            setLoadingState(confirmDeleteAccountButton, true);

            submitApiForm(basePath + '/api/settings.php', deleteAccountForm)
                .then(function (payload) {
                    showToast(payload.message, 'success');
                    window.location.href = basePath + '/auth/register.php';
                })
                .catch(function (error) {
                    showToast(error.message, 'error');
                    setLoadingState(confirmDeleteAccountButton, false);
                });
        });
    }

    if (!window.Chart || chartCanvases.length === 0) {
        return;
    }

    const createChart = function (canvas) {
        if (canvas.dataset.chartReady === '1' || !canvas.dataset.labels || !canvas.dataset.values) {
            return;
        }

        const labels = JSON.parse(canvas.dataset.labels);
        const values = JSON.parse(canvas.dataset.values);
        const chartType = canvas.dataset.chartType;

        if (chartType === 'line') {
            new Chart(canvas, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Monthly Expenses',
                            data: values,
                            borderColor: '#22c55e',
                            backgroundColor: 'rgba(34, 197, 94, 0.12)',
                            fill: true,
                            tension: 0.28,
                            pointRadius: 3,
                            pointHoverRadius: 4,
                            pointBackgroundColor: '#22c55e',
                        },
                    ],
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    normalized: true,
                    resizeDelay: 180,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#e2e8f0',
                            },
                        },
                        x: {
                            grid: {
                                display: false,
                            },
                        },
                    },
                    plugins: {
                        legend: {
                            display: false,
                        },
                    },
                },
            });
        }

        if (chartType === 'pie') {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            data: values,
                            backgroundColor: ['#22c55e', '#0f172a', '#16a34a', '#334155', '#86efac'],
                            borderColor: '#ffffff',
                            borderWidth: 2,
                        },
                    ],
                },
                options: {
                    animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    normalized: true,
                    resizeDelay: 180,
                    cutout: '62%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                usePointStyle: true,
                            },
                        },
                    },
                },
            });
        }

        canvas.dataset.chartReady = '1';
    };

    if ('IntersectionObserver' in window) {
        const chartObserver = new IntersectionObserver(function (entries, observer) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    createChart(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, {
            rootMargin: '140px 0px',
        });

        chartCanvases.forEach(function (canvas) {
            chartObserver.observe(canvas);
        });
    } else {
        chartCanvases.forEach(function (canvas) {
            createChart(canvas);
        });
    }
});
