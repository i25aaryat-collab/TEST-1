document.addEventListener('submit', async (event) => {
    const form = event.target;
    const token = form.querySelector('input[data-sitekey][data-action]');
    if (!token || event.defaultPrevented) return;
    event.preventDefault();
    if (form.dataset.verifying) return;
    form.dataset.verifying = 'true';
    const message = form.querySelector('[data-error-for="captcha"]');
    message.setAttribute('role', 'status');
    message.textContent = 'Verifying…';
    let timer;
    try {
        const api = window.grecaptcha;
        if (!api) throw new Error();
        const result = await Promise.race([
            new Promise((resolve, reject) => api.ready(() => Promise.resolve().then(() => api.execute(token.dataset.sitekey, {action: token.dataset.action})).then(resolve, reject))),
            new Promise((_, reject) => { timer = setTimeout(reject, 12000); })
        ]);
        if (!result) throw new Error();
        token.value = result;
        HTMLFormElement.prototype.submit.call(form);
    } catch {
        token.value = '';
        message.textContent = 'Verification could not finish. Please try again.';
    } finally {
        clearTimeout(timer);
        delete form.dataset.verifying;
    }
});
