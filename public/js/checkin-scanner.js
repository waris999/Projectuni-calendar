(() => {
    const buttons = [...document.querySelectorAll('.checkin-scan-button')];
    if (!buttons.length) return;
    let active = null;
    let busy = false;
    const message = (row, text) => {
        const target = row.querySelector('.checkin-msg');
        target.textContent = text;
        target.style.color = '#b71c1c';
    };
    async function stop() {
        const previous = active;
        active = null;
        if (!previous) return;
        try { await previous.scanner.stop(); } catch { /* Camera may not have started. */ }
        try { previous.scanner.clear(); } catch { /* Already cleared. */ }
        previous.box.replaceChildren();
        previous.box.hidden = true;
        previous.button.textContent = '📷 สแกน QR Code';
        previous.button.setAttribute('aria-expanded', 'false');
    }
    buttons.forEach(button => button.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        button.disabled = true;
        const row = button.closest('.checkin-item');
        const box = row.querySelector('.checkin-scanner-box');
        try {
            const closing = active?.button === button;
            await stop();
            if (closing) return;
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                message(row, 'กล้องต้องเปิดผ่าน HTTPS หรือ localhost กรุณาเปิดเว็บผ่าน HTTPS หรือกรอกรหัสเช็คอินแทน');
                return;
            }
            if (typeof Html5Qrcode === 'undefined') {
                message(row, 'โหลดตัวสแกนไม่สำเร็จ กรุณาตรวจสอบอินเทอร์เน็ต หรือกรอกรหัสเช็คอินแทน');
                return;
            }
            message(row, '');
            box.hidden = false;
            const reader = document.createElement('div');
            reader.id = 'qrReader-' + button.dataset.eventId;
            box.replaceChildren(reader);
            const scanner = new Html5Qrcode(reader.id);
            const session = { scanner, box, button };
            active = session;
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: (width, height) => { const size = Math.min(220, Math.floor(Math.min(width, height) * .75)); return { width: size, height: size }; } },
                async text => {
                    if (busy || active !== session) return;
                    busy = true;
                    let code;
                    let errorMessage = '';
                    try {
                        const target = new URL(text, window.location.href);
                        const expected = new URL('checkin.php', window.location.href);
                        if (target.origin !== expected.origin || target.pathname !== expected.pathname
                            || !/^[1-9]\d*$/.test(target.searchParams.get('auto_event') || '')
                            || !/^[A-Za-z0-9]{1,10}$/.test(target.searchParams.get('auto_code') || '')) {
                            errorMessage = 'QR นี้ไม่ใช่รหัสเช็คอินของระบบ';
                        } else if (target.searchParams.get('auto_event') !== button.dataset.eventId) {
                            errorMessage = 'QR Code นี้เป็นของกิจกรรมอื่น กรุณาสแกน QR ของกิจกรรมที่เลือก';
                        } else { code = target.searchParams.get('auto_code'); }
                        await stop();
                        if (errorMessage) { message(row, errorMessage); return; }
                        row.querySelector('.checkin-code-input').value = code;
                        await submitCheckin(Number(button.dataset.eventId), row.querySelector('.checkin-confirm'));
                    } catch {
                        await stop();
                        message(row, 'ไม่สามารถอ่าน QR Code ได้ กรุณาสแกนใหม่ หรือกรอกรหัสเช็คอิน');
                    } finally { busy = false; }
                }
            );
            button.textContent = 'ปิดกล้อง';
            button.setAttribute('aria-expanded', 'true');
        } catch (error) {
            const reason = error?.name || String(error);
            await stop();
            if (/NotAllowed|Permission|denied/i.test(reason)) message(row, 'ไม่ได้รับอนุญาตให้ใช้กล้อง กรุณาเปิดสิทธิ์กล้อง หรือกรอกรหัสเช็คอิน');
            else if (/NotFound|DevicesNotFound/i.test(reason)) message(row, 'ไม่พบกล้องบนอุปกรณ์นี้ กรุณากรอกรหัสเช็คอินแทน');
            else message(row, 'เปิดกล้องไม่ได้ กรุณาปิดโปรแกรมที่ใช้กล้องแล้วลองใหม่ หรือกรอกรหัสเช็คอิน');
        } finally {
            busy = false;
            button.disabled = false;
        }
    }));
    window.addEventListener('pagehide', () => { void stop(); });
})();
