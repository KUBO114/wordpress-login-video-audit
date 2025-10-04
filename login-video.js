(function() {
  const form = document.getElementById('loginform');
  if (!form || !window.MediaRecorder) return;

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    try {
      const username = (document.getElementById('user_login') || {}).value || '';
      const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
      const chunks = [];
      const recorder = new MediaRecorder(stream, { mimeType: 'video/webm' });
      
      recorder.ondataavailable = (ev) => ev.data.size && chunks.push(ev.data);
      recorder.onstop = async () => {
        stream.getTracks().forEach(t => t.stop());
        const blob = new Blob(chunks, { type: 'video/webm' });
        
        if (blob.size <= 2000000) {
          const fd = new FormData();
          fd.append('action', 'lva_upload');
          fd.append('nonce', LVA.nonce);
          fd.append('username', username);
          fd.append('video', blob, 'login.webm');
          
          try {
            await fetch(LVA.ajax, { method: 'POST', body: fd });
          } catch (e) {}
        }
        
        form.submit();
      };
      
      recorder.start();
      setTimeout(() => recorder.stop(), 1500);
    } catch (e) {
      form.submit();
    }
  }, { once: true });
})();
  