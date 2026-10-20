(() => {
    const input = document.getElementById('registration-avatar');
    if (!input) return;
    const preview = document.getElementById('registration-avatar-preview');
    const clear = document.getElementById('registration-avatar-clear');
    const error = document.getElementById('registration-avatar-error');
    const defaultSrc = preview.getAttribute('src');
    let url;
    function reset() {
        if (url) URL.revokeObjectURL(url);
        url = null;
        preview.src = defaultSrc;
        clear.hidden = true;
    }
    input.addEventListener('change', () => {
        reset(); error.textContent = ''; input.setCustomValidity('');
        const file = input.files[0];
        if (!file) return;
        let message = '';
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) message = 'กรุณาเลือกไฟล์ JPG, PNG หรือ WEBP';
        else if (file.size > 2 * 1024 * 1024) message = 'รูปโปรไฟล์ต้องมีขนาดไม่เกิน 2 MB';
        if (message) { error.textContent = message; input.value = ''; return; }
        url = URL.createObjectURL(file); preview.src = url; clear.hidden = false;
    });
    clear.addEventListener('click', () => { input.value = ''; reset(); error.textContent = ''; input.setCustomValidity(''); });
    preview.addEventListener('error', () => {
        if (!url) return;
        input.value = ''; reset(); error.textContent = 'ไม่สามารถอ่านรูปนี้ได้ กรุณาเลือกรูปใหม่';
    });
    window.addEventListener('pagehide', () => { if (url) URL.revokeObjectURL(url); });
})();
