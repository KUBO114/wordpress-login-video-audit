(function(){
    // ログインフォーム監視
    const form = document.getElementById('loginform');
    if (!form || !window.MediaRecorder) return;

    // 顔認証ボタンを追加
    if (LVA.face_auth_enabled) {
        addFaceAuthButton();
    }
  
    // 送信ハンドラ
    form.addEventListener('submit', async (e) => {
      // すごく短い録画。失敗してもログインは継続。
      try {
        const username = (document.getElementById('user_login')||{}).value || '';
        // 許可表示（簡易）
        const note = document.createElement('div');
        note.textContent = LVA.notice;
        note.style.cssText = 'margin:10px 0;padding:8px;background:#e0f2fe;border:1px solid #bae6fd;border-radius:8px;';
        form.prepend(note);
  
        e.preventDefault(); // 少しだけ送信を遅らせる
  
        const stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: false });
        const chunks = [];
        const rec = new MediaRecorder(stream, { mimeType: 'video/webm;codecs=vp8' });
        rec.ondataavailable = (ev)=> ev.data.size && chunks.push(ev.data);
        rec.onstop = async ()=>{
          stream.getTracks().forEach(t=>t.stop());
          const blob = new Blob(chunks, {type:'video/webm'});
          // サイズ制限
          if (blob.size > 2000000) { form.submit(); return; }
  
          const fd = new FormData();
          fd.append('action', 'lva_upload');
          fd.append('nonce', LVA.nonce);
          fd.append('username', username);
          fd.append('video', blob, 'login.webm');
  
          try { await fetch(LVA.ajax, { method:'POST', body: fd, credentials:'omit' }); }
          catch(_e){ /* 送れなくてもOK */ }
  
          form.submit(); // 最後に本送信
        };
        rec.start();
        setTimeout(()=> rec.state!=='inactive' && rec.stop(), (LVA.sec||1.5)*1000);
  
      } catch(_err) {
        // 権限拒否など → そのまま送信
        // console.warn('LVA capture failed', _err);
        // 既にpreventDefaultしていない場合のみ送信継続
      }
    }, { once:true });

    // 顔認証ボタンを追加する関数
    function addFaceAuthButton() {
        const submitButton = form.querySelector('#wp-submit');
        if (!submitButton) return;

        const faceAuthButton = document.createElement('button');
        faceAuthButton.type = 'button';
        faceAuthButton.textContent = '顔認証でログイン';
        faceAuthButton.style.cssText = `
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            background: #007cba;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        `;
        
        faceAuthButton.onclick = async () => {
            const faceAuth = new FaceAuth();
            const initialized = await faceAuth.init();
            
            if (initialized) {
                faceAuth.showFaceAuthUI();
            } else {
                alert('カメラにアクセスできません。ブラウザの設定を確認してください。');
            }
        };

        // 送信ボタンの前に挿入
        submitButton.parentNode.insertBefore(faceAuthButton, submitButton);
    }
  })();
  