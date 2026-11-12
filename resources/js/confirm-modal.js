const showConfirmModal = ({
    title = 'Confirm Action',
    message = '',
    confirmText = 'Confirm',
    showCancel = true,
} = {}) => new Promise((resolve) => {
    const confirmModal = document.getElementById('confirmModal');
    const confirmModalTitle = document.getElementById('confirmModalTitle');
    const confirmModalMessage = document.getElementById('confirmModalMessage');
    const confirmModalCancel = document.getElementById('confirmModalCancel');
    const confirmModalConfirm = document.getElementById('confirmModalConfirm');

    if (!confirmModal || !confirmModalTitle || !confirmModalMessage || !confirmModalCancel || !confirmModalConfirm) {
        resolve(false);
        return;
    }

    confirmModalTitle.textContent = title;
    confirmModalMessage.textContent = message;
    confirmModalConfirm.textContent = confirmText;
    confirmModalCancel.classList.toggle('hidden', !showCancel);
    confirmModal.classList.remove('hidden');
    confirmModal.classList.add('flex');
    confirmModal.setAttribute('aria-hidden', 'false');

    const closeModal = (result) => {
        confirmModal.classList.add('hidden');
        confirmModal.classList.remove('flex');
        confirmModal.setAttribute('aria-hidden', 'true');
        confirmModalCancel.classList.remove('hidden');
        confirmModalCancel.removeEventListener('click', handleCancel);
        confirmModalConfirm.removeEventListener('click', handleConfirm);
        confirmModal.removeEventListener('click', handleBackdrop);
        document.removeEventListener('keydown', handleEscape);
        resolve(result);
    };

    const handleCancel = () => closeModal(false);
    const handleConfirm = () => closeModal(true);
    const handleBackdrop = (event) => {
        if (event.target === confirmModal) {
            closeModal(false);
        }
    };
    const handleEscape = (event) => {
        if (event.key === 'Escape') {
            closeModal(false);
        }
    };

    confirmModalCancel.addEventListener('click', handleCancel);
    confirmModalConfirm.addEventListener('click', handleConfirm);
    confirmModal.addEventListener('click', handleBackdrop);
    document.addEventListener('keydown', handleEscape);
    confirmModalConfirm.focus();
});

window.showConfirmModal = showConfirmModal;
