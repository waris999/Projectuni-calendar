<div class="registration-avatar">
    <label for="registration-avatar">รูปโปรไฟล์ <span>(ไม่บังคับ)</span></label>
    <div class="registration-avatar-row">
        <img id="registration-avatar-preview" src="data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22256%22%20height%3D%22256%22%20viewBox%3D%220%200%20256%20256%22%3E%3Cdefs%3E%3CclipPath%20id%3D%22circle%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%2F%3E%3C%2FclipPath%3E%3C%2Fdefs%3E%3Cg%20clip-path%3D%22url%28%23circle%29%22%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%22128%22%20r%3D%22128%22%20fill%3D%22%23ccc%22%2F%3E%3Ccircle%20cx%3D%22128%22%20cy%3D%2296%22%20r%3D%2248%22%20fill%3D%22%23999%22%2F%3E%3Cpath%20d%3D%22M32%20256v-24c0-42%2043-80%2096-80s96%2038%2096%2080v24z%22%20fill%3D%22%23999%22%2F%3E%3C%2Fg%3E%3C%2Fsvg%3E" width="72" height="72" alt="ตัวอย่างรูปโปรไฟล์">
        <div>
            <input type="file" id="registration-avatar" accept="image/jpeg,image/png,image/webp" aria-describedby="registration-avatar-hint">
            <button type="button" id="registration-avatar-clear" hidden>ใช้รูปเริ่มต้น</button>
        </div>
    </div>
    <p id="registration-avatar-hint">JPG, PNG หรือ WEBP ไม่เกิน 2 MB · ไม่ใส่รูปจะใช้รูปเริ่มต้น</p>
    <p id="registration-avatar-error" role="alert"></p>
</div>
<input type="hidden" name="avatar_data" id="avatar-data" value="">
<input type="hidden" name="avatar_selected" value="0">
