document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-ai-form]');
    if (! form) return;

    event.preventDefault();
    const feedback = form.querySelector('[data-ai-feedback]');
    const button = form.querySelector('[type="submit"]');
    const data = Object.fromEntries(new FormData(form));
    delete data._token;
    button.disabled = true;
    if (feedback) feedback.textContent = 'Đang xử lý...';

    try {
        const response = await fetch(form.action, {
            method: form.dataset.method || form.method,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
            body: JSON.stringify(data),
        });
        const result = await response.json();
        if (! response.ok) {
            const errors = Object.values(result.errors || {}).flat();
            throw new Error(errors.join(' ') || result.message || 'Thao tác thất bại.');
        }

        if (form.dataset.reload === 'true') {
            window.location.reload();
            return;
        }
        if (feedback) feedback.textContent = result.run_id ? `Đã tạo run #${result.run_id}. Mở trang Pipeline để theo dõi.` : 'Đã lưu.';
    } catch (error) {
        if (feedback) feedback.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});
