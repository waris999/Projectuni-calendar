(() => {
    const button = document.getElementById('openScannerBtn');
    const box = document.getElementById('qrScannerBox');
    if (!button || !box) return;
    let scanner = null;
    let busy = false;
    let opened = false;

    const message = text => {
        box.replaceChildren();
        const p = document.createElement('p');
        p.textContent = text;
        p.style.cssText = 'color:#b71c1c;font-size:13px;line-height:1.6';
        p.setAttribute('role', 'status');
        box.append(p);
    };
    async function stop() {
        const active = scanner;
        scanner = null;
        if (active) {
            try { await active.stop(); } catch { /* Starting may have failed. */ }
            try { active.clear(); } catch { /* Already cleared. */ }
        }
        opened = false;
        button.textContent = '📷 สแกน QR Code';
        button.setAttribute('aria-expanded', 'false');
    }
    button.setAttribute('aria-controls', 'qrScannerBox');
    button.setAttribute('aria-expanded', 'false');
    button.addEventListener('click', async () => {
        if (busy) return;
        busy = true;
        button.disabled = true;
        try {
            if (opened) {
                await stop();
                box.style.display = 'none';
                box.replaceChildren();
                return;
            }
            box.style.display = 'block';
            if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                message('กล้องต้องเปิดผ่าน HTTPS หรือ localhost หากใช้โทรศัพท์ ให้เปิดเว็บผ่าน HTTPS หรือกรอกรหัสเช็คอินแทน');
                return;
            }
            if (typeof Html5Qrcode === 'undefined') {
                message('โหลดตัวสแกนไม่สำเร็จ กรุณาตรวจสอบอินเทอร์เน็ต หรือกรอกรหัสเช็คอินแทน');
                return;
            }
            box.replaceChildren();
            const reader = document.createElement('div');
            reader.id = 'qrReader';
            box.append(reader);
            scanner = new Html5Qrcode('qrReader');
            opened = true;
            await scanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: (width, height) => { const size = Math.min(220, Math.floor(Math.min(width, height) * 0.75)); return { width: size, height: size }; } },
                async text => {
                    if (busy || !opened) return;
                    let target;
                    try {
                        target = new URL(text, window.location.href);
                        const expected = new URL('checkin.php', window.location.href);
                        if (target.origin !== expected.origin || target.pathname !== expected.pathname
                            || !/^[1-9]\d*$/.test(target.searchParams.get('auto_event') || '')
                            || !/^[A-Za-z0-9]{1,10}$/.test(target.searchParams.get('auto_code') || '')) throw Error();
                    } catch {
                        busy = true;
                        await stop();
                        message('QR นี้ไม่ใช่รหัสเช็คอินของระบบ กรุณาเปิดกล้องเพื่อลองใหม่ หรือกรอกรหัสเช็คอิน');
                        busy = false;
                        return;
                    }
                    busy = true;
                    await stop();
                    window.location.assign(target.href);
                }
            );
            button.textContent = 'ปิดกล้อง';
            button.setAttribute('aria-expanded', 'true');
        } catch (error) {
            const reason = error?.name || String(error);
            await stop();
            if (/NotAllowed|Permission|denied/i.test(reason)) message('ไม่ได้รับอนุญาตให้ใช้กล้อง เปิดสิทธิ์กล้องของเว็บไซต์แล้วลองใหม่ หรือกรอกรหัสเช็คอิน');
            else if (/NotFound|DevicesNotFound/i.test(reason)) message('ไม่พบกล้องบนอุปกรณ์นี้ กรุณาใช้อุปกรณ์ที่มีกล้อง หรือกรอกรหัสเช็คอิน');
            else message('เปิดกล้องไม่ได้ กล้องอาจถูกใช้งานอยู่ กรุณาปิดโปรแกรมที่ใช้กล้องแล้วลองใหม่ หรือกรอกรหัสเช็คอิน');
        } finally {
            busy = false;
            button.disabled = false;
        }
    });
    window.addEventListener('pagehide', () => { void stop(); });
})();
