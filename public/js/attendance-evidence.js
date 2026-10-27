(() => {
    document.querySelectorAll('.attendance-evidence-form').forEach(form => {
        const input=form.querySelector('.evidence-file');
        const data=form.querySelector('[name="evidence_data"]');
        const preview=form.querySelector('.evidence-preview');
        const error=form.querySelector('.evidence-error');
        const button=form.querySelector('button[type="submit"]');
        let version=0, busy=false;
        input.addEventListener('change',async()=>{
            const current=++version;data.value='';input.setCustomValidity('');error.textContent='';preview.hidden=true;
            const file=input.files[0];if(!file){busy=false;button.disabled=false;return;}
            busy=true;button.disabled=true;error.textContent='กำลังเตรียมรูปหลักฐาน...';
            let url;
            try {
                if(!['image/jpeg','image/png','image/webp'].includes(file.type)||file.size>5*1024*1024)throw Error('เลือกรูป JPG, PNG หรือ WEBP ไม่เกิน 5 MB');
                url=URL.createObjectURL(file);const image=new Image();
                await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=()=>reject(Error('อ่านรูปไม่ได้ กรุณาเลือกรูปใหม่'));image.src=url;});
                if(current!==version)return;
                const scale=Math.min(1,1024/Math.max(image.naturalWidth,image.naturalHeight));const canvas=document.createElement('canvas');
                canvas.width=Math.max(1,Math.round(image.naturalWidth*scale));canvas.height=Math.max(1,Math.round(image.naturalHeight*scale));
                const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,canvas.width,canvas.height);ctx.drawImage(image,0,0,canvas.width,canvas.height);
                let value=canvas.toDataURL('image/jpeg',.75);if(value.length>1000000)value=canvas.toDataURL('image/jpeg',.5);
                if(!value.startsWith('data:image/jpeg;base64,')||value.length>1000000)throw Error('รูปใหญ่เกินไป กรุณาเลือกรูปอื่น');
                data.value=value;preview.src=value;preview.hidden=false;error.textContent='';
            }catch(e){if(current===version){error.textContent=e.message;input.setCustomValidity(e.message);}}
            finally{if(url)URL.revokeObjectURL(url);if(current===version){busy=false;button.disabled=false;}}
        });
        form.addEventListener('submit',event=>{
            if(busy||!data.value){event.preventDefault();error.textContent=busy?'กรุณารอเตรียมรูปให้เสร็จ':'กรุณาเลือกรูปหลักฐานก่อนส่ง';return;}
            button.disabled=true;button.textContent='กำลังส่งหลักฐาน...';
        });
    });
})();
