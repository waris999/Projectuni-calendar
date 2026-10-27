(() => {
    const input = document.getElementById('registration-avatar') || document.getElementById('avatarInput');
    if (!input) return;
    const form = input.closest('form');
    const hidden = form.querySelector('[name="avatar_data"]');
    const selected = form.querySelector('[name="avatar_selected"]');
    const preview = document.getElementById('registration-avatar-preview') || document.getElementById('avatarPreview');
    const clear = document.getElementById('registration-avatar-clear');
    const error = document.getElementById('registration-avatar-error');
    if (!hidden || !selected || !preview || !error) return;
    input.removeAttribute('name');
    const defaultSrc = preview.getAttribute('src');
    let generation = 0;
    let processing = false;
    const buttons = [...form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]')];
    const busy = value => { processing = value; buttons.forEach(button => button.disabled = value); };
    function reset() {
        generation++;
        input.value = ''; hidden.value = ''; selected.value = '0';
        input.setCustomValidity(''); preview.src = defaultSrc;
        if (clear) clear.hidden = true;
        error.textContent = ''; busy(false);
    }
    clear?.addEventListener('click', reset);
    input.addEventListener('change', async () => {
        const version = ++generation;
        const file = input.files[0];
        hidden.value = ''; input.setCustomValidity(''); error.textContent = '';
        selected.value = file ? '1' : '0';
        if (!file) { reset(); return; }
        if (clear) clear.hidden = false;
        busy(true); error.textContent = 'กำลังเตรียมรูป...';
        let url;
        try {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) throw new Error('กรุณาเลือกรูป JPG, PNG หรือ WEBP ไม่เกิน 2 MB');
            url = URL.createObjectURL(file);
            const image = new Image();
            await new Promise((resolve, reject) => { image.onload = resolve; image.onerror = () => reject(new Error('อ่านรูปนี้ไม่ได้ กรุณาเลือกรูปใหม่')); image.src = url; });
            if (version !== generation) return;
            const scale = Math.min(1, 512 / Math.max(image.naturalWidth, image.naturalHeight));
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(1, Math.round(image.naturalHeight * scale));
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff'; context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            let data = canvas.toDataURL('image/jpeg', .8);
            if (data.length > 450000) data = canvas.toDataURL('image/jpeg', .6);
            if (!data.startsWith('data:image/jpeg;base64,') || data.length > 450000) throw new Error('รูปมีขนาดใหญ่เกินไป กรุณาเลือกรูปอื่น');
            hidden.value = data; preview.src = data; error.textContent = '';
        } catch (problem) {
            if (version !== generation) return;
            hidden.value = ''; preview.src = defaultSrc;
            const message = problem.message || 'เตรียมรูปไม่สำเร็จ กรุณาเลือกรูปใหม่';
            error.textContent = message; input.setCustomValidity(message);
        } finally {
            if (url) URL.revokeObjectURL(url);
            if (version === generation) busy(false);
        }
    });
    form.addEventListener('submit', event => {
        if (processing || (selected.value === '1' && !hidden.value)) {
            event.preventDefault();
            error.textContent = processing ? 'รอเตรียมรูปให้เสร็จก่อนบันทึก' : 'กรุณาเลือกรูปใหม่ หรือยกเลิกรูปที่เลือก';
        }
    });
})();
